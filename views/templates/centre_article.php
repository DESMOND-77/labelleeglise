<?php /* Article d'un centre. Variables : $c (centres_presentation + centre_nom), $isAdmin, $backUrl, $editUrl. */
$photoHtml = !empty($c['photo'])
    ? '<img src="' . h($c['photo']) . '" class="centre-resp-photo" alt="' . h($c['centre_nom']) . '">'
    : '<div class="centre-resp-photo placeholder">🏫</div>';
$objectifsHtml = '';
$obj = json_decode((string) ($c['objectifs'] ?? ''), true);
if (is_array($obj)) {
    foreach ($obj as $o) {
        $objectifsHtml .= '<li>' . h($o) . '</li>';
    }
}
?>
<?= back_button('Retour aux centres', $backUrl) ?>
<div class="centre-article">
  <?= section_toolbar(h($c['centre_nom']), 'Article officiel de présentation', $editUrl ? '<a class="icon-btn" title="Modifier" href="' . h($editUrl) . '">✎</a>' : '') ?>
  <p class="centre-article-intro"><?= h($c['intro'] ?? '') ?></p>
  <div class="centre-article-grid">
    <div class="centre-article-main">
      <div class="centre-block"><h3>🎯 Vision</h3><p><?= h($c['vision'] ?? '') ?></p></div>
      <div class="centre-block"><h3>🧑‍🤝‍🧑 Direction & Encadrement</h3><p><?= h($c['direction'] ?? '') ?></p></div>
      <div class="centre-block"><h3>🌱 Origine</h3><p><?= h($c['origine'] ?? '') ?></p></div>
      <div class="centre-block"><h3>🚀 Objectifs</h3><ul><?= $objectifsHtml ?></ul></div>
    </div>
    <div class="centre-article-side">
      <?= $photoHtml ?>
      <div class="centre-resp-name"><?= h($c['centre_nom']) ?></div>
      <div class="centre-resp-role">Centre de l'église</div>
      <div class="centre-stats">
        <div class="centre-stat"><b><?= (int) $c['situation_annees'] ?></b><span>ans d'existence</span></div>
        <div class="centre-stat"><b><?= (int) $c['situation_pasteurs'] ?></b><span>pasteur(s)</span></div>
        <div class="centre-stat"><b><?= (int) $c['situation_bergers'] ?></b><span>bergers</span></div>
        <div class="centre-stat"><b><?= (int) $c['situation_leaders'] ?></b><span>leaders</span></div>
        <div class="centre-stat"><b><?= (int) $c['situation_bacentas'] ?></b><span>bacentas</span></div>
      </div>
    </div>
  </div>
</div>
