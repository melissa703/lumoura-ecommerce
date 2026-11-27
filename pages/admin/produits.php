<?php
// ============================================================
// FICHIER : pages/admin/produits.php
// RÔLE    : gestion des produits (liste, ajout, modification,
//           activation, mise en avant, stock, formats, image)
// SÉCURITÉ: accès admin vérifié en base, CSRF sur tout POST,
//           requêtes préparées, upload d'image contrôlé
// ============================================================

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';
require_once '../../includes/images.php';
require_once '_layout.php';

send_security_headers();
$admin = require_admin($pdo, '../connexion.php');

$CONCENTRATIONS = ['Eau Fraiche', 'Eau de Cologne', 'Eau de Toilette', 'Eau de Parfum', 'Parfum'];
$GENRES         = ['Homme', 'Femme', 'Unisexe'];
$IMG_DIR        = __DIR__ . '/../../assets/image';

// Les colonnes actif / en_avant viennent de sql/01_admin_fondations.sql
try {
    $pdo->query("SELECT actif, en_avant FROM produits LIMIT 1");
} catch (PDOException $e) {
    admin_header('Produits', $admin, 'produits');
    echo '<div class="card"><h2 class="page">Une étape manque</h2>'
       . '<p class="sub">Exécutez d\'abord le script <b>sql/01_admin_fondations.sql</b> dans phpMyAdmin '
       . '(base lumoura_db, onglet SQL), puis rechargez cette page.</p></div>';
    admin_footer();
    exit;
}

