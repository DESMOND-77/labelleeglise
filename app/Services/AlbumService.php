<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Query;
use App\Core\Upload;
use App\Repositories\AlbumRepository;
use App\Repositories\EvenementRepository;

/**
 * Logique métier des albums / galerie multimédia.
 */
class AlbumService
{
    private AlbumRepository $repo;
    private EvenementRepository $evenements;

    public function __construct(?AlbumRepository $repo = null, ?EvenementRepository $evenements = null)
    {
        $this->repo = $repo ?? new AlbumRepository();
        $this->evenements = $evenements ?? new EvenementRepository();
    }

    /** @return list<array<string,mixed>> Albums publiés (site public). */
    public function published(): array
    {
        $albums = $this->repo->published();
        foreach ($albums as $album => $id) {
            $albums[$album]['nb_photos'] = count($this->repo->publishedPhotos((int) $id) ?? 0);
        }
        return $albums;
    }

    /** Album publié par id (site public) avec ses photos. */
    public function findPublished(int $id): ?array
    {
        $album = $this->repo->findPublished($id);
        if ($album === null) {
            return null;
        }
        $album['photos'] = $this->repo->publishedPhotos($id);
        return $album;
    }

    /** @return list<array<string,mixed>> Tous les albums (admin). */
    public function allForAdmin(): array
    {
        return $this->repo->allForAdmin();
    }

    /** Album pour l'admin avec ses photos. */
    public function findForAdmin(int $id): ?array
    {
        $album = $this->repo->findForAdmin($id);
        if ($album === null) {
            return null;
        }
        $album['photos'] = $this->repo->photosForAdmin($id);
        return $album;
    }

    /** Événements disponibles pour le lien optionnel (admin). */
    public function evenementsPourLien(): array
    {
        return $this->evenements->all();
    }

    /** Créer un album. Retourne l'id. */
    public function create(array $data): int
    {
        $couverture = null;
        if (!empty($_FILES['couverture']['name'])) {
            $couverture = Upload::photo('couverture');
        }
        return $this->repo->create(
            (string) $data['titre'],
            trim((string) ($data['description'] ?? '')) ?: null,
            $couverture,
            trim((string) ($data['categorie'] ?? '')) ?: null,
            (string) ($data['annee'] ?? '') !== '' ? (int) $data['annee'] : null,
            !empty($data['publie']) ? 1 : 0,
            !empty($data['archive']) ? 1 : 0,
            (int) ($data['evenement_id'] ?? 0) ?: null
        );
    }

    /** Mettre à jour un album. */
    public function update(int $id, array $data): void
    {
        $album = $this->repo->findForAdmin($id);
        if ($album === null) {
            return;
        }
        $couverture = $album['couverture'];
        if (!empty($_FILES['couverture']['name'])) {
            $nouvelle = Upload::photo('couverture');
            if ($nouvelle !== null) {
                Upload::deletePhoto($couverture);
                $couverture = $nouvelle;
            }
        } elseif (!empty($data['couverture_current']) && $data['couverture_current'] !== '') {
            $couverture = $data['couverture_current'];
        }
        $this->repo->update(
            $id,
            (string) $data['titre'],
            trim((string) ($data['description'] ?? '')) ?: null,
            $couverture,
            trim((string) ($data['categorie'] ?? '')) ?: null,
            (string) ($data['annee'] ?? '') !== '' ? (int) $data['annee'] : null,
            !empty($data['publie']) ? 1 : 0,
            !empty($data['archive']) ? 1 : 0,
            (int) ($data['evenement_id'] ?? 0) ?: null
        );
    }

    /** Supprimer un album et ses fichiers physiques. */
    public function delete(int $id): void
    {
        $album = $this->repo->findForAdmin($id);
        if ($album === null) {
            return;
        }
        foreach ($this->repo->photosForAdmin($id) as $photo) {
            Upload::deletePhoto($photo['fichier']);
        }
        Upload::deletePhoto($album['couverture']);
        $this->repo->delete($id);
    }

