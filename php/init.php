<?php
declare(strict_types=1);

// Load private configuration first.
require_once __DIR__ . '/bootstrap.php';

// Set the application timezone to Philippine time.
date_default_timezone_set('Asia/Manila');

// Apply security headers before page output.
require_once __DIR__ . '/header.php';

// Start and manage secure sessions.
require_once __DIR__ . '/session.php';

// Load common security helper functions.
require_once __DIR__ . '/security.php';

// Load national ID encryption function.
require_once __DIR__ . '/encryption.php';

// Connect to the database.
require_once __DIR__ . '/db.php';