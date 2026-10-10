/**
 * Đo lường marketing (bopcamping-wi6h) — NGUỒN DUY NHẤT bắn sự kiện ra GA4 / GTM /
 * Meta Pixel / TikTok Pixel. Trang chỉ gọi trackXxx(), không tự gọi gtag/fbq/ttq.
 *
 * Nguyên tắc:
 * - Mỗi nền tảng chỉ bắn khi script của nó đã được blade chèn (admin điền mã). Thiếu → bỏ qua.
 * - KHÔNG BAO GIỜ ném lỗi: đo lường hỏng không được làm hỏng nút "Thêm vào giỏ" hay
 *   "Đặt đơn". Mọi lời gọi ra ngoài đều bọc try/catch.
 * - KHÔNG gửi PII (tên, SĐT, email, địa chỉ) cho nền tảng quảng cáo. Chỉ id/tên thiết bị,
 *   giá, số lượng, mã đơn.
 * - Không đo trang /admin và /shipper (blade cũng không chèn script ở đó).
 *
 * Thuê theo ngày: `price` gửi cho GA4/pixel = giá 1 đơn vị CHO CẢ KỲ THUÊ (giá/ngày × số
 * ngày), để value = Σ price × quantity khớp tiền thuê thật. Giá/ngày và số ngày vẫn đi kèm
 * dưới dạng tham số riêng (price_per_day, rental_days) để lọc trong báo cáo.
 */
import type { CartLine } from './cart';
import { dayCount } from './format';

export const CURRENCY = 'VND';

export type AnalyticsItem = {
    id: string;
    name: string;
    category?: string;
    pricePerDay: number;
    quantity: number;
    /** Số ngày thuê; chưa chọn lịch (vd lúc xem trang) = 1. */
    days?: number;
};

export type ContactMethod = 'zalo' | 'phone' | 'messenger';

type Gtag = (...args: unknown[]) => void;
type Fbq = ((...args: unknown[]) => void) & { disablePushState?: boolean };
type Ttq = {
    track?: (event: string, params?: Record<string, unknown>) => void;
    page?: () => void;
};

type TrackingWindow = Window & {
    gtag?: Gtag;
    dataLayer?: unknown[];
    fbq?: Fbq;
    ttq?: Ttq;
};

const w = (): TrackingWindow | null =>
    typeof window === 'undefined' ? null : (window as TrackingWindow);

/** Gọi một hàm bên ngoài, nuốt mọi lỗi (script bên thứ ba có thể hỏng bất kỳ lúc nào). */
function safe(fn: () => void): void {
    try {
        fn();
    } catch {
        /* đo lường không được làm vỡ trang */
    }
}

/** Trang nội bộ — không đo. */
export function isTrackablePath(pathname: string): boolean {
    return !/^\/(admin|shipper)(\/|$)/.test(pathname);
}

function trackingAllowed(): boolean {
    const win = w();
    return !!win && isTrackablePath(win.location.pathname);
}

/* ---------------- Chuẩn hoá item ---------------- */

const days = (i: AnalyticsItem) => Math.max(1, Math.round(i.days ?? 1));
const unitPrice = (i: AnalyticsItem) => Math.max(0, i.pricePerDay) * days(i);

export function itemsValue(items: AnalyticsItem[]): number {
    return items.reduce((s, i) => s + unitPrice(i) * i.quantity, 0);
}

function gaItem(i: AnalyticsItem, index: number) {
    return {
        item_id: i.id,
        item_name: i.name,
        ...(i.category ? { item_category: i.category } : {}),
        price: unitPrice(i),
        quantity: i.quantity,
        index,
        price_per_day: i.pricePerDay,
        rental_days: days(i),
    };
}

/** Dòng giỏ → item đo lường. Combo dùng id "combo-<id>" để không trùng id sản phẩm. */
export function itemFromCartLine(line: CartLine): AnalyticsItem {
    const isCombo = line.kind === 'combo';
    let d = 1;
    try {
        d = dayCount(line.start, line.end);
    } catch {
        d = 1;
    }
    return {
        id: isCombo ? `combo-${line.id}` : String(line.id),
        name: line.name,
        category: isCombo ? 'combo' : line.cat,
        pricePerDay: line.price,
        quantity: line.qty,
        days: Number.isFinite(d) && d > 0 ? d : 1,
    };
}

/* ---------------- Gửi ra từng nền tảng ---------------- */

type MetaEvent =
    | 'ViewContent'
    | 'AddToCart'
    | 'InitiateCheckout'
    | 'Purchase'
    | 'Contact';
type TiktokEvent =
    | 'ViewContent'
    | 'AddToCart'
    | 'InitiateCheckout'
    | 'CompletePayment'
    | 'Contact';

