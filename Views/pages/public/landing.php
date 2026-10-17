<?php
/* Landing publique. Variables : $nom, $accroche, $histoire, $centres, $contact, $socials, $direct.
   Chaque section n'apparaît que si ses données publiables existent. */
$hasCentres = $centres !== [];
$hasEquipe  = $equipe !== [];
$hasEvents  = $evenements !== [];
$hasAbout   = $accroche !== '' || $histoire !== '' || $hasEquipe;
$hasContact = $contact !== [] || $socials !== [];

$nav = [['#accueil', 'Accueil']];
if ($hasAbout)   { $nav[] = ['#a-propos', 'À propos']; }
if ($hasCentres) { $nav[] = ['#horaires', 'Horaires']; $nav[] = ['#centres', 'Nos centres']; }
if ($hasEvents)  { $nav[] = ['#evenements', 'Événements']; }
if ($direct !== '') { $nav[] = ['#medias', 'Culte en ligne']; }
if ($hasContact) { $nav[] = ['#contact', 'Contact']; }

$ctaHref = $hasCentres ? '#horaires' : ($hasContact ? '#contact' : '#accueil');
$ctaLabel = 'Planifier ma visite';
$socialIcons = ['facebook' => 'fa-brands fa-facebook', 'youtube' => 'fa-brands fa-youtube', 'instagram' => 'fa-brands fa-instagram'];
$socialLabels = ['facebook' => 'Facebook', 'youtube' => 'YouTube', 'instagram' => 'Instagram'];

/** Adresse lisible d'un centre. */
$addr = static fn(array $c): string => implode(', ', array_filter([$c['adresse'], $c['quartier'], $c['ville']], static fn($v) => $v !== ''));
?>
<header class="site-header">
  <div class="wrap header-row">
    <a class="brand" href="#accueil">
      <img src="assets/images/logo.png" alt="" width="40" height="40">
      <span><?= h($nom) ?></span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Menu">
      <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <nav id="site-nav" class="site-nav" aria-label="Navigation principale">
      <ul>
        <?php foreach ($nav as [$href, $label]): ?>
          <li><a href="<?= h($href) ?>"><?= h($label) ?></a></li>
        <?php endforeach; ?>
        <li><a class="nav-login" href="<?= h(url('index.php', ['page' => 'login'])) ?>">Connexion</a></li>
      </ul>
    </nav>
  </div>
</header>

