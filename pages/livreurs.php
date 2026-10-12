<?php
// ============================================================
// FICHIER : pages/livreurs.php
// RÔLE    : CRUD complet pour la gestion des livreurs
//           + assignation d'un livreur à une commande
// ============================================================

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Vérification : admin uniquement (même logique que admin.php)
if (!isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "pages/connexion.php");
    exit();
}

// Vérifier le rôle admin en BDD
$check_stmt = $pdo->prepare("SELECT role FROM utilisateurs WHERE id_utilisateur = ?");
$check_stmt->execute([$_SESSION['user_id']]);
$check_user = $check_stmt->fetch();

if (!$check_user || $check_user['role'] !== 'admin') {
    header("Location: " . SITE_URL . "pages/index.php");
    exit();
}

$success_msg = '';
$error_msg   = '';

// ============================================================
// TRAITEMENT DES ACTIONS POST
// ============================================================

// --- AJOUTER un livreur ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_ajouter'])) {
    $nom            = trim($_POST['nom']            ?? '');
    $prenom         = trim($_POST['prenom']         ?? '');
    $telephone      = trim($_POST['telephone']      ?? '');
    $zone_livraison = trim($_POST['zone_livraison'] ?? '');
    $vehicule       = trim($_POST['vehicule']       ?? '');
    $statut         = $_POST['statut'] === 'inactif' ? 'inactif' : 'actif';
    $prix_livraison = (float) str_replace(',', '.', $_POST['prix_livraison'] ?? 5.90);

    if ($nom && $prenom && $telephone && $zone_livraison && $vehicule) {
        $stmt = $pdo->prepare("
            INSERT INTO livreurs (nom, prenom, telephone, zone_livraison, vehicule, prix_livraison, statut)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nom, $prenom, $telephone, $zone_livraison, $vehicule, $prix_livraison, $statut]);
        $success_msg = "Livreur $prenom $nom ajouté avec succès !";
    } else {
        $error_msg = "Tous les champs sont obligatoires.";
    }
}

// --- MODIFIER un livreur ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_modifier'])) {
    $id             = (int) $_POST['id_livreur'];
    $nom            = trim($_POST['nom']            ?? '');
    $prenom         = trim($_POST['prenom']         ?? '');
    $telephone      = trim($_POST['telephone']      ?? '');
    $zone_livraison = trim($_POST['zone_livraison'] ?? '');
    $vehicule       = trim($_POST['vehicule']       ?? '');
    $statut         = $_POST['statut'] === 'inactif' ? 'inactif' : 'actif';
    $prix_livraison = (float) str_replace(',', '.', $_POST['prix_livraison'] ?? 5.90);

    if ($id && $nom && $prenom && $telephone && $zone_livraison && $vehicule) {
        $stmt = $pdo->prepare("
            UPDATE livreurs
            SET nom=?, prenom=?, telephone=?, zone_livraison=?, vehicule=?, prix_livraison=?, statut=?
            WHERE id_livreur=?
        ");
        $stmt->execute([$nom, $prenom, $telephone, $zone_livraison, $vehicule, $prix_livraison, $statut, $id]);
        $success_msg = "Livreur mis à jour avec succès !";
    } else {
        $error_msg = "Tous les champs sont obligatoires.";
    }
}

// --- SUPPRIMER un livreur ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_supprimer'])) {
    $id = (int) $_POST['id_livreur_suppr'];
    // On retire d'abord le livreur des commandes pour respecter la contrainte FK
    $pdo->prepare("UPDATE commandes SET id_livreur = NULL WHERE id_livreur = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM livreurs WHERE id_livreur = ?")->execute([$id]);
    $success_msg = "Livreur supprimé.";
}

// --- ASSIGNER un livreur à une commande ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_assigner'])) {
    $id_commande = (int) $_POST['id_commande'];
    $id_livreur  = ($_POST['id_livreur'] === '') ? null : (int) $_POST['id_livreur'];

    $stmt = $pdo->prepare("UPDATE commandes SET id_livreur = ? WHERE id_commande = ?");
    $stmt->execute([$id_livreur, $id_commande]);
    $success_msg = "Livreur assigné à la commande avec succès !";
}

// ============================================================
// RÉCUPÉRATION DES DONNÉES
// ============================================================

