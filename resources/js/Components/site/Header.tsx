import Logo from '@/Components/Logo';
import { emit, EVENTS } from '@/lib/bus';
import { Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const NAV = [
    { label: 'Trang chủ', href: '/' },
    { label: 'Thuê đồ', href: '/thiet-bi' },
    { label: 'Combo', href: '/combos' },
    { label: 'Giới thiệu', href: '/gioi-thieu' },
];

function isActive(current: string, href: string) {
    if (href === '/') return current === '/';
    return current.startsWith(href);
}

export default function Header({
    cartCount = 0,
    userName,
}: {
    cartCount?: number;
    userName?: string;
}) {
    const url = usePage().url;
    const { post, processing } = useForm({});
    const [menuOpen, setMenuOpen] = useState(false);
    // Menu tài khoản. Trước đây bấm vào tên là ĐĂNG XUẤT LUÔN — không hỏi gì, không có
    // đường lùi, mà cái tên trông chẳng giống nút đăng xuất. Nay nó mở menu.
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const userMenuRef = useRef<HTMLDivElement>(null);

    // Đăng nhập: tra cứu đơn nằm trong "Tài khoản" (bopcamping-7w8) — không hiện mục riêng.
    // Khách vãng lai: giữ "Tra cứu đơn" ở nav vì không vào được trang tài khoản.
    const nav = userName
        ? [...NAV, { label: 'Tài khoản', href: '/tai-khoan' }]
        : [...NAV, { label: 'Tra cứu đơn', href: '/tra-cuu' }];

    // Đóng menu khi đổi trang / nhấn Esc.
    useEffect(() => {
        setMenuOpen(false);
        setUserMenuOpen(false);
    }, [url]);
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key !== 'Escape') return;
            setMenuOpen(false);
            setUserMenuOpen(false);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    // Bấm ra ngoài thì đóng menu tài khoản. Dùng mousedown chứ không phải click: click nổ
    // SAU khi React đã xử lý nút bên trong, nên bấm lại chính cái nút sẽ đóng rồi mở lại
    // ngay — nhìn như menu không đóng được.
    useEffect(() => {
        if (!userMenuOpen) return;
        const onDown = (e: MouseEvent) => {
            if (!userMenuRef.current?.contains(e.target as Node)) {
                setUserMenuOpen(false);
            }
        };
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [userMenuOpen]);

    const handleUserClick = () => {
        if (userName) {
            setUserMenuOpen((v) => !v);
        } else {
            emit(EVENTS.openLogin);
        }
    };

    // Đang XEM HỘ khách thì đây không phải đăng xuất của khách mà là kết thúc phiên xem hộ —
    // server tự phân biệt ở GuestAuthController::destroy(), client không cần biết.
    const handleLogout = () => {
        setUserMenuOpen(false);
        post(route('guest.logout'));
    };

    return (
        <header
            className="sticky top-0 z-50 border-b border-[#c2dcec]"
            style={{
                background: 'rgba(221,239,250,.78)',
                backdropFilter: 'blur(14px)',
                WebkitBackdropFilter: 'blur(14px)',
            }}
        >
            <div className="mx-auto flex h-16 max-w-[1400px] items-center gap-4 px-4 sm:px-5">
                {/* Logo — chữ ẩn trên mobile, chỉ còn icon */}
                <Link href="/" className="flex shrink-0 items-center gap-2.5">
                    <Logo size={38} />
                    <span className="leading-tight">
                        <span className="block text-[15px] font-extrabold tracking-tight text-pine sm:text-[17px]">
                            BỐP CAMPING
                        </span>
                        <span className="hidden font-mono text-[10px] tracking-[0.14em] text-moss sm:block">
                            THUÊ ĐỒ DÃ NGOẠI
                        </span>
                    </span>
                </Link>

                {/* Menu desktop — ẩn trên mobile, hiện từ md */}
                <nav className="ml-2 hidden flex-1 items-center gap-1 overflow-x-auto md:flex">
                    {nav.map((n) => {
                        const active = isActive(url, n.href);
                        return (
                            <Link
                                key={n.href}
                                href={n.href}
                                className={`shrink-0 rounded-[10px] px-3.5 py-2 text-sm font-semibold transition ${
                                    active
                                        ? 'bg-grass text-white'
                                        : 'text-[#3f4a32] hover:bg-black/5'
                                }`}
                            >
                                {n.label}
                            </Link>
                        );
                    })}
                </nav>

                {/* Actions — đẩy sang phải; trên mobile là icon vuông */}
                <div className="ml-auto flex shrink-0 items-center gap-2">
                    {/* Menu ☰ (chỉ mobile) */}
                    <button
                        onClick={() => setMenuOpen((o) => !o)}
                        aria-label="Menu"
                        aria-expanded={menuOpen}
                        className="grid h-10 w-10 place-items-center rounded-control border border-cardBorder bg-card text-pine transition hover:border-grass md:hidden"
                    >
                        {menuOpen ? (
                            <svg
                                width="20"
                                height="20"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                            >
                                <path d="m6 6 12 12M18 6 6 18" />
                            </svg>
                        ) : (
                            <svg
                                width="20"
                                height="20"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                            >
                                <path d="M4 7h16M4 12h16M4 17h16" />
                            </svg>
                        )}
                    </button>

                    {/* Đăng nhập / tài khoản */}
                    <div className="relative" ref={userMenuRef}>
                        <button
                            onClick={handleUserClick}
                            disabled={processing}
                            title={
                                userName
                                    ? `Tài khoản (${userName})`
                                    : 'Đăng nhập'
                            }
                            aria-label={
                                userName
                                    ? `Tài khoản (${userName})`
                                    : 'Đăng nhập'
                            }
                            aria-haspopup={userName ? 'menu' : undefined}
                            aria-expanded={userName ? userMenuOpen : undefined}
                            className="flex h-10 w-10 items-center justify-center gap-2 rounded-control border border-cardBorder bg-card text-sm font-semibold text-pine transition hover:border-grass disabled:opacity-60 md:w-auto md:px-3"
                        >
                            {userName ? (
                                <span className="grid h-6 w-6 place-items-center rounded-full bg-grass/15 text-xs font-bold text-grass">
                                    {userName.trim().charAt(0).toUpperCase()}
                                </span>
                            ) : (
                                <svg
                                    width="19"
                                    height="19"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="1.8"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                >
                                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                                    <path d="m10 17 5-5-5-5" />
                                    <path d="M15 12H3" />
                                </svg>
                            )}
                            <span className="hidden max-w-[120px] truncate md:inline">
                                {userName ? userName : 'Đăng nhập'}
                            </span>
                            {userName && (
                                <svg
                                    className={`hidden text-moss transition-transform md:inline ${userMenuOpen ? 'rotate-180' : ''}`}
                                    width="12"
                                    height="12"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2.5"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    aria-hidden
                                >
                                    <path d="m6 9 6 6 6-6" />
                                </svg>
                            )}
                        </button>

                        {userName && userMenuOpen && (
                            <div
                                role="menu"
                                aria-label="Menu tài khoản"
                                className="absolute right-0 top-[calc(100%+8px)] z-50 w-[210px] overflow-hidden rounded-card border border-cardBorder bg-card shadow-cardhover"
                            >
                                <div className="border-b border-cardBorder px-4 py-2.5">
                                    <p className="truncate text-[13px] font-bold text-ink">
                                        {userName}
                                    </p>
                                </div>
                                <Link
                                    href="/tai-khoan"
                                    role="menuitem"
                                    onClick={() => setUserMenuOpen(false)}
                                    className="block px-4 py-2.5 text-[14px] font-semibold text-pine transition hover:bg-grass/10"
                                >
                                    Thông tin tài khoản
                                </Link>
                                <button
                                    role="menuitem"
                                    onClick={handleLogout}
                                    disabled={processing}
                                    className="block w-full border-t border-cardBorder px-4 py-2.5 text-left text-[14px] font-semibold text-campfire transition hover:bg-campfire/10 disabled:opacity-60"
                                >
                                    {processing ? 'Đang xử lý…' : 'Đăng xuất'}
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Giỏ thuê */}
                    <Link
                        href="/gio-thue"
                        title="Giỏ thuê"
                        aria-label={`Giỏ thuê (${cartCount})`}
                        className="relative flex h-10 w-10 items-center justify-center gap-2 rounded-control bg-grass text-sm font-bold text-white transition hover:bg-pine md:w-auto md:px-3.5"
                    >
                        <svg
                            width="19"
                            height="19"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="1.8"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        >
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

            {/* Dropdown nav (mobile) */}
            {menuOpen && (
                <>
                    <button
                        aria-label="Đóng menu"
                        onClick={() => setMenuOpen(false)}
                        className="fixed inset-0 top-16 z-40 cursor-default md:hidden"
                        style={{ background: 'rgba(24,35,15,.25)' }}
                    />
                    <nav
                        className="absolute inset-x-0 top-16 z-50 flex flex-col gap-1 border-b border-[#c2dcec] px-4 py-2.5 md:hidden"
                        style={{
                            background: 'rgba(228,243,251,.97)',
                            backdropFilter: 'blur(14px)',
                        }}
                    >
                        {nav.map((n) => {
                            const active = isActive(url, n.href);
                            return (
                                <Link
                                    key={n.href}
                                    href={n.href}
                                    onClick={() => setMenuOpen(false)}
                                    className={`rounded-[11px] px-4 py-3 text-[15px] font-semibold transition ${
                                        active
                                            ? 'bg-grass text-white'
                                            : 'text-[#3f4a32] hover:bg-black/5'
                                    }`}
                                >
                                    {n.label}
                                </Link>
                            );
                        })}
                    </nav>
                </>
            )}
        </header>
    );
}
