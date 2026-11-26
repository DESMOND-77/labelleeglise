<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Query;
use App\Repositories\BergerRepository;
use App\Repositories\PwaSyncRepository;
use App\Repositories\UserRepository;
use PDOException;
use Throwable;

/**
 * Orchestration de la synchronisation hors ligne (PWA).
 * -------------------------------------------------------------
 * Plan : `docs/plans/pwa-offline-sync-implementation-plan.md` (§7, §8, §9).
 *
 * Principes appliqués ici, sans exception :
 *
 * 1. Le hors ligne n'est jamais un contournement de l'autorisation serveur :
 *    chaque opération est ré-authentifiée, ré-autorisée (rôle, responsabilité
 *    et périmètre relus en base), revalidée puis commitée - exactement comme
 *    le flux en ligne correspondant dans `ActionsController`.
 * 2. Aucune valeur fournie par le client (user_id, centre_id, bacenta_id,
 *    rôle, crédit) n'est jamais tenue pour une autorité.
 * 3. Une opération n'est appliquée qu'une fois : UUID + contrainte UNIQUE sur
 *    `pwa_sync_operations.operation_id` + commit groupé du résultat.
 * 4. Jamais d'écrasement silencieux : toute divergence avec l'état lu au
 *    moment de la saisie remonte un conflit explicite avec les valeurs
 *    serveur, et la résolution est un choix de l'utilisateur.
 * 5. Les types/actions non allowlistés (finances dédiées, présences de masse,
 *    suppressions, rôles, uploads…) sont refusés - voir Config/pwa.php.
 */
class PwaSyncService
{
    /** Version du protocole de synchronisation (refusée si différente). */
    public const PROTOCOL_VERSION = 1;

    /** Statuts renvoyés au client (plan §7.2). */
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_CONFLICT = 'conflict';
    public const STATUS_VALIDATION_ERROR = 'validation_error';
    public const STATUS_UNAUTHORIZED = 'unauthorized';
    public const STATUS_RETRYABLE_ERROR = 'retryable_error';
    public const STATUS_REJECTED = 'rejected';

    private PwaSyncRepository $repo;
    private RapportJourService $rapports;
    private BergerRepository $bergers;
    private UserRepository $users;
    private RegistrationService $registrations;

    public function __construct(
        ?PwaSyncRepository $repo = null,
        ?RapportJourService $rapports = null,
        ?BergerRepository $bergers = null,
        ?UserRepository $users = null,
        ?RegistrationService $registrations = null
    ) {
        $this->repo = $repo ?? new PwaSyncRepository();
        $this->rapports = $rapports ?? new RapportJourService();
        $this->bergers = $bergers ?? new BergerRepository();
        $this->users = $users ?? new UserRepository();
        $this->registrations = $registrations ?? new RegistrationService();
    }

    /* ================================================================
     * AMORÇAGE / CAPACITÉS
     * ================================================================ */

    /**
     * Capacités exposées au client : ce qui est synchronisable, pour qui,
     * dans quelle version de schéma. Le client n'en déduit AUCUNE autorité :
     * tout est recontrôlé au commit.
     *
     * @param array<string,mixed>|null $user
     * @return list<array<string,mixed>>
     */
    public function capabilities(?array $user): array
    {
        $capabilities = [];
        foreach ((array) (pwa_config()['operations'] ?? []) as $type => $config) {
            if (!is_array($config) || empty($config['enabled'])) {
                continue;
            }
            $isPublic = !empty($config['public']);
            $available = $isPublic || ($user !== null && $this->canUseOperation((string) $type, $user));

            $capabilities[] = [
                'type'            => (string) $type,
                'actions'         => array_values((array) ($config['actions'] ?? [])),
                'schema_version'  => (int) ($config['schema_version'] ?? 1),
                'public'          => $isPublic,
                'available'       => $available,
                'conflict_policy' => (string) ($config['conflict_policy'] ?? 'explicit'),
                'reason'          => $available ? null : ($user === null ? 'session_required' : 'permission_missing'),
            ];
        }
        return $capabilities;
    }

    /**
     * Réponse d'amorçage : jeton CSRF frais (jamais mis en file par le
     * client), identité, capacités, politique du coffre et limites.
     *
     * @param array<string,mixed>|null $user
     * @return array<string,mixed>
     */
    public function bootstrap(?array $user): array
    {
        $config = pwa_config();
        $vault = (array) ($config['vault'] ?? []);
        $sync = (array) ($config['sync'] ?? []);

        return [
            'ok'               => true,
            'protocol_version' => self::PROTOCOL_VERSION,
            'server_time'      => date('c'),
            'authenticated'    => $user !== null,
            'user'             => $this->publicUser($user),
            'csrf_token'       => Csrf::token(),
            'capabilities'     => $this->capabilities($user),
            'vault'            => [
                'mode'           => (string) ($vault['mode'] ?? 'simple'),
                'iterations'     => (int) ($vault['iterations'] ?? 210000),
                'retention_days' => (int) ($vault['retention_days'] ?? 7),
                'logout_policy'  => (string) ($vault['logout_policy'] ?? 'ask'),
            ],
            'limits'           => [
                'max_batch'         => (int) ($sync['max_batch'] ?? 20),
                'max_payload_bytes' => (int) ($sync['max_payload_bytes'] ?? 65536),
                'max_attempts'      => (int) ($sync['max_attempts'] ?? 8),
                'retry_base_ms'     => (int) ($sync['retry_base_ms'] ?? 4000),
                'retry_max_ms'      => (int) ($sync['retry_max_ms'] ?? 900000),
            ],
        ];
    }

