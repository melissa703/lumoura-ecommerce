<?php
// ============================================================
// FICHIER : admin.php
// RÔLE    : Panneau d'administration Back-Office du site Lumoura
//           → Gérer les produits (liste, ajout, suppression)
//           → Gérer les commandes (liste, changement de statut)
//           → Afficher les statistiques globales du site
// ACCÈS   : Réservé exclusivement aux utilisateurs avec role = 'admin'
// ============================================================

// --- INCLUSION DES FICHIERS PARTAGÉS ---
require_once '../includes/config.php';    // Configuration générale
require_once '../includes/db.php';        // Connexion BDD (variable $pdo disponible)
require_once '../includes/functions.php'; // Fonctions utilitaires : isLoggedIn(), etc.

// ============================================================
// SECTION 1 : DOUBLE VÉRIFICATION DE SÉCURITÉ (ACCÈS ADMIN)
// ============================================================
// ÉTAPE 1 : L'utilisateur doit être connecté (session active)
if (!isLoggedIn()) {
    header('Location: connexion.php'); // Redirige vers la page de connexion
    exit();                            // TOUJOURS appeler exit() après un header redirect
}

// ÉTAPE 2 : L'utilisateur connecté doit avoir le rôle 'admin' en BDD
// On récupère ses infos depuis la table utilisateurs pour vérifier
$user_id   = $_SESSION['user_id'];
$user_stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur = ?");
$user_stmt->execute([$user_id]);
$admin = $user_stmt->fetch();

// Si l'utilisateur n'existe pas OU si son rôle n'est pas 'admin' → accès refusé
if (!$admin || $admin['role'] !== 'admin') {
    header('Location: index.php'); // Redirige vers la page d'accueil
    exit();
}
// Après ces 2 vérifications, on est certains que seul un vrai admin peut continuer

// --- VARIABLES GLOBALES DE LA PAGE ---
$success_message = ''; // Message de succès affiché en vert (ex: "Produit ajouté avec succès")
$error_message   = ''; // Message d'erreur affiché en rouge (ex: "Le nom est obligatoire")

// Onglet actif : récupéré depuis l'URL ?tab=... (par défaut : 'produits')
// Permet de rester sur le bon onglet après une action POST
$active_tab = $_GET['tab'] ?? 'produits';

// ============================================================
// SECTION 2 : AJOUT D'UN NOUVEAU PRODUIT
// ============================================================
// Déclenchée quand le formulaire d'ajout est soumis (bouton name="add_product")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    
    // Récupération et nettoyage de tous les champs du formulaire
    // trim() : supprime les espaces inutiles en début/fin
    // floatval() / intval() : conversion sécurisée en nombre (évite les injections)
    $nom                = trim($_POST['nom']                  ?? '');
    $description        = trim($_POST['description']          ?? '');
    $description_courte = trim($_POST['description_courte']   ?? '');
    $prix               = floatval($_POST['prix']             ?? 0);
    $stock              = intval($_POST['stock']              ?? 0);
    $concentration      = trim($_POST['concentration']        ?? '');
    $genre              = trim($_POST['genre']                ?? '');
    $volume_ml          = intval($_POST['volume_ml']          ?? 100);
    $id_categorie       = intval($_POST['id_categorie']       ?? 0);
    $promotion          = floatval($_POST['promotion_pourcentage'] ?? 0);
    $image_url          = trim($_POST['image_url']            ?? '');
    $marque             = trim($_POST['marque']               ?? 'Lumoura');

    // Validation : le nom et le prix sont les seuls champs obligatoires
    if (empty($nom) || $prix <= 0) {
        $error_message = 'Le nom et le prix sont obligatoires.';
    } else {
        try {
            // Insertion en BDD avec requête préparée (les ? sont remplacés dans execute())
            // NOW() : insère automatiquement la date et heure actuelles côté MySQL
            // $id_categorie ?: null : si id_categorie vaut 0 (aucune sélection), on met NULL en BDD
            $insert = $pdo->prepare("
                INSERT INTO produits (nom, description, description_courte, prix, stock, concentration, genre, volume_ml, id_categorie, promotion_pourcentage, image_url, marque, date_ajout)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $insert->execute([$nom, $description, $description_courte, $prix, $stock, $concentration, $genre, $volume_ml, $id_categorie ?: null, $promotion, $image_url, $marque]);
            $success_message = "Produit \"$nom\" ajouté avec succès !";
            $active_tab = 'produits'; // Redirige l'affichage vers l'onglet liste
        } catch (PDOException $e) {
            $error_message = 'Erreur lors de l\'ajout : ' . $e->getMessage();
        }
    }
}

