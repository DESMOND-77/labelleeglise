<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Query;

/**
 * Événements du calendrier événementiel.
 */
class EvenementRepository
{
    private const SELECT = 'SELECT e.*, ru.prenom AS resp_prenom, ru.nom AS resp_nom
                              FROM evenements e
                              LEFT JOIN users ru ON ru.id = e.responsable_id';

    /** @return array<int,array<string,mixed>> */
    public function all(?string $fromDate = null): array
    {
        if ($fromDate !== null) {
            return Query::all(self::SELECT . ' WHERE e.date_debut >= ? ORDER BY e.date_debut ASC', [$fromDate]);
        }
        return Query::all(self::SELECT . ' ORDER BY e.date_debut ASC');
    }

    public function find(int $id): ?array
    {
        return Query::one(self::SELECT . ' WHERE e.id = ?', [$id]);
    }

    public function create(string $nom, string $dateDebut, ?string $dateFin, ?string $lieu, ?int $responsableId, ?int $createdBy): int
    {
        return Query::run(
            'INSERT INTO evenements (nom, date_debut, date_fin, lieu, responsable_id, created_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$nom, $dateDebut, $dateFin, $lieu, $responsableId, $createdBy]
        );
    }

    public function update(int $id, string $nom, string $dateDebut, ?string $dateFin, ?string $lieu, ?int $responsableId): void
    {
        Query::run(
            'UPDATE evenements SET nom = ?, date_debut = ?, date_fin = ?, lieu = ?, responsable_id = ? WHERE id = ?',
            [$nom, $dateDebut, $dateFin, $lieu, $responsableId, $id]
        );
    }

    public function delete(int $id): void
    {
        Query::run('DELETE FROM evenements WHERE id = ?', [$id]);
    }
}
