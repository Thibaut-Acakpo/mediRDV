<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_auth('practitioner');
$pid = (int)$user['pid'];
maybe_run_reminders();

$today = now()->format('Y-m-d');
$nowStr = now()->format('Y-m-d H:i:s');
$weekEnd = now()->modify('sunday this week')->format('Y-m-d') . ' 23:59:59';

$todayList = fetch_all(
    "SELECT a.*, u.first_name, u.last_name, u.phone FROM appointments a JOIN users u ON u.id = a.patient_id
      WHERE a.practitioner_id = ? AND DATE(a.start_at) = ? AND a.status <> 'cancelled' ORDER BY a.start_at",
    [$pid, $today]
);
$pending = fetch_all(
    "SELECT a.*, u.first_name, u.last_name, u.phone FROM appointments a JOIN users u ON u.id = a.patient_id
      WHERE a.practitioner_id = ? AND a.status = 'pending' AND a.start_at > ? ORDER BY a.start_at LIMIT 10",
    [$pid, $nowStr]
);
$weekCount = (int)fetch_value("SELECT COUNT(*) FROM appointments WHERE practitioner_id = ? AND status IN ('pending','confirmed') AND start_at > ? AND start_at <= ?", [$pid, $nowStr, $weekEnd]);
$patients = (int)fetch_value("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE practitioner_id = ? AND status IN ('completed','confirmed')", [$pid]);
$todayActive = count(array_filter($todayList, fn($a) => in_array($a['status'], ['pending', 'confirmed'], true)));

page_header('Tableau de bord', ['body' => 'page-dash', 'nav' => 'dash']);
?>
<section class="container page-section">
  <?php dash_title('Bonjour Dr ' . $user['last_name'], ucfirst(fmt_date(now(), true, true)),
      '<a class="btn btn-ghost" href="' . e(url('praticien/availability.php')) . '"><i class="bi bi-clock"></i> Mes disponibilités</a>' .
      '<a class="btn btn-brand" href="' . e(url('praticien/appointments.php')) . '"><i class="bi bi-calendar3"></i> Voir l\'agenda</a>'); ?>

  <div class="row g-3 mb-4">
    <?php foreach ([["Aujourd'hui", $todayActive, 'bi-calendar-day'], ['À confirmer', count($pending), 'bi-hourglass-split'], ['Cette semaine', $weekCount, 'bi-calendar-week'], ['Patients suivis', $patients, 'bi-people']] as $i => [$label, $n, $icon]): ?>
      <div class="col-6 col-lg-3" data-reveal style="--d:<?= $i * 0.07 ?>s">
        <div class="kpi" data-tilt-soft><i class="bi <?= $icon ?>"></i><span class="kpi-n" data-count="<?= $n ?>">0</span><span class="kpi-l"><?= e($label) ?></span></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="panel">
        <h2 class="panel-title"><i class="bi bi-calendar-day"></i> Agenda du jour</h2>
        <?php if (!$todayList): ?>
          <div class="empty-inline">Aucun rendez-vous aujourd'hui.</div>
        <?php else: ?>
          <ol class="timeline">
            <?php foreach ($todayList as $a):
                $started = new DateTimeImmutable($a['start_at']) <= now();
                $open = in_array($a['status'], ['pending', 'confirmed'], true); ?>
              <li class="tl-item st-<?= e($a['status']) ?>">
                <time><?= e(fmt_time($a['start_at'])) ?></time>
                <div class="tl-body">
                  <div class="d-flex flex-wrap gap-2 align-items-center"><strong><?= e($a['first_name'] . ' ' . $a['last_name']) ?></strong><?= status_badge($a['status']) ?></div>
                  <?php if ($a['reason']): ?><div class="small text-body-secondary"><?= e($a['reason']) ?></div><?php endif; ?>
                  <?php if ($a['phone']): ?><div class="small text-body-secondary"><i class="bi bi-telephone"></i> <?= e($a['phone']) ?></div><?php endif; ?>
                  <?php if ($open && $started): ?>
                    <div class="d-flex gap-2 mt-2">
                      <button class="btn btn-sm btn-brand" data-api="appointment_status.php" data-payload='{"id":<?= (int)$a['id'] ?>,"action":"complete"}'><i class="bi bi-check2"></i> Terminé</button>
                      <button class="btn btn-sm btn-ghost" data-api="appointment_status.php" data-payload='{"id":<?= (int)$a['id'] ?>,"action":"no_show"}'>Absent</button>
                    </div>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="panel">
        <h2 class="panel-title"><i class="bi bi-hourglass-split"></i> À confirmer</h2>
        <?php if (!$pending): ?>
          <div class="empty-inline">Aucune demande en attente.</div>
        <?php else: foreach ($pending as $a): ?>
          <div class="pending-row">
            <div>
              <strong><?= e($a['first_name'] . ' ' . $a['last_name']) ?></strong>
              <div class="small text-body-secondary"><?= e(fmt_datetime($a['start_at'])) ?></div>
              <?php if ($a['reason']): ?><div class="small"><?= e($a['reason']) ?></div><?php endif; ?>
            </div>
            <div class="d-flex gap-1">
              <button class="btn btn-sm btn-brand" title="Confirmer" data-api="appointment_status.php" data-payload='{"id":<?= (int)$a['id'] ?>,"action":"confirm"}'><i class="bi bi-check-lg"></i></button>
              <button class="btn btn-sm btn-outline-danger" title="Refuser" data-api="appointment_status.php" data-payload='{"id":<?= (int)$a['id'] ?>,"action":"cancel"}'
                      data-confirm="Refuser la demande de <?= e($a['first_name'] . ' ' . $a['last_name']) ?> ? Le patient sera prévenu par email."><i class="bi bi-x-lg"></i></button>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</section>
<?php confirm_modal(); page_footer(); ?>
