<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Nguồn đơn — first-touch attribution (bopcamping-wi6h). SINGLE SOURCE cho:
 *  - đọc tín hiệu nguồn từ request vào trang (UTM, click id quảng cáo, referrer ngoài),
 *  - làm sạch giá trị trước khi lưu (dữ liệu từ URL = dữ liệu khách tự gõ được),
 *  - đóng/mở cookie `bop_attr`,
 *  - gắn nhãn nguồn để admin xem (chi tiết đơn + bảng tổng quan).
 *
 * Vì sao bắt ở SERVER (middleware + cookie) mà không ở JS/localStorage:
 *  - Ghi được ngay từ request đầu tiên, kể cả khi JS chưa chạy/bị chặn, hay khách thoát
 *    trước khi React mount.
 *  - Cookie được EncryptCookies của Laravel mã hoá → khách không sửa tay được nguồn.
 *  - Lúc đặt đơn server tự đọc cookie — form checkout không phải gửi thêm trường nào,
 *    và không tin bất kỳ giá trị nguồn nào từ body request.
 */
class AttributionService
{
    public const COOKIE = 'bop_attr';

    /** First-touch giữ 30 ngày. */
    public const TTL_MINUTES = 60 * 24 * 30;

    /** Cột trên orders + độ dài tối đa (khớp migration add_attribution_to_orders_table). */
    public const FIELDS = [
        'utm_source' => 100,
        'utm_medium' => 100,
        'utm_campaign' => 150,
        'utm_content' => 150,
        'utm_term' => 150,
        'referrer_host' => 100,
        'click_id_type' => 10,
    ];

    /** Tham số click id → loại. gbraid/wbraid là biến thể gclid của Google Ads (iOS). */
    private const CLICK_IDS = [
        'gclid' => 'gclid',
        'gbraid' => 'gclid',
        'wbraid' => 'gclid',
        'fbclid' => 'fbclid',
        'ttclid' => 'ttclid',
    ];

    /**
     * Tín hiệu nguồn của request hiện tại, hoặc null nếu không có gì (vào thẳng / điều
     * hướng nội bộ). Chỉ lưu SỰ CÓ MẶT của click id, không lưu giá trị của nó.
     *
     * @return array<string, string>|null
     */
    public function fromRequest(Request $request): ?array
    {
        $data = [];

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $key) {
            $value = $request->query($key);
            if (is_string($value) && ($clean = $this->clean($key, $value)) !== null) {
                $data[$key] = $clean;
            }
        }

        foreach (self::CLICK_IDS as $param => $type) {
            $value = $request->query($param);
            if (is_string($value) && trim($value) !== '') {
                $data['click_id_type'] = $type;
                break;
            }
        }

        $host = $this->externalReferrerHost($request);
        if ($host !== null) {
            $data['referrer_host'] = $host;
        }

        return $data === [] ? null : $data;
    }

    /**
     * Đọc cookie nguồn đã lưu. Làm sạch LẠI từng trường — không tin dữ liệu cũ hơn code
     * hiện tại (giới hạn/luật có thể đã đổi giữa hai lần deploy).
     *
     * @return array<string, string>|null
     */
    public function fromCookie(Request $request): ?array
    {
        $raw = $request->cookie(self::COOKIE);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        $data = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $value = $decoded[$key] ?? null;
            if (is_string($value) && ($clean = $this->clean($key, $value)) !== null) {
                $data[$key] = $clean;
            }
        }

        return $data === [] ? null : $data;
    }

    /** @param array<string, string> $data */
    public function encode(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Mảng cột để gộp vào đơn lúc tạo (mọi key đều có mặt, thiếu = null).
     *
     * @return array<string, string|null>
     */
    public function forOrder(Request $request): array
    {
        $data = $this->fromCookie($request) ?? [];

        $columns = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $columns[$key] = $data[$key] ?? null;
        }

        return $columns;
    }

    /**
     * Nhãn nguồn cho admin: utm_source → nền tảng suy từ click id → referrer → "Trực tiếp".
     * Dùng chung cho chi tiết đơn và bảng tổng quan để hai nơi không lệch nhau.
     */
    public static function label(?string $utmSource, ?string $referrerHost, ?string $clickIdType): string
    {
        if ($utmSource) {
            return $utmSource;
        }

        $byClick = ['gclid' => 'google (ads)', 'fbclid' => 'facebook', 'ttclid' => 'tiktok'];
        if ($clickIdType && isset($byClick[$clickIdType])) {
            return $byClick[$clickIdType];
        }

        if ($referrerHost) {
            return $referrerHost;
        }

        return 'Trực tiếp / không rõ';
    }

    /**
     * Làm sạch 1 giá trị: bỏ ký tự điều khiển/thẻ HTML, chỉ giữ chữ (mọi ngôn ngữ), số,
     * khoảng trắng và vài dấu hay gặp trong tên chiến dịch; cắt theo độ dài cột.
     * source/medium/click_id/referrer viết thường để gom nhóm không bị tách "Facebook"/"facebook".
     */
    private function clean(string $key, string $value): ?string
    {
        $max = self::FIELDS[$key] ?? 100;

        $value = strip_tags($value);
        $value = (string) preg_replace('/[^\p{L}\p{M}\p{N} ._\-+|:\/@]/u', '', $value);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if (in_array($key, ['utm_source', 'utm_medium', 'referrer_host', 'click_id_type'], true)) {
            $value = mb_strtolower($value);
        }
        if ($key === 'click_id_type' && ! in_array($value, array_unique(array_values(self::CLICK_IDS)), true)) {
            return null;
        }

        $value = mb_substr($value, 0, $max);

        return $value === '' ? null : $value;
    }

    /** Host của Referer nếu là trang NGOÀI site (bỏ www.), ngược lại null. */
    private function externalReferrerHost(Request $request): ?string
    {
        $referer = $request->headers->get('referer');
        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = $this->stripWww(mb_strtolower($host));
        $own = array_filter([
            $this->stripWww(mb_strtolower($request->getHost())),
            $this->stripWww(mb_strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST))),
        ]);

        if (in_array($host, $own, true)) {
            return null;
        }

        return $this->clean('referrer_host', $host);
    }

    private function stripWww(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
