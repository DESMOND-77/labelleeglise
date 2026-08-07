<?php

namespace App\Core;

use PDO;

/**
 * Micro-couche d'exécution de requêtes préparées.
 * Reprend les fonctions qall / qone / qval / qexec (autrefois dans db.php).
 */
class Query
{
    /** Exécute une requête préparée et retourne toutes les lignes. */
    public static function all(string $sql, array $params = []): array
    {
        $st = Database::connection()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Exécute une requête préparée et retourne la première ligne (ou null). */
    public static function one(string $sql, array $params = []): ?array
    {
        $st = Database::connection()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    /** Exécute une requête préparée et retourne une valeur scalaire. */
    public static function value(string $sql, array $params = [])
    {
        $st = Database::connection()->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn();
    }

/** Exécute une requête d'écriture et retourne le lastInsertId. */
    public static function run(string $sql, array $params = []): int
    {
        $pdo = Database::connection();
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int) $pdo->lastInsertId();
    }

    /** Exécute une requête sans préparation (DDL, TRUNCATE…). */
    public static function raw(string $sql): void
    {
        Database::connection()->exec($sql);
    }
}
