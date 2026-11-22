document.addEventListener('DOMContentLoaded', function () {
    const captchaImage = document.getElementById('captcha-image');
    const refreshButton = document.getElementById('refresh-captcha');
    const captchaInput = document.getElementById('captcha_answer');
    const notice = document.querySelector('.recovery-message');
    const cooldownMatch = notice?.textContent.match(
        /(?:Please wait\s+(\d+)\s+seconds before requesting another reset code|maximum of 3 reset-code resends within 15 minutes\. Please try again in\s+(\d+)\s+seconds)/i
    );

    if (cooldownMatch) {
        const securityForm = document.querySelector(
            'form input[name="step"][value="security"]'
        )?.closest('form');
        const submitButton = securityForm?.querySelector('button[type="submit"]');

        if (submitButton) {
            let remaining = Number(cooldownMatch[1] || cooldownMatch[2]);
            notice.setAttribute('aria-live', 'polite');
            submitButton.disabled = true;

            const updateCountdown = function () {
                if (remaining <= 0) {
                    notice.textContent = 'You can request another reset code now.';
                    submitButton.disabled = false;
                    return;
                }

                notice.textContent =
                    'Please wait ' + remaining +
                    ' second' + (remaining === 1 ? '' : 's') +
                    ' before requesting another reset code.';
                remaining -= 1;
                window.setTimeout(updateCountdown, 1000);
            };

            updateCountdown();
        }
    }

    if (!captchaImage || !refreshButton) {
        return;
    }

    refreshButton.addEventListener('click', function () {
        captchaImage.src =
            'captcha.php?purpose=forgot_password&t=' + Date.now();
        if (captchaInput) {
            captchaInput.value = '';
        }
    });
});
