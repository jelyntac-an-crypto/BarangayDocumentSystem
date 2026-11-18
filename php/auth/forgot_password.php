<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/mailer.php';
require_once __DIR__ . '/recovery_security.php';
require_once dirname(__DIR__) . '/layout.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    http_response_code(405);
    exit('Method not allowed.');
}

$questionLabels = [
    'Q1' => 'What was the name of your first pet?',
    'Q4' => "What is your mother's maiden first name?",
    'Q6' => 'What was the first song you learned by heart?'
];

$message = $_SESSION['password_reset_message'] ?? '';
unset($_SESSION['password_reset_message']);

$stage = 'identify';
$showVerificationLink = false;
$selectedQuestions = $questionLabels;

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

// Check whether the user has already completed CAPTCHA.
if (
    ($_SESSION['password_recovery_stage'] ?? '') === 'security'
    && isset(
        $_SESSION['password_recovery_username'],
        $_SESSION['password_recovery_created_at']
    )
) {
    $createdAt = (int) $_SESSION['password_recovery_created_at'];

    if (time() - $createdAt <= 900) {
        $stage = 'security';
    } else {
        unset(
            $_SESSION['password_recovery_stage'],
            $_SESSION['password_recovery_username'],
            $_SESSION['password_recovery_created_at']
        );

        $message = 'Your recovery session expired. Please start again.';
    }
}

