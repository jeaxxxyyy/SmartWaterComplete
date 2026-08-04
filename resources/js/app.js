const tokenKey = 'smart-water-token';

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

const formatNumber = (value, digits = 2) =>
    Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    });

let activeCurrency = 'PHP';

const formatCurrency = (value, currency = activeCurrency) =>
    `${currency} ${Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

const formatPersonalDisplay = (value, fallback = '--') => {
    const text = String(value ?? '').trim();
    return text ? text.toUpperCase() : fallback;
};

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

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

const formatCompactDateTime = (value) => {
    if (!value) return '--';

    return new Date(value).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const page = document.body.dataset.page ?? 'default';
const logoutButtons = $$('[data-logout]');
const statusLabel = $('[data-status]');
const tokenInput = $('[data-token-input]');
const saveTokenButton = $('[data-token-save]');
const refreshButton = $('[data-refresh]');
const householdSidebar = $('[data-household-sidebar]');
const householdSidebarToggle = $('[data-household-sidebar-toggle]');
const householdSidebarDismiss = $('[data-household-sidebar-dismiss]');
const householdSidebarCollapse = $('[data-household-sidebar-collapse]');
const notificationControl = $('[data-notification-control]');
const notificationToggle = $('[data-notification-toggle]');
const notificationDropdown = $('[data-notification-dropdown]');
const notificationDropdownList = $('[data-notification-dropdown-list]');
const notificationDropdownSummary = $('[data-notification-dropdown-summary]');
const dashboardFilterForm = $('[data-reading-filters]');
const consumptionFilterForm = $('[data-consumption-filters]');
const householdUserFilterForm = $('[data-household-user-filters]');
const adminNotificationFilterForm = $('[data-admin-notification-filters]');
const billingManagementFilterForm = $('[data-billing-management-filters]');
const reportManagementFilterForm = $('[data-report-management-filters]');
const billingDetailDialog = $('[data-billing-detail-dialog]');
const adminSearch = $('[data-admin-search]');
const adminPageTitle = $('[data-admin-page-title]');
const readingsPagers = $$('[data-readings-pager]');
const accountForm = $('[data-account-form]');
const passwordForm = $('[data-password-form]');
const accountProfileState = $('[data-account-profile-state]');
const householdBillingState = $('[data-household-billing-state]');
const toastStack = $('[data-toast-stack]');
const adminHouseholdDialog = $('[data-admin-household-dialog]');
const adminHouseholdEditForm = $('[data-admin-household-edit-form]');
const passwordToggleButtons = $$('[data-password-toggle]');
const accountSubmitButton = $('[data-account-submit]');
const passwordSubmitButton = $('[data-password-submit]');
const activityContainer = $('[data-account-activity]');
const badgeDisplay = $('[data-profile-badge]');
const passwordCriteria = $('[data-password-criteria]');

let accountFormInitial = {};
let passwordValidationState = {
    length: false,
    uppercase: false,
    number: false,
    special: false,
};
const consumptionDialog = $('[data-consumption-dialog]');
const adminAnalyticsRange = $('[data-admin-analytics-range]');
const householdAnalyticsRange = $('[data-household-analytics-range]');
const adminAnalyticsCustom = $('[data-admin-analytics-custom]');
const householdAnalyticsCustom = $('[data-household-analytics-custom]');
const householdAnalyticsCustomToggle = $('[data-household-analytics-custom-toggle]');
const adminAnalyticsFrom = $('[data-admin-analytics-from]');
const adminAnalyticsTo = $('[data-admin-analytics-to]');
const householdAnalyticsFrom = $('[data-household-analytics-from]');
const householdAnalyticsTo = $('[data-household-analytics-to]');
const historyFilterForm = $('[data-history-filters]');

const showToast = (message, type = 'success') => {
    if (!toastStack || !message) return;
    const toast = document.createElement('div');
    toast.className = `toast-alert${type === 'error' ? ' is-error' : ''}`;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    toast.textContent = message;
    toastStack.appendChild(toast);
    window.setTimeout(() => toast.remove(), type === 'error' ? 6000 : 4000);
};

const errorPayload = (error) => {
    try {
        return JSON.parse(error?.message ?? '{}');
    } catch {
        return {};
    }
};

const errorMessage = (error, fallback = 'The action could not be completed.') => {
    const payload = errorPayload(error);
    return payload.message ?? Object.values(payload.errors ?? {}).flat()[0] ?? fallback;
};

const setText = (selector, value) => {
    $$(selector).forEach((node) => {
        node.textContent = value;
    });
};

const setFieldError = (fieldName, message) => {
    const feedback = $(`[data-field-error="${fieldName}"]`);
    if (!feedback) return;
    feedback.textContent = message;
    feedback.hidden = !message;
    feedback.classList.toggle('is-error', Boolean(message));
};

const clearFieldErrors = () => {
    $$('[data-field-error]').forEach((node) => {
        node.textContent = '';
        node.hidden = true;
        node.classList.remove('is-error');
    });
};

const setHouseholdAlertCount = (value) => {
    const count = Number(value ?? 0);
    const hasUnread = count > 0;
    $$('[data-household-alert-count]').forEach((node) => {
        node.textContent = count;
        node.hidden = !hasUnread;
    });
    notificationToggle?.classList.toggle('has-unread', hasUnread);
    notificationToggle?.setAttribute('aria-label', hasUnread
        ? `Open notifications, ${count} unread`
        : 'Open notifications');
    if (notificationDropdownSummary) {
        notificationDropdownSummary.textContent = hasUnread
            ? `${count} unread message${count === 1 ? '' : 's'}`
            : 'No unread messages';
    }
};

const setNotificationDropdownOpen = (isOpen) => {
    if (!notificationDropdown || !notificationToggle) return;
    notificationDropdown.hidden = !isOpen;
    notificationToggle.setAttribute('aria-expanded', String(isOpen));
    notificationControl?.classList.toggle('is-open', isOpen);
};

const dashboardState = {
    page: 1,
    per_page: 10,
    from: '',
    to: '',
};
const historyState = {
    period: 'daily',
    from: '',
    to: '',
    search: '',
    page: 1,
    per_page: 10,
};
let consumptionHistory = { daily: [], weekly: [], monthly: [] };
let historyPeriod = 'daily';
let historyReloadQueued = false;
let notificationObserver = null;
let householdChartObserver = null;
let adminChartObserver = null;
const notificationReadInFlight = new Set();
let householdProfile = null;
let accountProfileLoaded = false;
let accountProfileLoading = false;
let householdBillingLoaded = false;
let householdBillingLoading = false;
let notificationRefreshInFlight = false;
let historyRefreshInFlight = false;
let householdAnalyticsLoadedRange = '';
let householdAnalytics = null;

const adminState = {
    page: 1,
    per_page: 10,
    search: '',
    role: '',
    is_active: '',
};

const householdUserState = {
    page: 1,
    per_page: 10,
    search: '',
    account_status: '',
};

const consumptionState = {
    page: 1,
    per_page: 10,
    search: '',
    account_status: '',
    meter_status: '',
    usage_condition: '',
    from: '',
    to: '',
};

const adminNotificationState = {
    page: 1,
    per_page: 10,
    search: '',
    type: '',
    status: '',
};

const billingManagementState = {
    page: 1,
    per_page: 10,
    search: '',
    bill_type: 'all',
    status: 'all',
    from: '',
    to: '',
};

const reportManagementState = {
    page: 1,
    per_page: 25,
    period: 'monthly',
    report_type: 'all',
    from: '',
    to: '',
    search: '',
    status: '',
};

let householdUsersById = new Map();
let adminMunicipalDevices = [];
let billingRecordsByKey = new Map();

const adminViewTitles = {
    'admin-dashboard': 'Dashboard',
    'customer-management': 'Customer Management',
    'device-management': 'Device Management',
    'water-rates': 'Water Rate Management',
    'billing-management': 'Billing',
    'consumption-monitoring': 'Consumption Monitoring',
    'leak-monitoring': 'Leak Monitoring',
    reports: 'Reports',
    notifications: 'Notifications',
};

const updateAdminPageTitle = (target) => {
    if (adminPageTitle) {
        adminPageTitle.textContent = adminViewTitles[target] ?? 'Dashboard';
    }
};

const DASHBOARD_REFRESH_MS = 3000;
let dashboardRefreshTimer = null;
let dashboardRefreshInFlight = false;
const ADMIN_DEVICE_REFRESH_MS = 10000;
let adminDeviceRefreshTimer = null;
let adminDeviceRefreshInFlight = false;

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

const stopAdminDevicePolling = () => {
    if (adminDeviceRefreshTimer) {
        window.clearTimeout(adminDeviceRefreshTimer);
        adminDeviceRefreshTimer = null;
    }
};

const scheduleAdminDevicePolling = () => {
    stopAdminDevicePolling();

    if (page !== 'admin' || !readToken()) return;

    adminDeviceRefreshTimer = window.setTimeout(() => {
        loadAdminDevices();
    }, ADMIN_DEVICE_REFRESH_MS);
};

const bindAdminUserDeleteActions = (container) => {
    if (!container || container.dataset.deleteBound === '1') {
        return;
    }

    container.dataset.deleteBound = '1';
    container.addEventListener('click', async (event) => {
        const editButton = event.target.closest('[data-edit-user]');
        if (editButton) {
            const user = householdUsersById.get(String(editButton.dataset.editUser));
            if (user) openAdminHouseholdDialog(user);
            return;
        }

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

const setAdminUserFieldErrors = (form, errors = {}) => {
    const hasSpecificErrors = Object.keys(errors).length > 0;

    $$('[data-admin-user-field-error]', form).forEach((errorNode) => {
        const field = errorNode.dataset.adminUserFieldError;
        if (hasSpecificErrors && !Object.prototype.hasOwnProperty.call(errors, field)) return;

        const message = Array.isArray(errors[field]) ? errors[field][0] : (errors[field] ?? '');
        const input = form.elements.namedItem(field);

        errorNode.textContent = message;
        input?.classList.toggle('is-invalid', Boolean(message));
        input?.setAttribute('aria-invalid', message ? 'true' : 'false');
    });
};

const setAdminUserFormFeedback = (form, message = '', type = '') => {
    const feedback = $('[data-admin-user-form-status], [data-admin-household-edit-status]', form);
    if (!feedback) return;

    feedback.textContent = message;
    feedback.hidden = !message;
    feedback.classList.toggle('is-error', type === 'error');
    feedback.classList.toggle('is-loading', type === 'loading');
};

const openAdminHouseholdDialog = (user) => {
    if (!adminHouseholdDialog || !adminHouseholdEditForm) return;

    adminHouseholdEditForm.elements.user_id.value = user.id;
    adminHouseholdEditForm.elements.name.value = user.name ?? '';
    adminHouseholdEditForm.elements.email.value = user.email ?? '';
    adminHouseholdEditForm.elements.address.value = user.address ?? '';
    adminHouseholdEditForm.elements.phone.value = user.phone ?? '';
    adminHouseholdEditForm.elements.is_active.checked = Boolean(user.is_active && (user.account_status ?? 'active') === 'active');
    adminHouseholdEditForm.dataset.wasActive = adminHouseholdEditForm.elements.is_active.checked ? '1' : '0';

    const deviceSelect = adminHouseholdEditForm.elements.device_id;
    const assignedDevice = adminMunicipalDevices.find((device) => Number(device.household_id) === Number(user.id))
        ?? (Array.isArray(user.devices) ? user.devices[0] : null);
    adminHouseholdEditForm.dataset.currentDeviceId = assignedDevice?.id ?? '';
    const assignmentOptions = assignedDevice
        ? `<option value="__unassign">Unassign ${escapeHtml(formatPersonalDisplay(assignedDevice.name, 'current meter'))}</option>`
        : '<option value="">No meter currently assigned</option>';
    deviceSelect.innerHTML = assignmentOptions + '<option value="">Keep current assignment</option>' +
        adminMunicipalDevices
            .filter((device) => device.status !== 'decommissioned')
            .map((device) => `<option value="${device.id}"${Number(device.id) === Number(assignedDevice?.id) ? ' selected' : ''}>${escapeHtml(formatPersonalDisplay(device.name))} · ${escapeHtml(device.asset_number)}${device.household_id && Number(device.household_id) !== Number(user.id) ? ' · assigned' : ''}</option>`)
            .join('');

    setAdminUserFormFeedback(adminHouseholdEditForm);
    setAdminUserFieldErrors(adminHouseholdEditForm);
    adminHouseholdEditForm.dataset.saving = '0';
    if (typeof adminHouseholdDialog.showModal === 'function') {
        adminHouseholdDialog.showModal();
    } else {
        adminHouseholdDialog.setAttribute('open', '');
    }
};

const closeAdminHouseholdDialog = () => {
    if (!adminHouseholdDialog) return;
    if (typeof adminHouseholdDialog.close === 'function') {
        adminHouseholdDialog.close();
    } else {
        adminHouseholdDialog.removeAttribute('open');
    }
};

const bindAdminHouseholdEditForm = () => {
    if (!adminHouseholdEditForm || adminHouseholdEditForm.dataset.bound === '1') return;

    adminHouseholdEditForm.dataset.bound = '1';
    $('[data-admin-household-dialog-close]')?.addEventListener('click', closeAdminHouseholdDialog);
    $('[data-admin-household-dialog-cancel]')?.addEventListener('click', closeAdminHouseholdDialog);

    adminHouseholdEditForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (adminHouseholdEditForm.dataset.saving === '1') return;

        const submitButton = $('[data-admin-household-edit-submit]', adminHouseholdEditForm);
        const userId = adminHouseholdEditForm.elements.user_id.value;
        const payload = {
            name: adminHouseholdEditForm.elements.name.value.trim(),
            email: adminHouseholdEditForm.elements.email.value.trim(),
            address: adminHouseholdEditForm.elements.address.value.trim(),
            phone: adminHouseholdEditForm.elements.phone.value.trim(),
            is_active: adminHouseholdEditForm.elements.is_active.checked,
            account_status: adminHouseholdEditForm.elements.is_active.checked ? 'active' : 'suspended',
        };

        if (adminHouseholdEditForm.dataset.wasActive === '1' && !payload.is_active
            && !window.confirm('Deactivate this household account? The user will no longer be able to sign in.')) {
            return;
        }

        const editErrors = {};
        if (!payload.name) editErrors.name = ['Full name is required.'];
        if (!payload.email) editErrors.email = ['Email address is required.'];
        else if (!/^\S+@\S+\.\S+$/.test(payload.email)) editErrors.email = ['Enter a valid email address.'];
        if (!payload.address) editErrors.address = ['Address is required.'];

        if (Object.keys(editErrors).length) {
            setAdminUserFieldErrors(adminHouseholdEditForm, editErrors);
            setAdminUserFormFeedback(adminHouseholdEditForm, 'Please correct the highlighted fields.', 'error');
            return;
        }

        adminHouseholdEditForm.dataset.saving = '1';
        adminHouseholdEditForm.setAttribute('aria-busy', 'true');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';
        }
        setAdminUserFormFeedback(adminHouseholdEditForm, 'Saving household account changes...', 'loading');

        try {
            await requestJson(`/api/users/${userId}`, { method: 'PUT', body: JSON.stringify(payload) });

            const selectedDeviceId = adminHouseholdEditForm.elements.device_id.value;
            const currentDeviceId = adminHouseholdEditForm.dataset.currentDeviceId;
            if (selectedDeviceId === '__unassign' && currentDeviceId) {
                await requestJson(`/api/municipal-devices/${currentDeviceId}`, {
                    method: 'PATCH',
                    body: JSON.stringify({ household_id: null }),
                });
            } else if (selectedDeviceId) {
                await requestJson(`/api/municipal-devices/${selectedDeviceId}`, {
                    method: 'PATCH',
                    body: JSON.stringify({ household_id: Number(userId) }),
                });
                if (currentDeviceId && Number(currentDeviceId) !== Number(selectedDeviceId)) {
                    await requestJson(`/api/municipal-devices/${currentDeviceId}`, {
                        method: 'PATCH',
                        body: JSON.stringify({ household_id: null }),
                    });
                }
            }

            closeAdminHouseholdDialog();
            showToast('Household account updated successfully.');
            await loadAdmin();
        } catch (error) {
            if (!handleAuthFailure(error)) {
                setAdminUserFormFeedback(adminHouseholdEditForm, errorMessage(error, 'Could not update the household account.'), 'error');
                showToast(errorMessage(error, 'Could not update the household account.'), 'error');
            }
        } finally {
            adminHouseholdEditForm.dataset.saving = '0';
            adminHouseholdEditForm.removeAttribute('aria-busy');
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'Save changes';
            }
        }
    });
};

const validateAdminUserForm = (form) => {
    const values = Object.fromEntries(new FormData(form).entries());
    const errors = {};
    const email = String(values.email ?? '').trim();

    if (!String(values.name ?? '').trim()) {
        errors.name = ['Name is required.'];
    }

    if (!email) {
        errors.email = ['Email address is required.'];
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errors.email = ['Enter a valid email address.'];
    }

    if (!String(values.password ?? '')) {
        errors.password = ['Password is required.'];
    } else if (String(values.password).length < 8) {
        errors.password = ['Password must be at least 8 characters.'];
    }

    if (!String(values.address ?? '').trim()) {
        errors.address = ['Address is required.'];
    }

    return errors;
};

const bindAdminUserForm = (form) => {
    if (!form || form.dataset.bound === '1') return;

    form.dataset.bound = '1';
    const submitButton = $('[data-admin-user-submit]', form);
    const defaultButtonText = submitButton?.textContent.trim() ?? 'Create household user';

    form.querySelectorAll('input, select').forEach((field) => {
        field.addEventListener('input', () => setAdminUserFieldErrors(form, { [field.name]: '' }));
        field.addEventListener('change', () => setAdminUserFieldErrors(form, { [field.name]: '' }));
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (form.dataset.saving === '1') return;

        setAdminUserFieldErrors(form);
        const clientErrors = validateAdminUserForm(form);
        if (Object.keys(clientErrors).length) {
            setAdminUserFieldErrors(form, clientErrors);
            setAdminUserFormFeedback(form, 'Please correct the highlighted fields.', 'error');
            return;
        }

        const payload = Object.fromEntries(new FormData(form).entries());
        Object.keys(payload).forEach((key) => {
            if (payload[key] === '') delete payload[key];
        });

        form.dataset.saving = '1';
        form.setAttribute('aria-busy', 'true');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';
        }
        setAdminUserFormFeedback(form, 'Saving household user account...', 'loading');

        try {
            const response = await requestJson('/api/household-users', {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            form.reset();
            setAdminUserFieldErrors(form);
            setAdminUserFormFeedback(form, response?.message ?? 'Household user account created successfully. The user can now log in.');
            showToast(response?.message ?? 'Household user account created successfully. The user can now log in.');
            householdUserState.page = 1;

            try {
                await loadAdminUsers({ householdsOnly: true });
            } catch (refreshError) {
                showToast('Account created, but the customer list could not be refreshed.', 'error');
            }
        } catch (error) {
            if (handleAuthFailure(error)) return;

            const payload = errorPayload(error);
            setAdminUserFieldErrors(form, payload.errors ?? {});
            setAdminUserFormFeedback(form, errorMessage(error, 'Could not create the household user account.'), 'error');
            showToast(errorMessage(error, 'Could not create the household user account.'), 'error');
        } finally {
            form.dataset.saving = '0';
            form.removeAttribute('aria-busy');
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = defaultButtonText;
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

const analyticsQuery = (select, fromInput, toInput, fallback) => buildQuery({
    range: select?.value || fallback,
    from: select?.value === 'custom' ? fromInput?.value : '',
    to: select?.value === 'custom' ? toInput?.value : '',
});

const updateCustomAnalyticsRange = (select, container) => {
    if (!select || !container) return;
    const isCustom = select.value === 'custom';
    container.hidden = !isCustom;
    select.setAttribute('aria-expanded', String(isCustom));
};

const setHouseholdCustomRangeOpen = (isOpen) => {
    if (!householdAnalyticsRange || !householdAnalyticsCustom) return;
    const shouldOpen = Boolean(isOpen) && householdAnalyticsRange.value === 'custom';
    householdAnalyticsCustom.hidden = !shouldOpen;
    householdAnalyticsRange.setAttribute('aria-expanded', String(shouldOpen));
    householdAnalyticsCustomToggle?.setAttribute('aria-expanded', String(shouldOpen));
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

const renderHouseholdAreaChart = (container, series, options = {}) => {
    if (!container) return;

    const rows = (Array.isArray(series) ? series : []).map((row) => {
        const readingCount = row.reading_count === undefined || row.reading_count === null
            ? null
            : Number(row.reading_count);
        const value = Number(row.total_cubic_meter ?? row.consumption ?? 0);

        return {
            label: String(row.label ?? row.period ?? row.month ?? ''),
            value: Number.isFinite(value) ? value : 0,
            hasReading: readingCount === null ? value > 0 : readingCount > 0,
        };
    });
    const actualIndexes = rows.reduce((indexes, row, index) => {
        if (row.hasReading) indexes.push(index);
        return indexes;
    }, []);
    const hasData = options.dataAvailable ?? actualIndexes.length > 0;
    const emptyMarkup = options.emptyMessage ?? '<div class="empty-state">No consumption history available yet.</div>';

    if (!hasData || !actualIndexes.length) {
        container.classList.add('is-empty');
        container.dataset.chartSignature = JSON.stringify({ rows, state: 'empty' });
        container.innerHTML = emptyMarkup;
        return;
    }

    container.classList.remove('is-empty');
    const chartRows = actualIndexes.map((index) => rows[index]);
    const signature = JSON.stringify({ chartRows, state: 'area' });
    if (container.dataset.chartSignature === signature) return;
    container.dataset.chartSignature = signature;

    const width = 760;
    const height = 320;
    const padding = { top: 22, right: 20, bottom: 52, left: 58 };
    const plotWidth = width - padding.left - padding.right;
    const plotHeight = height - padding.top - padding.bottom;
    const maxValue = Math.max(...chartRows.filter((row) => row.hasReading).map((row) => row.value), 0);
    const domainMax = maxValue > 0 ? maxValue * 1.15 : 1;
    const xFor = (index) => chartRows.length === 1
        ? padding.left + plotWidth / 2
        : padding.left + (index / (chartRows.length - 1)) * plotWidth;
    const yFor = (value) => padding.top + plotHeight - (value / domainMax) * plotHeight;
    const points = chartRows.map((row, index) => row.hasReading
        ? { x: xFor(index), y: yFor(row.value), row }
        : null);
    const segments = [];
    let segment = [];
    points.forEach((point) => {
        if (point) {
            segment.push(point);
        } else if (segment.length) {
            segments.push(segment);
            segment = [];
        }
    });
    if (segment.length) segments.push(segment);

    const linePath = segments.map((items) => items.map((point, index) => `${index ? 'L' : 'M'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`).join(' ')).join(' ');
    const areaPath = segments.map((items) => {
        const first = items[0];
        const last = items[items.length - 1];
        return `M ${first.x.toFixed(2)} ${(padding.top + plotHeight).toFixed(2)} ${items.map((point) => `L ${point.x.toFixed(2)} ${point.y.toFixed(2)}`).join(' ')} L ${last.x.toFixed(2)} ${(padding.top + plotHeight).toFixed(2)} Z`;
    }).join(' ');
    const grid = [0, 1 / 3, 2 / 3, 1].map((ratio) => {
        const y = padding.top + plotHeight - ratio * plotHeight;
        return `<line class="area-chart-grid-line" x1="${padding.left}" x2="${width - padding.right}" y1="${y}" y2="${y}"></line><text class="area-chart-y-label" x="${padding.left - 10}" y="${y + 4}" text-anchor="end">${escapeHtml(formatNumber(domainMax * ratio))}</text>`;
    }).join('');
    const xLabelIndexes = [...new Set([0, Math.floor((chartRows.length - 1) / 2), chartRows.length - 1])];
    const xLabels = xLabelIndexes.map((index) => `<text class="area-chart-x-label" x="${xFor(index)}" y="${height - 17}" text-anchor="middle">${escapeHtml(chartRows[index].label)}</text>`).join('');
    const pointMarkup = points.filter(Boolean).map((point) => `
        <circle class="area-chart-point" cx="${point.x}" cy="${point.y}" r="4" tabindex="0" role="button"
            data-chart-label="${escapeHtml(point.row.label)}"
            data-chart-value="${escapeHtml(formatCubicMeterValue(point.row.value))}"
            aria-label="${escapeHtml(`${point.row.label}: ${formatCubicMeterValue(point.row.value)}`)}"></circle>
    `).join('');
    const wasAnimated = container.classList.contains('is-animated');
    container.classList.remove('is-animated');
    container.innerHTML = `
        <div class="area-chart-shell">
            <svg class="area-line-chart" viewBox="0 0 ${width} ${height}" role="img" aria-label="Monthly household water consumption trend">
                <g class="area-chart-grid">${grid}</g>
                <path class="area-chart-area" d="${areaPath}"></path>
                <path class="area-chart-line" d="${linePath}"></path>
                <g class="area-chart-points">${pointMarkup}</g>
                <g class="area-chart-x-labels">${xLabels}</g>
            </svg>
            <div class="area-chart-tooltip" data-chart-tooltip role="status" hidden></div>
        </div>
    `;

    const shell = $('.area-chart-shell', container);
    const tooltip = $('[data-chart-tooltip]', container);
    const showTooltip = (point) => {
        if (!tooltip || !shell) return;
        tooltip.textContent = `${point.dataset.chartLabel}: ${point.dataset.chartValue}`;
        tooltip.hidden = false;
        const shellRect = shell.getBoundingClientRect();
        const chartRect = $('.area-line-chart', shell).getBoundingClientRect();
        const x = ((Number(point.getAttribute('cx')) / width) * chartRect.width) + (chartRect.left - shellRect.left);
        const y = ((Number(point.getAttribute('cy')) / height) * chartRect.height) + (chartRect.top - shellRect.top);
        tooltip.style.left = `${Math.max(8, Math.min(shellRect.width - tooltip.offsetWidth - 8, x - tooltip.offsetWidth / 2))}px`;
        tooltip.style.top = `${Math.max(8, y - tooltip.offsetHeight - 12)}px`;
    };
    $$('.area-chart-point', container).forEach((point) => {
        point.addEventListener('pointerenter', () => showTooltip(point));
        point.addEventListener('focus', () => showTooltip(point));
        point.addEventListener('click', () => showTooltip(point));
        point.addEventListener('pointerleave', (event) => {
            if (event.pointerType !== 'touch' && tooltip) tooltip.hidden = true;
        });
    });

    if (wasAnimated) {
        container.classList.add('is-animated');
    } else if (container.closest('.dashboard-view')?.classList.contains('is-active')) {
        observeHouseholdChart(container);
    }
};

const renderMonthlyChart = (container, series, options = {}) => {
    if (!container) {
        return;
    }

    if (container.matches('[data-household-monthly-chart]')) {
        renderHouseholdAreaChart(container, series, options);
        return;
    }

    const rows = Array.isArray(series) ? series : [];
    const hasBills = options.hasBills ?? false;
    const hasData = options.dataAvailable ?? rows.some((row) => Number(row.total_cubic_meter ?? 0) > 0 || Number(row.estimated_bill ?? 0) > 0);
    const chartRows = (hasData ? rows.filter((row) => row.reading_count === undefined || Number(row.reading_count) > 0) : []).slice(-3);
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

const isConfiguredAdmin = (user) => user?.role === 'admin';

const roleLabelForUser = (user) => {
    if (isConfiguredAdmin(user)) return 'Administrator';
    if (user?.role === 'user') return 'Household User';
    return 'Unsupported account';
};

const pathForUser = (user) => {
    if (isConfiguredAdmin(user)) return '/admin';
    if (user?.role === 'user') return '/household';
    return '/login';
};

const redirectToRoleHome = async () => {
    try {
        const response = await requestJson('/api/me');
        safeRedirect(pathForUser(response.data));
    } catch (error) {
        persistToken('');
        safeRedirect('/login');
    }
};

const bootHomeSessionActions = () => {
    const actions = $$('[data-home-session-action]');
    if (page !== 'home' || !actions.length || !readToken()) return;

    let destination = '';
    const destinationPromise = requestJson('/api/me')
        .then((response) => pathForUser(response.data))
        .catch(() => {
            persistToken('');
            return '/login';
        })
        .then((target) => {
            destination = target;

            actions.forEach((action) => {
                const label = $('[data-home-session-label]', action);
                action.href = target;
                action.removeAttribute('aria-busy');

                if (target === '/login' && label) {
                    label.textContent = action.dataset.signedOutLabel ?? 'Sign In';
                }
            });

            return target;
        });

    actions.forEach((action) => {
        const label = $('[data-home-session-label]', action);
        action.dataset.signedOutLabel = label?.textContent?.trim() ?? 'Sign In';
        action.href = '#';
        action.setAttribute('aria-busy', 'true');

        if (label) {
            label.textContent = action.dataset.authenticatedLabel ?? 'Open Dashboard';
        }

        action.addEventListener('click', async (event) => {
            if (destination) return;

            event.preventDefault();
            safeRedirect(await destinationPromise);
        });
    });
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
        safeRedirect(user.role === 'user' ? '/household' : '/login');
        return true;
    }

    if (expectedRole === 'household' && isConfiguredAdmin(user)) {
        safeRedirect('/admin');
        return true;
    }

    if (expectedRole === 'household' && user.role !== 'user') {
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
        const isActive = button.dataset.householdNav === target;
        const isSidebarItem = button.closest('.household-sidebar-nav');
        button.classList.toggle('is-active', Boolean(isSidebarItem && isActive));
        if (isSidebarItem && isActive) {
            button.setAttribute('aria-current', 'page');
        } else {
            button.removeAttribute('aria-current');
        }
    });
    const activeView = $(`[data-household-view="${target}"]`);
    if (activeView) animateVisibleCharts(activeView);
    if (householdSidebar?.classList.contains('is-open')) {
        householdSidebar.classList.remove('is-open');
        householdSidebarToggle?.setAttribute('aria-expanded', 'false');
    }
    if (target === 'notifications') loadNotifications();
    if (target === 'history') loadConsumptionHistory();
    if (target === 'billing') loadHouseholdBilling({ force: true });
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
    updateAdminPageTitle(target);
    const activeView = $(`[data-admin-view="${target}"]`);
    if (activeView) animateVisibleCharts(activeView);
};

const hasTokenOrReport = () => {
    if (readToken()) {
        return true;
    }

    // Admin authentication is established by the login flow and persisted
    // in the current browser session. The dashboard never asks staff to
    // paste or manually manage a bearer token.
    setStatus('Redirecting to sign in.');
    safeRedirect('/login');
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
            stopAdminDevicePolling();
            stopAdminLeakPolling();
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

const completeLoginTransition = async (destination) => {
    const transition = $('[data-login-transition]');
    if (!transition) {
        safeRedirect(destination);
        return;
    }

    document.body.classList.add('login-is-transitioning');
    transition.hidden = false;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    await new Promise((resolve) => window.setTimeout(resolve, reducedMotion ? 250 : 850));
    safeRedirect(destination);
};

const bootLoginPage = () => {
    const authStatus = $('[data-auth-status]');

    const report = (message, state = 'info') => {
        if (authStatus) {
            authStatus.textContent = message;
            authStatus.hidden = !message;
            authStatus.classList.toggle('is-error', state === 'error');
        }
    };

    $$('[data-tab-target]').forEach((button) => {
        button.addEventListener('click', () => activeTab(button.dataset.tabTarget));
    });

    $$('[data-auth-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (form.dataset.submitting === 'true') return;

            const submitButton = $('button[type="submit"]', form);
            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());
            form.dataset.submitting = 'true';
            if (submitButton) submitButton.disabled = true;

            try {
                if (form.dataset.authForm === 'login') {
                    report('Signing in...');
                    const response = await submitJsonForm('/api/login', payload);
                    const destination = pathForUser(response.data.user);
                    if (destination === '/login') {
                        persistToken('');
                        report('This account type does not have an application dashboard.');
                        return;
                    }
                    persistToken(response.data.token);
                    report('Login successful.');
                    await completeLoginTransition(destination);
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
                report(handleAuthFailure(error)
                    ? 'Session expired.'
                    : errorMessage(error, 'Unable to log in. Please check your credentials or contact the administrator.'), 'error');
            } finally {
                form.dataset.submitting = 'false';
                if (submitButton) submitButton.disabled = false;
            }
        });
    });

    if (readToken()) {
        redirectToRoleHome();
    }
};

const renderReadings = (items) => {
    const containers = $$('[data-live-readings-list]');
    const previews = $$('[data-recent-readings-list]');
    if (!containers.length && !previews.length) {
        return;
    }

    if (!items?.length) {
        containers.forEach((container) => {
            container.innerHTML = '<div class="table-empty-state"><i class="bi bi-broadcast" aria-hidden="true"></i><strong>No recent readings</strong><span>The connected meter has not submitted data for this account yet.</span></div>';
        });
        previews.forEach((container) => {
            container.innerHTML = '<div class="dashboard-empty-state"><strong>No recent readings yet</strong><span>Readings will appear here after the connected water meter sends an update.</span></div>';
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

    previews.forEach((container) => {
        container.innerHTML = items.slice(0, 4).map((reading) => {
            const status = reading.is_possible_leak ? 'Leak detected' : reading.is_threshold_exceeded ? 'Usage limit reached' : 'Normal';
            const statusClass = reading.is_possible_leak ? 'badge badge-leak' : reading.is_threshold_exceeded ? 'badge badge-threshold' : 'badge badge-normal';

            return `<div class="recent-reading-row">
                <div><strong>${formatDateTime(reading.recorded_at)}</strong><small>${formatNumber(reading.flow_rate)} L/min · ${formatCubicMeters(reading.liters_used)}</small></div>
                <span class="${statusClass}">${status}</span>
            </div>`;
        }).join('');
    });
};

const renderHistorySummary = (summary) => {
    setText('[data-history-period-label]', `${historyPeriod.charAt(0).toUpperCase() + historyPeriod.slice(1)} trend`);
    if (!summary) return;
    setText('[data-history-today-usage]', `${formatNumber(summary.today_usage, 3)} L`);
    setText('[data-history-week-usage]', `${formatNumber(summary.week_usage, 3)} L`);
    setText('[data-history-month-usage]', `${formatNumber(summary.month_usage, 3)} L`);
    setText('[data-history-estimated-bill]', formatCurrency(summary.estimated_bill, summary.currency));
    setText('[data-history-average-usage]', `${formatNumber(summary.average_daily_usage, 3)} L`);
};

const renderHistoryChart = (chartData = []) => {
    const container = $('[data-history-chart]');
    if (!container) return;
    renderHouseholdAreaChart(container, chartData.map((item) => ({
        label: item.label,
        total_cubic_meter: item.value,
        reading_count: 1,
    })), {
        dataAvailable: Array.isArray(chartData) && chartData.length > 0,
        emptyMessage: '<div class="chart-empty-state"><strong>No consumption trend data available</strong><span>Try applying a different date range.</span></div>',
    });
};

const historyTableHeadLabels = (period) => {
    const label = period === 'weekly' ? 'Week' : period === 'monthly' ? 'Month' : 'Day';
    const head = $('[data-history-table-head]');
    if (!head) return;
    head.innerHTML = `
        <span>${label}</span>
        <span>Total liters</span>
        <span>Total m³</span>
        <span>Estimated bill</span>
        <span>Status</span>
    `;
};

const exportHistoryCsv = (items) => {
    if (!Array.isArray(items) || !items.length) {
        showToast('No history rows available to export.', 'error');
        return;
    }

    const headers = ['Period', 'Total liters', 'Total cubic meter', 'Estimated bill', 'Status'];
    const rows = items.map((item) => {
        const label = historyPeriod === 'weekly'
            ? `Week of ${new Date(`${item.period}T00:00:00`).toLocaleDateString()}`
            : historyPeriod === 'monthly'
                ? new Date(`${item.period}-01T00:00:00`).toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
                : new Date(`${item.period}T00:00:00`).toLocaleDateString();
        return [
            label,
            formatNumber(item.total_liters, 3) + ' L',
            formatCubicMeterValue(item.total_cubic_meter),
            formatCurrency(item.estimated_bill),
            item.status || 'Normal',
        ];
    });

    const csv = [headers.join(','), ...rows.map((row) => row.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(','))].join('\r\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `consumption-history-${historyPeriod}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 60000);
};

