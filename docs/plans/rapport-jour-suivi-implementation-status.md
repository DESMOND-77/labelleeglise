# Module 20 - Suivi des Rapports du Jour : état d'implémentation (2026-11-23)

- **Plan :** `rapport-jour-suivi-motivation-implementation-plan.md` (§16 prévaut) · **Audit :** `rapport-jour-suivi-phase0-audit.md`
- **Branche :** `arena/ed931e2b-labelleeglise` · **Vérification :** lint syntaxique statique uniquement (`php-parser`) - **aucun runtime PHP ni MySQL disponible dans le bac à sable de développement**. La recette fonctionnelle doit être jouée sur l'hébergement cible.

## 1. Ce qui est implémenté

### Phase 1 - Moteur d'occurrences (partagé avec le save-flow)
- `rapport_occurrences` / `rapport_occurrence_responsables` : génération idempotente (centre **et** bacenta, indépendamment) par `RapportOccurrenceService::sync()` (échéances J-30 → J+90).
- Heure limite **snapshotée** sur l'occurrence à la génération (`due_at`, fuseau `Africa/Libreville`) ; aucune heure limite configurée ⇒ occurrence sans `due_at` + message d'erreur visible (jamais d'invention).
- Attribution du crédit au **premier dépôt valide** (transaction du save-flow `RapportJourService::save()` → `RapportOccurrenceService::recordSubmission()`), `first-deposit-wins` même à la resoumission/édition ; auteur ≠ crédité ≠ notifiés ; `filled_by_other` pour les autres responsables.
- Clôture automatique des rappels non envoyés à la soumission ; backfill idempotent depuis les rapports existants.

### Phase 2 - Checklist & suivi (UI)
- Page `rapport_suivi` (tous les responsables avec périmètre + admin) : filtres période (semaine ISO / mois civil / personnalisée), type, centre, responsable, statut, recherche ; bascule « Mes échéances » / « Mon périmètre » ; pagination 25/page ; colonnes date+échéance, structure, type, responsables, statut, rapport, dernière relance ; actions : Remplir / Voir, Relancer (POST + CSRF + confirm), Annuler (admin, motif obligatoire, `rapport_occurrence_cancel`) ; **export CSV** (`rapport_suivi_export`, périmètre admin = tout, périmètre responsable = le sien, `vue=mes` forcé hors périmètre) ; badges statut FR.
- Statuts : À venir / À remplir / En retard / Rempli à temps / Rempli en retard / Annulé (délai de grâce configurable, défaut 12 h).

### Phase 3 - Notifications & rappels (in-app + email)
- `RapportReminderService` : préventif (veille @ heure configurable), échéance (jour J après l'heure limite), relance (lendemain @ heure configurable), relance manuelle ; **verify-before-send** (le rapport est revérifié juste avant chaque envoi ; une soumission entre-temps ⇒ rappels clôturés, aucun envoi) ; idempotence par (occurrence, destinataire, type, canal) - unique en base + lignes `cancelled/sent/failed/skipped` ; échecs indépendants par canal ; tentatives bornées (défaut 3, toutes les 30 min) ; `GET_LOCK('rapport_rappels_lock')` intégré à `processAll()` (une seule exécution à la fois) ; **dry-run** complet (aucun envoi, rapport détaillé).
- E-mails : `MailService::sendRapportReminder()` et `sendRapportBilan()` + templates `views/emails/rapport-rappel.php` (rappel) et `rapport-bilan.php` (bilan) - inline CSS, FR.
- Déclenchement : CLI `php cron-rapport-rappels.php [--dry-run]` (à planifier, ex. toutes les 15 min) ; endpoint webcron `index.php?action=rapport_cron&token=<cron_token>[&dry_run=1]` (jeton secret `rapport_settings.cron_token`, généré une fois par la migration) ; bouton admin « Simuler / Envoyer maintenant » dans Paramètres (audit `cron_manual_run`).
- Félicitations : notifiées dans le bilan au franchissement d'un **nouveau** niveau de barème (détection vs niveau précédent + `notified_at` ⇒ jamais deux fois pour la même période).
- Bilans **hebdomadaire (semaine ISO, lundi)** et **mensuel (mois civil, le 1er)** : génération + historisation (`rapport_scoring_results`, `rules_version` + `message_snapshot`), envoi aux responsables concernés (in-app + email), **« Non évalué »** si aucune échéance attendue (jamais 0 %).

### Phase 4 - Barème & scoring
- `RapportScoringService` : échelons [min, max[ + point 100 inclus, validation serveur de la couverture complète de [0, 100] sans trou ni chevauchement à l'enregistrement/suppression ; version d'empreinte du barème ; statistiques individuelles (crédités / manqués / par un autre) ; taux avec numérateur/dénominateur partout.
- Historisation par période (aucune réécriture rétroactive ; le barème en vigueur est snapshoté dans chaque résultat).

### Phase 5 - Tableau de bord & classement
- `rapport_dashboard` : KPI (réalisation, ponctualité + deltas vs période précédente), 3 graphes (évolution jour, par structure, répartition statut), "responsables les plus réguliers", alertes admin (problèmes de configuration, rappels en échec, occurrences sans responsable).
- `rapport_classement` : **réservé admins + responsables ayant un périmètre** (limité à leur périmètre de gestion ; jamais public) ; tri taix + ponctualité + crédités avec rangs ex-aequo ; **Non évalué** si aucune échéance.
- `rapport_responsable` : fiche (soi-même toujours autorisée ; autre personne ⇒ admin ou périmètre partagé, sans l'historique des bilans) ; blocs semaine ISO / mois civil / cumul (depuis `MIN(responsibilities.created_at)` des responsabilités `manager` actuelles, ou date admin explicite `cumul_start_default` - **jamais** déduite) ; historique des bilans.

### Phase 6 - Paramètres, navigation, audit
- `rapport_parametres` (admin) : exécution cron (CLI + webcron + boutons dry-run/réel), paramètres généraux (heure limite globale, grâce, rappels on/off + heures, canaux in-app/email, tentatives, félicitations, bilans, départ de cumul), **éditeur de calendriers** centres & bacentas (jours/heure début/fin/heure limite), **éditeur de barème** (CRUD avec validation de couverture), journal d'audit, journal des échecs d'envoi.
- Navigation : groupe « Rapport du Jour » (NAV_ORDER admin), hoist non-admin (checklist + tableau de bord + classement selon périmètre) ;Paramètres admin-only ; CSS dédié `assets/css/rapport_suivi.css` (pages chargées uniquement sur le module).
- Audit : `rapport_audit_logs` - annulations, relances manuelles, modifications barème/calendrier/paramètres, bilans, exécutions cron manuelles.

## 2. Limites connues (documentées - pas de supposition)

1. **Aucune vérification d'exécution possible ici** (pas de PHP/MySQL) ; le lint statique et la revue manuelle sont les seuls filets. La recette ci-dessous est OBLIGATOIRE avant ouverture aux utilisateurs.
2. **Historique d'affectations partiel** : `responsibilities` ne conserve que les lignes actives - une responsabilité révoquée-puis-réattribuée décale la borne basse du cumul. Une date de départ explicite (`cumul_start_default`) peut être validée par l'admin ; à défaut, le cumul est « Non évalué » - jamais la date de lancement du module.
3. **Heure limite globale vide par défaut** (migration) : erreur de configuration visible tant qu'elle n'est pas renseignée ; les occurrences générées le restent sans `due_at`.
4. **Les occurrences sont créées à la synchronisation** (cron, ouverture des pages du module, enregistrement d'un rapport) - le cron reste le mécanisme principal.
5. Échecs email sans SMTP configuré : journalisés, in-app non impacté ; les lignes passent en échec borné puis s'arrêtent (pas de boucle).

## 3. Recette de mise en service (sur le PHP + MySQL cible)

> ⚠️ **N'exécutez PAS `install.php`** : il fait `down()` (DROP) + seed - destructif.
> La migration Bloc 20 n'ajoute que des colonnes/tables/INSERT IGNORE : exécutez-la SANS la partie destructive :

```bash
cd <racine>/labelleeglise
cp Database/Migrations/2024_01_01_000000_create_schema.php /tmp/up_only_backup.php   # sécurité
php -r "require 'Bootstrap/init.php'; require 'Database/Migrations/2024_01_01_000000_create_schema.php'; \Database\Migrations\up();"
```

Puis :
1. Vérifier les tables (`rapport_occurrences`, `_responsables`, `rapport_reminder_logs`, `rapport_scoring_rules`…) et la présence du barème par défaut (5 échelons).
2. Configurer `heure_limite_defaut` **ou** les heures limites par calendrier ; renseigner les **calendriers des centres** (aucun n'existait) et des bacentas (Paramètres > Suivi rapports).
3. `php cron-rapport-rappels.php --dry-run` : vérifier le résumé JSON, puis `php cron-rapport-rappels.php` (ou le bouton « Simuler / Envoyer maintenant »).
4. Planifier la tâche (cron système) : `*/15 * * * * php /chemin/cron-rapport-rappels.php >/dev/null 2>&1` (ou équivalent webcron + jeton).
5. Casse-test : deux soumissions simultanées du même (scope, date) ⇒ un seul crédit (le second rapport s'enregistre, le crédit reste au premier) ; un rapport saisi par un admin ⇒ occurrence clôturée, **aucun** responsable crédité ; envoi après échéance ⇒ statut `filled_late`.
6. Vérifier les e-mails (config SMTP), la page Notifications (types `rapport_rappel`, `rapport_bilan`), la fiche responsable, le classement (admin vs responsable scopé vs membre simple).
7. Vérifier l'audit (`rapport_audit_logs`) après une annulation, une relance manuelle et une sauvegarde de barème.
8. Basculer les SSO/SMTP production ; supprimer le jeton webcron si seule la voie CLI est utilisée (laisser vide = webcron refusé).

## 4. Points ouverts (hors périmètre ce lot)

- Tests automatisés (PHPUnit absent du projet, pas de runtime de test ici) - la stratégie TDD du plan reste à jouer côté CI avec base jetable.
- WhatsApp/SMS/push : hors scope V1 (in-app + email uniquement).
- Classement public : exclu par §16.6.
