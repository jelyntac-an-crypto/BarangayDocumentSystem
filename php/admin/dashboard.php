<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';

// Get the visitor's IP address and browser information.
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

// Require an authenticated session.
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Allow only the correct account role.
if (!is_string($_SESSION['role']) || $_SESSION['role'] !== 'security_admin') {
    // Record unauthorized access by an authenticated non-admin.
    recordActivity(
        $pdo,
         (string) $_SESSION['user_id'],
        'ADMIN_ACCESS_DENIED',
        'A non-admin user attempted to access the admin dashboard.',
        $ipAddress,
        $userAgent
    );

    http_response_code(403);
    exit('Access denied.');
}

// Record successful admin dashboard access.
recordActivity(
    $pdo,
    (string) $_SESSION['user_id'],
    'ADMIN_VIEWED_DASHBOARD',
    'Security administrator opened the admin dashboard.',
    $ipAddress,
    $userAgent
);

require_once dirname(__DIR__) . '/layout.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Security Monitoring Dashboard - Barangay Document Request and Tracking System</title>

    <link rel="stylesheet" href="../../css/layout.css">
    <link rel="stylesheet" href="../../css/admin/dashboard.css">

    <!-- Chart.js library -->
    <script src="../../js/chart.umd.min.js"></script>

    <!-- Dashboard JavaScript -->
    <script src="../../js/admin/dashboard.js" defer></script>
</head>
<body class="admin-dashboard-body">

    <?php renderSiteHeader('default', '../../'); ?>

    <main class="admin-dashboard">

        <section class="dashboard-header">
            <div>
                <h1>Security Monitoring Dashboard</h1>
                <p class="dashboard-subtitle">
                    Monitor account activity, failed log-ins, blocked IP addresses,
                    and security findings.
                </p>
                <p id="dashboard-updated" class="dashboard-updated">
                    Loading dashboard data...
                </p>
            </div>
        </section>

        <p id="dashboard-message"
           class="dashboard-message"
           role="status"
           aria-live="polite"
           hidden></p>

        <!-- Today's Summary -->
        <section class="summary-grid" aria-label="Today's security summary">

            <article class="summary-card">
                <span class="card-label">Successful Log-ins Today</span>
                <strong class="card-value" id="successful-logins">0</strong>
            </article>

            <article class="summary-card">
                <span class="card-label">Failed Log-ins Today</span>
                <strong class="card-value" id="failed-logins">0</strong>
            </article>

            <article class="summary-card">
                <span class="card-label">Accounts Locked Now</span>
                <strong class="card-value" id="locked-accounts">0</strong>
            </article>

            <article class="summary-card">
                <span class="card-label">IPs Blocked Now</span>
                <strong class="card-value" id="blocked-ips">0</strong>
            </article>

            <article class="summary-card">
                <span class="card-label">OTPs Sent Today</span>
                <strong class="card-value" id="otps-sent">0</strong>
            </article>

            <article class="summary-card">
                <span class="card-label">OTP Failures Today</span>
                <strong class="card-value" id="otp-failures">0</strong>
            </article>

            <article class="summary-card">
                <span class="card-label">Alert Emails Sent Today</span>
                <strong class="card-value" id="alert-emails-sent">0</strong>
            </article>

        </section>

        <section class="dashboard-grid">

            <!-- Failed Log-ins Chart -->
            <article class="dashboard-panel">
                <h2>Failed Log-ins Per Hour</h2>
                <p class="dashboard-subtitle">
                    Failed authentication attempts during the last 24 hours.
                </p>

                <div class="chart-container">
                    <canvas id="failed-logins-chart"
                            role="img"
                            aria-label="Bar chart showing failed log-ins per hour">
                    </canvas>
                </div>
            </article>

            <!-- Top IP Addresses -->
            <article class="dashboard-panel">
                <h2>Top 5 IP Addresses</h2>
                <p class="dashboard-subtitle">
                    IP addresses with the most failed authentication attempts
                    during the last 24 hours.
                </p>

                <div id="top-ips-list" class="data-list">
                    <p class="dashboard-empty">Loading IP addresses...</p>
                </div>
            </article>

            <!-- Security Flags -->
            <article class="dashboard-panel full-width">
                <h2>Security Flags</h2>
                <p class="dashboard-subtitle">
                    Findings detected by the security monitoring script.
                </p>

                <div id="security-flags-list" class="data-list">
                    <p class="dashboard-empty">Loading security flags...</p>
                </div>
            </article>

            <!-- Recent Events -->
            <article class="dashboard-panel full-width">
                <h2>Recent Security Events</h2>
                <p class="dashboard-subtitle">
                    Latest activity log records, with a maximum of 50 events.
                </p>

                <!-- Event Filters -->
                <form id="event-filter-form" class="event-filters">

                    <div>
                        <label for="filter-from">From Date</label>
                        <input type="date" id="filter-from" name="from">
                    </div>

                    <div>
                        <label for="filter-to">To Date</label>
                        <input type="date" id="filter-to" name="to">
                    </div>

                    <div>
                        <label for="filter-event-type">Event Type</label>
                        <input type="text"
                               id="filter-event-type"
                               name="event_type"
                               maxlength="100"
                               placeholder="e.g. LOGIN_FAILED">
                    </div>

                    <div>
                        <label for="filter-username">Username</label>
                        <input type="text"
                               id="filter-username"
                               name="username"
                               maxlength="100"
                               placeholder="Enter username">
                    </div>

                    <div>
                        <label for="filter-ip">IP Address</label>
                        <input type="text"
                               id="filter-ip"
                               name="ip"
                               maxlength="45"
                               placeholder="Enter IP address">
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="filter-button">
                            Apply Filters
                        </button>

                        <button type="button"
                                id="reset-filters"
                                class="filter-button secondary">
                            Reset
                        </button>
                    </div>

                </form>

                <!-- Events Table -->
                <div class="events-table-wrap">
                    <table class="events-table">
                        <thead>
                            <tr>
                                <th>Date and Time</th>
                                <th>Username</th>
                                <th>Event Type</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>User ID</th>
                            </tr>
                        </thead>

                        <tbody id="recent-events-body">
                            <tr>
                                <td colspan="6" class="dashboard-empty">
                                    Loading recent events...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </article>

        </section>

    </main>

    <?php renderSiteFooter(); ?>

</body>
</html>