const renderConsumptionHistory = () => {
    const container = $('[data-history-list]');
    if (!container) return;
    const items = consumptionHistory[historyPeriod] ?? [];
    historyTableHeadLabels(historyPeriod);
    if (!items.length) {
        container.innerHTML = '<div class="table-empty-state"><i class="bi bi-bar-chart-line" aria-hidden="true"></i><strong>No usage records yet</strong><span>Try another period, or wait for the meter to submit readings.</span></div>';
        return;
    }

    container.innerHTML = items.map((item) => {
        const label = historyPeriod === 'weekly'
            ? `Week of ${new Date(`${item.period}T00:00:00`).toLocaleDateString()}`
            : historyPeriod === 'monthly'
                ? new Date(`${item.period}-01T00:00:00`).toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
                : new Date(`${item.period}T00:00:00`).toLocaleDateString();
        const alerts = Number(item.leak_count || 0) + Number(item.limit_count || 0);
        const statusClass = item.condition === 'leak'
            ? 'badge badge-leak'
            : item.condition === 'threshold'
                ? 'badge badge-threshold'
                : item.condition === 'night'
                    ? 'badge badge-muted'
                    : 'badge badge-normal';

        return `<div class="table-row">
            <span>${escapeHtml(label)}</span>
            <span>${formatNumber(item.total_liters, 3)} L</span>
            <span>${formatCubicMeterValue(item.total_cubic_meter)}</span>
            <span>${formatCurrency(item.estimated_bill)}</span>
            <span><span class="${statusClass}">${escapeHtml(item.status || (alerts ? `${alerts} alert(s)` : 'Normal'))}</span></span>
        </div>`;
    }).join('');
};

const notificationTypeLabel = (type) => ({
    announcement: 'General announcement',
    service_interruption: 'Service interruption',
    maintenance: 'Scheduled maintenance',
    billing: 'Billing notice',
    leak_alert: 'Leak or emergency alert',
    device_system: 'Device or system notice',
    possible_leak: 'Leak alert',
    threshold_exceeded: 'Usage threshold alert',
}[type] ?? 'System notification');

const renderNotificationDropdown = (items) => {
    if (!notificationDropdownList) return;

    const notifications = Array.isArray(items) ? items.slice(0, 6) : [];
    if (!notifications.length) {
        notificationDropdownList.innerHTML = `
            <div class="notification-dropdown-empty">
                <i class="bi bi-bell-slash" aria-hidden="true"></i>
                <strong>You’re all caught up</strong>
                <span>New messages will appear here.</span>
            </div>`;
        return;
    }

    notificationDropdownList.innerHTML = notifications.map((notification) => {
        const isUnread = !notification.is_read;
        return `
            <button class="notification-dropdown-item${isUnread ? ' is-unread' : ''}" type="button" data-notification-dropdown-item data-notification-id="${notification.id}" data-notification-read="${notification.is_read ? '1' : '0'}">
                <span class="notification-dropdown-icon" aria-hidden="true"><i class="bi ${notification.type === 'leak_alert' || notification.type === 'possible_leak' ? 'bi-exclamation-triangle' : 'bi-bell'}"></i></span>
                <span class="notification-dropdown-copy">
                    <strong>${escapeHtml(notification.title)}</strong>
                    <span>${escapeHtml(notification.message)}</span>
                    <small>${escapeHtml(notificationTypeLabel(notification.type))} · ${escapeHtml(formatCompactDateTime(notification.sent_at ?? notification.created_at))}</small>
                </span>
                <i class="notification-dropdown-unread-dot" aria-label="Unread"></i>
            </button>`;
    }).join('');
};

const renderNotifications = (items, selector = '[data-notifications-list]') => {
    const containers = $$(selector);
    if (!containers.length) {
        return;
    }

    const isPreview = selector === '[data-dashboard-notifications-list]';
    if (!isPreview) {
        const clearButton = $('[data-clear-notifications]');
        if (clearButton) clearButton.disabled = !items?.length;
    }

    if (!items?.length) {
        const emptyMessage = selector === '[data-dashboard-notifications-list]'
            ? '<div class="dashboard-empty-state"><strong>No recent notifications</strong><span>New alerts and service updates will appear here.</span></div>'
            : '<div class="notification-page-empty"><i class="bi bi-bell-slash" aria-hidden="true"></i><strong>No notifications yet</strong><span>New alerts and service updates will appear here automatically.</span></div>';
        containers.forEach((container) => {
            container.innerHTML = emptyMessage;
        });
        return;
    }

    const markup = (isPreview ? items.slice(0, 3) : items)
        .map((notification) => {
            const label = notificationTypeLabel(notification.type);

            return `
            <article class="notification-card${isPreview ? ' dashboard-notification-card' : ''}" data-notification-id="${notification.id}" data-notification-read="${notification.is_read ? '1' : '0'}" tabindex="0">
                <div class="notification-head">
                    <h4>${escapeHtml(notification.title)}</h4>
                    <span class="status-chip ${notification.is_read ? 'status-chip-muted' : 'status-chip-soft'}">${notification.is_read ? 'Read' : 'Unread'}</span>
                </div>
                <p class="notification-tag">${escapeHtml(label)}</p>
                <p>${escapeHtml(notification.message)}</p>
                <small>${formatCompactDateTime(notification.sent_at ?? notification.created_at)}</small>
                ${isPreview ? '' : `<button type="button" class="link-button rose" data-delete-received-notification="${notification.id}">Delete</button>`}
            </article>
        `;
        })
        .join('');

    containers.forEach((container) => {
        container.innerHTML = markup;
        bindNotificationReadTracking(container);
    });
};

