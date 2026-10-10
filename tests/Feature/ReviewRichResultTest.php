<?php

namespace Tests\Feature;

use App\Models\Combo;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-b4c3 — sao đánh giá trên SERP (Product.aggregateRating + review).
 *
 * Khai ở CẢ sản phẩm lẻ lẫn combo, chỉ từ đánh giá đã duyệt của CHÍNH trang đó
 * (đúng thứ đang hiện ra). Không có đánh giá thì không khai — bịa điểm là bị Google phạt.
 * Organization/LocalBusiness thì không bao giờ mang review (self-serving, Google không nhận).
 */
class ReviewRichResultTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function product_page_emits_aggregate_rating_and_reviews_from_approved_reviews_only(): void
    {
        $product = Product::factory()->create(['slug' => 'leu-sao-vang']);
        $this->review(['product_id' => $product->id, 'category' => 'product', 'rating' => 5, 'reviewer_name' => 'Lan', 'content' => '<p>Lều sạch</p>']);
        $this->review(['product_id' => $product->id, 'category' => 'product', 'rating' => 4, 'reviewer_name' => 'Minh']);
        $this->review(['product_id' => $product->id, 'category' => 'product', 'rating' => 1, 'status' => 'pending']);

        $this->get('/thiet-bi/leu-sao-vang')->assertInertia(fn (Assert $p) => $p
            ->where('seo.jsonld.0.@type', 'Product')
            ->where('seo.jsonld.0.aggregateRating.ratingValue', 4.5)
            ->where('seo.jsonld.0.aggregateRating.reviewCount', 2)
            ->has('seo.jsonld.0.review', 2)
            ->where('seo.jsonld.0.review.0.@type', 'Review')
            ->where('seo.jsonld.0.review.0.reviewRating.bestRating', 5)
            ->has('seo.jsonld.0.review.0.author.name')
            ->etc());

        $html = $this->get('/thiet-bi/leu-sao-vang')->getContent();
        // reviewBody là văn xuôi sạch, không lọt thẻ HTML admin/khách nhập.
        $this->assertStringContainsString('"reviewBody":"Lều sạch"', $html);
        $this->assertSame(1, substr_count($html, '"AggregateRating"'));
    }

    /** @test */
    public function product_page_without_approved_reviews_has_no_rating_markup(): void
    {
        $product = Product::factory()->create(['slug' => 'leu-chua-danh-gia']);
        $this->review(['product_id' => $product->id, 'category' => 'product', 'status' => 'pending']);

        $res = $this->get('/thiet-bi/leu-chua-danh-gia');
        $res->assertInertia(fn (Assert $p) => $p
            ->missing('seo.jsonld.0.aggregateRating')
            ->missing('seo.jsonld.0.review')
            ->etc());
        $res->assertDontSee('AggregateRating', false);
    }

    /** @test */
    public function combo_page_emits_rating_from_its_own_reviews(): void
    {
        $combo = $this->combo('combo-sao-vang');
        $this->review(['combo_id' => $combo->id, 'category' => 'combo', 'rating' => 5]);
        $this->review(['combo_id' => $combo->id, 'category' => 'combo', 'rating' => 3]);
        $this->review(['combo_id' => $combo->id, 'category' => 'combo', 'rating' => 1, 'status' => 'rejected']);

        $this->get('/combos/combo-sao-vang')->assertInertia(fn (Assert $p) => $p
            ->where('seo.jsonld.0.@type', 'Product')
            ->where('seo.jsonld.0.aggregateRating.ratingValue', 4)
            ->where('seo.jsonld.0.aggregateRating.reviewCount', 2)
            ->has('seo.jsonld.0.review', 2)
            ->etc());
    }

    /** @test */
    public function combo_page_does_not_borrow_ratings_of_its_items(): void
    {
        $combo = $this->combo('combo-muon-diem');
        // Món bên trong có đánh giá, combo thì chưa — không được gán điểm của món cho combo.
        $item = $combo->items()->first()->product;
        $this->review(['product_id' => $item->id, 'category' => 'product', 'rating' => 5]);

        $this->get('/combos/combo-muon-diem')
            ->assertInertia(fn (Assert $p) => $p->missing('seo.jsonld.0.aggregateRating')->etc())
            ->assertDontSee('AggregateRating', false);
    }

    /** @test */
    public function homepage_system_reviews_never_become_organization_rating(): void
    {
        $this->review(['category' => 'system', 'rating' => 5]);

        $this->get('/')->assertDontSee('AggregateRating', false)->assertDontSee('"Review"', false);
    }

    /**
     * Tên/nội dung đánh giá là chữ khách tự gõ, mà JSON-LD in bằng {!! !!}. Phải không
     * thoát được khỏi thẻ <script> (CWE-79) — kể cả khi gõ dạng thực thể &lt;/script&gt;
     * (plainText giải mã thực thể thành ký tự thật).
     *
     * @test
     */
    public function review_text_cannot_break_out_of_the_jsonld_script_tag(): void
    {
        $product = Product::factory()->create(['slug' => 'leu-xss']);
        $this->review([
            'product_id' => $product->id, 'category' => 'product',
            'reviewer_name' => '</script><script>alert(1)</script>',
            'content' => '&lt;/script&gt;&lt;script&gt;alert(2)&lt;/script&gt;',
        ]);

        $html = $this->get('/thiet-bi/leu-xss')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<script>alert(2)', $html);

        // JSON-LD vẫn hợp lệ và giữ đúng chữ khách gõ sau khi giải mã.
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $nodes = collect($m[1])->map(fn ($j) => json_decode($j, true, flags: JSON_THROW_ON_ERROR));
        $product = $nodes->flatten(1)->firstWhere('@type', 'Product');
        $this->assertSame('</script><script>alert(1)</script>', $product['review'][0]['author']['name']);
        $this->assertSame('</script><script>alert(2)</script>', $product['review'][0]['reviewBody']);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function combo(string $slug): Combo
    {
        $combo = Combo::factory()->create(['slug' => $slug]);
        $combo->items()->create(['product_id' => Product::factory()->create()->id, 'quantity' => 1]);

        return $combo;
    }

    /** @param  array<string, mixed>  $attrs */
    private function review(array $attrs): Review
    {
        return Review::create($attrs + [
            'reviewer_name' => 'Khách', 'rating' => 5, 'content' => 'Tốt', 'status' => 'approved',
        ]);
    }
}
