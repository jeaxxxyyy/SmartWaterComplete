const tokenKey = 'smart-water-token';
const adminEmail = 'admin@gmail.com';
const themeKey = 'smart-water-theme';

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

const formatNumber = (value, digits = 2) =>
    Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    });

const formatCurrency = (value) =>
    `PHP ${Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

const litersToCubicMeters = (value) => Number(value ?? 0) / 1000;

const formatCubicMeters = (liters) => `${formatNumber(litersToCubicMeters(liters))} m³`;

const formatCubicMeterValue = (value) => `${formatNumber(value)} m³`;

const formatDateTime = (value) => {
    if (!value) {
        return '--';
    }

    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};

const page = document.body.dataset.page ?? 'default';
const logoutButtons = $$('[data-logout]');
const statusLabel = $('[data-status]');
const tokenInput = $('[data-token-input]');
const saveTokenButton = $('[data-token-save]');
const refreshButton = $('[data-refresh]');
const dashboardFilterForm = $('[data-reading-filters]');
const adminUserFilterForm = $('[data-user-filters]');
const readingsPagers = $$('[data-readings-pager]');
const accountForm = $('[data-account-form]');
const passwordForm = $('[data-password-form]');
const toastStack = $('[data-toast-stack]');
const themeToggle = $('[data-theme-toggle]');

const applyTheme = (theme) => {
    const resolved = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.dataset.theme = resolved;
    localStorage.setItem(themeKey, resolved);
    if (themeToggle) {
        themeToggle.textContent = resolved === 'dark' ? 'Use light theme' : 'Use dark theme';
        themeToggle.setAttribute('aria-pressed', resolved === 'dark' ? 'true' : 'false');
    }
};

const showToast = (message, type = 'success') => {
    if (!toastStack || !message) return;
    const toast = document.createElement('div');
    toast.className = `toast-alert${type === 'error' ? ' is-error' : ''}`;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    toast.textContent = message;
    toastStack.appendChild(toast);
    window.setTimeout(() => toast.remove(), type === 'error' ? 6000 : 4000);
};

const errorMessage = (error, fallback = 'The action could not be completed.') => {
    try {
        const payload = JSON.parse(error?.message ?? '');
        return payload.message ?? Object.values(payload.errors ?? {}).flat()[0] ?? fallback;
    } catch {
        return fallback;
    }
};

const setText = (selector, value) => {
    $$(selector).forEach((node) => {
        node.textContent = value;
    });
};

const dashboardState = {
    page: 1,
    per_page: 10,
    from: '',
    to: '',
};
let consumptionHistory = { daily: [], weekly: [], monthly: [] };
let historyPeriod = 'daily';
let notificationObserver = null;
let householdChartObserver = null;
let adminChartObserver = null;
const notificationReadInFlight = new Set();

const adminState = {
    page: 1,
    per_page: 10,
    search: '',
    role: '',
    is_active: '',
};

const DASHBOARD_REFRESH_MS = 3000;
let dashboardRefreshTimer = null;
let dashboardRefreshInFlight = false;

const stopDashboardPolling = () => {
    if (dashboardRefreshTimer) {
        window.clearTimeout(dashboardRefreshTimer);
        dashboardRefreshTimer = null;
    }
};

const scheduleDashboardPolling = () => {
    stopDashboardPolling();

    if (page !== 'household' || !readToken()) {
        return;
    }

    dashboardRefreshTimer = window.setTimeout(() => {
        loadDashboard({ reschedule: true });
    }, DASHBOARD_REFRESH_MS);
};

const bindAdminUserDeleteActions = (container) => {
    if (!container || container.dataset.deleteBound === '1') {
        return;
    }

    container.dataset.deleteBound = '1';
    container.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-delete-user]');
        if (!button) {
            return;
        }

        const userId = button.dataset.deleteUser;
        const userName = button.dataset.userName || 'this user';

        if (!window.confirm(`Delete ${userName}?`)) {
            return;
        }

        try {
            await requestJson(`/api/users/${userId}`, { method: 'DELETE' });
            showToast(`${userName} was deleted successfully.`);
            loadAdmin();
        } catch (error) {
            if (!handleAuthFailure(error)) {
                console.error(error);
                showToast(errorMessage(error, `Could not delete ${userName}.`), 'error');
            }
        }
    });
};

const readToken = () => localStorage.getItem(tokenKey) ?? '';
const persistToken = (value) => {
    if (value) {
        localStorage.setItem(tokenKey, value);
    } else {
        localStorage.removeItem(tokenKey);
    }
};

const authHeaders = () => {
    const token = readToken();

    return token
        ? {
              Authorization: `Bearer ${token}`,
              Accept: 'application/json',
              'Content-Type': 'application/json',
          }
        : {
              Accept: 'application/json',
              'Content-Type': 'application/json',
          };
};

const requestJson = async (url, options = {}) => {
    const response = await fetch(url, {
        ...options,
        headers: {
            ...authHeaders(),
            ...(options.headers ?? {}),
        },
    });

    if (!response.ok) {
        const text = await response.text();
        const error = new Error(text || response.statusText);
        error.status = response.status;
        throw error;
    }

    if (response.status === 204) {
        return null;
    }

    const contentType = response.headers.get('content-type') ?? '';
    return contentType.includes('application/json') ? response.json() : response.text();
};

const buildQuery = (params) => {
    const searchParams = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            searchParams.set(key, value);
        }
    });

    const query = searchParams.toString();
    return query ? `?${query}` : '';
};

const responseItems = (response) => {
    if (Array.isArray(response?.data?.data)) {
        return response.data.data;
    }

    if (Array.isArray(response?.data)) {
        return response.data;
    }

    if (Array.isArray(response?.data?.items)) {
        return response.data.items;
    }

    if (Array.isArray(response?.items)) {
        return response.items;
    }

    return [];
};

const responsePaginator = (response) => {
    if (response?.data?.current_page) {
        return response.data;
    }

    if (response?.current_page) {
        return response;
    }

    return null;
};

const renderPager = (container, paginator, onPageChange) => {
    if (!container) {
        return;
    }

    if (!paginator?.total) {
        container.innerHTML = '';
        return;
    }

    const from = paginator.from ?? 0;
    const to = paginator.to ?? 0;
    const currentPage = paginator.current_page ?? 1;
    const lastPage = paginator.last_page ?? 1;

    container.innerHTML = `
        <div class="subtle">Showing ${from} to ${to} of ${paginator.total} records</div>
        <div class="pager-group">
            <button type="button" ${currentPage <= 1 ? 'disabled' : ''} data-page="prev">Previous</button>
            <button type="button" ${currentPage >= lastPage ? 'disabled' : ''} data-page="next">Next</button>
        </div>
    `;

    container.querySelectorAll('button[data-page]').forEach((button) => {
        button.addEventListener('click', () => {
            if (button.dataset.page === 'prev' && currentPage > 1) {
                onPageChange(currentPage - 1);
            }

            if (button.dataset.page === 'next' && currentPage < lastPage) {
                onPageChange(currentPage + 1);
            }
        });
    });
};

const renderMonthlyChart = (container, series, options = {}) => {
    if (!container) {
        return;
    }

    const rows = Array.isArray(series) ? series : [];
    const hasBills = options.hasBills ?? false;
    const hasData = rows.some((row) => Number(row.total_cubic_meter ?? 0) > 0 || Number(row.estimated_bill ?? 0) > 0);
    const chartRows = (hasData ? rows : (options.fallbackRows ?? [])).slice(-3);
    const maxUsage = Math.max(...chartRows.map((row) => Number(row.total_cubic_meter ?? 0)), 0);
    const maxBill = Math.max(...chartRows.map((row) => Number(row.estimated_bill ?? 0)), 0);

    if (!chartRows.length) {
        container.innerHTML = options.emptyMessage ?? '<div class="empty-state">No monthly usage available yet.</div>';
        return;
    }

    const signature = JSON.stringify({ chartRows, hasBills });
    if (container.dataset.chartSignature === signature) return;
    container.dataset.chartSignature = signature;
    const wasAnimated = container.classList.contains('is-animated');
    container.classList.remove('is-animated');
    container.innerHTML = `<div class="vertical-chart-grid">${chartRows
        .map((row) => {
            const usage = Number(row.total_cubic_meter ?? 0);
            const bill = Number(row.estimated_bill ?? 0);
            const usageHeight = maxUsage > 0 ? Math.max((usage / maxUsage) * 100, usage > 0 ? 5 : 0) : 0;
            const billHeight = maxBill > 0 ? Math.max((bill / maxBill) * 100, bill > 0 ? 5 : 0) : 0;

            return `
                <div class="chart-month" title="${row.label}: ${formatCubicMeterValue(usage)}${hasBills ? `, ${formatCurrency(bill)}` : ''}">
                    <div class="vertical-bars">
                        <span class="vertical-bar usage-bar" style="--bar-height:${usageHeight}%"><i>${formatCubicMeterValue(usage)}</i></span>
                        ${hasBills ? `<span class="vertical-bar bill-bar" style="--bar-height:${billHeight}%"><i>${formatCurrency(bill)}</i></span>` : ''}
                    </div>
                    <strong>${row.label}</strong>
                </div>
            `;
        })
        .join('')}</div>`;

    if (wasAnimated) {
        container.classList.add('is-animated');
    } else if (container.closest('.dashboard-view')?.classList.contains('is-active')) {
        if (container.matches('[data-household-monthly-chart]')) {
            observeHouseholdChart(container);
        } else {
            observeAdminChart(container);
        }
    }
};

const observeHouseholdChart = (chart) => {
    householdChartObserver?.disconnect();
    householdChartObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
                entry.target.classList.add('is-animated');
                householdChartObserver?.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    householdChartObserver.observe(chart);
};

const observeAdminChart = (chart) => {
    adminChartObserver?.disconnect();
    adminChartObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting && entry.intersectionRatio >= 1) {
                entry.target.classList.add('is-animated');
                adminChartObserver?.unobserve(entry.target);
            }
        });
    }, { threshold: 1 });
    adminChartObserver.observe(chart);
};

const animateVisibleCharts = (view) => {
    $$('[data-admin-monthly-chart], [data-household-monthly-chart]', view).forEach((chart) => {
        chart.classList.remove('is-animated');
        void chart.offsetHeight;
        if (chart.matches('[data-household-monthly-chart]')) {
            observeHouseholdChart(chart);
            return;
        }
        observeAdminChart(chart);
    });
};

const serializeForm = (form) => Object.fromEntries(new FormData(form).entries());

const safeRedirect = (target) => {
    window.location.assign(target);
};

const isConfiguredAdmin = (user) => user?.role === 'admin' && user?.email === adminEmail;

const pathForUser = (user) => (isConfiguredAdmin(user) ? '/admin' : '/household');

const redirectToRoleHome = async () => {
    try {
        const response = await requestJson('/api/me');
        safeRedirect(pathForUser(response.data));
    } catch (error) {
        persistToken('');
        safeRedirect('/login');
    }
};

const handleAuthFailure = (error) => {
    if (error?.status === 401) {
        persistToken('');
        safeRedirect('/login');
        return true;
    }

    return false;
};

const redirectAwayFromWrongRole = (user, expectedRole) => {
    if (!user) {
        safeRedirect('/login');
        return true;
    }

    if (expectedRole === 'admin' && !isConfiguredAdmin(user)) {
        safeRedirect('/household');
        return true;
    }

    if (expectedRole === 'household' && isConfiguredAdmin(user)) {
        safeRedirect('/admin');
        return true;
    }

    if (expectedRole === 'household' && user.role === 'device') {
        safeRedirect('/login');
        return true;
    }

    return false;
};

const setStatus = (message) => {
    if (statusLabel) {
        statusLabel.textContent = message;
    }
};

const setTokenInput = () => {
    if (tokenInput) {
        tokenInput.value = readToken();
    }
};

const showHouseholdView = (target) => {
    $$('[data-household-view]').forEach((view) => {
        view.classList.toggle('is-active', view.dataset.householdView === target);
    });

    $$('[data-household-nav]').forEach((button) => {
        button.classList.toggle('is-active', button.dataset.householdNav === target);
    });
    const activeView = $(`[data-household-view="${target}"]`);
    if (activeView) animateVisibleCharts(activeView);
};

const showAdminView = (target, shouldUpdateHash = true) => {
    $$('[data-admin-view]').forEach((view) => {
        view.classList.toggle('is-active', view.dataset.adminView === target);
    });

    $$('[data-admin-nav]').forEach((button) => {
        button.classList.toggle('is-active', button.dataset.adminNav === target);
    });

    if (shouldUpdateHash) {
        window.history.replaceState(null, '', `#${target}`);
    }
    const activeView = $(`[data-admin-view="${target}"]`);
    if (activeView) animateVisibleCharts(activeView);
};