const loadNotifications = async () => {
    if (notificationRefreshInFlight || !readToken()) return;
    notificationRefreshInFlight = true;

    try {
        const response = await requestJson('/api/notifications?per_page=100');
        const items = responseItems(response);
        renderNotifications(items);
        renderNotifications(items, '[data-dashboard-notifications-list]');
        renderNotificationDropdown(items);
        setHouseholdAlertCount(items.filter((item) => !item.is_read).length);
    } catch (error) {
        if (handleAuthFailure(error)) return;
        $$('[data-notifications-list], [data-dashboard-notifications-list]').forEach((container) => {
            container.innerHTML = '<div class="empty-state is-error">Unable to retrieve notifications. Try again.</div>';
        });
        if (notificationDropdownList) {
            notificationDropdownList.innerHTML = '<div class="notification-dropdown-empty is-error">Unable to load notifications.</div>';
        }
    } finally {
        notificationRefreshInFlight = false;
    }
};

const loadConsumptionHistory = async () => {
    if (!readToken()) return;
    if (historyRefreshInFlight) {
        historyReloadQueued = true;
        return;
    }

    historyRefreshInFlight = true;
    const requestedPeriod = historyState.period;
    $('[data-history-tabs]')?.setAttribute('aria-busy', 'true');

    try {
        const response = await requestJson(`/api/consumption-history${buildQuery({
            period: historyState.period,
            from: historyState.from,
            to: historyState.to,
            search: historyState.search,
            page: historyState.page,
            per_page: historyState.per_page,
        })}`);

        const data = response.data ?? {};
        if (requestedPeriod !== historyState.period) {
            historyReloadQueued = true;
            return;
        }

        consumptionHistory = {
            daily: data.daily ?? [],
            weekly: data.weekly ?? [],
            monthly: data.monthly ?? [],
        };
        historyState.page = data.meta?.current_page ?? historyState.page;
        renderHistorySummary(data.summary);
        renderHistoryChart(data.chart);
        renderConsumptionHistory();
        renderPager($('[data-history-pager]'), data.meta ?? {}, (page) => {
            historyState.page = page;
            loadConsumptionHistory();
        });
    } catch (error) {
        if (handleAuthFailure(error)) return;
        const container = $('[data-history-list]');
        if (container) container.innerHTML = '<div class="empty-state is-error">Unable to retrieve consumption history. Try again.</div>';
    } finally {
        historyRefreshInFlight = false;
        $('[data-history-tabs]')?.removeAttribute('aria-busy');
        if (historyReloadQueued) {
            historyReloadQueued = false;
            loadConsumptionHistory();
        }
    }
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
        card.classList.remove('is-unread');
        $('.notification-dropdown-unread-dot', card)?.remove();
        const unreadCount = Math.max(0, Number($('[data-household-alert-count]')?.textContent ?? 0) - 1);
        setHouseholdAlertCount(unreadCount);
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
    setText('[data-account-role]', roleLabelForUser(user));
    setText('[data-profile-name]', String(user?.name ?? '').trim() || 'Household account');
    setText('[data-profile-email]', user?.email ?? '--');
    setText('[data-profile-account]', user?.account_number ?? '--');
    setText('[data-profile-address]', String(user?.address ?? '').trim() || 'No address recorded');
    setText('[data-profile-phone]', user?.phone ?? '--');
    setText('[data-profile-role]', roleLabelForUser(user));
    setText('[data-profile-status]', user?.is_active && (user?.account_status ?? 'active') === 'active' ? 'Active' : String(user?.account_status ?? 'Inactive').replaceAll('_', ' '));
    setText('[data-profile-badge]', user?.is_active && (user?.account_status ?? 'active') === 'active' ? '🟢 Verified Household' : '⚠️ Household status pending');
    setText('[data-profile-initials]', (user?.name ?? 'SW')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join(''));
    setText('[data-profile-password-changed]', user?.password_changed_at ? formatDateTime(user.password_changed_at) : 'Not available');
    setText('[data-profile-last-login]', user?.last_login_at ? formatDateTime(user.last_login_at) : 'Not available');
    setText('[data-profile-created-at]', user?.created_at ? formatDateTime(user.created_at) : 'Not available');
    setText('[data-profile-device]', user?.registered_device ?? 'Not available');
    setText('[data-profile-meter]', user?.assigned_meter ?? 'Not available');
    if (accountForm && !accountForm.contains(document.activeElement)) {
        accountForm.elements.email.value = user?.email ?? '';
        accountForm.elements.phone.value = user?.phone ?? '';
    }
    accountFormInitial = {
        email: user?.email ?? '',
        phone: user?.phone ?? '',
    };
    updateAccountFormState();
};

const setAccountProfileState = (message, tone = '') => {
    if (!accountProfileState) return;
    accountProfileState.textContent = message;
    accountProfileState.classList.remove('is-loading', 'is-error', 'is-ready');
    if (tone) accountProfileState.classList.add(`is-${tone}`);
};

const loadAccountProfile = async () => {
    if (accountProfileLoading || accountProfileLoaded || !accountForm) return;

    accountProfileLoading = true;
    setAccountProfileState('Loading account details...', 'loading');
    try {
        const response = await requestJson('/api/account');
        householdProfile = response?.data ?? householdProfile;
        renderProfile(householdProfile);
        accountProfileLoaded = true;
        setAccountProfileState('Account details loaded', 'ready');
    } catch (error) {
        if (handleAuthFailure(error)) return;
        setAccountProfileState(errorMessage(error, 'Account details could not be loaded.'), 'error');
        showToast(errorMessage(error, 'Account details could not be loaded.'), 'error');
    } finally {
        accountProfileLoading = false;
    }
};

const setButtonLoading = (button, isLoading, label) => {
    if (!button) return;
    button.disabled = isLoading;
    button.classList.toggle('is-loading', isLoading);
    button.textContent = isLoading ? label : button.dataset.originalText || button.textContent;
};

const setAccountFormFeedback = (form, message = '', type = '') => {
    const feedback = form === accountForm
        ? $('[data-account-feedback]', form)
        : $('[data-password-feedback]', form);
    if (!feedback) return;
    feedback.textContent = message;
    feedback.hidden = !message;
    feedback.classList.toggle('is-error', type === 'error');
    feedback.classList.toggle('is-loading', type === 'loading');
};

const validateEmail = (value) => {
    if (!value) return 'Email is required.';
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(value) ? '' : 'Enter a valid email address.';
};

const validatePhone = (value) => {
    if (!value) return '';
    const phoneRegex = /^[0-9+()\s-]{6,50}$/;
    return phoneRegex.test(value) ? '' : 'Enter a valid phone number.';
};

const updateAccountFormState = () => {
    if (!accountForm || !accountSubmitButton) return;
    const email = accountForm.elements.email?.value.trim() ?? '';
    const phone = accountForm.elements.phone?.value.trim() ?? '';
    const hasChanged = email !== (accountFormInitial.email ?? '') || phone !== (accountFormInitial.phone ?? '');
    const emailError = validateEmail(email);
    const phoneError = validatePhone(phone);
    setFieldError('email', emailError);
    setFieldError('phone', phoneError);
    const valid = !emailError && !phoneError && hasChanged;
    accountSubmitButton.disabled = !valid;
};

const updatePasswordCriteria = (value) => {
    if (!passwordCriteria) return;
    passwordValidationState.length = value.length >= 8;
    passwordValidationState.uppercase = /[A-Z]/.test(value);
    passwordValidationState.number = /[0-9]/.test(value);
    passwordValidationState.special = /[^A-Za-z0-9]/.test(value);

    Object.entries(passwordValidationState).forEach(([key, isValid]) => {
        const item = passwordCriteria.querySelector(`[data-criteria="${key}"]`);
        if (!item) return;
        item.classList.toggle('is-valid', isValid);
        item.classList.toggle('is-invalid', !isValid);
    });
    if (passwordSubmitButton) {
        const confirmation = passwordForm?.elements.password_confirmation?.value ?? '';
        const match = confirmation.length > 0 && confirmation === (passwordForm?.elements.password?.value ?? '');
        passwordSubmitButton.disabled = !Object.values(passwordValidationState).every(Boolean) || !match;
    }
};

const updatePasswordFormState = () => {
    if (!passwordForm || !passwordSubmitButton) return;
    const password = passwordForm.elements.password?.value ?? '';
    const confirmation = passwordForm.elements.password_confirmation?.value ?? '';
    updatePasswordCriteria(password);
    const confirmationError = confirmation && password !== confirmation ? 'Passwords do not match.' : '';
    setFieldError('password_confirmation', confirmationError);
    if (passwordSubmitButton) {
        passwordSubmitButton.disabled = !Object.values(passwordValidationState).every(Boolean) || Boolean(confirmationError);
    }
};

const renderAccountActivity = (user) => {
    if (!activityContainer) return;
    const items = [];
    if (user?.created_at) {
        items.push({ label: 'Account created', date: formatDateTime(user.created_at) });
    }
    if (user?.last_login_at) {
        items.push({ label: 'Last login', date: formatDateTime(user.last_login_at) });
    }
    if (user?.password_changed_at) {
        items.push({ label: 'Password changed', date: formatDateTime(user.password_changed_at) });
    }
    if (user?.updated_at && user?.updated_at !== user?.created_at) {
        items.push({ label: 'Contact information updated', date: formatDateTime(user.updated_at) });
    }
    activityContainer.innerHTML = items.length
        ? items.map((item) => `
            <div class="timeline-entry">
                <strong>${escapeHtml(item.label)}</strong>
                <small>${escapeHtml(item.date)}</small>
            </div>
        `).join('')
        : '<div class="timeline-empty"><strong>No activity available yet.</strong><span>Account activity will appear here when available.</span></div>';
};

const setHouseholdBillingState = (message, tone = '') => {
    if (!householdBillingState) return;
    householdBillingState.textContent = message;
    householdBillingState.classList.remove('is-loading', 'is-error', 'is-ready', 'is-empty');
    if (tone) householdBillingState.classList.add(`is-${tone}`);
};

const billingRecordTypeLabel = (type) => ({
    estimated: 'Estimated',
    finalized: 'Finalized',
    unavailable: 'Unavailable',
}[type] ?? 'Unavailable');

const renderHouseholdBilling = (billing) => {
    const calculation = billing?.calculation ?? {};
    const type = billing?.record_type ?? 'unavailable';
    const hasReadings = Number(billing?.reading_count ?? 0) > 0;
    const hasRate = billing?.has_rate_configuration === true;
    const currency = calculation.currency ?? 'PHP';
    activeCurrency = currency;
    const unavailableReason = !hasReadings
        ? 'No meter readings are available for this billing period yet.'
        : !hasRate
            ? 'An active water rate has not been configured yet. Billing will appear after the administrator publishes a rate.'
            : 'This amount is based on the recorded readings and the effective billing configuration for this period.';

    setText('[data-household-billing-period]', billing?.period?.label ?? 'Current billing period');
    setText('[data-billing-current-charge]', type === 'unavailable' ? 'Unavailable' : formatCurrency(calculation.current_charge, currency));
    setText('[data-billing-consumption]', type === 'unavailable' ? 'Unavailable' : formatCubicMeterValue(calculation.total_cubic_meter));
    setText('[data-billing-reading-count]', `${formatNumber(billing?.reading_count ?? 0, 0)} reading${Number(billing?.reading_count ?? 0) === 1 ? '' : 's'} in this period`);
    setText('[data-billing-rate]', hasRate || type === 'finalized' ? formatCurrency(calculation.water_rate, currency) + ' / m³' : 'Unavailable');
    setText('[data-billing-rate-effective]', billing?.rate_effective_date ? `Effective ${billing.rate_effective_date}` : 'No active rate configured');
    setText('[data-billing-balance]', calculation.remaining_balance === null || calculation.remaining_balance === undefined ? 'Not finalized' : formatCurrency(calculation.remaining_balance, currency));
    setText('[data-billing-record-type]', `${billingRecordTypeLabel(type)} · ${billing?.period?.label ?? 'Current period'}`);
    setText(
        '[data-billing-explanation]',
        type === 'unavailable'
            ? 'This estimate will update automatically after the missing meter data or billing rate becomes available.'
            : unavailableReason,
    );

    const typeChip = $('[data-billing-type-chip]');
    if (typeChip) {
        typeChip.textContent = billingRecordTypeLabel(type);
        typeChip.classList.toggle('status-chip-soft', type === 'finalized');
        typeChip.classList.toggle('status-chip-muted', type === 'estimated' || type === 'unavailable');
        typeChip.classList.toggle('status-chip-alert', type === 'unavailable');
    }

    const calculationContainer = $('[data-billing-calculation]');
    if (calculationContainer) {
        calculationContainer.innerHTML = type === 'unavailable'
            ? `<div class="billing-empty-state"><span class="billing-empty-icon" aria-hidden="true"><i class="bi bi-calculator"></i></span><div><strong>Estimate unavailable</strong><span>${escapeHtml(unavailableReason)}</span></div></div>`
            : `<div class="billing-calculation-row"><span>Consumption</span><strong>${formatCubicMeterValue(calculation.total_cubic_meter)} <small>${formatNumber(calculation.total_liters, 2)} L</small></strong></div>
                <div class="billing-calculation-row"><span>Usage charge</span><strong>${formatCurrency(calculation.usage_charge, currency)}</strong></div>
                <div class="billing-calculation-row"><span>Fixed monthly charge</span><strong>${formatCurrency(calculation.fixed_monthly_charge, currency)}</strong></div>
                <div class="billing-calculation-row"><span>Subsidy or discount</span><strong class="billing-discount">− ${formatCurrency(calculation.subsidy_deduction, currency)}</strong></div>
                <div class="billing-calculation-total"><span>${type === 'finalized' ? 'Finalized charge' : 'Estimated current charge'}</span><strong>${formatCurrency(calculation.current_charge, currency)}</strong></div>`;
    }

    const meterContainer = $('[data-billing-meter]');
    if (meterContainer) {
        const meter = billing?.meter;
        meterContainer.innerHTML = meter
            ? `<div class="billing-meter-icon" aria-hidden="true">⌁</div><div><strong>${escapeHtml(meter.name ?? 'Assigned water meter')}</strong><small>${escapeHtml(meter.asset_number ?? meter.serial_number ?? 'Meter identity unavailable')}</small><span class="status-chip ${meter.is_online ? 'status-chip-soft' : 'status-chip-muted'}">${meter.is_online ? 'Online' : 'Offline'}</span></div>`
            : '<div class="billing-empty-state"><span class="billing-empty-icon" aria-hidden="true"><i class="bi bi-cpu"></i></span><div><strong>No meter assigned</strong><span>Contact the administrator to connect a household meter.</span></div></div>';
    }

    const latest = billing?.latest_reading;
    const latestContainer = $('[data-billing-latest-reading]');
    if (latestContainer) {
        latestContainer.innerHTML = latest
            ? `<div><dt>Recorded</dt><dd>${escapeHtml(formatDateTime(latest.recorded_at))}</dd></div><div><dt>Flow rate</dt><dd>${formatNumber(latest.flow_rate)} L/min</dd></div><div><dt>Reading volume</dt><dd>${formatNumber(latest.liters_used, 2)} L · ${formatCubicMeterValue(latest.cubic_meter)}</dd></div><div><dt>Meter connection</dt><dd>${escapeHtml(normalizeConnectionStatus(latest.device_connection_status))}</dd></div>`
            : '<div class="billing-reading-empty"><i class="bi bi-activity" aria-hidden="true"></i><div><strong>No recent meter reading</strong><span>The latest billing-period reading will appear here automatically.</span></div></div>';
    }

    const history = Array.isArray(billing?.history) ? billing.history : [];
    const historyContainer = $('[data-billing-history]');
    if (historyContainer) {
        historyContainer.innerHTML = history.length
            ? history.map((item) => `<tr><td>${escapeHtml(item.billing_month ? new Date(item.billing_month).toLocaleDateString(undefined, { month: 'long', year: 'numeric' }) : '—')}</td><td>${formatCubicMeterValue(item.total_cubic_meter)}</td><td>${formatCurrency(item.current_charge, item.currency)}</td><td>${formatCurrency(item.remaining_balance, item.currency)}</td><td><span class="status-chip ${item.status === 'paid' ? 'status-chip-soft' : 'status-chip-alert'}">${escapeHtml(String(item.status ?? 'Unknown').replaceAll('_', ' '))}</span></td></tr>`).join('')
            : '<tr><td colspan="5"><div class="empty-state">No finalized billing statements are available yet.</div></td></tr>';
    }
};

const loadHouseholdBilling = async ({ force = false } = {}) => {
    if (!householdBillingState || householdBillingLoading || (householdBillingLoaded && !force)) return;
    householdBillingLoading = true;
    setHouseholdBillingState('Loading billing details...', 'loading');
    try {
        const response = await requestJson('/api/household-billing');
        const billing = response?.data ?? {};
        renderHouseholdBilling(billing);
        householdBillingLoaded = true;
        setHouseholdBillingState(
            billing.record_type === 'unavailable' ? 'Billing needs attention' : `${billingRecordTypeLabel(billing.record_type)} ready`,
            billing.record_type === 'unavailable' ? 'empty' : 'ready',
        );
    } catch (error) {
        if (handleAuthFailure(error)) return;
        const message = errorMessage(error, 'Billing details could not be loaded.');
        setHouseholdBillingState(message, 'error');
        const calculationContainer = $('[data-billing-calculation]');
        if (calculationContainer) calculationContainer.innerHTML = `<div class="empty-state is-error"><strong>Billing details unavailable</strong><span>${escapeHtml(message)}</span><button class="button button-ghost" type="button" data-billing-retry>Retry</button></div>`;
        $('[data-billing-retry]')?.addEventListener('click', () => loadHouseholdBilling({ force: true }));
    } finally {
        householdBillingLoading = false;
    }
};

