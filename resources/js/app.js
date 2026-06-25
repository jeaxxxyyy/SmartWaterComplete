const tokenKey = 'smart-water-token';

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
const logoutButton = $('[data-logout]');
const statusLabel = $('[data-status]');
const tokenInput = $('[data-token-input]');
const saveTokenButton = $('[data-token-save]');
const refreshButton = $('[data-refresh]');
const dashboardFilterForm = $('[data-reading-filters]');
const adminUserFilterForm = $('[data-user-filters]');
const readingsPager = $('[data-readings-pager]');
const adminPager = $('[data-admin-users-pager]');

const dashboardState = {
    page: 1,
    per_page: 10,
    from: '',
    to: '',
};

const adminState = {
    page: 1,
    per_page: 10,
    search: '',
    role: '',
    is_active: '',
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

const serializeForm = (form) => Object.fromEntries(new FormData(form).entries());

const safeRedirect = (target) => {
    window.location.assign(target);
};

const handleAuthFailure = (error) => {
    if (error?.status === 401) {
        persistToken('');
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

const hasTokenOrReport = (message = 'Paste and save a bearer token first.') => {
    if (readToken()) {
        return true;
    }

    setStatus(message);
    setTokenInput();
    return false;
};

const bootLogout = () => {
    logoutButton?.addEventListener('click', async () => {
        try {
            if (readToken()) {
                await requestJson('/api/logout', { method: 'POST' });
            }
        } catch (error) {
            handleAuthFailure(error);
        } finally {
            persistToken('');
            safeRedirect('/login');
        }
    });
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
                    safeRedirect(response.data.user.role === 'admin' ? '/admin' : '/dashboard');
                    return;
                }

                if (form.dataset.authForm === 'register') {
                    report('Creating account...');
                    const response = await submitJsonForm('/api/register', payload);
                    persistToken(response.data.token);
                    report('Account created.');
                    safeRedirect('/dashboard');
                    return;
                }

                report('Issuing device token...');
                const response = await submitJsonForm('/api/device-token', payload);
                persistToken(response.data.token);
                report(`Device token issued for ${response.data.user.name}.`);
            } catch (error) {
                report(handleAuthFailure(error) ? 'Session expired.' : 'Request failed. Check your inputs.');
                console.error(error);
            }
        });
    });

    if (readToken()) {
        safeRedirect('/dashboard');
    }
};

const renderReadings = (items) => {
    const container = $('[data-readings-list]');
    if (!container) {
        return;
    }

    if (!items?.length) {
        container.innerHTML = '<div class="empty-state" style="margin:1rem;">No readings available for the selected account.</div>';
        return;
    }

    container.innerHTML = items
        .map((reading) => {
            const status = reading.is_possible_leak ? 'Leak risk' : reading.is_threshold_exceeded ? 'Threshold' : 'Normal';
            const statusClass = reading.is_possible_leak ? 'badge badge-leak' : reading.is_threshold_exceeded ? 'badge badge-threshold' : 'badge badge-normal';

            return `
                <div class="table-row">
                    <span>${formatDateTime(reading.recorded_at)}</span>
                    <span>${formatNumber(reading.flow_rate)} L/min</span>
                    <span>${formatNumber(reading.liters_used)} L</span>
                    <span>${formatCurrency(reading.estimated_bill)}</span>
                    <span><span class="${statusClass}">${status}</span></span>
                </div>
            `;
        })
        .join('');
};

const renderNotifications = (items) => {
    const container = $('[data-notifications-list]');
    if (!container) {
        return;
    }

    if (!items?.length) {
        container.innerHTML = '<div class="empty-state">No alerts yet.</div>';
        return;
    }

    container.innerHTML = items
        .map((notification) => `
            <article class="notification-card">
                <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;">
                    <h4>${notification.title}</h4>
                    <span style="font-size:0.75rem;color:${notification.is_read ? '#94a3b8' : '#67e8f9'};">${notification.is_read ? 'Read' : 'Unread'}</span>
                </div>
                <p>${notification.message}</p>
                <small>${formatDateTime(notification.sent_at ?? notification.created_at)}</small>
            </article>
        `)
        .join('');
};

