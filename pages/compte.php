<?php
// ============================================================
// FICHIER : compte.php
// RÔLE    : Page "Mon compte" de l'utilisateur connecté
// CORRECTIONS :
//   1. Statuts corrigés : 'en attente' au lieu de 'en_attente'
//   2. Ajout du bouton Annuler (commandes 'en attente' uniquement)
//   3. Remise du stock en BDD lors d'une annulation
// ============================================================

session_start();

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "pages/connexion.php");
    exit();
}

$user_id     = (int) $_SESSION['user_id'];
$erreurs     = [];
$success     = false;
$success_msg = '';

// SECTION 1 : Récupération des données de l'utilisateur
$stmt = $pdo->prepare("SELECT nom, prenom, email FROM utilisateurs WHERE id_utilisateur = :id");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die("Utilisateur introuvable");

$nom    = $user['nom'];
$prenom = $user['prenom'];
$email  = $user['email'];

// SECTION 2 : Fonction pour récupérer toutes les adresses
function getAdresses($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM adresses 
        WHERE id_utilisateur = :id 
        ORDER BY est_principale DESC, date_creation DESC
    ");
    $stmt->execute(['id' => $user_id]);
    $adresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $deja_principale = false;
    foreach ($adresses as &$addr) {
        if ($addr['est_principale']) {
            if ($deja_principale) {
                $pdo->prepare("UPDATE adresses SET est_principale = 0 WHERE id_adresse = ?")
                    ->execute([$addr['id_adresse']]);
                $addr['est_principale'] = 0;
            } else {
                $deja_principale = true;
            }
        }
    }
    unset($addr);
    return $adresses;
}

$adresses = getAdresses($pdo, $user_id);

