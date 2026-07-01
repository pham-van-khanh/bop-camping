<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-1ly — gửi đánh giá sản phẩm ở màn chi tiết (gate + ảnh/video + pending).
 */
class ReviewSubmitTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function eligible_customer_can_submit_review_with_image_and_video(): void
    {
        Storage::fake('media');
        $user = User::create(['name' => 'Trần Quốc Bảo', 'phone' => '0900000001']);
        $product = $this->product();
        $this->returnedOrder($user, $product);

        $this->actingAs($user)->post(route('reviews.store', $product), [
            'rating' => 5,
            'content' => 'Đồ sạch sẽ, đóng gói gọn gàng.',
            'media' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->create('clip.mp4', 800, 'video/mp4'),
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $review = Review::first();
        $this->assertNotNull($review);
        $this->assertSame('pending', $review->status);
        $this->assertSame('product', $review->category);
        $this->assertSame(5, $review->rating);
        $this->assertSame('Trần Quốc Bảo', $review->reviewer_name);
        $this->assertSame($user->id, $review->user_id);
        $this->assertNotNull($review->order_item_id);

        $this->assertSame(2, $review->images()->count());
        $this->assertSame(['image', 'video'], $review->images()->pluck('type')->all());
    }

    /** @test */
    public function logged_in_customer_without_returned_order_can_still_review(): void
    {
        $user = User::create(['name' => 'Khách Lạ', 'phone' => '0900000099']);
        $product = $this->product();

        $this->actingAs($user)->post(route('reviews.store', $product), ['rating' => 5, 'content' => 'Nhìn xịn'])
            ->assertRedirect()->assertSessionHas('success');

        $review = Review::first();
        $this->assertSame('pending', $review->status);
        $this->assertSame('Khách Lạ', $review->reviewer_name);
        $this->assertSame($user->id, $review->user_id);
        $this->assertNull($review->order_item_id); // chưa thuê -> không gắn order_item
    }

    /** @test */
    public function guest_can_review_with_name(): void
    {
        $product = $this->product();

        $this->post(route('reviews.store', $product), ['reviewer_name' => 'Vãng Lai', 'rating' => 4, 'content' => 'Tốt'])
            ->assertRedirect()->assertSessionHas('success');

        $review = Review::first();
        $this->assertSame('pending', $review->status);
        $this->assertSame('Vãng Lai', $review->reviewer_name);
        $this->assertNull($review->user_id);
        $this->assertNull($review->order_item_id);
    }

    /** @test */
    public function guest_must_provide_name(): void
    {
        $product = $this->product();

        $this->post(route('reviews.store', $product), ['rating' => 4])
            ->assertSessionHasErrors('reviewer_name');

        $this->assertSame(0, Review::count());
    }

    /** @test */
    public function detail_page_shows_only_approved_reviews_and_can_review_flag(): void
    {
        $user = User::create(['name' => 'Bảo', 'phone' => '0900000001']);
        $product = $this->product();
        $this->returnedOrder($user, $product);

        Review::create(['product_id' => $product->id, 'reviewer_name' => 'A', 'rating' => 5, 'content' => 'ok', 'category' => 'product', 'status' => 'approved']);
        Review::create(['product_id' => $product->id, 'reviewer_name' => 'B', 'rating' => 1, 'content' => 'no', 'category' => 'product', 'status' => 'pending']);

        $this->actingAs($user)->get(route('products.show', $product))->assertInertia(fn (Assert $page) => $page
            ->component('ProductDetail')
            ->where('can_review', true)
            ->where('review_summary.count', 1)
            ->where('review_summary.avg', 5)
            ->has('reviews', 1)
            ->where('reviews.0.reviewer_name', 'A'));

        // Khách chưa thuê → can_review false
        $stranger = User::create(['name' => 'Lạ', 'phone' => '0900000077']);
        $this->actingAs($stranger)->get(route('products.show', $product))
            ->assertInertia(fn (Assert $page) => $page->where('can_review', false));
    }

    private function product(): Product
    {
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu-'.uniqid()]);

        return Product::create([
            'category_id' => $cat->id, 'name' => 'Lều Test', 'slug' => 'leu-'.uniqid(),
            'price_per_day' => 100000, 'quantity' => 5, 'status' => 'active',
        ]);
    }

    private function returnedOrder(User $user, Product $product): void
    {
        $order = Order::create([
            'user_id' => $user->id, 'customer_name' => $user->name, 'customer_phone' => $user->phone,
            'start_date' => '2026-07-01', 'end_date' => '2026-07-03',
            'total_price' => 200000, 'deposit_total' => 0, 'status' => 'returned',
        ]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price_per_day' => 100000, 'days' => 2, 'subtotal' => 200000]);
    }
}
