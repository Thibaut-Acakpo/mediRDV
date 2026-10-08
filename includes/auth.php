<?php
declare(strict_types=1);

/* ------------------------------------------------------------------ Session utilisateur */

function current_user(): ?array
{
    if (array_key_exists('__user', $GLOBALS)) {
        return $GLOBALS['__user'];
    }
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $user = fetch_one(
            'SELECT u.id, u.role, u.first_name, u.last_name, u.email, u.phone, p.id AS pid
               FROM users u LEFT JOIN practitioners p ON p.user_id = u.id
              WHERE u.id = ?',
            [(int)$_SESSION['uid']]
        );
        if (!$user) {
            unset($_SESSION['uid']);
        }
    }
    return $GLOBALS['__user'] = $user;
}

function refresh_user(): void
{
    unset($GLOBALS['__user']);
}

function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    unset($_SESSION['csrf']);
    refresh_user();
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now()->format('Y-m-d H:i:s'), $id]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
    refresh_user();
}

function dashboard_url(?array $user = null): string
{
    $user = $user ?? current_user();
    if (!$user) {
        return 'login.php';
    }
    return $user['role'] === 'practitioner' ? 'praticien/dashboard.php' : 'patient/dashboard.php';
}

/** Protège une page : redirige vers la connexion ou vers le bon espace. */
function require_auth(?string $role = null): array
{
    $user = current_user();
    if (!$user) {
        $next = ltrim(substr((string)($_SERVER['REQUEST_URI'] ?? ''), strlen(base_path())), '/');
        flash('warning', 'Connectez-vous pour accéder à cette page.');
        redirect('login.php' . (safe_next($next) ? '?next=' . urlencode($next) : ''));
    }
    if ($role !== null && $user['role'] !== $role) {
        flash('warning', 'Cet espace est réservé aux ' . ($role === 'practitioner' ? 'praticiens' : 'patients') . '.');
        redirect(dashboard_url($user));
    }
    return $user;
}

/** Variante pour les endpoints JSON. */
function api_auth(?string $role = null): array
{
    $user = current_user();
    if (!$user) {
        json_error('Votre session a expiré. Reconnectez-vous.', 401);
    }
    if ($role !== null && $user['role'] !== $role) {
        json_error('Action non autorisée pour votre profil.', 403);
    }
    return $user;
}

function api_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Méthode non autorisée.', 405);
    }
    if (!csrf_valid()) {
        json_error('Session expirée. Rechargez la page.', 419);
    }
}

/* ------------------------------------------------------------------ Validation */

