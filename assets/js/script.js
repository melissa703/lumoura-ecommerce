// ============================================
// FICHIER SCRIPT PRINCIPAL - LUMOURA PARFUMS
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    
    // ============================================
    // NAVBAR SCROLL EFFECT
    // ============================================
    
    const navbar = document.getElementById('mainNavbar');
    let lastScroll = 0;
    
    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset;
        
        // Ajoute/supprime la classe 'scrolled' quand on descend
        if (currentScroll > 100) {
            navbar.classList.add('scrolled');
            
            // Cache la navbar quand on descend, la montre quand on remonte
            if (currentScroll > lastScroll && currentScroll > 200) {
                navbar.style.transform = 'translateY(-100%)';
            } else {
                navbar.style.transform = 'translateY(0)';
            }
        } else {
            navbar.classList.remove('scrolled');
            navbar.style.transform = 'translateY(0)';
        }
        
        lastScroll = currentScroll;
    });
    
    // ============================================
    // MENU MOBILE TOGGLE
    // ============================================
    
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navMenu = document.getElementById('navMenu');
    const mobileSearch = document.getElementById('mobileSearch');
    
    mobileMenuBtn.addEventListener('click', function() {
        navMenu.classList.toggle('active');
        mobileMenuBtn.innerHTML = navMenu.classList.contains('active') 
            ? '<i class="fas fa-times"></i>' 
            : '<i class="fas fa-bars"></i>';
        
        // Ferme la recherche mobile si ouverte
        mobileSearch.classList.remove('active');
    });
    
    // ============================================
    // RECHERCHE TOGGLE
    // ============================================
    
    const searchToggle = document.getElementById('searchToggle');
    const searchBox = document.getElementById('searchBox');
    
    if (searchToggle) {
        searchToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            searchBox.classList.toggle('active');
        });
        
        // Ferme la recherche en cliquant ailleurs
        document.addEventListener('click', function(e) {
            if (!searchBox.contains(e.target) && e.target !== searchToggle) {
                searchBox.classList.remove('active');
            }
        });
    }
    
    // ============================================
    // CAROUSEL HERO
    // ============================================
    
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.slider-dot');
    const prevBtn = document.querySelector('.slider-prev');
    const nextBtn = document.querySelector('.slider-next');
    
    let currentSlide = 0;
    let slideInterval;
    
    function showSlide(n) {
        // Cache toutes les slides
        slides.forEach(slide => slide.classList.remove('active'));
        dots.forEach(dot => dot.classList.remove('active'));
        
        // Gestion du défilement circulaire
        currentSlide = (n + slides.length) % slides.length;
        
        // Affiche la slide actuelle
        slides[currentSlide].classList.add('active');
        dots[currentSlide].classList.add('active');
    }
    
    function nextSlide() {
        showSlide(currentSlide + 1);
    }
    
    function prevSlide() {
        showSlide(currentSlide - 1);
    }
    
    // Initialise le carousel si présent sur la page
    if (slides.length > 0) {
        // Configure les événements
        if (nextBtn) nextBtn.addEventListener('click', nextSlide);
        if (prevBtn) prevBtn.addEventListener('click', prevSlide);
        
        // Configure les dots
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => showSlide(index));
        });
        
        // Auto-slide toutes les 5 secondes
        slideInterval = setInterval(nextSlide, 5000);
        
        // Arrête l'auto-slide au survol
        const hero = document.querySelector('.hero');
        if (hero) {
            hero.addEventListener('mouseenter', () => clearInterval(slideInterval));
            hero.addEventListener('mouseleave', () => {
                slideInterval = setInterval(nextSlide, 5000);
            });
        }
    }
    
    // ============================================
    // GALLERIE PRODUIT (ZOOM ET THUMBNAILS)
    // ============================================
    
    const mainImage = document.querySelector('.main-image img');
    const thumbnails = document.querySelectorAll('.thumbnail');
    
    if (mainImage && thumbnails.length > 0) {
        thumbnails.forEach(thumbnail => {
            thumbnail.addEventListener('click', function() {
                // Supprime la classe active de toutes les thumbnails
                thumbnails.forEach(t => t.classList.remove('active'));
                
                // Ajoute la classe active à la thumbnail cliquée
                this.classList.add('active');
                
                // Change l'image principale
                const newSrc = this.querySelector('img').src;
                mainImage.src = newSrc;
                
                // Effet de fondu
                mainImage.style.opacity = '0';
                setTimeout(() => {
                    mainImage.style.opacity = '1';
                }, 200);
            });
        });
        
        // Effet de zoom sur l'image principale
        mainImage.addEventListener('mousemove', function(e) {
            const { left, top, width, height } = this.getBoundingClientRect();
            const x = ((e.clientX - left) / width) * 100;
            const y = ((e.clientY - top) / height) * 100;
            
            this.style.transformOrigin = `${x}% ${y}%`;
            this.style.transform = 'scale(1.5)';
        });
        
        mainImage.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    }
    
    // ============================================
    // SELECTEUR DE QUANTITÉ
    // ============================================
    
    const quantityInputs = document.querySelectorAll('.quantity-input');
    const minusBtns = document.querySelectorAll('.quantity-btn.minus');
    const plusBtns = document.querySelectorAll('.quantity-btn.plus');
    
    minusBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.nextElementSibling;
            let value = parseInt(input.value);
            if (value > 1) {
                input.value = value - 1;
                updateCartItem(this);
            }
        });
    });
    
    plusBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.previousElementSibling;
            let value = parseInt(input.value);
            input.value = value + 1;
            updateCartItem(this);
        });
    });
    
    function updateCartItem(btn) {
        // Trouve l'élément parent cart-item
        const cartItem = btn.closest('.cart-item');
        if (!cartItem) return;
        
        const priceElement = cartItem.querySelector('.cart-item-price');
        const totalElement = cartItem.querySelector('.cart-item-total');
        const quantityInput = cartItem.querySelector('.quantity-input');
        
        if (priceElement && totalElement && quantityInput) {
            const price = parseFloat(priceElement.textContent.replace('€', '').trim());
            const quantity = parseInt(quantityInput.value);
            const total = price * quantity;
            
            totalElement.textContent = total.toFixed(2) + ' €';
            updateCartSummary();
        }
    }
    
    // ============================================
    // WISHLIST TOGGLE
    // ============================================
    
    const wishlistBtns = document.querySelectorAll('.btn-wishlist');
    
    wishlistBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            this.classList.toggle('active');
            
            if (this.classList.contains('active')) {
                this.innerHTML = '<i class="fas fa-heart"></i>';
                showNotification('Ajouté aux favoris');
            } else {
                this.innerHTML = '<i class="far fa-heart"></i>';
                showNotification('Retiré des favoris');
            }
        });
    });
    
    // ============================================
    // NOTIFICATIONS
    // ============================================
    
    function showNotification(message, type = 'success') {
        // Crée l'élément de notification
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
            <button class="notification-close"><i class="fas fa-times"></i></button>
        `;
        
        // Ajoute au DOM
        document.body.appendChild(notification);
        
        // Animation d'entrée
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Fermeture automatique après 3 secondes
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
        
        // Fermeture au clic
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        });
    }
    
    // Styles pour les notifications (injectés via JS)
    const notificationStyles = `
        .notification {
            position: fixed;
            top: 100px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            transform: translateX(150%);
            transition: transform 0.3s ease;
            max-width: 350px;
        }
        .notification.show {
            transform: translateX(0);
        }
        .notification.success {
            border-left: 4px solid #27ae60;
        }
        .notification.error {
            border-left: 4px solid #e74c3c;
        }
        .notification i {
            font-size: 20px;
        }
        .notification.success i {
            color: #27ae60;
        }
        .notification.error i {
            color: #e74c3c;
        }
        .notification-close {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            margin-left: auto;
        }
    `;
    
    const styleSheet = document.createElement('style');
    styleSheet.textContent = notificationStyles;
    document.head.appendChild(styleSheet);
    
    // ============================================
    // FILTRES PRODUITS
    // ============================================
    
    const filterButtons = document.querySelectorAll('.filter-btn');
    const productCards = document.querySelectorAll('.product-card');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Active le bouton cliqué
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            
            // Filtre les produits
            productCards.forEach(card => {
                if (filter === 'all') {
                    card.style.display = 'block';
                } else {
                    const cardCategory = card.dataset.category;
                    if (cardCategory === filter) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                }
                
                // Animation de fade
                card.style.opacity = '0';
                setTimeout(() => {
                    card.style.opacity = '1';
                }, 300);
            });
        });
    });
    
    // ============================================
    // VALIDATION FORMULAIRE
    // ============================================
    
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const inputs = this.querySelectorAll('.form-input[required]');
            
            inputs.forEach(input => {
                const errorElement = input.nextElementSibling;
                
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = '#e74c3c';
                    if (errorElement && errorElement.classList.contains('form-error')) {
                        errorElement.textContent = 'Ce champ est requis';
                    }
                } else {
                    input.style.borderColor = '';
                    if (errorElement && errorElement.classList.contains('form-error')) {
                        errorElement.textContent = '';
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Veuillez remplir tous les champs obligatoires', 'error');
            }
        });
    });
    
    // ============================================
    // LOADING ANIMATION
    // ============================================
    
    // Affiche un loader lors du chargement de la page
    window.addEventListener('load', function() {
        const loader = document.createElement('div');
        loader.className = 'page-loader';
        loader.innerHTML = `
            <div class="loader-content">
                <div class="loader-spinner"></div>
                <div class="loader-text">LUMOURA</div>
            </div>
        `;
        
        // Styles pour le loader
        const loaderStyles = `
            .page-loader {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: var(--cream);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                transition: opacity 0.5s ease;
            }
            .loader-content {
                text-align: center;
            }
            .loader-spinner {
                width: 50px;
                height: 50px;
                border: 3px solid var(--gold-light);
                border-top-color: var(--gold);
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto 20px;
            }
            .loader-text {
                font-family: 'Cinzel', serif;
                font-size: 24px;
                color: var(--deep-brown);
                letter-spacing: 2px;
            }
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
        `;
        
        const loaderStyleSheet = document.createElement('style');
        loaderStyleSheet.textContent = loaderStyles;
        document.head.appendChild(loaderStyleSheet);
        
        document.body.appendChild(loader);
        
        // Cache le loader après 1.5 secondes
        setTimeout(() => {
            loader.style.opacity = '0';
            setTimeout(() => {
                loader.remove();
            }, 500);
        }, 1500);
    });

    // ============================================
    // AJOUT AU PANIER & ACHETER MAINTENANT
    // ============================================

    // Bouton "Ajouter au panier" → ajoute et redirige vers panier.php
    document.querySelectorAll('.btn-add-to-cart').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.getAttribute('data-product-id');
            const quantityInput = document.getElementById('quantity');
            const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;

            fetch('../../pages/ajouter_au_panier.php', {  // ← chemin corrigé depuis /assets/js/
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${productId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = 'panier.php';
                    }, 1500); // 1.5s pour voir la notification
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erreur fetch:', error);
                showNotification('Erreur lors de l\'ajout au panier.', 'error');
            });
        });
    });

    // Bouton "Acheter maintenant" → ajoute et redirige vers commande.php
    document.querySelectorAll('.btn-buy-now').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            // IMPORTANT : adapte selon ton cas
            // Si script inclus dans produit.php → PHP dispo
            // const productId = <?php echo $productId ?? 'null'; ?>;
            // Sinon (script global) → utilise data-product-id sur le bouton HTML
            const productId = this.getAttribute('data-product-id'); // ← recommandé
            const quantityInput = document.getElementById('quantity');
            const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;

            if (!productId) {
                showNotification('Erreur : ID produit manquant.', 'error');
                return;
            }

            fetch('../../pages/ajouter_au_panier.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${productId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = 'commande.php';
                    }, 1500);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erreur fetch:', error);
                showNotification('Erreur lors de l\'achat.', 'error');
            });
        });
    });
});