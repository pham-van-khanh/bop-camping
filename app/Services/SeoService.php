<?php

namespace App\Services;

use App\Models\Category;
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
     * Liệt kê danh mục đang có để nhét vào description (bopcamping-gyg8).
     *
     * Trước đây description gõ tay "lều, bếp, túi ngủ, đèn trại..." — thêm/đổi tên
     * danh mục trong admin thì câu này đứng yên, y hệt bài học của FAQ. Giờ lấy thẳng
     * từ bảng `categories`.
     *
     * Khác với faqPage(): chỗ này KHÔNG phải structured data mirror nội dung trên
     * trang, chỉ là câu văn mô tả, và danh mục là taxonomy toàn site chứ không phải
     * dữ liệu riêng của một trang — nên tự query ở đây là hợp lý, không vi phạm quy
     * tắc "markup phải khớp nội dung nhìn thấy".
     *
     * Cắt ở $limit vì description chỉ có ~160 ký tự: đã đo với 6 danh mục hiện có thì
     * câu dài 179 ký tự và bị Str::limit chặt mất đuôi "cọc linh hoạt, trả tiền khi
     * nhận (COD)" — mà đuôi đó mới là thứ đáng đọc. limit=3 cho ra 141–143 ký tự, còn
     * dư chỗ nếu sau này tên danh mục dài hơn.
     *
     * Sắp theo SỐ SẢN PHẨM giảm dần chứ không theo bảng chữ cái: mặt hàng chủ lực phải
     * đứng đầu description. Xếp theo alphabet thì "Lều cắm trại" (nhiều hàng nhất) rơi
     * xuống áp chót và bị cắt, trong khi "Ba lô & Túi" lại lên đầu.
     */
    public function categoryPhrase(int $limit = 3): string
    {
        $names = Category::withCount('products')
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return 'lều, bếp, túi ngủ, đèn trại';
        }

        return Str::lower($names->take($limit)->implode(', '))
            .($names->count() > $limit ? '...' : '');
    }

    /**
     * Nối brand vào title, nhưng CHỈ KHI title chưa có (bopcamping-12n9).
     *
     * Tiêu đề trang tĩnh do admin nhập trong panel, mà người nhập thường tự gõ luôn
     * brand — rồi controller nối thêm lần nữa thành "… — BỐP CAMPING | BỐP CAMPING",
     * ăn mất chỗ trong ~60 ký tự hiển thị trên SERP. Nửa nguyên nhân nằm ở DỮ LIỆU
     * admin nhập nên đọc code không thấy được, phải chặn ở đây.
     *
     * So khớp không phân biệt hoa/thường và bỏ dấu phụ để "Bốp Camping" cũng tính là có.
     */
    public function withBrand(?string $title): string
    {
        $title = trim((string) $title);
        if ($title === '') {
            return self::BRAND;
        }

        $norm = fn (string $s) => Str::lower(Str::ascii($s));

        return Str::contains($norm($title), $norm(self::BRAND))
            ? $title
            : $title.' | '.self::BRAND;
    }

    /**
     * FAQPage JSON-LD sinh từ CHÍNH dữ liệu đang render ra màn hình (bopcamping-s5ct).
     *
     * Google đòi markup phải ứng với FAQ nhìn thấy được trên trang đó. Trước đây khối
     * này hardcode 4 câu trong app.blade.php còn trang chủ hiện 8 câu lấy từ bảng
     * `faqs` — khớp 0/4, lại xuất ở mọi trang kể cả trang sản phẩm không có FAQ nào.
     * Nên phải truyền vào đúng collection vừa đưa cho React, KHÔNG tự query lại ở đây:
     * query lại là mở đường cho hai bên lệch nhau lần nữa.
     *
     * Trả null khi rỗng — không có FAQ thì đừng khai FAQPage.
     *
     * @param  iterable<int, array{question: string, answer: string}|object>  $faqs
     * @return array<string, mixed>|null
     */
    public function faqPage(iterable $faqs): ?array
    {
        $items = collect($faqs)
            ->map(fn ($f) => [
                'question' => trim((string) (is_array($f) ? ($f['question'] ?? '') : $f->question)),
                'answer' => trim(strip_tags((string) (is_array($f) ? ($f['answer'] ?? '') : $f->answer))),
            ])
            ->filter(fn (array $qa) => $qa['question'] !== '' && $qa['answer'] !== '')
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $items->map(fn (array $qa) => [
                '@type' => 'Question',
                'name' => $qa['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa['answer']],
            ])->all(),
        ];
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
