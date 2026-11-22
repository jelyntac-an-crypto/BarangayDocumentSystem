<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';

// Require an authenticated session.
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Allow only staff accounts.
if (!is_string($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    http_response_code(403);
    exit('Access denied.');
}

require_once dirname(__DIR__) . '/layout.php';

$summary = [
    'total' => 0,
    'pending' => 0,
    'under_review' => 0,
    'approved' => 0,
    'rejected' => 0,
    'ready' => 0,
    'claimed' => 0
];

$recentRequests = [];
$loadError = false;

try {
    // Get request totals for each status.
    $summaryQuery = $pdo->query("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(status = 'Pending'), 0) AS pending,
            COALESCE(SUM(status = 'Under Review'), 0) AS under_review,
            COALESCE(SUM(status = 'Approved'), 0) AS approved,
            COALESCE(SUM(status = 'Rejected'), 0) AS rejected,
            COALESCE(SUM(status = 'Ready for Claiming'), 0) AS ready,
            COALESCE(SUM(status = 'Claimed'), 0) AS claimed
        FROM document_requests
    ");

    $summary = $summaryQuery->fetch(PDO::FETCH_ASSOC) ?: $summary;

    // Get the five most recent requests.
    $recentQuery = $pdo->query("
        SELECT
            dr.id,
            dr.status,
            dr.created_at,
            dt.document_name,
            CONCAT_WS(' ',
                NULLIF(rp.first_name, ''),
                NULLIF(rp.middle_name, ''),
                NULLIF(rp.last_name, ''),
                NULLIF(rp.name_extension, '')
            ) AS resident_name
        FROM document_requests dr
        INNER JOIN document_types dt
            ON dt.id = dr.document_type_id
        LEFT JOIN resident_profiles rp
            ON rp.user_id = dr.user_id
        ORDER BY dr.created_at DESC, dr.id DESC
        LIMIT 5
    ");

    $recentRequests = $recentQuery->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $exception) {
    error_log('Staff dashboard error: ' . $exception->getMessage());
    $loadError = true;
}

function dashboardStatusClass(string $status): string
{
    return match ($status) {
        'Pending' => 'status-pending',
        'Under Review' => 'status-review',
        'Approved' => 'status-approved',
        'Rejected' => 'status-rejected',
        'Ready for Claiming' => 'status-ready',
        'Claimed' => 'status-claimed',
        default => ''
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Barangay Document Request and Tracking System</title>
    <link rel="stylesheet" href="../../css/layout.css">
    <link rel="stylesheet" href="../../css/staff/requests.css">
    <link rel="stylesheet" href="../../css/staff/dashboard.css">
</head>
<body class="staff-dashboard-body">

<?php renderSiteHeader('default', '../../'); ?>

<main class="staff-dashboard">

    <header class="dashboard-header">
        <div>
            <h1>Staff Dashboard</h1>
            <p>Welcome, <?= e((string) ($_SESSION['username'] ?? 'User')) ?>!</p>
            <p>Manage resident document requests and monitor their status.</p>
        </div>

        <a href="requests.php" class="staff-requests-button">
            Manage Document Requests
        </a>
    </header>

    <?php if ($loadError): ?>

        <div class="message error-message">
            Unable to load dashboard information. Please try again later.
        </div>

    <?php else: ?>

        <section class="dashboard-summary">
            <h2>Request Summary</h2>

            <div class="summary-grid">
                <article class="summary-card">
                    <span>Total Requests</span>
                    <strong><?= (int) $summary['total'] ?></strong>
                </article>

                <article class="summary-card">
                    <span>Pending</span>
                    <strong><?= (int) $summary['pending'] ?></strong>
                </article>

                <article class="summary-card">
                    <span>Under Review</span>
                    <strong><?= (int) $summary['under_review'] ?></strong>
                </article>

                <article class="summary-card">
                    <span>Approved</span>
                    <strong><?= (int) $summary['approved'] ?></strong>
                </article>

                <article class="summary-card">
                    <span>Rejected</span>
                    <strong><?= (int) $summary['rejected'] ?></strong>
                </article>

                <article class="summary-card">
                    <span>Ready for Claiming</span>
                    <strong><?= (int) $summary['ready'] ?></strong>
                </article>

                <article class="summary-card">
                    <span>Claimed</span>
                    <strong><?= (int) $summary['claimed'] ?></strong>
                </article>
            </div>
        </section>

        <section class="recent-requests">
            <div class="recent-header">
                <div>
                    <h2>Recent Document Requests</h2>
                    <p>Latest five applications submitted by residents.</p>
                </div>

                <a href="requests.php">View All Requests</a>
            </div>

            <div class="table-wrapper">
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Resident</th>
                            <th>Document</th>
                            <th>Status</th>
                            <th>Date Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (empty($recentRequests)): ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                No document requests have been submitted yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentRequests as $request): ?>
                            <tr>
                                <td>#<?= (int) $request['id'] ?></td>

                                <td>
                                    <?= e(trim((string) ($request['resident_name'] ?? '')) ?: 'Resident') ?>
                                </td>

                                <td><?= e((string) $request['document_name']) ?></td>

                                <td>
                                    <span class="status-badge <?= e(dashboardStatusClass((string) $request['status'])) ?>">
                                        <?= e((string) $request['status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e(date('M d, Y', strtotime((string) $request['created_at']))) ?>
                                </td>

                                <td>
                                    <a class="view-button"
                                       href="review_request.php?id=<?= (int) $request['id'] ?>">
                                        Review
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php endif; ?>

</main>

<?php renderSiteFooter(); ?>

</body>
</html>
