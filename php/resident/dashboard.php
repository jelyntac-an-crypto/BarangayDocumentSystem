<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';

// Require an authenticated session.
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Allow only resident accounts.
if (!is_string($_SESSION['role']) || $_SESSION['role'] !== 'resident') {
    http_response_code(403);
    exit('Access denied.');
}

require_once dirname(__DIR__) . '/layout.php';

// The session user ID is the resident's users.id_number.
$userId = (string) $_SESSION['user_id'];

$requestCounts = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'ready' => 0
];

$requests = [];
$dashboardError = '';

try {
    // Get request summary for this resident only.
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(status = 'Pending'), 0) AS pending,
            COALESCE(SUM(status = 'Approved'), 0) AS approved,
            COALESCE(SUM(status = 'Ready for Claiming'), 0) AS ready
         FROM document_requests
         WHERE user_id = ?"
    );

    $stmt->execute([$userId]);
    $requestCounts = $stmt->fetch(PDO::FETCH_ASSOC) ?: $requestCounts;

    // Get the resident's latest document requests.
    $stmt = $pdo->prepare(
        "SELECT
            dr.id,
            dt.document_name,
            dr.purpose,
            dr.status,
            dr.created_at,
            dr.updated_at
         FROM document_requests dr
         INNER JOIN document_types dt
            ON dt.id = dr.document_type_id
         WHERE dr.user_id = ?
         ORDER BY dr.created_at DESC, dr.id DESC
         LIMIT 8"
    );

    $stmt->execute([$userId]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log('Resident dashboard error: ' . $error->getMessage());
    $dashboardError = 'Unable to load dashboard information. Please try again later.';
}

// Convert request status into a safe CSS class.
function getRequestStatusClass(string $status): string
{
    return match ($status) {
        'Pending' => 'status-pending',
        'Under Review' => 'status-review',
        'Approved' => 'status-approved',
        'Rejected' => 'status-rejected',
        'Ready for Claiming' => 'status-ready',
        'Claimed' => 'status-claimed',
        default => 'status-default'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Resident Dashboard - Barangay Document Request and Tracking System</title>

    <link rel="stylesheet" href="../../css/layout.css">
    <link rel="stylesheet" href="../../css/resident/dashboard.css">
</head>
<body class="resident-page">

    <?php renderSiteHeader('default', '../../'); ?>

    <main class="resident-dashboard">

        <section class="dashboard-header">
            <div>
                <h1>Resident Dashboard</h1>
                <p>
                    Welcome,
                    <strong><?= e((string) ($_SESSION['username'] ?? 'Resident')) ?></strong>!
                </p>
                <p>View and track your barangay document requests.</p>
<div class="dashboard-actions">
    <a href="new_request.php" class="new-request-button">
        + New Document Request
    </a>
    <a href="my_requests.php" class="view-requests-button">
        View All Requests
    </a>
</div>
            </div>
        </section>

        <?php if ($dashboardError !== ''): ?>
            <p class="dashboard-error" role="alert">
                <?= e($dashboardError) ?>
            </p>
        <?php else: ?>

            <section class="summary-grid" aria-label="Request summary">

                <article class="summary-card">
                    <h2>Total Requests</h2>
                    <p class="summary-number">
                        <?= (int) $requestCounts['total'] ?>
                    </p>
                </article>

                <article class="summary-card">
                    <h2>Pending Requests</h2>
                    <p class="summary-number">
                        <?= (int) $requestCounts['pending'] ?>
                    </p>
                </article>

                <article class="summary-card">
                    <h2>Approved Requests</h2>
                    <p class="summary-number">
                        <?= (int) $requestCounts['approved'] ?>
                    </p>
                </article>

                <article class="summary-card">
                    <h2>Ready for Claiming</h2>
                    <p class="summary-number">
                        <?= (int) $requestCounts['ready'] ?>
                    </p>
                </article>

            </section>

            <section class="dashboard-panel">
                <div class="panel-heading">
                    <div>
                        <h2>My Document Requests</h2>
                        <p>Check the latest status of your applications.</p>
                    </div>
                </div>

                <?php if (empty($requests)): ?>
                    <p class="dashboard-empty">
                        You have not submitted any document requests yet.
                    </p>
                <?php else: ?>

                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Document</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>Date Submitted</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td>#<?= (int) $request['id'] ?></td>

                                        <td>
                                            <?= e((string) $request['document_name']) ?>
                                        </td>

                                        <td>
                                            <?= e((string) $request['purpose']) ?>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= e(
                                                getRequestStatusClass((string) $request['status'])
                                            ) ?>">
                                                <?= e((string) $request['status']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= e(date(
                                                'M d, Y',
                                                strtotime((string) $request['created_at'])
                                            )) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>
            </section>

        <?php endif; ?>

    </main>

    <?php renderSiteFooter(); ?>

</body>
</html>
