@extends('layouts.app')

@section('content')
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon">
                <i data-lucide="activity" style="width: 20px; height: 20px; color: white;"></i>
            </div>
            <span class="logo-text">SignalVision</span>
        </div>
        <button type="button" class="menu-toggle" onclick="toggleSidebar(event)">
            <i data-lucide="menu" style="width: 20px; height: 20px;"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <div class="section-title">MAIN</div>

        <a href="{{ route('dashboard') }}" class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <div class="menu-item-left">
                <i data-lucide="layout-dashboard" class="menu-icon"></i>
                <span class="menu-label">Dashboard</span>
            </div>
        </a>

        <div class="section-title">MANAGEMENT</div>

        <button type="button" class="menu-item" onclick="toggleSubmenu('users')">
            <div class="menu-item-left">
                <i data-lucide="users" class="menu-icon"></i>
                <span class="menu-label">Users</span>
            </div>
            <i data-lucide="chevron-down" class="chevron" style="width: 16px; height: 16px;"></i>
        </button>

        <div class="submenu {{ Route::is("user.*") ? 'expanded' : '' }}" id="submenu-users">
            <a href="{{ route("user.all") }}" class="submenu-item {{ Route::is("user.all") ? 'active' : '' }}">
                <span>All Users</span>
                <!-- <span class="menu-badge badge-blue">2847</span> -->
            </a>
            <a href="{{ route("user.paid") }}" class="submenu-item {{ Route::is("user.paid") ? 'active' : '' }}">
                <span>Paid Users</span>
                <!-- <span class="menu-badge badge-green">156</span> -->
            </a>
            <a href="{{ route("user.free") }}" class="submenu-item {{ Route::is("user.free") ? 'active' : '' }}">
                <span>Free Users</span>
            </a>
        </div>

        <a href="{{ route("support.index") }}" class="menu-item {{ request()->routeIs('support.index') ? 'active' : '' }}">
            <div class="menu-item-left">
                <i data-lucide="heart-plus" class="menu-icon"></i>
                <span class="menu-label">Support</span>
            </div>
        </a>

        <button type="button" class="menu-item" onclick="toggleSubmenu('bot-tokens')">
            <div class="menu-item-left">
                <i data-lucide="key" class="menu-icon"></i>
                <span class="menu-label">Bot Tokens</span>
            </div>
            <i data-lucide="chevron-down" class="chevron" style="width: 16px; height: 16px;"></i>
        </button>

        <div class="submenu {{ Route::is("bot-token.*") ? 'expanded' : '' }}" id="submenu-bot-tokens">
            <a href="{{ route("bot-token.index") }}" class="submenu-item {{ Route::is("bot-token.index") ? 'active' : '' }}">
                <span>All Tokens</span>
            </a>
            <a href="{{ route("bot-token.create") }}" class="submenu-item {{ Route::is("bot-token.create") ? 'active' : '' }}">
                <span>Add New Token</span>
            </a>
        </div>

        <button type="button" class="menu-item d-none">
            <div class="menu-item-left">
                <i data-lucide="trending-up" class="menu-icon"></i>
                <span class="menu-label">Trades</span>
            </div>
            <span class="menu-badge badge-blue">24</span>
        </button>

        <button type="button" class="menu-item d-none">
            <div class="menu-item-left">
                <i data-lucide="bot" class="menu-icon"></i>
                <span class="menu-label">Bots</span>
            </div>
            <span class="menu-badge badge-green">12</span>
        </button>

        <button type="button" class="menu-item d-none">
            <div class="menu-item-left">
                <i data-lucide="dollar-sign" class="menu-icon"></i>
                <span class="menu-label">Payments</span>
            </div>
        </button>

        <div class="section-title d-none">SYSTEM</div>

        <button type="button" class="menu-item d-none">
            <div class="menu-item-left">
                <i data-lucide="settings" class="menu-icon"></i>
                <span class="menu-label">Settings</span>
            </div>
        </button>

        <button type="button" class="menu-item d-none">
            <div class="menu-item-left">
                <i data-lucide="bell" class="menu-icon"></i>
                <span class="menu-label">Notifications</span>
            </div>
            <span class="menu-badge badge-red">3</span>
        </button>

        <button type="button" class="menu-item d-none">
            <div class="menu-item-left">
                <i data-lucide="file-text" class="menu-icon"></i>
                <span class="menu-label">Reports</span>
            </div>
        </button>
    </nav>

    <div class="user-section">
        <div class="user-avatar">{{ strtoupper(substr(auth('admin')->user()->full_name, 0, 2)) }}</div>
        <div class="user-info">
            <div class="user-name">{{ auth('admin')->user()->full_name }}</div>
            <div class="user-email">{{ auth('admin')->user()->email }}</div>
        </div>
        <form method="POST" action="{{ route('logout') }}" style="display: inline;">
            @csrf
            <button type="submit" class="logout-btn">
                <i data-lucide="log-out" style="width: 18px; height: 18px;"></i>
            </button>
        </form>
    </div>
