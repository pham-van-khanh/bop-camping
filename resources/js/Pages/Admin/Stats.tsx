import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useMemo, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { money } from '@/lib/format';
import type { PageProps } from '@/types';

type ChartPoint = { date: string; label: string; count: number };
type CategoryStat = { category: string; label: string; total: number; count: number };
type ExpenseRow = { id: number; spent_on: string; spent_on_label: string; amount: number; category: string; category_label: string; note: string | null };
type CategoryOption = { value: string; label: string };
type RevenueOrder = { id: number; code: string; amount: number };
type RevenueDay = {
    date: string;
    label: string;
    weekday: string;
    total: number;
    count: number;
    orders: RevenueOrder[];
};

type Props = PageProps<{
    period: 'today' | 'week' | 'month' | 'all';
    order_counts: { today: number; week: number; month: number; total: number };
    chart: ChartPoint[];
    finance: { revenue: number; expense: number; profit: number; returned_count: number };
    by_category: CategoryStat[];
    expenses: ExpenseRow[];
    categories: CategoryOption[];
    revenue_by_day: RevenueDay[];
    has_more_days: boolean;
}>;

const PERIODS: { key: Props['period']; label: string }[] = [
    { key: 'today', label: 'Hôm nay' },
    { key: 'week', label: 'Tuần này' },
    { key: 'month', label: 'Tháng này' },
    { key: 'all', label: 'Tất cả' },
];

const todayISO = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

export default function AdminStats() {
    const {
        period,
        order_counts,
        chart,
        finance,
        by_category,
        expenses,
        categories,
        revenue_by_day,
        has_more_days,
    } = usePage<Props>().props;

    const setPeriod = (p: Props['period']) =>
        router.get(route('admin.stats'), { period: p }, { preserveState: true, replace: true, preserveScroll: true });

    return (
        <>
            <Head title="Quản trị · Thống kê" />
            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-[22px] font-extrabold text-pine">Thống kê</h1>
                    <p className="mt-0.5 text-[13px] text-moss">Số đơn theo thời gian, thu chi và chi phí phát sinh</p>
                </div>

                {/* Số đơn theo kỳ */}
                <div className="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <StatTile label="Đơn hôm nay" value={order_counts.today} />
                    <StatTile label="Đơn tuần này" value={order_counts.week} />
                    <StatTile label="Đơn tháng này" value={order_counts.month} />
                    <StatTile label="Tổng đơn" value={order_counts.total} muted />
                </div>

                {/* Biểu đồ số đơn theo ngày */}
                <OrdersChart data={chart} />

                {/* Bộ lọc kỳ cho thu chi */}
                <div className="mb-3 mt-6 flex flex-wrap items-center gap-2">
                    <span className="mr-1 text-[13px] font-semibold text-pine">Thu chi:</span>
                    {PERIODS.map((p) => (
                        <button
                            key={p.key}
                            onClick={() => setPeriod(p.key)}
                            className={`rounded-pill border px-3.5 py-1.5 text-[12.5px] font-semibold transition ${
                                period === p.key ? 'border-grass bg-grass text-white' : 'border-cardBorder bg-white text-pine hover:border-grass'
                            }`}
                        >
                            {p.label}
                        </button>
                    ))}
                </div>

                {/* Thu chi lợi nhuận */}
                <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <FinanceTile label="Tổng thu" hint={`${finance.returned_count} đơn đã trả`} value={finance.revenue} color="#3a5a1f" />
                    <FinanceTile label="Tổng chi" hint="chi phí phát sinh" value={finance.expense} color="#C97B36" />
                    <FinanceTile label="Lợi nhuận" hint="thu − chi" value={finance.profit} color={finance.profit >= 0 ? '#3a5a1f' : '#b3493a'} sign />
                </div>

                <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    {/* Chi theo loại */}
                    <ExpenseByCategory rows={by_category} total={finance.expense} />
                    {/* Quản lý chi phí */}
                    <ExpenseManager expenses={expenses} categories={categories} />
                </div>

                {/* Doanh thu theo ngày */}
                <RevenueByDay days={revenue_by_day} hasMore={has_more_days} />
            </div>
        </>
    );
}

