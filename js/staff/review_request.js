document.addEventListener('DOMContentLoaded', function () {
    const statusSelect = document.getElementById('new_status');
    const rejectionGroup = document.getElementById('rejection-group');
    const rejectionInput = document.getElementById('rejection_reason');

    if (!statusSelect || !rejectionGroup || !rejectionInput) return;

    function updateRejectionField() {
        const isRejected = statusSelect.value === 'Rejected';
        rejectionGroup.hidden = !isRejected;
        rejectionInput.required = isRejected;
        if (!isRejected) rejectionInput.value = '';
    }

    statusSelect.addEventListener('change', updateRejectionField);
    updateRejectionField();
});
