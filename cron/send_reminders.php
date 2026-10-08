<?php
/**
 * Envoi des rappels de rendez-vous par email.
 * À planifier toutes les 10 à 15 minutes (voir README.md) :
 *   php cron/send_reminders.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI uniquement.');
}
require dirname(__DIR__) . '/includes/bootstrap.php';

$n = send_due_reminders();
echo '[' . date('Y-m-d H:i:s') . "] $n rappel(s) envoyé(s).\n";
