<?php

/**
 * Compatibilité — page Paramètres (comptes + accès & responsabilités).
 * Portage de l'ancien pages_parametres.php, remanié pour le nouveau modèle
 * ROLE ≠ RESPONSABILITÉ ≠ PÉRIMÈTRE (voir docs/roles-and-permissions.md,
 * docs/responsibilities.md).
 */

declare(strict_types=1);

use App\Core\Query;

function render_parametres_page(): void
{
    // Accès réservé à l'admin — voir AuthorizationService (permission
    // 'users.manage', détenue uniquement par le rôle admin via '*').
    if (!auth_has_permission('users.manage')) {
        render_page(SECTION_LABELS['parametres'], empty_state('fa-ban', 'Accès réservé à l\'administrateur.'));
        return;
    }

    $form = nav('form');
    if ($form === 'user') {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $user = $id ? get_user($id) : null;
        $bacentas = get_bacentas();
        $bacentaOptions = '<option value="">— Aucun —</option>';
        foreach ($bacentas as $b) {
            $bacentaOptions .= '<option value="' . $b['id'] . '"' . ($user && (int) $user['bacenta_id'] === (int) $b['id'] ? ' selected' : '') . '>' . h($b['nom']) . '</option>';
        }
        $content = view('pages/forms/user', [
            'title'     => $user ? "Modifier l'utilisateur" : 'Ajouter un utilisateur',
            'user'      => $user,
            'bacentaOptions' => $bacentaOptions,
            'roles'     => ROLE_LABELS,
            'cancelUrl' => url('index.php', ['page' => 'parametres', 'param_tab' => 'comptes']),
            'csrf'      => csrf_field(),
            'responsibilitiesPanel' => $user ? user_responsibilities_panel($user) : '',
        ]);
        render_page('Paramètres', $content);
        return;
    }

    $tab = nav('param_tab');
    $tabs = [
        'comptes' => ['label' => '<i class="fa-solid fa-key"></i> Comptes application', 'url' => url('index.php', ['page' => 'parametres', 'param_tab' => 'comptes'])],
        'acces'   => ['label' => '<i class="fa-solid fa-id-card"></i> Accès & Responsables', 'url' => url('index.php', ['page' => 'parametres', 'param_tab' => 'acces'])],
    ];
    $tabRow = tab_row($tabs, $tab);

    $body = $tab === 'acces' ? parametres_acces() : parametres_comptes();
    render_page(SECTION_LABELS['parametres'], $tabRow . $body);
}

function parametres_comptes(): string
{
    $rows = '';
    foreach (get_users() as $u) {
        $role = ROLE_LABELS[$u['role']] ?? $u['role'];
        $badge = $u['role'] === 'admin' ? 'neutral' : ($u['role'] === 'membre' ? 'neutral' : 'present');
        $actif = (int) $u['compte_actif'] === 1 ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-xmark"></i>';
        $rows .= '<tr><td>' . h(full_name($u)) . '</td><td>' . h($u['email']) . '</td>'
            . '<td><span class="badge ' . $badge . '">' . h($role) . '</span></td>'
            . '<td>' . $actif . '</td>'
            . '<td class="row-actions">'
            . '<a class="icon-btn" title="Modifier" href="' . h(url('index.php', ['page' => 'parametres', 'form' => 'user', 'id' => $u['id']])) . '"><i class="fa-solid fa-pen"></i></a>'
            . '<a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer cet utilisateur ?" href="' . h(url('index.php', ['page' => 'parametres', 'action' => 'delete_user', 'id' => $u['id']])) . '"><i class="fa-solid fa-trash"></i></a>'
            . '</td></tr>';
    }
    $rows = $rows ?: '<tr><td colspan="5">' . empty_state('fa-inbox', 'Aucun utilisateur.') . '</td></tr>';

    return section_toolbar('Comptes application', 'Gérer les comptes (connexion par email)',
             add_button('Ajouter un utilisateur', ['page' => 'parametres', 'form' => 'user']))
         . '<div class="table-wrap"><table class="data-table"><thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Actif</th><th>Actions</th></tr></thead>'
         . '<tbody>' . $rows . '</tbody></table></div>';
}

/* ================= RESPONSABILITÉS (ROLE ≠ RESPONSABILITÉ) ================= */

const RESPONSIBILITY_TARGET_LABELS = [
    'center'  => ['singular' => 'centre',  'label' => 'Centre'],
    'bacenta' => ['singular' => 'bacenta', 'label' => 'Bacenta'],
    'cult'    => ['singular' => 'culte',   'label' => 'Culte'],
    'basonta' => ['singular' => 'basonta', 'label' => 'Basonta'],
];