    /** Importer plusieurs photos dans un album.
     * $files peut être :
     *  - un tableau de fichiers au format [['name'=>..., 'tmp_name'=>..., ...], ...]
     *  - ou directement $_FILES['photos'] (structure imbriquée de PHP)
     */
    public function importPhotos(int $albumId, array $files): array
    {
        // Normaliser la structure $_FILES (name, tmp_name, etc. imbriqués)
        $normalized = [];
        if (isset($files['name']) && is_array($files['name'])) {
            foreach ($files['name'] as $index => $name) {
                $normalized[] = [
                    'name'     => $name,
                    'type'     => $files['type'][$index] ?? '',
                    'tmp_name' => $files['tmp_name'][$index] ?? '',
                    'error'    => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $files['size'][$index] ?? 0,
                ];
            }
        } else {
            $normalized = $files;
        }

        $importes = 0;
        $erreurs = [];
        $existing = $this->repo->photosForAdmin($albumId);
        $maxPosition = 0;
        foreach ($existing as $p) {
            $maxPosition = max($maxPosition, (int) $p['position']);
        }
        foreach ($normalized as $file) {
            if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
                continue;
            }
            if ($file['size'] > MAX_PHOTO_BYTES) {
                $erreurs[] = $file['name'] . ': fichier trop volumineux (max ' . (MAX_PHOTO_BYTES / 1024 / 1024) . ' Mo)';
                continue;
            }
            $info = @getimagesize($file['tmp_name']);
            if ($info === false) {
                $erreurs[] = $file['name'] . ': fichier invalide';
                continue;
            }
            $extMap = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
            $ext = $extMap[$info[2]] ?? 'jpg';
            if (!is_dir(UPLOAD_DIR)) {
                @mkdir(UPLOAD_DIR, 0775, true);
            }
            $name = 'photo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $full = UPLOAD_DIR . '/' . $name;
            if (!move_uploaded_file($file['tmp_name'], $full)) {
                $erreurs[] = $file['name'] . ': échec de l\'enregistrement';
                continue;
            }
            $maxPosition++;
            $this->repo->addPhoto($albumId, 'uploads/' . $name, null, $maxPosition, 1);
            $importes++;
        }
        return ['importes' => $importes, 'erreurs' => $erreurs];
    }

    /** Mettre à jour une photo existante (fichier optionnel, légende, position, publication). */
    public function updatePhoto(int $id, array $data, ?string $nouveauFichier): void
    {
        $ancienne = Query::one('SELECT fichier FROM album_photos WHERE id = ?', [$id]);
        $fichier = $ancienne['fichier'] ?? null;
        if ($nouveauFichier !== null) {
            Upload::deletePhoto($fichier);
            $fichier = $nouveauFichier;
        }
        $this->repo->updatePhoto(
            $id,
            $fichier,
            trim((string) ($data['legende'] ?? '')) ?: null,
            (int) ($data['position'] ?? 0),
            !empty($data['publie']) ? 1 : 0
        );
    }

    /** Supprimer une photo et son fichier physique. Retourne l'id de l'album. */
    public function deletePhoto(int $id): ?int
    {
        $fichier = $this->repo->deletePhoto($id);
        if ($fichier !== null) {
            Upload::deletePhoto($fichier);
        }
        $row = Query::one('SELECT album_id FROM album_photos WHERE id = ?', [$id]);
        return $row ? (int) $row['album_id'] : null;
    }

    /** Mettre à jour les photos d'un album (légendes, publication, positions).
     * @param int $albumId
     * @param array $post Données POST contenant photo_ids[], photo_legendes[id], photo_publie[id], photo_positions[id]
     */
    public function updatePhotos(int $albumId, array $post): void
    {
        $photoIds = array_map('intval', (array) ($post['photo_ids'] ?? []));
        $legendes = (array) ($post['photo_legendes'] ?? []);
        $publie = (array) ($post['photo_publie'] ?? []);
        $positions = (array) ($post['photo_positions'] ?? []);

        foreach ($photoIds as $photoId) {
            $this->repo->updatePhoto(
                $photoId,
                null, // fichier inchangé
                isset($legendes[$photoId]) ? trim((string) $legendes[$photoId]) : null,
                isset($positions[$photoId]) ? (int) $positions[$photoId] : 0,
                isset($publie[$photoId]) ? 1 : 0
            );
        }
    }

    /** Définir quels albums sont publiés. @param int[] $ids */
    public function setPublie(array $ids): void
    {
        $this->repo->setPublie($ids);
    }

    /** Définir quels albums sont archivés. @param int[] $ids */
    public function setArchive(array $ids): void
    {
        $this->repo->setArchive($ids);
    }

    /** Désarchive exactement les albums listés (passe archive = 0). @param int[] $ids */
    public function setUnarchive(array $ids): void
    {
        $this->repo->setUnarchive($ids);
    }

    /** Réorganiser les photos d'un album. @param array<int,int> $positions [photo_id => position] */
    public function reorderPhotos(array $positions): void
    {
        $this->repo->reorderPhotos($positions);
    }

    /** Années disponibles pour le filtre public. */
    public function years(): array
    {
        return $this->repo->years();
    }

    /** Catégories disponibles pour le filtre public. */
    public function categories(): array
    {
        return $this->repo->categories();
    }
}
