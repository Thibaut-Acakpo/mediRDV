<?php
require __DIR__ . '/../includes/bootstrap.php';

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $email = post_str('email', 190);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        start_password_reset($email);
    }
    $sent = true;
}
page_header('Mot de passe oublié', ['body' => 'page-auth']);
?>
<section class="auth-wrap">
  <div class="auth-card" data-tilt-soft>
    <div class="auth-logo"><i class="bi bi-key"></i></div>
    <h1>Mot de passe oublié</h1>
    <?php if ($sent): ?>
      <div class="alert alert-success">Si un compte existe avec cette adresse, un lien de réinitialisation vient d'être envoyé. Il est valable 1 heure.</div>
      <?php if (config('app.env') === 'dev' && config('mail.driver') === 'log'): ?>
        <p class="small text-body-secondary">Mode développement : l'email est enregistré dans le dossier <code>storage/mails</code> (ouvrez le dernier fichier dans votre navigateur).</p>
      <?php endif; ?>
      <a class="btn btn-ghost w-100" href="<?= e(url('login.php')) ?>">Retour à la connexion</a>
    <?php else: ?>
      <p class="text-body-secondary">Indiquez votre email, nous vous envoyons un lien pour en choisir un nouveau.</p>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <label class="form-label" for="email">Adresse email</label>
        <input class="form-control" id="email" name="email" type="email" required autofocus>
        <button class="btn btn-brand w-100 mt-3" type="submit">Envoyer le lien</button>
      </form>
      <p class="text-center mt-4 mb-0"><a href="<?= e(url('login.php')) ?>">Retour à la connexion</a></p>
    <?php endif; ?>
  </div>
</section>
<?php page_footer(); ?>
