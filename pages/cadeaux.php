<?php
// ============================================================
// PAGE CADEAUX - Coffrets Parfums Lumoura
// Ce fichier affiche la page cadeaux avec :
// - Gestion de l'ajout au panier
// - Affichage des bestsellers, nouveautés et promotions
// ============================================================

// Inclusion des fichiers de configuration, base de données et fonctions
require_once '../includes/config.php';  // Contient les constantes (SITE_URL, DB_NAME...)
require_once '../includes/db.php';      // Connexion à la BDD via $pdo
require_once '../includes/functions.php'; // Fonctions utilitaires (isLoggedIn, formatPrice...)

// ============================================================
// TRAITEMENT DU FORMULAIRE D'AJOUT AU PANIER
// S'exécute quand l'utilisateur clique sur "Ajouter"
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    
    // Vérifier que l'utilisateur est connecté avant d'ajouter au panier
    if (!isLoggedIn()) {
        $_SESSION['login_redirect'] = 'Veuillez vous connecter pour ajouter un article au panier.';
        header('Location: connexion.php'); // Redirection vers la page de connexion
        exit();
    }

    // Récupérer les données envoyées par le formulaire
    $user_id    = $_SESSION['user_id'];              // ID de l'utilisateur connecté
    $product_id = intval($_POST['product_id'] ?? 0); // ID du produit (converti en entier pour sécurité)
    $quantity   = intval($_POST['quantity'] ?? 1);   // Quantité (1 par défaut)

    if ($product_id > 0) {
        try {
            // Vérifier si ce produit est déjà dans le panier de cet utilisateur
            $checkQuery = "SELECT * FROM panier WHERE id_utilisateur = ? AND id_produit = ?";
            $checkStmt  = $pdo->prepare($checkQuery);
            $checkStmt->execute([$user_id, $product_id]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                // Le produit existe déjà → on incrémente la quantité de 1
                $pdo->prepare("UPDATE panier SET quantite = quantite + 1 WHERE id_panier = ?")
                    ->execute([$existing['id_panier']]);
            } else {
                // Le produit n'existe pas encore → on l'insère dans le panier
                $pdo->prepare("INSERT INTO panier (id_utilisateur, id_produit, quantite) VALUES (?, ?, ?)")
                    ->execute([$user_id, $product_id, $quantity]);
            }

            // Message de succès stocké en session (affiché après redirection)
            $_SESSION['cart_message'] = 'Produit ajouté au panier !';

        } catch (PDOException $e) {
            // En cas d'erreur SQL, on affiche un message d'erreur
            $_SESSION['cart_message'] = 'Erreur lors de l\'ajout.';
        }
    }

    // Redirection après traitement (évite la re-soumission du formulaire si on rafraîchit)
    header('Location: cadeaux.php');
    exit();
}

// ============================================================
// RÉCUPÉRATION DES PRODUITS DEPUIS LA BASE DE DONNÉES
// On récupère 3 catégories de produits pour les afficher
// ============================================================
try {
    // Bestsellers : produits avec bestseller=1 ET stock > 0, limité à 3 résultats
    $bestsellers = $pdo->query("SELECT * FROM produits WHERE bestseller = 1 AND stock > 0 LIMIT 3")->fetchAll();

    // Nouveautés : produits marqués comme nouveaux ET en stock, limité à 3
    $nouveautes  = $pdo->query("SELECT * FROM produits WHERE nouveaute = 1 AND stock > 0 LIMIT 3")->fetchAll();

    // Promotions : produits avec une réduction ET en stock, limité à 3
    $promos      = $pdo->query("SELECT * FROM produits WHERE promotion_pourcentage > 0 AND stock > 0 LIMIT 3")->fetchAll();

} catch (PDOException $e) {
    // En cas d'erreur, on initialise des tableaux vides pour éviter les erreurs d'affichage
    $bestsellers = $nouveautes = $promos = [];
}

// Définir le titre de la page (utilisé dans le <title> du header)
$pageTitle = "Coffrets Cadeaux - Lumoura";

