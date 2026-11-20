<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Query;

/**
 * Données des albums / galerie multimédia.
 * Lecture publique : albums publiés + photos publiées uniquement.
 * Administration : tous les albums (publiés, archivés, brouillons).
 */
class AlbumRepository
{
    /** @return list<array<string,mixed>> Albums publiés (site public), triés par année desc puis titre. */
    public function published(): array
    {
        return Query::all(
            'SELECT id, titre, description, couverture, categorie, annee, publie, archive, evenement_id, created_at
               FROM albums
              WHERE publie = 1 AND archive = 0
              ORDER BY annee IS NULL, annee DESC, titre ASC'
        );
    }

    /** Album publié par son id (site public). */
    public function findPublished(int $id): ?array
    {
        return Query::one(
            'SELECT id, titre, description, couverture, categorie, annee, publie, archive, evenement_id, created_at
               FROM albums
              WHERE id = ? AND publie = 1 AND archive = 0',
            [$id]
        );
    }

    /** Photos publiques d'un album, triées par position puis id. */
    public function publishedPhotos(int $albumId): array
    {
        return Query::all(
            'SELECT id, album_id, fichier, legende, position, publie, created_at
               FROM album_photos
              WHERE album_id = ? AND publie = 1
              ORDER BY position ASC, id ASC',
            [$albumId]
        );
    }

    /** @return list<array<string,mixed>> Tous les albums (admin), triés par created_at desc. */
    public function allForAdmin(): array
    {
        return Query::all(
            'SELECT a.id, a.titre, a.description, a.couverture, a.categorie, a.annee,
                    a.publie, a.archive, a.evenement_id, a.created_at, a.updated_at,
                    e.nom AS evenement_nom,
                    (SELECT COUNT(*) FROM album_photos p WHERE p.album_id = a.id AND p.publie = 1) AS nb_photos
               FROM albums a
          LEFT JOIN evenements e ON e.id = a.evenement_id
              ORDER BY a.created_at DESC'
        );
    }

    /** Album par id (admin). */
    public function findForAdmin(int $id): ?array
    {
        return Query::one(
            'SELECT a.id, a.titre, a.description, a.couverture, a.categorie, a.annee,
                    a.publie, a.archive, a.evenement_id, a.created_at, a.updated_at,
                    e.nom AS evenement_nom
               FROM albums a
          LEFT JOIN evenements e ON e.id = a.evenement_id
              WHERE a.id = ?',
            [$id]
        );
    }

    /** Toutes les photos d'un album (admin), triées par position puis id. */
    public function photosForAdmin(int $albumId): array
    {
        return Query::all(
            'SELECT id, album_id, fichier, legende, position, publie, created_at
               FROM album_photos
              WHERE album_id = ?
              ORDER BY position ASC, id ASC',
            [$albumId]
        );
    }

    /** Nombre de photos publiées d'un album. */
    public function countPublishedPhotos(int $albumId): int
    {
        return (int) Query::value(
            'SELECT COUNT(*) FROM album_photos WHERE album_id = ? AND publie = 1',
            [$albumId]
        );
    }

