<?php
/**
 * Couche d'accès aux données — nouveau modèle (centres / users / bacentas /
 * basontas / cultes / presences / offrandes / dimes / visites / suivi…).
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/* ------------------------------------------------------------------ */
/* Structure : centres & bacentas                                      */
/* ------------------------------------------------------------------ */

function get_centres(): array
{
    return qall(
        "SELECT c.*, (SELECT COUNT(*) FROM bacentas b WHERE b.centre_id = c.id) AS nb_bacentas
           FROM centres c ORDER BY c.id"
    );
}

function get_centre(int $id): ?array
{
    return qone("SELECT * FROM centres WHERE id = ?", [$id]);
}

function get_bacentas(?int $centreId = null): array
{
    if ($centreId) {
        return qall(
            "SELECT b.*, c.nom AS centre_nom,
                    (SELECT COUNT(*) FROM users u WHERE u.bacenta_id = b.id) AS nb_membres
               FROM bacentas b LEFT JOIN centres c ON c.id = b.centre_id
              WHERE b.centre_id = ? ORDER BY b.id",
            [$centreId]
        );
    }
    return qall(
        "SELECT b.*, c.nom AS centre_nom,
                (SELECT COUNT(*) FROM users u WHERE u.bacenta_id = b.id) AS nb_membres
           FROM bacentas b LEFT JOIN centres c ON c.id = b.centre_id
          ORDER BY b.id"
    );
}

function get_bacenta(int $id): ?array
{
    return qone(
        "SELECT b.*, c.nom AS centre_nom,
                (SELECT COUNT(*) FROM users u WHERE u.bacenta_id = b.id) AS nb_membres
           FROM bacentas b LEFT JOIN centres c ON c.id = b.centre_id
          WHERE b.id = ?",
        [$id]
    );
}

/* ------------------------------------------------------------------ */
/* Basontas & cultes                                                   */
/* ------------------------------------------------------------------ */

function get_basontas(): array
{
    return qall(
        "SELECT b.*, (SELECT COUNT(*) FROM users_basontas ub WHERE ub.basonta_id = b.id) AS nb_membres
           FROM basontas b ORDER BY b.id"
    );
}

function get_basonta(int $id): ?array
{
    return qone(
        "SELECT b.*, (SELECT COUNT(*) FROM users_basontas ub WHERE ub.basonta_id = b.id) AS nb_membres
           FROM basontas b WHERE b.id = ?",
        [$id]
    );
}

function get_cultes(): array
{
    return qall(
        "SELECT c.*, u.prenom AS resp_prenom, u.nom AS resp_nom,
                (SELECT COUNT(*) FROM presences p WHERE p.culte_id = c.id) AS nb_presents
           FROM cultes c LEFT JOIN users u ON u.id = c.responsable_id
          ORDER BY c.date_culte DESC, c.id"
    );
}

function get_culte(int $id): ?array
{
    return qone(
        "SELECT c.*, u.prenom AS resp_prenom, u.nom AS resp_nom,
                (SELECT COUNT(*) FROM presences p WHERE p.culte_id = c.id) AS nb_presents
           FROM cultes c LEFT JOIN users u ON u.id = c.responsable_id
          WHERE c.id = ?",
        [$id]
    );
}

/* ------------------------------------------------------------------ */
/* Membres (table users)                                               */
/* ------------------------------------------------------------------ */

function get_user(int $id): ?array
{
    return qone("SELECT * FROM users WHERE id = ?", [$id]);
}

function full_name(array $u): string
{
    return trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''));
}

function get_members_of_bacenta(int $bacentaId): array
{
    return qall("SELECT * FROM users WHERE bacenta_id = ? ORDER BY prenom, nom", [$bacentaId]);
}

function get_members_of_centre(int $centreId): array
{
    return qall(
        "SELECT u.* FROM users u JOIN bacentas b ON b.id = u.bacenta_id
          WHERE b.centre_id = ? ORDER BY u.prenom, u.nom",
        [$centreId]
    );
}

function get_members_of_basonta(int $basontaId): array
{
    return qall(
        "SELECT u.* FROM users u JOIN users_basontas ub ON ub.user_id = u.id
          WHERE ub.basonta_id = ? ORDER BY u.prenom, u.nom",
        [$basontaId]
    );
}

/** Membres présents à un culte (table presences). */
function get_members_of_culte(int $culteId): array
{
    return qall(
        "SELECT u.*, p.date_presence FROM users u JOIN presences p ON p.user_id = u.id
          WHERE p.culte_id = ? ORDER BY u.prenom, u.nom",
        [$culteId]
    );
}

