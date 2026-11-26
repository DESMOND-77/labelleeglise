<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Logger;
use App\Repositories\PwaSyncRepository;
use App\Services\PwaSyncService;
use Throwable;

/**
 * Endpoints JSON de la PWA + écran « Données hors ligne ».
 * -------------------------------------------------------------
 * Plan : `docs/plans/pwa-offline-sync-implementation-plan.md` §7.1.
 *
 * Routes (déclarées dans Routes/web.php, dispatchées par le front controller
 * existant - aucun second point d'entrée) :
 *
 *   GET  index.php?api=pwa/v1/manifest         manifeste résolu (racine réelle)
 *   GET  index.php?api=pwa/v1/assets           contrat de précachage (Service Worker)
 *   GET  index.php?api=pwa/v1/bootstrap        session, capacités, jeton CSRF frais
 *   GET  index.php?api=pwa/v1/status           vérification de session + état serveur
 *   POST index.php?api=pwa/v1/sync             envoi d'un lot d'opérations privées
 *   POST index.php?api=pwa/v1/public/register  opération publique (inscription différée)
 *   GET  index.php?page=pwa_offline            écran de gestion des données locales
 *
 * Toutes les réponses sont `no-store` (plan §5.2) : aucune ne doit finir dans
 * un cache HTTP ni dans Cache Storage.
 */
class PwaSyncController extends Controller
{
    private PwaSyncService $sync;

    public function __construct(?PwaSyncService $sync = null)
    {
        $this->sync = $sync ?? new PwaSyncService();
    }

    /* ================================================================
     * ENDPOINTS PUBLICS (aucune session requise)
     * ================================================================ */

    /** GET api=pwa/v1/manifest - manifeste appliqué à la racine de déploiement. */
    public function manifest(): void
    {
        if (!pwa_enabled()) {
            $this->fail(404, 'pwa_disabled', 'La PWA est désactivée sur cette installation.');
        }
        $manifest = $this->sync->manifest();
        $this->respond($manifest, 200, 'application/manifest+json; charset=utf-8', 'no-cache, must-revalidate, private');
    }

    /** GET api=pwa/v1/assets - liste de précachage et pages publiques autorisées. */
    public function assets(): void
    {
        if (!pwa_enabled()) {
            $this->fail(404, 'pwa_disabled', 'La PWA est désactivée sur cette installation.');
        }
        $this->respond($this->sync->assets(), 200, 'application/json; charset=utf-8', 'no-cache, must-revalidate, private');
    }

    /**
     * GET api=pwa/v1/bootstrap - capacités + jeton CSRF FRAIS.
     * Le client ne met jamais un ancien jeton en file : il en redemande un
     * ici avant chaque envoi (plan §7.4).
     */
    public function bootstrap(): void
    {
        if (!pwa_offline_capture_enabled()) {
            $this->fail(404, 'offline_capture_disabled', 'La saisie hors ligne est désactivée sur cette installation.');
        }
        $this->respond($this->sync->bootstrap(current_user()), 200);
    }

    /** GET api=pwa/v1/status - session, identité et état serveur de la file. */
    public function status(): void
    {
        if (!pwa_offline_capture_enabled()) {
            $this->fail(404, 'offline_capture_disabled', 'La saisie hors ligne est désactivée sur cette installation.');
        }
        $this->respond($this->sync->status(current_user()), 200);
    }

    /* ================================================================
     * ENDPOINTS D'ÉCRITURE
     * ================================================================ */

    /** POST api=pwa/v1/sync - lot d'opérations privées (session obligatoire). */
    public function sync(): void
    {
        if (!pwa_offline_capture_enabled()) {
            $this->fail(404, 'offline_capture_disabled', 'La saisie hors ligne est désactivée sur cette installation.');
        }

        $body = $this->readJsonBody();
        if ($body === null) {
            $this->fail(400, 'invalid_json', 'Requête illisible.');
        }

        if (!$this->guardSameOrigin($body)) {
            $this->fail(403, 'origin_mismatch', 'Requête refusée : origine inattendue.');
        }
        if (!$this->guardCsrf($body)) {
            // 401 et non 400 : le client doit demander un jeton frais puis rejouer.
            $this->fail(401, 'csrf_invalid', 'Jeton de sécurité expiré. Redemandez un jeton puis renvoyez la saisie.');
        }

        $user = current_user();
        if ($user === null) {
            $this->respond([
                'ok'      => false,
                'error'   => 'session_required',
                'message' => 'Connexion requise pour envoyer ces saisies. Vos brouillons sont conservés sur cet appareil.',
                'results' => [],
            ], 401);
        }

        $operations = $body['operations'] ?? [];
        if (!is_array($operations)) {
            $this->fail(400, 'invalid_batch', 'Lot d’opérations invalide.');
        }

        $result = $this->runBatch($user, $operations, $this->clientIp());
        $code = empty($result['ok']) ? 422 : 200;
        $this->respond($result, $code);
    }