    /** Créer un album. Retourne l'id inséré. */
    public function create(string $titre, ?string $description, ?string $couverture,
                           ?string $categorie, ?int $annee, int $publie, int $archive,
                           ?int $evenementId): int
    {
        return Query::run(
            'INSERT INTO albums (titre, description, couverture, categorie, annee, publie, archive, evenement_id)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$titre, $description, $couverture, $categorie, $annee,
             $publie ? 1 : 0, $archive ? 1 : 0, $evenementId]
        );
    }

    /** Mettre à jour un album. */
    public function update(int $id, string $titre, ?string $description, ?string $couverture,
                           ?string $categorie, ?int $annee, int $publie, int $archive,
                           ?int $evenementId): void
    {
        Query::run(
            'UPDATE albums SET titre = ?, description = ?, couverture = ?, categorie = ?,
                                   annee = ?, publie = ?, archive = ?, evenement_id = ?
                                 WHERE id = ?',
            [$titre, $description, $couverture, $categorie, $annee,
             $publie ? 1 : 0, $archive ? 1 : 0, $evenementId, $id]
        );
    }

    /** Supprimer un album et toutes ses photos (cascade SQL + fichiers physiques côté service). */
    public function delete(int $id): void
    {
        Query::run('DELETE FROM albums WHERE id = ?', [$id]);
    }

    /** Ajouter une photo à un album. Retourne l'id inséré. */
    public function addPhoto(int $albumId, string $fichier, ?string $legende, int $position, int $publie): int
    {
        return Query::run(
            'INSERT INTO album_photos (album_id, fichier, legende, position, publie)
               VALUES (?, ?, ?, ?, ?)',
            [$albumId, $fichier, $legende, $position, $publie ? 1 : 0]
        );
    }

    /** Mettre à jour une photo. */
    public function updatePhoto(int $id, ?string $fichier, ?string $legende, int $position, int $publie): void
    {
        $sets = [];
        $params = [];
        if ($fichier !== null) {
            $sets[] = 'fichier = ?';
            $params[] = $fichier;
        }
        if ($legende !== null) {
            $sets[] = 'legende = ?';
            $params[] = $legende;
        }
        $sets[] = 'position = ?';
        $params[] = $position;
        $sets[] = 'publie = ?';
        $params[] = $publie ? 1 : 0;
        $params[] = $id;
        Query::run('UPDATE album_photos SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    /** Supprimer une photo. Retourne le chemin du fichier (pour suppression physique côté service). */
    public function deletePhoto(int $id): ?string
    {
        $row = Query::one('SELECT fichier FROM album_photos WHERE id = ?', [$id]);
        if (!$row) {
            return null;
        }
        Query::run('DELETE FROM album_photos WHERE id = ?', [$id]);
        return (string) $row['fichier'];
    }

    /** Définir quels albums sont publiés (liste des ids publiés, les autres dépubliés). */
    public function setPublie(array $ids): void
    {
        Query::transaction(function () use ($ids): void {
            Query::run('UPDATE albums SET publie = 0');
            foreach (array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $i) => $i > 0))) as $id) {
                Query::run('UPDATE albums SET publie = 1 WHERE id = ?', [$id]);
            }
        });
    }

    /** Définir quels albums sont archivés. @param int[] $ids */
    public function setArchive(array $ids): void
    {
        Query::transaction(function () use ($ids): void {
            Query::run('UPDATE albums SET archive = 0');
            foreach (array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $i) => $i > 0))) as $id) {
                Query::run('UPDATE albums SET archive = 1 WHERE id = ?', [$id]);
            }
        });
    }

    /** Désarchive exactement les albums listés (passe archive = 0). @param int[] $ids */
    public function setUnarchive(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $i) => $i > 0)));
        if ($ids === []) {
            return;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        Query::run("UPDATE albums SET archive = 0 WHERE id IN ($in)", $ids);
    }

    /** Réorganiser les photos d'un album. @param array<int,int> $positions [photo_id => position] */
    public function reorderPhotos(array $positions): void
    {
        foreach ($positions as $photoId => $position) {
            Query::run('UPDATE album_photos SET position = ? WHERE id = ?', [(int) $position, (int) $photoId]);
        }
    }

    /** Albums disponibles pour le filtre d'année (publiés). */
    public function years(): array
    {
        $rows = Query::all(
            'SELECT DISTINCT annee FROM albums WHERE publie = 1 AND archive = 0 AND annee IS NOT NULL ORDER BY annee DESC'
        );
        return array_map(static fn($r) => (int) $r['annee'], $rows);
    }

    /** Catégories disponibles pour le filtre (publiés). */
    public function categories(): array
    {
        $rows = Query::all(
            'SELECT DISTINCT categorie FROM albums WHERE publie = 1 AND archive = 0 AND categorie IS NOT NULL AND categorie <> "" ORDER BY categorie'
        );
        return array_map(static fn($r) => (string) $r['categorie'], $rows);
    }
}