function get_nouveaux_members(?string $filter = null): array
{
    $sql = "SELECT * FROM users WHERE (recu_par IS NOT NULL OR date_recu IS NOT NULL OR invite_par IS NOT NULL)";
    $params = [];
    if ($filter !== null && $filter !== '') {
        $sql .= " AND (LOWER(CONCAT(prenom, ' ', nom)) LIKE ? OR LOWER(CONCAT(nom, ' ', prenom)) LIKE ? OR LOWER(quartier) LIKE ?)";
        $f = '%' . mb_lower($filter) . '%';
        $params = [$f, $f, $f];
    }
    $sql .= " ORDER BY date_recu DESC, prenom, nom";
    return qall($sql, $params);
}

function get_generale_members(?string $filter = null): array
{
    $sql = "SELECT * FROM users WHERE 1 = 1";
    $params = [];
    if ($filter !== null && $filter !== '') {
        $sql .= " AND (LOWER(CONCAT(prenom, ' ', nom)) LIKE ? OR LOWER(CONCAT(nom, ' ', prenom)) LIKE ? OR LOWER(quartier) LIKE ?)";
        $f = '%' . mb_lower($filter) . '%';
        $params = [$f, $f, $f];
    }
    $sql .= " ORDER BY prenom, nom";
    return qall($sql, $params);
}

function get_berger_members(?string $filter = null): array
{
    $roles = implode(',', array_map(fn($r) => db()->quote($r), BERGER_ROLES));
    $sql = "SELECT * FROM users WHERE role IN ($roles)";
    $params = [];
    if ($filter !== null && $filter !== '') {
        $sql .= " AND (LOWER(CONCAT(prenom, ' ', nom)) LIKE ? OR LOWER(CONCAT(nom, ' ', prenom)) LIKE ?)";
        $f = '%' . mb_lower($filter) . '%';
        $params = [$f, $f];
    }
    $sql .= " ORDER BY prenom, nom";
    return qall($sql, $params);
}

/** Contexte des membres pour une section + entité (bacenta/centre/culte/basonta). */
function get_members_context(string $section, ?int $id = null): array
{
    switch ($section) {
        case 'bacentas':
            $b = $id ? get_bacenta($id) : null;
            return $b
                ? ['members' => get_members_of_bacenta($id), 'label' => $b['nom'], 'entity' => $b, 'entityId' => $id]
                : ['members' => [], 'label' => '', 'entity' => null, 'entityId' => null];
        case 'centres':
            $c = $id ? get_centre($id) : null;
            return $c
                ? ['members' => get_members_of_centre($id), 'label' => $c['nom'], 'entity' => $c, 'entityId' => $id]
                : ['members' => [], 'label' => '', 'entity' => null, 'entityId' => null];
        case 'cultes':
            $c = $id ? get_culte($id) : null;
            return $c
                ? ['members' => get_members_of_culte($id), 'label' => $c['nom'], 'entity' => $c, 'entityId' => $id]
                : ['members' => [], 'label' => '', 'entity' => null, 'entityId' => null];
        case 'basontas':
            $b = $id ? get_basonta($id) : null;
            return $b
                ? ['members' => get_members_of_basonta($id), 'label' => $b['nom'], 'entity' => $b, 'entityId' => $id]
                : ['members' => [], 'label' => '', 'entity' => null, 'entityId' => null];
        case 'nouveaux':
            return ['members' => get_nouveaux_members(), 'label' => 'Nouveaux membres', 'entity' => null, 'entityId' => null];
        case 'bergers':
            return ['members' => get_berger_members(), 'label' => 'Liste des bergers', 'entity' => null, 'entityId' => null];
        default: // generale
            return ['members' => get_generale_members(), 'label' => 'Liste générale des membres', 'entity' => null, 'entityId' => null];
    }
}

/* ------------------------------------------------------------------ */
/* Compteurs / statistiques                                            */
/* ------------------------------------------------------------------ */

