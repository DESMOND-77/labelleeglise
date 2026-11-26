<?php
/**
 * Configuration PWA / mode hors ligne.
 * -------------------------------------------------------------
 * Toute l'activation progressive et le rollback de la PWA passent par
 * ce fichier (plan §14, §18) : chaque bloc peut être désactivé sans
 * toucher au code applicatif.
 *
 * Principe non négociable (plan §1) : le mode hors ligne n'est jamais
 * un contournement de l'autorisation serveur. Rien n'est « enregistré »
 * tant que le serveur n'a pas authentifié, autorisé, validé et commité.
 *
 * Les valeurs sensibles à l'environnement sont surchargeables par `.env`
 * (voir `.env.example`, section « PWA / mode hors ligne »).
 */

declare(strict_types=1);

/**
 * Liste blanche des opérations métier synchronisables hors ligne.
 *
 * Chaque entrée est volontairement explicite (plan §1 : « la
 * synchronisation ne doit pas être une mise en file automatique de tous
 * les formulaires POST »). Une action absente d'ici reste utilisable en
 * ligne uniquement ; le client affiche alors un message clair.
 *
 * - `enabled`              : activé ou non (rollback unitaire).
 * - `actions`              : actions autorisées pour ce type.
 * - `schema_version`       : version du contrat de payload (refusée si différente).
 * - `sensitive_fields`     : champs à ne jamais journaliser côté serveur.
 * - `public`               : true = opération publique (aucune session), schéma
 *                            et route séparés, anti-spam + débit limité.
 * - `conflict_policy`      : 'explicit' = jamais d'écrasement silencieux.
 */
$pwaOperations = [
    // Rapport du Jour (plan §9.1) : réutilise RapportJourService::save(), donc
    // revalide période, occurrence, responsable et règles de crédit au commit.
    // Le champ `offrande` fait partie du rapport : un conflit sur un rapport
    // existant exige une résolution explicite (jamais d'écrasement silencieux).
    'rapport_jour' => [
        'enabled'              => env_bool('PWA_OP_RAPPORT_JOUR', true),
        'actions'              => ['save'],
        'schema_version'       => 1,
        'sensitive_fields'     => ['offrande', 'assistants'],
        'public'               => false,
        'conflict_policy'      => 'explicit',
    ],

    // Suivi hebdomadaire personnel (plan §9.1) : propriétaire ou admin,
    // vérifié au moment du commit (même règle que ActionsController).
    'suivi_hebdo' => [
        'enabled'              => env_bool('PWA_OP_SUIVI_HEBDO', true),
        'actions'              => ['save'],
        'schema_version'       => 1,
        'sensitive_fields'     => ['suivi'],
        'public'               => false,
        'conflict_policy'      => 'explicit',
    ],

    // Demande d'inscription publique (plan §9.1) : schéma et route séparés,
    // anti-spam + limitation de débit, vérification email inchangée.
    'public_register' => [
        'enabled'              => env_bool('PWA_OP_PUBLIC_REGISTER', true),
        'actions'              => ['submit'],
        'schema_version'       => 1,
        'sensitive_fields'     => ['password', 'password_confirm', 'telephone', 'email'],
        'public'               => true,
        'conflict_policy'      => 'explicit',
    ],
];

/**
 * Opérations explicitement EXCLUES du hors ligne (plan §9.2).
 * Documentées ici pour que l'exclusion soit un choix visible, pas un oubli :
 * finances/offrandes dédiées, Budget Bus, présences/pointages de masse,
 * suppressions, rôles/permissions, uploads, actions à effets externes
 * non idempotents (relances email, activations de compte…).
 */
$pwaExcludedOperations = [
    'save_visites_offrandes', 'save_offrandes_centre', 'save_bus_budget',
    'point_culte', 'save_presence_occurrence', 'save_nouveaux_membres_pointage',
    'save_nouveau_membre_presence', 'cancel_nouveau_membre_presence',
    'save_user', 'delete_user', 'assign_responsibility', 'revoke_responsibility',
    'admin_activate_account', 'admin_reject_account', 'change_password',
    'request_email_change', 'rapport_manual_relance', 'rapport_occurrence_cancel',
    'rapport_settings_save', 'rapport_rules_save', 'rapport_rule_delete',
    'save_album_photos', 'save_profile_photo', 'login', 'verify_access',
];

