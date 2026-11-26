# PWA & synchronisation hors ligne — état d'implémentation

- **Spécification source :** `docs/plans/pwa-offline-sync-implementation-plan.md`
- **Branche :** `arena/87b27a25-labelleeglise`
- **État :** Phases 1 à 4 implémentées (installation PWA, vitrine hors ligne,
  coffre local, API de synchronisation idempotente, première saisie hors
  ligne). Phase 5 (durcissement, métriques, recette appareils réels) reste à
  mener en production.
- **Recette rapide :** `php tests/pwa-smoke.php` + `node --check sw.js assets/js/pwa/*.js`

---

## 1. Cartographie des fichiers

### Navigateur
| Fichier | Rôle |
|---|---|
| `sw.js` | Service Worker racine : caches versionnés, Network First public, réseau seul privé, page de secours, capture d'envoi interrompu, Background Sync, mise à jour sur action explicite |
| `assets/offline.html` | Page de secours statique (aucune donnée privée, date de dernière actualisation lue dans Cache Storage) |
| `assets/images/site.webmanifest` | Manifeste corrigé (nom, thème, `id`/`start_url`/`scope`, icônes `any` + `maskable` dédiées, aucune orientation imposée) |
| `assets/images/icon-maskable-{192,512}.png` | Variantes maskable (logo recentré dans la zone de sécurité 80 %) |
| `assets/js/pwa/offline-db.js` | IndexedDB `lbe_pwa` v1 : `drafts`, `outbox`, `sync_meta`, `crypto_meta`, `recovery` (partagé page + SW) |
| `assets/js/pwa/crypto-vault.js` | Coffre AES-GCM 256, IV unique, AAD lié à l'enregistrement ; modes `simple` et `pin` (PBKDF2-SHA256), rotation de clé sur conversion |
| `assets/js/pwa/outbox.js` | Brouillons, file d'attente, états, expiration, purge par compte, adoption des saisies capturées par le SW |
| `assets/js/pwa/sync-client.js` | Bootstrap (CSRF frais), envoi par lots, accusés individuels, backoff exponentiel + jitter, résolution de conflits, déclencheurs online/visibilité/manuel/Background Sync |
| `assets/js/pwa/forms-adapter.js` | Adaptateurs EXPLICITES (`data-pwa-*`) : brouillon auto, interception hors ligne, reprise de brouillon, champs jamais stockés |
| `assets/js/pwa/network-status.js` | Pastille d'état uniforme (plan §9.3) |
| `assets/js/pwa/offline-panel.js` | Tiroir/écran de gestion : file, conflits (local vs serveur, 3 résolutions), coffre PIN, purge compte, garde de déconnexion |
| `assets/js/pwa/register.js` | Enregistrement SW, détection de support silencieuse, bannière « Mise à jour disponible → Actualiser » |
| `assets/css/pwa.css` | Styles de la pastille, du tiroir, des modales, du champ piège |

### Serveur
| Fichier | Rôle |
|---|---|
| `Config/pwa.php` | Activation progressive, allowlist des opérations, exclusions documentées, coffre, limites, débit public |
| `app/Helpers/pwa.php` | Racine réelle de déploiement, en-têtes de cache (`no-store` / `X-LBE-Cacheable`), empreintes, injection layouts, attributs de formulaires |
| `app/Controllers/PwaSyncController.php` | Endpoints JSON versionnés + écran `?page=pwa_offline` |
| `app/Services/PwaSyncService.php` | Orchestration : allowlist, session, idempotence, transactions, conflits, opération publique |
| `app/Repositories/PwaSyncRepository.php` | Journal `pwa_sync_operations` : claim/replay/anomalie, synthèse, débit public, purge |
| `Database/Migrations/2024_01_01_000000_create_schema.php` | Bloc 21 : table d'idempotence (DDL idempotent, UNIQUE `operation_id`) |
| `Routes/web.php` + `index.php` | Routes `pwa/v1/*` dispatchées par le routeur maison, AVANT le dispatch POST |

---

## 2. Décisions d'ingénierie (et leurs motifs)

1. **Autorisation de mise en cache côté serveur.** Le Service Worker ne met
   en cache une navigation publique que si la réponse porte
   `X-LBE-Cacheable: 1`, posé uniquement quand la page est allowlistée ET la
   session anonyme (`pwa_mark_public_cacheable()`). Toute réponse dynamique
   est `no-store` par défaut depuis `Bootstrap/init.php`.
