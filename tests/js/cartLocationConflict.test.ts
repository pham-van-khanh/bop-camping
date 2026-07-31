import {
    cartHasLocationConflict,
    locationConflict,
    type CartLine,
} from '@/lib/cart';
import { describe, expect, it } from 'vitest';

/**
 * Chốt ràng buộc vị trí của giỏ (bopcamping-6qsm, T6). `locations` rỗng ([]) nghĩa là món
 * không bán được ở kho nào — phải bị coi là xung đột, không được lọt qua như "không ràng
 * buộc" (giỏ trống / dòng cũ chưa có field `locations`). Truyền `lines` trực tiếp để test
 * thuần, không đụng localStorage.
 */
const HN = { slug: 'hn', name: 'Hà Nội' };
const HCM = { slug: 'hcm', name: 'TP.HCM' };

const makeLine = (overrides: Partial<CartLine> = {}): CartLine => ({
    id: 1,
    name: 'Lều 2 người',
    cat: 'leu',
    grad: '',
    price: 100000,
    deposit: 200000,
    qty: 1,
    start: '2026-08-01',
    end: '2026-08-02',
    ...overrides,
});

describe('locationConflict', () => {
    it('sản phẩm mới không có locations (rỗng) -> báo conflict khi giỏ đã ràng buộc', () => {
        const lines = [makeLine({ locations: [HN] })];

        const { conflict, cartLocations } = locationConflict([], lines);

        expect(conflict).toBe(true);
        expect(cartLocations).toEqual([HN]);
    });

    it('sản phẩm mới không có locations (undefined) -> báo conflict khi giỏ đã ràng buộc', () => {
        const lines = [makeLine({ locations: [HN] })];

        const { conflict, cartLocations } = locationConflict(undefined, lines);

        expect(conflict).toBe(true);
        expect(cartLocations).toEqual([HN]);
    });

    it('có kho chung với giỏ -> KHÔNG conflict (giữ hành vi cũ)', () => {
        const lines = [makeLine({ locations: [HN, HCM] })];

        const { conflict } = locationConflict([HN], lines);

        expect(conflict).toBe(false);
    });

    it('giỏ trống hoàn toàn + thêm món có kho -> không conflict', () => {
        const { conflict } = locationConflict([HN], []);

        expect(conflict).toBe(false);
    });
});

describe('cartHasLocationConflict', () => {
    it('giỏ chứa dòng locations rỗng -> true', () => {
        const lines = [makeLine({ locations: [] })];

        expect(cartHasLocationConflict(lines)).toBe(true);
    });

    it('giỏ chứa dòng locations rỗng cùng dòng khác có kho -> true', () => {
        const lines = [
            makeLine({ id: 1, locations: [HN] }),
            makeLine({ id: 2, locations: [] }),
        ];

        expect(cartHasLocationConflict(lines)).toBe(true);
    });

    it('nhiều dòng có kho chung -> KHÔNG conflict (giữ hành vi cũ)', () => {
        const lines = [
            makeLine({ id: 1, locations: [HN, HCM] }),
            makeLine({ id: 2, locations: [HN] }),
        ];

        expect(cartHasLocationConflict(lines)).toBe(false);
    });

    it('giỏ trống hoàn toàn -> không conflict', () => {
        expect(cartHasLocationConflict([])).toBe(false);
    });
});
