<?php

/**
 * Compatibilité — sections (bacentas / centres / cultes / basontas / listes).
 * Portage de l'ancien pages_sections.php.
 */

declare(strict_types=1);

use App\Core\Query;
use App\Repositories\MemberRepository;

/* ================= Dispatcher section ================= */

function render_section_page(string $section): void
{
    $form = nav('form');

    if ($form === 'membre') {
        render_member_form($section);
        return;
    }

    switch ($section) {
        case 'bacentas':
            if ($form === 'bacenta') {
                render_bacenta_form();
                return;
            }
            if (!nav('id')) {
                render_bacentas_grid();
            } else {
                render_bacenta_detail(nav('id'));
            }
            return;

        case 'centres':
            if ($form === 'centre') {
                render_centre_form();
                return;
            }
            if (!nav('id')) {
                render_centres_grid();
            } else {
                render_centre_detail(nav('id'));
            }
            return;

        case 'cultes':
            if ($form === 'culte') {
                render_culte_form();
                return;
            }
            if (!nav('id')) {
                render_cultes_grid();
            } else {
                render_culte_detail(nav('id'));
            }
            return;

        case 'basontas':
            if ($form === 'basonta') {
                render_basonta_form();
                return;
            }
            if (!nav('id')) {
                render_basontas_grid();
            } else {
                render_basonta_detail(nav('id'));
            }
            return;

        case 'nouveaux':
            render_member_list_page('nouveaux');
            return;
        case 'generale':
            render_member_list_page('generale');
            return;
        case 'bergers':
            render_member_list_page('bergers');
            return;
    }

    redirect('index.php', ['page' => 'accueil']);
}

/* ================= GRILLES ================= */

function bacenta_card_html(array $b, bool $canEdit): string
{
    $actions = '';
    if ($canEdit) {
        $actions = '<div class="card-actions">'
            . '<a class="icon-btn" title="Modifier" href="' . h(url('index.php', ['page' => 'bacentas', 'form' => 'bacenta', 'id' => $b['id']])) . '"><i class="fa-solid fa-pen"></i></a>'
            . '<a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer ce bacenta et tous ses membres ?" href="' . h(url('index.php', ['page' => 'bacentas', 'action' => 'delete_bacenta', 'id' => $b['id']])) . '"><i class="fa-solid fa-trash"></i></a>'
            . '</div>';
    }
    $sub = ($b['centre_nom'] ?? '') . ($b['centre_nom'] ? ' · ' : '') . (int) $b['nb_membres'] . ' membre(s)';
    return '<div class="unit-card" onclick="location.href=\'' . h(url('index.php', ['page' => 'bacentas', 'id' => $b['id']])) . '\'">'
        . $actions
        . '<div class="icon-wrap"><i class="fa-solid fa-church"></i></div><h3>' . h($b['nom']) . '</h3><p>' . h($sub) . '</p></div>';
}

function render_bacentas_grid(): void
{
    $user = current_user();
    $isAdmin = $user['role'] === 'admin';

    $bacentas = get_bacentas();
    // Périmètre réel (spec §12/§17/§39) : la liste elle-même est filtrée au
    // périmètre autorisé, jamais seulement les boutons d'action.
    // auth_can_manage_bacenta() couvre à la fois la bacenta d'appartenance
    // historique (leader/pasteur/reverant/berger/ms) et la responsabilité
    // réelle (table `responsibilities`, avec héritage centre → bacenta).
    if (!$isAdmin) {
        $bacentas = array_values(array_filter($bacentas, fn($b) => auth_can_manage_bacenta((int) $b['id'])));
    }
    $cards = '';
    foreach ($bacentas as $b) {
        $canEdit = $isAdmin || auth_can_manage_bacenta((int) $b['id']);
        $cards .= bacenta_card_html($b, $canEdit);
    }
    $addCard = $isAdmin
        ? '<a class="unit-card add-card" href="' . h(url('index.php', ['page' => 'bacentas', 'form' => 'bacenta'])) . '"><div class="plus">+</div> Ajouter un bacenta</a>'
        : '';

    $content = section_toolbar(SECTION_LABELS['bacentas'], 'Choisissez un bacenta', $addCard ? '<a class="btn btn-primary" href="' . h(url('index.php', ['page' => 'bacentas', 'form' => 'bacenta'])) . '">+ Ajouter un bacenta</a>' : '')
        . '<div class="card-grid">' . $cards . $addCard . '</div>'
        . ($cards === '' ? empty_state('fa-inbox', 'Aucun bacenta pour le moment.') : '');
    render_page(SECTION_LABELS['bacentas'], $content);
}

