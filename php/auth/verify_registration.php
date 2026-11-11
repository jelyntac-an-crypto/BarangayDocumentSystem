<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';

// Allow only GET and POST requests.
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','POST'], true)) {
    http_response_code(405);
    exit('Method not allowed.');
}

// Check for a pending registration.
$userId = $_SESSION['pending_registration_user_id'] ?? null;

if (
    !is_string($userId) ||
    preg_match('/^[0-9]{4}-[0-9]{4}$/D', $userId) !== 1
) {
    header('Location: register.php');
    exit;
}

$message = $_SESSION['registration_otp_message'] ?? '';
unset($_SESSION['registration_otp_message']);

// Process OTP submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify CSRF token.
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_string($csrfToken) || !verifyCsrfToken($csrfToken)) {
        http_response_code(403);
        exit('Invalid request.');
    }

    // Validate the OTP format.
    $otp = $_POST['otp'] ?? '';

    if (!is_string($otp) ||  preg_match('/^[0-9]{6}$/', $otp) !== 1) {
        $message = 'Please enter a valid 6-digit OTP. ';
    } else {

        try {
            $pdo->beginTransaction();

            // Lock the pending account
            $stmt = $pdo->prepare(
                'SELECT id_number, account_status
                FROM users
                WHERE id_number = ?
                FOR UPDATE'
            );

            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || $user['account_status'] !== 'pending') {
                throw new RuntimeException('Registration is unavailable.');
            }

            // Get the latest unused registration OTP.
            $stmt = $pdo->prepare(
                'SELECT id, otp_hash, expires_at, attempts,
                        (expires_at <= NOW()) AS is_expired
                FROM email_otps
                WHERE user_id = ?
                   AND purpose = ?
                   AND used_at IS NULL
                ORDER BY created_at DESC, id DESC
                LIMIT 1
                FOR UPDATE'
            );

            $stmt->execute([$userId, 'registration']);
            $otpRecord = $stmt->fetch();

            if (!$otpRecord) {
                $pdo->rollBack();
                $message = 'No valid OTP found. Please request a new code.';
            } elseif ((int) $otpRecord['attempts'] >= 3) {
                $pdo->rollBack();
                $message = 'Maximum OTP attempts reached. Please request a new code.';
            } elseif ((int) $otpRecord['is_expired'] === 1) {
                $stmt = $pdo->prepare(
                    'UPDATE email_otps
                    SET used_at = NOW()
                    WHERE id = ? '
                );

                $stmt->execute([$otpRecord['id']]);

                // Record the expired OTP event.
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

                $logStmt = $pdo->prepare(
                    'INSERT INTO activity_logs
                       (user_id, event_type, description, ip_address, user_agent)
                     VALUES (?, ?, ?, ?, ?)'
                );

                $logStmt->execute([
                    $userId,
                    'OTP_EXPIRED',
                    'Registration OTP expired.',
                    $ipAddress,
                    $userAgent
                ]);

                $pdo->commit();

                $message = 'Your OTP has expired. Please request a new code.';

            } elseif (!password_verify($otp, $otpRecord['otp_hash'])) {
                $newAttempts = (int) $otpRecord['attempts'] + 1;

                $stmt = $pdo->prepare(
                    'UPDATE email_otps
                    SET attempts = ?,
                        used_at = CASE WHEN ? >= 3 THEN NOW() ELSE used_at END
                    WHERE id = ? '
                );

                $stmt->execute([
                    $newAttempts,
                    $newAttempts,
                    $otpRecord['id']
                ]);

                //Record the failed OTP verification attempt.
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

                $logStmt = $pdo->prepare(
                    'INSERT INTO activity_logs
                        (user_id, event_type, description, ip_address, user_agent)
                    VALUES (?, ?, ?, ?, ?)'
                );

                $logStmt->execute([
                    $userId,
                    'OTP_FAILED',
                    'Incorrect registration OTP entered.',
                    $ipAddress,
                    $userAgent
                ]);

                $pdo->commit();

                $remaining = 3 - $newAttempts;

                $message = $remaining > 0
                    ? 'Incorrect OTP. Attempts remaining: ' . $remaining
                    : 'Maximum OTP attempts reached. Please request a new code.';

            } else {

                // Mark the OTP as used.
                $stmt = $pdo->prepare(
                    'UPDATE email_otps
                     SET used_at = NOW()
                     WHERE id = ?'
                );

                $stmt->execute([$otpRecord['id']]);

                // Activate the resident account.
                $stmt = $pdo->prepare(
                    'UPDATE users
                     SET account_status = ?,
                         email_verified_at = NOW()
                     WHERE id_number = ?'
                );

                $stmt->execute(['active', $userId]);

                // Record successful email verification.
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

                $stmt = $pdo->prepare(
                    'INSERT INTO activity_logs
                        (user_id, event_type, description, ip_address, user_agent)
                     VALUES (?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $userId,
                    'EMAIL_VERIFIED',
                    'Resident email successfully verified.',
                    $ipAddress,
                    $userAgent
                ]);

                $pdo->commit();

                // Remove pending registration session data.
                unset($_SESSION['pending_registration_user_id']);

                // Regenerate the session ID.
                session_regenerate_id(true);

                header('Location: login.php?verified=1');
                exit;
            }

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Registration OTP verification failed: ' . $e->getMessage());

            http_response_code(500);
            exit('Unable to process OTP verification.');
        }
    }

}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/auth/verify_registration.css">
    <title>Verify Registration</title>
</head>
<body>
    <div class="verification-container">
        <h2>Email Verification</h2>
    </div>

    <p>Enter the 6-digit verification code sent to your registered email address.</p>

    <?php if ($message !== ''): ?>
        <p role="alert"><?= e($message) ?></p>
    <?php endif; ?>

    <form method="POST" action="verify_registration.php">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(generateCsrfToken()) ?>"
        >

        <label for="otp">Verification Code</label>

        <input
            type="text"
            id="otp"
            name="otp"
            maxlength="6"
            minlength="6"
            pattern="[0-9]{6}"
            inputmode="numeric"
            autocomplete="one-time-code"
            required
        >

        <button type="submit">Verify Email</button>

    </form>
    <form method="POST" action="resend_registration.php">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= e(generateCsrfToken()) ?>"
    >

    <button type="submit" id="resend-otp-button">Resend OTP</button>

    <p id="resend-countdown" aria-live="polite"></p>

</form>
<div>
    <script src="../../js/auth/verify_registration.js" defer></script>
</div>

</body>
</html>