    /**
     * Vérification de session + état serveur de la file de l'utilisateur
     * (réconciliation après une reprise, un changement de compte…).
     *
     * @param array<string,mixed>|null $user
     * @return array<string,mixed>
     */
    public function status(?array $user): array
    {
        $userId = $user === null ? 0 : (int) ($user['id'] ?? 0);
        $summary = null;
        if ($userId > 0) {
            try {
                $summary = $this->repo->summaryForUser($userId);
            } catch (Throwable $e) {
                Logger::warning('PWA : état de synchronisation indisponible', ['error' => $e->getMessage()]);
            }
        }

        return [
            'ok'               => true,
            'protocol_version' => self::PROTOCOL_VERSION,
            'server_time'      => date('c'),
            'authenticated'    => $user !== null,
            'user'             => $this->publicUser($user),
            'capabilities'     => $this->capabilities($user),
            'sync'             => $summary,
        ];
    }

    /**
     * Manifeste applicatif résolu pour la racine réelle de déploiement.
     * Le fichier statique `assets/images/site.webmanifest` reste la source
     * d'identité ; les URLs (start_url, scope, icônes) sont résolues ici pour
     * ne jamais supposer que le site est servi à `/` (plan §4.2).
     *
     * @return array<string,mixed>
     */
    public function manifest(): array
    {
        $config = pwa_config();
        $file = APP_PUBLIC_PATH . '/' . (string) ($config['manifest_file'] ?? 'assets/images/site.webmanifest');
        $raw = is_file($file) ? (string) file_get_contents($file) : '';
        $decoded = $raw === '' ? null : json_decode($raw, true);
        $manifest = is_array($decoded) ? $decoded : [];

        $base = app_base_path();
        $indexUrl = pwa_url('index.php');

        $manifest['id'] = $base;
        $manifest['start_url'] = $indexUrl;
        $manifest['scope'] = $base;
        $manifest['display'] = 'standalone';
        $manifest['display_override'] = ['standalone', 'minimal-ui'];
        // Ni orientation ni verrouillage portrait : l'application doit rester
        // utilisable en portrait, en paysage et sur desktop (plan §4.1).
        unset($manifest['orientation']);

        $icons = [];
        foreach ((array) ($manifest['icons'] ?? []) as $icon) {
            if (!is_array($icon) || empty($icon['src'])) {
                continue;
            }
            $src = (string) $icon['src'];
            if (preg_match('#^https?://#i', $src) === 1) {
                $icon['src'] = $src;
            } else {
                // Chemin relatif au manifeste statique (assets/images/) :
                // résolu depuis la racine web réelle.
                $icon['src'] = pwa_url('assets/images/' . ltrim(basename($src), '/'));
            }
            $icons[] = $icon;
        }
        $manifest['icons'] = $icons;

        return $manifest;
    }

    /**
     * Contrat de précachage servi au Service Worker : version, ressources
     * statiques publiques et pages publiques allowlistées. Aucune ressource
     * privée n'y figure (plan §5.1).
     *
     * @return array<string,mixed>
     */
    public function assets(): array
    {
        $config = pwa_config();
        return [
            'ok'             => true,
            'version'        => (string) ($config['cache_version'] ?? 'v1'),
            'generated_at'   => date('c'),
            'base_path'      => app_base_path(),
            'index_url'      => pwa_url('index.php'),
            'offline_page'   => pwa_url((string) ($config['offline_page'] ?? 'assets/offline.html')),
            'sw_file'        => pwa_url((string) ($config['sw_file'] ?? 'sw.js')),
            'precache'       => pwa_precache_urls(),
            'public_pages'   => array_values((array) ($config['public_cacheable_pages'] ?? [])),
            'enabled'        => pwa_service_worker_enabled(),
            'vault_mode'     => (string) ($config['vault']['mode'] ?? 'simple'),
            'capture_failed_posts' => pwa_offline_capture_enabled()
                                      && ($config['vault']['mode'] ?? 'simple') === 'simple',
        ];
    }

    /* ================================================================
     * SYNCHRONISATION D'UN LOT
     * ================================================================ */

    /**
     * Traite un lot d'opérations. Chaque opération reçoit un accusé
     * distinct : un lot partiellement traité est rejouable sans doublon
     * (plan §8).
     *
     * @param array<string,mixed>|null $user
     * @param array<int,mixed> $operations
     * @return array<string,mixed>
     */
    public function processBatch(?array $user, array $operations, string $ip): array
    {
        $config = (array) (pwa_config()['sync'] ?? []);
        $maxBatch = max(1, (int) ($config['max_batch'] ?? 20));

        if ($operations === []) {
            return [
                'ok'      => false,
                'error'   => 'empty_batch',
                'message' => 'Aucune opération à synchroniser.',
                'results' => [],
            ];
        }
        if (count($operations) > $maxBatch) {
            return [
                'ok'      => false,
                'error'   => 'batch_too_large',
                'message' => 'Lot trop volumineux : ' . count($operations) . ' opérations (maximum ' . $maxBatch . ').',
                'results' => [],
            ];
        }

        $batchId = self::uuid();
        $results = [];
        foreach (array_values($operations) as $operation) {
            $operation = is_array($operation) ? $operation : [];
            // Les opérations publiques (inscription) ont un endpoint et un
            // schéma séparés : elles sont refusées dans le lot privé.
            if (pwa_operation_config((string) ($operation['type'] ?? ''))['public'] ?? false) {
                $results[] = $this->result($operation, self::STATUS_REJECTED, 'public_endpoint_required', 'Cette saisie doit être envoyée via l’endpoint public.', self::uuid());
                continue;
            }
            $results[] = $this->processOperation($operation, $user, $ip, $batchId);
        }

        return [
            'ok'               => true,
            'protocol_version' => self::PROTOCOL_VERSION,
            'batch_id'         => $batchId,
            'server_time'      => date('c'),
            'results'          => $results,
        ];
    }

