<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Query;

/**
 * Résolution du périmètre de visibilité du module de suivi.
 *
 * Règle unique, appliquée côté serveur partout :
 *  - administrateur : `null` = aucune restriction (toutes les structures) ;
 *  - responsable « manager » d'un centre : occurrences du centre + des
 *    bacentas de ce centre (héritage §17, comme le reste de l'app) ;
 *  - responsable « manager » d'un bacenta : occurrences du bacenta uniquement ;
 *  - tout autre utilisateur : aucune donnée de suivi nominative (sauf sa
 *    propre fiche et ses propres occurrences — « Mes échéances »).
 */
class RapportScopeService
{
    /**
     * Paires de périmètre autorisées, ou `null` (global) / `[]` (aucun).
     * @param ?array<string,mixed> $user
     * @return ?array<int,array{type:string,id:int}>
     */
    public function pairsForUser(?array $user): ?array
    {
        if (!$user) {
            return [];
        }
        if (($user['role'] ?? '') === 'admin') {
            return null;
        }
        $uid = (int) $user['id'];
        $pairs = [];

        $centreIds = array_map('intval', array_column(
            Query::all("SELECT target_id FROM responsibilities WHERE user_id = ? AND target_type = 'center' AND responsibility_type = 'manager'", [$uid]),
            'target_id'
        ));
        foreach ($centreIds as $centreId) {
            $pairs[] = ['type' => 'centre', 'id' => $centreId];
            $bacentaIds = array_map('intval', array_column(
                Query::all('SELECT id FROM bacentas WHERE centre_id = ?', [$centreId]),
                'id'
            ));
            foreach ($bacentaIds as $bacentaId) {
                $pairs[] = ['type' => 'bacenta', 'id' => $bacentaId];
            }
        }

        $bacentaIds = array_map('intval', array_column(
            Query::all("SELECT target_id FROM responsibilities WHERE user_id = ? AND target_type = 'bacenta' AND responsibility_type = 'manager'", [$uid]),
            'target_id'
        ));
        foreach ($bacentaIds as $bacentaId) {
            $pairs[] = ['type' => 'bacenta', 'id' => $bacentaId];
        }

        // Dédoublonnage stable.
        $seen = [];
        $unique = [];
        foreach ($pairs as $pair) {
            $key = $pair['type'] . '#' . $pair['id'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $pair;
            }
        }
        return $unique;
    }

    /** L'utilisateur a-t-il un périmètre de gestion (admin ou manager d'au moins une cible) ? */
    public function hasManagerScope(?array $user): bool
    {
        $pairs = $this->pairsForUser($user);
        return $pairs === null || $pairs !== [];
    }

    /**
     * L'utilisateur peut-il consulter la fiche de suivi de $targetUserId ?
     *  - soi-même : oui (ses propres indicateurs) ;
     *  - administrateur : oui ;
     *  - manager : oui si les deux utilisateurs partagent au moins une
     *    occurrence attendue dans le périmètre du manager.
     */
    public function canViewResponsible(?array $user, int $targetUserId): bool
    {
        if (!$user || $targetUserId <= 0) {
            return false;
        }
        $uid = (int) $user['id'];
        if ($uid === $targetUserId || ($user['role'] ?? '') === 'admin') {
            return true;
        }
        $pairs = $this->pairsForUser($user);
        if ($pairs === null || $pairs === []) {
            return false;
        }
        $params = [$targetUserId];
        $scope = (new \App\Repositories\RapportOccurrenceRepository())->scopeWhere($pairs, $params, 'o');
        $count = (int) Query::value(
            "SELECT COUNT(DISTINCT o.id)
               FROM rapport_occurrences o
               JOIN rapport_occurrence_responsables r ON r.occurrence_id = o.id AND r.user_id = ?
              WHERE 1 = 1 $scope",
            $params
        );
        return $count > 0;
    }

    /**
     * L'utilisateur peut-il agir sur cette occurrence précisément ?
     * Admin : oui. Sinon : occurrence dont la paire (type, id) est incluse
     * dans son périmètre de gestion.
     */
    public function isInScopeForOccurrence(?array $user, int $occurrenceId): bool
    {
        if (!$user || $occurrenceId <= 0) {
            return false;
        }
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        $occ = (new \App\Repositories\RapportOccurrenceRepository())->find($occurrenceId);
        if (!$occ) {
            return false;
        }
        $pairs = $this->pairsForUser($user);
        if ($pairs === null) {
            return true;
        }
        foreach ($pairs as $pair) {
            if ($pair['type'] === $occ['scope_type'] && (int) $pair['id'] === (int) $occ['scope_id']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Les utilisateurs évaluables dans un périmètre (pour le classement) :
     * responsables « manager » d'au moins une cible du périmètre.
     * @return array<int>
     */
    public function responsibleUserIdsForScope(?array $pairs, string $from, string $to): array
    {
        $params = [];
        $scope = (new \App\Repositories\RapportOccurrenceRepository())->scopeWhere($pairs, $params, 'o');
        $rows = Query::all(
            "SELECT DISTINCT r.user_id
               FROM rapport_occurrence_responsables r
               JOIN rapport_occurrences o ON o.id = r.occurrence_id
              WHERE o.report_date BETWEEN ? AND ? $scope AND o.status <> 'cancelled'",
            array_merge([$from, $to], $params)
        );
        return array_map(static fn($r) => (int) $r['user_id'], $rows);
    }

    /** Date effective de prise de responsabilité (MIN(responsibilities.created_at)) ou null. */
    public function responsibilityStartFor(int $userId): ?string
    {
        $value = Query::value(
            "SELECT MIN(created_at)
               FROM responsibilities
              WHERE user_id = ? AND responsibility_type = 'manager' AND target_type IN ('center', 'bacenta')",
            [$userId]
        );
        $value = is_string($value) && $value !== '' ? $value : null;
        return $value !== null ? substr($value, 0, 10) : null;
    }
}
