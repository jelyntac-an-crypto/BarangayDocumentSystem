<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/mailer.php';

// Capture request details for security logging.
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

// Only allow POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Check whether this IP address is temporarily blocked.
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

// Verify CSRF token.
$csrfToken = $_POST['csrf_token'] ?? null;

if (!is_string($csrfToken) || !verifyCsrfToken($csrfToken)) {
    recordActivity(
        $pdo,
        $userId,
        'CSRF_REJECTED',
        'Invalid CSRF token during login OTP resend.',
        $ipAddress,
        $userAgent
    );

    http_response_code(403);
    exit('Invalid request.');
}

// Return to login verification with a message.
function returnToLoginVerification(string $message): never
{
    $_SESSION['login_otp_message'] = $message;
    header('Location: verify_login.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Lock the account.
    $stmt = $pdo->prepare(
        'SELECT id_number, username, email, account_status, locked_until
         FROM users
         WHERE id_number = ?
         FOR UPDATE'
    );

    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || $user['account_status'] !== 'active') {
        throw new RuntimeException('Login account is unavailable.');
    }

    // Check account lockout.
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

    // Retrieve the latest login OTP.
    $stmt = $pdo->prepare(
        "SELECT id,
                TIMESTAMPDIFF(SECOND, created_at, NOW()) AS seconds_since_sent
         FROM email_otps
         WHERE user_id = ?
           AND purpose = 'login'
         ORDER BY created_at DESC, id DESC
         LIMIT 1"
    );

    $stmt->execute([$userId]);
    $latestOtp = $stmt->fetch();

    if (!$latestOtp) {
        throw new RuntimeException('No login OTP record found.');
    }

    // Enforce 60-second resend cooldown.
    $secondsSinceSent = (int) $latestOtp['seconds_since_sent'];

    if ($secondsSinceSent < 60) {
        $remaining = 60 - $secondsSinceSent;
        $pdo->rollBack();

        returnToLoginVerification(
            'Please wait ' . $remaining . ' seconds before requesting another OTP.'
        );
    }

    // Count login OTP resend requests within 15 minutes.
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM activity_logs
         WHERE user_id = ?
           AND event_type = 'LOGIN_OTP_RESEND_REQUESTED'
           AND created_at >= NOW() - INTERVAL 15 MINUTE"
    );

    $stmt->execute([$userId]);
    $resendCount = (int) $stmt->fetchColumn();

    if ($resendCount >= 3) {
        $pdo->rollBack();

        returnToLoginVerification(
            'You have reached the maximum of 3 OTP resend requests within 15 minutes. Please try again later.'
        );
    }

    // Generate and hash a new 6-digit OTP.
    $otp = (string) random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);

    if ($otpHash === false) {
        throw new RuntimeException('OTP hashing failed.');
    }

    // Invalidate previous unused login OTPs.
    $stmt = $pdo->prepare(
        "UPDATE email_otps
         SET used_at = NOW()
         WHERE user_id = ?
           AND purpose = 'login'
           AND used_at IS NULL"
    );

    $stmt->execute([$userId]);

    // Insert the new OTP with a 5-minute expiry.
    $stmt = $pdo->prepare(
        "INSERT INTO email_otps
            (user_id, otp_hash, purpose, expires_at, attempts)
         VALUES (?, ?, 'login', DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0)"
    );

    $stmt->execute([$userId, $otpHash]);
    $newOtpId = (int) $pdo->lastInsertId();

    // Record the resend request.
    recordActivity(
        $pdo,
        $userId,
        'LOGIN_OTP_RESEND_REQUESTED',
        'User requested a new login OTP.',
        $ipAddress,
        $userAgent,
        [],
        (string) $user['username']
    );

    $pdo->commit();

    // Send the new OTP by email.
    try {
        $mail = createMailer();
        $mail->addAddress((string) $user['email']);
        $mail->isHTML(true);
        $mail->Subject = 'New Login Verification Code';

        $expiryTime = date('g:i A', time() + 300);
        $mail->Body = '
            <h2>Barangay Document Request and Tracking System</h2>
            <h3>Your log-in code</h3>
            <p>Your new 6-digit verification code is:</p>
            <h1>' . e($otp) . '</h1>
            <p>This code expires at ' . e($expiryTime) . ' Philippine time (in 5 minutes).</p>
            <p>Your previous unused login code is no longer valid.</p>
            <p>If you did not request this code, ignore this email and consider changing your password.</p>
        ';

        $mail->AltBody =
            "Barangay Document Request and Tracking System\n"
            . "Your log-in code is: {$otp}\n"
            . "This code expires at {$expiryTime} Philippine time (in 5 minutes).\n"
            . "Your previous unused login code is no longer valid.\n"
            . "If you did not request this code, ignore this email and consider changing your password.";

        $mail->send();

        // Record successful login OTP email delivery.
        recordActivity(
            $pdo,
            $userId,
            'OTP_SENT',
            'Login verification OTP resend email sent.',
            $ipAddress,
            $userAgent,
            [],
            (string) $user['username']
        );
        
    } catch (Throwable $mailError) {

        // Invalidate the new OTP if email delivery fails.
        $stmt = $pdo->prepare(
            'UPDATE email_otps
             SET used_at = NOW()
             WHERE id = ?'
        );

        $stmt->execute([$newOtpId]);

        error_log(
            'Login OTP resend email failed: '
            . $mailError->getMessage()
        );

        returnToLoginVerification(
            'Unable to send a new login verification code. Please try again later.'
        );
    }

    returnToLoginVerification(
        'A new login verification code has been sent to your registered email.'
    );

} catch (Throwable $error) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Login OTP resend failed: ' . $error->getMessage());

    http_response_code(500);
    exit('Unable to process your OTP resend request.');
}
