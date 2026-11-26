<?php

/**
 * Helpers PWA / mode hors ligne (plan `docs/plans/pwa-offline-sync-implementation-plan.md`).
 * -------------------------------------------------------------
 * Chargés au boot (Bootstrap/init.php) comme les autres helpers.
 *
 * Rôle : centraliser la configuration PWA, la résolution des URLs selon la
 * racine réelle de déploiement (le site n'est pas toujours servi à `/`),
 * les en-têtes HTTP de cache et l'empreinte de concurrence optimiste.
 */

declare(strict_types=1);

/* ---------- Configuration ---------- */

/** Configuration PWA (Config/pwa.php), mise en cache par requête. */
function pwa_config(): array
{
    static $config = null;
    if ($config === null) {
        $file = APP_CONFIG_PATH . '/pwa.php';
        $loaded = is_file($file) ? require $file : [];
        $config = is_array($loaded) ? $loaded : [];
    }
    return $config;
}

/** La couche PWA est-elle activée ? (rollback global par configuration) */
function pwa_enabled(): bool
{
    return (bool) (pwa_config()['enabled'] ?? false);
}

/** Le Service Worker / les caches statiques sont-ils activés ? */
function pwa_service_worker_enabled(): bool
{
    return pwa_enabled() && (bool) (pwa_config()['service_worker'] ?? false);
}

/** La saisie/brouillons hors ligne sont-ils activés ? */
function pwa_offline_capture_enabled(): bool
{
    return pwa_enabled() && (bool) (pwa_config()['offline_capture'] ?? false);
}

/* ---------- Racine de déploiement ---------- */

/**
 * Chemin racine (absolu côté navigateur) du déploiement, slash final inclus.
 * `/` si le site est à la racine web, `/labelleeglise/` dans un sous-dossier.
 * Sert au manifest, au scope du Service Worker et aux URLs de précache :
 * on ne suppose jamais que le site est servi à la racine (plan §4.2).
 */
function app_base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '/index.php'));
    $dir = str_replace('\\', '/', dirname($script));
    $dir = trim($dir);
    if ($dir === '' || $dir === '.' || $dir === '/') {
        return $base = '/';
    }
    // dirname() d'un script en sous-dossier renvoie déjà le préfixe attendu.
    return $base = rtrim($dir, '/') . '/';
}

/** URL absolue côté navigateur depuis la racine de déploiement. */
function pwa_url(string $path): string
{
    return app_base_path() . ltrim($path, '/');
}

/** URL d'un endpoint JSON de la PWA (`index.php?api=...`). */
function pwa_api_url(string $endpoint): string
{
    return pwa_url('index.php') . '?api=' . rawurlencode($endpoint);
}

/* ---------- En-têtes HTTP de cache (plan §5.2, §12) ---------- */

/**
 * En-têtes par défaut de toute réponse dynamique : jamais conservée.
 * Les pages publiques autorisées sont ensuite « promues » par
 * pwa_mark_public_cacheable() (autorisation côté serveur, pas côté client).
 */
function pwa_send_no_store_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    header('Cache-Control: no-store, no-cache, must-revalidate, private', true);
    header('Pragma: no-cache', true);
    header('X-LBE-Cacheable: 0', true);
}

/**
 * Marque la réponse courante comme publiquement mettable en cache par le
 * Service Worker : uniquement si la demande est anonyme (aucune session) et
 * que la page appartient à la liste blanche. Le Service Worker ne met en
 * cache une navigation que si cet en-tête vaut 1 - l'autorisation reste donc
 * décidée par le serveur, au moment de la réponse.
 */
