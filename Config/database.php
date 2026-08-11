<?php
/**
 * Configuration de la base de données.
 * -------------------------------------------------------------
 * Base `la_belle_eglise_db` — MySQL / MariaDB.
 * Les identifiants sont lus depuis les variables d'environnement
 * (fichier `.env` à la racine en local, ou vraies variables d'environnement
 * du serveur en production) — jamais codés en dur ici. Voir `.env.example`.
 */

declare(strict_types=1);

return [
    'host'    => env_value('DB_HOST', 'sql303.infinityfree.com'),
    'port'    => (int) env_value('DB_PORT', 3306),
    'name'    => env_value('DB_NAME', 'if0_40779107_la_belle_eglise_db'),
    'user'    => env_value('DB_USER', 'if0_40779107'),
    'pass'    => env_value('DB_PASS', 'sgrh7IU3io'),
    'charset' => env_value('DB_CHARSET', 'utf8mb4'),
];
