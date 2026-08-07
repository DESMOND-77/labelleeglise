<?php

namespace App\Repositories;

use App\Core\Query;

/**
 * Accès aux données des centres.
 */
class CentreRepository
{
    /** Tous les centres avec le nombre de bacentas. */
    public function all(): array
    {
        return Query::all(
            "SELECT c.*, (SELECT COUNT(*) FROM bacentas b WHERE b.centre_id = c.id) AS nb_bacentas
               FROM centres c ORDER BY c.id"
        );
    }

    public function find(int $id): ?array
    {
        return Query::one('SELECT * FROM centres WHERE id = ?', [$id]);
    }

    public function create(string $nom): int
    {
        return Query::run('INSERT INTO centres (nom) VALUES (?)', [$nom]);
    }

    public function update(int $id, string $nom): void
    {
        Query::run('UPDATE centres SET nom = ? WHERE id = ?', [$nom, $id]);
    }

    public function delete(int $id): void
    {
        Query::run('DELETE FROM presences WHERE centre_id = ?', [$id]);
        Query::run('DELETE FROM offrandes WHERE centre_id = ?', [$id]);
        Query::run('DELETE FROM bacentas WHERE centre_id = ?', [$id]);
        Query::run('DELETE FROM centres_presentation WHERE centre_id = ?', [$id]);
        Query::run('DELETE FROM centres WHERE id = ?', [$id]);
    }
}