const renderLegacyAdminAlerts = (items) => {
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
            <h4>${formatPersonalDisplay(leak.user.name)} · ${leak.user.account_number ?? leak.user.email}</h4>
                    <span class="status-chip ${leak.status === 'resolved' ? 'status-chip-soft' : 'status-chip-alert'}">${leak.status === 'resolved' ? 'Resolved' : 'Unresolved'}</span>
                </div>
                <p>${formatPersonalDisplay(leak.user.address, 'NO HOUSEHOLD ADDRESS RECORDED')} · Flow: ${formatNumber(leak.flow_rate)} L/min</p>
                <small>Last reading: ${formatDateTime(leak.last_reading_at)} · Last notice: ${formatDateTime(leak.last_notice_at)}</small>
            </article>
        `)
        .join('');

    containers.forEach((container) => {
        container.innerHTML = markup;
    });
};

const renderAdminAlerts = (items) => {
    const containers = $$('[data-admin-alerts]');
    if (!containers.length) return;

    const alerts = Array.isArray(items) ? items : [];
    if (!alerts.length) {
        containers.forEach((container) => {
            container.innerHTML = '<div class="empty-state">No possible leak alerts are currently recorded.</div>';
        });
        return;
    }

    const statusLabels = {
        detected: 'Detected',
        acknowledged: 'Acknowledged',
        investigating: 'Investigating',
        resolved: 'Resolved',
        false_alarm: 'False alarm',
    };
    const markup = alerts.map((alert) => {
        const status = alert.status ?? 'detected';
        const statusClass = ['resolved', 'false_alarm'].includes(status) ? 'status-chip-soft' : 'status-chip-alert';
        const user = alert.user ?? {};
        const latest = alert.latest_reading ?? {};
        const first = alert.first_reading ?? {};
        const device = alert.device ?? {};
        const notification = alert.notification ?? {};
        const statusOptions = Object.entries(statusLabels)
            .map(([value, label]) => '<option value="' + value + '"' + (value === status ? ' selected' : '') + '>' + label + '</option>')
            .join('');

        return '<article class="notification-card leak-alert-card" data-leak-alert-id="' + escapeHtml(alert.id) + '">'
            + '<div class="notification-head"><div>'
            + '<h4>' + escapeHtml(formatPersonalDisplay(user.name)) + ' · ' + escapeHtml(user.account_number ?? user.email ?? 'HOUSEHOLD') + '</h4>'
            + '<p>' + escapeHtml(user.email ?? 'No email recorded') + ' · ' + escapeHtml(formatPersonalDisplay(user.address, 'NO HOUSEHOLD ADDRESS RECORDED')) + '</p>'
            + '</div><span class="status-chip ' + statusClass + '">' + escapeHtml(statusLabels[status] ?? status) + '</span></div>'
            + '<div class="summary-list leak-alert-summary">'
            + '<div><dt>Meter</dt><dd>' + escapeHtml(device.name ?? latest.device_name ?? 'Registered meter') + ' · ' + escapeHtml(device.asset_number ?? 'Asset not recorded') + '</dd></div>'
            + '<div><dt>Current flow</dt><dd>' + escapeHtml(formatNumber(latest.flow_rate)) + ' L/min</dd></div>'
            + '<div><dt>Leak started</dt><dd>' + escapeHtml(formatDateTime(alert.detected_at ?? first.recorded_at)) + '</dd></div>'
            + '<div><dt>Last detected</dt><dd>' + escapeHtml(formatDateTime(alert.last_detected_at ?? latest.recorded_at)) + '</dd></div>'
            + '<div><dt>Latest reading</dt><dd>' + escapeHtml(formatDateTime(latest.recorded_at)) + ' · ' + escapeHtml(latest.leak_detection_status ?? 'Possible leak') + '</dd></div>'
            + '<div><dt>Household notified</dt><dd>' + escapeHtml(formatDateTime(alert.notified_at ?? notification.sent_at)) + '</dd></div>'
            + '</div>'
            + '<small>Device status: ' + escapeHtml(device.status ?? 'Unknown') + ' · Connection: ' + escapeHtml(latest.device_connection_status ?? 'Not reported') + ' · Server: ' + escapeHtml(latest.server_connection_status ?? 'Not reported') + '</small>'
            + '<form class="stack-form leak-alert-update-form" data-leak-alert-update-form>'
            + '<label>Status<select name="status" required>' + statusOptions + '</select></label>'
            + '<label>Administrative notes<textarea name="notes" maxlength="2000" placeholder="Record investigation or resolution notes">' + escapeHtml(alert.notes ?? '') + '</textarea></label>'
            + '<div class="button-row"><button class="button button-secondary" type="submit">Save alert status</button></div>'
            + '</form>'
            + '<form class="stack-form leak-alert-notify-form" data-leak-alert-notify-form>'
            + '<label>Follow-up title<input type="text" name="title" maxlength="255" value="Follow-up: possible water leak" required></label>'
            + '<label>Follow-up message<textarea name="message" maxlength="2000" required>Smart Water is following up on the possible leak detected by your meter. Please check your household plumbing and contact the municipal water office if the flow continues.</textarea></label>'
            + '<button class="button button-primary" type="submit">Notify household</button>'
            + '</form></article>';
    }).join('');

    containers.forEach((container) => {
        container.innerHTML = markup;
    });
};

let adminLeakRefreshTimer = null;
let adminLeakRefreshInFlight = false;

const stopAdminLeakPolling = () => {
    if (adminLeakRefreshTimer) {
        window.clearTimeout(adminLeakRefreshTimer);
        adminLeakRefreshTimer = null;
    }
};

const scheduleAdminLeakPolling = () => {
    stopAdminLeakPolling();
    if (page !== 'admin' || !readToken()) return;
    adminLeakRefreshTimer = window.setTimeout(() => loadAdminLeakAlerts(), 10000);
};

const loadAdminLeakAlerts = async () => {
    const container = $('[data-admin-alerts]');
    if (!container || adminLeakRefreshInFlight || !readToken()) return [];

    adminLeakRefreshInFlight = true;
    try {
        const response = await requestJson('/api/leak-alerts?per_page=100');
        const alerts = responseItems(response);
        renderAdminAlerts(alerts);
        return alerts;
    } catch (error) {
        if (!handleAuthFailure(error)) {
            container.innerHTML = '<div class="empty-state is-error">Unable to load leak alerts. Try again.</div>';
            console.error(error);
        }
        return [];
    } finally {
        adminLeakRefreshInFlight = false;
        scheduleAdminLeakPolling();
    }
};

const bindAdminLeakActions = (container) => {
    if (!container || container.dataset.bound === '1') return;
    container.dataset.bound = '1';

    container.addEventListener('submit', async (event) => {
        const updateForm = event.target.closest('[data-leak-alert-update-form]');
        const notifyForm = event.target.closest('[data-leak-alert-notify-form]');
        if (!updateForm && !notifyForm) return;

        event.preventDefault();
        const card = event.target.closest('[data-leak-alert-id]');
        const alertId = card?.dataset.leakAlertId;
        if (!alertId) return;

        const form = updateForm ?? notifyForm;
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.dataset.defaultText = submitButton.dataset.defaultText || submitButton.textContent;
            submitButton.textContent = 'Saving...';
        }

        try {
            const payload = Object.fromEntries(new FormData(form).entries());
            const endpoint = updateForm ? '/api/leak-alerts/' + alertId : '/api/leak-alerts/' + alertId + '/notify';
            const response = await requestJson(endpoint, {
                method: updateForm ? 'PATCH' : 'POST',
                body: JSON.stringify(payload),
            });
            showToast(response?.message ?? 'Leak alert updated successfully.');
            await loadAdminLeakAlerts();
        } catch (error) {
            if (!handleAuthFailure(error)) showToast(errorMessage(error, 'Could not update the leak alert.'), 'error');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = submitButton.dataset.defaultText || 'Save';
            }
        }
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

const renderAdminNotificationHistory = (response) => {
    const container = $('[data-admin-notification-history]');
    const records = responseItems(response);
    const paginator = responsePaginator(response);
    setText('[data-admin-notification-summary]', paginator?.total ? `${paginator.total} notification batch${paginator.total === 1 ? '' : 'es'}` : 'No notification batches');
    if (!container) return;

    if (!records.length) {
        container.innerHTML = '<div class="empty-state">No sent notification records match the current filters.</div>';
    } else {
        container.innerHTML = records.map((item) => {
            const status = item.delivery_status ?? 'delivered';
            const statusLabel = status === 'partial' ? 'Partially delivered' : status.charAt(0).toUpperCase() + status.slice(1);
            const audience = item.audience === 'selected_household' ? 'Selected household' : 'All active households';
            const actionMarkup = item.failed_count > 0
                ? `<button type="button" class="link-button" data-retry-sent-notification="${escapeHtml(item.id)}">Retry failed</button>`
                : '';

            return `<article class="notification-card admin-notification-history-item">
                <div class="notification-head"><div><h4>${escapeHtml(item.title)}</h4><p class="notification-tag">${escapeHtml(notificationTypeLabel(item.type))}</p></div><span class="status-chip ${status === 'delivered' ? 'status-chip-soft' : 'status-chip-alert'}">${escapeHtml(statusLabel)}</span></div>
                <p>${escapeHtml(item.message)}</p>
                <div class="summary-list admin-notification-stats">
                    <div><dt>Audience</dt><dd>${escapeHtml(audience)}</dd></div>
                    <div><dt>Recipients</dt><dd>${formatNumber(item.recipient_count, 0)}</dd></div>
                    <div><dt>Delivered</dt><dd>${formatNumber(item.delivered_count, 0)}</dd></div>
                    <div><dt>Read</dt><dd>${formatNumber(item.read_count, 0)} / ${formatNumber(item.recipient_count, 0)}</dd></div>
                    <div><dt>Sent / scheduled</dt><dd>${escapeHtml(formatDateTime(item.sent_at ?? item.scheduled_at))}</dd></div>
                </div>
                <small>${status === 'scheduled' ? 'Scheduled delivery' : 'Created'} ${escapeHtml(formatDateTime(item.created_at))}</small>
                <div class="table-actions admin-notification-actions">${actionMarkup}<button type="button" class="link-button" data-archive-sent-notification="${escapeHtml(item.id)}">Archive</button><button type="button" class="link-button rose" data-delete-sent-notification="${escapeHtml(item.id)}" data-notification-title="${escapeHtml(item.title)}">Delete</button></div>
            </article>`;
        }).join('');
    }

    renderPager($('[data-admin-notification-pager]'), paginator, (page) => {
        adminNotificationState.page = page;
        loadAdminNotificationHistory();
    });
};

const loadAdminNotificationHistory = async () => {
    const container = $('[data-admin-notification-history]');
    try {
        const response = await requestJson(`/api/notification-management${buildQuery(adminNotificationState)}`);
        renderAdminNotificationHistory(response);
        return response;
    } catch (error) {
        if (handleAuthFailure(error)) return null;
        if (container) container.innerHTML = `<div class="empty-state is-error">${escapeHtml(errorMessage(error, 'Unable to load notification history.'))}</div>`;
        return null;
    }
};

const bindAdminNotificationFilters = () => {
    if (!adminNotificationFilterForm || adminNotificationFilterForm.dataset.bound === '1') return;
    adminNotificationFilterForm.dataset.bound = '1';
    adminNotificationFilterForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const fields = serializeForm(adminNotificationFilterForm);
        adminNotificationState.page = 1;
        adminNotificationState.search = fields.search ?? '';
        adminNotificationState.type = fields.type ?? '';
        adminNotificationState.status = fields.status ?? '';
        loadAdminNotificationHistory();
    });
};

const normalizeConnectionStatus = (value, unavailable = 'Unavailable') => {
    const normalized = String(value ?? '').trim().toLowerCase();
    if (!normalized) return unavailable;
    if (['connected', 'online', 'active', 'ok', 'normal'].includes(normalized)) return 'Connected';
    return 'Disconnected';
};

const normalizeMonitoringStatus = (value, unavailable = 'Unavailable') => {
    const normalized = String(value ?? '').trim().toLowerCase();
    if (!normalized) return unavailable;
    if (['connected', 'online', 'active', 'monitoring', 'ok'].includes(normalized)) return 'Active';
    return 'Inactive';
};

const applyStatusTone = (selector, value) => {
    $$(selector).forEach((node) => {
        node.classList.remove('is-good', 'is-warning', 'is-unavailable', 'is-error');
        const normalized = String(value ?? '').toLowerCase();
        node.classList.add(normalized.includes('unavailable') ? 'is-unavailable' : normalized.includes('disconnected') || normalized.includes('critical') || normalized.includes('leak') ? 'is-error' : normalized.includes('inactive') || normalized.includes('warning') ? 'is-warning' : 'is-good');
    });
};

const applySummary = (summary) => {
    const current = summary?.current_month ?? {};
    const notifications = summary?.notifications ?? {};
    const billingStatement = summary?.billing_statement ?? null;
    const activeBilling = summary?.active_billing ?? null;
    activeCurrency = activeBilling?.currency ?? 'PHP';

    const summaryData = {
        ...current,
        reading_count: current.reading_count ?? 0,
        total_liters: current.total_liters ?? 0,
        estimated_bill: current.estimated_bill ?? 0,
        monthly_usage: summary?.monthly_usage ?? [],
    };
    const latestReading = current.latest_reading ?? null;

    setText('[data-water-rate]', activeBilling ? formatCurrency(activeBilling.water_rate) : '--');
    setText('[data-balance]', billingStatement ? formatCurrency(billingStatement.remaining_balance) : '--');
    setText('[data-threshold]', activeBilling ? `${formatNumber(activeBilling.threshold_liters ?? 0)} L` : '--');
    setText('[data-leak-threshold]', activeBilling ? `${formatNumber(activeBilling.leak_flow_rate_threshold ?? 0)} L/min` : '--');
    setText('[data-billing-month]', billingStatement?.billing_month
        ? new Date(billingStatement.billing_month).toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
        : '--');
    setText('[data-leak-details]', !latestReading ? 'No recent device reading available.' : Boolean(latestReading?.is_possible_leak) ? 'Possible leak detected from the latest reading.' : 'No leak has been detected yet.');

    renderHouseholdSummaryCards(summaryData, latestReading, notifications);
};

const setHouseholdAnalyticsValue = (selector, value, isEmpty = false) => {
    $$(selector).forEach((node) => {
        node.textContent = value;
        node.classList.toggle('is-empty', isEmpty);
    });
};

const applyHouseholdAnalytics = (analytics) => {
    householdAnalytics = analytics ?? null;
    const period = analytics?.period ?? {};
    const current = analytics?.current_period ?? {};
    const previous = analytics?.previous_period ?? {};
    const comparison = analytics?.comparison ?? {};
    const highest = analytics?.highest_usage_period ?? null;
    const latestWeek = Array.isArray(analytics?.weekly_consumption) ? analytics.weekly_consumption.at(-1) : null;
    const latestMonth = Array.isArray(analytics?.monthly_consumption) ? analytics.monthly_consumption.at(-1) : null;

    setText('[data-household-analytics-timezone]', period.timezone ?? analytics?.timezone ?? 'UTC');
    const hasPeriod = Boolean(period.from && period.to);
    const hasDaily = analytics?.daily_consumption !== null && analytics?.daily_consumption !== undefined;
    const hasBill = current.estimated_bill !== null && current.estimated_bill !== undefined;
    const hasPrevious = previous.total_liters !== null && previous.total_liters !== undefined;
    const hasComparison = comparison.change_percent !== null && comparison.change_percent !== undefined;
    const hasLeakHistory = Array.isArray(analytics?.leak_warning_history);
    const hasLastReading = Boolean(analytics?.last_successful_meter_reading?.recorded_at);

    setHouseholdAnalyticsValue('[data-household-analytics-period]', hasPeriod
        ? `${formatCompactDateTime(period.from)} – ${formatCompactDateTime(period.to)}`
        : 'Choose a date range to begin', !hasPeriod);
    setHouseholdAnalyticsValue('[data-household-daily-consumption]', hasDaily
        ? formatCubicMeters(analytics.daily_consumption) : 'Awaiting readings', !hasDaily);
    setHouseholdAnalyticsValue('[data-household-weekly-consumption]', latestWeek
        ? formatCubicMeters(latestWeek.total_liters) : 'Awaiting readings', !latestWeek);
    setHouseholdAnalyticsValue('[data-household-monthly-consumption]', latestMonth
        ? formatCubicMeters(latestMonth.total_liters) : 'Awaiting readings', !latestMonth);
    setHouseholdAnalyticsValue('[data-household-analytics-bill]', hasBill
        ? formatCurrency(current.estimated_bill) : 'Not calculated yet', !hasBill);
    setHouseholdAnalyticsValue('[data-household-previous-period]', hasPrevious
        ? formatCubicMeters(previous.total_liters) : 'Awaiting comparison', !hasPrevious);
    setHouseholdAnalyticsValue('[data-household-period-comparison]', hasComparison
        ? `${comparison.change_percent > 0 ? '+' : ''}${formatNumber(comparison.change_percent)}% (${comparison.direction})`
        : 'Awaiting comparison', !hasComparison);
    setHouseholdAnalyticsValue('[data-household-highest-usage]', highest
        ? `${formatCompactDateTime(highest.period)} · ${formatCubicMeters(highest.total_liters)}`
        : 'No usage recorded', !highest);
    setHouseholdAnalyticsValue('[data-household-leak-warning-count]', hasLeakHistory
        ? analytics.leak_warning_history.length : 'Not available', !hasLeakHistory);
    setHouseholdAnalyticsValue('[data-household-last-reading]', hasLastReading
        ? formatDateTime(analytics.last_successful_meter_reading.recorded_at)
        : 'No reading recorded', !hasLastReading);
};

const setDashboardFeedback = (message = '', type = 'info') => {
    const feedback = $('[data-dashboard-feedback]');
    if (!feedback) return;

    feedback.hidden = !message;
    feedback.textContent = message;
    feedback.classList.toggle('is-error', type === 'error');
    feedback.classList.toggle('is-loading', type === 'loading');
};

const renderHouseholdSummaryCards = (summaryData, latestReading, notifications) => {
    const readingCount = Number(summaryData?.reading_count ?? 0);
    const hasReadings = readingCount > 0;
    const monthlyUsage = Array.isArray(summaryData?.monthly_usage) ? summaryData.monthly_usage : [];
    const hasConsumptionHistory = monthlyUsage.some((row) => Number(row.reading_count ?? 0) > 0 || Number(row.total_cubic_meter ?? row.consumption ?? 0) > 0);
    const usageContent = $('[data-household-usage-content]');
    usageContent?.classList.remove('is-loading');
    usageContent?.classList.toggle('is-empty', !hasConsumptionHistory);
    const totalConsumed = hasReadings ? formatCubicMeters(summaryData.total_liters) : 'No data yet';
    const estimatedBill = hasReadings ? formatCurrency(summaryData.estimated_bill) : 'No data yet';
    const leakDetected = Boolean(latestReading?.is_possible_leak);
    const leakStatus = !latestReading ? 'Unavailable' : leakDetected ? 'Leak detected' : 'Normal';
    const meterStatus = normalizeConnectionStatus(latestReading?.device_connection_status);
    const serverStatus = normalizeConnectionStatus(latestReading?.server_connection_status);
    const monitoringStatus = normalizeMonitoringStatus(latestReading?.monitoring_status);
    const wifiStatus = latestReading?.wifi_signal_strength === null || latestReading?.wifi_signal_strength === undefined
        ? 'Unavailable'
        : Number(latestReading.wifi_signal_strength) > 0 ? 'Connected' : 'Disconnected';
    const overallConnection = meterStatus === 'Unavailable' || serverStatus === 'Unavailable'
        ? 'Unavailable'
        : meterStatus === 'Connected' && serverStatus === 'Connected' ? 'Connected' : 'Disconnected';
    const lastUpdated = latestReading?.recorded_at ? formatDateTime(latestReading.recorded_at) : 'Waiting for data';
    const quickSummary = hasReadings ? `${readingCount} readings · ${totalConsumed}` : 'No readings yet';

    setText('[data-stat-readings]', readingCount);
    setText('[data-stat-liters]', totalConsumed);
    setText('[data-stat-bill]', estimatedBill);
    setText('[data-stat-alerts]', Number(notifications.unread ?? 0));
    setHouseholdAlertCount(notifications.unread ?? 0);
    setText('[data-today-total]', totalConsumed);
    setText('[data-total-consumed]', totalConsumed);
    setText('[data-estimated-bill]', estimatedBill);
    setText('[data-quick-summary]', quickSummary);
    setText('[data-current-flow]', latestReading ? `${formatNumber(latestReading.flow_rate)} L/min` : 'No reading');
    setText('[data-leak-summary]', leakStatus);
    setText('[data-connection-summary]', overallConnection);
    setText('[data-connection-state]', overallConnection === 'Connected' ? 'Meter and server are connected.' : overallConnection === 'Disconnected' ? 'Check the meter or server connection.' : 'No recent device reading available.');
    setText('[data-flow-state]', latestReading ? Number(latestReading.flow_rate) === 0 ? 'Currently no water flow detected.' : 'Latest reading from the connected meter.' : 'No reading received yet.');
    setText('[data-consumption-state]', hasReadings ? `${readingCount} reading${readingCount === 1 ? '' : 's'} recorded this month.` : 'No consumption readings are available yet.');
    setText('[data-billing-state]', hasReadings ? 'Calculated from this month’s readings.' : 'No billable readings this month.');
    setText('[data-leak-state]', !latestReading ? 'No recent device reading available.' : leakDetected ? 'Possible leak detected from the latest reading.' : 'No leak detected in the latest reading.');
    setText('[data-notification-state]', Number(notifications.unread ?? 0) > 0 ? 'Unread alerts need your attention.' : 'You have no unread notifications.');
    setText('[data-system-water-meter]', meterStatus);
    setText('[data-system-monitoring]', monitoringStatus);
    setText('[data-system-wifi]', wifiStatus);
    setText('[data-system-server]', serverStatus);
    setText('[data-system-leak]', leakStatus === 'Normal' ? 'Normal' : leakStatus);
    setText('[data-last-updated-label]', `Last updated: ${lastUpdated}`);
    setText('[data-last-updated]', lastUpdated);

    applyStatusTone('[data-leak-summary], [data-system-leak]', leakStatus);
    applyStatusTone('[data-system-water-meter], [data-system-server], [data-connection-summary]', overallConnection);
    applyStatusTone('[data-system-monitoring]', monitoringStatus);
    applyStatusTone('[data-system-wifi]', wifiStatus);
    $$('[data-leak-summary]').forEach((node) => {
        node.classList.toggle('mint', leakStatus === 'Normal');
        node.classList.toggle('rose', leakDetected);
    });

    renderNotifications(notifications.latest ?? [], '[data-dashboard-notifications-list]');
    $$('[data-household-monthly-chart]').forEach((chart) => renderMonthlyChart(chart, monthlyUsage, {
        dataAvailable: hasConsumptionHistory,
        emptyMessage: '<div class="chart-empty-state"><strong>No consumption history yet</strong><span>Usage data will appear here after the connected water meter submits its first reading.</span></div>',
    }));
    setText('[data-live-label]', latestReading ? 'Live' : 'Offline');
};

const loadDashboard = async (options = {}) => {
    const { reschedule = true, manual = false } = options;

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
    if (manual && refreshButton) {
        refreshButton.disabled = true;
        refreshButton.classList.add('is-loading');
        refreshButton.setAttribute('aria-busy', 'true');
        refreshButton.setAttribute('aria-label', 'Refreshing dashboard data');
    }

    try {
        const me = householdProfile ? { data: householdProfile } : await requestJson('/api/me');
        householdProfile = me.data;
        renderProfile(me.data);
        renderAccountActivity(me.data);
        void loadAccountProfile();
        void loadHouseholdBilling();
        if (redirectAwayFromWrongRole(me.data, 'household')) {
            stopDashboardPolling();
            return;
        }

        // Notifications are an independent authenticated resource. Refresh
        // them directly so a missing/offline meter cannot hide admin notices.
        void loadNotifications();

        const readingsQuery = buildQuery({
            page: dashboardState.page,
            per_page: dashboardState.per_page,
            from: dashboardState.from,
            to: dashboardState.to,
        });

        const summaryUrl = `/api/summary?user_id=${me.data.id}`;

        const [summaryResponse, readingsResponse] = await Promise.all([
            requestJson(summaryUrl),
            requestJson(`/api/readings${readingsQuery}`),
        ]);

        const readings = responseItems(readingsResponse);

        applySummary(summaryResponse.data);
        const householdAnalyticsKey = `${householdAnalyticsRange?.value || 'last_30_days'}:${householdAnalyticsFrom?.value || ''}:${householdAnalyticsTo?.value || ''}`;
        if (householdAnalyticsLoadedRange !== householdAnalyticsKey
            && (householdAnalyticsRange?.value !== 'custom' || (householdAnalyticsFrom?.value && householdAnalyticsTo?.value))) {
            try {
                const analyticsResponse = await requestJson(`/api/analytics/household${analyticsQuery(householdAnalyticsRange, householdAnalyticsFrom, householdAnalyticsTo, 'last_30_days')}`);
                householdAnalyticsLoadedRange = householdAnalyticsKey;
                applyHouseholdAnalytics(analyticsResponse.data);
            } catch (analyticsError) {
                console.error(analyticsError);
                applyHouseholdAnalytics(null);
            }
        }
        renderReadings(readings);
        readingsPagers.forEach((readingsPager) => renderPager(readingsPager, responsePaginator(readingsResponse), (page) => {
            dashboardState.page = page;
            loadDashboard();
        }));
        setDashboardFeedback('');
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
        setDashboardFeedback('Unable to retrieve dashboard data. Try again.', 'error');
        if (!accountProfileLoaded) {
            setAccountProfileState('Account details are temporarily unavailable. Try again.', 'error');
            setText('[data-profile-name]', 'Account unavailable');
            setText('[data-profile-email]', 'Unavailable');
            setText('[data-profile-account]', 'Unavailable');
            setText('[data-profile-address]', 'Unavailable');
            setText('[data-profile-phone]', 'Unavailable');
            setText('[data-profile-status]', 'Unavailable');
        }
        const usageContent = $('[data-household-usage-content]');
        usageContent?.classList.remove('is-loading');
        usageContent?.classList.add('is-empty');
        $$('[data-household-monthly-chart]').forEach((chart) => renderHouseholdAreaChart(chart, [], {
            dataAvailable: false,
            emptyMessage: '<div class="chart-empty-state is-error"><strong>Usage data is temporarily unavailable</strong><span>Reconnect to load the household consumption trend.</span></div>',
        }));
        $('[data-live-label]') && ($('[data-live-label]').textContent = 'Offline');
        if (reschedule) {
            scheduleDashboardPolling();
        }
    } finally {
        dashboardRefreshInFlight = false;
        if (manual && refreshButton) {
            refreshButton.disabled = false;
            refreshButton.classList.remove('is-loading');
            refreshButton.removeAttribute('aria-busy');
            refreshButton.setAttribute('aria-label', 'Refresh dashboard data');
        }
    }
};

const renderAdminUsers = (usersResponse, { householdsOnly = false } = {}) => {
    const usersContainers = $$(householdsOnly ? '[data-admin-household-users]' : '[data-admin-users]');
    const users = responseItems(usersResponse);
    if (householdsOnly) {
        householdUsersById = new Map(users.map((user) => [String(user.id), user]));
    }
    const usersMarkup = users.length
        ? users
            .map(
                (user) => {
                    const devices = Array.isArray(user.devices) ? user.devices : [];
                    const device = devices[0];
                    const meterHealth = device
                        ? (device.is_online ? 'Online' : device.status === 'pending' ? 'Pending activation' : 'Offline')
                        : 'No meter assigned';
                    const deviceMarkup = householdsOnly
                        ? device
                            ? `<span class="table-primary">${escapeHtml(formatPersonalDisplay(device.name))}</span><small class="table-secondary">${escapeHtml(device.asset_number ?? 'Meter')}</small><small class="table-secondary">${escapeHtml(meterHealth)}</small>`
                            : '<span class="table-secondary">No meter assigned</span>'
                        : '';
                    const accountStatus = String(user.account_status ?? (user.is_active ? 'active' : 'inactive'));
                    const statusLabel = accountStatus === 'active' && user.is_active
                        ? 'Active'
                        : accountStatus === 'archived'
                            ? 'Archived'
                            : accountStatus === 'suspended'
                                ? 'Suspended'
                                : 'Inactive';
                    const statusMarkup = statusLabel === 'Active'
                        ? '<span class="status-pill status-pill-soft">Active</span>'
                        : `<span class="status-pill status-pill-inactive">${statusLabel}</span>`;
                    return `
                <div class="table-row admin-table-row">
                    <span>
                        <strong class="table-primary">${escapeHtml(formatPersonalDisplay(user.name))}</strong>
                        ${householdsOnly ? `<small class="table-secondary">${escapeHtml(user.account_number ?? 'No account number')}</small>` : ''}
                    </span>
                    <span>
                        <span class="table-primary">${escapeHtml(user.email)}</span>
                        ${householdsOnly && user.phone ? `<small class="table-secondary">${escapeHtml(user.phone)}</small>` : ''}
                        ${householdsOnly && user.address ? `<small class="table-secondary">${escapeHtml(user.address)}</small>` : ''}
                    </span>
                    ${householdsOnly ? `<span>${deviceMarkup}</span>` : `<span>${escapeHtml(user.role)}</span>`}
                    <span>${statusMarkup}</span>
                    <span class="table-actions">
                        ${householdsOnly ? `<button type="button" class="link-button" data-edit-user="${user.id}">Manage</button>` : ''}
                        <button type="button" class="link-button rose" data-delete-user="${user.id}" data-user-name="${escapeHtml(formatPersonalDisplay(user.name))}">Delete</button>
                    </span>
                </div>
            `;
                },
            )
            .join('')
        : `<div class="empty-state table-empty-state"><strong>${householdsOnly ? 'No household accounts found' : 'No users match the current filters.'}</strong><span>${householdsOnly ? 'Create a household user account or adjust the filters to see matching customers.' : 'Try a different search or filter.'}</span></div>`;

    usersContainers.forEach((usersContainer) => {
        usersContainer.innerHTML = usersMarkup;
        bindAdminUserDeleteActions(usersContainer);
    });

    $$(householdsOnly ? '[data-admin-household-users-pager]' : '[data-admin-users-pager]').forEach((pager) => renderPager(pager, responsePaginator(usersResponse), (page) => {
        const state = householdsOnly ? householdUserState : adminState;
        state.page = page;
        loadAdminUsers({ householdsOnly });
    }));

    if (householdsOnly) {
        setText('[data-household-user-summary]', `${responsePaginator(usersResponse)?.total ?? users.length} household accounts`);
    } else {
        setText('[data-admin-summary]', `${responsePaginator(usersResponse)?.total ?? users.length} records`);
    }
    return users;
};

const loadAdminUsers = async ({ householdsOnly = false } = {}) => {
    const state = householdsOnly ? householdUserState : adminState;
    const usersQuery = buildQuery({
        page: state.page,
        per_page: state.per_page,
        search: state.search,
        ...(householdsOnly ? {} : { role: adminState.role }),
        ...(householdsOnly ? { account_status: state.account_status } : { is_active: state.is_active }),
    });
    const endpoint = householdsOnly ? '/api/household-users' : '/api/users';
    const response = await requestJson(`${endpoint}${usersQuery}`);
    renderAdminUsers(response, { householdsOnly });
    return response;
};

const consumptionConditionLabel = (condition) => ({
    leak: 'Leak alert',
    high: 'High usage',
    threshold: 'Threshold exceeded',
    normal: 'Normal',
    no_data: 'No readings',
}[condition] ?? 'No readings');

const consumptionConditionClass = (condition) => ({
    leak: 'badge badge-leak',
    high: 'badge badge-threshold',
    threshold: 'badge badge-threshold',
    normal: 'badge badge-normal',
    no_data: 'badge',
}[condition] ?? 'badge');

const renderConsumptionMonitoring = (response) => {
    const container = $('[data-consumption-records]');
    const items = responseItems(response);
    const summary = $('[data-consumption-monitoring-summary]');
    const meta = response?.meta ?? {};

    if (summary) {
        summary.innerHTML = `
            <div><dt>Households</dt><dd>${formatNumber(meta.household_count ?? 0, 0)}</dd></div>
            <div><dt>Usage</dt><dd>${formatNumber(meta.total_liters ?? 0, 3)} L</dd></div>
            <div><dt>Estimated billing</dt><dd>${formatCurrency(meta.estimated_bill ?? 0)}</dd></div>
            <div><dt>Abnormal households</dt><dd>${formatNumber(meta.abnormal_count ?? 0, 0)}</dd></div>
        `;
    }

    setText('[data-consumption-filter-status]', meta.period
        ? `Showing ${formatDateTime(meta.period.from)} to ${formatDateTime(meta.period.to)}. Search and filters are applied on the server.`
        : 'Search and filters are applied on the server.');

    if (!container) return;
    if (!items.length) {
        container.innerHTML = '<div class="empty-state table-empty-state"><strong>No household consumption records found</strong><span>Try another search, date range, or filter.</span></div>';
    } else {
        container.innerHTML = items.map((item) => {
            const household = item.household ?? {};
            const meter = item.device;
            const usage = item.summary ?? {};
            const latest = item.latest_reading;
            const condition = usage.condition ?? 'no_data';
            const meterLabel = meter
                ? `<span class="table-primary">${escapeHtml(formatPersonalDisplay(meter.name))}</span><small class="table-secondary">${escapeHtml(meter.asset_number ?? meter.serial_number ?? 'Meter')}</small><small class="table-secondary">${escapeHtml(meter.status ?? 'Unknown')}${meter.is_online ? ' · Online' : ' · Offline'}</small>`
                : '<span class="table-secondary">No meter assigned</span>';
            const latestLabel = latest
                ? `<span class="table-primary">${escapeHtml(formatDateTime(latest.recorded_at))}</span><small class="table-secondary">${formatNumber(latest.flow_rate ?? 0)} L/min · ${formatNumber(latest.liters_used, 3)} L</small>`
                : '<span class="table-secondary">No reading in period</span>';

            return `<div class="table-row admin-table-row consumption-monitoring-row">
                <span><strong class="table-primary">${escapeHtml(formatPersonalDisplay(household.name))}</strong><small class="table-secondary">${escapeHtml(household.account_number ?? household.email ?? 'No account number')}</small><small class="table-secondary">${escapeHtml(household.address ?? 'No address')}</small></span>
                <span>${meterLabel}</span>
                <span><strong class="table-primary">${formatNumber(usage.total_liters, 3)} L</strong><small class="table-secondary">${formatCubicMeterValue(usage.total_cubic_meter)} · ${formatNumber(usage.reading_count, 0)} readings</small></span>
                <span>${formatCurrency(usage.estimated_bill)}</span>
                <span>${latestLabel}</span>
                <span><span class="${consumptionConditionClass(condition)}">${consumptionConditionLabel(condition)}</span></span>
                <span><button type="button" class="link-button" data-consumption-history-id="${household.id}">View history</button></span>
            </div>`;
        }).join('');
    }

    renderPager($('[data-consumption-pager]'), responsePaginator(response), (page) => {
        consumptionState.page = page;
        loadAdminConsumption();
    });
};

const renderAdminConsumptionHistory = (data) => {
    const container = $('[data-consumption-history]');
    const household = data?.household ?? {};
    const summary = data?.summary ?? {};
    const latest = data?.latest_reading;
    const period = data?.period ?? {};
    const rows = (items) => (Array.isArray(items) && items.length)
        ? items.map((item) => `<div class="table-row"><span>${escapeHtml(item.period)}</span><span>${formatNumber(item.total_liters, 3)} L</span><span>${formatCubicMeterValue(item.total_cubic_meter)}</span><span>${formatCurrency(item.estimated_bill)}</span></div>`).join('')
        : '<div class="empty-state table-empty-state">No readings in this period.</div>';

    if ($('[data-consumption-dialog-title]')) {
        $('[data-consumption-dialog-title]').textContent = formatPersonalDisplay(household.name, 'Household history');
    }
    if (!container) return;
    container.innerHTML = `
        <div class="summary-list consumption-history-summary">
            <div><dt>Period</dt><dd>${escapeHtml(formatDateTime(period.from))} – ${escapeHtml(formatDateTime(period.to))}</dd></div>
            <div><dt>Total usage</dt><dd>${formatNumber(summary.total_liters, 3)} L</dd></div>
            <div><dt>Estimated billing</dt><dd>${formatCurrency(summary.estimated_bill)}</dd></div>
            <div><dt>Active rate</dt><dd>${data.active_billing ? formatCurrency(data.active_billing.water_rate) + ' / m³' : 'Not configured'}</dd></div>
            <div><dt>Latest meter reading</dt><dd>${latest ? escapeHtml(formatDateTime(latest.recorded_at)) : 'No reading'}</dd></div>
        </div>
        <div class="consumption-history-section"><p class="eyebrow">Daily consumption</p><div class="table"><div class="table-head"><span>Day</span><span>Liters</span><span>Volume</span><span>Estimated bill</span></div>${rows(data.daily)}</div></div>
        <div class="consumption-history-section"><p class="eyebrow">Weekly consumption</p><div class="table"><div class="table-head"><span>Week of</span><span>Liters</span><span>Volume</span><span>Estimated bill</span></div>${rows(data.weekly)}</div></div>
        <div class="consumption-history-section"><p class="eyebrow">Monthly consumption</p><div class="table"><div class="table-head"><span>Month</span><span>Liters</span><span>Volume</span><span>Estimated bill</span></div>${rows(data.monthly)}</div></div>
        <div class="dialog-actions"><button type="button" class="button button-ghost" data-consumption-export>Export this history</button></div>
    `;
    container.querySelector('[data-consumption-export]')?.addEventListener('click', async () => {
        const printWindow = window.open('', '_blank');
        try {
            const url = `/api/exports/readings${buildQuery({ user_id: household.id, from: consumptionState.from || period.from?.slice(0, 10), to: consumptionState.to || period.to?.slice(0, 10) })}`;
            const response = await fetch(url, { headers: authHeaders() });
            if (!response.ok) throw new Error('PDF export failed.');
            const blobUrl = URL.createObjectURL(await response.blob());
            if (printWindow) printWindow.location.href = blobUrl;
            else window.location.href = blobUrl;
            showToast('PDF export created successfully.');
            window.setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
        } catch (error) {
            printWindow?.close();
            if (!handleAuthFailure(error)) showToast('Could not create the PDF export.', 'error');
        }
    });
};

const openConsumptionHistory = async (userId) => {
    if (!consumptionDialog) return;
    const container = $('[data-consumption-history]');
    if (container) container.innerHTML = '<div class="empty-state">Loading consumption history...</div>';
    if (typeof consumptionDialog.showModal === 'function') consumptionDialog.showModal();
    else consumptionDialog.setAttribute('open', '');

    try {
        const response = await requestJson(`/api/consumption-monitoring/${userId}/history${buildQuery({ from: consumptionState.from, to: consumptionState.to })}`);
        renderAdminConsumptionHistory(response.data);
    } catch (error) {
        if (!handleAuthFailure(error) && container) container.innerHTML = `<div class="empty-state"><strong>Unable to load history</strong><span>${escapeHtml(errorMessage(error, 'Try again later.'))}</span></div>`;
    }
};

const bindConsumptionMonitoring = () => {
    const records = $('[data-consumption-records]');
    if (records && records.dataset.bound !== '1') {
        records.dataset.bound = '1';
        records.addEventListener('click', (event) => {
            const button = event.target.closest('[data-consumption-history-id]');
            if (button) openConsumptionHistory(button.dataset.consumptionHistoryId);
        });
    }
    if (consumptionDialog && consumptionDialog.dataset.bound !== '1') {
        consumptionDialog.dataset.bound = '1';
        $('[data-consumption-dialog-close]')?.addEventListener('click', () => {
            if (consumptionDialog.close) consumptionDialog.close();
            else consumptionDialog.removeAttribute('open');
        });
    }
};

const loadAdminConsumption = async () => {
    const container = $('[data-consumption-records]');
    try {
        const response = await requestJson(`/api/consumption-monitoring${buildQuery(consumptionState)}`);
        renderConsumptionMonitoring(response);
        return response;
    } catch (error) {
        if (handleAuthFailure(error)) return null;
        if (container) container.innerHTML = `<div class="empty-state"><strong>Consumption monitoring is unavailable</strong><span>${escapeHtml(errorMessage(error, 'Try again later.'))}</span></div>`;
        setText('[data-consumption-filter-status]', 'Unable to load consumption records.');
        return null;
    }
};

const renderHouseholdOptions = (users, selects) => {
    const eligibleUsers = [...(Array.isArray(users) ? users : [])]
        .filter((user) => user.role === 'user' && user.is_active !== false && (user.account_status ?? 'active') === 'active')
        .sort((a, b) => String(a.name ?? '').localeCompare(String(b.name ?? '')));

    selects.forEach((select) => {
        if (!select) return;
        const isAnnouncement = select.matches('[data-announcement-user]');
        const emptyLabel = isAnnouncement
            ? 'All active households'
            : eligibleUsers.length ? 'Choose household' : 'No active household users are available.';
        select.innerHTML = `<option value="">${emptyLabel}</option>` + eligibleUsers
            .map((user) => {
                const details = isAnnouncement
                    ? user.email
                    : [user.email, user.address].filter(Boolean).join(' · ');
                return `<option value="${user.id}">${escapeHtml(formatPersonalDisplay(user.name))} (${escapeHtml(details)})</option>`;
            })
            .join('');
        select.disabled = eligibleUsers.length === 0;
    });

    const status = $('[data-device-household-status]');
    if (status) status.textContent = eligibleUsers.length ? `${eligibleUsers.length} active household user${eligibleUsers.length === 1 ? '' : 's'} available.` : 'No active household users are available.';
    return eligibleUsers;
};

const loadEligibleHouseholds = async (selects) => {
    const status = $('[data-device-household-status]');
    selects.forEach((select) => {
        if (select) {
            select.disabled = true;
            select.innerHTML = '<option value="">Loading household users...</option>';
        }
    });
    if (status) status.textContent = 'Loading active household users...';

    try {
        const response = await requestJson('/api/household-users?for_device=1&per_page=100');
        const users = responseItems(response);
        renderHouseholdOptions(users, selects);
        return users;
    } catch (error) {
        selects.forEach((select) => {
            if (select) {
                select.disabled = true;
                select.innerHTML = '<option value="">Household users could not be loaded.</option>';
            }
        });
        if (status) status.textContent = 'Unable to load household users. Refresh and try again.';
        console.error(error);
        return [];
    }
};

const bindAdminNotificationAudience = (form, users = []) => {
    if (!form) return;
    const audience = $('[data-notification-audience]', form);
    const householdField = $('[data-notification-household-field]', form);
    const householdSelect = $('[data-announcement-user]', form);
    const status = $('[data-notification-recipient-status]', form);
    const message = $('[data-notification-message]', form);
    const characterCount = $('[data-notification-character-count]', form);
    form.notificationEligibleUsers = Array.isArray(users) ? users : [];
    const update = () => {
        const eligibleUsers = form.notificationEligibleUsers ?? [];
        const selected = audience?.value === 'selected_household';
        if (householdField) householdField.hidden = !selected;
        if (householdSelect) {
            householdSelect.disabled = !selected || eligibleUsers.length === 0;
            if (!selected) householdSelect.value = '';
        }
        if (status) {
            status.textContent = selected
                ? (eligibleUsers.length ? 'Choose one active household recipient.' : 'No eligible active households are available.')
                : `${eligibleUsers.length} active household${eligibleUsers.length === 1 ? '' : 's'} will receive this notice.`;
        }
    };
    if (form.dataset.audienceBound !== '1') {
        form.dataset.audienceBound = '1';
        audience?.addEventListener('change', update);
        form.addEventListener('reset', () => window.setTimeout(update, 0));
        message?.addEventListener('input', () => {
            if (characterCount) characterCount.textContent = `${message.value.length} / 2000`;
        });
    }
    update();
};

const bindAdminNotificationForm = (form) => {
    if (!form || form.dataset.bound === '1') return;

    form.dataset.bound = '1';
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(form).entries());
        if (payload.audience === 'all_active_households') delete payload.user_id;
        if (!payload.user_id) delete payload.user_id;
        if (payload.audience === 'all_active_households' && !window.confirm('Send this notification to every active household?')) return;

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.dataset.defaultText = submitButton.dataset.defaultText || submitButton.textContent;
            submitButton.textContent = 'Sending...';
        }

        try {
            const response = await requestJson('/api/notifications', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            showToast(response?.message ?? `Notification sent successfully to ${response?.delivered_count ?? 0} household user${response?.delivered_count === 1 ? '' : 's'}.`);
            form.reset();
            bindAdminNotificationFilters();
            loadAdmin();
        } catch (error) {
            if (!handleAuthFailure(error)) {
                console.error(error);
                showToast(errorMessage(error, 'Could not send the notification.'), 'error');
            }
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = submitButton.dataset.defaultText || 'Send notification';
            }
        }
    });
};

const renderActivationResult = (container, details, heading = 'One-time activation information') => {
    if (!container || !details) return;

    container.innerHTML = `<article class="notification-card">
        <h4>${escapeHtml(heading)}</h4>
        <p>Device UUID: <code>${escapeHtml(details.device_uuid ?? details.device?.uuid)}</code></p>
        <p>Activation code: <strong>${escapeHtml(details.activation_code)}</strong></p>
        <small>Expires: ${escapeHtml(formatDateTime(details.expires_at))}. This code is shown once, stored only as a hash, and must be entered on the physical meter.</small>
    </article>`;
};

const renderMunicipalDevices = (items, container) => {
    if (!container) return;

    const devices = Array.isArray(items) ? items : [];
    if (!devices.length) {
        container.innerHTML = '<div class="empty-state">No registered meters found.</div>';
        return;
    }

    container.innerHTML = devices.map((device) => {
        const lifecycle = String(device.status ?? 'pending');
        const health = lifecycle === 'decommissioned'
            ? 'Decommissioned'
            : device.is_online ? 'Online' : 'Offline';
        const healthClass = health === 'Online' ? 'status-chip-soft' : health === 'Offline' ? 'status-chip-alert' : 'status-chip-muted';
        const householdName = formatPersonalDisplay(device.household?.name, 'UNASSIGNED');
        const maintenance = String(device.maintenance_status ?? 'operational').replaceAll('_', ' ');

        return `<article class="notification-card device-health-card">
            <div class="notification-head">
                <div>
                    <h4>${escapeHtml(device.asset_number)} · ${escapeHtml(device.name)}</h4>
                    <p>${escapeHtml(householdName)} · ${escapeHtml(device.serial_number)}</p>
                </div>
                <span class="status-chip ${healthClass}">${escapeHtml(health)}</span>
            </div>
            <small>Lifecycle: ${escapeHtml(lifecycle)} · Maintenance: ${escapeHtml(maintenance)}</small>
            <small>Last seen: ${escapeHtml(formatDateTime(device.last_seen_at))} · Last telemetry: ${escapeHtml(formatDateTime(device.last_telemetry_at))}</small>
            <small>Firmware: ${escapeHtml(device.firmware_version ?? 'Not reported')} · Hardware: ${escapeHtml(device.hardware_id ?? 'Not commissioned')}</small>
            <small>Installed: ${escapeHtml(device.installed_at ? formatDateTime(device.installed_at) : 'Not set')} · Address: ${escapeHtml(device.installation_address ?? 'Not set')}</small>
            <div class="button-row">
                ${lifecycle !== 'decommissioned' ? `<button type="button" class="button button-secondary" data-device-new-code="${escapeHtml(device.id)}">${lifecycle === 'suspended' ? 'Reactivate with new code' : 'New activation code'}</button>` : ''}
                ${lifecycle === 'active' ? `<button type="button" class="button button-secondary" data-device-status="${escapeHtml(device.id)}" data-next-status="maintenance">Maintenance</button>` : ''}
                ${lifecycle === 'maintenance' ? `<button type="button" class="button button-secondary" data-device-status="${escapeHtml(device.id)}" data-next-status="active">Return to service</button>` : ''}
                ${lifecycle !== 'decommissioned' ? `<button type="button" class="link-button rose" data-device-decommission="${escapeHtml(device.id)}">Decommission</button>` : ''}
            </div>
        </article>`;
    }).join('');
};

const loadAdminDevices = async () => {
    const devicesContainer = $('[data-municipal-devices]');
    if (!devicesContainer || adminDeviceRefreshInFlight || !readToken()) return adminMunicipalDevices;

    adminDeviceRefreshInFlight = true;
    try {
        const response = await requestJson('/api/municipal-devices');
        adminMunicipalDevices = responseItems(response);
        renderMunicipalDevices(adminMunicipalDevices, devicesContainer);
        return adminMunicipalDevices;
    } catch (error) {
        if (!handleAuthFailure(error)) {
            devicesContainer.innerHTML = '<div class="empty-state is-error">Unable to load meter connection health. Try again.</div>';
            console.error(error);
        }
        return [];
    } finally {
        adminDeviceRefreshInFlight = false;
        scheduleAdminDevicePolling();
    }
};

const bindAdminDeviceRegistrationForm = (form, activationResult) => {
    if (!form || form.dataset.bound === '1') return;

    form.dataset.bound = '1';
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submitButton = form.querySelector('button[type="submit"]');
        const payload = Object.fromEntries(new FormData(form).entries());
        Object.keys(payload).forEach((key) => payload[key] === '' && delete payload[key]);

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.dataset.defaultText = submitButton.dataset.defaultText || submitButton.textContent;
            submitButton.textContent = 'Registering...';
        }

        try {
            const response = await requestJson('/api/municipal-devices', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            renderActivationResult(activationResult, response.data, 'Save this one-time activation information');
            showToast('M5Stack CoreS3 meter registered and assigned successfully.');
            form.reset();
            await loadAdminDevices();
        } catch (error) {
            if (!handleAuthFailure(error)) {
                console.error(error);
                showToast(errorMessage(error, 'Could not register the meter.'), 'error');
            }
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = submitButton.dataset.defaultText || 'Register and create activation code';
            }
        }
    });
};

const bindAdminDeviceActions = (container, activationResult) => {
    if (!container || container.dataset.bound === '1') return;

    container.dataset.bound = '1';
    container.addEventListener('click', async (event) => {
        const codeButton = event.target.closest('[data-device-new-code]');
        const statusButton = event.target.closest('[data-device-status]');
        const decommissionButton = event.target.closest('[data-device-decommission]');

        try {
            if (codeButton) {
                if (!window.confirm('Rotate credentials and create a new activation code? The current device token will stop working.')) return;
                const response = await requestJson(`/api/municipal-devices/${codeButton.dataset.deviceNewCode}/activation-code`, { method: 'POST' });
                renderActivationResult(activationResult, response.data);
                showToast('New activation code created and old device credentials revoked.');
            } else if (statusButton) {
                await requestJson(`/api/municipal-devices/${statusButton.dataset.deviceStatus}`, {
                    method: 'PATCH',
                    body: JSON.stringify({
                        status: statusButton.dataset.nextStatus,
                        maintenance_status: statusButton.dataset.nextStatus === 'active' ? 'operational' : 'under_maintenance',
                    }),
                });
                showToast('Meter lifecycle status updated.');
            } else if (decommissionButton) {
                if (!window.confirm('Permanently decommission this meter and revoke its credentials?')) return;
                await requestJson(`/api/municipal-devices/${decommissionButton.dataset.deviceDecommission}`, { method: 'DELETE' });
                showToast('Meter decommissioned and device credentials revoked.');
            } else {
                return;
            }

            await loadAdminDevices();
        } catch (error) {
            if (!handleAuthFailure(error)) showToast(errorMessage(error, 'Could not update the meter.'), 'error');
        }
    });
};

const formatAdminRefreshTime = (value = new Date()) => new Date(value).toLocaleString(undefined, {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
});

const renderAdminOverviewAttention = (analytics) => {
    const container = $('[data-admin-attention-list]');
    if (!container) return;

    const households = analytics.households ?? {};
    const devices = analytics.devices ?? {};
    const alerts = analytics.alerts ?? {};
    const items = [
        [Number(alerts.unresolved_leak_alerts ?? 0), 'Unresolved leak alerts', 'Review household leak investigations', 'leak-monitoring', 'bi-exclamation-triangle'],
        [Number(devices.pending_activation ?? 0), 'Meters pending activation', 'Complete device commissioning', 'device-management', 'bi-cpu'],
        [Number(devices.offline ?? 0), 'Offline meters', 'Check connection health and last-seen time', 'device-management', 'bi-wifi-off'],
        [Number(households.inactive ?? 0) + Number(households.suspended ?? 0), 'Inactive or suspended households', 'Review account access and status', 'customer-management', 'bi-people'],
        [Number(alerts.unread_notifications ?? 0), 'Unread household notifications', 'Open the notification queue', 'notifications', 'bi-bell'],
        [Number(analytics.device_sync?.failed_attempts ?? 0), 'Failed device sync attempts', 'Review recent operational reports', 'reports', 'bi-arrow-repeat'],
    ].filter(([count]) => count > 0);

    container.innerHTML = items.length
        ? items.map(([count, title, description, target, icon]) => `<div class="admin-overview-list-item"><span class="admin-overview-list-icon"><i class="bi ${icon}" aria-hidden="true"></i></span><span><strong>${formatNumber(count, 0)} ${escapeHtml(title)}</strong><small>${escapeHtml(description)}</small></span><button class="link-button" type="button" data-admin-nav="${target}">Open</button></div>`).join('')
        : '<div class="empty-state">No operational follow-ups require attention.</div>';

    if (container.dataset.bound !== '1') {
        container.dataset.bound = '1';
        container.addEventListener('click', (event) => {
            const button = event.target.closest('[data-admin-nav]');
            if (button) showAdminView(button.dataset.adminNav);
        });
    }
};

const renderAdminOverviewActivity = (analytics) => {
    const container = $('[data-admin-activity-list]');
    if (!container) return;

    const activity = Array.isArray(analytics.recent?.administrative_actions)
        ? analytics.recent.administrative_actions.slice(0, 6)
        : [];
    container.innerHTML = activity.length
        ? activity.map((item) => {
            const label = String(item.action ?? 'system event').replaceAll('_', ' ').replace(/^./, (character) => character.toUpperCase());
            return `<div class="admin-overview-list-item"><span class="admin-overview-list-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span><span><strong>${escapeHtml(label)}</strong><small>${escapeHtml(item.description ?? 'Administrative event')} · ${escapeHtml(item.actor ?? 'System')} · ${escapeHtml(formatCompactDateTime(item.created_at))}</small></span></div>`;
        }).join('')
        : '<div class="empty-state">No recent administrative or device activity.</div>';
};

