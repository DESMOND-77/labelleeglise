<?php /* Formulaire d'ajout d'un nouveau membre (pré-intégration).
   Variables : $bacentas, $userOptions, $akwabaOptions, $csrf. Aucun email/mot
   de passe demandé : voir NouveauMembreService::register() pour la
   génération du compte placeholder. */
?>
<?= section_toolbar('Ajouter un nouveau membre') ?>
<div class="form-page">
  <form method="post" action="index.php" class="form-card">
    <input type="hidden" name="action" value="save_nouveau_membre">
    <?= $csrf ?>

    <div class="form-grid">
      <div class="form-group"><label>Prénom</label><input type="text" name="prenom" required autofocus></div>
      <div class="form-group"><label>Nom</label><input type="text" name="nom" required></div>
      <div class="form-group">
        <label>Sexe</label>
        <select name="sexe">
          <option value="">- Non précisé -</option>
          <option value="F">Féminin</option>
          <option value="M">Masculin</option>
        </select>
      </div>
      <div class="form-group"><label>Date de naissance</label><input type="date" name="date_naissance"></div>
      <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone"></div>
      <div class="form-group"><label>Quartier (résidence)</label><input type="text" name="quartier"></div>
      <div class="form-group"><label>Date de première visite</label><input type="date" name="date_premiere_visite" value="<?= h(date('Y-m-d')) ?>"></div>
      <div class="form-group">
        <label>Bacenta de rattachement</label>
        <select name="bacenta_id">
          <option value="">- Aucun -</option>
          <?php foreach ($bacentas as $b): ?>
            <option value="<?= (int) $b['id'] ?>"><?= h($b['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <h3 class="form-section-title"><i class="fa-solid fa-dove"></i> Accueil & intégration</h3>
    <div class="form-grid">
      <div class="form-group">
        <label>Nom de la personne invitée</label>
        <select name="invite_par"><option value="">- Aucun -</option><?= $userOptions ?></select>
      </div>
      <div class="form-group">
        <label>Nom de l'Akwaba reçue</label>
        <select name="recu_par"><option value="">- Aucun -</option><?= $akwabaOptions ?></select>
      </div>
    </div>

    <?php if (isset($_GET['error'])): ?><div class="modal-error" style="display:block;">Le nom et le prénom sont obligatoires.</div><?php endif; ?>
    <div class="modal-actions">
      <a class="btn btn-outline" href="<?= h(url('index.php', ['page' => 'nouveaux_membres'])) ?>">Annuler</a>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
  </form>
</div>
