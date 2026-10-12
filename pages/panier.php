<?php
// Page panier - Affichage et gestion du panier utilisateur
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// ✅ CORRECTION : Constante centralisée pour la livraison gratuite
// Modifier ici pour que panier.php et commande.php soient toujours cohérents
if (!defined('LIVRAISON_GRATUITE_SEUIL')) {
    define('LIVRAISON_GRATUITE_SEUIL', 150);
}

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['login_redirect'] = 'Veuillez vous connecter pour accéder à votre panier.';
    header('Location: connexion.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Gestion des actions du panier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = intval($_POST['product_id'] ?? 0);
    $format_id  = intval($_POST['format_id'] ?? 0); // format (30/50/100 ml) de la ligne du panier

    switch ($action) {
        case 'update':
            $quantity = intval($_POST['quantity'] ?? 0);
            if ($quantity < 1) $quantity = 1;

            // ✅ CORRECTION : Vérifier le stock avant de mettre à jour la quantité
            try {
                $stock_stmt = $pdo->prepare("SELECT stock FROM produits WHERE id_produit = ?");
                $stock_stmt->execute([$product_id]);
                $produit = $stock_stmt->fetch();

                // Le stock est commun à tous les formats d'un parfum :
                // on retire ce qui est déjà dans le panier dans les AUTRES formats
                $other_stmt = $pdo->prepare("SELECT COALESCE(SUM(quantite), 0) FROM panier WHERE id_utilisateur = ? AND id_produit = ? AND id_format != ?");
                $other_stmt->execute([$user_id, $product_id, $format_id]);
                $autres_formats = (int) $other_stmt->fetchColumn();
                $stock_dispo    = $produit ? max(1, $produit['stock'] - $autres_formats) : $quantity;

                if ($produit && $quantity > $stock_dispo) {
                    $quantity = $stock_dispo;
                    $success_message = 'Quantité ajustée au stock disponible (' . $produit['stock'] . ').';
                }

                $query = "UPDATE panier SET quantite = ? WHERE id_utilisateur = ? AND id_produit = ? AND id_format = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$quantity, $user_id, $product_id, $format_id]);
                if (empty($success_message)) $success_message = 'Quantité mise à jour.';
            } catch (PDOException $e) {
                $error_message = 'Erreur lors de la mise à jour : ' . $e->getMessage();
            }
            break;

        case 'remove':
            try {
                $query = "DELETE FROM panier WHERE id_utilisateur = ? AND id_produit = ? AND id_format = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$user_id, $product_id, $format_id]);
                $success_message = 'Article supprimé du panier.';
            } catch (PDOException $e) {
                $error_message = 'Erreur lors de la suppression : ' . $e->getMessage();
            }
            break;

        case 'clear':
            try {
                $query = "DELETE FROM panier WHERE id_utilisateur = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$user_id]);
                $success_message = 'Panier vidé.';
            } catch (PDOException $e) {
                $error_message = 'Erreur lors du vidage du panier : ' . $e->getMessage();
            }
            break;
    }

    // Redirection pour éviter la resoumission
    $referer = $_SERVER['HTTP_REFERER'] ?? 'panier.php';
    header("Location: $referer");
    exit();
}

