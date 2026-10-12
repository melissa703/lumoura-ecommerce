<?php
// Page détail d'un produit
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Constante livraison gratuite (cohérente avec panier.php et commande.php)
if (!defined('LIVRAISON_GRATUITE_SEUIL')) {
    define('LIVRAISON_GRATUITE_SEUIL', 150);
}

// Vérifier si un ID de produit est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: catalogue.php');
    exit();
}

$productId = intval($_GET['id']);

// Récupérer les informations du produit
try {
    $query = "SELECT * FROM produits WHERE id_produit = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        header('Location: catalogue.php');
        exit();
    }

    // Récupérer les avis du produit (statut 'approuve' uniquement)
    $reviewsQuery = "SELECT a.*, u.nom, u.prenom FROM avis a 
                    JOIN utilisateurs u ON a.id_utilisateur = u.id_utilisateur 
                    WHERE a.id_produit = ? AND a.statut = 'approuve' 
                    ORDER BY a.date_avis DESC LIMIT 5";
    $reviewsStmt = $pdo->prepare($reviewsQuery);
    $reviewsStmt->execute([$productId]);
    $reviews = $reviewsStmt->fetchAll();

    // Calculer la note moyenne
    $avgRatingQuery = "SELECT AVG(note) as avg_rating, COUNT(*) as review_count FROM avis 
                      WHERE id_produit = ? AND statut = 'approuve'";
    $avgStmt = $pdo->prepare($avgRatingQuery);
    $avgStmt->execute([$productId]);
    $ratingData = $avgStmt->fetch();
    $avgRating   = $ratingData['avg_rating'] ? round($ratingData['avg_rating'], 1) : 0;
    $reviewCount = $ratingData['review_count'];

    // ✅ CORRECTION : Vérifier si l'utilisateur connecté a déjà laissé un avis
    $already_reviewed = false;
    if (isLoggedIn()) {
        $check_avis = $pdo->prepare("SELECT id_avis FROM avis WHERE id_utilisateur = ? AND id_produit = ?");
        $check_avis->execute([$_SESSION['user_id'], $productId]);
        $already_reviewed = (bool) $check_avis->fetch();
    }

} catch (PDOException $e) {
    die('Erreur lors du chargement du produit');
}

// Récupérer les FORMATS du produit (30 / 50 / 100 ml) et leurs prix
try {
    $formatsStmt = $pdo->prepare("SELECT id_format, volume_ml, prix FROM produit_formats WHERE id_produit = ? ORDER BY volume_ml ASC");
    $formatsStmt->execute([$productId]);
    $formats = $formatsStmt->fetchAll();
} catch (PDOException $e) {
    $formats = [];
}

// Format sélectionné par défaut : le moins cher (le même que le "Dès X €" du catalogue)
$selectedFormat = null;
foreach ($formats as $f) {
    if ($selectedFormat === null || $f['prix'] < $selectedFormat['prix']) {
        $selectedFormat = $f;
    }
}

// Récupérer des produits similaires
try {
    $similarQuery = "SELECT * FROM produits 
                    WHERE id_categorie = ? AND id_produit != ? 
                    ORDER BY RAND() LIMIT 4";
    $similarStmt = $pdo->prepare($similarQuery);
    $similarStmt->execute([$product['id_categorie'], $productId]);
    $similarProducts = $similarStmt->fetchAll();
} catch (PDOException $e) {
    $similarProducts = [];
}

