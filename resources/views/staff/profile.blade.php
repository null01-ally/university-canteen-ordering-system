@extends('layout')
@section('title', 'Profile & account')
@section('content')
@php($account = auth()->user())
<div class="workspace-page-title"><div><h1 class="workspace-heading">Profile &amp; account</h1><p>Manage your staff details and keep your campus account secure.</p></div></div>
<form method="post" action="{{ route('staff.profile.update') }}" class="workspace-settings-grid">
    @csrf @method('PATCH')
    <div>
        <section class="workspace-card">
            <h2 class="workspace-section-title">Personal details</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label class="field-label">Full name<input class="field" name="name" value="{{ old('name', $account->name) }}" required maxlength="255"></label>
                <label class="field-label">Contact number<input class="field" type="tel" name="contact_phone" value="{{ old('contact_phone', $account->contact_phone) }}" maxlength="32"></label>
                <label class="field-label sm:col-span-2">Email address<input class="field" type="email" name="email" value="{{ old('email', $account->email) }}" required maxlength="255"></label>
            </div>
        </section>
        <section class="workspace-card mt-6">
            <h2 class="workspace-section-title">Password &amp; security</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label class="field-label sm:col-span-2">Current password<input class="field" type="password" name="current_password" autocomplete="current-password" required></label>
                <label class="field-label">New password<input class="field" type="password" name="password" autocomplete="new-password" minlength="8" placeholder="Enter new password"></label>
                <label class="field-label">Confirm new password<input class="field" type="password" name="password_confirmation" autocomplete="new-password" placeholder="Re-enter new password"></label>
            </div>
            <p class="mt-4 text-xs text-stone-600">Use at least 8 characters. Leave the new password blank to keep your current password.</p>
        </section>
        <div class="mt-8 flex justify-end gap-4"><a class="button-secondary" href="{{ route('staff.profile.edit') }}">Cancel</a><button class="button-primary">Save changes</button></div>
        <p class="mt-4 text-xs text-stone-600">Account updates require your current password.</p>
    </div>
    <aside>
        <section class="workspace-card">
            <h2 class="workspace-section-title">Your staff assignment</h2>
            <span class="workspace-badge mt-5 bg-status-ready-bg text-status-ready-text">Active staff account</span>
            <h3 class="mt-7 text-2xl font-bold text-maroon-800">{{ $account->stall->name }}</h3><p class="mt-1 text-sm">{{ $account->stall->location }}</p>
            <dl class="mt-7 grid grid-cols-2 gap-5 text-sm"><dt class="text-stone-600">Role</dt><dd>Canteen staff</dd><dt class="text-stone-600">Staff ID</dt><dd>STF-{{ str_pad($account->id, 4, '0', STR_PAD_LEFT) }}</dd></dl>
        </section>
        <div class="mt-6 rounded-2xl bg-maroon-50 p-6"><h3 class="text-sm font-semibold text-maroon-800">Assigned by your administrator</h3><p class="mt-3 text-sm text-stone-600">Stall designation and staff roles can only be changed by a university administrator.</p></div>
    </aside>
</form>
@endsection
