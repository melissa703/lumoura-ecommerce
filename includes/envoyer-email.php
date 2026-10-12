<?php
// ============================================================
// FICHIER : includes/envoyer-email.php
// RÔLE    : Fonction utilitaire pour envoyer un email via
//           PHPMailer + Gmail SMTP
// ============================================================

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ============================================================
// CONFIGURATION
// ✅ CORRECTION SÉCURITÉ : les identifiants ne sont plus écrits en dur
// dans le code. Ils sont chargés depuis un fichier .env (placé à la
// racine du projet, EXCLU de Git via .gitignore).
// ============================================================
if (!function_exists('chargerEnv')) {
    function chargerEnv(string $cheminEnv): void {
        if (!file_exists($cheminEnv)) {
            return;
        }
        $lignes = file($cheminEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lignes as $ligne) {
            $ligne = trim($ligne);
            if ($ligne === '' || str_starts_with($ligne, '#')) {
                continue; // ignore lignes vides et commentaires
            }
            [$cle, $valeur] = array_pad(explode('=', $ligne, 2), 2, '');
            $cle    = trim($cle);
            $valeur = trim($valeur, " \t\n\r\0\x0B\"'");
            if ($cle !== '' && getenv($cle) === false) {
                putenv("$cle=$valeur");
            }
        }
    }
}

chargerEnv(__DIR__ . '/../.env');

define('GMAIL_USER',         getenv('GMAIL_USER')         ?: '');
define('GMAIL_APP_PASSWORD', getenv('GMAIL_APP_PASSWORD') ?: '');
define('MAIL_FROM_NAME',     getenv('MAIL_FROM_NAME')     ?: 'Lumoura Parfums');


// ============================================================
// FONCTION : envoyerCodeVerification()
// ============================================================
function envoyerCodeVerification(string $email, string $prenom, string $code): bool {

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(GMAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($email, $prenom);

        $mail->isHTML(true);
        $mail->Subject = 'Votre code de confirmation Lumoura';

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin: auto;
                    border: 1px solid #eee; border-radius: 16px; overflow: hidden;'>

            <!-- En-tête -->
            <div style='background: linear-gradient(90deg, #ff8c00, #e07b00);
                        padding: 30px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 24px;'>LUMOURA</h1>
                <p style='color: rgba(255,255,255,0.85); margin: 5px 0 0;'>Parfums d'Exception</p>
            </div>

            <!-- Corps -->
            <div style='padding: 35px; background: #fff;'>
                <p style='font-size: 16px; color: #333;'>Bonjour <strong>" . htmlspecialchars($prenom) . "</strong>,</p>
                <p style='color: #555;'>Votre adresse de livraison a bien été enregistrée.<br>
                Veuillez confirmer votre adresse en saisissant le code ci-dessous :</p>

                <!-- Code OTP mis en valeur -->
                <div style='text-align: center; margin: 30px 0;'>
                    <div style='display: inline-block; background: #fff8f0;
                                border: 3px solid #ff8c00; border-radius: 16px;
                                padding: 20px 40px;'>
                        <p style='margin: 0; font-size: 13px; color: #888; letter-spacing: 2px;'>VOTRE CODE</p>
                        <p style='margin: 8px 0 0; font-size: 42px; font-weight: bold;
                                  color: #ff8c00; letter-spacing: 10px;'>" . $code . "</p>
                    </div>
                </div>

                <p style='color: #888; font-size: 13px; text-align: center;'>
                    ⏱️ Ce code est valable <strong>15 minutes</strong>.<br>
                    Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.
                </p>
            </div>

            <!-- Pied de page -->
            <div style='background: #f8f9fa; padding: 20px; text-align: center;'>
                <p style='margin: 0; color: #aaa; font-size: 12px;'>
                    © 2026 Lumoura · 123 Avenue des Champs-Élysées, 75008 Paris
                </p>
            </div>
        </div>";

        $mail->AltBody = "Bonjour $prenom,\n\nVotre code de confirmation Lumoura est : $code\n\nCe code est valable 15 minutes.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Erreur envoi email : " . $mail->ErrorInfo);
        return false;
    }
}


// ============================================================
// FONCTION : genererCodeOTP()
// ============================================================
function genererCodeOTP(): string {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}


