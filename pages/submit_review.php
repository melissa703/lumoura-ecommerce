<?php
// submit_review.php - Version avec débogage intégré
session_start();

// Activer tous les messages d'erreur
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log pour suivre l'exécution
error_log("=== submit_review.php appelé ===");
error_log("Méthode: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("SESSION data: " . print_r($_SESSION, true));

// Vérifier si c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("ERREUR: Accès direct sans POST");
    header('Location: ../pages/catalogue.php');
    exit();
}

// Inclure les fichiers nécessaires
$included = true;
if (!@include_once 'config.php') {
    error_log("ERREUR: config.php non trouvé");
    $included = false;
}
if (!@include_once 'db.php') {
    error_log("ERREUR: db.php non trouvé");
    $included = false;
}
if (!@include_once 'functions.php') {
    error_log("ERREUR: functions.php non trouvé");
    $included = false;
}

if (!$included) {
    die("Erreur: fichiers manquants");
}

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    error_log("ERREUR: Utilisateur non connecté");
    $_SESSION['error_message'] = 'Vous devez être connecté pour laisser un avis.';
    header('Location: ../pages/connexion.php');
    exit();
}

// Récupérer les données
$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

error_log("Données reçues - user_id: $user_id, product_id: $product_id, rating: $rating");

// Validation
$errors = [];

if ($product_id <= 0) {
    $errors[] = 'Produit invalide.';
    error_log("Produit invalide: $product_id");
}

if ($rating < 1 || $rating > 5) {
    $errors[] = 'La note doit être entre 1 et 5 étoiles.';
    error_log("Note invalide: $rating");
}

if (empty($comment)) {
    $errors[] = 'Le commentaire ne peut pas être vide.';
    error_log("Commentaire vide");
} elseif (strlen($comment) < 10) {
    $errors[] = 'Le commentaire doit contenir au moins 10 caractères.';
    error_log("Commentaire trop court: " . strlen($comment) . " caractères");
}

// Si pas d'erreurs, insérer dans la base
if (empty($errors)) {
    try {
        error_log("Tentative d'insertion dans la base de données...");
        
        // Vérifier si l'utilisateur a déjà laissé un avis
        $checkQuery = "SELECT id_avis FROM avis WHERE id_utilisateur = ? AND id_produit = ?";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([$user_id, $product_id]);
        
        if ($checkStmt->fetch()) {
            $errors[] = 'Vous avez déjà laissé un avis sur ce produit.';
            error_log("Avis déjà existant pour user $user_id sur produit $product_id");
        } else {
            // Insérer l'avis
            $insertQuery = "INSERT INTO avis (id_utilisateur, id_produit, note, commentaire, date_avis, statut) 
                           VALUES (?, ?, ?, ?, NOW(), 'approuve')";
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->execute([$user_id, $product_id, $rating, sanitize($comment)]);
            
            $avis_id = $pdo->lastInsertId();
            error_log("Avis inséré avec ID: $avis_id");
            
            // Mettre à jour la note moyenne
            $updateQuery = "UPDATE produits 
                           SET note_moyenne = (
                               SELECT AVG(note) FROM avis WHERE id_produit = ? AND statut = 'approuve'
                           ),
                           nombre_avis = (
                               SELECT COUNT(*) FROM avis WHERE id_produit = ? AND statut = 'approuve'
                           )
                           WHERE id_produit = ?";
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->execute([$product_id, $product_id, $product_id]);
            
            error_log("Produit $product_id mis à jour avec nouvelle note moyenne");
            
            $_SESSION['success_message'] = 'Votre avis a été publié avec succès !';
            error_log("Message de succès défini");
        }
        
    } catch (PDOException $e) {
        $errors[] = 'Erreur lors de l\'enregistrement de votre avis.';
        error_log("Erreur PDO: " . $e->getMessage());
    }
}

// Gérer les erreurs
if (!empty($errors)) {
    $_SESSION['review_errors'] = $errors;
    $_SESSION['review_data'] = [
        'rating' => $rating,
        'comment' => $comment,
        'product_id' => $product_id
    ];
    error_log("Erreurs enregistrées: " . implode(', ', $errors));
}

// Redirection
error_log("Redirection vers produit.php?id=$product_id");
header('Location: ../pages/produit.php?id=' . $product_id);
exit();
?>