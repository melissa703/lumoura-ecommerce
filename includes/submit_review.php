<?php
// submit_review.php - Traitement de la soumission d'un avis client
session_start();

// Vérifier si c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/catalogue.php');
    exit();
}

// Inclure les fichiers nécessaires
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['error_message'] = 'Vous devez être connecté pour laisser un avis.';
    header('Location: ../pages/connexion.php');
    exit();
}

// Récupérer les données
$user_id    = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$rating     = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$comment    = isset($_POST['comment']) ? trim($_POST['comment']) : '';

// Validation des données
$errors = [];

if ($product_id <= 0) {
    $errors[] = 'Produit invalide.';
}

if ($rating < 1 || $rating > 5) {
    $errors[] = 'La note doit être entre 1 et 5 étoiles.';
}

if (empty($comment)) {
    $errors[] = 'Le commentaire ne peut pas être vide.';
} elseif (strlen($comment) < 10) {
    $errors[] = 'Le commentaire doit contenir au moins 10 caractères.';
} elseif (strlen($comment) > 1000) {
    // ✅ CORRECTION : Limite la longueur du commentaire
    $errors[] = 'Le commentaire ne peut pas dépasser 1000 caractères.';
}

// Si pas d'erreurs, insérer dans la base
if (empty($errors)) {
    try {
        // ✅ CORRECTION : Vérifier si l'utilisateur a déjà laissé un avis sur CE produit
        // Limite à 1 avis par utilisateur par produit
        $checkQuery = "SELECT id_avis FROM avis WHERE id_utilisateur = ? AND id_produit = ?";
        $checkStmt  = $pdo->prepare($checkQuery);
        $checkStmt->execute([$user_id, $product_id]);

        if ($checkStmt->fetch()) {
            // ✅ CORRECTION : Message clair en français
            $errors[] = 'Vous avez déjà laissé un avis sur ce produit. Un seul avis par produit est autorisé.';
        } else {
            // ✅ CORRECTION : Statut 'en attente' par défaut (modération admin avant publication)
            // ✅ CORRECTION : Suppression de la mise à jour note_moyenne/nombre_avis
            //    (ces colonnes n'existent pas dans la table produits → erreur SQL silencieuse)
            $insertQuery = "INSERT INTO avis (id_utilisateur, id_produit, note, commentaire, date_avis, statut)
                           VALUES (?, ?, ?, ?, NOW(), 'en attente')";
            $insertStmt  = $pdo->prepare($insertQuery);
            $insertStmt->execute([$user_id, $product_id, $rating, sanitize($comment)]);

            error_log("Avis inséré avec ID: " . $pdo->lastInsertId());

            $_SESSION['success_message'] = 'Votre avis a été soumis et sera visible après validation par notre équipe. Merci !';
        }

    } catch (PDOException $e) {
        $errors[] = 'Erreur lors de l\'enregistrement de votre avis.';
        error_log("Erreur PDO submit_review: " . $e->getMessage());
    }
}

// Gérer les erreurs
if (!empty($errors)) {
    $_SESSION['review_errors'] = $errors;
    $_SESSION['review_data']   = [
        'rating'     => $rating,
        'comment'    => $comment,
        'product_id' => $product_id
    ];
}

// Redirection vers la page produit
header('Location: ../pages/produit.php?id=' . $product_id);
exit();
?>