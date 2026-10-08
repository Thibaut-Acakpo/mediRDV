<?php
declare(strict_types=1);

/**
 * Moteur de créneaux : horaires hebdomadaires - congés - rendez-vous existants.
 * Toutes les dates sont "locales" (fuseau Africa/Porto-Novo), stockées sans fuseau.
 */

function valid_date(string $date): bool
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

function date_in_horizon(string $date): bool
{
    if (!valid_date($date)) {
        return false;
    }
    $today = now()->setTime(0, 0);
    $max   = $today->modify('+' . (int)config('booking.horizon_days', 45) . ' days');
    $d     = new DateTimeImmutable($date);
    return $d >= $today && $d <= $max;
}

/** Charge en une fois tout ce qu'il faut pour calculer les créneaux d'une période. */
function load_schedule(int $pid, string $from, string $to): array
{
    $av = [];
    foreach (fetch_all('SELECT weekday, start_time, end_time FROM availabilities WHERE practitioner_id = ? ORDER BY start_time', [$pid]) as $r) {
        $av[(int)$r['weekday']][] = [substr($r['start_time'], 0, 5), substr($r['end_time'], 0, 5)];
    }
    $off = fetch_all('SELECT start_date, end_date FROM time_off WHERE practitioner_id = ? AND end_date >= ? AND start_date <= ?', [$pid, $from, $to]);
    $appts = fetch_all(
        "SELECT start_at AS s, end_at AS e FROM appointments
          WHERE practitioner_id = ? AND status IN ('pending','confirmed') AND start_at >= ? AND start_at < ?",
        [$pid, $from . ' 00:00:00', (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d') . ' 00:00:00']
    );
    return ['av' => $av, 'off' => $off, 'appts' => $appts];
}

/** @return array<int,array{time:string,start:string}> */
function compute_day_slots(string $date, array $sched, int $duration, ?DateTimeImmutable $at = null): array
{
    $at = $at ?? now();
    foreach ($sched['off'] as $o) {
        if ($date >= $o['start_date'] && $date <= $o['end_date']) {
            return [];
        }
    }
    $weekday  = (int)(new DateTimeImmutable($date))->format('N');
    $earliest = $at->modify('+' . (int)config('booking.min_notice_hours', 2) . ' hours')->format('Y-m-d H:i:s');

    $busy = [];
    foreach ($sched['appts'] as $a) {
        if (str_starts_with($a['s'], $date)) {
            $busy[] = $a;
        }
    }

    $slots = [];
    foreach ($sched['av'][$weekday] ?? [] as [$from, $to]) {
        $t   = new DateTimeImmutable("$date $from:00");
        $end = new DateTimeImmutable("$date $to:00");
        while (($next = $t->modify("+$duration minutes")) <= $end) {
            $s = $t->format('Y-m-d H:i:s');
            $e = $next->format('Y-m-d H:i:s');
            $free = $s >= $earliest;
            if ($free) {
                foreach ($busy as $b) {
                    if ($b['s'] < $e && $b['e'] > $s) {
                        $free = false;
                        break;
                    }
                }
            }
            if ($free) {
                $slots[$s] = ['time' => $t->format('H:i'), 'start' => $s];
            }
            $t = $next;
        }
    }
    ksort($slots);
    return array_values($slots);
}

function practitioner_duration(int $pid): int
{
    return (int)(fetch_value('SELECT slot_minutes FROM practitioners WHERE id = ? AND is_active = 1', [$pid]) ?? 0);
}

function get_day_slots(int $pid, string $date): array
{
    $dur = practitioner_duration($pid);
    if ($dur <= 0 || !date_in_horizon($date)) {
        return [];
    }
    return compute_day_slots($date, load_schedule($pid, $date, $date), $dur);
}

/** Nombre de créneaux libres par jour, pour colorer la bande de dates. */
function get_overview(int $pid, int $days): array
{
    $dur = practitioner_duration($pid);
    if ($dur <= 0) {
        return [];
    }
    $start = now()->setTime(0, 0);
    $to    = $start->modify('+' . ($days - 1) . ' days')->format('Y-m-d');
    $sched = load_schedule($pid, $start->format('Y-m-d'), $to);
    $out = [];
    for ($i = 0; $i < $days; $i++) {
        $d = $start->modify("+$i days")->format('Y-m-d');
        $out[$d] = count(compute_day_slots($d, $sched, $dur));
    }
    return $out;
}

/** Prochain créneau libre dans les 21 jours (ou null). */
function next_free_slot(int $pid): ?string
{
    $dur = practitioner_duration($pid);
    if ($dur <= 0) {
        return null;
    }
    $start = now()->setTime(0, 0);
    $sched = load_schedule($pid, $start->format('Y-m-d'), $start->modify('+20 days')->format('Y-m-d'));
    for ($i = 0; $i < 21; $i++) {
        $d = $start->modify("+$i days")->format('Y-m-d');
        $slots = compute_day_slots($d, $sched, $dur);
        if ($slots) {
            return $slots[0]['start'];
        }
    }
    return null;
}

/* ------------------------------------------------------------------ Horaires hebdomadaires */

function get_weekly_hours(int $pid): array
{
    $out = array_fill(1, 7, []);
    foreach (fetch_all('SELECT weekday, start_time, end_time FROM availabilities WHERE practitioner_id = ? ORDER BY weekday, start_time', [$pid]) as $r) {
        $out[(int)$r['weekday']][] = ['start' => substr($r['start_time'], 0, 5), 'end' => substr($r['end_time'], 0, 5)];
    }
    return $out;
}

/** Valide et enregistre les horaires. @return string|null message d'erreur */
function save_weekly_hours(int $pid, array $week): ?string
{
    $clean = [];
    for ($d = 1; $d <= 7; $d++) {
        $ranges = [];
        foreach ((array)($week[$d] ?? $week[(string)$d] ?? []) as $r) {
            $s = (string)($r['start'] ?? '');
            $e = (string)($r['end'] ?? '');
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $s) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $e)) {
                return 'Heure invalide pour ' . FR_DAYS[$d - 1] . '.';
            }
            if ($s >= $e) {
                return 'Pour ' . FR_DAYS[$d - 1] . ', l\'heure de fin doit être après l\'heure de début.';
            }
            $ranges[] = [$s, $e];
        }
        usort($ranges, fn($a, $b) => strcmp($a[0], $b[0]));
        for ($i = 1; $i < count($ranges); $i++) {
            if ($ranges[$i][0] < $ranges[$i - 1][1]) {
                return 'Les plages de ' . FR_DAYS[$d - 1] . ' se chevauchent.';
            }
        }
        if (count($ranges) > 6) {
            return 'Maximum 6 plages par jour.';
        }
        $clean[$d] = $ranges;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('DELETE FROM availabilities WHERE practitioner_id = ?', [$pid]);
        foreach ($clean as $d => $ranges) {
            foreach ($ranges as [$s, $e]) {
                q('INSERT INTO availabilities (practitioner_id, weekday, start_time, end_time) VALUES (?,?,?,?)', [$pid, $d, $s, $e]);
            }
        }
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }
    return null;
}

/* ------------------------------------------------------------------ Congés */

function get_time_off(int $pid): array
{
    return fetch_all('SELECT id, start_date, end_date, reason FROM time_off WHERE practitioner_id = ? AND end_date >= ? ORDER BY start_date', [$pid, now()->format('Y-m-d')]);
}

function add_time_off(int $pid, string $start, string $end, string $reason): ?string
{
    if (!valid_date($start) || !valid_date($end)) {
        return 'Dates invalides.';
    }
    if ($end < $start) {
        return 'La date de fin doit être après la date de début.';
    }
    if ($end < now()->format('Y-m-d')) {
        return 'Cette période est déjà passée.';
    }
    q('INSERT INTO time_off (practitioner_id, start_date, end_date, reason) VALUES (?,?,?,?)',
        [$pid, $start, $end, mb_substr($reason, 0, 150) ?: null]);
    return null;
}
