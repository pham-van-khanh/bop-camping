import { Link, useForm, usePage } from '@inertiajs/react';
import { emit, EVENTS } from '@/lib/bus';

const NAV = [
    { label: 'Trang chủ', href: '/' },
    { label: 'Thuê đồ', href: '/thiet-bi' },
    { label: 'Tra cứu đơn', href: '/tra-cuu' },
];

function isActive(current: string, href: string) {
    if (href === '/') return current === '/';
    return current.startsWith(href);
}

export default function Header({ cartCount = 0, userName }: { cartCount?: number; userName?: string }) {
    const url = usePage().url;
    const { post, processing } = useForm({});

    // Thêm mục "Tài khoản" khi đã đăng nhập
    const nav = userName ? [...NAV, { label: 'Tài khoản', href: '/tai-khoan' }] : NAV;

    const handleUserClick = () => {
        if (userName) {
            // Đăng xuất nếu đang đăng nhập
            post(route('guest.logout'));
        } else {
            emit(EVENTS.openLogin);
        }
    };

    return (
        <header
            className="sticky top-0 z-50 border-b border-[#c2dcec]"
            style={{ background: 'rgba(221,239,250,.78)', backdropFilter: 'blur(14px)', WebkitBackdropFilter: 'blur(14px)' }}
        >
            <div className="mx-auto flex h-16 max-w-[1200px] items-center gap-4 px-4 sm:px-5">
                {/* Logo — chữ ẩn trên mobile, chỉ còn icon */}
                <Link href="/" className="flex shrink-0 items-center gap-2.5">
                    <span className="grid h-[38px] w-[38px] place-items-center rounded-[11px] bg-grass">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2" strokeLinejoin="round">
                            <path d="M12 4 L21 20 H3 Z" />
                            <path d="M12 4 V20 M9 20 l3 -6 3 6" />
                        </svg>
                    </span>
                    <span className="hidden leading-tight md:block">
                        <span className="block text-[17px] font-extrabold tracking-tight text-pine">BỐP CAMPING</span>
                        <span className="block font-mono text-[10px] tracking-[0.14em] text-moss">THUÊ ĐỒ DÃ NGOẠI</span>
                    </span>
                </Link>

                {/* Menu — ẩn trên mobile, hiện từ md */}
                <nav className="ml-2 hidden flex-1 items-center gap-1 overflow-x-auto md:flex">
                    {nav.map((n) => {
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

                {/* Actions — đẩy sang phải; trên mobile là icon vuông */}
                <div className="ml-auto flex shrink-0 items-center gap-2">
                    {/* Đăng nhập / tài khoản */}
                    <button
                        onClick={handleUserClick}
                        disabled={processing}
                        title={userName ? `Đăng xuất (${userName})` : 'Đăng nhập'}
                        aria-label={userName ? `Đăng xuất (${userName})` : 'Đăng nhập'}
                        className="flex h-10 w-10 items-center justify-center gap-2 rounded-control border border-cardBorder bg-card text-sm font-semibold text-pine transition hover:border-grass disabled:opacity-60 md:w-auto md:px-3"
                    >
                        {userName ? (
                            <span className="grid h-6 w-6 place-items-center rounded-full bg-grass/15 text-xs font-bold text-grass">
                                {userName.trim().charAt(0).toUpperCase()}
                            </span>
                        ) : (
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                                <path d="m10 17 5-5-5-5" />
                                <path d="M15 12H3" />
                            </svg>
                        )}
                        <span className="hidden max-w-[120px] truncate md:inline">{userName ? userName : 'Đăng nhập'}</span>
                        {userName && <span className="hidden text-[11px] text-moss md:inline">↩</span>}
                    </button>

                    {/* Giỏ thuê */}
                    <Link
                        href="/gio-thue"
                        title="Giỏ thuê"
                        aria-label={`Giỏ thuê (${cartCount})`}
                        className="relative flex h-10 w-10 items-center justify-center gap-2 rounded-control bg-grass text-sm font-bold text-white transition hover:bg-pine md:w-auto md:px-3.5"
                    >
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                            <path d="M3 6h18" />
                            <path d="M16 10a4 4 0 0 1-8 0" />
                        </svg>
                        <span className="hidden md:inline">Giỏ thuê</span>
                        <span className="absolute -right-1.5 -top-1.5 grid h-5 min-w-[20px] place-items-center rounded-full bg-campfire px-1 font-mono text-[11px] font-bold text-white md:static md:right-auto md:top-auto">
                            {cartCount}
                        </span>
                    </Link>
                </div>
            </div>
        </header>
    );
}
