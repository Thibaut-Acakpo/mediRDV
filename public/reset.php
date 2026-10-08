<?php
require __DIR__ . '/../includes/bootstrap.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$reset = find_reset($token);
$error = null;

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $pwd = (string)($_POST['password'] ?? '');
    if ($e = validate_password($pwd)) {
        $error = $e;
    } elseif ($pwd !== (string)($_POST['password_confirm'] ?? '')) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        complete_password_reset($reset, $pwd);
        flash('success', 'Mot de passe modifié. Vous pouvez vous connecter.');
        redirect('login.php');
    }
}
page_header('Nouveau mot de passe', ['body' => 'page-auth']);
?>
<section class="auth-wrap">
  <div class="auth-card" data-tilt-soft>
    <div class="auth-logo"><i class="bi bi-shield-lock"></i></div>
    <h1>Nouveau mot de passe</h1>
    <?php if (!$reset): ?>
      <div class="alert alert-warning">Ce lien est invalide ou a expiré.</div>
      <a class="btn btn-brand w-100" href="<?= e(url('forgot.php')) ?>">Demander un nouveau lien</a>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label class="form-label" for="password">Nouveau mot de passe</label>
        <input class="form-control mb-3" id="password" name="password" type="password" autocomplete="new-password" required autofocus>
        <label class="form-label" for="password_confirm">Confirmer</label>
        <input class="form-control" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required>
        <div class="form-text">8 caractères minimum, avec une lettre et un chiffre.</div>
        <button class="btn btn-brand w-100 mt-3" type="submit">Enregistrer</button>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php page_footer(); ?>
