<?php
/**
 * wishlist.php
 * Page d'affichage des produits favoris de l'utilisateur - VERSION CORRIGÉE
 */

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['login_redirect'] = 'Veuillez vous connecter pour voir vos favoris.';
    header('Location: connexion.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$pageTitle = "Mes Favoris - Lumoura";

include '../includes/header.php';

// Récupérer les produits favoris
$query = "
    SELECT 
        p.*,
        f.date_ajout as date_favori
    FROM favoris f
    JOIN produits p ON f.id_produit = p.id_produit
    WHERE f.id_utilisateur = ?
    ORDER BY f.date_ajout DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$favorites = $stmt->fetchAll();
?>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.wishlist-page {
    padding-top: 80px;
    min-height: 100vh;
    background: linear-gradient(to bottom, #faf8f3 0%, #ffffff 100%);
}

.wishlist-header {
    background: linear-gradient(135deg, #D4AF37 0%, #F4E4C1 100%);
    padding: 50px 20px;
    text-align: center;
    margin-bottom: 40px;
    box-shadow: 0 4px 20px rgba(212, 175, 55, 0.15);
}

.wishlist-header h1 {
    font-size: 2.8rem;
    margin-bottom: 15px;
    color: #2c1810;
    font-weight: 700;
    letter-spacing: -0.5px;
}

.wishlist-header h1 i {
    color: #ef4444;
    margin-right: 15px;
    animation: heartbeat 1.5s ease-in-out infinite;
}

@keyframes heartbeat {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.wishlist-header p {
    font-size: 1.15rem;
    color: #5a4a3a;
    font-weight: 500;
}

.wishlist-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px 80px;
}

/* Message liste vide */
.wishlist-empty {
    text-align: center;
    padding: 120px 40px;
    background: white;
    border-radius: 24px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    max-width: 600px;
    margin: 60px auto;
}

.wishlist-empty i {
    font-size: 6rem;
    color: #D4AF37;
    margin-bottom: 30px;
    opacity: 0.3;
    display: block;
}

.wishlist-empty h2 {
    font-size: 2rem;
    margin-bottom: 20px;
    color: #2c1810;
    font-weight: 700;
}

.wishlist-empty p {
    font-size: 1.15rem;
    color: #666;
    margin-bottom: 40px;
    line-height: 1.6;
}

/* Grille de produits */
.wishlist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 25px;
    margin-top: 20px;
}

/* Carte produit améliorée */
.wishlist-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.wishlist-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 35px rgba(212, 175, 55, 0.25);
}

/* Image produit */
.wishlist-card-image {
    position: relative;
    width: 100%;
    height: 320px;
    overflow: hidden;
    background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
    cursor: pointer;
}

.wishlist-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.wishlist-card:hover .wishlist-card-image img {
    transform: scale(1.08);
}

/* Badge promotion */
.wishlist-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 700;
    z-index: 3;
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
}

/* Bouton supprimer */
.wishlist-remove {
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: none;
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    z-index: 3;
}

.wishlist-remove:hover {
    background: #ef4444;
    transform: rotate(90deg) scale(1.15);
}

.wishlist-remove i {
    font-size: 1.3rem;
    color: #ef4444;
    transition: color 0.3s ease;
}

.wishlist-remove:hover i {
    color: white;
}

/* Contenu de la carte */
.wishlist-card-content {
    padding: 25px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.wishlist-brand {
    font-size: 0.85rem;
    color: #D4AF37;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 10px;
}

.wishlist-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: #2c1810;
    margin-bottom: 12px;
    line-height: 1.4;
    min-height: 2.8rem;
}

.wishlist-name a {
    color: inherit;
    text-decoration: none;
    transition: color 0.3s ease;
}

.wishlist-name a:hover {
    color: #D4AF37;
}

.wishlist-description {
    font-size: 0.95rem;
    color: #666;
    margin-bottom: 20px;
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    flex-grow: 1;
}

/* Prix */
.wishlist-price-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.wishlist-price-current {
    font-size: 1.75rem;
    font-weight: 800;
    color: #2c1810;
    letter-spacing: -0.5px;
}

.wishlist-price-original {
    font-size: 1.15rem;
    color: #999;
    text-decoration: line-through;
}

.wishlist-discount {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 700;
}

/* Actions */
.wishlist-actions {
    display: flex;
    gap: 10px;
    margin-top: auto;
}