function validate_password(string $pwd): ?string
{
    if (mb_strlen($pwd) < 8) {
        return 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if (!preg_match('/[A-Za-z]/', $pwd) || !preg_match('/\d/', $pwd)) {
        return 'Le mot de passe doit contenir au moins une lettre et un chiffre.';
    }
    return null;
}

function validate_phone(string $phone): bool
{
    return $phone === '' || (bool)preg_match('/^\+?[0-9 .\-]{8,20}$/', $phone);
}

/* ------------------------------------------------------------------ Inscription */

/** @return array{ok:bool, errors:array<string,string>, user_id?:int} */
function register_user(array $in): array
{
    $err = [];
    $role  = ($in['role'] ?? '') === 'practitioner' ? 'practitioner' : 'patient';
    $first = trim((string)($in['first_name'] ?? ''));
    $last  = trim((string)($in['last_name'] ?? ''));
    $email = mb_strtolower(trim((string)($in['email'] ?? '')));
    $phone = trim((string)($in['phone'] ?? ''));
    $pwd   = (string)($in['password'] ?? '');

    if ($first === '' || mb_strlen($first) > 60) $err['first_name'] = 'Indiquez votre prénom.';
    if ($last === ''  || mb_strlen($last) > 60)  $err['last_name']  = 'Indiquez votre nom.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $err['email'] = 'Adresse email invalide.';
    if (!validate_phone($phone)) $err['phone'] = 'Numéro de téléphone invalide.';
    if ($e = validate_password($pwd)) $err['password'] = $e;
    if ($pwd !== (string)($in['password_confirm'] ?? '')) $err['password_confirm'] = 'Les mots de passe ne correspondent pas.';

    $specialty = (int)($in['specialty_id'] ?? 0);
    $city = trim((string)($in['city'] ?? ''));
    $address = trim((string)($in['address'] ?? ''));
    $slot = (int)($in['slot_minutes'] ?? 30);
    if ($role === 'practitioner') {
        if (!fetch_value('SELECT id FROM specialties WHERE id = ?', [$specialty])) $err['specialty_id'] = 'Choisissez une spécialité.';
        if ($city === '' || mb_strlen($city) > 80) $err['city'] = 'Indiquez votre ville.';
        if ($address === '' || mb_strlen($address) > 190) $err['address'] = 'Indiquez l\'adresse du cabinet.';
        if (!in_array($slot, [10, 15, 20, 30, 45, 60], true)) $err['slot_minutes'] = 'Durée de consultation invalide.';
    }

    if (!$err && fetch_value('SELECT id FROM users WHERE email = ?', [$email])) {
        $err['email'] = 'Un compte existe déjà avec cet email.';
    }
    if ($err) {
        return ['ok' => false, 'errors' => $err];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('INSERT INTO users (role, first_name, last_name, email, phone, password_hash) VALUES (?,?,?,?,?,?)',
            [$role, $first, $last, $email, $phone ?: null, password_hash($pwd, PASSWORD_DEFAULT)]);
        $uid = (int)$pdo->lastInsertId();

        if ($role === 'practitioner') {
            $price = (int)($in['price_fcfa'] ?? 0);
            q('INSERT INTO practitioners (user_id, specialty_id, city, address, slot_minutes, price_fcfa) VALUES (?,?,?,?,?,?)',
                [$uid, $specialty, $city, $address, $slot, $price > 0 ? $price : null]);
            $pid = (int)$pdo->lastInsertId();
            // Horaires par défaut : lundi-vendredi, matin et après-midi (modifiables ensuite)
            for ($d = 1; $d <= 5; $d++) {
                q('INSERT INTO availabilities (practitioner_id, weekday, start_time, end_time) VALUES (?,?,?,?)', [$pid, $d, '08:00', '12:00']);
                q('INSERT INTO availabilities (practitioner_id, weekday, start_time, end_time) VALUES (?,?,?,?)', [$pid, $d, '14:00', '17:00']);
            }
        }
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }
    return ['ok' => true, 'errors' => [], 'user_id' => $uid];
}

/* ------------------------------------------------------------------ Connexion */

/** @return array{ok:bool, error?:string, user_id?:int} */
function attempt_login(string $email, string $password): array
{
    $email = mb_strtolower(trim($email));
    $since = now()->modify('-15 minutes')->format('Y-m-d H:i:s');
    $fails = (int)fetch_value('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip = ? AND attempted_at > ?', [$email, client_ip(), $since]);
    if ($fails >= 5) {
        return ['ok' => false, 'error' => 'Trop de tentatives. Réessayez dans 15 minutes.'];
    }

    $u = fetch_one('SELECT id, password_hash FROM users WHERE email = ?', [$email]);
    $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringforsaltuQ3b1d7bYf2Zr9K8h0x5uXq8e1y6C';
    $valid = password_verify($password, $hash);

    if (!$u || !$valid) {
        q('INSERT INTO login_attempts (email, ip, attempted_at) VALUES (?,?,?)', [$email, client_ip(), now()->format('Y-m-d H:i:s')]);
        return ['ok' => false, 'error' => 'Email ou mot de passe incorrect.'];
    }
    q('DELETE FROM login_attempts WHERE email = ?', [$email]);
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    return ['ok' => true, 'user_id' => (int)$u['id']];
}

/* ------------------------------------------------------------------ Mot de passe oublié */

function start_password_reset(string $email): void
{
    $email = mb_strtolower(trim($email));
    $u = fetch_one('SELECT id, first_name, email FROM users WHERE email = ?', [$email]);
    if (!$u) {
        return; // même réponse dans tous les cas : on ne révèle pas l'existence du compte
    }
    $token = bin2hex(random_bytes(32));
    q('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?,?)',
        [$u['id'], hash('sha256', $token), now()->modify('+1 hour')->format('Y-m-d H:i:s')]);
    mail_password_reset($u, abs_url('reset.php?token=' . $token));
}

function find_reset(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return fetch_one('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?',
        [hash('sha256', $token), now()->format('Y-m-d H:i:s')]);
}

function complete_password_reset(array $reset, string $newPassword): void
{
    q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($newPassword, PASSWORD_DEFAULT), $reset['user_id']]);
    q('UPDATE password_resets SET used_at = ? WHERE id = ?', [now()->format('Y-m-d H:i:s'), $reset['id']]);
    q('DELETE FROM login_attempts WHERE email = (SELECT email FROM users WHERE id = ?)', [$reset['user_id']]);
}

/** Corps JSON d'une requête API (ou $_POST en repli). */
function json_input(): array
{
    static $data = null;
    if ($data === null) {
        $raw = file_get_contents('php://input');
        $j = $raw !== '' && $raw !== false ? json_decode($raw, true) : null;
        $data = is_array($j) ? $j : $_POST;
    }
    return $data;
}

/* ------------------------------------------------------------------ Profil */

/** @return array<string,string> erreurs par champ (vide si OK) */
function update_account(int $uid, array $in): array
{
    $err = [];
    $first = trim((string)($in['first_name'] ?? ''));
    $last  = trim((string)($in['last_name'] ?? ''));
    $email = mb_strtolower(trim((string)($in['email'] ?? '')));
    $phone = trim((string)($in['phone'] ?? ''));
    if ($first === '' || mb_strlen($first) > 60) $err['first_name'] = 'Indiquez votre prénom.';
    if ($last === ''  || mb_strlen($last) > 60)  $err['last_name']  = 'Indiquez votre nom.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $err['email'] = 'Adresse email invalide.';
    elseif (fetch_value('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $uid])) $err['email'] = 'Cet email est déjà utilisé.';
    if (!validate_phone($phone)) $err['phone'] = 'Numéro de téléphone invalide.';
    if (!$err) {
        q('UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?', [$first, $last, $email, $phone ?: null, $uid]);
        refresh_user();
    }
    return $err;
}

function change_password(int $uid, string $current, string $new, string $confirm): ?string
{
    $hash = (string)fetch_value('SELECT password_hash FROM users WHERE id = ?', [$uid]);
    if (!password_verify($current, $hash)) return 'Mot de passe actuel incorrect.';
    if ($e = validate_password($new)) return $e;
    if ($new !== $confirm) return 'Les nouveaux mots de passe ne correspondent pas.';
    q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
    return null;
}
