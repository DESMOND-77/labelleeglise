# Plan d'implémentation — Suivi, motivation et rappels des Rapports du Jour

- **Projet :** La Belle Église
- **Dépôt :** `DESMOND-77/labelleeglise`
- **Branche de travail :** `docs/rapport-jour-suivi-motivation-plan`
- **Statut :** plan d'implémentation détaillé — aucune fonctionnalité applicative n'est modifiée par ce document
- **Canaux de rappel retenus :** notifications in-app et email
- **Fuseau horaire métier :** `Africa/Libreville` (à centraliser dans la configuration)

## 1. Objectif et périmètre

Faire évoluer le module existant « Rapport du Jour » pour permettre à l'administration et aux responsables autorisés de :

1. savoir quels rapports étaient attendus, lesquels ont été soumis et lesquels sont en retard ;
2. visualiser les résultats par semaine, mois, période personnalisée, centre, bacenta et responsable ;
3. encourager les responsables avec un barème configurable, des messages de félicitations et des statistiques individuelles ;
4. envoyer des rappels automatiques in-app et par email en fonction du calendrier réel des réunions ;
5. consulter une checklist opérationnelle et un tableau de bord d'évolution.

Le système doit réutiliser les règles d'accès, les services et les dépôts existants. Il ne doit pas créer un second formulaire de rapport ni modifier le fonctionnement actuel de sauvegarde sans nécessité démontrée.

### Hors périmètre de cette première livraison

- WhatsApp, SMS et notifications push navigateur ;
- classement public ou affichage des résultats à tous les membres ;
- pénalités financières ou sanctions automatiques ;
- modification rétroactive silencieuse des calendriers ou des barèmes ;
- génération d'un rapport lorsqu'aucune réunion/occurrence n'était prévue.

## 2. État existant à préserver

L'exploration du dépôt a identifié les composants suivants :

- `RapportController` expose la liste et le formulaire du rapport ;
- `RapportJourService` porte la validation, les autorisations métier et l'enregistrement ;
- `RapportJourRepository` accède à `rapports_jour`, notamment par `scope_type`, `scope_id` et `date_rapport` ;
- les rapports peuvent concerner une cible `centre` ou `bacenta` ;
- `BacentaRepository` expose déjà `jours_semaine`, `heure_debut` et `heure_fin` ;
- les responsabilités sont modélisées dans `responsibilities` et interrogées par `ResponsibilityRepository` ;
- `NotificationService` et `NotificationRepository` gèrent déjà les notifications internes ;
- `MailService` est déjà injecté dans `RapportJourService`.

**Conséquence d'architecture :** la nouvelle fonctionnalité doit se brancher sur ces composants plutôt que dupliquer leurs responsabilités. Avant toute migration, vérifier le schéma réel de `centres`, `users`, `responsibilities`, `notifications`, les index, les contraintes d'unicité, la configuration d'envoi email, les conventions de routes, les vues et le mécanisme de tâches planifiées déjà présent.

### Points à vérifier pendant la phase 0

- Le champ `jours_semaine` des bacentas est-il toujours stocké en CSV et quelles sont exactement les valeurs admises par `WEEK_DAYS` ?
- Les centres disposent-ils déjà d'un calendrier hebdomadaire ou faut-il en ajouter un ?
- Un centre doit-il produire un rapport à chaque jour de réunion, indépendamment des rapports de ses bacentas ?
- Quelles responsabilités (`manager`, responsable de centre, suppléant) sont destinataires des rappels ?
- L'email des responsables est-il vérifié et disponible ? Comment `MailService` expose-t-il l'envoi et les erreurs ?
- Existe-t-il déjà une commande CLI, un cron documenté ou un verrouillage de tâches ?
- La table `rapports_jour` autorise-t-elle un seul rapport par cible et date, comme le laisse supposer `findByScope`/ `upsert` ? Vérifier l'index SQL correspondant.

Ne pas supposer qu'un responsable de bacenta et le créateur technique du rapport sont nécessairement la même personne. Les métriques de responsabilité doivent être attribuées à partir de `responsibilities` et du calendrier d'affectation, pas uniquement de `auteur_id`.

## 3. Règles métier à faire valider

### 3.1 Une échéance = une occurrence attendue

Le dénominateur des taux de réalisation doit provenir d'occurrences de rapport attendues, pas du nombre de jours du calendrier.