// ============================================================
// FONCTION : envoyerEmailBienvenue()
// ============================================================
function envoyerEmailBienvenue(string $email, string $prenom, string $nom): bool {

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(GMAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($email, "$prenom $nom");

        $mail->isHTML(true);
        $mail->Subject = 'Bienvenue chez Lumoura, ' . $prenom . ' !';

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto;
                    border: 1px solid #eee; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);'>

            <!-- En-tête doré -->
            <div style='background: linear-gradient(90deg, #d4a017, #f0c040);
                        padding: 40px 30px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 30px; letter-spacing: 3px;'>LUMOURA</h1>
                <p style='color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px; letter-spacing: 1px;'>Parfums d'Exception</p>
            </div>

            <!-- Corps -->
            <div style='padding: 45px 35px; background: #fff;'>
                <p style='font-size: 22px; color: #3a2c1f; margin: 0 0 10px 0;'>
                    Bienvenue, <strong>" . htmlspecialchars($prenom) . "</strong> !
                </p>
                <p style='color: #666; font-size: 15px; line-height: 1.7; margin-bottom: 30px;'>
                    Votre compte Lumoura a été créé avec succès. Vous faites maintenant partie
                    d'une communauté de passionnés de parfums d'exception.
                </p>

                <!-- Avantages membre -->
                <div style='background: #fffbf0; border: 1px solid #f0d080; border-radius: 12px; padding: 25px; margin-bottom: 30px;'>
                    <p style='font-size: 15px; font-weight: bold; color: #3a2c1f; margin: 0 0 15px 0;'>
                        Vos avantages en tant que membre :
                    </p>
                    <div style='display: flex; align-items: center; margin-bottom: 10px;'>
                        <span style='color: #d4a017; font-size: 18px; margin-right: 12px;'>&#128666;</span>
                        <span style='color: #555; font-size: 14px;'>Livraison gratuite dès 150 €</span>
                    </div>
                    <div style='display: flex; align-items: center; margin-bottom: 10px;'>
                        <span style='color: #d4a017; font-size: 18px; margin-right: 12px;'>&#127873;</span>
                        <span style='color: #555; font-size: 14px;'>Échantillons offerts à chaque commande</span>
                    </div>
                    <div style='display: flex; align-items: center; margin-bottom: 10px;'>
                        <span style='color: #d4a017; font-size: 18px; margin-right: 12px;'>&#128260;</span>
                        <span style='color: #555; font-size: 14px;'>Retour gratuit sous 30 jours</span>
                    </div>
                    <div style='display: flex; align-items: center;'>
                        <span style='color: #d4a017; font-size: 18px; margin-right: 12px;'>&#128142;</span>
                        <span style='color: #555; font-size: 14px;'>Accès aux offres exclusives membres</span>
                    </div>
                </div>

                <!-- Bouton CTA -->
                <div style='text-align: center; margin-bottom: 30px;'>
                    <a href='https://lumoura.fr/pages/catalogue.php'
                       style='display: inline-block; background: linear-gradient(90deg, #d4a017, #f0c040);
                              color: white; text-decoration: none; padding: 16px 40px;
                              border-radius: 50px; font-weight: bold; font-size: 16px;
                              box-shadow: 0 4px 15px rgba(212,160,23,0.4);'>
                        Découvrir nos parfums
                    </a>
                </div>

                <p style='color: #999; font-size: 13px; text-align: center;'>
                    Si vous n'êtes pas à l'origine de cette inscription, ignorez cet email.
                </p>
            </div>

            <!-- Pied de page -->
            <div style='background: #f8f5f0; padding: 20px; text-align: center; border-top: 1px solid #eee;'>
                <p style='margin: 0; color: #aaa; font-size: 12px;'>
                    © 2026 Lumoura · 123 Avenue des Champs-Élysées, 75008 Paris<br>
                    <a href='#' style='color: #d4a017; text-decoration: none;'>Se désabonner</a>
                </p>
            </div>
        </div>";

        $mail->AltBody = "Bienvenue chez Lumoura, $prenom $nom !\n\nVotre compte a été créé avec succès.\n\nDécouvrez nos parfums sur : https://lumoura.fr/pages/catalogue.php\n\n© 2026 Lumoura";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Erreur envoi email bienvenue : " . $mail->ErrorInfo);
        return false;
    }
}


