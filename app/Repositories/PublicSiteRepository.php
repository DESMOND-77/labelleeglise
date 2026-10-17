<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Query;

/**
 * Données du site public (landing page).
 * Lectures : colonnes publiques uniquement, centres publiés uniquement -
 * aucune jointure vers users / presences / offrandes.
 */
class PublicSiteRepository
{
    /** Clés de `site_settings` reconnues (liste blanche). */
    public const SETTING_KEYS = [
        'nom_affichage', 'telephone', 'email', 'whatsapp',
        'facebook', 'youtube', 'instagram', 'lien_direct',
    ];

    /** @return array<string,string> */
    public function settings(): array
    {
        $out = [];
        foreach (Query::all('SELECT cle, valeur FROM site_settings') as $row) {
            $out[(string) $row['cle']] = (string) ($row['valeur'] ?? '');
        }
        return $out;
    }

    /** @param array<string,string> $values */
    public function saveSettings(array $values): void
    {
        foreach (self::SETTING_KEYS as $key) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            Query::run(
                'INSERT INTO site_settings (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)',
                [$key, $values[$key]]
            );
        }
    }

    /** Centres publiés, avec l'introduction de leur article de présentation. */
    public function publishedCentres(): array
    {
        return Query::all(
            "SELECT c.id, c.nom, c.adresse, c.quartier, c.ville, c.itineraire_url,
                    c.telephone, c.whatsapp, c.email,
                    (SELECT cp.intro FROM centres_presentation cp WHERE cp.centre_id = c.id ORDER BY cp.id LIMIT 1) AS intro,
                    (SELECT cp.photo FROM centres_presentation cp WHERE cp.centre_id = c.id ORDER BY cp.id LIMIT 1) AS photo
               FROM centres c
              WHERE c.publie = 1
              ORDER BY c.id"
        );
    }

    /** Horaires groupés par centre : [centre_id => [horaire, …]]. */
    public function horairesByCentre(array $centreIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $centreIds), static fn(int $i) => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $rows = Query::all(
            "SELECT centre_id, libelle, jours, heure_debut, heure_fin
               FROM centre_horaires WHERE centre_id IN ($in) ORDER BY centre_id, ordre, id",
            $ids
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['centre_id']][] = $r;
        }
        return $out;
    }

    /* ---------- Administration (admin uniquement - garde côté contrôleur) ---------- */

    /** Tous les centres avec leurs champs publics (formulaire admin). */
    public function allCentresForAdmin(): array
    {
        return Query::all(
            'SELECT id, nom, adresse, quartier, ville, itineraire_url, telephone, whatsapp, email, publie
               FROM centres ORDER BY id'
        );
    }

    public function centreExists(int $id): bool
    {
        return (int) Query::value('SELECT COUNT(*) FROM centres WHERE id = ?', [$id]) > 0;
    }

    /** @param array<string,mixed> $f */
    public function saveCentrePublic(int $id, array $f): void
    {
        Query::run(
            'UPDATE centres SET adresse = ?, quartier = ?, ville = ?, itineraire_url = ?,
                    telephone = ?, whatsapp = ?, email = ?, publie = ? WHERE id = ?',
            [$f['adresse'], $f['quartier'], $f['ville'], $f['itineraire_url'],
             $f['telephone'], $f['whatsapp'], $f['email'], $f['publie'] ? 1 : 0, $id]
        );
    }

    /** Remplace les horaires d'un centre (transaction). @param array<int,array<string,mixed>> $rows */
    public function replaceHoraires(int $centreId, array $rows): void
    {
        Query::transaction(function () use ($centreId, $rows): void {
            Query::run('DELETE FROM centre_horaires WHERE centre_id = ?', [$centreId]);
            foreach (array_values($rows) as $i => $r) {
                Query::run(
                    'INSERT INTO centre_horaires (centre_id, libelle, jours, heure_debut, heure_fin, ordre) VALUES (?, ?, ?, ?, ?, ?)',
                    [$centreId, $r['libelle'], $r['jours'], $r['heure_debut'], $r['heure_fin'], $i]
                );
            }
        });
    }
}