function pwa_mark_public_cacheable(string $page = ''): void
{
    if (PHP_SAPI === 'cli' || headers_sent() || !pwa_service_worker_enabled()) {
        return;
    }

    $allowlist = (array) (pwa_config()['public_cacheable_pages'] ?? []);
    if ($page !== '' && !in_array($page, $allowlist, true)) {
        return;
    }
    // Une réponse personnalisée (utilisateur connecté, jeton, cookie posé)
    // n'est JAMAIS mettable en cache (plan §5.2).
    if (function_exists('current_user') && current_user() !== null) {
        return;
    }
    // Filet de sécurité : toute donnée de session autre que le jeton CSRF
    // (porte d'accès vérifiée, erreurs d'administration…) interdit le cache.
    if (session_status() === PHP_SESSION_ACTIVE
        && array_diff(array_keys((array) $_SESSION), ['csrf']) !== []) {
        return;
    }

    header('Cache-Control: private, max-age=0, must-revalidate', true);
    header('Pragma: ', true);
    header('Vary: Cookie, Accept-Encoding', true);
    header('X-LBE-Cacheable: 1', true);
    header('X-LBE-Cached-At: ' . gmdate('c'), true);
}

/* ---------- Empreinte de concurrence optimiste (plan §8) ---------- */

/**
 * Empreinte SHA-256 canonique de l'état serveur d'une entité.
 * Le client ne la calcule jamais : il renvoie celle que le serveur lui a
 * donnée au chargement du formulaire. Toute divergence au moment du commit
 * signale un conflit (jamais d'écrasement silencieux).
 *
 * @param array<string,mixed> $state
 */
function pwa_fingerprint(string $type, array $state): string
{
    ksort($state);
    $canonical = json_encode(
        ['type' => $type, 'state' => $state],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
    );
    return hash('sha256', $canonical === false ? $type : $canonical);
}

/**
 * Empreinte d'un payload d'opération : sert à détecter l'anomalie
 * « même operation_id, contenu différent » (plan §7.3).
 *
 * @param array<string,mixed> $payload
 */
function pwa_payload_hash(array $payload): string
{
    $canonical = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    return hash('sha256', $canonical === false ? '' : $canonical);
}

/* ---------- Liste blanche des opérations ---------- */

/** L'opération (type + action) est-elle autorisée hors ligne ? */
function pwa_operation_allowed(string $type, string $action): bool
{
    $op = (array) (pwa_config()['operations'][$type] ?? []);
    if ($op === [] || empty($op['enabled'])) {
        return false;
    }
    return in_array($action, (array) ($op['actions'] ?? []), true);
}

/** Configuration d'une opération (schéma, champs sensibles, politique). */
function pwa_operation_config(string $type): array
{
    return (array) (pwa_config()['operations'][$type] ?? []);
}

/* ---------- Précache des ressources statiques ---------- */

/**
 * URLs (racine de déploiement + version par mtime) des ressources statiques
 * publiques à précacher. Aucun fichier privé : uniquement `assets/` et la
 * page de secours hors ligne (plan §5.1).
 *
 * @return list<string>
 */
function pwa_precache_urls(): array
{
    static $urls = null;
    if ($urls !== null) {
        return $urls;
    }

    $root = APP_PUBLIC_PATH;
    $patterns = [
        '/assets/css/*.css',
        '/assets/js/*.js',
        '/assets/js/pwa/*.js',
        '/assets/images/*',
        '/assets/carousel/*.webp',
        '/assets/vendor/chartjs/*.js',
        '/assets/vendor/fontawesome/css/*.css',
        '/assets/vendor/fontawesome/webfonts/*.woff2',
        '/assets/vendor/fonts/*.css',
        '/assets/vendor/fonts/*.woff2',
    ];

    $files = [];
    foreach ($patterns as $pattern) {
        foreach (glob($root . $pattern) ?: [] as $file) {
            if (is_file($file)) {
                $files[] = $file;
            }
        }
    }

    // Garde-fous de quota (plan §16) : fichiers trop lourds écartés.
    $maxFileBytes = 3 * 1024 * 1024;
    $offlinePage = (string) (pwa_config()['offline_page'] ?? 'assets/offline.html');

    $urls = [];
    foreach ($files as $file) {
        if ((int) @filesize($file) > $maxFileBytes) {
            continue;
        }
        $relative = ltrim(str_replace('\\', '/', substr($file, strlen($root))), '/');
        if ($relative === '' || str_starts_with($relative, '.')) {
            continue; // jamais de fichier caché (.htaccess…)
        }
        $version = (int) @filemtime($file);
        $urls[] = pwa_url($relative) . '?v=' . $version;
    }

    if (is_file($root . '/' . $offlinePage)) {
        array_unshift($urls, pwa_url($offlinePage) . '?v=' . (int) @filemtime($root . '/' . $offlinePage));
    }
    foreach ((array) (pwa_config()['precache_extra'] ?? []) as $extra) {
        $urls[] = pwa_url((string) $extra);
    }

    return $urls = array_values(array_unique($urls));
}

