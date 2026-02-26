<?php
// submit_review.php - Version simplifiée : plusieurs avis autorisés, pas de restriction sur commentaire
// Plus de message d'erreur utilisateur dans le catch

session_start();

// Activer les erreurs pour debug (à enlever en prod une fois tout OK)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log pour suivre (utile pour debug)
error_log("=== submit_review.php appelé ===");
error_log("Méthode: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));

// Vérifier POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("ERREUR: Accès direct sans POST");
    header('Location: ../pages/catalogue.php');
    exit();
}

// Inclure fichiers
$included = true;
if (!@include_once 'config.php') { error_log("ERREUR: config.php non trouvé"); $included = false; }
if (!@include_once 'db.php') { error_log("ERREUR: db.php non trouvé"); $included = false; }
if (!@include_once 'functions.php') { error_log("ERREUR: functions.php non trouvé"); $included = false; }

if (!$included) {
    die("Erreur: fichiers manquants");
}

// Vérifier connexion
if (!isLoggedIn()) {
    error_log("ERREUR: Utilisateur non connecté");
    $_SESSION['error_message'] = 'Vous devez être connecté pour laisser un avis.';
    header('Location: ../pages/connexion.php');
    exit();
}

// Récupérer données
$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

// Validation minimale (essentielle)
$errors = [];

if ($product_id <= 0) {
    $errors[] = 'Produit invalide.';
    error_log("Produit invalide: $product_id");
}

if ($rating < 1 || $rating > 5) {
    $errors[] = 'La note doit être entre 1 et 5 étoiles.';
    error_log("Note invalide: $rating");
}

// Si pas d'erreurs de validation de base, on tente l'insertion
if (empty($errors)) {
    try {
        error_log("Tentative d'insertion...");

        // Insert (pas de check duplicate)
        $insertQuery = "INSERT INTO avis (id_utilisateur, id_produit, note, commentaire, date_avis, statut) 
                        VALUES (?, ?, ?, ?, NOW(), 'approuve')";
        $insertStmt = $pdo->prepare($insertQuery);
        $insertStmt->execute([$user_id, $product_id, $rating, sanitize($comment)]);
        
        $avis_id = $pdo->lastInsertId();
        error_log("Avis inséré avec ID: $avis_id");
        
        // Update produit (note moyenne + nombre avis)
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
        
        error_log("Produit $product_id mis à jour");
        
        // Succès !
        $_SESSION['success_message'] = 'Votre avis a été publié avec succès !';
        
    } catch (PDOException $e) {
        // On log l'erreur pour toi (dans error_log), mais on n'affiche RIEN à l'utilisateur
        error_log("Erreur PDO lors insert/avis : " . $e->getMessage());
        // → Pas de $errors[] ici = pas de message rouge
    }
}

// Gestion erreurs (seulement les validations de base, pas les PDO)
if (!empty($errors)) {
    $_SESSION['review_errors'] = $errors;
    $_SESSION['review_data'] = [
        'rating' => $rating,
        'comment' => $comment,
        'product_id' => $product_id
    ];
    error_log("Erreurs de validation: " . implode(', ', $errors));
}

// Redirection
error_log("Redirection vers produit.php?id=$product_id");
header('Location: ../pages/produit.php?id=' . $product_id);
exit();
?>