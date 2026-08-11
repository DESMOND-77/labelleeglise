<?php

namespace App\Services;

use App\Repositories\NotificationRepository;
use App\Repositories\UserRepository;

/**
 * Centre de notifications plateforme (bannière/dropdown topbar + page dédiée).
 */
class NotificationService
{
    private NotificationRepository $notifications;
    private UserRepository $users;

    public function __construct(?NotificationRepository $notifications = null, ?UserRepository $users = null)
    {
        $this->notifications = $notifications ?? new NotificationRepository();
        $this->users = $users ?? new UserRepository();
    }

    public function notify(int $recipientId, string $type, string $title, string $message, ?string $link = null): int
    {
        return $this->notifications->create($recipientId, $type, $title, $message, $link);
    }

    /** Notifie tous les comptes disposant du rôle administrateur. */
    public function notifyAdmins(string $type, string $title, string $message, ?string $link = null): array
    {
        $admins = $this->users->findAdmins();
        $ids = [];
        foreach ($admins as $admin) {
            $ids[] = (int) $admin['id'];
            $this->notify((int) $admin['id'], $type, $title, $message, $link);
        }
        return $admins;
    }

    public function unreadCount(int $userId): int
    {
        return $this->notifications->unreadCount($userId);
    }

    public function recentFor(int $userId, int $limit = 8): array
    {
        return $this->notifications->forRecipient($userId, $limit);
    }

    public function allFor(int $userId): array
    {
        return $this->notifications->forRecipient($userId, 100);
    }

    public function markRead(int $id, int $userId): void
    {
        $this->notifications->markRead($id, $userId);
    }

    public function markAllRead(int $userId): void
    {
        $this->notifications->markAllRead($userId);
    }

    public function find(int $id): ?array
    {
        return $this->notifications->find($id);
    }
}