type Dispatch = {
    ga: string;
    gaParams: Record<string, unknown>;
    meta: MetaEvent;
    metaParams: Record<string, unknown>;
    tiktok: TiktokEvent;
    tiktokParams: Record<string, unknown>;
    /** Purchase cần eventID để Meta khử trùng nếu sau này có Conversions API. */
    metaEventId?: string;
};

function dispatch(d: Dispatch): void {
    if (!trackingAllowed()) return;
    const win = w()!;

    if (typeof win.gtag === 'function') {
        safe(() => win.gtag!('event', d.ga, d.gaParams));
    }
    // GTM: theo chuẩn ecommerce của GA4 — xoá object ecommerce cũ trước khi đẩy cái mới.
    if (Array.isArray(win.dataLayer)) {
        safe(() => {
            const { items, ...rest } = d.gaParams;
            if (items) {
                win.dataLayer!.push({ ecommerce: null });
                win.dataLayer!.push({
                    event: d.ga,
                    ecommerce: { ...rest, items },
                });
            } else {
                win.dataLayer!.push({ event: d.ga, ...rest });
            }
        });
    }
    if (typeof win.fbq === 'function') {
        safe(() =>
            d.metaEventId
                ? win.fbq!('track', d.meta, d.metaParams, {
                      eventID: d.metaEventId,
                  })
                : win.fbq!('track', d.meta, d.metaParams),
        );
    }
    if (win.ttq && typeof win.ttq.track === 'function') {
        safe(() => win.ttq!.track!(d.tiktok, d.tiktokParams));
    }
}

function ecommerce(
    items: AnalyticsItem[],
    value = itemsValue(items),
): {
    ga: Record<string, unknown>;
    meta: Record<string, unknown>;
    tiktok: Record<string, unknown>;
} {
    return {
        ga: { currency: CURRENCY, value, items: items.map(gaItem) },
        meta: {
            currency: CURRENCY,
            value,
            content_type: 'product',
            content_ids: items.map((i) => i.id),
            contents: items.map((i) => ({
                id: i.id,
                quantity: i.quantity,
                item_price: unitPrice(i),
            })),
            num_items: items.reduce((s, i) => s + i.quantity, 0),
            ...(items.length === 1 ? { content_name: items[0].name } : {}),
        },
        tiktok: {
            currency: CURRENCY,
            value,
            content_type: 'product',
            contents: items.map((i) => ({
                content_id: i.id,
                content_name: i.name,
                quantity: i.quantity,
                price: unitPrice(i),
            })),
        },
    };
}

/* ---------------- API công khai ---------------- */

export function trackViewItem(item: AnalyticsItem): void {
    const e = ecommerce([item]);
    dispatch({
        ga: 'view_item',
        gaParams: e.ga,
        meta: 'ViewContent',
        metaParams: e.meta,
        tiktok: 'ViewContent',
        tiktokParams: e.tiktok,
    });
}

export function trackAddToCart(item: AnalyticsItem): void {
    const e = ecommerce([item]);
    dispatch({
        ga: 'add_to_cart',
        gaParams: e.ga,
        meta: 'AddToCart',
        metaParams: e.meta,
        tiktok: 'AddToCart',
        tiktokParams: e.tiktok,
    });
}

export function trackBeginCheckout(items: AnalyticsItem[]): void {
    if (items.length === 0) return;
    const e = ecommerce(items);
    dispatch({
        ga: 'begin_checkout',
        gaParams: e.ga,
        meta: 'InitiateCheckout',
        metaParams: e.meta,
        tiktok: 'InitiateCheckout',
        tiktokParams: e.tiktok,
    });
}

const PURCHASE_KEY = 'bop_tracked_purchase:';

function alreadyTracked(code: string): boolean {
    try {
        return window.sessionStorage.getItem(PURCHASE_KEY + code) === '1';
    } catch {
        return false;
    }
}

function markTracked(code: string): void {
    try {
        window.sessionStorage.setItem(PURCHASE_KEY + code, '1');
    } catch {
        /* sessionStorage bị chặn (ẩn danh/Safari) — chấp nhận rủi ro đếm lại */
    }
}

/**
 * Đặt đơn thành công. `value` lấy từ SERVER (tiền thuê − giảm giá, không gồm cọc vì cọc
 * hoàn lại) — giá trong giỏ ở máy khách chỉ là ước lượng. Bắn ĐÚNG 1 LẦN mỗi mã đơn,
 * kể cả khi khách reload hay bấm Back quay lại trang cảm ơn.
 *
 * @returns true nếu lần này thực sự bắn (false = đã bắn trước đó / không đo được).
 */
