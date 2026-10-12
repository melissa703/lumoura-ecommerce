<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../vendor/autoload.php';
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);

    $stmt = $pdo->prepare("SELECT id_utilisateur, prenom FROM utilisateurs WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: forgot_password.php?error=1");
        exit;
    }

    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $pdo->prepare("UPDATE utilisateurs SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
    $stmt->execute([$token, $expires, $email]);

    $prenom    = htmlspecialchars($user['prenom']);
    $resetLink = "http://localhost/lumoura/pages/reset_password.php?token=" . $token;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sissaminuilla@gmail.com';
        $mail->Password   = 'fupt qntg duti eqpz';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('sissaminuilla@gmail.com', 'Lumoura Parfums');
        $mail->addAddress($email, $prenom);
        $mail->isHTML(true);
        $mail->Subject = 'Réinitialisation de votre mot de passe – Lumoura';
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin: auto;
                    border: 1px solid #eee; border-radius: 16px; overflow: hidden;'>

            <!-- En-tête -->
            <div style='background: linear-gradient(90deg, #d4a017, #f0c040);
                        padding: 30px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 26px; letter-spacing: 3px;'>LUMOURA</h1>
                <p style='color: rgba(255,255,255,0.9); margin: 5px 0 0; font-size: 13px;'>Parfums d'Exception</p>
            </div>

            <!-- Corps -->
            <div style='padding: 35px; background: #fff;'>
                <p style='font-size: 16px; color: #333;'>Bonjour <strong>$prenom</strong>,</p>
                <p style='color: #555; line-height: 1.7;'>
                    Vous avez demandé la réinitialisation de votre mot de passe.<br>
                    Cliquez sur le bouton ci-dessous. Ce lien est valable <strong>1 heure</strong>.
                </p>

                <!-- Bouton -->
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$resetLink'
                       style='display: inline-block;
                              background: linear-gradient(90deg, #d4a017, #f0c040);
                              color: white; text-decoration: none;
                              padding: 16px 40px; border-radius: 50px;
                              font-weight: bold; font-size: 15px;
                              box-shadow: 0 4px 15px rgba(212,160,23,0.4);'>
                        Réinitialiser mon mot de passe
                    </a>
                </div>

                <p style='color: #999; font-size: 13px; text-align: center;'>
                    Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.
                </p>
            </div>

            <!-- Pied de page -->
            <div style='background: #f8f5f0; padding: 20px; text-align: center; border-top: 1px solid #eee;'>
                <p style='margin: 0; color: #aaa; font-size: 12px;'>
                    © 2026 Lumoura · 123 Avenue des Champs-Élysées, 75008 Paris<br>
                    <a href='mailto:contact@lumoura.fr' style='color: #d4a017; text-decoration: none;'>contact@lumoura.fr</a>
                </p>
            </div>
        </div>";

        $mail->AltBody = "Bonjour $prenom,\n\nCliquez sur ce lien pour réinitialiser votre mot de passe (valable 1h) :\n$resetLink\n\nSi vous n'avez pas fait cette demande, ignorez cet email.\n\n© 2026 Lumoura";

        $mail->send();
        header("Location: forgot_password.php?success=1");
    } catch (Exception $e) {
        error_log("Erreur reset password : " . $mail->ErrorInfo);
        header("Location: forgot_password.php?error=2");
    }
    exit;
}
?>