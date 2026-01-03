<?php
/**
 * Script de test email - SI-GPRH
 * À supprimer après test réussi
 */

require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "=== Test Email SI-GPRH ===\n\n";

// Charger .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(sprintf('%s=%s', trim($name), trim($value)));
    }
}

$smtpPass = getenv('SMTP_PASS');

if (empty($smtpPass)) {
    echo "❌ SMTP_PASS non configuré dans .env\n";
    echo "   Ajoutez : SMTP_PASS=votre_mot_de_passe_app_gmail\n";
    exit(1);
}

echo "✓ SMTP_PASS trouvé dans .env\n";

// Configuration email de test
$testEmail = readline("Entrez votre email pour le test (ex: vous@gmail.com) : ");

if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    echo "❌ Email invalide\n";
    exit(1);
}

$mail = new PHPMailer(true);

try {
    echo "\n📧 Configuration SMTP...\n";
    
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'armpgrh@gmail.com';
    $mail->Password = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    
    // Debug verbeux
    $mail->SMTPDebug = 2; // 0 = off, 1 = client, 2 = both
    $mail->Debugoutput = 'echo';
    
    echo "\n📨 Envoi email test...\n\n";
    
    $mail->setFrom('armpgrh@gmail.com', 'SI-GPRH Test');
    $mail->addAddress($testEmail);
    
    $mail->isHTML(true);
    $mail->Subject = '✅ Test Email SI-GPRH';
    $mail->Body = '
        <h2>Email Test Réussi !</h2>
        <p>Le service email de SI-GPRH fonctionne correctement.</p>
        <p><strong>Configuration SMTP Gmail validée.</strong></p>
        <hr>
        <small>Si vous recevez cet email, vous pouvez utiliser les notifications de validation de congés.</small>
    ';
    
    $mail->send();
    
    echo "\n\n✅ ========================================\n";
    echo "✅ EMAIL ENVOYÉ AVEC SUCCÈS !\n";
    echo "✅ ========================================\n\n";
    echo "👉 Vérifiez votre boîte email : $testEmail\n";
    echo "👉 Vérifiez aussi le dossier SPAM si besoin\n\n";
    echo "Le service email est prêt à être utilisé ! 🎉\n\n";
    
} catch (Exception $e) {
    echo "\n\n❌ ========================================\n";
    echo "❌ ERREUR D'ENVOI\n";
    echo "❌ ========================================\n\n";
    echo "Message d'erreur : {$mail->ErrorInfo}\n\n";
    
    echo "Solutions possibles :\n";
    echo "1. Vérifier que SMTP_PASS est bien le mot de passe d'application Gmail (16 caractères)\n";
    echo "2. Vérifier que la validation en 2 étapes est activée sur le compte Gmail\n";
    echo "3. Régénérer un nouveau mot de passe d'application\n";
    echo "4. Vérifier votre connexion internet\n\n";
    
    exit(1);
}
