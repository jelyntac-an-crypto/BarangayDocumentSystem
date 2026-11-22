
document.addEventListener('DOMContentLoaded', function () {
    const privacyLink = document.getElementById('privacy-notice-link');
    const privacyDialog = document.getElementById('privacy-notice-dialog');
    const privacyFrame = document.getElementById('privacy-notice-frame');
    const privacyClose = document.getElementById('privacy-modal-close');

    if (privacyLink && privacyDialog && privacyFrame && privacyClose) {
        privacyLink.addEventListener('click', function (event) {
            if (typeof privacyDialog.showModal !== 'function') return;
            event.preventDefault();
            privacyFrame.src = 'privacy_notice.php?embed=1';
            privacyDialog.showModal();
        });
        privacyClose.addEventListener('click', function () { privacyDialog.close(); });
        privacyDialog.addEventListener('click', function (event) {
            if (event.target === privacyDialog) privacyDialog.close();
        });
    }
console.log('REGISTER JS: DOMContentLoaded started');

    const idNumberInput = document.getElementById('id_number');
    const idNumberFeedback = document.getElementById('id-number-feedback');

    const usernameInput = document.getElementById('username');
    const usernameFeedback = document.getElementById('username-feedback');

    const emailInput = document.getElementById('email');
    const emailFeedback = document.getElementById('email-feedback');

    const passwordInput = document.getElementById('password');
    const passwordStrength = document.getElementById('password-strength');
    const passwordFeedback = document.getElementById('password-feedback');

    const confirmPasswordInput = document.getElementById('confirm_password');
    const confirmPasswordFeedback = document.getElementById('confirm-password-feedback');

    const firstNameInput = document.getElementById('first_name');
    const firstNameFeedback = document.getElementById('first-name-feedback');

    const middleNameInput = document.getElementById('middle_name');
    const middleNameFeedback = document.getElementById('middle-name-feedback');

    const lastNameInput = document.getElementById('last_name');
    const lastNameFeedback = document.getElementById('last-name-feedback');

    const extensionSelect = document.getElementById('name_extension');
    const otherExtension = document.getElementById('other_name_extension');

    const nameExtensionFeedback = document.getElementById('name-extension-feedback');
    const otherExtensionFeedback = document.getElementById('other-extension-feedback');

    const birthDateInput = document.getElementById('birth_date');
    const ageInput = document.getElementById('age');
    const birthDateFeedback = document.getElementById('birth-date-feedback');

    const sexInput = document.getElementById('sex');
    const sexFeedback = document.getElementById('sex-feedback');

    const contactNumberInput = document.getElementById('contact_number');
    const contactNumberFeedback = document.getElementById('contact-number-feedback');

    const purokStreetInput = document.getElementById('purok_street');
    const purokStreetFeedback = document.getElementById('purok-street-feedback');

    const barangayInput = document.getElementById('barangay');
    const barangayFeedback = document.getElementById('barangay-feedback');

    const municipalityCityInput = document.getElementById('municipality_city');
    const municipalityCityFeedback = document.getElementById('municipality-city-feedback');

    const provinceInput = document.getElementById('province');
    const provinceFeedback = document.getElementById('province-feedback');

    const countryInput = document.getElementById('country');
    const countryFeedback = document.getElementById('country-feedback');

    const zipCodeInput = document.getElementById('zip_code');
    const zipCodeFeedback = document.getElementById('zip-code-feedback');

    const civilStatusInput = document.getElementById('civil_status');
    const civilStatusFeedback = document.getElementById('civil-status-feedback');

    const nationalIDInput = document.getElementById('national_id_number');
    const nationalIDFeedback = document.getElementById('national-id-number-feedback');

    const securityQ1Input = document.getElementById('security_q1');
    const securityQ1Feedback = document.getElementById('security-q1-feedback');

    const securityQ4Input = document.getElementById('security_q4');
    const securityQ4Feedback = document.getElementById('security-q4-feedback');

    const securityQ6Input = document.getElementById('security_q6');
    const securityQ6Feedback = document.getElementById('security-q6-feedback');

    const privacyConsentInput = document.getElementById('privacy_consent');
    const privacyConsentFeedback = document.getElementById('privacy-consent-feedback');

    const registerForm = passwordInput.closest('form');

    let usernameTimer;
    let usernameRequest;

    function checkPasswordStrength() {
        const password = passwordInput.value;

        if (password.length === 0) {
            passwordStrength.textContent = '';
            passwordStrength.removeAttribute('class');
            passwordFeedback.textContent = '';
            passwordInput.dataset.strength = 'weak';
            return;
        }

        const hasLowercase = /[a-z]/.test(password);
        const hasUppercase = /[A-Z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[^a-zA-Z0-9]/.test(password);

        const criteriaCount = [
            hasLowercase,
            hasUppercase,
            hasNumber,
            hasSpecial
        ].filter(Boolean).length;

        if (password.length < 8 || criteriaCount <= 2) {
            passwordStrength.textContent = 'Password Strength: Weak';
            passwordStrength.className = 'strength-weak';
            passwordInput.dataset.strength = 'weak';
        } else if (criteriaCount === 3) {
            passwordStrength.textContent = 'Password Strength: Medium';
            passwordStrength.className = 'strength-medium';
            passwordInput.dataset.strength = 'medium';
        } else {
            passwordStrength.textContent = 'Password Strength: Strong';
            passwordStrength.className = 'strength-strong';
            passwordInput.dataset.strength = 'strong';
        }

        if (password.length < 8) {
            passwordFeedback.textContent =
                'Password must contain at least 8 characters.';
        } else if (password.length > 64) {
            passwordFeedback.textContent =
                'Password must not exceed 64 characters.';
        } else if (criteriaCount <= 2) {
            passwordFeedback.textContent =
                'Password is too weak. Use lowercase letters, uppercase letters, numbers, and special characters.';
        } else {
            passwordFeedback.textContent = '';
        }
    }

    function checkConfirmPassword() {
    const password = passwordInput.value;
    const confirmPassword = confirmPasswordInput.value;

    if (confirmPassword.length === 0) {
        if (confirmPasswordInput.dataset.touched === 'true') {
            confirmPasswordFeedback.textContent =
                'Please confirm your password.';
            confirmPasswordFeedback.className = 'field-error';
        } else {
            confirmPasswordFeedback.textContent = '';
            confirmPasswordFeedback.removeAttribute('class');
        }
        return;
    }

    if (password !== confirmPassword) {
        confirmPasswordFeedback.textContent =
            'Passwords do not match.';
        confirmPasswordFeedback.className = 'field-error';
    } else {
        confirmPasswordFeedback.textContent = '';
        confirmPasswordFeedback.removeAttribute('class');
    }
}

    function checkIDNumber() {
        const idNumber = idNumberInput.value.trim();
        const isValid = /^[0-9]{4}-[0-9]{4}$/.test(idNumber);

        if (isValid) {
            idNumberFeedback.textContent = '';
            return true;
        }

        idNumberFeedback.textContent =
            'ID Number must follow the format xxxx-xxxx.';
            return false;
    }

    function checkEmail() {
        const email = emailInput.value.trim();

        if (email.length === 0) {
            emailFeedback.textContent =
            emailInput.dataset.touched === 'true'
                ? 'Email address is required.'
                : '';
            return;
        }

        if (email.length > 100) {
            emailFeedback.textContent =
            'Email address must not exceed 100 characters.';
            return;
        }

        if (!emailInput.validity.valid) {
            emailFeedback.textContent = 'Please enter a valid email address.';
            return;
        }

        emailFeedback.textContent = '';
    }

    function checkFirstName() {
        const name = firstNameInput.value.trim();

        if (name.length === 0) {
            firstNameFeedback.textContent =
                firstNameInput.dataset.touched === 'true'
                    ? 'First Name is required.'
                    : '';
                return;
        }

        if (name.length < 2) {
            firstNameFeedback.textContent =
                'First Name must contain at least 2 letters.';
        } else if (name.length > 28) {
            firstNameFeedback.textContent =
                'First Name must not exceed 28 characters.';
        } else if (/[0-9]/.test(name)) {
            firstNameFeedback.textContent =
                'Numbers are not allowed in First Name.';
        } else if (/[^A-Za-z ]/.test(name)) {
            firstNameFeedback.textContent =
                'Special characters are not allowed in First Name.';
        } else if (/ {2,}/.test(name)) {
            firstNameFeedback.textContent =
                'Double space is not allowed.';
        } else if (/(.)\1\1/i.test(name)) {
            firstNameFeedback.textContent =
                'Three consecutive identical letters are not allowed.';
        } else if (/^[a-z]/.test(name)) {
            firstNameFeedback.textContent =
                'First letter of your name must be capital.';
        } else if (/^[A-Z ]+$/.test(name)) {
            firstNameFeedback.textContent =
                'Do not use all capital letters.';
        } else if (!/^[A-Z][a-z]*(?: [A-Z][a-z]*)*$/.test(name)) {
            firstNameFeedback.textContent =
                'Use Title Case for each part of your name.';
        } else {
            firstNameFeedback.textContent = '';
        }
    }

    function checkMiddleName() {
        const name = middleNameInput.value.trim();

        // Middle Name is optional
        if (name.length === 0) {
            middleNameFeedback.textContent = '';
            return;
        }

        if (name.length < 2) {
            middleNameFeedback.textContent =
                'Middle Name must contain at least 2 letters.';
        } else if (name.length > 28) {
            middleNameFeedback.textContent =
                'Middle Name must not exceed 28 characters.';
        } else if (/[0-9]/.test(name)) {
            middleNameFeedback.textContent =
                'Numbers are not allowed in Middle Name.';
        } else if (/[^A-Za-z ]/.test(name)) {
            middleNameFeedback.textContent =
                'Special characters are not allowed in Middle Name.';
        } else if (/ {2,}/.test(name)) {
            middleNameFeedback.textContent =
                'Double space is not allowed.';
        } else if (/(.)\1\1/i.test(name)) {
            middleNameFeedback.textContent =
                'Three consecutive identical letters are not allowed.';
        } else if (/^[a-z]/.test(name)) {
            middleNameFeedback.textContent =
                'First letter of your name must be capital.';
        } else if (/^[A-Z ]+$/.test(name)) {
            middleNameFeedback.textContent =
                'Do not use all capital letters.';
        } else if (!/^[A-Z][a-z]*(?: [A-Z][a-z]*)*$/.test(name)) {
            middleNameFeedback.textContent =
                'Use Title Case for each part of your name.';
        } else {
            middleNameFeedback.textContent = '';
        }
    }

    function checkLastName() {
        const name = lastNameInput.value.trim();

        if (name.length === 0) {
            lastNameFeedback.textContent =
                lastNameInput.dataset.touched === 'true'
                    ? 'Family Name is required.'
                    : '';
            return;
        }

        if (name.length < 2) {
            lastNameFeedback.textContent =
                'Family Name must contain at least 2 letters.';
        } else if (name.length > 28) {
            lastNameFeedback.textContent =
                'Family Name must not exceed 28 characters.';
        } else if (/[0-9]/.test(name)) {
            lastNameFeedback.textContent =
                'Numbers are not allowed in Family Name.';
        } else if (/[^A-Za-z ]/.test(name)) {
            lastNameFeedback.textContent =
                'Special characters are not allowed in Family Name.';
        } else if (/ {2,}/.test(name)) {
            lastNameFeedback.textContent =
                'Double space is not allowed.';
        } else if (/(.)\1\1/i.test(name)) {
            lastNameFeedback.textContent =
                'Three consecutive identical letters are not allowed.';
        } else if (/^[a-z]/.test(name)) {
            lastNameFeedback.textContent =
                'First letter of your name must be capital.';
        } else if (/^[A-Z ]+$/.test(name)) {
            lastNameFeedback.textContent =
                'Do not use all capital letters.';
        } else if (!/^[A-Z][a-z]*(?: [A-Z][a-z]*)*$/.test(name)) {
            lastNameFeedback.textContent =
                'Use Title Case for each part of your name.';
        } else {
            lastNameFeedback.textContent = '';
        }
    }

    function checkNameExtension() {
        const selectedExtension = extensionSelect.value;
        const customExtension = otherExtension.value.trim();

        nameExtensionFeedback.textContent = '';
        otherExtensionFeedback.textContent = '';

        if (selectedExtension === 'Jr.' || selectedExtension === 'Sr.') {
            if (customExtension !== '') {
                otherExtensionFeedback.textContent =
                    'Do not enter a custom extension when Jr. or Sr. is selected.';
                return false;
            }

           return true;
        }

        if (selectedExtension === 'Other') {
            const validExtensions = ['I', 'II', 'III', 'IV', 'IX', 'X'];

            if (!validExtensions.includes(customExtension)) {
                otherExtensionFeedback.textContent =
                    'Custom name extension must be one of: I, II, III, IV, IX, X.';
                return false;
            }

            return true;
        }

        if (selectedExtension === '') {
            if (customExtension !== '') {
                otherExtensionFeedback.textContent =
                    'Select Other before entering a custom name extension.';
                return false;
            }

            return true;
        }

        nameExtensionFeedback.textContent =
            'Please select a valid name extension.';

        return false;

    }

    function calculateAge() {
        if (!birthDateInput.value) {
            ageInput.value = '';
            return;
        }

        const [year, month, day] = birthDateInput.value.split('-').map(Number);
        const today = new Date();

        let age = today.getFullYear() - year;

        if (
            today.getMonth() + 1 < month ||
            (today.getMonth() + 1 === month && today.getDate() < day)
        ) {
            age--;
        }

        ageInput.value = age;
    }

    function checkBirthDate() {
        const birthDate = birthDateInput.value;

        birthDateFeedback.textContent = '';

        if (birthDate === '') {
            if (birthDateInput.dataset.touched === 'true') {
                birthDateFeedback.textContent = 'Birthdate is required.';
            }

            ageInput.value = '';
            return false;
        }

        const selectedDate = new Date(birthDate + 'T00:00:00');
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (Number.isNaN(selectedDate.getTime())) {
            birthDateFeedback.textContent = 'Please enter a valid birthdate.';
            ageInput.value = '';
            return false;
        }

        if (selectedDate > today) {
            birthDateFeedback.textContent = 'Birthdate cannot be in the future.';
            ageInput.value = '';
            return false;
        }

        calculateAge();

        if (Number(ageInput.value) < 18) {
            birthDateFeedback.textContent =
                'You must be at least 18 years old to register.';
            return false;
        }

        return true;
    }

    function checkSex() {
        sexFeedback.textContent = '';

        if (sexInput.value === '') {
            if (sexInput.dataset.touched === 'true') {
                sexFeedback.textContent = 'Please select Male or Female.';
            }

            return false;
        }

        if (!['Male', 'Female'].includes(sexInput.value)) {
            sexFeedback.textContent = 'Please select Male or Female.';
            return false;
        }

        return true;
    }
    function checkContactNumber() {
        const contactNumber = contactNumberInput.value.trim();

        contactNumberFeedback.textContent = '';

        if (contactNumber === '') {
            if (contactNumberInput.dataset.touched === 'true') {
                contactNumberFeedback.textContent =
                    'Contact Number is required.';
            }
            return false;
        }

        if (!/^09[0-9]{9}$/.test(contactNumber)) {
            contactNumberFeedback.textContent =
                'Contact Number must start with 09 and contain exactly 11 digits.';
            return false;
        }

        return true;
    }

    function checkPurokStreet() {
        const purokStreet = purokStreetInput.value;

        purokStreetFeedback.textContent = '';

        if (purokStreet.trim() === '') {
            if (purokStreetInput.dataset.touched === 'true') {
                purokStreetFeedback.textContent =
                    'Purok / Street is required.';
            }
            return false;
        }

        if (purokStreet.length < 3 || purokStreet.length > 50) {
            purokStreetFeedback.textContent =
                'Purok / Street must contain 3 to 50 characters.';
            return false;
        }

        if (!/^[A-Za-z0-9]+(?: [A-Za-z0-9]+)*$/.test(purokStreet)) {
            purokStreetFeedback.textContent =
                'Purok / Street can only contain letters, numbers, and single spaces.';
            return false;
        }

        return true;
    }

    function checkBarangay() {
        const barangay = barangayInput.value;

        barangayFeedback.textContent = '';

        if (barangay.trim() === '') {
            if (barangayInput.dataset.touched === 'true') {
                barangayFeedback.textContent = 'Barangay is required.';
            }
            return false;
        }

        if (barangay.length < 2 || barangay.length > 50) {
            barangayFeedback.textContent =
                'Barangay must contain 2 to 50 characters.';
            return false;
        }

        if (!/^[A-Z][a-z]*(?: [A-Z][a-z]*)*$/.test(barangay)) {
            barangayFeedback.textContent =
                'Barangay must use Title Case with single spaces.';
            return false;
        }

        return true;
    }

    function checkMunicipalityCity() {
        const municipalityCity = municipalityCityInput.value;

        municipalityCityFeedback.textContent = '';

        if (municipalityCity.trim() === '') {
            if (municipalityCityInput.dataset.touched === 'true') {
                municipalityCityFeedback.textContent =
                    'Municipality / City is required.';
            }
            return false;
        }

        if (municipalityCity.length < 2 || municipalityCity.length > 50) {
            municipalityCityFeedback.textContent =
                'Municipality / City must contain 2 to 50 characters.';
            return false;
        }

        if (!/^[A-Z][a-z]*(?: [A-Z][a-z]*)*$/.test(municipalityCity)) {
            municipalityCityFeedback.textContent =
                'Municipality / City must use Title Case with single spaces.';
            return false;
        }

        return true;
    }


    function checkProvince() {
        const province = provinceInput.value;

        provinceFeedback.textContent = '';

        if (province.trim() === '') {
            if (provinceInput.dataset.touched === 'true') {
                provinceFeedback.textContent =
                    'Province is required.';
            }
            return false;
        }

        if (province.length < 2 || province.length > 50) {
            provinceFeedback.textContent =
                'Province must contain 2 to 50 characters.';
            return false;
        }

        if (!/^[A-Z][a-z]*(?: [A-Z][a-z]*)*$/.test(province)) {
            provinceFeedback.textContent =
                'Province must use Title Case with single spaces.';
            return false;
        }

        return true;
    }

    function checkCountry() {
        const country = countryInput.value;

        countryFeedback.textContent = '';

        if (country.trim() === '') {
            if (countryInput.dataset.touched === 'true') {
                countryFeedback.textContent =
                    'Country is required.';
            }
            return false;
        }

        if (country.length < 2 || country.length > 50) {
            countryFeedback.textContent =
                'Country must contain 2 to 50 characters.';
            return false;
        }

        if (!/^[A-Z][a-z]*(?: [A-Z][a-z]*)*$/.test(country)) {
            countryFeedback.textContent =
                'Country must use Title Case with single spaces.';
            return false;
        }

        return true;
    }

    function checkZipCode() {
        const zipCode = zipCodeInput.value;

        zipCodeFeedback.textContent = '';

        if (zipCode.trim() === '') {
            if (zipCodeInput.dataset.touched === 'true') {
                zipCodeFeedback.textContent =
                    'ZIP Code is required.';
            }
            return false;
        }

        if (!/^[0-9]{4}$/.test(zipCode)) {
            zipCodeFeedback.textContent =
                'ZIP Code must contain exactly 4 digits';
            return false;
        }

        return true;
    }

    function checkCivilStatus() {
        const civilStatus = civilStatusInput.value;

        civilStatusFeedback.textContent = '';

        const validStatuses = [
            'Single',
            'Married',
            'Widowed',
            'Separated'
        ]

        if (!validStatuses.includes(civilStatus)) {
            if (civilStatusInput.dataset.touched === 'true') {
                civilStatusFeedback.textContent =
                    'Please select a valid civil status.';
            }
            return false;
        }

        return true;
    }

    function checkNationalID() {
        const nationalID = nationalIDInput.value;

        nationalIDFeedback.textContent = '';

        if (nationalID.trim() === '') {
            if (nationalIDInput.dataset.touched === 'true') {
                nationalIDFeedback.textContent =
                    'National ID Number is required.';
            }
            return false;
        }

        if (!/^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}$/.test(nationalID)) {
            nationalIDFeedback.textContent =
                'National ID Number must follow the format XXXX-XXXX-XXXX-XXXX.';
            return false;
        }

        return true;
    }

    function checkSecurityAnswer(input, feedback, label) {
        const answer = input.value;

        feedback.textContent = '';

        if (answer.trim() === '') {
            if (input.dataset.touched === 'true') {
                feedback.textContent = label + ' is required.';
            }
            return false;
        }

        if (answer.length < 2 || answer.length > 50) {
            feedback.textContent =
                label + ' must contain 2 to 50 characters.';
            return false;
        }

        if (!/^[A-Za-z]+(?: [A-Za-z]+)*$/.test(answer)) {
            feedback.textContent =
                label + ' can only contain letters and single spaces.';
            return false;
        }

        return true;
    }

    function checkSecurityQ1() {
        return checkSecurityAnswer(
            securityQ1Input,
            securityQ1Feedback,
            'Security Answer 1'
        );
    }

    function checkSecurityQ4() {
        return checkSecurityAnswer(
            securityQ4Input,
            securityQ4Feedback,
            'Security Answer 2'
        );
    }

    function checkSecurityQ6() {
        return checkSecurityAnswer(
            securityQ6Input,
            securityQ6Feedback,
            'Security Answer 3'
        );
    }

    function checkPrivacyConsent() {
        privacyConsentFeedback.textContent = '';

        if (!privacyConsentInput.checked) {
            privacyConsentFeedback.textContent =
                'You must agree to the Privacy Notice to register.';
            return false;
        }

        return true;
    }

    function toggleOtherExtension() {
        const isOther = extensionSelect.value === 'Other';

        otherExtension.hidden = !isOther;
        otherExtension.required = isOther;

        if (!isOther) {
            otherExtension.value = '';
        }
    }
    async function checkUsername() {
        const username = usernameInput.value.trim();

        clearTimeout(usernameTimer);

        if (usernameRequest) {
            usernameRequest.abort();
        }

        usernameFeedback.textContent = '';
        usernameFeedback.removeAttribute('class');

        // Check required field.
        if (username.length === 0) {
            if (usernameInput.dataset.touched === 'true') {
                usernameFeedback.textContent = 'Username is required.';
                usernameFeedback.className = 'field-error';
            }
            return;
        }

        // Check minimum length.
        if (username.length < 8) {
            usernameFeedback.textContent =
                'Username must contain at least 8 characters.';
            usernameFeedback.className = 'field-error';
            return;
        }

        // Check maximum length.
        if (username.length > 19) {
            usernameFeedback.textContent =
                'Username must not exceed 19 characters.';
            usernameFeedback.className = 'field-error';
            return;
        }

        // Check first character.
        if (!/^[a-zA-Z]/.test(username)) {
            usernameFeedback.textContent =
                'Username must start with a letter.';
            usernameFeedback.className = 'field-error';
            return;
        }

        // Check allowed characters.
        if (!/^[a-zA-Z0-9]+$/.test(username)) {
            usernameFeedback.textContent =
                'Username may contain only letters and numbers.';
            usernameFeedback.className = 'field-error';
            return;
        }

        // Check username availability.
        usernameTimer = setTimeout(async function () {
            usernameRequest = new AbortController();

            try {
                const response = await fetch(
                    'check_username.php?username=' + encodeURIComponent(username),
                    {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        },
                        signal: usernameRequest.signal
                    }
                );

                const result = await response.json();

                if (usernameInput.value.trim() !== username) {
                    return;
                }

                if (!response.ok || !result.success) {
                    usernameFeedback.textContent =
                        result.message || 'Unable to check username right now.';
                    usernameFeedback.className = 'field-error';
                    return;
                }

                if (!result.available) {
                    usernameFeedback.textContent = result.message;
                    usernameFeedback.className = 'field-error';
                } else {
                    usernameFeedback.textContent = 'Username is available.';
                    usernameFeedback.className = 'field-success';
                }

            } catch (error) {
                if (error.name !== 'AbortError') {
                    usernameFeedback.textContent =
                        'Unable to check username right now. Please try again.';
                    usernameFeedback.className = 'field-error';
                }
            }
        }, 500);
}

    extensionSelect.addEventListener('change', toggleOtherExtension);

    usernameInput.addEventListener('input', function () {
        usernameInput.dataset.touched = 'true';
        checkUsername();
    });

    usernameInput.addEventListener('blur', function () {
        usernameInput.dataset.touched = 'true';
        checkUsername();
    });

    passwordInput.addEventListener('input', checkPasswordStrength);
    passwordInput.addEventListener('input', checkConfirmPassword);

    confirmPasswordInput.addEventListener('input', function () {
        confirmPasswordInput.dataset.touched = 'true';
        checkConfirmPassword();
    });

    confirmPasswordInput.addEventListener('blur', function () {
        confirmPasswordInput.dataset.touched = 'true';
        checkConfirmPassword();
    });

    registerForm.addEventListener('submit', function (event) {
        // Validate CAPTCHA before running the other registration checks.
        const captchaInput = document.getElementById('captcha_answer');
        const captchaError = document.getElementById('captcha-error');

        if (captchaInput && captchaError && captchaInput.value.trim() === '') {
            event.preventDefault();
            captchaError.textContent = 'Please enter the CAPTCHA code.';
            captchaInput.focus();
            return;
        }

        if (captchaError) {
            captchaError.textContent = '';
        }
        checkPasswordStrength();

        birthDateInput.dataset.touched = 'true';
        const isBirthDateValid = checkBirthDate();

        sexInput.dataset.touched = 'true';
        const isSexValid = checkSex();

        contactNumberInput.dataset.touched = 'true';
        const isContactNumberValid = checkContactNumber();

        purokStreetInput.dataset.touched = 'true';
        const isPurokStreetValid = checkPurokStreet();

        barangayInput.dataset.touched = 'true';
        const isBarangayValid = checkBarangay();

        municipalityCityInput.dataset.touched = 'true';
        const isMunicipalityCityValid = checkMunicipalityCity();

        provinceInput.dataset.touched = 'true';
        const isProvinceValid = checkProvince();

        countryInput.dataset.touched = 'true';
        const isCountryValid = checkCountry();

        zipCodeInput.dataset.touched = 'true';
        const isZipCodeValid = checkZipCode();

        civilStatusInput.dataset.touched = 'true';
        const isCivilStatusValid = checkCivilStatus();

        nationalIDInput.dataset.touched = 'true';
        const isNationalIDValid = checkNationalID();

        securityQ1Input.dataset.touched = 'true';
        const isSecurityQ1Valid = checkSecurityQ1();

        securityQ4Input.dataset.touched = 'true';
        const isSecurityQ4Valid = checkSecurityQ4();

        securityQ6Input.dataset.touched = 'true';
        const isSecurityQ6Valid = checkSecurityQ6();

        const isPrivacyConsentValid = checkPrivacyConsent();

        if (!isBirthDateValid) {
            event.preventDefault();
            birthDateInput.focus();
            return;
        }

        if (!isSexValid) {
            event.preventDefault();
            sexInput.focus();
            return;
        }

        if (!isContactNumberValid) {
            event.preventDefault();
            contactNumberInput.focus();
            return;
        }

        if (!isPurokStreetValid) {
            event.preventDefault();
            purokStreetInput.focus();
            return;
        }

        if (!isBarangayValid) {
            event.preventDefault();
            barangayInput.focus();
            return;
        }

        if (!isMunicipalityCityValid) {
            event.preventDefault();
            municipalityCityInput.focus();
            return;
        }

        if (!isProvinceValid) {
            event.preventDefault();
            provinceInput.focus();
            return;
        }

        if (!isCountryValid) {
            event.preventDefault();
            countryInput.focus();
            return;
        }

        if (!isZipCodeValid) {
            event.preventDefault();
            zipCodeInput.focus();
            return;
        }

        if (!isCivilStatusValid) {
            event.preventDefault();
            civilStatusInput.focus();
            return;
        }

        if (!isNationalIDValid) {
            event.preventDefault();
            nationalIDInput.focus();
            return;
        }

        if (!isSecurityQ1Valid) {
            event.preventDefault();
            securityQ1Input.focus();
            return;
        }

        if (!isSecurityQ4Valid) {
            event.preventDefault();
            securityQ4Input.focus();
            return;
        }

        if (!isSecurityQ6Valid) {
            event.preventDefault();
            securityQ6Input.focus();
            return;
        }

        if (!isPrivacyConsentValid) {
            event.preventDefault();
            privacyConsentInput.focus();
            return;
        }

        if (passwordInput.dataset.strength === 'weak') {
            event.preventDefault();
            passwordStrength.textContent =
                'Password Strength: Weak. Please use a stronger password.';
            passwordInput.focus();
        }
    });

    idNumberInput.addEventListener('input', function () {
        if (idNumberInput.value.length > 0) {
            checkIDNumber();
        } else {
            idNumberFeedback.textContent = '';
        }
    });

    idNumberInput.addEventListener('blur', function () {
        checkIDNumber();
    });

    emailInput.addEventListener('input', function () {
        emailInput.dataset.touched = 'true';
        checkEmail();
    });

    emailInput.addEventListener('blur', function () {
        emailInput.dataset.touched = 'true';
        checkEmail();
    });

    firstNameInput.addEventListener('input', function () {
        firstNameInput.dataset.touched = 'true';
        checkFirstName();
    });

    firstNameInput.addEventListener('blur', function () {
        firstNameInput.dataset.touched = 'true';
        checkFirstName();
    });

    middleNameInput.addEventListener('input', checkMiddleName);
    middleNameInput.addEventListener('blur', checkMiddleName);

    lastNameInput.addEventListener('input', function () {
        lastNameInput.dataset.touched = 'true';
        checkLastName();
    });

    lastNameInput.addEventListener('blur', function () {
        lastNameInput.dataset.touched = 'true';
        checkLastName();
    });

    extensionSelect.addEventListener('change', function () {
        toggleOtherExtension();
        checkNameExtension();
    });

    otherExtension.addEventListener('input', checkNameExtension);
    otherExtension.addEventListener('blur', checkNameExtension);

    calculateAge();
    toggleOtherExtension();

    birthDateInput.addEventListener('input', function () {
        birthDateInput.dataset.touched = 'true';
        checkBirthDate();
    });

    birthDateInput.addEventListener('change', function () {
        birthDateInput.dataset.touched = 'true';
        checkBirthDate();
    });

    birthDateInput.addEventListener('blur', function () {
       birthDateInput.dataset.touched = 'true';
       checkBirthDate();
    });

    sexInput.addEventListener('change', function () {
        sexInput.dataset.touched = 'true';
        checkSex();
    });

    sexInput.addEventListener('blur', function () {
        sexInput.dataset.touched = 'true';
        checkSex();
    });

    contactNumberInput.addEventListener('input', function () {
        contactNumberInput.dataset.touched = 'true';
        checkContactNumber();
    });

    contactNumberInput.addEventListener('blur', function () {
        contactNumberInput.dataset.touched = 'true';
        checkContactNumber();
    });

    purokStreetInput.addEventListener('input', function () {
        purokStreetInput.dataset.touched = 'true';
        checkPurokStreet();
    });

    purokStreetInput.addEventListener('blur', function () {
        purokStreetInput.dataset.touched = 'true';
        checkPurokStreet();
    });

    barangayInput.addEventListener('input', function () {
        barangayInput.dataset.touched = 'true';
        checkBarangay();
    });

    barangayInput.addEventListener('blur', function () {
        barangayInput.dataset.touched = 'true';
        checkBarangay();
    });

    municipalityCityInput.addEventListener('input', function () {
        municipalityCityInput.dataset.touched = 'true';
        checkMunicipalityCity();
    });

    municipalityCityInput.addEventListener('blur', function () {
        municipalityCityInput.dataset.touched = 'true';
        checkMunicipalityCity();
    });

    provinceInput.addEventListener('input', function () {
        provinceInput.dataset.touched = 'true';
        checkProvince();
    });

    provinceInput.addEventListener('blur', function () {
        provinceInput.dataset.touched = 'true';
        checkProvince();
    });

    countryInput.addEventListener('input', function () {
        countryInput.dataset.touched = 'true';
        checkCountry();
    });

    countryInput.addEventListener('blur', function () {
        countryInput.dataset.touched = 'true';
        checkCountry();
    });

    zipCodeInput.addEventListener('input', function () {
        zipCodeInput.dataset.touched = 'true';
        checkZipCode();
    });

    zipCodeInput.addEventListener('blur', function () {
        zipCodeInput.dataset.touched = 'true';
        checkZipCode();
    });

    civilStatusInput.addEventListener('change', function () {
        civilStatusInput.dataset.touched = 'true';
        checkCivilStatus();
    });

    civilStatusInput.addEventListener('blur', function () {
        civilStatusInput.dataset.touched = 'true';
        checkCivilStatus();
    });

    nationalIDInput.addEventListener('input', function () {
        nationalIDInput.dataset.touched = 'true';
        checkNationalID();
    });

    nationalIDInput.addEventListener('blur', function () {
        nationalIDInput.dataset.touched = 'true';
        checkNationalID();
    });

    securityQ1Input.addEventListener('input', function () {
        securityQ1Input.dataset.touched = 'true';
        checkSecurityQ1();
    });

    securityQ1Input.addEventListener('blur', function () {
        securityQ1Input.dataset.touched = 'true';
        checkSecurityQ1();
    });

    securityQ4Input.addEventListener('input', function () {
        securityQ4Input.dataset.touched = 'true';
        checkSecurityQ4();
    });

    securityQ4Input.addEventListener('blur', function () {
        securityQ4Input.dataset.touched = 'true';
        checkSecurityQ4();
    });

    securityQ6Input.addEventListener('input', function () {
        securityQ6Input.dataset.touched = 'true';
        checkSecurityQ6();
    });

    securityQ6Input.addEventListener('blur', function () {
        securityQ6Input.dataset.touched = 'true';
        checkSecurityQ6();
    });

    privacyConsentInput.addEventListener('change', function () {
        checkPrivacyConsent();
    });

    // Validate CAPTCHA when the user types or leaves the field.
    const captchaInput = document.getElementById('captcha_answer');
    const captchaError = document.getElementById('captcha-error');

    function checkCaptchaField() {
        if (captchaInput.value.trim() === '') {
            captchaError.textContent =
                captchaInput.dataset.touched === 'true'
                    ? 'CAPTCHA is required.'
                    : '';
            return false;
        }

        captchaError.textContent = '';
        return true;
    }

    captchaInput.addEventListener('input', function () {
        captchaInput.dataset.touched = 'true';
        checkCaptchaField();
    });

    captchaInput.addEventListener('blur', function () {
        captchaInput.dataset.touched = 'true';
        checkCaptchaField();
    });
    // Refresh CAPTCHA image and generate a new challenge.
    const captchaImage = document.getElementById('captcha-image');
    const refreshCaptchaButton = document.getElementById('refresh-captcha');

    if (captchaImage && refreshCaptchaButton) {
        refreshCaptchaButton.addEventListener('click', function () {
            captchaImage.src = 'captcha.php?refresh=' + Date.now();
        });
    }

});

