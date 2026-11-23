<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Query;
use App\Core\Request;

/**
 * Suivi des Rapports du Jour : checklist, tableau de bord, classement,
 * fiche responsable et paramètres du module.
 * Aucun SQL ici : tout passe par les services/repositories.
 */
class RapportSuiviController extends Controller
{
    private function requireSuiviUser(): array
    {
        $user = current_user();
        if (!$user || !auth_can_view_rapport_suivi()) {
            $this->redirect('index.php', ['page' => 'accueil']);
        }
        return $user;
    }

    /** Synchronisation des occurrences, au plus une fois par minute et par requête HTTP. */
    private function syncThrottled(): void
    {
        try {
            $settings = rapport_settings_service();
            $last = trim((string) ($settings->get('last_sync_at') ?? ''));
            if ($last !== '' && strtotime($last) !== false && strtotime($last) > time() - 60) {
                return;
            }
            rapport_occurrence_service()->sync();
            $settings->setMany(['last_sync_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Synchronisation des occurrences échouée', ['error' => $e->getMessage()]);
        }
    }

    /** @return array<string,mixed> */
    private function filters(string $defaultPeriode = 'semaine'): array
    {
        $periode = (string) (Request::get('periode') ?? $defaultPeriode);
        if (!in_array($periode, ['semaine', 'mois', 'custom'], true)) {
            $periode = $defaultPeriode;
        }

        $analytics = rapport_analytics_service();
        $from = $this->dateOrNull(Request::get('from'));
        $to = $this->dateOrNull(Request::get('to'));

        if ($periode === 'custom' && $from && $to && $from <= $to) {
            $period = $analytics->resolvePeriod('custom', $from, $to);
        } elseif ($periode === 'mois') {
            $period = $analytics->resolvePeriod('month', null, null);
        } else {
            $periode = $periode === 'custom' ? 'semaine' : $periode;
            $period = $analytics->resolvePeriod('week', null, null);
        }

        $centre = (int) (Request::get('centre') ?? 0) ?: null;
        $bacenta = (int) (Request::get('bacenta') ?? 0) ?: null;
        $responsable = (int) (Request::get('responsable') ?? 0) ?: null;
        $statut = trim((string) (Request::get('statut') ?? ''));
        if (!in_array($statut, ['pending', 'due', 'overdue', 'filled_on_time', 'filled_late', 'cancelled'], true)) {
            $statut = '';
        }
        $type = (string) (Request::get('type') ?? '');
        if (!in_array($type, ['centre', 'bacenta'], true)) {
            $type = '';
        }
        $q = mb_substr(trim((string) (Request::get('q') ?? '')), 0, 80);
        $vue = (string) (Request::get('vue') ?? '') === 'mes' ? 'mes' : 'all';

        return [
            'periode' => $periode,
            'from' => $period['from'],
            'to' => $period['to'],
            'from_input' => $from ?? $period['from'],
            'to_input' => $to ?? $period['to'],
            'period' => $period,
            'centre_id' => $centre,
            'bacenta_id' => $bacenta,
            'user_id' => $responsable,
            'status' => $statut,
            'scope_type' => $type,
            'q' => $q,
            'vue' => $vue,
        ];
    }

    private function dateOrNull($value): ?string
    {
        $v = trim((string) ($value ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    private function filterQueryParams(array $filters): array
    {
        return array_filter([
            'periode' => $filters['periode'],
            'from' => $filters['periode'] === 'custom' ? $filters['from_input'] : null,
            'to' => $filters['periode'] === 'custom' ? $filters['to_input'] : null,
            'centre' => $filters['centre_id'],
            'bacenta' => $filters['bacenta_id'],
            'responsable' => $filters['user_id'],
            'statut' => $filters['status'] !== '' ? $filters['status'] : null,
            'type' => $filters['scope_type'] !== '' ? $filters['scope_type'] : null,
            'q' => $filters['q'] !== '' ? $filters['q'] : null,
            'vue' => $filters['vue'],
        ], static fn($v) => $v !== null && $v !== '');
    }

    /* ================= Checklist ================= */

    public function checklist(): void
    {
        $user = $this->requireSuiviUser();
        $this->syncThrottled();

        $isAdmin = ($user['role'] ?? '') === 'admin';
        $filters = $this->filters();
        // Par défaut : « tout mon périmètre » pour l'admin et les responsables scopés.
        if ((Request::get('vue') ?? '') === '' && ($isAdmin || rapport_scope_service()->hasManagerScope($user))) {
            $filters['vue'] = 'all';
        }
        if (!$isAdmin && !rapport_scope_service()->hasManagerScope($user)) {
            $filters['vue'] = 'mes';
        }

        $page = max(1, (int) (Request::get('p') ?? 1));
        $result = rapport_analytics_service()->checklist($filters, $user, $page);

        // Listes déroulantes de filtre (responsables ayant des échéances sur la période).
        $pairs = rapport_scope_service()->pairsForUser($user);
        $responsables = rapport_occurrence_repository_for_filters($filters, $pairs);

        $centres = get_centres();
        if (!$isAdmin && $pairs !== null) {
            // Centres autorisés : gérés directement, ou parents des bacentas gérés.
            $allowedCentreIds = [];
            $managedBacentaIds = [];
            foreach ($pairs as $pair) {
                if ($pair['type'] === 'centre') {
                    $allowedCentreIds[] = (int) $pair['id'];
                } elseif ($pair['type'] === 'bacenta') {
                    $managedBacentaIds[] = (int) $pair['id'];
                }
            }
            if ($managedBacentaIds !== []) {
                $rows = Query::all(
                    'SELECT DISTINCT centre_id FROM bacentas WHERE id IN (' . implode(',', array_fill(0, count($managedBacentaIds), '?')) . ')',
                    $managedBacentaIds
                );
                foreach ($rows as $row) {
                    $allowedCentreIds[] = (int) $row['centre_id'];
                }
            }
            $allowedCentreIds = array_values(array_unique($allowedCentreIds));
            $centres = array_values(array_filter($centres, static fn($c) => in_array((int) $c['id'], $allowedCentreIds, true)));
        }

        render_page(SECTION_LABELS['rapport_suivi'], view('pages/rapport_suivi', [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'perPage' => $result['per_page'],
            'filters' => $filters,
            'filterQuery' => $this->filterQueryParams($filters),
            'centres' => $centres,
            'responsables' => $responsables,
            'isAdmin' => $isAdmin,
            'canSettings' => $isAdmin,
            'currentUserId' => (int) $user['id'],
            'saved' => (string) (Request::get('saved') ?? ''),
            'error' => (string) (Request::get('error') ?? ''),
            'csrf' => csrf_field(),
        ]));
    }

    /* ================= Export CSV ================= */

    public function export(): void
    {
        $user = $this->requireSuiviUser();
        $filters = $this->filters();
        $pairs = rapport_scope_service()->pairsForUser($user);
        if ((Request::get('vue') ?? '') === 'mes' || (($user['role'] ?? '') !== 'admin' && $pairs !== null && $pairs === [])) {
            $filters['vue'] = 'mes';
        }
        $rows = rapport_occurrence_repository()->checklist($filters, 5000, 0, $pairs, (int) $user['id']);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="suivi-rapports.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Date', 'Type', 'Structure', 'Responsables', 'Heure limite', 'Statut', 'Rapport #', 'Auteur', 'Dernier rappel']);
        $statusLabels = rapport_status_labels();
        foreach ($rows as $r) {
            fputcsv($out, [
                (string) $r['report_date'],
                $r['scope_type'] === 'centre' ? 'Centre' : 'Bacenta',
                (string) ($r['cible_nom'] ?? ''),
                (string) ($r['responsables_noms'] ?? ''),
                (string) ($r['due_at'] ?? 'non configurée'),
                $statusLabels[$r['status']] ?? (string) $r['status'],
                $r['submitted_report_id'] !== null ? (string) $r['submitted_report_id'] : '',
                (string) ($r['auteur_nom'] ?? ''),
                (string) ($r['dernier_rappel'] ?? ''),
            ]);
        }
        fclose($out);
        exit;
    }

    /* ================= Tableau de bord ================= */

    public function dashboard(): void
    {
        $user = $this->requireSuiviUser();
        $this->syncThrottled();

        $isAdmin = ($user['role'] ?? '') === 'admin';
        $filters = $this->filters();
        $period = $filters['period'];
        $data = rapport_analytics_service()->dashboard((string) $period['kind'], $period['from'], $period['to'], $user);
        // Le filtre de période du tableau de bord suit « semaine|mois|custom ».
        $data['filters'] = $filters;

        $configProblems = [];
        if ($isAdmin) {
            $occ = rapport_occurrence_repository();
            $configProblems = rapport_settings_service()->configurationProblems(
                $occ->allCentresCalendar(),
                $occ->allBacentasCalendar()
            );
        }

        $daily = $data['daily'];
        $charts = [
            'rapport' => [
                'evolution' => [
                    'labels' => array_map(static fn($r) => date('d/m', strtotime((string) $r['day'])), $daily),
                    'attendus' => array_map(static fn($r) => (int) $r['expected'], $daily),
                    'soumis' => array_map(static fn($r) => (int) $r['submitted'], $daily),
                ],
                'structure' => [
                    'labels' => array_map(static fn($r) => (string) $r['cible_nom'], $data['by_structure']),
                    'attendus' => array_map(static fn($r) => (int) $r['expected'], $data['by_structure']),
                    'soumis' => array_map(static fn($r) => (int) $r['submitted'], $data['by_structure']),
                ],
                'status' => [
                    'labels' => ['À venir', 'À remplir', 'En retard', 'Remplis à temps', 'Remplis en retard', 'Annulés'],
                    'data' => [
                        $data['by_status']['pending'],
                        $data['by_status']['due'],
                        $data['by_status']['overdue'],
                        $data['by_status']['filled_on_time'],
                        $data['by_status']['filled_late'],
                        $data['by_status']['cancelled'],
                    ],
                ],
            ],
        ];

        render_page(SECTION_LABELS['rapport_dashboard'], view('pages/rapport_dashboard', [
            'data' => $data,
            'filters' => $filters,
            'filterQuery' => $this->filterQueryParams($filters),
            'isAdmin' => $isAdmin,
            'configProblems' => $configProblems,
            'failedReminders' => $isAdmin ? rapport_reminder_service()->failedCount() : 0,
        ]), $charts);
    }

    /* ================= Classement ================= */

    public function classement(): void
    {
        $user = current_user();
        if (!$user || !auth_can_view_rapport_classement()) {
            render_page(SECTION_LABELS['rapport_classement'], empty_state('fa-lock', 'Accès réservé aux administrateurs et aux responsables autorisés, pour leur périmètre.'));
            return;
        }
        $this->syncThrottled();

        $isAdmin = ($user['role'] ?? '') === 'admin';
        $filters = $this->filters('mois');
        $pairs = rapport_scope_service()->pairsForUser($user);
        $rows = rapport_analytics_service()->classement($filters['from'], $filters['to'], $pairs);

        render_page(SECTION_LABELS['rapport_classement'], view('pages/rapport_classement', [
            'rows' => $rows,
            'filters' => $filters,
            'filterQuery' => $this->filterQueryParams($filters),
            'isAdmin' => $isAdmin,
            'scopeRestricted' => !$isAdmin,
            'currentUserId' => (int) $user['id'],
        ]));
    }

    /* ================= Fiche responsable ================= */

    public function responsable(): void
    {
        $user = $this->requireSuiviUser();
        $targetUserId = (int) (Request::get('id') ?? 0);
        if ($targetUserId <= 0) {
            $this->redirect('index.php', ['page' => 'rapport_suivi']);
        }
        $target = get_user($targetUserId);
        if (!$target) {
            $this->redirect('index.php', ['page' => 'rapport_suivi']);
        }

        $uid = (int) $user['id'];
        $isAdmin = ($user['role'] ?? '') === 'admin';
        $isSelf = $uid === $targetUserId;

        // Spectateur limité : statistiques restreintes à son périmètre (l'historique des bilans n'est pas exposé).
        $viewerPairs = null;
        if (!$isAdmin && !$isSelf) {
            $viewerPairs = rapport_scope_service()->pairsForUser($user);
            if (!rapport_scope_service()->canViewResponsible($user, $targetUserId)) {
                render_page(SECTION_LABELS['rapport_responsable'], empty_state('fa-lock', 'Vous ne pouvez pas consulter le suivi de cette personne.'));
                return;
            }
        }

        $sheet = rapport_analytics_service()->responsableSheet($targetUserId, $viewerPairs);

        // Responsabilités actuelles de la personne (affichage).
        $responsabilites = Query::all(
            "SELECT r.target_type, r.target_id,
                    CASE r.target_type
                        WHEN 'center' THEN (SELECT nom FROM centres WHERE id = r.target_id)
                        WHEN 'bacenta' THEN (SELECT nom FROM bacentas WHERE id = r.target_id)
                        ELSE NULL
                    END AS cible_nom,
                    r.created_at
               FROM responsibilities r
              WHERE r.user_id = ? AND r.responsibility_type = 'manager' AND r.target_type IN ('center', 'bacenta')
              ORDER BY r.created_at",
            [$targetUserId]
        );

        render_page(SECTION_LABELS['rapport_responsable'], view('pages/rapport_responsable', [
            'target' => $target,
            'sheet' => $sheet,
            'responsabilites' => $responsabilites,
            'isSelf' => $isSelf,
            'isAdmin' => $isAdmin,
            'restricted' => $viewerPairs !== null,
        ]));
    }

    /* ================= Paramètres du module ================= */

    public function parametres(): void
    {
        $user = current_user();
        if (!$user || !auth_can_manage_rapport_settings()) {
            render_page(SECTION_LABELS['rapport_parametres'], empty_state('fa-lock', 'Accès réservé à l\'administrateur.'));
            return;
        }
        $this->syncThrottled();

        $occ = rapport_occurrence_repository();
        $settings = rapport_settings_service()->all();
        $rules = rapport_scoring_service()->allRules();
        $weekDays = WEEK_DAYS;
        $centresCalendar = $occ->allCentresCalendar();
        $bacentasCalendar = $occ->allBacentasCalendar();
        $configProblems = rapport_settings_service()->configurationProblems($centresCalendar, $bacentasCalendar);
        $auditRecent = rapport_audit_repository()->recent(30);
        $failedReminders = rapport_reminder_service()->failedRecent(10);
        $lastRun = $_SESSION['rapport_last_run'] ?? null;
        if (is_array($lastRun)) {
            unset($_SESSION['rapport_last_run']);
        } else {
            $lastRun = null;
        }

        $editRuleId = (int) (Request::get('edit_rule') ?? 0);
        $editRule = $editRuleId > 0 ? rapport_scoring_service()->findRule($editRuleId) : null;

        render_page(SECTION_LABELS['rapport_parametres'], view('pages/rapport_parametres', [
            'settings' => $settings,
            'rules' => $rules,
            'weekDays' => $weekDays,
            'centres' => $centresCalendar,
            'bacentas' => $bacentasCalendar,
            'configProblems' => $configProblems,
            'auditRecent' => $auditRecent,
            'failedReminders' => $failedReminders,
            'cronToken' => rapport_settings_service()->cronToken() ?? '',
            'lastRun' => $lastRun,
            'editRule' => $editRule,
            'saved' => (string) (Request::get('saved') ?? ''),
            'error' => (string) (Request::get('error') ?? ''),
            'csrf' => csrf_field(),
        ]));
    }
}
