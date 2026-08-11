<?php

/**
 * Compatibilité — profil libre-service ("Mon profil"), fiche administrative
 * d'un utilisateur (identité/rôle/responsabilités/présences/suivi hebdo) et
 * fiches imprimables (présences, suivi hebdomadaire).
 *
 * RÈGLE ABSOLUE (spec) : jamais de confiance dans un id "membre" venant du
 * navigateur pour une ressource sensible — toute consultation d'une fiche
 * autre que la sienne passe par AuthorizationService::canManageMember()
 * (admin bypass inclus).
 */

declare(strict_types=1);

use App\Services\ProfileService;
use App\Services\EmailChangeService;
use App\Services\AttendanceService;
use App\Services\ExportService;

function profile_service(): ProfileService
{
    static $svc = null;
    $svc = $svc ?? new ProfileService();
    return $svc;
}

function email_change_service(): EmailChangeService
{
    static $svc = null;
    $svc = $svc ?? new EmailChangeService();
    return $svc;
}

function attendance_service(): AttendanceService
{
    static $svc = null;
    $svc = $svc ?? new AttendanceService();
    return $svc;
}

function export_service(): ExportService
{
    static $svc = null;
    $svc = $svc ?? new ExportService();
    return $svc;
}

/**
 * Autorisation commune aux 3 surfaces sensibles (fiche, impressions,
 * export) : soi-même toujours autorisé, sinon canManageMember() (admin
 * bypass déjà inclus dans AuthorizationService).
 */
function can_view_member_profile(int $memberId): bool
{
    $current = current_user();
    if (!$current) {
        return false;
    }
    if ((int) $current['id'] === $memberId) {
        return true;
    }
    return authz_service()->canManageMember($current, $memberId);
}

/** Refus d'accès homogène avec ActionsController::deny() (redirection, pas d'exception fatale). */
function deny_profile_access(): never
{
    redirect('index.php', ['page' => 'apropos']);
}

/* ================= FICHE ADMINISTRATIVE (personProfile) ================= */

function render_profile_page(): void
{
    $current = current_user();
    $membreId = nav('membre') ?: (int) $current['id'];

    if (!can_view_member_profile((int) $membreId)) {
        deny_profile_access();
    }

    $member = get_user((int) $membreId);
    if (!$member) {
        render_page(SECTION_LABELS['personProfile'], empty_state('fa-ban', 'Membre introuvable.'));
        return;
    }

    $bacenta = !empty($member['bacenta_id']) ? get_bacenta((int) $member['bacenta_id']) : null;
    $invite = !empty($member['invite_par']) ? get_user((int) $member['invite_par']) : null;
    $recu = !empty($member['recu_par']) ? get_user((int) $member['recu_par']) : null;

    // Responsabilités réelles (table `responsibilities`), séparées visuellement du rôle (spec §32-33).
    $responsibilities = [];
    foreach (responsibility_service()->listForUser((int) $membreId) as $row) {
        $targetType = $row['target_type'];
        $targetLabel = match ($targetType) {
            'center'  => \App\Core\Query::value('SELECT nom FROM centres WHERE id = ?', [$row['target_id']]),
            'bacenta' => \App\Core\Query::value('SELECT nom FROM bacentas WHERE id = ?', [$row['target_id']]),
            'cult'    => \App\Core\Query::value('SELECT nom FROM cultes WHERE id = ?', [$row['target_id']]),
            'basonta' => \App\Core\Query::value('SELECT nom FROM basontas WHERE id = ?', [$row['target_id']]),
            default   => '#' . $row['target_id'],
        };
        $responsibilities[] = [
            'type'  => RESPONSIBILITY_TARGET_LABELS[$targetType]['label'] ?? $targetType,
            'label' => (string) $targetLabel,
        ];
    }

    // Présences — semaine consultée (spec §26).
    $weekKey = (string) (nav('semaine') ?: current_week_key());
    $weekRows = attendance_service()->weekForUser((int) $membreId, $weekKey);
    $stats = attendance_service()->statsForUser((int) $membreId);

    $hasWeeklyFollowup = in_array($member['role'], WEEKLY_FOLLOWUP_ROLES, true);
    $suiviWeek = $hasWeeklyFollowup ? get_suivi_week((int) $membreId, $weekKey) : [];

    $content = view('pages/profile', [
        'member'           => $member,
        'bacenta'          => $bacenta,
        'invite'           => $invite,
        'recu'             => $recu,
        'responsibilities' => $responsibilities,
        'accountStatusLabel' => ACCOUNT_STATUS_LABELS[$member['account_status'] ?? 'active'] ?? ($member['account_status'] ?? ''),
        'lastLoginLabel'   => !empty($member['last_login_at']) ? date('d/m/Y à H:i', strtotime($member['last_login_at'])) : 'Jamais connecté',
        'weekKey'          => $weekKey,
        'weekRangeLabel'   => format_week_range_label($weekKey),
        'prevWeekKey'      => week_key_of(monday_of_week_key($weekKey)->modify('-7 days')),
        'nextWeekKey'      => week_key_of(monday_of_week_key($weekKey)->modify('+7 days')),
        'weekRows'         => $weekRows,
        'stats'            => $stats,
        'hasWeeklyFollowup'=> $hasWeeklyFollowup,
        'suiviWeek'        => $suiviWeek,
        'suiviFields'      => SUIVI_FIELDS,
        'weekDays'         => WEEK_DAYS,
        'isSelf'           => (int) $current['id'] === (int) $membreId,
        'csrf'             => csrf_field(),
    ]);

    $charts = ['doughnut' => member_presence_counts($member)];
    render_page(SECTION_LABELS['personProfile'], $content, $charts);
}

