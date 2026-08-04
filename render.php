<?php
/**
 * Moteur de rendu — templates serveur (view + layout).
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

/** Rendu d'un template en chaîne HTML. */
function view(string $template, array $vars = []): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/views/templates/' . $template . '.php';
    return (string) ob_get_clean();
}

/** Page complète (shell : sidebar + topbar + contenu). */
function render_page(string $title, string $content, ?array $charts = null): void
{
    echo view('layout', ['title' => $title, 'content' => $content, 'charts' => $charts]);
}

/** Première lettre (repli sûr si mbstring absent). */
function first_char(string $s): string
{
    return function_exists('mb_substr') ? mb_substr($s, 0, 1) : substr($s, 0, 1);
}

/* ================= PARTIALS ================= */

function breadcrumb_html(array $crumbs): string
{
    $parts = [];
    foreach ($crumbs as $i => $c) {
        if ($i === 0) {
            $parts[] = '<span class="crumb-link"><a href="' . h($c['url']) . '">' . h($c['label']) . '</a></span>';
        } else {
            $parts[] = '<span class="crumb-sep"><i class="fa-solid fa-chevron-right"></i></span><span class="crumb-item">' . h($c['label']) . '</span>';
        }
    }
    return implode('', $parts);
}

function section_toolbar(string $title, string $sub = '', string $extraRight = ''): string
{
    return '<div class="section-toolbar"><div><h2>' . $title . '</h2>' .
           ($sub !== '' ? '<div class="sub">' . $sub . '</div>' : '') . '</div>' . $extraRight . '</div>';
}

function back_button(string $label, string $url): string
{
    return '<a class="btn-back" href="' . h($url) . '"><i class="fa-solid fa-chevron-left"></i> ' . h($label) . '</a>';
}

function empty_state(string $icon, string $text): string
{
    // Accepte soit une classe Font Awesome, soit un markup <i> complet.
    $html = str_starts_with($icon, '<') ? $icon : '<i class="fa-solid ' . $icon . '"></i>';
    return '<div class="empty-state"><div class="emoji">' . $html . '</div><p>' . h($text) . '</p></div>';
}

function tab_row(array $tabs, string $active): string
{
    $html = '<div class="tab-row">';
    foreach ($tabs as $key => $label) {
        $cls = $key === $active ? 'active' : '';
        $html .= '<a class="tab-btn ' . $cls . '" href="' . h($label['url']) . '">' . $label['label'] . '</a>';
    }
    return $html . '</div>';
}

function stat_card(string $label, string $value, string $color, string $sub = ''): string
{
    return '<div class="stat-card"><div class="stat-top"><span class="stat-label">' . h($label) . '</span></div>' .
           '<div class="stat-value" style="color:' . h($color) . '">' . $value . '</div>' .
           ($sub !== '' ? '<div class="stat-label">' . h($sub) . '</div>' : '') . '</div>';
}

function total_chip(string $label, string $value): string
{
    return '<div class="total-chip"><span>' . h($label) . '</span><b>' . $value . '</b></div>';
}

/** Lien "ajouter" dans une toolbar. */
function add_button(string $label, array $params): string
{
    return '<a class="btn btn-primary" href="' . h(url('index.php', $params)) . '">+ ' . h($label) . '</a>';
}

function presence_badge(?string $val): string
{
    if (!$val) {
        return '<span class="badge neutral">—</span>';
    }
    $cls = $val === 'Présent' ? 'present' : 'absent';
    return '<span class="badge ' . $cls . '">' . h($val) . '</span>';
}

function info_rows_html(array $rows): string
{
    $html = '';
    foreach ($rows as [$label, $val]) {
        $html .= '<div class="info-row"><span>' . h($label) . '</span><b>' . ($val !== null && $val !== '' ? h($val) : '—') . '</b></div>';
    }
    return $html;
}