return [
    /* ---------- Activation générale ---------- */
    // `enabled` coupe toute la couche PWA (manifest dynamique, en-têtes,
    // scripts, endpoints) : l'application classique continue de fonctionner.
    'enabled'               => env_bool('PWA_ENABLED', true),
    // Saisie/brouillons hors ligne (indépendant de l'installation PWA).
    'offline_capture'       => env_bool('PWA_OFFLINE_CAPTURE', true),
    // Service Worker + caches statiques. Peut être coupé seul (ex. incident).
    'service_worker'        => env_bool('PWA_SERVICE_WORKER', true),

    /* ---------- Identité / chemins ---------- */
    'sw_file'               => 'sw.js',
    'manifest_file'         => 'assets/images/site.webmanifest',
    'offline_page'          => 'assets/offline.html',
    'cache_version'         => env_value('PWA_CACHE_VERSION', 'v1'),

    /* ---------- Vitrine publique hors ligne (plan §10) ---------- */
    // Pages publiques GET autorisées en cache. Une page n'est mise en cache
    // que si le serveur répond `X-LBE-Cacheable: 1` (anonyme + allowlistée).
    // Exclues volontairement : `login` (personnalisée/session),
    // `verify_email`/`verify_email_change` (jetons), toute page de gestion.
    'public_cacheable_pages' => ['eglise', 'albums', 'album', 'mentions_legales', 'confidentialite', 'register'],
    // Ressources statiques précachées (complétées dynamiquement par
    // l'endpoint `pwa/v1/assets` : CSS/JS/icônes réellement présents).
    'precache_extra'        => [],

    /* ---------- Coffre local (plan §6.2, décision §17.1) ---------- */
    'vault' => [
        // 'simple' : clé appareil non extractible (protège la lecture brute du
        //            stockage, PAS contre un XSS ni un appareil compromis).
        // 'pin'    : clé enveloppée par un code PIN/phrase choisi par
        //            l'utilisateur (recommandé pour les appareils partagés).
        'mode'              => in_array((string) env_value('PWA_VAULT_MODE', 'simple'), ['simple', 'pin'], true)
                                ? (string) env_value('PWA_VAULT_MODE', 'simple')
                                : 'simple',
        'iterations'        => max(120000, (int) env_value('PWA_VAULT_ITERATIONS', 210000)),
        // Conservation locale (plan §17.4) : durée courte, configurable.
        'retention_days'    => max(1, (int) env_value('PWA_RETENTION_DAYS', 7)),
        // Politique de déconnexion avec file non synchronisée (plan §17.2) :
        // 'ask' (défaut) | 'keep' | 'purge'.
        'logout_policy'     => in_array((string) env_value('PWA_LOGOUT_POLICY', 'ask'), ['ask', 'keep', 'purge'], true)
                                ? (string) env_value('PWA_LOGOUT_POLICY', 'ask')
                                : 'ask',
    ],

    /* ---------- Protocole de synchronisation (plan §7, §8) ---------- */
    'sync' => [
        'max_batch'             => max(1, (int) env_value('PWA_SYNC_MAX_BATCH', 20)),
        'max_payload_bytes'     => max(1024, (int) env_value('PWA_SYNC_MAX_PAYLOAD_BYTES', 65536)),
        'max_attempts'          => max(1, (int) env_value('PWA_SYNC_MAX_ATTEMPTS', 8)),
        // Fenêtre pendant laquelle une opération 'processing' est considérée
        // comme encore en cours (protection anti-concurrence, plan §7.3).
        'processing_grace_seconds' => 90,
        // Débit des opérations PUBLIQUES (anti-spam, plan §7.4).
        'public_rate_limit_per_hour' => max(1, (int) env_value('PWA_PUBLIC_RATE_LIMIT', 5)),
        // Backoff client : base/plafond en millisecondes.
        'retry_base_ms'         => 4000,
        'retry_max_ms'          => 900000,
    ],

    'operations'          => $pwaOperations,
    'excluded_operations' => $pwaExcludedOperations,
];
