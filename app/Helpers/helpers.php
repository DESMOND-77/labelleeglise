<?php

/**
 * Fonctions utilitaires globales de l'application.
 * (échappement, dates, semaines ISO, formatage, URL, CSRF, navigation)
 *
 * Chargées automatiquement au boot (Bootstrap/init.php).
 */

declare(strict_types=1);

/* ---------- Échappement ---------- */

function h($str): string
{
    if ($str === null || $str === '') {
        return '';
    }
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

/** Minuscules UTF-8 (repli sur strtolower si mbstring n'est pas chargé). */
function mb_lower(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

/* ---------- URL / redirections ---------- */

function url(string $path = '', array $params = []): string
{
    $query = $params ? ('?' . http_build_query($params)) : '';
    return APP_URL . $path . $query;
}

function redirect(string $path = '', array $params = []): never
{
    header('Location: ' . url($path, $params));
    exit;
}

/**
 * Redirige vers le contexte membres d'une section (bacentas/centres/cultes/
 * basontas ou liste). Utilisé par les actions membre (suppression, sauvegarde).
 */
function redirect_members_context(string $section, ?int $entityId = null): never
{
    $params = ['page' => $section];
    $entityId = $entityId ?: nav('id_ent') ?: nav('id');
    if (in_array($section, ['bacentas', 'centres', 'cultes', 'basontas'], true) && $entityId) {
        $params['id'] = $entityId;
        if ($section === 'bacentas' && nav('tab')) {
            $params['tab'] = nav('tab');
        }
    }
    redirect('index.php', $params);
}

function current_url_params(): array
{
    $params = [];
    foreach (['page', 'quartier', 'groupe', 'item', 'tab', 'membre', 'semaine', 'annee', 'param_tab',
              'centre', 'q', 'form', 'action', 'error'] as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== '') {
            $params[$k] = (string) $_GET[$k];
        }
    }
    return $params;
}

/** État de navigation courant (miroir de l'objet `nav` de l'app JS). */
function nav(string $key, $default = null)
{
    static $c = null;
    if ($c === null) {
        $c = [
            'page'       => $_GET['page'] ?? 'accueil',
            'id'         => isset($_GET['id']) ? (int) $_GET['id'] : null,
            'id_ent'     => isset($_GET['id_ent']) ? (int) $_GET['id_ent'] : null,
            'tab'        => $_GET['tab'] ?? 'membres',
            'membre'     => isset($_GET['membre']) ? (int) $_GET['membre'] : null,
            'semaine'    => $_GET['semaine'] ?? null,
            'annee'      => isset($_GET['annee']) ? (int) $_GET['annee'] : null,
            'param_tab'  => $_GET['param_tab'] ?? 'comptes',
            'q'          => $_GET['q'] ?? null,
            'form'       => $_GET['form'] ?? null,
            'gate'       => isset($_GET['gate']) ? (int) $_GET['gate'] : 0,
        ];
    }
    return $c[$key] ?? $default;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    return \App\Core\Csrf::token();
}

function csrf_field(): string
{
    return \App\Core\Csrf::field();
}

function check_csrf(): void
{
    \App\Core\Csrf::check($_POST['csrf'] ?? '');
}

/* ---------- Petits utilitaires ---------- */

function pad2(int $n): string
{
    return str_pad((string) $n, 2, '0', STR_PAD_LEFT);
}

function current_year(): int
{
    return (int) date('Y');
}

function month_key_of(DateTimeInterface $d): string
{
    return $d->format('Y-m');
}

function current_month_key(): string
{
    return date('Y-m');
}

function month_label(string $monthKey): string
{
    [$y, $m] = array_map('intval', explode('-', $monthKey));
    return (MONTHS_FR[$m - 1] ?? $m) . ' ' . $y;
}

function format_fcfa($n): string
{
    return number_format((float) ($n ?? 0), 0, ',', ' ') . ' FCFA';
}

function format_date_short(DateTimeInterface $d): string
{
    return pad2((int) $d->format('d')) . '/' . pad2((int) $d->format('m'));
}

function iso_date_of(DateTimeInterface $d): string
{
    return $d->format('Y-m-d');
}

function week_key_of(DateTimeInterface $d): string
{
    return $d->format('o-\WW');
}

function current_week_key(): string
{
    return date('o-\WW');
}

function monday_of_week_key(string $weekKey): DateTimeImmutable
{
    [$year, $week] = array_map('intval', explode('-W', $weekKey));
    $jan4 = new DateTimeImmutable(sprintf('%04d-01-04', $year));
    $jan4Day = (int) $jan4->format('N');
    $week1Monday = $jan4->modify('-' . ($jan4Day - 1) . ' days');
    return $week1Monday->modify('+' . (($week - 1) * 7) . ' days');
}

function date_for_day_in_week(string $weekKey, int $dayIndex): DateTimeImmutable
{
    return monday_of_week_key($weekKey)->modify("+{$dayIndex} days");
}

function format_week_range_label(string $weekKey): string
{
    $monday = monday_of_week_key($weekKey);
    $sunday = $monday->modify('+6 days');
    return 'Semaine du ' . format_date_short($monday) . ' au ' . format_date_short($sunday) . ' / ' . $sunday->format('Y');
}

function monday_date_of_week_key(string $weekKey): string
{
    return monday_of_week_key($weekKey)->format('Y-m-d');
}

function get_month_buckets(int $n): array
{
    $now = new DateTimeImmutable();
    $buckets = [];
    for ($i = $n - 1; $i >= 0; $i--) {
        $start = $now->modify("-{$i} months")->modify('first day of this month')->setTime(0, 0);
        $end = $start->modify('+1 month');
        $buckets[] = ['label' => MONTHS_FR_SHORT[(int) $start->format('n') - 1], 'start' => $start, 'end' => $end];
    }
    return $buckets;
}

function build_month_options(string $selected): string
{
    $out = '';
    for ($i = 11; $i >= -1; $i--) {
        $d = (new DateTimeImmutable('first day of this month'))->modify(($i === -1 ? '-1 month' : "-{$i} months"));
        $key = $d->format('Y-m');
        $out .= '<option value="' . h($key) . '"' . ($key === $selected ? ' selected' : '') . '>' . h(month_label($key)) . '</option>';
    }
    return $out;
}

function build_year_options(?int $selected = null): string
{
    $now = current_year();
    $selected = $selected ?: $now;
    $years = array_unique([$now - 1, $now, $now + 1, $selected]);
    sort($years);
    $out = '';
    foreach ($years as $y) {
        $out .= '<option value="' . $y . '"' . ($y === $selected ? ' selected' : '') . '>' . $y . '</option>';
    }
    return $out;
}

function get_section_fields(string $section): array
{
    return array_merge(BASE_USER_FIELDS, SECTION_EXTRA_FIELDS[$section] ?? []);
}

/* ---------- Uploads ---------- */

function handle_photo_upload(string $inputName): ?string
{
    return \App\Core\Upload::photo($inputName);
}

function delete_photo_file(?string $path): void
{
    \App\Core\Upload::deletePhoto($path);
}
