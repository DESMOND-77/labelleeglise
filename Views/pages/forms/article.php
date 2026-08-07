<?php /* Formulaire article de centre. Variables : $article, $isNew, $centreOpts, $cancelUrl, $csrf. */
$article = $article ?? null;
$s = [
    'annees' => $article['situation_annees'] ?? 0,
    'pasteurs' => $article['situation_pasteurs'] ?? 0,
    'bergers' => $article['situation_bergers'] ?? 0,
    'leaders' => $article['situation_leaders'] ?? 0,
    'bacentas' => $article['situation_bacentas'] ?? 0,
];
$objText = '';
if ($article && $article['objectifs']) {
    $obj = json_decode((string) $article['objectifs'], true);
    $objText = is_array($obj) ? implode("\n", $obj) : '';
}
?>
<?= section_toolbar(h($isNew ? 'Ajouter un article' : 'Modifier l\'article')) ?>
<div class="form-page">
  <form method="post" action="index.php" class="form-card" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save_article">
    <?= $csrf ?>
    <?php if ($article): ?><input type="hidden" name="id" value="<?= h($article['id']) ?>"><?php endif; ?>
    <div class="form-group"><label>Centre concerné</label><select name="centre_id" required><?= $centreOpts ?></select></div>
    <div class="form-group"><label>Photo du responsable</label>
      <input type="file" name="photo" accept="image/*">
      <?php if (!empty($article['photo'])): ?><img src="<?= h($article['photo']) ?>" class="photo-input-preview" alt="Aperçu"><?php endif; ?>
    </div>
    <div class="form-group"><label>Introduction</label><textarea name="intro" rows="3"><?= h($article['intro'] ?? '') ?></textarea></div>
    <div class="form-group"><label>Vision</label><textarea name="vision" rows="2"><?= h($article['vision'] ?? '') ?></textarea></div>
    <div class="form-group"><label>Direction & Encadrement</label><textarea name="direction" rows="2"><?= h($article['direction'] ?? '') ?></textarea></div>
    <div class="form-group"><label>Origine</label><textarea name="origine" rows="2"><?= h($article['origine'] ?? '') ?></textarea></div>
    <div class="form-group"><label>Objectifs (un par ligne)</label><textarea name="objectifs" rows="4"><?= h($objText) ?></textarea></div>
    <div class="form-grid">
      <div class="form-group"><label>Années d'existence</label><input type="number" min="0" name="annees" value="<?= h($s['annees']) ?>"></div>
      <div class="form-group"><label>Pasteurs</label><input type="number" min="0" name="pasteurs" value="<?= h($s['pasteurs']) ?>"></div>
      <div class="form-group"><label>Bergers</label><input type="number" min="0" name="bergers" value="<?= h($s['bergers']) ?>"></div>
      <div class="form-group"><label>Leaders</label><input type="number" min="0" name="leaders" value="<?= h($s['leaders']) ?>"></div>
      <div class="form-group"><label>Bacentas</label><input type="number" min="0" name="bacentas" value="<?= h($s['bacentas']) ?>"></div>
    </div>
    <div class="modal-actions">
      <a class="btn btn-outline" href="<?= h($cancelUrl) ?>">Annuler</a>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
  </form>
</div>
