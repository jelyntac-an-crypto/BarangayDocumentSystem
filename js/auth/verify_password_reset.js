document.addEventListener('DOMContentLoaded', function () {
    const countdown = document.getElementById('resend-countdown');
    const resendButton = document.getElementById('resend-reset-code-button');

    if (!countdown || !resendButton) {
        return;
    }

    let remaining = Number(countdown.dataset.remaining || 0);
    if (remaining <= 0) {
        resendButton.disabled = false;
        countdown.textContent = 'You can request another reset code now.';
        return;
    }

    resendButton.disabled = true;

    function updateCountdown() {
        if (remaining <= 0) {
            countdown.textContent = 'You can request another reset code now.';
            resendButton.disabled = false;
            return;
        }

        countdown.textContent = 'You can request another reset code in '
            + remaining + ' second' + (remaining === 1 ? '' : 's') + '.';
        remaining -= 1;
        window.setTimeout(updateCountdown, 1000);
    }

    updateCountdown();
});
