@extends('layouts.app')

@section('title', 'Admin | Smart Water Monitoring')
@section('page', 'admin')
@push('head')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
@endpush

@section('admin-topbar')
    <button class="admin-sidebar-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#admin-sidebar" aria-controls="admin-sidebar" aria-label="Open administrator navigation">
        <i class="bi bi-list" aria-hidden="true"></i>
    </button>
    <div class="admin-topbar-title"><span>Administrator workspace</span><strong data-admin-page-title>Dashboard</strong></div>
    <label class="admin-search" aria-label="Search administrator workspace"><i class="bi bi-search" aria-hidden="true"></i><input type="search" placeholder="Search workspace" data-admin-search></label>
    <div class="admin-topbar-actions">
        <div class="admin-notification-control" data-admin-notification-control>
            <button class="admin-icon-button admin-notification-button" type="button" data-admin-notification-toggle aria-haspopup="dialog" aria-expanded="false" aria-controls="admin-notification-dropdown" aria-label="Open administrator notifications" title="Notifications">
                <i class="bi bi-bell" aria-hidden="true"></i>
                <span class="admin-notification-badge" data-admin-alert-count aria-hidden="true" hidden></span>
            </button>
            <section class="household-notification-dropdown admin-notification-dropdown" id="admin-notification-dropdown" data-admin-notification-dropdown role="dialog" aria-label="Recent administrator notifications" hidden>
                <header class="notification-dropdown-header">
                    <div>
                        <strong>Notifications</strong>
                        <small data-admin-notification-dropdown-summary>No unread household messages</small>
                    </div>
                    <span class="notification-live-dot" aria-hidden="true"></span>
                </header>
                <div class="notification-dropdown-list" data-admin-notification-dropdown-list>
                    <div class="notification-dropdown-empty">Loading notifications...</div>
                </div>
                <button class="notification-dropdown-footer" type="button" data-admin-notification-view-all>View all notifications</button>
            </section>
        </div>
        @include('components.theme-toggle', ['class' => 'admin-icon-button'])
        <div class="admin-profile" aria-label="Administrator municipal account">
            <span class="admin-avatar">AD</span>
            <span class="admin-profile-copy"><strong data-account-role>Administrator</strong><small>Municipal Account</small></span>
        </div>
    </div>
@endsection

@section('admin-sidebar')
    <div class="admin-sidebar-brand">
        <span class="admin-sidebar-mark"><i class="bi bi-droplet-half" aria-hidden="true"></i></span>
        <div class="admin-sidebar-brand-copy"><strong>Smart Water</strong><small>Operations console</small></div>
        <button class="admin-sidebar-collapse" type="button" data-admin-sidebar-collapse aria-controls="admin-sidebar" aria-expanded="true" aria-label="Collapse administrator sidebar" title="Collapse sidebar">@include('components.ui-icon', ['name' => 'sidebar-collapse'])</button>
    </div>
    <nav class="sidebar-nav admin-sidebar-nav" aria-label="Administrator sections">
        <span class="sidebar-section-title">Dashboard</span>
        <button type="button" class="is-active" data-admin-nav="admin-dashboard" title="Overview"><i class="bi bi-grid-1x2-fill"></i><span>Overview</span></button>
        <span class="sidebar-section-title">Management</span>
        <button type="button" data-admin-nav="customer-management" title="Customer Management"><i class="bi bi-people"></i><span>Customer Management</span></button>
        <button type="button" data-admin-nav="device-management" title="Device Management"><i class="bi bi-cpu"></i><span>Device Management</span></button>
        <button type="button" data-admin-nav="water-rates" title="Water Rate Management"><i class="bi bi-droplet"></i><span>Water Rate Management</span></button>
        <button type="button" data-admin-nav="billing-management" title="Billing"><i class="bi bi-receipt"></i><span>Billing</span></button>
        <span class="sidebar-section-title">Monitoring</span>
        <button type="button" data-admin-nav="consumption-monitoring" title="Consumption Monitoring"><i class="bi bi-activity"></i><span>Consumption Monitoring</span></button>
        <button type="button" data-admin-nav="leak-monitoring" title="Leak Monitoring"><i class="bi bi-exclamation-triangle"></i><span>Leak Monitoring</span></button>
        <span class="sidebar-section-title">Insights</span>
        <button type="button" data-admin-nav="reports" title="Reports"><i class="bi bi-bar-chart-line"></i><span>Reports</span></button>
        <button type="button" data-admin-nav="notifications" title="Notifications"><i class="bi bi-megaphone"></i><span>Notifications</span></button>
    </nav>
    <button class="admin-sidebar-logout" type="button" data-logout><i class="bi bi-box-arrow-left"></i><span>Sign out</span></button>
@endsection

@section('nav')
    <button type="button" class="is-active" data-admin-nav="admin-dashboard">Dashboard</button>
    <button type="button" data-admin-nav="customer-management">Customer Management</button>
    <button type="button" data-admin-nav="device-management">Device Management</button>
    <button type="button" data-admin-nav="water-rates">Water Rate Management</button>
    <button type="button" data-admin-nav="billing-management">Billing Management</button>
    <button type="button" data-admin-nav="consumption-monitoring">Consumption Monitoring</button>
    <button type="button" data-admin-nav="leak-monitoring">Leak Monitoring</button>
    <button type="button" data-admin-nav="reports">Reports</button>
    <button type="button" data-admin-nav="notifications">Notification Management</button>
@endsection

