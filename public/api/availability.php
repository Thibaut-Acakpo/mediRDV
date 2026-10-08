<?php
require __DIR__ . '/../../includes/bootstrap.php';

api_post();
$user = api_auth('practitioner');
$pid = (int)$user['pid'];
$in = json_input();

switch ($in['action'] ?? '') {
    case 'save_weekly':
        $err = save_weekly_hours($pid, (array)($in['week'] ?? []));
        if ($err) json_error($err);
        json_out(['ok' => true, 'message' => 'Horaires enregistrés.']);

    case 'add_off':
        $err = add_time_off($pid, (string)($in['start'] ?? ''), (string)($in['end'] ?? ''), trim((string)($in['reason'] ?? '')));
        if ($err) json_error($err);
        $rows = array_map(fn($r) => [
            'id' => (int)$r['id'],
            'label' => $r['start_date'] === $r['end_date'] ? fmt_date($r['start_date'], true, true) : 'Du ' . fmt_date($r['start_date'], false, true) . ' au ' . fmt_date($r['end_date'], false, true),
            'reason' => $r['reason'],
        ], get_time_off($pid));
        $affected = (int)fetch_value(
            "SELECT COUNT(*) FROM appointments WHERE practitioner_id = ? AND status IN ('pending','confirmed') AND start_at >= ? AND start_at < ?",
            [$pid, $in['start'] . ' 00:00:00', (new DateTimeImmutable($in['end']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00']
        );
        $msg = 'Période de fermeture ajoutée.' . ($affected ? " Attention : $affected rendez-vous existants sur cette période restent à annuler ou à déplacer." : '');
        json_out(['ok' => true, 'message' => $msg, 'off' => $rows, 'warn' => $affected > 0]);

    case 'delete_off':
        q('DELETE FROM time_off WHERE id = ? AND practitioner_id = ?', [(int)($in['id'] ?? 0), $pid]);
        json_out(['ok' => true, 'message' => 'Période supprimée.']);

    default:
        json_error('Action inconnue.');
}
