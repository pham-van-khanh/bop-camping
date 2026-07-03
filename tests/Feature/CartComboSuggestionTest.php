<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\ComboEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-28v (Combo P4) — tầng HTTP của cart combo detection (PRD 5.4):
 * endpoint /gio-thue/goi-y-combo trả payload đủ để FE convert 1 click,
 * event log shown/converted (US-09, khử trùng lặp theo session), và ràng buộc
 * "convert phải rẻ hơn SAU voucher" (mục 7 — voucher thường không giảm phần combo).
 * Logic match thuần đã cover ở tests/Unit/ComboCartDetectionTest.
 */
class CartComboSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private Product $tent;   // 200k/ngày, kho 5

    private Product $table;  // 100k/ngày, kho 5

    private Product $chair;  // 25k/ngày, kho 20

    private Combo $combo;    // 1 lều + 1 bàn + 4 ghế = 400k lẻ → 340k (tiết kiệm 60k/ngày)

    protected function setUp(): void
    {
        parent::setUp();

        // Cô lập số học voucher như ComboVoucherTest: tắt email bonus, nới trần giảm
        PromotionSetting::current()->update([
            'email_bonus_enabled' => false,
            'max_discount_percent_per_order' => 50,
        ]);

        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $this->tent = Product::create([
            'category_id' => $cat->id, 'name' => 'Lều Test', 'slug' => 'leu-test',
            'price_per_day' => 200000, 'quantity' => 5,
        ]);
        $this->table = Product::create([
            'category_id' => $cat->id, 'name' => 'Bàn Test', 'slug' => 'ban-test',
            'price_per_day' => 100000, 'quantity' => 5,
        ]);
        $this->chair = Product::create([
            'category_id' => $cat->id, 'name' => 'Ghế Test', 'slug' => 'ghe-test',
            'price_per_day' => 25000, 'quantity' => 20,
        ]);

        $this->combo = Combo::create(['name' => 'Combo Test', 'slug' => 'combo-test', 'combo_price' => 340000]);
        $this->combo->items()->createMany([
            ['product_id' => $this->tent->id, 'quantity' => 1],
            ['product_id' => $this->table->id, 'quantity' => 1],
            ['product_id' => $this->chair->id, 'quantity' => 4],
        ]);
    }

    /** Payload items lẻ khớp đúng combo, 12–14/07 (3 ngày). */
    private function exactItems(): array
    {
        return [
            ['product_id' => $this->tent->id, 'quantity' => 1, 'start' => '2030-07-12', 'end' => '2030-07-14'],
            ['product_id' => $this->table->id, 'quantity' => 1, 'start' => '2030-07-12', 'end' => '2030-07-14'],
            ['product_id' => $this->chair->id, 'quantity' => 4, 'start' => '2030-07-12', 'end' => '2030-07-14'],
        ];
    }

    /**
     * AC-5: giỏ khớp đủ → gợi ý exact kèm đủ dữ liệu convert 1 click
     * (matched items, giá/cọc combo, vị trí) + log shown.
     *
     * @test
     */
    public function exact_suggestion_returns_convert_payload_and_logs_shown(): void
    {
        $this->postJson(route('cart.suggestion'), ['items' => $this->exactItems()])
            ->assertOk()
            ->assertJson([
                'suggestion' => [
                    'type' => 'exact',
                    'savings' => 60000,
                    'savings_total' => 180000, // 60k × 3 ngày
                    'days' => 3,
                    'start' => '2030-07-12',
                    'end' => '2030-07-14',
                    'combo' => [
                        'id' => $this->combo->id,
                        'slug' => 'combo-test',
                        'combo_price' => 340000,
                        'sum_individual' => 400000,
                    ],
                    'missing' => [],
                ],
            ])
            ->assertJsonCount(3, 'suggestion.combo.items');

        $this->assertSame(1, ComboEvent::where('event', 'shown')
            ->where('combo_id', $this->combo->id)
            ->where('suggestion_type', 'exact')
            ->count());
    }

    /**
     * US-09: giỏ đổi nhưng gợi ý không đổi → KHÔNG đếm shown trùng trong session.
     *
     * @test
     */
    public function same_suggestion_is_not_logged_twice_in_session(): void
    {
        $this->postJson(route('cart.suggestion'), ['items' => $this->exactItems()])->assertOk();
        $this->postJson(route('cart.suggestion'), ['items' => $this->exactItems()])->assertOk();

        $this->assertSame(1, ComboEvent::where('event', 'shown')->count());
    }

    /**
     * Thiếu 1 món → upsell kèm thông tin món thiếu để "thêm nhanh" (AC-6 chiều thuận).
     *
     * @test
     */
    public function upsell_suggestion_includes_missing_item_payload(): void
    {
        // Thiếu bàn
        $items = [
            ['product_id' => $this->tent->id, 'quantity' => 1, 'start' => '2030-07-12', 'end' => '2030-07-14'],
            ['product_id' => $this->chair->id, 'quantity' => 4, 'start' => '2030-07-12', 'end' => '2030-07-14'],
        ];

        $this->postJson(route('cart.suggestion'), ['items' => $items])
            ->assertOk()
            ->assertJson([
                'suggestion' => [
                    'type' => 'upsell',
                    'missing' => [[
                        'product_id' => $this->table->id,
                        'name' => 'Bàn Test',
                        'qty' => 1,
                        'price_per_day' => 100000,
                    ]],
                ],
            ]);

        $this->assertSame(1, ComboEvent::where('suggestion_type', 'upsell')->count());
    }

    /**
     * AC-6: combo hết hàng trong khoảng ngày của giỏ → không gợi ý, không log.
     *
     * @test
     */
    public function no_suggestion_when_combo_out_of_stock(): void
    {
        $order = Order::factory()->create(['start_date' => '2030-07-12', 'end_date' => '2030-07-14']);
        $order->items()->create([
            'product_id' => $this->tent->id, 'quantity' => 5,
            'price_per_day' => 200000, 'days' => 3, 'subtotal' => 3000000,
        ]);

        $this->postJson(route('cart.suggestion'), ['items' => $this->exactItems()])
            ->assertOk()
            ->assertJson(['suggestion' => null]);

        $this->assertSame(0, ComboEvent::count());
    }

    /** @test */
    public function converted_endpoint_logs_event(): void
    {
        $this->postJson(route('cart.suggestion.converted'), [
            'combo_id' => $this->combo->id,
            'suggestion_type' => 'exact',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1, ComboEvent::where('event', 'converted')
            ->where('combo_id', $this->combo->id)
            ->count());

        // combo không tồn tại / type lạ → 422, không ghi event
        $this->postJson(route('cart.suggestion.converted'), ['combo_id' => 999999, 'suggestion_type' => 'exact'])
            ->assertStatus(422);
        $this->postJson(route('cart.suggestion.converted'), ['combo_id' => $this->combo->id, 'suggestion_type' => 'xxx'])
            ->assertStatus(422);
        $this->assertSame(1, ComboEvent::count());
    }

    /**
     * PRD 5.4 + mục 7: voucher thường (không áp combo) có thể khiến convert ĐẮT HƠN
     * → không gợi ý. Số học: lẻ 1.2M − 50% = 600k; combo 1.02M − 0 (voucher thường
     * không giảm phần combo) = 1.02M > 600k.
     *
     * @test
     */
    public function regular_voucher_making_convert_costlier_blocks_suggestion(): void
    {
        $user = User::factory()->create();
        $voucher = $this->voucher($user, 50, forCombos: false);

        $this->actingAs($user)
            ->postJson(route('cart.suggestion'), [
                'items' => $this->exactItems(),
                'voucher_codes' => [$voucher->code],
            ])
            ->assertOk()
            ->assertJson(['suggestion' => null]);

        $this->assertSame(0, ComboEvent::count());
    }

    /**
     * Voucher có applicable_to_combos giảm được cả phần combo → convert vẫn rẻ hơn
     * → gợi ý giữ nguyên (510k < 600k).
     *
     * @test
     */
    public function combo_applicable_voucher_keeps_suggestion(): void
    {
        $user = User::factory()->create();
        $voucher = $this->voucher($user, 50, forCombos: true);

        $this->actingAs($user)
            ->postJson(route('cart.suggestion'), [
                'items' => $this->exactItems(),
                'voucher_codes' => [$voucher->code],
            ])
            ->assertOk()
            ->assertJson(['suggestion' => ['type' => 'exact', 'savings' => 60000]]);
    }

    /** @test */
    public function empty_cart_returns_null_and_bad_payload_fails_validation(): void
    {
        $this->postJson(route('cart.suggestion'), [])->assertOk()->assertJson(['suggestion' => null]);
        $this->postJson(route('cart.suggestion'), ['items' => []])->assertOk()->assertJson(['suggestion' => null]);

        // Thiếu quantity / sai định dạng ngày → 422
        $this->postJson(route('cart.suggestion'), [
            'items' => [['product_id' => $this->tent->id, 'start' => '2030-07-12', 'end' => '2030-07-14']],
        ])->assertStatus(422);
        $this->postJson(route('cart.suggestion'), [
            'items' => [['product_id' => $this->tent->id, 'quantity' => 1, 'start' => 'xx', 'end' => '2030-07-14']],
        ])->assertStatus(422);
    }

    // -------------------------------------------------------------------------

    private function voucher(User $user, int|float $value, bool $forCombos): Voucher
    {
        return Voucher::create([
            'user_id' => $user->id,
            'code' => 'VC'.strtoupper(uniqid()),
            'type' => 'percent',
            'value' => $value,
            'source' => 'manual_admin',
            'status' => 'active',
            'max_uses' => 1,
            'applicable_to_combos' => $forCombos,
        ]);
    }
}
