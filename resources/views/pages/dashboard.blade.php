@extends('layouts.app')

@section('title', 'Household | Smart Water Monitoring')
@section('page', 'household')
@section('nav')
    <button type="button" class="is-active" data-household-nav="dashboard">Dashboard</button>
    <button type="button" data-household-nav="live-monitoring">Monitoring</button>
    <button type="button" data-household-nav="history">Consumption History</button>
    <button type="button" data-household-nav="billing">Estimated Billing</button>
    <button type="button" data-household-nav="leak">Leak Detection</button>
    <button type="button" data-household-nav="notifications">Notifications</button>
    <button type="button" data-household-nav="settings">Account Settings</button>
    <button type="button" data-logout>Logout</button>
@endsection

@section('content')
    <section class="dashboard-view is-active" data-household-view="dashboard">
        <section class="panel panel-hero household-hero">
            <div class="hero-copy">
                <p class="eyebrow">User Functionalities</p>
                <h1>Monitor usage, estimated billing, and leak alerts in one place.</h1>
                <p class="lede">This dashboard matches the household features in your specification: live monitoring, consumption history, estimated billing, leak detection, notifications, and account settings.</p>
                <div class="hero-badges">
                    <span class="status-chip" data-live-label>Idle</span>
                    <span class="status-chip status-chip-muted" data-device-name>Device: --</span>
                    <span class="status-chip status-chip-soft" data-monitoring-status>Monitoring: --</span>
                    <span class="status-chip status-chip-muted" data-wifi-status>WiFi: --</span>
                    <span class="status-chip status-chip-muted" data-device-connection-status>Device: --</span>
                    <span class="status-chip status-chip-muted" data-server-connection-status>Server: --</span>
                    <span class="status-chip status-chip-alert" data-water-limit>Limit: --</span>
                    <span class="status-chip status-chip-muted">Auto refresh: 15s</span>
                    <span class="status-chip status-chip-soft" data-leak-status>Normal</span>
                    <span class="status-chip status-chip-muted" data-last-updated-label>Last updated: --</span>
                </div>
            </div>

            <div class="hero-metrics">
                <div class="mini-card">
                    <span class="mini-label">Current flow rate</span>
                    <strong class="mint" data-current-flow>-- L/min</strong>
                    <small>Real-time reading from the latest device upload.</small>
                </div>
                <div class="mini-card">
                    <span class="mini-label">Total consumed</span>
                    <strong data-total-consumed>-- m3</strong>
                    <small>Current month water consumption.</small>
                </div>
                <div class="mini-card">
                    <span class="mini-label">Estimated bill</span>
                    <strong class="amber" data-estimated-bill>--</strong>
                    <small>Calculated using the active water rate.</small>
                </div>
                <div class="mini-card">
                    <span class="mini-label">Leak detection</span>
                    <strong class="rose" data-leak-summary>Normal</strong>
                    <small>Immediate status for possible leaks.</small>
                </div>
                <div class="mini-card">
                    <span class="mini-label">Connection health</span>
                    <strong data-device-connection-status>Connected</strong>
                    <small data-server-connection-status>Server connected.</small>
                </div>
            </div>
        </section>

        <section class="stat-grid">
            <article class="stat-card">
                <span class="stat-label">Readings</span>
                <strong data-stat-readings>--</strong>
                <small>Current month reading count.</small>
            </article>
            <article class="stat-card">
                <span class="stat-label">Quick summary</span>
                <strong class="mint" data-quick-summary>--</strong>
                <small>Latest usage snapshot from the system.</small>
            </article>
            <article class="stat-card">
                <span class="stat-label">Rate per m3</span>
                <strong class="amber" data-water-rate>--</strong>
                <small>Active water rate for this household.</small>
            </article>
            <article class="stat-card">
                <span class="stat-label">Notifications</span>
                <strong class="rose" data-stat-alerts>--</strong>
                <small>Unread or recent system alerts.</small>
            </article>
        </section>
    </section>

    <section class="dashboard-view" data-household-view="live-monitoring">
        <section class="panel filter-panel">
            <div>
                <p class="eyebrow">Live Water Monitoring</p>
                <h2>Real-time flow rate and consumption</h2>
            </div>
            <form class="filter-bar" data-reading-filters>
                <input type="date" name="from" class="filter-input" aria-label="From date">
                <input type="date" name="to" class="filter-input" aria-label="To date">
                <select name="per_page" class="filter-input" aria-label="Rows per page">
                    <option value="5">5 per page</option>
                    <option value="10" selected>10 per page</option>
                    <option value="25">25 per page</option>
                </select>
                <button class="button button-primary" type="submit">Apply</button>
            </form>
        </section>

        <article class="panel panel-table">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Live Feed</p>
                    <h2>Recent readings from the M5Stack device</h2>
                </div>
                <span class="status-pill" data-live-label>Offline</span>
            </div>
            <div class="table">
                <div class="table-head">
                    <span>Date</span>
                    <span>Flow rate</span>
                    <span>Consumed</span>
                    <span>Estimated bill</span>
                    <span>Status</span>
                </div>
                <div class="table-body" data-readings-list>
                    <div class="empty-state">Connect a token to load live readings.</div>
                </div>
            </div>
            <div class="pager" data-readings-pager></div>
        </article>

        <article class="panel chart-panel">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Monitoring Graph</p>
                    <h2>Real-time consumption trend</h2>
                </div>
                <span class="status-pill">Optional graph</span>
            </div>
            <div class="chart-legend">
                <span><i class="legend-dot usage-dot"></i>m3</span>
                <span><i class="legend-dot bill-dot"></i>Estimated bill</span>
            </div>
            <div class="usage-chart" data-household-monthly-chart>
                <div class="empty-state">No usage data yet.</div>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-household-view="history">
        <article class="panel panel-table">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Water Consumption History</p>
                    <h2>Daily, weekly, and monthly usage records</h2>
                </div>
            </div>
            <div class="table">
                <div class="table-head">
                    <span>Date</span>
                    <span>Flow rate</span>
                    <span>Consumed</span>
                    <span>Estimated bill</span>
                    <span>Status</span>
                </div>
                <div class="table-body" data-readings-list>
                    <div class="empty-state">No readings loaded yet.</div>
                </div>
            </div>
            <div class="pager" data-readings-pager></div>
        </article>
    </section>

    <section class="dashboard-view" data-household-view="billing">
        <section class="stat-grid">
            <article class="stat-card">
                <span class="stat-label">Water rate</span>
                <strong data-water-rate>--</strong>
                <small>Rate per cubic meter.</small>
            </article>
            <article class="stat-card">
                <span class="stat-label">Total water consumed</span>
                    <strong data-total-consumed>-- m3</strong>
                <small>Usage basis for billing.</small>
            </article>
            <article class="stat-card">
                <span class="stat-label">Estimated bill</span>
                <strong class="amber" data-estimated-bill>--</strong>
                <small>Expected monthly amount.</small>
            </article>
            <article class="stat-card">
                <span class="stat-label">Billing month</span>
                <strong data-billing-month>--</strong>
                <small>Latest generated statement.</small>
            </article>
        </section>

        <article class="panel side-card">
            <p class="eyebrow">Estimated Billing</p>
            <h2>Current billing details</h2>
            <dl class="summary-list">
                <div>
                    <dt>Remaining balance</dt>
                    <dd data-balance>--</dd>
                </div>
                <div>
                    <dt>Water rate per m3</dt>
                    <dd data-water-rate>--</dd>
                </div>
                <div>
                    <dt>Usage threshold</dt>
                    <dd data-threshold>--</dd>
                </div>
                <div>
                    <dt>Quick summary</dt>
                    <dd data-quick-summary>--</dd>
                </div>
            </dl>
        </article>
    </section>

    <section class="dashboard-view" data-household-view="leak">
        <article class="panel side-card">
            <p class="eyebrow">Leak Detection</p>
            <h2>Status and response</h2>
            <div class="leak-banner">
                <span class="status-chip status-chip-soft" data-leak-status>Normal</span>
                <span class="subtle" data-leak-details>No leak has been detected yet.</span>
            </div>
            <dl class="summary-list">
                <div>
                    <dt>Current flow rate</dt>
                    <dd data-current-flow>-- L/min</dd>
                </div>
                <div>
                    <dt>Leak threshold</dt>
                    <dd data-leak-threshold>--</dd>
                </div>
                <div>
                    <dt>Last updated</dt>
                    <dd data-last-updated>--</dd>
                </div>
            </dl>
        </article>
    </section>

    <section class="dashboard-view" data-household-view="notifications">
        <article class="panel side-card">
            <p class="eyebrow">Notifications</p>
            <h2>Leak, limit, and consumption alerts</h2>
            <div class="notification-list" data-notifications-list>
                <div class="empty-state">No alerts loaded yet.</div>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-household-view="settings">
        <article class="panel side-card">
            <p class="eyebrow">Account Settings</p>
            <h2>Customer information</h2>
            <dl class="summary-list">
                <div>
                    <dt>Name</dt>
                    <dd data-profile-name>--</dd>
                </div>
                <div>
                    <dt>Email</dt>
                    <dd data-profile-email>--</dd>
                </div>
                <div>
                    <dt>Account No.</dt>
                    <dd data-profile-account>--</dd>
                </div>
                <div>
                    <dt>Address</dt>
                    <dd data-profile-address>--</dd>
                </div>
                <div>
                    <dt>Phone</dt>
                    <dd data-profile-phone>--</dd>
                </div>
                <div>
                    <dt>Session</dt>
                    <dd><button type="button" class="link-button" data-logout>Logout</button></dd>
                </div>
            </dl>
        </article>
    </section>
@endsection
