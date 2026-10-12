<?php
// ============================================================
// FICHIER : admin.php
// RÔLE    : Panneau d'administration Back-Office Lumoura
// CORRECTIONS :
//   1. Statuts corrigés : 'en attente' (espace) au lieu de 'en_attente'
//   2. Email automatique au client lors d'un changement de statut
//      → Expédiée, Livrée, Annulée
// ============================================================

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Vérification connexion + rôle admin
if (!isLoggedIn()) {
    header('Location: ../../pages/connexion.php');
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur = ?");
$user_stmt->execute([$user_id]);
$admin = $user_stmt->fetch();

if (!$admin || $admin['role'] !== 'admin') {
    header('Location: ../../pages/index.php');
    exit();
}

$success_message = '';
$error_message   = '';
$active_tab      = $_GET['tab'] ?? 'produits';

// ============================================================
// FONCTION : Envoi email de notification au client
// On utilise envoyer-email.php qui contient déjà PHPMailer
// ============================================================
require_once '../../includes/envoyer-email.php';

// ============================================================
// SECTION 2 : AJOUT D'UN NOUVEAU PRODUIT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $nom                = trim($_POST['nom']                       ?? '');
    $description        = trim($_POST['description']               ?? '');
    $description_courte = trim($_POST['description_courte']        ?? '');
    $prix               = floatval($_POST['prix']                  ?? 0);
    $stock              = intval($_POST['stock']                   ?? 0);
    $concentration      = trim($_POST['concentration']             ?? '');
    $genre              = trim($_POST['genre']                     ?? '');
    $volume_ml          = intval($_POST['volume_ml']               ?? 100);
    $id_categorie       = intval($_POST['id_categorie']            ?? 0);
    $promotion          = floatval($_POST['promotion_pourcentage'] ?? 0);
    $image_url          = trim($_POST['image_url']                 ?? '');
    $marque             = trim($_POST['marque']                    ?? 'Lumoura');

    if (empty($nom) || $prix <= 0) {
        $error_message = 'Le nom et le prix sont obligatoires.';
    } else {
        try {
            $insert = $pdo->prepare("
                INSERT INTO produits (nom, description, description_courte, prix, stock, concentration, genre, volume_ml, id_categorie, promotion_pourcentage, image_url, marque, date_ajout)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $insert->execute([$nom, $description, $description_courte, $prix, $stock, $concentration, $genre, $volume_ml, $id_categorie ?: null, $promotion, $image_url, $marque]);
            $success_message = "Produit \"$nom\" ajouté avec succès !";
            $active_tab = 'produits';
        } catch (PDOException $e) {
            $error_message = 'Erreur lors de l\'ajout : ' . $e->getMessage();
        }
    }
}

// ============================================================
// SECTION 3 : SUPPRESSION D'UN PRODUIT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $id_produit = intval($_POST['id_produit']);
    try {
        $del = $pdo->prepare("DELETE FROM produits WHERE id_produit = ?");
        $del->execute([$id_produit]);
        $success_message = 'Produit supprimé avec succès.';
    } catch (PDOException $e) {
        $error_message = 'Impossible de supprimer ce produit (il est peut-être lié à des commandes).';
    }
}

// ============================================================
// SECTION 4 : MISE À JOUR STATUT + EMAIL CLIENT
// ✅ CORRECTION : statuts avec espaces conformes à l'ENUM MySQL
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $id_commande = intval($_POST['id_commande']);
    $statut      = $_POST['statut'] ?? '';

    // ✅ Whitelist avec les vrais statuts ENUM MySQL
    $allowed = ['en attente', 'payee', 'expediee', 'livree', 'annulee'];

    if (in_array($statut, $allowed)) {

        // Récupérer ancien statut + infos client AVANT mise à jour
        $old_stmt = $pdo->prepare("
            SELECT c.statut, c.numero_commande, u.email, u.prenom
            FROM commandes c
            JOIN utilisateurs u ON c.id_utilisateur = u.id_utilisateur
            WHERE c.id_commande = ?
        ");
        $old_stmt->execute([$id_commande]);
        $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);

        // Mettre à jour le statut
        $pdo->prepare("UPDATE commandes SET statut = ? WHERE id_commande = ?")
            ->execute([$statut, $id_commande]);

        $success_message = 'Statut mis à jour.';
        $active_tab      = 'commandes';

        // ✅ Envoyer email si statut a changé et est notifiable
        $statuts_notifiables = ['expediee', 'livree', 'annulee'];

        if ($old_data && $old_data['statut'] !== $statut && in_array($statut, $statuts_notifiables)) {
            $envoye = envoyerEmailStatut(
                $old_data['email'],
                $old_data['prenom'],
                $old_data['numero_commande'],
                $statut
            );
            if ($envoye) {
                $success_message .= ' — Email envoyé à ' . htmlspecialchars($old_data['email']) . ' ✉️';
            } else {
                $success_message .= ' — <span style="color:#ffc107;">⚠️ Email non envoyé (vérifier config SMTP)</span>';
                error_log("Échec email statut commande #$id_commande");
            }
        }
    } else {
        $error_message = 'Statut invalide.';
    }
}

