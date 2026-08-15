import PaymentQr, { type PaymentQrData } from '@/Components/PaymentQr';
import PaymentStatus from '@/Components/PaymentStatus';
import { COMBO_GRAD } from '@/Components/site/ComboCard';
import DateRangeCalendar from '@/Components/site/DateRangeCalendar';
import OrderLookupPanel, {
    type LookupProps,
} from '@/Components/site/OrderLookupPanel';
import SiteLayout from '@/Layouts/SiteLayout';
import { emit, EVENTS } from '@/lib/bus';
import { getCart, setCart, type CartLine, type CartLocation } from '@/lib/cart';
import { dayCount, money, rangeText } from '@/lib/format';
import { gradFor } from '@/lib/grad';
import { STATUS_LABEL, STATUS_STYLE } from '@/lib/orderStatus';
import { sessionLabel, shopHours, type Session } from '@/lib/session';
import {
    VOUCHER_SOURCE_FALLBACK,
    VOUCHER_SOURCE_LABEL,
    voucherValueText,
    type VoucherType,
} from '@/lib/voucher';
import type { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Fragment, ReactNode, useEffect, useMemo, useState } from 'react';

// Dòng hiển thị trong đơn: thuê lẻ, hoặc combo đã gộp (children = món trong 1 bộ).
type OrderGroup = {
    kind: 'product' | 'combo';
    name: string;
    quantity: number;
    days: number;
    subtotal: number;
    children?: { name: string; quantity: number }[];
};

type OrderDiscount = { label: string; amount: number };

type ReorderPayload = {
    products: {
        id: number;
        name: string;
        cat: string;
        price: number;
        deposit: number;
        qty: number;
        early_return_pct: number;
        locations: CartLocation[];
    }[];
    combos: {
        id: number;
        name: string;
        price: number;
        deposit: number;
        qty: number;
        comboItems: { name: string; qty: number }[];
        locations: CartLocation[];
    }[];
    skipped: number;
    // Cửa hàng phục vụ mọi món (feedback 2026-07-27) — cho khách đổi store ở modal đặt lại.
    store_options: { id: number; name: string }[];
};

type AccountOrder = {
    id: number;
    code: string;
    status: string;
    status_label: string;
    created_at: string;
    start_date: string;
    end_date: string;
    days: number;
    // Buổi khách chọn + giờ nhận/trả (spec 2026-07-26) — hiển thị lại cho khách.
    session: Session | null;
    requested_pickup_time: string | null;
    requested_return_time: string | null;
    // Giờ shop ĐÃ CHỐT (spec 2026-07-28) — ưu tiên hiện thay cho giờ mong muốn.
    confirmed_pickup_time: string | null;
    confirmed_return_time: string | null;
    address: string | null;
    phone: string;
    note: string | null;
    total_price: number;
    // Phụ phí từng khoản — optional phòng server cũ (xem orderShared.tsx).
    extra_fees?: { name: string; value: number }[];
    deposit_total: number;
    discount_total: number;
    amount_due: number;
    // QR chuyển khoản (bopcamping-55rh) — null khi đơn không còn ở 'pending' / đã thu đủ.
    payment_qr: PaymentQrData | null;
    // Shop đã nhận khoản nào (bopcamping-pew1) + SỐ TIỀN thật đã nhận (bopcamping-r3fy).
    rental_due: number;
    rental_paid: boolean;
    deposit_paid: boolean;
    rental_received: number;
    deposit_received: number;
    outstanding_due: number;
    groups: OrderGroup[];
    discounts: OrderDiscount[];
    reorder: ReorderPayload | null;
    review_token: string | null;
    review_submitted: boolean;
};

type Voucher = {
    code: string;
    type: VoucherType;
    value: number;
    source: string;
    bucket: 'active' | 'used' | 'expired';
    expires_at: string | null;
};

type Props = PageProps<{
    stats: {
        completedProductCount: number;
        activeOrderCount: number;
        referralCount: number;
    };
    orders: AccountOrder[];
    referralCode: string;
    vouchers: Voucher[];
    lookup: LookupProps;
}>;

const ACTIVE_STATUSES = ['pending', 'confirmed', 'renting'];

const BUCKET_TABS: { key: Voucher['bucket']; label: string }[] = [
    { key: 'active', label: 'Đang dùng được' },
    { key: 'used', label: 'Đã dùng' },
    { key: 'expired', label: 'Hết hạn' },
];

