<?php

namespace App\Controllers\conge;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\conge\ValidationCongeModel;
use App\Models\conge\CongeModel;
use App\Models\conge\SignatureModel;
use App\Services\CongeValidationService;
use App\Services\EmailService;

class ValidationCongeController extends ResourceController
{
    use ResponseTrait;

    private CongeValidationService $validationService;

    public function __construct()
    {
        $this->validationService = new CongeValidationService();
    }

    /**
     * Get validation status for a leave request
     * Returns all validation steps with their current status
     */
    public function getStatus($cngCode = null)
    {
        if (!$cngCode) {
            return $this->fail('cng_code requis');
        }

        $congeModel = new CongeModel();
        $conge = $congeModel->find($cngCode);
        
        if (!$conge) {
            return $this->failNotFound('Congé non trouvé');
        }

        $validationModel = new ValidationCongeModel();
        // SignatureModel not really needed for mapping anymore since we have code in steps

        // 1. Get Dynamic Steps from Service (returns [{'step'=>'...', 'code'=>X}, ...])
        $stepObjects = $this->validationService->getValidationSteps($conge['emp_code']);

        // 2. Get existing validations
        $validations = $validationModel
            ->where('cng_code', $cngCode)
            ->findAll();

        $validationMap = [];
        foreach ($validations as $v) {
            $validationMap[$v['sign_code']] = $v;
        }

        // 3. Build Status
        $steps = [];
        $isRejected = false;
        $currentStep = null;
        $allValidated = true;

        if (empty($stepObjects)) {
            $allValidated = true;
        }

        foreach ($stepObjects as $stepObj) {
            $stepName = $stepObj['step'];
            $stepCode = $stepObj['code'];
            
            $validation = $validationMap[$stepCode] ?? null;

            $status = 'pending';
            if ($validation) {
                // Determine boolean status
                $valStatus = $validation['val_status'];
                if ($valStatus === true || $valStatus === 't' || $valStatus === '1' || $valStatus === 1) {
                    $status = 'validated';
                } elseif ($valStatus === false || $valStatus === 'f' || $valStatus === '0'|| $valStatus === 0) {
                    $status = 'rejected';
                    $isRejected = true;
                }
            }

            if ($status !== 'validated') {
                $allValidated = false;
            }

            if ($status === 'pending' && $currentStep === null && !$isRejected) {
                $currentStep = $stepName;
            }

            $steps[] = [
                'step' => $stepName,
                'sign_code' => $stepCode, // Using the dynamic code from Service
                'status' => $status,
                'val_date' => $validation['val_date'] ?? null,
                'val_observation' => $validation['val_observation'] ?? null
            ];
        }

        return $this->respond([
            'cng_code' => $cngCode,
            'cng_status' => $conge['cng_status'],
            'is_rejected' => $isRejected,
            'is_fully_validated' => $allValidated && !$isRejected,
            'current_step' => $isRejected ? null : $currentStep,
            'steps' => $steps
        ]);
    }

    /**
     * Get the current step info
     */
    public function getCurrentStep($cngCode = null)
    {
        $statusResponse = $this->getStatus($cngCode);
        $data = json_decode($statusResponse->getBody(), true);
        
        if (!isset($data['current_step'])) {
            return $this->respond([
                'current_step' => null,
                'message' => $data['is_rejected'] ? 'Demande rejetée' : 'Toutes les validations sont terminées'
            ]);
        }

        // Return current step name
        return $this->respond([
            'current_step' => $data['current_step'],
            'message' => 'En attente de validation par ' . $data['current_step']
        ]);
    }

    /**
     * Get validation steps applicable for an employee (Public Endpoint)
     */
    public function getStepsForEmployee($empCode = null)
    {
        if (!$empCode) {
            return $this->fail('emp_code requis');
        }

        $steps = $this->validationService->getValidationSteps((int)$empCode);

        // Get Poste for info
        $db = \Config\Database::connect();
        $emp = $db->table('employee e')
             ->select('p.pst_fonction')
             ->join('affectation a', 'a.emp_code = e.emp_code')
             ->join('poste p', 'p.pst_code = a.pst_code')
             ->where('e.emp_code', $empCode)
             ->get()->getRowArray();

        return $this->respond([
            'emp_code' => $empCode,
            'poste' => $emp['pst_fonction'] ?? 'N/A',
            'steps' => $steps
        ]);
    }

