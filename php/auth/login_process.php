<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/mailer.php';

// Return to the login page with a message.

function returnLoginMessage(string $message): never
{
    $_SESSION['login_notice'] = $message;
    header('Location: login.php');
    exit;
}

//Safely get a string from POST data.
function loginPostString(string $field): string
{
    $value = $_POST[$field] ?? '';

    return is_string($value) ? trim($value) : '';
}

//Record a login attempt.
 
function recordLoginAttempt(
    PDO $pdo,
    ?string $userId,
    string $username,
    string $ipAddress,
    string $userAgent,
    string $status
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts
         (user_id, attempted_username, ip_address, user_agent, attempt_status)
         VALUES (?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $userId,
        $username !== '' ? substr($username, 0, 255) : null,
        $ipAddress,
        $userAgent,
        $status
    ]);

    // Apply IP blocking after a failed authentication attempt.
    if (in_array($status, ['FAILED', 'CAPTCHA_FAILED', 'OTP_FAILED'], true)) {
        applyIpFailureBlock($pdo, $ipAddress, $userId, $userAgent);
    }
}

// Record a security activity.
function recordLoginActivity(
    PDO $pdo,
    ?string $userId,
    string $eventType,
    string $description,
    string $ipAddress,
    string $userAgent,
    ?string $usernameTyped = null
): void {
    recordActivity(
        $pdo,
        $userId,
        $eventType,
        $description,
        $ipAddress,
        $userAgent,
        [],
        $usernameTyped
    );
}


function sendFailedLoginAlert(
    PDO $pdo,
    array $user,
    int $failedCount,
    string $ipAddress,
    string $userAgent
): void {
    // Send alerts only at 3, 6, and 9 failed attempts.
    if (!in_array($failedCount, [3, 6, 9], true)) {
        return;
    }

    $tier = intdiv($failedCount, 3);
    $userId = (string) $user['id_number'];

    // Reserve this tier so the email is sent only once.
    $reserve = $pdo->prepare(
        "INSERT IGNORE INTO security_alerts
         (user_id, tier, failed_attempt_count, ip_address,
          user_agent, alert_status)
         VALUES (?, ?, ?, ?, ?, 'pending')"
    );

    $reserve->execute([
        $userId,
        $tier,
        $failedCount,
        $ipAddress,
        $userAgent
    ]);

    // A previous alert already exists for this tier.
    if ($reserve->rowCount() !== 1) {
        return;
    }

    global $config;

    try {
        $appUrl = rtrim((string) $config['app_url'], '/');
        $resetUrl = $appUrl . '/php/auth/forgot_password.php';

        $dateTime = date('F j, Y h:i:s A') . ' Philippine Time';

        $safeDateTime = e($dateTime);
        $safeIp = e($ipAddress);
        $safeUserAgent = e($userAgent);
        $safeResetUrl = e($resetUrl);
        $safeCount = e((string) $failedCount);

        $mail = createMailer();
        $mail->addAddress((string) $user['email']);
        $mail->isHTML(true);
        $mail->Subject = 'Failed sign-in attempts on your account';

        $mail->Body =
            '<h2>Security Alert</h2>'
            . '<p>We detected multiple failed sign-in attempts '
            . 'on your account.</p>'
            . '<p><strong>Failed attempts:</strong> '
            . $safeCount . '</p>'
            . '<p><strong>Date and time:</strong> '
            . $safeDateTime . '</p>'
            . '<p><strong>IP address:</strong> '
            . $safeIp . '</p>'
            . '<p><strong>Browser/device:</strong> '
            . $safeUserAgent . '</p>'
            . '<p>If this was not you, reset your password now:</p>'
            . '<p><a href="' . $safeResetUrl . '">'
            . 'Reset Your Password</a></p>';

        $mail->AltBody =
            "Security Alert\n"
            . "Failed attempts: {$failedCount}\n"
            . "Date and time: {$dateTime}\n"
            . "IP address: {$ipAddress}\n"
            . "Browser/device: {$userAgent}\n\n"
            . "If this was not you, reset your password now:\n"
            . $resetUrl;

        $mail->send();

        // Mark the alert as successfully sent.
        $update = $pdo->prepare(
            "UPDATE security_alerts
             SET alert_status = 'sent',
                 sent_at = NOW()
             WHERE user_id = ? AND tier = ?"
        );

        $update->execute([$userId, $tier]);

        // Record the successful email in the activity log.
        recordLoginActivity(
            $pdo,
            $userId,
            'ALERT_EMAIL_SENT',
            "Tier {$tier} failed sign-in alert email sent.",
            $ipAddress,
            $userAgent
        );

    } catch (Throwable $error) {
        $failed = $pdo->prepare(
            "UPDATE security_alerts
             SET alert_status = 'failed'
             WHERE user_id = ? AND tier = ?"
        );

        $failed->execute([$userId, $tier]);

        error_log(
            'Failed login alert email error: ' . $error->getMessage()
        );
    }
}

// Accept POST requests only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

// Check whether this IP address is temporarily blocked.
if (isIpTemporarilyBlocked($pdo, $ipAddress)) {
    http_response_code(429);

    returnLoginMessage(
        'Too many failed attempts. This IP address is temporarily blocked for 10 minutes.'
    );
}

