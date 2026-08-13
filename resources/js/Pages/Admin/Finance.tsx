import ExpenseManager, {
    type CategoryOption,
    type ExpenseRow,
} from '@/Components/admin/ExpenseManager';
import AdminLayout from '@/Layouts/AdminLayout';
import { money, moneyShort } from '@/lib/format';
import type { PageProps } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ReactNode, useMemo, useState } from 'react';

type Overview = {
    capital: number;
    spent: number;
    capital_left: number;
    revenue: number;
    profit: number;
    payback_percent: number;
    held_deposit: number;
    pipeline_revenue: number;
    returned_count: number;
};

type MonthRow = {
    month: string;
    label: string;
    revenue: number;
    expense: number;
    profit: number;
    cum_revenue: number;
    cum_expense: number;
};

type CategoryStat = {
    category: string;
    label: string;
    total: number;
    count: number;
    percent: number;
};

type Props = PageProps<{
    period: 'month' | 'quarter' | 'year' | 'all';
    overview: Overview;
    period_summary: {
        revenue: number;
        expense: number;
        profit: number;
        returned_count: number;
    };
    monthly: MonthRow[];
    by_category: CategoryStat[];
    expenses: { rows: ExpenseRow[]; total_count: number };
    categories: CategoryOption[];
}>;

const PERIODS: { key: Props['period']; label: string }[] = [
    { key: 'month', label: 'Tháng này' },
    { key: 'quarter', label: 'Quý này' },
    { key: 'year', label: 'Năm nay' },
    { key: 'all', label: 'Tất cả' },
];

const GREEN = '#557A2B';
const ORANGE = '#B4762A';
const RED = '#B3493A';

