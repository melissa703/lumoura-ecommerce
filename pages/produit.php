<?php
// Page détail d'un produit
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Constante livraison gratuite (cohérente avec panier.php et commande.php)
if (!defined('LIVRAISON_GRATUITE_SEUIL')) {
    define('LIVRAISON_GRATUITE_SEUIL', 150);
}

// Vérifier si un ID de produit est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: catalogue.php');
    exit();
}

$productId = intval($_GET['id']);

// Récupérer les informations du produit
try {
    $query = "SELECT * FROM produits WHERE id_produit = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        header('Location: catalogue.php');
        exit();
    }

    // Avis du produit (statut 'approuve' uniquement)
    $reviewsQuery = "SELECT a.*, u.nom, u.prenom FROM avis a 
                    JOIN utilisateurs u ON a.id_utilisateur = u.id_utilisateur 
                    WHERE a.id_produit = ? AND a.statut = 'approuve' 
                    ORDER BY a.date_avis DESC LIMIT 5";
    $reviewsStmt = $pdo->prepare($reviewsQuery);
    $reviewsStmt->execute([$productId]);
    $reviews = $reviewsStmt->fetchAll();

    // Note moyenne
    $avgRatingQuery = "SELECT AVG(note) as avg_rating, COUNT(*) as review_count FROM avis 
                      WHERE id_produit = ? AND statut = 'approuve'";
    $avgStmt = $pdo->prepare($avgRatingQuery);
    $avgStmt->execute([$productId]);
    $ratingData = $avgStmt->fetch();
    $avgRating   = $ratingData['avg_rating'] ? round($ratingData['avg_rating'], 1) : 0;
    $reviewCount = (int) $ratingData['review_count'];

    // L'utilisateur connecté a-t-il déjà laissé un avis ? (et est-il encore en attente ?)
    $already_reviewed = false;
    $reviewPending    = false;
    if (isLoggedIn()) {
        $check_avis = $pdo->prepare("SELECT statut FROM avis WHERE id_utilisateur = ? AND id_produit = ?");
        $check_avis->execute([$_SESSION['user_id'], $productId]);
        $myReview = $check_avis->fetch();
        if ($myReview) {
            $already_reviewed = true;
            $reviewPending    = ($myReview['statut'] !== 'approuve');
        }
    }

} catch (PDOException $e) {
    die('Erreur lors du chargement du produit');
}

// FORMATS du produit (30 / 50 / 100 ml) et leurs prix
try {
    $formatsStmt = $pdo->prepare("SELECT id_format, volume_ml, prix FROM produit_formats WHERE id_produit = ? ORDER BY volume_ml ASC");
    $formatsStmt->execute([$productId]);
    $formats = $formatsStmt->fetchAll();
} catch (PDOException $e) {
    $formats = [];
}

// Format sélectionné par défaut : le moins cher (le même que le "Dès X €" du catalogue)
$selectedFormat = null;
foreach ($formats as $f) {
    if ($selectedFormat === null || $f['prix'] < $selectedFormat['prix']) {
        $selectedFormat = $f;
    }
}

// Produits similaires (avec le prix du format le moins cher, comme le catalogue)
try {
    $similarQuery = "SELECT p.*,
                            (SELECT MIN(f.prix) FROM produit_formats f WHERE f.id_produit = p.id_produit) AS prix_min
                     FROM produits p
                     WHERE p.id_categorie = ? AND p.id_produit != ?
                     ORDER BY RAND() LIMIT 4";
    $similarStmt = $pdo->prepare($similarQuery);
    $similarStmt->execute([$product['id_categorie'], $productId]);
    $similarProducts = $similarStmt->fetchAll();
} catch (PDOException $e) {
    $similarProducts = [];
}

$pageTitle = $product['nom'] . " - Lumoura";

// Helpers d'affichage propres à cette page
$esc = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$initial = function ($text) {
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    return function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8')
        : strtoupper(substr($text, 0, 1));
};
// Étoiles : toujours exactement 5 icônes (pleines / vides)
$stars = function ($n) {
    $n = max(0, min(5, (int) round((float) $n)));
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<i class="' . ($i <= $n ? 'fas' : 'far') . ' fa-star"></i>';
    }
    return $html;
};

$stock          = (int) $product['stock'];
$alertThreshold = (int) $product['seuil_alerte'];
$avgRatingLabel = $reviewCount > 0 ? number_format((float) $avgRating, 1, ',', '') : '';

include '../includes/header.php';
?>

<style>
.lm-produit {
    --lm-cream: #FBF8F1;
    --lm-cream-2: #F5F0E6;
    --lm-paper: #FFFDF9;
    --lm-beige: #E9DFCB;
    --lm-beige-d: #D9CBB0;
    --lm-line: #E6DCC8;
    --lm-choco: #2C1810;
    --lm-choco-2: #3D2318;
    --lm-text: #4A3C33;
    --lm-muted: #6B5B50;
    --lm-gold: #D4AF37;
    --lm-gold-d: #A07C17;
    --lm-gold-text: #7D6216;
    --lm-glow: rgba(212, 175, 90, .45);
    --lm-ease: cubic-bezier(.22, 1, .36, 1);
    --lm-serif: "Playfair Display", "Cormorant Garamond", Georgia, "Times New Roman", serif;
    --lm-radius: 14px;

    box-sizing: border-box;
    width: 100%;
    max-width: 1180px;
    margin: 0 auto;
    padding: 28px clamp(12px, 2.5vw, 24px) 72px;
    color: var(--lm-choco);
    line-height: 1.5;
}
.lm-produit *, .lm-produit *::before, .lm-produit *::after { box-sizing: border-box; }
/* :where() = spécificité 0 → n'écrase plus les marges de .lm-title, .lm-subtitle, etc. */
.lm-produit :where(h1, h2, h3, p, dl, dd, ul) { margin: 0; padding: 0; }
.lm-produit [id] { scroll-margin-top: 96px; }
@media (prefers-reduced-motion: no-preference) { html { scroll-behavior: smooth; } }

/* =====================================================================
   EFFET 3D (même principe que le quiz : inclinaison + reflet doré)
   ===================================================================== */
.lm-tilt {
    --rx: 0deg; --ry: 0deg; --mx: 50%; --my: 50%; --s: 1;
    transform: perspective(1000px) rotateX(var(--rx)) rotateY(var(--ry)) scale(var(--s));
    transition: transform .7s var(--lm-ease), box-shadow .6s var(--lm-ease), border-color .3s;
    will-change: transform;
}
.lm-tilt.is-tilting {
    --s: 1.02;
    transition: transform .12s ease-out, box-shadow .6s var(--lm-ease), border-color .3s;
    border-color: var(--lm-gold);
    box-shadow: 0 36px 64px -30px rgba(26, 14, 8, .6), 0 0 0 1px rgba(212, 175, 90, .45), 0 0 40px -10px var(--lm-glow);
}
.lm-tilt::after { /* reflet doré qui suit le curseur */
    content: "";
    position: absolute; inset: 0;
    pointer-events: none; border-radius: inherit;
    background: radial-gradient(380px circle at var(--mx) var(--my), rgba(255, 244, 214, .30), transparent 55%);
    opacity: 0; transition: opacity .4s ease;
}
.lm-tilt.is-tilting::after { opacity: 1; }