Une occurrence attendue identifie au minimum :

- la cible : centre ou bacenta ;
- la date de réunion/rapport ;
- le ou les responsables à notifier ;
- l'heure limite de soumission ;
- le statut de suivi.

Un bacenta qui se réunit le dimanche ne doit pas être pénalisé les autres jours. Un responsable nouvellement nommé ne doit pas recevoir des échéances antérieures à sa prise de fonction.

### 3.2 Date et heure limite

Valeurs initiales proposées, configurables :

- rappel préventif : la veille à 18 h ;
- rappel à échéance : le jour de réunion à l'heure de fin de réunion + 30 minutes ;
- relance : le lendemain à 09 h si le rapport reste absent ;
- fréquence maximale : une notification par type de rappel et par occurrence, sauf relance explicitement configurée.

Si les heures de réunion sont absentes, utiliser une heure limite globale configurable et afficher clairement cette règle dans l'administration. Ne pas fabriquer une heure de réunion.

### 3.3 Définition du rapport soumis

Une occurrence est « remplie » lorsqu'un rapport valide existe pour la même cible et la même date. Les états recommandés :

- **À venir** : échéance future ;
- **À remplir** : échéance atteinte, rapport absent, délai de grâce non écoulé ;
- **En retard** : délai de grâce dépassé et rapport absent ;
- **Rempli à temps** : rapport enregistré avant l'échéance ;
- **Rempli en retard** : rapport enregistré après l'échéance ;
- **Non applicable / annulé** : occurrence explicitement annulée par un utilisateur autorisé, avec motif et historique.

Un rapport soumis après relance clôt les rappels futurs de cette occurrence. Les notifications déjà envoyées restent dans l'historique.

### 3.4 Attribution

Par défaut, attribuer la performance à la personne responsable de la cible à la date de l'occurrence. Si plusieurs responsables sont affectés, décider si chacun reçoit le crédit complet ou si le crédit revient au responsable principal. Recommandation : attribuer l'échéance au responsable principal désigné ; garder les suppléants comme destinataires secondaires sans double comptage.

L'auteur effectif reste visible pour audit. Une soumission par un administrateur ou un remplaçant peut satisfaire l'occurrence, sans réattribuer automatiquement la performance au compte de l'auteur.

## 4. Modules fonctionnels

### A. Dashboard de suivi

Créer un tableau de bord avec filtres :

- période : semaine ISO, mois, plage personnalisée ;
- centre, bacenta, responsable ;
- état : à venir, à remplir, en retard, rempli à temps, rempli en retard ;
- type de cible.

Indicateurs :

- occurrences attendues ;
- rapports soumis ;
- rapports soumis à temps ;
- rapports en retard ;
- rapports manquants ;
- taux de réalisation = occurrences remplies / occurrences attendues ;
- taux de ponctualité = rapports soumis à temps / occurrences attendues ;
- évolution par rapport à la période précédente.

Visualisations :

- courbe d'évolution quotidienne/hebdomadaire ;
- histogramme par centre/bacenta ;
- répartition des statuts ;
- tableau des responsables avec attendu, soumis, en retard, taux et niveau.

Toujours afficher le numérateur et le dénominateur avec le pourcentage. Exclure les occurrences annulées du dénominateur et préciser les filtres actifs. Prévoir pagination et limites SQL pour les grands volumes.

### B. Checklist Viewer

Une ligne par occurrence attendue, pas une ligne par utilisateur arbitraire :

| Colonne | Contenu |
|---|---|
| Date | Date de la réunion |
| Structure | Centre ou bacenta |
| Responsable | Responsable attribué à la date |
| Échéance | Heure limite |
| Statut | État calculé |
| Rapport | Lien vers le rapport si disponible |
| Rappel | Dernier rappel et canal |
| Action | Voir le rapport, relancer manuellement, annuler si autorisé |

Fonctions : filtres, recherche, pagination, export CSV si les exports sont déjà supportés, vue « mes échéances » et vue globale pour les administrateurs. Les actions doivent vérifier les autorisations côté serveur, pas seulement masquer les boutons.

### C. Notation et félicitations

Afficher séparément trois mesures :

1. **Hebdomadaire** : rapports soumis / rapports attendus durant la semaine ISO ;
2. **Mensuelle** : rapports soumis / rapports attendus durant le mois civil ;
3. **Cumulée** : rapports soumis / rapports attendus depuis la date d'entrée en responsabilité (ou depuis la date de mise en service du suivi).

