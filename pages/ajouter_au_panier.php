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

// NOUVEAU : format choisi (id_format de la table produit_formats). Facultatif :
// si aucun format n'est envoyé (ex : bouton rapide du catalogue), on prend le moins cher.
$format_id  = isset($_POST['format_id']) ? intval($_POST['format_id']) : 0;

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
    
    // --- ÉTAPE 1 bis : Déterminer le FORMAT (30 / 50 / 100 ml) ---
    if ($format_id > 0) {
        // Le format reçu doit bien appartenir à CE produit (sécurité : on ne fait jamais confiance au client)
        $check_format = $pdo->prepare("SELECT id_format FROM produit_formats WHERE id_format = ? AND id_produit = ?");
        $check_format->execute([$format_id, $product_id]);
    } else {
        // Pas de format précisé → on prend le format le moins cher (celui affiché "Dès X €")
        $check_format = $pdo->prepare("SELECT id_format FROM produit_formats WHERE id_produit = ? ORDER BY prix ASC LIMIT 1");
        $check_format->execute([$product_id]);
    }
    $format_id = (int) $check_format->fetchColumn();

    if ($format_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Format invalide ou indisponible.']);
        exit();
    }

    // --- ÉTAPE 2 : Le stock est COMMUN à tous les formats d'un même parfum ---
    // On compte donc ce qui est déjà dans le panier pour ce produit, tous formats confondus
    $total_stmt = $pdo->prepare("SELECT COALESCE(SUM(quantite), 0) FROM panier WHERE id_utilisateur = ? AND id_produit = ?");
    $total_stmt->execute([$user_id, $product_id]);
    $already_in_cart = (int) $total_stmt->fetchColumn();

    if ($already_in_cart + $quantity > $product['stock']) {
        echo json_encode(['success' => false, 'message' => 'Stock insuffisant pour cette quantité.']);
        exit();
    }

    // --- ÉTAPE 3 : Ce produit, DANS CE FORMAT, est-il déjà dans le panier ? ---
    $check = $pdo->prepare("SELECT quantite FROM panier WHERE id_utilisateur = ? AND id_produit = ? AND id_format = ?");
    $check->execute([$user_id, $product_id, $format_id]);
    $existing = $check->fetch();

    if ($existing) {
        // CAS A : même produit + même format déjà dans le panier → on ADDITIONNE les quantités
        $new_qty = $existing['quantite'] + $quantity;

        $update = $pdo->prepare("UPDATE panier SET quantite = ?, date_ajout = NOW() WHERE id_utilisateur = ? AND id_produit = ? AND id_format = ?");
        $update->execute([$new_qty, $user_id, $product_id, $format_id]);
    } else {
        // CAS B : nouvelle ligne (un autre format du même parfum donne aussi une nouvelle ligne)
        $insert = $pdo->prepare("INSERT INTO panier (id_utilisateur, id_produit, id_format, quantite, date_ajout) VALUES (?, ?, ?, ?, NOW())");
        $insert->execute([$user_id, $product_id, $format_id, $quantity]);
    }

    // Tout s'est bien passé → réponse JSON de succès
    // Le JavaScript qui appelle ce fichier récupère ce JSON et affiche le message
    // Compter le total du panier pour mettre à jour le compteur navbar
    $count_stmt = $pdo->prepare("SELECT SUM(quantite) FROM panier WHERE id_utilisateur = ?");
    $count_stmt->execute([$user_id]);
    $cart_count = (int)($count_stmt->fetchColumn() ?? 0);

    echo json_encode(['success' => true, 'message' => 'Produit ajouté au panier !', 'cart_count' => $cart_count]);
    
} catch (PDOException $e) {
    // En cas d'erreur SQL inattendue :
    // error_log() écrit l'erreur dans les logs serveur (invisible pour l'utilisateur)
    // → Jamais afficher le détail de l'erreur SQL à l'utilisateur (sécurité)
    error_log("Erreur ajout panier: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur. Veuillez réessayer.']);
}
exit(); // Bonne pratique : toujours terminer un endpoint AJAX avec exit()
?>