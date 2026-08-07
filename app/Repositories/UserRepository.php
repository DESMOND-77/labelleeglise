<?php

namespace App\Repositories;

use App\Core\Query;
use App\Core\Database;

/**
 * Accès aux données des utilisateurs (comptes + membres).
 */
class UserRepository
{
    public function find(int $id): ?array
    {
        return Query::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public function findByEmail(string $email): ?array
    {
        return Query::one('SELECT * FROM users WHERE LOWER(email) = ?', [mb_strtolower(trim($email))]);
    }

    public function all(): array
    {
        return Query::all('SELECT * FROM users ORDER BY role, prenom, nom');
    }

    public function allIdNames(): array
    {
        return Query::all('SELECT id, prenom, nom FROM users ORDER BY prenom, nom');
    }

    public function insert(array $data): int
    {
        $cols = array_keys($data);
        return Query::run(
            'INSERT INTO users (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')',
            array_values($data)
        );
    }

    public function update(int $id, string $sets, array $params): void
    {
        Query::run("UPDATE users SET $sets WHERE id = ?", [...$params, $id]);
    }

    public function delete(int $id): void
    {
        $adminCount = (int) Query::value("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $u = $this->find($id);
        if ($u && $u['role'] === 'admin' && $adminCount <= 1) {
            return; // dernier admin protégé
        }
        Query::run('DELETE FROM users_basontas WHERE user_id = ?', [$id]);
        Query::run('UPDATE bacentas SET responsable_id = NULL WHERE responsable_id = ?', [$id]);
        Query::run('UPDATE basontas SET responsable_id = NULL WHERE responsable_id = ?', [$id]);
        Query::run('UPDATE cultes SET responsable_id = NULL WHERE responsable_id = ?', [$id]);
        Query::run('DELETE FROM users WHERE id = ?', [$id]);
    }

    public function emailTaken(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE LOWER(email) = ?';
        $params = [mb_strtolower(trim($email))];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        return (bool) Query::value($sql, $params);
    }

    public function countByRoleIn(array $roles): int
    {
        $list = implode(',', array_map(fn($r) => Database::connection()->quote($r), $roles));
        return (int) Query::value("SELECT COUNT(*) FROM users WHERE role IN ($list)");
    }

    public function countNew(): int
    {
        return (int) Query::value('SELECT COUNT(*) FROM users WHERE recu_par IS NOT NULL OR date_recu IS NOT NULL OR invite_par IS NOT NULL');
    }

    public function countAll(): int
    {
        return (int) Query::value('SELECT COUNT(*) FROM users');
    }

    public function countWithBacenta(): int
    {
        return (int) Query::value('SELECT COUNT(*) FROM users WHERE bacenta_id IS NOT NULL');
    }

    public function countWithCentre(): int
    {
        return (int) Query::value('SELECT COUNT(*) FROM users u JOIN bacentas b ON b.id = u.bacenta_id');
    }
}
