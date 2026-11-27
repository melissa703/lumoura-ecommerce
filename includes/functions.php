<?php
// Fonctions utilitaires pour le site
// Ce fichier contient des fonctions réutilisables sur toutes les pages

/**
 * Vérifie si l'utilisateur est connecté
 * @return bool True si connecté, False sinon
 */
function isLoggedIn() {
    // Vérifie si la session 'user_id' existe (cela signifie que l'utilisateur est connecté)
    return isset($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur est administrateur
 * @return bool True si admin, False sinon
 */
function isAdmin() {
    // Vérifie si l'utilisateur est connecté ET si son rôle est 'admin'
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Formate un prix avec le symbole euro
 * @param float $price Le prix à formater
 * @return string Prix formaté (ex: "125,50 €")
 */
function formatPrice($price) {
    // Formate le prix avec 2 décimales, une virgule comme séparateur et le symbole €
    return number_format($price, 2, ',', ' ') . ' €';
}

/**
 * Calcule le prix après promotion
 * @param float $price Prix original
 * @param float $discount Pourcentage de réduction
 * @return float Prix après réduction
 */
function calculateDiscount($price, $discount) {
    // Calcule le prix réduit : prix original - (prix original * réduction / 100)
    return $price - ($price * $discount / 100);
}

/**
 * Affiche les étoiles pour la notation
 * @param int $rating Note de 1 à 5
 * @return string HTML des étoiles
 */
function displayRating($rating) {
    $html = '<div class="stars">';
    // Affiche 5 étoiles, les étoiles pleines selon la note
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $html .= '<i class="fas fa-star"></i>'; // Étoile pleine
        } else {
            $html .= '<i class="far fa-star"></i>'; // Étoile vide
        }
    }
    $html .= '</div>';
    return $html;
}

/**
 * Raccourcit un texte à une certaine longueur
 * @param string $text Texte à raccourcir
 * @param int $length Longueur maximale
 * @return string Texte raccourci
 */
function shortenText($text, $length = 100) {
    // Si le texte est plus long que la limite, on le coupe et ajoute "..."
    if (strlen($text) > $length) {
        return substr($text, 0, $length) . '...';
    }
    return $text;
}

/**
 * Sécurise une chaîne pour l'affichage HTML
 * @param string $string Chaîne à sécuriser
 * @return string Chaîne sécurisée
 */
function sanitize($string) {
    // Convertit les caractères spéciaux en entités HTML pour éviter les injections XSS
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Enregistre une action d'administration lorsqu'une table admin_logs existe.
 *
 * La fonction ne doit jamais bloquer une action métier si la table de journal
 * n'a pas encore été créée. Dans ce cas, l'information est envoyée dans le
 * journal PHP afin que l'administrateur puisse terminer la migration SQL.
 */
if (!function_exists('admin_log')) {
    function admin_log(PDO $pdo, int $adminId, string $action, string $details = ''): void {
        try {
            static $tableExists = null;

            if ($tableExists === null) {
                $check = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM information_schema.tables
                     WHERE table_schema = DATABASE()
                       AND table_name = 'admin_logs'"
                );
                $check->execute();
                $tableExists = (int)$check->fetchColumn() > 0;
            }

            if (!$tableExists) {
                error_log('[Lumoura admin log] Table admin_logs absente : ' . $action . ' - ' . $details);
                return;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO admin_logs (id_utilisateur, action, details, date_action)
                 VALUES (?, ?, ?, NOW())'
            );
            $stmt->execute([$adminId, $action, $details]);
        } catch (Throwable $e) {
            // Le journal ne doit jamais annuler une opération d'administration.
            error_log('[Lumoura admin log] ' . $e->getMessage());
        }
    }
}
?>