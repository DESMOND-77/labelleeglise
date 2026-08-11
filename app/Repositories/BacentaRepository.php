<?php

namespace App\Repositories;

use App\Core\Query;
use App\Core\Database;

/**
 * Accès aux données des bacentas.
 */
class BacentaRepository
{
    /** Bacentas (optionnellement filtrés par centre) avec compte de membres. */
    public function all(?int $centreId = null): array
    {
        $base = "SELECT b.*, c.nom AS centre_nom,
                        (SELECT COUNT(*) FROM users u WHERE u.bacenta_id = b.id) AS nb_membres
                   FROM bacentas b LEFT JOIN centres c ON c.id = b.centre_id";
        if ($centreId) {
            return Query::all($base . ' WHERE b.centre_id = ? ORDER BY b.id', [$centreId]);
        }
        return Query::all($base . ' ORDER BY b.id');
    }

    public function find(int $id): ?array
    {
        return Query::one(
            "SELECT b.*, c.nom AS centre_nom,
                    (SELECT COUNT(*) FROM users u WHERE u.bacenta_id = b.id) AS nb_membres
               FROM bacentas b LEFT JOIN centres c ON c.id = b.centre_id
              WHERE b.id = ?",
            [$id]
        );
    }

    public function nameMap(): array
    {
        $map = [];
        foreach (Query::all('SELECT id, nom FROM bacentas') as $b) {
            $map[(int) $b['id']] = $b['nom'];
        }
        return $map;
    }

    /**
     * $respId est accepté pour compatibilité de signature mais IGNORÉ :
     * responsable_id est désormais une colonne dénormalisée synchronisée
     * automatiquement par ResponsibilityService à partir de la table
     * `responsibilities` (source de vérité) — jamais écrite directement ici.
     */
    public function create(string $nom, ?int $centreId, ?int $respId = null): int
    {
        return Query::run('INSERT INTO bacentas (nom, centre_id) VALUES (?, ?)', [$nom, $centreId]);
    }

    public function update(int $id, string $nom, ?int $centreId, ?int $respId = null): void
    {
        Query::run('UPDATE bacentas SET nom = ?, centre_id = ? WHERE id = ?', [$nom, $centreId, $id]);
    }

    public function delete(int $id): void
    {
        // Intégrité (spec §38) : une responsabilité ne référence jamais une
        // structure supprimée — `responsibilities` n'a pas de FK sur
        // target_id (polymorphe), le nettoyage est donc explicite ici.
        Query::run("DELETE FROM responsibilities WHERE target_type = 'bacenta' AND target_id = ?", [$id]);
        Query::run('UPDATE users SET bacenta_id = NULL WHERE bacenta_id = ?', [$id]);
        Query::run('DELETE FROM visites WHERE bacenta_id = ?', [$id]);
        Query::run('DELETE FROM presences WHERE bacenta_id = ?', [$id]);
        Query::run('DELETE FROM offrandes WHERE bacenta_id = ?', [$id]);
        Query::run('DELETE FROM bacentas WHERE id = ?', [$id]);
    }

    /** Bacentas dont un utilisateur est responsable. */
    public function forResponsible(int $userId): array
    {
        $rows = Query::all('SELECT id FROM bacentas WHERE responsable_id = ?', [$userId]);
        return array_map(fn($r) => (int) $r['id'], $rows);
    }

    /** Premier bacenta d'un centre (rattachement automatique). */
    public function firstIdOfCentre(int $centreId): ?int
    {
        $id = Query::value('SELECT id FROM bacentas WHERE centre_id = ? ORDER BY id LIMIT 1', [$centreId]);
        return $id ? (int) $id : null;
    }

    /** Affecte un membre (déjà revalidé) à un bacenta. */
    public function assignMember(int $userId, int $bacentaId): void
    {
        Query::run('UPDATE users SET bacenta_id = ? WHERE id = ?', [$bacentaId, $userId]);
    }

    /** Table basontas/cultes par type (responsable). */
    public function entityForResponsible(string $type, array $scope): array
    {
        $table = $type . 's';
        return Query::all("SELECT id FROM $table WHERE id = ? AND responsable_id = ?", [$scope['id'], $scope['userId']]);
    }
}