$usernameRaw = $_POST['username'] ?? '';
$usernameRaw = is_string($usernameRaw) ? $usernameRaw : '';

$username = trim($usernameRaw);
$password = $_POST['password'] ?? '';

$captchaInput = strtoupper(loginPostString('captcha_answer'));

// Verify CSRF token.
if (!verifyCsrfToken(loginPostString('csrf_token'))) {
    recordLoginActivity(
        $pdo,
        null,
        'CSRF_REJECTED',
        'Login request rejected because CSRF validation failed. Username: ' . $username,
        $ipAddress,
        $userAgent,
        $usernameRaw
    );

    returnLoginMessage('Invalid request. Please try again.');
}

// Validate required fields before checking the CAPTCHA.
$fieldErrors = [];

if ($username === '') {
    $fieldErrors['username'] = 'Username is required.';
}

if (!is_string($password) || $password === '') {
    $fieldErrors['password'] = 'Password is required.';
}

if ($captchaInput === '') {
    $fieldErrors['captcha'] = 'CAPTCHA is required.';
}

if ($fieldErrors !== []) {
    $_SESSION['login_errors'] = $fieldErrors;
    header('Location: login.php');
    exit;
}
// Consume the login CAPTCHA so it cannot be reused.
$captchaHash = $_SESSION['captcha_answer_hash_login'] ?? null;
unset($_SESSION['captcha_answer_hash_login']);

if (
    !is_string($captchaHash) ||
    !is_string($password) ||
    $captchaInput === '' ||
    !password_verify($captchaInput, $captchaHash)
) {
    recordLoginAttempt(
        $pdo,
        null,
        $usernameRaw,
        $ipAddress,
        $userAgent,
        'CAPTCHA_FAILED'
    );

    // Record the failed CAPTCHA validation.
    recordLoginActivity(
        $pdo,
        null,
        'CAPTCHA_FAILED',
        'CAPTCHA validation failed for username: ' . $username,
        $ipAddress,
        $userAgent,
        $usernameRaw
    );

    returnLoginMessage('Invalid username or password.');
}

// Validate input lengths and format.
if (
    strlen($username) < 8 ||
    strlen($username) > 19 ||
    preg_match('/^[A-Za-z][A-Za-z0-9]{7,18}$/', $username) !== 1 ||
    strlen($password) < 8 ||
    strlen($password) > 64
) {
    recordLoginAttempt(
        $pdo,
        null,
        $usernameRaw,
        $ipAddress,
        $userAgent,
        'FAILED'
    );

    recordLoginActivity(
        $pdo,
        null,
        'LOGIN_FAILED',
        'Login failed because the username or password format was invalid.',
        $ipAddress,
        $userAgent,
        $usernameRaw
    );
}

