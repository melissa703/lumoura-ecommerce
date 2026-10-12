<?php
// ============================================================
// FICHIER : pages/verify-inscription.php
// RÔLE    : Page de vérification du code OTP envoyé par email
//           lors de l'inscription d'un nouvel utilisateur
//
// FLUX COMPLET :
//   1. inscription.php → formulaire rempli et validé
//   2. inscription.php → génère OTP, sauvegarde en session, envoie email
//   3. verify-inscription.php → l'utilisateur saisit son code OTP
//   4. Code correct → compte créé en BDD → session ouverte → accueil
// ============================================================

session_start();

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// ============================================================
// VÉRIFICATION QU'UN OTP D'INSCRIPTION EST EN ATTENTE
// ============================================================
if (!isset($_SESSION['otp_inscription_pending'])) {
    header("Location: inscription.php");
    exit();
}

// Si déjà connecté → pas besoin d'être ici
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$erreur      = '';
$success_msg = '';

// ============================================================
// TRAITEMENT DU FORMULAIRE DE VÉRIFICATION (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verifier_otp'])) {

    $code_saisi = trim($_POST['otp_code'] ?? '');
    $pending    = $_SESSION['otp_inscription_pending'];

    if ($code_saisi === $pending['otp_code'] && time() < $pending['otp_expires']) {

        // ✅ CODE VALIDE → On insère le compte en BDD
        try {
            $stmt = $pdo->prepare("
                INSERT INTO utilisateurs (email, mot_de_passe, nom, prenom, telephone, civilite, newsletter, date_inscription, role)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'client')
            ");
            $stmt->execute([
                $pending['email'],
                $pending['password'],
                $pending['nom'],
                $pending['prenom'],
                $pending['telephone'],
                $pending['civilite'],
                $pending['newsletter'],
            ]);

            $user_id = $pdo->lastInsertId();

            // Ouverture de la session → l'utilisateur est maintenant connecté
            $_SESSION['user_id'] = $user_id;
            $_SESSION['email']   = $pending['email'];
            $_SESSION['nom']     = $pending['nom'];
            $_SESSION['prenom']  = $pending['prenom'];
            $_SESSION['role']    = 'client';

            // ✅ Envoi de l'email de bienvenue après inscription réussie
            require_once '../includes/envoyer-email.php';
            envoyerEmailBienvenue(
                $pending['email'],
                $pending['prenom'],
                $pending['nom']
            );

            // Nettoyage de la session OTP
            unset($_SESSION['otp_inscription_pending']);

            header('Location: index.php?inscription=success');
            exit();

        } catch (PDOException $e) {
            $erreur = 'Une erreur est survenue lors de la création du compte. Veuillez réessayer.';
        }

    } elseif (time() >= $pending['otp_expires']) {
        $erreur = "⏱️ Ce code a expiré. Veuillez vous réinscrire pour recevoir un nouveau code.";
        unset($_SESSION['otp_inscription_pending']);
    } else {
        $erreur = "❌ Code incorrect. Vérifiez votre email et réessayez.";
    }
}

// ============================================================
// RENVOYER LE CODE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renvoyer_otp'])) {

    if (isset($_SESSION['otp_inscription_pending'])) {
        require_once '../includes/envoyer-email.php';

        $nouveau_code = genererCodeOTP();
        $_SESSION['otp_inscription_pending']['otp_code']    = $nouveau_code;
        $_SESSION['otp_inscription_pending']['otp_expires'] = time() + (15 * 60);

        $pending = $_SESSION['otp_inscription_pending'];

        if (envoyerCodeVerification($pending['email'], $pending['prenom'], $nouveau_code)) {
            $success_msg = "✅ Un nouveau code a été envoyé à votre adresse email.";
        } else {
            $erreur = "❌ Erreur lors de l'envoi. Veuillez réessayer.";
        }
    } else {
        header("Location: inscription.php");
        exit();
    }
}