const hasTokenOrReport = (message = 'Paste and save a bearer token first.') => {
    if (readToken()) {
        return true;
    }

    setStatus(message);
    setTokenInput();
    return false;
};

const bootLogout = () => {
    logoutButtons.forEach((logoutButton) => logoutButton.addEventListener('click', async () => {
        try {
            if (readToken()) {
                await requestJson('/api/logout', { method: 'POST' });
            }
        } catch (error) {
            handleAuthFailure(error);
        } finally {
            stopDashboardPolling();
            persistToken('');
            safeRedirect('/login');
        }
    }));
};

const submitJsonForm = async (url, payload) =>
    requestJson(url, {
        method: 'POST',
        body: JSON.stringify(payload),
    });

const activeTab = (target) => {
    $$('[data-tab-target]').forEach((button) => {
        button.classList.toggle('is-active', button.dataset.tabTarget === target);
    });

    $$('[data-auth-form]').forEach((form) => {
        form.classList.toggle('is-active', form.dataset.authForm === target);
    });
};

const bootLoginPage = () => {
    const authStatus = $('[data-auth-status]');

    const report = (message) => {
        if (authStatus) {
            authStatus.textContent = message;
        }
    };

    $$('[data-tab-target]').forEach((button) => {
        button.addEventListener('click', () => activeTab(button.dataset.tabTarget));
    });

    $$('[data-auth-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());

            try {
                if (form.dataset.authForm === 'login') {
                    report('Signing in...');
                    const response = await submitJsonForm('/api/login', payload);
                    persistToken(response.data.token);
                    report('Login successful.');
                    safeRedirect(pathForUser(response.data.user));
                    return;
                }

                if (form.dataset.authForm === 'register') {
                    report('Creating account...');
                    const response = await submitJsonForm('/api/register', payload);
                    persistToken(response.data.token);
                    report('Account created.');
                    safeRedirect('/household');
                    return;
                }

                report('This account type must be commissioned by the municipal administrator.');
            } catch (error) {
                report(handleAuthFailure(error) ? 'Session expired.' : 'Request failed. Check your inputs.');
                console.error(error);
            }
        });
    });

    if (readToken()) {
        redirectToRoleHome();
    }
};

