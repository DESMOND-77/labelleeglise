<?php /* FAQ « Première visite » — liste dynamique (ajout illimité).
   Variables : $faq, $csrf. */ ?>
<div class="page-header">
  <h2>FAQ — première visite</h2>
  <p class="sub">tenue, durée du culte, accueil des enfants, stationnement, accessibilité, langues, déroulement. L'ordre du formulaire est l'ordre d'affichage.</p>
</div>

<form class="form-card" method="post" action="index.php" data-track-dirty>
  <input type="hidden" name="action" value="save_faq_public">
  <?= $csrf ?>

  <?php if (!$faq): ?>
    <p class="sub">Aucune question enregistrée — ajoutez-en ci-dessous.</p>
  <?php endif; ?>

  <div id="faq-list">
    <?php foreach ($faq as $f): ?>
      <fieldset class="form-group form-card-inner faq-item-admin" data-faq-id="<?= (int) $f['id'] ?>">
        <legend>Question <?= (int) $f['id'] ?></legend>
        <label class="check-label">
          <input type="checkbox" name="publie[]" value="<?= (int) $f['id'] ?>"<?= !empty($f['publie']) ? ' checked' : '' ?>>
          Publiée sur le site public
        </label>
        <label>Question<input type="text" name="question[<?= (int) $f['id'] ?>]" maxlength="255" value="<?= h($f['question']) ?>"></label>
        <label>Réponse<textarea name="reponse[<?= (int) $f['id'] ?>]" rows="3" maxlength="5000"><?= h($f['reponse']) ?></textarea></label>
        <a class="icon-btn danger" title="Supprimer" data-confirm="Supprimer cette question ?"
           href="<?= h(url('index.php', ['action' => 'delete_faq', 'id' => (int) $f['id'], 'tab' => 'faq'])) ?>">
          <i class="fa-solid fa-trash"></i>
        </a>
      </fieldset>
    <?php endforeach; ?>
  </div>

  <template id="faq-row-template">
    <fieldset class="form-group form-card-inner faq-item-admin">
      <legend>Nouvelle question <span class="faq-index"></span></legend>
      <label class="check-label"><input type="checkbox" name="publie[]" value=""> Publiée sur le site public</label>
      <label>Question<input type="text" name="question[]" maxlength="255"></label>
      <label>Réponse<textarea name="reponse[]" rows="3" maxlength="5000"></textarea></label>
    </fieldset>
  </template>

  <?php /* Lignes vides statiques : l'ajout fonctionne aussi sans JavaScript. */ ?>
  <?php $next = count($faq) + 1; ?>
  <?php for ($i = 0; $i < 2; $i++): ?>
    <?php $key = 'new' . ($next + $i); ?>
    <fieldset class="form-group form-card-inner faq-item-admin">
      <legend>Nouvelle question <?= (int) ($i + 1) ?></legend>
      <label class="check-label"><input type="checkbox" name="publie[]" value="<?= h($key) ?>"> Publiée sur le site public</label>
      <label>Question<input type="text" name="question[<?= h($key) ?>]" maxlength="255"></label>
      <label>Réponse<textarea name="reponse[<?= h($key) ?>]" rows="3" maxlength="5000"></textarea></label>
    </fieldset>
  <?php endfor; ?>

  <div class="form-actions">
    <button type="button" class="btn" id="faq-add"><i class="fa-solid fa-plus" aria-hidden="true"></i> Ajouter une question</button>
    <button class="btn btn-primary" type="submit">Enregistrer la FAQ</button>
    <?php if ($faq): ?>
      <a class="btn" href="<?= h(url('index.php', ['page' => 'eglise'])) ?>#visite" target="_blank" rel="noopener noreferrer">Prévisualiser la FAQ</a>
    <?php endif; ?>
  </div>
</form>
