@extends('layouts.dashboard')

@section('page-title', 'Dashboard')

@section('page-content')

<div class="section-header" style="margin-bottom: 25px">
    <div>
        <h1 class="page-title">{{$headerTxt}}</h1>
        <p class="page-subtitle">Complete user management with 360° view</p>
    </div>
    <div>
        <button class="btn btn-secondary" onclick="exportUsersCSV()">
            <i data-lucide="download"></i>
            Export CSV
        </button>
    </div>
</div>

<!-- filters -->
<form method="GET" action="{{ url()->current() }}" id="searchForm">
    <div class="filters-bar">
        <input type="hidden" name="page" value="1">
        <input type="hidden" name="per_page" value="{{ request('per_page', 20) }}">

        <div class="filter-group">
            <input type="text" class="form-input" placeholder="Search users..." id="userSearch" name="search" value="{{ request('search') }}" onkeypress="if(event.key === 'Enter') { this.form.submit(); }" />
        </div>
        <div class="filter-group">
            <span class="filter-label">Manager:</span>
            <select class="form-select" name="manager" id="managerFilter" onchange="this.form.submit()">
                <option value="all" {{ request('manager', 'all') == 'all' ? 'selected' : '' }}>All</option>
                <option value="paid" {{ request('manager') == 'paid' ? 'selected' : '' }}>Paid</option>
                <option value="trial" {{ request('manager') == 'trial' ? 'selected' : '' }}>Free</option>
            </select>
        </div>
        <div class="filter-group">
            <span class="filter-label">Shot:</span>
            <select class="form-select" name="shot" id="shotFilter" onchange="this.form.submit()">
                <option value="all" {{ request('shot', 'all') == 'all' ? 'selected' : '' }}>All</option>
                <option value="paid" {{ request('shot') == 'paid' ? 'selected' : '' }}>Paid</option>
                <option value="trial" {{ request('shot') == 'trial' ? 'selected' : '' }}>Free</option>
            </select>
        </div>
        <div class="filter-group">
            <span class="filter-label">Mode:</span>
            <select class="form-select" name="mod" id="modeFilter" onchange="this.form.submit()">
                <option value="all" {{ request('mod', 'all') == 'all' ? 'selected' : '' }}>All</option>
                <option value="active" {{ request('mod') == 'active' ? 'selected' : '' }}>Active</option>
                <option value="passive" {{ request('mod') == 'passive' ? 'selected' : '' }}>Passive</option>
            </select>
        </div>
        <div class="filter-group">
            <span class="filter-label">Bot:</span>
            <select class="form-select" name="bot" id="botFilter" onchange="this.form.submit()">
                <option value="all" {{ request('bot', 'all') == 'all' ? 'selected' : '' }}>All</option>
                <option value="bot1" {{ request('bot') == 'bot1' ? 'selected' : '' }}>Bot1</option>
                <option value="bot2" {{ request('bot') == 'bot2' ? 'selected' : '' }}>Bot2</option>
                <option value="bot3" {{ request('bot') == 'bot3' ? 'selected' : '' }}>Bot3</option>
                <option value="bot4" {{ request('bot') == 'bot4' ? 'selected' : '' }}>Bot4</option>
                <option value="bot5" {{ request('bot') == 'bot5' ? 'selected' : '' }}>Bot5</option>
                <option value="bot6" {{ request('bot') == 'bot6' ? 'selected' : '' }}>Bot6</option>
                <option value="bot7" {{ request('bot') == 'bot7' ? 'selected' : '' }}>Bot7</option>
                <option value="bot8" {{ request('bot') == 'bot8' ? 'selected' : '' }}>Bot8</option>
            </select>
        </div>
    </div>
</form>


<!-- Bulk Actions Bar -->
<div class="bulk-actions-bar" id="bulkActionsBar">
    <div class="bulk-actions-left">
        <span class="bulk-count" id="bulkCount">{{$response['total']}} users selected</span>
    </div>
    <div class="bulk-actions-right">
        <button class="btn btn-secondary" type="button" onclick="openMessageModal()">
            <i data-lucide="message-square"></i>
            Message Selected
        </button>
        <button class="btn btn-secondary" onclick="exportSelectedUsers()">
            <i data-lucide="download"></i>
            Export
        </button>
        <button class="btn btn-danger" onclick="banSelectedUsers()">
            <i data-lucide="user-x"></i>
            Ban Selected
        </button>
    </div>
</div>

