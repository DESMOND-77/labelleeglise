<?php

/**
 * Wrappers de compatibilité pour le rendu des pages.
 * -------------------------------------------------------------
 * Regroupe l'ensemble des fonctions de rendu autrefois réparties
 * dans pages.php / pages_sections.php / pages_bergers.php /
 * pages_finances.php / pages_parametres.php / pages_apropos.php.
 *
 * Les contrôleurs sont de simples relais vers ces fonctions.
 */

declare(strict_types=1);

use App\Core\Query;

/* ================= Dispatcher ================= */

function page_content(): void
{
    $page = nav('page');

    $allowed = get_allowed_sections();
    if ($allowed !== null && !in_array($page, $allowed, true)) {
        $target = scope_target();
        redirect('index.php', $target ?: ['page' => 'apropos']);
    }

    switch ($page) {
        case 'accueil':
            render_accueil_page();
            break;
        case 'apropos':
            render_apropos_page();
            break;
        case 'centresPresentation':
            render_centres_presentation_page();
            break;
        case 'bacentas':
        case 'centres':
        case 'cultes':
        case 'basontas':
        case 'nouveaux':
        case 'generale':
        case 'bergers':
            render_section_page($page);
            break;
        case 'bergerFiche':
            render_berger_fiche_page();
            break;
        case 'suiviBergers':
            render_suivi_bergers_page();
            break;
        case 'finances':
            render_finances_page();
            break;
        case 'parametres':
            render_parametres_page();
            break;
        case 'recherche':
            render_recherche_page();
            break;
        case 'personProfile':
            render_profile_page();
            break;
        default:
            render_accueil_page();
    }
}

/* ================= ACCUEIL ================= */

function render_accueil_page(): void
{
    $stats  = compute_summary_stats();
    $hist   = compute_evolution_history();
    $narrative = build_narrative($stats);
    $year   = current_year();
    $poles  = CHART_POLES;

    $charts = [
        'bar' => [
            'labels' => array_column($poles, 'label'),
            'data'   => array_map(fn($p) => count_members($p['key']), $poles),
            'colors' => array_column($poles, 'color'),
        ],
        'mini' => [],
    ];
    foreach ($poles as $p) {
        $charts['mini'][] = [
            'id'     => 'chart-' . $p['key'],
            'labels' => $hist['labels'],
            'data'   => $hist[$p['key']],
            'color'  => $p['color'],
        ];
    }

    $content = view('pages/accueil', [
        'user'      => current_user(),
        'slides'    => SLIDES,
        'poles'     => $poles,
        'counts'    => array_map(fn($p) => count_members($p['key']), $poles),
        'year'      => $year,
        'offrandes' => global_offrandes_year($year),
        'stats'     => $stats,
        'narrative' => $narrative,
    ]);

    render_page(SECTION_LABELS['accueil'], $content, $charts);
}

/* ================= RECHERCHE GLOBALE ================= */

function render_recherche_page(): void
{
    if (current_user()['role'] !== 'admin') {
        redirect('index.php', ['page' => 'apropos']);
    }
    $q = trim((string) nav('q'));
    $results = $q !== '' ? search_people($q) : [];
    $content = view('pages/recherche', ['q' => $q, 'results' => $results]);
    render_page('Recherche', $content);
}

/* ================= FICHE PROFIL / MON PROFIL ================= */
// Voir app/Compat/profile.php : render_profile_page(), render_my_profile_page(),
// render_attendance_print_page(), render_suivi_print_page().

/* ================= LOGIN ================= */

function page_login(): void
{
    echo view('pages/login', ['error' => isset($_GET['error'])]);
}