// ============================================================
// SECTION 3 : SUPPRESSION D'UN PRODUIT
// ============================================================
// Déclenchée quand le bouton "Supprimer" (name="delete_product") est cliqué
// Le produit est supprimé de la table 'produits' par son id_produit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $id_produit = intval($_POST['id_produit']); // intval() pour sécuriser l'ID
    try {
        $del = $pdo->prepare("DELETE FROM produits WHERE id_produit = ?");
        $del->execute([$id_produit]);
        $success_message = 'Produit supprimé avec succès.';
    } catch (PDOException $e) {
        // La suppression peut échouer si le produit est lié à des commandes
        // (contrainte de clé étrangère dans la BDD → intégrité référentielle)
        $error_message = 'Impossible de supprimer ce produit (il est peut-être lié à des commandes).';
    }
}

// ============================================================
// SECTION 4 : MISE À JOUR DU STATUT D'UNE COMMANDE
// ============================================================
// Déclenchée depuis le formulaire dans l'onglet "Commandes"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $id_commande = intval($_POST['id_commande']);
    $statut      = $_POST['statut'] ?? '';
    
    // SÉCURITÉ : whitelist des statuts autorisés
    // On n'accepte que ces valeurs exactes pour éviter d'injecter n'importe quoi en BDD
    $allowed = ['en_attente', 'confirmee', 'expediee', 'livree', 'annulee'];
    if (in_array($statut, $allowed)) {
        $upd = $pdo->prepare("UPDATE commandes SET statut=? WHERE id_commande=?");
        $upd->execute([$statut, $id_commande]);
        $success_message = 'Statut de la commande mis à jour.';
        $active_tab = 'commandes'; // Reste sur l'onglet commandes après l'action
    }
}

// ============================================================
// SECTION 5 : CHARGEMENT DES DONNÉES POUR L'AFFICHAGE
// ============================================================
// Ces requêtes sont exécutées à chaque chargement de la page
// pour alimenter les onglets avec des données fraîches

// Tous les produits avec leur catégorie (LEFT JOIN pour garder les produits sans catégorie)
// Triés du plus récent au plus ancien
$produits   = $pdo->query("SELECT p.*, c.nom_categorie as categorie_nom FROM produits p LEFT JOIN categories c ON p.id_categorie = c.id_categorie ORDER BY p.date_ajout DESC")->fetchAll();

// Toutes les catégories (pour le <select> du formulaire d'ajout)
$categories = $pdo->query("SELECT * FROM categories ORDER BY nom_categorie")->fetchAll();

// Les 50 dernières commandes avec les infos du client (JOIN sur utilisateurs)
$commandes  = $pdo->query("SELECT c.*, u.prenom, u.nom, u.email FROM commandes c JOIN utilisateurs u ON c.id_utilisateur = u.id_utilisateur ORDER BY c.date_commande DESC LIMIT 50")->fetchAll();

// --- STATISTIQUES DU TABLEAU DE BORD ---
$total_produits  = count($produits);  // Nb de produits (déjà chargés, pas besoin d'une requête COUNT)
$total_commandes = count($commandes); // Nb de commandes affichées

// Chiffre d'affaires total : SUM du montant de toutes les commandes NON annulées
// ?? 0 : si aucune commande, SUM retourne NULL → on le remplace par 0
$ca_total = $pdo->query("SELECT SUM(montant) as total FROM commandes WHERE statut != 'annulee'")->fetch()['total'] ?? 0;

// Nombre total de clients (utilisateurs avec role = 'client', pas les admins)
$total_clients = $pdo->query("SELECT COUNT(*) as n FROM utilisateurs WHERE role = 'client'")->fetch()['n'];

$pageTitle = "Admin - Lumoura";
include '../includes/header.php'; // Inclusion du header HTML commun
?>

