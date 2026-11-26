<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Query;
use PDOException;

/**
 * Journal d'idempotence de la synchronisation hors ligne (PWA).
 * -------------------------------------------------------------
 * Persistance des opérations reçues du client hors ligne : une ligne par
 * `operation_id` (UUID généré côté client). C'est cette table - et sa
 * contrainte UNIQUE - qui garantit qu'une opération rejouée n'est jamais
 * appliquée deux fois (plan §7.3).
 *
 * Aucune donnée de formulaire n'y est stockée : uniquement l'empreinte du
 * payload, l'état final, l'entité produite et l'accusé de réception amputé
 * de l'état serveur (plan §12 : « journaliser les identifiants d'opération
 * et résultats, sans enregistrer le contenu sensible des formulaires »).
 */
class PwaSyncRepository
{
    /** États terminaux positifs (rejoués tels quels, jamais ré-exécutés). */
    public const FINAL_STATES = ['accepted', 'rejected'];

    /** États non terminaux : une nouvelle tentative est autorisée. */
    public const RETRYABLE_STATES = ['conflict', 'validation_error', 'unauthorized', 'retryable_error', 'processing'];

    private static bool $schemaChecked = false;

    /**
     * Garantit l'existence de la table sur une base déjà installée.
     *
     * `install.php` réinitialise entièrement la base (drop + seed) : il ne
     * peut donc PAS être relancé pour ajouter une table en production. La
     * création est idempotente (`CREATE TABLE IF NOT EXISTS`) et bornée à une
     * vérification par processus. Désactivable via `Config/pwa.php`.
     */
    public function ensureSchema(): void
    {
        if (self::$schemaChecked || PHP_SAPI === 'cli') {
            self::$schemaChecked = true;
            return;
        }
        self::$schemaChecked = true;

        $config = function_exists('pwa_config') ? pwa_config() : ['enabled' => true];
        if (empty($config['enabled'])) {
            return; // PWA désactivée par configuration : aucun DDL.
        }

        try {
            Database::connection()->exec(self::ddl());
        } catch (PDOException $e) {
            // Droits DDL absents sur l'hébergement : la table doit alors être
            // créée par la migration (Database/Migrations, bloc 21). On ne
            // casse pas la requête en cours, on journalise.
            Logger::warning('PWA : schéma de synchronisation non vérifiable', ['error' => $e->getMessage()]);
        }
    }

