<?php /* Barre d'annonce : uniquement le prochain événement publié.
   Aucune donnée => aucune barre (pas de message inventé).
   Variables : $nextEvent (array|null). */ ?>
<?php if ($nextEvent !== null): ?>
  <aside class="lp-announce" aria-label="Annonce">
    <div class="wrap lp-announce-inner">
      <span class="lp-announce-label">Prochain rendez-vous</span>
      <span>
        <a href="#evenements"><?= h($nextEvent['nom']) ?></a>
        — <?= h($nextEvent['quand']) ?><?= $nextEvent['lieu'] !== '' ? ' · ' . h($nextEvent['lieu']) : '' ?>
      </span>
    </div>
  </aside>
<?php endif; ?>
