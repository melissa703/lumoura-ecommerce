<?php
// ============================================================
// FICHIER : pages/admin/commandes.php
// RÔLE    : gestion des commandes (liste, recherche, filtre par
//           statut, détail des articles, changement de statut
//           avec e-mail automatique au client)
// SÉCURITÉ: accès admin vérifié en base, CSRF sur tout POST,
//           requêtes préparées, statuts en liste blanche
// ============================================================

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';
require_once '../../includes/images.php';
require_once '../../includes/envoyer-email.php';
require_once '_layout.php';

send_security_headers();
$admin = require_admin($pdo, '../connexion.php');

// statut en base => [libellé, classe CSS]
$STATUTS = [
    'en attente' => ['En attente', 'st-attente'],
    'payee'      => ['Payée',      'st-payee'],
    'expediee'   => ['Expédiée',   'st-expediee'],
    'livree'     => ['Livrée',     'st-livree'],
    'annulee'    => ['Annulée',    'st-annulee'],
];
$NOTIFIABLES = ['expediee', 'livree', 'annulee'];   // statuts qui préviennent le client par e-mail
$PAR_PAGE    = 20;

// Filtres (GET) : recherche, statut, page
$q = trim((string)($_GET['q'] ?? ''));
$f = (string)($_GET['f'] ?? '');
if ($f !== '' && !isset($STATUTS[$f])) $f = '';
$page = max(1, (int)($_GET['p'] ?? 1));

/** Adresse de la page avec les filtres courants (pour les liens et les retours après action). */
function cmd_url(array $over = []): string {
    global $q, $f, $page;
    $params = array_merge(['q' => $q, 'f' => $f, 'p' => $page], $over);
    $params = array_filter($params, static fn($v) => $v !== '' && $v !== 1 && $v !== null);
    return 'commandes.php' . ($params ? '?' . http_build_query($params) : '');
}

// ------------------------------------------------------------
// ACTION : changement de statut (POST)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $idCommande = (int)($_POST['id_commande'] ?? 0);
    $statut     = (string)($_POST['statut'] ?? '');

    // On revient sur la même vue (mêmes filtres) après l'action
    $q    = trim((string)($_POST['q'] ?? ''));
    $f    = (string)($_POST['f'] ?? '');
    if ($f !== '' && !isset($STATUTS[$f])) $f = '';
    $page = max(1, (int)($_POST['p'] ?? 1));

    if ($idCommande <= 0 || !isset($STATUTS[$statut])) {
        flash_set('err', 'Statut invalide.');
        header('Location: ' . cmd_url()); exit;
    }

    try {
        $old = $pdo->prepare(
            "SELECT c.statut, c.numero_commande, u.email, u.prenom
             FROM commandes c JOIN utilisateurs u ON u.id_utilisateur = c.id_utilisateur
             WHERE c.id_commande = ?"
        );
        $old->execute([$idCommande]);
        $data = $old->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            flash_set('err', 'Commande introuvable.');
            header('Location: ' . cmd_url()); exit;
        }

        if ($data['statut'] === $statut) {
            flash_set('ok', 'Le statut était déjà « ' . $STATUTS[$statut][0] . ' ».');
            header('Location: ' . cmd_url()); exit;
        }

        $pdo->prepare("UPDATE commandes SET statut = ? WHERE id_commande = ?")->execute([$statut, $idCommande]);
        admin_log($pdo, (int)$admin['id_utilisateur'], 'commande_statut', "commande #$idCommande : {$data['statut']} -> $statut");

        $msg = 'Commande ' . $data['numero_commande'] . ' : statut « ' . $STATUTS[$statut][0] . ' ».';
        $type = 'ok';
        if (in_array($statut, $NOTIFIABLES, true)) {
            $envoye = envoyerEmailStatut($data['email'], $data['prenom'], $data['numero_commande'], $statut);
            if ($envoye) {
                $msg .= ' E-mail envoyé au client.';
            } else {
                $msg .= " Attention : l'e-mail au client n'a pas pu partir (vérifie la configuration SMTP).";
                $type = 'err';
                error_log("[Lumoura admin commandes] échec e-mail commande #$idCommande");
            }
        }
        flash_set($type, $msg);
    } catch (PDOException $e) {
        error_log('[Lumoura admin commandes] ' . $e->getMessage());
        flash_set('err', "Une erreur est survenue, le statut n'a pas été modifié.");
    }
    header('Location: ' . cmd_url()); exit;
}

