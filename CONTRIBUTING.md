# CONTRIBUTING — La Belle Église

Merci de contribuer à ce projet ! Ce guide définit les conventions de code,
l'architecture attendue et le workflow de contribution.

---

## 1. Conventions de code

### PHP
- **PSR-12** : espacement, accolades, `declare(strict_types=1)`.
- **typage** : déclarer les types des paramètres et retours.
- **Namespaces** : `App\Core`, `App\Controllers`, `App\Services`,
  `App\Repositories`, `App\Middleware`, `App\Auth`, `App\Helpers`.
- **Commentaires** : docblocks courts, uniquement quand nécessaire (utilité,
  comportement non évident). Pas de commentaires redondants.

### Organisation
- **Controllers** : requête → validation → service/repository → vue. Jamais de SQL.
- **Services** : logique métier pure. Jamais de HTML.
- **Repositories** : **tout** le SQL. Jamais de HTML / logique d'affichage.
- **Views** : HTML uniquement, avec de simples boucles/conditions (`<?= $var ?>`).
  Jamais de SQL ni de logique métier.

### Principes
- **SOLID**, **DRY**, **KISS**, **YAGNI**.
- Pas de classes « God », pas de contrôleurs massifs, pas de code dupliqué.
- Ajouter une fonctionnalité en traversant les couches (controller → service →
  repository) plutôt que d'injecter du SQL dans une vue.

---

## 2. Ajouter une nouvelle page

1. **Route** : déclarer dans `Routes/web.php` le mapping `page` → contrôleur/méthode.
2. **Contrôleur** : ajouter la méthode dans le contrôleur concerné (ou un nouveau).
3. **Service/Repository** : placer la logique métier et/ou les requêtes au bon endroit.
4. **Vue** : créer le template dans `Views/pages/` (+ `forms/`, `partials/` si besoin).
5. **Rendu** : utiliser `render_page($titre, $content, $charts?)` (layout automatique).

---

## 3. Ajouter une action POST

1. Ajouter le `case` dans `ActionsController::postAction()` (avec `check_csrf()`).
2. Valider la saisie via le `Validator` / services.
3. Écrire via un **Repository** (jamais de SQL dans le contrôleur).
4. Terminer par une **redirection** (`redirect()`).

---

## 4. Ajouter une table

1. Étendre la migration `Database/Migrations/2024_01_01_000000_create_schema.php`.
2. Créer un **Repository** dédié regroupant les requêtes de cette table.
3. Si besoin, un **Service** pour la logique métier associée.
4. Mettre à jour le **Seeder** si des données de démonstration sont utiles.

---

## 5. CSS / Frontend

- CSS **modulaire** dans `assets/css/` (variables, reset, typography, layout,
  sidebar, topbar, dashboard, cards, buttons, forms, tables, modal, alerts,
  utilities, animations, responsive, components).
- Utiliser les **variables CSS** (`assets/css/variables.css`) pour les couleurs,
  espacements, rayons, ombres. Pas de valeurs en dur.
- **Aucune injection** de gros blocs CSS/JS inline dans les vues.
- JavaScript léger dans `assets/js/app.js` (pas de framework).

---

## 6. Workflow Git

- Branche de fonctionnalité : `feature/ma-fonctionnalite`.
- Message de commit clair et concis (imperatif) :
  - `feat: ajouter la gestion des visites`
  - `fix: corriger le pointage de présence`
  - `refactor: extraire ContributionRepository`
  - `docs: mettre à jour ARCHITECTURE.md`
- **Ne pas casser** les URL, l'authentification, les images, les uploads.
- **Vérifier** que toutes les pages rendent avant de pousser.

---

## 7. Tests / Vérification

Avant de soumettre une PR :

1. `php -l <fichier>` pour chaque fichier PHP modifié.
2. Vérifier le **login** (admin + berger + responsable).
3. Parcourir toutes les **sections** (GET 200).
4. Tester une **écriture POST** (avec CSRF).
5. Vérifier que les **images** et **uploads** se chargent.
6. Vérifier la **responsive** (desktop, tablette, mobile).

---

## 8. Environnement

- **Aucun Composer / CLI requis** pour déployer. L'autoloader est maison.
- Adaptez uniquement `Config/database.php` à votre environnement.
- `install.php` est **uniquement** pour l'installation initiale (à supprimer ensuite).
</content>
