<?php
// ============================================================
// FICHIER : pages/quiz-resultats.php
// RÔLE    : Page de résultats du quiz olfactif.
//           Reçoit les 4 réponses en GET depuis quiz.php, les VALIDE côté
//           serveur (liste blanche du moteur), calcule les recommandations
//           avec quiz_scoring.php et les affiche.
//           Lecture seule : aucune écriture en base.
// ============================================================

// --- Ordre d'inclusion imposé par header.php : config -> db -> functions -> header ---
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/quiz_scoring.php';

// Page produit : adapte ce lien à ta page détail (ex. 'produit.php?id=')
$lienProduit = 'produit.php?id=';

// ------------------------------------------------------------
// 1. Validation (jamais confiance au client) : réponse invalide -> retour au quiz
// ------------------------------------------------------------
$validation = quiz_valider_reponses($_GET);
if (!$validation['ok']) {
    header('Location: quiz.php');
    exit;
}
$reponses = $validation['reponses'];

// ------------------------------------------------------------
// 2. Recommandation
// ------------------------------------------------------------
try {
    $produits = quiz_charger_produits($pdo);
    $reco     = quiz_recommander($reponses, $produits);
} catch (PDOException $e) {
    $reco = [
        'statut'         => 'erreur',
        'resultats'      => [],
        'hors_budget'    => [],
        'budget_suggere' => null,
        'message'        => "Le service est momentanément indisponible. Réessayez dans un instant.",
    ];
}

// ------------------------------------------------------------
// 3. Libellés d'affichage (mêmes textes que quiz.php)
// ------------------------------------------------------------
$libFamille   = ['florale' => 'Florale', 'boisee' => 'Boisée', 'fruitee_gourmande' => 'Fruitée et gourmande',
                 'hesperidee' => 'Hespéridée', 'orientale' => 'Orientale', 'aquatique_aromatique' => 'Aquatique et aromatique'];
$libAmbiance  = ['elegante' => 'Élégante', 'douce' => 'Douce', 'fraiche' => 'Fraîche', 'sensuelle' => 'Sensuelle', 'intense' => 'Intense'];
$libIntensite = ['discrete' => 'Discrète', 'moderee' => 'Modérée', 'intense' => 'Intense'];
$libBudget    = ['50' => 'Jusqu\'à 50 €', '100' => 'Jusqu\'à 100 €', '150' => 'Jusqu\'à 150 €', 'illimite' => 'Sans limite'];

// ------------------------------------------------------------
// 4. Petites fonctions d'affichage (noms préfixés qr_ pour éviter tout conflit)
// ------------------------------------------------------------
function qr_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function qr_prix($v): string { return number_format((float)$v, 2, ',', ' ') . ' €'; }

/** Une carte parfum. $rang = 1..3 (0 = pas de rang affiché). */
function qr_carte(array $r, int $rang, string $lienProduit, array $libAmbiance, array $reponses): void
{
    $id    = (int)$r['id_produit'];
    $pct   = max(0, min(100, (int)$r['affinite']));
    $promo = (float)$r['promotion'];
    $f     = $r['format_recommande'];
    $rangs = [1 => 'Notre premier choix', 2 => 'Deuxième choix', 3 => 'Troisième choix'];

    // Critères réellement remplis (transparence du moteur)
    $criteres = [];
    if ($r['famille_relation'] === 'exacte')   { $criteres[] = 'Famille olfactive exacte'; }
    if ($r['famille_relation'] === 'voisine')  { $criteres[] = 'Famille olfactive proche'; }
    if ($r['details']['ambiance'] > 0)         { $criteres[] = 'Ambiance ' . mb_strtolower($libAmbiance[$reponses['ambiance']] ?? ''); }
    if ($r['details']['intensite'] >= 15)      { $criteres[] = 'Intensité idéale'; }
    elseif ($r['details']['intensite'] > 0)    { $criteres[] = 'Intensité proche'; }
    if ($r['dans_budget'])                     { $criteres[] = 'Dans votre budget'; }
    ?>
    <article class="qr-card<?php echo $rang === 1 ? ' qr-card--top' : ''; ?>">
        <a class="qr-media" href="<?php echo qr_h($lienProduit . $id); ?>" tabindex="-1" aria-hidden="true">
            <?php if (!empty($r['image_url'])): ?>
                <img src="<?php echo qr_h($r['image_url']); ?>" alt="" loading="lazy">
            <?php endif; ?>
            <span class="qr-badges">
                <?php if ($rang && isset($rangs[$rang])): ?><span class="qr-badge qr-badge-rank"><?php echo qr_h($rangs[$rang]); ?></span><?php endif; ?>
                <?php if ($promo > 0): ?><span class="qr-badge">-<?php echo qr_h(rtrim(rtrim(number_format($promo, 1, ',', ''), '0'), ',')); ?> %</span><?php endif; ?>
                <?php if (!empty($r['bestseller'])): ?><span class="qr-badge">Best-seller</span><?php endif; ?>
                <?php if (!empty($r['dernieres_pieces'])): ?><span class="qr-badge">Dernières pièces</span><?php endif; ?>
            </span>
        </a>

        <div class="qr-body">
            <div class="qr-ring" style="--pct: <?php echo $pct; ?>" role="img" aria-label="Affinité : <?php echo $pct; ?> pour cent">
                <span><?php echo $pct; ?><small>%</small></span>
            </div>

            <h3 class="qr-name"><?php echo qr_h($r['nom']); ?></h3>
            <p class="qr-meta"><?php echo qr_h($r['famille_olfactive']); ?><?php echo !empty($r['concentration']) ? ' · ' . qr_h($r['concentration']) : ''; ?></p>
            <?php if (!empty($r['description_courte'])): ?>
                <p class="qr-desc"><?php echo qr_h($r['description_courte']); ?></p>
            <?php endif; ?>

            <?php if ($criteres): ?>
                <ul class="qr-chips">
                    <?php foreach ($criteres as $c): ?><li><?php echo qr_h($c); ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="qr-price">
                <?php if ($f): ?>
                    <span class="qr-price-label"><?php echo (int)$f['volume_ml']; ?> ml</span>
                    <span class="qr-price-now"><?php echo qr_prix($f['prix_final']); ?></span>
                    <?php if ($promo > 0): ?><s class="qr-price-old"><?php echo qr_prix($f['prix']); ?></s><?php endif; ?>
                <?php else: ?>
                    <span class="qr-price-label">Dès</span>
                    <span class="qr-price-now"><?php echo qr_prix($r['prix_des']); ?></span>
                <?php endif; ?>
            </div>

            <a class="quiz-btn quiz-btn-primary qr-cta" href="<?php echo qr_h($lienProduit . $id); ?>">Découvrir ce parfum</a>
        </div>
    </article>
    <?php
}