export default function AdminFinance() {
    const {
        period,
        overview,
        period_summary,
        monthly,
        by_category,
        expenses,
        categories,
    } = usePage<Props>().props;

    const go = (p: Props['period']) =>
        router.get(
            route('admin.finance'),
            { period: p },
            { preserveState: true, replace: true, preserveScroll: true },
        );

    const profitPositive = overview.profit >= 0;

    return (
        <>
            <Head title="Quản trị · Tài chính" />
            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-[22px] font-extrabold text-pine">
                        Tài chính
                    </h1>
                    <p className="mt-0.5 text-[13px] text-moss">
                        Vốn, thu chi, lợi nhuận và tiến độ hoàn vốn của shop
                    </p>
                </div>

                {/* ── Vốn: bốn con số trả lời "tiền của tôi đang ở đâu" ── */}
                <SectionTitle>Vốn</SectionTitle>
                <div className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Tile
                        label="Vốn ban đầu"
                        value={money(overview.capital)}
                        hint="Số tiền bỏ ra lúc mở shop"
                        color="#18230F"
                    />
                    <Tile
                        label="Đã chi"
                        value={money(overview.spent)}
                        hint={`${pct(overview.spent, overview.capital)} vốn ban đầu`}
                        color={ORANGE}
                    />
                    <Tile
                        label="Vốn còn lại"
                        value={money(overview.capital_left)}
                        hint={
                            overview.capital_left < 0
                                ? 'Đã tiêu quá vốn — phần vượt lấy từ tiền thu được'
                                : 'Chưa tiêu đến'
                        }
                        color={overview.capital_left < 0 ? RED : GREEN}
                    />
                    <Tile
                        label="Đã hoàn vốn"
                        // Chưa chi đồng nào thì KHÔNG in "0%": đọc thành "chưa thu lại
                        // được gì" trong khi thực tế là chưa bỏ ra gì để mà thu lại.
                        value={
                            overview.spent > 0
                                ? `${fmtPercent(overview.payback_percent)}%`
                                : '—'
                        }
                        hint={
                            overview.spent > 0
                                ? `Thu ${moneyShort(overview.revenue)} / đã chi ${moneyShort(overview.spent)}`
                                : 'Chưa nhập khoản chi nào'
                        }
                        color={overview.payback_percent >= 100 ? GREEN : ORANGE}
                    />
                </div>

                <CapitalBar overview={overview} />

                {/* ── Kinh doanh ── */}
                <SectionTitle className="mt-6">Kinh doanh</SectionTitle>
                <div className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Tile
                        label="Doanh thu"
                        value={money(overview.revenue)}
                        hint={`${overview.returned_count} đơn đã trả đồ`}
                        color={GREEN}
                    />
                    <Tile
                        label={profitPositive ? 'Lợi nhuận' : 'Đang lỗ'}
                        value={money(overview.profit)}
                        hint="Doanh thu − tổng chi phí"
                        color={profitPositive ? GREEN : RED}
                    />
                    <Tile
                        label="Cọc đang giữ"
                        value={money(overview.held_deposit)}
                        hint="Tiền của khách, phải hoàn khi trả đồ"
                        color="#4A7C9B"
                    />
                    <Tile
                        label="Sắp thu"
                        value={money(overview.pipeline_revenue)}
                        hint="Tiền thuê của đơn đang chạy"
                        color="#8a967a"
                    />
                </div>

                {/* Cảnh báo cái bẫy kinh điển của mô hình cho thuê. */}
                {overview.held_deposit > 0 && (
                    <div className="mb-6 rounded-[12px] border border-[#cfe0ea] bg-[#f2f8fb] px-4 py-3 text-[12.5px] text-[#31607a]">
                        <b>Lưu ý:</b> {money(overview.held_deposit)} tiền cọc
                        đang cầm <b>không phải</b> doanh thu — đây là tiền của
                        khách, phải hoàn lại khi họ trả đồ nguyên vẹn. Đừng cộng
                        vào lãi.
                    </div>
                )}

                {/* ── Bộ lọc kỳ, chỉ áp cho khối dưới ── */}
                <div className="mb-3 mt-6 flex flex-wrap items-center gap-2">
                    <span className="mr-1 text-[13px] font-semibold text-pine">
                        Xem theo kỳ:
                    </span>
                    {PERIODS.map((p) => (
                        <button
                            key={p.key}
                            onClick={() => go(p.key)}
                            className={`rounded-pill border px-3.5 py-1.5 text-[12.5px] font-semibold transition ${
                                period === p.key
                                    ? 'border-grass bg-grass text-white'
                                    : 'border-cardBorder bg-white text-pine hover:border-grass'
                            }`}
                        >
                            {p.label}
                        </button>
                    ))}
                    <span className="text-[11.5px] text-[#a3ad92]">
                        (khối Vốn phía trên luôn tính trên toàn bộ lịch sử)
                    </span>
                </div>

                <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Tile
                        label="Thu trong kỳ"
                        value={money(period_summary.revenue)}
                        hint={`${period_summary.returned_count} đơn`}
                        color={GREEN}
                    />
                    <Tile
                        label="Chi trong kỳ"
                        value={money(period_summary.expense)}
                        hint="Tổng khoản chi đã nhập"
                        color={ORANGE}
                    />
                    <Tile
                        label="Lãi/lỗ trong kỳ"
                        value={money(period_summary.profit)}
                        hint="Thu − chi của kỳ"
                        color={period_summary.profit >= 0 ? GREEN : RED}
                    />
                </div>

                {/* ── Biểu đồ ── */}
                <div className="mb-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <MonthlyBars data={monthly} />
                    <CumulativeChart data={monthly} />
                </div>

                <div className="mb-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <CategoryBreakdown
                        data={by_category}
                        categories={categories}
                    />
                    <MonthlyTable data={monthly} />
                </div>

                <ExpenseManager
                    expenses={expenses.rows}
                    categories={categories}
                    totalCount={expenses.total_count}
                />
            </div>
        </>
    );
}

/* ─────────────────────────── helpers ─────────────────────────── */

const fmtPercent = (n: number) => String(n).replace('.', ',');

/** Tỉ lệ a/b dạng "35%" — b = 0 thì không có gì để so, trả "—". */
const pct = (a: number, b: number) =>
    b > 0 ? `${Math.round((a / b) * 100)}%` : '—';

function SectionTitle({
    children,
    className = '',
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <h2
            className={`mb-2.5 text-[12px] font-bold uppercase tracking-[0.08em] text-[#a3ad92] ${className}`}
        >
            {children}
        </h2>
    );
}