const applySummary = (summary) => {
    const current = summary?.current_month ?? {};
    const notifications = summary?.notifications ?? {};
    const billingStatement = summary?.billing_statement ?? null;
    const activeBilling = summary?.active_billing ?? null;

    $('[data-stat-readings]') && ($('[data-stat-readings]').textContent = current.reading_count ?? '--');
    $('[data-stat-liters]') && ($('[data-stat-liters]').textContent = `${formatNumber(current.total_liters ?? 0)} L`);
    $('[data-stat-bill]') && ($('[data-stat-bill]').textContent = formatCurrency(current.estimated_bill ?? 0));
    $('[data-stat-alerts]') && ($('[data-stat-alerts]').textContent = notifications.unread ?? '--');
    $('[data-today-total]') && ($('[data-today-total]').textContent = `${formatNumber(current.total_liters ?? 0)} liters`);
    $('[data-balance]') && ($('[data-balance]').textContent = billingStatement ? formatCurrency(billingStatement.remaining_balance) : '--');
    $('[data-water-rate]') && ($('[data-water-rate]').textContent = activeBilling ? formatCurrency(activeBilling.water_rate) : '--');
    $('[data-subsidy]') && ($('[data-subsidy]').textContent = activeBilling ? formatCurrency(activeBilling.subsidy_deduction) : '--');
    $('[data-billing-month]') &&
        ($('[data-billing-month]').textContent = billingStatement?.billing_month
            ? new Date(billingStatement.billing_month).toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
            : '--');

    renderNotifications(notifications.latest ?? []);
    $('[data-live-label]') && ($('[data-live-label]').textContent = current.latest_reading ? 'Live' : 'Idle');
};

const loadDashboard = async () => {
    if (!hasTokenOrReport()) {
        return;
    }

    try {
        const readingsQuery = buildQuery({
            page: dashboardState.page,
            per_page: dashboardState.per_page,
            from: dashboardState.from,
            to: dashboardState.to,
        });

        const [summaryResponse, readingsResponse, notificationsResponse] = await Promise.all([
            requestJson('/api/summary'),
            requestJson(`/api/readings${readingsQuery}`),
            requestJson('/api/notifications?per_page=5'),
        ]);

        applySummary(summaryResponse.data);
        renderReadings(readingsResponse.data ?? []);
        renderNotifications(notificationsResponse.data ?? []);
        renderPager(readingsPager, readingsResponse, (page) => {
            dashboardState.page = page;
            loadDashboard();
        });
        setStatus('Connected');
    } catch (error) {
        if (handleAuthFailure(error)) {
            return;
        }

        console.error(error);
        setStatus('Connection failed');
        $('[data-live-label]') && ($('[data-live-label]').textContent = 'Offline');
    }
};

