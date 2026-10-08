<?php
declare(strict_types=1);

/* ------------------------------------------------------------------ Envoi */

function mime_encode_header(string $s): string
{
    return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

function html_to_text(string $html): string
{
    $t = preg_replace('#<(br|/p|/tr|/h[1-6]|/div)\s*/?>#i', "\n", $html);
    $t = preg_replace('#</t[dh]>#i', ' ', $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
    return trim(preg_replace("/[ \t]+\n|\n{3,}/", "\n\n", $t));
}

/** @return array{0:string,1:string} [en-têtes (sans To/Subject), corps] */
function mime_build(string $html, string $text): array
{
    $cfg = config('mail');
    $b = 'mrdv_' . bin2hex(random_bytes(8));
    $headers = [
        'From: ' . mime_encode_header($cfg['from_name']) . ' <' . $cfg['from_email'] . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $b . '"',
    ];
    $part = fn(string $type, string $c) => "--$b\r\nContent-Type: $type; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($c));
    $body = $part('text/plain', $text) . $part('text/html', $html) . "--$b--\r\n";
    return [implode("\r\n", $headers), $body];
}

function log_mail(string $to, string $subject, string $status, ?string $error = null): void
{
    try {
        q('INSERT INTO email_logs (to_email, subject, status, error) VALUES (?,?,?,?)', [$to, mb_substr($subject, 0, 255), $status, $error ? mb_substr($error, 0, 255) : null]);
    } catch (Throwable $t) {
        error_log('MédiRDV email_logs: ' . $t->getMessage());
    }
}

function send_mail(string $to, string $subject, string $html, ?string $text = null): bool
{
    $text = $text ?? html_to_text($html);
    $driver = config('mail.driver', 'log');

    try {
        if ($driver === 'smtp') {
            smtp_send($to, $subject, $html, $text);
            log_mail($to, $subject, 'sent');
            return true;
        }
        if ($driver === 'mail') {
            [$headers, $body] = mime_build($html, $text);
            $ok = mail($to, mime_encode_header($subject), $body, $headers);
            log_mail($to, $subject, $ok ? 'sent' : 'failed', $ok ? null : 'mail() a retourné false');
            return $ok;
        }
        // Driver "log" : le message est enregistré dans storage/mails/ (ouvrable dans un navigateur)
        $dir = ROOT_PATH . '/storage/mails';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.html';
        file_put_contents($file, "<!-- To: $to\n     Subject: $subject -->\n" . $html);
        log_mail($to, $subject, 'logged');
        return true;
    } catch (Throwable $t) {
        log_mail($to, $subject, 'failed', $t->getMessage());
        error_log('MédiRDV mail: ' . $t->getMessage());
        return false;
    }
}

function smtp_send(string $to, string $subject, string $html, string $text): void
{
    $c = config('mail.smtp');
    $remote = ($c['secure'] === 'ssl' ? 'ssl://' : '') . $c['host'] . ':' . $c['port'];
    $fp = stream_socket_client($remote, $errno, $errstr, 15);
    if (!$fp) {
        throw new RuntimeException("SMTP: connexion impossible ($errstr)");
    }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 515)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $out;
    };
    $cmd = function (string $line, array $ok) use ($fp, $read): string {
        fwrite($fp, $line . "\r\n");
        $r = $read();
        if (!in_array((int)substr($r, 0, 3), $ok, true)) {
            throw new RuntimeException('SMTP: ' . trim($r));
        }
        return $r;
    };

    $r = $read();
    if ((int)substr($r, 0, 3) !== 220) {
        throw new RuntimeException('SMTP: ' . trim($r));
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $cmd("EHLO $host", [250]);
    if ($c['secure'] === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('SMTP: échec STARTTLS');
        }
        $cmd("EHLO $host", [250]);
    }
    if ($c['user'] !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($c['user']), [334]);
        $cmd(base64_encode($c['pass']), [235]);
    }
    $from = config('mail.from_email');
    $cmd("MAIL FROM:<$from>", [250]);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd('DATA', [354]);

    [$headers, $body] = mime_build($html, $text);
    $msg = $headers . "\r\nTo: <$to>\r\nSubject: " . mime_encode_header($subject) . "\r\nDate: " . date('r') . "\r\n\r\n" . $body;
    $msg = preg_replace('/^\./m', '..', $msg);
    fwrite($fp, $msg . "\r\n.\r\n");
    $r = $read();
    if ((int)substr($r, 0, 3) !== 250) {
        throw new RuntimeException('SMTP: ' . trim($r));
    }
    $cmd('QUIT', [221]);
    fclose($fp);
}

