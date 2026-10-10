<?php

namespace Tests\Concerns;

use App\Services\SeoService;
use Illuminate\Testing\TestResponse;

/**
 * Title một nguồn (bopcamping-ft1j): <title inertia> server in ra PHẢI trùng khít
 * seo.title (thứ SiteLayout/SeoTitle đặt làm document.title phía client) và chứa
 * brand đúng một lần — trang không tự ghép thêm brand lần nữa.
 */
trait AssertsSeoTitle
{
    protected function assertSeoTitleSingleSource(TestResponse $response): string
    {
        $response->assertOk();
        $seoTitle = $response->viewData('page')['props']['seo']['title'] ?? null;
        $this->assertIsString($seoTitle, 'trang phải có seo.title');

        $this->assertSame(1, preg_match('#<title inertia>(.*?)</title>#s', $response->getContent(), $m));
        $serverTitle = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertSame($seoTitle, $serverTitle, '<title> server = seo.title');
        $this->assertSame(1, substr_count($serverTitle, SeoService::BRAND), 'brand đúng 1 lần: '.$serverTitle);

        return $serverTitle;
    }
}
