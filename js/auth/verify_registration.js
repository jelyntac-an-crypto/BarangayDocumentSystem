document.addEventListener('DOMContentLoaded', function () {
    const resendButton = document.getElementById('resend-otp-button');
    const countdownText = document.getElementById('resend-countdown');

    if (!resendButton || !countdownText) {
        return;
    }

    let remaining = Number(countdownText.dataset.remaining || 0);
    if (remaining <= 0) {
        resendButton.disabled = false;
        countdownText.textContent = 'You may request a new OTP now.';
        return;
    }

    resendButton.disabled = true;

    function updateCountdown() {
        if (remaining <= 0) {
            resendButton.disabled = false;
            countdownText.textContent = 'You may request a new OTP now.';
            return;
        }

        countdownText.textContent =
            'You can request another OTP in ' + remaining +
            ' second' + (remaining === 1 ? '' : 's') + '.';
        remaining -= 1;
        window.setTimeout(updateCountdown, 1000);
    }

    updateCountdown();
});
