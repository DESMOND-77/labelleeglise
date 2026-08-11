<?php

/**
 * Compatibilité — centre de notifications & administration des inscriptions.
 * Expose des fonctions globales pour les vues, à l'image des autres
 * fichiers app/Compat/*.php (délègue aux services applicatifs).
 */

declare(strict_types=1);

use App\Services\NotificationService;
use App\Services\RegistrationService;
use App\Services\BacentaMembershipService;

function notification_service(): NotificationService
{
    return _repo(NotificationService::class);
}

function registration_service(): RegistrationService
{
    return _repo(RegistrationService::class);
}

function bacenta_membership_service(): BacentaMembershipService
{
    return _repo(BacentaMembershipService::class);
}

/* ---------- Notifications ---------- */

function unread_notifications_count(int $userId): int
{
    return notification_service()->unreadCount($userId);
}

function recent_notifications(int $userId, int $limit = 8): array
{
    return notification_service()->recentFor($userId, $limit);
}

function all_notifications(int $userId): array
{
    return notification_service()->allFor($userId);
}

/* ---------- Administration des inscriptions ---------- */

function get_pending_registrations(): array
{
    return registration_service()->listPendingRegistrations();
}

function get_registration(int $id): ?array
{
    return registration_service()->getRegistration($id);
}
