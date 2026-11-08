@extends('layout')
@section('title', 'Canteen Stall Directory')
@section('content')
<div class="workspace-page-title stall-directory-heading">
    <div><h1 class="workspace-heading">Canteen Stall Directory</h1><p>Manage campus stalls, staff assignments, and delivery availability.</p></div>
    <button class="button-gold" type="button" data-edit-dialog="register-stall" aria-haspopup="dialog">+ Register New Stall</button>
</div>
<form action="{{ route('admin.stalls.index') }}" class="workspace-filter stall-directory-filters">
    <label class="sr-only" for="stall-search">Search stalls</label>
    <input id="stall-search" name="search" type="search" class="field" placeholder="Search stall name, ID, or assigned staff" value="{{ request('search') }}" maxlength="100">
    <select class="field w-auto" name="status" aria-label="Stall status" data-auto-submit><option value="">All statuses</option><option value="open" @selected(request('status') === 'open')>Open</option><option value="closed" @selected(request('status') === 'closed')>Closed</option></select>
    <button class="button-secondary stall-search-button sr-only focus:not-sr-only">Search</button>
    <p class="ml-auto text-xs text-stone-600">{{ $totalStalls }} stalls · {{ $openStalls }} open · {{ $deliveryStalls }} delivery active</p>
</form>
<div class="overflow-x-auto rounded-2xl border border-maroon-100 bg-white" tabindex="0" role="region" aria-label="Canteen stall directory">
    <table class="workspace-table stall-directory-table">
        <thead><tr><th>Stall ID</th><th>Stall name</th><th>Assigned staff account</th><th>Status</th><th>Delivery active</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse ($stalls as $stall)
            <tr>
                <td class="text-stone-600">ST-{{ str_pad($stall->id, 3, '0', STR_PAD_LEFT) }}</td>
                <td><p class="font-semibold">{{ $stall->name }}</p>@if ($stall->description)<p class="mt-1 max-w-56 text-[10px] text-stone-600">{{ $stall->description }}</p>@endif</td>
                <td>@forelse ($stall->staff as $staff)<div class="mb-1 last:mb-0"><p class="font-semibold">{{ $staff->name }}</p><p class="mt-1 text-[10px] text-stone-600">{{ $staff->email }}</p></div>@empty<p class="font-semibold">Unassigned</p><p class="mt-1 text-[10px] text-stone-600">Assign a staff account</p>@endforelse</td>
                <td><span @class(['workspace-badge', 'bg-status-ready-bg text-status-ready-text' => $stall->isTakingOrders(), 'bg-status-completed-bg text-status-completed-text' => ! $stall->isTakingOrders()])>{{ $stall->isTakingOrders() ? 'Open' : 'Closed' }}</span></td>
                <td><form method="post" action="{{ route('admin.stalls.delivery', $stall) }}" data-async-form>@csrf @method('PATCH')<button class="design-switch {{ $stall->delivery_enabled ? 'design-switch-on' : '' }}" name="delivery_enabled" value="{{ (int) ! $stall->delivery_enabled }}" role="switch" aria-checked="{{ $stall->delivery_enabled ? 'true' : 'false' }}" aria-label="Delivery for {{ $stall->name }}"><span></span></button></form></td>
                <td><div class="flex items-center gap-3 whitespace-nowrap"><a class="stall-row-action" href="{{ route('admin.stalls.configuration', $stall) }}">Settings</a><button class="stall-row-action" data-edit-dialog="edit-stall-{{ $stall->id }}">Edit</button><button class="stall-row-action" data-edit-dialog="reassign-stall-{{ $stall->id }}">Reassign</button><button class="stall-row-action !text-red-700" data-edit-dialog="delete-stall-{{ $stall->id }}">Delete</button></div></td>
            </tr>
        @empty<tr><td colspan="6">No stalls match your filters.</td></tr>@endforelse
        </tbody>
    </table>
