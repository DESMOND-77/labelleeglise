<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AlbumService;

/**
 * Contrôleur public de la galerie Album.
 * Pages : listing des albums (cartes) et détail d'un album (grille + lightbox).
 */
class AlbumController extends Controller
{
    /** GET ?page=albums - Listing public des albums publiés. */
    public function index(): void
    {
        // Albums publiés uniquement (AlbumService::published) : la copie
        // anonyme peut être conservée hors ligne. Les images `uploads/` ne
        // sont jamais mises en cache (dossier mixte, plan §5.2).
        pwa_mark_public_cacheable('albums');

        $service = new AlbumService();
        $albums = $service->published();
        $years = $service->years();
        $categories = $service->categories();

        $anneeFilter = (string) ($_GET['annee'] ?? '');
        $categorieFilter = (string) ($_GET['categorie'] ?? '');

        if ($anneeFilter !== '') {
            $albums = array_values(array_filter($albums, static fn($a) => (string) ($a['annee'] ?? '') === $anneeFilter));
        }
        if ($categorieFilter !== '') {
            $albums = array_values(array_filter($albums, static fn($a) => (string) ($a['categorie'] ?? '') === $categorieFilter));
        }

        echo view('layouts/public', [
            'title'         => 'Album - ' . APP_NAME,
            'canonical'     => url('index.php', ['page' => 'albums']),
            'jsonLd'        => null,
            'hasUser'       => (bool) current_user(),
            'content'       => view('pages/albums', [
                'albums'      => $albums,
                'years'       => $years,
                'categories'  => $categories,
                'anneeFilter' => $anneeFilter,
                'categorieFilter' => $categorieFilter,
            ]),
            'brand'         => APP_NAME,
            'ogImage'       => '',
            'metaDescription' => 'Galerie photo de La Belle Église Intenationale Franceville',
        ]);
    }

    /** GET ?page=album&id=... - Détail public d'un album (grille + lightbox). */
    public function detail(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            redirect('index.php', ['page' => 'albums']);
        }

        $service = new AlbumService();
        $album = $service->findPublished($id);
        if ($album === null) {
            redirect('index.php', ['page' => 'albums']);
        }

        pwa_mark_public_cacheable('album');

        echo view('layouts/public', [
            'title'         => $album['titre'] . ' - ' . APP_NAME,
            'canonical'     => url('index.php', ['page' => 'album', 'id' => $id]),
            'jsonLd'        => null,
            'hasUser'       => (bool) current_user(),
            'content'       => view('pages/album_detail', [
                'album' => $album,
            ]),
            'brand'         => APP_NAME,
            'ogImage'       => !empty($album['couverture']) ? asset($album['couverture']) : '',
            'metaDescription' => $album['description'] ?? '',
        ]);
    }
}
