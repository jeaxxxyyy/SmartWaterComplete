<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Smart Water Monitoring'))</title>
    <script>
        const dashboardPage = ['/household', '/admin'].includes(window.location.pathname.replace(/\/$/, '') || '/');
        document.documentElement.dataset.theme = dashboardPage
            ? (localStorage.getItem('smart-water-theme') || 'light')
            : 'light';
    </script>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="page" data-page="@yield('page', 'default')">
    <div class="toast-stack" data-toast-stack aria-live="polite" aria-atomic="true"></div>
    <div class="backdrop backdrop-a"></div>
    <div class="backdrop backdrop-b"></div>
    <div class="backdrop backdrop-c"></div>

    <header class="topbar">
        <a href="/" class="brand">
            <span class="brand-mark"></span>
            <span>
                <strong>Smart Water</strong>
                <small>Monitoring System</small>
            </span>
        </a>

        @hasSection('nav')
        @else
            <nav class="topnav">
                <a href="/login">Login</a>
            </nav>
        @endif
    </header>

    @hasSection('nav')
        <div class="app-shell">
            <aside class="dashboard-sidebar panel">
                <div class="sidebar-brand">
                    <p class="eyebrow">@yield('title', config('app.name', 'Smart Water Monitoring'))</p>
                    <h2>Navigation</h2>
                    <p class="subtle">Use the sections on the left to switch between dashboard views.</p>
                </div>
                <nav class="sidebar-nav">
                    @yield('nav')
                </nav>
            </aside>

            <main class="shell shell-app shell-dashboard">
                @yield('content')
            </main>
        </div>
    @else
        <main class="shell shell-app">
            @yield('content')
        </main>
    @endif
</body>
</html>
