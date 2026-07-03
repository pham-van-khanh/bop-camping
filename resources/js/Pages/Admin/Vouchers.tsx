import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import SelectInput from '@/Components/admin/SelectInput';
import { voucherValueText, VOUCHER_SOURCE_LABEL, VOUCHER_SOURCE_FALLBACK, type VoucherType } from '@/lib/voucher';
import type { PageProps } from '@/types';

type Row = {
    id: number;
    code: string;
    type: VoucherType;
    value: number;
    source: string;
    status: 'active' | 'used' | 'expired' | 'revoked';
    // AC-8: voucher thường không giảm phần combo; bật cờ này mới áp lên combo
    applicable_to_combos: boolean;
    user: { name: string; phone: string | null } | null;
    expires_at: string | null;
    used_at: string | null;
};

type Paginator<T> = { data: T[]; prev_page_url: string | null; next_page_url: string | null; current_page: number; last_page: number };

type Props = PageProps<{
    vouchers: Paginator<Row>;
    filters: { status?: string; source?: string; q?: string };
}>;

const STATUS_STYLE: Record<string, { color: string; bg: string }> = {
    active: { color: '#3a5a1f', bg: '#dcebc4' },
    used: { color: '#5C6E47', bg: '#e7ecdc' },
    expired: { color: '#9a7a2a', bg: '#fbf2d8' },
    revoked: { color: '#b3493a', bg: '#f6ddd6' },
};
const STATUS_LABEL: Record<string, string> = { active: 'Đang dùng', used: 'Đã dùng', expired: 'Hết hạn', revoked: 'Thu hồi' };