La mesure principale de régularité est le taux de réalisation, et le nombre brut de rapports reste affiché pour donner le contexte.

Barème initial proposé, modifiable par l'administration :

| Taux de réalisation | Niveau | Exemple de message |
|---:|---|---|
| 100 % | Exemplaire | « Félicitations ! Toutes vos échéances ont été respectées. » |
| 90 à moins de 100 % | Excellent | « Excellent engagement, continuez ainsi ! » |
| 75 à moins de 90 % | Très bien | « Bravo pour votre régularité et vos efforts. » |
| 50 à moins de 75 % | À encourager | « Votre progression est encourageante, poursuivez vos efforts. » |
| 0 à moins de 50 % | À accompagner | « Nous vous encourageons à reprendre un rythme régulier. » |

Règles :

- stocker les seuils sous forme de bornes sans chevauchement, valider les intervalles de 0 à 100 ;
- gérer explicitement le cas « aucune échéance » : afficher « Non évalué », jamais 0 % ;
- les félicitations doivent être positives, respectueuses et non humiliantes ;
- ne pas assimiler volume brut et qualité du rapport ;
- enregistrer le barème/version utilisé pour chaque période évaluée afin que les changements de configuration ne réécrivent pas l'historique ;
- éviter de recalculer et renvoyer la même félicitation à chaque consultation ;
- définir si le responsable reçoit une notification à chaque changement de niveau ou uniquement à la clôture d'une période.

### D. Rappels automatiques in-app et email

Canaux retenus : notifications internes via `NotificationService` et email via `MailService`.

Événements initiaux :

1. veille de réunion : rappel préventif ;
2. après l'heure limite : rappel si rapport absent ;
3. lendemain : relance si rapport toujours absent.

Le moteur doit :

- calculer les occurrences à partir du calendrier et des affectations de responsables ;
- ignorer les occurrences annulées ;
- vérifier l'existence du rapport juste avant l'envoi ;
- vérifier les préférences globales et individuelles de canal ;
- ignorer les emails absents/non valides et enregistrer le motif ;
- journaliser succès, échec, destinataire, canal, date, type et identifiant d'occurrence ;
- utiliser une clé d'idempotence unique pour empêcher les doublons ;
- appliquer un délai progressif ou une politique de nouvelle tentative aux erreurs temporaires ;
- ne jamais envoyer un email en boucle si le service mail est indisponible ;
- fournir un mode simulation/dry-run pour les tests et la recette.

Les emails doivent contenir le nom de la structure, la date concernée, la date/heure limite, un lien authentifié vers le formulaire et une formulation courtoise. Ne jamais inclure de données personnelles ou de contenu du rapport dans l'objet du mail.

## 5. Modèle de données proposé

Les noms définitifs doivent suivre les conventions SQL du dépôt et être confirmés après lecture des migrations existantes.

### 5.1 `rapport_occurrences`

Table de référence des échéances attendues.

Champs proposés :

- `id` ;
- `scope_type` ENUM ou VARCHAR contrôlé : `centre` / `bacenta` ;
- `scope_id` ;
- `report_date` DATE ;
- `due_at` DATETIME ;
- `responsible_user_id` ;
- `status` ;
- `cancelled_at`, `cancelled_by`, `cancellation_reason` ;
- `created_at`, `updated_at`.

Contrainte unique : (`scope_type`, `scope_id`, `report_date`). Index sur (`report_date`, `status`), `responsible_user_id` et `due_at`.

Si plusieurs responsables doivent avoir une attribution individuelle, créer une table de liaison `rapport_occurrence_responsables` plutôt que multiplier les occurrences.

### 5.2 `rapport_reminder_logs`

Journal d'envoi et idempotence :

- `id`, `occurrence_id`, `recipient_user_id` ;
- `reminder_type` : préventif, échéance, relance, manuel ;
- `channel` : inapp/email ;
- `status` : pending/sent/failed/skipped ;
- `idempotency_key` UNIQUE ;
- `attempt_count`, `last_error` (message technique expurgé) ;
- `sent_at`, `created_at`.

Prévoir une contrainte unique logique sur occurrence + destinataire + type + canal. Ne pas stocker de contenu sensible inutile.

### 5.3 `rapport_scoring_rules`

Configuration des barèmes :