const renderReadings = (items) => {
    const containers = $$('[data-live-readings-list]');
    if (!containers.length) {
        return;
    }

    if (!items?.length) {
        containers.forEach((container) => {
            container.innerHTML = '<div class="empty-state" style="margin:1rem;">No readings available for the selected account.</div>';
        });
        return;
    }

    const markup = items
        .map((reading) => {
            const status = reading.is_possible_leak ? 'Leak detected' : reading.is_threshold_exceeded ? 'Usage limit reached' : 'Normal';
            const statusClass = reading.is_possible_leak ? 'badge badge-leak' : reading.is_threshold_exceeded ? 'badge badge-threshold' : 'badge badge-normal';

            return `
                <div class="table-row">
                    <span>${formatDateTime(reading.recorded_at)}</span>
                    <span>${formatNumber(reading.flow_rate)} L/min</span>
                    <span>${formatCubicMeters(reading.liters_used)}</span>
                    <span>${formatCurrency(reading.estimated_bill)}</span>
                    <span><span class="${statusClass}">${status}</span></span>
                </div>
            `;
        })
        .join('');

    containers.forEach((container) => {
        container.innerHTML = markup;
    });
};

const renderConsumptionHistory = () => {
    const container = $('[data-history-list]');
    if (!container) return;
    const items = consumptionHistory[historyPeriod] ?? [];
    if (!items.length) {
        container.innerHTML = '<div class="empty-state" style="margin:1rem;">No consumption totals available.</div>';
        return;
    }
    container.innerHTML = items.map((item) => {
        const label = historyPeriod === 'weekly'
            ? `Week of ${new Date(`${item.period}T00:00:00`).toLocaleDateString()}`
            : historyPeriod === 'monthly'
                ? new Date(`${item.period}-01T00:00:00`).toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
                : new Date(`${item.period}T00:00:00`).toLocaleDateString();
        const alerts = Number(item.leak_count) + Number(item.limit_count);
        return `<div class="table-row">
            <span>${label}</span>
            <span>${formatNumber(item.total_liters, 3)} L</span>
            <span>${formatCubicMeterValue(item.total_cubic_meter)}</span>
            <span>${formatCurrency(item.estimated_bill)}</span>
            <span>${alerts ? `${alerts} alert(s)` : 'None'}</span>
        </div>`;
    }).join('');
};

const renderNotifications = (items) => {
    const containers = $$('[data-notifications-list]');
    if (!containers.length) {
        return;
    }

    if (!items?.length) {
        containers.forEach((container) => {
            container.innerHTML = '<div class="empty-state">No alerts yet.</div>';
        });
        return;
    }

    const markup = items
        .map((notification) => {
            const label = notification.type === 'possible_leak'
                ? 'Leak detected'
                : notification.type === 'threshold_exceeded'
                    ? 'Usage limit reached'
                    : 'Announcement';

            return `
            <article class="notification-card" data-notification-id="${notification.id}" data-notification-read="${notification.is_read ? '1' : '0'}" tabindex="0">
                <div class="notification-head">
                    <h4>${notification.title}</h4>
                    <span class="status-chip ${notification.is_read ? 'status-chip-muted' : 'status-chip-soft'}">${notification.is_read ? 'Read' : 'Unread'}</span>
                </div>
                <p class="notification-tag">${label}</p>
                <p>${notification.message}</p>
                <small>${formatDateTime(notification.sent_at ?? notification.created_at)}</small>
                <button type="button" class="link-button rose" data-delete-received-notification="${notification.id}">Delete</button>
            </article>
        `;
        })
        .join('');

    containers.forEach((container) => {
        container.innerHTML = markup;
        bindNotificationReadTracking(container);
    });
};

