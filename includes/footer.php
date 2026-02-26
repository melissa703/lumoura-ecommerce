</main>
    <!-- Fin du contenu principal -->
    
    <!-- Pied de page -->
    <footer class="footer">
        <div class="footer-top">
            <div class="footer-grid">
                <!-- Colonne 1 : Logo et description -->
                <div class="footer-column">
                    <div class="footer-logo">LUMOURA</div>
                    <p class="footer-description">
                        Depuis 1920, Lumoura crée des parfums d'exception qui racontent des histoires. 
                        Chaque fragrance est une œuvre d'art, mêlant tradition et innovation.
                    </p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-pinterest-p"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
                
                <!-- Colonne 2 : Navigation -->
                <div class="footer-column">
                    <h3 class="footer-title">Navigation</h3>
                    <div class="footer-links">
                        <a href="<?php echo SITE_URL; ?>">Accueil</a>
                        <a href="<?php echo SITE_URL; ?>pages/catalogue.php">Catalogue</a>
                        <a href="<?php echo SITE_URL; ?>pages/catalogue.php?category=femme">Parfums Femme</a>
                        <a href="<?php echo SITE_URL; ?>pages/catalogue.php?category=homme">Parfums Homme</a>
                        <a href="<?php echo SITE_URL; ?>pages/catalogue.php?category=unisexe">Parfums Unisexe</a>
                        <a href="<?php echo SITE_URL; ?>pages/catalogue.php?filter=promo">Promotions</a>
                    </div>
                </div>
                
                <!-- Colonne 3 : Informations -->
                <div class="footer-column">
                    <h3 class="footer-title">Informations</h3>
                    <div class="footer-links">
                        <a href="#">À propos de nous</a>
                        <a href="#">Livraison et retours</a>
                        <a href="#">Conditions générales</a>
                        <a href="#">Politique de confidentialité</a>
                        <a href="#">FAQ</a>
                        <a href="#">Contactez-nous</a>
                    </div>
                </div>
                
                <!-- Colonne 4 : Contact -->
                <div class="footer-column">
                    <h3 class="footer-title">Contact</h3>
                    <div class="contact-info">
                        <p><i class="fas fa-map-marker-alt"></i> 123 Avenue des Champs-Élysées, 75008 Paris</p>
                        <p><i class="fas fa-phone"></i> 01 23 45 67 89</p>
                        <p><i class="fas fa-envelope"></i> contact@lumoura.fr</p>
                        <p><i class="fas fa-clock"></i> Lun-Sam: 10h-19h</p>
                    </div>
                    
                    <!-- Newsletter -->
                    <div class="newsletter-form">
                        <h4>Newsletter</h4>
                        <p>Inscrivez-vous pour recevoir nos offres exclusives</p>
                        <form class="newsletter-subscribe">
                            <input type="email" placeholder="Votre email" required>
                            <button type="submit"><i class="fas fa-paper-plane"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Lumoura - Tous droits réservés. | Site créé avec passion pour les parfums d'exception.</p>
            <div class="payment-methods">
                <i class="fab fa-cc-visa"></i>
                <i class="fab fa-cc-mastercard"></i>
                <i class="fab fa-cc-paypal"></i>
                <i class="fab fa-cc-apple-pay"></i>
                <i class="fab fa-cc-amazon-pay"></i>
            </div>
        </div>
    </footer>
    
    <!-- Bouton retour en haut -->
    <button class="back-to-top" id="backToTop">
        <i class="fas fa-chevron-up"></i>
    </button>
    
    <!-- Scripts JavaScript -->
    <script src="<?php echo SITE_URL; ?>assets/js/script.js"></script>
    
    <script>
    // Bouton retour en haut
    const backToTop = document.getElementById('backToTop');
    
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTop.style.display = 'block';
        } else {
            backToTop.style.display = 'none';
        }
    });
    
    backToTop.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    
    // Ajout de styles pour le bouton retour en haut
    const backToTopStyles = `
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--gold);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            display: none;
            z-index: 1000;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .back-to-top:hover {
            background: var(--bronze);
            transform: translateY(-3px);
        }
        
        .payment-methods {
            margin-top: 20px;
            font-size: 24px;
            display: flex;
            gap: 15px;
            justify-content: center;
            color: var(--gold-light);
        }
        
        .contact-info p {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .newsletter-subscribe {
            display: flex;
            margin-top: 10px;
        }
        
        .newsletter-subscribe input {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 3px 0 0 3px;
        }
        
        .newsletter-subscribe button {
            background: var(--gold);
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 0 3px 3px 0;
            cursor: pointer;
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 40px 0;
        }
        
        .category-card {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .category-image {
            height: 300px;
            overflow: hidden;
        }
        
        .category-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .category-card:hover .category-image img {
            transform: scale(1.1);
        }
        
        .category-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(44, 24, 16, 0.8);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .category-content h3 {
            color: var(--gold-light);
            margin-bottom: 10px;
        }
        
        .about-section {
            background: linear-gradient(135deg, var(--cream), var(--dark-cream));
            padding: 80px 0;
            margin-top: 80px;
        }
        
        .about-content {
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .about-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        .feature {
            text-align: center;
            padding: 20px;
        }
        
        .feature i {
            font-size: 40px;
            color: var(--gold);
            margin-bottom: 15px;
        }
        
        .catalogue-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 40px;
            margin-top: 40px;
        }
        
        .filters-sidebar {
            background: var(--white);
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            position: sticky;
            top: 100px;
            height: fit-content;
        }
        
        .filter-group {
            margin-bottom: 30px;
        }
        
        .filter-title {
            font-size: 18px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-light);
            color: var(--deep-brown);
        }
        
        .filter-list {
            list-style: none;
        }
        
        .filter-list li {
            margin-bottom: 8px;
        }
        
        .filter-list a {
            display: block;
            padding: 8px 0;
            color: var(--gray);
            transition: var(--transition);
            border-left: 3px solid transparent;
            padding-left: 10px;
        }
        
        .filter-list a:hover,
        .filter-list a.active {
            color: var(--gold);
            border-left-color: var(--gold);
        }
        
        .products-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
        }
        
        .sort-select {
            padding: 10px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 5px;
            font-family: 'Montserrat', sans-serif;
            background: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 50px;
            padding: 20px 0;
        }
        
        .page-link {
            padding: 10px 20px;
            background: var(--white);
            border: 1px solid var(--gray-light);
            border-radius: 5px;
            color: var(--deep-brown);
            transition: var(--transition);
        }
        
        .page-link:hover {
            background: var(--gold);
            color: white;
            border-color: var(--gold);
        }
        
        .page-number {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            background: var(--white);
            border: 1px solid var(--gray-light);
            transition: var(--transition);
        }
        
        .page-number.active {
            background: var(--gold);
            color: white;
            border-color: var(--gold);
        }
        
        .page-number:hover:not(.active) {
            background: var(--cream);
        }
        
        .page-dots {
            padding: 0 10px;
        }
        
        .breadcrumb {
            margin: 100px 0 30px;
            color: var(--gray);
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: var(--gold);
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .no-products {
            text-align: center;
            padding: 80px 20px;
            color: var(--gray);
        }
        
        .no-products i {
            font-size: 60px;
            color: var(--gray-light);
            margin-bottom: 20px;
        }
        
        .error-message {
            text-align: center;
            padding: 40px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 10px;
            margin: 20px 0;
        }
    `;
    
    const styleSheet = document.createElement('style');
    styleSheet.textContent = backToTopStyles;
    document.head.appendChild(styleSheet);
    </script>
</body>
</html>