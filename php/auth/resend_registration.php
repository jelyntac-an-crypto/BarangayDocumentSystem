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

// Check for a pending registration.
$userId = $_SESSION['pending_registration_user_id'] ?? null;

if (
    !is_string($userId) ||
    preg_match('/^[0-9]{4}-[0-9]{4}$/D', $userId) !== 1
) {
    header('Location: register.php');
    exit;
}

// Verify CSRF token.
$csrfToken = $_POST['csrf_token'] ?? null;

if (!is_string($csrfToken) || !verifyCsrfToken($csrfToken)) {
    recordActivity(
        $pdo,
        $userId,
        'CSRF_REJECTED',
        'Invalid CSRF token during registration OTP resend.',
        $ipAddress,
        $userAgent
    );

    http_response_code(403);
    exit('Invalid request.');
}

// Store a message and return to the verification page.
function returnToVerification(string $message): never
{
    $_SESSION['registration_otp_message'] = $message;
    header('Location: verify_registration.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Lock the pending account.
    $stmt = $pdo->prepare(
        'SELECT id_number, email, account_status
         FROM users
         WHERE id_number = ?
         FOR UPDATE'
    );

    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || $user['account_status'] !== 'pending') {
        throw new RuntimeException('Registration is unavailable.');
    }

    // Check the latest registration OTP and resend cooldown.
    $stmt = $pdo->prepare(
        'SELECT
            TIMESTAMPDIFF(SECOND, created_at, NOW()) AS seconds_since_sent
         FROM email_otps
         WHERE user_id = ?
           AND purpose = ?
         ORDER BY created_at DESC, id DESC
         LIMIT 1'
    );

    $stmt->execute([$userId, 'registration']);
    $latestOtp = $stmt->fetch();

    if (!$latestOtp) {
        throw new RuntimeException('No registration OTP record found.');
    }

    $secondsSinceSent = (int) $latestOtp['seconds_since_sent'];

    if ($secondsSinceSent < 60) {
        $remaining = 60 - $secondsSinceSent;
        $pdo->rollBack();

        returnToVerification(
            'Please wait ' . $remaining . ' seconds before requesting another OTP.'
        );
    }

    // Count resend requests within the last 15 minutes.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS resend_count
         FROM activity_logs
         WHERE user_id = ?
           AND event_type = ?
           AND created_at >= NOW() - INTERVAL 15 MINUTE'
    );

    $stmt->execute([$userId, 'OTP_RESEND_REQUESTED']);
    $resendCount = (int) $stmt->fetchColumn();

    if ($resendCount >= 3) {
        $pdo->rollBack();

        returnToVerification(
            'You have reached the maximum of 3 OTP resend requests within 15 minutes. Please try again later.'
        );
    }

    // Generate a new 6-digit OTP.
    $otp = (string) random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);

    if ($otpHash === false) {
        throw new RuntimeException('OTP hashing failed.');
    }

    // Invalidate previous unused registration OTPs.
    $stmt = $pdo->prepare(
        'UPDATE email_otps
         SET used_at = NOW()
         WHERE user_id = ?
           AND purpose = ?
           AND used_at IS NULL'
    );

    $stmt->execute([$userId, 'registration']);

    // Save the new OTP with a 5-minute expiry.
    $stmt = $pdo->prepare(
        'INSERT INTO email_otps
            (user_id, otp_hash, purpose, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))'
    );

    $stmt->execute([
        $userId,
        $otpHash,
        'registration'
    ]);

    $newOtpId = (int) $pdo->lastInsertId();

// Record the resend request for rate limiting.
recordActivity(
    $pdo,
    $userId,
    'OTP_RESEND_REQUESTED',
    'Resident requested a new registration OTP.',
    $ipAddress,
    $userAgent
);

    $pdo->commit();

    // Send the new OTP by email.
    try {
        $mail = createMailer();
        $mail->addAddress($user['email']);
        $mail->isHTML(true);
        $mail->Subject = 'New Verification Code - Barangay Document Request and Tracking System';

        $mail->Body = '
            <h2>Barangay Document Request and Tracking System</h2>
            <h3>New Registration Verification Code</h3>
            <p>Your new 6-digit verification code is:</p>
            <h1>' . e($otp) . '</h1>
            <p>This code expires in 5 minutes.</p>
            <p>Your previous unused registration code is no longer valid.</p>
            <p>If you did not request this code, ignore this email and consider changing your password.</p>
        ';

        $mail->AltBody =
            "Barangay Document Request and Tracking System\n"
            . "New Registration Verification Code\n"
            . "Your new 6-digit verification code is: {$otp}\n"
            . "This code expires in 5 minutes.\n"
            . "Your previous unused registration code is no longer valid.\n"
            . "If you did not request this code, ignore this email and consider changing your password.";

        $mail->send();

        // Record successful registration OTP email delivery.
        recordActivity(
            $pdo,
            $userId,
            'OTP_SENT',
            'Registration verification OTP resend email sent.',
            $ipAddress,
            $userAgent
        );

    } catch (Throwable $mailError) {
        // Invalidate the code if email delivery fails.
        $stmt = $pdo->prepare(
            'UPDATE email_otps
             SET used_at = NOW()
             WHERE id = ?'
        );

        $stmt->execute([$newOtpId]);

        error_log(
            'Registration OTP resend email failed: '
            . $mailError->getMessage()
        );

        returnToVerification(
            'Unable to send a new verification code. Please try again later.'
        );
    }

    returnToVerification(
        'A new verification code has been sent to your registered email.'
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Registration OTP resend failed: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to process your OTP resend request.');
}