// Inclusion du header (navbar + balises HTML d'ouverture)
include '../includes/header.php';
?>

<!-- ============================================================
     CSS SPÉCIFIQUE À LA PAGE CADEAUX
     Ces styles s'appliquent uniquement à cette page
     ============================================================ -->
<style>
/* Variables CSS réutilisables dans toute la page */
:root {
    --gold: #d4a017;          /* Couleur dorée principale */
    --gold-light: #f4e4c1;    /* Dorée claire pour les dégradés */
    --deep-brown: #3a2c1f;    /* Brun foncé pour les textes */
    --cream: #f8f5f0;         /* Fond crème clair */
}

/* ---- SECTION HERO (bandeau principal en haut) ---- */
.cadeaux-hero {
    background: linear-gradient(135deg, #2c1810 0%, #3a2c1f 50%, #4a3728 100%);
    padding: 120px 0 80px; /* Espace en haut pour la navbar fixe */
    text-align: center;
    position: relative;
    overflow: hidden; /* Cache ce qui dépasse */
}

/* Photo de fond du hero (semi-transparente via opacity) */
.cadeaux-hero::before {
    content: '';
    position: absolute;
    inset: 0; /* Couvre tout le parent */
    background: url('https://images.unsplash.com/photo-1615634260167-c8cdede054de?auto=format&fit=crop&w=1600&q=80') center/cover no-repeat;
    opacity: 0.15; /* Très transparent pour ne pas cacher le texte */
}

/* Le contenu du hero passe au-dessus de la photo (z-index) */
.cadeaux-hero-content {
    position: relative;
    z-index: 2;
}

/* Petit label doré au-dessus du titre */
.cadeaux-hero .hero-label {
    display: inline-block;
    color: var(--gold);
    font-size: 0.85em;
    letter-spacing: 5px;      /* Espacement des lettres pour effet luxe */
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 20px;
}

/* Titre principal du hero */
.cadeaux-hero h1 {
    font-family: 'Playfair Display', serif; /* Police élégante */
    font-size: 3.5em;
    color: white;
    font-weight: 700;
    margin-bottom: 20px;
    line-height: 1.2;
}

/* Le mot en doré dans le titre */
.cadeaux-hero h1 span { color: var(--gold); }

/* Sous-titre du hero */
.cadeaux-hero p {
    font-size: 1.2em;
    color: rgba(255,255,255,0.75); /* Blanc semi-transparent */
    max-width: 600px;
    margin: 0 auto 40px;
    line-height: 1.7;
}

/* Ligne décorative dorée sous le titre */
.hero-divider {
    width: 60px;
    height: 3px;
    background: linear-gradient(to right, var(--gold), var(--gold-light));
    margin: 0 auto 30px;
    border-radius: 2px;
}

/* ---- BARRE DE GARANTIES ---- */
.garanties-bar {
    background: white;
    border-bottom: 1px solid #f0ebe2;
    padding: 20px 0;
}

.garanties-bar .container {
    display: flex;
    justify-content: center;
    gap: 50px;
    flex-wrap: wrap; /* S'adapte sur mobile */
}

/* Chaque élément de garantie (icône + texte) */
.garantie-item {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.95em;
    color: var(--deep-brown);
    font-weight: 500;
}
.garantie-item i { color: var(--gold); font-size: 1.4em; }

/* ---- SECTIONS COFFRETS ---- */
.coffrets-section { padding: 70px 0; }

/* Les sections paires ont un fond crème (alternance visuelle) */
.coffrets-section:nth-child(even) { background: var(--cream); }

/* En-tête de chaque section (titre + sous-titre) */
.section-header { text-align: center; margin-bottom: 50px; }

/* Badge coloré au-dessus des titres de section */
.section-label {
    display: inline-block;
    background: linear-gradient(135deg, var(--gold), #c29b15);
    color: white;
    padding: 6px 20px;
    border-radius: 20px;
    font-size: 0.8em;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 15px;
}

.section-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: 2.2em;
    color: var(--deep-brown);
    margin-bottom: 10px;
}

.section-header p { color: #777; font-size: 1.05em; max-width: 500px; margin: 0 auto; }

/* ---- GRILLE DES CARTES PRODUITS ---- */
/* auto-fill = autant de colonnes que possible, min 300px chacune */
.coffret-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
}

/* ---- CARTE PRODUIT INDIVIDUELLE ---- */
.coffret-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0,0,0,0.07);
    transition: all 0.35s ease; /* Animation fluide au survol */
    border: 2px solid transparent; /* Bordure invisible par défaut */
    cursor: pointer;
    position: relative; /* Pour positionner le ribbon */
}

