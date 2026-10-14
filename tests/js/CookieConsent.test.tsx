import CookieConsent from '@/Components/site/CookieConsent';
import { emit, EVENTS } from '@/lib/bus';
import { CONSENT_KEY } from '@/lib/consent';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Banner đồng ý cookie (bopcamping-vqwr) — chế độ CHẶN HẲN: chưa bấm "Đồng ý" thì
 * không nạp GA/GTM/Meta Pixel/TikTok Pixel, không gửi gì ra ngoài. Server không còn in
 * script đo lường (xem CookieConsentTest.php); file này khoá phần front-end quyết định
 * nạp hay không.
 */

type Analytics = {
    ga_id: string | null;
    gtm_id: string | null;
    meta_pixel_id: string | null;
    tiktok_pixel_id: string | null;
};

const state = vi.hoisted(() => ({
    analytics: null as Analytics | null,
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { analytics: state.analytics } }),
    Link: ({
        href,
        children,
        ...rest
    }: {
        href: string;
        children: React.ReactNode;
    }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

const GA = 'G-TEST1234';
const GTM = 'GTM-ABC123';
const META = '123456789012345';
const TIKTOK = 'C4ABC123DEF456GHI789';
const DAY = 864e5;

const ids = (over: Partial<Analytics> = {}): Analytics => ({
    ga_id: GA,
    gtm_id: null,
    meta_pixel_id: null,
    tiktok_pixel_id: null,
    ...over,
});

const gaScripts = () =>
    document.querySelectorAll('script[src*="googletagmanager.com/gtag/js"]');
const gtmScripts = () =>
    document.querySelectorAll('script[src*="googletagmanager.com/gtm.js"]');
const dialog = () => screen.queryByRole('dialog', { name: /cookie/i });

/** dataLayer chứa object `arguments` của gtag() — đổi sang mảng để so. */
function dataLayer(): unknown[][] {
    const dl = (window as unknown as { dataLayer?: unknown[] }).dataLayer ?? [];
    return dl.map((e) =>
        e && typeof e === 'object' && 'length' in e
            ? Array.from(e as ArrayLike<unknown>)
            : [e],
    );
}

function saved(analytics: boolean, ageDays = 0, ads = false) {
    localStorage.setItem(
        CONSENT_KEY,
        JSON.stringify({
            v: 1,
            analytics,
            ads,
            at: Date.now() - ageDays * DAY,
        }),
    );
}

function clearCookies() {
    document.cookie.split(';').forEach((c) => {
        const name = c.split('=')[0].trim();
        if (name) document.cookie = `${name}=; Max-Age=0; path=/`;
    });
}

beforeEach(() => {
    state.analytics = ids();
    localStorage.clear();
    clearCookies();
    document.querySelectorAll('script').forEach((s) => s.remove());
    for (const key of [
        'dataLayer',
        'gtag',
        `ga-disable-${GA}`,
        'fbq',
        '_fbq',
        'ttq',
        'TiktokAnalyticsObject',
    ]) {
        Reflect.deleteProperty(window, key);
    }
});

describe('banner đồng ý cookie', () => {
    it('không render khi shop chưa bật công cụ đo lường nào', () => {
        state.analytics = null;
        const { container } = render(<CookieConsent />);
        expect(container).toBeEmptyDOMElement();
    });

    it('lần đầu vào: hiện banner và CHƯA nạp GA', () => {
        render(<CookieConsent />);
        expect(dialog()).toBeInTheDocument();
        expect(gaScripts()).toHaveLength(0);
        expect(document.cookie).not.toContain('_ga');
    });

    it('bấm Đồng ý: lưu lựa chọn, ẩn banner, nạp GA ngay với Consent Mode', async () => {
        const user = userEvent.setup();
        render(<CookieConsent />);

        await user.click(screen.getByRole('button', { name: 'Đồng ý' }));

        expect(dialog()).not.toBeInTheDocument();
        expect(JSON.parse(localStorage.getItem(CONSENT_KEY)!)).toMatchObject({
            v: 1,
            analytics: true,
        });

        const scripts = gaScripts();
        expect(scripts).toHaveLength(1);
        expect(scripts[0].getAttribute('src')).toBe(
            `https://www.googletagmanager.com/gtag/js?id=${GA}`,
        );

        // Consent Mode v2 phải đi TRƯỚC config: chỉ bật phân tích, quảng cáo vẫn tắt.
        const calls = dataLayer();
        const consentAt = calls.findIndex(
            (c) => c[0] === 'consent' && c[1] === 'default',
        );
        const configAt = calls.findIndex(
            (c) => c[0] === 'config' && c[1] === GA,
        );
        expect(consentAt).toBeGreaterThanOrEqual(0);
        expect(configAt).toBeGreaterThan(consentAt);
        expect(calls[consentAt][2]).toMatchObject({
            analytics_storage: 'granted',
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied',
        });
    });

    it('bấm Từ chối: lưu lựa chọn, ẩn banner, không nạp GA', async () => {
        const user = userEvent.setup();
        render(<CookieConsent />);

        await user.click(screen.getByRole('button', { name: 'Từ chối' }));

        expect(dialog()).not.toBeInTheDocument();
        expect(JSON.parse(localStorage.getItem(CONSENT_KEY)!)).toMatchObject({
            analytics: false,
        });
        expect(gaScripts()).toHaveLength(0);
    });

    it('đã đồng ý từ trước: không hỏi lại, nạp GA luôn', () => {
        saved(true, 30);
        render(<CookieConsent />);
        expect(dialog()).not.toBeInTheDocument();
        expect(gaScripts()).toHaveLength(1);
    });

    it('đã từ chối từ trước: không hỏi lại, không nạp', () => {
        saved(false, 30);
        render(<CookieConsent />);
        expect(dialog()).not.toBeInTheDocument();
        expect(gaScripts()).toHaveLength(0);
    });

    it('lựa chọn đã quá 12 tháng thì hỏi lại, chưa nạp GA', () => {
        saved(true, 366);
        render(<CookieConsent />);
        expect(dialog()).toBeInTheDocument();
        expect(gaScripts()).toHaveLength(0);
    });

    it('dữ liệu lưu bị hỏng thì coi như chưa chọn', () => {
        localStorage.setItem(CONSENT_KEY, '{hỏng');
        render(<CookieConsent />);
        expect(dialog()).toBeInTheDocument();
        expect(gaScripts()).toHaveLength(0);
    });

    it('localStorage bị chặn: vẫn hiện banner, bấm xong vẫn đóng được', async () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('SecurityError');
        });
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('SecurityError');
        });
        const user = userEvent.setup();
        render(<CookieConsent />);

        await user.click(screen.getByRole('button', { name: 'Đồng ý' }));

        expect(dialog()).not.toBeInTheDocument();
        expect(gaScripts()).toHaveLength(1);
    });

    it('"Cài đặt cookie" ở footer mở lại banner', () => {
        saved(false);
        render(<CookieConsent />);
        expect(dialog()).not.toBeInTheDocument();

        act(() => emit(EVENTS.openCookieSettings));

        expect(dialog()).toBeInTheDocument();
    });

    it('rút lại đồng ý: tắt GA ngay và xoá cookie _ga', async () => {
        saved(true);
        const user = userEvent.setup();
        render(<CookieConsent />);
        document.cookie = '_ga=GA1.1.123.456; path=/';
        document.cookie = '_ga_TEST1234=GS1.1.789; path=/';
        document.cookie = 'giu_lai=1; path=/';

        act(() => emit(EVENTS.openCookieSettings));
        await user.click(screen.getByRole('button', { name: 'Từ chối' }));

        expect(document.cookie).not.toContain('_ga');
        // Cookie không phải của GA thì không được đụng vào.
        expect(document.cookie).toContain('giu_lai=1');
        expect(Reflect.get(window, `ga-disable-${GA}`)).toBe(true);
        expect(dataLayer()).toContainEqual([
            'consent',
            'update',
            expect.objectContaining({ analytics_storage: 'denied' }),
        ]);
        expect(JSON.parse(localStorage.getItem(CONSENT_KEY)!)).toMatchObject({
            analytics: false,
        });
    });

    it('đồng ý nhiều lần vẫn chỉ nạp GA một lần', async () => {
        saved(true);
        const user = userEvent.setup();
        render(<CookieConsent />);

        act(() => emit(EVENTS.openCookieSettings));
        await user.click(screen.getByRole('button', { name: 'Đồng ý' }));

        expect(gaScripts()).toHaveLength(1);
    });

    it('GTM: đồng ý thì nạp gtm.js', async () => {
        state.analytics = ids({ ga_id: null, gtm_id: GTM });
        const user = userEvent.setup();
        render(<CookieConsent />);
        expect(gtmScripts()).toHaveLength(0);

        await user.click(screen.getByRole('button', { name: 'Đồng ý' }));

        const scripts = gtmScripts();
        expect(scripts).toHaveLength(1);
        expect(scripts[0].getAttribute('src')).toBe(
            `https://www.googletagmanager.com/gtm.js?id=${GTM}`,
        );
        expect(dataLayer()).toContainEqual([
            expect.objectContaining({ event: 'gtm.js' }),
        ]);
        expect(gaScripts()).toHaveLength(0);
    });

    it('Đồng ý và Từ chối nổi bật ngang nhau — không ép khách bấm Đồng ý', () => {
        // Nút Đồng ý tô đậm còn Từ chối chỉ có viền là "dark pattern" mà hướng dẫn
        // GDPR/CookieYes cấm: hai lựa chọn phải cùng cỡ, cùng kiểu.
        render(<CookieConsent />);
        const accept = screen.getByRole('button', { name: 'Đồng ý' });
        const reject = screen.getByRole('button', { name: 'Từ chối' });
        expect(accept.className).toBe(reject.className);
    });

    it('nội dung theo cấu trúc adidas: tiêu đề lợi ích + bấm Đồng ý nghĩa là gì', () => {
        // Chủ shop chọn kiểu adidas nhưng viết lại theo đúng việc Bốp làm: chỉ cookie
        // phân tích, không quảng cáo, không bán dữ liệu — và không nêu tên Google.
        render(<CookieConsent />);
        expect(
            screen.getByRole('heading', { name: /trải nghiệm tốt hơn/i }),
        ).toBeInTheDocument();
        const text = dialog()!.textContent ?? '';
        expect(text).toMatch(/bằng cách chọn “Đồng ý”/i);
        expect(text).toMatch(/không dùng cookie quảng cáo/i);
        expect(text).toMatch(/vẫn xem và thuê đồ bình thường/i);
        expect(text).not.toMatch(/google/i);
        // Không dùng chữ "theo dõi bạn" như bản gốc — dễ làm khách lo.
        expect(text).not.toMatch(/theo dõi/i);
    });

    it('link Chính sách bảo mật để tìm hiểu thêm', () => {
        render(<CookieConsent />);
        expect(
            screen.getByRole('link', { name: 'Chính sách bảo mật' }),
        ).toHaveAttribute('href', '/chinh-sach-bao-mat');
    });

    it('desktop: nằm góc TRÁI, không đè nút Zalo/Góp ý bên phải', () => {
        render(<CookieConsent />);
        const cls = dialog()!.className;
        expect(cls).toContain('fixed');
        expect(cls).toContain('sm:left-5');
        expect(cls).toContain('sm:right-auto');
        // Trên nút nổi (z-80) để mobile bấm được, dưới modal (z-160).
        expect(cls).toContain('z-[90]');
    });
});

