<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';

// Allow POST requests only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

// Verify CSRF token before ending the session.
$csrfToken = $_POST['csrf_token'] ?? null;

if (!is_string($csrfToken) || !verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit('Invalid request.');
}

// Clear all session data.
$_SESSION = [];

// Expire the session cookie using the existing cookie settings.
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

// Destroy the session.
session_destroy();

// Return to the Home page.
header('Location: ../../index.php');
exit;