<?php

namespace App\Services;

/**
 * Exports bruts (téléchargement de fichiers) — sortie directe, pas de
 * rendu de page. Chaque méthode termine l'exécution (exit).
 */
class ExportService
{
    /**
     * Export CSV réel des présences d'un utilisateur (spec §27) :
     * Content-Disposition attachment, colonnes Date/Semaine/Culte/Centre/Bacenta/Statut.
     * $rows : lignes issues de AttendanceService::historyForUser().
     */
    public function attendanceCsv(array $user, array $rows): void
    {
        $slug = $this->slug(trim(($user['prenom'] ?? '') . '_' . ($user['nom'] ?? '')));
        $filename = 'presences_' . ($slug !== '' ? $slug : 'membre') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        // BOM UTF-8 pour un affichage correct des accents dans Excel.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Date', 'Semaine', 'Culte', 'Centre', 'Bacenta', 'Statut']);

        foreach ($rows as $r) {
            $date = (string) ($r['date_presence'] ?? '');
            $ts = $date !== '' ? strtotime($date) : false;
            $semaine = $ts !== false ? date('o-\WW', $ts) : '';
            fputcsv($out, [
                $date,
                $semaine,
                $r['culte_nom'] ?? '',
                $r['centre_nom'] ?? '',
                $r['bacenta_nom'] ?? '',
                'Présent',
            ]);
        }
        fclose($out);
        exit;
    }

    /** Translittère et nettoie une chaîne pour un nom de fichier sûr. */
    private function slug(string $s): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t === false || $t === null) {
            $t = $s;
        }
        $t = preg_replace('/[^a-zA-Z0-9]+/', '_', $t) ?? '';
        return trim($t, '_');
    }
}
