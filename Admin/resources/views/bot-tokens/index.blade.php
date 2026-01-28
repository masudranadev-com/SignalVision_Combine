@extends('layouts.dashboard')

@section('page-title', 'Bot Tokens Management')

@section('page-content')

<div class="section-header" style="margin-bottom: 25px">
    <div>
        <h1 class="page-title">Telegram Bot Tokens</h1>
        <p class="page-subtitle">Manage bot tokens for free and paid users</p>
    </div>
    <div>
        <a href="{{ route('bot-token.create') }}" class="btn btn-primary">
            <i data-lucide="plus"></i>
            Add New Token
        </a>
    </div>
</div>

<!-- Success/Error Messages -->
@if(session('success'))
<div style="background: rgba(34, 197, 94, 0.1); border: 1px solid var(--green); border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; color: var(--green); display: flex; align-items: center; gap: 10px;">
    <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
    <span>{{ session('success') }}</span>
</div>
@endif

@if(session('error'))
<div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--red); border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; color: var(--red); display: flex; align-items: center; gap: 10px;">
    <i data-lucide="alert-circle" style="width: 20px; height: 20px;"></i>
    <span>{{ session('error') }}</span>
</div>
@endif

<!-- Filters -->
<form method="GET" action="{{ route('bot-token.index') }}" id="filterForm">
    <div class="filters-bar">
        <input type="hidden" name="page" value="1">
        <input type="hidden" name="per_page" value="{{ request('per_page', 20) }}">

        <div class="filter-group">
            <span class="filter-label">Type:</span>
            <select class="form-select" name="type" onchange="this.form.submit()">
                <option value="all" {{ ($filters['type'] ?? 'all') == 'all' ? 'selected' : '' }}>All Types</option>
                <option value="free" {{ ($filters['type'] ?? '') == 'free' ? 'selected' : '' }}>Free</option>
                <option value="paid" {{ ($filters['type'] ?? '') == 'paid' ? 'selected' : '' }}>Paid</option>
            </select>
        </div>

        <div class="filter-group">
            <span class="filter-label">Status:</span>
            <select class="form-select" name="is_active" onchange="this.form.submit()">
                <option value="all" {{ ($filters['is_active'] ?? 'all') == 'all' ? 'selected' : '' }}>All Status</option>
                <option value="1" {{ ($filters['is_active'] ?? '') == '1' ? 'selected' : '' }}>Active</option>
                <option value="0" {{ ($filters['is_active'] ?? '') == '0' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
    </div>
</form>

<!-- Bot Tokens Table -->
<div class="table-container">
    <div style="overflow-x: auto">
        <table>
            <thead>
                <tr>
                    <th>Bot Name</th>
                    <th>Token</th>
                    <th>Type</th>
                    <th>Users</th>
                    <th>Capacity</th>
                    <th style="text-align: center">Status</th>
                    <th>Created</th>
                    <th style="text-align: center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @if(isset($response['data']) && count($response['data']) > 0)
                    @foreach($response['data'] as $token)
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #00d9e9, #3b82f6); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px;">
                                {{ strtoupper(substr($token['name'] ?? 'B', 0, 2)) }}
                            </div>
                            <div>
                                <div style="font-weight: 500; color: var(--text-white);">{{ $token['name'] ?? 'Unknown' }}</div>
                                <div style="font-size: 12px; color: var(--text-gray);">ID: {{ $token['id'] ?? '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <code style="background: var(--bg-secondary); padding: 4px 8px; border-radius: 4px; font-size: 12px; color: var(--text-gray); font-family: 'Courier New', monospace;">
                                {{ isset($token['token']) ? substr($token['token'], 0, 20) . '...' : 'N/A' }}
                            </code>
                            <button onclick="copyToken('{{ $token['token'] ?? '' }}', {{ $token['id'] ?? 0 }})" class="btn-icon" title="Copy Token" style="width: 28px; height: 28px;">
                                <i data-lucide="copy" style="width: 14px; height: 14px;"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        @if(($token['type'] ?? '') === 'paid')
                            <span class="badge badge-paid">Paid</span>
                        @else
                            <span class="badge badge-free">Free</span>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight: 500; color: var(--text-white);">{{ $token['current_users'] ?? 0 }}</div>
                        <div style="font-size: 12px; color: var(--text-gray);">of {{ $token['max_user'] ?? 0 }} max</div>
                    </td>
                    <td>
                        @php
                            $usagePercent = $token['usage_percentage'] ?? 0;
                            $barColor = $usagePercent >= 90 ? 'var(--red)' : ($usagePercent >= 70 ? 'var(--orange)' : 'var(--green)');
                        @endphp
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="flex: 1; height: 6px; background: var(--bg-secondary); border-radius: 3px; overflow: hidden;">
                                <div style="width: {{ $usagePercent }}%; height: 100%; background: {{ $barColor }}; transition: width 0.3s ease;"></div>
                            </div>
                            <span style="font-size: 12px; color: {{ $barColor }}; font-weight: 600; min-width: 45px;">{{ number_format($usagePercent, 1) }}%</span>
                        </div>
                    </td>
                    <td style="text-align: center">
                        <label class="toggle-switch" style="margin: 0 auto;">
                            <input type="checkbox"
                                   {{ ($token['is_active'] ?? false) ? 'checked' : '' }}
                                   onchange="toggleBotStatus({{ $token['id'] ?? 0 }}, this)"
                            />
                            <span class="toggle-slider"></span>
                        </label>
                    </td>
                    <td>
                        <span style="color: var(--text-gray); font-size: 12px">
                            {{ isset($token['created_at']) ? \Carbon\Carbon::parse($token['created_at'])->format('M d, Y') : 'N/A' }}
                        </span>
                    </td>
                    <td>
                        <div class="actions-cell">
                            <a href="{{ route('bot-token.edit', $token['id'] ?? 0) }}" class="action-btn edit" title="Edit">
                                <i data-lucide="edit"></i>
                            </a>
                            <button class="action-btn" onclick="deleteBotToken({{ $token['id'] ?? 0 }})" title="Delete" style="color: var(--red);">
                                <i data-lucide="trash-2"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                    @endforeach
                @else
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-gray);">
                        <i data-lucide="database" style="width: 48px; height: 48px; margin-bottom: 12px;"></i>
                        <div>No bot tokens found</div>
                        <a href="{{ route('bot-token.create') }}" class="btn btn-primary" style="margin-top: 16px;">
                            <i data-lucide="plus"></i>
                            Add First Token
                        </a>
                    </td>
                </tr>
                @endif
            </tbody>
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
            Showing {{ $total > 0 ? $from : 0 }}-{{ $to }} of {{ $total }} bot tokens
        </div>
        <div class="pagination-controls">
            @php
                $filterParams = '';
                if (request('type') && request('type') != 'all') $filterParams .= '&type=' . urlencode(request('type'));
                if (request('is_active') && request('is_active') != 'all') $filterParams .= '&is_active=' . urlencode(request('is_active'));
            @endphp
            <a href="{{ route('bot-token.index') }}?page={{ $currentPage - 1 }}&per_page={{ $perPage }}{{ $filterParams }}"
               class="pagination-btn {{ $currentPage <= 1 ? 'disabled' : '' }}"
               style="{{ $currentPage <= 1 ? 'pointer-events: none; opacity: 0.5;' : '' }}">
                <i data-lucide="chevron-left"></i>
                Prev
            </a>
            <div class="pagination-numbers">
                @for($i = 1; $i <= $lastPage; $i++)
                    @if($i == 1 || $i == $lastPage || ($i >= $currentPage - 2 && $i <= $currentPage + 2))
                        <a href="{{ route('bot-token.index') }}?page={{ $i }}&per_page={{ $perPage }}{{ $filterParams }}"
                           class="pagination-number {{ $i == $currentPage ? 'active' : '' }}">
                            {{ $i }}
                        </a>
                    @elseif($i == $currentPage - 3 || $i == $currentPage + 3)
                        <span class="pagination-ellipsis">...</span>
                    @endif
                @endfor
            </div>
            <a href="{{ route('bot-token.index') }}?page={{ $currentPage + 1 }}&per_page={{ $perPage }}{{ $filterParams }}"
               class="pagination-btn {{ $currentPage >= $lastPage ? 'disabled' : '' }}"
               style="{{ $currentPage >= $lastPage ? 'pointer-events: none; opacity: 0.5;' : '' }}">
                Next
                <i data-lucide="chevron-right"></i>
            </a>
        </div>
        <select class="form-select" style="width: auto" onchange="changePageSize(this.value)">
            <option value="20" {{ $perPage == 20 ? 'selected' : '' }}>20 per page</option>
            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 per page</option>
            <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 per page</option>
        </select>
    </div>
</div>

@push('scripts')
<script>
    // Copy token to clipboard
    function copyToken(token, id) {
        if (!token) {
            alert('No token to copy');
            return;
        }

        navigator.clipboard.writeText(token).then(() => {
            // Show success feedback
            const btn = event.currentTarget;
            const icon = btn.querySelector('i');
            icon.setAttribute('data-lucide', 'check');
            lucide.createIcons();

            setTimeout(() => {
                icon.setAttribute('data-lucide', 'copy');
                lucide.createIcons();
            }, 2000);
        }).catch(err => {
            alert('Failed to copy token');
            console.error('Copy failed:', err);
        });
    }

    // Toggle bot status
    async function toggleBotStatus(id, checkbox) {
        try {
            const response = await fetch(`/bot-token/${id}/toggle-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const result = await response.json();

            if (result.success) {
                // Show success message
                console.log('Status toggled successfully');
            } else {
                // Revert checkbox on error
                checkbox.checked = !checkbox.checked;
                alert('Failed to toggle status: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            // Revert checkbox on error
            checkbox.checked = !checkbox.checked;
            alert('Error: ' + error.message);
            console.error('Toggle error:', error);
        }
    }

    // Delete bot token
    async function deleteBotToken(id) {
        if (!confirm('Are you sure you want to delete this bot token? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch(`/bot-token/${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const result = await response.json();

            if (result.success) {
                // Reload page on success
                window.location.reload();
            } else {
                alert('Failed to delete bot token: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            alert('Error: ' + error.message);
            console.error('Delete error:', error);
        }
    }

    // Change page size
    function changePageSize(perPage) {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('per_page', perPage);
        currentUrl.searchParams.set('page', 1);
        window.location.href = currentUrl.toString();
    }

    // Initialize icons on page load
    document.addEventListener('DOMContentLoaded', function() {
        lucide.createIcons();
    });
</script>
@endpush
@endsection
