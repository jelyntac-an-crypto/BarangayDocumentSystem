<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';

// Generate a CSRF token for form protection.
$csrfToken = generateCsrfToken();

// Retrieve validation errors and previously submitted safe values.
$errors = $_SESSION['registration_errors'] ?? [];
$oldInput = $_SESSION['registration_old_input'] ?? [];

// Clear them after retrieving so they are displayed only once.
unset(
    $_SESSION['registration_errors'],
    $_SESSION['registration_old_input']
);

// Safely retrieve an old input value.
function oldValue(array $oldInput, string $field): string
{
    return e($oldInput[$field] ?? '');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Registration</title>

    <link rel="stylesheet" href="../../css/auth/register.css">
</head>

<body>

    <p class="system-name">
        Barangay Document Request and Tracking System
    </p>

    <header>
        <nav>
            <a href="../../index.php">Home</a> |
            <a href="login.php">Log-in</a>
        </nav>
    </header>

    <h1>Resident Registration</h1>

    <form method="POST" action="register_process.php">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($csrfToken) ?>"
        >

        <h2>Account Information</h2>

        <label for="id_number">ID Number <span class="required">*</span></label>
        <input
            type="text"
            id="id_number"
            name="id_number"
            placeholder="xxxx-xxxx"
            pattern="[0-9]{4}-[0-9]{4}"
            minlength="9"
            maxlength="9"
            autocomplete="off"
            value="<?= oldValue($oldInput, 'id_number') ?>"
            required
        >

        <p id="id-number-feedback" class="field-error" aria-live="polite"></p>


        <?php if (isset($errors['id_number'])): ?>
            <p class="field-error"><?= e($errors['id_number']) ?></p>
        <?php endif; ?>

        <label for="username">Username <span class="required">*</span></label>
        <input
            type="text"
            id="username"
            name="username"
            minlength="8"
            maxlength="19"
            value="<?= oldValue($oldInput, 'username') ?>"
            required
        >

        <p id="username-feedback" aria-live="polite"></p>

        <?php if (isset($errors['username'])): ?>
            <p class="field-error"><?= e($errors['username']) ?></p>
        <?php endif; ?>

        <label for="email">Email Address <span class="required">*</span></label>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="100"
            value="<?= oldValue($oldInput, 'email') ?>"
            required
        >

        <p id="email-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['email'])): ?>
            <p class="field-error"><?= e($errors['email']) ?></p>
        <?php endif; ?>

        <label for="password">Password <span class="required">*</span></label>
        <input
            type="password"
            id="password"
            name="password"
            minlength="8"
            maxlength="64"
            required
        >

        <p id="password-strength" aria-live="polite"></p>
        <p id="password-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['password'])): ?>
            <p class="field-error"><?= e($errors['password']) ?></p>
        <?php endif; ?>

        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
        <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            minlength="8"
            maxlength="64"
            required
        >

        <p id="confirm-password-feedback" aria-live="polite"></p>

        <?php if (isset($errors['confirm_password'])): ?>
            <p class="field-error"><?= e($errors['confirm_password']) ?></p>
        <?php endif; ?>

        <h2>Personal Information</h2>

        <label for="first_name">First Name <span class="required">*</span></label>
        <input
            type="text"
            id="first_name"
            name="first_name"
            minlength="2"
            maxlength="28"
            pattern="[A-Z][a-zA-Z]*( [A-Z][a-zA-Z]*)*"
            value="<?= oldValue($oldInput, 'first_name') ?>"
            required
        >

        <p id="first-name-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['first_name'])): ?>
            <p class="field-error"><?= e($errors['first_name']) ?></p>
        <?php endif; ?>

        <label for="middle_name">Middle Name <span class="optional">optional</span></label>
        <input
            type="text"
            id="middle_name"
            name="middle_name"
            minlength="2"
            maxlength="28"
            pattern="[A-Z][a-zA-Z]*( [A-Z][a-zA-Z]*)*"
            value="<?= oldValue($oldInput, 'middle_name') ?>"
        >

        <p id="middle-name-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['middle_name'])): ?>
            <p class="field-error"><?= e($errors['middle_name']) ?></p>
        <?php endif; ?>

        <label for="last_name">Family Name <span class="required">*</span></label>
        <input
            type="text"
            id="last_name"
            name="last_name"
            minlength="2"
            maxlength="28"
            pattern="[A-Z][a-zA-Z]*( [A-Z][a-zA-Z]*)*"
            value="<?= oldValue($oldInput, 'last_name') ?>"
            required
        >

        <p id="last-name-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['last_name'])): ?>
            <p class="field-error"><?= e($errors['last_name']) ?></p>
        <?php endif; ?>

        <label for="name_extension">Name Extension <span class="optional">optional</span></label>
        <select name="name_extension" id="name_extension">
            <option value="" <?= ($oldInput['name_extension'] ?? '') === '' ? 'selected' : '' ?>>None</option>
            <option value="Jr." <?= ($oldInput['name_extension'] ?? '') === 'Jr.' ? 'selected' : '' ?>>Jr.</option>
            <option value="Sr." <?= ($oldInput['name_extension'] ?? '') === 'Sr.' ? 'selected' : '' ?>>Sr.</option>
            <option value="Other" <?= ($oldInput['name_extension'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
        </select>

        <p id="name-extension-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['name_extension'])): ?>
            <p class="field-error"><?= e($errors['name_extension']) ?></p>
        <?php endif; ?>

        <label for="other_name_extension">If Other, enter your extension <span class="optional">optional</span></label>
        <input
            type="text"
            name="other_name_extension"
            id="other_name_extension"
            maxlength="28"
            pattern="(I|II|III|IV|IX|X)"
            placeholder="Example: III"
            value="<?= oldValue($oldInput, 'other_name_extension') ?>"
        >

        <p id="other-extension-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['other_name_extension'])): ?>
            <p class="field-error"><?= e($errors['other_name_extension']) ?></p>
        <?php endif; ?>

        <label for="birth_date">Birthdate <span class="required">*</span></label>
        <input
            type="date"
            name="birth_date"
            id="birth_date"
            value="<?= oldValue($oldInput, 'birth_date') ?>"
            required
        >

        <p id="birth-date-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['birth_date'])): ?>
            <p class="field-error"><?= e($errors['birth_date']) ?></p>
        <?php endif; ?>

        <label for="age">Age <span class="required">*</span></label>
        <input
            type="number"
            name="age"
            id="age"
            readonly
            required
        >

        <p id="age-feedback-class" class="field-error" aria-live="polite"></p>

        <label for="sex">Sex <span class="required">*</span></label>
        <select name="sex" id="sex" required>
            <option value="">Select sex</option>
            <option value="Male" <?= ($oldInput['sex'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= ($oldInput['sex'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
        </select>

        <p id="sex-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['sex'])): ?>
            <p class="field-error"><?= e($errors['sex']) ?></p>
        <?php endif; ?>

        <h2>Resident Information</h2>

        <label for="contact_number">Contact Number <span class="required">*</span></label>
        <input
            type="tel"
            name="contact_number"
            id="contact_number"
            inputmode="numeric"
            placeholder="09xxxxxxxxx"
            pattern="09[0-9]{9}"
            minlength="11"
            maxlength="11"
            value="<?= oldValue($oldInput, 'contact_number') ?>"
            required
        >

        <p id="contact-number-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['contact_number'])): ?>
            <p class="field-error"><?= e($errors['contact_number']) ?></p>
        <?php endif; ?>

        <label for="purok_street">Purok/Street <span class="required">*</span></label>
        <input
            type="text"
            name="purok_street"
            id="purok_street"
            minlength="3"
            maxlength="50"
            pattern="[A-Za-z0-9]+( [A-Za-z0-9]+)*"
            value="<?= oldValue($oldInput, 'purok_street') ?>"
            required
        >

        <p id="purok-street-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['purok_street'])): ?>
            <p class="field-error"><?= e($errors['purok_street']) ?></p>
        <?php endif; ?>

        <label for="barangay">Barangay <span class="required">*</span></label>
        <input
            type="text"
            name="barangay"
            id="barangay"
            minlength="2"
            maxlength="50"
            pattern="[A-Z][a-zA-Z]*( [A-Z][a-zA-Z]*)*"
            value="<?= oldValue($oldInput, 'barangay') ?>"
            required
        >

        <p id="barangay-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['barangay'])): ?>
            <p class="field-error"><?= e($errors['barangay']) ?></p>
        <?php endif; ?>

        <label for="municipality_city">Municipality/City <span class="required">*</span></label>
        <input
            type="text"
            name="municipality_city"
            id="municipality_city"
            minlength="2"
            maxlength="50"
            pattern="[A-Z][a-zA-Z]*( [A-Z][a-zA-Z]*)*"
            value="<?= oldValue($oldInput, 'municipality_city') ?>"
            required
        >

        <p id="municipality-city-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['municipality_city'])): ?>
            <p class="field-error"><?= e($errors['municipality_city']) ?></p>
        <?php endif; ?>

        <label for="province">Province <span class="required">*</span></label>
        <input
            type="text"
            name="province"
            id="province"
            minlength="2"
            maxlength="50"
            pattern="[A-Z][a-zA-Z]*( [A-Z][a-zA-Z]*)*"
            value="<?= oldValue($oldInput, 'province') ?>"
            required
        >

        <p id="province-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['province'])): ?>
            <p class="field-error"><?= e($errors['province']) ?></p>
        <?php endif; ?>

        <label for="country">Country <span class="required">*</span></label>
        <input
            type="text"
            name="country"
            id="country"
            minlength="2"
            maxlength="50"
            pattern="[A-Z][a-zA-Z]*( [A-Z][a-zA-Z]*)*"
            value="<?= oldValue($oldInput, 'country') ?>"
            required
        >

        <p id="country-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['country'])): ?>
            <p class="field-error"><?= e($errors['country']) ?></p>
        <?php endif; ?>

        <label for="zip_code">ZIP Code <span class="required">*</span></label>
        <input
            type="text"
            name="zip_code"
            id="zip_code"
            pattern="[0-9]{4}"
            minlength="4"
            maxlength="4"
            value="<?= oldValue($oldInput, 'zip_code') ?>"
            required
        >

        <p id="zip-code-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['zip_code'])): ?>
            <p class="field-error"><?= e($errors['zip_code']) ?></p>
        <?php endif; ?>

        <label for="civil_status">Civil Status <span class="required">*</span></label>
        <select name="civil_status" id="civil_status" required>
            <option value="">Select civil status</option>
            <option value="Single" <?= ($oldInput['civil_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
            <option value="Married" <?= ($oldInput['civil_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
            <option value="Widowed" <?= ($oldInput['civil_status'] ?? '') === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
            <option value="Separated" <?= ($oldInput['civil_status'] ?? '') === 'Separated' ? 'selected' : '' ?>>Separated</option>
        </select>

        <p id="civil-status-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['civil_status'])): ?>
            <p class="field-error"><?= e($errors['civil_status']) ?></p>
        <?php endif; ?>

        <label for="national_id_number">National ID Number (16-digit PCN) <span class="required">*</span></label>
        <input
            type="text"
            name="national_id_number"
            id="national_id_number"
            inputmode="numeric"
            pattern="[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}"
            minlength="19"
            maxlength="19"
            placeholder="XXXX-XXXX-XXXX-XXXX"
            required
        >

        <p id="national-id-number-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['national_id_number'])): ?>
            <p class="field-error"><?= e($errors['national_id_number']) ?></p>
        <?php endif; ?>

        <h2>Security Questions</h2>

        <label for="security_q1">What was the name of your first pet? <span class="required">*</span></label>
        <input
            type="text"
            name="security_q1"
            id="security_q1"
            minlength="2"
            maxlength="50"
            required
        >

        <p id="security-q1-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['security_q1'])): ?>
            <p class="field-error"><?= e($errors['security_q1']) ?></p>
        <?php endif; ?>

        <label for="security_q4">What is your mother's maiden first name? <span class="required">*</span></label>
        <input
            type="text"
            name="security_q4"
            id="security_q4"
            minlength="2"
            maxlength="50"
            required
        >
        <p id="security-q4-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['security_q4'])): ?>
            <p class="field-error"><?= e($errors['security_q4']) ?></p>
        <?php endif; ?>

        <label for="security_q6">What was the first song you learned by heart? <span class="required">*</span></label>
        <input
            type="text"
            name="security_q6"
            id="security_q6"
            minlength="2"
            maxlength="50"
            required
        >

        <p id="security-q6-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['security_q6'])): ?>
            <p class="field-error"><?= e($errors['security_q6']) ?></p>
        <?php endif; ?>

        <h2>Privacy Notice and Consent</h2>

        <p>
            Please read our
            <a href="privacy_notice.php" target="_blank" rel="noopener noreferrer">
                Privacy Notice
            </a>
            before registering.
        </p>

        <label>
            <input
                type="checkbox"
                id="privacy_consent"
                name="privacy_consent"
                value="1"
                required
            >
            I have read the Privacy Notice and agree to the collection and processing
            of my personal data in accordance with the Data Privacy Act of 2012 (RA 10173).
            <span class="required">*</span>
        </label>

        <p id="privacy-consent-feedback" class="field-error" aria-live="polite"></p>

        <?php if (isset($errors['privacy_consent'])): ?>
            <p class="field-error"><?= e($errors['privacy_consent']) ?></p>
        <?php endif; ?>

        <!-- Honeypot field: real users should leave this empty. -->
        <div hidden aria-hidden="true">
            <label>Leave this field empty</label>
            <input type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit">Register</button>

    </form>

    <footer>
        <p>&copy; 2026 Barangay Document Request and Tracking System. All rights reserved.</p>
    </footer>

    <script src="../../js/auth/register.js" defer></script>

</body>
</html>
