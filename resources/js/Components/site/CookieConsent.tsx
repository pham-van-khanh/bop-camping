import { EVENTS, on } from '@/lib/bus';
import {
    consentScope,
    disableAnalytics,
    loadAnalytics,
    readConsent,
    saveConsent,
} from '@/lib/consent';
import type { AnalyticsIds, PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const CHOICE_BUTTON =
    'h-10 rounded-control border border-pine bg-white text-[14px] font-bold text-pine transition hover:bg-pine hover:text-white';

/**
 * Banner đồng ý cookie (bopcamping-vqwr) — chế độ CHẶN HẲN: chưa bấm "Đồng ý" thì không
 * nạp GA4/GTM/Meta Pixel/TikTok Pixel (xem lib/consent.ts). Chỉ hiện khi shop có bật công
 * cụ nào đó; cookie thiết yếu (phiên, giỏ hàng, nhớ đăng nhập) không cần hỏi.
 *
 * Vị trí: desktop là thẻ nhỏ góc dưới TRÁI — bên phải đã có nút Zalo + Góp ý (z-80).
 * Mobile trải ngang đáy, nằm trên các nút nổi đó cho tới khi khách chọn, dưới modal
 * (z-160). Footer có nút "Cài đặt cookie" mở lại để đổi ý.
 */
export default function CookieConsent() {
    const { analytics } = usePage<PageProps>().props;
    const gaId = analytics?.ga_id ?? null;
    const gtmId = analytics?.gtm_id ?? null;
    const metaId = analytics?.meta_pixel_id ?? null;
    const tiktokId = analytics?.tiktok_pixel_id ?? null;
    const [open, setOpen] = useState(false);
    const reopened = useRef(false);
    const panel = useRef<HTMLDivElement>(null);

    // Phụ thuộc vào từng mã (chuỗi) chứ không vào object `analytics` — Inertia tạo object
    // mới mỗi lần chuyển trang.
    const enabled = !!(gaId || gtmId || metaId || tiktokId);
    useEffect(() => {
        if (!enabled) return;
        const ids: AnalyticsIds = {
            ga_id: gaId,
            gtm_id: gtmId,
            meta_pixel_id: metaId,
            tiktok_pixel_id: tiktokId,
        };
        const choice = readConsent(consentScope(ids));
        if (choice === null) setOpen(true);
        else if (choice) loadAnalytics(ids);
    }, [enabled, gaId, gtmId, metaId, tiktokId]);

    useEffect(
        () =>
            on(EVENTS.openCookieSettings, () => {
                reopened.current = true;
                setOpen(true);
            }),
        [],
    );

    // Mở lại từ footer: banner hiện tận đáy màn hình, đưa focus tới để người dùng
    // bàn phím/đọc màn hình không phải đi tìm. Lần đầu tự hiện thì không giật focus.
    useEffect(() => {
        if (open && reopened.current) panel.current?.focus();
    }, [open]);

    if (!open || !enabled) return null;

    const ids: AnalyticsIds = {
        ga_id: gaId,
        gtm_id: gtmId,
        meta_pixel_id: metaId,
        tiktok_pixel_id: tiktokId,
    };
    const scope = consentScope(ids);

    const decide = (granted: boolean) => {
        saveConsent(granted, scope);
        if (granted) loadAnalytics(ids);
        else disableAnalytics(ids);
        reopened.current = false;
        setOpen(false);
    };

    return (
        <div
            ref={panel}
            role="dialog"
            aria-labelledby="cookie-consent-title"
            aria-describedby="cookie-consent-desc"
            tabIndex={-1}
            className="fixed inset-x-0 bottom-0 z-[90] border-t border-cardBorder bg-card px-5 pb-[max(14px,env(safe-area-inset-bottom))] pt-3.5 shadow-toast outline-none sm:bottom-5 sm:left-5 sm:right-auto sm:w-[400px] sm:rounded-card sm:border sm:pb-3.5"
        >
            {/* Cấu trúc theo banner adidas (chủ shop chọn) nhưng viết đúng việc Bốp làm, và
                câu chữ đổi theo công cụ đang bật: chỉ GA → "phân tích", có pixel/GTM → nói
                rõ "quảng cáo". Không nêu tên nền tảng (chủ shop yêu cầu bỏ phần Google);
                tên công cụ + danh sách cookie nằm ở Chính sách bảo mật. Tránh chữ "theo dõi
                bạn" của bản gốc vì dễ làm khách lo. */}
            <div className="flex items-center gap-2.5">
                <CookieIcon />
                <h2
                    id="cookie-consent-title"
                    className="m-0 text-[15px] font-extrabold leading-snug text-pine"
                >
                    Cookie giúp bạn có trải nghiệm tốt hơn trên Bốp Camping
                </h2>
            </div>
            <p
                id="cookie-consent-desc"
                className="mb-0 mt-2 text-[13px] leading-[1.55] text-moss"
            >
                {scope === 'ads' ? (
                    <>
                        Bằng cách chọn “Đồng ý”, bạn cho phép Bốp dùng cookie
                        phân tích và quảng cáo để ghi nhận cách bạn xem website
                        và chia sẻ với các nền tảng quảng cáo (không kèm tên, số
                        điện thoại hay địa chỉ), nhằm cải thiện trang web và
                        giới thiệu đồ thuê phù hợp với bạn.
                    </>
                ) : (
                    <>
                        Bằng cách chọn “Đồng ý”, bạn cho phép Bốp dùng cookie
                        phân tích để ghi nhận cách bạn xem website, như trang
                        nào được xem và xem bao lâu, nhằm cải thiện trang web.
                        Bốp không dùng cookie quảng cáo và không bán dữ liệu của
                        bạn.
                    </>
                )}{' '}
                Chọn “Từ chối” thì bạn vẫn xem và thuê đồ bình thường. Muốn tìm
                hiểu thêm? Xem{' '}
                <Link
                    href="/chinh-sach-bao-mat"
                    className="font-semibold text-grass underline underline-offset-2 hover:text-pine"
                >
                    Chính sách bảo mật
                </Link>
                .
            </p>
            {/* Hai nút GIỐNG HỆT nhau (cỡ, màu, thứ tự đọc) — tô đậm riêng "Đồng ý" là
                dark pattern mà hướng dẫn GDPR/CookieYes cấm: lựa chọn phải tự do. */}
            <div className="mt-3 grid grid-cols-2 gap-2.5">
                <button
                    type="button"
                    onClick={() => decide(false)}
                    className={CHOICE_BUTTON}
                >
                    Từ chối
                </button>
                <button
                    type="button"
                    onClick={() => decide(true)}
                    className={CHOICE_BUTTON}
                >
                    Đồng ý
                </button>
            </div>
        </div>
    );
}

/** Bánh cookie bị cắn một miếng — tông đất cho hợp theme be Naturehike. */
function CookieIcon() {
    return (
        <svg
            viewBox="0 0 32 32"
            width={30}
            height={30}
            aria-hidden="true"
            className="shrink-0"
        >
            <path
                d="M16 3a13 13 0 1 0 13 13 4 4 0 0 1-4.5-4.5 4 4 0 0 1-4.5-4.5A4 4 0 0 1 16 3Z"
                fill="#D9A35F"
            />
            <circle cx="11" cy="13" r="1.8" fill="#6B4423" />
            <circle cx="17.5" cy="19.5" r="1.8" fill="#6B4423" />
            <circle cx="10.5" cy="21.5" r="1.4" fill="#6B4423" />
            <circle cx="23" cy="23" r="1.3" fill="#6B4423" />
        </svg>
    );
}
