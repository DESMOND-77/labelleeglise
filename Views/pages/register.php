<?php
/* Formulaire d'inscription publique. Variables : $errors (array), $old (array), $sent (bool). */
$errors = $errors ?? [];
$old = $old ?? [];
$sent = $sent ?? false;
$v = static fn(string $k) => h($old[$k] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Créer un compte — <?= h(APP_NAME) ?></title>
  <meta name="description" content="Créez votre compte sur la plateforme de gestion de <?= h(APP_NAME) ?>. Votre inscription sera vérifiée par email puis validée par un administrateur.">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="<?= h(url('index.php', ['page' => 'register'])) ?>">
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>
  <div class="login-screen" id="loginScreen">
    <?php if ($sent): ?>
      <div class="login-card register-result-card">
        <div class="login-logo"><img src="/assets/images/logo.png" alt="<?= h(APP_NAME) ?>"></div>
        <div class="result-icon success"><i class="fa-solid fa-envelope-circle-check"></i></div>
        <h1>Inscription réussie</h1>
        <p class="login-sub">Un email de vérification vient de vous être envoyé. Ouvrez votre boîte de réception
          et cliquez sur le lien pour confirmer votre adresse email.</p>
        <p class="register-hint">Vous n'avez rien reçu ? Vérifiez vos indésirables, ou réessayez dans quelques minutes.</p>
        <a class="btn btn-primary btn-block btn-lg" href="index.php">Retour à la connexion</a>
      </div>
    <?php else: ?>
      <form class="login-card register-card" method="post" action="index.php" novalidate>
        <input type="hidden" name="action" value="register">
        <?= csrf_field() ?>
        <div class="login-logo"><img src="/assets/images/logo.png" alt="<?= h(APP_NAME) ?>"></div>
        <h1>Créer un compte</h1>
        <p class="login-sub">Rejoignez <?= h(APP_NAME) ?> — votre inscription sera vérifiée par email puis validée par un administrateur.</p>

        <?php if ($errors): ?>
          <div class="modal-error show"><i class="fa-solid fa-triangle-exclamation"></i> Merci de corriger les champs signalés ci-dessous.</div>
        <?php endif; ?>

        <div class="form-grid register-grid">
          <div class="floating-field">
            <input type="text" id="regPrenom" name="prenom" placeholder=" " value="<?= $v('prenom') ?>" required autofocus>
            <label for="regPrenom">Prénom</label>
          </div>
          <div class="floating-field">
            <input type="text" id="regNom" name="nom" placeholder=" " value="<?= $v('nom') ?>" required>
            <label for="regNom">Nom</label>
          </div>
        </div>
        <?php if (!empty($errors['prenom']) || !empty($errors['nom'])): ?>
          <div class="field-error"><?= h($errors['prenom'] ?? $errors['nom']) ?></div>
        <?php endif; ?>

        <div class="floating-field">
          <input type="email" id="regEmail" name="email" placeholder=" " value="<?= $v('email') ?>" required autocomplete="email">
          <label for="regEmail">Email</label>
        </div>
        <?php if (!empty($errors['email'])): ?><div class="field-error"><?= h($errors['email']) ?></div><?php endif; ?>

        <div class="floating-field">
          <input type="tel" id="regTelephone" name="telephone" placeholder=" " value="<?= $v('telephone') ?>" required autocomplete="tel">
          <label for="regTelephone">Téléphone</label>
        </div>
        <?php if (!empty($errors['telephone'])): ?><div class="field-error"><?= h($errors['telephone']) ?></div><?php endif; ?>

        <div class="floating-field">
          <div class="password-wrap">
            <input type="password" id="regPassword" name="password" placeholder=" " required autocomplete="new-password" minlength="8">
            <label for="regPassword">Mot de passe (8 caractères minimum)</label>
            <button type="button" class="password-toggle" data-toggle-for="regPassword" title="Afficher le mot de passe" aria-label="Afficher le mot de passe">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>
        </div>
        <?php if (!empty($errors['password'])): ?><div class="field-error"><?= h($errors['password']) ?></div><?php endif; ?>

        <div class="floating-field">
          <div class="password-wrap">
            <input type="password" id="regPasswordConfirm" name="password_confirm" placeholder=" " required autocomplete="new-password" minlength="8">
            <label for="regPasswordConfirm">Confirmer le mot de passe</label>
            <button type="button" class="password-toggle" data-toggle-for="regPasswordConfirm" title="Afficher le mot de passe" aria-label="Afficher le mot de passe">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>
        </div>
        <?php if (!empty($errors['password_confirm'])): ?><div class="field-error"><?= h($errors['password_confirm']) ?></div><?php endif; ?>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Créer mon compte</button>

        <div class="login-hint register-back">
          <span>Déjà inscrit ? <a href="index.php">Se connecter</a></span>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      document.querySelectorAll(".password-toggle[data-toggle-for]").forEach(function (toggle) {
        var input = document.getElementById(toggle.getAttribute("data-toggle-for"));
        if (!input) return;
        toggle.addEventListener("click", function () {
          var show = input.type === "password";
          input.type = show ? "text" : "password";
          toggle.innerHTML = show
            ? '<i class="fa-regular fa-eye-slash"></i>'
            : '<i class="fa-regular fa-eye"></i>';
        });
      });
    });
  </script>
</body>

</html>
