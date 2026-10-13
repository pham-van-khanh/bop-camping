import {
    contactMethodFromHref,
    handleDocumentClick,
    isTrackablePath,
    itemFromCartLine,
    resetNavigationState,
    trackAddToCart,
    trackBeginCheckout,
    trackLead,
    trackPurchase,
    trackSpaNavigation,
    trackViewItem,
    type AnalyticsItem,
} from '@/lib/analytics';
import type { CartLine } from '@/lib/cart';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-wi6h — module đo lường duy nhất (GA4 / GTM / Meta Pixel / TikTok Pixel).
 * Kiểm: không có script nào thì im lặng, payload đúng chuẩn từng nền tảng, purchase chỉ
 * bắn 1 lần mỗi mã đơn, không đo trang admin, và script bên thứ ba lỗi không làm vỡ trang.
 */

type W = Window & {
    gtag?: unknown;
    dataLayer?: unknown[];
    fbq?: unknown;
    ttq?: unknown;
};
const win = window as W;

const lều: AnalyticsItem = {
    id: '7',
    name: 'Lều 2 người',
    category: 'leu',
    pricePerDay: 100000,
    quantity: 2,
    days: 3,
};

/** Dấu snippet GTM đẩy đồng bộ khi chạy — analytics.ts dựa vào đây để biết có GTM. */
const GTM_START = { 'gtm.start': 1, event: 'gtm.js' };

function installAll({ gtm = true }: { gtm?: boolean } = {}) {
    const gtag = vi.fn();
    const fbq = vi.fn();
    const ttq = { track: vi.fn(), page: vi.fn() };
    win.gtag = gtag;
    win.fbq = fbq;
    win.ttq = ttq;
    win.dataLayer = gtm ? [GTM_START] : [];
    return { gtag, fbq, ttq, dataLayer: win.dataLayer };
}

function goTo(path: string) {
    window.history.replaceState({}, '', path);
}

beforeEach(() => {
    delete win.gtag;
    delete win.fbq;
    delete win.ttq;
    delete win.dataLayer;
    window.sessionStorage.clear();
    resetNavigationState();
    goTo('/thiet-bi/leu-2-nguoi');
});

afterEach(() => {
    goTo('/');
});

describe('không có script nào', () => {
    it('mọi hàm đều no-op, không ném lỗi', () => {
        expect(() => {
            trackViewItem(lều);
            trackAddToCart(lều);
            trackBeginCheckout([lều]);
            trackLead('zalo');
            trackSpaNavigation('/a');
            trackSpaNavigation('/b');
        }).not.toThrow();
        expect(
            trackPurchase({ transactionId: 'BOP-1', value: 1, items: [lều] }),
        ).toBe(true);
    });
});

