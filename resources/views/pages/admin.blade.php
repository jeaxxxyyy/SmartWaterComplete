@extends('layouts.app')

@section('title', 'Admin | Smart Water Monitoring')
@section('page', 'admin')
@section('nav')
    <button type="button" class="is-active" data-admin-nav="admin-dashboard">Dashboard</button>
    <button type="button" data-admin-nav="manage-users">Manage Users</button>
    <button type="button" data-admin-nav="billing-settings">Billing Settings</button>
    <button type="button" data-admin-nav="device-management">Device Management</button>
    <button type="button" data-admin-nav="reports">Reports</button>
    <button type="button" data-admin-nav="alerts">Alerts</button>
    <button type="button" data-admin-nav="search-households">Search Households</button>
    <button type="button" data-logout>Logout</button>
@endsection

@section('content')
    <section class="dashboard-view is-active" data-admin-view="admin-dashboard">
        <section class="panel panel-hero">
            <div class="hero-copy">
                <p class="eyebrow">Admin Console</p>
                <h1>Manage users, billing, device tokens, and reports.</h1>
                <p class="lede">This page gives utility staff a polished control center for households, billing rules, device access, and reporting.</p>
            </div>

            <div class="hero-metrics">
                <div class="mini-card">
                    <span class="mini-label">Connection</span>
                    <strong data-status>Waiting for token</strong>
                    <small>Admin bearer token required.</small>
                </div>
                <div class="mini-card">
                    <span class="mini-label">Records</span>
                    <strong class="mint" data-admin-summary>--</strong>
                    <small>Users, readings, and alerts snapshot.</small>
                </div>
            </div>
        </section>

        <section class="panel token-panel">
            <div>
                <p class="eyebrow">API Access</p>
                <h2>Connect to admin tools</h2>
                <p class="subtle">Use an admin bearer token to load users, billing settings, and device tokens.</p>
            </div>
            <div class="token-controls">
                <input type="password" data-token-input placeholder="Paste bearer token" class="token-input">
                <button type="button" data-token-save class="button button-primary">Save token</button>
                <button type="button" data-refresh class="button button-ghost">Refresh</button>
            </div>
        </section>

        <section class="stat-grid">
            <article class="stat-card">
                <span class="stat-label">Households</span>
                <strong data-admin-households>--</strong>
                <small>Registered household accounts.</small>
            </article>
            <article class="stat-card">
                <span class="stat-label">Devices</span>
                <strong class="mint" data-admin-devices>--</strong>
                <small>Device accounts for water readings.</small>
            </article>
            <article class="stat-card">
                <span class="stat-label">Alerts</span>
                <strong class="rose" data-admin-alert-count>--</strong>
                <small>Unread notification count.</small>
            </article>
            <article class="stat-card">
                <span class="stat-label">Estimated Usage</span>
                <strong class="amber" data-admin-liters>--</strong>
                <small>Current month water usage.</small>
            </article>
        </section>

        <article class="panel chart-panel">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Monthly Usage</p>
                    <h2>Total water usage for all users</h2>
                </div>
                <span class="status-pill">Auto scale</span>
            </div>
            <div class="chart-legend">
                <span><i class="legend-dot usage-dot"></i>Cubic meters</span>
            </div>
            <div class="usage-chart single-series" data-admin-monthly-chart>
                <div class="empty-state">Sample output: June 2026 total usage is 245.00 m^3.</div>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-admin-view="manage-users">
        <section class="admin-grid">
            <article class="panel admin-card">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Manage Users</p>
                        <h2>Households and devices</h2>
                    </div>
                </div>
                <div class="table">
                    <div class="table-head admin-table-head">
                        <span>Name</span>
                        <span>Email</span>
                        <span>Role</span>
                        <span>Status</span>
                    </div>
                    <div class="table-body" data-admin-users>
                        <div class="empty-state">Connect an admin token to load users.</div>
                    </div>
                </div>
                <div class="pager" data-admin-users-pager></div>
            </article>

            <article class="panel admin-card">
                <p class="eyebrow">Manage Users</p>
                <h2>Create user</h2>
                <form class="stack-form" data-admin-user-form>
                    <input type="text" name="name" placeholder="Name" required>
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <select name="role" required>
                        <option value="user">Household</option>
                        <option value="admin">Admin</option>
                        <option value="device">Device</option>
                    </select>
                    <input type="text" name="account_number" placeholder="Account number">
                    <input type="text" name="address" placeholder="Address">
                    <input type="text" name="phone" placeholder="Phone">
                    <button class="button button-primary" type="submit">Create user</button>
                </form>
            </article>

            <article class="panel sample-panel">
                <p class="eyebrow">Sample Output</p>
                <div class="sample-grid">
                    <div><dt>Household</dt><dd>Maria Santos</dd></div>
                    <div><dt>Role</dt><dd>Household</dd></div>
                    <div><dt>Status</dt><dd>Active</dd></div>
                </div>
            </article>
        </section>
    </section>

    <section class="dashboard-view" data-admin-view="search-households">
        <section class="panel filter-panel">
            <div>
                <p class="eyebrow">Search Households</p>
                <h2>Search households and devices</h2>
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
                    <p class="eyebrow">Search Results</p>
                    <h2>Matching accounts</h2>
                </div>
            </div>
            <div class="table">
                <div class="table-head admin-table-head">
                    <span>Name</span>
                    <span>Email</span>
                    <span>Role</span>
                    <span>Status</span>
                </div>
                <div class="table-body" data-admin-users>
                    <div class="empty-state">Connect an admin token to load users.</div>
                </div>
            </div>
            <div class="pager" data-admin-users-pager></div>
        </article>

        <article class="panel sample-panel">
            <p class="eyebrow">Sample Output</p>
            <div class="sample-grid">
                <div><dt>Search</dt><dd>Account HH-2026-001</dd></div>
                <div><dt>Result</dt><dd>1 active household</dd></div>
                <div><dt>Role filter</dt><dd>Household</dd></div>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-admin-view="billing-settings">
        <article class="panel admin-card">
            <p class="eyebrow">Billing Settings</p>
            <h2>Rates and thresholds</h2>
            <form class="stack-form" data-billing-form>
                <label>
                    Water rate per cubic meter
                    <input type="number" step="0.01" name="water_rate" placeholder="Example: 40.00" required>
                </label>
                <label>
                    Subsidy deduction
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

        <article class="panel sample-panel">
            <p class="eyebrow">Sample Output</p>
            <div class="sample-grid">
                <div><dt>Rate</dt><dd>PHP 40.00 per m^3</dd></div>
                <div><dt>Threshold</dt><dd>10.00 m^3</dd></div>
                <div><dt>Quiet hours</dt><dd>22:00-05:00</dd></div>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-admin-view="device-management">
        <article class="panel admin-card">
            <p class="eyebrow">Device Management</p>
            <h2>Device tokens</h2>
            <form class="stack-form" data-device-token-form>
                <select name="user_id" required data-device-select>
                    <option value="">Choose device account</option>
                </select>
                <input type="text" name="name" placeholder="Device name" value="ESP32 Core2">
                <button class="button button-primary" type="submit">Issue token</button>
            </form>
            <div class="notification-list" data-device-tokens>
                <div class="empty-state">No device tokens loaded yet.</div>
            </div>
        </article>

        <article class="panel sample-panel">
            <p class="eyebrow">Sample Output</p>
            <div class="sample-grid">
                <div><dt>Device</dt><dd>ESP32 Core2</dd></div>
                <div><dt>Owner</dt><dd>Household device account</dd></div>
                <div><dt>Tokens</dt><dd>1 active token</dd></div>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-admin-view="reports">
        <article class="panel admin-card">
            <p class="eyebrow">Reports</p>
            <h2>System summary</h2>
            <div class="summary-list" data-admin-reports>
                <div><dt>Loading</dt><dd>--</dd></div>
            </div>
            <div class="token-controls">
                <a class="button button-ghost" href="/api/exports/readings">Export readings</a>
                <a class="button button-ghost" href="/api/exports/billing-statements">Export billing</a>
            </div>
        </article>

        <article class="panel sample-panel">
            <p class="eyebrow">Sample Output</p>
            <div class="sample-grid">
                <div><dt>Monthly readings</dt><dd>320</dd></div>
                <div><dt>Total usage</dt><dd>245.00 m^3</dd></div>
                <div><dt>Unread alerts</dt><dd>4</dd></div>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-admin-view="alerts">
        <article class="panel admin-card">
            <p class="eyebrow">Alerts</p>
            <h2>Alert exports and monitoring</h2>
            <div class="notification-list" data-admin-alerts>
                <div class="empty-state">Connect an admin token to load alerts.</div>
            </div>
            <div class="token-controls">
                <a class="button button-ghost" href="/api/reports/notifications">View alert report</a>
                <a class="button button-ghost" href="/api/exports/notifications">Export alerts</a>
            </div>
        </article>

        <article class="panel sample-panel">
            <p class="eyebrow">Sample Output</p>
            <div class="notification-card">
                <h4>Leak risk detected</h4>
                <p>A household reported high flow during quiet hours.</p>
                <small>Sample admin alert</small>
            </div>
        </article>
    </section>
@endsection
