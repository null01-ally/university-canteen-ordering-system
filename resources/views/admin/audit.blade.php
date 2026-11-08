@extends('layout')
@section('title', 'System audit log')
@section('content')
@php
    $eventLabels = [
        'admin.users.store' => 'Account created', 'admin.users.update' => 'Account updated',
        'admin.users.password-reset' => 'Password reset link requested', 'admin.stalls.store' => 'Stall created',
        'admin.stalls.update' => 'Stall updated', 'admin.stalls.reassign' => 'Stall staff reassigned',
        'admin.stalls.delivery' => 'Stall delivery updated', 'admin.stalls.destroy' => 'Empty stall deleted',
        'admin.sales.store' => 'Sales report saved', 'admin.sales.export' => 'Sales report exported',
        'admin.reports.index' => 'Sales report exported', 'admin.audit.index' => 'Audit log exported',
        'staff.stalls.update' => 'Stall settings updated', 'staff.delivery.update' => 'Delivery service updated',
        'staff.menu.store' => 'Menu item created', 'staff.menu.update' => 'Menu item updated',
        'staff.menu.availability' => 'Menu availability updated', 'staff.menu.destroy' => 'Menu item removed',
        'staff.profile.update' => 'Staff profile updated', 'staff.orders.update' => 'Order status updated',
        'staff.orders.cancel' => 'Order cancelled',
    ];
@endphp
<div class="workspace-page-title"><div><h1 class="workspace-heading">System audit log</h1><p>Monitor security events, administrative changes, and account activity.</p></div><a class="button-secondary" href="{{ route('admin.audit.index', array_merge(request()->except('page'), ['export' => 1])) }}">Export log</a></div>
<form class="workspace-card mb-6" action="{{ route('admin.audit.index') }}">
    <div class="grid items-end gap-4 md:grid-cols-2 xl:grid-cols-[1.7fr_1fr_1fr_1fr_auto]">
        <label class="field-label">Search activity<input class="field" type="search" name="search" placeholder="Search actor, event, or reference" value="{{ request('search') }}" maxlength="100"></label>
        <label class="field-label">Event type<select class="field" name="event"><option value="">All events</option>@foreach ($eventTypes as $event)<option value="{{ $event }}" @selected(request('event') === $event)>{{ $eventLabels[$event] ?? $event }}</option>@endforeach</select></label>
        <label class="field-label">Date<input class="field" type="date" name="date" value="{{ $date }}"></label>
        <label class="field-label">Result<select class="field" name="result"><option value="">All results</option><option value="success" @selected(request('result') === 'success')>Success</option><option value="failed" @selected(request('result') === 'failed')>Failed</option></select></label>
        <button class="button-primary !min-w-0 !px-5">Apply</button>
    </div>
</form>
<div class="mb-6 grid gap-5 sm:grid-cols-3">
    @foreach (['Events on selected day' => [$stats->total, 'Across all users and stalls'], 'Failed sign-ins' => [$stats->failures ?? 0, 'Review unusual login attempts'], 'Administrative changes' => [$stats->changes ?? 0, 'Roles, accounts, and configurations']] as $label => [$value, $hint])<div class="workspace-kpi"><p class="workspace-kpi-label">{{ $label }}</p><div class="mt-1 flex flex-wrap items-baseline gap-6"><strong class="workspace-kpi-value">{{ $value }}</strong><span class="text-xs text-stone-600">{{ $hint }}</span></div></div>@endforeach
</div>
<section class="workspace-card">
    <h2 class="workspace-section-title">Recent activity</h2><p class="mt-1 mb-4 text-xs text-stone-600">{{ $date }} · Asia/Manila · Read-only event records</p>
    <div class="overflow-x-auto"><table class="workspace-table audit-table"><thead><tr><th>Time</th><th>Actor</th><th>Event</th><th>Target</th><th>Result</th><th><span class="sr-only">Details</span></th></tr></thead><tbody>
        @forelse ($events as $event)<tr><td class="whitespace-nowrap text-stone-600">{{ \Illuminate\Support\Carbon::parse($event->created_at, 'UTC')->timezone(config('canteen.timezone'))->format('g:i:s A') }}</td><td>{{ $event->actor }}</td><td>{{ $eventLabels[$event->event] ?? $event->event }}</td><td class="max-w-56 break-words">{{ $event->target }}</td><td><span @class(['workspace-badge', 'bg-status-ready-bg text-status-ready-text' => $event->result === 'success', 'bg-status-pending-bg text-status-pending-text' => $event->result !== 'success'])>{{ ucfirst($event->result) }}</span></td><td><button class="workspace-action" data-edit-dialog="audit-{{ $event->id }}" aria-haspopup="dialog">Details →</button></td></tr>@empty<tr><td colspan="6">No activity recorded for these filters. New actions will appear here.</td></tr>@endforelse
    </tbody></table></div><div class="mt-5">{{ $events->links() }}</div>
</section>
@foreach ($events as $event)<dialog id="audit-{{ $event->id }}" class="edit-dialog" aria-labelledby="audit-title-{{ $event->id }}"><div class="flex items-start justify-between gap-4"><h2 id="audit-title-{{ $event->id }}" class="workspace-section-title">Activity details</h2><button class="menu-icon" data-close-edit-dialog aria-label="Close details">×</button></div><dl class="mt-5 space-y-3 text-sm"><div><dt class="font-semibold">Actor</dt><dd>{{ $event->actor }}</dd></div><div><dt class="font-semibold">Event</dt><dd>{{ $eventLabels[$event->event] ?? $event->event }}</dd></div><div><dt class="font-semibold">Recorded at (Asia/Manila)</dt><dd>{{ \Illuminate\Support\Carbon::parse($event->created_at, 'UTC')->timezone(config('canteen.timezone'))->toDateTimeString() }}</dd></div></dl><pre class="mt-5 overflow-auto rounded-xl bg-stone-50 p-4 text-xs">{{ json_encode(json_decode($event->details), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></dialog>@endforeach
<p class="mt-5 text-xs text-stone-600">Audit entries cannot be edited. Open Details to review the actor, timestamp, and recorded changes.</p>
@endsection


