<?php
declare(strict_types=1);

// Set safer session-cookie defaults before starting the session.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        exit('Invalid request token. Refresh the page and try again.');
    }
}

function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . app_url('login.php'));
        exit;
    }
}

function require_role(string $role): void {
    require_login();
    if (($_SESSION['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Access denied: you do not have permission to view this page.');
    }
}

function app_url(string $path = ''): string {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $folder = basename($scriptDir);
    if (in_array($folder, ['admin', 'technician'], true)) {
        $scriptDir = dirname($scriptDir);
    }
    $base = rtrim($scriptDir, '/');
    return $base . '/' . ltrim($path, '/');
}
