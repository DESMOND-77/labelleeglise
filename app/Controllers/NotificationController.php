<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\NotificationService;

/**
 * Centre de notifications plateforme (page complète — voir aussi le
 * dropdown de la topbar, alimenté directement depuis le layout via les
 * wrappers app/Compat/notifications.php).
 */
class NotificationController extends Controller
{
    private NotificationService $notifications;

    public function __construct(?NotificationService $notifications = null)
    {
        $this->notifications = $notifications ?? new NotificationService();
    }

    /** GET ?page=notifications */
    public function index(): void
    {
        $user = (new AuthMiddleware())->handle();

        $items = $this->notifications->allFor((int) $user['id']);

        $content = view('pages/notifications', ['notifications' => $items]);
        render_page(SECTION_LABELS['notifications'], $content);
    }
}
