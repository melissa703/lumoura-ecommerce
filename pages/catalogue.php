<?php
// ============================================================
// FICHIER : catalogue.php
// RÔLE    : Page principale du catalogue de parfums
//           → Affiche les produits filtrés depuis la BDD
//           → Gère l'ajout au panier (AJAX + repli POST classique)
//           → Gère la wishlist (favoris) via AJAX
// ============================================================

require_once '../includes/config.php';    // Configuration générale
require_once '../includes/db.php';        // Connexion BDD (variable $pdo)
require_once '../includes/functions.php'; // isLoggedIn(), formatPrice(), etc.

// ============================================================
// SECTION 1 : AJOUT AU PANIER (repli sans JavaScript)
// ============================================================
// Avec le JS actif, le bouton passe par ajouter_au_panier.php (sans rechargement).
// Ce bloc reste le filet de sécurité si le JS est désactivé ou en erreur.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {

    if (!isLoggedIn()) {
        $_SESSION['login_redirect'] = 'Veuillez vous connecter pour ajouter un article au panier.';
        header('Location: connexion.php');
        exit();
    }

    $user_id    = $_SESSION['user_id'];
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity   = intval($_POST['quantity'] ?? 1);

    if ($product_id > 0 && $quantity > 0) {
        try {
            // ÉTAPE 1 : le produit existe-t-il ? Quel stock ?
            $stmtProduct = $pdo->prepare("SELECT stock FROM produits WHERE id_produit = ?");
            $stmtProduct->execute([$product_id]);
            $product = $stmtProduct->fetch();

            if (!$product) {
                $_SESSION['cart_message'] = 'Produit non trouvé.';
            } elseif ($product['stock'] < $quantity) {
                $_SESSION['cart_message'] = 'Stock insuffisant.';
            } else {
                // ÉTAPE 2 : format le moins cher (celui affiché "Dès X €")
                $formatStmt = $pdo->prepare("SELECT id_format FROM produit_formats WHERE id_produit = ? ORDER BY prix ASC LIMIT 1");
                $formatStmt->execute([$product_id]);
                $format_id = (int) $formatStmt->fetchColumn();

                // Déjà dans le panier (même format) ? → on additionne, sinon on insère
                $checkStmt = $pdo->prepare("SELECT * FROM panier WHERE id_utilisateur = ? AND id_produit = ? AND id_format = ?");
                $checkStmt->execute([$user_id, $product_id, $format_id]);
                $existingItem = $checkStmt->fetch();

                if ($existingItem) {
                    $updateStmt = $pdo->prepare("UPDATE panier SET quantite = ? WHERE id_panier = ?");
                    $updateStmt->execute([$existingItem['quantite'] + $quantity, $existingItem['id_panier']]);
                } else {
                    $insertStmt = $pdo->prepare("INSERT INTO panier (id_utilisateur, id_produit, id_format, quantite) VALUES (?, ?, ?, ?)");
                    $insertStmt->execute([$user_id, $product_id, $format_id, $quantity]);
                }

                $_SESSION['cart_message'] = 'Le produit a été ajouté à votre panier.';
            }

        } catch (PDOException $e) {
            $_SESSION['cart_message'] = 'Erreur lors de l\'ajout au panier : ' . $e->getMessage();
        }
    } else {
        $_SESSION['cart_message'] = 'Données invalides.';
    }

    // Patron PRG (Post/Redirect/Get) : évite de renvoyer le formulaire en rafraîchissant
    $referer = $_SERVER['HTTP_REFERER'] ?? 'catalogue.php';
    header("Location: $referer");
    exit();
}

// ============================================================
// SECTION 2 : PARAMÈTRES URL (nettoyés)
// ============================================================
$page      = max(1, (int)($_GET['page']      ?? 1));
$category  = trim($_GET['category']  ?? '');   // Femme / Homme / Unisexe
$filter    = trim($_GET['filter']    ?? '');   // new / bestseller / promo
$search    = trim($_GET['search']    ?? '');
$sort      = trim($_GET['sort']      ?? 'newest');
$famille   = trim($_GET['famille']   ?? '');
$marque    = trim($_GET['marque']    ?? '');
$max_price = (float)($_GET['max_price'] ?? 0);
$min_price = (float)($_GET['min_price'] ?? 0);

$limit  = 60;                    // Produits chargés à chaque fois ("Voir plus de parfums")
$offset = ($page - 1) * $limit;

// ============================================================
// SECTION 3 : CONSTRUCTION DE LA CLAUSE WHERE
// ============================================================
$where  = [];
$params = [];

// Listes blanches : seules ces valeurs sont acceptées
$valid_categories = ['Femme', 'Homme', 'Unisexe'];
$valid_filters    = ['new', 'bestseller', 'promo'];
$familles         = ['Florale', 'Boisée', 'Orientale', 'Hespéridée', 'Fougère', 'Chyprée', 'Aromatique', 'Gourmande', 'Fruitée', 'Aquatique', 'Cuir'];

if ($category && in_array($category, $valid_categories)) {
    $where[]             = "p.genre = :category";
    $params[':category'] = $category;
}

if ($filter && in_array($filter, $valid_filters)) {
    if ($filter === 'new') {
        $where[] = "p.nouveaute = 1";
    } elseif ($filter === 'bestseller') {
        $where[] = "p.bestseller = 1";
    } elseif ($filter === 'promo') {
        $where[] = "p.promotion_pourcentage > 0";
    }
}

if ($search) {
    $where[]           = "(p.nom LIKE :search OR p.description LIKE :search OR p.description_courte LIKE :search OR p.marque LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($famille && in_array($famille, $familles)) {
    $where[]            = "p.famille_olfactive = :famille";
    $params[':famille'] = $famille;
}

if ($marque) {
    $where[]           = "p.marque = :marque";
    $params[':marque'] = $marque;
}

if ($max_price > 1) {
    $where[]              = "COALESCE(f.prix_min, p.prix) <= :max_price";
    $params[':max_price'] = $max_price;
}

if ($min_price > 0) {
    $where[]              = "COALESCE(f.prix_min, p.prix) >= :min_price";
    $params[':min_price'] = $min_price;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// ============================================================
// SECTION 4 : REQUÊTES SQL
// ============================================================
// Jointure sur les formats : prix_min = format le moins cher ("Dès X €"),
// formats = contenances ("30 / 50 / 100"), nb_formats = nombre de formats
$formatsJoin = "LEFT JOIN (
        SELECT id_produit,
               MIN(prix) AS prix_min,
               GROUP_CONCAT(volume_ml ORDER BY volume_ml SEPARATOR ' / ') AS formats,
               COUNT(*) AS nb_formats
        FROM produit_formats
        GROUP BY id_produit
    ) f ON f.id_produit = p.id_produit";

// Nombre total de produits (pour "Voir plus" et les compteurs)
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM produits p $formatsJoin $whereClause");
$countStmt->execute($params);
$total_products = (int) $countStmt->fetchColumn();
$total_pages    = (int) ceil($total_products / $limit);

// Tri sécurisé (PHP 8 : match)
$orderBy = match($sort) {
    'price_asc'  => 'COALESCE(f.prix_min, p.prix) ASC',
    'price_desc' => 'COALESCE(f.prix_min, p.prix) DESC',
    'name_asc'   => 'p.nom ASC',
    'name_desc'  => 'p.nom DESC',
    'popular'    => '(p.bestseller + p.nouveaute) DESC, COALESCE(f.prix_min, p.prix) ASC',
    default      => 'p.date_ajout DESC',
};

// Produits de la page courante.
// id_format_min = id du format le moins cher : le bouton "Ajouter" l'envoie à ajouter_au_panier.php
$query = "
    SELECT p.*, c.nom_categorie, f.prix_min, f.formats, f.nb_formats,
           (SELECT pf.id_format FROM produit_formats pf
             WHERE pf.id_produit = p.id_produit ORDER BY pf.prix ASC LIMIT 1) AS id_format_min
    FROM produits p
    LEFT JOIN categories c ON p.id_categorie = c.id_categorie
    $formatsJoin
    $whereClause
    ORDER BY $orderBy
    LIMIT :limit OFFSET :offset
";

// bindValue() : LIMIT et OFFSET doivent être liés en entiers
$stmt = $pdo->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Marques disponibles (filtre sidebar)
$all_marques = $pdo->query("SELECT DISTINCT marque FROM produits WHERE marque IS NOT NULL AND marque != '' ORDER BY marque")
                   ->fetchAll(PDO::FETCH_COLUMN);

// ============================================================
// SECTION 5 : URL DES FILTRES (cumulables) + PASTILLES ACTIVES
// ============================================================
// Avant : chaque lien de filtre effaçait les autres (genre → perdait la marque, etc.).
// Maintenant : on part des paramètres actuels, on bascule UN paramètre, et on repart à la page 1.
$currentGet = $_GET;
unset($currentGet['page']);

// Lien qui active le filtre $key=$value (ou le désactive s'il est déjà actif)
$toggleUrl = function (string $key, string $value) use ($currentGet) {
    $p = $currentGet;
    if (isset($p[$key]) && (string)$p[$key] === $value) {
        unset($p[$key]);
    } else {
        $p[$key] = $value;
    }
    $q = http_build_query($p);
    return 'catalogue.php' . ($q ? '?' . $q : '');
};
// Lien qui retire complètement le paramètre $key
$removeUrl = function (string $key) use ($currentGet) {
    $p = $currentGet;
    unset($p[$key]);
    $q = http_build_query($p);
    return 'catalogue.php' . ($q ? '?' . $q : '');
};

