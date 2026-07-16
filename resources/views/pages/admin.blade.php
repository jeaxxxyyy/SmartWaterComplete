@extends('layouts.app')

@section('title', 'Admin | Smart Water Monitoring')
@section('page', 'admin')
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
    <button type="button" data-logout>Logout</button>
@endsection

@section('content')
    <section class="dashboard-view is-active" data-admin-view="admin-dashboard">
        <section class="panel panel-hero admin-hero">
            <button type="button" class="button button-ghost admin-theme-toggle" data-theme-toggle aria-pressed="false">Use dark theme</button>
            <div class="hero-copy">
                <p class="eyebrow">Admin Functionalities</p>
                <h1>Run the Smart Water Monitoring System from a single operations console.</h1>
                <p class="lede">This area is organized around the administrator requirements: customer records, devices, billing rules, consumption monitoring, leaks, reports, and announcements.</p>
            </div>

            <div class="hero-metrics">
                <div class="mini-card">
                    <span class="mini-label">Registered users</span>
                    <strong data-admin-households>--</strong>
                    <small>Household accounts in the system.</small>
                </div>
                <div class="mini-card">
                    <span class="mini-label">Registered devices</span>
                    <strong class="mint" data-admin-devices>--</strong>
                    <small>Device accounts assigned to customers.</small>
                </div>
                <div class="mini-card">
                    <span class="mini-label">Online devices</span>
                    <strong class="amber" data-admin-online-devices>--</strong>
                    <small>Devices with active tokens.</small>
                </div>
                <div class="mini-card">
                    <span class="mini-label">Offline devices</span>
                    <strong class="rose" data-admin-offline-devices>--</strong>
                    <small>Devices without active tokens.</small>
                </div>
            </div>
        </section>

        <section class="stat-grid">
            <article class="stat-card">
                <span class="stat-label">Total consumption</span>
                <strong data-admin-liters>--</strong>
                <small>Current month water usage.</small>
            </article>
            <article class="stat-card">
                <span class="stat-label">Estimated billing</span>
                <strong class="amber" data-admin-billing-total>--</strong>
                <small>Calculated from current usage.</small>
            </article>
            <article class="stat-card">
                <span class="stat-label">Unread alerts</span>
                <strong class="rose" data-admin-alert-count>--</strong>
                <small>Latest unresolved notification count.</small>
            </article>
            <article class="stat-card">
                <span class="stat-label">Summary</span>
                <strong data-admin-summary>--</strong>
                <small>System snapshot for quick review.</small>
            </article>
        </section>

        <article class="panel chart-panel">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Monthly Trend</p>
                    <h2>Total water usage for all users</h2>
                </div>
                <span class="status-pill">System overview</span>
            </div>
            <div class="chart-legend">
                <span><i class="legend-dot usage-dot"></i>m³</span>
            </div>
            <div class="usage-chart single-series" data-admin-monthly-chart>
                <div class="empty-state">No usage data yet.</div>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-admin-view="customer-management">
        <section class="admin-grid">
            <article class="panel admin-card">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Customer Management</p>
                        <h2>Register, update, deactivate, and assign accounts</h2>
                    </div>
                </div>
                <div class="table">
                    <div class="table-head admin-table-head">
                        <span>Name</span>
                        <span>Email</span>
                        <span>Role</span>
                        <span>Status</span>
                        <span>Actions</span>
                    </div>
                    <div class="table-body" data-admin-users>
                        <div class="empty-state">Connect an admin token to load users.</div>
                    </div>
                </div>
                <div class="pager" data-admin-users-pager></div>
            </article>

            <article class="panel admin-card">
                <p class="eyebrow">Register New Customer</p>
                <h2>Create a household or device account</h2>
                <form class="stack-form" data-admin-user-form>
                    <input type="text" name="name" placeholder="Name" required>
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <select name="role" required>
                        <option value="user">Household</option>
                        <option value="admin">Admin</option>
                        <option value="device">Device</option>
                    </select>
                    <select name="assigned_user_id" data-household-select>
                        <option value="">Assign device to household (devices only)</option>
                    </select>
                    <input type="text" name="address" placeholder="Address">
                    <input type="text" name="phone" placeholder="Phone">
                    <button class="button button-primary" type="submit">Create user</button>
                </form>
            </article>
        </section>
    </section>

    <section class="dashboard-view" data-admin-view="device-management">
        <section class="admin-grid">
            <article class="panel admin-card">
                <p class="eyebrow">Device Management</p>
                <h2>Meters and Connection Health</h2>
                <div class="notification-list" data-municipal-devices>
                    <div class="empty-state">No registered meters loaded yet.</div>
                </div>
            </article>

            <article class="panel admin-card">
                <p class="eyebrow">Register M5Stack</p>
                <h2>Assign a meter to a household</h2>
                <form class="stack-form" data-device-register-form>
                    <select name="household_id" required data-device-household-select>
                        <option value="">Choose household</option>
                    </select>
                    <input type="text" name="asset_number" placeholder="Municipal asset no. (MWM-2026-0002)" required>
                    <input type="text" name="serial_number" placeholder="Manufacturer serial no." required>
                    <input type="text" name="name" placeholder="Meter name" value="M5Stack CoreS3 Household Meter" required>
                    <input type="text" name="installation_address" placeholder="Installation address">
                    <label>Installation date <input type="date" name="installed_at"></label>
                    <textarea name="notes" placeholder="Installation or maintenance notes"></textarea>
                    <button class="button button-primary" type="submit">Register and create activation code</button>
                </form>
                <div class="notification-list" data-activation-result></div>
            </article>
        </section>
    </section>

    <section class="dashboard-view" data-admin-view="water-rates">
        <article class="panel admin-card">
            <p class="eyebrow">Water Rate Management</p>
            <h2>Update rates without changing source code</h2>
            <form class="stack-form" data-billing-form>
                <label>
                    Water rate per m³
                    <input type="number" step="0.01" name="water_rate" placeholder="Example: 40.00" required>
                </label>
                <label>
                    Fixed charges / subsidy deduction
                    <input type="number" step="0.01" name="subsidy_deduction" placeholder="Example: 50.00" required>
                </label>
                <label>
                    Monthly usage threshold in liters
                    <input type="number" step="0.01" name="threshold_liters" placeholder="Example: 10000" required>
                </label>
                <label>
                    Leak flow threshold
                    <input type="number" step="0.01" name="leak_flow_rate_threshold" placeholder="Example: 30.00" required>
                </label>
                <label>
                    Quiet hours start
                    <input type="number" name="quiet_hours_start" placeholder="0 to 23" required>
                </label>
                <label>
                    Quiet hours end
                    <input type="number" name="quiet_hours_end" placeholder="0 to 23" required>
                </label>
                <button class="button button-primary" type="submit">Save billing</button>
            </form>
        </article>
    </section>

    <section class="dashboard-view" data-admin-view="billing-management">
        <article class="panel admin-card">
            <p class="eyebrow">Billing Management</p>
            <h2>Estimated billing records and summaries</h2>
            <div class="summary-list" data-admin-billing-summary>
                <div><dt>Loading</dt><dd>--</dd></div>
            </div>
            <div class="token-controls">
                <button class="button button-ghost" type="button" data-pdf-export="/api/exports/billing-statements">Export billing records</button>
                <button class="button button-ghost" type="button" data-pdf-export="/api/exports/readings">Export reading history</button>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-admin-view="consumption-monitoring">
        <section class="panel filter-panel">
            <div>
                <p class="eyebrow">Water Consumption Monitoring</p>
                <h2>Search users and filter consumption records</h2>
            </div>
            <form class="filter-bar" data-user-filters>
                <input type="text" name="search" class="filter-input" placeholder="Search name, email, or account">
                <select name="role" class="filter-input">
                    <option value="">All roles</option>
                    <option value="user">Household</option>
                    <option value="admin">Admin</option>
                    <option value="device">Device</option>
                </select>
                <select name="is_active" class="filter-input">
                    <option value="">Any status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
                <select name="per_page" class="filter-input">
                    <option value="5">5 per page</option>
                    <option value="10" selected>10 per page</option>
                    <option value="25">25 per page</option>
                </select>
                <button class="button button-primary" type="submit">Apply</button>
            </form>
        </section>

        <article class="panel admin-card">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Consumption Records</p>
                    <h2>Matching accounts and usage</h2>
                </div>
            </div>
            <div class="table">
                <div class="table-head admin-table-head">
                    <span>Name</span>
                    <span>Email</span>
                    <span>Role</span>
                    <span>Status</span>
                    <span>Actions</span>
                </div>
                <div class="table-body" data-admin-users>
                    <div class="empty-state">Connect an admin token to load users.</div>
                </div>
            </div>
            <div class="pager" data-admin-users-pager></div>
        </article>
    </section>

    <section class="dashboard-view" data-admin-view="leak-monitoring">
        <article class="panel admin-card">
            <p class="eyebrow">Leak Monitoring</p>
            <h2>Affected household, latest flow, daily notice, and resolution state</h2>
            <div class="notification-list" data-admin-alerts>
                <div class="empty-state">Connect an admin token to load alerts.</div>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-admin-view="reports">
        <article class="panel admin-card">
            <p class="eyebrow">Reports</p>
            <h2>Daily, weekly, monthly, billing, and consumption reports</h2>
            <div class="summary-list" data-admin-reports>
                <div><dt>Loading</dt><dd>--</dd></div>
            </div>
            <div class="token-controls">
                <button class="button button-ghost" type="button" data-pdf-export="/api/exports/readings">Export readings PDF</button>
                <button class="button button-ghost" type="button" data-pdf-export="/api/exports/billing-statements">Export billing PDF</button>
                <button class="button button-ghost" type="button" data-pdf-export="/api/exports/notifications">Export notifications PDF</button>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-admin-view="notifications">
        <article class="panel admin-card">
            <p class="eyebrow">Notification Management</p>
            <h2>Service interruptions, maintenance, and announcements</h2>
            <form class="stack-form" data-announcement-form>
                <select name="type" required>
                    <option value="announcement">General announcement</option>
                    <option value="maintenance">Maintenance schedule</option>
                    <option value="service_interruption">Service interruption</option>
                </select>
                <select name="user_id" data-announcement-user>
                    <option value="">All active households</option>
                </select>
                <input type="text" name="title" placeholder="Title" required>
                <textarea name="message" placeholder="Message" required></textarea>
                <button class="button button-primary" type="submit">Send notification</button>
                <button class="button button-ghost" type="button" data-delete-all-sent-notifications>Delete all sent notifications</button>
            </form>
        </article>
        <section class="admin-grid">
            <article class="panel admin-card">
                <p class="eyebrow">Service Interruptions</p>
                <h2>Sent interruption records</h2>
                <div class="notification-list" data-admin-sent-type="service_interruption">
                    <div class="empty-state">No sent records.</div>
                </div>
            </article>
            <article class="panel admin-card">
                <p class="eyebrow">Maintenance</p>
                <h2>Sent maintenance records</h2>
                <div class="notification-list" data-admin-sent-type="maintenance">
                    <div class="empty-state">No sent records.</div>
                </div>
            </article>
            <article class="panel admin-card">
                <p class="eyebrow">Announcements</p>
                <h2>Sent announcement records</h2>
                <div class="notification-list" data-admin-sent-type="announcement">
                    <div class="empty-state">No sent records.</div>
                </div>
            </article>
        </section>
    </section>
@endsection
