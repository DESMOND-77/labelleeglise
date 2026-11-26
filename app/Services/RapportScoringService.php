<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RapportOccurrenceRepository;
use App\Repositories\RapportScoringRepository;
use App\Repositories\RapportSettingsRepository;
use App\Repositories\UserRepository;

/**
 * Barème configurable et félicitations / bilans périodiques.
 *
 * Règles d'or :
 *  - les seuils sont des intervalles [min, max[ en pourcentage (100 inclus),
 *    validés côté serveur : bornes 0-100, sans chevauchement, couverture
 *    complète de la plage - un barème invalide n'est jamais persisté ;
 *  - l'historique n'est jamais réécrit : chaque résultat historisé porte la
 *    version du barème et un instantané du message ;
 *  - « Non évalué » si aucune échéance attendue (jamais 0 %) ;
 *  - une seule notification par responsable, période et version de barème.
 */
class RapportScoringService
{
    private RapportScoringRepository $rules;
    private RapportOccurrenceRepository $occurrences;
    private RapportSettingsService $settings;
    private UserRepository $users;

    public function __construct(
        ?RapportScoringRepository $rules = null,
        ?RapportOccurrenceRepository $occurrences = null,
        ?RapportSettingsRepository $settingsRepo = null,
        ?UserRepository $users = null
    ) {
        $this->rules = $rules ?? new RapportScoringRepository();
        $this->occurrences = $occurrences ?? new RapportOccurrenceRepository();
        $this->settings = new RapportSettingsService($settingsRepo ?? new RapportSettingsRepository());
        $this->users = $users ?? new UserRepository();
    }

    /* ================= Barème : lecture ================= */

    public function allRules(): array
    {
        return $this->rules->allRules();
    }

    public function activeRules(): array
    {
        return $this->rules->activeRules();
    }

    public function findRule(int $id): ?array
    {
        return $this->rules->findRule($id);
    }

    /** Version stable du barème actif (empreinte des seuils et messages). */
    public function rulesVersion(): string
    {
        $active = array_map(
            static fn($r) => [
                'min' => (string) $r['min_rate'],
                'max' => (string) $r['max_rate'],
                'level' => (string) $r['level_key'],
                'label' => (string) $r['label'],
                'message' => (string) $r['message_template'],
            ],
            $this->rules->activeRules()
        );
        return substr(sha1((string) json_encode($active, JSON_UNESCAPED_UNICODE)), 0, 12);
    }

    /** Niveau de barème pour un taux (null ⇒ « Non évalué » à afficher). */
    public function levelForRate(?float $rate): ?array
    {
        if ($rate === null) {
            return null;
        }
        foreach ($this->rules->activeRules() as $rule) {
            $min = (float) $rule['min_rate'];
            $max = (float) $rule['max_rate'];
            // Intervalle [min, max[ ; le point 100 appartient à toute règle dont max ≥ 100.
            if ($rate >= $min && ($rate < $max || (abs($rate - 100.0) < 0.00001 && $max >= 100.0))) {
                return $rule;
            }
        }
        return null;
    }

    /* ================= Barème : validation & sauvegarde ================= */

