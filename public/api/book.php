<?php
require __DIR__ . '/../../includes/bootstrap.php';

api_post();
$user = api_auth('patient');
$in = json_input();

$pid   = (int)($in['practitioner_id'] ?? 0);
$start = (string)($in['start'] ?? '');
$reason = (string)($in['reason'] ?? '');
if ($pid <= 0 || $start === '') {
    json_error('Choisissez un créneau.');
}
$res = book_appointment((int)$user['id'], $pid, $start, $reason);
if (!$res['ok']) {
    json_error($res['error'], 409);
}
$msg = $res['status'] === 'confirmed'
    ? 'Rendez-vous confirmé ! Un email de confirmation vous a été envoyé.'
    : 'Demande envoyée. Le praticien doit la confirmer, vous recevrez un email.';
flash('success', $msg);
json_out(['ok' => true, 'id' => $res['id'], 'status' => $res['status'], 'redirect' => url('patient/appointments.php')]);
