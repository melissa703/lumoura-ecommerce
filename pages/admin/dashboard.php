<?php
// ============================================================
// FICHIER : pages/admin/dashboard.php
// RÔLE    : Tableau de bord administrateur (chiffres réels de la base)
// ============================================================

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/security.php';
require_once '_layout.php';

send_security_headers();
$admin = require_admin($pdo, '../connexion.php');

/** Petit helper : une valeur unique (ou 0 si la requête échoue). */
function stat_value(PDO $pdo, string $sql, array $params = []) {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false || $v === null ? 0 : $v;
    } catch (PDOException $e) {
        error_log('[Lumoura dashboard] ' . $e->getMessage());
        return 0;
    }
}
/** Petit helper : plusieurs lignes (ou tableau vide si la requête échoue). */
function stat_rows(PDO $pdo, string $sql, array $params = []): array {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (PDOException $e) {
        error_log('[Lumoura dashboard] ' . $e->getMessage());
        return [];
    }
}

// Chiffre d'affaires = commandes réellement encaissées (hors "en attente" et "annulée")
$PAID = "statut IN ('payee','expediee','livree')";

$ca_total      = (float)stat_value($pdo, "SELECT COALESCE(SUM(montant),0) FROM commandes WHERE $PAID");
$ca_30j        = (float)stat_value($pdo, "SELECT COALESCE(SUM(montant),0) FROM commandes WHERE $PAID AND date_commande >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$ca_30j_prec   = (float)stat_value($pdo, "SELECT COALESCE(SUM(montant),0) FROM commandes WHERE $PAID AND date_commande >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND date_commande < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$nb_commandes  = (int)stat_value($pdo, "SELECT COUNT(*) FROM commandes");
$nb_attente    = (int)stat_value($pdo, "SELECT COUNT(*) FROM commandes WHERE statut = 'en attente'");
$nb_clients    = (int)stat_value($pdo, "SELECT COUNT(*) FROM utilisateurs WHERE role = 'client'");
$nb_produits   = (int)stat_value($pdo, "SELECT COUNT(*) FROM produits");
$panier_moyen  = (float)stat_value($pdo, "SELECT COALESCE(AVG(montant),0) FROM commandes WHERE $PAID");

$variation = $ca_30j_prec > 0 ? (($ca_30j - $ca_30j_prec) / $ca_30j_prec) * 100 : null;

$recentes = stat_rows($pdo, "
    SELECT c.id_commande, c.numero_commande, c.date_commande, c.statut, c.montant, u.prenom, u.nom
    FROM commandes c JOIN utilisateurs u ON u.id_utilisateur = c.id_utilisateur
    ORDER BY c.date_commande DESC LIMIT 8");

$top = stat_rows($pdo, "
    SELECT p.id_produit, p.nom, SUM(d.quantite) AS vendus, SUM(d.quantite * d.prix_unitaire) AS ca
    FROM details_commande d
    JOIN commandes c ON c.id_commande = d.id_commande AND c.statut <> 'annulee'
    JOIN produits p  ON p.id_produit  = d.id_produit
    GROUP BY p.id_produit, p.nom
    ORDER BY vendus DESC LIMIT 5");

$stock_bas = stat_rows($pdo, "
    SELECT id_produit, nom, stock, seuil_alerte FROM produits
    WHERE stock <= COALESCE(seuil_alerte, 10)
    ORDER BY stock ASC LIMIT 8");

// CA des 6 derniers mois (les mois sans vente apparaissent à 0)
$mois_bdd = [];
foreach (stat_rows($pdo, "
    SELECT DATE_FORMAT(date_commande, '%Y-%m') AS m, SUM(montant) AS ca
    FROM commandes
    WHERE $PAID AND date_commande >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 5 MONTH)
    GROUP BY m") as $r) {
    $mois_bdd[$r['m']] = (float)$r['ca'];
}
$noms_mois = ['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Juin',
              '07'=>'Juil','08'=>'Août','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc'];
$serie = [];
for ($i = 5; $i >= 0; $i--) {
    $ts  = strtotime("first day of -$i month");
    $key = date('Y-m', $ts);
    $serie[] = ['label' => $noms_mois[date('m', $ts)], 'ca' => $mois_bdd[$key] ?? 0.0];
}
$max_ca = max(array_column($serie, 'ca')) ?: 1;

$statuts = [
    'en attente' => ['En attente', '#A0684A'],
    'payee'      => ['Payée',      '#C9922A'],
    'expediee'   => ['Expédiée',   '#6B3A2A'],
    'livree'     => ['Livrée',     '#27ae60'],
    'annulee'    => ['Annulée',    '#e74c3c'],
];
?>
<?php admin_header('Tableau de bord', $admin, 'dashboard'); ?>
<style>
    .hello{font-family:'Cormorant Garamond',serif;font-size:2rem;margin-bottom:4px}
    .sub{color:var(--gray);font-size:.9rem;margin-bottom:28px}
    .grid{display:grid;gap:18px}
    .kpis{grid-template-columns:repeat(auto-fit,minmax(210px,1fr));margin-bottom:26px}
    .card{background:var(--white);border-radius:14px;padding:22px;box-shadow:var(--shadow);border:1px solid rgba(201,146,42,.14);transition:transform .25s,box-shadow .25s}
    .kpi:hover{transform:translateY(-3px);box-shadow:0 14px 36px rgba(44,24,16,.15)}
    .kpi .label{font-size:.72rem;text-transform:uppercase;letter-spacing:.12em;color:var(--gray)}
    .kpi .value{font-family:'Cormorant Garamond',serif;font-size:2.3rem;font-weight:600;margin:6px 0 2px}
    .kpi .note{font-size:.8rem;color:var(--gray)}
    .up{color:#27ae60}.down{color:#e74c3c}
    .two{grid-template-columns:2fr 1fr;margin-bottom:18px}
    @media(max-width:900px){.two{grid-template-columns:1fr}}
    h2{font-family:'Cormorant Garamond',serif;font-size:1.35rem;margin-bottom:14px;color:var(--warm-brown)}
    .bars{display:flex;align-items:flex-end;gap:14px;height:200px;padding-top:10px}
    .bar{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%}
    .bar span.fill{width:100%;max-width:56px;border-radius:8px 8px 0 0;background:linear-gradient(180deg,var(--gold-light),var(--gold));min-height:3px;transition:filter .2s}
    .bar:hover span.fill{filter:brightness(1.08)}
    .bar small{margin-top:8px;font-size:.75rem;color:var(--gray)}
    .bar em{font-style:normal;font-size:.7rem;color:var(--warm-brown);margin-bottom:4px}
    .rank{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-top:1px solid var(--dark-cream);font-size:.9rem}
    .rank:first-of-type{border-top:0}
    .rank b{color:var(--gold)}
    .alert{color:#e74c3c;font-weight:600}
    .empty{color:var(--gray);font-size:.9rem;padding:10px 0}
    a.link{color:var(--gold);text-decoration:none;font-weight:500}
    a.link:hover{text-decoration:underline}
</style>
    <p class="hello">Bonjour <?= h($admin['prenom']) ?></p>
    <p class="sub">Voici l'état réel de votre boutique, calculé à partir de la base de données.</p>

    <section class="grid kpis">
        <div class="card kpi">
            <div class="label">Chiffre d'affaires</div>
            <div class="value"><?= h(formatPrice($ca_total)) ?></div>
            <div class="note">Commandes payées, expédiées et livrées</div>
        </div>
        <div class="card kpi">
            <div class="label">30 derniers jours</div>
            <div class="value"><?= h(formatPrice($ca_30j)) ?></div>
            <div class="note">
                <?php if ($variation === null): ?>
                    Pas de période précédente à comparer
                <?php else: ?>
                    <span class="<?= $variation >= 0 ? 'up' : 'down' ?>">
                        <?= $variation >= 0 ? '▲' : '▼' ?> <?= h(number_format(abs($variation), 1, ',', ' ')) ?> %
                    </span> vs les 30 jours précédents
                <?php endif; ?>
            </div>
        </div>
        <div class="card kpi">
            <div class="label">Commandes</div>
            <div class="value"><?= $nb_commandes ?></div>
            <div class="note"><?= $nb_attente ?> en attente de traitement</div>
        </div>
        <div class="card kpi">
            <div class="label">Clients</div>
            <div class="value"><?= $nb_clients ?></div>
            <div class="note">Comptes clients inscrits</div>
        </div>
        <div class="card kpi">
            <div class="label">Produits</div>
            <div class="value"><?= $nb_produits ?></div>
            <div class="note">Panier moyen : <?= h(formatPrice($panier_moyen)) ?></div>
        </div>
    </section>

    <section class="grid two">
        <div class="card">
            <h2>Chiffre d'affaires, 6 derniers mois</h2>
            <div class="bars" role="img" aria-label="Histogramme du chiffre d'affaires mensuel">
                <?php foreach ($serie as $s): $pct = round(($s['ca'] / $max_ca) * 100); ?>
                    <div class="bar">
                        <em><?= $s['ca'] > 0 ? h(number_format($s['ca'], 0, ',', ' ')) . ' €' : '' ?></em>
                        <span class="fill" style="height:<?= (int)$pct ?>%"></span>
                        <small><?= h($s['label']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h2>Les plus vendus</h2>
            <?php if (!$top): ?>
                <p class="empty">Aucune vente pour le moment.</p>
            <?php else: foreach ($top as $t): ?>
                <div class="rank">
                    <span><?= h($t['nom']) ?></span>
                    <b><?= (int)$t['vendus'] ?> vendus</b>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="grid two">
        <div class="card">
            <h2>Commandes récentes</h2>
            <?php if (!$recentes): ?>
                <p class="empty">Aucune commande.</p>
            <?php else: ?>
            <div style="overflow-x:auto">
            <table>
                <thead><tr><th>N°</th><th>Client</th><th>Date</th><th>Montant</th><th>Statut</th></tr></thead>
                <tbody>
                <?php foreach ($recentes as $c):
                    $st = $statuts[$c['statut']] ?? [$c['statut'], '#A08070']; ?>
                    <tr>
                        <td><a class="link" href="commandes.php"><?= h($c['numero_commande']) ?></a></td>
                        <td><?= h($c['prenom'] . ' ' . $c['nom']) ?></td>
                        <td><?= h(date('d/m/Y', strtotime($c['date_commande']))) ?></td>
                        <td><?= h(formatPrice($c['montant'])) ?></td>
                        <td><span class="badge" style="background:<?= h($st[1]) ?>"><?= h($st[0]) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Stock faible ou en rupture</h2>
            <?php if (!$stock_bas): ?>
                <p class="empty">Tous les stocks sont suffisants.</p>
            <?php else: foreach ($stock_bas as $p): ?>
                <div class="rank">
                    <span><?= h($p['nom']) ?></span>
                    <span class="<?= (int)$p['stock'] <= 0 ? 'alert' : '' ?>">
                        <?= (int)$p['stock'] <= 0 ? 'Rupture' : (int)$p['stock'] . ' en stock' ?>
                    </span>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </section>
<?php admin_footer(); ?>