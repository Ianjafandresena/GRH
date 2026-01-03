<?php
/**
 * Template HTML professionnel pour email de validation de congé
 * À utiliser dans EmailService::sendValidationRequest()
 */

function getValidationEmailTemplate(array $congeDetails, string $approveLink, string $rejectLink, string $appName): string
{
    return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,\"Helvetica Neue\",Arial,sans-serif;background-color:#f5f5f5;'>
            <div style='max-width:600px;margin:40px auto;background:#ffffff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);overflow:hidden;'>
                <!-- Header -->
                <div style='background:#4F46E5;color:#ffffff;padding:30px 40px;text-align:center;'>
                    <h1 style='margin:0;font-size:24px;font-weight:600;'>Nouvelle demande de congé</h1>
                </div>
                
                <!-- Content -->
                <div style='padding:40px;'>
                    <p style='color:#374151;font-size:15px;line-height:1.6;margin:0 0 30px;'>
                        L'employé <strong>{$congeDetails['nom_emp']} {$congeDetails['prenom_emp']}</strong> a fait une demande de congé.
                    </p>
                    
                    <!-- Details Table -->
                    <table style='width:100%;border-collapse:collapse;margin-bottom:30px;'>
                        <tr>
                            <td style='padding:12px 0;color:#6B7280;font-size:14px;border-bottom:1px solid #E5E7EB;'>Type :</td>
                            <td style='padding:12px 0;color:#111827;font-size:14px;font-weight:500;border-bottom:1px solid #E5E7EB;text-align:right;'>{$congeDetails['typ_appelation']}</td>
                        </tr>
                        <tr>
                            <td style='padding:12px 0;color:#6B7280;font-size:14px;border-bottom:1px solid #E5E7EB;'>Dates :</td>
                            <td style='padding:12px 0;color:#111827;font-size:14px;font-weight:500;border-bottom:1px solid #E5E7EB;text-align:right;'>du {$congeDetails['cng_debut']} au {$congeDetails['cng_fin']}</td>
                        </tr>
                        <tr>
                            <td style='padding:12px 0;color:#6B7280;font-size:14px;'>Nombre de jours :</td>
                            <td style='padding:12px 0;color:#111827;font-size:14px;font-weight:600;text-align:right;'>{$congeDetails['cng_nb_jour']} jours</td>
                        </tr>
                    </table>
                    
                    <p style='color:#6B7280;font-size:14px;margin:0 0 25px;'>
                        Pour traiter cette demande, veuillez cliquer sur le lien ci-dessous :
                    </p>
                    
                    <!-- Action Button -->
                    <div style='text-align:center;margin-bottom:30px;'>
                        <a href='$approveLink' style='display:inline-block;background:#4F46E5;color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:6px;font-size:15px;font-weight:600;'>
                            Accéder à la validation
                        </a>
                    </div>
                    
                    <!-- Alternative Links -->
                    <div style='padding:20px;background:#F9FAFB;border-radius:6px;margin-top:20px;'>
                        <p style='color:#6B7280;font-size:13px;margin:0 0 12px;'>Vous pouvez également :</p>
                        <div style='text-align:center;'>
                            <a href='$approveLink' style='display:inline-block;margin:5px;padding:10px 20px;background:#10B981;color:#ffffff;text-decoration:none;border-radius:5px;font-size:14px;'>
                                ✓ Valider
                            </a>
                            <a href='$rejectLink' style='display:inline-block;margin:5px;padding:10px 20px;background:#EF4444;color:#ffffff;text-decoration:none;border-radius:5px;font-size:14px;'>
                                ✕ Refuser
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div style='background:#F9FAFB;padding:20px 40px;border-top:1px solid #E5E7EB;'>
                    <p style='color:#9CA3AF;font-size:12px;margin:0;text-align:center;'>
                        Ce lien est valide pour 7 jours. • $appName
                    </p>
                </div>
            </div>
        </body>
        </html>
    ";
}
