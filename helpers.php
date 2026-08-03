<?php
/**
 * Fonctions utilitaires : échappement, dates, semaines ISO, formatage, URL…
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

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
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function check_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals(csrf_token(), (string) $token)) {
        http_response_code(400);
        exit('Jeton de sécurité invalide (page expirée). Revenez en arrière et réessayez.');
    }
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

/** Clé de semaine ISO "2026-W31" à partir d'une date. */
function week_key_of(DateTimeInterface $d): string
{
    return $d->format('o-\WW');
}

function current_week_key(): string
{
    return date('o-\WW');
}

/** Lundi de la semaine ISO donnée ("2026-W31"). */
function monday_of_week_key(string $weekKey): DateTimeImmutable
{
    [$year, $week] = array_map('intval', explode('-W', $weekKey));
    $jan4 = new DateTimeImmutable(sprintf('%04d-01-04', $year));
    $jan4Day = (int) $jan4->format('N'); // 1 = lundi … 7 = dimanche
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

/** Liste des n derniers mois (mois en cours inclus) : [['label','start','end']]. */
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

/** Champs d'un membre selon la section (miroir de getSectionFields). */
function get_section_fields(string $section): array
{
    return array_merge(BASE_USER_FIELDS, SECTION_EXTRA_FIELDS[$section] ?? []);
}

/* ---------- Uploads ---------- */

/**
 * Enregistre un fichier photo uploadé et retourne son chemin web relatif
 * (ex. "uploads/photo_xxx.jpg"), ou null si aucun fichier valide.
 */
function handle_photo_upload(string $inputName): ?string
{
    if (empty($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$inputName];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($f['size'] > MAX_PHOTO_BYTES) {
        return null;
    }
    $info = @getimagesize($f['tmp_name']);
    if ($info === false) {
        return null;
    }
    $extMap = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    $ext = $extMap[$info[2]] ?? 'jpg';

    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0775, true);
    }
    $name = 'photo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        return null;
    }
    return 'uploads/' . $name;
}

function delete_photo_file(?string $path): void
{
    if ($path && str_starts_with($path, 'uploads/')) {
        $full = UPLOAD_DIR . '/' . substr($path, strlen('uploads/'));
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