// Récupérer les articles du panier
try {
    $query = "
        SELECT 
            p.*,
            c.nom_categorie,
            pan.quantite,
            pan.id_panier,
            pan.date_ajout,
            pan.id_format,
            f.prix      AS prix_format,   -- prix du FORMAT choisi (et non produits.prix)
            f.volume_ml AS volume_format  -- contenance choisie (30 / 50 / 100 ml)
        FROM panier pan
        JOIN produits p ON pan.id_produit = p.id_produit
        JOIN produit_formats f ON f.id_format = pan.id_format
        LEFT JOIN categories c ON p.id_categorie = c.id_categorie
        WHERE pan.id_utilisateur = ?
        ORDER BY pan.date_ajout DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ CORRECTION : Détecter les problèmes de stock pour chaque article
    $stock_warnings = [];
    $qte_par_produit = [];
    foreach ($cart_items as $item) {
        $qte_par_produit[$item['id_produit']] = ($qte_par_produit[$item['id_produit']] ?? 0) + $item['quantite'];
    }
    foreach ($cart_items as $item) {
        if ($item['stock'] <= 0) {
            $stock_warnings[$item['id_produit']] = 'rupture';
        } elseif ($qte_par_produit[$item['id_produit']] > $item['stock']) {
            $stock_warnings[$item['id_produit']] = 'insuffisant';
        }
    }

    // Calcul des totaux
    $subtotal = 0;
    $total_items = 0;
    $shipping_cost = 0;

    foreach ($cart_items as $item) {
        $price = $item['prix_format'];
        $discount = $item['promotion_pourcentage'];
        $final_price = $discount > 0 ? $price * (1 - $discount / 100) : $price;
        $item_total = $final_price * $item['quantite'];

        $subtotal += $item_total;
        $total_items += $item['quantite'];
    }

    // ✅ CORRECTION : Utilisation de la constante centralisée (cohérent avec commande.php)
    $shipping_cost = ($subtotal >= LIVRAISON_GRATUITE_SEUIL || $subtotal == 0) ? 0 : 5.90;
    $total = $subtotal + $shipping_cost;

} catch (PDOException $e) {
    $error_message = 'Erreur lors du chargement du panier : ' . $e->getMessage();
    $cart_items = [];
    $subtotal = 0;
    $total_items = 0;
    $total = 0;
    $shipping_cost = 0;
    $stock_warnings = [];
}

$pageTitle = "Mon Panier - Lumoura";
include '../includes/header.php';
?>

