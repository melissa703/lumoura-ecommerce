<?php
// ============================================================
// FICHIER : pages/admin/_layout.php
// RÔLE    : cadre commun des pages admin (barre latérale, messages
//           flash). Le style est dans assets/css/admin.css
// ============================================================

function flash_set(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function admin_header(string $title, array $admin, string $active = ''): void {
    global $pdo;
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    // Nombre de commandes à traiter (pastille dans le menu)
    $pending = 0;
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $pending = (int)$pdo->query("SELECT COUNT(*) FROM commandes WHERE statut = 'en attente'")->fetchColumn();
        } catch (Throwable $e) {
            error_log('[Lumoura admin layout] ' . $e->getMessage());
        }
    }

    $menu = [
        'dashboard' => ['dashboard.php',            'fa-chart-line',   'Tableau de bord'],
        'produits'  => ['produits.php',             'fa-spray-can',    'Produits'],
        'commandes' => ['commandes.php',             'fa-box',          'Commandes'],
        'livreurs'  => ['../livreurs.php',          'fa-motorcycle',   'Livreurs'],
    ];
    $prenom  = (string)($admin['prenom'] ?? 'Admin');
    $initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($prenom, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($prenom, 0, 1));
    $cssFile = __DIR__ . '/../../assets/css/admin.css';
    $cssVer  = is_file($cssFile) ? filemtime($cssFile) : 1;
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= h($title) ?> - Lumoura Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/admin.css?v=<?= (int)$cssVer ?>">
</head>
<body>
<div class="shell">
    <aside class="side" id="side">
        <div class="side-brand">
            <strong>LUMOURA</strong>
            <span>Administration</span>
        </div>
        <nav class="side-nav">
            <?php foreach ($menu as $key => [$url, $icon, $label]): ?>
                <a href="<?= h($url) ?>" class="<?= $active === $key ? 'on' : '' ?>">
                    <i class="fas <?= h($icon) ?>"></i><?= h($label) ?>
                    <?php if ($key === 'commandes' && $pending > 0): ?><span class="pill" title="Commandes en attente"><?= $pending ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="side-foot">
            <div class="side-user"><span class="avatar"><?= h($initial) ?></span><span><?= h($prenom) ?></span></div>
            <a href="../index.php"><i class="fas fa-store"></i>Voir la boutique</a>
            <a href="../deconnexion.php"><i class="fas fa-right-from-bracket"></i>Déconnexion</a>
        </div>
    </aside>

    <div class="content">
        <header class="mobilebar">
            <strong>LUMOURA</strong>
            <button class="burger" type="button" id="burger" aria-label="Ouvrir le menu"><i class="fas fa-bars"></i></button>
        </header>
        <main>
    <?php if ($flash): ?>
        <div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>"><?= h($flash['msg']) ?></div>
    <?php endif;
}

function admin_footer(): void {
    ?>
        </main>
    </div>
</div>
<script>
(function () {
    var b = document.getElementById('burger');
    if (!b) return;
    b.addEventListener('click', function () { document.body.classList.toggle('menu-open'); });
    document.querySelector('.content').addEventListener('click', function () { document.body.classList.remove('menu-open'); });
})();
</script>
</body>
</html>
<?php
}