<?php
// ============================================================
// FICHIER : catalogue.php
// RÔLE    : Page principale du catalogue de parfums
//           → Affiche les produits filtrés depuis la BDD
//           → Gère l'ajout au panier
//           → Gère la wishlist (favoris) via AJAX
// ============================================================

// --- INCLUSION DES FICHIERS PARTAGÉS ---
// Ces fichiers contiennent du code réutilisable sur tout le site
require_once '../includes/config.php';    // Configuration générale (constantes, paramètres)
require_once '../includes/db.php';        // Connexion à la base de données (variable $pdo)
require_once '../includes/functions.php'; // Fonctions utilitaires : isLoggedIn(), formatPrice(), etc.

// ============================================================
// SECTION 1 : GESTION DE L'AJOUT AU PANIER
// ============================================================
// Cette section est déclenchée UNIQUEMENT quand un formulaire est soumis en POST
// avec le champ action = 'add' (bouton "Ajouter au panier")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    
    // Vérification : l'utilisateur doit être connecté pour ajouter au panier
    if (!isLoggedIn()) {
        // On sauvegarde un message en session, puis on redirige vers la connexion
        $_SESSION['login_redirect'] = 'Veuillez vous connecter pour ajouter un article au panier.';
        header('Location: connexion.php');
        exit(); // IMPORTANT : toujours appeler exit() après un header('Location:...')
    }
    
    // Récupération des données POST avec sécurisation
    $user_id    = $_SESSION['user_id'];             // ID de l'utilisateur connecté (depuis la session)
    $product_id = intval($_POST['product_id'] ?? 0); // intval() convertit en entier → sécurité anti-injection
    $quantity   = intval($_POST['quantity'] ?? 1);   // Quantité (1 par défaut si non précisé)
    
    // Validation basique : les IDs et quantités doivent être positifs
    if ($product_id > 0 && $quantity > 0) {
        try {
            // ÉTAPE 1 : Vérifier que le produit existe en BDD et regarder son stock
            // On utilise une requête préparée (?) pour éviter les injections SQL
            $checkProduct = "SELECT stock FROM produits WHERE id_produit = ?";
            $stmtProduct  = $pdo->prepare($checkProduct);
            $stmtProduct->execute([$product_id]);
            $product = $stmtProduct->fetch(); // fetch() retourne UNE seule ligne
            
            if (!$product) {
                // Le produit n'existe pas dans la BDD
                $_SESSION['cart_message'] = 'Produit non trouvé.';
            } elseif ($product['stock'] < $quantity) {
                // Le stock disponible est insuffisant pour la quantité demandée
                $_SESSION['cart_message'] = 'Stock insuffisant.';
            } else {
                // ÉTAPE 2 : Choisir le FORMAT à ajouter : le moins cher (celui affiché "Dès X €")
                $formatStmt = $pdo->prepare("SELECT id_format FROM produit_formats WHERE id_produit = ? ORDER BY prix ASC LIMIT 1");
                $formatStmt->execute([$product_id]);
                $format_id = (int) $formatStmt->fetchColumn();

                // Vérifier si ce produit, dans ce format, est déjà dans le panier de cet utilisateur
                $checkQuery = "SELECT * FROM panier WHERE id_utilisateur = ? AND id_produit = ? AND id_format = ?";
                $checkStmt  = $pdo->prepare($checkQuery);
                $checkStmt->execute([$user_id, $product_id, $format_id]);
                $existingItem = $checkStmt->fetch();
                
                if ($existingItem) {
                    // CAS A : Le produit est déjà dans le panier → on ADDITIONNE les quantités
                    $newQuantity  = $existingItem['quantite'] + $quantity;
                    $updateQuery  = "UPDATE panier SET quantite = ? WHERE id_panier = ?";
                    $updateStmt   = $pdo->prepare($updateQuery);
                    $updateStmt->execute([$newQuantity, $existingItem['id_panier']]);
                } else {
                    // CAS B : Nouveau produit dans le panier → on INSERT une nouvelle ligne
                    $insertQuery = "INSERT INTO panier (id_utilisateur, id_produit, id_format, quantite) VALUES (?, ?, ?, ?)";
                    $insertStmt  = $pdo->prepare($insertQuery);
                    $insertStmt->execute([$user_id, $product_id, $format_id, $quantity]);
                }
                
                $_SESSION['cart_message'] = 'Le produit a été ajouté à votre panier.';
            }
            
        } catch (PDOException $e) {
            // En cas d'erreur SQL (connexion perdue, etc.), on attrape l'exception
            $_SESSION['cart_message'] = 'Erreur lors de l\'ajout au panier : ' . $e->getMessage();
        }
    } else {
        // Les données reçues ne sont pas valides (IDs négatifs ou nuls)
        $_SESSION['cart_message'] = 'Données invalides.';
    }
    
    // Redirection POST → GET (patron PRG : Post/Redirect/Get)
    // Cela évite que l'utilisateur resoumette le formulaire en rafraîchissant la page
    $referer = $_SERVER['HTTP_REFERER'] ?? 'catalogue.php'; // Retour à la page précédente
    header("Location: $referer");
    exit();
}

// ============================================================
// SECTION 2 : RÉCUPÉRATION ET SÉCURISATION DES PARAMÈTRES URL
// ============================================================
// Ces variables viennent de l'URL : ex: catalogue.php?page=2&category=Femme&sort=price_asc
// L'opérateur ?? signifie "si non défini, utilise cette valeur par défaut"
// trim() supprime les espaces en début/fin de chaîne
// (int) et (float) convertissent en nombre pour éviter les injections
$page      = max(1, (int)($_GET['page']      ?? 1));   // Numéro de page (minimum 1)
$category  = trim($_GET['category']  ?? '');            // Filtre genre : Femme / Homme / Unisexe
$filter    = trim($_GET['filter']    ?? '');            // Filtre spécial : new / bestseller / promo
$search    = trim($_GET['search']    ?? '');            // Terme de recherche texte
$sort      = trim($_GET['sort']      ?? 'newest');      // Ordre de tri (par défaut : plus récents)
$famille   = trim($_GET['famille']   ?? '');            // Famille olfactive : Florale, Boisée, etc.
$marque    = trim($_GET['marque']    ?? '');            // Filtre par marque
$max_price = (float)($_GET['max_price'] ?? 0);         // Prix maximum
$min_price = (float)($_GET['min_price'] ?? 0);         // Prix minimum

// --- PARAMÈTRES DE PAGINATION ---
$limit  = 12;                    // Nombre de produits affichés par page
$offset = ($page - 1) * $limit; // Position de départ en BDD : page 1 → 0, page 2 → 12, page 3 → 24...
// $total_pages sera calculé après la requête COUNT

// ============================================================
// SECTION 3 : CONSTRUCTION DYNAMIQUE DE LA REQUÊTE SQL
// ============================================================
// Technique : on construit la clause WHERE dynamiquement selon les filtres actifs
// $where  = tableau de conditions SQL (ex: "p.genre = :category")
// $params = tableau de valeurs associées aux placeholders (ex: ':category' => 'Femme')
$where  = [];
$params = [];

// --- SÉCURITÉ : Listes blanches (whitelist) ---
// On vérifie que les valeurs reçues font bien partie des valeurs autorisées
// Cela empêche d'injecter des valeurs malveillantes dans la requête SQL
$valid_categories = ['Femme', 'Homme', 'Unisexe'];
$valid_filters    = ['new', 'bestseller', 'promo'];

// Filtre par genre (Femme / Homme / Unisexe)
if ($category && in_array($category, $valid_categories)) {
    $where[]             = "p.genre = :category";
    $params[':category'] = $category;
}

// Filtre spécial : nouveautés, bestsellers ou promotions
if ($filter && in_array($filter, $valid_filters)) {
    if ($filter === 'new') {
        $where[] = "p.nouveaute = 1";           // Produits marqués comme nouveautés en BDD
    } elseif ($filter === 'bestseller') {
        $where[] = "p.bestseller = 1";           // Produits marqués comme best-sellers
    } elseif ($filter === 'promo') {
        $where[] = "p.promotion_pourcentage > 0"; // Produits ayant une réduction (> 0%)
    }
}