    /**
     * Traite une opération : validation structurelle, allowlist, session,
     * idempotence, puis exécution métier dans une transaction qui committe
     * ensemble la mutation et l'enregistrement du résultat (plan §7.3).
     *
     * @param array<string,mixed> $operation
     * @param array<string,mixed>|null $user
     * @return array<string,mixed>
     */
    public function processOperation(array $operation, ?array $user, string $ip, string $batchId = ''): array
    {
        $correlationId = self::uuid();
        $config = pwa_config();
        $syncConfig = (array) ($config['sync'] ?? []);

        /* ---- 1. Validation structurelle ---- */
        $operationId = self::normalizeUuid((string) ($operation['operation_id'] ?? ''), true);
        if ($operationId === null) {
            return $this->result($operation, self::STATUS_REJECTED, 'invalid_operation_id', 'Identifiant d’opération invalide (UUID attendu).', $correlationId);
        }

        $type = (string) ($operation['type'] ?? '');
        $action = (string) ($operation['action'] ?? '');
        $typeConfig = pwa_operation_config($type);
        if ($typeConfig === [] || empty($typeConfig['enabled'])) {
            return $this->result($operation, self::STATUS_REJECTED, 'operation_disabled', 'Ce type d’opération n’est pas autorisé hors connexion.', $correlationId);
        }
        if (!in_array($action, (array) ($typeConfig['actions'] ?? []), true)) {
            return $this->result($operation, self::STATUS_REJECTED, 'action_not_allowed', 'Cette action n’est pas autorisée hors connexion.', $correlationId);
        }
        if ((int) ($operation['schema_version'] ?? 0) !== (int) ($typeConfig['schema_version'] ?? 1)) {
            return $this->result($operation, self::STATUS_REJECTED, 'schema_version_mismatch', 'Version de schéma refusée : mettez à jour l’application puis renvoyez la saisie.', $correlationId);
        }

        $payload = $operation['payload'] ?? null;
        if (!is_array($payload)) {
            return $this->result($operation, self::STATUS_REJECTED, 'invalid_payload', 'Charge utile invalide.', $correlationId);
        }
        $maxBytes = max(1024, (int) ($syncConfig['max_payload_bytes'] ?? 65536));
        $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encodedPayload === false || strlen($encodedPayload) > $maxBytes) {
            return $this->result($operation, self::STATUS_REJECTED, 'payload_too_large', 'Saisie trop volumineuse pour la synchronisation (maximum ' . (int) floor($maxBytes / 1024) . ' Ko).', $correlationId);
        }

        /* ---- 2. Opérations publiques : route et schéma séparés ---- */
        if (!empty($typeConfig['public'])) {
            return $this->submitPublicOperation($operation, $operationId, $type, $action, $ip, $correlationId, $payload);
        }

        /* ---- 3. Session obligatoire pour toute opération privée ---- */
        if ($user === null) {
            return $this->result($operation, self::STATUS_UNAUTHORIZED, 'session_required', 'Connexion requise pour envoyer cette saisie.', $correlationId);
        }
        if (!$this->canUseOperation($type, $user)) {
            return $this->result($operation, self::STATUS_UNAUTHORIZED, 'permission_missing', 'Action refusée : vos droits ne permettent pas cette saisie.', $correlationId);
        }

        /* ---- 4. Idempotence ---- */
        $userId = (int) ($user['id'] ?? 0);
        $accountScope = 'u' . $userId;
        $payloadHash = pwa_payload_hash($payload);
        $resolution = (string) ($operation['resolution'] ?? '');
        $grace = max(5, (int) ($syncConfig['processing_grace_seconds'] ?? 90));

        try {
            $claim = $this->repo->claim(
                $operationId,
                $userId,
                $accountScope,
                $type,
                $action,
                (int) ($operation['schema_version'] ?? 1),
                $payloadHash,
                $correlationId,
                PwaSyncRepository::hashIp($ip),
                $resolution !== '',
                $grace
            );
        } catch (Throwable $e) {
            Logger::error('PWA : réclamation d’opération impossible', ['operation_id' => $operationId, 'error' => $e->getMessage()]);
            return $this->result($operation, self::STATUS_RETRYABLE_ERROR, 'storage_unavailable', 'Synchronisation temporairement indisponible. Vos brouillons sont conservés.', $correlationId);
        }

        if ($claim['state'] === 'replay') {
            // Même opération déjà acceptée : on rejoue l'accusé sans rien réécrire.
            $stored = $this->repo->storedAck((array) ($claim['row'] ?? []));
            return $this->result($operation, self::STATUS_DUPLICATE, (string) ($stored['code'] ?? 'already_applied'), (string) ($stored['message'] ?? 'Opération déjà enregistrée par le serveur.'), $correlationId, [
                'entity_type' => $stored['entity_type'] ?? null,
                'entity_id'   => isset($stored['entity_id']) ? (int) $stored['entity_id'] : null,
                'applied'     => 'duplicate',
                'duplicate'   => true,
            ]);
        }
        if ($claim['state'] === 'anomaly') {
            $this->safeRecord($operationId, self::STATUS_REJECTED, null, null, 'operation_anomaly', 'Identifiant déjà utilisé avec un contenu différent.', []);
            return $this->result($operation, self::STATUS_REJECTED, 'operation_anomaly', 'Anomalie : cet identifiant d’opération a déjà été utilisé pour une autre saisie.', $correlationId);
        }
        if ($claim['state'] === 'in_flight') {
            return $this->result($operation, self::STATUS_RETRYABLE_ERROR, 'operation_in_flight', 'Cette opération est déjà en cours de traitement. Réessayez dans quelques instants.', $correlationId);
        }