/* ---------- Injection dans les layouts ---------- */

/**
 * Charge utile d'amorçage côté navigateur (aucune donnée sensible :
 * jamais de jeton CSRF, de cookie ou de mot de passe - plan §6.1).
 *
 * @return array<string,mixed>
 */
function pwa_boot_payload(): array
{
    $config = pwa_config();
    $user = function_exists('current_user') ? current_user() : null;
    $userId = $user ? (int) ($user['id'] ?? 0) : 0;

    return [
        'enabled'         => pwa_enabled(),
        'offlineCapture'  => pwa_offline_capture_enabled(),
        'serviceWorker'   => pwa_service_worker_enabled(),
        'basePath'        => app_base_path(),
        'indexUrl'        => pwa_url('index.php'),
        'swUrl'           => pwa_service_worker_enabled() ? pwa_url((string) ($config['sw_file'] ?? 'sw.js')) : '',
        'offlinePage'     => pwa_url((string) ($config['offline_page'] ?? 'assets/offline.html')),
        'cacheVersion'    => (string) ($config['cache_version'] ?? 'v1'),
        'manifestUrl'     => pwa_api_url('pwa/v1/manifest'),
        'endpoints'       => [
            'assets'        => pwa_api_url('pwa/v1/assets'),
            'bootstrap'     => pwa_api_url('pwa/v1/bootstrap'),
            'status'        => pwa_api_url('pwa/v1/status'),
            'sync'          => pwa_api_url('pwa/v1/sync'),
            'publicRegister' => pwa_api_url('pwa/v1/public/register'),
        ],
        'vault'           => [
            'mode'          => (string) ($config['vault']['mode'] ?? 'simple'),
            'iterations'    => (int) ($config['vault']['iterations'] ?? 210000),
            'retentionDays' => (int) ($config['vault']['retention_days'] ?? 7),
            'logoutPolicy'  => (string) ($config['vault']['logout_policy'] ?? 'ask'),
        ],
        'sync'            => [
            'maxBatch'      => (int) ($config['sync']['max_batch'] ?? 20),
            'maxAttempts'   => (int) ($config['sync']['max_attempts'] ?? 8),
            'retryBaseMs'   => (int) ($config['sync']['retry_base_ms'] ?? 4000),
            'retryMaxMs'    => (int) ($config['sync']['retry_max_ms'] ?? 900000),
        ],
        'operations'      => array_values(array_keys(array_filter(
            (array) ($config['operations'] ?? []),
            static fn($op): bool => is_array($op) && !empty($op['enabled'])
        ))),
        'user'            => $userId > 0 ? [
            'id'           => $userId,
            'accountScope' => 'u' . $userId,
            'name'         => trim((string) ($user['prenom'] ?? '') . ' ' . (string) ($user['nom'] ?? '')),
            'role'         => (string) ($user['role'] ?? ''),
        ] : null,
        'panelUrl'        => $userId > 0 ? pwa_url('index.php') . '?page=pwa_offline' : '',
    ];
}

