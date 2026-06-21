// Đăng nhập nhanh bằng SĐT + tên, lưu localStorage (chưa nối backend — issue bopcamping-r1b).
import { emit, EVENTS } from './bus';

export type LocalUser = { name: string; phone: string };

const KEY = 'bop_user_v1';

export function getUser(): LocalUser | null {
    if (typeof window === 'undefined') return null;
    try {
        const raw = window.localStorage.getItem(KEY);
        return raw ? (JSON.parse(raw) as LocalUser) : null;
    } catch {
        return null;
    }
}

export function setUser(u: LocalUser) {
    window.localStorage.setItem(KEY, JSON.stringify(u));
    emit(EVENTS.userChange, u);
}

export function clearUser() {
    window.localStorage.removeItem(KEY);
    emit(EVENTS.userChange, null);
}
