<?php
declare(strict_types=1);

function appointment_full(int $id): ?array
{
    return fetch_one(
        "SELECT a.*,
                pu.first_name AS patient_first, pu.last_name AS patient_last, pu.email AS patient_email, pu.phone AS patient_phone,
                du.first_name AS doc_first, du.last_name AS doc_last, du.email AS doc_email,
                p.city, p.address, p.price_fcfa, p.slot_minutes, p.user_id AS doc_user_id, s.name AS specialty
           FROM appointments a
           JOIN users pu ON pu.id = a.patient_id
           JOIN practitioners p ON p.id = a.practitioner_id
           JOIN users du ON du.id = p.user_id
           JOIN specialties s ON s.id = p.specialty_id
          WHERE a.id = ?",
        [$id]
    );
}

/** @return array{ok:bool, error?:string, id?:int, status?:string} */
function book_appointment(int $patientId, int $pid, string $start, string $reason): array
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $start) ?: DateTimeImmutable::createFromFormat('!Y-m-d H:i', $start);
    if (!$d) {
        return ['ok' => false, 'error' => 'Créneau invalide.'];
    }
    $startStr = $d->format('Y-m-d H:i:s');
    $date = $d->format('Y-m-d');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Le verrou sur le praticien sérialise les réservations concurrentes : pas de double réservation.
        $prac = fetch_one('SELECT id, slot_minutes, auto_confirm FROM practitioners WHERE id = ? AND is_active = 1 FOR UPDATE', [$pid]);
        if (!$prac) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Ce praticien n\'est pas disponible.'];
        }
        $free = array_column(get_day_slots($pid, $date), 'start');
        if (!in_array($startStr, $free, true)) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Ce créneau vient d\'être pris. Choisissez-en un autre.'];
        }
        $end = $d->modify('+' . (int)$prac['slot_minutes'] . ' minutes')->format('Y-m-d H:i:s');

        $clash = fetch_value(
            "SELECT id FROM appointments WHERE patient_id = ? AND status IN ('pending','confirmed') AND start_at < ? AND end_at > ? LIMIT 1",
            [$patientId, $end, $startStr]
        );
        if ($clash) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Vous avez déjà un rendez-vous à cette heure.'];
        }
        $upcoming = (int)fetch_value(
            "SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND practitioner_id = ? AND status IN ('pending','confirmed') AND start_at > ?",
            [$patientId, $pid, now()->format('Y-m-d H:i:s')]
        );
        if ($upcoming >= 3) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Vous avez déjà 3 rendez-vous à venir avec ce praticien.'];
        }

        $status = (int)$prac['auto_confirm'] === 1 ? 'confirmed' : 'pending';
        // Réservé dans la fenêtre de rappel : l'email de confirmation suffit, inutile de rappeler aussitôt.
        $inWindow = $d <= now()->modify('+' . (int)config('booking.reminder_hours', 24) . ' hours') ? 1 : 0;
        q('INSERT INTO appointments (patient_id, practitioner_id, start_at, end_at, status, reason, reminder_sent) VALUES (?,?,?,?,?,?,?)',
            [$patientId, $pid, $startStr, $end, $status, mb_substr(trim($reason), 0, 255) ?: null, $inWindow]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $t;
    }

    try {
        mail_booked(appointment_full($id));
    } catch (Throwable $t) {
        error_log('MédiRDV mail: ' . $t->getMessage());
    }
    return ['ok' => true, 'id' => $id, 'status' => $status];
}

