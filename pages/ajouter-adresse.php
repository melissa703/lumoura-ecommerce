<?php
// ============================================================
// FICHIER : ajouter-adresse.php
// RÔLE    : Permet à un utilisateur connecté d'ajouter ou de
//           modifier son adresse de livraison
//           → Après enregistrement, génère un code OTP à 6 chiffres
//             et l'envoie par email pour confirmation
// ============================================================

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/envoyer-email.php'; // ← Nouvelle inclusion pour l'envoi email

// ============================================================
// SECTION 1 : VÉRIFICATION DE CONNEXION
// ============================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "pages/connexion.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$erreurs = [];
$success = false;
$action  = 'ajouter';

// ============================================================
// SECTION 2 : PRÉ-REMPLISSAGE AVEC LES INFOS DE L'UTILISATEUR
// ============================================================
$stmt = $pdo->prepare("SELECT prenom, nom, email FROM utilisateurs WHERE id_utilisateur = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Utilisateur introuvable");
}

$adresse_data = [
    'prenom'               => $user['prenom'] ?? '',
    'nom'                  => $user['nom'] ?? '',
    'adresse'              => '',
    'complement_adresse'   => '',
    'code_postal'          => '',
    'ville'                => '',
    'pays'                 => 'France',
    'telephone'            => '',
    'est_defaut_livraison' => 1
];

// ============================================================
// SECTION 3 : MODE MODIFICATION (?edit=ID dans l'URL)
// ============================================================
$adresse_id = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $adresse_id = (int)$_GET['edit'];
    $action     = 'modifier';

    $stmt = $pdo->prepare("SELECT * FROM adresses WHERE id_adresse = ? AND id_utilisateur = ?");
    $stmt->execute([$adresse_id, $user_id]);
    $adr = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($adr) {
        $adresse_data = $adr;
    } else {
        $erreurs[] = "Adresse introuvable ou non autorisée.";
        $action    = 'ajouter';
    }
}