describe('payload', () => {
    it('view_item: GA4 + Meta ViewContent + TikTok ViewContent, giá = giá/ngày × số ngày', () => {
        const { gtag, fbq, ttq } = installAll();

        trackViewItem(lều);

        expect(gtag).toHaveBeenCalledWith('event', 'view_item', {
            currency: 'VND',
            value: 600000,
            items: [
                {
                    item_id: '7',
                    item_name: 'Lều 2 người',
                    item_category: 'leu',
                    price: 300000,
                    quantity: 2,
                    index: 0,
                    price_per_day: 100000,
                    rental_days: 3,
                },
            ],
        });
        expect(fbq).toHaveBeenCalledWith(
            'track',
            'ViewContent',
            expect.objectContaining({
                currency: 'VND',
                value: 600000,
                content_ids: ['7'],
                content_type: 'product',
                content_name: 'Lều 2 người',
            }),
        );
        expect(ttq.track).toHaveBeenCalledWith(
            'ViewContent',
            expect.objectContaining({
                currency: 'VND',
                value: 600000,
                contents: [
                    {
                        content_id: '7',
                        content_name: 'Lều 2 người',
                        quantity: 2,
                        price: 300000,
                    },
                ],
            }),
        );
    });

    it('GTM: xoá ecommerce cũ rồi đẩy sự kiện theo chuẩn GA4', () => {
        const { dataLayer } = installAll();

        trackAddToCart(lều);

        expect(dataLayer[1]).toEqual({ ecommerce: null });
        expect(dataLayer[2]).toMatchObject({
            event: 'add_to_cart',
            ecommerce: { currency: 'VND', value: 600000 },
        });
    });

    it('chỉ có GA4 (gtag tự tạo dataLayer, không có GTM): không đẩy sự kiện kiểu GTM vào dataLayer', () => {
        const { gtag, dataLayer } = installAll({ gtm: false });

        trackAddToCart(lều);
        trackSpaNavigation('/');
        trackSpaNavigation('/thiet-bi');

        expect(gtag).toHaveBeenCalledWith(
            'event',
            'add_to_cart',
            expect.objectContaining({ value: 600000 }),
        );
        expect(dataLayer).toEqual([]);
    });

    it('begin_checkout cộng nhiều món; giỏ rỗng không bắn', () => {
        const { gtag, fbq } = installAll();

        trackBeginCheckout([]);
        expect(gtag).not.toHaveBeenCalled();

        trackBeginCheckout([lều, { ...lều, id: '8', quantity: 1, days: 1 }]);
        expect(gtag).toHaveBeenCalledWith(
            'event',
            'begin_checkout',
            expect.objectContaining({ value: 700000 }),
        );
        expect(fbq).toHaveBeenCalledWith(
            'track',
            'InitiateCheckout',
            expect.objectContaining({ num_items: 3 }),
        );
    });

    it('lead: generate_lead + Meta Contact + TikTok Contact, không kèm PII', () => {
        const { gtag, fbq, ttq } = installAll();

        trackLead('phone', 'footer');

        expect(gtag).toHaveBeenCalledWith('event', 'generate_lead', {
            method: 'phone',
            placement: 'footer',
            currency: 'VND',
            value: 0,
        });
        expect(fbq).toHaveBeenCalledWith('track', 'Contact', {
            content_name: 'phone',
        });
        expect(ttq.track).toHaveBeenCalledWith('Contact', {
            content_name: 'phone',
        });
    });

    it('itemFromCartLine: combo có id riêng, số ngày tính từ khoảng thuê', () => {
        const line: CartLine = {
            id: 3,
            kind: 'combo',
            name: 'Combo 4 người',
            cat: 'combo',
            grad: '',
            price: 500000,
            deposit: 1000000,
            qty: 1,
            start: '2030-07-01',
            end: '2030-07-02',
        };
        expect(itemFromCartLine(line)).toEqual({
            id: 'combo-3',
            name: 'Combo 4 người',
            category: 'combo',
            pricePerDay: 500000,
            quantity: 1,
            days: 2,
        });
    });
});

describe('purchase', () => {
    it('dùng mã đơn làm transaction_id, value lấy từ server', () => {
        const { gtag, fbq, ttq } = installAll();

        trackPurchase({
            transactionId: 'BOP-ABC',
            value: 550000,
            items: [lều],
        });

        expect(gtag).toHaveBeenCalledWith(
            'event',
            'purchase',
            expect.objectContaining({
                transaction_id: 'BOP-ABC',
                value: 550000,
                currency: 'VND',
            }),
        );
        expect(fbq).toHaveBeenCalledWith(
            'track',
            'Purchase',
            expect.objectContaining({ value: 550000, currency: 'VND' }),
            { eventID: 'order-BOP-ABC' },
        );
        expect(ttq.track).toHaveBeenCalledWith(
            'CompletePayment',
            expect.objectContaining({ value: 550000, order_id: 'BOP-ABC' }),
        );
    });

    it('chỉ bắn 1 lần mỗi mã đơn (reload / Back lại trang cảm ơn)', () => {
        const { gtag } = installAll();
        const args = { transactionId: 'BOP-1', value: 100, items: [lều] };

        expect(trackPurchase(args)).toBe(true);
        expect(trackPurchase(args)).toBe(false);
        expect(gtag).toHaveBeenCalledTimes(1);

        expect(trackPurchase({ ...args, transactionId: 'BOP-2' })).toBe(true);
        expect(gtag).toHaveBeenCalledTimes(2);
    });

    it('sessionStorage bị chặn vẫn không ném lỗi', () => {
        const { gtag } = installAll();
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('SecurityError');
        });
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('SecurityError');
        });

        expect(() =>
            trackPurchase({ transactionId: 'BOP-9', value: 1, items: [] }),
        ).not.toThrow();
        expect(gtag).toHaveBeenCalledTimes(1);
    });
});

