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

$requestId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($requestId === false || $requestId === null || $requestId < 1) {
    http_response_code(400);
    exit('Invalid request ID.');
}

$request = null;
$statusHistory = [];
$uploadedFiles = [];
$pageError = '';

try {
    // Get the request only if it belongs to the logged-in resident.
    $stmt = $pdo->prepare(
        "SELECT
            dr.id,
            dr.user_id,
            dt.document_name,
            dr.purpose,
            dr.status,
            dr.reviewed_by,
            dr.reviewed_at,
            dr.rejection_reason,
            dr.claimed_at,
            dr.created_at,
            dr.updated_at,
            reviewer.username AS reviewer_name
         FROM document_requests dr
         INNER JOIN document_types dt
            ON dt.id = dr.document_type_id
         LEFT JOIN users reviewer
            ON reviewer.id_number = dr.reviewed_by
         WHERE dr.id = ?
           AND dr.user_id = ?
         LIMIT 1"
    );

    $stmt->execute([$requestId, $userId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        http_response_code(404);
        exit('Document request not found.');
    }

    // Get the request status history.
    $stmt = $pdo->prepare(
        "SELECT
            rsh.old_status,
            rsh.new_status,
            rsh.remarks,
            rsh.changed_at,
            actor.username AS changed_by_name
         FROM request_status_history rsh
         LEFT JOIN users actor
            ON actor.id_number = rsh.changed_by
         WHERE rsh.request_id = ?
         ORDER BY rsh.changed_at ASC, rsh.id ASC"
    );

    $stmt->execute([$requestId]);
    $statusHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get uploaded requirement information.
    // Private storage paths are never displayed to the resident.
    $stmt = $pdo->prepare(
        "SELECT
            original_filename,
            mime_type,
            file_size,
            uploaded_at
         FROM uploaded_requirements
         WHERE request_id = ?
         ORDER BY uploaded_at ASC, id ASC"
    );

    $stmt->execute([$requestId]);
    $uploadedFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $error) {
    error_log('Resident request details error: ' . $error->getMessage());
    $pageError = 'Unable to load request details. Please try again later.';
}

function getViewStatusClass(string $status): string
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

function formatRequestDate(?string $value, string $format = 'M d, Y h:i A'): string
{
    if ($value === null || $value === '') {
        return 'Not available';
    }

    $timestamp = strtotime($value);

    return $timestamp === false
        ? 'Not available'
        : date($format, $timestamp);
}

function formatFileSize(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    if ($bytes < 1048576) {
        return number_format($bytes / 1024, 2) . ' KB';
    }

    return number_format($bytes / 1048576, 2) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Request Details - Barangay Document Request and Tracking System</title>

    <link rel="stylesheet" href="../../css/layout.css">
    <link rel="stylesheet" href="../../css/resident/view-request.css">
</head>
<body class="view-request-body">

<?php renderSiteHeader('default', '../../'); ?>

<main class="view-request-page">

    <section class="details-heading">
        <h1>Request Details</h1>
        <p>View your document request information and status updates.</p>

        <a href="my_requests.php" class="back-button">
            Back to My Requests
        </a>
    </section>

    <?php if ($pageError !== ''): ?>

        <p class="request-error" role="alert">
            <?= e($pageError) ?>
        </p>

    <?php elseif ($request !== null): ?>

        <section class="details-card">

            <div class="details-title">
                <div>
                    <p class="request-reference">
                        Request #<?= (int) $request['id'] ?>
                    </p>

                    <h2>
                        <?= e((string) $request['document_name']) ?>
                    </h2>
                </div>

                <span class="request-status <?= e(
                    getViewStatusClass((string) $request['status'])
                ) ?>">
                    <?= e((string) $request['status']) ?>
                </span>
            </div>

            <div class="details-grid">

                <div class="detail-item">
                    <span>Purpose</span>
                    <p><?= e((string) $request['purpose']) ?></p>
                </div>

                <div class="detail-item">
                    <span>Date Submitted</span>
                    <p><?= e(formatRequestDate((string) $request['created_at'])) ?></p>
                </div>

                <div class="detail-item">
                    <span>Last Updated</span>
                    <p><?= e(formatRequestDate((string) $request['updated_at'])) ?></p>
                </div>

                <div class="detail-item">
                    <span>Reviewed By</span>
                    <p>
                        <?= e((string) ($request['reviewer_name'] ?? 'Not yet reviewed')) ?>
                    </p>
                </div>

                <div class="detail-item">
                    <span>Review Date</span>
                    <p>
                        <?= e(formatRequestDate(
                            $request['reviewed_at'] !== null
                                ? (string) $request['reviewed_at']
                                : null
                        )) ?>
                    </p>
                </div>

                <?php if ($request['claimed_at'] !== null): ?>
                    <div class="detail-item">
                        <span>Date Claimed</span>
                        <p>
                            <?= e(formatRequestDate((string) $request['claimed_at'])) ?>
                        </p>
                    </div>
                <?php endif; ?>

            </div>

            <?php if (
                $request['status'] === 'Rejected'
                && !empty($request['rejection_reason'])
            ): ?>
                <div class="rejection-notice">
                    <h3>Reason for Rejection</h3>
                    <p><?= e((string) $request['rejection_reason']) ?></p>
                </div>
            <?php endif; ?>

        </section>

        <section class="details-card">
            <h2>Supporting Documents</h2>

            <?php if (empty($uploadedFiles)): ?>

                <p class="empty-message">
                    No supporting documents were uploaded for this request.
                </p>

            <?php else: ?>

                <div class="uploaded-file-list">

                    <?php foreach ($uploadedFiles as $file): ?>
                        <article class="uploaded-file">
                            <div>
                                <strong>
                                    <?= e((string) $file['original_filename']) ?>
                                </strong>

                                <p>
                                    <?= e((string) $file['mime_type']) ?>
                                    ·
                                    <?= e(formatFileSize((int) $file['file_size'])) ?>
                                </p>

                                <small>
                                    Uploaded:
                                    <?= e(formatRequestDate((string) $file['uploaded_at'])) ?>
                                </small>
                            </div>
                        </article>
                    <?php endforeach; ?>

                </div>

            <?php endif; ?>
        </section>

        <section class="details-card">
            <h2>Request Status History</h2>
            <p class="section-description">
                Follow the progress of your document request.
            </p>

            <?php if (empty($statusHistory)): ?>

                <p class="empty-message">
                    No status updates are available yet.
                </p>

            <?php else: ?>

                <div class="status-timeline">

                    <?php foreach ($statusHistory as $history): ?>
                        <article class="timeline-item">

                            <div class="timeline-marker"></div>

                            <div class="timeline-content">

                                <span class="request-status <?= e(
                                    getViewStatusClass((string) $history['new_status'])
                                ) ?>">
                                    <?= e((string) $history['new_status']) ?>
                                </span>

                                <?php if (!empty($history['old_status'])): ?>
                                    <p class="status-transition">
                                        Previous status:
                                        <?= e((string) $history['old_status']) ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($history['remarks'])): ?>
                                    <p class="timeline-remarks">
                                        <?= e((string) $history['remarks']) ?>
                                    </p>
                                <?php endif; ?>

                                <small>
                                    <?= e(formatRequestDate((string) $history['changed_at'])) ?>
                                </small>

                                <?php if (!empty($history['changed_by_name'])): ?>
                                    <small class="changed-by">
                                        Updated by:
                                        <?= e((string) $history['changed_by_name']) ?>
                                    </small>
                                <?php endif; ?>

                            </div>

                        </article>
                    <?php endforeach; ?>

                </div>

            <?php endif; ?>
        </section>

    <?php endif; ?>

</main>

<?php renderSiteFooter(); ?>

</body>
</html>