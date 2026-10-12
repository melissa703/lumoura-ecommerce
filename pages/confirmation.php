<?php
// ============================================================
// FICHIER : confirmation.php
// RÔLE    : Page affichée APRÈS qu'une commande a été passée
//           → Récupère les détails de la commande en BDD
//           → Affiche le récapitulatif complet (articles, montant, adresse)
//           → Affiche les prochaines étapes (email, expédition...)
// ACCÈS   : Uniquement via commande.php après un commit() réussi
//           URL : confirmation.php?order_id=42
// ============================================================

// Page confirmation - Affichage de la confirmation de commande
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Vérification connexion : seul l'utilisateur qui a commandé peut voir sa confirmation
if (!isLoggedIn()) {
    header('Location: connexion.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupération de l'ID commande depuis l'URL (?order_id=42)
// intval() : sécurise la valeur en la convertissant en entier
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Récupération du message de succès stocké en session par commande.php
// Ce message n'existe qu'une fois (stocké puis immédiatement supprimé = flash message)
$success_message = $_SESSION['order_success'] ?? '';
unset($_SESSION['order_success']); // Supprime le message après lecture (ne s'affiche qu'une fois)

// Si pas d'order_id valide → redirection vers l'accueil
if ($order_id <= 0) {
    header('Location: index.php');
    exit();
}

// ============================================================
// SECTION 1 : RÉCUPÉRATION DE LA COMMANDE EN BDD
// ============================================================
try {
    // JOIN pour récupérer les infos commande + infos utilisateur en une seule requête
    // La condition "AND c.id_utilisateur = ?" est CRUCIALE pour la sécurité :
    // elle empêche un utilisateur de voir la commande d'un autre en changeant l'URL
    $order_query = "
        SELECT c.*, u.email, u.prenom, u.nom
        FROM commandes c
        JOIN utilisateurs u ON c.id_utilisateur = u.id_utilisateur
        WHERE c.id_commande = ? AND c.id_utilisateur = ?
    ";
    $order_stmt = $pdo->prepare($order_query);
    $order_stmt->execute([$order_id, $user_id]); // Double vérification : ID commande + ID utilisateur
    $order = $order_stmt->fetch();
    
    // Si la commande n'appartient pas à cet utilisateur → redirection (sécurité)
    if (!$order) {
        header('Location: index.php');
        exit();
    }
    
    // Récupération des articles de la commande depuis details_commande
    // JOIN produits pour avoir le nom, l'image et la marque de chaque article
    // Le prix_unitaire est celui figé au moment de la commande (pas le prix actuel)
    $items_query = "
        SELECT dc.*, p.nom, p.image_url, p.marque
        FROM details_commande dc
        JOIN produits p ON dc.id_produit = p.id_produit
        WHERE dc.id_commande = ?
    ";
    $items_stmt = $pdo->prepare($items_query);
    $items_stmt->execute([$order_id]);
    $order_items = $items_stmt->fetchAll(); // fetchAll() : récupère tous les articles
    
} catch (PDOException $e) {
    // die() : arrête tout le script et affiche le message d'erreur (à améliorer en production)
    die('Erreur lors du chargement de la commande.');
}

// ============================================================
// ENVOI EMAIL DE CONFIRMATION DE COMMANDE (une seule fois)
// ============================================================
// On utilise un flag en session pour éviter de renvoyer
// l'email si l'utilisateur recharge la page.
// Le flag 'email_commande_envoyé_{order_id}' est créé ici
// puis supprimé après envoi.
// ============================================================
if (!isset($_SESSION['email_commande_envoye_' . $order_id])) {
    require_once '../includes/envoyer-email.php';

    $email_envoye = envoyerEmailConfirmationCommande(
        $order['email'],
        $order['prenom'],
        $order,
        $order_items,
        $order_id
    );

    // Marquer comme envoyé en session (évite le doublon si l'utilisateur recharge)
    $_SESSION['email_commande_envoye_' . $order_id] = true;

    if (!$email_envoye) {
        error_log("⚠️ Échec envoi email confirmation commande #$order_id à " . $order['email']);
    }
}

// Titre de page dynamique avec le numéro de commande
$pageTitle = "Confirmation de commande #$order_id - Lumoura";
include '../includes/header.php';
?>

<style>
body {
    background: linear-gradient(to bottom, #f8f5f0 0%, #ffffff 100%);
}

.confirmation-container {
    max-width: 800px;
    margin: 60px auto;
    padding: 0 20px 60px;
}

.success-header {
    text-align: center;
    margin-bottom: 40px;
    animation: slideDown 0.5s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.success-icon {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, #2a9d8f 0%, #27ae60 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 25px;
    animation: scaleIn 0.5s ease-out 0.2s both;
}

@keyframes scaleIn {
    from {
        transform: scale(0);
    }
    to {
        transform: scale(1);
    }
}

.success-icon i {
    font-size: 50px;
    color: white;
}

.success-title {
    font-size: 2.5em;
    color: #2a9d8f;
    margin-bottom: 15px;
    font-weight: 700;
}

.success-subtitle {
    font-size: 1.2em;
    color: #666;
}

.order-card {
    background: white;
    border-radius: 16px;
    padding: 35px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    margin-bottom: 30px;
}

.order-number {
    background: linear-gradient(135deg, #d4a017 0%, #f4e4c1 100%);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 30px;
    text-align: center;
}

.order-number h2 {
    margin: 0;
    color: #2c1810;
    font-size: 1.8em;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.info-item {
    padding: 15px;
    background: #f8f5f0;
    border-radius: 10px;
    border-left: 4px solid #d4a017;
}

.info-label {
    font-size: 0.9em;
    color: #888;
    margin-bottom: 5px;
}

.info-value {
    font-weight: bold;
    color: #3a2c1f;
    font-size: 1.1em;
}

.order-items {
    margin-top: 30px;
}

.order-item {
    display: flex;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #eee;
}

.order-item:last-child {
    border-bottom: none;
}

.item-image {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 10px;
}

.item-info {
    flex: 1;
}

.item-name {
    font-weight: bold;
    color: #3a2c1f;
    margin-bottom: 5px;
}

.item-brand {
    color: #888;
    font-size: 0.9em;
    margin-bottom: 5px;
}

.item-quantity {
    color: #666;
    font-size: 0.9em;
}

.item-price {
    text-align: right;
    font-weight: bold;
    color: #d4a017;
    font-size: 1.2em;
}

.order-total {
    background: #f8f5f0;
    padding: 20px;
    border-radius: 12px;
    margin-top: 30px;
}

.total-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    font-size: 1.1em;
}

.total-row.final {
    border-top: 2px solid #d4a017;
    padding-top: 15px;
    margin-top: 15px;
    font-size: 1.4em;
    font-weight: bold;
    color: #c0392b;
}

.action-buttons {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.btn {
    flex: 1;
    min-width: 200px;
    padding: 15px 30px;
    border-radius: 10px;
    text-decoration: none;
    text-align: center;
    font-weight: bold;
    font-size: 1.1em;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-primary {
    background: #d4a017;
    color: white;
}

.btn-primary:hover {
    background: #c29b15;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(212, 160, 23, 0.3);
}

.btn-secondary {
    background: white;
    color: #3a2c1f;
    border: 2px solid #d4a017;
}

.btn-secondary:hover {
    background: #f8f5f0;
}

.next-steps {
    background: #e6f7ff;
    padding: 25px;
    border-radius: 12px;
    border-left: 4px solid #2a9d8f;
    margin-top: 30px;
}

.next-steps h3 {
    color: #2a9d8f;
    margin-bottom: 15px;
}

.next-steps ul {
    list-style: none;
    padding: 0;
}

.next-steps li {
    padding: 10px 0;
    display: flex;
    align-items: start;
    gap: 12px;
}

.next-steps li i {
    color: #2a9d8f;
    margin-top: 3px;
}
</style>

<div class="confirmation-container">

    <!-- ===== FLASH MESSAGE DE SUCCÈS ===== -->
    <!-- Affiché seulement si $_SESSION['order_success'] existait (stocké par commande.php) -->
    <?php if ($success_message): ?>
    <div style="background: linear-gradient(135deg, #2a9d8f, #27ae60); color: white; padding: 20px 30px; border-radius: 12px; margin-bottom: 30px; text-align: center; font-size: 1.2em; font-weight: bold; box-shadow: 0 5px 20px rgba(42,157,143,0.3);">
        <i class="fas fa-check-circle" style="margin-right: 10px; font-size: 1.3em;"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
    <?php endif; ?>

    <!-- ===== EN-TÊTE ANIMÉ : icône ✓ + titre + prénom du client ===== -->
    <!-- Les animations CSS sont définies dans le <style> : slideDown et scaleIn -->
    <div class="success-header">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        <h1 class="success-title">Commande confirmée !</h1>
        <p class="success-subtitle">
            Merci <?php echo htmlspecialchars($order['prenom']); ?> ! Votre commande a été enregistrée avec succès.
        </p>
    </div>
    
    <!-- Carte de la commande -->
    <div class="order-card">
        <!-- Numéro de commande formaté avec str_pad() : affiche ex '000042' au lieu de '42' -->
        <div class="order-number">
            <h2>
                <i class="fas fa-receipt" style="margin-right: 10px;"></i>
                Commande #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?>
            </h2>
        </div>
        
        <!-- ===== GRILLE D'INFORMATIONS ===== -->
        <!-- CSS Grid auto-fit : s'adapte au nombre de colonnes selon la taille d'écran -->
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Date de commande</div>
                <div class="info-value">
                    <?php echo date('d/m/Y à H:i', strtotime($order['date_commande'])); ?>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Statut</div>
                <div class="info-value" style="color: #f39c12;">
                    <i class="fas fa-clock"></i> En attente
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Mode de paiement</div>
                <div class="info-value">
                    <?php 
                    // Tableau de correspondance valeur BDD → libellé lisible
                    $payment_methods = [
                        'carte' => 'Carte bancaire',
                        'paypal' => 'PayPal',
                        'apple_pay' => 'Apple Pay'
                    ];
                    // ?? 'Carte bancaire' : valeur par défaut si le mode_paiement n'est pas dans le tableau
                    echo $payment_methods[$order['mode_paiement']] ?? 'Carte bancaire';
                    ?>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Email de confirmation</div>
                <div class="info-value" style="font-size: 0.95em;">
                    <?php echo htmlspecialchars($order['email']); ?>
                </div>
            </div>
        </div>
        
        <!-- ===== ADRESSE DE LIVRAISON ===== -->
        <!-- nl2br() : convertit les retours à la ligne \n en <br> HTML -->
        <div style="background: #f8f5f0; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
            <h3 style="margin: 0 0 10px 0; color: #3a2c1f;">
                <i class="fas fa-map-marker-alt" style="color: #d4a017; margin-right: 10px;"></i>
                Adresse de livraison
            </h3>
            <p style="margin: 0; color: #555; line-height: 1.6;">
                <?php echo nl2br(htmlspecialchars($order['adresse_livraison'])); // Double sécurité : htmlspecialchars() contre XSS, nl2br() pour l'affichage ?>
            </p>
        </div>
        
        <!-- ===== LISTE DES ARTICLES COMMANDÉS ===== -->
        <!-- Boucle sur $order_items récupéré via details_commande + JOIN produits -->
        <div class="order-items">
            <h3 style="margin: 0 0 20px 0; color: #3a2c1f; border-bottom: 2px solid #d4a017; padding-bottom: 10px;">
                Articles commandés
            </h3>
            
            <?php foreach ($order_items as $item): 
                // prix_unitaire : prix figé au moment de la commande (indépendant des changements futurs)
                $item_total = $item['prix_unitaire'] * $item['quantite'];
            ?>
                <div class="order-item">
                    <img 
                        src="<?php echo $item['image_url'] ?: 'https://via.placeholder.com/80'; ?>" 
                        alt="<?php echo htmlspecialchars($item['nom']); ?>"
                        class="item-image"
                    >
                    <div class="item-info">
                        <div class="item-name"><?php echo htmlspecialchars($item['nom']); ?></div>
                        <div class="item-brand"><?php echo htmlspecialchars($item['marque']); ?></div>
                        <div class="item-quantity">Quantité : <?php echo $item['quantite']; ?></div>
                    </div>
                    <div class="item-price">
                        <?php echo formatPrice($item_total); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- ===== RÉCAPITULATIF DES MONTANTS ===== -->
        <!-- Sous-total = montant - frais_livraison (les deux sont stockés séparément en BDD) -->
        <div class="order-total">
            <div class="total-row">
                <span>Sous-total</span>
                <span><?php echo formatPrice($order['montant'] - $order['frais_livraison']); // Recalcul : total - frais = sous-total produits ?></span>
            </div>
            <div class="total-row">
                <span>Livraison</span>
                <span><?php echo $order['frais_livraison'] == 0 ? 'GRATUIT' : formatPrice($order['frais_livraison']); ?></span>
            </div>
            <div class="total-row final">
                <span>Total payé</span>
                <span><?php echo formatPrice($order['montant']); ?></span>
            </div>
        </div>
    </div>
    
    <!-- ===== SECTION PROCHAINES ÉTAPES ===== -->
    <!-- Informe l'utilisateur de ce qui va se passer après sa commande -->
    <div class="next-steps">
        <h3><i class="fas fa-info-circle" style="margin-right: 10px;"></i>Que se passe-t-il maintenant ?</h3>
        <ul>
            <li>
                <i class="fas fa-envelope"></i>
                <span>Vous allez recevoir un email de confirmation à <strong><?php echo htmlspecialchars($order['email']); ?></strong></span>
            </li>
            <li>
                <i class="fas fa-box"></i>
                <span>Nous préparons votre commande avec soin</span>
            </li>
            <li>
                <i class="fas fa-truck"></i>
                <span>Livraison estimée : <strong>2 à 4 jours ouvrés</strong></span>
            </li>
            <li>
                <i class="fas fa-bell"></i>
                <span>Vous recevrez un email dès l'expédition de votre colis</span>
            </li>
        </ul>
    </div>
    
    <!-- ===== BOUTONS DE NAVIGATION POST-COMMANDE ===== -->
    <div class="action-buttons">
        <a href="index.php" class="btn btn-primary">
            <i class="fas fa-home"></i>
            Retour à l'accueil
        </a>
        <a href="catalogue.php" class="btn btn-secondary">
            <i class="fas fa-shopping-bag"></i>
            Continuer mes achats
        </a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>