/* Au survol : la carte se lève et affiche une bordure dorée */
.coffret-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(212,160,23,0.18);
    border-color: var(--gold);
}

/* Ruban coloré en haut à droite (ex: "Best-Seller", "Nouveau") */
.coffret-card .ribbon {
    position: absolute;
    top: 18px;
    right: -5px;
    background: linear-gradient(135deg, var(--gold), #c29b15);
    color: white;
    padding: 6px 16px 6px 12px;
    font-size: 0.8em;
    font-weight: 700;
    border-radius: 4px 0 0 4px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.2);
    z-index: 10;
}

/* Petit triangle pour faire l'effet "ruban" à droite */
.coffret-card .ribbon::after {
    content: '';
    position: absolute;
    right: -8px;
    top: 0;
    border: 14px solid transparent;
    border-left: 8px solid #c29b15;
    border-top: 14px solid #c29b15;
}

/* Conteneur de l'image */
.coffret-img { height: 240px; overflow: hidden; position: relative; }

.coffret-img img {
    width: 100%; height: 100%;
    object-fit: cover; /* L'image couvre tout sans se déformer */
    transition: transform 0.5s ease;
}

/* L'image zoome légèrement au survol de la carte */
.coffret-card:hover .coffret-img img { transform: scale(1.06); }

/* Dégradé sombre en bas de l'image pour améliorer la lisibilité */
.coffret-img .overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, transparent 60%, rgba(0,0,0,0.2));
}

/* Corps de la carte (texte + prix + bouton) */
.coffret-body { padding: 22px; }