const bindAdminSettingsForm = (form, endpoint, statusSelector, successMessage) => {
    if (!form || form.dataset.bound === '1') return;

    form.dataset.bound = '1';
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (form.dataset.saving === '1') return;

        const submitButton = $('button[type="submit"]', form);
        const feedback = $(statusSelector);
        const payload = Object.fromEntries(new FormData(form).entries());
        $$('input[type="checkbox"]', form).forEach((checkbox) => {
            payload[checkbox.name] = checkbox.checked;
        });

        form.dataset.saving = '1';
        submitButton && (submitButton.disabled = true);
        if (feedback) {
            feedback.hidden = false;
            feedback.classList.remove('is-error');
            feedback.textContent = 'Saving configuration...';
        }

        try {
            await requestJson(endpoint, { method: 'POST', body: JSON.stringify(payload) });
            if (feedback) feedback.textContent = successMessage;
            showToast(successMessage);
            await loadAdmin();
        } catch (error) {
            if (!handleAuthFailure(error)) {
                const message = errorMessage(error, 'Could not save this configuration.');
                if (feedback) {
                    feedback.hidden = false;
                    feedback.classList.add('is-error');
                    feedback.textContent = message;
                }
                showToast(message, 'error');
            }
        } finally {
            form.dataset.saving = '0';
            if (submitButton) submitButton.disabled = false;
        }
    });
};

