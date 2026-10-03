<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - SiteStock</title>
    @livewireStyles
    <style>
        :root { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #333; background: #f2f5f2; }
        body { margin: 0; }
        header { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 16px max(24px, calc((100% - 1280px) / 2)); background: #1f5b2c; color: white; }
        header a, header button { color: white; }
        header nav { display: flex; gap: 16px; align-items: center; }
        header button { background: transparent; border: 1px solid #c9a227; padding: 8px 12px; border-radius: 6px; cursor: pointer; }
        main { max-width: 1280px; margin: 28px auto; padding: 0 24px; }
        .toolbar, .columns, .actions { display: flex; gap: 12px; align-items: end; flex-wrap: wrap; }
        .panel { background: white; border: 1px solid #dce7dc; border-radius: 10px; padding: 18px; margin-bottom: 18px; }
        .fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; }
        input, select { width: 100%; box-sizing: border-box; padding: 9px; border: 1px solid #cbd5cb; border-radius: 6px; }
        button.primary { border: 0; background: #1f5b2c; color: white; padding: 10px 14px; border-radius: 6px; cursor: pointer; }
        button.secondary, button.danger { border: 1px solid #d3dcd3; background: white; padding: 7px 10px; border-radius: 6px; cursor: pointer; }
        button.danger { color: #b91c1c; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px 9px; text-align: left; border-bottom: 1px solid #edf1ed; white-space: nowrap; }
        th { color: #4d4d4d; font-size: 12px; }
        .status { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 12px; background: #e3ede1; color: #1f5b2c; }
        .status.warning { background: #fef3c7; color: #92400e; }
        .status.empty { background: #fee2e2; color: #b91c1c; }
        .error { color: #b91c1c; font-size: 12px; }
        .muted { color: #666; }
        @media (max-width: 600px) { header { align-items: flex-start; flex-direction: column; } main { margin-top: 18px; padding: 0 12px; } }
    </style>
</head>
<body>
<header>
    <strong>SiteStock · Inventory</strong>
    <nav>
        <a href="{{ route('dashboard') }}">Dashboard</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Log out</button>
        </form>
    </nav>
</header>
<main>{{ $slot }}</main>
@livewireScripts
</body>
</html>
