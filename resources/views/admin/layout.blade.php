<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — OurHouseHelp UK</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f6f8; color: #1a1a2e; font-size: 14px; }

        /* Topbar */
        .topbar { background: #1E3A5F; color: #fff; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; height: 56px; position: sticky; top: 0; z-index: 100; }
        .topbar-brand { font-weight: 700; font-size: 16px; letter-spacing: 0.3px; }
        .topbar-brand span { color: #4FC3F7; }
        .topbar-user { display: flex; align-items: center; gap: 16px; font-size: 13px; color: rgba(255,255,255,0.8); }
        .topbar-user form { display: inline; }
        .topbar-logout { background: none; border: 1px solid rgba(255,255,255,0.3); color: rgba(255,255,255,0.8); padding: 5px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; }
        .topbar-logout:hover { background: rgba(255,255,255,0.1); }

        /* Sidebar */
        .layout { display: flex; min-height: calc(100vh - 56px); }
        .sidebar { width: 210px; background: #fff; border-right: 1px solid #e2e8f0; padding: 20px 0; flex-shrink: 0; }
        .sidebar-section { padding: 6px 16px; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 12px; }
        .sidebar a { display: block; padding: 10px 20px; color: #475569; font-weight: 500; text-decoration: none; border-left: 3px solid transparent; }
        .sidebar a:hover { background: #f1f5f9; color: #1E3A5F; }
        .sidebar a.active { background: #eff6ff; color: #1E3A5F; border-left-color: #1E3A5F; font-weight: 600; }

        /* Main */
        .main { flex: 1; padding: 28px 32px; overflow-x: auto; }
        .page-title { font-size: 20px; font-weight: 700; margin-bottom: 20px; color: #1a1a2e; }

        /* Flash messages */
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 13px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-danger  { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Cards & tables */
        .card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 24px; }
        .card-header { padding: 14px 20px; font-weight: 600; font-size: 14px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .card-body { padding: 20px; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 10px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border-bottom: 2px solid #e2e8f0; background: #f8fafc; }
        td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }

        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 12px; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef9c3; color: #854d0e; }
        .badge-danger  { background: #fee2e2; color: #991b1b; }
        .badge-info    { background: #dbeafe; color: #1e40af; }
        .badge-muted   { background: #f1f5f9; color: #64748b; }

        /* Buttons */
        .btn { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; }
        .btn-primary { background: #1E3A5F; color: #fff; }
        .btn-primary:hover { background: #162d4a; }
        .btn-sm { padding: 5px 12px; font-size: 12px; border-radius: 6px; }
        .btn-outline { background: transparent; border: 1.5px solid #cbd5e1; color: #475569; }
        .btn-outline:hover { border-color: #1E3A5F; color: #1E3A5F; }
        .btn-danger { background: #ef4444; color: #fff; }

        /* Stat cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 18px 20px; }
        .stat-value { font-size: 28px; font-weight: 700; color: #1E3A5F; line-height: 1; }
        .stat-label { font-size: 12px; color: #64748b; margin-top: 6px; font-weight: 500; }
        .stat-icon { font-size: 22px; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 10px; background: #eff6ff; color: #1E3A5F; }
        .sidebar a i { margin-right: 8px; font-size: 15px; opacity: 0.7; }
        .sidebar a.active i { opacity: 1; }

        /* Forms */
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.4px; }
        input[type=text], input[type=email], input[type=password], input[type=date], select, textarea {
            width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; color: #1a1a2e; background: #fff;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #1E3A5F; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }

        /* Pagination */
        .pagination { display: flex; gap: 6px; margin-top: 16px; align-items: center; justify-content: flex-end; }
        .pagination a, .pagination span { padding: 6px 12px; border-radius: 6px; font-size: 12px; text-decoration: none; border: 1px solid #e2e8f0; color: #475569; }
        .pagination .active span { background: #1E3A5F; color: #fff; border-color: #1E3A5F; }
        .pagination [aria-disabled="true"] span { color: #cbd5e1; }

        /* Search filter bar */
        .filter-bar { display: flex; gap: 10px; margin-bottom: 16px; align-items: flex-end; }
        .filter-bar input, .filter-bar select { max-width: 220px; }

        /* Detail grid */
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
        .detail-row { padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; margin-bottom: 3px; }
        .detail-value { font-size: 14px; color: #1a1a2e; }
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-brand">OurHouseHelp <span>UK</span> &nbsp;·&nbsp; Admin</div>
    <div class="topbar-user">
        {{ auth()->user()->name }}
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="topbar-logout">Sign out</button>
        </form>
    </div>
</div>

<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-section">Overview</div>
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>Dashboard
        </a>

        <div class="sidebar-section">People</div>
        <a href="{{ route('admin.applicants') }}" class="{{ request()->routeIs('admin.applicants*') ? 'active' : '' }}">
            <i class="bi bi-person-badge"></i>Applicants
        </a>

        <div class="sidebar-section">Bookings</div>
        <a href="{{ route('admin.requests') }}" class="{{ request()->routeIs('admin.requests*') ? 'active' : '' }}">
            <i class="bi bi-clipboard-check"></i>Service Requests
        </a>

        <div class="sidebar-section">Settings</div>
        <a href="{{ route('admin.packages') }}" class="{{ request()->routeIs('admin.packages*') ? 'active' : '' }}">
            <i class="bi bi-box-seam"></i>Packages
        </a>
        <a href="{{ route('admin.pricing') }}" class="{{ request()->routeIs('admin.pricing*') ? 'active' : '' }}">
            <i class="bi bi-tags"></i>Service Pricing
        </a>
    </aside>

    <main class="main">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>
</div>

</body>
</html>
