<?php
/**
 * Coquille de la vitrine publique (document autonome, sans sidebar).
 * Variables : $title, $content, $brand, $canonical, $jsonLd, $hasUser.
 */
$metaDescription = 'Découvrez ' . $brand . ' : horaires des cultes, centres et contacts.';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($title) ?></title>
  <meta name="description" content="<?= h($metaDescription) ?>">
<?php if ($canonical !== ''): ?>
  <link rel="canonical" href="<?= h($canonical) ?>">
<?php endif; ?>
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?= h($title) ?>">
  <meta property="og:description" content="<?= h($metaDescription) ?>">
  <meta property="og:locale" content="fr_FR">
  <link rel="icon" type="image/png" sizes="96x96" href="assets/images/favicon-96x96.png">
  <link rel="icon" href="assets/images/favicon.svg" type="image/svg+xml" sizes="any">
  <link rel="icon" href="assets/images/favicon.ico">
  <link rel="apple-touch-icon" href="assets/images/apple-touch-icon.png">
  <link rel="stylesheet" href="assets/vendor/fonts/inter.css">
  <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="assets/css/variables.css">
  <link rel="stylesheet" href="assets/css/landing.css">
<?php if ($jsonLd): ?>
  <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
</head>

<body class="landing">
  <a class="skip-link" href="#contenu">Aller au contenu</a>
  <?= $content ?>
  <script src="assets/js/landing.js" defer></script>
</body>

</html>
