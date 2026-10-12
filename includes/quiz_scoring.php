<?php
// ============================================================
// FICHIER : includes/quiz_scoring.php
// RÔLE    : Moteur de recommandation du quiz olfactif (SANS interface).
//           - lit les parfums réels (produits + produit_formats)
//           - compare les réponses du client aux caractéristiques
//           - renvoie un classement transparent (score détaillé par critère)
//
// BASE DE DONNÉES : lecture seule (2 SELECT préparés). Aucune écriture,
//                   aucune modification de structure.
//
// RÈGLES DE PRIX (identiques au reste du site) :
//   - le prix d'un parfum dépend du FORMAT (produit_formats.prix) ;
//   - la promotion produits.promotion_pourcentage s'applique à tous les formats :
//     prix final = prix_format * (1 - promo / 100)   (comme catalogue.php / panier.php)
//   - « Dès X € » = format le moins cher, après promotion.
//
// SCORE SUR 100 :
//   famille olfactive  40  (exacte = 40, famille voisine = 15)
//   ambiance           20
//   intensité          15  (déduite de la concentration)
//   budget             15  (un format rentre dans le budget)
//   occasion           10  (réservé : question non incluse dans cette version)
// => dans cette version (sans question « occasion ») le maximum réel est 90 ;
//    le champ « affinite » ramène le score sur 100 pour l'affichage.
// ============================================================

// ------------------------------------------------------------
// 1. PARAMÈTRES (tout est modifiable ici, rien n'est « caché »)
// ------------------------------------------------------------

const QUIZ_POINTS_FAMILLE_EXACTE  = 40;
const QUIZ_POINTS_FAMILLE_VOISINE = 15;
const QUIZ_POINTS_AMBIANCE        = 20;
const QUIZ_POINTS_BUDGET          = 15;
// Score "profil" minimum (famille + ambiance + intensité, budget exclu).
// « Pertinent » exige en plus une correspondance de famille (exacte ou voisine).
const QUIZ_SEUIL_PERTINENT   = 35;  // en dessous : pas une vraie correspondance
const QUIZ_SEUIL_ALTERNATIVE = 15;  // en dessous : on ne propose rien du tout
const QUIZ_NB_RESULTATS      = 3;
// Score maximal atteignable aujourd'hui : 40 + 20 + 15 + 15 (l'occasion, 10 pts, viendra plus tard)
const QUIZ_SCORE_MAX         = 90;

/**
 * Réponse « famille » du quiz → valeurs de produits.famille_olfactive.
 * 'exactes' = 40 pts, 'voisines' = 15 pts.
 * (« Gourmande » n'existe pas dans la base actuelle : la carte du quiz s'appelle
 *  « Fruitée et gourmande » et vise Fruitée ; Gourmande est listée pour plus tard.)
 */
function quiz_familles_config(): array
{
    return [
        'florale'              => ['exactes' => ['Florale'],                  'voisines' => ['Fruitée']],
        'boisee'               => ['exactes' => ['Boisée'],                   'voisines' => ['Cuir', 'Aromatique']],
        'fruitee_gourmande'    => ['exactes' => ['Fruitée', 'Gourmande'],     'voisines' => ['Orientale', 'Florale']],
        'hesperidee'           => ['exactes' => ['Hespéridée'],               'voisines' => ['Aquatique', 'Aromatique']],
        'orientale'            => ['exactes' => ['Orientale'],                'voisines' => ['Cuir', 'Boisée']],
        'aquatique_aromatique' => ['exactes' => ['Aquatique', 'Aromatique'],  'voisines' => ['Hespéridée']],
    ];
}

/** Réponse « ambiance » → familles qui lui correspondent (20 pts). */
function quiz_ambiances_config(): array
{
    return [
        'elegante'  => ['Florale', 'Boisée', 'Chyprée'],
        'douce'     => ['Florale', 'Fruitée', 'Gourmande'],
        'fraiche'   => ['Hespéridée', 'Aquatique', 'Aromatique', 'Fougère'],
        'sensuelle' => ['Orientale', 'Cuir', 'Gourmande'],
        'intense'   => ['Orientale', 'Cuir', 'Boisée'],
    ];
}

/**
 * Intensité déduite de produits.concentration (APPROXIMATION assumée :
 * il n'existe pas de colonne « intensité » dans la base).
 */
function quiz_niveau_concentration(?string $concentration): ?float
{
    $niveaux = [
        'Eau Fraiche'     => 1.0,
        'Eau de Cologne'  => 1.0,
        'Eau de Toilette' => 2.0,
        'Eau de Parfum'   => 2.5,
        'Parfum'          => 3.0,
    ];
    return $niveaux[$concentration ?? ''] ?? null;
}

