<?php
/**
 * Actions (POST et suppressions) — chaque action se termine par une redirection.
 */

declare(strict_types=1);

require_once __DIR__ . '/render.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/auth.php';

function handle_get_action(): void
{
    $action = $_GET['action'] ?? null;

    switch ($action) {
        case 'logout':
            logout();
            redirect('index.php');
            break;

        case 'delete_centre':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id) {
                qexec('DELETE FROM presences WHERE centre_id = ?', [$id]);
                qexec('DELETE FROM offrandes WHERE centre_id = ?', [$id]);
                qexec('DELETE FROM bacentas WHERE centre_id = ?', [$id]);
                qexec('DELETE FROM centres_presentation WHERE centre_id = ?', [$id]);
                qexec('DELETE FROM centres WHERE id = ?', [$id]);
            }
            redirect('index.php', ['page' => 'centres']);
            break;

        case 'delete_bacenta':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id) {
                qexec('UPDATE users SET bacenta_id = NULL WHERE bacenta_id = ?', [$id]);
                qexec('DELETE FROM visites WHERE bacenta_id = ?', [$id]);
                qexec('DELETE FROM presences WHERE bacenta_id = ?', [$id]);
                qexec('DELETE FROM offrandes WHERE bacenta_id = ?', [$id]);
                qexec('DELETE FROM bacentas WHERE id = ?', [$id]);
            }
            redirect('index.php', ['page' => 'bacentas']);
            break;

        case 'delete_culte':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id) {
                qexec('DELETE FROM presences WHERE culte_id = ?', [$id]);
                qexec('DELETE FROM cultes WHERE id = ?', [$id]);
            }
            redirect('index.php', ['page' => 'cultes']);
            break;

        case 'delete_basonta':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id) {
                qexec('DELETE FROM users_basontas WHERE basonta_id = ?', [$id]);
                qexec('DELETE FROM basontas WHERE id = ?', [$id]);
            }
            redirect('index.php', ['page' => 'basontas']);
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
            redirect('index.php', ['page' => 'bergerFiche', 'membre' => $membre, 'tab' => 'examens']);
            break;

        case 'delete_veillee':
            $membre = (int) ($_GET['membre'] ?? 0);
            $id = (int) ($_GET['id'] ?? 0);
            if ($membre && $id) {
                delete_veillee($membre, $id);
            }
            redirect('index.php', ['page' => 'bergerFiche', 'membre' => $membre, 'tab' => 'veillees']);
            break;

        case 'delete_user':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id) {
                delete_user($id);
            }
            redirect('index.php', ['page' => 'parametres', 'param_tab' => 'comptes']);
            break;

        case 'delete_equipe':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id) {
                $m = qone('SELECT * FROM equipe WHERE id = ?', [$id]);
                if ($m) {
                    delete_photo_file($m['photo']);
                    qexec('DELETE FROM equipe WHERE id = ?', [$id]);
                }
            }
            redirect('index.php', ['page' => 'apropos']);
            break;

        case 'delete_article':
            $id = (int) ($_GET['id'] ?? 0);
            if ($id) {
                $c = qone('SELECT * FROM centres_presentation WHERE id = ?', [$id]);
                if ($c) {
                    delete_photo_file($c['photo']);
                    qexec('DELETE FROM centres_presentation WHERE id = ?', [$id]);
                }
            }
            redirect('index.php', ['page' => 'centresPresentation']);
            break;

        case 'basonta_remove_member':
            $basonta = (int) ($_GET['basonta'] ?? 0);
            $membre = (int) ($_GET['membre'] ?? 0);
            if ($basonta && $membre) {
                qexec('DELETE FROM users_basontas WHERE user_id = ? AND basonta_id = ?', [$membre, $basonta]);
            }
            redirect('index.php', ['page' => 'basontas', 'id' => $basonta]);
            break;
    }
}

