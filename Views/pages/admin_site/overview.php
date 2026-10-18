<?php /* Vue d'ensemble — indicateurs calculés sur les données réelles.
   Variables : $overview. */
$ov = $overview;
$missingIdentity = array_keys(array_filter($ov['identity'], static fn($v) => !$v));
$identityLabels = [
    'nom_affichage' => 'Nom d\'affichage', 'slogan' => 'Slogan',
    'accueil_texte' => 'Texte d\'accueil', 'adresse' => 'Adresse',
    'contact' => 'Coordonnées (téléphone, e-mail ou WhatsApp)',
    'hero_image' => 'Image d\'accueil',
];
$todo = [];
foreach ($missingIdentity as $k) {
    $todo[] = ['label' => $identityLabels[$k] . ' manquant', 'href' => url('index.php', ['page' => 'site_public', 'tab' => 'identity'])];
}
if ($ov['centres']['publies'] === 0) {
    $todo[] = ['label' => 'Aucun centre publié sur le site public', 'href' => url('index.php', ['page' => 'site_public', 'tab' => 'centres'])];
}
foreach ($ov['centres']['list'] as $c) {
    if (!$c['publie'] && !$c['complete']) {
        $todo[] = [
            'label' => 'Centre « ' . $c['nom'] . ' » incomplet (' . implode(', ', $c['missing']) . ')',
            'href' => url('index.php', ['page' => 'site_public', 'tab' => 'centre', 'id' => $c['id']]),
        ];
    }
}
if ($ov['equipe']['total'] === 0) {
    $todo[] = ['label' => 'Aucun membre d\'équipe enregistré', 'href' => url('index.php', ['page' => 'apropos'])];
} elseif ($ov['equipe']['publies'] === 0) {
    $todo[] = ['label' => 'Équipe pastorale : personne autorisée à être publiée', 'href' => url('index.php', ['page' => 'site_public', 'tab' => 'team'])];
}
if ($ov['evenements']['total'] === 0) {
    $todo[] = ['label' => 'Aucun événement à venir enregistré', 'href' => url('index.php', ['page' => 'agenda'])];
}
if ($ov['faq']['total'] === 0) {
    $todo[] = ['label' => 'Aucune question de FAQ pour les visiteurs', 'href' => url('index.php', ['page' => 'site_public', 'tab' => 'faq'])];
}
if (!$ov['identity']['contact'] && $ov['centres']['total'] === 0) {
    $todo[] = ['label' => 'Aucune coordonnée — la page publique resterait vide', 'href' => url('index.php', ['page' => 'site_public', 'tab' => 'identity'])];
}
?>
<div class="page-header">
  <h2>Vue d'ensemble</h2>
  <p class="sub">État réel des contenus affichés sur la vitrine publique.
    <a class="btn btn-sm" href="<?= h(url('index.php', ['page' => 'eglise'])) ?>" target="_blank" rel="noopener noreferrer">Prévisualiser le site public</a>
  </p>
</div>

<div class="stat-grid">
  <div class="stat-card-admin">
    <span class="stat-num"><?= (int) $ov['centres']['publies'] ?><span class="stat-denom">/<?= (int) $ov['centres']['total'] ?></span></span>
    <span class="stat-lbl">Centres publiés</span>
  </div>
  <div class="stat-card-admin">
    <span class="stat-num"><?= (int) $ov['equipe']['publies'] ?><span class="stat-denom">/<?= (int) $ov['equipe']['total'] ?></span></span>
    <span class="stat-lbl">Membres d'équipe publiés</span>
  </div>
  <div class="stat-card-admin">
    <span class="stat-num"><?= (int) $ov['evenements']['publies'] ?><span class="stat-denom">/<?= (int) $ov['evenements']['total'] ?></span></span>
    <span class="stat-lbl">Événements à venir publiés</span>
  </div>
  <div class="stat-card-admin">
    <span class="stat-num"><?= (int) $ov['faq']['publies'] ?><span class="stat-denom">/<?= (int) $ov['faq']['total'] ?></span></span>
    <span class="stat-lbl">Questions de FAQ publiées</span>
  </div>
</div>

<div class="admin-cols">
  <section class="card" aria-labelledby="ov-actions">
    <h3 id="ov-actions">Actions à effectuer</h3>
    <?php if ($todo === []): ?>
      <p class="alert alert-success" role="status">Tous les contenus essentiels sont configurés et publiés.</p>
    <?php else: ?>
      <ul class="todo-list">
        <?php foreach ($todo as $t): ?>
          <li><a href="<?= h($t['href']) ?>"><?= h($t['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="card" aria-labelledby="ov-ministries">
    <h3 id="ov-ministries">Ministères</h3>
    <ul class="kv-list">
      <li><span>Bacentas (prière)</span><b><?= (int) $ov['ministries']['bacentas']['publies'] ?>/<?= (int) $ov['ministries']['bacentas']['total'] ?> publiés</b></li>
      <li><span>Basontas (groupes et services)</span><b><?= (int) $ov['ministries']['basontas']['publies'] ?>/<?= (int) $ov['ministries']['basontas']['total'] ?> publiés</b></li>
    </ul>
    <p class="sub"><a href="<?= h(url('index.php', ['page' => 'site_public', 'tab' => 'ministries'])) ?>">Gérer les ministères</a></p>
  </section>
</div>

<p class="sub">Rappel : la politique de confidentialité doit être renseignée avant tout formulaire public ou outil de mesure. <a href="<?= h(url('index.php', ['page' => 'site_public', 'tab' => 'legal'])) ?>">Pages légales</a></p>
