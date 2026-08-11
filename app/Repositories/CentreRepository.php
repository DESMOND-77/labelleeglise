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
        // Intégrité (spec §38) : nettoie les responsabilités de ce centre
        // ET des bacentas qu'il contient (supprimées en cascade ci-dessous) —
        // `responsibilities` n'a pas de FK sur target_id (polymorphe).
        $bacentaIds = Query::all('SELECT id FROM bacentas WHERE centre_id = ?', [$id]);
        foreach ($bacentaIds as $b) {
            Query::run("DELETE FROM responsibilities WHERE target_type = 'bacenta' AND target_id = ?", [(int) $b['id']]);
        }
        Query::run("DELETE FROM responsibilities WHERE target_type = 'center' AND target_id = ?", [$id]);
        Query::run('DELETE FROM presences WHERE centre_id = ?', [$id]);
        Query::run('DELETE FROM offrandes WHERE centre_id = ?', [$id]);
        Query::run('DELETE FROM bacentas WHERE centre_id = ?', [$id]);
        Query::run('DELETE FROM centres_presentation WHERE centre_id = ?', [$id]);
        Query::run('DELETE FROM centres WHERE id = ?', [$id]);
    }
}
