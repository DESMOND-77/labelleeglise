<?php /* Formulaire équipe. Variables : $member, $isNew, $cancelUrl, $csrf, $categories. */
$member = $member ?? null;
$selectedCat = $member ? ($member['categorie'] ?? 'Autre') : 'Pasteur';
?>
<?= section_toolbar(h($isNew ? 'Ajouter un membre de l\'équipe' : 'Modifier le membre de l\'équipe')) ?>
<div class="form-page">
  <form method="post" action="index.php" class="form-card" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save_equipe">
    <?= $csrf ?>
    <?php if ($member): ?><input type="hidden" name="id" value="<?= h($member['id']) ?>"><?php endif; ?>
    <div class="form-group"><label>Nom complet</label><input type="text" name="nom" value="<?= h($member['nom_affichage'] ?? '') ?>" required></div>
    <div class="form-group"><label>Rôle affiché</label><input type="text" name="role" value="<?= h($member['role_affichage'] ?? '') ?>" placeholder="ex : Révérend Principal" required></div>
    <div class="form-group">
      <label>Catégorie</label>
      <select name="categorie">
        <?php foreach ($categories as $cat): ?>
          <option value="<?= h($cat) ?>" <?= $selectedCat === $cat ? 'selected' : '' ?>><?= $cat === 'Autre' ? 'Autre' : h($cat) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Photo (depuis votre ordinateur)</label>
      <input type="file" name="photo" accept="image/*">
      <?php if (!empty($member['photo'])): ?><img src="<?= h($member['photo']) ?>" class="photo-input-preview" alt="Aperçu"><?php endif; ?>
    </div>
    <div class="form-group"><label>Emoji / avatar (si pas de photo)</label><input type="text" name="emoji" value="<?= h($member['emoji'] ?? '👤') ?>" maxlength="4"></div>
    <div class="form-group"><label>Présentation</label><textarea name="bio" rows="3"><?= h($member['bio'] ?? '') ?></textarea></div>
    <div class="modal-actions">
      <a class="btn btn-outline" href="<?= h($cancelUrl) ?>">Annuler</a>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
  </form>
</div>
