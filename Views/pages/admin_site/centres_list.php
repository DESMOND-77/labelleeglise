<?php /* Liste centralisée des centres. Variables : $centres (avec completion), $csrf n'est pas utilisé ici.
   Recherche et filtre réels (JS progressif, données déjà chargées). */ ?>
<div class="page-header">
  <h2>Centres</h2>
  <p class="sub">
    <?= count($centres) ?> centre<?= count($centres) > 1 ? 's' : '' ?> configuré<?= count($centres) > 1 ? 's' : '' ?> —
    <?= count(array_filter($centres, static fn($c) => !empty($c['publie']))) ?> publié<?= count(array_filter($centres, static fn($c) => !empty($c['publie']))) > 1 ? 's' : '' ?>.
    <a class="btn btn-sm" href="<?= h(url('index.php', ['page' => 'centres'])) ?>">Créer un centre</a>
  </p>
</div>

<?php if (!$centres): ?>
  <?= empty_state('fa-school', 'Aucun centre. Créez d\'abord un centre dans la section Centres de l\'application.') ?>
<?php else: ?>
  <div class="list-toolbar">
    <input type="search" id="centres-search" placeholder="Rechercher un centre (nom, quartier, ville, adresse)…" aria-label="Rechercher un centre">
    <select id="centres-filter" aria-label="Filtrer les centres par état">
      <option value="all">Tous les centres</option>
      <option value="publie">Publiés</option>
      <option value="brouillon">Non publiés</option>
      <option value="incomplet">Incomplets (non publiables)</option>
    </select>
    <p class="list-count" id="centres-count" aria-live="polite"></p>
  </div>

  <table class="admin-table" data-centres-table>
    <thead>
      <tr>
        <th scope="col">Centre</th>
        <th scope="col">Localisation</th>
        <th scope="col">Publication</th>
        <th scope="col">Complétude</th>
        <th scope="col">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($centres as $c): ?>
        <?php $complete = $c['completion']['complete']; ?>
        <tr data-name="<?= h(mb_lower($c['nom'] . ' ' . ($c['quartier'] ?? '') . ' ' . ($c['ville'] ?? '') . ' ' . ($c['adresse'] ?? ''))) ?>"
            data-publie="<?= !empty($c['publie']) ? '1' : '0' ?>" data-complete="<?= $complete ? '1' : '0' ?>">
          <td><a href="<?= h(url('index.php', ['page' => 'site_public', 'tab' => 'centre', 'id' => (int) $c['id']])) ?>"><?= h($c['nom']) ?></a></td>
          <td><?= h(implode(', ', array_filter([$c['quartier'] ?? '', $c['ville'] ?? '', $c['adresse'] ?? '']))) ?: '<span class="muted">—</span>' ?></td>
          <td>
            <?php if (!empty($c['publie'])): ?>
              <span class="badge-status status-ok">Publié</span>
            <?php else: ?>
              <span class="badge-status status-draft">Brouillon</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($complete): ?>
              <span class="badge-status status-ok">Complet</span>
            <?php else: ?>
              <span class="badge-status status-warn" title="Manque : <?= h(implode(', ', $c['completion']['missing'])) ?>">Incomplet</span>
            <?php endif; ?>
          </td>
          <td class="actions-cell">
            <a class="btn btn-sm" href="<?= h(url('index.php', ['page' => 'site_public', 'tab' => 'centre', 'id' => (int) $c['id']])) ?>">Modifier</a>
            <?php if (!empty($c['publie'])): ?>
              <a class="btn btn-sm" href="<?= h(url('index.php', ['page' => 'eglise'])) ?>#centres" target="_blank" rel="noopener noreferrer">Prévisualiser</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="card-list-mobile" data-centres-cards>
    <?php foreach ($centres as $c): ?>
      <article class="mini-card" data-name="<?= h(mb_lower($c['nom'] . ' ' . ($c['quartier'] ?? '') . ' ' . ($c['ville'] ?? '') . ' ' . ($c['adresse'] ?? ''))) ?>"
               data-publie="<?= !empty($c['publie']) ? '1' : '0' ?>" data-complete="<?= $c['completion']['complete'] ? '1' : '0' ?>">
        <div class="mini-card-head">
          <strong><a href="<?= h(url('index.php', ['page' => 'site_public', 'tab' => 'centre', 'id' => (int) $c['id']])) ?>"><?= h($c['nom']) ?></a></strong>
          <?php if (!empty($c['publie'])): ?>
            <span class="badge-status status-ok">Publié</span>
          <?php else: ?>
            <span class="badge-status status-draft">Brouillon</span>
          <?php endif; ?>
        </div>
        <p class="muted"><?= h(implode(', ', array_filter([$c['quartier'] ?? '', $c['ville'] ?? '']))) ?: 'Localisation à renseigner' ?></p>
        <?php if (!$c['completion']['complete']): ?>
          <p class="mini-card-missing">Manque : <?= h(implode(', ', $c['completion']['missing'])) ?></p>
        <?php endif; ?>
        <div class="mini-card-actions">
          <a class="btn btn-sm" href="<?= h(url('index.php', ['page' => 'site_public', 'tab' => 'centre', 'id' => (int) $c['id']])) ?>">Modifier</a>
          <?php if (!empty($c['publie'])): ?>
            <a class="btn btn-sm" href="<?= h(url('index.php', ['page' => 'eglise'])) ?>#centres" target="_blank" rel="noopener noreferrer">Prévisualiser</a>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <p class="sub" id="centres-empty" hidden>Aucun centre ne correspond à la recherche ou au filtre.</p>
<?php endif; ?>
