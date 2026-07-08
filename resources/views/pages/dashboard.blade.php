@extends('layouts.app')

@section('title', 'Household | Smart Water Monitoring')
@section('page', 'household')
@section('nav')
    <button type="button" class="is-active" data-household-nav="dashboard">Dashboard</button>
    <button type="button" data-household-nav="live-water-usage">Live Water Usage</button>
    <button type="button" data-household-nav="consumption-history">Consumption History</button>
    <button type="button" data-household-nav="billing-summary">Billing Summary</button>
    <button type="button" data-household-nav="notifications-alerts">Notifications/Alerts</button>
    <button type="button" data-household-nav="profile">Profile</button>
    <button type="button" data-logout>Logout</button>
@endsection

@section('content')
    <section class="dashboard-view is-active" data-household-view="dashboard">
        <section class="panel panel-hero">
            <div class="hero-copy">
                <p class="eyebrow">Household Dashboard</p>
                <h1>Track water use, billing, and alerts in real time.</h1>
                <p class="lede">This dashboard shows the current month summary, recent readings, and alert notifications for the authenticated household.</p>
            </div>

            <div class="hero-metrics">
                <div class="mini-card">
                    <span class="mini-label">Current month</span>
                    <strong class="mint" data-today-total>-- m^3</strong>
                    <small>Latest monthly usage snapshot.</small>
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
                <span class="stat-label">Cubic Meters</span>
                <strong class="mint" data-stat-liters>--</strong>
                <small>Total water used this month.</small>
            </article>
            <article class="stat-card">
                <span class="stat-label">Estimated Bill</span>
                <strong class="amber" data-stat-bill>--</strong>
                <small>Based on the configured rate and subsidy.</small>
            </article>
            <article class="stat-card">
                <span class="stat-label">Alerts</span>
                <strong class="rose" data-stat-alerts>--</strong>
                <small>Unread notifications and warning count.</small>
            </article>
        </section>

        <article class="panel chart-panel">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Monthly Usage</p>
                    <h2>Water use and billing trend</h2>
                </div>
                <span class="status-pill">Auto scale</span>
            </div>
            <div class="chart-legend">
                <span><i class="legend-dot usage-dot"></i>Cubic meters</span>
                <span><i class="legend-dot bill-dot"></i>Estimated bill</span>
            </div>
            <div class="usage-chart" data-household-monthly-chart>
                <div class="empty-state">Sample output: Jan 2026 shows 8.40 m^3 and PHP 336.00.</div>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-household-view="live-water-usage">
        <article class="panel panel-table">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Live Readings</p>
                    <h2>Recent consumption history</h2>
                </div>
                <span class="status-pill" data-live-label>Offline</span>
            </div>
            <div class="table">
                <div class="table-head">
                    <span>Date</span>
                    <span>Flow</span>
                    <span>Cubic Meters</span>
                    <span>Bill</span>
                    <span>Status</span>
                </div>
                <div class="table-body" data-readings-list>
                    <div class="empty-state">Connect a token to load live readings.</div>
                </div>
            </div>
            <div class="pager" data-readings-pager></div>
        </article>

        <article class="panel sample-panel">
            <p class="eyebrow">Sample Output</p>
            <div class="sample-grid">
                <div><dt>Flow</dt><dd>12.50 L/min</dd></div>
                <div><dt>Usage</dt><dd>0.08 m^3</dd></div>
                <div><dt>Status</dt><dd>Normal</dd></div>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-household-view="consumption-history">
        <section class="panel filter-panel">
            <div>
                <p class="eyebrow">Reading Filters</p>
                <h2>Search by date range</h2>
            </div>
            <form class="filter-bar" data-reading-filters>
                <input type="date" name="from" class="filter-input">
                <input type="date" name="to" class="filter-input">
                <select name="per_page" class="filter-input">
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
                    <p class="eyebrow">Consumption History</p>
                    <h2>Filtered reading records</h2>
                </div>
            </div>
            <div class="table">
                <div class="table-head">
                    <span>Date</span>
                    <span>Flow</span>
                    <span>Cubic Meters</span>
                    <span>Bill</span>
                    <span>Status</span>
                </div>
                <div class="table-body" data-readings-list>
                    <div class="empty-state">No readings loaded yet.</div>
                </div>
            </div>
            <div class="pager" data-readings-pager></div>
        </article>

        <article class="panel sample-panel">
            <p class="eyebrow">Sample Output</p>
            <div class="sample-grid">
                <div><dt>Date range</dt><dd>June 1-30, 2026</dd></div>
                <div><dt>Total usage</dt><dd>11.25 m^3</dd></div>
                <div><dt>Bill</dt><dd>PHP 450.00</dd></div>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-household-view="billing-summary">
        <article class="panel side-card">
            <p class="eyebrow">Billing Summary</p>
            <h2>Current billing details</h2>
            <dl class="summary-list">
                <div>
                    <dt>Remaining balance</dt>
                    <dd data-balance>--</dd>
                </div>
                <div>
                    <dt>Water rate</dt>
                    <dd data-water-rate>--</dd>
                </div>
                <div>
                    <dt>Subsidy</dt>
                    <dd data-subsidy>--</dd>
                </div>
                <div>
                    <dt>Billing month</dt>
                    <dd data-billing-month>--</dd>
                </div>
            </dl>
        </article>

        <article class="panel sample-panel">
            <p class="eyebrow">Sample Output</p>
            <div class="sample-grid">
                <div><dt>Rate</dt><dd>PHP 40.00 per m^3</dd></div>
                <div><dt>Subsidy</dt><dd>PHP 50.00</dd></div>
                <div><dt>Balance</dt><dd>PHP 398.00</dd></div>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-household-view="notifications-alerts">
        <article class="panel side-card">
            <p class="eyebrow">Notifications/Alerts</p>
            <h2>Latest household alerts</h2>
            <div class="notification-list" data-notifications-list>
                <div class="empty-state">No alerts loaded yet.</div>
            </div>
        </article>

        <article class="panel sample-panel">
            <p class="eyebrow">Sample Output</p>
            <div class="notification-card">
                <h4>High usage warning</h4>
                <p>Your usage exceeded the configured monthly threshold.</p>
                <small>Sample alert</small>
            </div>
        </article>
    </section>

    <section class="dashboard-view" data-household-view="profile">
        <article class="panel side-card">
            <p class="eyebrow">Profile</p>
            <h2>Account information</h2>
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
