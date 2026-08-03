<?php /* Porte d'accès d'une liste. Variables : $title, $page, $id. */ ?>
<div class="empty-state gate-state">
  <div class="emoji">🔒</div>
  <p>Cette liste est protégée. Confirmez votre identité (email ou nom + mot de passe) pour la consulter.</p>
</div>
<div class="gate-card">
  <form method="post" action="index.php">
    <input type="hidden" name="action" value="verify_access">
    <?= csrf_field() ?>
    <input type="hidden" name="page" value="<?= h($page) ?>">
    <input type="hidden" name="id" value="<?= h($id ?? '') ?>">
    <h3><?= h($title) ?></h3>
    <label class="field"><span>Email ou nom</span>
      <input type="text" name="name" required autofocus value="<?= h($_POST['name'] ?? '') ?>">
    </label>
    <label class="field"><span>Mot de passe</span>
      <input type="password" name="password" required>
    </label>
    <div class="modal-error" style="<?= isset($_GET['error']) ? 'display:block;' : 'display:none;' ?>">Identifiants incorrects.</div>
    <div class="modal-actions">
      <a class="btn btn-outline" href="<?= h(url('index.php', ['page' => $page])) ?>">Annuler</a>
      <button type="submit" class="btn btn-primary">Déverrouiller</button>
    </div>
  </form>
</div>
