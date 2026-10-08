<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_auth('practitioner');
$pid = (int)$user['pid'];

$week = get_weekly_hours($pid);
$off = array_map(fn($r) => [
    'id' => (int)$r['id'],
    'label' => $r['start_date'] === $r['end_date'] ? fmt_date($r['start_date'], true, true) : 'Du ' . fmt_date($r['start_date'], false, true) . ' au ' . fmt_date($r['end_date'], false, true),
    'reason' => $r['reason'],
], get_time_off($pid));
$dur = practitioner_duration($pid);

page_header('Disponibilités', ['body' => 'page-dash', 'nav' => 'avail']);
?>
<section class="container page-section">
  <?php dash_title('Disponibilités', 'Les patients ne voient que les créneaux compris dans ces plages, par tranches de ' . $dur . ' minutes.'); ?>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="panel" id="weekEditor"
           data-week='<?= e(json_encode($week)) ?>'>
        <h2 class="panel-title"><i class="bi bi-calendar-week"></i> Horaires de la semaine</h2>
        <div id="weekRows"></div>
        <div class="d-flex flex-wrap gap-2 align-items-center mt-4">
          <button class="btn btn-brand" id="saveWeek" type="button"><i class="bi bi-check2"></i> Enregistrer les horaires</button>
          <span class="small text-body-secondary" id="weekState"></span>
        </div>
        <div class="alert alert-danger mt-3 mb-0" id="weekError" hidden></div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="panel" id="offPanel" data-off='<?= e(json_encode($off)) ?>'>
        <h2 class="panel-title"><i class="bi bi-airplane"></i> Congés et fermetures</h2>
        <div class="row g-2">
          <div class="col-6"><label class="form-label small" for="offStart">Du</label><input class="form-control" type="date" id="offStart" min="<?= date('Y-m-d') ?>"></div>
          <div class="col-6"><label class="form-label small" for="offEnd">Au</label><input class="form-control" type="date" id="offEnd" min="<?= date('Y-m-d') ?>"></div>
          <div class="col-12"><label class="form-label small" for="offReason">Motif <span class="text-body-secondary">(facultatif)</span></label><input class="form-control" id="offReason" maxlength="150" placeholder="Congés, formation…"></div>
        </div>
        <button class="btn btn-ghost w-100 mt-3" id="addOff" type="button"><i class="bi bi-plus-lg"></i> Ajouter la période</button>
        <ul class="off-list" id="offList"></ul>
      </div>
    </div>
  </div>
</section>
<?php page_footer(['availability.js']); ?>
