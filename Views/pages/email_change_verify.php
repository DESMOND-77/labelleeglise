<?php
/* Résultat de la vérification du changement d'email. Variables : $status, $user (nullable). */
$status = $status ?? 'invalid';
$user = $user ?? null;

$copy = [
    'ok' => [
        'icon'  => 'fa-solid fa-circle-check',
        'class' => 'success',
        'title' => 'Adresse email confirmée',
        'text'  => 'Votre nouvelle adresse email est maintenant active. Vous pouvez vous reconnecter avec cette '
                 . 'nouvelle adresse — votre ancienne adresse ne fonctionne plus pour la connexion.',
    ],
    'expired' => [
        'icon'  => 'fa-solid fa-triangle-exclamation',
        'class' => 'warning',
        'title' => 'Lien expiré',
        'text'  => 'Ce lien de confirmation a expiré (validité 24 heures). Reconnectez-vous avec votre adresse '
                 . 'actuelle puis relancez la demande de changement d\'email depuis "Mon profil".',
    ],
    'invalid' => [
        'icon'  => 'fa-solid fa-circle-xmark',
        'class' => 'danger',
        'title' => 'Lien invalide',
        'text'  => 'Ce lien de confirmation est invalide ou a déjà été utilisé.',
    ],
][$status] ?? null;
if ($copy === null) {
    $copy = ['icon' => 'fa-solid fa-circle-xmark', 'class' => 'danger', 'title' => 'Lien invalide', 'text' => 'Ce lien de confirmation est invalide.'];
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Changement d'email — <?= h(APP_NAME) ?></title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" type="image/png" sizes="96x96" href="assets/images/favicon-96x96.png">
  <link rel="icon" href="assets/images/favicon.svg" type="image/svg+xml" sizes="any">
  <link rel="icon" href="assets/images/favicon.ico">
  <link rel="apple-touch-icon" href="assets/images/apple-touch-icon.png">
  <link rel="manifest" href="assets/images/site.webmanifest">
  <meta name="theme-color" content="#4F46E5" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>
  <div class="login-screen" id="loginScreen">
    <div class="login-card register-result-card">
      <div class="login-logo"><img src="/assets/images/logo.png" alt="<?= h(APP_NAME) ?>"></div>
      <div class="result-icon <?= h($copy['class']) ?>"><i class="<?= h($copy['icon']) ?>"></i></div>
      <h1><?= h($copy['title']) ?></h1>
      <p class="login-sub"><?= h($copy['text']) ?></p>
      <a class="btn btn-primary btn-block btn-lg" href="index.php">Se connecter</a>
    </div>
  </div>
</body>

</html>
