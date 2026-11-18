<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/layout.php';

// Allow GET and POST only.
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    http_response_code(405);
    exit('Method not allowed.');
}

// Block requests from IP addresses with an active temporary block.
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (isIpTemporarilyBlocked($pdo, $ipAddress)) {
    http_response_code(429);
    exit('Too many failed attempts. This IP address is temporarily blocked for 10 minutes.');
}

// Check pending login session.
$userId = $_SESSION['pending_login_user_id'] ?? null;
$pendingCreatedAt = $_SESSION['pending_login_created_at'] ?? null;

if (
    !is_string($userId) ||
    preg_match('/^[0-9]{4}-[0-9]{4}$/D', $userId) !== 1 ||
    !is_int($pendingCreatedAt) ||
    (time() - $pendingCreatedAt) > 900 ||
    $pendingCreatedAt > time()
) {
    unset(
        $_SESSION['pending_login_user_id'],
        $_SESSION['pending_login_created_at']
    );

    header('Location: login.php');
    exit;
}

$message = $_SESSION['login_otp_message'] ?? '';
unset($_SESSION['login_otp_message']);

$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

$sessionUsername = $_SESSION['pending_login_username'] ?? null;
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

// Get the remaining OTP resend cooldown.
$resendRemaining = 0;
$resendLimitReached = false;

$stmt = $pdo->prepare(
    "SELECT TIMESTAMPDIFF(SECOND, created_at, NOW())
     FROM email_otps
     WHERE user_id = ?
       AND purpose = 'login'
     ORDER BY created_at DESC, id DESC
     LIMIT 1"
);

$stmt->execute([$userId]);
$secondsSinceSent = $stmt->fetchColumn();

if ($secondsSinceSent !== false) {
    $resendRemaining = max(0, 60 - (int) $secondsSinceSent);
}

// Check the maximum of 3 resends within 15 minutes.
$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM activity_logs
     WHERE user_id = ?
       AND event_type = 'LOGIN_OTP_RESEND_REQUESTED'
       AND created_at >= NOW() - INTERVAL 15 MINUTE"
);

$stmt->execute([$userId]);
$resendLimitReached = (int) $stmt->fetchColumn() >= 3;

