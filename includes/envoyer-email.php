<?php
// ============================================================
// FICHIER : includes/envoyer-email.php
// RÔLE    : Fonction utilitaire pour envoyer un email via
//           PHPMailer + Gmail SMTP
// ============================================================

// ✅ CORRECTION : Chargement correct de l'autoloader Composer
// __DIR__ = dossier actuel (includes/)
// On remonte d'un niveau (../) pour atteindre la racine du projet
// où se trouve le dossier vendor/
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ============================================================
// ⚙️ CONFIGURATION — À MODIFIER AVEC TES INFOS
// ============================================================
define('GMAIL_USER',         'sissaminuilla@gmail.com');
define('GMAIL_APP_PASSWORD', 'fupt qntg duti eqpz');   // ✅ Mot de passe corrigé (celui qui fonctionne dans test-email.php)
define('MAIL_FROM_NAME',     'Lumoura Parfums');


// ============================================================
// FONCTION : envoyerCodeVerification()
// ============================================================
// Envoie un email contenant le code OTP à 6 chiffres
//
// Paramètres :
//   $email    → adresse email du destinataire
//   $prenom   → prénom de l'utilisateur (pour personnaliser l'email)
//   $code     → le code à 6 chiffres généré
//
// Retourne :
//   true  → email envoyé avec succès
//   false → erreur lors de l'envoi
// ============================================================
function envoyerCodeVerification(string $email, string $prenom, string $code): bool {

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(GMAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($email, $prenom);

        $mail->isHTML(true);
        $mail->Subject = 'Votre code de confirmation Lumoura';

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin: auto;
                    border: 1px solid #eee; border-radius: 16px; overflow: hidden;'>

            <!-- En-tête -->
            <div style='background: linear-gradient(90deg, #ff8c00, #e07b00);
                        padding: 30px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 24px;'>LUMOURA</h1>
                <p style='color: rgba(255,255,255,0.85); margin: 5px 0 0;'>Parfums d'Exception</p>
            </div>

            <!-- Corps -->
            <div style='padding: 35px; background: #fff;'>
                <p style='font-size: 16px; color: #333;'>Bonjour <strong>" . htmlspecialchars($prenom) . "</strong>,</p>
                <p style='color: #555;'>Votre adresse de livraison a bien été enregistrée.<br>
                Veuillez confirmer votre adresse en saisissant le code ci-dessous :</p>

                <!-- Code OTP mis en valeur -->
                <div style='text-align: center; margin: 30px 0;'>
                    <div style='display: inline-block; background: #fff8f0;
                                border: 3px solid #ff8c00; border-radius: 16px;
                                padding: 20px 40px;'>
                        <p style='margin: 0; font-size: 13px; color: #888; letter-spacing: 2px;'>VOTRE CODE</p>
                        <p style='margin: 8px 0 0; font-size: 42px; font-weight: bold;
                                  color: #ff8c00; letter-spacing: 10px;'>" . $code . "</p>
                    </div>
                </div>

                <p style='color: #888; font-size: 13px; text-align: center;'>
                    ⏱️ Ce code est valable <strong>15 minutes</strong>.<br>
                    Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.
                </p>
            </div>

            <!-- Pied de page -->
            <div style='background: #f8f9fa; padding: 20px; text-align: center;'>
                <p style='margin: 0; color: #aaa; font-size: 12px;'>
                    © 2026 Lumoura · 123 Avenue des Champs-Élysées, 75008 Paris
                </p>
            </div>
        </div>";

        $mail->AltBody = "Bonjour $prenom,\n\nVotre code de confirmation Lumoura est : $code\n\nCe code est valable 15 minutes.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Erreur envoi email : " . $mail->ErrorInfo);
        return false;
    }
}


// ============================================================
// FONCTION : genererCodeOTP()
// ============================================================
// Génère un code aléatoire à 6 chiffres
// str_pad() ajoute des zéros à gauche si le nombre < 100000
// Exemple : random_int() donne 7342 → str_pad donne "007342"
// ============================================================
function genererCodeOTP(): string {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}