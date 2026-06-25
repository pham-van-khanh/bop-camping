<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-599 — admin duyệt đánh giá.
 */
class AdminReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function guest_and_non_admin_cannot_access(): void
    {
        $this->get(route('admin.reviews'))->assertRedirect(route('admin.login'));
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get(route('admin.reviews'))->assertRedirect(route('admin.login'));
    }

    /** @test */
    public function index_defaults_to_pending_and_filters_by_status(): void
    {
        $product = $this->product();
        $this->review($product, 'pending');
        $this->review($product, 'approved');

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.reviews'))->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reviews')
            ->where('filters.status', 'pending')
            ->where('counts.pending', 1)
            ->has('reviews.data', 1));

        $this->actingAs($admin)->get(route('admin.reviews', ['status' => 'approved']))
            ->assertInertia(fn (Assert $page) => $page->has('reviews.data', 1)->where('reviews.data.0.status', 'approved'));
    }

    /** @test */
    public function admin_can_approve_review_then_it_shows_publicly(): void
    {
        $product = $this->product();
        $review = $this->review($product, 'pending');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertCount(0, $product->approvedReviews()->get());

        $this->actingAs($admin)->patch(route('admin.reviews.update', $review), ['status' => 'approved'])
            ->assertRedirect();

        $this->assertSame('approved', $review->fresh()->status);
        $this->assertCount(1, $product->approvedReviews()->get());
    }

    /** @test */
    public function admin_can_reject_with_note(): void
    {
        $review = $this->review($this->product(), 'pending');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('admin.reviews.update', $review), ['status' => 'rejected', 'admin_note' => 'Nội dung không phù hợp'])
            ->assertRedirect();

        $review->refresh();
        $this->assertSame('rejected', $review->status);
        $this->assertSame('Nội dung không phù hợp', $review->admin_note);
    }

    /** @test */
    public function pending_count_is_shared_for_admin_badge(): void
    {
        $product = $this->product();
        $this->review($product, 'pending');
        $this->review($product, 'pending');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('pending_reviews', 2));
    }

    private function product(): Product
    {
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu-'.uniqid()]);

        return Product::create(['category_id' => $cat->id, 'name' => 'Lều', 'slug' => 'leu-'.uniqid(), 'price_per_day' => 100000, 'quantity' => 5, 'status' => 'active']);
    }

    private function review(Product $product, string $status): Review
    {
        return Review::create(['product_id' => $product->id, 'reviewer_name' => 'Khách', 'rating' => 5, 'content' => 'Tốt', 'category' => 'product', 'status' => $status]);
    }
}
