<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-saeb — đánh giá combo.
 *
 * Cùng luật với đánh giá sản phẩm (xem ReviewSubmitTest): ai cũng gửi được, mọi đánh giá
 * vào pending chờ duyệt. Cái đáng kiểm ở đây là chuyện RIÊNG của combo: đánh giá phải gắn
 * vào combo_id chứ không phải một món con nào, không lẫn với đánh giá sản phẩm, và vé
 * order_item chỉ gắn khi khách thật sự thuê combo đó.
 */
class ComboReviewTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function customer_who_rented_the_combo_can_review_it(): void
    {
        Storage::fake('media');
        $user = User::create(['name' => 'Lê Thu Hà', 'phone' => '0900000301']);
        $combo = $this->combo();
        $this->returnedComboOrder($user, $combo);

        $this->actingAs($user)->post(route('combos.reviews.store', $combo->slug), [
            'rating' => 5,
            'content' => 'Trọn bộ dựng nhanh, hai món hợp nhau.',
            'media' => [UploadedFile::fake()->image('setup.jpg')],
        ])->assertRedirect()->assertSessionHas('success');

        $review = Review::first();
        $this->assertNotNull($review);
        $this->assertSame('combo', $review->category);
        $this->assertSame($combo->id, $review->combo_id);
        $this->assertNull($review->product_id, 'Đánh giá combo không gắn vào một món con nào.');
        $this->assertSame('pending', $review->status, 'Phải chờ admin duyệt như mọi đánh giá khác.');
        $this->assertSame('Lê Thu Hà', $review->reviewer_name);
        $this->assertNotNull($review->order_item_id, 'Gắn vé order_item để hiện "X ngày" ở meta.');
        $this->assertSame(1, $review->images()->count());
    }

    /** @test */
    public function logged_in_customer_who_never_rented_the_combo_can_still_review(): void
    {
        $user = User::create(['name' => 'Khách Lạ', 'phone' => '0900000302']);
        $combo = $this->combo();

        $this->actingAs($user)->post(route('combos.reviews.store', $combo->slug), ['rating' => 5, 'content' => 'Nhìn xịn'])
            ->assertRedirect()->assertSessionHas('success');

        $review = Review::first();
        $this->assertSame('combo', $review->category);
        $this->assertSame($combo->id, $review->combo_id);
        $this->assertNull($review->order_item_id, 'Chưa thuê -> không có vé order_item để gắn.');
    }

    /** @test */
    public function guest_can_review_a_combo_with_a_name(): void
    {
        $combo = $this->combo();

        $this->post(route('combos.reviews.store', $combo->slug), ['reviewer_name' => 'Vãng Lai', 'rating' => 5, 'content' => 'Nhìn hợp lý'])
            ->assertRedirect()->assertSessionHas('success');

        $review = Review::first();
        $this->assertSame('combo', $review->category);
        $this->assertSame('Vãng Lai', $review->reviewer_name);
        $this->assertNull($review->user_id);
        $this->assertNull($review->order_item_id);
    }

    /** @test */
    public function guest_must_provide_a_name_to_review_a_combo(): void
    {
        $combo = $this->combo();

        $this->post(route('combos.reviews.store', $combo->slug), ['rating' => 4])
            ->assertSessionHasErrors('reviewer_name');

        $this->assertSame(0, Review::count());
    }

    /**
     * Vé order_item CHỈ gắn khi khách thật sự đặt trọn bộ. Thuê lẻ đủ các món giống combo
     * thì vẫn đánh giá được (ai cũng được), nhưng không được nhận vé — nếu không thì meta
     * "X ngày" nói sai rằng người này đã thuê combo.
     *
     * @test
     */
    public function renting_the_member_products_separately_does_not_earn_the_order_item_ticket(): void
    {
        $user = User::create(['name' => 'Thuê Lẻ', 'phone' => '0900000303']);
        $combo = $this->combo();

        // Đơn đã trả, chứa đúng các món của combo nhưng KHÔNG mang combo_id.
        $order = $this->order($user);
        foreach ($combo->items as $item) {
            $order->items()->create([
                'product_id' => $item->product_id, 'quantity' => $item->quantity,
                'price_per_day' => 100000, 'days' => 2, 'subtotal' => 200000,
            ]);
        }

        $this->actingAs($user)->post(route('combos.reviews.store', $combo->slug), ['rating' => 5])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertNull(Review::first()->order_item_id);
    }

    /**
     * Đơn còn đang thuê (chưa trả đồ) cũng chưa được tính là "đã thuê" -> chưa có vé.
     *
     * @test
     */
    public function combo_order_not_yet_returned_does_not_earn_the_ticket_either(): void
    {
        $user = User::create(['name' => 'Đang Thuê', 'phone' => '0900000304']);
        $combo = $this->combo();
        $this->returnedComboOrder($user, $combo, status: 'renting');

        $this->actingAs($user)->post(route('combos.reviews.store', $combo->slug), ['rating' => 5])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertNull(Review::first()->order_item_id);
    }

    /** @test */
    public function combo_detail_page_shows_only_approved_reviews(): void
    {
        $combo = $this->combo();

        Review::create(['combo_id' => $combo->id, 'reviewer_name' => 'A', 'rating' => 5, 'content' => 'ok', 'category' => 'combo', 'status' => 'approved']);
        Review::create(['combo_id' => $combo->id, 'reviewer_name' => 'B', 'rating' => 1, 'content' => 'no', 'category' => 'combo', 'status' => 'pending']);
        Review::create(['combo_id' => $combo->id, 'reviewer_name' => 'C', 'rating' => 1, 'content' => 'no', 'category' => 'combo', 'status' => 'rejected']);

        $this->get(route('combos.show', $combo->slug))->assertInertia(fn (Assert $page) => $page
            ->component('ComboDetail')
            ->where('review_summary.count', 1)
            ->where('review_summary.avg', 5)
            ->has('reviews', 1)
            ->where('reviews.0.reviewer_name', 'A'));
    }

    /**
     * Đánh giá của người ĐÃ thuê combo mang thêm dòng "X ngày" trong meta; của người chưa
     * thuê thì không. Đây là chỗ duy nhất còn phân biệt hai nhóm sau khi bỏ cổng chặn, nên
     * nếu vé order_item gắn nhầm thì trang combo nói dối mà không ai thấy.
     *
     * @test
     */
    public function only_a_real_renter_review_shows_the_rented_days_in_its_meta(): void
    {
        $user = User::create(['name' => 'Hà', 'phone' => '0900000305']);
        $combo = $this->combo();
        $order = $this->returnedComboOrder($user, $combo);

        Review::create([
            'combo_id' => $combo->id, 'order_item_id' => $order->items()->value('id'),
            'reviewer_name' => 'Đã thuê', 'rating' => 5, 'category' => 'combo', 'status' => 'approved',
        ]);
        Review::create([
            'combo_id' => $combo->id, 'reviewer_name' => 'Chưa thuê', 'rating' => 4,
            'category' => 'combo', 'status' => 'approved',
        ]);

        $this->get(route('combos.show', $combo->slug))->assertInertia(function (Assert $page) {
            $metas = collect($page->toArray()['props']['reviews'])
                ->pluck('meta', 'reviewer_name');

            $this->assertStringContainsString('2 ngày', $metas['Đã thuê']);
            $this->assertStringNotContainsString('ngày', $metas['Chưa thuê']);
        });
    }

    /**
     * Đánh giá combo KHÔNG được lẫn vào đánh giá của món con, và ngược lại. Hai bên dùng
     * hai cột khác nhau nên rất dễ tưởng là xong mà thực ra đếm chồng nhau.
     *
     * @test
     */
    public function combo_and_product_reviews_do_not_leak_into_each_other(): void
    {
        $combo = $this->combo();
        $member = $combo->items->first()->product;

        Review::create(['combo_id' => $combo->id, 'reviewer_name' => 'Của combo', 'rating' => 5, 'category' => 'combo', 'status' => 'approved']);
        Review::create(['product_id' => $member->id, 'reviewer_name' => 'Của món lẻ', 'rating' => 3, 'category' => 'product', 'status' => 'approved']);

        $this->get(route('combos.show', $combo->slug))->assertInertia(fn (Assert $page) => $page
            ->where('review_summary.count', 1)
            ->where('review_summary.avg', 5)
            ->where('reviews.0.reviewer_name', 'Của combo'));

        $this->get(route('products.show', $member->slug))->assertInertia(fn (Assert $page) => $page
            ->where('review_summary.count', 1)
            ->where('review_summary.avg', 3)
            ->where('reviews.0.reviewer_name', 'Của món lẻ'));
    }

    /** Đánh giá của combo đã xoá thì gỡ liên kết chứ không xoá theo (nullOnDelete). */
    /** @test */
    public function deleting_a_combo_keeps_its_reviews_but_unlinks_them(): void
    {
        $combo = $this->combo();
        Review::create(['combo_id' => $combo->id, 'reviewer_name' => 'A', 'rating' => 5, 'category' => 'combo', 'status' => 'approved']);

        $combo->items()->delete();
        $combo->delete();

        $review = Review::first();
        $this->assertNotNull($review, 'Xoá combo không được xoá mất đánh giá.');
        $this->assertNull($review->combo_id);
    }

    private function combo(): Combo
    {
        $combo = Combo::create([
            'name' => 'Combo Cặp Đôi', 'slug' => 'combo-cap-doi-'.uniqid(),
            'combo_price' => 120000, 'deposit' => 300000, 'is_active' => true,
        ]);
        $combo->items()->create(['product_id' => $this->product('Lều Đôi')->id, 'quantity' => 1]);
        $combo->items()->create(['product_id' => $this->product('Túi ngủ')->id, 'quantity' => 2]);

        return $combo->load('items.product');
    }

    private function product(string $name): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);

        return Product::create([
            'category_id' => $cat->id, 'name' => $name, 'slug' => Str::slug($name).'-'.uniqid(),
            'price_per_day' => 100000, 'quantity' => 5, 'status' => 'active',
        ]);
    }

    private function order(User $user, string $status = 'returned'): Order
    {
        return Order::create([
            'user_id' => $user->id, 'customer_name' => $user->name, 'customer_phone' => $user->phone,
            'start_date' => '2026-07-01', 'end_date' => '2026-07-03',
            'total_price' => 240000, 'deposit_total' => 300000, 'status' => $status,
        ]);
    }

    /** Đơn chứa combo — bung thành nhiều order_items cùng combo_id + combo_group_uuid. */
    private function returnedComboOrder(User $user, Combo $combo, string $status = 'returned'): Order
    {
        $order = $this->order($user, $status);
        $uuid = (string) Str::uuid();
        foreach ($combo->items as $item) {
            $order->items()->create([
                'product_id' => $item->product_id, 'combo_id' => $combo->id, 'combo_group_uuid' => $uuid,
                'quantity' => $item->quantity, 'price_per_day' => 100000, 'days' => 2,
                'subtotal' => 120000, 'allocated_price' => 60000, 'allocated_deposit' => 150000,
            ]);
        }

        return $order;
    }
}
