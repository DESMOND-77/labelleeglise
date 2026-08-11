<?php

namespace App\Repositories;

use App\Core\Query;

/**
 * Accès aux données de la table polymorphe `responsibilities`.
 *
 * user_id × responsibility_type × target_type × target_id.
 * `target_type` ∈ 'center' | 'bacenta' | 'cult' (extensible sans migration —
 * voir Database/Migrations/2024_01_01_000000_create_schema.php).
 */
class ResponsibilityRepository
{
    private const DEFAULT_TYPE = 'manager';

    /** Crée l'affectation si elle n'existe pas déjà (contrainte UNIQUE). */
    public function assign(int $userId, string $targetType, int $targetId, string $responsibilityType = self::DEFAULT_TYPE): int
    {
        $existing = Query::one(
            'SELECT id FROM responsibilities WHERE user_id = ? AND responsibility_type = ? AND target_type = ? AND target_id = ?',
            [$userId, $responsibilityType, $targetType, $targetId]
        );
        if ($existing) {
            return (int) $existing['id'];
        }
        return Query::run(
            'INSERT INTO responsibilities (user_id, responsibility_type, target_type, target_id) VALUES (?, ?, ?, ?)',
            [$userId, $responsibilityType, $targetType, $targetId]
        );
    }

    public function revoke(int $userId, string $targetType, int $targetId, string $responsibilityType = self::DEFAULT_TYPE): void
    {
        Query::run(
            'DELETE FROM responsibilities WHERE user_id = ? AND responsibility_type = ? AND target_type = ? AND target_id = ?',
            [$userId, $responsibilityType, $targetType, $targetId]
        );
    }

    public function revokeById(int $id): void
    {
        Query::run('DELETE FROM responsibilities WHERE id = ?', [$id]);
    }

    /** Révoque TOUTES les responsabilités d'un utilisateur (ex : changement de rôle incompatible). */
    public function revokeAllForUser(int $userId, ?string $targetType = null): int
    {
        if ($targetType !== null) {
            $rows = Query::all('SELECT id FROM responsibilities WHERE user_id = ? AND target_type = ?', [$userId, $targetType]);
            Query::run('DELETE FROM responsibilities WHERE user_id = ? AND target_type = ?', [$userId, $targetType]);
        } else {
            $rows = Query::all('SELECT id FROM responsibilities WHERE user_id = ?', [$userId]);
            Query::run('DELETE FROM responsibilities WHERE user_id = ?', [$userId]);
        }
        return count($rows);
    }

    /** Toutes les responsabilités d'un utilisateur (avec libellé de la cible si dispo). */
    public function listForUser(int $userId): array
    {
        return Query::all(
            'SELECT * FROM responsibilities WHERE user_id = ? ORDER BY target_type, target_id',
            [$userId]
        );
    }

    /** Tous les responsables d'une cible donnée. */
    public function listForTarget(string $targetType, int $targetId): array
    {
        return Query::all(
            'SELECT r.*, u.prenom, u.nom, u.email, u.role
               FROM responsibilities r JOIN users u ON u.id = r.user_id
              WHERE r.target_type = ? AND r.target_id = ?
              ORDER BY u.prenom, u.nom',
            [$targetType, $targetId]
        );
    }

    public function isResponsibleFor(int $userId, string $targetType, int $targetId): bool
    {
        return (bool) Query::value(
            'SELECT COUNT(*) FROM responsibilities WHERE user_id = ? AND target_type = ? AND target_id = ?',
            [$userId, $targetType, $targetId]
        );
    }

    /** IDs des cibles d'un type donné dont l'utilisateur est responsable. */
    public function targetIdsForUser(int $userId, string $targetType): array
    {
        $rows = Query::all(
            'SELECT target_id FROM responsibilities WHERE user_id = ? AND target_type = ?',
            [$userId, $targetType]
        );
        return array_map(fn($r) => (int) $r['target_id'], $rows);
    }

    /** Une seule ligne (pour vérifier existence avant revoke ciblé depuis l'UI, par id). */
    public function find(int $id): ?array
    {
        return Query::one('SELECT * FROM responsibilities WHERE id = ?', [$id]);
    }
}