function count_members(string $section): int
{
    switch ($section) {
        case 'bacentas':
            return (int) qval('SELECT COUNT(*) FROM users WHERE bacenta_id IS NOT NULL');
        case 'centres':
            return (int) qval('SELECT COUNT(*) FROM users u JOIN bacentas b ON b.id = u.bacenta_id');
        case 'cultes':
            return (int) qval('SELECT COUNT(DISTINCT user_id) FROM presences WHERE culte_id IS NOT NULL');
        case 'basontas':
            return (int) qval('SELECT COUNT(DISTINCT user_id) FROM users_basontas');
        case 'nouveaux':
            return (int) qval('SELECT COUNT(*) FROM users WHERE recu_par IS NOT NULL OR date_recu IS NOT NULL OR invite_par IS NOT NULL');
        case 'bergers': {
            $roles = implode(',', array_map(fn($r) => db()->quote($r), BERGER_ROLES));
            return (int) qval("SELECT COUNT(*) FROM users WHERE role IN ($roles)");
        }
        default:
            return (int) qval('SELECT COUNT(*) FROM users');
    }
}

/** Nombre cumulé de membres enregistrés (created_at) mois par mois sur 6 mois. */
function compute_evolution_history(): array
{
    $buckets = get_month_buckets(6);
    $history = ['labels' => array_map(fn($b) => $b['label'], $buckets)];

    foreach (CHART_POLES as $pole) {
        $section = $pole['key'];
        $series = [];
        foreach ($buckets as $b) {
            $end = $b['end']->format('Y-m-d 00:00:00');
            switch ($section) {
                case 'bacentas':
                    $series[] = (int) qval('SELECT COUNT(*) FROM users WHERE bacenta_id IS NOT NULL AND created_at < ?', [$end]);
                    break;
                case 'centres':
                    $series[] = (int) qval('SELECT COUNT(*) FROM users u JOIN bacentas b ON b.id = u.bacenta_id WHERE u.created_at < ?', [$end]);
                    break;
                case 'cultes':
                    $series[] = (int) qval('SELECT COUNT(DISTINCT p.user_id) FROM presences p JOIN users u ON u.id = p.user_id WHERE p.culte_id IS NOT NULL AND u.created_at < ?', [$end]);
                    break;
                case 'basontas':
                    $series[] = (int) qval('SELECT COUNT(DISTINCT ub.user_id) FROM users_basontas ub JOIN users u ON u.id = ub.user_id WHERE u.created_at < ?', [$end]);
                    break;
                case 'nouveaux':
                    $series[] = (int) qval('SELECT COUNT(*) FROM users WHERE (recu_par IS NOT NULL OR date_recu IS NOT NULL OR invite_par IS NOT NULL) AND created_at < ?', [$end]);
                    break;
                case 'bergers': {
                    $roles = implode(',', array_map(fn($r) => db()->quote($r), BERGER_ROLES));
                    $series[] = (int) qval("SELECT COUNT(*) FROM users WHERE role IN ($roles) AND created_at < ?", [$end]);
                    break;
                }
                default:
                    $series[] = (int) qval('SELECT COUNT(*) FROM users WHERE created_at < ?', [$end]);
            }
        }
        $history[$section] = $series;
    }
    return $history;
}

function compute_summary_stats(): array
{
    $hist = compute_evolution_history();
    $out = [];
    foreach (CHART_POLES as $pole) {
        $arr = $hist[$pole['key']];
        $n = count($arr);
        $current = $arr[$n - 1];
        $previous = $arr[$n - 2] ?? $current;
        $variation = $current - $previous;
        $deltas = [];
        for ($i = 1; $i < $n; $i++) {
            $deltas[] = $arr[$i] - $arr[$i - 1];
        }
        $average = $deltas ? array_sum($deltas) / count($deltas) : 0;
        $growth = $arr[$n - 1] - $arr[0];
        $trend = $growth > 0 ? 'up' : ($growth < 0 ? 'down' : 'flat');
        $out[] = ['key' => $pole['key'], 'label' => $pole['label'], 'color' => $pole['color'],
                  'current' => $current, 'variation' => $variation, 'average' => $average, 'trend' => $trend];
    }
    return $out;
}

function build_narrative(array $stats): array
{
    usort($stats, fn($a, $b) => $b['variation'] <=> $a['variation']);
    $best = $stats[0];
    $worst = $stats[count($stats) - 1];
    $rising = count(array_filter($stats, fn($s) => $s['trend'] === 'up'));
    $declining = count(array_filter($stats, fn($s) => $s['trend'] === 'down'));
    $total = array_sum(array_column($stats, 'current'));
    $fmt = fn($n) => ($n >= 0 ? '+' : '') . $n;

    return [
        "Sur les 6 derniers mois, l'église totalise <b>{$total}</b> membres enregistrés cumulés, tous pôles confondus.",
        "<b>" . h($best['label']) . "</b> affiche la meilleure dynamique du dernier mois ({$fmt($best['variation'])} par rapport au mois précédent), tandis que <b>" . h($worst['label']) . "</b> enregistre la plus faible variation ({$fmt($worst['variation'])}).",
        "{$rising} pôle(s) sur " . count($stats) . " sont en tendance haussière sur la période, {$declining} en baisse, les autres restent stables.",
    ];
}

