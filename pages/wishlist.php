<?php
/**
 * wishlist.php
 * Page « Mes favoris » — refonte éditoriale Lumoura.
 *
 * Contrats AJAX conservés (identiques à catalogue.php / produit.php) :
 *  - toggle_wishlist.php   : POST product_id        → { success, action: 'added'|'removed', wishlist_count, message?, redirect? }
 *  - ajouter_au_panier.php : POST product_id, quantity, format_id? → { success, message?, cart_count? }
 */

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['login_redirect'] = 'Veuillez vous connecter pour voir vos favoris.';
    header('Location: connexion.php');
    exit();
}

$user_id   = $_SESSION['user_id'];
$pageTitle = "Mes Favoris - Lumoura";

// ------------------------------------------------------------
// Récupération des favoris (requêtes préparées, propres à l'utilisateur)
// ------------------------------------------------------------
// Pour chaque favori on récupère aussi le FORMAT LE MOINS CHER (celui du « Dès X € »
// du catalogue et du format présélectionné sur la fiche produit) : id, prix, volume.
$richQuery = "
    SELECT
        p.*,
        f.date_ajout AS date_favori,
        (SELECT pf.id_format  FROM produit_formats pf WHERE pf.id_produit = p.id_produit ORDER BY pf.prix ASC, pf.volume_ml ASC LIMIT 1) AS id_format_min,
        (SELECT pf.prix       FROM produit_formats pf WHERE pf.id_produit = p.id_produit ORDER BY pf.prix ASC, pf.volume_ml ASC LIMIT 1) AS prix_min,
        (SELECT pf.volume_ml  FROM produit_formats pf WHERE pf.id_produit = p.id_produit ORDER BY pf.prix ASC, pf.volume_ml ASC LIMIT 1) AS volume_min,
        (SELECT COUNT(*)      FROM produit_formats pf WHERE pf.id_produit = p.id_produit) AS nb_formats
    FROM favoris f
    JOIN produits p ON f.id_produit = p.id_produit
    WHERE f.id_utilisateur = ?
    ORDER BY f.date_ajout DESC
";
// Requête d'origine : repli si la table des formats est indisponible
$baseQuery = "
    SELECT p.*, f.date_ajout AS date_favori
    FROM favoris f
    JOIN produits p ON f.id_produit = p.id_produit
    WHERE f.id_utilisateur = ?
    ORDER BY f.date_ajout DESC
";

$favorites = null;
try {
    $stmt = $pdo->prepare($richQuery);
    if ($stmt && $stmt->execute([$user_id])) {
        $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $favorites = null;
}
if ($favorites === null) {
    $stmt = $pdo->prepare($baseQuery);
    $stmt->execute([$user_id]);
    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$favCount = count($favorites);

// ------------------------------------------------------------
// Helpers d'affichage propres à cette page
// ------------------------------------------------------------
$esc = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

// URL d'image sûre : chemin relatif ou http(s) uniquement (pas de javascript:, data:, etc.)
$safeImage = function ($url) {
    $url = trim((string) $url);
    if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
        return '';
    }
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url) && !preg_match('#^https?://#i', $url)) {
        return '';
    }
    return $url;
};

$formatDate = function ($value) {
    $ts = strtotime((string) $value);
    return $ts ? date('d/m/Y', $ts) : '';
};

include '../includes/header.php';
?>

<style>
/* ============================================================
   LUMOURA — FAVORIS
   Tout est scopé sous .wishlist-page : aucun effet sur le header,
   le footer ni les autres pages.
   ============================================================ */
.wishlist-page {
    --wl-ivory:   #FAF8F3;
    --wl-cream:   #F5F0E7;
    --wl-paper:   #FFFEFB;
    --wl-champ:   #C6A76A;   /* champagne : filets, icônes décoratives */
    --wl-gold:    #D4AF37;
    --wl-gold-tx: #7A6128;   /* or foncé : texte lisible sur ivoire (contraste AA) */
    --wl-brown:   #2C211B;
    --wl-brown-2: #3E2F26;
    --wl-text:    #5B4D43;
    --wl-muted:   #7D6F64;
    --wl-line:    #E7DECF;
    --wl-rose:    #9E4B52;   /* cœur : rose sourd, jamais criard */
    --wl-ok:      #3E6B4B;
    --wl-err:     #A3322B;
    --wl-ease:    cubic-bezier(.22, 1, .36, 1);
    --wl-serif:   "Cormorant Garamond", "Playfair Display", Georgia, "Times New Roman", serif;
    --wl-fit:     cover;     /* "contain" si vous préférez ne jamais rogner les photos */

    padding-top: 96px;       /* header fixe */
    min-height: 100vh;
    background: linear-gradient(180deg, var(--wl-ivory) 0%, var(--wl-cream) 100%);
    color: var(--wl-brown);
}
.wishlist-page *, .wishlist-page *::before, .wishlist-page *::after { box-sizing: border-box; }
.wishlist-page :where(h1, h2, h3, p, ul, figure) { margin: 0; padding: 0; }
.wishlist-page :where(button) { font: inherit; }

.wishlist-container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 clamp(16px, 3vw, 32px) 96px;
}

