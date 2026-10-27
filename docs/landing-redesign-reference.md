# Vitrine publique — relevé de la référence daghewardmills.org

Date : 27 octobre 2026. Objet : documenter les valeurs **mesurées** de la
référence avant de refondre la vitrine de La Belle Église. Ce fichier ne décrit
que la référence et les jetons retenus ; l'inventaire des fichiers modifiés est
dans le compte rendu de livraison.

## Méthode (et limite)

- La sandbox de travail n'a **pas** d'accès réseau sortant vers
  `daghewardmills.org` (seuls GitHub, npm et PyPI sont joignables) : le rendu
  live (capture 1440 px / 390 px + computed styles) n'a donc pas pu être
  produit ici. Chrome headless + Playwright/Puppeteer ont bien été exécutés,
  mais la requête vers le domaine est refusée par le réseau de la sandbox
  (`net::ERR_CONNECTION_CLOSED`).
- Contournement utilisé, sans aucune extrapolation :
  1. le **texte rendu** de la page d'accueil a été récupéré (structure des
     sections, libellés, hiérarchie) ;
  2. la **feuille de styles réellement servie** par le site
     (`/_next/static/chunks/0q5ypilq4313a.css`, archivée) a été lue pour
     extraire la palette, les `@font-face`, les échelles de titres, les
     utilitaires d'espacement, de rayon, d'ombre et de transition.
  Les valeurs ci-dessous sont donc des valeurs **lues dans le CSS de
  production**, pas des estimations à l'œil.
- Les captures « avant / après » de **notre** vitrine, elles, ont été prises
  réellement (1440 × 900 et 390 × 844, Chrome headless), y compris avec des
  données vides pour vérifier que les sections disparaissent.

## 1. Palette mesurée de la référence

| Rôle | Valeur lue | Usage observé |
| --- | --- | --- |
| Noir de fond | `#060608` | fond du document, hero, footer |
| Noir secondaire | `#0d0d10` | surfaces alternées |
| Surface sombre | `#141418`, `#1e1e24` | cartes, panneaux, survols |
| Or (accent) | `#c9a84c` | liens actifs, boutons, eyebrows |
| Or clair | `#e8c97a` | survol des accents |
| Or sombre | `#9a7a2e` | bordures / ombres dorées |
| Blanc chaud | `#f5f3ee` | texte principal sur fond sombre |
| Texte secondaire | `#8a8a96` | légendes, méta |
| Voiles | `black/60`, `black/70`, `black/90`, `[#060608]/70` | dégradés sur photo |

Structure chromatique : site **intégralement sombre** (encre quasi noire) avec
un unique accent or ; pas de bleu, pas de couleur secondaire. Les cartes sont
des surfaces légèrement plus claires que le fond, bordées `white/10`.

## 2. Typographie mesurée

- **Titres** : `Cormorant Garamond` (serif), poids 300 à 700, italique présent.
- **Texte** : `DM Sans` (sans serif), poids 400 à 600, variable.
- Échelle de titres (utilitaires `text-[clamp(...)]` réellement compilés) :
  `clamp(2.2rem, 4vw, 3.5rem)`, `clamp(2.5rem, 6vw, 4.5rem)`,
  `clamp(3rem, 7vw, 6rem)`, `clamp(3rem, 8vw, 6.5rem)` pour les très grands
  titres ; `text-2xl`/`text-3xl` pour les cartes.
- Interlignes : `leading-[0.92]`, `leading-[0.95]`, `leading-none` pour les
  display ; `leading-relaxed` (1.625) pour les paragraphes.
- Eyebrows : `uppercase` + `tracking-[0.2em]` / `tracking-[0.25em]`, taille
  `text-[0.6rem]` à `text-xs` (0.75 rem).
- Graisses : `font-light` (300) pour les grands titres, `font-medium`/
  `font-semibold` pour les libellés.

## 3. Rythme, conteneur, cartes

| Élément | Valeur lue | Repère |
| --- | --- | --- |
| Conteneur | `max-w-7xl` = **80 rem (1280 px)**, `mx-auto` + `px-4/6/8` | largeur de contenu |
| Padding de section | `py-16`, `py-20`, `py-24` (**64 / 80 / 96 px**) | rythme vertical |
| Rayons | `rounded-xl` .75 rem, `rounded-2xl` 1 rem, `rounded-3xl` 1.5 rem | cartes |
| Ombre | `shadow-2xl` = `0 25px 50px -12px rgb(0 0 0 / .25)` | cartes flottantes |
| Transitions | `duration-200/300/500/700`, `ease-in-out` | survols |
| Effets de survol | `hover:scale-105`, `translate-y`, `group-hover:text-[#c9a84c]`, `underline` sur les liens | micro-interactions |
| Flou | `backdrop-blur-sm/md/xl` (8 / 12 / 24 px) | header sur photo |

## 4. Structure de la page de référence (relevée)

1. Barre d'annonce fine (événement à venir + lien).
2. Header fixe : logo + navigation + bouton d'action très visible (or).
3. Hero plein écran : eyebrow, très grand titre, accroche, un CTA primaire,
   bloc « prochain événement » avec compte à rebours, photo plein cadre.