const markNotificationRead = async (card) => {
    const id = card?.dataset.notificationId;
    if (!id || card.dataset.notificationRead === '1' || notificationReadInFlight.has(id)) return;
    notificationReadInFlight.add(id);
    try {
        await requestJson(`/api/notifications/${id}/read`, { method: 'PATCH' });
        card.dataset.notificationRead = '1';
        const badge = $('.status-chip', card);
        if (badge) {
            badge.textContent = 'Read';
            badge.classList.remove('status-chip-soft');
            badge.classList.add('status-chip-muted');
        }
    } catch (error) {
        if (!handleAuthFailure(error)) console.error(error);
    } finally {
        notificationReadInFlight.delete(id);
    }
};

const bindNotificationReadTracking = (container) => {
    notificationObserver?.disconnect();
    if (container.dataset.readTrackingBound !== '1') {
        container.dataset.readTrackingBound = '1';
        container.addEventListener('click', async (event) => {
            const deleteButton = event.target.closest('[data-delete-received-notification]');
            if (deleteButton) {
                event.stopPropagation();
                try {
                    await requestJson(`/api/notifications/received/${deleteButton.dataset.deleteReceivedNotification}`, { method: 'DELETE' });
                    deleteButton.closest('[data-notification-id]')?.remove();
                    showToast('Notification deleted successfully.');
                    loadDashboard();
                } catch (error) {
                    if (!handleAuthFailure(error)) showToast(errorMessage(error, 'Could not delete the notification.'), 'error');
                }
                return;
            }
            markNotificationRead(event.target.closest('[data-notification-id]'));
        });
        container.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') markNotificationRead(event.target.closest('[data-notification-id]'));
        });
    }
    notificationObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting && entry.intersectionRatio >= 0.75) {
                markNotificationRead(entry.target);
                notificationObserver?.unobserve(entry.target);
            }
        });
    }, { threshold: 0.75 });
    $$('[data-notification-id]', container).forEach((card) => {
        if (card.dataset.notificationRead !== '1') notificationObserver.observe(card);
    });
};

const renderProfile = (user) => {
    setText('[data-profile-name]', user?.name ?? '--');
    setText('[data-profile-email]', user?.email ?? '--');
    setText('[data-profile-account]', user?.account_number ?? '--');
    setText('[data-profile-address]', user?.address ?? '--');
    setText('[data-profile-phone]', user?.phone ?? '--');
    if (accountForm && !accountForm.contains(document.activeElement)) {
        accountForm.elements.email.value = user?.email ?? '';
        accountForm.elements.phone.value = user?.phone ?? '';
    }
};

const renderAdminAlerts = (items) => {
    const containers = $$('[data-admin-alerts]');
    if (!containers.length) {
        return;
    }

    const alerts = items ?? [];

    if (!alerts.length) {
        containers.forEach((container) => {
            container.innerHTML = '<div class="empty-state">No alerts found for this period.</div>';
        });
        return;
    }

    const markup = alerts
        .map((leak) => `
            <article class="notification-card">
                <div class="notification-head">
                    <h4>${leak.user.name} · ${leak.user.account_number ?? leak.user.email}</h4>
                    <span class="status-chip ${leak.status === 'resolved' ? 'status-chip-soft' : 'status-chip-alert'}">${leak.status === 'resolved' ? 'Resolved' : 'Unresolved'}</span>
                </div>
                <p>${leak.user.address ?? 'No household address recorded'} · Flow: ${formatNumber(leak.flow_rate)} L/min</p>
                <small>Last reading: ${formatDateTime(leak.last_reading_at)} · Last notice: ${formatDateTime(leak.last_notice_at)}</small>
            </article>
        `)
        .join('');

    containers.forEach((container) => {
        container.innerHTML = markup;
    });
};

const renderAdminSentNotifications = (items) => {
    const types = ['service_interruption', 'maintenance', 'announcement'];
    types.forEach((type) => {
        const container = $(`[data-admin-sent-type="${type}"]`);
        if (!container) return;
        const grouped = new Map();
        (items ?? []).filter((item) => item.type === type).forEach((item) => {
            const sentTime = item.sent_at ?? item.created_at ?? '';
            const legacyTime = sentTime.slice(0, 16);
            const key = item.batch_id ?? `${item.type}|${item.title}|${item.message}|${legacyTime}`;
            if (!grouped.has(key)) grouped.set(key, { ...item, recipientCount: 0 });
            grouped.get(key).recipientCount += 1;
        });
        const records = [...grouped.values()];
        container.innerHTML = records.length
            ? records.map((item) => `<article class="notification-card">
                <div class="notification-head">
                    <h4>${item.title}</h4>
                </div>
                <p>${item.message}</p>
                <small>Sent ${formatDateTime(item.sent_at ?? item.created_at)} · ${item.recipientCount} recipient${item.recipientCount === 1 ? '' : 's'}</small>
                <button type="button" class="link-button rose" data-delete-sent-notification="${item.id}" data-notification-title="${item.title}">Delete</button>
            </article>`).join('')
            : '<div class="empty-state">No sent records in this category.</div>';
    });
};

