<?php
// ============================================================
// FICHIER : index.php — Page d'accueil du site Lumoura
// RÔLE    : Affiche le hero slider, les catégories,
//           les nouveautés et les best-sellers
// ============================================================

// config.php  → définit les constantes (SITE_URL, DB_HOST...) et démarre la session PHP
// db.php      → crée la connexion PDO à la base de données MySQL ($pdo)
// functions.php → fonctions utilitaires réutilisables dans tout le site
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Titre de la page utilisé dans la balise <title> du header
$pageTitle = "Accueil - Lumoura Parfums de Luxe";

// Inclut header.php → contient DOCTYPE, <head>, <nav> et le début du <body>
include '../includes/header.php';
?>

<style>
/* Import police Cormorant Garamond */
@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&display=swap');

/* ============================================
   STYLES PAGE ACCUEIL LUXE - LUMOURA
   ============================================ */

/* --- HERO --- */
.hero {
    position: relative;
    height: 100vh;
    min-height: 650px;
    overflow: hidden;
}
.hero-slider { width: 100%; height: 100%; position: relative; }

.slide {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    opacity: 0;
    transition: opacity 1.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}
.slide.active { opacity: 1; z-index: 1; }

.slide-content {
    text-align: center;
    color: white;
    z-index: 2;
    padding: 0 20px;
    max-width: 750px;
}
.slide-eyebrow {
    font-size: 0.8em;
    letter-spacing: 5px;
    text-transform: uppercase;
    color: #d4a017;
    margin-bottom: 18px;
    font-weight: 500;
}
.slide-content h1 {
    font-family: 'Cormorant Garamond', 'Playfair Display', serif;
    font-size: clamp(2em, 3.8vw, 3.8em);
    font-weight: 300;
    line-height: 1.3;
    margin-bottom: 20px;
    color: #fff8f0;
    text-shadow: 0 2px 25px rgba(0,0,0,0.25), 0 0 80px rgba(212,160,23,0.12);
    letter-spacing: 0.1em;
}
.slide-content h1 em {
    color: #e8c56a;
    font-style: italic;
    font-weight: 300;
    letter-spacing: 0.12em;
    text-shadow: 0 0 40px rgba(232,197,106,0.4);
}
.slide-content p {
    font-size: 1.1em;
    opacity: 0.9;
    margin-bottom: 35px;
    line-height: 1.7;
    max-width: 550px;
    margin-left: auto;
    margin-right: auto;
}
.hero-btns { display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; }
.btn-hero-primary {
    background: linear-gradient(135deg, #d4a017, #b8860b);
    color: white;
    padding: 15px 40px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    font-size: 1em;
    letter-spacing: 1px;
    transition: all 0.3s;
    border: 2px solid transparent;
}
.btn-hero-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(212,160,23,0.4);
    color: white;
}
.btn-hero-outline {
    background: transparent;
    color: white;
    padding: 15px 40px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 500;
    font-size: 1em;
    letter-spacing: 1px;
    transition: all 0.3s;
    border: 2px solid rgba(255,255,255,0.6);
}
.btn-hero-outline:hover {
    background: rgba(255,255,255,0.15);
    border-color: white;
    color: white;
}

/* Nav slider */
.slider-nav button {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    font-size: 1em;
    cursor: pointer;
    transition: all 0.3s;
}
.slider-nav button:hover { background: rgba(212,160,23,0.6); }
.slider-prev { left: 25px; }
.slider-next { right: 25px; }
.slider-controls {
    position: absolute;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 10px;
    z-index: 10;
}
.slider-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: rgba(255,255,255,0.4);
    cursor: pointer;
    transition: all 0.3s;
}
.slider-dot.active {
    background: #d4a017;
    width: 28px;
    border-radius: 4px;
}

/* Scroll indicator */
.hero-scroll {
    position: absolute;
    bottom: 30px;
    right: 40px;
    z-index: 10;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    color: rgba(255,255,255,0.6);
    font-size: 0.75em;
    letter-spacing: 2px;
    text-transform: uppercase;
}
.hero-scroll-line {
    width: 1px;
    height: 50px;
    background: linear-gradient(to bottom, rgba(255,255,255,0.6), transparent);
    animation: scrollLine 1.5s ease-in-out infinite;
}
@keyframes scrollLine {
    0%, 100% { opacity: 0.4; transform: scaleY(1); }
    50% { opacity: 1; transform: scaleY(0.6); }
}

/* --- BANDEAU AVANTAGES --- */
.benefits-bar {
    background: #1a1009;
    color: #d4a017;
    padding: 14px 0;
    text-align: center;
}
.benefits-bar-inner {
    display: flex;
    justify-content: center;
    gap: 50px;
    flex-wrap: wrap;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}
.benefit-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85em;
    font-weight: 500;
    letter-spacing: 0.5px;
    color: #e8c56a;
}
.benefit-item i { color: #d4a017; }

/* --- SECTIONS GÉNÉRALES --- */
.home-section {
    padding: 80px 0;
}
.home-section.bg-dark-luxury {
    background: linear-gradient(135deg, #1a1009 0%, #2c1810 50%, #1a1009 100%);
    color: white;
}
.home-section.bg-light {
    background: #faf8f5;
}
.home-section.bg-cream {
    background: #f5f0e8;
}

.section-header {
    text-align: center;
    margin-bottom: 50px;
}
.section-eyebrow {
    display: inline-block;
    font-size: 0.75em;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: #d4a017;
    font-weight: 600;
    margin-bottom: 12px;
    padding: 5px 16px;
    border: 1px solid #d4a017;
    border-radius: 20px;
}
.section-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(1.8em, 4vw, 2.8em);
    font-weight: 400;
    color: #2c1810;
    margin: 0 0 12px;
}
.home-section.bg-dark-luxury .section-header h2 { color: white; }
.section-header p {
    color: #888;
    font-size: 1.05em;
    max-width: 500px;
    margin: 0 auto;
}
.home-section.bg-dark-luxury .section-header p { color: rgba(255,255,255,0.6); }

.section-divider {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin-bottom: 10px;
}
.section-divider span { width: 60px; height: 1px; background: #d4a017; }
.section-divider i { color: #d4a017; font-size: 0.8em; }

/* --- CATÉGORIES LUXE --- */
.categories-luxe {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 25px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 30px;
}
.cat-card-luxe {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    height: 420px;
    cursor: pointer;
    group: true;
}
.cat-card-luxe img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
}
.cat-card-luxe:hover img { transform: scale(1.08); }
.cat-card-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.2) 50%, transparent 100%);
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 35px;
    transition: all 0.3s;
}
.cat-card-luxe:hover .cat-card-overlay {
    background: linear-gradient(to top, rgba(44,24,16,0.95) 0%, rgba(0,0,0,0.4) 50%, transparent 100%);
}
.cat-label {
    font-size: 0.75em;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: #d4a017;
    font-weight: 600;
    margin-bottom: 8px;
}
.cat-card-overlay h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.9em;
    color: white;
    font-weight: 400;
    margin: 0 0 8px;
}
.cat-card-overlay p {
    color: rgba(255,255,255,0.75);
    font-size: 0.9em;
    margin: 0 0 20px;
}
.cat-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #d4a017;
    color: white;
    text-decoration: none;
    padding: 10px 22px;
    border-radius: 50px;
    font-size: 0.85em;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: all 0.3s;
    width: fit-content;
    transform: translateY(5px);
    opacity: 0;
    transition: all 0.3s ease;
}
.cat-card-luxe:hover .cat-btn {
    transform: translateY(0);
    opacity: 1;
}

