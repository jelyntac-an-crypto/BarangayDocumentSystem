<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/mailer.php';
require_once dirname(__DIR__) . '/layout.php';

// Verify the one-time password reset session.
$userId = $_SESSION['verified_password_reset_user_id'] ?? null;
$verifiedAt = $_SESSION['verified_password_reset_created_at'] ?? null;
$sessionUsername = $_SESSION['password_recovery_username'] ?? null;

if (
    !is_string($userId) ||
    preg_match('/^[0-9]{4}-[0-9]{4}$/D', $userId) !== 1 ||
    !is_int($verifiedAt) ||
    (time() - $verifiedAt) > 600 ||
    $verifiedAt > time()
) {
    unset(
        $_SESSION['verified_password_reset_user_id'],
        $_SESSION['verified_password_reset_created_at']
    );

    header('Location: forgot_password.php');
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    http_response_code(405);
    exit('Method not allowed.');
}

$message = '';

function getPasswordStrength(string $password): array
{
    $criteria = [
        preg_match('/[a-z]/', $password) === 1,
        preg_match('/[A-Z]/', $password) === 1,
        preg_match('/[0-9]/', $password) === 1,
        preg_match('/[^a-zA-Z0-9]/', $password) === 1
    ];

    $count = count(array_filter($criteria));

    if (strlen($password) < 8 || strlen($password) > 64 || $count <= 2) {
        return ['Weak', $count];
    }

    if ($count === 3) {
        return ['Medium', $count];
    }

    return ['Strong', $count];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_string($csrfToken) || !verifyCsrfToken($csrfToken)) {
        http_response_code(403);
        exit('Invalid request.');
    }

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!is_string($password) || !is_string($confirmPassword)) {

        $message = 'Invalid password input.';

    } elseif ($password !== $confirmPassword) {

        $message = 'Mismatch Password.';

    } else {

        [$strength, $criteriaCount] = getPasswordStrength($password);

        if (strlen($password) < 8 || strlen($password) > 64) {

            $message = 'Password must be 8 to 64 characters long.';

        } elseif ($strength === 'Weak') {

            $message =
                'Password is too weak. Use at least three of the following: '
                . 'lowercase letters, uppercase letters, numbers, '
                . 'and special characters.';

        } else {

            try {

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                if ($passwordHash === false) {
                    throw new RuntimeException('Password hashing failed.');
                }

                $pdo->beginTransaction();

                // Lock the account before checking password history.
                $stmt = $pdo->prepare(
                    "SELECT id_number, username, email, password_hash
                     FROM users
                     WHERE id_number = ?
                       AND account_status = 'active'
                     FOR UPDATE"
                );

                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                if (!$user) {
                    throw new RuntimeException('Account is unavailable.');
                }

                // Get the three most recent saved password hashes.
                $stmt = $pdo->prepare(
                    'SELECT password_hash
                     FROM password_history
                     WHERE user_id = ?
                     ORDER BY created_at DESC, id DESC
                     LIMIT 3
                     FOR UPDATE'
                );

                $stmt->execute([$userId]);
                $recentPasswords = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $reusedPassword = false;

                foreach ($recentPasswords as $oldHash) {
                    if (password_verify($password, (string) $oldHash)) {
                        $reusedPassword = true;
                        break;
                    }
                }

                // Also check the current password in case history is incomplete.
                if (
                    !$reusedPassword &&
                    password_verify($password, (string) $user['password_hash'])
                ) {
                    $reusedPassword = true;
                }

                if ($reusedPassword) {

                    $pdo->rollBack();

                    $message = 'You cannot reuse a recent password.';

                } else {

                    // Update the account password and clear login lockout.
                    $stmt = $pdo->prepare(
                        'UPDATE users
                         SET password_hash = ?,
                             failed_login_count = 0,
                             lockout_level = 0,
                             locked_until = NULL,
                             updated_at = NOW()
                         WHERE id_number = ?'
                    );

                    $stmt->execute([$passwordHash, $userId]);

                    // Save the new password in password history.
                    $stmt = $pdo->prepare(
                        'INSERT INTO password_history
                         (user_id, password_hash)
                         VALUES (?, ?)'
                    );

                    $stmt->execute([$userId, $passwordHash]);

                    // Keep only the three most recent password records.
                    $stmt = $pdo->prepare(
                        'SELECT id
                         FROM password_history
                         WHERE user_id = ?
                         ORDER BY created_at DESC, id DESC'
                    );

                    $stmt->execute([$userId]);
                    $historyIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    $oldIds = array_slice($historyIds, 3);

                    if ($oldIds) {
                        $placeholders = implode(
                            ',',
                            array_fill(0, count($oldIds), '?')
                        );

                        $delete = $pdo->prepare(
                            "DELETE FROM password_history
                             WHERE user_id = ?
                               AND id IN ($placeholders)"
                        );

                        $delete->execute(array_merge([$userId], $oldIds));
                    }

                    // Clear previous failed-login alert tracking.
                    $stmt = $pdo->prepare(
                        'DELETE FROM security_alerts WHERE user_id = ?'
                    );

                    $stmt->execute([$userId]);

                    // Invalidate any remaining password reset OTPs.
                    $stmt = $pdo->prepare(
                        "UPDATE email_otps
                         SET used_at = NOW()
                         WHERE user_id = ?
                           AND purpose = 'password_reset'
                           AND used_at IS NULL"
                    );

                    $stmt->execute([$userId]);

                    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

                    $userAgent = substr(
                        $_SERVER['HTTP_USER_AGENT'] ?? '',
                        0,
                        500
                    );

                    // Record the successful password reset.
                    recordActivity(
                        $pdo,
                        $userId,
                        'PASSWORD_CHANGED',
                        'User successfully reset their password.',
                        $ipAddress,
                        $userAgent,
                        [],
                        is_string($sessionUsername) ? $sessionUsername : null
                    );

                    $pdo->commit();

                    /*
                     * Consume the verified reset session immediately.
                     * A reload or Back action cannot reuse this authorization.
                     */
                    unset(
                        $_SESSION['verified_password_reset_user_id'],
                        $_SESSION['verified_password_reset_created_at'],
                        $_SESSION['show_forgot_password'],
                        $_SESSION['login_lockout_until']
                    );

                    session_regenerate_id(true);

                    // Send the password change notification.
                    try {

                        $dateTime =
                            date('F j, Y h:i:s A') . ' Philippine Time';

                        $mail = createMailer();
                        $mail->addAddress((string) $user['email']);
                        $mail->isHTML(true);

                        $mail->Subject = 'Your password was changed';

                        $safeDateTime = e($dateTime);
                        $safeIp = e($ipAddress);
                        $safeUserAgent = e($userAgent);

                        $mail->Body =
                            '<h2>Barangay Document Request and Tracking System</h2>'
                            . '<p>Your account password was successfully changed.</p>'
                            . '<p><strong>Date and time:</strong> '
                            . $safeDateTime . '</p>'
                            . '<p><strong>IP address:</strong> '
                            . $safeIp . '</p>'
                            . '<p><strong>Browser/device:</strong> '
                            . $safeUserAgent . '</p>'
                            . '<p>If this was not you, contact your system administrator immediately.</p>';

                        $mail->AltBody =
                            "Your account password was successfully changed.\n"
                            . "Date and time: {$dateTime}\n"
                            . "IP address: {$ipAddress}\n"
                            . "Browser/device: {$userAgent}\n"
                            . "If this was not you, contact your system administrator immediately.";

                        $mail->send();

                        recordActivity(
                            $pdo,
                            $userId,
                            'ALERT_EMAIL_SENT',
                            'Password change notification email sent.',
                            $ipAddress,
                            $userAgent,
                            ['alert_type' => 'password_changed'],
                            is_string($sessionUsername) ? $sessionUsername : null
                            
                        );

                    } catch (Throwable $mailError) {

                        error_log(
                            'Password change notification failed: '
                            . $mailError->getMessage()
                        );
                    }

                    $_SESSION['password_reset_message'] =
                        'Successfully Change Password.';

                    header('Location: login.php');
                    exit;
                }

            } catch (Throwable $error) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'Password reset error: ' . $error->getMessage()
                );

                $message = 'Unable to change your password. Please try again.';
            }
        }
    }
}

