<?php
// ============================================================
// FICHIER : pages/verify-adresse.php
// RÔLE    : Vérification du code OTP envoyé par email
//           avant d'insérer une nouvelle adresse en BDD
// FLUX    : compte.php → (envoi OTP) → verify-adresse.php → compte.php
// ============================================================

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// L'utilisateur doit être connecté
if (!isLoggedIn()) {
    header('Location: connexion.php');
    exit();
}

// Il doit y avoir une adresse en attente de validation
if (!isset($_SESSION['otp_adresse_pending'])) {
    header('Location: compte.php');
    exit();
}

$pending  = $_SESSION['otp_adresse_pending'];
$erreur   = '';
$user_id  = $_SESSION['user_id'];

// ============================================================
// TRAITEMENT : Vérification du code OTP soumis
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp_code'])) {

    $code_saisi = trim($_POST['otp_code']);

    // Vérifier si le code a expiré (15 minutes)
    if (time() > $pending['otp_expires']) {
        unset($_SESSION['otp_adresse_pending']);
        $_SESSION['flash_error'] = "Le code de vérification a expiré. Veuillez recommencer.";
        header('Location: compte.php');
        exit();
    }

    // Vérifier si le code est correct
    if ($code_saisi === (string)$pending['otp_code']) {

        // ✅ Code valide → insérer l'adresse en BDD
        try {
            // Si cette adresse est principale, on retire le flag des autres
            if ($pending['est_principale']) {
                $pdo->prepare("UPDATE adresses SET est_principale = 0 WHERE id_utilisateur = ?")
                    ->execute([$user_id]);
            }

            $stmt = $pdo->prepare("
                INSERT INTO adresses
                    (id_utilisateur, prenom, nom, adresse, complement, code_postal, ville, pays, telephone, est_principale)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $pending['prenom'],
                $pending['nom'],
                $pending['adresse'],
                $pending['complement'],
                $pending['cp'],
                $pending['ville'],
                $pending['pays'],
                $pending['telephone'],
                $pending['est_principale'],
            ]);

            // Nettoyer la session OTP
            unset($_SESSION['otp_adresse_pending']);

            // Message flash de succès → affiché dans compte.php
            $_SESSION['flash_success'] = "Adresse ajoutée avec succès !";
            header('Location: compte.php');
            exit();

        } catch (PDOException $e) {
            $erreur = "Une erreur est survenue lors de l'enregistrement. Veuillez réessayer.";
        }

    } else {
        $erreur = "Code incorrect. Veuillez vérifier votre email et réessayer.";
    }
}

// ============================================================
// RENVOI DU CODE OTP
// ============================================================
if (isset($_GET['renvoyer'])) {
    require_once '../includes/envoyer-email.php';

    // Récupérer les infos de l'utilisateur
    $stmt = $pdo->prepare("SELECT prenom, email FROM utilisateurs WHERE id_utilisateur = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // Générer un nouveau code
    $nouveau_otp     = genererCodeOTP();
    $nouveau_expires = time() + (15 * 60);

    $_SESSION['otp_adresse_pending']['otp_code']    = $nouveau_otp;
    $_SESSION['otp_adresse_pending']['otp_expires'] = $nouveau_expires;

    if (envoyerCodeVerification($user['email'], $user['prenom'], $nouveau_otp)) {
        $message_renvoye = "Un nouveau code a été envoyé à votre adresse email.";
    } else {
        $erreur = "Erreur lors de l'envoi du code. Veuillez réessayer.";
    }
}

// Calcul du temps restant
$temps_restant = max(0, $pending['otp_expires'] - time());
$minutes_restantes = ceil($temps_restant / 60);

$pageTitle = "Vérification de votre adresse";
include '../includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;600&display=swap');

.verify-section {
    min-height: 70vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #faf8f5;
    padding: 60px 20px;
}

.verify-card {
    background: white;
    border-radius: 20px;
    padding: 50px 45px;
    max-width: 480px;
    width: 100%;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    text-align: center;
}

