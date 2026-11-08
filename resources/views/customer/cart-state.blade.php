@php
    $cartPayload = [
        'items' => $cartLines->map(fn ($line) => [
            'id' => $line->item_id,
            'quantity' => $line->quantity,
            'instructions' => $line->instructions,
            'unit_cents' => (int) str_replace('.', '', $line->item?->price ?? '0.00'),
            'maximum' => min(999, $line->item?->is_available ? $line->item->stock_qty : 0),
        ])->values(),
        'fee_cents' => (int) round($cartFee * 100),
    ];
@endphp
<script type="application/json" data-cart-state data-cart-context="{{ $cartContext }}">@json($cartPayload)</script>


