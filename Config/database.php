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
    'host'    => env_value('DB_HOST', '127.0.0.1'),
    'port'    => (int) env_value('DB_PORT', 3306),
    'name'    => env_value('DB_NAME', 'la_belle_eglise_db'),
    'user'    => env_value('DB_USER', 'root'),
    'pass'    => env_value('DB_PASS', ''),
    'charset' => env_value('DB_CHARSET', 'utf8mb4'),
];