</div>
<dialog id="register-stall" class="edit-dialog stall-dialog" aria-labelledby="register-stall-title" @if (old('stall_dialog') === 'register-stall') data-open-edit-dialog @endif>
    <div class="flex items-start justify-between gap-4"><div><h2 id="register-stall-title" class="text-xl font-semibold text-maroon-800">Register new canteen stall</h2><p class="mt-1 text-xs text-stone-600">Add stall details and assign an existing staff user account.</p></div><button class="stall-modal-close" type="button" data-close-edit-dialog aria-label="Close registration">×</button></div>
    <form action="{{ route('admin.stalls.store') }}" method="post" class="mt-4">
        @include('admin.stall-errors', ['dialogId' => 'register-stall'])
        @csrf
        <div class="grid gap-4 sm:grid-cols-2"><label class="field-label">Stall ID · Auto-generated<input class="field" value="Assigned on registration" readonly aria-readonly="true"></label><label class="field-label">Stall name *<input class="field" name="name" value="{{ old('stall_dialog') === 'register-stall' ? old('name') : '' }}" placeholder="Stall No. 5" maxlength="255" required></label></div>
        <div class="mt-4">@include('admin.stall-staff-select', ['currentStall' => null, 'dialogId' => 'register-stall'])</div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-4"><p class="text-xs text-stone-600">Initial status: Closed until staff opens the stall</p><label class="flex items-center gap-3 text-xs">Delivery active<input type="hidden" name="delivery_enabled" value="0"><input class="peer sr-only" name="delivery_enabled" type="checkbox" role="switch" value="1" @checked(old('stall_dialog') !== 'register-stall' || old('delivery_enabled'))><span class="design-switch peer-checked:justify-end peer-checked:bg-maroon-800 peer-focus-visible:outline-2 peer-focus-visible:outline-maroon-800" aria-hidden="true"><span></span></span></label></div>
        <div class="mt-4 flex justify-end gap-3"><button class="button-secondary" type="button" data-close-edit-dialog>Cancel</button><button class="button-gold">Register Stall</button></div>
    </form>
</dialog>
@foreach ($stalls as $stall)
    @php($editId = 'edit-stall-'.$stall->id)
    <dialog id="{{ $editId }}" class="edit-dialog stall-dialog" aria-labelledby="{{ $editId }}-title" @if (old('stall_dialog') === $editId) data-open-edit-dialog @endif>
        <div class="flex justify-between gap-4"><h2 id="{{ $editId }}-title" class="text-xl font-semibold text-maroon-800">Edit canteen stall</h2><button class="stall-modal-close" data-close-edit-dialog aria-label="Close edit">×</button></div>
        <form action="{{ route('admin.stalls.update', $stall) }}" method="post" class="mt-5 space-y-4">@csrf @method('PATCH')
            @include('admin.stall-errors', ['dialogId' => $editId])
            <label class="field-label">Stall name<input class="field" name="name" value="{{ old('stall_dialog') === $editId ? old('name', $stall->name) : $stall->name }}" maxlength="255" required></label>
            <label class="field-label">Description<textarea class="field" name="description" maxlength="1000" rows="2">{{ old('stall_dialog') === $editId ? old('description', $stall->description) : $stall->description }}</textarea></label>
            <label class="field-label">Ordering status<select class="field" name="is_active"><option value="1" @selected((int) (old('stall_dialog') === $editId ? old('is_active', $stall->is_active) : $stall->is_active) === 1)>Open (within operating hours)</option><option value="0" @selected((int) (old('stall_dialog') === $editId ? old('is_active', $stall->is_active) : $stall->is_active) === 0)>Closed</option></select></label>
            <div class="flex justify-end gap-3"><button class="button-secondary" type="button" data-close-edit-dialog>Cancel</button><button class="button-gold">Save changes</button></div>
        </form>
    </dialog>
    @php($reassignId = 'reassign-stall-'.$stall->id)
    <dialog id="{{ $reassignId }}" class="edit-dialog stall-dialog" aria-labelledby="{{ $reassignId }}-title" @if (old('stall_dialog') === $reassignId) data-open-edit-dialog @endif>
        <div class="flex justify-between gap-4"><h2 id="{{ $reassignId }}-title" class="text-xl font-semibold text-maroon-800">Reassign staff · {{ $stall->name }}</h2><button class="stall-modal-close" data-close-edit-dialog aria-label="Close reassignment">×</button></div>
        <form action="{{ route('admin.stalls.reassign', $stall) }}" method="post" class="mt-5">@csrf @method('PATCH')
            @include('admin.stall-errors', ['dialogId' => $reassignId])
            @include('admin.stall-staff-select', ['currentStall' => $stall, 'dialogId' => $reassignId])
            <p class="mt-4 text-xs text-stone-600">The selected account moves from its current stall. Other staff assigned to this stall will be unassigned and must receive a new assignment to use their workspace.</p>
            <div class="mt-5 flex justify-end gap-3"><button class="button-secondary" type="button" data-close-edit-dialog>Cancel</button><button class="button-gold">Reassign staff</button></div>
        </form>
    </dialog>
    <dialog id="delete-stall-{{ $stall->id }}" class="edit-dialog stall-dialog" aria-labelledby="delete-stall-title-{{ $stall->id }}"><h2 id="delete-stall-title-{{ $stall->id }}" class="text-xl font-semibold text-maroon-800">Delete {{ $stall->name }}?</h2><p class="mt-3 text-sm text-stone-600">Only empty, unassigned stalls can be deleted. Stalls with accounts, menus, carts, or order history must be closed using Edit.</p><form method="post" action="{{ route('admin.stalls.destroy', $stall) }}" class="mt-5 flex justify-end gap-3">@csrf @method('DELETE')<button class="button-secondary" type="button" data-close-edit-dialog>Cancel</button><button class="button-primary !bg-red-700">Delete stall</button></form></dialog>
@endforeach
@endsection