/* Apparition douce au scroll (uniquement si le JS est actif) */
.lm-js .lm-reveal { opacity: 0; transform: translateY(34px); transition: opacity .9s var(--lm-ease), transform .9s var(--lm-ease); }
.lm-js .lm-reveal.is-in { opacity: 1; transform: none; }
.lm-js .lm-similar .product-card.lm-reveal { transition-delay: calc(var(--i, 0) * 110ms); }

/* ---------- Mise en page principale ---------- */
.lm-product {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: clamp(28px, 5vw, 64px);
    align-items: start;
}
.lm-gallery { position: sticky; top: 96px; perspective: 1400px; }
.lm-gallery__frame {
    position: relative;
    aspect-ratio: 4 / 5;
    max-height: 640px;
    width: 100%;
    overflow: hidden;
    border-radius: var(--lm-radius);
    border: 1px solid var(--lm-line);
    background: linear-gradient(160deg, var(--lm-cream-2), var(--lm-beige));
    box-shadow: 0 22px 44px -28px rgba(44, 24, 16, .45);
}
/* cover : la photo remplit tout le cadre, sans bandes blanches */
.lm-gallery__frame img { display: block; width: 100%; height: 100%; object-fit: cover; object-position: center; transition: transform 1.3s var(--lm-ease); }
.lm-gallery__frame:hover img { transform: scale(1.06); }

/* ---------- Bloc d'informations ---------- */
.lm-brand {
    display: inline-flex; align-items: center; gap: 10px;
    font-size: .76rem; font-weight: 600; letter-spacing: .22em; text-transform: uppercase;
    color: var(--lm-gold-text);
}
.lm-brand::before { content: ""; width: 26px; height: 1px; background: var(--lm-gold); transition: width .6s var(--lm-ease); }
.lm-info:hover .lm-brand::before { width: 46px; }
.lm-title {
    margin: 10px 0 14px;
    font-family: var(--lm-serif); font-weight: 600; letter-spacing: -.005em;
    font-size: clamp(1.9rem, 3.2vw, 2.65rem); line-height: 1.14; color: var(--lm-choco);
}
.lm-rating { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 10px; font-size: .9rem; color: var(--lm-muted); }
.lm-rating a { color: inherit; text-decoration: none; border-bottom: 1px solid var(--lm-beige-d); transition: color .2s, border-color .2s; }
.lm-rating a:hover { color: var(--lm-choco); border-bottom-color: var(--lm-gold); }