function render_centres_grid(): void
{
    $user = current_user();
    $isAdmin = $user['role'] === 'admin';
    $centres = get_centres();
    if (!$isAdmin) {
        $centres = array_values(array_filter($centres, fn($c) => auth_can_manage_center((int) $c['id'])));
    }
    $cards = '';
    foreach ($centres as $c) {
        $canEdit = $isAdmin || auth_can_manage_center((int) $c['id']);
        $actions = $canEdit
            ? '<div class="card-actions">'
            . '<a class="icon-btn" title="Modifier" href="' . h(url('index.php', ['page' => 'centres', 'form' => 'centre', 'id' => $c['id']])) . '"><i class="fa-solid fa-pen"></i></a>'
            . ($isAdmin ? '<a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer ce centre et toutes ses données ?" href="' . h(url('index.php', ['page' => 'centres', 'action' => 'delete_centre', 'id' => $c['id']])) . '"><i class="fa-solid fa-trash"></i></a>' : '')
            . '</div>'
            : '';
        $sub = (int) $c['nb_bacentas'] . ' bacenta(s)';
        $cards .= '<div class="unit-card" onclick="location.href=\'' . h(url('index.php', ['page' => 'centres', 'id' => $c['id']])) . '\'">'
            . $actions . '<div class="icon-wrap"><i class="fa-solid fa-landmark"></i></div><h3>' . h($c['nom']) . '</h3><p>' . h($sub) . '</p></div>';
    }
    $addCard = $isAdmin
        ? '<a class="unit-card add-card" href="' . h(url('index.php', ['page' => 'centres', 'form' => 'centre'])) . '"><div class="plus">+</div> Ajouter un centre</a>'
        : '';
    $content = section_toolbar(SECTION_LABELS['centres'], 'La structure des centres', $addCard ? '<a class="btn btn-primary" href="' . h(url('index.php', ['page' => 'centres', 'form' => 'centre'])) . '">+ Ajouter un centre</a>' : '')
        . '<div class="card-grid">' . $cards . $addCard . '</div>'
        . ($cards === '' ? empty_state('fa-landmark', 'Aucun centre pour le moment.') : '');
    render_page(SECTION_LABELS['centres'], $content);
}

function render_cultes_grid(): void
{
    $isAdmin = current_user()['role'] === 'admin';
    $cultes = get_cultes();
    if (!$isAdmin) {
        // spec §26 : seul un pasteur/reverant responsable DE CE CULTE le voit
        // dans sa liste de gestion (jamais par simple rôle).
        $cultes = array_values(array_filter($cultes, fn($c) => auth_can_manage_culte((int) $c['id'])));
    }
    $cards = '';
    foreach ($cultes as $c) {
        $canEdit = $isAdmin || auth_can_manage_culte((int) $c['id']);
        $actions = $canEdit
            ? '<div class="card-actions">'
            . '<a class="icon-btn" title="Modifier" href="' . h(url('index.php', ['page' => 'cultes', 'form' => 'culte', 'id' => $c['id']])) . '"><i class="fa-solid fa-pen"></i></a>'
            . ($isAdmin ? '<a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer ce culte ?" href="' . h(url('index.php', ['page' => 'cultes', 'action' => 'delete_culte', 'id' => $c['id']])) . '"><i class="fa-solid fa-trash"></i></a>' : '')
            . '</div>'
            : '';
        $date = $c['date_culte'] ? date('d/m/Y', strtotime($c['date_culte'])) : 'Date à définir';
        $sub = $date . ' · ' . (int) $c['nb_presents'] . ' présent(s)';
        $cards .= '<div class="unit-card" onclick="location.href=\'' . h(url('index.php', ['page' => 'cultes', 'id' => $c['id']])) . '\'">'
            . $actions . '<div class="icon-wrap"><i class="fa-solid fa-hands-praying"></i></div><h3>' . h($c['nom']) . '</h3><p>' . h($sub) . '</p></div>';
    }
    $addCard = $isAdmin
        ? '<a class="unit-card add-card" href="' . h(url('index.php', ['page' => 'cultes', 'form' => 'culte'])) . '"><div class="plus">+</div> Ajouter un culte</a>'
        : '';
    $content = section_toolbar(SECTION_LABELS['cultes'], 'Cultes et réunions', $addCard ? '<a class="btn btn-primary" href="' . h(url('index.php', ['page' => 'cultes', 'form' => 'culte'])) . '">+ Ajouter un culte</a>' : '')
        . '<div class="card-grid">' . $cards . $addCard . '</div>'
        . ($cards === '' ? empty_state('fa-hands-praying', 'Aucun culte pour le moment.') : '');
    render_page(SECTION_LABELS['cultes'], $content);
}

