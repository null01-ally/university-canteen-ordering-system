@if ($notifications->isNotEmpty())
    <section aria-label="Pickup notifications" class="mb-6 space-y-2">
        @foreach ($notifications as $notification)
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 rounded-2xl border border-gold-400/60 bg-gold-50 px-4 py-3">
                <p class="min-w-0 flex-1 basis-48 text-sm wrap-anywhere text-stone-800">{{ $notification->message }}</p>
                <form data-async-form method="post" action="{{ route('notifications.read', $notification) }}">
                    @csrf
                    @method('PATCH')
                    <button class="text-link min-h-10 text-sm">Mark as read</button>
                </form>
            </div>
        @endforeach
    </section>
@endif