- `id`, `name`, `period_type` ;
- `min_rate`, `max_rate`, `level_key`, `label`, `message_template` ;
- `is_active`, `sort_order`, `created_by`, horodatages.

Validation serveur : seuils cohérents, sans chevauchement, couverture de la plage attendue. Garder un historique de versions ou une copie des règles appliquées dans les résultats.

### 5.4 `rapport_scoring_results`

Résultats historisés par responsable et période :

- `user_id`, `period_type`, `period_start`, `period_end` ;
- `expected_count`, `submitted_count`, `on_time_count`, `late_count` ;
- `completion_rate`, `punctuality_rate`, `level_key`, `message_snapshot` ;
- `rules_version`, `calculated_at`.

Contrainte unique sur utilisateur + type de période + début/fin. Le résultat peut être recalculé de manière idempotente, mais toute version notifiée doit conserver sa trace.

### 5.5 `rapport_notification_preferences` (facultatif pour V1)

Préférences par responsable : activation in-app/email, choix de rappel, email de destination si le modèle utilisateur permet une adresse vérifiée. Les paramètres administratifs globaux restent prioritaires.

### 5.6 Calendrier des centres

Ne pas ajouter immédiatement des colonnes redondantes. Réutiliser `bacentas.jours_semaine`, `heure_debut`, `heure_fin`. Si les centres ont un calendrier distinct, créer une configuration explicite ou réutiliser un modèle de planning commun. Documenter les valeurs de jours et stocker les heures dans un format SQL cohérent.

### 5.7 Intégrité et suppression

Les tables de suivi doivent respecter les conventions de clés étrangères du projet. Pour les références polymorphes `scope_type/scope_id`, contrôler l'existence de la cible dans le service métier. Définir le comportement à la suppression d'un centre, d'un bacenta ou d'un utilisateur : archivage/anonymisation logique recommandé plutôt qu'effacement silencieux des historiques.

## 6. Services et responsabilités applicatives

Créer des services spécialisés, avec dépendances injectables et repositories dédiés :

- `RapportOccurrenceService` : génération et synchronisation des échéances ;
- `RapportAnalyticsService` : agrégats et statistiques par période/périmètre ;
- `RapportScoringService` : calcul, application du barème et historisation ;
- `RapportReminderService` : sélection des rappels dus, envoi et idempotence ;
- `RapportDashboardService` : orchestration des données d'écran si nécessaire.

Repositories possibles :

- `RapportOccurrenceRepository` ;
- `RapportReminderRepository` ;
- `RapportScoringRepository` ;
- étendre `RapportJourRepository` seulement pour les requêtes ciblées qui lui appartiennent.

Ne pas placer de SQL complexe dans les contrôleurs. Les services ne doivent pas dépendre de variables globales de session pour leurs règles métier ; passer explicitement l'identité et le périmètre autorisé.

## 7. Routes, permissions et écrans

Routes indicatives à adapter au routeur actuel :

- `rapport_dashboard` : dashboard ;
- `rapport_checklist` : checklist ;
- `rapport_classement` : classement ;
- `rapport_responsable` : fiche de suivi ;
- `rapport_parametres` : barèmes, horaires et rappels ;
- actions POST protégées par CSRF : sauvegarder les réglages, relancer, annuler une occurrence.

Permissions recommandées :

- **Administrateur** : toutes les structures, configuration, historique, export et relance ;
- **Responsable de centre** : périmètre de son centre et bacentas autorisés selon les responsabilités réelles ;
- **Responsable de bacenta** : ses échéances et son historique ;
- **Autres utilisateurs** : aucun accès aux données de suivi nominatives, sauf permission déjà définie par le système.

Les contrôles doivent être effectués côté serveur pour chaque consultation et chaque action. Réutiliser les helpers d'autorisation du projet, sans introduire une permission plus large que `auth_can_report_for_centre` ou les règles de responsabilité existantes.

## 8. Tâche planifiée (cron)

Créer une commande CLI dédiée, par exemple `php bin/console rapports:process-reminders` selon les conventions découvertes à la phase 0.

Fréquence recommandée : toutes les 5 à 15 minutes. La commande :

1. acquiert un verrou pour éviter deux exécutions simultanées ;
2. synchronise les occurrences de la fenêtre utile ;
3. sélectionne les rappels dus ;
4. vérifie à nouveau l'absence du rapport ;
5. crée les lignes d'idempotence avant ou pendant l'envoi selon la stratégie transactionnelle ;
6. envoie la notification in-app et l'email indépendamment ;
7. journalise chaque résultat ;
8. libère le verrou et expose un résumé exploitable dans les logs.

