<?php
require __DIR__ . '/../includes/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$p = fetch_one(
    'SELECT p.*, u.first_name, u.last_name, s.name AS specialty, s.icon
       FROM practitioners p JOIN users u ON u.id = p.user_id JOIN specialties s ON s.id = p.specialty_id
      WHERE p.id = ? AND p.is_active = 1',
    [$id]
);
if (!$p) {
    http_response_code(404);
    page_header('Praticien introuvable', ['body' => 'page-search']);
    echo '<div class="container page-section"><div class="empty-state"><i class="bi bi-person-x"></i><h3>Praticien introuvable</h3><p>Ce profil n\'existe pas ou n\'est plus actif.</p><a class="btn btn-brand" href="' . e(url('practitioners.php')) . '">Voir tous les praticiens</a></div></div>';
    page_footer();
    exit;
}
$user = current_user();
$mode = !$user ? 'guest' : ($user['role'] === 'patient' ? 'patient' : 'practitioner');
$loginUrl = url('login.php?next=' . urlencode('practitioner.php?id=' . $id));

page_header('Dr ' . $p['first_name'] . ' ' . $p['last_name'], ['body' => 'page-search', 'nav' => 'search']);
?>
<section class="container page-section">
  <a class="back-link" href="<?= e(url('practitioners.php')) ?>"><i class="bi bi-arrow-left"></i> Tous les praticiens</a>
  <div class="row g-4 mt-1">
    <div class="col-lg-5">
      <article class="profile-card" data-tilt-soft>
        <span class="avatar avatar-xl"><?= e(initials($p['first_name'], $p['last_name'])) ?></span>
        <h1>Dr <?= e($p['first_name'] . ' ' . $p['last_name']) ?></h1>
        <span class="chip"><i class="bi <?= e($p['icon']) ?>"></i> <?= e($p['specialty']) ?></span>
        <?php if ($p['bio']): ?><p class="bio"><?= nl2br(e($p['bio'])) ?></p><?php endif; ?>
        <ul class="pro-meta">
          <li><i class="bi bi-geo-alt"></i> <?= e($p['address'] . ', ' . $p['city']) ?></li>
          <li><i class="bi bi-clock"></i> Consultation de <?= (int)$p['slot_minutes'] ?> minutes</li>
          <li><i class="bi bi-cash-coin"></i> <?= e(fmt_price($p['price_fcfa'] ? (int)$p['price_fcfa'] : null)) ?></li>
          <li><i class="bi bi-<?= $p['auto_confirm'] ? 'lightning-charge' : 'hourglass-split' ?>"></i> <?= $p['auto_confirm'] ? 'Confirmation immédiate' : 'Confirmation par le praticien' ?></li>
        </ul>
      </article>
    </div>

    <div class="col-lg-7">
      <div class="booking" id="booking"
           data-practitioner="<?= (int)$p['id'] ?>" data-mode="<?= e($mode) ?>" data-login="<?= e($loginUrl) ?>"
           data-auto="<?= (int)$p['auto_confirm'] ?>" data-duration="<?= (int)$p['slot_minutes'] ?>">
        <h2><i class="bi bi-calendar2-week"></i> Choisissez votre créneau</h2>

        <?php if ($mode === 'practitioner'): ?>
          <div class="alert alert-info">Vous êtes connecté en tant que praticien : seuls les patients peuvent réserver.</div>
        <?php endif; ?>

        <div class="day-strip-wrap">
          <button class="strip-btn" type="button" id="stripPrev" aria-label="Jours précédents"><i class="bi bi-chevron-left"></i></button>
          <div class="day-strip" id="dayStrip" role="listbox" aria-label="Jours disponibles">
            <div class="strip-loading">Chargement des disponibilités…</div>
          </div>
          <button class="strip-btn" type="button" id="stripNext" aria-label="Jours suivants"><i class="bi bi-chevron-right"></i></button>
        </div>

        <div class="slots-area">
          <h3 id="slotsTitle" class="slots-title">Sélectionnez un jour</h3>
          <div class="slots" id="slots" role="listbox" aria-label="Créneaux horaires"></div>
        </div>

        <div class="book-confirm" id="bookConfirm" hidden>
          <div class="summary" id="summary"></div>
          <label class="form-label" for="reason">Motif de la consultation <span class="text-body-secondary">(facultatif)</span></label>
          <textarea class="form-control" id="reason" rows="2" maxlength="255" placeholder="Ex. : fièvre depuis 2 jours, renouvellement d'ordonnance…"></textarea>
          <button class="btn btn-brand btn-lg w-100 mt-3" id="bookBtn" type="button" <?= $mode === 'practitioner' ? 'disabled' : '' ?>>
            <?= $mode === 'guest' ? 'Se connecter pour confirmer' : 'Confirmer le rendez-vous' ?>
          </button>
          <div class="alert alert-danger mt-3 mb-0" id="bookError" hidden></div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php page_footer(['booking.js']); ?>