// Handle submitted forms.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_string($csrfToken) || !verifyCsrfToken($csrfToken)) {
        $sessionUsername = $_SESSION['password_recovery_username'] ?? null;

        recordActivity(
            $pdo,
            null,
            'CSRF_REJECTED',
            'Invalid CSRF token during password recovery.',
            $ipAddress,
            $userAgent,
            [],
            is_string($sessionUsername) ? $sessionUsername : null
        );

        http_response_code(403);
        exit('Invalid request.');
    }

    $submittedStep = $_POST['step'] ?? '';

    /*
     * STEP 1: Username and CAPTCHA.
     */
    if ($submittedStep === 'identify') {

        // Remove any previous recovery session.
        unset(
            $_SESSION['password_recovery_stage'],
            $_SESSION['password_recovery_username'],
            $_SESSION['password_recovery_created_at'],
            $_SESSION['pending_password_reset_user_id'],
            $_SESSION['pending_password_reset_created_at'],
            $_SESSION['verified_password_reset_user_id'],
            $_SESSION['verified_password_reset_created_at']
        );

        $username = $_POST['username'] ?? '';
        $captchaInput = $_POST['captcha_answer'] ?? '';

        $username = is_string($username) ? trim($username) : '';
        $captchaInput = is_string($captchaInput)
            ? strtoupper(trim($captchaInput))
            : '';

        // CAPTCHA is one-use, even if validation fails.
        $captchaHash = $_SESSION['captcha_answer_hash_forgot_password'] ?? null;
        unset($_SESSION['captcha_answer_hash_forgot_password']);

        $validCaptcha = is_string($captchaHash)
            && preg_match('/^[A-Z0-9]{6}$/', $captchaInput) === 1
            && password_verify($captchaInput, $captchaHash);

        $validUsername = preg_match(
            '/^[A-Za-z][A-Za-z0-9]{7,18}$/',
            $username
        ) === 1;

        if (!$validCaptcha) {
            recordActivity(
                $pdo,
                null,
                'CAPTCHA_FAILED',
                'Incorrect or missing CAPTCHA during password recovery.',
                $ipAddress,
                $userAgent,
                ['reason' => 'incorrect_or_missing'],
                $username
            );

            $message = 'Incorrect CAPTCHA. Please try again.';
        } elseif (!$validUsername) {
            $message = 'Please enter a valid username.';
        } else {
            // Do not reveal whether the username exists.
            $_SESSION['password_recovery_stage'] = 'security';
            $_SESSION['password_recovery_username'] = $username;
            $_SESSION['password_recovery_created_at'] = time();

            session_regenerate_id(true);

            $stage = 'security';
            $message = 'Please answer your registered security question.';
        }
    }

    /*
     * STEP 2: Security question and matching answers.
     */
    elseif ($submittedStep === 'security') {

        $sessionUsername = $_SESSION['password_recovery_username'] ?? null;
        $sessionCreatedAt = $_SESSION['password_recovery_created_at'] ?? null;

        $validRecoverySession =
            ($_SESSION['password_recovery_stage'] ?? '') === 'security'
            && is_string($sessionUsername)
            && is_numeric($sessionCreatedAt)
            && time() - (int) $sessionCreatedAt <= 900;

        if (!$validRecoverySession) {

            unset(
                $_SESSION['password_recovery_stage'],
                $_SESSION['password_recovery_created_at']
            );

            $stage = 'identify';
            $message = 'Your recovery session expired. Please start again.';

        } else {

            $questionCode = $_POST['question_code'] ?? '';
            $answer = $_POST['answer'] ?? '';
            $confirmAnswer = $_POST['confirm_answer'] ?? '';

            $questionCode = is_string($questionCode)
                ? $questionCode
                : '';

            $answer = is_string($answer) ? trim($answer) : '';
            $confirmAnswer = is_string($confirmAnswer)
                ? trim($confirmAnswer)
                : '';

            $stage = 'security';

            // Answers must match before any database/hash verification.
            if ($answer === '' || $confirmAnswer === '') {

                $message = 'Please enter and re-enter your answer.';

            } elseif (!hash_equals($answer, $confirmAnswer)) {

                $message = 'Your answers do not match.';

            } elseif (
                !isset($questionLabels[$questionCode])
                || preg_match(
                    '/^[A-Za-z]+(?: [A-Za-z]+)*$/',
                    $answer
                ) !== 1
                || strlen($answer) < 2
                || strlen($answer) > 50
            ) {

                $message = 'Please enter a valid security answer.';

            } else {

                try {

                    // Find the active account without revealing its existence.
                    $stmt = $pdo->prepare(
                        "SELECT id_number, username, email,
                                failed_login_count, lockout_level,
                                locked_until
                         FROM users
                         WHERE username = ?
                           AND account_status = 'active'
                         LIMIT 1"
                    );

                    $stmt->execute([$sessionUsername]);
                    $user = $stmt->fetch();

                    $answerCorrect = false;

                    if ($user) {

                        // Check whether the account is currently locked.
                        if (!empty($user['locked_until'])) {

                            $lockUntil = strtotime(
                                (string) $user['locked_until']
                            );

                            if ($lockUntil !== false && $lockUntil > time()) {

                                $remaining = $lockUntil - time();

                                $message =
                                    'Your account is temporarily locked. '
                                    . 'Please try again after '
                                    . $remaining
                                    . ' seconds.';
                            }
                        }

                        // Verify only if the account is not locked.
                        if ($message === '') {

                            $stmt = $pdo->prepare(
                                'SELECT answer_hash
                                 FROM security_answers
                                 WHERE user_id = ?
                                   AND question_code = ?
                                 LIMIT 1'
                            );

                            $stmt->execute([
                                (string) $user['id_number'],
                                $questionCode
                            ]);

                            $storedAnswer = $stmt->fetch();

                            if (
                                $storedAnswer
                                && password_verify(
                                    $answer,
                                    (string) $storedAnswer['answer_hash']
                                )
                            ) {
                                $answerCorrect = true;
                            } else {

                                // Wrong answer uses the shared login lockout.
                                $failure = recordRecoveryFailure(
                                    $pdo,
                                    (string) $user['id_number'],
                                    $sessionUsername,
                                    $ipAddress,
                                    $userAgent
                                );

                                if ($failure['locked']) {

                                    $message =
                                        'Too many failed attempts. '
                                        . 'Your account is temporarily locked for '
                                        . $failure['remaining']
                                        . ' seconds.';

                                } else {

                                    $message =
                                        'Unable to verify the information. '
                                        . 'Please check your answer and try again.';
                                }
                            }
                        }

                    } else {

                        // Same general response for unknown usernames.
                        $message =
                            'Unable to verify the information. '
                            . 'Please check your answer and try again.';
                    }

                    /*
                     * STEP 3: Correct answer.
                     * Generate and email the password reset OTP.
                     */
                    if ($answerCorrect && $user) {

                        $otp = (string) random_int(100000, 999999);
                        $otpHash = password_hash($otp, PASSWORD_DEFAULT);

                        if ($otpHash === false) {
                            throw new RuntimeException('OTP hashing failed.');
                        }

                        $pdo->beginTransaction();

                        try {

                            // Invalidate previous unused reset codes.
                            $stmt = $pdo->prepare(
                                "UPDATE email_otps
                                 SET used_at = NOW()
                                 WHERE user_id = ?
                                   AND purpose = 'password_reset'
                                   AND used_at IS NULL"
                            );

                            $stmt->execute([
                                (string) $user['id_number']
                            ]);

                            // Create the new OTP with a 5-minute expiry.
                            $stmt = $pdo->prepare(
                                "INSERT INTO email_otps
                                 (user_id, otp_hash, purpose, expires_at)
                                 VALUES (?, ?, 'password_reset',
                                         DATE_ADD(NOW(), INTERVAL 5 MINUTE))"
                            );

                            $stmt->execute([
                                (string) $user['id_number'],
                                $otpHash
                            ]);

                            $otpId = (int) $pdo->lastInsertId();

                            recordRecoveryActivity(
                                $pdo,
                                (string) $user['id_number'],
                                'RESET_REQUESTED',
                                'User passed security verification and requested a password reset.',
                                $ipAddress,
                                $userAgent,
                                [],
                                $sessionUsername
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

                            $mail->Subject =
                                'Password Reset Verification Code';

                            $mail->Body =
                                '<h2>Barangay Document Request and Tracking System</h2>'
                                . '<p>Your password reset verification code is:</p>'
                                . '<h1>' . e($otp) . '</h1>'
                                . '<p>This code expires in 5 minutes.</p>'
                                . '<p>If you did not request this code, please ignore this email.</p>';

                            $mail->AltBody =
                                "Your password reset verification code is: {$otp}. "
                                . "This code expires in 5 minutes. "
                                . "If you did not request this code, ignore this email.";

                            $mail->send();

                            // Record successful password reset OTP email delivery.
                            recordActivity(
                                $pdo,
                                (string) $user['id_number'],
                                'OTP_SENT',
                                'Password reset verification OTP email sent.',
                                $ipAddress,
                                $userAgent,
                                [],
                                $sessionUsername
                            );

                            // Grant access to OTP verification only after email succeeds.
                            session_regenerate_id(true);

                            $_SESSION['pending_password_reset_user_id'] =
                                (string) $user['id_number'];

                            $_SESSION['pending_password_reset_created_at'] =
                                time();

                            unset(
                                $_SESSION['password_recovery_stage'],
                                $_SESSION['password_recovery_created_at']
                            );

                            $stage = 'complete';
                            $showVerificationLink = true;

                            $message =
                                'If the information is valid, a reset code '
                                . 'has been sent to the registered email address.';

                        } catch (Throwable $mailError) {

                            // Invalidate the OTP if delivery fails.
                            $stmt = $pdo->prepare(
                                'UPDATE email_otps
                                 SET used_at = NOW()
                                 WHERE id = ?'
                            );

                            $stmt->execute([$otpId]);

                            error_log(
                                'Password reset OTP email failed: '
                                . $mailError->getMessage()
                            );

                            $message =
                                'Unable to send the reset code. '
                                . 'Please try again later.';
                        }
                    }

                } catch (Throwable $error) {

                    error_log(
                        'Password recovery verification error: '
                        . $error->getMessage()
                    );

                    $message =
                        'Unable to process your request. Please try again later.';
                }
            }
        }
    }

    else {
        http_response_code(400);
        exit('Invalid request.');
    }
}

