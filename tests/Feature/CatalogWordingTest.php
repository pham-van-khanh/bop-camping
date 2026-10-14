<?php

namespace Tests\Feature;

use App\Services\SeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Câu chữ mặc định (meta, JSON-LD, fallback) không được hứa món shop KHÔNG cho thuê.
 *
 * Shop chỉ có lều, tăng bạt, bàn, ghế, bếp nướng, nệm hơi, thảm. Trước 14/10/2026 nhiều
 * chỗ ghi cứng "túi ngủ, đèn trại" — lên cả mô tả Google lẫn LocalBusiness của trang chủ.
 */
class CatalogWordingTest extends TestCase
{
    use RefreshDatabase;

    private const NOT_RENTED = ['túi ngủ', 'đèn trại'];

    /** @test */
    public function default_phrases_only_name_items_the_shop_rents(): void
    {
        $seo = app(SeoService::class);

        $texts = [
            $seo->categoryPhrase(),
            json_encode($seo->siteJsonLd(), JSON_UNESCAPED_UNICODE),
            json_encode($seo->localBusinessJsonLd('0373655008', '8:00 - 21:00'), JSON_UNESCAPED_UNICODE),
        ];

        foreach ($texts as $text) {
            foreach (self::NOT_RENTED as $item) {
                $this->assertStringNotContainsStringIgnoringCase($item, (string) $text);
            }
        }
    }

    /** @test */
    public function home_page_head_does_not_promise_items_the_shop_does_not_rent(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $head = substr($html, 0, (int) strpos($html, '</head>'));

        foreach (self::NOT_RENTED as $item) {
            $this->assertStringNotContainsStringIgnoringCase($item, $head);
        }
    }
}
