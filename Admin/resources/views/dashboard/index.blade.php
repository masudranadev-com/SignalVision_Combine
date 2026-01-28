@extends('layouts.dashboard')

@section('page-title', 'Dashboard')

@section('page-content')
<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-glow" style="background: var(--cyan);"></div>
        <div class="stat-header">
            <div class="stat-icon" style="background: rgba(0, 217, 233, 0.2);">
                <i data-lucide="users" style="width: 24px; height: 24px; color: var(--cyan);"></i>
            </div>
            <div class="stat-change positive">
                <!-- <i data-lucide="trending-up" style="width: 14px; height: 14px;"></i> -->
                <!-- <span>+12.5%</span> -->
            </div>
        </div>
        <div class="stat-label">Total Users</div>
        <div class="stat-value">{{ $response['total_users'] }}</div>
        <!-- <div class="stat-subtitle">+235 from last month</div> -->
    </div>

    <div class="stat-card">
        <div class="stat-card-glow" style="background: var(--cyan);"></div>
        <div class="stat-header">
            <div class="stat-icon" style="background: rgba(0, 217, 233, 0.2);">
                <i data-lucide="users" style="width: 24px; height: 24px; color: var(--cyan);"></i>
            </div>
            <div class="stat-change positive">
                <!-- <i data-lucide="trending-up" style="width: 14px; height: 14px;"></i> -->
                <!-- <span>+12.5%</span> -->
            </div>
        </div>
        <div class="stat-label">Total paid Users</div>
        <div class="stat-value">{{ $response['paid_users'] }}</div>
        <!-- <div class="stat-subtitle">+235 from last month</div> -->
    </div>

    <div class="stat-card">
        <div class="stat-card-glow" style="background: var(--lime);"></div>
        <div class="stat-header">
            <div class="stat-icon" style="background: rgba(163, 230, 53, 0.2);">
                <i data-lucide="trending-up" style="width: 24px; height: 24px; color: var(--lime);"></i>
            </div>
            <div class="stat-change positive">
                <!-- <i data-lucide="trending-up" style="width: 14px; height: 14px;"></i> -->
                <!-- <span>+8.2%</span> -->
            </div>
        </div>
        <div class="stat-label">Waiting Trades</div>
        <div class="stat-value">{{ $response['trades']['waiting'] }}</div>
        <!-- <div class="stat-subtitle">156 pending signals</div> -->
    </div>

    <div class="stat-card">
        <div class="stat-card-glow" style="background: var(--lime);"></div>
        <div class="stat-header">
            <div class="stat-icon" style="background: rgba(163, 230, 53, 0.2);">
                <i data-lucide="trending-up" style="width: 24px; height: 24px; color: var(--lime);"></i>
            </div>
            <div class="stat-change positive">
                <!-- <i data-lucide="trending-up" style="width: 14px; height: 14px;"></i> -->
                <!-- <span>+8.2%</span> -->
            </div>
        </div>
        <div class="stat-label">Running Trades</div>
        <div class="stat-value">{{$response['trades']['running']}}</div>
        <!-- <div class="stat-subtitle">156 pending signals</div> -->
    </div>

    <div class="stat-card d-none">
        <div class="stat-card-glow" style="background: var(--green);"></div>
        <div class="stat-header">
            <div class="stat-icon" style="background: rgba(34, 197, 94, 0.2);">
                <i data-lucide="dollar-sign" style="width: 24px; height: 24px; color: var(--green);"></i>
            </div>
            <div class="stat-change positive">
                <!-- <i data-lucide="trending-up" style="width: 14px; height: 14px;"></i> -->
                <!-- <span>+23.1%</span> -->
            </div>
        </div>
        <div class="stat-label">Demo Trades Revenue</div>
        <div class="stat-value">${{ $response['trades']['demo_pnl'] }}</div>
        <!-- <div class="stat-subtitle">+$8,234 this month</div> -->
    </div>

     <div class="stat-card d-none">
        <div class="stat-card-glow" style="background: var(--green);"></div>
        <div class="stat-header">
            <div class="stat-icon" style="background: rgba(34, 197, 94, 0.2);">
                <i data-lucide="dollar-sign" style="width: 24px; height: 24px; color: var(--green);"></i>
            </div>
            <div class="stat-change positive">
                <!-- <i data-lucide="trending-up" style="width: 14px; height: 14px;"></i> -->
                <!-- <span>+23.1%</span> -->
            </div>
        </div>
        <div class="stat-label">Real Trades Revenue</div>
        <div class="stat-value">${{ $response['trades']['real_pnl'] }}</div>
        <!-- <div class="stat-subtitle">+$8,234 this month</div> -->
    </div>
</div>

