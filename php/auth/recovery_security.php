<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/mailer.php';

// Record a failed recovery attempt in login_attempts.

function recordRecoveryLoginAttempt(
    PDO $pdo,
    string $userId,
    string $username,
    string $ipAddress,
    string $userAgent
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts
         (user_id, attempted_username, ip_address, user_agent, attempt_status)
         VALUES (?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $userId,
        substr($username, 0, 19),
        $ipAddress,
        $userAgent,
        'FAILED'
    ]);
}

// Record a recovery security activity.
function recordRecoveryActivity(
    PDO $pdo,
    string $userId,
    string $eventType,
    string $description,
    string $ipAddress,
    string $userAgent,
    array $eventData = [],
    ?string $usernameTyped = null
): void {
    recordActivity(
        $pdo,
        $userId,
        $eventType,
        $description,
        $ipAddress,
        $userAgent,
        $eventData,
        $usernameTyped
    );
}

// Send the same progressive security alerts used by login.

function sendRecoveryFailedLoginAlert(
    PDO $pdo,
    array $user,
    int $failedCount,
    string $ipAddress,
    string $userAgent
): void {
    if (!in_array($failedCount, [3, 6, 9], true)) {
        return;
    }

    $tier = intdiv($failedCount, 3);
    $userId = (string) $user['id_number'];

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

    if ($reserve->rowCount() !== 1) {
        return;
    }

    global $config;

    try {
        $appUrl = rtrim((string) $config['app_url'], '/');
        $resetUrl = $appUrl . '/php/auth/forgot_password.php';

        $dateTime = date('F j, Y h:i:s A') . ' Philippine Time';

        $mail = createMailer();
        $mail->addAddress((string) $user['email']);
        $mail->isHTML(true);
        $mail->Subject = 'Failed sign-in attempts on your account';

        $mail->Body =
            '<h2>Security Alert</h2>'
            . '<p>We detected multiple failed sign-in attempts '
            . 'on your account.</p>'
            . '<p><strong>Failed attempts:</strong> '
            . e((string) $failedCount) . '</p>'
            . '<p><strong>Date and time:</strong> '
            . e($dateTime) . '</p>'
            . '<p><strong>IP address:</strong> '
            . e($ipAddress) . '</p>'
            . '<p><strong>Browser/device:</strong> '
            . e($userAgent) . '</p>'
            . '<p>If this was not you, reset your password now:</p>'
            . '<p><a href="' . e($resetUrl) . '">'
            . 'Reset Your Password</a></p>';

        $mail->AltBody =
            "Security Alert\n"
            . "Failed attempts: {$failedCount}\n"
            . "Date and time: {$dateTime}\n"
            . "IP address: {$ipAddress}\n"
            . "Browser/device: {$userAgent}\n\n"
            . "Reset your password: {$resetUrl}";

        $mail->send();

        $update = $pdo->prepare(
            "UPDATE security_alerts
             SET alert_status = 'sent',
                 sent_at = NOW()
             WHERE user_id = ? AND tier = ?"
        );

        $update->execute([$userId, $tier]);

        recordRecoveryActivity(
            $pdo,
            $userId,
            'ALERT_EMAIL_SENT',
            "Tier {$tier} failed sign-in alert email sent.",
            $ipAddress,
            $userAgent,
            ['alert_type' => 'failed_login', 'tier' => $tier]
        );

    } catch (Throwable $error) {
        $failed = $pdo->prepare(
            "UPDATE security_alerts
             SET alert_status = 'failed'
             WHERE user_id = ? AND tier = ?"
        );

        $failed->execute([$userId, $tier]);

        error_log(
            'Recovery security alert email error: '
            . $error->getMessage()
        );
    }
}

// Increment the shared login/recovery failure counter.
//  Returns the current lockout status and remaining time.
 
function recordRecoveryFailure(
    PDO $pdo,
    string $userId,
    string $username,
    string $ipAddress,
    string $userAgent
): array {
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "SELECT id_number, email, failed_login_count,
                    lockout_level, locked_until
             FROM users
             WHERE id_number = ?
               AND account_status = 'active'
             FOR UPDATE"
        );

        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $pdo->rollBack();

            return [
                'locked' => false,
                'remaining' => 0,
                'failed_count' => 0
            ];
        }

        // Do not increment attempts while already locked.
        if (!empty($user['locked_until'])) {
            $lockUntil = strtotime((string) $user['locked_until']);

            if ($lockUntil !== false && $lockUntil > time()) {
                $remaining = $lockUntil - time();

                $pdo->rollBack();

                return [
                    'locked' => true,
                    'remaining' => $remaining,
                    'failed_count' => (int) $user['failed_login_count']
                ];
            }
        }

        $failedCount = (int) $user['failed_login_count'] + 1;
        $lockoutLevel = (int) $user['lockout_level'];
        $lockSeconds = 0;

        // Seed D1 = 5: 20, 40, and 80 seconds.
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

        recordRecoveryLoginAttempt(
            $pdo,
            $userId,
            $username,
            $ipAddress,
            $userAgent
        );

        recordRecoveryActivity(
            $pdo,
            $userId,
            'PASSWORD_RECOVERY_FAILED',
            'Incorrect security answer during password recovery.',
            $ipAddress,
            $userAgent,
            ['failed_count' => $failedCount]
        );

        // Record the lockout tier when a new lockout is activated.
        if ($lockSeconds > 0) {
            $lockoutEvent = 'LOCKOUT_TIER_' . $lockoutLevel;

            recordRecoveryActivity(
                $pdo,
                $userId,
                $lockoutEvent,
                'Account temporarily locked for ' . $lockSeconds .
                    ' seconds after ' . $failedCount .
                    ' failed recovery attempts.',
                $ipAddress,
                $userAgent,
                [
                    'failed_count' => $failedCount,
                    'lockout_level' => $lockoutLevel,
                    'lockout_seconds' => $lockSeconds
                ]
            );
        }

        $pdo->commit();

        // Send the alert after committing the failure record.
        sendRecoveryFailedLoginAlert(
            $pdo,
            $user,
            $failedCount,
            $ipAddress,
            $userAgent
        );

        return [
            'locked' => $lockSeconds > 0,
            'remaining' => $lockSeconds,
            'failed_count' => $failedCount
        ];

    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }
}