$pageTitle = $product['nom'] . " - Lumoura";
include '../includes/header.php';
?>

    <div class="product-detail">
        <!-- Galerie d'images -->
        <div class="product-gallery">
            <div class="main-image">
                <img src="<?php echo $product['image_url'] ?: 'https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=800&q=80'; ?>"
                     alt="<?php echo sanitize($product['nom']); ?>" id="mainImage">
            </div>
        </div>

        <!-- Informations du produit -->
        <div class="product-info-detail">
            <div class="product-category"><?php echo sanitize($product['marque']); ?></div>
            <h1 class="product-title"><?php echo sanitize($product['nom']); ?></h1>

            <!-- Notation -->
            <div class="product-rating">
                <?php echo displayRating(round($avgRating)); ?>
                <span class="review-count">(<?php echo $reviewCount; ?> avis)</span>
            </div>

            <!-- Prix -->
            <div class="product-price-detail">
                <?php
                // Prix du format sélectionné (sinon prix du produit si aucun format n'existe)
                $price    = $selectedFormat ? $selectedFormat['prix'] : $product['prix'];
                $discount = $product['promotion_pourcentage'];

                if ($discount > 0):
                    $discountedPrice = calculateDiscount($price, $discount);
                ?>
                    <div id="originalPrice" class="original-price" style="text-decoration: line-through; color: #999; font-size: 20px;">
                        <?php echo formatPrice($price); ?>
                    </div>
                    <div id="currentPrice" class="current-price" style="color: #D4AF37; font-size: 32px; font-weight: bold;">
                        <?php echo formatPrice($discountedPrice); ?>
                    </div>
                    <div class="discount-badge" style="background: #D4AF37; color: white; padding: 5px 10px; border-radius: 3px; display: inline-block; margin-left: 10px;">
                        Économisez <?php echo $discount; ?>%
                    </div>
                <?php else: ?>
                    <div id="currentPrice" class="current-price" style="font-size: 32px; font-weight: bold; color: #2C1810;">
                        <?php echo formatPrice($price); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Choix du format (30 / 50 / 100 ml) -->
            <?php if (!empty($formats)): ?>
            <div class="format-selector" style="margin: 20px 0;">
                <div style="font-weight: 500; margin-bottom: 10px;">Contenance :</div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php foreach ($formats as $f): ?>
                        <button type="button"
                                class="format-btn<?php echo $f['id_format'] == $selectedFormat['id_format'] ? ' active' : ''; ?>"
                                data-format-id="<?php echo (int)$f['id_format']; ?>"
                                data-volume="<?php echo (int)$f['volume_ml']; ?>"
                                data-price="<?php echo (float)$f['prix']; ?>"
                                style="padding: 10px 18px; border: 2px solid #D4AF37; border-radius: 6px; background: transparent; color: #2C1810; cursor: pointer; font-size: 15px;">
                            <?php echo (int)$f['volume_ml']; ?> ml
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <style>
                .format-btn.active { background: #D4AF37 !important; color: #fff !important; font-weight: 600; }
                .format-btn:hover  { background: rgba(212,175,55,0.15); }
            </style>
            <?php endif; ?>

            <!-- Description -->
            <div class="product-description-detail">
                <h3>Description</h3>
                <p><?php echo nl2br(sanitize($product['description'])); ?></p>
            </div>

            <!-- Caractéristiques -->
            <div class="product-features">
                <h3>Caractéristiques</h3>
                <ul style="list-style: none; padding-left: 0;">
                    <li style="margin-bottom: 10px; padding-left: 20px; position: relative;">
                        <i class="fas fa-wine-bottle" style="position: absolute; left: 0; color: #D4AF37;"></i>
                        Volume : <span id="volumeText"><?php echo $selectedFormat ? (int)$selectedFormat['volume_ml'] : $product['volume_ml']; ?></span> ml
                    </li>
                    <li style="margin-bottom: 10px; padding-left: 20px; position: relative;">
                        <i class="fas fa-tag" style="position: absolute; left: 0; color: #D4AF37;"></i>
                        Concentration : <?php echo $product['concentration']; ?>
                    </li>
                    <li style="margin-bottom: 10px; padding-left: 20px; position: relative;">
                        <i class="fas fa-venus-mars" style="position: absolute; left: 0; color: #D4AF37;"></i>
                        Genre : <?php echo $product['genre']; ?>
                    </li>
                    <!-- ✅ CORRECTION : Badge stock coloré avec seuil d'alerte -->
                    <li style="margin-bottom: 10px; padding-left: 20px; position: relative;">
                        <i class="fas fa-box" style="position: absolute; left: 0; color: #D4AF37;"></i>
                        Disponibilité :
                        <?php if ($product['stock'] <= 0): ?>
                            <span style="background: #e63946; color: white; padding: 2px 10px; border-radius: 10px; font-size: 0.85em; font-weight: bold;">
                                Rupture de stock
                            </span>
                        <?php elseif ($product['stock'] <= $product['seuil_alerte']): ?>
                            <span style="background: #f39c12; color: white; padding: 2px 10px; border-radius: 10px; font-size: 0.85em; font-weight: bold;">
                                Plus que <?php echo $product['stock']; ?> en stock !
                            </span>
                        <?php else: ?>
                            <span style="background: #2a9d8f; color: white; padding: 2px 10px; border-radius: 10px; font-size: 0.85em; font-weight: bold;">
                                En stock
                            </span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>

            <!-- ✅ CORRECTION : Sélecteur quantité désactivé si rupture de stock -->
            <?php if ($product['stock'] > 0): ?>
            <div class="quantity-selector">
                <label for="quantity" style="font-weight: 500; margin-right: 15px;">Quantité :</label>
                <button class="quantity-btn minus">-</button>
                <input type="number" id="quantity" class="quantity-input" value="1" min="1" max="<?php echo $product['stock']; ?>">
                <button class="quantity-btn plus">+</button>
            </div>

            <!-- Actions -->
            <div class="product-actions-detail">
                <button class="btn-add-to-cart" data-product-id="<?php echo $productId; ?>">
                    <i class="fas fa-shopping-cart"></i> Ajouter au panier
                </button>
                <button class="btn-buy-now">
                    <i class="fas fa-bolt"></i> Acheter maintenant
                </button>
            </div>
            <?php else: ?>
            <!-- ✅ CORRECTION : Bouton désactivé si rupture de stock -->
            <div class="product-actions-detail">
                <button disabled style="
                    background: #ccc;
                    color: #888;
                    padding: 15px 30px;
                    border: none;
                    border-radius: 8px;
                    font-size: 1.05em;
                    cursor: not-allowed;
                    width: 100%;
                    margin-bottom: 10px;
                ">
                    <i class="fas fa-times-circle"></i> Produit indisponible
                </button>
                <p style="color: #e63946; font-size: 0.9em; text-align: center; margin-top: 8px;">
                    Ce produit est actuellement en rupture de stock.
                </p>
            </div>
            <?php endif; ?>

            <!-- Métadonnées -->
            <div class="product-meta">
                <!-- ✅ CORRECTION : Seuil livraison gratuite cohérent (150€) -->
                <div class="meta-item">
                    <i class="fas fa-shipping-fast"></i>
                    Livraison gratuite dès <?php echo LIVRAISON_GRATUITE_SEUIL; ?>€
                </div>
                <div class="meta-item">
                    <i class="fas fa-undo"></i>
                    Retour gratuit sous 30 jours
                </div>
                <div class="meta-item">
                    <i class="fas fa-shield-alt"></i>
                    Paiement sécurisé
                </div>
            </div>
        </div>
    </div>

    <!-- Section Avis -->
    <div class="reviews-section" style="margin-top: 80px;">
        <h2 style="font-size: 28px; margin-bottom: 30px; border-bottom: 2px solid #D4AF37; padding-bottom: 10px;">Avis clients</h2>

        <div class="rating-summary" style="background: #F5F0E6; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 48px; font-weight: bold; color: #2C1810;"><?php echo $avgRating; ?>/5</div>
                    <div style="color: #D4AF37; margin: 10px 0;"><?php echo displayRating(round($avgRating)); ?></div>
                    <div style="color: #666;">Basé sur <?php echo $reviewCount; ?> avis</div>
                </div>

                <!-- ✅ CORRECTION : Bouton "Laisser un avis" masqué si déjà noté -->
                <?php if (isLoggedIn() && !$already_reviewed): ?>
                    <button class="btn-primary" onclick="document.getElementById('reviewForm').scrollIntoView()">
                        Laisser un avis
                    </button>
                <?php elseif (isLoggedIn() && $already_reviewed): ?>
                    <div style="background: #e6f4ea; color: #2e7d32; padding: 10px 18px; border-radius: 8px; font-size: 0.9em; border: 1px solid #c8e6c9;">
                        <i class="fas fa-check-circle" style="margin-right: 6px;"></i> Vous avez déjà noté ce produit
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Liste des avis -->
        <div class="reviews-list">
            <?php if ($reviews): ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-item" style="border-bottom: 1px solid #eee; padding: 20px 0;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <div>
                                <strong><?php echo sanitize($review['prenom'] . ' ' . $review['nom']); ?></strong>
                                <div style="color: #D4AF37; margin-top: 5px;"><?php echo displayRating($review['note']); ?></div>
                            </div>
                            <div style="color: #999; font-size: 14px;">
                                <?php echo date('d/m/Y', strtotime($review['date_avis'])); ?>
                            </div>
                        </div>
                        <p style="color: #666; line-height: 1.6;"><?php echo nl2br(sanitize($review['commentaire'])); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 40px 0;">
                    Soyez le premier à laisser un avis sur ce produit !
                </p>
            <?php endif; ?>
        </div>

        <!-- ✅ CORRECTION : Formulaire d'avis affiché uniquement si pas encore noté -->
        <?php if (isLoggedIn()): ?>
            <?php if ($already_reviewed): ?>
                <!-- Message si l'utilisateur a déjà noté -->
                <div style="margin-top: 40px; background: #f0fdf4; padding: 25px; border-radius: 10px; border: 1px solid #86efac; text-align: center;">
                    <i class="fas fa-check-circle" style="color: #2a9d8f; font-size: 2em; margin-bottom: 10px; display: block;"></i>
                    <p style="color: #2a9d8f; font-weight: bold; margin: 0;">Merci pour votre avis sur ce produit !</p>
                    <p style="color: #555; font-size: 0.9em; margin-top: 8px;">Un seul avis par produit est autorisé.</p>
                </div>
            <?php else: ?>
                <!-- Formulaire de soumission d'avis -->
                <div id="reviewForm" style="margin-top: 40px; background: #F5F0E6; padding: 30px; border-radius: 10px;">

                    <!-- Affichage des messages (succès / erreurs) -->
                    <?php
                    if (isset($_SESSION['success_message'])) {
                        echo '<div style="background: #e6f4ea; color: #2e7d32; padding: 16px; border-radius: 8px; margin-bottom: 24px; border-left: 4px solid #4caf50; font-weight: 500;">'
                             . htmlspecialchars($_SESSION['success_message'])
                             . '</div>';
                        unset($_SESSION['success_message']);
                    }

                    if (isset($_SESSION['review_errors']) && is_array($_SESSION['review_errors'])) {
                        echo '<div style="background: #ffebee; color: #c62828; padding: 16px; border-radius: 8px; margin-bottom: 24px; border-left: 4px solid #f44336;">';
                        foreach ($_SESSION['review_errors'] as $err) {
                            echo '<p style="margin: 6px 0;">⚠️ ' . htmlspecialchars($err) . '</p>';
                        }
                        echo '</div>';
                        unset($_SESSION['review_errors']);
                    }
                    ?>

                    <h3 style="margin-bottom: 20px;">Laisser un avis</h3>
                    <form method="POST" action="../includes/submit_review.php">
                        <input type="hidden" name="product_id" value="<?php echo $productId; ?>">

                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 10px; font-weight: 500;">Votre note</label>
                            <div class="star-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="far fa-star" data-rating="<?php echo $i; ?>"
                                       style="font-size: 24px; color: #ccc; cursor: pointer; margin-right: 5px;"></i>
                                <?php endfor; ?>
                                <input type="hidden" name="rating" id="ratingInput" value="5">
                            </div>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <label for="comment" style="display: block; margin-bottom: 10px; font-weight: 500;">
                                Votre commentaire <span style="color: #888; font-size: 0.85em;">(10 à 1000 caractères)</span>
                            </label>
                            <textarea id="comment" name="comment"
                                      style="width: 100%; padding: 15px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit;"
                                      rows="5" placeholder="Partagez votre expérience avec ce parfum..." required
                                      maxlength="1000"></textarea>
                            <!-- ✅ CORRECTION : Compteur de caractères -->
                            <div id="charCount" style="font-size: 0.82em; color: #888; text-align: right; margin-top: 4px;">0 / 1000</div>
                        </div>

                        <button type="submit" class="btn-primary" style="padding: 12px 30px;">
                            Publier mon avis
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <script>
            // Gestion des étoiles de notation
            document.querySelectorAll('.star-rating i').forEach(star => {
                star.addEventListener('click', function() {
                    const rating = this.dataset.rating;
                    document.getElementById('ratingInput').value = rating;

                    document.querySelectorAll('.star-rating i').forEach((s, index) => {
                        if (index < rating) {
                            s.classList.remove('far');
                            s.classList.add('fas');
                            s.style.color = '#D4AF37';
                        } else {
                            s.classList.remove('fas');
                            s.classList.add('far');
                            s.style.color = '#ccc';
                        }
                    });
                });
            });

            // ✅ CORRECTION : Compteur de caractères en temps réel
            const commentArea = document.getElementById('comment');
            const charCount   = document.getElementById('charCount');
            if (commentArea && charCount) {
                commentArea.addEventListener('input', function() {
                    const len = this.value.length;
                    charCount.textContent = len + ' / 1000';
                    charCount.style.color = len > 900 ? '#e63946' : '#888';
                });
            }
            </script>
        <?php endif; ?>
    </div>

    <!-- Produits similaires -->
    <?php if ($similarProducts): ?>
        <div class="similar-products" style="margin-top: 80px;">
            <h2 style="font-size: 28px; margin-bottom: 30px; text-align: center;">Vous aimerez aussi</h2>

            <div class="products-grid">
                <?php foreach ($similarProducts as $similar): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="<?php echo $similar['image_url'] ?: 'https://images.unsplash.com/photo-1541643600914-78b084683601?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'; ?>"
                                 alt="<?php echo sanitize($similar['nom']); ?>"
                                 onclick="window.location.href='produit.php?id=<?php echo $similar['id_produit']; ?>'">
                        </div>

                        <div class="product-info">
                            <div class="product-brand"><?php echo sanitize($similar['marque']); ?></div>
                            <h3 class="product-name">
                                <a href="produit.php?id=<?php echo $similar['id_produit']; ?>">
                                    <?php echo sanitize($similar['nom']); ?>
                                </a>
                            </h3>

                            <div class="product-price">
                                <?php
                                $price    = $similar['prix'];
                                $discount = $similar['promotion_pourcentage'];

                                if ($discount > 0):
                                    $discountedPrice = calculateDiscount($price, $discount);
                                ?>
                                    <span class="price-original"><?php echo formatPrice($price); ?></span>
                                    <span class="price-current"><?php echo formatPrice($discountedPrice); ?></span>
                                <?php else: ?>
                                    <span class="price-current"><?php echo formatPrice($price); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="product-actions">
                                <?php if ($similar['stock'] > 0): ?>
                                    <button class="btn-cart" data-product-id="<?php echo $similar['id_produit']; ?>">
                                        <i class="fas fa-shopping-cart"></i> Ajouter
                                    </button>
                                <?php else: ?>
                                    <button disabled style="background: #ccc; color: #888; padding: 8px 16px; border: none; border-radius: 6px; cursor: not-allowed; font-size: 0.9em;">
                                        Indisponible
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

<script>
// GESTION DES BOUTONS PAGE PRODUIT
document.addEventListener('DOMContentLoaded', function() {

    // BOUTONS QUANTITÉ (+/-)
    const quantityInput = document.getElementById('quantity');

    // ===== CHOIX DU FORMAT =====
    // Pourcentage de promotion du produit (0 s'il n'y en a pas)
    const discountPercent = <?php echo (float)$product['promotion_pourcentage']; ?>;
    let selectedFormatId  = <?php echo $selectedFormat ? (int)$selectedFormat['id_format'] : 0; ?>;

    function formatEuro(n) {
        return n.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
    }

    document.querySelectorAll('.format-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.format-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            selectedFormatId = parseInt(this.dataset.formatId);
            const basePrice  = parseFloat(this.dataset.price);

            // Mise à jour du prix affiché (avec promotion éventuelle)
            const original = document.getElementById('originalPrice');
            const current  = document.getElementById('currentPrice');
            if (original) {
                original.textContent = formatEuro(basePrice);
                current.textContent  = formatEuro(Math.round(basePrice * (1 - discountPercent / 100) * 100) / 100);
            } else if (current) {
                current.textContent = formatEuro(basePrice);
            }

            const volumeText = document.getElementById('volumeText');
            if (volumeText) volumeText.textContent = this.dataset.volume;
        });
    });
    const minusBtn      = document.querySelector('.quantity-btn.minus');
    const plusBtn       = document.querySelector('.quantity-btn.plus');

    if (minusBtn) {
        minusBtn.addEventListener('click', function() {
            let currentValue = parseInt(quantityInput.value);
            if (currentValue > 1) {
                quantityInput.value = currentValue - 1;
            }
        });
    }

    if (plusBtn) {
        plusBtn.addEventListener('click', function() {
            let currentValue = parseInt(quantityInput.value);
            let maxValue     = parseInt(quantityInput.max);
            if (currentValue < maxValue) {
                quantityInput.value = currentValue + 1;
            }
        });
    }

    // BOUTON "AJOUTER AU PANIER"
    const addToCartBtn = document.querySelector('.btn-add-to-cart');

    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', function() {
            const productId   = this.getAttribute('data-product-id');
            const quantity    = parseInt(quantityInput.value) || 1;
            const originalHTML = this.innerHTML;

            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
            this.disabled  = true;

            fetch('ajouter_au_panier.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + encodeURIComponent(productId) + '&quantity=' + encodeURIComponent(quantity) + '&format_id=' + encodeURIComponent(selectedFormatId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.innerHTML = '<i class="fas fa-check"></i> Ajouté !';
                    this.style.background = '#27ae60';

                    const cartCounters = document.querySelectorAll('.cart-count, #cartCount');
                    cartCounters.forEach(counter => {
                        let current = parseInt(counter.textContent) || 0;
                        counter.textContent = current + quantity;
                        counter.style.transform = 'scale(1.4)';
                        setTimeout(() => { counter.style.transform = 'scale(1)'; }, 300);
                    });

                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.style.background = '';
                        this.disabled = false;
                    }, 2000);

                } else {
                    if (data.message && data.message.includes('connecter')) {
                        if (confirm('Vous devez être connecté. Aller à la page de connexion ?')) {
                            window.location.href = 'connexion.php';
                        } else {
                            this.innerHTML = originalHTML;
                            this.disabled  = false;
                        }
                    } else {
                        alert(data.message || 'Erreur lors de l\'ajout au panier');
                        this.innerHTML = originalHTML;
                        this.disabled  = false;
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur réseau. Veuillez réessayer.');
                this.innerHTML = originalHTML;
                this.disabled  = false;
            });
        });
    }

    // BOUTON "ACHETER MAINTENANT"
    const buyNowBtn = document.querySelector('.btn-buy-now');

    if (buyNowBtn) {
        buyNowBtn.addEventListener('click', function() {
            const productId   = addToCartBtn ? addToCartBtn.getAttribute('data-product-id') : null;
            const quantity    = parseInt(quantityInput.value) || 1;
            const originalHTML = this.innerHTML;

            if (!productId) {
                alert('Erreur : Produit non trouvé');
                return;
            }

            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
            this.disabled  = true;

            fetch('ajouter_au_panier.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + encodeURIComponent(productId) + '&quantity=' + encodeURIComponent(quantity) + '&format_id=' + encodeURIComponent(selectedFormatId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'panier.php';
                } else {
                    if (data.message && data.message.includes('connecter')) {
                        if (confirm('Vous devez être connecté. Aller à la page de connexion ?')) {
                            window.location.href = 'connexion.php';
                        } else {
                            this.innerHTML = originalHTML;
                            this.disabled  = false;
                        }
                    } else {
                        alert(data.message || 'Erreur lors de l\'ajout au panier');
                        this.innerHTML = originalHTML;
                        this.disabled  = false;
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur réseau. Veuillez réessayer.');
                this.innerHTML = originalHTML;
                this.disabled  = false;
            });
        });
    }

    // BOUTONS "AJOUTER" DES PRODUITS SIMILAIRES
    document.querySelectorAll('.btn-cart').forEach(button => {
        button.addEventListener('click', function() {
            const productId   = this.getAttribute('data-product-id');
            const originalHTML = this.innerHTML;

            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.disabled  = true;

            fetch('ajouter_au_panier.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + encodeURIComponent(productId) + '&quantity=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.innerHTML = '<i class="fas fa-check"></i> Ajouté';
                    this.style.background = '#27ae60';

                    document.querySelectorAll('.cart-count, #cartCount').forEach(counter => {
                        counter.textContent = parseInt(counter.textContent) + 1;
                    });

                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.style.background = '';
                        this.disabled = false;
                    }, 2000);
                } else {
                    alert(data.message || 'Erreur');
                    this.innerHTML = originalHTML;
                    this.disabled  = false;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur réseau');
                this.innerHTML = originalHTML;
                this.disabled  = false;
            });
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>