    /**
     * POST api=pwa/v1/public/register - opération PUBLIQUE différée.
     * Route et schéma séparés des opérations privées : aucune donnée publique
     * ne peut déclencher une opération administrative (plan §7.4).
     */
    public function publicRegister(): void
    {
        if (!pwa_offline_capture_enabled()) {
            $this->fail(404, 'offline_capture_disabled', 'La saisie hors ligne est désactivée sur cette installation.');
        }
        if (!pwa_operation_allowed('public_register', 'submit')) {
            $this->fail(404, 'operation_disabled', 'L’inscription différée n’est pas activée sur cette installation.');
        }

        $body = $this->readJsonBody();
        if ($body === null) {
            $this->fail(400, 'invalid_json', 'Requête illisible.');
        }
        if (!$this->guardSameOrigin($body)) {
            $this->fail(403, 'origin_mismatch', 'Requête refusée : origine inattendue.');
        }
        if (!$this->guardCsrf($body)) {
            $this->fail(401, 'csrf_invalid', 'Jeton de sécurité expiré. Redemandez un jeton puis renvoyez la demande.');
        }

        $operation = $body['operation'] ?? null;
        if (!is_array($operation)) {
            $this->fail(400, 'invalid_operation', 'Opération invalide.');
        }

        // Un visiteur connecté n'a rien à faire ici : son inscription existe.
        if (current_user() !== null) {
            $this->respond([
                'ok'      => true,
                'results' => [[
                    'operation_id' => (string) ($operation['operation_id'] ?? ''),
                    'status'       => PwaSyncService::STATUS_REJECTED,
                    'code'         => 'already_authenticated',
                    'message'      => 'Vous êtes déjà connecté : cette demande n’est plus nécessaire.',
                ]],
            ], 200);
        }

        $result = $this->sync->processOperation($operation, null, $this->clientIp());
        $status = (string) ($result['status'] ?? '');
        $code = in_array($status, [PwaSyncService::STATUS_ACCEPTED, PwaSyncService::STATUS_DUPLICATE], true) ? 200 : 422;
        if (($result['code'] ?? '') === 'rate_limited') {
            $code = 429;
        }
        $this->respond(['ok' => $code === 200, 'results' => [$result], 'server_time' => date('c')], $code);
    }

    /* ================================================================
     * ÉCRAN DE GESTION DES DONNÉES HORS LIGNE
     * ================================================================ */

    /** GET page=pwa_offline - écran « Données hors ligne » (session requise). */
    public function panel(): void
    {
        $user = current_user();
        if ($user === null) {
            $this->redirect('index.php');
        }

        $isAdmin = ((string) ($user['role'] ?? '')) === 'admin';
        $config = pwa_config();
        $repo = new PwaSyncRepository();

        $purged = null;
        $purgeError = null;
        if ($isAdmin && isset($_GET['purge_journal'])) {
            // Action d'administration : jeton CSRF vérifié, effet borné
            // (journal plus ancien que la durée de conservation).
            if (!hash_equals(Csrf::token(), (string) ($_GET['csrf'] ?? ''))) {
                $purgeError = 'Jeton de sécurité invalide : purge non effectuée.';
            } else {
                try {
                    $purged = $repo->purgeOlderThan(30);
                } catch (Throwable $e) {
                    Logger::error('PWA : purge du journal échouée', ['error' => $e->getMessage()]);
                    $purgeError = 'Purge impossible : ' . $e->getMessage();
                }
            }
        }

        $summary = null;
        try {
            $summary = $repo->summaryForUser((int) $user['id']);
        } catch (Throwable $e) {
            Logger::warning('PWA : synthèse serveur indisponible', ['error' => $e->getMessage()]);
        }

        $allowed = [];
        foreach ((array) ($config['operations'] ?? []) as $type => $operation) {
            if (!is_array($operation) || empty($operation['enabled'])) {
                continue;
            }
            $allowed[] = [
                'type'    => (string) $type,
                'actions' => implode(', ', (array) ($operation['actions'] ?? [])),
                'public'  => !empty($operation['public']),
            ];
        }

        render_page('Données hors ligne', view('pages/pwa_offline', [
            'user'            => $user,
            'isAdmin'         => $isAdmin,
            'pwaEnabled'      => pwa_enabled(),
            'offlineCapture'  => pwa_offline_capture_enabled(),
            'serviceWorker'   => pwa_service_worker_enabled(),
            'vaultMode'       => (string) ($config['vault']['mode'] ?? 'simple'),
            'retentionDays'   => (int) ($config['vault']['retention_days'] ?? 7),
            'logoutPolicy'    => (string) ($config['vault']['logout_policy'] ?? 'ask'),
            'allowedOps'      => $allowed,
            'excludedOps'     => array_values((array) ($config['excluded_operations'] ?? [])),
            'serverSummary'   => $summary,
            'purged'          => $purged,
            'purgeError'      => $purgeError,
            'purgeUrl'        => url('index.php', ['page' => 'pwa_offline', 'purge_journal' => 1, 'csrf' => csrf_token()]),
            'capabilities'    => $this->sync->capabilities($user),
            'csrf'            => csrf_field(),
        ]));
    }