    /**
     * Approve a step
     */
    public function approveStep()
    {
        $data = $this->request->getJSON(true);
        // Fix: JWTFilter sets $request->admin, not 'user' var.
        $userObj = $this->request->admin ?? null;
        $user = $userObj ? (array)$userObj : null;
        
        log_message('error', "DEBUG AUTH: User Payload (Fixed): " . print_r($user, true));

        if (!isset($data['cng_code'], $data['sign_code'])) {
            return $this->fail('cng_code et sign_code requis');
        }

        $cngCode = (int)$data['cng_code'];
        $signCode = (int)$data['sign_code'];
        $observation = $data['observation'] ?? '';

        // Vérifier que l'utilisateur a le droit de signer pour ce rôle
        // BYPASS SUPER ADMIN (Role 0)
        $isAdmin = isset($user['role']) && (int)$user['role'] === 0;

        if (!$isAdmin) {
             $signatureModel = new SignatureModel();
             $sigOwner = $signatureModel->where('sign_code', $signCode)
                                        ->where('emp_code', $user['emp_code'] ?? 0)
                                        ->first();
             
             if (!$sigOwner) {
                 return $this->failForbidden("Vous n'êtes pas autorisé à valider pour ce rôle (Signature non assignée à votre compte).");
             }
        }

        $congeModel = new CongeModel();
        $conge = $congeModel->find($cngCode);
        if (!$conge) return $this->failNotFound('Congé non trouvé');

        $signatureModel = new SignatureModel();
        $signature = $signatureModel->find($signCode);
        if (!$signature) return $this->failNotFound('Signature non trouvée');

        // Check Hierarchical Status
        $stepObjects = $this->validationService->getValidationSteps($conge['emp_code']);
        
        // Check Status
        $statusResp = $this->getStatus($cngCode);
        $status = json_decode($statusResp->getBody(), true);

        if ($status['is_rejected']) return $this->fail('Demande rejetée');
        if (!$status['current_step']) return $this->fail('Aucune étape en attente');

        // Find Expected Code
        $expectedCode = null;
        foreach($status['steps'] as $s) {
            if ($s['step'] === $status['current_step']) {
                $expectedCode = $s['sign_code'];
                break;
            }
        }

        if ($signCode !== $expectedCode) {
            return $this->fail("Ce n'est pas votre tour. Attendu: {$status['current_step']} (Code $expectedCode), Reçu: $signCode");
        }
        
        $currentStepName = $status['current_step'];

        // Validate
        $validationModel = new ValidationCongeModel();
        // Check duplicate or pending
        // ... (Existing update logic is fine)
        $existing = $validationModel->where('cng_code', $cngCode)->where('sign_code', $signCode)->first();
        
        $saveData = [
            'val_date' => date('Y-m-d'),
            'val_status' => true,
            'val_observation' => $observation
        ];

        if ($existing) {
            $s = $existing['val_status'];
            if ($s === true || $s === 't' || $s === 1 || $s === '1') {
                return $this->fail('Déjà validé');
            }
            $validationModel->where('cng_code', $cngCode)->where('sign_code', $signCode)->set($saveData)->update();
        } else {
            $validationModel->insert(array_merge(['cng_code' => $cngCode, 'sign_code' => $signCode], $saveData));
        }

        // NEXT STEP LOGIC
        $stepNames = array_column($stepObjects, 'step');
        $idx = array_search($currentStepName, $stepNames);
        
        if ($idx !== false && isset($stepObjects[$idx + 1])) {
            $nextStep = $stepObjects[$idx + 1];
            $nextStepName = $nextStep['step'];
            $nextStepCode = $nextStep['code'];
            
            // Notify Next Validator with CODE
            $this->notifyValidator($conge, $nextStepCode);
            
            return $this->respond([
                'success' => true,
                'message' => "Validé. Prochaine étape : $nextStepName",
                'final' => false,
                'next_step' => $nextStepName
            ]);
        } else {
            // Final Validation (End of chain)
            $this->finalizeValidation($cngCode);
            
            // Notify Employee
            $this->notifyCompletion($conge);

            return $this->respond([
                'success' => true,
                'message' => 'Validation finale effectuée !',
                'final' => true
            ]);
        }
    }

