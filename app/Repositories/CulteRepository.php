<?php

namespace App\Repositories;

use App\Core\Query;

/**
 * Accès aux données des cultes.
 */
class CulteRepository
{
    public function all(): array
    {
        return Query::all(
            "SELECT c.*, u.prenom AS resp_prenom, u.nom AS resp_nom,
                    (SELECT COUNT(*) FROM presences p WHERE p.culte_id = c.id) AS nb_presents
               FROM cultes c LEFT JOIN users u ON u.id = c.responsable_id
              ORDER BY c.date_culte DESC, c.id"
        );
    }

    public function find(int $id): ?array
    {
        return Query::one(
            "SELECT c.*, u.prenom AS resp_prenom, u.nom AS resp_nom,
                    (SELECT COUNT(*) FROM presences p WHERE p.culte_id = c.id) AS nb_presents
               FROM cultes c LEFT JOIN users u ON u.id = c.responsable_id
              WHERE c.id = ?",
            [$id]
        );
    }

    /** $resp accepté pour compatibilité mais IGNORÉ — voir BacentaRepository::create(). */
    public function create(string $nom, ?string $date, ?string $debut, ?string $fin, ?int $resp = null, ?string $jours = null): int
    {
        return Query::run('INSERT INTO cultes (nom, date_culte, jours_semaine, heure_debut, heure_fin) VALUES (?, ?, ?, ?, ?)',
            [$nom, $date, $jours, $debut, $fin]);
    }

    public function update(int $id, string $nom, ?string $date, ?string $debut, ?string $fin, ?int $resp = null, ?string $jours = null): void
    {
        Query::run('UPDATE cultes SET nom = ?, date_culte = ?, jours_semaine = ?, heure_debut = ?, heure_fin = ? WHERE id = ?',
            [$nom, $date, $jours, $debut, $fin, $id]);
    }

    public function delete(int $id): void
    {
        Query::run("DELETE FROM responsibilities WHERE target_type = 'cult' AND target_id = ?", [$id]);
        Query::run('DELETE FROM presences WHERE culte_id = ?', [$id]);
        Query::run('DELETE FROM cultes WHERE id = ?', [$id]);
    }

    /** Culte le plus récent (sinon le premier). */
    public function latest(): ?array
    {
        $c = Query::one('SELECT id FROM cultes ORDER BY date_culte IS NULL, date_culte DESC, id LIMIT 1');
        return $c ? $this->find((int) $c['id']) : null;
    }
}
