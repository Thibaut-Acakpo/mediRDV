<?php
require __DIR__ . '/../../includes/bootstrap.php';

$pid  = (int)($_GET['practitioner'] ?? 0);
$date = (string)($_GET['date'] ?? '');
if ($pid <= 0 || !valid_date($date)) {
    json_error('Paramètres invalides.');
}
if (!date_in_horizon($date)) {
    json_error('Cette date n\'est pas réservable.');
}
$slots = get_day_slots($pid, $date);
json_out(['ok' => true, 'date' => $date, 'label' => fmt_date($date, true, false), 'slots' => $slots]);
