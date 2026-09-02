# Spec maître — Intégration des 7 modules « La Belle Église »

- **Date** : 2026-09-01
- **Source** : `prompts/to-implement.md` (demande utilisateur + Q/R de cadrage)
- **Statut** : validé, prêt pour découpage en plans d'implémentation
- **Portée** : document maître. Chaque module fait ensuite l'objet de son propre
  plan d'implémentation détaillé (skill `writing-plans`).

---

## 1. Contexte et contrainte de conception

Application PHP SSR, micro-framework maison, **zéro dépendance** (pas de Composer,
npm, build, Docker). Déployable par simple copie de dossier. Couches strictes :
`Controller → Service → Repository → App\Core\Query/Database → View`. Routes via
`?page=xxx` (GET) / `action=xxx` (POST via `ActionsController::postAction`).
Migration = fichier unique idempotent
`Database/Migrations/2024_01_01_000000_create_schema.php` (`up()` non destructif en
prod, `down()` + `install.php` = destructif réservé au dev).

**Principe directeur de l'intégration** : *étendre sans casser*. Aucune URL, aucun
flux d'auth, aucun formulaire existant connu-fonctionnel n'est remplacé — on ajoute
à côté, comme l'a été la section « member-picker » de la page bacenta.

## 2. Vue d'ensemble des 7 modules

| # | Module | Nature | Nouvelles tables |
|---|--------|--------|------------------|
| M3 | Suivi Hebdo. des Bergers — champs prière / Mixlr / Ushers | Extension de constante | — |
| M1 | Présences par occurrence (Bacentas / Basontas / Cultes) + matrice annuelle | Extension schéma + moteur | — (colonnes ajoutées) |
| M4 | Calendrier événementiel + calendrier d'anniversaires | Nouveau | `evenements`, `anniversaires` |
| M5 | Rapport du Jour des responsables de bacenta | Nouveau | `rapports_jour` |
| M6 | Classes / Écoles post-culte + progression automatique | Nouveau | `classes`, `classe_inscrits` |
| M2 | Budget Bus du dimanche par centre | Nouveau | `bus_budget` |

## 3. Séquencement et dépendances

```
M3  →  M1  →  M4  →  (addendum M1 : pointage d'événement)  →  M5  →  M6  →  M2
```

- **M3** : totalement indépendant. Quick win (surtout `Config/constants.php`).
- **M1** : indépendant pour culte / bacenta / basonta. Fournit un moteur de
  présence réutilisable.
- **M4** : indépendant. **Après M4**, petit addendum à M1 : une occurrence
  d'`evenement` devient un rassemblement pointable (colonne `presences.evenement_id`).
- **M5** : indépendant (`centres` + `responsibilities` suffisent).
- **M6** : indépendant.
- **M2** : indépendant, le plus simple — peut être avancé si besoin d'un livrable rapide.

Chaque module est livrable et vérifiable isolément (branche + plan dédiés).

---

## 4. Détail par module

### M3 — Suivi Hebdo. des Bergers

**Besoin** : ajouter « Temps de prière », « Lien/Statut Mixlr », « Nombre d'Ushers »
au tableau de suivi hebdomadaire ; corriger l'orthographe « ashers » → « ushers ».

**Constat code** :
- Le stockage `suivi_hebdo` est déjà EAV générique `(user_id, semaine, jour, champ, valeur)`.
- Les colonnes du tableau sont pilotées par la constante `SUIVI_FIELDS`
  (`Config/constants.php:177`), consommée par `Views/pages/suivi_week.php`,
  `app/Compat/bergers.php`, `app/Compat/profile.php`, `app/Services/ReportService.php`
  (calcul du % de réalisation).
- « Temps de prière quotidien » existe déjà (`priere`).
- « ashers » : la seule occurrence réelle est `BASONTAS_DEFAULT` (`Config/constants.php:48`),
  `['Chorale', 'Ashers', 'Film Start', ...]` — nom de basonta (ministère « ushers »).

