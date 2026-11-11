<?php
declare(strict_types=1);

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

$username = trim($_GET['username'] ?? '');

if (
    strlen($username) < 8 ||
    strlen($username) > 19 ||
    preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $username) !== 1
) {
    echo json_encode([
        'success' => false,
        'available' => false,
        'message' => 'Please enter a valid username.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM users WHERE username = :username LIMIT 1'
    );

    $stmt->execute(['username' => $username]);

    $isAvailable = $stmt->fetchColumn() === false;

    echo json_encode([
        'success' => true,
        'available' => $isAvailable,
        'message' => $isAvailable
            ? 'Username is available.'
            : 'Username is already taken.'
    ]);
} catch (Throwable $e) {
    error_log('Username availability check failed: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to check username right now. Please try again.'
    ]);
}
