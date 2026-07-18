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
// Rent NET sau giảm giá thuê dài ngày (bopcamping-e36e) — mirror server RentalPricingService.
// tiers rỗng (mặc định) → net = gross (giữ hành vi cũ khi caller chưa truyền bậc).
export const lineRent = (l: CartLine, tiers: DurationTier[] = []) => {
    const days = lineDays(l);
    return netFromGross(l.price * l.qty * days, days, tiers);
};
export const lineDeposit = (l: CartLine) => l.deposit * l.qty;

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
