// Giỏ thuê lưu ở localStorage (chưa có backend). Mỗi thay đổi bắn EVENTS.cartChange
// để Header cập nhật badge và trang Giỏ vẽ lại.
import { dayCount } from './format';
import { emit, EVENTS } from './bus';
import { netFromGross, type DurationTier } from './pricing';

export type CartLocation = { slug: string; name: string };

export type CartLine = {
    id: number; // product id, hoặc combo id khi kind = 'combo'
    name: string;
    cat: string; // slug danh mục (legacy) — combo dùng 'combo'
    grad: string;
    price: number; // giá/ngày (combo = combo_price)
    deposit: number; // cọc 1 lần / bộ (combo = cọc combo)
    qty: number;
    start: string; // ISO
    end: string; // ISO
    // Vị trí phục vụ (đang mở) của sản phẩm — để giữ giỏ trong cùng 1 vị trí.
    locations?: CartLocation[];
    // Cửa hàng khách CHỌN cho dòng này (per-store stock). null = chưa chọn → checkout tự gán.
    location_id?: number | null;
    // Dòng combo (PRD combo): id trỏ vào combos, kèm danh sách món để mở rộng xem.
    kind?: 'product' | 'combo';
    comboItems?: { name: string; qty: number }[];
    // Ưu đãi trả sớm trong ngày % của sản phẩm (adr_pricing_models) — 0/undefined = không có.
    early_return_pct?: number;
    // Khách chọn "trả sớm trong ngày" cho dòng này — chỉ áp khi đơn cùng ngày (start === end).
    half_day?: boolean;
    // Giờ khách chọn khi thuê 1 ngày (bopcamping-n6mr) — "HH:MM"; null = không chọn (nhiều ngày).
    requested_pickup_time?: string | null;
    requested_return_time?: string | null;
};

/** Cửa hàng đã chọn trong giỏ (per-store): id đầu tiên khác null, hoặc null nếu chưa dòng nào chọn. */
export function cartChosenStoreId(lines = getCart()): number | null {
    return lines.find((l) => l.location_id != null)?.location_id ?? null;
}

/** Dòng giỏ là combo (dòng cũ không có kind = product). */
export const isComboLine = (l: CartLine) => l.kind === 'combo';

const KEY = 'bop_cart_v1';

export function getCart(): CartLine[] {
    if (typeof window === 'undefined') return [];
    try {
        const raw = window.localStorage.getItem(KEY);
        return raw ? (JSON.parse(raw) as CartLine[]) : [];
    } catch {
        return [];
    }
}

function save(lines: CartLine[]) {
    window.localStorage.setItem(KEY, JSON.stringify(lines));
    emit(EVENTS.cartChange, lines.length);
}

/**
 * Khoảng ngày "đang dùng" của giỏ (bopcamping-wtuv T5) — prefill lịch cho sản phẩm mở sau.
 * Mọi dòng cùng khoảng → khoảng đó; giỏ đã lẫn nhiều khoảng → khoảng của DÒNG THÊM GẦN NHẤT
 * (khách đã cố ý tách); giỏ trống → null.
 */
export function cartSuggestedRange(lines = getCart()): { start: string; end: string } | null {
    if (lines.length === 0) return null;
    const last = lines[lines.length - 1];
    return { start: last.start, end: last.end };
}

/** Gộp khi cùng sản phẩm/combo + cùng khoảng ngày (id product và combo là 2 không gian riêng) */
export function addLine(line: CartLine) {
    const lines = getCart();
    const i = lines.findIndex(
        (l) => l.id === line.id
            && (l.kind ?? 'product') === (line.kind ?? 'product')
            && l.start === line.start
            && l.end === line.end,
    );
    if (i >= 0) lines[i].qty += line.qty;
    else lines.push(line);
    save(lines);
}

export function setQty(index: number, qty: number) {
    const lines = getCart();
    if (!lines[index]) return;
    lines[index].qty = Math.max(1, qty);
    save(lines);
}

export function removeLine(index: number) {
    const lines = getCart();
    lines.splice(index, 1);
    save(lines);
}

export function clearCart() {
    save([]);
}