/* ------------------------------------------------------------------ */
/* Présences                                                           */
/* ------------------------------------------------------------------ */

/** Dernier culte (le plus récent, sinon le premier). */
function latest_culte(): ?array
{
    $c = qone('SELECT id FROM cultes ORDER BY date_culte IS NULL, date_culte DESC, id LIMIT 1');
    return $c ? get_culte((int) $c['id']) : null;
}

function latest_basonta_of_user(int $userId): ?int
{
    return (int) (qval('SELECT basonta_id FROM users_basontas WHERE user_id = ? ORDER BY basonta_id LIMIT 1', [$userId]) ?: 0) ?: null;
}

/** Statut de présence "rapide" d'un membre pour un type donné. */
function presence_status(array $user, string $type): string
{
    $uid = (int) $user['id'];
    switch ($type) {
        case 'presenceCulte':
            $culte = latest_culte();
            if (!$culte) {
                return '';
            }
            $row = qone('SELECT id FROM presences WHERE user_id = ? AND culte_id = ? LIMIT 1', [$uid, $culte['id']]);
            return $row ? 'Présent' : '';
        case 'presenceBacenta':
            if (empty($user['bacenta_id'])) {
                return '';
            }
            $row = qone('SELECT id FROM presences WHERE user_id = ? AND bacenta_id = ? LIMIT 1', [$uid, (int) $user['bacenta_id']]);
            return $row ? 'Présent' : '';
        case 'presenceBasonta':
            $basontaId = latest_basonta_of_user($uid);
            if (!$basontaId) {
                return '';
            }
            $row = qone('SELECT id FROM presences WHERE user_id = ? AND basonta_id = ? LIMIT 1', [$uid, $basontaId]);
            return $row ? 'Présent' : '';
        case 'presenceCentre': {
            if (empty($user['bacenta_id'])) {
                return '';
            }
            $b = get_bacenta((int) $user['bacenta_id']);
            if (!$b || empty($b['centre_id'])) {
                return '';
            }
            $row = qone('SELECT id FROM presences WHERE user_id = ? AND centre_id = ? LIMIT 1', [$uid, (int) $b['centre_id']]);
            return $row ? 'Présent' : '';
        }
    }
    return '';
}

/** Enregistre (upsert) une présence rapide : 'Présent' → ligne, sinon suppression. */
function save_quick_presence(int $userId, string $type, string $value): void
{
    $user = get_user($userId);
    if (!$user) {
        return;
    }
    $present = ($value === 'Présent');

    switch ($type) {
        case 'presenceCulte':
            $culte = latest_culte();
            if (!$culte) {
                return;
            }
            if ($present) {
                $exists = qval('SELECT id FROM presences WHERE user_id = ? AND culte_id = ?', [$userId, $culte['id']]);
                if (!$exists) {
                    qexec('INSERT INTO presences (user_id, date_presence, culte_id) VALUES (?, CURDATE(), ?)', [$userId, $culte['id']]);
                }
            } else {
                qexec('DELETE FROM presences WHERE user_id = ? AND culte_id = ?', [$userId, $culte['id']]);
            }
            break;
        case 'presenceBacenta':
            if (empty($user['bacenta_id'])) {
                return;
            }
            if ($present) {
                $exists = qval('SELECT id FROM presences WHERE user_id = ? AND bacenta_id = ?', [$userId, $user['bacenta_id']]);
                if (!$exists) {
                    qexec('INSERT INTO presences (user_id, date_presence, bacenta_id) VALUES (?, CURDATE(), ?)', [$userId, $user['bacenta_id']]);
                }
            } else {
                qexec('DELETE FROM presences WHERE user_id = ? AND bacenta_id = ?', [$userId, $user['bacenta_id']]);
            }
            break;
        case 'presenceBasonta':
            $basontaId = latest_basonta_of_user($userId);
            if (!$basontaId) {
                return;
            }
            if ($present) {
                $exists = qval('SELECT id FROM presences WHERE user_id = ? AND basonta_id = ?', [$userId, $basontaId]);
                if (!$exists) {
                    qexec('INSERT INTO presences (user_id, date_presence, basonta_id) VALUES (?, CURDATE(), ?)', [$userId, $basontaId]);
                }
            } else {
                qexec('DELETE FROM presences WHERE user_id = ? AND basonta_id = ?', [$userId, $basontaId]);
            }
            break;
        case 'presenceCentre': {
            if (empty($user['bacenta_id'])) {
                return;
            }
            $b = get_bacenta((int) $user['bacenta_id']);
            if (!$b || empty($b['centre_id'])) {
                return;
            }
            if ($present) {
                $exists = qval('SELECT id FROM presences WHERE user_id = ? AND centre_id = ?', [$userId, $b['centre_id']]);
                if (!$exists) {
                    qexec('INSERT INTO presences (user_id, date_presence, centre_id) VALUES (?, CURDATE(), ?)', [$userId, $b['centre_id']]);
                }
            } else {
                qexec('DELETE FROM presences WHERE user_id = ? AND centre_id = ?', [$userId, $b['centre_id']]);
            }
            break;
        }
    }
}

