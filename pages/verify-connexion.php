<?php
// ============================================================
// FICHIER : pages/verify-connexion.php
// RÔLE    : Page de vérification du code OTP envoyé par email
//           lors de la connexion de l'utilisateur
//
// FLUX COMPLET :
//   1. connexion.php → email + mot de passe corrects
//   2. connexion.php → génère OTP, sauvegarde en session, envoie email
//   3. verify-connexion.php → l'utilisateur saisit son code OTP
//   4. Code correct → session ouverte → redirection (admin ou accueil)
// ============================================================

// ⚠️ PAS de session_start() ici — déjà fait dans config.php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// ============================================================
// VÉRIFICATION QU'UN OTP DE CONNEXION EST EN ATTENTE
// ============================================================
// Si l'utilisateur arrive directement sur cette page sans
// passer par connexion.php → pas de données en session → on redirige.
if (!isset($_SESSION['otp_login_pending'])) {
    header("Location: " . SITE_URL . "pages/connexion.php");
    exit();
}

// Si l'utilisateur est déjà connecté → pas besoin d'être ici
if (isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "index.php");
    exit();
}

$erreur      = '';
$success_msg = '';

// ============================================================
// TRAITEMENT DU FORMULAIRE DE VÉRIFICATION (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verifier_otp'])) {

    $code_saisi = trim($_POST['otp_code'] ?? '');
    $pending    = $_SESSION['otp_login_pending'];

    // Vérification 1 : le code correspond-il ?
    // Vérification 2 : n'est-il pas expiré ?
    if ($code_saisi === $pending['otp_code'] && time() < $pending['otp_expires']) {

        // ✅ CODE VALIDE → On ouvre enfin la session utilisateur
        $_SESSION['user_id'] = $pending['user_id'];
        $_SESSION['email']   = $pending['email'];
        $_SESSION['nom']     = $pending['nom'];
        $_SESSION['prenom']  = $pending['prenom'];
        $_SESSION['role']    = $pending['role'];

        // Nettoyage : les données OTP ne sont plus nécessaires
        unset($_SESSION['otp_login_pending']);

        // Redirection selon le rôle
        if ($pending['role'] === 'admin') {
            header('Location: ' . SITE_URL . 'pages/admin/admin.php');
        } else {
            header('Location: ' . SITE_URL . 'pages/index.php');
        }
        exit();

    } elseif (time() >= $pending['otp_expires']) {
        // Code expiré
        $erreur = "⏱️ Ce code a expiré. Veuillez vous reconnecter pour recevoir un nouveau code.";
        unset($_SESSION['otp_login_pending']);
    } else {
        // Code incorrect
        $erreur = "❌ Code incorrect. Vérifiez votre email et réessayez.";
    }
}

// ============================================================
// RENVOYER LE CODE (si l'utilisateur n'a pas reçu l'email)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renvoyer_otp'])) {

    if (isset($_SESSION['otp_login_pending'])) {
        require_once '../includes/envoyer-email.php';

        // Génère un nouveau code et remet le timer à 15 minutes
        $nouveau_code = genererCodeOTP();
        $_SESSION['otp_login_pending']['otp_code']    = $nouveau_code;
        $_SESSION['otp_login_pending']['otp_expires'] = time() + (15 * 60);

        $pending = $_SESSION['otp_login_pending'];

        if (envoyerCodeVerification($pending['email'], $pending['prenom'], $nouveau_code)) {
            $success_msg = "✅ Un nouveau code a été envoyé à votre adresse email.";
        } else {
            $erreur = "❌ Erreur lors de l'envoi. Veuillez réessayer.";
        }
    } else {
        // Session expirée → retour connexion
        header("Location: " . SITE_URL . "pages/connexion.php");
        exit();
    }
}

// Email masqué pour l'affichage (ex: s***@gmail.com)
$email_complet = $_SESSION['otp_login_pending']['email'] ?? '';
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
                <i class="fas fa-shield-alt" style="font-size: 36px; color: white;"></i>
            </div>
        </div>

        <h1 class="auth-title">Vérification en deux étapes</h1>

        <p style="text-align: center; color: #888; margin-bottom: 30px; font-size: 15px;">
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

        <!-- Formulaire de vérification OTP -->
        <form method="POST" action="" class="auth-form">

            <div class="form-group">
                <label class="form-label" style="text-align: center; display: block;">
                    <i class="fas fa-key"></i> Entrez votre code de vérification
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
                <i class="fas fa-check-circle"></i> Vérifier et se connecter
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
                <a href="connexion.php" style="color: #aaa; font-size: 13px; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Retour à la connexion
                </a>
            </p>
        </div>

    </div>
</div>

<!-- ============================================================ -->
<!-- JAVASCRIPT : Minuterie compte à rebours                      -->
<!-- ============================================================ -->
<script>
let tempsRestant = 900; // 15 minutes en secondes

const timerEl  = document.getElementById('timer');
const btnVerif = document.getElementById('btn-verify');

const interval = setInterval(function() {
    tempsRestant--;

    const minutes  = Math.floor(tempsRestant / 60);
    const secondes = tempsRestant % 60;
    timerEl.textContent = minutes + ':' + String(secondes).padStart(2, '0');

    // Alerte rouge quand moins de 2 minutes
    if (tempsRestant <= 120) {
        timerEl.style.color = '#dc3545';
    }

    // Timer arrivé à 0
    if (tempsRestant <= 0) {
        clearInterval(interval);
        timerEl.textContent     = 'Expiré';
        btnVerif.disabled       = true;
        btnVerif.innerHTML      = '⏱️ Code expiré — Renvoyez un nouveau code';
        btnVerif.style.background = '#ccc';
        btnVerif.style.cursor   = 'not-allowed';
    }
}, 1000);
</script>

<?php include '../includes/footer.php'; ?>