export function trackPurchase(args: {
    transactionId: string;
    value: number;
    items: AnalyticsItem[];
}): boolean {
    const code = args.transactionId.trim();
    if (!code || !trackingAllowed() || alreadyTracked(code)) return false;
    markTracked(code);

    const e = ecommerce(args.items, Math.max(0, Math.round(args.value)));
    dispatch({
        ga: 'purchase',
        gaParams: { transaction_id: code, ...e.ga },
        meta: 'Purchase',
        metaParams: e.meta,
        metaEventId: `order-${code}`,
        tiktok: 'CompletePayment',
        tiktokParams: { ...e.tiktok, order_id: code },
    });
    return true;
}

/** Khách bấm liên hệ (Zalo / gọi điện / Messenger) — chuyển đổi "lead". */
export function trackLead(method: ContactMethod, placement?: string): void {
    const params = {
        method,
        ...(placement ? { placement } : {}),
    };
    dispatch({
        ga: 'generate_lead',
        gaParams: { ...params, currency: CURRENCY, value: 0 },
        meta: 'Contact',
        metaParams: { content_name: method },
        tiktok: 'Contact',
        tiktokParams: { content_name: method },
    });
}

/** Link liên hệ → kênh; null nếu không phải link liên hệ. */
export function contactMethodFromHref(
    href: string | null | undefined,
): ContactMethod | null {
    if (!href) return null;
    const h = href.trim().toLowerCase();
    if (h.startsWith('tel:')) return 'phone';
    if (/^https?:\/\/([a-z0-9-]+\.)*zalo\.me(\/|$)/.test(h)) return 'zalo';
    if (/^https?:\/\/([a-z0-9-]+\.)*(m\.me|messenger\.com)(\/|$)/.test(h))
        return 'messenger';
    return null;
}

/* ---------------- Page view khi điều hướng SPA ---------------- */

let lastUrl: string | null = null;

/**
 * Gọi ở MỌI sự kiện `navigate` của Inertia (kể cả lần tải đầu).
 *
 * - Lần đầu: chỉ ghi nhận URL, KHÔNG bắn — script gốc trong blade đã tự gửi page view
 *   của lần tải trang (gtag config / fbq PageView / ttq.page()).
 * - Cùng URL (vd submit form rồi quay về chính trang đó): bỏ qua.
 * - GA4: KHÔNG gửi page_view tay. Enhanced measurement của GA4 (mặc định BẬT "thay đổi
 *   trang theo lịch sử trình duyệt") đã tự bắt pushState của Inertia — gửi thêm là đếm đôi.
 * - Meta: blade tắt fbq.disablePushState để ta chủ động gửi PageView tại đây.
 * - TikTok: ttq.page().
 * - GTM: đẩy `virtual_page_view` để ai dùng GTM tự gắn trigger.
 */
export function trackSpaNavigation(url: string): void {
    const win = w();
    if (!win) return;
    let key = url;
    try {
        const u = new URL(url, win.location.origin);
        key = u.pathname + u.search;
    } catch {
        /* giữ nguyên chuỗi */
    }
    const first = lastUrl === null;
    const same = key === lastUrl;
    lastUrl = key;
    if (first || same) return;
    if (!isTrackablePath(key.split('?')[0])) return;

    if (Array.isArray(win.dataLayer)) {
        safe(() =>
            win.dataLayer!.push({ event: 'virtual_page_view', page_path: key }),
        );
    }
    if (typeof win.fbq === 'function') {
        safe(() => win.fbq!('track', 'PageView'));
    }
    if (win.ttq && typeof win.ttq.page === 'function') {
        safe(() => win.ttq!.page!());
    }
}

/** Chỉ dùng cho test: quên URL đã thấy. */
export function resetNavigationState(): void {
    lastUrl = null;
}

/** Bắt click link liên hệ ở bất kỳ đâu (nút Zalo nổi, footer, trang chi tiết…). */
export function handleDocumentClick(e: Event): void {
    const target = e.target as Element | null;
    if (!target || typeof target.closest !== 'function') return;
    const a = target.closest('a[href]');
    if (!a) return;
    const method = contactMethodFromHref(a.getAttribute('href'));
    if (!method) return;
    const placement =
        a
            .closest('[data-track-placement]')
            ?.getAttribute('data-track-placement') ?? undefined;
    trackLead(method, placement);
}

/**
 * Khởi động đo lường 1 lần cho cả app (app.tsx). `onNavigate` là router.on('navigate')
 * của Inertia — truyền vào thay vì import để module này không phụ thuộc Inertia.
 */
export function initAnalytics(
    onNavigate: (cb: (url: string) => void) => void,
): void {
    if (typeof document === 'undefined') return;
    safe(() => onNavigate((url) => trackSpaNavigation(url)));
    document.addEventListener('click', handleDocumentClick, { capture: true });
}
