<?php
/* Écran de connexion (par email). Variable : $error (string|null) — reason ∈ invalid|not_verified|pending|disabled. */
$error = $error ?? null;
$errorMessages = [
  'invalid'      => 'Email ou mot de passe incorrect.',
  'not_verified' => 'Veuillez vérifier votre adresse email avant de vous connecter. Consultez votre boîte de réception.',
  'pending'      => 'Votre adresse email est vérifiée. Votre compte est actuellement en attente de validation par un administrateur.',
  'disabled'     => 'Votre compte est actuellement désactivé. Contactez l\'administration pour plus d\'informations.',
];
$errorMessage = $error ? ($errorMessages[$error] ?? $errorMessages['invalid']) : null;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion — <?= h(APP_NAME) ?></title>
  <!-- Écran de connexion : utilitaire, jamais indexé. -->
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
    <form class="login-card" method="post" action="index.php" novalidate>
      <input type="hidden" name="action" value="login">
      <?= csrf_field() ?>
      <div class="login-logo"><img src="/assets/images/logo.png" alt="La Belle Église Internationale Franceville"></div>
      <h1><?= h(APP_NAME) ?></h1>
      <p class="login-sub">Plateforme de gestion des membres, bacentas, centres et finances</p>

      <div class="floating-field">
        <input type="email" id="loginEmail" name="email" placeholder=" " required autocomplete="email" autofocus>
        <label for="loginEmail">Email</label>
      </div>

      <div class="floating-field">
        <div class="password-wrap">
          <input type="password" id="loginPassword" name="password" placeholder=" " required autocomplete="current-password">
          <label for="loginPassword">Mot de passe</label>
          <button type="button" class="password-toggle" id="passwordToggle" title="Afficher le mot de passe" aria-label="Afficher le mot de passe">
            <i class="fa-regular fa-eye"></i>
          </button>
        </div>
      </div>

      <div class="modal-error<?= $errorMessage ? ' show' : '' ?>" id="loginError" style="<?= $errorMessage ? 'display:block;' : 'display:none;' ?>">
        <i class="fa-solid fa-triangle-exclamation"></i> <?= h($errorMessage ?? '') ?>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg">Se connecter</button>

      <div class="login-hint">
        <strong>Comptes de démonstration</strong>
        <span><code>admin@labelleeglise.ga</code> / <code>LBEGF</code> — accès complet</span>
        <span><code>user@labelleeglise.ga</code> / <code>user1111</code> — accès limité</span>
        <span><code>resp.bacenta.sion@labelleeglise.ga</code> / <code>ESKLna</code></span>
        <span><code>berger.eric.bongo@labelleeglise.ga</code> / <code>BergerEB1</code></span>
      </div>

      <div class="login-hint register-back">
        <span>Pas encore de compte ? <a href="<?= h(url('index.php', ['page' => 'register'])) ?>">Créer un compte</a></span>
      </div>
    </form>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      var toggle = document.getElementById("passwordToggle");
      var input = document.getElementById("loginPassword");
      if (toggle && input) {
        toggle.addEventListener("click", function() {
          var show = input.type === "password";
          input.type = show ? "text" : "password";
          toggle.innerHTML = show ?
            '<i class="fa-regular fa-eye-slash"></i>' :
            '<i class="fa-regular fa-eye"></i>';
          toggle.setAttribute("aria-label", show ? "Masquer le mot de passe" : "Afficher le mot de passe");
        });
      }
    });
  </script>
</body>

</html>