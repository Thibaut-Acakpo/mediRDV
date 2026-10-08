<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_auth('practitioner');
$pid = (int)$user['pid'];
$accErrors = [];
$proErrors = [];
$pwdError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $form = $_POST['form'] ?? '';
    if ($form === 'account') {
        $accErrors = update_account((int)$user['id'], $_POST);
        if (!$accErrors) { flash('success', 'Vos informations ont été mises à jour.'); redirect('praticien/profile.php'); }
    } elseif ($form === 'password') {
        $pwdError = change_password((int)$user['id'], (string)($_POST['current'] ?? ''), (string)($_POST['new'] ?? ''), (string)($_POST['confirm'] ?? ''));
        if (!$pwdError) { flash('success', 'Mot de passe modifié.'); redirect('praticien/profile.php'); }
    } elseif ($form === 'pro') {
        $spec = (int)($_POST['specialty_id'] ?? 0);
        $city = post_str('city', 80);
        $addr = post_str('address', 190);
        $slot = (int)($_POST['slot_minutes'] ?? 0);
        $price = (int)($_POST['price_fcfa'] ?? 0);
        $bio = mb_substr(trim((string)($_POST['bio'] ?? '')), 0, 1500);
        if (!fetch_value('SELECT id FROM specialties WHERE id = ?', [$spec])) $proErrors['specialty_id'] = 'Choisissez une spécialité.';
        if ($city === '') $proErrors['city'] = 'Indiquez votre ville.';
        if ($addr === '') $proErrors['address'] = 'Indiquez l\'adresse du cabinet.';
        if (!in_array($slot, [10, 15, 20, 30, 45, 60], true)) $proErrors['slot_minutes'] = 'Durée invalide.';
        if (!$proErrors) {
            q('UPDATE practitioners SET specialty_id=?, city=?, address=?, bio=?, slot_minutes=?, price_fcfa=?, auto_confirm=?, is_active=? WHERE id=?',
                [$spec, $city, $addr, $bio ?: null, $slot, $price > 0 ? $price : null, isset($_POST['auto_confirm']) ? 1 : 0, isset($_POST['is_active']) ? 1 : 0, $pid]);
            flash('success', 'Profil professionnel mis à jour.');
            redirect('praticien/profile.php');
        }
    }
}
$p = fetch_one('SELECT * FROM practitioners WHERE id = ?', [$pid]);
$isPro = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'pro';
$v = fn(string $k) => $isPro ? old($k) : e($p[$k] ?? '');
$specialties = specialties_all();

page_header('Mon profil', ['body' => 'page-dash']);
?>
<section class="container page-section">
  <?php dash_title('Mon profil', 'Ce que les patients voient sur votre fiche.', '<a class="btn btn-ghost" href="' . e(url('practitioner.php?id=' . $pid)) . '"><i class="bi bi-eye"></i> Voir ma fiche publique</a>'); ?>

  <form method="post" class="panel mb-4" novalidate>
    <?= csrf_field() ?><input type="hidden" name="form" value="pro">
    <h2 class="panel-title"><i class="bi bi-clipboard2-pulse"></i> Cabinet et consultations</h2>
    <?php if ($proErrors): ?><div class="alert alert-danger py-2">Certains champs sont à corriger.</div><?php endif; ?>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label" for="specialty_id">Spécialité</label>
        <select class="form-select<?= err_class($proErrors, 'specialty_id') ?>" id="specialty_id" name="specialty_id">
          <?php foreach ($specialties as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= (int)($isPro ? ($_POST['specialty_id'] ?? 0) : $p['specialty_id']) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select><?= err_msg($proErrors, 'specialty_id') ?>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="city">Ville</label>
        <input class="form-control<?= err_class($proErrors, 'city') ?>" id="city" name="city" value="<?= $v('city') ?>"><?= err_msg($proErrors, 'city') ?>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="address">Adresse du cabinet</label>
        <input class="form-control<?= err_class($proErrors, 'address') ?>" id="address" name="address" value="<?= $v('address') ?>"><?= err_msg($proErrors, 'address') ?>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="slot_minutes">Durée d'une consultation</label>
        <select class="form-select<?= err_class($proErrors, 'slot_minutes') ?>" id="slot_minutes" name="slot_minutes">
          <?php foreach ([10, 15, 20, 30, 45, 60] as $m): ?>
            <option value="<?= $m ?>" <?= (int)($isPro ? ($_POST['slot_minutes'] ?? 0) : $p['slot_minutes']) === $m ? 'selected' : '' ?>><?= $m ?> minutes</option>
          <?php endforeach; ?>
        </select><?= err_msg($proErrors, 'slot_minutes') ?>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="price_fcfa">Tarif (FCFA)</label>
        <input class="form-control" id="price_fcfa" name="price_fcfa" type="number" min="0" step="500" value="<?= $v('price_fcfa') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label" for="bio">Présentation</label>
        <textarea class="form-control" id="bio" name="bio" rows="3" maxlength="1500" placeholder="Parcours, domaines de compétence, langues parlées…"><?= $v('bio') ?></textarea>
      </div>
      <div class="col-md-6">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" id="auto_confirm" name="auto_confirm" <?= ($isPro ? isset($_POST['auto_confirm']) : $p['auto_confirm']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="auto_confirm">Confirmer automatiquement les rendez-vous<div class="form-text mt-0">Sinon, chaque demande attend votre validation.</div></label>
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" <?= ($isPro ? isset($_POST['is_active']) : $p['is_active']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="is_active">Visible dans l'annuaire<div class="form-text mt-0">Désactivez pour suspendre les nouvelles réservations.</div></label>
        </div>
      </div>
    </div>
    <button class="btn btn-brand mt-4" type="submit">Enregistrer</button>
  </form>

  <?php require ROOT_PATH . '/includes/account_partial.php'; ?>
</section>
<?php page_footer(); ?>
