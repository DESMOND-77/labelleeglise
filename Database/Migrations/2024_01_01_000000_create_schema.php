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
    ];

    foreach ($schema as $sql) {
        $pdo->exec($sql);
    }
}

/**
 * Supprime toutes les tables (dans l'ordre FK-safe).
 */
function down(): void
{
    $pdo = Database::connection();
    $tables = ['users_basontas', 'presences', 'offrandes', 'visites', 'suivi_hebdo', 'dimes',
               'examens', 'veillees', 'cultes', 'basontas', 'bacentas', 'users',
               'centres_presentation', 'equipe', 'presentation', 'centres'];
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $t) {
        $pdo->exec("DROP TABLE IF EXISTS `$t`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}