// ============================================================
// SECTION 5 : CHARGEMENT DES DONNÉES
// ============================================================
$produits   = $pdo->query("SELECT p.*, c.nom_categorie as categorie_nom FROM produits p LEFT JOIN categories c ON p.id_categorie = c.id_categorie ORDER BY p.date_ajout DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY nom_categorie")->fetchAll();
$commandes  = $pdo->query("SELECT c.*, u.prenom, u.nom, u.email FROM commandes c JOIN utilisateurs u ON c.id_utilisateur = u.id_utilisateur ORDER BY c.date_commande DESC LIMIT 50")->fetchAll();

$total_produits  = count($produits);
$total_commandes = count($commandes);
$ca_total        = $pdo->query("SELECT SUM(montant) as total FROM commandes WHERE statut != 'annulee'")->fetch()['total'] ?? 0;
$total_clients   = $pdo->query("SELECT COUNT(*) as n FROM utilisateurs WHERE role = 'client'")->fetch()['n'];

$pageTitle = "Admin - Lumoura";
include '../../includes/header.php';
?>

<div style="padding-top: 120px; background: linear-gradient(135deg, #1a1008 0%, #3a2c1f 100%); min-height: 100vh;">
    <div style="max-width: 1300px; margin: 0 auto; padding: 40px 20px;">

        <!-- En-tête -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 40px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="color: #d4a017; font-size: 2em; margin: 0 0 5px 0;">
                    <i class="fas fa-cog" style="margin-right: 12px;"></i>Panneau Admin
                </h1>
                <p style="color: #aaa; margin: 0;">Bienvenue, <?php echo htmlspecialchars($admin['prenom']); ?> — Lumoura Back-Office</p>
            </div>
            <a href="../../pages/profil.php" style="background: rgba(255,255,255,0.1); color: #d4a017; padding: 10px 20px; border-radius: 20px; text-decoration: none; font-weight: bold;">
                <i class="fas fa-arrow-left" style="margin-right: 8px;"></i>Retour au profil
            </a>
        </div>

        <!-- Statistiques -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px;">
            <?php
            $stats = [
                ['icon' => 'fa-spray-can', 'value' => $total_produits,                               'label' => 'Produits',  'color' => '#d4a017'],
                ['icon' => 'fa-box',        'value' => $total_commandes,                              'label' => 'Commandes', 'color' => '#2a9d8f'],
                ['icon' => 'fa-users',      'value' => $total_clients,                                'label' => 'Clients',   'color' => '#e76f51'],
                ['icon' => 'fa-euro-sign',  'value' => number_format($ca_total, 0, ',', ' ') . ' €', 'label' => 'CA Total',  'color' => '#8ecae6'],
            ];
            foreach ($stats as $st): ?>
            <div style="background: rgba(255,255,255,0.07); border-radius: 16px; padding: 25px; text-align: center; border: 1px solid rgba(212,160,23,0.2);">
                <i class="fas <?php echo $st['icon']; ?>" style="font-size: 2em; color: <?php echo $st['color']; ?>; margin-bottom: 12px; display: block;"></i>
                <div style="font-size: 1.8em; font-weight: bold; color: white; margin-bottom: 5px;"><?php echo $st['value']; ?></div>
                <div style="color: #aaa; font-size: 0.9em;"><?php echo $st['label']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div style="padding: 15px 20px; margin-bottom: 25px; background: rgba(40,167,69,0.2); border: 1px solid #28a745; border-radius: 10px; color: #90ee90; text-align: center;">
                <i class="fas fa-check-circle" style="margin-right: 8px;"></i><?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div style="padding: 15px 20px; margin-bottom: 25px; background: rgba(220,53,69,0.2); border: 1px solid #dc3545; border-radius: 10px; color: #ffaaaa; text-align: center;">
                <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Onglets -->
        <div style="display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap;">
            <?php
            $tabs = [
                'produits'  => ['icon' => 'fa-spray-can',   'label' => 'Produits'],
                'ajouter'   => ['icon' => 'fa-plus-circle', 'label' => 'Ajouter un produit'],
                'commandes' => ['icon' => 'fa-box',         'label' => 'Commandes'],
            ];
            foreach ($tabs as $key => $tab):
                $is_active = ($active_tab === $key);
            ?>
            <a href="?tab=<?php echo $key; ?>" style="
                padding: 12px 22px; border-radius: 30px; text-decoration: none; font-weight: bold; font-size: 0.95em; transition: all 0.3s;
                <?php echo $is_active
                    ? 'background: #d4a017; color: white; box-shadow: 0 4px 15px rgba(212,160,23,0.4);'
                    : 'background: rgba(255,255,255,0.08); color: #ccc;';
                ?>
            ">
                <i class="fas <?php echo $tab['icon']; ?>" style="margin-right: 8px;"></i><?php echo $tab['label']; ?>
            </a>
            <?php endforeach; ?>

            <a href="../../pages/livreurs.php" style="padding: 12px 22px; border-radius: 30px; text-decoration: none; font-weight: bold; font-size: 0.95em; background: rgba(255,255,255,0.08); color: #ccc; transition: all 0.3s;"
               onmouseover="this.style.background='rgba(212,160,23,0.2)'; this.style.color='#d4a017';"
               onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.color='#ccc';">
                <i class="fas fa-motorcycle" style="margin-right: 8px;"></i>Livreurs
            </a>
        </div>

        <!-- ===== ONGLET 1 : LISTE PRODUITS ===== -->
        <?php if ($active_tab === 'produits'): ?>
        <div style="background: rgba(255,255,255,0.05); border-radius: 16px; padding: 30px; border: 1px solid rgba(212,160,23,0.15);">
            <h2 style="color: #d4a017; margin: 0 0 25px 0; font-size: 1.4em;">
                <i class="fas fa-spray-can" style="margin-right: 10px;"></i>Liste des produits (<?php echo $total_produits; ?>)
            </h2>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
                    <thead>
                        <tr style="border-bottom: 2px solid #d4a017;">
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Image</th>
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Nom</th>
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Prix</th>
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Stock</th>
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Genre</th>
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Catégorie</th>
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produits as $p): ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.07);" onmouseover="this.style.background='rgba(212,160,23,0.07)'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 10px;">
                                <?php
                                $img_src = $p['image_url'] ?? '';
                                if (!empty($img_src) && strpos($img_src, 'http') !== 0) {
                                    $img_src = '../../' . ltrim(str_replace('../', '', $img_src), '/');
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($img_src); ?>"
                                     onerror="this.style.display='none'"
                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                            </td>
                            <td style="padding: 10px; color: white; font-weight: bold;"><?php echo htmlspecialchars($p['nom']); ?></td>
                            <td style="padding: 10px; color: #d4a017; font-weight: bold;"><?php echo number_format($p['prix'], 2, ',', ' '); ?> €</td>
                            <td style="padding: 10px;">
                                <span style="color: <?php echo $p['stock'] <= 5 ? '#ff6b6b' : '#90ee90'; ?>; font-weight: bold;">
                                    <?php echo $p['stock']; ?>
                                </span>
                            </td>
                            <td style="padding: 10px; color: #ccc;"><?php echo htmlspecialchars($p['genre'] ?? '-'); ?></td>
                            <td style="padding: 10px; color: #ccc;"><?php echo htmlspecialchars($p['categorie_nom'] ?? '-'); ?></td>
                            <td style="padding: 10px;">
                                <form method="POST" onsubmit="return confirm('Supprimer ce produit ?')">
                                    <input type="hidden" name="id_produit" value="<?php echo $p['id_produit']; ?>">
                                    <button type="submit" name="delete_product" style="background: #dc3545; color: white; border: none; padding: 7px 14px; border-radius: 8px; cursor: pointer; font-size: 0.85em;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== ONGLET 2 : AJOUTER PRODUIT ===== -->
        <?php elseif ($active_tab === 'ajouter'): ?>
        <div style="background: rgba(255,255,255,0.05); border-radius: 16px; padding: 35px; border: 1px solid rgba(212,160,23,0.15); max-width: 750px;">
            <h2 style="color: #d4a017; margin: 0 0 25px 0; font-size: 1.4em;">
                <i class="fas fa-plus-circle" style="margin-right: 10px;"></i>Ajouter un nouveau produit
            </h2>
            <form method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Nom du parfum <span style="color: #ff6b6b;">*</span></label>
                        <input type="text" name="nom" required placeholder="Ex: Rose Éternelle"
                            style="width: 100%; padding: 13px; background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='rgba(212,160,23,0.3)'">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Marque</label>
                        <input type="text" name="marque" value="Lumoura" placeholder="Lumoura"
                            style="width: 100%; padding: 13px; background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='rgba(212,160,23,0.3)'">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Prix (€) <span style="color: #ff6b6b;">*</span></label>
                        <input type="number" name="prix" required min="0.01" step="0.01" placeholder="125.00"
                            style="width: 100%; padding: 13px; background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='rgba(212,160,23,0.3)'">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Stock</label>
                        <input type="number" name="stock" min="0" value="10" placeholder="10"
                            style="width: 100%; padding: 13px; background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='rgba(212,160,23,0.3)'">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Genre</label>
                        <select name="genre" style="width: 100%; padding: 13px; background: #2a1f12; border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;">
                            <option value="Femme">Femme</option>
                            <option value="Homme">Homme</option>
                            <option value="Unisexe">Unisexe</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Concentration</label>
                        <select name="concentration" style="width: 100%; padding: 13px; background: #2a1f12; border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;">
                            <option value="Eau de Parfum">Eau de Parfum</option>
                            <option value="Eau de Toilette">Eau de Toilette</option>
                            <option value="Parfum">Parfum</option>
                            <option value="Eau de Cologne">Eau de Cologne</option>
                            <option value="Eau Fraîche">Eau Fraîche</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Volume (ml)</label>
                        <input type="number" name="volume_ml" min="1" value="100"
                            style="width: 100%; padding: 13px; background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='rgba(212,160,23,0.3)'">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Promotion (%)</label>
                        <input type="number" name="promotion_pourcentage" min="0" max="100" value="0" placeholder="0"
                            style="width: 100%; padding: 13px; background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='rgba(212,160,23,0.3)'">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Catégorie</label>
                        <select name="id_categorie" style="width: 100%; padding: 13px; background: #2a1f12; border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;">
                            <option value="">-- Aucune --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id_categorie']; ?>"><?php echo htmlspecialchars($cat['nom_categorie']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">URL de l'image</label>
                        <input type="text" name="image_url" placeholder="./assets/image/mon-parfum.jpg"
                            style="width: 100%; padding: 13px; background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='rgba(212,160,23,0.3)'">
                    </div>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Description courte</label>
                    <input type="text" name="description_courte" placeholder="Parfum floral romantique..."
                        style="width: 100%; padding: 13px; background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;"
                        onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='rgba(212,160,23,0.3)'">
                </div>
                <div style="margin-bottom: 25px;">
                    <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Description complète</label>
                    <textarea name="description" rows="4" placeholder="Description détaillée du parfum, ses notes olfactives..."
                        style="width: 100%; padding: 13px; background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box; resize: vertical;"
                        onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='rgba(212,160,23,0.3)'"></textarea>
                </div>
                <button type="submit" name="add_product" style="background: #d4a017; color: white; border: none; padding: 16px 40px; border-radius: 10px; font-weight: bold; font-size: 1.1em; cursor: pointer; transition: background 0.3s;"
                    onmouseover="this.style.background='#c29b15'" onmouseout="this.style.background='#d4a017'">
                    <i class="fas fa-plus" style="margin-right: 10px;"></i>Ajouter le produit
                </button>
            </form>
        </div>

        <!-- ===== ONGLET 3 : COMMANDES ===== -->
        <?php elseif ($active_tab === 'commandes'): ?>
        <div style="background: rgba(255,255,255,0.05); border-radius: 16px; padding: 30px; border: 1px solid rgba(212,160,23,0.15);">
            <h2 style="color: #d4a017; margin: 0 0 10px 0; font-size: 1.4em;">
                <i class="fas fa-box" style="margin-right: 10px;"></i>Toutes les commandes (<?php echo count($commandes); ?>)
            </h2>

            <!-- Info emails automatiques -->
            <div style="margin-bottom: 22px; padding: 12px 18px; background: rgba(42,157,143,0.15); border: 1px solid rgba(42,157,143,0.4); border-radius: 10px; font-size: 0.88em; color: #90ee90;">
                <i class="fas fa-envelope" style="margin-right: 8px;"></i>
                Email automatique envoyé au client lors du passage à <strong>Expédiée</strong>, <strong>Livrée</strong> ou <strong>Annulée</strong>.
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
                    <thead>
                        <tr style="border-bottom: 2px solid #d4a017;">
                            <th style="padding: 12px; text-align: left; color: #d4a017;">N° Commande</th>
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Client</th>
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Date</th>
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Montant</th>
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Statut actuel</th>
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Changer le statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // ✅ Couleurs avec les vrais statuts (avec espaces)
                        $statut_colors = [
                            'en attente' => ['bg' => '#856404', 'label' => '&#9203; En attente'],
                            'payee'      => ['bg' => '#0c5460', 'label' => '&#128179; Payee'],
                            'expediee'   => ['bg' => '#004085', 'label' => '&#128230; Expediee'],
                            'livree'     => ['bg' => '#155724', 'label' => '&#10003; Livree'],
                            'annulee'    => ['bg' => '#721c24', 'label' => '&#10005; Annulee'],
                        ];
                        foreach ($commandes as $cmd):
                            $s = $statut_colors[$cmd['statut']] ?? ['bg' => '#333', 'label' => ucfirst($cmd['statut'] ?: 'En attente')];
                        ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.07);" onmouseover="this.style.background='rgba(212,160,23,0.07)'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 10px; color: #d4a017; font-weight: bold;"><?php echo htmlspecialchars($cmd['numero_commande'] ?? '#' . $cmd['id_commande']); ?></td>
                            <td style="padding: 10px; color: white;">
                                <?php echo htmlspecialchars($cmd['prenom'] . ' ' . $cmd['nom']); ?>
                                <br><span style="color: #888; font-size: 0.85em;"><?php echo htmlspecialchars($cmd['email']); ?></span>
                            </td>
                            <td style="padding: 10px; color: #ccc;"><?php echo date('d/m/Y H:i', strtotime($cmd['date_commande'])); ?></td>
                            <td style="padding: 10px; color: #d4a017; font-weight: bold;"><?php echo number_format($cmd['montant'], 2, ',', ' '); ?> €</td>
                            <td style="padding: 10px;">
                                <span style="background: <?php echo $s['bg']; ?>; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.85em; font-weight: bold;">
                                    <?php echo $s['label']; ?>
                                </span>
                            </td>
                            <td style="padding: 10px;">
                                <form method="POST" style="display: flex; gap: 6px; align-items: center;">
                                    <input type="hidden" name="id_commande" value="<?php echo $cmd['id_commande']; ?>">
                                    <!-- ✅ Options avec les vrais statuts ENUM MySQL -->
                                    <select name="statut" style="background: #2a1f12; border: 1px solid rgba(212,160,23,0.3); color: white; padding: 6px 10px; border-radius: 6px; font-size: 0.85em;">
                                        <option value="en attente" <?php echo $cmd['statut']==='en attente' ? 'selected':'' ?>>En attente</option>
                                        <option value="payee"      <?php echo $cmd['statut']==='payee'      ? 'selected':'' ?>>Payee</option>
                                        <option value="expediee"   <?php echo $cmd['statut']==='expediee'   ? 'selected':'' ?>>Expediee</option>
                                        <option value="livree"     <?php echo $cmd['statut']==='livree'     ? 'selected':'' ?>>Livree</option>
                                        <option value="annulee"    <?php echo $cmd['statut']==='annulee'    ? 'selected':'' ?>>Annulee</option>
                                    </select>
                                    <button type="submit" name="update_order_status"
                                            style="background: #2a9d8f; color: white; border: none; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 0.85em; white-space: nowrap;"
                                            title="Valider et notifier le client si necessaire">
                                        <i class="fas fa-check"></i> Valider
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include '../../includes/footer.php'; ?>