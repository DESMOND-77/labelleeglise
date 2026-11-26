<?php

/**
 * Smoke test statique de la PWA / synchronisation hors ligne - sans base.
 *
 *   php tests/pwa-smoke.php
 *   node --check assets/js/pwa/*.js sw.js        (complément syntaxe JS)
 *
 * Vérifie la cohérence de la configuration, des routes, du manifeste, du
 * Service Worker, des layouts et des adaptateurs de formulaires. Ne remplace
 * ni la recette navigateur (installation, offline/online) ni les tests de
 * synchronisation par rôle.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

$ok = static function (bool $cond, string $label) use (&$failures, &$checks): void {
    $checks++;
    if ($cond) {
        echo "  ✓ {$label}\n";
    } else {
        $failures[] = $label;
        echo "  ✗ {$label}\n";
    }
};

$read = static fn(string $path): string => (string) @file_get_contents($root . '/' . $path);

echo "== PWA / hors ligne - smoke test ==\n";

/* ---------- 1. Configuration ---------- */
echo "-- Configuration (Config/pwa.php)\n";
// Amorçage minimal : env + chemins, SANS base de données ni session.
define('BASE_PATH', $root);
require_once $root . '/Bootstrap/env.php';   // env_value()/env_bool()
require_once $root . '/Config/paths.php';    // APP_CONFIG_PATH, APP_PUBLIC_PATH…
$config = require $root . '/Config/pwa.php';
$ok(is_array($config), 'Config/pwa.php renvoie un tableau');
$ok(array_key_exists('enabled', $config), 'clé `enabled` présente');
$ok(array_key_exists('offline_capture', $config), 'clé `offline_capture` présente');
$ok(array_key_exists('service_worker', $config), 'clé `service_worker` présente');
$ok(($config['vault']['mode'] ?? '') === 'simple' || ($config['vault']['mode'] ?? '') === 'pin', 'mode de coffre valide (simple|pin)');
$ok((int) ($config['vault']['retention_days'] ?? 0) >= 1, 'durée de conservation locale >= 1 jour');
$ok(in_array('eglise', (array) ($config['public_cacheable_pages'] ?? []), true), 'vitrine publique allowlistée');
$ok(!in_array('login', (array) ($config['public_cacheable_pages'] ?? []), true), 'page de connexion JAMAIS allowlistée');
$ok(!in_array('accueil', (array) ($config['public_cacheable_pages'] ?? []), true), 'pages de gestion JAMAIS allowlistées');

foreach (['rapport_jour', 'suivi_hebdo', 'public_register'] as $type) {
    $operation = $config['operations'][$type] ?? null;
    $ok(is_array($operation) && !empty($operation['enabled']), "opération allowlistée : {$type}");
    $ok((int) ($operation['schema_version'] ?? 0) === 1, "schéma v1 pour {$type}");
}
$ok($config['operations']['public_register']['public'] ?? false, 'inscription publique marquée `public` (route séparée)');
$ok(!($config['operations']['rapport_jour']['public'] ?? true), 'rapport du jour privé (session requise)');
$ok(count((array) ($config['excluded_operations'] ?? [])) >= 10, 'liste des exclusions documentée (finances, présences, rôles…)');
foreach (['save_offrandes_centre', 'point_culte', 'save_bus_budget', 'delete_user', 'assign_responsibility'] as $excluded) {
    $ok(in_array($excluded, (array) ($config['excluded_operations'] ?? []), true), "exclusion explicite : {$excluded}");
}

/* ---------- 2. Routes et front controller ---------- */
echo "-- Routes et front controller\n";
$routes = $read('Routes/web.php');
foreach (['pwa/v1/manifest', 'pwa/v1/assets', 'pwa/v1/bootstrap', 'pwa/v1/status', 'pwa/v1/sync', 'pwa/v1/public/register', 'pwa_offline'] as $route) {
    $ok(strpos($routes, "'" . $route . "'") !== false, "route déclarée : {$route}");
}
$index = $read('index.php');
$apiPos = strpos($index, "\$_GET['api']");
$postPos = strpos($index, "REQUEST_METHOD'] === 'POST'");
$ok($apiPos !== false && $postPos !== false && $apiPos < $postPos, 'dispatch API placé AVANT le dispatch des POST');
$ok(strpos($index, 'Routes/web.php') !== false, "l'API réutilise le routeur maison (pas de second front controller)");

