<?php
require __DIR__ . '/../../includes/bootstrap.php';

$pid = (int)($_GET['practitioner'] ?? 0);
if ($pid <= 0 || practitioner_duration($pid) <= 0) {
    json_error('Praticien introuvable.', 404);
}
$days = [];
foreach (get_overview($pid, (int)config('booking.horizon_days', 45) + 1) as $date => $count) {
    $d = new DateTimeImmutable($date);
    $days[] = [
        'date'  => $date,
        'count' => $count,
        'dow'   => mb_substr(FR_DAYS[(int)$d->format('N') - 1], 0, 3),
        'day'   => (int)$d->format('j'),
        'month' => mb_substr(FR_MONTHS[(int)$d->format('n') - 1], 0, 4),
    ];
}
json_out(['ok' => true, 'days' => $days]);