const applySummary = (summary) => {
    const current = summary?.current_month ?? {};
    const notifications = summary?.notifications ?? {};
    const billingStatement = summary?.billing_statement ?? null;
    const activeBilling = summary?.active_billing ?? null;

    const latestReading = current.latest_reading ?? null;
    const totalConsumed = formatCubicMeters(current.total_liters ?? 0);
    const estimatedBill = formatCurrency(current.estimated_bill ?? 0);
    const leakDetected = Boolean(latestReading?.is_possible_leak);
    const leakStatus = leakDetected ? 'Leak detected' : 'Normal';
    const quickSummary = `${current.reading_count ?? 0} readings, ${totalConsumed}, ${estimatedBill}`;

    setText('[data-stat-readings]', current.reading_count ?? '--');
    setText('[data-stat-liters]', totalConsumed);
    setText('[data-stat-bill]', estimatedBill);
    setText('[data-stat-alerts]', notifications.unread ?? '--');
    setText('[data-today-total]', totalConsumed);
    setText('[data-total-consumed]', totalConsumed);
    setText('[data-estimated-bill]', estimatedBill);
    setText('[data-water-rate]', activeBilling ? formatCurrency(activeBilling.water_rate) : '--');
    setText('[data-balance]', billingStatement ? formatCurrency(billingStatement.remaining_balance) : '--');
    setText('[data-threshold]', activeBilling ? `${formatNumber(activeBilling.threshold_liters ?? 0)} L` : '--');
    setText('[data-leak-threshold]', activeBilling ? `${formatNumber(activeBilling.leak_flow_rate_threshold ?? 0)} L/min` : '--');
    setText('[data-current-flow]', latestReading ? `${formatNumber(latestReading.flow_rate)} L/min` : '-- L/min');
    setText('[data-leak-status]', leakStatus);
    setText('[data-leak-summary]', leakStatus);
    $$('[data-leak-status]').forEach((node) => {
        node.classList.toggle('status-chip-soft', !leakDetected);
        node.classList.toggle('status-chip-alert', leakDetected);
    });
    $$('[data-leak-summary]').forEach((node) => {
        node.classList.toggle('mint', !leakDetected);
        node.classList.toggle('rose', leakDetected);
    });
    setText('[data-leak-details]', leakDetected ? 'Possible leak detected from the latest reading.' : 'No leak has been detected yet.');
    setText('[data-last-updated-label]', latestReading?.recorded_at ? `Last updated: ${formatDateTime(latestReading.recorded_at)}` : 'Last updated: --');
    setText('[data-last-updated]', latestReading?.recorded_at ? formatDateTime(latestReading.recorded_at) : '--');
    setText('[data-billing-month]', billingStatement?.billing_month
        ? new Date(billingStatement.billing_month).toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
        : '--');
    setText('[data-quick-summary]', quickSummary);

    renderNotifications(notifications.latest ?? []);
    renderMonthlyChart($('[data-household-monthly-chart]'), summary?.monthly_usage ?? [], {
        hasBills: true,
        fallbackRows: [
            { label: 'Apr 2026', total_cubic_meter: 7.8, estimated_bill: 312 },
            { label: 'May 2026', total_cubic_meter: 9.15, estimated_bill: 366 },
            { label: 'Jun 2026', total_cubic_meter: 8.4, estimated_bill: 336 },
        ],
    });
    setText('[data-live-label]', latestReading ? 'Live' : 'Idle');
};

const loadDashboard = async (options = {}) => {
    const { reschedule = true } = options;

    if (!readToken()) {
        stopDashboardPolling();
        safeRedirect('/login');
        return;
    }

    if (dashboardRefreshInFlight) {
        if (reschedule) {
            scheduleDashboardPolling();
        }

        return;
    }

    dashboardRefreshInFlight = true;

    try {
        const me = await requestJson('/api/me');
        if (redirectAwayFromWrongRole(me.data, 'household')) {
            stopDashboardPolling();
            return;
        }

        const readingsQuery = buildQuery({
            page: dashboardState.page,
            per_page: dashboardState.per_page,
            from: dashboardState.from,
            to: dashboardState.to,
        });

        const summaryUrl = `/api/summary?user_id=${me.data.id}`;

        const [summaryResponse, readingsResponse, notificationsResponse, historyResponse] = await Promise.all([
            requestJson(summaryUrl),
            requestJson(`/api/readings${readingsQuery}`),
            requestJson('/api/notifications?per_page=100'),
            requestJson('/api/consumption-history'),
        ]);

        const readings = responseItems(readingsResponse);
        const notifications = responseItems(notificationsResponse);

        renderProfile(me.data);
        applySummary(summaryResponse.data);
        renderReadings(readings);
        renderNotifications(notifications);
        consumptionHistory = historyResponse.data ?? consumptionHistory;
        renderConsumptionHistory();
        readingsPagers.forEach((readingsPager) => renderPager(readingsPager, responsePaginator(readingsResponse), (page) => {
            dashboardState.page = page;
            loadDashboard();
        }));
        setStatus('Connected');
        if (reschedule) {
            scheduleDashboardPolling();
        }
    } catch (error) {
        if (handleAuthFailure(error)) {
            stopDashboardPolling();
            return;
        }

        console.error(error);
        setStatus('Connection failed');
        $('[data-live-label]') && ($('[data-live-label]').textContent = 'Offline');
        if (reschedule) {
            scheduleDashboardPolling();
        }
    } finally {
        dashboardRefreshInFlight = false;
    }
};