/** Libellé "Prénom Nom (Rôle)" pour une ligne de responsabilité affichée. */
function responsibility_badge_html(array $row): string
{
    return '<span class="badge present" style="margin:2px 6px 2px 0;display:inline-flex;align-items:center;gap:6px;">'
        . h(trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? '')))
        . ' <small>(' . h(ROLE_LABELS[$row['role']] ?? $row['role']) . ')</small>'
        . ' <a class="icon-btn danger" style="width:20px;height:20px;" title="Retirer" data-confirm="Retirer cette responsabilité ?" href="'
        . h(url('index.php', ['action' => 'revoke_responsibility', 'rid' => $row['id']]))
        . '"><i class="fa-solid fa-xmark"></i></a></span>';
}

/** Bloc "Cible + responsables actuels + formulaire d'ajout" pour un type de cible donné. */
function responsibility_target_row_html(string $targetType, int $targetId, string $targetLabel): string
{
    $current = responsibility_service()->listForTarget($targetType, $targetId);
    $badges = '';
    foreach ($current as $row) {
        $badges .= responsibility_badge_html($row);
    }
    $badges = $badges ?: '<span class="sub">Aucun responsable</span>';

    $eligibleRoles = responsibility_service()->eligibleRoles($targetType);
    $placeholders = implode(',', array_fill(0, count($eligibleRoles), '?'));
    $candidates = Query::all("SELECT id, prenom, nom, role FROM users WHERE role IN ($placeholders) ORDER BY prenom, nom", $eligibleRoles);
    $alreadyIds = array_map(fn($r) => (int) $r['user_id'], $current);

    $options = '<option value="">— Ajouter un responsable —</option>';
    foreach ($candidates as $c) {
        if (in_array((int) $c['id'], $alreadyIds, true)) {
            continue;
        }
        $options .= '<option value="' . $c['id'] . '">' . h(full_name($c)) . ' (' . h(ROLE_LABELS[$c['role']] ?? $c['role']) . ')</option>';
    }

    $form = '<form method="post" action="index.php" class="inline-resp-form">'
        . '<input type="hidden" name="action" value="assign_responsibility">' . csrf_field()
        . '<input type="hidden" name="target_type" value="' . h($targetType) . '">'
        . '<input type="hidden" name="target_id" value="' . $targetId . '">'
        . '<select name="user_id" required>' . $options . '</select>'
        . '<button type="submit" class="btn btn-outline btn-sm">+ Ajouter</button></form>';

    return '<tr><td>' . h($targetLabel) . '</td><td>' . $badges . '</td><td>' . $form . '</td></tr>';
}

