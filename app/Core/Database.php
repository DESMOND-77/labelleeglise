<?php

namespace App\Core;

use PDO;

/**
 * Connexion PDO unique (singleton) - MySQL / MariaDB.
 * Configuration depuis Config/database.php.
 */
class Database
{
    private static ?PDO $pdo = null;

    /** Retourne l'instance PDO unique. */
    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = require APP_CONFIG_PATH . '/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        self::$pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Aligne la collation de la connexion sur celle des tables. Sans cela elle reprend le
        // défaut du jeu de caractères (utf8mb4_general_ci) alors que les tables sont en
        // utf8mb4_unicode_ci : deux valeurs « coercibles » de collations différentes ne se
        // comparent plus (erreur SQL 1267). Une collation d'un autre jeu de caractères est ignorée.
        $collation = (string) ($config['collation'] ?? '');
        if (preg_match('/^[a-z0-9_]+$/i', $collation) === 1 && str_starts_with($collation, $config['charset'] . '_')) {
            self::$pdo->exec(sprintf('SET NAMES %s COLLATE %s', $config['charset'], $collation));
        }

        return self::$pdo;
    }

    /** Réinitialise la connexion (utile en CLI / tests). */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
