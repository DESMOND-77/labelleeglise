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

    /* ================= Inscription publique / vérification / activation ================= */

    /**
     * Crée un compte issu de l'inscription publique.
     * Le rôle, le statut de vérification et le statut de compte sont
     * TOUJOURS imposés ici (jamais lus depuis la requête HTTP).
     */
    public function createRegistration(array $fields, string $hashedPassword, string $hashedToken, string $expiresAt): int
    {
        return $this->insert([
            'nom'                      => $fields['nom'],
            'prenom'                   => $fields['prenom'],
            'email'                    => $fields['email'],
            'telephone'                => $fields['telephone'],
            'password'                 => $hashedPassword,
            'role'                     => 'membre',
            'email_verified'           => 0,
            'account_status'           => 'pending',
            'compte_actif'             => 0,
            'verification_token'       => $hashedToken,
            'verification_expires_at'  => $expiresAt,
        ]);
    }

    /** Retrouve un compte par le hash du jeton de vérification (jeton non expiré ou non). */
    public function findByVerificationTokenHash(string $hashedToken): ?array
    {
        return Query::one('SELECT * FROM users WHERE verification_token = ?', [$hashedToken]);
    }

    /** Régénère un nouveau jeton de vérification (renvoi d'email). */
    public function setVerificationToken(int $id, string $hashedToken, string $expiresAt): void
    {
        Query::run('UPDATE users SET verification_token = ?, verification_expires_at = ? WHERE id = ?', [$hashedToken, $expiresAt, $id]);
    }

    /** Marque l'email comme vérifié et invalide le jeton (usage unique). */
    public function markEmailVerified(int $id): void
    {
        Query::run(
            "UPDATE users SET email_verified = 1, email_verified_at = NOW(), verification_token = NULL, verification_expires_at = NULL WHERE id = ?",
            [$id]
        );
    }

    /** Active le compte (validation administrative) — synchronise compte_actif. */
    public function activateAccount(int $id): void
    {
        Query::run("UPDATE users SET account_status = 'active', compte_actif = 1 WHERE id = ?", [$id]);
    }

    /** Désactive un compte — synchronise compte_actif. */
    public function disableAccount(int $id): void
    {
        Query::run("UPDATE users SET account_status = 'disabled', compte_actif = 0 WHERE id = ?", [$id]);
    }

    /** Tous les comptes disposant du rôle administrateur. */
    public function findAdmins(): array
    {
        return Query::all("SELECT * FROM users WHERE role = 'admin'");
    }

    /** Inscriptions en attente de validation administrative (tableau de bord admin). */
    public function findPendingRegistrations(): array
    {
        return Query::all(
            "SELECT * FROM users WHERE account_status = 'pending' AND role = 'membre' ORDER BY created_at DESC, id DESC"
        );
    }

    /**
     * Membres actifs, vérifiés, de rôle "membre" et non affectés à un bacenta.
     * Utilisé par l'écran d'affectation des responsables de bacenta.
     */
    public function findUnassignedActiveMembers(string $search = ''): array
    {
        $sql = "SELECT * FROM users
                 WHERE role = 'membre' AND account_status = 'active' AND email_verified = 1
                   AND bacenta_id IS NULL";
        $params = [];
        $search = trim($search);
        if ($search !== '') {
            $sql .= " AND (LOWER(CONCAT(prenom, ' ', nom)) LIKE ?
                        OR LOWER(CONCAT(nom, ' ', prenom)) LIKE ?
                        OR LOWER(email) LIKE ?
                        OR telephone LIKE ?)";
            $like = '%' . mb_strtolower($search) . '%';
            $params = [$like, $like, $like, '%' . $search . '%'];
        }
        $sql .= ' ORDER BY prenom, nom';
        return Query::all($sql, $params);
    }

    /**
     * Revalide un candidat unique côté serveur, juste avant affectation à un
     * bacenta (jamais de confiance dans les IDs envoyés par le client).
     * Retourne le user si toutes les conditions sont réunies, sinon null.
     */
    public function findEligibleUnassignedMember(int $id): ?array
    {
        return Query::one(
            "SELECT * FROM users
              WHERE id = ? AND role = 'membre' AND account_status = 'active'
                AND email_verified = 1 AND bacenta_id IS NULL",
            [$id]
        );
    }

    /* ================= Profil libre-service / dernière connexion ================= */

    /** Champs personnels modifiables depuis "Mon profil" (jamais email/role/password ici). */
    public function updateProfileFields(int $id, array $fields): void
    {
        Query::run(
            'UPDATE users SET nom = ?, prenom = ?, date_naissance = ?, quartier = ?, telephone = ? WHERE id = ?',
            [$fields['nom'], $fields['prenom'], $fields['date_naissance'] ?: null, $fields['quartier'], $fields['telephone'], $id]
        );
    }

    public function updatePhoto(int $id, string $photoPath): void
    {
        Query::run('UPDATE users SET photo_de_profil = ? WHERE id = ?', [$photoPath, $id]);
    }

    public function updatePassword(int $id, string $hashedPassword): void
    {
        Query::run('UPDATE users SET password = ? WHERE id = ?', [$hashedPassword, $id]);
    }

    public function setLastLogin(int $id): void
    {
        Query::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    /* ================= Changement d'email sécurisé ================= */

    /** Enregistre une demande de changement d'email (jeton haché, expiration). */
    public function setPendingEmailChange(int $id, string $pendingEmail, string $hashedToken, string $expiresAt): void
    {
        Query::run(
            'UPDATE users SET pending_email = ?, email_change_token = ?, email_change_expires_at = ? WHERE id = ?',
            [$pendingEmail, $hashedToken, $expiresAt, $id]
        );
    }

    public function findByEmailChangeTokenHash(string $hashedToken): ?array
    {
        return Query::one('SELECT * FROM users WHERE email_change_token = ?', [$hashedToken]);
    }

    /** Confirme le changement : remplace l'email, marque vérifié, purge les champs pending (usage unique). */
    public function confirmEmailChange(int $id, string $newEmail): void
    {
        Query::run(
            "UPDATE users SET email = ?, email_verified = 1, email_verified_at = NOW(),
                pending_email = NULL, email_change_token = NULL, email_change_expires_at = NULL
              WHERE id = ?",
            [$newEmail, $id]
        );
    }

    /** Annule une demande en cours (sans toucher à l'email actuel). */
    public function cancelPendingEmailChange(int $id): void
    {
        Query::run(
            'UPDATE users SET pending_email = NULL, email_change_token = NULL, email_change_expires_at = NULL WHERE id = ?',
            [$id]
        );
    }
}