function StatTile({ label, value, muted }: { label: string; value: number; muted?: boolean }) {
    return (
        <div className="rounded-[14px] border border-cardBorder bg-white p-4">
            <div className="font-mono text-[26px] font-bold" style={{ color: muted ? '#8a967a' : '#18230F' }}>{value}</div>
            <div className="mt-0.5 text-[12px] text-moss">{label}</div>
        </div>
    );
}

function FinanceTile({ label, hint, value, color, sign }: { label: string; hint: string; value: number; color: string; sign?: boolean }) {
    return (
        <div className="rounded-[14px] border border-cardBorder bg-white p-4">
            <div className="text-[12px] font-semibold uppercase tracking-[0.04em] text-moss">{label}</div>
            <div className="mt-1 font-mono text-[22px] font-extrabold" style={{ color }}>
                {sign && value > 0 ? '+' : ''}{money(value)}
            </div>
            <div className="mt-0.5 text-[11.5px] text-[#a3ad92]">{hint}</div>
        </div>
    );
}

/** Biểu đồ cột số đơn/ngày (1 chuỗi, 1 màu, hover hiện chi tiết). */
function OrdersChart({ data }: { data: ChartPoint[] }) {
    const [hover, setHover] = useState<number | null>(null);
    const max = useMemo(() => Math.max(1, ...data.map((d) => d.count)), [data]);
    const totalRange = data.reduce((s, d) => s + d.count, 0);

    return (
        <div className="rounded-[16px] border border-cardBorder bg-white p-5">
            <div className="mb-1 flex items-baseline justify-between">
                <h2 className="text-[15px] font-bold text-ink">Số đơn 30 ngày gần đây</h2>
                <span className="text-[12px] text-moss">Tổng <span className="font-mono font-bold text-pine">{totalRange}</span> đơn · cao nhất {max}/ngày</span>
            </div>
            <div className="relative">
                {/* Cột */}
                <div className="flex h-[160px] items-end gap-[2px]">
                    {data.map((d, i) => (
                        <div
                            key={d.date}
                            className="relative flex-1"
                            style={{ height: '100%' }}
                            onMouseEnter={() => setHover(i)}
                            onMouseLeave={() => setHover(null)}
                        >
                            <div className="absolute inset-x-0 bottom-0 flex h-full items-end">
                                <div
                                    className="w-full rounded-t-[3px] transition-colors"
                                    style={{
                                        height: `${Math.max(d.count === 0 ? 2 : 6, (d.count / max) * 100)}%`,
                                        background: hover === i ? '#3a5a1f' : d.count === 0 ? '#e3e8d6' : '#557A2B',
                                    }}
                                />
                            </div>
                        </div>
                    ))}
                </div>
                {/* Tooltip */}
                {hover !== null && (
                    <div
                        className="pointer-events-none absolute -top-1 z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-[8px] bg-ink px-2.5 py-1.5 text-[11.5px] font-semibold text-white"
                        style={{ left: `${((hover + 0.5) / data.length) * 100}%` }}
                    >
                        {data[hover].label}: {data[hover].count} đơn
                    </div>
                )}
            </div>
            {/* Nhãn ngày thưa (mỗi ~5 ngày) */}
            <div className="mt-1.5 flex gap-[2px] text-[10px] text-[#a3ad92]">
                {data.map((d, i) => (
                    <div key={d.date} className="flex-1 text-center">{i % 5 === 0 ? d.label : ''}</div>
                ))}
            </div>
        </div>
    );
}