@section('content')
    <section class="dashboard-view is-active" data-admin-view="admin-dashboard">
        <section class="panel admin-overview-header">
            <div class="admin-overview-heading">
                <div class="admin-overview-eyebrow">
                    <span class="admin-overview-dot" aria-hidden="true"></span>
                    <span>Admin Overview</span>
                </div>
                <h1>Operations Dashboard</h1>
                <p>Monitor household accounts, meters, consumption, billing, and alerts from a more focused municipal command center.</p>
            </div>
            <div class="admin-overview-controls">
                <div class="admin-refresh-control">
                    <span class="admin-refresh-label">Last refreshed: <time data-admin-last-refreshed>--</time></span>
                    <button class="button button-ghost admin-refresh-button" type="button" data-refresh aria-label="Refresh administrator overview"><i class="bi bi-arrow-clockwise admin-refresh-icon" aria-hidden="true"></i><span>Refresh</span></button>
                </div>
                <div class="admin-analytics-controls">
                    <div class="admin-analytics-period-field">
                        <label class="field-hint" for="admin-analytics-range">Analytics period</label>
                        <div class="analytics-range-control admin-analytics-range-control">
                            <select id="admin-analytics-range" class="filter-input analytics-range-select" data-admin-analytics-range aria-label="Administrator analytics period">
                                <option value="last_7_days">Last 7 days</option>
                                <option value="last_30_days">Last 30 days</option>
                                <option value="last_3_months">Last 3 months</option>
                                <option value="last_12_months" selected>Last 12 months</option>
                                <option value="custom">Custom range</option>
                            </select>
                            <span class="analytics-custom-range admin-analytics-custom-range" data-admin-analytics-custom hidden>
                                <strong>Choose a custom period</strong>
                                <label><span>From</span><input type="date" class="filter-input" data-admin-analytics-from aria-label="Analytics start date"></label>
                                <label><span>To</span><input type="date" class="filter-input" data-admin-analytics-to aria-label="Analytics end date"></label>
                            </span>
                        </div>
                    </div>
                    <small class="field-hint admin-analytics-timezone">Timezone: <span data-admin-analytics-timezone>UTC</span></small>
                </div>
            </div>
        </section>

        <section class="admin-overview-section">
            <div class="panel-head overview-section-heading">
                <div>
                    <p class="eyebrow">Operational snapshot</p>
                    <h2>Accounts and meter health</h2>
                </div>
                <span class="status-pill" data-admin-overview-period>Selected period</span>
            </div>
            <div class="stat-grid admin-overview-kpis">
                <article class="stat-card">
                    <div class="stat-card-top">
                        <span class="stat-card-icon" aria-hidden="true"><i class="bi bi-people"></i></span>
                        <span class="stat-card-badge">Customers</span>
                    </div>
                    <span class="stat-label">Household accounts</span>
                    <strong data-admin-households>--</strong>
                    <small>Total household accounts.</small>
                    <button class="link-button" type="button" data-admin-nav="customer-management">View customers</button>
                </article>
                <article class="stat-card">
                    <div class="stat-card-top">
                        <span class="stat-card-icon" aria-hidden="true"><i class="bi bi-person-check"></i></span>
                        <span class="stat-card-badge">Active</span>
                    </div>
                    <span class="stat-label">Active households</span>
                    <strong data-admin-active-households>--</strong>
                    <small>Accounts currently allowed to sign in.</small>
                </article>
                <article class="stat-card">
                    <div class="stat-card-top">
                        <span class="stat-card-icon" aria-hidden="true"><i class="bi bi-person-dash"></i></span>
                        <span class="stat-card-badge">Review</span>
                    </div>
                    <span class="stat-label">Inactive households</span>
                    <strong data-admin-inactive-households>--</strong>
                    <small>Inactive accounts requiring review.</small>
                </article>
                <article class="stat-card">
                    <div class="stat-card-top">
                        <span class="stat-card-icon" aria-hidden="true"><i class="bi bi-slash-circle"></i></span>
                        <span class="stat-card-badge">Blocked</span>
                    </div>
                    <span class="stat-label">Suspended households</span>
                    <strong class="rose" data-admin-suspended-households>--</strong>
                    <small>Accounts blocked by account status.</small>
                </article>
                <article class="stat-card">
                    <div class="stat-card-top">
                        <span class="stat-card-icon" aria-hidden="true"><i class="bi bi-cpu"></i></span>
                        <span class="stat-card-badge">Meters</span>
                    </div>
                    <span class="stat-label">Registered meters</span>
                    <strong class="mint" data-admin-devices>--</strong>
                    <small>Non-decommissioned physical meters.</small>
                    <button class="link-button" type="button" data-admin-nav="device-management">View meters</button>
                </article>
                <article class="stat-card">
                    <div class="stat-card-top">
                        <span class="stat-card-icon" aria-hidden="true"><i class="bi bi-wifi"></i></span>
                        <span class="stat-card-badge">Online</span>
                    </div>
                    <span class="stat-label">Online meters</span>
                    <strong class="amber" data-admin-online-devices>--</strong>
                    <small>Reporting within the health window.</small>
                </article>
                <article class="stat-card">
                    <div class="stat-card-top">
                        <span class="stat-card-icon" aria-hidden="true"><i class="bi bi-wifi-off"></i></span>
                        <span class="stat-card-badge">Offline</span>
                    </div>
                    <span class="stat-label">Offline meters</span>
                    <strong class="rose" data-admin-offline-devices>--</strong>
                    <small>Active meters outside the health window.</small>
                </article>
                <article class="stat-card">
                    <div class="stat-card-top">
                        <span class="stat-card-icon" aria-hidden="true"><i class="bi bi-play-circle"></i></span>
                        <span class="stat-card-badge">Pending</span>
                    </div>
                    <span class="stat-label">Pending activation</span>
                    <strong data-admin-pending-devices>--</strong>
                    <small>Registered meters awaiting activation.</small>
                </article>
                <article class="stat-card">
                    <div class="stat-card-top">
                        <span class="stat-card-icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
                        <span class="stat-card-badge">Awaiting</span>
                    </div>
                    <span class="stat-label">Waiting for first reading</span>
                    <strong data-admin-waiting-devices>--</strong>
                    <small>Meters with no valid reading yet.</small>
                </article>
            </div>
        </section>

        <section class="stat-grid admin-overview-period-stats">
            <article class="stat-card">
                <span class="stat-label">Selected-period consumption</span>
                <strong data-admin-liters>--</strong>
                <small>Real water usage for the selected analytics period.</small>
            </article>
            <article class="stat-card">
                <span class="stat-label">Selected-period billing</span>
                <strong class="amber" data-admin-billing-total>--</strong>
                <small>Estimated billing from recorded readings.</small>
            </article>
            <article class="stat-card">
                <span class="stat-label">Unresolved leak alerts</span>
                <strong class="rose" data-admin-unresolved-leaks>--</strong>
                <small>Detected, acknowledged, or investigating.</small>
                <button class="link-button" type="button" data-admin-nav="leak-monitoring">Review leaks</button>
            </article>
            <article class="stat-card">
                <span class="stat-label">Unread notifications</span>
                <strong class="rose" data-admin-unread-notifications>--</strong>
                <small>Unread notices for household accounts.</small>
                <button class="link-button" type="button" data-admin-nav="notifications">Open notifications</button>
            </article>
        </section>

        <article class="panel admin-overview-system-health" aria-labelledby="admin-system-health-title">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">System diagnostics</p>
                    <h2 id="admin-system-health-title">Application and data health</h2>
                </div>
                <span class="status-pill" data-admin-system-health-status>Checking</span>
            </div>
            <div class="stat-grid">
                <article class="stat-card">
                    <span class="stat-label">Database</span>
                    <strong data-admin-system-database>Checking</strong>
                    <small>Connection and foreign-key enforcement.</small>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Latest telemetry</span>
                    <strong data-admin-system-telemetry>Checking</strong>
                    <small data-admin-system-telemetry-detail>Waiting for diagnostics.</small>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Relational integrity</span>
                    <strong data-admin-system-integrity>Checking</strong>
                    <small>Orphan and device ownership checks.</small>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Failed background jobs</span>
                    <strong data-admin-system-failed-jobs>Checking</strong>
                    <small>Queued work that requires attention.</small>
                </article>
            </div>
        </article>

        <article class="panel chart-panel admin-overview-trend">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Consumption trend</p>
                    <h2>Total water usage over the last 12 months</h2>
                </div>
                <span class="status-pill">m³</span>
            </div>
            <div class="chart-legend">
                <span><i class="legend-dot usage-dot"></i>m³</span>
            </div>
            <div class="usage-chart single-series" data-admin-monthly-chart>
                <div class="empty-state">No usage data yet.</div>
            </div>
        </article>

        <section class="admin-overview-lower-grid">
            <article class="panel admin-overview-list-panel">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Needs attention</p>
                        <h2>Operational follow-ups</h2>
                    </div>
                </div>
                <div class="admin-overview-list" data-admin-attention-list>
                    <div class="dashboard-empty-state is-loading admin-overview-empty-state">
                        <div class="loading-skeleton-card loading-skeleton-card-inline" aria-hidden="true">
                            <span class="loading-skeleton-line loading-skeleton-line-lg"></span>
                            <span class="loading-skeleton-line"></span>
                        </div>
                        <strong>Loading operational follow-ups...</strong>
                        <span>Gathering device alerts and account issues to keep the operations queue current.</span>
                    </div>
                </div>
            </article>
            <article class="panel admin-overview-list-panel">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Recent activity</p>
                        <h2>Administrative and device events</h2>
                    </div>
                    <button class="link-button" type="button" data-admin-nav="reports">View reports</button>
                </div>
                <div class="admin-overview-list" data-admin-activity-list>
                    <div class="dashboard-empty-state is-loading admin-overview-empty-state">
                        <div class="loading-skeleton-card loading-skeleton-card-inline" aria-hidden="true">
                            <span class="loading-skeleton-line loading-skeleton-line-lg"></span>
                            <span class="loading-skeleton-line"></span>
                        </div>
                        <strong>Loading recent activity...</strong>
                        <span>Pulling in recent administrative and device events for review.</span>
                    </div>
                </div>
            </article>
        </section>
    </section>

    <section class="dashboard-view" data-admin-view="customer-management">
        <section class="admin-grid customer-management-layout admin-workspace-layout">
            <article class="panel admin-card customer-management-table-card">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Customer Management</p>
                        <h2>Household accounts</h2>
                        <p class="field-hint">Search household contacts, account numbers, addresses, or assigned meters.</p>
                    </div>
                    <span class="status-pill status-pill-soft">Household users only</span>
                </div>
                <form class="filter-bar admin-customer-filters" data-household-user-filters>
                    <input type="text" name="search" class="filter-input" placeholder="Name, email, account, address, or meter" aria-label="Search household accounts">
                    <select name="account_status" class="filter-input" aria-label="Filter household account status">
                        <option value="">All account statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                        <option value="archived">Archived</option>
                    </select>
                    <select name="per_page" class="filter-input" aria-label="Household accounts per page">
                        <option value="5">5 per page</option>
                        <option value="10" selected>10 per page</option>
                        <option value="25">25 per page</option>
                    </select>
                    <button class="button button-primary" type="submit">Apply filters</button>
                </form>
                <div class="customer-management-table-meta">
                    <span class="table-secondary" data-household-user-summary>-- household accounts</span>
                    <span class="field-hint">Use Manage to update access or meter assignment.</span>
                </div>
                <div class="customer-management-table-shell">
                    <div class="table customer-management-table">
                        <div class="table-head admin-table-head">
                            <span>Name</span>
                            <span>Contact</span>
                            <span>Device</span>
                            <span>Account Status</span>
                            <span>Actions</span>
                        </div>
                        <div class="table-body" data-admin-household-users>
                            <div class="empty-state">Loading household users...</div>
                        </div>
                    </div>
                </div>
                <div class="pager" data-admin-household-users-pager></div>
            </article>

            <article class="panel admin-card customer-management-form-card">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Register New Customer</p>
                        <h2>Create a household user account</h2>
                    </div>
                    <span class="status-pill status-pill-soft">Admin only</span>
                </div>
                <p class="field-hint customer-management-form-intro">Create a household login with its contact details. Meter assignment is managed separately from this form.</p>
                <div class="form-feedback" data-admin-user-form-status role="status" aria-live="polite" hidden></div>
                <form class="stack-form" data-admin-user-form novalidate>
                    <label class="admin-form-field">
                        Full name <span class="required-mark" aria-hidden="true">*</span>
                        <input type="text" name="name" placeholder="Name" autocomplete="name" aria-describedby="admin-user-name-error" required>
                        <small class="field-error" id="admin-user-name-error" data-admin-user-field-error="name"></small>
                    </label>
                    <label class="admin-form-field">
                        Email address <span class="required-mark" aria-hidden="true">*</span>
                        <input type="email" name="email" placeholder="Email" autocomplete="email" aria-describedby="admin-user-email-error" required>
                        <small class="field-error" id="admin-user-email-error" data-admin-user-field-error="email"></small>
                    </label>
                    <label class="admin-form-field">
                        Temporary password <span class="required-mark" aria-hidden="true">*</span>
                        <input type="password" name="password" placeholder="At least 8 characters" autocomplete="new-password" aria-describedby="admin-user-password-error" minlength="8" required>
                        <small class="field-error" id="admin-user-password-error" data-admin-user-field-error="password"></small>
                    </label>
                    <label class="admin-form-field">
                        House number, street, or purok <span class="required-mark" aria-hidden="true">*</span>
                        <input type="text" name="address" placeholder="Example: Purok 1" autocomplete="address-line1" aria-describedby="admin-user-address-error" required>
                        <small class="field-error" id="admin-user-address-error" data-admin-user-field-error="address"></small>
                    </label>
                    <label class="admin-form-field">
                        Barangay <span class="required-mark" aria-hidden="true">*</span>
                        <select name="barangay" autocomplete="address-level3" aria-describedby="admin-user-barangay-error" required>
                            <option value="">Select a barangay</option>
                            @foreach (config('municipality.barangays', []) as $barangay)
                                <option value="{{ $barangay }}">{{ $barangay }}</option>
                            @endforeach
                        </select>
                        <small class="field-error" id="admin-user-barangay-error" data-admin-user-field-error="barangay"></small>
                    </label>
                    <label class="admin-form-field">
                        Phone <span class="field-hint">optional</span>
                        <input type="tel" name="phone" placeholder="Phone" autocomplete="tel">
                    </label>
                    <button class="button button-primary" type="submit" data-admin-user-submit>Create household user</button>
                </form>
                <div class="customer-management-form-note">
                    <strong>Account safety</strong>
                    <span>Only eligible Household Users appear in this page. Administrator, device, and service accounts are excluded.</span>
                </div>
            </article>
        </section>

        <dialog class="admin-user-dialog" data-admin-household-dialog aria-labelledby="admin-household-dialog-title">
            <form class="stack-form" data-admin-household-edit-form novalidate>
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Household account</p>
                        <h2 id="admin-household-dialog-title">Manage customer</h2>
                    </div>
                    <button class="link-button" type="button" data-admin-household-dialog-close>Close</button>
                </div>
                <div class="form-feedback" data-admin-household-edit-status role="status" aria-live="polite" hidden></div>
                <input type="hidden" name="user_id">
                <label class="admin-form-field">
                    Full name <span class="required-mark" aria-hidden="true">*</span>
                    <input type="text" name="name" autocomplete="name" required>
                    <small class="field-error" data-admin-user-field-error="name"></small>
                </label>
                <label class="admin-form-field">
                    Email address <span class="required-mark" aria-hidden="true">*</span>
                    <input type="email" name="email" autocomplete="email" required>
                    <small class="field-error" data-admin-user-field-error="email"></small>
                </label>
                <label class="admin-form-field">
                    House number, street, or purok <span class="required-mark" aria-hidden="true">*</span>
                    <input type="text" name="address" autocomplete="address-line1" required>
                    <small class="field-error" data-admin-user-field-error="address"></small>
                </label>
                <label class="admin-form-field">
                    Barangay <span class="required-mark" aria-hidden="true">*</span>
                    <select name="barangay" autocomplete="address-level3" required>
                        <option value="">Select a barangay</option>
                        @foreach (config('municipality.barangays', []) as $barangay)
                            <option value="{{ $barangay }}">{{ $barangay }}</option>
                        @endforeach
                    </select>
                    <small class="field-error" data-admin-user-field-error="barangay"></small>
                </label>
                <label class="admin-form-field">
                    Phone <span class="field-hint">optional</span>
                    <input type="tel" name="phone" autocomplete="tel">
                </label>
                <label class="admin-form-field">
                    New temporary password <span class="field-hint">optional</span>
                    <input type="password" name="password" autocomplete="new-password" minlength="8">
                    <small class="field-hint">Leave blank to keep the current password.</small>
                    <small class="field-error" data-admin-user-field-error="password"></small>
                </label>
                <label class="admin-form-field">
                    Confirm new temporary password <span class="field-hint">optional</span>
                    <input type="password" name="password_confirmation" autocomplete="new-password" minlength="8">
                    <small class="field-error" data-admin-user-field-error="password_confirmation"></small>
                </label>
                <label class="admin-form-field">
                    Account Status
                    <select name="account_status" required>
                        <option value="active">Active - household can sign in</option>
                        <option value="inactive">Inactive - sign-in disabled</option>
                        <option value="suspended">Suspended - access temporarily blocked</option>
                        <option value="archived">Archived - account retained for records</option>
                    </select>
                    <small class="field-hint">Only active household accounts can sign in. Setting any other status revokes existing login sessions.</small>
                </label>
                <label class="admin-form-field">
                    Assigned water meter
                    <select name="device_id" data-household-device-select>
                        <option value="">Keep current assignment</option>
                    </select>
                    <small class="field-hint">Assign, change, or unassign a meter. Changing an assignment revokes device credentials and requires activation again.</small>
                </label>
                <div class="dialog-actions">
                    <button class="button button-ghost" type="button" data-admin-household-dialog-cancel>Cancel</button>
                    <button class="button button-primary" type="submit" data-admin-household-edit-submit>Save changes</button>
                </div>
            </form>
        </dialog>
    </section>

    <section class="dashboard-view" data-admin-view="device-management">
        <section class="admin-grid admin-device-layout admin-workspace-layout">
            <article class="panel admin-card admin-device-list-card">
                <p class="eyebrow">Device Management</p>
                <h2>Meters and Connection Health</h2>
                <p class="field-hint">Online means the meter has reported telemetry within the configured health window.</p>
                <div class="notification-list" data-municipal-devices>
                    <div class="empty-state">No registered meters loaded yet.</div>
                </div>
            </article>

            <article class="panel admin-card admin-device-form-card">
                <p class="eyebrow">Register M5Stack</p>
                <h2>Assign a meter to a household</h2>
                <form class="stack-form admin-device-register-form" data-device-register-form autocomplete="off">
                    <label>
                        <span class="admin-field-label">Household <span class="required-mark" aria-hidden="true">*</span></span>
                        <select name="household_id" required data-device-household-select aria-describedby="device-household-status">
                            <option value="">Choose household</option>
                        </select>
                    </label>
                    <small class="field-hint" id="device-household-status" data-device-household-status role="status" aria-live="polite"></small>
                    <label>
                        <span class="admin-field-label">Asset number <span class="required-mark" aria-hidden="true">*</span></span>
                        <input type="text" name="asset_number" placeholder="Municipal asset no. (MWM-2026-0002)" maxlength="100" required>
                    </label>
                    <label>
                        <span class="admin-field-label">M5Stack serial number <span class="required-mark" aria-hidden="true">*</span></span>
                        <input type="text" name="serial_number" placeholder="Manufacturer serial no." maxlength="150" required>
                    </label>
                    <label>
                        <span class="admin-field-label">Meter name <span class="required-mark" aria-hidden="true">*</span></span>
                        <input type="text" name="name" placeholder="Meter name" value="M5Stack CoreS3 Household Meter" maxlength="150" required>
                    </label>
                    <label>
                        <span class="admin-field-label">Installation address <span class="field-hint">optional</span></span>
                        <input type="text" name="installation_address" placeholder="Installation address" maxlength="255">
                    </label>
                    <label><span class="admin-field-label">Installation date</span><input type="date" name="installed_at"></label>
                    <label>
                        <span class="admin-field-label">Notes <span class="field-hint">optional</span></span>
                        <textarea name="notes" placeholder="Installation or maintenance notes" maxlength="2000"></textarea>
                    </label>
                    <button class="button button-primary" type="submit">Register and create activation code</button>
                </form>
                <div class="notification-list" data-activation-result></div>
            </article>
        </section>
    </section>

    <section class="dashboard-view" data-admin-view="water-rates">
        <section class="panel admin-card water-rate-management-header">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Water Rate Management</p>
                    <h1>Billing and monitoring configuration</h1>
                    <p class="field-hint">Manage financial rates, household usage alerts, and leak detection independently.</p>
                </div>
                <span class="status-pill status-pill-soft">Administrator only</span>
            </div>
            <div class="summary-list water-rate-current-summary" data-water-rate-current-summary>
                <div><dt>Current water rate</dt><dd>--</dd></div>
                <div><dt>Effective date</dt><dd>--</dd></div>
                <div><dt>Currency</dt><dd>--</dd></div>
                <div><dt>Leak monitoring</dt><dd>--</dd></div>
            </div>
        </section>

        <section class="admin-water-rate-grid">
            <article class="panel admin-card water-rate-section-card">
                <div class="panel-head"><div><p class="eyebrow">Billing Rates</p><h2>Billing configuration</h2></div><i class="bi bi-receipt-cutoff admin-section-icon" aria-hidden="true"></i></div>
                <p class="field-hint">These values are used for estimated household billing and billing statements.</p>
                <div class="form-feedback" data-billing-rates-status role="status" aria-live="polite" hidden></div>
                <form class="stack-form" data-billing-rates-form novalidate>
                    <label>Water rate per m³ <span class="required-mark" aria-hidden="true">*</span><div class="input-with-currency"><span data-rate-currency>PHP</span><input type="number" step="0.01" min="0" name="water_rate" placeholder="40.00" required></div></label>
                    <label>Fixed monthly charge <span class="required-mark" aria-hidden="true">*</span><div class="input-with-currency"><span data-rate-currency>PHP</span><input type="number" step="0.01" min="0" name="fixed_monthly_charge" placeholder="0.00" required></div></label>
                    <label>Subsidy or discount <span class="required-mark" aria-hidden="true">*</span><div class="input-with-currency"><span data-rate-currency>PHP</span><input type="number" step="0.01" min="0" name="subsidy_deduction" placeholder="0.00" required></div></label>
                    <div class="billing-rate-actions">
                        <label>Effective date <input type="date" name="effective_date"></label>
                        <input type="hidden" name="currency" value="PHP">
                        <button class="button button-primary" type="submit">Save billing rates</button>
                    </div>
                </form>
            </article>

            <article class="panel admin-card water-rate-section-card">
                <div class="panel-head"><div><p class="eyebrow">Consumption Alerts</p><h2>Consumption alert configuration</h2></div><i class="bi bi-graph-up-arrow admin-section-icon" aria-hidden="true"></i></div>
                <p class="field-hint">Compare actual current month-to-date usage with the same period of the previous month. Only changes that reach the configured percentage are relevant and generate an alert.</p>
                <div class="form-feedback" data-consumption-alert-status role="status" aria-live="polite" hidden></div>
                <form class="stack-form" data-consumption-alert-form novalidate>
                    <label>Relevant change percentage <span class="required-mark" aria-hidden="true">*</span><input type="number" step="0.01" min="0.01" max="10000" name="percentage_threshold" value="100" placeholder="100" required><small class="field-hint">Example: 100% increase means the current usage is twice the previous period's usage.</small></label>
                    <div class="admin-settings-note"><strong>Comparison basis</strong><span>Current month-to-date versus the equivalent days of the previous month. Both increases and decreases are evaluated from actual readings.</span></div>
                    <label class="admin-checkbox-field"><span>Alert status</span><span><input type="checkbox" name="consumption_alert_enabled" value="1" checked> Enable consumption alerts</span></label>
                    <label>Notification behavior<select name="notification_behavior" required><option value="household">Notify household</option><option value="household_admin">Notify household and administrators</option><option value="disabled">Do not send notifications</option></select></label>
                    <button class="button button-primary" type="submit">Save consumption alerts</button>
                </form>
            </article>

            <article class="panel admin-card water-rate-section-card water-rate-leak-card">
                <div class="panel-head"><div><p class="eyebrow">Leak Detection Settings</p><h2>Leak monitoring configuration</h2></div><i class="bi bi-exclamation-triangle admin-section-icon" aria-hidden="true"></i></div>
                <p class="field-hint">Configure the flow signal, required continuous duration, quiet hours, and notification behavior.</p>
                <div class="form-feedback" data-leak-settings-status role="status" aria-live="polite" hidden></div>
                <form class="stack-form" data-leak-settings-form novalidate>
                    <label>Leak-flow threshold (L/min) <span class="required-mark" aria-hidden="true">*</span><input type="number" step="0.01" min="0" name="leak_flow_rate_threshold" placeholder="30.00" required></label>
                    <label>Continuous-flow duration (minutes) <span class="required-mark" aria-hidden="true">*</span><input type="number" step="1" min="0" max="1440" name="leak_continuous_duration_minutes" placeholder="0" required></label>
                    <div class="form-grid-two"><label>Quiet-hours start <input type="number" min="0" max="23" name="quiet_hours_start" placeholder="22" required></label><label>Quiet-hours end <input type="number" min="0" max="23" name="quiet_hours_end" placeholder="4" required></label></div>
                    <label class="admin-checkbox-field"><span>Alert status</span><span><input type="checkbox" name="leak_alert_enabled" value="1" checked> Enable leak alerts</span></label>
                    <label>Leak-alert behavior<select name="leak_alert_behavior" required><option value="household">Notify household</option><option value="household_admin">Notify household and administrators</option><option value="disabled">Do not send notifications</option></select></label>
                    <button class="button button-primary" type="submit">Save leak settings</button>
                </form>
            </article>
        </section>
    </section>

    <section class="dashboard-view" data-admin-view="billing-management">
        <section class="panel admin-card billing-management-header">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Billing Management</p>
                    <h1>Household billing records</h1>
                    <p class="field-hint">Review real consumption-based estimates and finalized billing statements for Household Users.</p>
                </div>
                <span class="status-pill status-pill-soft">Administrator only</span>
            </div>
            <div class="billing-management-state" data-billing-management-state role="status" aria-live="polite">Loading billing records...</div>
        </section>

        <section class="stat-grid billing-management-summary" data-billing-management-summary aria-live="polite">
            <article class="stat-card"><span class="stat-label">Records</span><strong data-billing-total-records>--</strong><small>Estimated and finalized records in the selected period.</small></article>
            <article class="stat-card"><span class="stat-label">Finalized charges</span><strong class="amber" data-billing-finalized-charges>--</strong><small>Saved billing statements.</small></article>
            <article class="stat-card"><span class="stat-label">Estimated charges</span><strong data-billing-estimated-charges>--</strong><small>Calculated from real readings.</small></article>
            <article class="stat-card"><span class="stat-label">Outstanding balance</span><strong class="rose" data-billing-outstanding>--</strong><small>Finalized balances still due.</small></article>
        </section>

        <section class="panel filter-panel billing-management-filter-panel">
            <form class="filter-bar" data-billing-management-filters>
                <input class="filter-input" type="search" name="search" placeholder="Search household, email, account, address, or meter" aria-label="Search billing records">
                <select class="filter-input" name="bill_type" aria-label="Filter billing record type"><option value="all">All record types</option><option value="estimated">Estimated only</option><option value="finalized">Finalized only</option></select>
                <select class="filter-input" name="status" aria-label="Filter billing status"><option value="all">All statuses</option><option value="estimated">Estimated</option><option value="unpaid">Unpaid</option><option value="partial">Partial</option><option value="paid">Paid</option></select>
                <label class="billing-date-field">From<input type="date" name="from"></label>
                <label class="billing-date-field">To<input type="date" name="to"></label>
                <select class="filter-input" name="per_page" aria-label="Billing records per page"><option value="10" selected>10 per page</option><option value="25">25 per page</option><option value="50">50 per page</option></select>
                <button class="button button-primary" type="submit">Apply filters</button>
                <button class="button button-ghost" type="reset">Reset</button>
            </form>
        </section>

        <article class="panel admin-card billing-management-records-card">
            <div class="panel-head">
                <div><p class="eyebrow">Billing Records</p><h2>Estimated and finalized household bills</h2></div>
                <div class="billing-export-actions"><button class="button button-ghost" type="button" data-billing-export="/api/exports/billing-statements" hidden disabled>Export billing records</button><button class="button button-ghost" type="button" data-billing-export="/api/exports/readings" hidden disabled>Export reading history</button></div>
            </div>
            <div class="billing-management-error" data-billing-management-error role="alert" hidden></div>
            <div class="table billing-management-table">
                <div class="table-head"><span>Household</span><span>Period</span><span>Type</span><span>Consumption</span><span>Charges</span><span>Status</span><span>Actions</span></div>
                <div class="table-body" data-billing-management-records><div class="empty-state">Loading billing records...</div></div>
            </div>
            <div class="pager" data-billing-management-pager></div>
        </article>

        <dialog class="admin-user-dialog billing-detail-dialog" data-billing-detail-dialog aria-labelledby="billing-detail-title">
            <div class="panel-head"><div><p class="eyebrow">Billing calculation</p><h2 id="billing-detail-title" data-billing-detail-title>Billing details</h2></div><button class="link-button" type="button" data-billing-detail-close>Close</button></div>
            <div data-billing-detail-content><div class="empty-state">Select a billing record to view its calculation.</div></div>
        </dialog>
    </section>

    <section class="dashboard-view" data-admin-view="consumption-monitoring">
        <section class="panel filter-panel consumption-filter-panel">
            <div>
                <p class="eyebrow">Water Consumption Monitoring</p>
                <h2>Search households and filter consumption records</h2>
            </div>
            <form class="filter-bar" data-consumption-filters>
                <input type="text" name="search" class="filter-input" placeholder="Search household, email, account, address, or meter">
                <select name="account_status" class="filter-input">
                    <option value="">Any account status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                    <option value="archived">Archived</option>
                </select>
                <select name="meter_status" class="filter-input">
                    <option value="">Any meter status</option>
                    <option value="unassigned">Unassigned</option>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="suspended">Suspended</option>
                    <option value="decommissioned">Decommissioned</option>
                </select>
                <select name="usage_condition" class="filter-input">
                    <option value="">All usage conditions</option>
                    <option value="normal">Normal</option>
                    <option value="high">High usage</option>
                    <option value="leak">Leak alert</option>
                    <option value="threshold">Threshold exceeded</option>
                    <option value="no_data">No readings</option>
                </select>
                <label class="filter-date">From <input type="date" name="from" class="filter-input"></label>
                <label class="filter-date">To <input type="date" name="to" class="filter-input"></label>
                <select name="per_page" class="filter-input">
                    <option value="10" selected>10 per page</option>
                    <option value="25">25 per page</option>
                    <option value="50">50 per page</option>
                </select>
                <button class="button button-primary" type="submit">Apply</button>
                <button class="button button-ghost" type="reset">Reset</button>
            </form>
            <small class="field-hint" data-consumption-filter-status>Showing the current month. Search and filters are applied on the server.</small>
        </section>

        <article class="panel admin-card admin-leak-monitoring-card">
            <div class="summary-list consumption-monitoring-summary" data-consumption-monitoring-summary>
                <div><dt>Households</dt><dd>--</dd></div>
                <div><dt>Usage</dt><dd>--</dd></div>
                <div><dt>Estimated billing</dt><dd>--</dd></div>
                <div><dt>Abnormal households</dt><dd>--</dd></div>
            </div>
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Consumption Records</p>
                    <h2>Matching households and usage</h2>
                </div>
            </div>
            <div class="table">
                <div class="table-head admin-table-head consumption-monitoring-table">
                    <span>Household</span>
                    <span>Meter</span>
                    <span>Usage</span>
                    <span>Estimated bill</span>
                    <span>Latest reading</span>
                    <span>Condition</span>
                    <span>Details</span>
                </div>
                <div class="table-body" data-consumption-records>
                    <div class="empty-state">Loading household consumption...</div>
                </div>
            </div>
            <div class="pager" data-consumption-pager></div>
        </article>

        <dialog class="admin-user-dialog consumption-history-dialog" data-consumption-dialog aria-labelledby="consumption-dialog-title">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Consumption history</p>
                    <h2 id="consumption-dialog-title" data-consumption-dialog-title>Household details</h2>
                </div>
                <button class="link-button" type="button" data-consumption-dialog-close>Close</button>
            </div>
            <div data-consumption-history>
                <div class="empty-state">Select a household to view its consumption history.</div>
            </div>
        </dialog>
    </section>

    <section class="dashboard-view" data-admin-view="leak-monitoring">
        <article class="panel admin-card">
            <p class="eyebrow">Leak Monitoring</p>
            <h2>Affected household, latest flow, daily notice, and resolution state</h2>
            <div class="notification-list" data-admin-alerts>
                <div class="empty-state">Loading real leak alerts...</div>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-admin-view="reports">
        <section class="panel admin-card reports-header">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Reports</p>
                    <h2>Official operational reports</h2>
                    <p class="subtle">Preview real system records, then export the selected period and report category.</p>
                </div>
                <span class="status-pill" data-report-management-state>Loading report data...</span>
            </div>
        </section>

        <section class="panel filter-panel reports-filter-panel">
            <form class="filter-form reports-filter-form" data-report-management-filters>
                <label>
                    Period
                    <select name="period">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly" selected>Monthly</option>
                        <option value="custom">Custom range</option>
                    </select>
                </label>
                <label>
                    Report type
                    <select name="report_type">
                        <option value="all" selected>All operational reports</option>
                        <option value="consumption">Consumption and meter readings</option>
                        <option value="billing">Billing records</option>
                        <option value="notifications">Notifications and announcements</option>
                        <option value="leaks">Leak alerts</option>
                        <option value="devices">Device and connection health</option>
                        <option value="households">Household account summaries</option>
                        <option value="audit">Administrative activity</option>
                    </select>
                </label>
                <label>
                    From
                    <input type="date" name="from" data-report-date-from disabled>
                </label>
                <label>
                    To
                    <input type="date" name="to" data-report-date-to disabled>
                </label>
                <label class="reports-search-field">
                    Search
                    <input type="search" name="search" placeholder="Household, account, meter, or title">
                </label>
                <label>
                    Status
                    <select name="status" data-report-status>
                        <option value="">Any status</option>
                    </select>
                </label>
                <div class="filter-actions">
                    <button class="button button-primary" type="submit">Apply filters</button>
                    <button class="button button-ghost" type="button" data-report-reset>Reset</button>
                </div>
                <p class="subtle reports-period-description" data-report-period-description>Current calendar month</p>
            </form>
        </section>

        <section class="stat-grid reports-summary" data-report-summary aria-live="polite">
            <article class="stat-card"><span>Records</span><strong>—</strong></article>
            <article class="stat-card"><span>Consumption</span><strong>—</strong></article>
            <article class="stat-card"><span>Charges</span><strong>—</strong></article>
            <article class="stat-card"><span>Attention</span><strong>—</strong></article>
        </section>

        <article class="panel admin-card reports-preview-card">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Report preview</p>
                    <h2 data-report-preview-label>Operational report</h2>
                </div>
                <div class="report-preview-actions">
                    <label class="report-page-size">
                        Rows
                        <select data-report-page-size aria-label="Report preview rows per page">
                            <option value="5" selected>5 rows</option>
                            <option value="10">10 rows</option>
                            <option value="15">15 rows</option>
                            <option value="20">20 rows</option>
                            <option value="25">25 rows</option>
                        </select>
                    </label>
                    <div class="token-controls report-export-actions">
                        <button class="button button-ghost" type="button" data-report-export="/api/exports/reports" disabled hidden>Export selected report</button>
                    </div>
                </div>
            </div>
            <div class="empty-state" data-report-error hidden>
                <strong>Reports could not be loaded.</strong>
                <span data-report-error-message>Try again or adjust the selected filters.</span>
                <button class="button button-ghost" type="button" data-report-retry>Retry</button>
            </div>
            <div class="table-wrap reports-table-wrap">
                <table class="data-table reports-preview-table">
                    <thead><tr data-report-preview-head><th>Report preview</th></tr></thead>
                    <tbody data-report-preview-body><tr><td>Loading report data...</td></tr></tbody>
                </table>
            </div>
            <div class="pager" data-report-pager hidden></div>
        </article>
    </section>

    <section class="dashboard-view" data-admin-view="notifications">
        <section class="admin-grid admin-notification-layout">
            <article class="panel admin-card admin-notification-compose-card">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Notification Management</p>
                        <h2>Compose an official notice</h2>
                    </div>
                    <span class="status-pill">Household inbox</span>
                </div>
                <p class="subtle">Messages are persisted in the household notification inbox and remain available after logout or refresh.</p>
                <form class="stack-form" data-announcement-form>
                    <label>
                        Category
                        <select name="type" required>
                            <option value="announcement">General announcement</option>
                            <option value="service_interruption">Service interruption</option>
                            <option value="maintenance">Scheduled maintenance</option>
                            <option value="billing">Billing notice</option>
                            <option value="leak_alert">Leak or emergency alert</option>
                            <option value="device_system">Device or system notice</option>
                        </select>
                    </label>
                    <label>
                        Recipient audience
                        <select name="audience" data-notification-audience required>
                            <option value="all_active_households">All active households</option>
                            <option value="selected_household">Selected household</option>
                        </select>
                    </label>
                    <label data-notification-household-field hidden>
                        Household recipient
                        <select name="user_id" data-announcement-user disabled>
                            <option value="">Choose a household</option>
                        </select>
                    </label>
                    <small class="field-hint" data-notification-recipient-status role="status" aria-live="polite">Loading eligible active households...</small>
                    <label>
                        Schedule delivery <span class="field-hint">optional</span>
                        <input type="datetime-local" name="scheduled_at" data-notification-scheduled-at>
                    </label>
                    <label>
                        Title
                        <input type="text" name="title" maxlength="255" placeholder="Official notification title" required>
                    </label>
                    <label>
                        Message
                        <textarea name="message" maxlength="2000" placeholder="Write the message for the selected household audience." data-notification-message required></textarea>
                    </label>
                    <small class="field-hint notification-character-count" data-notification-character-count>0 / 2000</small>
                    <div class="button-row">
                        <button class="button button-primary" type="submit">Send notification</button>
                        <button class="button button-ghost" type="reset">Clear form</button>
                    </div>
                </form>
            </article>

            <article class="panel admin-card admin-notification-guidance">
                <p class="eyebrow">Delivery controls</p>
                <h2>Audience and delivery status</h2>
                <div class="summary-list">
                    <div><dt>Audience</dt><dd>Only active Household Users</dd></div>
                    <div><dt>Persistence</dt><dd>Database-backed inbox</dd></div>
                    <div><dt>Read tracking</dt><dd>Household read status</dd></div>
                    <div><dt>Scheduling</dt><dd>Due notices delivered automatically</dd></div>
                    <div><dt>Audit trail</dt><dd>Send, retry, archive, and delete actions</dd></div>
                </div>
                <button class="button button-ghost" type="button" data-delete-all-sent-notifications>Delete all sent records</button>
            </article>
        </section>

        <article class="panel admin-card admin-notification-history-card">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Sent-notification history</p>
                    <h2>Recipients, delivery, and read status</h2>
                </div>
                <small class="table-secondary" data-admin-notification-summary>Loading notification history...</small>
            </div>
            <form class="filter-bar admin-notification-filters" data-admin-notification-filters>
                <input type="search" name="search" class="filter-input" placeholder="Search title, message, or household" aria-label="Search sent notifications">
                <select name="type" class="filter-input" aria-label="Filter notification category">
                    <option value="">All categories</option>
                    <option value="announcement">General announcement</option>
                    <option value="service_interruption">Service interruption</option>
                    <option value="maintenance">Scheduled maintenance</option>
                    <option value="billing">Billing notice</option>
                    <option value="leak_alert">Leak or emergency alert</option>
                    <option value="device_system">Device or system notice</option>
                </select>
                <select name="status" class="filter-input" aria-label="Filter delivery status">
                    <option value="">All delivery statuses</option>
                    <option value="delivered">Delivered</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="partial">Partially delivered</option>
                    <option value="failed">Failed</option>
                </select>
                <button class="button button-primary" type="submit">Apply filters</button>
            </form>
            <div class="notification-list" data-admin-notification-history>
                <div class="empty-state">Loading sent-notification history...</div>
            </div>
            <div class="pager" data-admin-notification-pager></div>
        </article>
    </section>
@endsection
