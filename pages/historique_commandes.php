<?php
// ============================================================
// FICHIER : historique_commandes.php
// RÔLE    : Afficher l'historique des commandes de l'utilisateur
// CORRECTIONS :
//   1. Statuts corrigés avec espace ('en attente' au lieu de 'en_attente')
//   2. Calcul du total corrigé (montant inclut déjà les frais)
//   3. Bouton d'annulation pour les commandes 'en attente'
//   4. Barre de progression du suivi de commande
//   5. Affichage du mode de livraison
// ============================================================

session_start();

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Vérification connexion
if (!isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "pages/connexion.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// ============================================================
// TRAITEMENT : Annulation d'une commande
// ============================================================
$cancel_message = '';
$cancel_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annuler_commande'])) {
    $id_commande = (int) ($_POST['id_commande'] ?? 0);

    if ($id_commande > 0) {
        try {
            // Vérifier que la commande appartient bien à cet utilisateur ET est en attente
            $check = $pdo->prepare("
                SELECT id_commande FROM commandes
                WHERE id_commande = ? AND id_utilisateur = ? AND statut = 'en attente'
            ");
            $check->execute([$id_commande, $user_id]);

            if ($check->fetch()) {
                // Remettre le stock des produits commandés avant d'annuler
                $articles = $pdo->prepare("
                    SELECT id_produit, quantite FROM details_commande WHERE id_commande = ?
                ");
                $articles->execute([$id_commande]);
                foreach ($articles->fetchAll() as $art) {
                    $pdo->prepare("UPDATE produits SET stock = stock + ? WHERE id_produit = ?")
                        ->execute([$art['quantite'], $art['id_produit']]);
                }

                // Annuler la commande
                $pdo->prepare("UPDATE commandes SET statut = 'annulee' WHERE id_commande = ?")
                    ->execute([$id_commande]);

                $cancel_message = 'Votre commande a été annulée avec succès.';
            } else {
                $cancel_error = 'Impossible d\'annuler cette commande (déjà expédiée ou non trouvée).';
            }
        } catch (PDOException $e) {
            $cancel_error = 'Erreur lors de l\'annulation.';
            error_log("Erreur annulation commande: " . $e->getMessage());
        }
    }
}

// ============================================================
// RÉCUPÉRATION DES COMMANDES DE L'UTILISATEUR
// ============================================================
$stmt = $pdo->prepare("
    SELECT
        id_commande,
        numero_commande,
        statut,
        montant,
        frais_livraison,
        mode_paiement,
        mode_livraison,
        adresse_livraison,
        notes,
        date_commande
    FROM commandes
    WHERE id_utilisateur = :id
    ORDER BY date_commande DESC
");
$stmt->execute(['id' => $user_id]);
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// POUR CHAQUE COMMANDE : récupérer les articles commandés
// ============================================================
foreach ($commandes as &$cmd) {
    $detail_stmt = $pdo->prepare("
        SELECT
            dc.quantite,
            dc.prix_unitaire,
            p.nom,
            p.image_url
        FROM details_commande dc
        JOIN produits p ON p.id_produit = dc.id_produit
        WHERE dc.id_commande = :id_commande
    ");
    $detail_stmt->execute(['id_commande' => $cmd['id_commande']]);
    $cmd['articles'] = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($cmd);

// ============================================================
// ✅ CORRECTION 1 : Statuts avec espaces — conformes à l'ENUM MySQL
// ============================================================
$statuts = [
    'en attente' => ['label' => 'En attente',  'color' => '#f39c12', 'icon' => 'bi-clock',          'etape' => 1],
    'payee'      => ['label' => 'Payée',        'color' => '#3498db', 'icon' => 'bi-credit-card',    'etape' => 2],
    'expediee'   => ['label' => 'Expédiée',     'color' => '#9b59b6', 'icon' => 'bi-truck',          'etape' => 3],
    'livree'     => ['label' => 'Livrée',       'color' => '#27ae60', 'icon' => 'bi-check-circle',   'etape' => 4],
    'annulee'    => ['label' => 'Annulée',      'color' => '#e74c3c', 'icon' => 'bi-x-circle',       'etape' => 0],
];

// Étapes de la progression (ordre pour la barre de suivi)
$etapes_suivi = [
    1 => 'Commande passée',
    2 => 'Paiement validé',
    3 => 'En cours d\'expédition',
    4 => 'Livrée',
];

$pageTitle = "Mes Commandes — Lumoura";
include '../includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
.page-wrapper { padding-top: 120px; min-height: 80vh; background: #fdf8f0; }
.page-title { font-size: 2rem; font-weight: 700; color: #2c2c2c; }
.page-title i { color: #cc8800; }

/* Carte commande */
.commande-card {
    border: 1px solid #e8dcc8;
    border-radius: 16px;
    background: #fff;
    overflow: hidden;
    transition: box-shadow 0.3s;
    margin-bottom: 24px;
}
.commande-card:hover { box-shadow: 0 8px 28px rgba(0,0,0,0.10); }

/* En-tête */
.commande-header {
    background: linear-gradient(90deg, #fff8ec, #fff3d6);
    padding: 16px 24px;
    border-bottom: 1px solid #f0e0c0;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
}
.commande-numero { font-weight: 700; font-size: 1rem; color: #2c2c2c; letter-spacing: 0.5px; }
.commande-date { color: #888; font-size: 0.88rem; }

/* Badge statut */
.badge-statut {
    font-size: 0.82rem;
    padding: 5px 14px;
    border-radius: 20px;
    font-weight: 600;
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

/* Barre de progression suivi */
.suivi-container {
    padding: 20px 24px 10px;
    background: #fffcf5;
    border-bottom: 1px solid #f0e0c0;
}
.suivi-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    margin-bottom: 8px;
}
.suivi-bar::before {
    content: '';
    position: absolute;
    top: 16px;
    left: 16px;
    right: 16px;
    height: 3px;
    background: #e8dcc8;
    z-index: 0;
}
.suivi-bar .progress-fill {
    position: absolute;
    top: 16px;
    left: 16px;
    height: 3px;
    background: #cc8800;
    z-index: 1;
    transition: width 0.5s ease;
}
.suivi-etape {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    z-index: 2;
    flex: 1;
}
.suivi-cercle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 700;
    border: 3px solid #e8dcc8;
    background: white;
    color: #bbb;
    transition: all 0.3s;
}
.suivi-cercle.actif { border-color: #cc8800; background: #cc8800; color: white; }
.suivi-cercle.passe { border-color: #cc8800; background: #fff8ec; color: #cc8800; }
.suivi-label { font-size: 0.72rem; color: #aaa; text-align: center; max-width: 80px; line-height: 1.3; }
.suivi-label.actif { color: #cc8800; font-weight: 600; }
.suivi-label.passe { color: #888; }

/* Corps articles */
.commande-body { padding: 20px 24px; }
.article-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 0;
    border-bottom: 1px solid #f5f0e8;
}
.article-row:last-child { border-bottom: none; }
.article-img {
    width: 56px; height: 56px;
    object-fit: cover; border-radius: 10px;
    border: 1px solid #ece0c8;
    flex-shrink: 0;
}
.article-img-placeholder {
    width: 56px; height: 56px;
    border-radius: 10px; background: #f0e8d8;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; color: #cc8800; font-size: 1.3rem;
}
.article-nom { font-weight: 600; font-size: 0.95rem; color: #2c2c2c; }
.article-details { font-size: 0.85rem; color: #888; }
.article-prix { margin-left: auto; font-weight: 700; color: #cc8800; font-size: 0.95rem; white-space: nowrap; }

/* Pied de carte */
.commande-footer {
    background: #fafaf8;
    border-top: 1px solid #f0e0c0;
    padding: 14px 24px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.total-label { font-size: 0.88rem; color: #888; }
.total-montant { font-size: 1.1rem; font-weight: 700; color: #2c2c2c; }
.total-livraison { font-size: 0.82rem; color: #888; margin-left: 6px; }

/* Pills info */
.info-pill {
    font-size: 0.82rem;
    background: #f5efe4;
    color: #7a5c20;
    border-radius: 20px;
    padding: 4px 12px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

/* Bouton toggle */
.btn-toggle-details {
    background: none;
    border: 1px solid #e0c88a;
    color: #cc8800;
    border-radius: 20px;
    font-size: 0.83rem;
    padding: 4px 14px;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-toggle-details:hover { background: #fff3d6; }

/* Bouton annuler */
.btn-annuler {
    background: none;
    border: 1px solid #e74c3c;
    color: #e74c3c;
    border-radius: 20px;
    font-size: 0.83rem;
    padding: 4px 14px;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-annuler:hover { background: #fdf0ef; }

/* État vide */
.empty-state { text-align: center; padding: 60px 20px; }
.empty-icon { font-size: 5rem; color: #e0c88a; margin-bottom: 20px; }
.empty-title { font-size: 1.4rem; font-weight: 700; color: #2c2c2c; margin-bottom: 10px; }
.empty-subtitle { color: #888; margin-bottom: 28px; }
.btn-catalogue {
    background: #cc8800; color: #fff;
    border: none; border-radius: 30px;
    padding: 12px 32px; font-weight: 600;
    font-size: 0.95rem; text-decoration: none;
    transition: all 0.3s; display: inline-flex;
    align-items: center; gap: 8px;
}
.btn-catalogue:hover { background: #b37800; color: #fff; transform: translateY(-2px); }

/* Messages flash */
.flash-success {
    background: #e6f4ea; color: #2e7d32;
    padding: 14px 20px; border-radius: 10px;
    border-left: 4px solid #4caf50;
    margin-bottom: 20px; font-weight: 500;
}
.flash-error {
    background: #ffebee; color: #c62828;
    padding: 14px 20px; border-radius: 10px;
    border-left: 4px solid #f44336;
    margin-bottom: 20px;
}
</style>

<div class="page-wrapper">
    <div class="container pb-5">

        <!-- Titre -->
        <h1 class="page-title mb-4">
            <i class="bi bi-bag-check me-2"></i>Mes Commandes
        </h1>

        <!-- Messages flash annulation -->
        <?php if ($cancel_message): ?>
            <div class="flash-success">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($cancel_message); ?>
            </div>
        <?php endif; ?>
        <?php if ($cancel_error): ?>
            <div class="flash-error">
                <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($cancel_error); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($commandes)): ?>
        <!-- AUCUNE COMMANDE -->
        <div class="commande-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-bag-x"></i></div>
                <div class="empty-title">Vous n'avez pas encore de commande</div>
                <p class="empty-subtitle">Découvrez notre sélection de parfums d'exception.</p>
                <a href="<?= SITE_URL ?>pages/catalogue.php" class="btn-catalogue">
                    <i class="bi bi-shop"></i> Découvrir le catalogue
                </a>
            </div>
        </div>

        <?php else: ?>
        <!-- LISTE DES COMMANDES -->

        <?php foreach ($commandes as $cmd):
            // ✅ CORRECTION 1 : Correspondance avec les vrais statuts de la BDD
            $statut_info = $statuts[$cmd['statut']] ?? [
                'label' => ucfirst($cmd['statut'] ?: 'En attente'),
                'color' => '#888',
                'icon'  => 'bi-question-circle',
                'etape' => 1
            ];

            $nb_articles = array_sum(array_column($cmd['articles'], 'quantite'));

            // ✅ CORRECTION 2 : montant inclut déjà les frais — pas de doublon
            $total_ttc = $cmd['montant'];

            $etape_actuelle  = $statut_info['etape'];
            $est_annulee     = ($cmd['statut'] === 'annulee');
            $peut_annuler    = ($cmd['statut'] === 'en attente');
        ?>

        <div class="commande-card">

            <!-- EN-TÊTE -->
            <div class="commande-header">
                <div>
                    <div class="commande-numero">
                        <i class="bi bi-receipt me-1" style="color:#cc8800;"></i>
                        <?= htmlspecialchars($cmd['numero_commande']) ?>
                    </div>
                    <div class="commande-date">
                        <i class="bi bi-calendar3 me-1"></i>
                        <?= date('d/m/Y à H:i', strtotime($cmd['date_commande'])) ?>
                        &nbsp;·&nbsp;
                        <?= $nb_articles ?> article<?= $nb_articles > 1 ? 's' : '' ?>
                    </div>
                </div>

                <!-- Badge statut coloré -->
                <span class="badge-statut ms-auto" style="background: <?= $statut_info['color'] ?>;">
                    <i class="bi <?= $statut_info['icon'] ?>"></i>
                    <?= $statut_info['label'] ?>
                </span>

                <!-- Bouton annuler (uniquement si statut = en attente) -->
                <?php if ($peut_annuler): ?>
                <form method="POST" style="margin: 0;" onsubmit="return confirm('Annuler cette commande ?');">
                    <input type="hidden" name="annuler_commande" value="1">
                    <input type="hidden" name="id_commande" value="<?= $cmd['id_commande'] ?>">
                    <button type="submit" class="btn-annuler">
                        <i class="bi bi-x-lg me-1"></i>Annuler
                    </button>
                </form>
                <?php endif; ?>

                <button class="btn-toggle-details"
                        onclick="toggleDetails(<?= $cmd['id_commande'] ?>)"
                        id="btn-<?= $cmd['id_commande'] ?>">
                    <i class="bi bi-chevron-down me-1"></i>Détails
                </button>
            </div>

            <!-- ✅ AMÉLIORATION : Barre de progression suivi (masquée si annulée) -->
            <?php if (!$est_annulee): ?>
            <div class="suivi-container">
                <div class="suivi-bar">
                    <!-- Ligne de progression en arrière-plan -->
                    <?php
                    $nb_etapes  = count($etapes_suivi);
                    $pct_fill   = $etape_actuelle > 1
                        ? (($etape_actuelle - 1) / ($nb_etapes - 1)) * 100
                        : 0;
                    ?>
                    <div class="progress-fill" style="width: calc(<?= $pct_fill ?>% * (100% - 32px) / 100);"></div>

                    <?php foreach ($etapes_suivi as $num => $label_etape):
                        if ($num < $etape_actuelle)       $classe = 'passe';
                        elseif ($num === $etape_actuelle) $classe = 'actif';
                        else                               $classe = '';
                    ?>
                    <div class="suivi-etape">
                        <div class="suivi-cercle <?= $classe ?>">
                            <?php if ($num < $etape_actuelle): ?>
                                <i class="bi bi-check"></i>
                            <?php else: ?>
                                <?= $num ?>
                            <?php endif; ?>
                        </div>
                        <div class="suivi-label <?= $classe ?>">
                            <?= htmlspecialchars($label_etape) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <!-- Message annulation -->
            <div style="padding: 12px 24px; background: #fff5f5; border-bottom: 1px solid #fdd; font-size: 0.88rem; color: #e74c3c;">
                <i class="bi bi-x-circle me-1"></i>
                Cette commande a été annulée — le stock a été remis à disposition.
            </div>
            <?php endif; ?>

            <!-- DÉTAILS ARTICLES (masqués par défaut) -->
            <div id="details-<?= $cmd['id_commande'] ?>" style="display:none;">
                <div class="commande-body">

                    <?php foreach ($cmd['articles'] as $article): ?>
                    <div class="article-row">
                        <?php if (!empty($article['image_url'])): ?>
                            <img src="<?= htmlspecialchars($article['image_url']) ?>"
                                 alt="<?= htmlspecialchars($article['nom']) ?>"
                                 class="article-img">
                        <?php else: ?>
                            <div class="article-img-placeholder">
                                <i class="bi bi-droplet"></i>
                            </div>
                        <?php endif; ?>

                        <div>
                            <div class="article-nom"><?= htmlspecialchars($article['nom']) ?></div>
                            <div class="article-details">
                                Qté : <?= $article['quantite'] ?>
                                &nbsp;·&nbsp;
                                <?= number_format($article['prix_unitaire'], 2, ',', ' ') ?> € / unité
                            </div>
                        </div>

                        <div class="article-prix">
                            <?= number_format($article['quantite'] * $article['prix_unitaire'], 2, ',', ' ') ?> €
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Infos commande -->
                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <?php if (!empty($cmd['adresse_livraison'])): ?>
                        <span class="info-pill">
                            <i class="bi bi-geo-alt"></i>
                            <?= htmlspecialchars($cmd['adresse_livraison']) ?>
                        </span>
                        <?php endif; ?>

                        <?php if (!empty($cmd['mode_paiement'])): ?>
                        <span class="info-pill">
                            <i class="bi bi-credit-card"></i>
                            <?= htmlspecialchars($cmd['mode_paiement']) ?>
                        </span>
                        <?php endif; ?>

                        <!-- ✅ AMÉLIORATION : Affichage du mode de livraison -->
                        <?php if (!empty($cmd['mode_livraison'])): ?>
                        <?php
                        $modes_livraison = [
                            'standard'     => '📦 Livraison Standard (3-5 jours)',
                            'express'      => '⚡ Livraison Express (lendemain)',
                            'click_collect'=> '🏪 Click & Collect',
                        ];
                        $label_livraison = $modes_livraison[$cmd['mode_livraison']] ?? $cmd['mode_livraison'];
                        ?>
                        <span class="info-pill">
                            <i class="bi bi-truck"></i>
                            <?= htmlspecialchars($label_livraison) ?>
                        </span>
                        <?php endif; ?>

                        <?php if (!empty($cmd['notes'])): ?>
                        <span class="info-pill">
                            <i class="bi bi-chat-left-text"></i>
                            <?= htmlspecialchars($cmd['notes']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- PIED DE CARTE -->
            <div class="commande-footer">
                <div>
                    <span class="total-label">Total payé :</span>
                    <!-- ✅ CORRECTION 2 : montant contient déjà les frais de livraison -->
                    <span class="total-montant"><?= number_format($total_ttc, 2, ',', ' ') ?> €</span>
                    <?php if ($cmd['frais_livraison'] > 0): ?>
                        <span class="total-livraison">(dont <?= number_format($cmd['frais_livraison'], 2, ',', ' ') ?> € de livraison)</span>
                    <?php else: ?>
                        <span class="total-livraison">(livraison offerte)</span>
                    <?php endif; ?>
                </div>

                <a href="<?= SITE_URL ?>pages/catalogue.php" class="info-pill" style="text-decoration:none;">
                    <i class="bi bi-arrow-repeat"></i> Recommander
                </a>
            </div>

        </div>
        <?php endforeach; ?>

        <?php endif; ?>
    </div>
</div>

<script>
function toggleDetails(id) {
    const details = document.getElementById('details-' + id);
    const btn     = document.getElementById('btn-' + id);
    const isOpen  = details.style.display !== 'none';

    details.style.display = isOpen ? 'none' : 'block';
    btn.innerHTML = isOpen
        ? '<i class="bi bi-chevron-down me-1"></i>Détails'
        : '<i class="bi bi-chevron-up me-1"></i>Masquer';
}
</script>

<?php include '../includes/footer.php'; ?>