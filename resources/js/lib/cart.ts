// Giỏ thuê lưu ở localStorage (chưa có backend). Mỗi thay đổi bắn EVENTS.cartChange
// để Header cập nhật badge và trang Giỏ vẽ lại.
import { dayCount } from './format';
import { emit, EVENTS } from './bus';
import type { CatKey } from './catalog';

export type CartLine = {
    id: number;
    name: string;
    cat: CatKey;
    grad: string;
    price: number;
    deposit: number;
    qty: number;
    start: string; // ISO
    end: string; // ISO
};

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

/** Gộp khi cùng sản phẩm + cùng khoảng ngày */
export function addLine(line: CartLine) {
    const lines = getCart();
    const i = lines.findIndex((l) => l.id === line.id && l.start === line.start && l.end === line.end);
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

export const lineDays = (l: CartLine) => dayCount(l.start, l.end);
export const lineRent = (l: CartLine) => l.price * l.qty * lineDays(l);
export const lineDeposit = (l: CartLine) => l.deposit * l.qty;

export function cartCount(lines = getCart()) {
    return lines.reduce((s, l) => s + l.qty, 0);
}

export function cartTotals(lines = getCart()) {
    const rent = lines.reduce((s, l) => s + lineRent(l), 0);
    const deposit = lines.reduce((s, l) => s + lineDeposit(l), 0);
    return { rent, deposit, pay: rent + deposit };
}
