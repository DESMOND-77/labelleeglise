<?php
/**
 * Page Paramètres : comptes applicatifs (users) + accès responsables
 * (bacentas / basontas / cultes).
 */

declare(strict_types=1);

require_once __DIR__ . '/render.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/auth.php';

function render_parametres_page(): void
{
    if (current_user()['role'] !== 'admin') {
        render_page(SECTION_LABELS['parametres'], empty_state('🚫', 'Accès réservé à l\'administrateur.'));
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
        $content = view('forms/user', [
            'title'     => $user ? "Modifier l'utilisateur" : 'Ajouter un utilisateur',
            'user'      => $user,
            'bacentaOptions' => $bacentaOptions,
            'roles'     => ROLE_LABELS,
            'cancelUrl' => url('index.php', ['page' => 'parametres', 'param_tab' => 'comptes']),
            'csrf'      => csrf_field(),
        ]);
        render_page('Paramètres', $content);
        return;
    }

    $tab = nav('param_tab');
    $tabs = [
        'comptes' => ['label' => '🔑 Comptes application', 'url' => url('index.php', ['page' => 'parametres', 'param_tab' => 'comptes'])],
        'acces'   => ['label' => '🪪 Accès & Responsables', 'url' => url('index.php', ['page' => 'parametres', 'param_tab' => 'acces'])],
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
        $actif = (int) $u['compte_actif'] === 1 ? '✔' : '✖';
        $rows .= '<tr><td>' . h(full_name($u)) . '</td><td>' . h($u['email']) . '</td>'
            . '<td><span class="badge ' . $badge . '">' . h($role) . '</span></td>'
            . '<td>' . h($actif) . '</td>'
            . '<td class="row-actions">'
            . '<a class="icon-btn" title="Modifier" href="' . h(url('index.php', ['page' => 'parametres', 'form' => 'user', 'id' => $u['id']])) . '">✎</a>'
            . '<a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer cet utilisateur ?" href="' . h(url('index.php', ['page' => 'parametres', 'action' => 'delete_user', 'id' => $u['id']])) . '">🗑</a>'
            . '</td></tr>';
    }
    $rows = $rows ?: '<tr><td colspan="5">' . empty_state('📭', 'Aucun utilisateur.') . '</td></tr>';

    return section_toolbar('Comptes application', 'Gérer les comptes (connexion par email)',
             add_button('Ajouter un utilisateur', ['page' => 'parametres', 'form' => 'user']))
         . '<div class="table-wrap"><table class="data-table"><thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Actif</th><th>Actions</th></tr></thead>'
         . '<tbody>' . $rows . '</tbody></table></div>';
}

function responsable_select(int $currentId, array $candidates): string
{
    $out = '<select name="user_id">';
    $out .= '<option value="0">— Aucun —</option>';
    foreach ($candidates as $u) {
        $out .= '<option value="' . $u['id'] . '"' . ($currentId === (int) $u['id'] ? ' selected' : '') . '>' . h(full_name($u)) . '</option>';
    }
    return $out . '</select>';
}

function parametres_acces(): string
{
    $candidates = qall("SELECT id, prenom, nom FROM users WHERE role IN ('responsable','leader','pasteur','reverant') ORDER BY prenom, nom");

    // Bacentas
    $bacRows = '';
    foreach (get_bacentas() as $b) {
        $bacRows .= '<tr><td>' . h($b['nom']) . '</td><td>' . h($b['centre_nom'] ?? '') . '</td><td>'
            . '<form method="post" action="index.php" class="inline-resp-form">'
            . '<input type="hidden" name="action" value="save_responsable">' . csrf_field()
            . '<input type="hidden" name="type" value="bacenta"><input type="hidden" name="id" value="' . $b['id'] . '">'
            . responsable_select($b['responsable_id'] ? (int) $b['responsable_id'] : 0, $candidates)
            . '<button type="submit" class="btn btn-outline btn-sm">OK</button></form></td></tr>';
    }
    $bacRows = $bacRows ?: '<tr><td colspan="3">' . empty_state('📭', 'Aucun bacenta.') . '</td></tr>';

    // Basontas
    $basRows = '';
    foreach (get_basontas() as $b) {
        $basRows .= '<tr><td>' . h($b['nom']) . '</td><td>'
            . '<form method="post" action="index.php" class="inline-resp-form">'
            . '<input type="hidden" name="action" value="save_responsable">' . csrf_field()
            . '<input type="hidden" name="type" value="basonta"><input type="hidden" name="id" value="' . $b['id'] . '">'
            . responsable_select($b['responsable_id'] ? (int) $b['responsable_id'] : 0, $candidates)
            . '<button type="submit" class="btn btn-outline btn-sm">OK</button></form></td></tr>';
    }
    $basRows = $basRows ?: '<tr><td colspan="2">' . empty_state('📭', 'Aucun basonta.') . '</td></tr>';

    // Cultes
    $culRows = '';
    foreach (get_cultes() as $c) {
        $culRows .= '<tr><td>' . h($c['nom']) . '</td><td>'
            . '<form method="post" action="index.php" class="inline-resp-form">'
            . '<input type="hidden" name="action" value="save_responsable">' . csrf_field()
            . '<input type="hidden" name="type" value="culte"><input type="hidden" name="id" value="' . $c['id'] . '">'
            . responsable_select($c['responsable_id'] ? (int) $c['responsable_id'] : 0, $candidates)
            . '<button type="submit" class="btn btn-outline btn-sm">OK</button></form></td></tr>';
    }
    $culRows = $culRows ?: '<tr><td colspan="2">' . empty_state('📭', 'Aucun culte.') . '</td></tr>';

    $html = '<div class="dash-section-title"><h2>Responsables de bacentas</h2><span>Attribuez le responsable de chaque bacenta</span></div>'
        . '<div class="table-wrap"><table class="data-table"><thead><tr><th>Bacenta</th><th>Centre</th><th>Responsable</th></tr></thead><tbody>' . $bacRows . '</tbody></table></div>'
        . '<div class="dash-section-title"><h2>Responsables de basontas</h2><span>Ministères et départements</span></div>'
        . '<div class="table-wrap"><table class="data-table"><thead><tr><th>Basonta</th><th>Responsable</th></tr></thead><tbody>' . $basRows . '</tbody></table></div>'
        . '<div class="dash-section-title"><h2>Responsables de cultes</h2><span>Cultes et réunions</span></div>'
        . '<div class="table-wrap"><table class="data-table"><thead><tr><th>Culte</th><th>Responsable</th></tr></thead><tbody>' . $culRows . '</tbody></table></div>';

    return section_toolbar('Accès & Responsables', 'Responsables des bacentas, basontas et cultes') . $html;
}
