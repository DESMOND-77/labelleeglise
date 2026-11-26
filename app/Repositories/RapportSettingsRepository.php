<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Query;

/**
 * Paramètres globaux du module « Suivi des Rapports du Jour ».
 * Stockage clé/valeur (table `rapport_settings`). Jamais de valeur
 * sensible dans ce module ; le jeton cron reste en base, jamais affiché
 * en clair dans les journaux.
 */
class RapportSettingsRepository
{
    /** @return array<string,string> Map cle → valeur (chaînes brutes). */
    public function all(): array
    {
        $rows = Query::all('SELECT cle, valeur FROM rapport_settings');
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['cle']] = (string) ($row['valeur'] ?? '');
        }
        return $map;
    }

    public function get(string $cle, ?string $default = null): ?string
    {
        $value = Query::value('SELECT valeur FROM rapport_settings WHERE cle = ?', [$cle]);
        if ($value === false || $value === null) {
            return $default;
        }
        return (string) $value;
    }

    public function set(string $cle, ?string $valeur): void
    {
        Query::run(
            'INSERT INTO rapport_settings (cle, valeur) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), updated_at = CURRENT_TIMESTAMP',
            [$cle, $valeur]
        );
    }

    /** @param array<string,string|null> $values */
    public function setMany(array $values): void
    {
        foreach ($values as $cle => $valeur) {
            $this->set($cle, $valeur);
        }
    }
}