// Tous les livreurs
$livreurs = $pdo->query("SELECT * FROM livreurs ORDER BY statut ASC, nom ASC")->fetchAll(PDO::FETCH_ASSOC);

// Toutes les commandes avec le livreur assigné (si existant)
$commandes = $pdo->query("
    SELECT 
        c.id_commande,
        c.numero_commande,
        c.statut,
        c.montant,
        c.date_commande,
        c.id_livreur,
        CONCAT(u.prenom, ' ', u.nom) AS client,
        CONCAT(l.prenom, ' ', l.nom) AS livreur_nom
    FROM commandes c
    JOIN utilisateurs u ON u.id_utilisateur = c.id_utilisateur
    LEFT JOIN livreurs l ON l.id_livreur = c.id_livreur
    ORDER BY c.date_commande DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Livreur à modifier (si ?edit=X dans l'URL)
$edit_livreur = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM livreurs WHERE id_livreur = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_livreur = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Statistiques
$nb_actifs   = count(array_filter($livreurs, fn($l) => $l['statut'] === 'actif'));
$nb_inactifs = count($livreurs) - $nb_actifs;
$nb_assignees = count(array_filter($commandes, fn($c) => $c['id_livreur'] !== null));

$pageTitle = "Gestion des Livreurs";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livreurs — Lumoura Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Segoe UI', sans-serif;
    background: #1a1a1a;
    color: #e0e0e0;
    min-height: 100vh;
}

/* ---- TOPBAR ---- */
.topbar {
    background: #111;
    padding: 18px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 2px solid #d4a017;
    position: sticky;
    top: 0;
    z-index: 100;
}
.topbar-title { font-size: 1.3rem; font-weight: 700; color: #d4a017; }
.topbar-title i { margin-right: 10px; }
.btn-back {
    background: #2a2a2a;
    color: #d4a017;
    border: 1px solid #d4a017;
    border-radius: 8px;
    padding: 8px 18px;
    font-size: 0.9rem;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-back:hover { background: #d4a017; color: #111; }

/* ---- WRAPPER ---- */
.wrapper { max-width: 1300px; margin: 0 auto; padding: 32px 24px; }

/* ---- STATS ---- */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 32px;
}
.stat-card {
    background: #232323;
    border: 1px solid #333;
    border-radius: 14px;
    padding: 22px 28px;
    text-align: center;
    transition: border-color 0.2s;
}
.stat-card:hover { border-color: #d4a017; }
.stat-icon { font-size: 1.8rem; margin-bottom: 8px; color: #d4a017; }
.stat-value { font-size: 2rem; font-weight: 800; color: #fff; }
.stat-label { font-size: 0.82rem; color: #888; margin-top: 4px; }

/* ---- ALERTES ---- */
.alert {
    padding: 14px 20px;
    border-radius: 10px;
    margin-bottom: 24px;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success { background: #1a3a1a; border: 1px solid #2d6a2d; color: #6fcf6f; }
.alert-error   { background: #3a1a1a; border: 1px solid #6a2d2d; color: #cf6f6f; }

/* ---- SECTION TITRE ---- */
.section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #d4a017;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ---- FORMULAIRE ---- */
.form-card {
    background: #232323;
    border: 1px solid #333;
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 32px;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    font-size: 0.83rem;
    color: #aaa;
    margin-bottom: 6px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.form-group input,
.form-group select {
    width: 100%;
    background: #1a1a1a;
    border: 1px solid #444;
    border-radius: 8px;
    color: #e0e0e0;
    padding: 10px 14px;
    font-size: 0.95rem;
    transition: border-color 0.2s;
}
.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #d4a017;
}
.form-group select option { background: #1a1a1a; }

/* ---- BOUTONS ---- */
.btn {
    padding: 10px 22px;
    border-radius: 8px;
    border: none;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none;
}
.btn-gold  { background: linear-gradient(90deg, #d4a017, #e8b825); color: #111; }
.btn-gold:hover  { background: linear-gradient(90deg, #b8880f, #d4a017); transform: translateY(-1px); }
.btn-edit  { background: #2a3a5a; color: #6ab0f5; border: 1px solid #3a5a8a; font-size: 0.82rem; padding: 6px 14px; }
.btn-edit:hover  { background: #3a5a8a; }
.btn-delete{ background: #3a1a1a; color: #f57070; border: 1px solid #6a2d2d; font-size: 0.82rem; padding: 6px 14px; }
.btn-delete:hover{ background: #6a2d2d; }
.btn-cancel{ background: #2a2a2a; color: #aaa; border: 1px solid #444; }
.btn-cancel:hover{ background: #333; }

/* ---- TABLE ---- */
.table-card {
    background: #232323;
    border: 1px solid #333;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 32px;
}
table { width: 100%; border-collapse: collapse; }
thead tr { background: #1a1a1a; border-bottom: 2px solid #d4a017; }
thead th { padding: 14px 16px; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; color: #d4a017; text-align: left; }
tbody tr { border-bottom: 1px solid #2a2a2a; transition: background 0.15s; }
tbody tr:hover { background: #2a2a2a; }
tbody td { padding: 13px 16px; font-size: 0.92rem; vertical-align: middle; }

/* ---- BADGES ---- */
.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.badge-actif    { background: #1a3a1a; color: #6fcf6f; border: 1px solid #2d6a2d; }
.badge-inactif  { background: #3a3a1a; color: #cfcf6f; border: 1px solid #6a6a2d; }
.badge-attente  { background: #3a2a1a; color: #d4a017; border: 1px solid #6a4a1a; }
.badge-livree   { background: #1a3a1a; color: #6fcf6f; border: 1px solid #2d6a2d; }
.badge-expediee { background: #1a2a3a; color: #6fafcf; border: 1px solid #2d4a6a; }
.badge-annulee  { background: #3a1a1a; color: #cf6f6f; border: 1px solid #6a2d2d; }
.badge-aucun    { background: #2a2a2a; color: #666; border: 1px solid #444; }

/* ---- VÉHICULE ICONE ---- */
.vehicule-icon { font-size: 1.1rem; margin-right: 6px; }

/* ---- SELECT ASSIGNER ---- */
.select-assign {
    background: #1a1a1a;
    border: 1px solid #444;
    border-radius: 6px;
    color: #e0e0e0;
    padding: 6px 10px;
    font-size: 0.85rem;
    cursor: pointer;
}
.select-assign:focus { outline: none; border-color: #d4a017; }

/* ---- TABS ---- */
.tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 28px;
    background: #1a1a1a;
    padding: 6px;
    border-radius: 12px;
    width: fit-content;
}
.tab-btn {
    background: none;
    border: none;
    color: #888;
    padding: 10px 22px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.tab-btn.active, .tab-btn:hover {
    background: #d4a017;
    color: #111;
}

/* ---- EMPTY STATE ---- */
.empty-state {
    text-align: center;
    padding: 48px 20px;
    color: #555;
}
.empty-state i { font-size: 3rem; margin-bottom: 12px; display: block; }

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .form-grid  { grid-template-columns: 1fr; }
    table { font-size: 0.82rem; }
    thead th, tbody td { padding: 10px 10px; }
}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="topbar-title">
        <i class="fas fa-motorcycle"></i>Gestion des Livreurs
    </div>
    <a href="<?php echo SITE_URL; ?>pages/admin/admin.php" class="btn-back">
        <i class="fas fa-arrow-left"></i> Retour Admin
    </a>
</div>

<div class="wrapper">

    <!-- ALERTES -->
    <?php if ($success_msg): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
    <div class="alert alert-error"><i class="bi bi-x-circle-fill"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?= count($livreurs) ?></div>
            <div class="stat-label">Total livreurs</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?= $nb_actifs ?></div>
            <div class="stat-label">Livreurs actifs</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-box-open"></i></div>
            <div class="stat-value"><?= $nb_assignees ?></div>
            <div class="stat-label">Commandes assignées</div>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('livreurs', this)">
            <i class="fas fa-motorcycle"></i> Livreurs
        </button>
        <button class="tab-btn" onclick="showTab('commandes', this)">
            <i class="fas fa-box"></i> Assignation commandes
        </button>
    </div>

    <!-- ======================================================= -->
    <!-- TAB 1 : GESTION DES LIVREURS                            -->
    <!-- ======================================================= -->
    <div id="tab-livreurs">

        <!-- FORMULAIRE AJOUT / MODIFICATION -->
        <div class="form-card">
            <?php if ($edit_livreur): ?>
                <!-- MODE MODIFICATION -->
                <div class="section-title">
                    <i class="bi bi-pencil-square"></i> Modifier le livreur
                </div>
                <form method="POST">
                    <input type="hidden" name="id_livreur" value="<?= $edit_livreur['id_livreur'] ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Prénom</label>
                            <input type="text" name="prenom" value="<?= htmlspecialchars($edit_livreur['prenom']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Nom</label>
                            <input type="text" name="nom" value="<?= htmlspecialchars($edit_livreur['nom']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="text" name="telephone" value="<?= htmlspecialchars($edit_livreur['telephone']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Zone de livraison</label>
                            <input type="text" name="zone_livraison" value="<?= htmlspecialchars($edit_livreur['zone_livraison']) ?>" placeholder="Ex: Paris 1er - 5ème" required>
                        </div>
                        <div class="form-group">
                            <label>Véhicule</label>
                            <select name="vehicule">
                                <?php foreach (['Vélo','Scooter','Voiture','Camionnette'] as $v): ?>
                                <option value="<?= $v ?>" <?= $edit_livreur['vehicule'] === $v ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Statut</label>
                            <select name="statut">
                                <option value="actif"   <?= $edit_livreur['statut'] === 'actif'   ? 'selected' : '' ?>>✅ Actif</option>
                                <option value="inactif" <?= $edit_livreur['statut'] === 'inactif' ? 'selected' : '' ?>>⏸ Inactif</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Prix livraison (€)</label>
                            <input type="number" name="prix_livraison" step="0.01" min="0"
                                   value="<?= number_format($edit_livreur['prix_livraison'] ?? 5.90, 2, '.', '') ?>" required>
                        </div>
                    </div>
                    <div style="display:flex; gap:12px;">
                        <button type="submit" name="action_modifier" class="btn btn-gold">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                        <a href="livreurs.php" class="btn btn-cancel">
                            <i class="bi bi-x"></i> Annuler
                        </a>
                    </div>
                </form>

            <?php else: ?>
                <!-- MODE AJOUT -->
                <div class="section-title">
                    <i class="bi bi-plus-circle"></i> Ajouter un livreur
                </div>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Prénom</label>
                            <input type="text" name="prenom" placeholder="Lucas" required>
                        </div>
                        <div class="form-group">
                            <label>Nom</label>
                            <input type="text" name="nom" placeholder="Dupont" required>
                        </div>
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="text" name="telephone" placeholder="06 XX XX XX XX" required>
                        </div>
                        <div class="form-group">
                            <label>Zone de livraison</label>
                            <input type="text" name="zone_livraison" placeholder="Ex: Paris 1er - 5ème" required>
                        </div>
                        <div class="form-group">
                            <label>Véhicule</label>
                            <select name="vehicule">
                                <option value="Vélo">🚲 Vélo</option>
                                <option value="Scooter" selected>🛵 Scooter</option>
                                <option value="Voiture">🚗 Voiture</option>
                                <option value="Camionnette">🚐 Camionnette</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Statut</label>
                            <select name="statut">
                                <option value="actif">✅ Actif</option>
                                <option value="inactif">⏸ Inactif</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Prix livraison (€)</label>
                            <input type="number" name="prix_livraison" step="0.01" min="0" value="5.90" required placeholder="5.90">
                        </div>
                    </div>
                    <button type="submit" name="action_ajouter" class="btn btn-gold">
                        <i class="bi bi-plus-circle"></i> Ajouter le livreur
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- TABLEAU DES LIVREURS -->
        <div class="table-card">
            <div class="section-title" style="padding: 20px 24px 0;">
                <i class="fas fa-list"></i> Liste des livreurs (<?= count($livreurs) ?>)
            </div>

            <?php if (empty($livreurs)): ?>
            <div class="empty-state">
                <i class="fas fa-motorcycle"></i>
                Aucun livreur enregistré pour l'instant.
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Livreur</th>
                        <th>Téléphone</th>
                        <th>Zone</th>
                        <th>Véhicule</th>
                        <th>Prix livraison</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($livreurs as $l): ?>
                    <?php
                        $icone_vehicule = match($l['vehicule']) {
                            'Vélo'        => '🚲',
                            'Scooter'     => '🛵',
                            'Voiture'     => '🚗',
                            'Camionnette' => '🚐',
                            default       => '🚚'
                        };
                    ?>
                    <tr>
                        <td style="color:#555;">#<?= $l['id_livreur'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($l['prenom'] . ' ' . $l['nom']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($l['telephone']) ?></td>
                        <td style="color:#bbb;"><?= htmlspecialchars($l['zone_livraison']) ?></td>
                        <td><?= $icone_vehicule ?> <?= htmlspecialchars($l['vehicule']) ?></td>
                        <td style="color:#d4a017; font-weight:700;">
                            <?= number_format($l['prix_livraison'] ?? 5.90, 2, ',', ' ') ?> €
                        </td>
                        <td>
                            <span class="badge badge-<?= $l['statut'] ?>">
                                <?= $l['statut'] === 'actif' ? '✅ Actif' : '⏸ Inactif' ?>
                            </span>
                        </td>
                        <td>
                            <a href="livreurs.php?edit=<?= $l['id_livreur'] ?>" class="btn btn-edit">
                                <i class="bi bi-pencil"></i> Modifier
                            </a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce livreur ? Les commandes associées seront désassignées.')">
                                <input type="hidden" name="id_livreur_suppr" value="<?= $l['id_livreur'] ?>">
                                <button type="submit" name="action_supprimer" class="btn btn-delete">
                                    <i class="bi bi-trash"></i> Supprimer
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div><!-- /tab-livreurs -->


    <!-- ======================================================= -->
    <!-- TAB 2 : ASSIGNATION LIVREUR → COMMANDE                  -->
    <!-- ======================================================= -->
    <div id="tab-commandes" style="display:none;">

        <div class="table-card">
            <div class="section-title" style="padding: 20px 24px 0;">
                <i class="fas fa-box"></i> Assigner un livreur aux commandes
            </div>

            <?php if (empty($commandes)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                Aucune commande trouvée.
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>N° Commande</th>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th>Livreur assigné</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commandes as $cmd):
                        $badge_statut = match($cmd['statut']) {
                            'en_attente' => 'badge-attente',
                            'livree'     => 'badge-livree',
                            'expediee'   => 'badge-expediee',
                            'annulee'    => 'badge-annulee',
                            default      => 'badge-attente'
                        };
                        $label_statut = match($cmd['statut']) {
                            'en_attente' => '⏳ En attente',
                            'confirmee'  => '✅ Confirmée',
                            'en_cours'   => '🔄 En cours',
                            'expediee'   => '📦 Expédiée',
                            'livree'     => '✅ Livrée',
                            'annulee'    => '❌ Annulée',
                            default      => ucfirst($cmd['statut'])
                        };
                    ?>
                    <tr>
                        <td style="font-weight:700; color:#d4a017;"><?= htmlspecialchars($cmd['numero_commande']) ?></td>
                        <td><?= htmlspecialchars($cmd['client']) ?></td>
                        <td style="color:#888; font-size:0.85rem;"><?= date('d/m/Y', strtotime($cmd['date_commande'])) ?></td>
                        <td style="color:#fff;"><?= number_format($cmd['montant'], 2, ',', ' ') ?> €</td>
                        <td><span class="badge <?= $badge_statut ?>"><?= $label_statut ?></span></td>
                        <td>
                            <?php if ($cmd['livreur_nom']): ?>
                                <span class="badge badge-actif">🛵 <?= htmlspecialchars($cmd['livreur_nom']) ?></span>
                            <?php else: ?>
                                <span class="badge badge-aucun">Non assigné</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:flex; align-items:center; gap:8px;">
                                <input type="hidden" name="id_commande" value="<?= $cmd['id_commande'] ?>">
                                <select name="id_livreur" class="select-assign">
                                    <option value="">— Aucun —</option>
                                    <?php foreach ($livreurs as $l):
                                        if ($l['statut'] !== 'actif') continue; ?>
                                    <option value="<?= $l['id_livreur'] ?>" <?= $cmd['id_livreur'] == $l['id_livreur'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($l['prenom'] . ' ' . $l['nom']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="action_assigner" class="btn btn-gold" style="padding:6px 14px; font-size:0.82rem;">
                                    <i class="bi bi-check2"></i> Ok
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div><!-- /tab-commandes -->

</div><!-- /wrapper -->

<script>
function showTab(name, btn) {
    // Cacher tous les tabs
    document.getElementById('tab-livreurs').style.display  = 'none';
    document.getElementById('tab-commandes').style.display = 'none';

    // Afficher le bon
    document.getElementById('tab-' + name).style.display = 'block';

    // Mettre à jour les boutons
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}
</script>

</body>
</html>