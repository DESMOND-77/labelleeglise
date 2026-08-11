<?php
/* Page complète du centre de notifications. Variable : $notifications (array). */
$notifications = $notifications ?? [];
$unread = 0;
foreach ($notifications as $n) {
    if (!(int) $n['is_read']) {
        $unread++;
    }
}

$items = '';
foreach ($notifications as $n) {
    $isUnread = !(int) $n['is_read'];
    $icon = NOTIFICATION_TYPE_ICONS[$n['type']] ?? '<i class="fa-solid fa-bell"></i>';
    $when = !empty($n['created_at']) ? date('d/m/Y à H:i', strtotime($n['created_at'])) : '';
    $openUrl = url('index.php', ['action' => 'notification_open', 'id' => $n['id']]);
    $items .= '<a class="notif-row' . ($isUnread ? ' unread' : '') . '" href="' . h($openUrl) . '">'
        . '<span class="notif-row-icon">' . $icon . '</span>'
        . '<span class="notif-row-body"><strong>' . h($n['title']) . '</strong><span>' . h($n['message']) . '</span><time>' . h($when) . '</time></span>'
        . ($isUnread ? '<span class="notif-row-dot"></span>' : '')
        . '</a>';
}
$items = $items ?: empty_state('fa-bell-slash', 'Aucune notification pour le moment.');
?>
<?= section_toolbar(
    'Notifications',
    count($notifications) . ' notification(s), ' . $unread . ' non lue(s)',
    $unread ? '<a class="btn btn-outline btn-sm" href="' . h(url('index.php', ['action' => 'notification_mark_all_read'])) . '">Tout marquer comme lu</a>' : ''
) ?>
<div class="notif-list-page"><?= $items ?></div>
