<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Query;

/**
 * Classes / écoles post-culte et inscriptions.
 */
class ClasseRepository
{
    private const SELECT = "SELECT c.*, f.prenom AS formateur_prenom, f.nom AS formateur_nom,
                                   (SELECT COUNT(*) FROM classe_inscrits ci WHERE ci.classe_id = c.id) AS nb_inscrits
                              FROM classes c
                              LEFT JOIN users f ON f.id = c.formateur_id";

    public function all(): array
    {
        return Query::all(self::SELECT . ' ORDER BY c.ordre, c.id');
    }

    public function find(int $id): ?array
    {
        return Query::one(self::SELECT . ' WHERE c.id = ?', [$id]);
    }

    public function create(string $nom, ?int $formateurId, int $ordre, int $nbModules, ?string $prochaineSession, int $actif): int
    {
        return Query::run(
            'INSERT INTO classes (nom, formateur_id, ordre, nb_modules, prochaine_session, actif) VALUES (?, ?, ?, ?, ?, ?)',
            [$nom, $formateurId, $ordre, $nbModules, $prochaineSession, $actif]
        );
    }

    public function update(int $id, string $nom, ?int $formateurId, int $ordre, int $nbModules, ?string $prochaineSession, int $actif): void
    {
        Query::run(
            'UPDATE classes SET nom = ?, formateur_id = ?, ordre = ?, nb_modules = ?, prochaine_session = ?, actif = ? WHERE id = ?',
            [$nom, $formateurId, $ordre, $nbModules, $prochaineSession, $actif, $id]
        );
    }

    public function delete(int $id): void
    {
        Query::run('DELETE FROM classes WHERE id = ?', [$id]);
    }

    public function nextActiveClassId(int $ordre): ?int
    {
        $id = Query::value('SELECT id FROM classes WHERE actif = 1 AND ordre > ? ORDER BY ordre ASC, id ASC LIMIT 1', [$ordre]);
        return $id ? (int) $id : null;
    }

    /* ---------------- Inscrits ---------------- */

    public function inscritsOf(int $classeId): array
    {
        return Query::all(
            'SELECT ci.*, u.prenom, u.nom, u.email
               FROM classe_inscrits ci JOIN users u ON u.id = ci.user_id
              WHERE ci.classe_id = ? ORDER BY u.prenom, u.nom',
            [$classeId]
        );
    }

    public function findInscrit(int $id): ?array
    {
        return Query::one('SELECT * FROM classe_inscrits WHERE id = ?', [$id]);
    }

    public function findInscritByClasseUser(int $classeId, int $userId): ?array
    {
        return Query::one('SELECT * FROM classe_inscrits WHERE classe_id = ? AND user_id = ?', [$classeId, $userId]);
    }

    /** INSERT IGNORE ; renvoie l'id (existant ou nouveau). */
    public function insertInscrit(int $classeId, int $userId): int
    {
        Query::run('INSERT IGNORE INTO classe_inscrits (classe_id, user_id) VALUES (?, ?)', [$classeId, $userId]);
        $row = $this->findInscritByClasseUser($classeId, $userId);
        return $row ? (int) $row['id'] : 0;
    }

    public function updateInscrit(int $id, int $modulesValides, string $examOral, string $examEcrit, ?float $examNote, ?string $examDate): void
    {
        Query::run(
            'UPDATE classe_inscrits SET modules_valides = ?, exam_oral = ?, exam_ecrit = ?, exam_note = ?, exam_date = ? WHERE id = ?',
            [$modulesValides, $examOral, $examEcrit, $examNote, $examDate, $id]
        );
    }

    public function setInscritStatut(int $id, string $statut): void
    {
        Query::run('UPDATE classe_inscrits SET statut = ? WHERE id = ?', [$statut, $id]);
    }

    public function deleteInscrit(int $id): void
    {
        Query::run('DELETE FROM classe_inscrits WHERE id = ?', [$id]);
    }

    /* ---------------- Listes de sélection ---------------- */

    public function candidates(int $classeId): array
    {
        return Query::all(
            "SELECT id, prenom, nom FROM users
              WHERE role IN ('membre','leader','assistant','pasteur','reverant')
                AND id NOT IN (SELECT user_id FROM classe_inscrits WHERE classe_id = ?)
              ORDER BY prenom, nom",
            [$classeId]
        );
    }

    public function formateurCandidates(): array
    {
        return Query::all(
            "SELECT id, prenom, nom FROM users
              WHERE role IN ('berger','ms','pasteur','reverant','leader','admin')
              ORDER BY prenom, nom"
        );
    }
}
