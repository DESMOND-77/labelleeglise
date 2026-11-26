<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RapportSettingsRepository;

/**
 * Accès typé aux paramètres globaux du module « Suivi des Rapports du Jour »
 * (table `rapport_settings`). Les valeurs par défaut documentées sont
 * retournées si la clé est absente (jamais d'heure limite inventée : la
 * clé `heure_limite_defaut` vide ⇒ erreur de configuration visible).
 */
class RapportSettingsService
{
    private RapportSettingsRepository $repo;

    public function __construct(?RapportSettingsRepository $repo = null)
    {
        $this->repo = $repo ?? new RapportSettingsRepository();
    }

    public function all(): array
    {
        return $this->repo->all();
    }

    public function get(string $cle, ?string $default = null): ?string
    {
        return $this->repo->get($cle, $default);
    }

    public function setMany(array $values): void
    {
        $this->repo->setMany($values);
    }

    public function getBool(string $cle, bool $default): bool
    {
        $v = $this->repo->get($cle);
        if ($v === null || $v === '') {
            return $default;
        }
        return in_array(strtolower(trim($v)), ['1', 'true', 'on', 'yes'], true);
    }

    public function getInt(string $cle, int $default, int $min = 0, int $max = 100000): int
    {
        $v = $this->repo->get($cle);
        $i = ($v === null || $v === '' || !is_numeric($v)) ? $default : (int) $v;
        return max($min, min($max, $i));
    }

    /** Heure "HH:MM" validée, ou null si absente/invalide (jamais d'invention). */
    public function getTime(string $cle): ?string
    {
        $v = trim((string) ($this->repo->get($cle) ?? ''));
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : null;
    }

    public function graceMinutes(): int
    {
        return $this->getInt('grace_minutes', 720, 0, 10080);
    }

    public function maxAttempts(): int
    {
        return $this->getInt('reminder_max_attempts', 3, 1, 10);
    }

    public function retryMinutes(): int
    {
        return $this->getInt('reminder_retry_minutes', 30, 1, 720);
    }

    public function inappEnabled(): bool
    {
        return $this->getBool('channel_inapp_enabled', true);
    }

    public function emailEnabled(): bool
    {
        return $this->getBool('channel_email_enabled', true);
    }

    public function preventifEnabled(): bool
    {
        return $this->getBool('rappel_preventif_enabled', true);
    }

    public function preventifHeure(): string
    {
        return $this->getTime('rappel_preventif_heure') ?? '18:00';
    }

    public function echeanceEnabled(): bool
    {
        return $this->getBool('rappel_echeance_enabled', true);
    }

    public function relanceEnabled(): bool
    {
        return $this->getBool('rappel_relance_enabled', true);
    }

    public function relanceHeure(): string
    {
        return $this->getTime('rappel_relance_heure') ?? '09:00';
    }

    public function felicitationsEnabled(): bool
    {
        return $this->getBool('felicitations_enabled', true);
    }

    public function bilanHebdoEnabled(): bool
    {
        return $this->getBool('bilan_hebdo_enabled', true);
    }

    public function bilanHebdoHeure(): string
    {
        return $this->getTime('bilan_hebdo_heure') ?? '08:00';
    }

    public function bilanMensuelEnabled(): bool
    {
        return $this->getBool('bilan_mensuel_enabled', true);
    }

    public function bilanMensuelHeure(): string
    {
        return $this->getTime('bilan_mensuel_heure') ?? '08:00';
    }

    /** Heure limite de défaut globale (HH:MM) ou null si non configurée. */
    public function heureLimiteDefaut(): ?string
    {
        return $this->getTime('heure_limite_defaut');
    }

    /** Date de départ du cumul explicitement validée par l'admin (AAAA-MM-JJ) ou null. */
    public function cumulStartDefault(): ?string
    {
        $v = trim((string) ($this->repo->get('cumul_start_default') ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    public function cronToken(): ?string
    {
        $v = trim((string) ($this->repo->get('cron_token') ?? ''));
        return $v !== '' ? $v : null;
    }

    /**
     * Enregistre (de façon validée) les paramètres soumis par le formulaire
     * d'administration. Toute valeur invalide lève une RuntimeException avec
     * un message français - rien n'est silencieusement corrigé.
     * @param array<string,mixed> $values
     */
    public function save(array $values): void
    {
        $out = [];

        $timeKeys = ['heure_limite_defaut', 'rappel_preventif_heure', 'rappel_relance_heure', 'bilan_hebdo_heure', 'bilan_mensuel_heure'];
        foreach ($timeKeys as $key) {
            $v = trim((string) ($values[$key] ?? ''));
            if ($v === '') {
                $out[$key] = ''; // vide = non configurée (heure_limite_defaut ⇒ erreur de config visible)
                continue;
            }
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) {
                throw new \RuntimeException('Heure invalide pour « ' . $key . ' » (format HH:MM attendu).');
            }
            $out[$key] = $v;
        }

        $grace = (int) ($values['grace_minutes'] ?? 720);
        if ($grace < 0 || $grace > 10080) {
            throw new \RuntimeException('Le délai de grâce doit être compris entre 0 et 10080 minutes.');
        }
        $out['grace_minutes'] = (string) $grace;

        $attempts = (int) ($values['reminder_max_attempts'] ?? 3);
        if ($attempts < 1 || $attempts > 10) {
            throw new \RuntimeException('Le nombre maximal de tentatives doit être compris entre 1 et 10.');
        }
        $out['reminder_max_attempts'] = (string) $attempts;

        $retry = (int) ($values['reminder_retry_minutes'] ?? 30);
        if ($retry < 1 || $retry > 720) {
            throw new \RuntimeException('Le délai entre deux tentatives doit être compris entre 1 et 720 minutes.');
        }
        $out['reminder_retry_minutes'] = (string) $retry;

        $boolKeys = ['rappel_preventif_enabled', 'rappel_echeance_enabled', 'rappel_relance_enabled', 'channel_inapp_enabled', 'channel_email_enabled', 'felicitations_enabled', 'bilan_hebdo_enabled', 'bilan_mensuel_enabled'];
        foreach ($boolKeys as $key) {
            $out[$key] = (string) ($values[$key] ?? '0') === '1' ? '1' : '0';
        }

        $cumul = trim((string) ($values['cumul_start_default'] ?? ''));
        if ($cumul !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cumul) || strtotime($cumul) === false || date('Y-m-d', strtotime($cumul)) !== $cumul) {
                throw new \RuntimeException('La date de départ de cumul par défaut doit être au format AAAA-MM-JJ.');
            }
        }
        $out['cumul_start_default'] = $cumul;

        $this->repo->setMany($out);
    }

