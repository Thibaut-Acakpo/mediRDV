<?php
/** Formulaires « Mes informations » et « Mot de passe » partagés. Variables attendues : $user, $accErrors, $pwdError. */
$me = fetch_one('SELECT first_name, last_name, email, phone FROM users WHERE id = ?', [$user['id']]);
$val = fn(string $k) => $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'account' ? old($k) : e($me[$k] ?? '');
?>
<div class="row g-4">
  <div class="col-lg-7">
    <form method="post" class="panel" novalidate>
      <?= csrf_field() ?><input type="hidden" name="form" value="account">
      <h2 class="panel-title"><i class="bi bi-person-vcard"></i> Mes informations</h2>
      <div class="row g-3">
        <div class="col-sm-6">
          <label class="form-label" for="a_first">Prénom</label>
          <input class="form-control<?= err_class($accErrors, 'first_name') ?>" id="a_first" name="first_name" value="<?= $val('first_name') ?>">
          <?= err_msg($accErrors, 'first_name') ?>
        </div>
        <div class="col-sm-6">
          <label class="form-label" for="a_last">Nom</label>
          <input class="form-control<?= err_class($accErrors, 'last_name') ?>" id="a_last" name="last_name" value="<?= $val('last_name') ?>">
          <?= err_msg($accErrors, 'last_name') ?>
        </div>
        <div class="col-sm-7">
          <label class="form-label" for="a_email">Email</label>
          <input class="form-control<?= err_class($accErrors, 'email') ?>" id="a_email" name="email" type="email" value="<?= $val('email') ?>">
          <?= err_msg($accErrors, 'email') ?>
        </div>
        <div class="col-sm-5">
          <label class="form-label" for="a_phone">Téléphone</label>
          <input class="form-control<?= err_class($accErrors, 'phone') ?>" id="a_phone" name="phone" type="tel" value="<?= $val('phone') ?>">
          <?= err_msg($accErrors, 'phone') ?>
        </div>
      </div>
      <button class="btn btn-brand mt-4" type="submit">Enregistrer</button>
    </form>
  </div>
  <div class="col-lg-5">
    <form method="post" class="panel" novalidate>
      <?= csrf_field() ?><input type="hidden" name="form" value="password">
      <h2 class="panel-title"><i class="bi bi-shield-lock"></i> Mot de passe</h2>
      <?php if ($pwdError): ?><div class="alert alert-danger py-2"><?= e($pwdError) ?></div><?php endif; ?>
      <label class="form-label" for="p_cur">Mot de passe actuel</label>
      <input class="form-control mb-3" id="p_cur" name="current" type="password" autocomplete="current-password">
      <label class="form-label" for="p_new">Nouveau mot de passe</label>
      <input class="form-control mb-3" id="p_new" name="new" type="password" autocomplete="new-password">
      <label class="form-label" for="p_conf">Confirmer</label>
      <input class="form-control" id="p_conf" name="confirm" type="password" autocomplete="new-password">
      <button class="btn btn-ghost mt-4" type="submit">Changer le mot de passe</button>
    </form>
  </div>
</div>