/* ------------------------------------------------------------------ Gabarit */

function email_layout(string $title, string $introHtml, array $rows = [], ?string $ctaUrl = null, ?string $ctaLabel = null, string $outro = ''): string
{
    $name = e(config('app.name'));
    $table = '';
    if ($rows) {
        $table = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;border-radius:12px;background:#f3f4fb">';
        foreach ($rows as $label => $value) {
            $table .= '<tr><td style="padding:10px 16px;color:#6b7194;font-size:13px;width:34%">' . e($label) .
                '</td><td style="padding:10px 16px;color:#1b1e3a;font-size:14px;font-weight:600">' . e($value) . '</td></tr>';
        }
        $table .= '</table>';
    }
    $cta = $ctaUrl ? '<p style="margin:26px 0"><a href="' . e($ctaUrl) . '" style="background:#667eea;background-image:linear-gradient(135deg,#667eea,#764ba2);color:#fff;text-decoration:none;padding:13px 26px;border-radius:999px;font-weight:700;font-size:14px;display:inline-block">' . e($ctaLabel) . '</a></p>' : '';

    return '<!doctype html><html lang="fr"><body style="margin:0;background:#e9ebf7;font-family:Segoe UI,Helvetica,Arial,sans-serif">' .
        '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:28px 12px">' .
        '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#fff;border-radius:18px;overflow:hidden">' .
        '<tr><td style="background:#12182e;padding:22px 30px;color:#fff;font-size:20px;font-weight:800;letter-spacing:.3px">' .
        '<span style="color:#5eead4">&#9829;</span> ' . $name . '</td></tr>' .
        '<tr><td style="padding:30px;color:#1b1e3a;font-size:15px;line-height:1.6">' .
        '<h1 style="margin:0 0 14px;font-size:22px;color:#1b1e3a">' . e($title) . '</h1>' . $introHtml . $table . $cta .
        ($outro ? '<p style="color:#6b7194;font-size:13px">' . $outro . '</p>' : '') .
        '</td></tr><tr><td style="padding:16px 30px;background:#f3f4fb;color:#8a8fb0;font-size:12px">Cet email a été envoyé automatiquement par ' . $name . '. Merci de ne pas y répondre.</td></tr>' .
        '</table></td></tr></table></body></html>';
}

function appt_rows(array $a, bool $forDoctor = false): array
{
    $rows = $forDoctor
        ? ['Patient' => $a['patient_first'] . ' ' . $a['patient_last'], 'Téléphone' => $a['patient_phone'] ?: 'Non renseigné']
        : ['Praticien' => 'Dr ' . $a['doc_first'] . ' ' . $a['doc_last'], 'Spécialité' => $a['specialty']];
    $rows['Date'] = fmt_date($a['start_at'], true, true);
    $rows['Heure'] = fmt_time($a['start_at']) . ' - ' . fmt_time($a['end_at']);
    if (!$forDoctor) {
        $rows['Adresse'] = $a['address'] . ', ' . $a['city'];
    }
    if (!empty($a['reason'])) {
        $rows['Motif'] = $a['reason'];
    }
    return $rows;
}

/* ------------------------------------------------------------------ Messages métier */

