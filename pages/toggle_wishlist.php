<?php
/**
 * toggle-wishlist.php
 * Ajoute ou supprime un produit de la liste de favoris
 */

// La session est déjà démarrée dans config.php ou functions.php
header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour ajouter des favoris.',
        'redirect' => 'connexion.php'
    ]);
    exit();
}

// Vérifier la méthode de requête
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

// Validation du produit
if ($product_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Produit invalide'
    ]);
    exit();
}

try {
    // Vérifier si le produit existe
    $checkProduct = "SELECT id_produit FROM produits WHERE id_produit = ?";
    $stmtCheck = $pdo->prepare($checkProduct);
    $stmtCheck->execute([$product_id]);
    
    if (!$stmtCheck->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Produit introuvable'
        ]);
        exit();
    }
    
    // Vérifier si le produit est déjà dans les favoris
    $checkFavorite = "SELECT id_favori FROM favoris WHERE id_utilisateur = ? AND id_produit = ?";
    $stmtFav = $pdo->prepare($checkFavorite);
    $stmtFav->execute([$user_id, $product_id]);
    $favorite = $stmtFav->fetch();
    
    if ($favorite) {
        // Supprimer des favoris
        $deleteFav = "DELETE FROM favoris WHERE id_favori = ?";
        $stmtDelete = $pdo->prepare($deleteFav);
        $stmtDelete->execute([$favorite['id_favori']]);
        
        // Compter le nombre total de favoris
        $countQuery = "SELECT COUNT(*) as total FROM favoris WHERE id_utilisateur = ?";
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute([$user_id]);
        $count = $stmtCount->fetch()['total'];
        
        echo json_encode([
            'success' => true,
            'action' => 'removed',
            'message' => 'Produit retiré des favoris',
            'wishlist_count' => $count
        ]);
    } else {
        // Ajouter aux favoris
        $insertFav = "INSERT INTO favoris (id_utilisateur, id_produit, date_ajout) VALUES (?, ?, NOW())";
        $stmtInsert = $pdo->prepare($insertFav);
        $stmtInsert->execute([$user_id, $product_id]);
        
        // Compter le nombre total de favoris
        $countQuery = "SELECT COUNT(*) as total FROM favoris WHERE id_utilisateur = ?";
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute([$user_id]);
        $count = $stmtCount->fetch()['total'];
        
        echo json_encode([
            'success' => true,
            'action' => 'added',
            'message' => 'Produit ajouté aux favoris',
            'wishlist_count' => $count
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Erreur wishlist: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la mise à jour des favoris'
    ]);
}
?>