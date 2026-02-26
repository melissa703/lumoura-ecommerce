<?php
// Page détail d'un produit
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

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
    
    // Récupérer les avis du produit
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
    $avgRating = $ratingData['avg_rating'] ? round($ratingData['avg_rating'], 1) : 0;
    $reviewCount = $ratingData['review_count'];
    
} catch (PDOException $e) {
    die('Erreur lors du chargement du produit');
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
                $price = $product['prix'];
                $discount = $product['promotion_pourcentage'];
                
                if ($discount > 0):
                    $discountedPrice = calculateDiscount($price, $discount);
                ?>
                    <div class="original-price" style="text-decoration: line-through; color: #999; font-size: 20px;">
                        <?php echo formatPrice($price); ?>
                    </div>
                    <div class="current-price" style="color: #D4AF37; font-size: 32px; font-weight: bold;">
                        <?php echo formatPrice($discountedPrice); ?>
                    </div>
                    <div class="discount-badge" style="background: #D4AF37; color: white; padding: 5px 10px; border-radius: 3px; display: inline-block; margin-left: 10px;">
                        Économisez <?php echo $discount; ?>%
                    </div>
                <?php else: ?>
                    <div class="current-price" style="font-size: 32px; font-weight: bold; color: #2C1810;">
                        <?php echo formatPrice($price); ?>
                    </div>
                <?php endif; ?>
            </div>
            
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
                        Volume : <?php echo $product['volume_ml']; ?> ml
                    </li>
                    <li style="margin-bottom: 10px; padding-left: 20px; position: relative;">
                        <i class="fas fa-tag" style="position: absolute; left: 0; color: #D4AF37;"></i>
                        Concentration : <?php echo $product['concentration']; ?>
                    </li>
                    <li style="margin-bottom: 10px; padding-left: 20px; position: relative;">
                        <i class="fas fa-venus-mars" style="position: absolute; left: 0; color: #D4AF37;"></i>
                        Genre : <?php echo $product['genre']; ?>
                    </li>
                    <li style="margin-bottom: 10px; padding-left: 20px; position: relative;">
                        <i class="fas fa-box" style="position: absolute; left: 0; color: #D4AF37;"></i>
                        Stock : <?php echo $product['stock'] > 0 ? 'En stock' : 'Rupture de stock'; ?>
                    </li>
                </ul>
            </div>
            
            <!-- Sélecteur de quantité -->
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
            
            <!-- Métadonnées -->
            <div class="product-meta">
                <div class="meta-item">
                    <i class="fas fa-shipping-fast"></i>
                    Livraison gratuite dès 100€
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
                
                <?php if (isLoggedIn()): ?>
                    <button class="btn-primary" onclick="document.getElementById('reviewForm').scrollIntoView()">
                        Laisser un avis
                    </button>
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
        
        <!-- Formulaire d'avis (visible seulement si connecté) -->
        <?php if (isLoggedIn()): ?>
            <div id="reviewForm" style="margin-top: 40px; background: #F5F0E6; padding: 30px; border-radius: 10px;">
                
                <!-- === AFFICHAGE DES MESSAGES (succès / erreurs) === -->
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
                <!-- === FIN AFFICHAGE MESSAGES === -->

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
                        <label for="comment" style="display: block; margin-bottom: 10px; font-weight: 500;">Votre commentaire</label>
                        <textarea id="comment" name="comment" 
                                  style="width: 100%; padding: 15px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit;"
                                  rows="5" placeholder="Partagez votre expérience avec ce parfum..." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn-primary" style="padding: 12px 30px;">
                        Publier mon avis
                    </button>
                </form>
            </div>
            
            <script>
            // Gestion des étoiles de notation
            document.querySelectorAll('.star-rating i').forEach(star => {
                star.addEventListener('click', function() {
                    const rating = this.dataset.rating;
                    document.getElementById('ratingInput').value = rating;
                    
                    // Met à jour l'affichage des étoiles
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
                                $price = $similar['prix'];
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
                                <button class="btn-cart" data-product-id="<?php echo $similar['id_produit']; ?>">
                                    <i class="fas fa-shopping-cart"></i> Ajouter
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>


<script>
// ========================================
// GESTION DES BOUTONS PAGE PRODUIT
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    
    // ==========================================
    // BOUTONS QUANTITÉ (+/-)
    // ==========================================
    const quantityInput = document.getElementById('quantity');
    const minusBtn = document.querySelector('.quantity-btn.minus');
    const plusBtn = document.querySelector('.quantity-btn.plus');
    
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
            let maxValue = parseInt(quantityInput.max);
            if (currentValue < maxValue) {
                quantityInput.value = currentValue + 1;
            }
        });
    }
    
    // ==========================================
    // BOUTON "AJOUTER AU PANIER"
    // ==========================================
    const addToCartBtn = document.querySelector('.btn-add-to-cart');
    
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const quantity = parseInt(quantityInput.value) || 1;
            
            // Animation du bouton
            const originalHTML = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
            this.disabled = true;
            
            // Requête AJAX
            fetch('ajouter_au_panier.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + encodeURIComponent(productId) + '&quantity=' + encodeURIComponent(quantity)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Succès
                    this.innerHTML = '<i class="fas fa-check"></i> Ajouté !';
                    this.style.background = '#27ae60';
                    
                    // Mettre à jour le compteur panier dans la navbar
                    const cartCounters = document.querySelectorAll('.cart-count, #cartCount');
                    cartCounters.forEach(counter => {
                        let current = parseInt(counter.textContent) || 0;
                        counter.textContent = current + quantity;
                        
                        // Animation
                        counter.style.transform = 'scale(1.4)';
                        setTimeout(() => {
                            counter.style.transform = 'scale(1)';
                        }, 300);
                    });
                    
                    // Restaurer le bouton après 2s
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.style.background = '';
                        this.disabled = false;
                    }, 2000);
                    
                } else {
                    // Erreur
                    if (data.message && data.message.includes('connecter')) {
                        // Utilisateur non connecté
                        if (confirm('Vous devez être connecté. Aller à la page de connexion ?')) {
                            window.location.href = 'connexion.php';
                        } else {
                            this.innerHTML = originalHTML;
                            this.disabled = false;
                        }
                    } else {
                        alert(data.message || 'Erreur lors de l\'ajout au panier');
                        this.innerHTML = originalHTML;
                        this.disabled = false;
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur réseau. Veuillez réessayer.');
                this.innerHTML = originalHTML;
                this.disabled = false;
            });
        });
    }
    
    // ==========================================
    // BOUTON "ACHETER MAINTENANT"
    // ==========================================
    const buyNowBtn = document.querySelector('.btn-buy-now');
    
    if (buyNowBtn) {
        buyNowBtn.addEventListener('click', function() {
            const productId = addToCartBtn ? addToCartBtn.getAttribute('data-product-id') : null;
            const quantity = parseInt(quantityInput.value) || 1;
            
            if (!productId) {
                alert('Erreur : Produit non trouvé');
                return;
            }
            
            // Animation du bouton
            const originalHTML = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
            this.disabled = true;
            
            // Ajouter au panier puis rediriger vers le panier
            fetch('ajouter_au_panier.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + encodeURIComponent(productId) + '&quantity=' + encodeURIComponent(quantity)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Rediriger vers le panier
                    window.location.href = 'panier.php';
                } else {
                    if (data.message && data.message.includes('connecter')) {
                        // Utilisateur non connecté
                        if (confirm('Vous devez être connecté. Aller à la page de connexion ?')) {
                            window.location.href = 'connexion.php';
                        } else {
                            this.innerHTML = originalHTML;
                            this.disabled = false;
                        }
                    } else {
                        alert(data.message || 'Erreur lors de l\'ajout au panier');
                        this.innerHTML = originalHTML;
                        this.disabled = false;
                    }
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur réseau. Veuillez réessayer.');
                this.innerHTML = originalHTML;
                this.disabled = false;
            });
        });
    }
    // ==========================================
    // BOUTONS "AJOUTER" DES PRODUITS SIMILAIRES
    // ==========================================
    document.querySelectorAll('.btn-cart').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const originalHTML = this.innerHTML;
            
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.disabled = true;
            
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
                    this.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur réseau');
                this.innerHTML = originalHTML;
                this.disabled = false;
            });
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>