const loadAdmin = async () => {
    if (!hasTokenOrReport('Paste and save an admin bearer token first.')) {
        return;
    }

    const usersContainers = $$('[data-admin-users]');
    const devicesContainer = $('[data-municipal-devices]');
    const reportsContainer = $('[data-admin-reports]');
    const billingSummaryContainer = $('[data-admin-billing-summary]');
    const deviceHouseholdSelect = $('[data-device-household-select]');
    const householdSelect = $('[data-household-select]');
    const summaryLabel = $('[data-admin-summary]');
    const userForm = $('[data-admin-user-form]');
    const billingForm = $('[data-billing-form]');
    const deviceRegisterForm = $('[data-device-register-form]');
    const activationResult = $('[data-activation-result]');
    const announcementForm = $('[data-announcement-form]');
    const announcementUser = $('[data-announcement-user]');

    if (adminUserFilterForm) {
        const fields = serializeForm(adminUserFilterForm);
        adminState.page = Number(fields.page ?? 1);
        adminState.per_page = Number(fields.per_page ?? 10);
        adminState.search = fields.search ?? '';
        adminState.role = fields.role ?? '';
        adminState.is_active = fields.is_active ?? '';
    }

    try {
        const me = await requestJson('/api/me');
        if (redirectAwayFromWrongRole(me.data, 'admin')) {
            return;
        }

        const usersQuery = buildQuery({
            page: adminState.page,
            per_page: adminState.per_page,
            search: adminState.search,
            role: adminState.role,
            is_active: adminState.is_active,
        });

        const [summaryResponse, usersResponse, overviewUsersResponse, billingResponse, consumptionResponse, notificationsResponse, sentNotificationsResponse, devicesResponse, leaksResponse] = await Promise.all([
            requestJson('/api/summary'),
            requestJson(`/api/users${usersQuery}`),
            requestJson('/api/users?per_page=100'),
            requestJson('/api/billing-settings'),
            requestJson('/api/reports/consumption'),
            requestJson('/api/reports/notifications'),
            requestJson('/api/notifications?per_page=100'),
            requestJson('/api/municipal-devices'),
            requestJson('/api/reports/leaks'),
        ]);

        const users = responseItems(usersResponse);
        const overviewUsers = responseItems(overviewUsersResponse);
        const overviewDeviceUsers = overviewUsers.filter((user) => user.role === 'device');
        const overviewHouseholdUsers = overviewUsers.filter((user) => user.role === 'user');
        const billingSettings = billingResponse.data ?? null;
        const summary = summaryResponse.data ?? {};
        const consumption = consumptionResponse.data ?? {};
        const notificationReport = notificationsResponse.data ?? {};
        const sentNotifications = responseItems(sentNotificationsResponse);
        const leakHouseholds = responseItems(leaksResponse);
        const municipalDevices = responseItems(devicesResponse);
        const onlineDevices = municipalDevices.filter((device) => device.is_online).length;
        const offlineDevices = municipalDevices.filter((device) => !device.is_online && device.status !== 'decommissioned').length;
        const totalConsumption = formatCubicMeters(summary.current_month?.total_liters ?? 0);
        const estimatedBilling = formatCurrency(summary.current_month?.estimated_bill ?? 0);

        summaryLabel && (summaryLabel.textContent = `${responsePaginator(usersResponse)?.total ?? users.length} records`);
        setText('[data-admin-households]', overviewHouseholdUsers.length);
        setText('[data-admin-devices]', municipalDevices.length);
        setText('[data-admin-online-devices]', onlineDevices);
        setText('[data-admin-offline-devices]', offlineDevices);
        setText('[data-admin-alert-count]', notificationReport.unread ?? 0);
        setText('[data-admin-liters]', totalConsumption);
        setText('[data-admin-billing-total]', estimatedBilling);
        renderMonthlyChart($('[data-admin-monthly-chart]'), summary.monthly_usage ?? [], {
            fallbackRows: [
                { label: 'Apr 2026', total_cubic_meter: 218 },
                { label: 'May 2026', total_cubic_meter: 245 },
                { label: 'Jun 2026', total_cubic_meter: 231 },
            ],
        });

        if (usersContainers.length) {
            const usersMarkup = users.length
                ? users
                    .map(
                        (user) => `
                        <div class="table-row admin-table-row">
                            <span>${user.name}</span>
                            <span>${user.email}</span>
                            <span>${user.role}</span>
                            <span>${user.is_active ? 'Active' : 'Inactive'}</span>
                            <span><button type="button" class="link-button" data-delete-user="${user.id}" data-user-name="${user.name}">Delete</button></span>
                        </div>
                    `,
                    )
                    .join('')
                : '<div class="empty-state" style="margin:1rem;">No users match the current filters.</div>';

            usersContainers.forEach((usersContainer) => {
                usersContainer.innerHTML = usersMarkup;
                bindAdminUserDeleteActions(usersContainer);
            });
        }

        $$('[data-admin-users-pager]').forEach((pager) => renderPager(pager, responsePaginator(usersResponse), (page) => {
            adminState.page = page;
            loadAdmin();
        }));

        if (deviceHouseholdSelect) {
            deviceHouseholdSelect.innerHTML = '<option value="">Choose household</option>' +
                overviewHouseholdUsers
                    .map((user) => `<option value="${user.id}">${user.name} (${user.account_number ?? user.email})</option>`)
                    .join('');
        }

        if (householdSelect) {
            householdSelect.innerHTML = '<option value="">Assign device to household (devices only)</option>' +
                overviewHouseholdUsers
                    .map((user) => `<option value="${user.id}">${user.name} (${user.account_number ?? user.email})</option>`)
                    .join('');
        }

        if (announcementUser) {
            announcementUser.innerHTML = '<option value="">All active households</option>' +
                overviewHouseholdUsers
                    .map((user) => `<option value="${user.id}">${user.name} (${user.email})</option>`)
                    .join('');
        }

        if (devicesContainer) {
            if (!municipalDevices.length) {
                devicesContainer.innerHTML = '<div class="empty-state">No municipal meters registered.</div>';
            } else {
                devicesContainer.innerHTML = municipalDevices
                    .map((device) => `
                        <article class="notification-card">
                            <h4>${device.asset_number} · ${device.name}</h4>
                            <p>${device.household?.name ?? 'Unassigned'} · ${device.serial_number}</p>
                            <small>${device.is_online ? 'Online' : 'Offline'} · Lifecycle: ${device.status} · Maintenance: ${device.maintenance_status}</small>
                            <small>Last seen: ${formatDateTime(device.last_seen_at)} · Firmware: ${device.firmware_version ?? 'Not reported'}</small>
                            <small>Hardware: ${device.hardware_id ?? 'Not commissioned'} · Installed: ${device.installed_at ? formatDateTime(device.installed_at) : 'Not set'}</small>
                            <div class="button-row">
                                ${device.status !== 'decommissioned' ? `<button type="button" class="button button-secondary" data-device-new-code="${device.id}">New activation code</button>` : ''}
                                ${device.status === 'active' ? `<button type="button" class="button button-secondary" data-device-status="${device.id}" data-next-status="maintenance">Maintenance</button>` : ''}
                                ${['maintenance', 'suspended'].includes(device.status) ? `<button type="button" class="button button-secondary" data-device-status="${device.id}" data-next-status="active">Return to service</button>` : ''}
                                ${device.status !== 'decommissioned' ? `<button type="button" class="link-button" data-device-decommission="${device.id}">Decommission</button>` : ''}
                            </div>
                        </article>
                    `)
                    .join('');
            }
        }

        if (reportsContainer) {
            reportsContainer.innerHTML = `
                <div><dt>Monthly readings</dt><dd>${summary.current_month?.reading_count ?? 0}</dd></div>
                <div><dt>Monthly usage</dt><dd>${totalConsumption}</dd></div>
                <div><dt>Estimated billing</dt><dd>${estimatedBilling}</dd></div>
                <div><dt>Unread alerts</dt><dd>${notificationReport.unread ?? 0}</dd></div>
                <div><dt>Consumption rows</dt><dd>${consumption.summary?.reading_count ?? 0}</dd></div>
                <div><dt>Notification items</dt><dd>${notificationReport.total ?? 0}</dd></div>
            `;
        }

        if (billingSummaryContainer) {
            billingSummaryContainer.innerHTML = `
                <div><dt>Water rate per m³</dt><dd>${billingSettings ? formatCurrency(billingSettings.water_rate) : '--'}</dd></div>
                <div><dt>Threshold liters</dt><dd>${billingSettings ? formatNumber(billingSettings.threshold_liters ?? 0) : '--'}</dd></div>
                <div><dt>Leak threshold</dt><dd>${billingSettings ? formatNumber(billingSettings.leak_flow_rate_threshold ?? 0) : '--'}</dd></div>
                <div><dt>Quiet hours</dt><dd>${billingSettings ? `${billingSettings.quiet_hours_start}:00 - ${billingSettings.quiet_hours_end}:00` : '--'}</dd></div>
                <div><dt>Households</dt><dd>${overviewHouseholdUsers.length}</dd></div>
                <div><dt>Devices online</dt><dd>${onlineDevices}</dd></div>
            `;
        }

        renderAdminAlerts(leakHouseholds);
        renderAdminSentNotifications(sentNotifications);

        if (billingForm && billingSettings) {
            const fields = ['water_rate', 'subsidy_deduction', 'threshold_liters', 'leak_flow_rate_threshold', 'quiet_hours_start', 'quiet_hours_end'];
            fields.forEach((field) => {
                const input = billingForm.querySelector(`[name="${field}"]`);
                if (input) {
                    input.value = billingSettings[field] ?? '';
                }
            });
        }

        if (userForm && !userForm.dataset.bound) {
            userForm.dataset.bound = '1';
            userForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const payload = Object.fromEntries(new FormData(userForm).entries());
            try {
                await requestJson('/api/users', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                showToast('User account created successfully.');
                userForm.reset();
                loadAdmin();
            } catch (error) {
                if (!handleAuthFailure(error)) {
                    console.error(error);
                    showToast(errorMessage(error, 'Could not create the user account.'), 'error');
                }
            }
            });
        }

        if (billingForm && !billingForm.dataset.bound) {
            billingForm.dataset.bound = '1';
            billingForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const payload = Object.fromEntries(new FormData(billingForm).entries());
            try {
                await requestJson('/api/billing-settings', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                showToast('Water-rate and billing settings saved successfully.');
                loadAdmin();
            } catch (error) {
                if (!handleAuthFailure(error)) {
                    console.error(error);
                    showToast(errorMessage(error, 'Could not save billing settings.'), 'error');
                }
            }
            });
        }

        if (deviceRegisterForm && !deviceRegisterForm.dataset.bound) {
            deviceRegisterForm.dataset.bound = '1';
            deviceRegisterForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const payload = Object.fromEntries(new FormData(deviceRegisterForm).entries());
            Object.keys(payload).forEach((key) => payload[key] === '' && delete payload[key]);

            try {
                const response = await requestJson('/api/municipal-devices', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                const details = response.data;
                if (activationResult) activationResult.innerHTML = `<article class="notification-card"><h4>Save this one-time activation information</h4><p>Device UUID: <code>${details.device.uuid}</code></p><p>Activation code: <strong>${details.activation_code}</strong></p><small>Expires: ${formatDateTime(details.expires_at)}. The credential token is delivered directly to the device and is never shown here.</small></article>`;
                showToast('Municipal meter registered successfully.');
                deviceRegisterForm.reset();
                loadAdmin();
            } catch (error) {
                if (!handleAuthFailure(error)) {
                    console.error(error);
                    showToast(errorMessage(error, 'Could not register the meter.'), 'error');
                }
            }
            });
        }

        if (devicesContainer && !devicesContainer.dataset.bound) {
            devicesContainer.dataset.bound = '1';
            devicesContainer.addEventListener('click', async (event) => {
                const codeButton = event.target.closest('[data-device-new-code]');
                const statusButton = event.target.closest('[data-device-status]');
                const decommissionButton = event.target.closest('[data-device-decommission]');
                try {
                    if (codeButton) {
                        if (!window.confirm('Rotate credentials and create a new activation code? The current device token will stop working.')) return;
                        const response = await requestJson(`/api/municipal-devices/${codeButton.dataset.deviceNewCode}/activation-code`, { method: 'POST' });
                        if (activationResult) activationResult.innerHTML = `<article class="notification-card"><h4>One-time activation information</h4><p>Device UUID: <code>${response.data.device_uuid}</code></p><p>Activation code: <strong>${response.data.activation_code}</strong></p><small>Expires: ${formatDateTime(response.data.expires_at)}</small></article>`;
                        showToast('New activation code created and old credentials revoked.');
                    } else if (statusButton) {
                        await requestJson(`/api/municipal-devices/${statusButton.dataset.deviceStatus}`, { method: 'PATCH', body: JSON.stringify({ status: statusButton.dataset.nextStatus, maintenance_status: statusButton.dataset.nextStatus === 'active' ? 'operational' : 'under_maintenance' }) });
                        showToast('Device lifecycle status updated.');
                    } else if (decommissionButton) {
                        if (!window.confirm('Permanently decommission this meter and revoke its credentials?')) return;
                        await requestJson(`/api/municipal-devices/${decommissionButton.dataset.deviceDecommission}`, { method: 'DELETE' });
                        showToast('Device decommissioned and credentials revoked.');
                    } else return;
                    loadAdmin();
                } catch (error) {
                    if (!handleAuthFailure(error)) showToast(errorMessage(error, 'Could not update the meter.'), 'error');
                }
            });
        }

        if (announcementForm && !announcementForm.dataset.bound) {
            announcementForm.dataset.bound = '1';
            announcementForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                const payload = Object.fromEntries(new FormData(announcementForm).entries());
                if (!payload.user_id) delete payload.user_id;
                try {
                    await requestJson('/api/notifications', { method: 'POST', body: JSON.stringify(payload) });
                    showToast('Notification sent successfully.');
                    announcementForm.reset();
                    loadAdmin();
                } catch (error) {
                    if (!handleAuthFailure(error)) {
                        console.error(error);
                        showToast(errorMessage(error, 'Could not send the notification.'), 'error');
                    }
                }
            });
        }

        if (adminUserFilterForm && !adminUserFilterForm.dataset.bound) {
            adminUserFilterForm.dataset.bound = '1';
            adminUserFilterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            adminState.page = 1;
            const fields = serializeForm(adminUserFilterForm);
            adminState.search = fields.search ?? '';
            adminState.role = fields.role ?? '';
            adminState.is_active = fields.is_active ?? '';
            adminState.per_page = Number(fields.per_page ?? 10);
            loadAdmin();
            });
        }
    } catch (error) {
        if (handleAuthFailure(error)) {
            return;
        }

        console.error(error);
        setStatus('Admin load failed');
    }
};

