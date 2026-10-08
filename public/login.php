<?php
require __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect(dashboard_url());
}
$next  = safe_next($_GET['next'] ?? $_POST['next'] ?? null);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $res = attempt_login(post_str('email', 190), (string)($_POST['password'] ?? ''));
    if ($res['ok']) {
        clear_old();
        login_user($res['user_id']);
        redirect($next ?: dashboard_url());
    }
    $error = $res['error'];
    keep_old($_POST);
}

page_header('Connexion', ['body' => 'page-auth', 'nav' => '']);
?>
<section class="auth-wrap">
  <div class="auth-card" data-tilt-soft>
    <div class="auth-logo"><?= logo_svg(34) ?></div>
    <h1>Bon retour parmi nous</h1>
    <p class="text-body-secondary">Connectez-vous pour gérer vos rendez-vous.</p>

    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <?php if ($next): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
      <div class="mb-3">
        <label class="form-label" for="email">Adresse email</label>
        <input class="form-control" id="email" name="email" type="email" autocomplete="email" required value="<?= old('email') ?>" autofocus>
      </div>
      <div class="mb-2">
        <label class="form-label d-flex justify-content-between" for="password">Mot de passe <a href="<?= e(url('forgot.php')) ?>" class="small">Mot de passe oublié ?</a></label>
        <div class="input-eye">
          <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
          <button type="button" class="eye" data-toggle-pass="password" aria-label="Afficher le mot de passe"><i class="bi bi-eye"></i></button>
        </div>
      </div>
      <button class="btn btn-brand w-100 mt-3" type="submit">Se connecter</button>
    </form>
    <p class="text-center mt-4 mb-0 text-body-secondary">Pas encore de compte ? <a href="<?= e(url('register.php')) ?>">Créer un compte</a></p>

    <?php if (config('app.env') === 'dev'): ?>
    <div class="demo-box">
      <span class="small text-body-secondary">Comptes de démonstration</span>
      <div class="d-flex gap-2 mt-2 flex-wrap">
        <button type="button" class="btn btn-ghost btn-sm" data-fill-email="patient@medirdv.test" data-fill-pass="Patient123"><i class="bi bi-person"></i> Patient</button>
        <button type="button" class="btn btn-ghost btn-sm" data-fill-email="dr.adjovi@medirdv.test" data-fill-pass="Praticien123"><i class="bi bi-heart-pulse"></i> Praticien</button>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php page_footer(); ?>
