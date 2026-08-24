<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-vxwx — mail mời đánh giá sau chuyến đi phải mời cả COMBO.
 *
 * Đây là kênh chính sinh ra đánh giá (khách chủ động vào trang combo viết thì ít hơn
 * nhiều), nên trước bản này đánh giá combo gần như không bao giờ có dù tính năng đã xong.
 *
 * Chỗ nguy hiểm nhất: `combo_id` KHÔNG được lấy từ payload mà phải đối chiếu với đúng
 * order_items của đơn — tin payload thì ai cầm link đánh giá cũng gắn được đánh giá vào
 * combo bất kỳ trong shop. Có test riêng cho đúng ca đó.
 */
class ReviewInviteComboTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Combo là MỘT mục chấm cho cả bộ, và các món bên trong KHÔNG được hỏi riêng — chủ shop
     * chốt vậy vì bắt chấm thêm từng món làm form dài gấp mấy lần, khách bỏ ngang thì mất
     * luôn cả đánh giá combo. Món trong bộ chỉ hiện dưới dạng chữ để khách nhớ đã thuê gì.
     *
     * @test
     */
    public function combo_is_one_thing_to_rate_and_its_member_products_are_not_asked_separately(): void
    {
        [$order, $combo] = $this->orderWithCombo();

        $this->get(route('review.invite', $order->review_token))
            ->assertInertia(fn (Assert $page) => $page
                ->component('ReviewInvite')
                ->has('combos', 1)
                ->where('combos.0.combo_id', $combo->id)
                ->where('combos.0.name', $combo->name)
                // Liệt kê món trong bộ (chỉ để đọc); ×n khi số lượng > 1.
                ->where('combos.0.items', ['Lều Đôi', 'Túi ngủ ×2'])
                // Không mục chấm nào cho món con — đơn này chỉ có combo.
                ->has('items', 0));
    }

    /** Món thuê LẺ vẫn hỏi riêng như cũ — chỉ món nằm trong combo mới bị gộp. */
    /** @test */
    public function standalone_items_are_still_asked_one_by_one(): void
    {
        [$order, $combo] = $this->orderWithCombo();
        $order->items()->create([
            'product_id' => $this->product('Ghế xếp')->id, 'quantity' => 1,
            'price_per_day' => 80000, 'days' => 2, 'subtotal' => 160000,
        ]);

        $this->get(route('review.invite', $order->review_token))
            ->assertInertia(fn (Assert $page) => $page
                ->has('combos', 1)
                ->has('items', 1)
                ->where('items.0.name', 'Ghế xếp'));
    }

    /** @test */
    public function order_without_a_combo_still_lists_nothing_to_rate_as_combo(): void
    {
        $order = $this->plainOrder();

        $this->get(route('review.invite', $order->review_token))
            ->assertInertia(fn (Assert $page) => $page
                ->has('combos', 0)
                ->has('items', 1));
    }

    /** @test */
    public function rating_the_combo_creates_a_combo_review_tied_to_the_order(): void
    {
        Storage::fake('media');
        [$order, $combo] = $this->orderWithCombo();
        $key = $order->items->first()->combo_group_uuid;

        $this->post(route('review.invite.store', $order->review_token), [
            'combos' => [[
                'key' => $key,
                'rating' => 5,
                'content' => 'Trọn bộ đủ đồ, hai món hợp nhau.',
                'media' => [UploadedFile::fake()->image('setup.jpg')],
            ]],
        ])->assertRedirect()->assertSessionHas('success');

        $review = Review::where('category', 'combo')->first();
        $this->assertNotNull($review);
        $this->assertSame($combo->id, $review->combo_id);
        $this->assertSame('pending', $review->status);
        $this->assertSame('Khách', $review->reviewer_name);
        $this->assertNull($review->product_id, 'Đánh giá combo không gắn vào món con nào.');
        // Vé order_item nuôi dòng meta "X ngày" trên trang combo.
        $this->assertNotNull($review->order_item_id);
        $this->assertSame(1, $review->images()->count());
    }

    /**
     * Gửi `key` của đơn KHÁC (hoặc bịa ra) thì không tạo được gì — nếu không, ai có một
     * link đánh giá hợp lệ cũng bơm được đánh giá vào combo họ chưa từng thuê.
     *
     * @test
     */
    public function a_key_that_does_not_belong_to_this_order_is_ignored(): void
    {
        [$mine] = $this->orderWithCombo();
        [$someoneElse] = $this->orderWithCombo();
        $foreignKey = $someoneElse->items->first()->combo_group_uuid;

        $this->post(route('review.invite.store', $mine->review_token), [
            'combos' => [
                ['key' => $foreignKey, 'rating' => 5, 'content' => 'Của đơn người khác'],
                ['key' => 'khoa-bia-dat', 'rating' => 5, 'content' => 'Bịa'],
            ],
        ])->assertSessionHasErrors('review'); // không mục nào hợp lệ -> "hãy chấm sao ít nhất một mục"

        $this->assertSame(0, Review::count());
    }

    /**
     * Đặt 2 combo giống nhau trong một đơn = 2 lượt, chấm riêng từng lượt. Gom theo
     * combo_id thay vì combo_group_uuid sẽ nhập hai lượt làm một.
     *
     * @test
     */
    public function two_runs_of_the_same_combo_are_two_separate_things_to_rate(): void
    {
        [$order, $combo] = $this->orderWithCombo();
        $this->attachComboGroup($order, $combo);          // lượt thứ hai, cùng combo
        $order->load('items');

        $this->get(route('review.invite', $order->review_token))
            ->assertInertia(fn (Assert $page) => $page->has('combos', 2));

        $keys = $order->items->pluck('combo_group_uuid')->unique()->values();
        $this->post(route('review.invite.store', $order->review_token), [
            'combos' => [
                ['key' => $keys[0], 'rating' => 5],
                ['key' => $keys[1], 'rating' => 3],
            ],
        ])->assertSessionHas('success');

        $this->assertSame([3, 5], Review::where('category', 'combo')->pluck('rating')->sort()->values()->all());
    }

    /** @test */
    public function combo_standalone_item_and_shop_can_be_rated_in_the_same_submit(): void
    {
        [$order] = $this->orderWithCombo();
        $key = $order->items->first()->combo_group_uuid;
        $loose = $order->items()->create([
            'product_id' => $this->product('Ghế xếp')->id, 'quantity' => 1,
            'price_per_day' => 80000, 'days' => 2, 'subtotal' => 160000,
        ]);

        $this->post(route('review.invite.store', $order->review_token), [
            'system_rating' => 5,
            'combos' => [['key' => $key, 'rating' => 5]],
            'items' => [['order_item_id' => $loose->id, 'rating' => 4]],
        ])->assertSessionHas('success');

        $this->assertSame(1, Review::where('category', 'system')->count());
        $this->assertSame(1, Review::where('category', 'combo')->count());
        $this->assertSame(1, Review::where('category', 'product')->count());
    }

    /** @return array{0: Order, 1: Combo} */
    private function orderWithCombo(): array
    {
        $combo = Combo::create([
            'name' => 'Combo Cặp Đôi', 'slug' => 'combo-'.uniqid(),
            'combo_price' => 120000, 'deposit' => 300000, 'is_active' => true,
        ]);
        $combo->items()->create(['product_id' => $this->product('Lều Đôi')->id, 'quantity' => 1]);
        $combo->items()->create(['product_id' => $this->product('Túi ngủ')->id, 'quantity' => 2]);
        $combo->load('items.product');

        $order = $this->plainOrder(withPlainItem: false);
        $this->attachComboGroup($order, $combo);

        return [$order->load('items'), $combo];
    }

    /** Thêm một LƯỢT combo vào đơn: các món con cùng combo_id + combo_group_uuid. */
    private function attachComboGroup(Order $order, Combo $combo): void
    {
        $uuid = (string) Str::uuid();
        foreach ($combo->items as $item) {
            $order->items()->create([
                'product_id' => $item->product_id, 'combo_id' => $combo->id, 'combo_group_uuid' => $uuid,
                'quantity' => $item->quantity, 'price_per_day' => 100000, 'days' => 2,
                'subtotal' => 120000, 'allocated_price' => 60000, 'allocated_deposit' => 150000,
            ]);
        }
    }

    private function plainOrder(bool $withPlainItem = true): Order
    {
        $order = Order::create([
            'customer_name' => 'Khách', 'customer_phone' => '0900000001', 'customer_email' => 'k@example.com',
            'start_date' => '2026-07-01', 'end_date' => '2026-07-03', 'total_price' => 200000, 'deposit_total' => 0,
            'status' => 'returned', 'review_token' => 'TOKEN-'.uniqid(),
        ]);

        if ($withPlainItem) {
            $order->items()->create([
                'product_id' => $this->product('Ghế xếp')->id, 'quantity' => 1,
                'price_per_day' => 100000, 'days' => 2, 'subtotal' => 200000,
            ]);
        }

        return $order->load('items');
    }

    private function product(string $name): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);

        return Product::create([
            'category_id' => $cat->id, 'name' => $name, 'slug' => Str::slug($name).'-'.uniqid(),
            'price_per_day' => 100000, 'quantity' => 5, 'status' => 'active',
        ]);
    }
}
