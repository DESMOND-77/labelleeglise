<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Query;

/**
 * Journal d'envoi des rappels + idempotence.
 * Unicité : (occurrence, destinataire, type, canal) + clé d'idempotence.
 */
class RapportReminderRepository
{
    /**
     * Réclame le droit d'envoyer un rappel (idempotence).
     *
     * Stratégie : INSERT IGNORE d'abord (une seule exécution « gagne » la
     * ligne) ; sinon, une ligne existante en `pending` peut être
     * re-tentée après `$retryMinutes` (bug/crash ou erreur temporaire),
     * tant que `attempt_count` reste sous `$maxAttempts`.
     *
     * @return array{id:int, action:string}|null  'send' (à tenter) ou null (déjà fait / non retryable)
     */
    public function claimOrFetch(int $occurrenceId, int $recipientUserId, string $type, string $channel, string $idempotencyKey, int $maxAttempts, int $retryMinutes): ?array
    {
        $pdo = Database::connection();
        $st = $pdo->prepare(
            "INSERT IGNORE INTO rapport_reminder_logs
                (occurrence_id, recipient_user_id, reminder_type, channel, status, idempotency_key, attempt_count)
             VALUES (?, ?, ?, ?, 'pending', ?, 0)"
        );
        $st->execute([$occurrenceId, $recipientUserId, $type, $channel, $idempotencyKey]);
        if ($st->rowCount() === 1) {
            return ['id' => (int) $pdo->lastInsertId(), 'action' => 'send'];
        }

        $retryMinutes = max(1, $retryMinutes);
        $maxAttempts = max(1, $maxAttempts);
        $row = Query::one(
            "SELECT id, status, attempt_count, updated_at
               FROM rapport_reminder_logs
              WHERE occurrence_id = ? AND recipient_user_id = ? AND reminder_type = ? AND channel = ?
              ORDER BY id DESC
              LIMIT 1",
            [$occurrenceId, $recipientUserId, $type, $channel]
        );
        if (!$row || $row['status'] !== 'pending' || (int) $row['attempt_count'] >= $maxAttempts) {
            return null;
        }
        $updated = strtotime((string) $row['updated_at']);
        if ($updated !== false && $updated > time() - $retryMinutes * 60) {
            return null;
        }
        return ['id' => (int) $row['id'], 'action' => 'retry'];
    }

    public function markSent(int $id): void
    {
        Query::run(
            "UPDATE rapport_reminder_logs SET status = 'sent', sent_at = NOW(), last_error = NULL WHERE id = ?",
            [$id]
        );
    }

    /** Échec : incrémente le compteur de tentatives ; bascule en 'failed' au-delà de $maxAttempts (jamais de boucle infinie). */
    public function markFailed(int $id, string $error, int $maxAttempts): void
    {
        $error = mb_substr(trim($error), 0, 255);
        Query::run(
            "UPDATE rapport_reminder_logs
                SET attempt_count = attempt_count + 1,
                    last_error = ?,
                    status = IF(attempt_count + 1 >= ?, 'failed', 'pending')
              WHERE id = ?",
            [$error, max(1, $maxAttempts), $id]
        );
    }

    public function markSkipped(int $id, string $reason): void
    {
        Query::run(
            "UPDATE rapport_reminder_logs SET status = 'skipped', last_error = ? WHERE id = ?",
            [mb_substr(trim($reason), 0, 255), $id]
        );
    }

    /** Clôture les rappels non envoyés d'une occurrence (rapport enregistré / occurrence annulée). */
    public function cancelPendingForOccurrence(int $occurrenceId): int
    {
        $pdo = Database::connection();
        $st = $pdo->prepare("UPDATE rapport_reminder_logs SET status = 'cancelled' WHERE occurrence_id = ? AND status = 'pending'");
        $st->execute([$occurrenceId]);
        return $st->rowCount();
    }

    /** Occurrences ayant un rappel en échec (monitoring/admin). */
    public function failedRecent(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return Query::all(
            "SELECT l.*, o.scope_type, o.scope_id, o.report_date
               FROM rapport_reminder_logs l
               JOIN rapport_occurrences o ON o.id = l.occurrence_id
              WHERE l.status = 'failed'
              ORDER BY l.id DESC
              LIMIT $limit"
        );
    }

    /** Nombre de rappels en échec (indicateur tableau de bord admin). */
    public function failedCount(): int
    {
        return (int) Query::value("SELECT COUNT(*) FROM rapport_reminder_logs WHERE status = 'failed'", []);
    }
}
