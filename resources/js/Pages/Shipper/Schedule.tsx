// Lịch giao/thu CỦA CHÍNH shipper đang đăng nhập (bopcamping-lsch + w2yl + lvw3).
// Lịch THÁNG lớn: ngày có lượt của mình thì bôi đỏ (↓ giao · ↑ thu), bấm ngày ra danh sách;
// bấm 1 đơn thì mở chi tiết để xem món + tiền và thu tiền ngay tại chỗ.
// Mobile-first: người dùng đang ở ngoài đường, một tay giữ đồ. Chữ to, target bấm ≥44px.
import ShipperLayout from '@/Layouts/ShipperLayout';
import { money } from '@/lib/format';
import { buildMonthGrid, WEEKDAY_LABELS } from '@/lib/monthGrid';
import { Head, router, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';

type ScheduleItem = { name: string; quantity: number };

type ScheduleOrder = {
    id: number;
    code: string;
    time: string | null;
    // Giờ đang hiện chỉ là giờ mặc định theo khung giờ shop (chủ shop chưa chốt).
    time_is_default: boolean;
    // Cả hai mốc của đơn — shipper biết luôn giao lúc nào, thu lúc nào (feedback 30/07).
    // Giờ CHỈ hiện ở đây (trong chi tiết), không hiện to ở đầu card (feedback 31/07).
    pickup_date: string;
    pickup_time: string | null;
    pickup_time_is_default: boolean;
    return_date: string;
    return_time: string | null;
    return_time_is_default: boolean;
    customer_name: string;
    customer_phone: string;
    customer_address: string | null;
    status: string;
    amount_due: number;
    rental_due: number;
    rental_paid: boolean;
    deposit_total: number;
    deposit_paid: boolean;
    deposit_refund_status: string;
    schedule_note: string | null;
    items: ScheduleItem[];
};

/** Số lượt giao/thu của 1 ngày — chỉ những ngày CÓ lượt của mình được trả về. */
type DayCount = { date: string; pickups: number; returns: number };

type TripKind = 'pickup' | 'return';

const RED = { bg: '#f6ddd6', fg: '#b3493a' };
const GREEN = { bg: '#dcebc4', fg: '#3a5a1f' };

export default function ShipperSchedule({
    month,
    month_label,
    prev_month,
    next_month,
    days,
    date,
    date_label,
    today,
    min_date,
    max_date,
    pickups,
    returns,
}: {
    month: string;
    month_label: string;
    prev_month: string;
    next_month: string;
    days: DayCount[];
    date: string;
    date_label: string;
    today: string;
    min_date: string;
    max_date: string;
    pickups: ScheduleOrder[];
    returns: ScheduleOrder[];
}) {
    const weeks = buildMonthGrid(month);
    const byDate = new Map(days.map((d) => [d.date, d]));

    const nav = (params: Record<string, string>) =>
        router.get(route('shipper.schedule'), { month, date, ...params }, { preserveState: true, preserveScroll: true });

    return (
        <>
            <Head title="Lịch của tôi" />

            {/* Lịch tháng */}
            <div className="mb-5 rounded-[16px] border border-cardBorder bg-white p-2.5">
                <div className="mb-2 flex items-center justify-between gap-2">
                    <button
                        type="button"
                        onClick={() => nav({ month: prev_month })}
                        aria-label="Tháng trước"
                        className="grid h-11 w-11 flex-none place-items-center rounded-[10px] border border-cardBorder text-[18px] text-pine"
                    >
                        ‹
                    </button>
                    <div className="text-center">
                        <div className="text-[15px] font-bold text-pine">{month_label}</div>
                        {date !== today && (
                            <button
                                type="button"
                                onClick={() => nav({ month: today.slice(0, 7), date: today })}
                                className="text-[12px] font-semibold text-grass underline-offset-2 hover:underline"
                            >
                                Về hôm nay
                            </button>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={() => nav({ month: next_month })}
                        aria-label="Tháng sau"
                        className="grid h-11 w-11 flex-none place-items-center rounded-[10px] border border-cardBorder text-[18px] text-pine"
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
                                        // Ngoài khoảng được xem thì khoá — server cũng kẹp lại.
                                        disabled={cell < min_date || cell > max_date}
                                        selected={cell === date}
                                        isToday={cell === today}
                                        onSelect={(d) => nav({ date: d })}
                                    />
                                ),
                            )}
                        </div>
                    ))}
                </div>
            </div>

            <h2 className="mb-3 text-[15px] font-bold text-pine">{date_label}</h2>

            <Section title="Cần giao" kind="pickup" orders={pickups} emptyText="Không có đơn nào cần giao." />
            <Section title="Cần thu" kind="return" orders={returns} emptyText="Không có đơn nào cần thu." />
        </>
    );
}

