<?php

namespace App\Models;

use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'order_id', 'message', 'is_read'])]
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    protected $attributes = [
        'is_read' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function heading(): string
    {
        return match (true) {
            str_contains($this->message, 'was received') => 'Order received',
            str_contains($this->message, 'preparing') => 'Preparing your order',
            str_contains($this->message, 'completed') => 'Order completed',
            str_contains($this->message, 'cancelled') => 'Order cancelled',
            str_contains($this->message, 'ready for campus delivery') => 'Order ready for delivery',
            str_contains($this->message, 'is ready') => 'Order ready for pickup',
            default => 'Order update',
        };
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function readyOrderReference(): ?string
    {
        return preg_match('/\AOrder ([0-9a-z]{26}) is ready\b/i', $this->message, $matches)
            ? strtolower($matches[1])
            : null;
    }
}