**Décisions** :
1. Ajouter à `SUIVI_FIELDS` :
   - `mixlr` — `type => 'text'`, label « Diffusion Mixlr (lien ou statut) »
   - `ushers` — `type => 'number'`, label « Nombre d'Ushers »
   - `themeSemaine` — `type => 'text'`, label « Thème de la semaine » (optionnel)
   - `nomBerger` — `type => 'text'`, label « Berger concerné » (optionnel)
2. `BASONTAS_DEFAULT` : `'Ashers'` → `'Ushers'`.
3. Migration : bloc idempotent de **correction de donnée** —
   `UPDATE basontas SET nom = 'Ushers' WHERE nom = 'Ashers'` (une seule fois ;
   inoffensif si aucune ligne). Sinon les installations existantes gardent la
   faute (le seed ne rejoue pas sur une base déjà peuplée).
4. Vérifier que `ReportService` (calcul du %) tolère les nouveaux champs — les
   champs optionnels ne doivent pas plomber le pourcentage (les compter comme
   les autres est acceptable ; à confirmer dans le plan M3).

**Schéma** : aucun changement.

**Fichiers touchés** : `Config/constants.php`, migration, éventuellement
`app/Services/ReportService.php`. Vues : aucune (pilotées par la constante).

---

### M1 — Présences par occurrence + matrice annuelle

**Besoin** : pointer la présence (Présent / Absent / Excusé) des membres à chaque
rassemblement de l'année, plus une vue de synthèse annuelle imprimable.

**Modèle de rassemblement** (Q/R de cadrage) :
- Un rassemblement = une **occurrence datée** d'une unité récurrente
  (`culte`, `bacenta`, `basonta`) ou (après M4) d'un `evenement`.
- À la **création / édition** d'un culte / bacenta / basonta, on définit son/ses
  **jour(s) de la semaine** + une **plage horaire facultative**, récurrents chaque
  semaine.
- Le pointage se fait **dans la page de l'unité** :
  `Bacentas > {unité} > {date}`, `Cultes > {unité} > {date}`,
  `Basontas > {unité} > {date}`.

**Qui pointe quoi** :
- Bacenta → membres du bacenta (`users.bacenta_id`)
- Basonta → membres du basonta (`users_basontas`)
- Culte → **l'ensemble des membres** (même population que le pointage culte actuel :
  `role IN ('membre','leader','assistant','pasteur','reverant')` — cf.
  `render_culte_detail` dans `app/Compat/sections.php`)

**Permissions** : périmètre RBAC existant — `can_manage_entity('bacenta'|'cult'|'basonta', $id)`
(admin + responsable réel de l'unité via `responsibilities`, avec héritage
centre → bacenta). Aucun élargissement.

**Schéma** (blocs idempotents, `column_exists` / `index_exists` déjà fournis) :

```
ALTER TABLE cultes   ADD COLUMN jours_semaine VARCHAR(60) NULL AFTER date_culte;
ALTER TABLE bacentas ADD COLUMN jours_semaine VARCHAR(60) NULL,
                     ADD COLUMN heure_debut  TIME NULL,
                     ADD COLUMN heure_fin    TIME NULL;
ALTER TABLE basontas ADD COLUMN jours_semaine VARCHAR(60) NULL,
                     ADD COLUMN heure_debut  TIME NULL,
                     ADD COLUMN heure_fin    TIME NULL;
ALTER TABLE presences ADD COLUMN statut ENUM('present','absent','excuse')
                     NOT NULL DEFAULT 'present' AFTER date_presence;
-- unicité d'une ligne de présence par (personne, date, unité)
CREATE UNIQUE INDEX uniq_presence
    ON presences (user_id, date_presence, culte_id, bacenta_id, basonta_id);
```

- `jours_semaine` : CSV de libellés de `WEEK_DAYS` (ex. `"Vendredi"`,
  `"Lundi,Mercredi"`). `cultes` a déjà `heure_debut` / `heure_fin`.
