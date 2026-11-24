<?php

/**
 * Tâche planifiée - Rappels & bilans des Rapports du Jour (module 20).
 * -------------------------------------------------------------------
 * Usage CLI (à planifier, par ex. toutes les 15 minutes) :
 *   * /usr/bin/php /home/xxx/labelleeglise/cron-rapport-rappels.php
 *   * /usr/bin/php .../cron-rapport-rappels.php --dry-run   (simulation, aucun envoi)
 *
 * Peut aussi être déclenché par un planificateur externe (webcron) via :
 *   index.php?action=rapport_cron&token=<cron_token>[&dry_run=1]
 * (jeton secret côté Paramètres > Suivi rapports).
 *
 * Sécurité : verrou MySQL (GET_LOCK) - un seul traitement à la fois, même
 * si deux déclenchements se chevauchent. Aucune sortie n'expose de données
 * personnelles ; le retour est un récapitulatif chiffré (JSON).
 */

declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    // Ce fichier n'est pas une page web : accès direct via le navigateur refusé.
    http_response_code(404);
    exit('Not found');
}

require_once __DIR__ . '/Bootstrap/init.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

try {
    $summary = rapport_reminder_service()->processAll($dryRun);
} catch (\Throwable $e) {
    \App\Core\Logger::error('cron-rapport-rappels : échec fatal', ['error' => $e->getMessage()]);
    fwrite(STDERR, 'Échec : ' . $e->getMessage() . "\n");
    exit(1);
}

$line = json_encode($summary, JSON_UNESCAPED_UNICODE);
echo ($dryRun ? '[dry-run] ' : '') . $line . "\n";
exit(0);
