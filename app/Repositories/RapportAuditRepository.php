<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Query;

/** Journal d'audit du module (barèmes, calendriers, annulations, relances manuelles, cron). */
class RapportAuditRepository
{
    public function log(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null, ?int $actorUserId = null): void
    {
        Query::run(
            'INSERT INTO rapport_audit_logs (actor_user_id, action, entity_type, entity_id, details)
             VALUES (?, ?, ?, ?, ?)',
            [$actorUserId, mb_substr($action, 0, 60), $entityType !== null ? mb_substr($entityType, 0, 40) : null, $entityId, $details !== null ? mb_substr($details, 0, 2000) : null]
        );
    }

    public function recent(int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        return Query::all(
            "SELECT a.*, u.prenom, u.nom
               FROM rapport_audit_logs a
               LEFT JOIN users u ON u.id = a.actor_user_id
              ORDER BY a.id DESC
              LIMIT $limit"
        );
    }
}
