<?php
// PHPMailer einbinden
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Lade manuell heruntergeladene Dateien (Pfade relativ zu dieser Datei)
require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

// Konfiguration laden
if (file_exists('/var/www/secret/mail_config.php')) {
    require_once '/var/www/secret/mail_config.php';
}

function send_smtp_mail($to, $subject, $body, $isHtml = false) {
    if (!defined('SMTP_HOST')) {
        // Fallback wenn keine Config da ist (z.B. local dev)
        error_log("Warnung: Keine SMTP Config gefunden. Simuliere Mail an $to");
        return false;
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->CharSet    = 'UTF-8'; // <--- Neu hinzugefügt
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;           // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = SMTP_USER;                     // SMTP username
        $mail->Password   = SMTP_PASS;                               // SMTP password
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;            // Enable JS SSL/TLS (Implicit TLS encryption)
        $mail->Port       = SMTP_PORT;                                    // TCP port to connect to; use 587 if you have set SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);     // Add a recipient

        // Content
        $mail->isHTML($isHtml);                                  // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        // Plain text version (optional)
        if($isHtml) {
            $mail->AltBody = strip_tags($body);
        }

        $mail->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>