function render_basontas_grid(): void
{
    $isAdmin = current_user()['role'] === 'admin';
    $basontas = get_basontas();
    if (!$isAdmin) {
        $basontas = array_values(array_filter($basontas, fn($b) => auth_can_manage_basonta((int) $b['id'])));
    }
    $cards = '';
    foreach ($basontas as $b) {
        $canEdit = $isAdmin || auth_can_manage_basonta((int) $b['id']);
        $actions = $canEdit
            ? '<div class="card-actions">'
            . '<a class="icon-btn" title="Modifier" href="' . h(url('index.php', ['page' => 'basontas', 'form' => 'basonta', 'id' => $b['id']])) . '"><i class="fa-solid fa-pen"></i></a>'
            . ($isAdmin ? '<a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer ce basonta ?" href="' . h(url('index.php', ['page' => 'basontas', 'action' => 'delete_basonta', 'id' => $b['id']])) . '"><i class="fa-solid fa-trash"></i></a>' : '')
            . '</div>'
            : '';
        $cards .= '<div class="unit-card" onclick="location.href=\'' . h(url('index.php', ['page' => 'basontas', 'id' => $b['id']])) . '\'">'
            . $actions . '<div class="icon-wrap"><i class="fa-solid fa-microphone"></i></div><h3>' . h($b['nom']) . '</h3><p>' . (int) $b['nb_membres'] . ' membre(s)</p></div>';
    }
    $addCard = $isAdmin
        ? '<a class="unit-card add-card" href="' . h(url('index.php', ['page' => 'basontas', 'form' => 'basonta'])) . '"><div class="plus">+</div> Ajouter un basonta</a>'
        : '';
    $content = section_toolbar(SECTION_LABELS['basontas'], 'Ministères et départements', $addCard ? '<a class="btn btn-primary" href="' . h(url('index.php', ['page' => 'basontas', 'form' => 'basonta'])) . '">+ Ajouter un basonta</a>' : '')
        . '<div class="card-grid">' . $cards . $addCard . '</div>'
        . ($cards === '' ? empty_state('fa-microphone', 'Aucun basonta pour le moment.') : '');
    render_page(SECTION_LABELS['basontas'], $content);
}

/* ================= DÉTAILS ================= */

/**
 * Onglet présences d'une unité : pointage d'une date (tab=presences) ou
 * matrice annuelle (tab=presences_annuel). $unitType ∈ bacenta|cult|basonta,
 * $pageKey ∈ bacentas|cultes|basontas.
 */
function render_unit_presence_tab(string $unitType, string $pageKey, array $unit, string $tab, array $members): void
{
    $unitId = (int) $unit['id'];
    if (!can_manage_entity($unitType, $unitId)) {
        redirect('index.php', ['page' => $pageKey, 'id' => $unitId]);
    }
    $joursHint = trim(str_replace(',', ', ', (string) ($unit['jours_semaine'] ?? '')));

    if ($tab === 'presences_annuel') {
        $year = (int) (nav('year') ?: date('Y'));
        render_page($unit['nom'], view('pages/presence_matrix', [
            'unit'     => $unit,
            'pageKey'  => $pageKey,
            'unitType' => $unitType,
            'year'     => $year,
            'matrix'   => unit_annual_matrix($unitType, $unitId, $year, $members),
            'statuts'  => PRESENCE_STATUTS,
            'printUrl' => url('index.php', ['page' => 'presencePrint', 'unit_type' => $unitType, 'unit_id' => $unitId, 'year' => $year]),
            'occUrl'   => url('index.php', ['page' => $pageKey, 'id' => $unitId, 'tab' => 'presences']),
        ]));
        return;
    }

    $date = (string) (nav('date') ?: date('Y-m-d'));
    render_page($unit['nom'], view('pages/presence_occurrence', [
        'unitType'  => $unitType,
        'unit'      => $unit,
        'pageKey'   => $pageKey,
        'date'      => $date,
        'grid'      => unit_presence_grid($unitType, $unitId, $date, $members),
        'statuts'   => PRESENCE_STATUTS,
        'joursHint' => $joursHint,
        'csrf'      => csrf_field(),
        'matrixUrl' => url('index.php', ['page' => $pageKey, 'id' => $unitId, 'tab' => 'presences_annuel']),
    ]));
}

/**
 * Page autonome imprimable : matrice annuelle des présences d'une unité.
 * ?page=presencePrint&unit_type=<bacenta|cult|basonta>&unit_id=<id>&year=<yyyy>
 */
function render_presence_matrix_print_page(): void
{
    if (!current_user()) {
        redirect('index.php', ['page' => 'apropos']);
    }
    $unitType = (string) (nav('unit_type') ?? '');
    $unitId = (int) (nav('unit_id') ?? 0);
    if (!in_array($unitType, ['bacenta', 'cult', 'basonta'], true) || !$unitId || !can_manage_entity($unitType, $unitId)) {
        redirect('index.php', ['page' => 'accueil']);
    }
    $year = (int) (nav('year') ?: date('Y'));

    [$unit, $members] = match ($unitType) {
        'bacenta' => [get_bacenta($unitId), get_members_of_bacenta($unitId)],
        'basonta' => [get_basonta($unitId), get_members_of_basonta($unitId)],
        'cult'    => [get_culte($unitId), Query::all("SELECT * FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant') ORDER BY prenom, nom")],
    };
    if (!$unit) {
        redirect('index.php', ['page' => 'accueil']);
    }

    echo view('pages/presence_matrix_print', [
        'unit'      => $unit,
        'year'      => $year,
        'matrix'    => unit_annual_matrix($unitType, $unitId, $year, $members),
        'statuts'   => PRESENCE_STATUTS,
        'printedAt' => date('d/m/Y à H:i'),
    ]);
}

