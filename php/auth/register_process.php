<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/mailer.php';

$config = $GLOBALS['config'];

// Safely get a text value from POST data.
function getPostString(string $field): string
{
    $value = $_POST[$field] ?? '';

    if (!is_string($value)) {
        return '';
    }

    return trim($value);
}

// Return to registration page with field-specific errors.
function returnRegistrationErrors(array $errors): never
{
    $_SESSION['registration_errors'] = $errors;

    // Preserve only non-sensitive form values.
    $fieldsToPreserve = [
        'id_number',
        'username',
        'email',
        'first_name',
        'middle_name',
        'last_name',
        'name_extension',
        'other_name_extension',
        'birth_date',
        'sex',
        'contact_number',
        'purok_street',
        'barangay',
        'municipality_city',
        'province',
        'country',
        'zip_code',
        'civil_status'
    ];

    $oldInput = [];

    foreach ($fieldsToPreserve as $field) {
        $oldInput[$field] = getPostString($field);
    }

    $_SESSION['registration_old_input'] = $oldInput;

    header('Location: register.php');
    exit;
}

// Only allow POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Verify the CSRF token.
$csrfToken = $_POST['csrf_token'] ?? null;

if (!is_string($csrfToken) || !verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit('Invalid request.');
}

// Silently reject submissions containing the honeypot value.
$honeypot = $_POST['website'] ?? '';

if (!is_string($honeypot) || $honeypot !=='') {
    http_response_code(400);
    exit;
}

// Collect submitted registration fields.
$idNumber = getPostString('id_number');

$username = getPostString('username');
$email = strtolower(getPostString('email'));
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

$firstName = getPostString('first_name');
$middleName = getPostString('middle_name');
$lastName = getPostString('last_name');

$nameExtensionOption = getPostString('name_extension');
$otherNameExtension = getPostString('other_name_extension');

$nameExtension = '';

if ($nameExtensionOption === 'Jr.' || $nameExtensionOption === 'Sr.') {
    $nameExtension = $nameExtensionOption;
} elseif ($nameExtensionOption === 'Other') {
    $nameExtension = $otherNameExtension;
}

$birthDate = getPostString('birth_date');
$sex = getPostString('sex');

$contactNumber = getPostString('contact_number');

$purokStreet = getPostString('purok_street');
$barangay = getPostString('barangay');
$municipalityCity = getPostString('municipality_city');
$province = getPostString('province');
$country = getPostString('country');
$zipCode = getPostString('zip_code');

$civilStatus = getPostString('civil_status');

$nationalID = getPostString('national_id_number');

$privacyConsent = getPostString('privacy_consent');

$securityQ1 = getPostString('security_q1');
$securityQ4 = getPostString('security_q4');
$securityQ6 = getPostString('security_q6');

// Ensure password fields contain text values.
if (!is_string($password) || !is_string($confirmPassword)) {
    http_response_code(422);
    exit('Invalid registration information.');
}

// Validate the submitted fields.
$errors = [];

// Validate ID Number.
if (preg_match('/^[0-9]{4}-[0-9]{4}$/D', $idNumber) !== 1) {
    $errors['id_number'] = 'ID Number must follow the format xxxx-xxxx.';
}

// Validate Username.
if ($username === '') {
    $errors['username'] = 'Username is required.';
} elseif (strlen($username) < 8) {
    $errors['username'] = 'Username must contain at least 8 characters.';
} elseif (strlen($username) > 19) {
    $errors['username'] = 'Username must not exceed 19 characters.';
} elseif (preg_match('/^[a-zA-Z]/', $username) !== 1) {
    $errors['username'] = 'Username must start with a letter.';
} elseif (preg_match('/^[a-zA-Z0-9]+$/', $username) !== 1) {
    $errors['username'] = 'Username may contain only letters and numbers.';
}