$filterLabels = ['new' => 'Nouveautés', 'bestseller' => 'Best-sellers', 'promo' => 'Promotions'];
$chips = []; // [libellé, url pour retirer]
if ($category && in_array($category, $valid_categories)) $chips[] = [$category, $removeUrl('category')];
if ($filter && isset($filterLabels[$filter]))             $chips[] = [$filterLabels[$filter], $removeUrl('filter')];
if ($famille && in_array($famille, $familles))            $chips[] = [$famille, $removeUrl('famille')];
if ($marque)                                              $chips[] = [$marque, $removeUrl('marque')];
if ($search)                                              $chips[] = ['« ' . $search . ' »', $removeUrl('search')];
if ($max_price > 1)                                       $chips[] = ['≤ ' . (int)$max_price . ' €', $removeUrl('max_price')];
if ($min_price > 0)                                       $chips[] = ['≥ ' . (int)$min_price . ' €', $removeUrl('min_price')];

// Titre de page dynamique
$pageTitle = "Notre Collection de Parfums";
if ($category) $pageTitle .= " – " . htmlspecialchars($category);
if ($filter === 'new') $pageTitle .= " – Nouveautés";
if ($filter === 'bestseller') $pageTitle .= " – Best-sellers";
if ($filter === 'promo') $pageTitle .= " – Promotions";
if ($search) $pageTitle .= " – Recherche : " . htmlspecialchars($search);

include '../includes/header.php';
?>
<style>
/* ============================================================
   LUMOURA — CATALOGUE
   Variables communes + effets 3D / hover (même langage que le quiz)
   ============================================================ */
.lm-catalogue {
    --c-gold: #d4a017;
    --c-gold-l: #e6c65e;
    --c-gold-d: #9a7b2f;
    --c-brown: #3a2c1f;
    --c-cream: #f8f5f0;
    --c-line: #ebdfc8;
    --c-glow: rgba(212, 175, 90, .45);
    --c-ease: cubic-bezier(.22, 1, .36, 1);
    max-width: 1400px;
    margin: 0 auto;
    padding: 30px 20px;
}

/* ----- En-tête : titre avec reflet doré qui traverse une fois (comme le quiz) ----- */
.lm-head { text-align: center; margin-bottom: 44px; }
.lm-head h1.lm-page-title {
    margin: 0 0 15px;
    font-size: clamp(2rem, 4.4vw, 2.8rem);
    font-weight: 300;
    color: var(--c-brown);
}
@supports ((-webkit-background-clip: text) or (background-clip: text)) {
    .lm-head h1.lm-page-title {
        background: linear-gradient(100deg, var(--c-brown) 42%, var(--c-gold) 50%, var(--c-brown) 58%) 100% 0 / 260% 100%;
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        animation: lmGlint 2.6s var(--c-ease) .4s 1 forwards;
    }
}
@keyframes lmGlint { to { background-position: 0 0; } }
.lm-head .lm-subtitle { max-width: 800px; margin: 0 auto; font-size: 1.15rem; }
.lm-head .lm-rule {
    display: block; width: 64px; height: 2px; margin: 22px auto 0;
    background: linear-gradient(90deg, var(--c-gold-d), var(--c-gold), var(--c-gold-l));
    transform-origin: center; animation: lmRule 1.1s var(--c-ease) .25s backwards;
}
@keyframes lmRule { from { transform: scaleX(0); } }

/* ----- Message panier ----- */
.lm-flash {
    padding: 15px 20px; margin-bottom: 25px; text-align: center; font-size: 1.05rem;
    background: #f0f9f0; border: 1px solid #c6e9c6; border-radius: 10px; color: #2d662d;
    animation: lmFadeIn .5s ease backwards;
}
.lm-flash i { margin-right: 10px; }