const setBillingManagementState = (message, tone = '') => {
    const state = $('[data-billing-management-state]');
    if (!state) return;
    state.textContent = message;
    state.classList.remove('is-loading', 'is-error', 'is-empty', 'is-ready');
    if (tone) state.classList.add(`is-${tone}`);
};

const renderBillingManagementSummary = (meta = {}) => {
    setText('[data-billing-total-records]', formatNumber(meta.total_records ?? 0, 0));
    setText('[data-billing-finalized-charges]', formatCurrency(meta.finalized_charges ?? 0));
    setText('[data-billing-estimated-charges]', formatCurrency(meta.estimated_charges ?? 0));
    setText('[data-billing-outstanding]', formatCurrency(meta.outstanding_balance ?? 0));
};

const billingStatusLabel = (status) => ({
    estimated: 'Estimated',
    unpaid: 'Unpaid',
    partial: 'Partial',
    paid: 'Paid',
}[status] ?? String(status ?? 'Unknown').replaceAll('_', ' '));

const renderBillingRecords = (response) => {
    const container = $('[data-billing-management-records]');
    if (!container) return;
    const errorNode = $('[data-billing-management-error]');
    if (errorNode) errorNode.hidden = true;
    const records = responseItems(response);
    billingRecordsByKey = new Map(records.map((record) => [String(record.id), record]));
    renderBillingManagementSummary(response?.meta ?? {});

    if (!records.length) {
        container.innerHTML = '<div class="empty-state table-empty-state"><strong>No billing records found</strong><span>There are no estimated readings or finalized statements for the selected period and filters.</span></div>';
        setBillingManagementState('No billing records match the selected period.', 'empty');
    } else {
        container.innerHTML = records.map((record) => {
            const household = record.household ?? {};
            const typeLabel = record.record_type === 'finalized' ? 'Finalized' : 'Estimated';
            const typeClass = record.record_type === 'finalized' ? 'status-chip-soft' : 'status-chip-muted';
            const statusClass = record.status === 'paid' ? 'status-chip-soft' : record.status === 'estimated' ? 'status-chip-muted' : 'status-chip-alert';
            const key = escapeHtml(String(record.id));
            const actions = record.record_type === 'finalized'
                ? `<button type="button" class="link-button" data-billing-detail="${key}">Details</button>${['unpaid', 'partial'].includes(record.status) ? `<button type="button" class="link-button" data-billing-pay="${key}">Record payment</button>` : ''}`
                : `<button type="button" class="link-button" data-billing-detail="${key}">Details</button><button type="button" class="link-button" data-billing-generate="${key}">Finalize month</button>`;

            return `<div class="table-row billing-management-row">
                <span><strong class="table-primary">${escapeHtml(formatPersonalDisplay(household.name))}</strong><small class="table-secondary">${escapeHtml(household.account_number ?? household.email ?? 'No account number')}</small></span>
                <span>${escapeHtml(record.period?.label ?? '--')}</span>
                <span><span class="status-chip ${typeClass}">${typeLabel}</span></span>
                <span><strong class="table-primary">${formatCubicMeterValue(record.total_cubic_meter ?? 0)}</strong><small class="table-secondary">${formatNumber(record.total_liters ?? 0, 2)} L${record.reading_count ? ` · ${formatNumber(record.reading_count, 0)} readings` : ''}</small></span>
                <span><strong class="table-primary">${formatCurrency(record.current_charge ?? 0, record.currency)}</strong><small class="table-secondary">Rate ${formatCurrency(record.water_rate ?? 0, record.currency)} / m³</small></span>
                <span><span class="status-chip ${statusClass}">${escapeHtml(billingStatusLabel(record.status))}</span><small class="table-secondary">Balance ${formatCurrency(record.remaining_balance ?? 0, record.currency)}</small></span>
                <span class="table-actions">${actions}</span>
            </div>`;
        }).join('');
        setBillingManagementState(`${formatNumber(response?.meta?.total_records ?? records.length, 0)} real billing record${Number(response?.meta?.total_records ?? records.length) === 1 ? '' : 's'} loaded.`, 'ready');
    }

    const hasRecords = Number(response?.meta?.total_records ?? records.length) > 0;
    $$('[data-billing-export]').forEach((button) => {
        button.hidden = !hasRecords;
        button.disabled = !hasRecords;
    });
};

const renderBillingDetail = (data) => {
    const container = $('[data-billing-detail-content]');
    if (!container) return;
    const record = data.record_type === 'finalized' ? {
        ...data.statement,
        record_type: 'finalized',
        household: data.household,
        total_liters: data.statement.total_liters,
        total_cubic_meter: data.statement.total_cubic_meter,
        water_rate: data.statement.water_rate,
        fixed_monthly_charge: data.calculation.fixed_monthly_charge,
        subsidy_deduction: data.calculation.subsidy_deduction,
        current_charge: data.calculation.current_charge,
        amount_paid: data.calculation.amount_paid,
        remaining_balance: data.calculation.remaining_balance,
        currency: data.calculation.currency,
        period: { label: new Date(data.statement.billing_month).toLocaleDateString(undefined, { month: 'long', year: 'numeric' }) },
    } : data;
    const household = record.household ?? {};
    const usageCharge = data.calculation?.usage_charge ?? Number(record.total_cubic_meter ?? 0) * Number(record.water_rate ?? 0);
    const readings = Array.isArray(data.readings) ? data.readings : [];
    const detailTitle = $('[data-billing-detail-title]');
    if (detailTitle) detailTitle.textContent = `${formatPersonalDisplay(household.name, 'Household')} · ${record.period?.label ?? 'Billing details'}`;
    container.innerHTML = `<div class="summary-list billing-detail-summary">
        <div><dt>Record type</dt><dd>${record.record_type === 'finalized' ? 'Finalized statement' : 'Estimated from readings'}</dd></div>
        <div><dt>Household</dt><dd>${escapeHtml(formatPersonalDisplay(household.name))}</dd></div>
        <div><dt>Consumption</dt><dd>${formatCubicMeterValue(record.total_cubic_meter ?? 0)} · ${formatNumber(record.total_liters ?? 0, 2)} L</dd></div>
        <div><dt>Usage charge</dt><dd>${formatCurrency(usageCharge, record.currency)}</dd></div>
        <div><dt>Fixed monthly charge</dt><dd>${formatCurrency(record.fixed_monthly_charge ?? 0, record.currency)}</dd></div>
        <div><dt>Subsidy or discount</dt><dd>− ${formatCurrency(record.subsidy_deduction ?? 0, record.currency)}</dd></div>
        <div><dt>Current charge</dt><dd>${formatCurrency(record.current_charge ?? 0, record.currency)}</dd></div>
        <div><dt>Paid</dt><dd>${formatCurrency(record.amount_paid ?? 0, record.currency)}</dd></div>
        <div><dt>Remaining balance</dt><dd>${formatCurrency(record.remaining_balance ?? 0, record.currency)}</dd></div>
        <div><dt>Applicable rate</dt><dd>${formatCurrency(record.water_rate ?? 0, record.currency)} / m³</dd></div>
    </div>${readings.length ? `<div class="billing-detail-readings"><p class="eyebrow">Source readings</p><div class="table"><div class="table-head"><span>Recorded</span><span>Flow</span><span>Liters</span><span>Estimated</span></div>${readings.slice(0, 20).map((reading) => `<div class="table-row"><span>${escapeHtml(formatDateTime(reading.recorded_at))}</span><span>${formatNumber(reading.flow_rate)} L/min</span><span>${formatNumber(reading.liters_used, 2)} L</span><span>${formatCurrency(reading.estimated_bill, record.currency)}</span></div>`).join('')}</div></div>` : '<div class="empty-state">No source readings are attached to this finalized statement.</div>'}`;
};

const openBillingDetail = async (record) => {
    if (!billingDetailDialog) return;
    const container = $('[data-billing-detail-content]');
    if (container) container.innerHTML = '<div class="empty-state">Loading billing calculation...</div>';
    if (typeof billingDetailDialog.showModal === 'function') billingDetailDialog.showModal();
    else billingDetailDialog.setAttribute('open', '');

    try {
        const data = record.record_type === 'finalized'
            ? (await requestJson(`/api/billing-management/${record.id}`)).data
            : record;
        renderBillingDetail(data);
    } catch (error) {
        if (!handleAuthFailure(error) && container) container.innerHTML = `<div class="billing-management-error">${escapeHtml(errorMessage(error, 'Could not load this billing calculation.'))}</div>`;
    }
};

const closeBillingDetail = () => {
    if (!billingDetailDialog) return;
    if (typeof billingDetailDialog.close === 'function') billingDetailDialog.close();
    else billingDetailDialog.removeAttribute('open');
};

const bindBillingManagementActions = () => {
    const container = $('[data-billing-management-records]');
    if (container && container.dataset.bound !== '1') {
        container.dataset.bound = '1';
        container.addEventListener('click', async (event) => {
            const detailButton = event.target.closest('[data-billing-detail]');
            const generateButton = event.target.closest('[data-billing-generate]');
            const payButton = event.target.closest('[data-billing-pay]');
            const key = detailButton?.dataset.billingDetail ?? generateButton?.dataset.billingGenerate ?? payButton?.dataset.billingPay;
            const record = key ? billingRecordsByKey.get(String(key)) : null;
            if (!record) return;
            try {
                if (detailButton) {
                    await openBillingDetail(record);
                    return;
                }
                if (generateButton) {
                    if (!window.confirm(`Finalize the ${record.period?.label ?? 'selected'} statement for ${formatPersonalDisplay(record.household?.name)}?`)) return;
                    await requestJson('/api/billing-statements/generate', { method: 'POST', body: JSON.stringify({ user_id: record.household.id, month: (record.period_from ?? '').slice(0, 7) || new Date().toISOString().slice(0, 7) }) });
                    showToast('Billing statement finalized successfully.');
                    await loadAdminBillingManagement();
                    return;
                }
                const amount = window.prompt('Enter the payment amount.', String(record.remaining_balance ?? 0));
                if (amount === null) return;
                await requestJson(`/api/billing-statements/${record.id}/paid`, { method: 'PATCH', body: JSON.stringify({ amount_paid: Number(amount) }) });
                showToast('Billing payment recorded successfully.');
                await loadAdminBillingManagement();
            } catch (error) {
                if (!handleAuthFailure(error)) showToast(errorMessage(error, 'Could not update this billing record.'), 'error');
            }
        });
    }
    const closeButton = $('[data-billing-detail-close]');
    if (closeButton && closeButton.dataset.bound !== '1') {
        closeButton.dataset.bound = '1';
        closeButton.addEventListener('click', closeBillingDetail);
    }
};

