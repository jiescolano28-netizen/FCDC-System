<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SiteStock - Construction Management</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <style>
      :root{
        --emerald-950:#163f1e;
        --emerald-900:#1F5B2C;  /* Dark Green — sidebar / primary buttons */
        --emerald-800:#245332;
        --emerald-700:#3F7D3A;  /* Medium Green — accents / highlights */
        --emerald-600:#3F7D3A;
        --emerald-200:#bcd6b8;
        --emerald-100:#e3ede1;
        --emerald-50:#F2F2F2;   /* Light Gray — cards / background areas */
        --amber-100:#fef3c7;
        --amber-800:#92400e;
        --gold:#C9A227;         /* Gold/Yellow — small logo / accent */
        --gold-dark:#a3841e;
        --slate-900:#333333;    /* Dark Gray — text */
        --slate-700:#4d4d4d;
        --slate-500:#666666;
        --slate-400:#8c8c8c;
        --slate-200:#dcdcdc;
        --slate-100:#F2F2F2;
        --slate-50:#f7f7f7;
        --red-100:#fee2e2;
        --red-700:#b91c1c;
      }
      .search-icon {
        font-size: 16px;
        color: #475569;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        flex-shrink: 0;
      }
      * { box-sizing: border-box; }
      body { margin:0; font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; background: var(--emerald-50); color: var(--slate-900); }
      .app { display:flex; height:100vh; }


      /* Sidebar */
      .sidebar { width:230px; flex-shrink:0; background: var(--emerald-900); color:#F2F2F2; display:flex; flex-direction:column; transition: width 0.2s ease; position:relative; }
      .brand { display:flex; align-items:center; gap:10px; padding:18px 20px; border-bottom:1px solid var(--emerald-800); }
      .company-logo {
        width: 90px;
        height: 90px;
        object-fit: contain;
        flex-shrink: 0;
      }
      .brand-icon { background:var(--gold); color:var(--emerald-900); border-radius:8px; padding:8px; font-size:18px; }
      .brand-name { font-weight:600; color:#fff; font-size:15px; }
      .brand-sub { font-size:11px; color:var(--emerald-200); }
      .nav { flex:1; padding:12px; display:flex; flex-direction:column; gap:4px; }
      .nav-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:8px; font-size:14px; font-weight:500; color:var(--emerald-200); cursor:pointer; border:none; background:none; width:100%; text-align:left; }
      .nav-item:hover { background: var(--emerald-800); color:#fff; }
      .nav-item.active { background: var(--emerald-700); color:#fff; }
      .nav-icon { width:18px; text-align:center; }
      .nav-group { width:100%; }
      .nav-parent { position:relative; }
      .nav-arrow { font-size:10px; }
      .nav-dropdown { display:none; padding-left:14px; }
      .nav-dropdown.open { display:block; }
      .nav-child { font-size:13px; padding:8px 12px 8px 14px; }
      .nav-child.active { background:var(--emerald-700); color:#fff; }


      .sidebar-footer { padding:16px 20px; border-top:1px solid var(--emerald-800); font-size:11px; color:var(--emerald-200); }
      .sidebar-footer strong { color:#fff; display:block; font-size:13px; margin-top:2px; }
      .logout-form { margin-top:12px; }
      .logout-button {
        width:100%;
        padding:7px 10px;
        border:1px solid var(--gold);
        border-radius:6px;
        background:transparent;
        color:#fff;
        font-size:12px;
        cursor:pointer;
      }
      .logout-button:hover { background:var(--gold-dark); }
      .sidebar.collapsed .logout-form { display:none; }

      /* Collapsed sidebar state */
      .sidebar.collapsed { width:68px; }
      .sidebar.collapsed .brand { padding:18px 10px; justify-content:center; }
      .sidebar.collapsed .company-logo { width:36px; height:36px; }
      .sidebar.collapsed .brand-text,
      .sidebar.collapsed .nav-label,
      .sidebar.collapsed .nav-arrow,
      .sidebar.collapsed .nav-dropdown,
      .sidebar.collapsed .sidebar-footer-text { display:none; }
      .sidebar.collapsed .nav-item { justify-content:center; padding:10px 0; }
      .sidebar.collapsed .nav-item .nav-icon { width:auto; }
      .sidebar.collapsed .sidebar-footer { display:flex; justify-content:center; padding:16px 8px; }
      .sidebar-toggle {
        position:absolute; top:18px; right:-12px;
        width:24px; height:24px; border-radius:50%;
        background:var(--emerald-700); color:#fff; border:2px solid var(--emerald-50);
        display:flex; align-items:center; justify-content:center;
        cursor:pointer; font-size:11px; z-index:5;
      }
      .sidebar-toggle:hover { background: var(--gold-dark); }


      /* Main */
      main { flex:1; overflow-y:auto; padding:24px 28px; }
      .view { display:none; }
      .view.active { display:block; }
      h1 { font-size:20px; font-weight:600; margin:0; }
      .subtitle { font-size:13px; color:var(--slate-500); margin:4px 0 20px; }


      .grid { display:grid; gap:16px; }
      .grid-4 { grid-template-columns: repeat(4, 1fr); }
      .grid-3 { grid-template-columns: repeat(3, 1fr); }
      .grid-2 { grid-template-columns: repeat(2, 1fr); }
      .col-span-2 { grid-column: span 2; }


      .card { background:#fff; border:1px solid var(--emerald-100); border-radius:12px; padding:16px; box-shadow:0 1px 2px rgba(0,0,0,0.03); }
      .stat-card { display:flex; gap:12px; align-items:flex-start; }
      .stat-icon { border-radius:8px; padding:10px; background:var(--emerald-50); color:var(--emerald-700); font-size:16px; }
      .stat-icon.amber { background:var(--amber-100); color:var(--amber-800); }
      .stat-label { font-size:11px; font-weight:600; color:var(--slate-500); text-transform:uppercase; letter-spacing:0.03em; }
      .stat-value { font-size:22px; font-weight:600; margin-top:2px; }
      .stat-sub { font-size:12px; color:var(--slate-400); margin-top:2px; }


      .card-title { font-size:13px; font-weight:600; color:var(--slate-700); margin:0 0 12px; }


      table { width:100%; border-collapse:collapse; font-size:13px; }
      thead tr { background: var(--emerald-50); color: var(--emerald-800); text-transform:uppercase; font-size:11px; letter-spacing:0.03em; }
      th, td { padding:10px 12px; text-align:left; }
      th.right, td.right { text-align:right; }
      tbody tr { border-top:1px solid var(--slate-100); }
      tbody tr:hover { background: var(--slate-50); }


      .badge { display:inline-flex; align-items:center; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:600; }
      .badge.emerald { background: var(--emerald-100); color: var(--emerald-700); }
      .badge.amber { background: var(--amber-100); color: var(--amber-800); }
      .badge.slate { background: var(--slate-100); color: var(--slate-500); }
      .badge.red { background: var(--red-100); color: var(--red-700); }


      .btn { display:inline-flex; align-items:center; gap:6px; padding:9px 16px; border-radius:8px; font-size:13px; font-weight:600; border:none; cursor:pointer; }
      .btn-primary { background: var(--emerald-700); color:#fff; }
      .btn-primary:hover { background: var(--emerald-800); }
      .btn-primary:disabled { background: var(--slate-200); color: var(--slate-400); cursor:not-allowed; }
      .btn-ghost { background:none; color: var(--slate-500); }
      .btn-ghost:hover { background: var(--slate-100); }
      .btn-icon { background:none; border:none; cursor:pointer; color: var(--slate-400); font-size:14px; }
      .btn-icon:hover { color: var(--red-700); }


      .toolbar { display:flex; gap:10px; margin-bottom:14px; align-items:center; }
      .search-box { display:flex; align-items:center; gap:8px; background:#fff; border:1px solid var(--slate-200); border-radius:8px; padding:8px 12px; flex:1; max-width:320px; }
      .search-box input { border:none; outline:none; font-size:13px; width:100%; }
      select, input[type=text], input[type=number] { font-size:13px; border:1px solid var(--slate-200); border-radius:8px; padding:8px 10px; }


      .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.4); display:none; align-items:center; justify-content:center; z-index:100; }
      .modal-overlay.open { display:flex; }
      .modal { background:#fff; border-radius:12px; padding:20px; width:380px; max-height:90vh; overflow-y:auto; }
      .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; }
      .modal-header p { font-weight:600; margin:0; }
      .form-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px; }
      .form-field { margin-bottom:10px; }
      .form-field label { font-size:11px; font-weight:600; color:var(--slate-500); text-transform:uppercase; display:block; margin-bottom:4px; }
      .form-field input, .form-field select { width:100%; }
      .modal-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:12px; }


      /* POS */
      .pos-layout { display:grid; grid-template-columns: 2fr 1fr; gap:16px; align-items:start; }
      .product-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; max-height:600px; overflow-y:auto; padding-right:4px; }
      .product-card { text-align:left; background:#fff; border:1px solid var(--emerald-100); border-radius:12px; padding:12px; }
      .product-card img {
    width: 100%;
    height: 180px;
    object-fit: cover;
    border-radius: 8px;
    display: block;
    margin-bottom: 10px;
}
      .product-card:hover { border-color:var(--emerald-700); box-shadow:0 1px 3px rgba(0,0,0,0.06); }
      .product-cat { font-size:11px; color: var(--emerald-700); font-weight:600; }
      .product-name { font-size:13px; margin:6px 0; line-height:1.3; }
      .product-footer { display:flex; justify-content:space-between; align-items:center; margin-top:8px; }
      .product-price { font-weight:600; font-size:13px; }
      .product-stock { font-size:11px; color: var(--slate-400); }
      .add-to-sale-btn {
        width: 100%;
        margin-top: 12px;
        padding: 9px 12px;
        border: none;
        border-radius: 6px;
        background: #3F7D3A;
        color: white;
        font-family: inherit;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
      }

      .cart-line { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; font-size:14px; padding:10px 0; border-bottom:1px solid var(--slate-50); }
      .cart-line:last-child { border-bottom:none; }
      .cart-line-name { flex:1; min-width:0; white-space:normal; word-break:break-word; line-height:1.35; }
      .cart-line-sub { font-size:12px; color:var(--slate-400); margin-top:2px; }
      .cart-lines-scroll { max-height:600px; overflow-y:auto; padding-right:4px; }
      .pos-cart-card { display:block; }
      .qty-controls { display:flex; align-items:center; gap:6px; flex-shrink:0; }
      .qty-btn { width:20px; height:20px; border-radius:4px; background:var(--slate-100); border:none; cursor:pointer; font-size:11px; }
      .cart-summary-row { display:flex; justify-content:space-between; font-size:13px; color:var(--slate-500); padding:2px 0; }
      .cart-total-row { display:flex; justify-content:space-between; font-weight:600; font-size:15px; padding-top:6px; }
      .divider { border-top:1px solid var(--slate-100); margin-top:12px; padding-top:10px; }
      .confirm-banner { margin-top:10px; background: var(--emerald-50); color: var(--emerald-700); font-size:13px; padding:8px 12px; border-radius:8px; display:flex; align-items:center; gap:6px; }


      .chart-wrap { position:relative; height:220px; }
      .chart-wrap.short { height:200px; }


      .list-row { display:flex; justify-content:space-between; align-items:center; font-size:13px; border-bottom:1px solid var(--slate-50); padding:8px 0; }
      .list-row:last-child { border-bottom:none; }
      .list-row-sub { font-size:11px; color:var(--slate-400); }


      .settings-form { max-width:560px; }

      /* Sales period toggle */
      .card-header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; gap:12px; flex-wrap:wrap; }
      .card-header-row .card-title { margin:0; }
      .period-toggle { display:inline-flex; background:var(--slate-100); border-radius:8px; padding:3px; gap:2px; }
      .period-toggle button {
        border:none; background:none; cursor:pointer;
        font-size:12px; font-weight:600; color:var(--slate-500);
        padding:6px 12px; border-radius:6px;
      }
      .period-toggle button:hover { color:var(--emerald-800); }
      .period-toggle button.active { background:#fff; color:var(--emerald-800); box-shadow:0 1px 2px rgba(0,0,0,0.08); }

      /* EDIT BTN */
      .edit-btn {
        color: var(--emerald-700);
        margin-right: 6px;
      }

      .edit-btn:hover {
        color: var(--gold-dark);
      }

      .delete-btn {
        color: var(--red-700);
      }

      .delete-btn:hover {
        color: #991b1b;
      }

      /* Accounting module only */
      .page-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; }
      .empty-accounting { text-align:center; color:var(--slate-400); padding:24px; }

      /* Journal Entry alignment */
      #journal-lines th,
      #journal-lines td {
        vertical-align: middle;
      }

      #journal-lines th:nth-child(2),
      #journal-lines th:nth-child(3),
      #journal-lines td:nth-child(2),
      #journal-lines td:nth-child(3) {
        text-align: right;
      }

      #journal-lines .je-debit,
      #journal-lines .je-credit {
        width: 130px;
        text-align: right;
      }

      #journal-lines .je-account {
        width: 100%;
      }

      #journal-lines .je-line-description {
        width: 100%;
        min-width: 180px;
      }

      /* Receipt */
      .receipt-modal { width: 320px; }
      .receipt-body { font-size: 13px; }
      .receipt-header { text-align: center; margin-bottom: 12px; }
      .receipt-header .receipt-company { font-weight: 700; font-size: 15px; }
      .receipt-header div { color: var(--slate-500); font-size: 11px; }
      .receipt-meta { display:flex; justify-content:space-between; font-size:11px; color:var(--slate-500); margin-bottom:8px; }
      .receipt-lines { width:100%; border-top:1px dashed var(--slate-200); border-bottom:1px dashed var(--slate-200); padding:8px 0; margin-bottom:8px; }
      .receipt-lines td { padding:4px 0; font-size:12px; }
      .receipt-line-name { color: var(--slate-900); }
      .receipt-line-sub { font-size:10px; color:var(--slate-400); }
      .receipt-totals-row { display:flex; justify-content:space-between; font-size:12px; padding:2px 0; }
      .receipt-totals-row.grand { font-weight:700; font-size:14px; margin-top:4px; }
      .receipt-footer { text-align:center; margin-top:14px; font-size:11px; color:var(--slate-400); }

      @media print {
        body * { visibility: hidden; }
        #receipt-print, #receipt-print * { visibility: visible; }
        #receipt-print {
          position: absolute; left:0; top:0; width:100%;
          padding: 20px;
        }
      }
      /* Tax Compliance frontend */
      .tax-page-toolbar {
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:16px;
        margin-bottom:14px;
        flex-wrap:wrap;
      }
      .toolbar-actions {
        display:flex;
        gap:8px;
        align-items:center;
        flex-wrap:wrap;
      }
      .print-document-shell {
        padding:24px;
      }
      .print-document {
        background:#fff;
      }
      .print-company-header {
        text-align:center;
        padding-bottom:16px;
        border-bottom:2px solid var(--emerald-900);
        margin-bottom:16px;
        line-height:1.6;
      }
      .print-company-name {
        font-size:18px;
        font-weight:700;
        color:var(--emerald-900);
      }
      .print-report-meta {
        display:grid;
        grid-template-columns:repeat(3, 1fr);
        gap:12px;
        margin-bottom:16px;
        font-size:12px;
      }
      .print-summary {
        margin-top:16px;
        margin-left:auto;
        width:360px;
        max-width:100%;
        border-top:2px solid var(--slate-200);
      }
      .print-summary div {
        display:flex;
        justify-content:space-between;
        padding:8px 0;
        border-bottom:1px solid var(--slate-100);
        font-size:13px;
      }
      .print-summary strong {
        color:var(--emerald-900);
      }
      .print-note {
        margin-top:18px;
        font-size:11px;
        color:var(--slate-500);
        line-height:1.5;
      }
      .form-section-title {
        font-size:12px;
        font-weight:700;
        color:var(--emerald-800);
        text-transform:uppercase;
        letter-spacing:0.03em;
        border-bottom:1px solid var(--emerald-100);
        padding-bottom:6px;
        margin:18px 0 12px;
      }
      .form-grid {
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:12px;
      }
      .form-grid .form-field {
        margin-bottom:0;
      }
      .form-grid input {
        width:100%;
      }

      @media (max-width: 800px) {
        .print-report-meta,
        .form-grid { grid-template-columns:1fr; }
        .print-summary { width:100%; }
      }

      @media (max-width: 800px) {
        .app {
          height:auto;
          min-height:100vh;
          flex-direction:column;
        }
        .sidebar,
        .sidebar.collapsed {
          width:100%;
          flex-shrink:0;
        }
        .sidebar-toggle,
        .sidebar.collapsed .brand-text,
        .sidebar.collapsed .nav-label,
        .sidebar.collapsed .nav-arrow,
        .sidebar.collapsed .nav-dropdown,
        .sidebar.collapsed .sidebar-footer-text,
        .sidebar.collapsed .logout-form {
          display:none;
        }
        .brand,
        .sidebar.collapsed .brand {
          padding:8px 16px;
          justify-content:flex-start;
        }
        .company-logo,
        .sidebar.collapsed .company-logo {
          width:44px;
          height:44px;
        }
        .nav {
          flex-direction:row;
          overflow-x:auto;
          padding:8px;
        }
        .nav-group { width:auto; flex-shrink:0; }
        .nav-item { width:auto; flex-shrink:0; white-space:nowrap; }
        .nav-dropdown.open {
          position:absolute;
          z-index:6;
          min-width:200px;
          background:var(--emerald-900);
        }
        .sidebar-footer {
          display:flex;
          align-items:center;
          justify-content:space-between;
          gap:12px;
          padding:10px 16px;
        }
        .logout-form { margin:0; }
        .logout-button { width:auto; min-width:90px; }
        main {
          min-width:0;
          overflow-x:hidden;
          padding:16px;
        }
        .grid-4,
        .grid-3 { grid-template-columns:repeat(2, minmax(0, 1fr)); }
        .grid-2 { grid-template-columns:repeat(2, minmax(0, 1fr)); }
      }

      @media (max-width: 520px) {
        .grid-4,
        .grid-3,
        .grid-2 { grid-template-columns:minmax(0, 1fr); }
        .col-span-2 { grid-column:auto; }
        .toolbar { flex-wrap:wrap; }
      }

      @media print {
        body.print-tax-report * {
          visibility:hidden !important;
        }
        body.print-tax-report #tax-report-print,
        body.print-tax-report #tax-report-print * {
          visibility:visible !important;
        }
        body.print-tax-report #tax-report-print {
          position:absolute;
          left:0;
          top:0;
          width:100%;
          padding:20px;
        }

        body.print-vat-return * {
          visibility:hidden !important;
        }
        body.print-vat-return #vat-return-print,
        body.print-vat-return #vat-return-print * {
          visibility:visible !important;
        }
        body.print-vat-return #vat-return-print {
          position:absolute;
          left:0;
          top:0;
          width:100%;
          padding:20px;
        }

        body.print-tax-report .print-document,
        body.print-vat-return .print-document {
          box-shadow:none !important;
          border:none !important;
        }
      }


      #image-viewer-overlay { background: rgba(0,0,0,0.85); }
    </style>
  </head>
  <body>
    <div class="app">
      <!-- Sidebar -->
      <aside class="sidebar" id="sidebar">
        <button class="sidebar-toggle" id="sidebar-toggle" onclick="toggleSidebar()" title="Collapse sidebar">&#10094;</button>
        <div class="brand">
          <img src="{{ asset('image/company-logo.png') }}" class="company-logo" alt="Company Logo">
          <div class="brand-text">
            <div class="brand-name">Fabellon Construction</div>
            <div class="brand-sub">and Development Corp.</div>
          </div>
        </div>
        <nav class="nav" id="nav"></nav>
        <div class="sidebar-footer">
          <span class="sidebar-footer-text">Signed in as
            <strong>{{ auth()->user()->username }}</strong></span>
          <form class="logout-form" action="{{ route('logout') }}" method="POST">
            @csrf
            <button class="logout-button" type="submit">Log out</button>
          </form>
        </div>

      </aside>
      <!-- Main -->
      <main>

        <!-- Dashboard -->
        <section class="view active" id="view-dashboard">
          <h1>Dashboard</h1>
          <p class="subtitle">Overview of stock, sales, and site activity.</p>
          <div class="grid grid-4" id="dashboard-stats"></div>
          <div class="grid grid-3" style="margin-top:16px;">
            <div class="card col-span-2">
              <div class="card-header-row">
                <p class="card-title" id="sales-chart-title">Sales this week</p>
                <div class="period-toggle" id="sales-period-toggle">
                  <button type="button" data-period="week" class="active" onclick="setSalesPeriod('week')">Week</button>
                  <button type="button" data-period="month" onclick="setSalesPeriod('month')">Month</button>
                  <button type="button" data-period="year" onclick="setSalesPeriod('year')">Year</button>
                </div>
              </div>
              <div class="chart-wrap"><canvas id="chart-weekly-sales"></canvas></div>
            </div>
            <div class="card">
              <p class="card-title">Low stock alerts</p>
              <div id="low-stock-list"></div>
            </div>
          </div>
          <div class="card" style="margin-top:16px;">
            <p class="card-title">Inventory value by category</p>
            <div class="chart-wrap short"><canvas id="chart-category-value"></canvas></div>
          </div>
        </section>




        <!-- POS -->
        <section class="view" id="view-pos">
          <h1>Point of sale</h1>
          <p class="subtitle">Ring up materials for a walk-in customer or job pickup.</p>
          <div class="pos-layout">
            <div>
              <div class="search-box" style="max-width:none; margin-bottom:12px;">
                <span class="search-icon">⌕</span>
                <input type="text" id="pos-search" placeholder="Search materials to sell..." oninput="renderProductGrid()" />
              </div>
              <div class="product-grid" id="product-grid"></div>
            </div>
            <div class="card pos-cart-card">
              <p class="card-title">Current Sale</p>
              <div class="cart-lines-scroll"><div id="cart-lines"></div></div>
              <div class="divider">
                <div class="cart-summary-row"><span>Subtotal</span><span id="cart-subtotal">₱0.00</span></div>
                <div class="cart-summary-row"><span>VAT (12%)</span><span id="cart-tax">₱0.00</span></div>
                <div class="cart-total-row"><span>Total</span><span id="cart-total">₱0.00</span></div>
              </div>
              <button class="btn btn-primary" id="checkout-btn" style="width:100%; justify-content:center; margin-top:12px;" onclick="checkout()" disabled>Charge</button>
              <div class="confirm-banner" id="confirm-banner" style="display:none;"></div>
            </div>
          </div>
        </section>


        <!-- Tax Compliance -->
        <section class="view" id="view-vat-records">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
            <div>
              <h1>VAT Records</h1>
              <p class="subtitle">View and monitor VAT-related transaction records. Interface data is sample data for frontend demonstration only.</p>
            </div>
            <div class="badge emerald" style="font-size:12px; padding:6px 12px;">12% VAT</div>
          </div>

          <div class="toolbar">
            <div class="search-box">
              <span class="search-icon">⌕</span>
              <input type="text" id="vat-record-search" placeholder="Search reference no. or tax period..." oninput="renderVatRecords()" />
            </div>
            <select id="vat-period-filter" onchange="renderVatRecords()">
              <option value="All">All tax periods</option>
              <option value="Q1 2026">Q1 2026</option>
              <option value="Q2 2026">Q2 2026</option>
              <option value="Q3 2026">Q3 2026</option>
            </select>
            <input type="date" id="vat-date-filter" onchange="renderVatRecords()" title="Filter by transaction date" />
          </div>

          <div class="card" style="padding:0; overflow:hidden;">
            <div style="padding:16px 16px 0;">
              <p class="card-title">VAT Transaction Records</p>
              <p style="color:var(--slate-500); font-size:13px; margin-top:4px;">
                Placeholder records prepared for later backend integration.
              </p>
            </div>
            <div style="overflow-x:auto; margin-top:16px;">
              <table>
                <thead>
                  <tr>
                    <th>Transaction/Reference No.</th>
                    <th>Transaction Date</th>
                    <th>Tax Period</th>
                    <th class="right">Taxable Sales</th>
                    <th>VAT Rate</th>
                    <th class="right">VAT Amount</th>
                    <th class="right">Total Sales</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody id="vat-records-tbody"></tbody>
              </table>
            </div>
            <div id="vat-records-pagination" style="display:flex; justify-content:flex-end; padding:12px 16px; color:var(--slate-500); font-size:12px;"></div>
          </div>
        </section>

        <!-- VAT Summary -->
        <section class="view" id="view-vat-summary">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
            <div>
              <h1>VAT Summary</h1>
              <p class="subtitle">Summary of VAT information for the selected reporting period.</p>
            </div>
            <div class="period-toggle" id="vat-summary-period-toggle">
              <button type="button" data-period="monthly" class="active" onclick="setVatSummaryPeriod('monthly')">Monthly</button>
              <button type="button" data-period="quarterly" onclick="setVatSummaryPeriod('quarterly')">Quarterly</button>
            </div>
          </div>

          <div class="card" style="margin-bottom:16px;">
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
              <label style="font-size:11px; font-weight:600; color:var(--slate-500); text-transform:uppercase;">Selected Tax Period</label>
              <select id="vat-summary-period" onchange="renderVatSummary()"></select>
              <span class="badge emerald">12% VAT</span>
            </div>
          </div>

          <div class="grid grid-4" id="vat-summary-stats"></div>

          <div class="card" style="margin-top:16px;">
            <p class="card-title">VAT Reporting Summary</p>
            <table>
              <tbody>
                <tr><td>Total Taxable Sales</td><td class="right" id="vat-summary-taxable">₱0.00</td></tr>
                <tr><td>VAT Rate</td><td class="right">12%</td></tr>
                <tr><td>Total VAT Amount</td><td class="right" id="vat-summary-vat">₱0.00</td></tr>
                <tr><td><strong>Total Sales</strong></td><td class="right"><strong id="vat-summary-total">₱0.00</strong></td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Tax Report -->
        <section class="view" id="view-tax-report">
          <div class="tax-page-toolbar">
            <div>
              <h1>Tax Report</h1>
              <p class="subtitle">Printable internal VAT tax report using placeholder frontend data.</p>
            </div>
            <div class="toolbar-actions">
              <select id="tax-report-period" onchange="renderTaxReport()">
                <option value="Q3 2026">Q3 2026</option>
                <option value="Q2 2026">Q2 2026</option>
                <option value="Q1 2026">Q1 2026</option>
              </select>
              <button class="btn btn-primary" onclick="printTaxReport()">Print Report</button>
            </div>
          </div>

          <div class="card print-document-shell">
            <div id="tax-report-print" class="print-document">
              <div class="print-company-header">
                <div class="print-company-name">Fabellon Construction and Development Corporation</div>
                <div>San Mateo, Rizal</div>
                <div>VAT Tax Report</div>
              </div>

              <div class="print-report-meta">
                <div><strong>Report Title:</strong> VAT Tax Report</div>
                <div><strong>Reporting Period:</strong> <span id="tax-report-print-period">Q3 2026</span></div>
                <div><strong>Date Prepared:</strong> <span id="tax-report-print-date">2026-10-02</span></div>
              </div>

              <div style="overflow-x:auto;">
                <table>
                  <thead>
                    <tr>
                      <th>Transaction/Reference No.</th>
                      <th>Transaction Date</th>
                      <th>Tax Period</th>
                      <th class="right">Taxable Sales</th>
                      <th>VAT Rate</th>
                      <th class="right">VAT Amount</th>
                      <th class="right">Total Sales</th>
                    </tr>
                  </thead>
                  <tbody id="tax-report-tbody"></tbody>
                </table>
              </div>

              <div class="print-summary">
                <div><span>Total Taxable Sales</span><strong id="tax-report-taxable">₱0.00</strong></div>
                <div><span>Total VAT Amount</span><strong id="tax-report-vat">₱0.00</strong></div>
                <div><span>Total Sales</span><strong id="tax-report-total">₱0.00</strong></div>
              </div>

              <div class="print-note">This report is a frontend demonstration using placeholder data and is not connected to the POS, database, or BIR filing systems.</div>
            </div>
          </div>
        </section>

        <!-- VAT Return Preparation (BIR Form 2550Q) -->
        <section class="view" id="view-vat-return">
          <div class="tax-page-toolbar">
            <div>
              <h1>VAT Return Preparation</h1>
              <p class="subtitle">BIR Form 2550Q preparation and printing interface. This page does not file or submit to the BIR.</p>
            </div>
            <div class="toolbar-actions">
              <select id="vat-return-period" onchange="renderVatReturn()">
                <option value="Q3 2026">Q3 2026</option>
                <option value="Q2 2026">Q2 2026</option>
                <option value="Q1 2026">Q1 2026</option>
              </select>
              <button class="btn btn-primary" onclick="printVatReturn()">Print 2550Q</button>
            </div>
          </div>

          <div class="card print-document-shell">
            <div id="vat-return-print" class="print-document">
              <div class="print-company-header">
                <div class="print-company-name">Fabellon Construction and Development Corporation</div>
                <div>San Mateo, Rizal</div>
                <div>BIR Form 2550Q — Quarterly VAT Return Preparation</div>
              </div>

              <div class="form-section-title">Taxpayer / Company Information</div>
              <div class="form-grid">
                <div class="form-field"><label>Taxpayer / Company Name</label><input type="text" value="Fabellon Construction and Development Corporation" /></div>
                <div class="form-field"><label>Tax Period</label><input type="text" id="vat-return-print-period" value="Q3 2026" readonly /></div>
                <div class="form-field"><label>VAT Rate</label><input type="text" value="12%" readonly /></div>
                <div class="form-field"><label>Preparation Status</label><input type="text" value="For Review / Printing" readonly /></div>
              </div>

              <div class="form-section-title">VAT-Related Sales Information</div>
              <table>
                <thead>
                  <tr>
                    <th>Information</th>
                    <th class="right">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td>Taxable Sales</td><td class="right" id="vat-return-taxable">₱0.00</td></tr>
                  <tr><td>VAT Rate</td><td class="right">12%</td></tr>
                  <tr><td>VAT Amount</td><td class="right" id="vat-return-vat">₱0.00</td></tr>
                  <tr><td><strong>Total Sales</strong></td><td class="right"><strong id="vat-return-total">₱0.00</strong></td></tr>
                </tbody>
              </table>

              <div class="print-note" style="margin-top:16px;">
                Preparation and printing only. This interface does not replace eFPS, eBIRForms, or any official BIR filing system and does not submit any return to the BIR.
              </div>
            </div>
          </div>
        </section>

      <!-- Reports -->
      <section class="view" id="view-reports">
        <h1>Reports</h1>
        <p class="subtitle">Sales performance and stock allocation.</p>
        <div class="grid grid-3" id="reports-stats"></div>
        <div class="grid grid-2" style="margin-top:16px;">
          <div class="card">
            <p class="card-title">Sales trend</p>
            <div class="chart-wrap"><canvas id="chart-sales-trend"></canvas></div>
          </div>
          <div class="card">
            <p class="card-title">Inventory value by category</p>
            <div class="chart-wrap"><canvas id="chart-category-pie"></canvas></div>
          </div>
        </div>
        <div class="card" style="margin-top:16px; padding:0; overflow:hidden;">
          <p class="card-title" style="padding:16px 16px 0;">Recent sales</p>
          <table>
            <thead><tr><th>Sale ID</th><th>Date</th><th class="right">Items</th><th class="right">Total</th><th>Method</th></tr></thead>
            <tbody id="sales-tbody"></tbody>
          </table>
        </div>
      </section>

      <!-- Accounting -->
      <section class="view" id="view-accounting-overview">
        <h1>Accounting Overview</h1><p class="subtitle">View accounting balances and posted accounting activity.</p>
        <div class="grid grid-4">
          <div class="card stat-card"><div class="stat-icon">&#128176;</div><div><div class="stat-label">Total revenue</div><div class="stat-value" id="acct-revenue">₱0.00</div><div class="stat-sub">Posted revenue accounts</div></div></div>
          <div class="card stat-card"><div class="stat-icon amber">&#128181;</div><div><div class="stat-label">Expenses</div><div class="stat-value" id="acct-expenses">₱0.00</div><div class="stat-sub">Posted expense accounts</div></div></div>
          <div class="card stat-card"><div class="stat-icon">&#128200;</div><div><div class="stat-label">Net income</div><div class="stat-value" id="acct-net">₱0.00</div><div class="stat-sub">Revenue less expenses</div></div></div>
          <div class="card stat-card"><div class="stat-icon">&#128188;</div><div><div class="stat-label">Accounts payable</div><div class="stat-value" id="acct-payables">₱0.00</div><div class="stat-sub">Outstanding supplier balances</div></div></div>
        </div>
        <div class="grid grid-2" style="margin-top:16px;"><div class="card"><p class="card-title">Account balances</p><table><thead><tr><th>Account</th><th>Type</th><th class="right">Balance</th></tr></thead><tbody id="acct-balances"><tr><td colspan="3" class="empty-accounting">No posted accounting records available.</td></tr></tbody></table></div><div class="card"><p class="card-title">Quick actions</p><div style="display:flex;flex-wrap:wrap;gap:8px;"><button class="btn btn-primary" onclick="switchView('journal-entry')">+ Journal Entry</button><button class="btn btn-ghost" onclick="switchView('general-ledger')">General Ledger</button><button class="btn btn-ghost" onclick="switchView('trial-balance')">Trial Balance</button><button class="btn btn-ghost" onclick="switchView('financial-statements')">Financial Statements</button></div></div></div>
        <div class="card" style="margin-top:16px;padding:0;overflow:hidden;"><p class="card-title" style="padding:16px 16px 0;">Recent journal entries</p><table><thead><tr><th>Date</th><th>Reference</th><th>Description</th><th>Source</th><th class="right">Debit</th><th class="right">Credit</th></tr></thead><tbody id="acct-recent"><tr><td colspan="6" class="empty-accounting">No posted journal entries available.</td></tr></tbody></table></div>
      </section>

      <section class="view" id="view-chart-of-accounts">
        <div class="page-head"><div><h1>Chart of Accounts</h1><p class="subtitle">Reference accounts used by the accounting records and reports.</p></div><button class="btn btn-primary" onclick="alert('Chart of Accounts form is ready for backend integration.')">+ Add Account</button></div>
        <div class="toolbar"><div class="search-box"><span class="search-icon">⌕</span><input id="coa-search" placeholder="Search account code or name..." oninput="renderChartOfAccounts()" /></div><select id="coa-type" onchange="renderChartOfAccounts()"><option>All</option><option>Asset</option><option>Liability</option><option>Equity</option><option>Revenue</option><option>Expense</option></select><select id="coa-status" onchange="renderChartOfAccounts()"><option>All</option><option>Active</option><option>Inactive</option></select></div>
        <div class="card" style="padding:0;overflow:hidden;"><table><thead><tr><th>Account Code</th><th>Account Name</th><th>Type</th><th>Description</th><th>Status</th><th>Action</th></tr></thead><tbody id="coa-tbody"></tbody></table></div>
      </section>

      <section class="view" id="view-journal-entry">
        <h1>Journal Entry</h1><p class="subtitle">Create and review balanced debit and credit entries.</p>
        <div class="card"><div class="form-row"><div class="form-field"><label>Transaction Date</label><input type="date" id="je-date"></div><div class="form-field"><label>Reference No.</label><input id="je-reference" placeholder="e.g. JE-0001"></div><div class="form-field"><label>Transaction Source</label><select id="je-source"><option>Manual</option><option>POS Sale</option><option>Accounts Payable</option><option>Cash Disbursement</option><option>Other</option></select></div></div><div class="form-field"><label>Description</label><input id="je-description" placeholder="Enter transaction description"></div><p class="card-title" style="margin-top:16px;">Journal lines</p><div style="overflow-x:auto;"><table><thead><tr><th>Account</th><th>Description</th><th class="right">Debit</th><th class="right">Credit</th><th></th></tr></thead><tbody id="journal-lines"></tbody></table></div><div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;flex-wrap:wrap;gap:12px;"><button class="btn btn-ghost" onclick="addJournalLine()">+ Add line</button><strong>Total Debit: <span id="je-total-debit">₱0.00</span> &nbsp; Total Credit: <span id="je-total-credit">₱0.00</span></strong></div><div id="je-status" style="margin-top:12px;font-size:13px;"></div><div class="modal-actions"><button class="btn btn-ghost" onclick="clearJournalEntry()">Clear</button><button class="btn btn-primary" onclick="saveJournalEntry()">Post Journal Entry</button></div></div>
        <div class="card" style="margin-top:16px;padding:0;overflow:hidden;"><p class="card-title" style="padding:16px 16px 0;">Posted Journal Entries</p><table><thead><tr><th>Date</th><th>Reference</th><th>Description</th><th>Source</th><th class="right">Debit</th><th class="right">Credit</th></tr></thead><tbody id="journal-history"></tbody></table></div>
      </section>

      <section class="view" id="view-accounts-payable">
        <div class="page-head"><div><h1>Accounts Payable</h1><p class="subtitle">Monitor supplier obligations, payments, and outstanding balances.</p></div><button class="btn btn-primary" onclick="alert('Accounts Payable form is ready for backend integration.')">+ Add Payable</button></div>
        <div class="grid grid-4"><div class="card stat-card"><div><div class="stat-label">Total payable</div><div class="stat-value" id="ap-total">₱0.00</div></div></div><div class="card stat-card"><div><div class="stat-label">Amount paid</div><div class="stat-value" id="ap-paid">₱0.00</div></div></div><div class="card stat-card"><div><div class="stat-label">Outstanding</div><div class="stat-value" id="ap-outstanding">₱0.00</div></div></div><div class="card stat-card"><div><div class="stat-label">Open invoices</div><div class="stat-value" id="ap-open">0</div></div></div></div>
        <div class="toolbar"><div class="search-box"><span class="search-icon">⌕</span><input id="ap-search" placeholder="Search supplier or invoice..." oninput="renderAccountsPayable()"></div><select id="ap-filter" onchange="renderAccountsPayable()"><option>All</option><option>Unpaid</option><option>Partially Paid</option><option>Paid</option></select></div>
        <div class="card" style="padding:0;overflow:hidden;"><table><thead><tr><th>Supplier</th><th>Invoice/Reference</th><th>Date</th><th>Due Date</th><th class="right">Amount Payable</th><th class="right">Amount Paid</th><th class="right">Outstanding</th><th>Status</th><th>Action</th></tr></thead><tbody id="ap-tbody"></tbody></table></div>
      </section>

      <section class="view" id="view-cash-disbursements">
        <div class="page-head"><div><h1>Cash Disbursements</h1><p class="subtitle">Record company payments and cash outflows linked to accounting entries.</p></div><button class="btn btn-primary" onclick="alert('Cash Disbursement form is ready for backend integration.')">+ Add Disbursement</button></div>
        <div class="grid grid-3"><div class="card stat-card"><div><div class="stat-label">Total disbursements</div><div class="stat-value" id="cd-total">₱0.00</div></div></div><div class="card stat-card"><div><div class="stat-label">Cash payments</div><div class="stat-value" id="cd-cash">₱0.00</div></div></div><div class="card stat-card"><div><div class="stat-label">Check / Bank</div><div class="stat-value" id="cd-bank">₱0.00</div></div></div></div>
        <div class="toolbar"><div class="search-box"><span class="search-icon">⌕</span><input id="cd-search" placeholder="Search reference or payee..." oninput="renderCashDisbursements()"></div><select id="cd-method" onchange="renderCashDisbursements()"><option>All</option><option>Cash</option><option>Check</option><option>Bank Transfer</option></select></div>
        <div class="card" style="padding:0;overflow:hidden;"><table><thead><tr><th>Payment Date</th><th>Reference No.</th><th>Payee</th><th>Description</th><th>Method</th><th class="right">Amount</th><th>Related Account</th><th>Supporting Reference</th><th>Action</th></tr></thead><tbody id="cd-tbody"></tbody></table></div>
      </section>

      <section class="view" id="view-general-ledger">
        <h1>General Ledger</h1><p class="subtitle">Posted journal transactions grouped by account.</p><div class="toolbar"><select id="gl-account" onchange="renderGeneralLedger()"><option value="All">All accounts</option></select><input type="date" id="gl-from" onchange="renderGeneralLedger()"><input type="date" id="gl-to" onchange="renderGeneralLedger()"></div><div class="card" style="padding:0;overflow:hidden;"><table><thead><tr><th>Date</th><th>Reference</th><th>Description</th><th>Account</th><th class="right">Debit</th><th class="right">Credit</th><th class="right">Running Balance</th></tr></thead><tbody id="gl-tbody"></tbody></table></div>
      </section>

      <section class="view" id="view-trial-balance">
        <div class="page-head"><div><h1>Trial Balance</h1><p class="subtitle">Ending debit and credit balances for the selected accounting period.</p></div><button class="btn btn-ghost" onclick="window.print()">Print Trial Balance</button></div><div class="card"><div class="form-row"><div class="form-field"><label>Period From</label><input type="date" id="tb-from" onchange="renderTrialBalance()"></div><div class="form-field"><label>Period To</label><input type="date" id="tb-to" onchange="renderTrialBalance()"></div></div></div><div class="card" style="margin-top:16px;padding:0;overflow:hidden;"><table><thead><tr><th>Account Code</th><th>Account Name</th><th class="right">Debit Balance</th><th class="right">Credit Balance</th></tr></thead><tbody id="tb-tbody"></tbody><tfoot><tr><th colspan="2">Total</th><th class="right" id="tb-debit">₱0.00</th><th class="right" id="tb-credit">₱0.00</th></tr></tfoot></table></div><div id="tb-status" style="margin-top:12px;"></div>
      </section>

      <section class="view" id="view-financial-statements">
        <div class="page-head"><div><h1>Financial Statements</h1><p class="subtitle">Financial reports derived from posted accounting records.</p></div><button class="btn btn-ghost" onclick="window.print()">Print Statement</button></div><div class="toolbar"><select id="fs-type" onchange="renderFinancialStatements()"><option value="income">Income Statement</option><option value="balance">Balance Sheet</option></select><input type="date" id="fs-date" onchange="renderFinancialStatements()"></div><div class="card print-document-shell"><div id="financial-statement-document" class="print-document"></div></div>
      </section>

      <!-- Settings -->
      <section class="view" id="view-settings">
        <h1>Settings</h1>
        <p class="subtitle">Company details used on receipts and reports.</p>
        <div class="settings-form">
          <div class="card">
            <div class="form-field"><label>Company name</label><input type="text" id="set-name" value="Fabellion Construction and Development Corp." /></div>
            <div class="form-field"><label>Address</label><input type="text" id="set-address" value="San Mateo, Rizal" /></div>
            <div class="form-row">
              <div class="form-field"><label>Phone</label><input type="text" id="set-phone" value="(951) 555-0148" /></div>
              <div class="form-field"><label>Tax rate (%)</label><input type="number" id="set-tax" value="12" /></div>
            </div>
            <div class="form-field">
              <label>Currency</label>
              <select id="set-currency">
                <option>USD</option><option>CAD</option><option>EUR</option><option>PHP</option>
              </select>
            </div>
            <div style="display:flex; align-items:center; gap:10px; margin-top:6px;">
              <button class="btn btn-primary" onclick="saveSettings()">Save changes</button>
              <span id="settings-saved" style="display:none; color:var(--emerald-700); font-size:13px;">&#10003; Saved</span>
            </div>
          </div>
          <div class="card" style="margin-top:16px;">
            <p class="card-title">Team members</p>
            <div class="list-row"><span>J. Alvarez</span><span class="badge slate">Site Manager</span></div>
            <div class="list-row"><span>M. Chen</span><span class="badge slate">Warehouse Lead</span></div>
            <div class="list-row"><span>R. Santos</span><span class="badge slate">POS Cashier</span></div>
          </div>
        </div>
      </section>

    </main>
  </div>