/** Annulation par le patient ou le praticien. @return array{ok:bool,error?:string} */
function cancel_appointment(int $id, array $user): array
{
    $a = appointment_full($id);
    if (!$a) {
        return ['ok' => false, 'error' => 'Rendez-vous introuvable.'];
    }
    $by = null;
    if ($user['role'] === 'patient' && (int)$a['patient_id'] === (int)$user['id']) {
        $by = 'patient';
    } elseif ($user['role'] === 'practitioner' && (int)$a['practitioner_id'] === (int)$user['pid']) {
        $by = 'practitioner';
    }
    if (!$by) {
        return ['ok' => false, 'error' => 'Action non autorisée.'];
    }
    if (!in_array($a['status'], ['pending', 'confirmed'], true)) {
        return ['ok' => false, 'error' => 'Ce rendez-vous ne peut plus être annulé.'];
    }
    $start = new DateTimeImmutable($a['start_at']);
    if ($start <= now()) {
        return ['ok' => false, 'error' => 'Ce rendez-vous est déjà passé.'];
    }
    $limit = (int)config('booking.cancel_limit_hours', 2);
    if ($by === 'patient' && $start < now()->modify("+$limit hours")) {
        return ['ok' => false, 'error' => "À moins de $limit h du rendez-vous, contactez directement le cabinet."];
    }
    q("UPDATE appointments SET status = 'cancelled', cancelled_by = ?, cancelled_at = ? WHERE id = ?",
        [$by, now()->format('Y-m-d H:i:s'), $id]);
    try {
        mail_cancelled(appointment_full($id), $by);
    } catch (Throwable $t) {
        error_log('MédiRDV mail: ' . $t->getMessage());
    }
    return ['ok' => true];
}

/** Changement de statut par le praticien : confirm | complete | no_show. */
function practitioner_set_status(int $id, int $pid, string $action): array
{
    $a = appointment_full($id);
    if (!$a || (int)$a['practitioner_id'] !== $pid) {
        return ['ok' => false, 'error' => 'Rendez-vous introuvable.'];
    }
    $started = new DateTimeImmutable($a['start_at']) <= now();
    switch ($action) {
        case 'confirm':
            if ($a['status'] !== 'pending') return ['ok' => false, 'error' => 'Seul un rendez-vous en attente peut être confirmé.'];
            if ($started) return ['ok' => false, 'error' => 'Ce rendez-vous est déjà passé.'];
            $new = 'confirmed';
            break;
        case 'complete':
        case 'no_show':
            if (!in_array($a['status'], ['pending', 'confirmed'], true)) return ['ok' => false, 'error' => 'Statut non modifiable.'];
            if (!$started) return ['ok' => false, 'error' => 'Le rendez-vous n\'a pas encore commencé.'];
            $new = $action === 'complete' ? 'completed' : 'no_show';
            break;
        default:
            return ['ok' => false, 'error' => 'Action inconnue.'];
    }
    q('UPDATE appointments SET status = ? WHERE id = ?', [$new, $id]);
    if ($new === 'confirmed') {
        if (new DateTimeImmutable($a['start_at']) <= now()->modify('+' . (int)config('booking.reminder_hours', 24) . ' hours')) {
            q('UPDATE appointments SET reminder_sent = 1 WHERE id = ?', [$id]);
        }
        try {
            mail_confirmed(appointment_full($id));
        } catch (Throwable $t) {
            error_log('MédiRDV mail: ' . $t->getMessage());
        }
    }
    return ['ok' => true, 'status' => $new];
}

/** Rendez-vous d'un patient, filtrés : upcoming | past | cancelled. */
function patient_appointments(int $uid, string $tab): array
{
    $now = now()->format('Y-m-d H:i:s');
    $defs = [
        'upcoming'  => ["a.status IN ('pending','confirmed') AND a.start_at > ?", [$now], 'a.start_at ASC'],
        'past'      => ["(a.status IN ('completed','no_show') OR (a.status IN ('pending','confirmed') AND a.start_at <= ?))", [$now], 'a.start_at DESC'],
        'cancelled' => ["a.status = 'cancelled'", [], 'a.start_at DESC'],
    ];
    if (!isset($defs[$tab])) {
        return [];
    }
    [$cond, $params, $order] = $defs[$tab];
    return fetch_all(
        "SELECT a.*, du.first_name AS doc_first, du.last_name AS doc_last, p.city, p.address, p.id AS pid, s.name AS specialty
           FROM appointments a
           JOIN practitioners p ON p.id = a.practitioner_id
           JOIN users du ON du.id = p.user_id
           JOIN specialties s ON s.id = p.specialty_id
          WHERE a.patient_id = ? AND $cond ORDER BY $order LIMIT 200",
        array_merge([$uid], $params)
    );
}
