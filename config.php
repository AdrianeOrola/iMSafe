<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
date_default_timezone_set('Asia/Manila');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
// A browser session represents one identity. Resolve sessions created before
// this rule existed by keeping the most recently recorded role (admin by default).
if (!empty($_SESSION['imsafe_admin']) && !empty($_SESSION['imsafe_user'])) {
    if (($_SESSION['imsafe_auth_role'] ?? 'admin') === 'user') unset($_SESSION['imsafe_admin']);
    else unset($_SESSION['imsafe_user']);
}
function app(): \ImSafe\AppKernel { static $app = null; return $app ??= new \ImSafe\AppKernel(); }
function is_admin(): bool { return !empty($_SESSION['imsafe_admin']); }
function current_local_user(): ?array { return $_SESSION['imsafe_user'] ?? null; }
function csrf_token(): string { if (empty($_SESSION['imsafe_csrf'])) $_SESSION['imsafe_csrf'] = bin2hex(random_bytes(32)); return (string)$_SESSION['imsafe_csrf']; }
function csrf_is_valid(?string $token): bool { return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token); }
function verify_csrf(?string $token): void { if (!csrf_is_valid($token)) { http_response_code(403); exit('This form has expired. Go back, refresh the page, and try again.'); } }
function rate_limit(string $action, int $limit, int $windowSeconds): bool {
    $key = hash('sha256', $action . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $path = __DIR__ . '/storage/rate-' . $key . '.json';
    if (!is_dir(__DIR__ . '/storage')) mkdir(__DIR__ . '/storage', 0775, true);
    $handle = @fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        return false;
    }
    $contents = stream_get_contents($handle);
    $record = is_string($contents) && $contents !== '' ? json_decode($contents, true) : null;
    $now = time();
    if (!is_array($record) || $now - (int)($record['started'] ?? 0) >= $windowSeconds) $record = ['started' => $now, 'count' => 0];
    $record['count'] = (int)$record['count'] + 1;
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, (string)json_encode($record));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $record['count'] <= $limit;
}
function log_app_error(Throwable $error): void { error_log('[iMSafe v2.0] ' . $error->getMessage() . "\n" . $error->getTraceAsString()); }
function require_admin(): void { if (!is_admin()) { header('Location: admin.php'); exit; } }
