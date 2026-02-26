<?php
// ============================================================
// FICHIER : connexion.php
// RÔLE    : Page de connexion utilisateur
// ============================================================

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } 
    elseif (strlen($password) < 8) {
        $error = 'Le mot de passe doit contenir au minimum 8 caractères.';
    } 
    else {

        try {
            $query = "SELECT * FROM utilisateurs WHERE email = ?";
            $stmt  = $pdo->prepare($query);
            $stmt->execute([$email]);
            $user  = $stmt->fetch();

            if ($user && password_verify($password, $user['mot_de_passe'])) {

                // ✅ ÉTAPE 1 : Identifiants corrects → on NE connecte PAS encore l'utilisateur
                // On génère un code OTP et on l'envoie par email AVANT d'ouvrir la session.

                require_once '../includes/envoyer-email.php';

                $otp_code    = genererCodeOTP();
                $otp_expires = time() + (15 * 60); // Expire dans 15 minutes

                // ✅ ÉTAPE 2 : On sauvegarde en session les infos nécessaires APRÈS validation OTP
                // On ne met PAS encore user_id en session (l'utilisateur n'est pas encore connecté)
                $_SESSION['otp_login_pending'] = [
                    'otp_code'    => $otp_code,
                    'otp_expires' => $otp_expires,
                    'user_id'     => $user['id_utilisateur'],
                    'email'       => $user['email'],
                    'nom'         => $user['nom'],
                    'prenom'      => $user['prenom'],
                    'role'        => $user['role'],
                ];

                // ✅ ÉTAPE 3 : Envoi du code OTP par email
                if (envoyerCodeVerification($user['email'], $user['prenom'], $otp_code)) {
                    // Redirection vers la page de vérification
                    header('Location: verify-connexion.php');
                    exit();
                } else {
                    // Échec envoi email → on annule et on affiche une erreur
                    unset($_SESSION['otp_login_pending']);
                    $error = 'Erreur lors de l\'envoi du code de vérification. Veuillez réessayer.';
                }

            } else {
                $error = 'Email ou mot de passe incorrect.';
            }

        } catch (PDOException $e) {
            $error = 'Une erreur est survenue. Veuillez réessayer.';
        }
    }
}

$pageTitle = "Connexion - Lumoura";
include '../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-box">
        <h1 class="auth-title">Connexion</h1>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="auth-form">

            <div class="form-group">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope"></i> Adresse email
                </label>

                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-input"
                    value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : '' ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password" class="form-label">
                    <i class="fas fa-lock"></i> Mot de passe
                </label>

                <div style="position: relative;">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input" 
                        minlength="8"
                        required 
                        style="padding-right: 45px;"
                    >

                    <span onclick="togglePassword()" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #888;">
                        <i class="fas fa-eye" id="eye-icon"></i>
                    </span>
                </div>

                <div style="text-align: right; margin-top: 5px;">
                    <a href="#" style="font-size: 14px; color: var(--gold);">
                        Mot de passe oublié ?
                    </a>
                </div>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="remember" checked>
                    <span>Se souvenir de moi</span>
                </label>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </button>
        </form>

        <div class="auth-links">
            <p>Vous n'avez pas de compte ? 
                <a href="inscription.php">Créer un compte</a>
            </p>

            <p>
                <a href="index.php" class="btn-secondary">
                    <i class="fas fa-home"></i> Retour à l'accueil
                </a>
            </p>
        </div>

        <div class="social-login">
            <p style="text-align: center; margin: 20px 0; color: var(--gray);">
                Ou connectez-vous avec
            </p>

            <div class="social-buttons">
                <button class="social-btn facebook">
                    <i class="fab fa-facebook-f"></i> Facebook
                </button>

                <button class="social-btn google">
                    <i class="fab fa-google"></i> Google
                </button>
            </div>
        </div>

    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('eye-icon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>

<?php include '../includes/footer.php'; ?>