- L'unique key : une seule des colonnes `culte_id/bacenta_id/basonta_id` est
  non-NULL par ligne. En MySQL/MariaDB deux NULL ne collisionnent pas — c'est
  voulu. Le service ne s'appuie pas sur `INSERT ... ON DUPLICATE` : il fait un
  **upsert transactionnel** (`DELETE` des présences de l'(unité, date) puis
  `INSERT` de la nouvelle map) dans un `Query::transaction()`.

**Comportement** :
- Nouvel onglet `tab=presences` sur la page détail d'une unité : sélecteur de
  date (limité aux dates dont le jour de semaine ∈ `jours_semaine`, ou libre si
  `jours_semaine` vide), puis liste des membres avec un `<select>`
  Présent / Absent / Excusé chacun.
- Nouvel onglet `tab=presences_annuel` : **matrice** lignes = membres,
  colonnes = toutes les dates pointées de l'année en cours, lecture seule +
  bouton d'impression (réutiliser le patron `attendancePrint` /
  `Views/pages/attendance_print.php`). Compteurs / % en pied.
- La config de récurrence (`jours_semaine`, `heure_debut`, `heure_fin`) est
  ajoutée aux **formulaires existants** `Views/pages/forms/{bacenta,culte,equipe}.php`
  concernés et gérée par les actions existantes `save_bacenta` / `save_culte` /
  `save_basonta` — **pas de nouvelle action** pour la config.

**Routes / navigation** : aucune nouvelle route (`?page=bacentas&id=X&tab=presences&date=YYYY-MM-DD`
passe déjà par `SectionController::index` → `render_section_page`). Étendre le
dispatch `tab` dans `render_bacenta_detail` / `render_culte_detail` /
`render_basonta_detail` (`app/Compat/sections.php`).