    /* ================================================================
     * GARDES ET UTILITAIRES
     * ================================================================ */

    /**
     * @param array<string,mixed>|null $user
     * @param array<int,mixed> $operations
     * @return array<string,mixed>
     */
    private function runBatch(?array $user, array $operations, string $ip): array
    {
        try {
            return $this->sync->processBatch($user, $operations, $ip);
        } catch (Throwable $e) {
            Logger::error('PWA : échec du lot de synchronisation', ['error' => $e->getMessage()]);
            return [
                'ok'       => false,
                'error'    => 'internal_error',
                'message'  => 'Le serveur n’a pas pu traiter ce lot. Vos brouillons sont conservés.',
                'results'  => [],
                'retryable' => true,
            ];
        }
    }

    /** Corps JSON borné (jamais plus que la limite configurée + marge). */
    private function readJsonBody(): ?array
    {
        $limit = max(1024, (int) (pwa_config()['sync']['max_payload_bytes'] ?? 65536));
        $maxBatch = max(1, (int) (pwa_config()['sync']['max_batch'] ?? 20));
        $maxBytes = ($limit * $maxBatch) + 65536;

        $handle = fopen('php://input', 'rb');
        if ($handle === false) {
            return null;
        }
        $raw = stream_get_contents($handle, $maxBytes + 1);
        fclose($handle);

        if ($raw === false || $raw === '' || strlen($raw) > $maxBytes) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Vérification origine/méthode (plan §7.4) : pas de CORS ouvert, un POST
     * cross-site est refusé même avec un jeton volé.
     *
     * @param array<string,mixed> $body
     */
    private function guardSameOrigin(array $body): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return false;
        }

        $site = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
        if ($site !== '' && !in_array($site, ['same-origin', 'same-site', 'none'], true)) {
            return false;
        }

        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin !== '') {
            $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
            $expected = (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') ? 'http://' : 'https://';
            if (!hash_equals(rtrim($expected . $host, '/'), rtrim($origin, '/'))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Jeton CSRF frais : accepté depuis l'en-tête `X-CSRF-Token` ou le corps
     * JSON. Jamais lu depuis une file d'attente hors ligne (plan §6.1).
     *
     * @param array<string,mixed> $body
     */
    private function guardCsrf(array $body): bool
    {
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($token === '') {
            $token = (string) ($body['csrf'] ?? ($body['csrf_token'] ?? ''));
        }
        return $token !== '' && hash_equals(Csrf::token(), $token);
    }

    /** IP client (proxy inverse toléré) - jamais stockée en clair. */
    private function clientIp(): string
    {
        $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwarded !== '') {
            $parts = explode(',', $forwarded);
            $first = trim((string) $parts[0]);
            if ($first !== '') {
                return $first;
            }
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /**
     * Réponse JSON normalisée. `Response::json()` impose
     * `Content-Type: application/json` : on écrit donc directement ici pour
     * pouvoir servir le manifeste dans son type réel.
     *
     * @param array<string,mixed> $data
     */
    private function respond(array $data, int $code = 200, string $contentType = 'application/json; charset=utf-8', string $cacheControl = 'no-store, no-cache, must-revalidate, private'): never
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: ' . $contentType, true);
            header('Cache-Control: ' . $cacheControl, true);
            header('Pragma: no-cache', true);
            header('X-Content-Type-Options: nosniff', true);
            header('X-LBE-Cacheable: 0', true);
            header('Referrer-Policy: no-referrer', true);
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        echo $json === false ? '{"ok":false,"error":"encoding_error"}' : $json;
        exit;
    }

    /** Erreur JSON courte (jamais de détail SQL/chemin/jeton - plan §12). */
    private function fail(int $code, string $error, string $message): never
    {
        $this->respond(['ok' => false, 'error' => $error, 'message' => $message, 'results' => []], $code);
    }
}