<!-- Chart Card -->
<div class="card d-none">
    <div class="card-header">
        <h3 class="card-title">Revenue Overview</h3>
        <div class="period-selector">
            <button class="period-btn active">7D</button>
            <button class="period-btn">1M</button>
            <button class="period-btn">3M</button>
            <button class="period-btn">1Y</button>
        </div>
    </div>
    <div class="chart-container">
        <canvas id="revenueChart"></canvas>
    </div>
</div>

<!-- Users Table -->
<div class="table-container" style="margin-top: 25px;">
    <div class="table-header">
        <h3 class="card-title">Recent Users</h3>
        <div class="table-actions">
            <a class="btn btn-primary" href="{{route('user.all')}}">
                <i data-lucide="users"></i>
                View All Users
            </a>
        </div>
    </div>
    <div style="overflow-x: auto">
        <table id="usersTable">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Bot</th>
                    <th>Manager</th>
                    <th>Shot</th>
                    <th>Mode</th>
                    <th>Strategy</th>
                    <th>Signals</th>
                    <!-- <th>Demo PnL</th>
                    <th>Real PnL</th> -->
                    <th>Activity</th>
                </tr>
            </thead>
            <tbody>

            @foreach($response['users'] as $user)
                <tr>
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
                    <td>{{ $user['trade_stats']['total'] ?? 0 }}</td>
                    <td>
                        <span style="color: var(--text-gray); font-size: 12px">
                            @if(isset($user['updated_at']))
                                {{ \Carbon\Carbon::parse($user['updated_at'])->diffForHumans() }}
                            @else
                                N/A
                            @endif
                        </span>
                    </td>
                </tr>
                @endforeach

               
            </tbody>
        </table>
    </div>
</div>

<!-- Activity Feed -->
<div class="card d-none" style="margin-top: 25px;">
    <div class="card-header">
        <h3 class="card-title">Recent Activity</h3>
        <button class="card-link">
            View all
            <i data-lucide="arrow-right" style="width: 14px; height: 14px;"></i>
        </button>
    </div>
    <div class="activity-list">
        <div class="activity-item">
            <div class="activity-icon" style="background: rgba(34, 197, 94, 0.2);">
                <i data-lucide="user-plus" style="width: 16px; height: 16px; color: var(--green);"></i>
            </div>
            <div class="activity-content">
                <div class="activity-message">New user registered: <strong>john_doe</strong></div>
                <div class="activity-time">5 minutes ago</div>
            </div>
        </div>

        <div class="activity-item">
            <div class="activity-icon" style="background: rgba(0, 217, 233, 0.2);">
                <i data-lucide="trending-up" style="width: 16px; height: 16px; color: var(--cyan);"></i>
            </div>
            <div class="activity-content">
                <div class="activity-message">Trade signal executed: <strong>BTC/USDT Long</strong></div>
                <div class="activity-time">12 minutes ago</div>
            </div>
        </div>

        <div class="activity-item">
            <div class="activity-icon" style="background: rgba(245, 158, 11, 0.2);">
                <i data-lucide="dollar-sign" style="width: 16px; height: 16px; color: var(--orange);"></i>
            </div>
            <div class="activity-content">
                <div class="activity-message">Payment received: <strong>$99.00</strong></div>
                <div class="activity-time">1 hour ago</div>
            </div>
        </div>

        <div class="activity-item">
            <div class="activity-icon" style="background: rgba(139, 92, 246, 0.2);">
                <i data-lucide="settings" style="width: 16px; height: 16px; color: var(--purple);"></i>
            </div>
            <div class="activity-content">
                <div class="activity-message">Bot configuration updated: <strong>Grid Bot #5</strong></div>
                <div class="activity-time">2 hours ago</div>
            </div>
        </div>

        <div class="activity-item">
            <div class="activity-icon" style="background: rgba(239, 68, 68, 0.2);">
                <i data-lucide="alert-triangle" style="width: 16px; height: 16px; color: var(--red);"></i>
            </div>
            <div class="activity-content">
                <div class="activity-message">Stop loss triggered: <strong>ETH/USDT</strong></div>
                <div class="activity-time">3 hours ago</div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    lucide.createIcons();

    // Revenue Chart
    const ctx = document.getElementById('revenueChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Revenue',
                    data: [12000, 19000, 15000, 25000, 22000, 30000, 28000],
                    borderColor: '#00D9E9',
                    backgroundColor: 'rgba(0, 217, 233, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#00D9E9',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#374151'
                        },
                        ticks: {
                            color: '#9CA3AF'
                        }
                    },
                    x: {
                        grid: {
                            color: '#374151'
                        },
                        ticks: {
                            color: '#9CA3AF'
                        }
                    }
                }
            }
        });
    }
</script>
@endpush
@endsection
