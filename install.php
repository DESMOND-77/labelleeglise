<?php
/**
 * Installation — crée les tables + données de démonstration.
 * -------------------------------------------------------------
 * Nouvelle architecture : délègue aux classes de migration/seeding
 * (Database\Migrations, Database\Seeders).
 *
 * Usage :
 *   1. adapter Config/database.php
 *   2. php install.php   (ou ouvrir install.php dans le navigateur)
 */

declare(strict_types=1);

require_once __DIR__ . '/Bootstrap/init.php';

// Les fichiers de migration / seeder définissent des fonctions procédurales
// (namespace), pas des classes : on les charge explicitement.
require_once BASE_PATH . '/Database/Migrations/2024_01_01_000000_create_schema.php';
require_once BASE_PATH . '/Database/Seeders/DatabaseSeeder.php';

/**
 * Exécute la migration complète (drop + création + seed).
 */
function run_install(): void
{
    // Réinitialisation complète (rend le script ré-exécutable).
    \Database\Migrations\down();
    \Database\Migrations\up();

    // Données de démonstration.
    \Database\Seeders\seed();
}

$cli = (PHP_SAPI === 'cli');
try {
    run_install();
} catch (\Throwable $e) {
    \App\Core\Logger::error('Installation failed', ['message' => $e->getMessage()]);
    if ($cli) {
        fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
        exit(1);
    }
    http_response_code(500);
    exit('Erreur d\'installation : ' . h($e->getMessage()));
}

if ($cli) {
    echo "✔ Base de données installée : tables créées + données de démonstration.\n";
    echo "  Comptes :\n";
    echo "    admin@labelleeglise.ga / LBEGF\n";
    echo "    user@labelleeglise.ga / user1111\n";
    echo "    resp.bacenta.sion@labelleeglise.ga / ESKLna\n";
    echo "    berger.eric.bongo@labelleeglise.ga / BergerEB1\n";
    exit(0);
}

echo '<h1>Installation terminée ✔</h1><p>Fermez cette page et connectez-vous sur <a href="index.php">index.php</a> (admin@labelleeglise.ga / LBEGF).</p>';

