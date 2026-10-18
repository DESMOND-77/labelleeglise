# Landing page publique de La Belle Église - plan d'implémentation v2 (spécification unifiée 1.0)

Date : 17 octobre 2026. Source : « Landing page publique de La Belle Église - spécification unifiée v1.0 » (fusion des deux versions précédentes).
Statut : **audit réalisé en lecture seule ; aucun fichier, route, table ou migration modifié** (consigne impérative n°3). Ce document est le livrable de l'étape ; il remplace fonctionnellement `docs/landing-page-implementation-plan.md` (plan v1, antérieur à la livraison de la landing).

**Constat majeur de l'audit :** la landing **existe déjà** (branche `landing_page`, commits jusqu'à `ee0569d`). Ce plan est donc un plan d'**alignement v2** : il part de l'existant réel, identifie les écarts par rapport à la spécification unifiée et propose les modifications additives nécessaires. Rien n'est à réécrire de zéro ; la structure routage → contrôleur → service → dépôt → vue est saine et conforme à l'architecture du projet.

---

## A. Synthèse de l'existant

### A.1 Architecture réellement identifiée

- **Langage / stack :** PHP pur, **aucun framework, aucune dépendance** (pas de Composer, npm, build, Docker - contrainte de projet documentée dans `CLAUDE.md`). Déployable par copie du dossier sur l'hébergement mutualisé Hostinger (racine web = racine du projet).
- **Point d'entrée :** `index.php` (front controller) → `Bootstrap/init.php` (autoload PSR-4 `Bootstrap/autoload.php`, `.env` via `Bootstrap/env.php`, session sécurisée, fuseau `Africa/Libreville`, constantes `APP_NAME`/`APP_URL`).
- **Routage :** paramètre `?page=xxx` (GET) et `?action=xxx` (POST) dispatchés par `App\Core\Router` (`app/Core/Router.php`) via la table déclarée dans `Routes/web.php`. **Aucune réécriture d'URL** : les URLs sont `index.php?page=…`. `.htaccess` force HTTPS, bloque les dossiers applicatifs (`app/`, `Config/`, `Database/`, `Routes/`, `Storage/`, `Views/`…), désactive `display_errors`, pose des en-têtes de sécurité.
- **Couches strictes :** Controllers (`app/Controllers`) → Services (`app/Services`) → Repositories (`app/Repositories`, tout le SQL) → `App\Core\Query` (PDO, requêtes préparées) → Views (`Views/`). Compat (`app/Compat`) expose des fonctions globales aux vues anciennes.
- **Auth / rôles :** `app/Auth/` (`AuthenticationService`, `RbacService`, `AuthorizationService`, `AuthorizationMiddleware`). Rôles `admin/leader/assistant/pasteur/reverant/berger/ms/membre/responsable(legacy)` ; permissions dans `Config/permissions.php` ; périmètres via la table `responsibilities`. Les comptes `pending`/`not_verified`/`disabled` **ne peuvent pas ouvrir de session** (`AuthenticationService::authenticate()` retourne une raison sans créer de session) - un visiteur « compte non activé » est donc toujours anonyme et voit la landing.
- **Stockage :** MySQL/MariaDB (`la_belle_eglise_db`), schéma idempotent en blocs numérotés 1–15 dans `Database/Migrations/2024_01_01_000000_create_schema.php` (le bloc 15 est celui du site public). Aucune migration n'est lancée à cette étape.
- **Statiques :** CSS modulaire `assets/css/*.css` (variables `variables.css`), JS vanilla `assets/js/`, fonts et FontAwesome **vendus localement** (`assets/vendor/`), images `assets/images/` (logo.png, pasteur-bg.jpg, favicons, webmanifest), photos uploadées `uploads/` (exécution PHP désactivée par `.htaccess`).
- **Pages d'erreur :** `errors/400.html`…`500.html` + `error.css` existent.
- **Tests :** aucun framework de test ; la vérification projet est `php -l` + scripts d'assertion jetables (`php -d zend.assertions=1`).

### A.2 La landing actuelle (v1) - fichiers réels

