document.addEventListener('DOMContentLoaded', function () {
    const WARNING_TIME = 5 * 1000;
    const LOGOUT_TIME = 10 * 1000;
    const ACTIVITY_INTERVAL = 2 * 1000;

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
    warningBox.className = 'session-warning';
    warningBox.setAttribute('role', 'alert');
    warningBox.setAttribute('aria-live', 'assertive');

    warningBox.innerHTML = `
        <strong>Session Expiring Soon</strong>
        <p>Your session will expire due to inactivity.</p>
        <p id="session-countdown">Logging out in 10 seconds.</p>
        <button type="button" id="continue-session" class="session-warning__button">
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

    // Show the five-second warning.
    function showWarning() {
        warningBox.classList.add('is-visible');

        let remainingSeconds = 5;

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

    // Redirect after ten seconds of inactivity.
    async function logoutUser() {
          clearTimeout(warningTimer);
          clearTimeout(logoutTimer);
          clearInterval(countdownTimer);

          warningBox.classList.remove('is-visible');
          warningBox.remove();

          try {
            await fetch(logoutForm.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    csrf_token: csrfToken
                })
            });
          } catch (error) {
            console.error('Automatic logout request failed.');
          }

           window.location.replace(loginUrl.href);
        }

    // Reset the inactivity timers.
    function resetTimers() {
        clearTimeout(warningTimer);
        clearTimeout(logoutTimer);
        clearInterval(countdownTimer);

        warningBox.classList.remove('is-visible');

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
