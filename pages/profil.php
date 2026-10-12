<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: connexion.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Récupérer les infos de l'utilisateur
$user_stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

// Récupérer les commandes de l'utilisateur
$orders_stmt = $pdo->prepare("SELECT * FROM commandes WHERE id_utilisateur = ? ORDER BY date_commande DESC LIMIT 10");
$orders_stmt->execute([$user_id]);
$orders = $orders_stmt->fetchAll();

// Récupérer les favoris
try {
    $favs_stmt = $pdo->prepare("
        SELECT p.* FROM favoris f
        JOIN produits p ON f.id_produit = p.id_produit
        WHERE f.id_utilisateur = ?
    ");
    $favs_stmt->execute([$user_id]);
    $favoris = $favs_stmt->fetchAll();
} catch (Exception $e) {
    $favoris = [];
}

// Traitement modification des infos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['update_info'])) {
        $prenom = trim($_POST['prenom'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');

        if (empty($prenom) || empty($nom) || empty($email)) {
            $error_message = 'Veuillez remplir tous les champs obligatoires.';
        } else {
            // Vérifier si l'email est déjà utilisé par un autre utilisateur
            $check = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE email = ? AND id_utilisateur != ?");
            $check->execute([$email, $user_id]);
            if ($check->fetch()) {
                $error_message = 'Cet email est déjà utilisé par un autre compte.';
            } else {
                $update = $pdo->prepare("UPDATE utilisateurs SET prenom=?, nom=?, email=?, telephone=? WHERE id_utilisateur=?");
                $update->execute([$prenom, $nom, $email, $telephone, $user_id]);
                $_SESSION['user_email'] = $email;
                $success_message = 'Vos informations ont été mises à jour avec succès !';
                // Rafraîchir les données
                $user_stmt->execute([$user_id]);
                $user = $user_stmt->fetch();
            }
        }
    }

    if (isset($_POST['update_password'])) {
        $old_password = $_POST['old_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = 'Veuillez remplir tous les champs du mot de passe.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'Les nouveaux mots de passe ne correspondent pas.';
        } elseif (strlen($new_password) < 6) {
            $error_message = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
        } elseif (!password_verify($old_password, $user['mot_de_passe'])) {
            $error_message = 'L\'ancien mot de passe est incorrect.';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe=? WHERE id_utilisateur=?");
            $update->execute([$hashed, $user_id]);
            $success_message = 'Mot de passe modifié avec succès !';
        }
    }

    if (isset($_POST['remove_favori'])) {
        $id_produit = (int)$_POST['id_produit'];
        $del = $pdo->prepare("DELETE FROM favoris WHERE id_utilisateur=? AND id_produit=?");
        $del->execute([$user_id, $id_produit]);
        // Rafraîchir les favoris
        $favs_stmt->execute([$user_id]);
        $favoris = $favs_stmt->fetchAll();
        $success_message = 'Produit retiré des favoris.';
    }
}

$pageTitle = "Mon Profil - Lumoura";
include '../includes/header.php';
?>

<div style="padding-top: 120px; background: linear-gradient(135deg, #f5f3ef 0%, #e8e4dc 100%); min-height: 100vh;">
    <div class="container" style="max-width: 1100px; margin: 0 auto; padding: 40px 20px;">

        <!-- En-tête -->
        <div style="text-align: center; margin-bottom: 40px;">
            <div style="width: 90px; height: 90px; background: linear-gradient(135deg, #d4a017, #c29b15); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-user" style="font-size: 2.5em; color: white;"></i>
            </div>
            <h1 style="font-size: 2em; color: #3a2c1f; margin: 0 0 8px 0;">
                Bonjour, <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?> 👋
            </h1>
            <p style="color: #888; font-size: 1em;">Membre depuis le <?php echo date('d/m/Y', strtotime($user['date_inscription'])); ?></p>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="admin/admin.php" style="display: inline-block; margin-top: 10px; background: #3a2c1f; color: #d4a017; padding: 8px 20px; border-radius: 20px; text-decoration: none; font-weight: bold; font-size: 0.9em;">
                    <i class="fas fa-cog" style="margin-right: 6px;"></i>Accéder au panneau Admin
                </a>
            <?php endif; ?>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div style="padding: 15px 20px; margin-bottom: 25px; background: #d4edda; border: 2px solid #c3e6cb; border-radius: 10px; color: #155724; text-align: center;">
                <i class="fas fa-check-circle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div style="padding: 15px 20px; margin-bottom: 25px; background: #f8d7da; border: 2px solid #f5c6cb; border-radius: 10px; color: #721c24; text-align: center;">
                <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Onglets -->
        <div style="display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap;">
            <?php
            $tabs = [
                'infos'         => ['icon' => 'fa-user-edit',    'label' => 'Mes informations'],
                'password'      => ['icon' => 'fa-lock',         'label' => 'Mot de passe'],
                'commandes'     => ['icon' => 'fa-box',          'label' => 'Mes commandes'],
                'favoris'       => ['icon' => 'fa-heart',        'label' => 'Mes favoris'],
            ];
            $active_tab = $_GET['tab'] ?? 'infos';
            foreach ($tabs as $key => $tab):
                $is_active = ($active_tab === $key);
            ?>
            <a href="?tab=<?php echo $key; ?>" style="
                padding: 12px 22px;
                border-radius: 30px;
                text-decoration: none;
                font-weight: bold;
                font-size: 0.95em;
                transition: all 0.3s;
                <?php echo $is_active
                    ? 'background: #d4a017; color: white; box-shadow: 0 4px 15px rgba(212,160,23,0.3);'
                    : 'background: white; color: #555; box-shadow: 0 2px 8px rgba(0,0,0,0.08);'; ?>
            ">
                <i class="fas <?php echo $tab['icon']; ?>" style="margin-right: 8px;"></i>
                <?php echo $tab['label']; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- ============ ONGLET INFOS ============ -->
        <?php if ($active_tab === 'infos'): ?>
        <div style="background: white; border-radius: 16px; padding: 35px; box-shadow: 0 8px 25px rgba(0,0,0,0.08);">
            <h2 style="margin: 0 0 25px 0; color: #3a2c1f; border-bottom: 2px solid #d4a017; padding-bottom: 15px; font-size: 1.5em;">
                <i class="fas fa-user-edit" style="color: #d4a017; margin-right: 10px;"></i>Mes informations
            </h2>
            <form method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">Prénom <span style="color: #c0392b;">*</span></label>
                        <input type="text" name="prenom" value="<?php echo htmlspecialchars($user['prenom']); ?>" required
                            style="width: 100%; padding: 13px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='#e0e0e0'">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">Nom <span style="color: #c0392b;">*</span></label>
                        <input type="text" name="nom" value="<?php echo htmlspecialchars($user['nom']); ?>" required
                            style="width: 100%; padding: 13px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='#e0e0e0'">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">Email <span style="color: #c0392b;">*</span></label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required
                            style="width: 100%; padding: 13px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='#e0e0e0'">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">Téléphone</label>
                        <input type="tel" name="telephone" value="<?php echo htmlspecialchars($user['telephone'] ?? ''); ?>"
                            style="width: 100%; padding: 13px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='#e0e0e0'">
                    </div>
                </div>
                <button type="submit" name="update_info" style="background: #d4a017; color: white; border: none; padding: 14px 35px; border-radius: 10px; font-weight: bold; font-size: 1em; cursor: pointer;">
                    <i class="fas fa-save" style="margin-right: 8px;"></i>Sauvegarder
                </button>
            </form>
        </div>

        <!-- ============ ONGLET MOT DE PASSE ============ -->
        <?php elseif ($active_tab === 'password'): ?>
        <div style="background: white; border-radius: 16px; padding: 35px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); max-width: 550px;">
            <h2 style="margin: 0 0 25px 0; color: #3a2c1f; border-bottom: 2px solid #d4a017; padding-bottom: 15px; font-size: 1.5em;">
                <i class="fas fa-lock" style="color: #d4a017; margin-right: 10px;"></i>Changer mon mot de passe
            </h2>
            <form method="POST">
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">Ancien mot de passe</label>
                        <input type="password" name="old_password" required
                            style="width: 100%; padding: 13px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='#e0e0e0'">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">Nouveau mot de passe</label>
                        <input type="password" name="new_password" required minlength="6"
                            style="width: 100%; padding: 13px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='#e0e0e0'">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">Confirmer le nouveau mot de passe</label>
                        <input type="password" name="confirm_password" required minlength="6"
                            style="width: 100%; padding: 13px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='#e0e0e0'">
                    </div>
                    <div>
                        <button type="submit" name="update_password" style="background: #d4a017; color: white; border: none; padding: 14px 35px; border-radius: 10px; font-weight: bold; font-size: 1em; cursor: pointer;">
                            <i class="fas fa-key" style="margin-right: 8px;"></i>Modifier le mot de passe
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ============ ONGLET COMMANDES ============ -->
        <?php elseif ($active_tab === 'commandes'): ?>
        <div style="background: white; border-radius: 16px; padding: 35px; box-shadow: 0 8px 25px rgba(0,0,0,0.08);">
            <h2 style="margin: 0 0 25px 0; color: #3a2c1f; border-bottom: 2px solid #d4a017; padding-bottom: 15px; font-size: 1.5em;">
                <i class="fas fa-box" style="color: #d4a017; margin-right: 10px;"></i>Mes commandes
            </h2>
            <?php if (empty($orders)): ?>
                <div style="text-align: center; padding: 50px 20px; color: #888;">
                    <i class="fas fa-box-open" style="font-size: 3em; margin-bottom: 15px; display: block; color: #ddd;"></i>
                    <p style="font-size: 1.1em;">Vous n'avez pas encore de commandes.</p>
                    <a href="catalogue.php" style="display: inline-block; margin-top: 15px; background: #d4a017; color: white; padding: 12px 30px; border-radius: 10px; text-decoration: none; font-weight: bold;">
                        Découvrir nos parfums
                    </a>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f5f0;">
                                <th style="padding: 14px; text-align: left; color: #3a2c1f; border-bottom: 2px solid #d4a017;">Commande</th>
                                <th style="padding: 14px; text-align: left; color: #3a2c1f; border-bottom: 2px solid #d4a017;">Date</th>
                                <th style="padding: 14px; text-align: left; color: #3a2c1f; border-bottom: 2px solid #d4a017;">Montant</th>
                                <th style="padding: 14px; text-align: left; color: #3a2c1f; border-bottom: 2px solid #d4a017;">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): 
                                $statut_colors = [
                                    'en_attente'  => ['bg' => '#fff3cd', 'color' => '#856404', 'label' => 'En attente'],
                                    'confirmee'   => ['bg' => '#d4edda', 'color' => '#155724', 'label' => 'Confirmée'],
                                    'expediee'    => ['bg' => '#cce5ff', 'color' => '#004085', 'label' => 'Expédiée'],
                                    'livree'      => ['bg' => '#d4edda', 'color' => '#155724', 'label' => 'Livrée'],
                                    'annulee'     => ['bg' => '#f8d7da', 'color' => '#721c24', 'label' => 'Annulée'],
                                ];
                                $s = $statut_colors[$order['statut']] ?? ['bg' => '#eee', 'color' => '#333', 'label' => $order['statut']];
                            ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 14px; font-weight: bold; color: #d4a017;"><?php echo htmlspecialchars($order['numero_commande'] ?? '#' . $order['id_commande']); ?></td>
                                <td style="padding: 14px; color: #555;"><?php echo date('d/m/Y', strtotime($order['date_commande'])); ?></td>
                                <td style="padding: 14px; font-weight: bold; color: #3a2c1f;"><?php echo number_format($order['montant'], 2, ',', ' '); ?> €</td>
                                <td style="padding: 14px;">
                                    <span style="background: <?php echo $s['bg']; ?>; color: <?php echo $s['color']; ?>; padding: 5px 12px; border-radius: 20px; font-size: 0.85em; font-weight: bold;">
                                        <?php echo $s['label']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ============ ONGLET FAVORIS ============ -->
        <?php elseif ($active_tab === 'favoris'): ?>
        <div style="background: white; border-radius: 16px; padding: 35px; box-shadow: 0 8px 25px rgba(0,0,0,0.08);">
            <h2 style="margin: 0 0 25px 0; color: #3a2c1f; border-bottom: 2px solid #d4a017; padding-bottom: 15px; font-size: 1.5em;">
                <i class="fas fa-heart" style="color: #d4a017; margin-right: 10px;"></i>Mes favoris
            </h2>
            <?php if (empty($favoris)): ?>
                <div style="text-align: center; padding: 50px 20px; color: #888;">
                    <i class="fas fa-heart-broken" style="font-size: 3em; margin-bottom: 15px; display: block; color: #ddd;"></i>
                    <p style="font-size: 1.1em;">Vous n'avez pas encore de favoris.</p>
                    <a href="catalogue.php" style="display: inline-block; margin-top: 15px; background: #d4a017; color: white; padding: 12px 30px; border-radius: 10px; text-decoration: none; font-weight: bold;">
                        Parcourir le catalogue
                    </a>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px;">
                    <?php foreach ($favoris as $fav): ?>
                    <div style="border: 1px solid #eee; border-radius: 12px; overflow: hidden; transition: box-shadow 0.3s;" onmouseover="this.style.boxShadow='0 8px 20px rgba(0,0,0,0.12)'" onmouseout="this.style.boxShadow='none'">
                        <img src="<?php echo htmlspecialchars($fav['image_url'] ?? './assets/image/default.jpg'); ?>"
                             alt="<?php echo htmlspecialchars($fav['nom']); ?>"
                             onerror="this.src='./assets/image/default.jpg'"
                             style="width: 100%; height: 160px; object-fit: cover;">
                        <div style="padding: 15px;">
                            <div style="font-weight: bold; color: #3a2c1f; margin-bottom: 5px; font-size: 0.95em;"><?php echo htmlspecialchars($fav['nom']); ?></div>
                            <div style="color: #d4a017; font-weight: bold; margin-bottom: 12px;"><?php echo number_format($fav['prix'], 2, ',', ' '); ?> €</div>
                            <div style="display: flex; gap: 8px;">
                                <a href="produit.php?id=<?php echo $fav['id_produit']; ?>" style="flex: 1; background: #d4a017; color: white; padding: 8px; border-radius: 8px; text-decoration: none; text-align: center; font-size: 0.85em; font-weight: bold;">Voir</a>
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="id_produit" value="<?php echo $fav['id_produit']; ?>">
                                    <button type="submit" name="remove_favori" style="width: 100%; background: #f8d7da; color: #721c24; border: none; padding: 8px; border-radius: 8px; cursor: pointer; font-size: 0.85em; font-weight: bold;">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include '../includes/footer.php'; ?>