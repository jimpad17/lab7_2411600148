<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - JimTrack Fitness</title>

    <!-- Bootstrap 5 CSS (same UI toolkit used in Laboratory Exercise 4) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/jimtrack.css') }}">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm py-2">
        <div class="container-fluid px-3">
            <a class="navbar-brand fw-bold text-white" href="{{ route('dashboard') }}">
                <i class="bi bi-lightning-charge-fill text-warning me-2"></i>JimTrack Fitness
                <span class="text-warning small ms-1">Inventory</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                @php
                    $alertTypes = [
                        \App\Notifications\LowStockNotification::class,
                        \App\Notifications\StockOutNotification::class,
                        \App\Notifications\OrderApprovedNotification::class,
                        \App\Notifications\PurchaseOrderNotification::class,
                    ];
                    $unreadAlertsCount = Auth::user()->unreadNotifications()
                        ->whereIn('type', $alertTypes)->count();
                    $unreadAlerts = Auth::user()->unreadNotifications()
                        ->whereIn('type', $alertTypes)
                        ->latest()->limit(5)->get();
                @endphp
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item dropdown me-3">
                        <a class="nav-link text-white position-relative" href="#" id="alertsDropdown"
                           role="button" data-bs-toggle="dropdown" aria-expanded="false"
                           title="Inventory alerts">
                            <i class="bi bi-bell-fill text-warning" style="font-size: 1.15rem;"></i>
                            @if ($unreadAlertsCount > 0)
                                <span class="notification-count">{{ $unreadAlertsCount > 9 ? '9+' : $unreadAlertsCount }}</span>
                            @endif
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm notification-menu" aria-labelledby="alertsDropdown">
                            <li>
                                <h6 class="dropdown-header fw-bold">
                                    <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                                    Notifications
                                    <span class="badge badge-low ms-1">{{ $unreadAlertsCount }}</span>
                                </h6>
                            </li>
                            @forelse ($unreadAlerts as $alert)
                                <li>
                                    <a class="dropdown-item small py-2" href="{{ $alert->data['url'] ?? route('notifications.index') }}">
                                        <div class="fw-bold text-dark">{{ $alert->data['title'] ?? $alert->data['product_name'] ?? 'Notification' }}</div>
                                        <div class="text-muted">{{ \Illuminate\Support\Str::limit($alert->data['message'] ?? '', 70) }}</div>
                                        <div class="text-muted" style="font-size: .68rem;">{{ $alert->created_at->diffForHumans() }}</div>
                                    </a>
                                </li>
                            @empty
                                <li><span class="dropdown-item-text small text-muted">No unread alerts. Stock levels look good.</span></li>
                            @endforelse
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item small fw-semibold text-warning-dark" href="{{ route('notifications.index') }}">
                                <i class="bi bi-list-ul me-1"></i> View all alerts
                            </a></li>
                            @if ($unreadAlertsCount > 0)
                                <li>
                                    <form method="POST" action="{{ route('notifications.read-all') }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="dropdown-item small fw-semibold">
                                            <i class="bi bi-check2-all me-1"></i> Mark all as read
                                        </button>
                                    </form>
                                </li>
                            @endif
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white small" href="#" id="userDropdown"
                           role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle text-warning me-1"></i>{{ Auth::user()->name }}
                            <span class="badge role-badge ms-1">{{ strtoupper(Auth::user()->role) }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userDropdown">
                            <li><span class="dropdown-item-text small text-muted">{{ Auth::user()->email }}</span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item small fw-semibold" href="{{ route('profile') }}">
                                    <i class="bi bi-person-circle me-1"></i> My Profile
                                </a>
                            </li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item small fw-semibold">
                                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar vh-100 p-3">
                <div class="position-sticky pt-2 d-flex flex-column" style="min-height: calc(100vh - 90px);">
                    @php
                        $isAdmin = Auth::user()->isAdmin();
                        $navGroups = [
                            ['label' => 'Main', 'items' => [
                                ['dashboard', 'Dashboard', 'bi-speedometer2', 'dashboard'],
                            ]],
                            ['label' => 'Inventory', 'items' => array_filter([
                                $isAdmin ? ['inventory.index', 'Inventory', 'bi-clipboard-data', 'inventory*'] : null,
                                ['products.index', 'Products', 'bi-box-seam', 'products*'],
                                ['brands.index', 'Brands', 'bi-award', 'brands*'],
                                ['categories.index', 'Categories', 'bi-tags', 'categories*'],
                            ])],
                            ['label' => 'Management', 'items' => array_filter([
                                $isAdmin ? ['users.index', 'Users', 'bi-people', 'users*'] : null,
                                ['product-report', 'Product Report', 'bi-file-bar-graph', 'product-report*'],
                            ])],
                            ['label' => 'Procurement (Lab 7)', 'items' => array_filter([
                                ['purchase-orders.index', 'Purchase Orders', 'bi-cart-plus', 'purchase-orders.*'],
                                $isAdmin ? ['suppliers.index', 'Suppliers', 'bi-truck', 'suppliers.index*'] : null,
                                ['suppliers.performance', 'Supplier Performance', 'bi-graph-up', 'suppliers.performance*'],
                            ])],
                            ['label' => 'Orders', 'items' => [
                                ['sales-orders', 'Sales Order', 'bi-cart-check', 'sales-orders*'],
                                ['purchase-orders', 'Purchase Requests', 'bi-clipboard-check', 'purchase-orders'],
                            ]],
                            ['label' => 'Records', 'items' => array_filter([
                                $isAdmin ? ['receipts.index', 'Receipt', 'bi-receipt', 'receipts*'] : null,
                                ['transaction-history', 'Transaction History', 'bi-arrow-left-right', 'transaction-history*'],
                            ])],
                            ['label' => 'Monitoring', 'items' => array_filter([
                                ['product-history', 'Product History', 'bi-clock-history', 'product-history*'],
                                $isAdmin ? ['daily-report-history', 'Daily Report History', 'bi-calendar2-week', 'daily-report-history*'] : null,
                                ['audit-trail', 'Audit Trail', 'bi-shield-check', 'audit-trail*'],
                            ])],
                        ];
                    @endphp

                    @foreach ($navGroups as $group)
                        <small class="text-uppercase text-muted fw-bold px-3 mt-3 mb-2" style="font-size: .68rem;">{{ $group['label'] }}</small>
                        <ul class="nav flex-column gap-1">
                            @foreach ($group['items'] as [$route, $label, $icon, $pattern])
                                <li class="nav-item">
                                    <a class="nav-link rounded px-3 py-2 {{ request()->routeIs($pattern) ? 'active' : '' }}"
                                       href="{{ route($route) }}">
                                        <i class="bi {{ $icon }} me-2"></i> {{ $label }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach

                    @unless ($isAdmin)
                        <small class="text-muted px-3 mt-3 d-block" style="font-size: .65rem;">
                            <i class="bi bi-info-circle me-1"></i> Staff view — inventory operations
                        </small>
                    @endunless
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap align-items-center pb-2 mb-4 border-bottom">
                    <h2 class="fw-bold text-dark mb-0">
                        @hasSection('heading')
                            @yield('heading')
                        @else
                            Welcome, <span class="text-warning fw-bold">{{ Auth::user()->name }}</span>!
                        @endif
                    </h2>
                    @hasSection('actions')
                        <div class="ms-auto pt-2 pt-sm-0">@yield('actions')</div>
                    @endif
                </div>

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show py-2 small fw-bold" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show py-2 small fw-bold" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger py-2 small" role="alert">
                        <strong>Please correct the following:</strong>
                        <ul class="mb-0 mt-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
