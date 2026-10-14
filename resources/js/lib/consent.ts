// Đồng ý cookie phân tích (bopcamping-vqwr) — chế độ CHẶN HẲN.
//
// Đây là nơi DUY NHẤT nạp GA4/GTM. Blade không in script phân tích nữa: in thẳng thì
// cookie _ga được đặt ngay khi mở trang, trước khi khách kịp đồng ý (Luật BVDLCN
// 2025 / NĐ 13/2023: im lặng không phải đồng ý). Chưa bấm "Đồng ý" = không nạp gì,
// không gửi gì tới Google.

import type { AnalyticsIds } from '@/types';

export const CONSENT_KEY = 'bop_cookie_consent';
/** Tăng khi đổi nội dung xin phép (vd thêm loại cookie mới) để hỏi lại mọi người. */
const CONSENT_VERSION = 1;
/** Hỏi lại sau 12 tháng — lựa chọn cũ không nên có hiệu lực mãi. */
const MAX_AGE_MS = 365 * 24 * 60 * 60 * 1000;

type Stored = { v: number; analytics: boolean; at: number };

/**
 * null = chưa chọn / đã hết hạn / dữ liệu hỏng / localStorage bị chặn → phải hỏi.
 * Bị chặn thì mỗi lần tải lại trang sẽ hỏi lại; trong một lần xem, banner nằm ở layout
 * bền (SiteLayout) nên bấm xong không bật lại khi chuyển trang.
 */
export function readConsent(): boolean | null {
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
        return c.analytics;
    } catch {
        return null;
    }
}

export function saveConsent(analytics: boolean): void {
    try {
        const value: Stored = {
            v: CONSENT_VERSION,
            analytics,
            at: Date.now(),
        };
        localStorage.setItem(CONSENT_KEY, JSON.stringify(value));
    } catch {
        // Bị chặn lưu: lần tải trang sau hỏi lại.
    }
}

type GtagWindow = Window & {
    dataLayer?: unknown[];
    gtag?: (...args: unknown[]) => void;
};

function gtag(...args: unknown[]): void {
    const w = window as GtagWindow;
    w.dataLayer = w.dataLayer || [];
    if (!w.gtag) {
        // GA đọc đúng object `arguments` chứ không phải mảng — giữ nguyên snippet gốc.
        w.gtag = function () {
            // eslint-disable-next-line prefer-rest-params
            w.dataLayer!.push(arguments);
        };
    }
    w.gtag(...args);
}

/** Đã khai `consent default` trong dataLayer này chưa (Consent Mode chỉ khai một lần). */
function consentDeclared(dataLayer: unknown[]): boolean {
    return dataLayer.some((e) => {
        const call = e as ArrayLike<unknown> | null;
        return call?.[0] === 'consent' && call?.[1] === 'default';
    });
}

function injectScript(id: string, src: string): void {
    if (document.getElementById(id)) return;
    const s = document.createElement('script');
    s.id = id;
    s.async = true;
    s.src = src;
    document.head.appendChild(s);
}

/** Nạp GA4/GTM — CHỈ gọi sau khi khách đã đồng ý. Gọi nhiều lần cũng chỉ nạp một lần. */
export function loadAnalytics({ ga_id, gtm_id }: AnalyticsIds): void {
    const w = window as GtagWindow;
    if (ga_id) Reflect.deleteProperty(w, `ga-disable-${ga_id}`);

    if (!consentDeclared(w.dataLayer ?? [])) {
        // Khách mới đồng ý phân tích nên quảng cáo vẫn tắt.
        gtag('consent', 'default', {
            analytics_storage: 'granted',
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied',
        });
    } else {
        // Đã từng rút lại trong lần xem này rồi đồng ý lại.
        gtag('consent', 'update', { analytics_storage: 'granted' });
    }

    if (gtm_id && !document.getElementById('bop-gtm')) {
        w.dataLayer!.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
        injectScript(
            'bop-gtm',
            `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(gtm_id)}`,
        );
    }

    if (ga_id && !document.getElementById('bop-ga4')) {
        gtag('js', new Date());
        gtag('config', ga_id);
        injectScript(
            'bop-ga4',
            `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(ga_id)}`,
        );
    }
}

/**
 * Rút lại đồng ý: chặn GA gửi tiếp ngay trong lần xem này và xoá cookie GA đã đặt.
 * Script đã nạp thì không gỡ được — lần tải trang sau sẽ không nạp nữa.
 */
export function disableAnalytics({ ga_id }: AnalyticsIds): void {
    const w = window as GtagWindow;
    if (ga_id) Reflect.set(w, `ga-disable-${ga_id}`, true);
    if (w.dataLayer) {
        gtag('consent', 'update', { analytics_storage: 'denied' });
    }
    deleteGaCookies();
}

/** _ga, _ga_<container>, _gid, _gat* — GA đặt ở domain cao nhất (vd .bopcamping.com). */
function deleteGaCookies(): void {
    const names = document.cookie
        .split(';')
        .map((c) => c.split('=')[0].trim())
        .filter((n) => /^(_ga(_|$)|_gid$|_gat)/.test(n));

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
