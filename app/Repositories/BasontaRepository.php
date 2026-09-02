<?php

namespace App\Repositories;

use App\Core\Query;

/**
 * Accès aux données des basontas (ministères).
 */
class BasontaRepository
{
    public function all(): array
    {
        return Query::all(
            "SELECT b.*, (SELECT COUNT(*) FROM users_basontas ub WHERE ub.basonta_id = b.id) AS nb_membres
               FROM basontas b ORDER BY b.id"
        );
    }

    public function find(int $id): ?array
    {
        return Query::one(
            "SELECT b.*, (SELECT COUNT(*) FROM users_basontas ub WHERE ub.basonta_id = b.id) AS nb_membres
               FROM basontas b WHERE b.id = ?",
            [$id]
        );
    }

    /** $respId accepté pour compatibilité mais IGNORÉ — voir BacentaRepository::create(). */
    public function create(string $nom, ?int $respId = null, ?string $jours = null, ?string $debut = null, ?string $fin = null): int
    {
        return Query::run(
            'INSERT INTO basontas (nom, jours_semaine, heure_debut, heure_fin) VALUES (?, ?, ?, ?)',
            [$nom, $jours, $debut, $fin]
        );
    }

    public function update(int $id, string $nom, ?int $respId = null, ?string $jours = null, ?string $debut = null, ?string $fin = null): void
    {
        Query::run(
            'UPDATE basontas SET nom = ?, jours_semaine = ?, heure_debut = ?, heure_fin = ? WHERE id = ?',
            [$nom, $jours, $debut, $fin, $id]
        );
    }

    public function delete(int $id): void
    {
        Query::run("DELETE FROM responsibilities WHERE target_type = 'basonta' AND target_id = ?", [$id]);
        Query::run('DELETE FROM users_basontas WHERE basonta_id = ?', [$id]);
        Query::run('DELETE FROM basontas WHERE id = ?', [$id]);
    }

    public function addMember(int $basontaId, int $userId): void
    {
        $exists = Query::value('SELECT COUNT(*) FROM users_basontas WHERE user_id = ? AND basonta_id = ?', [$userId, $basontaId]);
        if (!$exists) {
            Query::run('INSERT INTO users_basontas (user_id, basonta_id) VALUES (?, ?)', [$userId, $basontaId]);
        }
    }

    public function removeMember(int $basontaId, int $userId): void
    {
        Query::run('DELETE FROM users_basontas WHERE user_id = ? AND basonta_id = ?', [$userId, $basontaId]);
    }

    public function memberIds(int $basontaId): array
    {
        $rows = Query::all('SELECT user_id FROM users_basontas WHERE basonta_id = ?', [$basontaId]);
        return array_map(fn($r) => (int) $r['user_id'], $rows);
    }

    /** Dernier basonta d'un membre (pour présence rapide). */
    public function latestOfUser(int $userId): ?int
    {
        $id = Query::value('SELECT basonta_id FROM users_basontas WHERE user_id = ? ORDER BY basonta_id LIMIT 1', [$userId]);
        return ((int) ($id ?: 0)) ?: null;
    }
}
