<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DigitalReceipt;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerExperienceTest extends TestCase
{
    public function test_menu_images_support_storage_urls_and_safe_branded_fallbacks(): void
    {
        $item = MenuItem::factory()->make(['image_path' => 'menu/lunch.jpg']);
        $this->assertSame(Storage::disk('public')->url('menu/lunch.jpg'), $item->imageUrl());
        $item->image_path = 'https://images.example.com/lunch.jpg';
        $this->assertSame('https://images.example.com/lunch.jpg', $item->imageUrl());
        foreach ([null, '', 'javascript:alert(1)', '../private.jpg', '//example.com/image.jpg'] as $path) {
            $item->image_path = $path;
            $this->assertSame(asset('images/icons/menu-placeholder.png'), $item->imageUrl());
        }
    }

    use RefreshDatabase;

    public function test_item_instructions_survive_quantity_changes_checkout_and_staff_rendering(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $item = MenuItem::factory()->create(['stock_qty' => 10]);
        $this->actingAs($customer)->putJson(route('cart.quantity', $item), ['quantity' => 2, 'context' => 'menu', 'instructions' => 'Extra sauce, please.'])
            ->assertOk()->assertJsonPath('cart.items.0.instructions', 'Extra sauce, please.');
        $this->putJson(route('cart.quantity', $item), ['quantity' => 3, 'context' => 'menu'])->assertOk()->assertJsonPath('cart.items.0.instructions', 'Extra sauce, please.');
        $review = $this->get(route('checkout.index'))->assertOk()->assertSee('Extra sauce, please.');
        $this->post(route('orders.store'), ['snapshot' => $review->viewData('snapshot')])->assertSessionHasNoErrors();
        $order = $customer->orders()->sole();
        $this->assertSame('Extra sauce, please.', $order->lines()->sole()->instructions);
        $this->actingAs(User::factory()->staff()->create())->get(route('staff.orders.index'))->assertOk()->assertSee('Extra sauce, please.');
    }

    public function test_instruction_validation_and_checkout_snapshots_prevent_silent_changes(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $item = MenuItem::factory()->create(['stock_qty' => 5]);
        $this->actingAs($customer)->putJson(route('cart.quantity', $item), ['quantity' => 1, 'context' => 'menu', 'instructions' => str_repeat('a', 501)])->assertUnprocessable();
        $this->putJson(route('cart.quantity', $item), ['quantity' => 1, 'context' => 'menu', 'instructions' => 'No ice'])->assertOk();
        $snapshot = $this->get(route('checkout.index'))->viewData('snapshot');
        $this->putJson(route('cart.quantity', $item), ['quantity' => 1, 'context' => 'menu', 'instructions' => 'With ice'])->assertOk();
        $this->post(route('orders.store'), ['snapshot' => $snapshot])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_completed_orders_can_be_reordered_at_current_prices_without_reserving_stock(): void
    {
        $customer = User::factory()->create();
        $item = MenuItem::factory()->create(['price' => '90.00', 'stock_qty' => 5]);
        $order = Order::factory()->for($customer, 'customer')->create(['status' => 'completed']);
        OrderLine::factory()->for($order)->for($item, 'item')->create(['quantity' => 2, 'unit_price' => '65.00', 'instructions' => 'No chili']);
        $this->actingAs($customer)->post(route('orders.again', $order->reference_number))->assertRedirect(route('cart.index'))->assertSessionHasNoErrors();
        $line = $customer->cart()->sole()->items()->sole();
        $this->assertSame(2, $line->quantity);
        $this->assertSame('No chili', $line->instructions);
        $this->assertSame(18000, $line->subtotalInCents());
        $this->assertSame(5, $item->fresh()->stock_qty);
        $this->assertDatabaseCount('orders', 1);
        $this->post(route('orders.again', $order->reference_number))->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_reorder_is_scoped_and_rolls_back_all_items_if_one_is_unavailable(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer, 'customer')->create(['status' => 'completed']);
        $available = MenuItem::factory()->create(['stock_qty' => 5]);
        $unavailable = MenuItem::factory()->create(['stock_qty' => 0]);
        OrderLine::factory()->for($order)->for($available, 'item')->create();
        OrderLine::factory()->for($order)->for($unavailable, 'item')->create();
        $this->actingAs(User::factory()->create())->post(route('orders.again', $order->reference_number))->assertNotFound();
        $this->actingAs($customer)->post(route('orders.again', $order->reference_number))->assertSessionHasErrors('order');
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertSame(5, $available->fresh()->stock_qty);
    }

    public function test_menu_contains_category_sections_accessible_item_drawer_and_basket_checkout(): void
    {
        $this->withoutVite();
        $category = Category::factory()->create(['name' => 'Desserts']);
        $item = MenuItem::factory()->for($category)->create();
        $this->actingAs(User::factory()->create())->get(route('menu.index'))->assertOk()
            ->assertSee('category-'.$category->id)->assertSee('data-category-target', false)
            ->assertSee('dish-dialog')->assertSee('Special instructions')->assertSee('View Basket')
            ->assertSee(route('checkout.index'))->assertSee($item->name);
        $this->get(route('menu.index', ['search' => 'no-such-craving-xyz']))->assertOk()->assertSee('Clear Filters');
        $this->get(route('checkout.index'))->assertOk()->assertSee('Browse Stalls');
    }

    public function test_tracking_alias_receipt_drawer_and_reorder_are_only_available_to_the_owner(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer, 'customer')->create(['status' => 'completed']);
        DigitalReceipt::factory()->for($order)->create();
        $this->actingAs($customer)->get(route('orders.track.show', $order->reference_number))->assertOk()
            ->assertSee('receipt-drawer')->assertSee('View Digital Receipt')->assertSee('Order Again');
        $this->get(route('orders.track'))->assertRedirect(route('orders.show', $order->reference_number));
        $this->actingAs(User::factory()->create())->get(route('orders.track.show', $order->reference_number))->assertNotFound();
    }

    public function test_header_separates_basket_history_account_and_order_notifications(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer, 'customer')->create(['status' => 'preparing']);
        $customer->pickupNotifications()->create(['order_id' => $order->id, 'message' => 'The kitchen is preparing your meal.']);
        User::factory()->create()->pickupNotifications()->create(['message' => 'Private foreign notification']);
        $response = $this->actingAs($customer)->get(route('menu.index'))->assertOk()->assertSee('The kitchen is preparing your meal.')->assertDontSee('Private foreign notification');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $account = $xpath->query('//*[@id="main"]/header/div[1]/*[last()]')->item(0);
        $this->assertSame(route('account.edit'), $account->getAttribute('href'));
        $this->assertSame('', trim($account->textContent));
        $panel = $xpath->query('//*[@data-notification-preview]')->item(0);
        $links = $xpath->query('.//a', $panel);
        $destinations = [];
        foreach ($links as $link) {
            $destinations[] = $link->getAttribute('href');
        }
        $this->assertContains(route('orders.show', $order->reference_number), $destinations);
        $this->assertContains(route('notifications.index'), $destinations);
        $this->assertNotContains(route('account.edit'), $destinations);
        $this->assertNotContains(route('orders.index'), $destinations);
        $this->assertNotContains(route('orders.index', ['history' => 1]), $destinations);
        $this->assertSame(route('cart.index'), $xpath->query('//a[@data-active-cart-link]')->item(0)->getAttribute('href'));
        $this->assertSame(route('customer.orders.index'), $xpath->query('//nav[@aria-label="Customer navigation"]/a[@data-order-history-link]')->item(0)->getAttribute('href'));
        foreach ($xpath->query('//nav[@aria-label="Customer navigation"]/a') as $link) {
            $this->assertSame(0, $xpath->query('./text()[normalize-space(.) != ""]', $link)->length);
            $this->assertNotEmpty($link->getAttribute('aria-label'));
        }
    }

    public function test_basket_review_contains_active_cart_items_not_completed_order_history(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $item = MenuItem::factory()->create(['name' => 'Active basket meal', 'stock_qty' => 5]);
        $past = Order::factory()->for($customer, 'customer')->create(['status' => 'completed']);
        $this->actingAs($customer)->post(route('cart.store', $item), ['quantity' => 2])->assertSessionHasNoErrors();
        $response = $this->get(route('cart.index'))->assertOk()->assertSee('Active basket meal')->assertDontSee($past->displayReference());
        $this->assertSame(2, $response->viewData('lines')->sole()->quantity);
        $this->get(route('orders.index', ['history' => 1]))->assertOk()->assertSee($past->displayReference());
    }

    public function test_history_shortcut_always_filters_past_orders_and_requires_customer_access(): void
    {
        $this->withoutVite();
        $customer = User::factory()->create();
        $past = Order::factory()->for($customer, 'customer')->create(['status' => 'completed']);
        $active = Order::factory()->for($customer, 'customer')->create(['status' => 'pending']);
        $foreign = Order::factory()->create(['status' => 'completed']);
        $this->actingAs($customer)->get(route('customer.orders.index', ['history' => 0]))->assertOk()
            ->assertSee($past->displayReference())->assertDontSee($active->displayReference())->assertDontSee($foreign->displayReference());
        $this->actingAs(User::factory()->create(['user_type' => 'staff']))->get(route('customer.orders.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['user_type' => 'admin']))->get(route('customer.orders.index'))->assertForbidden();
    }
}
