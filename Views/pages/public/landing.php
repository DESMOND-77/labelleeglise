<?php
/* Landing publique v2 (spec unifiée 1.0).
   Variables fournies par PublicSiteService::landing() (y compris impact,
   image « à propos » et prochain événement : aucun calcul ici).
   Chaque section n'apparaît que si ses données publiables existent :
   rien n'est fabriqué, aucun bouton décoratif. */

$hasCentres = $centres !== [];
$hasEquipe  = $equipe !== [];
$hasEvents  = $evenements !== [];
$hasAbout   = $accroche !== '' || $histoire !== '' || $hasEquipe;
$hasContact = $contact !== [] || $socials !== [] || $adresse !== '';
$hasMedias  = $direct !== '' || $predication['url'] !== '';
$hasFaq     = $faq !== [];
$hasEngagement = $lien_don !== '';
$welcome = $accueil_texte !== '' ? $accueil_texte : $accroche;

/* Le CTA principal n'existe que s'il mène quelque part de réel. */
$ctaHref = $hasCentres ? '#horaires' : ($hasContact ? '#contact' : '');
$ctaLabel = 'Planifier ma visite';

/** Adresse lisible d'un centre. */
$addr = static fn(array $c): string => implode(', ', array_filter([$c['adresse'], $c['quartier'], $c['ville']], static fn($v) => $v !== ''));

/* ---------- Navigation (ordre = ordre réel des sections) ---------- */
$navAll = [['#accueil', 'Accueil']];
if ($hasAbout)       { $navAll[] = ['#a-propos', 'À propos']; }
if ($hasCentres)     { $navAll[] = ['#horaires', 'Horaires']; }
if ($hasCentres)     { $navAll[] = ['#centres', 'Nos centres']; }
if ($hasMinistries)  { $navAll[] = ['#vie-eglise', 'Vie de l\'église']; }
if ($hasEvents)      { $navAll[] = ['#evenements', 'Événements']; }
if ($hasMedias)      { $navAll[] = ['#medias', 'Médias']; }
if ($hasFaq)         { $navAll[] = ['#visite', 'Première visite']; }
if ($hasContact)     { $navAll[] = ['#contact', 'Contact']; }

/* En-tête : jeu réduit pour rester lisible sur une seule ligne. */
$navPrimary = [];
foreach ($navAll as [$href, $label]) {
    $keep = ['#accueil', '#a-propos', '#horaires', '#centres', '#evenements', '#contact'];
    if (in_array($href, $keep, true)) {
        $navPrimary[] = [$href, $label];
    }
}
if (count($navPrimary) < 2) {
    $navPrimary = $navAll;
}

echo view('pages/public/partials/annonce', [
    'nextEvent' => $next_event,
]);

echo view('pages/public/partials/header', [
    'nav'      => $navPrimary,
    'nom'      => $nom,
    'brand'    => $nom,
    // Sous-titre : la localisation, uniquement si le nom ne la contient pas déjà.
    'brandSub' => (defined('APP_LOCATION') && mb_stripos($nom, (string) APP_LOCATION) === false)
        ? (string) APP_LOCATION
        : '',
    'ctaHref'  => $ctaHref,
    'ctaLabel' => $ctaLabel,
]);
?>
<main id="contenu">
<?php
echo view('pages/public/partials/hero', [
    'nom' => $nom, 'slogan' => $slogan, 'welcome' => $welcome,
    'hero_image' => $hero_image, 'hero_slides' => $hero_slides,
    'hero_image_alt' => $hero_image_alt, 'ctaHref' => $ctaHref,
    'ctaLabel' => $ctaLabel, 'hasCentres' => $hasCentres,
    'nextEvent' => $next_event,
    'centres' => $centres, 'adresse' => $adresse,
]);
if ($hasAbout) {
    echo view('pages/public/partials/a_propos', [
        'histoire' => $histoire,
        'accroche' => $accroche,
        'equipe' => $equipe,
        'impact' => $impact,
        'image' => $about_image,
        'imageAlt' => $about_image_alt,
        'aboutTitle' => $about_title,
    ]);
}
if ($hasCentres) {
    echo view('pages/public/partials/horaires', ['centres' => $centres, 'contact' => $contact, 'fuseau' => $fuseau, 'addr' => $addr]);
}
if ($hasCentres) {
    echo view('pages/public/partials/centres', ['centres' => $centres, 'addr' => $addr]);
}
if ($hasMinistries) {
    echo view('pages/public/partials/vie_eglise', ['ministries' => $ministries]);
}
if ($hasEvents) {
    echo view('pages/public/partials/evenements', ['evenements' => $evenements]);
}
if ($hasMedias) {
    echo view('pages/public/partials/medias', ['predication' => $predication, 'direct' => $direct]);
}
if ($hasFaq) {
    echo view('pages/public/partials/visite', ['faq' => $faq]);
}
if ($hasContact) {
    echo view('pages/public/partials/contact', ['contact' => $contact, 'socials' => $socials, 'adresse' => $adresse]);
}
if ($hasEngagement) {
    echo view('pages/public/partials/engagement', ['lien_don' => $lien_don, 'don_usage' => $don_usage, 'hasContact' => $hasContact]);
}
?>
</main>
<?php
echo view('pages/public/partials/footer', [
    'nom' => $nom, 'adresse' => $adresse, 'contact' => $contact, 'socials' => $socials,
    'nav' => $navAll, 'centres' => $centres,
    'has_mentions_legales' => $has_mentions_legales, 'has_confidentialite' => $has_confidentialite,
]);
