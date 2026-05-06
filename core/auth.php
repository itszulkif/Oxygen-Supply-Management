<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function auth_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ]);
    }
    auth_ensure_user_schema();
}

function auth_ensure_user_schema(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;
    try {
        $pdo = db();
    } catch (Throwable $e) {
        return;
    }
    if (!table_exists($pdo, 'users')) {
        return;
    }
    if (!column_exists($pdo, 'users', 'username')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN username VARCHAR(60) NULL UNIQUE AFTER name');
    }
    auth_backfill_usernames($pdo);
}

function auth_backfill_usernames(PDO $pdo): void
{
    $rows = $pdo->query("SELECT id, email FROM users WHERE username IS NULL OR username = ''")->fetchAll();
    foreach ($rows as $row) {
        $email = (string) ($row['email'] ?? '');
        $local = strstr($email, '@', true);
        if ($local === false || $local === '') {
            $local = 'user';
        }
        $base = strtolower(preg_replace('/[^a-z0-9._-]/i', '', $local)) ?: 'user';
        $candidate = $base;
        $suffix = 0;
        while (true) {
            $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
            $chk->execute([$candidate, (int) $row['id']]);
            if (!$chk->fetch()) {
                break;
            }
            $suffix++;
            $candidate = $base . $suffix;
        }
        $up = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
        $up->execute([$candidate, (int) $row['id']]);
    }
}

function auth_user_row(bool $fresh = false): ?array
{
    static $cached = null;
    if ($fresh) {
        $cached = null;
    }
    if ($cached !== null) {
        return $cached;
    }
    auth_start();
    $id = (int) ($_SESSION['auth_user_id'] ?? 0);
    if ($id <= 0) {
        $cached = null;
        return null;
    }
    $pdo = db();
    $st = $pdo->prepare('SELECT id, name, username, email, role FROM users WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    $cached = $row ?: null;
    return $cached;
}

function auth_user_id(): int
{
    $row = auth_user_row();
    return $row ? (int) $row['id'] : 0;
}

function auth_set_session_from_user(array $user): void
{
    $_SESSION['auth_user_id'] = (int) $user['id'];
}

function auth_clear_session(): void
{
    unset($_SESSION['auth_user_id']);
}

function auth_require_login(): void
{
    if (auth_user_id() > 0) {
        return;
    }
    $target = (string) ($_GET['module'] ?? 'dashboard');
    if (!in_array($target, auth_allowed_modules(), true)) {
        $target = 'dashboard';
    }
    header('Location: ?module=login&return=' . rawurlencode($target) . i18n_lang_query());
    exit;
}

function auth_safe_return_module(string $return): string
{
    $return = trim($return);
    if (!in_array($return, auth_allowed_modules(), true)) {
        return 'dashboard';
    }
    return $return;
}

/** @return list<string> */
function auth_allowed_modules(): array
{
    return [
        'dashboard',
        'suppliers',
        'customers',
        'services',
        'invoices',
        'payments',
        'ledger',
        'reports',
        'settings',
    ];
}

function auth_attempt_login(string $login, string $password): bool
{
    $login = trim($login);
    if ($login === '' || $password === '') {
        return false;
    }
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $st->execute([$login, $login]);
    $user = $st->fetch();
    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        return false;
    }
    auth_set_session_from_user($user);
    return true;
}

function auth_logout(): void
{
    auth_start();
    auth_clear_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

function auth_create_first_admin(string $name, string $username, string $email, string $password): string
{
    $name = trim($name);
    $username = trim($username);
    $email = trim($email);
    if ($name === '' || $username === '' || $email === '' || $password === '') {
        return __('auth.err_all_required');
    }
    if (!auth_validate_username($username)) {
        return __('auth.err_username_rules');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return __('auth.err_email');
    }
    if (strlen($password) < 8) {
        return __('auth.err_password_short');
    }
    $pdo = db();
    if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
        return __('auth.err_admin_exists');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO users (name, username, email, password_hash, role) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([$name, $username, $email, $hash, 'admin']);
    $id = (int) $pdo->lastInsertId();
    auth_set_session_from_user(['id' => $id]);
    return '';
}

function auth_validate_username(string $username): bool
{
    return (bool) preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username);
}

/**
 * @return string Error message, or empty string on success.
 */
function auth_update_profile(int $userId, string $name, string $username, string $email, string $currentPassword, ?string $newPassword): string
{
    $name = trim($name);
    $username = trim($username);
    $email = trim($email);
    if ($name === '' || $username === '' || $email === '') {
        return __('auth.err_name_username_email');
    }
    if (!auth_validate_username($username)) {
        return __('auth.err_username_rules');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return __('auth.err_email');
    }
    if ($currentPassword === '') {
        return __('auth.err_current_password');
    }
    $pdo = db();
    $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row || !password_verify($currentPassword, (string) $row['password_hash'])) {
        return __('auth.err_wrong_password');
    }
    $newPassword = $newPassword !== null ? trim($newPassword) : '';
    if ($newPassword !== '') {
        if (strlen($newPassword) < 8) {
            return __('auth.err_new_password_short');
        }
    }
    try {
        if ($newPassword !== '') {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $up = $pdo->prepare('UPDATE users SET name = ?, username = ?, email = ?, password_hash = ? WHERE id = ?');
            $up->execute([$name, $username, $email, $hash, $userId]);
        } else {
            $up = $pdo->prepare('UPDATE users SET name = ?, username = ?, email = ? WHERE id = ?');
            $up->execute([$name, $username, $email, $userId]);
        }
    } catch (PDOException $e) {
        $info = $e->errorInfo ?? [];
        if (isset($info[1]) && (int) $info[1] === 1062) {
            return __('auth.err_duplicate');
        }
        return __('auth.err_save_profile');
    }
    auth_user_row(true);
    return '';
}
