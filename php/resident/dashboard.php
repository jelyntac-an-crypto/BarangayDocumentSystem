<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';

// Require an authenticated session.
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Allow only the correct account role.
if (!is_string($_SESSION['role']) || $_SESSION['role'] !== 'resident') {
    http_response_code(403);
    exit('Access denied.');
}

require_once dirname(__DIR__) . '/layout.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - Barangay Document Request and Tracking System</title>
    <link rel="stylesheet" href="../../css/layout.css">
</head>
<body>

    <?php renderSiteHeader('default', '../../'); ?>

    <main>
        <h1>Resident Dashboard</h1>
        <p>Welcome, <?= e((string) ($_SESSION['username'] ?? 'User')) ?>!</p>
        <p>You are logged in as a resident.</p>
    </main>

    <?php renderSiteFooter(); ?>

</body>
</html>