// DEBUG - afficher la requête SQL finale

// Filtre par recherche textuelle (nom, description, marque)
// LIKE '%mot%' cherche le mot n'importe où dans le champ
if ($search) {
    $where[]        = "(p.nom LIKE :search OR p.description LIKE :search OR p.description_courte LIKE :search OR p.marque LIKE :search)";
    $params[':search'] = '%' . $search . '%'; // Les % sont les jokers SQL
}

// Filtre par famille olfactive (whitelist également)
if ($famille) {
    $valid_familles = ['Florale', 'Boisée', 'Orientale', 'Hespéridée', 'Fougère', 'Chyprée', 'Aromatique', 'Gourmande', 'Fruitée', 'Aquatique', 'Cuir'];
    if (in_array($famille, $valid_familles)) {
        $where[]          = "p.famille_olfactive = :famille";
        $params[':famille'] = $famille;
    }
}

// Filtre par marque (valeur directe depuis la BDD donc moins risquée, mais toujours paramétrée)
if ($marque) {
    // Vérifier que la marque existe dans la base
    $where[]         = "p.marque = :marque";
    $params[':marque'] = $marque;
}

// Filtre par prix maximum
if ($max_price > 1) {
    $where[]           = "COALESCE(f.prix_min, p.prix) <= :max_price";
    $params[':max_price'] = $max_price;
}

// Filtre par prix minimum
if ($min_price > 0) {
    $where[]           = "COALESCE(f.prix_min, p.prix) >= :min_price";
    $params[':min_price'] = $min_price;
}

// Assemblage final de la clause WHERE
// implode(' AND ', $where) joint toutes les conditions avec " AND "
// Exemple : "WHERE p.genre = :category AND p.nouveaute = 1"
// On affiche les produits en rupture de stock aussi (avec un badge "Rupture de stock")
$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// ============================================================
// SECTION 4 : EXÉCUTION DES REQUÊTES SQL
// ============================================================

// --- REQUÊTE 1 : Compter le nombre total de produits correspondants ---
// Indispensable pour calculer le nombre de pages (pagination)
// --- JOINTURE SUR LES FORMATS (30 / 50 / 100 ml) ---
// Pour chaque produit : prix_min = prix du format le moins cher (affiché "Dès X €")
//                       formats  = liste des contenances (ex : "30 / 50 / 100")
$formatsJoin = "LEFT JOIN (
        SELECT id_produit,
               MIN(prix) AS prix_min,
               GROUP_CONCAT(volume_ml ORDER BY volume_ml SEPARATOR ' / ') AS formats,
               COUNT(*) AS nb_formats
        FROM produit_formats
        GROUP BY id_produit
    ) f ON f.id_produit = p.id_produit";

$countQuery    = "SELECT COUNT(*) FROM produits p $formatsJoin $whereClause";
$countStmt     = $pdo->prepare($countQuery);
$countStmt->execute($params);
$total_products = (int) $countStmt->fetchColumn(); // fetchColumn() retourne la valeur de la 1ère colonne
$total_pages    = ceil($total_products / $limit);  // ceil() arrondit au supérieur (ex: 25/12 = 2.08 → 3 pages)

// --- TRI SÉCURISÉ ---
// match() est une syntaxe PHP 8 moderne (équivalent d'un switch/case)
// La valeur de $sort détermine l'ORDER BY de la requête
// Si $sort ne correspond à rien → valeur par défaut : plus récents en premier
$orderBy = match($sort) {
    'price_asc'  => 'COALESCE(f.prix_min, p.prix) ASC',                              // Prix du moins cher au plus cher
    'price_desc' => 'COALESCE(f.prix_min, p.prix) DESC',                             // Prix du plus cher au moins cher
    'name_asc'   => 'p.nom ASC',                               // Alphabétique A → Z
    'name_desc'  => 'p.nom DESC',                              // Alphabétique Z → A
    'popular'    => '(p.bestseller + p.nouveaute) DESC, COALESCE(f.prix_min, p.prix) ASC', // Best-sellers et nouveautés d'abord
    default      => 'p.date_ajout DESC',                       // Défaut : les plus récents
};

// --- REQUÊTE 2 : Récupérer les produits de la page courante ---
// LEFT JOIN : joint la table produits (p) avec categories (c)
//   → récupère aussi le nom de la catégorie (nom_categorie)
//   → LEFT JOIN = garde le produit même s'il n'a pas de catégorie associée
// LIMIT + OFFSET : pagination SQL
//   → LIMIT 12 : prend maximum 12 produits
//   → OFFSET 24 : saute les 24 premiers (pour la page 3)
$query = "
    SELECT p.*, c.nom_categorie, f.prix_min, f.formats, f.nb_formats 
    FROM produits p 
    LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
    $formatsJoin 
    $whereClause 
    ORDER BY $orderBy
    LIMIT :limit OFFSET :offset
";















// --- EXÉCUTION DE LA REQUÊTE PRINCIPALE ---
// On utilise bindValue() au lieu de execute($params) car LIMIT et OFFSET
// doivent être liés explicitement en tant qu'entiers (PDO::PARAM_INT)
// sinon PDO les traite comme des chaînes et la requête échoue
$stmt = $pdo->prepare($query);
foreach ($params as $key => $val) {
    // Pour chaque paramètre filtre : lie en INT si c'est un entier, sinon STRING
    $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT); // Nombre de produits par page
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT); // Point de départ dans la BDD
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC); // fetchAll() récupère TOUS les résultats en tableau associatif

// --- RÉCUPÉRATION DES MARQUES DISPONIBLES (pour le filtre sidebar) ---
// DISTINCT = évite les doublons (une marque n'apparaît qu'une fois)
// ORDER BY marque = tri alphabétique pour l'affichage
$marquesQuery  = "SELECT DISTINCT marque FROM produits WHERE marque IS NOT NULL AND marque != '' ORDER BY marque";
$marquesStmt   = $pdo->query($marquesQuery); // query() pour les requêtes sans paramètres
$all_marques   = $marquesStmt->fetchAll(PDO::FETCH_COLUMN); // Récupère uniquement la 1ère colonne (marque)

// --- TITRE DE LA PAGE DYNAMIQUE ---
// Le titre change selon les filtres actifs pour le SEO et l'UX
$pageTitle = "Notre Collection de Parfums";
if ($category) $pageTitle .= " – " . htmlspecialchars($category);         // ex: "... – Femme"
if ($filter === 'new') $pageTitle .= " – Nouveautés";
if ($filter === 'bestseller') $pageTitle .= " – Best-sellers";
if ($filter === 'promo') $pageTitle .= " – Promotions";
if ($search) $pageTitle .= " – Recherche : " . htmlspecialchars($search); // ex: "... – Recherche : Chanel"

// Inclusion du header HTML commun à toutes les pages (navbar, balises <head>, etc.)
include '../includes/header.php';
?>

