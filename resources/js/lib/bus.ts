// Event bus nhỏ để Header / trang / layout nói chuyện với nhau
// (mở modal đăng nhập, bắn toast, báo giỏ thay đổi) mà không cần global state.

type Detail = unknown;
const target: EventTarget | null = typeof window !== 'undefined' ? new EventTarget() : null;

export function emit(name: string, detail?: Detail) {
    target?.dispatchEvent(new CustomEvent(name, { detail }));
}

export function on(name: string, handler: (detail: Detail) => void) {
    if (!target) return () => {};
    const cb = (e: Event) => handler((e as CustomEvent).detail);
    target.addEventListener(name, cb);
    return () => target.removeEventListener(name, cb);
}

export const EVENTS = {
    cartChange: 'bop:cart-change',
    userChange: 'bop:user-change',
    toast: 'bop:toast',
    openLogin: 'bop:open-login',
} as const;