Le cron ne doit pas être déclenché par une requête HTTP publique. Si l'hébergement ne permet pas un cron fréquent, utiliser le minimum permis par l'hébergeur et rendre les fenêtres de rappel tolérantes aux retards. Documenter la commande de test, la configuration serveur et le fuseau horaire.

## 9. Stratégie de calcul et performance

- Les périodes hebdomadaires utilisent les semaines ISO (lundi à dimanche), sauf décision métier contraire.
- Les mois sont des mois civils dans `Africa/Libreville`.
- Les périodes sont calculées en date locale métier ; convertir les instants avec un fuseau explicite.
- Les résultats doivent distinguer attendu, soumis, ponctuel et tardif.
- Un rapport existant satisfait une occurrence si la cible et la date correspondent ; confirmer la règle en présence de plusieurs rapports ou d'une correction.
- Préférer des agrégats SQL et indexés plutôt que charger toutes les lignes PHP.
- Paginer les listes et limiter les plages de dates par défaut.
- Mettre en cache uniquement les agrégats non sensibles si le projet possède déjà un mécanisme adapté ; invalider après sauvegarde d'un rapport ou changement de calendrier.
- Les tableaux de bord doivent rester exacts même si la tâche cron est en retard : l'état peut être dérivé à la lecture à partir de l'échéance et de l'existence du rapport.

## 10. Sécurité, confidentialité et audit

- CSRF sur toutes les actions de mutation ;
- validation stricte des dates, périodes, seuils et identifiants ;
- requêtes paramétrées ;
- contrôle de périmètre au niveau du service/repository ;
- échappement des messages et noms dans les vues ;
- ne pas exposer les emails ou résultats individuels à des utilisateurs non autorisés ;
- journaliser les modifications de barèmes, calendriers, annulations et relances manuelles ;
- protéger les liens de rapport par authentification et autorisation ;
- ne jamais considérer un lien reçu par email comme une autorisation ;
- ne pas envoyer de rappel à une personne qui n'était pas responsable à la date concernée.

## 11. Plan de réalisation par phases

### Phase 0 — Audit et décisions métier

- [ ] Cartographier migrations, schéma SQL et index existants.
- [ ] Lire intégralement `RapportJourService`, `RapportJourRepository`, `MailService`, `NotificationService`, les contrôleurs d'administration et le routeur.
- [ ] Vérifier la gestion des responsables principaux/suppléants et les dates d'affectation.
- [ ] Vérifier calendrier des centres et bacentas.
- [ ] Vérifier configuration mail et tâches planifiées de l'hébergement.
- [ ] Faire valider les règles de responsabilité, les heures limites et les messages.
- **Livrable :** note d'audit et décisions métier, sans migration destructive.

### Phase 1 — Modèle de données et occurrence attendue

- [ ] Écrire les migrations réversibles pour occurrences et journaux.
- [ ] Ajouter les index et contraintes d'unicité.
- [ ] Développer génération idempotente des occurrences.
- [ ] Gérer changement de calendrier et annulation avec audit.
- [ ] Associer le responsable actif à la date de l'occurrence.
- [ ] Tester les dates limites, changements de semaine/mois et absence de calendrier.
- **Livrable :** échéancier fiable et testable.

### Phase 2 — Checklist et statut des rapports

- [ ] Relier chaque occurrence à un rapport existant par cible et date.
- [ ] Implémenter les états à venir, à remplir, en retard, rempli à temps/tard, annulé.
- [ ] Développer le repository de checklist avec filtres et pagination.
- [ ] Créer la page checklist et la vue « mes échéances ».
- [ ] Ajouter liens vers formulaire et rapport existant.
- **Livrable :** visibilité opérationnelle des rapports attendus et manquants.

### Phase 3 — Notifications automatiques

- [ ] Créer la commande CLI et le verrou d'exécution.
- [ ] Implémenter les trois fenêtres de rappel configurables.
- [ ] Ajouter idempotence par occurrence, destinataire, type et canal.
- [ ] Intégrer `NotificationService` pour in-app.
- [ ] Intégrer `MailService` pour email.
- [ ] Ajouter gestion des échecs, retries et mode dry-run.
- [ ] Configurer le cron de production et documenter son installation.
- **Livrable :** rappels in-app/email fiables, sans doublons.