/* Ligne marque + volume en petit gris */
.coffret-meta { font-size: 0.8em; color: #aaa; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
.coffret-meta i { color: var(--gold); }

/* Nom du produit */
.coffret-name {
    font-family: 'Playfair Display', serif;
    font-size: 1.25em;
    color: var(--deep-brown);
    font-weight: 700;
    margin-bottom: 8px;
    line-height: 1.3;
}

/* Description courte du produit */
.coffret-desc { font-size: 0.88em; color: #888; margin-bottom: 16px; line-height: 1.6; }

/* Pied de carte : prix à gauche, bouton à droite */
.coffret-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; }

/* Prix normal */
.coffret-price { font-size: 1.5em; font-weight: 800; color: var(--deep-brown); }

/* Prix en promotion (rouge) */
.coffret-price.promo { color: #e63946; }

/* Ancien prix barré */
.coffret-price-old { font-size: 0.9em; color: #bbb; text-decoration: line-through; display: block; line-height: 1; }

/* Bouton Ajouter au panier */
.btn-add-cart {
    background: linear-gradient(135deg, var(--gold), #c29b15);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.92em;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(212,160,23,0.3);
    white-space: nowrap; /* Empêche le texte de passer à la ligne */
}
.btn-add-cart:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(212,160,23,0.45); }
.btn-add-cart:disabled { background: #bdc3c7; box-shadow: none; cursor: not-allowed; transform: none; }

/* ---- COFFRET VEDETTE (section noire mise en avant) ---- */
.coffret-vedette {
    background: linear-gradient(135deg, var(--deep-brown) 0%, #2c1810 100%);
    border-radius: 20px;
    padding: 50px;
    display: grid;
    grid-template-columns: 1fr 1fr; /* 2 colonnes égales */
    gap: 50px;
    align-items: center;
    margin-bottom: 60px;
    position: relative;
    overflow: hidden;
}

/* Emoji décoratif géant en arrière-plan (très transparent) */
.coffret-vedette::before {
    content: '🎁';
    position: absolute;
    font-size: 200px;
    right: -30px; top: -30px;
    opacity: 0.05;
}

.coffret-vedette-text h2 { font-family: 'Playfair Display', serif; color: white; font-size: 2.2em; margin-bottom: 15px; }
.coffret-vedette-text h2 span { color: var(--gold); }
.coffret-vedette-text p { color: rgba(255,255,255,0.7); line-height: 1.8; margin-bottom: 25px; font-size: 1.05em; }

/* Image du coffret vedette */
.coffret-vedette-img { border-radius: 16px; overflow: hidden; height: 300px; box-shadow: 0 20px 50px rgba(0,0,0,0.4); }
.coffret-vedette-img img { width: 100%; height: 100%; object-fit: cover; }

/* Bouton doré du coffret vedette */
.btn-vedette {
    display: inline-flex; align-items: center; gap: 10px;
    background: var(--gold); color: white;
    padding: 15px 35px; border-radius: 12px;
    text-decoration: none; font-weight: 700; font-size: 1.05em;
    transition: all 0.3s; border: none; cursor: pointer;
    box-shadow: 0 6px 20px rgba(212,160,23,0.4);
}
.btn-vedette:hover { background: #c29b15; transform: translateY(-2px); color: white; }

/* ---- PILLS DE FILTRES (boutons ronds) ---- */
.budget-pills { display: flex; justify-content: center; gap: 15px; flex-wrap: wrap; margin-bottom: 40px; }

.budget-pill {
    padding: 10px 25px; border-radius: 50px;
    border: 2px solid var(--gold);
    color: var(--deep-brown); font-weight: 600; font-size: 0.95em;
    cursor: pointer; transition: all 0.3s;
    background: white; text-decoration: none;
}
.budget-pill:hover, .budget-pill.active { background: var(--gold); color: white; }

/* ---- NOTIFICATION D'AJOUT AU PANIER ---- */
/* Apparaît en haut à droite après l'ajout d'un produit */
.cart-alert {
    position: fixed;
    top: 100px; right: 30px;
    background: linear-gradient(135deg, #2a9d8f, #27ae60);
    color: white; padding: 15px 25px;
    border-radius: 12px; font-weight: 600;
    z-index: 9999;
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    animation: slideIn 0.4s ease;
    display: none; /* Caché par défaut, affiché par JS */
}

/* Animation d'entrée de la notification */
@keyframes slideIn {
    from { transform: translateX(100px); opacity: 0; }
    to   { transform: translateX(0); opacity: 1; }
}

/* ---- SECTION EMBALLAGE CADEAU ---- */
.emballage-section { background: linear-gradient(135deg, #f8f5f0, #f0ebe2); padding: 60px 0; text-align: center; }

.emballage-features { display: flex; justify-content: center; gap: 40px; flex-wrap: wrap; margin-top: 40px; }

/* Chaque carte d'avantage (écrin, message, etc.) */
.emballage-feature {
    background: white; padding: 30px 25px;
    border-radius: 16px; width: 220px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.06);
    transition: all 0.3s; border: 2px solid transparent;
}
.emballage-feature:hover { border-color: var(--gold); transform: translateY(-5px); }
.emballage-feature i { font-size: 2.5em; color: var(--gold); margin-bottom: 15px; }
.emballage-feature h4 { font-weight: 700; color: var(--deep-brown); margin-bottom: 8px; font-size: 1em; }
.emballage-feature p { font-size: 0.85em; color: #888; line-height: 1.5; margin: 0; }
</style>

<!-- ============================================================
     NOTIFICATION PANIER
     Affichée après un ajout réussi, disparaît automatiquement
     ============================================================ -->
<?php if (isset($_SESSION['cart_message'])): ?>
<div class="cart-alert" id="cartAlert">
    <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
    <?php echo htmlspecialchars($_SESSION['cart_message']); ?>
</div>
<?php unset($_SESSION['cart_message']); // On supprime le message après affichage ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const alert = document.getElementById('cartAlert');
        if (alert) {
            alert.style.display = 'block';                          // Afficher
            setTimeout(() => alert.style.opacity = '0', 2500);     // Commencer à disparaître après 2.5s
            setTimeout(() => alert.style.display = 'none', 3000);  // Cacher complètement après 3s
        }
    });
</script>
<?php endif; ?>

<!-- ============================================================
     SECTION HERO - Bandeau principal
     ============================================================ -->
<div class="cadeaux-hero">
    <div class="cadeaux-hero-content container">
        <!-- Petit label au-dessus du titre -->
        <span class="hero-label"><i class="fas fa-gift" style="margin-right:8px;"></i>Lumoura — L'Art d'Offrir</span>
        
        <!-- Titre principal avec mot en doré -->
        <h1>Offrez le <span>parfum parfait</span></h1>
        
        <!-- Séparateur décoratif doré -->
        <div class="hero-divider"></div>
        
        <!-- Sous-titre -->
        <p>Des coffrets soigneusement composés pour chaque occasion. Livraison en écrin luxueux incluse.</p>
        
        <!-- Bouton qui fait défiler vers la section coffrets (ancre #coffrets) -->
        <a href="#coffrets" style="
            display: inline-flex; align-items: center; gap: 10px;
            background: var(--gold); color: white;
            padding: 16px 40px; border-radius: 12px;
            text-decoration: none; font-weight: 700; font-size: 1.1em;
            transition: all 0.3s; box-shadow: 0 6px 20px rgba(212,160,23,0.4);
        ">
            <i class="fas fa-gift"></i> Découvrir nos coffrets
        </a>
    </div>
</div>

<!-- ============================================================
     BARRE DE GARANTIES - Icônes sous le hero
     ============================================================ -->
<div class="garanties-bar">
    <div class="container">
        <div class="garantie-item"><i class="fas fa-box-open"></i> Emballage cadeau offert</div>
        <div class="garantie-item"><i class="fas fa-truck"></i> Livraison en 24-48h</div>
        <div class="garantie-item"><i class="fas fa-undo"></i> Retour 30 jours</div>
        <div class="garantie-item"><i class="fas fa-shield-alt"></i> Paiement sécurisé</div>
        <div class="garantie-item"><i class="fas fa-leaf"></i> Échantillons offerts</div>
    </div>
</div>

<!-- ============================================================
     SECTION COFFRETS PRINCIPAUX
     id="coffrets" permet au bouton du hero de défiler ici
     ============================================================ -->
<div id="coffrets" class="coffrets-section">
    <div class="container">

        <!-- COFFRET VEDETTE (mise en avant luxueuse) -->
        <div class="coffret-vedette">
            <div class="coffret-vedette-text">
                <span class="section-label" style="margin-bottom:20px; display:inline-block;">⭐ Coup de cœur</span>
                <h2>Le Coffret <span>Prestige</span></h2>
                <p>Notre sélection exclusive de 3 parfums d'exception, présentée dans un écrin en velours noir.</p>
                
                <!-- Liste des avantages du coffret -->
                <div style="display:flex; gap:20px; margin-bottom:25px; flex-wrap:wrap;">
                    <div style="color:white; font-size:0.9em;"><i class="fas fa-check" style="color:var(--gold); margin-right:6px;"></i>3 parfums 100ml</div>
                    <div style="color:white; font-size:0.9em;"><i class="fas fa-check" style="color:var(--gold); margin-right:6px;"></i>Écrin velours offert</div>
                    <div style="color:white; font-size:0.9em;"><i class="fas fa-check" style="color:var(--gold); margin-right:6px;"></i>Carte message personnalisée</div>
                </div>
                
                <!-- Lien vers les bestsellers pour composer son coffret -->
                <a href="catalogue.php?filter=bestseller" class="btn-vedette">
                    <i class="fas fa-shopping-bag"></i> Composer mon coffret
                </a>
            </div>
            
            <!-- Image du coffret prestige -->
            <div class="coffret-vedette-img">
                <img src="https://images.unsplash.com/photo-1590736704728-f4730bb30770?auto=format&fit=crop&w=800&q=80" alt="Coffret Prestige Lumoura">
            </div>
        </div>

        <!-- En-tête de la section bestsellers -->
        <div class="section-header">
            <span class="section-label">🎁 Nos Coffrets</span>
            <h2>Parfums Best-Sellers à Offrir</h2>
            <p>Nos parfums les plus appréciés, idéaux pour faire un cadeau qui marquera les esprits</p>
        </div>

        <!-- Boutons de navigation rapide vers les catégories -->
        <div class="budget-pills">
            <a href="catalogue.php?filter=bestseller" class="budget-pill">⭐ Best-Sellers</a>
            <a href="catalogue.php?filter=new" class="budget-pill">✨ Nouveautés</a>
            <a href="catalogue.php?filter=promo" class="budget-pill">🏷️ Promotions</a>
            <a href="catalogue.php?category=Femme" class="budget-pill">♀️ Pour Elle</a>
            <a href="catalogue.php?category=Homme" class="budget-pill">♂️ Pour Lui</a>
        </div>

        <!-- Grille des bestsellers (si la BDD en contient) -->
        <?php if (!empty($bestsellers)): // Affiche la section seulement si des bestsellers existent en BDD ?>
        <div class="coffret-grid">
            <?php foreach ($bestsellers as $p):
                // Calcul du prix final selon la promotion
                $price    = (float)$p['prix'];
                $discount = (float)$p['promotion_pourcentage'];
                $final    = $discount > 0 ? $price * (1 - $discount / 100) : $price;

                // Image de secours si pas d'image en BDD
                $image    = $p['image_url'] ?: 'https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=800&q=80';
            ?>
            <!-- onclick rend toute la carte cliquable vers la fiche produit -->
            <div class="coffret-card" onclick="window.location='produit.php?id=<?php echo $p['id_produit']; ?>'">
                
                <!-- Ruban "Best-Seller" -->
                <div class="ribbon">Best-Seller</div>
                
                <!-- Image du produit -->
                <div class="coffret-img">
                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($p['nom']); ?>">
                    <div class="overlay"></div>
                </div>
                
                <!-- Contenu texte de la carte -->
                <div class="coffret-body">
                    <!-- Marque + volume -->
                    <div class="coffret-meta">
                        <i class="fas fa-tag"></i><?php echo htmlspecialchars($p['marque']); ?>
                        <span style="color:#ddd;">•</span>
                        <i class="fas fa-wine-bottle"></i><?php echo $p['volume_ml']; ?> ml
                    </div>
                    
                    <!-- Nom du produit -->
                    <div class="coffret-name"><?php echo htmlspecialchars($p['nom']); ?></div>
                    
                    <!-- Description tronquée à 80 caractères -->
                    <div class="coffret-desc">
                        <?php echo htmlspecialchars(mb_substr($p['description_courte'] ?? '', 0, 80)) . '...'; ?>
                    </div>
                    
                    <!-- Pied de carte : prix + bouton (stopPropagation évite le clic sur la carte) -->
                    <div class="coffret-footer" onclick="event.stopPropagation()">
                        <div>
                            <?php if ($discount > 0): ?>
                                <!-- Affichage prix barré + prix réduit -->
                                <span class="coffret-price-old"><?php echo formatPrice($price); ?></span>
                                <span class="coffret-price promo"><?php echo formatPrice($final); ?></span>
                            <?php else: ?>
                                <!-- Prix normal -->
                                <span class="coffret-price"><?php echo formatPrice($final); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Formulaire d'ajout au panier -->
                        <form method="POST" action="cadeaux.php" onclick="event.stopPropagation()">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?php echo $p['id_produit']; ?>">
                            <input type="hidden" name="quantity" value="1">
                            <!-- Bouton désactivé si le produit est en rupture de stock -->
                            <!-- disabled si stock = 0 : attribut HTML qui désactive le bouton -->
                            <button type="submit" class="btn-add-cart" <?php echo $p['stock'] == 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-shopping-cart"></i>
                                <?php echo $p['stock'] > 0 ? 'Ajouter' : 'Indispo'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php else: ?>
            <!-- Message si aucun bestseller en BDD -->
            <div style="text-align:center; padding:40px; color:#888;">
                <i class="fas fa-flask" style="font-size:3em; color:#ddd; display:block; margin-bottom:15px;"></i>
                Aucun produit disponible pour le moment.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================
     SECTION NOUVEAUTÉS
     Affichée seulement si la BDD contient des nouveautés
     ============================================================ -->
<?php if (!empty($nouveautes)): // Affiche la section seulement si des nouveautés existent en BDD ?>
<div class="coffrets-section">
    <div class="container">
        <div class="section-header">
            <span class="section-label">✨ Nouveautés</span>
            <h2>Les Dernières Fragrances</h2>
            <p>Offrez une découverte olfactive unique avec nos toutes dernières créations</p>
        </div>
        <div class="coffret-grid">
            <?php foreach ($nouveautes as $p):
                $price = (float)$p['prix'];
                $image = $p['image_url'] ?: 'https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=800&q=80';
            ?>
            <div class="coffret-card" onclick="window.location='produit.php?id=<?php echo $p['id_produit']; ?>'">
                <!-- Ruban vert pour les nouveautés -->
                <div class="ribbon" style="background: linear-gradient(135deg, #2a9d8f, #27ae60);">Nouveau</div>
                <div class="coffret-img">
                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($p['nom']); ?>">
                    <div class="overlay"></div>
                </div>
                <div class="coffret-body">
                    <div class="coffret-meta">
                        <i class="fas fa-tag"></i><?php echo htmlspecialchars($p['marque']); ?>
                        <span style="color:#ddd;">•</span>
                        <i class="fas fa-venus-mars"></i><?php echo htmlspecialchars($p['genre']); ?>
                    </div>
                    <div class="coffret-name"><?php echo htmlspecialchars($p['nom']); ?></div>
                    <!-- mb_substr() : coupe la description à 80 caractères (mb_ = compatible accents/utf8) -->
                    <div class="coffret-desc"><?php echo htmlspecialchars(mb_substr($p['description_courte'] ?? '', 0, 80)) . '...'; ?></div>
                    <div class="coffret-footer" onclick="event.stopPropagation()">
                        <span class="coffret-price"><?php echo formatPrice($price); ?></span>
                        <form method="POST" action="cadeaux.php" onclick="event.stopPropagation()">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?php echo $p['id_produit']; ?>">
                            <input type="hidden" name="quantity" value="1">
                            <!-- disabled si stock = 0 : attribut HTML qui désactive le bouton -->
                            <button type="submit" class="btn-add-cart" <?php echo $p['stock'] == 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-shopping-cart"></i>
                                <?php echo $p['stock'] > 0 ? 'Ajouter' : 'Indispo'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     SECTION PROMOTIONS
     Affichée seulement si la BDD contient des produits en promo
     ============================================================ -->
<?php if (!empty($promos)): // Affiche la section seulement si des produits en promo existent en BDD ?>
<div class="coffrets-section">
    <div class="container">
        <div class="section-header">
            <!-- Badge rouge pour les promotions -->
            <span class="section-label" style="background: linear-gradient(135deg, #e63946, #ff6b6b);">🏷️ Promotions</span>
            <h2>Offrir à Prix Doux</h2>
            <p>Des parfums d'exception en promotion — idéals pour un cadeau généreux sans se ruiner</p>
        </div>
        <div class="coffret-grid">
            <?php foreach ($promos as $p):
                $price    = (float)$p['prix'];
                $discount = (float)$p['promotion_pourcentage'];
                // Calcul prix final : prix de base × (1 - taux_reduction / 100)
                // Exemple : 100€ avec 20% → 100 × (1 - 0.2) = 80€
                $final    = $price * (1 - $discount / 100);
                $image    = $p['image_url'] ?: 'https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=800&q=80';
            ?>
            <div class="coffret-card" onclick="window.location='produit.php?id=<?php echo $p['id_produit']; ?>'">
                <!-- Ruban rouge avec le pourcentage de réduction -->
                <div class="ribbon" style="background: linear-gradient(135deg, #e63946, #ff6b6b);">
                    -<?php echo number_format($discount, 0); ?>%
                </div>
                <div class="coffret-img">
                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($p['nom']); ?>">
                    <div class="overlay"></div>
                </div>
                <div class="coffret-body">
                    <div class="coffret-meta">
                        <i class="fas fa-tag"></i><?php echo htmlspecialchars($p['marque']); ?>
                        <span style="color:#ddd;">•</span>
                        <i class="fas fa-venus-mars"></i><?php echo htmlspecialchars($p['genre']); ?>
                    </div>
                    <div class="coffret-name"><?php echo htmlspecialchars($p['nom']); ?></div>
                    <!-- mb_substr() : coupe la description à 80 caractères (mb_ = compatible accents/utf8) -->
                    <div class="coffret-desc"><?php echo htmlspecialchars(mb_substr($p['description_courte'] ?? '', 0, 80)) . '...'; ?></div>
                    <div class="coffret-footer" onclick="event.stopPropagation()">
                        <div>
                            <!-- Prix original barré + prix réduit en rouge -->
                            <span class="coffret-price-old"><?php echo formatPrice($price); ?></span>
                            <span class="coffret-price promo"><?php echo formatPrice($final); ?></span>
                        </div>
                        <form method="POST" action="cadeaux.php" onclick="event.stopPropagation()">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?php echo $p['id_produit']; ?>">
                            <input type="hidden" name="quantity" value="1">
                            <!-- disabled si stock = 0 : attribut HTML qui désactive le bouton -->
                            <button type="submit" class="btn-add-cart" <?php echo $p['stock'] == 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-shopping-cart"></i>
                                <?php echo $p['stock'] > 0 ? 'Ajouter' : 'Indispo'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     SECTION EMBALLAGE CADEAU
     Présente les avantages inclus avec chaque commande
     ============================================================ -->
<div class="emballage-section">
    <div class="container">
        <div class="section-header">
            <span class="section-label">🎀 Inclus avec votre commande</span>
            <h2>L'Art de l'Emballage</h2>
            <p>Chaque commande est préparée avec soin pour sublimer votre cadeau</p>
        </div>
        
        <!-- 4 avantages en cartes -->
        <div class="emballage-features">
            <div class="emballage-feature">
                <i class="fas fa-box-open"></i>
                <h4>Écrin luxueux</h4>
                <p>Boîte en velours noir avec ruban doré offerte pour chaque commande</p>
            </div>
            <div class="emballage-feature">
                <i class="fas fa-pen-fancy"></i>
                <h4>Message personnalisé</h4>
                <p>Ajoutez un mot doux sur une carte calligraphiée à la main</p>
            </div>
            <div class="emballage-feature">
                <i class="fas fa-leaf"></i>
                <h4>Échantillons offerts</h4>
                <p>2 à 3 échantillons de nos nouvelles fragrances glissés dans chaque colis</p>
            </div>
            <div class="emballage-feature">
                <i class="fas fa-truck"></i>
                <h4>Livraison express</h4>
                <p>Livraison en 24-48h pour ne jamais rater une occasion spéciale</p>
            </div>
        </div>

        <!-- Bouton vers le catalogue complet -->
        <div style="margin-top: 50px;">
            <a href="catalogue.php" style="
                display: inline-flex; align-items: center; gap: 10px;
                background: linear-gradient(135deg, var(--gold), #c29b15);
                color: white; padding: 16px 45px; border-radius: 12px;
                text-decoration: none; font-weight: 700; font-size: 1.1em;
                box-shadow: 0 6px 20px rgba(212,160,23,0.35); transition: all 0.3s;
            "
            onmouseover="this.style.transform='translateY(-2px)'"
            onmouseout="this.style.transform='translateY(0)'">
                <i class="fas fa-store"></i> Voir toute la collection
            </a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; // Inclusion du pied de page ?>