// ============================================================
// SECTION 4 : TRAITEMENT DU FORMULAIRE (soumission POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $adresse_data = [
        'prenom'             => trim($_POST['prenom']             ?? ''),
        'nom'                => trim($_POST['nom']                ?? ''),
        'adresse'            => trim($_POST['adresse']            ?? ''),
        'complement_adresse' => trim($_POST['complement_adresse'] ?? ''),
        'code_postal'        => trim($_POST['code_postal']        ?? ''),
        'ville'              => trim($_POST['ville']              ?? ''),
        'pays'               => trim($_POST['pays']               ?? 'France'),
        'telephone'          => trim($_POST['telephone']          ?? ''),
    ];

    // Validation des champs obligatoires
    $obligatoires = ['prenom', 'nom', 'adresse', 'code_postal', 'ville'];
    foreach ($obligatoires as $champ) {
        if (empty($adresse_data[$champ])) {
            $erreurs[] = ucfirst(str_replace('_', ' ', $champ)) . " est obligatoire.";
        }
    }

    if (empty($erreurs)) {

        $est_defaut = 1;

        if ($action === 'modifier' && $adresse_id) {
            // ✅ MODE MODIFICATION : UPDATE direct sans DELETE
            $sql = "UPDATE adresses SET 
                        prenom = ?, nom = ?, adresse = ?, complement_adresse = ?,
                        code_postal = ?, ville = ?, pays = ?, telephone = ?,
                        est_defaut_livraison = ?
                    WHERE id_adresse = ? AND id_utilisateur = ?";
            $params = [
                $adresse_data['prenom'],
                $adresse_data['nom'],
                $adresse_data['adresse'],
                $adresse_data['complement_adresse'],
                $adresse_data['code_postal'],
                $adresse_data['ville'],
                $adresse_data['pays'],
                $adresse_data['telephone'],
                $est_defaut,
                $adresse_id,
                $user_id
            ];
        } else {
            // ✅ MODE AJOUT : DELETE + INSERT
            $pdo->prepare("DELETE FROM adresses WHERE id_utilisateur = ?")->execute([$user_id]);

            $sql = "INSERT INTO adresses 
                        (id_utilisateur, prenom, nom, adresse, complement_adresse, 
                         code_postal, ville, pays, telephone, est_defaut_livraison)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [
                $user_id,
                $adresse_data['prenom'],
                $adresse_data['nom'],
                $adresse_data['adresse'],
                $adresse_data['complement_adresse'],
                $adresse_data['code_postal'],
                $adresse_data['ville'],
                $adresse_data['pays'],
                $adresse_data['telephone'],
                $est_defaut
            ];
        }

        try {
            $pdo->prepare($sql)->execute($params);

            // ============================================================
            // ✉️ GÉNÉRATION ET ENVOI DU CODE OTP
            // ============================================================
            $otp_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $_SESSION['otp_code']       = $otp_code;
            $_SESSION['otp_expiration'] = time() + 900;
            $_SESSION['otp_email']      = $user['email'];
            $_SESSION['otp_tentatives'] = 0;

            $email_envoye = envoyerCodeVerification(
                $user['email'],
                $user['prenom'],
                $otp_code
            );

            if ($email_envoye) {
                header("Location: " . SITE_URL . "pages/verifier-code.php");
            } else {
                $_SESSION['message_success'] = $action === 'modifier'
                    ? "Adresse modifiée. (Email de confirmation non envoyé)"
                    : "Adresse ajoutée. (Email de confirmation non envoyé)";
                header("Location: " . SITE_URL . "pages/compte.php");
            }
            exit();

        } catch (Exception $e) {
            $erreurs[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }
}

$pageTitle = $action === 'modifier' ? "Modifier mon adresse" : "Ajouter mon adresse";
include '../includes/header.php';
?>

<!-- ============================================================ -->
<!-- jQuery + jQuery UI (Autocomplete) -->
<!-- ============================================================ -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<!-- Style personnalisé pour la liste de suggestions -->
<style>
    /* Intègre le menu déroulant jQuery UI avec le style Bootstrap */
    .ui-autocomplete {
        max-height: 220px;
        overflow-y: auto;
        overflow-x: hidden;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        background: #fff;
        z-index: 9999;
        font-size: 0.95rem;
    }
    .ui-menu-item-wrapper {
        padding: 10px 15px;
        cursor: pointer;
        transition: background 0.2s;
    }
    .ui-menu-item-wrapper.ui-state-active {
        background: linear-gradient(90deg, #ff8c00, #e07b00) !important;
        color: #fff !important;
        border: none !important;
        border-radius: 0;
    }
    /* Petit icône loupe dans le champ adresse */
    .input-adresse-wrapper {
        position: relative;
    }
    .input-adresse-wrapper .icone-recherche {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #aaa;
        pointer-events: none;
        font-size: 1rem;
    }
</style>

<div class="container py-5" style="padding-top:120px !important;">
    <div class="card shadow-lg p-4 mx-auto" style="max-width: 700px; border-radius: 20px;">

        <h3 class="text-center mb-4 fw-bold">
            <?= $action === 'modifier' ? 'Modifier mon adresse' : 'Ajouter mon adresse de livraison' ?>
        </h3>

        <?php if (isset($_SESSION['message_success'])): ?>
            <div class="alert alert-success text-center">
                <?= htmlspecialchars($_SESSION['message_success']) ?>
            </div>
            <?php unset($_SESSION['message_success']); ?>
        <?php endif; ?>

        <?php if ($erreurs): ?>
            <div class="alert alert-danger">
                <?= implode('<br>', array_map('htmlspecialchars', $erreurs)) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label fw-bold">Prénom *</label>
                    <input type="text" class="form-control py-3" name="prenom"
                           value="<?= htmlspecialchars($adresse_data['prenom']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Nom *</label>
                    <input type="text" class="form-control py-3" name="nom"
                           value="<?= htmlspecialchars($adresse_data['nom']) ?>" required>
                </div>

                <!-- ============================================================ -->
                <!-- CHAMP ADRESSE avec autocomplétion jQuery UI                  -->
                <!-- ============================================================ -->
                <div class="col-12">
                    <label class="form-label fw-bold">Adresse *</label>
                    <div class="input-adresse-wrapper">
                        <input type="text"
                               class="form-control py-3"
                               name="adresse"
                               id="champ-adresse"
                               placeholder="Ex : 12 Rue de la Paix"
                               value="<?= htmlspecialchars($adresse_data['adresse']) ?>"
                               autocomplete="off"
                               required>
                        <span class="icone-recherche">🔍</span>
                    </div>
                    <small class="text-muted">Commencez à taper votre adresse, des suggestions apparaîtront.</small>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold">Complément d'adresse</label>
                    <input type="text" class="form-control py-3" name="complement_adresse"
                           value="<?= htmlspecialchars($adresse_data['complement_adresse']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Code postal *</label>
                    <input type="text" class="form-control py-3" name="code_postal" id="champ-code-postal"
                           value="<?= htmlspecialchars($adresse_data['code_postal']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Ville *</label>
                    <input type="text" class="form-control py-3" name="ville" id="champ-ville"
                           value="<?= htmlspecialchars($adresse_data['ville']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Pays</label>
                    <input type="text" class="form-control py-3" name="pays"
                           value="<?= htmlspecialchars($adresse_data['pays']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Téléphone</label>
                    <input type="tel" class="form-control py-3" name="telephone"
                           value="<?= htmlspecialchars($adresse_data['telephone']) ?>">
                </div>

                <div class="col-12 text-center mt-4">
                    <button type="submit" class="btn btn-primary px-5 py-3 fw-bold rounded-pill"
                            style="background: linear-gradient(90deg, #ff8c00, #e07b00); border: none;">
                        <?= $action === 'modifier' ? 'Modifier mon adresse' : 'Enregistrer mon adresse' ?>
                    </button>
                    <a href="<?= SITE_URL ?>pages/compte.php"
                       class="btn btn-outline-secondary ms-3 px-5 py-3 rounded-pill">Annuler</a>
                </div>

            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- SCRIPT JQUERY UI AUTOCOMPLETE                                -->
<!-- ============================================================ -->
<script>
$(document).ready(function () {

    // ----------------------------------------------------------------
    // BASE DE DONNÉES D'ADRESSES FRANÇAISES
    // Chaque objet contient :
    //   - label  : ce qui s'affiche dans la liste de suggestions
    //   - value  : ce qui remplit le champ "adresse" après sélection
    //   - cp     : code postal → remplit automatiquement le champ code postal
    //   - ville  : ville      → remplit automatiquement le champ ville
    // ----------------------------------------------------------------
    var adressesFrance = [
        // Paris
        { label: "1 Rue de Rivoli, 75001 Paris",           value: "1 Rue de Rivoli",           cp: "75001", ville: "Paris" },
        { label: "12 Rue de la Paix, 75002 Paris",         value: "12 Rue de la Paix",         cp: "75002", ville: "Paris" },
        { label: "5 Avenue des Champs-Élysées, 75008 Paris", value: "5 Avenue des Champs-Élysées", cp: "75008", ville: "Paris" },
        { label: "20 Rue du Faubourg Saint-Antoine, 75012 Paris", value: "20 Rue du Faubourg Saint-Antoine", cp: "75012", ville: "Paris" },
        { label: "3 Rue de la Roquette, 75011 Paris",      value: "3 Rue de la Roquette",      cp: "75011", ville: "Paris" },
        { label: "8 Boulevard Haussmann, 75009 Paris",     value: "8 Boulevard Haussmann",     cp: "75009", ville: "Paris" },
        { label: "15 Rue Montorgueil, 75001 Paris",        value: "15 Rue Montorgueil",        cp: "75001", ville: "Paris" },
        { label: "42 Rue de Lévis, 75017 Paris",           value: "42 Rue de Lévis",           cp: "75017", ville: "Paris" },

        // Lyon
        { label: "10 Place Bellecour, 69002 Lyon",         value: "10 Place Bellecour",        cp: "69002", ville: "Lyon" },
        { label: "5 Rue de la République, 69001 Lyon",     value: "5 Rue de la République",    cp: "69001", ville: "Lyon" },
        { label: "3 Quai Saint-Antoine, 69002 Lyon",       value: "3 Quai Saint-Antoine",      cp: "69002", ville: "Lyon" },
        { label: "18 Rue Mercière, 69002 Lyon",            value: "18 Rue Mercière",           cp: "69002", ville: "Lyon" },

        // Marseille
        { label: "1 La Canebière, 13001 Marseille",        value: "1 La Canebière",            cp: "13001", ville: "Marseille" },
        { label: "7 Rue Saint-Ferréol, 13001 Marseille",   value: "7 Rue Saint-Ferréol",       cp: "13001", ville: "Marseille" },
        { label: "25 Boulevard Longchamp, 13001 Marseille", value: "25 Boulevard Longchamp",   cp: "13001", ville: "Marseille" },

        // Bordeaux
        { label: "2 Place des Quinconces, 33000 Bordeaux", value: "2 Place des Quinconces",    cp: "33000", ville: "Bordeaux" },
        { label: "14 Cours de l'Intendance, 33000 Bordeaux", value: "14 Cours de l'Intendance", cp: "33000", ville: "Bordeaux" },
        { label: "6 Rue Sainte-Catherine, 33000 Bordeaux", value: "6 Rue Sainte-Catherine",    cp: "33000", ville: "Bordeaux" },

        // Toulouse
        { label: "1 Place du Capitole, 31000 Toulouse",    value: "1 Place du Capitole",       cp: "31000", ville: "Toulouse" },
        { label: "10 Rue d'Alsace-Lorraine, 31000 Toulouse", value: "10 Rue d'Alsace-Lorraine", cp: "31000", ville: "Toulouse" },

        // Nice
        { label: "5 Promenade des Anglais, 06000 Nice",    value: "5 Promenade des Anglais",   cp: "06000", ville: "Nice" },
        { label: "12 Avenue Jean Médecin, 06000 Nice",     value: "12 Avenue Jean Médecin",    cp: "06000", ville: "Nice" },

        // Nantes
        { label: "2 Place du Commerce, 44000 Nantes",      value: "2 Place du Commerce",       cp: "44000", ville: "Nantes" },
        { label: "8 Rue de la Paix, 44000 Nantes",         value: "8 Rue de la Paix",          cp: "44000", ville: "Nantes" },

        // Strasbourg
        { label: "1 Place de la Cathédrale, 67000 Strasbourg", value: "1 Place de la Cathédrale", cp: "67000", ville: "Strasbourg" },
        { label: "10 Rue des Grandes Arcades, 67000 Strasbourg", value: "10 Rue des Grandes Arcades", cp: "67000", ville: "Strasbourg" },

        // Montpellier
        { label: "3 Place de la Comédie, 34000 Montpellier", value: "3 Place de la Comédie",  cp: "34000", ville: "Montpellier" },

        // Rennes
        { label: "5 Rue Le Bastard, 35000 Rennes",         value: "5 Rue Le Bastard",          cp: "35000", ville: "Rennes" },

        // Lille
        { label: "1 Grand Place, 59000 Lille",             value: "1 Grand Place",             cp: "59000", ville: "Lille" },
        { label: "20 Rue de Béthune, 59000 Lille",         value: "20 Rue de Béthune",         cp: "59000", ville: "Lille" },
    ];

    // ----------------------------------------------------------------
    // INITIALISATION DU WIDGET JQUERY UI AUTOCOMPLETE
    // ----------------------------------------------------------------
    $("#champ-adresse").autocomplete({

        // 'source' filtre les adresses selon ce que l'utilisateur tape
        source: function (requete, reponse) {
            var terme = requete.term.toLowerCase();

            // On filtre : on garde les adresses qui CONTIENNENT le texte tapé
            var resultats = $.grep(adressesFrance, function (adresse) {
                return adresse.label.toLowerCase().indexOf(terme) !== -1;
            });

            // On limite à 8 suggestions maximum pour ne pas surcharger
            reponse(resultats.slice(0, 8));
        },

        // Nombre minimum de caractères avant d'afficher les suggestions
        minLength: 2,

        // 'select' se déclenche quand l'utilisateur clique sur une suggestion
        select: function (evenement, ui) {
            // Remplit le champ adresse avec la valeur sélectionnée
            $("#champ-adresse").val(ui.item.value);

            // Remplit automatiquement le code postal et la ville 🎯
            $("#champ-code-postal").val(ui.item.cp);
            $("#champ-ville").val(ui.item.ville);

            // Empêche jQuery UI de remettre le 'label' dans le champ
            return false;
        },

        // 'focus' empêche jQuery UI de mettre le label dans le champ au survol
        focus: function (evenement, ui) {
            return false;
        }

    });

});
</script>

<?php include '../includes/footer.php'; ?>