/** Ghi đè toàn bộ giỏ (dùng khi làm tươi từ server). */
export function setCart(lines: CartLine[]) {
    save(lines);
}

export const lineDays = (l: CartLine) => dayCount(l.start, l.end);

/** Dòng đủ điều kiện "trả sớm trong ngày": thuê đúng 1 ngày + sản phẩm có ưu đãi. */
export const halfDayEligible = (l: CartLine) => lineDays(l) === 1 && (l.early_return_pct ?? 0) > 0;

// Rent NET sau giảm giá thuê dài ngày (bopcamping-e36e) — mirror server RentalPricingService.
// tiers rỗng (mặc định) → net = gross (giữ hành vi cũ khi caller chưa truyền bậc).
// Nửa ngày (adr_pricing_models): khách chọn trả sớm + đơn cùng ngày → áp ưu đãi trả sớm
// thay bậc dài ngày (bậc dài ngày = 0 khi 1 ngày). Mirror server priceLine().
export const lineRent = (l: CartLine, tiers: DurationTier[] = []) => {
    const days = lineDays(l);
    const gross = l.price * l.qty * days;
    if (l.half_day && halfDayEligible(l)) {
        return Math.round(gross * (1 - (l.early_return_pct as number) / 100));
    }
    return netFromGross(gross, days, tiers);
};
export const lineDeposit = (l: CartLine) => l.deposit * l.qty;

/** Bật/tắt "trả sớm trong ngày" cho 1 dòng giỏ. */
export function setHalfDay(index: number, on: boolean) {
    const lines = getCart();
    if (!lines[index]) return;
    lines[index].half_day = on;
    save(lines);
}

export function cartCount(lines = getCart()) {
    return lines.reduce((s, l) => s + l.qty, 0);
}

/**
 * Vị trí chung của giỏ = giao vị trí của TẤT CẢ dòng có ràng buộc vị trí.
 * Dòng không có locations (giỏ cũ) coi như "không ràng buộc" → bỏ qua khi giao.
 * Trả [] nghĩa là giỏ chưa có ràng buộc (trống hoặc toàn dòng cũ).
 */
export function cartCommonLocations(lines = getCart()): CartLocation[] {
    const constrained = lines.filter((l) => l.locations && l.locations.length > 0);
    if (constrained.length === 0) return [];

    let common = constrained[0].locations as CartLocation[];
    for (const l of constrained.slice(1)) {
        const slugs = new Set((l.locations as CartLocation[]).map((x) => x.slug));
        common = common.filter((c) => slugs.has(c.slug));
    }
    return common;
}

/**
 * Kiểm tra thêm sản phẩm (với vị trí của nó) có hợp giỏ không.
 * Xung đột khi: giỏ đã ràng buộc 1 vị trí VÀ sản phẩm mới có ràng buộc VÀ hai bên không giao nhau.
 * Sản phẩm/giỏ "không ràng buộc" (toàn hệ thống / trống / dòng cũ) → luôn hợp.
 */
export function locationConflict(
    newLocations: CartLocation[] | undefined,
    lines = getCart(),
): { conflict: boolean; cartLocations: CartLocation[] } {
    const common = cartCommonLocations(lines);
    if (common.length === 0) return { conflict: false, cartLocations: [] };
    if (!newLocations || newLocations.length === 0) return { conflict: false, cartLocations: common };

    const slugs = new Set(newLocations.map((x) => x.slug));
    const conflict = !common.some((c) => slugs.has(c.slug));
    return { conflict, cartLocations: common };
}

/** Giỏ có mâu thuẫn vị trí không (≥2 dòng ràng buộc nhưng không có vị trí chung). */
export function cartHasLocationConflict(lines = getCart()): boolean {
    const constrained = lines.filter((l) => l.locations && l.locations.length > 0);
    if (constrained.length < 2) return false;
    return cartCommonLocations(lines).length === 0;
}

export function cartTotals(lines = getCart(), tiers: DurationTier[] = []) {
    const rent = lines.reduce((s, l) => s + lineRent(l, tiers), 0);
    const deposit = lines.reduce((s, l) => s + lineDeposit(l), 0);
    return { rent, deposit, pay: rent + deposit };
}
