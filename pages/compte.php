<?php
// ============================================================
// FICHIER : compte.php
// RÔLE    : Page "Mon compte" de l'utilisateur connecté
//
// CE FICHIER FAIT 3 GRANDES CHOSES :
//   1. Afficher et modifier le profil (nom, email, mot de passe)
//   2. Gérer PLUSIEURS adresses de livraison :
//       → Afficher toutes les adresses enregistrées
//       → Ajouter une nouvelle adresse
//       → Modifier une adresse existante (formulaire inline)
//       → Supprimer une adresse
//       → Définir une adresse comme "principale"
//
// COMMENT ÇA MARCHE EN RÉSUMÉ ?
//   → Le navigateur envoie des requêtes POST quand l'utilisateur
//     clique sur un bouton de formulaire.
//   → PHP reçoit ce POST, regarde quel bouton a été cliqué
//     grâce à isset($_POST['nom_du_bouton']), et exécute
//     la bonne action (ajout, modification, suppression...).
//   → Après chaque action, la page se recharge et affiche
//     le résultat mis à jour.
// ============================================================

// session_start() DOIT être appelé AVANT tout output HTML
// Elle démarre (ou reprend) la session PHP, ce qui permet
// d'accéder à $_SESSION['user_id'] (l'ID de l'utilisateur connecté).
session_start();

// require_once charge un fichier PHP externe UNE SEULE FOIS.
// Si le fichier a déjà été chargé, require_once ne le recharge pas.
// config.php  → constantes comme SITE_URL
// db.php      → connexion PDO à la base de données ($pdo)
// functions.php → fonctions utilitaires du site
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// ============================================================
// VÉRIFICATION DE CONNEXION
// ============================================================
// isset() vérifie si une variable existe ET n'est pas null.
// Si l'utilisateur n'est pas connecté (pas de user_id en session)
// → on le redirige vers la page de connexion avec header().
// exit() est OBLIGATOIRE après header() pour arrêter le script.
// Sans exit(), le reste du code continuerait à s'exécuter.
if (!isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "pages/connexion.php");
    exit();
}

// (int) est un "cast" : force la conversion en entier.
// Sécurité : si quelqu'un met ?user_id=abc dans l'URL,
// (int) "abc" donne 0, ce qui ne correspond à aucun utilisateur.
$user_id = (int) $_SESSION['user_id'];

// Variables d'état de la page :
// $erreurs  → tableau qui contiendra les messages d'erreur de validation
// $success  → booléen true/false pour savoir si une action a réussi
// $success_msg → le message de succès à afficher
$erreurs     = [];
$success     = false;
$success_msg = '';


// ============================================================
// SECTION 1 : RÉCUPÉRATION DES DONNÉES DE L'UTILISATEUR
// ============================================================

// prepare() crée une requête préparée avec un paramètre nommé :id
// Les requêtes préparées protègent contre les injections SQL :
// la valeur de :id est échappée automatiquement par PDO.
$stmt = $pdo->prepare("SELECT nom, prenom, email FROM utilisateurs WHERE id_utilisateur = :id");

// execute() exécute la requête en remplaçant :id par la valeur réelle
$stmt->execute(['id' => $user_id]);

// fetch() retourne UNE SEULE ligne sous forme de tableau associatif
// PDO::FETCH_ASSOC → clés = noms des colonnes SQL (ex: $user['nom'])
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Cas très rare mais à gérer : si la session existe mais pas l'utilisateur en BDD
// die() arrête tout le script et affiche le message
if (!$user) die("Utilisateur introuvable");

// On met les données dans des variables simples pour pré-remplir le formulaire HTML
$nom    = $user['nom'];
$prenom = $user['prenom'];
$email  = $user['email'];


// ============================================================
// SECTION 2 : FONCTION POUR RÉCUPÉRER TOUTES LES ADRESSES
// ============================================================
// On définit une FONCTION pour pouvoir rappeler facilement
// la liste des adresses après chaque action (ajout, suppression, etc.)
// Sans cette fonction, il faudrait réécrire la requête SQL à chaque fois.

function getAdresses($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM adresses 
        WHERE id_utilisateur = :id 
        ORDER BY est_principale DESC, date_creation DESC
    ");
    $stmt->execute(['id' => $user_id]);
    $adresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ CORRECTION : S'assurer qu'une seule adresse est principale
    // Si plusieurs ont est_principale=1 (bug BDD), on garde seulement la première
    $deja_principale = false;
    foreach ($adresses as &$addr) {
        if ($addr['est_principale']) {
            if ($deja_principale) {
                // Deuxième adresse "principale" → on la remet à 0 en BDD et en mémoire
                $pdo->prepare("UPDATE adresses SET est_principale = 0 WHERE id_adresse = ?")
                    ->execute([$addr['id_adresse']]);
                $addr['est_principale'] = 0;
            } else {
                $deja_principale = true;
            }
        }
    }
    unset($addr); // Libère la référence du foreach

    return $adresses;
}

// Premier appel à la fonction pour charger les adresses au démarrage de la page
$adresses = getAdresses($pdo, $user_id);


