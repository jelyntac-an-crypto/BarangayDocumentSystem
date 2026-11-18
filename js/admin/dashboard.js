"use strict";

document.addEventListener("DOMContentLoaded", () => {
    const endpoint = "dashboard_data.php";

    const summaryFields = {
        successful_logins: "successful-logins",
        failed_logins: "failed-logins",
        locked_accounts: "locked-accounts",
        blocked_ips: "blocked-ips",
        otps_sent: "otps-sent",
        otp_failures: "otp-failures",
        alert_emails_sent: "alert-emails-sent"
    };

    const elements = {
        updated: document.getElementById("dashboard-updated"),
        message: document.getElementById("dashboard-message"),
        topIps: document.getElementById("top-ips-list"),
        flags: document.getElementById("security-flags-list"),
        events: document.getElementById("recent-events-body"),
        chart: document.getElementById("failed-logins-chart"),
        filterForm: document.getElementById("event-filter-form"),
        resetFilters: document.getElementById("reset-filters")
    };

    let failedLoginsChart = null;
    let refreshTimer = null;
    let isLoading = false;

    function createCell(value, className = "") {
        const cell = document.createElement("td");
        cell.textContent = value ?? "";

        if (className) {
            cell.className = className;
        }

        return cell;
    }

    function showMessage(message, isError = false) {
        if (!elements.message) return;

        elements.message.textContent = message;
        elements.message.className = isError
            ? "dashboard-message dashboard-error"
            : "dashboard-message";

        elements.message.hidden = false;
    }

    function hideMessage() {
        if (!elements.message) return;

        elements.message.textContent = "";
        elements.message.hidden = true;
    }

    function setSummary(summary) {
        Object.entries(summaryFields).forEach(([key, id]) => {
            const element = document.getElementById(id);

            if (element) {
                const value = Number(summary[key] ?? 0);
                element.textContent = Number.isFinite(value)
                    ? value.toLocaleString()
                    : "0";
            }
        });
    }

    function renderChart(chartData) {
        if (!elements.chart || typeof Chart === "undefined") {
            return;
        }

        const labels = Array.isArray(chartData.labels)
            ? chartData.labels
            : [];

        const values = Array.isArray(chartData.values)
            ? chartData.values.map(value => Number(value) || 0)
            : [];

        if (failedLoginsChart) {
            failedLoginsChart.data.labels = labels;
            failedLoginsChart.data.datasets[0].data = values;
            failedLoginsChart.update();
            return;
        }

        failedLoginsChart = new Chart(elements.chart, {
            type: "bar",
            data: {
                labels: labels,
                datasets: [{
                    label: "Failed log-ins",
                    data: values,
                    backgroundColor: "#3b82f6",
                    borderColor: "#2563eb",
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true
                    }
                }
            }
        });
    }

    function renderTopIps(ips) {
        if (!elements.topIps) return;

        elements.topIps.replaceChildren();

        if (!Array.isArray(ips) || ips.length === 0) {
            const empty = document.createElement("p");
            empty.className = "dashboard-empty";
            empty.textContent = "No failed login IP addresses found.";
            elements.topIps.appendChild(empty);
            return;
        }

        ips.forEach(item => {
            const row = document.createElement("div");
            row.className = "data-list-item";

            const main = document.createElement("div");
            main.className = "data-list-main";

            const ip = document.createElement("strong");
            ip.textContent = item.ip_address ?? "Unknown IP";

            const count = document.createElement("small");
            count.textContent =
                `${Number(item.failure_count) || 0} failed attempts`;

            main.append(ip, count);

            const status = document.createElement("span");
            const blocked = Number(item.is_blocked) === 1;

            status.className = blocked
                ? "status-badge status-active"
                : "status-badge status-normal";

            status.textContent = blocked ? "Blocked" : "Not blocked";

            row.append(main, status);
            elements.topIps.appendChild(row);
        });
    }

    function renderFlags(flags) {
        if (!elements.flags) return;

        elements.flags.replaceChildren();

        if (!Array.isArray(flags) || flags.length === 0) {
            const empty = document.createElement("p");
            empty.className = "dashboard-empty";
            empty.textContent = "No security flags detected.";
            elements.flags.appendChild(empty);
            return;
        }

        flags.forEach(flag => {
            const row = document.createElement("div");
            row.className = "data-list-item";

            const main = document.createElement("div");
            main.className = "data-list-main";

            const type = document.createElement("strong");
            type.textContent = flag.flag_type ?? "Security finding";

            const description = document.createElement("small");
            description.textContent = flag.description ?? "";

            const detected = document.createElement("small");
            detected.textContent = `Detected: ${flag.detected_at ?? ""}`;

            main.append(type, description, detected);

            const severity = document.createElement("span");
            const level = String(flag.severity ?? "low").toLowerCase();

            severity.className = `status-badge severity-${level}`;
            severity.textContent = level.toUpperCase();

            row.append(main, severity);
            elements.flags.appendChild(row);
        });
    }

    function renderEvents(events) {
        if (!elements.events) return;

        elements.events.replaceChildren();

        if (!Array.isArray(events) || events.length === 0) {
            const row = document.createElement("tr");
            const cell = document.createElement("td");

            cell.colSpan = 6;
            cell.className = "dashboard-empty";
            cell.textContent = "No events found.";

            row.appendChild(cell);
            elements.events.appendChild(row);
            return;
        }

        events.forEach(event => {
            const row = document.createElement("tr");

            row.append(
                createCell(event.created_at),
                createCell(event.username_typed || event.username || "Unknown"),
                createCell(event.event_type),
                createCell(event.description, "event-description"),
                createCell(event.ip_address),
                createCell(event.user_id || "N/A")
            );

            elements.events.appendChild(row);
        });
    }

    function getFilters() {
        if (!elements.filterForm) return "";

        const params = new URLSearchParams();

        const fields = [
            "from",
            "to",
            "event_type",
            "username",
            "ip"
        ];

        fields.forEach(name => {
            const field = elements.filterForm.elements.namedItem(name);

            if (field && field.value.trim() !== "") {
                params.set(name, field.value.trim());
            }
        });

        return params.toString();
    }

    async function loadDashboard(applyFilters = false) {
        if (isLoading) return;

        isLoading = true;

        try {
            const filters = applyFilters ? getFilters() : "";
            const url = filters
                ? `${endpoint}?${filters}`
                : endpoint;

            const response = await fetch(url, {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store",
                headers: {
                    "Accept": "application/json"
                }
            });

            if (response.status === 401 || response.status === 403) {
                showMessage(
                    "Your session is not authorized. Please log in again.",
                    true
                );
                return;
            }

            if (!response.ok) {
                throw new Error("Unable to load dashboard data.");
            }

            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            hideMessage();

            setSummary(data.summary ?? {});
            renderChart(data.failed_logins_chart ?? {});
            renderTopIps(data.top_ips ?? []);
            renderFlags(data.security_flags ?? []);
            renderEvents(data.recent_events ?? []);

            if (elements.updated) {
                elements.updated.textContent =
                    `Last updated: ${data.generated_at ?? "Unknown"}`;
            }
        } catch (error) {
            showMessage(
                error.message || "An error occurred while loading the dashboard.",
                true
            );
        } finally {
            isLoading = false;
        }
    }

    if (elements.filterForm) {
        elements.filterForm.addEventListener("submit", event => {
            event.preventDefault();
            loadDashboard(true);
        });
    }

    if (elements.resetFilters) {
        elements.resetFilters.addEventListener("click", () => {
            if (elements.filterForm) {
                elements.filterForm.reset();
            }

            loadDashboard(true);
        });
    }

    loadDashboard();

    refreshTimer = window.setInterval(() => {
        loadDashboard(true);
    }, 60000);

    window.addEventListener("beforeunload", () => {
        if (refreshTimer !== null) {
            window.clearInterval(refreshTimer);
        }
    });
});
