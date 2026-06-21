// Auth helper — đã chuyển sang Inertia session (backend).
// localStorage không còn dùng để lưu user; các hàm giữ lại để không break imports cũ.
import { emit, EVENTS } from './bus';

export type LocalUser = { name: string; phone: string };

/** @deprecated Không còn dùng localStorage. Dùng usePage().props.auth.user thay thế. */
export function getUser(): LocalUser | null {
    return null;
}

/** @deprecated Login nay qua POST /dang-nhap (GuestAuthController). */
export function setUser(_u: LocalUser) {
    emit(EVENTS.userChange, null);
}

/** @deprecated Logout nay qua POST /dang-xuat (GuestAuthController). */
export function clearUser() {
    emit(EVENTS.userChange, null);
}