/** Réponse « intensité » → niveau demandé. */
function quiz_intensites_config(): array
{
    return ['discrete' => 1.0, 'moderee' => 2.0, 'intense' => 3.0];
}

/** Réponse « budget » → plafond en euros (null = sans limite). */
function quiz_budgets_config(): array
{
    return ['50' => 50.0, '100' => 100.0, '150' => 150.0, 'illimite' => null];
}

// ------------------------------------------------------------
// 2. VALIDATION CÔTÉ SERVEUR (liste blanche : on ne fait pas confiance au client)
// ------------------------------------------------------------

/**
 * @param array $brut  ex. $_GET ou $_POST
 * @return array ['ok' => bool, 'reponses' => array, 'erreurs' => string[]]
 */
function quiz_valider_reponses(array $brut): array
{
    // array_map('strval', ...) : PHP transforme les clés '50', '100', '150' en ENTIERS ;
    // sans cette conversion, in_array(..., true) refuserait un budget reçu en texte depuis le formulaire.
    $attendus = [
        'famille'   => array_map('strval', array_keys(quiz_familles_config())),
        'ambiance'  => array_map('strval', array_keys(quiz_ambiances_config())),
        'intensite' => array_map('strval', array_keys(quiz_intensites_config())),
        'budget'    => array_map('strval', array_keys(quiz_budgets_config())),
    ];

    $reponses = [];
    $erreurs  = [];
    foreach ($attendus as $champ => $valeursAutorisees) {
        $valeur = $brut[$champ] ?? null;
        // is_string : refuse les tableaux (ex. ?famille[]=x)
        if (!is_string($valeur) || !in_array($valeur, $valeursAutorisees, true)) {
            $erreurs[] = "Réponse manquante ou invalide : $champ";
            continue;
        }
        $reponses[$champ] = $valeur;
    }
    return ['ok' => empty($erreurs), 'reponses' => $reponses, 'erreurs' => $erreurs];
}

// ------------------------------------------------------------
// 3. PRIX (même formule que le reste du site)
// ------------------------------------------------------------

/** Prix après promotion, arrondi comme à l'affichage (2 décimales). */
function quiz_prix_apres_promo(float $prix, float $promoPourcent): float
{
    $final = $promoPourcent > 0 ? $prix * (1 - $promoPourcent / 100) : $prix;
    return round($final, 2);
}

// ------------------------------------------------------------
// 4. LECTURE DES DONNÉES (lecture seule, requêtes préparées)
// ------------------------------------------------------------

/**
 * Charge les parfums en stock avec leurs formats.
 * Colonnes réellement utilisées : voir les 2 SELECT ci-dessous.
 */
function quiz_charger_produits(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        "SELECT id_produit, nom, description_courte, famille_olfactive, concentration,
                genre, promotion_pourcentage, image_url, stock, seuil_alerte, bestseller
         FROM produits
         WHERE stock > 0"
    );
    $stmt->execute();
    $produits = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $p['id_produit']            = (int)$p['id_produit'];
        $p['promotion_pourcentage'] = (float)$p['promotion_pourcentage'];
        $p['stock']                 = (int)$p['stock'];
        $p['seuil_alerte']          = $p['seuil_alerte'] !== null ? (int)$p['seuil_alerte'] : 10;
        $p['bestseller']            = (int)$p['bestseller'];
        $p['formats']               = [];
        $produits[$p['id_produit']] = $p;
    }
    if (!$produits) {
        return [];
    }

    $stmt = $pdo->prepare("SELECT id_format, id_produit, volume_ml, prix FROM produit_formats ORDER BY volume_ml ASC");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $id = (int)$f['id_produit'];
        if (isset($produits[$id])) {
            $produits[$id]['formats'][] = [
                'id_format' => (int)$f['id_format'],
                'volume_ml' => (int)$f['volume_ml'],
                'prix'      => (float)$f['prix'],
            ];
        }
    }
    return array_values($produits);
}

// ------------------------------------------------------------
// 5. SCORING D'UN PRODUIT
// ------------------------------------------------------------

/**
 * Calcule le score détaillé d'UN produit pour des réponses déjà validées.
 * Renvoie null si le produit n'a aucun format (impossible de le vendre/afficher).
 */
