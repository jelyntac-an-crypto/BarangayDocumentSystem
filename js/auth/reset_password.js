document.addEventListener('DOMContentLoaded', function () {
    const passwordInput = document.getElementById('password');
    const strengthLabel = document.getElementById('strength-label');
    const strengthMeter = document.getElementById('strength-meter');

    if (!passwordInput || !strengthLabel || !strengthMeter) return;

    passwordInput.addEventListener('input', function () {
        const password = passwordInput.value;
        const checks = [/[a-z]/, /[A-Z]/, /[0-9]/, /[^a-zA-Z0-9]/];
        const criteriaCount = checks.filter((pattern) => pattern.test(password)).length;
        let strength = 'Weak';

        if (password.length >= 8 && password.length <= 64) {
            if (criteriaCount === 3) strength = 'Medium';
            if (criteriaCount === 4) strength = 'Strong';
        }

        strengthLabel.textContent = strength;
        strengthMeter.dataset.criteria = String(criteriaCount);
        strengthMeter.dataset.strength = strength;
        strengthMeter.setAttribute('aria-valuenow', String(criteriaCount));
    });
});
