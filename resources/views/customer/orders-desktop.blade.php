<div class="customer-desktop-only desktop-orders">
    <div class="desktop-page-heading"><div><h1>My Orders &amp; notifications</h1><p>Your campus orders and the latest updates, all in one place.</p></div><p class="text-xs">{{ now(config('canteen.timezone'))->format('j F Y') }}</p></div>
    <div class="desktop-orders-grid">
        <div>
            @unless (request()->boolean('history'))
            @foreach ($activeOrders as $active)
                <section class="desktop-active-order">
                    <div class="desktop-active-copy"><span class="desktop-active-icon" aria-hidden="true"><img src="{{ asset('images/icons/orders-desktop-1.png') }}" alt="" aria-hidden="true" width="20" height="20" class="ui-icon object-contain size-6" decoding="async"></span><div><small>ACTIVE ORDER</small><h2>{{ $active->displayReference() }} {{ $active->status === 'ready' ? 'is ready for '.($active->fulfillment_method === 'pickup' ? 'pickup' : 'delivery') : 'is '.strtolower(\App\Models\Order::STAGES[$active->status]) }}</h2><p>{{ $active->stall->name }} · {{ $active->lines_sum_quantity ?? 0 }} items · Php {{ number_format($active->total_amount, 2) }} · Payment due {{ $active->fulfillment_method === 'delivery' ? 'on delivery' : 'at pickup' }}</p></div></div><a class="desktop-maroon-button" href="{{ route('orders.show', $active->reference_number) }}">Track order</a>
                </section>
            @endforeach
            @if ($activeOrders->hasPages())<div class="my-4">{{ $activeOrders->links() }}</div>@endif
            @endunless
            <section class="desktop-card desktop-past-orders">
                <div class="flex flex-wrap items-center justify-between gap-4"><h2>Past orders</h2><form action="{{ route('orders.index') }}" class="flex items-center gap-2">@if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif<label class="sr-only" for="orders-month">Order month</label><input class="desktop-month-field" id="orders-month" name="month" type="month" value="{{ request('month') }}" data-auto-submit><button class="text-xs text-maroon-800">Apply</button></form></div>
                <nav class="my-4 flex gap-2" aria-label="Past order filters">@foreach (['' => 'All orders', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)<a class="desktop-filter-pill {{ request('status', '') === $value ? 'is-selected' : '' }}" href="{{ route('orders.index', array_filter(['status' => $value, 'month' => request('month')])) }}">{{ $label }}</a>@endforeach</nav>
                <div class="overflow-x-auto"><table class="desktop-order-table"><thead><tr><th>Order</th><th>Date &amp; time</th><th>Stall / method</th><th>Total</th><th>Status</th><th><span class="sr-only">View</span></th></tr></thead><tbody>
                    @forelse ($pastOrders as $past)<tr><td><strong>{{ $past->displayReference() }}</strong><small>{{ $past->status === 'cancelled' ? 'No payment collected' : ($past->lines_sum_quantity ?? 0).' items · Cash at '.($past->fulfillment_method === 'pickup' ? 'stall' : 'delivery') }}</small></td><td>{{ $past->order_date->timezone(config('canteen.timezone'))->format('d M Y') }}<small>{{ $past->order_date->timezone(config('canteen.timezone'))->format('g:i A') }}</small></td><td>{{ $past->stall->name }}<small>{{ ucfirst($past->fulfillment_method) }}</small></td><td class="font-semibold text-maroon-800">Php {{ number_format($past->total_amount, 2) }}</td><td>@include('order-status', ['status' => $past->status])</td><td><a class="desktop-outline-button !min-h-9 !px-5" href="{{ $past->receipt ? route('orders.receipt', $past->reference_number) : route('orders.show', $past->reference_number) }}">View</a></td></tr>@empty<tr><td colspan="6" class="text-stone-600">No past orders match these filters.</td></tr>@endforelse
                </tbody></table></div><div class="mt-4 flex justify-between text-[11px] text-stone-600"><span>Showing {{ $pastOrders->count() }} past orders · Active orders appear above</span><span>Page {{ $pastOrders->currentPage() }} of {{ $pastOrders->lastPage() }}</span></div>@if ($pastOrders->hasPages())<div class="mt-4">{{ $pastOrders->links() }}</div>@endif
            </section>
        </div>
        <aside class="desktop-card desktop-order-notifications">
            <div class="flex justify-between gap-3"><h2>Notifications</h2><span class="desktop-small-badge">{{ $unreadCount }} unread</span></div>
            <div class="my-4 flex justify-between gap-3"><a class="text-xs text-maroon-800" href="{{ route('orders.index', request()->boolean('unread') ? [] : ['unread' => 1]) }}">{{ request()->boolean('unread') ? 'Show all updates' : 'Unread updates' }} ⌄</a><form method="post" action="{{ route('notifications.read-all') }}" data-async-form>@csrf @method('PATCH')<button class="text-[11px] text-maroon-800" @disabled($unreadCount === 0)>Mark all as read</button></form></div>
            <div class="border-t border-slate-200 pt-4">
                @foreach ($recentUpdates->groupBy(fn ($update) => $update->created_at->timezone(config('canteen.timezone'))->isToday() ? 'Today · '.now(config('canteen.timezone'))->format('j F') : 'Earlier') as $group => $updates)
                    <h3 class="mb-3 mt-1 text-[10px] font-medium uppercase tracking-wide text-stone-600">{{ $group }}</h3>
                    @foreach ($updates as $update)<article @class(['desktop-update', 'is-unread' => ! $update->is_read])><div class="flex justify-between gap-3"><h4>{{ $update->heading() }}</h4>@if (! $update->is_read)<span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-gold-400" aria-label="Unread"></span>@endif</div><p>{{ $update->message }}</p><div class="mt-2 flex items-center justify-between gap-3"><small>{{ $update->created_at->timezone(config('canteen.timezone'))->format('j M · g:i A') }} · {{ $update->is_read ? 'Read' : 'Unread' }}</small><a href="{{ $update->order && $update->order->customer_id === auth()->id() ? route('orders.show', $update->order->reference_number) : route('orders.index', ['history' => 1]) }}">View order →</a></div></article>@endforeach
                @endforeach
                @if ($recentUpdates->isEmpty())<p class="text-sm text-stone-600">You are all caught up.</p>@endif
            </div><a class="mt-1 inline-block text-xs text-maroon-800" href="{{ route('notifications.index') }}">All notifications →</a>
        </aside>
    </div>
</div>


