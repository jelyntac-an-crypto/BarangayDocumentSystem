document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.request-filter');
    const searchInput = document.getElementById('search');
    const statusSelect = document.getElementById('status');
    const resultsBody = document.getElementById('requestResults');
    const requestCount = document.getElementById('requestCount');

    if (!form || !searchInput || !statusSelect || !resultsBody || !requestCount) {
        return;
    }

    let debounceTimer;
    let controller;

    function escapeHTML(value) {
        return String(value).replace(/[&<>"']/g, character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[character]);
    }

    async function loadRequests() {
        if (controller) controller.abort();
        controller = new AbortController();

        const params = new URLSearchParams({
            search: searchInput.value,
            status: statusSelect.value
        });

        try {
            const response = await fetch(`${form.action}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal
            });

            if (!response.ok) throw new Error('Unable to load requests.');

            const data = await response.json();
            const requests = data.requests || [];

            if (requests.length === 0) {
                resultsBody.innerHTML = `
                    <tr><td colspan="7" class="empty-state">
                        No document requests found.
                    </td></tr>`;
            } else {
                resultsBody.innerHTML = requests.map(request => `
                    <tr>
                        <td>#${Number(request.id)}</td>
                        <td>${escapeHTML(request.resident_name)}</td>
                        <td>${escapeHTML(request.document_name)}</td>
                        <td class="purpose-cell">${escapeHTML(request.purpose)}</td>
                        <td>
                            <span class="status-badge ${escapeHTML(request.status_class)}">
                                ${escapeHTML(request.status)}
                            </span>
                        </td>
                        <td>${escapeHTML(request.created_at)}</td>
                        <td>
                            <a class="view-button"
                               href="review_request.php?id=${Number(request.id)}">
                                Review
                            </a>
                        </td>
                    </tr>
                `).join('');
            }

            requestCount.textContent = `${requests.length} request(s) found.`;

            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('search', searchInput.value);
            currentUrl.searchParams.set('status', statusSelect.value);
            window.history.replaceState({}, '', currentUrl);

        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error(error);
                requestCount.textContent = 'Unable to load requests. Please try again.';
            }
        }
    }

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadRequests, 300);
    });

    statusSelect.addEventListener('change', loadRequests);

    form.addEventListener('submit', event => {
        event.preventDefault();
        clearTimeout(debounceTimer);
        loadRequests();
    });
});