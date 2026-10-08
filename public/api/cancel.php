<?php
require __DIR__ . '/../../includes/bootstrap.php';

api_post();
$user = api_auth();
$id = (int)(json_input()['id'] ?? 0);
$res = cancel_appointment($id, $user);
if (!$res['ok']) {
    json_error($res['error'], 409);
}
json_out(['ok' => true, 'message' => 'Rendez-vous annulé.']);