// Email masqué pour l'affichage
$email_complet = $_SESSION['otp_inscription_pending']['email'] ?? '';
$email_affiche = substr($email_complet, 0, 2) . '***@' . explode('@', $email_complet)[1];

$pageTitle = "Vérification - Lumoura";
include '../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-box" style="max-width: 480px;">

        <!-- Icône -->
        <div style="text-align: center; margin-bottom: 25px;">
            <div style="width: 80px; height: 80px;
                        background: linear-gradient(135deg, #ff8c00, #e07b00);
                        border-radius: 50%;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;">
                <i class="fas fa-envelope-open-text" style="font-size: 36px; color: white;"></i>
            </div>
        </div>

        <h1 class="auth-title">Confirmez votre email</h1>

        <p style="text-align: center; color: #888; margin-bottom: 30px; font-size: 15px;">
            Votre compte est presque prêt !<br>
            Un code à 6 chiffres a été envoyé à<br>
            <strong style="color: #333;"><?= htmlspecialchars($email_affiche) ?></strong>
        </p>

        <!-- Message d'erreur -->
        <?php if ($erreur): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($erreur) ?>
            </div>
        <?php endif; ?>

        <!-- Message de succès (renvoi) -->
        <?php if ($success_msg): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire OTP -->
        <form method="POST" action="" class="auth-form">

            <div class="form-group">
                <label class="form-label" style="text-align: center; display: block;">
                    <i class="fas fa-key"></i> Entrez votre code de confirmation
                </label>

                <input
                    type="text"
                    name="otp_code"
                    class="form-input"
                    placeholder="000000"
                    maxlength="6"
                    pattern="\d{6}"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                    required
                    style="font-size: 32px;
                           font-weight: bold;
                           letter-spacing: 14px;
                           text-align: center;
                           border: 3px solid #ff8c00;
                           border-radius: 16px;
                           padding: 15px;
                           color: #ff8c00;"
                >
            </div>

            <!-- Minuterie -->
            <p style="text-align: center; color: #aaa; font-size: 13px; margin-top: -10px; margin-bottom: 20px;">
                ⏱️ Code valable encore <span id="timer" style="font-weight: bold; color: #ff8c00;">15:00</span>
            </p>

            <button type="submit" name="verifier_otp" class="btn-submit" id="btn-verify">
                <i class="fas fa-user-check"></i> Confirmer et créer mon compte
            </button>

        </form>

        <div style="text-align: center; margin-top: 20px;">
            <p style="color: #aaa; font-size: 14px; margin-bottom: 8px;">
                Vous n'avez pas reçu le code ?
            </p>
            <form method="POST" style="display: inline;">
                <button type="submit" name="renvoyer_otp"
                        style="background: none; border: none; color: #ff8c00;
                               font-weight: 600; text-decoration: underline;
                               cursor: pointer; font-size: 14px;">
                    <i class="fas fa-redo"></i> Renvoyer un nouveau code
                </button>
            </form>
        </div>

        <div class="auth-links" style="margin-top: 25px;">
            <p>
                <a href="inscription.php" style="color: #aaa; font-size: 13px; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Retour à l'inscription
                </a>
            </p>
        </div>

    </div>
</div>

<script>
let tempsRestant = 900;

const timerEl  = document.getElementById('timer');
const btnVerif = document.getElementById('btn-verify');

const interval = setInterval(function() {
    tempsRestant--;

    const minutes  = Math.floor(tempsRestant / 60);
    const secondes = tempsRestant % 60;
    timerEl.textContent = minutes + ':' + String(secondes).padStart(2, '0');

    if (tempsRestant <= 120) {
        timerEl.style.color = '#dc3545';
    }

    if (tempsRestant <= 0) {
        clearInterval(interval);
        timerEl.textContent       = 'Expiré';
        btnVerif.disabled         = true;
        btnVerif.innerHTML        = '⏱️ Code expiré — Renvoyez un nouveau code';
        btnVerif.style.background = '#ccc';
        btnVerif.style.cursor     = 'not-allowed';
    }
}, 1000);
</script>

<?php include '../includes/footer.php'; ?>