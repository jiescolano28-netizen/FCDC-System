<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'SiteStock' }} | Fabellon Construction</title>
    @vite(['resources/css/shell.css', 'resources/js/shell.js'])
    @livewireStyles
</head>
<body class="app-body">
    <div class="app-shell">
        <aside class="sidebar" id="app-sidebar">
            <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="true" title="Collapse sidebar" aria-label="Collapse sidebar">&#10094;</button>

            <a class="brand" href="{{ route('dashboard') }}" aria-label="Fabellon Construction dashboard">
                <img src="{{ asset('image/company-logo.png') }}" class="company-logo" alt="">
                <span class="brand-text">
                    <span class="brand-name">Fabellon Construction</span>
                    <span class="brand-sub">and Development Corp.</span>
                </span>
            </a>

            <nav class="nav" aria-label="Application navigation">
                <a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                    <span class="nav-icon" aria-hidden="true">▦</span><span class="nav-label">Dashboard</span>
                </a>

                <div class="nav-group">
                    <button class="nav-item nav-parent {{ request()->routeIs('inventory.*') ? 'active' : '' }}" type="button" data-dropdown-toggle="inventory-menu" aria-controls="inventory-menu" aria-expanded="{{ request()->routeIs('inventory.*') ? 'true' : 'false' }}">
                        <span class="nav-icon" aria-hidden="true">▧</span><span class="nav-label">Inventory</span><span class="nav-arrow" aria-hidden="true">&#9662;</span>
                    </button>
                    <div class="nav-dropdown {{ request()->routeIs('inventory.*') ? 'open' : '' }}" id="inventory-menu">
                        <a class="nav-item nav-child {{ request()->routeIs('inventory.management') ? 'active' : '' }}" href="{{ route('inventory.management') }}" @if (request()->routeIs('inventory.management')) aria-current="page" @endif>
                            <span class="nav-label">Manage inventory</span>
                        </a>
                    </div>
                </div>

                <a class="nav-item {{ request()->routeIs('settings') ? 'active' : '' }}" href="{{ route('settings') }}" @if (request()->routeIs('settings')) aria-current="page" @endif>
                    <span class="nav-icon" aria-hidden="true">⚙</span><span class="nav-label">Settings</span>
                </a>
            </nav>

            <footer class="sidebar-footer">
                <span class="sidebar-footer-text">Signed in as<strong>{{ auth()->user()->username }}</strong></span>
                <form class="logout-form" action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="logout-button" type="submit">Log out</button>
                </form>
            </footer>
        </aside>

        <main class="app-main">
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
