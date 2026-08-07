<?php

namespace App\Repositories;

use App\Core\Query;

/**
 * CMS : présentation de l'église, équipe, articles des centres.
 */
class CMSRepository
{
    public function presentation(): array
    {
        $row = Query::one('SELECT accroche, histoire FROM presentation ORDER BY id LIMIT 1');
        return $row ?: ['accroche' => '', 'histoire' => ''];
    }

    public function savePresentation(string $accroche, string $histoire): void
    {
        $exists = (int) Query::value('SELECT COUNT(*) FROM presentation') > 0;
        if ($exists) {
            Query::run('UPDATE presentation SET accroche = ?, histoire = ?', [$accroche, $histoire]);
        } else {
            Query::run('INSERT INTO presentation (accroche, histoire) VALUES (?, ?)', [$accroche, $histoire]);
        }
    }

    public function equipe(): array
    {
        return Query::all('SELECT * FROM equipe ORDER BY id');
    }

    public function equipeFind(int $id): ?array
    {
        return Query::one('SELECT * FROM equipe WHERE id = ?', [$id]);
    }

    public function saveEquipe(?int $id, array $data): void
    {
        if ($id) {
            Query::run(
                'UPDATE equipe SET nom_affichage = ?, role_affichage = ?, bio = ?, emoji = ?, categorie = ?, photo = COALESCE(?, photo) WHERE id = ?',
                [$data['nom'], $data['role'], $data['bio'], $data['emoji'], $data['categorie'], $data['photo'], $id]
            );
        } else {
            Query::run(
                'INSERT INTO equipe (nom_affichage, role_affichage, bio, emoji, categorie, photo) VALUES (?, ?, ?, ?, ?, ?)',
                [$data['nom'], $data['role'], $data['bio'], $data['emoji'], $data['categorie'], $data['photo']]
            );
        }
    }

    public function deleteEquipe(int $id): ?array
    {
        $m = $this->equipeFind($id);
        if ($m) {
            Query::run('DELETE FROM equipe WHERE id = ?', [$id]);
        }
        return $m;
    }

    public function centresArticles(): array
    {
        return Query::all(
            "SELECT cp.*, c.nom AS centre_nom FROM centres_presentation cp JOIN centres c ON c.id = cp.centre_id ORDER BY cp.id"
        );
    }

    public function centreArticle(int $id): ?array
    {
        return Query::one(
            "SELECT cp.*, c.nom AS centre_nom FROM centres_presentation cp JOIN centres c ON c.id = cp.centre_id WHERE cp.id = ?",
            [$id]
        );
    }

    public function saveCentreArticle(?int $id, array $fields): void
    {
        if ($id) {
            $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
            $params = array_values($fields);
            $params[] = $id;
            Query::run("UPDATE centres_presentation SET $sets WHERE id = ?", $params);
        } else {
            $cols = array_keys($fields);
            Query::run('INSERT INTO centres_presentation (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')',
                array_values($fields));
        }
    }

    public function findCentreArticleRow(int $id): ?array
    {
        return Query::one('SELECT * FROM centres_presentation WHERE id = ?', [$id]);
    }

    public function deleteCentreArticle(int $id): ?array
    {
        $c = $this->findCentreArticleRow($id);
        if ($c) {
            Query::run('DELETE FROM centres_presentation WHERE id = ?', [$id]);
        }
        return $c;
    }
}
