# Landing page publique — plan d'implémentation

Date : 17 octobre 2026. Source : `prompts/Landing page publique de La Belle Église — spécification unifiée.md`.
Statut : audit réalisé en lecture seule, aucun code modifié. Rubriques A à J (spec §9).

---

## A. Synthèse de l'existant

**Architecture réelle**
- PHP sans framework ni Composer. `index.php` est le front controller, `Routes/web.php` mappe `?page=` vers un Controller, `app/Core/Router.php` dispatche. Aucune réécriture d'URL : le `.htaccess` force HTTPS, bloque les dossiers applicatifs et pose des en-têtes. Pas de dossier `tests/`.
- `index.php` ordonne : actions GET, puis POST, puis une liste blanche `$publicPages = [register, verify_email, verify_email_change, email_change_pending]`. Sans utilisateur connecté, `AuthController::loginForm()` s'affiche. `$page` vaut `accueil` par défaut (tableau de bord privé).
- Conséquence : `/` sans session affiche la connexion ; avec session, le dashboard. `index.php?page=bacentas` sans session affiche aussi la connexion à la même URL.
- Rôles : `Config/permissions.php`, `RbacService::allowedSections()`, table `responsibilities`.

**Les deux pages actuelles ne sont pas publiques**
- Servies par `AboutController` → `app/Compat/apropos.php` → `Views/pages/apropos.php`, `centres_presentation.php`, `centre_article.php`.
- Elles exigent une session et rendent dans le layout applicatif (sidebar).
- Elles mêlent consultation et administration : formulaires `form=histoire|equipe|article`, suppressions `delete_equipe` et `delete_article` (`ActionsController` l. ~1000-1073).
- **Les écrans d'admin doivent survivre.** Seule la vue de consultation est remplacée.

**Données existantes réutilisables**

| Source | Contenu |
| --- | --- |
| `presentation` | `accroche`, `histoire` |
| `equipe` | nom, rôle, bio, photo, catégorie |
| `centres_presentation` | `intro`, `vision`, `direction`, `origine`, `situation_*`, `objectifs` (JSON), `photo` |
| `evenements` | `nom`, `date_debut`, `date_fin`, `lieu`, `responsable_id` |
| `assets/images` | `logo.png`, `pasteur-bg.jpg`, favicons |
| `uploads/` | photos, servies publiquement, exécution de code désactivée |
| `Config/app.php` | nom, fuseau `Africa/Libreville` |

**Lacunes bloquantes**
- `centres` ne contient que `id` et `nom` : ni adresse, ni GPS, ni téléphone, ni statut de publication.
- Les horaires existent sur `cultes`, `bacentas`, `basontas` (`jours_semaine`, `heure_debut`, `heure_fin`) mais pas sur `centres`. `cultes` ne se rattache aux centres que via `culte_bacentas` : non fiable pour des horaires publics.
- Rien pour contacts, réseaux sociaux, prédications, FAQ, témoignages, dons, légal.
- `evenements` n'a ni description, ni affiche, ni indicateur public ; il contient des événements internes.