/* ---------- 3. Manifeste et icônes ---------- */
echo "-- Manifeste et icônes\n";
$manifestRaw = $read('assets/images/site.webmanifest');
$manifest = json_decode($manifestRaw, true);
$ok(is_array($manifest), 'manifeste JSON valide');
$ok(strpos((string) ($manifest['name'] ?? ''), 'Internationale') !== false, 'nom de marque corrigé (« Internationale »)');
$ok(strpos((string) ($manifest['name'] ?? ''), 'Intenationale') === false, 'faute « Intenationale » absente');
$ok(!array_key_exists('orientation', $manifest), 'aucun verrouillage d’orientation (portrait/paysage/desktop)');
$ok(strtoupper((string) ($manifest['theme_color'] ?? '')) === '#0B1020', 'theme_color aligné sur les layouts');
$purposes = array_column($manifest['icons'] ?? [], 'purpose');
$ok(in_array('any', $purposes, true), 'icônes `any` présentes');
$ok(in_array('maskable', $purposes, true), 'icônes `maskable` dédiées (zone de sécurité 80 %)');
foreach (['icon-192.png', 'icon-512.png', 'icon-maskable-192.png', 'icon-maskable-512.png', 'apple-touch-icon.png'] as $icon) {
    $ok(is_file($root . '/assets/images/' . $icon), "fichier icône présent : {$icon}");
}

/* ---------- 4. Service Worker ---------- */
echo "-- Service Worker (sw.js)\n";
$sw = $read('sw.js');
$ok($sw !== '', 'sw.js présent à la racine web');
$ok(strpos($sw, "X-LBE-Cacheable") !== false, 'mise en cache publique conditionnée par l’en-tête serveur');
$ok(strpos($sw, 'caches.delete') !== false, 'purge des caches obsolètes');
$ok(strpos($sw, 'indexedDB.deleteDatabase') === false, 'le Service Worker ne supprime JAMAIS IndexedDB');
$ok(strpos($sw, "skipWaiting()") !== false && strpos($sw, "data.type === 'skip-waiting'") !== false, 'mise à jour sur action explicite uniquement');
$ok(strpos($sw, "url.searchParams.has('api')") !== false, 'routes API jamais mises en cache');
$ok(strpos($sw, 'url.origin !== self.location.origin') !== false, 'aucune interception cross-origin');
$ok(strpos($sw, 'handlePostNavigation') !== false, 'capture d’un envoi interrompu (allowlist seulement)');
$ok(strpos($sw, "credentials: 'same-origin'") !== false, 'requêtes de navigation avec cookies same-origin');
$ok(strpos($sw, 'isSecretField') !== false, 'aucun secret (mot de passe) écrit sur l’appareil par la capture SW');

/* ---------- 5. Layouts, helpers et en-têtes ---------- */
echo "-- Layouts, helpers, en-têtes\n";
$layout = $read('Views/layouts/layout.php');
$public = $read('Views/layouts/public.php');
$ok(strpos($layout, 'pwa_head_tags()') !== false, 'layout de gestion : amorçage PWA dans <head>');
$ok(strpos($layout, 'pwa_foot_tags()') !== false, 'layout de gestion : scripts PWA en pied de page');
$ok(strpos($public, 'pwa_head_tags()') !== false, 'layout public : manifest + amorçage PWA');
$ok(strpos($public, 'pwa_foot_tags()') !== false, 'layout public : scripts PWA');
$ok(strpos($layout, "'page' => 'pwa_offline'") !== false, 'accès « Données hors ligne » depuis le menu profil');
$init = $read('Bootstrap/init.php');
$ok(strpos($init, 'app/Helpers/pwa.php') !== false, 'helpers PWA chargés au boot');
$ok(strpos($init, 'pwa_send_no_store_headers()') !== false, 'no-store par défaut sur toute réponse dynamique');
$helpers = $read('app/Helpers/pwa.php');
$ok(strpos($helpers, 'current_user() !== null') !== false, 'une réponse personnalisée n’est jamais mettable en cache');
$ok(strpos($helpers, "header('Cache-Control: no-store") !== false, 'en-têtes no-store centralisés');