    /**
     * Validation serveur d'un ENSEMBLE de règles (appelée sur l'état résultant
     * d'une modification) : bornes 0-100, intervalles valides, unicité des
     * niveaux, couverture complète sans chevauchement.
     *
     * @param array<int,array<string,mixed>> $rules lignes (min_rate, max_rate, level_key, label, message_template, is_active)
     * @return array<int,string> messages d'erreur (vide = valide)
     */
    public function validateRuleSet(array $rules): array
    {
        $errors = [];
        $active = array_values(array_filter($rules, static fn($r) => !empty($r['is_active'])));
        if (!$active) {
            $errors[] = 'Au moins une règle active est requise.';
            return $errors;
        }

        $keys = [];
        foreach ($active as $i => $rule) {
            $min = (float) ($rule['min_rate'] ?? -1);
            $max = (float) ($rule['max_rate'] ?? -1);
            $label = trim((string) ($rule['label'] ?? ''));
            $key = trim((string) ($rule['level_key'] ?? ''));
            $message = trim((string) ($rule['message_template'] ?? ''));
            if ($min < 0 || $max > 100 || $min > $max) {
                $errors[] = 'Règle « ' . ($label !== '' ? $label : '#' . ($i + 1)) . ' » : les bornes doivent être dans [0, 100] avec min ≤ max.';
            } elseif ($min === $max && !($min >= 100.0)) {
                $errors[] = 'Règle « ' . ($label !== '' ? $label : '#' . ($i + 1)) . ' » : un intervalle de largeur nulle n’est admis qu’au point 100.';
            }
            if ($label === '') {
                $errors[] = 'Chaque règle doit avoir un libellé.';
            }
            if ($message === '') {
                $errors[] = 'Chaque règle doit avoir un message de félicitations/encouragement.';
            }
            if ($key === '') {
                $errors[] = 'Chaque règle doit avoir une clé de niveau.';
            } elseif (isset($keys[$key])) {
                $errors[] = 'Clé de niveau en double : « ' . $key . ' ».';
            }
            $keys[$key] = true;
        }
        if ($errors) {
            return $errors;
        }

        // Couverture complète sans chevauchement : balayage par borne basse.
        usort($active, static function ($a, $b) {
            $c = (float) $a['min_rate'] <=> (float) $b['min_rate'];
            return $c !== 0 ? $c : ((float) $b['max_rate'] <=> (float) $a['max_rate']);
        });
        $cursor = 0.0;
        foreach ($active as $rule) {
            $min = (float) $rule['min_rate'];
            $max = (float) $rule['max_rate'];
            if ($min > $cursor + 0.00001) {
                $errors[] = 'Trou dans la couverture : aucune règle active ne couvre ' . rtrim(rtrim(number_format($cursor, 2, '.', ''), '0'), '.') . ' %.';
            } elseif ($min < $cursor - 0.00001) {
                $errors[] = 'Chevauchement autour de ' . rtrim(rtrim(number_format($min, 2, '.', ''), '0'), '.') . ' % : ajustez les bornes.';
            }
            $cursor = max($cursor, $max);
        }
        if ($cursor < 100.0) {
            $errors[] = 'Couverture incomplète : la borne supérieure doit atteindre 100 %.';
        }
        return $errors;
    }

    /**
     * Crée ou met à jour une règle après validation de l'état RÉSULTANT
     * complet (les règles inchangées sont incluses dans la vérification).
     *
     * @return array<int,string> erreurs (vide = succès)
     */
    public function saveRule(array $data, ?int $ruleId, ?int $actorId): array
    {
        $min = (float) $data['min_rate'];
        $max = (float) $data['max_rate'];
        $label = trim((string) $data['label']);
        $key = trim((string) $data['level_key']);
        if ($key === '' && $label !== '') {
            $key = $this->slugify($label);
        }
        $candidate = [
            'name' => 'Barème Rapports du Jour',
            'period_type' => 'all',
            'min_rate' => $min,
            'max_rate' => $max,
            'level_key' => mb_substr($key, 0, 30),
            'label' => mb_substr($label, 0, 80),
            'message_template' => trim((string) $data['message_template']),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_by' => $actorId,
        ];

        $current = $this->rules->allRules();
        $resulting = [];
        foreach ($current as $rule) {
            if ($ruleId !== null && (int) $rule['id'] === $ruleId) {
                $candidate['sort_order'] = $candidate['sort_order'] > 0 ? $candidate['sort_order'] : (int) $rule['sort_order'];
                $resulting[] = $candidate + ['id' => $ruleId];
            } else {
                $resulting[] = $rule;
            }
        }
        if ($ruleId === null) {
            if ($candidate['sort_order'] <= 0) {
                $maxSort = 0;
                foreach ($current as $rule) {
                    $maxSort = max($maxSort, (int) $rule['sort_order']);
                }
                $candidate['sort_order'] = $maxSort + 10;
            }
            $resulting[] = $candidate + ['id' => null];
        }

        $errors = $this->validateRuleSet($resulting);
        if ($errors) {
            return $errors;
        }

        if ($ruleId === null) {
            $this->rules->insertRule($candidate);
        } else {
            $this->rules->updateRule($ruleId, $candidate);
        }
        return [];
    }

