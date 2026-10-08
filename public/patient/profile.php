<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_auth('patient');
$accErrors = [];
$pwdError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    if (($_POST['form'] ?? '') === 'account') {
        $accErrors = update_account((int)$user['id'], $_POST);
        if (!$accErrors) { clear_old(); flash('success', 'Vos informations ont été mises à jour.'); redirect('patient/profile.php'); }
        keep_old($_POST);
    } elseif (($_POST['form'] ?? '') === 'password') {
        $pwdError = change_password((int)$user['id'], (string)($_POST['current'] ?? ''), (string)($_POST['new'] ?? ''), (string)($_POST['confirm'] ?? ''));
        if (!$pwdError) { flash('success', 'Mot de passe modifié.'); redirect('patient/profile.php'); }
    }
}
page_header('Mon profil', ['body' => 'page-dash']);
?>
<section class="container page-section">
  <?php dash_title('Mon profil', 'Ces informations sont utilisées pour vos confirmations et rappels.'); ?>
  <?php require ROOT_PATH . '/includes/account_partial.php'; ?>
</section>
<?php page_footer(); ?>
