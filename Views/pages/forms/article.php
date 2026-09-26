<?php /* Formulaire article de centre. Variables : $article, $isNew, $centreOpts, $cancelUrl, $csrf. */
$cDetails = [];
foreach ($centres as $centre){
  $cid = (int)$centre['id'];
  $cDetails[$cid] = [
    'nb_bacentas' => $centre['nb_bacentas']?? 0,
    'role_counts' => []
  ];
  $members = get_members_of_centre($centre['id']);
  foreach ($members as $member) {
      $role = strtolower($member['role']?? '');
      $cDetails[$cid]['role_counts'][$role] = ($cDetails[$cid]['role_counts'][$role]?? 0) + 1;
  }
}

$article = $article?? null;
$s = [
    'annees' => $article['situation_annees']?? 0,
    'pasteurs' => $article['situation_pasteurs']?? 0,
    'bergers' => $article['situation_bergers']?? 0,
    'leaders' => $article['situation_leaders']?? 0,
    'bacentas' => $article['situation_bacentas']?? 0,
];
$objText = '';
if ($article && $article['objectifs']) {
    $obj = json_decode((string) $article['objectifs'], true);
    $objText = is_array($obj)? implode("\n", $obj) : '';
}
?>
<?= section_toolbar(h($isNew? 'Ajouter un article' : 'Modifier l\'article'))?>
<div class="form-page">
  <form method="post" action="index.php" class="form-card" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save_article">
    <?= $csrf?>
    <?php if ($article):?><input type="hidden" name="id" value="<?= h($article['id'])?>"><?php endif;?>

    <div class="form-group">
        <label>Centre concerné</label>
        <select name="centre_id" id="centre_id" required><?= $centreOpts?></select>
    </div>

    <div class="form-group"><label>Photo du responsable</label>
      <input type="file" name="photo" accept="image/*">
      <?php if (!empty($article['photo'])):?><img src="<?= h($article['photo'])?>" class="photo-input-preview" alt="Aperçu"><?php endif;?>
    </div>
    <div class="form-group"><label>Introduction</label><textarea name="intro" rows="3"><?= h($article['intro']?? '')?></textarea></div>
    <div class="form-group"><label>Vision</label><textarea name="vision" rows="2"><?= h($article['vision']?? '')?></textarea></div>
    <div class="form-group"><label>Direction & Encadrement</label><textarea name="direction" rows="2"><?= h($article['direction']?? '')?></textarea></div>
    <div class="form-group"><label>Origine</label><textarea name="origine" rows="2"><?= h($article['origine']?? '')?></textarea></div>
    <div class="form-group"><label>Objectifs (un par ligne)</label><textarea name="objectifs" rows="4"><?= h($objText)?></textarea></div>

    <div class="form-grid">
      <div class="form-group"><label>Années d'existence</label><input type="number" min="0" name="annees" id="field_annees" value="<?= h($s['annees'])?>"></div>
      <div class="form-group"><label>Pasteurs</label><input type="number" min="0" name="pasteurs" id="field_pasteurs" value="<?= h($s['pasteurs'])?>"></div>
      <div class="form-group"><label>Bergers</label><input type="number" min="0" name="bergers" id="field_bergers" value="<?= h($s['bergers'])?>"></div>
      <div class="form-group"><label>Leaders</label><input type="number" min="0" name="leaders" id="field_leaders" value="<?= h($s['leaders'])?>"></div>
      <div class="form-group"><label>Bacentas</label><input type="number" min="0" name="bacentas" id="field_bacentas" value="<?= h($s['bacentas'])?>"></div>
    </div>

    <div class="modal-actions">
      <a class="btn btn-outline" href="<?= h($cancelUrl)?>">Annuler</a>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
  </form>
</div>

<script>
// On injecte ton array PHP en JS
const cDetails = <?= json_encode($cDetails, JSON_UNESCAPED_UNICODE)?>;

const centreSelect = document.getElementById('centre_id');

function remplirChamps(centreId) {
    if (!centreId ||!cDetails[centreId]) return;

    const details = cDetails[centreId];
    const roles = details.role_counts || {};

    // Remplissage auto - la value du select = la key du array
    document.getElementById('field_pasteurs').value = roles['pasteur']?? roles['pasteurs']?? 0;
    document.getElementById('field_bergers').value = roles['berger']?? roles['bergers']?? 0;
    document.getElementById('field_leaders').value = roles['leader']?? roles['leaders']?? 0;
    document.getElementById('field_bacentas').value = details.nb_bacentas?? 0;
    // annees tu peux laisser manuel ou mettre une logique
}

centreSelect.addEventListener('change', function() {
    remplirChamps(this.value);
});

// Si on est en édition, on ne veut PAS écraser les valeurs déjà enregistrées
// On ne remplit auto que si c'est un nouvel article
// <?php if ($isNew):?>
remplirChamps(centreSelect.value);
// <?php endif;?>
</script>