// Validate Email.
if ($email === '') {
    $errors['email'] = 'Email address is required.';
} elseif (strlen($email) > 100) {
    $errors['email'] = 'Email address must not exceed 100 characters.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

// Validate Password.
if (strlen($password) < 8) {
    $errors['password'] = 'Password must contain at least 8 characters.';
} elseif (strlen($password) > 64) {
    $errors['password'] = 'Password must not exceed 64 characters.';
} else {
    $hasLowercase = preg_match('/[a-z]/', $password);
    $hasUppercase = preg_match('/[A-Z]/', $password);
    $hasNumber = preg_match('/[0-9]/', $password);
    $hasSpecial = preg_match('/[^a-zA-Z0-9]/', $password);

    $criteriaCount = $hasLowercase + $hasUppercase + $hasNumber + $hasSpecial;

    if ($criteriaCount <= 2) {
        $errors['password'] = 'Password is too weak. Use a combination of lowercase letters, uppercase letters, numbers, and special characters.';
    }
}

// Validate Confirm Password.
if ($password !== $confirmPassword) {
    $errors['confirm_password'] = 'Passwords do not match.';
}

// Validate First Name.
$nameError = getPersonNameError($firstName, 'First Name');

if ($nameError !== null) {
    $errors['first_name'] = $nameError;
}

// Validate Middle Name.
$nameError = getPersonNameError($middleName, 'Middle Name', false);

if ($nameError !== null) {
    $errors['middle_name'] = $nameError;
}

// Validate Family Name.
$nameError = getPersonNameError($lastName, 'Family Name');

if ($nameError !== null) {
    $errors['last_name'] = $nameError;
}

// Validate name extension.
if ($nameExtensionOption === 'Jr.' || $nameExtensionOption === 'Sr.') {

    if ($otherNameExtension !== '') {
        $errors['name_extension'] = 'Do not enter a custom extension when Jr. or Sr. is selected.';
    }

} elseif ($nameExtensionOption === 'Other') {

    if (!isValidCustomNameExtension($otherNameExtension)) {
        $errors['other_name_extension'] = 'Custom name extension must be one of: I, II, III, IV, IX, X.';
    }

} elseif ($nameExtensionOption === '') {

    if ($otherNameExtension !== '') {
        $errors['other_name_extension'] = 'Select Other before entering a custom name extension.';
    }

} else {

    $errors['name_extension'] = 'Please select a valid name extension.';
}

// Validate birthdate and calculate age on the server.
$date = DateTime::createFromFormat('!Y-m-d', $birthDate);
$today = new DateTimeImmutable('today');

if (!$date || $date->format('Y-m-d') !== $birthDate) {
    $errors['birth_date'] = 'Please enter a valid birthdate.';
} elseif ($date > $today) {
    $errors['birth_date'] = 'Birthdate cannot be in the future.';
} else {
    $age = $date->diff($today)->y;

    if ($age < 18) {
        $errors['birth_date'] = 'You must be at least 18 years old to register.';
    }
}

// Validate Sex.
if (!in_array($sex, ['Male', 'Female'], true)) {
    $errors['sex'] = 'Please select Male or Female.';
}

// Validate Contact Number.
if (preg_match('/^09[0-9]{9}$/', $contactNumber) !== 1) {
    $errors['contact_number'] = 'Contact Number must start with 09 and contain exactly 11 digits.';
}

// Validate Purok / Street.
if (strlen($purokStreet) < 3 || strlen($purokStreet) > 50) {
    $errors['purok_street'] = 'Purok / Street must contain 3 to 50 characters.';
} elseif (preg_match('/^[A-Za-z0-9]+(?: [A-Za-z0-9]+)*$/', $purokStreet) !== 1) {
    $errors['purok_street'] = 'Purok / Street can only contain letters, numbers, and single spaces.';
}

// Validate Barangay, Municipality / City, and Province.
$locationFields = [
    'barangay' => ['value' => $barangay, 'label' => 'Barangay'],
    'municipality_city' => ['value' => $municipalityCity, 'label' => 'Municipality / City'],
    'province' => ['value' => $province, 'label' => 'Province']
];

foreach ($locationFields as $field => $data) {
    $value = $data['value'];
    $label = $data['label'];

    if (strlen($value) < 2 || strlen($value) > 50) {
        $errors[$field] = $label . ' must contain 2 to 50 characters.';
    } elseif (preg_match('/^[A-Z][a-z]*(?: [A-Z][a-z]*)*$/', $value) !== 1) {
        $errors[$field] = $label . ' must use Title Case with single spaces.';
    }
}

// Validate Country.
if (strlen($country) < 2 || strlen($country) > 50) {
    $errors['country'] = 'Country must contain 2 to 50 characters.';
} elseif (preg_match('/^[A-Z][a-z]*(?: [A-Z][a-z]*)*$/', $country) !== 1) {
    $errors['country'] = 'Country must use Title Case with single spaces.';
}

// Validate ZIP Code.
if (preg_match('/^[0-9]{4}$/', $zipCode) !== 1) {
    $errors['zip_code'] = 'ZIP Code must contain exactly 4 digits.';
}

// Validate Civil Status.
if (!in_array($civilStatus, ['Single', 'Married', 'Widowed', 'Separated'], true)) {
    $errors['civil_status'] = 'Please select a valid civil status.';
}

// Validate National ID Number.
if (preg_match('/^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}$/', $nationalID) !== 1) {
    $errors['national_id_number'] = 'National ID Number must follow the format XXXX-XXXX-XXXX-XXXX.';
}

// Validate Privacy Notice consent.
if ($privacyConsent !== '1') {
    $errors['privacy_consent'] = 'You must agree to the Privacy Notice to register.';
}

// Validate security answers: letters and single spaces only.
$securityAnswerFields = [
    'security_q1' => 'Security Answer 1',
    'security_q4' => 'Security Answer 2',
    'security_q6' => 'Security Answer 3'
];

$securityAnswers = [
    'security_q1' => $securityQ1,
    'security_q4' => $securityQ4,
    'security_q6' => $securityQ6
];

foreach ($securityAnswers as $field => $answer) {
    $label = $securityAnswerFields[$field];

    if (strlen($answer) < 2 || strlen($answer) > 50) {
        $errors[$field] = $label . ' must contain 2 to 50 characters.';
    } elseif (preg_match('/^[A-Za-z]+(?: [A-Za-z]+)*$/', $answer) !== 1) {
        $errors[$field] = $label . ' can only contain letters and single spaces.';
    }
}

// Stop if any field is invalid.
if (!empty($errors)) {
    returnRegistrationErrors($errors);
}

// Check if ID Number, username, or email already exists.
try {
    $stmt = $pdo->prepare(
        'SELECT id_number, username, email
         FROM users
         WHERE id_number = :id_number
            OR username = :username
            OR email = :email'
    );

    $stmt->execute([
        'id_number' => $idNumber,
        'username' => $username,
        'email' => $email
    ]);

    $existingUsers = $stmt->fetchAll();

    foreach ($existingUsers as $existingUser) {
        if ($existingUser['id_number'] === $idNumber) {
            $errors['id_number'] = 'This ID Number is already registered.';
        }

        if (strcasecmp($existingUser['username'], $username) === 0) {
            $errors['username'] = 'This username is already taken.';
        }

        if (strcasecmp($existingUser['email'], $email) === 0) {
            $errors['email'] = 'This email is already registered.';
        }
    }

} catch (PDOException $e) {
    error_log('Registration duplicate check failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to process registration.');
}

if (!empty($errors)) {
    returnRegistrationErrors($errors);
}

// Hash the resident's password.
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

if ($passwordHash === false) {
    error_log('Password hashing failed during registration.');
    http_response_code(500);
    exit('Unable to process registration.');
}

// Encrypt the National ID using AES-256-GCM.
try {
    $encryptedNationalID = encryptNationalID(
        $nationalID,
        $config['encryption_key']
    );
} catch (Throwable $e) {
    error_log('National ID encryption failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to process registration. ');
}

// Hash the security question answers.
$securityAnswers = [
    'Q1' => strtolower($securityQ1),
    'Q4' => strtolower($securityQ4),
    'Q6' => strtolower($securityQ6)
];

$securityAnswerHashes = [];

foreach ($securityAnswers as $questionCode => $answer) {
    $answerHash = password_hash($answer, PASSWORD_DEFAULT);

    if ($answerHash === false) {
        error_log('Security answer hashing failed.');
        http_response_code(500);
        exit('Unable to process registration.');
    }

    $securityAnswerHashes[$questionCode] = $answerHash;
}

// Generate a secure 6-digit registration OTP.
$otp = (string) random_int(100000, 999999);
$otpHash = password_hash($otp, PASSWORD_DEFAULT);

if ($otpHash === false) {
    error_log('Registration OTP hashing failed.');
    http_response_code(500);
    exit('Unable to process registration.');
}

// Set OTP expiration to 5 minutes.
$otpExpiry = date('Y-m-d H:i:s', time() + 300);

// Get the user's Ip address and browser infomation.
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);


