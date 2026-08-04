@extends('layouts.app')

@section('title', 'Household | Smart Water Monitoring')
@section('page', 'household')
@section('app-header-actions')
    <div class="household-header-actions" aria-label="Household account actions">
        <button class="household-menu-button" type="button" data-household-sidebar-toggle aria-expanded="false" aria-controls="household-sidebar">Menu</button>
        <button class="header-action-button" type="button" data-household-nav="notifications" aria-label="Open notifications">
            <span aria-hidden="true">Notifications</span>
            <span class="header-action-count" data-household-alert-count hidden>0</span>
        </button>
        <span class="account-role-badge" data-account-role>Household User</span>
        @include('components.theme-toggle', ['class' => 'site-theme-toggle'])
        <button class="household-account-button" type="button" data-household-nav="settings" aria-label="Open account settings">
            <span class="household-avatar" data-profile-initials aria-hidden="true">SW</span>
            <span class="household-account-copy">
                <strong data-profile-name>Household account</strong>
                <small>Account settings</small>
            </span>
        </button>
    </div>
@endsection

@section('household-sidebar')
    <div class="household-sidebar-brand">
        <span class="household-sidebar-mark" aria-hidden="true"></span>
        <div>
            <strong data-profile-name>Household account</strong>
            <small>Household workspace</small>
        </div>
    </div>
    <nav class="sidebar-nav household-sidebar-nav" aria-label="Household sections">
        <span class="sidebar-section-title">Workspace</span>
        <button type="button" class="is-active" data-household-nav="dashboard" aria-current="page">Dashboard</button>
        <button type="button" data-household-nav="live-monitoring">Live Monitoring</button>
        <button type="button" data-household-nav="history">Consumption History</button>
        <button type="button" data-household-nav="billing">Estimated Billing</button>
        <button type="button" data-household-nav="leak">Leak Detection</button>
        <button type="button" data-household-nav="notifications">Notifications <span class="sidebar-count" data-household-alert-count hidden>0</span></button>
        <button type="button" data-household-nav="settings">Account Settings</button>
    </nav>
    <div class="household-sidebar-account">
        <span class="subtle" data-profile-email>Signed-in household</span>
        <button class="household-sidebar-logout" type="button" data-logout>Logout</button>
    </div>
@endsection

