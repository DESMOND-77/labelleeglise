<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Query;

/**
 * Saisies manuelles du calendrier d'anniversaires (personnes sans compte).
 * Les anniversaires des membres sont dérivés de users.date_naissance.
 */
class AnniversaireRepository
{
    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return Query::all('SELECT * FROM anniversaires ORDER BY mois, jour, nom');
    }

    public function find(int $id): ?array
    {
        return Query::one('SELECT * FROM anniversaires WHERE id = ?', [$id]);
    }

    public function create(string $nom, int $jour, int $mois, ?int $annee, ?int $createdBy): int
    {
        return Query::run(
            'INSERT INTO anniversaires (nom, jour, mois, annee, created_by) VALUES (?, ?, ?, ?, ?)',
            [$nom, $jour, $mois, $annee, $createdBy]
        );
    }

    public function delete(int $id): void
    {
        Query::run('DELETE FROM anniversaires WHERE id = ?', [$id]);
    }
}
