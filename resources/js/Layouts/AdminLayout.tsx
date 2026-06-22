import { Link, router, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';
import type { PageProps } from '@/types';

const NAV = [
    {
        href: '/admin/orders',
        name: 'admin.orders',
        label: 'Đơn thuê',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" strokeWidth="1.8" />
                <path d="M7 8h10M7 12h7M7 16h5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
            </svg>
        ),
    },
    {
        href: '/admin/products',
        name: 'admin.products',
        label: 'Sản phẩm',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M20 7H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1Z" stroke="currentColor" strokeWidth="1.8" />
                <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" stroke="currentColor" strokeWidth="1.8" />
            </svg>
        ),
    },
    {
        href: '/admin/categories',
        name: 'admin.categories',
        label: 'Danh mục',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M4 6h16M4 12h10M4 18h7" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
            </svg>
        ),
    },
    {
        href: '/admin/users',
        name: 'admin.users',
        label: 'Người dùng',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M16 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
                <circle cx="9" cy="7" r="3" stroke="currentColor" strokeWidth="1.8" />
                <path d="M21 19v-1a4 4 0 0 0-3-3.87M16 4.13A4 4 0 0 1 16 11.5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
            </svg>
        ),
    },
];

export default function AdminLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage<PageProps>().props;
    const currentRoute = usePage().url;

    const logout = () => {
        router.post('/admin/logout');
    };

    const isActive = (href: string) => currentRoute.startsWith(href);

    return (
        <div className="flex min-h-screen" style={{ background: '#f4f6ef' }}>
            {/* Sidebar */}
            <aside className="flex w-[220px] flex-none flex-col border-r border-cardBorder" style={{ background: '#fff' }}>
                {/* Logo */}
                <div className="flex h-[60px] items-center gap-2.5 border-b border-cardBorder px-5">
                    <span className="grid h-8 w-8 place-items-center rounded-[9px] bg-grass">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M12 3 3 20h18L12 3Z" fill="#cfe0a8" />
                            <path d="M12 9 7 20h10L12 9Z" fill="#557A2B" />
                        </svg>
                    </span>
                    <div>
                        <div className="text-[13px] font-extrabold leading-none text-pine">BopCamping</div>
                        <div className="mt-0.5 text-[10px] font-mono text-moss">ADMIN</div>
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
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                {/* User + logout */}
                <div className="border-t border-cardBorder p-3">
                    <div className="mb-2 px-2 text-[12px] text-moss">
                        <div className="font-semibold text-pine">{auth.user?.name}</div>
                        <div className="font-mono">{auth.user?.phone}</div>
                    </div>
                    <button
                        onClick={logout}
                        className="flex w-full items-center gap-2 rounded-[10px] px-3 py-2 text-[13px] font-semibold text-[#b3493a] transition hover:bg-red-50"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                        Đăng xuất
                    </button>
                </div>
            </aside>

            {/* Main content */}
            <main className="flex-1 overflow-auto">
                {children}
            </main>
        </div>
    );
}
