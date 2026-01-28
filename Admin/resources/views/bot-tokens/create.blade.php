@extends('layouts.dashboard')

@section('page-title', 'Add New Bot Token')

@section('page-content')

<div class="section-header" style="margin-bottom: 25px">
    <div>
        <h1 class="page-title">Add New Bot Token</h1>
        <p class="page-subtitle">Create a new Telegram bot token for your users</p>
    </div>
    <div>
        <a href="{{ route('bot-token.index') }}" class="btn btn-secondary">
            <i data-lucide="arrow-left"></i>
            Back to List
        </a>
    </div>
</div>

<!-- Error Messages -->
@if(session('error'))
<div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--red); border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; color: var(--red); display: flex; align-items: center; gap: 10px;">
    <i data-lucide="alert-circle" style="width: 20px; height: 20px;"></i>
    <span>{{ session('error') }}</span>
</div>
@endif

<!-- Form Card -->
<div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 32px; max-width: 800px;">
    <form method="POST" action="{{ route('bot-token.store') }}" id="botTokenForm">
        @csrf

        <!-- Bot Name -->
        <div style="margin-bottom: 24px;">
            <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: var(--text-white);">
                Bot Name <span style="color: var(--red);">*</span>
            </label>
            <input
                type="text"
                name="name"
                class="form-input"
                placeholder="e.g., Support Bot Free"
                required
                value="{{ old('name') }}"
                style="width: 100%;"
            />
            <div style="margin-top: 6px; font-size: 12px; color: var(--text-gray);">
                A friendly name to identify this bot (max 255 characters)
            </div>
        </div>

        <!-- Bot Token -->
        <div style="margin-bottom: 24px;">
            <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: var(--text-white);">
                Bot Token <span style="color: var(--red);">*</span>
            </label>
            <input
                type="text"
                name="token"
                class="form-input"
                placeholder="e.g., 1234567890:ABCdefGHIjklMNOpqrsTUVwxyz"
                required
                value="{{ old('token') }}"
                style="width: 100%; font-family: 'Courier New', monospace;"
            />
            <div style="margin-top: 6px; font-size: 12px; color: var(--text-gray);">
                Get this from <a href="https://t.me/BotFather" target="_blank" style="color: var(--cyan);">@BotFather</a> on Telegram
            </div>
        </div>

        <!-- Bot Type -->
        <div style="margin-bottom: 24px;">
            <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: var(--text-white);">
                Bot Type <span style="color: var(--red);">*</span>
            </label>
            <div style="display: flex; gap: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 12px 20px; background: var(--bg-secondary); border: 2px solid var(--border); border-radius: 8px; flex: 1; transition: all 0.2s;">
                    <input type="radio" name="type" value="free" {{ old('type') == 'free' ? 'checked' : '' }} required style="width: 18px; height: 18px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500; color: var(--text-white);">Free Bot</div>
                        <div style="font-size: 12px; color: var(--text-gray);">For trial users</div>
                    </div>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 12px 20px; background: var(--bg-secondary); border: 2px solid var(--border); border-radius: 8px; flex: 1; transition: all 0.2s;">
                    <input type="radio" name="type" value="paid" {{ old('type') == 'paid' ? 'checked' : '' }} required style="width: 18px; height: 18px; cursor: pointer;">
                    <div>
                        <div style="font-weight: 500; color: var(--text-white);">Paid Bot</div>
                        <div style="font-size: 12px; color: var(--text-gray);">For premium users</div>
                    </div>
                </label>
            </div>
        </div>

        <!-- Max Users -->
        <div style="margin-bottom: 24px;">
            <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: var(--text-white);">
                Maximum Users <span style="color: var(--red);">*</span>
            </label>
            <input
                type="number"
                name="max_user"
                class="form-input"
                placeholder="e.g., 1000"
                required
                min="1"
                value="{{ old('max_user') }}"
                style="width: 100%;"
            />
            <div style="margin-top: 6px; font-size: 12px; color: var(--text-gray);">
                Maximum number of users this bot can handle (minimum 1)
            </div>
        </div>

        <!-- Active Status -->
        <div style="margin-bottom: 32px;">
            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') == '1' ? 'checked' : '' }} style="width: 18px; height: 18px; cursor: pointer;">
                <div>
                    <div style="font-weight: 500; color: var(--text-white);">Active</div>
                    <div style="font-size: 12px; color: var(--text-gray);">Bot can accept new users when active</div>
                </div>
            </label>
        </div>

        <!-- Form Actions -->
        <div style="display: flex; gap: 12px; padding-top: 24px; border-top: 1px solid var(--border);">
            <button type="submit" class="btn btn-primary" style="flex: 1;">
                <i data-lucide="check"></i>
                Create Bot Token
            </button>
            <a href="{{ route('bot-token.index') }}" class="btn btn-secondary">
                <i data-lucide="x"></i>
                Cancel
            </a>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        lucide.createIcons();

        // Add visual feedback for radio button selection
        const radioLabels = document.querySelectorAll('label:has(input[type="radio"])');
        radioLabels.forEach(label => {
            const radio = label.querySelector('input[type="radio"]');

            // Set initial state
            if (radio.checked) {
                label.style.borderColor = 'var(--cyan)';
                label.style.background = 'rgba(0, 217, 233, 0.1)';
            }

            radio.addEventListener('change', function() {
                // Reset all labels
                radioLabels.forEach(l => {
                    l.style.borderColor = 'var(--border)';
                    l.style.background = 'var(--bg-secondary)';
                });

                // Highlight selected
                if (this.checked) {
                    label.style.borderColor = 'var(--cyan)';
                    label.style.background = 'rgba(0, 217, 233, 0.1)';
                }
            });
        });

        // Form validation
        const form = document.getElementById('botTokenForm');
        form.addEventListener('submit', function(e) {
            const token = form.querySelector('input[name="token"]').value.trim();

            // Basic token format validation (Telegram bot tokens are typically: number:alphanumeric)
            const tokenPattern = /^\d+:[A-Za-z0-9_-]+$/;
            if (!tokenPattern.test(token)) {
                e.preventDefault();
                alert('Invalid bot token format. Token should be in format: 1234567890:ABCdefGHIjklMNOpqrsTUVwxyz');
                return false;
            }

            const maxUser = parseInt(form.querySelector('input[name="max_user"]').value);
            if (maxUser < 1) {
                e.preventDefault();
                alert('Maximum users must be at least 1');
                return false;
            }
        });
    });
</script>
@endpush
@endsection