4. À propos : titre court (« A Global Evangelist ») + eyebrow, texte en
   plusieurs paragraphes, lien « Read Full Biography », colonne photo.
5. Bande de chiffres d'impact (grandes valeurs + libellés courts), sur fond
   encre, séparateurs fins.
6. Sections alternées image + texte (campagne, prière en ligne, école biblique).
7. Événements : cartes avec affiche, date + lieu, titre, lien.
8. Ressources/ministères : grille de cartes visuelles.
9. Bloc don/partenariat avec CTA.
10. Footer riche multi-colonnes.

## 5. Jetons retenus pour notre vitrine

La référence est 100 % sombre ; une église avec beaucoup de texte à lire
(horaires, histoire, FAQ) gagne à alterner. Nous conservons donc **l'ADN**
(encre profonde + or + serif display) en ajoutant une **base crème chaude**
pour les sections de lecture, ce qui préserve le contraste et la lisibilité.

| Jeton | Valeur | Origine |
| --- | --- | --- |
| `--lp-ink` | `#0b1020` | encre de la référence (`#060608`), légèrement bleutée pour rester dans l'identité de l'église |
| `--lp-ink-2` / `--lp-ink-3` | `#141a2e` / `#1e2438` | surfaces sombres (équivalent `#141418` / `#1e1e24`) |
| `--lp-gold` | `#c9a84c` | **mesuré** sur la référence |
| `--lp-gold-light` | `#e8c97a` | **mesuré** |
| `--lp-gold-ink` | `#7c5f1f` | dérivé : or assez sombre pour du texte sur crème (AA 5,5:1) |
| `--lp-cream` | `#faf6ee` | fond de lecture chaud |
| `--lp-cream-2` | `#f2ece0` | surface alternée |
| `--lp-text` / `--lp-text-soft` | `#1a1f33` / `#575e74` | corps de texte (15,1:1 et 6,0:1 sur crème) |
| `--lp-on-dark` / `--lp-on-dark-soft` | `#f4f1ea` / `#b9bdcb` | texte sur encre (16,8:1 et 10,1:1) |
| `--lp-font-display` | `Cormorant Garamond` (auto-hébergée) | **mesuré** |
| `--lp-font-body` | `DM Sans` (auto-hébergée, variable) | **mesuré** |
| `--lp-fs-display` | `clamp(2.75rem, 1.9rem + 4.2vw, 5.25rem)` | aligné sur `clamp(3rem, 7vw, 6rem)` de la référence, plafonné pour nos titres longs |
| `--lp-fs-h2` | `clamp(2.15rem, 1.7rem + 2.1vw, 3.6rem)` | échelle des sections |
| `--lp-fs-lead` | `clamp(1.125rem, 1.05rem + 0.4vw, 1.375rem)` | chapeaux |
| `--lp-container` | `1200px` | proche du `max-w-7xl` (1280 px) de la référence |
| `--lp-section-y` | `clamp(64px, 7vw, 120px)` | `py-16/20/24` de la référence |
| `--lp-radius-lg` / `--lp-radius-xl` | `16px` / `24px` | `rounded-2xl` / `3xl` |
| `--lp-shadow-lg` | `0 32px 60px -32px …45 %` | `shadow-2xl` |
| `--lp-dur` / `--lp-dur-slow` | `200ms` / `480ms` | `duration-200/500` |
| Eyebrow | `0.75rem`, `uppercase`, `letter-spacing .22em`, filet horizontal | `tracking-[0.2em]` |

### Contraste (WCAG AA vérifié par calcul)

| Paire | Ratio | Verdict |
| --- | --- | --- |
| texte `#1a1f33` sur crème `#faf6ee` | 15,1:1 | AAA |
| texte doux `#575e74` sur crème | 6,0:1 | AA |
| or-encre `#7c5f1f` sur crème | 5,5:1 | AA |
| or-encre sur blanc | 6,0:1 | AA |
| encre sur bouton or `#c9a84c` | 8,9:1 | AAA |
| texte clair `#f4f1ea` sur encre | 16,8:1 | AAA |
| texte doux clair `#b9bdcb` sur encre | 10,1:1 | AAA |
| or `#c9a84c` sur encre | 8,9:1 | AAA |

L'or de la référence (`#c9a84c`) n'est **jamais** utilisé pour du texte sur
fond clair (2,1:1) : il y est remplacé par `--lp-gold-ink`.

## 6. Polices : auto-hébergement

`assets/vendor/fonts/landing-fonts.css` déclare les deux familles à partir de
fichiers `woff2` du dépôt (aucun CDN, aucun build) :
`cormorant-garamond-latin-{400,500,600}-normal.woff2`,
`cormorant-garamond-latin-{400,600}-italic.woff2`,
`dm-sans-latin-wght-normal.woff2` (variable 100-1000).
Licences SIL OFL 1.1 conservées à côté : `OFL-Cormorant-Garamond.txt`,
`OFL-DM-Sans.txt`.
