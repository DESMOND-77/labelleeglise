<?php
/* "Mon profil" — libre-service. Variables : $user, $psaved (bool), $perror
 * (string code), $psection (string), $csrf. */
$errorMessages = [
    'validation'      => 'Merci de corriger les champs signalés ci-dessous.',
    'invalid_image'   => "Le fichier envoyé n'est pas une image valide.",
    'current_invalid' => 'Le mot de passe actuel est incorrect.',
    'too_short'        => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
    'mismatch'        => 'La confirmation ne correspond pas au nouveau mot de passe.',
    'invalid'         => 'Adresse email invalide.',
    'same_email'      => 'Cette adresse est déjà votre adresse actuelle.',
    'taken'           => 'Cette adresse email est déjà utilisée par un autre compte.',
];
$errorMsg = $perror !== '' ? ($errorMessages[$perror] ?? 'Une erreur est survenue.') : '';
?>
<?= section_toolbar('Mon profil', 'Informations personnelles, photo, sécurité') ?>

<?php if ($psaved): ?>
  <div class="modal-error show" style="background:#ECFDF5;color:#065F46;border-color:#A7F3D0;"><i class="fa-solid fa-circle-check"></i> Modifications enregistrées.</div>
<?php endif; ?>
<?php if ($errorMsg !== ''): ?>
  <div class="modal-error show"><i class="fa-solid fa-triangle-exclamation"></i> <?= h($errorMsg) ?></div>
<?php endif; ?>

<div class="profile-grid">
  <!-- ===================== Informations personnelles + photo ===================== -->
  <div class="form-card">
    <h3 style="margin-top:0;"><i class="fa-solid fa-id-card"></i> Informations personnelles</h3>
    <form method="post" action="index.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save_profile">
      <?= $csrf ?>

      <div class="photo-upload-row" style="display:flex;align-items:center;gap:16px;margin-bottom:16px;">
        <div class="profile-avatar" style="width:72px;height:72px;">
          <img id="myProfilePhotoPreview" src="<?= !empty($user['photo_de_profil']) ? h($user['photo_de_profil']) : '' ?>"
               style="<?= empty($user['photo_de_profil']) ? 'display:none;' : '' ?>width:100%;height:100%;object-fit:cover;border-radius:50%;" alt="Photo de profil">
          <span id="myProfilePhotoInitial" style="<?= empty($user['photo_de_profil']) ? '' : 'display:none;' ?>"><?= h(strtoupper(first_char(trim($user['prenom'] ?: '?')))) ?></span>
        </div>
        <div>
          <label for="myProfilePhotoInput" class="btn btn-outline btn-sm"><i class="fa-solid fa-camera"></i> Changer la photo</label>
          <input type="file" id="myProfilePhotoInput" name="photo" accept="image/*" style="display:none;">
          <div class="sub">JPG, PNG, GIF ou WebP.</div>
        </div>
      </div>

      <div class="form-grid register-grid">
        <div class="floating-field">
          <input type="text" id="mpPrenom" name="prenom" placeholder=" " value="<?= h($user['prenom'] ?? '') ?>" required>
          <label for="mpPrenom">Prénom</label>
        </div>
        <div class="floating-field">
          <input type="text" id="mpNom" name="nom" placeholder=" " value="<?= h($user['nom'] ?? '') ?>" required>
          <label for="mpNom">Nom</label>
        </div>
      </div>
      <div class="floating-field">
        <input type="date" id="mpDateNaissance" name="date_naissance" placeholder=" " value="<?= h($user['date_naissance'] ?? '') ?>">
        <label for="mpDateNaissance">Date de naissance</label>
      </div>
      <div class="floating-field">
        <input type="text" id="mpQuartier" name="quartier" placeholder=" " value="<?= h($user['quartier'] ?? '') ?>">
        <label for="mpQuartier">Quartier (résidence)</label>
      </div>
      <div class="floating-field">
        <input type="tel" id="mpTelephone" name="telephone" placeholder=" " value="<?= h($user['telephone'] ?? '') ?>">
        <label for="mpTelephone">Téléphone</label>
      </div>

      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </form>
  </div>

  <div>
    <!-- ===================== Sécurité — mot de passe ===================== -->
    <div class="form-card">
      <h3 style="margin-top:0;"><i class="fa-solid fa-lock"></i> Sécurité — Mot de passe</h3>
      <form method="post" action="index.php">
        <input type="hidden" name="action" value="change_password">
        <?= $csrf ?>
        <div class="floating-field">
          <input type="password" id="mpCurrentPassword" name="current_password" placeholder=" " required autocomplete="current-password">
          <label for="mpCurrentPassword">Mot de passe actuel</label>
        </div>
        <div class="floating-field">
          <input type="password" id="mpNewPassword" name="new_password" placeholder=" " required minlength="8" autocomplete="new-password">
          <label for="mpNewPassword">Nouveau mot de passe (8 caractères min.)</label>
        </div>
        <div class="floating-field">
          <input type="password" id="mpNewPasswordConfirm" name="new_password_confirm" placeholder=" " required minlength="8" autocomplete="new-password">
          <label for="mpNewPasswordConfirm">Confirmer le nouveau mot de passe</label>
        </div>
        <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
      </form>
    </div>

    <!-- ===================== Sécurité — adresse email ===================== -->
    <div class="form-card" style="margin-top:24px;">
      <h3 style="margin-top:0;"><i class="fa-solid fa-envelope"></i> Adresse email</h3>
      <p class="sub">Adresse actuelle : <b><?= h($user['email'] ?? '') ?></b></p>

      <div class="modal-error show" style="background:#FFFBEB;color:#92400E;border-color:#FDE68A;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Attention : si vous modifiez votre adresse email, vous serez automatiquement déconnecté.
        Vous devrez vérifier votre nouvelle adresse email avant de pouvoir vous reconnecter.
      </div>

      <form method="post" action="index.php" onsubmit="return confirm('Modifier votre adresse email va vous déconnecter immédiatement. Vous devrez vérifier la nouvelle adresse avant de pouvoir vous reconnecter. Continuer ?');">
        <input type="hidden" name="action" value="request_email_change">
        <?= $csrf ?>
        <div class="floating-field">
          <input type="email" id="mpNewEmail" name="new_email" placeholder=" " required autocomplete="email">
          <label for="mpNewEmail">Nouvelle adresse email</label>
        </div>
        <button type="submit" class="btn btn-outline btn-danger">Demander le changement d'adresse email</button>
      </form>
    </div>
  </div>
</div>

<script>
  (function () {
    var input = document.getElementById('myProfilePhotoInput');
    var img = document.getElementById('myProfilePhotoPreview');
    var initial = document.getElementById('myProfilePhotoInitial');
    if (!input) return;
    input.addEventListener('change', function () {
      if (!input.files || !input.files[0]) return;
      var url = URL.createObjectURL(input.files[0]);
      img.src = url;
      img.style.display = 'block';
      if (initial) initial.style.display = 'none';
    });
  })();
</script>
