<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-a93 — carousel đánh giá 'system' ở trang chủ.
 */
class HomeSystemReviewTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function home_shows_only_approved_system_reviews_with_avg(): void
    {
        $this->systemReview(5, 'approved', 'Tuyệt vời');
        $this->systemReview(4, 'approved', 'Rất ổn');
        $this->systemReview(1, 'pending', 'Chưa duyệt');                 // loại
        Review::create(['reviewer_name' => 'X', 'rating' => 2, 'content' => 'sp', 'category' => 'product', 'status' => 'approved']); // loại (product)

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('system_reviews', 2)
            ->where('review_stat.count', 2)
            ->where('review_stat.avg', 4.5));
    }

    /** @test */
    public function home_has_no_system_reviews_when_none_approved(): void
    {
        $this->systemReview(5, 'pending', 'Chờ');

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('system_reviews', 0)
            ->where('review_stat.count', 0));
    }

    /* ---- bopcamping-saeb: viết đánh giá tổng thể ngay trang chủ ---- */

    /** @test */
    public function customer_with_a_returned_order_can_post_a_system_review_from_home(): void
    {
        $user = $this->userWithReturnedOrder();

        $this->actingAs($user)->post(route('reviews.system.store'), [
            'rating' => 5,
            'content' => 'Giao nhận đúng hẹn, đồ sạch.',
        ])->assertRedirect()->assertSessionHas('success');

        $review = Review::first();
        $this->assertSame('system', $review->category);
        $this->assertSame('pending', $review->status, 'Vẫn phải qua kiểm duyệt.');
        $this->assertSame($user->id, $review->user_id);
        $this->assertNull($review->product_id);
        $this->assertNull($review->combo_id);
    }

    /**
     * Chưa từng thuê thì không được gửi. Trang chủ là chỗ dễ bị spam nhất nên cổng này
     * chặt hơn đánh giá sản phẩm (bên đó khách vãng lai vẫn gửi được).
     *
     * @test
     */
    public function customer_who_never_rented_is_blocked_from_posting_a_system_review(): void
    {
        $user = User::create(['name' => 'Chưa Thuê', 'phone' => '0900000402']);

        $this->actingAs($user)->post(route('reviews.system.store'), ['rating' => 5, 'content' => 'Nghe hay'])
            ->assertSessionHasErrors('review');

        $this->assertSame(0, Review::count());
    }

    /** @test */
    public function guest_cannot_post_a_system_review(): void
    {
        $this->post(route('reviews.system.store'), ['rating' => 5])->assertRedirect(route('login'));

        $this->assertSame(0, Review::count());
    }

    /** @test */
    public function home_exposes_the_gate_flag_so_the_cta_knows_whether_to_open_the_form(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('can_review_system', false));

        $this->actingAs($this->userWithReturnedOrder())->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('can_review_system', true));
    }

    /**
     * Đơn của khách đặt lúc CHƯA đăng nhập chỉ có customer_phone, không có user_id — vẫn
     * phải tính là "đã thuê", nếu không khách quen lại bị chặn.
     *
     * @test
     */
    public function order_matched_only_by_phone_still_unlocks_the_review(): void
    {
        $user = User::create(['name' => 'Khách Cũ', 'phone' => '0900000403']);
        Order::create([
            'customer_name' => 'Khách Cũ', 'customer_phone' => '0900000403',
            'start_date' => '2026-07-01', 'end_date' => '2026-07-03',
            'total_price' => 200000, 'deposit_total' => 0, 'status' => 'returned',
        ]);

        $this->actingAs($user)->post(route('reviews.system.store'), ['rating' => 4])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, Review::count());
    }

    private function userWithReturnedOrder(): User
    {
        $user = User::create(['name' => 'Đã Thuê', 'phone' => '0900000401']);
        Order::create([
            'user_id' => $user->id, 'customer_name' => $user->name, 'customer_phone' => $user->phone,
            'start_date' => '2026-07-01', 'end_date' => '2026-07-03',
            'total_price' => 200000, 'deposit_total' => 0, 'status' => 'returned',
        ]);

        return $user;
    }

    private function systemReview(int $rating, string $status, string $content): Review
    {
        return Review::create([
            'reviewer_name' => 'Khách', 'rating' => $rating, 'content' => $content,
            'category' => 'system', 'status' => $status,
        ]);
    }
}