/** Pointage de présence à un culte (cocher les présents). */
function point_culte_presence(int $culteId, string $date, array $userIds): void
{
    $culte = get_culte($culteId);
    if (!$culte) {
        return;
    }
    // On repart de zéro pour cette date + culte (pointer = recenser les présents).
    qexec('DELETE FROM presences WHERE culte_id = ? AND date_presence = ?', [$culteId, $date]);
    foreach ($userIds as $uid) {
        qexec('INSERT INTO presences (user_id, date_presence, culte_id) VALUES (?, ?, ?)', [(int) $uid, $date, $culteId]);
    }
}

/** Statuts de présence d'un membre pour le donut du profil. */
function member_presence_counts(array $user): array
{
    $present = 0;
    $absent = 0;
    $none = 0;
    foreach (PRESENCE_FIELDS as $f) {
        $s = presence_status($user, $f);
        if ($s === 'Présent') {
            $present++;
        } elseif ($s === 'Absent') {
            $absent++;
        } else {
            $none++;
        }
    }
    return ['present' => $present, 'absent' => $absent, 'none' => $none];
}

/* ------------------------------------------------------------------ */
/* Visites & offrandes                                                 */
/* ------------------------------------------------------------------ */

function get_bacenta_visites(int $bacentaId, string $monthKey): array
{
    $rows = qall(
        "SELECT v.*, CONCAT(u.prenom, ' ', u.nom) AS visite_nom
           FROM visites v LEFT JOIN users u ON u.id = v.visite_user_id
          WHERE v.bacenta_id = ? AND v.mois = ? ORDER BY v.semaine, v.id",
        [$bacentaId, $monthKey]
    );
    $out = [];
    for ($i = 0; $i < 4; $i++) {
        $out[$i] = ['nom_visite' => '', 'date_visite' => '', 'observations' => '', 'visite_user_id' => null];
        foreach ($rows as $r) {
            if ((int) $r['semaine'] === $i) {
                $nom = $r['nom_visite'] ?: ($r['visite_nom'] ?? '');
                $out[$i] = [
                    'nom_visite' => $nom,
                    'date_visite' => $r['date_visite'] ?? '',
                    'observations' => $r['observations'] ?? '',
                    'visite_user_id' => $r['visite_user_id'],
                ];
                break;
            }
        }
    }
    return $out;
}

function save_bacenta_visites(int $bacentaId, string $monthKey, array $visites, int $visiteurId): void
{
    foreach ($visites as $i => $v) {
        $nom = trim((string) ($v['nom_visite'] ?? ''));
        $date = trim((string) ($v['date_visite'] ?? '')) ?: date('Y-m-d');
        $obs = trim((string) ($v['observations'] ?? '')) ?: null;
        $exists = qval('SELECT id FROM visites WHERE bacenta_id = ? AND mois = ? AND semaine = ?', [$bacentaId, $monthKey, $i]);
        if ($exists) {
            qexec('UPDATE visites SET nom_visite = ?, date_visite = ?, observations = ? WHERE id = ?',
                  [$nom ?: null, $date, $obs, (int) $exists]);
        } else {
            qexec('INSERT INTO visites (visiteur_id, bacenta_id, nom_visite, date_visite, mois, semaine, observations) VALUES (?, ?, ?, ?, ?, ?, ?)',
                  [$visiteurId, $bacentaId, $nom ?: null, $date, $monthKey, $i, $obs]);
        }
    }
}

