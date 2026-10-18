<?php /* Shell du back-office « Site public ».
   Variables : $tab, $tabLabels, $tabIcons, $errors, $saved, $content.
   Navigation latérale (ordinateur) / barre horizontale (mobile). */ ?>
<?php if ($saved): ?>
  <div class="alert alert-success" role="status" id="save-status">Modifications enregistrées.</div>
<?php endif; ?>
<?php foreach ($errors as $e): ?>
  <div class="alert alert-danger" role="alert"><?= h($e) ?></div>
<?php endforeach; ?>

<div class="admin-site">
  <nav class="admin-site-nav" aria-label="Rubriques de gestion du site public">
    <ul>
      <?php foreach ($tabLabels as $key => $label): ?>
        <?php $active = $key === $tab || ($tab === 'centre' && $key === 'centres'); ?>
        <li>
          <a class="admin-nav-item<?= $active ? ' active' : '' ?>"
             href="<?= h(url('index.php', ['page' => 'site_public', 'tab' => $key])) ?>"
             <?= $active ? 'aria-current="page"' : '' ?>>
            <i class="<?= h($tabIcons[$key]) ?>" aria-hidden="true"></i><span><?= h($label) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>
  <div class="admin-site-content">
    <?= $content ?>
  </div>
</div>
