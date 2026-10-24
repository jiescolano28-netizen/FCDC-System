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
<body @class(['app-body', 'tax-report-body' => request()->routeIs('tax.report'), 'financial-statements-body' => request()->routeIs('accounting.financial-statements'), 'trial-balance-body' => request()->routeIs('accounting.trial-balance'), 'disclosure-body' => request()->routeIs('accounting.notes-to-financial-statements'), 'profitability-body' => request()->routeIs('accounting.sales-inventory-profitability')])>
    @if (config('demo.enabled'))
        <div class="synthetic-demo-banner" role="note" data-testid="synthetic-demo-banner">
            <strong>SYNTHETIC DEMO DATA</strong>
            <span>Generated sample records for capstone testing only. These are not FCDC official financial balances, records, or approvals. Do not file, audit, or use them in production.</span>
        </div>
    @endif
    <div class="app-shell">
        <aside class="sidebar" id="app-sidebar">
            <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="true" title="Collapse sidebar" aria-label="Collapse sidebar">&#10094;</button>

            @can('dashboard.view')
                <a class="brand" href="{{ route('dashboard') }}" aria-label="Fabellon Construction dashboard">
                    <img src="{{ asset('image/company-logo.png') }}" class="company-logo" alt="">
                    <span class="brand-text">
                        <span class="brand-name">Fabellon Construction</span>
                        <span class="brand-sub">and Development Corp.</span>
                    </span>
                </a>
            @else
                <div class="brand" aria-label="Fabellon Construction">
                    <img src="{{ asset('image/company-logo.png') }}" class="company-logo" alt="">
                    <span class="brand-text">
                        <span class="brand-name">Fabellon Construction</span>
                        <span class="brand-sub">and Development Corp.</span>
                    </span>
                </div>
            @endcan

            <nav class="nav" aria-label="Application navigation">
                
                @can('dashboard.view')
                    <a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                        <span class="nav-icon" aria-hidden="true">▦</span><span class="nav-label">Dashboard Update</span>
                    </a>
                @endcan
                @can('reports.view')
                    <a class="nav-item {{ request()->routeIs('reports') ? 'active' : '' }}" href="{{ route('reports') }}" @if (request()->routeIs('reports')) aria-current="page" @endif>
                        <span class="nav-icon" aria-hidden="true">▤</span><span class="nav-label">Reports</span>
                    </a>
                @endcan

                @can('pos.view')
                    <a class="nav-item {{ request()->routeIs('pos') ? 'active' : '' }}" href="{{ route('pos') }}" @if (request()->routeIs('pos')) aria-current="page" @endif>
                        <span class="nav-icon" aria-hidden="true">▣</span><span class="nav-label">Point of sale</span>
                    </a>
                @endcan

                @can('inventory.view')
                    <div class="nav-group">
                        <button class="nav-item nav-parent {{ request()->routeIs('inventory.*') ? 'active' : '' }}" type="button" data-dropdown-toggle="inventory-menu" aria-controls="inventory-menu" aria-expanded="{{ request()->routeIs('inventory.*') ? 'true' : 'false' }}">
                            <span class="nav-icon" aria-hidden="true">▧</span><span class="nav-label">Inventory</span><span class="nav-arrow" aria-hidden="true">&#9662;</span>
                        </button>
                        <div class="nav-dropdown {{ request()->routeIs('inventory.*') ? 'open' : '' }}" id="inventory-menu">
                            <a class="nav-item nav-child {{ request()->routeIs('inventory.overview') ? 'active' : '' }}" href="{{ route('inventory.overview') }}" @if (request()->routeIs('inventory.overview')) aria-current="page" @endif>
                                <span class="nav-label">Inventory overview</span>
                            </a>
                            <a class="nav-item nav-child {{ request()->routeIs('inventory.management') ? 'active' : '' }}" href="{{ route('inventory.management') }}" @if (request()->routeIs('inventory.management')) aria-current="page" @endif>
                                <span class="nav-label">Manage inventory</span>
                            </a>
                            <a class="nav-item nav-child {{ request()->routeIs('inventory.batch-categories') ? 'active' : '' }}" href="{{ route('inventory.batch-categories') }}" @if (request()->routeIs('inventory.batch-categories')) aria-current="page" @endif>
                                <span class="nav-label">Batch categories</span>
                            </a>
                            <a class="nav-item nav-child {{ request()->routeIs('inventory.reworking-cost-records') ? 'active' : '' }}" href="{{ route('inventory.reworking-cost-records') }}" @if (request()->routeIs('inventory.reworking-cost-records')) aria-current="page" @endif>
                                <span class="nav-label">Reworking Cost Record</span>
                            </a>
                            <a class="nav-item nav-child {{ request()->routeIs('inventory.operating-cost-records') ? 'active' : '' }}" href="{{ route('inventory.operating-cost-records') }}" @if (request()->routeIs('inventory.operating-cost-records')) aria-current="page" @endif>
                                <span class="nav-label">Operating Cost Record</span>
                            </a>
                        </div>
                    </div>
                @endcan
                @php($taxNavigationActive = request()->routeIs('tax.*'))
                @can('tax.view')
                    <div class="nav-group">
                        <button class="nav-item nav-parent {{ $taxNavigationActive ? 'active' : '' }}" type="button" data-dropdown-toggle="tax-menu" aria-controls="tax-menu" aria-expanded="{{ $taxNavigationActive ? 'true' : 'false' }}">
                            <span class="nav-icon" aria-hidden="true">▤</span><span class="nav-label">VAT Compliance</span><span class="nav-arrow" aria-hidden="true">&#9662;</span>
                        </button>
                        <div class="nav-dropdown {{ $taxNavigationActive ? 'open' : '' }}" id="tax-menu">
                            <a class="nav-item nav-child {{ request()->routeIs('tax.vat-records') ? 'active' : '' }}" href="{{ route('tax.vat-records') }}" @if (request()->routeIs('tax.vat-records')) aria-current="page" @endif>
                                <span class="nav-label">VAT Records</span>
                            </a>
                            <a class="nav-item nav-child {{ request()->routeIs('tax.vat-summary') ? 'active' : '' }}" href="{{ route('tax.vat-summary') }}" @if (request()->routeIs('tax.vat-summary')) aria-current="page" @endif>
                                <span class="nav-label">VAT Summary</span>
                            </a>
                            <a class="nav-item nav-child {{ request()->routeIs('tax.report') ? 'active' : '' }}" href="{{ route('tax.report') }}" @if (request()->routeIs('tax.report')) aria-current="page" @endif>
                                <span class="nav-label">VAT Report</span>
                            </a>
                            <a class="nav-item nav-child {{ request()->routeIs('tax.vat-return-preparation') ? 'active' : '' }}" href="{{ route('tax.vat-return-preparation') }}" @if (request()->routeIs('tax.vat-return-preparation')) aria-current="page" @endif>
                                <span class="nav-label">VAT Return Preparation</span>
                            </a>
                        </div>
                    </div>
                @endcan
                @php($accountingNavigationActive = request()->routeIs('accounting.*'))
                @can('accounting.view')
                    <div class="nav-group">
                        <button class="nav-item nav-parent {{ $accountingNavigationActive ? 'active' : '' }}" type="button" data-dropdown-toggle="accounting-menu" aria-controls="accounting-menu" aria-expanded="{{ $accountingNavigationActive ? 'true' : 'false' }}">
                            <span class="nav-icon" aria-hidden="true">▤</span><span class="nav-label">Accounting</span><span class="nav-arrow" aria-hidden="true">&#9662;</span>
                        </button>
                        <div class="nav-dropdown {{ $accountingNavigationActive ? 'open' : '' }}" id="accounting-menu">
                            <a class="nav-item nav-child {{ request()->routeIs('accounting.chart-of-accounts') ? 'active' : '' }}" href="{{ route('accounting.chart-of-accounts') }}" @if (request()->routeIs('accounting.chart-of-accounts')) aria-current="page" @endif>
                                <span class="nav-icon" aria-hidden="true">▦</span><span class="nav-label">Chart of Accounts</span>
                            </a>
                            <a class="nav-item nav-child {{ request()->routeIs('accounting.journal-entry') ? 'active' : '' }}" href="{{ route('accounting.journal-entry') }}" @if (request()->routeIs('accounting.journal-entry')) aria-current="page" @endif>
                                <span class="nav-icon" aria-hidden="true">✎</span><span class="nav-label">Journal Entry</span>
                            </a>
                            <a class="nav-item nav-child {{ request()->routeIs('accounting.general-ledger') ? 'active' : '' }}" href="{{ route('accounting.general-ledger') }}" @if (request()->routeIs('accounting.general-ledger')) aria-current="page" @endif>
                                <span class="nav-icon" aria-hidden="true">☰</span><span class="nav-label">General Ledger</span>
                            </a>
                            <a class="nav-item nav-child {{ request()->routeIs('accounting.trial-balance') ? 'active' : '' }}" href="{{ route('accounting.trial-balance') }}" @if (request()->routeIs('accounting.trial-balance')) aria-current="page" @endif>
                                <span class="nav-icon" aria-hidden="true">⚖</span><span class="nav-label">Trial Balance</span>
                            </a>
                            <a class="nav-item nav-child {{ request()->routeIs('accounting.sales-inventory-profitability') ? 'active' : '' }}" href="{{ route('accounting.sales-inventory-profitability') }}" @if (request()->routeIs('accounting.sales-inventory-profitability')) aria-current="page" @endif>
                                <span class="nav-icon" aria-hidden="true">↗</span><span class="nav-label">Sales and Inventory Profitability Report</span>
                            </a>
                        </div>
                    </div>
                @endcan
                @php($settingsNavigationActive = request()->routeIs('settings', 'employees.*', 'roles.*', 'activity-log'))

                @if (auth()->user()->can('settings.view') || auth()->user()->can('employees.view') || auth()->user()->can('roles.view') || auth()->user()->can('activity-log.view'))
                    <div class="nav-group">
                        <button class="nav-item nav-parent {{ $settingsNavigationActive ? 'active' : '' }}" type="button" data-dropdown-toggle="settings-menu" aria-controls="settings-menu" aria-expanded="{{ $settingsNavigationActive ? 'true' : 'false' }}">
                            <span class="nav-icon" aria-hidden="true">⚙</span><span class="nav-label">Settings</span><span class="nav-arrow" aria-hidden="true">&#9662;</span>
                        </button>
                        <div class="nav-dropdown {{ $settingsNavigationActive ? 'open' : '' }}" id="settings-menu">
                            @can('settings.view')
                                <a class="nav-item nav-child {{ request()->routeIs('settings') ? 'active' : '' }}" href="{{ route('settings') }}" @if (request()->routeIs('settings')) aria-current="page" @endif>
                                    <span class="nav-label">Company settings</span>
                                </a>
                            @endcan
                            @can('employees.view')
                                <a class="nav-item nav-child {{ request()->routeIs('employees.*') ? 'active' : '' }}" href="{{ route('employees.index') }}" @if (request()->routeIs('employees.*')) aria-current="page" @endif>
                                    <span class="nav-icon" aria-hidden="true">♙</span><span class="nav-label">Employees</span>
                                </a>
                            @endcan
                            @can('roles.view')
                                <a class="nav-item nav-child {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}" @if (request()->routeIs('roles.*')) aria-current="page" @endif>
                                    <span class="nav-icon" aria-hidden="true">⚿</span><span class="nav-label">Roles</span>
                                </a>
                            @endcan
                            @can('activity-log.view')
                                <a class="nav-item nav-child {{ request()->routeIs('activity-log') ? 'active' : '' }}" href="{{ route('activity-log') }}" @if (request()->routeIs('activity-log')) aria-current="page" @endif>
                                    <span class="nav-icon" aria-hidden="true">◷</span><span class="nav-label">Activity log</span>
                                </a>
                            @endcan
                        </div>
                    </div>
                @endif
            </nav>

            <footer class="sidebar-footer">
                <a class="sidebar-profile-link {{ request()->routeIs('profile') ? 'active' : '' }}" href="{{ route('profile') }}" @if (request()->routeIs('profile')) aria-current="page" @endif aria-label="View profile for {{ auth()->user()->username }}">
                    <span class="sidebar-profile-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->username, 0, 1)) }}</span>
                    <span class="sidebar-footer-text">
                        <span class="sidebar-profile-caption">Signed in as</span>
                        <strong>{{ auth()->user()->username }}</strong>
                    </span>
                    <svg class="sidebar-profile-arrow" aria-hidden="true" viewBox="0 0 20 20" fill="none"><path d="m7.5 4.5 5.5 5.5-5.5 5.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
                <form class="logout-form" action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="logout-button" type="submit">Log out</button>
                </form>
            </footer>
        </aside>

        <main @class(['app-main', 'inventory-main' => request()->routeIs('inventory.management', 'inventory.management.show', 'inventory.overview')])>
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
