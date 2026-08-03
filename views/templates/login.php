<?php /* Écran de connexion (par email). Variable : $error (bool). */ ?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion — <?= h(APP_NAME) ?></title>
  <link rel="stylesheet" href="assets/css/app.css">
</head>

<body>
  <div class="login-screen" id="loginScreen">
    <form class="login-card" method="post" action="index.php">
      <input type="hidden" name="action" value="login">
      <?= csrf_field() ?>
      <div class="login-logo"><img src="/assets/images/logo.png" alt="La Belle Église Internationale Franceville"></div>
      <h1><?= h(APP_NAME) ?></h1>
      <p class="login-sub">Espace de gestion des membres, bacentas, centres et finances</p>

      <label class="field">
        <span>Email</span>
        <input type="email" id="loginEmail" name="email" placeholder="ex. admin@labelleeglise.ga" required autofocus>
      </label>
      <label class="field">
        <span>Mot de passe</span>
        <input type="password" id="loginPassword" name="password" placeholder="••••••" required>
      </label>

      <div class="modal-error" id="loginError" style="<?= !empty($error) ? 'display:block;' : 'display:none;' ?>">
        Email ou mot de passe incorrect (ou compte inactif).
      </div>

      <button type="submit" class="btn btn-primary btn-block">Se connecter</button>

      <div class="login-hint">
        <strong>Comptes de démonstration</strong>
        <span>admin@labelleeglise.ga / LBEGF — accès complet</span>
        <span>user@labelleeglise.ga / user1111 — accès limité</span>
        <span>resp.bacenta.sion@labelleeglise.ga / ESKLna</span>
        <span>berger.eric.bongo@labelleeglise.ga / BergerEB1</span>
      </div>
    </form>
  </div>
</body>

</html>