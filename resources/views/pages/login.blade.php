@extends('layouts.app')

@section('title', 'Login | Smart Water Monitoring')
@section('page', 'login')
@section('nav')
    <a href="/">Home</a>
@endsection

@section('content')
    <section class="auth-grid">
        <article class="panel auth-panel">
            <p class="eyebrow">Welcome back</p>
            <h1>Sign in to your water monitoring account</h1>
            <p class="subtle">Use your household, admin, or device account to access readings, billing, and alerts.</p>

            <div class="auth-tabs">
                <button type="button" class="tab-button is-active" data-tab-target="login">Login</button>
                <button type="button" class="tab-button" data-tab-target="register">Register</button>
                <button type="button" class="tab-button" data-tab-target="device">Device Token</button>
            </div>

            <div class="auth-status" data-auth-status>Ready</div>

            <form class="auth-form is-active" data-auth-form="login">
                <label>Email <input type="email" name="email" autocomplete="email" required></label>
                <label>Password <input type="password" name="password" autocomplete="current-password" required></label>
                <button class="button button-primary" type="submit">Sign in</button>
            </form>

            <form class="auth-form" data-auth-form="register">
                <label>Name <input type="text" name="name" autocomplete="name" required></label>
                <label>Email <input type="email" name="email" autocomplete="email" required></label>
                <label>Password <input type="password" name="password" autocomplete="new-password" required></label>
                <label>Confirm password <input type="password" name="password_confirmation" autocomplete="new-password" required></label>
                <label>Address <input type="text" name="address"></label>
                <label>Phone <input type="text" name="phone"></label>
                <button class="button button-primary" type="submit">Create account</button>
            </form>

            <form class="auth-form" data-auth-form="device">
                <label>Device email <input type="email" name="email" required></label>
                <label>Device password <input type="password" name="password" required></label>
                <label>Device name <input type="text" name="device_name" placeholder="ESP32 Core2"></label>
                <button class="button button-primary" type="submit">Issue device token</button>
            </form>
        </article>

        <aside class="panel auth-side">
            <p class="eyebrow">Need access</p>
            <h2>What this app does</h2>
            <ul class="focus-list">
                <li>Household water tracking</li>
                <li>Estimated billing and history</li>
                <li>Leak and threshold alerts</li>
                <li>Admin monitoring and device auth</li>
            </ul>
        </aside>
    </section>
@endsection
