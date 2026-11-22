<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';

// Require an authenticated resident.
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (!is_string($_SESSION['role']) || $_SESSION['role'] !== 'resident') {
    http_response_code(403);
    exit('Access denied.');
}

require_once dirname(__DIR__) . '/layout.php';

$userId = (string) $_SESSION['user_id'];

$errors = [];
$documentTypes = [];
$purpose = '';
$selectedDocumentType = '';
$successMessage = $_SESSION['request_success'] ?? '';
unset($_SESSION['request_success']);

$maxFiles = 3;
$maxFileSize = 5 * 1024 * 1024;

$allowedTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png'
];

try {
    $stmt = $pdo->query(
        "SELECT id, document_name, description
         FROM document_types
         WHERE is_active = 1
         ORDER BY document_name ASC"
    );

    $documentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Loading document types failed: ' . $e->getMessage());
    $errors[] = 'Unable to load document types. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purpose = trim((string) ($_POST['purpose'] ?? ''));
    $selectedDocumentType = trim(
        (string) ($_POST['document_type_id'] ?? '')
    );

    // Verify CSRF token.
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid or expired form submission. Please try again.';
    }

    // Validate document type.
    if (
        $selectedDocumentType === '' ||
        !ctype_digit($selectedDocumentType) ||
        (int) $selectedDocumentType < 1
    ) {
        $errors[] = 'Please select a valid document type.';
    }

    // Validate purpose.
    $purposeLength = function_exists('mb_strlen')
        ? mb_strlen($purpose, 'UTF-8')
        : strlen($purpose);

    if ($purpose === '') {
        $errors[] = 'Please enter the purpose of your request.';
    } elseif ($purposeLength > 500) {
        $errors[] = 'Purpose must not exceed 500 characters.';
    }

    // Confirm the selected document type is still active.
    if (
        $selectedDocumentType !== '' &&
        ctype_digit($selectedDocumentType) &&
        (int) $selectedDocumentType > 0
    ) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id
                 FROM document_types
                 WHERE id = ? AND is_active = 1
                 LIMIT 1"
            );
            $stmt->execute([(int) $selectedDocumentType]);

            if (!$stmt->fetchColumn()) {
                $errors[] = 'The selected document type is unavailable.';
            }
        } catch (Throwable $e) {
            error_log('Document type validation failed: ' . $e->getMessage());
            $errors[] = 'Unable to validate the selected document type.';
        }
    }

    // Validate uploaded files before moving anything.
    $validatedFiles = [];
    $fileInput = $_FILES['supporting_files'] ?? null;

    if ($fileInput !== null) {
        if (
            !is_array($fileInput) ||
            !isset(
                $fileInput['name'],
                $fileInput['tmp_name'],
                $fileInput['error'],
                $fileInput['size']
            ) ||
            !is_array($fileInput['name'])
        ) {
            $errors[] = 'Invalid file upload data.';
        } else {
            $fileCount = count($fileInput['name']);

            if ($fileCount > $maxFiles) {
                $errors[] = 'You can upload a maximum of 3 files.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);

                for ($i = 0; $i < $fileCount; $i++) {
                    $uploadError = $fileInput['error'][$i] ?? UPLOAD_ERR_NO_FILE;

                    if ($uploadError === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    if ($uploadError !== UPLOAD_ERR_OK) {
                        $errors[] = 'One of your files could not be uploaded.';
                        continue;
                    }

                    $tmpName = $fileInput['tmp_name'][$i] ?? '';
                    $originalName = (string) ($fileInput['name'][$i] ?? '');
                    $fileSize = (int) ($fileInput['size'][$i] ?? 0);

                    if (!is_uploaded_file($tmpName)) {
                        $errors[] = 'An invalid uploaded file was detected.';
                        continue;
                    }

                    if ($fileSize < 1 || $fileSize > $maxFileSize) {
                        $errors[] = 'Each file must be between 1 byte and 5 MB.';
                        continue;
                    }

                    $safeOriginalName = basename(
                        str_replace('\\', '/', $originalName)
                    );

                    $extension = strtolower(
                        pathinfo($safeOriginalName, PATHINFO_EXTENSION)
                    );

                    if (!isset($allowedTypes[$extension])) {
                        $errors[] = 'Only PDF, JPG, JPEG, and PNG files are allowed.';
                        continue;
                    }

                    $mimeType = $finfo->file($tmpName);

                    if (
                        $mimeType === false ||
                        $mimeType !== $allowedTypes[$extension]
                    ) {
                        $errors[] = 'A file does not match its declared file type.';
                        continue;
                    }

                    $safeOriginalName = preg_replace(
                        '/[\x00-\x1F\x7F]/',
                        '',
                        $safeOriginalName
                    ) ?? 'uploaded-file';

                    if (function_exists('mb_strcut')) {
                        $safeOriginalName = mb_strcut(
                            $safeOriginalName,
                            0,
                            255,
                            'UTF-8'
                        );
                    } else {
                        $safeOriginalName = substr($safeOriginalName, 0, 255);
                    }

                    $validatedFiles[] = [
                        'tmp_name' => $tmpName,
                        'original_name' => $safeOriginalName,
                        'extension' => $extension,
                        'mime_type' => $mimeType,
                        'file_size' => $fileSize
                    ];
                }
            }
        }
    }

    // Save request, files, history, and notification.
    if (empty($errors)) {
        $privateUploadRoot = dirname(__DIR__, 4)
            . DIRECTORY_SEPARATOR . 'private'
            . DIRECTORY_SEPARATOR . 'BarangayDocumentSystem'
            . DIRECTORY_SEPARATOR . 'uploads';

        $requestDirectory = null;
        $movedFiles = [];
        $transactionStarted = false;

        try {
            if (!is_dir($privateUploadRoot)) {
                throw new RuntimeException('Private upload directory is missing.');
            }

            $realUploadRoot = realpath($privateUploadRoot);
            $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');

            if ($realUploadRoot === false) {
                throw new RuntimeException('Private upload directory is inaccessible.');
            }

            if (
                $documentRoot !== false &&
                str_starts_with(
                    strtolower($realUploadRoot . DIRECTORY_SEPARATOR),
                    strtolower(rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
                )
            ) {
                throw new RuntimeException('Upload directory must be outside the web root.');
            }

            $pdo->beginTransaction();
            $transactionStarted = true;

            // Create the document request.
            $stmt = $pdo->prepare(
                "INSERT INTO document_requests
                    (user_id, document_type_id, purpose)
                 VALUES (?, ?, ?)"
            );

            $stmt->execute([
                $userId,
                (int) $selectedDocumentType,
                $purpose
            ]);

            $requestId = (int) $pdo->lastInsertId();

            // Create a private folder for this request.
            $requestDirectory = $realUploadRoot
                . DIRECTORY_SEPARATOR . 'request_' . $requestId;

            if (
                !mkdir($requestDirectory, 0700) &&
                !is_dir($requestDirectory)
            ) {
                throw new RuntimeException('Unable to create request upload directory.');
            }

            // Move and record validated attachments.
            foreach ($validatedFiles as $file) {
                $storedFilename = bin2hex(random_bytes(16))
                    . '.' . $file['extension'];

                $destination = $requestDirectory
                    . DIRECTORY_SEPARATOR . $storedFilename;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    throw new RuntimeException('Unable to store an uploaded file.');
                }

                $movedFiles[] = $destination;

                $stmt = $pdo->prepare(
                    "INSERT INTO uploaded_requirements
                        (
                            request_id,
                            original_filename,
                            stored_filename,
                            stored_path,
                            mime_type,
                            file_size
                        )
                     VALUES (?, ?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $requestId,
                    $file['original_name'],
                    $storedFilename,
                    $destination,
                    $file['mime_type'],
                    $file['file_size']
                ]);
            }

            // Record the initial request status.
            $stmt = $pdo->prepare(
                "INSERT INTO request_status_history
                    (request_id, changed_by, old_status, new_status, remarks)
                 VALUES (?, ?, NULL, 'Pending', ?)"
            );

            $stmt->execute([
                $requestId,
                $userId,
                'Request submitted by resident.'
            ]);

            // Notify the resident.
            $stmt = $pdo->prepare(
                "INSERT INTO notifications
                    (user_id, title, message, notification_type)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->execute([
                $userId,
                'Document Request Submitted',
                'Your document request #' . $requestId . ' has been submitted successfully and is pending review.',
                'request'
            ]);

            $pdo->commit();
            $transactionStarted = false;

            $_SESSION['request_success'] =
                'Your document request was submitted successfully.';

            header('Location: new_request.php');
            exit;

        } catch (Throwable $e) {
            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Remove files if the database transaction failed.
            foreach ($movedFiles as $movedFile) {
                if (is_file($movedFile)) {
                    unlink($movedFile);
                }
            }

            if (
                $requestDirectory !== null &&
                is_dir($requestDirectory)
            ) {
                rmdir($requestDirectory);
            }

            error_log('Document request submission failed: ' . $e->getMessage());

            $errors[] =
                'Unable to submit your request. Please check your information and try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Document Request</title>
    <link rel="stylesheet" href="../../css/layout.css">
    <link rel="stylesheet" href="../../css/resident/new-request.css">
</head>
<body class="new-request-body">

<?php renderSiteHeader('default', '../../'); ?>

<main class="new-request-page">

    <section class="request-heading">
        <h1>New Document Request</h1>
        <p>Complete the form below to request a barangay document.</p>
        <a href="dashboard.php" class="back-link">Back to Dashboard</a>
    </section>

    <?php if ($successMessage !== ''): ?>
        <div class="request-success" role="status">
            <?= e($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="request-errors" role="alert">
            <strong>Please correct the following:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form
        class="request-form"
        method="POST"
        action="new_request.php"
        enctype="multipart/form-data"
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(generateCsrfToken()) ?>"
        >

        <div class="form-group">
            <label for="document_type_id">Document Type</label>

            <select
                id="document_type_id"
                name="document_type_id"
                required
            >
                <option value="">Select a document</option>

                <?php foreach ($documentTypes as $type): ?>
                    <option
                        value="<?= (int) $type['id'] ?>"
                        <?= $selectedDocumentType === (string) $type['id'] ? 'selected' : '' ?>
                    >
                        <?= e($type['document_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="purpose">Purpose of Request</label>

            <textarea
                id="purpose"
                name="purpose"
                rows="5"
                maxlength="500"
                placeholder="Explain why you need this document."
                required
            ><?= e($purpose) ?></textarea>

            <small>Maximum of 500 characters.</small>
        </div>

        <div class="form-group">
            <label for="supporting_files">
                Supporting Files <span>(Optional)</span>
            </label>

            <input
                type="file"
                id="supporting_files"
                name="supporting_files[]"
                accept=".pdf,.jpg,.jpeg,.png"
                multiple
            >

            <small>
                Optional attachments. PDF, JPG, JPEG, or PNG only.
                Maximum 3 files, 5 MB each.
            </small>
        </div>

        <?php if (empty($documentTypes)): ?>
            <p class="form-notice">
                No document types are currently available.
                Please contact the barangay office.
            </p>
        <?php else: ?>
            <button type="submit" class="submit-request-button">
                Submit Request
            </button>
        <?php endif; ?>
    </form>

</main>

<?php renderSiteFooter(); ?>

</body>
</html>