.wishlist-btn-cart {
    flex: 1;
    background: linear-gradient(135deg, #D4AF37 0%, #c29b15 100%);
    color: white;
    border: none;
    padding: 14px 20px;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
}

.wishlist-btn-cart:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
    background: linear-gradient(135deg, #c29b15 0%, #D4AF37 100%);
}

.wishlist-btn-cart:active {
    transform: translateY(0);
}

.wishlist-btn-cart i {
    font-size: 1rem;
}

/* Date d'ajout */
.wishlist-date {
    text-align: center;
    font-size: 0.8rem;
    color: #999;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #f0f0f0;
    font-style: italic;
}

/* Bouton retour au catalogue */
.btn-primary {
    background: linear-gradient(135deg, #D4AF37 0%, #c29b15 100%);
    color: white;
    border: none;
    padding: 18px 40px;
    border-radius: 14px;
    font-size: 1.15rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 6px 25px rgba(212, 175, 55, 0.3);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(212, 175, 55, 0.4);
    background: linear-gradient(135deg, #c29b15 0%, #D4AF37 100%);
}

.btn-primary i {
    font-size: 1.2rem;
}

/* Responsive */
@media (max-width: 1024px) {
    .wishlist-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }
}

@media (max-width: 768px) {
    .wishlist-header h1 {
        font-size: 2rem;
    }
    
    .wishlist-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .wishlist-card-image {
        height: 280px;
    }
    
    .wishlist-empty {
        padding: 80px 30px;
    }
}

@media (max-width: 480px) {
    .wishlist-header h1 {
        font-size: 1.75rem;
    }
    
    .wishlist-price-current {
        font-size: 1.5rem;
    }
    
    .wishlist-card-content {
        padding: 20px;
    }
}

/* Animation d'entrée */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.wishlist-card {
    animation: fadeInUp 0.6s ease-out backwards;
}

.wishlist-card:nth-child(1) { animation-delay: 0.1s; }
.wishlist-card:nth-child(2) { animation-delay: 0.2s; }
.wishlist-card:nth-child(3) { animation-delay: 0.3s; }
.wishlist-card:nth-child(4) { animation-delay: 0.4s; }
.wishlist-card:nth-child(5) { animation-delay: 0.5s; }
.wishlist-card:nth-child(6) { animation-delay: 0.6s; }
</style>

<div class="wishlist-page">
    <!-- En-tête -->
    <section class="wishlist-header">
        <h1><i class="fas fa-heart"></i> Mes Favoris</h1>
        <p><?php echo count($favorites); ?> produit<?php echo count($favorites) > 1 ? 's' : ''; ?> dans votre liste</p>
    </section>

    <!-- Contenu -->
    <section class="wishlist-container">
        <?php if (empty($favorites)): ?>
            <!-- Liste vide -->
            <div class="wishlist-empty">
                <i class="far fa-heart"></i>
                <h2>Votre liste de favoris est vide</h2>
                <p>Découvrez nos collections et ajoutez vos parfums préférés à votre wishlist</p>
                <a href="catalogue.php" class="btn-primary">
                    <i class="fas fa-shopping-bag"></i> Découvrir le catalogue
                </a>
            </div>
        <?php else: ?>
            <!-- Grille de produits favoris -->
            <div class="wishlist-grid">
                <?php foreach ($favorites as $product): ?>
                    <div class="wishlist-card" data-product-id="<?php echo $product['id_produit']; ?>">
                        <!-- Badge promotion -->
                        <?php if ($product['promotion_pourcentage'] > 0): ?>
                            <span class="wishlist-badge">-<?php echo $product['promotion_pourcentage']; ?>%</span>
                        <?php endif; ?>
                        
                        <!-- Bouton supprimer -->
                        <button class="wishlist-remove" data-product-id="<?php echo $product['id_produit']; ?>" title="Retirer des favoris">
                            <i class="fas fa-times"></i>
                        </button>
                        
                        <!-- Image cliquable -->
                        <a href="produit.php?id=<?php echo $product['id_produit']; ?>" class="wishlist-card-image">
                            <img src="<?php echo $product['image_url'] ?: 'https://images.unsplash.com/photo-1541643600914-78b084683601?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'; ?>" 
                                 alt="<?php echo sanitize($product['nom']); ?>">
                        </a>
                        
                        <!-- Contenu -->
                        <div class="wishlist-card-content">
                            <div class="wishlist-brand"><?php echo sanitize($product['marque']); ?></div>
                            <h3 class="wishlist-name">
                                <a href="produit.php?id=<?php echo $product['id_produit']; ?>">
                                    <?php echo sanitize($product['nom']); ?>
                                </a>
                            </h3>
                            <p class="wishlist-description"><?php echo sanitize($product['description_courte'] ?? 'Un parfum exceptionnel qui saura vous séduire'); ?></p>
                            
                            <!-- Prix -->
                            <div class="wishlist-price-row">
                                <?php
                                $price = $product['prix'];
                                $discount = $product['promotion_pourcentage'];
                                if ($discount > 0):
                                    $discountedPrice = $price * (1 - $discount / 100);
                                ?>
                                    <span class="wishlist-price-current"><?php echo formatPrice($discountedPrice); ?></span>
                                    <span class="wishlist-price-original"><?php echo formatPrice($price); ?></span>
                                    <span class="wishlist-discount">-<?php echo $discount; ?>%</span>
                                <?php else: ?>
                                    <span class="wishlist-price-current"><?php echo formatPrice($price); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Actions - BOUTON PANIER UNIQUEMENT -->
                            <div class="wishlist-actions">
                                <button class="wishlist-btn-cart" data-product-id="<?php echo $product['id_produit']; ?>">
                                    <i class="fas fa-shopping-cart"></i> Ajouter au panier
                                </button>
                            </div>
                            
                            <!-- Date d'ajout -->
                            <div class="wishlist-date">
                                Ajouté le <?php echo date('d/m/Y', strtotime($product['date_favori'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ========================================
    // SUPPRIMER DES FAVORIS - CORRIGÉ
    // ========================================
    document.querySelectorAll('.wishlist-remove').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const productId = this.getAttribute('data-product-id');
            const card = this.closest('.wishlist-card');
            
            if (!confirm('Voulez-vous vraiment retirer ce produit de vos favoris ?')) {
                return;
            }
            
            // Animation de suppression
            card.style.opacity = '0.5';
            card.style.pointerEvents = 'none';
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.disabled = true;
            
            // ✅ CORRIGÉ : Virgule APRÈS l'URL, pas avant l'accolade
            fetch('toggle_wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + encodeURIComponent(productId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.action === 'removed') {
                    // Animation fluide de suppression
                    card.style.transform = 'scale(0.8)';
                    card.style.opacity = '0';
                    
                    setTimeout(() => {
                        card.remove();
                        
                        // Vérifier s'il reste des produits
                        const remainingCards = document.querySelectorAll('.wishlist-card');
                        if (remainingCards.length === 0) {
                            location.reload(); // Recharger pour afficher le message "liste vide"
                        } else {
                            // Mettre à jour le compteur dans le header
                            const headerCount = document.querySelector('.wishlist-header p');
                            if (headerCount) {
                                const count = remainingCards.length;
                                headerCount.textContent = count + ' produit' + (count > 1 ? 's' : '') + ' dans votre liste';
                            }
                        }
                    }, 400);
                    
                    // Mettre à jour le compteur wishlist du header principal
                    const wishlistCount = document.querySelector('.wishlist-count');
                    if (wishlistCount) {
                        wishlistCount.textContent = data.wishlist_count;
                    }
                } else {
                    alert(data.message || 'Erreur lors de la suppression');
                    card.style.opacity = '1';
                    card.style.pointerEvents = 'auto';
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-times"></i>';
                }
            })
            .catch(err => {
                console.error('Erreur:', err);
                alert('Erreur réseau. Veuillez réessayer.');
                card.style.opacity = '1';
                card.style.pointerEvents = 'auto';
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-times"></i>';
            });
        });
    });
    
    // ========================================
    // AJOUTER AU PANIER - CORRIGÉ
    // ========================================
    document.querySelectorAll('.wishlist-btn-cart').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const productId = this.getAttribute('data-product-id');
            const originalHTML = this.innerHTML;
            
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
            this.disabled = true;
            this.style.cursor = 'wait';
            
            // ✅ CORRIGÉ : URL changée vers add-to-cart.php et virgule bien placée
            fetch('add-to-cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + encodeURIComponent(productId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Animation de succès
                    this.innerHTML = '<i class="fas fa-check"></i> Ajouté !';
                    this.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                    
                    // Mettre à jour le compteur panier
                    const cartCount = document.querySelector('.cart-count');
                    if (cartCount) {
                        cartCount.textContent = data.cart_count;
                        
                        // Animation du compteur
                        cartCount.style.transform = 'scale(1.3)';
                        setTimeout(() => {
                            cartCount.style.transform = 'scale(1)';
                        }, 300);
                    }
                    
                    // Retour à l'état normal après 2.5 secondes
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.style.background = '';
                        this.style.cursor = 'pointer';
                        this.disabled = false;
                    }, 2500);
                } else {
                    this.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erreur';
                    this.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                    alert(data.message || 'Erreur lors de l\'ajout au panier');
                    
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.style.background = '';
                        this.style.cursor = 'pointer';
                        this.disabled = false;
                    }, 2000);
                }
            })
            .catch(err => {
                console.error('Erreur:', err);
                this.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erreur réseau';
                this.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                    this.style.background = '';
                    this.style.cursor = 'pointer';
                    this.disabled = false;
                }, 2000);
            });
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>