/** Offrandes d'un bacenta ou d'un centre pour un mois (4 semaines). */
function get_offrandes_month(string $type, int $entityId, string $monthKey): array
{
    $col = $type === 'centre' ? 'centre_id' : 'bacenta_id';
    $rows = qall("SELECT jour_index, montant FROM offrandes WHERE $col = ? AND mois = ? ORDER BY jour_index", [$entityId, $monthKey]);
    $out = [0, 0, 0, 0];
    foreach ($rows as $r) {
        $out[(int) $r['jour_index']] = (int) $r['montant'];
    }
    return $out;
}

function save_offrandes_month(string $type, int $entityId, string $monthKey, array $vals): void
{
    $col = $type === 'centre' ? 'centre_id' : 'bacenta_id';
    $vals = array_pad(array_slice($vals, 0, 4), 4, 0);
    $vals = array_map(fn($v) => max(0, (int) $v), $vals);
    foreach ($vals as $i => $montant) {
        $exists = qval("SELECT id FROM offrandes WHERE $col = ? AND mois = ? AND jour_index = ?", [$entityId, $monthKey, $i]);
        if ($exists) {
            qexec('UPDATE offrandes SET montant = ?, date_offrande = ? WHERE id = ?',
                  [$montant, date('Y-m-d', strtotime("first day of $monthKey") + $i * 86400 * 7), (int) $exists]);
        } elseif ($montant > 0) {
            qexec("INSERT INTO offrandes ($col, montant, date_offrande, mois, jour_index) VALUES (?, ?, ?, ?, ?)",
                  [$entityId, $montant, date('Y-m-d', strtotime("first day of $monthKey") + $i * 86400 * 7), $monthKey, $i]);
        }
    }
}

function sum_offrandes_month_total(string $type, int $entityId, string $monthKey): int
{
    return array_sum(get_offrandes_month($type, $entityId, $monthKey));
}

function sum_offrandes_year_total(string $type, int $entityId, int $year): int
{
    $col = $type === 'centre' ? 'centre_id' : 'bacenta_id';
    return (int) qval("SELECT COALESCE(SUM(montant),0) FROM offrandes WHERE $col = ? AND mois LIKE ?", [$entityId, $year . '-%']);
}

function sum_offrandes_section_year(string $type, int $year): int
{
    $col = $type === 'centre' ? 'centre_id' : 'bacenta_id';
    return (int) qval("SELECT COALESCE(SUM(montant),0) FROM offrandes WHERE $col IS NOT NULL AND mois LIKE ?", [$year . '-%']);
}

function global_offrandes_year(int $year): int
{
    return sum_offrandes_section_year('bacenta', $year) + sum_offrandes_section_year('centre', $year);
}

/* ------------------------------------------------------------------ */
/* Fiche berger : dîmes / examens / veillées / suivi                   */
/* ------------------------------------------------------------------ */

function get_dimes(int $userId, int $year): array
{
    $rows = qall('SELECT mois, montant FROM dimes WHERE user_id = ? AND annee = ?', [$userId, $year]);
    $out = array_fill(0, 12, 0);
    foreach ($rows as $r) {
        $out[(int) $r['mois'] - 1] = (int) $r['montant'];
    }
    return $out;
}

function save_dimes(int $userId, int $year, array $vals): void
{
    foreach (array_slice($vals, 0, 12) as $i => $v) {
        $montant = max(0, (int) $v);
        $mois = $i + 1;
        $exists = qval('SELECT id FROM dimes WHERE user_id = ? AND annee = ? AND mois = ?', [$userId, $year, $mois]);
        if ($exists) {
            qexec('UPDATE dimes SET montant = ? WHERE id = ?', [$montant, (int) $exists]);
        } elseif ($montant > 0) {
            qexec('INSERT INTO dimes (user_id, annee, mois, montant) VALUES (?, ?, ?, ?)', [$userId, $year, $mois, $montant]);
        }
    }
}

function get_examens(int $userId): array
{
    return qall('SELECT * FROM examens WHERE user_id = ? ORDER BY date_exam DESC, id DESC', [$userId]);
}

function add_examen(int $userId, string $nom, ?string $date): void
{
    qexec('INSERT INTO examens (user_id, nom, date_exam) VALUES (?, ?, ?)', [$userId, $nom, $date ?: null]);
}

function delete_examen(int $userId, int $examenId): void
{
    qexec('DELETE FROM examens WHERE id = ? AND user_id = ?', [$examenId, $userId]);
}

