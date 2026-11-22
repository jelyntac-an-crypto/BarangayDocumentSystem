<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'resident') {
    http_response_code(403);
    exit('Access denied.');
}

require_once dirname(__DIR__) . '/layout.php';

$notificationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($notificationId === false || $notificationId === null || $notificationId < 1) {
    http_response_code(400);
    exit('Invalid notification ID.');
}

$notification = null;
try {
    $stmt = $pdo->prepare(
        'SELECT title, message, notification_type, is_read, created_at
         FROM notifications
         WHERE id = ? AND user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$notificationId, (string) $_SESSION['user_id']]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        http_response_code(404);
        exit('Notification not found.');
    }

    if ((int) $notification['is_read'] === 0) {
        $stmt = $pdo->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$notificationId, (string) $_SESSION['user_id']]);
    }
} catch (Throwable $error) {
    error_log('Resident notification details error: ' . $error->getMessage());
    http_response_code(500);
    exit('Unable to load this notification. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification - Barangay Document Portal</title>
    <link rel="stylesheet" href="../../css/layout.css">
    <link rel="stylesheet" href="../../css/resident/notifications.css">
</head>
<body class="resident-notification-body">
<?php renderSiteHeader('default', '../../'); ?>
<main class="resident-notification-page">
    <a class="resident-notification-back" href="dashboard.php">&larr; Back to Dashboard</a>
    <article class="resident-notification-card">
        <p class="resident-notification-label">Notification</p>
        <h1><?= e((string) $notification['title']) ?></h1>
        <p class="resident-notification-message"><?= nl2br(e((string) $notification['message'])) ?></p>
        <div class="resident-notification-meta">
            <span><?= e(ucwords(str_replace('_', ' ', (string) $notification['notification_type']))) ?></span>
            <time datetime="<?= e(date(DATE_ATOM, strtotime((string) $notification['created_at']))) ?>">
                <?= e(date('F d, Y · h:i A', strtotime((string) $notification['created_at']))) ?>
            </time>
        </div>
        <div class="resident-notification-actions">
            <a href="my_requests.php">View My Requests</a>
            <a href="dashboard.php">Dashboard</a>
        </div>
    </article>
</main>
<?php renderSiteFooter(); ?>
</body>
</html>