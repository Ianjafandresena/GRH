<?php

namespace App\Controllers\conge;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\conge\ValidationCongeModel;
use App\Models\conge\CongeModel;
use App\Models\conge\SignatureModel;
use App\Models\conge\SoldeCongeModel;
use App\Models\conge\DebitSoldeCngModel;

class ValidationCongeController extends ResourceController
{
    use ResponseTrait;

    // Ordre des étapes de validation
    const VALIDATION_ORDER = ['CHEF', 'RRH', 'DAAF', 'DG'];

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
        $signatureModel = new SignatureModel();

        // Get all signatures in order
        $signatures = $signatureModel->findAll();
        $signatureMap = [];
        foreach ($signatures as $s) {
            $signatureMap[$s['sign_libele']] = $s;
        }

        // Get existing validations for this leave
        $validations = $validationModel
            ->select('validation_cng.*, signature.sign_libele')
            ->join('signature', 'signature.sign_code = validation_cng.sign_code', 'left')
            ->where('validation_cng.cng_code', $cngCode)
            ->findAll();

        $validationMap = [];
        foreach ($validations as $v) {
            $validationMap[$v['sign_libele']] = $v;
        }

        // Build status for each step
        $steps = [];
        $isRejected = false;
        $currentStep = null;
        $allValidated = true;

