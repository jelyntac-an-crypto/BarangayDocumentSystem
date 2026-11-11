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
