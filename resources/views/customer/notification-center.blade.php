@extends('layout')
@section('title', 'Notifications')
@section('compact-layout', 'true')
@section('live-page', 'true')
@section('main-class', 'customer-flow customer-notifications')
@section('content')
@include('mobile-heading', ['back' => route('stalls.index'), 'heading' => 'Notifications'])
<div class="mb-4 flex items-center justify-between gap-3"><p class="text-xs text-stone-600">{{ $unreadCount }} unread updates</p><form method="post" action="{{ route('notifications.read-all') }}" data-async-form>@csrf @method('PATCH')<button class="text-xs font-semibold text-maroon-800" @disabled($unreadCount === 0)>Mark all as read</button></form></div>
<nav class="mb-6 grid grid-cols-2 gap-3" aria-label="Notification filters"><a href="{{ route('notifications.index') }}" @class(['menu-pill !min-h-9', 'menu-pill-active' => ! request()->boolean('unread')])>All notifications</a><a href="{{ route('notifications.index', ['unread' => 1]) }}" @class(['menu-pill !min-h-9', 'menu-pill-active' => request()->boolean('unread')])>Unread ({{ $unreadCount }})</a></nav>
@foreach ($updates->getCollection()->groupBy(fn ($update) => $update->created_at->timezone(config('canteen.timezone'))->isToday() ? 'Today · '.now(config('canteen.timezone'))->format('j F') : 'Earlier') as $group => $items)
    <h2 class="mt-3 mb-2 text-[11px] font-semibold uppercase text-stone-600">{{ $group }}</h2>
    <div class="space-y-3">
        @foreach ($items as $update)
            <article @class(['rounded-2xl border border-maroon-100 px-4 py-3', 'bg-maroon-50' => ! $update->is_read, 'bg-white' => $update->is_read])>
                <div class="flex justify-between gap-3"><h3 @class(['text-sm font-semibold', 'text-maroon-800' => ! $update->is_read])>{{ $update->heading() }}</h3>@if (! $update->is_read)<span class="mt-1.5 size-1.5 rounded-full bg-maroon-800" aria-label="Unread"></span>@endif</div>
                <p class="mt-1 text-xs leading-[18px] text-stone-600">{{ $update->message }}</p>
                <div class="mt-5 flex items-center justify-between gap-2 text-[11px]"><span class="text-stone-600">{{ $update->created_at->timezone(config('canteen.timezone'))->format($group === 'Earlier' ? 'j M · g:i A' : 'g:i A') }} · {{ $update->is_read ? 'Read' : 'Unread' }}</span>
                    @if ($update->order && $update->order->customer_id === auth()->id())<a class="font-semibold text-maroon-800" href="{{ str_contains($update->message, 'completed') && $update->order->receipt ? route('orders.receipt', $update->order->reference_number) : route('orders.show', $update->order->reference_number) }}">{{ str_contains($update->message, 'completed') && $update->order->receipt ? 'View receipt' : 'View order' }} →</a>@else<a class="font-semibold text-maroon-800" href="{{ route('orders.index', ['history' => 1]) }}">View past orders →</a>@endif
                </div>
                @if (! $update->is_read)<form method="post" action="{{ route('notifications.read', $update) }}" data-async-form class="mt-2">@csrf @method('PATCH')<button class="text-[11px] text-maroon-800 underline underline-offset-2">Mark as read</button></form>@endif
            </article>
        @endforeach
    </div>
@endforeach
@if ($updates->isEmpty())<p class="rounded-2xl border border-maroon-100 bg-white p-5 text-sm text-stone-600">You’re all caught up. New order updates will appear here.</p>@endif
<div class="mt-4">{{ $updates->links() }}</div>
@endsection

