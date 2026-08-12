<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-7w8 — trang /tai-khoan: lịch sử đơn đầy đủ (combo/lẻ, tiền, ưu đãi,
 * địa chỉ), payload "Đặt lại", và tra cứu đơn gộp vào tài khoản.
 */
class AccountOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name = 'Lều', int $price = 100000, int $deposit = 300000): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);

        return Product::create([
            'category_id' => $cat->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'price_per_day' => $price,
            'quantity' => 5,
            'deposit' => $deposit,
            'status' => 'active',
        ]);
    }

    private function order(User $user, string $phone): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_phone' => $phone,
            'customer_address' => 'Số 1 Đường ABC, Vinh',
            'start_date' => '2030-07-01',
            'end_date' => '2030-07-03',
            'status' => 'pending',
            'payment_method' => 'cod',
            'total_price' => 300000,
            'deposit_total' => 300000,
        ]);
    }

    /** @test */
    public function account_lists_orders_with_full_detail_and_address(): void
    {
        $user = User::factory()->create(['phone' => '0900000200']);
        $product = $this->product();
        $order = $this->order($user, $user->phone);
        $order->items()->create([
            'product_id' => $product->id, 'quantity' => 2, 'price_per_day' => 100000, 'days' => 3, 'subtotal' => 300000,
        ]);

        $this->actingAs($user)->get(route('account'))->assertInertia(fn (Assert $p) => $p
            ->component('Account')
            ->has('orders', 1)
            ->where('orders.0.code', $order->code)
            ->where('orders.0.address', 'Số 1 Đường ABC, Vinh')
            ->where('orders.0.total_price', 300000)
            ->where('orders.0.deposit_total', 300000)
            ->where('orders.0.amount_due', 600000)
            ->has('orders.0.groups', 1)
            ->where('orders.0.groups.0.kind', 'product')
            ->where('orders.0.groups.0.quantity', 2)
            ->has('orders.0.reorder'));
    }

    /** @test */
    public function combo_items_are_grouped_as_one_line_with_children(): void
    {
        $user = User::factory()->create(['phone' => '0900000201']);
        $tent = $this->product('Lều Combo', 100000, 300000);
        $bag = $this->product('Túi ngủ Combo', 30000, 100000);
        $combo = Combo::create(['name' => 'Combo Bộ Đôi', 'slug' => 'combo-bo-doi-'.uniqid(), 'combo_price' => 120000, 'deposit' => 350000, 'status' => 'active']);
        $combo->items()->create(['product_id' => $tent->id, 'quantity' => 1]);
        $combo->items()->create(['product_id' => $bag->id, 'quantity' => 2]);

        $order = $this->order($user, $user->phone);
        $uuid = (string) Str::uuid();
        foreach ([[$tent, 1, 60000], [$bag, 2, 60000]] as [$p, $qty, $alloc]) {
            $order->items()->create([
                'product_id' => $p->id, 'combo_id' => $combo->id, 'combo_group_uuid' => $uuid,
                'quantity' => $qty, 'price_per_day' => $p->price_per_day, 'days' => 3,
                'subtotal' => $alloc * 3, 'allocated_price' => $alloc, 'allocated_deposit' => 100000,
            ]);
        }

        $this->actingAs($user)->get(route('account'))->assertInertia(fn (Assert $p) => $p
            ->has('orders.0.groups', 1)
            ->where('orders.0.groups.0.kind', 'combo')
            ->where('orders.0.groups.0.name', 'Combo Bộ Đôi')
            ->where('orders.0.groups.0.quantity', 1)
            ->has('orders.0.groups.0.children', 2)
            ->where('orders.0.groups.0.subtotal', 360000)
            ->where('orders.0.reorder.combos.0.id', $combo->id)
            ->where('orders.0.reorder.combos.0.qty', 1));
    }

    /** @test */
    public function discount_breakdown_is_shown_with_friendly_labels(): void
    {
        $user = User::factory()->create(['phone' => '0900000202']);
        $product = $this->product();
        $order = $this->order($user, $user->phone);
        $order->items()->create([
            'product_id' => $product->id, 'quantity' => 1, 'price_per_day' => 100000, 'days' => 3, 'subtotal' => 300000,
        ]);
        $order->applyDiscountLines([['source' => 'email_bonus', 'amount' => 15000]]);

        $this->actingAs($user)->get(route('account'))->assertInertia(fn (Assert $p) => $p
            ->where('orders.0.discount_total', 15000)
            ->has('orders.0.discounts', 1)
            ->where('orders.0.discounts.0.label', 'Ưu đãi thêm email (đơn đầu)')
            ->where('orders.0.discounts.0.amount', 15000)
            ->where('orders.0.amount_due', 585000)); // 300k thuê + 300k cọc − 15k
    }

    /** @test */
    public function reorder_skips_deleted_products(): void
    {
        $user = User::factory()->create(['phone' => '0900000203']);
        $keep = $this->product('Còn bán');
        $gone = $this->product('Sắp ẩn');
        $order = $this->order($user, $user->phone);
        foreach ([$keep, $gone] as $p) {
            $order->items()->create([
                'product_id' => $p->id, 'quantity' => 1, 'price_per_day' => 100000, 'days' => 3, 'subtotal' => 300000,
            ]);
        }
        $gone->update(['status' => 'hidden']);

        $this->actingAs($user)->get(route('account'))->assertInertia(fn (Assert $p) => $p
            ->has('orders.0.reorder.products', 1)
            ->where('orders.0.reorder.products.0.name', 'Còn bán')
            ->where('orders.0.reorder.skipped', 1));
    }

    /** @test */
    public function lookup_in_account_finds_order_by_code_and_phone(): void
    {
        $user = User::factory()->create(['phone' => '0900000204']);
        $product = $this->product();
        $order = $this->order($user, $user->phone);
        $order->items()->create([
            'product_id' => $product->id, 'quantity' => 1, 'price_per_day' => 100000, 'days' => 3, 'subtotal' => 300000,
        ]);

        $this->actingAs($user)
            ->get(route('account', ['code' => $order->code, 'phone' => $user->phone]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('lookup.order.code', $order->code)
                ->where('lookup.not_found', false)
                ->has('lookup.order.timeline'));
    }

    /** @test */
    public function lookup_in_account_reports_not_found_for_wrong_phone(): void
    {
        $user = User::factory()->create(['phone' => '0900000205']);
        $order = $this->order($user, $user->phone);

        $this->actingAs($user)
            ->get(route('account', ['code' => $order->code, 'phone' => '0999999999']))
            ->assertInertia(fn (Assert $p) => $p
                ->where('lookup.order', null)
                ->where('lookup.not_found', true));
    }

    /** @test bopcamping-bhr — nút đánh giá: đơn đã trả có review_token (sinh on-demand). */
    public function returned_order_exposes_review_token_generated_on_demand(): void
    {
        $user = User::factory()->create(['phone' => '0900000210']);
        $order = $this->order($user, $user->phone); // vãng lai → chưa có token
        $order->update(['status' => 'returned']);
        $this->assertNull($order->fresh()->review_token);

        $this->actingAs($user)->get(route('account'))->assertInertia(fn (Assert $p) => $p
            ->where('orders.0.review_token', fn ($t) => is_string($t) && strlen($t) >= 20)
            ->where('orders.0.review_submitted', false));

        // Token được lưu lại (on-demand) để link /danh-gia hoạt động.
        $this->assertNotNull($order->fresh()->review_token);
    }

    /** @test */
    public function non_returned_order_has_null_review_token(): void
    {
        $user = User::factory()->create(['phone' => '0900000211']);
        $this->order($user, $user->phone); // pending

        $this->actingAs($user)->get(route('account'))->assertInertia(fn (Assert $p) => $p
            ->where('orders.0.review_token', null));
    }

    /** @test */
    public function orders_include_paid_orders_and_active_count_excludes_them(): void
    {
        $user = User::factory()->create(['phone' => '0900000206']);
        $this->order($user, $user->phone); // pending → active
        $done = $this->order($user, $user->phone);
        $done->update(['status' => 'returned']);

        $this->actingAs($user)->get(route('account'))->assertInertia(fn (Assert $p) => $p
            ->has('orders', 2)
            ->where('stats.activeOrderCount', 1));
    }

    /** @test bopcamping-2ded — giờ shop đã chốt hiện trong props /tai-khoan. */
    public function orders_expose_confirmed_schedule_times(): void
    {
        $user = User::factory()->create(['phone' => '0900000212']);
        $order = $this->order($user, $user->phone);
        $order->update(['confirmed_pickup_time' => '14:30', 'confirmed_return_time' => '09:00']);

        $this->actingAs($user)->get(route('account'))->assertInertia(fn (Assert $p) => $p
            ->where('orders.0.confirmed_pickup_time', '14:30')
            ->where('orders.0.confirmed_return_time', '09:00'));
    }

    /** @test bopcamping-2ded — giờ đã chốt cũng có trong lookup section trong /tai-khoan. */
    public function lookup_in_account_exposes_confirmed_schedule_times(): void
    {
        $user = User::factory()->create(['phone' => '0900000213']);
        $order = $this->order($user, $user->phone);
        $order->update(['confirmed_pickup_time' => '14:30', 'confirmed_return_time' => '09:00']);

        $this->actingAs($user)
            ->get(route('account', ['code' => $order->code, 'phone' => $user->phone]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('lookup.order.confirmed_pickup_time', '14:30')
                ->where('lookup.order.confirmed_return_time', '09:00'));
    }

    /**
     * Trang tài khoản phải nêu từng khoản phụ phí (bopcamping-j6hc).
     *
     * "Trả khi nhận" lấy amount_due vốn đã cộng phụ phí; thiếu dòng giải thích thì các
     * dòng khách thấy cộng lại không ra tổng.
     *
     * @test
     */
    public function account_orders_expose_each_extra_fee(): void
    {
        $user = User::factory()->create(['phone' => '0912000111']);
        $order = $this->order($user, '0912000111');
        $order->update([
            'extra_fees' => [
                ['name' => 'Phí giao tận nơi', 'value' => 50000],
                ['name' => 'Trả muộn 22h', 'value' => 30000],
            ],
            'extra_fee' => 80000,
        ]);

        $this->actingAs($user)->get(route('account'))->assertInertia(fn (Assert $p) => $p
            ->has('orders.0.extra_fees', 2)
            ->where('orders.0.extra_fees.0.name', 'Phí giao tận nơi')
            ->where('orders.0.extra_fees.1.value', 30000)
            ->etc());

        $fresh = $order->fresh();
        $sum = $fresh->total_price - $fresh->discount_total + $fresh->deposit_total
            + collect($fresh->extraFeeLines())->sum('value');
        $this->assertSame($fresh->amount_due, $sum);
    }
}
