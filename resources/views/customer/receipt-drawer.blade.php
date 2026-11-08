@if ($order->status === 'completed' && $order->receipt)
<dialog id="receipt-drawer" class="customer-receipt-dialog" aria-labelledby="receipt-drawer-title">
    <button class="dish-close" type="button" data-close-customer-dialog aria-label="Close receipt"><img src="{{ asset('images/icons/receipt-drawer-1.png') }}" alt="" aria-hidden="true" width="20" height="20" class="ui-icon object-contain " decoding="async"></button>
    <h2 id="receipt-drawer-title">Digital cash receipt</h2>
    <p class="text-sm">{{ $order->stall->name }} · {{ $order->displayReference() }}</p>
    <p class="mt-2 text-xs text-stone-600 break-all">{{ $order->receipt->receipt_number }} · {{ $order->receipt->issue_date->timezone(config('canteen.timezone'))->format('d M Y · g:i A') }}</p>
    <ul class="receipt-items">@foreach ($order->lines as $line)<li><span>{{ $line->quantity }} × {{ $line->item_name }}</span><strong>Php {{ number_format($line->quantity * $line->unit_price, 2) }}</strong></li>@endforeach<li><span>Delivery fee</span><strong>Php {{ number_format($order->delivery_fee, 2) }}</strong></li></ul>
    <p class="receipt-total"><span>Cash paid</span><strong>Php {{ number_format($order->receipt->total_paid, 2) }}</strong></p>
    <p class="mb-5 text-xs text-stone-600">Cash payment received. Thank you for ordering.</p>
    <a class="experience-button experience-button-outline w-full" href="{{ route('orders.receipt', $order->reference_number) }}">Open printable receipt</a>
</dialog>
@endif