    /**
     * Reject a step
     */
    public function reject()
    {
        $data = $this->request->getJSON(true);
        // ... (Similar logic, simplified for brevity here, assumed correct from previous)
        // Copying standard rejection logic but ensuring dynamic checks
        
        if (!isset($data['cng_code'], $data['sign_code'])) return $this->fail('Données manquantes');
        $cngCode = $data['cng_code'];
        $signCode = $data['sign_code'];
        $observation = $data['observation'] ?? 'Rejeté';

        $congeModel = new CongeModel();
        $conge = $congeModel->find($cngCode);
        if(!$conge) return $this->failNotFound('Congé introuvable');
        
        $signatureModel = new SignatureModel();
        $signature = $signatureModel->find($signCode);
        
        // Status check
        // Check Hierarchical Status (For consistency check)
        $stepObjects = $this->validationService->getValidationSteps($conge['emp_code']);

        $statusResp = $this->getStatus($cngCode);
        $status = json_decode($statusResp->getBody(), true);
        
        if (!$status['current_step']) return $this->fail("Pas d'étape en cours");

        // Find Expected Code
        $expectedCode = null;
        foreach($status['steps'] as $s) {
            if ($s['step'] === $status['current_step']) {
                $expectedCode = $s['sign_code'];
                break;
            }
        }

        if($signCode !== $expectedCode) {
             return $this->fail("Ce n'est pas votre tour. Attendu: {$status['current_step']} (Code $expectedCode), Reçu: $signCode");
        }

        // Insert rejection
        $validationModel = new ValidationCongeModel();
        
        // Check existing
        $existing = $validationModel->where('cng_code', $cngCode)->where('sign_code', $signCode)->first();
        
        $rejectData = [
            'val_date' => date('Y-m-d'),
            'val_status' => false,
            'val_observation' => $observation
        ];

        if ($existing) {
            if ($existing['val_status'] !== null) {
                return $this->fail('Déjà traité');
            }
            $validationModel->where('cng_code', $cngCode)->where('sign_code', $signCode)->set($rejectData)->update();
        } else {
            $validationModel->insert(array_merge(['cng_code' => $cngCode, 'sign_code' => $signCode], $rejectData));
        }

        // Update global status
        $congeModel->update($cngCode, ['cng_status' => false]);

        // Notify
        $this->notifyRejection($conge, $signature['sign_libele'], $observation);

        return $this->respond(['success' => true, 'message' => 'Rejet enregistré']);
    }

    // --- Helpers / Actions ---

