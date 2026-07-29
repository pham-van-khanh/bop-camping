// Lịch giao/thu cho shipper (bopcamping-rtkh, prd_delivery_schedule FR-5).
// Lịch THÁNG: ngày có đơn bôi đỏ + đếm đơn, ngày đã qua bị khoá; bấm 1 ngày → danh sách
// đơn cần giao/thu hôm đó (feedback 2026-07-28). Mobile-first, KHÔNG dùng <table> cho đơn.
import ScheduleAssignList, { type ShipperOption } from '@/Components/admin/ScheduleAssignList';
import AdminLayout from '@/Layouts/AdminLayout';
import { money } from '@/lib/format';
import { buildMonthGrid, WEEKDAY_LABELS } from '@/lib/monthGrid';
import { sessionLabel, shopHours, type Session } from '@/lib/session';
import { Head, router, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';

type ScheduleItem = { name: string; quantity: number };

type ScheduleOrder = {
    id: number;
    code: string;
    time: string | null;
    customer_name: string;
    customer_phone: string;
    customer_address: string | null;
    service_location: string | null;
    session: Session | null;
    status: string;
    payment_status: string;
    amount_due: number;
    deposit_total: number;
    schedule_note: string | null;
    items: ScheduleItem[];
    // Gán shipper theo lượt (bopcamping-yc7d)
    shipper_id: number | null;
    shipper_name: string | null;
};

/** Số đơn giao/thu của 1 ngày trong tháng — chỉ những ngày CÓ đơn được trả về. */
type DayCount = { date: string; pickups: number; returns: number };

type Stats = { pickups: number; returns: number; unscheduled: number; unassigned: number };

// Lượt đi của shipper: giao đồ (thu tiền COD) hoặc thu đồ (hoàn cọc).
type TripKind = 'pickup' | 'return';

// Nhãn tình trạng chuyển tiền — bản rút gọn cho card shipper (nguồn đầy đủ ở orderShared.tsx).
const PAYMENT_LABEL: Record<string, string> = {
    unpaid: 'chưa chuyển',
    deposit: 'đã chuyển cọc',
    full: 'đã chuyển hết',
};

// Đỏ đất — ngày có đơn + giờ đã chốt (khớp tông be/đất của shop).
const RED = { bg: '#f6ddd6', fg: '#b3493a' };

export default function AdminDeliverySchedule({
    month,
    month_label,
    prev_month,
    next_month,
    days,
    date,
    date_label,
    today,
    pickups,
    returns,
    stats,
    shippers,
    filters,
}: {
    month: string;
    month_label: string;
    prev_month: string;
    next_month: string;
    days: DayCount[];
    date: string;
    date_label: string;
    today: string;
    pickups: ScheduleOrder[];
    returns: ScheduleOrder[];
    stats: Stats;
    shippers: ShipperOption[];
    filters: { shipper: string };
}) {
    const weeks = buildMonthGrid(month);
    const byDate = new Map(days.map((d) => [d.date, d]));

    // Bộ lọc shipper đi kèm mọi lần điều hướng ngày/tháng để không bị mất khi bấm lịch.
    const nav = (params: Record<string, string>) =>
        router.get(
            route('admin.schedule'),
            { month, date, shipper: filters.shipper, ...params },
            { preserveState: true, preserveScroll: true },
        );
    const goMonth = (m: string) => nav({ month: m });
    const goDate = (d: string) => nav({ date: d });
    const goToday = () => nav({ month: today.slice(0, 7), date: today });

    return (
        <>
            <Head title="Quản trị · Lịch giao" />
            <div className="p-4 sm:p-6">
                <div className="mb-4">
                    <h1 className="text-[20px] font-extrabold text-pine">Lịch giao/thu</h1>
                    <p className="mt-0.5 text-[13px] text-moss">
                        Ngày bôi đỏ là ngày có đơn — bấm vào ngày để xem cần giao/thu những đơn nào.
                    </p>
                </div>

                {/* Lịch tháng */}
                <div className="mb-6 rounded-[16px] border border-cardBorder bg-white p-3 sm:p-4">
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <button
                            type="button"
                            onClick={() => goMonth(prev_month)}
                            aria-label="Tháng trước"
                            className="grid h-11 w-11 flex-none place-items-center rounded-[10px] border border-cardBorder text-[18px] text-pine hover:border-grass"
                        >
                            ‹
                        </button>
                        <div className="text-center">
                            <div className="text-[15px] font-bold text-pine">{month_label}</div>
                            <button
                                type="button"
                                onClick={goToday}
                                className="mt-0.5 text-[12px] font-semibold text-grass underline-offset-2 hover:underline"
                            >
                                Về hôm nay
                            </button>
                        </div>
                        <button
                            type="button"
                            onClick={() => goMonth(next_month)}
                            aria-label="Tháng sau"
                            className="grid h-11 w-11 flex-none place-items-center rounded-[10px] border border-cardBorder text-[18px] text-pine hover:border-grass"
                        >
                            ›
                        </button>
                    </div>

                    <div className="grid grid-cols-7 gap-1 text-center text-[11px] font-bold uppercase text-[#a3ad92]">
                        {WEEKDAY_LABELS.map((w) => (
                            <div key={w} className="py-1">
                                {w}
                            </div>
                        ))}
                    </div>

                    <div className="flex flex-col gap-1">
                        {weeks.map((week, wi) => (
                            <div key={wi} className="grid grid-cols-7 gap-1">
                                {week.map((cell, ci) =>
                                    cell === null ? (
                                        <div key={ci} />
                                    ) : (
                                        <DayCell
                                            key={cell}
                                            date={cell}
                                            counts={byDate.get(cell)}
                                            past={cell < today}
                                            selected={cell === date}
                                            isToday={cell === today}
                                            onSelect={goDate}
                                        />
                                    ),
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                {/* Ngày đang chọn */}
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-[16px] font-bold text-pine">{date_label}</h2>
                    {/* Lọc theo shipper — áp dụng cho cả lịch tháng và danh sách dưới */}
                    <label className="flex items-center gap-2 text-[12.5px] text-moss">
                        Shipper
                        <select
                            value={filters.shipper}
                            onChange={(e) => nav({ shipper: e.target.value })}
                            className="min-h-[36px] rounded-[9px] border border-cardBorder bg-white px-2 text-[13px] text-ink outline-none focus:border-grass"
                        >
                            <option value="">Tất cả</option>
                            <option value="none">Chưa gán</option>
                            {shippers.map((s) => (
                                <option key={s.id} value={String(s.id)}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>
                <div className="mb-6 flex flex-wrap gap-2 text-[13px] font-semibold">
                    <span className="rounded-pill border border-cardBorder bg-white px-3 py-1.5 text-pine">
                        {stats.pickups} giao
                    </span>
                    <span className="rounded-pill border border-cardBorder bg-white px-3 py-1.5 text-pine">
                        {stats.returns} thu
                    </span>
                    {stats.unscheduled > 0 && (
                        <span className="rounded-pill px-3 py-1.5" style={{ background: '#fbe9d8', color: '#9a5a1f' }}>
                            {stats.unscheduled} chưa chốt giờ
                        </span>
                    )}
                    {stats.unassigned > 0 && (
                        <span className="rounded-pill px-3 py-1.5" style={{ background: '#f6ddd6', color: '#b3493a' }}>
                            {stats.unassigned} chưa có shipper
                        </span>
                    )}
                </div>

                <ScheduleSection
                    title="Cần giao"
                    kind="pickup"
                    date={date}
                    orders={pickups}
                    shippers={shippers}
                    emptyText="Không có đơn nào cần giao ngày này."
                />
                <ScheduleSection
                    title="Cần thu"
                    kind="return"
                    date={date}
                    orders={returns}
                    shippers={shippers}
                    emptyText="Không có đơn nào cần thu ngày này."
                />
            </div>
        </>
    );
}

/**
 * 1 ô ngày trong lịch tháng. Ngày đã qua: khoá (không bấm được) nhưng vẫn thấy mờ là hôm
 * đó có đơn. Ngày có đơn: nền đỏ nhạt + số đơn (↓ giao · ↑ thu). Đang chọn: viền đậm.
 */
function DayCell({
    date,
    counts,
    past,
    selected,
    isToday,
    onSelect,
}: {
    date: string;
    counts?: DayCount;
    past: boolean;
    selected: boolean;
    isToday: boolean;
    onSelect: (d: string) => void;
}) {
    const day = Number(date.slice(-2));
    const pickups = counts?.pickups ?? 0;
    const returns = counts?.returns ?? 0;
    const hasOrders = pickups + returns > 0;

    const label = `Ngày ${day}${hasOrders ? ` · ${pickups} giao, ${returns} thu` : ' · không có đơn'}`;

    return (
        <button
            type="button"
            disabled={past}
            aria-label={label}
            aria-current={selected ? 'date' : undefined}
            onClick={() => onSelect(date)}
            className={`min-h-[52px] rounded-[10px] border p-1 text-center transition ${
                past ? 'cursor-not-allowed opacity-45' : 'hover:border-grass'
            } ${selected ? 'border-grass ring-1 ring-grass' : 'border-[#eef2e3]'}`}
            style={{ background: hasOrders ? RED.bg : '#fafcf7' }}
        >
            <div
                className={`text-[13px] ${hasOrders ? 'font-bold' : 'font-semibold'} ${isToday ? 'underline underline-offset-2' : ''}`}
                style={{ color: hasOrders ? RED.fg : '#5C6E47' }}
            >
                {day}
            </div>
            {hasOrders && (
                <div className="font-mono text-[10px] leading-tight" style={{ color: RED.fg }}>
                    {pickups > 0 && `${pickups}↓`}
                    {pickups > 0 && returns > 0 && ' '}
                    {returns > 0 && `${returns}↑`}
                </div>
            )}
        </button>
    );
}

function ScheduleSection({
    title,
    kind,
    date,
    orders,
    shippers,
    emptyText,
}: {
    title: string;
    kind: TripKind;
    date: string;
    orders: ScheduleOrder[];
    shippers: ShipperOption[];
    emptyText: string;
}) {
    const unassigned = orders.filter((o) => o.shipper_id === null).length;

    return (
        <div className="mb-8">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-[15px] font-bold text-pine">
                    {title} <span className="font-mono text-[13px] font-normal text-moss">({orders.length})</span>
                </h3>
                {unassigned > 0 && shippers.length > 0 && (
                    <AssignAll kind={kind} date={date} shippers={shippers} count={unassigned} />
                )}
            </div>
            <ScheduleAssignList
                leg={kind}
                orders={orders}
                shippers={shippers}
                emptyText={emptyText}
                renderCard={(order) => <ScheduleOrderCard order={order} kind={kind} />}
            />
        </div>
    );
}

/** Gán 1 shipper cho mọi đơn CHƯA có người của mục này — không ghi đè đơn đã gán. */
function AssignAll({
    kind,
    date,
    shippers,
    count,
}: {
    kind: TripKind;
    date: string;
    shippers: ShipperOption[];
    count: number;
}) {
    const [shipperId, setShipperId] = useState('');
    const [saving, setSaving] = useState(false);

    const submit = () => {
        if (!shipperId) return;
        setSaving(true);
        router.post(
            route('admin.schedule.assignAll'),
            { leg: kind, date, shipper_id: Number(shipperId) },
            { preserveScroll: true, preserveState: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            <select
                value={shipperId}
                onChange={(e) => setShipperId(e.target.value)}
                className="min-h-[36px] rounded-[9px] border border-cardBorder bg-white px-2 text-[13px] text-ink outline-none focus:border-grass"
            >
                <option value="">Chọn shipper…</option>
                {shippers.map((s) => (
                    <option key={s.id} value={String(s.id)}>
                        {s.name}
                    </option>
                ))}
            </select>
            <button
                type="button"
                onClick={submit}
                disabled={!shipperId || saving}
                className="min-h-[36px] rounded-[9px] bg-grass px-3 text-[12.5px] font-bold text-white transition hover:bg-pine disabled:opacity-60"
            >
                {saving ? 'Đang gán…' : `Gán ${count} đơn chưa có người`}
            </button>
        </div>
    );
}

function ScheduleOrderCard({ order, kind }: { order: ScheduleOrder; kind: TripKind }) {
    const hours = shopHours((usePage().props as { site?: Parameters<typeof shopHours>[0] }).site);
    const sessTag = sessionLabel(order.session, hours);

    return (
        <div className="rounded-[16px] border border-cardBorder bg-white p-4">
            <div className="flex items-start justify-between gap-3">
                {order.time ? (
                    <div className="font-mono text-[20px] font-bold" style={{ color: RED.fg }}>
                        {order.time}
                    </div>
                ) : (
                    <span
                        className="rounded-pill px-2.5 py-1 text-[12px] font-bold"
                        style={{ background: '#fbe9d8', color: '#9a5a1f' }}
                    >
                        Chưa chốt giờ
                    </span>
                )}
                <a
                    href={route('admin.orders.show', order.id)}
                    className="font-mono text-[13px] font-bold text-grass underline-offset-2 hover:underline"
                >
                    {order.code}
                </a>
            </div>

            <div className="mt-2 flex flex-wrap items-center gap-2">
                <div className="text-[14px] font-semibold text-ink">{order.customer_name}</div>
                {order.status === 'pending' && (
                    <span
                        className="rounded-pill px-2 py-0.5 text-[11px] font-bold"
                        style={{ color: '#9a7a2a', background: '#fbf2d8' }}
                    >
                        Chờ xác nhận
                    </span>
                )}
            </div>

            <a
                href={`tel:${order.customer_phone}`}
                className="mt-1 inline-block min-h-[44px] py-1.5 font-mono text-[14px] text-grass"
            >
                📞 {order.customer_phone}
            </a>

            {order.customer_address && <div className="text-[13px] text-moss">{order.customer_address}</div>}
            <div className="mt-1 text-[12.5px] text-moss">
                {order.service_location ?? 'Chưa gán cửa hàng'}
                {sessTag && <span className="text-grass"> · {sessTag}</span>}
            </div>

            {order.items.length > 0 && (
                <ul className="mt-2 border-t border-[#f1f4ea] pt-2 text-[12.5px] text-ink">
                    {order.items.map((it, i) => (
                        <li key={i}>
                            {it.name} × {it.quantity}
                        </li>
                    ))}
                </ul>
            )}

            {/* Lượt GIAO thu tiền (COD); lượt THU trả cọc lại cho khách — hai việc khác nhau. */}
            <div className="mt-2 border-t border-[#f1f4ea] pt-2 text-[13px]">
                {kind === 'pickup' ? (
                    <>
                        <span className="font-mono font-bold text-ink">Thu khi nhận: {money(order.amount_due)}</span>
                        <span className="ml-1 text-moss">
                            ({PAYMENT_LABEL[order.payment_status] ?? order.payment_status})
                        </span>
                    </>
                ) : (
                    <>
                        <span className="font-mono font-bold text-ink">Hoàn cọc: {money(order.deposit_total)}</span>
                        {order.payment_status !== 'full' && (
                            <span className="ml-1" style={{ color: RED.fg }}>
                                · tiền thuê {PAYMENT_LABEL[order.payment_status] ?? order.payment_status}
                            </span>
                        )}
                    </>
                )}
            </div>

            {order.schedule_note && (
                <div
                    className="mt-2 rounded-[10px] p-2.5 text-[12.5px]"
                    style={{ background: '#f8faf4', color: '#5C6E47' }}
                >
                    <span className="font-bold text-pine">Ghi chú shipper: </span>
                    {order.schedule_note}
                </div>
            )}
        </div>
    );
}

AdminDeliverySchedule.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