/* ---------------- Pixel quảng cáo (Meta / TikTok) ---------------- */

type FbqStub = ((...a: unknown[]) => void) & {
    queue: ArrayLike<unknown>[];
    disablePushState?: boolean;
};

const metaScripts = () =>
    document.querySelectorAll('script[src*="connect.facebook.net"]');
const tiktokScripts = () =>
    document.querySelectorAll('script[src*="analytics.tiktok.com"]');
const fbqCalls = () =>
    (Reflect.get(window, 'fbq') as FbqStub).queue.map((a) => Array.from(a));
const ttqCalls = () =>
    (Reflect.get(window, 'ttq') as unknown[]).filter(Array.isArray);

describe('pixel quảng cáo Meta / TikTok', () => {
    beforeEach(() => {
        state.analytics = ids({ meta_pixel_id: META, tiktok_pixel_id: TIKTOK });
    });

    it('chưa đồng ý: không nạp pixel nào, không có fbq/ttq', () => {
        render(<CookieConsent />);
        expect(dialog()).toBeInTheDocument();
        expect(metaScripts()).toHaveLength(0);
        expect(tiktokScripts()).toHaveLength(0);
        expect(Reflect.get(window, 'fbq')).toBeUndefined();
        expect(Reflect.get(window, 'ttq')).toBeUndefined();
    });

    it('Đồng ý: nạp Meta Pixel (init + PageView, tắt tự đếm pushState)', async () => {
        const user = userEvent.setup();
        render(<CookieConsent />);
        await user.click(screen.getByRole('button', { name: 'Đồng ý' }));

        expect(metaScripts()).toHaveLength(1);
        expect(metaScripts()[0].getAttribute('src')).toBe(
            'https://connect.facebook.net/en_US/fbevents.js',
        );
        const fbq = Reflect.get(window, 'fbq') as FbqStub;
        // analytics.ts tự gửi PageView khi Inertia đổi URL — Meta không được tự đếm thêm.
        expect(fbq.disablePushState).toBe(true);
        expect(fbqCalls()).toContainEqual(['init', META]);
        expect(fbqCalls()).toContainEqual(['track', 'PageView']);
    });

    it('Đồng ý: nạp TikTok Pixel (load + page)', async () => {
        const user = userEvent.setup();
        render(<CookieConsent />);
        await user.click(screen.getByRole('button', { name: 'Đồng ý' }));

        expect(tiktokScripts()).toHaveLength(1);
        expect(tiktokScripts()[0].getAttribute('src')).toBe(
            `https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=${TIKTOK}&lib=ttq`,
        );
        expect(ttqCalls()).toContainEqual(['page']);
    });

    it('rút lại: Meta/TikTok ngừng gửi và xoá cookie _fbp/_ttp', async () => {
        saved(true, 0, true);
        const user = userEvent.setup();
        render(<CookieConsent />);
        document.cookie = '_fbp=fb.1.123; path=/';
        document.cookie = '_ttp=abc; path=/';
        document.cookie = 'giu_lai=1; path=/';

        act(() => emit(EVENTS.openCookieSettings));
        await user.click(screen.getByRole('button', { name: 'Từ chối' }));

        expect(fbqCalls()).toContainEqual(['consent', 'revoke']);
        expect(ttqCalls()).toContainEqual(['revokeConsent']);
        expect(document.cookie).not.toContain('_fbp');
        expect(document.cookie).not.toContain('_ttp');
        expect(document.cookie).toContain('giu_lai=1');
    });

    it('chỉ có pixel (không GA/GTM): không tạo window.gtag', async () => {
        // analytics.ts thấy window.gtag là bắn sự kiện GA — không có GA thì không được có.
        state.analytics = ids({ ga_id: null, meta_pixel_id: META });
        const user = userEvent.setup();
        render(<CookieConsent />);
        await user.click(screen.getByRole('button', { name: 'Đồng ý' }));

        expect(Reflect.get(window, 'gtag')).toBeUndefined();
        expect(gaScripts()).toHaveLength(0);
    });

    it('chỉ có GTM: không tạo window.gtag (tránh analytics.ts bắn đôi qua dataLayer)', async () => {
        state.analytics = ids({ ga_id: null, gtm_id: GTM });
        const user = userEvent.setup();
        render(<CookieConsent />);
        await user.click(screen.getByRole('button', { name: 'Đồng ý' }));

        expect(Reflect.get(window, 'gtag')).toBeUndefined();
        expect(gtmScripts()).toHaveLength(1);
    });

    it('có pixel quảng cáo: banner nói rõ "quảng cáo", không hứa "không dùng cookie quảng cáo"', () => {
        render(<CookieConsent />);
        const text = dialog()!.textContent ?? '';
        expect(text).toMatch(/cookie phân tích và quảng cáo/i);
        expect(text).toMatch(/không kèm tên, số điện thoại hay địa chỉ/i);
        expect(text).not.toMatch(/không dùng cookie quảng cáo/i);
        expect(text).not.toMatch(/google|facebook|meta|tiktok/i);
        expect(text).not.toMatch(/theo dõi/i);
    });

    it('đã đồng ý lúc shop CHỈ có GA, nay shop bật pixel → hỏi lại, chưa nạp pixel', () => {
        saved(true, 10, false);
        render(<CookieConsent />);
        expect(dialog()).toBeInTheDocument();
        expect(metaScripts()).toHaveLength(0);
        expect(tiktokScripts()).toHaveLength(0);
    });

    it('đã từ chối từ trước: shop bật pixel cũng không hỏi lại', () => {
        saved(false, 10, false);
        render(<CookieConsent />);
        expect(dialog()).not.toBeInTheDocument();
        expect(metaScripts()).toHaveLength(0);
    });

    it('đã đồng ý cả quảng cáo: không hỏi lại, nạp đủ pixel', () => {
        saved(true, 10, true);
        render(<CookieConsent />);
        expect(dialog()).not.toBeInTheDocument();
        expect(metaScripts()).toHaveLength(1);
        expect(tiktokScripts()).toHaveLength(1);
    });
});