.verify-icon {
    width: 72px;
    height: 72px;
    background: linear-gradient(135deg, #d4a017, #b8860b);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
    font-size: 1.8rem;
    color: white;
}

.verify-card h1 {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.9em;
    font-weight: 400;
    color: #2c1810;
    margin-bottom: 10px;
}

.verify-card p.subtitle {
    color: #888;
    font-size: 0.95em;
    line-height: 1.6;
    margin-bottom: 32px;
}

.verify-card p.subtitle strong {
    color: #2c1810;
}

/* Champ OTP */
.otp-input {
    width: 100%;
    text-align: center;
    font-size: 2em;
    font-weight: 700;
    letter-spacing: 12px;
    color: #2c1810;
    background: #faf8f5;
    border: 2px solid #e0d5c5;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 24px;
    transition: border-color 0.2s;
    outline: none;
}
.otp-input:focus {
    border-color: #d4a017;
    background: white;
}

.btn-verify {
    width: 100%;
    background: linear-gradient(135deg, #d4a017, #b8860b);
    color: white;
    border: none;
    padding: 15px;
    border-radius: 12px;
    font-size: 1em;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    letter-spacing: 0.5px;
    margin-bottom: 20px;
}
.btn-verify:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(212,160,23,0.35);
}

.alert-error {
    background: #fef2f2;
    border: 1px solid #fca5a5;
    color: #b91c1c;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 0.9em;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.alert-success {
    background: #f0fdf4;
    border: 1px solid #86efac;
    color: #166534;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 0.9em;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.verify-footer {
    border-top: 1px solid #f0ebe3;
    padding-top: 20px;
    margin-top: 10px;
    font-size: 0.88em;
    color: #aaa;
}

.verify-footer a {
    color: #d4a017;
    text-decoration: none;
    font-weight: 600;
}
.verify-footer a:hover { text-decoration: underline; }

.timer {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #faf8f5;
    border: 1px solid #e0d5c5;
    border-radius: 20px;
    padding: 6px 14px;
    font-size: 0.82em;
    color: #888;
    margin-bottom: 24px;
}
.timer i { color: #d4a017; }
</style>

<section class="verify-section">
    <div class="verify-card">

        <div class="verify-icon">
            <i class="fas fa-envelope-open-text"></i>
        </div>

        <h1>Vérification email</h1>
        <p class="subtitle">
            Nous avons envoyé un code à 6 chiffres à votre adresse email.<br>
            Saisissez-le ci-dessous pour confirmer l'ajout de l'adresse :
            <br><br>
            <strong><?= htmlspecialchars($pending['adresse'] . ', ' . $pending['cp'] . ' ' . $pending['ville']) ?></strong>
        </p>

        <?php if ($temps_restant > 0): ?>
        <div class="timer">
            <i class="fas fa-clock"></i>
            Code valide encore <?= $minutes_restantes ?> minute<?= $minutes_restantes > 1 ? 's' : '' ?>
        </div>
        <?php endif; ?>

        <?php if ($erreur): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erreur) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($message_renvoye)): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message_renvoye) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <input
                type="text"
                name="otp_code"
                class="otp-input"
                placeholder="000000"
                maxlength="6"
                pattern="\d{6}"
                inputmode="numeric"
                autocomplete="one-time-code"
                autofocus
                required
            >
            <button type="submit" class="btn-verify">
                <i class="fas fa-check-circle"></i> Confirmer le code
            </button>
        </form>

        <div class="verify-footer">
            Vous n'avez pas reçu le code ?
            <a href="verify-adresse.php?renvoyer=1">Renvoyer un nouveau code</a>
            <br><br>
            <a href="compte.php"><i class="fas fa-arrow-left"></i> Annuler et retourner à mon compte</a>
        </div>

    </div>
</section>

<script>
// Auto-soumettre le formulaire quand 6 chiffres sont saisis
document.querySelector('.otp-input').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, ''); // Garde uniquement les chiffres
    if (this.value.length === 6) {
        this.closest('form').submit();
    }
});
</script>

<?php include '../includes/footer.php'; ?>