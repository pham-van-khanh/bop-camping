import { Head, Link, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { money } from '@/lib/format';
import { STATUS_LABEL, STATUS_STYLE } from '@/lib/orderStatus';
import type { PageProps } from '@/types';

type Stats = {
    total: number;
    pending: number;
    confirmed: number;
    renting: number;
    returned: number;
    cancelled: number;
    revenue: number;
    revenue_month: number;
};

type RecentOrder = {
    id: number;
    code: string;
    customer_name: string;
    status: string;
    amount_due: number;
    created_at: string;
};

type Props = PageProps<{ stats: Stats; recent: RecentOrder[] }>;

const STATUS_ORDER = ['pending', 'confirmed', 'renting', 'returned', 'cancelled'] as const;

export default function AdminDashboard() {
    const { stats, recent } = usePage<Props>().props;

    return (
        <>
            <Head title="Admin · Tổng quan" />
            <div className="mx-auto max-w-[980px]">
                <h1 className="mb-1 text-[22px] font-extrabold text-ink">Tổng quan</h1>
                <p className="mb-5 text-[14px] text-moss">Tình hình đơn thuê và doanh thu của shop.</p>

                {/* Doanh thu */}
                <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <BigStat label="Doanh thu (đơn đã trả)" value={money(stats.revenue)} accent />
                    <BigStat label="Doanh thu tháng này" value={money(stats.revenue_month)} />
                    <BigStat label="Tổng số đơn" value={String(stats.total)} />
                </div>

                {/* Đơn theo trạng thái */}
                <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
                    {STATUS_ORDER.map((s) => (
                        <Link key={s} href={`/admin/orders?status=${s}`} className="rounded-[12px] border border-cardBorder bg-card p-3 transition hover:border-grass">
                            <div className="font-mono text-[20px] font-extrabold" style={{ color: STATUS_STYLE[s].color }}>{stats[s]}</div>
                            <div className="text-[12px] text-moss">{STATUS_LABEL[s]}</div>
                        </Link>
                    ))}
                </div>

                {/* Đơn mới gần đây */}
                <div className="rounded-card border border-cardBorder bg-card p-5">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-[16px] font-bold text-ink">Đơn mới gần đây</h2>
                        <Link href="/admin/orders" className="text-[13px] font-semibold text-grass hover:text-pine">Xem tất cả →</Link>
                    </div>

                    {recent.length === 0 ? (
                        <p className="py-6 text-center text-[14px] text-moss">Chưa có đơn nào.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-[14px]">
                                <thead>
                                    <tr className="border-b border-cardBorder text-left text-[12px] uppercase tracking-[0.04em] text-moss">
                                        <th className="py-2 pr-3 font-semibold">Mã đơn</th>
                                        <th className="py-2 pr-3 font-semibold">Khách</th>
                                        <th className="py-2 pr-3 font-semibold">Trạng thái</th>
                                        <th className="py-2 pr-3 text-right font-semibold">Trả khi nhận</th>
                                        <th className="py-2 font-semibold">Ngày tạo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recent.map((o) => (
                                        <tr key={o.id} className="border-b border-cardBorder/60 last:border-0">
                                            <td className="py-2.5 pr-3">
                                                <Link href={`/admin/orders?status=all`} className="font-mono font-bold text-grass hover:text-pine">{o.code}</Link>
                                            </td>
                                            <td className="py-2.5 pr-3 text-ink">{o.customer_name}</td>
                                            <td className="py-2.5 pr-3">
                                                <span className="inline-block rounded-full px-2.5 py-0.5 text-[12px] font-semibold"
                                                    style={{ color: STATUS_STYLE[o.status]?.color, background: STATUS_STYLE[o.status]?.bg }}>
                                                    {STATUS_LABEL[o.status] ?? o.status}
                                                </span>
                                            </td>
                                            <td className="py-2.5 pr-3 text-right font-semibold text-ink">{money(o.amount_due)}</td>
                                            <td className="py-2.5 text-moss">{o.created_at}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

function BigStat({ label, value, accent }: { label: string; value: string; accent?: boolean }) {
    return (
        <div className={`rounded-card border p-4 ${accent ? 'border-grass/40 bg-[#eef2e3]' : 'border-cardBorder bg-card'}`}>
            <div className={`font-mono text-[22px] font-extrabold ${accent ? 'text-pine' : 'text-ink'}`}>{value}</div>
            <div className="text-[13px] text-moss">{label}</div>
        </div>
    );
}

AdminDashboard.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
