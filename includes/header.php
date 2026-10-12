<?php
// ============================================================
// FICHIER : includes/header.php
// RÔLE    : En-tête commun à toutes les pages
//
// ⚠️  NE PAS appeler require_once config/db/functions ici :
//     chaque page les charge déjà AVANT d'inclure ce fichier.
// ============================================================

// ============================================
// COMPTEUR DU PANIER
// ============================================
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM panier WHERE id_utilisateur = ?");
        $count_stmt->execute([$_SESSION['user_id']]);
        $cart_count = (int)($count_stmt->fetch()['total'] ?? 0);
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
        $wl_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM favoris WHERE id_utilisateur = ?");
        $wl_stmt->execute([$_SESSION['user_id']]);
        $wishlist_count = (int)($wl_stmt->fetch()['total'] ?? 0);
    } catch (PDOException $e) {
        $wishlist_count = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?>Lumoura - Parfums de Luxe</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>assets/images/logo.png">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Playfair+Display:wght@400;700&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">

    <!-- CSS principal du site -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">

    <!-- Lien « Quiz » du menu -->
    <style>
        .nav-link-quiz { color: var(--gold, #b8860b) !important; font-weight: 600; }
        .nav-link-quiz:hover { color: var(--gold-light, #d4a017) !important; }
        .nav-link-quiz i { display: inline-block; transition: transform .5s cubic-bezier(.22,.8,.24,1); }
        .nav-link-quiz:hover i { transform: scale(1.25) rotate(14deg); }
    </style>
</head>
<body>

<!-- ============================================================
     NAVIGATION PRINCIPALE
     ============================================================ -->
<nav class="lumoura-navbar navbar navbar-expand-lg fixed-top">
    <div class="container">

        <!-- Logo -->
        <a href="<?php echo SITE_URL; ?>pages/index.php" class="nav-logo">
            <span class="logo-text">LUMOURA</span>
            <span class="logo-subtext">Parfums d'Exception</span>
        </a>

        <!-- Burger mobile -->
        <button class="navbar-toggler mobile-menu-btn"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navMenu"
                aria-controls="navMenu"
                aria-expanded="false"
                aria-label="Ouvrir le menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Menu principal -->
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
                <a href="<?php echo SITE_URL; ?>pages/quiz.php" class="nav-link nav-link-quiz">
                    <i class="fas fa-wand-magic-sparkles"></i> Quiz
                </a>
            </div>

            <!-- Actions utilisateur -->
            <div class="nav-actions d-flex align-items-center gap-4">

                <!-- Recherche -->
                <div class="search-container">
                    <button class="search-btn" id="searchToggle" type="button" aria-label="Rechercher">
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
                                    aria-label="Rechercher un parfum"
                                >
                                <button type="submit" class="btn btn-gold border-start-0">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Favoris -->
                <a href="<?php echo SITE_URL; ?>pages/wishlist.php" class="wishlist-btn" title="Mes Favoris">
                    <i class="fas fa-heart"></i> Favoris
                    <span class="wishlist-count" id="wishlistCount"
                        <?php echo $wishlist_count == 0 ? 'style="display:none;"' : ''; ?>>
                        <?php echo $wishlist_count; ?>
                    </span>
                </a>

                <!-- Compte -->
                <div class="user-dropdown">
                    <button class="user-btn" aria-label="Mon compte">
                        <i class="fas fa-user"></i>
                    </button>
                    <div class="user-dropdown-content">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="<?php echo SITE_URL; ?>pages/compte.php">
                                <i class="fas fa-user-circle"></i> Mon Compte
                            </a>
                            <a href="<?php echo SITE_URL; ?>pages/historique_commandes.php">
                                <i class="fas fa-shopping-bag"></i> Mes Commandes
                            </a>
                            <?php if (!empty($_SESSION['is_admin'])): ?>
                                <a href="<?php echo SITE_URL; ?>pages/admin/">
                                    <i class="fas fa-cog"></i> Administration
                                </a>
                            <?php endif; ?>
                            <hr>
                            <a href="<?php echo SITE_URL; ?>pages/deconnexion.php">
                                <i class="fas fa-sign-out-alt"></i> Déconnexion
                            </a>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>pages/connexion.php">
                                <i class="fas fa-sign-in-alt"></i> Connexion
                            </a>
                            <a href="<?php echo SITE_URL; ?>pages/inscription.php">
                                <i class="fas fa-user-plus"></i> Inscription
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Panier -->
                <a href="<?php echo SITE_URL; ?>pages/panier.php" class="cart-btn" title="Mon panier">
                    <i class="fas fa-shopping-bag"></i>
                    <span class="cart-count" id="cartCount"><?php echo $cart_count; ?></span>
                </a>

            </div><!-- /nav-actions -->
        </div><!-- /navbar-collapse -->
    </div><!-- /container -->
</nav>

<!-- ============================================================
     OUVERTURE DU CONTENU PRINCIPAL
     Fermé par </main> dans footer.php
     ============================================================ -->
<main class="main-content">

<!-- ============================================================
     SCRIPTS GLOBAUX
     (Bootstrap JS + scripts navbar/recherche)
     ============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>

<script>
// Effet scroll sur la navbar
window.addEventListener('scroll', function () {
    document.querySelector('.lumoura-navbar').classList.toggle('scrolled', window.scrollY > 50);
});

// Toggle barre de recherche
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn   = document.getElementById('searchToggle');
    const searchBox   = document.getElementById('searchBox');
    const searchInput = document.getElementById('searchInput');

    if (!toggleBtn || !searchBox) return;

    toggleBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        const isOpen = searchBox.classList.toggle('active');
        if (isOpen && searchInput) searchInput.focus();
    });

    // Fermer en cliquant ailleurs
    document.addEventListener('click', function (e) {
        if (!toggleBtn.contains(e.target) && !searchBox.contains(e.target)) {
            searchBox.classList.remove('active');
        }
    });

    // Fermer avec Échap
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') searchBox.classList.remove('active');
    });
});
</script>