</div>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="top-bar-left">
            <button type="button" class="mobile-menu-toggle" onclick="toggleSidebar(event)">
                <i data-lucide="menu" style="width: 20px; height: 20px;"></i>
            </button>

            <div class="breadcrumb">
                <span class="breadcrumb-item">Admin</span>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-current">@yield('page-title', 'Dashboard')</span>
            </div>
        </div>

        <div class="top-bar-center d-none">
            <div class="search-box">
                <input type="text" placeholder="Search..." id="searchInput">
                <span class="search-shortcut"><i data-lucide="search"></i></span>
            </div>
        </div>

        <div class="top-bar-actions">
            <!-- <button type="button" class="top-bar-btn notification-btn" title="Notifications">
                <i data-lucide="bell" style="width: 18px; height: 18px;"></i>
                <span class="notification-dot"></span>
            </button> -->

            <button type="button" class="top-bar-btn top-bar-btn-user">
                <i data-lucide="user" style="width: 18px; height: 18px;"></i>
                <span class="username-text">{{ auth('admin')->user()->username }}</span>
            </button>
        </div>
    </div>

    <!-- Page Content -->
    <div class="page-content">
        @yield('page-content')
    </div>
</div>

<script>
    lucide.createIcons();

    function toggleSidebar(event) {
        // Prevent any default button behavior
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        // Toggle the collapsed class
        sidebar.classList.toggle('collapsed');

        // On mobile, also toggle visibility
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('mobile-open');
        }
    }

    function toggleSubmenu(id) {
        const submenu = document.getElementById('submenu-' + id);
        const button = event.currentTarget;
        const chevron = button.querySelector('.chevron');

        if (submenu) {
            // Close all other submenus
            document.querySelectorAll('.submenu.expanded').forEach(menu => {
                if (menu.id !== 'submenu-' + id) {
                    menu.classList.remove('expanded');
                    // Reset other chevrons
                    const otherButton = menu.previousElementSibling;
                    if (otherButton) {
                        const otherChevron = otherButton.querySelector('.chevron');
                        if (otherChevron) {
                            otherChevron.style.transform = 'rotate(0deg)';
                        }
                    }
                }
            });

            // Toggle current submenu
            submenu.classList.toggle('expanded');

            // Rotate chevron
            if (submenu.classList.contains('expanded')) {
                chevron.style.transform = 'rotate(180deg)';
            } else {
                chevron.style.transform = 'rotate(0deg)';
            }
        }
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const menuToggle = document.querySelector('.menu-toggle');

        if (window.innerWidth <= 768) {
            if (sidebar.classList.contains('mobile-open') &&
                !sidebar.contains(event.target) &&
                event.target !== mobileToggle &&
                event.target !== menuToggle) {
                sidebar.classList.remove('mobile-open');
            }
        }
    });
</script>
@endsection
