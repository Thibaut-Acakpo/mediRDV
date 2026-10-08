<?php
require __DIR__ . '/../../includes/bootstrap.php';

api_post();
$user = api_auth('practitioner');
$in = json_input();
$id = (int)($in['id'] ?? 0);
$action = (string)($in['action'] ?? '');

if ($action === 'cancel') {
    $res = cancel_appointment($id, $user);
    if (!$res['ok']) json_error($res['error'], 409);
    json_out(['ok' => true, 'status' => 'cancelled', 'message' => 'Rendez-vous annulé, le patient a été prévenu.']);
}
$res = practitioner_set_status($id, (int)$user['pid'], $action);
if (!$res['ok']) {
    json_error($res['error'], 409);
}
$labels = ['confirmed' => 'Rendez-vous confirmé, le patient a été prévenu.', 'completed' => 'Consultation marquée comme terminée.', 'no_show' => 'Patient marqué comme absent.'];
json_out(['ok' => true, 'status' => $res['status'], 'message' => $labels[$res['status']] ?? 'Mis à jour.']);
