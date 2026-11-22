<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';

// Require an authenticated resident account.
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'resident') {
    http_response_code(403);
    exit('Access denied.');
}

require_once dirname(__DIR__) . '/layout.php';

$userId = (string) $_SESSION['user_id'];

$allowedStatuses = [
    'Pending',
    'Under Review',
    'Approved',
    'Rejected',
    'Ready for Claiming',
    'Claimed'
];

$selectedStatus = isset($_GET['status']) && is_string($_GET['status'])
    ? $_GET['status']
    : '';

if ($selectedStatus !== '' && !in_array($selectedStatus, $allowedStatuses, true)) {
    $selectedStatus = '';
}

$requests = [];
$pageError = '';

function getMyRequestStatusClass(string $status): string
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

try {
    $sql = "SELECT
                dr.id,
                dt.document_name,
                dr.purpose,
                dr.status,
                dr.created_at,
                dr.updated_at
            FROM document_requests dr
            INNER JOIN document_types dt
                ON dt.id = dr.document_type_id
            WHERE dr.user_id = ?";

    $parameters = [$userId];

    if ($selectedStatus !== '') {
        $sql .= " AND dr.status = ?";
        $parameters[] = $selectedStatus;
    }

    $sql .= " ORDER BY dr.created_at DESC, dr.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parameters);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $error) {
    error_log('Resident request tracking error: ' . $error->getMessage());
    $pageError = 'Unable to load your document requests. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Document Requests</title>

    <link rel="stylesheet" href="../../css/layout.css">
    <link rel="stylesheet" href="../../css/resident/my-requests.css">
</head>
<body class="my-requests-body">

<?php renderSiteHeader('default', '../../'); ?>

<main class="my-requests-page">

    <section class="requests-heading">
        <h1>My Document Requests</h1>
        <p>View and track the status of your barangay document applications.</p>

        <div class="requests-actions">
            <a href="dashboard.php" class="back-button">Back to Dashboard</a>
            <a href="new_request.php" class="new-request-button">+ New Request</a>
        </div>
    </section>

    <?php if ($pageError !== ''): ?>

        <p class="request-error" role="alert">
            <?= e($pageError) ?>
        </p>

    <?php else: ?>

        <section class="requests-panel">

            <div class="requests-filter">
                <form method="GET" action="my_requests.php">
                    <label for="status">Filter by Status</label>

                    <select name="status" id="status">
                        <option value="">All Requests</option>

                        <?php foreach ($allowedStatuses as $status): ?>
                            <option
                                value="<?= e($status) ?>"
                                <?= $selectedStatus === $status ? 'selected' : '' ?>
                            >
                                <?= e($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit">Apply Filter</button>

                    <?php if ($selectedStatus !== ''): ?>
                        <a href="my_requests.php" class="clear-filter">
                            Clear Filter
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <p class="request-count">
                <?= count($requests) ?>
                request(s) found.
            </p>

            <?php if (empty($requests)): ?>

                <div class="requests-empty">
                    <h2>No Document Requests Found</h2>
                    <p>
                        You have no requests under this status.
                        Submit a new request or select another filter.
                    </p>
                    <a href="new_request.php">Submit a Document Request</a>
                </div>

            <?php else: ?>

                <div class="requests-table-wrapper">
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Document</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Date Submitted</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td>
                                        #<?= (int) $request['id'] ?>
                                    </td>

                                    <td>
                                        <?= e((string) $request['document_name']) ?>
                                    </td>

                                    <td>
                                        <?= e((string) $request['purpose']) ?>
                                    </td>

                                    <td>
                                        <span class="request-status <?= e(
                                            getMyRequestStatusClass(
                                                (string) $request['status']
                                            )
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

                                    <td>
                                        <a
                                            class="view-request-button"
                                            href="view_request.php?id=<?= (int) $request['id'] ?>"
                                        >
                                            View Details
                                        </a>
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