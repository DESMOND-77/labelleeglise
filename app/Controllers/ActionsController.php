<?php

namespace App\Controllers;

use App\Core\Query;
use App\Core\Request;
use App\Repositories\CMSRepository;

/**
 * Gestionnaire central des actions (POST et suppressions).
 * Chaque action se termine par une redirection (comportement identique à
 * l'ancien actions.php).
 */
class ActionsController extends Controller
{
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
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    delete_centre($id);
                }
                $this->redirect('index.php', ['page' => 'centres']);
                break;

            case 'delete_bacenta':
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    delete_bacenta($id);
                }
                $this->redirect('index.php', ['page' => 'bacentas']);
                break;

            case 'delete_culte':
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    delete_culte($id);
                }
                $this->redirect('index.php', ['page' => 'cultes']);
                break;

            case 'delete_basonta':
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    delete_basonta($id);
                }
                $this->redirect('index.php', ['page' => 'basontas']);
                break;

            case 'delete_membre':
                $id = (int) ($_GET['id'] ?? 0);
                $section = (string) nav('page');
                if ($id) {
                    delete_user($id);
                }
                redirect_members_context($section);
                break;

            case 'delete_examen':
                $membre = (int) ($_GET['membre'] ?? 0);
                $id = (int) ($_GET['id'] ?? 0);
                if ($membre && $id) {
                    delete_examen($membre, $id);
                }
                $this->redirect('index.php', ['page' => 'bergerFiche', 'membre' => $membre, 'tab' => 'examens']);
                break;

            case 'delete_veillee':
                $membre = (int) ($_GET['membre'] ?? 0);
                $id = (int) ($_GET['id'] ?? 0);
                if ($membre && $id) {
                    delete_veillee($membre, $id);
                }
                $this->redirect('index.php', ['page' => 'bergerFiche', 'membre' => $membre, 'tab' => 'veillees']);
                break;

            case 'delete_user':
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    delete_user($id);
                }
                $this->redirect('index.php', ['page' => 'parametres', 'param_tab' => 'comptes']);
                break;

            case 'delete_equipe':
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    delete_equipe_record($id);
                }
                $this->redirect('index.php', ['page' => 'apropos']);
                break;

            case 'delete_article':
                $id = (int) ($_GET['id'] ?? 0);
                if ($id) {
                    delete_article_record($id);
                }
                $this->redirect('index.php', ['page' => 'centresPresentation']);
                break;

            case 'basonta_remove_member':
                $basonta = (int) ($_GET['basonta'] ?? 0);
                $membre = (int) ($_GET['membre'] ?? 0);
                if ($basonta && $membre) {
                    basonta_remove_member($basonta, $membre);
                }
                $this->redirect('index.php', ['page' => 'basontas', 'id' => $basonta]);
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
                $id = (int) ($_POST['id'] ?? 0);
                $nom = trim((string) ($_POST['nom'] ?? ''));
                if ($nom !== '') {
                    save_centre($id ?: null, $nom);
                }
                $this->redirect('index.php', ['page' => 'centres']);
                break;

            case 'save_bacenta':
                $id = (int) ($_POST['id'] ?? 0);
                $nom = trim((string) ($_POST['nom'] ?? ''));
                $centreId = (int) ($_POST['centre_id'] ?? 0) ?: null;
                $respId = (int) ($_POST['responsable_id'] ?? 0) ?: null;
                if ($nom !== '') {
                    save_bacenta($id ?: null, $nom, $centreId, $respId);
                }
                $this->redirect('index.php', ['page' => 'bacentas']);
                break;

            case 'save_culte':
                $id = (int) ($_POST['id'] ?? 0);
                $nom = trim((string) ($_POST['nom'] ?? ''));
                $date = trim((string) ($_POST['date_culte'] ?? '')) ?: null;
                $debut = trim((string) ($_POST['heure_debut'] ?? '')) ?: null;
                $fin = trim((string) ($_POST['heure_fin'] ?? '')) ?: null;
                $resp = (int) ($_POST['responsable_id'] ?? 0) ?: null;
                if ($nom !== '') {
                    save_culte($id ?: null, $nom, $date, $debut, $fin, $resp);
                }
                $this->redirect('index.php', ['page' => 'cultes']);
                break;

            case 'save_basonta':
                $id = (int) ($_POST['id'] ?? 0);
                $nom = trim((string) ($_POST['nom'] ?? ''));
                $resp = (int) ($_POST['responsable_id'] ?? 0) ?: null;
                if ($nom !== '') {
                    save_basonta($id ?: null, $nom, $resp);
                }
                $this->redirect('index.php', ['page' => 'basontas']);
                break;

            /* ---------- CRUD membre (users) ---------- */

            case 'save_membre':
                $id = (int) ($_POST['id'] ?? 0);
                $section = (string) ($_POST['section'] ?? 'generale');
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

                $entityId = (int) ($_POST['id_ent'] ?? 0) ?: null;

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

            case 'save_user':
                $id = (int) ($_POST['id'] ?? 0);
                $data = user_data_from_post($id ?: null);
                $data['role'] = (($_POST['role'] ?? 'user') === 'admin') ? 'admin' : ($_POST['role'] ?? 'membre');
                if ($data['email'] === '' || $data['nom'] === '' || (($_POST['password'] ?? '') === '' && !$id)) {
                    $this->redirect('index.php', ['page' => 'parametres', 'param_tab' => 'comptes']);
                }
                if (email_taken($data['email'], $id ?: null)) {
                    $this->redirect('index.php', ['page' => 'parametres', 'form' => 'user', 'id' => $id ?: null, 'error' => 1]);
                }
                $newPass = trim((string) ($_POST['password'] ?? ''));
                if ($id) {
                    update_user_from_post($id, $data, null, $newPass !== '' ? $newPass : null);
                } else {
                    insert_user_from_post($data, null);
                }
                $this->redirect('index.php', ['page' => 'parametres', 'param_tab' => 'comptes']);
                break;

            /* ---------- Suivi & offrandes (bacenta) ---------- */

            case 'save_visites_offrandes':
                $bacenta = (int) ($_POST['bacenta'] ?? 0);
                $mois = (string) ($_POST['mois'] ?? current_month_key());
                if ($bacenta) {
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
                }
                $this->redirect('index.php', ['page' => 'bacentas', 'id' => $bacenta, 'tab' => 'suivi', 'semaine' => $mois]);
                break;

            case 'save_offrandes_centre':
                $centre = (int) ($_POST['centre'] ?? 0);
                $mois = (string) ($_POST['mois'] ?? current_month_key());
                if ($centre) {
                    $offs = [];
                    foreach (($_POST['offrandes'] ?? []) as $i => $v) {
                        $offs[(int) $i] = (int) $v;
                    }
                    save_offrandes_month('centre', $centre, $mois, $offs);
                }
                $this->redirect('index.php', ['page' => 'centres', 'id' => $centre, 'tab' => 'suivi', 'semaine' => $mois]);
                break;

            /* ---------- Présence par événement (culte) ---------- */

            case 'point_culte':
                $culte = (int) ($_POST['culte'] ?? 0);
                $date = (string) ($_POST['date_presence'] ?? date('Y-m-d'));
                if ($culte && $date !== '') {
                    $userIds = array_map('intval', array_keys($_POST['present'] ?? []));
                    point_culte_presence($culte, $date, $userIds);
                }
                $this->redirect('index.php', ['page' => 'cultes', 'id' => $culte]);
                break;

            /* ---------- Basonta : ajout de membre ---------- */

            case 'basonta_add_member':
                $basonta = (int) ($_POST['basonta'] ?? 0);
                $membre = (int) ($_POST['membre'] ?? 0);
                if ($basonta && $membre) {
                    basonta_add_member($basonta, $membre);
                }
                $this->redirect('index.php', ['page' => 'basontas', 'id' => $basonta]);
                break;

            /* ---------- Fiche berger ---------- */

            case 'save_dimes':
                $membre = (int) ($_POST['membre'] ?? 0);
                $annee = (int) ($_POST['annee'] ?? current_year());
                if ($membre) {
                    $vals = [];
                    foreach (($_POST['dimes'] ?? []) as $i => $v) {
                        $vals[(int) $i] = (int) $v;
                    }
                    save_dimes($membre, $annee, $vals);
                }
                $this->redirect('index.php', ['page' => 'bergerFiche', 'membre' => $membre, 'tab' => 'dimes', 'annee' => $annee]);
                break;

            case 'add_examen':
                $membre = (int) ($_POST['membre'] ?? 0);
                $nom = trim((string) ($_POST['nom'] ?? ''));
                $date = trim((string) ($_POST['date'] ?? '')) ?: null;
                if ($membre && $nom !== '') {
                    add_examen($membre, $nom, $date);
                }
                $this->redirect('index.php', ['page' => 'bergerFiche', 'membre' => $membre, 'tab' => 'examens']);
                break;

            case 'add_veillee':
                $membre = (int) ($_POST['membre'] ?? 0);
                $date = trim((string) ($_POST['date'] ?? ''));
                $present = (($_POST['present'] ?? '1') === '1');
                if ($membre && $date !== '') {
                    add_veillee($membre, $date, $present);
                }
                $this->redirect('index.php', ['page' => 'bergerFiche', 'membre' => $membre, 'tab' => 'veillees']);
                break;

            case 'save_suivi':
                $membre = (int) ($_POST['membre'] ?? 0);
                $semaine = (string) ($_POST['semaine'] ?? current_week_key());
                if ($membre) {
                    $data = [];
                    foreach (($_POST['suivi'] ?? []) as $day => $fields) {
                        $data[$day] = $fields;
                    }
                    save_suivi_week($membre, $semaine, $data);
                }
                $this->redirect('index.php', ['page' => 'suiviBergers', 'membre' => $membre, 'semaine' => $semaine]);
                break;

            /* ---------- Présentation ---------- */

            case 'save_responsable':
                $type = (string) ($_POST['type'] ?? 'bacenta');
                $id = (int) ($_POST['id'] ?? 0);
                $userId = (int) ($_POST['user_id'] ?? 0) ?: null;
                save_responsable($type, $id, $userId);
                $this->redirect('index.php', ['page' => 'parametres', 'param_tab' => 'acces']);
                break;

            case 'save_histoire':
                save_presentation(
                    trim((string) ($_POST['accroche'] ?? '')),
                    trim((string) ($_POST['histoire'] ?? ''))
                );
                $this->redirect('index.php', ['page' => 'apropos']);
                break;

            case 'save_equipe':
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

