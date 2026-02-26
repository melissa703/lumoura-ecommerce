<?php
// Script pour créer et peupler la base de données
require_once 'includes/config.php';

try {
    // Connexion sans base de données spécifiée
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Créer la base de données si elle n'existe pas
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);
    
    echo "<h2>Base de données créée avec succès</h2>";
    
    // Lire et exécuter le fichier SQL
    $sql_file = 'database_complete.sql';
    if (file_exists($sql_file)) {
        $sql = file_get_contents($sql_file);
        
        // Exécuter les requêtes une par une
        $queries = explode(';', $sql);
        $success_count = 0;
        $error_count = 0;
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                try {
                    $pdo->exec($query);
                    $success_count++;
                } catch (PDOException $e) {
                    echo "<p style='color: orange;'>⚠️ Requête ignorée: " . substr($query, 0, 100) . "...</p>";
                    $error_count++;
                }
            }
        }
        
        echo "<p style='color: green;'>✅ $success_count requêtes exécutées avec succès</p>";
        if ($error_count > 0) {
            echo "<p style='color: orange;'>⚠️ $error_count requêtes ont été ignorées (tables déjà existantes)</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Fichier SQL non trouvé: $sql_file</p>";
        echo "<p>Créez un fichier database_complete.sql avec le script SQL ci-dessus</p>";
    }
    
    // Vérifier les tables créées
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Tables créées dans la base " . DB_NAME . ":</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        // Compter le nombre d'enregistrements dans chaque table
        $count_stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $count_stmt->fetch()['count'];
        echo "<li>$table ($count enregistrements)</li>";
    }
    echo "</ul>";
    
    // Afficher quelques produits
    echo "<h3>Produits ajoutés:</h3>";
    $products_stmt = $pdo->query("SELECT nom, prix, stock FROM produits LIMIT 5");
    $products = $products_stmt->fetchAll();
    
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Nom</th><th>Prix</th><th>Stock</th></tr>";
    foreach ($products as $product) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($product['nom']) . "</td>";
        echo "<td>" . $product['prix'] . " €</td>";
        echo "<td>" . $product['stock'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Informations de connexion:</h3>";
    echo "<p>Admin: admin@lumoura.fr / admin123</p>";
    echo "<p>Clients: Voir les utilisateurs dans la base</p>";
    
} catch (PDOException $e) {
    die("<div style='color: red; padding: 20px; border: 1px solid red;'>
        <h3>Erreur de connexion à MySQL</h3>
        <p><strong>Message:</strong> " . $e->getMessage() . "</p>
        <p><strong>Vérifiez:</strong></p>
        <ul>
            <li>Que MySQL est en cours d'exécution</li>
            <li>Les identifiants dans includes/config.php</li>
            <li>Que l'utilisateur MySQL a les droits suffisants</li>
        </ul>
    </div>");
}
?>

<style>
    body {
        font-family: Arial, sans-serif;
        padding: 20px;
        background: #f5f5f5;
    }
    h2, h3 {
        color: #333;
    }
    table {
        border-collapse: collapse;
        margin: 20px 0;
        background: white;
    }
    th {
        background: #4CAF50;
        color: white;
        padding: 10px;
    }
    td {
        padding: 8px;
        border: 1px solid #ddd;
    }
</style>