function render_bacenta_detail(int $bacentaId): void
{
    $b = get_bacenta($bacentaId);
    if (!$b) {
        redirect('index.php', ['page' => 'bacentas']);
    }

    $tab = nav('tab');
    if ($tab === 'suivi') {
        if (!has_verified_access('bacentas', $bacentaId)) {
            render_gate('bacentas', $bacentaId, $b['nom']);
            return;
        }
        $content = bacenta_suivi($b);
        render_page($b['nom'], $content);
        return;
    }

    if (!has_verified_access('bacentas', $bacentaId)) {
        render_gate('bacentas', $bacentaId, $b['nom']);
        return;
    }

    if ($tab === 'presences' || $tab === 'presences_annuel') {
        render_unit_presence_tab('bacenta', 'bacentas', $b, $tab, get_members_of_bacenta($bacentaId));
        return;
    }

    $tabs = [
        'membres'   => ['label' => '<i class="fa-solid fa-users"></i> Membres', 'url' => url('index.php', ['page' => 'bacentas', 'id' => $bacentaId, 'tab' => 'membres'])],
        'presences' => ['label' => '<i class="fa-solid fa-clipboard-check"></i> Présences', 'url' => url('index.php', ['page' => 'bacentas', 'id' => $bacentaId, 'tab' => 'presences'])],
        'suivi'     => ['label' => '<i class="fa-solid fa-chart-column"></i> Suivi & Offrandes', 'url' => url('index.php', ['page' => 'bacentas', 'id' => $bacentaId, 'tab' => 'suivi'])],
    ];
    $content = tab_row($tabs, 'membres')
             . members_table('bacentas', $bacentaId, $b['nom'], count(get_members_of_bacenta($bacentaId)));

    // Section "Ajouter des membres" (membres actifs/vérifiés non affectés) —
    // affichée EN PLUS du formulaire d'ajout existant (members_table
    // ci-dessus), sans le remplacer. Réservée à l'admin ou au responsable
    // qui gère réellement ce bacenta (vérifié via RBAC, jamais un simple
    // masquage côté vue).
    if (can_manage_entity('bacenta', $bacentaId)) {
        $content .= bacenta_available_members_section($bacentaId);
    }

    render_page($b['nom'], $content);
}

/**
 * Section "Membres disponibles" : recherche + sélection multiple de
 * membres actifs, vérifiés, non affectés, pour affectation au bacenta
 * courant. La recherche est un simple aller-retour serveur (paramètre GET
 * "mq", isolé du reste de la navigation), l'affectation se fait par un
 * formulaire POST sécurisé (CSRF + revalidation serveur de chaque membre).
 */
function bacenta_available_members_section(int $bacentaId): string
{
    $search = trim((string) ($_GET['mq'] ?? ''));
    $members = bacenta_membership_service()->searchUnassigned($search);

    $rows = '';
    foreach ($members as $m) {
        $initial = h(mb_strtoupper(first_char(trim($m['prenom'] ?? '?'))));
        $rows .= '<label class="member-select-row">'
            . '<input type="checkbox" name="member_ids[]" value="' . (int) $m['id'] . '">'
            . '<span class="member-select-avatar">' . $initial . '</span>'
            . '<span class="member-select-info"><strong>' . h(full_name($m)) . '</strong>'
            . '<span>' . h($m['email'] ?? '') . ($m['telephone'] ? ' · ' . h($m['telephone']) : '') . '</span></span>'
            . '</label>';
    }
    $rows = $rows ?: '<div class="member-select-empty">' . empty_state('fa-user-check', 'Aucun membre disponible pour le moment.') . '</div>';

    $searchUrl = url('index.php', ['page' => 'bacentas', 'id' => $bacentaId, 'tab' => 'membres']);

    return '<div class="member-select-section">'
        . '<div class="section-toolbar"><div><h2>Ajouter des membres</h2>'
        . '<div class="sub">Membres actifs, vérifiés et non affectés à un bacenta</div></div></div>'
        . '<form method="get" action="index.php" class="member-select-search">'
        . '<input type="hidden" name="page" value="bacentas"><input type="hidden" name="id" value="' . $bacentaId . '"><input type="hidden" name="tab" value="membres">'
        . '<input type="search" name="mq" placeholder="Rechercher un membre (nom, prénom, email, téléphone)…" value="' . h($search) . '">'
        . '<button type="submit" class="btn btn-outline">Rechercher</button>'
        . ($search !== '' ? '<a class="btn btn-outline" href="' . h($searchUrl) . '">Effacer</a>' : '')
        . '</form>'
        . '<form method="post" action="index.php">'
        . '<input type="hidden" name="action" value="bacenta_assign_members">' . csrf_field()
        . '<input type="hidden" name="bacenta_id" value="' . $bacentaId . '">'
        . '<div class="member-select-list">' . $rows . '</div>'
        . '<div class="member-select-actions">'
        . '<span class="member-select-count">' . count($members) . ' membre(s) disponible(s)</span>'
        . '<button type="submit" class="btn btn-primary" ' . ($members ? '' : 'disabled') . '>Ajouter les membres sélectionnés</button>'
        . '</div></form></div>';
}

