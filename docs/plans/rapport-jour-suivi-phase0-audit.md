# Phase 0 - Audit & décisions métier validées
# Suivi, motivation et rappels des Rapports du Jour

- **Date :** 2026-11-23
- **Branche :** `arena/ed931e2b-labelleeglise` (base : `feat/marking-system`)
- **Statut :** audit terminé - aucune migration destructive n'est appliquée par ce document
- **Règles applicables :** §16 du plan (`docs/plans/rapport-jour-suivi-motivation-implementation-plan.md`) prévaut sur les hypothèses provisoires des §3, §5, §11, §12, §14.

## 1. Schéma réel constaté (lecture de `Database/Migrations/2024_01_01_000000_create_schema.php`)

| Table | Constat | Consequence |
|---|---|---|
| `rapports_jour` | `UNIQUE KEY uniq_rapport_scope (scope_type, scope_id, date_rapport)` confirmé ; `scope_type ENUM('centre','bacenta')`, `scope_id INT NOT NULL`, `auteur_id` FK → `users(id) ON DELETE CASCADE` | **Un seul rapport par cible et date** - OK. Le lien occurrence ↔ rapport se fait par `(scope_type, scope_id, date_rapport)`, et `submitted_report_id` référence `rapports_jour.id`. |
| `centres` | `id`, `nom`, `created_at` uniquement - **aucun calendrier** | Ajout idempotent de `jours_semaine VARCHAR(60) NULL`, `heure_debut TIME NULL`, `heure_fin TIME NULL`, `heure_limite TIME NULL` (même conventions que `cultes`/`bacentas`/`basontas`, jours = CSV des libellés `WEEK_DAYS`). |
| `bacentas` | `jours_semaine VARCHAR(60) NULL` (CSV de libellés `WEEK_DAYS`), `heure_debut`, `heure_fin` (bloc 10 de la migration) | Réutilisation directe. Ajout de `heure_limite TIME NULL`. |
| `WEEK_DAYS` (`Config/constants.php:231`) | `['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche']` - stocké en CSV, **jamais traduit** | La génération d'occurrences mappe chaque libellé vers `date('N')` (1 = lundi … 7 = dimanche). Toute valeur hors `WEEK_DAYS` est ignorée et journalisée. |
| `responsibilities` | `(user_id, responsibility_type, target_type, target_id)`, `UNIQUE uniq_responsibility`, `target_type` ∈ `'center'` (oui : `listForTarget('center', …)` est utilisé par `RapportJourService`), `'bacenta'`, `'cult'`, extensible (VARCHAR). `created_at`/`updated_at` présents | **Historique d'affectation = `created_at` de la ligne `responsibilities`** (création ; l'UPDATE `updated_at` ne marque pas une nouvelle affectation). La date de prise de responsabilité utilisée pour le cumul §16.7 est `MIN(created_at)` des responsabilité `manager` actives de l'utilisateur. Aucune date de fin n'existe : une révocation supprime la ligne - l'historique est donc partiel par construction (limite documentée, §6 ci-dessous). |
| `notifications` | `recipient_id` FK → `users`, `type VARCHAR(60)`, `title`, `message`, `link`, `is_read` | Réutilisée telle quelle pour le canal in-app (via `NotificationService::notify()`). Nouveau `type` : `rapport_rappel`, `rapport_bilan`, `rapport_felicitation`. |
| `users` | `email VARCHAR(100) UNIQUE`, `compte_actif`, `account_status`, `email_verified` | Les rappels email n'exigent pas `email_verified = 1` (comptes internes), mais l'email vide/invalide est refusé et le motif est journalisé (`rapport_reminder_logs.last_error`, statut `skipped`). |
| `centre_horaires` | Horaires **d'affichage public** (libellé libre + jours en texte libre) - non exploitable machine | Non réutilisée pour les échéances ; le calendrier d'occurrences est le couple `jours_semaine`/`heure_*` ajouté aux tables `centres`/`bacentas`. |

## 2. Index & contraintes confirmés

