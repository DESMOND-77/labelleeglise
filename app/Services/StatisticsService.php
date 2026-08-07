<?php

namespace App\Services;

use App\Core\Query;
use App\Repositories\UserRepository;
use App\Repositories\AttendanceRepository;

/**
 * Statistiques du tableau de bord : compteurs, évolution, synthèse.
 */
class StatisticsService
{
    private UserRepository $users;
    private AttendanceRepository $attendance;

    public function __construct(?UserRepository $users = null, ?AttendanceRepository $attendance = null)
    {
        $this->users = $users ?? new UserRepository();
        $this->attendance = $attendance ?? new AttendanceRepository();
    }

    /** Nombre de membres d'un pôle. */
    public function countMembers(string $section): int
    {
        switch ($section) {
            case 'bacentas':
                return $this->users->countWithBacenta();
            case 'centres':
                return $this->users->countWithCentre();
            case 'cultes':
                return $this->attendance->countDistinctForCultes();
            case 'basontas':
                return $this->attendance->countDistinctForBasontas();
            case 'nouveaux':
                return $this->users->countNew();
            case 'bergers':
                return $this->users->countByRoleIn(BERGER_ROLES);
            default:
                return $this->users->countAll();
        }
    }

    /** Nombre cumulé de membres enregistrés mois par mois sur 6 mois. */
    public function evolutionHistory(): array
    {
        $buckets = get_month_buckets(6);
        $history = ['labels' => array_map(fn($b) => $b['label'], $buckets)];

        foreach (CHART_POLES as $pole) {
            $section = $pole['key'];
            $series = [];
            foreach ($buckets as $b) {
                $end = $b['end']->format('Y-m-d 00:00:00');
                $series[] = $this->countMembersBefore($section, $end);
            }
            $history[$section] = $series;
        }
        return $history;
    }

    private function countMembersBefore(string $section, string $end): int
    {
        switch ($section) {
            case 'bacentas':
                return (int) Query::value('SELECT COUNT(*) FROM users WHERE bacenta_id IS NOT NULL AND created_at < ?', [$end]);
            case 'centres':
                return (int) Query::value('SELECT COUNT(*) FROM users u JOIN bacentas b ON b.id = u.bacenta_id WHERE u.created_at < ?', [$end]);
            case 'cultes':
                return (int) Query::value('SELECT COUNT(DISTINCT p.user_id) FROM presences p JOIN users u ON u.id = p.user_id WHERE p.culte_id IS NOT NULL AND u.created_at < ?', [$end]);
            case 'basontas':
                return (int) Query::value('SELECT COUNT(DISTINCT ub.user_id) FROM users_basontas ub JOIN users u ON u.id = ub.user_id WHERE u.created_at < ?', [$end]);
            case 'nouveaux':
                return (int) Query::value('SELECT COUNT(*) FROM users WHERE (recu_par IS NOT NULL OR date_recu IS NOT NULL OR invite_par IS NOT NULL) AND created_at < ?', [$end]);
            case 'bergers': {
                $roles = implode(',', array_map(fn($r) => \App\Core\Database::connection()->quote($r), BERGER_ROLES));
                return (int) Query::value("SELECT COUNT(*) FROM users WHERE role IN ($roles) AND created_at < ?", [$end]);
            }
            default:
                return (int) Query::value('SELECT COUNT(*) FROM users WHERE created_at < ?', [$end]);
        }
    }

    public function summaryStats(): array
    {
        $hist = $this->evolutionHistory();
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

    public function narrative(array $stats): array
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
            "<b>" . htmlspecialchars($best['label'], ENT_QUOTES, 'UTF-8') . "</b> affiche la meilleure dynamique du dernier mois ({$fmt($best['variation'])} par rapport au mois précédent), tandis que <b>" . htmlspecialchars($worst['label'], ENT_QUOTES, 'UTF-8') . "</b> enregistre la plus faible variation ({$fmt($worst['variation'])}).",
            "{$rising} pôle(s) sur " . count($stats) . " sont en tendance haussière sur la période, {$declining} en baisse, les autres restent stables.",
        ];
    }
}