function Tile({
    label,
    value,
    hint,
    color,
}: {
    label: string;
    value: string;
    hint: string;
    color: string;
}) {
    return (
        <div className="rounded-[14px] border border-cardBorder bg-white p-4">
            <div className="text-[12px] font-semibold uppercase tracking-[0.04em] text-moss">
                {label}
            </div>
            <div
                className="mt-1 font-mono text-[20px] font-extrabold leading-tight"
                style={{ color }}
            >
                {value}
            </div>
            <div className="mt-1 text-[11.5px] leading-snug text-[#a3ad92]">
                {hint}
            </div>
        </div>
    );
}

/**
 * Hai thanh tiến độ: vốn đã tiêu đến đâu, và thu đã bù lại được bao nhiêu phần chi.
 * Kẹp bề rộng ở 100% để khi tiêu quá vốn thanh không tràn ra ngoài thẻ.
 */
function CapitalBar({ overview }: { overview: Overview }) {
    const spentPct = Math.min(
        100,
        overview.capital > 0 ? (overview.spent / overview.capital) * 100 : 0,
    );
    const paybackPct = Math.min(100, overview.payback_percent);
    const over = overview.spent > overview.capital;

    return (
        <div className="rounded-[16px] border border-cardBorder bg-white p-5">
            <Bar
                title="Vốn đã sử dụng"
                right={`${money(overview.spent)} / ${money(overview.capital)}`}
                percent={spentPct}
                color={over ? RED : ORANGE}
                note={
                    over
                        ? `Vượt vốn ${money(overview.spent - overview.capital)} — phần vượt đang lấy từ tiền thu được.`
                        : undefined
                }
            />
            <div className="mt-4">
                <Bar
                    title="Tiến độ hoàn vốn"
                    right={
                        overview.spent > 0
                            ? `${fmtPercent(overview.payback_percent)}%`
                            : '—'
                    }
                    percent={paybackPct}
                    color={GREEN}
                    // Chưa chi gì mà báo "còn thiếu 0đ nữa là hoà vốn" là câu vô nghĩa —
                    // không có gì để hoàn thì nói thẳng là chưa có dữ liệu.
                    // So bằng SỐ THẬT, không bằng payback_percent: phần trăm đã làm tròn
                    // 1 chữ số nên 99,96% thành 100,0 và câu "đã thu về đủ" sẽ sai.
                    note={
                        overview.spent === 0
                            ? 'Chưa có khoản chi nào để tính hoàn vốn — nhập chi phí ở khối bên dưới.'
                            : overview.revenue >= overview.spent
                              ? 'Đã thu về đủ số tiền bỏ ra — phần vượt là lãi.'
                              : `Còn thiếu ${money(overview.spent - overview.revenue)} nữa là hoà vốn.`
                    }
                />
            </div>
        </div>
    );
}

function Bar({
    title,
    right,
    percent,
    color,
    note,
}: {
    title: string;
    right: string;
    percent: number;
    color: string;
    note?: string;
}) {
    return (
        <div>
            <div className="mb-1.5 flex items-baseline justify-between">
                <span className="text-[13px] font-bold text-ink">{title}</span>
                <span className="font-mono text-[12.5px] font-semibold text-pine">
                    {right}
                </span>
            </div>
            <div
                className="h-3 w-full overflow-hidden rounded-pill bg-[#eef1e6]"
                role="progressbar"
                aria-label={title}
                aria-valuenow={Math.round(percent)}
                aria-valuemin={0}
                aria-valuemax={100}
            >
                <div
                    className="h-full rounded-pill transition-all"
                    style={{ width: `${percent}%`, background: color }}
                />
            </div>
            {note && (
                <p className="mt-1.5 text-[11.5px] text-[#a3ad92]">{note}</p>
            )}
        </div>
    );
}

function ChartCard({
    title,
    subtitle,
    children,
    empty,
}: {
    title: string;
    subtitle?: ReactNode;
    children: ReactNode;
    empty: boolean;
}) {
    return (
        <div className="rounded-[16px] border border-cardBorder bg-white p-5">
            <div className="mb-3 flex items-baseline justify-between gap-3">
                <h2 className="text-[15px] font-bold text-ink">{title}</h2>
                {subtitle && (
                    <span className="text-right text-[11.5px] text-moss">
                        {subtitle}
                    </span>
                )}
            </div>
            {empty ? (
                <p className="py-10 text-center text-[13px] text-[#a3ad92]">
                    Chưa có dữ liệu. Nhập khoản chi hoặc hoàn tất một đơn thuê
                    để bắt đầu theo dõi.
                </p>
            ) : (
                children
            )}
        </div>
    );
}

