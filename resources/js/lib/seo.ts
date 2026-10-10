/**
 * Gợi ý độ dài SEO cho form admin (bopcamping-b4c3).
 *
 * Các hàm `*Title` dưới đây CHỈ để xem trước — chúng lặp lại đúng cách server ghép
 * <title>, không phải nơi quyết định title. Nguồn thật:
 *   - sản phẩm: ProductController::show  → "<tên> — Thuê tại BỐP CAMPING"
 *   - combo:    ComboController::show    → "<tên> — Thuê trọn bộ tại BỐP CAMPING"
 *   - trang tĩnh: SeoService::withBrand  → nối " | BỐP CAMPING" khi tiêu đề chưa có brand
 * Đổi cách ghép ở PHP thì sửa cả ở đây (có test tests/js/SeoLengthHint.test.tsx).
 */

export const SEO_BRAND = 'BỐP CAMPING';

/** Google hiện khoảng 50–60 ký tự title trên SERP, dài hơn bị cắt "…". */
export const TITLE_MAX = 60;

/** Meta description nên 120–160 ký tự; server cắt ở 155 (SeoService::plainText). */
export const DESC_MIN = 120;
export const DESC_MAX = 155;

/** Bỏ dấu + hạ chữ thường, tương đương Str::lower(Str::ascii()) bên PHP. */
function normalize(s: string): string {
    return s
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLowerCase();
}

export function productTitle(name: string): string {
    return `${name.trim()} — Thuê tại ${SEO_BRAND}`;
}

export function comboTitle(name: string): string {
    return `${name.trim()} — Thuê trọn bộ tại ${SEO_BRAND}`;
}

export function staticPageTitle(title: string): string {
    const t = title.trim();
    if (t === '') return SEO_BRAND;
    return normalize(t).includes(normalize(SEO_BRAND))
        ? t
        : `${t} | ${SEO_BRAND}`;
}

/** Văn bản thuần như meta description sẽ thấy: gỡ thẻ HTML, gộp khoảng trắng. */
export function plainText(html: string): string {
    return html
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/** Đếm theo ký tự Unicode (giống mb_strlen), không theo UTF-16 code unit. */
export function charCount(s: string): number {
    return Array.from(s).length;
}