// ------------------------------------------------------------
// DONNÉES D'AFFICHAGE
// ------------------------------------------------------------
// Compteurs par statut (pastilles de filtre)
$counts = array_fill_keys(array_keys($STATUTS), 0);
foreach ($pdo->query("SELECT statut, COUNT(*) AS n FROM commandes GROUP BY statut")->fetchAll() as $r) {
    if (isset($counts[$r['statut']])) $counts[$r['statut']] = (int)$r['n'];
}
$totalAll = array_sum($counts);

// Filtres SQL
$where  = [];
$params = [];
if ($f !== '') { $where[] = 'c.statut = :statut'; $params[':statut'] = $f; }
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(c.numero_commande LIKE :q1 OR u.nom LIKE :q2 OR u.prenom LIKE :q3 OR u.email LIKE :q4)';
    $params[':q1'] = $params[':q2'] = $params[':q3'] = $params[':q4'] = $like;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM commandes c JOIN utilisateurs u ON u.id_utilisateur = c.id_utilisateur $whereSql");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / $PAR_PAGE));
if ($page > $pages) $page = $pages;

$st = $pdo->prepare(
    "SELECT c.*, u.prenom, u.nom, u.email
     FROM commandes c JOIN utilisateurs u ON u.id_utilisateur = c.id_utilisateur
     $whereSql
     ORDER BY c.date_commande DESC, c.id_commande DESC
     LIMIT :lim OFFSET :off"
);
foreach ($params as $k => $v) $st->bindValue($k, $v);
$st->bindValue(':lim', $PAR_PAGE, PDO::PARAM_INT);
$st->bindValue(':off', ($page - 1) * $PAR_PAGE, PDO::PARAM_INT);
$st->execute();
$commandes = $st->fetchAll();

// Articles des commandes affichées (une seule requête)
$articles = [];
if ($commandes) {
    $ids = array_map(static fn($c) => (int)$c['id_commande'], $commandes);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $da  = $pdo->prepare(
        "SELECT d.id_commande, d.quantite, d.prix_unitaire, p.nom, p.image_url
         FROM details_commande d JOIN produits p ON p.id_produit = d.id_produit
         WHERE d.id_commande IN ($in) ORDER BY d.id_detail"
    );
    $da->execute($ids);
    foreach ($da->fetchAll() as $a) $articles[(int)$a['id_commande']][] = $a;
}

