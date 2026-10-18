<?php /* Fiche de modification d'un centre. Variables : $centre, $centreId, $csrf.
   Horaires en saisie guidée (libellé, jours, début, fin) - même validation
   et même stockage que le format texte hérité (contrac préservé). */
$days = WEEK_DAYS;
$existing = $centre['horaires_rows'] ?? [];
$rows = $existing !== [] ? array_merge($existing, [null]) : [null, null];
/** Jours cochés d'une ligne (CSV en base). */
$checkedDays = static function (array|string $jours): array {
    if (is_array($jours)) {
        return array_map('strval', $jours);
    }
    return array_filter(array_map('trim', explode(',', (string) $jours)), static fn($d) => $d !== '');
};
?>
<?= back_button('Retour aux centres', url('index.php', ['page' => 'site_public', 'tab' => 'centres'])) ?>

<div class="page-header">
  <h2><?= h($centre['nom']) ?></h2>
  <p class="sub">
    <?php if (!empty($centre['publie'])): ?>
      <span class="badge-status status-ok">Publié sur le site public</span>
    <?php else: ?>
      <span class="badge-status status-draft">Brouillon - non affiché publiquement</span>
    <?php endif; ?>
  </p>
</div>

<form class="form-card" method="post" action="index.php" data-track-dirty>
  <input type="hidden" name="action" value="save_centre_public">
  <input type="hidden" name="centre_id" value="<?= (int) $centreId ?>">
  <?= $csrf ?>

  <fieldset class="form-group">
    <legend>Informations générales</legend>
    <div class="field-grid">
      <label>Adresse<input type="text" name="adresse" maxlength="255" value="<?= h($centre['adresse'] ?? '') ?>"></label>
      <label>Quartier<input type="text" name="quartier" maxlength="100" value="<?= h($centre['quartier'] ?? '') ?>"></label>
      <label>Ville<input type="text" name="ville" maxlength="100" value="<?= h($centre['ville'] ?? '') ?>"></label>
    </div>
  </fieldset>

  <fieldset class="form-group">
    <legend>Contacts du centre (facultatifs - le contact général sert de repli)</legend>
    <div class="field-grid">
      <label>Téléphone<input type="tel" name="telephone" maxlength="30" value="<?= h($centre['telephone'] ?? '') ?>"></label>
      <label>WhatsApp (indicatif international)<input type="tel" name="whatsapp" maxlength="30" value="<?= h($centre['whatsapp'] ?? '') ?>"></label>
      <label>E-mail<input type="email" name="email" maxlength="150" value="<?= h($centre['email'] ?? '') ?>"></label>
    </div>
  </fieldset>

  <fieldset class="form-group">
    <legend>Informations publiques</legend>
    <label>Responsable affiché (texte public - seulement si destiné au public)
      <input type="text" name="responsable_public" maxlength="150" value="<?= h($centre['responsable_public'] ?? '') ?>">
    </label>
    <label>Lien d'itinéraire (Google Maps ou autre carte, https)
      <input type="url" name="itineraire_url" value="<?= h($centre['itineraire_url'] ?? '') ?>">
    </label>
  </fieldset>

  <fieldset class="form-group">
    <legend>Horaires de culte</legend>
    <p class="sub">Un horaire = un libellé, un ou plusieurs jours, et une heure de début. L'heure de fin est facultative.</p>
    <?php foreach ($rows as $i => $row): ?>
      <?php $slot = is_array($row) ? $row : []; ?>
      <div class="horaire-row">
        <input type="text" name="horaire_libelle[<?= (int) $i ?>]" maxlength="100"
               value="<?= h($slot['libelle'] ?? '') ?>" placeholder="Libellé (ex. Culte du dimanche)"
               aria-label="Horaire <?= (int) $i + 1 ?> - libellé">
        <div class="horaire-jours" role="group" aria-label="Horaire <?= (int) $i + 1 ?> - jours">
          <?php $sel = $checkedDays($slot['jours'] ?? ''); ?>
          <?php foreach ($days as $d): ?>
            <label class="check-label check-inline"><input type="checkbox" name="horaire_jours[<?= (int) $i ?>][]" value="<?= h($d) ?>"<?= in_array($d, $sel, true) ? ' checked' : '' ?>> <?= h($d) ?></label>
          <?php endforeach; ?>
        </div>
        <input type="time" name="horaire_debut[<?= (int) $i ?>]" value="<?= h(substr((string) ($slot['heure_debut'] ?? ''), 0, 5)) ?>" aria-label="Horaire <?= (int) $i + 1 ?> - heure de début">
        <input type="time" name="horaire_fin[<?= (int) $i ?>]" value="<?= h(substr((string) ($slot['heure_fin'] ?? ''), 0, 5)) ?>" aria-label="Horaire <?= (int) $i + 1 ?> - heure de fin (facultatif)">
      </div>
    <?php endforeach; ?>
    <p class="sub">Exemple de jours : « Dimanche » ou « Lundi,Mercredi ».</p>
  </fieldset>

  <fieldset class="form-group">
    <legend>Publication</legend>
    <label class="check-label"><input type="checkbox" name="publie" value="1"<?= !empty($centre['publie']) ? ' checked' : '' ?>> Publier ce centre sur le site public</label>
    <?php if (!$centre['completion']['complete']): ?>
      <p class="alert alert-warning" role="alert">
        Centre non publiable tant que : <?= h(implode(', ', $centre['completion']['missing'])) ?> - complétez ces informations.
      </p>
    <?php endif; ?>
  </fieldset>

  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Enregistrer les modifications</button>
    <?php if (!empty($centre['publie'])): ?>
      <a class="btn" href="<?= h(url('index.php', ['page' => 'eglise'])) ?>#centres" target="_blank" rel="noopener noreferrer">Prévisualiser sur le site public</a>
    <?php endif; ?>
  </div>
</form>
