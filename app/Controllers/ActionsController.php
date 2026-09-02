<?php

namespace App\Controllers;

use App\Core\Query;
use App\Core\Request;
use App\Repositories\CMSRepository;

/**
 * Gestionnaire central des actions (POST et suppressions).
 * Chaque action se termine par une redirection (comportement identique à
 * l'ancien actions.php).
 *
 * IMPORTANT — contrôle serveur (spec §34, §40, §42) : `getAction()` est
 * dispatché par index.php AVANT la vérification de session (voir front
 * controller), donc CHAQUE case doit revérifier explicitement
 * current_user() + l'autorisation réelle (rôle + permission + responsabilité
 * + périmètre) — jamais seulement un bouton masqué côté vue.
 */
class ActionsController extends Controller
{
    /** Redirige un accès refusé (même convention que AdminMiddleware). */
    private function deny(): never
    {
        $this->redirect('index.php', ['page' => 'apropos']);
    }

    private function requireAdmin(): array
    {
        $user = current_user();
        if (!$user || $user['role'] !== 'admin') {
            $this->deny();
        }
        return $user;
    }

    private function requireUser(): array
    {
        $user = current_user();
        if (!$user) {
            $this->deny();
        }
        return $user;
    }

    /** Jours de récurrence soumis (cases) → CSV filtré sur WEEK_DAYS, ou null. */
    private function scheduleDaysFromPost(): ?string
    {
        $days = array_values(array_intersect(WEEK_DAYS, (array) ($_POST['jours_semaine'] ?? [])));
        return $days ? implode(',', $days) : null;
    }

    /** Suppressions / déconnexions passées en GET (?action=…). */
    public function getAction(): void
    {
        $action = $_GET['action'] ?? null;

        switch ($action) {
            case 'logout':
                logout();
                $this->redirect('index.php');
                break;

            case 'delete_centre':
                $this->requireAdmin();
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    delete_centre($id);
                }
                $this->redirect('index.php', ['page' => 'centres']);
                break;

            case 'delete_bacenta':
                $this->requireAdmin();
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    delete_bacenta($id);
                }
                $this->redirect('index.php', ['page' => 'bacentas']);
                break;

            /* ---------- Calendriers (M4) ---------- */

            case 'delete_evenement': {
                $this->requireUser();
                $id = (int) ($_GET['id'] ?? 0);
                $evt = $id ? calendrier_service()->event($id) : null;
                if (!$evt || !auth_can_edit_evenement($evt)) {
                    $this->deny();
                }
                calendrier_service()->deleteEvent($id);
                $this->redirect('index.php', ['page' => 'calendrier']);
                break;
            }

