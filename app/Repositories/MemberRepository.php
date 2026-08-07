<?php

namespace App\Repositories;

use App\Core\Query;
use App\Core\Database;

/**
 * Liste des membres par périmètre (bacenta, centre, basonta, culte, listes).
 */
class MemberRepository
{
    public function ofBacenta(int $bacentaId): array
    {
        return Query::all('SELECT * FROM users WHERE bacenta_id = ? ORDER BY prenom, nom', [$bacentaId]);
    }

    public function ofCentre(int $centreId): array
    {
        return Query::all(
            "SELECT u.* FROM users u JOIN bacentas b ON b.id = u.bacenta_id
              WHERE b.centre_id = ? ORDER BY u.prenom, u.nom",
            [$centreId]
        );
    }

    public function ofBasonta(int $basontaId): array
    {
        return Query::all(
            "SELECT u.* FROM users u JOIN users_basontas ub ON ub.user_id = u.id
              WHERE ub.basonta_id = ? ORDER BY u.prenom, u.nom",
            [$basontaId]
        );
    }

    public function ofCulte(int $culteId): array
    {
        return Query::all(
            "SELECT u.*, p.date_presence FROM users u JOIN presences p ON p.user_id = u.id
              WHERE p.culte_id = ? ORDER BY u.prenom, u.nom",
            [$culteId]
        );
    }

    public function nouveaux(?string $filter = null): array
    {
        $sql = "SELECT * FROM users WHERE (recu_par IS NOT NULL OR date_recu IS NOT NULL OR invite_par IS NOT NULL)";
        $params = [];
        if ($filter !== null && $filter !== '') {
            $sql .= " AND (LOWER(CONCAT(prenom, ' ', nom)) LIKE ? OR LOWER(CONCAT(nom, ' ', prenom)) LIKE ? OR LOWER(quartier) LIKE ?)";
            $f = '%' . mb_strtolower($filter) . '%';
            $params = [$f, $f, $f];
        }
        $sql .= " ORDER BY date_recu DESC, prenom, nom";
        return Query::all($sql, $params);
    }

    public function generale(?string $filter = null): array
    {
        $sql = 'SELECT * FROM users WHERE 1 = 1';
        $params = [];
        if ($filter !== null && $filter !== '') {
            $sql .= " AND (LOWER(CONCAT(prenom, ' ', nom)) LIKE ? OR LOWER(CONCAT(nom, ' ', prenom)) LIKE ? OR LOWER(quartier) LIKE ?)";
            $f = '%' . mb_strtolower($filter) . '%';
            $params = [$f, $f, $f];
        }
        $sql .= ' ORDER BY prenom, nom';
        return Query::all($sql, $params);
    }

    public function bergers(?string $filter = null): array
    {
        $roles = implode(',', array_map(fn($r) => Database::connection()->quote($r), BERGER_ROLES));
        $sql = "SELECT * FROM users WHERE role IN ($roles)";
        $params = [];
        if ($filter !== null && $filter !== '') {
            $sql .= " AND (LOWER(CONCAT(prenom, ' ', nom)) LIKE ? OR LOWER(CONCAT(nom, ' ', prenom)) LIKE ?)";
            $f = '%' . mb_strtolower($filter) . '%';
            $params = [$f, $f];
        }
        $sql .= ' ORDER BY prenom, nom';
        return Query::all($sql, $params);
    }

    public function candidatesForBasonta(int $basontaId): array
    {
        return Query::all(
            "SELECT id, prenom, nom FROM users
              WHERE id NOT IN (SELECT user_id FROM users_basontas WHERE basonta_id = ?)
                AND role IN ('membre','leader','assistant','pasteur','reverant')
              ORDER BY prenom, nom",
            [$basontaId]
        );
    }

    public function search(string $q): array
    {
        $out = [];
        $q = mb_strtolower(trim($q));
        if ($q === '') {
            return $out;
        }
        $rows = Query::all(
            "SELECT * FROM users WHERE LOWER(CONCAT(prenom, ' ', nom)) LIKE ? OR LOWER(CONCAT(nom, ' ', prenom)) LIKE ?
             ORDER BY prenom, nom LIMIT 20",
            ['%' . $q . '%', '%' . $q . '%']
        );
        foreach ($rows as $u) {
            $out[] = ['user' => $u];
        }
        return $out;
    }

    /** Noms des utilisateurs pour invite_par / recu_par. */
    public function namesForIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $rows = Query::all('SELECT id, prenom, nom FROM users WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')');
        $map = [];
        foreach ($rows as $u) {
            $map[(int) $u['id']] = trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''));
        }
        return $map;
    }
}