// SECTION 3 : Modification du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    $nom         = trim($_POST['nom']         ?? '');
    $prenom      = trim($_POST['prenom']      ?? '');
    $email       = trim($_POST['email']       ?? '');
    $mdp         = trim($_POST['mdp']         ?? '');
    $mdp_confirm = trim($_POST['mdp_confirm'] ?? '');

    if ($nom === '' || $prenom === '' || $email === '') {
        $erreurs[] = "Tous les champs obligatoires sont requis.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "Email invalide.";
    }

    $update_mdp = false;
    if ($mdp !== '') {
        if (strlen($mdp) < 6) {
            $erreurs[] = "Mot de passe trop court (minimum 6 caractères).";
        } elseif ($mdp !== $mdp_confirm) {
            $erreurs[] = "Les mots de passe ne correspondent pas.";
        } else {
            $update_mdp = true;
        }
    }

    if (empty($erreurs)) {
        $checkEmail = $pdo->prepare("
            SELECT id_utilisateur FROM utilisateurs 
            WHERE email = :email AND id_utilisateur != :id
        ");
        $checkEmail->execute(['email' => $email, 'id' => $user_id]);
        if ($checkEmail->fetch()) {
            $erreurs[] = "Cette adresse email est déjà utilisée par un autre compte.";
        } else {
            $sql    = "UPDATE utilisateurs SET nom = :nom, prenom = :prenom, email = :email";
            $params = ['nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'id' => $user_id];
            if ($update_mdp) {
                $params['mdp'] = password_hash($mdp, PASSWORD_DEFAULT);
                $sql .= ", mot_de_passe = :mdp";
            }
            $sql .= " WHERE id_utilisateur = :id";
            $pdo->prepare($sql)->execute($params);
            $_SESSION['user_nom'] = $prenom . ' ' . $nom;
            $success     = true;
            $success_msg = "Profil mis à jour avec succès !";
        }
    }
}

// SECTION 3B : Récupération des commandes
try {
    $cmd_stmt = $pdo->prepare("
        SELECT id_commande, numero_commande, montant, statut, date_commande, mode_paiement
        FROM commandes
        WHERE id_utilisateur = ?
        ORDER BY date_commande DESC
        LIMIT 20
    ");
    $cmd_stmt->execute([$user_id]);
    $mes_commandes = $cmd_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mes_commandes = [];
}

// ✅ CORRECTION : Annulation d'une commande avec remise du stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annuler_commande'])) {
    $id_commande = (int) ($_POST['id_commande'] ?? 0);

    if ($id_commande > 0) {
        try {
            // Vérifier que la commande appartient à l'utilisateur ET est bien 'en attente'
            $check = $pdo->prepare("
                SELECT id_commande FROM commandes
                WHERE id_commande = ? AND id_utilisateur = ? AND statut = 'en attente'
            ");
            $check->execute([$id_commande, $user_id]);

            if ($check->fetch()) {
                // Remettre le stock des produits commandés
                $articles = $pdo->prepare("
                    SELECT id_produit, quantite FROM details_commande WHERE id_commande = ?
                ");
                $articles->execute([$id_commande]);
                foreach ($articles->fetchAll() as $art) {
                    $pdo->prepare("UPDATE produits SET stock = stock + ? WHERE id_produit = ?")
                        ->execute([$art['quantite'], $art['id_produit']]);
                }

                // Mettre le statut à 'annulee'
                $pdo->prepare("UPDATE commandes SET statut = 'annulee' WHERE id_commande = ?")
                    ->execute([$id_commande]);

                $success     = true;
                $success_msg = "Votre commande a été annulée et le stock a été remis à disposition.";

                // Recharger les commandes après annulation
                $cmd_stmt->execute([$user_id]);
                $mes_commandes = $cmd_stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $erreurs[] = "Impossible d'annuler cette commande (déjà expédiée ou non trouvée).";
            }
        } catch (PDOException $e) {
            $erreurs[] = "Erreur lors de l'annulation.";
            error_log("Erreur annulation: " . $e->getMessage());
        }
    }
}

// SECTION 4 : Ajout d'une nouvelle adresse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_adresse'])) {
    $a_prenom     = trim($_POST['a_prenom']     ?? '');
    $a_nom        = trim($_POST['a_nom']        ?? '');
    $a_adresse    = trim($_POST['a_adresse']    ?? '');
    $a_complement = trim($_POST['a_complement'] ?? '');
    $a_cp         = trim($_POST['a_cp']         ?? '');
    $a_ville      = trim($_POST['a_ville']      ?? '');
    $a_pays       = trim($_POST['a_pays']       ?? 'France');
    $a_telephone  = trim($_POST['a_telephone']  ?? '');
    $a_principale = (isset($_POST['a_principale']) || empty($adresses)) ? 1 : 0;

    if (empty($a_prenom) || empty($a_nom) || empty($a_adresse) || empty($a_cp) || empty($a_ville)) {
        $erreurs[] = "Veuillez remplir tous les champs obligatoires de l'adresse.";
    } else {
        if ($a_principale) {
            $pdo->prepare("UPDATE adresses SET est_principale = 0 WHERE id_utilisateur = ?")
                ->execute([$user_id]);
        }
        $stmt = $pdo->prepare("
            INSERT INTO adresses
                (id_utilisateur, prenom, nom, adresse, code_postal, ville, pays, telephone, est_principale)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $a_prenom, $a_nom, $a_adresse, $a_cp, $a_ville, $a_pays, $a_telephone, $a_principale]);
        $success     = true;
        $success_msg = "Adresse ajoutée avec succès !";
        $adresses    = getAdresses($pdo, $user_id);
    }
}

// SECTION 5 : Modification d'une adresse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_adresse'])) {
    $id_adresse   = (int)($_POST['id_adresse'] ?? 0);
    $a_prenom     = trim($_POST['a_prenom']     ?? '');
    $a_nom        = trim($_POST['a_nom']        ?? '');
    $a_adresse    = trim($_POST['a_adresse']    ?? '');
    $a_complement = trim($_POST['a_complement'] ?? '');
    $a_cp         = trim($_POST['a_cp']         ?? '');
    $a_ville      = trim($_POST['a_ville']      ?? '');
    $a_pays       = trim($_POST['a_pays']       ?? 'France');
    $a_telephone  = trim($_POST['a_telephone']  ?? '');
    $a_principale = isset($_POST['a_principale']) ? 1 : 0;

    if (empty($a_prenom) || empty($a_nom) || empty($a_adresse) || empty($a_cp) || empty($a_ville)) {
        $erreurs[] = "Veuillez remplir tous les champs obligatoires de l'adresse.";
    } else {
        $check = $pdo->prepare("SELECT id_adresse FROM adresses WHERE id_adresse = ? AND id_utilisateur = ?");
        $check->execute([$id_adresse, $user_id]);
        if (!$check->fetch()) {
            $erreurs[] = "Adresse introuvable.";
        } else {
            if ($a_principale) {
                $pdo->prepare("UPDATE adresses SET est_principale = 0 WHERE id_utilisateur = ?")
                    ->execute([$user_id]);
            }
            $pdo->prepare("
                UPDATE adresses SET
                    prenom = ?, nom = ?, adresse = ?, complement_adresse = ?,
                    code_postal = ?, ville = ?, pays = ?, telephone = ?, est_principale = ?
                WHERE id_adresse = ? AND id_utilisateur = ?
            ")->execute([$a_prenom, $a_nom, $a_adresse, $a_complement, $a_cp, $a_ville, $a_pays, $a_telephone, $a_principale, $id_adresse, $user_id]);
            $success     = true;
            $success_msg = "Adresse modifiée avec succès !";
            $adresses    = getAdresses($pdo, $user_id);
        }
    }
}

// SECTION 6 : Suppression d'une adresse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_adresse'])) {
    $id_adresse = (int)($_POST['id_adresse'] ?? 0);
    $check = $pdo->prepare("SELECT id_adresse, est_principale FROM adresses WHERE id_adresse = ? AND id_utilisateur = ?");
    $check->execute([$id_adresse, $user_id]);
    $addr_to_delete = $check->fetch();
    if ($addr_to_delete) {
        $pdo->prepare("DELETE FROM adresses WHERE id_adresse = ? AND id_utilisateur = ?")
            ->execute([$id_adresse, $user_id]);
        if ($addr_to_delete['est_principale']) {
            $first = $pdo->prepare("SELECT id_adresse FROM adresses WHERE id_utilisateur = ? ORDER BY date_creation DESC LIMIT 1");
            $first->execute([$user_id]);
            $next = $first->fetch();
            if ($next) {
                $pdo->prepare("UPDATE adresses SET est_principale = 1 WHERE id_adresse = ?")
                    ->execute([$next['id_adresse']]);
            }
        }
        $success     = true;
        $success_msg = "Adresse supprimée.";
        $adresses    = getAdresses($pdo, $user_id);
    }
}

