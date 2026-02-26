<?php  
// Ici tu peux mettre des définitions, constantes, etc. si besoin
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// ============================================
// COMPTEUR DU PANIER
// ============================================
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $count_query = "SELECT COUNT(*) as total FROM panier WHERE id_utilisateur = ?";
        $count_stmt = $pdo->prepare($count_query);
        $count_stmt->execute([$_SESSION['user_id']]);
        $cart_count = $count_stmt->fetch()['total'] ?? 0;
    } catch (PDOException $e) {
        $cart_count = 0;
    }
}

// ============================================
// COMPTEUR DES FAVORIS
// ============================================
$wishlist_count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $wl_query = "SELECT COUNT(*) as total FROM favoris WHERE id_utilisateur = ?";
        $wl_stmt = $pdo->prepare($wl_query);
        $wl_stmt->execute([$_SESSION['user_id']]);
        $wishlist_count = $wl_stmt->fetch()['total'] ?? 0;
    } catch (PDOException $e) {
        $wishlist_count = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Lumoura - Parfums de Luxe</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Playfair+Display:wght@400;700&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3.8 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" 
          rel="stylesheet" 
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" 
          crossorigin="anonymous">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- CSS principal -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>assets/images/logo.png">
</head>
<body>

    <!-- Navigation principale -->
    <nav class="lumoura-navbar navbar navbar-expand-lg fixed-top">
        <div class="container">
            <!-- Logo -->
            <a href="<?php echo SITE_URL; ?>pages/index.php" class="nav-logo">
                <span class="logo-text">LUMOURA</span>
                <span class="logo-subtext">Parfums d'Exception</span>
            </a>

            <!-- Burger pour mobile -->
            <button class="navbar-toggler mobile-menu-btn" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Menu principal + actions -->
            <div class="collapse navbar-collapse nav-menu" id="navMenu">
                <!-- Liens de navigation -->
                <div class="nav-links d-flex gap-4 mx-auto">
                    <a href="<?php echo SITE_URL; ?>pages/index.php" class="nav-link">
                        <i class="fas fa-home"></i> Accueil
                    </a>
                    <a href="<?php echo SITE_URL; ?>pages/catalogue.php" class="nav-link">
                        <i class="fas fa-store"></i> Catalogue
                    </a>
                    <a href="<?php echo SITE_URL; ?>pages/catalogue.php?category=Femme" class="nav-link">Femme</a>
                    <a href="<?php echo SITE_URL; ?>pages/catalogue.php?category=Homme" class="nav-link">Homme</a>
                    <a href="<?php echo SITE_URL; ?>pages/catalogue.php?category=Unisexe" class="nav-link">Unisexe</a>
                    
                    <!-- Dropdown Collections -->
                    <div class="nav-dropdown">
                        <a href="#" class="nav-link">
                            <i class="fas fa-star"></i> Collections <i class="fas fa-chevron-down small"></i>
                        </a>
                        <div class="dropdown-content">
                            <a href="<?php echo SITE_URL; ?>pages/catalogue.php?filter=new">Nouveautés</a>
                            <a href="<?php echo SITE_URL; ?>pages/catalogue.php?filter=bestseller">Best-sellers</a>
                            <a href="<?php echo SITE_URL; ?>pages/catalogue.php?filter=promo">Promotions</a>
                            <a href="<?php echo SITE_URL; ?>pages/catalogue.php?concentration=Parfum">Parfums Signature</a>
                        </div>
                    </div>
                    
                    <a href="<?php echo SITE_URL; ?>pages/cadeaux.php" class="nav-link">
                        <i class="fas fa-gift"></i> Cadeaux
                    </a>
                </div>

                <!-- Actions utilisateur (recherche, favoris, compte, panier) -->
                <div class="nav-actions d-flex align-items-center gap-4">
                    <!-- Recherche -->
                    <div class="search-container">
                        <button class="search-btn d-lg-none" id="searchToggle" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                        
                        <div class="search-box" id="searchBox">
                            <form action="<?php echo SITE_URL; ?>pages/catalogue.php" method="get" class="d-flex align-items-center w-100">
                                <div class="input-group">
                                    <input 
                                        type="search" 
                                        name="q" 
                                        id="searchInput"
                                        placeholder="Rechercher un parfum..." 
                                        autocomplete="off"
                                        class="form-control border-end-0"
                                        aria-label="Rechercher"
                                    >
                                    <button type="submit" class="btn btn-gold border-start-0">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Favoris / Wishlist -->
                    <a href="<?php echo SITE_URL; ?>pages/wishlist.php" class="wishlist-btn" title="Mes Favoris" style="position: relative;">
                        <i class="fas fa-heart"></i> Favoris
                        <span class="wishlist-count" id="wishlistCount" style="
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            background: #ef4444;
                            color: white;
                            font-size: 0.68em;
                            font-weight: 700;
                            min-width: 18px;
                            height: 18px;
                            border-radius: 50%;
                            padding: 0 4px;
                            margin-left: 5px;
                            line-height: 1;
                            vertical-align: middle;
                            <?php echo $wishlist_count == 0 ? 'display: none !important;' : ''; ?>
                        "><?php echo $wishlist_count; ?></span>
                    </a>
                    
                    <!-- Compte -->
                    <div class="user-dropdown">
                        <button class="user-btn">
                            <i class="fas fa-user"></i>
                        </button>
                        <div class="user-dropdown-content">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="<?php echo SITE_URL; ?>pages/compte.php"><i class="fas fa-user-circle"></i> Mon Compte</a>
                                <a href="<?php echo SITE_URL; ?>pages/commande.php"><i class="fas fa-shopping-bag"></i> Mes Commandes</a>
                                <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                                    <a href="<?php echo SITE_URL; ?>pages/admin/"><i class="fas fa-cog"></i> Administration</a>
                                <?php endif; ?>
                                <hr>
                                <a href="<?php echo SITE_URL; ?>pages/deconnexion.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                            <?php else: ?>
                                <a href="<?php echo SITE_URL; ?>pages/connexion.php"><i class="fas fa-sign-in-alt"></i> Connexion</a>
                                <a href="<?php echo SITE_URL; ?>pages/inscription.php"><i class="fas fa-user-plus"></i> Inscription</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Panier -->
                    <a href="<?php echo SITE_URL; ?>pages/panier.php" class="cart-btn">
                        <i class="fas fa-shopping-bag"></i>
                        <span class="cart-count" id="cartCount"><?php echo $cart_count; ?></span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenu principal -->
    <main class="main-content">

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" 
            crossorigin="anonymous"></script>

    <!-- Script navbar scroll -->
    <script>
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.lumoura-navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    </script>

    <!-- Script recherche toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('searchToggle');
            const searchBox = document.getElementById('searchBox');
            const searchInput = document.getElementById('searchInput');

            if (!toggleBtn || !searchBox || !searchInput) return;

            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                searchBox.classList.toggle('active');
                if (searchBox.classList.contains('active')) {
                    searchInput.focus();
                }
            });

            document.addEventListener('click', function(e) {
                if (!toggleBtn.contains(e.target) && !searchBox.contains(e.target)) {
                    searchBox.classList.remove('active');
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    searchBox.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>