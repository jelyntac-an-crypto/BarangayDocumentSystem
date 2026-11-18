<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/layout.php';

// Allow GET and POST requests only.
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    http_response_code(405);
    exit('Method not allowed.');
}

// Check the pending password reset session.
$userId = $_SESSION['pending_password_reset_user_id'] ?? null;
$pendingCreatedAt = $_SESSION['pending_password_reset_created_at'] ?? null;

if (
    !is_string($userId) ||
    preg_match('/^[0-9]{4}-[0-9]{4}$/D', $userId) !== 1 ||
    !is_int($pendingCreatedAt) ||
    (time() - $pendingCreatedAt) > 900 ||
    $pendingCreatedAt > time()
) {
    unset(
        $_SESSION['pending_password_reset_user_id'],
        $_SESSION['pending_password_reset_created_at']
    );

    header('Location: forgot_password.php');
    exit;
}

$message = $_SESSION['password_reset_otp_message'] ?? '';
unset($_SESSION['password_reset_otp_message']);

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = substr(
    $_SERVER['HTTP_USER_AGENT'] ?? '',
    0,
    500
);

$sessionUsername = $_SESSION['password_recovery_username'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify CSRF token.
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_string($csrfToken) || !verifyCsrfToken($csrfToken)) {
        recordActivity(
            $pdo,
            $userId,
            'CSRF_REJECTED',
            'Invalid CSRF token during password reset OTP verification.',
            $ipAddress,
            $userAgent,
            [],
            is_string($sessionUsername) ? $sessionUsername : null
        );

        http_response_code(403);
        exit('Invalid request.');
    }

    // Validate OTP format.
    $otp = $_POST['otp'] ?? '';

    if (!is_string($otp) || preg_match('/^[0-9]{6}$/D', $otp) !== 1) {
        $message = 'Please enter a valid 6-digit OTP.';

    } else {

        try {
            $pdo->beginTransaction();

            // Lock the account.
            $stmt = $pdo->prepare(
                'SELECT id_number, account_status
                 FROM users
                 WHERE id_number = ?
                 FOR UPDATE'
            );

            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || $user['account_status'] !== 'active') {
                $pdo->rollBack();

                unset(
                    $_SESSION['pending_password_reset_user_id'],
                    $_SESSION['pending_password_reset_created_at']
                );

                header('Location: forgot_password.php');
                exit;
            }

            // Retrieve the latest unused password reset OTP.
            $stmt = $pdo->prepare(
                "SELECT id, otp_hash, attempts,
                        (expires_at <= NOW()) AS is_expired
                 FROM email_otps
                 WHERE user_id = ?
                   AND purpose = 'password_reset'
                   AND used_at IS NULL
                 ORDER BY created_at DESC, id DESC
                 LIMIT 1
                 FOR UPDATE"
            );

            $stmt->execute([$userId]);
            $otpRecord = $stmt->fetch();

            if (!$otpRecord) {
                $pdo->rollBack();
                $message = 'No valid reset code found. Please request a new one.';

            } elseif ((int) $otpRecord['attempts'] >= 3) {
                $pdo->rollBack();

                unset(
                    $_SESSION['pending_password_reset_user_id'],
                    $_SESSION['pending_password_reset_created_at']
                );

                $message = 'Maximum OTP attempts reached. Please request a new reset code.';

            } elseif ((int) $otpRecord['is_expired'] === 1) {

                $stmt = $pdo->prepare(
                    'UPDATE email_otps
                     SET used_at = NOW()
                     WHERE id = ?'
                );

                $stmt->execute([$otpRecord['id']]);

                recordActivity(
                    $pdo,
                    $userId,
                    'OTP_FAILED',
                    'Expired password reset OTP entered.',
                    $ipAddress,
                    $userAgent,
                    ['reason' => 'expired'],
                    is_string($sessionUsername) ? $sessionUsername : null
                );

                $pdo->commit();

                unset(
                    $_SESSION['pending_password_reset_user_id'],
                    $_SESSION['pending_password_reset_created_at']
                );

                $message = 'Your reset code has expired. Please request a new one.';

            } elseif (!password_verify($otp, (string) $otpRecord['otp_hash'])) {

                $newAttempts = (int) $otpRecord['attempts'] + 1;

                $stmt = $pdo->prepare(
                    'UPDATE email_otps
                     SET attempts = ?,
                         used_at = CASE WHEN ? >= 3 THEN NOW() ELSE used_at END
                     WHERE id = ?'
                );

                $stmt->execute([
                    $newAttempts,
                    $newAttempts,
                    $otpRecord['id']
                ]);

                // Record the failed OTP verification.
                recordActivity(
                    $pdo,
                    $userId,
                    'OTP_FAILED',
                    'Incorrect password reset OTP entered.',
                    $ipAddress,
                    $userAgent,
                    ['attempts' => $newAttempts],
                    is_string($sessionUsername) ? $sessionUsername : null
                );

                $pdo->commit();

                $remaining = 3 - $newAttempts;

                $message = $remaining > 0
                    ? 'Incorrect OTP. Attempts remaining: ' . $remaining
                    : 'Maximum OTP attempts reached. Please request a new reset code.';

                if ($remaining <= 0) {
                    unset(
                        $_SESSION['pending_password_reset_user_id'],
                        $_SESSION['pending_password_reset_created_at']
                    );
                }

            } else {

                // Mark the OTP as used.
                $stmt = $pdo->prepare(
                    'UPDATE email_otps
                     SET used_at = NOW()
                     WHERE id = ?'
                );

                $stmt->execute([$otpRecord['id']]);

                $pdo->commit();

                // Store a short-lived verified reset session.
                unset(
                    $_SESSION['pending_password_reset_user_id'],
                    $_SESSION['pending_password_reset_created_at']
                );

                session_regenerate_id(true);

                $_SESSION['verified_password_reset_user_id'] = $userId;
                $_SESSION['verified_password_reset_created_at'] = time();

                header('Location: reset_password.php');
                exit;
            }

        } catch (Throwable $error) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Password reset OTP verification error: '
                . $error->getMessage()
            );

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

    <title>Verify Password Reset</title>

    <link rel="stylesheet" href="../../css/layout.css">
    <link rel="stylesheet" href="../../css/auth/verify_registration.css">
</head>
<body>

    <?php renderSiteHeader('login', '../../'); ?>

    <div class="verification-container">
        <h2>Password Reset Verification</h2>
    </div>

    <p>Enter the 6-digit password reset code sent to your registered email address.</p>

    <?php if ($message !== ''): ?>
        <p role="alert"><?= e($message) ?></p>
    <?php endif; ?>

    <form method="POST" action="verify_password_reset.php">

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

        <button type="submit">Verify Reset Code</button>

    </form>

    <p>
        <a href="forgot_password.php">Request a new reset code</a>
    </p>

    <p>
        <a href="login.php">Back to Login</a>
    </p>

    <?php renderSiteFooter(); ?>

</body>
</html>