admin_header('Commandes', $admin, 'commandes');
?>

    <div class="toolbar">
        <div>
            <h2 class="page">Commandes</h2>
            <p class="sub"><?= $total ?> commande(s)<?= ($q !== '' || $f !== '') ? ' correspondant à votre recherche' : '' ?> · un e-mail part automatiquement au client quand la commande passe à Expédiée, Livrée ou Annulée.</p>
        </div>
        <form method="get">
            <?php if ($f !== ''): ?><input type="hidden" name="f" value="<?= h($f) ?>"><?php endif; ?>
            <input type="search" name="q" value="<?= h($q) ?>" placeholder="N° de commande, client, e-mail…" style="width:260px">
            <button class="btn btn-ghost" type="submit"><i class="fas fa-search"></i> Rechercher</button>
            <?php if ($q !== ''): ?><a class="btn btn-ghost" href="<?= h(cmd_url(['q' => '', 'p' => 1])) ?>">Effacer</a><?php endif; ?>
        </form>
    </div>

    <div class="chips">
        <a class="chip <?= $f === '' ? 'on' : '' ?>" href="<?= h(cmd_url(['f' => '', 'p' => 1])) ?>">Toutes <small><?= $totalAll ?></small></a>
        <?php foreach ($STATUTS as $key => [$label, $cls]): ?>
            <a class="chip <?= $f === $key ? 'on' : '' ?>" href="<?= h(cmd_url(['f' => $key, 'p' => 1])) ?>"><?= h($label) ?> <small><?= (int)$counts[$key] ?></small></a>
        <?php endforeach; ?>
    </div>

    <div class="card" style="overflow-x:auto">
        <table>
            <thead><tr><th>N° commande</th><th>Client</th><th>Date</th><th>Montant</th><th>Statut</th><th>Changer le statut</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($commandes as $c):
                $id  = (int)$c['id_commande'];
                [$label, $cls] = $STATUTS[$c['statut']] ?? [(string)$c['statut'], 'st-attente'];
                $lignes = $articles[$id] ?? []; ?>
                <tr>
                    <td><b><?= h($c['numero_commande'] ?: '#' . $id) ?></b></td>
                    <td><?= h($c['prenom'] . ' ' . $c['nom']) ?><br><small style="color:var(--gray)"><?= h($c['email']) ?></small></td>
                    <td><?= h(date('d/m/Y H:i', strtotime($c['date_commande']))) ?></td>
                    <td><b><?= h(formatPrice($c['montant'])) ?></b></td>
                    <td><span class="st <?= h($cls) ?>"><?= h($label) ?></span></td>
                    <td>
                        <form method="post" class="status-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_commande" value="<?= $id ?>">
                            <input type="hidden" name="q" value="<?= h($q) ?>">
                            <input type="hidden" name="f" value="<?= h($f) ?>">
                            <input type="hidden" name="p" value="<?= (int)$page ?>">
                            <select name="statut" aria-label="Nouveau statut">
                                <?php foreach ($STATUTS as $key => [$lab, $cl]): ?>
                                    <option value="<?= h($key) ?>" <?= $c['statut'] === $key ? 'selected' : '' ?>><?= h($lab) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-gold btn-sm" type="submit" title="Valider et prévenir le client si nécessaire"><i class="fas fa-check"></i> Valider</button>
                        </form>
                    </td>
                    <td><button type="button" class="row-toggle" data-target="detail-<?= $id ?>" aria-expanded="false" aria-label="Voir le détail"><i class="fas fa-chevron-down"></i></button></td>
                </tr>
                <tr class="detail-row" id="detail-<?= $id ?>" hidden>
                    <td colspan="7">
                        <div class="detail-box">
                            <div class="detail-items">
                                <?php if (!$lignes): ?>
                                    <span style="color:var(--gray)">Aucun article enregistré pour cette commande.</span>
                                <?php else: foreach ($lignes as $l): ?>
                                    <div class="detail-item">
                                        <img src="<?= h(product_image_url($l['image_url'])) ?>" alt="" loading="lazy">
                                        <span><?= h($l['nom']) ?></span>
                                        <span class="qty">× <?= (int)$l['quantite'] ?></span>
                                        <b><?= h(formatPrice((float)$l['prix_unitaire'] * (int)$l['quantite'])) ?></b>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                            <div class="detail-meta">
                                <div><span>Livraison :</span> <?= h($c['mode_livraison'] ?: '-') ?> (<?= h(formatPrice($c['frais_livraison'] ?? 0)) ?>)</div>
                                <div><span>Paiement :</span> <?= h($c['mode_paiement'] ?: '-') ?></div>
                                <div><span>Payée le :</span> <?= !empty($c['date_paiement']) ? h(date('d/m/Y', strtotime($c['date_paiement']))) : '-' ?></div>
                                <?php if (!empty($c['notes'])): ?><div><span>Note :</span> <?= h($c['notes']) ?></div><?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$commandes): ?><tr><td colspan="7" style="color:var(--gray)">Aucune commande ne correspond.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="pager">
            <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= h(cmd_url(['p' => $page - 1])) ?>">← Précédent</a><?php endif; ?>
            <span>Page <?= $page ?> sur <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= h(cmd_url(['p' => $page + 1])) ?>">Suivant →</a><?php endif; ?>
        </div>
    <?php endif; ?>

    <script>
    document.querySelectorAll('.row-toggle').forEach(function (b) {
        b.addEventListener('click', function () {
            var row = document.getElementById(b.dataset.target);
            var open = row.hasAttribute('hidden');
            if (open) { row.removeAttribute('hidden'); } else { row.setAttribute('hidden', ''); }
            b.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
    </script>

<?php admin_footer();