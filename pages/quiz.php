<?php
// ============================================================
// FICHIER : pages/quiz.php
// RÔLE    : Page du quiz olfactif (introduction visuelle + une question par écran).
//           Les réponses sont envoyées en GET à quiz-resultats.php, qui les
//           valide côté serveur et calcule les recommandations.
//           Lecture seule : cette page ne lit en base que le nom et l'image de
//           quelques parfums réels, pour illustrer le quiz.
// ============================================================

// --- Ordre d'inclusion imposé par header.php : config -> db -> functions -> header ---
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/quiz_scoring.php';

// ------------------------------------------------------------
// Questions du quiz. Les identifiants (clés) DOIVENT être ceux du moteur
// (quiz_familles_config, quiz_ambiances_config, ...). Une option dont la clé
// n'existe pas dans le moteur n'est jamais affichée.
// ------------------------------------------------------------
$questions = [
    'famille' => [
        'court'   => 'Famille olfactive',
        'titre'   => 'Quelle famille olfactive vous attire ?',
        'aide'    => 'Choisissez celle qui vous parle le plus.',
        'valides' => array_map('strval', array_keys(quiz_familles_config())),
        'options' => [
            'florale'              => ['Florale',                 'Rose, jasmin, pivoine',          'fa-spa'],
            'boisee'               => ['Boisée',                  'Cèdre, vétiver, bois chauds',    'fa-tree'],
            'fruitee_gourmande'    => ['Fruitée et gourmande',    'Fruits, notes sucrées',          'fa-apple-whole'],
            'hesperidee'           => ['Hespéridée',              'Agrumes, fraîcheur vive',        'fa-lemon'],
            'orientale'            => ['Orientale',               'Ambre, épices, vanille',         'fa-moon'],
            'aquatique_aromatique' => ['Aquatique et aromatique', 'Notes marines, herbes, lavande', 'fa-water'],
        ],
    ],
    'ambiance' => [
        'court'   => 'Ambiance',
        'titre'   => 'Quelle ambiance souhaitez-vous dégager ?',
        'aide'    => 'Pensez à l\'effet que vous voulez créer.',
        'valides' => array_map('strval', array_keys(quiz_ambiances_config())),
        'options' => [
            'elegante'  => ['Élégante',  'Raffinée et intemporelle', 'fa-gem'],
            'douce'     => ['Douce',     'Tendre et réconfortante',  'fa-feather'],
            'fraiche'   => ['Fraîche',   'Légère et énergisante',    'fa-wind'],
            'sensuelle' => ['Sensuelle', 'Chaude et envoûtante',     'fa-fire'],
            'intense'   => ['Intense',   'Marquante et affirmée',    'fa-bolt'],
        ],
    ],
    'intensite' => [
        'court'   => 'Intensité',
        'titre'   => 'Quelle intensité préférez-vous ?',
        'aide'    => 'Selon la concentration du parfum.',
        'valides' => array_map('strval', array_keys(quiz_intensites_config())),
        'options' => [
            'discrete' => ['Discrète', 'Une présence légère',  'fa-circle'],
            'moderee'  => ['Modérée',  'Un sillage équilibré', 'fa-circle-half-stroke'],
            'intense'  => ['Intense',  'Une présence affirmée', 'fa-circle-dot'],
        ],
    ],
    'budget' => [
        'court'   => 'Budget',
        'titre'   => 'Quel est votre budget ?',
        'aide'    => 'Prix du format, promotion déduite.',
        'valides' => array_map('strval', array_keys(quiz_budgets_config())),
        'options' => [
            '50'       => ['Jusqu\'à 50 €',  'Un petit format accessible', 'fa-euro-sign'],
            '100'      => ['Jusqu\'à 100 €', 'Le bon équilibre',           'fa-euro-sign'],
            '150'      => ['Jusqu\'à 150 €', 'Un parfum d\'exception',     'fa-euro-sign'],
            'illimite' => ['Sans limite',    'Le meilleur pour moi',       'fa-crown'],
        ],
    ],
];

// ------------------------------------------------------------
// Visuels : photos RÉELLES de parfums de la boutique (table produits).
// Aucun paramètre utilisateur dans la requête. Si la base est indisponible ou
// si un parfum est en rupture, la page s'affiche quand même, sans ces photos.
// ------------------------------------------------------------
$visuels = [];
try {
    $stmt = $pdo->prepare(
        "SELECT id_produit, nom, image_url
         FROM produits
         WHERE id_produit IN (3, 5, 6, 15) AND stock > 0
           AND image_url IS NOT NULL AND image_url <> ''"
    );
    $stmt->execute();
    $parId = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $parId[(int)$p['id_produit']] = $p;
    }
    foreach ([6, 3, 5, 15] as $id) {          // ordre d'affichage voulu
        if (isset($parId[$id])) { $visuels[] = $parId[$id]; }
    }
} catch (PDOException $e) {
    $visuels = [];                            // pas de photos : on n'invente rien
}
$nbVisuels = count($visuels);

