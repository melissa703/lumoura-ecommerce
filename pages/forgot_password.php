<?php session_start(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mot de passe oublié – Lumoura</title>
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
        input[type="email"] {
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
        .msg-success { color: green; margin-bottom: 15px; font-size: 14px; }
        .msg-error   { color: red;   margin-bottom: 15px; font-size: 14px; }
        .back-link {
            margin-top: 20px;
            display: block;
            color: #C9972A;
            font-size: 14px;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="card">
    <h2>Mot de passe oublié</h2>
    <p class="subtitle">Entrez votre adresse email, nous vous enverrons un lien de réinitialisation.</p>

    <?php if (isset($_GET['success'])): ?>
        <p class="msg-success">✅ Un email de réinitialisation a été envoyé !</p>
    <?php elseif (isset($_GET['error']) && $_GET['error'] == 1): ?>
        <p class="msg-error">❌ Aucun compte trouvé avec cet email.</p>
    <?php elseif (isset($_GET['error']) && $_GET['error'] == 2): ?>
        <p class="msg-error">❌ Erreur lors de l'envoi de l'email. Réessayez.</p>
    <?php endif; ?>

    <form action="forgot_password_process.php" method="POST">
        <input type="email" name="email" required placeholder="Votre adresse email">
        <button type="submit" class="btn-gold">📧 ENVOYER LE LIEN</button>
    </form>

    <a href="connexion.php" class="back-link">← Retour à la connexion</a>
</div>
</body>
</html>