function render_centre_detail(int $centreId): void
{
    $c = get_centre($centreId);
    if (!$c) {
        redirect('index.php', ['page' => 'centres']);
    }
    // IDOR (spec §12/§40) : la section 'centres' peut être autorisée dans la
    // navigation sans que CE centre précis le soit — vérification par id
    // obligatoire, jamais seulement "la section est visible".
    if (!has_verified_access('centres', $centreId)) {
        render_gate('centres', $centreId, $c['nom']);
        return;
    }
    $tab = nav('tab');
    $tabs = [
        'bacentas' => ['label' => '<i class="fa-solid fa-church"></i> Bacentas', 'url' => url('index.php', ['page' => 'centres', 'id' => $centreId, 'tab' => 'bacentas'])],
        'suivi'    => ['label' => '<i class="fa-solid fa-sack-dollar"></i> Offrandes', 'url' => url('index.php', ['page' => 'centres', 'id' => $centreId, 'tab' => 'suivi'])],
    ];
    $tabRow = tab_row($tabs, $tab);

    if ($tab === 'suivi') {
        $monthKey = nav('semaine') ?? current_month_key();
        $offs = get_offrandes_month('centre', $centreId, $monthKey);
$content = $tabRow . view('pages/centre_offrandes', [
            'centre'      => $c,
            'monthKey'    => $monthKey,
            'monthLabel'  => month_label($monthKey),
            'monthOptions'=> build_month_options($monthKey),
            'offrandes'   => $offs,
            'monthTotal'  => array_sum($offs),
            'yearTotal'   => sum_offrandes_year_total('centre', $centreId, current_year()),
            'year'        => current_year(),
            'csrf'        => csrf_field(),
        ]);
        render_page($c['nom'], $content);
        return;
    }

    $cards = '';
    foreach (get_bacentas($centreId) as $b) {
        $cards .= bacenta_card_html($b, current_user()['role'] === 'admin' || auth_can_manage_bacenta((int) $b['id']));
    }
    $content = $tabRow . '<div class="card-grid">' . $cards . '</div>'
             . ($cards === '' ? empty_state('fa-church', 'Aucun bacenta dans ce centre pour le moment.') : '');
    render_page($c['nom'], $content);
}

function render_culte_detail(int $culteId): void
{
    $c = get_culte($culteId);
    if (!$c) {
        redirect('index.php', ['page' => 'cultes']);
    }
    if (!has_verified_access('cultes', $culteId)) {
        render_gate('cultes', $culteId, $c['nom']);
        return;
    }

    $culteMembers = Query::all("SELECT * FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant') ORDER BY prenom, nom");
    $tab = nav('tab');
    if ($tab === 'presences' || $tab === 'presences_annuel') {
        render_unit_presence_tab('cult', 'cultes', $c, $tab, $culteMembers);
        return;
    }
    $culteTabs = [
        'pointage'  => ['label' => '<i class="fa-solid fa-hands"></i> Pointage rapide', 'url' => url('index.php', ['page' => 'cultes', 'id' => $culteId])],
        'presences' => ['label' => '<i class="fa-solid fa-clipboard-check"></i> Présences', 'url' => url('index.php', ['page' => 'cultes', 'id' => $culteId, 'tab' => 'presences'])],
    ];

    $presents = get_members_of_culte($culteId);
    $isAdmin = current_user()['role'] === 'admin';
    // Tous les membres candidats au pointage (chevauchement de la liste).
    $candidates = Query::all("SELECT id, prenom, nom FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant') ORDER BY prenom, nom");

    $date = $c['date_culte'] ? date('d/m/Y', strtotime($c['date_culte'])) : 'Date à définir';
    $content = tab_row($culteTabs, 'pointage')
        . section_toolbar(h($c['nom']), 'Culte · ' . $date . ($c['resp_prenom'] ? ' · ' . h(trim($c['resp_prenom'] . ' ' . $c['resp_nom'])) : ''))
        . view('pages/culte_detail', [
            'culte'     => $c,
            'presents'  => $presents,
            'candidates'=> $candidates,
            'isAdmin'   => $isAdmin,
            'csrf'      => csrf_field(),
            'defaultDate' => date('Y-m-d'),
        ]);
    render_page($c['nom'], $content);
}