// Process OTP submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify CSRF token.
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_string($csrfToken) || !verifyCsrfToken($csrfToken)) {
        recordActivity(
            $pdo,
            $userId,
            'CSRF_REJECTED',
            'Invalid CSRF token during login OTP verification.',
            $ipAddress,
            $userAgent
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
                'SELECT id_number, username, role, account_status,
                        failed_login_count, lockout_level, locked_until
                 FROM users
                 WHERE id_number = ?
                 FOR UPDATE'
            );

            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || $user['account_status'] !== 'active') {
                throw new RuntimeException('Login account is unavailable.');
            }

            // Check whether the account is still locked.
            if (!empty($user['locked_until'])) {
                $lockUntil = strtotime((string) $user['locked_until']);

                if ($lockUntil !== false && $lockUntil > time()) {
                    $pdo->rollBack();

                    unset(
                        $_SESSION['pending_login_user_id'],
                        $_SESSION['pending_login_created_at']
                    );

                    header('Location: login.php');
                    exit;
                }
            }

            // Retrieve the latest unused login OTP.
            $stmt = $pdo->prepare(
                "SELECT id, otp_hash, attempts,
                        (expires_at <= NOW()) AS is_expired
                 FROM email_otps
                 WHERE user_id = ?
                   AND purpose = 'login'
                   AND used_at IS NULL
                 ORDER BY created_at DESC, id DESC
                 LIMIT 1
                 FOR UPDATE"
            );

            $stmt->execute([$userId]);
            $otpRecord = $stmt->fetch();

            if (!$otpRecord) {
                $pdo->rollBack();
                $message = 'No valid OTP found. Please log in again.';

            } elseif ((int) $otpRecord['attempts'] >= 3) {
                $pdo->rollBack();

                unset(
                    $_SESSION['pending_login_user_id'],
                    $_SESSION['pending_login_created_at']
                );

                $message = 'Maximum OTP attempts reached. Please log in again.';

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
                    'Expired login OTP entered.',
                    $ipAddress,
                    $userAgent,
                    ['reason' => 'expired'],
                    (string) $user['username']
                );

                $pdo->commit();

                unset(
                    $_SESSION['pending_login_user_id'],
                    $_SESSION['pending_login_created_at']
                );

                $message = 'Your OTP has expired. Please log in again.';

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

                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

                // Record the failed OTP attempt.
                $stmt = $pdo->prepare(
                    "INSERT INTO login_attempts
                     (user_id, attempted_username, ip_address, user_agent, attempt_status)
                     VALUES (?, ?, ?, ?, 'OTP_FAILED')"
                );

                $stmt->execute([
                    $userId,
                    $user['username'],
                    $ipAddress,
                    $userAgent
                ]);

                // Apply IP blocking after the failed OTP attempt.
                applyIpFailureBlock(
                    $pdo,
                    $ipAddress,
                    $userId,
                    $userAgent
                );

                // Record the security activity.
                recordActivity(
                    $pdo,
                    $userId,
                    'OTP_FAILED',
                    'Incorrect login OTP entered.',
                    $ipAddress,
                    $userAgent,
                    ['attempts' => $newAttempts],
                    (string) $user['username']
                );

                $pdo->commit();

                $remaining = 3 - $newAttempts;

                $message = $remaining > 0
                    ? 'Incorrect OTP. Attempts remaining: ' . $remaining
                    : 'Maximum OTP attempts reached. Please log in again.';

                if ($remaining <= 0) {
                    unset(
                        $_SESSION['pending_login_user_id'],
                        $_SESSION['pending_login_created_at']
                    );
                }

            } else {

                // Mark OTP as used.
                $stmt = $pdo->prepare(
                    'UPDATE email_otps
                     SET used_at = NOW()
                     WHERE id = ?'
                );

                $stmt->execute([$otpRecord['id']]);

                // Reset progressive lockout after successful OTP.
                $stmt = $pdo->prepare(
                    'UPDATE users
                     SET failed_login_count = 0,
                         lockout_level = 0,
                         locked_until = NULL,
                         last_login_at = NOW()
                     WHERE id_number = ?'
                );

                $stmt->execute([$userId]);

                // Clear tier alert tracking for the next failed-login cycle.
                $stmt = $pdo->prepare(
                    'DELETE FROM security_alerts WHERE user_id = ?'
                );

                $stmt->execute([$userId]);

                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

                // Record completed login.
                $stmt = $pdo->prepare(
                    "INSERT INTO login_attempts
                     (user_id, attempted_username, ip_address, user_agent, attempt_status)
                     VALUES (?, ?, ?, ?, 'SUCCESS')"
                );

                $stmt->execute([
                    $userId,
                    $user['username'],
                    $ipAddress,
                    $userAgent
                ]);

                // Record successful login activity.       
                recordActivity(
                    $pdo,
                    $userId,
                    'LOGIN_SUCCESS',
                    'User successfully completed two-step login.',
                    $ipAddress,
                    $userAgent,
                    ['role' => $user['role']],
                    (string) $user['username']
                );

                $pdo->commit();

                // Remove pending login information.
                unset(
                    $_SESSION['pending_login_user_id'],
                    $_SESSION['pending_login_created_at'],
                    $_SESSION['login_otp_message'],
                    $_SESSION['show_forgot_password'],
                    $_SESSION['login_lockout_until']
                );

                // Prevent session fixation.
                session_regenerate_id(true);

                // Create authenticated session only after OTP succeeds.
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = (string) $user['username'];
                $_SESSION['role'] = (string) $user['role'];
                $_SESSION['last_activity'] = time();

                // Redirect according to account role.
                switch ($user['role']) {
                    case 'resident':
                        header('Location: ../resident/dashboard.php');
                        break;

                    case 'staff':
                        header('Location: ../staff/dashboard.php');
                        break;

                    case 'security_admin':
                        header('Location: ../admin/dashboard.php');
                        break;

                    default:
                        $_SESSION = [];
                        session_destroy();
                        header('Location: login.php');
                        break;
                }

                exit;
            }

        } catch (Throwable $error) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Login OTP verification error: ' . $error->getMessage());

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
    <title>Login Verification</title>
    <link rel="stylesheet" href="../../css/layout.css">
    <link rel="stylesheet" href="../../css/auth/verify_registration.css">
</head>
<body>

    <?php renderSiteHeader('login', '../../'); ?>

    <div class="verification-container">
        <h2>Login Verification</h2>
    </div>

    <p>Enter the 6-digit login verification code sent to your registered email address.</p>

    <?php if ($message !== ''): ?>
        <p role="alert"><?= e($message) ?></p>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['pending_login_user_id'])): ?>

    <form method="POST" action="verify_login.php">

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

        <button type="submit">Verify Login</button>

    </form>
    
    <form method="POST" action="resend_login.php" id="resend-otp-form">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(generateCsrfToken()) ?>"
        >

        <button
            type="submit"
            id="resend-otp-button"
            <?= ($resendRemaining > 0 || $resendLimitReached) ? 'disabled' : '' ?>
        >
            Resend OTP
        </button>

        <p id="resend-countdown" role="status">
            <?php if ($resendLimitReached): ?>
                Maximum resend requests reached. Please try again later.
            <?php elseif ($resendRemaining > 0): ?>
                You can request another OTP in <?= $resendRemaining ?> seconds.
            <?php endif; ?>
        </p>

    </form>
    <?php endif; ?>



<p><a href="login.php">Cancel and return to login</a></p>


    <?php renderSiteFooter(); ?>

</body>
</html>
