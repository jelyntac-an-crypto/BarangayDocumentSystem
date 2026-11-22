document.addEventListener('DOMContentLoaded', function () {
    const resendButton = document.getElementById('resend-otp-button');
    const countdown = document.getElementById('resend-countdown');

    if (!resendButton || !countdown) {
        return;
    }

    let remaining = Number(countdown.dataset.remaining || 0);
    if (remaining <= 0) {
        return;
    }

    resendButton.disabled = true;

    function updateCountdown() {
        if (remaining <= 0) {
            countdown.textContent = 'You may request a new OTP now.';
            resendButton.disabled = false;
            return;
        }

        countdown.textContent = 'You can request another OTP in '
            + remaining + ' second' + (remaining === 1 ? '' : 's') + '.';
        remaining -= 1;
        window.setTimeout(updateCountdown, 1000);
    }

    updateCountdown();
});
