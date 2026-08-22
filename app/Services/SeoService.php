<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ServiceLocation;
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
            'description' => $this->plainText($description),
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
     * Biến nội dung HTML thành một dòng văn xuôi sạch cho meta description.
     *
     * strip_tags() KHÔNG chèn khoảng trắng khi gỡ thẻ, nên hai khối liền nhau dính lại.
     * Đo trên production 13/08/2026, cả 6 trang tĩnh đều hỏng:
     *   "Câu chuyện BỐP CAMPING" + "BỐP CAMPING ra đời…"  →  "CAMPINGBỐP CAMPING"
     *   "…cho khách." + "1. Khu vực phục vụ" + "Chúng tôi…" → "khách.1. Khu vực phục vụChúng tôi"
     * Đây là chữ hiện thẳng trên kết quả Google.
     *
     * Mô tả sản phẩm còn chứa xuống dòng thật (\r\n) lọt vào content="…" — meta phải là
     * MỘT dòng; xuống dòng trong đó làm hỏng cả bộ phân tích ngây thơ (chính lệnh grep
     * lúc audit cũng đọc nhầm thành "không có description").
     *
     * Thứ tự quan trọng: chèn khoảng trắng THAY cho thẻ trước, rồi mới gộp khoảng trắng,
     * rồi mới cắt — cắt trước thì đếm nhầm cả khoảng trắng thừa vào 160 ký tự.
     *
     * public vì controller chi tiết sản phẩm/combo cần chính hàm này cho cả meta lẫn
     * JSON-LD. Trước đây mỗi nơi tự viết `Str::limit(strip_tags(...))` một bản — ba bản
     * cùng một lỗi, sửa một chỗ không hết.
     */
    public function plainText(?string $html, int $limit = 160): ?string
    {
        if (blank($html)) {
            return null;
        }

        // Thẻ -> khoảng trắng (không phải xoá trắng), rồi giải mã thực thể (&amp; …).
        $text = html_entity_decode(strip_tags(preg_replace('/<[^>]*>/', ' $0', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Mọi loại khoảng trắng (\r \n \t, cả no-break space) gộp thành một dấu cách.
        $text = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text));

        return Str::limit($text, $limit) ?: null;
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
     * Organization + WebSite JSON-LD (site-wide).
     *
     * PHẢI dựng ở PHP, KHÔNG được viết thẳng trong .blade.php: Laravel 11+ có directive
     * `@context`, nên chuỗi '@context' nằm trong file blade bị compiler biến thành mã
     * PHP và key JSON-LD ra thành "<?php $__contextArgs = []; ...". Đo trên production
     * 11/08: Organization, WebSite, FAQPage đều mất @context => Google không đọc được.
     * LocalBusiness thoát vì nó vốn đã dựng ở PHP.
     *
     * areaServed suy từ ServiceLocation đang mở thay vì ghi cứng ['Vinh','Hà Nội'] —
     * cùng lỗi cũ: mở cơ sở mới thì schema nói sai khu vực phục vụ.
     *
     * `description` tả TỔ CHỨC, không lấy description của trang đang xem: Organization
     * là một thực thể cố định, mô tả nó phải giống nhau ở mọi trang. Bản cũ nhét
     * $seoDesc vào nên trang sản phẩm nào thì Organization mang mô tả sản phẩm đó.
     *
     * @return array<int, array<string, mixed>>
     */
    public function siteJsonLd(): array
    {
        $areas = ServiceLocation::open()
            ->pluck('name')->filter()->unique()->values()->all();

        return [
            array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => self::BRAND,
                'url' => url('/'),
                'logo' => url('/images/logo.png'),
                'image' => url('/images/album/forest-camp-aerial.jpg'),
                'description' => 'Cho thuê '.$this->categoryPhrase().' theo ngày. Cọc linh hoạt, trả tiền khi nhận (COD).',
                'areaServed' => $areas ?: null,
            ], fn ($v) => $v !== null),
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => self::BRAND,
                'url' => url('/'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => url('/thiet-bi').'?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }

    /**
     * LocalBusiness JSON-LD — MỘT khối/cơ sở khi admin đã điền địa chỉ cụ thể (Cài đặt >
     * Điểm cắm trại), gộp chung MỘT khối generic khi chưa điền.
     *
     * Trước đây luôn khai 1 khối chung dùng `areaServed` (chỉ tên khu vực, vd "Vinh"),
     * dù `service_locations.address` đã có sẵn cột và form admin đã cho nhập từ lâu —
     * chỉ là chưa ai điền. Rich result "gần bạn"/Google Maps cần `address` (PostalAddress)
     * cụ thể mới xếp hạng local pack tốt; tên khu vực suông không đủ.
     *
     * Vinh và Hà Nội là hai CƠ SỞ THẬT (kho riêng, sản phẩm/tồn kho riêng — xem ADR
     * per-store stock), không phải một cơ sở phục vụ nhiều khu vực, nên khi đã có địa chỉ
     * thì khai MỖI cơ sở một LocalBusiness riêng — đúng hơn là nhét chung một khối.
     *
     * Trả null khi chưa có hotline (đủ dữ liệu tối thiểu cho rich result).
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function localBusinessJsonLd(?string $telephone, ?string $workingHours): ?array
    {
        if (blank($telephone)) {
            return null;
        }

        $locations = ServiceLocation::open()->ordered()->get();
        $withAddress = $locations->filter(fn (ServiceLocation $l) => filled($l->address));

        if ($withAddress->isEmpty()) {
            return [array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'LocalBusiness',
                'name' => self::BRAND,
                'description' => 'Cho thuê thiết bị cắm trại theo ngày — lều, bếp, túi ngủ, đèn trại.',
                'url' => url('/'),
                'image' => url('/images/album/forest-camp-aerial.jpg'),
                'telephone' => $telephone,
                'areaServed' => $locations->pluck('name')->all() ?: ['Vinh', 'Hà Nội'],
                'openingHours' => $workingHours,
                'priceRange' => '$$',
            ], fn ($v) => $v !== null)];
        }

        return $withAddress->map(fn (ServiceLocation $l) => array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => self::BRAND.' - '.$l->name,
            'description' => 'Cho thuê thiết bị cắm trại theo ngày — lều, bếp, túi ngủ, đèn trại.',
            'url' => url('/'),
            'image' => url('/images/album/forest-camp-aerial.jpg'),
            'telephone' => $telephone,
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $l->address,
                'addressLocality' => $l->name,
                'addressRegion' => $l->area,
                'addressCountry' => 'VN',
            ], fn ($v) => filled($v)),
            'areaServed' => [$l->name],
            'openingHours' => $workingHours,
            'priceRange' => '$$',
        ], fn ($v) => $v !== null))->values()->all();
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
                // Câu trả lời FAQ cũng là HTML do admin nhập — dính chữ ở đây thì rich result
                // hiện sai y hệt meta description.
                'answer' => (string) $this->plainText(is_array($f) ? ($f['answer'] ?? '') : $f->answer, 5000),
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
