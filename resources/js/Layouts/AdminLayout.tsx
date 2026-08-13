import Logo from '@/Components/Logo';
import type { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

const NAV = [
    {
        href: '/admin/dashboard',
        name: 'admin.dashboard',
        label: 'Tổng quan',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <rect
                    x="3"
                    y="3"
                    width="7"
                    height="9"
                    rx="1.5"
                    stroke="currentColor"
                    strokeWidth="1.8"
                />
                <rect
                    x="14"
                    y="3"
                    width="7"
                    height="5"
                    rx="1.5"
                    stroke="currentColor"
                    strokeWidth="1.8"
                />
                <rect
                    x="14"
                    y="12"
                    width="7"
                    height="9"
                    rx="1.5"
                    stroke="currentColor"
                    strokeWidth="1.8"
                />
                <rect
                    x="3"
                    y="16"
                    width="7"
                    height="5"
                    rx="1.5"
                    stroke="currentColor"
                    strokeWidth="1.8"
                />
            </svg>
        ),
    },
    {
        href: '/admin/stats',
        name: 'admin.stats',
        label: 'Thống kê',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path
                    d="M4 20V10M10 20V4M16 20v-7M22 20H2"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </svg>
        ),
    },
    {
        href: '/admin/tai-chinh',
        name: 'admin.finance',
        label: 'Tài chính',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path
                    d="M12 3v18M16.5 7.5c-.7-1.3-2.3-2-4.5-2-2.5 0-4 1-4 2.8 0 4 8.5 2.2 8.5 6.4 0 1.9-1.7 3-4.5 3-2.4 0-4-.8-4.7-2.2"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </svg>
        ),
    },
    {
        href: '/admin/orders',
        name: 'admin.orders',
        label: 'Đơn thuê',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <rect
                    x="3"
                    y="4"
                    width="18"
                    height="16"
                    rx="2"
                    stroke="currentColor"
                    strokeWidth="1.8"
                />
                <path
                    d="M7 8h10M7 12h7M7 16h5"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                />
            </svg>
        ),
    },
    {
        href: '/admin/lich-giao',
        name: 'admin.schedule',
        label: 'Lịch giao',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <rect
                    x="3"
                    y="4"
                    width="18"
                    height="17"
                    rx="2"
                    stroke="currentColor"
                    strokeWidth="1.8"
                />
                <path
                    d="M3 9h18M8 2.5v3M16 2.5v3"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                />
                <path
                    d="M8 13.5h3M8 17h6"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                />
            </svg>
        ),
    },
    {
        href: '/admin/products',
        name: 'admin.products',
        label: 'Sản phẩm',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path
                    d="M20 7H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1Z"
                    stroke="currentColor"
                    strokeWidth="1.8"
                />
                <path
                    d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"
                    stroke="currentColor"
                    strokeWidth="1.8"
                />
            </svg>
        ),
    },
    {
        href: '/admin/combos',
        name: 'admin.combos',
        label: 'Combo',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path
                    d="M12 3 3.5 7.5v9L12 21l8.5-4.5v-9L12 3Z"
                    stroke="currentColor"
                    strokeWidth="1.7"
                    strokeLinejoin="round"
                />
                <path
                    d="M3.5 7.5 12 12l8.5-4.5M12 12v9"
                    stroke="currentColor"
                    strokeWidth="1.7"
                    strokeLinejoin="round"
                />
            </svg>
        ),
    },
    {
        href: '/admin/categories',
        name: 'admin.categories',
        label: 'Danh mục',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path
                    d="M4 6h16M4 12h10M4 18h7"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                />
            </svg>
        ),
    },
    {
        href: '/admin/users',
        name: 'admin.users',
        label: 'Người dùng',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path
                    d="M16 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                />
                <circle
                    cx="9"
                    cy="7"
                    r="3"
                    stroke="currentColor"
                    strokeWidth="1.8"
                />
                <path
                    d="M21 19v-1a4 4 0 0 0-3-3.87M16 4.13A4 4 0 0 1 16 11.5"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                />
            </svg>
        ),
    },
    {
        href: '/admin/vouchers',
        name: 'admin.vouchers',
        label: 'Voucher',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path
                    d="M3 9V6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v3a2 2 0 0 0 0 4v3a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-3a2 2 0 0 0 0-4Z"
                    stroke="currentColor"
                    strokeWidth="1.8"
                />
                <path
                    d="M14 5v14"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    strokeDasharray="2 2"
                />
            </svg>
        ),
    },
    {
        href: '/admin/referrals',
        name: 'admin.referrals',
        label: 'Giới thiệu',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <circle
                    cx="6"
                    cy="12"
                    r="2.4"
                    stroke="currentColor"
                    strokeWidth="1.8"
                />
                <circle
                    cx="18"
                    cy="6"
                    r="2.4"
                    stroke="currentColor"
                    strokeWidth="1.8"
                />
                <circle
                    cx="18"
                    cy="18"
                    r="2.4"
                    stroke="currentColor"
                    strokeWidth="1.8"
                />
                <path
                    d="M8.2 11 16 6.6M8.2 13 16 17.4"
                    stroke="currentColor"
                    strokeWidth="1.8"
                />
            </svg>
        ),
    },
    {
        href: '/admin/promotion',
        name: 'admin.promotion',
        label: 'Khuyến mãi',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <circle
                    cx="12"
                    cy="12"
                    r="9"
                    stroke="currentColor"
                    strokeWidth="1.8"
                />
                <path
                    d="M9 15 15 9M9.5 9.5h.01M14.5 14.5h.01"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                />
            </svg>
        ),
    },
    {
        href: '/admin/reviews',
        name: 'admin.reviews',
        label: 'Đánh giá',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path
                    d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 17l-5.2 2.6 1-5.8L3.5 9.7l5.9-.9L12 3.5z"
                    stroke="currentColor"
                    strokeWidth="1.6"
                    strokeLinejoin="round"
                />
            </svg>
        ),
    },
    {
        href: '/admin/camping-spots',
        name: 'admin.camping-spots',
        label: 'Điểm cắm trại',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path
                    d="M12 4 3 20h18L12 4Z"
                    stroke="currentColor"
                    strokeWidth="1.7"
                    strokeLinejoin="round"
                />
                <path
                    d="M12 11 7 20h10l-5-9Z"
                    stroke="currentColor"
                    strokeWidth="1.5"
                    strokeLinejoin="round"
                />
            </svg>
        ),
    },
    {
        href: '/admin/banners',
        name: 'admin.banners',
        label: 'Banner',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <rect
                    x="3"
                    y="5"
                    width="18"
                    height="14"
                    rx="2"
                    stroke="currentColor"
                    strokeWidth="1.7"
                />
                <path
                    d="m3 15 5-4 4 3 4-4 5 4"
                    stroke="currentColor"
                    strokeWidth="1.6"
                    strokeLinejoin="round"
                />
                <circle cx="9" cy="9" r="1.3" fill="currentColor" />
            </svg>
        ),
    },
    {
        href: '/admin/faqs',
        name: 'admin.faqs',
        label: 'FAQ',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <circle
                    cx="12"
                    cy="12"
                    r="9"
                    stroke="currentColor"
                    strokeWidth="1.7"
                />
                <path
                    d="M9.5 9.2a2.5 2.5 0 0 1 4.5 1.4c0 1.6-2 2-2 3.4"
                    stroke="currentColor"
                    strokeWidth="1.7"
                    strokeLinecap="round"
                />
                <circle
                    cx="12"
                    cy="17"
                    r="0.6"
                    fill="currentColor"
                    stroke="currentColor"
                    strokeWidth="0.9"
                />
            </svg>
        ),
    },
    {
        href: '/admin/gop-y',
        name: 'admin.feedbacks',
        label: 'Góp ý',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path
                    d="M21 11.5a8.4 8.4 0 0 1-8.5 8.3c-1.4 0-2.8-.3-4-1L3 20l1.3-4.2a8 8 0 0 1-1.3-4.3A8.4 8.4 0 0 1 11.5 3 8.4 8.4 0 0 1 21 11.5Z"
                    stroke="currentColor"
                    strokeWidth="1.7"
                    strokeLinejoin="round"
                />
                <path
                    d="M8.5 10.5h7M8.5 13.5h4.5"
                    stroke="currentColor"
                    strokeWidth="1.7"
                    strokeLinecap="round"
                />
            </svg>
        ),
    },
    {
        href: '/admin/pages',
        name: 'admin.pages',
        label: 'Trang nội dung',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path
                    d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"
                    stroke="currentColor"
                    strokeWidth="1.7"
                    strokeLinejoin="round"
                />
                <path
                    d="M14 3v5h5M9 12h6M9 16h6"
                    stroke="currentColor"
                    strokeWidth="1.7"
                    strokeLinecap="round"
                />
            </svg>
        ),
    },
    {
        href: '/admin/settings',
        name: 'admin.settings',
        label: 'Cài đặt shop',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <circle
                    cx="12"
                    cy="12"
                    r="3.2"
                    stroke="currentColor"
                    strokeWidth="1.7"
                />
                <path
                    d="M12 3v2.5M12 18.5V21M4.2 7.5l2.2 1.3M17.6 15.2l2.2 1.3M4.2 16.5l2.2-1.3M17.6 8.8l2.2-1.3"
                    stroke="currentColor"
                    strokeWidth="1.7"
                    strokeLinecap="round"
                />
            </svg>
        ),
    },
];

