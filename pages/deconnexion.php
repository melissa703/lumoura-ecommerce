<?php
// ============================================================
// FICHIER : deconnexion.php
// RÔLE    : Déconnecte l'utilisateur en détruisant sa session
//           puis le redirige vers la page d'accueil
// ============================================================

/**
 * Page de déconnexion
 * Déconnecte l'utilisateur et redirige vers l'accueil
 */
session_start(); // Obligatoire pour accéder à $_SESSION et la détruire

// ÉTAPE 1 : Vider le tableau $_SESSION
// On écrase toutes les variables de session (user_id, nom, prenom, role, etc.)
// par un tableau vide → l'utilisateur n'est plus reconnu
$_SESSION = array();

// ÉTAPE 2 : Détruire la session côté serveur
// session_destroy() supprime le fichier de session sur le serveur
// (les sessions PHP sont stockées dans des fichiers temporaires sur le serveur)
session_destroy();

// ÉTAPE 3 : Rediriger vers l'accueil
// L'utilisateur est maintenant déconnecté et revient sur la page principale
header('Location: index.php');
exit(); // Toujours appeler exit() après un header('Location:...')
?>