    /** DDL de la table (partagé avec Database/Migrations - bloc 21). */
    public static function ddl(): string
    {
        return "CREATE TABLE IF NOT EXISTS pwa_sync_operations (
            id INT NOT NULL AUTO_INCREMENT,
            operation_id CHAR(36) NOT NULL,
            user_id INT NULL,
            account_scope VARCHAR(32) NULL,
            op_type VARCHAR(40) NOT NULL,
            op_action VARCHAR(40) NOT NULL,
            schema_version SMALLINT NOT NULL DEFAULT 1,
            payload_hash CHAR(64) NOT NULL,
            entity_type VARCHAR(40) NULL,
            entity_id INT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'processing',
            result_code VARCHAR(40) NULL,
            result_message VARCHAR(255) NULL,
            ack_json MEDIUMTEXT NULL,
            attempts INT NOT NULL DEFAULT 1,
            correlation_id CHAR(36) NOT NULL,
            ip_hash CHAR(64) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_pwa_operation (operation_id),
            KEY idx_pwa_user_status (user_id, status),
            KEY idx_pwa_entity (entity_type, entity_id),
            KEY idx_pwa_public_rate (ip_hash, created_at),
            CONSTRAINT fk_pwa_sync_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    /** Retrouve une opération par son UUID. */
    public function find(string $operationId): ?array
    {
        return Query::one('SELECT * FROM pwa_sync_operations WHERE operation_id = ?', [$operationId]);
    }

    /**
     * Réserve (claim) une opération avant exécution.
     *
     * Retourne un état de réclamation :
     * - `new`      : première réception, ligne créée en `processing` ;
     * - `replay`   : opération déjà `accepted` → l'accusé stocké est rejoué ;
     * - `retry`    : échec non terminal précédent → nouvelle tentative ;
     * - `in_flight`: un traitement concurrent est en cours (fenêtre de grâce) ;
     * - `anomaly`  : même UUID avec un payload différent (plan §7.3).
     *
     * @return array{state:string,row:?array<string,mixed>}
     */
    public function claim(
        string $operationId,
        ?int $userId,
        string $accountScope,
        string $type,
        string $action,
        int $schemaVersion,
        string $payloadHash,
        string $correlationId,
        ?string $ipHash,
        bool $payloadMayChange = false,
        int $graceSeconds = 90
    ): array {
        $this->ensureSchema();

        try {
            Query::run(
                'INSERT INTO pwa_sync_operations
                    (operation_id, user_id, account_scope, op_type, op_action, schema_version,
                     payload_hash, status, attempts, correlation_id, ip_hash)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
                [$operationId, $userId, $accountScope, $type, $action, $schemaVersion,
                 $payloadHash, 'processing', $correlationId, $ipHash]
            );
            return ['state' => 'new', 'row' => null];
        } catch (PDOException $e) {
            // 23000 = violation de contrainte (operation_id déjà présent).
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
        }

        $row = $this->find($operationId);
        if ($row === null) {
            // Cas théorique (ligne supprimée entre-temps) : à retenter.
            return ['state' => 'retry', 'row' => null];
        }

        if (!hash_equals((string) $row['payload_hash'], $payloadHash) && !$payloadMayChange) {
            return ['state' => 'anomaly', 'row' => $row];
        }

        $status = (string) $row['status'];

        if ($status === 'accepted' || $status === 'rejected') {
            return ['state' => 'replay', 'row' => $row];
        }

        if ($status === 'processing') {
            $updatedAt = strtotime((string) ($row['updated_at'] ?? '')) ?: 0;
            if ($updatedAt > 0 && (time() - $updatedAt) < max(1, $graceSeconds)) {
                return ['state' => 'in_flight', 'row' => $row];
            }
        }

        Query::run(
            'UPDATE pwa_sync_operations
                SET status = ?, payload_hash = ?, schema_version = ?, correlation_id = ?,
                    user_id = COALESCE(?, user_id), account_scope = COALESCE(?, account_scope),
                    ip_hash = COALESCE(?, ip_hash), attempts = attempts + 1
              WHERE operation_id = ?',
            ['processing', $payloadHash, $schemaVersion, $correlationId, $userId, $accountScope, $ipHash, $operationId]
        );

        return ['state' => 'retry', 'row' => $row];
    }

    /** Inscrit le résultat (terminal ou non) d'une opération réclamée. */
    public function recordResult(
        string $operationId,
        string $status,
        ?string $entityType,
        ?int $entityId,
        ?string $code,
        ?string $message,
        array $ack
    ): void {
        $json = json_encode(
            $ack,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        Query::run(
            'UPDATE pwa_sync_operations
                SET status = ?, entity_type = ?, entity_id = ?, result_code = ?, result_message = ?, ack_json = ?
              WHERE operation_id = ?',
            [
                $status,
                $entityType,
                $entityId,
                $code === null ? null : mb_substr($code, 0, 40),
                $message === null ? null : mb_substr($message, 0, 255),
                $json === false ? null : $json,
                $operationId,
            ]
        );
    }

    /** Accusé de réception stocké (pour rejouer une opération déjà acceptée). */
    public function storedAck(array $row): array
    {
        $raw = (string) ($row['ack_json'] ?? '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Synthèse serveur des opérations d'un utilisateur (réconciliation).
     *
     * @return array{total:int,accepted:int,conflict:int,failed:int,last_synced_at:?string}
     */
    public function summaryForUser(int $userId): array
    {
        $this->ensureSchema();
        $rows = Query::all(
            'SELECT status, COUNT(*) AS n, MAX(updated_at) AS last_at
               FROM pwa_sync_operations
              WHERE user_id = ?
              GROUP BY status',
            [$userId]
        );

        $summary = ['total' => 0, 'accepted' => 0, 'conflict' => 0, 'failed' => 0, 'last_synced_at' => null];
        $lastAccepted = null;
        foreach ($rows as $row) {
            $count = (int) $row['n'];
            $summary['total'] += $count;
            $status = (string) $row['status'];
            if ($status === 'accepted') {
                $summary['accepted'] += $count;
                $lastAccepted = $row['last_at'];
            } elseif ($status === 'conflict') {
                $summary['conflict'] += $count;
            } elseif (in_array($status, ['validation_error', 'unauthorized', 'rejected'], true)) {
                $summary['failed'] += $count;
            }
        }
        $summary['last_synced_at'] = $lastAccepted === null ? null : (string) $lastAccepted;

        return $summary;
    }

    /** Nombre d'opérations publiques reçues d'une empreinte d'IP depuis un horodatage. */
    public function publicAttemptsSince(string $ipHash, int $sinceTimestamp): int
    {
        $this->ensureSchema();
        return (int) Query::value(
            'SELECT COUNT(*) FROM pwa_sync_operations
              WHERE user_id IS NULL AND ip_hash = ? AND created_at >= FROM_UNIXTIME(?)',
            [$ipHash, $sinceTimestamp]
        );
    }

    /** Purge du journal au-delà d'un âge donné (retour : nombre de lignes). */
    public function purgeOlderThan(int $days): int
    {
        $this->ensureSchema();
        $days = max(1, $days);
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "DELETE FROM pwa_sync_operations
              WHERE created_at < (NOW() - INTERVAL ? DAY)
                AND status <> 'processing'"
        );
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    /** Empreinte pseudonymisée d'une adresse IP (jamais l'IP en clair). */
    public static function hashIp(string $ip): string
    {
        return hash('sha256', trim($ip) . '|lbe-pwa');
    }
}