export default function AdminVouchers() {
    const { vouchers, filters, flash } = usePage<Props>().props;
    const [toast, setToast] = useState('');

    useEffect(() => {
        if (flash.success) {
            setToast(flash.success);
            const t = setTimeout(() => setToast(''), 2500);
            return () => clearTimeout(t);
        }
    }, [flash.success]);

    const filter = (patch: Record<string, string>) =>
        router.get(route('admin.vouchers'), { ...filters, ...patch }, { preserveState: true, replace: true });

    const create = useForm({ phone: '', type: 'fixed', value: '', validity_days: '', applicable_to_combos: false });
    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        create.post(route('admin.vouchers.store'), { preserveScroll: true, onSuccess: () => create.reset() });
    };

    const revoke = (id: number) => router.patch(route('admin.vouchers.revoke', id), {}, { preserveScroll: true });

    return (
        <>
            <Head title="Admin · Voucher" />
            <div className="p-6">
                <h1 className="mb-4 text-[22px] font-extrabold text-ink">Voucher</h1>

                {toast && <div className="mb-4 rounded-[10px] border border-grass/40 bg-[#eef2e3] px-4 py-2.5 text-[14px] font-semibold text-pine">{toast}</div>}

                {/* Tạo voucher thủ công */}
                <form onSubmit={submitCreate} className="mb-5 flex flex-wrap items-end gap-3 rounded-card border border-cardBorder bg-card p-4">
                    <Field label="SĐT khách">
                        <input value={create.data.phone} onChange={(e) => create.setData('phone', e.target.value)} placeholder="09..." className={inputCls} />
                        {create.errors.phone && <span className="text-[12px] text-red-500">{create.errors.phone}</span>}
                    </Field>
                    <Field label="Kiểu giảm">
                        <SelectInput value={create.data.type} onChange={(v) => create.setData('type', v)} ariaLabel="Kiểu voucher" className="min-w-[150px]"
                            options={[{ value: 'fixed', label: 'Số tiền (đ)' }, { value: 'percent', label: 'Phần trăm (%)' }]} />
                    </Field>
                    <Field label="Giá trị">
                        <input type="number" step="any" value={create.data.value} onChange={(e) => create.setData('value', e.target.value)} className={inputCls} />
                        {create.errors.value && <span className="text-[12px] text-red-500">{create.errors.value}</span>}
                    </Field>
                    <Field label="HSD (ngày)">
                        <input type="number" value={create.data.validity_days} onChange={(e) => create.setData('validity_days', e.target.value)} placeholder="mặc định" className={inputCls} />
                    </Field>
                    {/* AC-8: mặc định voucher không giảm phần combo (combo đã là giá ưu đãi) */}
                    <label className="flex h-11 cursor-pointer items-center gap-2 rounded-[10px] border border-cardBorder bg-white px-3">
                        <input
                            type="checkbox"
                            checked={create.data.applicable_to_combos}
                            onChange={(e) => create.setData('applicable_to_combos', e.target.checked)}
                            className="accent-grass"
                        />
                        <span className="text-[13px] font-semibold text-pine">Áp dụng cả combo</span>
                    </label>
                    <button type="submit" disabled={create.processing} className="h-11 rounded-control bg-grass px-5 text-[14px] font-bold text-white disabled:opacity-60">Tạo voucher</button>
                </form>

                {/* Bộ lọc */}
                <div className="mb-3 flex flex-wrap items-center gap-2">
                    <span className="text-[13px] font-semibold text-moss">Lọc:</span>
                    <SelectInput value={filters.status ?? ''} onChange={(v) => filter({ status: v })} placeholder="Mọi trạng thái" ariaLabel="Lọc theo trạng thái" className="w-[170px]"
                        options={Object.entries(STATUS_LABEL).map(([value, label]) => ({ value, label }))} />
                    <SelectInput value={filters.source ?? ''} onChange={(v) => filter({ source: v })} placeholder="Mọi nguồn" ariaLabel="Lọc theo nguồn" className="w-[190px]"
                        options={Object.entries(VOUCHER_SOURCE_LABEL).map(([value, label]) => ({ value, label }))} />
                </div>

                <div className="overflow-x-auto rounded-card border border-cardBorder bg-card">
                    <table className="w-full text-[13px]">
                        <thead>
                            <tr className="border-b border-cardBorder text-left text-moss" style={{ background: '#f8faf4' }}>
                                <th className="px-3 py-2.5 font-semibold">Mã</th>
                                <th className="px-3 py-2.5 font-semibold">Khách</th>
                                <th className="px-3 py-2.5 font-semibold">Giá trị</th>
                                <th className="px-3 py-2.5 font-semibold">Nguồn</th>
                                <th className="px-3 py-2.5 font-semibold">Trạng thái</th>
                                <th className="px-3 py-2.5 font-semibold">HSD</th>
                                <th className="px-3 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {vouchers.data.length === 0 && (
                                <tr><td colSpan={7} className="px-3 py-6 text-center text-moss">Chưa có voucher nào.</td></tr>
                            )}
                            {vouchers.data.map((v) => (
                                <tr key={v.id} className="border-t border-[#eef2e3]">
                                    <td className="px-3 py-2.5 font-mono font-bold text-pine">{v.code}</td>
                                    <td className="px-3 py-2.5 text-ink">{v.user ? `${v.user.name} · ${v.user.phone}` : '—'}</td>
                                    <td className="px-3 py-2.5 font-semibold text-grass">
                                        {voucherValueText(v.type, v.value)}
                                        {v.applicable_to_combos && (
                                            <span className="ml-1.5 rounded-pill bg-[#e7eed5] px-1.5 py-0.5 font-mono text-[10px] font-bold text-[#3a5a1f]" title="Voucher này giảm được cả phần combo">
                                                +COMBO
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2.5 text-moss">{VOUCHER_SOURCE_LABEL[v.source] ?? VOUCHER_SOURCE_FALLBACK}</td>
                                    <td className="px-3 py-2.5">
                                        <span className="rounded-pill px-2.5 py-1 text-[11px] font-bold" style={STATUS_STYLE[v.status]}>{STATUS_LABEL[v.status]}</span>
                                    </td>
                                    <td className="px-3 py-2.5 text-moss">{v.expires_at ?? '—'}</td>
                                    <td className="px-3 py-2.5 text-right">
                                        {v.status === 'active' && (
                                            <button onClick={() => revoke(v.id)} className="text-[12px] font-semibold text-[#b3493a] hover:underline">Thu hồi</button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={vouchers} />
            </div>
        </>
    );
}

const inputCls = 'h-11 rounded-[10px] border border-cardBorder bg-white px-3 text-[14px] text-ink outline-none focus:border-grass';

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="flex flex-col gap-1">
            <span className="text-[12px] font-semibold text-moss">{label}</span>
            {children}
        </label>
    );
}

export function Pagination({ paginator }: { paginator: { prev_page_url: string | null; next_page_url: string | null; current_page: number; last_page: number } }) {
    if (paginator.last_page <= 1) return null;
    return (
        <div className="mt-4 flex items-center justify-center gap-3 text-[13px]">
            <button disabled={!paginator.prev_page_url} onClick={() => paginator.prev_page_url && router.get(paginator.prev_page_url)}
                className="rounded-control border border-cardBorder px-3 py-1.5 font-semibold text-pine disabled:opacity-40">← Trước</button>
            <span className="text-moss">Trang {paginator.current_page}/{paginator.last_page}</span>
            <button disabled={!paginator.next_page_url} onClick={() => paginator.next_page_url && router.get(paginator.next_page_url)}
                className="rounded-control border border-cardBorder px-3 py-1.5 font-semibold text-pine disabled:opacity-40">Sau →</button>
        </div>
    );
}

AdminVouchers.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