function mail_booked(array $a): void
{
    $confirmed = $a['status'] === 'confirmed';
    send_mail(
        $a['patient_email'],
        $confirmed ? 'Rendez-vous confirmé - ' . fmt_date($a['start_at']) : 'Demande de rendez-vous reçue',
        email_layout(
            $confirmed ? 'Votre rendez-vous est confirmé' : 'Votre demande est bien reçue',
            '<p>Bonjour ' . e($a['patient_first']) . ',</p><p>' . ($confirmed
                ? 'Votre rendez-vous est réservé. Nous vous enverrons un rappel avant la consultation.'
                : 'Le praticien doit encore confirmer votre rendez-vous. Vous recevrez un email dès que ce sera fait.') . '</p>',
            appt_rows($a), abs_url('patient/appointments.php'), 'Voir mes rendez-vous',
            'Un empêchement ? Vous pouvez annuler depuis votre espace patient.'
        )
    );
    send_mail(
        $a['doc_email'],
        'Nouveau rendez-vous - ' . $a['patient_first'] . ' ' . $a['patient_last'],
        email_layout(
            $confirmed ? 'Nouveau rendez-vous' : 'Nouvelle demande à confirmer',
            '<p>Bonjour Dr ' . e($a['doc_last']) . ',</p><p>' . ($confirmed ? 'Un patient vient de réserver un créneau.' : 'Un patient attend votre confirmation.') . '</p>',
            appt_rows($a, true), abs_url('praticien/appointments.php'), 'Ouvrir mon agenda'
        )
    );
}

function mail_confirmed(array $a): void
{
    send_mail(
        $a['patient_email'],
        'Rendez-vous confirmé - ' . fmt_date($a['start_at']),
        email_layout('Votre rendez-vous est confirmé',
            '<p>Bonjour ' . e($a['patient_first']) . ',</p><p>Dr ' . e($a['doc_last']) . ' a confirmé votre rendez-vous.</p>',
            appt_rows($a), abs_url('patient/appointments.php'), 'Voir mes rendez-vous')
    );
}

function mail_cancelled(array $a, string $by): void
{
    if ($by === 'patient') {
        send_mail(
            $a['doc_email'],
            'Rendez-vous annulé - ' . $a['patient_first'] . ' ' . $a['patient_last'],
            email_layout('Un rendez-vous a été annulé',
                '<p>Bonjour Dr ' . e($a['doc_last']) . ',</p><p>Le patient a annulé ce rendez-vous. Le créneau est de nouveau disponible.</p>',
                appt_rows($a, true), abs_url('praticien/appointments.php'), 'Ouvrir mon agenda')
        );
        return;
    }
    send_mail(
        $a['patient_email'],
        'Votre rendez-vous a été annulé',
        email_layout('Votre rendez-vous a été annulé',
            '<p>Bonjour ' . e($a['patient_first']) . ',</p><p>Dr ' . e($a['doc_last']) . ' a dû annuler ce rendez-vous. Nous sommes désolés pour la gêne occasionnée.</p>',
            appt_rows($a), abs_url('practitioner.php?id=' . (int)$a['practitioner_id']), 'Reprendre rendez-vous')
    );
}

function mail_reminder(array $a): bool
{
    return send_mail(
        $a['patient_email'],
        'Rappel : rendez-vous ' . (new DateTimeImmutable($a['start_at']) < now()->modify('+30 hours') ? 'demain' : 'bientôt') . ' à ' . fmt_time($a['start_at']),
        email_layout('Rappel de votre rendez-vous',
            '<p>Bonjour ' . e($a['patient_first']) . ',</p><p>Petit rappel : vous avez rendez-vous le ' . e(fmt_date($a['start_at'])) . ' à ' . e(fmt_time($a['start_at'])) . '.</p>',
            appt_rows($a), abs_url('patient/appointments.php'), 'Gérer mon rendez-vous',
            'Si vous ne pouvez pas venir, annulez depuis votre espace pour libérer le créneau.')
    );
}

function mail_password_reset(array $u, string $link): void
{
    send_mail(
        $u['email'],
        'Réinitialisation de votre mot de passe',
        email_layout('Réinitialiser votre mot de passe',
            '<p>Bonjour ' . e($u['first_name']) . ',</p><p>Vous avez demandé à changer votre mot de passe. Ce lien est valable 1 heure.</p>',
            [], $link, 'Choisir un nouveau mot de passe',
            'Si vous n\'êtes pas à l\'origine de cette demande, ignorez simplement cet email.')
    );
}
