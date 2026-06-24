<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * bopcamping-0hb — schema + model đánh giá + điều kiện viết.
 */
class ReviewSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function review_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('reviews'));
        $this->assertTrue(Schema::hasColumns('reviews', [
            'order_item_id', 'product_id', 'user_id', 'reviewer_name', 'rating',
            'content', 'category', 'status', 'admin_note', 'review_token', 'review_token_used_at',
        ]));
        $this->assertTrue(Schema::hasTable('review_images'));
    }

    /** @test */
    public function approved_reviews_and_average_rating_only_count_approved(): void
    {
        $product = $this->product();
        $this->review($product, 5, 'approved');
        $this->review($product, 3, 'approved');
        $this->review($product, 1, 'pending');   // không tính
        $this->review($product, 1, 'rejected');  // không tính

        $this->assertCount(2, $product->approvedReviews()->get());
        $this->assertSame(4.0, $product->averageRating()); // (5+3)/2
    }

    /** @test */
    public function average_rating_is_zero_without_approved_reviews(): void
    {
        $this->assertSame(0.0, $this->product()->averageRating());
    }

    /** @test */
    public function review_has_ordered_images(): void
    {
        $review = $this->review($this->product(), 5, 'approved');
        $review->images()->create(['path' => 'a.jpg', 'sort_order' => 1]);
        $review->images()->create(['path' => 'b.jpg', 'sort_order' => 0]);

        $this->assertSame(['b.jpg', 'a.jpg'], $review->images()->pluck('path')->all());
    }

    /** @test */
    public function eligibility_requires_a_returned_order_with_the_product(): void
    {
        $user = User::create(['name' => 'Khách', 'phone' => '0900000001']);
        $product = $this->product();
        $other = $this->product();

        // Đơn returned chứa $product → đủ điều kiện.
        $itemId = $this->returnedOrderItem($user, $product);
        $this->assertSame($itemId, $user->reviewableOrderItemId($product->id));

        // Sản phẩm khác → null.
        $this->assertNull($user->reviewableOrderItemId($other->id));
    }

    /** @test */
    public function eligibility_false_when_order_not_returned(): void
    {
        $user = User::create(['name' => 'Khách', 'phone' => '0900000002']);
        $product = $this->product();

        $order = Order::create([
            'user_id' => $user->id, 'customer_name' => 'Khách', 'customer_phone' => '0900000002',
            'start_date' => '2026-07-01', 'end_date' => '2026-07-02',
            'total_price' => 100000, 'deposit_total' => 0, 'status' => 'confirmed',
        ]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price_per_day' => 100000, 'days' => 1, 'subtotal' => 100000]);

        $this->assertNull($user->reviewableOrderItemId($product->id));
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function product(): Product
    {
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu-'.uniqid()]);

        return Product::create([
            'category_id' => $cat->id, 'name' => 'Lều', 'slug' => 'leu-'.uniqid(),
            'price_per_day' => 100000, 'quantity' => 5, 'status' => 'active',
        ]);
    }

    private function review(Product $product, int $rating, string $status): Review
    {
        return Review::create([
            'product_id' => $product->id, 'reviewer_name' => 'Khách',
            'rating' => $rating, 'content' => 'Tốt', 'category' => 'product', 'status' => $status,
        ]);
    }

    private function returnedOrderItem(User $user, Product $product): int
    {
        $order = Order::create([
            'user_id' => $user->id, 'customer_name' => $user->name, 'customer_phone' => $user->phone,
            'start_date' => '2026-07-01', 'end_date' => '2026-07-02',
            'total_price' => 100000, 'deposit_total' => 0, 'status' => 'returned',
        ]);

        return $order->items()->create([
            'product_id' => $product->id, 'quantity' => 1, 'price_per_day' => 100000, 'days' => 1, 'subtotal' => 100000,
        ])->id;
    }
}
