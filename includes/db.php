<?php
// Connexion à la base de données

try {
    // Tentative de connexion à la base de données MySQL
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Mode d'erreur : lance des exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC // Récupère les données sous forme de tableau associatif
        ]
    );
    
    // Si la connexion réussit, on affiche rien (tout est bon)
} catch (PDOException $e) {
    // Si la connexion échoue, affiche un message d'erreur clair
    die('<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px;">
        <h3>Erreur de connexion à la base de données</h3>
        <p>Détails : ' . $e->getMessage() . '</p>
        <p>Vérifiez vos paramètres de connexion dans config.php</p>
    </div>');
}
?>