<?php
/**
 * get-wishlist.php
 * Renvoie la liste des IDs de produits favoris de l'utilisateur connecté
 */

// La session est déjà démarrée dans config.php ou functions.php
header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    echo json_encode([
        'success' => true,
        'favorites' => [],
        'count' => 0,
        'logged_in' => false
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Récupérer tous les IDs de produits favoris
    $query = "SELECT id_produit FROM favoris WHERE id_utilisateur = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $favorites = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Convertir en entiers
    $favorites = array_map('intval', $favorites);
    
    echo json_encode([
        'success' => true,
        'favorites' => $favorites,
        'count' => count($favorites),
        'logged_in' => true
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur get-wishlist: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des favoris',
        'favorites' => [],
        'count' => 0
    ]);
}
?>