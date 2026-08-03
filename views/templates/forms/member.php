<?php /* Formulaire membre (table users).
           Variables : $title, $member, $section, $isAdmin, $bacentaOptions, $userOptions,
                       $akwabaOptions, $presenceValues, $extraFields, $roles, $cancelUrl, $csrf, $hidden. */
$member = $member ?? null;
$isNew = !$member;
?>
<?= section_toolbar(h($title)) ?>
<div class="form-page">
  <form method="post" action="index.php" class="form-card" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save_membre">
    <?= $csrf ?>
    <?= $hidden ?>

    <div class="form-grid">
      <div class="form-group"><label>Prénom</label><input type="text" name="prenom" value="<?= h($member['prenom'] ?? '') ?>" required></div>
      <div class="form-group"><label>Nom</label><input type="text" name="nom" value="<?= h($member['nom'] ?? '') ?>" required></div>
      <div class="form-group"><label>Email (identifiant de connexion)</label><input type="email" name="email" value="<?= h($member['email'] ?? '') ?>" required></div>
      <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone" value="<?= h($member['telephone'] ?? '') ?>"></div>
      <div class="form-group"><label>Quartier (résidence)</label><input type="text" name="quartier" value="<?= h($member['quartier'] ?? '') ?>"></div>
      <div class="form-group"><label>Date de naissance</label><input type="date" name="date_naissance" value="<?= h($member['date_naissance'] ?? '') ?>"></div>
      <div class="form-group">
        <label>Rôle</label>
        <select name="role">
          <?php foreach ($roles as $key => $label): ?>
            <option value="<?= h($key) ?>" <?= ($member['role'] ?? 'membre') === $key ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Bacenta d'appartenance</label>
        <select name="bacenta_id">
          <option value="">— Aucun —</option>
          <?= $bacentaOptions ?>
        </select>
      </div>
      <div class="form-group"><label>Mot de passe (<?= $isNew ? 'obligatoire' : 'laisser vide pour conserver' ?>)</label><input type="text" name="password" value=""></div>
      <div class="form-group">
        <label>Photo de profil</label>
        <input type="file" name="photo" accept="image/*">
        <?php if (!empty($member['photo_de_profil'])): ?>
          <img src="<?= h($member['photo_de_profil']) ?>" class="photo-input-preview" alt="Aperçu">
        <?php endif; ?>
      </div>
      <div class="form-group" style="justify-content:flex-end;">
        <label class="check-label">
          <input type="checkbox" name="compte_actif" value="1" <?= (!$isNew && (int) $member['compte_actif'] === 1) || $isNew ? 'checked' : '' ?>>
          Compte actif (peut se connecter)
        </label>
      </div>
    </div>

    <?php if (in_array('invite_par', $extraFields, true) || in_array('recu_par', $extraFields, true) || in_array('date_recu', $extraFields, true)): ?>
      <h3 class="form-section-title">🕊️ Accueil & intégration</h3>
      <div class="form-grid">
        <div class="form-group">
          <label>Invité par</label>
          <select name="invite_par"><option value="">— Aucun —</option><?= $userOptions ?></select>
        </div>
        <div class="form-group">
          <label>Reçu par (Akwaba)</label>
          <select name="recu_par"><option value="">— Aucun —</option><?= $akwabaOptions ?></select>
        </div>
        <div class="form-group"><label>Date d'arrivée</label><input type="date" name="date_recu" value="<?= h($member['date_recu'] ?? '') ?>"></div>
      </div>
    <?php endif; ?>

    <h3 class="form-section-title">🙌 Présence (dernier événement de chaque type)</h3>
    <div class="form-grid">
      <?php foreach (PRESENCE_FIELDS as $f): ?>
        <div class="form-group">
          <label><?= h(FIELD_LABELS[$f]) ?></label>
          <select name="<?= h($f) ?>">
            <option value="">—</option>
            <option value="Présent" <?= ($presenceValues[$f] ?? '') === 'Présent' ? 'selected' : '' ?>>Présent</option>
          </select>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="form-hint">« Présent » enregistre la présence du membre à l'événement le plus récent de ce type (culte, basonta, centre, bacenta).</p>

    <div class="modal-error" style="<?= isset($_GET['error']) ? 'display:block;' : 'display:none;' ?>">Cet email est déjà utilisé.</div>
    <div class="modal-actions">
      <a class="btn btn-outline" href="<?= h($cancelUrl) ?>">Annuler</a>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
  </form>
</div>