<!-- Users Table -->
<div class="table-container">
    <div style="overflow-x: auto">
        <table id="fullUsersTable">
            <table id="fullUsersTable">
                <!-- Full users table will be loaded here -->
                <thead>
                    <tr>
                        <th class="checkbox-cell"><div class="checkbox" onclick="toggleSelectAll()"></div></th>
                        <th>User</th>
                        <th>Bot</th>
                        <th>Manager</th>
                        <th>Shot</th>
                        <th>Mode</th>
                        <th>Strategy</th>
                        <th>Leverage</th>
                        <th>Risk</th>
                        <th>API</th>
                        <th style="text-align: center">Signals</th>
                        <th style="text-align: center">Trades</th>
                        <th style="text-align: center">Win Rate</th>
                        <!-- <th style="text-align: right">Demo PnL</th>
                        <th style="text-align: right">Real PnL</th> -->
                        <th>Last Active</th>
                        <th>Registered</th>
                        <th style="text-align: center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @if(isset($response['data']) && count($response['data']) > 0)
                        @foreach($response['data'] as $index => $user)
                    <tr>
                        <td class="checkbox-cell">
                            <div class="checkbox" onclick="toggleUserSelection({{ $user['user_id'] ?? $index }}, event)"></div>
                        </td>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-small" style="background: linear-gradient(135deg, #00d9e9, #3b82f6)">
                                    {{ strtoupper(substr($user['username'] ?? 'U', 0, 2)) }}
                                </div>
                                <div class="user-details">
                                    <span class="user-username">@ {{ $user['username'] ?? 'Unknown' }}</span>
                                    <span class="user-chatid">{{ $user['user_id'] ?? '' }}</span>
                                </div>
                            </div>
                        </td>
                        <td><span style="color: var(--cyan); font-size: 12px">Bot {{ $user['bot'] ?? 'N/A' }}</span></td>
                        <td>
                            @if(isset($user['manager_is_paid']) && $user['manager_is_paid'])
                                <span class="badge badge-paid">Paid</span>
                            @else
                                <span class="badge badge-trial">Free</span>
                            @endif
                        </td>
                        <td>
                            @if(isset($user['shot_is_paid']) && $user['shot_is_paid'])
                                <span class="badge badge-paid">Paid</span>
                            @else
                                <span class="badge badge-trial">Free</span>
                            @endif
                        </td>

                        <td>
                            @php
                                $mode = $user['money_management_uni_strategy_status'] ?? '';
                                $isActive = strtolower($mode) === 'active' || strtolower($mode) === 'passive';
                            @endphp
                            <div class="mode-cell {{ $isActive ? 'active' : 'passive' }}">
                                @if($mode == "active")
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="24"
                                        height="24"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        data-lucide="zap"
                                        class="lucide lucide-zap"
                                    >
                                        <path
                                            d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a .5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"
                                        ></path>
                                    </svg>
                                    Active
                                @elseif($mode == "passive")
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="24"
                                        height="24"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        data-lucide="toggle-left"
                                        class="lucide lucide-toggle-left"
                                    >
                                        <circle cx="9" cy="12" r="3"></circle>
                                        <rect width="20" height="14" x="2" y="5" rx="7"></rect>
                                    </svg>
                                    Passive
                                @else
                                    N/A
                                @endif
                            </div>
                        </td>
                        <td>
                            @php
                                $strategyStatus = $user['money_management_uni_strategy_status'] ?? null;
                                $isStrategyActive = $strategyStatus && strtolower($strategyStatus) === 'active' || strtolower($strategyStatus) === 'passive';
                                $leverage = $user['money_management_uni_leverage'] ?? 'N/A';
                                $risk = $user['money_management_risk'] ?? 'N/A';
                            @endphp
                            @if($isStrategyActive && isset($user['money_management_profit_strategy']))
                                <div class="strategy-cell">
                                    {{ ucwords(str_replace('_', ' ', $user['money_management_profit_strategy'])) }}
                                    @if(isset($user['money_management_profit_strategy_tp']))
                                        (TP{{ $user['money_management_profit_strategy_tp'] }})
                                    @endif
                                </div>
                                <div class="strategy-details">{{ $leverage }}X · {{ $risk }}%</div>
                            @else
                                <div class="strategy-cell">N/A</div>
                                <div class="strategy-details">{{ $leverage }}X · {{ $risk }}%</div>
                            @endif
                        </td>

                        <td><span style="font-size: 12px; color: var(--orange)">{{ isset($user['money_management_uni_leverage']) ? $user['money_management_uni_leverage'] . 'X' : 'N/A' }}</span></td>
                        <td><span style="font-size: 12px">{{ isset($user['money_management_risk']) ? $user['money_management_risk'] . '%' : 'N/A' }}</span></td>
                        <td>
                            @if(isset($user['bybit_is_connected']) && $user['bybit_is_connected'])
                                <div class="api-status connected">
                                    <i data-lucide="check-circle" style="width: 16px; height: 16px;"></i>
                                    Bybit
                                </div>
                            @endif
                            @if(isset($user['binance_is_connected']) && $user['binance_is_connected'])
                                <div class="api-status connected" style="margin-top: 4px">
                                    <i data-lucide="check-circle" style="width: 16px; height: 16px;"></i>
                                    Binance
                                </div>
                            @endif
                            @if((!isset($user['bybit_is_connected']) || !$user['bybit_is_connected']) && (!isset($user['binance_is_connected']) || !$user['binance_is_connected']))
                                <span style="color: var(--text-gray); font-size: 12px">None</span>
                            @endif
                        </td>
                        <td style="text-align: center">{{ $user['trade_stats']['total'] ?? 0 }}</td>
                        <td style="text-align: center">{{ $user['trade_stats']['active'] ?? 0 }}</td>
                        <td style="text-align: center">
                            @php
                                $winRate = $user['trade_stats']['win_rate'] ?? 0;
                                $badgeClass = $winRate >= 70 ? 'good' : ($winRate >= 50 ? 'average' : 'poor');
                            @endphp
                            <span class="winrate-badge {{ $badgeClass }}">{{ $winRate }}%</span>
                        </td>
                        <td><span style="color: var(--text-gray); font-size: 12px">
                            @if(isset($user['updated_at']))
                                {{ \Carbon\Carbon::parse($user['updated_at'])->diffForHumans() }}
                            @else
                                N/A
                            @endif
                        </span></td>
                        <td><span style="color: var(--text-gray); font-size: 12px">{{ isset($user['created_at']) ? \Carbon\Carbon::parse($user['created_at'])->format('M d, Y') : 'N/A' }}</span></td>
                        <td>
                            <div class="actions-cell">
                                <button class="action-btn view" onclick="openUserModal({{ $user['user_id'] ?? $index }})" title="View">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="24"
                                        height="24"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        data-lucide="eye"
                                        class="lucide lucide-eye"
                                    >
                                        <path
                                            d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"
                                        ></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                                <button class="action-btn message" onclick="messageUser({{ $user['user_id'] ?? $index }})" title="Message">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="24"
                                        height="24"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        data-lucide="message-square"
                                        class="lucide lucide-message-square"
                                    >
                                        <path
                                            d="M22 17a2 2 0 0 1-2 2H6.828a2 2 0 0 0-1.414.586l-2.202 2.202A.71.71 0 0 1 2 21.286V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2z"
                                        ></path>
                                    </svg>
                                </button>
                                <button class="action-btn edit" onclick="editUserModal({{ $user['user_id'] ?? $index }})" title="Edit">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="24"
                                        height="24"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        data-lucide="edit"
                                        class="lucide lucide-edit"
                                    >
                                        <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path
                                            d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"
                                        ></path>
                                    </svg>
                                </button>
                                <div class="dropdown" style="position: relative">
                                    <button class="action-btn" onclick="toggleUserDropdown({{ $user['user_id'] ?? $index }})" title="More">
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            width="24"
                                            height="24"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            data-lucide="more-vertical"
                                            class="lucide lucide-more-vertical"
                                        >
                                            <circle cx="12" cy="12" r="1"></circle>
                                            <circle cx="12" cy="5" r="1"></circle>
                                            <circle cx="12" cy="19" r="1"></circle>
                                        </svg>
                                    </button>
                                    <div
                                        class="dropdown-menu"
                                        id="dropdown-{{ $user['user_id'] ?? $index }}"
                                        style="
                                            display: none;
                                            position: absolute;
                                            right: 0;
                                            top: 100%;
                                            background: var(--bg-card);
                                            border: 1px solid var(--border);
                                            border-radius: 8px;
                                            padding: 8px;
                                            z-index: 1000;
                                            min-width: 150px;
                                        "
                                    >
                                        <button
                                            class="dropdown-item"
                                            onclick="resetUserSettings({{ $user['user_id'] ?? $index }})"
                                            style="
                                                width: 100%;
                                                text-align: left;
                                                padding: 8px;
                                                background: none;
                                                border: none;
                                                color: var(--text-white);
                                                cursor: pointer;
                                                font-size: 13px;
                                                display: flex;
                                                align-items: center;
                                                gap: 8px;
                                            "
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                width="24"
                                                height="24"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                data-lucide="refresh-cw"
                                                style="width: 14px; height: 14px"
                                                class="lucide lucide-refresh-cw"
                                            >
                                                <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                                                <path d="M21 3v5h-5"></path>
                                                <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                                                <path d="M8 16H3v5"></path>
                                            </svg>
                                            Reset Settings
                                        </button>
                                        <button
                                            class="dropdown-item"
                                            onclick="banUser({{ $user['user_id'] ?? $index }})"
                                            style="
                                                width: 100%;
                                                text-align: left;
                                                padding: 8px;
                                                background: none;
                                                border: none;
                                                color: var(--red);
                                                cursor: pointer;
                                                font-size: 13px;
                                                display: flex;
                                                align-items: center;
                                                gap: 8px;
                                            "
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                width="24"
                                                height="24"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                data-lucide="user-x"
                                                style="width: 14px; height: 14px"
                                                class="lucide lucide-user-x"
                                            >
                                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="9" cy="7" r="4"></circle>
                                                <line x1="17" x2="22" y1="8" y2="13"></line>
                                                <line x1="22" x2="17" y1="8" y2="13"></line>
                                            </svg>
                                            Ban User
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                        @endforeach
                    @else
                    <tr>
                        <td colspan="16" style="text-align: center; padding: 40px; color: var(--text-gray);">
                            No users found
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </table>
    </div>
    <div class="pagination">
        @php
            $currentPage = $response['current_page'] ?? 1;
            $perPage = $response['per_page'] ?? 20;
            $total = $response['total'] ?? 0;
            $lastPage = $response['last_page'] ?? 1;
            $from = ($currentPage - 1) * $perPage + 1;
            $to = min($currentPage * $perPage, $total);
        @endphp
        <div class="pagination-info">
            Showing {{ $total > 0 ? $from : 0 }}-{{ $to }} of {{ $total }} users
        </div>
        <div class="pagination-controls">
            @php
                $filterParams = '';
                if (request('search')) $filterParams .= '&search=' . urlencode(request('search'));
                if (request('manager') && request('manager') != 'all') $filterParams .= '&manager=' . urlencode(request('manager'));
                if (request('shot') && request('shot') != 'all') $filterParams .= '&shot=' . urlencode(request('shot'));
                if (request('mod') && request('mod') != 'all') $filterParams .= '&mod=' . urlencode(request('mod'));
                if (request('bot') && request('bot') != 'all') $filterParams .= '&bot=' . urlencode(request('bot'));
            @endphp
            <a href="{{ url()->current() }}?page={{ $currentPage - 1 }}&per_page={{ $perPage }}{{ $filterParams }}"
               class="pagination-btn {{ $currentPage <= 1 ? 'disabled' : '' }}"
               style="{{ $currentPage <= 1 ? 'pointer-events: none; opacity: 0.5;' : '' }}">
                <i data-lucide="chevron-left"></i>
                Prev
            </a>
            <div class="pagination-numbers">
                @for($i = 1; $i <= $lastPage; $i++)
                    @if($i == 1 || $i == $lastPage || ($i >= $currentPage - 2 && $i <= $currentPage + 2))
                        <a href="{{ url()->current() }}?page={{ $i }}&per_page={{ $perPage }}{{ $filterParams }}"
                           class="pagination-number {{ $i == $currentPage ? 'active' : '' }}">
                            {{ $i }}
                        </a>
                    @elseif($i == $currentPage - 3 || $i == $currentPage + 3)
                        <span class="pagination-ellipsis">...</span>
                    @endif
                @endfor
            </div>
            <a href="{{ url()->current() }}?page={{ $currentPage + 1 }}&per_page={{ $perPage }}{{ $filterParams }}"
               class="pagination-btn {{ $currentPage >= $lastPage ? 'disabled' : '' }}"
               style="{{ $currentPage >= $lastPage ? 'pointer-events: none; opacity: 0.5;' : '' }}">
                Next
                <i data-lucide="chevron-right"></i>
            </a>
        </div>
        <select class="form-select" id="pageSizeSelect" style="width: auto" onchange="changePageSize(this.value)">
            <option value="20" {{ $perPage == 20 ? 'selected' : '' }}>20 per page</option>
            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 per page</option>
            <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 per page</option>
        </select>
    </div>
</div>

<!-- User Detail Modal -->
<div class="modal-overlay" id="userModalOverlay">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="user-avatar-large" id="modalUserAvatar">TM</div>
                <div class="modal-user-info">
                    <div class="modal-username" id="modalUsername">
                        @trader_mike
                        <span class="badge badge-paid" id="modalManagerBadge">Paid</span>
                        <span class="badge badge-trial" id="modalShotBadge">Trial</span>
                        <span class="badge badge-active" id="modalModeBadge">Active ⚡</span>
                    </div>
                    <div class="modal-chatid" id="modalChatId">6062724880</div>
                    <div class="modal-stats">
                        <div class="modal-stat">
                            <div class="modal-stat-value" id="modalSignals">47</div>
                            <div class="modal-stat-label">Signals</div>
                        </div>
                        <div class="modal-stat">
                            <div class="modal-stat-value" id="modalTrades">23</div>
                            <div class="modal-stat-label">Trades</div>
                        </div>
                        <div class="modal-stat">
                            <div class="modal-stat-value" id="modalWinRate" style="color: var(--green)">68%</div>
                            <div class="modal-stat-label">Win Rate</div>
                        </div>
                        <div class="modal-stat">
                            <div class="modal-stat-value" id="modalPnl" style="color: var(--green)">+234</div>
                            <div class="modal-stat-label">PnL</div>
                        </div>
                    </div>
                </div>
            </div>
            <button class="modal-close" onclick="closeUserModal()">✕</button>
        </div>
        <div class="modal-body">
            <!-- SignalManager & SignalShot -->
            <div class="modal-grid">
                <div class="modal-card">
                    <div class="modal-section-title">
                        <i data-lucide="zap"></i>
                        SignalManager
                    </div>
                    <div class="modal-row">
                        <span class="modal-label">Status</span>
                        <span class="modal-value badge badge-paid" id="modalManagerStatus">Paid</span>
                    </div>
                    <div class="modal-row">
                        <span class="modal-label">Start Date</span>
                        <span class="modal-value" id="modalManagerStart">Nov 15, 2025</span>
                    </div>
                    <div class="modal-row">
                        <span class="modal-label">Expires</span>
                        <span class="modal-value" id="modalManagerEnd">Dec 15, 2025</span>
                    </div>
                    <div class="modal-row">
                        <span class="modal-label">Signals Used</span>
                        <span class="modal-value" id="modalSignalsUsed">47</span>
                    </div>
                </div>

                <div class="modal-card">
                    <div class="modal-section-title">
                        <i data-lucide="target"></i>
                        SignalShot
                    </div>
                    <div class="modal-row">
                        <span class="modal-label">Status</span>
                        <span class="modal-value badge badge-trial" id="modalShotStatus">Trial</span>
                    </div>
                    <div class="modal-row">
                        <span class="modal-label">Start Date</span>
                        <span class="modal-value" id="modalShotStart">Nov 20, 2025</span>
                    </div>
                    <div class="modal-row">
                        <span class="modal-label">Expires</span>
                        <span class="modal-value" id="modalShotEnd">Nov 27, 2025</span>
                    </div>
                    <div class="modal-row">
                        <span class="modal-label">Trades Executed</span>
                        <span class="modal-value" id="modalTradesExec">23</span>
                    </div>
                </div>
            </div>

            <!-- Mode & Strategy -->
            <div class="modal-grid">
                <div class="modal-card">
                    <div class="modal-section-title">
                        <i data-lucide="settings"></i>
                        Mode & Strategy
                    </div>
                    <div class="modal-row">
                        <span class="modal-label">Trading Mode</span>
                        <div class="modal-value">
                            <label class="toggle-switch">
                                <input type="checkbox" id="modalModeToggle" checked />
                                <span class="toggle-slider"></span>
                            </label>
                            <span id="modalModeLabel" style="margin-left: 10px"></span>
                        </div>
                    </div>
                    <div class="modal-row">
                        <span class="modal-label">Strategy</span>
                        <span class="modal-value" id="modalStrategy">Close at TP2</span>
                    </div>
                    <div class="modal-row">
                        <span class="modal-label">Leverage</span>
                        <span class="modal-value" id="modalLeverage">0X</span>
                    </div>
                </div>

                <div class="modal-card">
                    <div class="modal-section-title">
                        <i data-lucide="shield"></i>
                        Risk Management
                    </div>
                    <div class="modal-row">
                        <span class="modal-label">Risk per Trade</span>
                        <span class="modal-value" id="modalRiskPercent">2%</span>
                    </div>
                    <div class="modal-row">
                        <span class="modal-label">Max Daily Loss</span>
                        <span class="modal-value" id="modalMaxRisk">50 USDT</span>
                    </div>
                    <div class="modal-row">
                        <span class="modal-label">Base Balance</span>
                        <span class="modal-value" id="modalBaseBalance">1000 USDT</span>
                    </div>
                    <div class="modal-row">
                        <span class="modal-label">Daily Reset</span>
                        <span class="modal-value">00:00 UTC</span>
                    </div>
                </div>
            </div>

            <!-- Safety Rules -->
            <div class="modal-card" style="margin-bottom: 24px">
                <div class="modal-section-title">
                    <i data-lucide="shield-check"></i>
                    Money Management Settings
                </div>
                <div class="modal-row">
                    <span class="modal-label">Max Account Exposure</span>
                    <span class="modal-value" id="modalMaxExposure">35%</span>
                </div>
                <div class="modal-row">
                    <span class="modal-label">Trade Limit</span>
                    <span class="modal-value" id="modalTradeLimit">10</span>
                </div>
                <!-- <div class="modal-row">
                    <span class="modal-label">Stop Trades At</span>
                    <span class="modal-value" id="modalStopTrades">0 USDT Loss</span>
                </div> -->
                <div class="modal-row">
                    <span class="modal-label">Exchange</span>
                    <span class="modal-value" id="modalExchange">Bybit</span>
                </div>
            </div>

            <!-- API Connections -->
            <div class="modal-card" style="margin-bottom: 24px">
                <div class="modal-section-title">
                    <i data-lucide="link"></i>
                    API Connections
                </div>
                <div id="modalAPIStatus">
                    <!-- Dynamic API connection status will be populated here -->
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="modal-card">
                <div class="modal-section-title">
                    <i data-lucide="history"></i>
                    Recent Activity
                </div>
                <div class="activity-list" id="modalRecentActivity">
                    <!-- Dynamic activity will be populated here -->
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeUserModal()">
                <i data-lucide="x"></i>Close
            </button>
        </div>
    </div>
</div>

<!-- Message Broadcast Modal -->
<div class="modal-overlay" id="messageModalOverlay">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <div class="modal-header-left">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #00d9e9, #3b82f6); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="message-square" style="width: 24px; height: 24px; color: white;"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 18px; color: var(--text-white);">Broadcast Message</h3>
                        <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-gray);">
                            Send message to <span id="messageUserCount" style="color: var(--cyan); font-weight: 600;">{{ $response['total'] }}</span> users
                        </p>
                    </div>
                </div>
            </div>
            <button class="modal-close" onclick="closeMessageModal()">✕</button>
        </div>
        <div class="modal-body" style="padding: 24px;">
            <!-- Message Input -->
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: var(--text-white);">
                    Message
                </label>
                <textarea
                    id="broadcastMessage"
                    class="form-input"
                    rows="6"
                    placeholder="Type your message here..."
                    style="width: 100%; resize: vertical; font-family: inherit;"
                ></textarea>
                <div style="margin-top: 8px; font-size: 12px; color: var(--text-gray);">
                    <span id="messageCharCount">0</span> characters
                </div>
            </div>

            <!-- Progress Section (Hidden by default) -->
            <div id="messageProgress" style="display: none;">
                <div style="margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 14px; color: var(--text-white);">Sending Progress</span>
                        <span style="font-size: 14px; color: var(--cyan); font-weight: 600;">
                            <span id="progressCurrent">0</span> / <span id="progressTotal">0</span>
                        </span>
                    </div>
                    <div style="width: 100%; height: 8px; background: var(--bg-secondary); border-radius: 4px; overflow: hidden;">
                        <div id="progressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #00d9e9, #3b82f6); transition: width 0.3s ease;"></div>
                    </div>
                </div>

                <!-- Status Messages -->
                <div id="progressStatus" style="max-height: 200px; overflow-y: auto; background: var(--bg-secondary); border-radius: 8px; padding: 12px; font-size: 12px;">
                    <!-- Progress messages will appear here -->
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeMessageModal()" id="messageCancelBtn">
                <i data-lucide="x"></i>Cancel
            </button>
            <button class="btn btn-primary" onclick="sendBroadcastMessage()" id="messageSendBtn" style="background: linear-gradient(135deg, #00d9e9, #3b82f6);">
                <i data-lucide="send"></i>Send Message
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Pass PHP data to JavaScript
    const usersData = @json($response['data'] ?? []);

    // Open user modal
    function openUserModal(userId) {
        // Check if modal exists on this page
        const userModalOverlay = document.getElementById("userModalOverlay");
        if (!userModalOverlay) {
            alert("User modal is not available on this page yet. This feature is coming soon!");
            return;
        }

        const user = usersData.find((u) => u.user_id == userId);
        if (!user) {
            alert("User not found!");
            return;
        }

        // Helper function to safely set element content
        const safeSetContent = (id, content) => {
            const element = document.getElementById(id);
            if (element) element.textContent = content;
        };

        const safeSetHTML = (id, html) => {
            const element = document.getElementById(id);
            if (element) element.innerHTML = html;
        };

        const safeSetValue = (id, value) => {
            const element = document.getElementById(id);
            if (element) element.value = value;
        };

        const safeSetClass = (id, className) => {
            const element = document.getElementById(id);
            if (element) element.className = className;
        };

        // Set basic info
        const modalUserAvatar = document.getElementById("modalUserAvatar");
        if (modalUserAvatar) {
            const initials = (user.username || 'U').substring(0, 2).toUpperCase();
            modalUserAvatar.textContent = initials;
            modalUserAvatar.style.background = 'linear-gradient(135deg, #00d9e9, #3b82f6)';
        }

        // Manager status badge
        const managerStatus = user.manager_is_paid ? 'paid' : 'trial';
        const managerLabel = user.manager_is_paid ? 'Paid' : 'Free';

        // Shot status badge
        const shotStatus = user.shot_is_paid ? 'paid' : 'free';
        const shotLabel = user.shot_is_paid ? 'Paid' : 'None';

        // Mode badge
        const mode = user.money_management_uni_strategy_status.toLowerCase();
        const isActive = mode === 'active' || mode === 'passive';
        const modeLabelText = toCapitalize(mode);
        const modeBadge = isActive ? 'active' : 'info';

        safeSetHTML("modalUsername", `
            @${user.username || 'Unknown'}
            <span class="badge badge-${managerStatus}" id="modalManagerBadge">${managerLabel}</span>
            <span class="badge badge-${shotStatus}" id="modalShotBadge">${shotLabel}</span>
            <span class="badge badge-${modeBadge}" id="modalModeBadge">${modeLabelText}</span>
        `);
        safeSetContent("modalChatId", user.user_id || '');

        // Set stats from trade_stats
        const tradeStats = user.trade_stats || {};
        safeSetContent("modalSignals", tradeStats.total || 0);
        safeSetContent("modalTrades", tradeStats.active || 0);
        safeSetContent("modalWinRate", (tradeStats.win_rate || 0) + "%");
        const pnl = tradeStats.total_pnl || 0;
        safeSetContent("modalPnl", (pnl >= 0 ? "+" : "") + pnl.toFixed(2));

        // Set SignalManager info
        safeSetContent("modalManagerStatus", managerLabel);
        safeSetClass("modalManagerStatus", `badge badge-${managerStatus}`);
        safeSetContent("modalManagerStart", user.manager_activation_in);
        safeSetContent("modalManagerEnd", user.manager_expired_in);
        safeSetContent("modalSignalsUsed", tradeStats.total || 0);

        // Set SignalShot info
        safeSetContent("modalShotStatus", shotLabel);
        safeSetClass("modalShotStatus", `badge badge-${shotStatus}`);
        safeSetContent("modalShotStart", user.shot_activation_in);
        safeSetContent("modalShotEnd", user.shot_expired_in);
        safeSetContent("modalTradesExec", tradeStats.active || 0);

        // Set trading settings (VIEW ONLY - disable toggle)
        const modeToggle = document.getElementById("modalModeToggle");
        if (modeToggle) {
            modeToggle.checked = isActive;
            modeToggle.disabled = true; // Read-only
        }

        const modeLabel = document.getElementById("modalModeLabel");
        if (modeLabel) {
            modeLabel.textContent = modeLabelText;
        }

        // Strategy - check if strategy status is active
        let strategyDisplay = 'N/A';
        const strategyStatus = user.money_management_uni_strategy_status;

        if (strategyStatus && (strategyStatus.toLowerCase() === 'active' || strategyStatus.toLowerCase() === 'passive')) {
            const strategy = user.money_management_profit_strategy || 'close_specific_tp';
            const strategyTP = user.money_management_profit_strategy_tp;
            const strategyLabel = strategy.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            strategyDisplay = strategyTP ? `${strategyLabel} (TP${strategyTP})` : strategyLabel;
        }

        // Display all fields as read-only text

        let baseBalance = 0;
        if(user.money_management_type === "demo"){
            baseBalance = user.money_management_demo_wallet_balance;
        }else{
            baseBalance = user.money_management_bybit_wallet_balance;
        }
        
        safeSetContent("modalStrategy", strategyDisplay);
        safeSetContent("modalLeverage", (user.money_management_uni_leverage) + "X");
        safeSetContent("modalRiskPercent", (user.money_management_risk) + "%");
        safeSetContent("modalMaxRisk", (user.money_management_daily_loss) + " USDT");
        safeSetContent("modalBaseBalance", baseBalance + " USDT");

        // Money Management Settings
        safeSetContent("modalMaxExposure", (user.money_management_max_exposure || 0) + "%");
        safeSetContent("modalTradeLimit", user.money_management_trade_limit || 0);
        // safeSetContent("modalStopTrades", (user.money_management_stop_trades || 0) + " USDT Loss");
        safeSetContent("modalExchange", (user.money_management_exchange || 'None').toUpperCase());

        // API Connection Status
        const apiConnections = [];
        if (user.bybit_is_connected) {
            const bybitBalance = user.bybit_balance || 0;
            apiConnections.push(`<div class="modal-row">
                <span class="modal-label">Bybit</span>
                <span class="modal-value">
                    <span class="badge badge-success">✅ Connected</span>
                    <span style="margin-left: 10px; color: var(--text-white);">Balance: ${bybitBalance} USDT</span>
                </span>
            </div>`);
        }
        if (user.binance_is_connected) {
            const binanceBalance = user.binance_balance || 0;
            apiConnections.push(`<div class="modal-row">
                <span class="modal-label">Binance</span>
                <span class="modal-value">
                    <span class="badge badge-success">✅ Connected</span>
                    <span style="margin-left: 10px; color: var(--text-white);">Balance: ${binanceBalance} USDT</span>
                </span>
            </div>`);
        }
        if (apiConnections.length === 0) {
            apiConnections.push(`<div class="modal-row">
                <span class="modal-label">Status</span>
                <span class="modal-value" style="color: var(--text-gray)">No API Connected</span>
            </div>`);
        }
        safeSetHTML("modalAPIStatus", apiConnections.join(''));

        // Recent Activity
        const recentActivity = user.recent_activity || [];
        const activityHTML = [];

        if (recentActivity.length > 0) {
            recentActivity.forEach(activity => {
                // Determine icon and color based on trade status and PnL
                let icon = 'activity';
                let iconColor = 'var(--cyan)';
                let bgColor = 'rgba(0, 217, 233, 0.2)';

                if (activity.status === 'closed') {
                    const pnl = activity.actual_profit_loss || 0;
                    if (pnl > 0) {
                        icon = 'trending-up';
                        iconColor = 'var(--green)';
                        bgColor = 'rgba(34, 197, 94, 0.2)';
                    } else if (pnl < 0) {
                        icon = 'trending-down';
                        iconColor = 'var(--red)';
                        bgColor = 'rgba(239, 68, 68, 0.2)';
                    } else {
                        icon = 'minus';
                        iconColor = 'var(--text-gray)';
                        bgColor = 'rgba(156, 163, 175, 0.2)';
                    }
                } else if (activity.status === 'active' || activity.status === 'waiting') {
                    icon = 'zap';
                    iconColor = 'var(--orange)';
                    bgColor = 'rgba(245, 158, 11, 0.2)';
                }

                // Format trade message
                const instrument = activity.instruments || 'N/A';
                const direction = (activity.tp_mode || '').toUpperCase();
                const status = activity.status || 'unknown';
                const message = `Trade ${instrument} ${direction} ${status}`;

                // Format time and PnL
                let timeText = '';
                if (activity.created_at) {
                    const activityDate = new Date(activity.created_at);
                    timeText = activityDate.toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }

                // Add PnL if available and trade is closed
                if (activity.status === 'closed' && activity.actual_profit_loss !== undefined) {
                    const pnl = activity.actual_profit_loss;
                    const pnlSign = pnl >= 0 ? '+' : '';
                    timeText += ` · PnL: ${pnlSign}${pnl.toFixed(2)} USDT`;
                }

                activityHTML.push(`
                    <div class="activity-item">
                        <div class="activity-icon" style="background: ${bgColor}">
                            <i data-lucide="${icon}" style="color: ${iconColor}"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-message">${message}</div>
                            <div class="activity-time">${timeText}</div>
                        </div>
                    </div>
                `);
            });
        } else {
            activityHTML.push(`
                <div style="text-align: center; padding: 20px; color: var(--text-gray);">
                    No recent activity
                </div>
            `);
        }

        safeSetHTML("modalRecentActivity", activityHTML.join(''));

        // Open modal
        userModalOverlay.classList.add("active");

        lucide.createIcons();
    }

    function toCapitalize(str) {
        if (!str) return '';  // Handle empty string
        return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();  // Capitalize first char, lowercase the rest
    }

    // Close user modal
    function closeUserModal() {
        const userModalOverlay = document.getElementById("userModalOverlay");
        if (userModalOverlay) {
            userModalOverlay.classList.remove("active");
        }
    }

    // Stub functions for other actions
    function messageUser(userId) {
        alert("Message user feature coming soon!");
    }

    function editUserModal(userId) {
        alert("Edit user feature coming soon!");
    }

    function toggleUserDropdown(userId) {
        const dropdown = document.getElementById(`dropdown-${userId}`);
        if (dropdown) {
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }
    }

    function resetUserSettings(userId) {
        if (confirm('Are you sure you want to reset all settings for this user?')) {
            alert("Reset user settings feature coming soon!");
        }
    }

    function banUser(userId) {
        if (confirm('Are you sure you want to ban this user?')) {
            alert("Ban user feature coming soon!");
        }
    }

    function toggleSelectAll() {
        alert("Select all feature coming soon!");
    }

    function toggleUserSelection(userId, event) {
        if (event) event.stopPropagation();
        alert("User selection feature coming soon!");
    }

    function exportUsersCSV() {
        alert("Export users to CSV feature coming soon!");
    }

    function openAddUserModal() {
        alert("Add user feature coming soon!");
    }

    function messageSelectedUsers() {
        alert("Message selected users feature coming soon!");
    }

    function exportSelectedUsers() {
        alert("Export selected users feature coming soon!");
    }

    function banSelectedUsers() {
        if (confirm('Are you sure you want to ban the selected users?')) {
            alert("Ban selected users feature coming soon!");
        }
    }

    // Change page size
    function changePageSize(perPage) {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('per_page', perPage);
        currentUrl.searchParams.set('page', 1); // Reset to first page
        // All filter parameters (search, manager, shot, mod, bot) are preserved automatically
        window.location.href = currentUrl.toString();
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
            });
        }
    });

    // ===== MESSAGE BROADCAST FUNCTIONS =====

    // Character counter for message
    document.addEventListener('DOMContentLoaded', function() {
        const messageTextarea = document.getElementById('broadcastMessage');
        const charCount = document.getElementById('messageCharCount');

        if (messageTextarea && charCount) {
            messageTextarea.addEventListener('input', function() {
                charCount.textContent = this.value.length;
            });
        }
    });

    // Open message modal
    function openMessageModal() {
        const messageModalOverlay = document.getElementById('messageModalOverlay');
        const messageTextarea = document.getElementById('broadcastMessage');
        const charCount = document.getElementById('messageCharCount');
        const progressSection = document.getElementById('messageProgress');
        const sendBtn = document.getElementById('messageSendBtn');
        const cancelBtn = document.getElementById('messageCancelBtn');

        if (messageModalOverlay) {
            // Reset modal
            if (messageTextarea) messageTextarea.value = '';
            if (charCount) charCount.textContent = '0';
            if (progressSection) progressSection.style.display = 'none';
            if (sendBtn) sendBtn.disabled = false;
            if (cancelBtn) cancelBtn.textContent = 'Cancel';

            messageModalOverlay.classList.add('active');
            lucide.createIcons();
        }
    }

    // Close message modal
    function closeMessageModal() {
        const messageModalOverlay = document.getElementById('messageModalOverlay');
        if (messageModalOverlay) {
            messageModalOverlay.classList.remove('active');
        }
    }

    // Send broadcast message
    async function sendBroadcastMessage() {
        const messageTextarea = document.getElementById('broadcastMessage');
        const message = messageTextarea ? messageTextarea.value.trim() : '';

        if (!message) {
            alert('Please enter a message to send');
            return;
        }

        if (!confirm(`Are you sure you want to send this message to ${usersData.length} users?`)) {
            return;
        }

        const progressSection = document.getElementById('messageProgress');
        const progressBar = document.getElementById('progressBar');
        const progressCurrent = document.getElementById('progressCurrent');
        const progressTotal = document.getElementById('progressTotal');
        const progressStatus = document.getElementById('progressStatus');
        const sendBtn = document.getElementById('messageSendBtn');
        const cancelBtn = document.getElementById('messageCancelBtn');

        // Show progress section
        if (progressSection) progressSection.style.display = 'block';
        if (progressTotal) progressTotal.textContent = usersData.length;
        if (progressCurrent) progressCurrent.textContent = '0';
        if (progressBar) progressBar.style.width = '0%';
        if (progressStatus) progressStatus.innerHTML = '<div style="color: var(--cyan);">Starting to send messages...</div>';

        // Disable send button
        if (sendBtn) {
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i data-lucide="loader"></i>Sending...';
            lucide.createIcons();
        }

        let successCount = 0;
        let failCount = 0;

        // Send messages one by one
        for (let i = 0; i < usersData.length; i++) {
            const user = usersData[i];
            const userId = user.user_id;
            const username = user.username || 'Unknown';

            try {
                // Call backend endpoint to send message
                const response = await fetch('{{ route("user.send-message") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        message: message
                    })
                });

                const result = await response.json();

                if (result.success) {
                    successCount++;
                    if (progressStatus) {
                        progressStatus.innerHTML += `<div style="color: var(--green); margin-top: 4px;">✓ Sent to @${username} (${userId})</div>`;
                    }
                } else {
                    failCount++;
                    if (progressStatus) {
                        progressStatus.innerHTML += `<div style="color: var(--red); margin-top: 4px;">✗ Failed to send to @${username} (${userId}): ${result.message || 'Unknown error'}</div>`;
                    }
                }
            } catch (error) {
                failCount++;
                if (progressStatus) {
                    progressStatus.innerHTML += `<div style="color: var(--red); margin-top: 4px;">✗ Error sending to @${username} (${userId}): ${error.message}</div>`;
                }
            }

            // Update progress
            const currentCount = i + 1;
            const percentage = Math.round((currentCount / usersData.length) * 100);

            if (progressCurrent) progressCurrent.textContent = currentCount;
            if (progressBar) progressBar.style.width = percentage + '%';

            // Auto-scroll to bottom of status
            if (progressStatus) {
                progressStatus.scrollTop = progressStatus.scrollHeight;
            }

            // Small delay to avoid rate limiting
            await new Promise(resolve => setTimeout(resolve, 100));
        }

        // Show completion message
        if (progressStatus) {
            progressStatus.innerHTML += `<div style="color: var(--cyan); margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border); font-weight: 600;">
                ✓ Completed! Success: ${successCount} | Failed: ${failCount}
            </div>`;
            progressStatus.scrollTop = progressStatus.scrollHeight;
        }

        // Re-enable buttons
        if (sendBtn) {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i data-lucide="check"></i>Completed';
            lucide.createIcons();
        }
        if (cancelBtn) {
            cancelBtn.innerHTML = '<i data-lucide="x"></i>Close';
            lucide.createIcons();
        }
    }

    // load prams
</script>
@endpush
@endsection