/** `<script>` d'amorçage + manifest dynamique (à placer dans `<head>`). */
function pwa_head_tags(): string
{
    if (!pwa_enabled()) {
        return '';
    }

    $boot = json_encode(
        pwa_boot_payload(),
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    if ($boot === false) {
        return '';
    }

    $cssVersion = (int) @filemtime(APP_PUBLIC_PATH . '/assets/css/pwa.css');
    $tags = '<link rel="manifest" href="' . h(pwa_api_url('pwa/v1/manifest')) . '">'
        . '<link rel="stylesheet" href="' . h(pwa_url('assets/css/pwa.css')) . '?v=' . $cssVersion . '">'
        . '<script>window.LBEGF_PWA = ' . $boot . ';</script>';

    return $tags;
}

/** Scripts du client PWA (à placer avant `</body>`, ordre significatif). */
function pwa_foot_tags(): string
{
    if (!pwa_enabled()) {
        return '';
    }

    $modules = [
        'offline-db', 'crypto-vault', 'outbox', 'sync-client',
        'forms-adapter', 'network-status', 'offline-panel',
    ];
    if (!pwa_offline_capture_enabled()) {
        // Installation + vitrine hors ligne uniquement (Phase 1 du plan).
        $modules = ['network-status'];
    }

    $html = '';
    foreach ($modules as $module) {
        $file = APP_PUBLIC_PATH . '/assets/js/pwa/' . $module . '.js';
        if (!is_file($file)) {
            continue;
        }
        $html .= '<script src="' . h(pwa_url('assets/js/pwa/' . $module . '.js')) . '?v=' . (int) @filemtime($file) . '" defer></script>';
    }

    // register.js enregistre le Service Worker SI il est activé, et initialise
    // toujours le client de synchronisation (déclencheurs online/visibilité).
    $register = APP_PUBLIC_PATH . '/assets/js/pwa/register.js';
    if (is_file($register)) {
        $html .= '<script src="' . h(pwa_url('assets/js/pwa/register.js')) . '?v=' . (int) @filemtime($register) . '" defer></script>';
    }

    return $html;
}

/* ---------- Service et attributs de formulaires compatibles ---------- */

/** Service de synchronisation hors ligne (mémoïsé par requête). */
function pwa_sync_service(): \App\Services\PwaSyncService
{
    static $service = null;
    return $service = $service ?? new \App\Services\PwaSyncService();
}

/**
 * Attributs `data-pwa-*` à poser sur un formulaire EXPLICITEMENT compatible
 * hors ligne (plan §1 : aucun formulaire n'est mis en file par défaut).
 *
 * - `data-pwa-type` / `data-pwa-action` : opération allowlistée ;
 * - `data-pwa-base-fingerprint` : empreinte de l'état serveur au moment de la
 *   lecture (concurrence optimiste - jamais recalculée côté client) ;
 * - `data-pwa-base-snapshot` : valeurs lues, base de la fusion champ par champ
 *   et de l'affichage d'un conflit ;
 * - `data-pwa-entity-id` / `data-pwa-form-url` : contexte de reprise.
 *
 * Retourne une chaîne vide si la PWA ou l'opération est désactivée : le
 * formulaire retrouve alors exactement son comportement actuel.
 *
 * @param array<string,mixed> $snapshot
 * @param array<string,mixed> $extra
 */
function pwa_form_attrs(string $type, string $action, string $fingerprint = '', array $snapshot = [], array $extra = []): string
{
    if (!pwa_offline_capture_enabled() || !pwa_operation_allowed($type, $action)) {
        return '';
    }

    $attributes = array_merge([
        'data-pwa-type'             => $type,
        'data-pwa-action'           => $action,
        'data-pwa-schema-version'   => (string) (int) (pwa_operation_config($type)['schema_version'] ?? 1),
        'data-pwa-base-fingerprint' => $fingerprint,
        'data-pwa-base-snapshot'    => $snapshot === [] ? '' : (string) json_encode(
            $snapshot,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        ),
    ], $extra);

    $html = '';
    foreach ($attributes as $name => $value) {
        $html .= ' ' . h((string) $name) . '="' . h((string) $value) . '"';
    }
    return $html;
}