try {
    $pdo->beginTransaction();

    // Lock the matching account row while checking its login status.
    $stmt = $pdo->prepare(
        'SELECT id_number, username, email, password_hash, role,
                account_status, failed_login_count, lockout_level,
                locked_until
         FROM users
         WHERE username = ?
         LIMIT 1
         FOR UPDATE'
    );

    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        $pdo->commit();

        // Perform a dummy password verification to reduce timing differences.
        password_verify(
            $password,
            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.'
        );

        recordLoginAttempt(
            $pdo,
            null,
            $usernameRaw,
            $ipAddress,
            $userAgent,
            'FAILED'
        );

        // Record the exact username entered, even if the account does not exist.
        recordLoginActivity(
            $pdo,
            null,
            'LOGIN_FAILED',
            'A login attempt failed for username: ' . $username,
            $ipAddress,
            $userAgent,
            $usernameRaw
        );

        returnLoginMessage('Invalid username or password.');
    }

    $userId = (string) $user['id_number'];

    // Check whether this account is currently locked.
    if (!empty($user['locked_until'])) {
        $lockUntil = strtotime((string) $user['locked_until']);

        if ($lockUntil !== false && $lockUntil > time()) {
            $remaining = $lockUntil - time();

            $_SESSION['login_lockout_until'] = time() + $remaining;

            $pdo->commit();

            recordLoginAttempt(
                $pdo,
                $userId,
                $usernameRaw,
                $ipAddress,
                $userAgent,
                'FAILED'
            );

            returnLoginMessage(
                'Account temporarily locked. Please try again in '
                . $remaining . ' seconds.'
            );
        }
    }

    unset($_SESSION['login_lockout_until']);

    // Verify the password and account status.
    $passwordCorrect = password_verify(
        $password,
        (string) $user['password_hash']
    );

    $accountActive = $user['account_status'] === 'active';

    if (!$passwordCorrect || !$accountActive) {
        $failedCount = (int) $user['failed_login_count'] + 1;
        $_SESSION['show_forgot_password'] = ($failedCount >= 3);
        $lockoutLevel = (int) $user['lockout_level'];
        $lockSeconds = 0;

        // Seed D1 = 5: progressive lockout of 20, 40, and 80 seconds.
        $lockoutDurations = [20, 40, 80];

        if ($failedCount >= 3 && $failedCount % 3 === 0) {
            $lockoutLevel = min(3, intdiv($failedCount, 3));
            $lockSeconds = $lockoutDurations[$lockoutLevel - 1];
        }

        $lockedUntil = $lockSeconds > 0
            ? date('Y-m-d H:i:s', time() + $lockSeconds)
            : null;

        $update = $pdo->prepare(
            'UPDATE users
             SET failed_login_count = ?,
                 lockout_level = ?,
                 locked_until = ?
             WHERE id_number = ?'
        );

        $update->execute([
            $failedCount,
            $lockoutLevel,
            $lockedUntil,
            $userId
        ]);

        recordLoginAttempt(
            $pdo,
            $userId,
            $usernameRaw,
            $ipAddress,
            $userAgent,
            'FAILED'
        );

        recordLoginActivity(
            $pdo,
            $userId,
            'LOGIN_FAILED',
            'A login attempt failed.',
            $ipAddress,
            $userAgent,
            $usernameRaw
        );

        // Record the lockout tier when a new lockout is activated.
        if ($lockSeconds > 0) {
            $lockoutEvent = 'LOCKOUT_TIER_' . $lockoutLevel;

            recordLoginActivity(
                $pdo,
                $userId,
                $lockoutEvent,
                'Account temporarily locked for '
                    . $lockSeconds . ' seconds after '
                    . $failedCount . ' failed login attempts.',
                $ipAddress,
                $userAgent
            );
        }

        $pdo->commit();

        // Send a security alert when the account reaches a new tier.
        sendFailedLoginAlert(
            $pdo,
            $user,
            $failedCount,
            $ipAddress,
            $userAgent
        );

        if ($lockSeconds > 0) {
            $_SESSION['login_lockout_until'] = time() + $lockSeconds;

            returnLoginMessage(
                'Account temporarily locked. Please try again in '
                . $lockSeconds . ' seconds.'
            );
        }
        $attemptsRemaining = 3 - ($failedCount % 3);

        returnLoginMessage(
            'Incorrect username or password. '
            . $attemptsRemaining
            . ' attempt(s) remaining before temporary account lockout.'
        );
    }

    // Password is correct. Generate the login OTP.
    $otp = (string) random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + 300);

    // Invalidate any previous unused login OTP.
    $invalidate = $pdo->prepare(
        "UPDATE email_otps
         SET used_at = NOW()
         WHERE user_id = ?
           AND purpose = 'login'
           AND used_at IS NULL"
    );

    $invalidate->execute([$userId]);

    // Save the new hashed OTP.
    $insertOtp = $pdo->prepare(
        "INSERT INTO email_otps
         (user_id, otp_hash, purpose, expires_at, attempts)
         VALUES (?, ?, 'login', ?, 0)"
    );

    $insertOtp->execute([
        $userId,
        $otpHash,
        $expiresAt
    ]);

    recordLoginActivity(
        $pdo,
        $userId,
        'LOGIN_OTP_REQUESTED',
        'Correct password. Login OTP requested.',
        $ipAddress,
        $userAgent
    );

    $pdo->commit();

    // Send the OTP to the registered email address.
    try {
        $mail = createMailer();
        $mail->addAddress((string) $user['email']);
        $mail->isHTML(true);
        $mail->Subject = 'Your Login Verification Code';

        $expiryTime = date('g:i A', strtotime($expiresAt));
        $mail->Body =
            '<h2>Barangay Document Request and Tracking System</h2>'
            . '<p>Your log-in code is:</p>'
            . '<h2>' . e($otp) . '</h2>'
            . '<p>This code expires at ' . e($expiryTime)
            . ' Philippine time (in 5 minutes).</p>'
            . '<p>If you did not request this code, ignore this email and consider changing your password.</p>';

        $mail->AltBody =
            "Barangay Document Request and Tracking System\n"
            . "Your log-in code is: {$otp}.\n"
            . "This code expires at {$expiryTime} Philippine time (in 5 minutes).\n"
            . "If you did not request this code, ignore this email and consider changing your password.";

        $mail->send();
        // Record successful login OTP email delivery.
        recordLoginActivity(
            $pdo,
            $userId,
            'OTP_SENT',
            'Login verification OTP email sent.',
            $ipAddress,
            $userAgent
        );
    } catch (Throwable $mailError) {
        error_log('Login OTP email error: ' . $mailError->getMessage());

        // Invalidate the OTP if email delivery fails.
        $cancel = $pdo->prepare(
            "UPDATE email_otps
             SET used_at = NOW()
             WHERE user_id = ?
               AND purpose = 'login'
               AND used_at IS NULL"
        );

        $cancel->execute([$userId]);

        returnLoginMessage(
            'Unable to send the verification code. Please try again.'
        );
    }

    // Store only a pending login. This is NOT an authenticated session.
    $_SESSION['pending_login_user_id'] = $userId;
    $_SESSION['pending_login_created_at'] = time();

    header('Location: verify_login.php');
    exit;

} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Login processing error: ' . $error->getMessage());

    returnLoginMessage('Unable to process your login. Please try again.');
}