    /** @return array<int,string> erreurs (vide = succès) */
    public function deleteRule(int $ruleId): array
    {
        $current = $this->rules->allRules();
        $resulting = array_values(array_filter($current, static fn($r) => (int) $r['id'] !== $ruleId));
        $errors = $this->validateRuleSet($resulting);
        if ($errors) {
            return array_merge(['Suppression refusée : le barème restant ne couvrirait plus [0, 100].'], $errors);
        }
        $this->rules->deleteRule($ruleId);
        return [];
    }

    /* ================= Calcul & historisation ================= */

    /**
     * Statistiques d'un responsable sur une période (occurrences qui lui
     * sont affectées, créditées à lui ou remplies par un autre).
     * expected = crédités + manquants + remplis-par-un-autre ;
     * le taux individuel = crédités / (crédités + manquants).
     */
    public function statsForUser(int $userId, string $from, string $to, ?array $scopePairs = null): array
    {
        $row = $this->occurrences->individualStatsFrom($from, $to, $userId, $scopePairs);
        $credited = (int) $row['credited'];
        $missing = (int) $row['missing'];
        $byOther = (int) $row['filled_by_other'];
        $onTime = (int) $row['on_time'];
        $late = (int) $row['late'];
        $denominator = $credited + $missing;
        return [
            'user_id' => $userId,
            'expected_count' => (int) $row['expected'],
            'submitted_count' => $credited + $byOther,
            'credited_count' => $credited,
            'filled_by_other' => $byOther,
            'on_time_count' => $onTime,
            'late_count' => $late,
            'missing_count' => $missing,
            'completion_rate' => $denominator > 0 ? round($credited / $denominator * 100, 2) : null,
            'punctuality_rate' => $denominator > 0 ? round($onTime / $denominator * 100, 2) : null,
        ];
    }

    /** Calcule et historise les résultats de la période (idempotent). */
    public function finalizePeriod(string $periodType, string $from, string $to, ?array $scopePairs = null): array
    {
        $users = $this->rules->usersWithOccurrences($from, $to, $scopePairs);
        $version = $this->rulesVersion();
        $results = [];
        foreach ($users as $row) {
            $userId = (int) $row['user_id'];
            $stats = $this->statsForUser($userId, $from, $to, $scopePairs);
            $level = $this->levelForRate($stats['completion_rate']);
            $levelKey = $level['level_key'] ?? null;
            $message = $level['message_template'] ?? 'Aucune échéance attendue sur la période : non évalué.';
            $this->rules->upsertResult([
                'user_id' => $userId,
                'period_type' => $periodType,
                'period_start' => $from,
                'period_end' => $to,
                'expected_count' => $stats['expected_count'],
                'submitted_count' => $stats['submitted_count'],
                'on_time_count' => $stats['on_time_count'],
                'late_count' => $stats['late_count'],
                'completion_rate' => $stats['completion_rate'],
                'punctuality_rate' => $stats['punctuality_rate'],
                'level_key' => $levelKey,
                'message_snapshot' => $message,
                'rules_version' => $version,
            ]);
            $results[] = ['user_id' => $userId, 'stats' => $stats, 'level' => $level, 'level_key' => $levelKey, 'message' => $message];
        }
        return $results;
    }

    public function resultFor(int $userId, string $periodType, string $from, string $to): ?array
    {
        return $this->rules->resultFor($userId, $periodType, $from, $to);
    }

    /** Nombre de responsables ayant au moins une occurrence attendue sur la période (simulation). */
    public function usersWithOccurrences(string $from, string $to, ?array $scopePairs): array
    {
        return $this->rules->usersWithOccurrences($from, $to, $scopePairs);
    }

    /** Niveau historisé du résultat PRÉCÉDENT (même type de période), pour la détection de changement. */
    public function previousLevelKey(int $userId, string $periodType, string $beforePeriodStart): ?string
    {
        $row = \App\Core\Query::one(
            'SELECT level_key FROM rapport_scoring_results
              WHERE user_id = ? AND period_type = ? AND period_start < ?
              ORDER BY period_start DESC, id DESC
              LIMIT 1',
            [$userId, $periodType, $beforePeriodStart]
        );
        return $row['level_key'] ?? null;
    }

    private function slugify(string $label): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        $ascii = $ascii !== false ? $ascii : $label;
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $ascii));
        $slug = trim($slug, '_');
        return $slug !== '' ? $slug : 'niveau';
    }
}
