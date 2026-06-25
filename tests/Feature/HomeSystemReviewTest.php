<?php

namespace Tests\Feature;

use App\Models\Review;
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

    private function systemReview(int $rating, string $status, string $content): Review
    {
        return Review::create([
            'reviewer_name' => 'Khách', 'rating' => $rating, 'content' => $content,
            'category' => 'system', 'status' => $status,
        ]);
    }
}