/** 1 ô ngày: có lượt → bôi đỏ + đếm (↓ giao · ↑ thu); ngoài khoảng cho xem → khoá. */
function DayCell({
    date,
    counts,
    disabled,
    selected,
    isToday,
    onSelect,
}: {
    date: string;
    counts?: DayCount;
    disabled: boolean;
    selected: boolean;
    isToday: boolean;
    onSelect: (d: string) => void;
}) {
    const day = Number(date.slice(-2));
    const pickups = counts?.pickups ?? 0;
    const returns = counts?.returns ?? 0;
    const has = pickups + returns > 0;

    return (
        <button
            type="button"
            disabled={disabled}
            aria-label={`Ngày ${day}${has ? ` · ${pickups} giao, ${returns} thu` : ' · không có lượt'}`}
            aria-current={selected ? 'date' : undefined}
            onClick={() => onSelect(date)}
            className={`min-h-[52px] rounded-[10px] border p-1 text-center transition ${
                disabled ? 'cursor-not-allowed opacity-40' : ''
            } ${selected ? 'border-grass ring-1 ring-grass' : 'border-[#eef2e3]'}`}
            style={{ background: has ? RED.bg : '#fafcf7' }}
        >
            <div
                className={`text-[14px] ${has ? 'font-bold' : 'font-semibold'} ${isToday ? 'underline underline-offset-2' : ''}`}
                style={{ color: has ? RED.fg : '#5C6E47' }}
            >
                {day}
            </div>
            {has && (
                <div className="font-mono text-[10px] leading-tight" style={{ color: RED.fg }}>
                    {pickups > 0 && `${pickups}↓`}
                    {pickups > 0 && returns > 0 && ' '}
                    {returns > 0 && `${returns}↑`}
                </div>
            )}
        </button>
    );
}

function Section({
    title,
    kind,
    orders,
    emptyText,
}: {
    title: string;
    kind: TripKind;
    orders: ScheduleOrder[];
    emptyText: string;
}) {
    return (
        <div className="mb-7">
            <h3 className="mb-2.5 text-[14px] font-bold uppercase tracking-[0.04em] text-grass">
                {title} <span className="font-mono text-[13px] font-normal normal-case text-moss">({orders.length})</span>
            </h3>
            {orders.length === 0 ? (
                <div className="rounded-[16px] border border-cardBorder bg-white py-8 text-center text-[13px] text-moss">
                    {emptyText}
                </div>
            ) : (
                <div className="flex flex-col gap-3">
                    {orders.map((order) => (
                        <OrderCard key={order.id} order={order} kind={kind} />
                    ))}
                </div>
            )}
        </div>
    );
}

