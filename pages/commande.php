<?php
// ============================================================
// FICHIER : commande.php
// RÔLE    : Page de finalisation de commande
//           → Récupère le panier de l'utilisateur
//           → Récupère l'adresse principale depuis la table "adresses"
//           → Permet de choisir parmi ses adresses enregistrées
//           → Affiche le formulaire d'adresse + paiement
//           → Crée la commande en BDD via une TRANSACTION SQL
// ============================================================

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Vérification de connexion obligatoire pour passer commande
if (!isLoggedIn()) {
    $_SESSION['login_redirect'] = 'Veuillez vous connecter pour passer commande.';
    header('Location: connexion.php');
    exit();
}

$user_id         = $_SESSION['user_id'];
$success_message = '';
$error_message   = '';

// ============================================================
// SECTION 1 : RÉCUPÉRER LES INFOS DE L'UTILISATEUR
// ============================================================
try {
    $user_stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
} catch (PDOException $e) {
    $error_message = 'Erreur lors du chargement des informations utilisateur.';
    $user = null;
}

// ============================================================
// SECTION 2 : RÉCUPÉRER TOUTES LES ADRESSES DE L'UTILISATEUR
// ============================================================
// On récupère TOUTES les adresses triées :
//   - d'abord l'adresse principale (est_principale = 1)
//   - puis les autres par date de création décroissante
try {
    $addr_stmt = $pdo->prepare("
        SELECT * FROM adresses 
        WHERE id_utilisateur = ? 
        ORDER BY est_principale DESC, date_creation DESC
    ");
    $addr_stmt->execute([$user_id]);
    $adresses = $addr_stmt->fetchAll();

    // On cherche l'adresse principale pour pré-remplir le formulaire
    // Si pas d'adresse principale → on prend la première disponible
    $adresse_principale = null;
    foreach ($adresses as $addr) {
        if ($addr['est_principale'] == 1) {
            $adresse_principale = $addr;
            break;
        }
    }
    // Si aucune marquée principale → fallback sur la première
    if (!$adresse_principale && !empty($adresses)) {
        $adresse_principale = $adresses[0];
    }

} catch (PDOException $e) {
    $adresses = [];
    $adresse_principale = null;
}

// ============================================================
// SECTION 3 : RÉCUPÉRER LE PANIER ET CALCULER LES TOTAUX
// ============================================================
try {
    $cart_query = "
        SELECT 
            p.*,
            pan.quantite,
            pan.id_panier
        FROM panier pan
        JOIN produits p ON pan.id_produit = p.id_produit
        WHERE pan.id_utilisateur = ?
        ORDER BY pan.date_ajout DESC
    ";
    $cart_stmt = $pdo->prepare($cart_query);
    $cart_stmt->execute([$user_id]);
    $cart_items = $cart_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcul du sous-total (avec réductions) et du nombre total d'articles
    $subtotal    = 0;
    $total_items = 0;

    foreach ($cart_items as $item) {
        $price       = $item['prix'];
        $discount    = $item['promotion_pourcentage'];
        $final_price = $discount > 0 ? $price * (1 - $discount / 100) : $price;
        $subtotal    += $final_price * $item['quantite'];
        $total_items += $item['quantite'];
    }

    // Frais de port : GRATUIT si commande >= 150€, sinon 5.90€
    $shipping_cost = ($subtotal >= 150 || $subtotal == 0) ? 0 : 5.90;
    $total         = $subtotal + $shipping_cost;

} catch (PDOException $e) {
    $error_message = 'Erreur lors du chargement du panier.';
    $cart_items    = [];
    $subtotal      = 0;
    $total         = 0;
}

// Si le panier est vide → retour au panier
if (empty($cart_items)) {
    header('Location: panier.php');
    exit();
}

// ============================================================
// SECTION 4 : TRAITEMENT DE LA COMMANDE (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    // Récupération et nettoyage des données du formulaire
    $prenom        = trim($_POST['prenom']        ?? '');
    $nom           = trim($_POST['nom']           ?? '');
    $email         = trim($_POST['email']         ?? '');
    $telephone     = trim($_POST['telephone']     ?? '');
    $adresse       = trim($_POST['adresse']       ?? '');
    $code_postal   = trim($_POST['code_postal']   ?? '');
    $ville         = trim($_POST['ville']         ?? '');
    $pays          = trim($_POST['pays']          ?? 'France');
    $mode_paiement = trim($_POST['mode_paiement'] ?? 'carte');
    $notes         = trim($_POST['notes']         ?? '');

    // Validation : tous les champs sauf notes sont obligatoires
    if (empty($prenom) || empty($nom) || empty($email) || empty($telephone) ||
        empty($adresse) || empty($code_postal) || empty($ville)) {
        $error_message = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        try {
            // TRANSACTION SQL — regroupe toutes les opérations en une seule :
            // Si une échoue → rollBack() → rien n'est sauvegardé
            // Si toutes réussissent → commit() → tout est sauvegardé
            $pdo->beginTransaction();

            // ÉTAPE A : Générer un numéro de commande unique lisible
            // Exemple : "CMD-2026-A3B7F9C2"
            $numero_commande = 'CMD-' . date('Y') . '-' . strtoupper(substr(uniqid(), -8));

            // ÉTAPE B : Concaténer l'adresse complète en une chaîne
            $adresse_complete = "$adresse, $code_postal $ville, $pays";

            // ÉTAPE C : Insérer la commande principale dans la table 'commandes'
            $order_query = "
                INSERT INTO commandes (
                    numero_commande,
                    id_utilisateur,
                    statut,
                    montant,
                    frais_livraison,
                    mode_paiement,
                    adresse_livraison,
                    notes
                ) VALUES (?, ?, 'en_attente', ?, ?, ?, ?, ?)
            ";
            $order_stmt = $pdo->prepare($order_query);
            $order_stmt->execute([
                $numero_commande,
                $user_id,
                $total,
                $shipping_cost,
                $mode_paiement,
                $adresse_complete,
                $notes
            ]);

            // lastInsertId() : récupère l'ID de la commande qu'on vient d'insérer
            $order_id = $pdo->lastInsertId();

            // ÉTAPE D : Insérer chaque article commandé dans details_commande
            // Le prix est figé au moment de la commande (ne changera pas si le prix du produit change)
            foreach ($cart_items as $item) {
                $price       = $item['prix'];
                $discount    = $item['promotion_pourcentage'];
                $final_price = $discount > 0 ? $price * (1 - $discount / 100) : $price;

                $detail_stmt = $pdo->prepare("
                    INSERT INTO details_commande (
                        id_commande, id_produit, quantite, prix_unitaire
                    ) VALUES (?, ?, ?, ?)
                ");
                $detail_stmt->execute([$order_id, $item['id_produit'], $item['quantite'], $final_price]);

                // ÉTAPE E : Décrémenter le stock du produit commandé
                $update_stmt = $pdo->prepare("UPDATE produits SET stock = stock - ? WHERE id_produit = ?");
                $update_stmt->execute([$item['quantite'], $item['id_produit']]);
            }

            // ÉTAPE F : Vider le panier de l'utilisateur
            $clear_stmt = $pdo->prepare("DELETE FROM panier WHERE id_utilisateur = ?");
            $clear_stmt->execute([$user_id]);

            // COMMIT : valide toutes les opérations
            $pdo->commit();

            $_SESSION['order_success'] = "Votre commande #$order_id a été passée avec succès !";
            header("Location: confirmation.php?order_id=$order_id");
            exit();

        } catch (PDOException $e) {
            // Si erreur → ROLLBACK : annule tout, la BDD reste dans son état initial
            $pdo->rollBack();
            $error_message = 'Erreur lors de la création de la commande : ' . $e->getMessage();
        }
    }
}

$pageTitle = "Finaliser ma commande - Lumoura";
include '../includes/header.php';
?>

<div style="padding-top: 120px; background: linear-gradient(135deg, #f5f3ef 0%, #e8e4dc 100%); min-height: 100vh;">
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 40px 20px;">

        <!-- En-tête -->
        <div style="text-align: center; margin-bottom: 50px;">
            <h1 style="font-size: 2.5em; margin-bottom: 15px; color: #3a2c1f; font-weight: bold;">
                <i class="fas fa-lock" style="color: #d4a017; margin-right: 15px;"></i>
                Finaliser ma commande
            </h1>
            <p style="font-size: 1.1em; color: #666;">
                Plus qu'une étape avant de recevoir vos parfums d'exception
            </p>
        </div>

        <!-- Message d'erreur -->
        <?php if ($error_message): ?>
            <div style="padding: 20px; margin-bottom: 30px; background: #ffe6e6; border: 2px solid #ffb3b3; border-radius: 12px; color: #cc0000; text-align: center;">
                <i class="fas fa-exclamation-circle" style="margin-right: 10px;"></i>
                <strong><?php echo htmlspecialchars($error_message); ?></strong>
            </div>
        <?php endif; ?>

        <!-- Layout principal : formulaire (gauche) + résumé (droite) -->
        <form method="POST" action="commande.php" style="display: grid; grid-template-columns: 1fr 400px; gap: 40px;">

            <div>
                <!-- ===== BLOC 1 : SÉLECTION / SAISIE D'ADRESSE ===== -->
                <div style="background: white; border-radius: 16px; padding: 35px; box-shadow: 0 8px 25px rgba(0,0,0,0.1); margin-bottom: 30px;">
                    <h2 style="font-size: 1.8em; margin: 0 0 25px 0; color: #3a2c1f; border-bottom: 2px solid #d4a017; padding-bottom: 15px;">
                        <i class="fas fa-shipping-fast" style="color: #d4a017; margin-right: 10px;"></i>
                        Informations de livraison
                    </h2>

                    <?php if (!empty($adresses)): ?>
                    <!-- ===== SOUS-BLOC : Choisir une adresse enregistrée ===== -->
                    <!-- S'affiche seulement si l'utilisateur a au moins une adresse en BDD -->
                    <div style="margin-bottom: 25px; padding: 20px; background: #f8f5f0; border-radius: 12px; border: 2px solid #e8e0d0;">
                        <p style="font-weight: bold; color: #3a2c1f; margin: 0 0 15px 0;">
                            <i class="fas fa-map-marker-alt" style="color: #d4a017; margin-right: 8px;"></i>
                            Mes adresses enregistrées
                        </p>

                        <?php foreach ($adresses as $addr): ?>
                        <!-- Chaque adresse est un bouton radio cliquable -->
                        <!-- Au clic, le JS pré-remplit automatiquement les champs du formulaire -->
                        <label style="
                            display: flex;
                            align-items: flex-start;
                            gap: 12px;
                            padding: 15px;
                            border: 2px solid <?php echo ($adresse_principale && $addr['id_adresse'] == $adresse_principale['id_adresse']) ? '#d4a017' : '#e0e0e0'; ?>;
                            border-radius: 10px;
                            margin-bottom: 10px;
                            cursor: pointer;
                            background: <?php echo ($adresse_principale && $addr['id_adresse'] == $adresse_principale['id_adresse']) ? '#fffbf0' : 'white'; ?>;
                            transition: all 0.3s;
                        " onmouseover="this.style.borderColor='#d4a017'" onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='#e0e0e0'}">

                            <!-- Radio bouton avec data-attributes pour le JS -->
                            <input
                                type="radio"
                                name="adresse_selectionnee"
                                value="<?php echo $addr['id_adresse']; ?>"
                                <?php echo ($adresse_principale && $addr['id_adresse'] == $adresse_principale['id_adresse']) ? 'checked' : ''; ?>
                                data-prenom="<?php echo htmlspecialchars($addr['prenom']); ?>"
                                data-nom="<?php echo htmlspecialchars($addr['nom']); ?>"
                                data-telephone="<?php echo htmlspecialchars($addr['telephone']); ?>"
                                data-adresse="<?php echo htmlspecialchars($addr['adresse']); ?>"
                                data-code_postal="<?php echo htmlspecialchars($addr['code_postal']); ?>"
                                data-ville="<?php echo htmlspecialchars($addr['ville']); ?>"
                                data-pays="<?php echo htmlspecialchars($addr['pays']); ?>"
                                style="margin-top: 3px; cursor: pointer; width: 18px; height: 18px;"
                                onchange="remplirAdresse(this)"
                            >

                            <div style="flex: 1;">
                                <div style="font-weight: bold; color: #3a2c1f; margin-bottom: 4px;">
                                    <?php echo htmlspecialchars($addr['prenom'] . ' ' . $addr['nom']); ?>
                                    <?php if ($addr['est_principale']): ?>
                                        <!-- Badge "Principale" affiché si c'est l'adresse principale -->
                                        <span style="background: #d4a017; color: white; font-size: 0.7em; padding: 2px 8px; border-radius: 10px; margin-left: 8px; font-weight: bold;">
                                            Principale
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div style="color: #555; font-size: 0.9em;">
                                    <?php echo htmlspecialchars($addr['adresse']); ?><br>
                                    <?php echo htmlspecialchars($addr['code_postal'] . ' ' . $addr['ville'] . ', ' . $addr['pays']); ?><br>
                                    <?php if ($addr['telephone']): ?>
                                        <span style="color: #888;"><i class="fas fa-phone" style="margin-right: 4px;"></i><?php echo htmlspecialchars($addr['telephone']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </label>
                        <?php endforeach; ?>

                        <!-- Lien vers le profil pour gérer les adresses -->
                        <a href="profil.php?tab=adresses" style="display: inline-block; margin-top: 5px; font-size: 0.9em; color: #d4a017; text-decoration: none;">
                            <i class="fas fa-plus-circle" style="margin-right: 5px;"></i>Gérer mes adresses
                        </a>
                    </div>
                    <?php else: ?>
                    <!-- Message si l'utilisateur n'a encore aucune adresse enregistrée -->
                    <div style="padding: 15px; background: #fff3cd; border-radius: 10px; border: 1px solid #ffc107; margin-bottom: 20px; font-size: 0.9em; color: #856404;">
                        <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
                        Vous n'avez pas encore d'adresse enregistrée. Remplissez le formulaire ci-dessous.
                        <a href="profil.php?tab=adresses" style="color: #d4a017; font-weight: bold; margin-left: 5px;">Sauvegarder une adresse</a>
                    </div>
                    <?php endif; ?>

                    <!-- ===== FORMULAIRE D'ADRESSE (pré-rempli via JS ou modifiable) ===== -->
                    <!-- Ces champs sont pré-remplis par JS quand on sélectionne une adresse radio -->
                    <!-- L'utilisateur peut aussi les modifier librement -->

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">
                                Prénom <span style="color: #c0392b;">*</span>
                            </label>
                            <input
                                type="text" id="champ_prenom" name="prenom"
                                value="<?php echo htmlspecialchars($adresse_principale['prenom'] ?? $user['prenom'] ?? ''); ?>"
                                required
                                style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box;"
                                onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='#e0e0e0'"
                            >
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">
                                Nom <span style="color: #c0392b;">*</span>
                            </label>
                            <input
                                type="text" id="champ_nom" name="nom"
                                value="<?php echo htmlspecialchars($adresse_principale['nom'] ?? $user['nom'] ?? ''); ?>"
                                required
                                style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box;"
                                onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='#e0e0e0'"
                            >
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">
                                Email <span style="color: #c0392b;">*</span>
                            </label>
                            <input
                                type="email" id="champ_email" name="email"
                                value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                required
                                style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box;"
                                onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='#e0e0e0'"
                            >
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">
                                Téléphone <span style="color: #c0392b;">*</span>
                            </label>
                            <input
                                type="tel" id="champ_telephone" name="telephone"
                                value="<?php echo htmlspecialchars($adresse_principale['telephone'] ?? $user['telephone'] ?? ''); ?>"
                                required placeholder="06 12 34 56 78"
                                style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box;"
                                onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='#e0e0e0'"
                            >
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">
                            Adresse complète <span style="color: #c0392b;">*</span>
                        </label>
                        <input
                            type="text" id="champ_adresse" name="adresse"
                            value="<?php echo htmlspecialchars($adresse_principale['adresse'] ?? ''); ?>"
                            required placeholder="12 Rue de la République"
                            style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='#e0e0e0'"
                        >
                    </div>

                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">
                                Code postal <span style="color: #c0392b;">*</span>
                            </label>
                            <input
                                type="text" id="champ_code_postal" name="code_postal"
                                value="<?php echo htmlspecialchars($adresse_principale['code_postal'] ?? ''); ?>"
                                required placeholder="75000" maxlength="5"
                                style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box;"
                                onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='#e0e0e0'"
                            >
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">
                                Ville <span style="color: #c0392b;">*</span>
                            </label>
                            <input
                                type="text" id="champ_ville" name="ville"
                                value="<?php echo htmlspecialchars($adresse_principale['ville'] ?? ''); ?>"
                                required placeholder="Paris"
                                style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box;"
                                onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='#e0e0e0'"
                            >
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">
                            Pays <span style="color: #c0392b;">*</span>
                        </label>
                        <select
                            id="champ_pays" name="pays" required
                            style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; cursor: pointer; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='#e0e0e0'"
                        >
                            <?php
                            // On génère les options en cochant le bon pays selon l'adresse sélectionnée
                            $pays_list = ['France', 'Belgique', 'Suisse', 'Luxembourg'];
                            $pays_selectionne = $adresse_principale['pays'] ?? 'France';
                            foreach ($pays_list as $p):
                            ?>
                            <option value="<?php echo $p; ?>" <?php echo ($pays_selectionne === $p) ? 'selected' : ''; ?>>
                                <?php echo $p; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">
                            Instructions de livraison (optionnel)
                        </label>
                        <textarea
                            name="notes" rows="3"
                            placeholder="Code d'accès, étage, instructions spéciales..."
                            style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; resize: vertical; box-sizing: border-box;"
                            onfocus="this.style.borderColor='#d4a017'" onblur="this.style.borderColor='#e0e0e0'"
                        ></textarea>
                    </div>
                </div>

                <!-- ===== BLOC 2 : MODE DE PAIEMENT ===== -->
                <div style="background: white; border-radius: 16px; padding: 35px; box-shadow: 0 8px 25px rgba(0,0,0,0.1);">
                    <h2 style="font-size: 1.8em; margin: 0 0 25px 0; color: #3a2c1f; border-bottom: 2px solid #d4a017; padding-bottom: 15px;">
                        <i class="fas fa-credit-card" style="color: #d4a017; margin-right: 10px;"></i>
                        Mode de paiement
                    </h2>

                    <div style="display: grid; gap: 15px;">
                        <!-- Carte bancaire -->
                        <label style="display: flex; align-items: center; padding: 20px; border: 2px solid #d4a017; border-radius: 10px; cursor: pointer; background: #f8f5f0; transition: all 0.3s;"
                            onmouseover="this.style.borderColor='#d4a017'; this.style.background='#f8f5f0'"
                            onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='#e0e0e0'; this.style.background='white'}">
                            <input type="radio" name="mode_paiement" value="carte" checked
                                style="margin-right: 15px; cursor: pointer; width: 20px; height: 20px;"
                                onchange="this.closest('label').style.borderColor='#d4a017'; this.closest('label').style.background='#f8f5f0'">
                            <div style="flex: 1;">
                                <div style="font-weight: bold; font-size: 1.1em; margin-bottom: 5px;">
                                    <i class="fas fa-credit-card" style="color: #d4a017; margin-right: 8px;"></i>Carte bancaire
                                </div>
                                <div style="font-size: 0.9em; color: #777;">Visa, Mastercard, American Express</div>
                            </div>
                            <div style="display: flex; gap: 10px; font-size: 1.8em; color: #555;">
                                <i class="fab fa-cc-visa"></i><i class="fab fa-cc-mastercard"></i>
                            </div>
                        </label>

                        <!-- PayPal -->
                        <label style="display: flex; align-items: center; padding: 20px; border: 2px solid #e0e0e0; border-radius: 10px; cursor: pointer; transition: all 0.3s;"
                            onmouseover="this.style.borderColor='#d4a017'; this.style.background='#f8f5f0'"
                            onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='#e0e0e0'; this.style.background='white'}">
                            <input type="radio" name="mode_paiement" value="paypal"
                                style="margin-right: 15px; cursor: pointer; width: 20px; height: 20px;"
                                onchange="this.closest('label').style.borderColor='#d4a017'; this.closest('label').style.background='#f8f5f0'">
                            <div style="flex: 1;">
                                <div style="font-weight: bold; font-size: 1.1em; margin-bottom: 5px;">
                                    <i class="fab fa-paypal" style="color: #0070ba; margin-right: 8px;"></i>PayPal
                                </div>
                                <div style="font-size: 0.9em; color: #777;">Paiement sécurisé via PayPal</div>
                            </div>
                            <i class="fab fa-cc-paypal" style="font-size: 1.8em; color: #0070ba;"></i>
                        </label>

                        <!-- Apple Pay -->
                        <label style="display: flex; align-items: center; padding: 20px; border: 2px solid #e0e0e0; border-radius: 10px; cursor: pointer; transition: all 0.3s;"
                            onmouseover="this.style.borderColor='#d4a017'; this.style.background='#f8f5f0'"
                            onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='#e0e0e0'; this.style.background='white'}">
                            <input type="radio" name="mode_paiement" value="apple_pay"
                                style="margin-right: 15px; cursor: pointer; width: 20px; height: 20px;"
                                onchange="this.closest('label').style.borderColor='#d4a017'; this.closest('label').style.background='#f8f5f0'">
                            <div style="flex: 1;">
                                <div style="font-weight: bold; font-size: 1.1em; margin-bottom: 5px;">
                                    <i class="fab fa-apple-pay" style="color: #000; margin-right: 8px;"></i>Apple Pay
                                </div>
                                <div style="font-size: 0.9em; color: #777;">Paiement rapide et sécurisé</div>
                            </div>
                            <i class="fab fa-cc-apple-pay" style="font-size: 1.8em; color: #000;"></i>
                        </label>
                    </div>

                    <div style="margin-top: 25px; padding: 20px; background: #f8f5f0; border-radius: 10px; border-left: 4px solid #2a9d8f;">
                        <div style="display: flex; align-items: start; gap: 15px;">
                            <i class="fas fa-shield-alt" style="color: #2a9d8f; font-size: 1.5em;"></i>
                            <div>
                                <div style="font-weight: bold; margin-bottom: 5px; color: #2a9d8f;">Paiement 100% sécurisé</div>
                                <div style="font-size: 0.9em; color: #555;">Toutes vos transactions sont cryptées. Vos données bancaires ne sont jamais stockées sur nos serveurs.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== RÉSUMÉ DE COMMANDE (colonne droite) ===== -->
            <div style="position: sticky; top: 120px; height: fit-content;">
                <div style="background: white; border-radius: 16px; padding: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 25px 0; font-size: 1.5em; color: #3a2c1f; border-bottom: 2px solid #d4a017; padding-bottom: 15px;">
                        <i class="fas fa-shopping-bag" style="color: #d4a017; margin-right: 10px;"></i>
                        Votre commande
                    </h3>

                    <!-- Liste des articles du panier -->
                    <div style="max-height: 300px; overflow-y: auto; margin-bottom: 20px; padding-right: 10px;">
                        <?php foreach ($cart_items as $item):
                            $price       = $item['prix'];
                            $discount    = $item['promotion_pourcentage'];
                            $final_price = $discount > 0 ? $price * (1 - $discount / 100) : $price;
                        ?>
                        <div style="display: flex; gap: 15px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                            <img src="<?php echo htmlspecialchars($item['image_url'] ?? './assets/image/default.jpg'); ?>"
                                alt="<?php echo htmlspecialchars($item['nom']); ?>"
                                style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;"
                                onerror="this.src='./assets/image/default.jpg'">
                            <div style="flex: 1;">
                                <div style="font-weight: bold; font-size: 0.95em; margin-bottom: 5px; color: #3a2c1f;">
                                    <?php echo htmlspecialchars($item['nom']); ?>
                                </div>
                                <div style="font-size: 0.85em; color: #888; margin-bottom: 5px;">Quantité : <?php echo $item['quantite']; ?></div>
                                <div style="font-weight: bold; color: #d4a017;"><?php echo formatPrice($final_price * $item['quantite']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Récapitulatif montants -->
                    <div style="margin-bottom: 25px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 12px; color: #555;">
                            <span>Sous-total</span>
                            <span style="font-weight: bold;"><?php echo formatPrice($subtotal); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 12px; color: #555;">
                            <span>Livraison</span>
                            <span style="font-weight: bold; color: <?php echo $shipping_cost == 0 ? '#2a9d8f' : '#555'; ?>;">
                                <?php echo $shipping_cost == 0 ? 'GRATUIT' : formatPrice($shipping_cost); ?>
                            </span>
                        </div>
                        <?php if ($subtotal < 150 && $subtotal > 0): ?>
                        <div style="background: #e6f7ff; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.85em; color: #0066cc;">
                            <i class="fas fa-info-circle" style="margin-right: 5px;"></i>
                            Ajoutez <?php echo formatPrice(150 - $subtotal); ?> pour la livraison gratuite
                        </div>
                        <?php endif; ?>
                        <div style="border-top: 2px solid #eee; padding-top: 15px; margin-top: 15px;">
                            <div style="display: flex; justify-content: space-between; font-size: 1.4em; font-weight: bold;">
                                <span style="color: #3a2c1f;">Total</span>
                                <span style="color: #c0392b;"><?php echo formatPrice($total); ?></span>
                            </div>
                            <p style="font-size: 0.85em; color: #777; margin-top: 8px; margin-bottom: 0;">TTC, livraison incluse</p>
                        </div>
                    </div>

                    <!-- Bouton de validation -->
                    <button type="submit" name="place_order"
                        style="width: 100%; background: #d4a017; color: white; border: none; padding: 18px; border-radius: 10px; font-weight: bold; font-size: 1.2em; cursor: pointer; margin-bottom: 15px; transition: background 0.3s;"
                        onmouseover="this.style.background='#c29b15'" onmouseout="this.style.background='#d4a017'">
                        <i class="fas fa-lock" style="margin-right: 10px;"></i>
                        Confirmer ma commande
                    </button>

                    <a href="panier.php" style="display: block; text-align: center; color: #666; text-decoration: none; font-size: 0.95em;"
                        onmouseover="this.style.color='#d4a017'" onmouseout="this.style.color='#666'">
                        <i class="fas fa-arrow-left" style="margin-right: 5px;"></i>Retour au panier
                    </a>

                    <!-- Icônes réassurance -->
                    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee;">
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <div style="display: flex; align-items: center; gap: 10px; font-size: 0.9em; color: #555;">
                                <i class="fas fa-truck" style="color: #2a9d8f;"></i><span>Livraison en 24-48h</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px; font-size: 0.9em; color: #555;">
                                <i class="fas fa-undo" style="color: #2a9d8f;"></i><span>Retour gratuit 30 jours</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px; font-size: 0.9em; color: #555;">
                                <i class="fas fa-gift" style="color: #2a9d8f;"></i><span>Échantillons offerts</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ===== JAVASCRIPT : Remplissage automatique du formulaire ===== -->
<script>
/**
 * remplirAdresse(radio)
 * Appelée à chaque fois que l'utilisateur clique sur un radio "adresse enregistrée"
 * Elle lit les data-attributes du radio (data-prenom, data-adresse, etc.)
 * et les injecte dans les champs du formulaire en dessous.
 * Ainsi l'utilisateur n'a pas à re-saisir son adresse manuellement.
 */
function remplirAdresse(radio) {
    // Remplir chaque champ du formulaire avec la valeur du data-attribute correspondant
    document.getElementById('champ_prenom').value      = radio.dataset.prenom;
    document.getElementById('champ_nom').value         = radio.dataset.nom;
    document.getElementById('champ_telephone').value   = radio.dataset.telephone;
    document.getElementById('champ_adresse').value     = radio.dataset.adresse;
    document.getElementById('champ_code_postal').value = radio.dataset.code_postal;
    document.getElementById('champ_ville').value       = radio.dataset.ville;

    // Pour le select "pays" : on cherche l'option dont la valeur correspond
    const selectPays = document.getElementById('champ_pays');
    for (let option of selectPays.options) {
        if (option.value === radio.dataset.pays) {
            option.selected = true;
            break;
        }
    }

    // Mise à jour visuelle des labels radio (bordure dorée = sélectionné)
    document.querySelectorAll('input[name="adresse_selectionnee"]').forEach(r => {
        const label = r.closest('label');
        if (label) {
            label.style.borderColor = r.checked ? '#d4a017' : '#e0e0e0';
            label.style.background  = r.checked ? '#fffbf0' : 'white';
        }
    });
}

// =====================================================================
// CORRECTION DU BUG : Prénom/Nom de l'adresse non pris en compte
// =====================================================================
// Problème : Au chargement de la page, les champs Prénom et Nom étaient
// pré-remplis via PHP avec les données du compte utilisateur ($user['prenom'])
// au lieu des données propres à l'adresse ($addr['prenom'] dans la table adresses).
// Résultat : si l'adresse principale avait un nom/prénom différent du compte,
// le mauvais nom s'affichait — et était envoyé à la commande.
//
// Solution : Au chargement du DOM, on détecte le radio coché par défaut
// (l'adresse principale) et on appelle remplirAdresse() dessus.
// Cela force le remplissage des champs avec les vraies données de l'adresse.
// =====================================================================
document.addEventListener('DOMContentLoaded', function () {
    // querySelector() trouve le PREMIER élément qui correspond au sélecteur CSS
    // 'input[name="adresse_selectionnee"]:checked' = le radio coché par défaut
    const radioChecked = document.querySelector('input[name="adresse_selectionnee"]:checked');

    // On vérifie que le radio existe (= l'utilisateur a au moins une adresse)
    // puis on appelle remplirAdresse() pour remplir les champs avec les bonnes données
    if (radioChecked) {
        remplirAdresse(radioChecked);
    }
});
</script>

<?php include '../includes/footer.php'; ?>