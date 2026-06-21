import { Link, usePage } from '@inertiajs/react';
import { emit, EVENTS } from '@/lib/bus';

const NAV = [
    { label: 'Trang chủ', href: '/' },
    { label: 'Thuê đồ', href: '/thiet-bi' },
    { label: 'Tra cứu đơn', href: '/tra-cuu' },
    { label: 'Quản trị', href: '/admin' },
];

function isActive(current: string, href: string) {
    if (href === '/') return current === '/';
    return current.startsWith(href);
}

export default function Header({ cartCount = 0, userName }: { cartCount?: number; userName?: string }) {
    const url = usePage().url;
    return (
        <header
            className="sticky top-0 z-50 border-b border-[#c2dcec]"
            style={{ background: 'rgba(221,239,250,.78)', backdropFilter: 'blur(14px)', WebkitBackdropFilter: 'blur(14px)' }}
        >
            <div className="mx-auto flex h-16 max-w-[1200px] items-center gap-4 px-5">
                {/* Logo */}
                <Link href="/" className="flex shrink-0 items-center gap-2.5">
                    <span className="grid h-[38px] w-[38px] place-items-center rounded-[11px] bg-grass">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2" strokeLinejoin="round">
                            <path d="M12 4 L21 20 H3 Z" />
                            <path d="M12 4 V20 M9 20 l3 -6 3 6" />
                        </svg>
                    </span>
                    <span className="leading-tight">
                        <span className="block text-[17px] font-extrabold tracking-tight text-pine">BỐP CAMPING</span>
                        <span className="block font-mono text-[10px] tracking-[0.14em] text-moss">THUÊ ĐỒ DÃ NGOẠI</span>
                    </span>
                </Link>

                {/* Menu */}
                <nav className="ml-2 flex flex-1 items-center gap-1 overflow-x-auto">
                    {NAV.map((n) => {
                        const active = isActive(url, n.href);
                        return (
                            <Link
                                key={n.href}
                                href={n.href}
                                className={`shrink-0 rounded-[10px] px-3.5 py-2 text-sm font-semibold transition ${
                                    active ? 'bg-grass text-white' : 'text-[#3f4a32] hover:bg-black/5'
                                }`}
                            >
                                {n.label}
                            </Link>
                        );
                    })}
                </nav>

                {/* Actions */}
                <div className="flex shrink-0 items-center gap-2">
                    <button
                        onClick={() => emit(EVENTS.openLogin)}
                        className="flex items-center gap-2 rounded-control border border-cardBorder bg-card px-3 py-2 text-sm font-semibold text-pine transition hover:border-grass"
                    >
                        <span className="grid h-6 w-6 place-items-center rounded-full bg-grass/15 text-xs font-bold text-grass">
                            {userName ? userName.trim().charAt(0).toUpperCase() : 'B'}
                        </span>
                        <span className="hidden max-w-[120px] truncate sm:inline">{userName ? userName : 'Đăng nhập'}</span>
                    </button>
                    <Link
                        href="/gio-thue"
                        className="relative flex items-center gap-2 rounded-control bg-grass px-3.5 py-2 text-sm font-bold text-white transition hover:bg-pine"
                    >
                        Giỏ thuê
                        <span className="grid h-5 min-w-[20px] place-items-center rounded-full bg-campfire px-1 font-mono text-[11px] font-bold text-white">
                            {cartCount}
                        </span>
                    </Link>
                </div>
            </div>
        </header>
    );
}
