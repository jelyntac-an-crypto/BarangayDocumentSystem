<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/mailer.php';
require_once __DIR__ . '/recovery_security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$userId = $_SESSION['pending_password_reset_user_id'] ?? null;
$pendingCreatedAt = $_SESSION['pending_password_reset_created_at'] ?? null;
if (
    !is_string($userId)
    || preg_match('/^[0-9]{4}-[0-9]{4}$/D', $userId) !== 1
    || !is_int($pendingCreatedAt)
    || (time() - $pendingCreatedAt) > 900
    || $pendingCreatedAt > time()
) {
    unset(
        $_SESSION['pending_password_reset_user_id'],
        $_SESSION['pending_password_reset_created_at']
    );
    header('Location: forgot_password.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (!is_string($csrfToken) || !verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit('Invalid request.');
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
$sessionUsername = $_SESSION['password_recovery_username'] ?? null;

function returnToPasswordResetOtp(string $message): never
{
    $_SESSION['password_reset_otp_message'] = $message;
    header('Location: verify_password_reset.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT id_number, username, email, account_status
         FROM users
         WHERE id_number = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['account_status'] !== 'active') {
        unset(
            $_SESSION['pending_password_reset_user_id'],
            $_SESSION['pending_password_reset_created_at']
        );
        header('Location: forgot_password.php');
        exit;
    }

    $throttle = getPasswordResetOtpThrottle($pdo, $userId);
    if (!$throttle['allowed']) {
        returnToPasswordResetOtp($throttle['message']);
    }

    $otp = (string) random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    if ($otpHash === false) {
        throw new RuntimeException('Password reset OTP hashing failed.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE email_otps
             SET used_at = NOW()
             WHERE user_id = ?
               AND purpose = 'password_reset'
               AND used_at IS NULL"
        );
        $stmt->execute([$userId]);

        $stmt = $pdo->prepare(
            "INSERT INTO email_otps
                (user_id, otp_hash, purpose, expires_at, attempts)
             VALUES (?, ?, 'password_reset', DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0)"
        );
        $stmt->execute([$userId, $otpHash]);
        $otpId = (int) $pdo->lastInsertId();

        recordActivity(
            $pdo,
            $userId,
            'RESET_OTP_RESEND_REQUESTED',
            'User requested another password reset OTP.',
            $ipAddress,
            $userAgent,
            [],
            is_string($sessionUsername) ? $sessionUsername : (string) $user['username']
        );
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    try {
        $mail = createMailer();
        $mail->addAddress((string) $user['email']);
        $mail->isHTML(true);
        $mail->Subject = 'New Password Reset Verification Code';
        $expiryTime = date('g:i A', time() + 300);
        $mail->Body =
            '<h2>Barangay Document Request and Tracking System</h2>'
            . '<p>Your new password reset verification code is:</p>'
            . '<h1>' . e($otp) . '</h1>'
            . '<p>This code expires at ' . e($expiryTime)
            . ' Philippine time (in 5 minutes).</p>'
            . '<p>Your previous unused reset code is no longer valid.</p>'
            . '<p>If you did not request this code, ignore this email.</p>';
        $mail->AltBody =
            "Barangay Document Request and Tracking System\n"
            . "Your new password reset verification code is: {$otp}.\n"
            . "This code expires at {$expiryTime} Philippine time (in 5 minutes).\n"
            . "Your previous unused reset code is no longer valid.\n"
            . 'If you did not request this code, ignore this email.';
        $mail->send();

        recordActivity(
            $pdo,
            $userId,
            'OTP_SENT',
            'Password reset OTP resend email sent.',
            $ipAddress,
            $userAgent,
            [],
            is_string($sessionUsername) ? $sessionUsername : (string) $user['username']
        );

        $_SESSION['pending_password_reset_created_at'] = time();
    } catch (Throwable $mailError) {
        $stmt = $pdo->prepare('UPDATE email_otps SET used_at = NOW() WHERE id = ?');
        $stmt->execute([$otpId]);
        error_log('Password reset OTP resend email failed: ' . $mailError->getMessage());
        returnToPasswordResetOtp('Unable to send a new reset code. Please try again later.');
    }

    returnToPasswordResetOtp('A new password reset code has been sent to your registered email.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Password reset OTP resend failed: ' . $error->getMessage());
    http_response_code(500);
    exit('Unable to process your reset-code request.');
}