function quiz_scorer_produit(array $produit, array $reponses): ?array
{
    if (empty($produit['formats'])) {
        return null;
    }

    $familles  = quiz_familles_config()[$reponses['famille']];
    $famille   = $produit['famille_olfactive'] ?? '';
    $promo     = (float)$produit['promotion_pourcentage'];
    $plafond   = quiz_budgets_config()[$reponses['budget']];

    // --- Famille (40 / 15 / 0) ---
    if (in_array($famille, $familles['exactes'], true)) {
        $ptsFamille = QUIZ_POINTS_FAMILLE_EXACTE;
        $famRel = 'exacte';
    } elseif (in_array($famille, $familles['voisines'], true)) {
        $ptsFamille = QUIZ_POINTS_FAMILLE_VOISINE;
        $famRel = 'voisine';
    } else {
        $ptsFamille = 0;
        $famRel = 'aucune';
    }

    // --- Ambiance (20 / 0) ---
    $ptsAmbiance = in_array($famille, quiz_ambiances_config()[$reponses['ambiance']], true)
        ? QUIZ_POINTS_AMBIANCE : 0;

    // --- Intensité (15 / 10 / 5 / 0) selon l'écart de niveau ---
    $niveauProduit = quiz_niveau_concentration($produit['concentration'] ?? null);
    $ptsIntensite = 0;
    if ($niveauProduit !== null) {
        $ecart = abs($niveauProduit - quiz_intensites_config()[$reponses['intensite']]);
        $ptsIntensite = $ecart <= 0.0 ? 15 : ($ecart <= 0.5 ? 10 : ($ecart <= 1.0 ? 5 : 0));
    }

    // --- Formats : prix après promo, « Dès X € », format recommandé ---
    $formats = [];
    foreach ($produit['formats'] as $f) {
        $f['prix_final'] = quiz_prix_apres_promo($f['prix'], $promo);
        $formats[] = $f;
    }
    usort($formats, fn($a, $b) => $a['prix_final'] <=> $b['prix_final']);
    $moinsCher = $formats[0];                       // = « Dès X € » du catalogue

    // Format recommandé = le PLUS GRAND format qui rentre dans le budget
    $recommande = null;
    foreach ($formats as $f) {
        if ($plafond === null || $f['prix_final'] <= $plafond) {
            if ($recommande === null || $f['volume_ml'] > $recommande['volume_ml']) {
                $recommande = $f;
            }
        }
    }
    $dansBudget = $recommande !== null;
    $ptsBudget  = $dansBudget ? QUIZ_POINTS_BUDGET : 0;

    $scoreProfil = $ptsFamille + $ptsAmbiance + $ptsIntensite;

    // Niveau de correspondance. CORRECTION : sans aucune correspondance de famille
    // (ni exacte ni voisine), un parfum ne peut JAMAIS être « pertinent », même si
    // l'ambiance + l'intensité atteignent le seuil. Il reste « alternative » s'il
    // atteint le seuil alternatif, sinon « aucun ».
    if ($ptsFamille > 0 && $scoreProfil >= QUIZ_SEUIL_PERTINENT) {
        $niveau = 'pertinent';
    } elseif ($scoreProfil >= QUIZ_SEUIL_ALTERNATIVE) {
        $niveau = 'alternative';
    } else {
        $niveau = 'aucun';
    }

    return [
        'id_produit'        => $produit['id_produit'],
        'nom'               => $produit['nom'],
        'description_courte'=> $produit['description_courte'],
        'famille_olfactive' => $famille,
        'concentration'     => $produit['concentration'],
        'image_url'         => $produit['image_url'],
        'promotion'         => $promo,
        'bestseller'        => $produit['bestseller'],
        'dernieres_pieces'  => $produit['stock'] <= $produit['seuil_alerte'],
        'prix_des'          => $moinsCher['prix_final'],
        'format_recommande' => $recommande,          // null si hors budget
        'dans_budget'       => $dansBudget,
        'famille_relation'  => $famRel,
        'details'           => [
            'famille'   => $ptsFamille,
            'ambiance'  => $ptsAmbiance,
            'intensite' => $ptsIntensite,
            'budget'    => $ptsBudget,
        ],
        'score_profil'      => $scoreProfil,
        'score'             => $scoreProfil + $ptsBudget,
        'affinite'          => (int) round(($scoreProfil + $ptsBudget) / QUIZ_SCORE_MAX * 100),
        'niveau'            => $niveau,
    ];
}

/** Tri : score décroissant, puis best-seller, puis prix « Dès » croissant, puis id (ordre stable). */
function quiz_comparer(array $a, array $b): int
{
    return [$b['score'], $b['bestseller'], $a['prix_des'], $a['id_produit']]
       <=> [$a['score'], $a['bestseller'], $b['prix_des'], $b['id_produit']];
}