/* ---------- En-tête éditorial ---------- */
.wl-hero {
    position: relative;
    display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap;
    gap: 24px 40px;
    padding: clamp(28px, 5vw, 56px) 0 clamp(26px, 4vw, 40px);
    margin-bottom: clamp(28px, 4vw, 48px);
    animation: wlFade .8s var(--wl-ease) backwards;
}
.wl-hero::after { /* filet champagne qui s'estompe */
    content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 1px;
    background: linear-gradient(90deg, var(--wl-champ), rgba(198, 167, 106, .25) 60%, transparent);
}
.wl-eyebrow {
    display: inline-flex; align-items: center; gap: 12px;
    font-size: .74rem; font-weight: 600; letter-spacing: .26em; text-transform: uppercase;
    color: var(--wl-gold-tx);
}
.wl-eyebrow::before { content: ""; width: 28px; height: 1px; background: var(--wl-champ); }
.wl-title {
    margin: 12px 0 10px;
    font-family: var(--wl-serif);
    font-size: clamp(2.4rem, 5.4vw, 3.8rem);
    font-weight: 500; line-height: 1.05; letter-spacing: -.01em;
    color: var(--wl-brown);
}
.wl-lead { max-width: 520px; font-size: 1rem; line-height: 1.7; color: var(--wl-text); }

.wl-count { display: flex; align-items: center; gap: 16px; }
.wl-count__ring {
    position: relative; flex: 0 0 auto;
    display: flex; align-items: center; justify-content: center;
    width: 58px; height: 58px; border-radius: 50%;
    border: 1px solid var(--wl-champ); background: var(--wl-paper);
    color: var(--wl-rose); font-size: 1.15rem;
    box-shadow: 0 10px 24px -16px rgba(44, 33, 27, .5);
}
.wl-count__ring::after { content: ""; position: absolute; inset: 5px; border-radius: 50%; border: 1px solid var(--wl-line); }
.wl-count__txt { display: flex; flex-direction: column; line-height: 1.1; }
.wl-count__num { font-family: var(--wl-serif); font-size: 2.6rem; font-weight: 500; color: var(--wl-brown); display: inline-block; }
.wl-count__num.is-bump { animation: wlBump .5s var(--wl-ease); }
.wl-count__label { margin-top: 2px; font-size: .78rem; letter-spacing: .16em; text-transform: uppercase; color: var(--wl-muted); }

/* ---------- Grille ---------- */
.wishlist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 270px), 1fr));
    gap: clamp(20px, 2.6vw, 34px);
}
.wishlist-grid[hidden] { display: none; }

/* ---------- Carte ---------- */
.wishlist-card {
    position: relative;
    display: flex; flex-direction: column;
    height: 100%;
    background: var(--wl-paper);
    border: 1px solid var(--wl-line);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(44, 33, 27, .04), 0 14px 30px -24px rgba(44, 33, 27, .45);
    transition: transform .5s var(--wl-ease), box-shadow .5s var(--wl-ease), border-color .3s, opacity .4s ease;
    animation: wlRise .8s var(--wl-ease) backwards;
    animation-delay: calc(var(--i, 0) * 70ms);
}
.wishlist-card:hover, .wishlist-card:focus-within {
    transform: translateY(-4px);
    border-color: var(--wl-champ);
    box-shadow: 0 1px 2px rgba(44, 33, 27, .05), 0 26px 44px -26px rgba(44, 33, 27, .5);
}
.wishlist-card.is-leaving { opacity: 0; transform: scale(.96) translateY(8px); pointer-events: none; }
.wishlist-card.is-unavailable .wl-media img { filter: grayscale(.35); opacity: .85; }