/* --- PRODUITS CARDS LUXE --- */
.products-luxe-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 25px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 30px;
}
.product-luxe-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    transition: all 0.35s ease;
    position: relative;
    cursor: pointer;
}
.product-luxe-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 45px rgba(212,160,23,0.18);
}
.product-luxe-img {
    height: 260px;
    overflow: hidden;
    position: relative;
}
.product-luxe-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}
.product-luxe-card:hover .product-luxe-img img { transform: scale(1.06); }

.product-luxe-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: linear-gradient(135deg, #d4a017, #b8860b);
    color: white;
    font-size: 0.75em;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 20px;
    z-index: 2;
}
.product-luxe-badge.promo {
    background: linear-gradient(135deg, #e63946, #c62828);
}

.product-luxe-wishlist {
    position: absolute;
    top: 12px;
    right: 12px;
    background: white;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 2;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: all 0.3s;
    color: #aaa;
}
.product-luxe-wishlist:hover { color: #ef4444; transform: scale(1.1); }

.product-luxe-body { padding: 18px 20px; }
.product-luxe-brand {
    font-size: 0.75em;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #d4a017;
    font-weight: 600;
    margin-bottom: 5px;
}
.product-luxe-name {
    font-family: 'Playfair Display', serif;
    font-size: 1.1em;
    color: #2c1810;
    font-weight: 600;
    margin: 0 0 5px;
    line-height: 1.3;
}
.product-luxe-desc {
    font-size: 0.82em;
    color: #999;
    margin: 0 0 14px;
    line-height: 1.5;
}
.product-luxe-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.product-luxe-price {
    display: flex;
    flex-direction: column;
}
.price-old {
    font-size: 0.8em;
    color: #bbb;
    text-decoration: line-through;
    line-height: 1;
}
.price-new {
    font-size: 1.2em;
    font-weight: 700;
    color: #2c1810;
}
.price-new.promo { color: #e63946; }
.btn-add-luxe {
    background: linear-gradient(135deg, #d4a017, #b8860b);
    color: white;
    border: none;
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 0.85em;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}
.btn-add-luxe:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(212,160,23,0.4);
}

/* --- BANDEAU CHIFFRES --- */
.stats-banner {
    background: linear-gradient(135deg, #2c1810, #1a1009);
    padding: 60px 30px;
    text-align: center;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
    max-width: 900px;
    margin: 0 auto;
}
.stat-item-luxe { color: white; }
.stat-number {
    font-family: 'Playfair Display', serif;
    font-size: 2.8em;
    color: #d4a017;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 8px;
}
.stat-label {
    font-size: 0.85em;
    color: rgba(255,255,255,0.6);
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* --- SECTION À PROPOS --- */
.about-luxe {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 80px;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 30px;
}
.about-luxe-img {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    height: 500px;
}
.about-luxe-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.about-luxe-img::after {
    content: '';
    position: absolute;
    bottom: -20px;
    right: -20px;
    width: 200px;
    height: 200px;
    border: 3px solid #d4a017;
    border-radius: 20px;
    z-index: -1;
}
.about-luxe-content .eyebrow { margin-bottom: 15px; }
.about-luxe-content h2 {
    font-family: 'Playfair Display', serif;
    font-size: 2.5em;
    font-weight: 400;
    color: #2c1810;
    line-height: 1.25;
    margin-bottom: 20px;
}
.about-luxe-content p {
    color: #666;
    line-height: 1.8;
    font-size: 1.05em;
    margin-bottom: 35px;
}
.about-features-luxe {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 35px;
}
.about-feature-luxe {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.about-feature-icon {
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, #d4a017, #b8860b);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1em;
    flex-shrink: 0;
}
.about-feature-luxe h4 {
    font-size: 0.9em;
    font-weight: 600;
    color: #2c1810;
    margin: 0 0 4px;
}
.about-feature-luxe p {
    font-size: 0.82em;
    color: #999;
    margin: 0;
    line-height: 1.4;
}

.btn-about {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: #2c1810;
    color: white;
    padding: 15px 35px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95em;
    transition: all 0.3s;
}
.btn-about:hover {
    background: #d4a017;
    color: white;
    transform: translateY(-2px);
}

/* --- CARROUSEL SCROLL --- */
.carousel-wrap {
    max-width: 1260px;
    margin: 0 auto;
    padding: 0 30px;
    position: relative;
}
.carousel-inner {
    overflow-x: auto;
    scroll-behavior: smooth;
    scrollbar-width: none;
    -ms-overflow-style: none;
    padding: 10px 0 20px;
}
.carousel-inner::-webkit-scrollbar { display: none; }
.carousel-track {
    display: flex;
    gap: 25px;
    width: max-content;
}
.carousel-track .product-luxe-card {
    flex: 0 0 260px;
    width: 260px;
}
.carousel-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: white;
    border: 1px solid #e0d5c5;
    color: #2c1810;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 5;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s;
    font-size: 0.9em;
}
.carousel-arrow:hover { background: #d4a017; color: white; border-color: #d4a017; }
.carousel-arrow.left { left: -10px; }
.carousel-arrow.right { right: -10px; }

/* Responsive */
@media (max-width: 900px) {
    .categories-luxe { grid-template-columns: 1fr; }
    .cat-card-luxe { height: 320px; }
    .about-luxe { grid-template-columns: 1fr; gap: 40px; }
    .about-luxe-img { height: 300px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .cat-btn { opacity: 1; transform: none; }
}
</style>

<!-- ==========================================
     HERO SLIDER - STYLE MAGAZINE LUXE
     =========================================
     Structure en 2 colonnes :
     - Colonne gauche : texte (eyebrow + titre + description + boutons)
     - Colonne droite : photo pleine hauteur
     Le slider change automatiquement toutes les 4 secondes
     ========================================== -->
<style>
/* Override hero pour style magazine */
.hero { background: #0e0a06; }

.slide {
    display: grid !important;
    grid-template-columns: 1fr 1fr;
    align-items: center;
    background: none !important;
    padding: 0;
}

.slide-left {
    padding: 80px 70px 80px 80px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    z-index: 2;
}

.slide-right {
    position: relative;
    height: 100vh;
    min-height: 650px;
    overflow: hidden;
}
.slide-right img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: brightness(0.88);
    transition: transform 1.5s ease;
}
.slide.active .slide-right img { transform: scale(1.04); }
.slide-right::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to right, #0e0a06 0%, transparent 30%);
    z-index: 1;
}
.slide-right::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(212,160,23,0.08) 0%, transparent 60%);
    z-index: 1;
}

/* Texte slide magazine */
.slide-eyebrow {
    font-size: 0.72em;
    letter-spacing: 6px;
    text-transform: uppercase;
    color: #e8c56a;
    font-weight: 300;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-family: 'Montserrat', sans-serif;
}
.slide-eyebrow::before {
    content: '';
    display: inline-block;
    width: 40px;
    height: 1px;
    background: linear-gradient(to right, transparent, #e8c56a);
}

.slide-content h1 {
    font-family: 'Cormorant Garamond', 'Playfair Display', serif;
    font-size: clamp(2em, 3.8vw, 3.8em);
    font-weight: 300;
    line-height: 1.3;
    color: #fff8f0;
    margin-bottom: 22px;
    text-shadow: 0 2px 25px rgba(0,0,0,0.25), 0 0 80px rgba(212,160,23,0.12);
    letter-spacing: 0.1em;
}
.slide-content h1 em {
    color: #e8c56a;
    font-style: italic;
    font-weight: 300;
    display: block;
    letter-spacing: 0.12em;
    text-shadow: 0 0 40px rgba(232,197,106,0.4);
}

.slide-separator {
    width: 60px;
    height: 2px;
    background: linear-gradient(to right, #d4a017, transparent);
    margin-bottom: 22px;
}

.slide-content p {
    font-size: 1em;
    color: rgba(255,245,238,0.75);
    line-height: 1.9;
    margin-bottom: 38px;
    max-width: 420px;
    margin-left: 0;
    margin-right: 0;
    font-weight: 300;
    letter-spacing: 0.03em;
}

.hero-btns { justify-content: flex-start; }

/* Numéro de slide */
.slide-number {
    position: absolute;
    bottom: 40px;
    left: 80px;
    color: rgba(255,255,255,0.2);
    font-family: 'Playfair Display', serif;
    font-size: 5em;
    font-weight: 700;
    line-height: 1;
    z-index: 3;
    pointer-events: none;
    user-select: none;
}
.slide-number span { color: #d4a017; font-size: 0.4em; vertical-align: top; margin-top: 15px; display: inline-block; }

/* Barre de progression */
.slider-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: #d4a017;
    width: 0%;
    z-index: 20;
    transition: none;
}
.slider-progress.animating {
    transition: width 4s linear;
    width: 100%;
}

/* Dots repositionnés */
.slider-controls {
    position: absolute;
    bottom: 40px;
    right: 50px;
    flex-direction: column;
    gap: 12px;
    z-index: 10;
}
.slider-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
}
.slider-dot.active {
    background: #d4a017;
    width: 6px;
    height: 24px;
    border-radius: 3px;
}

/* Nav boutons style magazine */
.slider-nav button {
    background: transparent;
    border: 1px solid rgba(255,255,255,0.2);
    backdrop-filter: none;
}
.slider-nav button:hover { background: #d4a017; border-color: #d4a017; }
.slider-prev { left: auto; right: 80px; bottom: 80px; top: auto; transform: none; }
.slider-next { right: 25px; bottom: 80px; top: auto; transform: none; }

@media (max-width: 768px) {
    .slide { grid-template-columns: 1fr; }
    .slide-right { height: 50vh; min-height: 300px; }
    .slide-left { padding: 40px 25px; }
    .slide-right::before { background: linear-gradient(to bottom, #0e0a06 0%, transparent 40%); }
    .slide-number { display: none; }
}
</style>

<section class="hero">
    <div class="hero-slider">

        <!-- ===== SLIDE 1 : active = visible au chargement de la page ===== -->
        <div class="slide active">
            <div class="slide-left">
                <div class="slide-content">
                    <div class="slide-eyebrow">Lumoura — L'Art du Parfum</div>
                    <h1>La Fragrance,<br><em>Un Art Invisible</em></h1>
                    <div class="slide-separator"></div>
                    <p>Des parfums signature créés avec passion et expertise, pour éveiller vos sens et sublimer chaque moment de votre vie.</p>
                    <div class="hero-btns">
                        <a href="catalogue.php" class="btn-hero-primary"><i class="fas fa-store"></i> Découvrir</a>
                        <a href="catalogue.php?filter=new" class="btn-hero-outline">Nouveautés</a>
                    </div>
                </div>
            </div>
            <div class="slide-right">
                <img src="https://images.unsplash.com/photo-1587304883224-b1b6e004a97a?q=80&w=1074&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" alt="Parfum luxe flacon doré">
            </div>

        </div>

        <!-- ===== SLIDE 2 ===== -->
        <div class="slide">
            <div class="slide-left">
                <div class="slide-content">
                    <div class="slide-eyebrow">Nouveautés Automne 2025</div>
                    <h1>Des Senteurs<br><em>Précieuses</em></h1>
                    <div class="slide-separator"></div>
                    <p>Plongez dans nos nouvelles créations olfactives, inspirées de l'or, de l'ambre et des épices les plus rares du monde.</p>
                    <div class="hero-btns">
                        <a href="catalogue.php?filter=new" class="btn-hero-primary"><i class="fas fa-star"></i> Voir les Nouveautés</a>
                        <a href="catalogue.php" class="btn-hero-outline">Collection</a>
                    </div>
                </div>
            </div>
            <div class="slide-right">
                <img src="https://images.unsplash.com/photo-1744367848862-7eda496f3186?q=80&w=1974&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" alt="Flacon parfum luxe ambiance">
            </div>
        </div>

        <!-- ===== SLIDE 3 ===== -->
        <div class="slide">
            <div class="slide-left">
                <div class="slide-content">
                    <div class="slide-eyebrow">Offres Exclusives</div>
                    <h1>Promotions<br><em>d'Exception</em></h1>
                    <div class="slide-separator"></div>
                    <p>Profitez de réductions exceptionnelles sur notre sélection best-seller, soigneusement choisie pour vous ravir.</p>
                    <div class="hero-btns">
                        <a href="catalogue.php?filter=promo" class="btn-hero-primary"><i class="fas fa-tag"></i> Voir les Offres</a>
                        <a href="catalogue.php?filter=bestseller" class="btn-hero-outline">Best-sellers</a>
                    </div>
                </div>
            </div>
            <div class="slide-right">
                <img src="https://images.unsplash.com/photo-1759793500391-24a07d5a1269?q=80&w=687&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" alt="Parfum femme luxe élégant">
            </div>

    </div>

    <!-- Barre de progression -->
    <div class="slider-progress" id="sliderProgress"></div>

    <div class="slider-nav">
        <button class="slider-prev"><i class="fas fa-chevron-left"></i></button>
        <button class="slider-next"><i class="fas fa-chevron-right"></i></button>
    </div>
    <div class="slider-controls">
        <span class="slider-dot active"></span>
        <span class="slider-dot"></span>
        <span class="slider-dot"></span>
    </div>
    <div class="hero-scroll">
        <div class="hero-scroll-line"></div>
        <span>Scroll</span>
    </div>
</section>

<!-- ==========================================
     BANDEAU AVANTAGES
     ========================================== -->
<div class="benefits-bar">
    <div class="benefits-bar-inner">
        <div class="benefit-item"><i class="fas fa-shipping-fast"></i> Livraison gratuite dès 150€</div>
        <div class="benefit-item"><i class="fas fa-gift"></i> Échantillons offerts</div>
        <div class="benefit-item"><i class="fas fa-undo"></i> Retour gratuit 30 jours</div>
        <div class="benefit-item"><i class="fas fa-shield-alt"></i> Paiement 100% sécurisé</div>
        <div class="benefit-item"><i class="fas fa-headset"></i> Service client 7j/7</div>
    </div>
</div>

<!-- ==========================================
     SECTION CATÉGORIES
     ========================================== -->
<section class="home-section bg-light">
    <div class="section-header">
        <div class="section-eyebrow">Nos Collections</div>
        <div class="section-divider"><span></span><i class="fas fa-gem"></i><span></span></div>
        <h2>Trouvez Votre Fragrance</h2>
        <p>Une sélection raffinée pour chaque personnalité, chaque occasion</p>
    </div>

    <div class="categories-luxe">
        <!-- Pour Elle -->
        <div class="cat-card-luxe">
            <img src="https://images.unsplash.com/photo-1605091863201-b005ef043049?q=80&w=735&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" 
                 alt="Parfums Femme"
                 onerror="this.src='https:https: //unsplash.com/fr/photos/flacon-de-parfum-en-verre-transparent-sur-table-en-bois-blanc-shtjk2hfiCE">
            <div class="cat-card-overlay">
                <div class="cat-label">Collection</div>
                <h3>Pour Elle</h3>
                <p>Fragrances délicates, florales et envoûtantes</p>
                <a href="catalogue.php?category=Femme" class="cat-btn">
                    Découvrir <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Pour Lui -->
        <div class="cat-card-luxe">
            <img src="https://images.unsplash.com/photo-1707379426703-5920fb03375e?q=80&w=1171&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" 
                 alt="Parfums Homme"
                 onerror="this.src='https: //images.unsplash.com/photo-1590736969956-6d9c2a8d6972?auto=format&fit=crop&w=800&q=80'">
            <div class="cat-card-overlay">
                <div class="cat-label">Collection</div>
                <h3>Pour Lui</h3>
                <p>Essences boisées, viriles et sophistiquées</p>
                <a href="catalogue.php?category=Homme" class="cat-btn">
                    Découvrir <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Unisexe -->
        <div class="cat-card-luxe">
            <img src="https://images.unsplash.com/photo-1769038937414-09bcf11f6ed7?q=80&w=1976&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" 
                 alt="Parfums Unisexe"
                 onerror="this.src='https: //images.unsplash.com/photo-1592945403244-b3fbafd7f539?auto=format&fit=crop&w=800&q=80'">
            <div class="cat-card-overlay">
                <div class="cat-label">Collection</div>
                <h3>Unisexe</h3>
                <p>Fragrances libres, audacieuses, pour tous</p>
                <a href="catalogue.php?category=Unisexe" class="cat-btn">
                    Découvrir <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================
     SECTION QUIZ OLFACTIF
     ==========================================
     Invite à faire le quiz (pages/quiz.php).
     Les photos viennent de la table produits (requête sans paramètre
     utilisateur) ; si la base ne répond pas, la section s'affiche sans photos.
     ========================================== -->
<?php
$hqVisuels = [];
try {
    $hqStmt = $pdo->prepare(
        "SELECT id_produit, nom, image_url
         FROM produits
         WHERE id_produit IN (6, 3, 5) AND stock > 0
           AND image_url IS NOT NULL AND image_url <> ''"
    );
    $hqStmt->execute();
    $hqParId = [];
    foreach ($hqStmt->fetchAll(PDO::FETCH_ASSOC) as $hqRow) {
        $hqParId[(int)$hqRow['id_produit']] = $hqRow;
    }
    foreach ([6, 3, 5] as $hqId) {            // ordre d'affichage voulu
        if (isset($hqParId[$hqId])) { $hqVisuels[] = $hqParId[$hqId]; }
    }
} catch (PDOException $e) {
    $hqVisuels = [];
}
$hqNb = count($hqVisuels);
?>
<style>
/* --- QUIZ OLFACTIF (accueil) : préfixe hq- --- */
.hq { position: relative; overflow: hidden; isolation: isolate; }
.hq::before {
    content: "";
    position: absolute;
    right: -10%; top: 50%;
    width: 780px; height: 780px;
    transform: translateY(-50%);
    background: radial-gradient(closest-side, rgba(212,160,23,0.20), transparent);
    z-index: -1;
    pointer-events: none;
}
.hq-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 30px;
    display: grid;
    grid-template-columns: 5fr 6fr;
    gap: 64px;
    align-items: center;
}
.hq-text .section-eyebrow { margin-bottom: 22px; }
.hq-title {
    font-family: 'Cormorant Garamond', 'Playfair Display', serif;
    font-weight: 300;
    font-size: clamp(2.3em, 4.6vw, 3.8em);
    line-height: 1.12;
    letter-spacing: 0.04em;
    color: #fff8f0;
    margin: 0 0 20px;
}
.hq-title em {
    color: #e8c56a;
    font-style: italic;
    text-shadow: 0 0 40px rgba(232,197,106,0.35);
}
.hq-lead {
    color: rgba(255,255,255,0.72);
    font-size: 1.05em;
    line-height: 1.8;
    max-width: 470px;
    margin: 0 0 34px;
}

.hq-btn {
    --bx: 0px; --by: 0px;
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    overflow: hidden;
    padding: 16px 38px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95em;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: #fff;
    background: linear-gradient(135deg, #b8860b, #d4a017 50%, #e8c56a);
    box-shadow: 0 12px 28px -10px rgba(212,160,23,0.7), inset 0 1px 0 rgba(255,255,255,0.35);
    transform: translate(var(--bx), var(--by));
    transition: box-shadow .4s cubic-bezier(.22,.8,.24,1), transform .25s ease-out;
}
.hq-btn::before {
    content: "";
    position: absolute;
    inset: 0 auto 0 -60%;
    width: 45%;
    background: linear-gradient(105deg, transparent, rgba(255,255,255,0.55), transparent);
    transform: skewX(-20deg);
    transition: left .8s cubic-bezier(.22,.8,.24,1);
}
.hq-btn i { transition: transform .4s cubic-bezier(.22,.8,.24,1); }
.hq-btn:hover { color: #fff; box-shadow: 0 20px 40px -12px rgba(212,160,23,0.85), 0 0 0 6px rgba(212,160,23,0.18), inset 0 1px 0 rgba(255,255,255,0.45); }
.hq-btn:hover::before { left: 120%; }
.hq-btn:hover i { transform: translateX(5px); }
.hq-btn:active { transform: translate(var(--bx), var(--by)) scale(.97); }
.hq-btn:focus-visible { outline: 2px solid #e8c56a; outline-offset: 4px; }

.hq-perks {
    list-style: none;
    margin: 30px 0 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 10px 24px;
    font-size: 0.85em;
    color: rgba(255,255,255,0.6);
}
.hq-perks li::before { content: "\2713"; color: #d4a017; font-weight: 700; margin-right: 8px; }

/* Mosaïque de photos en 3D */
.hq-stage {
    display: grid;
    grid-template-columns: 1.1fr 1fr;
    grid-template-rows: 1fr 1fr;
    gap: 14px;
    height: 500px;
    perspective: 1400px;
}
.hq-stage-n1, .hq-stage-n2 { grid-template-columns: repeat(auto-fit, minmax(0, 1fr)); grid-template-rows: 1fr; }
.hq-stage-n3 .hq-card-1 { grid-row: 1 / span 2; }

.hq-card {
    --rx: 0deg; --ry: 0deg; --mx: 50%; --my: 50%; --s: 1;
    position: relative;
    margin: 0;
    overflow: hidden;
    border-radius: 16px;
    background: #1a1009;
    box-shadow: 0 24px 50px -28px rgba(0,0,0,0.9), 0 0 0 1px rgba(232,197,106,0.12);
    transform: perspective(1000px) rotateX(var(--rx)) rotateY(var(--ry)) scale(var(--s));
    transition: transform .7s cubic-bezier(.22,.8,.24,1), box-shadow .6s cubic-bezier(.22,.8,.24,1);
    will-change: transform;
}
.hq-card img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 1.3s cubic-bezier(.22,.8,.24,1);
}
.hq-card::before {   /* dégradé sous la légende */
    content: "";
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(14,10,6,0.72), transparent 50%);
    z-index: 1;
}
.hq-card::after {    /* reflet doré qui suit le curseur */
    content: "";
    position: absolute; inset: 0;
    z-index: 2;
    pointer-events: none;
    background: radial-gradient(380px circle at var(--mx) var(--my), rgba(255,244,214,0.28), transparent 55%);
    opacity: 0;
    transition: opacity .4s ease;
}
.hq-card.is-tilting {
    --s: 1.035;
    transition: transform .12s ease-out, box-shadow .6s cubic-bezier(.22,.8,.24,1);
    box-shadow: 0 40px 70px -28px rgba(0,0,0,0.95), 0 0 0 1px rgba(232,197,106,0.6), 0 0 50px -8px rgba(212,160,23,0.5);
}
.hq-card.is-tilting::after { opacity: 1; }
.hq-card.is-tilting img { transform: scale(1.08); }
.hq-card figcaption {
    position: absolute;
    z-index: 3;
    left: 16px; bottom: 14px;
    font-family: 'Cinzel', serif;
    font-size: 0.72rem;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #fff;
}
.hq-card figcaption::after {
    content: "";
    display: block;
    width: 0; height: 1px;
    margin-top: 8px;
    background: #e8c56a;
    transition: width .6s cubic-bezier(.22,.8,.24,1);
}
.hq-card.is-tilting figcaption::after { width: 56px; }

/* Apparition au scroll (ajoutée par le JS ; sans JS tout reste visible) */
.hq.hq-anim:not(.is-in) .hq-text > * { opacity: 0; transform: translateY(24px); }
.hq.hq-anim:not(.is-in) .hq-card { opacity: 0; transform: perspective(1000px) translateY(46px) rotateX(10deg); }
.hq .hq-text > * { transition: opacity .8s cubic-bezier(.22,.8,.24,1), transform .8s cubic-bezier(.22,.8,.24,1); }
.hq .hq-text > *:nth-child(2) { transition-delay: .1s; }
.hq .hq-text > *:nth-child(3) { transition-delay: .2s; }
.hq .hq-text > *:nth-child(4) { transition-delay: .3s; }
.hq .hq-text > *:nth-child(5) { transition-delay: .4s; }
.hq .hq-card-1 { transition-delay: .15s; }
.hq .hq-card-2 { transition-delay: .3s; }
.hq .hq-card-3 { transition-delay: .45s; }
.hq-card.is-tilting { transition-delay: 0s; }

@media (max-width: 900px) {
    .hq-inner { grid-template-columns: 1fr; gap: 44px; }
    .hq-text { text-align: center; }
    .hq-lead { margin-left: auto; margin-right: auto; }
    .hq-perks { justify-content: center; }
    .hq-stage { height: 380px; }
}
@media (prefers-reduced-motion: reduce) {
    .hq-card, .hq-card.is-tilting { transform: none; transition: none; }
    .hq-btn::before { display: none; }
}
</style>

<section class="home-section bg-dark-luxury hq" id="quizAccueil" aria-labelledby="hq-title">
    <div class="hq-inner">
        <div class="hq-text">
            <div class="section-eyebrow">Quiz olfactif</div>
            <h2 class="hq-title" id="hq-title">Quel parfum <em>vous ressemble</em>&nbsp;?</h2>
            <p class="hq-lead">
                Quatre questions, moins d'une minute. Nous comparons vos goûts aux parfums
                réellement disponibles chez Lumoura et vous proposons jusqu'à trois recommandations.
            </p>
            <a href="quiz.php" class="hq-btn">Commencer le quiz <i class="fas fa-arrow-right"></i></a>
            <ul class="hq-perks">
                <li>Sans inscription</li>
                <li>Parfums en stock</li>
                <li>Prix exacts</li>
            </ul>
        </div>

        <?php if ($hqNb > 0): ?>
        <div class="hq-stage hq-stage-n<?php echo $hqNb; ?>">
            <?php foreach ($hqVisuels as $hqI => $hqV): ?>
                <figure class="hq-card hq-card-<?php echo $hqI + 1; ?>">
                    <img src="<?php echo htmlspecialchars($hqV['image_url']); ?>"
                         alt="Flacon <?php echo htmlspecialchars($hqV['nom']); ?>"
                         loading="lazy">
                    <figcaption><?php echo htmlspecialchars($hqV['nom']); ?></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ==========================================
     SECTION NOUVEAUTÉS
     ========================================== -->
<section class="home-section bg-cream">
    <div class="section-header">
        <div class="section-eyebrow">✦ Nouveautés</div>
        <div class="section-divider"><span></span><i class="fas fa-star"></i><span></span></div>
        <h2>Les Dernières Créations</h2>
        <p>Découvrez nos nouvelles fragrances exclusives, fraîchement arrivées</p>
    </div>

    <?php
    try {
        // Requête SQL : récupère les 8 derniers produits marqués "nouveaute"
        // ORDER BY date_ajout DESC = du plus récent au plus ancien
        // LIMIT 8 = maximum 8 produits affichés
        $query = "SELECT * FROM produits WHERE nouveaute = 1 ORDER BY date_ajout DESC LIMIT 8";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $newProducts = $stmt->fetchAll();

        if ($newProducts): ?>
            <div class="carousel-wrap">
                <button class="carousel-arrow left" onclick="scrollCarousel('new-carousel', -600)"><i class="fas fa-chevron-left"></i></button>
                <div class="carousel-inner" id="new-carousel">
                    <div class="carousel-track">
                        <?php foreach ($newProducts as $product):
                            $price    = (float)$product['prix'];
                            $discount = (float)$product['promotion_pourcentage'];
                            // Si promotion > 0 → calcule le prix réduit, sinon prix normal
                            $finalPrice = $discount > 0 ? $price * (1 - $discount/100) : $price;
                            // Image produit depuis la BDD, sinon image par défaut
                            $img = $product['image_url'] ?: 'https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=800&q=80';
                        ?>
                            <div class="product-luxe-card" onclick="window.location='produit.php?id=<?php echo $product['id_produit']; ?>'">
                                <div class="product-luxe-img">
                                    <span class="product-luxe-badge">Nouveau</span>
                                    <?php if ($discount > 0): ?>
                                        <span class="product-luxe-badge promo" style="top:48px;">-<?php echo number_format($discount,0); ?>%</span>
                                    <?php endif; ?>
                                    <button class="product-luxe-wishlist btn-wishlist" data-product-id="<?php echo $product['id_produit']; ?>" onclick="event.stopPropagation()">
                                        <i class="far fa-heart"></i>
                                    </button>
                                    <!-- htmlspecialchars() protège contre les failles XSS (injection de code malveillant) -->
                                    <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($product['nom']); ?>">
                                </div>
                                <div class="product-luxe-body">
                                    <div class="product-luxe-brand"><?php echo htmlspecialchars($product['marque']); ?></div>
                                    <h3 class="product-luxe-name"><?php echo htmlspecialchars($product['nom']); ?></h3>
                                    <!-- mb_substr() coupe la description à 65 caractères (mb = gère les accents UTF-8) -->
                                    <p class="product-luxe-desc"><?php echo htmlspecialchars(mb_substr($product['description_courte'] ?? '', 0, 65)) . '...'; ?></p>
                                    <div class="product-luxe-footer" onclick="event.stopPropagation()">
                                        <div class="product-luxe-price">
                                            <?php if ($discount > 0): ?>
                                                <!-- number_format(val, 2, ',', ' ') → format français : 1 234,50 € -->
                                                <span class="price-old"><?php echo number_format($price, 2, ',', ' '); ?> €</span>
                                                <span class="price-new promo"><?php echo number_format($finalPrice, 2, ',', ' '); ?> €</span>
                                            <?php else: ?>
                                                <span class="price-new"><?php echo number_format($price, 2, ',', ' '); ?> €</span>
                                            <?php endif; ?>
                                        </div>
                                        <button class="btn-add-luxe btn-cart" data-product-id="<?php echo $product['id_produit']; ?>">
                                            <i class="fas fa-shopping-cart"></i> Ajouter
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="carousel-arrow right" onclick="scrollCarousel('new-carousel', 600)"><i class="fas fa-chevron-right"></i></button>
            </div>
        <?php else: ?>
            <p style="text-align:center; color:#999;">Aucun nouveau produit pour le moment.</p>
        <?php endif;
    } catch (PDOException $e) {
        echo '<p style="text-align:center;color:#e74c3c;">Erreur lors du chargement.</p>';
    } ?>
</section>

<!-- ==========================================
     SECTION BEST-SELLERS
     ========================================== -->
<section class="home-section bg-dark-luxury">
    <div class="section-header">
        <div class="section-eyebrow">✦ Best-sellers</div>
        <div class="section-divider"><span></span><i class="fas fa-fire"></i><span></span></div>
        <h2>Nos Incontournables</h2>
        <p>Les fragrances préférées de nos clients, plébiscitées saison après saison</p>
    </div>

    <?php
    try {
        // ORDER BY RAND() = ordre aléatoire à chaque rechargement de la page
        $query = "SELECT * FROM produits WHERE bestseller = 1 ORDER BY RAND() LIMIT 8";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $bestsellers = $stmt->fetchAll();

        if ($bestsellers): ?>
            <div class="carousel-wrap">
                <button class="carousel-arrow left" onclick="scrollCarousel('bs-carousel', -600)" style="background:#2c1810; color:#d4a017; border-color:#d4a017;"><i class="fas fa-chevron-left"></i></button>
                <div class="carousel-inner" id="bs-carousel">
                    <div class="carousel-track">
                        <?php foreach ($bestsellers as $product):
                            $price = (float)$product['prix'];
                            $discount = (float)$product['promotion_pourcentage'];
                            $finalPrice = $discount > 0 ? $price * (1 - $discount/100) : $price;
                            $img = $product['image_url'] ?: 'https://images.unsplash.com/photo-1590736969956-6d9c2a8d6972?auto=format&fit=crop&w=800&q=80';
                        ?>
                            <div class="product-luxe-card" onclick="window.location='produit.php?id=<?php echo $product['id_produit']; ?>'">
                                <div class="product-luxe-img">
                                    <span class="product-luxe-badge" style="background: linear-gradient(135deg,#e9c46a,#f4a261); color:#333;">⭐ Best-seller</span>
                                    <?php if ($discount > 0): ?>
                                        <span class="product-luxe-badge promo" style="top:48px;">-<?php echo number_format($discount,0); ?>%</span>
                                    <?php endif; ?>
                                    <button class="product-luxe-wishlist btn-wishlist" data-product-id="<?php echo $product['id_produit']; ?>" onclick="event.stopPropagation()">
                                        <i class="far fa-heart"></i>
                                    </button>
                                    <!-- htmlspecialchars() protège contre les failles XSS (injection de code malveillant) -->
                                    <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($product['nom']); ?>">
                                </div>
                                <div class="product-luxe-body">
                                    <div class="product-luxe-brand"><?php echo htmlspecialchars($product['marque']); ?></div>
                                    <h3 class="product-luxe-name"><?php echo htmlspecialchars($product['nom']); ?></h3>
                                    <!-- mb_substr() coupe la description à 65 caractères (mb = gère les accents UTF-8) -->
                                    <p class="product-luxe-desc"><?php echo htmlspecialchars(mb_substr($product['description_courte'] ?? '', 0, 65)) . '...'; ?></p>
                                    <div class="product-luxe-footer" onclick="event.stopPropagation()">
                                        <div class="product-luxe-price">
                                            <?php if ($discount > 0): ?>
                                                <!-- number_format(val, 2, ',', ' ') → format français : 1 234,50 € -->
                                                <span class="price-old"><?php echo number_format($price, 2, ',', ' '); ?> €</span>
                                                <span class="price-new promo"><?php echo number_format($finalPrice, 2, ',', ' '); ?> €</span>
                                            <?php else: ?>
                                                <span class="price-new"><?php echo number_format($price, 2, ',', ' '); ?> €</span>
                                            <?php endif; ?>
                                        </div>
                                        <button class="btn-add-luxe btn-cart" data-product-id="<?php echo $product['id_produit']; ?>">
                                            <i class="fas fa-shopping-cart"></i> Ajouter
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="carousel-arrow right" onclick="scrollCarousel('bs-carousel', 600)" style="background:#2c1810; color:#d4a017; border-color:#d4a017;"><i class="fas fa-chevron-right"></i></button>
            </div>
        <?php else: ?>
            <p style="text-align:center; color:rgba(255,255,255,0.5);">Aucun best-seller pour le moment.</p>
        <?php endif;
    } catch (PDOException $e) {
        echo '<p style="text-align:center;color:#e74c3c;">Erreur lors du chargement.</p>';
    } ?>

    <div style="text-align:center; margin-top: 50px;">
        <a href="catalogue.php?filter=bestseller" style="
            display: inline-flex; align-items: center; gap: 10px;
            border: 2px solid #d4a017; color: #d4a017;
            padding: 14px 40px; border-radius: 50px;
            text-decoration: none; font-weight: 600; font-size: 0.95em;
            transition: all 0.3s; letter-spacing: 0.5px;
        " onmouseover="this.style.background='#d4a017';this.style.color='white'"
           onmouseout="this.style.background='transparent';this.style.color='#d4a017'">
            Voir tous les best-sellers <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</section>

<!-- ==========================================
     BANDEAU CHIFFRES
     ========================================== -->
<div class="stats-banner">
    <div class="stats-grid">
        <div class="stat-item-luxe">
            <div class="stat-number">18+</div>
            <div class="stat-label">Parfums exclusifs</div>
        </div>
        <div class="stat-item-luxe">
            <div class="stat-number">1920</div>
            <div class="stat-label">Fondée en</div>
        </div>
        <div class="stat-item-luxe">
            <div class="stat-number">98%</div>
            <div class="stat-label">Clients satisfaits</div>
        </div>
        <div class="stat-item-luxe">
            <div class="stat-number">24h</div>
            <div class="stat-label">Expédition</div>
        </div>
    </div>
</div>

<!-- ==========================================
     SECTION À PROPOS
     ========================================== -->
<section class="home-section bg-light">
    <div class="about-luxe">
        <div class="about-luxe-img">
            <img src="https://images.unsplash.com/photo-1615634260167-c8cdede054de?auto=format&fit=crop&w=800&q=80" alt="L'histoire Lumoura">
        </div>
        <div class="about-luxe-content">
            <div class="section-eyebrow eyebrow">Notre Histoire</div>
            <h2>L'Héritage<br><em style="color:#d4a017; font-style:italic;">Lumoura</em></h2>
            <p>Depuis 1920, Lumoura crée des parfums d'exception qui racontent des histoires. Chaque fragrance est une œuvre d'art, mêlant tradition ancestrale et innovation contemporaine pour créer des émotions uniques.</p>

            <div class="about-features-luxe">
                <div class="about-feature-luxe">
                    <div class="about-feature-icon"><i class="fas fa-leaf"></i></div>
                    <div>
                        <h4>Ingrédients Naturels</h4>
                        <p>100% naturels et durables, sourcés éthiquement</p>
                    </div>
                </div>
                <div class="about-feature-luxe">
                    <div class="about-feature-icon"><i class="fas fa-hand-sparkles"></i></div>
                    <div>
                        <h4>Fabriqué Main</h4>
                        <p>Chaque flacon préparé avec soin et passion</p>
                    </div>
                </div>
                <div class="about-feature-luxe">
                    <div class="about-feature-icon"><i class="fas fa-shipping-fast"></i></div>
                    <div>
                        <h4>Livraison Express</h4>
                        <p>Expédition sous 24-48h partout en France</p>
                    </div>
                </div>
                <div class="about-feature-luxe">
                    <div class="about-feature-icon"><i class="fas fa-heart"></i></div>
                    <div>
                        <h4>Garantie Qualité</h4>
                        <p>Satisfaction garantie ou remboursé 30 jours</p>
                    </div>
                </div>
            </div>

            <a href="catalogue.php" class="btn-about">
                <i class="fas fa-store"></i> Découvrir notre collection
            </a>
        </div>
    </div>
</section>

<!-- ==========================================
     JAVASCRIPT
     =========================================
     5 fonctionnalités gérées :
     1. scrollCarousel()   → défilement du carrousel horizontal
     2. Hero Slider        → défilement auto + barre de progression
     3. IntersectionObserver → animations d'apparition au scroll
     4. btn-cart           → ajout au panier en AJAX (fetch)
     5. btn-wishlist       → gestion des favoris en AJAX (fetch)
     ========================================== -->
<script>
// ---- 1. CARROUSEL SCROLL ----
// Appelée via onclick sur les flèches : scrollCarousel('new-carousel', -600)
// id = identifiant HTML du carrousel | amount = pixels (négatif = gauche, positif = droite)
function scrollCarousel(id, amount) {
    document.getElementById(id).scrollBy({ left: amount, behavior: 'smooth' });
}

// Tout le code s'exécute après le chargement complet du DOM
document.addEventListener('DOMContentLoaded', function() {

    // ---- 2. HERO SLIDER ----
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.slider-dot');
    const prevBtn = document.querySelector('.slider-prev');
    const nextBtn = document.querySelector('.slider-next');
    const progress = document.getElementById('sliderProgress');
    let current = 0;

    // Affiche la slide à l'index "idx"
    function showSlide(idx) {
        slides.forEach(s => s.classList.remove('active'));
        dots.forEach(d => d.classList.remove('active'));
        // Le modulo assure la boucle : après slide 3 → retour slide 1
        current = (idx + slides.length) % slides.length;
        slides[current].classList.add('active');
        dots[current].classList.add('active');
        // Réinitialiser la barre de progression
        if (progress) {
            progress.classList.remove('animating');
            progress.style.width = '0%';
            void progress.offsetWidth; // Force le reflow (nécessaire pour relancer l'animation CSS)
            progress.classList.add('animating');
        }
    }

    if (prevBtn) prevBtn.addEventListener('click', () => { showSlide(current - 1); resetAutoSlide(); });
    if (nextBtn) nextBtn.addEventListener('click', () => { showSlide(current + 1); resetAutoSlide(); });
    dots.forEach((d, i) => d.addEventListener('click', () => { showSlide(i); resetAutoSlide(); }));

    // Démarrer la barre dès le chargement
    if (progress) {
        void progress.offsetWidth;
        progress.classList.add('animating');
    }

    // Défilement automatique toutes les 4 secondes (4000 millisecondes)
    let autoSlide = setInterval(() => showSlide(current + 1), 4000);

    function resetAutoSlide() {
        clearInterval(autoSlide);
        autoSlide = setInterval(() => showSlide(current + 1), 4000);
    }

    // ---- 3. ANIMATIONS D'APPARITION (IntersectionObserver) ----
    // Observe les éléments → les anime en fondu quand ils entrent dans la zone visible
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });
    document.querySelectorAll('.product-luxe-card, .cat-card-luxe, .about-feature-luxe, .stat-item-luxe').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(25px)';
        el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        observer.observe(el);
    });

    // ---- 4. AJOUT AU PANIER (requête AJAX fetch) ----
    document.querySelectorAll('.btn-cart').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation(); // Empêche le clic de remonter à la carte (qui redirige vers la page produit)
            const productId = this.getAttribute('data-product-id');
            if (!productId) return;

            const originalHTML = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.disabled = true;

            fetch('ajouter_au_panier.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body  : 'product_id=' + encodeURIComponent(productId) + '&quantity=1'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.innerHTML = '<i class="fas fa-check"></i> Ajouté !';
                    this.style.background = '#27ae60';
                    document.querySelectorAll('.cart-count, #cartCount').forEach(el => {
                        el.textContent = parseInt(el.textContent || 0) + 1;
                        el.style.transform = 'scale(1.4)';
                        setTimeout(() => el.style.transform = 'scale(1)', 300);
                    });
                    setTimeout(() => { this.innerHTML = originalHTML; this.style.background = ''; this.disabled = false; }, 2000);
                } else {
                    if (data.message?.includes('connecter')) {
                        if (confirm('Vous devez être connecté. Aller à la connexion ?')) window.location.href = 'connexion.php';
                        else { this.innerHTML = originalHTML; this.disabled = false; }
                    } else {
                        alert(data.message || 'Erreur'); this.innerHTML = originalHTML; this.disabled = false;
                    }
                }
            })
            .catch(() => { alert('Erreur réseau.'); this.innerHTML = originalHTML; this.disabled = false; });
        });
    });

    // ---- 5. FAVORIS / WISHLIST ----
    <?php echo 'const SITE_URL = "' . SITE_URL . '";'; ?>

    function animateNavFavoris(action, count) {
        const navFavLink = document.querySelector('.wishlist-btn');
        const navHeartIcon = navFavLink ? navFavLink.querySelector('i') : null;
        const wishlistCount = document.querySelector('.wishlist-count');
        if (navHeartIcon) {
            navHeartIcon.style.color = action === 'added' ? '#ef4444' : '';
            navHeartIcon.style.transform = 'scale(1.4)';
            setTimeout(() => { navHeartIcon.style.transform = 'scale(1)'; if(action==='removed') navHeartIcon.style.color=''; }, 400);
        }
        if (wishlistCount && count !== undefined) {
            wishlistCount.textContent = count;
            wishlistCount.style.display = count > 0 ? 'inline-flex' : 'none';
            wishlistCount.style.transform = 'scale(1.5)';
            setTimeout(() => wishlistCount.style.transform = 'scale(1)', 400);
        }
    }

    function loadWishlistState() {
        fetch(SITE_URL + 'pages/get-wishlist.php')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.favorites) {
                    document.querySelectorAll('.btn-wishlist').forEach(btn => {
                        const id = parseInt(btn.getAttribute('data-product-id'));
                        const icon = btn.querySelector('i');
                        if (!icon) return;
                        if (data.favorites.includes(id)) { icon.classList.replace('far','fas'); icon.style.color='#ef4444'; }
                        else { icon.classList.replace('fas','far'); icon.style.color=''; }
                    });
                    const wc = document.querySelector('.wishlist-count');
                    if (wc) { wc.textContent = data.count; wc.style.display = data.count > 0 ? 'inline-flex' : 'none'; }
                }
            }).catch(() => {});
    }
    loadWishlistState();

    document.querySelectorAll('.btn-wishlist').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault(); e.stopPropagation();
            const productId = this.getAttribute('data-product-id');
            if (!productId) return;
            this.style.pointerEvents = 'none';
            fetch(SITE_URL + 'pages/toggle_wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + encodeURIComponent(productId)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const icon = this.querySelector('i');
                    if (data.action === 'added') { icon.classList.replace('far','fas'); icon.style.color='#ef4444'; }
                    else { icon.classList.replace('fas','far'); icon.style.color=''; }
                    animateNavFavoris(data.action, data.wishlist_count);
                } else if (data.redirect) {
                    alert('Veuillez vous connecter pour ajouter des favoris');
                    setTimeout(() => window.location.href = 'connexion.php', 800);
                }
                this.style.pointerEvents = 'auto';
            })
            .catch(() => { this.style.pointerEvents = 'auto'; });
        });
    });

});
</script>

<script src="<?php echo htmlspecialchars(SITE_URL); ?>assets/js/quiz-premium.js" defer></script>

<?php
// Inclut footer.php → ferme les balises </main>, </body>, </html>
// et affiche le pied de page (liens, newsletter, réseaux sociaux...)
include '../includes/footer.php';
?>