// ============================================================
// SECTION 3 : MODIFICATION DU PROFIL (POST)
// ============================================================
// $_SERVER['REQUEST_METHOD'] === 'POST' → vérifie que la page a reçu un formulaire
// isset($_POST['update_profil']) → vérifie que c'est BIEN le bouton "Enregistrer le profil"
//   qui a été cliqué (et pas le bouton d'ajout d'adresse par exemple)
// Cette double vérification permet d'avoir PLUSIEURS formulaires sur la même page
// sans qu'ils interfèrent entre eux.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {

    // trim() supprime les espaces au début et à la fin de la saisie
    // ?? '' est l'opérateur "null coalescent" : si $_POST['nom'] n'existe pas → ''
    $nom         = trim($_POST['nom']         ?? '');
    $prenom      = trim($_POST['prenom']      ?? '');
    $email       = trim($_POST['email']       ?? '');
    $mdp         = trim($_POST['mdp']         ?? '');
    $mdp_confirm = trim($_POST['mdp_confirm'] ?? '');

    // --- VALIDATION ---
    // On vérifie les champs AVANT de toucher à la base de données.
    // Si une erreur est trouvée, on l'ajoute au tableau $erreurs.
    // $erreurs[] = "..." ajoute un élément à la fin du tableau.

    // Vérification des champs obligatoires (nom, prénom, email)
    if ($nom === '' || $prenom === '' || $email === '') {
        $erreurs[] = "Tous les champs obligatoires sont requis.";
    }

    // filter_var() avec FILTER_VALIDATE_EMAIL vérifie le FORMAT de l'email
    // Exemple valide : test@gmail.com  |  Invalide : test@  ou  @gmail.com
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "Email invalide.";
    }

    // Le mot de passe est OPTIONNEL : si le champ est vide, on ne le change pas.
    // $update_mdp = false par défaut → deviendra true si le MDP doit être mis à jour.
    $update_mdp = false;
    if ($mdp !== '') {
        // strlen() compte le nombre de caractères de la chaîne
        if (strlen($mdp) < 6) {
            $erreurs[] = "Mot de passe trop court (minimum 6 caractères).";
        } elseif ($mdp !== $mdp_confirm) {
            // Les deux champs "nouveau mdp" et "confirmer mdp" doivent être identiques
            $erreurs[] = "Les mots de passe ne correspondent pas.";
        } else {
            // Pas d'erreur : on autorise la mise à jour du mot de passe
            $update_mdp = true;
        }
    }

    // empty($erreurs) → true si le tableau $erreurs est vide (pas d'erreur)
    // On n'exécute la requête UPDATE que s'il n'y a aucune erreur de validation.
    if (empty($erreurs)) {

        // ✅ VÉRIFICATION EMAIL DUPLIQUÉ
        // On vérifie si l'email saisi est déjà utilisé par UN AUTRE utilisateur
        // Le AND id_utilisateur != :id exclut l'utilisateur actuel de la recherche
        $checkEmail = $pdo->prepare("
            SELECT id_utilisateur FROM utilisateurs 
            WHERE email = :email AND id_utilisateur != :id
        ");
        $checkEmail->execute(['email' => $email, 'id' => $user_id]);

        if ($checkEmail->fetch()) {
            // Un autre compte utilise déjà cet email → on bloque la mise à jour
            $erreurs[] = "Cette adresse email est déjà utilisée par un autre compte.";
        } else {
            // Email disponible → on peut faire la mise à jour

            // On construit la requête SQL dynamiquement :
            // → La partie de base met à jour nom, prenom, email (toujours)
            // → On ajoute ", mot_de_passe = :mdp" seulement si $update_mdp est true
            // → Cela évite d'écraser le mot de passe quand il ne doit pas changer
            $sql    = "UPDATE utilisateurs SET nom = :nom, prenom = :prenom, email = :email";
            $params = [
                'nom'    => $nom,
                'prenom' => $prenom,
                'email'  => $email,
                'id'     => $user_id
            ];

            if ($update_mdp) {
                // password_hash() génère un hash sécurisé (bcrypt) du mot de passe.
                // On ne stocke JAMAIS un mot de passe en clair dans la BDD !
                // PASSWORD_DEFAULT = algorithme recommandé par PHP (bcrypt actuellement)
                $params['mdp'] = password_hash($mdp, PASSWORD_DEFAULT);
                $sql .= ", mot_de_passe = :mdp"; // On ajoute la colonne à mettre à jour
            }

            // On termine la requête avec la clause WHERE pour cibler cet utilisateur
            $sql .= " WHERE id_utilisateur = :id";

            // On prépare et exécute la requête en une seule ligne (chaînage de méthodes)
            $pdo->prepare($sql)->execute($params);

            // Mise à jour du nom affiché dans la navbar (stocké en session)
            $_SESSION['user_nom'] = $prenom . ' ' . $nom;

            $success     = true;
            $success_msg = "Profil mis à jour avec succès !";
        }
    }
}


// ============================================================
// SECTION 4 : AJOUT D'UNE NOUVELLE ADRESSE (AVEC VÉRIFICATION OTP)
// ============================================================
// ✅ MODIFICATION : Au lieu d'insérer directement l'adresse en BDD,
//    on génère un code OTP, on l'envoie par email, on sauvegarde
//    les données de l'adresse en SESSION, puis on redirige vers
//    la page de vérification (verify-adresse.php).
//    L'insertion réelle en BDD se fait APRÈS validation du code OTP.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_adresse'])) {

    // Chargement de la fonction d'envoi d'email et de génération OTP
    require_once '../includes/envoyer-email.php';

    $a_prenom     = trim($_POST['a_prenom']     ?? '');
    $a_nom        = trim($_POST['a_nom']        ?? '');
    $a_adresse    = trim($_POST['a_adresse']    ?? '');
    $a_complement = trim($_POST['a_complement'] ?? '');
    $a_cp         = trim($_POST['a_cp']         ?? '');
    $a_ville      = trim($_POST['a_ville']      ?? '');
    $a_pays       = trim($_POST['a_pays']       ?? 'France');
    $a_telephone  = trim($_POST['a_telephone']  ?? '');
    $a_principale = (isset($_POST['a_principale']) || empty($adresses)) ? 1 : 0;

    // Validation des champs obligatoires (identique à avant)
    if (empty($a_prenom) || empty($a_nom) || empty($a_adresse) || empty($a_cp) || empty($a_ville)) {
        $erreurs[] = "Veuillez remplir tous les champs obligatoires de l'adresse.";
    } else {

        // ✅ ÉTAPE 1 : Générer un code OTP à 6 chiffres
        $otp_code    = genererCodeOTP();
        $otp_expires = time() + (15 * 60); // Expire dans 15 minutes (timestamp UNIX)

        // ✅ ÉTAPE 2 : Sauvegarder TOUTES les données de l'adresse + OTP en session
        // On ne touche PAS encore à la BDD. Les données "attendent" la validation.
        $_SESSION['otp_adresse_pending'] = [
            'otp_code'      => $otp_code,
            'otp_expires'   => $otp_expires,
            'prenom'        => $a_prenom,
            'nom'           => $a_nom,
            'adresse'       => $a_adresse,
            'complement'    => $a_complement,
            'cp'            => $a_cp,
            'ville'         => $a_ville,
            'pays'          => $a_pays,
            'telephone'     => $a_telephone,
            'est_principale' => $a_principale,
        ];

        // ✅ ÉTAPE 3 : Envoyer le code OTP par email à l'utilisateur connecté
        // On utilise l'email de la session ($email récupéré plus haut dans le fichier)
        if (envoyerCodeVerification($email, $prenom, $otp_code)) {

            // ✅ ÉTAPE 4 : Rediriger vers la page de vérification
            header("Location: " . SITE_URL . "pages/verify-adresse.php");
            exit();

        } else {
            // Échec de l'envoi email → on annule et on affiche une erreur
            unset($_SESSION['otp_adresse_pending']);
            $erreurs[] = "Erreur lors de l'envoi du code de vérification. Veuillez réessayer.";
        }
    }
}

// ✅ AJOUT : Afficher le message flash de succès après redirection depuis verify-adresse.php
// $_SESSION['flash_success'] est défini dans verify-adresse.php après validation réussie
if (isset($_SESSION['flash_success'])) {
    $success     = true;
    $success_msg = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']); // On le supprime pour qu'il n'apparaisse qu'une fois
}


