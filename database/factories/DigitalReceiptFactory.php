<?php

namespace Database\Factories;

use App\Models\DigitalReceipt;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DigitalReceipt>
 */
class DigitalReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->state(['status' => 'completed']),
            'receipt_number' => (string) Str::ulid(),
            'issue_date' => now(),
            'total_paid' => fn (array $attributes): string => Order::findOrFail($attributes['order_id'])->total_amount,
        ];
    }
}
