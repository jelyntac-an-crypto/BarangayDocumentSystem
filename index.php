<?php
declare(strict_types=1);

require_once __DIR__ . '/php/init.php';
require_once __DIR__ . '/php/layout.php';

$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Document Request and Tracking System</title>

    <link rel="stylesheet" href="css/layout.css">
</head>
<body>

    <?php renderSiteHeader('home'); ?>

    <main>
        <h1>Welcome</h1>
        <p>Request and track your barangay documents online.</p>
    </main>

    <?php renderSiteFooter(); ?>

</body>
</html>