**Contraintes** : pas de Composer, npm, build ni Docker ; CSS modulaire via `variables.css`, pas de style inline ; migrations idempotentes en blocs numérotés (jusqu'au bloc 13) ; vérification par `php -l` et scripts d'assertion.

**Risques relevés**
- ~20 redirections codées en dur vers `page=apropos` (accès refusé, `ActionsController`, `Compat`, middlewares) ; `apropos` figure dans `allowedSections` de chaque rôle.
- `sitemap.xml` et `robots.txt` pointent vers `https://lbegf.rf.gd` (ressemble à un hébergeur gratuit, pas Hostinger). **Domaine de production à confirmer.**
- `login.php` est `noindex`. `layout.php` porte `$publicLis` et `NAV_ORDER` avec `apropos` en première position (« exigence produit »).
- `Views/pages/apropos.php` utilise du style inline : à ne pas reprendre.

---

## B. Inventaire des données

| Donnée | Source existante | Publiable ? | Travail | Priorité |
| --- | --- | --- | --- | --- |
| Nom, logo | `Config/app.php`, `logo.png` | Oui | Valider l'orthographe (« Intenationale » semble une faute de frappe) | **Bloquant** |
| Slogan / mission | `presentation.accroche` | Partiel | Validation du texte | **Bloquant** |
| Histoire | `presentation.histoire` | Oui, après relecture | Aucun | Lancement |
| Vision / valeurs / croyances | `centres_presentation.vision` (par centre) | Partiel | Texte global à fournir | Optionnel |
| Centres : nom, intro, photo | `centres`, `centres_presentation` | Oui | Droits des photos | Lancement |
| Centres : adresse, quartier, ville, GPS, itinéraire | **Absent** | – | Nouvelles colonnes dans `centres` | **Bloquant** |
| Horaires par centre, fuseau | **Absent** (fuseau dans `Config/app.php`) | – | Nouvelle table `centre_horaires` | **Bloquant** |
| Accès, PMR, stationnement | Absent | – | Champs optionnels | Ultérieur |
| Contacts (tél., e-mail, WhatsApp) | Absent | – | Table clé/valeur `site_settings` + colonnes par centre | **Bloquant** |
| Responsables / équipe pastorale | `equipe` | Après consentement | Indicateur de publication | Optionnel |
| Chiffres `situation_*` | `centres_presentation` | À décider | Donnée interne ou publique ? | Optionnel |
| Basontas / Bacentas | `basontas`, `bacentas` | Non (pas de description ni de drapeau public) | `publie` + `description_publique` ; Basonta = service, Bacenta = prière ; jamais de listes de membres | Optionnel |
| Événements | `evenements` | Non en l'état | `publie`, `description`, `affiche`, `lien_inscription` | Optionnel |
| Prédications / direct | Absent | – | Lien externe unique dans `site_settings` | Optionnel |
| Réseaux sociaux | Absent | – | `site_settings` | Optionnel |
| FAQ / première visite | Absent | – | Contenu à fournir | Optionnel |
| Témoignages | Absent | – | Hors lancement | Ultérieur |
| Dons | Absent | – | Hors lancement | Ultérieur |
| Légal, analytique | Absent | – | Mentions légales et confidentialité avant tout formulaire ou mesure | Optionnel |

Aucun endpoint JSON n'existe ni n'est nécessaire : rendu côté serveur.

---

## C. Architecture cible

**Route canonique (recommandation)**
- `/` sans session et sans paramètre sert la landing (nouvelle branche dans `index.php`).
- `/` avec session reste le dashboard : favoris et redirections post-connexion inchangés.
- Route explicite `index.php?page=eglise` : landing pour tous (connectés et comptes non activés inclus, critère 1). Nom de la clé à valider.

**Connexion**
- Nouvelle route publique `?page=login`.
- `AuthController::login` redirige vers `page=login&error=…` en cas d'échec (aujourd'hui `index.php?error=`).
- Une page protégée visitée sans session continue d'afficher la connexion à la même URL ; seul `/` nu change.
- Le lien « Connexion » de l'en-tête pointe vers `?page=login`.
- La déconnexion redirige vers `index.php`, donc vers la landing (à valider).

**Nouveaux fichiers**
- `app/Controllers/PublicController.php`
- `app/Services/PublicSiteService.php` (règles de publication et de seuil)
- `app/Repositories/PublicSiteRepository.php` (SQL en liste blanche de colonnes, lignes publiées uniquement)
- `Views/layouts/public.php` (document HTML autonome, comme `login.php`)
- `Views/pages/public/landing.php` + partials par section
- `assets/css/landing.css` (variables uniquement)
- `assets/js/landing.js` (menu mobile, accordéon, vanilla)
- Helper `public_site_service()` via `_repo()` dans `app/Compat/data.php`

**Fichiers modifiés**
- `index.php` : ajoute `eglise` et `login` à `$publicPages` + branche `/` anonyme. Seul point de contournement de l'authentification.
- `Routes/web.php` : routes `eglise` et `login`.
- `Views/layouts/layout.php` : remplace les deux `$publicLis` par un lien « Notre église » vers la landing.
- `Config/constants.php` : labels et `NAV_ORDER`. **Les clés `apropos` et `centresPresentation` sont conservées** (RbacService, redirections).
- `AboutController` : sans `form` ni `id`, redirection 301 vers `?page=eglise#a-propos` / `#centres`. Les `form=…` et `id=…` d'admin restent servis.
- `ActionsController` : redirections après sauvegarde conservées ; nouvelles actions admin `save_site_settings` et `save_centre_public` (CSRF déjà appliqué en tête de `postAction()`).
- `robots.txt`, `sitemap.xml` : ajouter la landing, retirer les mentions obsolètes.
- Migration : bloc 14 idempotent — colonnes de `centres`, `site_settings`, `centre_horaires`, `evenements.publie` / `description`, `equipe.publie`. Rien n'est supprimé.

**Source par section (§7.1)**
- À propos : `presentation` + `equipe` (publiée)
- Centres : `centres` + `centres_presentation` + `centre_horaires`
- Horaires : `centre_horaires` (même source que les cartes)
- Contact et réseaux : `site_settings`
- Événements : `evenements` où `publie = 1` et date future
- Médias : lien externe dans `site_settings`
- Le reste est masqué tant que les données manquent

