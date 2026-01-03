<?php

namespace App\Controllers\conge;

use App\Controllers\BaseController;
use App\Models\conge\CongeModel;
use App\Models\conge\SignatureModel;
use App\Services\EmailService;
use App\Services\CongeValidationService;
use CodeIgniter\API\ResponseTrait;

/**
 * Controller pour gérer les validations de congé via email
 */
class ValidationEmailController extends BaseController
{
    use ResponseTrait;

    private CongeModel $congeModel;
    private $signatureModel;
    private EmailService $emailService;
    private CongeValidationService $validationService;

    public function __construct()
    {
        $this->congeModel = new CongeModel();
        $this->signatureModel = new SignatureModel();
        $this->emailService = new EmailService();
        $this->validationService = new CongeValidationService();
    }

    /**
     * Gérer un clic sur lien de validation email
     */
    public function handleEmailValidation()
    {
        $token = $this->request->getGet('token');
        $action = $this->request->getGet('action');
        $motif = $this->request->getGet('motif');

        log_message('info', "[EMAIL-VALIDATE] Token reçu: $token, Action: $action");

        if (!$token) return $this->showResultPage('error', 'Token manquant');

        $db = \Config\Database::connect();
        $validation = $db->table('validation_cng')->where('val_token', $token)->get()->getRowArray();

        log_message('info', "[EMAIL-VALIDATE] Validation trouvée: " . json_encode($validation));

        if (!$validation) return $this->showResultPage('error', 'Lien invalide ou expiré');
        
        log_message('info', "[EMAIL-VALIDATE] val_token_used=" . ($validation['val_token_used'] ? 'TRUE' : 'FALSE'));
        
        // PostgreSQL retourne 't'/'f' en string, pas boolean
        if ($validation['val_token_used'] === 't' || $validation['val_token_used'] === true) {
            return $this->showResultPage('warning', 'Ce lien a déjà été utilisé');
        }
        
        if ($validation['val_token_expires'] && strtotime($validation['val_token_expires']) < time()) {
            return $this->showResultPage('error', 'Ce lien a expiré');
        }

        if ($action === 'reject' && !$motif) return $this->showMotifForm($token);

        // Mark Update
        $db->table('validation_cng')
            ->where('val_token', $token)
            ->update([
                'val_token_used' => true,
                'val_date' => date('Y-m-d'),
                'val_status' => ($action === 'approve'),
                'val_observation' => $motif ?: null
            ]);

        $conge = $this->getCongeDetails($validation['cng_code']);
        $currentSignature = $this->signatureModel->find($validation['sign_code']);

        if ($action === 'approve') {
            return $this->handleApproval($conge, $currentSignature);
        } else {
            return $this->handleRejection($conge, $currentSignature, $motif);
        }
    }

    private function handleApproval(array $conge, array $currentSignature): string
    {
        // 1. Get Dynamic Steps
        $stepObjects = $this->validationService->getValidationSteps($conge['emp_code']);
        $stepNames = array_column($stepObjects, 'step');

        $currentStep = $currentSignature['sign_libele'];
        
        // Find position
        $currentIndex = null;
        foreach ($stepObjects as $index => $stepObj) {
            if ($stepObj['step'] === $currentStep) {
                $currentIndex = $index;
                break;
            }
        }

        // Check Next
        if ($currentIndex !== null && isset($stepObjects[$currentIndex + 1])) {
            $nextStepObj = $stepObjects[$currentIndex + 1];
            $nextStepName = $nextStepObj['step'];
            $nextStepCode = $nextStepObj['code'];
            
            if ($this->validationService->sendValidationNotification($conge, $nextStepCode)) {
                return $this->showResultPage('success', "Congé approuvé. En attente de validation par: {$nextStepName}");
            } else {
                return $this->showResultPage('warning', "Congé approuvé, mais erreur d'envoi d'email au validateur suivant ({$nextStepName}).");
            }
        }

        // Final
        $this->markCongeAsValidated($conge['cng_code']);
        $this->sendCompletionEmail($conge);
        return $this->showResultPage('success', 'Congé entièrement validé !');
    }

    private function handleRejection(array $conge, array $currentSignature, string $motif): string
    {
        $rejetePar = $currentSignature['sign_libele'];
        $this->congeModel->update($conge['cng_code'], ['cng_status' => false]);

        $this->emailService->sendRejectionNotice(
            $conge['emp_mail'],
            $conge['nom_emp'] . ' ' . $conge['prenom_emp'],
            $conge,
            $rejetePar,
            $motif
        );
        
        $this->notifyPreviousValidators($conge, $rejetePar, $motif);
        return $this->showResultPage('rejected', "Congé refusé. Notification envoyée.");
    }