const bootCommon = () => {
    setTokenInput();
    bootLogout();
    const supportsThemeSelection = page === 'household' || page === 'admin';
    if (supportsThemeSelection) {
        applyTheme(localStorage.getItem(themeKey) ?? 'light');
    } else {
        document.documentElement.dataset.theme = 'light';
    }
    themeToggle?.addEventListener('click', () => {
        const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        applyTheme(nextTheme);
        showToast(`${nextTheme === 'dark' ? 'Dark' : 'Light'} theme enabled.`);
    });

    document.addEventListener('click', async (event) => {
        const itemButton = event.target.closest('[data-delete-sent-notification]');
        const allButton = event.target.closest('[data-delete-all-sent-notifications]');
        if (!itemButton && !allButton) return;

        const title = itemButton?.dataset.notificationTitle ?? 'all sent notifications';
        if (!window.confirm(`Delete ${title}? This cannot be undone.`)) return;

        const url = itemButton
            ? `/api/notifications/sent/${itemButton.dataset.deleteSentNotification}`
            : '/api/notifications/sent/all';
        try {
            const response = await requestJson(url, { method: 'DELETE' });
            showToast(response?.message ?? 'Notification records deleted successfully.');
            loadAdmin();
        } catch (error) {
            if (!handleAuthFailure(error)) {
                console.error(error);
                showToast(errorMessage(error, 'Could not delete the notification records.'), 'error');
            }
        }
    });

    $$('[data-pdf-export]').forEach((button) => {
        button.addEventListener('click', async () => {
            const printWindow = window.open('', '_blank');
            try {
                const response = await fetch(button.dataset.pdfExport, { headers: authHeaders() });
                if (!response.ok) throw new Error('PDF export failed.');
                const url = URL.createObjectURL(await response.blob());
                if (printWindow) printWindow.location.href = url;
                else window.location.href = url;
                showToast('PDF export created successfully.');
                window.setTimeout(() => URL.revokeObjectURL(url), 60000);
            } catch (error) {
                printWindow?.close();
                if (!handleAuthFailure(error)) {
                    console.error(error);
                    setStatus('PDF export failed');
                    showToast('Could not create the PDF export.', 'error');
                }
            }
        });
    });

    if (accountForm && !accountForm.dataset.bound) {
        accountForm.dataset.bound = '1';
        accountForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                await requestJson('/api/account', { method: 'PATCH', body: JSON.stringify(serializeForm(accountForm)) });
                setStatus('Account updated');
                showToast('Contact details updated successfully.');
                loadDashboard();
            } catch (error) {
                showToast(errorMessage(error, 'Could not update contact details.'), 'error');
            }
        });
    }

    if (passwordForm && !passwordForm.dataset.bound) {
        passwordForm.dataset.bound = '1';
        passwordForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                await requestJson('/api/account/password', { method: 'PATCH', body: JSON.stringify(serializeForm(passwordForm)) });
                passwordForm.reset();
                setStatus('Password changed');
                showToast('Password changed successfully.');
            } catch (error) {
                showToast(errorMessage(error, 'Could not change the password.'), 'error');
            }
        });
    }

    $$('[data-household-nav]').forEach((button) => {
        button.addEventListener('click', () => showHouseholdView(button.dataset.householdNav));
    });

    $$('[data-history-period]').forEach((button) => {
        button.addEventListener('click', () => {
            historyPeriod = button.dataset.historyPeriod;
            $$('[data-history-period]').forEach((tab) => tab.classList.toggle('is-active', tab === button));
            renderConsumptionHistory();
        });
    });

    $$('[data-admin-nav]').forEach((button) => {
        button.addEventListener('click', () => showAdminView(button.dataset.adminNav));
    });

    saveTokenButton?.addEventListener('click', () => {
        persistToken(tokenInput?.value.trim() ?? '');
        setStatus('Token saved');
        showToast('API token saved successfully.');
    });

    if (dashboardFilterForm && !dashboardFilterForm.dataset.bound) {
        dashboardFilterForm.dataset.bound = '1';
        dashboardFilterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const fields = serializeForm(dashboardFilterForm);
            dashboardState.page = 1;
            dashboardState.from = fields.from ?? '';
            dashboardState.to = fields.to ?? '';
            dashboardState.per_page = Number(fields.per_page ?? 10);

            if (page === 'household') {
                loadDashboard();
            }
        });
    }

    refreshButton?.addEventListener('click', () => {
        if (page === 'household') {
            loadDashboard();
        }

        if (page === 'admin') {
            loadAdmin();
        }
    });

    const clearNotificationsButton = $('[data-clear-notifications]');
    clearNotificationsButton?.addEventListener('click', async () => {
        if (!window.confirm('Clear every notification from your inbox?')) return;
        try {
            const response = await requestJson('/api/notifications/received/all', { method: 'DELETE' });
            showToast(response.message ?? 'All notifications cleared successfully.');
            loadDashboard();
        } catch (error) {
            if (!handleAuthFailure(error)) showToast(errorMessage(error, 'Could not clear notifications.'), 'error');
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    bootCommon();

    if (page === 'login') {
        bootLoginPage();
        return;
    }

    if (page === 'household') {
        if (!readToken()) {
            safeRedirect('/login');
            return;
        }

        showHouseholdView('dashboard');
        loadDashboard();
        return;
    }

    if (page === 'admin') {
        if (!readToken()) {
            setStatus('Redirecting to login');
            safeRedirect('/login');
            return;
        }

        loadAdmin();
        const initialAdminView = window.location.hash.replace('#', '') || 'admin-dashboard';
        const adminViewExists = Boolean($(`[data-admin-view="${initialAdminView}"]`));
        showAdminView(adminViewExists ? initialAdminView : 'admin-dashboard', false);
        return;
    }

    if (page === 'landing' && readToken()) {
        redirectToRoleHome();
    }
});
