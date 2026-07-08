<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Smart Water Monitoring'))</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="page" data-page="@yield('page', 'default')">
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

        <nav class="topnav">
            @hasSection('nav')
                @yield('nav')
            @else
                <a href="/login">Login</a>
            @endif
        </nav>
    </header>

    <main class="shell shell-app">
        @yield('content')
    </main>
</body>
</html>
