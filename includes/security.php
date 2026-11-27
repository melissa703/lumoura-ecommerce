<?php
// ============================================================
// includes/security.php : fonctions de sécurité communes
// - en-têtes HTTP de sécurité
// - protection CSRF (jeton de session)
// - échappement HTML
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ------------------------------------------------------------
// En-têtes HTTP de sécurité (à appeler avant tout affichage)
// ------------------------------------------------------------
if (!function_exists('send_security_headers')) {
    function send_security_headers(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Frame-Options: SAMEORIGIN');            // anti-clickjacking
        header('X-Content-Type-Options: nosniff');        // pas de devinette du type MIME
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cache-Control: no-store');                // pages admin : pas de cache
    }
}

// ------------------------------------------------------------
// Échappement HTML (au cas où h() n'existe pas déjà ailleurs)
// ------------------------------------------------------------
if (!function_exists('h')) {
    function h($valeur): string
    {
        return htmlspecialchars((string)$valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// ------------------------------------------------------------
// CSRF
// ------------------------------------------------------------
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

// Champ caché à mettre dans chaque formulaire POST
if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
    }
}

// Vérification à appeler au début du traitement d'un POST
if (!function_exists('csrf_check')) {
    function csrf_check(): void
    {
        $envoye = $_POST['csrf_token'] ?? '';
        $attendu = $_SESSION['csrf_token'] ?? '';

        if (!is_string($envoye) || $attendu === '' || !hash_equals($attendu, $envoye)) {
            http_response_code(403);
            exit('Requête refusée : jeton de sécurité invalide. Recharge la page et réessaie.');
        }
    }
}

// ------------------------------------------------------------
// Contrôle d'accès admin : à appeler en haut des pages admin
// Usage : $admin = require_admin($pdo, '../connexion.php');
// - non connecté       -> redirection vers la page de connexion
// - connecté non admin -> erreur 403
// - renvoie la ligne de la table utilisateurs de l'admin
// ------------------------------------------------------------
if (!function_exists('require_admin')) {
    function require_admin(PDO $pdo, string $loginUrl = '../connexion.php'): array
    {
        $id = (int)($_SESSION['user_id'] ?? 0);

        if ($id <= 0) {
            header('Location: ' . $loginUrl);
            exit;
        }

        $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE id_utilisateur = ?');
        $stmt->execute([$id]);
        $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$utilisateur
            || ($utilisateur['role'] ?? '') !== 'admin'
            || ($utilisateur['statut'] ?? 'actif') !== 'actif'
        ) {
            http_response_code(403);
            exit('Accès refusé : cette page est réservée aux administrateurs.');
        }

        return $utilisateur;
    }
}
