<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\ComboEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-28v (Combo P4, US-09) — widget dashboard: top combo theo lượt thuê
 * (mỗi combo_group_uuid = 1 lượt, bỏ đơn huỷ) + convert-rate từ event log.
 */
class AdminComboStatsTest extends TestCase
{
    use RefreshDatabase;

    private Product $tent;

    private Combo $comboA;

    private Combo $comboB;

    protected function setUp(): void
    {
        parent::setUp();

        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $this->tent = Product::create([
            'category_id' => $cat->id, 'name' => 'Lều Test', 'slug' => 'leu-test',
            'price_per_day' => 100000, 'quantity' => 10,
        ]);

        $this->comboA = Combo::create(['name' => 'Combo A', 'slug' => 'combo-a', 'combo_price' => 90000]);
        $this->comboA->items()->create(['product_id' => $this->tent->id, 'quantity' => 1]);
        $this->comboB = Combo::create(['name' => 'Combo B', 'slug' => 'combo-b', 'combo_price' => 95000]);
        $this->comboB->items()->create(['product_id' => $this->tent->id, 'quantity' => 1]);
    }

    /** @test */
    public function dashboard_ranks_combos_by_rentals_with_convert_rate(): void
    {
        // Combo A: 2 lượt thuê (2 group uuid trong 2 đơn) + 1 lượt trong đơn HUỶ (không tính)
        $this->comboOrder($this->comboA, 'confirmed');
        $this->comboOrder($this->comboA, 'pending');
        $this->comboOrder($this->comboA, 'cancelled');
        // Combo B: 1 lượt
        $this->comboOrder($this->comboB, 'confirmed');

        // Event log: A shown 4 / converted 1 → 25%; B chưa từng hiện banner
        ComboEvent::insert([
            ['combo_id' => $this->comboA->id, 'event' => 'shown', 'suggestion_type' => 'exact', 'created_at' => now(), 'updated_at' => now()],
            ['combo_id' => $this->comboA->id, 'event' => 'shown', 'suggestion_type' => 'exact', 'created_at' => now(), 'updated_at' => now()],
            ['combo_id' => $this->comboA->id, 'event' => 'shown', 'suggestion_type' => 'upsell', 'created_at' => now(), 'updated_at' => now()],
            ['combo_id' => $this->comboA->id, 'event' => 'shown', 'suggestion_type' => 'superset', 'created_at' => now(), 'updated_at' => now()],
            ['combo_id' => $this->comboA->id, 'event' => 'converted', 'suggestion_type' => 'exact', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->count('combo_stats', 2)
                ->where('combo_stats.0.name', 'Combo A')
                ->where('combo_stats.0.rentals', 2)   // đơn huỷ không tính
                ->where('combo_stats.0.shown', 4)
                ->where('combo_stats.0.converted', 1)
                ->where('combo_stats.0.convert_rate', 25)
                ->where('combo_stats.1.name', 'Combo B')
                ->where('combo_stats.1.rentals', 1)
                ->where('combo_stats.1.convert_rate', null)); // chưa shown → không có rate
    }

    /** @test */
    public function dashboard_without_combo_activity_has_empty_stats(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('combo_stats', []));
    }

    /** Đơn chứa 1 instance combo (1 group uuid). */
    private function comboOrder(Combo $combo, string $status): Order
    {
        $order = Order::factory()->create([
            'start_date' => '2030-07-01',
            'end_date' => '2030-07-02',
            'status' => $status,
        ]);
        $order->items()->create([
            'product_id' => $this->tent->id,
            'combo_id' => $combo->id,
            'combo_group_uuid' => (string) Str::uuid(),
            'quantity' => 1,
            'price_per_day' => 100000,
            'days' => 2,
            'subtotal' => ((int) $combo->combo_price) * 2,
            'allocated_price' => (int) $combo->combo_price,
        ]);

        return $order;
    }
}