/** Chi theo loại — thanh tỉ lệ 1 màu (magnitude, không phải identity). */
function ExpenseByCategory({ rows, total }: { rows: CategoryStat[]; total: number }) {
    return (
        <div className="rounded-[16px] border border-cardBorder bg-white p-5">
            <h2 className="mb-3 text-[15px] font-bold text-ink">Chi theo loại</h2>
            {rows.length === 0 ? (
                <p className="py-6 text-center text-[13px] text-[#a3ad92]">Chưa có khoản chi nào trong kỳ.</p>
            ) : (
                <ul className="flex flex-col gap-2.5">
                    {rows.map((r) => (
                        <li key={r.category}>
                            <div className="mb-1 flex items-baseline justify-between text-[13px]">
                                <span className="text-ink">{r.label} <span className="text-[#a3ad92]">· {r.count}</span></span>
                                <span className="font-mono font-semibold text-campfire">{money(r.total)}</span>
                            </div>
                            <div className="h-2 overflow-hidden rounded-full bg-[#f1f2ed]">
                                <div className="h-full rounded-full" style={{ width: `${total > 0 ? (r.total / total) * 100 : 0}%`, background: '#C97B36' }} />
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/**
 * Doanh thu theo ngày — mỗi ngày một khối: cột trái là ngày + tổng, cột phải liệt kê
 * từng đơn (mã + tiền). Chỉ đơn đã trả, gom theo ngày trả nên tổng khớp ô "Tổng thu".
 */
function RevenueByDay({
    days,
    hasMore,
}: {
    days: RevenueDay[];
    hasMore: boolean;
}) {
    const totalOrders = days.reduce((s, d) => s + d.count, 0);

    return (
        <div className="mt-5 rounded-[16px] border border-cardBorder bg-white p-5">
            <div className="mb-1 flex items-baseline justify-between gap-3">
                <h2 className="text-[15px] font-bold text-ink">
                    Doanh thu theo ngày
                </h2>
                <span className="text-[12px] text-moss">
                    {days.length} ngày ·{' '}
                    <span className="font-mono font-bold text-pine">
                        {totalOrders}
                    </span>{' '}
                    đơn đã trả
                </span>
            </div>
            <p className="mb-2 text-[11.5px] text-[#a3ad92]">
                Tính theo ngày đánh dấu đã trả đơn, đã trừ giảm giá.
            </p>

            {days.length === 0 ? (
                <p className="py-6 text-center text-[13px] text-[#a3ad92]">
                    Chưa có đơn nào đã trả trong kỳ.
                </p>
            ) : (
                <ul className="max-h-[520px] overflow-y-auto">
                    {days.map((d) => (
                        <li
                            key={d.date}
                            className="flex flex-col gap-1 border-t border-[#f1f4ea] py-3 sm:flex-row sm:gap-5"
                        >
                            {/* Ngày + tổng của ngày */}
                            <div className="shrink-0 sm:w-[170px]">
                                <div className="font-mono text-[13.5px] font-bold text-pine">
                                    {d.label}
                                </div>
                                <div className="text-[11.5px] text-[#a3ad92]">
                                    {d.weekday} · {d.count} đơn
                                </div>
                                <div
                                    className="mt-0.5 font-mono text-[15px] font-extrabold"
                                    style={{ color: '#3a5a1f' }}
                                >
                                    {money(d.total)}
                                </div>
                            </div>

                            {/* Chi tiết từng đơn trong ngày */}
                            <div className="min-w-0 flex-1">
                                {d.orders.map((o) => (
                                    <div
                                        key={o.id}
                                        className="flex items-baseline justify-between gap-3 py-[3px] text-[12.5px]"
                                    >
                                        <Link
                                            href={route(
                                                'admin.orders.show',
                                                o.id,
                                            )}
                                            className="truncate font-mono font-semibold text-pine hover:text-grass hover:underline"
                                        >
                                            {o.code}
                                        </Link>
                                        <span className="shrink-0 font-mono font-semibold text-ink">
                                            {money(o.amount)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {hasMore && (
                <p className="mt-3 border-t border-[#f1f4ea] pt-2.5 text-[11.5px] text-[#a3ad92]">
                    Chỉ hiện 60 ngày gần nhất — tổng ở bảng này nhỏ hơn ô “Tổng
                    thu”.
                </p>
            )}
        </div>
    );
}

/** Thêm / sửa / xoá chi phí. */
function ExpenseManager({ expenses, categories }: { expenses: ExpenseRow[]; categories: CategoryOption[] }) {
    const [editId, setEditId] = useState<number | null>(null);
    const [confirmDelete, setConfirmDelete] = useState<number | null>(null);

    const form = useForm<{ spent_on: string; amount: string; category: string; note: string }>({
        spent_on: todayISO(),
        amount: '',
        category: categories[0]?.value ?? 'other',
        note: '',
    });

    const resetForm = () => {
        setEditId(null);
        form.reset();
        form.setData('spent_on', todayISO());
        form.clearErrors();
    };

    const startEdit = (e: ExpenseRow) => {
        setEditId(e.id);
        form.setData({ spent_on: e.spent_on, amount: String(e.amount), category: e.category, note: e.note ?? '' });
    };

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: () => resetForm() };
        if (editId) form.put(route('admin.expenses.update', editId), opts);
        else form.post(route('admin.expenses.store'), opts);
    };

    const remove = (id: number) =>
        router.delete(route('admin.expenses.destroy', id), { preserveScroll: true, onFinish: () => setConfirmDelete(null) });

    const canSubmit = form.data.spent_on !== '' && Number(form.data.amount) > 0 && !form.processing;

    return (
        <div className="rounded-[16px] border border-cardBorder bg-white p-5">
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-[15px] font-bold text-ink">Chi phí phát sinh</h2>
                {editId && <button onClick={resetForm} className="text-[12px] font-semibold text-moss underline">Huỷ sửa</button>}
            </div>

            {/* Form thêm/sửa */}
            <div className="mb-4 grid grid-cols-2 gap-2">
                <input
                    type="date" value={form.data.spent_on} onChange={(e) => form.setData('spent_on', e.target.value)}
                    className="h-10 rounded-[10px] border border-cardBorder bg-white px-2.5 text-[13px] text-ink outline-none focus:border-grass"
                />
                <input
                    type="number" min={1} value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} placeholder="Số tiền (đ)"
                    className="h-10 rounded-[10px] border border-cardBorder bg-white px-2.5 text-[13px] text-ink outline-none focus:border-grass"
                />
                <select
                    value={form.data.category} onChange={(e) => form.setData('category', e.target.value)}
                    className="h-10 rounded-[10px] border border-cardBorder bg-white px-2.5 text-[13px] text-ink outline-none focus:border-grass"
                >
                    {categories.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                </select>
                <input
                    value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} placeholder="Ghi chú" maxLength={255}
                    className="h-10 rounded-[10px] border border-cardBorder bg-white px-2.5 text-[13px] text-ink outline-none focus:border-grass"
                />
                <button
                    onClick={submit} disabled={!canSubmit}
                    className="col-span-2 h-10 rounded-[10px] text-[13px] font-bold text-white transition disabled:cursor-not-allowed"
                    style={{ background: canSubmit ? '#557A2B' : '#c4cfae' }}
                >
                    {form.processing ? 'Đang lưu…' : editId ? 'Cập nhật khoản chi' : 'Thêm khoản chi'}
                </button>
                {(form.errors.amount || form.errors.spent_on || form.errors.category) && (
                    <p className="col-span-2 text-[12px] text-red-500">{form.errors.amount || form.errors.spent_on || form.errors.category}</p>
                )}
            </div>

            {/* Danh sách chi phí */}
            {expenses.length === 0 ? (
                <p className="py-4 text-center text-[13px] text-[#a3ad92]">Chưa có khoản chi nào trong kỳ.</p>
            ) : (
                <div className="max-h-[280px] overflow-y-auto">
                    <table className="w-full text-[12.5px]">
                        <tbody>
                            {expenses.map((e) => (
                                <tr key={e.id} className="border-t border-[#f1f4ea]">
                                    <td className="py-2 pr-2 font-mono text-[11.5px] text-moss">{e.spent_on_label}</td>
                                    <td className="py-2 pr-2">
                                        <span className="rounded-pill px-1.5 py-0.5 text-[10.5px] font-bold" style={{ background: '#efe4d3', color: '#7f4f24' }}>{e.category_label}</span>
                                        {e.note && <span className="ml-1.5 text-[#8a967a]">{e.note}</span>}
                                    </td>
                                    <td className="py-2 pr-2 text-right font-mono font-semibold text-campfire">{money(e.amount)}</td>
                                    <td className="py-2 text-right">
                                        {confirmDelete === e.id ? (
                                            <span className="inline-flex gap-1">
                                                <button onClick={() => remove(e.id)} className="rounded-[7px] bg-[#f6ddd6] px-2 py-1 text-[11px] font-bold text-[#b3493a]">Xoá</button>
                                                <button onClick={() => setConfirmDelete(null)} className="rounded-[7px] border border-cardBorder px-2 py-1 text-[11px] font-semibold text-pine">Huỷ</button>
                                            </span>
                                        ) : (
                                            <span className="inline-flex gap-2">
                                                <button onClick={() => startEdit(e)} className="text-[11.5px] font-semibold text-pine hover:text-grass">Sửa</button>
                                                <button onClick={() => setConfirmDelete(e.id)} className="text-[11.5px] font-semibold text-[#b3493a] hover:underline">Xoá</button>
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

AdminStats.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
