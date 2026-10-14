import { charCount, DESC_MAX, DESC_MIN, plainText, TITLE_MAX } from '@/lib/seo';

/**
 * Bộ đếm ký tự SEO hiện ngay dưới ô nhập (bopcamping-b4c3). Chỉ GỢI Ý — không tự sửa
 * tên/tiêu đề, admin quyết định rút gọn thế nào.
 *
 * - `kind="title"`: `value` là <title> ĐẦY ĐỦ như Google thấy (đã ghép brand — dùng
 *   productTitle/comboTitle/staticPageTitle trong lib/seo). Cảnh báo khi > 60 ký tự.
 * - `kind="description"`: `value` là mô tả (có thể là HTML); đếm trên văn bản thuần,
 *   khuyên 120–155 ký tự.
 */
export default function SeoLengthHint({
    kind,
    value,
}: {
    kind: 'title' | 'description';
    value: string;
}) {
    const base = 'mt-1 text-[12px] leading-[1.5]';
    const warn = `${base} text-[#b3493a]`;
    const ok = `${base} text-moss`;

    if (kind === 'title') {
        const n = charCount(value);
        const over = n - TITLE_MAX;
        return (
            <p className={over > 0 ? warn : ok} aria-live="polite">
                Tiêu đề trên Google:{' '}
                <span className="font-mono">
                    {n}/{TITLE_MAX}
                </span>{' '}
                ký tự
                {over > 0
                    ? ` — dài quá, Google sẽ cắt bớt. Nên rút gọn khoảng ${over} ký tự.`
                    : ''}
                <span className="block truncate text-[11.5px] text-[#8a967a]">
                    {value}
                </span>
            </p>
        );
    }

    const n = charCount(plainText(value));
    let note: string;
    let bad = false;
    if (n === 0) {
        note = 'Để trống thì Google dùng câu mô tả tự sinh.';
    } else if (n < DESC_MIN) {
        note = `Hơi ngắn — nên ${DESC_MIN}–${DESC_MAX} ký tự để hiện đủ trên Google.`;
        bad = true;
    } else if (n > DESC_MAX) {
        // Mô tả dài là bình thường (đây cũng là nội dung trang) — chỉ nhắc câu đầu.
        note = `Google chỉ lấy ~${DESC_MAX} ký tự đầu — đặt ý chính ở câu đầu.`;
    } else {
        note = 'Độ dài vừa đẹp cho Google.';
    }

    return (
        <p className={bad ? warn : ok} aria-live="polite">
            Mô tả trên Google: <span className="font-mono">{n}</span> ký tự —{' '}
            {note}
        </p>
    );
}
