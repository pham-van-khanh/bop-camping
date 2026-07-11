<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Nguồn chân lý sinh meta SEO cho từng trang (Epic 5). Controller gọi rồi truyền
 * prop 'seo' qua Inertia; app.blade.php dựng title/description/OG/canonical/JSON-LD.
 */
class SeoService
{
    public const BRAND = 'BỐP CAMPING';

    /**
     * Chuẩn hoá 1 mảng seo cho trang. description tự cắt ~160 ký tự + strip tag
     * (tự sinh từ tên/mô tả). image → URL tuyệt đối; url mặc định = current.
     *
     * @param  array|null  $jsonld  JSON-LD riêng của trang (Product, BreadcrumbList...)
     */
    public function page(string $title, ?string $description = null, ?string $image = null, ?string $url = null, ?array $jsonld = null): array
    {
        $seo = [
            'title' => $title,
            'description' => Str::limit(trim(strip_tags((string) $description)), 160) ?: null,
            'url' => $url ?? url()->current(),
        ];

        if ($image) {
            $seo['image'] = Str::startsWith($image, ['http://', 'https://']) ? $image : url($image);
        }
        if ($jsonld) {
            $seo['jsonld'] = $jsonld;
        }

        return array_filter($seo, fn ($v) => $v !== null);
    }

    /**
     * BreadcrumbList JSON-LD từ danh sách [tên => url].
     *
     * @param  array<array{0:string,1:string}>  $items  [[name, url], ...]
     */
    public function breadcrumb(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn (array $it, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $it[0],
                'item' => $it[1],
            ])->all(),
        ];
    }
}