function get_veillees(int $userId): array
{
    return qall('SELECT * FROM veillees WHERE user_id = ? ORDER BY date_veillee DESC, id DESC', [$userId]);
}

function add_veillee(int $userId, string $date, bool $present): void
{
    qexec('INSERT INTO veillees (user_id, date_veillee, present) VALUES (?, ?, ?)', [$userId, $date, (int) $present]);
}

function delete_veillee(int $userId, int $veilleeId): void
{
    qexec('DELETE FROM veillees WHERE id = ? AND user_id = ?', [$veilleeId, $userId]);
}

function get_suivi_week(int $userId, string $weekKey): array
{
    $rows = qall('SELECT jour, champ, valeur FROM suivi_hebdo WHERE user_id = ? AND semaine = ?', [$userId, $weekKey]);
    $out = [];
    foreach (WEEK_DAYS as $day) {
        $out[$day] = [];
    }
    foreach ($rows as $r) {
        $out[$r['jour']][$r['champ']] = $r['valeur'];
    }
    return $out;
}

function save_suivi_week(int $userId, string $weekKey, array $data): void
{
    foreach ($data as $day => $fields) {
        foreach ($fields as $field => $value) {
            $value = trim((string) $value);
            $exists = qval('SELECT id FROM suivi_hebdo WHERE user_id = ? AND semaine = ? AND jour = ? AND champ = ?',
                           [$userId, $weekKey, $day, $field]);
            if ($exists) {
                qexec('UPDATE suivi_hebdo SET valeur = ? WHERE id = ?', [$value, (int) $exists]);
            } elseif ($value !== '') {
                qexec('INSERT INTO suivi_hebdo (user_id, semaine, jour, champ, valeur) VALUES (?, ?, ?, ?, ?)',
                      [$userId, $weekKey, $day, $field, $value]);
            }
        }
    }
}

function is_field_filled(array $f, $v): bool
{
    if ($v === null || $v === '') {
        return false;
    }
    if (($f['type'] ?? 'text') === 'number') {
        return (float) $v > 0;
    }
    return true;
}

function compute_week_completion(array $week): int
{
    $filled = 0;
    $total = 0;
    foreach (WEEK_DAYS as $day) {
        $data = $week[$day] ?? [];
        foreach (SUIVI_FIELDS as $f) {
            if (!empty($f['sundayOnly']) && $day !== 'Dimanche') {
                continue;
            }
            $total++;
            if (is_field_filled($f, $data[$f['key']] ?? '')) {
                $filled++;
            }
        }
    }
    return $total ? (int) round(($filled / $total) * 100) : 0;
}

function compute_year_completion(int $userId, int $year): int
{
    $weeks = qall('SELECT DISTINCT semaine FROM suivi_hebdo WHERE user_id = ? AND semaine LIKE ?', [$userId, $year . '-W%']);
    if (!$weeks) {
        return 0;
    }
    $total = 0;
    foreach ($weeks as $w) {
        $total += compute_week_completion(get_suivi_week($userId, $w['semaine']));
    }
    return (int) round($total / count($weeks));
}

/* ------------------------------------------------------------------ */
/* Recherche / divers                                                  */
/* ------------------------------------------------------------------ */

function search_people(string $q): array
{
    $out = [];
    $q = mb_lower(trim($q));
    if ($q === '') {
        return $out;
    }
    $rows = qall(
        "SELECT * FROM users WHERE LOWER(CONCAT(prenom, ' ', nom)) LIKE ? OR LOWER(CONCAT(nom, ' ', prenom)) LIKE ?
         ORDER BY prenom, nom LIMIT 20",
        ['%' . $q . '%', '%' . $q . '%']
    );
    foreach ($rows as $u) {
        $out[] = ['user' => $u];
    }
    return $out;
}

function get_presentation(): array
{
    $row = qone('SELECT accroche, histoire FROM presentation ORDER BY id LIMIT 1');
    return $row ?: ['accroche' => '', 'histoire' => ''];
}

function save_presentation(string $accroche, string $histoire): void
{
    $exists = qval('SELECT COUNT(*) FROM presentation');
    if ($exists) {
        qexec('UPDATE presentation SET accroche = ?, histoire = ?', [$accroche, $histoire]);
    } else {
        qexec('INSERT INTO presentation (accroche, histoire) VALUES (?, ?)', [$accroche, $histoire]);
    }
}

