<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Stall;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CanteenDemoSeeder extends Seeder
{
    public const PASSWORD = 'CanteenDemo!2026';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Mock data must not be installed in production.');
        }
        if (User::exists() || Order::exists() || MenuItem::withTrashed()->exists()) {
            throw new RuntimeException('Mock data requires an empty application database. Back up existing data before resetting it.');
        }

        DB::transaction(function (): void {
            $password = Hash::make(self::PASSWORD);
            $admin = $this->account('University Demo Admin', 'admin@example.com', 'admin', $password);
            $customers = collect([
                ['Angel Cenabre', 'customer@example.com'],
                ['Miguel Reyes', 'teacher@example.com'],
                ['Aira Ramos', 'campus@example.com'],
            ])->map(fn (array $person): User => $this->account($person[0], $person[1], 'customer', $password));
            $disabled = $this->account('Disabled Demo Customer', 'disabled@example.com', 'customer', $password);
            $disabled->forceFill(['is_active' => false])->save();
            $categories = collect(['Meals', 'Beverages', 'Snacks', 'School Supplies'])
                ->mapWithKeys(fn (string $name): array => [$name => Category::create(['name' => $name])->id]);

            $catalog = [
                ['Chicken adobo + rice', 'Meals', '85.00', 'Rice meal · Regular'],
                ['Pancit canton', 'Meals', '45.00', 'Stir-fried noodles · Regular'],
                ['Lemon iced tea', 'Beverages', '20.00', '12 oz · Cold'],
                ['Crispy lumpia', 'Snacks', '25.00', '3 pieces · Regular'],
                ['Banana turon', 'Snacks', '20.00', '2 pieces · Freshly fried'],
                ['Notebook & pens', 'School Supplies', '35.00', '1 notebook + 2 pens'],
            ];
            $descriptions = ['Rice meals & snacks', 'Campus favorites', 'Drinks & desserts', 'Supplies & bites'];
            foreach ($descriptions as $index => $description) {
                $number = $index + 1;
                $stall = Stall::updateOrCreate(['id' => $number], [
                    'name' => 'Stall No. '.$number,
                    'location' => 'Main canteen · Counter '.$number,
                    'description' => $description,
                    'phone' => '0917000000'.$number,
                    'is_active' => $number !== 4,
                    'pickup_enabled' => true,
                    'delivery_enabled' => $number <= 2,
                    'delivery_fee' => '20.00',
                    'preparation_minutes' => 8 + $index * 2,
                    'weekly_hours' => null,
                    'opens_at' => null,
                    'closes_at' => null,
                ]);
                $staff = $this->account(['Jessa Santos', 'Mark Villanueva', 'Carlo Bautista', 'Janelle Cruz'][$index], 'staff'.$number.'@example.com', 'staff', $password, $stall->id);
                $items = collect($catalog)->map(function (array $entry) use ($stall, $categories): MenuItem {
                    return $stall->menuItems()->create([
                        'name' => $entry[0], 'category_id' => $categories[$entry[1]],
                        'price' => $entry[2], 'description' => $entry[3],
                        'stock_qty' => 100, 'is_available' => true,
                    ]);
                });
                for ($history = 0; $history < 12; $history++) {
                    $placed = $history < 4
                        ? now()->subMinutes(150 - $history * 30 + $index * 5)
                        : now()->subDays($history - 3)->subMinutes(30 + $index * 5);
                    $this->order($stall, $customers[$history % 3], $items->take(3)->all(), 'completed', $placed, $history % 2 === 1 && $stall->delivery_enabled);
                }
                foreach (['pending', 'preparing', 'ready'] as $stageIndex => $status) {
                    $this->order($stall, $customers[$stageIndex], $items->take(3)->all(), $status, now()->subMinutes(5 + $stageIndex * 4 + $index), $stageIndex === 1 && $stall->delivery_enabled);
                }
                $cancelled = $this->order($stall, $customers[$index % 3], [$items[0]], 'cancelled', now()->subDay(), false);
                $cancelled->forceFill(['cancelled_at' => $cancelled->order_date->copy()->addMinutes(2), 'cancelled_by' => $staff->id, 'cancellation_reason' => 'Demo: cancelled before preparation; no payment collected.'])->save();
                $items[4]->update(['stock_qty' => 3]);
                $items[5]->update(['stock_qty' => 0, 'is_available' => false]);

                if ($number <= 2) {
                    $cart = $customers[$index]->cart()->create(['stall_id' => $stall->id, 'fulfillment_method' => $number === 2 ? 'delivery' : 'pickup']);
                    foreach ($items->take(3) as $item) {
                        $cart->items()->create(['item_id' => $item->id, 'quantity' => 1]);
                    }
                }
            }
            $admin->salesReports()->create([
                'period_start' => now(config('canteen.timezone'))->subDays(8)->toDateString(),
                'period_end' => now(config('canteen.timezone'))->toDateString(),
                'total_orders' => Order::count(),
                'total_sales' => DB::table('digital_receipts')->sum('total_paid'),
            ]);
            DB::table('audit_events')->insert([
                'actor_id' => $admin->id, 'actor' => $admin->name,
                'event' => 'Mock dataset installed', 'target' => 'Demo workspace',
                'result' => 'success', 'details' => json_encode(['mock_data' => true], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        });
    }

    private function account(string $name, string $email, string $role, string $password, ?int $stallId = null): User
    {
        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['user_type' => $role, 'stall_id' => $stallId, 'is_active' => true, 'contact_phone' => '09171234567', 'email_verified_at' => now()])->save();

        return $user;
    }

    /** @param array<int, MenuItem> $items */
    private function order(Stall $stall, User $customer, array $items, string $status, Carbon $placed, bool $delivery): Order
    {
        $fee = $delivery ? 2000 : 0;
        $subtotal = array_sum(array_map(fn (MenuItem $item): int => (int) str_replace('.', '', $item->price), $items));
        $order = $customer->orders()->create([
            'stall_id' => $stall->id, 'order_date' => $placed, 'status' => $status,
            'total_amount' => number_format(($subtotal + $fee) / 100, 2, '.', ''),
            'fulfillment_method' => $delivery ? 'delivery' : 'pickup',
            'payment_method' => $delivery ? 'cash_on_delivery' : 'cash_at_pickup',
            'delivery_fee' => number_format($fee / 100, 2, '.', ''),
            'contact_name' => $customer->name, 'contact_phone' => $customer->contact_phone,
            'delivery_location' => $delivery ? (config('canteen.campus_delivery_locations')[0] ?? 'Main Library Entrance') : null,
            'notes' => 'Demo order: please pack sauce separately.',
            'created_at' => $placed, 'updated_at' => $placed,
        ]);
        foreach ($items as $item) {
            $order->lines()->create(['item_id' => $item->id, 'item_name' => $item->name, 'quantity' => 1, 'unit_price' => $item->price]);
            if ($status !== 'cancelled') {
                $item->decrement('stock_qty');
            }
        }
        if ($status === 'completed') {
            $issued = $placed->copy()->addMinutes(20);
            $order->receipt()->create(['receipt_number' => 'DEMO-RC-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT), 'issue_date' => $issued, 'total_paid' => $order->total_amount]);
        }
        $message = match ($status) {
            'pending' => 'Order '.$order->displayReference().' was received by '.$stall->name.'. Your meal will be prepared shortly.',
            'preparing' => 'The kitchen is preparing order '.$order->displayReference().'.',
            'ready' => 'Order '.$order->displayReference().' is ready. Pay in cash at the canteen when you pick it up.',
            'completed' => 'Order '.$order->displayReference().' is completed. Your digital cash receipt is now available.',
            default => 'Order '.$order->displayReference().' was cancelled. No payment was collected.',
        };
        $customer->pickupNotifications()->create(['order_id' => $order->id, 'message' => $message, 'is_read' => in_array($status, ['completed', 'cancelled'], true), 'created_at' => $placed->copy()->addMinutes($status === 'pending' ? 0 : 2)]);

        return $order;
    }
}