### Phase 4 — Barème et félicitations

- [ ] Ajouter configuration des seuils et messages.
- [ ] Développer calcul hebdomadaire, mensuel et cumulé.
- [ ] Calculer taux de réalisation et ponctualité.
- [ ] Historiser les résultats et la version du barème.
- [ ] Afficher le niveau et le message dans la fiche responsable.
- [ ] Décider si une notification de félicitations est envoyée à la clôture ou au changement de niveau.
- **Livrable :** système de motivation configurable et explicable.

### Phase 5 — Dashboard et classement

- [ ] Développer agrégats SQL et filtres par périmètre.
- [ ] Créer les cartes KPI, graphiques et tableau comparatif.
- [ ] Ajouter classement avec règle de départage documentée.
- [ ] Afficher les dénominateurs et les périodes exactes.
- [ ] Ajouter liens depuis KPI vers la checklist filtrée.
- [ ] Vérifier les performances sur un volume représentatif.
- **Livrable :** pilotage global et individuel.

### Phase 6 — Paramètres, recette et déploiement

- [ ] Écran admin de configuration des seuils, messages, heures et canaux.
- [ ] Journal d'audit des changements.
- [ ] Tests de permissions sur chaque rôle et périmètre.
- [ ] Recette fonctionnelle avec responsables de centre et de bacenta.
- [ ] Déployer migrations, cron et configuration mail.
- [ ] Surveiller les logs et les premières échéances.
- [ ] Prévoir rollback applicatif et migrations réversibles.
- **Livrable :** mise en production maîtrisée.

## 12. Stratégie de tests (TDD)

### Tests unitaires

- calcul du taux avec 0 occurrence ;
- seuils exactement égaux à 50, 75, 90 et 100 % ;
- semaine ISO traversant deux mois/années ;
- mois de 28, 29, 30 et 31 jours ;
- responsable arrivé ou parti en cours de période ;
- occurrence annulée exclue du dénominateur ;
- rapport soumis à l'heure limite, après l'heure limite, ou corrigé ;
- génération répétée d'occurrences sans doublons ;
- message de félicitation stable pour une même version de barème.

### Tests d'intégration

- rapport de centre et rapport de bacenta ;
- responsable principal et suppléant ;
- envoi in-app réussi/email en échec et inversement ;
- email absent ou invalide ;
- cron exécuté deux fois simultanément ;
- rapport soumis entre sélection et envoi du rappel ;
- changement du jour de réunion ;
- annulation d'une occurrence ;
- historique après modification du barème ;
- accès refusé hors périmètre.

### Tests de sécurité

- identifiants de centre/bacenta manipulés ;
- accès direct à une fiche d'un autre responsable ;
- CSRF absent ou invalide ;
- tentative de relance d'une occurrence non autorisée ;
- injection SQL et XSS dans les filtres/messages ;
- lien email utilisé sans session valide.

### Critères d'acceptation

- Une occurrence prévue apparaît une seule fois dans la checklist.
- Une occurrence non prévue n'est jamais comptée comme manquante.
- Un rapport valide ferme les rappels futurs de son occurrence.
- Une exécution répétée du cron ne crée pas de doublon.
- Les canaux in-app et email échouent indépendamment.
- Le responsable voit ses propres indicateurs ; l'administration voit le périmètre autorisé.
- Les statistiques hebdomadaires, mensuelles et cumulées sont reproductibles et explicables.
- Un changement de barème n'altère pas les résultats historisés.
- Les rapports et écrans existants continuent de fonctionner.
- Le système signale clairement les données manquantes (email, calendrier, responsable) au lieu de les deviner.

## 13. Proposition de navigation

Dans le menu « Rapport du Jour », regrouper :

- **Rapports** : liste et formulaire actuels ;
- **Suivi / Checklist** : échéances et statuts ;
- **Dashboard** : synthèse et évolution ;
- **Classement & félicitations** : résultats individuels et classement ;
- **Paramètres** (administrateurs uniquement) : barème, messages, rappels et calendrier.

Ne pas exposer le classement nominatif à tous les utilisateurs par défaut. Les vues doivent respecter les permissions existantes.

## 14. Décisions restant à valider avant le code

