<?php
require '../includes/db.php';

$token = $_GET['token'] ?? '';

$stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE reset_token = ? AND reset_token_expires > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die("
        <div style='text-align:center; padding:60px; font-family:Georgia; background:#f5f0eb; min-height:100vh;'>
            <div style='background:white; max-width:400px; margin:auto; padding:40px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.1);'>
                <h3 style='color:#8B6914;'>Lien invalide ou expiré</h3>
                <p style='color:#888; margin:15px 0;'>Ce lien a expiré ou a déjà été utilisé.</p>
                <a href='forgot_password.php'
                   style='background:linear-gradient(135deg,#C9972A,#8B6914); color:white; padding:12px 25px; text-decoration:none; border-radius:8px; font-weight:bold;'>
                    Faire une nouvelle demande
                </a>
            </div>
        </div>
    ");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouveau mot de passe – Lumoura</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background-color: #f5f0eb;
            font-family: 'Georgia', serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        h2 { color: #8B6914; margin-bottom: 10px; font-size: 24px; }
        .subtitle { color: #888; font-size: 14px; margin-bottom: 25px; }
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            background: #f9f6f2;
            margin-bottom: 15px;
        }
        .btn-gold {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #C9972A, #8B6914);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .btn-gold:hover { opacity: 0.9; }
        .msg-error { color: red; margin-bottom: 15px; font-size: 14px; }
    </style>
</head>
<body>
<div class="card">
    <h2>Nouveau mot de passe</h2>
    <p class="subtitle">Choisissez un nouveau mot de passe sécurisé (8 caractères minimum).</p>

    <?php if (isset($_GET['error'])): ?>
        <p class="msg-error">❌ Les mots de passe ne correspondent pas.</p>
    <?php endif; ?>

    <form action="reset_password_process.php" method="POST">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="password" name="password" required minlength="8" placeholder="Nouveau mot de passe">
        <input type="password" name="password_confirm" required minlength="8" placeholder="Confirmer le mot de passe">
        <button type="submit" class="btn-gold">✅ VALIDER</button>
    </form>
</div>
</body>
</html>