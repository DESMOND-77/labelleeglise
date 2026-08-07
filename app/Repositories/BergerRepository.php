<?php

namespace App\Repositories;

use App\Core\Query;

/**
 * Fiche berger : suivi hebdo, examens, veillées, visites.
 */
class BergerRepository
{
    /** Suivi hebdomadaire d'un membre (matrice jour × champ). */
    public function suiviWeek(int $userId, string $weekKey): array
    {
        $rows = Query::all('SELECT jour, champ, valeur FROM suivi_hebdo WHERE user_id = ? AND semaine = ?', [$userId, $weekKey]);
        $out = [];
        foreach (WEEK_DAYS as $day) {
            $out[$day] = [];
        }
        foreach ($rows as $r) {
            $out[$r['jour']][$r['champ']] = $r['valeur'];
        }
        return $out;
    }

    public function saveSuiviWeek(int $userId, string $weekKey, array $data): void
    {
        foreach ($data as $day => $fields) {
            foreach ($fields as $field => $value) {
                $value = trim((string) $value);
                $exists = Query::value('SELECT id FROM suivi_hebdo WHERE user_id = ? AND semaine = ? AND jour = ? AND champ = ?',
                    [$userId, $weekKey, $day, $field]);
                if ($exists) {
                    Query::run('UPDATE suivi_hebdo SET valeur = ? WHERE id = ?', [$value, (int) $exists]);
                } elseif ($value !== '') {
                    Query::run('INSERT INTO suivi_hebdo (user_id, semaine, jour, champ, valeur) VALUES (?, ?, ?, ?, ?)',
                        [$userId, $weekKey, $day, $field, $value]);
                }
            }
        }
    }

    public function weeksOfYear(int $userId, int $year): array
    {
        return Query::all('SELECT DISTINCT semaine FROM suivi_hebdo WHERE user_id = ? AND semaine LIKE ? ORDER BY semaine', [$userId, $year . '-W%']);
    }

    public function examens(int $userId): array
    {
        return Query::all('SELECT * FROM examens WHERE user_id = ? ORDER BY date_exam DESC, id DESC', [$userId]);
    }

    public function addExamen(int $userId, string $nom, ?string $date): void
    {
        Query::run('INSERT INTO examens (user_id, nom, date_exam) VALUES (?, ?, ?)', [$userId, $nom, $date ?: null]);
    }

    public function deleteExamen(int $userId, int $examenId): void
    {
        Query::run('DELETE FROM examens WHERE id = ? AND user_id = ?', [$examenId, $userId]);
    }

    public function veillees(int $userId): array
    {
        return Query::all('SELECT * FROM veillees WHERE user_id = ? ORDER BY date_veillee DESC, id DESC', [$userId]);
    }

    public function addVeillee(int $userId, string $date, bool $present): void
    {
        Query::run('INSERT INTO veillees (user_id, date_veillee, present) VALUES (?, ?, ?)', [$userId, $date, (int) $present]);
    }

    public function deleteVeillee(int $userId, int $veilleeId): void
    {
        Query::run('DELETE FROM veillees WHERE id = ? AND user_id = ?', [$veilleeId, $userId]);
    }

    /** Visites d'un bacenta pour un mois (par semaine). */
    public function bacentaVisites(int $bacentaId, string $monthKey): array
    {
        $rows = Query::all(
            "SELECT v.*, CONCAT(u.prenom, ' ', u.nom) AS visite_nom
               FROM visites v LEFT JOIN users u ON u.id = v.visite_user_id
              WHERE v.bacenta_id = ? AND v.mois = ? ORDER BY v.semaine, v.id",
            [$bacentaId, $monthKey]
        );
        $out = [];
        for ($i = 0; $i < 4; $i++) {
            $out[$i] = ['nom_visite' => '', 'date_visite' => '', 'observations' => '', 'visite_user_id' => null];
            foreach ($rows as $r) {
                if ((int) $r['semaine'] === $i) {
                    $nom = $r['nom_visite'] ?: ($r['visite_nom'] ?? '');
                    $out[$i] = [
                        'nom_visite' => $nom,
                        'date_visite' => $r['date_visite'] ?? '',
                        'observations' => $r['observations'] ?? '',
                        'visite_user_id' => $r['visite_user_id'],
                    ];
                    break;
                }
            }
        }
        return $out;
    }

    public function saveBacentaVisites(int $bacentaId, string $monthKey, array $visites, int $visiteurId): void
    {
        foreach ($visites as $i => $v) {
            $nom = trim((string) ($v['nom_visite'] ?? ''));
            $date = trim((string) ($v['date_visite'] ?? '')) ?: date('Y-m-d');
            $obs = trim((string) ($v['observations'] ?? '')) ?: null;
            $exists = Query::value('SELECT id FROM visites WHERE bacenta_id = ? AND mois = ? AND semaine = ?', [$bacentaId, $monthKey, $i]);
            if ($exists) {
                Query::run('UPDATE visites SET nom_visite = ?, date_visite = ?, observations = ? WHERE id = ?',
                    [$nom ?: null, $date, $obs, (int) $exists]);
            } else {
                Query::run('INSERT INTO visites (visiteur_id, bacenta_id, nom_visite, date_visite, mois, semaine, observations) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$visiteurId, $bacentaId, $nom ?: null, $date, $monthKey, $i, $obs]);
            }
        }
    }
}