function render_basonta_detail(int $basontaId): void
{
    $b = get_basonta($basontaId);
    if (!$b) {
        redirect('index.php', ['page' => 'basontas']);
    }
    if (!has_verified_access('basontas', $basontaId)) {
        render_gate('basontas', $basontaId, $b['nom']);
        return;
    }
    $members = get_members_of_basonta($basontaId);

    $tab = nav('tab');
    if ($tab === 'presences' || $tab === 'presences_annuel') {
        render_unit_presence_tab('basonta', 'basontas', $b, $tab, $members);
        return;
    }
    $basontaTabs = [
        'membres'   => ['label' => '<i class="fa-solid fa-users"></i> Membres', 'url' => url('index.php', ['page' => 'basontas', 'id' => $basontaId])],
        'presences' => ['label' => '<i class="fa-solid fa-clipboard-check"></i> Présences', 'url' => url('index.php', ['page' => 'basontas', 'id' => $basontaId, 'tab' => 'presences'])],
    ];

    $candidates = _repo(MemberRepository::class)->candidatesForBasonta($basontaId);

    $rows = '';
    foreach ($members as $m) {
        $rows .= '<tr><td>' . h($m['nom'] ?? '') . '</td><td>' . h($m['prenom'] ?? '') . '</td><td>' . h($m['telephone'] ?? '') . '</td>'
            . '<td>' . presence_badge(presence_status($m, 'presenceBasonta')) . '</td>'
            . '<td class="row-actions"><a class="icon-btn danger" title="Retirer" data-confirm="Retirer ce membre du basonta ?" href="' . h(url('index.php', ['page' => 'basontas', 'action' => 'basonta_remove_member', 'basonta' => $basontaId, 'membre' => $m['id']])) . '"><i class="fa-solid fa-trash"></i></a></td></tr>';
    }
    $rows = $rows ?: '<tr><td colspan="5">' . empty_state('fa-inbox', 'Aucun membre dans ce basonta.') . '</td></tr>';

    $content = tab_row($basontaTabs, 'membres')
        . section_toolbar(h($b['nom']), count($members) . ' membre(s)')
        . '<form class="inline-add-form" method="post" action="index.php">'
        . '<input type="hidden" name="action" value="basonta_add_member">' . csrf_field()
        . '<input type="hidden" name="basonta" value="' . h($basontaId) . '">'
        . '<select name="membre" required><option value="">— Choisir un membre —</option>'
        . implode('', array_map(fn($u) => '<option value="' . $u['id'] . '">' . h(full_name($u)) . '</option>', $candidates))
        . '</select><button type="submit" class="btn btn-primary btn-sm">+ Ajouter au basonta</button></form>'
        . '<div class="table-wrap"><table class="data-table"><thead><tr><th>Nom</th><th>Prénom</th><th>Téléphone</th><th>Présence Basonta</th><th>Actions</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    render_page($b['nom'], $content);
}

/* ================= LISTES MEMBRES ================= */

function render_member_list_page(string $section): void
{
    if (!has_verified_access($section, null)) {
        render_gate($section, null, SECTION_LABELS[$section]);
        return;
    }

    $filter = trim((string) nav('q'));
    switch ($section) {
        case 'nouveaux':
            $members = get_nouveaux_members($filter);
            break;
        case 'bergers':
            $members = get_berger_members($filter);
            break;
        default:
            $members = get_generale_members($filter);
    }

    $content = members_table($section, null, SECTION_LABELS[$section], count($members), $members, $filter);
    render_page(SECTION_LABELS[$section], $content);
}

/* ================= TABLEAU DES MEMBRES ================= */

function display_columns(string $section): array
{
    $cols = ['nom', 'prenom', 'telephone', 'quartier', 'bacenta'];
    if (in_array($section, ['nouveaux', 'generale'], true)) {
        $cols[] = 'invite_par';
        $cols[] = 'recu_par';
        $cols[] = 'date_recu';
    }
    foreach (PRESENCE_FIELDS as $p) {
        $cols[] = $p;
    }
    return $cols;
}

