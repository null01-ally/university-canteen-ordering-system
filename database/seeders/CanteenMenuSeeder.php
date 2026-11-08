<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Stall;
use Illuminate\Database\Seeder;

class CanteenMenuSeeder extends Seeder
{
    public function run(): void
    {
        $stall = Stall::findOrFail(Stall::DEFAULT_ID);
        $meals = Category::firstOrCreate(['name' => 'Rice Meals']);
        $drinks = Category::firstOrCreate(['name' => 'Drinks']);

        $stall->menuItems()->withTrashed()->firstOrCreate(
            ['category_id' => $meals->id, 'name' => 'Chicken Adobo Rice Bowl'],
            ['description' => 'Chicken adobo served with steamed rice.', 'price' => '65.00', 'stock_qty' => 20],
        );

        $stall->menuItems()->withTrashed()->firstOrCreate(
            ['category_id' => $drinks->id, 'name' => 'Calamansi Juice'],
            ['description' => 'Chilled calamansi juice.', 'price' => '25.00', 'stock_qty' => 30],
        );
    }
}