/* ----- Bandeau d'infos : 4 mini-cartes 3D ----- */
.lm-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 16px;
    margin-bottom: 44px;
    perspective: 1400px;
}
.lm-stat {
    --rx: 0deg; --ry: 0deg; --mx: 50%; --my: 50%;
    position: relative;
    display: flex; align-items: center; gap: 14px;
    padding: 20px 22px;
    background: linear-gradient(160deg, #fffdf8, var(--c-cream));
    border: 1px solid var(--c-line);
    border-radius: 12px;
    font-size: 1rem; color: var(--c-brown);
    transform: perspective(900px) rotateX(var(--rx)) rotateY(var(--ry));
    transition: transform .55s var(--c-ease), box-shadow .5s var(--c-ease), border-color .3s;
    overflow: hidden;
}
.lm-stat.is-tilting {
    transition: transform .12s ease-out, box-shadow .5s var(--c-ease), border-color .3s;
    border-color: var(--c-gold);
    box-shadow: 0 22px 40px -22px rgba(26, 14, 8, .45), 0 0 0 1px rgba(212, 175, 90, .35), 0 0 30px -10px var(--c-glow);
}
.lm-stat::after { /* reflet doré qui suit le curseur */
    content: ""; position: absolute; inset: 0; pointer-events: none;
    background: radial-gradient(220px circle at var(--mx) var(--my), rgba(212, 175, 90, .22), transparent 60%);
    opacity: 0; transition: opacity .35s ease;
}
.lm-stat.is-tilting::after { opacity: 1; }
.lm-stat i {
    flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center;
    width: 44px; height: 44px; border-radius: 50%;
    background: #f5ecda; color: var(--c-gold); font-size: 1.15rem;
    transition: transform .45s var(--c-ease), background-color .3s, color .3s, box-shadow .4s var(--c-ease);
}
.lm-stat:hover i { transform: scale(1.12) rotate(-6deg); background: var(--c-gold); color: #fff; box-shadow: 0 0 0 8px rgba(212, 175, 90, .16); }

/* ----- Layout : sidebar + contenu ----- */
.lm-layout { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 40px; align-items: start; }

/* ----- Sidebar ----- */
.lm-sidebar {
    position: sticky;
    top: 96px;                                /* sous le header fixe */
    max-height: calc(100vh - 116px);          /* reste utilisable même si très haute */
    overflow-y: auto;
    padding: 25px;
    background: #fff;
    border: 1px solid #f0e8d8;
    border-radius: 14px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, .05);
    scrollbar-width: thin;
    scrollbar-color: var(--c-line) transparent;
}
.lm-sidebar h3 { margin: 0; font-size: 1.4rem; color: var(--c-brown); }
.lm-sidebar h3 i { margin-right: 10px; color: var(--c-gold); }
.lm-sidebar h4 { margin: 0 0 12px; font-size: 1.05rem; color: #555; }
.lm-fsection { margin-bottom: 25px; }
.lm-flist { display: flex; flex-direction: column; gap: 8px; }
.lm-flist--scroll { max-height: 200px; overflow-y: auto; padding-right: 5px; scrollbar-width: thin; }

.lm-clear {
    display: inline-flex; align-items: center; gap: 6px; margin-top: 10px;
    color: var(--c-gold); font-weight: 500; font-size: .95rem; text-decoration: none;
    transition: color .2s, transform .3s var(--c-ease);
}
.lm-clear:hover { color: var(--c-brown); transform: translateX(3px); }

/* Liens de filtre (remplace les styles inline : permet le survol) */
.lm-flink {
    position: relative;
    display: flex; align-items: center;
    padding: 10px 15px;
    color: #444; text-decoration: none;
    background: transparent;
    border: 1px solid #eee; border-radius: 8px;
    transition: transform .35s var(--c-ease), background-color .25s, border-color .25s, box-shadow .35s var(--c-ease), color .25s;
}
.lm-flink i { margin-right: 10px; color: var(--c-gold); transition: transform .4s var(--c-ease); }
.lm-flink:hover {
    transform: translateX(5px);
    border-color: var(--c-gold); background: #fffaf0;
    box-shadow: 0 10px 18px -14px rgba(58, 44, 31, .5);
}
.lm-flink:hover i { transform: scale(1.2) rotate(-8deg); }
.lm-flink.is-active { background: var(--c-cream); border-color: var(--c-gold); color: var(--c-gold-d); font-weight: 600; }
.lm-flink.is-active::after {
    content: "\2713"; position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
    width: 18px; height: 18px; border-radius: 50%;
    background: var(--c-gold); color: #fff; font-size: .65rem; line-height: 18px; text-align: center;
}
.lm-flink:focus-visible { outline: 2px solid var(--c-gold); outline-offset: 2px; }
.lm-flink--sm { padding: 8px 12px; }

/* Recherche */
.lm-search { display: flex; }
.lm-search input {
    flex: 1; min-width: 0; padding: 10px 15px; font-size: .95rem;
    border: 1px solid #ddd; border-right: 0; border-radius: 8px 0 0 8px;
    transition: border-color .2s, box-shadow .35s var(--c-ease);
}
.lm-search input:focus { outline: none; border-color: var(--c-gold); box-shadow: 0 0 0 4px rgba(212, 160, 23, .18); }
.lm-search button {
    padding: 10px 15px; border: 0; border-radius: 0 8px 8px 0; cursor: pointer;
    background: var(--c-gold); color: #fff; transition: background-color .25s;
}
.lm-search button:hover { background: var(--c-brown); }

/* Prix */
.lm-range-top { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: .9rem; color: #666; }
.lm-range { width: 100%; accent-color: var(--c-gold); }

/* Bouton "Appliquer" + "Voir plus" + vide : balayage doré + halo (même effet que le quiz) */
.lm-apply, .lm-more .lm-more-btn, .lm-empty-btn {
    position: relative; overflow: hidden;
    transform: translate(var(--bx, 0px), var(--by, 0px));
    transition: transform .25s ease-out, box-shadow .4s var(--c-ease), background-color .3s, color .3s, border-color .3s;
}
.lm-apply::before, .lm-more .lm-more-btn::before, .lm-empty-btn::before, .lm-grid .lm-btn-cart::before {
    content: ""; position: absolute; inset: 0 auto 0 -60%; width: 45%;
    background: linear-gradient(105deg, transparent, rgba(255, 255, 255, .5), transparent);
    transform: skewX(-20deg); transition: left .8s var(--c-ease); pointer-events: none;
}
.lm-apply:hover::before, .lm-more .lm-more-btn:hover::before, .lm-empty-btn:hover::before,
.lm-grid .lm-btn-cart:hover:not(:disabled)::before { left: 120%; }
.lm-apply {
    width: 100%; margin-top: 15px; padding: 10px; border: 0; border-radius: 8px; cursor: pointer;
    background: linear-gradient(135deg, var(--c-gold-d), var(--c-gold) 50%, var(--c-gold-l));
    color: #fff; font-weight: 600; font-size: .95rem;
    box-shadow: 0 10px 22px -12px var(--c-glow), inset 0 1px 0 rgba(255, 255, 255, .35);
}
.lm-apply:hover { box-shadow: 0 16px 28px -12px var(--c-glow), 0 0 0 5px rgba(212, 175, 90, .18); }
.lm-apply:active { transform: translate(var(--bx, 0px), var(--by, 0px)) scale(.97); }

/* Bouton "Filtres" (mobile uniquement) */
.lm-filters-toggle {
    display: none; width: 100%; margin-top: 12px; padding: 11px 16px;
    align-items: center; justify-content: center; gap: 10px;
    border: 1px solid var(--c-brown); border-radius: 10px; background: transparent; color: var(--c-brown);
    font-weight: 600; letter-spacing: .06em; text-transform: uppercase; font-size: .8rem; cursor: pointer;
}

/* ----- Barre de contrôle + pastilles de filtres actifs ----- */
.lm-toolbar {
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
    margin-bottom: 18px; padding: 20px; background: #fff;
    border: 1px solid #f0e8d8; border-radius: 14px; box-shadow: 0 3px 10px rgba(0, 0, 0, .05);
}
.lm-toolbar .lm-count { font-size: 1.1rem; color: var(--c-brown); }
.lm-toolbar label { font-weight: 500; color: #555; font-size: .95rem; }
.lm-toolbar select {
    min-width: 180px; padding: 10px 15px; font-size: .95rem; cursor: pointer;
    border: 1px solid #ddd; border-radius: 8px; background: #fff;
    transition: border-color .2s, box-shadow .35s var(--c-ease);
}
.lm-toolbar select:hover, .lm-toolbar select:focus { outline: none; border-color: var(--c-gold); box-shadow: 0 0 0 4px rgba(212, 160, 23, .15); }

.lm-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 26px; }
.lm-chip {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 6px 12px; border-radius: 999px;
    background: #f5ecda; border: 1px solid #e8d8b0; color: #7a5a00;
    font-size: .82rem; font-weight: 600; text-decoration: none;
    transition: transform .35s var(--c-ease), background-color .25s, color .25s, border-color .25s;
}
.lm-chip i { font-size: .7rem; transition: transform .4s var(--c-ease); }
.lm-chip:hover { transform: translateY(-2px); background: var(--c-brown); border-color: var(--c-brown); color: #f8efdc; }
.lm-chip:hover i { transform: rotate(90deg); }

/* ----- Grille ----- */
.lm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));
    gap: 32px;
    margin-top: 20px;
}

/* ----- Carte ----- */
.lm-grid .lm-card {
    --tx: 0; --ty: 0; --mx: 50%; --my: 0%; --lift: 0px;
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
    background: linear-gradient(180deg, #fffdf8 0%, #fbf5ea 100%);
    border: 1px solid #ebdfc8;
    border-radius: 14px;
    overflow: hidden;
    cursor: pointer;
    color: #3a2c1f;
    box-shadow:
        calc(var(--tx) * -14px) calc(12px + var(--ty) * -10px) 28px rgba(58, 44, 31, .10),
        0 2px 6px rgba(58, 44, 31, .05);
    transform: perspective(1100px) translate3d(0, var(--lift), 0)
               rotateX(calc(var(--ty) * 6deg)) rotateY(calc(var(--tx) * -6deg));
    transition: transform .6s cubic-bezier(.22, .8, .24, 1),
                box-shadow .6s cubic-bezier(.22, .8, .24, 1),
                border-color .35s ease;
}
.lm-grid .lm-card.is-out { opacity: .78; }

/* Apparition au scroll : propriétés indépendantes (translate / opacity) → aucun conflit avec la 3D */
.lm-grid .lm-card.lm-reveal { opacity: 0; translate: 0 34px; }
.lm-grid .lm-card.lm-reveal.is-in {
    opacity: 1; translate: 0 0;
    transition: transform .6s cubic-bezier(.22, .8, .24, 1), box-shadow .6s ease, border-color .35s ease,
                opacity .8s ease calc(var(--i, 0) * 90ms), translate .9s var(--c-ease) calc(var(--i, 0) * 90ms);
}

/* Reflet lumineux qui suit le curseur */
.lm-grid .lm-card::after {
    content: ""; position: absolute; inset: 0; z-index: 4; pointer-events: none; opacity: 0;
    transition: opacity .45s ease;
    background: radial-gradient(circle at var(--mx) var(--my), rgba(255, 244, 214, .42) 0%, rgba(255, 244, 214, 0) 52%);
}

/* ----- Zone image ----- */
.lm-grid .lm-media {
    position: relative; height: 280px; overflow: hidden;
    background: radial-gradient(ellipse at 50% 30%, #fffaf0 0%, #f1e6d2 100%);
}
.lm-grid .lm-media img {
    display: block; width: 100%; height: 100%; object-fit: cover; transform: scale(1);
    transition: transform .7s cubic-bezier(.22, .8, .24, 1);
}
.lm-grid .lm-media-fade {
    position: absolute; inset: 0; pointer-events: none;
    background: linear-gradient(to bottom, rgba(251, 245, 234, 0) 65%, rgba(251, 245, 234, .35) 100%);
}
.lm-grid .lm-media::before { /* éclat diagonal qui traverse la photo au survol */
    content: ""; position: absolute; inset: 0 auto 0 -70%; width: 40%; z-index: 3; pointer-events: none;
    background: linear-gradient(105deg, transparent, rgba(255, 244, 214, .55), transparent);
    transform: skewX(-20deg); transition: left 1s var(--c-ease);
}
.lm-grid .lm-card:hover .lm-media::before { left: 130%; }

/* ----- Badges ----- */
.lm-grid .lm-card .lm-badges {
    position: absolute; top: 14px; left: 14px; z-index: 5;
    display: flex; flex-direction: column; align-items: flex-start; gap: 6px;
}
.lm-grid .lm-card .lm-badge {
    display: inline-block; margin: 0; padding: 5px 11px; border-radius: 999px;
    font-size: .68rem; font-weight: 600; line-height: 1; letter-spacing: .09em; text-transform: uppercase;
    box-shadow: 0 2px 8px rgba(58, 44, 31, .18);
    transition: transform .4s var(--c-ease);
}
.lm-grid .lm-card:hover .lm-badge { transform: translateX(3px); }
.lm-grid .lm-card .lm-badge--sale { background: #8c2f39; color: #fff8ef; }
.lm-grid .lm-card .lm-badge--new  { background: #3a2c1f; color: #f6ecd8; }
.lm-grid .lm-card .lm-badge--best { background: linear-gradient(135deg, #e2b53a, #c9a227); color: #2b2112; }
.lm-grid .lm-card .lm-badge--out  { background: rgba(74, 66, 58, .92); color: #fff; }

/* ----- Bouton favoris ----- */
.lm-grid .lm-card .lm-wish {
    position: absolute; top: 12px; right: 12px; z-index: 5;
    width: 40px; height: 40px; padding: 0;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%; border: 1px solid #ebdfc8; background: rgba(255, 252, 246, .94);
    box-shadow: 0 2px 10px rgba(58, 44, 31, .14); cursor: pointer;
    transition: transform .3s var(--c-ease), border-color .25s ease, box-shadow .25s ease;
}
.lm-grid .lm-card .lm-wish i { font-size: 1.05rem; color: #8d7d6b; transition: color .2s ease, transform .35s var(--c-ease); }
.lm-grid .lm-card .lm-wish:hover { border-color: #ef4444; box-shadow: 0 4px 14px rgba(239, 68, 68, .22); }
.lm-grid .lm-card .lm-wish:hover i { transform: scale(1.2); }

/* ----- Corps ----- */
.lm-grid .lm-body { display: flex; flex-direction: column; flex: 1; padding: 20px 22px 22px; }
.lm-grid .lm-eyebrow {
    display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 8px;
    font-size: .72rem; letter-spacing: .14em; text-transform: uppercase; color: #9a7b2f;
}
.lm-grid .lm-brand { font-weight: 700; }
.lm-grid .lm-dot   { color: #cdbb94; }
.lm-grid .lm-cat   { color: #9b8f7e; }

.lm-grid .lm-card .lm-title {
    margin: 0 0 12px; min-height: 2.4em;
    font-family: 'Cormorant Garamond', 'Playfair Display', Georgia, 'Times New Roman', serif;
    font-size: 1.45rem; font-weight: 600; line-height: 1.2; color: #3a2c1f;
}
.lm-grid .lm-card .lm-title a {
    color: inherit; font-weight: inherit; text-decoration: none;
    background: linear-gradient(var(--c-gold), var(--c-gold)) 0 100% / 0 1px no-repeat; /* filet doré qui se déploie */
    transition: color .3s ease, background-size .6s var(--c-ease);
}
.lm-grid .lm-card:hover .lm-title a { background-size: 100% 1px; }

.lm-grid .lm-formats { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 12px; padding: 0; list-style: none; }
.lm-grid .lm-formats li {
    padding: 4px 10px; border: 1px solid #e3d5b8; border-radius: 999px; background: #fffaf0;
    color: #6b5638; font-size: .75rem; font-weight: 600; letter-spacing: .02em;
    transition: border-color .3s ease, transform .35s var(--c-ease), background-color .3s;
}
.lm-grid .lm-formats li:hover { transform: translateY(-2px); background: #f5ecda; }

.lm-grid .lm-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; margin-bottom: 6px; font-size: .82rem; color: #7d705f; }
.lm-grid .lm-meta i { margin-right: 5px; color: #c9a227; }
.lm-grid .lm-family {
    display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px;
    background: #f5ecda; border: 1px solid #e8d8b0; color: #7a5a00; font-size: .74rem; font-weight: 600;
}
.lm-grid .lm-family i { margin-right: 5px; font-size: .85em; }
.lm-grid .lm-notes { margin-top: 2px; font-size: .78rem; font-style: italic; color: #9b8f7e; }
.lm-grid .lm-notes i { margin-right: 4px; color: #c9a227; }

/* ----- Prix ----- */
.lm-grid .lm-price { display: flex; align-items: baseline; flex-wrap: wrap; gap: 4px 10px; margin-top: auto; padding-top: 16px; }
.lm-grid .lm-price-from { font-size: .7rem; letter-spacing: .14em; text-transform: uppercase; color: #9b8f7e; }
.lm-grid .lm-price-old  { font-size: .95rem; color: #a89c8a; text-decoration: line-through; }
.lm-grid .lm-price-now {
    display: inline-block;
    font-family: 'Cormorant Garamond', 'Playfair Display', Georgia, 'Times New Roman', serif;
    font-size: 1.75rem; font-weight: 700; line-height: 1; color: #3a2c1f;
    transition: transform .45s var(--c-ease);
    transform-origin: left bottom;
}
.lm-grid .lm-card:hover .lm-price-now { transform: scale(1.06); }
.lm-grid .lm-price.is-promo .lm-price-now { color: #8c2f39; }

/* ----- Stock ----- */
.lm-grid .lm-stock { display: flex; align-items: center; gap: 6px; margin: 8px 0 16px; font-size: .82rem; }
.lm-grid .lm-stock--in-stock  { color: #4e7a4a; }
.lm-grid .lm-stock--low-stock { color: #a8641a; }
.lm-grid .lm-stock--low-stock i { animation: lmPulse 1.8s ease-in-out infinite; border-radius: 50%; }
@keyframes lmPulse { 50% { box-shadow: 0 0 0 5px rgba(168, 100, 26, .18); transform: scale(1.12); } }
.lm-grid .lm-stock--out-stock { color: #a93a32; }

/* ----- Bouton "Ajouter au panier" (balayage + magnétisme) ----- */
.lm-grid .lm-actions { display: flex; }
.lm-grid .lm-actions form { flex: 1; margin: 0; }
.lm-grid .lm-card .lm-btn-cart {
    position: relative; overflow: hidden;
    width: 100%; min-height: 46px;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    padding: 12px 16px;
    border: 1px solid #3a2c1f; border-radius: 10px; background: #3a2c1f; color: #f8efdc;
    font-size: .82rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
    cursor: pointer;
    box-shadow: 0 6px 14px rgba(58, 44, 31, .22);
    transform: translate(var(--bx, 0px), var(--by, 0px));
    transition: background .3s ease, border-color .3s ease, color .3s ease, transform .25s ease-out, box-shadow .4s var(--c-ease);
}
.lm-grid .lm-card .lm-btn-cart i { transition: transform .4s var(--c-ease); }
.lm-grid .lm-card .lm-btn-cart:hover:not(:disabled) {
    background: #d4a017; border-color: #d4a017; color: #2b2112;
    box-shadow: 0 14px 24px -10px rgba(212, 160, 23, .6), 0 0 0 5px rgba(212, 160, 23, .15);
}
.lm-grid .lm-card .lm-btn-cart:hover:not(:disabled) i { transform: scale(1.2) rotate(-8deg); }
.lm-grid .lm-card .lm-btn-cart:active:not(:disabled) { transform: translate(var(--bx, 0px), var(--by, 0px)) scale(.97); }
.lm-grid .lm-card .lm-btn-cart.is-success { background: #3E6B4B; border-color: #3E6B4B; color: #fff; }
.lm-grid .lm-card .lm-btn-cart:disabled { background: #d9d2c6; border-color: #d9d2c6; color: #7d7566; box-shadow: none; cursor: not-allowed; }
.lm-grid .lm-card .lm-btn-cart.is-busy { cursor: progress; opacity: .8; }
.lm-grid .lm-card .lm-btn-cart:focus-visible,
.lm-grid .lm-card .lm-wish:focus-visible,
.lm-grid .lm-card .lm-title a:focus-visible { outline: 2px solid #d4a017; outline-offset: 2px; }

/* ============================================================
   SURVOL (souris) : inclinaison, ombre, reflet, flottement
   ============================================================ */
@media (hover: hover) and (pointer: fine) {
    .lm-grid .lm-card:hover {
        --lift: -6px;
        border-color: #d8bd83;
        will-change: transform;
        box-shadow:
            calc(var(--tx) * -16px) calc(22px + var(--ty) * -12px) 44px rgba(58, 44, 31, .17),
            0 4px 10px rgba(58, 44, 31, .06),
            0 0 0 1px rgba(212, 160, 23, .22),
            0 0 36px -12px var(--c-glow);
    }
    .lm-grid .lm-card:hover::after { opacity: 1; }
    .lm-grid .lm-card.is-tilting { transition: transform .14s ease-out, box-shadow .2s ease-out, border-color .35s ease; }
    .lm-grid .lm-card:hover .lm-media img {
        transform: translate3d(calc(var(--tx) * -7px), calc(var(--ty) * -5px), 0) scale(1.07);
    }
    .lm-grid .lm-card.is-tilting .lm-media img { transition: transform .2s ease-out; }
    .lm-grid .lm-card:hover .lm-title a { color: #7a5a00; }
    .lm-grid .lm-card:hover .lm-formats li { border-color: #d8bd83; }
    .lm-grid .lm-card .lm-wish:hover { transform: scale(1.1); }
}

/* Tactile : retour visuel simple */
@media (hover: none) {
    .lm-grid .lm-card:active { transform: scale(.985); transition: transform .15s ease; }
    .lm-grid .lm-card .lm-btn-cart { min-height: 48px; }
}

/* ----- "Voir plus de parfums" ----- */
.lm-more { display: flex; flex-direction: column; align-items: center; gap: 16px; margin-top: 48px; }
.lm-more .lm-more-count { margin: 0; font-size: .88rem; letter-spacing: .02em; color: #9b8f7e; }
.lm-more .lm-more-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-height: 48px; padding: 12px 34px;
    border: 1px solid #3a2c1f; border-radius: 10px; background: transparent; color: #3a2c1f;
    font-size: .82rem; font-weight: 600; letter-spacing: .1em; text-transform: uppercase; text-decoration: none; cursor: pointer;
}
.lm-more .lm-more-btn:hover { background: #3a2c1f; color: #f8efdc; box-shadow: 0 14px 24px -12px rgba(58, 44, 31, .6), 0 0 0 5px rgba(212, 175, 90, .18); }
.lm-more .lm-more-btn.is-loading { opacity: .6; pointer-events: none; }
.lm-more .lm-more-btn:focus-visible { outline: 2px solid #d4a017; outline-offset: 2px; }

@keyframes lmFadeIn { from { opacity: 0; } to { opacity: 1; } }
.lm-grid .lm-card.lm-enter { animation: lmFadeIn .45s ease backwards; }

/* ----- Aucun résultat ----- */
.lm-empty { text-align: center; padding: 80px 20px; background: #fff; border-radius: 16px; box-shadow: 0 6px 20px rgba(0, 0, 0, .08); }
.lm-empty .lm-empty-ico { font-size: 5rem; color: #ece6da; margin-bottom: 20px; animation: lmFloat 4s ease-in-out infinite; }
@keyframes lmFloat { 50% { transform: translateY(-8px) rotate(-4deg); } }
.lm-empty h3 { font-size: 1.8rem; margin: 0 0 15px; color: #2c3e50; }
.lm-empty p { font-size: 1.1rem; color: #7f8c8d; margin: 0 auto 30px; max-width: 600px; }
.lm-empty .lm-empty-actions { display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; }
.lm-empty-btn {
    display: inline-flex; align-items: center; gap: 10px; padding: 15px 35px; border-radius: 10px;
    border: 2px solid var(--c-gold); text-decoration: none; font-weight: 600; font-size: 1.05rem;
}
.lm-empty-btn--fill { background: linear-gradient(135deg, var(--c-gold-d), var(--c-gold) 50%, var(--c-gold-l)); color: #fff; }
.lm-empty-btn--line { background: #fff; color: var(--c-gold); }
.lm-empty-btn--line:hover { background: var(--c-cream); }
.lm-empty-btn:hover { box-shadow: 0 14px 24px -12px var(--c-glow), 0 0 0 5px rgba(212, 175, 90, .18); }

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (max-width: 960px) {
    .lm-layout { grid-template-columns: minmax(0, 1fr); gap: 24px; }
    .lm-sidebar { position: static; max-height: none; overflow: visible; padding: 20px; }
    .lm-filters-toggle { display: flex; }
    .lm-sidebar .lm-fbody { display: none; margin-top: 20px; }
    .lm-sidebar.is-open .lm-fbody { display: block; }
}
@media (max-width: 600px) {
    .lm-catalogue { padding: 20px 14px; }
    .lm-grid { gap: 22px; }
    .lm-grid .lm-media { height: 250px; }
    .lm-grid .lm-body { padding: 18px 18px 20px; }
    .lm-grid .lm-card .lm-title { font-size: 1.35rem; }
    .lm-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .lm-stat { padding: 14px; font-size: .88rem; gap: 10px; }
    .lm-stat i { width: 36px; height: 36px; font-size: 1rem; }
    .lm-toolbar { padding: 16px; }
    .lm-toolbar select { min-width: 0; width: 100%; }
}

/* ============================================================
   ACCESSIBILITÉ : prefers-reduced-motion → aucun mouvement
   ============================================================ */
@media (prefers-reduced-motion: reduce) {
    .lm-grid .lm-card, .lm-grid .lm-card *, .lm-grid .lm-card::after,
    .lm-stat, .lm-stat *, .lm-flink, .lm-chip { transition: none !important; }
    .lm-grid .lm-card, .lm-grid .lm-card:hover, .lm-grid .lm-card:active, .lm-stat, .lm-stat.is-tilting { transform: none !important; }
    .lm-grid .lm-card .lm-media img, .lm-grid .lm-card:hover .lm-media img { transform: none !important; }
    .lm-grid .lm-card::after, .lm-grid .lm-media::before, .lm-apply::before, .lm-grid .lm-btn-cart::before,
    .lm-more .lm-more-btn::before, .lm-empty-btn::before { display: none; }
    .lm-grid .lm-card.lm-enter, .lm-head h1.lm-page-title, .lm-head .lm-rule, .lm-empty .lm-empty-ico,
    .lm-grid .lm-stock--low-stock i { animation: none !important; }
    .lm-head h1.lm-page-title { background: none; -webkit-text-fill-color: currentColor; }
}
</style>

<div class="container lm-catalogue">

    <!-- ===== En-tête ===== -->
    <div class="lm-head">
        <h1 class="lm-page-title">Collection de Parfums d'Exception</h1>
        <p class="lm-subtitle page-subtitle">Découvrez des fragrances uniques, soigneusement sélectionnées pour éveiller vos sens</p>
        <span class="lm-rule" aria-hidden="true"></span>
    </div>

    <!-- Message de confirmation panier (repli sans JS) -->
    <?php if (isset($_SESSION['cart_message'])): ?>
        <div class="lm-flash" role="status">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($_SESSION['cart_message']) ?>
        </div>
        <?php unset($_SESSION['cart_message']); ?>
    <?php endif; ?>

    <!-- ===== Bandeau d'infos (mini-cartes 3D) ===== -->
    <div class="lm-stats">
        <div class="lm-stat"><i class="fas fa-flask"></i><span><strong><?php echo number_format($total_products, 0, ',', ' '); ?></strong> parfums</span></div>
        <div class="lm-stat"><i class="fas fa-truck"></i><span>Livraison gratuite dès 150€</span></div>
        <div class="lm-stat"><i class="fas fa-gift"></i><span>Échantillons offerts</span></div>
        <div class="lm-stat"><i class="fas fa-shield-alt"></i><span>Paiement sécurisé</span></div>
    </div>

    <!-- ===== Layout : sidebar + contenu ===== -->
    <div class="lm-layout">

        <!-- ===== SIDEBAR : filtres (cumulables) ===== -->
        <aside class="lm-sidebar" id="lmSidebar">
            <div class="lm-fhead">
                <h3><i class="fas fa-sliders-h"></i>Filtres</h3>
                <?php if (!empty($chips)): ?>
                    <a href="catalogue.php" class="lm-clear"><i class="fas fa-times-circle"></i>Effacer tous les filtres</a>
                <?php endif; ?>
                <button type="button" class="lm-filters-toggle" id="lmFiltersToggle" aria-expanded="false" aria-controls="lmFiltersBody">
                    <i class="fas fa-filter"></i> Afficher les filtres
                </button>
            </div>

            <div class="lm-fbody" id="lmFiltersBody">

                <!-- Recherche : conserve les autres filtres actifs -->
                <div class="lm-fsection">
                    <h4>Rechercher</h4>
                    <form method="GET" class="lm-search">
                        <?php foreach ($currentGet as $k => $v): if ($k === 'search' || !is_string($v)) continue; ?>
                            <input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($v); ?>">
                        <?php endforeach; ?>
                        <input type="text" name="search" placeholder="Nom, marque..." value="<?php echo htmlspecialchars($search); ?>" aria-label="Rechercher un parfum">
                        <button type="submit" aria-label="Lancer la recherche"><i class="fas fa-search"></i></button>
                    </form>
                </div>

                <!-- Genre -->
                <div class="lm-fsection">
                    <h4>Genre</h4>
                    <div class="lm-flist">
                        <a href="catalogue.php" class="lm-flink<?php echo (!$category && empty($chips)) ? ' is-active' : ''; ?>">
                            <i class="fas fa-th-large"></i>Tous les parfums
                        </a>
                        <?php foreach ($valid_categories as $cat): ?>
                            <a href="<?php echo htmlspecialchars($toggleUrl('category', $cat)); ?>"
                               class="lm-flink<?php echo $category === $cat ? ' is-active' : ''; ?>">
                                <i class="fas <?php echo $cat === 'Femme' ? 'fa-venus' : ($cat === 'Homme' ? 'fa-mars' : 'fa-venus-mars'); ?>"></i>
                                <?php echo htmlspecialchars($cat); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sélections -->
                <div class="lm-fsection">
                    <h4>Sélections</h4>
                    <div class="lm-flist">
                        <?php foreach ([['new', 'fa-star', 'Nouveautés'], ['bestseller', 'fa-fire', 'Best-sellers'], ['promo', 'fa-tag', 'Promotions']] as $sel): ?>
                            <a href="<?php echo htmlspecialchars($toggleUrl('filter', $sel[0])); ?>"
                               class="lm-flink<?php echo $filter === $sel[0] ? ' is-active' : ''; ?>">
                                <i class="fas <?php echo $sel[1]; ?>"></i><?php echo $sel[2]; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Famille olfactive -->
                <div class="lm-fsection">
                    <h4>Famille olfactive</h4>
                    <div class="lm-flist lm-flist--scroll">
                        <?php foreach ($familles as $f): ?>
                            <a href="<?php echo htmlspecialchars($toggleUrl('famille', $f)); ?>"
                               class="lm-flink lm-flink--sm<?php echo $famille === $f ? ' is-active' : ''; ?>">
                                <i class="fas fa-leaf"></i><?php echo htmlspecialchars($f); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Marques -->
                <div class="lm-fsection">
                    <h4>Marques</h4>
                    <div class="lm-flist lm-flist--scroll">
                        <?php if (!empty($all_marques)): ?>
                            <?php foreach ($all_marques as $m): ?>
                                <a href="<?php echo htmlspecialchars($toggleUrl('marque', $m)); ?>"
                                   class="lm-flink lm-flink--sm<?php echo $marque === $m ? ' is-active' : ''; ?>">
                                    <i class="fas fa-copyright"></i><?php echo htmlspecialchars($m); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span style="color:#777;font-size:.9em;">Aucune marque disponible</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Prix : le JS met à jour #priceValueDisplay pendant le glissement -->
                <div class="lm-fsection" style="margin-bottom:0;">
                    <h4>Fourchette de prix</h4>
                    <div class="lm-range-top">
                        <span>0 €</span>
                        <span id="priceValueDisplay"><?php echo (int) ($max_price ?: 500); ?> €</span>
                    </div>
                    <input type="range" min="0" max="500" step="10" value="<?php echo (int) ($max_price ?: 500); ?>"
                           class="lm-range" id="priceSlider" aria-label="Prix maximum">
                    <button type="button" id="applyPrice" class="lm-apply lm-magnetic">Appliquer le filtre prix</button>
                </div>
            </div>
        </aside>

        <!-- ===== ZONE PRINCIPALE ===== -->
        <main class="products-main">

            <!-- Barre : nombre de résultats + tri -->
            <div class="lm-toolbar">
                <div class="lm-count">
                    <strong><?php echo number_format($total_products, 0, ',', ' '); ?></strong>
                    parfum<?php echo $total_products > 1 ? 's' : ''; ?> trouvé<?php echo $total_products > 1 ? 's' : ''; ?>
                </div>

                <div style="display:flex;align-items:center;gap:10px;">
                    <label for="sortSelect">Trier par :</label>
                    <select id="sortSelect" onchange="if(this.value) window.location.href=this.value;">
                        <?php
                        // URL de base : tous les filtres actuels, sauf sort et page
                        $baseParams = $_GET;
                        unset($baseParams['sort'], $baseParams['page']);
                        $baseUrl = '?' . http_build_query($baseParams) . ($baseParams ? '&' : '');

                        $options = [
                            'newest'     => 'Nouveautés',
                            'popular'    => 'Populaire',
                            'price_asc'  => 'Prix croissant',
                            'price_desc' => 'Prix décroissant',
                            'name_asc'   => 'Nom A–Z',
                            'name_desc'  => 'Nom Z–A',
                        ];
                        foreach ($options as $val => $label) {
                            $selected = ($sort === $val) ? 'selected' : '';
                            $url      = htmlspecialchars("{$baseUrl}sort=$val&page=1");
                            echo "<option value=\"{$url}\" $selected>$label</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- Filtres actifs : un clic retire le filtre -->
            <?php if (!empty($chips)): ?>
                <div class="lm-chips" aria-label="Filtres actifs">
                    <?php foreach ($chips as $chip): ?>
                        <a href="<?php echo htmlspecialchars($chip[1]); ?>" class="lm-chip" title="Retirer ce filtre">
                            <?php echo htmlspecialchars($chip[0]); ?> <i class="fas fa-times"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($total_products > 0): ?>
                <!-- ===== GRILLE DE PRODUITS ===== -->
                <div class="lm-grid">
                    <?php foreach ($products as $product):
                        // Prix affiché = format le moins cher, sinon prix du produit
                        $price       = (float)($product['prix_min'] ?? $product['prix']);
                        $has_formats = (int)($product['nb_formats'] ?? 0) > 1; // plusieurs formats → "Dès X €"
                        $discount    = (float)$product['promotion_pourcentage'];
                        $final_price = $discount > 0 ? $price * (1 - $discount / 100) : $price;

                        $image = $product['image_url'] ? htmlspecialchars($product['image_url']) :
                                'https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=800&q=80';

                        // > 10 : vert, 1 à 10 : orange, 0 : rouge
                        $stock_class = $product['stock'] > 10 ? 'in-stock' : ($product['stock'] > 0 ? 'low-stock' : 'out-stock');

                        // Formats (30 / 50 / 100) issus du GROUP_CONCAT, sinon volume_ml
                        $formats_raw  = !empty($product['formats']) ? $product['formats'] : ($product['volume_ml'] ?? '');
                        $formats_list = array_values(array_filter(array_map('trim', explode('/', (string)$formats_raw)), 'strlen'));
                    ?>
                        <!-- "product-card" est conservée : le JS (3D, "Voir plus") s'en sert -->
                        <div class="product-card lm-card<?php echo $product['stock'] == 0 ? ' is-out' : ''; ?>"
                             onclick="window.location='produit.php?id=<?php echo (int) $product['id_produit']; ?>'">

                            <div class="lm-media">
                                <img src="<?php echo $image; ?>"
                                     alt="<?php echo htmlspecialchars($product['nom']); ?> - <?php echo htmlspecialchars($product['marque']); ?>"
                                     loading="lazy">
                                <div class="product-overlay lm-media-fade"></div>

                                <div class="product-badges lm-badges">
                                    <?php if ($discount > 0): ?>
                                        <span class="badge discount lm-badge lm-badge--sale">-<?php echo number_format($discount, 0); ?>%</span>
                                    <?php endif; ?>
                                    <?php if ($product['nouveaute']): ?>
                                        <span class="badge new lm-badge lm-badge--new">Nouveau</span>
                                    <?php endif; ?>
                                    <?php if ($product['bestseller']): ?>
                                        <span class="badge bestseller lm-badge lm-badge--best">Best-seller</span>
                                    <?php endif; ?>
                                    <?php if ($product['stock'] == 0): ?>
                                        <span class="badge out-of-stock lm-badge lm-badge--out">Rupture de stock</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Favoris : le JS (SECTION JS 3) le retrouve via .btn-wishlist + data-product-id -->
                                <button class="btn-wishlist lm-wish" type="button"
                                        data-product-id="<?php echo (int) $product['id_produit']; ?>"
                                        title="Ajouter aux favoris" aria-label="Ajouter aux favoris"
                                        onclick="event.stopPropagation()">
                                    <i class="far fa-heart"></i>
                                </button>
                            </div>

                            <div class="lm-body">
                                <div class="lm-eyebrow">
                                    <span class="lm-brand"><?php echo htmlspecialchars($product['marque']); ?></span>
                                    <span class="lm-dot">•</span>
                                    <span class="lm-cat"><?php echo htmlspecialchars($product['nom_categorie'] ?? 'Parfum'); ?></span>
                                </div>

                                <h3 class="lm-title">
                                    <a href="produit.php?id=<?php echo (int) $product['id_produit']; ?>">
                                        <?php echo htmlspecialchars($product['nom']); ?>
                                    </a>
                                </h3>

                                <?php if (!empty($formats_list)): ?>
                                    <ul class="lm-formats" aria-label="Formats disponibles">
                                        <?php foreach ($formats_list as $fmt): ?>
                                            <li><?php echo htmlspecialchars($fmt); ?> ml</li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <div class="lm-meta">
                                    <span><i class="fas fa-venus-mars"></i><?php echo htmlspecialchars($product['genre']); ?></span>
                                    <?php if (!empty($product['famille_olfactive'])): ?>
                                        <span class="lm-family"><i class="fas fa-leaf"></i><?php echo htmlspecialchars($product['famille_olfactive']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($product['famille_olfactive']) && !empty($product['notes_tete'])): ?>
                                    <div class="lm-notes">
                                        <i class="fas fa-wind"></i>
                                        <?php echo htmlspecialchars(mb_substr($product['notes_tete'], 0, 40)) . (mb_strlen($product['notes_tete']) > 40 ? '...' : ''); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="lm-price<?php echo $discount > 0 ? ' is-promo' : ''; ?>">
                                    <?php if ($has_formats): ?><span class="lm-price-from">Dès</span><?php endif; ?>
                                    <?php if ($discount > 0): ?>
                                        <span class="lm-price-old"><?php echo formatPrice($price); ?></span>
                                    <?php endif; ?>
                                    <span class="lm-price-now"><?php echo formatPrice($final_price); ?></span>
                                </div>

                                <?php if ($product['stock'] > 0): ?>
                                    <div class="lm-stock lm-stock--<?php echo $stock_class; ?>">
                                        <i class="fas fa-check-circle"></i>
                                        <span><?php echo $product['stock'] > 10 ? 'En stock' : '<strong>Plus que ' . (int) $product['stock'] . ' en stock !</strong>'; ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="lm-stock lm-stock--out-stock">
                                        <i class="fas fa-times-circle"></i><span>Rupture de stock</span>
                                    </div>
                                <?php endif; ?>

                                <!-- Ajout au panier : formulaire POST classique (repli) ; le JS l'intercepte
                                     et appelle ajouter_au_panier.php avec le format le moins cher. -->
                                <div class="lm-actions" onclick="event.stopPropagation()">
                                    <form method="POST" action="catalogue.php" onclick="event.stopPropagation()"
                                          data-format-id="<?php echo (int) ($product['id_format_min'] ?? 0); ?>">
                                        <input type="hidden" name="action"     value="add">
                                        <input type="hidden" name="product_id" value="<?php echo (int) $product['id_produit']; ?>">
                                        <input type="hidden" name="quantity"   value="1">
                                        <button type="submit" class="add-to-cart-btn lm-btn-cart"
                                                <?php echo $product['stock'] == 0 ? 'disabled' : ''; ?>>
                                            <i class="fas fa-shopping-cart"></i>
                                            <span><?php echo $product['stock'] > 0 ? 'Ajouter au panier' : 'Indisponible'; ?></span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- ===== AUCUN PRODUIT ===== -->
                <div class="lm-empty">
                    <div class="lm-empty-ico"><i class="fas fa-flask"></i></div>
                    <h3>Aucun parfum ne correspond à vos critères</h3>
                    <p>Essayez d'élargir vos filtres ou découvrez toute notre collection exclusive.</p>
                    <div class="lm-empty-actions">
                        <a href="catalogue.php" class="lm-empty-btn lm-empty-btn--fill lm-magnetic"><i class="fas fa-store"></i>Toute la collection</a>
                        <a href="catalogue.php?filter=new" class="lm-empty-btn lm-empty-btn--line lm-magnetic"><i class="fas fa-star"></i>Voir les nouveautés</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($total_pages > 1): ?>
            <!-- ===== "VOIR PLUS" : vrai lien (fonctionne sans JS) ; le JS ajoute les cartes à la suite ===== -->
            <?php
            $shown_count = min($total_products, $offset + count($products));
            $nextParams  = $_GET;
            $nextParams['page'] = $page + 1;
            $nextUrl     = '?' . http_build_query($nextParams);
            ?>
            <div class="lm-more" id="loadMoreWrap"
                 data-next-page="<?php echo $page + 1; ?>"
                 data-total-pages="<?php echo $total_pages; ?>"
                 data-shown="<?php echo $shown_count; ?>">
                <p class="lm-more-count" aria-live="polite">
                    <span id="shownCount"><?php echo $shown_count; ?></span>
                    sur <?php echo $total_products; ?> parfum<?php echo $total_products > 1 ? 's' : ''; ?> affiché<?php echo $total_products > 1 ? 's' : ''; ?>
                </p>
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo htmlspecialchars($nextUrl); ?>" id="loadMoreBtn" class="lm-more-btn lm-magnetic">Voir plus de parfums</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
// ============================================================
// JAVASCRIPT — Interactions de la page catalogue
// ============================================================
document.addEventListener('DOMContentLoaded', function() {

    const reduceMotionMQ = window.matchMedia('(prefers-reduced-motion: reduce)');
    const finePointerMQ  = window.matchMedia('(hover: hover) and (pointer: fine)');

    // ==========================================
    // JS 1 : SLIDER DE PRIX
    // ==========================================
    const priceSlider       = document.getElementById('priceSlider');
    const priceValueDisplay = document.getElementById('priceValueDisplay');
    const applyPriceBtn     = document.getElementById('applyPrice');

    if (priceSlider && priceValueDisplay) {
        priceSlider.addEventListener('input', function() {
            priceValueDisplay.textContent = this.value + ' €';
        });
        if (applyPriceBtn) {
            applyPriceBtn.addEventListener('click', function() {
                const maxPrice = priceSlider.value;
                if (maxPrice > 0) {
                    const url = new URL(window.location);
                    url.searchParams.set('max_price', maxPrice);
                    url.searchParams.set('page', '1');
                    window.location.href = url.toString();
                }
            });
        }
    }

    // ==========================================
    // JS 1b : BOUTON "FILTRES" (mobile)
    // ==========================================
    const sidebar       = document.getElementById('lmSidebar');
    const filtersToggle = document.getElementById('lmFiltersToggle');
    if (sidebar && filtersToggle) {
        filtersToggle.addEventListener('click', function() {
            const open = sidebar.classList.toggle('is-open');
            this.setAttribute('aria-expanded', open ? 'true' : 'false');
            this.innerHTML = open ? '<i class="fas fa-times"></i> Masquer les filtres'
                                  : '<i class="fas fa-filter"></i> Afficher les filtres';
        });
    }

    // ==========================================
    // JS 2 : EFFETS 3D (cartes produits, mini-cartes, boutons magnétiques)
    // ==========================================
    // Cartes produits : position du curseur → variables CSS --tx/--ty (-1…+1) et --mx/--my (%)
    function initCard(card) {
        if (card.dataset.lmInit) return;
        card.dataset.lmInit = '1';
        let rect = null, frameId = null, px = 0, py = 0;

        function refreshRect() { rect = card.getBoundingClientRect(); }

        function render() {
            frameId = null;
            if (!rect) refreshRect();
            const nx = Math.min(1, Math.max(0, (px - rect.left) / rect.width));
            const ny = Math.min(1, Math.max(0, (py - rect.top)  / rect.height));
            card.style.setProperty('--tx', ((nx - 0.5) * 2).toFixed(3));
            card.style.setProperty('--ty', ((ny - 0.5) * 2).toFixed(3));
            card.style.setProperty('--mx', (nx * 100).toFixed(1) + '%');
            card.style.setProperty('--my', (ny * 100).toFixed(1) + '%');
        }

        function reset() {
            if (frameId) { cancelAnimationFrame(frameId); frameId = null; }
            window.removeEventListener('scroll', refreshRect);
            card.classList.remove('is-tilting');
            ['--tx', '--ty', '--mx', '--my'].forEach(p => card.style.removeProperty(p));
            rect = null;
        }

        card.addEventListener('pointerenter', function(e) {
            if (e.pointerType !== 'mouse' || reduceMotionMQ.matches || !finePointerMQ.matches) return;
            refreshRect();
            card.classList.add('is-tilting');
            window.addEventListener('scroll', refreshRect, { passive: true });
            px = e.clientX; py = e.clientY;
            frameId = requestAnimationFrame(render);
        });
        card.addEventListener('pointermove', function(e) {
            if (!card.classList.contains('is-tilting')) return;
            px = e.clientX; py = e.clientY;
            if (!frameId) frameId = requestAnimationFrame(render);
        });
        card.addEventListener('pointerleave',  reset);
        card.addEventListener('pointercancel', reset);

        // Bouton "Ajouter" de la carte : effet magnétique
        card.querySelectorAll('.lm-btn-cart').forEach(magnetic);
    }
    window.lmInitCard = initCard;

    // Bouton magnétique (souris uniquement) : même principe que le quiz
    function magnetic(el) {
        if (el.dataset.lmMag || reduceMotionMQ.matches || !finePointerMQ.matches) return;
        el.dataset.lmMag = '1';
        el.addEventListener('pointermove', function(e) {
            const r = el.getBoundingClientRect();
            el.style.setProperty('--bx', ((e.clientX - (r.left + r.width / 2)) * 0.12).toFixed(1) + 'px');
            el.style.setProperty('--by', ((e.clientY - (r.top + r.height / 2)) * 0.22).toFixed(1) + 'px');
        });
        el.addEventListener('pointerleave', function() {
            el.style.setProperty('--bx', '0px');
            el.style.setProperty('--by', '0px');
        });
    }

    // Mini-cartes du bandeau : inclinaison + reflet doré (identique au quiz)
    function tilt(el, max) {
        let frame = null;
        el.addEventListener('pointerenter', function() { el.classList.add('is-tilting'); });
        el.addEventListener('pointermove', function(e) {
            const r = el.getBoundingClientRect();
            if (!r.width || !r.height) return;
            const px = (e.clientX - r.left) / r.width;
            const py = (e.clientY - r.top)  / r.height;
            if (frame) cancelAnimationFrame(frame);
            frame = requestAnimationFrame(function() {
                el.style.setProperty('--ry', ((px - 0.5) * 2 * max).toFixed(2) + 'deg');
                el.style.setProperty('--rx', ((0.5 - py) * 2 * max).toFixed(2) + 'deg');
                el.style.setProperty('--mx', (px * 100).toFixed(1) + '%');
                el.style.setProperty('--my', (py * 100).toFixed(1) + '%');
            });
        });
        el.addEventListener('pointerleave', function() {
            el.classList.remove('is-tilting');
            el.style.setProperty('--rx', '0deg');
            el.style.setProperty('--ry', '0deg');
        });
    }

    document.querySelectorAll('.product-card').forEach(initCard);
    document.querySelectorAll('.lm-magnetic').forEach(magnetic);
    if (!reduceMotionMQ.matches && finePointerMQ.matches) {
        document.querySelectorAll('.lm-stat').forEach(function(el) { tilt(el, 8); });
    }

    // Apparition douce des cartes au scroll (uniquement les cartes présentes au chargement)
    if (!reduceMotionMQ.matches && 'IntersectionObserver' in window) {
        const io = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (!entry.isIntersecting) return;
                const card = entry.target;
                io.unobserve(card);
                card.classList.add('is-in');
                // Une fois l'animation finie, on retire les classes : l'effet 3D reprend la main
                setTimeout(function() {
                    card.classList.remove('lm-reveal', 'is-in');
                    card.style.removeProperty('--i');
                }, 1500);
            });
        }, { threshold: 0.08 });
        document.querySelectorAll('.lm-grid .product-card').forEach(function(card, i) {
            card.style.setProperty('--i', i % 6);   // décalage en cascade par rangée
            card.classList.add('lm-reveal');
            io.observe(card);
        });
    }

    // ==========================================
    // JS 2a : AJOUT AU PANIER SANS RECHARGEMENT
    // ==========================================
    // Délégation d'événement : fonctionne aussi pour les cartes ajoutées par "Voir plus".
    // En cas de problème (réseau, réponse inattendue) → repli sur l'envoi classique du formulaire.
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!form.matches || !form.matches('.lm-actions form') || !window.fetch) return;
        const btn = form.querySelector('.lm-btn-cart');
        if (!btn || btn.disabled) return;
        e.preventDefault();

        const productId    = form.querySelector('[name="product_id"]').value;
        const formatId     = parseInt(form.dataset.formatId, 10) || 0;
        const originalHTML = btn.innerHTML;

        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Ajout...</span>';
        btn.disabled  = true;
        btn.classList.add('is-busy');

        let body = 'product_id=' + encodeURIComponent(productId) + '&quantity=1';
        if (formatId) body += '&format_id=' + encodeURIComponent(formatId);

        fetch('ajouter_au_panier.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.classList.remove('is-busy');
            if (data.success) {
                btn.innerHTML = '<i class="fas fa-check"></i> <span>Ajouté !</span>';
                btn.classList.add('is-success');
                document.querySelectorAll('.cart-count, #cartCount').forEach(function(c) {
                    c.textContent = (parseInt(c.textContent) || 0) + 1;
                    c.style.transform = 'scale(1.4)';
                    setTimeout(function() { c.style.transform = 'scale(1)'; }, 300);
                });
                setTimeout(function() {
                    btn.innerHTML = originalHTML;
                    btn.classList.remove('is-success');
                    btn.disabled = false;
                }, 2000);
            } else {
                btn.innerHTML = originalHTML;
                btn.disabled  = false;
                if (data.message && data.message.includes('connecter')) {
                    if (confirm('Vous devez être connecté. Aller à la page de connexion ?')) {
                        window.location.href = 'connexion.php';
                    }
                } else {
                    alert(data.message || 'Erreur lors de l\'ajout au panier');
                }
            }
        })
        .catch(function() { form.submit(); });   // repli : envoi classique (POST + redirection)
    });

    // ==========================================
    // JS 2b : BOUTON "VOIR PLUS DE PARFUMS"
    // ==========================================
    const loadMoreWrap = document.getElementById('loadMoreWrap');
    const loadMoreBtn  = document.getElementById('loadMoreBtn');
    const productGrid  = document.querySelector('.lm-grid');

    if (loadMoreWrap && loadMoreBtn && productGrid) {
        let nextPage       = parseInt(loadMoreWrap.dataset.nextPage, 10);
        const lastPage     = parseInt(loadMoreWrap.dataset.totalPages, 10);
        let shown          = parseInt(loadMoreWrap.dataset.shown, 10);
        const shownCountEl = document.getElementById('shownCount');
        const defaultLabel = loadMoreBtn.textContent;
        let loading        = false;

        loadMoreBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (loading) return;
            loading = true;
            loadMoreBtn.classList.add('is-loading');
            loadMoreBtn.setAttribute('aria-busy', 'true');
            loadMoreBtn.textContent = 'Chargement…';

            const url = new URL(window.location.href);
            url.searchParams.set('page', nextPage);

            fetch(url.toString(), { credentials: 'same-origin' })
                .then(function(response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.text();
                })
                .then(function(html) {
                    const doc      = new DOMParser().parseFromString(html, 'text/html');
                    const newCards = doc.querySelectorAll('.lm-grid .product-card');
                    if (!newCards.length) throw new Error('Aucune carte reçue');

                    let firstNew = null;
                    newCards.forEach(function(c) {
                        const card = document.importNode(c, true);
                        card.classList.remove('lm-reveal', 'is-in');
                        card.classList.add('lm-enter');
                        productGrid.appendChild(card);
                        window.lmInitCard(card);
                        card.querySelectorAll('.btn-wishlist').forEach(bindWishlistButton);
                        if (!firstNew) firstNew = card;
                    });

                    shown += newCards.length;
                    if (shownCountEl) shownCountEl.textContent = shown;
                    nextPage++;

                    if (nextPage > lastPage) {
                        loadMoreBtn.remove();
                        const link = firstNew.querySelector('a');
                        if (link) link.focus({ preventScroll: true });
                    } else {
                        const nu = new URL(window.location.href);
                        nu.searchParams.set('page', nextPage);
                        loadMoreBtn.setAttribute('href', nu.search);
                    }
                    loadWishlistState();
                })
                .catch(function(err) {
                    console.error('Erreur "Voir plus" :', err);
                    window.location.href = loadMoreBtn.getAttribute('href');
                })
                .finally(function() {
                    loading = false;
                    loadMoreBtn.classList.remove('is-loading');
                    loadMoreBtn.removeAttribute('aria-busy');
                    loadMoreBtn.textContent = defaultLabel;
                });
        });
    }
});

// ==========================================
// JS 3 : WISHLIST (FAVORIS) — AJAX
// ==========================================
(function injectWishlistStyles() {
    const style = document.createElement('style');
    style.textContent = `
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
        @keyframes floatUpFade {
            0%   { opacity: 1; transform: translateY(0) scale(1); }
            70%  { opacity: 1; transform: translateY(-30px) scale(1.1); }
            100% { opacity: 0; transform: translateY(-50px) scale(0.8); }
        }
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
        .nav-heart-added   { animation: navHeartBeat 0.6s ease forwards !important; color: #ef4444 !important; }
        .nav-heart-removed { animation: navHeartBeatRemove 0.4s ease forwards !important; }
        .nav-fav-glow      { animation: navFavGlow 0.8s ease forwards !important; }
        .count-bounce      { animation: countBounce 0.5s ease forwards !important; }
        .wishlist-float-badge {
            position: fixed; font-size: 0.85em; font-weight: 700; color: #ef4444;
            pointer-events: none; z-index: 99999; animation: floatUpFade 1s ease forwards; white-space: nowrap;
        }
    `;
    document.head.appendChild(style);
})();

// Animation de l'icône cœur dans la navbar (action : 'added' ou 'removed')
function animateNavFavoris(action, count) {
    const navFavLink    = document.querySelector('.wishlist-btn');
    const navHeartIcon  = navFavLink ? navFavLink.querySelector('i') : null;
    const wishlistCount = document.querySelector('.wishlist-count');

    if (navFavLink) {
        navFavLink.classList.remove('nav-fav-glow');
        void navFavLink.offsetWidth; // force un "reflow" pour relancer l'animation
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

        if (navFavLink) {
            const rect  = navFavLink.getBoundingClientRect();
            const badge = document.createElement('span');
            badge.className   = 'wishlist-float-badge';
            badge.textContent = action === 'added' ? '+1 ❤' : '-1 ❤';
            badge.style.left  = (rect.left + rect.width / 2) + 'px';
            badge.style.top   = (rect.top - 5 + window.scrollY) + 'px';
            document.body.appendChild(badge);
            setTimeout(() => badge.remove(), 1100);
        }
    }

    if (wishlistCount && count !== undefined) {
        wishlistCount.textContent = count;
        wishlistCount.classList.remove('count-bounce');
        void wishlistCount.offsetWidth;
        wishlistCount.classList.add('count-bounce');
        setTimeout(() => wishlistCount.classList.remove('count-bounce'), 600);
    }
}

// État initial : colorie les cœurs des produits déjà en favoris (get-wishlist.php → JSON)
loadWishlistState();

function loadWishlistState() {
    fetch('get-wishlist.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.favorites) {
                document.querySelectorAll('.btn-wishlist').forEach(button => {
                    const productId = parseInt(button.getAttribute('data-product-id'));
                    const icon      = button.querySelector('i');
                    if (data.favorites.includes(productId)) {
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                        icon.style.color = '#ef4444';
                    } else {
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                        icon.style.color = '';
                    }
                });
                const wishlistCount = document.querySelector('.wishlist-count');
                if (wishlistCount && data.count !== undefined) {
                    wishlistCount.textContent = data.count;
                }
            }
        })
        .catch(err => console.error('Erreur chargement wishlist:', err));
}

// Clic sur un cœur → toggle_wishlist.php (aussi appelée pour les cartes ajoutées par "Voir plus")
function bindWishlistButton(button) {
    if (button.dataset.lmBound) return;
    button.dataset.lmBound = '1';
    button.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation(); // sinon le clic ouvrirait la fiche produit

        const productId = this.getAttribute('data-product-id');
        const icon      = this.querySelector('i');
        if (!productId) return;

        this.style.pointerEvents = 'none';

        fetch('toggle_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'product_id=' + encodeURIComponent(productId)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.action === 'added') {
                    icon.classList.remove('far');
                    icon.classList.add('fas');
                    icon.style.color     = '#ef4444';
                    this.style.transform = 'scale(1.3)';
                    setTimeout(() => this.style.transform = 'scale(1)', 300);
                } else if (data.action === 'removed') {
                    icon.classList.remove('fas');
                    icon.classList.add('far');
                    icon.style.color     = '';
                    this.style.transform = 'scale(0.8)';
                    setTimeout(() => this.style.transform = 'scale(1)', 300);
                }
                animateNavFavoris(data.action, data.wishlist_count);
            } else {
                if (data.redirect) {
                    alert('Veuillez vous connecter pour ajouter des favoris');
                    setTimeout(() => window.location.href = data.redirect, 1000);
                } else {
                    alert(data.message || 'Erreur lors de l\'ajout aux favoris');
                }
            }
            this.style.pointerEvents = 'auto';
        })
        .catch(err => {
            console.error('❌ Erreur réseau wishlist:', err);
            alert('Erreur réseau. Veuillez réessayer.');
            this.style.pointerEvents = 'auto';
        });
    });
}
document.querySelectorAll('.btn-wishlist').forEach(bindWishlistButton);
</script>

<?php include '../includes/footer.php'; ?>