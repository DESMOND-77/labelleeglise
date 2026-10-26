<?php

namespace App\Repositories;

use App\Core\Database;
use App\Core\Query;

/**
 * Suivi des nouveaux membres : statut, responsable assigné, intégration.
 * Table annexe 1:1 (user_id UNIQUE) sur `users` - voir Database/Migrations
 * bloc 17 pour la justification du modèle (pas de table membres séparée).
 */
class NouveauMembreRepository
{
    public function create(int $userId, array $data): int
    {
        return Query::run(
            'INSERT INTO nouveaux_membres (user_id, statut, responsable_id, date_premiere_visite, observations)
             VALUES (?, ?, ?, ?, ?)',
            [
                $userId,
                $data['statut'] ?? 'nouveau',
                $data['responsable_id'] ?? null,
                $data['date_premiere_visite'] ?? null,
                $data['observations'] ?? null,
            ]
        );
    }

    public function find(int $id): ?array
    {
        return Query::one(
            "SELECT nm.*, u.nom, u.prenom, u.sexe, u.date_naissance, u.telephone, u.quartier, u.bacenta_id,
                    u.invite_par, u.recu_par,
                    r.nom AS resp_nom, r.prenom AS resp_prenom, r.role AS resp_role,
                    b.nom AS bacenta_nom,
                    inv.nom AS invite_par_nom, inv.prenom AS invite_par_prenom,
                    rec.nom AS recu_par_nom, rec.prenom AS recu_par_prenom
               FROM nouveaux_membres nm
               JOIN users u ON u.id = nm.user_id
               LEFT JOIN users r ON r.id = nm.responsable_id
               LEFT JOIN bacentas b ON b.id = u.bacenta_id
               LEFT JOIN users inv ON inv.id = u.invite_par
               LEFT JOIN users rec ON rec.id = u.recu_par
              WHERE nm.id = ?",
            [$id]
        );
    }

    public function findByUserId(int $userId): ?array
    {
        return Query::one('SELECT * FROM nouveaux_membres WHERE user_id = ?', [$userId]);
    }

    /**
     * @param array{q?:string,responsable_id?:int,bacenta_id?:int,statut?:string,unassigned?:bool,exclude_integrated?:bool} $filters
     */
    public function list(array $filters = []): array
    {
        $sql = "SELECT nm.*, u.nom, u.prenom, u.telephone, u.quartier, u.bacenta_id,
                       r.nom AS resp_nom, r.prenom AS resp_prenom, r.role AS resp_role,
                       b.nom AS bacenta_nom
                  FROM nouveaux_membres nm
                  JOIN users u ON u.id = nm.user_id
                  LEFT JOIN users r ON r.id = nm.responsable_id
                  LEFT JOIN bacentas b ON b.id = u.bacenta_id
                 WHERE 1 = 1";
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= " AND (LOWER(CONCAT(u.prenom, ' ', u.nom)) LIKE ? OR LOWER(CONCAT(u.nom, ' ', u.prenom)) LIKE ? OR u.telephone LIKE ?)";
            $like = '%' . mb_strtolower($q) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = '%' . $q . '%';
        }
        if (!empty($filters['responsable_id'])) {
            $sql .= ' AND nm.responsable_id = ?';
            $params[] = (int) $filters['responsable_id'];
        }
        if (!empty($filters['bacenta_id'])) {
            $sql .= ' AND u.bacenta_id = ?';
            $params[] = (int) $filters['bacenta_id'];
        }
        if (!empty($filters['statut'])) {
            $sql .= ' AND nm.statut = ?';
            $params[] = (string) $filters['statut'];
        }
        if (!empty($filters['unassigned'])) {
            $sql .= ' AND nm.responsable_id IS NULL';
        }
        if (!empty($filters['exclude_integrated'])) {
            $sql .= " AND nm.statut <> 'integre'";
        }
        $sql .= ' ORDER BY nm.created_at DESC, nm.id DESC';
        return Query::all($sql, $params);
    }

    public function updateResponsable(int $id, ?int $responsableId): void
    {
        Query::run('UPDATE nouveaux_membres SET responsable_id = ? WHERE id = ?', [$responsableId, $id]);
    }

    public function updateStatut(int $id, string $statut): void
    {
        Query::run('UPDATE nouveaux_membres SET statut = ? WHERE id = ?', [$statut, $id]);
    }

    public function integrate(int $id, int $actorId): void
    {
        Query::run(
            "UPDATE nouveaux_membres SET statut = 'integre', date_integration = NOW(), integre_par = ? WHERE id = ?",
            [$actorId, $id]
        );
    }

    public function logAttribution(int $nouveauMembreId, ?int $responsableId, int $attribuePar): void
    {
        Query::run(
            'INSERT INTO nouveaux_membres_attributions (nouveau_membre_id, responsable_id, attribue_par) VALUES (?, ?, ?)',
            [$nouveauMembreId, $responsableId, $attribuePar]
        );
    }

    public function attributionHistory(int $nouveauMembreId): array
    {
        return Query::all(
            "SELECT nma.*, r.nom AS resp_nom, r.prenom AS resp_prenom,
                    a.nom AS par_nom, a.prenom AS par_prenom
               FROM nouveaux_membres_attributions nma
               LEFT JOIN users r ON r.id = nma.responsable_id
               JOIN users a ON a.id = nma.attribue_par
              WHERE nma.nouveau_membre_id = ?
              ORDER BY nma.created_at DESC, nma.id DESC",
            [$nouveauMembreId]
        );
    }

    /** Utilisateurs pouvant être désignés responsable de suivi (berger/MS/pasteur/révérend). */
    public function candidateResponsables(): array
    {
        $roles = implode(',', array_map(fn($r) => Database::connection()->quote($r), BERGER_ROLES));
        return Query::all(
            "SELECT u.id, u.nom, u.prenom, u.role, u.bacenta_id, b.nom AS bacenta_nom
               FROM users u LEFT JOIN bacentas b ON b.id = u.bacenta_id
              WHERE u.role IN ($roles)
              ORDER BY u.prenom, u.nom"
        );
    }
}