2. **Concurrence optimiste par empreinte serveur.** Aucune colonne de version
   ajoutée aux tables métier : le serveur calcule une empreinte SHA-256
   canonique de l'état lu (`pwa_fingerprint()`), injectée dans le formulaire
   (`data-pwa-base-fingerprint`) et renvoyée par le client. Divergence au
   commit ⇒ conflit explicite avec valeurs serveur ; résolutions possibles :
   `server`, `client`, `merge` (le merge ne réécrit que les champs réellement
   modifiés par l'utilisateur depuis sa lecture).
3. **Idempotence en deux temps documentée.** La ligne `processing` (UNIQUE
   `operation_id`) est posée AVANT l'exécution ; la mutation métier et
   l'enregistrement du résultat sont commités ensemble
   (`Query::transaction`). Pour `rapport_jour`, le service métier émet ses
   emails APRES sa transaction interne : englober l'envoi SMTP dans la
   transaction exposerait à des emails « fantômes » en cas de rollback ; le
   risque résiduel (panne entre commit métier et accusé) est couvert par le
   replay idempotent (upsert par clé métier unique + notification uniquement
   à la création).
4. **Chiffrement local à deux modes.** `simple` (clé appareil non
   extractible) par défaut ; `pin` (clé enveloppée PBKDF2) recommandé sur
   appareil partagé et activable par configuration ou depuis l'écran
   « Données hors ligne ». Le Service Worker ne capture un envoi interrompu
   qu'en mode `simple` (il ne peut pas déverrouiller un coffre PIN) — limite
   affichée dans l'interface.
5. **Aucun secret jamais écrit sur l'appareil** (trois filets).
   (a) Le formulaire d'inscription déclare `data-pwa-no-store` :
   l'adaptateur client ne met jamais ces champs dans un brouillon ni dans la
   file. (b) Le Service Worker, qui capture un envoi interrompu sans accès au
   DOM, filtre les noms de champs sensibles (`isSecretField()` : `password`,
   `pwd`, `secret`, `code_pin`, `otp`, `token`) avant d'écrire dans le store
   de récupération. (c) `outbox.adoptRecovery()` refiltre les mêmes noms au
   moment d'adopter une saisie capturée. Le mot de passe est donc redemandé au
   moment de l'envoi, conservé en mémoire vive le temps de la requête
   (`sync.setPendingCredentials()`), puis oublié.
   Corollaire : l'envoi en ligne d'un formulaire compatible est marqué d'un
   champ caché `__pwa_scope` (compte courant) afin que, si le réseau tombe
   pendant cet envoi, la saisie capturée soit rattachée au bon compte et
   jamais à la file d'un autre utilisateur ; ce champ est ignoré du serveur et
   exclu des brouillons.
6. **`uploads/` jamais mis en cache.** Le dossier est mixte (photos de
   profil privées + albums publics) : le Service Worker le laisse en réseau
   seul, `uploads/.htaccess` y pose `Cache-Control: no-store, private` (aucun
   cache HTTP, donc aucune copie survivante sur un appareil partagé), et le
   dossier est absent du précachage. Hors ligne, les pages d'albums restent
   lisibles mais leurs images nécessitent le réseau. Limite documentée.
7. **Manifeste dynamique, fichier statique conservé.**
   `index.php?api=pwa/v1/manifest` résout `id`, `start_url`, `scope` et les
   icônes depuis la racine réelle de déploiement (sous-répertoire toléré) en
   prenant le fichier statique comme source d'identité.
8. **Identifiant d'opération manquant.** L'UUID d'opération est généré côté
   client (c'est lui qui garantit l'idempotence quand le réseau tombe après un
   commit serveur). S'il est absent d'une opération privée (client ancien,
   requête manuelle), le serveur en crée un : la saisie est enregistrée mais
   un double renvoi ne sera pas détecté. Les opérations publiques exigent
   toujours un UUID client (clé du débit limité et de l'anti-rejeu).
9. **Finances.** Le champ `offrande` du rapport du jour fait partie du
   rapport (allowlisté par le plan §9.1/§17.3) : tout conflit sur un rapport
   existant impose une résolution explicite. Les modules financiers dédiés
   (`save_visites_offrandes`, `save_offrandes_centre`, `budget_bus`), les
   pointages de masse, suppressions, rôles et uploads restent exclus
   (`Config/pwa.php`, affiché dans l'écran dédié).

---

## 3. Recette minimale avant production

1. `php tests/pwa-smoke.php` puis recette navigateur (DevTools → Application) :
   manifeste valide, SW actif scope racine, installation Android/desktop.
2. Couper le réseau sur la vitrine déjà visitée : la copie publique s'affiche
   avec la page de secours en repli ; aucune page de gestion ne s'affiche
   depuis un cache.
3. Saisir un rapport du jour hors connexion : message « Brouillon enregistré
   sur cet appareil — en attente de synchronisation » ; reconnecter : envoi
   automatique, accusé « Synchronisé le … », ligne unique en base.
4. Rejouer le même lot (DevTools, replay) : statut `duplicate`, aucune double
   écriture, aucune double notification admin.
5. Modifier le rapport côté serveur pendant la saisie hors ligne d'un second
   appareil : conflit affiché avec les deux versions ; chaque résolution
   vérifiée.
6. Expirer la session avant l'envoi : envoi bloqué, brouillons intacts,
   message « Connexion requise pour envoyer ».
7. Deux comptes alternés sur un même navigateur : aucune file n'apparaît sous
   l'autre compte ; purge par compte vérifiée ; déconnexion avec file non
   synchronisée ⇒ écran de choix (synchroniser / conserver / supprimer).
8. Vérifier dans DevTools → Cache Storage : uniquement `lbe-static-v*`
   (ressources) et `lbe-public-v*` (pages publiques anonymes) ; aucun JSON
   privé, aucun cookie, aucune page de gestion.

## 4. Rollback

- `PWA_ENABLED=false` : toute la couche disparaît (manifeste dynamique,
  en-têtes, scripts, endpoints) ; l'application classique est inchangée.
- `PWA_SERVICE_WORKER=false` : coupe SW/caches sans toucher à la saisie.
- `PWA_OFFLINE_CAPTURE=false` : coupe brouillons/synchronisation.
- `PWA_OP_*` : coupe une opération individuellement.
- Le Service Worker installé reste inerte : sans endpoint de configuration,
  il ne met plus rien en cache et n'intercepte plus les écritures.

## 5. Reste à faire (Phase 5)

- Métriques anonymisées de synchronisation et outillage de diagnostic support.
- Tests automatisés PHP du service (idempotence, conflits) sur base réelle et
  tests JS IndexedDB/Web Crypto (navigateur ou workerd).
- Recette sur appareils réels (stockage faible, navigateur tué en arrière-plan).
- Reprise d'uploads hors ligne : protocole dédié à spécifier (hors V1).
- Éventuelles captures d'écran/raccourcis du manifeste quand les écrans seront stables.
