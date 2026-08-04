<?php
/**
 * Pages Bergers : fiche berger (infos / dîmes / examens / veillées)
 * et suivi hebdomadaire — basées sur la table users (rôles BERGER_ROLES).
 */

declare(strict_types=1);

require_once __DIR__ . '/render.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/auth.php';

/* ================= FICHE BERGER ================= */

function render_berger_fiche_page(): void
{
    $scope = get_user_scope();
    $memberId = nav('membre');
    // Un compte Berger est cantonné à sa propre fiche.
    if ($scope && $scope['kind'] === 'berger') {
        $memberId = $scope['user_id'];
    }
    if (!$memberId) {
        redirect('index.php', ['page' => 'bergers']);
    }
    $member = get_user($memberId);
    if (!$member || !in_array($member['role'], BERGER_ROLES, true)) {
        render_page(SECTION_LABELS['bergerFiche'], empty_state('fa-ban', 'Berger introuvable.'));
        return;
    }

    $tab = nav('tab') ?: 'infos';
    $labels = ['infos' => '<i class="fa-solid fa-user"></i> Infos', 'dimes' => '<i class="fa-solid fa-sack-dollar"></i> Dîmes', 'examens' => '<i class="fa-solid fa-graduation-cap"></i> Examens', 'veillees' => '<i class="fa-solid fa-moon"></i> Veillées'];
    $tabs = [];
    foreach ($labels as $k => $lbl) {
        $tabs[$k] = ['label' => $lbl, 'url' => url('index.php', ['page' => 'bergerFiche', 'membre' => $memberId, 'tab' => $k])];
    }
    $tabRow = tab_row($tabs, $tab);
    $locked = is_berger_scope_locked();
    $backBtn = $locked ? '' : back_button('Retour à la liste des bergers', url('index.php', ['page' => 'bergers']));

    $body = '';
    if ($tab === 'dimes') {
        $year = nav('annee') ?: current_year();
        $body = view('berger_dimes', [
            'member'      => $member,
            'year'        => $year,
            'yearOptions' => build_year_options($year),
            'dimes'       => get_dimes($memberId, $year),
            'monthsShort' => MONTHS_FR_SHORT,
            'total'       => array_sum(get_dimes($memberId, $year)),
        ]);
    } elseif ($tab === 'examens') {
        $body = view('berger_examens', ['member' => $member, 'examens' => get_examens($memberId)]);
    } elseif ($tab === 'veillees') {
        $veillees = get_veillees($memberId);
        $body = view('berger_veillees', [
            'member'  => $member,
            'veillees'=> $veillees,
            'presentCount' => count(array_filter($veillees, fn($v) => (bool) $v['present'])),
        ]);
    } else {
        $body = view('berger_infos', ['member' => $member]);
    }

    $content = $backBtn . section_toolbar(h(full_name($member)), 'Fiche berger') . $tabRow . $body;
    render_page('Fiche Berger', $content);
}

/* ================= SUIVI HEBDOMADAIRE DES BERGERS ================= */

function render_suivi_bergers_page(): void
{
    $scope = get_user_scope();
    $memberId = nav('membre');

    if ($scope && $scope['kind'] === 'berger') {
        $memberId = $scope['user_id'];
    }

    if (!$memberId) {
        // Liste des bergers (admin) — simple table avec accès direct au suivi.
        if (!has_verified_access('suiviBergers', null)) {
            render_gate('suiviBergers', null, SECTION_LABELS['suiviBergers']);
            return;
        }
        $members = get_berger_members();
        $content = members_table('suiviBergers', null, SECTION_LABELS['suiviBergers'], count($members), $members);
        render_page(SECTION_LABELS['suiviBergers'], $content);
        return;
    }

    $member = get_user($memberId);
    if (!$member || !in_array($member['role'], BERGER_ROLES, true)) {
        redirect('index.php', ['page' => 'suiviBergers']);
    }

    $semaine = nav('semaine');
    if ($semaine) {
        $weekKey = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $semaine)
            ? week_key_of(new DateTimeImmutable($semaine))
            : (string) $semaine;
    } else {
        $weekKey = current_week_key();
    }
    $week = get_suivi_week($memberId, $weekKey);
    $pct = compute_week_completion($week);
    $isAdmin = current_user()['role'] === 'admin';
    $locked = is_berger_scope_locked();
    $backBtn = $locked ? '' : back_button('Retour aux bergers', url('index.php', ['page' => 'suiviBergers']));

    $charts = null;
    $adminStats = '';
    if ($isAdmin) {
        $weeks = qall('SELECT DISTINCT semaine FROM suivi_hebdo WHERE user_id = ? AND semaine LIKE ? ORDER BY semaine',
                      [$memberId, current_year() . '-W%']);
        $series = [];
        foreach ($weeks as $w) {
            $series[] = ['week' => $w['semaine'], 'pct' => compute_week_completion(get_suivi_week($memberId, $w['semaine']))];
        }
        $charts = ['suivi' => $series];
        $adminStats = view('suivi_admin_stats', [
            'pctWeek' => $pct,
            'pctYear' => compute_year_completion($memberId, current_year()),
        ]);
    }

    $content = view('suivi_week', [
        'backBtn'    => $backBtn,
        'member'     => $member,
        'weekKey'    => $weekKey,
        'weekRange'  => format_week_range_label($weekKey),
        'mondayDate' => monday_date_of_week_key($weekKey),
        'week'       => $week,
        'fields'     => SUIVI_FIELDS,
        'days'       => WEEK_DAYS,
        'pct'        => $pct,
        'adminStats' => $adminStats,
        'csrf'       => csrf_field(),
        'isAdmin'    => $isAdmin,
    ]);

    render_page('Suivi Hebdomadaire des Bergers', $content, $charts);
}
