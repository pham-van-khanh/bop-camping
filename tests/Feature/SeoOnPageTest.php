<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-e91m — SEO on-page sau khi có SSR: trang danh mục có h1 + giới thiệu
 * riêng; combo có phụ đề chứa từ khoá đứng đầu title.
 */
class SeoOnPageTest extends TestCase
{
    use RefreshDatabase;

    private function category(array $attrs = []): Category
    {
        $category = Category::create($attrs + ['name' => 'Lều cắm trại', 'slug' => 'leu-cam-trai']);
        foreach ([120000, 80000] as $i => $price) {
            Product::factory()->create([
                'category_id' => $category->id,
                'slug' => 'mon-'.$i,
                'price_per_day' => $price,
            ]);
        }

        return $category;
    }

    private function combo(array $attrs = []): Combo
    {
        $combo = Combo::factory()->create($attrs + ['name' => 'Relax', 'slug' => 'relax']);
        $combo->items()->create(['product_id' => Product::factory()->create()->id, 'quantity' => 1]);

        return $combo;
    }

    /** @test */
    public function unfiltered_listing_has_no_category_block(): void
    {
        $this->category();

        $this->get('/thiet-bi')->assertInertia(fn ($p) => $p->where('category', null));
    }

    /** @test */
    public function category_page_gets_its_own_heading_and_generated_intro(): void
    {
        $this->category();

        $this->get('/thiet-bi?cat=leu-cam-trai')->assertInertia(fn ($p) => $p
            ->where('category.heading', 'Thuê Lều cắm trại')
            // Chưa nhập mô tả → sinh từ dữ liệu thật: 2 mẫu, giá thấp nhất 80.000đ.
            ->where('category.intro', 'Cho thuê Lều cắm trại theo ngày tại BỐP CAMPING: 2 mẫu, giá từ 80.000đ/ngày. Cọc linh hoạt, trả tiền khi nhận (COD).')
            // Meta description = đúng đoạn đang hiện trên trang.
            ->where('seo.description', fn ($d) => str_starts_with($d, 'Cho thuê Lều cắm trại theo ngày tại BỐP CAMPING: 2 mẫu'))
        );
    }

    /** @test */
    public function category_description_from_admin_wins_over_generated_intro(): void
    {
        $this->category(['description' => 'Lều 2–8 người, dựng nhanh, đã vệ sinh sau mỗi lượt thuê.']);

        $this->get('/thiet-bi?cat=leu-cam-trai')->assertInertia(fn ($p) => $p
            ->where('category.intro', 'Lều 2–8 người, dựng nhanh, đã vệ sinh sau mỗi lượt thuê.')
            // Mô tả ngắn → meta nối thêm câu chung cho đủ dài; phần hiện trên trang giữ nguyên.
            ->where('seo.description', 'Lều 2–8 người, dựng nhanh, đã vệ sinh sau mỗi lượt thuê. Thuê Lều cắm trại theo ngày tại BỐP CAMPING, cọc linh hoạt, trả tiền khi nhận (COD).')
        );
    }

    /** @test */
    public function unknown_category_slug_has_no_category_block(): void
    {
        $this->category();

        $this->get('/thiet-bi?cat=khong-co')->assertOk()
            ->assertInertia(fn ($p) => $p->where('category', null));
    }

    /** @test */
    public function combo_without_subtitle_keeps_the_old_title(): void
    {
        $this->combo();

        $this->get('/combos/relax')->assertInertia(fn ($p) => $p
            ->where('seo.title', 'Relax — Thuê trọn bộ tại BỐP CAMPING')
            ->where('combo.subtitle', null)
        );
    }

    /** @test */
    public function combo_subtitle_leads_the_title_and_is_sent_to_the_page(): void
    {
        $this->combo(['subtitle' => 'Combo cắm trại gia đình 4 người']);

        $this->get('/combos/relax')->assertInertia(fn ($p) => $p
            ->where('seo.title', 'Combo cắm trại gia đình 4 người — Relax | BỐP CAMPING')
            ->where('combo.subtitle', 'Combo cắm trại gia đình 4 người')
        );

        // Thẻ ở trang danh sách combo cũng có phụ đề.
        $this->get('/combos')->assertInertia(fn ($p) => $p
            ->where('combos.0.subtitle', 'Combo cắm trại gia đình 4 người')
        );
    }
}