/* Image */
.wl-media {
    position: relative;
    aspect-ratio: 4 / 5;
    overflow: hidden;
    background: linear-gradient(160deg, var(--wl-cream), #EADFCB);
}
.wishlist-card-image { display: block; width: 100%; height: 100%; }
.wl-media img {
    display: block; width: 100%; height: 100%;
    object-fit: var(--wl-fit); object-position: center;
    transition: transform 1.2s var(--wl-ease);
}
.wishlist-card:hover .wl-media img { transform: scale(1.035); }
.wl-media__empty { /* image absente ou invalide */
    position: absolute; inset: 0; display: none; align-items: center; justify-content: center; flex-direction: column; gap: 10px;
    color: var(--wl-champ); font-size: 2.6rem; background: linear-gradient(160deg, var(--wl-cream), #EADFCB);
}
.wl-media__empty span { font-size: .7rem; letter-spacing: .22em; text-transform: uppercase; color: var(--wl-muted); }
.wl-media.is-empty .wl-media__empty { display: flex; }
.wl-media.is-empty img { visibility: hidden; }

/* Badge promo : discret */
.wl-badge {
    position: absolute; top: 14px; left: 14px; z-index: 2;
    padding: 6px 11px; border-radius: 999px;
    background: rgba(250, 248, 243, .94); border: 1px solid var(--wl-champ);
    color: var(--wl-brown); font-size: .72rem; font-weight: 700; letter-spacing: .08em;
    backdrop-filter: blur(6px);
}
.wl-badge--out { top: auto; bottom: 14px; border-color: var(--wl-line); color: var(--wl-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .12em; }

/* Bouton retirer (icône discrète) */
.wishlist-remove {
    position: absolute; top: 12px; right: 12px; z-index: 3;
    display: flex; align-items: center; justify-content: center;
    width: 38px; height: 38px; padding: 0; border-radius: 50%;
    border: 1px solid var(--wl-line); background: rgba(250, 248, 243, .94); color: var(--wl-brown-2);
    font-size: .85rem; cursor: pointer; backdrop-filter: blur(6px);
    transition: background-color .25s, color .25s, border-color .25s, transform .35s var(--wl-ease);
}
.wishlist-remove:hover { background: var(--wl-brown); border-color: var(--wl-brown); color: var(--wl-ivory); transform: rotate(90deg); }

/* Confirmation intégrée (remplace confirm()) */
.wl-confirm {
    position: absolute; left: 12px; right: 12px; bottom: 12px; z-index: 4;
    padding: 16px; border-radius: 12px;
    background: rgba(250, 248, 243, .97); border: 1px solid var(--wl-champ);
    box-shadow: 0 18px 36px -18px rgba(44, 33, 27, .6);
    opacity: 0; visibility: hidden; transform: translateY(10px);
    transition: opacity .3s ease, transform .4s var(--wl-ease), visibility 0s linear .4s;
}
.wishlist-card.is-confirming .wl-confirm { opacity: 1; visibility: visible; transform: none; transition-delay: 0s; }
.wl-confirm p { margin-bottom: 12px; font-size: .9rem; line-height: 1.45; color: var(--wl-brown); text-align: center; }
.wl-confirm__row { display: flex; gap: 8px; }
.wl-confirm__row button {
    flex: 1; min-height: 40px; padding: 8px 10px; border-radius: 8px; cursor: pointer;
    font-size: .78rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
    transition: background-color .25s, color .25s, border-color .25s;
}
.wl-keep { background: transparent; border: 1px solid var(--wl-brown); color: var(--wl-brown); }
.wl-keep:hover { background: var(--wl-brown); color: var(--wl-ivory); }
.wl-confirm-yes { background: var(--wl-rose); border: 1px solid var(--wl-rose); color: #fff; }
.wl-confirm-yes:hover:not(:disabled) { background: #7F383E; border-color: #7F383E; }
.wl-confirm__row button:disabled { opacity: .7; cursor: progress; }

/* Contenu */
.wishlist-card-content { display: flex; flex-direction: column; flex: 1; padding: 22px 22px 20px; }
.wishlist-brand { font-size: .72rem; font-weight: 600; letter-spacing: .2em; text-transform: uppercase; color: var(--wl-gold-tx); }
.wishlist-name {
    margin: 8px 0 8px;
    font-family: var(--wl-serif); font-size: 1.55rem; font-weight: 600; line-height: 1.15; color: var(--wl-brown);
}
.wishlist-name a {
    color: inherit; text-decoration: none;
    background: linear-gradient(var(--wl-champ), var(--wl-champ)) 0 100% / 0 1px no-repeat;
    transition: background-size .6s var(--wl-ease);
}
.wishlist-card:hover .wishlist-name a { background-size: 100% 1px; }
.wishlist-description {
    min-height: 3.1em;                       /* hauteur maîtrisée : 2 lignes */
    margin-bottom: 10px;
    font-size: .9rem; line-height: 1.55; color: var(--wl-text);
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.wl-meta { margin-bottom: 4px; font-size: .78rem; letter-spacing: .04em; color: var(--wl-muted); }

/* Prix : poussé en bas pour aligner prix et boutons d'une carte à l'autre */
.wishlist-price-row {
    display: flex; align-items: baseline; flex-wrap: wrap; gap: 4px 10px;
    margin-top: auto; padding-top: 14px; margin-bottom: 16px;
}
.wl-from { font-size: .7rem; letter-spacing: .16em; text-transform: uppercase; color: var(--wl-muted); }
.wishlist-price-current { font-family: var(--wl-serif); font-size: 1.9rem; font-weight: 600; line-height: 1; color: var(--wl-brown); }
.wishlist-price-original { font-size: .95rem; color: var(--wl-muted); text-decoration: line-through; }
.wishlist-price-row.is-promo .wishlist-price-current { color: var(--wl-rose); }

/* Actions */
.wishlist-actions { display: flex; flex-direction: column; gap: 10px; }
.wishlist-btn-cart {
    position: relative; overflow: hidden;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    width: 100%; min-height: 48px; padding: 12px 16px;
    border: 1px solid var(--wl-brown); border-radius: 10px;
    background: var(--wl-brown); color: var(--wl-ivory);
    font-size: .78rem; font-weight: 600; letter-spacing: .12em; text-transform: uppercase;
    cursor: pointer;
    transition: background-color .3s, border-color .3s, box-shadow .4s var(--wl-ease), transform .25s;
}
.wishlist-btn-cart i { color: var(--wl-gold); transition: transform .4s var(--wl-ease); }
.wishlist-btn-cart::before { /* reflet discret */
    content: ""; position: absolute; inset: 0 auto 0 -60%; width: 40%;
    background: linear-gradient(105deg, transparent, rgba(255, 255, 255, .22), transparent);
    transform: skewX(-20deg); transition: left .8s var(--wl-ease); pointer-events: none;
}
.wishlist-btn-cart:hover:not(:disabled) { background: var(--wl-brown-2); box-shadow: 0 14px 24px -14px rgba(44, 33, 27, .7), 0 0 0 4px rgba(198, 167, 106, .2); }
.wishlist-btn-cart:hover:not(:disabled)::before { left: 120%; }
.wishlist-btn-cart:hover:not(:disabled) i { transform: scale(1.15) rotate(-8deg); }
.wishlist-btn-cart:active:not(:disabled) { transform: scale(.98); }
.wishlist-btn-cart:disabled { cursor: progress; opacity: .8; }
.wishlist-btn-cart.is-success, .wishlist-btn-cart.is-success:hover:not(:disabled) { background: var(--wl-ok); border-color: var(--wl-ok); }
.wishlist-btn-cart.is-success i { color: #fff; }
.wishlist-btn-cart.is-error, .wishlist-btn-cart.is-error:hover:not(:disabled) { background: var(--wl-err); border-color: var(--wl-err); }
.wishlist-btn-cart.is-unavailable { background: #E4DDD0; border-color: #E4DDD0; color: #6F6256; cursor: not-allowed; opacity: 1; }
.wishlist-btn-cart.is-unavailable i { color: #6F6256; }

.wl-link {
    align-self: center; display: inline-flex; align-items: center; gap: 8px;
    padding: 4px 2px; font-size: .8rem; font-weight: 600; letter-spacing: .06em; text-decoration: none;
    color: var(--wl-gold-tx); border-bottom: 1px solid transparent;
    transition: color .25s, border-color .25s, gap .35s var(--wl-ease);
}
.wl-link:hover { color: var(--wl-brown); border-bottom-color: var(--wl-champ); gap: 12px; }

.wishlist-date { margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--wl-line); text-align: center; font-size: .76rem; letter-spacing: .04em; color: var(--wl-muted); }

/* Lien de poursuite sous la grille */
.wl-continue { margin-top: clamp(36px, 5vw, 56px); text-align: center; }

/* ---------- État vide ---------- */
.wishlist-empty {
    max-width: 620px; margin: clamp(8px, 3vw, 32px) auto 0;
    padding: clamp(40px, 6vw, 64px) clamp(24px, 5vw, 56px);
    text-align: center;
    background: var(--wl-paper); border: 1px solid var(--wl-line); border-radius: 20px;
    box-shadow: 0 30px 60px -44px rgba(44, 33, 27, .55);
    animation: wlRise .8s var(--wl-ease) backwards;
}
.wishlist-empty[hidden] { display: none; }
.wl-empty__heart {
    position: relative; display: inline-flex; align-items: center; justify-content: center;
    width: 84px; height: 84px; margin-bottom: 26px; border-radius: 50%;
    border: 1px solid var(--wl-champ); color: var(--wl-rose); font-size: 1.9rem;
}
.wl-empty__heart::before, .wl-empty__heart::after { content: ""; position: absolute; border-radius: 50%; border: 1px solid var(--wl-line); }
.wl-empty__heart::before { inset: 7px; }
.wl-empty__heart::after  { inset: -10px; border-color: rgba(198, 167, 106, .35); }
.wishlist-empty h2 { margin-bottom: 14px; font-family: var(--wl-serif); font-size: clamp(1.9rem, 4vw, 2.5rem); font-weight: 500; line-height: 1.1; color: var(--wl-brown); }
.wishlist-empty h2:focus { outline: none; }
.wishlist-empty p { max-width: 440px; margin: 0 auto 30px; font-size: 1rem; line-height: 1.7; color: var(--wl-text); }

.wl-btn {
    position: relative; overflow: hidden;
    display: inline-flex; align-items: center; justify-content: center; gap: 12px;
    min-height: 50px; padding: 14px 32px; border-radius: 10px;
    border: 1px solid var(--wl-brown); background: var(--wl-brown); color: var(--wl-ivory);
    font-size: .8rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; text-decoration: none;
    transition: background-color .3s, box-shadow .4s var(--wl-ease), gap .35s var(--wl-ease);
}
.wl-btn i { color: var(--wl-gold); }
.wl-btn:hover { background: var(--wl-brown-2); gap: 16px; box-shadow: 0 16px 26px -16px rgba(44, 33, 27, .7), 0 0 0 4px rgba(198, 167, 106, .2); }
.wl-btn--ghost { background: transparent; color: var(--wl-brown); }
.wl-btn--ghost i { color: var(--wl-gold-tx); }
.wl-btn--ghost:hover { background: var(--wl-brown); color: var(--wl-ivory); }
.wl-btn--ghost:hover i { color: var(--wl-gold); }

/* ---------- Messages (toast visuel + régions aria-live) ---------- */
.wl-sr { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
.wl-toast {
    position: fixed; left: 50%; bottom: 28px; z-index: 1100;
    display: flex; align-items: center; gap: 12px;
    max-width: min(92vw, 460px); padding: 14px 20px; border-radius: 12px;
    background: var(--wl-brown); color: var(--wl-ivory); font-size: .92rem; line-height: 1.4;
    border-left: 3px solid var(--wl-champ);
    box-shadow: 0 24px 44px -20px rgba(44, 33, 27, .7);
    opacity: 0; visibility: hidden; transform: translate(-50%, 14px);
    transition: opacity .35s ease, transform .5s var(--wl-ease), visibility 0s linear .5s;
}
.wl-toast.is-on { opacity: 1; visibility: visible; transform: translate(-50%, 0); transition-delay: 0s; }
.wl-toast i { color: var(--wl-champ); }
.wl-toast.is-error { border-left-color: #E07B72; }
.wl-toast.is-error i { color: #E07B72; }

/* ---------- Focus clavier visible ---------- */
.wishlist-page a:focus-visible, .wishlist-page button:focus-visible {
    outline: 2px solid var(--wl-gold); outline-offset: 3px; border-radius: 6px;
}
.wishlist-remove:focus-visible { border-radius: 50%; }

/* ---------- Animations ---------- */
@keyframes wlRise  { from { opacity: 0; transform: translateY(26px); } }
@keyframes wlFade  { from { opacity: 0; } }
@keyframes wlBump  { 40% { transform: scale(1.18); color: var(--wl-gold-tx); } }

/* ---------- Responsive ---------- */
@media (max-width: 768px) {
    .wishlist-page { padding-top: 84px; }
    .wl-hero { align-items: flex-start; }
    .wishlist-card-content { padding: 20px 18px 18px; }
}
@media (max-width: 520px) {
    .wl-media { aspect-ratio: 5 / 4; }
    .wl-count__num { font-size: 2.2rem; }
    .wl-toast { bottom: 16px; width: calc(100vw - 32px); }
}

/* ---------- Mouvement réduit ---------- */
@media (prefers-reduced-motion: reduce) {
    .wishlist-page *, .wishlist-page *::before, .wishlist-page *::after { animation: none !important; transition-duration: .01ms !important; }
    .wishlist-card:hover, .wishlist-card:focus-within { transform: none; }
    .wishlist-card:hover .wl-media img { transform: none; }
    .wishlist-remove:hover { transform: none; }
}
</style>

<div class="wishlist-page">
    <div class="wishlist-container">

        <!-- ===== En-tête éditorial ===== -->
        <header class="wl-hero">
            <div class="wl-hero__text">
                <p class="wl-eyebrow">Votre sélection personnelle</p>
                <h1 class="wl-title">Mes favoris</h1>
                <p class="wl-lead" id="wlLead">
                    <?php echo $favCount > 0
                        ? 'Les parfums qui vous ont séduit, réunis pour les retrouver à tout moment.'
                        : 'Les parfums qui vous séduiront viendront s\'inscrire ici.'; ?>
                </p>
            </div>
            <div class="wl-count">
                <span class="wl-count__ring" aria-hidden="true"><i class="fas fa-heart"></i></span>
                <span class="wl-count__txt">
                    <span class="wl-count__num" id="wlCount"><?php echo (int) $favCount; ?></span>
                    <span class="wl-count__label" id="wlCountLabel"><?php echo $favCount > 1 ? 'parfums sélectionnés' : 'parfum sélectionné'; ?></span>
                </span>
            </div>
        </header>

        <!-- ===== Grille des favoris ===== -->
        <div class="wishlist-grid" id="wlGrid"<?php echo $favCount === 0 ? ' hidden' : ''; ?>>
            <?php foreach ($favorites as $idx => $product):
                $pid      = (int) $product['id_produit'];
                $nom      = (string) $product['nom'];
                $marque   = (string) ($product['marque'] ?? '');
                $img      = $safeImage($product['image_url'] ?? '');

                // Prix : format le moins cher (cohérent avec le catalogue), sinon prix du produit
                $hasFormat   = isset($product['id_format_min']) && $product['id_format_min'] !== null && $product['id_format_min'] !== '';
                $basePrice   = ($hasFormat && isset($product['prix_min']) && $product['prix_min'] !== null) ? (float) $product['prix_min'] : (float) $product['prix'];
                $nbFormats   = (int) ($product['nb_formats'] ?? 0);
                $discount    = (float) ($product['promotion_pourcentage'] ?? 0);
                $finalPrice  = $discount > 0 ? round($basePrice * (1 - $discount / 100), 2) : $basePrice;
                $discountLbl = rtrim(rtrim(number_format($discount, 2, ',', ''), '0'), ',');

                // Stock (si la colonne est absente, on considère le produit disponible)
                $inStock = !array_key_exists('stock', $product) || (int) $product['stock'] > 0;

                // Description courte, sinon début de la description, sinon texte par défaut
                $desc = trim((string) ($product['description_courte'] ?? ''));
                if ($desc === '') { $desc = trim((string) ($product['description'] ?? '')); }
                if ($desc === '') { $desc = 'Un parfum d\'exception qui saura vous séduire.'; }
                if (function_exists('mb_strimwidth')) { $desc = mb_strimwidth($desc, 0, 140, '…', 'UTF-8'); }

                // Ligne d'infos : concentration · volume du format proposé
                $metaParts = [];
                if (!empty($product['concentration'])) { $metaParts[] = (string) $product['concentration']; }
                $vol = $hasFormat ? (int) ($product['volume_min'] ?? 0) : (int) ($product['volume_ml'] ?? 0);
                if ($vol > 0) { $metaParts[] = $vol . ' ml'; }

                $dateFav = $formatDate($product['date_favori'] ?? '');
            ?>
                <article class="wishlist-card<?php echo $inStock ? '' : ' is-unavailable'; ?>"
                         data-product-id="<?php echo $pid; ?>" style="--i: <?php echo (int) min($idx, 8); ?>">

                    <div class="wl-media<?php echo $img === '' ? ' is-empty' : ''; ?>">
                        <a href="produit.php?id=<?php echo $pid; ?>" class="wishlist-card-image" tabindex="-1">
                            <?php if ($img !== ''): ?>
                                <img src="<?php echo $esc($img); ?>"
                                     alt="<?php echo $esc($nom . ($marque !== '' ? ' – ' . $marque : '')); ?>"
                                     loading="lazy" decoding="async">
                            <?php endif; ?>
                            <div class="wl-media__empty" aria-hidden="true"><i class="fas fa-wine-bottle"></i><span>Lumoura</span></div>
                        </a>

                        <?php if ($discount > 0): ?>
                            <span class="wl-badge" aria-label="Promotion : moins <?php echo $esc($discountLbl); ?> pour cent">−<?php echo $esc($discountLbl); ?>&nbsp;%</span>
                        <?php endif; ?>
                        <?php if (!$inStock): ?>
                            <span class="wl-badge wl-badge--out">Rupture de stock</span>
                        <?php endif; ?>

                        <!-- Retirer : ouvre une confirmation intégrée (pas de confirm() natif) -->
                        <button type="button" class="wishlist-remove" data-product-id="<?php echo $pid; ?>"
                                aria-label="Retirer <?php echo $esc($nom); ?> des favoris" aria-haspopup="true" aria-expanded="false"
                                title="Retirer des favoris">
                            <i class="fas fa-times" aria-hidden="true"></i>
                        </button>

                        <div class="wl-confirm" role="group" aria-label="Confirmer le retrait de <?php echo $esc($nom); ?>">
                            <p>Retirer ce parfum de vos favoris ?</p>
                            <div class="wl-confirm__row">
                                <button type="button" class="wl-keep">Garder</button>
                                <button type="button" class="wl-confirm-yes">Retirer</button>
                            </div>
                        </div>
                    </div>

                    <div class="wishlist-card-content">
                        <div class="wishlist-brand"><?php echo sanitize($marque); ?></div>
                        <h3 class="wishlist-name">
                            <a href="produit.php?id=<?php echo $pid; ?>"><?php echo sanitize($nom); ?></a>
                        </h3>
                        <p class="wishlist-description"><?php echo sanitize($desc); ?></p>
                        <?php if (!empty($metaParts)): ?>
                            <p class="wl-meta"><?php echo $esc(implode(' · ', $metaParts)); ?></p>
                        <?php endif; ?>

                        <!-- Prix : remise calculée à partir du prix du format proposé -->
                        <div class="wishlist-price-row<?php echo $discount > 0 ? ' is-promo' : ''; ?>">
                            <?php if ($nbFormats > 1): ?><span class="wl-from">Dès</span><?php endif; ?>
                            <span class="wishlist-price-current"><?php echo formatPrice($finalPrice); ?></span>
                            <?php if ($discount > 0): ?>
                                <span class="wishlist-price-original"><?php echo formatPrice($basePrice); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="wishlist-actions">
                            <?php if ($inStock): ?>
                                <!-- Ajoute le format le moins cher (le même que le « Dès X € » du catalogue) -->
                                <button type="button" class="wishlist-btn-cart"
                                        data-product-id="<?php echo $pid; ?>"
                                        data-format-id="<?php echo $hasFormat ? (int) $product['id_format_min'] : ''; ?>"
                                        data-product-name="<?php echo $esc($nom); ?>">
                                    <i class="fas fa-shopping-cart" aria-hidden="true"></i><span>Ajouter au panier</span>
                                </button>
                            <?php else: ?>
                                <button type="button" class="wishlist-btn-cart is-unavailable" disabled>
                                    <i class="fas fa-ban" aria-hidden="true"></i><span>Indisponible</span>
                                </button>
                            <?php endif; ?>
                            <a class="wl-link" href="produit.php?id=<?php echo $pid; ?>">
                                <?php echo $nbFormats > 1 ? 'Choisir un autre format' : 'Voir la fiche'; ?> <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>

                        <?php if ($dateFav !== ''): ?>
                            <div class="wishlist-date">Ajouté le <?php echo $esc($dateFav); ?></div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- Lien de poursuite (visible tant qu'il y a des favoris) -->
        <p class="wl-continue" id="wlContinue"<?php echo $favCount === 0 ? ' hidden' : ''; ?>>
            <a href="catalogue.php" class="wl-btn wl-btn--ghost"><i class="fas fa-store" aria-hidden="true"></i> Poursuivre ma découverte</a>
        </p>

        <!-- ===== État vide (aussi affiché dynamiquement après le dernier retrait) ===== -->
        <div class="wishlist-empty" id="wlEmpty"<?php echo $favCount > 0 ? ' hidden' : ''; ?>>
            <span class="wl-empty__heart" aria-hidden="true"><i class="far fa-heart"></i></span>
            <h2 id="wlEmptyTitle" tabindex="-1">Votre sélection commence ici</h2>
            <p>Touchez le cœur d'un parfum pour le garder près de vous. Vos coups de cœur vous attendront ici, prêts à rejoindre votre panier.</p>
            <a href="catalogue.php" class="wl-btn"><i class="fas fa-gem" aria-hidden="true"></i> Découvrir nos parfums</a>
        </div>
    </div>

    <!-- Messages : régions vocalisées + toast visuel -->
    <div class="wl-sr" id="wlStatus" role="status" aria-live="polite" aria-atomic="true"></div>
    <div class="wl-sr" id="wlAlert"  role="alert"  aria-live="assertive" aria-atomic="true"></div>
    <div class="wl-toast" id="wlToast" aria-hidden="true"><i class="fas fa-check-circle"></i><span id="wlToastText"></span></div>
</div>

<script>
(function () {
    'use strict';

    var root = document.querySelector('.wishlist-page');
    if (!root) { return; }

    var grid      = root.querySelector('#wlGrid');
    var emptyBox  = root.querySelector('#wlEmpty');
    var continueP = root.querySelector('#wlContinue');
    var countEl   = root.querySelector('#wlCount');
    var labelEl   = root.querySelector('#wlCountLabel');
    var leadEl    = root.querySelector('#wlLead');
    var statusEl  = root.querySelector('#wlStatus');
    var alertEl   = root.querySelector('#wlAlert');
    var toast     = root.querySelector('#wlToast');
    var toastText = root.querySelector('#wlToastText');
    var reduce    = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var toastTimer = null;

    // ---------- Messages accessibles (aria-live) + toast visuel ----------
    function notify(message, type) {
        var live = (type === 'error') ? alertEl : statusEl;
        live.textContent = '';
        window.setTimeout(function () { live.textContent = message; }, 40); // force la relecture
        toastText.textContent = message;
        toast.classList.toggle('is-error', type === 'error');
        toast.querySelector('i').className = type === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
        toast.classList.add('is-on');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function () { toast.classList.remove('is-on'); }, type === 'error' ? 5200 : 3200);
    }

    // ---------- Requêtes (JSON robuste) ----------
    function postForm(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
            credentials: 'same-origin'
        }).then(function (response) { return response.json(); });
    }
    function toggleFavorite(productId) {
        return postForm('toggle_wishlist.php', 'product_id=' + encodeURIComponent(productId));
    }

    // ---------- Compteurs ----------
    function setCount(n, serverCount) {
        countEl.textContent = n;
        countEl.classList.remove('is-bump');
        void countEl.offsetWidth;
        countEl.classList.add('is-bump');
        labelEl.textContent = n > 1 ? 'parfums sélectionnés' : 'parfum sélectionné';
        leadEl.textContent  = n > 0
            ? 'Les parfums qui vous ont séduit, réunis pour les retrouver à tout moment.'
            : 'Les parfums qui vous séduiront viendront s\'inscrire ici.';
        // Compteur de favoris du header principal (valeur du serveur si fournie)
        document.querySelectorAll('.wishlist-count').forEach(function (el) {
            el.textContent = (serverCount !== undefined && serverCount !== null) ? serverCount : n;
        });
    }

    function showEmptyState() {
        grid.hidden = true;
        if (continueP) { continueP.hidden = true; }
        emptyBox.hidden = false;
        emptyBox.style.animation = 'none';
        void emptyBox.offsetWidth;
        emptyBox.style.animation = '';
        var title = root.querySelector('#wlEmptyTitle');
        if (title) { title.focus({ preventScroll: true }); }
        notify('Votre liste de favoris est maintenant vide.');
    }

    // ---------- Retrait d'une carte (animation + réorganisation fluide de la grille) ----------
    function removeCard(card) {
        var others = Array.prototype.filter.call(grid.querySelectorAll('.wishlist-card'), function (c) { return c !== card; });
        var firstRects = others.map(function (c) { return c.getBoundingClientRect(); });
        card.remove();
        if (!reduce && card.animate) {
            others.forEach(function (c, i) {
                var b = c.getBoundingClientRect(), a = firstRects[i];
                var dx = a.left - b.left, dy = a.top - b.top;
                if (dx || dy) {
                    c.animate([{ transform: 'translate(' + dx + 'px,' + dy + 'px)' }, { transform: 'none' }],
                              { duration: 460, easing: 'cubic-bezier(.22,1,.36,1)' });
                }
            });
        }
    }

    // ---------- Confirmation intégrée ----------
    function closeConfirm(card, restoreFocus) {
        if (!card || !card.classList.contains('is-confirming')) { return; }
        card.classList.remove('is-confirming');
        var rm = card.querySelector('.wishlist-remove');
        rm.setAttribute('aria-expanded', 'false');
        if (restoreFocus) { rm.focus(); }
    }
    function closeAllConfirms(except) {
        grid.querySelectorAll('.wishlist-card.is-confirming').forEach(function (c) {
            if (c !== except) { closeConfirm(c, false); }
        });
    }
    function openConfirm(card) {
        closeAllConfirms(card);
        card.classList.add('is-confirming');
        card.querySelector('.wishlist-remove').setAttribute('aria-expanded', 'true');
        // Focus sur « Garder » : évite toute suppression accidentelle au clavier
        window.setTimeout(function () { var keep = card.querySelector('.wl-keep'); if (keep) { keep.focus(); } }, 60);
    }

    function confirmRemove(card, yesBtn) {
        var productId = card.getAttribute('data-product-id');
        var keepBtn   = card.querySelector('.wl-keep');
        var originalHTML = yesBtn.innerHTML;

        yesBtn.disabled = true; keepBtn.disabled = true;
        yesBtn.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i>';

        function restore() {
            yesBtn.disabled = false; keepBtn.disabled = false;
            yesBtn.innerHTML = originalHTML;
        }

        toggleFavorite(productId)
            .then(function (data) {
                // Page périmée : le produit n'était déjà plus en favoris → le toggle vient de le RÉ-ajouter.
                // On repasse une fois pour revenir à l'état « retiré ».
                if (data && data.success && data.action === 'added') { return toggleFavorite(productId); }
                return data;
            })
            .then(function (data) {
                if (data && data.success && data.action === 'removed') {
                    card.classList.remove('is-confirming');
                    card.classList.add('is-leaving');
                    var remaining = grid.querySelectorAll('.wishlist-card').length - 1;
                    window.setTimeout(function () {
                        removeCard(card);
                        setCount(remaining, data.wishlist_count);
                        if (remaining === 0) {
                            showEmptyState();
                        } else {
                            notify('Parfum retiré de vos favoris.');
                        }
                    }, reduce ? 0 : 420);
                } else if (data && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    restore();
                    notify((data && data.message) || 'Impossible de retirer ce parfum pour le moment. Veuillez réessayer.', 'error');
                }
            })
            .catch(function (err) {
                console.error('Erreur retrait favori :', err);
                restore();
                notify('Erreur de connexion. Veuillez réessayer.', 'error');
            });
    }

    // ---------- Ajout au panier ----------
    function addToCart(btn) {
        var productId  = btn.getAttribute('data-product-id');
        var formatId   = btn.getAttribute('data-format-id');
        var name       = btn.getAttribute('data-product-name') || 'Le parfum';
        var originalHTML = btn.innerHTML;

        // Le format le moins cher est envoyé s'il existe (même règle que catalogue.php).
        var body = 'product_id=' + encodeURIComponent(productId) + '&quantity=1';
        if (formatId) { body += '&format_id=' + encodeURIComponent(formatId); }

        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i><span>Ajout…</span>';

        function reset(delay) {
            window.setTimeout(function () {
                btn.innerHTML = originalHTML;
                btn.classList.remove('is-success', 'is-error');
                btn.removeAttribute('aria-busy');
                btn.disabled = false;
            }, delay);
        }

        postForm('ajouter_au_panier.php', body)
            .then(function (data) {
                if (data && data.success) {
                    btn.classList.add('is-success');
                    btn.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i><span>Ajouté</span>';
                    document.querySelectorAll('.cart-count, #cartCount').forEach(function (el) {
                        el.textContent = (data.cart_count !== undefined) ? data.cart_count : ((parseInt(el.textContent, 10) || 0) + 1);
                        el.style.display = 'inline-flex';
                        el.style.transition = 'transform .3s ease';
                        el.style.transform = 'scale(1.4)';
                        window.setTimeout(function () { el.style.transform = 'scale(1)'; }, 300);
                    });
                    notify('« ' + name + ' » a été ajouté à votre panier.');
                    reset(2200);
                } else {
                    var msg = (data && data.message) || 'Impossible d\'ajouter ce parfum au panier.';
                    btn.classList.add('is-error');
                    btn.innerHTML = '<i class="fas fa-exclamation-triangle" aria-hidden="true"></i><span>Erreur</span>';
                    notify(msg, 'error');
                    reset(2200);
                    if (/connecter/i.test(msg)) { window.setTimeout(function () { window.location.href = 'connexion.php'; }, 1600); }
                }
            })
            .catch(function (err) {
                console.error('Erreur ajout panier :', err);
                btn.classList.add('is-error');
                btn.innerHTML = '<i class="fas fa-exclamation-triangle" aria-hidden="true"></i><span>Erreur</span>';
                notify('Erreur de connexion. Veuillez réessayer.', 'error');
                reset(2200);
            });
    }

    // ---------- Délégation d'événements (un seul écouteur pour toute la page) ----------
    root.addEventListener('click', function (e) {
        var t = e.target;
        var removeBtn = t.closest('.wishlist-remove');
        var keepBtn   = t.closest('.wl-keep');
        var yesBtn    = t.closest('.wl-confirm-yes');
        var cartBtn   = t.closest('.wishlist-btn-cart');

        if (removeBtn) {
            e.preventDefault();
            var card = removeBtn.closest('.wishlist-card');
            if (card.classList.contains('is-confirming')) { closeConfirm(card, true); } else { openConfirm(card); }
        } else if (keepBtn) {
            closeConfirm(keepBtn.closest('.wishlist-card'), true);
        } else if (yesBtn) {
            confirmRemove(yesBtn.closest('.wishlist-card'), yesBtn);
        } else if (cartBtn && !cartBtn.disabled) {
            e.preventDefault();
            addToCart(cartBtn);
        } else if (!t.closest('.wl-confirm')) {
            closeAllConfirms(null);   // clic ailleurs : on referme les confirmations
        }
    });

    // Échap : referme la confirmation et rend le focus au bouton
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        var open = grid.querySelector('.wishlist-card.is-confirming');
        if (open) { closeConfirm(open, true); }
    });

    // ---------- Images absentes ou invalides → présentation élégante ----------
    root.querySelectorAll('.wl-media img').forEach(function (img) {
        var box = img.closest('.wl-media');
        function broken() { box.classList.add('is-empty'); }
        if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) { broken(); }
        img.addEventListener('error', broken);
    });
})();
</script>

<?php include '../includes/footer.php'; ?>