**Administration (§7.4)**
- Réservée à `admin`, pas de CMS complet.
- Écran « Site public » dans `parametres` : identité, contacts, réseaux, lien de direct.
- Champs publics du centre ajoutés au formulaire d'article existant.
- Interrupteur `publie` par centre, événement et membre d'équipe.

**Seuil de publication d'un centre (proposition)** : nom, adresse validée, au moins un horaire, un contact. Sinon non publié.

**Sécurité**
- `h()` partout, SQL préparé, colonnes en liste blanche.
- Aucune jointure vers `users`, `presences` ou `offrandes`.
- Pas de `noindex`.
- Formulaire de contact exclu du MVP ; s'il est ajouté, branche POST dédiée dans `index.php` (comme `register`) avec CSRF et anti-spam.

---

## D. Plan de conception (wireframe)

```
[Logo + Nom]  Accueil  À propos  Centres  Vie de l'église*  Médias*  Contact   [Planifier ma visite]  (Connexion)
─ HERO #accueil ─ H1 nom officiel · slogan · accueil · [Planifier ma visite] [Découvrir nos centres]
─ #horaires ─ synthèse jours/heures par centre · [Itinéraire] · contact rapide
─ #a-propos ─ histoire, vision, mission · équipe pastorale*
─ #centres ─ grille de cartes (photo, adresse, horaires, contact, itinéraire)
─ #vie-eglise* ─ #evenements* ─ #medias* ─ #communaute* ─ #visite* ─
─ #contact ─ tel: · mailto: · WhatsApp · réseaux
─ #engagement* ─ (don/bénévolat seulement si opérationnel)
─ FOOTER ─ coordonnées · liens · légal* · © année dynamique
```

(\* = conditionnelle, masquée avec son entrée de menu si sans données.)

- CTA principal par défaut : « Planifier ma visite » → `#horaires` (à valider).
- Mobile-first, un seul H1.
- Menu en `<button aria-expanded>`, focus rendu à la fermeture.
- Animations désactivées sous `prefers-reduced-motion`.
- Hero : `pasteur-bg.jpg` seulement si les droits sont confirmés, sinon dégradé.
- Itinéraire en lien externe `rel="noopener noreferrer"`, pas de carte intégrée.

---

## E. Plan technique et phases

| Phase | Fichiers | Travaux | Dépendances | Validation |
| --- | --- | --- | --- | --- |
| 1 Audit | – | Ce document | – | Livré |
| 2 Validation | – | Décisions et données (§11 spec) | Responsable de l'église | Liste des décisions signée |
| 3 Routage | `index.php`, `Routes/web.php`, `PublicController`, `AuthController`, login | `eglise`, `login`, `/` anonyme, redirections | 2 | Critères 1, 2, 12 |
| 4 Migration | Schéma bloc 14, repo, service | Colonnes et tables, règle de publication | 2 | Migration rejouée sans perte |
| 5 Design | `public.php`, partials, CSS, JS | Layout, sections, menu, accessibilité | 3 | Critères 3, 6, 7 |
| 6 Admin | `parametres`, `ActionsController`, formulaires | Écran « Site public », interrupteurs `publie` | 4 | Seul l'admin écrit |
| 7 SEO / perf | `<head>`, JSON-LD, sitemap, robots | Métadonnées, `Church`, `Event` | 5, 6 | Critères 8, 9 |
| 8 Non-régression | scripts d'assertion | Voir G | tout | Critère 12 |
| 9 Mise en prod | `.env`, domaine | HTTPS, canonical, sauvegarde | 8 | Aucun contenu provisoire |

- **MVP** : phases 3, 4 (centres et `site_settings`), 5, 6 (centres et identité).
- **Au lancement si prêts** : équipe, événements, médias, FAQ.
- **Ultérieur** : témoignages, dons, formulaire de contact.

---

## F. Plan de migration

- `?page=apropos` → 301 vers `?page=eglise#a-propos` ; `?page=centresPresentation` → `#centres`. Un ancien `?page=centresPresentation&id=N` va aussi vers `#centres` (aucune fiche détaillée créée). Formulaires et suppressions d'admin inchangés.
- Aucune boucle possible : la landing est publique, donc les redirections d'accès refusé vers `apropos` aboutissent à une page accessible.
- Alternative écartée : pages secondaires conservées — contredit « une seule destination » (critère 2).
- Retour arrière : un commit par phase ; migration additive (rien à annuler) ; il suffit de retirer la branche `/` et la redirection dans `AboutController`.
- Aucune table, route ni contrôleur supprimé à cette étape.

---

## G. Plan de tests

