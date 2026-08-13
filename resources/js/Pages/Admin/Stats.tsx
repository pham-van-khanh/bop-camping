import AdminLayout from '@/Layouts/AdminLayout';
import { money } from '@/lib/format';
import type { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Fragment, ReactNode, useMemo, useState } from 'react';

type ChartPoint = { date: string; label: string; count: number };
type RevenueOrder = { id: number; code: string; amount: number };
type RevenueDay = {
    date: string;
    label: string;
    total: number;
    orders: RevenueOrder[];
};
type MonthOption = { value: string; label: string };

type Props = PageProps<{
    period: 'today' | 'week' | 'month' | 'all';
    order_counts: { today: number; week: number; month: number; total: number };
    chart: ChartPoint[];
    finance: {
        revenue: number;
        expense: number;
        profit: number;
        returned_count: number;
    };
    revenue_by_day: RevenueDay[];
    revenue_month: string;
    revenue_months: MonthOption[];
}>;

const PERIODS: { key: Props['period']; label: string }[] = [
    { key: 'today', label: 'Hôm nay' },
    { key: 'week', label: 'Tuần này' },
    { key: 'month', label: 'Tháng này' },
    { key: 'all', label: 'Tất cả' },
];

export default function AdminStats() {
    const {
        period,
        order_counts,
        chart,
        finance,
        revenue_by_day,
        revenue_month,
        revenue_months,
    } = usePage<Props>().props;

    // Giữ lại tham số kia khi đổi một bộ lọc — nếu không, đổi tháng sẽ reset kỳ thu chi.
    const go = (params: { period?: Props['period']; month?: string }) =>
        router.get(
            route('admin.stats'),
            { period, month: revenue_month, ...params },
            { preserveState: true, replace: true, preserveScroll: true },
        );

    return (
        <>
            <Head title="Quản trị · Thống kê" />
            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-[22px] font-extrabold text-pine">
                        Thống kê
                    </h1>
                    <p className="mt-0.5 text-[13px] text-moss">
                        Số đơn theo thời gian, thu chi và chi phí phát sinh
                    </p>
                </div>

                {/* Số đơn theo kỳ */}
                <div className="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <StatTile label="Đơn hôm nay" value={order_counts.today} />
                    <StatTile label="Đơn tuần này" value={order_counts.week} />
                    <StatTile
                        label="Đơn tháng này"
                        value={order_counts.month}
                    />
                    <StatTile
                        label="Tổng đơn"
                        value={order_counts.total}
                        muted
                    />
                </div>

                {/* Biểu đồ số đơn theo ngày */}
                <OrdersChart data={chart} />

                {/* Bộ lọc kỳ cho thu chi */}
                <div className="mb-3 mt-6 flex flex-wrap items-center gap-2">
                    <span className="mr-1 text-[13px] font-semibold text-pine">
                        Thu chi:
                    </span>
                    {PERIODS.map((p) => (
                        <button
                            key={p.key}
                            onClick={() => go({ period: p.key })}
                            className={`rounded-pill border px-3.5 py-1.5 text-[12.5px] font-semibold transition ${
                                period === p.key
                                    ? 'border-grass bg-grass text-white'
                                    : 'border-cardBorder bg-white text-pine hover:border-grass'
                            }`}
                        >
                            {p.label}
                        </button>
                    ))}
                </div>

                {/* Thu chi lợi nhuận */}
                <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <FinanceTile
                        label="Tổng thu"
                        hint={`${finance.returned_count} đơn đã trả`}
                        value={finance.revenue}
                        color="#3a5a1f"
                    />
                    <FinanceTile
                        label="Tổng chi"
                        hint="chi phí phát sinh"
                        value={finance.expense}
                        color="#C97B36"
                    />
                    <FinanceTile
                        label="Lợi nhuận"
                        hint="thu − chi"
                        value={finance.profit}
                        color={finance.profit >= 0 ? '#3a5a1f' : '#b3493a'}
                        sign
                    />
                </div>

                {/* Chi phí + phân bổ + vốn + hoàn vốn nay ở màn Tài chính (bopcamping-n4qy)
                    — một bảng `expenses` thì chỉ nên có một chỗ nhập. */}
                <Link
                    href={route('admin.finance')}
                    className="mb-6 flex items-center justify-between rounded-[14px] border border-cardBorder bg-white px-4 py-3 transition hover:border-grass"
                >
                    <span className="text-[13px] text-pine">
                        Xem <b>vốn, chi phí theo loại, tiến độ hoàn vốn</b> và
                        nhập khoản chi ở màn Tài chính
                    </span>
                    <span className="text-[13px] font-bold text-grass">
                        Mở Tài chính →
                    </span>
                </Link>

                {/* Doanh thu theo ngày */}
                <RevenueByDay
                    days={revenue_by_day}
                    month={revenue_month}
                    months={revenue_months}
                    onMonthChange={(m) => go({ month: m })}
                />
            </div>
        </>
    );
}

