<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_auth('patient');
maybe_run_reminders();

$now = now()->format('Y-m-d H:i:s');
$upcoming = patient_appointments((int)$user['id'], 'upcoming');
$doneCount = (int)fetch_value("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'completed'", [$user['id']]);
$prosCount = (int)fetch_value("SELECT COUNT(DISTINCT practitioner_id) FROM appointments WHERE patient_id = ? AND status IN ('confirmed','completed','pending')", [$user['id']]);
$next = $upcoming[0] ?? null;

page_header('Mon espace', ['body' => 'page-dash', 'nav' => 'dash']);
?>
<section class="container page-section">
  <?php dash_title('Bonjour ' . $user['first_name'], 'Voici où vous en êtes.', '<a class="btn btn-brand" href="' . e(url('practitioners.php')) . '"><i class="bi bi-plus-lg"></i> Prendre rendez-vous</a>'); ?>

  <div class="row g-3 mb-4">
    <?php foreach ([['À venir', count($upcoming), 'bi-calendar-event'], ['Consultations passées', $doneCount, 'bi-check2-circle'], ['Praticiens consultés', $prosCount, 'bi-people']] as $i => [$label, $n, $icon]): ?>
      <div class="col-sm-4" data-reveal style="--d:<?= $i * 0.08 ?>s">
        <div class="kpi" data-tilt-soft><i class="bi <?= $icon ?>"></i><span class="kpi-n" data-count="<?= $n ?>">0</span><span class="kpi-l"><?= e($label) ?></span></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($next): $nd = new DateTimeImmutable($next['start_at']); $diff = $nd->diff(now()); ?>
    <article class="next-appt" data-tilt data-reveal>
      <div>
        <span class="chip">Prochain rendez-vous</span>
        <h2>Dr <?= e($next['doc_first'] . ' ' . $next['doc_last']) ?></h2>
        <p class="mb-1"><?= e($next['specialty']) ?></p>
        <p class="mb-0 text-body-secondary"><i class="bi bi-geo-alt"></i> <?= e($next['address'] . ', ' . $next['city']) ?></p>
      </div>
      <div class="next-when">
        <strong><?= e(fmt_date($next['start_at'])) ?></strong>
        <span><?= e(fmt_time($next['start_at'])) ?></span>
        <small><?= $diff->days > 0 ? 'dans ' . $diff->days . ' jour' . ($diff->days > 1 ? 's' : '') : 'dans ' . $diff->h . ' h ' . $diff->i . ' min' ?></small>
      </div>
    </article>
  <?php else: ?>
    <div class="empty-state" data-reveal><i class="bi bi-calendar-plus"></i><h3>Aucun rendez-vous à venir</h3><p>Trouvez un praticien et réservez un créneau en deux minutes.</p>
      <a class="btn btn-brand" href="<?= e(url('practitioners.php')) ?>">Trouver un praticien</a></div>
  <?php endif; ?>

  <?php if (count($upcoming) > 1): ?>
    <h2 class="section-sub">Ensuite</h2>
    <div class="appt-list">
      <?php foreach (array_slice($upcoming, 1, 3) as $a) echo appt_card_patient($a); ?>
    </div>
    <p class="mt-3"><a href="<?= e(url('patient/appointments.php')) ?>">Voir tous mes rendez-vous</a></p>
  <?php endif; ?>
</section>
<?php confirm_modal(); page_footer(); ?>