<div style="padding-top: 120px; background: linear-gradient(135deg, #1a1008 0%, #3a2c1f 100%); min-height: 100vh;">
    <div style="max-width: 1300px; margin: 0 auto; padding: 40px 20px;">

        <!-- ===== EN-TÊTE DU PANNEAU ADMIN ===== -->
        <!-- Affiche le nom de l'admin connecté (récupéré depuis la BDD) et un lien retour -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 40px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="color: #d4a017; font-size: 2em; margin: 0 0 5px 0;">
                    <i class="fas fa-cog" style="margin-right: 12px;"></i>Panneau Admin
                </h1>
                <!-- htmlspecialchars() protège contre les attaques XSS (injection de code HTML/JS) -->
                <p style="color: #aaa; margin: 0;">Bienvenue, <?php echo htmlspecialchars($admin['prenom']); ?> — Lumoura Back-Office</p>
            </div>
            <a href="profil.php" style="background: rgba(255,255,255,0.1); color: #d4a017; padding: 10px 20px; border-radius: 20px; text-decoration: none; font-weight: bold;">
                <i class="fas fa-arrow-left" style="margin-right: 8px;"></i>Retour au profil
            </a>
        </div>

        <!-- ===== CARTES DE STATISTIQUES ===== -->
        <!-- CSS Grid responsive : auto-fit crée autant de colonnes que possible (min 200px chacune) -->
        <!-- Les 4 stats sont définies dans un tableau PHP puis affichées en boucle -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px;">
            <?php
            // Tableau associatif des 4 statistiques à afficher
            // Chaque entrée contient : icône FontAwesome, valeur calculée, libellé, couleur
            $stats = [
                ['icon' => 'fa-spray-can', 'value' => $total_produits,                                   'label' => 'Produits',  'color' => '#d4a017'],
                ['icon' => 'fa-box',        'value' => $total_commandes,                                  'label' => 'Commandes', 'color' => '#2a9d8f'],
                ['icon' => 'fa-users',      'value' => $total_clients,                                    'label' => 'Clients',   'color' => '#e76f51'],
                ['icon' => 'fa-euro-sign',  'value' => number_format($ca_total, 0, ',', ' ') . ' €',     'label' => 'CA Total',  'color' => '#8ecae6'],
                // number_format($ca_total, 0, ',', ' ') : formate le CA avec séparateur espace (ex: 12 345 €)
            ];
            foreach ($stats as $st): ?>
            <div style="background: rgba(255,255,255,0.07); border-radius: 16px; padding: 25px; text-align: center; border: 1px solid rgba(212,160,23,0.2);">
                <i class="fas <?php echo $st['icon']; ?>" style="font-size: 2em; color: <?php echo $st['color']; ?>; margin-bottom: 12px; display: block;"></i>
                <div style="font-size: 1.8em; font-weight: bold; color: white; margin-bottom: 5px;"><?php echo $st['value']; ?></div>
                <div style="color: #aaa; font-size: 0.9em;"><?php echo $st['label']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ===== MESSAGES DE RETOUR (SUCCÈS / ERREUR) ===== -->
        <!-- Affichés uniquement si les variables sont non vides après une action POST -->
        <?php if ($success_message): ?>
            <div style="padding: 15px 20px; margin-bottom: 25px; background: rgba(40,167,69,0.2); border: 1px solid #28a745; border-radius: 10px; color: #90ee90; text-align: center;">
                <i class="fas fa-check-circle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div style="padding: 15px 20px; margin-bottom: 25px; background: rgba(220,53,69,0.2); border: 1px solid #dc3545; border-radius: 10px; color: #ffaaaa; text-align: center;">
                <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- ===== SYSTÈME D'ONGLETS ===== -->
        <!-- Navigation par URL : ?tab=produits, ?tab=ajouter, ?tab=commandes -->
        <!-- L'onglet actif ($active_tab) est mis en surbrillance avec un fond doré -->
        <!-- Les onglets inactifs ont un fond semi-transparent -->
        <div style="display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap;">
            <?php
            // Tableau des onglets disponibles : clé = valeur GET, valeur = icône + libellé
            $tabs = [
                'produits'  => ['icon' => 'fa-spray-can',   'label' => 'Produits'],
                'ajouter'   => ['icon' => 'fa-plus-circle', 'label' => 'Ajouter un produit'],
                'commandes' => ['icon' => 'fa-box',         'label' => 'Commandes'],
            ];
            foreach ($tabs as $key => $tab):
                $is_active = ($active_tab === $key); // true si cet onglet est l'onglet actuel
            ?>
            <!-- Lien onglet : style conditionnel avec ternaire PHP selon $is_active -->
            <a href="?tab=<?php echo $key; ?>" style="
                padding: 12px 22px; border-radius: 30px; text-decoration: none; font-weight: bold; font-size: 0.95em; transition: all 0.3s;
                <?php echo $is_active
                    ? 'background: #d4a017; color: white; box-shadow: 0 4px 15px rgba(212,160,23,0.4);' // Actif : doré
                    : 'background: rgba(255,255,255,0.08); color: #ccc;'; // Inactif : gris transparent
                ?>
            ">
                <i class="fas <?php echo $tab['icon']; ?>" style="margin-right: 8px;"></i><?php echo $tab['label']; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- ===== ONGLET 1 : LISTE DES PRODUITS ===== -->
        <!-- Affiché uniquement si $active_tab = 'produits' -->
        <!-- Tableau HTML de tous les produits avec bouton de suppression -->
        <?php if ($active_tab === 'produits'): ?>
        <div style="background: rgba(255,255,255,0.05); border-radius: 16px; padding: 30px; border: 1px solid rgba(212,160,23,0.15);">
            <h2 style="color: #d4a017; margin: 0 0 25px 0; font-size: 1.4em;">
                <i class="fas fa-spray-can" style="margin-right: 10px;"></i>Liste des produits (<?php echo $total_produits; ?>)
            </h2>
            <!-- overflow-x: auto → le tableau devient scrollable horizontalement sur petits écrans -->
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
                        <!-- Ligne surbrillance dorée au survol (hover) via onmouseover/onmouseout inline -->
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.07);" onmouseover="this.style.background='rgba(212,160,23,0.07)'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 10px;">
                                <!-- onerror : si l'image ne se charge pas (URL cassée), affiche une image par défaut -->
                                <img src="<?php echo htmlspecialchars($p['image_url'] ?? ''); ?>"
                                     onerror="this.src='./assets/image/default.jpg'"
                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                            </td>
                            <td style="padding: 10px; color: white; font-weight: bold;"><?php echo htmlspecialchars($p['nom']); ?></td>
                            <!-- number_format : formate le prix (ex: 89,90 €) -->
                            <td style="padding: 10px; color: #d4a017; font-weight: bold;"><?php echo number_format($p['prix'], 2, ',', ' '); ?> €</td>
                            <td style="padding: 10px;">
                                <!-- Stock en rouge si ≤ 5 (alerte stock bas), sinon en vert -->
                                <span style="color: <?php echo $p['stock'] <= 5 ? '#ff6b6b' : '#90ee90'; ?>; font-weight: bold;">
                                    <?php echo $p['stock']; ?>
                                </span>
                            </td>
                            <td style="padding: 10px; color: #ccc;"><?php echo htmlspecialchars($p['genre'] ?? '-'); ?></td>
                            <td style="padding: 10px; color: #ccc;"><?php echo htmlspecialchars($p['categorie_nom'] ?? '-'); ?></td>
                            <td style="padding: 10px;">
                                <!-- Formulaire de suppression avec confirmation JavaScript -->
                                <!-- onsubmit="return confirm(...)" : ouvre une boîte de dialogue native du navigateur -->
                                <!-- Si l'utilisateur clique "Annuler", confirm() retourne false et le formulaire n'est PAS soumis -->
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

        <!-- ===== ONGLET 2 : FORMULAIRE D'AJOUT D'UN PRODUIT ===== -->
        <!-- elseif : affiché seulement si l'onglet actif est 'ajouter' -->
        <!-- Le formulaire POST soumet vers cette même page (action implicite = page courante) -->
        <?php elseif ($active_tab === 'ajouter'): ?>
        <div style="background: rgba(255,255,255,0.05); border-radius: 16px; padding: 35px; border: 1px solid rgba(212,160,23,0.15); max-width: 750px;">
            <h2 style="color: #d4a017; margin: 0 0 25px 0; font-size: 1.4em;">
                <i class="fas fa-plus-circle" style="margin-right: 10px;"></i>Ajouter un nouveau produit
            </h2>
            <!-- Formulaire POST sans action= → se soumet à lui-même (admin.php) -->
            <form method="POST">
                <!-- Grille 2 colonnes pour les champs courts -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">

                    <div>
                        <!-- required : validation HTML5 native (empêche la soumission si vide) -->
                        <!-- * rouge indique un champ obligatoire -->
                        <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Nom du parfum <span style="color: #ff6b6b;">*</span></label>
                        <input type="text" name="nom" required placeholder="Ex: Rose Éternelle"
                            style="width: 100%; padding: 13px; background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;"
                            <!-- onfocus/onblur : la bordure devient dorée au focus, revient à la normale en quittant -->
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='rgba(212,160,23,0.3)'">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Marque</label>
                        <!-- value="Lumoura" : valeur pré-remplie par défaut -->
                        <input type="text" name="marque" value="Lumoura" placeholder="Lumoura"
                            style="width: 100%; padding: 13px; background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='rgba(212,160,23,0.3)'">
                    </div>

                    <div>
                        <!-- step="0.01" : permet les centimes (ex: 89.99) -->
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
                        <!-- <select> : liste déroulante pour le genre (valeurs fixes) -->
                        <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Genre</label>
                        <select name="genre" style="width: 100%; padding: 13px; background: #2a1f12; border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;">
                            <option value="Femme">Femme</option>
                            <option value="Homme">Homme</option>
                            <option value="Unisexe">Unisexe</option>
                        </select>
                    </div>

                    <div>
                        <!-- <select> : liste déroulante pour le type de concentration du parfum -->
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
                        <!-- min="1" : empêche une valeur négative ou zéro -->
                        <input type="number" name="volume_ml" min="1" value="100"
                            style="width: 100%; padding: 13px; background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='rgba(212,160,23,0.3)'">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Promotion (%)</label>
                        <!-- max="100" : une réduction ne peut pas dépasser 100% -->
                        <input type="number" name="promotion_pourcentage" min="0" max="100" value="0" placeholder="0"
                            style="width: 100%; padding: 13px; background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='rgba(212,160,23,0.3)'">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Catégorie</label>
                        <!-- Les options sont générées dynamiquement depuis $categories (récupéré en BDD) -->
                        <!-- value="" avec option "Aucune" → enverra 0 → PHP met NULL en BDD -->
                        <select name="id_categorie" style="width: 100%; padding: 13px; background: #2a1f12; border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;">
                            <option value="">-- Aucune --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id_categorie']; ?>"><?php echo htmlspecialchars($cat['nom']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">URL de l'image</label>
                        <!-- Chemin relatif vers l'image du produit -->
                        <input type="text" name="image_url" placeholder="./assets/image/mon-parfum.jpg"
                            style="width: 100%; padding: 13px; background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='rgba(212,160,23,0.3)'">
                    </div>
                </div>

                <!-- Champs sur toute la largeur -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Description courte</label>
                    <!-- Texte bref affiché dans les cartes produits et les previews -->
                    <input type="text" name="description_courte" placeholder="Parfum floral romantique..."
                        style="width: 100%; padding: 13px; background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box;"
                        onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='rgba(212,160,23,0.3)'">
                </div>

                <div style="margin-bottom: 25px;">
                    <label style="display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;">Description complète</label>
                    <!-- <textarea> : zone de texte multiligne pour la description longue -->
                    <!-- rows="4" : hauteur initiale (4 lignes) ; resize: vertical → l'utilisateur peut agrandir verticalement -->
                    <textarea name="description" rows="4" placeholder="Description détaillée du parfum, ses notes olfactives..."
                        style="width: 100%; padding: 13px; background: rgba(255,255,255,0.08); border: 1px solid rgba(212,160,23,0.3); border-radius: 8px; color: white; font-size: 1em; box-sizing: border-box; resize: vertical;"
                        onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='rgba(212,160,23,0.3)'"></textarea>
                </div>

                <!-- Bouton de soumission : name="add_product" est détecté côté PHP avec isset($_POST['add_product']) -->
                <button type="submit" name="add_product" style="background: #d4a017; color: white; border: none; padding: 16px 40px; border-radius: 10px; font-weight: bold; font-size: 1.1em; cursor: pointer; transition: background 0.3s;"
                    onmouseover="this.style.background='#c29b15'" onmouseout="this.style.background='#d4a017'">
                    <i class="fas fa-plus" style="margin-right: 10px;"></i>Ajouter le produit
                </button>
            </form>
        </div>

        <!-- ===== ONGLET 3 : LISTE ET GESTION DES COMMANDES ===== -->
        <!-- Affiché si $active_tab = 'commandes' -->
        <!-- Tableau des 50 dernières commandes avec possibilité de changer leur statut -->
        <?php elseif ($active_tab === 'commandes'): ?>
        <div style="background: rgba(255,255,255,0.05); border-radius: 16px; padding: 30px; border: 1px solid rgba(212,160,23,0.15);">
            <h2 style="color: #d4a017; margin: 0 0 25px 0; font-size: 1.4em;">
                <i class="fas fa-box" style="margin-right: 10px;"></i>Toutes les commandes (<?php echo count($commandes); ?>)
            </h2>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
                    <thead>
                        <tr style="border-bottom: 2px solid #d4a017;">
                            <th style="padding: 12px; text-align: left; color: #d4a017;">N° Commande</th>
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Client</th>
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Date</th>
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Montant</th>
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Statut</th>
                            <th style="padding: 12px; text-align: left; color: #d4a017;">Modifier</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Tableau de correspondance statut → couleur de fond + libellé français
                        // Utilisé pour coloriser le badge de statut de chaque commande
                        $statut_colors = [
                            'en_attente' => ['bg' => '#856404', 'label' => 'En attente'], // Jaune/orange
                            'confirmee'  => ['bg' => '#155724', 'label' => 'Confirmée'],  // Vert foncé
                            'expediee'   => ['bg' => '#004085', 'label' => 'Expédiée'],   // Bleu
                            'livree'     => ['bg' => '#155724', 'label' => 'Livrée'],     // Vert foncé
                            'annulee'    => ['bg' => '#721c24', 'label' => 'Annulée'],    // Rouge foncé
                        ];
                        foreach ($commandes as $cmd):
                            // Récupère la couleur/label du statut, ou une valeur par défaut si statut inconnu
                            $s = $statut_colors[$cmd['statut']] ?? ['bg' => '#333', 'label' => $cmd['statut']];
                        ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.07);" onmouseover="this.style.background='rgba(212,160,23,0.07)'" onmouseout="this.style.background='transparent'">
                            <!-- ?? '#' . $cmd['id_commande'] : si pas de numéro de commande, affiche #ID -->
                            <td style="padding: 10px; color: #d4a017; font-weight: bold;"><?php echo htmlspecialchars($cmd['numero_commande'] ?? '#' . $cmd['id_commande']); ?></td>
                            <!-- Prénom + Nom sur une ligne, email en dessous en gris plus petit -->
                            <td style="padding: 10px; color: white;"><?php echo htmlspecialchars($cmd['prenom'] . ' ' . $cmd['nom']); ?><br><span style="color: #888; font-size: 0.85em;"><?php echo htmlspecialchars($cmd['email']); ?></span></td>
                            <!-- date() + strtotime() : convertit la date BDD en format lisible (ex: 19/02/2026 14:30) -->
                            <td style="padding: 10px; color: #ccc;"><?php echo date('d/m/Y H:i', strtotime($cmd['date_commande'])); ?></td>
                            <td style="padding: 10px; color: #d4a017; font-weight: bold;"><?php echo number_format($cmd['montant'], 2, ',', ' '); ?> €</td>
                            <td style="padding: 10px;">
                                <!-- Badge coloré selon le statut (couleur récupérée dans $statut_colors) -->
                                <span style="background: <?php echo $s['bg']; ?>; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.85em; font-weight: bold;">
                                    <?php echo $s['label']; ?>
                                </span>
                            </td>
                            <td style="padding: 10px;">
                                <!-- Formulaire de changement de statut : un formulaire par ligne de commande -->
                                <!-- L'id_commande est passé en hidden pour identifier quelle commande modifier -->
                                <!-- selected : pré-sélectionne le statut actuel de la commande dans le <select> -->
                                <form method="POST" style="display: flex; gap: 6px; align-items: center;">
                                    <input type="hidden" name="id_commande" value="<?php echo $cmd['id_commande']; ?>">
                                    <select name="statut" style="background: #2a1f12; border: 1px solid rgba(212,160,23,0.3); color: white; padding: 6px 10px; border-radius: 6px; font-size: 0.85em;">
                                        <option value="en_attente"  <?php echo $cmd['statut']==='en_attente'  ? 'selected':'' ?>>En attente</option>
                                        <option value="confirmee"   <?php echo $cmd['statut']==='confirmee'   ? 'selected':'' ?>>Confirmée</option>
                                        <option value="expediee"    <?php echo $cmd['statut']==='expediee'    ? 'selected':'' ?>>Expédiée</option>
                                        <option value="livree"      <?php echo $cmd['statut']==='livree'      ? 'selected':'' ?>>Livrée</option>
                                        <option value="annulee"     <?php echo $cmd['statut']==='annulee'     ? 'selected':'' ?>>Annulée</option>
                                    </select>
                                    <!-- Bouton de validation : name="update_order_status" détecté par le PHP en haut -->
                                    <button type="submit" name="update_order_status" style="background: #2a9d8f; color: white; border: none; padding: 7px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85em;">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; // Fin du système d'onglets if/elseif/elseif ?>

    </div>
</div>

<?php include '../includes/footer.php'; ?>