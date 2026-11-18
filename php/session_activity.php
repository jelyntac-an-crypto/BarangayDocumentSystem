<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';

// Accept POST requests only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Require an authenticated session.
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    exit;
}

// Verify the CSRF token.
$csrfToken = $_POST['csrf_token'] ?? null;

if (!is_string($csrfToken) || !verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit;
}

// Update the authenticated user's last activity.
$_SESSION['last_activity'] = time();

// Return a successful response.
http_response_code(200);
header('Content-Type: application/json; charset=UTF-8');

echo json_encode([
    'success' => true
]);