try {
    // Start database transaction.
    $pdo->beginTransaction();

    // Use the resident's ID Number as the users primary key.
    $userID = $idNumber;

    // Insert resident account.
    $stmt = $pdo->prepare(
        'INSERT INTO users
            (id_number, username, email, password_hash, role, account_status)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $idNumber,
        $username,
        $email,
        $passwordHash,
        'resident',
        'pending'
    ]);

    // Insert resident profile.
    $stmt = $pdo->prepare(
        'INSERT INTO resident_profiles (
            user_id, first_name, middle_name, last_name, name_extension,
            birth_date, sex, contact_number, purok_street,
            barangay, municipality_city, province,
            country, zip_code, civil_status,
            national_id_ciphertext, national_id_iv, national_id_tag,
            privacy_consent, privacy_consent_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );

    $stmt->execute([
        $userID,
        $firstName,
        $middleName !== '' ? $middleName : null,
        $lastName,
        $nameExtension !== '' ? $nameExtension : null,
        $birthDate,
        $sex,
        $contactNumber,
        $purokStreet,
        $barangay,
        $municipalityCity,
        $province,
        $country,
        $zipCode,
        $civilStatus,
        $encryptedNationalID['ciphertext'],
        $encryptedNationalID['iv'],
        $encryptedNationalID['tag'],
        1
    ]);

    // Insert security question answers.
    $stmt = $pdo->prepare(
        'INSERT INTO security_answers
            (user_id, question_code, answer_hash)
         VALUES (?, ?, ?)'
    );

    foreach ($securityAnswerHashes as $questionCode => $answerHash) {
        $stmt->execute([
            $userID,
            $questionCode,
            $answerHash
        ]);
    }

    // Save the initial password in password history.
    $stmt = $pdo->prepare(
        'INSERT INTO password_history (user_id, password_hash)
         VALUES (?, ?)'
    );

    $stmt->execute([$userID, $passwordHash]);

    // Save the registration OTP hash.
    $stmt = $pdo->prepare(
        'INSERT INTO email_otps
            (user_id, otp_hash, purpose, expires_at)
         VALUES (?, ?, ?, ?)'
    );

    $stmt->execute([
        $userID,
        $otpHash,
        'registration',
        $otpExpiry
    ]);

    // Record the registration activity.
    $stmt = $pdo->prepare(
        'INSERT INTO activity_logs
            (user_id, event_type, description, ip_address,
             user_agent, event_data)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $eventData = json_encode([
        'role' => 'resident',
        'account_status' => 'pending'
    ], JSON_THROW_ON_ERROR);

    $stmt->execute([
        $userID,
        'REGISTRATION_CREATED',
        'Resident registration submitted.',
        $ipAddress,
        $userAgent,
        $eventData
    ]);

    // Save all database records together.
    $pdo->commit();

    $_SESSION['pending_registration_user_id'] = $userID;

} catch (Throwable $e) {

    // Undo database changes if any operation fails.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Resident registration failed: ' . $e->getMessage());
    http_response_code(500);
    exit('We could not save your registration due to a server error. Please try again later.');
}


// Send the registration OTP after saving the database records.
try {
    $mail = createMailer();

    $mail->addAddress($email);
    $mail->isHTML(true);

    $mail->Subject = 'Verify Your Account - Barangay Document Request and Tracking System';

    $mail->Body = '
        <h2>Barangay Document Request and Tracking System</h2>
        <h3>Verify Your Account</h3>
        <p>Thank you for registering.</p>
        <p>Your 6-digit verification code is:</p>
        <h1>' . e($otp) . '</h1>
        <p>This code expires in 5 minutes.</p>
        <p>If you did not request this code, ignore this email and consider changing your password.</p>
    ';

    $mail->AltBody =
        "Barangay Document Request and Tracking System\n"
        .  "Verify Your Account\n"
        . "Your 6-digit verification code is: {$otp}\n"
        . "This code expires in 5 minutes.\n"
        . "If you did not request this code, ignore this email and consider changing your password.";

    $mail->send();
} catch (Throwable $e) {
    error_log('Registration OTP email failed: ' . $e->getMessage());
    http_response_code(500);
    exit(
        'Your registration was saved, but the verification email '
        . 'could not be sent. Please contact barangay staff for assistance.'
    );
}

// Redirect to the registration  verification page.
header('Location: verify_registration.php');
exit;