<?php
require __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect(dashboard_url());
}
$errors = [];
$role = ($_GET['role'] ?? $_POST['role'] ?? 'patient') === 'practitioner' ? 'practitioner' : 'patient';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $res = register_user($_POST);
    if ($res['ok']) {
        clear_old();
        login_user($res['user_id']);
        flash('success', $role === 'practitioner'
            ? 'Bienvenue ! Vos horaires par défaut sont en place : ajustez-les dans « Disponibilités ».'
            : 'Votre compte est créé. Trouvez votre praticien et réservez un créneau.');
        redirect($role === 'practitioner' ? 'praticien/availability.php' : 'practitioners.php');
    }
    $errors = $res['errors'];
    keep_old($_POST);
}
$specialties = specialties_all();

page_header('Créer un compte', ['body' => 'page-auth', 'nav' => '']);
?>
<section class="auth-wrap">
  <div class="auth-card auth-card-wide">
    <div class="auth-logo"><?= logo_svg(34) ?></div>
    <h1>Créer votre compte</h1>
    <p class="text-body-secondary">Gratuit pour les patients. Prêt en moins d'une minute.</p>

    <?php if ($errors): ?><div class="alert alert-danger" role="alert">Certains champs sont à corriger.</div><?php endif; ?>

    <form method="post" novalidate id="registerForm">
      <?= csrf_field() ?>
      <div class="role-switch" role="radiogroup" aria-label="Type de compte">
        <label class="role-opt">
          <input type="radio" name="role" value="patient" <?= $role === 'patient' ? 'checked' : '' ?>>
          <span><i class="bi bi-person-heart"></i><strong>Je suis patient</strong><small>Je réserve des consultations</small></span>
        </label>
        <label class="role-opt">
          <input type="radio" name="role" value="practitioner" <?= $role === 'practitioner' ? 'checked' : '' ?>>
          <span><i class="bi bi-clipboard2-pulse"></i><strong>Je suis praticien</strong><small>Je gère mon agenda</small></span>
        </label>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-sm-6">
          <label class="form-label" for="first_name">Prénom</label>
          <input class="form-control<?= err_class($errors, 'first_name') ?>" id="first_name" name="first_name" value="<?= old('first_name') ?>" required>
          <?= err_msg($errors, 'first_name') ?>
        </div>
        <div class="col-sm-6">
          <label class="form-label" for="last_name">Nom</label>
          <input class="form-control<?= err_class($errors, 'last_name') ?>" id="last_name" name="last_name" value="<?= old('last_name') ?>" required>
          <?= err_msg($errors, 'last_name') ?>
        </div>
        <div class="col-sm-7">
          <label class="form-label" for="email">Adresse email</label>
          <input class="form-control<?= err_class($errors, 'email') ?>" id="email" name="email" type="email" autocomplete="email" value="<?= old('email') ?>" required>
          <?= err_msg($errors, 'email') ?>
        </div>
        <div class="col-sm-5">
          <label class="form-label" for="phone">Téléphone <span class="text-body-secondary">(facultatif)</span></label>
          <input class="form-control<?= err_class($errors, 'phone') ?>" id="phone" name="phone" type="tel" placeholder="+229 01 00 00 00 00" value="<?= old('phone') ?>">
          <?= err_msg($errors, 'phone') ?>
        </div>
      </div>

      <div id="practitionerFields" class="pro-fields" <?= $role === 'practitioner' ? '' : 'hidden' ?>>
        <h2 class="h6 mt-4 mb-3">Votre cabinet</h2>
        <div class="row g-3">
          <div class="col-sm-6">
            <label class="form-label" for="specialty_id">Spécialité</label>
            <select class="form-select<?= err_class($errors, 'specialty_id') ?>" id="specialty_id" name="specialty_id">
              <option value="">Choisir…</option>
              <?php foreach ($specialties as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= (int)old('specialty_id', 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <?= err_msg($errors, 'specialty_id') ?>
          </div>
          <div class="col-sm-6">
            <label class="form-label" for="city">Ville</label>
            <input class="form-control<?= err_class($errors, 'city') ?>" id="city" name="city" value="<?= old('city') ?>">
            <?= err_msg($errors, 'city') ?>
          </div>
          <div class="col-12">
            <label class="form-label" for="address">Adresse du cabinet</label>
            <input class="form-control<?= err_class($errors, 'address') ?>" id="address" name="address" value="<?= old('address') ?>">
            <?= err_msg($errors, 'address') ?>
          </div>
          <div class="col-sm-6">
            <label class="form-label" for="slot_minutes">Durée d'une consultation</label>
            <select class="form-select<?= err_class($errors, 'slot_minutes') ?>" id="slot_minutes" name="slot_minutes">
              <?php foreach ([10, 15, 20, 30, 45, 60] as $m): ?>
                <option value="<?= $m ?>" <?= (int)old('slot_minutes', 30) === $m ? 'selected' : '' ?>><?= $m ?> minutes</option>
              <?php endforeach; ?>
            </select>
            <?= err_msg($errors, 'slot_minutes') ?>
          </div>
          <div class="col-sm-6">
            <label class="form-label" for="price_fcfa">Tarif <span class="text-body-secondary">(FCFA, facultatif)</span></label>
            <input class="form-control" id="price_fcfa" name="price_fcfa" type="number" min="0" step="500" value="<?= old('price_fcfa') ?>">
          </div>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-sm-6">
          <label class="form-label" for="password">Mot de passe</label>
          <div class="input-eye">
            <input class="form-control<?= err_class($errors, 'password') ?>" id="password" name="password" type="password" autocomplete="new-password" required>
            <button type="button" class="eye" data-toggle-pass="password" aria-label="Afficher le mot de passe"><i class="bi bi-eye"></i></button>
          </div>
          <?= err_msg($errors, 'password') ?>
          <div class="pw-meter" id="pwMeter" aria-hidden="true"><span></span></div>
          <div class="form-text">8 caractères minimum, avec une lettre et un chiffre.</div>
        </div>
        <div class="col-sm-6">
          <label class="form-label" for="password_confirm">Confirmer le mot de passe</label>
          <input class="form-control<?= err_class($errors, 'password_confirm') ?>" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required>
          <?= err_msg($errors, 'password_confirm') ?>
        </div>
      </div>
      <button class="btn btn-brand w-100 mt-4" type="submit">Créer mon compte</button>
    </form>
    <p class="text-center mt-4 mb-0 text-body-secondary">Déjà inscrit ? <a href="<?= e(url('login.php')) ?>">Se connecter</a></p>
  </div>
</section>
<?php page_footer(['auth.js']); ?>