        /* ---- 5. Exécution métier + enregistrement du résultat, commités ensemble ---- */
        $operation['correlation_id'] = $correlationId;
        $actor = $user;
        try {
            $outcome = Query::transaction(function () use ($type, $operation, $actor, $operationId, $correlationId): array {
                $result = $type === 'rapport_jour'
                    ? $this->handleRapportJour($operation, $actor)
                    : $this->handleSuiviHebdo($operation, $actor);

                $this->repo->recordResult(
                    $operationId,
                    $this->storedStatus((string) $result['status']),
                    isset($result['entity_type']) ? (string) $result['entity_type'] : null,
                    isset($result['entity_id']) ? (int) $result['entity_id'] : null,
                    isset($result['code']) ? (string) $result['code'] : null,
                    (string) ($result['message'] ?? ''),
                    $this->storableAck($result)
                );

                return $result;
            });
        } catch (PDOException $e) {
            // La transaction est annulée : la ligne reste `processing` et sera
            // ré-exécutable après la fenêtre de grâce (handlers idempotents).
            Logger::error('PWA : erreur base de données pendant la synchronisation', [
                'operation_id' => $operationId, 'type' => $type, 'error' => $e->getMessage(),
            ]);
            return $this->result($operation, self::STATUS_RETRYABLE_ERROR, 'database_error', 'Erreur de base de données. Vos brouillons sont conservés, réessayez plus tard.', $correlationId);
        } catch (Throwable $e) {
            Logger::error('PWA : échec inattendu d’une opération', [
                'operation_id' => $operationId, 'type' => $type, 'error' => $e->getMessage(),
            ]);
            $this->safeRecord($operationId, self::STATUS_REJECTED, null, null, 'internal_error', 'Échec inattendu du traitement.', []);
            return $this->result($operation, self::STATUS_REJECTED, 'internal_error', 'Le serveur n’a pas pu traiter cette saisie. Contactez un administrateur en indiquant la référence ' . substr($correlationId, 0, 8) . '.', $correlationId);
        }