<!-- VAT Record Details Modal -->
<div class="modal-overlay" id="vat-detail-overlay" onclick="if(event.target===this) closeVatDetail()">
  <div class="modal">
    <div class="modal-header">
      <p>VAT Record Details</p>
      <button class="btn-icon" onclick="closeVatDetail()">&times;</button>
    </div>
    <div id="vat-detail-content"></div>
    <div class="modal-actions">
      <button class="btn btn-primary" onclick="closeVatDetail()">Close</button>
    </div>
  </div>
</div>
<!-- Receipt Modal -->
<div class="modal-overlay" id="receipt-overlay">
  <div class="modal receipt-modal">
    <div class="modal-header">
      <p>Sale receipt</p>
      <button class="btn-icon" onclick="closeReceiptModal()">&times;</button>
    </div>

    <div id="receipt-print">
      <div id="receipt-body" class="receipt-body"></div>
    </div>

    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeReceiptModal()">Close</button>
      <button class="btn btn-primary" onclick="printReceipt()">Print receipt</button>
    </div>

  </div>
</div>
<!-- Image Viewer Modal -->
<div class="modal-overlay" id="image-viewer-overlay" onclick="if(event.target===this) closeImageViewer()">
  <div style="position:relative; max-width:90vw; max-height:90vh;">
    <button class="btn-icon" onclick="closeImageViewer()" style="position:absolute; top:-36px; right:0; color:#fff; font-size:22px;">&times;</button>
    <img id="image-viewer-img" src="" alt="" style="max-width:90vw; max-height:80vh; border-radius:12px; display:block; margin:0 auto;">
    <p id="image-viewer-caption" style="text-align:center; color:#fff; margin-top:10px; font-size:14px;"></p>
  </div>
