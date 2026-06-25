@extends('layouts.app')

@section('title', 'Admin | Smart Water Monitoring')
@section('page', 'admin')

@section('content')
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

    <section class="panel filter-panel">
        <div>
            <p class="eyebrow">User Filters</p>
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

    <section class="admin-grid">
        <article class="panel admin-card">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Users</p>
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
            <p class="eyebrow">Create User</p>
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

        <article class="panel admin-card">
            <p class="eyebrow">Billing Settings</p>
            <form class="stack-form" data-billing-form>
                <input type="number" step="0.01" name="water_rate" placeholder="Water rate" required>
                <input type="number" step="0.01" name="subsidy_deduction" placeholder="Subsidy deduction" required>
                <input type="number" step="0.01" name="threshold_liters" placeholder="Threshold liters" required>
                <input type="number" step="0.01" name="leak_flow_rate_threshold" placeholder="Leak flow threshold" required>
                <input type="number" name="quiet_hours_start" placeholder="Quiet hours start" required>
                <input type="number" name="quiet_hours_end" placeholder="Quiet hours end" required>
                <button class="button button-primary" type="submit">Save billing</button>
            </form>
        </article>

        <article class="panel admin-card">
            <p class="eyebrow">Device Tokens</p>
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

        <article class="panel admin-card">
            <p class="eyebrow">Reports</p>
            <div class="summary-list" data-admin-reports>
                <div><dt>Loading</dt><dd>--</dd></div>
            </div>
        </article>
    </section>
@endsection
