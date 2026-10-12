<?php
// ============================================================
// FICHIER : pages/facture.php
// RÔLE    : Génère et affiche la facture d'une commande
//           en HTML imprimable / téléchargeable en PDF
//           via le bouton "Télécharger PDF" (window.print)
// ACCÈS   : Utilisateur connecté, uniquement SES commandes
// URL     : facture.php?order_id=123
// ============================================================

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . 'pages/connexion.php');
    exit();
}

$user_id  = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    header('Location: ' . SITE_URL . 'pages/compte.php');
    exit();
}

// Récupérer la commande — en vérifiant qu'elle appartient bien à cet utilisateur
$stmt = $pdo->prepare("
    SELECT c.*, u.prenom, u.nom, u.email
    FROM commandes c
    JOIN utilisateurs u ON u.id_utilisateur = c.id_utilisateur
    WHERE c.id_commande = ? AND c.id_utilisateur = ?
");
$stmt->execute([$order_id, $user_id]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$commande) {
    header('Location: ' . SITE_URL . 'pages/compte.php');
    exit();
}

// Récupérer les articles de la commande
$items_stmt = $pdo->prepare("
    SELECT dc.quantite, dc.prix_unitaire,
           p.nom, p.marque, p.description_courte
    FROM details_commande dc
    JOIN produits p ON p.id_produit = dc.id_produit
    WHERE dc.id_commande = ?
");
$items_stmt->execute([$order_id]);
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculs
$sous_total      = array_sum(array_map(fn($i) => $i['prix_unitaire'] * $i['quantite'], $items));
$frais_livraison = (float)($commande['frais_livraison'] ?? 0);
$total           = (float)$commande['montant'];
$tva             = $total * 0.2 / 1.2;

// Numéro de facture basé sur le numéro de commande
$numero_facture = 'FAC-' . str_replace('CMD-', '', $commande['numero_commande']);
$date_commande  = date('d/m/Y', strtotime($commande['date_commande']));

$mode_paiement_label = match($commande['mode_paiement']) {
    'carte'     => 'Carte bancaire',
    'paypal'    => 'PayPal',
    'apple_pay' => 'Apple Pay',
    default     => htmlspecialchars($commande['mode_paiement'])
};

$statut_label = match($commande['statut']) {
    'en_attente' => 'En attente',
    'confirmee'  => 'Confirmée',
    'en_cours'   => 'En cours',
    'expediee'   => 'Expédiée',
    'livree'     => 'Livrée',
    'annulee'    => 'Annulée',
    default      => ucfirst($commande['statut'])
};

// ============================================================
// GÉNÉRATION PDF — déclenché par ?download=1
// Nécessite : composer require mpdf/mpdf
// ============================================================
if (isset($_GET['download']) && $_GET['download'] === '1') {

    // Vérifier que mPDF est installé via Composer
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        die('Erreur : mPDF non installé. Lancez "composer require mpdf/mpdf" à la racine du projet.');
    }
    require_once $autoload;

    // Construire le HTML de la facture (version épurée pour le PDF)
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 11pt; color: #2c1810; }

        /* EN-TÊTE */
        .header-table { width: 100%; background: #2c1810; padding: 28px 36px; }
        .logo-text  { font-size: 22pt; font-weight: bold; color: #d4a017; letter-spacing: 3px; }
        .logo-sub   { font-size: 7pt; color: #888; letter-spacing: 2px; text-transform: uppercase; margin-top: 4px; }
        .header-addr { font-size: 8pt; color: #aaa; line-height: 1.8; margin-top: 14px; }
        .facture-title  { font-size: 18pt; font-weight: bold; color: #fff; letter-spacing: 2px; text-align: right; }
        .facture-numero { font-size: 10pt; color: #d4a017; font-weight: bold; text-align: right; margin-top: 6px; }
        .facture-date   { font-size: 8pt; color: #888; text-align: right; margin-top: 3px; }

        /* BANDE DORÉE */
        .gold-stripe { height: 4px; background: #d4a017; width: 100%; }

        /* CORPS */
        .body { padding: 28px 36px; }

        /* ADRESSES */
        .addr-section { width: 100%; margin-bottom: 24px; }
        .addr-label { font-size: 7pt; letter-spacing: 2px; text-transform: uppercase; color: #d4a017;
                      font-weight: bold; border-bottom: 1px solid #e8e0d5; padding-bottom: 5px; margin-bottom: 8px; }
        .addr-name  { font-weight: bold; font-size: 10pt; }
        .addr-line  { font-size: 9pt; color: #555; line-height: 1.7; }

        /* INFOS COMMANDE */
        .info-box   { background: #faf8f5; border: 1px solid #e8e0d5; padding: 14px 20px;
                      margin-bottom: 24px; width: 100%; }
        .info-label { font-size: 7pt; text-transform: uppercase; letter-spacing: 1px; color: #aaa; }
        .info-value { font-size: 10pt; font-weight: bold; color: #2c1810; }

        /* TABLEAU */
        .section-title { font-size: 7pt; letter-spacing: 2px; text-transform: uppercase;
                         color: #d4a017; font-weight: bold; margin-bottom: 10px; }
        table.articles { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9.5pt; }
        table.articles thead tr { background: #2c1810; color: #fff; }
        table.articles thead th { padding: 10px 12px; text-align: left; font-size: 7.5pt;
                                   letter-spacing: 1px; text-transform: uppercase; }
        table.articles thead th:last-child { text-align: right; }
        table.articles tbody td { padding: 12px 12px; border-bottom: 1px solid #f0ebe3; vertical-align: top; }
        table.articles tbody td:last-child { text-align: right; font-weight: bold; }
        .prod-marque { font-size: 7.5pt; color: #d4a017; text-transform: uppercase; letter-spacing: 1px; }
        .prod-nom    { font-weight: bold; font-size: 10pt; color: #2c1810; }
        .prod-desc   { font-size: 8pt; color: #aaa; }

        /* TOTAUX */
        .totaux-table { width: 260px; margin-left: auto; border-collapse: collapse; font-size: 9.5pt; }
        .totaux-table td { padding: 7px 4px; border-bottom: 1px solid #f0ebe3; color: #555; }
        .totaux-table td:last-child { text-align: right; font-weight: bold; color: #2c1810; }
        .totaux-table tr:last-child td { border-bottom: none; font-size: 12pt; font-weight: bold; color: #2c1810; }
        .totaux-table tr:last-child td:last-child { color: #d4a017; }
        .gratuit { color: #2a9d8f; font-weight: bold; }

        /* PIED */
        .footer-sep { border-top: 1.5px solid #e8e0d5; margin: 28px 0 16px; }
        .footer-merci { font-size: 11pt; font-style: italic; font-weight: bold; color: #d4a017;
                        text-align: center; margin-bottom: 8px; }
        .footer-text  { font-size: 8.5pt; color: #888; text-align: center; line-height: 1.8; }
        .sceau        { text-align: center; margin-top: 16px; font-size: 8pt; color: #ccc; }
    </style>
    </head>
    <body>

    <!-- EN-TÊTE -->
    <table class="header-table" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="55%" style="vertical-align:top; color:#fff;">
                <div class="logo-text">LUMOURA</div>
                <div class="logo-sub">Parfums d'Exception</div>
                <div class="header-addr">
                    123 Avenue des Champs-Élysées<br>
                    75008 Paris, France<br>
                    contact@lumoura.fr<br>
                    SIRET : 123 456 789 00012
                </div>
            </td>
            <td width="45%" style="vertical-align:top;">
                <div class="facture-title">FACTURE</div>
                <div class="facture-numero"><?= htmlspecialchars($numero_facture) ?></div>
                <div class="facture-date">Émise le <?= $date_commande ?></div>
            </td>
        </tr>
    </table>
    <div class="gold-stripe"></div>

    <!-- CORPS -->
    <div class="body">

        <!-- ADRESSES -->
        <table class="addr-section" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="50%" style="vertical-align:top; padding-right: 20px;">
                    <div class="addr-label">Facturé à</div>
                    <div class="addr-name"><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></div>
                    <div class="addr-line">
                        <?= nl2br(htmlspecialchars($commande['adresse_livraison'])) ?><br>
                        <?= htmlspecialchars($commande['email']) ?>
                    </div>
                </td>
                <td width="50%" style="vertical-align:top; padding-left: 20px;">
                    <div class="addr-label">Livré à</div>
                    <div class="addr-line"><?= nl2br(htmlspecialchars($commande['adresse_livraison'])) ?></div>
                </td>
            </tr>
        </table>

        <!-- INFOS COMMANDE -->
        <table class="info-box" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="33%" style="text-align:center;">
                    <div class="info-label">N° Commande</div>
                    <div class="info-value"><?= htmlspecialchars($commande['numero_commande']) ?></div>
                </td>
                <td width="33%" style="text-align:center;">
                    <div class="info-label">Date</div>
                    <div class="info-value"><?= $date_commande ?></div>
                </td>
                <td width="33%" style="text-align:center;">
                    <div class="info-label">Statut</div>
                    <div class="info-value"><?= $statut_label ?></div>
                </td>
            </tr>
        </table>

        <!-- TABLEAU ARTICLES -->
        <div class="section-title">Détail de la commande</div>
        <table class="articles" width="100%">
            <thead>
                <tr>
                    <th width="45%">Produit</th>
                    <th width="20%" style="text-align:center;">Prix unitaire</th>
                    <th width="15%" style="text-align:center;">Qté</th>
                    <th width="20%" style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item):
                    $line_total = $item['prix_unitaire'] * $item['quantite'];
                ?>
                <tr>
                    <td>
                        <div class="prod-marque"><?= htmlspecialchars($item['marque']) ?></div>
                        <div class="prod-nom"><?= htmlspecialchars($item['nom']) ?></div>
                        <?php if (!empty($item['description_courte'])): ?>
                        <div class="prod-desc"><?= htmlspecialchars(mb_substr($item['description_courte'], 0, 60)) ?>...</div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center; color:#666;"><?= number_format($item['prix_unitaire'], 2, ',', ' ') ?> €</td>
                    <td style="text-align:center; color:#666;"><?= $item['quantite'] ?></td>
                    <td><?= number_format($line_total, 2, ',', ' ') ?> €</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- TOTAUX -->
        <table class="totaux-table">
            <tr>
                <td>Sous-total</td>
                <td><?= number_format($sous_total, 2, ',', ' ') ?> €</td>
            </tr>
            <tr>
                <td>Frais de livraison</td>
                <td><?= $frais_livraison == 0 ? '<span class="gratuit">GRATUIT</span>' : number_format($frais_livraison, 2, ',', ' ') . ' €' ?></td>
            </tr>
            <tr>
                <td>TVA (20%)</td>
                <td><?= number_format($tva, 2, ',', ' ') ?> €</td>
            </tr>
            <tr>
                <td><strong>Total TTC</strong></td>
                <td><?= number_format($total, 2, ',', ' ') ?> €</td>
            </tr>
        </table>

        <!-- PIED -->
        <div class="footer-sep"></div>
        <div class="footer-merci">Merci pour votre confiance ✨</div>
        <div class="footer-text">
            Mode de paiement : <strong><?= $mode_paiement_label ?></strong><br>
            Cette facture fait foi de paiement pour votre commande Lumoura.<br>
            Pour toute question : <strong>contact@lumoura.fr</strong> — 01 23 45 67 89
        </div>
        <div class="sceau">✓ Document généré automatiquement — Lumoura © <?= date('Y') ?></div>

    </div>
    </body>
    </html>
    <?php
    $html_pdf = ob_get_clean();

    // Générer le PDF avec mPDF
    $mpdf = new \Mpdf\Mpdf([
        'margin_top'    => 0,
        'margin_bottom' => 10,
        'margin_left'   => 0,
        'margin_right'  => 0,
        'format'        => 'A4',
    ]);

    $mpdf->SetTitle('Facture ' . $numero_facture . ' — Lumoura');
    $mpdf->SetAuthor('Lumoura');
    $mpdf->WriteHTML($html_pdf);

    $filename = 'Facture_' . $numero_facture . '.pdf';
    $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
    exit();
}
// ============================================================
// FIN BLOC PDF
// ============================================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture <?= htmlspecialchars($numero_facture) ?> — Lumoura</title>
    <style>
        /* ===== STYLES GÉNÉRAUX ===== */
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f5f0e8;
            color: #2c1810;
            min-height: 100vh;
            padding: 30px 20px;
        }

        /* ===== BOUTONS (cachés à l'impression) ===== */
        .actions-bar {
            max-width: 800px;
            margin: 0 auto 24px;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .btn-download {
            background: linear-gradient(135deg, #d4a017, #b8860b);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 10px;
            font-size: 1em;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }
        .btn-download:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(212,160,23,0.4); }
        .btn-back {
            background: white;
            color: #2c1810;
            border: 2px solid #e0d5c5;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 0.95em;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-back:hover { border-color: #d4a017; color: #d4a017; }

        /* ===== FACTURE ===== */
        .facture {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        /* En-tête */
        .facture-header {
            background: linear-gradient(135deg, #2c1810 0%, #1a1009 100%);
            color: white;
            padding: 40px 50px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .logo-zone .logo-text {
            font-size: 2.2em;
            font-weight: 900;
            letter-spacing: 3px;
            color: #d4a017;
        }
        .logo-zone .logo-sub {
            font-size: 0.8em;
            color: rgba(255,255,255,0.5);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .facture-info {
            text-align: right;
        }
        .facture-info .facture-title {
            font-size: 1.8em;
            font-weight: 700;
            color: white;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .facture-info .facture-numero {
            font-size: 1em;
            color: #d4a017;
            font-weight: 600;
            margin-top: 6px;
        }
        .facture-info .facture-date {
            font-size: 0.85em;
            color: rgba(255,255,255,0.5);
            margin-top: 4px;
        }

        /* Bandeau doré */
        .gold-stripe {
            height: 4px;
            background: linear-gradient(90deg, #d4a017, #e8c56a, #d4a017);
        }

        /* Corps */
        .facture-body { padding: 40px 50px; }

        /* Adresses */
        .adresses-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 35px;
        }
        .adresse-block h3 {
            font-size: 0.75em;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #d4a017;
            font-weight: 700;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #f0ebe3;
        }
        .adresse-block p {
            font-size: 0.92em;
            color: #555;
            line-height: 1.7;
        }
        .adresse-block strong { color: #2c1810; }

        /* Infos commande */
        .commande-infos {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 35px;
            background: #faf8f5;
            border-radius: 12px;
            padding: 20px 25px;
        }
        .info-item { text-align: center; }
        .info-item .info-label {
            font-size: 0.72em;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #aaa;
            margin-bottom: 5px;
        }
        .info-item .info-value {
            font-size: 0.95em;
            font-weight: 700;
            color: #2c1810;
        }
        .info-item .info-value.statut {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.85em;
        }
        .statut-en_attente  { background: #fff3cd; color: #856404; }
        .statut-confirmee   { background: #d1ecf1; color: #0c5460; }
        .statut-en_cours    { background: #cce5ff; color: #004085; }
        .statut-expediee    { background: #d4edda; color: #155724; }
        .statut-livree      { background: #d4edda; color: #155724; }
        .statut-annulee     { background: #f8d7da; color: #721c24; }

        /* Tableau articles */
        .table-title {
            font-size: 0.75em;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #d4a017;
            font-weight: 700;
            margin-bottom: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 0.92em;
        }
        thead tr {
            background: #2c1810;
            color: white;
        }
        thead th {
            padding: 12px 16px;
            text-align: left;
            font-size: 0.8em;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 600;
        }
        thead th:last-child { text-align: right; }
        tbody tr { border-bottom: 1px solid #f0ebe3; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #faf8f5; }
        tbody td { padding: 14px 16px; color: #444; vertical-align: middle; }
        tbody td:last-child { text-align: right; font-weight: 700; color: #2c1810; }
        .produit-nom { font-weight: 700; color: #2c1810; margin-bottom: 3px; }
        .produit-marque { font-size: 0.82em; color: #d4a017; text-transform: uppercase; letter-spacing: 1px; }
        .produit-desc { font-size: 0.82em; color: #aaa; }

        /* Totaux */
        .totaux {
            display: flex;
            justify-content: flex-end;
        }
        .totaux-box {
            width: 280px;
        }
        .totaux-line {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 0.92em;
            color: #666;
            border-bottom: 1px solid #f0ebe3;
        }
        .totaux-line:last-child { border-bottom: none; }
        .totaux-line.total-final {
            font-size: 1.15em;
            font-weight: 800;
            color: #2c1810;
            padding-top: 14px;
            margin-top: 4px;
        }
        .totaux-line .montant { font-weight: 700; color: #2c1810; }
        .totaux-line.total-final .montant { color: #d4a017; font-size: 1.1em; }
        .gratuit { color: #2a9d8f !important; font-weight: 700; }

        /* Pied de facture */
        .facture-footer {
            margin-top: 40px;
            padding-top: 25px;
            border-top: 2px solid #f0ebe3;
            text-align: center;
            font-size: 0.82em;
            color: #aaa;
            line-height: 1.8;
        }
        .facture-footer .merci {
            font-size: 1.1em;
            font-weight: 700;
            color: #d4a017;
            margin-bottom: 8px;
            font-style: italic;
        }

        /* Sceau */
        .sceau {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            color: #ccc;
            font-size: 0.8em;
        }
        .sceau-icon {
            width: 18px;
            height: 18px;
            background: #2a9d8f;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.7em;
        }

        /* ===== STYLES D'IMPRESSION ===== */
        @media print {
            body { background: white; padding: 0; }
            .actions-bar { display: none !important; }
            .facture {
                border-radius: 0;
                box-shadow: none;
                max-width: 100%;
            }
            .facture-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            thead tr { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <!-- BOUTONS D'ACTION -->
    <div class="actions-bar">
        <a href="<?php echo SITE_URL; ?>pages/compte.php" class="btn-back">
            ← Retour à mon compte
        </a>
        <a href="facture.php?order_id=<?= $order_id ?>&download=1" class="btn-download">
            ⬇ Télécharger la facture PDF
        </a>
    </div>

    <!-- FACTURE -->
    <div class="facture">

        <!-- EN-TÊTE -->
        <div class="facture-header">
            <div class="logo-zone">
                <div class="logo-text">LUMOURA</div>
                <div class="logo-sub">Parfums d'Exception</div>
                <div style="margin-top: 20px; font-size: 0.82em; color: rgba(255,255,255,0.5); line-height: 1.7;">
                    123 Avenue des Champs-Élysées<br>
                    75008 Paris, France<br>
                    contact@lumoura.fr<br>
                    SIRET : 123 456 789 00012
                </div>
            </div>
            <div class="facture-info">
                <div class="facture-title">Facture</div>
                <div class="facture-numero"><?= htmlspecialchars($numero_facture) ?></div>
                <div class="facture-date">Émise le <?= $date_commande ?></div>
            </div>
        </div>

        <div class="gold-stripe"></div>

        <!-- CORPS -->
        <div class="facture-body">

            <!-- ADRESSES -->
            <div class="adresses-grid">
                <div class="adresse-block">
                    <h3>Facturé à</h3>
                    <p>
                        <strong><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></strong><br>
                        <?= htmlspecialchars($commande['adresse_livraison']) ?><br>
                        <?= htmlspecialchars($commande['email']) ?>
                    </p>
                </div>
                <div class="adresse-block">
                    <h3>Livré à</h3>
                    <p><?= nl2br(htmlspecialchars($commande['adresse_livraison'])) ?></p>
                </div>
            </div>

            <!-- INFOS COMMANDE -->
            <div class="commande-infos">
                <div class="info-item">
                    <div class="info-label">N° Commande</div>
                    <div class="info-value"><?= htmlspecialchars($commande['numero_commande']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date</div>
                    <div class="info-value"><?= $date_commande ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Statut</div>
                    <div class="info-value">
                        <span class="info-value statut statut-<?= htmlspecialchars($commande['statut']) ?>">
                            <?= $statut_label ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- TABLEAU ARTICLES -->
            <div class="table-title">Détail de la commande</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 45%;">Produit</th>
                        <th style="width: 20%; text-align:center;">Prix unitaire</th>
                        <th style="width: 15%; text-align:center;">Qté</th>
                        <th style="width: 20%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item):
                        $line_total = $item['prix_unitaire'] * $item['quantite'];
                    ?>
                    <tr>
                        <td>
                            <div class="produit-marque"><?= htmlspecialchars($item['marque']) ?></div>
                            <div class="produit-nom"><?= htmlspecialchars($item['nom']) ?></div>
                            <?php if (!empty($item['description_courte'])): ?>
                            <div class="produit-desc"><?= htmlspecialchars(mb_substr($item['description_courte'], 0, 60)) ?>...</div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center; color:#666;">
                            <?= number_format($item['prix_unitaire'], 2, ',', ' ') ?> €
                        </td>
                        <td style="text-align:center; color:#666;">
                            <?= $item['quantite'] ?>
                        </td>
                        <td>
                            <?= number_format($line_total, 2, ',', ' ') ?> €
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- TOTAUX -->
            <div class="totaux">
                <div class="totaux-box">
                    <div class="totaux-line">
                        <span>Sous-total</span>
                        <span class="montant"><?= number_format($sous_total, 2, ',', ' ') ?> €</span>
                    </div>
                    <div class="totaux-line">
                        <span>Frais de livraison</span>
                        <span class="montant <?= $frais_livraison == 0 ? 'gratuit' : '' ?>">
                            <?= $frais_livraison == 0 ? 'GRATUIT' : number_format($frais_livraison, 2, ',', ' ') . ' €' ?>
                        </span>
                    </div>
                    <div class="totaux-line">
                        <span>TVA (20%)</span>
                        <span class="montant"><?= number_format($total * 0.2 / 1.2, 2, ',', ' ') ?> €</span>
                    </div>
                    <div class="totaux-line total-final">
                        <span>Total TTC</span>
                        <span class="montant"><?= number_format($total, 2, ',', ' ') ?> €</span>
                    </div>
                </div>
            </div>

            <!-- PIED DE FACTURE -->
            <div class="facture-footer">
                <div class="merci">Merci pour votre confiance ✨</div>
                Mode de paiement : <strong><?= $mode_paiement_label ?></strong>
                <br>
                Cette facture fait foi de paiement pour votre commande Lumoura.<br>
                Pour toute question : <strong>contact@lumoura.fr</strong> — 01 23 45 67 89
                <div class="sceau">
                    <span class="sceau-icon">✓</span>
                    Document généré automatiquement — Lumoura © <?= date('Y') ?>
                </div>
            </div>

        </div>
    </div>

</body>
</html>