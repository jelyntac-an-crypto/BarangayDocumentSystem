document.addEventListener('DOMContentLoaded', function () {
    const resendButton = document.getElementById('resend-otp-button');
    const countdownText = document.getElementById('resend-countdown');
    const messageElement = document.querySelector('[role="alert"]');

    if (!resendButton || !countdownText) {
        return;
    }

    const cooldownSeconds = 60;
    const storageKey = 'registration_otp_resend_time';

    // Start countdown only after successful email delivery.
    if (
        messageElement &&
        messageElement.textContent.trim() ===
        'A new verification code has been sent to your registered email.'
    ) {
        const endTime = Math.floor(Date.now() / 1000) + cooldownSeconds;
        localStorage.setItem(storageKey, String(endTime));
    }

    function startCountdown(seconds) {
        let remaining = seconds;

        resendButton.disabled = true;

        function updateCountdown() {
            countdownText.textContent =
                'You can request another OTP in ' + remaining + ' seconds.';

            if (remaining <= 0) {
                clearInterval(timer);
                resendButton.disabled = false;
                countdownText.textContent =
                    'You may request a new OTP now.';

                localStorage.removeItem(storageKey);
                return;
            }

            remaining--;
        }

        updateCountdown();

        const timer = setInterval(updateCountdown, 1000);
    }

    const savedTime = Number(localStorage.getItem(storageKey));
    const currentTime = Math.floor(Date.now() / 1000);

    if (savedTime > currentTime) {
        startCountdown(savedTime - currentTime);
    } else {
        resendButton.disabled = false;
        countdownText.textContent = 'You may request a new OTP now.';
        localStorage.removeItem(storageKey);
    }
});