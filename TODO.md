# Refonte UI — La Belle Église

## Objectif
Transformer l'application en un SaaS premium moderne (design système complet)
tout en préservant TOUTE la logique métier, les routes, les IDs et les hooks JS.

## Milestones

### ✅ Milestone 0 — Analyse du dépôt
- [x] Lire tous les fichiers (templates, CSS, JS, PHP, config)
- [x] Cartographier les hooks JS / classes CSS à préserver
- [x] Valider le plan auprès de l'utilisateur

### ✅ Milestone 1 — Fondations du design system (architecture CSS)
- [x] Créer `assets/css/variables.css` (tokens : couleurs, typo, espacements, rayons, ombres, animations)
- [x] Créer `assets/css/reset.css`
- [x] Créer `assets/css/typography.css`
- [x] Créer `assets/css/layout.css`
- [x] Créer `assets/css/sidebar.css`
- [x] Créer `assets/css/topbar.css`
- [x] Créer `assets/css/dashboard.css`
- [x] Créer `assets/css/cards.css`
- [x] Créer `assets/css/buttons.css`
- [x] Créer `assets/css/forms.css`
- [x] Créer `assets/css/tables.css`
- [x] Créer `assets/css/modal.css`
- [x] Créer `assets/css/alerts.css`
- [x] Créer `assets/css/utilities.css`
- [x] Créer `assets/css/animations.css`
- [x] Créer `assets/css/responsive.css`
- [x] Créer `assets/css/components.css`
- [x] Transformer `app.css` en point d'entrée (`@import`)
- [x] Mettre à jour les couleurs des graphiques dans `config.php` et `app.js`

### ✅ Milestone 2 — Coquille applicative (layout.php)
- [x] Redesign de la sidebar (collapse, indicateur actif, groupes, hover)
- [x] Redesign de la topbar (sticky, recherche, notifications, menu profil)
- [x] Ajouter le toggle de collapse sidebar
- [x] Mettre à jour app.js (collapse sidebar, dropdown profil, password toggle)

### ✅ Milestone 3 — Page de connexion (login.php)
- [x] Fullscreen hero + glassmorphism card
- [x] Floating labels, password visibility toggle
- [x] Animated button, hints modernisés

### ✅ Milestone 4 — Refonte des templates de pages
- [x] Dashboard (accueil) : welcome banner, stats, carrousel, graphiques, synthèse
- [x] Tables membres / finances / paramètres (classes partagées héritées du design system)
- [x] Formulaires (tous les forms/*) — styles modernes hérités
- [x] Cards (unités, équipe, centres) — héritées
- [x] Profil (avatar header + layout)
- [x] Recherche (page de recherche avec champ + résultats)
- [x] Porte d'accès (gate) — header icône cadenas
- [x] Suivi hebdo / fiche berger / à propos — classes partagées

### ✅ Milestone 5 — Responsive & Accessibilité
- [x] Sidebar → drawer sur mobile (menu-toggle)
- [x] Tables responsives (table-wrap overflow-x)
- [x] Focus-visible, aria-labels, contrast
- [x] Animations subtiles (transitions CSS)

### ✅ Milestone 6 — QA finale
- [x] Chaque page vérifiée (toutes 200)
- [x] Aucun overflow / layout cassé
- [x] Pas de CSS dupliqué (architecture modulaire)
- [x] Images préservées (logo.png, pasteur-bg.jpg, uploads)
- [x] Fonctionnalité intacte (toutes les pages rendent, charts présents)
