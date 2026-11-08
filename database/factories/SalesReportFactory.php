<?php

namespace Database\Factories;

use App\Models\SalesReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesReport>
 */
class SalesReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => User::factory()->state(['user_type' => 'admin']),
            'period_start' => today(),
            'period_end' => today(),
            'total_orders' => 1,
            'total_sales' => '65.00',
        ];
    }
}