            case 'delete_anniversaire': {
                $this->requireUser();
                if (!auth_can_manage_calendar()) {
                    $this->deny();
                }
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    calendrier_service()->deleteBirthday($id);
                }
                $this->redirect('index.php', ['page' => 'anniversaires']);
                break;
            }

            case 'delete_culte':
                $this->requireAdmin();
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    delete_culte($id);
                }
                $this->redirect('index.php', ['page' => 'cultes']);
                break;

            case 'delete_basonta':
                $this->requireAdmin();
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    delete_basonta($id);
                }
                $this->redirect('index.php', ['page' => 'basontas']);
                break;

            case 'delete_membre':
                $this->requireUser();
                $id = (int) ($_GET['id'] ?? 0);
                $section = (string) nav('page');
                // IDOR (spec §12/§40) : remonte la chaîne membre → bacenta →
                // centre côté serveur ; jamais de confiance dans l'id seul.
                if ($id && auth_can_manage_member($id)) {
                    delete_user($id);
                }
                redirect_members_context($section);
                break;

            case 'delete_examen':
            case 'delete_veillee': {
                $user = $this->requireUser();
                $membre = (int) ($_GET['membre'] ?? 0);
                $id = (int) ($_GET['id'] ?? 0);
                // Fiche berger : uniquement SA propre fiche (spec §20), sauf admin.
                $allowed = $membre && $id && ($user['role'] === 'admin' || $membre === (int) $user['id']);
                if ($allowed) {
                    if ($action === 'delete_examen') {
                        delete_examen($membre, $id);
                    } else {
                        delete_veillee($membre, $id);
                    }
                }
                $this->redirect('index.php', ['page' => 'bergerFiche', 'membre' => $membre, 'tab' => $action === 'delete_examen' ? 'examens' : 'veillees']);
                break;
            }

            case 'delete_user':
                $this->requireAdmin();
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    delete_user($id);
                }
                $this->redirect('index.php', ['page' => 'parametres', 'param_tab' => 'comptes']);
                break;

            case 'delete_equipe':
                $this->requireAdmin();
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    delete_equipe_record($id);
                }
                $this->redirect('index.php', ['page' => 'apropos']);
                break;

            case 'delete_article':
                $this->requireAdmin();
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    delete_article_record($id);
                }
                $this->redirect('index.php', ['page' => 'centresPresentation']);
                break;

            case 'basonta_remove_member':
                $this->requireUser();
                $basonta = (int) ($_GET['basonta'] ?? 0);
                $membre = (int) ($_GET['membre'] ?? 0);
                if ($basonta && $membre && (current_user()['role'] === 'admin' || auth_can_manage_basonta($basonta))) {
                    basonta_remove_member($basonta, $membre);
                }
                $this->redirect('index.php', ['page' => 'basontas', 'id' => $basonta]);
                break;

            case 'revoke_responsibility':
                $this->requireAdmin();
                if (!auth_can_manage_responsibilities()) {
                    $this->deny();
                }
                $id = (int) ($_GET['rid'] ?? $_GET['id'] ?? 0);
                if ($id) {
                    responsibility_service()->revokeById($id);
                }
                $returnToUser = (int) ($_GET['return_to_user'] ?? 0);
                if ($returnToUser) {
                    $this->redirect('index.php', ['page' => 'parametres', 'form' => 'user', 'id' => $returnToUser]);
                }
                $this->redirect('index.php', ['page' => 'parametres', 'param_tab' => 'acces']);
                break;

            case 'notification_mark_read':
                $user = current_user();
                $id = (int) ($_GET['id'] ?? 0);
                if ($user && $id) {
                    notification_service()->markRead($id, (int) $user['id']);
                }
                $this->redirect('index.php', ['page' => 'notifications']);
                break;

            case 'notification_mark_all_read':
                $user = current_user();
                if ($user) {
                    notification_service()->markAllRead((int) $user['id']);
                }
                $this->redirect('index.php', ['page' => 'notifications']);
                break;

            case 'export_attendance': {
                // Export CSV réel (spec §27) : gate identique à la fiche/aux
                // impressions — soi-même toujours autorisé, sinon
                // canManageMember() (admin bypass inclus). GET volontairement
                // non protégé CSRF, comme le reste des cases getAction()
                // existantes (convention préexistante de l'app, hors périmètre).
                $user = $this->requireUser();
                $membre = (int) ($_GET['membre'] ?? $user['id']);
                if ($membre !== (int) $user['id'] && !auth_can_manage_member($membre)) {
                    $this->deny();
                }
                $member = get_user($membre);
                if (!$member) {
                    $this->deny();
                }
                $from = trim((string) ($_GET['from'] ?? '')) ?: null;
                $to = trim((string) ($_GET['to'] ?? '')) ?: null;
                $rows = attendance_service()->historyForUser($membre, $from, $to);
                export_service()->attendanceCsv($member, $rows);
                break;
            }

            case 'notification_open':
                // Marque la notification comme lue puis redirige vers la
                // ressource concernée (fiche d'inscription…). Le lien est
                // toujours celui stocké en base côté serveur, jamais fourni
                // librement par le client.
                $user = current_user();
                $id = (int) ($_GET['id'] ?? 0);
                $link = 'index.php?page=notifications';
                if ($user && $id) {
                    $notif = notification_service()->find($id);
                    if ($notif && (int) $notif['recipient_id'] === (int) $user['id']) {
                        notification_service()->markRead($id, (int) $user['id']);
                        if (!empty($notif['link'])) {
                            $link = $notif['link'];
                        }
                    }
                }
                header('Location: ' . $link);
                exit;
        }
    }

    /** Toutes les écritures POST. */
    public function postAction(): void
    {
        $action = $_POST['action'] ?? '';
        check_csrf();

        switch ($action) {
            case 'login':
                $email = (string) ($_POST['email'] ?? '');
                $password = (string) ($_POST['password'] ?? '');
                $result = attempt_login($email, $password);
                if ($result['ok']) {
                    login($email, $password);
                    $target = scope_target();
                    $this->redirect('index.php', $target ?: ['page' => 'accueil']);
                }
                $this->redirect('index.php', ['error' => $result['reason']]);
                break;

            case 'verify_access':
                $ok = verify_credentials((string) ($_POST['name'] ?? ''), (string) ($_POST['password'] ?? ''));
                $page = (string) ($_POST['page'] ?? '');
                $id = (int) ($_POST['id'] ?? 0);
                if ($ok) {
                    grant_access($page, $id ?: null);
                }
                $params = ['page' => $page, 'id' => $id ?: null, 'gate' => $ok ? 0 : 1];
                if (!$ok) {
                    $params['error'] = 1;
                }
                $this->redirect('index.php', $params);
                break;

            /* ---------- CRUD structure ---------- */

            case 'save_centre':
                // Création/renommage de centre : réservé à l'admin (aucune
                // notion de "propriétaire" pour une structure racine).
                $this->requireAdmin();
                $id = (int) ($_POST['id'] ?? 0);
                $nom = trim((string) ($_POST['nom'] ?? ''));
                if ($nom !== '') {
                    save_centre($id ?: null, $nom);
                }
                $this->redirect('index.php', ['page' => 'centres']);
                break;

            case 'save_bacenta': {
                $user = $this->requireUser();
                $id = (int) ($_POST['id'] ?? 0);
                $nom = trim((string) ($_POST['nom'] ?? ''));
                $centreId = (int) ($_POST['centre_id'] ?? 0) ?: null;
                // Création : admin uniquement. Modification : admin OU
                // responsable (direct/hérité du centre) de cette bacenta.
                $authorized = $id ? auth_can_manage_bacenta($id) : ($user['role'] === 'admin');
                if (!$authorized) {
                    $this->deny();
                }
                if ($nom !== '') {
                    $jours = $this->scheduleDaysFromPost();
                    $debut = trim((string) ($_POST['heure_debut'] ?? '')) ?: null;
                    $fin   = trim((string) ($_POST['heure_fin'] ?? '')) ?: null;
                    save_bacenta($id ?: null, $nom, $centreId, null, $jours, $debut, $fin);
                }
                $this->redirect('index.php', ['page' => 'bacentas']);
                break;
            }

            case 'save_culte': {
                $user = $this->requireUser();
                $id = (int) ($_POST['id'] ?? 0);
                $nom = trim((string) ($_POST['nom'] ?? ''));
                $date = trim((string) ($_POST['date_culte'] ?? '')) ?: null;
                $debut = trim((string) ($_POST['heure_debut'] ?? '')) ?: null;
                $fin = trim((string) ($_POST['heure_fin'] ?? '')) ?: null;
                // Création : admin uniquement. Modification : admin OU
                // pasteur/reverant responsable de CE culte précis (spec §26).
                $authorized = $id ? auth_can_manage_culte($id) : ($user['role'] === 'admin');
                if (!$authorized) {
                    $this->deny();
                }
                if ($nom !== '') {
                    save_culte($id ?: null, $nom, $date, $debut, $fin, null, $this->scheduleDaysFromPost());
                }
                $this->redirect('index.php', ['page' => 'cultes']);
                break;
            }

            case 'save_basonta': {
                $user = $this->requireUser();
                $id = (int) ($_POST['id'] ?? 0);
                $nom = trim((string) ($_POST['nom'] ?? ''));
                $authorized = $id ? auth_can_manage_basonta($id) : ($user['role'] === 'admin');
                if (!$authorized) {
                    $this->deny();
                }
                if ($nom !== '') {
                    $jours = $this->scheduleDaysFromPost();
                    $debut = trim((string) ($_POST['heure_debut'] ?? '')) ?: null;
                    $fin   = trim((string) ($_POST['heure_fin'] ?? '')) ?: null;
                    save_basonta($id ?: null, $nom, null, $jours, $debut, $fin);
                }
                $this->redirect('index.php', ['page' => 'basontas']);
                break;
            }

            /* ---------- Mon profil (libre-service — spec §1-13) ---------- */

            case 'save_profile': {
                // Auto-ciblage exclusif sur l'utilisateur connecté : jamais
                // d'id "membre" lu depuis la requête pour cette action (spec
                // §1(a)) — un membre ne peut modifier QUE son propre profil.
                $user = $this->requireUser();
                $svc = profile_service();
                $errors = $svc->validatePersonalInfo($_POST);
                if ($errors) {
                    $this->redirect('index.php', ['page' => 'profile', 'psection' => 'info', 'perror' => 'validation']);
                }
                $svc->updatePersonalInfo((int) $user['id'], $_POST);
                $photoResult = $svc->updatePhoto($user, 'photo');
                if (!$photoResult['ok']) {
                    $this->redirect('index.php', ['page' => 'profile', 'psection' => 'info', 'perror' => $photoResult['error']]);
                }
                $this->redirect('index.php', ['page' => 'profile', 'psection' => 'info', 'psaved' => 1]);
                break;
            }

            case 'change_password': {
                $user = $this->requireUser();
                $result = profile_service()->changePassword(
                    $user,
                    (string) ($_POST['current_password'] ?? ''),
                    (string) ($_POST['new_password'] ?? ''),
                    (string) ($_POST['new_password_confirm'] ?? '')
                );
                if (!$result['ok']) {
                    $this->redirect('index.php', ['page' => 'profile', 'psection' => 'security', 'perror' => $result['error']]);
                }
                // Défense en profondeur (spec §16) : régénère l'identifiant de
                // session courante après un changement de mot de passe.
                \App\Core\Session::regenerate();
                $this->redirect('index.php', ['page' => 'profile', 'psection' => 'security', 'psaved' => 1]);
                break;
            }

            case 'request_email_change': {
                // Spec §12 : la déconnexion a lieu ICI, à la DEMANDE, avant
                // même que le lien de vérification soit cliqué — jamais au
                // moment de la vérification.
                $user = $this->requireUser();
                $newEmail = trim((string) ($_POST['new_email'] ?? ''));
                $result = email_change_service()->requestChange($user, $newEmail);
                if (!$result['ok']) {
                    $this->redirect('index.php', ['page' => 'profile', 'psection' => 'email', 'perror' => $result['error']]);
                }
                logout();
                $this->redirect('index.php', ['page' => 'email_change_pending']);
                break;
            }

            /* ---------- CRUD membre (users) ---------- */

            case 'save_membre': {
                $user = $this->requireUser();
                $id = (int) ($_POST['id'] ?? 0);
                $section = (string) ($_POST['section'] ?? 'generale');
                $entityId = (int) ($_POST['id_ent'] ?? 0) ?: null;

                // Contrôle serveur (spec §34/§40) : jamais confiance dans le
                // fait que le lien "modifier"/"ajouter" n'était pas visible.
                if ($id) {
                    if ($user['role'] !== 'admin' && !auth_can_manage_member($id)) {
                        $this->deny();
                    }
                } else {
                    $createAuthorized = $user['role'] === 'admin'
                        || ($section === 'bacentas' && $entityId && auth_can_manage_bacenta($entityId))
                        || ($section === 'centres' && $entityId && auth_can_manage_center($entityId));
                    if (!$createAuthorized) {
                        $this->deny();
                    }
                }

                $data = user_data_from_post($id ?: null);
                $photo = handle_photo_upload('photo');

                if ($data['email'] === '' || $data['nom'] === '') {
                    redirect_members_context($section);
                }
                if (email_taken($data['email'], $id ?: null)) {
                    $this->redirect('index.php', [
                        'page' => $section,
                        'form' => 'membre',
                        'id' => $id ?: null,
                        'id_ent' => (int) ($_POST['id_ent'] ?? 0) ?: null,
                        'error' => 1,
                    ]);
                }

                if ($id) {
                    $newPass = trim((string) ($_POST['password'] ?? ''));
                    update_user_from_post($id, $data, $photo, $newPass !== '' ? $newPass : null);
                } else {
                    $id = insert_user_from_post($data, $photo);
                    if ($section === 'bacentas' && $entityId) {
                        Query::run('UPDATE users SET bacenta_id = ? WHERE id = ?', [$entityId, $id]);
                    } elseif ($section === 'centres' && $entityId) {
                        $first = Query::value('SELECT id FROM bacentas WHERE centre_id = ? ORDER BY id LIMIT 1', [$entityId]);
                        if ($first) {
                            Query::run('UPDATE users SET bacenta_id = ? WHERE id = ?', [(int) $first, $id]);
                        }
                    } elseif ($section === 'basontas' && $entityId) {
                        Query::run('INSERT INTO users_basontas (user_id, basonta_id) VALUES (?, ?)', [$id, $entityId]);
                    } elseif ($section === 'cultes' && $entityId) {
                        Query::run('INSERT INTO presences (user_id, date_presence, culte_id) VALUES (?, CURDATE(), ?)', [$id, $entityId]);
                    }
                }

                foreach (PRESENCE_FIELDS as $f) {
                    if (isset($_POST[$f])) {
                        save_quick_presence($id, $f, (string) $_POST[$f]);
                    }
                }

                if (($_POST['retour'] ?? '') === 'fiche') {
                    $this->redirect('index.php', ['page' => 'bergerFiche', 'membre' => $id]);
                }
                redirect_members_context($section, $entityId);
                break;
            }

            case 'save_user': {
                $this->requireAdmin();
                $id = (int) ($_POST['id'] ?? 0);
                $data = user_data_from_post($id ?: null);
                // Le rôle soumis doit être un rôle actif réellement
                // sélectionnable (jamais 'responsable', jamais une valeur
                // arbitraire) — voir ROLE_LABELS (Config/constants.php).
                $submittedRole = (string) ($_POST['role'] ?? 'membre');
                $data['role'] = array_key_exists($submittedRole, ROLE_LABELS) ? $submittedRole : 'membre';

                if ($data['email'] === '' || $data['nom'] === '' || (($_POST['password'] ?? '') === '' && !$id)) {
                    $this->redirect('index.php', ['page' => 'parametres', 'param_tab' => 'comptes']);
                }
                if (email_taken($data['email'], $id ?: null)) {
                    $this->redirect('index.php', ['page' => 'parametres', 'form' => 'user', 'id' => $id ?: null, 'error' => 1]);
                }
                $newPass = trim((string) ($_POST['password'] ?? ''));
                if ($id) {
                    update_user_from_post($id, $data, null, $newPass !== '' ? $newPass : null);
                    // §31 — changement de rôle : révoque toute responsabilité
                    // devenue incohérente avec le nouveau rôle (choix
                    // documenté : auto-révocation + journalisation, voir
                    // ResponsibilityService::reconcileForNewRole).
                    $revoked = responsibility_service()->reconcileForNewRole($id, $data['role']);
                    if ($revoked) {
                        \App\Core\Logger::info('Rôle modifié : responsabilités révoquées (incohérentes)', [
                            'user_id' => $id, 'new_role' => $data['role'], 'revoked' => $revoked,
                        ]);
                    }
                } else {
                    insert_user_from_post($data, null);
                }
                $this->redirect('index.php', ['page' => 'parametres', 'param_tab' => 'comptes']);
                break;
            }

            /* ---------- Suivi & offrandes (bacenta) ---------- */

            case 'save_visites_offrandes': {
                $this->requireUser();
                $bacenta = (int) ($_POST['bacenta'] ?? 0);
                if (!$bacenta || !auth_can_manage_bacenta($bacenta)) {
                    $this->deny();
                }
                $mois = (string) ($_POST['mois'] ?? current_month_key());
                $visites = [];
                foreach (($_POST['visites'] ?? []) as $i => $v) {
                    $visites[(int) $i] = $v;
                }
                $visiteurId = (int) (current_user()['id'] ?? 0);
                save_bacenta_visites($bacenta, $mois, $visites, $visiteurId);
                $offs = [];
                foreach (($_POST['offrandes'] ?? []) as $i => $v) {
                    $offs[(int) $i] = (int) $v;
                }
                save_offrandes_month('bacenta', $bacenta, $mois, $offs);
                $this->redirect('index.php', ['page' => 'bacentas', 'id' => $bacenta, 'tab' => 'suivi', 'semaine' => $mois]);
                break;
            }

            case 'save_offrandes_centre': {
                $this->requireUser();
                $centre = (int) ($_POST['centre'] ?? 0);
                if (!$centre || !auth_can_manage_center($centre)) {
                    $this->deny();
                }
                $mois = (string) ($_POST['mois'] ?? current_month_key());
                $offs = [];
                foreach (($_POST['offrandes'] ?? []) as $i => $v) {
                    $offs[(int) $i] = (int) $v;
                }
                save_offrandes_month('centre', $centre, $mois, $offs);
                $this->redirect('index.php', ['page' => 'centres', 'id' => $centre, 'tab' => 'suivi', 'semaine' => $mois]);
                break;
            }

            /* ---------- Présence par événement (culte) ---------- */

            case 'point_culte': {
                $this->requireUser();
                $culte = (int) ($_POST['culte'] ?? 0);
                // spec §26 : un pasteur/reverant responsable du Culte A ne
                // doit jamais pouvoir pointer les présences du Culte B.
                if (!$culte || !auth_can_manage_culte($culte)) {
                    $this->deny();
                }
                $date = (string) ($_POST['date_presence'] ?? date('Y-m-d'));
                if ($date !== '') {
                    $userIds = array_map('intval', array_keys($_POST['present'] ?? []));
                    point_culte_presence($culte, $date, $userIds);
                }
                $this->redirect('index.php', ['page' => 'cultes', 'id' => $culte]);
                break;
            }

            /* ---------- Présence par occurrence (bacenta / culte / basonta, statut) ---------- */

            case 'save_presence_occurrence': {
                $this->requireUser();
                $unitType = (string) ($_POST['unit_type'] ?? '');
                $unitId = (int) ($_POST['unit_id'] ?? 0);
                if (!in_array($unitType, ['bacenta', 'cult', 'basonta', 'evenement'], true) || !$unitId) {
                    $this->deny();
                }
                if ($unitType === 'evenement') {
                    $evt = calendrier_service()->event($unitId);
                    if (!$evt || !(auth_can_manage_calendar() || auth_can_edit_evenement($evt))) {
                        $this->deny();
                    }
                } elseif (!can_manage_entity($unitType, $unitId)) {
                    $this->deny();
                }
                $date = (string) ($_POST['date'] ?? date('Y-m-d'));
                // Population autorisée revalidée serveur selon le type d'unité.
                $allowed = match ($unitType) {
                    'bacenta' => array_map(static fn($m) => (int) $m['id'], get_members_of_bacenta($unitId)),
                    'basonta' => array_map(static fn($m) => (int) $m['id'], get_members_of_basonta($unitId)),
                    'cult'    => array_map(static fn($m) => (int) $m['id'], Query::all("SELECT id FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant')")),
                    'evenement' => array_map(static fn($m) => (int) $m['id'], Query::all("SELECT id FROM users WHERE role IN ('membre','leader','assistant','pasteur','reverant')")),
                };
                $raw = [];
                foreach ((array) ($_POST['statut'] ?? []) as $uid => $st) {
                    $raw[(int) $uid] = (string) $st;
                }
                save_unit_presence($unitType, $unitId, $date, $raw, $allowed);
                if ($unitType === 'evenement') {
                    $this->redirect('index.php', ['page' => 'calendrier', 'evt' => $unitId, 'date' => $date]);
                }
                $pageKey = ['bacenta' => 'bacentas', 'cult' => 'cultes', 'basonta' => 'basontas'][$unitType];
                $this->redirect('index.php', ['page' => $pageKey, 'id' => $unitId, 'tab' => 'presences', 'date' => $date]);
                break;
            }

            /* ---------- Calendriers (M4) ---------- */

            case 'save_evenement': {
                $user = $this->requireUser();
                if (!auth_can_manage_calendar()) {
                    $this->deny();
                }
                $id = (int) ($_POST['id'] ?? 0);
                if ($id) {
                    $existing = calendrier_service()->event($id);
                    if (!$existing || !auth_can_edit_evenement($existing)) {
                        $this->deny();
                    }
                }
                $res = calendrier_service()->saveEvent($_POST, (int) $user['id']);
                if (!$res['ok']) {
                    $editForForm = $id ? calendrier_service()->event($id) : null;
                    render_page(SECTION_LABELS['calendrier'], view('pages/calendrier', [
                        'events'       => calendrier_service()->allEvents(),
                        'canManage'    => true,
                        'edit'         => $editForForm,
                        'responsables' => Query::all("SELECT id, prenom, nom FROM users WHERE role IN ('berger','ms','pasteur','reverant','admin') ORDER BY prenom, nom"),
                        'errors'       => $res['errors'],
                        'old'          => $_POST,
                        'csrf'         => csrf_field(),
                        'mode'         => 'list',
                    ]));
                    return;
                }
                $this->redirect('index.php', ['page' => 'calendrier']);
                break;
            }

            case 'save_anniversaire': {
                $user = $this->requireUser();
                if (!auth_can_manage_calendar()) {
                    $this->deny();
                }
                $res = calendrier_service()->saveBirthday($_POST, (int) $user['id']);
                if (!$res['ok']) {
                    render_page(SECTION_LABELS['anniversaires'], view('pages/anniversaires', [
                        'birthdays'    => calendrier_service()->birthdays(),
                        'canManage'    => true,
                        'monthsFr'     => MONTHS_FR,
                        'currentMonth' => (int) date('n'),
                        'errors'       => $res['errors'],
                        'old'          => $_POST,
                        'csrf'         => csrf_field(),
                    ]));
                    return;
                }
                $this->redirect('index.php', ['page' => 'anniversaires']);
                break;
            }

            /* ---------- Basonta : ajout de membre ---------- */

            case 'basonta_add_member': {
                $this->requireUser();
                $basonta = (int) ($_POST['basonta'] ?? 0);
                if (!$basonta || (current_user()['role'] !== 'admin' && !auth_can_manage_basonta($basonta))) {
                    $this->deny();
                }
                $membre = (int) ($_POST['membre'] ?? 0);
                if ($membre) {
                    basonta_add_member($basonta, $membre);
                }
                $this->redirect('index.php', ['page' => 'basontas', 'id' => $basonta]);
                break;
            }

            /* ---------- Fiche berger ---------- */

            case 'save_dimes':
            case 'add_examen':
            case 'add_veillee': {
                $user = $this->requireUser();
                $membre = (int) ($_POST['membre'] ?? 0);
                // spec §20 : "SA PROPRE fiche" — jamais celle d'un autre,
                // sauf admin. C'est ici, côté serveur, que la règle est
                // réellement appliquée (le verrouillage de vue n'est qu'un
                // confort UI, jamais une sécurité).
                if (!$membre || ($user['role'] !== 'admin' && $membre !== (int) $user['id'])) {
                    $this->deny();
                }
                if ($action === 'save_dimes') {
                    $annee = (int) ($_POST['annee'] ?? current_year());
                    $vals = [];
                    foreach (($_POST['dimes'] ?? []) as $i => $v) {
                        $vals[(int) $i] = (int) $v;
                    }
                    save_dimes($membre, $annee, $vals);
                    $this->redirect('index.php', ['page' => 'bergerFiche', 'membre' => $membre, 'tab' => 'dimes', 'annee' => $annee]);
                } elseif ($action === 'add_examen') {
                    $nom = trim((string) ($_POST['nom'] ?? ''));
                    $date = trim((string) ($_POST['date'] ?? '')) ?: null;
                    if ($nom !== '') {
                        add_examen($membre, $nom, $date);
                    }
                    $this->redirect('index.php', ['page' => 'bergerFiche', 'membre' => $membre, 'tab' => 'examens']);
                } else {
                    $date = trim((string) ($_POST['date'] ?? ''));
                    $present = (($_POST['present'] ?? '1') === '1');
                    if ($date !== '') {
                        add_veillee($membre, $date, $present);
                    }
                    $this->redirect('index.php', ['page' => 'bergerFiche', 'membre' => $membre, 'tab' => 'veillees']);
                }
                break;
            }

            case 'save_suivi': {
                $user = $this->requireUser();
                $membre = (int) ($_POST['membre'] ?? 0);
                // BUG CRITIQUE corrigé (spec §20) : auparavant $membre était
                // pris tel quel depuis $_POST, sans aucune vérification —
                // n'importe quel utilisateur authentifié pouvait écraser le
                // suivi_hebdo d'un autre. Seul le propriétaire (ou l'admin)
                // peut désormais écrire sa fiche.
                if (!$membre || ($user['role'] !== 'admin' && $membre !== (int) $user['id'])) {
                    $this->deny();
                }
                $semaine = (string) ($_POST['semaine'] ?? current_week_key());
                $data = [];
                foreach (($_POST['suivi'] ?? []) as $day => $fields) {
                    $data[$day] = $fields;
                }
                save_suivi_week($membre, $semaine, $data);
                $this->redirect('index.php', ['page' => 'suiviBergers', 'membre' => $membre, 'semaine' => $semaine]);
                break;
            }

            /* ---------- Responsabilités (nouveau modèle — remplace l'ancien save_responsable ad hoc) ---------- */

            case 'assign_responsibility': {
                // BUG CRITIQUE corrigé (spec §29/§34/§40) : auparavant
                // n'importe quel utilisateur authentifié pouvait réaffecter
                // le responsable d'un bacenta/basonta/culte (aucun contrôle
                // au-delà du CSRF global). Réservé à l'admin désormais.
                $this->requireAdmin();
                if (!auth_can_manage_responsibilities()) {
                    $this->deny();
                }
                $targetType = (string) ($_POST['target_type'] ?? '');
                $targetId = (int) ($_POST['target_id'] ?? 0);
                $userId = (int) ($_POST['user_id'] ?? 0);
                if ($targetType && $targetId && $userId) {
                    responsibility_service()->assign($userId, $targetType, $targetId);
                }
                $this->redirect('index.php', ['page' => 'parametres', 'param_tab' => 'acces']);
                break;
            }

            // Conservée pour compatibilité de forme avec l'ancien formulaire
            // "Accès & Responsables" (un seul <select> par ligne) : même
            // contrôle d'autorisation strict, délègue à ResponsibilityService.
            case 'save_responsable': {
                $this->requireAdmin();
                if (!auth_can_manage_responsibilities()) {
                    $this->deny();
                }
                $type = (string) ($_POST['type'] ?? 'bacenta');
                $id = (int) ($_POST['id'] ?? 0);
                $userId = (int) ($_POST['user_id'] ?? 0) ?: null;
                if ($id) {
                    save_responsable($type, $id, $userId);
                }
                $this->redirect('index.php', ['page' => 'parametres', 'param_tab' => 'acces']);
                break;
            }

            case 'save_histoire':
                $this->requireAdmin();
                save_presentation(
                    trim((string) ($_POST['accroche'] ?? '')),
                    trim((string) ($_POST['histoire'] ?? ''))
                );
                $this->redirect('index.php', ['page' => 'apropos']);
                break;

            case 'save_equipe':
                $this->requireAdmin();
                $id = (int) ($_POST['id'] ?? 0);
                $nom = trim((string) ($_POST['nom'] ?? ''));
                $role = trim((string) ($_POST['role'] ?? ''));
                $cat = trim((string) ($_POST['categorie'] ?? 'Autre')) ?: 'Autre';
                $bio = trim((string) ($_POST['bio'] ?? ''));
                $emoji = trim((string) ($_POST['emoji'] ?? '')) ?: '👤';
                if ($nom === '' || $role === '') {
                    $this->redirect('index.php', ['page' => 'apropos', 'form' => 'equipe', 'id' => $id ?: null]);
                }
                $photo = handle_photo_upload('photo');
                save_equipe_record($id ?: null, [
                    'nom_affichage' => $nom,
                    'role_affichage' => $role,
                    'bio' => $bio,
                    'emoji' => $emoji,
                    'categorie' => $cat,
                    'photo' => $photo,
                ]);
                $this->redirect('index.php', ['page' => 'apropos']);
                break;

            case 'save_article':
                $this->requireAdmin();
                $id = (int) ($_POST['id'] ?? 0);
                $centreId = (int) ($_POST['centre_id'] ?? 0);
                if (!$centreId) {
                    $this->redirect('index.php', ['page' => 'centresPresentation']);
                }
                $photo = handle_photo_upload('photo');
                $objectifs = json_encode(
                    array_values(array_filter(array_map('trim', explode("\n", (string) ($_POST['objectifs'] ?? ''))), fn($s) => $s !== '')),
                    JSON_UNESCAPED_UNICODE
                );
                $fields = [
                    'centre_id' => $centreId,
                    'intro' => trim((string) ($_POST['intro'] ?? '')),
                    'vision' => trim((string) ($_POST['vision'] ?? '')),
                    'direction' => trim((string) ($_POST['direction'] ?? '')),
                    'origine' => trim((string) ($_POST['origine'] ?? '')),
                    'situation_annees' => (int) ($_POST['annees'] ?? 0),
                    'situation_pasteurs' => (int) ($_POST['pasteurs'] ?? 0),
                    'situation_bergers' => (int) ($_POST['bergers'] ?? 0),
                    'situation_leaders' => (int) ($_POST['leaders'] ?? 0),
                    'situation_bacentas' => (int) ($_POST['bacentas'] ?? 0),
                    'objectifs' => $objectifs,
                    'photo' => $photo,
                ];
                save_article_record($id ?: null, $fields);
                $this->redirect('index.php', ['page' => 'centresPresentation']);
                break;

            /* ---------- Administration des inscriptions ---------- */

            case 'admin_activate_account':
                // Contrôle d'autorisation strictement côté serveur : jamais
                // seulement un bouton masqué côté vue.
                if (!current_user() || current_user()['role'] !== 'admin') {
                    $this->redirect('index.php', ['page' => 'apropos']);
                }
                $id = (int) ($_POST['id'] ?? 0);
                if ($id) {
                    registration_service()->activate($id);
                }
                $this->redirect('index.php', ['page' => 'admin_inscriptions']);
                break;

            case 'admin_reject_account':
                if (!current_user() || current_user()['role'] !== 'admin') {
                    $this->redirect('index.php', ['page' => 'apropos']);
                }
                $id = (int) ($_POST['id'] ?? 0);
                if ($id) {
                    registration_service()->reject($id);
                }
                $this->redirect('index.php', ['page' => 'admin_inscriptions']);
                break;

            /* ---------- Affectation de membres à un bacenta (responsable) ---------- */

            case 'bacenta_assign_members':
                $user = current_user();
                $submittedBacentaId = (int) ($_POST['bacenta_id'] ?? 0);
                $submittedIds = array_map('intval', (array) ($_POST['member_ids'] ?? []));

                $service = bacenta_membership_service();
                $authorizedBacentaId = $user ? $service->authorizedBacentaId($user, $submittedBacentaId) : null;

                if ($authorizedBacentaId && $submittedIds) {
                    $service->assignMembers($authorizedBacentaId, $submittedIds);
                }

                $this->redirect('index.php', [
                    'page' => 'bacentas',
                    'id'   => $authorizedBacentaId ?: $submittedBacentaId,
                    'tab'  => 'membres',
                ]);
                break;
        }
    }
}