function redirect_members_context(string $section, ?int $entityId = null): never
{
    $params = ['page' => $section];
    $entityId = $entityId ?: nav('id_ent') ?: nav('id');
    if (in_array($section, ['bacentas', 'centres', 'cultes', 'basontas'], true) && $entityId) {
        $params['id'] = $entityId;
        if ($section === 'bacentas' && nav('tab')) {
            $params['tab'] = nav('tab');
        }
    }
    redirect('index.php', $params);
}

function handle_post_action(): void
{
    $action = $_POST['action'] ?? '';
    check_csrf();

    switch ($action) {
        case 'login':
            $ok = login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
            if ($ok) {
                $target = scope_target();
                redirect('index.php', $target ?: ['page' => 'accueil']);
            }
            redirect('index.php', ['error' => 1]);
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
            redirect('index.php', $params);
            break;

        /* ---------- CRUD structure ---------- */

        case 'save_centre':
            $id = (int) ($_POST['id'] ?? 0);
            $nom = trim((string) ($_POST['nom'] ?? ''));
            if ($nom !== '') {
                if ($id) {
                    qexec('UPDATE centres SET nom = ? WHERE id = ?', [$nom, $id]);
                } else {
                    qexec('INSERT INTO centres (nom) VALUES (?)', [$nom]);
                }
            }
            redirect('index.php', ['page' => 'centres']);
            break;

        case 'save_bacenta':
            $id = (int) ($_POST['id'] ?? 0);
            $nom = trim((string) ($_POST['nom'] ?? ''));
            $centreId = (int) ($_POST['centre_id'] ?? 0) ?: null;
            $respId = (int) ($_POST['responsable_id'] ?? 0) ?: null;
            if ($nom !== '') {
                if ($id) {
                    qexec('UPDATE bacentas SET nom = ?, centre_id = ?, responsable_id = ? WHERE id = ?', [$nom, $centreId, $respId, $id]);
                } else {
                    qexec('INSERT INTO bacentas (nom, centre_id, responsable_id) VALUES (?, ?, ?)', [$nom, $centreId, $respId]);
                }
            }
            redirect('index.php', ['page' => 'bacentas']);
            break;

        case 'save_culte':
            $id = (int) ($_POST['id'] ?? 0);
            $nom = trim((string) ($_POST['nom'] ?? ''));
            $date = trim((string) ($_POST['date_culte'] ?? '')) ?: null;
            $debut = trim((string) ($_POST['heure_debut'] ?? '')) ?: null;
            $fin = trim((string) ($_POST['heure_fin'] ?? '')) ?: null;
            $resp = (int) ($_POST['responsable_id'] ?? 0) ?: null;
            if ($nom !== '') {
                if ($id) {
                    qexec('UPDATE cultes SET nom = ?, date_culte = ?, heure_debut = ?, heure_fin = ?, responsable_id = ? WHERE id = ?',
                          [$nom, $date, $debut, $fin, $resp, $id]);
                } else {
                    qexec('INSERT INTO cultes (nom, date_culte, heure_debut, heure_fin, responsable_id) VALUES (?, ?, ?, ?, ?)',
                          [$nom, $date, $debut, $fin, $resp]);
                }
            }
            redirect('index.php', ['page' => 'cultes']);
            break;

        case 'save_basonta':
            $id = (int) ($_POST['id'] ?? 0);
            $nom = trim((string) ($_POST['nom'] ?? ''));
            $resp = (int) ($_POST['responsable_id'] ?? 0) ?: null;
            if ($nom !== '') {
                if ($id) {
                    qexec('UPDATE basontas SET nom = ?, responsable_id = ? WHERE id = ?', [$nom, $resp, $id]);
                } else {
                    qexec('INSERT INTO basontas (nom, responsable_id) VALUES (?, ?)', [$nom, $resp]);
                }
            }
            redirect('index.php', ['page' => 'basontas']);
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
                redirect('index.php', [
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
                // Rattachement automatique selon la section d'origine
                if ($section === 'bacentas' && $entityId) {
                    qexec('UPDATE users SET bacenta_id = ? WHERE id = ?', [$entityId, $id]);
                } elseif ($section === 'centres' && $entityId) {
                    $first = qval('SELECT id FROM bacentas WHERE centre_id = ? ORDER BY id LIMIT 1', [$entityId]);
                    if ($first) {
                        qexec('UPDATE users SET bacenta_id = ? WHERE id = ?', [(int) $first, $id]);
                    }
                } elseif ($section === 'basontas' && $entityId) {
                    qexec('INSERT INTO users_basontas (user_id, basonta_id) VALUES (?, ?)', [$id, $entityId]);
                } elseif ($section === 'cultes' && $entityId) {
                    qexec('INSERT INTO presences (user_id, date_presence, culte_id) VALUES (?, CURDATE(), ?)', [$id, $entityId]);
                }
            }

            // Présences rapides (Présent / —)
            foreach (PRESENCE_FIELDS as $f) {
                if (isset($_POST[$f])) {
                    save_quick_presence($id, $f, (string) $_POST[$f]);
                }
            }

            if (($_POST['retour'] ?? '') === 'fiche') {
                redirect('index.php', ['page' => 'bergerFiche', 'membre' => $id]);
            }
            redirect_members_context($section, $entityId);
            break;

        case 'save_user':
            // Comptes applicatifs (Paramètres) : même table users.
            $id = (int) ($_POST['id'] ?? 0);
            $data = user_data_from_post($id ?: null);
            $data['role'] = (($_POST['role'] ?? 'user') === 'admin') ? 'admin' : ($_POST['role'] ?? 'membre');
            if ($data['email'] === '' || $data['nom'] === '' || (($_POST['password'] ?? '') === '' && !$id)) {
                redirect('index.php', ['page' => 'parametres', 'param_tab' => 'comptes']);
            }
            if (email_taken($data['email'], $id ?: null)) {
                redirect('index.php', ['page' => 'parametres', 'form' => 'user', 'id' => $id ?: null, 'error' => 1]);
            }
            $newPass = trim((string) ($_POST['password'] ?? ''));
            if ($id) {
                update_user_from_post($id, $data, null, $newPass !== '' ? $newPass : null);
            } else {
                insert_user_from_post($data, null);
            }
            redirect('index.php', ['page' => 'parametres', 'param_tab' => 'comptes']);
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
            redirect('index.php', ['page' => 'bacentas', 'id' => $bacenta, 'tab' => 'suivi', 'semaine' => $mois]);
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
            redirect('index.php', ['page' => 'centres', 'id' => $centre, 'tab' => 'suivi', 'semaine' => $mois]);
            break;

        /* ---------- Présence par événement (culte) ---------- */

        case 'point_culte':
            $culte = (int) ($_POST['culte'] ?? 0);
            $date = (string) ($_POST['date_presence'] ?? date('Y-m-d'));
            if ($culte && $date !== '') {
                $userIds = array_map('intval', array_keys($_POST['present'] ?? []));
                point_culte_presence($culte, $date, $userIds);
            }
            redirect('index.php', ['page' => 'cultes', 'id' => $culte]);
            break;

        /* ---------- Basonta : ajout de membre ---------- */

        case 'basonta_add_member':
            $basonta = (int) ($_POST['basonta'] ?? 0);
            $membre = (int) ($_POST['membre'] ?? 0);
            if ($basonta && $membre) {
                $exists = qval('SELECT COUNT(*) FROM users_basontas WHERE user_id = ? AND basonta_id = ?', [$membre, $basonta]);
                if (!$exists) {
                    qexec('INSERT INTO users_basontas (user_id, basonta_id) VALUES (?, ?)', [$membre, $basonta]);
                }
            }
            redirect('index.php', ['page' => 'basontas', 'id' => $basonta]);
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
            redirect('index.php', ['page' => 'bergerFiche', 'membre' => $membre, 'tab' => 'dimes', 'annee' => $annee]);
            break;

        case 'add_examen':
            $membre = (int) ($_POST['membre'] ?? 0);
            $nom = trim((string) ($_POST['nom'] ?? ''));
            $date = trim((string) ($_POST['date'] ?? '')) ?: null;
            if ($membre && $nom !== '') {
                add_examen($membre, $nom, $date);
            }
            redirect('index.php', ['page' => 'bergerFiche', 'membre' => $membre, 'tab' => 'examens']);
            break;

        case 'add_veillee':
            $membre = (int) ($_POST['membre'] ?? 0);
            $date = trim((string) ($_POST['date'] ?? ''));
            $present = (($_POST['present'] ?? '1') === '1');
            if ($membre && $date !== '') {
                add_veillee($membre, $date, $present);
            }
            redirect('index.php', ['page' => 'bergerFiche', 'membre' => $membre, 'tab' => 'veillees']);
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
            redirect('index.php', ['page' => 'suiviBergers', 'membre' => $membre, 'semaine' => $semaine]);
            break;

        /* ---------- Présentation ---------- */

        case 'save_responsable':
            $type = (string) ($_POST['type'] ?? 'bacenta');
            $id = (int) ($_POST['id'] ?? 0);
            $userId = (int) ($_POST['user_id'] ?? 0) ?: null;
            $table = in_array($type, ['bacenta', 'basonta', 'culte'], true) ? $type . 's' : 'bacentas';
            qexec("UPDATE $table SET responsable_id = ? WHERE id = ?", [$userId, $id]);
            redirect('index.php', ['page' => 'parametres', 'param_tab' => 'acces']);
            break;

        case 'save_histoire':
            save_presentation(
                trim((string) ($_POST['accroche'] ?? '')),
                trim((string) ($_POST['histoire'] ?? ''))
            );
            redirect('index.php', ['page' => 'apropos']);
            break;

        case 'save_equipe':
            $id = (int) ($_POST['id'] ?? 0);
            $nom = trim((string) ($_POST['nom'] ?? ''));
            $role = trim((string) ($_POST['role'] ?? ''));
            $cat = trim((string) ($_POST['categorie'] ?? 'Autre')) ?: 'Autre';
            $bio = trim((string) ($_POST['bio'] ?? ''));
            $emoji = trim((string) ($_POST['emoji'] ?? '')) ?: '👤';
            if ($nom === '' || $role === '') {
                redirect('index.php', ['page' => 'apropos', 'form' => 'equipe', 'id' => $id ?: null]);
            }
            $photo = handle_photo_upload('photo');
            if ($id) {
                $cur = qone('SELECT * FROM equipe WHERE id = ?', [$id]);
                if ($cur && $photo && $cur['photo'] && $cur['photo'] !== $photo) {
                    delete_photo_file($cur['photo']);
                }
                qexec('UPDATE equipe SET nom_affichage = ?, role_affichage = ?, bio = ?, emoji = ?, categorie = ?, photo = COALESCE(?, photo) WHERE id = ?',
                      [$nom, $role, $bio, $emoji, $cat, $photo, $id]);
            } else {
                qexec('INSERT INTO equipe (nom_affichage, role_affichage, bio, emoji, categorie, photo) VALUES (?, ?, ?, ?, ?, ?)',
                      [$nom, $role, $bio, $emoji, $cat, $photo]);
            }
            redirect('index.php', ['page' => 'apropos']);
            break;

        case 'save_article':
            $id = (int) ($_POST['id'] ?? 0);
            $centreId = (int) ($_POST['centre_id'] ?? 0);
            if (!$centreId) {
                redirect('index.php', ['page' => 'centresPresentation']);
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
            ];
            if ($id) {
                $cur = qone('SELECT * FROM centres_presentation WHERE id = ?', [$id]);
                if ($cur && $photo && $cur['photo'] && $cur['photo'] !== $photo) {
                    delete_photo_file($cur['photo']);
                }
                $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
                $params = array_values($fields);
                if ($photo) {
                    $sets .= ', photo = ?';
                    $params[] = $photo;
                }
                $params[] = $id;
                qexec("UPDATE centres_presentation SET $sets WHERE id = ?", $params);
            } else {
                $fields['photo'] = $photo;
                $cols = array_keys($fields);
                qexec('INSERT INTO centres_presentation (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')',
                      array_values($fields));
            }
            redirect('index.php', ['page' => 'centresPresentation']);
            break;
    }
}