/** Đầu card luôn hiện giờ + tên + SĐT; bấm để mở/đóng phần chi tiết (món, tiền, thao tác). */
function OrderCard({ order, kind }: { order: ScheduleOrder; kind: TripKind }) {
    const [open, setOpen] = useState(false);
    const moneyLeft = (kind === 'pickup' ? !order.rental_paid || !order.deposit_paid : false);

    return (
        <div className="overflow-hidden rounded-[16px] border border-cardBorder bg-white">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-expanded={open}
                className="flex w-full items-start justify-between gap-3 p-4 text-left"
            >
                <div className="min-w-0">
                    <div className="font-mono text-[12.5px] text-moss">{order.code}</div>
                    <div className="mt-1 text-[15px] font-semibold text-ink">{order.customer_name}</div>
                    {order.customer_address && (
                        <div className="mt-0.5 line-clamp-2 text-[13px] text-moss">{order.customer_address}</div>
                    )}
                    {moneyLeft && (
                        <span
                            className="mt-1.5 inline-block rounded-pill px-2 py-0.5 text-[11.5px] font-bold"
                            style={{ background: RED.bg, color: RED.fg }}
                        >
                            Cần thu tiền
                        </span>
                    )}
                </div>
                <span className="mt-1 shrink-0 text-[15px] text-[#a3ad92]">{open ? '▲' : '▼'}</span>
            </button>

            {open && <OrderDetail order={order} kind={kind} />}
        </div>
    );
}