- `rapports_jour.uniq_rapport_scope (scope_type, scope_id, date_rapport)` → la règle « un rapport valide satisfait l'occurrence de même cible et même date » est garantie côté base.
- Les nouvelles tables ajoutent : `rapport_occurrences UNIQUE (scope_type, scope_id, report_date)`, `rapport_occurrence_responsables UNIQUE (occurrence_id, user_id)`, `rapport_reminder_logs UNIQUE idempotency_key + UNIQUE (occurrence_id, recipient_user_id, reminder_type, channel)`, `rapport_scoring_results UNIQUE (user_id, period_type, period_start, period_end)`.

## 3. E-mail & tâches planifiées

- Envoi email : exclusivement via `App\Services\MailService::send()` (PHPMailer vendorisé, journalisé dans `Storage/logs/mail.log`). SMTP absent ⇒ retour `false`, **jamais d'exception fatale**. Pas d'email ⇒ journal + statut `skipped` dans `rapport_reminder_logs`.
- Aucune tâche planifiée / verrou existant dans le dépôt. Conventions découvertes : `install.php` (script PHP racine amorcé via `Bootstrap/init.php`), pas de `bin/`, pas de Composer. ⇒ La commande de rappels est `cron-rapport-rappels.php` à la racine, invocable par CLI (`php cron-rapport-rappels.php`) ou depuis le web avec le jeton secret `rapport_settings.cron_token` (`?page=` dédiée remplacée par l'action GET `?action=rapport_cron&token=…` - non listée dans le menu, protégée par comparaison `hash_equals`). Verrou mémoire via `GET_LOCK()` MySQL (fonctionne en mutualisé, se libère à la fin de la session DB).

## 4. Conventions de routes / vues / permissions

- Pages : clé `?page=` dans `Routes/web.php` → contrôleur → `render_page(SECTION_LABELS['…'], view('pages/…', [...]))`.
- Actions POST : `ActionsController::postAction()` (CSRF déjà vérifié en haut), redirections vers `index.php?page=…`. Suppressions : `getAction()`.
- Autorisation : helpers `auth_can_*` dans `app/Auth/compat.php`, contrôles serveur systématiques (jamais seulement un bouton masqué).
- Nav : `NAV_ORDER` (admin) + blocs « hoist » pour les non-admins.
- Fuseau : `Config/app.php` → `timezone = Africa/Libreville` (déjà la valeur par défaut et celle de `.env.example`). Toutes les dates/heures du module sont interprétées dans ce fuseau ; les instants sont convertis avec `DateTimeZone('Africa/Libreville')` explicite.

## 5. Décisions (validées par le commanditaire - §16, rappel)

1. **Une occurrence par réunion planifiée**, pour `centre` **et** `bacenta`, indépendamment - jamais l'une à la place de l'autre.
2. **Tous les responsables actifs sont notifiés** ; le **crédit revient au premier responsable dont la soumission valide est enregistrée** (atomique : `UPDATE … WHERE credited_user_id IS NULL` dans la transaction de sauvegarde). Auteur ≠ crédité ≠ notifiés.
3. **Soumission pour le compte de** : si l'auteur n'est pas un responsable affecté de l'occurrence (admin ou tiers), le rapport satisfait l'occurrence (elle passe à « remplie ») mais **aucun crédit n'est attribué automatiquement** (`credited_user_id` reste `NULL`, `submitted_by_user_id` = auteur). La règle est explicite et affichée telle quelle ; aucun choix supplémentaire n'est inventé.
4. **Heure limite fixe par calendrier** (minutes près, fuseau `Africa/Libreville`), enregistrée **sur l'occurrence à la génération** (`due_at`) - une modification ultérieure du paramètre ne change pas les échéances déjà créées. Si aucune heure limite n'est configurée ni sur le calendrier ni en défaut global : **erreur de configuration signalée à l'admin**, occurrence générée avec `due_at = NULL` et visible « heure limite manquante » - jamais d'heure inventée.
5. **Rappels** : veille 18 h (préventif) / jour J après l'heure limite (échéance) / lendemain 09 h (relance). Configurables & désactivables. Idempotence par (occurrence, destinataire, type, canal). La soumission annule tous les rappels non encore envoyés (les lignes `pending` passent à `skipped/cancelled`… - ici : `status='cancelled'` - voir note).
6. **Félicitations** au franchissement d'un **nouveau niveau de barème** (une seule notification par user + période + version de barème) ; **bilans hebdomadaire (semaine ISO) et mensuel (mois civils)** générés et communiqués ; statut **« Non évalué »** si aucune échéance attendue ; aucune divulgation de résultats nominatifs hors périmètre autorisé.
7. **Classement** : administrateurs + responsables autorisés à leur périmètre uniquement ; jamais public.
8. **Cumul** depuis la date de prise de responsabilité (`MIN(responsibilities.created_at)` pour les responsabilités `manager` centre/bacenta de l'utilisateur). Si aucune affectation trouvée → « Non évalué » et proposition à l'admin de définir une date de départ (`rapport_settings.cumul_start_default`) - **jamais déduite ni inventée**.
9. **Modifications de barème** : versionnées ; chaque résultat historisé porte `rules_version` (empreinte des seuils) et `message_snapshot` - l'historique n'est jamais réécrit.

### Notes d'implémentation tranchant les ambiguïtés restantes (documentées, réversibles)

- **Dépassement de grâce** (§3.3 « délai de grâce ») : paramètre `grace_minutes` (défaut **720 min = 12 h**). État `à remplir` entre l'heure limite et la fin du délai ; `en retard` ensuite. Le délai est configurable dans Paramètres.
- **États individuels** (§16.2) : `rapport_occurrence_responsables.individual_status` : `credited` / `filled_by_other` / `missing` (et `cancelled` si l'occurrence est annulée).
- **`rapport_reminder_logs.status`** : `pending`, `sent`, `failed`, `skipped`, `cancelled` (adapté : une soumission clos les rappels futurs - ils passent à `cancelled`, pas supprimés).
- **Annulation** : réservée aux admins (`rapport_cancel_occurrence` postulé §4 - action POST, motif obligatoire, journalisée dans `rapport_audit_logs`).
- **Relance manuelle** : admin + responsable autorisé sur une occurrence de son périmètre (`rapport_manual_reminder`).

## 6. Limites connues (documentées, pas devinées)

- **Historique des affectations incomplet** : `responsibilities` ne conserve pas les lignes révoquées (DELETE). La date de début de cumul est donc `MIN(created_at)` des responsabilités *actuelles* ; une responsabilité révoquée-puis-réattribuée réinitialise la borne basse. Une date de départ explicite peut être imposée globalement via `rapport_settings.cumul_start_default` (validée par l'admin) ; à défaut et sans responsabilité trouvée, le cumul est « Non évalué ». Aucune autre date n'est inventée.
- **Calendrier centre absent auparavant** : les centres n'avaient aucun calendrier ⇒ aucune occurrence n'est générée tant que l'admin n'a pas configuré `jours_semaine`/`heure_limite` sur le centre (erreur de configuration visible dans Paramètres et sur le tableau de bord, pas de génération silencieuse).
- **Rapport existant hors jour de réunion** : un rapport soumis un jour sans réunion n'ouvre **pas** d'occurrence (aucune invention d'échéance). La synchronisation marque « remplie » toute occurrence dont un rapport existe.
- **Pas de runtime PHP disponible dans le bac à sable de développement** : vérification par lint syntaxique (`php-parser`) + revue manuelle ; la recette réelle (migration, cron, UI) doit être jouée sur l'hébergement cible.

## 7. Conséquences sur le modèle de données (migration bloc 17, idempotente)

Tables créées : `rapport_occurrences`, `rapport_occurrence_responsables`, `rapport_reminder_logs`, `rapport_scoring_rules`, `rapport_scoring_results`, `rapport_settings`, `rapport_audit_logs`.
Colonnes ajoutées : `centres.jours_semaine|heure_debut|heure_fin|heure_limite`, `bacentas.heure_limite`.
Rien n'est supprimé ni modifié côté données existantes.
