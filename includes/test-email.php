<?php
require_once 'C:/xamppp/htdocs/lumoura/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'sissaminuilla@gmail.com'; // ← ton Gmail
    $mail->Password   = 'ebku wddz dwqm qngc';      // ← tes 16 caractères
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('sissaminuilla@gmail.com', 'lumoura');
    $mail->addAddress('salhimelissa2007@gmail.com');

    $mail->isHTML(true);
    $mail->Subject = 'Test lumoura';
    $mail->Body    = '<h1>Test réussi !</h1>';

    $mail->send();
    echo '✅ Email envoyé avec succès !';

} catch (Exception $e) {
    echo '❌ Erreur : ' . $mail->ErrorInfo;
}
?>