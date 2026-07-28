// Lịch giao/thu theo ngày cho shipper (bopcamping-rtkh, prd_delivery_schedule FR-5).
// Mobile-first: shipper mở 1 trang là biết hôm nay giao/thu đơn nào lúc mấy giờ.
// KHÔNG dùng <table> — mỗi đơn là 1 card dọc, target bấm ≥44px.
import AdminLayout from '@/Layouts/AdminLayout';
import { money } from '@/lib/format';
import { sessionLabel, shopHours, type Session } from '@/lib/session';
import { Head, router, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

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
};

type Stats = { pickups: number; returns: number; unscheduled: number };

// Lượt đi của shipper: giao đồ (thu tiền COD) hoặc thu đồ (hoàn cọc).
type TripKind = 'pickup' | 'return';

// Nhãn tình trạng chuyển tiền — bản rút gọn cho card shipper (nguồn đầy đủ ở orderShared.tsx).
const PAYMENT_LABEL: Record<string, string> = {
    unpaid: 'chưa chuyển',
    deposit: 'đã chuyển cọc',
    full: 'đã chuyển hết',
};

export default function AdminDeliverySchedule({
    date,
    date_label,
    prev_date,
    next_date,
    today,
    pickups,
    returns,
    stats,
}: {
    date: string;
    date_label: string;
    prev_date: string;
    next_date: string;
    today: string;
    pickups: ScheduleOrder[];
    returns: ScheduleOrder[];
    stats: Stats;
}) {
    const goTo = (d: string) =>
        router.get(
            route('admin.schedule'),
            { date: d },
            { preserveState: true },
        );

    return (
        <>
            <Head title="Quản trị · Lịch giao" />
            <div className="p-4 sm:p-6">
                {/* Header */}
                <div className="mb-4">
                    <h1 className="text-[20px] font-extrabold text-pine">
                        Lịch giao/thu
                    </h1>
                    <p className="mt-0.5 text-[13px] text-moss">{date_label}</p>
                </div>

                {/* Điều hướng ngày — target bấm ≥44px cho shipper dùng điện thoại */}
                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        onClick={() => goTo(prev_date)}
                        aria-label="Ngày trước"
                        className="grid h-11 w-11 flex-none place-items-center rounded-[10px] border border-cardBorder bg-white text-[18px] text-pine hover:border-grass"
                    >
                        ‹
                    </button>
                    <button
                        type="button"
                        onClick={() => goTo(today)}
                        className={`h-11 rounded-[10px] border px-4 text-[13px] font-semibold transition ${
                            date === today
                                ? 'border-grass bg-grass text-white'
                                : 'border-cardBorder bg-white text-pine hover:border-grass'
                        }`}
                    >
                        Hôm nay
                    </button>
                    <button
                        type="button"
                        onClick={() => goTo(next_date)}
                        aria-label="Ngày sau"
                        className="grid h-11 w-11 flex-none place-items-center rounded-[10px] border border-cardBorder bg-white text-[18px] text-pine hover:border-grass"
                    >
                        ›
                    </button>
                    <input
                        type="date"
                        value={date}
                        onChange={(e) => e.target.value && goTo(e.target.value)}
                        className="h-11 rounded-[10px] border border-cardBorder bg-white px-3 text-[13px] text-ink outline-none focus:border-grass"
                    />
                </div>

                {/* Chip đếm */}
                <div className="mb-6 flex flex-wrap gap-2 text-[13px] font-semibold">
                    <span className="rounded-pill border border-cardBorder bg-white px-3 py-1.5 text-pine">
                        {stats.pickups} giao
                    </span>
                    <span className="rounded-pill border border-cardBorder bg-white px-3 py-1.5 text-pine">
                        {stats.returns} thu
                    </span>
                    {stats.unscheduled > 0 && (
                        <span
                            className="rounded-pill px-3 py-1.5"
                            style={{ background: '#fbe9d8', color: '#9a5a1f' }}
                        >
                            {stats.unscheduled} chưa chốt giờ
                        </span>
                    )}
                </div>

                <ScheduleSection
                    title="Cần giao"
                    kind="pickup"
                    orders={pickups}
                    emptyText="Không có đơn nào cần giao ngày này."
                />
                <ScheduleSection
                    title="Cần thu"
                    kind="return"
                    orders={returns}
                    emptyText="Không có đơn nào cần thu ngày này."
                />
            </div>
        </>
    );
}

function ScheduleSection({
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
        <div className="mb-8">
            <h2 className="mb-3 text-[15px] font-bold text-pine">
                {title}{' '}
                <span className="font-mono text-[13px] font-normal text-moss">
                    ({orders.length})
                </span>
            </h2>
            {orders.length === 0 ? (
                <div className="rounded-[16px] border border-cardBorder bg-white py-10 text-center text-[13px] text-moss">
                    {emptyText}
                </div>
            ) : (
                <div className="flex flex-col gap-3">
                    {orders.map((order) => (
                        <ScheduleOrderCard key={order.id} order={order} kind={kind} />
                    ))}
                </div>
            )}
        </div>
    );
}

function ScheduleOrderCard({ order, kind }: { order: ScheduleOrder; kind: TripKind }) {
    const hours = shopHours(
        (usePage().props as { site?: Parameters<typeof shopHours>[0] }).site,
    );
    const sessTag = sessionLabel(order.session, hours);

    return (
        <div className="rounded-[16px] border border-cardBorder bg-white p-4">
            <div className="flex items-start justify-between gap-3">
                {order.time ? (
                    <div className="font-mono text-[20px] font-bold text-pine">
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
                <div className="text-[14px] font-semibold text-ink">
                    {order.customer_name}
                </div>
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

            {order.customer_address && (
                <div className="text-[13px] text-moss">
                    {order.customer_address}
                </div>
            )}
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
                        <span className="font-mono font-bold text-ink">
                            Thu khi nhận: {money(order.amount_due)}
                        </span>
                        <span className="ml-1 text-moss">
                            (
                            {PAYMENT_LABEL[order.payment_status] ??
                                order.payment_status}
                            )
                        </span>
                    </>
                ) : (
                    <>
                        <span className="font-mono font-bold text-ink">
                            Hoàn cọc: {money(order.deposit_total)}
                        </span>
                        {order.payment_status !== 'full' && (
                            <span className="ml-1" style={{ color: '#b3493a' }}>
                                · tiền thuê{' '}
                                {PAYMENT_LABEL[order.payment_status] ??
                                    order.payment_status}
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
                    <span className="font-bold text-pine">
                        Ghi chú shipper:{' '}
                    </span>
                    {order.schedule_note}
                </div>
            )}
        </div>
    );
}

AdminDeliverySchedule.layout = (page: ReactNode) => (
    <AdminLayout>{page}</AdminLayout>
);