<div class="container" style="max-width: 1400px; margin: 0 auto; padding: 30px 20px;">
    <!-- En-tête -->
    <div class="catalogue-header" style="text-align: center; margin-bottom: 50px;">
        <h1 class="page-title" style="font-size: 2.8em; margin-bottom: 15px; font-weight: 300;">
            Collection de Parfums d'Exception
        </h1>
        <p class="page-subtitle" style="font-size: 1.2em; max-width: 800px; margin: 0 auto 30px;">
            Découvrez des fragrances uniques, soigneusement sélectionnées pour éveiller vos sens
        </p>
    </div>

    <!-- Message de confirmation panier -->
    <!-- Affiché uniquement si un message a été stocké en session lors d'un ajout au panier -->
    <!-- unset() supprime le message après affichage pour qu'il n'apparaisse qu'une fois -->
    <?php if (isset($_SESSION['cart_message'])): ?>
        <div class="alert success" style="
            padding: 15px 20px;
            margin-bottom: 25px;
            background: #f0f9f0;
            border: 1px solid #c6e9c6;
            border-radius: 8px;
            color: #2d662d;
            text-align: center;
            font-size: 1.1em;
        ">
            <i class="fas fa-check-circle" style="margin-right: 10px;"></i>
            <?= htmlspecialchars($_SESSION['cart_message']) /* htmlspecialchars() protège contre les attaques XSS */ ?>
        </div>
        <?php unset($_SESSION['cart_message']); // Supprime le message après affichage ?>
    <?php endif; ?>

    <!-- ===== BANDEAU STATISTIQUES ===== -->
    <!-- Affiche des informations rassurantes pour l'utilisateur (nb produits, livraison, etc.) -->
    <!-- number_format($total_products, 0, ',', ' ') : formate le nombre sans décimale, avec espace pour les milliers -->
    <div class="catalogue-stats" style="
        display: flex;
        justify-content: center;
        gap: 40px;
        margin-bottom: 50px;
        flex-wrap: wrap;
        background: #f8f5f0;
        padding: 25px;
        border-radius: 12px;
    ">
        <div class="stat-item" style="display: flex; align-items: center; gap: 12px; font-size: 1.1em;">
            <i class="fas fa-flask" style="color: #d4a017; font-size: 1.4em;"></i>
            <span><strong><?php echo number_format($total_products, 0, ',', ' '); ?></strong> parfums</span>
        </div>
        <div class="stat-item" style="display: flex; align-items: center; gap: 12px; font-size: 1.1em;">
            <i class="fas fa-truck" style="color: #d4a017; font-size: 1.4em;"></i>
            <span>Livraison gratuite dès 150€</span>
        </div>
        <div class="stat-item" style="display: flex; align-items: center; gap: 12px; font-size: 1.1em;">
            <i class="fas fa-gift" style="color: #d4a017; font-size: 1.4em;"></i>
            <span>Échantillons offerts</span>
        </div>
        <div class="stat-item" style="display: flex; align-items: center; gap: 12px; font-size: 1.1em;">
            <i class="fas fa-shield-alt" style="color: #d4a017; font-size: 1.4em;"></i>
            <span>Paiement sécurisé</span>
        </div>
    </div>

    <!-- ===== LAYOUT PRINCIPAL : SIDEBAR + CONTENU ===== -->
    <!-- CSS Grid avec 2 colonnes : sidebar fixe (280px) + zone produits flexible (1fr) -->
    <div class="catalogue-layout" style="display: grid; grid-template-columns: 280px 1fr; gap: 40px;">
        
        <!-- ===== SIDEBAR : PANNEAU DE FILTRES ===== -->
        <!-- position: sticky; top: 20px; → la sidebar reste visible quand on scrolle -->
        <aside class="filters-sidebar" style="
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            height: fit-content;
            position: sticky;
            top: 20px;
        ">
            <div class="filters-header" style="margin-bottom: 25px;">
                <h3 style="margin: 0; font-size: 1.4em; color: #3a2c1f;">
                    <i class="fas fa-sliders-h" style="margin-right: 10px; color: #d4a017;"></i> 
                    Filtres
                </h3>
                <!-- Bouton "Effacer tous les filtres" : affiché seulement si au moins un filtre est actif -->
                <?php if ($category || $filter || $search || $famille || $marque || $max_price > 1): ?>
                    <a href="catalogue.php" style="
                        display: inline-block;
                        margin-top: 10px;
                        color: #d4a017;
                        font-weight: 500;
                        text-decoration: none;
                        font-size: 0.95em;
                    ">
                        <i class="fas fa-times-circle" style="margin-right: 5px;"></i>
                        Effacer tous les filtres
                    </a>
                <?php endif; ?>
            </div>

            <!-- ===== FILTRE : RECHERCHE RAPIDE ===== -->
            <!-- Formulaire GET : le terme de recherche est envoyé dans l'URL → ?search=chanel -->
            <!-- value= reprend la valeur actuelle pour ne pas vider le champ après soumission -->
            <div class="filter-section" style="margin-bottom: 25px;">
                <h4 style="font-size: 1.1em; margin-bottom: 12px; color: #555;">Rechercher</h4>
                <form method="GET" style="display: flex;">
                    <input type="text" name="search" placeholder="Nom, marque..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           style="
                               flex: 1;
                               padding: 10px 15px;
                               border: 1px solid #ddd;
                               border-radius: 6px 0 0 6px;
                               font-size: 0.95em;
                           ">
                    <button type="submit" style="
                        background: #d4a017;
                        color: white;
                        border: none;
                        padding: 10px 15px;
                        border-radius: 0 6px 6px 0;
                        cursor: pointer;
                    ">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>

            <!-- ===== FILTRE : GENRE ===== -->
            <!-- Boucle sur les 3 genres. Le lien actif est mis en surbrillance (fond + bordure dorée) -->
            <!-- urlencode() encode les caractères spéciaux pour les URLs (ex: espaces → %20) -->
            <!-- La classe 'active' et les styles inline changent selon si ce genre est sélectionné ou non -->
            <div class="filter-section" style="margin-bottom: 25px;">
                <h4 style="font-size: 1.1em; margin-bottom: 12px; color: #555;">Genre</h4>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <a href="catalogue.php" class="filter-link <?php echo !$category ? 'active' : ''; ?>" 
                       style="
                           display: flex;
                           align-items: center;
                           padding: 10px 15px;
                           color: #444;
                           text-decoration: none;
                           border-radius: 6px;
                           background: <?php echo !$category ? '#f8f5f0' : 'transparent'; ?>;
                           border: 1px solid <?php echo !$category ? '#d4a017' : '#eee'; ?>;
                       ">
                        <i class="fas fa-all" style="margin-right: 10px; color: #d4a017;"></i>
                        Tous les parfums
                    </a>
                    <?php foreach (['Femme', 'Homme', 'Unisexe'] as $cat): ?>
                        <a href="?category=<?php echo urlencode($cat); ?>&sort=<?php echo urlencode($sort); ?>&page=1" 
                           class="filter-link <?php echo $category === $cat ? 'active' : ''; ?>"
                           style="
                               display: flex;
                               align-items: center;
                               padding: 10px 15px;
                               color: #444;
                               text-decoration: none;
                               border-radius: 6px;
                               background: <?php echo $category === $cat ? '#f8f5f0' : 'transparent'; ?>;
                               border: 1px solid <?php echo $category === $cat ? '#d4a017' : '#eee'; ?>;
                           ">
                            <!-- Icône différente selon le genre : venus (♀), mars (♂), venus-mars (⚥) -->
                            <i class="fas <?php echo $cat === 'Femme' ? 'fa-venus' : ($cat === 'Homme' ? 'fa-mars' : 'fa-venus-mars'); ?>" 
                               style="margin-right: 10px; color: #d4a017;"></i>
                            <?php echo htmlspecialchars($cat); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ===== FILTRE : SÉLECTIONS SPÉCIALES ===== -->
            <div class="filter-section" style="margin-bottom: 25px;">
                <h4 style="font-size: 1.1em; margin-bottom: 12px; color: #555;">Sélections</h4>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <a href="?filter=new&sort=<?php echo urlencode($sort); ?>&page=1" 
                       class="<?php echo $filter === 'new' ? 'active' : ''; ?>"
                       style="
                           display: flex;
                           align-items: center;
                           padding: 10px 15px;
                           color: #444;
                           text-decoration: none;
                           border-radius: 6px;
                           background: <?php echo $filter === 'new' ? '#f8f5f0' : 'transparent'; ?>;
                           border: 1px solid <?php echo $filter === 'new' ? '#d4a017' : '#eee'; ?>;
                       ">
                        <i class="fas fa-star" style="margin-right: 10px; color: #d4a017;"></i>
                        Nouveautés
                    </a>
                    <a href="?filter=bestseller&sort=<?php echo urlencode($sort); ?>&page=1" 
                       class="<?php echo $filter === 'bestseller' ? 'active' : ''; ?>"
                       style="
                           display: flex;
                           align-items: center;
                           padding: 10px 15px;
                           color: #444;
                           text-decoration: none;
                           border-radius: 6px;
                           background: <?php echo $filter === 'bestseller' ? '#f8f5f0' : 'transparent'; ?>;
                           border: 1px solid <?php echo $filter === 'bestseller' ? '#d4a017' : '#eee'; ?>;
                       ">
                        <i class="fas fa-fire" style="margin-right: 10px; color: #d4a017;"></i>
                        Best-sellers
                    </a>
                    <a href="?filter=promo&sort=<?php echo urlencode($sort); ?>&page=1" 
                       class="<?php echo $filter === 'promo' ? 'active' : ''; ?>"
                       style="
                           display: flex;
                           align-items: center;
                           padding: 10px 15px;
                           color: #444;
                           text-decoration: none;
                           border-radius: 6px;
                           background: <?php echo $filter === 'promo' ? '#f8f5f0' : 'transparent'; ?>;
                           border: 1px solid <?php echo $filter === 'promo' ? '#d4a017' : '#eee'; ?>;
                       ">
                        <i class="fas fa-tag" style="margin-right: 10px; color: #d4a017;"></i>
                        Promotions
                    </a>
                </div>
            </div>

            <!-- ===== FILTRE : FAMILLE OLFACTIVE ===== -->
            <!-- Boucle PHP qui génère un lien pour chaque famille olfactive -->
            <!-- Les styles changent dynamiquement avec des ternaires PHP : condition ? valeurSiVrai : valeurSiFaux -->
            <div class="filter-section" style="margin-bottom: 25px;">
                <h4 style="font-size: 1.1em; margin-bottom: 12px; color: #555;">Famille olfactive</h4>
                <div style="display: flex; flex-direction: column; gap: 8px; max-height: 200px; overflow-y: auto; padding-right: 5px;">
                    <?php
                    $familles = ['Florale', 'Boisée', 'Orientale', 'Hespéridée', 'Fougère', 'Chyprée', 'Aromatique', 'Gourmande', 'Fruitée', 'Aquatique', 'Cuir'];
                    foreach ($familles as $f) {
                        $active = ($famille === $f); // true si cette famille est la famille sélectionnée
                        echo "<a href=\"?famille=" . urlencode($f) . "&page=1\" 
                              style=\"
                                  display: flex;
                                  align-items: center;
                                  padding: 8px 12px;
                                  color: " . ($active ? '#d4a017' : '#444') . ";
                                  text-decoration: none;
                                  border-radius: 6px;
                                  background: " . ($active ? '#f8f5f0' : 'transparent') . ";
                                  border: 1px solid " . ($active ? '#d4a017' : '#eee') . ";
                                  font-weight: " . ($active ? '600' : '400') . ";
                              \">
                                <i class=\"fas fa-leaf\" style=\"margin-right: 8px; color: #d4a017;\"></i>
                                $f
                              </a>";
                    }
                    ?>
                </div>
            </div>

            <!-- ===== FILTRE : MARQUES ===== -->
            <!-- $all_marques vient de la requête SQL DISTINCT faite plus haut -->
            <!-- Si aucune marque en BDD, affiche un message -->
            <div class="filter-section" style="margin-bottom: 25px;">
                <h4 style="font-size: 1.1em; margin-bottom: 12px; color: #555;">Marques</h4>
                <div style="display: flex; flex-direction: column; gap: 8px; max-height: 200px; overflow-y: auto; padding-right: 5px;">
                    <?php if (!empty($all_marques)): ?>
                        <?php foreach ($all_marques as $m): ?>
                            <?php $active = ($marque === $m); ?>
                            <a href="?marque=<?php echo urlencode($m); ?>&page=1" 
                               style="
                                   display: flex;
                                   align-items: center;
                                   padding: 8px 12px;
                                   color: <?php echo $active ? '#d4a017' : '#444'; ?>;
                                   text-decoration: none;
                                   border-radius: 6px;
                                   background: <?php echo $active ? '#f8f5f0' : 'transparent'; ?>;
                                   border: 1px solid <?php echo $active ? '#d4a017' : '#eee'; ?>;
                                   font-weight: <?php echo $active ? '600' : '400'; ?>;
                               ">
                                <i class="fas fa-copyright" style="margin-right: 8px; color: #d4a017;"></i>
                                <?php echo htmlspecialchars($m); ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span style="color: #777; font-size: 0.9em;">Aucune marque disponible</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== FILTRE : FOURCHETTE DE PRIX ===== -->
            <!-- Slider HTML5 (input type="range") de 0 à 500€, pas de 10€ -->
            <!-- La valeur initiale reprend $max_price si un filtre est actif, sinon 500 (max) -->
            <!-- Le JS (plus bas) met à jour #priceValueDisplay en temps réel pendant le glissement -->
            <div class="filter-section">
                <h4 style="font-size: 1.1em; margin-bottom: 12px; color: #555;">Fourchette de prix</h4>
                <div class="price-range" style="padding: 10px 0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.9em; color: #666;">
                        <span>0 €</span>
                        <span id="priceValueDisplay"><?php echo $max_price ?: '500'; ?> €</span>
                    </div>
                    <input type="range" min="0" max="500" step="10" 
                           value="<?php echo $max_price ?: 500; ?>" 
                           class="price-slider" id="priceSlider" 
                           style="width: 100%; accent-color: #d4a017;">
                    <button id="applyPrice" style="
                        background: #d4a017;
                        color: white;
                        border: none;
                        padding: 10px;
                        border-radius: 6px;
                        cursor: pointer;
                        font-weight: 500;
                        margin-top: 15px;
                        width: 100%;
                        transition: background 0.3s;
                        font-size: 0.95em;
                    ">
                        Appliquer le filtre prix
                    </button>
                </div>
            </div>
        </aside>

        <!-- ===== ZONE PRINCIPALE : GRILLE DE PRODUITS ===== -->
        <main class="products-main">
            <!-- ===== BARRE DE CONTRÔLE : nb résultats + tri ===== -->
            <!-- Affiche le nombre de produits trouvés et le menu de tri -->
            <div style="
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
                flex-wrap: wrap;
                gap: 15px;
                background: white;
                padding: 20px;
                border-radius: 12px;
                box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            ">
                <div style="font-size: 1.1em; color: #3a2c1f;">
                    <strong><?php echo number_format($total_products, 0, ',', ' '); ?></strong> 
                    parfum<?php echo $total_products > 1 ? 's' : ''; ?> trouvé<?php echo $total_products > 1 ? 's' : ''; ?>
                    <?php if ($search): ?>
                        pour « <strong><?php echo htmlspecialchars($search); ?></strong> »
                    <?php endif; ?>
                </div>

                <!-- Menu déroulant de tri -->
                <!-- onchange="if(this.value) window.location.href=this.value;" : redirige immédiatement vers l'URL de l'option choisie -->
                <!-- L'URL de chaque option conserve tous les filtres actifs et change uniquement le paramètre sort -->
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="sortSelect" style="font-weight: 500; color: #555; font-size: 0.95em;">
                        Trier par :
                    </label>
                    <select id="sortSelect" onchange="if(this.value) window.location.href=this.value;" 
                            style="
                                padding: 10px 15px;
                                border: 1px solid #ddd;
                                border-radius: 8px;
                                background: white;
                                font-size: 0.95em;
                                min-width: 180px;
                                cursor: pointer;
                            ">
                        <?php
                        // On reconstruit l'URL de base en gardant tous les filtres existants
                        // mais en enlevant 'sort' et 'page' pour les remplacer dans chaque option
                        $baseParams = $_GET;
                        unset($baseParams['sort']);
                        unset($baseParams['page']);
                        $baseUrl = '?' . http_build_query($baseParams) . ($baseParams ? '&' : '');
                        
                        // Tableau des options de tri : clé = valeur PHP, valeur = texte affiché
                        $options = [
                            'newest'     => 'Nouveautés',
                            'popular'    => 'Populaire',
                            'price_asc'  => 'Prix croissant',
                            'price_desc' => 'Prix décroissant',
                            'name_asc'   => 'Nom A–Z',
                            'name_desc'  => 'Nom Z–A',
                        ];
                        
                        // Génère les <option> avec selected sur le tri actif
                        foreach ($options as $val => $label) {
                            $selected = ($sort === $val) ? 'selected' : '';
                            $url      = "{$baseUrl}sort=$val&page=1";
                            echo "<option value=\"{$url}\" $selected>$label</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <?php if ($total_products > 0): ?>
                <!-- ===== GRILLE DE PRODUITS ===== -->
                <!-- CSS Grid responsive : auto-fill crée autant de colonnes que possible avec min 320px par colonne -->
                <div style="
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
                    gap: 30px;
                    margin-top: 20px;
                ">
                    <?php foreach ($products as $product): 
                        // --- Calcul du prix avec réduction ---
                        // Prix affiché = format le moins cher (table produit_formats), sinon prix du produit
                        $price       = (float)($product['prix_min'] ?? $product['prix']);
                        $has_formats = (int)($product['nb_formats'] ?? 0) > 1; // plusieurs formats → "Dès X €"
                        $discount    = (float)$product['promotion_pourcentage']; // % de réduction
                        // Si réduction > 0% : prix final = prix - réduction, sinon = prix normal
                        $final_price = $discount > 0 ? $price * (1 - $discount / 100) : $price;
                        
                        // --- Gestion de l'image ---
                        // Si pas d'image en BDD, on utilise une image Unsplash par défaut
                        $image = $product['image_url'] ? htmlspecialchars($product['image_url']) : 
                                'https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=800&q=80';
                        
                        // --- Classe CSS du stock ---
                        // > 10 : in-stock (vert), entre 1 et 10 : low-stock (orange), 0 : out-stock (rouge)
                        $stock_class = $product['stock'] > 10 ? 'in-stock' : ($product['stock'] > 0 ? 'low-stock' : 'out-stock');
                    ?>
                        <!-- Carte produit : onclick redirige vers la page détail du produit -->
                        <!-- opacity: 0.75 si stock = 0 pour indiquer visuellement l'indisponibilité -->
                        <div class="product-card" 
                             onclick="window.location='produit.php?id=<?php echo $product['id_produit']; ?>'"
                             style="
                            background: white;
                            border-radius: 16px;
                            overflow: hidden;
                            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
                            transition: all 0.35s ease;
                            position: relative;
                            cursor: pointer;
                            border: 2px solid transparent;
                            <?php echo $product['stock'] == 0 ? 'opacity: 0.75;' : ''; ?>
                        ">
                            <!-- ===== BADGES PRODUIT ===== -->
                            <!-- Badges positionnés en absolu en haut à gauche de la carte -->
                            <!-- Affichés conditionnellement selon les propriétés du produit en BDD -->
                            <div class="product-badges" style="position: absolute; top: 15px; left: 15px; z-index: 10;">
                                <?php if ($discount > 0): ?>
                                    <!-- Badge réduction : affiche le pourcentage (ex: -20%) -->
                                    <span class="badge discount" style="
                                        background: linear-gradient(135deg, #e63946, #ff6b6b);
                                        color: white;
                                        padding: 6px 12px;
                                        border-radius: 20px;
                                        font-weight: 600;
                                        font-size: 0.9em;
                                        margin-bottom: 8px;
                                        display: block;
                                        box-shadow: 0 3px 8px rgba(230, 57, 70, 0.3);
                                    ">
                                        -<?php echo number_format($discount, 0); ?>%
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($product['nouveaute']): ?>
                                    <!-- Badge "Nouveau" : affiché si nouveaute = 1 en BDD -->
                                    <span class="badge new" style="
                                        background: linear-gradient(135deg, #2a9d8f, #4cc9b0);
                                        color: white;
                                        padding: 6px 12px;
                                        border-radius: 20px;
                                        font-weight: 600;
                                        font-size: 0.9em;
                                        margin-bottom: <?php echo $discount > 0 ? '8px' : '0'; ?>;
                                        display: block;
                                        box-shadow: 0 3px 8px rgba(42, 157, 143, 0.3);
                                    ">
                                        Nouveau
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($product['bestseller']): ?>
                                    <!-- Badge "Best-seller" : affiché si bestseller = 1 en BDD -->
                                    <span class="badge bestseller" style="
                                        background: linear-gradient(135deg, #e9c46a, #f4a261);
                                        color: #333;
                                        padding: 6px 12px;
                                        border-radius: 20px;
                                        font-weight: 600;
                                        font-size: 0.9em;
                                        display: block;
                                        box-shadow: 0 3px 8px rgba(233, 196, 106, 0.3);
                                    ">
                                        Best-seller
                                    </span>
                                <?php endif; ?>

                                <?php if ($product['stock'] == 0): ?>
                                    <!-- Badge "Rupture de stock" : affiché si stock = 0 en BDD -->
                                    <span class="badge out-of-stock" style="
                                        background: linear-gradient(135deg, #e74c3c, #c0392b);
                                        color: white;
                                        padding: 6px 12px;
                                        border-radius: 20px;
                                        font-weight: 600;
                                        font-size: 0.9em;
                                        display: block;
                                        margin-top: 6px;
                                        box-shadow: 0 3px 8px rgba(231, 76, 60, 0.3);
                                    ">
                                        Rupture de stock
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- ===== IMAGE DU PRODUIT ===== -->
                            <!-- loading="lazy" : l'image se charge seulement quand elle est visible à l'écran (performance) -->
                            <!-- L'effet zoom (scale(1.05)) au survol est géré par onmouseover/onmouseout inline -->
                            <!-- L'overlay gradient noir en bas crée un effet esthétique -->
                            <div style="height: 280px; overflow: hidden; position: relative;">
                                <img src="<?php echo $image; ?>" 
                                     alt="<?php echo htmlspecialchars($product['nom']); ?> - <?php echo htmlspecialchars($product['marque']); ?>"
                                     loading="lazy"
                                     style="
                                         width: 100%;
                                         height: 100%;
                                         object-fit: cover;
                                         transition: transform 0.5s ease;
                                     "
                                     onmouseover="this.style.transform='scale(1.05)'"
                                     onmouseout="this.style.transform='scale(1)'">
                                <div class="product-overlay" style="
                                    position: absolute;
                                    top: 0;
                                    left: 0;
                                    right: 0;
                                    bottom: 0;
                                    background: linear-gradient(to bottom, transparent 70%, rgba(0,0,0,0.3));
                                "></div>
                            </div>

                            <!-- ===== INFOS PRODUIT ===== -->
                            <!-- Marque, catégorie, nom, volume, genre -->
                            <div style="padding: 20px;">
                                <!-- Infos produit -->
                                <div style="margin-bottom: 15px;">
                                    <div style="font-size: 0.85em; color: #888; margin-bottom: 8px; display: flex; align-items: center;">
                                        <i class="fas fa-tag" style="margin-right: 6px; color: #d4a017;"></i>
                                        <span><?php echo htmlspecialchars($product['marque']); ?></span>
                                        <span style="margin: 0 5px;">•</span>
                                        <i class="fas fa-layer-group" style="margin-right: 6px; color: #d4a017;"></i>
                                        <span><?php echo htmlspecialchars($product['nom_categorie'] ?? 'Parfum'); ?></span>
                                    </div>
                                    
                                    <h3 style="margin: 0 0 10px; font-size: 1.3em; line-height: 1.3; min-height: 3em;">
                                        <a href="produit.php?id=<?php echo $product['id_produit']; ?>" 
                                           style="color: #2c3e50; text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($product['nom']); ?>
                                        </a>
                                    </h3>
                                    
                                    <div style="font-size: 0.9em; color: #666; margin-bottom: 15px; display: flex; align-items: center;">
                                        <i class="fas fa-wine-bottle" style="margin-right: 6px; color: #d4a017;"></i>
                                        <span><?php echo htmlspecialchars($product['formats'] ?? $product['volume_ml']); ?> ml</span>
                                        <span style="margin: 0 8px;">•</span>
                                        <i class="fas fa-venus-mars" style="margin-right: 6px; color: #d4a017;"></i>
                                        <span><?php echo htmlspecialchars($product['genre']); ?></span>
                                    </div>
                                </div>

                                <!-- ===== NOTES OLFACTIVES ===== -->
                                <?php if (!empty($product['famille_olfactive'])): ?>
                                <div style="margin-bottom: 12px;">
                                    <span style="
                                        display: inline-flex;
                                        align-items: center;
                                        gap: 5px;
                                        background: #f8f5f0;
                                        border: 1px solid #e8d8b0;
                                        color: #7a5a00;
                                        font-size: 0.8em;
                                        font-weight: 600;
                                        padding: 4px 10px;
                                        border-radius: 20px;
                                    ">
                                        <i class="fas fa-leaf" style="color: #d4a017; font-size: 0.85em;"></i>
                                        <?php echo htmlspecialchars($product['famille_olfactive']); ?>
                                    </span>
                                    <?php if (!empty($product['notes_tete'])): ?>
                                    <div style="margin-top: 6px; font-size: 0.78em; color: #999; font-style: italic;">
                                        <i class="fas fa-wind" style="margin-right: 4px; color: #d4a017;"></i>
                                        <?php echo htmlspecialchars(mb_substr($product['notes_tete'], 0, 40)) . (mb_strlen($product['notes_tete']) > 40 ? '...' : ''); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <!-- ===== AFFICHAGE DU PRIX ===== -->
                                <!-- Si réduction > 0 : prix original barré + prix réduit en rouge -->
                                <!-- Sinon : prix normal en gris foncé -->
                                <!-- formatPrice() est une fonction de functions.php qui formate le prix (ex: 89,90 €) -->
                                <div style="margin-bottom: 20px;">
                                    <?php if ($has_formats): ?>
                                        <div style="font-size: 0.8em; color: #888; text-transform: uppercase; letter-spacing: 1px;">Dès</div>
                                    <?php endif; ?>
                                    <?php if ($discount > 0): ?>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span style="color: #888; text-decoration: line-through; font-size: 0.95em;">
                                                <?php echo formatPrice($price); ?>
                                            </span>
                                            <span style="
                                                font-size: 1.5em;
                                                font-weight: 700;
                                                color: #e63946;
                                            ">
                                                <?php echo formatPrice($final_price); ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span style="
                                            font-size: 1.5em;
                                            font-weight: 700;
                                            color: #2c3e50;
                                        ">
                                            <?php echo formatPrice($final_price); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($product['stock'] > 0): ?>
                                        <div style="font-size: 0.85em; color: #27ae60; margin-top: 5px;">
                                            <i class="fas fa-check-circle"></i>
                                            <?php echo $product['stock'] > 10 ? 'En stock' : '<strong>Plus que ' . $product['stock'] . ' en stock !</strong>'; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-size: 0.85em; color: #e74c3c; margin-top: 5px;">
                                            <i class="fas fa-times-circle"></i>
                                            Rupture de stock
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- ===== BOUTONS D'ACTION ===== -->
                                <!-- event.stopPropagation() : empêche le clic sur les boutons de déclencher
                                     le onclick de la carte (qui redirige vers produit.php) -->
                                <div style="display: flex; gap: 10px;" onclick="event.stopPropagation()">
                                    
                                    <!-- Formulaire d'ajout au panier -->
                                    <!-- Les 3 inputs hidden envoient silencieusement : action, product_id, quantity -->
                                    <!-- Le bouton est désactivé (disabled) si stock = 0 -->
                                    <form method="POST" action="catalogue.php" style="flex: 1;" onclick="event.stopPropagation()">
                                        <input type="hidden" name="action"     value="add">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id_produit']; ?>">
                                        <input type="hidden" name="quantity"   value="1">
                                        <button type="submit" 
                                                class="add-to-cart-btn"
                                                <?php echo $product['stock'] == 0 ? 'disabled' : ''; ?>
                                                style="
                                                    background: <?php echo $product['stock'] > 0 ? 'linear-gradient(135deg, #d4a017, #c29b15)' : '#95a5a6'; ?>;
                                                    color: white;
                                                    border: none;
                                                    padding: 12px;
                                                    border-radius: 12px;
                                                    cursor: <?php echo $product['stock'] > 0 ? 'pointer' : 'not-allowed'; ?>;
                                                    font-weight: 600;
                                                    font-size: 1em;
                                                    width: 100%;
                                                    transition: all 0.3s;
                                                    display: flex;
                                                    align-items: center;
                                                    justify-content: center;
                                                    gap: 8px;
                                                    box-shadow: <?php echo $product['stock'] > 0 ? '0 4px 12px rgba(212,160,23,0.3)' : 'none'; ?>;
                                                "
                                                onmouseover="if(this.style.cursor!=='not-allowed'){this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(212,160,23,0.45)'}"
                                                onmouseout="if(this.style.cursor!=='not-allowed'){this.style.transform='translateY(0)';this.style.boxShadow='0 4px 12px rgba(212,160,23,0.3)'}">
                                            <i class="fas fa-shopping-cart"></i>
                                            <?php echo $product['stock'] > 0 ? 'Ajouter au panier' : 'Indisponible'; ?>
                                        </button>
                                    </form>

                                    <!-- Bouton Favoris (Wishlist) -->
                                    <!-- data-product-id : attribut personnalisé lu par le JavaScript pour savoir quel produit -->
                                    <!-- L'icône est 'far fa-heart' (cœur vide) par défaut -->
                                    <!-- Le JS la change en 'fas fa-heart' (cœur plein rouge) si déjà en favoris -->
                                    <button class="btn-wishlist"
                                            data-product-id="<?php echo $product['id_produit']; ?>"
                                            title="Ajouter aux favoris"
                                            onclick="event.stopPropagation()"
                                            style="
                                                background: white;
                                                border: 2px solid #e0d5c5;
                                                border-radius: 12px;
                                                padding: 12px 14px;
                                                cursor: pointer;
                                                transition: all 0.3s;
                                                display: flex;
                                                align-items: center;
                                                justify-content: center;
                                                min-width: 48px;
                                                box-shadow: 0 2px 8px rgba(0,0,0,0.06);
                                            "
                                            onmouseover="this.style.borderColor='#ef4444'; this.style.transform='scale(1.1)'"
                                            onmouseout="this.style.borderColor='#e0d5c5'; this.style.transform='scale(1)'">
                                        <i class="far fa-heart" style="font-size: 1.2em; color: #888;"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- ===== PAGE VIDE : AUCUN PRODUIT TROUVÉ ===== -->
                <!-- Affiché si $total_products = 0 (aucun produit ne correspond aux filtres) -->
                <!-- Propose des liens pour réinitialiser les filtres ou voir les nouveautés -->
                <div style="
                    text-align: center;
                    padding: 80px 20px;
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
                ">
                    <div style="font-size: 5em; color: #f1f1f1; margin-bottom: 20px;">
                        <i class="fas fa-flask"></i>
                    </div>
                    <h3 style="font-size: 1.8em; margin-bottom: 15px; color: #2c3e50;">
                        Aucun parfum ne correspond à vos critères
                    </h3>
                    <p style="font-size: 1.1em; color: #7f8c8d; margin-bottom: 30px; max-width: 600px; margin-left: auto; margin-right: auto;">
                        Essayez d'élargir vos filtres ou découvrez toute notre collection exclusive.
                    </p>
                    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                        <a href="catalogue.php" style="
                            background: #d4a017;
                            color: white;
                            padding: 15px 35px;
                            border-radius: 10px;
                            text-decoration: none;
                            font-weight: 600;
                            font-size: 1.1em;
                            transition: background 0.3s;
                            display: inline-flex;
                            align-items: center;
                            gap: 10px;
                        "
                        onmouseover="this.style.background='#c29b15'"
                        onmouseout="this.style.background='#d4a017'">
                            <i class="fas fa-store"></i>
                            Toute la collection
                        </a>
                        <a href="catalogue.php?filter=new" style="
                            background: white;
                            color: #d4a017;
                            padding: 15px 35px;
                            border-radius: 10px;
                            text-decoration: none;
                            font-weight: 600;
                            font-size: 1.1em;
                            border: 2px solid #d4a017;
                            transition: all 0.3s;
                            display: inline-flex;
                            align-items: center;
                            gap: 10px;
                        "
                        onmouseover="this.style.background='#f8f5f0'"
                        onmouseout="this.style.background='white'">
                            <i class="fas fa-star"></i>
                            Voir les nouveautés
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
            <!-- ===== PAGINATION ===== -->
            <div style="
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 8px;
                margin-top: 50px;
                flex-wrap: wrap;
            ">
                <?php
                // Reconstruction de l'URL en conservant tous les filtres actifs
                $paginationParams = $_GET;
                unset($paginationParams['page']);
                $paginationBase = '?' . ($paginationParams ? http_build_query($paginationParams) . '&' : '');
                ?>

                <!-- Bouton Précédent -->
                <?php if ($page > 1): ?>
                <a href="<?php echo $paginationBase; ?>page=<?php echo $page - 1; ?>" style="
                    display: flex; align-items: center; gap: 6px;
                    padding: 10px 18px;
                    background: white;
                    border: 2px solid #e0d5c5;
                    border-radius: 10px;
                    color: #3a2c1f;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 0.95em;
                    transition: all 0.2s;
                " onmouseover="this.style.borderColor='#d4a017';this.style.color='#d4a017'"
                   onmouseout="this.style.borderColor='#e0d5c5';this.style.color='#3a2c1f'">
                    <i class="fas fa-chevron-left"></i> Précédent
                </a>
                <?php endif; ?>

                <!-- Numéros de pages -->
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php
                    // Afficher : page 1, page courante ±2, dernière page
                    // Sinon afficher "..."
                    $showPage = ($i == 1 || $i == $total_pages || abs($i - $page) <= 2);
                    $showDots  = (!$showPage && ($i == 2 || $i == $total_pages - 1));
                    if ($showDots): ?>
                        <span style="padding: 10px 6px; color: #aaa; font-size: 1.1em;">…</span>
                    <?php elseif ($showPage): ?>
                        <a href="<?php echo $paginationBase; ?>page=<?php echo $i; ?>" style="
                            display: flex; align-items: center; justify-content: center;
                            width: 42px; height: 42px;
                            border-radius: 10px;
                            font-weight: <?php echo $i == $page ? '700' : '500'; ?>;
                            font-size: 0.95em;
                            text-decoration: none;
                            transition: all 0.2s;
                            background: <?php echo $i == $page ? 'linear-gradient(135deg, #d4a017, #c29b15)' : 'white'; ?>;
                            color: <?php echo $i == $page ? 'white' : '#3a2c1f'; ?>;
                            border: 2px solid <?php echo $i == $page ? '#d4a017' : '#e0d5c5'; ?>;
                            box-shadow: <?php echo $i == $page ? '0 4px 12px rgba(212,160,23,0.35)' : 'none'; ?>;
                        " <?php if ($i != $page): ?>
                            onmouseover="this.style.borderColor='#d4a017';this.style.color='#d4a017'"
                            onmouseout="this.style.borderColor='#e0d5c5';this.style.color='#3a2c1f'"
                          <?php endif; ?>>
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <!-- Bouton Suivant -->
                <?php if ($page < $total_pages): ?>
                <a href="<?php echo $paginationBase; ?>page=<?php echo $page + 1; ?>" style="
                    display: flex; align-items: center; gap: 6px;
                    padding: 10px 18px;
                    background: white;
                    border: 2px solid #e0d5c5;
                    border-radius: 10px;
                    color: #3a2c1f;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 0.95em;
                    transition: all 0.2s;
                " onmouseover="this.style.borderColor='#d4a017';this.style.color='#d4a017'"
                   onmouseout="this.style.borderColor='#e0d5c5';this.style.color='#3a2c1f'">
                    Suivant <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>

                <!-- Infos résumé -->
                <div style="
                    width: 100%;
                    text-align: center;
                    margin-top: 12px;
                    font-size: 0.88em;
                    color: #aaa;
                ">
                    Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                    · <?php echo $total_products; ?> parfum<?php echo $total_products > 1 ? 's' : ''; ?> au total
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div><script>
// ============================================================
// JAVASCRIPT — Interactions dynamiques de la page catalogue
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    // DOMContentLoaded : attend que tout le HTML soit chargé avant d'exécuter le JS

    // ==========================================
    // SECTION JS 1 : GESTION DU SLIDER DE PRIX
    // ==========================================
    const priceSlider       = document.getElementById('priceSlider');       // L'input range
    const priceValueDisplay = document.getElementById('priceValueDisplay'); // Le span qui affiche la valeur
    const applyPriceBtn     = document.getElementById('applyPrice');        // Le bouton "Appliquer"
    
    if (priceSlider && priceValueDisplay) {
        // Mise à jour de l'affichage en temps réel pendant le glissement
        priceSlider.addEventListener('input', function() {
            priceValueDisplay.textContent = this.value + ' €';
        });
        
        // Quand on clique sur "Appliquer le filtre prix"
        if (applyPriceBtn) {
            applyPriceBtn.addEventListener('click', function() {
                const maxPrice = priceSlider.value;
                if (maxPrice > 0) {
                    // URL API : permet de manipuler l'URL facilement sans concatenation fragile
                    const url = new URL(window.location); // Copie l'URL actuelle
                    url.searchParams.set('max_price', maxPrice); // Ajoute/remplace le paramètre max_price
                    url.searchParams.set('page', '1');           // Repart à la page 1
                    window.location.href = url.toString();       // Redirige avec la nouvelle URL
                }
            });
        }
    }
    
    // ==========================================
    // SECTION JS 2 : ANIMATION DES CARTES PRODUITS
    // ==========================================
    // Effet hover : la carte se soulève (translateY) et une ombre dorée apparaît
    // Préférable aux pseudo-classes CSS :hover car permet des transitions sur box-shadow dynamiques
    const productCards = document.querySelectorAll('.product-card');
    productCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform   = 'translateY(-8px)';
            this.style.boxShadow   = '0 20px 40px rgba(212, 160, 23, 0.18)';
            this.style.borderColor = '#d4a017';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform   = 'translateY(0)';
            this.style.boxShadow   = '0 6px 20px rgba(0,0,0,0.08)';
            this.style.borderColor = 'transparent';
        });
    });
});