function post_str(string $key, int $max = 255): string {
    $v = $_POST[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}
function post_num(string $key): ?float {
    $v = str_replace(',', '.', post_str($key, 20));
    return is_numeric($v) ? (float)$v : null;
}

$errors   = [];
$formData = null;   // valeurs à réafficher si le formulaire est invalide
$editId   = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$showForm = $editId > 0 || isset($_GET['new']);

// ------------------------------------------------------------
// FILTRES DE LA LISTE (recherche, état, catégorie, tri)
// ------------------------------------------------------------
$FILTRES = [   // clé => [libellé, condition SQL]
    'low'     => ['Stock faible', 'p.stock > 0 AND p.stock <= COALESCE(p.seuil_alerte, 10)'],
    'rupture' => ['Rupture',      'p.stock <= 0'],
    'avant'   => ['En avant',     'p.en_avant = 1'],
    'promo'   => ['En promo',     'p.promotion_pourcentage > 0'],
    'nouveau' => ['Nouveautés',   'p.nouveaute = 1'],
];
$TRIS = [      // clé => [libellé, ORDER BY]
    'recent'     => ['Plus récents',        'p.id_produit DESC'],
    'nom'        => ['Nom (A → Z)',         'p.nom ASC'],
    'prix_asc'   => ['Prix croissant',      'COALESCE(pf.prix_min, p.prix) ASC'],
    'prix_desc'  => ['Prix décroissant',    'COALESCE(pf.prix_min, p.prix) DESC'],
    'stock_asc'  => ['Stock le plus bas',   'p.stock ASC'],
];
$srcFiltres = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $_GET;
$view = (($srcFiltres['view'] ?? 'active') === 'archived') ? 'archived' : 'active';
$q    = mb_substr(trim((string)($srcFiltres['q'] ?? '')), 0, 100);
$fil  = (string)($srcFiltres['f'] ?? '');
if (!isset($FILTRES[$fil])) $fil = '';
$catF = (int)($srcFiltres['cat'] ?? 0);
$sort = (string)($srcFiltres['sort'] ?? 'recent');
if (!isset($TRIS[$sort])) $sort = 'recent';

/** Adresse de la liste avec les filtres courants (+ éventuels changements). */
function produits_url(array $over = []): string {
    global $view, $q, $fil, $catF, $sort;
    $defaults = ['view' => 'active', 'q' => '', 'f' => '', 'cat' => 0, 'sort' => 'recent'];
    $p = array_merge(['view' => $view, 'q' => $q, 'f' => $fil, 'cat' => $catF, 'sort' => $sort], $over);
    foreach ($p as $k => $v) { if ($v === $defaults[$k]) unset($p[$k]); }
    return 'produits.php' . ($p ? '?' . http_build_query($p) : '');
}

// ------------------------------------------------------------
// TRAITEMENT DES ACTIONS (POST)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action', 30);
    $id     = (int)($_POST['id_produit'] ?? 0);

    try {
        if (in_array($action, ['toggle_actif', 'restore'], true) && $id > 0) {
            $current = $pdo->prepare("SELECT actif FROM produits WHERE id_produit = ?");
            $current->execute([$id]);
            $currentActif = $current->fetchColumn();

            if ($currentActif === false) {
                flash_set('err', 'Produit introuvable.');
            } else {
                $nextActif = $action === 'restore'
                    ? 1
                    : ((int)$currentActif === 1 ? 0 : 1);
                $pdo->prepare(
                    "UPDATE produits
                     SET actif = ?, en_avant = CASE WHEN ? = 0 THEN 0 ELSE en_avant END
                     WHERE id_produit = ?"
                )->execute([$nextActif, $nextActif, $id]);
                admin_log($pdo, (int)$admin['id_utilisateur'], $nextActif ? 'produit_restauration' : 'produit_archivage', "produit #$id");
                flash_set('ok', $nextActif ? 'Produit restauré dans le catalogue.' : 'Produit archivé.');
            }
            $redirectView = isset($nextActif) && (int)$nextActif === 1 ? 'active' : 'archived';
            header('Location: produits.php?view=' . $redirectView); exit;
        }

        if ($action === 'toggle_avant' && $id > 0) {
            $pdo->prepare("UPDATE produits SET en_avant = 1 - en_avant WHERE id_produit = ?")->execute([$id]);
            admin_log($pdo, (int)$admin['id_utilisateur'], 'produit_en_avant', "produit #$id");
            flash_set('ok', 'Mise en avant mise à jour.');
            header('Location: produits.php'); exit;
        }

        if (in_array($action, ['delete', 'archive'], true) && $id > 0) {
            $st = $pdo->prepare(
                "UPDATE produits
                 SET actif = 0, en_avant = 0
                 WHERE id_produit = ?"
            );
            $st->execute([$id]);

            if ($st->rowCount() === 0) {
                flash_set('err', 'Produit introuvable ou déjà archivé.');
            } else {
                admin_log($pdo, (int)$admin['id_utilisateur'], 'produit_archivage', "produit #$id");
                flash_set('ok', 'Produit archivé. Il reste récupérable depuis l’onglet Archives.');
            }
            header('Location: produits.php?view=archived'); exit;
        }

        if ($action === 'stock_set' && $id > 0) {
            // Ajustement rapide du stock : bouton - / + (delta) ou valeur saisie
            $delta = isset($_POST['delta']) ? (int)$_POST['delta'] : 0;
            if ($delta === 1 || $delta === -1) {
                $pdo->prepare("UPDATE produits SET stock = GREATEST(0, stock + ?) WHERE id_produit = ?")->execute([$delta, $id]);
            } else {
                $nouveau = max(0, (int)($_POST['stock'] ?? 0));
                $pdo->prepare("UPDATE produits SET stock = ? WHERE id_produit = ?")->execute([$nouveau, $id]);
            }
            admin_log($pdo, (int)$admin['id_utilisateur'], 'produit_stock', "produit #$id");
            header('Location: ' . produits_url() . '#p' . $id); exit;
        }

        if ($action === 'save') {
            $isNew = ($id === 0);
            $d = [
                'reference'             => mb_strtoupper(post_str('reference', 50)),
                'nom'                   => post_str('nom', 100),
                'marque'                => post_str('marque', 50) ?: 'Lumoura',
                'description'           => post_str('description', 5000),
                'description_courte'    => post_str('description_courte', 200),
                'id_categorie'          => (int)($_POST['id_categorie'] ?? 0),
                'prix'                  => post_num('prix'),
                'volume_ml'             => (int)($_POST['volume_ml'] ?? 0),
                'concentration'         => post_str('concentration', 30),
                'genre'                 => post_str('genre', 20),
                'famille_olfactive'     => post_str('famille_olfactive', 100),
                'stock'                 => (int)($_POST['stock'] ?? 0),
                'seuil_alerte'          => (int)($_POST['seuil_alerte'] ?? 10),
                'promotion_pourcentage' => post_num('promotion_pourcentage') ?? 0.0,
                'bestseller'            => isset($_POST['bestseller']) ? 1 : 0,
                'nouveaute'             => isset($_POST['nouveaute']) ? 1 : 0,
                'actif'                 => isset($_POST['actif']) ? 1 : 0,
                'en_avant'              => isset($_POST['en_avant']) ? 1 : 0,
            ];

            // Validation
            if ($d['nom'] === '')                          $errors[] = 'Le nom est obligatoire.';
            if ($d['reference'] === '')                    $errors[] = 'La référence est obligatoire (ex. LUM-F-021).';
            if ($d['prix'] === null || $d['prix'] <= 0)    $errors[] = 'Le prix doit être supérieur à 0.';
            if ($d['volume_ml'] <= 0)                      $errors[] = 'Le volume doit être supérieur à 0.';
            if ($d['stock'] < 0)                           $errors[] = 'Le stock ne peut pas être négatif.';
            if ($d['seuil_alerte'] < 0)                    $errors[] = "Le seuil d'alerte ne peut pas être négatif.";
            if ($d['promotion_pourcentage'] < 0 || $d['promotion_pourcentage'] > 90) $errors[] = 'La promotion doit être comprise entre 0 et 90 %.';
            if ($d['concentration'] !== '' && !in_array($d['concentration'], $CONCENTRATIONS, true)) $errors[] = 'Concentration invalide.';
            if (!in_array($d['genre'], $GENRES, true))     $errors[] = 'Genre invalide.';

            $cat = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id_categorie = ?");
            $cat->execute([$d['id_categorie']]);
            if (!(int)$cat->fetchColumn())                 $errors[] = 'Catégorie invalide.';

            $dup = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE reference = ? AND id_produit <> ?");
            $dup->execute([$d['reference'], $id]);
            if ((int)$dup->fetchColumn())                  $errors[] = 'Cette référence est déjà utilisée par un autre produit.';

            // Formats (volume + prix)
            $formats = [];
            $vols  = $_POST['fmt_volume'] ?? [];
            $prixs = $_POST['fmt_prix'] ?? [];
            if (is_array($vols) && is_array($prixs)) {
                foreach ($vols as $i => $v) {
                    $vol = (int)$v;
                    $pr  = str_replace(',', '.', (string)($prixs[$i] ?? ''));
                    if ($vol > 0 && is_numeric($pr) && (float)$pr > 0) {
                        $formats[$vol] = round((float)$pr, 2);
                    }
                }
            }

            // Image (facultative)
            $newImage = null;
            if (!$errors && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $newImage = '../assets/image/' . save_image_upload($_FILES['image'], $IMG_DIR);
                } catch (RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
            }

            if (!$errors) {
                $pdo->beginTransaction();
                $params = [
                    $d['reference'], $d['nom'], $d['description'], $d['description_courte'], $d['id_categorie'],
                    $d['marque'], $d['prix'], $d['volume_ml'], $d['concentration'] ?: null, $d['genre'],
                    $d['bestseller'], $d['nouveaute'], $d['promotion_pourcentage'], $d['stock'], $d['seuil_alerte'],
                    $d['famille_olfactive'] ?: null, $d['actif'], $d['en_avant'],
                ];
                if ($isNew) {
                    $pdo->prepare("INSERT INTO produits (reference, nom, description, description_courte, id_categorie,
                        marque, prix, volume_ml, concentration, genre, bestseller, nouveaute, promotion_pourcentage,
                        stock, seuil_alerte, famille_olfactive, actif, en_avant, image_url, date_ajout)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())")
                        ->execute(array_merge($params, [$newImage]));
                    $id = (int)$pdo->lastInsertId();
                } else {
                    $sql = "UPDATE produits SET reference=?, nom=?, description=?, description_courte=?, id_categorie=?,
                        marque=?, prix=?, volume_ml=?, concentration=?, genre=?, bestseller=?, nouveaute=?,
                        promotion_pourcentage=?, stock=?, seuil_alerte=?, famille_olfactive=?, actif=?, en_avant=?";
                    if ($newImage !== null) { $sql .= ", image_url=?"; $params[] = $newImage; }
                    $sql .= " WHERE id_produit=?";
                    $params[] = $id;
                    $pdo->prepare($sql)->execute($params);
                }

                // Formats : mise à jour ou ajout
                $existing = $pdo->prepare("SELECT id_format, volume_ml FROM produit_formats WHERE id_produit = ?");
                $existing->execute([$id]);
                $byVol = [];
                foreach ($existing->fetchAll() as $f) $byVol[(int)$f['volume_ml']] = (int)$f['id_format'];

                foreach ($formats as $vol => $pr) {
                    if (isset($byVol[$vol])) {
                        $pdo->prepare("UPDATE produit_formats SET prix = ? WHERE id_format = ?")->execute([$pr, $byVol[$vol]]);
                    } else {
                        $pdo->prepare("INSERT INTO produit_formats (id_produit, volume_ml, prix) VALUES (?,?,?)")->execute([$id, $vol, $pr]);
                    }
                }
                $pdo->commit();

                // Formats retirés du formulaire : supprimés seulement s'ils ne sont dans aucun panier
                $kept = true;
                foreach ($byVol as $vol => $idFormat) {
                    if (!isset($formats[$vol])) {
                        try {
                            $pdo->prepare("DELETE FROM produit_formats WHERE id_format = ?")->execute([$idFormat]);
                        } catch (PDOException $e) { $kept = false; }
                    }
                }

                admin_log($pdo, (int)$admin['id_utilisateur'], $isNew ? 'produit_creation' : 'produit_modification', "produit #$id {$d['nom']}");
                flash_set('ok', ($isNew ? 'Produit créé.' : 'Produit enregistré.')
                    . ($kept ? '' : ' Un format utilisé dans un panier a été conservé.'));
                header('Location: produits.php'); exit;
            }

            // Formulaire invalide : on le réaffiche avec les valeurs saisies
            $formData = $d + ['id_produit' => $id, 'formats' => $formats];
            $showForm = true;
            $editId   = $id;
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[Lumoura admin produits] ' . $e->getMessage());
        flash_set('err', "Une erreur est survenue, l'opération n'a pas été enregistrée.");
        header('Location: produits.php'); exit;
    }
}

