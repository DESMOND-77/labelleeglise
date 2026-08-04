<?php
/**
 * Pages Présentation de l'église (à propos + équipe) et Présentation des centres.
 */

declare(strict_types=1);

require_once __DIR__ . '/render.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/auth.php';

/* ================= PRÉSENTATION DE L'ÉGLISE ================= */

function render_apropos_page(): void
{
    $isAdmin = current_user()['role'] === 'admin';
    $form = nav('form');

    if ($form === 'histoire') {
        $p = get_presentation();
        $content = view('forms/histoire', [
            'accroche'  => $p['accroche'] ?? '',
            'histoire'  => $p['histoire'] ?? '',
            'cancelUrl' => url('index.php', ['page' => 'apropos']),
            'csrf'      => csrf_field(),
        ]);
        render_page('Modifier la présentation', $content);
        return;
    }

    if ($form === 'equipe') {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $member = $id ? qone('SELECT * FROM equipe WHERE id = ?', [$id]) : null;
        $content = view('forms/equipe', [
            'member'    => $member,
            'isNew'     => !$member,
            'cancelUrl' => url('index.php', ['page' => 'apropos']),
            'csrf'      => csrf_field(),
            'categories'=> TEAM_CATEGORIES,
        ]);
        render_page($member ? "Modifier le membre de l'équipe" : "Ajouter un membre de l'équipe", $content);
        return;
    }

    $p = get_presentation();
    $equipe = get_equipe();

    $groupLabels = ['Révérend' => 'Révérends', 'Pasteur' => 'Pasteurs', 'Berger' => 'Bergers', 'Leader' => 'Leaders', 'Autre' => 'Autres'];
    $groups = [];
    foreach (TEAM_CATEGORIES as $cat) {
        $members = array_values(array_filter($equipe, fn($m) => (($m['categorie'] ?? 'Autre') === $cat)));
        if ($members) {
            $groups[] = ['label' => $groupLabels[$cat], 'members' => $members];
        }
    }

    $content = view('apropos', [
        'p'        => $p,
        'isAdmin'  => $isAdmin,
        'groups'   => $groups,
        'editHistoireUrl' => $isAdmin ? url('index.php', ['page' => 'apropos', 'form' => 'histoire']) : null,
        'addTeamUrl'      => $isAdmin ? url('index.php', ['page' => 'apropos', 'form' => 'equipe']) : null,
    ]);

    render_page(SECTION_LABELS['apropos'], $content);
}

function team_card_html(array $m, bool $isAdmin): string
{
    $actions = '';
    if ($isAdmin) {
        $actions = '<div class="card-actions">'
            . '<a class="icon-btn" title="Modifier" href="' . h(url('index.php', ['page' => 'apropos', 'form' => 'equipe', 'id' => $m['id']])) . '"><i class="fa-solid fa-pen"></i></a>'
            . '<a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer ce membre de l\'équipe ?" href="' . h(url('index.php', ['page' => 'apropos', 'action' => 'delete_equipe', 'id' => $m['id']])) . '"><i class="fa-solid fa-trash"></i></a>'
            . '</div>';
    }
$avatar = !empty($m['photo'])
        ? '<img src="' . h($m['photo']) . '" class="team-avatar-img" alt="' . h($m['nom_affichage']) . '">'
        : '<div class="team-avatar">' . ($m['emoji'] ?? '<i class="fa-solid fa-user"></i>') . '</div>';
    return '<div class="team-card">' . $actions . $avatar
        . '<h3>' . h($m['nom_affichage']) . '</h3>'
        . '<div class="team-role">' . h($m['role_affichage']) . '</div>'
        . '<p>' . h($m['bio'] ?? '') . '</p></div>';
}

/* ================= PRÉSENTATION DES CENTRES ================= */

function render_centres_presentation_page(): void
{
    $isAdmin = current_user()['role'] === 'admin';
    $form = nav('form');

    if ($form === 'article') {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $c = $id ? get_centre_article($id) : null;
        $centreOpts = '';
        foreach (get_centres() as $ct) {
            $centreOpts .= '<option value="' . $ct['id'] . '"'
                . (($c && (int) $c['centre_id'] === (int) $ct['id']) || (!$c && !isset($ct)) ? ' selected' : '')
                . '>' . h($ct['nom']) . '</option>';
        }
        $content = view('forms/article', [
            'article'    => $c,
            'isNew'      => !$c,
            'centreOpts' => $centreOpts,
            'cancelUrl'  => url('index.php', ['page' => 'centresPresentation']),
            'csrf'       => csrf_field(),
        ]);
        render_page($c ? 'Modifier l\'article' : 'Ajouter un article', $content);
        return;
    }

    if (nav('id')) {
        $c = get_centre_article(nav('id'));
        if (!$c) {
            redirect('index.php', ['page' => 'centresPresentation']);
        }
        $content = view('centre_article', [
            'c'       => $c,
            'isAdmin' => $isAdmin,
            'backUrl' => url('index.php', ['page' => 'centresPresentation']),
            'editUrl' => $isAdmin ? url('index.php', ['page' => 'centresPresentation', 'form' => 'article', 'id' => $c['id']]) : null,
        ]);
        render_page('Présentation des centres', $content);
        return;
    }

    $articles = get_centres_articles();
    $cards = '';
    foreach ($articles as $c) {
        $actions = '';
        if ($isAdmin) {
            $actions = '<div class="card-actions">'
                . '<a class="icon-btn" title="Modifier" href="' . h(url('index.php', ['page' => 'centresPresentation', 'form' => 'article', 'id' => $c['id']])) . '"><i class="fa-solid fa-pen"></i></a>'
                . '<a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer cet article ?" href="' . h(url('index.php', ['page' => 'centresPresentation', 'action' => 'delete_article', 'id' => $c['id']])) . '"><i class="fa-solid fa-trash"></i></a>'
                . '</div>';
        }
        $intro = (string) ($c['intro'] ?? '');
        $sub = function_exists('mb_substr') ? mb_substr($intro, 0, 90) : substr($intro, 0, 90);
        $sub .= (function_exists('mb_strlen') ? mb_strlen($intro) : strlen($intro)) > 90 ? '…' : '';
        $cards .= '<div class="unit-card" onclick="location.href=\'' . h(url('index.php', ['page' => 'centresPresentation', 'id' => $c['id']])) . '\'">'
            . $actions . '<div class="icon-wrap"><i class="fa-solid fa-school"></i></div><h3>' . h($c['centre_nom']) . '</h3><p>' . h($sub) . '</p></div>';
    }
    $addCard = $isAdmin
        ? '<a class="unit-card add-card" href="' . h(url('index.php', ['page' => 'centresPresentation', 'form' => 'article'])) . '"><div class="plus">+</div> Ajouter un article</a>'
        : '';

    $content = view('centres_presentation', [
        'cards'   => $cards,
        'addCard' => $addCard,
        'addUrl'  => $isAdmin ? url('index.php', ['page' => 'centresPresentation', 'form' => 'article']) : null,
        'isAdmin' => $isAdmin,
        'count'   => count($articles),
    ]);

    render_page(SECTION_LABELS['centresPresentation'], $content);
}