const bindBillingManagementExports = () => {
    $$('[data-billing-export]').forEach((button) => {
        if (button.dataset.bound === '1') return;
        button.dataset.bound = '1';
        button.addEventListener('click', async () => {
            if (button.disabled) return;
            const printWindow = window.open('', '_blank');
            try {
                const query = buildQuery({ from: billingManagementState.from, to: billingManagementState.to });
                const response = await fetch(`${button.dataset.billingExport}${query}`, { headers: authHeaders() });
                if (!response.ok) throw new Error('PDF export failed.');
                const url = URL.createObjectURL(await response.blob());
                if (printWindow) printWindow.location.href = url; else window.location.href = url;
                showToast('PDF export created successfully.');
                window.setTimeout(() => URL.revokeObjectURL(url), 60000);
            } catch (error) {
                printWindow?.close();
                if (!handleAuthFailure(error)) showToast('Could not create the PDF export.', 'error');
            }
        });
    });
};

const loadAdminBillingManagement = async () => {
    const container = $('[data-billing-management-records]');
    if (!container) return;
    setBillingManagementState('Loading billing records...', 'loading');
    container.innerHTML = '<div class="empty-state">Loading real billing records...</div>';
    try {
        const response = await requestJson(`/api/billing-management${buildQuery(billingManagementState)}`);
        renderBillingRecords(response);
        renderPager($('[data-billing-management-pager]'), responsePaginator(response), (page) => {
            billingManagementState.page = page;
            loadAdminBillingManagement();
        });
    } catch (error) {
        if (handleAuthFailure(error)) return;
        const message = errorMessage(error, 'Billing records could not be loaded.');
        const errorNode = $('[data-billing-management-error]');
        if (errorNode) {
            errorNode.hidden = false;
            errorNode.textContent = message;
        }
        container.innerHTML = '<div class="empty-state">Billing records are unavailable right now.</div>';
        $$('[data-billing-export]').forEach((button) => { button.hidden = true; button.disabled = true; });
        setBillingManagementState(message, 'error');
    }
};

const bindBillingManagement = () => {
    bindBillingManagementActions();
    bindBillingManagementExports();
    if (billingManagementFilterForm && billingManagementFilterForm.dataset.bound !== '1') {
        billingManagementFilterForm.dataset.bound = '1';
        const fields = serializeForm(billingManagementFilterForm);
        Object.assign(billingManagementState, fields, { page: 1, per_page: Number(fields.per_page ?? 10) });
        billingManagementFilterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            Object.assign(billingManagementState, serializeForm(billingManagementFilterForm), { page: 1 });
            billingManagementState.per_page = Number(billingManagementState.per_page ?? 10);
            loadAdminBillingManagement();
        });
        billingManagementFilterForm.addEventListener('reset', () => {
            window.setTimeout(() => {
                Object.assign(billingManagementState, { page: 1, per_page: 10, search: '', bill_type: 'all', status: 'all', from: '', to: '' });
                loadAdminBillingManagement();
            }, 0);
        });
    }
};

const reportTypeLabels = {
    all: 'All operational reports',
    consumption: 'Consumption and meter readings',
    billing: 'Billing records',
    notifications: 'Notifications and announcements',
    leaks: 'Leak alerts',
    devices: 'Device and connection health',
    households: 'Household account summaries',
    audit: 'Administrative activity',
};

const reportStatusOptions = {
    all: [],
    consumption: [['threshold', 'Threshold exceeded'], ['leak', 'Possible leak']],
    billing: [['paid', 'Paid'], ['partial', 'Partially paid'], ['unpaid', 'Unpaid']],
    notifications: [['read', 'Read'], ['unread', 'Unread']],
    leaks: [['detected', 'Detected'], ['acknowledged', 'Acknowledged'], ['investigating', 'Investigating'], ['resolved', 'Resolved'], ['false_alarm', 'False alarm']],
    devices: [['pending', 'Pending'], ['active', 'Active'], ['suspended', 'Suspended'], ['maintenance', 'Maintenance'], ['decommissioned', 'Decommissioned']],
    households: [['active', 'Active'], ['inactive', 'Inactive'], ['suspended', 'Suspended'], ['archived', 'Archived']],
    audit: [['success', 'Success'], ['failed', 'Failed']],
};

const localDateValue = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const updateReportPeriodFields = () => {
    if (!reportManagementFilterForm) return;
    const period = $('select[name="period"]', reportManagementFilterForm)?.value ?? 'monthly';
    const from = $('[data-report-date-from]');
    const to = $('[data-report-date-to]');
    const description = $('[data-report-period-description]');
    const today = new Date();
    let start = today;
    let end = today;
    if (period === 'weekly') {
        start = new Date(today);
        start.setDate(today.getDate() - ((today.getDay() + 6) % 7));
        end = new Date(start);
        end.setDate(start.getDate() + 6);
    } else if (period === 'monthly') {
        start = new Date(today.getFullYear(), today.getMonth(), 1);
        end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    }
    const custom = period === 'custom';
    if (from) from.disabled = !custom;
    if (to) to.disabled = !custom;
    if (!custom) {
        if (from) from.value = localDateValue(start);
        if (to) to.value = localDateValue(end);
    }
    if (description) {
        description.textContent = custom
            ? 'Choose a date range up to 366 days.'
            : `${period.charAt(0).toUpperCase()}${period.slice(1)} report period: ${localDateValue(start)} to ${localDateValue(end)}`;
    }
};

