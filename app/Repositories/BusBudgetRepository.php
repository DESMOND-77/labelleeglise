<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Query;

/**
 * Budget Bus du dimanche : mouvements par centre (M2).
 * Une ligne = un retrait / une collecte pour le bus du dimanche.
 */
class BusBudgetRepository
{
    /** Colonnes métier écrites par create() (dans l'ordre). */
    private const WRITABLE = ['centre_id', 'date_retrait', 'montant', 'observations', 'created_by'];

    public function find(int $id): ?array
    {
        return Query::one('SELECT * FROM bus_budget WHERE id = ?', [$id]);
    }

    /**
     * Lignes d'une année, ordonnées par centre puis date.
     *
     * @param ?int         $centreId  filtre d'affichage (un seul centre) ou null
     * @param int          $year      année civile (YEAR(date_retrait))
     * @param ?array<int>  $centreIds périmètre autorisé — null = pas de restriction ; [] = aucun résultat
     * @return array<int,array<string,mixed>>
     */
    public function list(?int $centreId, int $year, ?array $centreIds = null): array
    {
        $sql = "SELECT b.*, c.nom AS centre_nom,
                       u.prenom AS auteur_prenom, u.nom AS auteur_nom
                  FROM bus_budget b
                  JOIN centres c ON c.id = b.centre_id
             LEFT JOIN users u   ON u.id = b.created_by
                 WHERE YEAR(b.date_retrait) = ?";
        $params = [$year];

        if ($centreId !== null) {
            $sql .= ' AND b.centre_id = ?';
            $params[] = $centreId;
        }
        if ($centreIds !== null) {
            if ($centreIds === []) {
                $sql .= ' AND 1 = 0';
            } else {
                $sql .= ' AND b.centre_id IN (' . implode(', ', array_fill(0, count($centreIds), '?')) . ')';
                foreach ($centreIds as $cid) {
                    $params[] = (int) $cid;
                }
            }
        }
        $sql .= ' ORDER BY c.nom, b.date_retrait, b.id';

        return Query::all($sql, $params);
    }

    public function create(array $data): int
    {
        $cols = self::WRITABLE;
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $params = array_map(static fn($c) => $data[$c] ?? null, $cols);
        return Query::run('INSERT INTO bus_budget (' . implode(', ', $cols) . ") VALUES ($placeholders)", $params);
    }

    /** Met à jour une ligne (created_by / created_at préservés). */
    public function update(int $id, array $data): void
    {
        $cols = ['centre_id', 'date_retrait', 'montant', 'observations'];
        $set = implode(', ', array_map(static fn($c) => "$c = ?", $cols));
        $params = array_map(static fn($c) => $data[$c] ?? null, $cols);
        $params[] = $id;
        Query::run("UPDATE bus_budget SET $set WHERE id = ?", $params);
    }

    public function delete(int $id): void
    {
        Query::run('DELETE FROM bus_budget WHERE id = ?', [$id]);
    }
}