<div class="container" style="max-width: 1200px; margin: 0 auto; padding: 40px 20px;">
    <!-- En-tête du panier -->
    <div class="cart-header" style="margin-bottom: 40px;">
        <h1 class="page-title" style="font-size: 2.5em; margin-bottom: 10px; color: #3a2c1f; font-weight: bold;">
            <i class="fas fa-shopping-bag" style="margin-right: 15px; color: #d4a017;"></i>
            Mon Panier
        </h1>
        <?php if ($total_items > 0): ?>
            <p class="page-subtitle" style="font-size: 1.1em; color: #555;">
                Vous avez <strong><?php echo $total_items; ?></strong> article<?php echo $total_items > 1 ? 's' : ''; ?> dans votre panier
            </p>
        <?php endif; ?>
    </div>

    <!-- Messages -->
    <?php if ($error_message): ?>
        <div class="alert alert-danger" style="padding: 15px; margin-bottom: 20px; background: #ffe6e6; border: 1px solid #ffb3b3; border-radius: 6px; color: #cc0000; text-align: center;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success" style="padding: 15px; margin-bottom: 20px; background: #e6ffe6; border: 1px solid #b3ffb3; border-radius: 6px; color: #006600; text-align: center;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <!-- ✅ CORRECTION : Alerte globale si des articles ont des problèmes de stock -->
    <?php if (!empty($stock_warnings)): ?>
        <div style="padding: 15px; margin-bottom: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; color: #856404;">
            <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
            <strong>Attention :</strong> certains articles de votre panier ont des problèmes de stock. Veuillez les vérifier avant de passer commande.
        </div>
    <?php endif; ?>

    <div class="cart-layout" style="display: grid; grid-template-columns: 1fr 350px; gap: 40px;">
        <!-- Section principale des articles -->
        <div class="cart-items">
            <?php if (empty($cart_items)): ?>
                <!-- Panier vide -->
                <div class="empty-cart" style="text-align: center; padding: 80px 20px; background: white; border-radius: 16px; box-shadow: 0 8px 25px rgba(0,0,0,0.1);">
                    <i class="fas fa-shopping-bag fa-4x" style="margin-bottom: 30px; color: #d4a017;"></i>
                    <h3 style="font-size: 1.8em; margin-bottom: 15px; color: #3a2c1f;">Votre panier est vide</h3>
                    <p style="font-size: 1.1em; margin-bottom: 30px; color: #555;">
                        Découvrez notre collection de parfums d'exception et ajoutez vos favoris.
                    </p>
                    <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                        <a href="catalogue.php" class="btn" style="
                            background: #d4a017;
                            color: white;
                            padding: 16px 40px;
                            border-radius: 10px;
                            text-decoration: none;
                            font-weight: bold;
                            font-size: 1.1em;
                            transition: background 0.3s;
                        ">
                            <i class="fas fa-store"></i> Découvrir le catalogue
                        </a>
                        <a href="catalogue.php?filter=new" class="btn" style="
                            background: white;
                            color: #3a2c1f;
                            padding: 16px 40px;
                            border-radius: 10px;
                            text-decoration: none;
                            font-weight: bold;
                            font-size: 1.1em;
                            border: 2px solid #d4a017;
                            transition: background 0.3s;
                        ">
                            <i class="fas fa-star"></i> Voir les nouveautés
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Liste des articles -->
                <div class="cart-items-list" style="background: white; border-radius: 16px; padding: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                        <h3 style="margin: 0; font-size: 1.4em; color: #3a2c1f;">Articles (<?php echo $total_items; ?>)</h3>
                        <form method="POST" action="" onsubmit="return confirm('Vider tout votre panier ?');" style="margin: 0;">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" style="
                                background: none;
                                border: none;
                                color: #e63946;
                                cursor: pointer;
                                font-size: 1em;
                                padding: 8px 16px;
                                border-radius: 6px;
                                transition: background 0.3s;
                            ">
                                <i class="fas fa-trash"></i> Vider le panier
                            </button>
                        </form>
                    </div>

                    <div class="items-container">
                        <?php foreach ($cart_items as $item):
                            $price = $item['prix_format'];
                            $discount = $item['promotion_pourcentage'];
                            $final_price = $discount > 0 ? $price * (1 - $discount / 100) : $price;
                            $item_total = $final_price * $item['quantite'];
                            $image = $item['image_url'] ?: 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=800&q=80';
                            $has_stock_issue = isset($stock_warnings[$item['id_produit']]);
                        ?>
                            <div class="cart-item" style="
                                display: grid;
                                grid-template-columns: 100px 1fr 150px 100px;
                                gap: 20px;
                                padding: 25px 0;
                                border-bottom: 1px solid #eee;
                                align-items: center;
                                <?php echo $has_stock_issue ? 'background: #fff8f8; border-radius: 8px; padding: 20px;' : ''; ?>
                            ">
                                <!-- Image -->
                                <div>
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($item['nom']); ?>" style="
                                        width: 100px;
                                        height: 100px;
                                        object-fit: cover;
                                        border-radius: 10px;
                                        <?php echo $has_stock_issue ? 'opacity: 0.6;' : ''; ?>
                                    ">
                                </div>

                                <!-- Informations produit -->
                                <div>
                                    <h4 style="margin: 0 0 8px 0; font-size: 1.1em;">
                                        <a href="produit.php?id=<?php echo $item['id_produit']; ?>" style="color: #222; text-decoration: none;">
                                            <?php echo htmlspecialchars($item['nom']); ?>
                                        </a>
                                    </h4>
                                    <p style="margin: 0 0 8px 0; color: #777; font-size: 0.9em;">
                                        <?php echo htmlspecialchars($item['marque']); ?> • <?php echo htmlspecialchars($item['nom_categorie']); ?> • <strong><?php echo (int)$item['volume_format']; ?> ml</strong>
                                    </p>
                                    <?php if ($discount > 0): ?>
                                        <div style="font-size: 0.9em;">
                                            <span style="color: #777; text-decoration: line-through;"><?php echo formatPrice($price); ?></span>
                                            <span style="color: #e63946; font-weight: bold; margin-left: 10px;"><?php echo formatPrice($final_price); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-weight: bold; color: #222;"><?php echo formatPrice($final_price); ?></div>
                                    <?php endif; ?>

                                    <!-- ✅ CORRECTION : Badge d'alerte stock sous le prix -->
                                    <?php if (isset($stock_warnings[$item['id_produit']])): ?>
                                        <?php if ($stock_warnings[$item['id_produit']] === 'rupture'): ?>
                                            <div style="margin-top: 8px; color: #e63946; font-size: 0.85em; font-weight: bold;">
                                                <i class="fas fa-times-circle"></i> Rupture de stock — sera retiré au paiement
                                            </div>
                                        <?php else: ?>
                                            <div style="margin-top: 8px; color: #f39c12; font-size: 0.85em; font-weight: bold;">
                                                <i class="fas fa-exclamation-triangle"></i> Seulement <?php echo $item['stock']; ?> disponible(s)
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- ✅ CORRECTION : Afficher le stock restant si faible -->
                                        <?php if ($item['stock'] > 0 && $item['stock'] <= $item['seuil_alerte']): ?>
                                            <div style="margin-top: 8px; color: #f39c12; font-size: 0.82em;">
                                                <i class="fas fa-fire"></i> Plus que <?php echo $item['stock']; ?> en stock !
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <!-- Quantité -->
                                <div>
                                    <form method="POST" action="" class="quantity-form" style="display: flex; align-items: center; gap: 10px;">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="product_id" value="<?php echo $item['id_produit']; ?>">
                                        <input type="hidden" name="format_id" value="<?php echo $item['id_format']; ?>">
                                        <button type="button" class="qty-btn minus" onclick="this.parentNode.querySelector('input[name=quantity]').stepDown()" style="
                                            width: 35px;
                                            height: 35px;
                                            border: 1px solid #ddd;
                                            background: white;
                                            border-radius: 6px;
                                            cursor: pointer;
                                            font-size: 1.2em;
                                        ">-</button>

                                        <input type="number" name="quantity" value="<?php echo $item['quantite']; ?>"
                                               min="1" max="<?php echo max(1, $item['stock']); ?>" style="
                                            width: 60px;
                                            text-align: center;
                                            padding: 8px;
                                            border: 1px solid #ddd;
                                            border-radius: 6px;
                                            font-size: 1em;
                                        ">

                                        <button type="button" class="qty-btn plus" onclick="this.parentNode.querySelector('input[name=quantity]').stepUp()" style="
                                            width: 35px;
                                            height: 35px;
                                            border: 1px solid #ddd;
                                            background: white;
                                            border-radius: 6px;
                                            cursor: pointer;
                                            font-size: 1.2em;
                                        ">+</button>

                                        <button type="submit" title="Mettre à jour" style="
                                            background: none;
                                            border: none;
                                            color: #d4a017;
                                            cursor: pointer;
                                            margin-left: 10px;
                                            font-size: 1em;
                                        ">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </form>
                                </div>

                                <!-- Total et suppression -->
                                <div style="text-align: right;">
                                    <div style="font-weight: bold; font-size: 1.2em; margin-bottom: 10px; color: #3a2c1f;">
                                        <?php echo formatPrice($item_total); ?>
                                    </div>
                                    <form method="POST" action="" onsubmit="return confirm('Supprimer cet article du panier ?');" style="margin: 0;">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="product_id" value="<?php echo $item['id_produit']; ?>">
                                        <input type="hidden" name="format_id" value="<?php echo $item['id_format']; ?>">
                                        <button type="submit" style="
                                            background: none;
                                            border: none;
                                            color: #e63946;
                                            cursor: pointer;
                                            font-size: 0.9em;
                                            padding: 5px 10px;
                                            border-radius: 4px;
                                            transition: background 0.3s;
                                        ">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar de résumé et paiement -->
        <?php if (!empty($cart_items)): ?>
        <div class="cart-summary" style="position: sticky; top: 30px; height: fit-content;">
            <div style="background: white; border-radius: 16px; padding: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 25px 0; font-size: 1.4em; color: #3a2c1f;">Résumé de la commande</h3>

                <div class="summary-details" style="margin-bottom: 30px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                        <span>Sous-total (<?php echo $total_items; ?> article<?php echo $total_items > 1 ? 's' : ''; ?>)</span>
                        <span style="font-weight: bold;"><?php echo formatPrice($subtotal); ?></span>
                    </div>

                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                        <span>Frais de livraison</span>
                        <span>
                            <?php if ($shipping_cost == 0): ?>
                                <span style="color: #2a9d8f; font-weight: bold;">Gratuit</span>
                            <?php else: ?>
                                <?php echo formatPrice($shipping_cost); ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <!-- ✅ CORRECTION : Utilise la constante centralisée -->
                    <?php if ($subtotal < LIVRAISON_GRATUITE_SEUIL && $subtotal > 0): ?>
                        <div style="background: #e6f7ff; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9em;">
                            <i class="fas fa-shipping-fast" style="color: #2a9d8f; margin-right: 8px;"></i>
                            Ajoutez <strong><?php echo formatPrice(LIVRAISON_GRATUITE_SEUIL - $subtotal); ?></strong> pour bénéficier de la livraison gratuite !
                        </div>
                    <?php endif; ?>

                    <div style="border-top: 2px solid #eee; padding-top: 20px; margin-top: 20px;">
                        <div style="display: flex; justify-content: space-between; font-size: 1.3em; font-weight: bold;">
                            <span>Total</span>
                            <span style="color: #c0392b;"><?php echo formatPrice($total); ?></span>
                        </div>
                        <p style="font-size: 0.9em; color: #777; margin-top: 10px;">
                            TTC, hors éventuelles taxes locales
                        </p>
                    </div>
                </div>

                <!-- Boutons d'action -->
                <div class="cart-actions" style="display: flex; flex-direction: column; gap: 15px;">
                    <a href="commande.php" class="btn-checkout" style="
                        background: #d4a017;
                        color: white;
                        padding: 18px;
                        border-radius: 10px;
                        text-decoration: none;
                        font-weight: bold;
                        font-size: 1.1em;
                        text-align: center;
                        transition: background 0.3s;
                    ">
                        <i class="fas fa-lock" style="margin-right: 10px;"></i>
                        Procéder au paiement
                    </a>

                    <a href="catalogue.php" class="btn-continue" style="
                        background: white;
                        color: #3a2c1f;
                        padding: 15px;
                        border-radius: 10px;
                        text-decoration: none;
                        font-weight: bold;
                        font-size: 1em;
                        text-align: center;
                        border: 2px solid #d4a017;
                        transition: all 0.3s;
                    ">
                        <i class="fas fa-arrow-left" style="margin-right: 10px;"></i>
                        Continuer mes achats
                    </a>

                    <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                        <p style="font-size: 0.9em; color: #777; margin-bottom: 15px;">
                            Paiement sécurisé par
                        </p>
                        <div style="display: flex; justify-content: center; gap: 15px; font-size: 1.5em; color: #555;">
                            <i class="fab fa-cc-visa" title="Visa"></i>
                            <i class="fab fa-cc-mastercard" title="Mastercard"></i>
                            <i class="fab fa-cc-paypal" title="PayPal"></i>
                            <i class="fab fa-cc-apple-pay" title="Apple Pay"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Garanties -->
            <div style="background: white; border-radius: 16px; padding: 25px; margin-top: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.1);">
                <h4 style="margin: 0 0 20px 0; font-size: 1.2em; color: #3a2c1f;">
                    <i class="fas fa-shield-alt" style="color: #d4a017; margin-right: 10px;"></i>
                    Vos garanties
                </h4>
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 15px;">
                    <li style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-undo" style="color: #2a9d8f;"></i>
                        <span style="font-size: 0.95em;">Retour gratuit sous 30 jours</span>
                    </li>
                    <li style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-gift" style="color: #2a9d8f;"></i>
                        <span style="font-size: 0.95em;">Échantillons offerts avec chaque commande</span>
                    </li>
                    <li style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-lock" style="color: #2a9d8f;"></i>
                        <span style="font-size: 0.95em;">Paiement 100% sécurisé</span>
                    </li>
                    <li style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-truck" style="color: #2a9d8f;"></i>
                        <span style="font-size: 0.95em;">Livraison en 24-48h</span>
                    </li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation des boutons quantité
    const qtyButtons = document.querySelectorAll('.qty-btn');
    qtyButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            this.style.transform = 'scale(0.9)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
    });

    // Auto-soumission des formulaires de quantité après 1.5s d'inactivité
    const quantityInputs = document.querySelectorAll('input[name="quantity"]');
    let timeout;

    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            const form = this.closest('.quantity-form');
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                form.submit();
            }, 1500);
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>