1. **Centre :** un rapport est-il attendu à chaque réunion du centre, en plus des rapports des bacentas ?
2. **Responsable :** si plusieurs responsables sont affectés, lequel reçoit le crédit et les rappels ?
3. **Échéance :** confirmer la règle « fin de réunion + 30 minutes » ou définir une heure limite fixe.
4. **Relances :** confirmer veille 18 h, jour même après réunion et lendemain 09 h.
5. **Félicitations :** notification au responsable à chaque nouveau niveau ou seulement bilan hebdomadaire/mensuel ?
6. **Classement :** visible uniquement aux administrateurs/responsables autorisés ou publication plus large ?
7. **Cumul :** depuis la date de prise de responsabilité ou depuis le lancement du nouveau système ?

En l'absence de validation, retenir les valeurs proposées comme valeurs initiales configurables, mais ne pas les figer dans le code.

## 15. Ordre de priorité recommandé

1. occurrence attendue + checklist (socle de vérité) ;
2. rappels automatiques (in-app et email) ;
3. barème et historique des félicitations ;
4. dashboard et classement ;
5. réglages avancés et exports.

**Règle directrice :** la qualité de ce module dépend d'abord de la justesse des échéances attendues et de l'attribution au bon responsable. Les scores et rappels ne doivent être activés qu'après validation de ce socle.


---

## 16. Décisions métier validées — version consolidée

Les décisions ci-dessous ont été confirmées par le commanditaire. Elles **remplacent toute proposition contradictoire** des sections précédentes et constituent les règles de référence pour l'implémentation.

### 16.1 Une occurrence pour chaque réunion de centre et de bacenta

- Chaque réunion planifiée d'un centre génère une occurrence de rapport de type `centre`.
- Chaque réunion planifiée d'un bacenta génère une occurrence de rapport de type `bacenta`.
- Les deux types d'occurrences sont suivis indépendamment, y compris lorsqu'une réunion de centre et une réunion de bacenta ont lieu le même jour.
- Une réunion de centre ne remplace pas les rapports attendus de ses bacentas, et inversement.
- Le calendrier propre à chaque centre et bacenta doit être administrable et explicite. Le calendrier des centres doit être ajouté si le modèle actuel ne le permet pas.
- La génération doit être idempotente : une réunion donnée ne produit qu'une occurrence de rapport pour sa cible et sa date.

### 16.2 Tous les responsables sont notifiés, un seul reçoit le crédit

- Tous les responsables actifs affectés à la cible à la date de l'occurrence reçoivent les rappels in-app et email, selon les canaux activés et la disponibilité de leurs coordonnées.
- Le crédit de réalisation est attribué exclusivement au **premier responsable dont la soumission valide est enregistrée avec succès** pour cette occurrence.
- L'attribution doit être atomique en base de données : en cas de soumissions concurrentes, une transaction et une contrainte/stratégie de verrouillage garantissent un seul gagnant.
- Enregistrer séparément l'identité de l'auteur de la soumission et l'identité du responsable crédité. Si un administrateur ou un tiers autorisé soumet le rapport au nom d'un responsable, ne pas lui attribuer automatiquement le crédit : appliquer les règles explicites de soumission « pour le compte de ».
- Les autres responsables voient l'occurrence comme remplie, mais ne reçoivent pas de crédit pour cette occurrence. Leurs statistiques doivent afficher clairement « rapport soumis par un autre responsable » plutôt que comptabiliser l'échéance comme un échec individuel.
- Tous les rappels futurs de l'occurrence sont annulés pour tous les destinataires dès qu'un rapport valide est enregistré. Les messages déjà envoyés restent dans l'historique.

**Conséquence sur le modèle :** remplacer le champ unique `responsible_user_id` dans `rapport_occurrences` par une table de liaison, par exemple `rapport_occurrence_responsables`, qui conserve tous les responsables concernés et leur statut de notification. L'occurrence porte un seul `credited_user_id` et un seul `submitted_by_user_id` (ou références équivalentes), fixés de manière atomique.

### 16.3 Heure limite fixe

- Ne pas calculer l'échéance comme « fin de réunion + 30 minutes ».
- Ajouter une heure limite fixe configurable pour chaque calendrier de centre/bacenta, avec possibilité de valeur par défaut globale.
- L'heure limite est enregistrée sur l'occurrence au moment de sa génération afin qu'une modification ultérieure des paramètres ne change pas rétroactivement les échéances déjà créées.
- La date et l'heure locales sont interprétées dans le fuseau `Africa/Libreville`.
- Si aucune heure limite n'est configurée, signaler une erreur de configuration à l'administration et ne pas inventer une heure.