function parametres_acces(): string
{
    if (!auth_can_manage_responsibilities()) {
        return empty_state('fa-ban', 'Accès réservé à l\'administrateur.');
    }

    $html = '<p class="sub" style="margin-bottom:16px;">Une <strong>responsabilité</strong> est indépendante du <strong>rôle</strong> : un même rôle (berger, ms, pasteur…) peut être responsable de plusieurs structures, et une structure peut avoir plusieurs responsables. Voir <code>docs/responsibilities.md</code>.</p>';

    // Centres — NOUVEAU (n'existait pas avant ce remaniement).
    $centerRows = '';
    foreach (get_centres() as $c) {
        $centerRows .= responsibility_target_row_html('center', (int) $c['id'], $c['nom']);
    }
    $centerRows = $centerRows ?: '<tr><td colspan="3">' . empty_state('fa-inbox', 'Aucun centre.') . '</td></tr>';

    $bacRows = '';
    foreach (get_bacentas() as $b) {
        $label = $b['nom'] . ($b['centre_nom'] ? ' — ' . $b['centre_nom'] : '');
        $bacRows .= responsibility_target_row_html('bacenta', (int) $b['id'], $label);
    }
    $bacRows = $bacRows ?: '<tr><td colspan="3">' . empty_state('fa-inbox', 'Aucun bacenta.') . '</td></tr>';

    $culRows = '';
    foreach (get_cultes() as $c) {
        $culRows .= responsibility_target_row_html('cult', (int) $c['id'], $c['nom']);
    }
    $culRows = $culRows ?: '<tr><td colspan="3">' . empty_state('fa-inbox', 'Aucun culte.') . '</td></tr>';

    $basRows = '';
    foreach (get_basontas() as $b) {
        $basRows .= responsibility_target_row_html('basonta', (int) $b['id'], $b['nom']);
    }
    $basRows = $basRows ?: '<tr><td colspan="3">' . empty_state('fa-inbox', 'Aucun basonta.') . '</td></tr>';

    $section = function (string $title, string $sub, string $rows) {
        return '<div class="dash-section-title"><h2>' . h($title) . '</h2><span>' . h($sub) . '</span></div>'
            . '<div class="table-wrap"><table class="data-table"><thead><tr><th>Structure</th><th>Responsable(s)</th><th>Ajouter</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    };

    $html .= $section('Responsables de centres', 'Éligibles : Berger, MS, Pasteur', $centerRows)
        . $section('Responsables de bacentas', 'Éligibles : Berger, MS, Pasteur (hérite aussi du centre — voir périmètre)', $bacRows)
        . $section('Responsables de cultes', 'Éligibles : Pasteur, Révérend uniquement', $culRows)
        . $section('Responsables de basontas', 'Éligibles : Berger, MS, Pasteur', $basRows);

    return section_toolbar('Accès & Responsables', 'Responsables des centres, bacentas, cultes et basontas') . $html;
}

/**
 * Panneau "Responsabilités" affiché sur la fiche utilisateur (spec §32-33) —
 * clairement séparé du champ "Rôle" (jamais présenté comme un rôle).
 */
function user_responsibilities_panel(array $user): string
{
    $userId = (int) $user['id'];
    $current = responsibility_service()->listForUser($userId);

    $rows = '';
    foreach ($current as $row) {
        $targetType = $row['target_type'];
        $targetLabel = match ($targetType) {
            'center'  => Query::value('SELECT nom FROM centres WHERE id = ?', [$row['target_id']]),
            'bacenta' => Query::value('SELECT nom FROM bacentas WHERE id = ?', [$row['target_id']]),
            'cult'    => Query::value('SELECT nom FROM cultes WHERE id = ?', [$row['target_id']]),
            'basonta' => Query::value('SELECT nom FROM basontas WHERE id = ?', [$row['target_id']]),
            default   => '#' . $row['target_id'],
        };
        $typeLabel = RESPONSIBILITY_TARGET_LABELS[$targetType]['label'] ?? $targetType;
        $rows .= '<tr><td>' . h($typeLabel) . '</td><td>' . h((string) $targetLabel) . '</td>'
            . '<td class="row-actions"><a class="icon-btn danger" title="Retirer" data-confirm="Retirer cette responsabilité ?" href="'
            . h(url('index.php', ['action' => 'revoke_responsibility', 'rid' => $row['id'], 'return_to_user' => $userId]))
            . '"><i class="fa-solid fa-trash"></i></a></td></tr>';
    }
    $rows = $rows ?: '<tr><td colspan="3">' . empty_state('fa-inbox', 'Aucune responsabilité.') . '</td></tr>';

    // Formulaires d'ajout filtrés selon le rôle actuel de l'utilisateur
    // (spec §33 : "le formulaire doit filtrer automatiquement les
    // structures disponibles selon le rôle").
    $addForms = '';
    if (in_array($user['role'], CENTER_BACENTA_RESPONSIBILITY_ROLES, true)) {
        $addForms .= user_responsibility_add_form($userId, 'center', get_centres(), 'nom');
        $addForms .= user_responsibility_add_form($userId, 'bacenta', get_bacentas(), 'nom');
    }
    if (in_array($user['role'], CULT_RESPONSIBILITY_ROLES, true)) {
        $addForms .= user_responsibility_add_form($userId, 'cult', get_cultes(), 'nom');
    }
    if ($addForms === '') {
        $addForms = '<p class="sub">Le rôle actuel (' . h(ROLE_LABELS[$user['role']] ?? $user['role']) . ') ne permet de recevoir aucune responsabilité de structure.</p>';
    }

    return '<div class="form-card" style="margin-top:24px;">'
        . '<h3 style="margin-top:0;">Responsabilités <span class="sub">(distinctes du rôle — voir docs/responsibilities.md)</span></h3>'
        . '<div class="table-wrap"><table class="data-table"><thead><tr><th>Type</th><th>Structure</th><th>Actions</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
        . '<div style="margin-top:12px;display:flex;flex-direction:column;gap:8px;">' . $addForms . '</div>'
        . '</div>';
}

function user_responsibility_add_form(int $userId, string $targetType, array $targets, string $nameField): string
{
    $options = '<option value="">— ' . h(RESPONSIBILITY_TARGET_LABELS[$targetType]['label'] ?? $targetType) . ' à ajouter —</option>';
    foreach ($targets as $t) {
        $label = $t[$nameField] . (isset($t['centre_nom']) && $t['centre_nom'] ? ' — ' . $t['centre_nom'] : '');
        $options .= '<option value="' . (int) $t['id'] . '">' . h($label) . '</option>';
    }
    return '<form method="post" action="index.php" class="inline-resp-form">'
        . '<input type="hidden" name="action" value="assign_responsibility">' . csrf_field()
        . '<input type="hidden" name="target_type" value="' . h($targetType) . '">'
        . '<input type="hidden" name="user_id" value="' . $userId . '">'
        . '<select name="target_id" required>' . $options . '</select>'
        . '<button type="submit" class="btn btn-outline btn-sm">+ ' . h(RESPONSIBILITY_TARGET_LABELS[$targetType]['label'] ?? $targetType) . '</button></form>';
}
