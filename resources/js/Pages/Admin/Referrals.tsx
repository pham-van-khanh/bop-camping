import SelectInput from '@/Components/admin/SelectInput';
import AdminLayout from '@/Layouts/AdminLayout';
import { Pagination } from '@/Pages/Admin/Vouchers';
import type { PageProps } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

type Party = { name: string; phone: string | null } | null;
type Row = {
    id: number;
    referrer: Party;
    referee: Party;
    code_used: string;
    status: 'pending' | 'converted' | 'rejected' | 'expired';
    first_order_code: string | null;
    converted_at: string | null;
    created_at: string;
};

type Paginator<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = PageProps<{
    referrals: Paginator<Row>;
    filters: { status?: string };
}>;

const STATUS_STYLE: Record<string, { color: string; bg: string }> = {
    pending: { color: '#9a7a2a', bg: '#fbf2d8' },
    converted: { color: '#3a5a1f', bg: '#dcebc4' },
    rejected: { color: '#b3493a', bg: '#f6ddd6' },
    expired: { color: '#5C6E47', bg: '#e7ecdc' },
};
const STATUS_LABEL: Record<string, string> = {
    pending: 'Chờ',
    converted: 'Thành công',
    rejected: 'Từ chối',
    expired: 'Hết hạn',
};

export default function AdminReferrals() {
    const { referrals, filters } = usePage<Props>().props;

    const filter = (status: string) =>
        router.get(
            route('admin.referrals'),
            { status },
            { preserveState: true, replace: true },
        );

    const party = (p: Party) => (p ? `${p.name} · ${p.phone ?? '—'}` : '—');

    return (
        <>
            <Head title="Admin · Giới thiệu" />
            <div className="p-6">
                <h1 className="mb-4 text-[22px] font-extrabold text-ink">
                    Lượt giới thiệu
                </h1>

                <div className="mb-3 flex flex-wrap items-center gap-2">
                    <span className="text-[13px] font-semibold text-moss">
                        Lọc:
                    </span>
                    <SelectInput
                        value={filters.status ?? ''}
                        onChange={(v) => filter(v)}
                        placeholder="Mọi trạng thái"
                        ariaLabel="Lọc theo trạng thái"
                        className="w-[180px]"
                        options={Object.entries(STATUS_LABEL).map(
                            ([value, label]) => ({ value, label }),
                        )}
                    />
                </div>

                <div className="overflow-x-auto rounded-card border border-cardBorder bg-card">
                    <table className="w-full text-[13px]">
                        <thead>
                            <tr
                                className="border-b border-cardBorder text-left text-moss"
                                style={{ background: '#f8faf4' }}
                            >
                                <th className="px-3 py-2.5 font-semibold">
                                    Người giới thiệu
                                </th>
                                <th className="px-3 py-2.5 font-semibold">
                                    Người được mời
                                </th>
                                <th className="px-3 py-2.5 font-semibold">
                                    Mã
                                </th>
                                <th className="px-3 py-2.5 font-semibold">
                                    Đơn đầu
                                </th>
                                <th className="px-3 py-2.5 font-semibold">
                                    Trạng thái
                                </th>
                                <th className="px-3 py-2.5 font-semibold">
                                    Chuyển đổi
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {referrals.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-3 py-6 text-center text-moss"
                                    >
                                        Chưa có lượt giới thiệu nào.
                                    </td>
                                </tr>
                            )}
                            {referrals.data.map((r) => (
                                <tr
                                    key={r.id}
                                    className="border-t border-[#eef2e3]"
                                >
                                    <td className="px-3 py-2.5 text-ink">
                                        {party(r.referrer)}
                                    </td>
                                    <td className="px-3 py-2.5 text-ink">
                                        {party(r.referee)}
                                    </td>
                                    <td className="px-3 py-2.5 font-mono font-bold text-pine">
                                        {r.code_used}
                                    </td>
                                    <td className="px-3 py-2.5 font-mono text-grass">
                                        {r.first_order_code ?? '—'}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <span
                                            className="rounded-pill px-2.5 py-1 text-[11px] font-bold"
                                            style={STATUS_STYLE[r.status]}
                                        >
                                            {STATUS_LABEL[r.status]}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2.5 text-moss">
                                        {r.converted_at ?? r.created_at}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={referrals} />
            </div>
        </>
    );
}

AdminReferrals.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
