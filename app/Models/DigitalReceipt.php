<?php

namespace App\Models;

use Database\Factories\DigitalReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'receipt_number', 'issue_date', 'total_paid'])]
class DigitalReceipt extends Model
{
    /** @use HasFactory<DigitalReceiptFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'issue_date' => 'datetime',
            'total_paid' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
