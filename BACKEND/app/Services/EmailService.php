<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Service d'envoi d'emails pour les validations de congés
 */
class EmailService
{
    private PHPMailer $mailer;
    private string $appUrl;
    private string $appName = 'SI-GPRH - Gestion des Ressources Humaines';

    public function __construct()
    {
        try {
            $this->mailer = new PHPMailer(true);
            $baseUrl = getenv('app.baseURL') ?: 'http://localhost:4200';
            // Enlever le slash final pour éviter double slash dans les URLs
            $this->appUrl = rtrim($baseUrl, '/');
            $this->configureMailer();
        } catch (\Throwable $e) {
            // FAIL-SAFE: Logger mais ne pas planter
            log_message('error', '[EmailService] Init failed: ' . $e->getMessage());
        }
    }

    /**
     * Configuration SMTP
     */
    private function configureMailer(): void
    {
        // Fail-Safe Configuration
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host       = 'smtp.gmail.com';
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = 'armpgrh@gmail.com';
            $this->mailer->Password   = getenv('SMTP_PASS') ?: ''; // Set this in .env
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port       = 587;
            $this->mailer->CharSet    = 'UTF-8';
            $this->mailer->isHTML(true);
            
            $this->mailer->setFrom('armpgrh@gmail.com', $this->appName);
        } catch (\Throwable $e) {
            log_message('error', '[EmailConfig] Error: ' . $e->getMessage());
        }
    }

    /**
     * Envoyer demande de validation à un validateur
     */
    public function sendValidationRequest(
        string $toEmail,
        string $toName,
        array $congeDetails,
        string $approveToken,
        string $rejectToken
    ):  bool {
        try {
            // FAIL-SAFE: Vérifier si mailer existe
            if (!isset($this->mailer)) {
                log_message('warning', '[Email] Mailer non initialisé, email skip: ' . $toEmail);
                return false;
            }

            $this->mailer->clearAddresses();
            $this->mailer->addAddress($toEmail, $toName);
            $this->mailer->Subject = "Validation requise: Congé de {$congeDetails['nom_emp']}";

            $approveLink = "{$this->appUrl}/api/conge/email-validate?token={$approveToken}";
            $rejectLink = "{$this->appUrl}/api/conge/email-validate?token={$rejectToken}";

            // Template professionnel moderne
            $body = $this->getValidationEmailTemplate($congeDetails, $approveLink, $rejectLink);
            
            $this->mailer->Body = $body;
            $sent = $this->mailer->send();
            
            if ($sent) {
                log_message('info', '[Email] Envoyé à: ' . $toEmail);
            }
            
            return $sent;

        } catch (\Throwable $e) {
            log_message('error', "[Email] Erreur envoi à $toEmail: " . $e->getMessage());
            // FAIL-SAFE: Ne pas propager l'exception
            return false;
        }
    }

    /**
     * Template professionnel pour email de validation
     */
    private function getValidationEmailTemplate(array $conge, string $approveLink, string $rejectLink): string
    {
        $nom = htmlspecialchars($conge['nom_emp'] . ' ' . $conge['prenom_emp']);
        $type = htmlspecialchars($conge['typ_appelation']);
        $debut = htmlspecialchars($conge['cng_debut']);
        $fin = htmlspecialchars($conge['cng_fin']);
        $jours = htmlspecialchars($conge['cng_nb_jour']);
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;background-color:#f5f5f5;">
    <div style="max-width:600px;margin:40px auto;background:#ffffff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);overflow:hidden;">
        <!-- Header -->
        <div style="background:#4F46E5;color:#ffffff;padding:30px 40px;text-align:center;">
            <h1 style="margin:0;font-size:24px;font-weight:600;">Nouvelle demande de congé</h1>
        </div>
        
        <!-- Content -->
        <div style="padding:40px;">
            <p style="color:#374151;font-size:15px;line-height:1.6;margin:0 0 30px;">
                L'employé <strong>$nom</strong> a fait une demande de congé.
            </p>
            
            <!-- Details Table -->
            <table style="width:100%;border-collapse:collapse;margin-bottom:30px;">
                <tr>
                    <td style="padding:12px 0;color:#6B7280;font-size:14px;border-bottom:1px solid #E5E7EB;">Type :</td>
                    <td style="padding:12px 0;color:#111827;font-size:14px;font-weight:500;border-bottom:1px solid #E5E7EB;text-align:right;">$type</td>
                </tr>
                <tr>
                    <td style="padding:12px 0;color:#6B7280;font-size:14px;border-bottom:1px solid #E5E7EB;">Dates :</td>
                    <td style="padding:12px 0;color:#111827;font-size:14px;font-weight:500;border-bottom:1px solid #E5E7EB;text-align:right;">du $debut au $fin</td>
                </tr>
                <tr>
                    <td style="padding:12px 0;color:#6B7280;font-size:14px;">Nombre de jours :</td>
                    <td style="padding:12px 0;color:#111827;font-size:14px;font-weight:600;text-align:right;">$jours jours</td>
                </tr>
            </table>
            
            <p style="color:#6B7280;font-size:14px;margin:0 0 25px;">
                Pour traiter cette demande, veuillez cliquer sur l'un des boutons ci-dessous :
            </p>
            
            <!-- Direct Action Buttons -->
            <div style="text-align:center;padding:20px;background:#F9FAFB;border-radius:6px;">
                <a href="$approveLink" style="display:inline-block;margin:8px;padding:14px 32px;background:#10B981;color:#ffffff;text-decoration:none;border-radius:6px;font-size:15px;font-weight:600;">
                    ✓ Valider
                </a>
                <a href="$rejectLink" style="display:inline-block;margin:8px;padding:14px 32px;background:#EF4444;color:#ffffff;text-decoration:none;border-radius:6px;font-size:15px;font-weight:600;">
                    ✕ Refuser
                </a>
            </div>
        </div>
        
        <!-- Footer -->
        <div style="background:#F9FAFB;padding:20px 40px;border-top:1px solid #E5E7EB;">
            <p style="color:#9CA3AF;font-size:12px;margin:0;text-align:center;">
                Ce lien est valide pour 7 jours. • {$this->appName}
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Notifier employé de la validation complète
     */
    public function sendValidationComplete(string $toEmail, string $toName, array $congeDetails): bool
    {
        log_message('info', "[MOCK EMAIL] Validation Complete sent to $toEmail for Conge {$congeDetails['cng_code']}");
        return true;
    }

    /**
     * Notifier du rejet (employé + validateurs précédents)
     */
    public function sendRejectionNotice(
        string $toEmail,
        string $toName,
        array $congeDetails,
        string $rejetePar,
        string $motif
    ): bool {
        log_message('info', "[MOCK EMAIL] Rejection Notice sent to $toEmail for Conge {$congeDetails['cng_code']}");
        return true;
    }

    /**
     * Générer un token unique sécurisé
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