// ============================================================
// SECTION 5 : MODIFICATION D'UNE ADRESSE EXISTANTE
// ============================================================
// Déclenché quand l'utilisateur clique sur "Enregistrer les modifications"
// dans le formulaire d'édition inline d'une adresse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_adresse'])) {

    // (int) cast : force l'ID en entier pour la sécurité
    // ?? 0 : si id_adresse n'existe pas dans POST → 0 (invalide → sera rejeté)
    $id_adresse   = (int)($_POST['id_adresse'] ?? 0);

    // Récupération des champs du formulaire d'édition (mêmes noms que l'ajout)
    $a_prenom     = trim($_POST['a_prenom']     ?? '');
    $a_nom        = trim($_POST['a_nom']        ?? '');
    $a_adresse    = trim($_POST['a_adresse']    ?? '');
    $a_complement = trim($_POST['a_complement'] ?? '');
    $a_cp         = trim($_POST['a_cp']         ?? '');
    $a_ville      = trim($_POST['a_ville']      ?? '');
    $a_pays       = trim($_POST['a_pays']       ?? 'France');
    $a_telephone  = trim($_POST['a_telephone']  ?? '');
    $a_principale = isset($_POST['a_principale']) ? 1 : 0;

    // Validation des champs obligatoires
    if (empty($a_prenom) || empty($a_nom) || empty($a_adresse) || empty($a_cp) || empty($a_ville)) {
        $erreurs[] = "Veuillez remplir tous les champs obligatoires de l'adresse.";
    } else {

        // ⚠️ VÉRIFICATION DE SÉCURITÉ IMPORTANTE ⚠️
        // On vérifie que l'adresse à modifier APPARTIENT BIEN à cet utilisateur.
        // Sans cette vérification, un utilisateur malveillant pourrait modifier
        // l'adresse d'un autre utilisateur en envoyant manuellement un POST
        // avec n'importe quel id_adresse.
        // La condition WHERE id_utilisateur = ? garantit que même si quelqu'un
        // envoie l'ID d'une adresse d'un autre utilisateur, la requête ne trouvera rien.
        $check = $pdo->prepare("
            SELECT id_adresse FROM adresses 
            WHERE id_adresse = ? AND id_utilisateur = ?
        ");
        $check->execute([$id_adresse, $user_id]);

        if (!$check->fetch()) {
            // L'adresse n'existe pas OU n'appartient pas à cet utilisateur → erreur
            $erreurs[] = "Adresse introuvable.";
        } else {

            // Même logique que pour l'ajout :
            // Si on définit cette adresse comme principale → remettre les autres à 0 d'abord
            if ($a_principale) {
                $pdo->prepare("UPDATE adresses SET est_principale = 0 WHERE id_utilisateur = ?")
                    ->execute([$user_id]);
            }

            // UPDATE : met à jour les colonnes de l'adresse existante
            // Le WHERE id_adresse = ? AND id_utilisateur = ? est une double sécurité :
            // → id_adresse = ? : cible la bonne adresse
            // → id_utilisateur = ? : s'assure qu'elle appartient à cet utilisateur
            $pdo->prepare("
                UPDATE adresses SET
                    prenom            = ?,
                    nom               = ?,
                    adresse           = ?,
                    complement_adresse = ?,
                    code_postal       = ?,
                    ville             = ?,
                    pays              = ?,
                    telephone         = ?,
                    est_principale    = ?
                WHERE id_adresse = ? AND id_utilisateur = ?
            ")->execute([
                $a_prenom,
                $a_nom,
                $a_adresse,
                $a_complement,
                $a_cp,
                $a_ville,
                $a_pays,
                $a_telephone,
                $a_principale,
                $id_adresse, // Identifie quelle adresse modifier
                $user_id     // Double sécurité : l'adresse doit appartenir à cet utilisateur
            ]);

            $success     = true;
            $success_msg = "Adresse modifiée avec succès !";

            // Rafraîchir la liste des adresses avec les nouvelles valeurs
            $adresses = getAdresses($pdo, $user_id);
        }
    }
}


// ============================================================
// SECTION 6 : SUPPRESSION D'UNE ADRESSE
// ============================================================
// Déclenché quand l'utilisateur clique sur le bouton "Supprimer" d'une adresse
// et confirme la boîte de dialogue JavaScript (return confirm(...))
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_adresse'])) {

    $id_adresse = (int)($_POST['id_adresse'] ?? 0);

    // Vérification de sécurité : l'adresse appartient-elle à cet utilisateur ?
    // On récupère aussi est_principale pour savoir si c'était la principale.
    $check = $pdo->prepare("
        SELECT id_adresse, est_principale 
        FROM adresses 
        WHERE id_adresse = ? AND id_utilisateur = ?
    ");
    $check->execute([$id_adresse, $user_id]);
    $addr_to_delete = $check->fetch();

    if ($addr_to_delete) {
        // Suppression de l'adresse
        $pdo->prepare("DELETE FROM adresses WHERE id_adresse = ? AND id_utilisateur = ?")
            ->execute([$id_adresse, $user_id]);

        // CAS PARTICULIER : si on vient de supprimer l'adresse principale,
        // on doit en désigner une autre automatiquement.
        // Sans ça, l'utilisateur n'aurait plus d'adresse principale du tout.
        if ($addr_to_delete['est_principale']) {

            // On cherche la première adresse restante (la plus récente)
            $first = $pdo->prepare("
                SELECT id_adresse FROM adresses 
                WHERE id_utilisateur = ? 
                ORDER BY date_creation DESC 
                LIMIT 1
            ");
            $first->execute([$user_id]);
            $next = $first->fetch();

            // Si une adresse restante existe → on la définit comme principale
            if ($next) {
                $pdo->prepare("UPDATE adresses SET est_principale = 1 WHERE id_adresse = ?")
                    ->execute([$next['id_adresse']]);
            }
            // Sinon : l'utilisateur n'a plus aucune adresse → rien à faire
        }

        $success     = true;
        $success_msg = "Adresse supprimée.";

        // Rafraîchir la liste (l'adresse supprimée ne doit plus apparaître)
        $adresses = getAdresses($pdo, $user_id);
    }
}


// ============================================================
// SECTION 7 : DÉFINIR UNE ADRESSE COMME PRINCIPALE
// ============================================================
// Déclenché quand l'utilisateur clique sur le bouton "⭐ Principale"
// d'une adresse qui n'est pas encore la principale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_principale'])) {

    $id_adresse = (int)($_POST['id_adresse'] ?? 0);

    // Vérification de sécurité : appartient à cet utilisateur ?
    $check = $pdo->prepare("
        SELECT id_adresse FROM adresses 
        WHERE id_adresse = ? AND id_utilisateur = ?
    ");
    $check->execute([$id_adresse, $user_id]);

    if ($check->fetch()) {

        // ÉTAPE 1 : Remettre TOUTES les adresses de l'utilisateur à est_principale = 0
        // (Pour s'assurer qu'il n'y a qu'une seule principale à la fois)
        $pdo->prepare("UPDATE adresses SET est_principale = 0 WHERE id_utilisateur = ?")
            ->execute([$user_id]);

        // ÉTAPE 2 : Mettre cette adresse spécifique à est_principale = 1
        $pdo->prepare("UPDATE adresses SET est_principale = 1 WHERE id_adresse = ? AND id_utilisateur = ?")
            ->execute([$id_adresse, $user_id]);

        $success     = true;
        $success_msg = "Adresse principale mise à jour !";

        // Rafraîchir pour que l'affichage montre la nouvelle principale
        $adresses = getAdresses($pdo, $user_id);
    }
}


// ============================================================
// PRÉPARATION AVANT L'AFFICHAGE HTML
// ============================================================

// $edit_id contient l'ID de l'adresse en cours de modification.
// Il vient du paramètre GET dans l'URL : profil.php?edit_addr=5
// → $_GET['edit_addr'] = "5" → (int) = 5
// Si pas de paramètre edit_addr dans l'URL → ?? 0 → $edit_id = 0 (aucune édition)
// Cet ID est utilisé plus bas dans le foreach pour savoir quelle adresse
// doit afficher son formulaire d'édition au lieu de ses informations.
$edit_id = (int)($_GET['edit_addr'] ?? 0);

$pageTitle = "Mon compte";
include '../includes/header.php'; // Charge le header HTML commun (navbar, <head>...)
?>

<!-- BOOTSTRAP ICONS : bibliothèque d'icônes (bi-house, bi-trash, bi-star...) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    /* Classe CSS pour les boutons avec effet au survol */
    .btn-pill-pro {
        transition: all 0.3s ease; /* Animation fluide sur toutes les propriétés */
    }
    .btn-pill-pro:hover {
        transform: translateY(-3px);            /* Monte de 3px au survol */
        box-shadow: 0 10px 25px rgba(0,0,0,0.25); /* Ombre portée */
    }

    /* Style de base pour toutes les cartes adresse */
    .adresse-card {
        border-radius: 16px;
        border: 2px solid #dee2e6; /* Gris par défaut */
        transition: border-color 0.3s;
        overflow: hidden; /* Empêche le contenu de dépasser les coins arrondis */
    }

    /* Variante verte pour l'adresse marquée "principale" */
    .adresse-card.principale {
        border-color: #198754; /* Vert Bootstrap success */
        border-width: 3px;     /* Bordure plus épaisse pour la mettre en valeur */
    }

    /* Personnalisation du focus des champs de formulaire */
    .form-control:focus, .form-select:focus {
        border-color: #ff8c00;                         /* Orange au focus */
        box-shadow: 0 0 0 0.2rem rgba(255,140,0,0.2);  /* Lueur orange subtile */
    }

    /* Le formulaire d'ajout est CACHÉ par défaut.
       Il sera affiché par toggleFormAdd() en JavaScript. */
    #form-add-adresse {
        display: none;
    }
