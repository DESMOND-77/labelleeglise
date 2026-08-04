<?php /* Résultats de recherche. Variables : $q, $results. */ ?>
<?= section_toolbar('Recherche', $q !== '' ? count($results) . ' résultat(s) pour « ' . h($q) . ' »' : 'Saisissez un nom dans la barre de recherche.') ?>
<?php if ($q === ''): ?>
  <?= empty_state('fa-magnifying-glass', 'Recherchez un membre (Liste générale ou Bergers).') ?>
<?php elseif (!$results): ?>
  <?= empty_state('fa-face-frown', 'Aucun résultat pour « ' . h($q) . ' ».') ?>
<?php else: ?>
  <div class="search-results">
    <?php foreach ($results as $r): ?>
      <?php $m = $r['user']; ?>
      <a class="search-result-item" href="<?= h(url('index.php', ['page' => 'personProfile', 'membre' => $m['id']])) ?>">
        <div class="avatar"><?= h(strtoupper(first_char($m['prenom'] ?: $m['nom'] ?: '?'))) ?></div>
        <div class="meta">
          <b><?= h(full_name($m)) ?></b>
          <span><?= h(ROLE_LABELS[$m['role']] ?? $m['role']) ?> · <?= h($m['quartier'] ?? '') ?></span>
        </div>
        <span class="arrow"><i class="fa-solid fa-chevron-right"></i></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