// Re-check the reset session after POST handling.
if (
    !isset($_SESSION['verified_password_reset_user_id']) ||
    !isset($_SESSION['verified_password_reset_created_at'])
) {
    header('Location: forgot_password.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Barangay Document Portal</title>
    <link rel="stylesheet" href="../../css/layout.css">
    <link rel="stylesheet" href="../../css/auth/login.css">
    <link rel="stylesheet" href="../../css/auth/reset-password.css">
</head>
<body class="auth-page">

    <?php renderSiteHeader('login', '../../'); ?>

    <main class="login-container">
        <section class="login-card reset-password-card">
            <p class="login-kicker">Secure Account Recovery</p>
            <h2>Choose a New Password</h2>
            <p class="login-description">Create a strong password to secure your account.</p>

            <?php if ($message !== ''): ?>
                <p class="notice-message" role="alert" aria-live="polite"><?= e($message) ?></p>
            <?php endif; ?>

            <form method="POST" action="reset_password.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">

                <div class="form-group">
                    <label for="password">Enter Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        maxlength="64"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <div class="reset-strength-group">
                    <p class="reset-strength-label">Password Strength: <strong id="strength-label">Weak</strong></p>
                    <div
                        id="strength-meter"
                        role="progressbar"
                        aria-label="Password strength"
                        aria-valuemin="0"
                        aria-valuemax="4"
                        aria-valuenow="0"
                    >
                        <div id="strength-bar"></div>
                    </div>
                    <p class="reset-password-hint">Use at least three of these: lowercase, uppercase, number, and special character.</p>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Re-enter Password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="8"
                        maxlength="64"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <button class="login-button" type="submit">Change Password</button>
            </form>
        </section>
    </main>

    <?php renderSiteFooter(); ?>

    <script src="../../js/auth/reset_password.js" defer></script>
</body>
</html>