| Élément | Fichier | État |
| --- | --- | --- |
| Route publique | `index.php` (liste `$publicPages` inclut `eglise`, `login`, `register`, `verify_email*`), `Routes/web.php` (`Router::get('eglise', PublicController…)`), `app/Controllers/PublicController.php` | Fonctionnel : `/` anonyme et `?page=eglise` (tous profils) servent la landing |
| Assemblage | `app/Services/PublicSiteService.php` (lecture publique + validation + seuil de publication + JSON-LD) | Fonctionnel, principes conformes (aucune valeur inventée) |
| Lectures SQL | `app/Repositories/PublicSiteRepository.php` (colonnes en liste blanche, `publie=1` uniquement, **aucune jointure vers `users`/`presences`/`offrandes`**) | Conforme |
| Vue | `Views/pages/public/landing.php`, layout `Views/layouts/public.php` (document autonome, `<html lang="fr">`, meta description, canonical, og:, JSON-LD, skip-link) | Fonctionnel mais incomplet (voir A.4) |
| Style / script | `assets/css/landing.css` (~6 Ko, mobile-first, variables), `assets/js/landing.js` (menu mobile : `aria-expanded`, Échap, retour du focus) | Conformes |
| Administration | `?page=site_public` → `app/Controllers/AdminSiteController.php` + `Views/pages/admin_site_public.php` ; écritures `ActionsController` cases `save_site_settings`, `save_equipe_public`, `save_events_public`, `save_centre_public` (tous `requireAdmin()`, CSRF appliqué en tête de `postAction()`) | Fonctionnel |
| Données | `site_settings` (clé/valeur : `nom_affichage`, `telephone`, `email`, `whatsapp`, `facebook`, `youtube`, `instagram`, `lien_direct`), colonnes publiques de `centres` + `centre_horaires`, `equipe.publie`, `evenements.publie`/`description`, `presentation` (accroche/histoire), `centres_presentation` (intro/photo) | Vide par défaut (rien n'est semé) : les sections se masquent |

### A.3 Les deux pages de présentation aujourd'hui

- `index.php?page=apropos` et `index.php?page=centresPresentation` : **anonymes et non-admins connectés → 301** vers `index.php?page=eglise#a-propos` / `#centres` (redirection dans `index.php` pour les anonymes, `AboutController::legacyRedirect()` pour les connectés non-admins). **Les administrateurs conservent les écrans de gestion** (histoire, équipe, articles de centre) via `app/Compat/apropos.php` → vues `Views/pages/apropos.php`, `centres_presentation.php`, `centre_article.php` et formulaires `Views/pages/forms/{histoire,equipe,article}.php`.
- Entrées de menu privées : la sidebar (`Views/layouts/layout.php`) n'affiche plus `apropos`/`centresPresentation` (boucle `NAV_ORDER` les ignore) mais un lien unique « Notre église » → `?page=eglise` (`$publicLis`), plus « Notre église » dans le menu profil. **Il n'y a donc plus deux destinations de présentation concurrentes** - critère 10.1-2 partiellement satisfait.
- `RbacService::allowedSections()` et `Config/constants.php` (`SECTION_LABELS`, `NAV_ORDER`) conservent les clés `apropos`/`centresPresentation` (nécessaire au fonctionnement interne) - à ne pas supprimer.
- ~20 redirections codées vers `page=apropos` dans `app/Compat/*`, middlewares et contrôleurs (refus d'accès) : elles aboutissent soit à la landing (anonyme/non-admin), soit à la gestion (admin) - aucune ne produit de 404.

### A.4 Écarts relevés entre la landing v1 et la spécification unifiée (c'est l'objet de ce plan)

1. **Hero sans support visuel** (spec 4.2) : aucune photo/vidéo, pas de texte d'accueil chaleureux distinct de l'accroche, pas de slogan dédié. `assets/images/pasteur-bg.jpg` existe mais ses droits ne sont pas confirmés.
2. **CTA principal décoratif à l'état vide** (spec 2) : sans centre publié ni contact, « Planifier ma visite » pointe sur `#accueil` (lui-même) - bouton boucle, interdit par la spec.
3. **En-tête sans CTA principal mis en évidence** (spec 4.1).
4. **Bloc horaires** (4.3) : pas de contact rapide dans le bloc, pas de bouton d'itinéraire, pas de mention de fuseau horaire.
5. **Cartes « Nos centres »** (4.5) : **les horaires propres au centre n'y figurent pas** (ils ne sont que dans `#horaires`) ; pas de responsable (aucun champ public - correct, à ne pas ajouter sans donnée).
6. **Pied de page trop maigre** (4.13, socle) : pas de logo/identité, pas de coordonnées, pas de liens de sections/centres, pas de réseaux sociaux, pas de mentions légales ; contient « Créer un compte » (hors périmètre spec).
7. **JSON-LD** (8.4) : nœuds `Church` par centre et `Event` OK, mais **aucun nœud pour l'église elle-même** (organisation) ; pas d'`og:image`.
8. **Sections conditionnelles absentes** (normales sans données, mais sans chemin d'alimentation) : 4.6 ministères/bacentas/basontas (aucun champ public sur ces tables), 4.10 FAQ/visite, 4.9 témoignages, 4.12 dons.
9. **Bug alt texte** (privé, mineur) : `layout.php` sidebar logo alt = « La Belle Église Intenationale Franceville Internationale Franceville » (chaîne dupliquée).
10. **Risque contenu de démonstration** : `Database/Seeders/DatabaseSeeder.php` seme `presentation.accroche/histoire` et 3 membres d'équipe (demo). L'accroche/l'histoire **n'ont pas de drapeau de publication** : toute base installée via `install.php` affiche ces textes démo publiquement. Or la spec 7.3 interdit tout contenu provisoire en production → **relecture/remplacement bloquante avant mise en prod**.
11. **Orthographe du nom** : « Intenationale » (sans « r ») est utilisé partout (`Config/app.php`, seeder, layout, sitemap). Faute possible ou marque délibérée - **à confirmer par le responsable, jamais corrigé silencieusement** (spec 5).
12. **Domaine** : `sitemap.xml`/`robots.txt` pointent sur `https://labelleeglise.com/` ; la spec mentionne `lbegf`. Domaine de production à confirmer.

### A.5 À préserver absolument

L'espace de gestion authentifié (toutes routes non publiques), le modèle RBAC/responsabilités, les formulaires admin des anciennes pages (`apropos`, `centresPresentation` en mode gestion), le workflow d'inscription/activation publique, les URLs existantes (`?page=`, POST `?action=`), les données privées (membres, présences, finances).

### A.6 Contraintes techniques

Pas de Composer/npm/build ; CSS sans couleur/espacement codés en dur, pas de style/script inline dans les vues ; migrations additives idempotentes (bloc numéroté suivant le 15) ; vérification par `php -l` + scripts d'assertion ; compatibilité PHP du mutualisé Hostinger (aucune extension exotique utilisée ; `mbstring`/PDO mysql requis - déjà utilisés).

---

## B. Inventaire des données et contenus

Table de la spec §3.3 complétée. **Aucune valeur inconnue n'est remplacée par une valeur fictive** : chaque trou est signalé avec son moyen de fourniture.

| Donnée | Champs attendus | Source existante | Réutilisable et publiable ? | Travail nécessaire | Priorité |
| --- | --- | --- | --- | --- | --- |
| Identité | nom d'affichage, logo, couleurs | `site_settings.nom_affichage` (repli `APP_NAME`), `assets/images/logo.png`, palette `variables.css` | Oui | **Valider l'orthographe exacte du nom** (« Intenationale ») et le logo officiel | **Bloquant** |
| Message | slogan, vision, mission, valeurs, croyances, histoire | `presentation.accroche` (sert d'accroche hero), `presentation.histoire` | Partiel (textes démo en base possible) | Champ `slogan` + `accueil_texte` (clé/valeur `site_settings`, sans migration) ; **relecture/remplacement des textes démo** | **Bloquant** (slogan) / Lancement (histoire) |
| Centres | nom, adresse, quartier, ville, GPS, photo, statut | `centres` (+colonnes publiques bloc 15), `centres_presentation.intro/photo` | Oui | Saisie admin (`?page=site_public`) ; **coordonnées GPS absentes** (non utilisées sans carte intégrée) | **Bloquant** (adresse) |
| Horaires des cultes | jours/heures par centre, fuseau | `centre_horaires` (par centre), `Config/app.php` timezone | Oui | Saisie admin ; mention du fuseau à l'affichage (décision) | **Bloquant** |
| Accès aux centres | entrée, stationnement, transports, PMR | **Absent** | – | Champs optionnels par centre (ajout ultérieur) ou texte statique fourni | Ultérieur |
| Contacts | téléphone, e-mail, WhatsApp | `site_settings` + colonnes par centre | Oui | Saisie admin (format validé serveur) | **Bloquant** |
| Responsables publics | équipe pastorale, responsables de centre, photos, autorisations | `equipe` (+`publie`), `centres_presentation.direction` (texte interne, non public) | Partiel | Interrupteur `publie` déjà admin ; **responsable de centre : aucun champ public - ne pas exposer `responsable_id`** ; ajouter un champ texte public optionnel si l'église le souhaite | Optionnel au lancement |
| Ministères et activités | Basontas/Bacentas : description, public, horaire, contact | `bacentas`, `basontas` (nom, responsable, horaires internes) | Non en l'état | **Ajout `description_publique` + `publie`** (bloc 16) si la section 4.6 est voulue ; jamais de liste de membres ; Bacenta = prière, Basonta = groupe/service (spec 4.6) | Optionnel au lancement (décision) |
| Événements | titre, date/heure, lieu, description, affiche, inscription, statut | `evenements` (`publie`, `description`, `lieu`, dates) | Oui | Déjà publié via admin ; **affiche et lien d'inscription absents** (à ajouter si besoin) | Optionnel au lancement |
| Prédications et directs | titre, intervenant, date, couverture, liens, direct | `site_settings.lien_direct` | Partiel | Clés `predication_titre/date/url` dans `site_settings` (sans migration) ; pas de médiathèque (spec : un lien suffit) | Optionnel au lancement |
| Réseaux sociaux | comptes officiels | `site_settings.facebook/youtube/instagram` | Oui | Saisie admin ; autres réseaux non prévus (clé/valeur extensible) | Optionnel |
| FAQ et première visite | tenue, durée, enfants, stationnement, PMR, langues, déroulement | **Absent** | – | Table `faq_publique` (bloc 16) + formulaire admin, **ou** contenu statique fourni ; accordéon clavier/lecteur d'écran (`landing.js`) | Optionnel au lancement |
| Témoignages et photos | contenus approuvés, consentements | **Absent** | – | Aucun modèle ; ne pas créer sans processus de consentement | Ultérieur |
| Dons | prestataire, moyens, usage | **Absent** | – | Un seul lien externe sécurisé (`lien_don`) **si** prestataire vérifié fourni | Ultérieur (jamais de simulation) |
| Légal et mesure | mentions légales, confidentialité, analytique | **Absent** | – | Textes à fournir par l'église ; pages statiques publiques `mentions_legales`/`confidentialite` recommandées ; **aucun outil de mesure au lancement** (la confidentialité doit précéder toute mesure) | Optionnel (recommandé) |

**Endpoints réutilisables :** aucun endpoint JSON n'existe ni n'est nécessaire (SSR complet, pas d'API publique à créer - la spec 8.1 est satisfaite par construction : aucune donnée privée n'est lue par `PublicSiteRepository`).

**Contenus manquants à faire valider (§11 spec) :** nom/logo officiels, slogan, adresses/horaires/fuseau, contacts, CTA principal, et - pour les sections conditionnelles - équipe (consentements), événements, médias, FAQ, témoignages, légal.

---

## C. Architecture cible

### C.1 Routage et accès (classés)

| Route | Classe | Justification |
| --- | --- | --- |
| `/` (anonyme), `?page=eglise` | **Publique** | Canonique, déjà déployée, préservée |
| `?page=login`, `?page=register`, `?page=verify_email*` | Publique d'authentification | Existant, inchangé |
| `?page=mentions_legales`, `?page=confidentialite` | **Publique** (à créer, optionnel) | Pages statiques texte |
| `?page=apropos`, `?page=centresPresentation` | Redirection 301 (anonyme/non-admin) / gestion admin | Stratégie §5.2 conservée |
| `?page=accueil`, `bacentas`, `centres`, `cultes`, `basontas`, `finances`, `parametres`, `site_public`, `rapports`, `classes`, `agenda`, `calendrier`, `anniversaires`, `bergerFiche`, `suiviBergers`, `profile`, `admin_inscriptions*`, `notifications`, `recherche`, `personProfile`, `attendancePrint`, `suiviPrint`, `presencePrint` | **Privées** (membre/ rôle/ admin selon permissions existantes) | Inchangées - aucune modification de rôle |

**Route canonique (recommandation, à confirmer - décision D1) :** conserver `/` (visiteur anonyme) + `index.php?page=eglise` (tous profils, y compris connecté - critère 10.1-1). `/` avec session reste le tableau de bord : favoris et redirection post-connexion (`AuthController::login()` → `scope_target()`/`accueil`) inchangés ; la déconnexion (`ActionsController` `logout` → `index.php`) ramène l'anonyme sur la landing. Aucun risque de boucle : la landing est publique.

**Anciennes URL (recommandation - décision D2) :** conserver la double redirection 301 déjà déployée (anonyme via `index.php`, connecté non-admin via `AboutController`). Alternatives comparées : pages secondaires temporaires (rejetée : deux destinations concurrentes, critère 2) ; suppression immédiate (rejetée : casse les favoris admin et les redirections internes `~20` vers `apropos`). `sitemap.xml` already lists only `eglise` + `register` ✓.

### C.2 Fichiers à créer / modifier (chaque modification justifiée)

**À créer (additif) :**
- `Views/pages/public/partials/hero.php`, `horaires.php`, `centres.php`, `a-propos.php`, `evenements.php`, `medias.php`, `contact.php`, `footer.php` - découpage de `landing.php` pour la lisibilité (le fichier unique actuel atteint une taille où les sections socle/conditionnelles se mélangent). *Alternative acceptable : garder un seul fichier - découpage recommandé, pas bloquant.*
- `app/Repositories/FaqRepository.php` (si FAQ table retenue - décision D6).

**À modifier :**
- `app/Services/PublicSiteService.php` - ajout : `slogan`, `accueil_texte`, `hero_image`+alt (même validation `safePhoto`), `lien_predication`(+titre/date), `lien_don`, FAQ, ministères (bacentas/basontas publiés) ; **règle du CTA** : cacher le CTA principal si aucune cible réelle (`#horaires`→centres publiés, sinon `#contact`→contact existant, sinon **pas de bouton** - jamais `#accueil`). Justification : spec 2 (aucun bouton décoratif), 4.2, 7.2.
- `app/Repositories/PublicSiteRepository.php` - lectures additionnelles en liste blanche (`bacentas`/`basontas` avec `publie=1`, `faq_publique`). Aucune jointure vers tables privées. Justification : spec 4.6, 8.1.
- `Views/pages/public/landing.php` + partials - hero enrichi (support visuel optionnel avec repli, phrase d'accueil), CTA en-tête, horaires + contact rapide + itinéraire, **horaires sur les cartes centres**, footer complet, accordéon FAQ. Justification : spec 4.1–4.5, 4.10, 4.13.
- `Views/layouts/public.php` - `og:image` quand `hero_image` validée, title enrichi (`nom - ville` si fourni). Justification : spec 8.4.
- `assets/css/landing.css` - styles des nouveaux blocs (variables uniquement). `assets/js/landing.js` - accordéon FAQ (bouton `aria-expanded`, région `aria-live` si nécessaire), animations déjà gérées (`prefers-reduced-motion` respecté dans le CSS ✓).
- `app/Controllers/AdminSiteController.php` + `Views/pages/admin_site_public.php` + `ActionsController` (cases existantes) - champs admin supplémentaires (slogan, texte d'accueil, image hero + alt, prédication, don, FAQ CRUD, bascules ministères). Justification : spec 7.4 (modification sans toucher au code).
- `Routes/web.php` + `index.php` (`$publicPages`) - **seulement** si pages légales créées (D7). Justification : spec 4.13.
- `Database/Migrations/…create_schema.php` - **bloc 16 idempotent**, uniquement si décidé : `faq_publique` (id, question, reponse, ordre, publie) et/ou `bacentas.description_publique`+`publie`, `basontas.description_publique`+`publie`, `centres.responsable_public` (texte libre, **jamais** un id user). Aucune suppression, aucune modification de colonne existante. Justification : spec 4.5/4.6/4.10, 7.1.
- `sitemap.xml`, `robots.txt` - domaine réel dès confirmation (D3) ; ajout des pages légales si créées.
- `Views/layouts/layout.php` - correction alt logo (doublon). Justification : hygiène, hors périmètre public.

**À ne PAS faire :** migration de framework, refonte de l'application, suppression de routes/tables/vues, exposition de listes de membres/présences/finances, système de streaming, CMS complet, paiement simulé.

### C.3 Source de contenu par section (§7.1)

| Section | Source | Migration ? |
| --- | --- | --- |
| 4.1 En-tête | `site_settings.nom_affichage`, `assets/images/logo.png` | Non |
| 4.2 Hero | `presentation.accroche` + nouvelles clés `slogan`, `accueil_texte`, `hero_image`, `hero_image_alt` | Non (clé/valeur) |
| 4.3 Horaires | `centre_horaires` + `centres` (adresse) - **même source que 4.5** | Non |
| 4.4 À propos | `presentation.histoire`, `equipe` (`publie=1`) | Non |
| 4.5 Centres | `centres` (publié, seuil), `centres_presentation`, `centre_horaires` | Non |
| 4.6 Vie de l'église | `bacentas`/`basontas` +`publie`/`description_publique` | **Oui, bloc 16** (si section voulue - D5) |
| 4.7 Événements | `evenements` (`publie=1`, futur) | Non |
| 4.8 Médias | `site_settings.lien_direct`, clés prédication | Non |
| 4.9 Communauté | - (masquée, aucun modèle) | Non |
| 4.10 Visite/FAQ | `faq_publique` (si créée) ou texte statique fourni | **Oui, bloc 16** (si table - D6) |
| 4.11 Contact | `site_settings`, contacts par centre | Non |
| 4.12 Engagement | `site_settings.lien_don` uniquement si prestataire vérifié | Non |
| 4.13 Pied | même sources + pages légales (D7) | Non |

### C.4 Stratégies particulières

- **Centres :** une seule source (`PublicSiteRepository::publishedCentres()` + `horairesByCentre()`) alimente `#horaires` (synthèse) et `#centres` (cartes) - spec 4.3/4.5. Seuil de publication **conservé** : adresse + ≥1 horaire + contact (propre ou global) (décision D4). Fiches de détail par centre : non créées (une seule page suffit ; `?page=centresPresentation&id=N` des anciens liens redirige vers `#centres`).
- **Cartes intégrées :** aucune - lien d'itinéraire léger (`itineraire_url`, https validé) ; spec 4.5.
- **Formulaires :** aucun au MVP (pas de formulaire de visite réel côté église → CTA « Planifier ma visite » mène aux infos, spec 2). Formulaire de contact : conditionnel (spec 4.11) - nécessiterait branche POST dédiée, CSRF, validation serveur, anti-spam, politique de confidentialité préalable ; **hors MVP** (décision D8).
- **Liens externes :** `target="_blank" rel="noopener noreferrer"` déjà appliqué ✓ ; seules URLs https validées (`safeUrl()`).

---

## D. Plan de conception (wireframe textuel, haut → bas)

```
EN-TÊTE (sticky) : [logo] Nom officiel | Accueil · Horaires · À propos · Nos centres ·
  [Événements]* · [Culte en ligne]* · Contact | [Planifier ma visite] (CTA, seulement si cible réelle) · Connexion (discret)
  Mobile : bouton menu aria-expanded, Échap, retour du focus (déjà conforme)

#accueil HERO : H1 = nom officiel (unique H1) · slogan (clé dédiée, à valider) ·
  texte d'accueil chaleureux · [support visuel officiel optionnel, repli = dégradé actuel] ·
  [Planifier ma visite] [Découvrir nos centres / Voir les horaires]

#horaires (SOCLE, tôt) : synthèse par centre (jours, heures, lieu) · contact rapide
  (téléphone/e-mail/WhatsApp globaux) · [Itinéraire] par centre · note fuseau horaire (si confirmée)

#a-propos (SOCLE) : portrait court (histoire, vision, mission, valeurs - à fournir) ·
  équipe pastorale* (publie=1, consentements)

#centres (SOCLE, ancre imposée) : carte par centre publié : photo*, nom, adresse complète,
  horaires propres au centre (ajout), contact, [Obtenir l'itinéraire] - aucune carte vide (seuil)

#vie-eglise* (conditionnel) : Bacentas (rassemblements de prière) et Basontas (groupes/services),
  clairement distingués, description/horaire/contact publics uniquement

#evenements* (conditionnel) : événements publiés et futurs (titre, date, lieu, description)

#medias* (conditionnel) : dernière prédication (titre, date, lien plateforme officielle) ·
  [Regarder le culte en direct] si lien fourni · chargement différé si embed (aucun embed au MVP)

#communaute* (conditionnel, hors MVP) : témoignages/approbations avec consentement

#visite* (conditionnel) : première visite + FAQ en accordéon accessible (clavier, SR, indexable)

#contact (SOCLE) : tel:/mailto:/WhatsApp (indicatif international exact) · réseaux officiels

#engagement* (conditionnel) : [Faire un don] uniquement si prestataire sécurisé vérifié ·
  bénévolat via canal réel

PIED (SOCLE) : logo + nom · coordonnées · liens de sections · liens centres · réseaux ·
  [Mentions légales]* [Confidentialité]* · © année dynamique
```

(\* = conditionnel : section **et** entrée de menu masquées sans données publiées.) Ordre conforme à la spec §4 ; le seul ajustement proposé est le contact rapide dans `#horaires` (déjà préconisé par la spec 4.3).

---

## E. Plan technique et découpage en phases

| Phase | Fichiers ou modules concernés | Travaux | Dépendances | Critères de validation |
| --- | --- | --- | --- | --- |
| 1. Audit | - | Ce document (fait) | - | Livré, aucun fichier modifié |
| 2. Validation | - | 5 données bloquantes + décisions D1–D9 | - | Liste signée par le responsable |
| 3. Structure publique & routage | `index.php`, `Routes/web.php` | Vérification (rien à changer sauf D7) | 2 | Critères 1, 2, 12 |
| 4. Contenus & données | `site_settings`, `presentation`, `centres`, `centre_horaires` | Saisie des données bloquantes via `?page=site_public` ; **purge des textes démo** | 2 | Critère 4 |
| 5. Design & composants v2 | `Views/pages/public/*`, `layouts/public.php`, `assets/css/landing.css`, `assets/js/landing.js` | Hero (visuel+slogan+accueil), CTA en-tête, horaires enrichis, horaires sur cartes centres, footer complet, correction CTA sans cible | 2, 3 | Critères 3, 5, 6, 7 |
| 6. Données conditionnelles | Migration bloc 16 (si D5/D6), `PublicSiteRepository`, `PublicSiteService`, admin `site_public` | FAQ, ministères, prédication, (don si D9) | 2, 4 | Sections masquées sans données |
| 7. SEO & données structurées | `layouts/public.php`, `PublicSiteService::jsonLd()`, `sitemap.xml`, `robots.txt` | Nœud `Church` racine, `og:image`, domaine réel | 3, 5 | Critère 8 (Rich Results Test) |
| 8. Sécurité & compatibilité | tous les fichiers modifiés | `php -l`, échappement, validation serveur, HTTPS, revue des nouvelles clés | 5, 6 | Critère 10 |
| 9. Tests & non-régression | scripts d'assertion | Plan G complet | 3–8 | Critères 1–12 |
| 10. Production | `.env`, DNS | HTTPS, canonical, sauvegarde, vérification finale | 9 | Aucun contenu provisoire |

**MVP (lancement) :** phases 3, 4, 5 + 7 (socle + exactitude). **Option au lancement :** phase 6 (FAQ, ministères, prédication) dès que les contenus sont fournis. **Évolution ultérieure :** témoignages, dons, formulaire de contact, cartes intégrées, PMR/accès.

Détail par phase (tâches / livrables / risques / critères d'acceptation) :

- **Ph. 2 (validation)** - livrable : décisions D1–D9 signées + contenus bloquants transmis. Risque : blocage total si le responsable ne fournit pas nom/logo/slogan/adresses/horaires/contacts (spec §11). Critère : checklist §11 spec cochée.
- **Ph. 3** - livrable : aucune modification attendue (routage déjà conforme) ; vérification documentée des 4 cas de session (anonyme privé, connecté, pending, admin). Risque : régression si l'on touche à `$publicPages` - ne touche qu'en cas D7. Critère : 200 sans écran de connexion.
- **Ph. 4** - livrable : données réelles saisies dans `site_settings`/`centres`/`centre_horaires`/`presentation` (relecture des textes démo du seeder). Risque : texte démo publié (A.4-10) - **bloquant**. Critère : chaque champ public relu par le responsable.
- **Ph. 5** - livrables : vues/partials, CSS, JS. Risques : accessibilité du menu (déjà OK) et du futur accordéon ; CLS (réserver `aspect-ratio` aux images). Critères : un seul H1 ; CTA absent sans cible ; aucun débordement horizontal à 320/375/768/1024/1440 px ; Lighthouse mobile.
- **Ph. 6** - livrables : migration additive idempotentes (bloc 16) **uniquement si D5/D6 acceptés**, repo/service/admin. Risque : exposition accidentelle de membres - interdite par construction (lectures sur `nom`, `description_publique`, `publie` uniquement). Critère : section masquée quand vide.
- **Ph. 7** - livrables : JSON-LD étendu, meta og. Critère : validateur Schema.org sans erreur ; sitemap/robots sur le vrai domaine.
- **Ph. 8** - livrable : rapport `php -l` + assertion scripts. Critère : aucune fuite (grep du HTML/JSON rendu : aucun e-mail/nom de membre, aucune présence, aucun montant).
- **Ph. 9–10** - voir G et F.

---

## F. Plan de migration

- **Anciennes URL :** statu quo de la redirection 301 déjà déployée (C.1) - aucun changement de code attendu. Vérifier : `curl -I` sur `?page=apropos` et `?page=centresPresentation` (anonyme → 301 vers `?page=eglise#a-propos`/`#centres`, sans boucle) et sous session admin (200, gestion).
- **Aucune suppression** de contrôleur, vue, route ou table. Aucune migration destructive : le bloc 16 est purement additif (`CREATE TABLE IF NOT EXISTS`, `ALTER TABLE … ADD COLUMN` gardés par `column_exists()`).
- **Contenu :** les textes des deux pages actuelles (histoire, équipe, articles de centre) sont réutilisés, pas dupliqués - la landing lit les mêmes tables ; les champs internes (`vision`, `direction`, `origine`, `objectifs`, `situation_*`) restent privés (décision D9 confirmée : non publiés).
- **Retour arrière :** un commit par phase ; rollback = revert du commit (la migration additive ne casse rien si elle est simplement ignorée ; les anciennes routes redeviennent visibles en retirant la redirection dans `index.php`/`AboutController`). Aucun risque de perte de données.
- **Déploiement :** copie du dossier sur le mutualisé Hostinger (mode opératoire existant) ; `up()` de la migration est idempotent et non destructif (contrairement à `install.php` - **jamais relancé en production**).

---

## G. Plan de tests

Pas de suite automatisée dans le dépôt : scripts d'assertion jetables (`php -d zend.assertions=1 -d assert.exception=1`) + `php -l` + vérifications manuelles, conformément à la convention projet (`CLAUDE.md`).

- **Accès (critère 1) :** `curl` sans cookie sur `/` et `?page=eglise` → 200, contenu publié, **aucun formulaire de connexion** ; idem avec session admin, session `membre`, session `berger` ; compte `pending` (ne peut pas ouvrir de session → reste anonyme → landing).
- **Anciennes URL (critère 2) :** 301 sans boucle (suivi de `-L`), y compris `?page=centresPresentation&id=1` ; navigation privée sans cookie.
- **Confidentialité (critère 10) :** le HTML rendu ne contient aucun e-mail/nom de membre, aucune présence, aucun montant, aucun `responsable_id` ; un centre non publié n'apparaît pas ; un centre sous le seuil n'apparaît pas.
- **Affichage (critères 3, 4) :** chaque centre publié a adresse + horaires + contact ; aucun champ deviné (champ vide = absent de l'affichage) ; aucun texte démo.
- **Liens/boutons (critère 5) :** chaque CTA/lien (`tel:`, `mailto:`, `wa.me/…`, itinéraire, direct, ancres) aboutit ; le CTA principal **n'existe pas** sans cible réelle.
- **Responsive (critère 6) :** 320/375/768/1024/1440 px, pas de débordement horizontal, menu mobile au clavier.
- **Accessibilité (critère 7) :** un seul H1, hiérarchie H2/H3, focus visible, contraste (variables du thème), images avec `alt`, accordéon FAQ au clavier et annoncé aux lecteurs d'écran, `prefers-reduced-motion` - audit Lighthouse/axe + manuel.
- **SEO (critère 8) :** title/meta/canonical/lang, sitemap/robots sur le bon domaine, Rich Results Test pour `Church`/`Event`, même contenu mobile/desktop.
- **Performance (critère 9) :** Lighthouse mobile (LCP ≤ 2,5 s, INP < 200 ms, CLS < 0,1) ; images dimensionnées/compressées si ajoutées ; un seul JS de 3 Ko, CSS 6 Ko (déjà conforme).
- **Formulaires (critère 11) :** N/A au MVP ; si D8 accepté, tests validation serveur, CSRF, anti-spam, états succès/erreur.
- **Non-régression (critère 12) :** connexion (succès → `scope_target()`, échec → `?page=login&error=…`), porte d'accès `verify_access`, dashboard, sections privées par rôle, `site_public` admin (écriture seule admin), suppression/édition CMS existantes.

---

## H. Estimation indicative (non ferme - audit de code terminé, contenu non fourni)

| Bloc | Complexité | Charge indicative |
| --- | --- | --- |
| Ph. 2–4 validation + données | Faible (technique), **dépend entièrement de la réactivité du responsable** | 0,5–1 journée dev + attente contenu |
| Ph. 5 design v2 (hero, horaires, cartes, footer, CTA) | Moyenne | 2–3 journées |
| Ph. 6 sections conditionnelles (FAQ, ministères) | Moyenne (migration additive + admin) | 1–2 journées, **only if D5/D6** |
| Ph. 7 SEO/JSON-LD | Faible | 0,5 journée |
| Ph. 8–9 sécurité + tests | Faible à moyenne | 1 journée |
| **Total développement** | | **≈ 5–8 journées**, contenu humain en sus |

Alourdissent : formulaire de contact avec anti-spam + confidentialité (1–2 j. + relecture juridique), médiathèque de prédications (3+ j., déconseillé - spec : un lien suffit), cartes intégrées (1 j. + poids), module de dons/paiement (hors périmètre technique ici : dépend du prestataire), CMS plus riche (déconseillé, spec 7.4).

---

## I. Risques et décisions ouvertes

| # | Sujet | Recommandation | Doit confirmer le responsable | Classe |
| --- | --- | --- | --- | --- |
| - | Nom officiel, logo | Ne rien corriger (même « Intenationale ») sans validation | Orthographe exacte, logo officiel, droits | **Bloquant** |
| - | Slogan / mission | Aucun texte provisoire en production (7.3) | Slogan approuvé, phrase d'accueil | **Bloquant** |
| - | Adresses, horaires, fuseau, durée | Saisie via `?page=site_public` | Valeurs vérifiées par centre | **Bloquant** |
| - | Contacts (tél., e-mail, WhatsApp, itinéraire) | Un global + optionnels par centre | Numéros exacts avec indicatif (+241 ?) | **Bloquant** |
| - | CTA principal | « Planifier ma visite » (défaut spec) → `#horaires` | Libellé final | **Bloquant** |
| D1 | Route canonique | Garder `/` + `?page=eglise` (déjà déployé) | Accord | Avant dev |
| D2 | Anciennes URL | Garder les 301 (déployés, SEO-safe) | Accord | Avant dev |
| D3 | Domaine | `labelleeglise.com` dans sitemap/robots ressemble à un hébergeur gratuit ; spec dit `lbegf` | Domaine HTTPS de production | Avant dev |
| D4 | Seuil de publication d'un centre | Conserver : adresse + ≥1 horaire + contact | Accord ou seuil différent | Avant dev |
| D5 | Section « Vie de l'église » (4.6) | Créer `description_publique`+`publie` sur bacentas/basontas **uniquement si** l'église veut la section ; jamais de liste de membres | Vouloir la section + fournir descriptions | Optionnel au lancement |
| D6 | FAQ (4.10) | Table `faq_publique` + admin (recommandé) ou texte statique | Fournir les réponses (tenue, durée, enfants, stationnement, PMR, langues, déroulement) | Optionnel au lancement |
| D7 | Pages légales | Créer `mentions_legales` + `confidentialite` (routes publiques, textes fournis par l'église) ; la confidentialité doit précéder tout formulaire/outil de mesure | Textes + accord | Recommandé avant lancement |
| D8 | Formulaire de contact | Hors MVP : aucun traitement réel aujourd'hui | Si desired : canal réel + anti-spam + textes | Ultérieur |
| D9 | Contenus internes (`vision`, `direction`, `origine`, `situation_*`, chiffres) | Restent privés (jamais exposés) | Confirmer le caractère interne | Avant dev |
| - | Image hero / photos / équipe | `pasteur-bg.jpg` et `uploads/*` : droits non confirmés → ne pas publier sans autorisation ; champ `hero_image` avec repli | Droits d'image, consentements équipe | Optionnel au lancement |
| - | Événements vides | Section masquée par défaut (statu quo) ; état vide optionnel | Choix | Avant dev |
| - | Prédications / direct | Lien externe uniquement (`lien_direct` existe ; clés prédication à ajouter) | URLs officielles (YouTube…) | Optionnel |
| - | Dons (4.12) | Aucun moyen de paiement connu : **pas de bouton** ; `lien_don` seulement si prestataire vérifié | Prestataire, usage des dons | Ultérieur |
| - | Analytique | Aucun outil au lancement (pas de justification fournie) | Si desired : outil + consentement + texte confidentialité | Ultérieur |
| - | Textes démo en base (A.4-10) | Relecture/remplacement **avant** toute publication | Contenu réel | **Bloquant** (contenu) |
| - | « Créer un compte » dans le pied | Retirer (hors spec 4.13) ; `register` reste accessible à son URL | Accord | Avant dev |

Aucune de ces décisions ne retarde la structure publique de base (routage + socle déjà conformes).

---

## J. Table de traçabilité (critères 10.1 → tâches → tests)

| Critère | Tâches (phase) | Tests (plan G) |
| --- | --- | --- |
| 1 Accès public | Ph. 3 (vérification `$publicPages`, `/` anonyme) | curl 4 cas de session |
| 2 Remplacement cohérent | Ph. 3 (301 conservés, sidebar unique « Notre église ») | 301 sans boucle, 200 admin |
| 3 Compréhension rapide | Ph. 5 (hero, slogan, CTA en-tête, `#horaires` tôt) | Revue manuelle + screenshots |
| 4 Exactitude | Ph. 4 (saisie réelle, purge démo, seuil centre) | Script : aucun centre incomplet, aucun texte démo |
| 5 Actions fonctionnelles | Ph. 5 (liens construits depuis données, CTA absent sans cible) | Clic manuel chaque lien |
| 6 Responsive | Ph. 5 (CSS mobile-first existant + extensions) | 5 largeurs, 3 navigateurs |
| 7 Accessibilité | Ph. 5 (sémantique, focus, accordéon) | Lighthouse/axe + test clavier |
| 8 SEO | Ph. 7 (meta, canonical, JSON-LD `Church` racine + centres, `Event`, sitemap) | Rich Results Test, validateur Schema.org |
| 9 Performance | Ph. 5/7 (images optimisées, pas d'autoplay, scripts minimes) | Lighthouse : LCP/INP/CLS |
| 10 Confidentialité | Ph. 4/8 (liste blanche colonnes, échappement, HTTPS) | Script de fuite HTML/JSON |
| 11 Formulaires conditionnels | Hors MVP (D8) ; si ajout : validation, CSRF, anti-spam, états | Tests dédiés uniquement si D8 |
| 12 Non-régression | Ph. 3, 9 (rien de supprimé, permissions inchangées) | Login, rôles, admin, écritures CMS |

Conformité 10.2 : le plan repose sur les fichiers réels (chemins vérifiés), décrit une page publique sans connexion et le remplacement fonctionnel des deux pages, réutilise données/images/composants existants, distingue public/privé, justifie chaque modification, couvre responsive/SEO/a11y/sécurité/performance, préserve auth et rôles, comporte migration avec rollback et plan de tests, identifie les contenus manquants, est découpé en phases vérifiables avec traçabilité complète, et ne propose aucun changement de framework ni refonte.

---

## Décisions à valider par un humain avant le développement

**Bloquantes (§11 spec) :**
1. Nom officiel d'affichage exact (confirmer « Intenationale » ou « Internationale ») et logo officiel.
2. Slogan / mission approuvé (aucun texte provisoire en production).
3. Adresses et horaires vérifiés de chaque centre + fuseau horaire (+ durée si annoncée).
4. Moyens de contact officiels et liens d'itinéraire (téléphone, e-mail, WhatsApp avec indicatif international).
5. Libellé du CTA principal (« Planifier ma visite » proposé par défaut).
6. **Contenu réel** de l'accroche et de l'histoire (les textes semés en démo ne doivent jamais être publiés).

**Décisions techniques/contenu (D1–D9 ci-dessus) :**
7. D1 confirmation route canonique (`/` + `?page=eglise`).
8. D2 confirmation stratégie 301 pour les anciennes URL.
9. D3 domaine de production (HTTPS) - sitemap/robots/canonical.
10. D4 seuil minimal de publication d'un centre (proposition : adresse + ≥1 horaire + contact).
11. D5 section « Vie de l'église » voulue ? (implique descriptions publiques de bacentas/basontas)
12. D6 FAQ : table + admin ou statique ? + les réponses (tenue, durée, enfants, stationnement, PMR, langues, déroulement).
13. D7 création des pages mentions légales + politique de confidentialité (textes à fournir).
14. D8 formulaire de contact réel ? (sinon aucun formulaire)
15. D9 confirmation que `vision`/`direction`/`origine`/`objectifs`/`situation_*` restent internes.
16. Droits d'image : photo hero (ex. `pasteur-bg.jpg`), photos de centres, photos/équipe - autorisations écrites.
17. Équipe pastorale publiée (noms, rôles, photos, bios) avec consentements.
18. Prédications/direct : URLs officielles des plateformes.
19. Dons : prestataire de paiement sécurisé vérifié + texte sur l'usage des dons (sinon pas de bouton).
20. Section Événements sans données : masquée (statu quo recommandé) ou état vide ?
21. Rôles autorisés à éditer le site public (recommandé : admin seul, statu quo).
22. Retrait du lien « Créer un compte » du pied de page (recommandé).
23. Analytique : aucun outil au lancement (recommandé) ou outil + consentement.
