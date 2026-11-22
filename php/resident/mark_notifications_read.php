<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

if (
    !isset($_SESSION['user_id'], $_SESSION['role'])
    || !is_string($_SESSION['user_id'])
    || $_SESSION['role'] !== 'resident'
) {
    http_response_code(403);
    exit('Access denied.');
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (!is_string($csrfToken) || !verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit('Invalid request.');
}

$userId = $_SESSION['user_id'];

try {
    if (array_key_exists('notification_id', $_POST)) {
        $notificationId = filter_var(
            $_POST['notification_id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (!is_int($notificationId)) {
            http_response_code(400);
            exit('Invalid notification.');
        }

        // Restrict the update to the signed-in resident's own notification.
        $stmt = $pdo->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$notificationId, $userId]);

        header('Location: notification_details.php?id=' . $notificationId, true, 303);
        exit;
    }

    // Mark all notifications belonging to this resident as read.
    $stmt = $pdo->prepare(
        'UPDATE notifications
         SET is_read = 1
         WHERE user_id = ? AND is_read = 0'
    );
    $stmt->execute([$userId]);
} catch (Throwable $error) {
    error_log('Mark resident notifications read error: ' . $error->getMessage());
    http_response_code(500);
    exit('Unable to update notifications. Please try again.');
}

$returnTo = $_POST['return_to'] ?? 'dashboard.php';
if (!is_string($returnTo)) {
    $returnTo = 'dashboard.php';
}

$allowedPages = ['dashboard.php', 'my_requests.php', 'new_request.php'];
$isRequestDetails = preg_match('/^view_request\.php\?id=[1-9][0-9]*$/D', $returnTo) === 1;
if (!in_array($returnTo, $allowedPages, true) && !$isRequestDetails) {
    $returnTo = 'dashboard.php';
}

header('Location: ' . $returnTo, true, 303);
exit;