$pageTitle = 'Quiz olfactif';
include '../includes/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(SITE_URL); ?>assets/css/quiz.css">

<section class="quiz" id="quiz">

    <!-- ================= INTRODUCTION ================= -->
    <div class="quiz-intro" id="quizIntro">
        <div class="quiz-hero<?php echo $nbVisuels ? '' : ' quiz-hero-noimg'; ?>">

            <div class="quiz-hero-text">
                <p class="quiz-eyebrow">Quiz olfactif</p>
                <h1 class="quiz-title">Trouvez le parfum qui vous ressemble</h1>
                <span class="quiz-rule" aria-hidden="true"></span>
                <p class="quiz-lead">
                    Quatre questions, moins d'une minute. Nous comparons vos goûts aux parfums
                    réellement disponibles chez Lumoura et vous proposons jusqu'à trois recommandations.
                </p>
                <button type="button" class="quiz-btn quiz-btn-primary" id="quizStart">Commencer le quiz</button>

                <ul class="quiz-points">
                    <li><i class="fas fa-check" aria-hidden="true"></i> Sans inscription</li>
                    <li><i class="fas fa-check" aria-hidden="true"></i> Parfums réellement en stock</li>
                    <li><i class="fas fa-check" aria-hidden="true"></i> Prix et formats exacts</li>
                </ul>
            </div>

            <?php if ($nbVisuels): ?>
                <div class="quiz-hero-visual" aria-hidden="false">
                    <?php foreach (array_slice($visuels, 0, 3) as $i => $v): ?>
                        <figure class="quiz-shot quiz-shot-<?php echo $i + 1; ?>">
                            <img src="<?php echo htmlspecialchars($v['image_url']); ?>"
                                 alt="<?php echo htmlspecialchars($v['nom']); ?>"
                                 loading="eager">
                            <figcaption><?php echo htmlspecialchars($v['nom']); ?></figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================= QUESTIONNAIRE ================= -->
    <form class="quiz-form" id="quizForm" action="quiz-resultats.php" method="get" novalidate>
        <div class="quiz-stage<?php echo $nbVisuels ? '' : ' quiz-stage-noimg'; ?>">

            <?php if ($nbVisuels): ?>
                <!-- Photo d'ambiance : change à chaque question (affichée seulement avec JavaScript) -->
                <aside class="quiz-visual" aria-hidden="true">
                    <?php for ($i = 0; $i < count($questions); $i++):
                        $v = $visuels[$i % $nbVisuels]; ?>
                        <div class="quiz-visual-layer" data-layer="<?php echo $i; ?>"
                             style="background-image:url('<?php echo htmlspecialchars($v['image_url'], ENT_QUOTES); ?>')">
                            <span class="quiz-visual-caption"><?php echo htmlspecialchars($v['nom']); ?></span>
                        </div>
                    <?php endfor; ?>
                </aside>
            <?php endif; ?>

            <div class="quiz-panel">
                <div class="quiz-progress" id="quizProgress" aria-live="polite">
                    <div class="quiz-progress-label">
                        Question <span id="quizCurrent">1</span> sur <?php echo count($questions); ?>
                        <span class="quiz-progress-name">· <span id="quizName"></span></span>
                    </div>
                    <div class="quiz-progress-track" role="progressbar" aria-valuemin="0"
                         aria-valuemax="<?php echo count($questions); ?>" aria-valuenow="1" id="quizBar">
                        <div class="quiz-progress-fill" id="quizFill"></div>
                    </div>
                </div>

                <?php $n = 0; foreach ($questions as $champ => $q): $n++; ?>
                    <fieldset class="quiz-step" data-step="<?php echo $n; ?>"
                              data-champ="<?php echo htmlspecialchars($champ); ?>"
                              data-label="<?php echo htmlspecialchars($q['court']); ?>">
                        <legend class="quiz-question"><?php echo htmlspecialchars($q['titre']); ?></legend>
                        <p class="quiz-help"><?php echo htmlspecialchars($q['aide']); ?></p>

                        <div class="quiz-options quiz-options-<?php echo count($q['options']); ?>">
                            <?php foreach ($q['options'] as $cle => $o):
                                $cle = (string)$cle;
                                if (!in_array($cle, $q['valides'], true)) { continue; } // clé inconnue du moteur : on n'affiche pas
                                $id = 'q_' . $champ . '_' . $cle; ?>
                                <div class="quiz-option">
                                    <input type="radio" class="quiz-radio"
                                           name="<?php echo htmlspecialchars($champ); ?>"
                                           id="<?php echo htmlspecialchars($id); ?>"
                                           value="<?php echo htmlspecialchars($cle); ?>">
                                    <label class="quiz-card" for="<?php echo htmlspecialchars($id); ?>">
                                        <span class="quiz-card-iconwrap">
                                            <i class="fas <?php echo htmlspecialchars($o[2]); ?> quiz-card-icon" aria-hidden="true"></i>
                                        </span>
                                        <span class="quiz-card-name"><?php echo htmlspecialchars($o[0]); ?></span>
                                        <span class="quiz-card-desc"><?php echo htmlspecialchars($o[1]); ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>

                <p class="quiz-error" id="quizError" role="alert" hidden>Veuillez choisir une réponse pour continuer.</p>

                <div class="quiz-nav" id="quizNav">
                    <button type="button" class="quiz-btn quiz-btn-ghost" id="quizPrev">Précédent</button>
                    <button type="button" class="quiz-btn quiz-btn-primary" id="quizNext">Suivant</button>
                    <button type="submit" class="quiz-btn quiz-btn-primary" id="quizSubmit">Découvrir mes parfums</button>
                </div>

                <noscript>
                    <p class="quiz-noscript">Toutes les questions sont affichées ci-dessus : répondez-y puis validez.</p>
                    <button type="submit" class="quiz-btn quiz-btn-primary">Découvrir mes parfums</button>
                </noscript>
            </div>
        </div>
    </form>