function get_equipe(): array
{
    return qall('SELECT * FROM equipe ORDER BY id');
}

function get_centres_articles(): array
{
    return qall(
        "SELECT cp.*, c.nom AS centre_nom FROM centres_presentation cp JOIN centres c ON c.id = cp.centre_id ORDER BY cp.id"
    );
}

function get_centre_article(int $id): ?array
{
    return qone(
        "SELECT cp.*, c.nom AS centre_nom FROM centres_presentation cp JOIN centres c ON c.id = cp.centre_id WHERE cp.id = ?",
        [$id]
    );
}

/* ------------------------------------------------------------------ */
/* CRUD users                                                          */
/* ------------------------------------------------------------------ */

/** Insère un utilisateur (retourne son id). */
function insert_user(array $data): int
{
    $cols = array_keys($data);
    return qexec('INSERT INTO users (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')',
                 array_values($data));
}

/** Champs d'un membre depuis $_POST (limités à la liste autorisée). */
function user_data_from_post(?int $existingId = null): array
{
    $data = [];
    foreach (['nom', 'prenom', 'telephone', 'email', 'quartier'] as $f) {
        $data[$f] = trim((string) ($_POST[$f] ?? ''));
    }
    $data['date_naissance'] = trim((string) ($_POST['date_naissance'] ?? '')) ?: null;
    $data['role'] = in_array($_POST['role'] ?? '', ['admin', 'leader', 'assistant', 'pasteur', 'reverant', 'membre', 'responsable'], true)
        ? $_POST['role'] : 'membre';
    $data['bacenta_id'] = (int) ($_POST['bacenta_id'] ?? 0) ?: null;
    $data['compte_actif'] = isset($_POST['compte_actif']) ? 1 : 0;
    $data['invite_par'] = (int) ($_POST['invite_par'] ?? 0) ?: null;
    $data['recu_par'] = (int) ($_POST['recu_par'] ?? 0) ?: null;
    $data['date_recu'] = trim((string) ($_POST['date_recu'] ?? '')) ?: null;
    return $data;
}

function email_taken(string $email, ?int $exceptId = null): bool
{
    $sql = 'SELECT COUNT(*) FROM users WHERE LOWER(email) = ?';
    $params = [mb_lower(trim($email))];
    if ($exceptId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $exceptId;
    }
    return (bool) qval($sql, $params);
}

function insert_user_from_post(array $data, ?string $photo): int
{
    $data['password'] = password_hash((string) ($_POST['password'] ?? ''), PASSWORD_DEFAULT);
    $data['photo_de_profil'] = $photo;
    return insert_user($data);
}

function update_user_from_post(int $id, array $data, ?string $photo, ?string $newPassword): void
{
    $sets = 'nom = ?, prenom = ?, telephone = ?, email = ?, quartier = ?, date_naissance = ?, role = ?, bacenta_id = ?, compte_actif = ?, invite_par = ?, recu_par = ?, date_recu = ?';
    $params = [$data['nom'], $data['prenom'], $data['telephone'], $data['email'], $data['quartier'],
               $data['date_naissance'], $data['role'], $data['bacenta_id'], $data['compte_actif'],
               $data['invite_par'], $data['recu_par'], $data['date_recu']];
    if ($photo) {
        $sets .= ', photo_de_profil = ?';
        $params[] = $photo;
    }
    if ($newPassword !== null && $newPassword !== '') {
        $sets .= ', password = ?';
        $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    $params[] = $id;
    qexec("UPDATE users SET $sets WHERE id = ?", $params);
}

function delete_user(int $id): void
{
    $adminCount = (int) qval("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $u = get_user($id);
    if ($u && $u['role'] === 'admin' && $adminCount <= 1) {
        return; // dernier admin protégé
    }
    // L'utilisateur devient inactif et ses liens sont rompus proprement.
    qexec('DELETE FROM users_basontas WHERE user_id = ?', [$id]);
    qexec('UPDATE bacentas SET responsable_id = NULL WHERE responsable_id = ?', [$id]);
    qexec('UPDATE basontas SET responsable_id = NULL WHERE responsable_id = ?', [$id]);
    qexec('UPDATE cultes SET responsable_id = NULL WHERE responsable_id = ?', [$id]);
    qexec('DELETE FROM users WHERE id = ?', [$id]);
}

function delete_user_photo(?string $path): void
{
    delete_photo_file($path);
}

function get_users(): array
{
    return qall('SELECT * FROM users ORDER BY role, prenom, nom');
}
