// Đồng ý cookie (bopcamping-vqwr) — chế độ CHẶN HẲN.
//
// Đây là nơi DUY NHẤT nạp GA4 / GTM / Meta Pixel / TikTok Pixel. Blade không in script
// đo lường nữa: in thẳng thì cookie được đặt ngay khi mở trang, trước khi khách kịp đồng ý
// (Luật BVDLCN 2025 / NĐ 13/2023: im lặng không phải đồng ý). Chưa bấm "Đồng ý" = không
// nạp gì, không gửi gì ra ngoài.
//
// lib/analytics.ts (bắn sự kiện xem thiết bị / thêm giỏ / đặt đơn) chỉ gọi gtag/fbq/ttq khi
// chúng đã tồn tại — nên chưa đồng ý thì mọi trackXxx() tự bỏ qua, không cần sửa ở đó.

import type { AnalyticsIds } from '@/types';

export const CONSENT_KEY = 'bop_cookie_consent';
/** Tăng khi đổi nội dung xin phép đến mức phải hỏi lại tất cả mọi người. */
const CONSENT_VERSION = 1;
/** Hỏi lại sau 12 tháng — lựa chọn cũ không nên có hiệu lực mãi. */
const MAX_AGE_MS = 365 * 24 * 60 * 60 * 1000;

/**
 * Phạm vi khách được xin phép: chỉ phân tích (GA4), hay cả quảng cáo (Meta/TikTok Pixel,
 * GTM — container GTM chứa thẻ gì thì code không biết, nên coi như có quảng cáo).
 */
export type ConsentScope = 'analytics' | 'ads';

export function consentScope(ids: AnalyticsIds): ConsentScope {
    return ids.meta_pixel_id || ids.tiktok_pixel_id || ids.gtm_id
        ? 'ads'
        : 'analytics';
}

/** `analytics` = khách bấm Đồng ý; `ads` = lần đồng ý đó có bao gồm quảng cáo không. */
type Stored = { v: number; analytics: boolean; ads?: boolean; at: number };

/**
 * null = chưa chọn / đã hết hạn / dữ liệu hỏng / localStorage bị chặn → phải hỏi.
 * Bị chặn thì mỗi lần tải lại trang sẽ hỏi lại; trong một lần xem, banner nằm ở layout
 * bền (SiteLayout) nên bấm xong không bật lại khi chuyển trang.
 *
 * Khách đồng ý lúc shop chỉ có GA, nay shop bật pixel quảng cáo → lần đồng ý cũ không
 * bao gồm quảng cáo, phải hỏi lại. Đã từ chối thì giữ nguyên từ chối.
 */
export function readConsent(scope: ConsentScope): boolean | null {
    try {
        const raw = localStorage.getItem(CONSENT_KEY);
        if (!raw) return null;
        const c = JSON.parse(raw) as Partial<Stored>;
        if (
            c.v !== CONSENT_VERSION ||
            typeof c.analytics !== 'boolean' ||
            typeof c.at !== 'number' ||
            Date.now() - c.at > MAX_AGE_MS
        ) {
            return null;
        }
        if (c.analytics && scope === 'ads' && c.ads !== true) return null;
        return c.analytics;
    } catch {
        return null;
    }
}

export function saveConsent(granted: boolean, scope: ConsentScope): void {
    try {
        const value: Stored = {
            v: CONSENT_VERSION,
            analytics: granted,
            ads: granted && scope === 'ads',
            at: Date.now(),
        };
        localStorage.setItem(CONSENT_KEY, JSON.stringify(value));
    } catch {
        // Bị chặn lưu: lần tải trang sau hỏi lại.
    }
}

/* ---------------- Snippet chính thức, viết lại có kiểu ---------------- */

type Fn = (...args: unknown[]) => void;

type FbqStub = Fn & {
    callMethod?: Fn;
    queue: unknown[];
    push: unknown;
    loaded: boolean;
    version: string;
    disablePushState?: boolean;
};

type TtqStub = unknown[] & {
    methods: string[];
    instance: (id: string) => unknown[];
    load: (id: string, opts?: Record<string, unknown>) => void;
    page: Fn;
    grantConsent: Fn;
    revokeConsent: Fn;
    _i: Record<string, unknown[] & { _u?: string }>;
    _t: Record<string, number>;
    _o: Record<string, Record<string, unknown>>;
};