export default function AdminLayout({ children }: { children: ReactNode }) {
    const { auth, pending_reviews, pending_orders, pending_feedback } =
        usePage<PageProps>().props;
    const currentRoute = usePage().url;

    const logout = () => {
        router.post('/admin/logout');
    };

    const isActive = (href: string) => currentRoute.startsWith(href);

    return (
        <div className="flex min-h-screen" style={{ background: '#f4f6ef' }}>
            {/* Sidebar */}
            <aside
                className="flex w-[220px] flex-none flex-col border-r border-cardBorder"
                style={{ background: '#fff' }}
            >
                {/* Logo */}
                <div className="flex h-[60px] items-center gap-2.5 border-b border-cardBorder px-5">
                    <Logo size={32} />
                    <div>
                        <div className="text-[13px] font-extrabold leading-none text-pine">
                            BopCamping
                        </div>
                        <div className="mt-0.5 font-mono text-[10px] text-moss">
                            ADMIN
                        </div>
                    </div>
                </div>

                {/* Nav */}
                <nav className="flex-1 px-3 py-4">
                    {NAV.map((item) => {
                        const active = isActive(item.href);
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={`mb-1 flex items-center gap-2.5 rounded-[10px] px-3 py-2.5 text-[13.5px] font-semibold transition ${
                                    active
                                        ? 'bg-grass text-white'
                                        : 'text-pine hover:bg-[#f1f4ea] hover:text-pine'
                                }`}
                            >
                                {item.icon}
                                <span className="flex-1">{item.label}</span>
                                {item.name === 'admin.reviews' &&
                                    !!pending_reviews && (
                                        <span
                                            className={`grid h-5 min-w-5 place-items-center rounded-full px-1.5 font-mono text-[11px] font-bold ${active ? 'bg-white text-grass' : 'bg-campfire text-white'}`}
                                        >
                                            {pending_reviews}
                                        </span>
                                    )}
                                {item.name === 'admin.orders' &&
                                    !!pending_orders && (
                                        <span
                                            className={`grid h-5 min-w-5 place-items-center rounded-full px-1.5 font-mono text-[11px] font-bold ${active ? 'bg-white text-grass' : 'bg-campfire text-white'}`}
                                        >
                                            {pending_orders}
                                        </span>
                                    )}
                                {item.name === 'admin.feedbacks' &&
                                    !!pending_feedback && (
                                        <span
                                            className={`grid h-5 min-w-5 place-items-center rounded-full px-1.5 font-mono text-[11px] font-bold ${active ? 'bg-white text-grass' : 'bg-campfire text-white'}`}
                                        >
                                            {pending_feedback}
                                        </span>
                                    )}
                            </Link>
                        );
                    })}
                </nav>

                {/* User + logout */}
                <div className="border-t border-cardBorder p-3">
                    <div className="mb-2 px-2 text-[12px] text-moss">
                        <div className="font-semibold text-pine">
                            {auth.user?.name}
                        </div>
                        <div className="font-mono">{auth.user?.phone}</div>
                    </div>
                    <button
                        onClick={logout}
                        className="flex w-full items-center gap-2 rounded-[10px] px-3 py-2 text-[13px] font-semibold text-[#b3493a] transition hover:bg-red-50"
                    >
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                        >
                            <path
                                d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"
                                stroke="currentColor"
                                strokeWidth="1.8"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                        </svg>
                        Đăng xuất
                    </button>
                </div>
            </aside>

            {/* Main content */}
            <main className="flex-1 overflow-auto">{children}</main>
        </div>
    );
}
