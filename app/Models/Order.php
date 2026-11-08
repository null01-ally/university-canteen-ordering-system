<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'customer_id', 'stall_id', 'order_date', 'status', 'total_amount', 'fulfillment_method',
    'contact_name', 'contact_phone', 'delivery_location', 'notes', 'delivery_fee', 'payment_method',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasUlids;

    public const STAGES = [
        'pending' => 'Pending',
        'preparing' => 'Preparing',
        'ready' => 'Ready',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $attributes = [
        'status' => 'pending',
        'stall_id' => Stall::DEFAULT_ID,
        'fulfillment_method' => 'pickup',
        'delivery_fee' => 0,
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'datetime',
            'total_amount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['reference_number'];
    }

    public function displayReference(): string
    {
        return '#BB-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function nextStatus(): ?string
    {
        return match ($this->status) {
            'pending' => 'preparing',
            'preparing' => 'ready',
            'ready' => 'completed',
            default => null,
        };
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function stall(): BelongsTo
    {
        return $this->belongsTo(Stall::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(DigitalReceipt::class);
    }
}
