document.addEventListener('DOMContentLoaded', function () {
    const WARNING_TIME = 4 * 60 * 1000;
    const LOGOUT_TIME = 5 * 60 * 1000;
    const ACTIVITY_INTERVAL = 30 * 1000;

    let warningTimer;
    let logoutTimer;
    let countdownTimer;
    let lastActivityRequest = 0;

    const script = document.querySelector('script[src*="session.js"]');

    if (!script) {
        return;
    }

    const activityUrl = new URL('../php/session_activity.php', script.src);
    const loginUrl = new URL('../php/auth/login.php?timeout=1', script.src);

    const logoutForm = document.querySelector('.logout-form');
    const csrfInput = logoutForm?.querySelector('[name="csrf_token"]');

    if (!logoutForm || !csrfInput) {
        return;
    }

    const csrfToken = csrfInput.value;

    const warningBox = document.createElement('div');

    warningBox.id = 'session-warning';
    warningBox.setAttribute('role', 'alert');
    warningBox.setAttribute('aria-live', 'assertive');

    warningBox.style.cssText = `
        display: none;
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 350px;
        padding: 18px;
        background: #fff3cd;
        color: #664d03;
        border: 1px solid #ffecb5;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        font-family: Arial, sans-serif;
    `;

    warningBox.innerHTML = `
        <strong>Session Expiring Soon</strong>
        <p>Your session will expire due to inactivity.</p>
        <p id="session-countdown">Logging out in 60 seconds.</p>
        <button type="button" id="continue-session">
            Continue Session
        </button>
    `;

    document.body.appendChild(warningBox);

    const countdownText = warningBox.querySelector('#session-countdown');
    const continueButton = warningBox.querySelector('#continue-session');

    // Send recent activity to the PHP server.
    async function updateServerActivity() {
        const now = Date.now();

        if (now - lastActivityRequest < ACTIVITY_INTERVAL) {
            return;
        }

        lastActivityRequest = now;

        try {
            const response = await fetch(activityUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    csrf_token: csrfToken
                })
            });

            if (response.status === 401 || response.status === 403) {
                window.location.replace(loginUrl.href);
            }
        } catch (error) {
            console.error('Session activity update failed.');
        }
    }

    // Show the one-minute warning.
    function showWarning() {
        warningBox.style.display = 'block';

        let remainingSeconds = 60;

        countdownText.textContent =
            'Logging out in ' + remainingSeconds + ' seconds.';

        countdownTimer = setInterval(function () {
            remainingSeconds--;

            countdownText.textContent =
                'Logging out in ' + remainingSeconds + ' seconds.';

            if (remainingSeconds <= 0) {
                clearInterval(countdownTimer);
            }
        }, 1000);
    }

    // Redirect after five minutes of inactivity.
    function logoutUser() {
        window.location.replace(loginUrl.href);
    }

    // Reset the inactivity timers.
    function resetTimers() {
        clearTimeout(warningTimer);
        clearTimeout(logoutTimer);
        clearInterval(countdownTimer);

        warningBox.style.display = 'none';

        warningTimer = setTimeout(showWarning, WARNING_TIME);
        logoutTimer = setTimeout(logoutUser, LOGOUT_TIME);
    }

    // Continue the session.
    continueButton.addEventListener('click', function () {
        resetTimers();
        updateServerActivity();
    });

    // Detect user activity.
    const activityEvents = [
        'mousemove',
        'mousedown',
        'keydown',
        'scroll',
        'touchstart',
        'click'
    ];

    activityEvents.forEach(function (eventName) {
        document.addEventListener(eventName, function () {
            resetTimers();
            updateServerActivity();
        }, { passive: true });
    });

    // Start the inactivity timers.
    resetTimers();
});
