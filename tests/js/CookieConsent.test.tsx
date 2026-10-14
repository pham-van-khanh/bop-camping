import CookieConsent from '@/Components/site/CookieConsent';
import { emit, EVENTS } from '@/lib/bus';
import { CONSENT_KEY } from '@/lib/consent';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Banner đồng ý cookie (bopcamping-vqwr) — chế độ CHẶN HẲN: chưa bấm "Đồng ý" thì
 * không nạp GA/GTM, không gửi gì tới Google. Server không còn in script phân tích
 * (xem CookieConsentTest.php); file này khoá phần front-end quyết định nạp hay không.
 */

type Analytics = { ga_id: string | null; gtm_id: string | null };

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
const DAY = 864e5;

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

function saved(analytics: boolean, ageDays = 0) {
    localStorage.setItem(
        CONSENT_KEY,
        JSON.stringify({ v: 1, analytics, at: Date.now() - ageDays * DAY }),
    );
}

function clearCookies() {
    document.cookie.split(';').forEach((c) => {
        const name = c.split('=')[0].trim();
        if (name) document.cookie = `${name}=; Max-Age=0; path=/`;
    });
}

beforeEach(() => {
    state.analytics = { ga_id: GA, gtm_id: null };
    localStorage.clear();
    clearCookies();
    document.querySelectorAll('script').forEach((s) => s.remove());
    Reflect.deleteProperty(window, 'dataLayer');
    Reflect.deleteProperty(window, 'gtag');
    Reflect.deleteProperty(window, `ga-disable-${GA}`);
});

describe('banner đồng ý cookie', () => {
    it('không render khi shop chưa cấu hình GA/GTM', () => {
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
        state.analytics = { ga_id: null, gtm_id: GTM };
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

    it('có link tới Chính sách bảo mật', () => {
        render(<CookieConsent />);
        expect(
            screen.getByRole('link', { name: /chính sách bảo mật/i }),
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