// Load the question choices for the current recovery username.
// All registered users normally have Q1, Q4, and Q6.
// For an unknown username, the same choices are displayed.
if ($stage === 'security') {

    $sessionUsername = $_SESSION['password_recovery_username'] ?? '';

    try {

        $stmt = $pdo->prepare(
            "SELECT sa.question_code
             FROM security_answers sa
             INNER JOIN users u ON u.id_number = sa.user_id
             WHERE u.username = ?
               AND u.account_status = 'active'
             ORDER BY FIELD(sa.question_code, 'Q1', 'Q4', 'Q6')"
        );

        $stmt->execute([$sessionUsername]);
        $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($codes) {

            $selectedQuestions = [];

            foreach ($codes as $code) {
                if (isset($questionLabels[$code])) {
                    $selectedQuestions[$code] = $questionLabels[$code];
                }
            }
        }

    } catch (Throwable $error) {

        error_log(
            'Password recovery question loading error: '
            . $error->getMessage()
        );

        $selectedQuestions = $questionLabels;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Forgot Password</title>

    <link rel="stylesheet" href="../../css/layout.css">
    <link rel="stylesheet" href="../../css/auth/verify_registration.css">
</head>
<body>

    <?php renderSiteHeader('login', '../../'); ?>

    <div class="verification-container">

        <h2>Forgot Password</h2>

        <?php if ($message !== ''): ?>
            <p role="status"><?= e($message) ?></p>
        <?php endif; ?>

        <?php if ($stage === 'identify'): ?>

            <p>
                Enter your username and solve the CAPTCHA
                to continue password recovery.
            </p>

            <form method="POST" action="forgot_password.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(generateCsrfToken()) ?>"
                >

                <input type="hidden" name="step" value="identify">

                <label for="username">Username</label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    minlength="8"
                    maxlength="19"
                    pattern="[A-Za-z][A-Za-z0-9]{7,18}"
                    autocomplete="username"
                    required
                >

                <p>Enter the six characters shown in the image.</p>

                <div class="captcha-container">

                    <img
                        src="captcha.php?purpose=forgot_password"
                        id="captcha-image"
                        alt="Forgot Password CAPTCHA verification image"
                        width="190"
                        height="65"
                    >

                    <button
                        type="button"
                        id="refresh-captcha"
                    >
                        Refresh CAPTCHA
                    </button>

                </div>

                <label for="captcha_answer">Enter CAPTCHA</label>

                <input
                    type="text"
                    id="captcha_answer"
                    name="captcha_answer"
                    minlength="6"
                    maxlength="6"
                    pattern="[A-Za-z0-9]{6}"
                    autocomplete="off"
                    required
                >

                <button type="submit">Continue</button>

            </form>

        <?php elseif ($stage === 'security'): ?>

            <p>
                Choose the following question and enter your answer.
            </p>

            <form method="POST" action="forgot_password.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(generateCsrfToken()) ?>"
                >

                <input type="hidden" name="step" value="security">

                <label for="question_code">
                    Choose the following question
                </label>

                <select
                    id="question_code"
                    name="question_code"
                    required
                >
                    <option value="">Select a security question</option>

                    <?php foreach ($selectedQuestions as $code => $label): ?>
                        <option value="<?= e($code) ?>">
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>

                </select>

                <label for="answer">Your Answer</label>

                <input
                    type="text"
                    id="answer"
                    name="answer"
                    minlength="2"
                    maxlength="50"
                    pattern="[A-Za-z]+( [A-Za-z]+)*"
                    autocomplete="off"
                    required
                >

                <label for="confirm_answer">Re-enter Answer</label>

                <input
                    type="text"
                    id="confirm_answer"
                    name="confirm_answer"
                    minlength="2"
                    maxlength="50"
                    pattern="[A-Za-z]+( [A-Za-z]+)*"
                    autocomplete="off"
                    required
                >

                <button type="submit">Verify Answer</button>

            </form>

        <?php elseif ($stage === 'complete' && $showVerificationLink): ?>

            <p>
                Continue to OTP verification to complete your password recovery.
            </p>

            <a href="verify_password_reset.php">
                Continue to OTP Verification
            </a>

        <?php endif; ?>

        <p>
            <a href="login.php">Back to Login</a>
        </p>

    </div>

    <?php renderSiteFooter(); ?>

    <script>
        const captchaImage = document.getElementById('captcha-image');
        const refreshButton = document.getElementById('refresh-captcha');
        const captchaInput = document.getElementById('captcha_answer');

        if (captchaImage && refreshButton) {
            refreshButton.addEventListener('click', function () {
                captchaImage.src =
                    'captcha.php?purpose=forgot_password&t=' + Date.now();

                if (captchaInput) {
                    captchaInput.value = '';
                }
            });
        }
    </script>

</body>
</html>
