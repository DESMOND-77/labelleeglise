<?php
/* Landing publique v2 (spec unifiée 1.0).
   Variables fournies par PublicSiteService::landing().
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

$nav = [['#accueil', 'Accueil']];
if ($hasCentres)     { $nav[] = ['#horaires', 'Horaires']; }
if ($hasAbout)       { $nav[] = ['#a-propos', 'À propos']; }
if ($hasCentres)     { $nav[] = ['#centres', 'Nos centres']; }
if ($hasMinistries)  { $nav[] = ['#vie-eglise', 'Vie de l\'église']; }
if ($hasEvents)      { $nav[] = ['#evenements', 'Événements']; }
if ($hasMedias)      { $nav[] = ['#medias', 'Médias']; }
if ($hasFaq)         { $nav[] = ['#visite', 'Première visite']; }
if ($hasContact)     { $nav[] = ['#contact', 'Contact']; }

/** Adresse lisible d'un centre. */
$addr = static fn(array $c): string => implode(', ', array_filter([$c['adresse'], $c['quartier'], $c['ville']], static fn($v) => $v !== ''));

echo view('pages/public/partials/header', [
    'nav'      => $nav,
    'nom'      => $nom,
    'ctaHref'  => $ctaHref,
    'ctaLabel' => 'Planifier ma visite',
]);
?>
<main id="contenu">
<?php
echo view('pages/public/partials/hero', [
    'nom' => $nom, 'slogan' => $slogan, 'welcome' => $welcome,
    'hero_image' => $hero_image, 'hero_slides' => $hero_slides,
    'hero_image_alt' => $hero_image_alt, 'ctaHref' => $ctaHref,
    'ctaLabel' => 'Planifier ma visite', 'hasCentres' => $hasCentres,
]);
if ($hasCentres) {
    echo view('pages/public/partials/horaires', ['centres' => $centres, 'contact' => $contact, 'fuseau' => $fuseau, 'addr' => $addr]);
}
if ($hasAbout) {
    echo view('pages/public/partials/a_propos', ['histoire' => $histoire, 'accroche' => $accroche, 'equipe' => $equipe]);
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
    'nav' => $nav, 'centres' => $centres,
    'has_mentions_legales' => $has_mentions_legales, 'has_confidentialite' => $has_confidentialite,
]);