export default function Account() {
    const { auth, stats, orders, referralCode, vouchers, lookup } =
        usePage<Props>().props;
    const [copied, setCopied] = useState(false);
    const [tab, setTab] = useState<Voucher['bucket']>('active');

    const activeOrders = orders.filter((o) =>
        ACTIVE_STATUSES.includes(o.status),
    );
    const doneOrders = orders.filter(
        (o) => !ACTIVE_STATUSES.includes(o.status),
    );
    const [orderTab, setOrderTab] = useState<'active' | 'done'>(
        activeOrders.length > 0 || doneOrders.length === 0 ? 'active' : 'done',
    );
    const shownOrders = orderTab === 'active' ? activeOrders : doneOrders;

    // Đơn nào đang mở chi tiết (bấm hàng để bung — như bảng quản lý admin).
    const [expandedId, setExpandedId] = useState<number | null>(null);
    // Modal "Đặt lại": chọn lại ngày trước khi thêm vào giỏ.
    const [reorder, setReorder] = useState<{
        order: AccountOrder;
        start: string | null;
        end: string | null;
    } | null>(null);

    const [copiedLink, setCopiedLink] = useState(false);
    const shareLink =
        typeof window !== 'undefined'
            ? `${window.location.origin}/?ref=${referralCode}`
            : `/?ref=${referralCode}`;

    const copyCode = async () => {
        try {
            await navigator.clipboard.writeText(referralCode);
            setCopied(true);
            setTimeout(() => setCopied(false), 1800);
        } catch {
            // Trình duyệt chặn clipboard — khách vẫn đọc/gõ tay được mã.
        }
    };

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(shareLink);
            setCopiedLink(true);
            setTimeout(() => setCopiedLink(false), 1800);
        } catch {
            // Bỏ qua nếu trình duyệt chặn clipboard.
        }
    };

    const countBy = (bucket: Voucher['bucket']) =>
        vouchers.filter((v) => v.bucket === bucket).length;
    const shown = vouchers.filter((v) => v.bucket === tab);

    /** Nhảy xuống section tra cứu với mã đơn + SĐT của đơn này (xem timeline). */
    const viewProgress = (o: AccountOrder) => {
        router.get(
            route('account'),
            { code: o.code, phone: o.phone },
            {
                preserveScroll: true,
                onSuccess: () =>
                    document
                        .getElementById('tra-cuu')
                        ?.scrollIntoView({ behavior: 'smooth' }),
            },
        );
    };

    return (
        <>
            <Head title="Tài khoản của tôi" />
            <main className="mx-auto max-w-[1100px] px-5 pb-12 pt-[38px]">
                <h1
                    className="mb-1 font-extrabold tracking-tight text-ink"
                    style={{ fontSize: 'clamp(24px,3vw,32px)' }}
                >
                    Tài khoản của tôi
                </h1>
                <p className="mb-6 text-moss">
                    Chào {auth.user?.name ?? 'bạn'} 👋 — tổng quan đơn thuê,
                    voucher và mã giới thiệu của bạn.
                </p>

                {/* Thống kê */}
                <div className="mb-[22px] grid grid-cols-2 gap-3.5 sm:grid-cols-3">
                    <Stat
                        label="Sản phẩm đã thuê"
                        value={stats.completedProductCount}
                        hint="đã hoàn thành"
                    />
                    <Stat
                        label="Đơn đang thuê"
                        value={stats.activeOrderCount}
                        hint="chưa hoàn thành"
                    />
                    <Stat
                        label="Giới thiệu thành công"
                        value={stats.referralCount}
                        hint="bạn đã mời"
                    />
                </div>

                {/* Đơn thuê — bảng gọn giống màn quản lý admin, bấm hàng để mở chi tiết */}
                <section className="mb-[22px]">
                    <h2 className="mb-3 text-[18px] font-bold text-ink">
                        Đơn thuê của bạn
                    </h2>
                    <div className="mb-3 flex flex-wrap items-center gap-2">
                        <button
                            onClick={() => {
                                setOrderTab('active');
                                setExpandedId(null);
                            }}
                            className={`rounded-pill px-3 py-1.5 text-[13px] font-semibold transition ${
                                orderTab === 'active'
                                    ? 'bg-grass text-white'
                                    : 'bg-[#eef2e3] text-pine hover:bg-[#e2e8d2]'
                            }`}
                        >
                            Đang thuê ({activeOrders.length})
                        </button>
                        <button
                            onClick={() => {
                                setOrderTab('done');
                                setExpandedId(null);
                            }}
                            className={`rounded-pill px-3 py-1.5 text-[13px] font-semibold transition ${
                                orderTab === 'done'
                                    ? 'bg-grass text-white'
                                    : 'bg-[#eef2e3] text-pine hover:bg-[#e2e8d2]'
                            }`}
                        >
                            Đã kết thúc ({doneOrders.length})
                        </button>
                    </div>

                    {shownOrders.length === 0 ? (
                        <div
                            className="rounded-card border border-dashed bg-white p-8 text-center"
                            style={{ borderColor: '#d6ddc4' }}
                        >
                            <div className="mb-1 text-[28px]">⛺</div>
                            <div className="font-semibold text-ink">
                                {orderTab === 'active'
                                    ? 'Chưa có đơn nào đang thuê'
                                    : 'Chưa có đơn nào đã kết thúc'}
                            </div>
                            <div className="mt-1 text-[14px] text-moss">
                                Khám phá thiết bị và đặt thuê cho chuyến đi sắp
                                tới nhé.
                            </div>
                        </div>
                    ) : (
                        <>
                            {/* Desktop (md+): bảng gọn giống màn quản lý admin */}
                            <div className="hidden overflow-x-auto rounded-card border border-cardBorder bg-white md:block">
                                <table className="w-full text-[13px]">
                                    <thead>
                                        <tr
                                            className="border-b border-[#eef2e3]"
                                            style={{ background: '#f8faf4' }}
                                        >
                                            <th className="px-4 py-3 text-left font-semibold text-moss">
                                                Mã đơn
                                            </th>
                                            <th className="px-4 py-3 text-left font-semibold text-moss">
                                                Ngày thuê
                                            </th>
                                            <th className="px-4 py-3 text-center font-semibold text-moss">
                                                Số món
                                            </th>
                                            <th className="px-4 py-3 text-right font-semibold text-moss">
                                                Trả khi nhận
                                            </th>
                                            <th className="px-4 py-3 text-left font-semibold text-moss">
                                                Trạng thái
                                            </th>
                                            <th
                                                className="px-3 py-3"
                                                aria-label="Chi tiết"
                                            />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {shownOrders.map((order) => {
                                            const style =
                                                STATUS_STYLE[order.status] ??
                                                STATUS_STYLE.pending;
                                            const expanded =
                                                expandedId === order.id;
                                            const itemCount =
                                                order.groups.reduce(
                                                    (s, g) => s + g.quantity,
                                                    0,
                                                );
                                            return (
                                                <Fragment key={order.id}>
                                                    <tr
                                                        className="cursor-pointer border-b border-[#f1f4ea] hover:bg-[#fafcf7]"
                                                        onClick={() =>
                                                            setExpandedId(
                                                                expanded
                                                                    ? null
                                                                    : order.id,
                                                            )
                                                        }
                                                    >
                                                        <td className="px-4 py-3 font-mono font-bold text-grass">
                                                            {order.code}
                                                        </td>
                                                        <td className="whitespace-nowrap px-4 py-3 font-mono text-[12px] text-pine">
                                                            {order.start_date} →{' '}
                                                            {order.end_date}
                                                        </td>
                                                        <td className="px-4 py-3 text-center text-moss">
                                                            {itemCount}
                                                        </td>
                                                        <td className="px-4 py-3 text-right font-mono font-bold text-ink">
                                                            {money(
                                                                order.amount_due,
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <span
                                                                className="whitespace-nowrap rounded-pill px-2.5 py-1 text-[11.5px] font-bold"
                                                                style={{
                                                                    color: style.color,
                                                                    background:
                                                                        style.bg,
                                                                }}
                                                            >
                                                                {STATUS_LABEL[
                                                                    order.status
                                                                ] ??
                                                                    order.status}
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-3 text-right">
                                                            <svg
                                                                width="18"
                                                                height="18"
                                                                viewBox="0 0 24 24"
                                                                fill="none"
                                                                stroke="#8a967a"
                                                                strokeWidth="2.2"
                                                                strokeLinecap="round"
                                                                strokeLinejoin="round"
                                                                className={`inline-block transition-transform ${expanded ? 'rotate-180' : ''}`}
                                                            >
                                                                <path d="m6 9 6 6 6-6" />
                                                            </svg>
                                                        </td>
                                                    </tr>
                                                    {expanded && (
                                                        <tr className="border-b border-[#f1f4ea]">
                                                            <td
                                                                colSpan={6}
                                                                className="px-4 pb-4 pt-1"
                                                                style={{
                                                                    background:
                                                                        '#fafcf7',
                                                                }}
                                                            >
                                                                <OrderDetail
                                                                    order={
                                                                        order
                                                                    }
                                                                    onReorder={() =>
                                                                        setReorder(
                                                                            {
                                                                                order,
                                                                                start: null,
                                                                                end: null,
                                                                            },
                                                                        )
                                                                    }
                                                                    onViewProgress={() =>
                                                                        viewProgress(
                                                                            order,
                                                                        )
                                                                    }
                                                                />
                                                            </td>
                                                        </tr>
                                                    )}
                                                </Fragment>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            {/* Mobile: card gọn — bấm bung chi tiết full-width (số tiền không bị cắt) */}
                            <div className="flex flex-col gap-3 md:hidden">
                                {shownOrders.map((order) => {
                                    const style =
                                        STATUS_STYLE[order.status] ??
                                        STATUS_STYLE.pending;
                                    const expanded = expandedId === order.id;
                                    const itemCount = order.groups.reduce(
                                        (s, g) => s + g.quantity,
                                        0,
                                    );
                                    return (
                                        <div
                                            key={order.id}
                                            className="overflow-hidden rounded-card border border-cardBorder bg-card"
                                        >
                                            <button
                                                onClick={() =>
                                                    setExpandedId(
                                                        expanded
                                                            ? null
                                                            : order.id,
                                                    )
                                                }
                                                aria-expanded={expanded}
                                                className="flex w-full items-center gap-3 px-4 py-3 text-left"
                                            >
                                                <span className="min-w-0 flex-1">
                                                    <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                        <span className="font-mono text-[15px] font-bold tracking-[0.04em] text-grass">
                                                            {order.code}
                                                        </span>
                                                        <span
                                                            className="rounded-pill px-2.5 py-0.5 text-[11px] font-bold"
                                                            style={{
                                                                color: style.color,
                                                                background:
                                                                    style.bg,
                                                            }}
                                                        >
                                                            {STATUS_LABEL[
                                                                order.status
                                                            ] ?? order.status}
                                                        </span>
                                                    </span>
                                                    <span className="mt-0.5 block text-[12.5px] text-moss">
                                                        {order.start_date} →{' '}
                                                        {order.end_date} ·{' '}
                                                        {itemCount} món
                                                    </span>
                                                </span>
                                                <span className="shrink-0 text-right">
                                                    <span className="block font-mono text-[14px] font-bold text-ink">
                                                        {money(
                                                            order.amount_due,
                                                        )}
                                                    </span>
                                                    <span className="block text-[10.5px] text-[#a3ad92]">
                                                        Trả khi nhận
                                                    </span>
                                                </span>
                                                <svg
                                                    width="18"
                                                    height="18"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="#8a967a"
                                                    strokeWidth="2.2"
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    className={`shrink-0 transition-transform ${expanded ? 'rotate-180' : ''}`}
                                                >
                                                    <path d="m6 9 6 6 6-6" />
                                                </svg>
                                            </button>
                                            {expanded && (
                                                <div
                                                    className="border-t border-cardBorder px-4 pb-4 pt-3"
                                                    style={{
                                                        background: '#fafcf7',
                                                    }}
                                                >
                                                    <OrderDetail
                                                        order={order}
                                                        onReorder={() =>
                                                            setReorder({
                                                                order,
                                                                start: null,
                                                                end: null,
                                                            })
                                                        }
                                                        onViewProgress={() =>
                                                            viewProgress(order)
                                                        }
                                                    />
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </>
                    )}
                    {orders.length >= 20 && (
                        <p className="mt-2 text-[12px] text-[#a3ad92]">
                            Hiển thị 20 đơn gần nhất — đơn cũ hơn tra bằng mã
                            đơn ở mục Tra cứu bên dưới.
                        </p>
                    )}
                </section>

                {/* Mã giới thiệu */}
                <section className="mb-[22px] rounded-card border border-cardBorder bg-card p-5">
                    <div className="mb-1.5 text-[11px] font-bold uppercase tracking-[0.05em] text-[#8a967a]">
                        Mã giới thiệu của bạn
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="rounded-[11px] border border-dashed border-grass bg-[#f1f4ea] px-4 py-2.5 font-mono text-[22px] font-bold tracking-[0.18em] text-grass">
                            {referralCode}
                        </span>
                        <button
                            onClick={copyCode}
                            className="h-11 rounded-control bg-grass px-4 text-[14px] font-bold text-white transition hover:bg-pine"
                        >
                            {copied ? 'Đã copy ✓' : 'Copy mã'}
                        </button>
                    </div>

                    {/* Link chia sẻ */}
                    <div className="mt-3">
                        <div className="mb-1.5 text-[11px] font-bold uppercase tracking-[0.05em] text-[#8a967a]">
                            Link chia sẻ
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <input
                                readOnly
                                value={shareLink}
                                onFocus={(e) => e.currentTarget.select()}
                                className="h-11 min-w-0 flex-1 rounded-[11px] border border-cardBorder bg-[#f8faf4] px-3 text-[13px] text-moss outline-none"
                            />
                            <button
                                onClick={copyLink}
                                className="h-11 rounded-control border border-grass px-4 text-[14px] font-bold text-grass transition hover:bg-[#eef2e3]"
                            >
                                {copiedLink ? 'Đã copy ✓' : 'Copy link'}
                            </button>
                        </div>
                    </div>

                    <p className="mt-3 text-[13px] text-moss">
                        Gửi <strong>link</strong> hoặc <strong>mã</strong> cho
                        bạn bè. Bạn ấy mở link sẽ thấy lời mời và được điền sẵn
                        mã; khi nhập mã ở <strong>đơn thuê đầu tiên</strong> và
                        hoàn tất đơn, bạn nhận voucher thưởng — còn bạn ấy được
                        giảm giá ngay đơn đầu.
                    </p>
                </section>

                {/* Voucher */}
                <section className="mb-[22px] rounded-card border border-cardBorder bg-card p-5">
                    <div className="mb-3 flex flex-wrap items-center gap-2">
                        {BUCKET_TABS.map((t) => (
                            <button
                                key={t.key}
                                onClick={() => setTab(t.key)}
                                className={`rounded-pill px-3 py-1.5 text-[13px] font-semibold transition ${
                                    tab === t.key
                                        ? 'bg-grass text-white'
                                        : 'bg-[#eef2e3] text-pine hover:bg-[#e2e8d2]'
                                }`}
                            >
                                {t.label} ({countBy(t.key)})
                            </button>
                        ))}
                    </div>
                    {shown.length === 0 ? (
                        <p className="py-4 text-center text-[14px] text-[#a3ad92]">
                            Chưa có voucher nào ở mục này.
                        </p>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {shown.map((v) => (
                                <li
                                    key={v.code}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-[10px] px-3.5 py-2.5"
                                    style={{
                                        background:
                                            v.bucket === 'active'
                                                ? '#e7ecdc'
                                                : '#f1f2ed',
                                    }}
                                >
                                    <span className="text-[13px] text-pine">
                                        <span className="font-mono font-bold">
                                            {v.code}
                                        </span>
                                        <span className="ml-2 text-moss">
                                            {VOUCHER_SOURCE_LABEL[v.source] ??
                                                VOUCHER_SOURCE_FALLBACK}
                                        </span>
                                        {v.expires_at && (
                                            <span className="ml-2 text-[11px] text-[#a3ad92]">
                                                HSD {v.expires_at}
                                            </span>
                                        )}
                                    </span>
                                    <span
                                        className={`font-mono font-bold ${v.bucket === 'active' ? 'text-grass' : 'text-[#a3ad92] line-through'}`}
                                    >
                                        {voucherValueText(v.type, v.value)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {/* Tra cứu đơn (chuyển từ /tra-cuu vào tài khoản — bopcamping-7w8) */}
                <section id="tra-cuu">
                    <h2 className="mb-1 text-[18px] font-bold text-ink">
                        Tra cứu đơn
                    </h2>
                    <p className="mb-3 text-[14px] text-moss">
                        Xem tiến trình bất kỳ đơn nào bằng mã đơn + số điện
                        thoại đặt đơn (kể cả đơn đặt hộ người khác).
                    </p>
                    <OrderLookupPanel
                        key={`${lookup.query.code}|${lookup.query.phone}`}
                        lookup={lookup}
                        routeName="account"
                        preserveScroll
                    />
                </section>
            </main>

            {/* Modal Đặt lại — chọn lại ngày trước khi thêm vào giỏ */}
            {reorder && (
                <ReorderModal
                    state={reorder}
                    setState={setReorder}
                    onClose={() => setReorder(null)}
                />
            )}
        </>
    );
}

/** Chi tiết đơn (bung dưới hàng bảng): thiết bị + địa chỉ + tiền + thao tác. */
function OrderDetail({
    order,
    onReorder,
    onViewProgress,
}: {
    order: AccountOrder;
    onReorder: () => void;
    onViewProgress: () => void;
}) {
    // Hook gọi ở top-level component (không trong IIFE) — tuân rules-of-hooks.
    const site = (usePage().props as { site?: Parameters<typeof shopHours>[0] })
        .site;
    const sessLabel = sessionLabel(order.session, shopHours(site));

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            {/* Cột trái: thông tin nhận + thiết bị */}
            <div>
                <div className="mb-1 text-[12px] text-moss">
                    Đặt ngày {order.created_at} · {order.days} ngày
                </div>
                {order.address && (
                    <div className="mb-2.5 text-[13px] text-moss">
                        📍 Giao nhận: {order.address}
                    </div>
                )}

                {/* Khoảng thuê + buổi + giờ nhận/trả khách đã chọn (spec 2026-07-26) */}
                <div className="mb-2.5 rounded-[10px] border border-[#eef2e3] bg-white px-3 py-2 text-[12.5px]">
                    <div>
                        <span className="text-moss">Khoảng thuê:</span>{' '}
                        <span className="font-mono text-ink">
                            {order.start_date} → {order.end_date}
                        </span>{' '}
                        <span className="text-moss">({order.days} ngày)</span>
                    </div>
                    {sessLabel && (
                        <div className="mt-0.5">
                            <span className="text-moss">Buổi:</span>{' '}
                            <span className="font-semibold text-grass">
                                {sessLabel}
                            </span>
                        </div>
                    )}
                    {/* Giờ shop đã chốt (spec 2026-07-28) ưu tiên hơn giờ khách mong muốn */}
                    {order.confirmed_pickup_time ||
                    order.confirmed_return_time ? (
                        <div className="mt-0.5">
                            <span className="text-moss">Giờ đã chốt:</span>{' '}
                            <span className="font-mono font-bold text-grass">
                                giao {order.confirmed_pickup_time ?? '—'} · thu{' '}
                                {order.confirmed_return_time ?? '—'}
                            </span>
                        </div>
                    ) : (
                        (order.requested_pickup_time ||
                            order.requested_return_time) && (
                            <div className="mt-0.5">
                                <span className="text-moss">
                                    Giờ (mong muốn):
                                </span>{' '}
                                <span className="font-mono text-ink">
                                    nhận {order.requested_pickup_time ?? '—'} ·
                                    trả {order.requested_return_time ?? '—'}
                                </span>
                            </div>
                        )
                    )}
                </div>

                <div className="mb-2 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                    Thiết bị
                </div>
                <ul className="flex flex-col gap-1.5 rounded-[10px] border border-[#eef2e3] bg-white px-3 py-2.5 text-[14px] text-ink">
                    {order.groups.map((g, i) => (
                        <li key={i}>
                            <div className="flex justify-between gap-2">
                                <span>
                                    {g.kind === 'combo' && (
                                        <span
                                            className="mr-1.5 rounded-pill px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.04em]"
                                            style={{
                                                background: '#efe4d3',
                                                color: '#7f4f24',
                                            }}
                                        >
                                            Combo
                                        </span>
                                    )}
                                    {g.name}{' '}
                                    <span className="text-moss">
                                        ×{g.quantity}
                                    </span>
                                </span>
                                <span className="font-mono font-semibold text-ink">
                                    {money(g.subtotal)}
                                </span>
                            </div>
                            {g.kind === 'combo' &&
                                g.children &&
                                g.children.length > 0 && (
                                    <div className="mt-0.5 pl-[52px] text-[12px] text-[#8a967a]">
                                        Gồm:{' '}
                                        {g.children
                                            .map(
                                                (c) =>
                                                    `${c.name} ×${c.quantity}`,
                                            )
                                            .join(' · ')}
                                    </div>
                                )}
                        </li>
                    ))}
                </ul>

                {order.note && (
                    <p className="mt-3 rounded-[10px] border border-[#eef2e3] bg-white p-3 text-[12.5px] text-moss">
                        <span className="font-semibold text-ink">Ghi chú:</span>{' '}
                        {order.note}
                    </p>
                )}
            </div>

            {/* Cột phải: tiền + thao tác */}
            <div>
                <div className="mb-2 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                    Thanh toán
                </div>
                <div className="rounded-[10px] border border-[#eef2e3] bg-white px-3 py-2.5 text-[13.5px]">
                    <div className="flex justify-between py-[3px]">
                        <span className="text-moss">Phí thuê</span>
                        <span className="font-mono font-semibold text-ink">
                            {money(order.total_price)}
                        </span>
                    </div>
                    {order.discounts.map((d, i) => (
                        <div key={i} className="flex justify-between py-[3px]">
                            <span className="text-moss">{d.label}</span>
                            <span className="font-mono font-semibold text-campfire">
                                {d.amount >= 0
                                    ? `−${money(d.amount)}`
                                    : `+${money(-d.amount)}`}
                            </span>
                        </div>
                    ))}
                    {order.extra_fees?.map((f, i) => (
                        <div key={i} className="flex justify-between py-[3px]">
                            <span className="text-moss">{f.name}</span>
                            <span className="font-mono font-semibold text-ink">
                                +{money(f.value)}
                            </span>
                        </div>
                    ))}
                    {order.deposit_total > 0 && (
                        <div className="flex justify-between py-[3px]">
                            <span className="text-moss">
                                Tiền cọc (hoàn lại)
                            </span>
                            <span className="font-mono font-semibold text-campfire">
                                {money(order.deposit_total)}
                            </span>
                        </div>
                    )}
                    <div
                        className="mt-1 flex justify-between border-t border-dashed pt-1.5"
                        style={{ borderColor: '#e3e8d6' }}
                    >
                        {/* Trừ khoản đã thu — xem chú thích ở OrderLookupPanel (bopcamping-r3fy). */}
                        <span className="font-bold text-ink">
                            {order.outstanding_due < order.amount_due
                                ? 'Còn phải trả'
                                : 'Trả khi nhận (COD)'}
                        </span>
                        <span className="font-mono font-bold text-grass">
                            {money(order.outstanding_due)}
                        </span>
                    </div>
                </div>

                {/* Shop đã nhận khoản nào (bopcamping-pew1) — chỉ khi chuyện tiền còn
                    đang mở; xem SHOW_PAYMENT_STATUS ở OrderLookupPanel (bopcamping-r3fy). */}
                {['pending', 'confirmed', 'renting'].includes(order.status) && (
                    <div className="mt-3">
                        <PaymentStatus
                            rentalDue={order.rental_due}
                            depositTotal={order.deposit_total}
                            rentalReceived={order.rental_received}
                            depositReceived={order.deposit_received}
                        />
                    </div>
                )}

                {/* Chuyển khoản thay cho COD (bopcamping-55rh). Chỉ render khi khối chi tiết
                    đang mở, nên mỗi lúc chỉ tải một ảnh QR chứ không tải cả danh sách. */}
                {order.payment_qr && (
                    <div className="mt-3">
                        <PaymentQr qr={order.payment_qr} />
                    </div>
                )}

                <div className="mt-3 flex flex-wrap gap-2">
                    <button
                        onClick={onViewProgress}
                        className="h-10 rounded-control border border-[#cdd6b6] bg-white px-4 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]"
                    >
                        Xem tiến trình
                    </button>
                    {order.reorder && (
                        <button
                            onClick={onReorder}
                            className="h-10 rounded-control bg-grass px-4 text-[13px] font-bold text-white transition hover:bg-pine"
                        >
                            Đặt lại đơn này
                        </button>
                    )}
                    {/* Đánh giá — chỉ đơn đã trả (bopcamping-bhr) */}
                    {order.review_token &&
                        (order.review_submitted ? (
                            <span className="inline-flex h-10 items-center gap-1.5 rounded-control border border-[#cdd6b6] bg-[#eef2e3] px-4 text-[13px] font-semibold text-grass">
                                ✓ Đã đánh giá
                            </span>
                        ) : (
                            <Link
                                href={`/danh-gia/${order.review_token}`}
                                className="inline-flex h-10 items-center gap-1.5 rounded-control px-4 text-[13px] font-bold text-white transition hover:brightness-105"
                                style={{ background: '#C97B36' }}
                            >
                                ★ Đánh giá chuyến đi
                            </Link>
                        ))}
                </div>
            </div>
        </div>
    );
}

/**
 * Modal Đặt lại: khách chọn lại khoảng ngày (ngày đơn cũ đã qua). Giá/vị trí là bản
 * MỚI NHẤT từ server (payload reorder); trang giỏ còn tự làm tươi + check tồn kho.
 * Nếu giỏ đang có món → cảnh báo sẽ thay toàn bộ ngay trong modal.
 */
function ReorderModal({
    state,
    setState,
    onClose,
}: {
    state: { order: AccountOrder; start: string | null; end: string | null };
    setState: (s: {
        order: AccountOrder;
        start: string | null;
        end: string | null;
    }) => void;
    onClose: () => void;
}) {
    const { order, start, end } = state;
    const r = order.reorder as ReorderPayload;
    const cartHadItems = useMemo(() => getCart().length > 0, []);
    const hours = shopHours(
        (usePage().props as { site?: Parameters<typeof shopHours>[0] }).site,
    );
    const storeOptions = r.store_options ?? [];

    // Cửa hàng khách chọn cho lần đặt lại (mặc định store đầu tiên phục vụ mọi món).
    const [storeId, setStoreId] = useState<number | null>(
        storeOptions[0]?.id ?? null,
    );
    // Buổi khi thuê đúng 1 ngày (feedback 2026-07-27).
    const [session, setSession] = useState<Session>('full');
    // Ngày đã hết theo store — nạp từ server, disable trên lịch.
    const [unavailable, setUnavailable] = useState<Set<string>>(new Set());

    // Nạp ngày bận mỗi khi mở / đổi store (per-store availability).
    useEffect(() => {
        const q = storeId != null ? `?location_id=${storeId}` : '';
        let alive = true;
        fetch(`/tai-khoan/dat-lai/${order.id}/kha-dung${q}`, {
            headers: { Accept: 'application/json' },
        })
            .then((res) => (res.ok ? res.json() : { unavailable: [] }))
            .then((d: { unavailable?: string[] }) => {
                if (alive) setUnavailable(new Set<string>(d.unavailable ?? []));
            })
            .catch(() => {
                if (alive) setUnavailable(new Set());
            });
        return () => {
            alive = false;
        };
    }, [order.id, storeId]);

    const days = start && end ? dayCount(start, end) : 0;
    const isOneDay = !!start && !!end && start === end;
    const canConfirm = !!start && !!end;

    const buildLines = (): CartLine[] => [
        ...r.products.map((p) => ({
            id: p.id,
            name: p.name,
            cat: p.cat,
            grad: gradFor(p.cat),
            price: p.price,
            deposit: p.deposit,
            qty: p.qty,
            start: start as string,
            end: end as string,
            locations: p.locations,
            location_id: storeId,
            early_return_pct: p.early_return_pct,
            session: isOneDay ? session : null,
        })),
        ...r.combos.map((c) => ({
            id: c.id,
            kind: 'combo' as const,
            name: c.name,
            cat: 'combo',
            grad: COMBO_GRAD,
            price: c.price,
            deposit: c.deposit,
            qty: c.qty,
            start: start as string,
            end: end as string,
            locations: c.locations,
            comboItems: c.comboItems,
            location_id: storeId,
        })),
    ];

    const confirm = () => {
        if (!canConfirm) return;
        setCart(buildLines());
        emit(
            EVENTS.toast,
            r.skipped > 0
                ? `Đã thêm lại đơn ${order.code} vào giỏ — ${r.skipped} món không còn cho thuê`
                : `Đã thêm lại đơn ${order.code} vào giỏ`,
        );
        onClose();
        router.visit('/gio-thue');
    };

    const totalItems =
        r.products.reduce((s, p) => s + p.qty, 0) +
        r.combos.reduce((s, c) => s + c.qty, 0);

    return (
        <div
            className="fixed inset-0 z-[200] grid place-items-center overflow-y-auto p-4"
            style={{ background: 'rgba(24,35,15,.45)' }}
        >
            <div className="my-auto w-full max-w-[860px] rounded-[18px] border border-cardBorder bg-white p-5">
                <div className="mb-1 flex items-start justify-between gap-3">
                    <div className="text-[17px] font-bold text-ink">
                        Đặt lại đơn{' '}
                        <span className="font-mono text-grass">
                            {order.code}
                        </span>
                    </div>
                    <button
                        onClick={onClose}
                        aria-label="Đóng"
                        className="shrink-0 rounded-full p-1 text-[#8a967a] hover:bg-[#f1f4ea]"
                    >
                        <svg
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                        >
                            <path d="m6 6 12 12M18 6 6 18" />
                        </svg>
                    </button>
                </div>
                <p className="mb-3 text-[13px] text-moss">
                    Chọn lại ngày thuê cho {totalItems} món. Đơn cũ thuê{' '}
                    {order.days} ngày.
                </p>

                {/* Món sẽ đặt lại */}
                <ul className="mb-3 max-h-[110px] overflow-y-auto rounded-[10px] border border-[#eef2e3] bg-[#fafcf7] px-3 py-2 text-[13px] text-ink">
                    {r.products.map((p) => (
                        <li
                            key={`p-${p.id}`}
                            className="flex justify-between py-0.5"
                        >
                            <span>{p.name}</span>
                            <span className="text-moss">×{p.qty}</span>
                        </li>
                    ))}
                    {r.combos.map((c) => (
                        <li
                            key={`c-${c.id}`}
                            className="flex justify-between py-0.5"
                        >
                            <span>
                                <span
                                    className="mr-1 rounded-pill px-1.5 py-0.5 text-[9.5px] font-bold uppercase"
                                    style={{
                                        background: '#efe4d3',
                                        color: '#7f4f24',
                                    }}
                                >
                                    Combo
                                </span>
                                {c.name}
                            </span>
                            <span className="text-moss">×{c.qty}</span>
                        </li>
                    ))}
                </ul>
                {r.skipped > 0 && (
                    <p
                        className="mb-3 rounded-[8px] px-3 py-2 text-[12.5px]"
                        style={{ background: '#fbf2d8', color: '#9a7a2a' }}
                    >
                        {r.skipped} món trong đơn cũ không còn cho thuê — sẽ
                        không được thêm lại.
                    </p>
                )}

                {/* Chọn cửa hàng (feedback 2026-07-27) — đổi store → lịch cập nhật ngày bận theo store. */}
                {storeOptions.length > 0 && (
                    <div className="mb-3">
                        <div className="mb-1.5 text-[12.5px] font-semibold text-pine">
                            Cửa hàng nhận đồ
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {storeOptions.map((s) => {
                                const on = storeId === s.id;
                                return (
                                    <button
                                        key={s.id}
                                        type="button"
                                        onClick={() => setStoreId(s.id)}
                                        aria-pressed={on}
                                        className={`rounded-[9px] border px-3 py-1.5 text-[12.5px] font-semibold transition ${on ? 'border-grass bg-grass text-white' : 'border-cardBorder text-pine hover:border-grass'}`}
                                    >
                                        {s.name}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* Chọn lại ngày — lịch 2 tháng nằm ngang; ngày đã hết theo store bị disable. */}
                <DateRangeCalendar
                    start={start}
                    end={end}
                    unavailable={unavailable}
                    onChange={(s, e) => setState({ order, start: s, end: e })}
                />
                <div className="mt-2 text-center text-[13px] text-moss">
                    {canConfirm ? (
                        <>
                            Thuê{' '}
                            <strong className="text-ink">
                                {rangeText(start, end)}
                            </strong>{' '}
                            · {days} ngày
                        </>
                    ) : (
                        'Chạm chọn ngày nhận và ngày trả.'
                    )}
                </div>

                {/* Thuê đúng 1 ngày → cho chọn buổi (feedback 2026-07-27). */}
                {isOneDay && (
                    <div className="mt-3 rounded-[12px] border border-cardBorder bg-[#fbfcf8] p-3">
                        <div className="mb-2 text-[12.5px] font-bold text-ink">
                            Chọn buổi thuê
                        </div>
                        <div className="grid grid-cols-3 gap-2">
                            {(
                                [
                                    {
                                        key: 'morning',
                                        label: 'Buổi sáng',
                                        time: `${hours.pickup}h–${hours.morningEnd}h`,
                                    },
                                    {
                                        key: 'afternoon',
                                        label: 'Buổi chiều',
                                        time: `${hours.afternoonStart}h–${hours.close}h`,
                                    },
                                    {
                                        key: 'full',
                                        label: 'Cả ngày',
                                        time: `${hours.pickup}h–${hours.close}h`,
                                    },
                                ] as const
                            ).map((opt) => {
                                const on = session === opt.key;
                                return (
                                    <button
                                        key={opt.key}
                                        type="button"
                                        onClick={() => setSession(opt.key)}
                                        aria-pressed={on}
                                        className={`rounded-[10px] border px-2 py-2 text-center transition ${on ? 'border-grass bg-[#eef5e1] ring-1 ring-grass' : 'border-cardBorder bg-white hover:border-grass'}`}
                                    >
                                        <span className="block text-[12.5px] font-bold text-ink">
                                            {opt.label}
                                        </span>
                                        <span className="block text-[11px] text-moss">
                                            {opt.time}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                {cartHadItems && (
                    <p
                        className="mt-2 rounded-[8px] px-3 py-2 text-[12.5px]"
                        style={{ background: '#f6ede3', color: '#8a5a2a' }}
                    >
                        Giỏ hiện tại đang có món — đặt lại sẽ{' '}
                        <strong>thay toàn bộ giỏ</strong>.
                    </p>
                )}

                <div className="mt-4 flex flex-col gap-2.5 sm:flex-row-reverse">
                    <button
                        onClick={confirm}
                        disabled={!canConfirm}
                        className="flex h-[56px] flex-1 items-center justify-center gap-2 rounded-control text-[17px] font-bold text-white shadow-sm transition disabled:cursor-not-allowed"
                        style={{
                            background: canConfirm ? '#557A2B' : '#c4cfae',
                        }}
                    >
                        <svg
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="1.9"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        >
                            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                            <path d="M3 6h18" />
                            <path d="M16 10a4 4 0 0 1-8 0" />
                        </svg>
                        {cartHadItems
                            ? 'Thay giỏ & thêm vào giỏ'
                            : 'Thêm vào giỏ'}
                    </button>
                    <button
                        onClick={onClose}
                        className="h-[56px] rounded-control border border-[#cdd6b6] bg-white px-6 text-[15px] font-semibold text-pine sm:flex-none"
                    >
                        Huỷ
                    </button>
                </div>
            </div>
        </div>
    );
}

function Stat({
    label,
    value,
    hint,
}: {
    label: string;
    value: number;
    hint: string;
}) {
    return (
        <div className="rounded-card border border-cardBorder bg-card p-4">
            <div className="font-mono text-[28px] font-extrabold leading-none text-grass">
                {value}
            </div>
            <div className="mt-1.5 text-[13px] font-semibold text-ink">
                {label}
            </div>
            <div className="text-[11px] text-[#a3ad92]">{hint}</div>
        </div>
    );
}

Account.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
