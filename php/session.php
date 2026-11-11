<?php
declare(strict_types=1);

// Prevent session configuration after a session has started.
if (session_status() !== PHP_SESSION_NONE) {
    throw new RuntimeException('Session already started.');
}

// Configure secure session cookies
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_samesite', 'Strict');

// Set the five-minute idle timeout.
const SESSION_IDLE_TIMEOUT = 300;

// Configure cookie parameters before starting the session.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();

// Check session inactivity.
$now = time();
$lastActivity = $_SESSION['last_activity'] ?? $now;

if (($now - $lastActivity) > SESSION_IDLE_TIMEOUT) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite']
            ]
        );
    }

    session_destroy();
    session_start();
}

$_SESSION['last_activity'] = time();