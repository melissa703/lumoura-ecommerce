<?php
// ============================================================
// FICHIER : pages/admin/admin.php
// RÔLE    : ancienne page d'administration, remplacée par
//           dashboard.php, produits.php et commandes.php.
//           Ce fichier ne fait plus que rediriger, pour que les
//           anciens liens (livreurs.php, favoris...) fonctionnent.
// ============================================================

$tab = (string)($_GET['tab'] ?? 'dashboard');
$cibles = [
    'commandes' => 'commandes.php',
    'produits'  => 'produits.php',
    'archives'  => 'produits.php?view=archived',
    'ajouter'   => 'produits.php?new=1',
];
header('Location: ' . ($cibles[$tab] ?? 'dashboard.php'));
exit;