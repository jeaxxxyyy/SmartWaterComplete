@extends('layouts.app')

@section('title', 'Smart Water Monitoring')
@section('page', 'landing')

@section('content')
    <section class="panel panel-hero">
        <div class="hero-copy">
            <p class="eyebrow">IoT Water Monitoring</p>
            <h1>Real-time water insights with a clean modern dashboard.</h1>
            <p class="lede">Track live readings, estimated billing, alerts, and admin records in one secure, responsive system built for households and utility staff.</p>

            <div class="hero-actions">
                <a class="button button-primary" href="/login">Sign in</a>
                <a class="button button-ghost" href="/dashboard">Open dashboard</a>
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
            <p class="eyebrow">Dashboard</p>
            <h3>For households</h3>
            <p>See current usage, billing summary, and alerts at a glance.</p>
        </article>
        <article class="panel info-card">
            <p class="eyebrow">Admin</p>
            <h3>For utility staff</h3>
            <p>Manage users, billing settings, devices, reports, and exports from one polished backend.</p>
        </article>
        <article class="panel info-card">
            <p class="eyebrow">Security</p>
            <h3>Token-based access</h3>
            <p>Users and devices authenticate through bearer tokens, with role-based access control.</p>
        </article>
    </section>
@endsection
