<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_auth('practitioner');
$pid = (int)$user['pid'];
maybe_run_reminders();

$views = ['today' => "Aujourd'hui", 'upcoming' => 'À venir', 'pending' => 'À confirmer', 'past' => 'Passés', 'cancelled' => 'Annulés'];
$view = array_key_exists($_GET['view'] ?? '', $views) ? $_GET['view'] : 'upcoming';
$now = now()->format('Y-m-d H:i:s');
$today = now()->format('Y-m-d');

$conds = [
    'today'     => ["DATE(a.start_at) = ? AND a.status <> 'cancelled'", [$today], 'a.start_at ASC'],
    'upcoming'  => ["a.status IN ('pending','confirmed') AND a.start_at > ?", [$now], 'a.start_at ASC'],
    'pending'   => ["a.status = 'pending' AND a.start_at > ?", [$now], 'a.start_at ASC'],
    'past'      => ["(a.status IN ('completed','no_show') OR (a.status IN ('pending','confirmed') AND a.start_at <= ?))", [$now], 'a.start_at DESC'],
    'cancelled' => ["a.status = 'cancelled'", [], 'a.start_at DESC'],
];
[$cond, $params, $order] = $conds[$view];
$list = fetch_all(
    "SELECT a.*, u.first_name, u.last_name, u.phone, u.email FROM appointments a JOIN users u ON u.id = a.patient_id
      WHERE a.practitioner_id = ? AND $cond ORDER BY $order LIMIT 200",
    array_merge([$pid], $params)
);

page_header('Agenda', ['body' => 'page-dash', 'nav' => 'appts']);
?>
<section class="container page-section">
  <?php dash_title('Agenda', 'Tous vos rendez-vous, du plus proche au plus lointain.'); ?>
  <ul class="tabs-pill" data-reveal>
    <?php foreach ($views as $k => $label): ?>
      <li><a class="<?= $view === $k ? 'active' : '' ?>" href="?view=<?= $k ?>"><?= e($label) ?></a></li>
    <?php endforeach; ?>
  </ul>

  <?php if (!$list): ?>
    <div class="empty-state"><i class="bi bi-calendar2-check"></i><h3>Rien à afficher</h3><p>Aucun rendez-vous dans cette vue.</p></div>
  <?php else: ?>
  <div class="panel p-0 table-panel">
    <div class="table-responsive">
      <table class="table agenda-table align-middle mb-0">
        <thead><tr><th>Date</th><th>Patient</th><th>Motif</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($list as $a):
            $started = new DateTimeImmutable($a['start_at']) <= now();
            $open = in_array($a['status'], ['pending', 'confirmed'], true);
            $pl = fn(string $act) => e(json_encode(['id' => (int)$a['id'], 'action' => $act])); ?>
          <tr>
            <td><strong><?= e(fmt_date($a['start_at'])) ?></strong><div class="small text-body-secondary"><?= e(fmt_time($a['start_at']) . ' - ' . fmt_time($a['end_at'])) ?></div></td>
            <td><?= e($a['first_name'] . ' ' . $a['last_name']) ?>
              <div class="small text-body-secondary"><?= $a['phone'] ? '<i class="bi bi-telephone"></i> ' . e($a['phone']) : e($a['email']) ?></div></td>
            <td class="small"><?= $a['reason'] ? e($a['reason']) : '<span class="text-body-secondary">—</span>' ?></td>
            <td><?= status_badge($a['status']) ?></td>
            <td class="text-end text-nowrap">
              <?php if ($a['status'] === 'pending' && !$started): ?>
                <button class="btn btn-sm btn-brand" data-api="appointment_status.php" data-payload='<?= $pl('confirm') ?>'>Confirmer</button>
              <?php endif; ?>
              <?php if ($open && $started): ?>
                <button class="btn btn-sm btn-brand" data-api="appointment_status.php" data-payload='<?= $pl('complete') ?>'>Terminé</button>
                <button class="btn btn-sm btn-ghost" data-api="appointment_status.php" data-payload='<?= $pl('no_show') ?>'>Absent</button>
              <?php endif; ?>
              <?php if ($open && !$started): ?>
                <button class="btn btn-sm btn-outline-danger" data-api="appointment_status.php" data-payload='<?= $pl('cancel') ?>'
                        data-confirm="Annuler le rendez-vous de <?= e($a['first_name'] . ' ' . $a['last_name']) ?> le <?= e(fmt_datetime($a['start_at'])) ?> ? Le patient sera prévenu par email.">Annuler</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</section>
<?php confirm_modal(); page_footer(); ?>
