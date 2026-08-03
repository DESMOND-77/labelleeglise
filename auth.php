<?php
/**
 * Authentification, session et RBAC — nouveau modèle de données.
 *
 *  - Connexion par email + mot de passe (table users, compte_actif = 1).
 *  - admin            : accès complet.
 *  - responsable      : cantonné aux bacentas/cultes/basontas dont il est
 *                       responsable (responsable_id) + leurs centres.
 *  - leader/pasteur/reverant ("berger") : sa fiche, son suivi hebdo et son
 *                       bacenta (users.bacenta_id).
 *  - membre/assistant : pages publiques uniquement.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('LBEGF_SESSID');
        session_start();
    }
}

/* ------------------------------------------------------------------ */
/* Comptes                                                             */
/* ------------------------------------------------------------------ */

function find_account(string $email, string $password): ?array
{
    $email = mb_lower(trim($email));
    $u = qone('SELECT * FROM users WHERE LOWER(email) = ?', [$email]);
    if (!$u || (int) $u['compte_actif'] !== 1) {
        return null;
    }
    if (!password_verify($password, $u['password'])) {
        return null;
    }
    return [
        'type' => 'app',
        'id'   => (int) $u['id'],
        'name' => trim($u['prenom'] . ' ' . $u['nom']),
        'role' => $u['role'],
        'email'=> $u['email'],
    ];
}

/** Vérifie des identifiants (email OU nom/prénom + mot de passe) — porte d'accès. */
function verify_credentials(string $name, string $password): bool
{
    $n = mb_lower(trim($name));
    $u = qone(
        "SELECT * FROM users WHERE LOWER(email) = ? OR LOWER(CONCAT(prenom, ' ', nom)) = ? OR LOWER(CONCAT(nom, ' ', prenom)) = ?",
        [$n, $n, $n]
    );
    if (!$u || (int) $u['compte_actif'] !== 1) {
        return false;
    }
    return password_verify($password, $u['password']);
}

function login(string $email, string $password): bool
{
    $account = find_account($email, $password);
    if (!$account) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => $account['id']];
    return true;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

function current_user(): ?array
{
    static $user = null;
    static $loaded = false;
    if (!$loaded) {
        $loaded = true;
        $id = $_SESSION['user']['id'] ?? null;
        $user = $id ? qone('SELECT * FROM users WHERE id = ?', [(int) $id]) : null;
    }
    return $user;
}

/* ------------------------------------------------------------------ */
/* RBAC                                                                */
/* ------------------------------------------------------------------ */

/** Bacentas dont l'utilisateur est responsable (si rôle responsable). */
function my_bacenta_ids(int $userId): array
{
    $rows = qall('SELECT id FROM bacentas WHERE responsable_id = ?', [$userId]);
    return array_map(fn($r) => (int) $r['id'], $rows);
}

/**
 * Périmètre du compte courant :
 *  - null                   : accès complet (admin) ou compte public (membre/assistant)
 *  - kind = 'responsable'   : bacentas / cultes / basontas dont il est responsable
 *  - kind = 'berger'        : fiche + suivi + son bacenta (users.bacenta_id)
 */
function get_user_scope(): ?array
{
    $u = current_user();
    if (!$u) {
        return null;
    }
    if ($u['role'] === 'admin') {
        return null;
    }
    if ($u['role'] === 'responsable') {
        return [
            'kind' => 'responsable',
            'bacentas' => my_bacenta_ids((int) $u['id']),
        ];
    }
    if (in_array($u['role'], BERGER_ROLES, true)) {
        return [
            'kind' => 'berger',
            'user_id' => (int) $u['id'],
            'bacenta_id' => $u['bacenta_id'] ? (int) $u['bacenta_id'] : null,
        ];
    }
    return null; // membre / assistant : pages publiques uniquement
}

function is_berger_scope_locked(): bool
{
    $scope = get_user_scope();
    return (bool) ($scope && $scope['kind'] === 'berger');
}

/** Sections autorisées : null = aucune restriction (admin). */
function get_allowed_sections(): ?array
{
    $u = current_user();
    if (!$u) {
        return null;
    }
    if ($u['role'] === 'admin') {
        return null;
    }
    $scope = get_user_scope();
    if (!$scope) {
        return ['apropos', 'centresPresentation'];
    }
    if ($scope['kind'] === 'berger') {
        $sections = ['apropos', 'centresPresentation', 'bergerFiche', 'suiviBergers'];
        if ($scope['bacenta_id']) {
            $sections[] = 'bacentas';
        }
        return $sections;
    }
    // responsable
    return ['apropos', 'centresPresentation', 'bacentas', 'centres', 'cultes', 'basontas'];
}

/** true si l'utilisateur peut gérer l'entité (bacenta/culte/basonta) demandée. */
function can_manage_entity(string $type, int $id): bool
{
    $u = current_user();
    if (!$u || $u['role'] === 'admin') {
        return $u !== null;
    }
    $scope = get_user_scope();
    if (!$scope) {
        return false;
    }
    if ($scope['kind'] === 'berger') {
        return $type === 'bacenta' && $scope['bacenta_id'] === $id;
    }
    if ($scope['kind'] === 'responsable') {
        if ($type === 'bacenta') {
            return in_array($id, $scope['bacentas'], true);
        }
        $table = $type . 's'; // cultes / basontas
        $row = qone("SELECT id FROM $table WHERE id = ? AND responsable_id = ?", [$id, $u['id']]);
        return (bool) $row;
    }
    return false;
}

/** Navigation par défaut du compte lié. */
function scope_target(): ?array
{
    $u = current_user();
    if (!$u) {
        return null;
    }
    if ($u['role'] === 'admin') {
        return null;
    }
    $scope = get_user_scope();
    if (!$scope) {
        return null;
    }
    if ($scope['kind'] === 'berger') {
        return ['page' => 'bergerFiche', 'membre' => $u['id']];
    }
    $bacentas = $scope['bacentas'];
    if ($bacentas) {
        return ['page' => 'bacentas', 'id' => $bacentas[0]];
    }
    return ['page' => 'accueil'];
}

/* ------------------------------------------------------------------ */
/* Porte d'accès (authentification secondaire d'une liste)             */
/* ------------------------------------------------------------------ */

function access_key(string $section, $id = null): string
{
    return $section . ':' . ($id ?? '');
}

function has_verified_access(string $section, $id = null): bool
{
    $u = current_user();
    if (!$u) {
        return false;
    }
    if ($u['role'] === 'admin') {
        return true;
    }
    // Le Responsable / Berger n'est jamais re-sollicité sur SON périmètre.
    $scope = get_user_scope();
    if ($scope && $scope['kind'] === 'berger' && $section === 'bacentas' && $scope['bacenta_id'] === (int) $id) {
        return true;
    }
    if ($scope && $scope['kind'] === 'responsable') {
        if (($section === 'bacentas' || $section === 'cultes' || $section === 'basontas') && can_manage_entity(($section === 'bacentas') ? 'bacenta' : $section, (int) $id)) {
            return true;
        }
    }
    return in_array(access_key($section, $id), $_SESSION['verified'] ?? [], true);
}

function grant_access(string $section, $id = null): void
{
    $_SESSION['verified'][] = access_key($section, $id);
}