type TrackingWindow = Window & {
    dataLayer?: unknown[];
    gtag?: Fn;
    fbq?: FbqStub;
    _fbq?: FbqStub;
    ttq?: TtqStub;
    TiktokAnalyticsObject?: string;
};

const win = () => window as TrackingWindow;

/**
 * `function gtag(){dataLayer.push(arguments)}` của Google — GA đọc đúng object
 * `arguments` chứ không phải mảng. Chỉ gán vào window.gtag khi có GA4: analytics.ts thấy
 * window.gtag là bắn sự kiện GA, chỉ có GTM mà gán thì thành đẩy thừa vào dataLayer.
 */
function pushToDataLayer(..._args: unknown[]): void {
    const w = win();
    w.dataLayer = w.dataLayer || [];
    // eslint-disable-next-line prefer-rest-params
    w.dataLayer.push(arguments);
}

function injectScript(id: string, src: string): void {
    if (document.getElementById(id)) return;
    const s = document.createElement('script');
    s.id = id;
    s.async = true;
    s.src = src;
    document.head.appendChild(s);
}

/** Đã khai `consent default` trong dataLayer này chưa (Consent Mode chỉ khai một lần). */
function consentDeclared(dataLayer: unknown[]): boolean {
    return dataLayer.some((e) => {
        const call = e as ArrayLike<unknown> | null;
        return call?.[0] === 'consent' && call?.[1] === 'default';
    });
}

function googleConsent(granted: boolean, ads: boolean) {
    const ad = granted && ads ? 'granted' : 'denied';
    return {
        analytics_storage: granted ? 'granted' : 'denied',
        ad_storage: ad,
        ad_user_data: ad,
        ad_personalization: ad,
    };
}

function loadGoogle(ga: string | null, gtm: string | null, ads: boolean) {
    if (!ga && !gtm) return;
    const w = win();
    if (ga) {
        Reflect.deleteProperty(w, `ga-disable-${ga}`);
        w.gtag = w.gtag || pushToDataLayer;
    }

    // Consent Mode v2 phải đi trước config. Lần sau trong cùng lần xem = khách rút lại
    // rồi đồng ý lại → update.
    pushToDataLayer(
        'consent',
        consentDeclared(w.dataLayer ?? []) ? 'update' : 'default',
        googleConsent(true, ads),
    );

    if (gtm && !document.getElementById('bop-gtm')) {
        w.dataLayer!.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
        injectScript(
            'bop-gtm',
            `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(gtm)}`,
        );
    }

    // Page view khi chuyển trang SPA do Enhanced measurement của GA4 tự bắt (theo lịch sử
    // trình duyệt) — analytics.ts cố ý KHÔNG gửi thêm để khỏi đếm đôi.
    if (ga && !document.getElementById('bop-ga4')) {
        pushToDataLayer('js', new Date());
        pushToDataLayer('config', ga);
        injectScript(
            'bop-ga4',
            `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(ga)}`,
        );
    }
}

/** Meta Pixel. disablePushState: analytics.ts tự gửi PageView mỗi lần Inertia đổi URL. */
function loadMetaPixel(id: string): void {
    const w = win();
    if (w.fbq) {
        w.fbq('consent', 'grant');
        return;
    }
    const fbq = function (..._args: unknown[]) {
        // eslint-disable-next-line prefer-rest-params
        const args = arguments;
        // Gọi dạng method → this = fbq, như callMethod.apply(n, arguments) của snippet gốc.
        if (fbq.callMethod) fbq.callMethod(...Array.from(args));
        else fbq.queue.push(args);
    } as FbqStub;
    fbq.push = fbq;
    fbq.loaded = true;
    fbq.version = '2.0';
    fbq.queue = [];
    fbq.disablePushState = true;
    w.fbq = fbq;
    if (!w._fbq) w._fbq = fbq;
    injectScript('bop-meta', 'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', id);
    fbq('track', 'PageView');
}

