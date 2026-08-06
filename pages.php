<?php
/**
 * Pages — dispatcher principal + Accueil + Recherche + Fiche profil + Login.
 */

declare(strict_types=1);

require_once __DIR__ . '/render.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/auth.php';

function page_content(): void
{
    $page = nav('page');

    // Garde-fou RBAC : aucune navigation hors du périmètre autorisé.
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

$content = view('accueil', [
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

/* ================= RECHERCHE GLOBALE (admin) ================= */

function render_recherche_page(): void
{
    if (current_user()['role'] !== 'admin') {
        redirect('index.php', ['page' => 'apropos']);
    }
    $q = trim((string) nav('q'));
    $results = $q !== '' ? search_people($q) : [];
    $content = view('recherche', ['q' => $q, 'results' => $results]);
    render_page('Recherche', $content);
}

/* ================= FICHE PROFIL (membre) ================= */

function render_profile_page(): void
{
    $user = nav('membre') ? get_user(nav('membre')) : null;
    if (!$user) {
        render_page(SECTION_LABELS['personProfile'], empty_state('fa-ban', 'Membre introuvable.'));
        return;
    }
    $charts = ['doughnut' => member_presence_counts($user)];
    $content = view('profile', ['member' => $user]);
    render_page(SECTION_LABELS['personProfile'], $content, $charts);
}

/* ================= LOGIN ================= */

function page_login(): void
{
    echo view('login', ['error' => isset($_GET['error'])]);
}
