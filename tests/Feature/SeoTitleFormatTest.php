<?php

namespace Tests\Feature;

use App\Models\Combo;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Khoá cách server ghép <title> cho sản phẩm/combo — bộ đếm ký tự trong admin
 * (resources/js/lib/seo.ts: productTitle/comboTitle) lặp lại đúng công thức này.
 * Chuỗi mong đợi ở đây PHẢI trùng với tests/js/SeoLengthHint.test.tsx: đổi một
 * bên mà quên bên kia thì một trong hai test đỏ (bopcamping-b4c3).
 * Trang tĩnh (withBrand) đã khoá ở SeoTest.
 */
class SeoTitleFormatTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function product_title_matches_admin_preview_formula(): void
    {
        Product::factory()->create(['slug' => 'leu-2', 'name' => 'Lều 2 người']);

        $this->get('/thiet-bi/leu-2')->assertInertia(fn (Assert $p) => $p
            ->where('seo.title', 'Lều 2 người — Thuê tại BỐP CAMPING')
            ->etc());
    }

    /** @test */
    public function combo_title_matches_admin_preview_formula(): void
    {
        $combo = Combo::factory()->create(['slug' => 'combo-gd', 'name' => 'Combo Gia Đình']);
        $combo->items()->create(['product_id' => Product::factory()->create()->id, 'quantity' => 1]);

        $this->get('/combos/combo-gd')->assertInertia(fn (Assert $p) => $p
            ->where('seo.title', 'Combo Gia Đình — Thuê trọn bộ tại BỐP CAMPING')
            ->etc());
    }
}