        foreach (self::VALIDATION_ORDER as $stepName) {
            $signature = $signatureMap[$stepName] ?? null;
            $validation = $validationMap[$stepName] ?? null;

            $status = 'pending'; // pending, validated, rejected
            if ($validation) {
                // PostgreSQL returns boolean as 't'/'f' strings
                $valStatus = $validation['val_status'];
                if ($valStatus === true || $valStatus === 't' || $valStatus === '1' || $valStatus === 1) {
                    $status = 'validated';
                } elseif ($valStatus === false || $valStatus === 'f' || $valStatus === '0' || $valStatus === 0) {
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
                'sign_code' => $signature['sign_code'] ?? null,
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
     * Get the current step info (who should validate next)
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

        $signatureModel = new SignatureModel();
        $signature = $signatureModel->where('sign_libele', $data['current_step'])->first();

        return $this->respond([
            'current_step' => $data['current_step'],
            'sign_code' => $signature['sign_code'] ?? null,
            'message' => 'En attente de validation par ' . $data['current_step']
        ]);
    }

    /**
     * Approve a step (renamed from validate to avoid conflict with CodeIgniter base)
     */
    public function approveStep()
    {
        $data = $this->request->getJSON(true);

        if (!isset($data['cng_code'], $data['sign_code'])) {
            return $this->fail('cng_code et sign_code requis');
        }

        $cngCode = (int)$data['cng_code'];
        $signCode = (int)$data['sign_code'];
        $observation = $data['observation'] ?? '';

        // Verify the leave exists and is not already validated/rejected globally
        $congeModel = new CongeModel();
        $conge = $congeModel->find($cngCode);

        if (!$conge) {
            return $this->failNotFound('Congé non trouvé');
        }

        // Get signature info
        $signatureModel = new SignatureModel();
        $signature = $signatureModel->find($signCode);

        if (!$signature) {
            return $this->failNotFound('Signature non trouvée');
        }

        // Check if this is the correct step
        $statusResponse = $this->getStatus($cngCode);
        $statusData = json_decode($statusResponse->getBody(), true);

        if ($statusData['is_rejected']) {
            return $this->fail('Cette demande a déjà été rejetée');
        }

        if ($statusData['current_step'] !== $signature['sign_libele']) {
            return $this->fail('Ce n\'est pas votre tour de valider. Étape actuelle: ' . $statusData['current_step']);
        }

        // Check if validation already exists
        $validationModel = new ValidationCongeModel();
        $existing = $validationModel
            ->where('cng_code', $cngCode)
            ->where('sign_code', $signCode)
            ->first();

        if ($existing) {
            return $this->fail('Cette étape a déjà été traitée');
        }

        // Create validation record
        $validationModel->insert([
            'cng_code' => $cngCode,
            'sign_code' => $signCode,
            'val_date' => date('Y-m-d'),
            'val_status' => true,
            'val_observation' => $observation
        ]);

        // Check if this was the last step (DG)
        if ($signature['sign_libele'] === 'DG') {
            // All validated! Update conge status and debit balance
            $result = $this->finalizeValidation($cngCode);
            
            if (!$result) {
                log_message('error', 'finalizeValidation failed for cng_code: ' . $cngCode);
                return $this->fail('Erreur lors de la finalisation de la validation');
            }
            
            return $this->respond([
                'success' => true,
                'message' => 'Validation finale effectuée. Le congé est maintenant validé et le solde débité.',
                'final' => true
            ]);
        }

        return $this->respond([
            'success' => true,
            'message' => 'Validation effectuée par ' . $signature['sign_libele'],
            'final' => false
        ]);
    }

    /**
     * Reject a step (stops the whole process)
     */
    public function reject()
    {
        $data = $this->request->getJSON(true);

        if (!isset($data['cng_code'], $data['sign_code'])) {
            return $this->fail('cng_code et sign_code requis');
        }

        $cngCode = (int)$data['cng_code'];
        $signCode = (int)$data['sign_code'];
        $observation = $data['observation'] ?? 'Rejeté';

        $congeModel = new CongeModel();
        $conge = $congeModel->find($cngCode);

        if (!$conge) {
            return $this->failNotFound('Congé non trouvé');
        }

        $signatureModel = new SignatureModel();
        $signature = $signatureModel->find($signCode);

        if (!$signature) {
            return $this->failNotFound('Signature non trouvée');
        }

        // Check current step
        $statusResponse = $this->getStatus($cngCode);
        $statusData = json_decode($statusResponse->getBody(), true);

        if ($statusData['is_rejected']) {
            return $this->fail('Cette demande a déjà été rejetée');
        }

        if ($statusData['current_step'] !== $signature['sign_libele']) {
            return $this->fail('Ce n\'est pas votre tour de valider. Étape actuelle: ' . $statusData['current_step']);
        }

        // Create rejection record
        $validationModel = new ValidationCongeModel();
        $validationModel->insert([
            'cng_code' => $cngCode,
            'sign_code' => $signCode,
            'val_date' => date('Y-m-d'),
            'val_status' => false,
            'val_observation' => $observation
        ]);

        // Update conge status to null (rejected)
        $congeModel->update($cngCode, ['cng_status' => null]);

        return $this->respond([
            'success' => true,
            'message' => 'Demande rejetée par ' . $signature['sign_libele'],
            'reason' => $observation
        ]);
    }

    /**
     * Finalize validation: set cng_status = true and debit balance
     */
    private function finalizeValidation($cngCode)
    {
        try {
            log_message('info', 'finalizeValidation started for cng_code: ' . $cngCode);
            
            $congeModel = new CongeModel();
            $conge = $congeModel->find($cngCode);

            if (!$conge) {
                log_message('error', 'finalizeValidation: conge not found for cng_code: ' . $cngCode);
                return false;
            }

            // Update status to validated - use raw SQL for PostgreSQL boolean
            $db = \Config\Database::connect();
            $updateResult = $db->query("UPDATE conge SET cng_status = true WHERE cng_code = ?", [$cngCode]);
            
            log_message('info', 'finalizeValidation: cng_status updated to true for cng_code: ' . $cngCode);

            // Debit balance from solde_conge (oldest first - FIFO)
            $empCode = $conge['emp_code'];
            $joursADebiter = (float) $conge['cng_nb_jour'];

            log_message('info', 'finalizeValidation: emp_code=' . $empCode . ', jours=' . $joursADebiter);

            $soldeModel = new SoldeCongeModel();
            $debitModel = new DebitSoldeCngModel();

            $reliquats = $soldeModel
                ->where('emp_code', $empCode)
                ->where('sld_restant >', 0)
                ->orderBy('sld_anne', 'ASC')
                ->findAll();

            log_message('info', 'finalizeValidation: Found ' . count($reliquats) . ' reliquats');

            if (empty($reliquats)) {
                // Pas de solde à débiter - le congé est quand même validé
                log_message('info', 'finalizeValidation: No reliquats to debit, returning true');
                return true;
            }

            $reste = $joursADebiter;
            foreach ($reliquats as $reliq) {
                if ($reste <= 0) break;
                
                $disponible = (float) $reliq['sld_restant'];
                $debit = min($reste, $disponible);
                $newRestant = $disponible - $debit;
                
                log_message('info', 'finalizeValidation: Debiting ' . $debit . ' from sld_code=' . $reliq['sld_code']);
                
                // Update solde_conge
                $soldeModel->update($reliq['sld_code'], [
                    'sld_restant' => $newRestant,
                    'sld_maj' => date('Y-m-d H:i:s')
                ]);
                
                // Record debit in debit_solde_cng for tracking
                $debitModel->insert([
                    'emp_code' => $empCode,
                    'sld_code' => $reliq['sld_code'],
                    'cng_code' => $cngCode,
                    'deb_jr' => $debit,
                    'deb_date' => date('Y-m-d')
                ]);
                
                $reste -= $debit;
            }

            log_message('info', 'finalizeValidation: Completed successfully');
            return true;
        } catch (\Exception $e) {
            log_message('error', 'finalizeValidation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get leaves pending validation for a specific signer
     */
    public function getPendingForSigner($signCode = null)
    {
        if (!$signCode) {
            return $this->fail('sign_code requis');
        }

        $signatureModel = new SignatureModel();
        $signature = $signatureModel->find($signCode);

        if (!$signature) {
            return $this->failNotFound('Signature non trouvée');
        }

        $stepName = $signature['sign_libele'];
        $stepIndex = array_search($stepName, self::VALIDATION_ORDER);

        if ($stepIndex === false) {
            return $this->fail('Type de signature invalide');
        }

        // Get all leaves with cng_status = false (pending)
        // Note: PostgreSQL requires explicit boolean comparison
        $congeModel = new CongeModel();
        $validationModel = new ValidationCongeModel();

        $pendingConges = $congeModel
            ->select('conge.*, employee.emp_nom, employee.emp_prenom, employee.emp_imarmp, type_conge.typ_appelation')
            ->join('employee', 'employee.emp_code = conge.emp_code', 'left')
            ->join('type_conge', 'type_conge.typ_code = conge.typ_code', 'left')
            ->where('conge.cng_status', 'false')  // PostgreSQL boolean as string
            ->findAll();

        $result = [];
        foreach ($pendingConges as $conge) {
            // Get current step for this conge
            $validations = $validationModel
                ->select('validation_cng.*, signature.sign_libele')
                ->join('signature', 'signature.sign_code = validation_cng.sign_code', 'left')
                ->where('validation_cng.cng_code', $conge['cng_code'])
                ->findAll();

            // Check if rejected
            $isRejected = false;
            foreach ($validations as $v) {
                $valStatus = $v['val_status'];
                if ($valStatus === false || $valStatus === 'f' || $valStatus === '0' || $valStatus === 0) {
                    $isRejected = true;
                    break;
                }
            }

            if ($isRejected) continue;

            // Find current step
            $validatedSteps = [];
            foreach ($validations as $v) {
                $valStatus = $v['val_status'];
                if ($valStatus === true || $valStatus === 't' || $valStatus === '1' || $valStatus === 1) {
                    $validatedSteps[] = $v['sign_libele'];
                }
            }

            $currentStep = null;
            foreach (self::VALIDATION_ORDER as $step) {
                if (!in_array($step, $validatedSteps)) {
                    $currentStep = $step;
                    break;
                }
            }

            // If this is the step for the requested signer, include it
            if ($currentStep === $stepName) {
                $result[] = $conge;
            }
        }

        return $this->respond($result);
    }
}