const updateReportStatusOptions = () => {
    if (!reportManagementFilterForm) return;
    const type = $('select[name="report_type"]', reportManagementFilterForm)?.value ?? 'all';
    const status = $('[data-report-status]');
    if (!status) return;
    const current = reportManagementState.status;
    status.innerHTML = '<option value="">Any status</option>' + (reportStatusOptions[type] ?? [])
        .map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`).join('');
    status.value = (reportStatusOptions[type] ?? []).some(([value]) => value === current) ? current : '';
};

const setReportManagementState = (message, tone = '') => {
    const state = $('[data-report-management-state]');
    if (!state) return;
    state.textContent = message;
    state.classList.remove('is-loading', 'is-error', 'is-empty', 'is-ready');
    if (tone) state.classList.add(`is-${tone}`);
};

const renderReportSummary = (summary = {}, reportType = 'all') => {
    const container = $('[data-report-summary]');
    if (!container) return;
    const consumption = Number(summary.total_cubic_meter ?? 0);
    const charges = Number(summary.estimated_bill ?? 0) + Number(summary.current_charge ?? 0);
    const attention = Number(summary.leak_count ?? 0) + Number(summary.threshold_count ?? 0)
        + Number(summary.unread_count ?? 0) + Number(summary.active_count ?? 0)
        + Number(summary.unnotified_count ?? 0);
    const consumptionLabel = reportType === 'billing' ? 'Billed volume' : 'Water volume';
    const chargeLabel = reportType === 'billing' ? 'Current charges' : 'Estimated charges';
    container.innerHTML = `
        <article class="stat-card"><span>Records</span><strong>${formatNumber(summary.total_records ?? 0, 0)}</strong><small>Real records in this period</small></article>
        <article class="stat-card"><span>${consumptionLabel}</span><strong>${formatCubicMeterValue(consumption)}</strong><small>${formatNumber(summary.total_liters ?? 0, 0)} L where available</small></article>
        <article class="stat-card"><span>${chargeLabel}</span><strong>${formatCurrency(charges)}</strong><small>Based on stored calculations</small></article>
        <article class="stat-card"><span>Attention</span><strong>${formatNumber(attention, 0)}</strong><small>Alerts, unread, or unresolved records</small></article>`;
};

const renderReportPreview = (report) => {
    const head = $('[data-report-preview-head]');
    const body = $('[data-report-preview-body]');
    const label = $('[data-report-preview-label]');
    const error = $('[data-report-error]');
    const records = Array.isArray(report?.items) ? report.items : [];
    const columns = Array.isArray(report?.columns) ? report.columns : [];
    if (label) label.textContent = `${reportTypeLabels[report?.report_type] ?? 'Operational report'} · ${report?.period?.label ?? ''}`;
    if (error) error.hidden = true;
    if (head) head.innerHTML = columns.map((column) => `<th>${escapeHtml(column.label)}</th>`).join('');
    if (body) body.innerHTML = records.length
        ? records.map((item) => `<tr>${columns.map((column) => `<td>${escapeHtml(item[column.key] ?? '—')}</td>`).join('')}</tr>`).join('')
        : `<tr><td colspan="${Math.max(columns.length, 1)}"><div class="empty-state table-empty-state"><strong>No report records found</strong><span>Try a wider period or different filters. No sample records are generated.</span></div></td></tr>`;
};

const setReportExports = (enabled, report = null) => {
    $$('[data-report-export]').forEach((button) => {
        button.hidden = !enabled;
        button.disabled = !enabled;
        if (enabled && report) button.textContent = `Export ${reportTypeLabels[report.report_type] ?? 'selected report'} · ${report.period?.label ?? 'selected period'}`;
    });
};

const loadAdminReports = async () => {
    if (!reportManagementFilterForm) return;
    const body = $('[data-report-preview-body]');
    setReportManagementState('Loading report data...', 'loading');
    setReportExports(false);
    if (body) body.innerHTML = '<tr><td>Loading real report data...</td></tr>';
    try {
        const response = await requestJson(`/api/report-management${buildQuery(reportManagementState)}`);
        const report = response?.data ?? {};
        renderReportSummary(report.summary, report.report_type);
        renderReportPreview(report);
        const total = Number(report.meta?.total ?? report.summary?.total_records ?? 0);
        setReportExports(total > 0, report);
        setReportManagementState(total > 0 ? `${formatNumber(total, 0)} real record${total === 1 ? '' : 's'} loaded.` : 'No records match the selected filters.', total > 0 ? 'ready' : 'empty');
        renderPager($('[data-report-pager]'), report.meta, (page) => {
            reportManagementState.page = page;
            loadAdminReports();
        });
        const pager = $('[data-report-pager]');
        if (pager) pager.hidden = !report.meta?.total;
    } catch (error) {
        if (handleAuthFailure(error)) return;
        const message = errorMessage(error, 'Reports could not be loaded.');
        const errorNode = $('[data-report-error]');
        if (errorNode) {
            errorNode.hidden = false;
            const messageNode = $('[data-report-error-message]', errorNode);
            if (messageNode) messageNode.textContent = message;
        }
        if (body) body.innerHTML = '';
        setReportExports(false);
        setReportManagementState(message, 'error');
    }
};

const bindAdminReports = () => {
    if (!reportManagementFilterForm || reportManagementFilterForm.dataset.bound === '1') return;
    reportManagementFilterForm.dataset.bound = '1';
    const period = $('select[name="period"]', reportManagementFilterForm);
    const type = $('select[name="report_type"]', reportManagementFilterForm);
    updateReportPeriodFields();
    updateReportStatusOptions();
    period?.addEventListener('change', updateReportPeriodFields);
    type?.addEventListener('change', updateReportStatusOptions);
    reportManagementFilterForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const fields = serializeForm(reportManagementFilterForm);
        if (fields.period === 'custom' && (!fields.from || !fields.to)) {
            setReportManagementState('Choose both dates for a custom report period.', 'error');
            return;
        }
        Object.assign(reportManagementState, fields, { page: 1, per_page: 25 });
        loadAdminReports();
    });
    $('[data-report-reset]')?.addEventListener('click', () => {
        reportManagementFilterForm.reset();
        Object.assign(reportManagementState, { page: 1, per_page: 25, period: 'monthly', report_type: 'all', from: '', to: '', search: '', status: '' });
        updateReportPeriodFields();
        updateReportStatusOptions();
        loadAdminReports();
    });
    $('[data-report-retry]')?.addEventListener('click', loadAdminReports);
    $$('[data-report-export]').forEach((button) => button.addEventListener('click', async () => {
        if (button.disabled) return;
        const printWindow = window.open('', '_blank');
        try {
            const query = buildQuery({ ...reportManagementState, page: '', per_page: '' });
            const response = await fetch(`${button.dataset.reportExport}${query}`, { headers: authHeaders() });
            if (!response.ok) throw new Error(await response.text() || 'PDF export failed.');
            const url = URL.createObjectURL(await response.blob());
            if (printWindow) printWindow.location.href = url; else window.location.href = url;
            showToast('Report PDF export created successfully.');
            window.setTimeout(() => URL.revokeObjectURL(url), 60000);
        } catch (error) {
            printWindow?.close();
            if (!handleAuthFailure(error)) showToast(errorMessage(error, 'Could not create the report export.'), 'error');
        }
    }));
};

const loadAdmin = async () => {
    if (!hasTokenOrReport()) {
        return;
    }

    const devicesContainer = $('[data-municipal-devices]');
    const reportsContainer = $('[data-admin-reports]');
    const billingSummaryContainer = $('[data-admin-billing-summary]');
    const deviceHouseholdSelect = $('[data-device-household-select]');
    const householdSelect = $('[data-household-select]');
    const userForm = $('[data-admin-user-form]');
    const billingRatesForm = $('[data-billing-rates-form]');
    const consumptionAlertForm = $('[data-consumption-alert-form]');
    const leakSettingsForm = $('[data-leak-settings-form]');
    const deviceRegisterForm = $('[data-device-register-form]');
    const activationResult = $('[data-activation-result]');
    const adminAlertsContainer = $('[data-admin-alerts]');
    const announcementForm = $('[data-announcement-form]');
    const announcementUser = $('[data-announcement-user]');

    bindAdminUserForm(userForm);
    bindAdminHouseholdEditForm();
    bindAdminDeviceRegistrationForm(deviceRegisterForm, activationResult);
    bindAdminDeviceActions(devicesContainer, activationResult);
    bindAdminLeakActions(adminAlertsContainer);
    // Bind this before dashboard/report requests so notification delivery is
    // not disabled by an unrelated analytics or device endpoint failure.
    bindAdminNotificationForm(announcementForm);
    bindAdminNotificationFilters();
    bindAdminSettingsForm(billingRatesForm, '/api/billing-settings/rates', '[data-billing-rates-status]', 'Billing rates saved successfully.');
    bindAdminSettingsForm(consumptionAlertForm, '/api/billing-settings/consumption-alerts', '[data-consumption-alert-status]', 'Consumption alert settings saved successfully.');
    bindAdminSettingsForm(leakSettingsForm, '/api/billing-settings/leak-settings', '[data-leak-settings-status]', 'Leak monitoring settings saved successfully.');
    bindBillingManagement();
    loadAdminBillingManagement();
    bindAdminReports();
    loadAdminReports();

    if (consumptionFilterForm) {
        const fields = serializeForm(consumptionFilterForm);
        consumptionState.page = Number(fields.page ?? 1);
        consumptionState.per_page = Number(fields.per_page ?? 10);
        consumptionState.search = fields.search ?? '';
        consumptionState.account_status = fields.account_status ?? '';
        consumptionState.meter_status = fields.meter_status ?? '';
        consumptionState.usage_condition = fields.usage_condition ?? '';
        consumptionState.from = fields.from ?? '';
        consumptionState.to = fields.to ?? '';
    }

    if (householdUserFilterForm) {
        const fields = serializeForm(householdUserFilterForm);
        householdUserState.page = Number(fields.page ?? 1);
        householdUserState.per_page = Number(fields.per_page ?? 10);
        householdUserState.search = fields.search ?? '';
        householdUserState.account_status = fields.account_status ?? '';
    }

    try {
        const me = await requestJson('/api/me');
        if (redirectAwayFromWrongRole(me.data, 'admin')) {
            return;
        }

        setText('[data-account-role]', roleLabelForUser(me.data));

        // Customer management must remain usable if an unrelated dashboard
        // report or device endpoint is temporarily unavailable.
        await loadAdminUsers({ householdsOnly: true });
        bindConsumptionMonitoring();
        const consumptionPromise = loadAdminConsumption();
        const overviewHouseholdUsers = await loadEligibleHouseholds([deviceHouseholdSelect, householdSelect, announcementUser]);
        bindAdminNotificationAudience(announcementForm, overviewHouseholdUsers);
        const municipalDevicesPromise = loadAdminDevices();
        const leakAlertsPromise = loadAdminLeakAlerts();
        const notificationHistoryPromise = loadAdminNotificationHistory();
        const [summaryResponse, analyticsResponse, billingResponse, consumptionResponse, notificationsResponse] = await Promise.all([
            requestJson('/api/summary'),
            requestJson(`/api/analytics/admin${analyticsQuery(adminAnalyticsRange, adminAnalyticsFrom, adminAnalyticsTo, 'last_12_months')}`),
            requestJson('/api/billing-settings'),
            requestJson('/api/reports/consumption'),
            requestJson('/api/reports/notifications'),
        ]);

        const billingSettings = billingResponse.data ?? null;
        const billingFormSettings = billingResponse.scheduled ?? billingSettings;
        activeCurrency = billingSettings?.currency ?? 'PHP';
        $$('[data-rate-currency]').forEach((node) => { node.textContent = billingFormSettings?.currency ?? activeCurrency; });
        if (billingFormSettings) {
            const setValue = (form, field, value) => {
                const input = form?.elements?.namedItem(field);
                if (input && value !== undefined && value !== null) input.value = value;
            };
            setValue(billingRatesForm, 'water_rate', billingFormSettings.water_rate ?? 0);
            setValue(billingRatesForm, 'fixed_monthly_charge', billingFormSettings.fixed_monthly_charge ?? 0);
            setValue(billingRatesForm, 'subsidy_deduction', billingFormSettings.subsidy_deduction ?? 0);
            setValue(billingRatesForm, 'effective_date', billingFormSettings.effective_date ? String(billingFormSettings.effective_date).slice(0, 10) : '');
            setValue(billingRatesForm, 'currency', billingFormSettings.currency ?? 'PHP');

            const alertUnit = billingFormSettings.consumption_alert_unit ?? 'liters';
            const thresholdValue = alertUnit === 'cubic_meters'
                ? Number(billingFormSettings.threshold_liters ?? 0) / 1000
                : Number(billingFormSettings.threshold_liters ?? 0);
            setValue(consumptionAlertForm, 'threshold_value', thresholdValue);
            setValue(consumptionAlertForm, 'threshold_unit', alertUnit);
            setValue(consumptionAlertForm, 'notification_behavior', billingFormSettings.consumption_alert_behavior ?? 'household');
            if (consumptionAlertForm?.elements?.consumption_alert_enabled) consumptionAlertForm.elements.consumption_alert_enabled.checked = billingFormSettings.consumption_alert_enabled !== false;

            setValue(leakSettingsForm, 'leak_flow_rate_threshold', billingFormSettings.leak_flow_rate_threshold ?? 0);
            setValue(leakSettingsForm, 'leak_continuous_duration_minutes', billingFormSettings.leak_continuous_duration_minutes ?? 0);
            setValue(leakSettingsForm, 'quiet_hours_start', billingFormSettings.quiet_hours_start ?? 22);
            setValue(leakSettingsForm, 'quiet_hours_end', billingFormSettings.quiet_hours_end ?? 4);
            setValue(leakSettingsForm, 'leak_alert_behavior', billingFormSettings.leak_alert_behavior ?? 'household');
            if (leakSettingsForm?.elements?.leak_alert_enabled) leakSettingsForm.elements.leak_alert_enabled.checked = billingFormSettings.leak_alert_enabled !== false;
        }
        const summary = summaryResponse.data ?? {};
        const analytics = analyticsResponse.data ?? {};
        const consumption = consumptionResponse.data ?? {};
        const notificationReport = notificationsResponse.data ?? {};
        const leakHouseholds = await leakAlertsPromise;
        const municipalDevices = await municipalDevicesPromise;
        await consumptionPromise;
        await notificationHistoryPromise;
        const onlineDevices = municipalDevices.filter((device) => device.is_online).length;
        const offlineDevices = municipalDevices.filter((device) => !device.is_online && device.status !== 'decommissioned').length;
        const totalConsumption = analytics.consumption?.has_data
            ? formatCubicMeters(analytics.consumption.total_liters)
            : 'No data yet';
        const estimatedBilling = analytics.consumption?.has_data
            ? formatCurrency(analytics.consumption.estimated_billing)
            : 'No data yet';

        setText('[data-admin-households]', analytics.households?.total ?? 0);
        setText('[data-admin-devices]', analytics.devices?.total ?? 0);
        setText('[data-admin-online-devices]', analytics.devices?.connected ?? 0);
        setText('[data-admin-offline-devices]', analytics.devices?.offline ?? 0);
        setText('[data-admin-alert-count]', analytics.alerts?.unread_notifications ?? 0);
        setText('[data-admin-unread-notifications]', analytics.alerts?.unread_notifications ?? 0);
        setText('[data-admin-unresolved-leaks]', analytics.alerts?.unresolved_leak_alerts ?? 0);
        setText('[data-admin-liters]', totalConsumption);
        setText('[data-admin-billing-total]', estimatedBilling);
        setText('[data-admin-active-households]', analytics.households?.active ?? 0);
        setText('[data-admin-inactive-households]', analytics.households?.inactive ?? 0);
        setText('[data-admin-suspended-households]', analytics.households?.suspended ?? 0);
        setText('[data-admin-assigned-devices]', analytics.devices?.assigned ?? 0);
        setText('[data-admin-pending-devices]', analytics.devices?.pending_activation ?? 0);
        setText('[data-admin-waiting-devices]', analytics.devices?.waiting_for_first_reading ?? 0);
        setText('[data-admin-analytics-timezone]', analytics.period?.timezone ?? 'UTC');
        setText('[data-admin-last-refreshed]', formatAdminRefreshTime());
        const overviewRangeLabels = {
            last_7_days: 'Last 7 days',
            last_30_days: 'Last 30 days',
            last_3_months: 'Last 3 months',
            last_12_months: 'Last 12 months',
        };
        setText('[data-admin-overview-period]', analytics.range === 'custom'
            ? `${formatDateTime(analytics.period?.from)} – ${formatDateTime(analytics.period?.to)}`
            : (overviewRangeLabels[analytics.range] ?? 'Selected period'));
        renderAdminOverviewAttention(analytics);
        renderAdminOverviewActivity(analytics);
        renderMonthlyChart($('[data-admin-monthly-chart]'), summary.monthly_usage ?? [], {
            dataAvailable: summary.monthly_usage?.some((row) => Number(row.reading_count ?? 0) > 0) === true,
            emptyMessage: '<div class="chart-empty-state"><strong>No analytics data available yet</strong><span>Analytics will appear after the system receives valid water-meter readings.</span></div>',
        });

        if (reportsContainer) {
            const recentActions = analytics.recent?.administrative_actions ?? [];
            const recentActivityMarkup = recentActions.length
                ? recentActions.slice(0, 8).map((item) => `<li><strong>${escapeHtml(item.action)}</strong><span>${escapeHtml(item.description)}</span><small>${escapeHtml(item.actor ?? 'System')} · ${escapeHtml(formatCompactDateTime(item.created_at))}</small></li>`).join('')
                : '<li class="empty-state">No administrative activity recorded for this period.</li>';
            reportsContainer.innerHTML = `
                <div><dt>Selected-period readings</dt><dd>${analytics.consumption?.reading_count ?? 'No data yet'}</dd></div>
                <div><dt>Monthly usage</dt><dd>${totalConsumption}</dd></div>
                <div><dt>Estimated billing</dt><dd>${estimatedBilling}</dd></div>
                <div><dt>Unread alerts</dt><dd>${analytics.alerts?.unread_notifications ?? 0}</dd></div>
                <div><dt>Consumption rows</dt><dd>${consumption.summary?.reading_count ?? 0}</dd></div>
                <div><dt>Notification items</dt><dd>${notificationReport.total ?? 0}</dd></div>
                <div><dt>Failed device sync attempts</dt><dd>${analytics.device_sync?.failed_attempts ?? 0}</dd></div>
                <div><dt>Average household usage</dt><dd>${analytics.consumption?.average_household_liters === null || analytics.consumption?.average_household_liters === undefined ? 'No data yet' : formatCubicMeters(analytics.consumption.average_household_liters)}</dd></div>
                <div><dt>Recent account registrations</dt><dd>${analytics.recent?.account_registrations?.length ?? 0}</dd></div>
                <div><dt>Recent account deactivations</dt><dd>${analytics.recent?.account_deactivations?.length ?? 0}</dd></div>
                <div><dt>Recent device assignments</dt><dd>${analytics.recent?.device_assignments?.length ?? 0}</dd></div>
                <div class="analytics-activity"><dt>Recent administrative actions</dt><dd><ul>${recentActivityMarkup}</ul></dd></div>
            `;
        }

        if (billingSummaryContainer) {
            billingSummaryContainer.innerHTML = `
                <div><dt>Water rate per m³</dt><dd>${billingSettings ? formatCurrency(billingSettings.water_rate) : '--'}</dd></div>
                <div><dt>Fixed monthly charge</dt><dd>${billingSettings ? formatCurrency(billingSettings.fixed_monthly_charge ?? 0) : '--'}</dd></div>
                <div><dt>Consumption threshold</dt><dd>${billingSettings ? `${formatNumber(billingSettings.threshold_liters ?? 0)} L` : '--'}</dd></div>
                <div><dt>Leak threshold</dt><dd>${billingSettings ? `${formatNumber(billingSettings.leak_flow_rate_threshold ?? 0)} L/min` : '--'}</dd></div>
                <div><dt>Quiet hours</dt><dd>${billingSettings ? `${billingSettings.quiet_hours_start}:00 - ${billingSettings.quiet_hours_end}:00` : '--'}</dd></div>
                <div><dt>Households</dt><dd>${overviewHouseholdUsers.length}</dd></div>
                <div><dt>Devices online</dt><dd>${onlineDevices}</dd></div>
            `;
        }

        const currentSummary = $('[data-water-rate-current-summary]');
        if (currentSummary) {
            currentSummary.innerHTML = billingSettings
                ? `<div><dt>Current water rate</dt><dd>${formatCurrency(billingSettings.water_rate)} / m³</dd></div><div><dt>Effective date</dt><dd>${escapeHtml(billingSettings.effective_date ? formatDateTime(billingSettings.effective_date) : 'Immediately')}</dd></div><div><dt>Currency</dt><dd>${escapeHtml(billingSettings.currency ?? 'PHP')}</dd></div><div><dt>Leak monitoring</dt><dd>${billingSettings.leak_alert_enabled === false ? 'Disabled' : `${formatNumber(billingSettings.leak_flow_rate_threshold ?? 0)} L/min`}</dd></div>`
                : '<div><dt>Current water rate</dt><dd>Not configured</dd></div><div><dt>Effective date</dt><dd>--</dd></div><div><dt>Currency</dt><dd>PHP</dd></div><div><dt>Leak monitoring</dt><dd>Not configured</dd></div>';
        }

        renderAdminAlerts(leakHouseholds);

        if (consumptionFilterForm && !consumptionFilterForm.dataset.bound) {
            consumptionFilterForm.dataset.bound = '1';
            consumptionFilterForm.addEventListener('submit', (event) => {
                event.preventDefault();
                const fields = serializeForm(consumptionFilterForm);
                consumptionState.page = 1;
                consumptionState.search = fields.search ?? '';
                consumptionState.account_status = fields.account_status ?? '';
                consumptionState.meter_status = fields.meter_status ?? '';
                consumptionState.usage_condition = fields.usage_condition ?? '';
                consumptionState.from = fields.from ?? '';
                consumptionState.to = fields.to ?? '';
                consumptionState.per_page = Number(fields.per_page ?? 10);
                loadAdminConsumption();
            });
            consumptionFilterForm.addEventListener('reset', () => {
                window.setTimeout(() => {
                    consumptionState.page = 1;
                    consumptionState.search = '';
                    consumptionState.account_status = '';
                    consumptionState.meter_status = '';
                    consumptionState.usage_condition = '';
                    consumptionState.from = '';
                    consumptionState.to = '';
                    consumptionState.per_page = 10;
                    loadAdminConsumption();
                }, 0);
            });
        }

        if (householdUserFilterForm && !householdUserFilterForm.dataset.bound) {
            householdUserFilterForm.dataset.bound = '1';
            householdUserFilterForm.addEventListener('submit', (event) => {
                event.preventDefault();
                householdUserState.page = 1;
                const fields = serializeForm(householdUserFilterForm);
                householdUserState.search = fields.search ?? '';
                householdUserState.account_status = fields.account_status ?? '';
                householdUserState.per_page = Number(fields.per_page ?? 10);
                loadAdminUsers({ householdsOnly: true });
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

    $$('[data-visibility-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = $(button.dataset.visibilityToggle);
            if (!input) return;

            const shouldReveal = input.type === 'password';
            input.type = shouldReveal ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(shouldReveal));
            button.setAttribute('aria-label', shouldReveal ? 'Hide password' : 'Show password');
            button.setAttribute('title', shouldReveal ? 'Hide password' : 'Show password');
            const icon = $('i', button);
            icon?.classList.toggle('bi-eye', !shouldReveal);
            icon?.classList.toggle('bi-eye-slash', shouldReveal);
        });
    });

    const closeHouseholdSidebar = () => {
        householdSidebar?.classList.remove('is-open');
        householdSidebarToggle?.setAttribute('aria-expanded', 'false');
    };

    const setHouseholdSidebarCollapsed = (isCollapsed, persist = false) => {
        document.body.classList.toggle('household-sidebar-collapsed', isCollapsed);
        if (householdSidebarCollapse) {
            householdSidebarCollapse.setAttribute('aria-expanded', String(!isCollapsed));
            householdSidebarCollapse.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
            householdSidebarCollapse.setAttribute('title', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
        }
        if (persist) {
            localStorage.setItem('smart-water-household-sidebar-collapsed', isCollapsed ? '1' : '0');
        }
    };

    if (page === 'household' && householdSidebarCollapse) {
        setHouseholdSidebarCollapsed(localStorage.getItem('smart-water-household-sidebar-collapsed') === '1');
        householdSidebarCollapse.addEventListener('click', () => {
            setHouseholdSidebarCollapsed(!document.body.classList.contains('household-sidebar-collapsed'), true);
        });
    }

    householdSidebarToggle?.addEventListener('click', () => {
        const isOpen = householdSidebar?.classList.toggle('is-open') === true;
        householdSidebarToggle.setAttribute('aria-expanded', String(isOpen));
    });
    householdSidebarDismiss?.addEventListener('click', closeHouseholdSidebar);
    notificationToggle?.addEventListener('click', () => {
        const shouldOpen = notificationDropdown?.hidden !== false;
        setNotificationDropdownOpen(shouldOpen);
        if (shouldOpen) void loadNotifications();
    });
    notificationDropdown?.addEventListener('click', (event) => {
        const notificationItem = event.target.closest('[data-notification-dropdown-item]');
        if (notificationItem) markNotificationRead(notificationItem);
        if (event.target.closest('[data-household-nav="notifications"]')) {
            setNotificationDropdownOpen(false);
        }
    });
    document.addEventListener('click', (event) => {
        if (notificationDropdown?.hidden === false && !notificationControl?.contains(event.target)) {
            setNotificationDropdownOpen(false);
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && householdSidebar?.classList.contains('is-open')) {
            closeHouseholdSidebar();
            householdSidebarToggle?.focus();
        }
        if (event.key === 'Escape' && notificationDropdown?.hidden === false) {
            setNotificationDropdownOpen(false);
            notificationToggle?.focus();
        }
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

    document.addEventListener('click', async (event) => {
        const archiveButton = event.target.closest('[data-archive-sent-notification]');
        const retryButton = event.target.closest('[data-retry-sent-notification]');
        if (!archiveButton && !retryButton) return;

        const notificationId = archiveButton?.dataset.archiveSentNotification ?? retryButton?.dataset.retrySentNotification;
        const action = archiveButton ? 'archive' : 'retry';
        const prompt = archiveButton
            ? 'Archive this notification batch from administrative history? Household inbox records will remain available.'
            : 'Retry failed deliveries for this notification batch?';
        if (!window.confirm(prompt)) return;

        try {
            const response = await requestJson(action === 'archive'
                ? `/api/notifications/${notificationId}/archive`
                : `/api/notifications/${notificationId}/retry`, { method: action === 'archive' ? 'PATCH' : 'POST' });
            showToast(response?.message ?? `Notification ${action} completed successfully.`);
            loadAdminNotificationHistory();
        } catch (error) {
            if (!handleAuthFailure(error)) showToast(errorMessage(error, `Could not ${action} the notification.`), 'error');
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
            if (accountForm.dataset.saving === '1') return;
            const submitButton = $('button[type="submit"]', accountForm);
            accountForm.dataset.saving = '1';
            if (submitButton) submitButton.disabled = true;
            setAccountFormFeedback(accountForm, 'Saving contact information...', 'loading');
            try {
                const response = await requestJson('/api/account', { method: 'PATCH', body: JSON.stringify(serializeForm(accountForm)) });
                householdProfile = response?.data ?? householdProfile;
                accountProfileLoaded = true;
                renderProfile(householdProfile);
                setAccountProfileState('Account details loaded', 'ready');
                setAccountFormFeedback(accountForm, 'Contact information saved successfully.');
                setStatus('Account updated');
                showToast('Contact details updated successfully.');
            } catch (error) {
                if (!handleAuthFailure(error)) {
                    const message = errorMessage(error, 'Could not update contact details.');
                    setAccountFormFeedback(accountForm, message, 'error');
                    showToast(message, 'error');
                }
            } finally {
                accountForm.dataset.saving = '0';
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = submitButton.dataset.originalText || submitButton.textContent;
                }
            }
        });
    }

    if (accountForm && !accountForm.dataset.validationBound) {
        accountForm.dataset.validationBound = '1';
        const emailField = accountForm.elements.email;
        const phoneField = accountForm.elements.phone;
        [emailField, phoneField].forEach((field) => {
            if (!field) return;
            field.addEventListener('input', updateAccountFormState);
            field.addEventListener('blur', () => updateAccountFormState());
        });
    }

    if (passwordForm && !passwordForm.dataset.validationBound) {
        passwordForm.dataset.validationBound = '1';
        const passwordField = passwordForm.elements.password;
        const confirmationField = passwordForm.elements.password_confirmation;
        [passwordField, confirmationField].forEach((field) => {
            if (!field) return;
            field.addEventListener('input', updatePasswordFormState);
            field.addEventListener('blur', updatePasswordFormState);
        });
    }

    passwordToggleButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const targetName = button.dataset.passwordToggle;
            const input = passwordForm?.elements[targetName];
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            button.textContent = isPassword ? 'Hide' : 'Show';
            button.setAttribute('aria-pressed', String(isPassword));
        });
    });

    if (passwordForm && !passwordForm.dataset.bound) {
        passwordForm.dataset.bound = '1';
        passwordForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (passwordForm.dataset.saving === '1') return;
            const submitButton = $('button[type="submit"]', passwordForm);
            passwordForm.dataset.saving = '1';
            if (submitButton) submitButton.disabled = true;
            setAccountFormFeedback(passwordForm, 'Updating password...', 'loading');
            try {
                await requestJson('/api/account/password', { method: 'PATCH', body: JSON.stringify(serializeForm(passwordForm)) });
                passwordForm.reset();
                setAccountFormFeedback(passwordForm, 'Password changed successfully.');
                setStatus('Password changed');
                showToast('Password changed successfully.');
            } catch (error) {
                if (!handleAuthFailure(error)) {
                    const message = errorMessage(error, 'Could not change the password.');
                    setAccountFormFeedback(passwordForm, message, 'error');
                    showToast(message, 'error');
                }
            } finally {
                passwordForm.dataset.saving = '0';
                if (submitButton) submitButton.disabled = false;
            }
        });
    }

    $$('[data-household-nav]').forEach((button) => {
        button.addEventListener('click', () => showHouseholdView(button.dataset.householdNav));
    });

    $$('[data-history-period]').forEach((button) => {
        button.addEventListener('click', () => {
            const nextPeriod = button.dataset.historyPeriod;
            if (!['daily', 'weekly', 'monthly'].includes(nextPeriod)) return;

            historyPeriod = nextPeriod;
            historyState.period = nextPeriod;
            historyState.page = 1;
            $$('[data-history-period]').forEach((tab) => {
                const isActive = tab === button;
                tab.classList.toggle('is-active', isActive);
                tab.setAttribute('aria-selected', String(isActive));
            });
            renderHistorySummary();
            loadConsumptionHistory();
        });
    });

    $$('[data-admin-nav]').forEach((button) => {
        button.addEventListener('click', () => showAdminView(button.dataset.adminNav));
    });

    if (adminSearch && page === 'admin') {
        adminSearch.addEventListener('input', () => {
            const query = adminSearch.value.trim().toLocaleLowerCase();
            const buttons = $$('[data-admin-nav]');

            buttons.forEach((button) => {
                const matches = !query || button.textContent.trim().toLocaleLowerCase().includes(query);
                button.hidden = !matches;
            });

            if (!query) {
                return;
            }

            const firstMatch = buttons.find((button) => !button.hidden);
            if (firstMatch) {
                showAdminView(firstMatch.dataset.adminNav);
            }
        });
    }

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

    if (historyFilterForm && !historyFilterForm.dataset.bound) {
        historyFilterForm.dataset.bound = '1';
        historyFilterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const fields = serializeForm(historyFilterForm);
            historyState.page = 1;
            historyState.period = historyPeriod;
            historyState.from = fields.from ?? '';
            historyState.to = fields.to ?? '';
            historyState.search = fields.search ?? '';
            historyState.per_page = Number(fields.per_page ?? 10);
            loadConsumptionHistory();
        });
        historyFilterForm.addEventListener('reset', () => {
            window.setTimeout(() => {
                historyState.page = 1;
                historyState.from = '';
                historyState.to = '';
                historyState.search = '';
                historyState.per_page = 10;
                loadConsumptionHistory();
            }, 0);
        });
    }

    $$('[data-history-export-csv]').forEach((button) => {
        button.addEventListener('click', () => {
            const items = consumptionHistory[historyPeriod] ?? [];
            exportHistoryCsv(items);
        });
    });

    $$('[data-history-export-pdf]').forEach((button) => {
        button.addEventListener('click', () => {
            const pdfUrl = `/api/exports/readings${buildQuery({
                from: historyState.from,
                to: historyState.to,
            })}`;
            window.open(pdfUrl, '_blank');
        });
    });

    refreshButton?.addEventListener('click', () => {
        if (page === 'household') {
            loadDashboard({ manual: true });
        }

        if (page === 'admin') {
            if (refreshButton) {
                refreshButton.disabled = true;
                refreshButton.dataset.defaultText = refreshButton.dataset.defaultText || refreshButton.innerHTML;
                refreshButton.innerHTML = '<i class="bi bi-arrow-repeat" aria-hidden="true"></i> Refreshing...';
            }
            loadAdmin().finally(() => {
                if (refreshButton) {
                    refreshButton.disabled = false;
                    refreshButton.innerHTML = refreshButton.dataset.defaultText || 'Refresh';
                }
            });
        }
    });

    householdAnalyticsRange?.addEventListener('change', () => {
        setHouseholdCustomRangeOpen(householdAnalyticsRange.value === 'custom');
        householdAnalyticsLoadedRange = '';
        if (householdAnalyticsRange.value === 'custom'
            && (!householdAnalyticsFrom?.value || !householdAnalyticsTo?.value)) return;
        loadDashboard();
    });

    householdAnalyticsRange?.addEventListener('pointerdown', () => {
        setHouseholdCustomRangeOpen(false);
    });

    householdAnalyticsCustomToggle?.addEventListener('click', () => {
        if (householdAnalyticsRange.value !== 'custom') {
            householdAnalyticsRange.value = 'custom';
            householdAnalyticsLoadedRange = '';
        }
        setHouseholdCustomRangeOpen(householdAnalyticsCustom.hidden);
    });

    [householdAnalyticsFrom, householdAnalyticsTo].forEach((input) => input?.addEventListener('change', () => {
        householdAnalyticsLoadedRange = '';
        if (!householdAnalyticsFrom?.value || !householdAnalyticsTo?.value) return;
        if (householdAnalyticsFrom.value > householdAnalyticsTo.value) {
            setDashboardFeedback('The start date must be before the end date.', 'error');
            return;
        }
        loadDashboard();
    }));

    document.addEventListener('click', (event) => {
        const rangeControl = householdAnalyticsRange?.closest('.analytics-range-control');
        if (!rangeControl || rangeControl.contains(event.target) || householdAnalyticsRange?.value !== 'custom') return;
        setHouseholdCustomRangeOpen(false);
    });

    adminAnalyticsRange?.addEventListener('change', () => {
        updateCustomAnalyticsRange(adminAnalyticsRange, adminAnalyticsCustom);
        if (adminAnalyticsRange.value === 'custom' && (!adminAnalyticsFrom?.value || !adminAnalyticsTo?.value)) return;
        loadAdmin();
    });

    [adminAnalyticsFrom, adminAnalyticsTo].forEach((input) => input?.addEventListener('change', () => {
        if (adminAnalyticsRange?.value === 'custom' && (!adminAnalyticsFrom?.value || !adminAnalyticsTo?.value)) return;
        loadAdmin();
    }));

    const clearNotificationsButton = $('[data-clear-notifications]');
    clearNotificationsButton?.addEventListener('click', async () => {
        if (!window.confirm('Clear every notification from your inbox?')) return;
        try {
            const response = await requestJson('/api/notifications/received/all', { method: 'DELETE' });
            showToast(response.message ?? 'All notifications cleared successfully.');
            loadDashboard();
            loadNotifications();
        } catch (error) {
            if (!handleAuthFailure(error)) showToast(errorMessage(error, 'Could not clear notifications.'), 'error');
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    bootCommon();

    if (page === 'home') {
        bootHomeSessionActions();
    }

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

window.addEventListener('pagehide', () => {
    stopDashboardPolling();
    stopAdminDevicePolling();
    stopAdminLeakPolling();
});
