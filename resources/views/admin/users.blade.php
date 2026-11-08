@extends('layout')
@section('title', 'User accounts')
@section('content')
<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
    <div><h1 class="workspace-heading">User accounts</h1><p class="mt-1 text-sm text-stone-600">Manage campus access, roles, and account permissions.</p></div>
    <a class="design-primary !min-h-11 !px-7" href="{{ route('admin.users.create') }}" data-edit-dialog="create-account" aria-haspopup="dialog">+ Create account</a>
</div>
<div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
    @foreach (['Total accounts' => $counts->sum(), 'Customers' => $counts['customer'] ?? 0, 'Canteen staff' => $counts['staff'] ?? 0, 'Administrators' => $counts['admin'] ?? 0] as $label => $value)
        <div class="workspace-kpi"><p class="workspace-kpi-label">{{ $label }}</p><p class="workspace-kpi-value">{{ number_format($value) }}</p></div>
    @endforeach
</div>
<form action="{{ route('admin.users.index') }}" class="workspace-filter">
    <input name="search" type="search" class="field mt-0 max-w-sm" placeholder="Search name or email" aria-label="Search accounts" value="{{ request('search') }}">
    <select name="role" aria-label="Role" class="field mt-0 w-auto"><option value="">All roles</option>@foreach (['customer' => 'Customer', 'staff' => 'Staff', 'admin' => 'Admin'] as $role => $label)<option value="{{ $role }}" @selected(request('role') === $role)>{{ $label }}</option>@endforeach</select>
    <select name="active" aria-label="Account status" class="field mt-0 w-auto"><option value="">All statuses</option><option value="1" @selected(request('active') === '1')>Active</option><option value="0" @selected(request('active') === '0')>Disabled</option></select>
    <button class="button-secondary">Filter</button><p class="ml-auto text-xs text-stone-600">{{ number_format($users->total()) }} accounts</p>
</form>
<p class="mb-2 text-xs text-stone-600 md:hidden">Scroll the table sideways for more details and actions.</p>
<div class="overflow-x-auto rounded-2xl border border-maroon-100 bg-white" tabindex="0" role="region" aria-label="User accounts table">
<table class="workspace-table workspace-user-table">
    <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Status</th><th>Permissions</th><th>Action</th></tr></thead>
    <tbody>
    @forelse ($users as $user)
        <tr>
            <td><p class="font-semibold">{{ $user->name }}</p><p class="mt-1 text-[10px] text-stone-600">{{ match ($user->user_type) { 'staff' => 'Canteen operations', 'admin' => 'University administration', default => 'Customer account' } }}</p></td>
            <td class="text-stone-600">{{ $user->email }}</td>
            <td><span class="workspace-badge bg-stone-50 text-stone-600">{{ ucfirst($user->user_type) }}</span></td>
            <td><span @class(['workspace-badge', 'bg-status-ready-bg text-status-ready-text' => $user->is_active, 'bg-status-completed-bg text-status-completed-text' => ! $user->is_active])>{{ $user->is_active ? 'Active' : 'Disabled' }}</span></td>
            <td class="text-xs text-stone-600">{{ ! $user->is_active ? 'No access' : match ($user->user_type) { 'admin' => 'Full access', 'staff' => $user->stall?->name ?? 'No stall assigned', default => 'Order & track' } }}</td>
            <td><a class="workspace-action" href="{{ route('admin.users.edit', $user) }}" data-edit-dialog="edit-account-{{ $user->id }}" aria-haspopup="dialog" aria-label="Manage {{ $user->name }}">Manage <span aria-hidden="true">↗︎</span></a></td>
        </tr>
    @empty
        <tr><td colspan="6">No accounts match your filters.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@foreach ($users as $user)
    @include('admin.user-form', ['account' => $user])
@endforeach
@include('admin.user-create')
@if ($editingAccount && ! $users->getCollection()->contains($editingAccount))
    @include('admin.user-form', ['account' => $editingAccount])
@endif
<div class="mt-5">{{ $users->links() }}</div>
@endsection

