<?php

namespace App\Core;

/**
 * Gestion centralisée des téléversements de fichiers.
 */
class Upload
{
    /**
     * Enregistre une photo uploadée et retourne son chemin web relatif
     * (ex. "uploads/photo_xxx.jpg"), ou null si aucun fichier valide.
     */
    public static function photo(string $inputName): ?string
    {
        if (empty($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $f = $_FILES[$inputName];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        if ($f['size'] > MAX_PHOTO_BYTES) {
            return null;
        }
        $info = @getimagesize($f['tmp_name']);
        if ($info === false) {
            return null;
        }
        $extMap = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        $ext = $extMap[$info[2]] ?? 'jpg';

        if (!is_dir(UPLOAD_DIR)) {
            @mkdir(UPLOAD_DIR, 0775, true);
        }
        $name = 'photo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $name)) {
            return null;
        }
        return 'uploads/' . $name;
    }

    /**
     * Variante indexée pour les champs de fichier en tableau
     * (`name="affiche[3]"`) : retourne le chemin web relatif de
     * la photo uploadée à l'index $index, ou null si aucun
     * fichier valide n'a été fourni pour cet index.
     */
    public static function photoAt(string $inputName, int|string $index): ?string
    {
        if (empty($_FILES[$inputName]) || !isset($_FILES[$inputName]['name'][$index])) {
            return null;
        }
        $f = [
            'name'     => $_FILES[$inputName]['name'][$index],
            'type'     => $_FILES[$inputName]['type'][$index],
            'tmp_name' => $_FILES[$inputName]['tmp_name'][$index],
            'error'    => $_FILES[$inputName]['error'][$index],
            'size'     => $_FILES[$inputName]['size'][$index],
        ];
        if ($f['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > MAX_PHOTO_BYTES) {
            return null;
        }
        $info = @getimagesize($f['tmp_name']);
        if ($info === false) {
            return null;
        }
        $extMap = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        $ext = $extMap[$info[2]] ?? 'jpg';

        if (!is_dir(UPLOAD_DIR)) {
            @mkdir(UPLOAD_DIR, 0775, true);
        }
        $name = 'photo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $name)) {
            return null;
        }
        return 'uploads/' . $name;
    }

    /** Supprime un fichier photo (chemin relatif "uploads/..."). */
    public static function deletePhoto(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            $full = UPLOAD_DIR . '/' . substr($path, strlen('uploads/'));
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }
}