        $outcome['operation_id'] = $operationId;
        $outcome['correlation_id'] = $correlationId;
        $outcome['server_time'] = date('c');
        return $outcome;
    }

    /* ================================================================
     * HANDLERS MÉTIER (allowlist - plan §9.1)
     * ================================================================ */

    /**
     * Rapport du Jour : même service que le flux en ligne
     * (`RapportJourService::save()`), donc mêmes validations de période,
     * d'occurrence, de responsable, de crédit et mêmes effets (contribution
     * d'offrande, suivi d'occurrence, notification des administrateurs).
     *
     * @param array<string,mixed> $operation
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    private function handleRapportJour(array $operation, array $user): array
    {
        $correlationId = (string) ($operation['correlation_id'] ?? self::uuid());
        $payload = (array) ($operation['payload'] ?? []);
        $uid = (int) ($user['id'] ?? 0);
        $isAdmin = ((string) ($user['role'] ?? '')) === 'admin';

        $centreId = (int) ($payload['centre_id'] ?? 0);
        $date = trim((string) ($payload['date_rapport'] ?? ''));
        $scopeType = (string) ($payload['scope_type'] ?? 'centre');
        if (!in_array($scopeType, ['centre', 'bacenta'], true)) {
            $scopeType = ((int) ($payload['bacenta_id'] ?? 0) > 0) ? 'bacenta' : 'centre';
        }
        $bacentaId = (int) ($payload['bacenta_id'] ?? 0) ?: null;
        if ($scopeType === 'centre') {
            $bacentaId = null;
        }

        if ($centreId <= 0) {
            return $this->result($operation, self::STATUS_VALIDATION_ERROR, 'invalid_centre', 'Choisissez un centre.', $correlationId, ['errors' => ['centre_id' => 'Choisissez un centre.']]);
        }
        if (!$this->isValidDate($date)) {
            return $this->result($operation, self::STATUS_VALIDATION_ERROR, 'invalid_date', 'Date invalide (format attendu AAAA-MM-JJ).', $correlationId, ['errors' => ['date_rapport' => 'Date invalide (format attendu AAAA-MM-JJ).']]);
        }

        // Autorisation relue en base AU MOMENT DU COMMIT : une responsabilité
        // retirée entre la saisie hors ligne et l'envoi entraîne un refus.
        if (!auth_can_report_for_centre($centreId)) {
            return $this->result($operation, self::STATUS_UNAUTHORIZED, 'permission_revoked', 'Action refusée : vos droits ont changé depuis la saisie.', $correlationId);
        }

        $existing = $this->rapports->reportForCentreDate($centreId, $date, $scopeType, $bacentaId);
        $serverState = $existing === null ? [] : $this->rapportState($existing);
        $serverFingerprint = $existing === null ? '' : pwa_fingerprint('rapport_jour', $serverState);
        $baseFingerprint = (string) ($operation['base_fingerprint'] ?? '');
        $resolution = (string) ($operation['resolution'] ?? '');

        if ($existing !== null && !in_array($resolution, ['server', 'client', 'merge'], true)) {
            // Création annoncée alors qu'un rapport existe déjà, ou état modifié
            // depuis la lecture : conflit explicite, jamais d'écrasement.
            if ($baseFingerprint === '' || !hash_equals($baseFingerprint, $serverFingerprint)) {
                return $this->conflictResult($operation, $correlationId, 'rapport_jour', (int) $existing['id'], $serverState, $serverFingerprint, 'Un rapport a été enregistré ou modifié sur le serveur depuis votre saisie.');
            }
        }

        if ($existing !== null && $resolution === 'server') {
            return $this->result($operation, self::STATUS_ACCEPTED, 'resolved_server', 'Version du serveur conservée : votre saisie n’a pas été appliquée.', $correlationId, [
                'entity_type' => 'rapport_jour',
                'entity_id'   => (int) $existing['id'],
                'applied'     => 'server',
            ]);
        }

        $input = $payload;
        if ($existing !== null && $resolution === 'merge') {
            $snapshot = is_array($operation['base_snapshot'] ?? null) ? (array) $operation['base_snapshot'] : [];
            $input = $this->mergeFields($snapshot, $payload, $serverState, array_map(static fn(array $f): string => (string) $f['key'], RAPPORT_JOUR_FIELDS));
        }

        // Champs de structure imposés par le serveur (jamais ceux du client
        // pour l'auteur) : le service réutilise ses propres règles.
        $input['centre_id'] = $centreId;
        $input['date_rapport'] = $date;
        $input['scope_type'] = $scopeType;
        $input['bacenta_id'] = $bacentaId ?? 0;

        $saved = $this->rapports->save($input, $uid, $isAdmin);

        if (empty($saved['ok'])) {
            $errors = (array) ($saved['errors'] ?? []);
            $isAuthorshipIssue = isset($errors['_form']) && count($errors) === 1;
            return $this->result(
                $operation,
                $isAuthorshipIssue ? self::STATUS_UNAUTHORIZED : self::STATUS_VALIDATION_ERROR,
                $isAuthorshipIssue ? 'not_author' : 'validation_failed',
                $isAuthorshipIssue ? (string) $errors['_form'] : 'La saisie a été refusée par le serveur : corrigez les champs signalés.',
                $correlationId,
                ['errors' => $errors]
            );
        }

        return $this->result($operation, self::STATUS_ACCEPTED, 'saved', 'Rapport enregistré par le serveur.', $correlationId, [
            'entity_type' => 'rapport_jour',
            'entity_id'   => isset($saved['id']) ? (int) $saved['id'] : null,
            'applied'     => $resolution === 'merge' ? 'merge' : 'client',
        ]);
    }

    /**
     * Suivi hebdomadaire personnel : propriétaire (ou admin) recontrôlé au
     * commit, jours/champs allowlistés, grille complète (aucun effacement
     * involontaire), résolution de conflit explicite.
     *
     * @param array<string,mixed> $operation
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    private function handleSuiviHebdo(array $operation, array $user): array
    {
        $correlationId = (string) ($operation['correlation_id'] ?? self::uuid());
        $payload = (array) ($operation['payload'] ?? []);
        $uid = (int) ($user['id'] ?? 0);
        $isAdmin = ((string) ($user['role'] ?? '')) === 'admin';

        $memberId = (int) ($payload['membre'] ?? 0);
        $weekKey = trim((string) ($payload['semaine'] ?? ''));

        if ($memberId <= 0) {
            return $this->result($operation, self::STATUS_VALIDATION_ERROR, 'invalid_member', 'Fiche de suivi invalide.', $correlationId, ['errors' => ['membre' => 'Fiche de suivi invalide.']]);
        }
        if (preg_match('/^\d{4}-W\d{2}$/', $weekKey) !== 1) {
            return $this->result($operation, self::STATUS_VALIDATION_ERROR, 'invalid_week', 'Semaine invalide (format attendu AAAA-Wnn).', $correlationId, ['errors' => ['semaine' => 'Semaine invalide (format attendu AAAA-Wnn).']]);
        }
        // Même règle que ActionsController::postAction('save_suivi') : seul le
        // propriétaire ou un administrateur écrit la fiche.
        if (!$isAdmin && $memberId !== $uid) {
            return $this->result($operation, self::STATUS_UNAUTHORIZED, 'permission_revoked', 'Action refusée : cette fiche de suivi ne vous appartient pas.', $correlationId);
        }

        $target = $this->users->find($memberId);
        if ($target === null) {
            return $this->result($operation, self::STATUS_VALIDATION_ERROR, 'member_not_found', 'Fiche introuvable.', $correlationId, ['errors' => ['membre' => 'Fiche introuvable.']]);
        }
        if (!in_array((string) ($target['role'] ?? ''), BERGER_ROLES, true)) {
            return $this->result($operation, self::STATUS_UNAUTHORIZED, 'not_eligible', 'Cette fiche ne dispose pas d’un suivi hebdomadaire.', $correlationId);
        }

        // Empreinte de base obligatoire : sans elle, aucune comparaison de
        // version n'est possible et un écrasement silencieux le serait.
        if (trim((string) ($operation['base_fingerprint'] ?? '')) === '') {
            return $this->result($operation, self::STATUS_VALIDATION_ERROR, 'missing_base_fingerprint', 'Rechargez la page de suivi puis ressaisissez : la référence de version est absente.', $correlationId);
        }

        $errors = [];
        $grid = $this->normalizeGrid($payload['suivi'] ?? [], $errors);
        if ($errors !== []) {
            return $this->result($operation, self::STATUS_VALIDATION_ERROR, 'invalid_grid', 'Grille de suivi invalide.', $correlationId, ['errors' => $errors]);
        }

        $serverGrid = $this->bergers->suiviWeek($memberId, $weekKey);
        $serverState = $this->suiviState($serverGrid);
        $serverFingerprint = $this->suiviFingerprint($memberId, $weekKey, $serverGrid);
        $baseFingerprint = (string) ($operation['base_fingerprint'] ?? '');
        $resolution = (string) ($operation['resolution'] ?? '');

        if ($baseFingerprint !== '' && !hash_equals($baseFingerprint, $serverFingerprint)
            && !in_array($resolution, ['server', 'client', 'merge'], true)) {
            return $this->conflictResult($operation, $correlationId, 'suivi_hebdo', $memberId, $serverState, $serverFingerprint, 'Cette semaine a été modifiée sur le serveur depuis votre saisie.', [
                'semaine' => $weekKey,
            ]);
        }

        if ($resolution === 'server') {
            return $this->result($operation, self::STATUS_ACCEPTED, 'resolved_server', 'Version du serveur conservée : votre saisie n’a pas été appliquée.', $correlationId, [
                'entity_type' => 'suivi_hebdo',
                'entity_id'   => $memberId,
                'entity_key'  => $memberId . '|' . $weekKey,
                'applied'     => 'server',
            ]);
        }

        $gridToSave = $grid;
        if ($resolution === 'merge') {
            $snapshot = is_array($operation['base_snapshot'] ?? null) ? (array) $operation['base_snapshot'] : [];
            $gridToSave = $this->mergeGrid($snapshot, $grid, $serverState);
        }

        save_suivi_week($memberId, $weekKey, $gridToSave);

        return $this->result($operation, self::STATUS_ACCEPTED, 'saved', 'Suivi hebdomadaire enregistré par le serveur.', $correlationId, [
            'entity_type' => 'suivi_hebdo',
            'entity_id'   => $memberId,
            'entity_key'  => $memberId . '|' . $weekKey,
            'applied'     => $resolution === 'merge' ? 'merge' : 'client',
        ]);
    }

    /**
     * Opération publique (demande d'inscription) : schéma et route séparés,
     * champ piège anti-robot, limitation de débit par empreinte d'IP, puis
     * validation et effets inchangés du flux en ligne (email de vérification,
     * statut `pending`, notification des administrateurs après vérification).
     *
     * @param array<string,mixed> $operation
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function submitPublicOperation(array $operation, string $operationId, string $type, string $action, string $ip, string $correlationId, array $payload): array
    {
        if ($type !== 'public_register' || $action !== 'submit') {
            return $this->result($operation, self::STATUS_REJECTED, 'unsupported_public_operation', 'Opération publique non prise en charge.', $correlationId);
        }

        // Champ piège : invisible pour un humain, jamais présenté à l'utilisateur.
        if (trim((string) ($payload['website'] ?? '')) !== '') {
            return $this->result($operation, self::STATUS_REJECTED, 'invalid_request', 'Demande incomplète.', $correlationId);
        }

        $ipHash = PwaSyncRepository::hashIp($ip);
        $limit = max(1, (int) (pwa_config()['sync']['public_rate_limit_per_hour'] ?? 5));
        try {
            $recent = $this->repo->publicAttemptsSince($ipHash, time() - 3600);
        } catch (Throwable $e) {
            Logger::error('PWA : contrôle de débit public impossible', ['error' => $e->getMessage()]);
            return $this->result($operation, self::STATUS_RETRYABLE_ERROR, 'storage_unavailable', 'Service temporairement indisponible. Votre brouillon est conservé.', $correlationId);
        }
        if ($recent >= $limit) {
            return $this->result($operation, self::STATUS_REJECTED, 'rate_limited', 'Trop de demandes envoyées depuis cet appareil. Réessayez plus tard.', $correlationId);
        }

        try {
            $claim = $this->repo->claim($operationId, null, 'public', $type, $action, (int) ($operation['schema_version'] ?? 1), pwa_payload_hash($payload), $correlationId, $ipHash, false, max(5, (int) (pwa_config()['sync']['processing_grace_seconds'] ?? 90)));
        } catch (Throwable $e) {
            Logger::error('PWA : réclamation d’opération publique impossible', ['operation_id' => $operationId, 'error' => $e->getMessage()]);
            return $this->result($operation, self::STATUS_RETRYABLE_ERROR, 'storage_unavailable', 'Service temporairement indisponible. Votre brouillon est conservé.', $correlationId);
        }

        if ($claim['state'] === 'replay') {
            $stored = $this->repo->storedAck((array) ($claim['row'] ?? []));
            return $this->result($operation, self::STATUS_DUPLICATE, 'already_applied', (string) ($stored['message'] ?? 'Demande déjà transmise.'), $correlationId, ['duplicate' => true]);
        }
        if ($claim['state'] === 'anomaly') {
            $this->safeRecord($operationId, self::STATUS_REJECTED, null, null, 'operation_anomaly', 'Identifiant déjà utilisé avec un contenu différent.', []);
            return $this->result($operation, self::STATUS_REJECTED, 'operation_anomaly', 'Anomalie : cet identifiant d’opération a déjà été utilisé.', $correlationId);
        }
        if ($claim['state'] === 'in_flight') {
            return $this->result($operation, self::STATUS_RETRYABLE_ERROR, 'operation_in_flight', 'Demande déjà en cours de traitement.', $correlationId);
        }

        $input = [
            'nom'              => (string) ($payload['nom'] ?? ''),
            'prenom'           => (string) ($payload['prenom'] ?? ''),
            'email'            => (string) ($payload['email'] ?? ''),
            'telephone'        => (string) ($payload['telephone'] ?? ''),
            'password'         => (string) ($payload['password'] ?? ''),
            'password_confirm' => (string) ($payload['password_confirm'] ?? ''),
        ];

        $errors = $this->registrations->validate($input);
        if ($errors !== []) {
            $message = 'La demande a été refusée par le serveur : corrigez les champs signalés.';
            $this->safeRecord($operationId, self::STATUS_VALIDATION_ERROR, null, null, 'validation_failed', $message, []);
            return $this->result($operation, self::STATUS_VALIDATION_ERROR, 'validation_failed', $message, $correlationId, ['errors' => $errors]);
        }

        /* Le mot de passe n'est JAMAIS renvoyé ni journalisé.
         * Pas de transaction englobante ici : RegistrationService enchaîne
         * création du compte, entrée « nouveaux membres » et envoi d'email.
         * L'idempotence est assurée en amont par la réclamation (UNIQUE
         * operation_id) et, en dernier recours, par l'unicité de l'email. */
        try {
            $created = $this->registrations->register($input);
        } catch (Throwable $e) {
            Logger::error('PWA : échec d’une inscription publique différée', ['operation_id' => $operationId, 'error' => $e->getMessage()]);
            $isDuplicateEmail = str_contains($e->getMessage(), 'Duplicate');
            $this->safeRecord($operationId, self::STATUS_REJECTED, null, null, $isDuplicateEmail ? 'duplicate_email' : 'internal_error', 'Inscription refusée.', []);
            return $this->result(
                $operation,
                $isDuplicateEmail ? self::STATUS_VALIDATION_ERROR : self::STATUS_REJECTED,
                $isDuplicateEmail ? 'duplicate_email' : 'internal_error',
                $isDuplicateEmail ? 'Une demande existe déjà pour cette adresse email.' : 'Le serveur n’a pas pu transmettre cette demande. Réessayez plus tard.',
                $correlationId,
                $isDuplicateEmail ? ['errors' => ['email' => 'Une demande existe déjà pour cette adresse email.']] : []
            );
        }

        $message = 'Demande transmise. Vérifiez votre boîte email pour confirmer votre adresse, puis attendez la validation d’un administrateur.';
        $entityId = isset($created['user_id']) ? (int) $created['user_id'] : null;
        $this->safeRecord($operationId, self::STATUS_ACCEPTED, 'user', $entityId, 'saved', $message, []);

        return $this->result($operation, self::STATUS_ACCEPTED, 'saved', $message, $correlationId, [
            'entity_type' => 'user',
            'entity_id'   => null, // identifiant interne non exposé à un visiteur
            'applied'     => 'client',
        ]);
    }

    /* ================================================================
     * EMPREINTES / ÉTATS SERVEUR (concurrence optimiste - plan §8)
     * ================================================================ */

    /**
     * État normalisé d'un rapport du jour : ce qui est comparé, affiché en
     * cas de conflit, et soumis à l'empreinte. Aucune donnée hors périmètre.
     *
     * @param array<string,mixed>|null $report
     * @return array<string,string>
     */
    public function rapportState(?array $report): array
    {
        if ($report === null) {
            return [];
        }
        $state = [];
        foreach (RAPPORT_JOUR_FIELDS as $field) {
            $key = (string) $field['key'];
            $raw = $report[$key] ?? null;
            $state[$key] = self::normalizeValue($raw, (string) ($field['type'] ?? 'text'));
        }
        $state['scope_type'] = (string) ($report['scope_type'] ?? '');
        $state['scope_id'] = (string) (int) ($report['scope_id'] ?? 0);
        $state['centre_id'] = (string) (int) ($report['centre_id'] ?? 0);
        $state['bacenta_id'] = (string) (int) ($report['bacenta_id'] ?? 0);
        $state['date_rapport'] = (string) ($report['date_rapport'] ?? '');
        $state['auteur_id'] = (string) (int) ($report['auteur_id'] ?? 0);
        $state['updated_at'] = (string) ($report['updated_at'] ?? '');
        return $state;
    }

    /** Empreinte d'un rapport du jour (chaîne vide si aucun rapport). */
    public function rapportFingerprint(?array $report): string
    {
        if ($report === null) {
            return '';
        }
        return pwa_fingerprint('rapport_jour', $this->rapportState($report));
    }

    /**
     * Grille de suivi hebdomadaire normalisée : toutes les paires
     * jour × champ, en chaînes - base de comparaison stable.
     *
     * @param array<string,array<string,mixed>> $grid
     * @return array<string,array<string,string>>
     */
    public function suiviState(array $grid): array
    {
        $fields = array_map(static fn(array $f): string => (string) $f['key'], SUIVI_FIELDS);
        $state = [];
        foreach (WEEK_DAYS as $day) {
            $row = [];
            foreach ($fields as $field) {
                $value = $grid[$day][$field] ?? '';
                $row[$field] = is_scalar($value) ? trim((string) $value) : '';
            }
            $state[$day] = $row;
        }
        return $state;
    }

    /** Empreinte d'une semaine de suivi (identique au rendu de la page). */
    public function suiviFingerprint(int $userId, string $weekKey, array $grid): string
    {
        return pwa_fingerprint('suivi_hebdo', [
            'user_id' => (string) $userId,
            'semaine' => $weekKey,
            'grid'    => $this->suiviState($grid),
        ]);
    }

    /* ================================================================
     * UTILITAIRES
     * ================================================================ */

    /** Le compte connecté peut-il utiliser ce type d'opération ? (capacité, pas autorité) */
    private function canUseOperation(string $type, array $user): bool
    {
        $role = (string) ($user['role'] ?? '');
        switch ($type) {
            case 'rapport_jour':
                return function_exists('auth_can_report_any') && auth_can_report_any();
            case 'suivi_hebdo':
                // Le suivi hebdomadaire concerne les bergers (et l'admin qui
                // peut écrire toute fiche) - même périmètre que la page.
                return $role === 'admin'
                    || (in_array($role, BERGER_ROLES, true) && (int) ($user['id'] ?? 0) > 0);
            default:
                return false;
        }
    }

    /**
     * Fusion champ par champ : ne réécrit que les champs réellement modifiés
     * par l'utilisateur depuis sa lecture ; tout le reste garde la valeur du
     * serveur. Utilisée uniquement quand les règles métier le permettent
     * (plan §8) et jamais pour écraser une valeur sensible en silence.
     *
     * @param array<string,mixed> $snapshot
     * @param array<string,mixed> $payload
     * @param array<string,string> $serverState
     * @param list<string> $keys
     * @return array<string,mixed>
     */
    private function mergeFields(array $snapshot, array $payload, array $serverState, array $keys): array
    {
        $merged = $payload;
        foreach ($keys as $key) {
            $mine = trim((string) ($payload[$key] ?? ''));
            $base = trim((string) ($snapshot[$key] ?? ''));
            if ($mine === $base) {
                $merged[$key] = $serverState[$key] ?? '';
            }
        }
        return $merged;
    }

    /**
     * Fusion d'une grille de suivi : cellules modifiées par l'utilisateur
     * conservées, cellules non modifiées reprises du serveur.
     *
     * @param array<string,array<string,mixed>> $snapshot
     * @param array<string,array<string,string>> $mine
     * @param array<string,array<string,string>> $serverState
     * @return array<string,array<string,string>>
     */
    private function mergeGrid(array $snapshot, array $mine, array $serverState): array
    {
        $merged = $serverState;
        foreach ($mine as $day => $fields) {
            foreach ((array) $fields as $field => $value) {
                $base = trim((string) ($snapshot[$day][$field] ?? ''));
                if (trim((string) $value) !== $base) {
                    $merged[$day][$field] = (string) $value;
                }
            }
        }
        return $merged;
    }

    /**
     * Normalise une grille envoyée par le client en matrice complète
     * (jours × champs allowlistés). Tout jour/champ inconnu est ignoré :
     * le dépôt n'écrit jamais une colonne inventée par le client.
     *
     * @param mixed $raw
     * @param array<string,string> $errors
     * @return array<string,array<string,string>>
     */
    private function normalizeGrid($raw, array &$errors): array
    {
        $fields = array_map(static fn(array $f): string => (string) $f['key'], SUIVI_FIELDS);
        $grid = [];
        foreach (WEEK_DAYS as $day) {
            $grid[$day] = array_fill_keys($fields, '');
        }

        if (!is_array($raw)) {
            $errors['suivi'] = 'Grille de suivi invalide.';
            return $grid;
        }
        if (count($raw) > count(WEEK_DAYS)) {
            $errors['suivi'] = 'Grille de suivi invalide (trop de jours).';
            return $grid;
        }

        foreach ($raw as $day => $cells) {
            if (!is_array($cells) || !in_array((string) $day, WEEK_DAYS, true)) {
                continue;
            }
            foreach ($cells as $field => $value) {
                if (!in_array((string) $field, $fields, true)) {
                    continue;
                }
                if (!is_scalar($value) && $value !== null) {
                    $errors['suivi'] = 'Valeur invalide dans la grille.';
                    continue;
                }
                $clean = trim((string) $value);
                if (mb_strlen($clean) > 255) {
                    $errors['suivi'] = 'Valeur trop longue (255 caractères maximum).';
                    continue;
                }
                $grid[(string) $day][(string) $field] = $clean;
            }
        }

        return $grid;
    }

    /** Normalisation d'une valeur métier pour comparaison/empreinte stable. */
    private static function normalizeValue($raw, string $type): string
    {
        switch ($type) {
            case 'int':
                return (string) (int) $raw;
            case 'decimal':
                $float = (float) str_replace([' ', ','], ['', '.'], trim((string) $raw));
                return number_format($float, 2, '.', '');
            default:
                return $raw === null ? '' : trim((string) $raw);
        }
    }

    private function isValidDate(string $date): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return false;
        }
        $time = strtotime($date);
        return $time !== false && date('Y-m-d', $time) === $date;
    }

    /**
     * Résultat normalisé d'une opération (plan §7.2). Le message est toujours
     * compréhensible et ne contient jamais de détail technique (SQL, chemin,
     * jeton) - plan §12.
     *
     * @param array<string,mixed> $operation
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function result(array $operation, string $status, ?string $code, string $message, string $correlationId, array $extra = []): array
    {
        return array_merge([
            'operation_id'   => (string) ($operation['operation_id'] ?? ''),
            'status'         => $status,
            'code'           => $code,
            'message'        => $message,
            'correlation_id' => $correlationId,
            'server_time'    => date('c'),
        ], $extra);
    }

    /** Résultat de conflit : valeurs serveur + empreinte courante. */
    private function conflictResult(array $operation, string $correlationId, string $entityType, int $entityId, array $serverState, string $serverFingerprint, string $message, array $extra = []): array
    {
        return $this->result($operation, self::STATUS_CONFLICT, 'version_conflict', $message, $correlationId, array_merge([
            'entity_type'        => $entityType,
            'entity_id'          => $entityId,
            'server_state'       => $serverState,
            'server_fingerprint' => $serverFingerprint,
            'resolutions'        => ['server', 'client', 'merge'],
        ], $extra));
    }

    /** Ce qui est conservé dans le journal (sans état serveur ni payload). */
    private function storableAck(array $result): array
    {
        $ack = [
            'status'      => (string) ($result['status'] ?? ''),
            'code'        => isset($result['code']) ? (string) $result['code'] : null,
            'message'     => (string) ($result['message'] ?? ''),
            'entity_type' => isset($result['entity_type']) ? (string) $result['entity_type'] : null,
            'entity_id'   => isset($result['entity_id']) ? (int) $result['entity_id'] : null,
        ];
        if (isset($result['entity_key'])) {
            $ack['entity_key'] = (string) $result['entity_key'];
        }
        if (isset($result['applied'])) {
            $ack['applied'] = (string) $result['applied'];
        }
        return $ack;
    }

    /** Statut persisté : `duplicate` n'existe pas en base (rejeu d'un `accepted`). */
    private function storedStatus(string $status): string
    {
        return $status === self::STATUS_DUPLICATE ? self::STATUS_ACCEPTED : $status;
    }

    /** Enregistrement de résultat hors transaction, tolérant aux pannes. */
    private function safeRecord(string $operationId, string $status, ?string $entityType, ?int $entityId, string $code, string $message, array $ack): void
    {
        try {
            $this->repo->recordResult($operationId, $status, $entityType, $entityId, $code, $message, $ack === [] ? ['status' => $status, 'code' => $code, 'message' => $message] : $ack);
        } catch (Throwable $e) {
            Logger::warning('PWA : résultat non journalisé', ['operation_id' => $operationId, 'error' => $e->getMessage()]);
        }
    }

    /** Identité exposée au client (jamais de donnée sensible). */
    private function publicUser(?array $user): ?array
    {
        if ($user === null) {
            return null;
        }
        $id = (int) ($user['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        return [
            'id'            => $id,
            'account_scope' => 'u' . $id,
            'name'          => trim((string) ($user['prenom'] ?? '') . ' ' . (string) ($user['nom'] ?? '')),
            'role'          => (string) ($user['role'] ?? ''),
        ];
    }

    /**
     * Normalise un UUID v4 ; renvoie null si invalide.
     *
     * $createIfEmpty : l'identifiant d'opération est généré côté client pour
     * garantir l'idempotence (réseau coupé après un commit serveur). S'il est
     * absent (client ancien, requête manuelle), le serveur en crée un : la
     * saisie reste enregistrée, mais un double renvoi ne sera pas détecté.
     * Les opérations publiques exigent un UUID client (anti-robot, débit).
     */
    public static function normalizeUuid(string $value, bool $createIfEmpty = false): ?string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value) === 1) {
            return $value;
        }
        return ($value === '' && $createIfEmpty) ? self::uuid() : null;
    }

    /** UUID v4 (identifiants de lot et de corrélation côté serveur). */
    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