</div>
<script>
  /* ---------- Data ---------- */
  /* ---------- VAT Compliance Placeholder Data ---------- */
  const VAT_RATE = 12;

  const vatRecords = [
    { reference:"VAT-2026-08-001", date:"2026-08-05", period:"Q3 2026", taxable:12500.00, vat:1500.00, total:14000.00 },
    { reference:"VAT-2026-08-002", date:"2026-08-12", period:"Q3 2026", taxable:18600.00, vat:2232.00, total:20832.00 },
    { reference:"VAT-2026-08-003", date:"2026-08-20", period:"Q3 2026", taxable:9500.00, vat:1140.00, total:10640.00 },
    { reference:"VAT-2026-07-001", date:"2026-07-15", period:"Q3 2026", taxable:14200.00, vat:1704.00, total:15904.00 },
    { reference:"VAT-2026-06-001", date:"2026-06-18", period:"Q2 2026", taxable:11800.00, vat:1416.00, total:13216.00 },
    { reference:"VAT-2026-05-001", date:"2026-05-22", period:"Q2 2026", taxable:10200.00, vat:1224.00, total:11424.00 },
    { reference:"VAT-2026-03-001", date:"2026-03-11", period:"Q1 2026", taxable:8700.00, vat:1044.00, total:9744.00 }
  ];

  const vatSummaryData = {
    monthly: {
      "August 2026": { taxable:40600.00, vat:4872.00, total:45472.00 },
      "July 2026": { taxable:14200.00, vat:1704.00, total:15904.00 },
      "June 2026": { taxable:11800.00, vat:1416.00, total:13216.00 }
    },
    quarterly: {
      "Q3 2026": { taxable:54800.00, vat:6576.00, total:61376.00 },
      "Q2 2026": { taxable:22000.00, vat:2640.00, total:24640.00 },
      "Q1 2026": { taxable:8700.00, vat:1044.00, total:9744.00 }
    }
  };

  let currentVatSummaryPeriod = "monthly";

  /* ---------- VAT Compliance Frontend Functions ---------- */
  function renderVatRecords() {
    const tbody = document.getElementById("vat-records-tbody");
    if (!tbody) return;

    const search = (document.getElementById("vat-record-search")?.value || "").toLowerCase().trim();
    const period = document.getElementById("vat-period-filter")?.value || "All";
    const date = document.getElementById("vat-date-filter")?.value || "";

    const filtered = vatRecords.filter(record => {
      const matchesSearch = !search
        || record.reference.toLowerCase().includes(search)
        || record.period.toLowerCase().includes(search);
      const matchesPeriod = period === "All" || record.period === period;
      const matchesDate = !date || record.date === date;
      return matchesSearch && matchesPeriod && matchesDate;
    });

    tbody.innerHTML = filtered.length
      ? filtered.map(record => `
        <tr>
          <td><strong>${record.reference}</strong></td>
          <td>${record.date}</td>
          <td><span class="badge slate">${record.period}</span></td>
          <td class="right">${money(record.taxable)}</td>
          <td><span class="badge emerald">${VAT_RATE}%</span></td>
          <td class="right">${money(record.vat)}</td>
          <td class="right"><strong>${money(record.total)}</strong></td>
          <td><button class="btn-ghost" style="font-size:11px; font-weight:600; color:var(--emerald-700); border:none; cursor:pointer;" onclick="viewVatRecord('${record.reference}')">View Details</button></td>
        </tr>
      `).join("")
      : `<tr><td colspan="8" style="text-align:center; color:var(--slate-400); padding:24px;">No VAT records match the selected filters.</td></tr>`;

    const pagination = document.getElementById("vat-records-pagination");
    if (pagination) {
      pagination.textContent = `Showing ${filtered.length} of ${vatRecords.length} placeholder records`;
    }
  }

  function viewVatRecord(reference) {
    const record = vatRecords.find(item => item.reference === reference);
    if (!record) return;

    const detail = document.getElementById("vat-detail-content");
    if (!detail) return;

    detail.innerHTML = `
      <div class="list-row"><span>Transaction/Reference No.</span><strong>${record.reference}</strong></div>
      <div class="list-row"><span>Transaction Date</span><strong>${record.date}</strong></div>
      <div class="list-row"><span>Tax Period</span><strong>${record.period}</strong></div>
      <div class="list-row"><span>Taxable Sales</span><strong>${money(record.taxable)}</strong></div>
      <div class="list-row"><span>VAT Rate</span><strong>${VAT_RATE}%</strong></div>
      <div class="list-row"><span>VAT Amount</span><strong>${money(record.vat)}</strong></div>
      <div class="list-row"><span>Total Sales</span><strong>${money(record.total)}</strong></div>
    `;

    document.getElementById("vat-detail-overlay").classList.add("open");
  }

  function closeVatDetail() {
    const overlay = document.getElementById("vat-detail-overlay");
    if (overlay) overlay.classList.remove("open");
  }

  function setVatSummaryPeriod(period) {
    if (!vatSummaryData[period]) return;
    currentVatSummaryPeriod = period;

    document.querySelectorAll("#vat-summary-period-toggle button").forEach(button => {
      button.classList.toggle("active", button.dataset.period === period);
    });

    const select = document.getElementById("vat-summary-period");
    if (select) {
      select.innerHTML = Object.keys(vatSummaryData[period])
        .map(label => `<option value="${label}">${label}</option>`)
        .join("");
    }

    renderVatSummary();
  }

  function renderVatSummary() {
    const select = document.getElementById("vat-summary-period");
    if (!select) return;

    const selected = select.value || Object.keys(vatSummaryData[currentVatSummaryPeriod])[0];
    const summary = vatSummaryData[currentVatSummaryPeriod][selected];
    if (!summary) return;

    document.getElementById("vat-summary-stats").innerHTML = `
      <div class="card stat-card">
        <div class="stat-icon">&#128176;</div>
        <div><div class="stat-label">Total taxable sales</div><div class="stat-value">${money(summary.taxable)}</div><div class="stat-sub">${selected}</div></div>
      </div>
      <div class="card stat-card">
        <div class="stat-icon amber">&#37;</div>
        <div><div class="stat-label">VAT rate</div><div class="stat-value">${VAT_RATE}%</div><div class="stat-sub">Current interface rate</div></div>
      </div>
      <div class="card stat-card">
        <div class="stat-icon">&#128200;</div>
        <div><div class="stat-label">Total VAT amount</div><div class="stat-value">${money(summary.vat)}</div><div class="stat-sub">Placeholder summary</div></div>
      </div>
      <div class="card stat-card">
        <div class="stat-icon">&#128196;</div>
        <div><div class="stat-label">Total sales</div><div class="stat-value">${money(summary.total)}</div><div class="stat-sub">Placeholder summary</div></div>
      </div>
    `;

    document.getElementById("vat-summary-taxable").textContent = money(summary.taxable);
    document.getElementById("vat-summary-vat").textContent = money(summary.vat);
    document.getElementById("vat-summary-total").textContent = money(summary.total);
  }

  function getVatReportRecords(period) {
    return vatRecords.filter(record => record.period === period);
  }

  function summarizeVatRecords(records) {
    return records.reduce((summary, record) => {
      summary.taxable += record.taxable;
      summary.vat += record.vat;
      summary.total += record.total;
      return summary;
    }, { taxable:0, vat:0, total:0 });
  }

  function renderTaxReport() {
    const period = document.getElementById("tax-report-period")?.value || "Q3 2026";
    const records = getVatReportRecords(period);
    const summary = summarizeVatRecords(records);
    const tbody = document.getElementById("tax-report-tbody");

    if (tbody) {
      tbody.innerHTML = records.map(record => `
        <tr>
          <td>${record.reference}</td>
          <td>${record.date}</td>
          <td>${record.period}</td>
          <td class="right">${money(record.taxable)}</td>
          <td>${VAT_RATE}%</td>
          <td class="right">${money(record.vat)}</td>
          <td class="right">${money(record.total)}</td>
        </tr>
      `).join("");
    }

    const periodEl = document.getElementById("tax-report-print-period");
    if (periodEl) periodEl.textContent = period;

    const dateEl = document.getElementById("tax-report-print-date");
    if (dateEl) dateEl.textContent = new Date().toISOString().slice(0,10);

    document.getElementById("tax-report-taxable").textContent = money(summary.taxable);
    document.getElementById("tax-report-vat").textContent = money(summary.vat);
    document.getElementById("tax-report-total").textContent = money(summary.total);
  }

  function renderVatReturn() {
    const period = document.getElementById("vat-return-period")?.value || "Q3 2026";
    const records = getVatReportRecords(period);
    const summary = summarizeVatRecords(records);

    const periodEl = document.getElementById("vat-return-print-period");
    if (periodEl) periodEl.value = period;

    document.getElementById("vat-return-taxable").textContent = money(summary.taxable);
    document.getElementById("vat-return-vat").textContent = money(summary.vat);
    document.getElementById("vat-return-total").textContent = money(summary.total);
  }

  function printTaxReport() {
    renderTaxReport();
    document.body.classList.add("print-tax-report");
    window.print();
    setTimeout(() => document.body.classList.remove("print-tax-report"), 100);
  }

  function printVatReturn() {
    renderVatReturn();
    document.body.classList.add("print-vat-return");
    window.print();
    setTimeout(() => document.body.classList.remove("print-vat-return"), 100);
  }

  const CATEGORIES = ["Lumber", "Concrete", "Electrical", "Plumbing", "Hardware", "Safety"];


  let inventory = [];
  let demoInventory = [];


  let sales = [
  { id:"S-1042", date:"2026-08-25", items:6, total:612.4, method:"Card" },
  { id:"S-1041", date:"2026-08-25", items:2, total:96.0, method:"Cash" },
  { id:"S-1040", date:"2026-08-24", items:11, total:1440.75, method:"Card" },
  { id:"S-1039", date:"2026-08-23", items:4, total:218.6, method:"Card" },
  { id:"S-1038", date:"2026-08-22", items:9, total:875.3, method:"Cash" },
  { id:"S-1037", date:"2026-08-21", items:3, total:154.2, method:"Card" },
  { id:"S-1036", date:"2026-08-20", items:7, total:690.1, method:"Card" },
  ];


  const weeklySales = [
  { day:"Mon", total:690.1 }, { day:"Tue", total:154.2 }, { day:"Wed", total:875.3 },
  { day:"Thu", total:218.6 }, { day:"Fri", total:1440.75 }, { day:"Sat", total:708.4 }, { day:"Sun", total:0 },
  ];

  const monthlySales = [
  { label:"Wk 1", total:3120.4 }, { label:"Wk 2", total:2840.1 },
  { label:"Wk 3", total:4087.25 }, { label:"Wk 4", total:3612.9 },
  ];

  const yearlySales = [
  { label:"Jan", total:9840.2 }, { label:"Feb", total:8720.5 }, { label:"Mar", total:10230.75 },
  { label:"Apr", total:9560.4 }, { label:"May", total:11040.6 }, { label:"Jun", total:10380.15 },
  { label:"Jul", total:12100.9 }, { label:"Aug", total:13780.35 }, { label:"Sep", total:0 },
  { label:"Oct", total:0 }, { label:"Nov", total:0 }, { label:"Dec", total:0 },
  ];

  const SALES_PERIODS = {
    week:  { title:"Sales this week",  subLabel:"Mon-Sun",     data:weeklySales },
    month: { title:"Sales this month", subLabel:"By week",     data:monthlySales },
    year:  { title:"Sales this year",  subLabel:"Jan-Dec",     data:yearlySales },
  };

  let currentSalesPeriod = "week";


  let filings = [
  { id:"F-2026-Q2", period:"Q2 2026 sales tax", dueDate:"2026-07-31", status:"Filed", amount:4180.6 },
  { id:"F-2026-06", period:"June 2026 payroll tax", dueDate:"2026-07-15", status:"Filed", amount:2960.0 },
  { id:"F-2026-Q3", period:"Q3 2026 sales tax", dueDate:"2026-10-31", status:"Upcoming", amount:0 },
  { id:"F-2026-08", period:"August 2026 payroll tax", dueDate:"2026-09-15", status:"Upcoming", amount:0 },
  ];


  const permits = [
  { name:"Reseller / sales tax permit", expires:"2027-03-01", status:"Active" },
  { name:"Contractor license (state)", expires:"2026-11-15", status:"Renew soon" },
  { name:"Hazardous materials storage permit", expires:"2027-06-30", status:"Active" },
  ];


  const PIE_COLORS = ["#1F5B2C","#3F7D3A","#C9A227","#8c8c8c","#a3841e","#666666"];
  let cart = [];
  let chartInstances = {};


  /* ---------- Helpers ---------- */
  function money(n) {
    return n.toLocaleString(undefined, { style:"currency", currency:"PHP" });
  }
  function badge(text, tone) {
    return `<span class="badge {text}</span>`;
  }


  /* ---------- Sidebar collapse ---------- */
  function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    const btn = document.getElementById("sidebar-toggle");
    const collapsed = sidebar.classList.toggle("collapsed");
    btn.innerHTML = collapsed ? "&#10095;" : "&#10094;";
    btn.title = collapsed ? "Expand sidebar" : "Collapse sidebar";
  }


  /* ---------- Navigation ---------- */
  const NAV_ITEMS = [
  {
    id: "dashboard",
    label: "Dashboard",
    icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect></svg>'
  },


  {
    id: "pos",
    label: "POS",
    icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"></rect><path d="M7 20h10"></path><path d="M7 8h10"></path><path d="M7 12h2"></path><path d="M11 12h2"></path><path d="M15 12h2"></path></svg>'
  },

  {
    id: "tax",
    label: "Tax Compliance",
    icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h5"></path></svg>',
    dropdown: true,

    children: [
    {
      id: "vat-records",
      label: "VAT Records",
      icon: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h5"></path></svg>'
    },

    {
      id: "vat-summary",
      label: "VAT Summary",
      icon: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5"></path><path d="M4 19h17"></path><path d="m7 15 3-4 3 2 5-7"></path></svg>'
    },

    {
      id: "tax-report",
      label: "Tax Report",
      icon: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"></path><path d="M8 8h8"></path><path d="M8 12h8"></path><path d="M8 16h5"></path></svg>'
    },

    {
      id: "vat-return",
      label: "VAT Return Preparation (2550Q)",
      icon: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h5"></path></svg>'
    }
    ]
  },

  {
    id: "accounting",
    label: "Accounting",
    icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"></rect><path d="M8 6h8"></path><path d="M8 10h2"></path><path d="M14 10h2"></path><path d="M8 14h2"></path><path d="M14 14h2"></path><path d="M8 18h8"></path></svg>',
    dropdown: true,

    children: [
      { id: "accounting-overview", label: "Accounting Overview", icon: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect></svg>' },
      { id: "chart-of-accounts", label: "Chart of Accounts", icon: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 6H3"></path><path d="M21 12H3"></path><path d="M21 18H3"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path></svg>' },
      { id: "journal-entry", label: "Journal Entry", icon: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 20H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9l5 5v4"></path><path d="M14 2v6h6"></path><path d="M16 18h6"></path><path d="M19 15v6"></path></svg>' },
      { id: "accounts-payable", label: "Accounts Payable", icon: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path><path d="M14 2v6h6"></path><path d="M16 13H8"></path><path d="M16 17H8"></path></svg>' },
      { id: "cash-disbursements", label: "Cash Disbursements", icon: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h14a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5"></path><path d="M16 13h.01"></path></svg>' },
      { id: "general-ledger", label: "General Ledger", icon: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"></path></svg>' },
      { id: "trial-balance", label: "Trial Balance", icon: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"></path><path d="m19 7-7-4-7 4"></path><path d="M5 7h14"></path><path d="M5 7l3 9h8l3-9"></path><path d="M7 21h10"></path></svg>' },
      { id: "financial-statements", label: "Financial Statements", icon: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="M7 16h.01"></path><path d="M12 11h.01"></path><path d="M17 14h.01"></path><path d="M22 7h.01"></path><path d="m7 16 5-5 5 3 5-7"></path></svg>' }
    ]
  },

  {
    id: "settings",
    label: "Settings",
    icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-1.7 1.7-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V20h-2.4v-.2a1.7 1.7 0 0 0-1.03-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06-1.7-1.7.06-.06A1.7 1.7 0 0 0 8.46 15a1.7 1.7 0 0 0-1.56-1.03H6v-2.4h.9A1.7 1.7 0 0 0 8.46 10a1.7 1.7 0 0 0-.34-1.88l-.06-.06 1.7-1.7.06.06a1.7 1.7 0 0 0 1.88.34A1.7 1.7 0 0 0 12.73 5.2V5h2.4v.2a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06 1.7 1.7-.06.06A1.7 1.7 0 0 0 19.4 10c.16.58.7 1 1.3 1h.3v2.4h-.3A1.7 1.7 0 0 0 19.4 15Z"></path></svg>'
  }
  ];


  function renderNav() {
    const nav = document.getElementById("nav");


    nav.innerHTML = NAV_ITEMS.map(item => {
      if (item.dropdown) {
        return `
        <div class="nav-group">
        <button class="nav-item nav-parent" type="button"
        onclick="toggleNavDropdown('${item.id}-menu')">
        <span class="nav-icon">${item.icon}</span>
        <span class="nav-label" style="flex:1;">${item.label}</span>
        <span class="nav-arrow" id="${item.id}-arrow">&#9662;</span>
        </button>


        <div class="nav-dropdown" id="${item.id}-menu">
        ${item.children.map(child => `
          <button class="nav-item nav-child"
          data-view="${child.id}"
          onclick="switchView('${child.id}')">
          <span class="nav-icon">${child.icon}</span>
          <span class="nav-label">${child.label}</span>
          </button>
          `).join("")}
        </div>
        </div>
        `;
      }


    return `
    <button class="nav-item ${item.id === 'dashboard' ? 'active' : ''}"
    data-view="${item.id}"
    onclick="switchView('${item.id}')">
    <span class="nav-icon">${item.icon}</span>
    <span class="nav-label">${item.label}</span>
    </button>
    `;
  }).join("");
  }


  function toggleNavDropdown(id) {
    const menu = document.getElementById(id);
    if (!menu) return;

    const sidebar = document.getElementById("sidebar");

    // If the sidebar is collapsed, expand it first so the dropdown
    // has room to show, then open the submenu.
    if (sidebar.classList.contains("collapsed")) {
      sidebar.classList.remove("collapsed");
      const toggleBtn = document.getElementById("sidebar-toggle");
      toggleBtn.innerHTML = "&#10094;";
      toggleBtn.title = "Collapse sidebar";

      menu.classList.add("open");
      const arrowId = id.replace("-menu", "-arrow");
      const arrow = document.getElementById(arrowId);
      if (arrow) arrow.innerHTML = "&#9652;";
      return;
    }


  const open = menu.classList.toggle("open");


  const arrowId = id.replace("-menu", "-arrow");
  const arrow = document.getElementById(arrowId);


  if (arrow) {
    arrow.innerHTML = open ? "&#9652;" : "&#9662;";
  }
  }


  function switchView(id) {
    document.querySelectorAll(".view").forEach(v => v.classList.remove("active"));


    const target = document.getElementById("view-" + id);
    if (target) target.classList.add("active");


    document.querySelectorAll(".nav-item").forEach(n => {
      n.classList.toggle("active", n.dataset.view === id);
    });


  if (
  id === "vat-records" ||
  id === "vat-summary" ||
  id === "tax-report" ||
  id === "vat-return"
  ) {
    const menu = document.getElementById("tax-menu");
    const arrow = document.getElementById("tax-arrow");

    if (menu) menu.classList.add("open");
    if (arrow) arrow.innerHTML = "&#9652;";
  }


  if (
  id === "accounting" ||
  id === "accounting-overview" ||
  id === "chart-of-accounts" ||
  id === "journal-entry" ||
  id === "accounts-payable" ||
  id === "cash-disbursements" ||
  id === "general-ledger" ||
  id === "trial-balance" ||
  id === "financial-statements"
  ) {
    const menu = document.getElementById("accounting-menu");
    const arrow = document.getElementById("accounting-arrow");


    if (menu) menu.classList.add("open");
    if (arrow) arrow.innerHTML = "&#9652;";
  }


  if (id === "dashboard") renderDashboard();
  if (id === "pos") renderProductGrid();
  if (id === "vat-records") renderVatRecords();
  if (id === "vat-summary") {
    setVatSummaryPeriod(currentVatSummaryPeriod);
  }
  if (id === "tax-report") renderTaxReport();
  if (id === "vat-return") renderVatReturn();
  if (id === "accounting" || id === "accounting-overview") renderAccounting();
  if (id === "chart-of-accounts") renderChartOfAccounts();
  if (id === "journal-entry") { renderJournalEntry(); renderJournalHistory(); }
  if (id === "accounts-payable") renderAccountsPayable();
  if (id === "cash-disbursements") renderCashDisbursements();
  if (id === "general-ledger") renderGeneralLedger();
  if (id === "trial-balance") renderTrialBalance();
  if (id === "financial-statements") renderFinancialStatements();
  if (id === "reports") renderReports();
  }


  /* ---------- Dashboard ---------- */
  function renderDashboard() {
    const totalValue = inventory.reduce((s,i) => s + i.qty * i.unitCost, 0);
    const lowStock = inventory.filter(i => i.qty <= i.reorder);
    const periodTotal = SALES_PERIODS[currentSalesPeriod].data.reduce((s,d) => s + d.total, 0);
    const periodLabel = currentSalesPeriod === "week" ? "Sales this week"
    : currentSalesPeriod === "month" ? "Sales this month"
    : "Sales this year";


    document.getElementById("dashboard-stats").innerHTML = `
    <div class="card stat-card"><div class="stat-icon">&#128230;</div><div><div class="stat-label">Inventory value</div><div class="stat-value">{inventory.length} SKUs tracked</div></div></div>
    <div class="card stat-card"><div class="stat-icon amber">&#9888;</div><div><div class="stat-label">Low stock items</div><div class="stat-value">${lowStock.length}</div><div class="stat-sub">Need reordering</div></div></div>
    <div class="card stat-card"><div class="stat-icon">&#128176;</div><div><div class="stat-label">Latest sale</div><div class="stat-value">{sales[0]?.date || ""}</div></div></div>
    <div class="card stat-card"><div class="stat-icon">&#128200;</div><div><div class="stat-label">{money(periodTotal)}</div><div class="stat-sub">${SALES_PERIODS[currentSalesPeriod].subLabel}</div></div></div>
    `;


    document.getElementById("low-stock-list").innerHTML = lowStock.length === 0
    ? `<p style="font-size:13px; color:var(--slate-400);">All items well stocked.</p>`
    : lowStock.map(i => `
    <div class="list-row">
    <div><div>{i.category}</div></div>
    ${badge(i.qty + " " + i.unit, "amber")}
    </div>`).join("");


    renderSalesChart();


    const byCategory = CATEGORIES.map(c => ({
      category:c, value: inventory.filter(i=>i.category===c).reduce((s,i)=>s+i.qty*i.unitCost,0)
    }));
  drawChart("chart-category-value", "bar", byCategory.map(c=>c.category), [{ data: byCategory.map(c=>c.value), backgroundColor:"#1F5B2C", borderRadius:4 }], true);
  }

  /* ---------- Sales period toggle (week / month / year) ---------- */
  function setSalesPeriod(period) {
    if (!SALES_PERIODS[period]) return;
    currentSalesPeriod = period;

    document.querySelectorAll("#sales-period-toggle button").forEach(btn => {
      btn.classList.toggle("active", btn.dataset.period === period);
    });

  const statValue = document.querySelector('.stat-value');
  renderSalesChart();

  // Refresh the "sales this \_\_\_" stat card too, since it depends on the period.
  const lowStock = inventory.filter(i => i.qty <= i.reorder);
  const totalValue = inventory.reduce((s,i) => s + i.qty * i.unitCost, 0);
  const periodTotal = SALES_PERIODS[period].data.reduce((s,d) => s + d.total, 0);
  const periodLabel = period === "week" ? "Sales this week" : period === "month" ? "Sales this month" : "Sales this year";
  const cards = document.querySelectorAll("#dashboard-stats .stat-card");
  if (cards[3]) {
    cards[3].querySelector(".stat-label").textContent = periodLabel;
    cards[3].querySelector(".stat-value").textContent = money(periodTotal);
    cards[3].querySelector(".stat-sub").textContent = SALES_PERIODS[period].subLabel;
  }
  }

  function renderSalesChart() {
    const period = SALES_PERIODS[currentSalesPeriod];
    document.getElementById("sales-chart-title").textContent = period.title;
    drawChart(
    "chart-weekly-sales",
    "bar",
    period.data.map(d => d.day || d.label),
    [{ data: period.data.map(d => d.total), backgroundColor:"#3F7D3A", borderRadius:4 }]
    );
  }





  async function loadInventory() {
    try {
      const response = await fetch("/inventory", {
        method: "GET",
        headers: {
          "Accept": "application/json"
        }
    });

  if (!response.ok) {
    throw new Error("Failed to load inventory.");
  }

  const data = await response.json();


  inventory = data.map(item => ({
    id: Number(item.id),
    name: item.name,
    category: item.category,
    qty: Number(item.qty),
    unit: item.unit,
    unitCost: Number(item.unit_cost),
    sellingPrice: item.selling_price === null ? null : Number(item.selling_price),
    reorder: Number(item.reorder_level),
    image: item.image
      ? (
        item.image.startsWith("http://") ||
        item.image.startsWith("https://") ||
        item.image.startsWith("/")
          ? item.image
          : "/" + item.image.replace(/^public\//, "")
      )
      : null,
  }));
  demoInventory = inventory.map(item => ({ ...item }));


  renderDashboard();
  renderProductGrid();

  } catch (error) {
  console.error("Inventory loading error:", error);

  alert("Could not load inventory from the database.");
  }
  }







  /* ---------- POS ---------- */
  function renderProductGrid() {
    const query = (document.getElementById("pos-search").value || "").toLowerCase();
    const items = demoInventory.filter(i =>
      i.qty > 0 &&
      i.sellingPrice !== null &&
      i.name.toLowerCase().includes(query)
    );
    const grid = document.getElementById("product-grid");
    grid.innerHTML = items.length === 0
      ? `<p style="grid-column:span 3; text-align:center; color:var(--slate-400); padding:32px 0;">No matching in-stock materials.</p>`
      : items.map(i => `
        <div class="product-card">
          ${i.image ? `<img src="${i.image}" alt="${i.name}" onclick="openImageViewer(this.src, this.alt)">` : ""}
          <div class="product-cat">${i.category}</div>
          <div class="product-name">${i.name}</div>
          <div class="product-footer">
            <span class="product-price">${money(i.sellingPrice)}</span>
            <span class="product-stock">${i.qty} ${i.unit} left</span>
          </div>
          <button type="button" class="add-to-sale-btn" onclick="addToCart(${i.id})">Add to Sale</button>
        </div>`).join("");
  }


  function addToCart(id) {
    const item = demoInventory.find(i => i.id === id);
    const existing = cart.find(c => c.id === id);
    if (existing) {
      if (existing.qty >= item.qty) return;
      existing.qty++;
    } else {
    cart.push({ ...item, qty:1 });
  }
  renderCart();
  }
  function changeCartQty(id, delta) {
    const line = cart.find(c => c.id === id);
    if (!line) return;
    line.qty += delta;
    cart = cart.filter(c => c.qty > 0);
    renderCart();
  }
  function removeFromCart(id) {
    cart = cart.filter(c => c.id !== id);
    renderCart();
  }


  function renderCart() {
    const linesEl = document.getElementById("cart-lines");
    linesEl.innerHTML = cart.length === 0
    ? `<p style="font-size:13px; color:var(--slate-400);">Cart is empty. Tap a material to add it.</p>`
    : cart.map(c => `
    <div class="cart-line">
    <div class="cart-line-name" title="${c.name}">${c.name}<div class="cart-line-sub">${money(c.sellingPrice)} / ${c.unit}</div></div>
    <div class="qty-controls">
    <button class="qty-btn" onclick="changeCartQty(${c.id}, -1)">-</button>
    <span>${c.qty}</span>
    <button class="qty-btn" onclick="changeCartQty(${c.id}, 1)">+</button>
    <button class="btn-icon" onclick="removeFromCart(${c.id})">&#128465;</button>
    </div>
    </div>`).join("");


    const subtotal = cart.reduce((s,c) => s + c.qty * c.sellingPrice, 0);
    const tax = subtotal * 0.12;
    const total = subtotal + tax;
    document.getElementById("cart-subtotal").textContent = money(subtotal);
    document.getElementById("cart-tax").textContent = money(tax);
    document.getElementById("cart-total").textContent = money(total);
    const btn = document.getElementById("checkout-btn");
    btn.disabled = cart.length === 0;
    btn.textContent = cart.length > 0 ? `Charge ${money(total)}` : "Charge";
  }


  function checkout() {
    if (cart.length === 0) return;

    const cartSnapshot = cart.map(c => ({ ...c }));

    cart.forEach(c => {
      const item = demoInventory.find(i => i.id === c.id);
      if (item) item.qty -= c.qty;
    });

  const subtotal = cartSnapshot.reduce((s,c) => s + c.qty * c.sellingPrice, 0);
  const tax = subtotal * 0.12;
  const total = subtotal * 1.12;
  const saleId = `S-${1043 + sales.length}`;
  const saleRecord = { id:saleId, date:new Date().toISOString().slice(0,10), items: cartSnapshot.reduce((s,c)=>s+c.qty,0), total, method:"Card" };
  sales.unshift(saleRecord);

  cart = [];
  renderCart();
  renderProductGrid();

  const banner = document.getElementById("confirm-banner");
  banner.style.display = "flex";
  banner.innerHTML = `&#10003; Sale ${saleId} completed`;
  setTimeout(() => banner.style.display = "none", 3000);

  showReceipt(saleRecord, cartSnapshot, subtotal, tax, total);
  }

  /* ---------- Receipt ---------- */
  function showReceipt(sale, items, subtotal, tax, total) {
    const companyName = document.getElementById("set-name")?.value || "SiteStock";
    const companyAddress = document.getElementById("set-address")?.value || "";
    const companyPhone = document.getElementById("set-phone")?.value || "";

    const lineRows = items.map(c => `
    <tr>
    <td>
    <div class="receipt-line-name">${c.name}</div>
    <div class="receipt-line-sub">${c.qty} x ${money(c.sellingPrice)}</div>
    </td>
    <td class="right">${money(c.qty * c.sellingPrice)}</td>
    </tr>
    `).join("");

    document.getElementById("receipt-body").innerHTML = `
    <div class="receipt-header">
    <div class="receipt-company">${companyName}</div>
    <div>${companyAddress}</div>
    <div>${companyPhone}</div>
    </div>
    <div class="receipt-meta">
    <span>Receipt #${sale.id}</span>
    <span>${sale.date}</span>
    </div>
    <table class="receipt-lines"><tbody>${lineRows}</tbody></table>
    <div class="receipt-totals-row"><span>Subtotal</span><span>${money(subtotal)}</span></div>
    <div class="receipt-totals-row"><span>VAT (12%)</span><span>${money(tax)}</span></div>
    <div class="receipt-totals-row grand"><span>Total</span><span>${money(total)}</span></div>
    <div class="receipt-totals-row" style="margin-top:8px; color:var(--slate-500);"><span>Payment method</span><span>${sale.method}</span></div>
    <div class="receipt-footer">Thank you for your purchase!</div>
    `;

    document.getElementById("receipt-overlay").classList.add("open");
  }

  function closeReceiptModal() {
    document.getElementById("receipt-overlay").classList.remove("open");
  }

  function printReceipt() {
    window.print();
  }

  /* ---------- Image viewer (lightbox) ---------- */
  function openImageViewer(src, caption) {
    document.getElementById("image-viewer-img").src = src;
    document.getElementById("image-viewer-caption").textContent = caption || "";
    document.getElementById("image-viewer-overlay").classList.add("open");
  }

  function closeImageViewer() {
    document.getElementById("image-viewer-overlay").classList.remove("open");
    document.getElementById("image-viewer-img").src = "";
  }


  /* ---------- Tax Compliance ---------- */
  // VAT-only interface rendering is handled by the functions above.


  /* ---------- Accounting ---------- */
  // Frontend accounting interface. Actual database/controller integration is handled separately.
  const accountingAccounts=[
    {code:"1010",name:"Cash",type:"Asset",description:"Cash and cash equivalents",status:"Active"},
    {code:"1100",name:"Accounts Receivable",type:"Asset",description:"Customer receivables",status:"Active"},
    {code:"1200",name:"Inventory",type:"Asset",description:"Construction materials",status:"Active"},
    {code:"2010",name:"Accounts Payable",type:"Liability",description:"Supplier obligations",status:"Active"},
    {code:"3010",name:"Owner's Capital",type:"Equity",description:"Owner's equity",status:"Active"},
    {code:"4010",name:"Sales Revenue",type:"Revenue",description:"Material sales revenue",status:"Active"},
    {code:"5010",name:"Cost of Goods Sold",type:"Expense",description:"Cost of materials sold",status:"Active"},
    {code:"5020",name:"Operating Expense",type:"Expense",description:"Operating expenses",status:"Active"}
  ];
  let accountingJournalEntries=[],accountsPayableRecords=[],cashDisbursementRecords=[];
  function emptyRow(cols,msg){return `<tr><td colspan="${cols}" class="empty-accounting">${msg}</td></tr>`;}
  function renderAccounting(){
    ["acct-revenue","acct-expenses","acct-net","acct-payables"].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=money(0);});
    const b=document.getElementById("acct-balances");if(b)b.innerHTML=emptyRow(3,"No posted accounting records available.");
    const r=document.getElementById("acct-recent");if(r)r.innerHTML=emptyRow(6,"No posted journal entries available.");
  }
  function renderChartOfAccounts(){const q=(document.getElementById("coa-search")?.value||"").toLowerCase(),t=document.getElementById("coa-type")?.value||"All",st=document.getElementById("coa-status")?.value||"All";const rows=accountingAccounts.filter(a=>(!q||`${a.code} ${a.name}`.toLowerCase().includes(q))&&(t==="All"||a.type===t)&&(st==="All"||a.status===st));const tb=document.getElementById("coa-tbody");if(!tb)return;tb.innerHTML=rows.map(a=>`<tr><td><strong>${a.code}</strong></td><td>${a.name}</td><td>${a.type}</td><td>${a.description}</td><td><span class="badge emerald">${a.status}</span></td><td><button class="btn-ghost" onclick="alert('Account ${a.code} is ready for backend integration.')">Edit</button></td></tr>`).join("")||emptyRow(6,"No accounts found.");}
  function renderJournalEntry(){const tb=document.getElementById("journal-lines");if(!tb)return;if(!document.getElementById("je-date").value)document.getElementById("je-date").value=new Date().toISOString().slice(0,10);if(!tb.children.length){addJournalLine();addJournalLine();}updateJournalTotals();}
  function addJournalLine(){const tb=document.getElementById("journal-lines");if(!tb)return;const tr=document.createElement("tr");tr.innerHTML=`<td><select class="je-account">${accountingAccounts.map(a=>`<option value="${a.code}">${a.code} — ${a.name}</option>`).join("")}</select></td><td><input class="je-line-description" placeholder="Line description"></td><td><input type="number" class="je-debit" min="0" step="0.01" value="0" oninput="updateJournalTotals()"></td><td><input type="number" class="je-credit" min="0" step="0.01" value="0" oninput="updateJournalTotals()"></td><td><button class="btn-icon" onclick="removeJournalLine(this)">&#128465;</button></td>`;tb.appendChild(tr);updateJournalTotals();}
  function removeJournalLine(btn){const tb=document.getElementById("journal-lines");if(tb.children.length<=2)return;btn.closest("tr").remove();updateJournalTotals();}
  function updateJournalTotals(){let d=0,c=0;document.querySelectorAll("#journal-lines .je-debit").forEach(x=>d+=Number(x.value)||0);document.querySelectorAll("#journal-lines .je-credit").forEach(x=>c+=Number(x.value)||0);document.getElementById("je-total-debit").textContent=money(d);document.getElementById("je-total-credit").textContent=money(c);}
  function saveJournalEntry(){const status=document.getElementById("je-status"),date=document.getElementById("je-date").value,ref=document.getElementById("je-reference").value.trim(),desc=document.getElementById("je-description").value.trim(),source=document.getElementById("je-source").value;let d=0,c=0,invalid=false;const lines=[...document.querySelectorAll("#journal-lines tr")].map(tr=>{const debit=Number(tr.querySelector(".je-debit").value)||0,credit=Number(tr.querySelector(".je-credit").value)||0;if(debit&&credit)invalid=true;d+=debit;c+=credit;return{account:tr.querySelector(".je-account").value,debit,credit,description:tr.querySelector(".je-line-description").value};});if(!date||!ref||!desc){status.textContent="Complete the date, reference number, and description.";status.style.color="var(--red-700)";return;}if(invalid||d<=0||c<=0||Math.abs(d-c)>0.005){status.textContent="Journal entry must contain valid and equal debit and credit totals.";status.style.color="var(--red-700)";return;}if(accountingJournalEntries.some(e=>e.reference===ref)){status.textContent="Duplicate reference number.";status.style.color="var(--red-700)";return;}accountingJournalEntries.push({date,reference:ref,description:desc,source,lines,debit:d,credit:c});status.textContent="Posted in frontend session; backend saving will be connected separately.";status.style.color="var(--emerald-700)";renderJournalHistory();}
  function renderJournalHistory(){const tb=document.getElementById("journal-history");if(!tb)return;tb.innerHTML=accountingJournalEntries.map(e=>`<tr><td>${e.date}</td><td>${e.reference}</td><td>${e.description}</td><td>${e.source}</td><td class="right">${money(e.debit)}</td><td class="right">${money(e.credit)}</td></tr>`).join("")||emptyRow(6,"No posted journal entries available.");}
  function clearJournalEntry(){document.getElementById("je-reference").value="";document.getElementById("je-description").value="";document.getElementById("je-status").textContent="";document.getElementById("journal-lines").innerHTML="";addJournalLine();addJournalLine();}
  function renderAccountsPayable(){["ap-total","ap-paid","ap-outstanding"].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=money(0);});const o=document.getElementById("ap-open");if(o)o.textContent="0";const tb=document.getElementById("ap-tbody");if(tb)tb.innerHTML=emptyRow(9,"No supplier payables recorded.");}
  function renderCashDisbursements(){["cd-total","cd-cash","cd-bank"].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=money(0);});const tb=document.getElementById("cd-tbody");if(tb)tb.innerHTML=emptyRow(9,"No cash disbursements recorded.");}
  function renderGeneralLedger(){const sel=document.getElementById("gl-account");if(sel&&sel.options.length===1)accountingAccounts.forEach(a=>sel.add(new Option(`${a.code} — ${a.name}`,a.code)));const tb=document.getElementById("gl-tbody");if(tb)tb.innerHTML=emptyRow(7,"No posted ledger transactions available.");}
  function renderTrialBalance(){const d=document.getElementById("tb-debit"),c=document.getElementById("tb-credit"),tb=document.getElementById("tb-tbody"),st=document.getElementById("tb-status");if(d)d.textContent=money(0);if(c)c.textContent=money(0);if(tb)tb.innerHTML=emptyRow(4,"No posted accounting balances available.");if(st)st.innerHTML='<span class="badge slate">No accounting records to validate.</span>';}
  function renderFinancialStatements(){const doc=document.getElementById("financial-statement-document");if(!doc)return;const date=document.getElementById("fs-date")?.value||new Date().toISOString().slice(0,10),type=document.getElementById("fs-type")?.value||"income";doc.innerHTML=`<div class="print-company-header"><div class="print-company-name">Fabellon Construction and Development Corporation</div><div>${type==="income"?"Income Statement":"Balance Sheet"}</div><div>Reporting Date: ${date}</div></div><div class="empty-accounting" style="padding:32px;">No posted accounting records are available. The statement will be calculated after the backend accounting records are connected.</div>`;}
  /* ---------- Settings ---------- */
  function saveSettings() {
    const el = document.getElementById("settings-saved");
    el.style.display = "inline";
    setTimeout(() => el.style.display = "none", 2000);
  }


  /* ---------- Charts ---------- */
  function drawChart(canvasId, type, labels, datasets, horizontal) {
    const ctx = document.getElementById(canvasId).getContext("2d");
    if (chartInstances[canvasId]) chartInstances[canvasId].destroy();
    chartInstances[canvasId] = new Chart(ctx, {
      type,
      data: { labels, datasets },
      options: {
        responsive:true, maintainAspectRatio:false,
        indexAxis: horizontal ? 'y' : 'x',
        plugins: { legend: { display:false } },
        scales: {
          x: { grid: { display: !horizontal }, ticks: { font:{size:11} } },
          y: { grid: { color:"#dcdcdc" }, ticks: { font:{size:11}, callback:(v)=> horizontal ? v : '₱'+v } }
        }
    }
  });
  }
  function drawPieChart(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId).getContext("2d");
    if (chartInstances[canvasId]) chartInstances[canvasId].destroy();
    chartInstances[canvasId] = new Chart(ctx, {
      type:"pie",
      data: { labels, datasets:[{ data, backgroundColor: PIE_COLORS }] },
      options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:"bottom", labels:{ font:{size:11} } } } }
    });
  }


  /* ---------- Init ---------- */
  renderNav();

  loadInventory();

  renderDashboard();
  renderProductGrid();
  renderCart();

  renderAccounting();
  // Journal Entry rows are created when the menu is opened.
</script>
</body>
</html>