    private function notifyPreviousValidators(array $conge, string $rejetePar, string $motif): void
    {
        $db = \Config\Database::connect();
        $previousValidations = $db->table('validation_cng v')
            ->select('e.emp_mail, e.emp_nom, e.emp_prenom')
            ->join('employee e', 'e.sign_code = v.sign_code')
            ->where('v.cng_code', $conge['cng_code'])
            ->where('v.val_status', true)
            ->get()->getResultArray();

        foreach ($previousValidations as $val) {
            if (!empty($val['emp_mail'])) {
                $this->emailService->sendRejectionNotice(
                    $val['emp_mail'],
                    $val['emp_nom'] . ' ' . $val['emp_prenom'],
                    $conge,
                    $rejetePar,
                    $motif
                );
            }
        }
    }



    private function sendCompletionEmail(array $conge): void
    {
        $this->emailService->sendValidationComplete(
            $conge['emp_mail'],
            $conge['nom_emp'] . ' ' . $conge['prenom_emp'],
            $conge
        );
    }

    private function markCongeAsValidated(int $cngCode): void
    {
        $this->congeModel->update($cngCode, ['cng_status' => true]);
    }

    private function getCongeDetails(int $cngCode): array
    {
        $db = \Config\Database::connect();
        return $db->table('conge c')
            ->select('c.*, e.emp_code, e.emp_nom as nom_emp, e.emp_prenom as prenom_emp, e.emp_mail, e.emp_imarmp as matricule, t.typ_appelation, r.reg_nom as nom_region')
            ->join('employee e', 'e.emp_code = c.emp_code')
            ->join('type_conge t', 't.typ_code = c.typ_code')
            ->join('region r', 'r.reg_code = c.reg_code')
            ->where('c.cng_code', $cngCode)
            ->get()->getRowArray();
    }

    private function showResultPage(string $type, string $message): string
    {
        $typeConfig = [
            'success' => [
                'bg' => '#10B981',
                'icon' => '✓',
                'title' => 'Validation réussie',
                'iconBg' => '#059669'
            ],
            'error' => [
                'bg' => '#EF4444',
                'icon' => '✕',
                'title' => 'Erreur',
                'iconBg' => '#DC2626'
            ],
            'warning' => [
                'bg' => '#F59E0B',
                'icon' => '!',
                'title' => 'Attention',
                'iconBg' => '#D97706'
            ],
            'rejected' => [
                'bg' => '#EF4444',
                'icon' => '✕',
                'title' => 'Demande rejetée',
                'iconBg' => '#DC2626'
            ]
        ];

        $config = $typeConfig[$type] ?? $typeConfig['success'];
        $bgColor = $config['bg'];
        $icon = $config['icon'];
        $title = $config['title'];
        $iconBg = $config['iconBg'];

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title</title>
</head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;background-color:#f5f5f5;display:flex;align-items:center;justify-content:center;min-height:100vh;">
    <div style="max-width:500px;margin:20px;background:#ffffff;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,0.1);overflow:hidden;text-align:center;">
        <!-- Icon -->
        <div style="padding:40px 40px 20px;background:linear-gradient(135deg, $bgColor 0%, $iconBg 100%);">
            <div style="width:80px;height:80px;margin:0 auto;background:rgba(255,255,255,0.2);border:4px solid #ffffff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:40px;color:#ffffff;font-weight:700;">
                $icon
            </div>
        </div>
        
        <!-- Content -->
        <div style="padding:30px 40px 40px;">
            <h1 style="margin:0 0 15px;font-size:24px;font-weight:600;color:#111827;">
                $message
            </h1>
            <p style="margin:0 0 30px;color:#6B7280;font-size:15px;line-height:1.6;">
                Cette action a été enregistrée avec succès.
            </p>
            
            <!-- Return Button -->
            <a href="javascript:window.close();" style="display:inline-block;padding:12px 32px;background:#4F46E5;color:#ffffff;text-decoration:none;border-radius:6px;font-size:15px;font-weight:600;transition:background 0.3s;">
                Retour
            </a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function showMotifForm(string $token): string
    {
        // ... (Keep existing HTML)
        return "<html><body style='font-family:sans-serif;background:#f5f5f5;display:flex;justify-content:center;align-items:center;height:100vh'><div style='background:white;padding:40px;border-radius:10px;box-shadow:0 4px 10px rgba(0,0,0,0.1)'><h1 style='color:#dc3545'>Motif du Rejet</h1><form><input type='hidden' name='token' value='$token'><input type='hidden' name='action' value='reject'><textarea name='motif' required style='width:100%;height:100px;margin:10px 0'></textarea><button style='background:#dc3545;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer'>Confirmer</button></form></div></body></html>";
    }
}
