<?php
/**
 * Page Finances & Offrandes — nouveau modèle (offrandes par bacenta_id / centre_id).
 */

declare(strict_types=1);

require_once __DIR__ . '/render.php';
require_once __DIR__ . '/data.php';

function render_finances_page(): void
{
    $year = current_year();
    $monthKey = current_month_key();
    $bacentasTotal = sum_offrandes_section_year('bacenta', $year);
    $centresTotal = sum_offrandes_section_year('centre', $year);

    $bacentasRows = '';
    foreach (get_bacentas() as $b) {
        $bacentasRows .= finance_row($b['nom'], 'bacenta', (int) $b['id'], $monthKey, $year);
    }
    $centresRows = '';
    foreach (get_centres() as $c) {
        $centresRows .= finance_row($c['nom'], 'centre', (int) $c['id'], $monthKey, $year);
    }

    $charts = ['finance' => ['bacentas' => $bacentasTotal, 'centres' => $centresTotal, 'year' => $year]];

    $content = view('finances', [
        'year'          => $year,
        'monthKey'      => $monthKey,
        'monthLabel'    => month_label($monthKey),
        'bacentasTotal' => $bacentasTotal,
        'centresTotal'  => $centresTotal,
        'globalTotal'   => $bacentasTotal + $centresTotal,
        'bacentasRows'  => $bacentasRows,
        'centresRows'   => $centresRows,
    ]);

    render_page(SECTION_LABELS['finances'], $content, $charts);
}

function finance_row(string $name, string $type, int $id, string $monthKey, int $year): string
{
    $weekArr = get_offrandes_month($type, $id, $monthKey);
    $weekLabel = [];
    foreach ($weekArr as $i => $v) {
        $weekLabel[] = 'S' . ($i + 1) . ' : ' . format_fcfa($v);
    }
    return '<tr><td>' . h($name) . '</td><td class="week-label">' . h(implode(' · ', $weekLabel)) . '</td>'
         . '<td>' . format_fcfa(sum_offrandes_month_total($type, $id, $monthKey)) . '</td>'
         . '<td>' . format_fcfa(sum_offrandes_year_total($type, $id, $year)) . '</td></tr>';
}
