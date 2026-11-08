@php($restoreInput = (string) old('editing_account') === (string) $account->id)
<dialog id="edit-account-{{ $account->id }}" aria-labelledby="edit-account-title-{{ $account->id }}" @if ($editingAccount?->is($account)) data-open-edit-dialog @endif class="edit-dialog">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 id="edit-account-title-{{ $account->id }}" class="text-xl font-bold text-maroon-800">Manage account</h2>
            <p class="mt-1 text-sm text-stone-600">{{ $account->name }} · {{ ucfirst($account->user_type) }}</p>
        </div>
        <button type="button" class="menu-icon !size-9" aria-label="Close account dialog" data-close-edit-dialog>×</button>
    </div>
    @if ($restoreInput && $errors->any())
        <ul role="alert" class="mt-4 list-disc rounded-xl bg-maroon-50 py-3 pr-3 pl-8 text-sm text-maroon-900">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif
    <form method="post" action="{{ route('admin.users.update', $account) }}" class="mt-5 space-y-4">
        @csrf
        @method('PATCH')
        <label class="field-label">Full name
            <input class="field" name="name" value="{{ $restoreInput ? old('name', $account->name) : $account->name }}" maxlength="255" required>
        </label>
        <label class="field-label">Email address
            <input class="field" name="email" type="email" value="{{ $restoreInput ? old('email', $account->email) : $account->email }}" maxlength="255" required>
        </label>
        @if ($account->is(auth()->user()))
            <input type="hidden" name="user_type" value="admin">
        @else
            <div>
                <label class="field-label" for="account-role-{{ $account->id }}">Role</label>
                <select class="field" id="account-role-{{ $account->id }}" name="user_type" required>
                    @foreach (['customer' => 'Customer', 'staff' => 'Canteen staff', 'admin' => 'Administrator'] as $value => $label)
                        <option value="{{ $value }}" @selected(($restoreInput ? old('user_type', $account->user_type) : $account->user_type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @include('admin.staff-stall-select', ['selectedStall' => $account->stall_id])
        @if ($account->is(auth()->user()))
            <input type="hidden" name="is_active" value="1">
        @else
            <div>
                <label class="field-label" for="account-status-{{ $account->id }}">Account status</label>
                <select class="field" id="account-status-{{ $account->id }}" name="is_active">
                    @foreach ([1 => 'Active', 0 => 'Deactivated'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) ($restoreInput ? old('is_active', (int) $account->is_active) : (int) $account->is_active) === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="flex flex-wrap justify-end gap-2">
            <button type="button" class="design-secondary" data-close-edit-dialog>Cancel</button>
            <button class="design-primary">Save changes</button>
        </div>
    </form>
    <form method="post" action="{{ route('admin.users.password-reset', $account) }}" class="mt-5 border-t border-maroon-100 pt-4">
        @csrf
        <p class="mb-2 text-xs text-stone-600">The account owner can choose a new password using a link sent to their email.</p>
        <button class="button-secondary" @disabled(! $account->is_active)>Send password reset link</button>
    </form>
</dialog>
