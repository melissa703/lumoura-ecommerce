<?php
require '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token    = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if ($password !== $confirm) {
        header("Location: reset_password.php?token=" . urlencode($token) . "&error=1");
        exit;
    }

    $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        die("Lien invalide ou expiré. <a href='forgot_password.php'>Recommencer</a>");
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ?, reset_token = NULL, reset_token_expires = NULL WHERE id_utilisateur = ?");
    $stmt->execute([$hash, $user['id_utilisateur']]);

    header("Location: connexion.php?reset=success");
    exit;
}
?>