</style>

<!-- ============================================================ -->
<!-- STRUCTURE HTML PRINCIPALE                                     -->
<!-- ============================================================ -->
<div class="container py-5" style="padding-top:120px !important;">
    <!-- Grande carte blanche centrée qui contient toute la page -->
    <div class="card shadow-lg p-5 mx-auto" style="max-width:960px; border-radius:30px;">

        <h1 class="text-center mb-5 fw-bold">Mon compte</h1>

        <!-- ===== MESSAGES DE RETOUR UTILISATEUR ===== -->

        <!-- Message de SUCCÈS : affiché en vert si $success est true -->
        <?php if ($success && $success_msg): ?>
            <div class="alert alert-success text-center mb-4 fs-5">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars($success_msg) ?>
                <!-- htmlspecialchars() protège contre les failles XSS :
                     convertit < > " ' & en entités HTML (&lt; &gt; etc.)
                     pour qu'un texte malveillant ne soit pas interprété comme du HTML -->
            </div>
        <?php endif; ?>

        <!-- Message d'ERREUR : affiché en rouge si $erreurs n'est pas vide -->
        <?php if ($erreurs): ?>
            <div class="alert alert-danger fs-5">
                <?php foreach ($erreurs as $e): ?>
                    <!-- On affiche chaque erreur du tableau sur une ligne -->
                    <div>
                        <i class="bi bi-x-circle-fill me-2"></i>
                        <?= htmlspecialchars($e) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>


        <!-- ============================================================ -->
        <!-- BLOC 1 : FORMULAIRE DE MODIFICATION DU PROFIL                -->
        <!-- ============================================================ -->
        <!--
            Ce formulaire modifie les infos personnelles de l'utilisateur.
            Le bouton "submit" a name="update_profil" → PHP le détecte avec
            isset($_POST['update_profil']) pour traiter ce formulaire
            (et pas les autres sur la même page).
        -->
        <form method="post">
            <div class="row g-4 mb-5 justify-content-center">

                <!-- Champ Prénom -->
                <div class="col-md-5">
                    <label class="form-label fw-bold">Prénom</label>
                    <!-- value="..." pré-remplit le champ avec la valeur actuelle en BDD -->
                    <input class="form-control py-3" name="prenom"
                           value="<?= htmlspecialchars($prenom) ?>" required>
                </div>

                <!-- Champ Nom -->
                <div class="col-md-5">
                    <label class="form-label fw-bold">Nom</label>
                    <input class="form-control py-3" name="nom"
                           value="<?= htmlspecialchars($nom) ?>" required>
                </div>

                <!-- Champ Email (pleine largeur) -->
                <div class="col-12 col-md-10">
                    <label class="form-label fw-bold">Email</label>
                    <!-- type="email" : le navigateur valide le format avant envoi -->
                    <input class="form-control py-3" type="email" name="email"
                           value="<?= htmlspecialchars($email) ?>" required>
                </div>

                <!-- Champ Nouveau mot de passe (OPTIONNEL) -->
                <!-- Si laissé vide → le mot de passe actuel est conservé -->
                <div class="col-md-5">
                    <label class="form-label fw-bold">
                        Nouveau mot de passe
                        <span class="text-muted fw-normal">(optionnel)</span>
                    </label>
                    <!-- type="password" : masque la saisie avec des ● -->
                    <input class="form-control py-3" type="password" name="mdp"
                           placeholder="Laisser vide si inchangé">
                </div>

                <!-- Champ Confirmation mot de passe -->
                <!-- PHP vérifie que ce champ = le champ mdp pour éviter les fautes de frappe -->
                <div class="col-md-5">
                    <label class="form-label fw-bold">Confirmer le mot de passe</label>
                    <input class="form-control py-3" type="password" name="mdp_confirm"
                           placeholder="Confirmer si changement">
                </div>

                <!-- Bouton de soumission du profil -->
                <!-- name="update_profil" → identifie ce formulaire côté PHP -->
                <div class="col-12 col-md-10 text-center mt-4">
                    <button class="btn btn-lg px-5 py-3 fw-bold rounded-pill text-white btn-pill-pro"
                            type="submit" name="update_profil"
                            style="background: linear-gradient(90deg, #ff8c00, #e07b00); border: none;">
                        <i class="bi bi-save me-2"></i>ENREGISTRER LES MODIFICATIONS
                    </button>
                </div>
            </div>
        </form>

        <!-- Séparateur visuel entre le profil et les adresses -->
        <hr class="my-5">


        <!-- ============================================================ -->
        <!-- BLOC 2 : EN-TÊTE DE LA SECTION ADRESSES                      -->
        <!-- ============================================================ -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0">
                <i class="bi bi-geo-alt-fill me-2 text-warning"></i>
                Mes adresses de livraison

                <!-- Badge indiquant le nombre d'adresses enregistrées -->
                <!-- Affiché seulement si l'utilisateur a au moins 1 adresse -->
                <?php if (!empty($adresses)): ?>
                    <span class="badge rounded-pill ms-2" style="background: #ff8c00; font-size: 0.75em;">
                        <?= count($adresses) ?>
                        <!-- count() retourne le nombre d'éléments dans un tableau -->
                    </span>
                <?php endif; ?>
            </h4>

            <!-- Bouton qui AFFICHE le formulaire d'ajout (caché par défaut) -->
            <!-- onclick="toggleFormAdd()" appelle la fonction JavaScript définie plus bas -->
            <button class="btn btn-lg px-4 py-2 fw-bold rounded-pill text-white btn-pill-pro"
                    style="background: linear-gradient(90deg, #ff8c00, #e07b00); border: none;"
                    onclick="toggleFormAdd()">
                <i class="bi bi-plus-circle me-2"></i>Ajouter une adresse
            </button>
        </div>


        <!-- ============================================================ -->
        <!-- FORMULAIRE D'AJOUT D'UNE NOUVELLE ADRESSE                    -->
        <!-- ============================================================ -->
        <!--
            Ce div est CACHÉ par défaut (CSS : #form-add-adresse { display: none; })
            toggleFormAdd() en JavaScript le rend visible quand l'utilisateur clique
            sur "Ajouter une adresse".
        -->
        <div id="form-add-adresse" class="card mb-4 p-4 border-0 shadow-sm"
             style="background:#fffbf4; border-radius:16px;">
            <h5 class="fw-bold mb-4">
                <i class="bi bi-plus-circle-fill me-2" style="color:#ff8c00;"></i>
                Nouvelle adresse
            </h5>

            <!-- name="add_adresse" sur le bouton submit → PHP identifie ce formulaire -->
            <form method="POST">
                <div class="row g-3">

                    <!-- Prénom et Nom de la personne à livrer -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Prénom <span class="text-danger">*</span>
                        </label>
                        <!-- name="a_prenom" → le "a_" distingue des champs du profil -->
                        <input type="text" class="form-control py-2"
                               name="a_prenom" required placeholder="Prénom">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Nom <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control py-2"
                               name="a_nom" required placeholder="Nom">
                    </div>

                    <!-- Adresse principale (rue, numéro) -->
                    <div class="col-12">
                        <label class="form-label fw-bold">
                            Adresse <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control py-2" name="a_adresse"
                               id="add-adresse" autocomplete="off"
                               required placeholder="12 Rue de la République">
                    </div>

                    <!-- Complément d'adresse : bâtiment, étage, code... (optionnel) -->
                    <div class="col-12">
                        <label class="form-label fw-bold">Complément d'adresse</label>
                        <input type="text" class="form-control py-2" name="a_complement"
                               placeholder="Bât. A, Appt. 12...">
                    </div>

                    <!-- Code postal, Ville, Pays sur la même ligne -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            Code postal <span class="text-danger">*</span>
                        </label>
                        <!-- maxlength="5" limite la saisie à 5 caractères (codes FR) -->
                        <input type="text" class="form-control py-2" name="a_cp"
                               id="add-cp" required placeholder="75000" maxlength="5">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">
                            Ville <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control py-2"
                               name="a_ville" id="add-ville" required placeholder="Paris">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Pays</label>
                        <!-- <select> : liste déroulante avec les pays disponibles -->
                        <select class="form-select py-2" name="a_pays" id="add-pays">
                            <option value="France">France</option>
                            <option value="Belgique">Belgique</option>
                            <option value="Suisse">Suisse</option>
                            <option value="Luxembourg">Luxembourg</option>
                        </select>
                    </div>

                    <!-- Téléphone (optionnel) et case "Adresse principale" -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Téléphone</label>
                        <!-- type="tel" → clavier téléphone sur mobile -->
                        <input type="tel" class="form-control py-2" name="a_telephone"
                               placeholder="06 12 34 56 78">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <!-- Case cochée automatiquement si l'utilisateur n'a pas encore d'adresse -->
                            <!-- empty($adresses) → true si le tableau est vide -->
                            <input class="form-check-input" type="checkbox"
                                   name="a_principale" value="1" id="add_principale"
                                   <?= empty($adresses) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="add_principale">
                                Définir comme adresse principale
                            </label>
                        </div>
                    </div>

                    <!-- Boutons : Enregistrer + Annuler -->
                    <div class="col-12 d-flex gap-3 mt-3">
                        <!-- name="add_adresse" → PHP sait que c'est un ajout -->
                        <button type="submit" name="add_adresse"
                                class="btn btn-lg px-5 py-2 fw-bold rounded-pill text-white btn-pill-pro"
                                style="background: linear-gradient(90deg, #ff8c00, #e07b00); border: none;">
                            <i class="bi bi-save me-2"></i>Enregistrer
                        </button>
                        <!-- Annuler : ferme le formulaire sans soumettre -->
                        <button type="button" onclick="toggleFormAdd()"
                                class="btn btn-lg px-4 py-2 fw-bold rounded-pill btn-outline-secondary">
                            Annuler
                        </button>
                    </div>

                </div>
            </form>
        </div><!-- fin #form-add-adresse -->


        <!-- ============================================================ -->
        <!-- LISTE DE TOUTES LES ADRESSES ENREGISTRÉES                    -->
        <!-- ============================================================ -->

        <?php if (empty($adresses)): ?>
            <!-- CAS : L'utilisateur n'a aucune adresse enregistrée -->
            <div class="text-center py-5 bg-light rounded-3 border">
                <i class="bi bi-geo-alt fs-1 text-muted mb-3 d-block"></i>
                <p class="lead text-muted mb-4">
                    Vous n'avez pas encore enregistré d'adresse de livraison.
                </p>
                <!-- Ce bouton appelle toggleFormAdd() pour ouvrir le formulaire d'ajout -->
                <button onclick="toggleFormAdd()"
                        class="btn btn-lg px-5 py-3 fw-bold rounded-pill text-white btn-pill-pro"
                        style="background: linear-gradient(90deg, #ff8c00, #e07b00); border: none;">
                    <i class="bi bi-plus-circle me-2"></i>Ajouter mon adresse
                </button>
            </div>

        <?php else: ?>
            <!-- CAS : L'utilisateur a au moins une adresse → on les affiche toutes -->

            <div class="row g-4">
                <!--
                    foreach ($adresses as $addr) :
                    → Boucle sur le tableau $adresses (récupéré depuis la BDD)
                    → À chaque tour, $addr contient les infos d'UNE adresse
                    → Le tableau $adresses est trié : principale en premier
                -->
                <?php foreach ($adresses as $addr):

                    // On compare l'ID de cette adresse avec $edit_id (paramètre URL ?edit_addr=X)
                    // Si les deux correspondent → on affiche le formulaire d'édition pour cette adresse
                    // Sinon → on affiche les informations normalement
                    $is_editing = ($edit_id === (int)$addr['id_adresse']);
                ?>
                <div class="col-12">

                    <!--
                        Classe CSS conditionnelle :
                        → "adresse-card principale" si est_principale = 1 (bordure verte)
                        → "adresse-card" si est_principale = 0 (bordure grise)
                        L'opérateur ternaire : condition ? valeur_si_vrai : valeur_si_faux
                    -->
                    <div class="adresse-card <?= $addr['est_principale'] ? 'principale' : '' ?>">

                        <!-- ===== EN-TÊTE DE LA CARTE ADRESSE ===== -->
                        <!-- Fond légèrement coloré selon si c'est la principale ou non -->
                        <div class="d-flex justify-content-between align-items-center px-4 py-3"
                             style="background: <?= $addr['est_principale'] ? '#f0fff4' : '#f8f9fa' ?>;">

                            <!-- Nom de la personne + badge "Principale" éventuel -->
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-house-door-fill fs-4 text-primary"></i>
                                <span class="fw-bold fs-5">
                                    <?= htmlspecialchars($addr['prenom'] . ' ' . $addr['nom']) ?>
                                </span>

                                <?php if ($addr['est_principale']): ?>
                                    <!-- Badge vert "Principale" : visible seulement pour l'adresse principale -->
                                    <span class="badge bg-success rounded-pill fs-6 px-3">
                                        <i class="bi bi-star-fill me-1"></i>Principale
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Boutons d'action sur la carte -->
                            <div class="d-flex gap-2 flex-wrap">

                                <!--
                                    Bouton MODIFIER (bascule formulaire d'édition) :
                                    C'est un lien <a>, pas un bouton de formulaire.
                                    Il modifie l'URL en ajoutant ?edit_addr=ID.
                                    PHP récupère cet ID via $_GET['edit_addr'].
                                    → Si on est déjà en train d'éditer cette adresse ($is_editing = true)
                                      → le lien remet edit_addr à 0 (ferme l'édition)
                                    → Sinon → le lien ouvre l'édition pour cette adresse
                                -->
                                <a href="?edit_addr=<?= $is_editing ? 0 : $addr['id_adresse'] ?>"
                                   class="btn btn-sm fw-bold rounded-pill text-white"
                                   style="background: linear-gradient(90deg, #ff8c00, #e07b00);">
                                    <i class="bi bi-pencil-square me-1"></i>
                                    <?= $is_editing ? 'Annuler' : 'Modifier' ?>
                                </a>

                                <?php if (!$addr['est_principale']): ?>
                                <!--
                                    Bouton "Définir comme principale" :
                                    Visible SEULEMENT si cette adresse n'est pas déjà la principale.
                                    Envoie un POST avec name="set_principale" et l'ID de l'adresse.
                                -->
                                <form method="POST" class="d-inline">
                                    <!-- Champ caché : transmet l'ID de cette adresse au PHP -->
                                    <input type="hidden" name="id_adresse" value="<?= $addr['id_adresse'] ?>">
                                    <button type="submit" name="set_principale"
                                            class="btn btn-sm fw-bold rounded-pill btn-outline-success">
                                        <i class="bi bi-star me-1"></i>Principale
                                    </button>
                                </form>
                                <?php endif; ?>

                                <!--
                                    Bouton SUPPRIMER :
                                    onsubmit="return confirm(...)" affiche une boîte de dialogue JS.
                                    → Si l'utilisateur clique "OK" → return true → formulaire soumis
                                    → Si l'utilisateur clique "Annuler" → return false → formulaire annulé
                                -->
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Supprimer cette adresse ?')">
                                    <input type="hidden" name="id_adresse" value="<?= $addr['id_adresse'] ?>">
                                    <!-- name="delete_adresse" → PHP identifie cette action -->
                                    <button type="submit" name="delete_adresse"
                                            class="btn btn-sm fw-bold rounded-pill btn-outline-danger">
                                        <i class="bi bi-trash3-fill me-1"></i>Supprimer
                                    </button>
                                </form>

                            </div>
                        </div><!-- fin en-tête carte -->


                        <!-- ===== CORPS DE LA CARTE : AFFICHAGE OU FORMULAIRE D'ÉDITION ===== -->
                        <div class="p-4">

                            <?php if (!$is_editing): ?>
                                <!-- ---- MODE AFFICHAGE NORMAL ---- -->
                                <!-- On affiche simplement les infos de l'adresse -->

                                <p class="mb-2 fs-5">
                                    <i class="bi bi-geo-alt-fill me-2 text-muted"></i>
                                    <?= htmlspecialchars($addr['adresse']) ?>
                                </p>

                                <!-- Complément d'adresse : affiché seulement s'il est renseigné -->
                                <!-- ET différent de l'adresse principale pour éviter les doublons -->
                                <?php if (!empty($addr['complement_adresse']) && $addr['complement_adresse'] !== $addr['adresse']): ?>
                                    <p class="mb-2 text-muted fs-5">
                                        <i class="bi bi-signpost me-2 text-muted"></i>
                                        <?= htmlspecialchars($addr['complement_adresse']) ?>
                                    </p>
                                <?php endif; ?>

                                <p class="mb-2 fs-5">
                                    <i class="bi bi-geo-fill me-2 text-muted"></i>
                                    <!-- Concaténation du code postal et de la ville avec un espace -->
                                    <?= htmlspecialchars($addr['code_postal'] . ' ' . $addr['ville']) ?>
                                </p>

                                <p class="mb-2 fs-5">
                                    <i class="bi bi-globe me-2 text-muted"></i>
                                    <!-- L'opérateur ?: (Elvis) : si pays est vide → affiche 'France' -->
                                    <?= htmlspecialchars($addr['pays'] ?: 'France') ?>
                                </p>

                                <!-- Téléphone : affiché seulement s'il est renseigné -->
                                <?php if (!empty($addr['telephone'])): ?>
                                    <p class="mb-0 fs-5">
                                        <i class="bi bi-telephone-fill me-2 text-success"></i>
                                        <?= htmlspecialchars($addr['telephone']) ?>
                                    </p>
                                <?php endif; ?>

                            <?php else: ?>
                                <!-- ---- MODE ÉDITION : Formulaire pré-rempli ---- -->
                                <!--
                                    Ce formulaire est affiché à la place des infos
                                    quand $is_editing est true (URL contient ?edit_addr=ID).
                                    Tous les champs sont pré-remplis avec les valeurs
                                    actuelles de $addr (récupérées depuis la BDD).
                                    L'utilisateur peut les modifier puis cliquer "Enregistrer".
                                -->
                                <form method="POST">
                                    <!--
                                        Champ caché (type="hidden") :
                                        Non visible à l'écran, mais envoyé dans le POST.
                                        PHP en a besoin pour savoir QUELLE adresse modifier.
                                        Sans ce champ, PHP ne saurait pas quel id_adresse mettre à jour.
                                    -->
                                    <input type="hidden" name="id_adresse" value="<?= $addr['id_adresse'] ?>">

                                    <div class="row g-3">
                                        <!-- Prénom pré-rempli avec la valeur actuelle -->
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">
                                                Prénom <span class="text-danger">*</span>
                                            </label>
                                            <!-- value="..." = valeur actuelle de la BDD -->
                                            <input type="text" class="form-control py-2" name="a_prenom"
                                                   value="<?= htmlspecialchars($addr['prenom']) ?>" required>
                                        </div>

                                        <!-- Nom pré-rempli -->
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">
                                                Nom <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control py-2" name="a_nom"
                                                   value="<?= htmlspecialchars($addr['nom']) ?>" required>
                                        </div>

                                        <!-- Adresse pré-remplie -->
                                        <div class="col-12">
                                            <label class="form-label fw-bold">
                                                Adresse <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control py-2" name="a_adresse"
                                                   id="edit-adresse" autocomplete="off"
                                                   value="<?= htmlspecialchars($addr['adresse']) ?>" required>
                                        </div>

                                        <!-- Complément pré-rempli (peut être vide → ?? '') -->
                                        <div class="col-12">
                                            <label class="form-label fw-bold">Complément d'adresse</label>
                                            <!-- ?? '' : si complement_adresse est null → chaîne vide -->
                                            <input type="text" class="form-control py-2" name="a_complement"
                                                   value="<?= htmlspecialchars($addr['complement_adresse'] ?? '') ?>"
                                                   placeholder="Bât. A, Appt. 12...">
                                        </div>

                                        <!-- Code postal, Ville, Pays sur la même ligne -->
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">
                                                Code postal <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control py-2" name="a_cp"
                                                   id="edit-cp"
                                                   value="<?= htmlspecialchars($addr['code_postal']) ?>"
                                                   required maxlength="5">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">
                                                Ville <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control py-2" name="a_ville"
                                                   id="edit-ville"
                                                   value="<?= htmlspecialchars($addr['ville']) ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Pays</label>
                                            <select class="form-select py-2" name="a_pays" id="edit-pays">
                                                <?php foreach (['France','Belgique','Suisse','Luxembourg'] as $p): ?>
                                                    <!--
                                                        Pour chaque pays, on génère une option.
                                                        "selected" est ajouté à l'option qui correspond
                                                        au pays enregistré → l'utilisateur voit le bon pays.
                                                    -->
                                                    <option value="<?= $p ?>"
                                                        <?= ($addr['pays'] === $p) ? 'selected' : '' ?>>
                                                        <?= $p ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Téléphone pré-rempli -->
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Téléphone</label>
                                            <input type="tel" class="form-control py-2" name="a_telephone"
                                                   value="<?= htmlspecialchars($addr['telephone'] ?? '') ?>">
                                        </div>

                                        <!-- Case "Adresse principale" pré-cochée si c'est déjà la principale -->
                                        <div class="col-md-6 d-flex align-items-end">
                                            <div class="form-check">
                                                <!--
                                                    id unique par adresse : "edit_principale_5" (par exemple)
                                                    Cela permet d'associer le <label> au bon <input>
                                                    même quand il y a plusieurs cases à cocher sur la page.
                                                    checked : ajouté si est_principale = 1 en BDD
                                                -->
                                                <input class="form-check-input" type="checkbox"
                                                       name="a_principale" value="1"
                                                       id="edit_principale_<?= $addr['id_adresse'] ?>"
                                                       <?= $addr['est_principale'] ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-bold"
                                                       for="edit_principale_<?= $addr['id_adresse'] ?>">
                                                    Adresse principale
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Boutons Enregistrer et Annuler -->
                                        <div class="col-12 d-flex gap-3 mt-2">
                                            <!-- name="edit_adresse" → PHP identifie cette action comme une modification -->
                                            <button type="submit" name="edit_adresse"
                                                    class="btn btn-lg px-5 py-2 fw-bold rounded-pill text-white btn-pill-pro"
                                                    style="background: linear-gradient(90deg, #ff8c00, #e07b00); border: none;">
                                                <i class="bi bi-save me-2"></i>Enregistrer les modifications
                                            </button>

                                            <!-- Annuler : retourne sur la page sans paramètre edit_addr -->
                                            <a href="compte.php"
                                               class="btn btn-lg px-4 py-2 fw-bold rounded-pill btn-outline-secondary">
                                                Annuler
                                            </a>
                                        </div>

                                    </div><!-- fin .row -->
                                </form>

                            <?php endif; // fin if/else $is_editing ?>

                        </div><!-- fin .p-4 corps carte -->

                    </div><!-- fin .adresse-card -->
                </div><!-- fin .col-12 -->

                <?php endforeach; // fin boucle foreach($adresses as $addr) ?>

            </div><!-- fin .row liste adresses -->

        <?php endif; // fin if(empty($adresses)) ?>

    </div><!-- fin .card principale -->
</div><!-- fin .container -->


<!-- ============================================================ -->
<!-- JAVASCRIPT                                                    -->
<!-- ============================================================ -->
<script>
/**
 * toggleFormAdd()
 * ----------------
 * Affiche ou masque le formulaire d'ajout d'adresse.
 *
 * Fonctionnement :
 *  1. On récupère l'élément HTML avec l'id "form-add-adresse"
 *  2. On lit son style.display actuel :
 *     → 'block' = visible → on le cache (display = 'none')
 *     → autre ('' ou 'none') = caché → on l'affiche (display = 'block')
 *  3. Si on vient de l'afficher → scrollIntoView() fait défiler
 *     la page pour que l'utilisateur le voie directement.
 */
function toggleFormAdd() {
    const form = document.getElementById('form-add-adresse');

    // On lit le display actuel (true = visible, false = caché)
    const isVisible = form.style.display === 'block';

    // Bascule : si visible → cacher, si caché → afficher
    form.style.display = isVisible ? 'none' : 'block';

    // Si on vient d'ouvrir le formulaire → scroll automatique vers lui
    if (!isVisible) {
        // behavior: 'smooth' = animation de défilement fluide
        // block: 'start' = aligne le haut du formulaire avec le haut de la fenêtre
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}


<?php if ($edit_id > 0): ?>
/**
 * Scroll automatique vers le formulaire d'édition
 * ------------------------------------------------
 * Ce code PHP génère du JavaScript SEULEMENT si $edit_id > 0
 * (c'est-à-dire si l'URL contient ?edit_addr=X).
 *
 * Au chargement de la page, on cherche le champ caché id_adresse
 * qui correspond à l'adresse en cours d'édition, puis on scrolle
 * vers sa carte pour que l'utilisateur la voie directement.
 *
 * document.addEventListener('DOMContentLoaded', ...) :
 * → Attend que tout le HTML soit chargé avant d'exécuter le code.
 * → Sans ça, le JS pourrait s'exécuter avant que les éléments HTML
 *   existent dans le DOM, et querySelector() retournerait null.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Cherche un input[name="id_adresse"] avec la valeur de $edit_id
    const editInput = document.querySelector(
        'input[name="id_adresse"][value="<?= $edit_id ?>"]'
    );
    if (editInput) {
        // closest('.adresse-card') remonte dans le DOM pour trouver
        // le parent avec la classe "adresse-card"
        editInput.closest('.adresse-card').scrollIntoView({
            behavior: 'smooth'
        });
    }
});
<?php endif; ?>
</script>

<!-- ============================================================ -->
<!-- JQUERY + JQUERY UI                                           -->
<!-- Chargés ICI (après Bootstrap) pour éviter tout conflit      -->
<!-- ============================================================ -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<style>
    /* ---- Style de la liste déroulante de suggestions ---- */
    .ui-autocomplete {
        max-height: 260px;
        overflow-y: auto;
        overflow-x: hidden;
        border: 1px solid #dee2e6;
        border-radius: 12px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        background: #fff;
        z-index: 9999 !important;
        font-size: 0.9rem;
        padding: 4px 0;
    }
    .ui-menu-item-wrapper {
        padding: 10px 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        border-left: 3px solid transparent;
        transition: background 0.15s;
    }
    .ui-menu-item-wrapper::before {
        content: "📍";
        font-size: 0.85rem;
        flex-shrink: 0;
    }
    .ui-menu-item-wrapper.ui-state-active {
        background: linear-gradient(90deg, #fff5e6, #fff0d6) !important;
        border-left: 3px solid #ff8c00 !important;
        color: #333 !important;
        border-radius: 0;
    }

    /* ---- Indicateur de chargement dans le champ ---- */
    .adresse-wrapper {
        position: relative;
    }
    .adresse-wrapper .spinner-adresse {
        display: none;
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        width: 18px;
        height: 18px;
        border: 2px solid #dee2e6;
        border-top-color: #ff8c00;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }
    @keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }
</style>

<script>
$(document).ready(function () {

    // ----------------------------------------------------------------
    // FONCTION PRINCIPALE : autocomplétion via l'API Adresse gouv.fr
    //
    // API gratuite, sans clé, maintenue par le gouvernement français.
    // Elle couvre TOUTES les adresses de France (rues, places, etc.)
    //
    // @param champId  ID du champ adresse  ex: "#add-adresse"
    // @param cpId     ID du champ CP       ex: "#add-cp"
    // @param villeId  ID du champ ville    ex: "#add-ville"
    // @param paysId   ID du champ pays     ex: "#add-pays"
    // ----------------------------------------------------------------
    function initAutocompleteAPI(champId, cpId, villeId, paysId) {

        var timer = null; // Pour le délai anti-spam (debounce)

        $(champId).autocomplete({

            // 'source' est appelé à chaque frappe de l'utilisateur.
            // Au lieu d'une liste statique, on interroge l'API en temps réel.
            source: function (requete, reponse) {

                // Annule la requête précédente si l'utilisateur tape vite
                clearTimeout(timer);

                // Affiche le spinner de chargement
                $(champId).siblings('.spinner-adresse').show();

                // Attend 300ms après la dernière frappe avant d'envoyer la requête
                // (évite de spammer l'API à chaque lettre)
                timer = setTimeout(function () {

                    $.ajax({
                        // URL de l'API Adresse du gouvernement français
                        // q = texte tapé par l'utilisateur
                        // limit = nombre max de résultats (8 suggestions)
                        // countrycodes=fr = uniquement la France
                        url: 'https://api-adresse.data.gouv.fr/search/',
                        data: {
                            q: requete.term,
                            limit: 8,
                            countrycodes: 'fr'
                        },
                        dataType: 'json',

                        success: function (data) {
                            // Cache le spinner
                            $(champId).siblings('.spinner-adresse').hide();

                            // L'API renvoie un GeoJSON → on extrait les features
                            // Chaque feature = une adresse avec ses propriétés
                            var suggestions = $.map(data.features, function (item) {
                                return {
                                    // Ce qui s'affiche dans la liste
                                    label: item.properties.label,
                                    // Ce qui remplit le champ adresse (numéro + rue)
                                    value: item.properties.name,
                                    // Les données qui remplissent automatiquement les autres champs
                                    cp:    item.properties.postcode,
                                    ville: item.properties.city,
                                    pays:  'France'
                                };
                            });

                            reponse(suggestions);
                        },

                        error: function () {
                            // En cas d'erreur réseau → cache le spinner silencieusement
                            $(champId).siblings('.spinner-adresse').hide();
                            reponse([]);
                        }
                    });

                }, 300); // délai debounce : 300ms
            },

            // Déclenche à partir de 3 caractères (évite les résultats trop larges)
            minLength: 3,

            // ✅ Quand l'utilisateur clique sur une suggestion
            select: function (event, ui) {
                // Remplit le champ adresse avec le numéro + nom de rue
                $(champId).val(ui.item.value);
                // Remplit automatiquement le code postal
                $(cpId).val(ui.item.cp);
                // Remplit automatiquement la ville
                $(villeId).val(ui.item.ville);
                // Remplit automatiquement le pays (France)
                if (paysId) $(paysId).val(ui.item.pays);

                return false; // Empêche jQuery UI de remettre le label complet
            },

            // Empêche le label complet de s'afficher dans le champ au survol
            focus: function () { return false; }
        });
    }

    // ----------------------------------------------------------------
    // INITIALISATION SUR LES DEUX FORMULAIRES
    // ----------------------------------------------------------------

    // Formulaire AJOUT (bouton "Ajouter une adresse")
    initAutocompleteAPI('#add-adresse', '#add-cp', '#add-ville', '#add-pays');

    // Formulaire MODIFICATION (affiché quand ?edit_addr=X dans l'URL)
    initAutocompleteAPI('#edit-adresse', '#edit-cp', '#edit-ville', '#edit-pays');

});
</script>

<?php include '../includes/footer.php'; // Footer HTML commun (bas de page) ?>