<?php
// Page d'inscription
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Si l'utilisateur est déjà connecté, rediriger vers la page d'accueil
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom        = trim($_POST['nom'] ?? '');
    $prenom     = trim($_POST['prenom'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $telephone  = trim($_POST['telephone'] ?? '');
    $civilite   = $_POST['civilite'] ?? 'Mme';
    $newsletter = isset($_POST['newsletter']) ? 1 : 0;

    $errors = [];

    if (empty($nom))    $errors[] = 'Le nom est requis.';
    if (empty($prenom)) $errors[] = 'Le prénom est requis.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Une adresse email valide est requise.';

    if (empty($password)) {
        $errors[] = 'Le mot de passe est requis.';
    } else {
        if (strlen($password) < 8)              $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        if (!preg_match('/[A-Z]/', $password))  $errors[] = 'Le mot de passe doit contenir au moins une majuscule.';
        if (!preg_match('/[a-z]/', $password))  $errors[] = 'Le mot de passe doit contenir au moins une minuscule.';
        if (!preg_match('/[0-9]/', $password))  $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
        if (!preg_match('/[\W]/', $password))   $errors[] = 'Le mot de passe doit contenir au moins un caractère spécial.';
    }

    if ($password !== $password_confirm) $errors[] = 'Les mots de passe ne correspondent pas.';

    // Vérifier si l'email existe déjà
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Cet email est déjà utilisé.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Une erreur est survenue. Veuillez réessayer.';
        }
    }

    // Inscription si pas d'erreurs
    if (empty($errors)) {

        // ✅ ÉTAPE 1 : Générer un OTP et sauvegarder toutes les données en session
        // On n'insère PAS encore en BDD → on attend la validation du code OTP
        require_once '../includes/envoyer-email.php';

        $otp_code    = genererCodeOTP();
        $otp_expires = time() + (15 * 60); // Expire dans 15 minutes

        $_SESSION['otp_inscription_pending'] = [
            'otp_code'     => $otp_code,
            'otp_expires'  => $otp_expires,
            'email'        => $email,
            'password'     => password_hash($password, PASSWORD_DEFAULT),
            'nom'          => $nom,
            'prenom'       => $prenom,
            'telephone'    => $telephone,
            'civilite'     => $civilite,
            'newsletter'   => $newsletter,
        ];

        // ✅ ÉTAPE 2 : Envoyer le code OTP par email
        if (envoyerCodeVerification($email, $prenom, $otp_code)) {
            // ✅ ÉTAPE 3 : Redirection vers la page de vérification
            header('Location: verify-inscription.php');
            exit();
        } else {
            // Échec envoi email → on annule
            unset($_SESSION['otp_inscription_pending']);
            $error = 'Erreur lors de l\'envoi du code de vérification. Veuillez réessayer.';
        }

    } else {
        $error = implode('<br>', $errors);
    }
}

$pageTitle = "Inscription - Lumoura";
include '../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-box">
        <h1 class="auth-title">Créer un compte</h1>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="auth-form">
            <!-- Civilité -->
            <div class="form-group">
                <label class="form-label">Civilité</label>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="civilite" value="Mme" <?php echo (!isset($_POST['civilite']) || $_POST['civilite'] == 'Mme') ? 'checked' : ''; ?>>
                        <span>Madame</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="civilite" value="M" <?php echo (isset($_POST['civilite']) && $_POST['civilite'] == 'M') ? 'checked' : ''; ?>>
                        <span>Monsieur</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="civilite" value="Mlle" <?php echo (isset($_POST['civilite']) && $_POST['civilite'] == 'Mlle') ? 'checked' : ''; ?>>
                        <span>Mademoiselle</span>
                    </label>
                </div>
            </div>

            <!-- Nom et Prénom -->
            <div class="form-row">
                <div class="form-group">
                    <label for="nom" class="form-label"><i class="fas fa-user"></i> Nom *</label>
                    <input type="text" id="nom" name="nom" class="form-input"
                           value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="prenom" class="form-label"><i class="fas fa-user"></i> Prénom *</label>
                    <input type="text" id="prenom" name="prenom" class="form-input"
                           value="<?php echo isset($_POST['prenom']) ? htmlspecialchars($_POST['prenom']) : ''; ?>" required>
                </div>
            </div>

            <!-- Email -->
            <div class="form-group">
                <label for="email" class="form-label"><i class="fas fa-envelope"></i> Adresse email *</label>
                <input type="email" id="email" name="email" class="form-input"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>

            <!-- Téléphone -->
            <div class="form-group">
                <label for="telephone" class="form-label"><i class="fas fa-phone"></i> Téléphone</label>
                <input type="tel" id="telephone" name="telephone" class="form-input"
                       value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>">
            </div>

            <!-- Mot de passe -->
            <div class="form-row">
                <div class="form-group">
                    <label for="password" class="form-label"><i class="fas fa-lock"></i> Mot de passe *</label>
                    <input type="password" id="password" name="password" class="form-input" required>
                    <div class="password-hint">
                        <small>Minimum 8 caractères, 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial</small>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password_confirm" class="form-label"><i class="fas fa-lock"></i> Confirmer Mdp *</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-input" required>
                </div>
            </div>

            <!-- Newsletter -->
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="newsletter" value="1" checked>
                    <span>Je souhaite m'abonner à la newsletter pour recevoir les offres exclusives</span>
                </label>
            </div>

            <!-- Conditions -->
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="conditions" required <?php echo isset($_POST['conditions']) ? 'checked' : ''; ?>>
                    <span>J'accepte les <a href="#" style="color: var(--gold);">conditions générales</a> et la <a href="#" style="color: var(--gold);">politique de confidentialité</a> *</span>
                </label>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-user-plus"></i> Créer mon compte
            </button>
        </form>

        <div class="auth-links">
            <p>Vous avez déjà un compte ? <a href="connexion.php">Se connecter</a></p>
            <p><a href="index.php" class="btn-secondary">
                <i class="fas fa-home"></i> Retour à l'accueil
            </a></p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>