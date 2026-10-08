<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('ASSET_VERSION', '1.0.0');

$GLOBALS['__config'] = require ROOT_PATH . '/config/config.php';

function config(string $key, $default = null)
{
    $v = $GLOBALS['__config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($v) || !array_key_exists($part, $v)) {
            return $default;
        }
        $v = $v[$part];
    }
    return $v;
}

date_default_timezone_set(config('app.timezone', 'Africa/Porto-Novo'));
mb_internal_encoding('UTF-8');

if (config('app.env') === 'dev') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name('medirdv_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => is_https(),
    ]);
    session_start();
}

/* ------------------------------------------------------------------ Base de données */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['name'], $c['charset']);
        try {
            $pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            if (config('app.env') === 'dev') {
                exit('<h1>Connexion à la base impossible</h1><p>' . htmlspecialchars($e->getMessage()) .
                    '</p><p>Vérifiez <code>config/config.php</code> et lancez <code>php database/install.php --seed</code>.</p>');
            }
            exit('Service temporairement indisponible.');
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function fetch_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function fetch_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function fetch_value(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/* ------------------------------------------------------------------ URLs & sécurité */

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    static $b = null;
    if ($b !== null) {
        return $b;
    }
    $doc = str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $pub = str_replace('\\', '/', (string)realpath(ROOT_PATH . '/public'));
    $b = ($doc !== '' && stripos($pub, $doc) === 0) ? rtrim(substr($pub, strlen($doc)), '/') : '';
    return $b;
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/')) . '?v=' . ASSET_VERSION;
}

function abs_url(string $path = ''): string
{
    $cfg = (string)config('app.url', '');
    if ($cfg !== '') {
        return rtrim($cfg, '/') . '/' . ltrim($path, '/');
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (is_https() ? 'https' : 'http') . '://' . $host . url($path);
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

function safe_next(?string $next): ?string
{
    if ($next && preg_match('#^[a-z0-9_\-/]+\.php(\?[a-zA-Z0-9_=&%\-]*)?$#', $next) && !str_contains($next, '..')) {
        return $next;
    }
    return null;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

function require_post_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Méthode non autorisée.');
    }
    if (!csrf_valid()) {
        http_response_code(419);
        exit('Session expirée. Rechargez la page et réessayez.');
    }
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/* ------------------------------------------------------------------ Messages flash & formulaires */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function old(string $key, $default = ''): string
{
    // Les formulaires sont ré-affichés dans la même requête en cas d'erreur : $_POST suffit.
    return e($_POST[$key] ?? $default);
}

function keep_old(array $data): void
{
    unset($data['password'], $data['password_confirm'], $data['_csrf']);
    $_SESSION['old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

function post_str(string $key, int $max = 255): string
{
    return mb_substr(trim((string)($_POST[$key] ?? '')), 0, $max);
}

/* ------------------------------------------------------------------ Dates en français */

const FR_DAYS   = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
const FR_MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

function to_dt($v): DateTimeImmutable
{
    return $v instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($v) : new DateTimeImmutable((string)$v);
}

function fmt_date($v, bool $weekday = true, bool $year = false): string
{
    $d = to_dt($v);
    $s = ($weekday ? FR_DAYS[(int)$d->format('N') - 1] . ' ' : '') . $d->format('j') . ' ' . FR_MONTHS[(int)$d->format('n') - 1];
    return $year ? $s . ' ' . $d->format('Y') : $s;
}

function fmt_time($v): string
{
    return to_dt($v)->format('H:i');
}

function fmt_datetime($v): string
{
    return fmt_date($v) . ' à ' . fmt_time($v);
}

function fmt_price(?int $fcfa): string
{
    return $fcfa ? number_format($fcfa, 0, ',', ' ') . ' FCFA' : 'Tarif sur demande';
}

function now(): DateTimeImmutable
{
    return new DateTimeImmutable('now');
}

/* ------------------------------------------------------------------ Statuts */

function status_label(string $s): string
{
    return [
        'pending'   => 'En attente',
        'confirmed' => 'Confirmé',
        'cancelled' => 'Annulé',
        'completed' => 'Terminé',
        'no_show'   => 'Absent',
    ][$s] ?? $s;
}

function status_badge(string $s): string
{
    return '<span class="st st-' . e($s) . '">' . e(status_label($s)) . '</span>';
}

function initials(string $first, string $last): string
{
    return mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
}

/* ------------------------------------------------------------------ Réponses JSON */

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $code = 422): never
{
    json_out(['ok' => false, 'error' => $message], $code);
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/schedule.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/appointments.php';
require_once __DIR__ . '/reminders.php';
require_once __DIR__ . '/views.php';
