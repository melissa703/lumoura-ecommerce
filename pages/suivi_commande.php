<?php
// ============================================================
// FICHIER : suivi_commande.php
// RÔLE    : Page de suivi détaillé d'une commande
// ACCÈS   : suivi_commande.php?order_id=42
// ============================================================

session_start();

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "pages/connexion.php");
    exit();
}

$user_id  = (int) $_SESSION['user_id'];
$order_id = (int) ($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    header("Location: compte.php");
    exit();
}

// Récupérer la commande (sécurité : appartient bien à cet utilisateur)
try {
    $order_stmt = $pdo->prepare("
        SELECT c.*, u.prenom, u.nom, u.email
        FROM commandes c
        JOIN utilisateurs u ON c.id_utilisateur = u.id_utilisateur
        WHERE c.id_commande = ? AND c.id_utilisateur = ?
    ");
    $order_stmt->execute([$order_id, $user_id]);
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header("Location: compte.php");
        exit();
    }

    // Récupérer les articles de la commande
    $items_stmt = $pdo->prepare("
        SELECT dc.quantite, dc.prix_unitaire, p.nom, p.image_url, p.marque, p.id_produit
        FROM details_commande dc
        JOIN produits p ON dc.id_produit = p.id_produit
        WHERE dc.id_commande = ?
    ");
    $items_stmt->execute([$order_id]);
    $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer le livreur si assigné
    $livreur = null;
    if (!empty($order['id_livreur'])) {
        $liv_stmt = $pdo->prepare("SELECT * FROM livreurs WHERE id_livreur = ?");
        $liv_stmt->execute([$order['id_livreur']]);
        $livreur = $liv_stmt->fetch(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    header("Location: compte.php");
    exit();
}

// Définition des étapes et statuts
// Le paiement est toujours effectué au moment de la commande.
// Les vraies étapes sont donc : Commande → Préparation → Expédiée → Livrée
$statuts_config = [
    'en attente' => ['label' => 'En attente',  'couleur' => '#f39c12', 'etape' => 1, 'icon' => '⏳'],
    'payee'      => ['label' => 'En préparation', 'couleur' => '#3498db', 'etape' => 2, 'icon' => '🔧'],
    'expediee'   => ['label' => 'Expédiée',    'couleur' => '#9b59b6', 'etape' => 3, 'icon' => '📦'],
    'livree'     => ['label' => 'Livrée',      'couleur' => '#27ae60', 'etape' => 4, 'icon' => '✅'],
    'annulee'    => ['label' => 'Annulée',     'couleur' => '#e74c3c', 'etape' => 0, 'icon' => '❌'],
];

$etapes = [
    1 => ['label' => 'Commande passée',    'icon' => '🛒', 'desc' => 'Votre commande a bien été enregistrée et votre paiement confirmé.'],
    2 => ['label' => 'En préparation',     'icon' => '🔧', 'desc' => 'Nos équipes préparent votre colis avec soin.'],
    3 => ['label' => 'Expédiée',           'icon' => '📦', 'desc' => 'Votre colis est en route vers vous.'],
    4 => ['label' => 'Livrée',             'icon' => '🏠', 'desc' => 'Votre commande a été livrée. Bonne dégustation !'],
];

$statut_actuel = $statuts_config[$order['statut']] ?? ['label' => 'En attente', 'couleur' => '#f39c12', 'etape' => 1, 'icon' => '⏳'];
$etape_actuelle = $statut_actuel['etape'];
$est_annulee    = ($order['statut'] === 'annulee');

// Calcul du sous-total articles
$sous_total = array_sum(array_map(fn($i) => $i['quantite'] * $i['prix_unitaire'], $order_items));

$pageTitle = "Suivi commande " . htmlspecialchars($order['numero_commande']) . " — Lumoura";
include '../includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
.suivi-wrapper { padding-top: 120px; min-height: 80vh; background: #fdf8f0; padding-bottom: 60px; }

/* Fil d'Ariane */
.breadcrumb-lumoura { font-size: 0.88rem; color: #aaa; margin-bottom: 28px; display: flex; align-items: center; gap: 8px; }
.breadcrumb-lumoura a { color: #cc8800; text-decoration: none; }
.breadcrumb-lumoura a:hover { text-decoration: underline; }

/* En-tête commande */
.order-header { background: white; border-radius: 16px; padding: 28px 32px; margin-bottom: 24px; border: 1px solid #e8dcc8; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; }
.order-number { font-size: 1.3rem; font-weight: 700; color: #2c2c2c; }
.order-date { font-size: 0.88rem; color: #888; margin-top: 4px; }
.badge-statut { font-size: 0.9rem; padding: 7px 18px; border-radius: 20px; font-weight: 700; color: white; display: inline-flex; align-items: center; gap: 7px; }

/* Barre de progression */
.progress-card { background: white; border-radius: 16px; padding: 32px; margin-bottom: 24px; border: 1px solid #e8dcc8; }
.progress-card h3 { font-size: 1.1rem; font-weight: 700; color: #2c2c2c; margin-bottom: 28px; display: flex; align-items: center; gap: 10px; }

.suivi-bar { display: flex; align-items: flex-start; justify-content: space-between; position: relative; padding: 0 16px; margin-bottom: 16px; }
.suivi-bar::before { content: ''; position: absolute; top: 20px; left: calc(16px + 20px); right: calc(16px + 20px); height: 4px; background: #e8dcc8; border-radius: 2px; z-index: 0; }
.progress-fill-bar { position: absolute; top: 20px; left: calc(16px + 20px); height: 4px; background: linear-gradient(90deg, #cc8800, #e09c00); border-radius: 2px; z-index: 1; transition: width 0.8s ease; }

.suivi-step { display: flex; flex-direction: column; align-items: center; gap: 10px; z-index: 2; flex: 1; }
.step-circle { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; border: 3px solid #e8dcc8; background: white; transition: all 0.4s; }
.step-circle.done   { background: #fff8ec; border-color: #cc8800; }
.step-circle.active { background: #cc8800; border-color: #cc8800; box-shadow: 0 0 0 5px rgba(204,136,0,0.15); }
.step-circle.active .step-emoji { filter: brightness(0) invert(1); font-style: normal; }
.step-label { font-size: 0.78rem; text-align: center; color: #aaa; max-width: 90px; line-height: 1.4; font-weight: 500; }
.step-label.done   { color: #888; }
.step-label.active { color: #cc8800; font-weight: 700; }

.step-desc { background: #fffbf0; border: 1px solid #f0e0c0; border-radius: 10px; padding: 14px 18px; font-size: 0.88rem; color: #7a5c20; display: flex; align-items: center; gap: 10px; margin-top: 8px; }

/* Message annulée */
.annulee-banner { background: #fff5f5; border: 1px solid #fdd; border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; display: flex; align-items: center; gap: 14px; color: #c0392b; }
.annulee-banner .icon { font-size: 2rem; flex-shrink: 0; }

/* Grille contenu */
.content-grid { display: grid; grid-template-columns: 1fr 360px; gap: 24px; }
@media (max-width: 768px) { .content-grid { grid-template-columns: 1fr; } }

/* Carte articles */
.articles-card { background: white; border-radius: 16px; padding: 28px; border: 1px solid #e8dcc8; }
.articles-card h3 { font-size: 1.1rem; font-weight: 700; color: #2c2c2c; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

.article-row { display: flex; align-items: center; gap: 16px; padding: 14px 0; border-bottom: 1px solid #f5f0e8; }
.article-row:last-child { border-bottom: none; }
.article-img { width: 64px; height: 64px; object-fit: cover; border-radius: 10px; border: 1px solid #ece0c8; flex-shrink: 0; }
.article-img-placeholder { width: 64px; height: 64px; border-radius: 10px; background: #f0e8d8; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
.article-name { font-weight: 600; font-size: 0.95rem; color: #2c2c2c; }
.article-brand { font-size: 0.82rem; color: #aaa; margin-top: 2px; }
.article-qty { font-size: 0.82rem; color: #888; margin-top: 4px; }
.article-price { margin-left: auto; font-weight: 700; color: #cc8800; font-size: 1rem; white-space: nowrap; }

/* Carte récapitulatif */
.recap-card { background: white; border-radius: 16px; padding: 28px; border: 1px solid #e8dcc8; height: fit-content; }
.recap-card h3 { font-size: 1.1rem; font-weight: 700; color: #2c2c2c; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.recap-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; font-size: 0.9rem; color: #666; border-bottom: 1px solid #f5f0e8; }
.recap-row:last-child { border-bottom: none; }
.recap-row.total { font-size: 1.1rem; font-weight: 700; color: #2c2c2c; padding-top: 16px; margin-top: 8px; border-top: 2px solid #e8dcc8; border-bottom: none; }
.info-pill { display: inline-flex; align-items: center; gap: 6px; background: #f5efe4; color: #7a5c20; border-radius: 20px; padding: 6px 14px; font-size: 0.82rem; width: 100%; margin-bottom: 8px; }

/* Carte livreur */
.livreur-card { background: #fffbf0; border-radius: 16px; padding: 22px; border: 1px solid #f0e0c0; margin-top: 16px; }
.livreur-card h4 { font-size: 0.95rem; font-weight: 700; color: #7a5c20; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }

/* Bouton retour */
.btn-back { display: inline-flex; align-items: center; gap: 8px; background: none; border: 1px solid #e0c88a; color: #cc8800; border-radius: 20px; padding: 8px 20px; font-size: 0.88rem; font-weight: 600; text-decoration: none; transition: all 0.2s; }
.btn-back:hover { background: #fff3d6; color: #cc8800; }
</style>

<div class="suivi-wrapper">
    <div class="container" style="max-width: 1000px;">

        <!-- Fil d'Ariane -->
        <div class="breadcrumb-lumoura">
            <a href="compte.php"><i class="bi bi-house"></i> Mon compte</a>
            <span>›</span>
            <span>Suivi commande</span>
            <span>›</span>
            <span style="color: #cc8800;"><?= htmlspecialchars($order['numero_commande']) ?></span>
        </div>

        <!-- En-tête -->
        <div class="order-header">
            <div>
                <div class="order-number">
                    <i class="bi bi-receipt" style="color:#cc8800; margin-right:8px;"></i>
                    <?= htmlspecialchars($order['numero_commande']) ?>
                </div>
                <div class="order-date">
                    Passée le <?= date('d/m/Y à H:i', strtotime($order['date_commande'])) ?>
                    &nbsp;·&nbsp;
                    <?= count($order_items) ?> article<?= count($order_items) > 1 ? 's' : '' ?>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <span class="badge-statut" style="background: <?= $statut_actuel['couleur'] ?>;">
                    <?= $statut_actuel['icon'] ?> <?= $statut_actuel['label'] ?>
                </span>
                <a href="compte.php" class="btn-back">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>

        <!-- Message si annulée -->
        <?php if ($est_annulee): ?>
        <div class="annulee-banner">
            <div class="icon">❌</div>
            <div>
                <strong>Cette commande a été annulée.</strong><br>
                <span style="font-size:0.88rem;">Le stock des produits a été remis à disposition. Si vous avez été débité, un remboursement sera effectué sous 3 à 5 jours ouvrés.</span>
            </div>
        </div>
        <?php else: ?>

        <!-- Barre de progression -->
        <div class="progress-card">
            <h3><i class="bi bi-truck" style="color:#cc8800;"></i> Suivi de votre commande</h3>

            <div class="suivi-bar">
                <?php
                $nb_etapes = count($etapes);
                $pct = $etape_actuelle > 1
                    ? (($etape_actuelle - 1) / ($nb_etapes - 1)) * 100
                    : 0;
                ?>
                <div class="progress-fill-bar" style="width: calc(<?= $pct ?>% * (100% - 88px) / 100);"></div>

                <?php foreach ($etapes as $num => $etape):
                    if ($num < $etape_actuelle)       $classe = 'done';
                    elseif ($num === $etape_actuelle) $classe = 'active';
                    else                               $classe = '';
                ?>
                <div class="suivi-step">
                    <div class="step-circle <?= $classe ?>">
                        <?php if ($num < $etape_actuelle): ?>
                            <i class="bi bi-check-lg" style="color:#cc8800; font-weight:700;"></i>
                        <?php else: ?>
                            <span class="step-emoji"><?= $etape['icon'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="step-label <?= $classe ?>"><?= htmlspecialchars($etape['label']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Description de l'étape actuelle -->
            <?php if (isset($etapes[$etape_actuelle])): ?>
            <div class="step-desc">
                <span style="font-size:1.4rem;"><?= $etapes[$etape_actuelle]['icon'] ?></span>
                <div>
                    <strong><?= htmlspecialchars($etapes[$etape_actuelle]['label']) ?></strong><br>
                    <?= htmlspecialchars($etapes[$etape_actuelle]['desc']) ?>
                    <?php if ($etape_actuelle === 1): ?>
                        — Estimation de livraison : <strong>3 à 5 jours ouvrés</strong>
                    <?php elseif ($etape_actuelle === 2): ?>
                        — Expédition prévue sous <strong>24 à 48h</strong>
                    <?php elseif ($etape_actuelle === 3): ?>
                        — Livraison estimée sous <strong>1 à 2 jours ouvrés</strong>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Grille articles + récapitulatif -->
        <div class="content-grid">

            <!-- Articles -->
            <div class="articles-card">
                <h3><i class="bi bi-bag" style="color:#cc8800;"></i> Articles commandés</h3>

                <?php foreach ($order_items as $item): ?>
                <div class="article-row">
                    <?php if (!empty($item['image_url'])): ?>
                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['nom']) ?>" class="article-img">
                    <?php else: ?>
                        <div class="article-img-placeholder">🌸</div>
                    <?php endif; ?>

                    <div style="flex:1;">
                        <div class="article-name"><?= htmlspecialchars($item['nom']) ?></div>
                        <div class="article-brand"><?= htmlspecialchars($item['marque']) ?></div>
                        <div class="article-qty">Quantité : <?= $item['quantite'] ?> × <?= number_format($item['prix_unitaire'], 2, ',', ' ') ?> €</div>
                    </div>
                    <div class="article-price">
                        <?= number_format($item['quantite'] * $item['prix_unitaire'], 2, ',', ' ') ?> €
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Récapitulatif -->
            <div>
                <div class="recap-card">
                    <h3><i class="bi bi-receipt" style="color:#cc8800;"></i> Récapitulatif</h3>

                    <div class="recap-row">
                        <span>Sous-total</span>
                        <span><?= number_format($sous_total, 2, ',', ' ') ?> €</span>
                    </div>
                    <div class="recap-row">
                        <span>Livraison</span>
                        <span><?= $order['frais_livraison'] > 0 ? number_format($order['frais_livraison'], 2, ',', ' ') . ' €' : '<span style="color:#27ae60; font-weight:600;">Gratuite</span>' ?></span>
                    </div>
                    <div class="recap-row total">
                        <span>Total</span>
                        <span style="color:#cc8800;"><?= number_format($order['montant'], 2, ',', ' ') ?> €</span>
                    </div>

                    <!-- Infos paiement & livraison -->
                    <div style="margin-top: 20px;">
                        <?php if (!empty($order['mode_paiement'])): ?>
                        <div class="info-pill">
                            <i class="bi bi-credit-card"></i>
                            <?php
                            $modes = ['carte' => 'Carte bancaire', 'paypal' => 'PayPal', 'apple_pay' => 'Apple Pay'];
                            echo htmlspecialchars($modes[$order['mode_paiement']] ?? $order['mode_paiement']);
                            ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($order['mode_livraison'])): ?>
                        <div class="info-pill">
                            <i class="bi bi-truck"></i>
                            <?php
                            $modes_liv = ['standard' => 'Livraison Standard (3-5j)', 'express' => 'Express (lendemain)', 'click_collect' => 'Click & Collect'];
                            echo htmlspecialchars($modes_liv[$order['mode_livraison']] ?? $order['mode_livraison']);
                            ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($order['adresse_livraison'])): ?>
                        <div class="info-pill" style="align-items:flex-start;">
                            <i class="bi bi-geo-alt" style="margin-top:2px; flex-shrink:0;"></i>
                            <span><?= htmlspecialchars($order['adresse_livraison']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Livreur assigné -->
                <?php if ($livreur): ?>
                <div class="livreur-card">
                    <h4><i class="bi bi-person-badge"></i> Votre livreur</h4>
                    <div style="font-weight:600; color:#2c2c2c; margin-bottom:6px;">
                        <?= htmlspecialchars($livreur['prenom'] . ' ' . $livreur['nom']) ?>
                    </div>
                    <div style="font-size:0.85rem; color:#888; display:flex; flex-direction:column; gap:5px;">
                        <span><i class="bi bi-telephone me-2" style="color:#cc8800;"></i><?= htmlspecialchars($livreur['telephone']) ?></span>
                        <span><i class="bi bi-scooter me-2" style="color:#cc8800;"></i><?= htmlspecialchars($livreur['vehicule']) ?></span>
                        <span><i class="bi bi-map me-2" style="color:#cc8800;"></i><?= htmlspecialchars($livreur['zone_livraison']) ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Facture -->
                <div style="margin-top:16px;">
                    <a href="facture.php?order_id=<?= $order_id ?>" target="_blank"
                       style="display:flex; align-items:center; justify-content:center; gap:8px; background:linear-gradient(135deg,#d4a017,#b8860b); color:white; padding:12px; border-radius:12px; font-size:0.9rem; font-weight:600; text-decoration:none; transition:all 0.2s;"
                       onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(212,160,23,0.4)'"
                       onmouseout="this.style.transform=''; this.style.boxShadow=''">
                        📄 Télécharger la facture PDF
                    </a>
                </div>
            </div>

        </div><!-- fin content-grid -->

    </div><!-- fin container -->
</div><!-- fin suivi-wrapper -->



<?php include '../includes/footer.php'; ?>