    private function finalizeValidation($cngCode)
    {
        $congeModel = new CongeModel();
        
        try {
            $conge = $congeModel->find($cngCode);
            
            if ($conge) {
                // Update status
                $congeModel->update($cngCode, ['cng_status' => true]);
                
                // Debit Solde logic with safety
                try {
                    $this->debitSolde($conge);
                } catch (\Throwable $e) {
                    log_message('critical', "Erreur lors du débit de solde pour cng $cngCode: " . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
             log_message('error', "Erreur critique finalizeValidation: " . $e->getMessage());
        }
    }

    /**
     * Calcul et enregistrement du débit de solde (FIFO)
     */
    private function debitSolde(array $conge)
    {
        $db = \Config\Database::connect();
        $joursRestantsADeduire = (float)$conge['cng_nb_jour']; // Float handles .5 days if any
        $empCode = $conge['emp_code'];

        // 1. Récupérer les soldes positifs (FIFO: plus ancien en premier)
        $soldes = $db->table('solde_conge')
                     ->where('emp_code', $empCode)
                     ->where('sld_restant >', 0)
                     ->orderBy('sld_anne', 'ASC')
                     ->get()->getResultArray();

        foreach ($soldes as $solde) {
            if ($joursRestantsADeduire <= 0) break;

            $dispo = (float)$solde['sld_restant'];
            // Déduire ce qu'on peut de ce solde
            $deduction = min($dispo, $joursRestantsADeduire);

            if ($deduction > 0) {
                // a. Insert Mouvement Débit
                $db->table('debit_solde_cng')->insert([
                    'deb_date' => date('Y-m-d'),
                    'deb_jr' => $deduction, // Corrected column name based on Model
                    'cng_code' => $conge['cng_code'],
                    'sld_code' => $solde['sld_code']
                ]);

                // b. Update Solde Restant
                $nouveauReste = $dispo - $deduction;
                $db->table('solde_conge')
                   ->where('sld_code', $solde['sld_code'])
                   ->update(['sld_restant' => $nouveauReste]);

                $joursRestantsADeduire -= $deduction;
            }
        }
        
        if ($joursRestantsADeduire > 0) {
            log_message('warning', "Solde insuffisant pour couvrir tout le congé $cngCode. Reste non déduit: $joursRestantsADeduire");
        }
    }



    private function notifyValidator($conge, int $signCode)
    {
        // Récupérer les détails complets du congé (avec nom_emp, prenom_emp, etc.)
        $congeDetails = $this->getCongeDetails($conge['cng_code']);
        
        if (!$congeDetails) {
            log_message('error', "[notifyValidator] Impossible de récupérer les détails pour cng_code=" . $conge['cng_code']);
            return;
        }
        
        // Use Service directly avec le CODE
        $this->validationService->sendValidationNotification($congeDetails, $signCode);
    }

    private function notifyCompletion($conge)
    {
        try {
            // Get details
            $details = $this->getCongeDetails($conge['cng_code']);
            if (!$details) throw new \Exception("Details introuvables pour cng " . $conge['cng_code']);

            $emailService = new EmailService();
            $emailService->sendValidationComplete(
                $details['emp_mail'], 
                $details['nom_emp'] . ' ' . $details['prenom_emp'], 
                $details
            );
        } catch (\Throwable $e) {
            log_message('error', "Erreur NotifyCompletion: " . $e->getMessage());
        }
    }

    private function notifyRejection($conge, $rejetePar, $motif)
    {
        $details = $this->getCongeDetails($conge['cng_code']);
        $emailService = new EmailService();
        
        // Notify Employee
        $emailService->sendRejectionNotice(
            $details['emp_mail'], 
            $details['nom_emp'] . ' ' . $details['prenom_emp'], 
            $details, 
            $rejetePar, 
            $motif
        );

        // Notify previous validators
        // ... (reuse logic from ValidationEmailController or rewrite here)
        // For simplicity, calling ValidationEmailController method if public, or rewrite.
        // Rewriting for safety:
        
        $db = \Config\Database::connect();
        $previous = $db->table('validation_cng v')
            ->select('e.emp_mail')
            ->join('employee e', 'e.sign_code = v.sign_code')
            ->where('v.cng_code', $conge['cng_code'])
            ->where('v.val_status', true)
            ->get()->getResultArray();
            
        foreach($previous as $prev) {
            if($prev['emp_mail']) {
                $emailService->sendRejectionNotice($prev['emp_mail'], 'Validateur', $details, $rejetePar, $motif);
            }
        }
    }

    private function getCongeDetails($cngCode)
    {
        $db = \Config\Database::connect();
        return $db->table('conge c')
            ->select('c.*, e.emp_nom as nom_emp, e.emp_prenom as prenom_emp, e.emp_mail, e.emp_imarmp as matricule, t.typ_appelation, r.reg_nom as nom_region')
            ->join('employee e', 'e.emp_code = c.emp_code')
            ->join('type_conge t', 't.typ_code = c.typ_code')
            ->join('region r', 'r.reg_code = c.reg_code')
            ->where('c.cng_code', $cngCode)
            ->get()->getRowArray();
    }
}