function StatTile({
    label,
    value,
    muted,
}: {
    label: string;
    value: number;
    muted?: boolean;
}) {
    return (
        <div className="rounded-[14px] border border-cardBorder bg-white p-4">
            <div
                className="font-mono text-[26px] font-bold"
                style={{ color: muted ? '#8a967a' : '#18230F' }}
            >
                {value}
            </div>
            <div className="mt-0.5 text-[12px] text-moss">{label}</div>
        </div>
    );
}

function FinanceTile({
    label,
    hint,
    value,
    color,
    sign,
}: {
    label: string;
    hint: string;
    value: number;
    color: string;
    sign?: boolean;
}) {
    return (
        <div className="rounded-[14px] border border-cardBorder bg-white p-4">
            <div className="text-[12px] font-semibold uppercase tracking-[0.04em] text-moss">
                {label}
            </div>
            <div
                className="mt-1 font-mono text-[22px] font-extrabold"
                style={{ color }}
            >
                {sign && value > 0 ? '+' : ''}
                {money(value)}
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
                <h2 className="text-[15px] font-bold text-ink">
                    Số đơn 30 ngày gần đây
                </h2>
                <span className="text-[12px] text-moss">
                    Tổng{' '}
                    <span className="font-mono font-bold text-pine">
                        {totalRange}
                    </span>{' '}
                    đơn · cao nhất {max}/ngày
                </span>
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
                                        background:
                                            hover === i
                                                ? '#3a5a1f'
                                                : d.count === 0
                                                  ? '#e3e8d6'
                                                  : '#557A2B',
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
                        style={{
                            left: `${((hover + 0.5) / data.length) * 100}%`,
                        }}
                    >
                        {data[hover].label}: {data[hover].count} đơn
                    </div>
                )}
            </div>
            {/* Nhãn ngày thưa (mỗi ~5 ngày) */}
            <div className="mt-1.5 flex gap-[2px] text-[10px] text-[#a3ad92]">
                {data.map((d, i) => (
                    <div key={d.date} className="flex-1 text-center">
                        {i % 5 === 0 ? d.label : ''}
                    </div>
                ))}
            </div>
        </div>
    );
}

/** Chi theo loại — thanh tỉ lệ 1 màu (magnitude, không phải identity). */
/**
 * Doanh thu theo ngày — bảng 3 cột (ngày · đơn cho thuê · số tiền), một dòng cho mỗi
 * đơn, ngày chỉ in ở dòng đầu của ngày đó. Ngày không có đơn vẫn giữ dòng "---" / "0đ"
 * để đọc liên tục như lịch.
 *
 * Bảng có bộ lọc THÁNG riêng (từ 08/2026), độc lập với bộ lọc kỳ "Thu chi" phía trên —
 * nên tổng ở đây là tổng của tháng đang chọn, không phải của kỳ thu chi.
 */