// ==========================================
// SECTION JS 3 : SYSTÈME WISHLIST (FAVORIS)
// ==========================================
// Fonctionnement AJAX (fetch) : les requêtes se font en arrière-plan
// sans recharger la page → expérience utilisateur fluide

// Injection dynamique des styles CSS d'animation dans le <head>
// Technique utilisée pour garder les styles liés à ce comportement JS ensemble
(function injectWishlistStyles() {
    const style = document.createElement('style');
    style.textContent = `
        /* Animation cœur navbar - pulse rouge quand on ajoute un favori */
        @keyframes navHeartBeat {
            0%   { transform: scale(1); }
            20%  { transform: scale(1.5); color: #ef4444; }
            40%  { transform: scale(0.9); }
            60%  { transform: scale(1.35); }
            80%  { transform: scale(0.95); }
            100% { transform: scale(1); }
        }
        @keyframes navHeartBeatRemove {
            0%   { transform: scale(1); }
            30%  { transform: scale(0.6); }
            60%  { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        /* Badge flottant "+1 ❤" */
        @keyframes floatUpFade {
            0%   { opacity: 1; transform: translateY(0) scale(1); }
            70%  { opacity: 1; transform: translateY(-30px) scale(1.1); }
            100% { opacity: 0; transform: translateY(-50px) scale(0.8); }
        }
        /* Glow sur le lien favoris */
        @keyframes navFavGlow {
            0%   { box-shadow: 0 0 0px rgba(239,68,68,0); background: transparent; border-radius: 8px; }
            40%  { box-shadow: 0 0 14px rgba(239,68,68,0.5); background: rgba(239,68,68,0.08); border-radius: 8px; }
            100% { box-shadow: 0 0 0px rgba(239,68,68,0); background: transparent; border-radius: 8px; }
        }
        @keyframes countBounce {
            0%   { transform: scale(1); }
            40%  { transform: scale(1.6); }
            70%  { transform: scale(0.85); }
            100% { transform: scale(1); }
        }
        .nav-heart-added {
            animation: navHeartBeat 0.6s ease forwards !important;
            color: #ef4444 !important;
        }
        .nav-heart-removed {
            animation: navHeartBeatRemove 0.4s ease forwards !important;
        }
        .nav-fav-glow {
            animation: navFavGlow 0.8s ease forwards !important;
        }
        .count-bounce {
            animation: countBounce 0.5s ease forwards !important;
        }
        .wishlist-float-badge {
            position: fixed;
            font-size: 0.85em;
            font-weight: 700;
            color: #ef4444;
            pointer-events: none;
            z-index: 99999;
            animation: floatUpFade 1s ease forwards;
            white-space: nowrap;
        }
    `;
    document.head.appendChild(style);
})();