**Actions POST** :
- `save_presence_occurrence` — champs : `unit_type` (`bacenta|cult|basonta`),
  `unit_id`, `date` (`Y-m-d`), `statut[user_id] => present|absent|excuse`.
  `check_csrf()` ; RBAC `can_manage_entity` ; revalidation serveur de chaque
  `user_id` (doit appartenir à l'unité) ; upsert transactionnel ; `redirect()`
  vers l'onglet.

**Nouveaux fichiers** :
- `app/Services/PresenceService.php` (moteur : occurrences valides, upsert, matrice annuelle)
- `app/Repositories/PresenceRepository.php` (SQL présences par unité/date/année)
- `Views/pages/presence_occurrence.php`, `Views/pages/presence_annuel.php`
- `assets/css/presences.css`

> Note : la table `presences` existe déjà et `AttendanceService` /
> `AttendanceRepository` aussi — le plan M1 devra décider entre **étendre**
> l'existant et **ajouter** un service dédié. Préférence : réutiliser
> `AttendanceRepository` si son périmètre s'y prête, sinon service dédié pour ne
> pas alourdir un fichier déjà chargé.

**« Nom de la personne visitée »** (Bacentas) : déjà couvert par la table
`visites` (`nom_visite`) et la fiche mensuelle `Views/pages/bacenta_suivi.php`.
Le plan M1 vérifie avec l'utilisateur si l'existant suffit ou s'il faut un champ
« personne visitée » par ligne de membre. **Hors périmètre par défaut.**

**« Prénom » (Basontas)** : `users.prenom` existe déjà. Le plan M1 ajoute la
**colonne d'affichage** « Prénom » dans le tableau des membres de basonta
(`members_table` / vue basonta) — pas de changement de schéma.

---

### M4 — Calendrier événementiel + calendrier d'anniversaires

**Besoin** :
- Événements : nom, date & heure début-fin, lieu, responsable. Exemples :
  kermesse, dimanche de délivrances, fête de fin d'année.
- Anniversaires : auto depuis `users.date_naissance` **+** saisie manuelle
  (personnes sans compte : révérend, pasteurs, MS). Âge calculé si année connue,
  surlignage du mois courant.

**Gestion / permissions** : via `responsibilities`.
- Nouveau helper `auth_can_manage_calendar()` = `is_admin()` OU l'utilisateur
  détient au moins une ligne `responsibilities` de type `manager` (ce qui
  couvre bergers / révérend / pasteurs / MS effectivement désignés).
- Édition / suppression d'un **événement** : admin OU `created_by` OU
  `responsable_id` de l'événement.
- Un `responsibilities` `target_type='evenement'` reste possible pour désigner un
  co-responsable, mais n'est pas requis pour la v1.

**Schéma** :

```
CREATE TABLE IF NOT EXISTS evenements (
    id INT NOT NULL AUTO_INCREMENT,
    nom VARCHAR(150) NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NULL,
    lieu VARCHAR(150) NULL,
    responsable_id INT NULL,
    created_by INT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_evt_debut (date_debut),
    CONSTRAINT fk_evt_resp    FOREIGN KEY (responsable_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_evt_creator FOREIGN KEY (created_by)     REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS anniversaires (
    id INT NOT NULL AUTO_INCREMENT,
    nom VARCHAR(150) NOT NULL,
    jour TINYINT NOT NULL,
    mois TINYINT NOT NULL,
    annee SMALLINT NULL,
    created_by INT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_anniv_mois (mois, jour),
    CONSTRAINT fk_anniv_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Addendum M1** (livré avec M4) :

```
ALTER TABLE presences ADD COLUMN evenement_id INT NULL AFTER basonta_id,
    ADD CONSTRAINT fk_pres_evt FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE;
DROP INDEX uniq_presence ON presences;
CREATE UNIQUE INDEX uniq_presence
    ON presences (user_id, date_presence, culte_id, bacenta_id, basonta_id, evenement_id);
```

`save_presence_occurrence` accepte alors `unit_type='evenement'` ; population
pointée = ensemble des membres (comme un culte). Pointage accessible depuis la
fiche événement.

**Routes / navigation** :
- `Router::get('calendrier', CalendrierController::class, 'evenements')`
- `Router::get('anniversaires', CalendrierController::class, 'anniversaires')`
- Entrées `SECTION_LABELS` / `SECTION_ICONS` / `NAV_ORDER` :
  `calendrier => 'Calendrier événementiel'`, `anniversaires => 'Anniversaires'`.

**Vues** :
- `Views/pages/calendrier.php` — liste chronologique / agenda (mois courant +
  à venir), formulaire d'ajout/édition d'événement (modale ou section).
- `Views/pages/anniversaires.php` — table fusionnée (users avec `date_naissance`
  non NULL + lignes `anniversaires`), triée par mois/jour, mois courant
  surligné, colonne Âge (`annee` connue → calcul, sinon `—`), formulaire d'ajout
  d'entrée manuelle.

**Actions POST** : `save_evenement`, `delete_evenement`, `save_anniversaire`,
`delete_anniversaire`. `check_csrf()` + `auth_can_manage_calendar()` (et contrôle
propriétaire pour `delete_evenement`).

**Nouveaux fichiers** : `CalendrierController`, `EvenementService`,
`AnniversaireService` (ou un `CalendrierService` unique — arbitrage dans le plan),
`EvenementRepository`, `AnniversaireRepository`, 2 vues, `assets/css/calendrier.css`,
helper RBAC.

---

### M5 — Rapport du Jour des responsables de bacenta

**Besoin** : centraliser les remontées terrain par centre et par date.

**Q/R de cadrage** :
- Le menu déroulant « centre » = table **`centres`** (`centre_id` FK).
- Granularité : **1 rapport = (centre + date)** — contrainte `UNIQUE`.
- **Modifiable** après enregistrement par l'auteur et l'admin.
- « Nom du responsable du centre » / « du bacenta » : **pré-remplis non
  modifiables**, dérivés automatiquement :
  - responsable du centre = manager du centre via `responsibilities`
    (`target_type='centre'`, `responsibility_type='manager'`) — nom via `users`.
  - responsable du bacenta = l'auteur du rapport (il crée le rapport pour son
    bacenta). Si l'auteur gère plusieurs bacentas du centre, sélecteur limité à
    ceux-là ; le nom affiché reste dérivé, non éditable.
- Créateur autorisé : **admin OU tout user détenant une responsabilité `manager`
  sur un bacenta dont `centre_id` = le centre choisi**. Nouveau helper
  `auth_can_report_for_centre(int $centreId): bool`.

**Champs du formulaire** (constante `RAPPORT_JOUR_FIELDS` pour piloter la vue) :

| Catégorie | Champs |
|-----------|--------|
| Identification | centre (select `centres`), date (`date`) |
| Responsables *(auto, lecture seule)* | responsable du centre, responsable du bacenta |
| Équipe | noms des assistants (`textarea`) |
| Assistance | nb présents, nb adultes, nb enfants, nb anciens, nb nouveaux (1ʳᵉ visite), nb nés de nouveau (`number`, ≥ 0) |
| Finances | offrande — montant total (`number`, ≥ 0) |
| Enseignement | nom du livre enseigné (`text`), chapitre enseigné (`text`) |

**Schéma** :

```
CREATE TABLE IF NOT EXISTS rapports_jour (
    id INT NOT NULL AUTO_INCREMENT,
    centre_id INT NOT NULL,
    date_rapport DATE NOT NULL,
    auteur_id INT NOT NULL,
    bacenta_id INT NULL,
    resp_centre_nom  VARCHAR(150) NULL,
    resp_bacenta_nom VARCHAR(150) NULL,
    assistants TEXT NULL,
    nb_presents      INT NOT NULL DEFAULT 0,
    nb_adultes       INT NOT NULL DEFAULT 0,
    nb_enfants       INT NOT NULL DEFAULT 0,
    nb_anciens       INT NOT NULL DEFAULT 0,
    nb_nouveaux      INT NOT NULL DEFAULT 0,
    nb_nes_de_nouveau INT NOT NULL DEFAULT 0,
    offrande DECIMAL(12,2) NOT NULL DEFAULT 0,
    livre_enseigne VARCHAR(150) NULL,
    chapitre_enseigne VARCHAR(80) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_rapport (centre_id, date_rapport),
    KEY idx_rapport_centre_date (centre_id, date_rapport),
    CONSTRAINT fk_rap_centre  FOREIGN KEY (centre_id)  REFERENCES centres(id) ON DELETE CASCADE,
    CONSTRAINT fk_rap_auteur  FOREIGN KEY (auteur_id)  REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_rap_bacenta FOREIGN KEY (bacenta_id) REFERENCES bacentas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Les colonnes `resp_*_nom` sont un **instantané** (le nom au moment du rapport),
rempli côté service à partir de `responsibilities` — jamais saisi par le client.

**Routes / navigation** :
- `Router::get('rapports', RapportController::class, 'index')` — liste filtrable
  (centre, mois) + accès « nouveau rapport ».
- `Router::get('rapport', RapportController::class, 'show')` — formulaire
  création / édition (`?id=` ou `?centre=&date=`).
- `SECTION_LABELS`/`ICONS`/`NAV_ORDER` : `rapports => 'Rapports du Jour'`.

**Actions POST** : `save_rapport_jour` (upsert par `(centre_id, date_rapport)` ;
`check_csrf()` ; `auth_can_report_for_centre()` ; l'édition exige
`auteur_id === current_user` OU admin ; nombres validés ≥ 0 via `Validator`).

**Nouveaux fichiers** : `RapportController`, `RapportJourService`,
`RapportJourRepository`, `Views/pages/rapports.php`, `Views/pages/rapport_form.php`,
`assets/css/rapports.css`, helper RBAC. Constante `RAPPORT_JOUR_FIELDS`.

Statistiques ultérieures : hors périmètre v1 (le stockage structuré les permet).

---

### M6 — Classes / Écoles post-culte

**Besoin** : gérer les 7 cursus post-culte, formateur, inscrits, progression,
examens, prochaine session. Ajout / suppression / édition de classes possibles.

Les 7 cursus (ordre initial) :

1. Manuel du nouveau croyant
2. Sept grands principes
3. Ce que signifie être un chrétien fort
4. École de la fondation solide
5. École de la vie victorieuse
6. École de la parole
7. École de l'apologétique  *(corrige « apologue tique »)*

**Q/R de cadrage** :
- Examens oral / écrit : **statut** `non_passe | reussi | echoue` (+ note et date
  facultatives).
- Progression **automatique** : quand `exam_oral = reussi` **ET**
  `exam_ecrit = reussi`, le service marque l'inscription `termine` et
  **inscrit automatiquement** la personne dans la classe d'`ordre` immédiatement
  supérieur (si elle existe), en transaction, sans doublon (`INSERT IGNORE` /
  contrôle d'unicité).
- Progression au sein d'une classe = **structure fixe** : `classes.nb_modules`
  définit le nombre de modules ; `classe_inscrits.modules_valides` = nombre de
  modules validés (0..`nb_modules`).
- Gestion / inscription : `auth_can_manage_classes()` = admin OU rôle
  ∈ {`berger`, `ms`, `pasteur`, `reverant`} détenant une responsabilité `manager`.

**Schéma** :

```
CREATE TABLE IF NOT EXISTS classes (
    id INT NOT NULL AUTO_INCREMENT,
    nom VARCHAR(150) NOT NULL,
    formateur_id INT NULL,
    ordre INT NOT NULL DEFAULT 0,
    nb_modules INT NOT NULL DEFAULT 1,
    prochaine_session DATE NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_classe_ordre (ordre),
    CONSTRAINT fk_classe_formateur FOREIGN KEY (formateur_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classe_inscrits (
    id INT NOT NULL AUTO_INCREMENT,
    classe_id INT NOT NULL,
    user_id INT NOT NULL,
    modules_valides INT NOT NULL DEFAULT 0,
    exam_oral  ENUM('non_passe','reussi','echoue') NOT NULL DEFAULT 'non_passe',
    exam_ecrit ENUM('non_passe','reussi','echoue') NOT NULL DEFAULT 'non_passe',
    exam_note DECIMAL(5,2) NULL,
    exam_date DATE NULL,
    statut ENUM('inscrit','termine') NOT NULL DEFAULT 'inscrit',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_inscrit (classe_id, user_id),
    CONSTRAINT fk_ci_classe FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_ci_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Seeder** (`Database/Seeders/DatabaseSeeder.php`) : insérer les 7 classes avec
`ordre` 1→7 si `classes` est vide. Constante `CLASSES_CURSUS` = liste ordonnée
des 7 libellés (source du seed, réutilisable en UI).

**Progression automatique — règle précise** (dans `ClasseService`, en transaction) :
- Déclencheur : sauvegarde d'un `classe_inscrit` où `exam_oral = 'reussi'`
  ET `exam_ecrit = 'reussi'`.
- Effet : `statut = 'termine'` sur l'inscription courante ; recherche de la
  classe `WHERE actif = 1 AND ordre > :ordreCourant ORDER BY ordre ASC LIMIT 1` ;
  si trouvée, `INSERT IGNORE INTO classe_inscrits (classe_id, user_id) VALUES (...)`.
- Idempotent : rejouer la sauvegarde ne crée pas de doublon (unicité
  `(classe_id, user_id)`).
- Pas de rétrogradation automatique si un statut repasse à `echoue` /
  `non_passe` (action manuelle si nécessaire).

**Routes / navigation** :
- `Router::get('classes', ClasseController::class, 'index')` — grille des classes.
- `Router::get('classe', ClasseController::class, 'show')` — détail : formateur,
  `nb_modules`, prochaine session, liste des inscrits avec statut d'examens et
  modules validés.
- `SECTION_LABELS`/`ICONS`/`NAV_ORDER` : `classes => 'Classes & Écoles'`.

**Actions POST** :
- `save_classe` (nom, formateur_id, ordre, nb_modules, prochaine_session, actif)
- `delete_classe` (GET `getAction`, cohérent avec les autres suppressions ;
  CASCADE supprime les inscriptions)
- `save_classe_inscrit` (classe_id, user_id, modules_valides, exam_oral,
  exam_ecrit, exam_note?, exam_date? ; déclenche la progression auto)
- `remove_classe_inscrit`

Toutes gardées par `auth_can_manage_classes()` + `check_csrf()`.

**Nouveaux fichiers** : `ClasseController`, `ClasseService`, `ClasseRepository`,
`Views/pages/classes.php`, `Views/pages/classe_detail.php`,
`assets/css/classes.css`, helper RBAC, constante `CLASSES_CURSUS`.

---

### M2 — Budget Bus du dimanche par centre

**Besoin** : suivre les sommes retirées / collectées par centre pour le bus du
dimanche, avec sous-totaux automatiques par centre et par mois.

**Q/R de cadrage** :
- « zone » = **centre** (table `centres`).
- Colonnes : centre, date, somme retirée, observations.
- Saisie / consultation : **admin + responsable réel du centre**
  (`auth_can_manage_center($centreId)`, helper existant).

**Schéma** :

```
CREATE TABLE IF NOT EXISTS bus_budget (
    id INT NOT NULL AUTO_INCREMENT,
    centre_id INT NOT NULL,
    date_retrait DATE NOT NULL,
    montant DECIMAL(12,2) NOT NULL DEFAULT 0,
    observations TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bus_centre_date (centre_id, date_retrait),
    CONSTRAINT fk_bus_centre  FOREIGN KEY (centre_id)  REFERENCES centres(id) ON DELETE CASCADE,
    CONSTRAINT fk_bus_creator FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Routes / navigation** :
- `Router::get('budgetBus', BusController::class, 'index')` — tableau de saisie
  (filtré au périmètre : centres gérés par l'utilisateur, ou tous si admin),
  lignes éditables, sous-totaux par centre et par mois (agrégation SQL
  `GROUP BY centre_id, DATE_FORMAT(date_retrait,'%Y-%m')`), total année.
- `SECTION_LABELS`/`ICONS`/`NAV_ORDER` : `budgetBus => 'Budget Bus'`.

**Actions POST** : `save_bus_budget` (create + update), `delete_bus_budget`
(GET `getAction`). `check_csrf()` + `auth_can_manage_center()` sur le centre visé.

**Nouveaux fichiers** : `BusController`, `BusBudgetService`,
`BusBudgetRepository`, `Views/pages/budget_bus.php`, `assets/css/bus.css`.

---

## 5. Conventions transverses (tous modules)

- **PSR-12**, `declare(strict_types=1)`, paramètres/retours typés.
- **Couches strictes** : pas de SQL hors Repository, pas de HTML hors View, pas
  d'accès requête hors Controller. Réutiliser `App\Core\Query`
  (`all/one/value/run/transaction`).
- **Nouvelles pages** : route `Router::get` + méthode Controller +
  Service/Repository + vue `Views/pages/*` + rendu via
  `render_page($title, $content, $charts?)` + entrées `SECTION_LABELS`,
  `SECTION_ICONS`, `NAV_ORDER` (`Config/constants.php`).
- **Nouvelles actions POST** : `case` dans `ActionsController::postAction()` avec
  `check_csrf()`, validation `Validator`/service, écriture Repository,
  `redirect()`. Suppressions → `getAction()` (cohérent avec l'existant).
- **Schéma** : uniquement des blocs idempotents dans le fichier de migration
  unique — `CREATE TABLE IF NOT EXISTS`, `ALTER` gardés par
  `column_exists()` / `index_exists()`. Étendre `down()` (ordre FK-safe) et
  `DatabaseSeeder.php` si des données de démo aident.
- **RBAC** : nouveaux wrappers globaux dans `app/Auth/compat.php`
  (`auth_can_manage_calendar`, `auth_can_report_for_centre`,
  `auth_can_manage_classes`). Le filtrage se fait sur les **données** (listes,
  cibles), jamais uniquement sur l'affichage des boutons. Ne jamais faire
  confiance à un `centre_id` / `bacenta_id` / `user_id` client : re-dériver le
  périmètre autorisé côté service et revalider chaque id.
- **CSS** : nouveaux fichiers modulaires sous `assets/css/`, variables de
  `assets/css/variables.css`, **aucune** couleur / marge en dur, **aucun** bloc
  `style` / `script` inline dans les vues.
- **Ne jamais casser** : URLs existantes, auth, formulaire « ajouter un membre »
  de la page bacenta, pointage culte actuel. On ajoute des onglets / sections /
  pages à côté.
- `install.php` reste supprimable — aucun code runtime n'en dépend.

## 6. Vérification (pas de test runner)

Il n'existe pas de PHPUnit dans ce dépôt. Pour **chaque module**, avant commit :

1. `php -l` sur **chaque** fichier PHP modifié ou créé.
2. `php install.php` sur une base de dev (recrée schéma + données de démo) —
   confirme que les blocs de migration passent.
3. Relancer `\Database\Migrations\up()` seul sur une base déjà peuplée (simulation
   prod) — confirme l'**idempotence** (aucune erreur, aucune perte).
4. Parcours manuel connecté :
   - **admin** : la nouvelle page apparaît au menu, CRUD complet fonctionne.
   - **berger / responsable concerné** : voit uniquement son périmètre ; une
     tentative d'accès à un id hors périmètre est refusée (`render_gate` ou
     redirection).
   - soumettre chaque formulaire **avec CSRF**, vérifier la persistance en base
     et l'affichage après `redirect()`.
5. Cas spécifiques :
   - **M1** : pointer 2 dates différentes d'une même unité → 2 jeux de présences
     distincts ; re-soumettre une date → remplacement propre (pas de doublon) ;
     matrice annuelle cohérente + impression.
   - **M5** : 2ᵉ rapport même (centre, date) → édition, pas de doublon ;
     sous-totaux corrects.
   - **M6** : passer les 2 examens à « reussi » → inscription auto dans la classe
     d'ordre suivant, sans doublon si on re-sauvegarde.
   - **M4** : anniversaire sans année → âge « — » ; mois courant surligné.
   - **M2** : sous-totaux par centre et par mois exacts.

## 7. Risques / points ouverts (à trancher dans les plans de module)

- **M1 / `AttendanceService` existant** : étendre vs service dédié `PresenceService`.
  Décision au plan M1 après lecture de `app/Services/AttendanceService.php` et
  `app/Repositories/AttendanceRepository.php`.
- **M1 « personne visitée » (Bacentas)** : l'existant (`visites`) est-il
  suffisant ? Question à l'utilisateur au plan M1. Hors périmètre par défaut.
- **M3 calcul du %** : intégrer ou non les nouveaux champs optionnels dans le
  dénominateur de `ReportService`.
- **M4 responsabilité d'événement** : `responsibilities.target_type='evenement'`
  souhaité en v1 ou v2 ?
- **M5 responsable du bacenta** : comportement exact quand l'auteur gère
  plusieurs bacentas d'un même centre (sélecteur restreint retenu).
- **Menu** : 5 nouvelles entrées de navigation — vérifier l'encombrement de la
  sidebar ; éventuel regroupement (sous-menu « Suivi » / « Planification ») à
  discuter, non bloquant.

## 8. Suite

Pour chaque module, dans l'ordre du §3 : invoquer le skill `writing-plans` pour
produire un plan d'implémentation détaillé (fichiers, étapes, TDD applicable au
sens « écrire la vérification manuelle attendue avant le code »), puis exécuter.