/* ---------- 6. Contrôleurs / services / dépôt ---------- */
echo "-- Couche serveur\n";
foreach (['app/Controllers/PwaSyncController.php', 'app/Services/PwaSyncService.php', 'app/Repositories/PwaSyncRepository.php'] as $file) {
    $ok(is_file($root . '/' . $file), "fichier présent : {$file}");
}
$service = $read('app/Services/PwaSyncService.php');
$ok(strpos($service, 'auth_can_report_for_centre') !== false, 'périmètre rapport relu en base au commit');
$ok(strpos($service, 'hash_equals') !== false, 'comparaisons d’empreintes en temps constant');
$ok(strpos($service, 'Query::transaction') !== false, 'mutation + résultat commités ensemble');
$ok(strpos($service, 'save_suivi_week') !== false, 'suivi hebdo réutilise le chemin métier existant');
$ok(strpos($service, 'registrations->validate') !== false, 'inscription différée revalidée par RegistrationService');
$controller = $read('app/Controllers/PwaSyncController.php');
$ok(strpos($controller, 'guardCsrf') !== false, 'jeton CSRF frais vérifié sur les écritures');
$ok(strpos($controller, 'guardSameOrigin') !== false, 'origine/méthode vérifiées (pas de CORS ouvert)');
$ok(strpos($controller, 'no-store') !== false, 'réponses JSON no-store');
$repository = $read('app/Repositories/PwaSyncRepository.php');
$ok(strpos($repository, 'UNIQUE KEY uniq_pwa_operation') !== false, 'contrainte UNIQUE sur operation_id (idempotence)');
$ok(strpos($repository, "'processing'") !== false, 'état `processing` anti-concurrence');

/* ---------- 7. Migration ---------- */
echo "-- Migration\n";
$migration = $read('Database/Migrations/2024_01_01_000000_create_schema.php');
$ok(strpos($migration, 'pwa_sync_operations') !== false, 'table pwa_sync_operations dans la migration');
$ok(strpos($migration, 'CREATE TABLE IF NOT EXISTS pwa_sync_operations') !== false, 'DDL idempotent (base déjà installée)');
$ok(strpos($migration, "'pwa_sync_operations', 'rapport_audit_logs'") !== false, 'table incluse dans down()');
$ok(strpos($migration, 'payload_hash') !== false, 'empreinte du payload (anomalie même UUID/contenu différent)');

/* ---------- 8. Adaptateurs de formulaires ---------- */
echo "-- Adaptateurs de formulaires (allowlist explicite)\n";
$rapport = $read('Views/pages/rapport_form.php');
$suivi = $read('Views/pages/suivi_week.php');
$register = $read('Views/pages/register.php');
$ok(strpos($rapport, "pwa_form_attrs('rapport_jour', 'save'") !== false, 'rapport du jour déclaré compatible');
$ok(strpos($rapport, 'rapportFingerprint($report)') !== false, 'empreinte de base fournie par le serveur (rapport)');
$ok(strpos($suivi, "pwa_form_attrs('suivi_hebdo', 'save'") !== false, 'suivi hebdo déclaré compatible');
$ok(strpos($suivi, 'suiviFingerprint(') !== false, 'empreinte de base fournie par le serveur (suivi)');
$ok(strpos($register, "pwa_form_attrs('public_register', 'submit'") !== false, 'inscription publique déclarée compatible');
$ok(strpos($register, 'data-pwa-no-store') !== false && strpos($register, 'password,password_confirm') !== false, 'mots de passe JAMAIS stockés localement');
$ok(strpos($register, 'name="website"') !== false, 'champ piège anti-robot sur l’inscription');
$ok(strpos($rapport, '$canEdit ?') !== false, 'aucun attribut hors ligne en consultation seule');