// --- Fonction : animation navbar favoris ---
// Appelée après chaque ajout/suppression de favori pour animer l'icône cœur dans la navbar
// action : 'added' ou 'removed'
// count  : nouveau nombre total de favoris
function animateNavFavoris(action, count) {
    // Cibler le lien favoris dans la navbar (classe wishlist-btn)
    const navFavLink    = document.querySelector('.wishlist-btn');
    const navHeartIcon  = navFavLink ? navFavLink.querySelector('i') : null;
    const wishlistCount = document.querySelector('.wishlist-count');

    if (navFavLink) {
        // Glow sur le lien
        navFavLink.classList.remove('nav-fav-glow');
        void navFavLink.offsetWidth; // Astuce : forcer un "reflow" du DOM pour relancer l'animation CSS depuis zéro
        navFavLink.classList.add('nav-fav-glow');
        setTimeout(() => navFavLink.classList.remove('nav-fav-glow'), 900);
    }

    if (navHeartIcon) {
        if (action === 'added') {
            navHeartIcon.classList.remove('nav-heart-removed');
            void navHeartIcon.offsetWidth;
            navHeartIcon.classList.add('nav-heart-added');
            navHeartIcon.style.color = '#ef4444';
            setTimeout(() => {
                navHeartIcon.classList.remove('nav-heart-added');
                navHeartIcon.style.color = '';
            }, 700);
        } else {
            navHeartIcon.classList.remove('nav-heart-added');
            void navHeartIcon.offsetWidth;
            navHeartIcon.classList.add('nav-heart-removed');
            navHeartIcon.style.color = '';
            setTimeout(() => navHeartIcon.classList.remove('nav-heart-removed'), 500);
        }

        // Badge flottant "+1 ❤" ou "-1" depuis l'icône navbar
        if (navFavLink) {
            const rect = navFavLink.getBoundingClientRect();
            const badge = document.createElement('span');
            badge.className = 'wishlist-float-badge';
            badge.textContent = action === 'added' ? '+1 ❤' : '-1 ❤';
            badge.style.left = (rect.left + rect.width / 2) + 'px';
            badge.style.top  = (rect.top - 5 + window.scrollY) + 'px';
            document.body.appendChild(badge);
            setTimeout(() => badge.remove(), 1100);
        }
    }

    // Mettre à jour et faire rebondir le compteur
    if (wishlistCount && count !== undefined) {
        wishlistCount.textContent = count;
        wishlistCount.classList.remove('count-bounce');
        void wishlistCount.offsetWidth;
        wishlistCount.classList.add('count-bounce');
        setTimeout(() => wishlistCount.classList.remove('count-bounce'), 600);
    }
}

