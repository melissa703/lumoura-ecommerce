<?php
// ============================================================
// FICHIER : ajouter_au_panier.php
// RÔLE    : Endpoint AJAX — Ajoute un produit au panier
//           et renvoie une réponse au format JSON
// APPELÉ  : Par du JavaScript (fetch/XMLHttpRequest) depuis
//           les pages produit ou catalogue, PAS directement
//           dans le navigateur par l'utilisateur
// ============================================================

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// --- DÉFINIR LE TYPE DE RÉPONSE EN JSON ---
// header() envoie un en-tête HTTP avant tout contenu
// Cela indique au navigateur/JS que la réponse est du JSON
// IMPORTANT : doit être appelé avant tout echo
header('Content-Type: application/json');

// ============================================================
// SECTION 1 : VÉRIFICATION DE CONNEXION
// ============================================================
// Un utilisateur non connecté ne peut pas avoir de panier
// On renvoie un JSON avec success:false → le JS peut afficher un message
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Veuillez vous connecter.']);
    exit(); // Stoppe l'exécution du reste du fichier
}

// ============================================================
// SECTION 2 : RÉCUPÉRATION ET VALIDATION DES DONNÉES POST
// ============================================================
$user_id    = $_SESSION['user_id'];

// isset() vérifie que la variable existe avant de l'utiliser (évite les notices PHP)
// intval() convertit en entier → sécurité contre les injections
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

// max(1, ...) : la quantité minimum est 1, même si l'utilisateur envoie 0 ou négatif
$quantity   = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;

// Vérification : l'ID produit doit être un entier positif
if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Produit invalide.']);
    exit();
}

// ============================================================
// SECTION 3 : LOGIQUE D'AJOUT AU PANIER (avec try/catch)
// ============================================================
try {
    // --- ÉTAPE 1 : Vérifier que le produit existe en BDD et récupérer son stock ---
    $check_product = $pdo->prepare("SELECT stock FROM produits WHERE id_produit = ?");
    $check_product->execute([$product_id]);
    $product = $check_product->fetch();
    
    if (!$product) {
        // Le produit n'existe pas dans la BDD
        echo json_encode(['success' => false, 'message' => 'Produit introuvable.']);
        exit();
    }
    
    // Vérification du stock : on ne peut pas commander plus que ce qui est disponible
    if ($product['stock'] < $quantity) {
        echo json_encode(['success' => false, 'message' => 'Stock insuffisant.']);
        exit();
    }
    
    // --- ÉTAPE 2 : Vérifier si ce produit est DÉJÀ dans le panier de cet utilisateur ---
    $check = $pdo->prepare("SELECT quantite FROM panier WHERE id_utilisateur = ? AND id_produit = ?");
    $check->execute([$user_id, $product_id]);
    $existing = $check->fetch();

    if ($existing) {
        // CAS A : Produit déjà dans le panier → on ADDITIONNE les quantités
        $new_qty = $existing['quantite'] + $quantity;
        
        // Double vérification : la quantité totale (ancienne + nouvelle) ne doit pas dépasser le stock
        if ($new_qty > $product['stock']) {
            echo json_encode(['success' => false, 'message' => 'Stock insuffisant pour cette quantité.']);
            exit();
        }
        
        // UPDATE : met à jour la quantité et la date d'ajout (NOW() = date/heure actuelle MySQL)
        $update = $pdo->prepare("UPDATE panier SET quantite = ?, date_ajout = NOW() WHERE id_utilisateur = ? AND id_produit = ?");
        $update->execute([$new_qty, $user_id, $product_id]);
    } else {
        // CAS B : Produit pas encore dans le panier → INSERT une nouvelle ligne
        $insert = $pdo->prepare("INSERT INTO panier (id_utilisateur, id_produit, quantite, date_ajout) VALUES (?, ?, ?, NOW())");
        $insert->execute([$user_id, $product_id, $quantity]);
    }

    // Tout s'est bien passé → réponse JSON de succès
    // Le JavaScript qui appelle ce fichier récupère ce JSON et affiche le message
    echo json_encode(['success' => true, 'message' => 'Produit ajouté au panier !']);
    
} catch (PDOException $e) {
    // En cas d'erreur SQL inattendue :
    // error_log() écrit l'erreur dans les logs serveur (invisible pour l'utilisateur)
    // → Jamais afficher le détail de l'erreur SQL à l'utilisateur (sécurité)
    error_log("Erreur ajout panier: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur. Veuillez réessayer.']);
}
exit(); // Bonne pratique : toujours terminer un endpoint AJAX avec exit()
?>