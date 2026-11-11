<?php
declare(strict_types=1);

require_once __DIR__ . '/php/init.php';

$isLoggedIn = isset($_SESSION['user_id']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Document Request and Tracking System</title>
</head>
<body>

    <!-- Approved System Name -->
    <p>Barangay Document Request and Tracking System</p>

    <!-- Header Navigation -->
    <header>
        <nav>
            <a href="index.php">Home</a> |
            <a href="php/auth/register.php">Register</a> |
            <a href="php/auth/login.php">Log-in</a>
        </nav>
    </header>

    <main>
        <h1>Welcome</h1>
        <p>Request and track your barangay documents online.</p>
    </main>

    <!-- Footer -->
    <footer>
        <p>&copy; 2026 Barangay Document Request and Tracking System. All rights reserved.</p>
    </footer>

</body>
</html>
