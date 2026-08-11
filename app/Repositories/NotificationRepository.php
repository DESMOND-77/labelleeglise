<?php

namespace App\Repositories;

use App\Core\Query;

/**
 * Accès aux données du centre de notifications plateforme.
 */
class NotificationRepository
{
    public function create(int $recipientId, string $type, string $title, string $message, ?string $link = null): int
    {
        return Query::run(
            'INSERT INTO notifications (recipient_id, type, title, message, link, is_read) VALUES (?, ?, ?, ?, ?, 0)',
            [$recipientId, $type, $title, $message, $link]
        );
    }

    public function find(int $id): ?array
    {
        return Query::one('SELECT * FROM notifications WHERE id = ?', [$id]);
    }

    /** Notifications les plus récentes d'un destinataire (dropdown + page). */
    public function forRecipient(int $recipientId, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        return Query::all(
            "SELECT * FROM notifications WHERE recipient_id = ? ORDER BY created_at DESC, id DESC LIMIT $limit",
            [$recipientId]
        );
    }

    public function unreadCount(int $recipientId): int
    {
        return (int) Query::value('SELECT COUNT(*) FROM notifications WHERE recipient_id = ? AND is_read = 0', [$recipientId]);
    }

    /** Marque une notification comme lue (uniquement si elle appartient au destinataire). */
    public function markRead(int $id, int $recipientId): void
    {
        Query::run('UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_id = ?', [$id, $recipientId]);
    }

    public function markAllRead(int $recipientId): void
    {
        Query::run('UPDATE notifications SET is_read = 1 WHERE recipient_id = ? AND is_read = 0', [$recipientId]);
    }
}
