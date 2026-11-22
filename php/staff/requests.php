<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';

// Require an authenticated staff account.
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (!is_string($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    http_response_code(403);
    exit('Access denied.');
}

require_once dirname(__DIR__) . '/layout.php';

$allowedStatuses = [
    'Pending',
    'Under Review',
    'Approved',
    'Rejected',
    'Ready for Claiming',
    'Claimed'
];

$selectedStatus = $_GET['status'] ?? 'All';

$searchTerm = $_GET['search'] ?? '';

if (!is_string($searchTerm)) {
    $searchTerm = '';
}

$searchTerm = trim($searchTerm);
$searchTerm = mb_substr($searchTerm, 0, 100);

if (!is_string($selectedStatus) ||
    ($selectedStatus !== 'All' && !in_array($selectedStatus, $allowedStatuses, true))) {
    $selectedStatus = 'All';
}

$summary = [
    'total' => 0,
    'pending' => 0,
    'under_review' => 0,
    'approved' => 0,
    'rejected' => 0
];

$requests = [];
$loadError = false;

try {
    $summaryQuery = $pdo->query("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(status = 'Pending'), 0) AS pending,
            COALESCE(SUM(status = 'Under Review'), 0) AS under_review,
            COALESCE(SUM(status = 'Approved'), 0) AS approved,
            COALESCE(SUM(status = 'Rejected'), 0) AS rejected
        FROM document_requests
    ");

    $summary = $summaryQuery->fetch(PDO::FETCH_ASSOC) ?: $summary;

    $sql = "
        SELECT
            dr.id,
            dr.purpose,
            dr.status,
            dr.created_at,
            dr.user_id,
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
    ";

    $conditions = [];

    if ($selectedStatus !== 'All') {
        $conditions[] = 'dr.status = :status';
    }

    if ($searchTerm !== '') {
        $conditions[] = "(
            CAST(dr.id AS CHAR) LIKE :request_id
            OR CONCAT_WS(' ',
                NULLIF(rp.first_name, ''),
                NULLIF(rp.middle_name, ''),
                NULLIF(rp.last_name, ''),
                NULLIF(rp.name_extension, '')
            ) LIKE :resident_name
            OR dt.document_name LIKE :document_name
        )";
    }

    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= " ORDER BY dr.created_at DESC, dr.id DESC";

    $stmt = $pdo->prepare($sql);

    if ($selectedStatus !== 'All') {
        $stmt->bindValue(':status', $selectedStatus, PDO::PARAM_STR);
    }

    if ($searchTerm !== '') {
        $searchPattern = '%' . $searchTerm . '%';

        $stmt->bindValue(':request_id', $searchPattern, PDO::PARAM_STR);
        $stmt->bindValue(':resident_name', $searchPattern, PDO::PARAM_STR);
        $stmt->bindValue(':document_name', $searchPattern, PDO::PARAM_STR);
    }

    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $exception) {
    error_log('Staff request management error: ' . $exception->getMessage());
    $loadError = true;
}

function staffRequestStatusClass(string $status): string
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

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
    header('Content-Type: application/json; charset=utf-8');

    $response = array_map(
        static function (array $request): array {
            return [
                'id' => (int) $request['id'],
                'resident_name' => trim((string) ($request['resident_name'] ?? '')) ?: 'Resident',
                'document_name' => (string) $request['document_name'],
                'purpose' => (string) $request['purpose'],
                'status' => (string) $request['status'],
                'status_class' => staffRequestStatusClass((string) $request['status']),
                'created_at' => date('M d, Y', strtotime((string) $request['created_at']))
            ];
        },
        $requests
    );

    echo json_encode(
        ['requests' => $response],
        JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests - Barangay Document Request and Tracking System</title>
    <link rel="stylesheet" href="../../css/layout.css">
    <link rel="stylesheet" href="../../css/staff/requests.css">
</head>
<body class="staff-requests-body">

<?php renderSiteHeader('default', '../../'); ?>

<main class="staff-requests">

    <header class="page-header">
        <div>
            <h1>Document Requests</h1>
            <p>Review and manage submitted barangay document applications.</p>
        </div>

        <a href="dashboard.php" class="back-button">Back to Dashboard</a>
    </header>

    <?php if ($loadError): ?>
        <div class="message error-message">
            Unable to load document requests. Please try again later.
        </div>
    <?php else: ?>

        <section class="summary-grid">
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
        </section>

        <section class="requests-panel">
            <form method="GET" action="requests.php" class="request-filter">
                <label for="search">Search Requests</label>
                <input
                    type="search"
                    name="search"
                    id="search"
                    placeholder="Search Request ID, resident, or document..."
                    value="<?= e($searchTerm) ?>"
                    maxlength="100"
                >

                <label for="status">Filter by Status</label>

                <select name="status" id="status">
                    <option value="All" <?= $selectedStatus === 'All' ? 'selected' : '' ?>>
                        All Requests
                    </option>

                    <?php foreach ($allowedStatuses as $status): ?>
                        <option value="<?= e($status) ?>"
                            <?= $selectedStatus === $status ? 'selected' : '' ?>>
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Apply Filter</button>
            </form>

            <div class="table-wrapper">
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Resident</th>
                            <th>Document</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Date Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody id="requestResults">
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                No document requests found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>#<?= (int) $request['id'] ?></td>

                                <td>
                                    <?= e(trim((string) ($request['resident_name'] ?? '')) ?: 'Resident') ?>
                                </td>

                                <td><?= e((string) $request['document_name']) ?></td>

                                <td class="purpose-cell">
                                    <?= e((string) $request['purpose']) ?>
                                </td>

                                <td>
                                    <span class="status-badge <?= e(staffRequestStatusClass((string) $request['status'])) ?>">
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

            <p class="request-count" id="requestCount">
                <?= count($requests) ?> request(s) found.
            </p>
        </section>

    <?php endif; ?>

</main>

<?php renderSiteFooter(); ?>

<script src="../../js/staff/requests.js" defer></script>

</body>
</html>