// ------------------------------------------------------------
// DONNÉES D'AFFICHAGE
// ------------------------------------------------------------
$categories = $pdo->query("SELECT id_categorie, nom_categorie FROM categories ORDER BY ordre_affichage, nom_categorie")->fetchAll();

if ($showForm) {
    if ($formData === null) {
        if ($editId > 0) {
            $st = $pdo->prepare("SELECT * FROM produits WHERE id_produit = ?");
            $st->execute([$editId]);
            $formData = $st->fetch();
            if (!$formData) { flash_set('err', 'Produit introuvable.'); header('Location: produits.php'); exit; }
            $fs = $pdo->prepare("SELECT volume_ml, prix FROM produit_formats WHERE id_produit = ? ORDER BY volume_ml");
            $fs->execute([$editId]);
            $formData['formats'] = array_column($fs->fetchAll(), 'prix', 'volume_ml');
        } else {
            $formData = ['id_produit' => 0, 'reference' => '', 'nom' => '', 'marque' => 'Lumoura', 'description' => '',
                'description_courte' => '', 'id_categorie' => 0, 'prix' => '', 'volume_ml' => 100, 'concentration' => 'Eau de Parfum',
                'genre' => 'Unisexe', 'famille_olfactive' => '', 'stock' => 0, 'seuil_alerte' => 10,
                'promotion_pourcentage' => 0, 'bestseller' => 0, 'nouveaute' => 0, 'actif' => 1, 'en_avant' => 0,
                'image_url' => '', 'formats' => []];
        }
    }
}

