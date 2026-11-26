# Plan technique — PWA complète et synchronisation hors ligne

- **Projet :** La Belle Église Internationale Franceville
- **Dépôt :** \`DESMOND-77/labelleeglise\`
- **Branche de référence auditée :** \`main\`
- **Périmètre validé :** vitrine publique + espace de gestion
- **Mode hors connexion validé :** consultation et saisie hors ligne, puis synchronisation au retour du réseau
- **Statut :** spécification technique à implémenter — aucun code applicatif modifié par ce document

---

## 1. Objectifs et principes non négociables

La PWA doit permettre :
1. l'installation de la vitrine publique et de l'espace de gestion sur Android et desktop ;
2. la consultation hors ligne d'un contenu public explicitement autorisé et de ressources statiques ;
3. la création et la modification hors ligne de brouillons métier autorisés ;
4. la synchronisation fiable de ces opérations après reconnexion ;
5. le maintien strict des règles actuelles d'authentification, de rôles, de responsabilités et de périmètres ;
6. la protection des données personnelles, pastorales, financières et administratives sur les appareils, notamment partagés.

**Règle fondamentale :** le mode hors ligne n'est jamais un contournement de l'autorisation serveur. Une opération mise en attente n'est ni validée ni enregistrée en base tant que le serveur ne l'a pas authentifiée, autorisée, validée et commitée.

La synchronisation ne doit pas être une mise en file automatique de tous les formulaires POST. Chaque formulaire doit être audité et explicitement déclaré compatible ou non.

## 2. État initial constaté dans le dépôt

Architecture et contraintes observées sur \`main\` :

- Application PHP sans framework, sans Composer, avec autoloader maison.
- Front controller \`index.php\`, initialisation \`Bootstrap/init.php\`, routes déclarées dans \`Routes/web.php\`.
- Contrôleurs, services, repositories et vues séparés ; base MySQL/MariaDB via PDO.
- Actions d'écriture centralisées dans \`app/Controllers/ActionsController.php\`, dispatchées avant la vérification de session dans le front controller ; chaque action réévalue donc ses propres permissions.
- Contrôle CSRF global dans \`ActionsController::postAction()\` via \`check_csrf()\`.
- Sessions PHP avec cookies HttpOnly et SameSite=Lax ; Secure selon détection HTTPS dans \`Bootstrap/init.php\`.
- Racine du dépôt = racine web sur l'hébergement mutualisé ; \`.htaccess\` protège les dossiers internes et force HTTPS hors localhost.
- Manifeste existant : \`assets/images/site.webmanifest\`, référencé par le layout authentifié. Le layout public \`Views/layouts/public.php\` n'y fait pas référence.
- Le manifeste déclare déjà les icônes 192 et 512, \`display: standalone\`, mais contient une faute dans le nom, un chemin \`start_url\` relatif à corriger et une couleur de thème différente des layouts.
- Icônes/favicon et métadonnées de thème déjà présentes.
- Les layouts utilisent des chemins d'assets relatifs, ce qui doit être pris en compte pour les pages publiques profondes et l'application installée.
- Aucun Service Worker, aucun mécanisme IndexedDB de brouillons/synchronisation ni protocole de synchronisation dédié n'a été identifié dans le code audité.

Le projet est hébergé sur un modèle mutualisé Apache/PHP. La solution doit rester compatible avec ce modèle, sans exiger Node, un serveur WebSocket, un worker serveur permanent ou une infrastructure cloud additionnelle.

## 3. Architecture cible

### 3.1 Composants côté navigateur

- **Web App Manifest** : identité, icônes, couleurs, scope et URL de démarrage.
- **Service Worker à la racine** : contrôle des caches, shell hors ligne, gestion de versions et signalement des mises à jour.
- **Cache Storage** : uniquement les ressources statiques publiques et les pages publiques strictement autorisées.
- **IndexedDB** : brouillons et opérations métier en attente, métadonnées de synchronisation, états et versions.
- **Web Crypto API** : chiffrement des données locales sensibles avant stockage persistant.
- **Client de synchronisation** : synchronisation à l'ouverture, au retour en ligne, à la reprise de visibilité et, si disponible, via Background Sync.
- **Indicateur d'état réseau** : hors ligne, brouillon local, en attente, synchronisation, synchronisé, conflit, échec définitif.

### 3.2 Composants côté serveur

- Front controller PHP existant.
- Routes JSON versionnées dédiées à la synchronisation.
- Authentification PHP existante et contrôle CSRF adapté aux appels JSON.
- Service de synchronisation métier qui réutilise les services/repositories existants.
- Table d'idempotence des opérations et journal minimal des résultats.
- Contrôle des permissions et du périmètre au moment de la synchronisation.
- Validation serveur, transactions SQL et gestion explicite des conflits.

### 3.3 Flux général

1. L'utilisateur remplit un formulaire compatible.
2. Si le réseau est disponible, l'application suit le flux en ligne normal, sauf si le formulaire utilise explicitement le protocole de synchronisation.
3. Si le réseau est indisponible, le formulaire enregistre un brouillon local chiffré.
4. L'interface confirme **« Brouillon enregistré sur cet appareil — en attente de synchronisation »**, jamais « enregistré » ou « validé ».
5. Au retour du réseau, le client demande une session/autorisation valide et un jeton CSRF frais.
6. Il envoie les opérations en attente avec un identifiant idempotent.
7. Le serveur réauthentifie, réautorise, revalide, vérifie les conflits puis committe chaque opération.
8. Le client marque l'opération synchronisée seulement après accusé de réception serveur vérifiable.
9. En cas de conflit, l'utilisateur voit les valeurs serveur et locales et choisit explicitement comment résoudre, selon les règles métier.

## 4. Manifest, URLs et périmètre d'installation

### 4.1 Manifeste

Faire évoluer \`assets/images/site.webmanifest\` :

- corriger le nom de marque et la description ;
- définir \`id\`, \`start_url\`, \`scope\`, \`display: standalone\` et éventuellement \`display_override\` ;
- utiliser des URLs absolues ou correctement résolues à partir de la racine de déploiement ;
- harmoniser \`theme_color\` avec les deux layouts ;
- conserver les icônes 192×192 et 512×512, vérifier leurs dimensions réelles et leur zone de sécurité maskable ;
- ajouter une icône Apple Touch 180×180 dédiée si nécessaire ;
- ne pas forcer l'orientation portrait : l'application doit rester utilisable en portrait et paysage, ainsi que sur desktop ;
- ajouter les captures d'écran et raccourcis uniquement si les écrans et URLs sont stables.

### 4.2 Vitrine et gestion

Un seul manifeste et un seul Service Worker peuvent couvrir les deux parties si le domaine et la racine sont communs, ce qui correspond à l'architecture actuelle.

- \`start_url\` : entrée stable de l'application ; une redirection vers connexion est acceptable si aucune session n'existe.
- \`scope\` : racine de l'application.
- Les routes publiques restent accessibles sans session.
- Les pages de gestion restent protégées par les contrôles PHP existants.
- Le layout public doit référencer le manifeste, les icônes et le même thème.
- Les pages profondes ne doivent pas casser les URLs CSS/JS ; utiliser les helpers d'assets ou des chemins racine cohérents.
- Vérifier le comportement sous le vrai sous-répertoire éventuel de déploiement : ne pas supposer que le site est toujours à \`/\`.

### 4.3 Service Worker racine

Créer par exemple \`/sw.js\` à la racine web pour obtenir le scope nécessaire à la vitrine et à la gestion. Le fichier doit être servi avec un type JavaScript correct et ne doit pas être placé dans \`assets/js/\` si cela limite son scope.

Enregistrement dans les deux layouts, avec détection de support et gestion d'erreur silencieuse côté UX. Le Service Worker ne doit jamais empêcher l'application classique de fonctionner si l'API PWA n'est pas disponible.

## 5. Stratégies de cache et cloisonnement des données

### 5.1 Cache autorisé

- CSS, JavaScript, polices, logos, icônes et images statiques publiques versionnées.
- Page statique de secours hors ligne.
- Pages publiques GET explicitement allowlistées, uniquement si elles sont anonymes, sans personnalisation, sans jeton, sans donnée privée et avec réponse compatible cache.
- Ressources publiques CMS uniquement après vérification de leur visibilité et de l'absence de contenu privé.

Stratégie proposée :
- ressources statiques versionnées : Cache First ;
- pages publiques autorisées : Network First avec timeout raisonnable puis dernière copie publique connue ;
- navigation non autorisée / inconnue : réseau uniquement puis page hors ligne générique.

### 5.2 Cache interdit

Ne jamais mettre en cache :
- pages de gestion authentifiées ;
- réponses contenant les données d'un utilisateur, d'un membre ou d'un responsable ;
- données de finances/offrandes, rapports, présences, suivi hebdomadaire, notifications et inscriptions ;
- réponses JSON privées ;
- réponses avec cookies, en-têtes d'authentification ou jetons CSRF ;
- pages de connexion personnalisées ou réponses de session ;
- résultats d'export, fichiers privés et uploads non publics.

Pour ces routes, utiliser \`Cache-Control: no-store, private\` côté serveur et une règle Service Worker Network Only. Le Service Worker ne doit pas cloner et stocker les réponses HTML authentifiées dans un cache « runtime ».

Ne jamais faire de cache global de toutes les requêtes GET ou de toutes les réponses réussies.

### 5.3 Purge et cloisonnement

- Les caches statiques ne contiennent aucune donnée utilisateur.
- À la déconnexion, vider les brouillons sensibles de l'utilisateur selon la politique de conservation choisie et supprimer les clés locales de déchiffrement associées.
- En cas de changement de compte, ne jamais afficher ni synchroniser la file du compte précédent sous le nouveau compte.
- Prévoir un écran de déconnexion si des opérations restent en attente : synchroniser avant de quitter si le réseau est disponible, ou demander confirmation explicite pour conserver/supprimer les brouillons locaux.
- Un compte différent ne peut jamais reprendre la file d'un autre compte sans mécanisme de déverrouillage et vérification d'identité explicites.
- Une politique d'expiration des brouillons locaux doit être configurable et affichée à l'utilisateur.

## 6. Stockage local et chiffrement

### 6.1 IndexedDB

Créer une base versionnée, par exemple \`lbe_pwa\`, avec des stores distincts :

- \`drafts\` : brouillons de formulaires non soumis ;
- \`outbox\` : opérations explicitement prêtes à être synchronisées ;
- \`sync_meta\` : curseurs, versions de protocole et dernier état de synchronisation ;
- \`crypto_meta\` : paramètres cryptographiques et identifiant de clé, sans secret en clair.

Chaque élément contient au minimum :
- \`operation_id\` UUID généré côté client ;
- \`user_id\` et \`account_scope\` comme étiquettes de cloisonnement, jamais comme preuve d'autorisation ;
- type d'entité et action allowlistés ;
- version de schéma du payload ;
- date locale de création et dernière modification ;
- état (draft, queued, syncing, synced, conflict, rejected) ;
- charge utile chiffrée ;
- nombre de tentatives et dernier code d'erreur non sensible.

Ne stocker ni mot de passe, ni cookie de session, ni jeton CSRF, ni token de connexion dans IndexedDB.

### 6.2 Chiffrement local

Les données personnelles ou financières en attente doivent être chiffrées avec AES-GCM via Web Crypto avant écriture IndexedDB. La clé ne doit jamais être envoyée au serveur.

Approche recommandée :
- générer une clé de chiffrement propre au compte et à l'installation ;
- la protéger par une phrase secrète/PIN hors ligne choisi par l'utilisateur, avec dérivation PBKDF2 et sel aléatoire ;
- utiliser un nonce/IV unique pour chaque chiffrement ;
- prévoir une procédure de déverrouillage avant consultation/synchronisation des brouillons ;
- effacer les clés et données sensibles à la révocation explicite ou à la suppression du compte local.

Une clé non extractible conservée seule dans IndexedDB protège principalement contre la lecture brute du stockage, pas contre un XSS exécuté sur la même origine. Le chiffrement local ne remplace donc ni la prévention XSS, ni CSP, ni la sécurité du terminal. Le PIN ne doit pas être présenté comme une protection contre un appareil compromis.

Si le produit choisit une expérience sans PIN, il faut documenter le niveau de protection réduit et limiter strictement les données conservées. La décision doit être validée avant implémentation.

### 6.3 Appareils partagés

La PWA de gestion peut être installée sur un téléphone utilisé par plusieurs bergers/responsables. Il faut :
- cloisonner chaque file par compte ;
- exiger le déverrouillage du coffre local pour lire les brouillons ;
- masquer le contenu sensible lorsque l'application passe en arrière-plan si possible ;
- proposer « Effacer les données hors ligne de ce compte » ;
- supprimer automatiquement les données locales après déconnexion selon la politique retenue ;
- ne jamais supposer que le verrouillage écran de l'appareil suffit.

## 7. Protocole de synchronisation serveur

### 7.1 Routes dédiées

Ne pas réutiliser directement le POST HTML historique comme API hors ligne. Ajouter des endpoints JSON versionnés, par exemple :

- \`POST /index.php?api=pwa/v1/sync\` : envoyer un lot d'opérations ;
- \`GET /index.php?api=pwa/v1/bootstrap\` : récupérer les capacités, versions de schéma et jeton CSRF frais, sous session valide ;
- \`GET /index.php?api=pwa/v1/status\` : vérifier session, identité et capacités de synchronisation.

Le choix final des routes doit respecter le routeur maison et ne pas introduire un second front controller. Réponses JSON normalisées, codes HTTP cohérents, \`Cache-Control: no-store\`.

### 7.2 Format d'une opération

Chaque opération contient :
- \`operation_id\` UUID ;
- \`type\` et \`action\` dans une liste blanche serveur ;
- \`schema_version\` ;
- \`entity_id\` si modification ;
- \`base_version\` ou \`updated_at\` serveur connu ;
- \`payload\` validé côté client pour UX, mais toujours revalidé côté serveur ;
- \`created_at_client\` informatif uniquement, jamais utilisé comme horloge d'autorité.

Le serveur renvoie pour chaque opération : \`accepted\`, \`duplicate\`, \`conflict\`, \`validation_error\`, \`unauthorized\` ou \`retryable_error\`, avec un identifiant de corrélation et un message non sensible.

### 7.3 Idempotence

Créer une table d'idempotence, par exemple \`pwa_sync_operations\`, avec :
- identifiant UUID unique ;
- utilisateur émetteur ;
- type/action ;
- empreinte du payload ;
- état final ;
- identifiant de l'entité créée/modifiée ;
- date serveur ;
- code de résultat.

Ajouter une contrainte UNIQUE sur \`operation_id\`. Même opération rejouée : retourner le résultat précédent sans exécuter une seconde fois l'action. Si le même UUID est reçu avec un payload différent, rejeter comme anomalie.

Chaque opération métier doit être transactionnelle : idempotence, mutation métier et enregistrement du résultat doivent être commités ensemble.

### 7.4 Autorisation et CSRF

À chaque synchronisation :
- exiger une session active pour les opérations privées ;
- renouveler/obtenir un jeton CSRF frais en ligne ; ne jamais mettre en file un ancien jeton ;
- vérifier origine/Host et méthode HTTP ;
- contrôler le rôle, la permission, la responsabilité et le périmètre sur les données relues en base ;
- recharger les entités et leurs relations depuis le serveur ;
- valider tous les champs, tailles, types, états et transitions ;
- ne jamais accepter \`user_id\`, \`centre_id\`, \`bacenta_id\`, rôle ou crédit comme autorité fournie par le client ;
- appliquer les mêmes règles métier que les actions en ligne existantes.

Pour les opérations publiques (ex. demande d'inscription), définir une route et un schéma séparés, avec anti-spam, limitation de débit, validation et vérification email habituelles. Aucune donnée publique ne doit pouvoir déclencher une opération administrative.

### 7.5 Réauthentification et révocation

Si la session expire, si le compte est désactivé ou si les permissions ont changé :
- arrêter la synchronisation ;
- ne pas supprimer silencieusement les brouillons ;
- demander reconnexion/déverrouillage ;
- revalider chaque opération avec les permissions actuelles ;
- refuser les actions devenues interdites et expliquer la marche à suivre.

## 8. Conflits, ordre et fiabilité

Ne pas appliquer une règle globale « dernier écrit gagne » : elle pourrait écraser une saisie financière ou une présence.

- **Création** : idempotence par UUID.
- **Modification** : comparaison de \`base_version\`/version serveur ; conflit si l'entité a changé depuis la lecture.
- **Champs indépendants** : fusion automatique seulement si les règles métier démontrent qu'elle est sûre.
- **Champs financiers, présences, rapports et décisions sensibles** : conflit explicite ou validation métier, jamais écrasement silencieux.
- **Suppression** : pas de suppression hors ligne au départ ; prévoir confirmation en ligne uniquement.
- **Ordre** : préserver l'ordre des opérations dépendantes sur une même entité ; ne pas bloquer les autres entités indépendantes.
- **Retry** : backoff exponentiel avec jitter, plafond et arrêt sur erreurs permanentes.
- **Réseau instable** : chaque opération reçoit un accusé distinct ; un lot partiellement traité doit pouvoir être rejoué sans doublon.
- **Horodatage** : l'heure serveur fait foi ; l'heure client sert à l'affichage et au diagnostic uniquement.

L'interface doit présenter les conflits avec les valeurs locales et serveur, et proposer « garder la version serveur », « reprendre ma saisie » ou une fusion champ par champ lorsque cela est autorisé.

## 9. Périmètre fonctionnel de la saisie hors ligne

La compatibilité doit être décidée formulaire par formulaire après inventaire des actions POST et de leurs effets secondaires.

### 9.1 Candidats à une première version

- Brouillons de formulaires de rapport du jour ; soumission après contrôle de la période, de l'occurrence, du responsable et des règles de crédit côté serveur.
- Saisie de suivi hebdomadaire personnel, avec validation du propriétaire et de la semaine au moment du commit.
- Brouillons d'événements/activités si les règles de permissions et de conflit sont maîtrisées.
- Demande publique d'inscription : brouillon local, puis envoi explicite une fois le réseau revenu ; vérification email et protections anti-abus inchangées.

### 9.2 À différer ou exclure de la première version

- Offrandes, finances, Budget Bus et toute opération de paiement/encaissement : risque de doublon et de divergence financière.
- Présences/pointages collectifs et opérations de masse : nécessitent un modèle de conflit et d'audit spécifique.
- Suppressions, révocations de responsabilités, activation/désactivation de comptes.
- Gestion des rôles, permissions et paramètres système.
- Uploads/photos/fichiers : pipeline séparé, taille, empreinte, reprise et sécurité à spécifier.
- Actions d'administration ou opérations ayant des notifications/email/effets externes non idempotents.

Une opération exclue reste utilisable en ligne ; elle affiche un message clair si l'utilisateur tente de la réaliser hors connexion.

### 9.3 États de l'interface

Afficher de manière uniforme :
- « En ligne » / « Hors connexion » ;
- « Brouillon enregistré sur cet appareil » ;
- « En attente de synchronisation (N) » ;
- « Synchronisation en cours » ;
- « Synchronisé le … » ;
- « Conflit à résoudre » ;
- « Action refusée : vos droits ont changé » ;
- « Connexion requise pour envoyer ».

Les pages ne doivent jamais présenter une donnée locale comme une donnée officiellement enregistrée. Distinguer visuellement état local et état serveur.

## 10. Vitrine publique hors ligne

- Mettre en cache le shell statique de la vitrine, ses CSS/JS/polices/icônes.
- Mettre en cache uniquement les pages publiques validées : présentation, centres publiés, horaires publiés, informations et albums publics.
- Exclure l'administration du site public, brouillons CMS, pages de prévisualisation et médias non publiés.
- Utiliser une stratégie Network First pour les pages publiques dynamiques ; servir la dernière copie publique si le réseau échoue.
- Fournir une page hors ligne explicite avec date de dernière actualisation et indication que les horaires/événements peuvent avoir changé.
- Les liens externes, cartes, vidéos et intégrations tierces ne sont pas garantis hors ligne.
- Les formulaires publics ne sont pas déclarés soumis avant accusé serveur.

## 11. Service Worker et cycle de vie

- Nommer les caches avec version explicite : \`lbe-static-v1\`, \`lbe-public-v1\`, etc.
- Installer le nouveau Service Worker sans interrompre un formulaire en cours.
- Activer après stratégie de mise à jour contrôlée ; supprimer uniquement les caches obsolètes appartenant à l'application.
- Prévoir une notification « Mise à jour disponible » et action explicite « Actualiser ».
- Ne jamais supprimer les brouillons IndexedDB lors d'une mise à jour du Service Worker.
- Ne pas intercepter les routes API de synchronisation comme des pages cacheables.
- Utiliser Background Sync si disponible, mais toujours fournir une synchronisation de secours via événement \`online\`, retour au premier plan et action manuelle.
- Ne pas promettre une synchronisation garantie lorsque le navigateur ferme l'application ou suspend le processus en arrière-plan.

## 12. Sécurité web complémentaire

- Conserver HTTPS obligatoire en production.
- Auditer XSS avant de stocker des données sensibles côté navigateur : une faille XSS peut lire les données déverrouillées.
- Déployer une Content Security Policy adaptée progressivement, en commençant par un mode Report-Only si nécessaire pour inventorier les scripts inline et CDN.
- Vérifier les en-têtes \`Cache-Control\`, \`Vary\`, \`Content-Type\`, \`X-Content-Type-Options\`.
- Refuser les requêtes cross-origin non attendues ; ne pas ouvrir CORS largement.
- Limiter la taille des payloads, le nombre d'opérations par lot et le débit.
- Journaliser les identifiants d'opération et résultats, sans enregistrer le contenu sensible des formulaires.
- Les erreurs client ne doivent pas exposer SQL, chemins, tokens ou détails de session.
- Éviter d'inclure des données privées dans les logs du Service Worker ou la console.

## 13. Structure de fichiers proposée

Adapter aux conventions existantes, sans imposer un framework :

- \`sw.js\` : Service Worker racine.
- \`assets/images/site.webmanifest\` : manifeste amélioré.
- \`assets/js/pwa/register.js\` : enregistrement et mises à jour.
- \`assets/js/pwa/network-status.js\` : état réseau et indicateurs.
- \`assets/js/pwa/offline-db.js\` : IndexedDB et migrations.
- \`assets/js/pwa/crypto-vault.js\` : chiffrement/déchiffrement.
- \`assets/js/pwa/outbox.js\` : file d'attente et états.
- \`assets/js/pwa/sync-client.js\` : bootstrap, envoi, retry, conflits.
- \`assets/js/pwa/forms-adapter.js\` : adaptateurs explicites des formulaires compatibles.
- \`assets/offline.html\` : page de secours statique sans données privées.
- \`app/Controllers/PwaSyncController.php\` : endpoints JSON.
- \`app/Services/PwaSyncService.php\` : orchestration idempotence et synchronisation.
- \`app/Repositories/PwaSyncRepository.php\` : persistance des opérations.
- Migration dédiée pour \`pwa_sync_operations\` et éventuelles colonnes de version métier.

Les noms sont indicatifs : réutiliser les conventions exactes du dépôt après audit des autoloaders, routeur et migrations.

## 14. Plan de réalisation par phases

### Phase 0 — Inventaire et décisions de sécurité

- [ ] Cartographier toutes les routes publiques, authentifiées et administratives.
- [ ] Inventorier les formulaires et chaque valeur \`action\` de \`ActionsController::postAction()\`.
- [ ] Documenter les effets secondaires (email, notification, upload, transaction, génération PDF/CSV).
- [ ] Identifier les données sensibles et les appareils partagés.
- [ ] Définir la politique de conservation et suppression des brouillons.
- [ ] Valider le choix de coffre local : PIN/phrase secrète ou expérience simplifiée avec risques documentés.
- [ ] Définir les formulaires autorisés en V1 et ceux exclus.

### Phase 1 — Installation PWA et vitrine publique

- [ ] Corriger le manifeste, le scope, les chemins, le nom, le thème et les icônes.
- [ ] Référencer le manifeste dans les layouts public et gestion.
- [ ] Ajouter \`sw.js\` racine et enregistrement progressif.
- [ ] Créer le cache statique versionné et la page hors ligne.
- [ ] Définir la liste blanche des pages publiques et leur stratégie Network First.
- [ ] Ajouter les en-têtes no-store aux réponses privées.
- [ ] Tester installation et lancement standalone sur Android et desktop.

### Phase 2 — Socle de stockage local sécurisé

- [ ] Implémenter IndexedDB versionné.
- [ ] Implémenter le coffre de chiffrement local et son déverrouillage.
- [ ] Ajouter la séparation des données par compte et appareil.
- [ ] Ajouter les états de brouillon et la gestion des quotas/erreurs de stockage.
- [ ] Ajouter les écrans de consultation, suppression et expiration des brouillons.
- [ ] Tester fermeture navigateur, redémarrage appareil, mise à jour SW et changement de compte.

### Phase 3 — API serveur et idempotence

- [ ] Ajouter les endpoints JSON versionnés.
- [ ] Créer la table d'idempotence et ses index.
- [ ] Ajouter les transactions et accusés individuels.
- [ ] Réutiliser les services métier existants sans contourner les règles.
- [ ] Implémenter authentification, CSRF frais, RBAC, responsabilités et périmètre.
- [ ] Ajouter validation, limites de payload, rate limiting et journalisation.
- [ ] Tester répétition, concurrence, session expirée et permissions révoquées.

### Phase 4 — Première saisie hors ligne

- [ ] Ajouter les adaptateurs pour les formulaires retenus en V1.
- [ ] Enregistrer les brouillons hors ligne.
- [ ] Ajouter l'envoi manuel et automatique à la reconnexion.
- [ ] Ajouter retry, ordre, erreurs et conflits.
- [ ] Commencer par les formulaires à faible risque et les brouillons de rapport/suivi hebdomadaire après validation métier.
- [ ] Garder les actions financières, suppressions et opérations administratives hors ligne désactivées.

### Phase 5 — Extension contrôlée et durcissement

- [ ] Évaluer les autres formulaires au cas par cas.
- [ ] Ajouter la reprise d'uploads si elle est nécessaire et spécifiée.
- [ ] Renforcer CSP et les protections XSS.
- [ ] Ajouter métriques anonymisées de synchronisation et diagnostic support.
- [ ] Effectuer tests de charge raisonnables et tests sur appareils réels.
- [ ] Documenter la procédure de purge et de support utilisateur.

## 15. Stratégie de tests et critères d'acceptation

### PWA et installation

- [ ] Manifest valide, icônes et start URL accessibles.
- [ ] Service Worker actif avec scope racine attendu.
- [ ] Installation possible sur Android/Chrome et desktop pris en charge.
- [ ] Navigation publique et gestion ne cassent pas les URLs profondes.
- [ ] Mise à jour SW sans perte de brouillons.

### Cache et confidentialité

- [ ] Aucun document privé, JSON privé, cookie, CSRF ou export dans Cache Storage.
- [ ] Un utilisateur A ne voit jamais les brouillons de l'utilisateur B.
- [ ] Déconnexion/changement de compte applique la politique de purge.
- [ ] Les pages privées expirées redirigent vers la connexion et ne s'affichent jamais depuis le cache.
- [ ] Une page publique hors ligne indique sa date de dernière mise à jour.

### Synchronisation

- [ ] Chaque opération n'est appliquée qu'une fois, même si la réponse réseau est perdue.
- [ ] Un lot partiel peut être rejoué sans doublon.
- [ ] Une session expirée bloque l'envoi sans détruire les brouillons.
- [ ] Une permission révoquée entre saisie et synchronisation entraîne un refus serveur.
- [ ] Un conflit de version n'écrase jamais silencieusement une donnée sensible.
- [ ] Une erreur de validation est compréhensible et permet de corriger le brouillon.
- [ ] L'interface ne marque « synchronisé » qu'après confirmation serveur.

### Tests par rôle et métier

- [ ] Administrateur.
- [ ] Berger / pasteur / révérend selon les permissions.
- [ ] Responsable de centre/bacenta avec périmètre attribué.
- [ ] Utilisateur sans permission de saisie.
- [ ] Compte désactivé ou session expirée.
- [ ] Appareil partagé avec alternance de comptes.

### Tests techniques

- [ ] Tests unitaires PHP du service de synchronisation et de l'idempotence.
- [ ] Tests d'intégration MySQL/MariaDB avec transactions.
- [ ] Tests JS IndexedDB/Web Crypto/Service Worker.
- [ ] Tests navigateur avec réseau offline/online, latence, coupure au milieu du POST.
- [ ] Lighthouse et DevTools Application.
- [ ] Recette Android réelle, notamment stockage faible et navigateur tué par le système.

**Condition de mise en production :** aucun défaut critique de fuite de données, de double écriture, d'élévation de privilège ou de perte de brouillon ne doit subsister. L'activation doit être progressive par fonctionnalité et réversible par configuration.

## 16. Risques et arbitrages à ne pas masquer

| Risque | Mesure |
|---|---|
| Double soumission après timeout | UUID + contrainte unique + transaction |
| Session expirée hors ligne | Brouillon conservé, synchronisation bloquée jusqu'à reconnexion |
| Permission changée depuis la saisie | Recontrôle serveur au moment du commit |
| Conflit de données | Version de base et résolution explicite |
| Appareil partagé | Coffre chiffré, cloisonnement compte, purge |
| XSS | Audit/renforcement CSP ; chiffrement local non suffisant |
| Quota navigateur / éviction | Indicateur d'espace, gestion d'erreur, export/sauvegarde contrôlée si autorisé |
| Background Sync indisponible | Repli online/visibility/action manuelle |
| Perte d'un upload | Hors V1 ; protocole de reprise spécifique |
| Contenu public périmé | Afficher dernière date de mise à jour |

## 17. Décisions produit proposées pour validation avant le code

Les choix suivants ne doivent pas être supposés silencieusement :

1. **Protection des brouillons hors ligne :** coffre protégé par PIN/phrase secrète (recommandé pour appareils partagés) ou protection simplifiée avec risque résiduel accepté.
2. **Déconnexion avec file non synchronisée :** demander de synchroniser, conserver le coffre verrouillé, ou supprimer les brouillons après confirmation.
3. **Formulaires V1 :** valider précisément la liste issue de l'inventaire des actions POST ; recommandation initiale : brouillons de rapports et suivi hebdomadaire, puis demande publique d'inscription si les protections anti-abus sont adaptées.
4. **Durée de conservation locale :** proposer une durée courte configurable, par exemple 7 jours, avec avertissement avant expiration.
5. **Saisie financière et présences :** rester en ligne uniquement pour la première version.

## 18. Définition du « prêt pour production »

La PWA ne sera déclarée prête que lorsque :
- la vitrine et l'espace de gestion sont installables ;
- le Service Worker et le manifeste sont validés sur le domaine HTTPS réel ;
- les pages privées ne sont jamais servies depuis un cache ;
- le coffre local protège les brouillons sensibles et sépare les comptes ;
- la synchronisation est idempotente, transactionnelle et soumise aux permissions serveur ;
- les conflits et sessions expirées sont gérés sans perte silencieuse ;
- les tests automatisés et la recette sur appareils réels sont passés ;
- une procédure de désactivation/rollback est documentée.