/* ---------- 9. Client navigateur ---------- */
echo "-- Client navigateur\n";
foreach (['offline-db', 'crypto-vault', 'outbox', 'sync-client', 'forms-adapter', 'network-status', 'offline-panel', 'register'] as $module) {
    $ok(is_file($root . '/assets/js/pwa/' . $module . '.js'), "module JS présent : {$module}.js");
}
$offlineHtml = $read('assets/offline.html');
$ok($offlineHtml !== '' && strpos($offlineHtml, '<?php') === false, 'page de secours statique (aucun PHP, aucune donnée privée)');
$ok(strpos($offlineHtml, 'Dernière actualisation') !== false, 'page de secours : date de dernière actualisation');
$adapter = $read('assets/js/pwa/forms-adapter.js');
$ok(strpos($adapter, 'navigator.onLine !== false') !== false, 'en ligne : flux HTML historique inchangé');
$ok(strpos($adapter, 'Brouillon enregistré sur cet appareil') !== false, 'message honnête (« en attente », jamais « enregistré »)');
$client = $read('assets/js/pwa/sync-client.js');
$ok(strpos($client, 'auth_required') !== false, 'session expirée : envoi bloqué, brouillons conservés');
$ok(strpos($client, 'backoffMs') !== false, 'retry avec backoff exponentiel + jitter');
$vault = $read('assets/js/pwa/crypto-vault.js');
$ok(strpos($vault, 'AES-GCM') !== false, 'chiffrement AES-GCM');
$ok(strpos($vault, 'PBKDF2') !== false, 'dérivation PBKDF2 du code PIN');
$ok(strpos($vault, 'additionalData') !== false, 'AAD lié à l’enregistrement (anti déplacement de copie)');
$outboxJs = $read('assets/js/pwa/outbox.js');
$ok(strpos($outboxJs, 'isSecretField') !== false, 'file d’attente : second filet contre la persistance d’un secret');
$ok(strpos($adapter, '__pwa_scope') !== false && strpos($adapter, 'stampScope') !== false, 'envoi en ligne marqué du compte courant (capture SW bien rattachée)');
$panel = $read('assets/js/pwa/offline-panel.js');
$ok(strpos($panel, 'action=logout') !== false, 'garde de déconnexion avec file non synchronisée');
$ok(strpos($panel, 'purgeAccount') !== false, 'purge locale par compte (appareil partagé)');

/* ---------- 10. Exclusions de cache ---------- */
echo "-- Cloisonnement des caches\n";
$ok(strpos($sw, 'uploads') === false || strpos($sw, "isStaticAsset") !== false, 'uploads/ jamais précachés (dossier mixte)');
$publicController = $read('app/Controllers/PublicController.php');
$ok(strpos($publicController, "pwa_mark_public_cacheable('eglise')") !== false, 'vitrine marquée mettable en cache côté serveur');
$albums = $read('app/Controllers/AlbumController.php');
$ok(strpos($albums, "pwa_mark_public_cacheable('albums')") !== false, 'albums publiés marqués côté serveur');
$uploadsHtaccess = $read('uploads/.htaccess');
$ok(strpos($uploadsHtaccess, 'no-store') !== false, 'uploads/ jamais conservé par un cache HTTP (dossier mixte)');
$ok(strpos($read('.htaccess'), '<If "') === false, '.htaccess racine sans directive <If> (compatible Apache 2.2)');

echo "\n== Résultat : " . ($checks - count($failures)) . "/{$checks} vérifications ==\n";
if ($failures) {
    foreach ($failures as $failure) {
        echo "  ÉCHEC : {$failure}\n";
    }
    exit(1);
}
echo "  Aucune régression détectée.\n";
exit(0);