function members_table(string $section, ?int $entityId, string $label, int $count, ?array $members = null, ?string $filter = null): string
{
    if ($members === null) {
        $ctx = get_members_context($section, $entityId);
        $members = $ctx['members'];
        $label = $ctx['label'] ?: $label;
        $count = count($members);
    }

    $bacentaNames = bacenta_name_map();
    $userNames = [];
    foreach ($members as $m) {
        if (!empty($m['invite_par'])) {
            $userNames[(int) $m['invite_par']] = null;
        }
        if (!empty($m['recu_par'])) {
            $userNames[(int) $m['recu_par']] = null;
        }
    }
    if ($userNames) {
        $userNames = _repo(MemberRepository::class)->namesForIds(array_keys($userNames));
    }

    $cols = display_columns($section);
    $headers = '';
    foreach ($cols as $f) {
        $headers .= '<th>' . h(FIELD_LABELS[$f] ?? $f) . '</th>';
    }
    $headers .= '<th>Actions</th>';

    $isAdmin = current_user()['role'] === 'admin';
    $rows = '';
    foreach ($members as $m) {
        $cells = '';
        foreach ($cols as $f) {
            if (in_array($f, PRESENCE_FIELDS, true)) {
                $cells .= '<td>' . presence_badge(presence_status($m, $f)) . '</td>';
                continue;
            }
            switch ($f) {
                case 'nom':
                case 'prenom':
                case 'telephone':
                case 'quartier':
                    $cells .= '<td>' . h($m[$f] ?? '') . '</td>';
                    break;
                case 'bacenta':
                    $cells .= '<td>' . h($m['bacenta_id'] ? ($bacentaNames[(int) $m['bacenta_id']] ?? '') : '') . '</td>';
                    break;
                case 'invite_par':
                    $cells .= '<td>' . h($m['invite_par'] ? ($userNames[(int) $m['invite_par']] ?? '') : '') . '</td>';
                    break;
                case 'recu_par':
                    $cells .= '<td>' . h($m['recu_par'] ? ($userNames[(int) $m['recu_par']] ?? '') : '') . '</td>';
                    break;
                case 'date_recu':
                    $cells .= '<td>' . h($m['date_recu'] ?? '') . '</td>';
                    break;
                default:
                    $cells .= '<td>' . h($m[$f] ?? '') . '</td>';
            }
        }
        $extra = '';
        if ($section === 'bergers') {
            $extra = '<a class="icon-btn" title="Fiche berger" href="' . h(url('index.php', ['page' => 'bergerFiche', 'membre' => $m['id']])) . '"><i class="fa-solid fa-clipboard-list"></i></a>'
                   . '<a class="icon-btn" title="Suivi hebdomadaire" href="' . h(url('index.php', ['page' => 'suiviBergers', 'membre' => $m['id']])) . '"><i class="fa-solid fa-calendar-days"></i></a>';
        } elseif ($section === 'suiviBergers') {
            $extra = '<a class="icon-btn" title="Suivi hebdomadaire" href="' . h(url('index.php', ['page' => 'suiviBergers', 'membre' => $m['id']])) . '"><i class="fa-solid fa-calendar-days"></i></a>';
        }
        $formParams = ['page' => $section, 'form' => 'membre', 'id' => $m['id']];
        if ($entityId) {
            $formParams['id_ent'] = $entityId;
        }
        $delParams = ['page' => $section, 'action' => 'delete_membre', 'id' => $m['id']];
        if ($entityId) {
            $delParams['id'] = $m['id'];
            $delParams['id_ent'] = $entityId;
        }
        $edit = '<a class="icon-btn" title="Modifier" href="' . h(url('index.php', $formParams)) . '"><i class="fa-solid fa-pen"></i></a>';
        $del = '<a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer ce membre ?" href="' . h(url('index.php', $delParams)) . '"><i class="fa-solid fa-trash"></i></a>';
        $rows .= '<tr>' . $cells . '<td class="row-actions">' . $extra . $edit . $del . '</td></tr>';
    }
    if ($members === []) {
        $rows = '<tr><td colspan="' . (count($cols) + 1) . '">' . empty_state('fa-inbox', 'Aucun membre pour le moment.') . '</td></tr>';
    }

    $addParams = ['page' => $section, 'form' => 'membre'];
    if ($entityId) {
        $addParams['id_ent'] = $entityId;
    }

    $filterBox = '';
    if (in_array($section, ['nouveaux', 'generale', 'bergers'], true)) {
        $filterBox = '<form method="get" action="index.php" class="filter-row">'
            . '<input type="hidden" name="page" value="' . h($section) . '">'
            . '<input type="search" class="search-input" name="q" placeholder="Filtrer (nom, quartier)…" value="' . h($filter ?? '') . '">'
            . '<button class="btn btn-outline btn-sm" type="submit">Filtrer</button>'
            . ($filter ? '<a class="btn btn-outline btn-sm" href="' . h(url('index.php', ['page' => $section])) . '">Effacer</a>' : '')
            . '</form>';
    }

    return $filterBox . '<div class="section-toolbar"><div><h2>' . h($label) . '</h2><div class="sub">' . $count . ' membre(s)</div></div>'
        . '<a class="btn btn-primary" href="' . h(url('index.php', $addParams)) . '">+ Ajouter un membre</a></div>'
        . '<div class="table-wrap"><table class="data-table"><thead><tr>' . $headers . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
}

/* ================= SUIVI BACENTA ================= */

function bacenta_suivi(array $b): string
{
    $monthKey = nav('semaine') ?? current_month_key();
    $visites = get_bacenta_visites((int) $b['id'], $monthKey);
    $offs = get_offrandes_month('bacenta', (int) $b['id'], $monthKey);

    return view('pages/bacenta_suivi', [
        'bacenta'      => $b,
        'monthKey'     => $monthKey,
        'monthLabel'   => month_label($monthKey),
        'monthOptions' => build_month_options($monthKey),
        'visites'      => $visites,
        'offrandes'    => $offs,
        'monthTotal'   => array_sum($offs),
        'yearTotal'    => sum_offrandes_year_total('bacenta', (int) $b['id'], current_year()),
        'year'         => current_year(),
        'csrf'         => csrf_field(),
    ]);
}

/* ================= PORTE D'ACCÈS ================= */

function render_gate(string $section, ?int $id, string $title): void
{
    $content = view('pages/access_gate', [
        'title' => $title,
        'page'  => $section,
        'id'    => $id,
    ]);
    render_page('Confirmez votre identité', $content);
}

/* ================= FORMULAIRES ================= */

