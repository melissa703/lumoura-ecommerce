<?php
// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'lumoura_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site configuration
define('SITE_NAME', 'Lumoura');
define('SITE_URL', 'http://localhost/lumoura/');

// Démarrer la session UNE SEULE FOIS ici
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}