    /**
     * Enregistre le calendrier et l'heure limite d'un centre ou d'un bacenta.
     * Jours : libellés `WEEK_DAYS`, CSV, ⊆ liste autorisée ; vide = calendrier
     * inactif. Heures au format HH:MM ; `heure_limite` vide = défaut global
     * (ou erreur de configuration visible si celui-ci manque).
     * Les occurrences déjà générées ne sont jamais réécrites.
     *
     * @return ?string message d'erreur (null = succès)
     */
    public function saveCalendar(string $type, int $id, ?string $joursCsv, ?string $debut, ?string $fin, ?string $limite): ?string
    {
        if (!in_array($type, ['centre', 'bacenta'], true) || $id <= 0) {
            return 'Cible invalide.';
        }
        $days = [];
        foreach (preg_split('/[,;|]/', (string) ($joursCsv ?? '')) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (!in_array($part, WEEK_DAYS, true)) {
                return 'Jour invalide : « ' . $part . ' ».';
            }
            if (!in_array($part, $days, true)) {
                $days[] = $part;
            }
        }
        // Ordonne selon WEEK_DAYS pour un stockage stable.
        usort($days, static fn($a, $b) => array_search($a, WEEK_DAYS, true) <=> array_search($b, WEEK_DAYS, true));

        $timeKeys = ['heure_debut' => $debut, 'heure_fin' => $fin, 'heure_limite' => $limite];
        foreach ($timeKeys as $label => $value) {
            $v = trim((string) ($value ?? ''));
            if ($v !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) {
                return 'Heure invalide pour « ' . $label . ' » (format HH:MM attendu).';
            }
        }
        $debut = trim((string) ($debut ?? ''));
        $fin = trim((string) ($fin ?? ''));
        if ($debut !== '' && $fin !== '' && $fin <= $debut) {
            return 'L\'heure de fin doit être postérieure à l\'heure de début.';
        }

        $occRepo = new \App\Repositories\RapportOccurrenceRepository();
        $jours = $days ? implode(',', $days) : null;
        $debut = $debut !== '' ? $debut : null;
        $fin = $fin !== '' ? $fin : null;
        $limite = trim((string) ($limite ?? ''));
        $limite = $limite !== '' ? $limite : null;

        if ($type === 'centre') {
            $found = \App\Core\Query::value('SELECT 1 FROM centres WHERE id = ?', [$id]);
            if ($found === false) {
                return 'Centre introuvable.';
            }
            $occRepo->updateCentreCalendar($id, $jours, $debut, $fin, $limite);
        } else {
            $found = \App\Core\Query::value('SELECT 1 FROM bacentas WHERE id = ?', [$id]);
            if ($found === false) {
                return 'Bacenta introuvable.';
            }
            $occRepo->updateBacentaCalendar($id, $jours, $debut, $fin, $limite);
        }
        return null;
    }

    /**
     * Problèmes de configuration à signaler à l'administration (jamais
     * d'invention) : appels coûteux, réservés aux écrans admin.
     * @return array<int,string>
     */
    public function configurationProblems(array $centres, array $bacentas): array
    {
        $problems = [];
        $global = $this->heureLimiteDefaut();
        if ($global === null) {
            $missing = [];
            foreach ($centres as $c) {
                if (trim((string) ($c['jours_semaine'] ?? '')) !== '' && trim((string) ($c['heure_limite'] ?? '')) === '') {
                    $missing[] = 'Centre « ' . $c['nom'] . ' »';
                }
            }
            foreach ($bacentas as $b) {
                if (trim((string) ($b['jours_semaine'] ?? '')) !== '' && trim((string) ($b['heure_limite'] ?? '')) === '') {
                    $missing[] = 'Bacenta « ' . $b['nom'] . ' »';
                }
            }
            if ($missing) {
                $problems[] = 'Heure limite manquante (aucune valeur globale ni par calendrier) : ' . implode(', ', $missing) . '. Les occurrences concernées seront générées sans heure limite et signalées « À configurer ».';
            }
        }
        $sansCalendrierCentres = array_filter($centres, static fn($c) => trim((string) ($c['jours_semaine'] ?? '')) === '');
        $sansCalendrierBacentas = array_filter($bacentas, static fn($b) => trim((string) ($b['jours_semaine'] ?? '')) === '');
        if ($sansCalendrierCentres) {
            $problems[] = count($sansCalendrierCentres) . ' centre(s) sans calendrier de réunion : aucun rapport ne sera attendu pour eux (' . implode(', ', array_map(static fn($c) => (string) $c['nom'], $sansCalendrierCentres)) . ').';
        }
        if ($sansCalendrierBacentas) {
            $problems[] = count($sansCalendrierBacentas) . ' bacenta(s) sans calendrier de réunion : aucun rapport ne sera attendu pour eux.';
        }
        return $problems;
    }
}