function render_member_form(string $section): void
{
    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    $member = $id ? get_user($id) : null;
    $entityId = nav('id_ent');

    $bacentas = get_bacentas();
    $bacentaOptions = '';
    foreach ($bacentas as $b) {
        $bacentaOptions .= '<option value="' . $b['id'] . '"' . ($member && (int) $member['bacenta_id'] === (int) $b['id'] ? ' selected' : '') . '>' . h($b['nom'] . ' — ' . ($b['centre_nom'] ?? '')) . '</option>';
    }

    $allUsers = Query::all('SELECT id, prenom, nom FROM users ORDER BY prenom, nom');
    $userOptions = '';
    foreach ($allUsers as $u) {
        if ($member && (int) $u['id'] === (int) $member['id']) {
            continue;
        }
        $userOptions .= '<option value="' . $u['id'] . '"' . ($member && (int) $member['invite_par'] === (int) $u['id'] ? ' selected' : '') . '>' . h(full_name($u)) . '</option>';
    }
    $akwabaOptions = '';
    foreach ($allUsers as $u) {
        if ($member && (int) $u['id'] === (int) $member['id']) {
            continue;
        }
        $akwabaOptions .= '<option value="' . $u['id'] . '"' . ($member && (int) $member['recu_par'] === (int) $u['id'] ? ' selected' : '') . '>' . h(full_name($u)) . '</option>';
    }

    $presenceValues = [];
    if ($member) {
        foreach (PRESENCE_FIELDS as $f) {
            $presenceValues[$f] = presence_status($member, $f);
        }
    }

    $content = view('pages/forms/member', [
        'title'      => $member ? 'Modifier le membre' : 'Ajouter un membre',
        'member'     => $member,
        'section'    => $section,
        'isAdmin'    => current_user()['role'] === 'admin',
        'bacentaOptions' => $bacentaOptions,
        'userOptions'=> $userOptions,
        'akwabaOptions' => $akwabaOptions,
        'presenceValues' => $presenceValues,
        'extraFields'=> SECTION_EXTRA_FIELDS[$section] ?? [],
        'roles'      => ROLE_LABELS,
        'cancelUrl'  => url('index.php', ['page' => $section] + ($entityId ? ['id' => $entityId] : [])),
        'csrf'       => csrf_field(),
        'hidden'     => ($member ? '<input type="hidden" name="id" value="' . $member['id'] . '">' : '')
                        . '<input type="hidden" name="section" value="' . h($section) . '">'
                        . '<input type="hidden" name="id_ent" value="' . h($entityId) . '">'
                        . ($_GET['retour'] ?? '' ? '<input type="hidden" name="retour" value="fiche">' : ''),
    ]);
    render_page($member ? 'Modifier le membre' : 'Ajouter un membre', $content);
}

function render_bacenta_form(): void
{
    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    $b = $id ? get_bacenta($id) : null;
    $centres = get_centres();

    $centreOpts = '';
    foreach ($centres as $c) {
        $centreOpts .= '<option value="' . $c['id'] . '"' . ($b && (int) $b['centre_id'] === (int) $c['id'] ? ' selected' : '') . '>' . h($c['nom']) . '</option>';
    }

    // Le responsable n'est plus assignable depuis ce formulaire : c'est une
    // RESPONSABILITÉ (table `responsibilities`), gérée exclusivement depuis
    // Paramètres → Accès & Responsables (ROLE ≠ RESPONSABILITÉ, spec §29).
    $content = view('pages/forms/bacenta', [
        'bacenta'   => $b,
        'centreOpts'=> $centreOpts,
        'cancelUrl' => url('index.php', ['page' => 'bacentas']),
        'csrf'      => csrf_field(),
        'respUrl'   => url('index.php', ['page' => 'parametres', 'param_tab' => 'acces']),
    ]);
    render_page($b ? 'Modifier le bacenta' : 'Ajouter un bacenta', $content);
}

function render_centre_form(): void
{
    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    $c = $id ? get_centre($id) : null;
    $content = view('pages/forms/name', [
        'title'     => $c ? 'Modifier le centre' : 'Ajouter un centre',
        'action'    => 'save_centre',
        'name'      => $c['nom'] ?? '',
        'extra'     => csrf_field() . ($id ? '<input type="hidden" name="id" value="' . $id . '">' : ''),
        'cancelUrl' => url('index.php', ['page' => 'centres']),
    ]);
    render_page($c ? 'Modifier le centre' : 'Ajouter un centre', $content);
}

function render_culte_form(): void
{
    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    $c = $id ? get_culte($id) : null;
    // Le responsable de culte (pasteur/reverant uniquement — spec §24-25)
    // n'est plus assignable ici : voir Paramètres → Accès & Responsables.
    $content = view('pages/forms/culte', [
        'culte'     => $c,
        'cancelUrl' => url('index.php', ['page' => 'cultes']),
        'csrf'      => csrf_field(),
        'respUrl'   => url('index.php', ['page' => 'parametres', 'param_tab' => 'acces']),
    ]);
    render_page($c ? 'Modifier le culte' : 'Ajouter un culte', $content);
}

function render_basonta_form(): void
{
    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    $b = $id ? get_basonta($id) : null;
    $content = view('pages/forms/basonta', [
        'basonta'   => $b,
        'cancelUrl' => url('index.php', ['page' => 'basontas']),
        'csrf'      => csrf_field(),
    ]);
    render_page($b ? 'Modifier le basonta' : 'Ajouter un basonta', $content);
}
