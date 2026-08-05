// Layout khu vực shipper (bopcamping-lsch) — CỐ TÌNH tối giản: chỉ tên người đăng nhập
// và nút đăng xuất. KHÔNG có nav admin, không link sang dashboard/thống kê/sản phẩm:
// shipper chỉ có một việc là xem lịch của mình (adr_shipper_role_and_access mục 3).
import Logo from '@/Components/Logo';
import { router, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

export default function ShipperLayout({ children }: { children: ReactNode }) {
    const user = (
        usePage().props as { auth?: { user?: { name?: string } | null } }
    ).auth?.user;

    return (
        <div className="min-h-screen" style={{ background: '#f4f6ef' }}>
            <header className="sticky top-0 z-10 border-b border-cardBorder bg-white">
                <div className="mx-auto flex max-w-[720px] items-center justify-between gap-3 px-4 py-2.5">
                    <div className="flex items-center gap-2.5">
                        <Logo size={34} />
                        <div>
                            <div className="text-[14px] font-extrabold leading-tight text-pine">
                                Lịch giao
                            </div>
                            <div className="text-[12px] leading-tight text-moss">
                                {user?.name ?? 'Shipper'}
                            </div>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => router.post(route('shipper.logout'))}
                        className="min-h-[40px] rounded-[10px] border border-cardBorder px-3 text-[13px] font-semibold text-moss transition hover:border-grass hover:text-grass"
                    >
                        Đăng xuất
                    </button>
                </div>
            </header>
            <main className="mx-auto max-w-[720px] px-4 py-4">{children}</main>
        </div>
    );
}
