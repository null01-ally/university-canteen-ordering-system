<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderLine>
 */
class OrderLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'item_id' => MenuItem::factory(),
            'item_name' => fn (array $attributes): string => MenuItem::withTrashed()->findOrFail($attributes['item_id'])->name,
            'quantity' => 1,
            'unit_price' => fn (array $attributes): string => MenuItem::withTrashed()->findOrFail($attributes['item_id'])->price,
        ];
    }
}
