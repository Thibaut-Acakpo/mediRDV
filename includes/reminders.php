<?php
declare(strict_types=1);

/** Envoie les rappels dus (confirmés, pas encore rappelés, dans la fenêtre configurée). */
function send_due_reminders(): int
{
    $hours = (int)config('booking.reminder_hours', 24);
    $now   = now();
    $rows  = fetch_all(
        "SELECT id FROM appointments
          WHERE status = 'confirmed' AND reminder_sent = 0 AND start_at > ? AND start_at <= ?
          ORDER BY start_at LIMIT 100",
        [$now->format('Y-m-d H:i:s'), $now->modify("+$hours hours")->format('Y-m-d H:i:s')]
    );
    $sent = 0;
    foreach ($rows as $r) {
        // Réservation atomique : deux exécutions simultanées n'enverront pas deux fois le rappel.
        $claimed = q('UPDATE appointments SET reminder_sent = 1, reminder_sent_at = ? WHERE id = ? AND reminder_sent = 0',
            [$now->format('Y-m-d H:i:s'), $r['id']])->rowCount();
        if ($claimed !== 1) {
            continue;
        }
        $ok = mail_reminder(appointment_full((int)$r['id']));
        if ($ok) {
            $sent++;
        } else {
            q('UPDATE appointments SET reminder_sent = 0, reminder_sent_at = NULL WHERE id = ?', [$r['id']]);
        }
    }
    return $sent;
}

/** Filet de sécurité sans tâche planifiée : au plus une fois toutes les 10 minutes. */
function maybe_run_reminders(): void
{
    if (!config('reminders.on_request', true)) {
        return;
    }
    $file = ROOT_PATH . '/storage/last_reminder.txt';
    $last = is_file($file) ? (int)@file_get_contents($file) : 0;
    if (time() - $last < 600) {
        return;
    }
    @file_put_contents($file, (string)time(), LOCK_EX);
    try {
        send_due_reminders();
    } catch (Throwable $t) {
        error_log('MédiRDV reminders: ' . $t->getMessage());
    }
}
