import type { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Icon Zalo (public/images/zalo-icon.png — nền trong suốt, đã sẵn khối bo góc + đuôi bong bóng).
 * alt rỗng vì link bọc ngoài đã có aria-label mô tả rồi.
 */
function ZaloMark({ size = 48 }: { size?: number }) {
    return (
        <img
            src="/images/zalo-icon.png"
            width={size}
            height={size}
            alt=""
            className="flex-none"
        />
    );
}

/**
 * Nút Zalo nổi, nằm ngay TRÊN nút Góp ý (FeedbackWidget: bottom-5 + h-12 = chiếm 20→68px,
 * nút này ở 80→128px nên hở 12px).
 *
 * Chỉ mở Zalo OA (bopcamping-h0hh) — một đích duy nhất nên không còn panel chọn tài
 * khoản như trước. Muốn nhắn theo SỐ nhân viên thì xuống footer, ở đó liệt kê từng số.
 */
export default function ZaloFloatButton() {
    const { site } = usePage<PageProps>().props;
    const url = site?.zalo_oa;

    if (!url) return null;

    return (
        <a
            href={url}
            target="_blank"
            rel="noreferrer"
            aria-label="Liên hệ Zalo"
            title="Nhắn Zalo cho BỐP CAMPING"
            className="fixed bottom-[80px] right-5 z-[80] grid h-12 w-12 place-items-center rounded-[14px] outline-none transition [filter:drop-shadow(0_4px_10px_rgba(0,0,0,.3))] hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-[#0068FF] focus-visible:ring-offset-2"
        >
            <ZaloMark />
        </a>
    );
}
