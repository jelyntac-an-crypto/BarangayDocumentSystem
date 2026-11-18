
document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('login-form');

    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const captchaInput = document.getElementById('captcha_answer');

    const usernameError = document.getElementById('username-error');
    const passwordError = document.getElementById('password-error');
    const captchaError = document.getElementById('captcha-error');

    const togglePassword = document.getElementById('toggle-password');
    const captchaImage = document.getElementById('captcha-image');
    const refreshCaptcha = document.getElementById('refresh-captcha');

    const lockoutMessage = document.getElementById('lockout-message');
    const loginButton = document.getElementById('login-button');

    let countdownInterval = null;

    function startLockoutCountdown() {
    let remaining = Number(lockoutMessage?.dataset.remaining || 0);

    if (remaining <= 0) {
        return;
    }

    loginButton.disabled = true;

    const registerLink = document.getElementById('register-link');

    if (registerLink) {
        registerLink.style.pointerEvents = 'none';
        registerLink.style.opacity = '0.5';
        registerLink.setAttribute('aria-disabled', 'true');
    }

    function updateCountdown() {
        if (remaining <= 0) {
            clearInterval(countdownInterval);

            loginButton.disabled = false;

            if (registerLink) {
                registerLink.style.pointerEvents = '';
                registerLink.style.opacity = '';
                registerLink.removeAttribute('aria-disabled');
            }

            lockoutMessage.classList.remove('lockout-message');
            lockoutMessage.textContent =
                'Lockout period ended. You may try logging in again.';

            return;
        }

        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;

        const formattedTime =
            String(minutes).padStart(2, '0') + ':' +
            String(seconds).padStart(2, '0');

        lockoutMessage.textContent =
            'Account temporarily locked. Please try again in ' +
            formattedTime + '.';

        remaining--;
    }

    updateCountdown();
    countdownInterval = setInterval(updateCountdown, 1000);
}

    // Validate username while typing.
    function checkUsername() {
        const username = usernameInput.value.trim();

        usernameError.textContent = '';

        if (username === '') {
            if (usernameInput.dataset.touched === 'true') {
                usernameError.textContent = 'Username is required.';
            }
            return false;
        }

        if (username.length < 8) {
            usernameError.textContent =
                'Username must contain at least 8 characters.';
            return false;
        }

        if (username.length > 19) {
            usernameError.textContent =
                'Username must not exceed 19 characters.';
            return false;
        }

        if (!/^[A-Za-z]/.test(username)) {
            usernameError.textContent =
                'Username must start with a letter.';
            return false;
        }

        if (!/^[A-Za-z0-9]+$/.test(username)) {
            usernameError.textContent =
                'Username may contain only letters and numbers.';
            return false;
        }

        return true;
    }

    // Validate password while typing.
    function checkPassword() {
        const password = passwordInput.value;

        passwordError.textContent = '';

        if (password === '') {
            if (passwordInput.dataset.touched === 'true') {
                passwordError.textContent = 'Password is required.';
            }
            return false;
        }

        if (password.length < 8) {
            passwordError.textContent =
                'Password must contain at least 8 characters.';
            return false;
        }

        if (password.length > 64) {
            passwordError.textContent =
                'Password must not exceed 64 characters.';
            return false;
        }

        return true;
    }

    // Validate CAPTCHA while typing.
    function checkCaptcha() {
        const captcha = captchaInput.value.trim();

        captchaError.textContent = '';

        if (captcha === '') {
            if (captchaInput.dataset.touched === 'true') {
                captchaError.textContent = 'CAPTCHA is required.';
            }
            return false;
        }

        if (captcha.length !== 6) {
            captchaError.textContent =
                'CAPTCHA must contain exactly 6 characters.';
            return false;
        }

        if (!/^[A-Za-z0-9]{6}$/.test(captcha)) {
            captchaError.textContent =
                'CAPTCHA can only contain letters and numbers.';
            return false;
        }

        return true;
    }

    // Show or hide the password.
    togglePassword.addEventListener('click', function () {
        const isHidden = passwordInput.type === 'password';

        passwordInput.type = isHidden ? 'text' : 'password';
        togglePassword.textContent = isHidden ? 'Hide' : 'Show';
        togglePassword.setAttribute(
            'aria-label',
            isHidden ? 'Hide password' : 'Show password'
        );
    });

    // Refresh the CAPTCHA image.
    refreshCaptcha.addEventListener('click', function () {
        captchaImage.src =
            'captcha.php?purpose=login&refresh=' + Date.now();

        captchaInput.value = '';
        captchaInput.dataset.touched = 'false';
        captchaError.textContent = '';
    });

    // Username live validation.
    usernameInput.addEventListener('input', function () {
        usernameInput.dataset.touched = 'true';
        checkUsername();
    });

    usernameInput.addEventListener('blur', function () {
        usernameInput.dataset.touched = 'true';
        checkUsername();
    });

    // Password live validation.
    passwordInput.addEventListener('input', function () {
        passwordInput.dataset.touched = 'true';
        checkPassword();
    });

    passwordInput.addEventListener('blur', function () {
        passwordInput.dataset.touched = 'true';
        checkPassword();
    });

    // CAPTCHA live validation.
    captchaInput.addEventListener('input', function () {
        captchaInput.dataset.touched = 'true';
        checkCaptcha();
    });

    captchaInput.addEventListener('blur', function () {
        captchaInput.dataset.touched = 'true';
        checkCaptcha();
    });

    // Validate all fields before submitting.
    loginForm.addEventListener('submit', function (event) {
        usernameInput.dataset.touched = 'true';
        passwordInput.dataset.touched = 'true';
        captchaInput.dataset.touched = 'true';

        const isUsernameValid = checkUsername();
        const isPasswordValid = checkPassword();
        const isCaptchaValid = checkCaptcha();

        if (!isUsernameValid || !isPasswordValid || !isCaptchaValid) {
            event.preventDefault();

            if (!isUsernameValid) {
                usernameInput.focus();
            } else if (!isPasswordValid) {
                passwordInput.focus();
            } else {
                captchaInput.focus();
            }
        }
    });

    // Start the lockout countdown when the page loads.
    startLockoutCountdown();

        loginForm.addEventListener('submit', function (event) {
        usernameInput.dataset.touched = 'true';
        passwordInput.dataset.touched = 'true';
        captchaInput.dataset.touched = 'true';

        const isUsernameValid = checkUsername();
        const isPasswordValid = checkPassword();
        const isCaptchaValid = checkCaptcha();

        if (!isUsernameValid || !isPasswordValid || !isCaptchaValid) {
            event.preventDefault();

            if (!isUsernameValid) {
                usernameInput.focus();
            } else if (!isPasswordValid) {
                passwordInput.focus();
            } else {
                captchaInput.focus();
            }
        }
    });

    startLockoutCountdown();
});

