<?php

namespace Tests\Feature;

use App\Mail\ReviewInviteMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-2x0 — mail mời đánh giá sau chuyến đi + trang viết qua token.
 */
class ReviewInviteTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function returned_order_generates_token_and_queues_invite(): void
    {
        Mail::fake();
        $product = $this->product();
        $order = Order::create([
            'customer_name' => 'Khách', 'customer_phone' => '0900000001', 'customer_email' => 'k@example.com',
            'start_date' => '2026-07-01', 'end_date' => '2026-07-03', 'total_price' => 200000, 'deposit_total' => 0, 'status' => 'confirmed',
        ]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price_per_day' => 100000, 'days' => 2, 'subtotal' => 200000]);

        $order->update(['status' => 'returned']);

        $order->refresh();
        $this->assertNotNull($order->review_token);
        $this->assertNotNull($order->review_invited_at);
        Mail::assertQueued(ReviewInviteMail::class, fn (ReviewInviteMail $m) => $m->order->is($order) && $m->hasTo('k@example.com'));
    }

    /** @test */
    public function no_invite_when_order_has_no_real_email(): void
    {
        Mail::fake();
        $product = $this->product();
        $order = Order::create([
            'customer_name' => 'Khách', 'customer_phone' => '0900000001',
            'start_date' => '2026-07-01', 'end_date' => '2026-07-03', 'total_price' => 200000, 'deposit_total' => 0, 'status' => 'confirmed',
        ]); // customer_email null
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price_per_day' => 100000, 'days' => 2, 'subtotal' => 200000]);

        $order->update(['status' => 'returned']);

        $this->assertNull($order->fresh()->review_token);
        Mail::assertNotQueued(ReviewInviteMail::class);
    }

    /** @test */
    public function token_page_shows_items_and_invalid_token_not_found(): void
    {
        [$order] = $this->returnedOrderWithToken();

        $this->get('/danh-gia/'.$order->review_token)->assertInertia(fn (Assert $page) => $page
            ->component('ReviewInvite')->where('found', true)->where('code', $order->code)->has('items', 1));

        $this->get('/danh-gia/khong-ton-tai')->assertInertia(fn (Assert $page) => $page->where('found', false));
    }

    /** @test */
    public function submit_creates_pending_system_and_product_reviews(): void
    {
        [$order, $item, $product] = $this->returnedOrderWithToken();

        $this->post('/danh-gia/'.$order->review_token, [
            'system_rating' => 5, 'system_content' => 'Shop nhiệt tình',
            'items' => [['order_item_id' => $item->id, 'rating' => 4, 'content' => 'Lều ổn']],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('reviews', ['category' => 'system', 'rating' => 5, 'status' => 'pending', 'product_id' => null]);
        $this->assertDatabaseHas('reviews', ['category' => 'product', 'rating' => 4, 'status' => 'pending', 'product_id' => $product->id, 'order_item_id' => $item->id]);
        $this->assertNotNull($order->fresh()->review_submitted_at);
    }

    /** @test */
    public function cannot_submit_twice_or_without_rating(): void
    {
        [$order, $item] = $this->returnedOrderWithToken();

        // Không chấm sao mục nào → lỗi.
        $this->post('/danh-gia/'.$order->review_token, ['items' => [['order_item_id' => $item->id]]])
            ->assertSessionHasErrors('review');
        $this->assertSame(0, Review::count());

        // Gửi hợp lệ lần 1.
        $this->post('/danh-gia/'.$order->review_token, ['system_rating' => 5])->assertSessionHas('success');
        // Gửi lại → bị chặn.
        $this->post('/danh-gia/'.$order->review_token, ['system_rating' => 4])->assertSessionHasErrors('review');
        $this->assertSame(1, Review::count());
    }

    private function product(): Product
    {
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu-'.uniqid()]);

        return Product::create(['category_id' => $cat->id, 'name' => 'Lều', 'slug' => 'leu-'.uniqid(), 'price_per_day' => 100000, 'quantity' => 5, 'status' => 'active']);
    }

    /** @return array{0: Order, 1: OrderItem, 2: Product} */
    private function returnedOrderWithToken(): array
    {
        $product = $this->product();
        $order = Order::create([
            'customer_name' => 'Khách', 'customer_phone' => '0900000001', 'customer_email' => 'k@example.com',
            'start_date' => '2026-07-01', 'end_date' => '2026-07-03', 'total_price' => 200000, 'deposit_total' => 0,
            'status' => 'returned', 'review_token' => 'TOKEN-'.uniqid(),
        ]);
        $item = $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price_per_day' => 100000, 'days' => 2, 'subtotal' => 200000]);

        return [$order, $item, $product];
    }
}