// ============================================================
// FONCTION : envoyerEmailConfirmationCommande()
// ============================================================
function envoyerEmailConfirmationCommande(string $email, string $prenom, array $order, array $order_items, int $order_id): bool {

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(GMAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($email, $prenom);

        $mail->isHTML(true);
        $numero_commande  = $order['numero_commande'] ?? ('CMD-' . str_pad($order_id, 6, '0', STR_PAD_LEFT));
        $date_commande    = isset($order['date_commande']) ? date('d/m/Y à H:i', strtotime($order['date_commande'])) : date('d/m/Y à H:i');
        $sous_total       = $order['montant'] - $order['frais_livraison'];
        $frais_livraison  = $order['frais_livraison'];
        $total            = $order['montant'];
        $adresse_liv      = htmlspecialchars($order['adresse_livraison'] ?? '');

        $modes_paiement   = ['carte' => 'Carte bancaire', 'paypal' => 'PayPal', 'apple_pay' => 'Apple Pay'];
        $mode_paiement    = $modes_paiement[$order['mode_paiement']] ?? 'Carte bancaire';

        $mail->Subject = 'Commande confirmée #' . $numero_commande . ' — Lumoura';

        // Construction de la liste des articles en HTML
        $articles_html = '';
        foreach ($order_items as $item) {
            $item_total     = $item['prix_unitaire'] * $item['quantite'];
            $nom_produit    = htmlspecialchars($item['nom']);
            $marque         = htmlspecialchars($item['marque'] ?? '');
            $quantite       = $item['quantite'];
            $prix_formate   = number_format($item_total, 2, ',', ' ') . ' €';

            $articles_html .= "
            <tr>
                <td style='padding: 12px 10px; border-bottom: 1px solid #f0e8d0; color: #3a2c1f;'>
                    <strong>$nom_produit</strong><br>
                    <span style='color: #888; font-size: 12px;'>$marque</span>
                </td>
                <td style='padding: 12px 10px; border-bottom: 1px solid #f0e8d0; text-align: center; color: #555;'>$quantite</td>
                <td style='padding: 12px 10px; border-bottom: 1px solid #f0e8d0; text-align: right; font-weight: bold; color: #d4a017;'>$prix_formate</td>
            </tr>";
        }

        $livraison_affiche = $frais_livraison == 0 ? 'GRATUIT' : number_format($frais_livraison, 2, ',', ' ') . ' €';
        $sous_total_fmt    = number_format($sous_total,  2, ',', ' ') . ' €';
        $total_fmt         = number_format($total,       2, ',', ' ') . ' €';

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 650px; margin: auto;
                    border: 1px solid #eee; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);'>

            <!-- En-tête vert succès -->
            <div style='background: linear-gradient(90deg, #2a9d8f, #27ae60);
                        padding: 35px 30px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 26px;'>Commande confirmée !</h1>
                <p style='color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px;'>
                    Merci pour votre confiance, <strong>" . htmlspecialchars($prenom) . "</strong> !
                </p>
            </div>

            <!-- Numéro de commande -->
            <div style='background: #fffbf0; border-bottom: 2px solid #d4a017; padding: 20px 30px; text-align: center;'>
                <p style='margin: 0; font-size: 13px; color: #888; letter-spacing: 1px;'>NUMÉRO DE COMMANDE</p>
                <p style='margin: 6px 0 0; font-size: 24px; font-weight: bold; color: #3a2c1f;'>$numero_commande</p>
                <p style='margin: 4px 0 0; font-size: 13px; color: #999;'>Passée le $date_commande</p>
            </div>

            <!-- Corps -->
            <div style='padding: 35px 30px; background: #fff;'>

                <!-- Infos clés -->
                <table style='width: 100%; border-collapse: collapse; margin-bottom: 30px;'>
                    <tr>
                        <td style='width: 50%; padding: 14px; background: #f8f5f0; border-radius: 10px 0 0 10px; border-right: 4px solid white;'>
                            <div style='font-size: 11px; color: #999; letter-spacing: 1px; margin-bottom: 4px;'>MODE DE PAIEMENT</div>
                            <div style='font-weight: bold; color: #3a2c1f;'>$mode_paiement</div>
                        </td>
                        <td style='width: 50%; padding: 14px; background: #f8f5f0; border-radius: 0 10px 10px 0;'>
                            <div style='font-size: 11px; color: #999; letter-spacing: 1px; margin-bottom: 4px;'>ADRESSE DE LIVRAISON</div>
                            <div style='font-weight: bold; color: #3a2c1f; font-size: 13px;'>$adresse_liv</div>
                        </td>
                    </tr>
                </table>

                <!-- Articles commandés -->
                <h3 style='color: #3a2c1f; margin: 0 0 15px 0; font-size: 16px;
                           border-bottom: 2px solid #d4a017; padding-bottom: 10px;'>
                    Articles commandés
                </h3>
                <table style='width: 100%; border-collapse: collapse; margin-bottom: 25px;'>
                    <thead>
                        <tr style='background: #f8f5f0;'>
                            <th style='padding: 10px; text-align: left; color: #888; font-size: 12px; font-weight: bold;'>PRODUIT</th>
                            <th style='padding: 10px; text-align: center; color: #888; font-size: 12px; font-weight: bold;'>QTÉ</th>
                            <th style='padding: 10px; text-align: right; color: #888; font-size: 12px; font-weight: bold;'>PRIX</th>
                        </tr>
                    </thead>
                    <tbody>
                        $articles_html
                    </tbody>
                </table>

                <!-- Récapitulatif montants -->
                <div style='background: #f8f5f0; border-radius: 12px; padding: 20px; margin-bottom: 30px;'>
                    <div style='display: flex; justify-content: space-between; margin-bottom: 10px; color: #555; font-size: 14px;'>
                        <span>Sous-total</span><span>$sous_total_fmt</span>
                    </div>
                    <div style='display: flex; justify-content: space-between; margin-bottom: 10px; color: #555; font-size: 14px;'>
                        <span>Livraison</span>
                        <span style='color: " . ($frais_livraison == 0 ? '#2a9d8f' : '#555') . ";'>$livraison_affiche</span>
                    </div>
                    <div style='display: flex; justify-content: space-between; border-top: 2px solid #d4a017;
                                padding-top: 12px; margin-top: 5px; font-size: 18px; font-weight: bold;'>
                        <span style='color: #3a2c1f;'>Total payé</span>
                        <span style='color: #c0392b;'>$total_fmt</span>
                    </div>
                </div>

                <!-- Prochaines étapes -->
                <div style='background: #e6f7ff; border-left: 4px solid #2a9d8f; border-radius: 10px; padding: 20px; margin-bottom: 30px;'>
                    <p style='font-weight: bold; color: #2a9d8f; margin: 0 0 12px 0; font-size: 15px;'>
                        Prochaines étapes
                    </p>
                    <p style='color: #555; font-size: 13px; margin: 0 0 8px 0;'>Votre commande est enregistrée et en cours de traitement.</p>
                    <p style='color: #555; font-size: 13px; margin: 0 0 8px 0;'>Vous recevrez un email dès l'expédition de votre colis.</p>
                    <p style='color: #555; font-size: 13px; margin: 0;'>Livraison estimée sous <strong>2 à 4 jours ouvrés</strong>.</p>
                </div>

            </div>

            <!-- Pied de page -->
            <div style='background: #f8f5f0; padding: 20px; text-align: center; border-top: 1px solid #eee;'>
                <p style='margin: 0; color: #aaa; font-size: 12px;'>
                    © 2026 Lumoura · 123 Avenue des Champs-Élysées, 75008 Paris<br>
                    Une question ? <a href='mailto:contact@lumoura.fr' style='color: #d4a017; text-decoration: none;'>contact@lumoura.fr</a>
                </p>
            </div>
        </div>";

        $mail->AltBody = "Commande confirmée ! Numéro : $numero_commande\nDate : $date_commande\nTotal : $total_fmt\n\nMerci pour votre achat chez Lumoura.\n\n© 2026 Lumoura";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Erreur envoi email commande : " . $mail->ErrorInfo);
        return false;
    }
}


