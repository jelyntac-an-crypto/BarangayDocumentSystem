<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Generate a CSRF token for the current session.
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// Verify a submitted CSRF token.
function verifyCsrfToken(?string $submittedToken): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!is_string($submittedToken) || $sessionToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $submittedToken);
}

// Validate a Philippine mobile number.
function isValidPhilippineMobile(string $number): bool
{
    return preg_match('/^09[0-9]{9}$/', $number) === 1;
}

// Validate Purok / Street.
function isValidPurokStreet(string $value): bool
{
    return strlen($value) >= 3
        && strlen($value) <= 50
        && preg_match('/^[A-Za-z0-9]+(?: [A-Za-z0-9]+)*$/', $value) === 1;
}

// Validate a username.
function isValidUsername(string $username): bool
{
    $length = strlen($username);

    return $length >= 8
        && $length <= 19
        && preg_match('/^[a-zA-Z0-9_]+$/', $username) === 1;
}

// Validate a password length.
function isValidPasswordLength(string $password): bool
{
    $length = strlen($password);

    return $length >= 8 && $length <= 64;
}

// Check whether a person's name follows the required format.
function isValidPersonName(string $name, bool $required = true): bool
{
    return getPersonNameError($name, 'Name', $required) === null;
}

// Return the specific validation error for a person's name.
function getPersonNameError(
    string $name,
    string $field,
    bool $required = true
): ?string {

    if ($name === '') {
        return $required ? $field . ' is required.' : null;
    }

    if (strlen($name) > 28) {
        return $field . ' must not exceed 28 characters.';
    }

    if (strlen($name) < 2) {
        return $field . ' must contain at least 2 letters.';
    }

    if (preg_match('/[0-9]/', $name)) {
        return 'Numbers are not allowed in ' . $field . '.';
    }

    if (preg_match('/[^A-Za-z ]/', $name)) {
        return 'Special characters are not allowed in ' . $field . '.';
    }

    if (preg_match('/ {2,}/', $name)) {
        return 'Double space is not allowed.';
    }

    if (preg_match('/(.)\1\1/i', $name)) {
        return 'Three consecutive identical letters are not allowed.';
    }

    if (preg_match('/^[a-z]/', $name)) {
        return 'First letter of your name must be capital.';
    }

    if (preg_match('/^[A-Z ]+$/', $name)) {
        return 'Do not use all capital letters.';
    }

    if (preg_match('/^[A-Z][a-z]*(?: [A-Z][a-z]*)*$/D', $name) !== 1) {
        return 'Use Title Case for each part of your name.';
    }

    return null;
}

// Validate a custom Roman numeral name extension.
function isValidCustomNameExtension(string $extension): bool
{
    return in_array($extension, ['I', 'II', 'III', 'IV', 'IX', 'X'], true);
}

// Record security activity using one consistent database format.
function recordActivity(
    PDO $pdo,
    ?string $userId,
    string $eventType,
    string $description,
    string $ipAddress,
    string $userAgent = '',
    array $eventData = [],
    ?string $usernameTyped = null
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO activity_logs
            (user_id, username_typed, event_type, description,
             ip_address, user_agent, event_data)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $userId,
        $usernameTyped === null ? null : substr($usernameTyped, 0, 255),
        $eventType,
        substr($description, 0, 500),
        $ipAddress,
        substr($userAgent, 0, 500),
        $eventData === []
            ? null
            : json_encode($eventData, JSON_THROW_ON_ERROR)
    ]);
}


// Check whether an IP address currently has an active block.
function isIpTemporarilyBlocked(PDO $pdo, string $ipAddress): bool
{
    $stmt = $pdo->prepare(
        'SELECT id
         FROM ip_blocks
         WHERE ip_address = ?
           AND expires_at > NOW()
         LIMIT 1'
    );

    $stmt->execute([$ipAddress]);

    return $stmt->fetchColumn() !== false;
}

// Block an IP after 16 failed authentication attempts within 10 minutes.
function applyIpFailureBlock(
    PDO $pdo,
    string $ipAddress,
    ?string $userId = null,
    string $userAgent = ''
): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM login_attempts
         WHERE ip_address = ?
           AND attempt_status IN ('FAILED', 'CAPTCHA_FAILED', 'OTP_FAILED')
           AND attempted_at >= NOW() - INTERVAL 10 MINUTE"
    );

    $stmt->execute([$ipAddress]);
    $failedCount = (int) $stmt->fetchColumn();

    if ($failedCount < 16) {
        return false;
    }

    // Do not repeatedly extend an existing active block.
    if (isIpTemporarilyBlocked($pdo, $ipAddress)) {
        return true;
    }

    // Create a 10-minute IP block.
    $stmt = $pdo->prepare(
        "INSERT INTO ip_blocks
            (ip_address, reason, blocked_at, expires_at)
         VALUES (
            ?,
            '16 failed authentication attempts within 10 minutes',
            NOW(),
            DATE_ADD(NOW(), INTERVAL 10 MINUTE)
         )
         ON DUPLICATE KEY UPDATE
            reason = VALUES(reason),
            blocked_at = NOW(),
            expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)"
    );

    $stmt->execute([$ipAddress]);

    // Record the IP-blocking event using the centralized helper.
    recordActivity(
       $pdo,
       $userId,
       'IP_BLOCKED',
       'IP address blocked after 16 failed authentication attempts within 10 minutes.',
       $ipAddress,
       $userAgent,
       [
        'failed_attempts' => $failedCount,
        'window_minutes' => 10,
        'block_minutes' => 10
       ]
    );

    return true;
}

