import { EVENTS, on } from '@/lib/bus';
import {
    disableAnalytics,
    loadAnalytics,
    readConsent,
    saveConsent,
} from '@/lib/consent';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const CHOICE_BUTTON =
    'h-10 rounded-control border border-pine bg-white text-[14px] font-bold text-pine transition hover:bg-pine hover:text-white';

/**
 * Banner đồng ý cookie phân tích (bopcamping-vqwr) — chế độ CHẶN HẲN: chưa bấm
 * "Đồng ý" thì không nạp GA4/GTM (xem lib/consent.ts). Chỉ hiện khi shop có cấu hình
 * công cụ phân tích; cookie thiết yếu (phiên, giỏ hàng, nhớ đăng nhập) không cần hỏi.
 *
 * Vị trí: desktop là thẻ nhỏ góc dưới TRÁI — bên phải đã có nút Zalo + Góp ý (z-80).
 * Mobile trải ngang đáy, nằm trên các nút nổi đó cho tới khi khách chọn, dưới modal
 * (z-160). Footer có nút "Cài đặt cookie" mở lại để đổi ý.
 */
export default function CookieConsent() {
    const { analytics } = usePage<PageProps>().props;
    const gaId = analytics?.ga_id ?? null;
    const gtmId = analytics?.gtm_id ?? null;
    const [open, setOpen] = useState(false);
    const reopened = useRef(false);
    const panel = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!gaId && !gtmId) return;
        const choice = readConsent();
        if (choice === null) setOpen(true);
        else if (choice) loadAnalytics({ ga_id: gaId, gtm_id: gtmId });
    }, [gaId, gtmId]);

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

    if (!open || (!gaId && !gtmId)) return null;

    const decide = (granted: boolean) => {
        const ids = { ga_id: gaId, gtm_id: gtmId };
        saveConsent(granted);
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
            className="fixed inset-x-0 bottom-0 z-[90] border-t border-cardBorder bg-card px-5 pb-[max(16px,env(safe-area-inset-bottom))] pt-4 shadow-toast outline-none sm:bottom-5 sm:left-5 sm:right-auto sm:w-[380px] sm:rounded-card sm:border sm:pb-4"
        >
            <h2
                id="cookie-consent-title"
                className="m-0 text-[15px] font-extrabold text-pine"
            >
                Bốp xin phép dùng cookie
            </h2>
            <p
                id="cookie-consent-desc"
                className="mb-3.5 mt-1.5 text-[13.5px] leading-[1.55] text-moss"
            >
                {/* Câu thứ hai trấn an khách: từ chối không mất gì (giỏ hàng, đăng
                    nhập dùng cookie thiết yếu, không cần xin phép). */}
                Tụi mình muốn dùng cookie của Google Analytics để biết trang nào
                hữu ích và làm web tốt hơn. Bạn từ chối vẫn xem và thuê đồ bình
                thường. Đổi ý lúc nào cũng được ở mục “Cài đặt cookie” cuối
                trang.{' '}
                <Link
                    href="/chinh-sach-bao-mat"
                    className="font-semibold text-grass underline underline-offset-2 hover:text-pine"
                >
                    Chính sách bảo mật
                </Link>
            </p>
            {/* Hai nút GIỐNG HỆT nhau (cỡ, màu, thứ tự đọc) — tô đậm riêng "Đồng ý" là
                dark pattern mà hướng dẫn GDPR/CookieYes cấm: lựa chọn phải tự do. */}
            <div className="grid grid-cols-2 gap-2.5">
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