/**
 * Lưới ngang + mốc tiền của trục Y, cao đúng 190px như vùng vẽ.
 *
 * Không có mốc thì cột chỉ nói được "cái này cao hơn cái kia" — nhìn một cột không biết
 * 3 triệu hay 30 triệu, phải rê chuột từng tháng mới đọc được. Với màn hình mà việc
 * chính là đọc số thì đó là thiếu sót thật, không phải trang trí.
 */
function GridScale({ max }: { max: number }) {
    const steps = [1, 0.75, 0.5, 0.25, 0];

    return (
        <div className="pointer-events-none relative h-[190px]" aria-hidden>
            {steps.map((s) => (
                <div
                    key={s}
                    className="absolute inset-x-0 flex items-center gap-2"
                    style={{ top: `${(1 - s) * 100}%` }}
                >
                    <span className="w-9 shrink-0 text-right font-mono text-[9px] leading-none text-[#b9c2ab]">
                        {s === 0 ? '0' : moneyShort(max * s)}
                    </span>
                    <span className="h-px flex-1 bg-[#f1f4ea]" />
                </div>
            ))}
        </div>
    );
}

/** Cột kép thu (xanh) / chi (cam) theo tháng. Hover một tháng để xem số chính xác. */
function MonthlyBars({ data }: { data: MonthRow[] }) {
    const [hover, setHover] = useState<number | null>(null);
    const max = useMemo(
        () => Math.max(1, ...data.map((d) => Math.max(d.revenue, d.expense))),
        [data],
    );
    const active = hover !== null ? data[hover] : null;

    return (
        <ChartCard
            title="Thu chi theo tháng"
            subtitle={
                <>
                    <Legend color={GREEN} label="Thu" />{' '}
                    <Legend color={ORANGE} label="Chi" />
                </>
            }
            empty={data.length === 0}
        >
            <GridScale max={max} />
            {/* pl khớp bề rộng cột nhãn của GridScale (w-9 + gap-2) để cột đứng đúng lưới. */}
            <div className="-mt-[190px] flex h-[190px] items-end gap-1.5 pl-[44px]">
                {data.map((d, i) => (
                    <div
                        key={d.month}
                        className="flex h-full flex-1 cursor-default flex-col justify-end"
                        onMouseEnter={() => setHover(i)}
                        onMouseLeave={() => setHover(null)}
                    >
                        <div className="flex h-full items-end justify-center gap-[3px]">
                            <span
                                className="w-1/2 rounded-t-[3px] transition-opacity"
                                style={{
                                    height: `${barH(d.revenue, max)}%`,
                                    background: GREEN,
                                    opacity:
                                        hover === null || hover === i
                                            ? 1
                                            : 0.35,
                                }}
                            />
                            <span
                                className="w-1/2 rounded-t-[3px] transition-opacity"
                                style={{
                                    height: `${barH(d.expense, max)}%`,
                                    background: ORANGE,
                                    opacity:
                                        hover === null || hover === i
                                            ? 1
                                            : 0.35,
                                }}
                            />
                        </div>
                    </div>
                ))}
            </div>
            <div className="mt-1.5 flex gap-1.5 pl-[44px]">
                {data.map((d, i) => (
                    <div
                        key={d.month}
                        className="flex-1 text-center font-mono text-[9.5px] text-[#a3ad92]"
                        style={{
                            color: hover === i ? '#18230F' : undefined,
                            fontWeight: hover === i ? 700 : 400,
                        }}
                    >
                        {d.label}
                    </div>
                ))}
            </div>
            <div className="mt-3 min-h-[38px] rounded-[10px] bg-[#f7f9f2] px-3 py-2 text-[12px]">
                {active ? (
                    <span className="text-pine">
                        <b>{active.label}</b> · thu{' '}
                        <b style={{ color: GREEN }}>{money(active.revenue)}</b>{' '}
                        · chi{' '}
                        <b style={{ color: ORANGE }}>{money(active.expense)}</b>{' '}
                        ·{' '}
                        <b
                            style={{
                                color: active.profit >= 0 ? GREEN : RED,
                            }}
                        >
                            {active.profit >= 0 ? 'lãi' : 'lỗ'}{' '}
                            {money(Math.abs(active.profit))}
                        </b>
                    </span>
                ) : (
                    <span className="text-[#a3ad92]">
                        Di chuột lên cột để xem chi tiết từng tháng
                    </span>
                )}
            </div>
        </ChartCard>
    );
}

