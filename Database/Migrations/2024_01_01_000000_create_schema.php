<?php

/**
 * Migration 001 — Schéma complet de la base « la_belle_eglise_db ».
 * -------------------------------------------------------------
 * Tables : centres, users, bacentas, basontas, users_basontas, cultes,
 * presences, offrandes, dimes, visites, suivi_hebdo, examens, veillees,
 * presentation, equipe, centres_presentation.
 *
 * NB : ces instructions sont volontairement idempotentes-friendly
 * (voir Database\Seeders pour la réinitialisation complète).
 */

declare(strict_types=1);

namespace Database\Migrations;

use App\Core\Database;

/**
 * Crée ou met à jour le schéma.
 */
function up(): void
{
    $pdo = Database::connection();

    /** @var array<int,string> $schema */
    $schema = [
        // ---- 1. STRUCTURE PRINCIPALE ----
        "CREATE TABLE IF NOT EXISTS centres (
            id INT NOT NULL AUTO_INCREMENT,
            nom VARCHAR(100) NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS users (
            id INT NOT NULL AUTO_INCREMENT,
            email VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','leader','assistant','pasteur','reverant','membre','responsable') DEFAULT 'membre',
            nom VARCHAR(50) NOT NULL,
            prenom VARCHAR(50) NOT NULL,
            date_naissance DATE NULL,
            telephone VARCHAR(20) NULL,
            quartier VARCHAR(100) NULL,
            bacenta_id INT NULL,
            photo_de_profil VARCHAR(255) NULL,
            compte_actif TINYINT(1) DEFAULT '0',
            invite_par INT NULL,
            recu_par INT NULL,
            date_recu DATE NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_email (email),
            KEY idx_bacenta (bacenta_id),
            CONSTRAINT fk_user_invite_par FOREIGN KEY (invite_par) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_user_recu_par   FOREIGN KEY (recu_par)   REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS bacentas (
            id INT NOT NULL AUTO_INCREMENT,
            nom VARCHAR(100) NOT NULL,
            responsable_id INT NULL,
            centre_id INT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            CONSTRAINT fk_bacenta_responsable FOREIGN KEY (responsable_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_bacenta_centre      FOREIGN KEY (centre_id)      REFERENCES centres(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS basontas (
            id INT NOT NULL AUTO_INCREMENT,
            nom VARCHAR(100) NOT NULL,
            responsable_id INT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            CONSTRAINT fk_basonta_responsable FOREIGN KEY (responsable_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS users_basontas (
            user_id INT NOT NULL,
            basonta_id INT NOT NULL,
            PRIMARY KEY (user_id, basonta_id),
            CONSTRAINT fk_ub_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
            CONSTRAINT fk_ub_basonta FOREIGN KEY (basonta_id) REFERENCES basontas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ---- 2. ACTIVITÉS & FINANCES ----
        "CREATE TABLE IF NOT EXISTS cultes (
            id INT NOT NULL AUTO_INCREMENT,
            nom VARCHAR(100) NOT NULL,
            date_culte DATE NULL,
            heure_debut TIME NULL,
            heure_fin TIME NULL,
            responsable_id INT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            CONSTRAINT fk_culte_responsable FOREIGN KEY (responsable_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS presences (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            date_presence DATE NOT NULL,
            culte_id INT NULL,
            bacenta_id INT NULL,
            basonta_id INT NULL,
            centre_id INT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            CONSTRAINT fk_pres_user    FOREIGN KEY (user_id)    REFERENCES users(id)      ON DELETE CASCADE,
            CONSTRAINT fk_pres_culte   FOREIGN KEY (culte_id)   REFERENCES cultes(id)     ON DELETE CASCADE,
            CONSTRAINT fk_pres_bacenta FOREIGN KEY (bacenta_id) REFERENCES bacentas(id)   ON DELETE CASCADE,
            CONSTRAINT fk_pres_basonta FOREIGN KEY (basonta_id) REFERENCES basontas(id)   ON DELETE CASCADE,
            CONSTRAINT fk_pres_centre  FOREIGN KEY (centre_id)  REFERENCES centres(id)    ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS offrandes (
            id INT NOT NULL AUTO_INCREMENT,
            centre_id INT NULL,
            bacenta_id INT NULL,
            montant DECIMAL(12,2) NOT NULL DEFAULT 0,
            date_offrande DATE NULL,
            mois CHAR(7) NULL,
            jour_index TINYINT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            CONSTRAINT fk_off_centre  FOREIGN KEY (centre_id)  REFERENCES centres(id)  ON DELETE CASCADE,
            CONSTRAINT fk_off_bacenta FOREIGN KEY (bacenta_id) REFERENCES bacentas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS dimes (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            annee SMALLINT NOT NULL,
            mois TINYINT NOT NULL,
            montant DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_dime (user_id, annee, mois),
            CONSTRAINT fk_dime_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ---- 3. SUIVI PASTORAL ----
        "CREATE TABLE IF NOT EXISTS visites (
            id INT NOT NULL AUTO_INCREMENT,
            visiteur_id INT NOT NULL,
            bacenta_id INT NULL,
            visite_user_id INT NULL,
            nom_visite VARCHAR(150) NULL,
            date_visite DATE NOT NULL,
            mois CHAR(7) NULL,
            semaine TINYINT NULL,
            observations TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            CONSTRAINT fk_vis_visiteur   FOREIGN KEY (visiteur_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_vis_bacenta    FOREIGN KEY (bacenta_id)  REFERENCES bacentas(id) ON DELETE CASCADE,
            CONSTRAINT fk_vis_user       FOREIGN KEY (visite_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS suivi_hebdo (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            semaine CHAR(8) NOT NULL,
            jour VARCHAR(10) NOT NULL,
            champ VARCHAR(40) NOT NULL,
            valeur VARCHAR(255) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_suivi (user_id, semaine, jour, champ),
            CONSTRAINT fk_suivi_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS examens (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            nom VARCHAR(150) NOT NULL,
            date_exam DATE NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_examen_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS veillees (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            date_veillee DATE NOT NULL,
            present TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            CONSTRAINT fk_veillee_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ---- 4. PRÉSENTATION CMS ----
        "CREATE TABLE IF NOT EXISTS presentation (
            id INT NOT NULL AUTO_INCREMENT,
            accroche TEXT NULL,
            histoire TEXT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS equipe (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NULL,
            nom_affichage VARCHAR(150) NOT NULL,
            role_affichage VARCHAR(80) NOT NULL,
            bio TEXT NULL,
            emoji VARCHAR(8) NULL,
            photo TEXT NULL,
            categorie VARCHAR(40) NOT NULL DEFAULT 'Autre',
            PRIMARY KEY (id),
            CONSTRAINT fk_equipe_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS centres_presentation (
            id INT NOT NULL AUTO_INCREMENT,
            centre_id INT NOT NULL,
            photo TEXT NULL,
            intro TEXT NULL,
            vision TEXT NULL,
            direction TEXT NULL,
            origine TEXT NULL,
            situation_annees INT NOT NULL DEFAULT 0,
            situation_pasteurs INT NOT NULL DEFAULT 0,
            situation_bergers INT NOT NULL DEFAULT 0,
            situation_leaders INT NOT NULL DEFAULT 0,
            situation_bacentas INT NOT NULL DEFAULT 0,
            objectifs TEXT NULL,
            PRIMARY KEY (id),
            CONSTRAINT fk_cp_centre FOREIGN KEY (centre_id) REFERENCES centres(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ---- 5. NOTIFICATIONS (centre de notifications plateforme) ----
        "CREATE TABLE IF NOT EXISTS notifications (
            id INT NOT NULL AUTO_INCREMENT,
            recipient_id INT NOT NULL,
            type VARCHAR(60) NOT NULL,
            title VARCHAR(150) NOT NULL,
            message TEXT NULL,
            link VARCHAR(255) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_notif_recipient (recipient_id, is_read),
            CONSTRAINT fk_notif_recipient FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ---- 6. RESPONSABILITÉS (ROLE ≠ RESPONSABILITÉ ≠ PÉRIMÈTRE) ----
        // Table polymorphe unique : user × responsibility_type × target_type
        // × target_id. `target_type` est un VARCHAR (pas un ENUM) pour que
        // de futurs types de cibles (département, province, activité,
        // événement, groupe — voir spec §49) n'exigent jamais de migration
        // de schéma. Pas de FK sur target_id (polymorphe) : l'existence de
        // la cible est validée au niveau service (ResponsibilityService).
        "CREATE TABLE IF NOT EXISTS responsibilities (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            responsibility_type VARCHAR(30) NOT NULL DEFAULT 'manager',
            target_type VARCHAR(30) NOT NULL,
            target_id INT NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_responsibility (user_id, responsibility_type, target_type, target_id),
            KEY idx_resp_target (target_type, target_id),
            CONSTRAINT fk_resp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($schema as $sql) {
        $pdo->exec($sql);
    }

    /* ---- 6. Inscription publique / vérification email / statut de compte ----
     * Colonnes ajoutées à `users` de façon idempotente (ALTER TABLE ne supporte
     * pas IF NOT EXISTS de façon fiable en MySQL/MariaDB < 8/10.x) : on vérifie
     * leur existence via information_schema avant chaque ALTER.
     */
    $userColumns = [
        'email_verified'          => "ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER role",
        'email_verified_at'       => "ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER email_verified",
        'verification_token'      => "ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) NULL AFTER email_verified_at",
        'verification_expires_at' => "ALTER TABLE users ADD COLUMN verification_expires_at DATETIME NULL AFTER verification_token",
        'account_status'          => "ALTER TABLE users ADD COLUMN account_status ENUM('pending','active','disabled') NOT NULL DEFAULT 'pending' AFTER verification_expires_at",
    ];
    foreach ($userColumns as $column => $alterSql) {
        if (!column_exists($pdo, 'users', $column)) {
            $pdo->exec($alterSql);
        }
    }
    // Index utile pour retrouver un token de vérification rapidement.
    if (!index_exists($pdo, 'users', 'idx_verification_token')) {
        $pdo->exec('CREATE INDEX idx_verification_token ON users (verification_token)');
    }

    /*
     * Compatibilité ascendante : les comptes déjà actifs avant l'introduction
     * de `account_status` / `email_verified` (compte_actif = 1) sont
     * automatiquement considérés comme vérifiés + actifs, pour ne jamais
     * bloquer les connexions existantes (comptes de démonstration compris).
     * Requête idempotente : ne touche que les lignes encore à 'pending'.
     */
    $pdo->exec(
        "UPDATE users
            SET account_status = 'active',
                email_verified = 1,
                email_verified_at = COALESCE(email_verified_at, created_at, NOW())
          WHERE compte_actif = 1
            AND account_status = 'pending'"
    );

    /* ---- 7. Remaniement rôles / responsabilités ----
     * ROLE ≠ RESPONSABILITÉ ≠ PÉRIMÈTRE (voir prompts/REMANIEMENT…md).
     * 1) Étendre l'ENUM users.role avec 'berger' et 'ms' (idempotent :
     *    on ne réémet le MODIFY que si 'berger' est absent du type actuel).
     *    'responsable' est CONSERVÉ dans l'ENUM pour rollback/sécurité
     *    (une suppression de valeur ENUM est destructrice si une ligne y
     *    fait encore référence) mais n'est plus un rôle actif.
     * 2) Migrer les données : role='responsable' → role='berger' (ancien
     *    rôle = "éligible à être désigné responsable", ce que berger +
     *    responsibilities représente désormais) ; responsable_id existants
     *    (bacentas/basontas/cultes) → lignes `responsibilities`.
     */
    $roleColumnType = (string) $pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'role'"
    )->fetchColumn();
    if (strpos($roleColumnType, "'berger'") === false) {
        $pdo->exec(
            "ALTER TABLE users MODIFY COLUMN role
                ENUM('admin','leader','assistant','pasteur','reverant','membre','responsable','berger','ms')
                DEFAULT 'membre'"
        );
    }

    // Migration des comptes role='responsable' → role='berger'.
    $migratedRoleCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'responsable'")->fetchColumn();
    if ($migratedRoleCount > 0) {
        $pdo->exec("UPDATE users SET role = 'berger' WHERE role = 'responsable'");
    }

    // Migration des anciennes colonnes responsable_id → table `responsibilities`
    // (une ligne par affectation non nulle existante, idempotente via la
    // contrainte UNIQUE ci-dessus : INSERT IGNORE ne duplique jamais).
    $legacyResponsibleTargets = [
        'bacenta' => 'bacentas',
        'basonta' => 'basontas',
        'cult'    => 'cultes',
    ];
    $migratedResponsibilityCounts = [];
    foreach ($legacyResponsibleTargets as $targetType => $table) {
        $rows = $pdo->query("SELECT id, responsable_id FROM `$table` WHERE responsable_id IS NOT NULL")->fetchAll(\PDO::FETCH_ASSOC);
        $count = 0;
        $ins = $pdo->prepare(
            "INSERT IGNORE INTO responsibilities (user_id, responsibility_type, target_type, target_id)
             VALUES (?, 'manager', ?, ?)"
        );
        foreach ($rows as $row) {
            // L'utilisateur référencé doit exister (FK) ; ON DELETE SET NULL
            // sur responsable_id garantit qu'un id orphelin n'est jamais présent.
            $ins->execute([(int) $row['responsable_id'], $targetType, (int) $row['id']]);
            $count += $ins->rowCount();
        }
        $migratedResponsibilityCounts[$targetType] = $count;
    }

    if (class_exists('\\App\\Core\\Logger')) {
        \App\Core\Logger::info('Migration rôles/responsabilités', [
            'users_responsable_to_berger' => $migratedRoleCount,
            'responsibilities_migrated'   => $migratedResponsibilityCounts,
        ]);
    }

    /* ---- 8. Profil utilisateur / changement d'email sécurisé / dernière connexion ----
     * Colonnes ajoutées de façon idempotente (voir bloc 6 ci-dessus pour la
     * même convention). `pending_email`/`email_change_token`/
     * `email_change_expires_at` sont DÉDIÉES au flux de changement d'email
     * (jamais partagées avec verification_token/verification_expires_at,
     * réservées à l'inscription publique). `email_change_token` stocke un
     * hash sha256 (jamais le jeton en clair), même principe que
     * verification_token.
     */
    $profileColumns = [
        'pending_email'           => "ALTER TABLE users ADD COLUMN pending_email VARCHAR(100) NULL AFTER email",
        'email_change_token'      => "ALTER TABLE users ADD COLUMN email_change_token VARCHAR(255) NULL AFTER pending_email",
        'email_change_expires_at' => "ALTER TABLE users ADD COLUMN email_change_expires_at DATETIME NULL AFTER email_change_token",
        'last_login_at'           => "ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER account_status",
    ];
    foreach ($profileColumns as $column => $alterSql) {
        if (!column_exists($pdo, 'users', $column)) {
            $pdo->exec($alterSql);
        }
    }
    if (!index_exists($pdo, 'users', 'idx_email_change_token')) {
        $pdo->exec('CREATE INDEX idx_email_change_token ON users (email_change_token)');
    }
}

/** Vérifie si une colonne existe déjà (idempotence des ALTER TABLE). */
function column_exists(\PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Vérifie si un index existe déjà (idempotence des CREATE INDEX). */
function index_exists(\PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics
          WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
    );
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Supprime toutes les tables (dans l'ordre FK-safe).
 */
function down(): void
{
    $pdo = Database::connection();
    $tables = ['responsibilities', 'notifications', 'users_basontas', 'presences', 'offrandes', 'visites', 'suivi_hebdo', 'dimes',
               'examens', 'veillees', 'cultes', 'basontas', 'bacentas', 'users',
               'centres_presentation', 'equipe', 'presentation', 'centres'];
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $t) {
        $pdo->exec("DROP TABLE IF EXISTS `$t`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}
