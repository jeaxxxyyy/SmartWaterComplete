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
            <p class="subtle">Use your household or municipal administrator account. Devices are commissioned securely by the administrator.</p>

            <div class="auth-status" data-auth-status>Ready</div>

            <form class="auth-form is-active" data-auth-form="login">
                <label>Email <input type="email" name="email" autocomplete="email" required></label>
                <label>Password <input type="password" name="password" autocomplete="current-password" required></label>
                <button class="button button-primary" type="submit">Sign in</button>
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
