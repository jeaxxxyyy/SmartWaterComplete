@extends('layouts.app')

@section('title', 'Smart Water Monitoring')
@section('page', 'landing')
@section('nav')
    <a href="/login">Login</a>
@endsection

@section('content')
    <section class="panel panel-hero">
        <div class="hero-copy">
            <p class="eyebrow">IoT Water Monitoring</p>
            <h1>Real-time water insights with a clean modern dashboard.</h1>
            <p class="lede">Sign in once and the system opens the correct workspace for your account role.</p>

            <div class="hero-actions">
                <a class="button button-primary" href="/login">Sign in</a>
            </div>
        </div>

        <div class="hero-metrics">
            <div class="mini-card">
                <span class="mini-label">Live readings</span>
                <strong>Sensor to cloud</strong>
                <small>ESP32 uploads water readings straight to Laravel.</small>
            </div>
            <div class="mini-card">
                <span class="mini-label">Billing</span>
                <strong>Estimated balance</strong>
                <small>Water rate, thresholds, and subsidy support built in.</small>
            </div>
            <div class="mini-card">
                <span class="mini-label">Security</span>
                <strong>Token access</strong>
                <small>Users and devices authenticate with bearer tokens.</small>
            </div>
        </div>
    </section>

    <section class="info-grid">
        <article class="panel info-card">
            <p class="eyebrow">Household</p>
            <h3>Household workspace</h3>
            <p>See current usage, billing summary, and alerts at a glance.</p>
        </article>
        <article class="panel info-card">
            <p class="eyebrow">Admin</p>
            <h3>Admin workspace</h3>
            <p>Manage users, billing settings, devices, reports, alerts, and household search.</p>
        </article>
        <article class="panel info-card">
            <p class="eyebrow">Security</p>
            <h3>Token-based access</h3>
            <p>Users and devices authenticate through bearer tokens, with role-based access control.</p>
        </article>
    </section>
@endsection
