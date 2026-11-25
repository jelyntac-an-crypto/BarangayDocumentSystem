<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/layout.php';


// Generate CSRF token.
$csrfToken = generateCsrfToken();

// Retrieve login messages.
$errors = $_SESSION['login_errors'] ?? [];
$notice = $_SESSION['login_notice'] ?? '';

if ($notice === '') {
    $notice = $_SESSION['password_reset_message'] ?? '';
}

$lockoutUntil = (int) ($_SESSION['login_lockout_until'] ?? 0);
$lockoutRemaining = max(0, $lockoutUntil - time());

// Load any active IP block so the login page can show its real remaining time.
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$stmt = $pdo->prepare(
    'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at))
     FROM ip_blocks
     WHERE ip_address = ? AND expires_at > NOW()
     ORDER BY expires_at DESC
     LIMIT 1'
);
$stmt->execute([$ipAddress]);
$ipBlockRemaining = max(0, (int) ($stmt->fetchColumn() ?: 0));

$activeLockoutType = $ipBlockRemaining > 0
    ? 'ip'
    : ($lockoutRemaining > 0 ? 'account' : '');
$activeLockoutRemaining = $ipBlockRemaining > 0
    ? $ipBlockRemaining
    : $lockoutRemaining;
if ($lockoutRemaining <= 0) {
    unset(
        $_SESSION['login_lockout_until'],
        $_SESSION['show_forgot_password']
    );
}

$showForgotPassword =
    !empty($_SESSION['show_forgot_password'])
    && $activeLockoutType === 'account'
    && $activeLockoutRemaining > 0;

unset($_SESSION['login_errors'], $_SESSION['login_notice'], $_SESSION['password_reset_message']);

// Escape output.
function loginEscape(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - Barangay Document Request and Tracking System</title>

    <link rel="stylesheet" href="../../css/layout.css">
    <link rel="stylesheet" href="../../css/auth/login.css">
</head>
<body class="auth-page">

    <?php renderSiteHeader('login', '../../'); ?>

    <main class="login-container">

        <section class="login-card">

            <p class="login-kicker">Secure Account Login</p>

            <h2>Log In</h2>

            <p class="login-description">
                Enter your username and password to access your account.
            </p>

            <?php if ($notice !== '' || $activeLockoutRemaining > 0): ?>
                <p
                    id="lockout-message"
                    class="notice-message <?= $activeLockoutRemaining > 0 ? 'lockout-message' : '' ?>"
                    data-remaining="<?= $activeLockoutRemaining ?>"
                    data-lockout-type="<?= loginEscape($activeLockoutType) ?>"
                    role="status"
                    aria-live="polite"
                >
                    <?= $activeLockoutRemaining > 0
                        ? ($activeLockoutType === 'ip'
                            ? 'This IP address is temporarily blocked. Please wait.'
                            : loginEscape(
                            $notice !== ''
                                ? $notice
                                : 'Account temporarily locked. Please wait.'
                        ))
                        : loginEscape($notice) ?>
                </p>
            <?php endif; ?>

            <form
                id="login-form"
                action="login_process.php"
                method="POST"
                autocomplete="on"
                novalidate
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= loginEscape($csrfToken) ?>"
                >

                <div class="form-group">
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

                    <p id="username-error" class="field-error" aria-live="polite">
                        <?= loginEscape($errors['username'] ?? '') ?>
                    </p>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>

                    <div class="password-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            maxlength="64"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            id="toggle-password"
                            aria-label="Show password"
                        >
                            Show
                        </button>
                    </div>

                    <p id="password-error" class="field-error" aria-live="polite">
                        <?= loginEscape($errors['password'] ?? '') ?>
                    </p>
                </div>

                <div class="form-group captcha">
                    <p class="group-title">Human Verification</p>

                    <p class="hint">
                        Enter the six characters shown in the image.
                    </p>

                    <div class="captcha-container">
                        <img
                            src="captcha.php?purpose=login"
                            id="captcha-image"
                            alt="Login CAPTCHA verification image"
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

                    <label for="captcha_answer">
                        Enter CAPTCHA
                    </label>

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

                    <p id="captcha-error" class="field-error" aria-live="polite">
                        <?= loginEscape($errors['captcha'] ?? '') ?>
                    </p>
                </div>

                <button
                    type="submit"
                    id="login-button"
                    class="login-button"
                >
                    Log In
                </button>

            </form>

            <div class="login-links">
                <a
                    href="forgot_password.php"
                    id="forgot-password-link"
                    <?= $showForgotPassword ? '' : 'hidden' ?>
                >
                    Forgot Password? Reset Here
                </a>

                <p>
                    Don't have an account?
                    <a href="register.php" id="register-link">Please register here</a>
                </p>
            </div>

        </section>

    </main>

    <?php renderSiteFooter(); ?>

    <script src="../../js/auth/login.js"></script>

</body>
</html>