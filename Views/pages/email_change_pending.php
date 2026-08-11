<?php /* "Vérifiez votre nouvel email" — affichée juste après la déconnexion immédiate (spec §12). */ ?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vérifiez votre nouvel email — <?= h(APP_NAME) ?></title>
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
      <div class="result-icon warning"><i class="fa-solid fa-envelope-circle-check"></i></div>
      <h1>Vérifiez votre nouvel email</h1>
      <p class="login-sub">
        Vous avez été déconnecté(e) car une demande de changement d'adresse email est en cours.
        Un email de vérification vient d'être envoyé à votre <strong>nouvelle</strong> adresse.
        Ouvrez votre boîte de réception et cliquez sur le lien pour confirmer le changement.
      </p>
      <p class="register-hint">Tant que vous n'aurez pas cliqué sur ce lien, votre ancienne adresse email reste inchangée mais votre session reste déconnectée.</p>
      <a class="btn btn-primary btn-block btn-lg" href="index.php">Retour à la connexion</a>
    </div>
  </div>
</body>

</html>