/**
 * Đường luỹ kế thu vs chi — chỗ hai đường cắt nhau chính là điểm hoà vốn.
 *
 * Vẽ SVG thuần (không thêm thư viện chart, xem .claude/rules/tech-strategy.md).
 * viewBox 0..100 để đường tự co giãn theo bề rộng thẻ.
 */
function CumulativeChart({ data }: { data: MonthRow[] }) {
    const max = useMemo(
        () =>
            Math.max(
                1,
                ...data.map((d) => Math.max(d.cum_revenue, d.cum_expense)),
            ),
        [data],
    );

    const pointsOf = (pick: (d: MonthRow) => number) =>
        data
            .map((d, i) => {
                const x =
                    data.length === 1 ? 50 : (i / (data.length - 1)) * 100;
                const y = 100 - (pick(d) / max) * 100;
                return `${x.toFixed(2)},${y.toFixed(2)}`;
            })
            .join(' ');

    // Tháng đầu tiên thu luỹ kế vượt chi luỹ kế = hoà vốn.
    const breakEven = data.find((d) => d.cum_revenue >= d.cum_expense);
    const last = data[data.length - 1];

    return (
        <ChartCard
            title="Luỹ kế & điểm hoà vốn"
            subtitle={
                <>
                    <Legend color={GREEN} label="Thu luỹ kế" />{' '}
                    <Legend color={ORANGE} label="Chi luỹ kế" />
                </>
            }
            empty={data.length === 0}
        >
            <GridScale max={max} />
            {/* Chừa đúng 44px bên trái cho cột nhãn của GridScale để đường khớp lưới. */}
            <div className="-mt-[190px] pl-[44px]">
                <svg
                    viewBox="0 0 100 100"
                    preserveAspectRatio="none"
                    className="h-[190px] w-full"
                    role="img"
                    aria-label="Biểu đồ thu chi luỹ kế theo tháng"
                >
                    <polyline
                        points={pointsOf((d) => d.cum_expense)}
                        fill="none"
                        stroke={ORANGE}
                        strokeWidth="1.6"
                        vectorEffect="non-scaling-stroke"
                    />
                    <polyline
                        points={pointsOf((d) => d.cum_revenue)}
                        fill="none"
                        stroke={GREEN}
                        strokeWidth="1.6"
                        vectorEffect="non-scaling-stroke"
                    />
                </svg>
            </div>
            <div className="mt-1.5 flex justify-between pl-[44px] font-mono text-[9.5px] text-[#a3ad92]">
                <span>{data[0]?.label}</span>
                <span>{last?.label}</span>
            </div>
            <div className="mt-3 rounded-[10px] bg-[#f7f9f2] px-3 py-2 text-[12px] text-pine">
                {breakEven ? (
                    <>
                        Hoà vốn từ <b>{breakEven.label}</b> — thu luỹ kế{' '}
                        <b>{money(last?.cum_revenue ?? 0)}</b> so với chi luỹ kế{' '}
                        <b>{money(last?.cum_expense ?? 0)}</b>.
                    </>
                ) : (
                    <>
                        Chưa hoà vốn — còn thiếu{' '}
                        <b>
                            {money(
                                Math.max(
                                    0,
                                    (last?.cum_expense ?? 0) -
                                        (last?.cum_revenue ?? 0),
                                ),
                            )}
                        </b>{' '}
                        nữa thì thu luỹ kế đuổi kịp chi luỹ kế.
                    </>
                )}
            </div>
        </ChartCard>
    );
}

