@php($restoreInput = (bool) old('creating_account'))
<dialog id="create-account" aria-labelledby="create-account-title" @if ($creatingAccount) data-open-edit-dialog @endif class="edit-dialog">
    <div class="flex items-start justify-between gap-3">
        <h2 id="create-account-title" class="text-xl font-bold text-maroon-800">Create account</h2>
        <button type="button" class="menu-icon !size-9" aria-label="Close dialog" data-close-edit-dialog>×</button>
    </div>
    @if ($restoreInput && $errors->any())
        <ul role="alert" class="mt-4 list-disc rounded-xl bg-maroon-50 py-3 pr-3 pl-8 text-sm text-maroon-900">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif
    <form method="post" action="{{ route('admin.users.store') }}" class="mt-5 space-y-4">
        @csrf
        <label class="field-label">Full name
            <input class="field" name="name" value="{{ $restoreInput ? old('name') : '' }}" maxlength="255" required>
        </label>
        <label class="field-label">Email address
            <input class="field" name="email" type="email" value="{{ $restoreInput ? old('email') : '' }}" maxlength="255" required>
        </label>
        <label class="field-label">Phone number
            @include('phone-input', ['phoneValue' => $restoreInput ? old('contact_phone', '+63 ') : '+63 '])
        </label>
        <label class="field-label">Password
            <input class="field" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
        </label>
        <div>
            <label class="field-label" for="create-account-role">Role</label>
            <select class="field" id="create-account-role" name="user_type" required>
                @foreach (['customer', 'staff', 'admin'] as $role)
                    <option value="{{ $role }}" @selected($restoreInput && old('user_type') === $role)>{{ ucfirst($role) }}</option>
                @endforeach
            </select>
        </div>
        @include('admin.staff-stall-select', ['selectedStall' => null])
        <div class="flex flex-wrap justify-end gap-2">
            <button type="button" class="design-secondary" data-close-edit-dialog>Cancel</button>
            <button class="design-primary">Create account</button>
        </div>
    </form>
</dialog>
