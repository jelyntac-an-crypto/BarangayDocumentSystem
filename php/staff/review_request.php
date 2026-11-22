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

$requestId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedId = $_POST['request_id'] ?? '';

    if (is_string($postedId) && ctype_digit($postedId)) {
        $requestId = (int) $postedId;
    }
}

if (!$requestId || $requestId < 1) {
    http_response_code(400);
    exit('Invalid request ID.');
}

$allowedTransitions = [
    'Pending' => ['Under Review'],
    'Under Review' => ['Approved', 'Rejected'],
    'Approved' => ['Ready for Claiming'],
    'Ready for Claiming' => ['Claimed'],
    'Rejected' => [],
    'Claimed' => []
];

$successMessage = '';
$errorMessage = '';

function reviewStatusClass(string $status): string
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

function reviewFormatDate(?string $date): string
{
    if (!$date) {
        return 'Not available';
    }

    $timestamp = strtotime($date);

    return $timestamp !== false
        ? date('M d, Y h:i A', $timestamp)
        : 'Not available';
}

function reviewFormatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }

    return number_format($bytes / 1024, 2) . ' KB';
}

// Process a status update.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $newStatus = $_POST['new_status'] ?? '';
    $rejectionReason = trim((string) ($_POST['rejection_reason'] ?? ''));
    $remarks = trim((string) ($_POST['remarks'] ?? ''));

    if (!verifyCsrfToken(
        is_string($submittedToken) ? $submittedToken : null
    )) {
        $errorMessage = 'Invalid security token. Please refresh the page and try again.';
    } elseif (!is_string($newStatus)) {
        $errorMessage = 'Invalid status selected.';
    } elseif (strlen($rejectionReason) > 500 || strlen($remarks) > 500) {
        $errorMessage = 'Rejection reason and remarks must not exceed 500 characters.';
    } elseif ($newStatus === 'Rejected' && $rejectionReason === '') {
        $errorMessage = 'Please provide a reason for rejecting this request.';
    } else {
        try {
            $pdo->beginTransaction();

            // Lock the request while processing the update.
            $stmt = $pdo->prepare("
                SELECT
                    dr.id,
                    dr.user_id,
                    dr.status,
                    dt.document_name
                FROM document_requests dr
                INNER JOIN document_types dt
                    ON dt.id = dr.document_type_id
                WHERE dr.id = :request_id
                FOR UPDATE
            ");

            $stmt->execute([
                ':request_id' => $requestId
            ]);

            $currentRequest = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentRequest) {
                throw new RuntimeException('Request not found.');
            }

            $oldStatus = (string) $currentRequest['status'];

            $validNextStatuses = $allowedTransitions[$oldStatus] ?? [];

            if (!in_array($newStatus, $validNextStatuses, true)) {
                throw new DomainException(
                    'This status update is not allowed. Please refresh the page.'
                );
            }

            $finalRejectionReason = $newStatus === 'Rejected'
                ? $rejectionReason
                : null;

            // Update the request.
            $update = $pdo->prepare("
                UPDATE document_requests
                SET
                    status = :new_status,
                    reviewed_by = :reviewed_by,
                    reviewed_at = NOW(),
                    rejection_reason = :rejection_reason,
                    claimed_at = CASE
                        WHEN :claimed_status = 'Claimed' THEN NOW()
                        ELSE claimed_at
                    END,
                    updated_at = NOW()
                WHERE id = :request_id
            ");

            $update->execute([
                ':new_status' => $newStatus,
                ':reviewed_by' => $_SESSION['user_id'],
                ':rejection_reason' => $finalRejectionReason,
                ':claimed_status' => $newStatus,
                ':request_id' => $requestId
            ]);

            // Record the status change in the history.
            $historyRemarks = $newStatus === 'Rejected'
                ? $rejectionReason
                : $remarks;

            $history = $pdo->prepare("
                INSERT INTO request_status_history
                (
                    request_id,
                    changed_by,
                    old_status,
                    new_status,
                    remarks
                )
                VALUES
                (
                    :request_id,
                    :changed_by,
                    :old_status,
                    :new_status,
                    :remarks
                )
            ");

            $history->execute([
                ':request_id' => $requestId,
                ':changed_by' => $_SESSION['user_id'],
                ':old_status' => $oldStatus,
                ':new_status' => $newStatus,
                ':remarks' => $historyRemarks !== ''
                    ? $historyRemarks
                    : null
            ]);

            // Notify the resident about the status change.
            $documentName = (string) $currentRequest['document_name'];

            $notificationTitle = 'Document Request Update';

            $notificationMessage = 'Your request #' . $requestId
                . ' for ' . $documentName
                . ' is now ' . $newStatus . '.';

            if ($newStatus === 'Rejected') {
                $notificationMessage .= ' Reason: ' . $rejectionReason;
            }

            $notification = $pdo->prepare("
                INSERT INTO notifications
                (
                    user_id,
                    title,
                    message,
                    notification_type,
                    sent_at
                )
                VALUES
                (
                    :user_id,
                    :title,
                    :message,
                    :notification_type,
                    NOW()
                )
            ");

            $notification->execute([
                ':user_id' => $currentRequest['user_id'],
                ':title' => $notificationTitle,
                ':message' => $notificationMessage,
                ':notification_type' => 'request_status'
            ]);

            $pdo->commit();

            $successMessage = 'Request status successfully updated. The resident has been notified.';

        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errorMessage = $exception->getMessage();

        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Staff review request error: ' . $exception->getMessage()
            );

            $errorMessage = 'Unable to update the request. Please try again later.';
        }
    }
}

// Load request details.
$request = null;
$requirements = [];
$statusHistory = [];
$loadError = false;

try {
    $stmt = $pdo->prepare("
        SELECT
            dr.id,
            dr.user_id,
            dr.purpose,
            dr.status,
            dr.reviewed_by,
            dr.reviewed_at,
            dr.rejection_reason,
            dr.claimed_at,
            dr.created_at,
            dr.updated_at,
            dt.document_name,
            dt.description AS document_description,
            u.username AS resident_username,
            u.email AS resident_email,
            CONCAT_WS(' ',
                NULLIF(rp.first_name, ''),
                NULLIF(rp.middle_name, ''),
                NULLIF(rp.last_name, ''),
                NULLIF(rp.name_extension, '')
            ) AS resident_name,
            rp.contact_number,
            rp.purok_street,
            rp.barangay,
            rp.municipality_city,
            rp.province
        FROM document_requests dr
        INNER JOIN document_types dt
            ON dt.id = dr.document_type_id
        INNER JOIN users u
            ON u.id_number = dr.user_id
        LEFT JOIN resident_profiles rp
            ON rp.user_id = dr.user_id
        WHERE dr.id = :request_id
        LIMIT 1
    ");

    $stmt->execute([
        ':request_id' => $requestId
    ]);

    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request) {
        // Load uploaded file information only.
        // Never expose the private storage path.
        $fileStmt = $pdo->prepare("
            SELECT
                original_filename,
                mime_type,
                file_size,
                uploaded_at
            FROM uploaded_requirements
            WHERE request_id = :request_id
            ORDER BY uploaded_at ASC
        ");

        $fileStmt->execute([
            ':request_id' => $requestId
        ]);

        $requirements = $fileStmt->fetchAll(PDO::FETCH_ASSOC);

        // Load the complete status history.
        $historyStmt = $pdo->prepare("
            SELECT
                h.old_status,
                h.new_status,
                h.remarks,
                h.changed_at,
                u.username AS changed_by_username
            FROM request_status_history h
            LEFT JOIN users u
                ON u.id_number = h.changed_by
            WHERE h.request_id = :request_id
            ORDER BY h.changed_at ASC, h.id ASC
        ");

        $historyStmt->execute([
            ':request_id' => $requestId
        ]);

        $statusHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Throwable $exception) {
    error_log(
        'Staff review page loading error: ' . $exception->getMessage()
    );

    $loadError = true;
}

$nextStatuses = [];

if ($request) {
    $nextStatuses = $allowedTransitions[(string) $request['status']] ?? [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Review Request - Barangay Document Request and Tracking System</title>

    <link rel="stylesheet" href="../../css/layout.css">
    <link rel="stylesheet" href="../../css/staff/requests.css">
    <link rel="stylesheet" href="../../css/staff/review-request.css">
</head>
<body class="staff-review-body">

<?php renderSiteHeader('default', '../../'); ?>

<main class="review-page">

    <header class="review-header">
        <div>
            <h1>Review Document Request</h1>
            <p>Review the resident's application and update its status.</p>
        </div>

        <a href="requests.php" class="back-button">
            Back to Requests
        </a>
    </header>

    <?php if ($successMessage !== ''): ?>
        <div class="message success-message">
            <?= e($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="message error-message">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($loadError): ?>

        <div class="message error-message">
            Unable to load request details. Please try again later.
        </div>

    <?php elseif (!$request): ?>

        <div class="message error-message">
            The requested document application was not found.
        </div>

    <?php else: ?>

        <section class="review-card">
            <div class="section-heading">
                <h2>Request Information</h2>
                <span class="request-number">
                    Request #<?= (int) $request['id'] ?>
                </span>
            </div>

            <div class="review-details-grid">

                <div class="detail-item">
                    <span>Document Type</span>
                    <strong><?= e((string) $request['document_name']) ?></strong>
                </div>

                <div class="detail-item">
                    <span>Current Status</span>
                    <strong>
                        <span class="status-badge <?= e(reviewStatusClass((string) $request['status'])) ?>">
                            <?= e((string) $request['status']) ?>
                        </span>
                    </strong>
                </div>

                <div class="detail-item">
                    <span>Date Submitted</span>
                    <strong><?= e(reviewFormatDate((string) $request['created_at'])) ?></strong>
                </div>

                <div class="detail-item">
                    <span>Last Updated</span>
                    <strong><?= e(reviewFormatDate((string) $request['updated_at'])) ?></strong>
                </div>

                <div class="detail-item full-width">
                    <span>Purpose</span>
                    <p><?= nl2br(e((string) $request['purpose'])) ?></p>
                </div>

                <?php if (!empty($request['rejection_reason'])): ?>
                    <div class="detail-item full-width rejection-details">
                        <span>Rejection Reason</span>
                        <p><?= nl2br(e((string) $request['rejection_reason'])) ?></p>
                    </div>
                <?php endif; ?>

            </div>
        </section>

        <section class="review-card">
            <h2>Resident Information</h2>

            <div class="review-details-grid">

                <div class="detail-item">
                    <span>Full Name</span>
                    <strong>
                        <?= e(trim((string) ($request['resident_name'] ?? '')) ?: 'Not provided') ?>
                    </strong>
                </div>

                <div class="detail-item">
                    <span>ID Number</span>
                    <strong><?= e((string) $request['user_id']) ?></strong>
                </div>

                <div class="detail-item">
                    <span>Username</span>
                    <strong><?= e((string) $request['resident_username']) ?></strong>
                </div>

                <div class="detail-item">
                    <span>Email</span>
                    <strong><?= e((string) $request['resident_email']) ?></strong>
                </div>

                <div class="detail-item">
                    <span>Contact Number</span>
                    <strong><?= e((string) ($request['contact_number'] ?? 'Not provided')) ?></strong>
                </div>

                <div class="detail-item">
                    <span>Address</span>
                    <strong>
                        <?= e(implode(', ', array_filter([
                            $request['purok_street'] ?? '',
                            $request['barangay'] ?? '',
                            $request['municipality_city'] ?? '',
                            $request['province'] ?? ''
                        ], static fn($value) => trim((string) $value) !== ''))) ?: 'Not provided' ?>
                    </strong>
                </div>

            </div>
        </section>

        <section class="review-card">
            <h2>Supporting Documents</h2>

            <?php if (empty($requirements)): ?>

                <p class="empty-message">
                    No supporting documents were uploaded.
                </p>

            <?php else: ?>

                <div class="table-wrapper">
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>File Type</th>
                                <th>File Size</th>
                                <th>Upload Date</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($requirements as $file): ?>
                                <tr>
                                    <td><?= e((string) $file['original_filename']) ?></td>
                                    <td><?= e((string) $file['mime_type']) ?></td>
                                    <td><?= e(reviewFormatFileSize((int) $file['file_size'])) ?></td>
                                    <td><?= e(reviewFormatDate((string) $file['uploaded_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </section>

        <section class="review-card">
            <h2>Update Request Status</h2>

            <?php if (empty($nextStatuses)): ?>

                <p class="empty-message">
                    This request has reached its final status. No further status updates are available.
                </p>

            <?php else: ?>

                <form method="POST" action="review_request.php?id=<?= (int) $request['id'] ?>" class="status-update-form">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(generateCsrfToken()) ?>"
                    >

                    <input
                        type="hidden"
                        name="request_id"
                        value="<?= (int) $request['id'] ?>"
                    >

                    <div class="form-group">
                        <label for="new_status">New Status</label>

                        <select name="new_status" id="new_status" required>
                            <option value="">Select new status</option>

                            <?php foreach ($nextStatuses as $nextStatus): ?>
                                <option value="<?= e($nextStatus) ?>">
                                    <?= e($nextStatus) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="rejection-group" hidden>
                        <label for="rejection_reason">
                            Rejection Reason
                            <span>(Required only when rejecting)</span>
                        </label>

                        <textarea
                            name="rejection_reason"
                            id="rejection_reason"
                            maxlength="500"
                            rows="3"
                            placeholder="Explain why the request is rejected."
                        ></textarea>
                    </div>

                    <div class="form-group">
                        <label for="remarks">Additional Remarks (Optional)</label>

                        <textarea
                            name="remarks"
                            id="remarks"
                            maxlength="500"
                            rows="3"
                            placeholder="Enter additional notes for the status history."
                        ></textarea>
                    </div>

                    <button type="submit" class="update-status-button">
                        Update Status
                    </button>

                </form>

            <?php endif; ?>
        </section>

        <section class="review-card">
            <h2>Request Status History</h2>

            <?php if (empty($statusHistory)): ?>

                <p class="empty-message">
                    No status history available.
                </p>

            <?php else: ?>

                <div class="history-list">

                    <?php foreach ($statusHistory as $history): ?>

                        <article class="history-item">

                            <div class="history-status">
                                <?php if ($history['old_status'] !== null): ?>
                                    <strong><?= e((string) $history['old_status']) ?></strong>
                                    <span>→</span>
                                <?php endif; ?>

                                <strong><?= e((string) $history['new_status']) ?></strong>
                            </div>

                            <p>
                                Updated by:
                                <?= e((string) ($history['changed_by_username'] ?? 'System')) ?>
                            </p>

                            <?php if (!empty($history['remarks'])): ?>
                                <p>
                                    Remarks:
                                    <?= e((string) $history['remarks']) ?>
                                </p>
                            <?php endif; ?>

                            <time>
                                <?= e(reviewFormatDate((string) $history['changed_at'])) ?>
                            </time>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>
        </section>

    <?php endif; ?>

</main>

<script src="../../js/staff/review_request.js" defer></script>

<?php renderSiteFooter(); ?>

</body>
</html>