Pas de framework : scripts d'assertion jetables (`php -d zend.assertions=1 -d assert.exception=1`) + `php -l`.
- **Accès** : `curl` sans cookie sur `/` et `?page=eglise` (200, pas de formulaire de connexion) ; idem avec session admin, session `membre`, compte `pending`.
- **Anciennes URL** : 301 vers la bonne ancre, sans boucle, y compris sous session.
- **Fuite** : le HTML public ne contient ni e-mail d'utilisateur, ni membre, ni présence ou offrande ; un centre non publié n'apparaît pas.
- **Vue** : `view('pages/public/landing', …)` avec jeux vides, chaque section se masque.
- **Connexion** : succès → `scope_target()` ; échec → `page=login&error=` ; `verify_access` inchangé.
- **Admin** : seul `admin` peut appeler les actions de sauvegarde.
- **Manuel** : mobile / tablette / ordinateur sans débordement ; clavier et focus ; Lighthouse (LCP, INP, CLS) ; Rich Results Test ; liens `tel:`, `mailto:`, WhatsApp, itinéraire.

---

## H. Estimation indicative (non ferme)

| Bloc | Complexité |
| --- | --- |
| Routage + connexion | Faible à moyenne (risque : non-régression) |
| Migration + repo + service | Moyenne |
| Design + accessibilité | Moyenne à élevée |
| Admin « Site public » | Moyenne |
| Contenus et tests | Dépend de la réactivité de l'église |

Alourdissent : formulaire de contact avec anti-spam, carte intégrée, module de dons, CMS plus riche.

---

## I. Risques et décisions ouvertes

| Sujet | Recommandation | À confirmer | Classe |
| --- | --- | --- | --- |
| Nom officiel et logo | Corriger « Intenationale » seulement après validation | Orthographe exacte | Bloquant |
| Slogan / mission | Pas de texte provisoire en production | Texte approuvé | Bloquant |
| Adresses, horaires, fuseau par centre | Saisie via l'admin | Valeurs vérifiées | Bloquant |
| Contacts et itinéraire | Un contact général + un par centre | Numéros, e-mail, WhatsApp | Bloquant |
| CTA principal | « Planifier ma visite » | Libellé | Bloquant |
| Domaine de production | `rf.gd` dans `sitemap.xml` / `robots.txt` | Domaine réel | Avant dev |
| Route canonique et connexion | `/` anonyme = landing ; login sur `?page=login` | Accord sur le changement de comportement | Avant dev |
| Chiffres `situation_*` et texte `direction` | Masqués par défaut | Public ou interne ? | Avant dev |
| Photos et équipe | Indicateur `publie` par membre | Consentements | Optionnel |
| Événements vides | Section masquée | Masquée ou état vide ? | Avant dev |
| Rôles d'édition | Admin seul | Ouvrir à d'autres rôles ? | Avant dev |
| Dons, témoignages, contact | Hors lancement | Prestataire, consentements | Ultérieur |

---

## J. Table de traçabilité

| Critère (spec 10.1) | Tâches | Tests |
| --- | --- | --- |
| 1 Accès public | Phase 3 : `$publicPages`, route `eglise` | G : 4 cas de session |
| 2 Remplacement | Phase 3 : redirections, menu, `constants` | G : anciennes URL, absence de boucle |
| 3 Compréhension | Phase 5 : hero, CTA, `#horaires` | Revue manuelle |
| 4 Exactitude | Phase 4 : seuil de publication, aucun placeholder | Script : centre incomplet absent |
| 5 Actions | Phase 5 : liens construits depuis les données | Clic manuel sur chaque lien |
| 6 Responsive | Phase 5 : CSS mobile-first | Trois largeurs |
| 7 Accessibilité | Phase 5 : sémantique, menu, focus | Audit automatisé + clavier |
| 8 SEO | Phase 7 | Rich Results Test, sitemap |
| 9 Performance | Phase 7 : images, scripts minimaux | Lighthouse |
| 10 Confidentialité | Phase 4 : liste blanche de colonnes | Script de fuite sur le HTML |
| 11 Formulaires | Hors MVP (N/A) | – |
| 12 Non-régression | Phase 8 | Login, rôles, admin, routes protégées |

---

## Décisions à valider avant le développement

1. Domaine de production (`lbegf` dans la spec, `lbegf.rf.gd` dans le dépôt).
2. Route canonique : `/` anonyme, `?page=eglise`, `?page=login`.
3. Destination de la déconnexion : landing ou connexion.
4. Anciennes URL : redirection 301 (recommandée).
5. Seuil de publication d'un centre.
6. Section Événements : masquée ou état vide.
7. Publication ou non des chiffres `situation_*` et du texte `direction`.
8. Rôles autorisés à éditer le site public.
9. Les cinq données bloquantes de la §11 de la spec.
