<?php
/* Résultat de la vérification d'email. Variables : $status, $user (nullable). */
$status = $status ?? 'invalid';
$user = $user ?? null;

$copy = [
    'ok' => [
        'icon'  => 'fa-solid fa-circle-check',
        'class' => 'success',
        'title' => 'Adresse email vérifiée',
        'text'  => 'Votre demande est maintenant en attente de validation par un administrateur de '
                 . h(APP_NAME) . '. Vous recevrez un email dès que votre compte sera activé.',
    ],
    'already_verified' => [
        'icon'  => 'fa-solid fa-circle-check',
        'class' => 'success',
        'title' => 'Adresse déjà vérifiée',
        'text'  => 'Votre adresse email a déjà été confirmée. Si votre compte n\'est pas encore actif, '
                 . 'un administrateur doit encore valider votre inscription.',
    ],
    'expired' => [
        'icon'  => 'fa-solid fa-triangle-exclamation',
        'class' => 'warning',
        'title' => 'Lien expiré',
        'text'  => 'Ce lien de vérification a expiré (validité 24 heures). Merci de vous réinscrire ou de '
                 . 'contacter l\'administration pour recevoir un nouveau lien.',
    ],
    'invalid' => [
        'icon'  => 'fa-solid fa-circle-xmark',
        'class' => 'danger',
        'title' => 'Lien invalide',
        'text'  => 'Ce lien de vérification est invalide ou a déjà été utilisé. Si vous pensez qu\'il s\'agit '
                 . 'd\'une erreur, réessayez de vous inscrire.',
    ],
][$status] ?? null;
if ($copy === null) {
    $copy = ['icon' => 'fa-solid fa-circle-xmark', 'class' => 'danger', 'title' => 'Lien invalide', 'text' => 'Ce lien de vérification est invalide.'];
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vérification email — <?= h(APP_NAME) ?></title>
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>
  <div class="login-screen" id="loginScreen">
    <div class="login-card register-result-card">
      <div class="login-logo"><img src="/assets/images/logo.png" alt="<?= h(APP_NAME) ?>"></div>
      <div class="result-icon <?= h($copy['class']) ?>"><i class="<?= h($copy['icon']) ?>"></i></div>
      <h1><?= h($copy['title']) ?></h1>
      <p class="login-sub"><?= $copy['text'] ?></p>
      <a class="btn btn-primary btn-block btn-lg" href="index.php">Retour à la connexion</a>
    </div>
  </div>
</body>

</html>