### 16.4 Calendrier des rappels confirmé

Pour chaque occurrence non remplie :

1. **La veille à 18 h** : rappel préventif à tous les responsables concernés.
2. **Le jour de la réunion, après l'heure limite fixe** : rappel d'échéance à tous les responsables concernés si le rapport n'est toujours pas soumis.
3. **Le lendemain à 09 h** : relance à tous les responsables concernés si le rapport reste absent.

Les heures sont celles du fuseau `Africa/Libreville`. Chaque événement est configurable/désactivable par l'administration, et chaque couple occurrence + destinataire + type + canal est protégé contre les doublons. La soumission du rapport annule tous les rappels non encore envoyés.

### 16.5 Félicitations et bilans périodiques

- Envoyer une félicitation au responsable crédité lorsqu'il atteint un nouveau niveau de barème.
- Générer et communiquer un bilan hebdomadaire et un bilan mensuel aux responsables concernés, avec leurs nombres attendus, soumis, réalisés à temps, en retard, leur taux et leur message.
- Les bilans hebdomadaires utilisent les semaines ISO ; les bilans mensuels utilisent les mois civils.
- Un changement de niveau ne doit produire qu'une seule notification pour la période et la version de barème concernées.
- Les messages, seuils, calendrier d'envoi des bilans et canaux de communication doivent être configurables.
- Les responsables qui n'ont aucune occurrence attendue dans la période reçoivent un statut « Non évalué », et non une note de 0 %.
- Les félicitations et bilans ne doivent jamais divulguer les résultats nominatifs d'autres responsables.

### 16.6 Classement à accès restreint

Le classement nominatif et les résultats détaillés ne sont visibles que par les administrateurs et les responsables disposant explicitement d'une permission adaptée à leur périmètre. Aucun classement public ou accessible à tous les membres n'est prévu. Les responsables de périmètre ne voient que les données relevant de leurs autorisations.

### 16.7 Cumul depuis la prise de responsabilité

- Le cumul individuel commence à la date effective de début de responsabilité, issue de l'historique des affectations.
- Les occurrences antérieures à cette date ne sont jamais incluses dans son dénominateur.
- En cas de changement de structure ou de fonction, conserver les périodes d'affectation et calculer les résultats en fonction des responsabilités réellement exercées.
- Si l'historique des affectations n'existe pas ou est incomplet, prévoir une date de départ initiale validée par un administrateur ; ne pas déduire ou inventer cette date.
- La date de lancement du nouveau module ne doit pas remplacer la date de prise de responsabilité lorsqu'une date historique fiable est disponible. Si les données historiques sont absentes, documenter la limite et appliquer une date de départ explicitement validée.

### 16.8 Mise à jour obligatoire du schéma et des tests

Le plan d'implémentation doit être ajusté comme suit :

- Ajouter un calendrier et une heure limite fixe pour les centres, et vérifier la configuration équivalente pour les bacentas.
- Ajouter `rapport_occurrence_responsables` pour tous les destinataires d'une occurrence.
- Ajouter un mécanisme atomique d'attribution du crédit au premier dépôt valide.
- Distinguer auteur, responsable crédité et responsables simplement notifiés.
- Ajouter des états de suivi individuel permettant de distinguer « crédit obtenu », « rapport soumis par un autre responsable » et « rapport non soumis ».
- Ajouter les tests de concurrence : deux responsables soumettent simultanément, un seul reçoit le crédit.
- Ajouter les tests garantissant que les rapports centre et bacenta sont attendus séparément.
- Ajouter les tests de rappel multi-destinataires, d'annulation de tous les rappels après soumission, de bilan hebdomadaire/mensuel et de changement de niveau sans doublon.
- Ajouter les tests de cumul avec prise de responsabilité en cours de mois, changement de structure et historique incomplet.
- Restreindre explicitement les routes et requêtes du classement aux administrateurs et responsables autorisés.

**Ces règles validées prévalent sur les hypothèses provisoires des sections 3, 5, 11, 12 et 14.** La phase 0 doit maintenant auditer les données et conventions existantes en appliquant ces décisions ; elle ne doit pas redemander au commanditaire de trancher les points déjà validés.