.lm-stars { display: inline-flex; align-items: center; gap: 2px; line-height: 1; color: var(--lm-gold); font-size: .95rem; }
.lm-stars i { color: var(--lm-gold); font-size: inherit; }
.lm-stars .far, .lm-stars .fa-regular { color: #D8CCB2; }

.lm-price {
    display: flex; align-items: baseline; flex-wrap: wrap; gap: 6px 14px;
    margin: 20px 0 22px; padding-bottom: 22px; border-bottom: 1px solid var(--lm-line);
}
.lm-price__current { font-family: var(--lm-serif); font-size: 2.1rem; font-weight: 600; line-height: 1.1; color: var(--lm-choco); display: inline-block; }
.lm-price__current.is-bump { animation: lmBump .45s var(--lm-ease); }
@keyframes lmBump { 40% { transform: scale(1.08); color: var(--lm-gold-d); } }
.lm-price--promo .lm-price__current { color: var(--lm-gold-d); }
.lm-price__old { font-size: 1.05rem; color: #8F8176; text-decoration: line-through; }
.lm-badge-promo {
    align-self: center; padding: 4px 11px; border-radius: 999px;
    background: var(--lm-gold); color: var(--lm-choco);
    font-size: .76rem; font-weight: 700; letter-spacing: .04em;
}

.lm-field { margin-bottom: 20px; }
.lm-label { margin-bottom: 10px; font-size: .78rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--lm-muted); }

/* ---------- Formats ---------- */
.lm-formats { display: flex; flex-wrap: wrap; gap: 10px; }
.lm-produit .lm-format {
    appearance: none; -webkit-appearance: none;
    min-width: 88px; padding: 11px 22px; margin: 0;
    border: 1px solid var(--lm-beige-d); border-radius: 999px;
    background: var(--lm-paper); color: var(--lm-choco);
    font: inherit; font-size: .95rem; font-weight: 500; line-height: 1.2; text-align: center; cursor: pointer;
    transition: transform .35s var(--lm-ease), border-color .2s, background-color .2s, color .2s, box-shadow .35s var(--lm-ease);
}
.lm-produit .lm-format:hover { transform: translateY(-3px); border-color: var(--lm-gold); background: var(--lm-cream-2); box-shadow: 0 12px 20px -14px rgba(44, 24, 16, .5); }
.lm-produit .lm-format:active { transform: translateY(-1px) scale(.96); }
.lm-produit .lm-format.active {
    background: var(--lm-choco); border-color: var(--lm-choco); color: var(--lm-cream); font-weight: 600;
    box-shadow: 0 0 0 3px rgba(212, 175, 55, .35);
}
.lm-produit .lm-format.active:hover { background: var(--lm-choco-2); box-shadow: 0 0 0 4px rgba(212, 175, 55, .45), 0 14px 22px -14px rgba(44, 24, 16, .7); }
.lm-produit .lm-format:focus-visible { outline: 2px solid var(--lm-gold); outline-offset: 3px; }

/* ---------- Quantité + disponibilité ---------- */
.lm-qtyrow { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px 18px; margin-bottom: 4px; }
.lm-qty { display: flex; align-items: center; gap: 14px; }
.lm-qty .lm-label { margin: 0; }
.lm-stepper {
    display: inline-flex; align-items: center;
    border: 1px solid var(--lm-beige-d); border-radius: 999px; background: var(--lm-paper); overflow: hidden;
    transition: border-color .2s, box-shadow .35s var(--lm-ease);
}
.lm-stepper:hover, .lm-stepper:focus-within { border-color: var(--lm-gold); box-shadow: 0 0 0 4px rgba(212, 175, 55, .15); }
.lm-produit .lm-stepper__btn {
    appearance: none; -webkit-appearance: none;
    display: inline-flex; align-items: center; justify-content: center;
    width: 42px; height: 42px; min-width: 0; margin: 0; padding: 0;
    border: 0; border-radius: 0; background: transparent; box-shadow: none;
    color: var(--lm-choco); font: inherit; font-size: 1.15rem; line-height: 1; cursor: pointer;
    transition: background-color .2s, color .2s, transform .2s;
}
.lm-produit .lm-stepper__btn:hover { background: var(--lm-gold); color: #fff; }
.lm-produit .lm-stepper__btn:active { transform: scale(.85); }
.lm-produit .lm-stepper__btn:focus-visible { outline: 2px solid var(--lm-gold); outline-offset: -3px; }
.lm-produit .lm-stepper__input {
    appearance: textfield; -moz-appearance: textfield;
    width: 48px; height: 42px; margin: 0; padding: 0;
    border: 0; border-radius: 0; background: transparent; box-shadow: none;
    color: var(--lm-choco); font: inherit; font-weight: 600; text-align: center;
}
.lm-produit .lm-stepper__input::-webkit-outer-spin-button,
.lm-produit .lm-stepper__input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.lm-produit .lm-stepper__input:focus-visible { outline: 2px solid var(--lm-gold); outline-offset: -3px; }

.lm-stock {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 6px 13px; border-radius: 999px; font-size: .82rem; font-weight: 600; line-height: 1.2;
}
.lm-stock::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
.lm-stock--ok  { background: #E8F2EA; color: #2F6B45; }
.lm-stock--low { background: #FBEFD9; color: #8A5A00; }
.lm-stock--low::before { animation: lmPulse 1.8s ease-in-out infinite; }
@keyframes lmPulse { 50% { box-shadow: 0 0 0 5px rgba(138, 90, 0, .18); transform: scale(1.15); } }
.lm-stock--out { background: #F8E4E1; color: #A3322B; }

/* ---------- Boutons d'action (balayage doré + magnétisme) ---------- */
.lm-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; margin-top: 18px; }
.lm-produit .lm-btn {
    appearance: none; -webkit-appearance: none;
    position: relative; overflow: hidden;
    display: inline-flex; align-items: center; justify-content: center; gap: 10px;
    width: 100%; min-height: 54px; margin: 0; padding: 14px 20px;
    border: 1px solid transparent; border-radius: 10px;
    font: inherit; font-size: .82rem; font-weight: 600; letter-spacing: .12em; text-transform: uppercase;
    line-height: 1.25; text-align: center; text-decoration: none; cursor: pointer;
    transform: translate(var(--bx, 0px), var(--by, 0px));
    transition: transform .25s ease-out, box-shadow .4s var(--lm-ease), background-color .2s, background-position .3s, color .2s;
}
.lm-produit .lm-btn::before { /* reflet qui traverse le bouton */
    content: ""; position: absolute; inset: 0 auto 0 -60%; width: 45%;
    background: linear-gradient(105deg, transparent, rgba(255, 255, 255, .45), transparent);
    transform: skewX(-20deg); transition: left .8s var(--lm-ease); pointer-events: none;
}
.lm-produit .lm-btn:hover:not(:disabled)::before { left: 120%; }
.lm-produit .lm-btn:active:not(:disabled) { transform: translate(var(--bx, 0px), var(--by, 0px)) scale(.97); }
.lm-produit .lm-btn i { font-size: 1rem; transition: transform .4s var(--lm-ease); }
.lm-produit .lm-btn:hover:not(:disabled) i { transform: scale(1.2) rotate(-8deg); }
.lm-produit .lm-btn:focus-visible { outline: 2px solid var(--lm-gold); outline-offset: 3px; }
.lm-produit .lm-btn:disabled { cursor: progress; opacity: .7; }
.lm-produit .lm-btn--dark { background: var(--lm-choco); border-color: var(--lm-choco); color: var(--lm-cream); }
.lm-produit .lm-btn--dark i { color: var(--lm-gold); }
.lm-produit .lm-btn--dark:hover:not(:disabled) { background: var(--lm-choco-2); box-shadow: 0 18px 30px -14px rgba(44, 24, 16, .75), 0 0 0 5px rgba(212, 175, 55, .18); }
.lm-produit .lm-btn--gold {
    background: linear-gradient(135deg, #E6CA63 0%, #D4AF37 55%, #BF9A2B 100%); background-size: 140% 100%;
    border-color: #B8942A; color: var(--lm-choco);
    box-shadow: 0 10px 24px -12px var(--lm-glow), inset 0 1px 0 rgba(255, 255, 255, .35);
}
.lm-produit .lm-btn--gold:hover:not(:disabled) { background-position: 100% 0; box-shadow: 0 18px 32px -14px rgba(160, 124, 23, .9), 0 0 0 5px rgba(212, 175, 55, .22); }
.lm-produit .lm-btn--outline { background: transparent; border-color: var(--lm-choco); color: var(--lm-choco); }
.lm-produit .lm-btn--outline:hover { background: var(--lm-choco); color: var(--lm-cream); }
.lm-produit .lm-btn.is-success, .lm-produit .lm-btn.is-success:hover:not(:disabled) { background: #3E6B4B; border-color: #3E6B4B; color: #fff; }
.lm-produit .lm-btn.is-success i { color: #fff; }
.lm-produit .lm-btn.is-success:disabled { opacity: 1; cursor: default; }
.lm-btn--disabled, .lm-produit .lm-btn--disabled:disabled { background: #E4DDD0; border-color: #E4DDD0; color: #8F8176; cursor: not-allowed; opacity: 1; }
.lm-unavailable-note { margin-top: 10px; text-align: center; font-size: .9rem; color: #A3322B; }

/* ---------- Réassurance ---------- */
.lm-assurances {
    display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px;
    margin-top: 24px; padding-top: 22px; border-top: 1px solid var(--lm-line);
}
.lm-assurance { display: flex; align-items: center; gap: 10px; font-size: .84rem; line-height: 1.3; color: var(--lm-muted); transition: color .25s, transform .4s var(--lm-ease); }
.lm-assurance:hover { color: var(--lm-choco); transform: translateY(-3px); }
.lm-assurance i {
    flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--lm-cream-2); border: 1px solid var(--lm-line); color: var(--lm-gold-text); font-size: .9rem;
    transition: transform .45s var(--lm-ease), background-color .25s, color .25s, box-shadow .4s var(--lm-ease);
}
.lm-assurance:hover i { transform: scale(1.12) rotate(-6deg); background: var(--lm-gold); color: #fff; box-shadow: 0 0 0 7px rgba(212, 175, 90, .16); }

/* ---------- Description + caractéristiques ---------- */
.lm-details { margin-top: 28px; }
.lm-subtitle {
    display: flex; align-items: center; gap: 14px; margin-bottom: 12px;
    font-family: var(--lm-serif); font-size: 1.2rem; font-weight: 600; color: var(--lm-choco);
}
.lm-subtitle::after { content: ""; flex: 1; height: 1px; background: var(--lm-line); }
.lm-description { font-size: .98rem; line-height: 1.75; color: var(--lm-text); }
.lm-specs {
    display: grid; grid-template-columns: repeat(3, minmax(0, 1fr));
    margin-top: 22px; border: 1px solid var(--lm-line); border-radius: var(--lm-radius); background: var(--lm-cream-2); overflow: hidden;
}
.lm-spec { display: flex; flex-direction: column; gap: 3px; padding: 16px 18px; transition: background-color .35s, transform .45s var(--lm-ease); }
.lm-spec + .lm-spec { border-left: 1px solid var(--lm-line); }
.lm-spec:hover { background: var(--lm-paper); transform: translateY(-3px); }
.lm-spec i { margin-bottom: 4px; font-size: 1rem; color: var(--lm-gold-text); transition: transform .45s var(--lm-ease), color .25s; }
.lm-spec:hover i { transform: scale(1.25) rotate(-8deg); color: var(--lm-gold); }
.lm-spec dt { font-size: .72rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--lm-muted); }
.lm-spec dd { font-size: .98rem; font-weight: 500; color: var(--lm-choco); }

/* ---------- Avis clients ---------- */
.lm-reviews { margin-top: 72px; }
.lm-section-title {
    position: relative; margin: 0 0 34px; padding-bottom: 16px; text-align: center;
    font-family: var(--lm-serif); font-size: clamp(1.6rem, 2.6vw, 2rem); font-weight: 600; color: var(--lm-choco);
}
.lm-section-title::after { content: ""; position: absolute; left: 50%; bottom: 0; width: 56px; height: 2px; transform: translateX(-50%); background: var(--lm-gold); transition: width .7s var(--lm-ease); }
.lm-section-title:hover::after, .lm-reveal.is-in .lm-section-title::after { width: 96px; }
.lm-reviews__grid { display: grid; grid-template-columns: 290px minmax(0, 1fr); gap: 28px; align-items: start; perspective: 1400px; }

.lm-summary {
    position: sticky; top: 96px;
    display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 28px 24px; text-align: center;
    border: 1px solid var(--lm-line); border-radius: var(--lm-radius); background: var(--lm-cream-2);
}
.lm-summary__score { font-family: var(--lm-serif); font-size: 3.4rem; font-weight: 600; line-height: 1; color: var(--lm-choco); }
.lm-summary__score small { margin-left: 2px; font-size: 1.15rem; font-weight: 500; color: var(--lm-muted); }
.lm-summary .lm-stars { font-size: 1.05rem; }
.lm-summary__count { font-size: .9rem; color: var(--lm-muted); }
.lm-summary .lm-btn { margin-top: 10px; min-height: 48px; }
.lm-login-link { margin-top: 8px; font-size: .88rem; color: var(--lm-muted); }
.lm-login-link a { color: var(--lm-gold-text); font-weight: 600; text-decoration: none; border-bottom: 1px solid var(--lm-gold); transition: color .2s; }
.lm-login-link a:hover { color: var(--lm-choco); }

.lm-reviews__list { border: 1px solid var(--lm-line); border-radius: var(--lm-radius); background: var(--lm-paper); overflow: hidden; }
.lm-review { display: grid; grid-template-columns: 42px minmax(0, 1fr); gap: 4px 14px; padding: 18px 22px; transition: background-color .35s, padding-left .45s var(--lm-ease); }
.lm-review:hover { background: var(--lm-cream-2); padding-left: 30px; }
.lm-review + .lm-review { border-top: 1px solid var(--lm-line); }
.lm-review__avatar {
    grid-row: 1 / span 2; display: flex; align-items: center; justify-content: center;
    width: 42px; height: 42px; border-radius: 50%;
    background: var(--lm-beige); color: var(--lm-gold-text); font-family: var(--lm-serif); font-size: 1.05rem; font-weight: 600;
    transition: transform .45s var(--lm-ease), background-color .3s, color .3s;
}
.lm-review:hover .lm-review__avatar { transform: scale(1.12) rotate(-6deg); background: var(--lm-gold); color: #fff; }
.lm-review__head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
.lm-review__name { font-weight: 600; color: var(--lm-choco); }
.lm-review__date { flex: 0 0 auto; font-size: .82rem; color: #8F8176; }
.lm-review .lm-stars { grid-column: 2; font-size: .8rem; }
.lm-review__text { grid-column: 2; margin-top: 4px; font-size: .95rem; line-height: 1.65; color: var(--lm-text); overflow-wrap: anywhere; }
.lm-empty { padding: 34px 20px; text-align: center; color: var(--lm-muted); }
.lm-empty i { display: block; margin-bottom: 10px; font-size: 1.5rem; color: var(--lm-gold); }

/* ---------- Formulaire d'avis ---------- */
.lm-form, .lm-thanks { margin-top: 22px; padding: 26px; border: 1px solid var(--lm-line); border-radius: var(--lm-radius); background: var(--lm-cream-2); }
.lm-reviews__list + .lm-form, .lm-reviews__list + .lm-thanks { margin-top: 22px; }
.lm-form h3, .lm-thanks h3 { margin-bottom: 16px; font-family: var(--lm-serif); font-size: 1.25rem; font-weight: 600; color: var(--lm-choco); }
.lm-thanks { padding: 20px 24px; text-align: center; background: #F0F6F1; border-color: #CFE3D4; }
.lm-thanks i { display: block; margin-bottom: 8px; font-size: 1.6rem; color: #2F6B45; }
.lm-thanks--pending { background: #FBF4E4; border-color: #EBD9A6; }
.lm-thanks--pending i, .lm-thanks--pending h3 { color: #8A5A00; }
.lm-thanks h3 { margin-bottom: 4px; font-size: 1.05rem; color: #2F6B45; }
.lm-thanks--pending h3 { color: #8A5A00; }
.lm-thanks p { font-size: .88rem; color: var(--lm-muted); }
.lm-alert { margin-bottom: 18px; padding: 14px 16px; border-radius: 10px; border-left: 4px solid; font-size: .92rem; }
.lm-alert--ok  { background: #E8F2EA; color: #2F6B45; border-color: #4C9A68; font-weight: 500; }
.lm-alert--err { background: #FBE9E7; color: #A3322B; border-color: #D9534F; }
.lm-alert--err p + p { margin-top: 4px; }
.lm-stars-input { display: inline-flex; gap: 4px; }
.lm-produit .lm-star {
    appearance: none; -webkit-appearance: none; margin: 0; padding: 2px; border: 0; background: transparent; cursor: pointer;
    font-size: 1.6rem; line-height: 1; transition: transform .3s var(--lm-ease);
}
.lm-produit .lm-star i { color: #CDBF9F; transition: color .15s, filter .25s; }
.lm-produit .lm-star.is-on i { color: var(--lm-gold); filter: drop-shadow(0 2px 6px rgba(212, 175, 55, .55)); }
.lm-produit .lm-star:hover { transform: scale(1.25) rotate(-8deg); }
.lm-produit .lm-star:focus-visible { outline: 2px solid var(--lm-gold); outline-offset: 2px; border-radius: 6px; }
.lm-form__row { margin-bottom: 18px; }
.lm-form__row > label { display: block; margin-bottom: 8px; font-size: .9rem; font-weight: 600; color: var(--lm-choco); }
.lm-form__row > label span { font-weight: 400; color: var(--lm-muted); font-size: .85em; }
.lm-produit .lm-textarea {
    display: block; width: 100%; min-height: 120px; margin: 0; padding: 14px 16px; resize: vertical;
    border: 1px solid var(--lm-beige-d); border-radius: 10px; background: var(--lm-paper); color: var(--lm-choco);
    font: inherit; font-size: .95rem; line-height: 1.6; transition: border-color .2s, box-shadow .35s var(--lm-ease);
}
.lm-produit .lm-textarea:hover { border-color: var(--lm-gold); }
.lm-produit .lm-textarea:focus { outline: none; border-color: var(--lm-gold); box-shadow: 0 0 0 4px rgba(212, 175, 55, .22); }
.lm-counter { margin-top: 6px; text-align: right; font-size: .8rem; color: var(--lm-muted); }
.lm-counter.is-warn { color: #A3322B; }
.lm-form .lm-btn { width: auto; min-width: 220px; }

/* ---------- Produits similaires ---------- */
.lm-similar { margin-top: 72px; }
.lm-similar .products-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 24px;
    perspective: 1400px;
}
.lm-similar .product-card {
    --rx: 0deg; --ry: 0deg; --mx: 50%; --my: 50%; --s: 1;
    position: relative; overflow: hidden; width: 100%; max-width: none; margin: 0;
    background: #fff; border: 1px solid var(--lm-line); border-radius: var(--lm-radius);
    box-shadow: 0 16px 34px -26px rgba(26, 14, 8, .5);
    transform: perspective(1000px) rotateX(var(--rx)) rotateY(var(--ry)) scale(var(--s));
    transition: transform .7s var(--lm-ease), box-shadow .6s var(--lm-ease), border-color .3s;
    will-change: transform;
}
.lm-similar .product-card.is-tilting {
    --s: 1.035;
    transition: transform .12s ease-out, box-shadow .6s var(--lm-ease), border-color .3s;
    border-color: var(--lm-gold);
    box-shadow: 0 36px 60px -28px rgba(26, 14, 8, .65), 0 0 36px -10px var(--lm-glow);
}
.lm-similar .product-card::after {
    content: ""; position: absolute; inset: 0; pointer-events: none; border-radius: inherit;
    background: radial-gradient(300px circle at var(--mx) var(--my), rgba(255, 244, 214, .32), transparent 55%);
    opacity: 0; transition: opacity .4s ease;
}
.lm-similar .product-card.is-tilting::after { opacity: 1; }
.lm-similar .product-image { overflow: hidden; }
.lm-similar .product-image img { display: block; width: 100%; cursor: pointer; transition: transform 1.3s var(--lm-ease); }
.lm-similar .product-card:hover .product-image img { transform: scale(1.08); }
.lm-similar .product-name a { text-decoration: none; color: inherit; transition: color .2s; }
.lm-similar .product-name a:hover { color: var(--lm-gold-d); }
.lm-similar .price-from { margin-right: 4px; font-size: .78em; font-weight: 500; color: var(--lm-muted); }
.lm-similar .btn-cart {
    position: relative; overflow: hidden;
    transform: translate(var(--bx, 0px), var(--by, 0px));
    transition: transform .25s ease-out, box-shadow .4s var(--lm-ease), background-color .2s;
}
.lm-similar .btn-cart::before {
    content: ""; position: absolute; inset: 0 auto 0 -60%; width: 45%;
    background: linear-gradient(105deg, transparent, rgba(255, 255, 255, .5), transparent);
    transform: skewX(-20deg); transition: left .8s var(--lm-ease); pointer-events: none;
}
.lm-similar .btn-cart:hover:not(:disabled)::before { left: 120%; }
.lm-similar .btn-cart:hover:not(:disabled) { box-shadow: 0 14px 24px -12px rgba(160, 124, 23, .8); }
.lm-similar .btn-cart:active:not(:disabled) { transform: translate(var(--bx, 0px), var(--by, 0px)) scale(.96); }

/* ---------- Tablette ---------- */
@media (max-width: 960px) {
    .lm-product { grid-template-columns: minmax(0, 1fr); gap: 26px; }
    .lm-gallery { position: static; }
    .lm-gallery__frame { aspect-ratio: 1 / 1; max-height: none; max-width: 540px; margin: 0 auto; }
    .lm-reviews__grid { grid-template-columns: minmax(0, 1fr); gap: 20px; }
    .lm-summary {
        position: static; display: grid; grid-template-columns: auto minmax(0, 1fr); justify-items: start; align-items: center;
        gap: 4px 22px; padding: 20px 22px; text-align: left;
    }
    .lm-summary__score { grid-row: 1 / span 2; font-size: 2.8rem; }
    .lm-summary .lm-btn, .lm-summary .lm-login-link { grid-column: 1 / -1; width: 100%; justify-content: center; text-align: center; }
}

/* ---------- Mobile ---------- */
@media (max-width: 560px) {
    .lm-produit { padding-top: 16px; padding-bottom: 56px; }
    .lm-gallery__frame { max-width: none; }
    .lm-title { font-size: 1.85rem; }
    .lm-price { margin: 16px 0 18px; padding-bottom: 18px; }
    .lm-price__current { font-size: 1.9rem; }
    .lm-formats { gap: 8px; }
    .lm-produit .lm-format { flex: 1 1 0; min-width: 0; padding: 12px 8px; }
    .lm-qtyrow { gap: 10px 14px; }
    .lm-actions { grid-template-columns: minmax(0, 1fr); }
    .lm-produit .lm-btn { min-height: 56px; }
    .lm-assurances { grid-template-columns: minmax(0, 1fr); gap: 10px; }
    .lm-specs { grid-template-columns: minmax(0, 1fr); }
    .lm-spec { flex-direction: row; align-items: center; gap: 10px; padding: 12px 16px; }
    .lm-spec + .lm-spec { border-left: 0; border-top: 1px solid var(--lm-line); }
    .lm-spec i { margin: 0; width: 18px; text-align: center; }
    .lm-spec dd { margin-left: auto; }
    .lm-reviews, .lm-similar { margin-top: 52px; }
    .lm-review { grid-template-columns: 36px minmax(0, 1fr); gap: 4px 12px; padding: 16px; }
    .lm-review:hover { padding-left: 16px; }
    .lm-review__avatar { width: 36px; height: 36px; font-size: .95rem; }
    .lm-form { padding: 20px 16px; }
    .lm-form .lm-btn { width: 100%; }
    .lm-similar .products-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
}

/* ---------- Mouvement réduit ---------- */
@media (prefers-reduced-motion: reduce) {
    .lm-tilt, .lm-tilt.is-tilting, .lm-similar .product-card, .lm-similar .product-card.is-tilting { transform: none; }
    .lm-js .lm-reveal { opacity: 1; transform: none; transition: none; }
    .lm-produit *, .lm-produit *::before { animation: none !important; }
    .lm-gallery__frame:hover img, .lm-similar .product-card:hover .product-image img { transform: none; }
    .lm-produit .lm-btn::before, .lm-similar .btn-cart::before { display: none; }
}
</style>

<div class="lm-produit">

    <div class="lm-product">
        <!-- Galerie d'images -->
        <div class="lm-gallery">
            <div class="lm-gallery__frame lm-tilt" data-tilt="6">
                <img src="<?php echo $esc($product['image_url'] ?: 'https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=800&q=80'); ?>"
                     alt="<?php echo sanitize($product['nom']); ?>" id="mainImage" fetchpriority="high" decoding="async">
            </div>
        </div>

        <!-- Informations du produit -->
        <div class="lm-info">
            <div class="lm-brand"><?php echo sanitize($product['marque']); ?></div>
            <h1 class="lm-title"><?php echo sanitize($product['nom']); ?></h1>

            <!-- Notation -->
            <div class="lm-rating">
                <span class="lm-stars" aria-hidden="true"><?php echo $stars($avgRating); ?></span>
                <?php if ($reviewCount > 0): ?>
                    <a href="#avis"><?php echo $avgRatingLabel; ?>/5 · <?php echo $reviewCount; ?> avis</a>
                <?php else: ?>
                    <a href="#avis">Aucun avis pour le moment</a>
                <?php endif; ?>
            </div>

            <!-- Prix -->
            <?php
            $price    = $selectedFormat ? $selectedFormat['prix'] : $product['prix'];
            $discount = $product['promotion_pourcentage'];
            ?>
            <div class="lm-price<?php echo $discount > 0 ? ' lm-price--promo' : ''; ?>" aria-live="polite">
                <?php if ($discount > 0):
                    $discountedPrice = calculateDiscount($price, $discount);
                    $discountLabel   = rtrim(rtrim(number_format((float) $discount, 2, ',', ''), '0'), ',');
                ?>
                    <span id="currentPrice" class="lm-price__current"><?php echo formatPrice($discountedPrice); ?></span>
                    <span id="originalPrice" class="lm-price__old"><?php echo formatPrice($price); ?></span>
                    <span class="lm-badge-promo" aria-label="Économisez <?php echo $esc($discountLabel); ?> %">−<?php echo $esc($discountLabel); ?>&nbsp;%</span>
                <?php else: ?>
                    <span id="currentPrice" class="lm-price__current"><?php echo formatPrice($price); ?></span>
                <?php endif; ?>
            </div>

            <!-- Choix du format -->
            <?php if (!empty($formats)): ?>
            <div class="lm-field">
                <div class="lm-label" id="lm-format-label">Contenance</div>
                <div class="lm-formats" role="group" aria-labelledby="lm-format-label">
                    <?php foreach ($formats as $f):
                        $isActive = $f['id_format'] == $selectedFormat['id_format']; ?>
                        <button type="button"
                                class="lm-format<?php echo $isActive ? ' active' : ''; ?>"
                                aria-pressed="<?php echo $isActive ? 'true' : 'false'; ?>"
                                data-format-id="<?php echo (int) $f['id_format']; ?>"
                                data-volume="<?php echo (int) $f['volume_ml']; ?>"
                                data-price="<?php echo (float) $f['prix']; ?>">
                            <?php echo (int) $f['volume_ml']; ?> ml
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quantité + disponibilité -->
            <div class="lm-qtyrow">
                <?php if ($stock > 0): ?>
                <div class="lm-qty">
                    <label for="quantity" class="lm-label">Quantité</label>
                    <div class="lm-stepper">
                        <button type="button" class="lm-stepper__btn" data-qty="minus" aria-label="Diminuer la quantité">−</button>
                        <input type="number" id="quantity" class="lm-stepper__input" value="1" min="1" max="<?php echo $stock; ?>" inputmode="numeric">
                        <button type="button" class="lm-stepper__btn" data-qty="plus" aria-label="Augmenter la quantité">+</button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($stock <= 0): ?>
                    <span class="lm-stock lm-stock--out">Rupture de stock</span>
                <?php elseif ($stock <= $alertThreshold): ?>
                    <span class="lm-stock lm-stock--low">Plus que <?php echo $stock; ?> en stock !</span>
                <?php else: ?>
                    <span class="lm-stock lm-stock--ok">En stock</span>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <?php if ($stock > 0): ?>
            <div class="lm-actions">
                <button type="button" class="lm-btn lm-btn--dark lm-magnetic" data-action="add-to-cart" data-product-id="<?php echo (int) $productId; ?>">
                    <i class="fas fa-shopping-cart" aria-hidden="true"></i> Ajouter au panier
                </button>
                <button type="button" class="lm-btn lm-btn--gold lm-magnetic" data-action="buy-now">
                    <i class="fas fa-bolt" aria-hidden="true"></i> Acheter maintenant
                </button>
            </div>
            <?php else: ?>
            <div class="lm-actions" style="grid-template-columns: 1fr;">
                <button type="button" class="lm-btn lm-btn--disabled" disabled>
                    <i class="fas fa-times-circle" aria-hidden="true"></i> Produit indisponible
                </button>
            </div>
            <p class="lm-unavailable-note">Ce produit est actuellement en rupture de stock.</p>
            <?php endif; ?>

            <!-- Réassurance -->
            <div class="lm-assurances">
                <div class="lm-assurance">
                    <i class="fas fa-shipping-fast" aria-hidden="true"></i>
                    <span>Livraison gratuite dès <?php echo (int) LIVRAISON_GRATUITE_SEUIL; ?>&nbsp;€</span>
                </div>
                <div class="lm-assurance">
                    <i class="fas fa-undo" aria-hidden="true"></i>
                    <span>Retour gratuit sous 30 jours</span>
                </div>
                <div class="lm-assurance">
                    <i class="fas fa-shield-alt" aria-hidden="true"></i>
                    <span>Paiement sécurisé</span>
                </div>
            </div>

            <!-- Description -->
            <div class="lm-details">
                <h2 class="lm-subtitle">Description</h2>
                <p class="lm-description"><?php echo nl2br(sanitize($product['description'])); ?></p>

                <dl class="lm-specs">
                    <div class="lm-spec">
                        <i class="fas fa-wine-bottle" aria-hidden="true"></i>
                        <dt>Volume</dt>
                        <dd><span id="volumeText"><?php echo $selectedFormat ? (int) $selectedFormat['volume_ml'] : (int) $product['volume_ml']; ?></span> ml</dd>
                    </div>
                    <div class="lm-spec">
                        <i class="fas fa-tag" aria-hidden="true"></i>
                        <dt>Concentration</dt>
                        <dd><?php echo $esc($product['concentration'] ?: '—'); ?></dd>
                    </div>
                    <div class="lm-spec">
                        <i class="fas fa-venus-mars" aria-hidden="true"></i>
                        <dt>Genre</dt>
                        <dd><?php echo $esc($product['genre'] ?: '—'); ?></dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    <!-- Section Avis -->
    <section class="lm-reviews lm-reveal" id="avis" aria-labelledby="lm-avis-title">
        <h2 class="lm-section-title" id="lm-avis-title">Avis clients</h2>

        <div class="lm-reviews__grid">
            <!-- Synthèse -->
            <aside class="lm-summary lm-tilt" data-tilt="5">
                <?php if ($reviewCount > 0): ?>
                    <div class="lm-summary__score"><?php echo $avgRatingLabel; ?><small>/5</small></div>
                <?php endif; ?>
                <div class="lm-stars" aria-hidden="true"><?php echo $stars($avgRating); ?></div>
                <div class="lm-summary__count">
                    <?php echo $reviewCount > 0 ? 'Basé sur ' . $reviewCount . ' avis' : 'Aucun avis pour le moment'; ?>
                </div>

                <?php if (isLoggedIn() && !$already_reviewed): ?>
                    <a class="lm-btn lm-btn--outline" href="#reviewForm">Laisser un avis</a>
                <?php elseif (!isLoggedIn()): ?>
                    <p class="lm-login-link"><a href="connexion.php">Connectez-vous</a> pour laisser un avis.</p>
                <?php endif; ?>
            </aside>

            <div class="lm-reviews__side">
                <!-- Liste des avis -->
                <div class="lm-reviews__list">
                    <?php if ($reviews): ?>
                        <?php foreach ($reviews as $review): ?>
                            <article class="lm-review">
                                <div class="lm-review__avatar" aria-hidden="true"><?php echo $esc($initial($review['prenom']) ?: $initial($review['nom']) ?: '•'); ?></div>
                                <div class="lm-review__head">
                                    <strong class="lm-review__name">
                                        <?php echo sanitize($review['prenom']); ?>
                                        <?php $lastInitial = $initial($review['nom']); echo $lastInitial !== '' ? $esc($lastInitial) . '.' : ''; ?>
                                    </strong>
                                    <time class="lm-review__date" datetime="<?php echo $esc(date('Y-m-d', strtotime($review['date_avis']))); ?>">
                                        <?php echo date('d/m/Y', strtotime($review['date_avis'])); ?>
                                    </time>
                                </div>
                                <div class="lm-stars" role="img" aria-label="Note : <?php echo (int) $review['note']; ?> sur 5"><?php echo $stars($review['note']); ?></div>
                                <p class="lm-review__text"><?php echo nl2br(sanitize($review['commentaire'])); ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="lm-empty">
                            <i class="far fa-star" aria-hidden="true"></i>
                            Soyez le premier à laisser un avis sur ce produit !
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (isLoggedIn()): ?>
                    <?php if ($already_reviewed): ?>
                        <div class="lm-thanks<?php echo $reviewPending ? ' lm-thanks--pending' : ''; ?>">
                            <i class="fas <?php echo $reviewPending ? 'fa-hourglass-half' : 'fa-check-circle'; ?>" aria-hidden="true"></i>
                            <h3><?php echo $reviewPending ? 'Merci, votre avis a bien été reçu !' : 'Merci pour votre avis sur ce produit !'; ?></h3>
                            <p><?php echo $reviewPending ? 'Il sera visible dès qu\'il aura été validé par notre équipe.' : 'Un seul avis par produit est autorisé.'; ?></p>
                        </div>
                    <?php else: ?>
                        <div id="reviewForm" class="lm-form">

                            <?php
                            if (isset($_SESSION['success_message'])) {
                                echo '<div class="lm-alert lm-alert--ok">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
                                unset($_SESSION['success_message']);
                            }

                            if (isset($_SESSION['review_errors']) && is_array($_SESSION['review_errors'])) {
                                echo '<div class="lm-alert lm-alert--err">';
                                foreach ($_SESSION['review_errors'] as $err) {
                                    echo '<p>⚠️ ' . htmlspecialchars($err) . '</p>';
                                }
                                echo '</div>';
                                unset($_SESSION['review_errors']);
                            }
                            ?>

                            <h3>Laisser un avis</h3>
                            <form method="POST" action="../includes/submit_review.php">
                                <input type="hidden" name="product_id" value="<?php echo (int) $productId; ?>">

                                <div class="lm-form__row">
                                    <label id="lm-rating-label">Votre note</label>
                                    <div class="star-rating lm-stars-input" role="radiogroup" aria-labelledby="lm-rating-label">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <button type="button" class="lm-star" role="radio" aria-checked="false"
                                                    data-rating="<?php echo $i; ?>"
                                                    aria-label="<?php echo $i; ?> étoile<?php echo $i > 1 ? 's' : ''; ?> sur 5">
                                                <i class="far fa-star" aria-hidden="true"></i>
                                            </button>
                                        <?php endfor; ?>
                                        <input type="hidden" name="rating" id="ratingInput" value="5">
                                    </div>
                                </div>

                                <div class="lm-form__row">
                                    <label for="comment">
                                        Votre commentaire <span>(10 à 1000 caractères)</span>
                                    </label>
                                    <textarea id="comment" name="comment" class="lm-textarea"
                                              rows="5" placeholder="Partagez votre expérience avec ce parfum..." required
                                              minlength="10" maxlength="1000"></textarea>
                                    <div id="charCount" class="lm-counter">0 / 1000</div>
                                </div>

                                <button type="submit" class="lm-btn lm-btn--dark lm-magnetic">Publier mon avis</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Produits similaires -->
    <?php if ($similarProducts): ?>
        <div class="lm-similar lm-reveal">
            <h2 class="lm-section-title">Vous aimerez aussi</h2>

            <div class="products-grid">
                <?php foreach ($similarProducts as $idx => $similar): ?>
                    <div class="product-card lm-reveal" data-tilt="7" style="--i: <?php echo (int) $idx; ?>">
                        <div class="product-image">
                            <img src="<?php echo $esc($similar['image_url'] ?: 'https://images.unsplash.com/photo-1541643600914-78b084683601?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'); ?>"
                                 alt="<?php echo sanitize($similar['nom']); ?>"
                                 loading="lazy"
                                 onclick="window.location.href='produit.php?id=<?php echo (int) $similar['id_produit']; ?>'">
                        </div>

                        <div class="product-info">
                            <div class="product-brand"><?php echo sanitize($similar['marque']); ?></div>
                            <h3 class="product-name">
                                <a href="produit.php?id=<?php echo (int) $similar['id_produit']; ?>">
                                    <?php echo sanitize($similar['nom']); ?>
                                </a>
                            </h3>

                            <div class="product-price">
                                <?php
                                // Même logique que le catalogue : prix du format le moins cher
                                $simPrice = ($similar['prix_min'] !== null) ? $similar['prix_min'] : $similar['prix'];
                                $simDisc  = $similar['promotion_pourcentage'];

                                if ($simDisc > 0):
                                    $simDiscounted = calculateDiscount($simPrice, $simDisc);
                                ?>
                                    <span class="price-original"><?php echo formatPrice($simPrice); ?></span>
                                    <span class="price-current"><?php echo formatPrice($simDiscounted); ?></span>
                                <?php else: ?>
                                    <?php if ($similar['prix_min'] !== null): ?><span class="price-from">Dès</span><?php endif; ?>
                                    <span class="price-current"><?php echo formatPrice($simPrice); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="product-actions">
                                <?php if ($similar['stock'] > 0): ?>
                                    <button class="btn-cart" data-product-id="<?php echo (int) $similar['id_produit']; ?>">
                                        <i class="fas fa-shopping-cart"></i> Ajouter
                                    </button>
                                <?php else: ?>
                                    <button disabled style="background: #ccc; color: #888; padding: 8px 16px; border: none; border-radius: 6px; cursor: not-allowed; font-size: 0.9em;">
                                        Indisponible
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    const quantityInput = document.getElementById('quantity');

    // ===== CHOIX DU FORMAT =====
    const discountPercent = <?php echo (float) $product['promotion_pourcentage']; ?>;
    let selectedFormatId  = <?php echo $selectedFormat ? (int) $selectedFormat['id_format'] : 0; ?>;

    function formatEuro(n) {
        return n.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
    }

    document.querySelectorAll('.lm-format').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.lm-format').forEach(function(b) {
                b.classList.remove('active');
                b.setAttribute('aria-pressed', 'false');
            });
            this.classList.add('active');
            this.setAttribute('aria-pressed', 'true');

            selectedFormatId = parseInt(this.dataset.formatId);
            const basePrice  = parseFloat(this.dataset.price);

            const original = document.getElementById('originalPrice');
            const current  = document.getElementById('currentPrice');
            if (original) {
                original.textContent = formatEuro(basePrice);
                current.textContent  = formatEuro(Math.round(basePrice * (1 - discountPercent / 100) * 100) / 100);
            } else if (current) {
                current.textContent = formatEuro(basePrice);
            }
            // Petite animation "pop" sur le prix
            if (current) {
                current.classList.remove('is-bump');
                void current.offsetWidth;
                current.classList.add('is-bump');
            }

            const volumeText = document.getElementById('volumeText');
            if (volumeText) volumeText.textContent = this.dataset.volume;
        });
    });

    // ===== QUANTITÉ (+/-) =====
    function readQuantity() {
        if (!quantityInput) return 1;
        const max = parseInt(quantityInput.max, 10) || 1;
        let value = parseInt(quantityInput.value, 10);
        if (isNaN(value) || value < 1) value = 1;
        if (value > max) value = max;
        quantityInput.value = value;
        return value;
    }

    if (quantityInput) {
        quantityInput.addEventListener('change', readQuantity);

        const minusBtn = document.querySelector('[data-qty="minus"]');
        const plusBtn  = document.querySelector('[data-qty="plus"]');

        if (minusBtn) {
            minusBtn.addEventListener('click', function() {
                quantityInput.value = readQuantity() - 1;
                readQuantity();
            });
        }
        if (plusBtn) {
            plusBtn.addEventListener('click', function() {
                quantityInput.value = readQuantity() + 1;
                readQuantity();
            });
        }
    }

    // ===== AJOUT AU PANIER =====
    function postAddToCart(productId, quantity, formatId) {
        let body = 'product_id=' + encodeURIComponent(productId) + '&quantity=' + encodeURIComponent(quantity);
        if (formatId !== null) {
            body += '&format_id=' + encodeURIComponent(formatId);
        }
        return fetch('ajouter_au_panier.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function(response) { return response.json(); });
    }

    function handleAddFailure(btn, originalHTML, data, fallbackMessage) {
        if (data.message && data.message.includes('connecter')) {
            if (confirm('Vous devez être connecté. Aller à la page de connexion ?')) {
                window.location.href = 'connexion.php';
                return;
            }
        } else {
            alert(data.message || fallbackMessage);
        }
        btn.innerHTML = originalHTML;
        btn.disabled  = false;
    }

    const addToCartBtn = document.querySelector('[data-action="add-to-cart"]');

    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', function() {
            const productId    = this.getAttribute('data-product-id');
            const quantity     = readQuantity();
            const originalHTML = this.innerHTML;

            this.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Ajout...';
            this.disabled  = true;

            postAddToCart(productId, quantity, selectedFormatId)
            .then(data => {
                if (data.success) {
                    this.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i> Ajouté !';
                    this.classList.add('is-success');

                    const cartCounters = document.querySelectorAll('.cart-count, #cartCount');
                    cartCounters.forEach(counter => {
                        let current = parseInt(counter.textContent) || 0;
                        counter.textContent = current + quantity;
                        counter.style.transform = 'scale(1.4)';
                        setTimeout(() => { counter.style.transform = 'scale(1)'; }, 300);
                    });

                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.classList.remove('is-success');
                        this.disabled = false;
                    }, 2000);
                } else {
                    handleAddFailure(this, originalHTML, data, 'Erreur lors de l\'ajout au panier');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur réseau. Veuillez réessayer.');
                this.innerHTML = originalHTML;
                this.disabled  = false;
            });
        });
    }

    const buyNowBtn = document.querySelector('[data-action="buy-now"]');

    if (buyNowBtn) {
        buyNowBtn.addEventListener('click', function() {
            const productId    = addToCartBtn ? addToCartBtn.getAttribute('data-product-id') : null;
            const quantity     = readQuantity();
            const originalHTML = this.innerHTML;

            if (!productId) {
                alert('Erreur : Produit non trouvé');
                return;
            }

            this.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Traitement...';
            this.disabled  = true;

            postAddToCart(productId, quantity, selectedFormatId)
            .then(data => {
                if (data.success) {
                    window.location.href = 'panier.php';
                } else {
                    handleAddFailure(this, originalHTML, data, 'Erreur lors de l\'ajout au panier');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur réseau. Veuillez réessayer.');
                this.innerHTML = originalHTML;
                this.disabled  = false;
            });
        });
    }

    // BOUTONS "AJOUTER" DES PRODUITS SIMILAIRES
    document.querySelectorAll('.btn-cart').forEach(button => {
        button.addEventListener('click', function() {
            const productId    = this.getAttribute('data-product-id');
            const originalHTML = this.innerHTML;

            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.disabled  = true;

            postAddToCart(productId, 1, null)
            .then(data => {
                if (data.success) {
                    this.innerHTML = '<i class="fas fa-check"></i> Ajouté';
                    this.style.background = '#27ae60';

                    document.querySelectorAll('.cart-count, #cartCount').forEach(counter => {
                        counter.textContent = (parseInt(counter.textContent) || 0) + 1;
                    });

                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.style.background = '';
                        this.disabled = false;
                    }, 2000);
                } else {
                    alert(data.message || 'Erreur');
                    this.innerHTML = originalHTML;
                    this.disabled  = false;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur réseau');
                this.innerHTML = originalHTML;
                this.disabled  = false;
            });
        });
    });

    // ===== NOTATION PAR ÉTOILES =====
    const starsWrap   = document.querySelector('.lm-stars-input');
    const ratingInput = document.getElementById('ratingInput');
    if (starsWrap && ratingInput) {
        const stars = Array.from(starsWrap.querySelectorAll('.lm-star'));

        function paintStars(count) {
            stars.forEach(function(star, index) {
                const on   = index < count;
                const icon = star.querySelector('i');
                star.classList.toggle('is-on', on);
                star.setAttribute('aria-checked', (index + 1) === parseInt(ratingInput.value) ? 'true' : 'false');
                icon.classList.toggle('fas', on);
                icon.classList.toggle('far', !on);
            });
        }

        stars.forEach(function(star, index) {
            star.addEventListener('click', function() {
                ratingInput.value = this.dataset.rating;
                paintStars(parseInt(ratingInput.value));
            });
            star.addEventListener('mouseenter', function() { paintStars(index + 1); });
            star.addEventListener('focus',      function() { paintStars(index + 1); });
        });
        starsWrap.addEventListener('mouseleave', function() { paintStars(parseInt(ratingInput.value)); });
        starsWrap.addEventListener('focusout',   function() { paintStars(parseInt(ratingInput.value)); });

        paintStars(parseInt(ratingInput.value) || 5);
    }

    // ===== COMPTEUR DE CARACTÈRES =====
    const commentArea = document.getElementById('comment');
    const charCount   = document.getElementById('charCount');
    if (commentArea && charCount) {
        commentArea.addEventListener('input', function() {
            const len = this.value.length;
            charCount.textContent = len + ' / 1000';
            charCount.classList.toggle('is-warn', len > 900);
        });
    }
});

/* =====================================================================
   EFFETS PREMIUM (même mécanique que quiz-premium.js)
   - apparition au scroll, inclinaison 3D + reflet, boutons magnétiques
   ===================================================================== */
(function () {
    'use strict';
    var root = document.querySelector('.lm-produit');
    if (!root) { return; }
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }

    /* Apparition douce au scroll (le CSS ne masque que si .lm-js est présent) */
    var reveals = root.querySelectorAll('.lm-reveal');
    if ('IntersectionObserver' in window && reveals.length) {
        root.classList.add('lm-js');
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
            });
        }, { threshold: 0.12 });
        reveals.forEach(function (el) { io.observe(el); });
    }

    /* Le reste : souris uniquement */
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) { return; }

    function tilt(el, max) {
        var frame = null;
        el.classList.add('lm-tilt');
        el.addEventListener('pointerenter', function () { el.classList.add('is-tilting'); });
        el.addEventListener('pointermove', function (e) {
            var r = el.getBoundingClientRect();
            if (!r.width || !r.height) { return; }
            var px = (e.clientX - r.left) / r.width;
            var py = (e.clientY - r.top) / r.height;
            if (frame) { cancelAnimationFrame(frame); }
            frame = requestAnimationFrame(function () {
                el.style.setProperty('--ry', ((px - 0.5) * 2 * max).toFixed(2) + 'deg');
                el.style.setProperty('--rx', ((0.5 - py) * 2 * max).toFixed(2) + 'deg');
                el.style.setProperty('--mx', (px * 100).toFixed(1) + '%');
                el.style.setProperty('--my', (py * 100).toFixed(1) + '%');
            });
        });
        el.addEventListener('pointerleave', function () {
            el.classList.remove('is-tilting');
            el.style.setProperty('--rx', '0deg');
            el.style.setProperty('--ry', '0deg');
        });
    }

    function magnetic(el) {
        el.addEventListener('pointermove', function (e) {
            var r = el.getBoundingClientRect();
            el.style.setProperty('--bx', ((e.clientX - (r.left + r.width / 2)) * 0.12).toFixed(1) + 'px');
            el.style.setProperty('--by', ((e.clientY - (r.top + r.height / 2)) * 0.22).toFixed(1) + 'px');
        });
        el.addEventListener('pointerleave', function () {
            el.style.setProperty('--bx', '0px');
            el.style.setProperty('--by', '0px');
        });
    }

    root.querySelectorAll('[data-tilt]').forEach(function (el) {
        tilt(el, parseFloat(el.getAttribute('data-tilt')) || 6);
    });
    root.querySelectorAll('.lm-magnetic, .lm-similar .btn-cart').forEach(magnetic);
})();
</script>

<?php include '../includes/footer.php'; ?>