$pageTitle = 'Vos parfums';
include '../includes/header.php';

// Lien « refaire » et lien « élargir le budget » (valeurs déjà validées)
$lienBudget = null;
if (!empty($reco['budget_suggere']) && isset($libBudget[$reco['budget_suggere']])) {
    $lienBudget = 'quiz-resultats.php?' . http_build_query(array_merge($reponses, ['budget' => $reco['budget_suggere']]));
}
?>
<link rel="stylesheet" href="<?php echo qr_h(SITE_URL); ?>assets/css/quiz.css">

<section class="quiz qr" id="quizResultats">

    <header class="qr-head">
        <p class="quiz-eyebrow">Votre sélection</p>
        <h1 class="quiz-title qr-title">
            <?php echo $reco['statut'] === 'ok' ? 'Vos parfums sont trouvés' : 'Votre sélection olfactive'; ?>
        </h1>
        <span class="quiz-rule qr-rule" aria-hidden="true"></span>
        <p class="quiz-lead qr-lead"><?php echo qr_h($reco['message']); ?></p>

        <ul class="qr-recap" aria-label="Vos réponses">
            <li><?php echo qr_h($libFamille[$reponses['famille']] ?? ''); ?></li>
            <li><?php echo qr_h($libAmbiance[$reponses['ambiance']] ?? ''); ?></li>
            <li>Intensité <?php echo qr_h(mb_strtolower($libIntensite[$reponses['intensite']] ?? '')); ?></li>
            <li><?php echo qr_h($libBudget[$reponses['budget']] ?? ''); ?></li>
        </ul>
    </header>

    <?php if ($reco['resultats']): ?>
        <div class="qr-grid">
            <?php foreach ($reco['resultats'] as $i => $r) { qr_carte($r, $i + 1, $lienProduit, $libAmbiance, $reponses); } ?>
        </div>
    <?php endif; ?>

    <?php if ($reco['hors_budget']): ?>
        <div class="qr-section">
            <h2 class="qr-subtitle">Un peu au-delà de votre budget</h2>
            <p class="qr-subtext">Ces parfums vous correspondent très bien, mais dépassent le plafond choisi.</p>
            <div class="qr-grid qr-grid--soft">
                <?php foreach ($reco['hors_budget'] as $r) { qr_carte($r, 0, $lienProduit, $libAmbiance, $reponses); } ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="qr-actions">
        <?php if ($lienBudget): ?>
            <a class="quiz-btn quiz-btn-primary" href="<?php echo qr_h($lienBudget); ?>">Élargir à « <?php echo qr_h($libBudget[$reco['budget_suggere']]); ?> »</a>
        <?php endif; ?>
        <a class="quiz-btn quiz-btn-ghost" href="quiz.php">Refaire le quiz</a>
    </div>
</section>

<script src="<?php echo qr_h(SITE_URL); ?>assets/js/quiz-premium.js" defer></script>

<?php include '../includes/footer.php'; ?>