function RevenueByDay({
    days,
    month,
    months,
    onMonthChange,
}: {
    days: RevenueDay[];
    month: string;
    months: MonthOption[];
    onMonthChange: (m: string) => void;
}) {
    const total = days.reduce((s, d) => s + d.total, 0);
    const orderCount = days.reduce((s, d) => s + d.orders.length, 0);

    return (
        <div className="mt-5 rounded-[16px] border border-cardBorder bg-white p-5">
            <div className="mb-1 flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-[15px] font-bold text-ink">
                    Doanh thu theo ngày
                </h2>
                <select
                    value={month}
                    onChange={(e) => onMonthChange(e.target.value)}
                    className="h-9 rounded-[10px] border border-cardBorder bg-white px-2.5 text-[13px] font-semibold text-pine outline-none focus:border-grass"
                >
                    {months.map((m) => (
                        <option key={m.value} value={m.value}>
                            {m.label}
                        </option>
                    ))}
                </select>
            </div>
            <p className="mb-3 text-[11.5px] text-[#a3ad92]">
                Đơn đã trả, tính theo ngày đánh dấu đã trả, đã trừ giảm giá. Bộ
                lọc tháng ở đây riêng, không theo kỳ “Thu chi” phía trên.
            </p>

            {/* Cuộn cả 2 chiều TRONG khung card: bảng hẹp lại thì tự cuộn ngang,
                không đẩy layout admin rộng ra. */}
            <div className="max-h-[560px] overflow-auto">
                <table className="w-full min-w-[320px] text-[13px]">
                    <thead className="sticky top-0 bg-white">
                        <tr className="border-b border-cardBorder text-left text-[11.5px] uppercase tracking-[0.04em] text-moss">
                            <th className="w-[110px] py-2 pr-3 font-semibold">
                                Ngày
                            </th>
                            <th className="py-2 pr-3 font-semibold">
                                Đơn cho thuê
                            </th>
                            <th className="w-[120px] py-2 text-right font-semibold">
                                Số tiền
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {days.map((d) => (
                            <Fragment key={d.date}>
                                {d.orders.length === 0 ? (
                                    <tr className="border-t border-[#f1f4ea]">
                                        <td className="py-2 pr-3 font-mono text-[12.5px] text-moss">
                                            {d.label}
                                        </td>
                                        <td className="py-2 pr-3 text-[#c2cbb2]">
                                            ---
                                        </td>
                                        <td className="py-2 text-right font-mono text-[#c2cbb2]">
                                            0đ
                                        </td>
                                    </tr>
                                ) : (
                                    d.orders.map((o, i) => (
                                        <tr
                                            key={o.id}
                                            className={
                                                i === 0
                                                    ? 'border-t border-[#f1f4ea]'
                                                    : ''
                                            }
                                        >
                                            <td className="py-2 pr-3 align-top font-mono text-[12.5px] text-moss">
                                                {i === 0 ? d.label : ''}
                                            </td>
                                            <td className="py-2 pr-3">
                                                <Link
                                                    href={route(
                                                        'admin.orders.show',
                                                        o.id,
                                                    )}
                                                    className="font-mono font-semibold text-pine underline decoration-[#cfd8bf] underline-offset-2 hover:text-grass"
                                                >
                                                    {o.code}
                                                </Link>
                                            </td>
                                            <td className="py-2 text-right font-mono font-semibold text-ink">
                                                {money(o.amount)}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </Fragment>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="border-t-2 border-cardBorder">
                            <td className="py-2.5 pr-3 text-[12.5px] font-bold text-pine">
                                Tổng
                            </td>
                            <td className="py-2.5 pr-3 text-[12px] text-moss">
                                {orderCount} đơn đã trả
                            </td>
                            <td
                                className="py-2.5 text-right font-mono text-[15px] font-extrabold"
                                style={{ color: '#3a5a1f' }}
                            >
                                {money(total)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    );
}

/** Thêm / sửa / xoá chi phí. */
AdminStats.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