// SECTION 7 : Définir une adresse comme principale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_principale'])) {
    $id_adresse = (int)($_POST['id_adresse'] ?? 0);
    $check = $pdo->prepare("SELECT id_adresse FROM adresses WHERE id_adresse = ? AND id_utilisateur = ?");
    $check->execute([$id_adresse, $user_id]);
    if ($check->fetch()) {
        $pdo->prepare("UPDATE adresses SET est_principale = 0 WHERE id_utilisateur =?")->execute([$user_id]);
        $pdo->prepare("UPDATE adresses SET est_principale = 1 WHERE id_adresse = ? AND id_utilisateur = ?")->execute([$id_adresse, $user_id]);
        $success     = true;
        $success_msg = "Adresse principale mise à jour !";
        $adresses    = getAdresses($pdo, $user_id);
    }
}

$edit_id   = (int)($_GET['edit_addr'] ?? 0);
$pageTitle = "Mon compte";
include '../includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    .btn-pill-pro { transition: all 0.3s ease; }
    .btn-pill-pro:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.25); }
    .adresse-card { border-radius: 16px; border: 2px solid #dee2e6; transition: border-color 0.3s; overflow: hidden; }
    .adresse-card.principale { border-color: #198754; border-width: 3px; }
    .form-control:focus, .form-select:focus { border-color: #ff8c00; box-shadow: 0 0 0 0.2rem rgba(255,140,0,0.2); }
    #form-add-adresse { display: none; }

    /* Bouton annuler commande */
    .btn-annuler-cmd {
        display: inline-flex; align-items: center; gap: 5px;
        background: none; border: 1px solid #dc3545; color: #dc3545;
        padding: 4px 12px; border-radius: 20px; font-size: 0.78em;
        font-weight: 600; cursor: pointer; transition: all 0.2s;
        text-decoration: none;
    }
    .btn-annuler-cmd:hover { background: #fff5f5; color: #dc3545; }
</style>

<div class="container py-5" style="padding-top:120px !important;">
    <div class="card shadow-lg p-5 mx-auto" style="max-width:960px; border-radius:30px;">

        <h1 class="text-center mb-5 fw-bold">Mon compte</h1>

        <!-- Messages -->
        <?php if ($success && $success_msg): ?>
            <div class="alert alert-success text-center mb-4 fs-5">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>
        <?php if ($erreurs): ?>
            <div class="alert alert-danger fs-5">
                <?php foreach ($erreurs as $e): ?>
                    <div><i class="bi bi-x-circle-fill me-2"></i><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- BLOC 1 : FORMULAIRE PROFIL -->
        <form method="post">
            <div class="row g-4 mb-5 justify-content-center">
                <div class="col-md-5">
                    <label class="form-label fw-bold">Prénom</label>
                    <input class="form-control py-3" name="prenom" value="<?= htmlspecialchars($prenom) ?>" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold">Nom</label>
                    <input class="form-control py-3" name="nom" value="<?= htmlspecialchars($nom) ?>" required>
                </div>
                <div class="col-12 col-md-10">
                    <label class="form-label fw-bold">Email</label>
                    <input class="form-control py-3" type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold">Nouveau mot de passe <span class="text-muted fw-normal">(optionnel)</span></label>
                    <input class="form-control py-3" type="password" name="mdp" placeholder="Laisser vide si inchangé">
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold">Confirmer le mot de passe</label>
                    <input class="form-control py-3" type="password" name="mdp_confirm" placeholder="Confirmer si changement">
                </div>
                <div class="col-12 col-md-10 text-center mt-4">
                    <button class="btn btn-lg px-5 py-3 fw-bold rounded-pill text-white btn-pill-pro"
                            type="submit" name="update_profil"
                            style="background: linear-gradient(90deg, #ff8c00, #e07b00); border: none;">
                        <i class="bi bi-save me-2"></i>ENREGISTRER LES MODIFICATIONS
                    </button>
                </div>
            </div>
        </form>

        <hr class="my-5">

        <!-- BLOC 2 : ADRESSES -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0">
                <i class="bi bi-geo-alt-fill me-2 text-warning"></i>
                Mes adresses de livraison
                <?php if (!empty($adresses)): ?>
                    <span class="badge rounded-pill ms-2" style="background: #ff8c00; font-size: 0.75em;"><?= count($adresses) ?></span>
                <?php endif; ?>
            </h4>
            <button class="btn btn-lg px-4 py-2 fw-bold rounded-pill text-white btn-pill-pro"
                    style="background: linear-gradient(90deg, #ff8c00, #e07b00); border: none;"
                    onclick="toggleFormAdd()">
                <i class="bi bi-plus-circle me-2"></i>Ajouter une adresse
            </button>
        </div>

        <!-- Formulaire ajout adresse -->
        <div id="form-add-adresse" class="card mb-4 p-4 border-0 shadow-sm" style="background:#fffbf4; border-radius:16px;">
            <h5 class="fw-bold mb-4"><i class="bi bi-plus-circle-fill me-2" style="color:#ff8c00;"></i>Nouvelle adresse</h5>
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Prénom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control py-2" name="a_prenom" required placeholder="Prénom">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control py-2" name="a_nom" required placeholder="Nom">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Adresse <span class="text-danger">*</span></label>
                        <input type="text" class="form-control py-2" name="a_adresse" id="add-adresse" autocomplete="off" required placeholder="12 Rue de la République">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Complément d'adresse</label>
                        <input type="text" class="form-control py-2" name="a_complement" placeholder="Bât. A, Appt. 12...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Code postal <span class="text-danger">*</span></label>
                        <input type="text" class="form-control py-2" name="a_cp" id="add-cp" required placeholder="75000" maxlength="5">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Ville <span class="text-danger">*</span></label>
                        <input type="text" class="form-control py-2" name="a_ville" id="add-ville" required placeholder="Paris">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Pays</label>
                        <select class="form-select py-2" name="a_pays" id="add-pays">
                            <option value="France">France</option>
                            <option value="Belgique">Belgique</option>
                            <option value="Suisse">Suisse</option>
                            <option value="Luxembourg">Luxembourg</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Téléphone</label>
                        <input type="tel" class="form-control py-2" name="a_telephone" placeholder="06 12 34 56 78">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="a_principale" value="1" id="add_principale" <?= empty($adresses) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="add_principale">Définir comme adresse principale</label>
                        </div>
                    </div>
                    <div class="col-12 d-flex gap-3 mt-3">
                        <button type="submit" name="add_adresse" class="btn btn-lg px-5 py-2 fw-bold rounded-pill text-white btn-pill-pro" style="background: linear-gradient(90deg, #ff8c00, #e07b00); border: none;">
                            <i class="bi bi-save me-2"></i>Enregistrer
                        </button>
                        <button type="button" onclick="toggleFormAdd()" class="btn btn-lg px-4 py-2 fw-bold rounded-pill btn-outline-secondary">Annuler</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Liste des adresses -->
        <?php if (empty($adresses)): ?>
            <div class="text-center py-5 bg-light rounded-3 border">
                <i class="bi bi-geo-alt fs-1 text-muted mb-3 d-block"></i>
                <p class="lead text-muted mb-4">Vous n'avez pas encore enregistré d'adresse de livraison.</p>
                <button onclick="toggleFormAdd()" class="btn btn-lg px-5 py-3 fw-bold rounded-pill text-white btn-pill-pro" style="background: linear-gradient(90deg, #ff8c00, #e07b00); border: none;">
                    <i class="bi bi-plus-circle me-2"></i>Ajouter mon adresse
                </button>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($adresses as $addr):
                    $is_editing = ($edit_id === (int)$addr['id_adresse']);
                ?>
                <div class="col-12">
                    <div class="adresse-card <?= $addr['est_principale'] ? 'principale' : '' ?>">
                        <div class="d-flex justify-content-between align-items-center px-4 py-3"
                             style="background: <?= $addr['est_principale'] ? '#f0fff4' : '#f8f9fa' ?>;">
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-house-door-fill fs-4 text-primary"></i>
                                <span class="fw-bold fs-5"><?= htmlspecialchars($addr['prenom'] . ' ' . $addr['nom']) ?></span>
                                <?php if ($addr['est_principale']): ?>
                                    <span class="badge bg-success rounded-pill fs-6 px-3"><i class="bi bi-star-fill me-1"></i>Principale</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="?edit_addr=<?= $is_editing ? 0 : $addr['id_adresse'] ?>" class="btn btn-sm fw-bold rounded-pill text-white" style="background: linear-gradient(90deg, #ff8c00, #e07b00);">
                                    <i class="bi bi-pencil-square me-1"></i><?= $is_editing ? 'Annuler' : 'Modifier' ?>
                                </a>
                                <?php if (!$addr['est_principale']): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="id_adresse" value="<?= $addr['id_adresse'] ?>">
                                    <button type="submit" name="set_principale" class="btn btn-sm fw-bold rounded-pill btn-outline-success">
                                        <i class="bi bi-star me-1"></i>Principale
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette adresse ?')">
                                    <input type="hidden" name="id_adresse" value="<?= $addr['id_adresse'] ?>">
                                    <button type="submit" name="delete_adresse" class="btn btn-sm fw-bold rounded-pill btn-outline-danger">
                                        <i class="bi bi-trash3-fill me-1"></i>Supprimer
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="p-4">
                            <?php if (!$is_editing): ?>
                                <p class="mb-2 fs-5"><i class="bi bi-geo-alt-fill me-2 text-muted"></i><?= htmlspecialchars($addr['adresse']) ?></p>
                                <?php if (!empty($addr['complement_adresse']) && $addr['complement_adresse'] !== $addr['adresse']): ?>
                                    <p class="mb-2 text-muted fs-5"><i class="bi bi-signpost me-2 text-muted"></i><?= htmlspecialchars($addr['complement_adresse']) ?></p>
                                <?php endif; ?>
                                <p class="mb-2 fs-5"><i class="bi bi-geo-fill me-2 text-muted"></i><?= htmlspecialchars($addr['code_postal'] . ' ' . $addr['ville']) ?></p>
                                <p class="mb-2 fs-5"><i class="bi bi-globe me-2 text-muted"></i><?= htmlspecialchars($addr['pays'] ?: 'France') ?></p>
                                <?php if (!empty($addr['telephone'])): ?>
                                    <p class="mb-0 fs-5"><i class="bi bi-telephone-fill me-2 text-success"></i><?= htmlspecialchars($addr['telephone']) ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="id_adresse" value="<?= $addr['id_adresse'] ?>">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Prénom <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control py-2" name="a_prenom" value="<?= htmlspecialchars($addr['prenom']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Nom <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control py-2" name="a_nom" value="<?= htmlspecialchars($addr['nom']) ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-bold">Adresse <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control py-2" name="a_adresse" id="edit-adresse" autocomplete="off" value="<?= htmlspecialchars($addr['adresse']) ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-bold">Complément d'adresse</label>
                                            <input type="text" class="form-control py-2" name="a_complement" value="<?= htmlspecialchars($addr['complement_adresse'] ?? '') ?>" placeholder="Bât. A, Appt. 12...">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Code postal <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control py-2" name="a_cp" id="edit-cp" value="<?= htmlspecialchars($addr['code_postal']) ?>" required maxlength="5">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Ville <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control py-2" name="a_ville" id="edit-ville" value="<?= htmlspecialchars($addr['ville']) ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Pays</label>
                                            <select class="form-select py-2" name="a_pays" id="edit-pays">
                                                <?php foreach (['France','Belgique','Suisse','Luxembourg'] as $p): ?>
                                                    <option value="<?= $p ?>" <?= ($addr['pays'] === $p) ? 'selected' : '' ?>><?= $p ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Téléphone</label>
                                            <input type="tel" class="form-control py-2" name="a_telephone" value="<?= htmlspecialchars($addr['telephone'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6 d-flex align-items-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="a_principale" value="1" id="edit_principale_<?= $addr['id_adresse'] ?>" <?= $addr['est_principale'] ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-bold" for="edit_principale_<?= $addr['id_adresse'] ?>">Adresse principale</label>
                                            </div>
                                        </div>
                                        <div class="col-12 d-flex gap-3 mt-2">
                                            <button type="submit" name="edit_adresse" class="btn btn-lg px-5 py-2 fw-bold rounded-pill text-white btn-pill-pro" style="background: linear-gradient(90deg, #ff8c00, #e07b00); border: none;">
                                                <i class="bi bi-save me-2"></i>Enregistrer les modifications
                                            </button>
                                            <a href="compte.php" class="btn btn-lg px-4 py-2 fw-bold rounded-pill btn-outline-secondary">Annuler</a>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- BLOC 3 : MES COMMANDES -->
        <div style="margin-top:50px; padding-top:40px; border-top:2px solid #f0ebe3;">
            <h2 class="fw-bold mb-4" style="font-size:1.5rem;">
                <i class="bi bi-bag-check" style="color:#d4a017; margin-right:10px;"></i>
                Mes commandes
            </h2>

            <?php if (empty($mes_commandes)): ?>
                <div class="text-center py-5" style="background:#faf8f5; border-radius:16px;">
                    <i class="bi bi-bag" style="font-size:3rem; color:#ddd;"></i>
                    <p class="mt-3 text-muted">Vous n'avez pas encore passé de commande.</p>
                    <a href="catalogue.php" class="btn mt-2" style="background:linear-gradient(90deg,#d4a017,#b8860b); color:white; border-radius:10px; padding:10px 28px; font-weight:600;">
                        Découvrir notre catalogue
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle" style="font-size:0.95rem;">
                        <thead>
                            <tr style="background:#faf8f5; border-bottom:2px solid #d4a017;">
                                <th style="padding:14px 16px; color:#d4a017; text-transform:uppercase; font-size:0.78em; letter-spacing:1px;">N° Commande</th>
                                <th style="padding:14px 16px; color:#d4a017; text-transform:uppercase; font-size:0.78em; letter-spacing:1px;">Date</th>
                                <th style="padding:14px 16px; color:#d4a017; text-transform:uppercase; font-size:0.78em; letter-spacing:1px;">Montant</th>
                                <th style="padding:14px 16px; color:#d4a017; text-transform:uppercase; font-size:0.78em; letter-spacing:1px;">Statut</th>
                                <th style="padding:14px 16px; color:#d4a017; text-transform:uppercase; font-size:0.78em; letter-spacing:1px; text-align:center;">Facture</th>
                                <th style="padding:14px 16px; color:#d4a017; text-transform:uppercase; font-size:0.78em; letter-spacing:1px; text-align:center;">Suivi</th>
                                <th style="padding:14px 16px; color:#d4a017; text-transform:uppercase; font-size:0.78em; letter-spacing:1px; text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mes_commandes as $cmd):
                                // ✅ CORRECTION : statuts avec espaces conformes à l'ENUM MySQL
                                $badge = match($cmd['statut']) {
                                    'en attente' => ['background:#fff3cd; color:#856404;',  '⏳ En attente'],
                                    'payee'      => ['background:#d1ecf1; color:#0c5460;',  '💳 Payée'],
                                    'expediee'   => ['background:#d4edda; color:#155724;',  '📦 Expédiée'],
                                    'livree'     => ['background:#d4edda; color:#155724;',  '✅ Livrée'],
                                    'annulee'    => ['background:#f8d7da; color:#721c24;',  '❌ Annulée'],
                                    default      => ['background:#e9ecef; color:#495057;',  ucfirst($cmd['statut'] ?: 'En attente')]
                                };
                            ?>
                            <tr style="border-bottom:1px solid #f0ebe3;">
                                <td style="padding:14px 16px; font-weight:700; color:#d4a017;">
                                    <?= htmlspecialchars($cmd['numero_commande']) ?>
                                </td>
                                <td style="padding:14px 16px; color:#888;">
                                    <?= date('d/m/Y', strtotime($cmd['date_commande'])) ?>
                                </td>
                                <td style="padding:14px 16px; font-weight:700; color:#2c1810;">
                                    <?= number_format((float)$cmd['montant'], 2, ',', ' ') ?> €
                                </td>
                                <td style="padding:14px 16px;">
                                    <span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:0.82em; font-weight:600; <?= $badge[0] ?>">
                                        <?= $badge[1] ?>
                                    </span>
                                </td>
                                <td style="padding:14px 16px; text-align:center;">
                                    <a href="facture.php?order_id=<?= $cmd['id_commande'] ?>" target="_blank"
                                       style="display:inline-flex; align-items:center; gap:6px; background:linear-gradient(135deg,#d4a017,#b8860b); color:white; padding:7px 16px; border-radius:8px; font-size:0.85em; font-weight:600; text-decoration:none; transition:all 0.2s;"
                                       onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(212,160,23,0.4)'"
                                       onmouseout="this.style.transform=''; this.style.boxShadow=''">
                                        📄 Facture PDF
                                    </a>
                                </td>
                                <!-- ✅ Colonne Suivi -->
                                <td style="padding:14px 16px; text-align:center;">
                                    <a href="suivi_commande.php?order_id=<?= $cmd['id_commande'] ?>"
                                       style="display:inline-flex; align-items:center; gap:5px; background:#f5efe4; color:#cc8800; padding:6px 14px; border-radius:20px; font-size:0.82em; font-weight:600; text-decoration:none; border:1px solid #e0c88a; transition:all 0.2s;"
                                       onmouseover="this.style.background='#fff3d6'"
                                       onmouseout="this.style.background='#f5efe4'">
                                        🔍 Voir le suivi
                                    </a>
                                </td>
                                <!-- ✅ Colonne Action avec bouton Annuler -->
                                <td style="padding:14px 16px; text-align:center;">
                                    <?php if ($cmd['statut'] === 'en attente'): ?>
                                        <form method="POST" style="margin:0;" onsubmit="return confirm('Annuler la commande <?= htmlspecialchars($cmd['numero_commande']) ?> ?');">
                                            <input type="hidden" name="annuler_commande" value="1">
                                            <input type="hidden" name="id_commande" value="<?= $cmd['id_commande'] ?>">
                                            <button type="submit" class="btn-annuler-cmd">
                                                ✕ Annuler
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:#ccc; font-size:0.82em;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function toggleFormAdd() {
    const form = document.getElementById('form-add-adresse');
    const isVisible = form.style.display === 'block';
    form.style.display = isVisible ? 'none' : 'block';
    if (!isVisible) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

<?php if ($edit_id > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
    const editInput = document.querySelector('input[name="id_adresse"][value="<?= $edit_id ?>"]');
    if (editInput) editInput.closest('.adresse-card').scrollIntoView({ behavior: 'smooth' });
});
<?php endif; ?>
</script>

<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<style>
    .ui-autocomplete { max-height: 260px; overflow-y: auto; overflow-x: hidden; border: 1px solid #dee2e6; border-radius: 12px; box-shadow: 0 6px 20px rgba(0,0,0,0.12); background: #fff; z-index: 9999 !important; font-size: 0.9rem; padding: 4px 0; }
    .ui-menu-item-wrapper { padding: 10px 16px; cursor: pointer; display: flex; align-items: center; gap: 8px; border-left: 3px solid transparent; transition: background 0.15s; }
    .ui-menu-item-wrapper::before { content: "📍"; font-size: 0.85rem; flex-shrink: 0; }
    .ui-menu-item-wrapper.ui-state-active { background: linear-gradient(90deg, #fff5e6, #fff0d6) !important; border-left: 3px solid #ff8c00 !important; color: #333 !important; }
</style>

<script>
$(document).ready(function () {
    function initAutocompleteAPI(champId, cpId, villeId, paysId) {
        var timer = null;
        $(champId).autocomplete({
            source: function (requete, reponse) {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    $.ajax({
                        url: 'https://api-adresse.data.gouv.fr/search/',
                        data: { q: requete.term, limit: 8, countrycodes: 'fr' },
                        dataType: 'json',
                        success: function (data) {
                            var suggestions = $.map(data.features, function (item) {
                                return { label: item.properties.label, value: item.properties.name, cp: item.properties.postcode, ville: item.properties.city, pays: 'France' };
                            });
                            reponse(suggestions);
                        },
                        error: function () { reponse([]); }
                    });
                }, 300);
            },
            minLength: 3,
            select: function (event, ui) {
                $(champId).val(ui.item.value);
                $(cpId).val(ui.item.cp);
                $(villeId).val(ui.item.ville);
                if (paysId) $(paysId).val(ui.item.pays);
                return false;
            },
            focus: function () { return false; }
        });
    }
    initAutocompleteAPI('#add-adresse', '#add-cp', '#add-ville', '#add-pays');
    initAutocompleteAPI('#edit-adresse', '#edit-cp', '#edit-ville', '#edit-pays');
});
</script>

<?php include '../includes/footer.php'; ?>