@section('content')
    <section class="dashboard-view is-active" data-household-view="dashboard">
        <header class="household-dashboard-hero">
            <div class="household-dashboard-hero-copy">
                <div class="household-dashboard-eyebrow">
                    <span class="household-dashboard-pulse" aria-hidden="true"></span>
                    <span>Household workspace</span>
                </div>
                <h1>Household Dashboard</h1>
                <p>Monitor your water usage, billing, and system status from a calm, government-grade view built for daily oversight.</p>
            </div>
            <div class="household-dashboard-hero-actions">
                <div class="household-dashboard-meta">
                    <span class="status-pill status-pill-live">Live update</span>
                    <span class="dashboard-updated" data-last-updated-label>Last updated: Waiting for data</span>
                </div>
                <div class="household-dashboard-controls">
                    <select class="filter-input analytics-range-select" data-household-analytics-range aria-label="Household analytics period">
                        <option value="last_7_days">Last 7 days</option>
                        <option value="last_30_days" selected>Last 30 days</option>
                        <option value="last_3_months">Last 3 months</option>
                        <option value="last_12_months">Last 12 months</option>
                        <option value="custom">Custom range</option>
                    </select>
                    <span class="analytics-custom-range" data-household-analytics-custom hidden>
                        <input type="date" class="filter-input" data-household-analytics-from aria-label="Analytics start date">
                        <input type="date" class="filter-input" data-household-analytics-to aria-label="Analytics end date">
                    </span>
                    <button class="button button-secondary" type="button" data-refresh aria-label="Refresh dashboard data">Refresh</button>
                </div>
            </div>
            <div class="dashboard-feedback" data-dashboard-feedback role="status" aria-live="polite" hidden></div>
        </header>

        <section class="household-kpi-grid" aria-label="Household summary">
            <article class="metric-card metric-card-primary">
                <div class="metric-card-top">
                    <span class="metric-card-icon" aria-hidden="true"><i class="bi bi-speedometer2"></i></span>
                    <span class="metric-card-badge">Live</span>
                </div>
                <span class="metric-card-label">Current flow rate</span>
                <strong class="mint" data-current-flow>Loading latest reading…</strong>
                <small data-flow-state>Reading the connected meter.</small>
            </article>
            <article class="metric-card metric-card-primary">
                <div class="metric-card-top">
                    <span class="metric-card-icon" aria-hidden="true"><i class="bi bi-droplet-half"></i></span>
                    <span class="metric-card-badge">This month</span>
                </div>
                <span class="metric-card-label">Monthly consumption</span>
                <strong data-total-consumed>Loading…</strong>
                <small data-consumption-state>Calculating this month’s total.</small>
            </article>
            <article class="metric-card metric-card-primary">
                <div class="metric-card-top">
                    <span class="metric-card-icon" aria-hidden="true"><i class="bi bi-receipt"></i></span>
                    <span class="metric-card-badge">Estimated</span>
                </div>
                <span class="metric-card-label">Estimated bill</span>
                <strong class="amber" data-estimated-bill>Loading…</strong>
                <small data-billing-state>Using the active water rate.</small>
            </article>
            <article class="metric-card">
                <div class="metric-card-top">
                    <span class="metric-card-icon" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                    <span class="metric-card-badge metric-card-badge-neutral">Safety</span>
                </div>
                <span class="metric-card-label">Leak status</span>
                <strong class="mint" data-leak-summary>Loading…</strong>
                <small data-leak-state>Checking the latest reading.</small>
            </article>
            <article class="metric-card">
                <div class="metric-card-top">
                    <span class="metric-card-icon" aria-hidden="true"><i class="bi bi-wifi"></i></span>
                    <span class="metric-card-badge metric-card-badge-neutral">Health</span>
                </div>
                <span class="metric-card-label">Connection status</span>
                <strong data-connection-summary>Loading…</strong>
                <small data-connection-state>Checking meter and server connectivity.</small>
            </article>
            <article class="metric-card">
                <div class="metric-card-top">
                    <span class="metric-card-icon" aria-hidden="true"><i class="bi bi-bell"></i></span>
                    <span class="metric-card-badge metric-card-badge-neutral">Inbox</span>
                </div>
                <span class="metric-card-label">Unread notifications</span>
                <strong class="rose" data-stat-alerts>Loading…</strong>
                <small data-notification-state>Checking your notification inbox.</small>
            </article>
        </section>

        <section class="household-dashboard-grid">
            <article class="panel chart-panel household-usage-panel">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Usage overview</p>
                        <h2>Monthly water consumption</h2>
                    </div>
                    <span class="panel-context">12-month trend</span>
                </div>
                <div class="household-usage-content is-loading" data-household-usage-content>
                    <div class="chart-legend">
                        <span><i class="legend-dot usage-dot"></i>Consumption</span>
                    </div>
                    <div class="usage-chart" data-household-monthly-chart>
                        <div class="loading-skeleton-card" aria-hidden="true">
                            <span class="loading-skeleton-line loading-skeleton-line-lg"></span>
                            <span class="loading-skeleton-line"></span>
                            <span class="loading-skeleton-line"></span>
                            <span class="loading-skeleton-line"></span>
                        </div>
                    </div>
                </div>
            </article>

            <article class="panel system-status-panel">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">System status</p>
                        <h2>Meter and service health</h2>
                    </div>
                </div>
                <div class="system-status-list">
                    <div class="system-status-item"><span>Water meter</span><strong data-system-water-meter>Loading…</strong></div>
                    <div class="system-status-item"><span>Monitoring</span><strong data-system-monitoring>Loading…</strong></div>
                    <div class="system-status-item"><span>Wi-Fi</span><strong data-system-wifi>Loading…</strong></div>
                    <div class="system-status-item"><span>Server</span><strong data-system-server>Loading…</strong></div>
                    <div class="system-status-item"><span>Leak status</span><strong data-system-leak>Loading…</strong></div>
                    <div class="system-status-item"><span>Last updated</span><strong data-last-updated>Waiting for data</strong></div>
                </div>
            </article>
        </section>

        <article class="panel side-card household-analytics-card">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Selected-period analytics</p>
                    <h2>Usage comparison and meter history</h2>
                </div>
                <span class="panel-context" data-household-analytics-timezone>UTC</span>
            </div>
            <div class="summary-list">
                <div><dt>Selected period</dt><dd data-household-analytics-period>Waiting for data</dd></div>
                <div><dt>Latest day consumption</dt><dd data-household-daily-consumption>--</dd></div>
                <div><dt>Latest week consumption</dt><dd data-household-weekly-consumption>--</dd></div>
                <div><dt>Latest month consumption</dt><dd data-household-monthly-consumption>--</dd></div>
                <div><dt>Selected-period bill</dt><dd data-household-analytics-bill>--</dd></div>
                <div><dt>Previous period</dt><dd data-household-previous-period>--</dd></div>
                <div><dt>Change from previous</dt><dd data-household-period-comparison>--</dd></div>
                <div><dt>Highest usage day</dt><dd data-household-highest-usage>--</dd></div>
                <div><dt>Leak warnings in period</dt><dd data-household-leak-warning-count>--</dd></div>
                <div><dt>Last successful meter reading</dt><dd data-household-last-reading>--</dd></div>
            </div>
        </article>

        <section class="household-activity-grid">
            <article class="panel activity-panel">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Recent readings</p>
                        <h2>Latest meter activity</h2>
                    </div>
                    <button class="activity-view-all" type="button" data-household-nav="live-monitoring" aria-label="View all recent readings">View all <span aria-hidden="true">→</span></button>
                </div>
                <div class="recent-readings-list" data-recent-readings-list>
                    <div class="dashboard-empty-state is-loading">
                        <div class="loading-skeleton-card loading-skeleton-card-inline" aria-hidden="true">
                            <span class="loading-skeleton-line loading-skeleton-line-lg"></span>
                            <span class="loading-skeleton-line"></span>
                        </div>
                        <strong>Loading recent readings...</strong>
                        <span>Checking the connected meter feed and preparing the latest activity.</span>
                    </div>
                </div>
            </article>

            <article class="panel activity-panel">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Notifications</p>
                        <h2>Recent alerts and updates</h2>
                    </div>
                    <button class="activity-view-all" type="button" data-household-nav="notifications" aria-label="View all notifications">View all <span aria-hidden="true">→</span></button>
                </div>
                <div class="notification-list dashboard-notification-list" data-dashboard-notifications-list>
                    <div class="dashboard-empty-state is-loading">
                        <div class="loading-skeleton-card loading-skeleton-card-inline" aria-hidden="true">
                            <span class="loading-skeleton-line loading-skeleton-line-lg"></span>
                            <span class="loading-skeleton-line"></span>
                        </div>
                        <strong>Loading notifications...</strong>
                        <span>Checking your latest alerts and updates before they appear here.</span>
                    </div>
                </div>
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
                <div class="table-body" data-live-readings-list>
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
                <span class="status-pill">Monitoring graph</span>
            </div>
            <div class="chart-legend">
                <span><i class="legend-dot usage-dot"></i>Consumption</span>
            </div>
            <div class="usage-chart" data-household-monthly-chart>
                <div class="empty-state">No usage data yet.</div>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-household-view="history">
        <div class="auth-tabs" data-history-tabs>
            <button type="button" class="tab-button is-active" data-history-period="daily">Daily</button>
            <button type="button" class="tab-button" data-history-period="weekly">Weekly</button>
            <button type="button" class="tab-button" data-history-period="monthly">Monthly</button>
        </div>

        <section class="panel filter-panel">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Consumption History</p>
                    <h2>Track usage, trends, and billing estimates</h2>
                </div>
            </div>
            <form class="filter-bar" data-history-filters>
                <label>From <input type="date" name="from"></label>
                <label>To <input type="date" name="to"></label>
                <label>Search <input type="search" name="search" placeholder="Meter name or reading ID"></label>
                <label>Rows <select name="per_page"><option value="10">10</option><option value="25">25</option><option value="50">50</option></select></label>
                <button class="button button-primary" type="submit">Apply</button>
            </form>
            <div class="filter-actions">
                <button class="button button-secondary" type="button" data-history-export-csv>Export CSV</button>
                <button class="button button-secondary" type="button" data-history-export-pdf>Export PDF</button>
            </div>
        </section>

        <section class="household-kpi-grid" aria-label="Consumption summary">
            <article class="metric-card">
                <span class="metric-card-label">Today’s usage</span>
                <strong data-history-today-usage>Loading…</strong>
                <small>Consumption in the last 24 hours</small>
            </article>
            <article class="metric-card">
                <span class="metric-card-label">This week</span>
                <strong data-history-week-usage>Loading…</strong>
                <small>Usage since the week started</small>
            </article>
            <article class="metric-card">
                <span class="metric-card-label">This month</span>
                <strong data-history-month-usage>Loading…</strong>
                <small>Usage since the month started</small>
            </article>
            <article class="metric-card metric-card-primary">
                <span class="metric-card-label">Estimated bill</span>
                <strong class="amber" data-history-estimated-bill>Loading…</strong>
                <small>Based on the active billing rate</small>
            </article>
            <article class="metric-card">
                <span class="metric-card-label">Average daily usage</span>
                <strong data-history-average-usage>Loading…</strong>
                <small>Across the selected range</small>
            </article>
        </section>

        <article class="panel chart-panel">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Trend</p>
                    <h2>Consumption over time</h2>
                </div>
                <span class="panel-context" data-history-period-label>Daily trend</span>
            </div>
            <div class="usage-chart" data-history-chart>
                <div class="chart-empty-state">Loading consumption trend…</div>
            </div>
        </article>

        <article class="panel panel-table">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Usage records</p>
                    <h2>Meter readings and aggregated totals</h2>
                </div>
            </div>
            <div class="table">
                <div class="table-head" data-history-table-head>
                    <span>Period</span>
                    <span>Total liters</span>
                    <span>Total m³</span>
                    <span>Estimated bill</span>
                    <span>Status</span>
                </div>
                <div class="table-body" data-history-list>
                    <div class="empty-state">No consumption totals loaded yet.</div>
                </div>
            </div>
            <div class="pager" data-history-pager></div>
        </article>
    </section>

    <section class="dashboard-view" data-household-view="billing">
        <section class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                            Estimated Billing
                        </span>
                    </div>
                    <div>
                        <h1 class="text-4xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">Current Billing Estimate</h1>
                        <p class="mt-2 text-lg text-slate-500 dark:text-slate-400">Review your current billing estimate based on your latest recorded water usage.</p>
                    </div>
                </div>
                <div class="flex flex-col items-start gap-3 lg:items-end">
                    <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300" data-household-billing-state>
                        Loading billing details...
                    </span>
                    <span class="text-sm font-medium text-slate-500 dark:text-slate-400" data-household-billing-period>Current billing period</span>
                </div>
            </div>
        </section>

        <section class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-4" data-household-billing-summary>
            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Current Charge</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900 dark:text-slate-100" data-billing-current-charge>--</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                        <i class="bi bi-receipt" aria-hidden="true"></i>
                    </span>
                </div>
                <p class="mt-4 text-sm text-slate-500 dark:text-slate-400" data-billing-record-type>Estimate pending</p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Consumption</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900 dark:text-slate-100" data-billing-consumption>--</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300">
                        <i class="bi bi-droplet-half" aria-hidden="true"></i>
                    </span>
                </div>
                <p class="mt-4 text-sm text-slate-500 dark:text-slate-400" data-billing-reading-count>Readings pending</p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Applicable Rate</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900 dark:text-slate-100" data-billing-rate>--</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">
                        <i class="bi bi-speedometer2" aria-hidden="true"></i>
                    </span>
                </div>
                <p class="mt-4 text-sm text-slate-500 dark:text-slate-400" data-billing-rate-effective>Effective rate</p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Balance</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900 dark:text-slate-100" data-billing-balance>--</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        <i class="bi bi-wallet2" aria-hidden="true"></i>
                    </span>
                </div>
                <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Finalized balance when available.</p>
            </article>
        </section>

        <section class="mt-8 grid gap-6 xl:grid-cols-[1.4fr_0.9fr]">
            <article class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Billing Breakdown</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">How your estimated bill is calculated</h2>
                    </div>
                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300" data-billing-type-chip>Estimated</span>
                </div>

                <div class="mt-8 space-y-4" data-billing-calculation>
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">Loading the billing calculation...</div>
                </div>

                <div class="mt-6 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-700 dark:border-sky-900 dark:bg-sky-950/50 dark:text-sky-300" data-billing-explanation>
                    Billing details will appear when the account has a valid rate and billable reading.
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        <i class="bi bi-cpu" aria-hidden="true"></i>
                    </span>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Meter Information</p>
                        <h2 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">M5Stack CoreS3 Household Meter</h2>
                    </div>
                </div>

                <div class="mt-8 space-y-4" data-billing-meter>
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">Loading assigned meter information...</div>
                </div>

                <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-800/50" data-billing-latest-reading>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Latest Reading</p>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">--</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Flow Rate</p>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">--</p>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Billing History</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Finalized statements</h2>
                </div>
                <button type="button" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700" disabled aria-label="Download billing statements">
                    Download Statements
                </button>
            </div>

            <div class="mt-8 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/70">
                        <tr>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Period</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Consumption</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Charge</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Balance</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-900" data-billing-history>
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                                <div class="mx-auto flex max-w-md flex-col items-center gap-3">
                                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        <i class="bi bi-receipt-cutoff text-2xl" aria-hidden="true"></i>
                                    </div>
                                    <div>
                                        <p class="text-base font-semibold text-slate-900 dark:text-slate-100">No finalized bills yet</p>
                                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">We’ll automatically display your official monthly billing statements here.</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                        <i class="bi bi-currency-dollar" aria-hidden="true"></i>
                    </span>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Water Rate</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100" data-water-rate>--</p>
                    </div>
                </div>
                <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Rate per cubic meter</p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300">
                        <i class="bi bi-droplet" aria-hidden="true"></i>
                    </span>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Total Water Consumed</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100" data-total-consumed>-- m³</p>
                    </div>
                </div>
                <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Usage basis for billing</p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">
                        <i class="bi bi-calculator" aria-hidden="true"></i>
                    </span>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Estimated Bill</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100" data-estimated-bill>--</p>
                    </div>
                </div>
                <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Current estimate</p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                    </span>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Billing Month</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100" data-billing-month>--</p>
                    </div>
                </div>
                <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Pending finalization</p>
            </article>
        </section>

        <section class="mt-8 grid gap-6 xl:grid-cols-[1.4fr_0.9fr]" hidden aria-hidden="true">
            <article class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Estimated Billing</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Current billing details</h2>
                <dl class="mt-6 space-y-4 text-sm text-slate-600 dark:text-slate-300">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-700">
                        <dt>Remaining balance</dt>
                        <dd class="font-semibold text-slate-900 dark:text-slate-100" data-balance>--</dd>
                    </div>
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-700">
                        <dt>Water rate per m³</dt>
                        <dd class="font-semibold text-slate-900 dark:text-slate-100" data-water-rate>--</dd>
                    </div>
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-700">
                        <dt>Usage threshold</dt>
                        <dd class="font-semibold text-slate-900 dark:text-slate-100" data-threshold>--</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt>Quick summary</dt>
                        <dd class="font-semibold text-slate-900 dark:text-slate-100" data-quick-summary>--</dd>
                    </div>
                </dl>
            </article>
        </section>
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
            <div class="notification-head">
                <p class="eyebrow">Notifications</p>
                <button type="button" class="button button-secondary" data-clear-notifications>Clear all</button>
            </div>
            <div class="notification-list" data-notifications-list>
                <div class="empty-state">No alerts loaded yet.</div>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-household-view="settings">
        <article class="panel side-card">
            <p class="eyebrow">Account Settings</p>
            <h2>Customer information</h2>
            <p class="subtle">Update your email, phone, and password for your household account.</p>

            <form class="stack-form" data-account-form novalidate>
                <label>Email address
                    <input id="account-email" type="email" name="email" autocomplete="email" placeholder="you@example.com" required>
                </label>
                <label>Phone number
                    <input id="account-phone" type="tel" name="phone" autocomplete="tel" placeholder="e.g. +1 607-992-7137" maxlength="50">
                </label>
                <button class="button button-primary" type="submit" data-account-submit disabled>Update contact details</button>
                <div class="form-feedback" data-account-feedback hidden role="status" aria-live="polite"></div>
            </form>

            <div class="divider" aria-hidden="true"></div>

            <form class="stack-form" data-password-form novalidate>
                <label>Current password
                    <input type="password" name="current_password" autocomplete="current-password" placeholder="Current password" required>
                </label>
                <label>New password
                    <input type="password" name="password" autocomplete="new-password" placeholder="New password" minlength="8" required>
                </label>
                <label>Confirm new password
                    <input type="password" name="password_confirmation" autocomplete="new-password" placeholder="Confirm new password" minlength="8" required>
                </label>
                <button class="button button-secondary" type="submit" data-password-submit disabled>Change password</button>
                <div class="form-feedback" data-password-feedback hidden role="status" aria-live="polite"></div>
            </form>
        </article>
    </section>
@endsection
