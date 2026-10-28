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
        // Landing v2 (spec unifiée §4.2, §4.8, §4.11-4.13)
        'slogan', 'accueil_texte', 'hero_image', 'hero_image_alt', 'adresse',
        'predication_titre', 'predication_date', 'predication_url', 'lien_don', 'don_usage',
        'mentions_legales', 'confidentialite',
        // Section « À propos » (le texte vit dans la table presentation)
        'about_image', 'about_image_alt',
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
                    cp.intro, cp.photo, cp.vision, cp.direction, cp.origine, cp.objectifs,
                    cp.situation_annees, cp.situation_pasteurs, cp.situation_bergers,
                    cp.situation_leaders, cp.situation_bacentas
               FROM centres c
          LEFT JOIN centres_presentation cp
                 ON cp.id = (SELECT MIN(x.id) FROM centres_presentation x WHERE x.centre_id = c.id)
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

    /** Membres d'équipe publiés (aucun lien vers `users`). */
    public function publishedEquipe(): array
    {
        return Query::all(
            'SELECT id, nom_affichage, role_affichage, bio, photo, categorie FROM equipe WHERE publie = 1 ORDER BY id'
        );
    }

    /** Événements publiés, non terminés à $now ('Y-m-d H:i:s'). */
    public function upcomingEvents(string $now, int $limit = 12): array
    {
        return Query::all(
            'SELECT id, nom, date_debut, date_fin, lieu, description, affiche, lien_inscription
               FROM evenements
              WHERE publie = 1 AND COALESCE(date_fin, date_debut) >= ?
              ORDER BY date_debut ASC LIMIT ' . max(1, $limit),
            [$now]
        );
    }

    /** FAQ publique publiée, ordonnée. */
    public function publishedFaq(): array
    {
        return Query::all(
            'SELECT id, question, reponse FROM faq_publique WHERE publie = 1 ORDER BY ordre, id'
        );
    }

    /** Ministères publiés : bacentas (prière) et basontas (groupes/services). */
    public function publishedMinistries(): array
    {
        return [
            'bacentas' => Query::all(
                "SELECT id, nom, description_publique,
                        jours_semaine, heure_debut, heure_fin
                   FROM bacentas WHERE publie = 1 ORDER BY id"
            ),
            'basontas' => Query::all(
                "SELECT id, nom, description_publique,
                        jours_semaine, heure_debut, heure_fin
                   FROM basontas WHERE publie = 1 ORDER BY id"
            ),
        ];
    }

    /* ---------- Administration (admin uniquement - garde côté contrôleur) ---------- */

    /** Tous les centres avec leurs champs publics (formulaire admin). */
    public function allCentresForAdmin(): array
    {
        return Query::all(
            'SELECT id, nom, adresse, quartier, ville, itineraire_url, telephone, whatsapp, email, responsable_public, publie
               FROM centres ORDER BY id'
        );
    }

    /** Un centre avec ses champs publics et ses horaires structurés (fiche admin). */
    public function centreForAdmin(int $id): ?array
    {
        $row = Query::one(
            'SELECT id, nom, adresse, quartier, ville, itineraire_url, telephone, whatsapp, email, responsable_public, publie
               FROM centres WHERE id = ?',
            [$id]
        );
        if (!$row) {
            return null;
        }
        $row['horaires_rows'] = $this->horairesByCentre([$id])[$id] ?? [];
        return $row;
    }

    public function equipeForAdmin(): array
    {
        return Query::all('SELECT id, nom_affichage, role_affichage, categorie, publie FROM equipe ORDER BY id');
    }

    /** Publie exactement les membres listés, dépublie les autres. @param int[] $ids */
    public function setEquipePublie(array $ids): void
    {
        Query::transaction(function () use ($ids): void {
            Query::run('UPDATE equipe SET publie = 0');
            foreach (array_unique(array_filter(array_map('intval', $ids), static fn(int $i) => $i > 0)) as $id) {
                Query::run('UPDATE equipe SET publie = 1 WHERE id = ?', [$id]);
            }
        });
    }

    /** Événements à venir ou en cours (écran admin). */
    public function eventsForAdmin(string $now): array
    {
        return Query::all(
            'SELECT id, nom, date_debut, date_fin, lieu, description, affiche, lien_inscription, publie FROM evenements
              WHERE COALESCE(date_fin, date_debut) >= ? ORDER BY date_debut ASC',
            [$now]
        );
    }

    public function saveEventPublic(int $id, bool $publie, ?string $description, ?string $affiche, ?string $lienInscription): void
    {
        Query::run(
            'UPDATE evenements SET publie = ?, description = ?, affiche = COALESCE(?, affiche), lien_inscription = ? WHERE id = ?',
            [$publie ? 1 : 0, $description, $affiche, $lienInscription ?: null, $id]
        );
    }

    /* ---------- FAQ publique (admin) ---------- */

    /** @return list<array{id:int,question:string,reponse:string,ordre:int,publie:int}> */
    public function faqForAdmin(): array
    {
        return Query::all('SELECT id, question, reponse, ordre, publie FROM faq_publique ORDER BY ordre, id');
    }

    public function faqExists(int $id): bool
    {
        return (int) Query::value('SELECT COUNT(*) FROM faq_publique WHERE id = ?', [$id]) > 0;
    }

    /**
     * Insère ou met à jour les entrées de FAQ (l'ordre suit $rows).
     * @param list<array{id?:int,question:string,reponse:string,publie:bool}> $rows
     */
    public function saveFaq(array $rows): void
    {
        Query::transaction(function () use ($rows): void {
            $ordre = 0;
            foreach ($rows as $row) {
                $ordre++;
                if (!empty($row['id'])) {
                    Query::run(
                        'UPDATE faq_publique SET question = ?, reponse = ?, ordre = ?, publie = ? WHERE id = ?',
                        [$row['question'], $row['reponse'], $ordre, $row['publie'] ? 1 : 0, (int) $row['id']]
                    );
                } else {
                    Query::run(
                        'INSERT INTO faq_publique (question, reponse, ordre, publie) VALUES (?, ?, ?, ?)',
                        [$row['question'], $row['reponse'], $ordre, $row['publie'] ? 1 : 0]
                    );
                }
            }
        });
    }

    public function deleteFaq(int $id): void
    {
        Query::run('DELETE FROM faq_publique WHERE id = ?', [$id]);
    }

    /* ---------- Ministères publics (admin) ---------- */

    /** @return array{bacentas:list<array<string,mixed>>,basontas:list<array<string,mixed>>} */
    public function ministriesForAdmin(): array
    {
        return [
            'bacentas' => Query::all('SELECT id, nom, description_publique, publie FROM bacentas ORDER BY id'),
            'basontas' => Query::all('SELECT id, nom, description_publique, publie FROM basontas ORDER BY id'),
        ];
    }

    /** Publie exactement les lignes listées, dépublie les autres. @param int[] $ids */
    public function setMinistryPublie(string $table, array $ids): void
    {
        if (!in_array($table, ['bacentas', 'basontas'], true)) {
            return;
        }
        Query::transaction(function () use ($table, $ids): void {
            Query::run("UPDATE {$table} SET publie = 0");
            foreach (array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $i) => $i > 0))) as $id) {
                Query::run("UPDATE {$table} SET publie = 1 WHERE id = ?", [$id]);
            }
        });
    }

    /** @param array<int,string> $descriptions [id => description] */
    public function saveMinistryDescriptions(string $table, array $descriptions): void
    {
        if (!in_array($table, ['bacentas', 'basontas'], true)) {
            return;
        }
        foreach ($descriptions as $id => $description) {
            Query::run("UPDATE {$table} SET description_publique = ? WHERE id = ?", [$description, (int) $id]);
        }
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
                    telephone = ?, whatsapp = ?, email = ?, responsable_public = ?, publie = ? WHERE id = ?',
            [$f['adresse'], $f['quartier'], $f['ville'], $f['itineraire_url'],
             $f['telephone'], $f['whatsapp'], $f['email'], $f['responsable_public'], $f['publie'] ? 1 : 0, $id]
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