// Charger l'état initial de la wishlist au chargement de la page
// Appel AJAX vers get-wishlist.php pour savoir quels produits sont déjà en favoris
// → colorise les cœurs en rouge sur les produits déjà dans la wishlist
loadWishlistState();

function loadWishlistState() {
    // fetch() = requête HTTP asynchrone (AJAX en JS moderne)
    // Appelle get-wishlist.php qui retourne un JSON : { success: true, favorites: [1, 5, 12], count: 3 }
    fetch('get-wishlist.php')
        .then(response => response.json()) // Convertit la réponse en objet JS
        .then(data => {
            if (data.success && data.favorites) {
                // Pour chaque bouton cœur sur la page, vérifie si son produit est dans la wishlist
                document.querySelectorAll('.btn-wishlist').forEach(button => {
                    const productId = parseInt(button.getAttribute('data-product-id'));
                    const icon      = button.querySelector('i');
                    if (data.favorites.includes(productId)) {
                        // Produit déjà en favoris → cœur plein rouge (fas = Font Awesome Solid)
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                        icon.style.color = '#ef4444';
                    } else {
                        // Pas en favoris → cœur vide (far = Font Awesome Regular)
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                        icon.style.color = '';
                    }
                });
                // Met à jour le compteur de favoris dans la navbar
                const wishlistCount = document.querySelector('.wishlist-count');
                if (wishlistCount && data.count !== undefined) {
                    wishlistCount.textContent = data.count;
                }
            }
        })
        .catch(err => console.error('Erreur chargement wishlist:', err)); // Gestion d'erreur réseau
}

