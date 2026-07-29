// Lịch giao/thu CỦA CHÍNH shipper đang đăng nhập (bopcamping-lsch).
// Mobile-first: người dùng đang ở ngoài đường, một tay giữ đồ. Chữ to, target bấm ≥44px.
// Nút "Chỉ đường" và "Đã giao / Đã thu" thuộc bopcamping-w2yl (S6) — chưa có ở bước này.
import ShipperLayout from '@/Layouts/ShipperLayout';
import { money } from '@/lib/format';
import { Head, router } from '@inertiajs/react';
import { ReactNode } from 'react';

type ScheduleItem = { name: string; quantity: number };

type ScheduleOrder = {
    id: number;
    code: string;
    time: string | null;
    customer_name: string;
    customer_phone: string;
    customer_address: string | null;
    status: string;
    payment_status: string;
    amount_due: number;
    deposit_total: number;
    schedule_note: string | null;
    items: ScheduleItem[];
};

type TripKind = 'pickup' | 'return';

const PAYMENT_LABEL: Record<string, string> = {
    unpaid: 'chưa chuyển',
    deposit: 'đã chuyển cọc',
    full: 'đã chuyển hết',
};

const RED = { bg: '#f6ddd6', fg: '#b3493a' };

export default function ShipperSchedule({
    date,
    date_label,
    today,
    prev_date,
    next_date,
    pickups,
    returns,
}: {
    date: string;
    date_label: string;
    today: string;
    prev_date: string;
    next_date: string;
    pickups: ScheduleOrder[];
    returns: ScheduleOrder[];
}) {
    const goDate = (d: string) => router.get(route('shipper.schedule'), { date: d }, { preserveState: true });

    return (
        <>
            <Head title="Lịch giao của tôi" />

            <div className="mb-4 flex items-center justify-between gap-2">
                <button
                    type="button"
                    onClick={() => goDate(prev_date)}
                    aria-label="Ngày trước"
                    className="grid h-11 w-11 flex-none place-items-center rounded-[10px] border border-cardBorder bg-white text-[18px] text-pine"
                >
                    ‹
                </button>
                <div className="text-center">
                    <div className="text-[15px] font-bold text-pine">{date_label}</div>
                    {date !== today && (
                        <button
                            type="button"
                            onClick={() => goDate(today)}
                            className="mt-0.5 text-[12px] font-semibold text-grass underline-offset-2 hover:underline"
                        >
                            Về hôm nay
                        </button>
                    )}
                </div>
                <button
                    type="button"
                    onClick={() => goDate(next_date)}
                    aria-label="Ngày sau"
                    className="grid h-11 w-11 flex-none place-items-center rounded-[10px] border border-cardBorder bg-white text-[18px] text-pine"
                >
                    ›
                </button>
            </div>

            <Section title="Cần giao" kind="pickup" orders={pickups} emptyText="Hôm nay bạn không phải giao đơn nào." />
            <Section title="Cần thu" kind="return" orders={returns} emptyText="Hôm nay bạn không phải thu đơn nào." />
        </>
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
            <h2 className="mb-2.5 text-[15px] font-bold text-pine">
                {title} <span className="font-mono text-[13px] font-normal text-moss">({orders.length})</span>
            </h2>
            {orders.length === 0 ? (
                <div className="rounded-[16px] border border-cardBorder bg-white py-8 text-center text-[13px] text-moss">
                    {emptyText}
                </div>
            ) : (
                <div className="flex flex-col gap-3">
                    {orders.map((order, i) => (
                        <OrderCard key={order.id} order={order} kind={kind} index={i + 1} />
                    ))}
                </div>
            )}
        </div>
    );
}

function OrderCard({ order, kind, index }: { order: ScheduleOrder; kind: TripKind; index: number }) {
    return (
        <div className="rounded-[16px] border border-cardBorder bg-white p-4">
            <div className="flex items-start gap-3">
                {/* Thứ tự đi trong ngày — do admin sắp */}
                <div
                    className="grid h-8 w-8 flex-none place-items-center rounded-full text-[14px] font-extrabold"
                    style={{ background: '#eef5e0', color: '#3a5a1f' }}
                >
                    {index}
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-baseline gap-x-2">
                        {order.time ? (
                            <span className="font-mono text-[20px] font-bold" style={{ color: RED.fg }}>
                                {order.time}
                            </span>
                        ) : (
                            <span
                                className="rounded-pill px-2.5 py-1 text-[12px] font-bold"
                                style={{ background: '#fbe9d8', color: '#9a5a1f' }}
                            >
                                Chưa chốt giờ
                            </span>
                        )}
                        <span className="font-mono text-[12.5px] text-moss">{order.code}</span>
                    </div>
                    <div className="mt-1 text-[15px] font-semibold text-ink">{order.customer_name}</div>
                </div>
            </div>

            <a
                href={`tel:${order.customer_phone}`}
                className="mt-1 inline-block min-h-[44px] py-2 font-mono text-[15px] font-semibold text-grass"
            >
                📞 {order.customer_phone}
            </a>

            {order.customer_address && <div className="text-[13.5px] text-moss">{order.customer_address}</div>}

            {order.items.length > 0 && (
                <ul className="mt-2 border-t border-[#f1f4ea] pt-2 text-[13px] text-ink">
                    {order.items.map((it, i) => (
                        <li key={i}>
                            {it.name} × {it.quantity}
                        </li>
                    ))}
                </ul>
            )}

            {/* Lượt GIAO thu tiền COD; lượt THU hoàn cọc lại cho khách. */}
            <div className="mt-2 border-t border-[#f1f4ea] pt-2 text-[14px]">
                {kind === 'pickup' ? (
                    <>
                        <span className="font-mono font-bold text-ink">Thu khi giao: {money(order.amount_due)}</span>
                        <span className="ml-1 text-[13px] text-moss">
                            ({PAYMENT_LABEL[order.payment_status] ?? order.payment_status})
                        </span>
                    </>
                ) : (
                    <>
                        <span className="font-mono font-bold text-ink">Hoàn cọc: {money(order.deposit_total)}</span>
                        {order.payment_status !== 'full' && (
                            <span className="ml-1 text-[13px]" style={{ color: RED.fg }}>
                                · tiền thuê {PAYMENT_LABEL[order.payment_status] ?? order.payment_status}
                            </span>
                        )}
                    </>
                )}
            </div>

            {order.schedule_note && (
                <div
                    className="mt-2 rounded-[10px] p-2.5 text-[13px]"
                    style={{ background: '#f8faf4', color: '#5C6E47' }}
                >
                    <span className="font-bold text-pine">Ghi chú: </span>
                    {order.schedule_note}
                </div>
            )}
        </div>
    );
}

ShipperSchedule.layout = (page: ReactNode) => <ShipperLayout>{page}</ShipperLayout>;