<main id="contenu">
  <section id="accueil" class="hero">
    <div class="wrap">
      <h1><?= h($nom) ?></h1>
      <?php if ($accroche !== ''): ?>
        <p class="hero-lead"><?= h($accroche) ?></p>
      <?php endif; ?>
      <p class="hero-actions">
        <a class="btn btn-primary btn-lg" href="<?= h($ctaHref) ?>"><?= h($ctaLabel) ?></a>
        <?php if ($hasCentres): ?>
          <a class="btn btn-ghost btn-lg" href="#centres">Découvrir nos centres</a>
        <?php endif; ?>
      </p>
    </div>
  </section>

  <?php if ($hasCentres): ?>
    <section id="horaires" class="section" aria-labelledby="horaires-titre">
      <div class="wrap">
        <h2 id="horaires-titre">Horaires des cultes</h2>
        <div class="grid">
          <?php foreach ($centres as $c): ?>
            <article class="card">
              <h3><?= h($c['nom']) ?></h3>
              <ul class="slots">
                <?php foreach ($c['horaires'] as $s): ?>
                  <li><strong><?= h($s['libelle']) ?></strong> — <?= h($s['jours']) ?>, <?= h($s['debut']) ?><?= $s['fin'] !== '' ? ' – ' . h($s['fin']) : '' ?></li>
                <?php endforeach; ?>
              </ul>
              <p class="muted"><?= h($addr($c)) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($hasAbout): ?>
    <section id="a-propos" class="section section-alt" aria-labelledby="apropos-titre">
      <div class="wrap narrow">
        <h2 id="apropos-titre">À propos</h2>
        <?php if ($histoire !== ''): ?>
          <div class="prose"><?= nl2br(h($histoire)) ?></div>
        <?php elseif ($accroche !== ''): ?>
          <div class="prose"><?= h($accroche) ?></div>
        <?php endif; ?>
        <?php if ($hasEquipe): ?>
          <h3 class="team-title">Notre équipe</h3>
          <?php foreach ($equipe as $g): ?>
            <h4><?= h($g['label']) ?></h4>
            <ul class="team">
              <?php foreach ($g['members'] as $m): ?>
                <li class="team-member">
                  <?php if ($m['photo'] !== ''): ?>
                    <img src="<?= h($m['photo']) ?>" alt="<?= h($m['nom']) ?>" loading="lazy" width="72" height="72">
                  <?php endif; ?>
                  <div>
                    <strong><?= h($m['nom']) ?></strong>
                    <span class="muted"><?= h($m['role']) ?></span>
                    <?php if ($m['bio'] !== ''): ?><p><?= h($m['bio']) ?></p><?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($hasCentres): ?>
    <section id="centres" class="section" aria-labelledby="centres-titre">
      <div class="wrap">
        <h2 id="centres-titre">Nos centres</h2>
        <div class="grid">
          <?php foreach ($centres as $c): ?>
            <article class="card">
              <?php if ($c['photo'] !== ''): ?>
                <img class="card-photo" src="<?= h($c['photo']) ?>" alt="<?= h($c['nom']) ?>" loading="lazy" width="400" height="240">
              <?php endif; ?>
              <h3><?= h($c['nom']) ?></h3>
              <?php if ($c['intro'] !== ''): ?>
                <p><?= h($c['intro']) ?></p>
              <?php endif; ?>
              <p class="muted"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= h($addr($c)) ?></p>
              <p class="card-actions">
                <?php if ($c['itineraire'] !== ''): ?>
                  <a class="btn" href="<?= h($c['itineraire']) ?>" target="_blank" rel="noopener noreferrer">Obtenir l'itinéraire<span class="sr-only"> (<?= h($c['nom']) ?>, nouvel onglet)</span></a>
                <?php endif; ?>
                <?php foreach ($c['contact'] as $kind => $link): ?>
                  <a class="btn" href="<?= h($link['href']) ?>"<?= $kind === 'whatsapp' ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= h($kind === 'email' ? 'E-mail' : ($kind === 'whatsapp' ? 'WhatsApp' : 'Appeler')) ?><span class="sr-only"> (<?= h($c['nom']) ?>)</span></a>
                <?php endforeach; ?>
              </p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($hasEvents): ?>
    <section id="evenements" class="section" aria-labelledby="evenements-titre">
      <div class="wrap">
        <h2 id="evenements-titre">Événements à venir</h2>
        <div class="grid">
          <?php foreach ($evenements as $e): ?>
            <article class="card">
              <h3><?= h($e['nom']) ?></h3>
              <p><time datetime="<?= h($e['iso']) ?>"><?= h($e['quand']) ?></time></p>
              <?php if ($e['lieu'] !== ''): ?><p class="muted"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= h($e['lieu']) ?></p><?php endif; ?>
              <?php if ($e['description'] !== ''): ?><p><?= h($e['description']) ?></p><?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($direct !== ''): ?>
    <section id="medias" class="section section-alt" aria-labelledby="medias-titre">
      <div class="wrap narrow">
        <h2 id="medias-titre">Culte en ligne</h2>
        <p><a class="btn btn-primary" href="<?= h($direct) ?>" target="_blank" rel="noopener noreferrer">Regarder le culte en direct<span class="sr-only"> (nouvel onglet)</span></a></p>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($hasContact): ?>
    <section id="contact" class="section" aria-labelledby="contact-titre">
      <div class="wrap narrow">
        <h2 id="contact-titre">Nous contacter</h2>
        <ul class="contact-list">
          <?php foreach ($contact as $kind => $link): ?>
            <li><a href="<?= h($link['href']) ?>"<?= $kind === 'whatsapp' ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
              <?= h($kind === 'telephone' ? 'Téléphone' : ($kind === 'whatsapp' ? 'WhatsApp' : 'E-mail')) ?> : <?= h($link['label']) ?></a></li>
          <?php endforeach; ?>
          <?php foreach ($socials as $net => $href): ?>
            <li><a href="<?= h($href) ?>" target="_blank" rel="noopener noreferrer"><?= h($socialLabels[$net]) ?><span class="sr-only"> (nouvel onglet)</span></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>
  <?php endif; ?>
</main>

<footer class="site-footer">
  <div class="wrap">
    <p>&copy; <?= (int) date('Y') ?> <?= h($nom) ?></p>
    <p><a href="<?= h(url('index.php', ['page' => 'login'])) ?>">Connexion</a> · <a href="<?= h(url('index.php', ['page' => 'register'])) ?>">Créer un compte</a></p>
  </div>
</footer>
