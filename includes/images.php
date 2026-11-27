<?php
// ============================================================
// includes/images.php : gestion des images produits
// - save_image_upload() : upload controle (type, taille, contenu)
// - product_image_url() : URL affichable depuis pages/admin/
// ============================================================

const IMG_MAX_OCTETS = 3 * 1024 * 1024; // 3 Mo

if (!function_exists('save_image_upload')) {
    function save_image_upload(array $fichier, string $dossier): string
    {
        if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            if (in_array($fichier['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new RuntimeException("L'image est trop lourde (3 Mo maximum).");
            }
            throw new RuntimeException("L'envoi de l'image a echoue. Reessaie.");
        }

        if (!is_uploaded_file($fichier['tmp_name'])) {
            throw new RuntimeException("Fichier invalide.");
        }
        if ($fichier['size'] > IMG_MAX_OCTETS) {
            throw new RuntimeException("L'image est trop lourde (3 Mo maximum).");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($fichier['tmp_name']);
        $autorises = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($autorises[$mime])) {
            throw new RuntimeException("Format non autorise : utilise JPG, PNG ou WebP.");
        }

        if (@getimagesize($fichier['tmp_name']) === false) {
            throw new RuntimeException("Le fichier n'est pas une image valide.");
        }

        if (!is_dir($dossier) && !mkdir($dossier, 0755, true)) {
            throw new RuntimeException("Impossible de creer le dossier des images.");
        }

        $nom = 'produit-' . bin2hex(random_bytes(6)) . '.' . $autorises[$mime];
        $destination = rtrim($dossier, '/\\') . DIRECTORY_SEPARATOR . $nom;

        if (!move_uploaded_file($fichier['tmp_name'], $destination)) {
            throw new RuntimeException("Impossible d'enregistrer l'image.");
        }

        return $nom;
    }
}

// Transforme la valeur stockee en base (image_url) en URL
// utilisable dans une page de pages/admin/.
if (!function_exists('product_image_url')) {
    function product_image_url($imageUrl): string
    {
        $imageUrl = trim((string)$imageUrl);

        if ($imageUrl === '') {
            return '../../assets/image/parf.jpg';
        }
        if (preg_match('#^(https?:)?//#i', $imageUrl)) {
            return $imageUrl;
        }

        $nom = basename(str_replace('\\', '/', $imageUrl));
        return '../../assets/image/' . rawurlencode($nom);
    }
}