// --- Gestion des clics sur les boutons wishlist ---
// Pour chaque bouton cœur, on écoute le clic et on envoie une requête AJAX à toggle_wishlist.php
document.querySelectorAll('.btn-wishlist').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();     // Annule le comportement par défaut du bouton
        e.stopPropagation();    // Empêche le clic de remonter jusqu'à la carte (sinon redirige vers produit.php)

        const productId = this.getAttribute('data-product-id'); // Récupère l'ID du produit
        const icon      = this.querySelector('i');

        if (!productId) return; // Sécurité : sortir si pas d'ID

        this.style.pointerEvents = 'none'; // Désactive temporairement les clics pour éviter le double-clic

        // Requête AJAX vers toggle_wishlist.php
        // application/x-www-form-urlencoded : format classique des formulaires HTML
        fetch('toggle_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'product_id=' + encodeURIComponent(productId)
        })
        .then(response => response.json()) // Réponse JSON : { success: true, action: 'added', wishlist_count: 4 }
        .then(data => {
            if (data.success) {
                if (data.action === 'added') {
                    // Produit ajouté aux favoris → cœur plein rouge + animation
                    icon.classList.remove('far');
                    icon.classList.add('fas');
                    icon.style.color     = '#ef4444';
                    this.style.transform = 'scale(1.3)';
                    setTimeout(() => this.style.transform = 'scale(1)', 300);
                } else if (data.action === 'removed') {
                    // Produit retiré des favoris → cœur vide + animation
                    icon.classList.remove('fas');
                    icon.classList.add('far');
                    icon.style.color     = '';
                    this.style.transform = 'scale(0.8)';
                    setTimeout(() => this.style.transform = 'scale(1)', 300);
                }

                // Déclenche l'animation sur l'icône cœur dans la navbar
                animateNavFavoris(data.action, data.wishlist_count);

            } else {
                if (data.redirect) {
                    // L'utilisateur n'est pas connecté → alerte + redirection vers connexion
                    alert('Veuillez vous connecter pour ajouter des favoris');
                    setTimeout(() => window.location.href = data.redirect, 1000);
                } else {
                    alert(data.message || 'Erreur lors de l\'ajout aux favoris');
                }
            }

            this.style.pointerEvents = 'auto'; // Réactive les clics sur le bouton
        })
        .catch(err => {
            // Erreur réseau (pas de connexion, timeout, etc.)
            console.error('❌ Erreur réseau wishlist:', err);
            alert('Erreur réseau. Veuillez réessayer.');
            this.style.pointerEvents = 'auto';
        });
    });
});
</script>