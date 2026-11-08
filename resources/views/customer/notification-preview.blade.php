<section aria-label="Order notifications" class="space-y-3">
    @foreach ($notifications as $notification)
        <article class="rounded-xl bg-maroon-50 p-3">
            <h2 class="text-xs font-semibold text-maroon-800">{{ $notification->heading() }}</h2>
            <p class="mt-1 text-xs leading-5 wrap-anywhere text-stone-700">{{ $notification->message }}</p>
            <p class="mt-2 text-[11px] text-stone-600">{{ $notification->created_at->timezone(config('canteen.timezone'))->format('j M · g:i A') }}</p>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3 text-xs">
                @if ($notification->order && $notification->order->customer_id === auth()->id())
                    <a class="text-link" href="{{ $notification->order->status === 'completed' && $notification->order->receipt ? route('orders.receipt', $notification->order->reference_number) : route('orders.show', $notification->order->reference_number) }}">{{ $notification->order->status === 'completed' && $notification->order->receipt ? 'View receipt' : 'View order' }} →</a>
                @endif
                <form data-async-form method="post" action="{{ route('notifications.read', $notification) }}">@csrf @method('PATCH')<button class="text-link min-h-10">Mark as read</button></form>
            </div>
        </article>
    @endforeach
</section>

