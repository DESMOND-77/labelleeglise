<?php
/**
 * Configuration générale de l'application.
 */

declare(strict_types=1);

return [
    'name'          => 'La Belle Église',
    'url'           => '',            // vide = chemins relatifs
    'timezone'      => 'Africa/Libreville',
    'charset'       => 'UTF-8',
    'debug'         => false,
    'session_name'  => 'LBEGF_SESSID',
    'upload_dir'    => 'uploads',     // relatif à la racine web
    'max_upload'    => 4 * 1024 * 1024, // 4 Mo
];
