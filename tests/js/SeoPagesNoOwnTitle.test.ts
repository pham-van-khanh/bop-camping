import { describe, expect, it } from 'vitest';

/**
 * bopcamping-ft1j — title một nguồn: trang khách có prop `seo` thì SiteLayout (SeoTitle) đặt
 * document.title = seo.title. Trang tự đặt `<Head title>` sẽ chạy SAU SeoTitle và ghi đè
 * title server sau hydrate (Googlebot index DOM đã render → title client thắng).
 *
 * Danh sách = mọi Page mà controller Shop render kèm `seo` (SeoService::page). Thêm trang
 * SEO mới thì thêm tên vào đây.
 */
const SEO_PAGES = [
    'About',
    'AreaLanding',
    'CampingSpotDetail',
    'CampingSpotIndex',
    'ComboDetail',
    'Combos',
    'Policy',
    'PostDetail',
    'Posts',
    'ProductDetail',
    'Products',
    'Welcome',
];

const sources = import.meta.glob<string>('../../resources/js/Pages/*.tsx', {
    query: '?raw',
    import: 'default',
    eager: true,
});

describe('trang có seo không tự đặt <Head title>', () => {
    it.each(SEO_PAGES)('%s', (name) => {
        const src = sources[`../../resources/js/Pages/${name}.tsx`];
        expect(src, `không tìm thấy Pages/${name}.tsx`).toBeTypeOf('string');
        expect(src).not.toMatch(/<Head\b[^>]*\btitle=/);
        expect(src).toMatch(/<SiteLayout>/);
    });
});