const TTQ_METHODS = [
    'page',
    'track',
    'identify',
    'instances',
    'debug',
    'on',
    'off',
    'once',
    'ready',
    'alias',
    'group',
    'enableCookie',
    'disableCookie',
    'holdConsent',
    'revokeConsent',
    'grantConsent',
];

/** Mỗi method gọi trước khi SDK tải xong được xếp hàng dạng [tên, ...tham số]. */
function deferMethods(target: unknown[]): void {
    for (const m of TTQ_METHODS) {
        Reflect.set(target, m, (...args: unknown[]) =>
            target.push([m, ...args]),
        );
    }
}

/**
 * TikTok Pixel. Không gọi ttq.page() khi đổi trang SPA: pixel tự đo pageview mỗi khi URL
 * đổi — page() ở đây chỉ cho lần nạp đầu.
 */
function loadTiktokPixel(id: string): void {
    const w = win();
    if (w.ttq) {
        w.ttq.grantConsent();
        return;
    }
    const src = 'https://analytics.tiktok.com/i18n/pixel/events.js';
    const ttq = [] as unknown as TtqStub;
    w.TiktokAnalyticsObject = 'ttq';
    w.ttq = ttq;
    ttq.methods = TTQ_METHODS;
    deferMethods(ttq);
    ttq.instance = (sdkId) => {
        const queue = ttq._i[sdkId] || [];
        deferMethods(queue);
        return queue;
    };
    ttq.load = (sdkId, opts) => {
        ttq._i = ttq._i || {};
        ttq._i[sdkId] = Object.assign([], { _u: src });
        ttq._t = ttq._t || {};
        ttq._t[sdkId] = Date.now();
        ttq._o = ttq._o || {};
        ttq._o[sdkId] = opts || {};
        injectScript(
            'bop-tiktok',
            `${src}?sdkid=${encodeURIComponent(sdkId)}&lib=ttq`,
        );
    };
    ttq.load(id);
    ttq.page();
}

/* ---------------- API ---------------- */

/** Nạp mọi công cụ đã cấu hình — CHỈ gọi sau khi khách đã đồng ý. Gọi lại không nạp đôi. */
export function loadAnalytics(ids: AnalyticsIds): void {
    loadGoogle(ids.ga_id, ids.gtm_id, consentScope(ids) === 'ads');
    if (ids.meta_pixel_id) loadMetaPixel(ids.meta_pixel_id);
    if (ids.tiktok_pixel_id) loadTiktokPixel(ids.tiktok_pixel_id);
}

/**
 * Rút lại đồng ý: các công cụ đã nạp ngừng gửi ngay trong lần xem này và cookie chúng đặt
 * trên website bị xoá. Script đã nạp thì không gỡ được — lần tải trang sau sẽ không nạp nữa.
 */
export function disableAnalytics(ids: AnalyticsIds): void {
    const w = win();
    if (ids.ga_id) Reflect.set(w, `ga-disable-${ids.ga_id}`, true);
    if (w.dataLayer) {
        pushToDataLayer('consent', 'update', googleConsent(false, true));
    }
    if (w.fbq) w.fbq('consent', 'revoke');
    if (w.ttq) w.ttq.revokeConsent();
    deleteTrackingCookies();
}

/**
 * Cookie bên thứ nhất của các công cụ: GA (_ga, _ga_<id>, _gid, _gat*), Meta (_fbp, _fbc),
 * TikTok (_ttp, _tt_enable_cookie). Chúng đặt ở domain cao nhất (vd .bopcamping.com) nên
 * xoá ở mọi cấp domain. Cookie trên domain của chính Google/Meta/TikTok thì không với tới.
 */
function deleteTrackingCookies(): void {
    const names = document.cookie
        .split(';')
        .map((c) => c.split('=')[0].trim())
        .filter((n) =>
            /^(_ga(_|$)|_gid$|_gat|_fbp$|_fbc$|_ttp$|_tt_enable_cookie$)/.test(
                n,
            ),
        );

    const parts = location.hostname.split('.');
    const domains = [''];
    for (let i = 0; i < parts.length - 1; i++) {
        domains.push(`; domain=.${parts.slice(i).join('.')}`);
    }

    for (const name of names) {
        for (const domain of domains) {
            document.cookie = `${name}=; Max-Age=0; path=/${domain}`;
        }
    }
}