</section>

<script>
(function () {
    var root   = document.getElementById('quiz');
    var form   = document.getElementById('quizForm');
    var intro  = document.getElementById('quizIntro');
    var steps  = Array.prototype.slice.call(form.querySelectorAll('.quiz-step'));
    var layers = Array.prototype.slice.call(form.querySelectorAll('.quiz-visual-layer'));
    var total  = steps.length;
    var current = 0;                       // index de l'étape affichée (0 = première question)

    var btnPrev = document.getElementById('quizPrev');
    var btnNext = document.getElementById('quizNext');
    var btnSub  = document.getElementById('quizSubmit');
    var errBox  = document.getElementById('quizError');
    var fill    = document.getElementById('quizFill');
    var bar     = document.getElementById('quizBar');
    var label   = document.getElementById('quizCurrent');
    var nameBox = document.getElementById('quizName');

    root.classList.add('quiz-js');         // active l'affichage « une question par écran »

    function answered(step) {
        return !!step.querySelector('input[type="radio"]:checked');
    }

    function show(index) {
        current = index;
        steps.forEach(function (s, i) { s.classList.toggle('is-active', i === index); });
        layers.forEach(function (l, i) { l.classList.toggle('is-active', i === index); });
        label.textContent = index + 1;
        nameBox.textContent = steps[index].getAttribute('data-label');
        fill.style.width = ((index + 1) / total * 100) + '%';
        bar.setAttribute('aria-valuenow', index + 1);
        btnPrev.hidden = (index === 0);
        btnNext.hidden = (index === total - 1);
        btnSub.hidden  = (index !== total - 1);
        errBox.hidden  = true;
        var first = steps[index].querySelector('input[type="radio"]:checked') ||
                    steps[index].querySelector('input[type="radio"]');
        if (first) { first.focus({ preventScroll: true }); }
    }

    document.getElementById('quizStart').addEventListener('click', function () {
        intro.hidden = true;
        form.classList.add('is-open');
        show(0);
        root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    btnNext.addEventListener('click', function () {
        if (!answered(steps[current])) { errBox.hidden = false; return; }
        show(current + 1);
    });

    btnPrev.addEventListener('click', function () {
        if (current > 0) { show(current - 1); }
    });

    // Effacer le message d'erreur dès qu'une réponse est choisie
    form.addEventListener('change', function () { errBox.hidden = true; });

    // Validation à l'envoi : toutes les questions doivent être répondues
    // (la vraie validation est refaite côté serveur dans quiz-resultats.php)
    form.addEventListener('submit', function (e) {
        for (var i = 0; i < total; i++) {
            if (!answered(steps[i])) {
                e.preventDefault();
                show(i);
                errBox.hidden = false;
                return;
            }
        }
    });
})();
</script>
<script src="<?php echo htmlspecialchars(SITE_URL); ?>assets/js/quiz-premium.js" defer></script>

<?php include '../includes/footer.php'; ?>