/* ================= MON PROFIL (libre-service) ================= */

function render_my_profile_page(): void
{
    $user = current_user();
    if (!$user) {
        deny_profile_access();
    }

    $content = view('pages/my_profile', [
        'user'    => $user,
        'psaved'  => isset($_GET['psaved']),
        'perror'  => (string) ($_GET['perror'] ?? ''),
        'psection'=> (string) ($_GET['psection'] ?? 'info'),
        'csrf'    => csrf_field(),
    ]);
    render_page('Mon profil', $content);
}

/* ================= IMPRESSION — PRÉSENCES ================= */

function render_attendance_print_page(): void
{
    $current = current_user();
    $membreId = nav('membre') ?: (int) $current['id'];

    if (!can_view_member_profile((int) $membreId)) {
        deny_profile_access();
    }

    $member = get_user((int) $membreId);
    if (!$member) {
        redirect('index.php', ['page' => 'apropos']);
    }

    $from = trim((string) ($_GET['from'] ?? ''));
    $to = trim((string) ($_GET['to'] ?? ''));
    $semaine = (string) (nav('semaine') ?: '');

    if ($from !== '' || $to !== '') {
        $rows = attendance_service()->historyForUser((int) $membreId, $from ?: null, $to ?: null);
        $periodLabel = 'Du ' . ($from ?: '…') . ' au ' . ($to ?: '…');
    } else {
        $weekKey = $semaine !== '' ? $semaine : current_week_key();
        $rows = attendance_service()->weekForUser((int) $membreId, $weekKey);
        $periodLabel = format_week_range_label($weekKey);
    }

    echo view('pages/attendance_print', [
        'member'      => $member,
        'rows'        => $rows,
        'periodLabel' => $periodLabel,
        'printedAt'   => date('d/m/Y à H:i'),
    ]);
}

/* ================= IMPRESSION — SUIVI HEBDOMADAIRE D'UN BERGER ================= */

function render_suivi_print_page(): void
{
    $current = current_user();
    $membreId = nav('membre') ?: (int) $current['id'];

    if (!can_view_member_profile((int) $membreId)) {
        deny_profile_access();
    }

    $member = get_user((int) $membreId);
    if (!$member || !in_array($member['role'], WEEKLY_FOLLOWUP_ROLES, true)) {
        redirect('index.php', ['page' => 'apropos']);
    }

    $weekKey = (string) (nav('semaine') ?: current_week_key());
    $week = get_suivi_week((int) $membreId, $weekKey);

    echo view('pages/suivi_print', [
        'member'     => $member,
        'weekKey'    => $weekKey,
        'weekRangeLabel' => format_week_range_label($weekKey),
        'week'       => $week,
        'fields'     => SUIVI_FIELDS,
        'days'       => WEEK_DAYS,
        'printedAt'  => date('d/m/Y à H:i'),
    ]);
}