// ============================================================
// FONCTION : envoyerEmailStatut()
// ============================================================
// Envoie un email au client quand l'admin change le statut
// d'une commande vers : expediee, livree ou annulee
//
// Paramètres :
//   $email            → adresse email du client
//   $prenom           → prénom du client
//   $numero_commande  → numéro de commande ex: CMD-2026-ABC123
//   $nouveau_statut   → 'expediee', 'livree' ou 'annulee'
//
// Retourne :
//   true  → email envoyé avec succès
//   false → statut non notifiable ou erreur
// ============================================================
function envoyerEmailStatut(string $email, string $prenom, string $numero_commande, string $nouveau_statut): bool {

    // Contenu personnalisé selon le statut
    $infos_statut = [
        'expediee' => [
            'sujet'   => 'Votre commande ' . $numero_commande . ' est expédiée !',
            'titre'   => 'Votre commande est en route !',
            'couleur' => '#9b59b6',
            'message' => 'Votre commande <strong>' . htmlspecialchars($numero_commande) . '</strong> a été expédiée et est en cours de livraison.',
            'detail'  => 'Vous recevrez votre colis dans <strong>1 à 2 jours ouvrés</strong>.',
        ],
        'livree' => [
            'sujet'   => 'Votre commande ' . $numero_commande . ' a été livrée !',
            'titre'   => 'Commande livrée avec succès !',
            'couleur' => '#27ae60',
            'message' => 'Votre commande <strong>' . htmlspecialchars($numero_commande) . '</strong> a bien été livrée.',
            'detail'  => 'Nous espérons que vos parfums vous plairont. N\'hésitez pas à laisser un avis sur notre site !',
        ],
        'annulee' => [
            'sujet'   => 'Votre commande ' . $numero_commande . ' a été annulée',
            'titre'   => 'Commande annulée',
            'couleur' => '#e74c3c',
            'message' => 'Votre commande <strong>' . htmlspecialchars($numero_commande) . '</strong> a été annulée par notre équipe.',
            'detail'  => 'Si vous avez été débité, un remboursement sera effectué sous <strong>3 à 5 jours ouvrés</strong>. Pour toute question : <a href="mailto:contact@lumoura.fr" style="color:#d4a017;">contact@lumoura.fr</a>',
        ],
    ];

    // Si le statut n'est pas notifiable → on n'envoie pas d'email
    if (!isset($infos_statut[$nouveau_statut])) {
        return false;
    }

    $info = $infos_statut[$nouveau_statut];
    $site = defined('SITE_URL') ? SITE_URL : '';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(GMAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($email, $prenom);
        $mail->addReplyTo('contact@lumoura.fr', 'Lumoura');

        $mail->isHTML(true);
        $mail->Subject = $info['sujet'];

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto;
                    border: 1px solid #eee; border-radius: 16px; overflow: hidden;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.08);'>

            <!-- En-tête coloré selon le statut -->
            <div style='background: " . $info['couleur'] . "; padding: 35px 30px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 22px;'>" . $info['titre'] . "</h1>
                <p style='color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px;'>
                    Lumoura — Parfums d'Exception
                </p>
            </div>

            <!-- Corps -->
            <div style='padding: 35px 30px; background: #fff;'>
                <p style='font-size: 16px; color: #333; margin-bottom: 20px;'>
                    Bonjour <strong>" . htmlspecialchars($prenom) . "</strong>,
                </p>
                <p style='color: #555; line-height: 1.7; margin-bottom: 15px;'>
                    " . $info['message'] . "
                </p>
                <p style='color: #555; line-height: 1.7; margin-bottom: 30px;'>
                    " . $info['detail'] . "
                </p>

                <!-- Numéro de commande mis en avant -->
                <div style='background: #fdf8f0; border-left: 4px solid #d4a017;
                            border-radius: 0 10px 10px 0; padding: 16px 20px; margin-bottom: 30px;'>
                    <div style='font-size: 12px; color: #888; margin-bottom: 4px;'>NUMÉRO DE COMMANDE</div>
                    <div style='font-size: 18px; font-weight: bold; color: #d4a017;'>
                        " . htmlspecialchars($numero_commande) . "
                    </div>
                </div>

                <!-- Bouton -->
                <div style='text-align: center; margin-bottom: 20px;'>
                    <a href='" . $site . "pages/compte.php'
                       style='display: inline-block; background: " . $info['couleur'] . ";
                              color: white; text-decoration: none; padding: 14px 35px;
                              border-radius: 30px; font-weight: bold; font-size: 15px;'>
                        Voir mes commandes
                    </a>
                </div>
            </div>

            <!-- Pied de page -->
            <div style='background: #f8f5f0; padding: 20px; text-align: center; border-top: 1px solid #eee;'>
                <p style='margin: 0; color: #aaa; font-size: 12px;'>
                    © 2026 Lumoura · 123 Avenue des Champs-Élysées, 75008 Paris<br>
                    <a href='mailto:contact@lumoura.fr' style='color: #d4a017; text-decoration: none;'>contact@lumoura.fr</a>
                </p>
            </div>
        </div>";

        $mail->AltBody = "Bonjour $prenom,\n\n" . strip_tags($info['message']) . "\n" . strip_tags($info['detail']) . "\n\nCommande : $numero_commande\n\n© 2026 Lumoura";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Erreur envoi email statut : " . $mail->ErrorInfo);
        return false;
    }
}