@extends('layouts.app')

@section('title', 'Dashboard | Smart Water Monitoring')
@section('page', 'dashboard')

@section('content')
    <section class="panel panel-hero">
        <div class="hero-copy">
            <p class="eyebrow">Household Dashboard</p>
            <h1>Track water use, billing, and alerts in real time.</h1>
            <p class="lede">This dashboard shows the current month summary, recent readings, and alert notifications for the authenticated household.</p>
        </div>

        <div class="hero-metrics">
            <div class="mini-card">
                <span class="mini-label">Connection</span>
                <strong data-status>Waiting for token</strong>
                <small>Use your bearer token to load personalized data.</small>
            </div>
            <div class="mini-card">
                <span class="mini-label">Current month</span>
                <strong class="mint" data-today-total>-- liters</strong>
                <small>Latest monthly usage snapshot.</small>
            </div>
        </div>
    </section>

    <section class="panel token-panel">
        <div>
            <p class="eyebrow">API Access</p>
            <h2>Connect to the backend</h2>
            <p class="subtle">Paste the bearer token issued from login, registration, or a device endpoint.</p>
        </div>
        <div class="token-controls">
            <input type="password" data-token-input placeholder="Paste bearer token" class="token-input">
            <button type="button" data-token-save class="button button-primary">Save token</button>
            <button type="button" data-refresh class="button button-ghost">Refresh</button>
        </div>
    </section>

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

    <section class="stat-grid">
        <article class="stat-card">
            <span class="stat-label">Readings</span>
            <strong data-stat-readings>--</strong>
            <small>Current month reading count.</small>
        </article>
        <article class="stat-card">
            <span class="stat-label">Liters</span>
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

    <section class="content-grid">
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
                    <span>Liters</span>
                    <span>Bill</span>
                    <span>Status</span>
                </div>
                <div class="table-body" data-readings-list>
                    <div class="empty-state">Connect a token to load live readings.</div>
                </div>
            </div>
            <div class="pager" data-readings-pager></div>
        </article>

        <aside class="sidebar">
            <article class="panel side-card">
                <p class="eyebrow">Billing Summary</p>
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

            <article class="panel side-card">
                <p class="eyebrow">Alerts</p>
                <div class="notification-list" data-notifications-list>
                    <div class="empty-state">No alerts loaded yet.</div>
                </div>
            </article>
        </aside>
    </section>
@endsection
