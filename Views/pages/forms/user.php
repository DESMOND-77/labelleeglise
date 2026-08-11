<?php /* Formulaire utilisateur applicatif. Variables : $title, $user, $bacentaOptions, $roles, $cancelUrl, $csrf, $responsibilitiesPanel. */
$user = $user ?? null;
$responsibilitiesPanel = $responsibilitiesPanel ?? '';
?>
<?= section_toolbar(h($title)) ?>
<div class="form-page">
  <form method="post" action="index.php" class="form-card">
    <input type="hidden" name="action" value="save_user">
    <?= $csrf ?>
    <?php if ($user): ?><input type="hidden" name="id" value="<?= h($user['id']) ?>"><?php endif; ?>
    <div class="form-grid">
      <div class="form-group"><label>Prénom</label><input type="text" name="prenom" value="<?= h($user['prenom'] ?? '') ?>" required></div>
      <div class="form-group"><label>Nom</label><input type="text" name="nom" value="<?= h($user['nom'] ?? '') ?>" required></div>
      <div class="form-group"><label>Email (identifiant de connexion)</label><input type="email" name="email" value="<?= h($user['email'] ?? '') ?>" required></div>
      <div class="form-group"><label>Mot de passe</label>
        <input type="text" name="password" value="" placeholder="<?= $user ? 'Laisser vide pour conserver l\'actuel' : 'Mot de passe' ?>" <?= $user ? '' : 'required' ?>>
      </div>
      <div class="form-group">
        <!-- Rôle ≠ Responsabilité (voir panneau "Responsabilités" ci-dessous) : ce champ ne détermine QUE les capacités générales. -->
        <label>Rôle</label>
        <select name="role">
          <?php foreach ($roles as $key => $label): ?>
            <option value="<?= h($key) ?>" <?= ($user['role'] ?? 'membre') === $key ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Bacenta d'appartenance</label>
        <select name="bacenta_id"><?= $bacentaOptions ?></select>
      </div>
      <div class="form-group" style="justify-content:flex-end;">
        <label class="check-label">
          <input type="checkbox" name="compte_actif" value="1" <?= ($user && (int) $user['compte_actif'] === 1) || !$user ? 'checked' : '' ?>>
          Compte actif
        </label>
      </div>
    </div>
    <div class="modal-error" style="<?= isset($_GET['error']) ? 'display:block;' : 'display:none;' ?>">Cet email est déjà utilisé.</div>
    <div class="modal-actions">
      <a class="btn btn-outline" href="<?= h($cancelUrl) ?>">Annuler</a>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
  </form>
  <?= $responsibilitiesPanel ?>
</div>
