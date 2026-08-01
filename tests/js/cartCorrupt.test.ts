import { cartCount, getCart, type CartLine } from '@/lib/cart';
import { beforeEach, describe, expect, it } from 'vitest';

/**
 * bopcamping-gccu — giỏ hỏng trong localStorage KHÔNG được làm vỡ site.
 *
 * Lỗi gốc: getCart() `JSON.parse(raw) as CartLine[]` — try/catch chỉ bắt lỗi PARSE, không
 * kiểm hình dạng. JSON hợp lệ nhưng không phải mảng (vd `{"items":[...]}`) lọt qua, rồi
 * cartCount() gọi `lines.reduce` và ném TypeError.
 *
 * Vì cartCount() được gọi trong useEffect của SiteLayout — layout dùng chung MỌI trang —
 * nên React tháo cả cây: khách thấy TRANG TRẮNG ở mọi trang, không riêng trang giỏ. Cách
 * duy nhất để tự thoát là xoá site data, mà hầu như không khách nào biết làm.
 *
 * Đường vào thực tế: đổi định dạng lưu giỏ giữa hai lần deploy (khách còn key cũ trong máy),
 * hoặc extension/tab khác ghi đè key.
 */

const KEY = 'bop_cart_v1';

const dongHopLe: CartLine = {
    id: 1,
    name: 'Lều 2 người',
    cat: 'leu',
    grad: '',
    price: 100000,
    deposit: 200000,
    qty: 2,
    start: '2026-08-04',
    end: '2026-08-06',
};

const set = (raw: string) => window.localStorage.setItem(KEY, raw);

describe('giỏ hỏng không được làm vỡ site (bopcamping-gccu)', () => {
    beforeEach(() => window.localStorage.clear());

    it('giỏ đúng định dạng vẫn đọc bình thường', () => {
        set(JSON.stringify([dongHopLe]));

        expect(getCart()).toHaveLength(1);
        expect(cartCount()).toBe(2);
    });

    /** Ca gây ra sự cố thật: JSON HỢP LỆ nhưng là object, không phải mảng. */
    it('JSON hợp lệ nhưng là object -> trả mảng rỗng, KHÔNG ném lỗi', () => {
        set(JSON.stringify({ items: [dongHopLe], combos: [] }));

        expect(getCart()).toEqual([]);
        expect(() => cartCount()).not.toThrow();
        expect(cartCount()).toBe(0);
    });

    it.each([
        ['chuỗi', '"xin chao"'],
        ['số', '42'],
        ['null', 'null'],
        ['true', 'true'],
        ['JSON rác', '{khong-phai-json'],
    ])('%s -> trả mảng rỗng, không ném lỗi', (_ten, raw) => {
        set(raw);

        expect(getCart()).toEqual([]);
        expect(() => cartCount()).not.toThrow();
    });

    /**
     * Mảng đúng nhưng phần tử rác. Bỏ nguyên giỏ thì khách mất hết hàng đã chọn — nên chỉ
     * loại dòng hỏng và GIỮ dòng lành.
     */
    it('mảng lẫn dòng rác -> giữ dòng lành, loại dòng hỏng', () => {
        set(
            JSON.stringify([
                dongHopLe,
                null,
                'rác',
                42,
                { id: 2 }, // thiếu gần hết trường bắt buộc
                { ...dongHopLe, id: 3, qty: 'nhiều' }, // qty sai kiểu
                { ...dongHopLe, id: 4, start: null }, // ngày sai kiểu
                { ...dongHopLe, id: 5 }, // lành
            ]),
        );

        const lines = getCart();

        expect(lines.map((l) => l.id)).toEqual([1, 5]);
        expect(cartCount()).toBe(4);
    });

    /**
     * qty rác làm badge trên header hiện "NaN" — vẫn là lỗi khách nhìn thấy.
     *
     * Phải gọi THẲNG cartCount(lines) chứ không đi qua localStorage: JSON không chứa được
     * NaN (`JSON.stringify({qty: NaN})` ra `"qty":null`), nên đi đường localStorage thì
     * test qua một cách vô nghĩa và không hề kiểm phần phòng thủ trong cartCount.
     */
    it('cartCount không bao giờ trả NaN dù dòng có qty rác', () => {
        const rac = [
            { ...dongHopLe, qty: Number.NaN },
            { ...dongHopLe, qty: Number.POSITIVE_INFINITY },
            { ...dongHopLe, qty: undefined as unknown as number },
            { ...dongHopLe, qty: 3 },
        ];

        expect(cartCount(rac)).toBe(3);
        expect(Number.isNaN(cartCount(rac))).toBe(false);
    });

    it('qty NaN đi qua localStorage cũng không làm hỏng số đếm', () => {
        set(JSON.stringify([{ ...dongHopLe, qty: Number.NaN }]));

        expect(cartCount()).toBe(0);
    });

    it('localStorage chưa có gì -> mảng rỗng', () => {
        expect(getCart()).toEqual([]);
        expect(cartCount()).toBe(0);
    });
});
