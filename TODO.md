# Plan: Remplacer les icônes emoji par Font Awesome

## Étapes
- [x] 1. Analyser le code (repérer tous les emojis/icônes)
- [x] 2. Ajouter le CDN Font Awesome dans `layout.php` et `login.php`
- [x] 3. Mettre à jour `SECTION_ICONS` dans `config.php`
- [x] 4. Mettre à jour `render.php` (empty_state, breadcrumb, back_button)
- [x] 5. Mettre à jour `pages_sections.php` (cartas, onglets, tableaux)
- [x] 6. Mettre à jour `pages_apropos.php` (team_card_html, actions)
- [x] 7. Mettre à jour `pages_parametres.php` (onglets, actions)
- [x] 8. Mettre à jour `pages_bergers.php` (onglets fiche)
- [x] 9. Mettre à jour `pages.php` (empty_state)
- [x] 10. Mettre à jour les vues templates (layout, accueil, apropos, etc.)
- [x] 11. Vérifier le rendu final

## Détails
- Icônes FAQ : `phi` → `fa-solid fa-pen` (Modifier), `🗑` → `fa-solid fa-trash` (Supprimer)
- Icônes méthodes : `fa-solid fa-...`
- Remplacer les emojis UI par `<i class="fa-solid fa-*"></i>`
- Laisser les données emoji utilisateur (seed.php, forms/equipe.php) intactes