const loadAdmin = async () => {
    if (!hasTokenOrReport('Paste and save an admin bearer token first.')) {
        return;
    }

    const usersContainer = $('[data-admin-users]');
    const tokensContainer = $('[data-device-tokens]');
    const reportsContainer = $('[data-admin-reports]');
    const deviceSelect = $('[data-device-select]');
    const summaryLabel = $('[data-admin-summary]');
    const userForm = $('[data-admin-user-form]');
    const billingForm = $('[data-billing-form]');
    const deviceTokenForm = $('[data-device-token-form]');

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
        if (me.data?.role !== 'admin') {
            safeRedirect('/dashboard');
            return;
        }

        const usersQuery = buildQuery({
            page: adminState.page,
            per_page: adminState.per_page,
            search: adminState.search,
            role: adminState.role,
            is_active: adminState.is_active,
        });

        const [summaryResponse, usersResponse, billingResponse, consumptionResponse, notificationsResponse] = await Promise.all([
            requestJson('/api/summary'),
            requestJson(`/api/users${usersQuery}`),
            requestJson('/api/billing-settings'),
            requestJson('/api/reports/consumption'),
            requestJson('/api/reports/notifications'),
        ]);

        const users = usersResponse.data ?? [];
        const deviceUsers = users.filter((user) => user.role === 'device');
        const billingSettings = billingResponse.data ?? null;

        summaryLabel && (summaryLabel.textContent = `${usersResponse.total ?? users.length} users`);

        if (usersContainer) {
            usersContainer.innerHTML = users
                .map(
                    (user) => `
                        <div class="table-row admin-table-row">
                            <span>${user.name}</span>
                            <span>${user.email}</span>
                            <span>${user.role}</span>
                            <span>${user.is_active ? 'Active' : 'Inactive'}</span>
                        </div>
                    `,
                )
                .join('');
        }

        renderPager(adminPager, usersResponse, (page) => {
            adminState.page = page;
            loadAdmin();
        });

        if (deviceSelect) {
            deviceSelect.innerHTML = '<option value="">Choose device account</option>' +
                deviceUsers
                    .map((user) => `<option value="${user.id}">${user.name} (${user.email})</option>`)
                    .join('');
        }

        if (tokensContainer) {
            if (!deviceUsers.length) {
                tokensContainer.innerHTML = '<div class="empty-state">No device accounts found.</div>';
            } else {
                const tokenGroups = await Promise.all(
                    deviceUsers.map(async (user) => {
                        try {
                            const tokenResponse = await requestJson(`/api/devices/${user.id}/tokens`);
                            const tokens = tokenResponse.data ?? [];
                            return `
                                <article class="notification-card">
                                    <h4>${user.name}</h4>
                                    <p>${user.email}</p>
                                    <small>${tokens.length} active token(s)</small>
                                </article>
                            `;
                        } catch (error) {
                            return `
                                <article class="notification-card">
                                    <h4>${user.name}</h4>
                                    <p>${user.email}</p>
                                    <small>Unable to load tokens</small>
                                </article>
                            `;
                        }
                    }),
                );

                tokensContainer.innerHTML = tokenGroups.join('');
            }
        }

        if (reportsContainer) {
            const summary = summaryResponse.data ?? {};
            const consumption = consumptionResponse.data ?? {};
            const notifications = notificationsResponse.data ?? {};

            reportsContainer.innerHTML = `
                <div><dt>Monthly readings</dt><dd>${summary.current_month?.reading_count ?? 0}</dd></div>
                <div><dt>Monthly liters</dt><dd>${formatNumber(summary.current_month?.total_liters ?? 0)}</dd></div>
                <div><dt>Unread alerts</dt><dd>${notifications.unread ?? 0}</dd></div>
                <div><dt>Consumption rows</dt><dd>${consumption.summary?.reading_count ?? 0}</dd></div>
                <div><dt>Notification items</dt><dd>${notifications.total ?? 0}</dd></div>
            `;
        }

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
                safeRedirect('/admin');
            } catch (error) {
                if (!handleAuthFailure(error)) {
                    console.error(error);
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
                safeRedirect('/admin');
            } catch (error) {
                if (!handleAuthFailure(error)) {
                    console.error(error);
                }
            }
            });
        }

        if (deviceTokenForm && !deviceTokenForm.dataset.bound) {
            deviceTokenForm.dataset.bound = '1';
            deviceTokenForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const payload = Object.fromEntries(new FormData(deviceTokenForm).entries());
            const userId = payload.user_id;
            delete payload.user_id;

            try {
                await requestJson(`/api/devices/${userId}/tokens`, {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                safeRedirect('/admin');
            } catch (error) {
                if (!handleAuthFailure(error)) {
                    console.error(error);
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
    saveTokenButton?.addEventListener('click', () => {
        persistToken(tokenInput?.value.trim() ?? '');
        setStatus('Token saved');
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

            if (page === 'dashboard') {
                loadDashboard();
            }
        });
    }

    refreshButton?.addEventListener('click', () => {
        if (page === 'dashboard') {
            loadDashboard();
        }

        if (page === 'admin') {
            loadAdmin();
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    bootCommon();

    if (page === 'login') {
        bootLoginPage();
        return;
    }

    if (page === 'dashboard') {
        if (!readToken()) {
            setStatus('Waiting for token');
        }

        loadDashboard();
        return;
    }

    if (page === 'admin') {
        if (!readToken()) {
            setStatus('Waiting for token');
        }

        loadAdmin();
        return;
    }

    if (page === 'landing' && readToken()) {
        safeRedirect('/dashboard');
    }
});
