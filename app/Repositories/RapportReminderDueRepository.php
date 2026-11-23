<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Query;

/**
 * Sélection des occurrences dues pour les rappels (tolérante aux crons
 * en retard) : chaque rappel s'ouvre à partir de son heure limite et reste
 * effectif tant que le rapport n'est pas enregistré et le rappel pas encore
 * envoyé (l'idempotence empêche les doublons).
 */
class RapportReminderDueRepository
{
    /**
     * @param string $type preventif|echeance|relance
     * @return array<int,array<string,mixed>> une ligne par occurrence et par responsable
     */
    public function dueReminders(string $type, string $preventifHeure, string $relanceHeure): array
    {
        $params = [];
        if ($type === 'preventif') {
            // La veille à l'heure configurée (par défaut 18:00) ; reste
            // effective si le cron arrive en retard, jusqu'à la date de la réunion.
            $where = "o.report_date >= CURDATE()
                      AND NOW() >= CONCAT(DATE_SUB(o.report_date, INTERVAL 1 DAY), ' ', ?, ':00')";
            $params[] = $preventifHeure;
        } elseif ($type === 'echeance') {
            // Jour J, après l'heure limite fixe. Reste effectif si le cron arrive en retard.
            $where = "o.due_at IS NOT NULL AND o.due_at <= NOW()";
        } elseif ($type === 'relance') {
            // Lendemain à l'heure configurée (par défaut 09:00) ; reste effectif si le cron arrive en retard.
            $where = "NOW() >= CONCAT(DATE_ADD(o.report_date, INTERVAL 1 DAY), ' ', ?, ':00')";
            $params[] = $relanceHeure;
        } else {
            return [];
        }

        return Query::all(
            "SELECT o.*, r.user_id AS responsible_user_id,
                    CONCAT(u.prenom, ' ', u.nom) AS responsible_name,
                    u.email AS responsible_email
               FROM rapport_occurrences o
               JOIN rapport_occurrence_responsables r ON r.occurrence_id = o.id
               JOIN users u ON u.id = r.user_id
              WHERE $where
                AND o.status IN ('pending', 'due', 'overdue')
                AND o.submitted_report_id IS NULL
              ORDER BY o.report_date, o.scope_type, o.scope_id, r.user_id",
            $params
        );
    }
}
