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
        try {
            if (!isset($this->mailer)) return false;

            $this->mailer->clearAddresses();
            $this->mailer->addAddress($toEmail, $toName);
            $this->mailer->Subject = "Notification de validation de congé : {$congeDetails['typ_appelation']}";

            $body = $this->getCompletionEmailTemplate($congeDetails);
            
            $this->mailer->Body = $body;
            $sent = $this->mailer->send();
            
            if ($sent) {
                log_message('info', "[Email] Notification de succès envoyée à: " . $toEmail);
            }
            return $sent;
        } catch (\Throwable $e) {
            log_message('error', "[Email] Erreur notification succès à $toEmail: " . $e->getMessage());
            return false;
        }
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
        try {
            if (!isset($this->mailer)) return false;

            $this->mailer->clearAddresses();
            $this->mailer->addAddress($toEmail, $toName);
            $this->mailer->Subject = "Notification de décision : Demande de congé non validée";

            $body = $this->getRejectionEmailTemplate($congeDetails, $rejetePar, $motif);
            
            $this->mailer->Body = $body;
            $sent = $this->mailer->send();
            
            if ($sent) {
                log_message('info', "[Email] Notification de rejet envoyée à: " . $toEmail);
            }
            return $sent;
        } catch (\Throwable $e) {
            log_message('error', "[Email] Erreur notification rejet à $toEmail: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Template pour validation complète
     */
    private function getCompletionEmailTemplate(array $conge): string
    {
        $type = htmlspecialchars($conge['typ_appelation']);
        $debut = htmlspecialchars($conge['cng_debut']);
        $fin = htmlspecialchars($conge['cng_fin']);
        $jours = htmlspecialchars($conge['cng_nb_jour']);
        
        return <<<HTML
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;font-family:sans-serif;background-color:#f9fafb;">
    <div style="max-width:600px;margin:30px auto;background:#ffffff;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.05);overflow:hidden;border:1px solid #e5e7eb;">
        <div style="background:#4F46E5;color:#ffffff;padding:25px;text-align:center;">
            <h2 style="margin:0;font-size:18px;">Notification de validation</h2>
        </div>
        <div style="padding:30px;color:#374151;line-height:1.6;">
            <p>Madame, Monsieur,</p>
            <p>Nous vous informons que votre demande de <strong>$type</strong> a reçu un avis favorable de la part de l'administration.</p>
            <div style="background:#f3f4f6;padding:20px;border-radius:8px;margin:20px 0;">
                <p style="margin:5px 0;"><strong>Récapitulatif de la période :</strong></p>
                <p style="margin:5px 0;">Dates : du $debut au $fin</p>
                <p style="margin:5px 0;">Durée : $jours jour(s)</p>
            </div>
            <p>Le titre de congé correspondant est désormais disponible en téléchargement dans votre espace personnel.</p>
            <p style="margin-top:30px;font-size:13px;color:#6b7280;border-top:1px solid #eee;padding-top:15px;">
                Direction Générale - Service des Ressources Humaines<br>
                Autorité de Régulation des Marchés Publics (ARMP)
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Template pour rejet
     */
    private function getRejectionEmailTemplate(array $conge, string $rejetePar, string $motif): string
    {
        $type = htmlspecialchars($conge['typ_appelation']);
        $motif = htmlspecialchars($motif);
        
        return <<<HTML
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;font-family:sans-serif;background-color:#f9fafb;">
    <div style="max-width:600px;margin:30px auto;background:#ffffff;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.05);overflow:hidden;border:1px solid #e5e7eb;">
        <div style="background:#374151;color:#ffffff;padding:25px;text-align:center;">
            <h2 style="margin:0;font-size:18px;">Notification de décision administrative</h2>
        </div>
        <div style="padding:30px;color:#374151;line-height:1.6;">
            <p>Madame, Monsieur,</p>
            <p>Nous vous informons que votre demande de <strong>$type</strong> n'a pas pu être validée pour le motif suivant :</p>
            <div style="background:#fef2f2;padding:20px;border-radius:8px;margin:20px 0;border-left:4px solid #ef4444;">
                <p style="margin:0;color:#991b1b;"><strong>Observation :</strong> $motif</p>
            </div>
            <p>Pour toute demande de précision, vous pouvez vous rapprocher du Service des Ressources Humaines.</p>
            <p style="margin-top:30px;font-size:13px;color:#6b7280;border-top:1px solid #eee;padding-top:15px;">
                Direction Générale - Service des Ressources Humaines<br>
                Autorité de Régulation des Marchés Publics (ARMP)
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Notifier l'employé que son état est envoyé à l'agent comptable
     */
    public function sendEtatComptableNotice(
        string $toEmail,
        string $toName,
        array $etat,
        array $demandes,
        ?array $attachment = null // ['path' => '...', 'name' => '...']
    ): bool {
        try {
            if (!isset($this->mailer)) return false;

            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->addAddress($toEmail, $toName);
            
            if ($attachment && !empty($attachment['content'])) {
                $this->mailer->addStringAttachment($attachment['content'], $attachment['name'] ?? 'etat_remboursement.pdf');
            }

            $this->mailer->Subject = "Suivi de Remboursement : État envoyé à l'Agent Comptable";

            $body = $this->getEtatEmailTemplate($etat, $demandes);
            
            $this->mailer->Body = $body;
            return $this->mailer->send();
        } catch (\Throwable $e) {
            log_message('error', "[Email-Etat] Erreur: " . $e->getMessage());
            return false;
        }
    }

    private function getEtatEmailTemplate(array $etat, array $demandes): string
    {
        $etatNum = htmlspecialchars($etat['etat_num']);
        $total = number_format($etat['eta_total'], 2, ',', ' ') . ' Ar';
        
        $rows = "";
        foreach ($demandes as $d) {
            $article = htmlspecialchars($d['obj_article'] ?? 'N/A');
            $montant = number_format($d['rem_montant'], 2, ',', ' ') . ' Ar';
            $date = date('d/m/Y', strtotime($d['rem_date']));
            $rows .= "<tr>
                        <td style='padding:10px; border-bottom:1px solid #eee;'>$article</td>
                        <td style='padding:10px; border-bottom:1px solid #eee;'>$date</td>
                        <td style='padding:10px; border-bottom:1px solid #eee; text-align:right;'>$montant</td>
                      </tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;">
        <h2 style="color: #2c3e50;">Bonjour,</h2>
        <p>Nous vous informons que votre état de remboursement numéro <strong>$etatNum</strong> a été transmis à <strong>l'Agent Comptable</strong> pour traitement.</p>
        
        <h4 style="border-bottom: 2px solid #2c3e50; padding-bottom: 5px;">Détails des demandes incluses :</h4>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding:10px; text-align:left;">Article</th>
                    <th style="padding:10px; text-align:left;">Date</th>
                    <th style="padding:10px; text-align:right;">Montant</th>
                </tr>
            </thead>
            <tbody>
                $rows
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="padding:10px; text-align:right; font-weight:bold;">T O T A L</td>
                    <td style="padding:10px; text-align:right; font-weight:bold; color: #27ae60;">$total</td>
                </tr>
            </tfoot>
        </table>
        
        <p style="margin-top: 25px;">Cordialement,<br><strong>Le Service des Ressources Humaines</strong></p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="font-size: 11px; color: #999; text-align: center;">Ceci est un message automatique, merci de ne pas y répondre.</p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Générer un token unique sécurisé
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