function OrderDetail({ order, kind }: { order: ScheduleOrder; kind: TripKind }) {
    const errors = usePage().props.errors as Record<string, string>;

    return (
        <div className="border-t border-[#f1f4ea] px-4 pb-4">
            <a
                href={`tel:${order.customer_phone}`}
                className="inline-block min-h-[44px] py-2 font-mono text-[15px] font-semibold text-grass"
            >
                📞 {order.customer_phone}
            </a>

            {order.customer_address && (
                <a
                    href={`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(order.customer_address)}`}
                    target="_blank"
                    rel="noreferrer"
                    className="ml-2 inline-flex min-h-[44px] items-center gap-1.5 rounded-[10px] border border-cardBorder px-3 text-[13.5px] font-semibold text-grass"
                >
                    🧭 Chỉ đường
                </a>
            )}

            {/* Cả 2 mốc để shipper biết đơn này giao lúc nào và thu lúc nào (feedback 30/07) */}
            <div className="mt-2 border-t border-[#f1f4ea] pt-2 text-[13.5px]">
                <LegTime label="Giao" date={order.pickup_date} time={order.pickup_time} isDefault={order.pickup_time_is_default} />
                <LegTime label="Thu" date={order.return_date} time={order.return_time} isDefault={order.return_time_is_default} />
            </div>

            {order.items.length > 0 && (
                <div className="mt-2 border-t border-[#f1f4ea] pt-2">
                    <div className="mb-1 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">Sản phẩm</div>
                    <ul className="text-[14px] text-ink">
                        {order.items.map((it, i) => (
                            <li key={i} className="flex justify-between gap-3 py-0.5">
                                <span>{it.name}</span>
                                <span className="font-mono shrink-0">× {it.quantity}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Tiền: 2 khoản độc lập. Khoản nào chưa thu thì shipper thu tại chỗ (bopcamping-lvw3) */}
            <div className="mt-3 border-t border-[#f1f4ea] pt-2">
                {errors.payment && <p className="mb-2 text-[12.5px] text-[#b3493a]">{errors.payment}</p>}
                <MoneyRow order={order} kind="rental" label="Tiền thuê" amount={order.rental_due} paid={order.rental_paid} />
                <MoneyRow order={order} kind="deposit" label="Tiền cọc" amount={order.deposit_total} paid={order.deposit_paid} />
                {!order.rental_paid || !order.deposit_paid ? (
                    <p className="mt-1.5 text-[12px] text-[#a3ad92]">
                        Tổng cần thu: <span className="font-mono font-bold text-ink">
                            {money((order.rental_paid ? 0 : order.rental_due) + (order.deposit_paid ? 0 : order.deposit_total))}
                        </span>
                    </p>
                ) : (
                    <p className="mt-1.5 text-[12px]" style={{ color: GREEN.fg }}>Đã thu đủ tiền đơn này.</p>
                )}
            </div>

            {order.schedule_note && (
                <div className="mt-3 rounded-[10px] p-2.5 text-[13px]" style={{ background: '#f8faf4', color: '#5C6E47' }}>
                    <span className="font-bold text-pine">Ghi chú: </span>
                    {order.schedule_note}
                </div>
            )}

            {kind === 'return' && <RefundDeposit order={order} />}
            <MarkDoneButton order={order} kind={kind} />
        </div>
    );
}

/** 1 mốc thời gian của đơn; ghi rõ khi giờ chỉ là mặc định (chủ shop chưa chốt). */
function LegTime({
    label,
    date,
    time,
    isDefault,
}: {
    label: string;
    date: string;
    time: string | null;
    isDefault: boolean;
}) {
    return (
        <div className="flex justify-between gap-3 py-0.5">
            <span className="text-moss">{label}</span>
            <span className="text-right font-mono text-ink">
                {date}
                {time ? ` · ${time}` : ' · chưa chốt giờ'}
                {time && isDefault && <span className="ml-1 font-sans text-[11px] text-[#a3ad92]">mặc định</span>}
            </span>
        </div>
    );
}

/** 1 khoản tiền: đã thu → dấu ✓; chưa thu → nút thu (hỏi lại 1 bước vì liên quan tiền). */
function MoneyRow({
    order,
    kind,
    label,
    amount,
    paid,
}: {
    order: ScheduleOrder;
    kind: 'rental' | 'deposit';
    label: string;
    amount: number;
    paid: boolean;
}) {
    const [confirming, setConfirming] = useState(false);
    const [saving, setSaving] = useState(false);

    const collect = () => {
        setSaving(true);
        router.patch(
            route('shipper.orders.collect', { order: order.id, kind }),
            {},
            { preserveScroll: true, onFinish: () => { setSaving(false); setConfirming(false); } },
        );
    };

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 py-1.5">
            <div>
                <span className="text-[13.5px] font-semibold text-ink">{label}</span>
                <span className="ml-1.5 font-mono text-[14px] text-ink">{money(amount)}</span>
            </div>
            {paid ? (
                <span
                    className="rounded-pill px-2.5 py-1 text-[12px] font-bold"
                    style={{ background: GREEN.bg, color: GREEN.fg }}
                >
                    ✓ Đã thu
                </span>
            ) : confirming ? (
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => setConfirming(false)}
                        className="min-h-[40px] rounded-[10px] border border-cardBorder px-3 text-[13px] font-semibold text-moss"
                    >
                        Chưa
                    </button>
                    <button
                        type="button"
                        onClick={collect}
                        disabled={saving}
                        className="min-h-[40px] rounded-[10px] px-3 text-[13px] font-bold text-white disabled:opacity-60"
                        style={{ background: '#557A2B' }}
                    >
                        {saving ? 'Đang lưu…' : `Xác nhận thu ${money(amount)}`}
                    </button>
                </div>
            ) : (
                <button
                    type="button"
                    onClick={() => setConfirming(true)}
                    className="min-h-[40px] rounded-[10px] px-3 text-[13px] font-bold text-white"
                    style={{ background: RED.fg }}
                >
                    Thu {label.toLowerCase()}
                </button>
            )}
        </div>
    );
}

/** Trả cọc lại cho khách sau khi kiểm đồ — chỉ ở lượt THU (bopcamping-lvw3). */
function RefundDeposit({ order }: { order: ScheduleOrder }) {
    const errors = usePage().props.errors as Record<string, string>;
    const [open, setOpen] = useState(false);
    const [note, setNote] = useState('');
    const [saving, setSaving] = useState(false);

    if (order.deposit_refund_status === 'refunded') {
        return (
            <div className="mt-3 rounded-[10px] p-2.5 text-[13px]" style={{ background: GREEN.bg, color: GREEN.fg }}>
                ✓ Đã hoàn cọc {money(order.deposit_total)} cho khách
            </div>
        );
    }

    const submit = () => {
        setSaving(true);
        router.patch(
            route('shipper.orders.refund', order.id),
            { deposit_refund_note: note || null },
            { preserveScroll: true, onFinish: () => { setSaving(false); setOpen(false); } },
        );
    };

    return (
        <div className="mt-3 rounded-[12px] border border-[#eef2e3] p-3">
            <div className="text-[13px] font-bold text-pine">Trả cọc cho khách</div>
            <p className="mt-0.5 text-[12.5px] text-moss">
                Kiểm đồ trước khi trả {money(order.deposit_total)}. Nếu thiếu/hư thì ghi lý do trừ cọc — có gì khác
                thường thì gọi chủ shop.
            </p>
            {errors.refund && <p className="mt-1.5 text-[12.5px] text-[#b3493a]">{errors.refund}</p>}
            {open ? (
                <>
                    <textarea
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        rows={2}
                        maxLength={500}
                        placeholder="Lý do trừ cọc nếu có: rách lều, thiếu cọc lều…"
                        className="mt-2 w-full resize-y rounded-[10px] border border-cardBorder bg-[#fafcf7] px-2.5 py-2 text-[13.5px] text-ink outline-none focus:border-grass"
                    />
                    <div className="mt-2 flex gap-2">
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="min-h-[44px] flex-1 rounded-[11px] border border-cardBorder text-[14px] font-semibold text-moss"
                        >
                            Thôi
                        </button>
                        <button
                            type="button"
                            onClick={submit}
                            disabled={saving}
                            className="min-h-[44px] flex-[2] rounded-[11px] text-[14px] font-bold text-white disabled:opacity-60"
                            style={{ background: '#557A2B' }}
                        >
                            {saving ? 'Đang lưu…' : 'Xác nhận đã hoàn cọc'}
                        </button>
                    </div>
                </>
            ) : (
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="mt-2 min-h-[44px] w-full rounded-[11px] border border-grass text-[14px] font-bold text-grass"
                >
                    Đã hoàn cọc
                </button>
            )}
        </div>
    );
}

/**
 * Đánh dấu đã giao (confirmed → renting) / đã thu (renting → returned). Chỉ hiện khi đơn
 * đang ở đúng trạng thái. Bấm 1 lần ra hỏi lại vì việc này gửi mail cho khách.
 */
function MarkDoneButton({ order, kind }: { order: ScheduleOrder; kind: TripKind }) {
    const errors = usePage().props.errors as Record<string, string>;
    const [confirming, setConfirming] = useState(false);
    const [saving, setSaving] = useState(false);

    const canDeliver = kind === 'pickup' && order.status === 'confirmed';
    const canCollect = kind === 'return' && order.status === 'renting';

    if (!canDeliver && !canCollect) {
        return (
            <div className="mt-3 text-center text-[12.5px] text-[#a3ad92]">
                {kind === 'pickup' && order.status === 'renting'
                    ? '✓ Đã giao'
                    : kind === 'pickup'
                      ? 'Chờ shop xác nhận đơn'
                      : '✓ Đã thu đồ'}
            </div>
        );
    }

    const label = canDeliver ? 'Đã giao xong' : 'Đã thu đồ';
    const submit = () => {
        setSaving(true);
        router.patch(
            route(canDeliver ? 'shipper.orders.delivered' : 'shipper.orders.collected', order.id),
            {},
            { preserveScroll: true, onFinish: () => { setSaving(false); setConfirming(false); } },
        );
    };

    return (
        <div className="mt-3">
            {errors.status && <p className="mb-2 text-[12.5px] text-[#b3493a]">{errors.status}</p>}
            {confirming ? (
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => setConfirming(false)}
                        className="min-h-[48px] flex-1 rounded-[12px] border border-cardBorder text-[14px] font-semibold text-moss"
                    >
                        Chưa
                    </button>
                    <button
                        type="button"
                        onClick={submit}
                        disabled={saving}
                        className="min-h-[48px] flex-[2] rounded-[12px] text-[14px] font-bold text-white disabled:opacity-60"
                        style={{ background: '#557A2B' }}
                    >
                        {saving ? 'Đang lưu…' : `Xác nhận ${label.toLowerCase()}`}
                    </button>
                </div>
            ) : (
                <button
                    type="button"
                    onClick={() => setConfirming(true)}
                    className="min-h-[48px] w-full rounded-[12px] text-[15px] font-bold text-white"
                    style={{ background: '#557A2B' }}
                >
                    {label}
                </button>
            )}
        </div>
    );
}

ShipperSchedule.layout = (page: ReactNode) => <ShipperLayout>{page}</ShipperLayout>;