$products = [];
$counts   = ['total' => 0, 'low' => 0, 'rupture' => 0, 'avant' => 0, 'promo' => 0, 'nouveau' => 0];
if (!$showForm) {
    // Base commune : état (actif/archivé) + recherche + catégorie
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $baseWhere = "p.actif = :actif
          AND (:qe = '' OR p.nom LIKE :q1 OR p.reference LIKE :q2 OR p.marque LIKE :q3)
          AND (:cat0 = 0 OR p.id_categorie = :cat1)";
    $baseParams = [
        ':actif' => $view === 'active' ? 1 : 0,
        ':qe' => $q, ':q1' => $like, ':q2' => $like, ':q3' => $like,
        ':cat0' => $catF, ':cat1' => $catF,
    ];

    // Compteurs des pastilles de filtre
    $cn = $pdo->prepare("SELECT COUNT(*) AS total,
            COALESCE(SUM(p.stock > 0 AND p.stock <= COALESCE(p.seuil_alerte, 10)), 0) AS low,
            COALESCE(SUM(p.stock <= 0), 0) AS rupture,
            COALESCE(SUM(p.en_avant = 1), 0) AS avant,
            COALESCE(SUM(p.promotion_pourcentage > 0), 0) AS promo,
            COALESCE(SUM(p.nouveaute = 1), 0) AS nouveau
        FROM produits p WHERE $baseWhere");
    $cn->execute($baseParams);
    $counts = array_map('intval', $cn->fetch(PDO::FETCH_ASSOC) ?: $counts);

    // Liste (avec le plus petit prix des formats pour « Dès X € » et la liste des volumes)
    $filtreSql = $fil !== '' ? ' AND ' . $FILTRES[$fil][1] : '';
    $st = $pdo->prepare("SELECT p.*, c.nom_categorie, pf.prix_min, pf.volumes
        FROM produits p
        LEFT JOIN categories c ON c.id_categorie = p.id_categorie
        LEFT JOIN (SELECT id_produit, MIN(prix) AS prix_min,
                          GROUP_CONCAT(volume_ml ORDER BY volume_ml SEPARATOR ' · ') AS volumes
                   FROM produit_formats GROUP BY id_produit) pf ON pf.id_produit = p.id_produit
        WHERE $baseWhere $filtreSql
        ORDER BY {$TRIS[$sort][1]} LIMIT 300");
    $st->execute($baseParams);
    $products = $st->fetchAll();
}

admin_header($showForm ? ($editId ? 'Modifier un produit' : 'Nouveau produit') : 'Produits', $admin, 'produits');
?>

<?php if (!$showForm): /* ================= LISTE ================= */ ?>

    <div class="toolbar">
        <div>
            <h2 class="page">Produits</h2>
            <p class="sub"><?= count($products) ?> produit(s) <?= $view === 'archived' ? 'archivé(s)' : 'en ligne' ?><?= ($q !== '' || $fil !== '' || $catF > 0) ? ' · filtres actifs' : '' ?></p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="btn <?= $view === 'active' ? 'btn-gold' : 'btn-ghost' ?>" href="<?= h(produits_url(['view' => 'active', 'f' => ''])) ?>"><i class="fas fa-store"></i> En ligne</a>
            <a class="btn <?= $view === 'archived' ? 'btn-gold' : 'btn-ghost' ?>" href="<?= h(produits_url(['view' => 'archived', 'f' => ''])) ?>"><i class="fas fa-box-archive"></i> Archives</a>
            <a class="btn btn-gold" href="produits.php?new=1"><i class="fas fa-plus"></i> Nouveau produit</a>
        </div>
    </div>

    <form method="get" class="filterbar">
        <input type="hidden" name="view" value="<?= h($view) ?>">
        <?php if ($fil !== ''): ?><input type="hidden" name="f" value="<?= h($fil) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= h($q) ?>" placeholder="Rechercher : nom, référence, marque…">
        <select name="cat" onchange="this.form.submit()" aria-label="Catégorie">
            <option value="0">Toutes les catégories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id_categorie'] ?>" <?= $catF === (int)$c['id_categorie'] ? 'selected' : '' ?>><?= h($c['nom_categorie']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="sort" onchange="this.form.submit()" aria-label="Trier par">
            <?php foreach ($TRIS as $key => [$lab, $sql]): ?>
                <option value="<?= h($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= h($lab) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-ghost" type="submit"><i class="fas fa-search"></i> Rechercher</button>
        <?php if ($q !== '' || $catF > 0 || $fil !== '' || $sort !== 'recent'): ?>
            <a class="btn btn-ghost" href="<?= h(produits_url(['q' => '', 'f' => '', 'cat' => 0, 'sort' => 'recent'])) ?>">Réinitialiser</a>
        <?php endif; ?>
    </form>

    <div class="chips">
        <a class="chip <?= $fil === '' ? 'on' : '' ?>" href="<?= h(produits_url(['f' => ''])) ?>">Tous <small><?= $counts['total'] ?></small></a>
        <?php foreach ($FILTRES as $key => [$lab, $sql]): ?>
            <a class="chip <?= $fil === $key ? 'on' : '' ?>" href="<?= h(produits_url(['f' => $key])) ?>"><?= h($lab) ?> <small><?= (int)$counts[$key] ?></small></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$products): ?>
        <div class="card empty-state">
            <i class="fas fa-spray-can-sparkles"></i>
            <p><?= $view === 'archived' ? 'Aucun produit archivé.' : 'Aucun produit ne correspond à ces filtres.' ?></p>
            <?php if ($view === 'active'): ?><a class="btn btn-gold" href="produits.php?new=1"><i class="fas fa-plus"></i> Ajouter un produit</a><?php endif; ?>
        </div>
    <?php else: ?>
    <div class="pgrid">
        <?php foreach ($products as $i => $p):
            $pid     = (int)$p['id_produit'];
            $stock   = (int)$p['stock'];
            $seuil   = (int)($p['seuil_alerte'] ?? 10);
            $rupture = $stock <= 0;
            $low     = !$rupture && $stock <= $seuil;
            $promo   = (float)$p['promotion_pourcentage'];
            $hasFmt  = $p['prix_min'] !== null;
            $prixBase  = $hasFmt ? (float)$p['prix_min'] : (float)$p['prix'];
            $prixFinal = $promo > 0 ? $prixBase * (1 - $promo / 100) : $prixBase; ?>
        <article class="pcard <?= $view === 'archived' ? 'is-archived' : '' ?>" id="p<?= $pid ?>" style="--i:<?= min($i, 12) ?>">
            <a class="pimg" href="produits.php?edit=<?= $pid ?>" aria-label="Modifier <?= h($p['nom']) ?>">
                <img src="<?= h(product_image_url($p['image_url'])) ?>" alt="" loading="lazy">
                <span class="pbadges">
                    <?php if ($promo > 0): ?><span class="pb pb-promo">-<?= h(number_format($promo, 0)) ?>%</span><?php endif; ?>
                    <?php if ($p['nouveaute']): ?><span class="pb pb-new">Nouveau</span><?php endif; ?>
                    <?php if ($p['bestseller']): ?><span class="pb pb-best">Best-seller</span><?php endif; ?>
                </span>
                <?php if ($p['en_avant']): ?><span class="pstar" title="Mis en avant"><i class="fas fa-star"></i></span><?php endif; ?>
                <span class="pedit"><i class="fas fa-pen"></i> Modifier</span>
            </a>
            <div class="pbody">
                <div class="pcat"><?= h($p['nom_categorie'] ?? 'Sans catégorie') ?><?= $p['famille_olfactive'] ? ' · ' . h($p['famille_olfactive']) : '' ?></div>
                <h3 class="pname"><?= h($p['nom']) ?></h3>
                <div class="pref"><?= h($p['reference']) ?> · <?= h($p['marque']) ?></div>
                <div class="pprice">
                    <?php if ($hasFmt): ?><small>Dès</small><?php endif; ?>
                    <b><?= h(formatPrice($prixFinal)) ?></b>
                    <?php if ($promo > 0): ?><s><?= h(formatPrice($prixBase)) ?></s><?php endif; ?>
                </div>
                <?php if (!empty($p['volumes'])): ?><div class="pvols"><?= h($p['volumes']) ?> ml</div><?php endif; ?>

                <form method="post" class="stockbox <?= $rupture ? 'is-out' : ($low ? 'is-low' : '') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="stock_set">
                    <input type="hidden" name="id_produit" value="<?= $pid ?>">
                    <input type="hidden" name="view" value="<?= h($view) ?>">
                    <input type="hidden" name="q" value="<?= h($q) ?>">
                    <input type="hidden" name="f" value="<?= h($fil) ?>">
                    <input type="hidden" name="cat" value="<?= (int)$catF ?>">
                    <input type="hidden" name="sort" value="<?= h($sort) ?>">
                    <?php /* Le bouton ✓ est le premier du formulaire : la touche Entrée dans le champ enregistre la valeur saisie. L'ordre d'affichage est géré en CSS. */ ?>
                    <button type="submit" class="stocksave" title="Enregistrer la quantité saisie" aria-label="Enregistrer"><i class="fas fa-check"></i></button>
                    <span class="stocklabel"><?= $rupture ? 'Rupture' : ($low ? 'Stock faible' : 'Stock') ?></span>
                    <button type="submit" name="delta" value="-1" class="stockminus" aria-label="Retirer 1">−</button>
                    <input type="number" name="stock" min="0" value="<?= $stock ?>" aria-label="Quantité en stock">
                    <button type="submit" name="delta" value="1" class="stockplus" aria-label="Ajouter 1">+</button>
                </form>

                <div class="pactions">
                    <a class="btn btn-ghost btn-sm" href="produits.php?edit=<?= $pid ?>"><i class="fas fa-pen"></i> Modifier</a>
                    <?php if ($view === 'active'): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_avant"><input type="hidden" name="id_produit" value="<?= $pid ?>">
                            <button class="iconbtn <?= $p['en_avant'] ? 'is-on' : '' ?>" type="submit" title="<?= $p['en_avant'] ? 'Retirer de la mise en avant' : 'Mettre en avant' ?>"><i class="fas fa-star"></i></button></form>
                        <form method="post" onsubmit="return confirm('Archiver ce produit ? Il restera récupérable depuis les archives.')"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="id_produit" value="<?= $pid ?>">
                            <button class="iconbtn iconbtn-danger" type="submit" title="Archiver"><i class="fas fa-box-archive"></i></button></form>
                    <?php else: ?>
                        <form method="post" onsubmit="return confirm('Restaurer ce produit dans le catalogue ?')"><?= csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="id_produit" value="<?= $pid ?>">
                            <button class="btn btn-gold btn-sm" type="submit"><i class="fas fa-rotate-left"></i> Restaurer</button></form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php else: /* ================= FORMULAIRE ================= */ ?>

    <div class="toolbar">
        <div>
            <h2 class="page"><?= $editId ? 'Modifier un produit' : 'Nouveau produit' ?></h2>
            <p class="sub">Les champs marqués d'une étoile sont obligatoires.</p>
        </div>
        <a class="btn btn-ghost" href="produits.php">← Retour à la liste</a>
    </div>

    <?php foreach ($errors as $e): ?><div class="flash err"><?= h($e) ?></div><?php endforeach; ?>

    <form method="post" enctype="multipart/form-data" class="card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id_produit" value="<?= (int)$formData['id_produit'] ?>">

        <div class="grid">
            <div><label>Nom *</label><input type="text" name="nom" maxlength="100" required value="<?= h($formData['nom']) ?>"></div>
            <div><label>Référence *</label><input type="text" name="reference" maxlength="50" required placeholder="LUM-F-021" value="<?= h($formData['reference']) ?>"></div>
            <div><label>Marque</label><input type="text" name="marque" maxlength="50" value="<?= h($formData['marque']) ?>"></div>
            <div><label>Catégorie *</label>
                <select name="id_categorie" required>
                    <option value="">Choisir…</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id_categorie'] ?>" <?= (int)$formData['id_categorie'] === (int)$c['id_categorie'] ? 'selected' : '' ?>><?= h($c['nom_categorie']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label>Genre *</label>
                <select name="genre"><?php foreach ($GENRES as $g): ?>
                    <option <?= $formData['genre'] === $g ? 'selected' : '' ?>><?= h($g) ?></option><?php endforeach; ?></select></div>
            <div><label>Concentration</label>
                <select name="concentration"><option value="">-</option><?php foreach ($CONCENTRATIONS as $c): ?>
                    <option <?= $formData['concentration'] === $c ? 'selected' : '' ?>><?= h($c) ?></option><?php endforeach; ?></select></div>
            <div><label>Famille olfactive</label><input type="text" name="famille_olfactive" maxlength="100" placeholder="Florale, Boisée…" value="<?= h($formData['famille_olfactive']) ?>"></div>
            <div><label>Prix (€) *</label><input type="text" inputmode="decimal" name="prix" required value="<?= h($formData['prix']) ?>"></div>
            <div><label>Volume de base (ml) *</label><input type="number" name="volume_ml" min="1" required value="<?= (int)$formData['volume_ml'] ?>"></div>
            <div><label>Promotion (%)</label><input type="text" inputmode="decimal" name="promotion_pourcentage" value="<?= h($formData['promotion_pourcentage']) ?>"></div>
            <div><label>Stock</label><input type="number" name="stock" min="0" value="<?= (int)$formData['stock'] ?>"></div>
            <div><label>Seuil d'alerte stock</label><input type="number" name="seuil_alerte" min="0" value="<?= (int)$formData['seuil_alerte'] ?>"></div>

            <div class="full"><label>Description courte</label><input type="text" name="description_courte" maxlength="200" value="<?= h($formData['description_courte']) ?>"></div>
            <div class="full"><label>Description</label><textarea name="description" rows="5" maxlength="5000"><?= h($formData['description']) ?></textarea></div>

            <div class="full">
                <label>Image du produit (JPG, PNG ou WebP, 3 Mo max)</label>
                <?php if (!empty($formData['image_url'])): ?>
                    <img class="thumb" style="width:90px;height:90px;margin-bottom:8px" src="<?= h(product_image_url($formData['image_url'])) ?>" alt="">
                <?php endif; ?>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                <p class="hint">Laissez vide pour conserver l'image actuelle.</p>
            </div>

            <div class="full">
                <label>Formats et prix (30 ml, 50 ml, 100 ml…)</label>
                <div id="fmts">
                    <?php $rows = $formData['formats'] ?: ['' => '']; foreach ($rows as $vol => $pr): ?>
                        <div class="fmt-row">
                            <input type="number" name="fmt_volume[]" min="1" placeholder="Volume (ml)" value="<?= h($vol) ?>">
                            <input type="text" inputmode="decimal" name="fmt_prix[]" placeholder="Prix (€)" value="<?= h($pr) ?>">
                            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentNode.remove()">✕</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" id="addFmt">+ Ajouter un format</button>
            </div>

            <div class="full" style="display:flex;gap:22px;flex-wrap:wrap">
                <label style="text-transform:none;letter-spacing:0;font-size:.9rem"><input type="checkbox" name="actif" <?= $formData['actif'] ? 'checked' : '' ?>> En ligne</label>
                <label style="text-transform:none;letter-spacing:0;font-size:.9rem"><input type="checkbox" name="en_avant" <?= $formData['en_avant'] ? 'checked' : '' ?>> Mis en avant</label>
                <label style="text-transform:none;letter-spacing:0;font-size:.9rem"><input type="checkbox" name="bestseller" <?= $formData['bestseller'] ? 'checked' : '' ?>> Best-seller</label>
                <label style="text-transform:none;letter-spacing:0;font-size:.9rem"><input type="checkbox" name="nouveaute" <?= $formData['nouveaute'] ? 'checked' : '' ?>> Nouveauté</label>
            </div>
        </div>

        <div style="margin-top:22px;display:flex;gap:12px">
            <button class="btn btn-gold" type="submit">Enregistrer</button>
            <a class="btn btn-ghost" href="produits.php">Annuler</a>
        </div>
    </form>

    <script>
    document.getElementById('addFmt').addEventListener('click', function () {
        var row = document.createElement('div');
        row.className = 'fmt-row';
        row.innerHTML = '<input type="number" name="fmt_volume[]" min="1" placeholder="Volume (ml)">'
            + '<input type="text" inputmode="decimal" name="fmt_prix[]" placeholder="Prix (€)">'
            + '<button type="button" class="btn btn-danger btn-sm" onclick="this.parentNode.remove()">✕</button>';
        document.getElementById('fmts').appendChild(row);
    });
    </script>

<?php endif;
admin_footer();