/** Phân bổ chi phí theo loại: một thanh xếp chồng + bảng chi tiết kèm tỉ trọng. */
function CategoryBreakdown({
    data,
    categories,
}: {
    data: CategoryStat[];
    categories: CategoryOption[];
}) {
    const total = data.reduce((s, d) => s + d.total, 0);
    const colorOf = (value: string) =>
        categories.find((c) => c.value === value)?.color ?? '#8A8A7B';

    return (
        <ChartCard
            title="Chi phí theo loại"
            subtitle={<>Tổng {money(total)}</>}
            empty={data.length === 0}
        >
            <div className="mb-4 flex h-4 w-full overflow-hidden rounded-pill">
                {data.map((d) => (
                    <span
                        key={d.category}
                        title={`${d.label} · ${money(d.total)}`}
                        style={{
                            width: `${d.percent}%`,
                            background: colorOf(d.category),
                        }}
                    />
                ))}
            </div>
            <table className="w-full text-[12.5px]">
                <tbody>
                    {data.map((d) => (
                        <tr
                            key={d.category}
                            className="border-t border-[#f1f4ea]"
                        >
                            <td className="py-2">
                                <span
                                    className="mr-2 inline-block h-2.5 w-2.5 rounded-full align-middle"
                                    style={{ background: colorOf(d.category) }}
                                />
                                <span className="font-semibold text-pine">
                                    {d.label}
                                </span>
                                <span className="ml-1.5 text-[11px] text-[#a3ad92]">
                                    {d.count} khoản
                                </span>
                            </td>
                            <td className="py-2 text-right font-mono text-[11.5px] text-moss">
                                {fmtPercent(d.percent)}%
                            </td>
                            <td className="py-2 pl-3 text-right font-mono font-semibold text-campfire">
                                {money(d.total)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </ChartCard>
    );
}

/** Bảng số liệu theo tháng — chỗ để đọc con số chính xác thay vì ước lượng trên chart. */
function MonthlyTable({ data }: { data: MonthRow[] }) {
    const rows = [...data].reverse(); // mới nhất lên đầu

    return (
        <ChartCard
            title="Bảng thu chi theo tháng"
            subtitle={<>{data.length} tháng</>}
            empty={data.length === 0}
        >
            <div className="max-h-[300px] overflow-y-auto">
                <table className="w-full text-[12.5px]">
                    <thead className="sticky top-0 bg-white">
                        <tr className="text-[11px] uppercase tracking-[0.04em] text-[#a3ad92]">
                            <th className="pb-2 text-left font-semibold">
                                Tháng
                            </th>
                            <th className="pb-2 text-right font-semibold">
                                Thu
                            </th>
                            <th className="pb-2 text-right font-semibold">
                                Chi
                            </th>
                            <th className="pb-2 text-right font-semibold">
                                Lãi/lỗ
                            </th>
                            <th className="pb-2 text-right font-semibold">
                                Luỹ kế
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((d) => (
                            <tr
                                key={d.month}
                                className="border-t border-[#f1f4ea]"
                            >
                                <td className="py-2 font-semibold text-pine">
                                    {d.label}
                                </td>
                                <td className="py-2 text-right font-mono">
                                    {d.revenue ? moneyShort(d.revenue) : '—'}
                                </td>
                                <td className="py-2 text-right font-mono">
                                    {d.expense ? moneyShort(d.expense) : '—'}
                                </td>
                                <td
                                    className="py-2 text-right font-mono font-semibold"
                                    style={{
                                        color: d.profit >= 0 ? GREEN : RED,
                                    }}
                                >
                                    {d.profit > 0 ? '+' : ''}
                                    {moneyShort(d.profit)}
                                </td>
                                <td
                                    className="py-2 text-right font-mono"
                                    style={{
                                        color:
                                            d.cum_revenue >= d.cum_expense
                                                ? GREEN
                                                : '#a3ad92',
                                    }}
                                >
                                    {moneyShort(d.cum_revenue - d.cum_expense)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </ChartCard>
    );
}

function Legend({ color, label }: { color: string; label: string }) {
    return (
        <span className="ml-2 inline-flex items-center gap-1">
            <span
                className="inline-block h-2 w-2 rounded-full"
                style={{ background: color }}
            />
            {label}
        </span>
    );
}

/**
 * Chiều cao cột theo %. Giá trị 0 vẫn để lại vạch mảng 2% — cột biến mất hoàn toàn
 * trông như thiếu dữ liệu, khác hẳn nghĩa "tháng đó không thu/chi đồng nào".
 */
const barH = (value: number, max: number) =>
    value === 0 ? 2 : Math.max(6, (value / max) * 100);

AdminFinance.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
