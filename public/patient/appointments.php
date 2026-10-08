<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_auth('patient');
maybe_run_reminders();

$tab = in_array($_GET['tab'] ?? '', ['upcoming', 'past', 'cancelled'], true) ? $_GET['tab'] : 'upcoming';
$list = patient_appointments((int)$user['id'], $tab);
$tabs = ['upcoming' => 'À venir', 'past' => 'Passés', 'cancelled' => 'Annulés'];
$empty = [
    'upcoming'  => ['bi-calendar-plus', 'Rien de prévu', 'Réservez votre prochaine consultation en quelques clics.'],
    'past'      => ['bi-clock-history', 'Aucune consultation passée', 'Votre historique apparaîtra ici.'],
    'cancelled' => ['bi-calendar-x', 'Aucun rendez-vous annulé', 'Les rendez-vous annulés apparaîtront ici.'],
];

page_header('Mes rendez-vous', ['body' => 'page-dash', 'nav' => 'appts']);
?>
<section class="container page-section">
  <?php dash_title('Mes rendez-vous', '', '<a class="btn btn-brand" href="' . e(url('practitioners.php')) . '"><i class="bi bi-plus-lg"></i> Nouveau rendez-vous</a>'); ?>
  <ul class="tabs-pill" data-reveal>
    <?php foreach ($tabs as $k => $label): ?>
      <li><a class="<?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>"><?= e($label) ?></a></li>
    <?php endforeach; ?>
  </ul>

  <?php if ($list): ?>
    <div class="appt-list"><?php foreach ($list as $a) echo appt_card_patient($a); ?></div>
  <?php else: [$ic, $t, $d] = $empty[$tab]; ?>
    <div class="empty-state"><i class="bi <?= $ic ?>"></i><h3><?= e($t) ?></h3><p><?= e($d) ?></p>
      <?php if ($tab === 'upcoming'): ?><a class="btn btn-brand" href="<?= e(url('practitioners.php')) ?>">Trouver un praticien</a><?php endif; ?></div>
  <?php endif; ?>
</section>
<?php confirm_modal(); page_footer(); ?>