describe('an toàn', () => {
    it('script bên thứ ba ném lỗi không làm hỏng các nền tảng còn lại', () => {
        const { ttq } = installAll();
        win.gtag = () => {
            throw new Error('gtag hỏng');
        };

        expect(() => trackViewItem(lều)).not.toThrow();
        expect(ttq.track).toHaveBeenCalled();
    });

    it('không đo trang /admin và /shipper', () => {
        const { gtag, fbq } = installAll();
        goTo('/admin/orders');

        trackViewItem(lều);
        trackLead('zalo');
        expect(trackPurchase({ transactionId: 'X', value: 1, items: [] })).toBe(
            false,
        );
        expect(gtag).not.toHaveBeenCalled();
        expect(fbq).not.toHaveBeenCalled();

        expect(isTrackablePath('/shipper')).toBe(false);
        expect(isTrackablePath('/shipper/lich')).toBe(false);
        expect(isTrackablePath('/admins-guide')).toBe(true);
        expect(isTrackablePath('/')).toBe(true);
    });
});

describe('page view khi điều hướng SPA', () => {
    it('bỏ lần đầu (script gốc đã đếm), bỏ trùng URL, không gửi page_view GA4/TikTok tay', () => {
        const { gtag, fbq, ttq, dataLayer } = installAll();

        trackSpaNavigation('/');
        expect(fbq).not.toHaveBeenCalled();

        trackSpaNavigation('/thiet-bi');
        trackSpaNavigation('/thiet-bi'); // submit form quay về cùng trang
        expect(fbq).toHaveBeenCalledTimes(1);
        expect(fbq).toHaveBeenCalledWith('track', 'PageView');
        // TikTok tự đo pageview khi URL đổi trong SPA (mặc định bật) → gọi tay là đếm đôi.
        expect(ttq.page).not.toHaveBeenCalled();
        expect(dataLayer).toEqual([
            GTM_START,
            { event: 'virtual_page_view', page_path: '/thiet-bi' },
        ]);
        expect(gtag).not.toHaveBeenCalled();
    });

    it('không đếm khi chuyển vào trang admin', () => {
        const { fbq } = installAll();
        trackSpaNavigation('/');
        trackSpaNavigation('/admin/dashboard');
        expect(fbq).not.toHaveBeenCalled();
    });
});

describe('click liên hệ', () => {
    it('nhận diện Zalo / gọi điện / Messenger', () => {
        expect(contactMethodFromHref('tel:0976544370')).toBe('phone');
        expect(contactMethodFromHref('https://zalo.me/0976544370')).toBe(
            'zalo',
        );
        expect(contactMethodFromHref('https://m.me/bopcamping')).toBe(
            'messenger',
        );
        expect(contactMethodFromHref('https://evilzalo.me/x')).toBeNull();
        expect(contactMethodFromHref('/thiet-bi')).toBeNull();
        expect(contactMethodFromHref(null)).toBeNull();
    });

    it('click link Zalo trong vùng có data-track-placement → lead kèm vị trí', () => {
        const { gtag } = installAll();
        document.body.innerHTML =
            '<footer data-track-placement="footer"><a href="https://zalo.me/1"><span>Zalo</span></a></footer>';
        const span = document.querySelector('span')!;

        handleDocumentClick({ target: span } as unknown as Event);

        expect(gtag).toHaveBeenCalledWith(
            'event',
            'generate_lead',
            expect.objectContaining({ method: 'zalo', placement: 'footer' }),
        );
        document.body.innerHTML = '';
    });
});