// ------------------------------------------------------------
// 6. RECOMMANDATION (fonction principale, sans accès base : testable seule)
// ------------------------------------------------------------

/**
 * @param array $reponses  Réponses VALIDÉES (voir quiz_valider_reponses)
 * @param array $produits  Résultat de quiz_charger_produits()
 * @return array {
 *   statut: 'ok' | 'alternatives' | 'hors_budget' | 'aucun',
 *   resultats: [...]          // jusqu'à 3 produits, dans le budget
 *   hors_budget: [...]        // jusqu'à 3 produits pertinents, meilleurs que les résultats, mais trop chers
 *   budget_suggere: ?string   // plus petite tranche qui donnerait un résultat
 *   message: string
 * }
 */
function quiz_recommander(array $reponses, array $produits): array
{
    $scores = [];
    foreach ($produits as $p) {
        $s = quiz_scorer_produit($p, $reponses);
        if ($s !== null && $s['niveau'] !== 'aucun') {
            $scores[] = $s;
        }
    }

    $dansBudget = array_values(array_filter($scores, fn($s) => $s['dans_budget']));
    $horsBudget = array_values(array_filter($scores, fn($s) => !$s['dans_budget']));
    usort($dansBudget, 'quiz_comparer');

    // Pertinents d'abord, puis alternatives (jamais d'alternative devant un pertinent)
    $pertinents   = array_values(array_filter($dansBudget, fn($s) => $s['niveau'] === 'pertinent'));
    $alternatives = array_values(array_filter($dansBudget, fn($s) => $s['niveau'] === 'alternative'));
    $resultats    = array_slice(array_merge($pertinents, $alternatives), 0, QUIZ_NB_RESULTATS);

    $reponse = [
        'statut'         => 'aucun',
        'resultats'      => $resultats,
        'hors_budget'    => [],
        'budget_suggere' => null,
        'message'        => '',
    ];

    // Parfums pertinents MAIS trop chers, qui correspondent MIEUX que le meilleur résultat
    // dans le budget : on les signale (ex. « Oasis, à 52 € pour un budget de 50 € »).
    $meilleurDansBudget = $resultats ? max(array_column($resultats, 'score_profil')) : 0;
    $pertinentsHors = array_values(array_filter(
        $horsBudget,
        fn($s) => $s['niveau'] === 'pertinent' && $s['score_profil'] > $meilleurDansBudget
    ));
    if ($pertinentsHors) {
        usort($pertinentsHors, 'quiz_comparer');
        $reponse['hors_budget']    = array_slice($pertinentsHors, 0, QUIZ_NB_RESULTATS);
        $reponse['budget_suggere'] = quiz_budget_suggere($pertinentsHors, $reponses['budget']);
    }

    if ($pertinents) {
        $reponse['statut']  = 'ok';
        $reponse['message'] = count($resultats) < QUIZ_NB_RESULTATS
            ? "Nous n'avons trouvé que " . count($resultats) . " parfum(s) correspondant à vos goûts."
            : 'Voici les parfums qui vous correspondent le mieux.';
    } elseif ($resultats) {
        $reponse['statut']  = 'alternatives';
        $reponse['message'] = "Aucun parfum ne correspond parfaitement à vos choix : voici les alternatives les plus proches.";
    } elseif ($pertinentsHors) {
        $reponse['statut']  = 'hors_budget';
        $reponse['message'] = "Des parfums correspondent à vos goûts, mais au-delà de votre budget. Souhaitez-vous l'élargir ?";
    } else {
        $reponse['statut']  = 'aucun';
        $reponse['message'] = "Aucun parfum disponible ne correspond à ces critères. Essayez d'élargir votre famille olfactive ou votre ambiance.";
    }
    return $reponse;
}

/**
 * Plus petite tranche de budget supérieure à celle choisie qui donnerait au moins
 * un parfum pertinent (null si aucune ne suffit).
 */
function quiz_budget_suggere(array $pertinentsHors, string $budgetChoisi): ?string
{
    $ordre   = ['50', '100', '150', 'illimite'];
    $plafonds = quiz_budgets_config();
    $debut   = array_search($budgetChoisi, $ordre, true);
    $moinsCherPossible = min(array_column($pertinentsHors, 'prix_des'));

    for ($i = $debut + 1; $i < count($ordre); $i++) {
        $p = $plafonds[$ordre[$i]];
        if ($p === null || $moinsCherPossible <= $p) {
            return $ordre[$i];
        }
    }
    return null;
}