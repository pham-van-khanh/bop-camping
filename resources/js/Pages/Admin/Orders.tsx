import { Head, router, usePage } from '@inertiajs/react';
import { Fragment, ReactNode, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import DateRangeCalendar from '@/Components/site/DateRangeCalendar';
import ProductStatusPill from '@/Components/ProductStatusPill';
import { money } from '@/lib/format';
import { STATUS_LABEL, STATUS_STYLE } from '@/lib/orderStatus';
import { voucherValueText, VOUCHER_SOURCE_LABEL, VOUCHER_SOURCE_FALLBACK, type VoucherType } from '@/lib/voucher';

type OrderItem = {
    name: string;
    quantity: number;
    price_per_day: number;
    days: number;
    subtotal: number;
    // % giảm thuê dài ngày đã áp cho dòng (bopcamping-e36e); 0 = không giảm.
    duration_discount_percent: number;
    // bopcamping-d7l: items thuộc combo mang uuid nhóm + giá phân bổ (AC-3)
    combo_group_uuid: string | null;
    combo_name: string | null;
    allocated_price: number | null;
};

// Nhóm items chi tiết đơn: mỗi combo_group_uuid = 1 khối combo, còn lại là dòng lẻ.
type ItemGroup = { key: string; combo: string | null; items: OrderItem[] };

// bopcamping-3ag: nguồn giảm giá từng dòng, lưu lúc checkout (đơn cũ = null).
type DiscountLine = { source: string; amount: number; code?: string; percent?: boolean };

const DISCOUNT_SOURCE_LABEL: Record<string, string> = {
    voucher: 'Voucher',
    referral: 'Mã giới thiệu (đơn đầu)',
    email_bonus: 'Ưu đãi thêm email (đơn đầu)',
    cap: 'Điều chỉnh trần giảm giá',
    // bopcamping-wtuv: phần giảm phân bổ từ voucher tính trên TỔNG đơn gộp (cha)
    parent_alloc: 'Giảm phân bổ từ đơn gộp',
};

function groupItems(items: OrderItem[]): ItemGroup[] {
    const groups: ItemGroup[] = [];
    const byUuid = new Map<string, ItemGroup>();
    items.forEach((it, i) => {
        if (it.combo_group_uuid) {
            let g = byUuid.get(it.combo_group_uuid);
            if (!g) {
                g = { key: it.combo_group_uuid, combo: it.combo_name ?? 'Combo', items: [] };
                byUuid.set(it.combo_group_uuid, g);
                groups.push(g);
            }
            g.items.push(it);
        } else {
            groups.push({ key: `single-${i}`, combo: null, items: [it] });
        }
    });
    return groups;
}
type UsedVoucher = { code: string; type: VoucherType; value: number; source: string };
type Order = {
    id: number; code: string; customer_name: string; customer_phone: string;
    customer_email: string | null; customer_address: string | null;
    start_date: string; end_date: string; days: number;
    // Giờ khách chọn khi thuê 1 ngày (bopcamping-n6mr)
    requested_pickup_time: string | null;
    requested_return_time: string | null;
    // ISO (Y-m-d) cho form đổi lịch (bopcamping-5hjm)
    start_date_iso: string; end_date_iso: string;
    total_price: number; deposit_total: number; discount_total: number; amount_due: number;
    discount_breakdown: DiscountLine[] | null;
    status: string; payment_status: string;
    deposit_refund_status: string; deposit_refund_note: string | null;
    note: string | null; created_at: string; items: OrderItem[];
    vouchers: UsedVoucher[]; referral: { referrer_name: string | null; status: string } | null;
    // Per-store: cửa hàng thuê + đơn hệ thống tự gán (admin review theo địa chỉ)
    service_location: { id: number; name: string } | null;
    location_auto_assigned: boolean;
    // Đơn cha/con (bopcamping-wtuv): cha gom N đợt giao; con nằm trong children của cha.
    is_parent: boolean;
    children?: Order[];
};

// Tình trạng chuyển tiền (marker admin — bopcamping-7be).
const PAYMENT_OPTIONS: { key: string; label: string; active: { bg: string; color: string } }[] = [
    { key: 'unpaid',  label: 'Chưa chuyển',    active: { bg: '#f6ddd6', color: '#b3493a' } },
    { key: 'deposit', label: 'Đã chuyển cọc',  active: { bg: '#fbf2d8', color: '#9a7a2a' } },
    { key: 'full',    label: 'Chuyển hết',     active: { bg: '#dcebc4', color: '#3a5a1f' } },
];

// Hoàn cọc — chỉ dùng khi đơn ĐÃ TRẢ (bopcamping-7be).
const REFUND_OPTIONS: { key: string; label: string; active: { bg: string; color: string } }[] = [
    { key: 'pending',  label: 'Chưa hoàn cọc', active: { bg: '#f6ddd6', color: '#b3493a' } },
    { key: 'refunded', label: 'Đã hoàn cọc',   active: { bg: '#dcebc4', color: '#3a5a1f' } },
];
type Stats = { total: number; pending: number; confirmed: number; renting: number; returned: number; cancelled: number };
type InventoryItem = { id: number; name: string; category: string; quantity: number; status: string };

const NEXT_STATUSES: Record<string, string[]> = {
    pending:   ['confirmed', 'cancelled'],
    confirmed: ['renting', 'cancelled'],
    renting:   ['returned'],
    returned:  [],
    cancelled: [],
};

const TABS = [
    { key: 'all', label: 'Tất cả' },
    { key: 'pending', label: 'Chờ xác nhận' },
    { key: 'confirmed', label: 'Đã xác nhận' },
    { key: 'renting', label: 'Đang thuê' },
    { key: 'returned', label: 'Đã trả' },
    { key: 'cancelled', label: 'Đã huỷ' },
];

export default function AdminOrders({
    orders, stats, inventory, service_locations, filters, max_discount_percent,
}: {
    orders: Order[]; stats: Stats; inventory: InventoryItem[];
    service_locations: { id: number; name: string }[];
    filters: { status: string; q: string };
    max_discount_percent: number;
}) {
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [tab, setTab] = useState<'orders' | 'inventory'>('orders');
    // Đơn cha đang mở danh sách con (bopcamping-wtuv T6) — bấm dòng cha để show/ẩn con.
    const [openParents, setOpenParents] = useState<Record<number, boolean>>({});
    const [search, setSearch] = useState(filters.q ?? '');

    const changeStatus = (order: Order, status: string) => {
        router.patch(route('admin.orders.update', order.id), { status }, { preserveScroll: true });
    };

    const changePayment = (order: Order, payment_status: string) => {
        if (order.payment_status === payment_status) return;
        router.patch(route('admin.orders.payment', order.id), { payment_status }, { preserveScroll: true });
    };

    const filterTab = (status: string) => {
        router.get(route('admin.orders'), {
            ...(status === 'all' ? {} : { status }),
            ...(filters.q ? { q: filters.q } : {}),
        }, { preserveState: true, replace: true });
    };

    // Search theo mã đơn (cả mã con) / tên khách / SĐT — server lọc (bopcamping-wtuv T6).
    const submitSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('admin.orders'), {
            ...(filters.status !== 'all' ? { status: filters.status } : {}),
            ...(search.trim() ? { q: search.trim() } : {}),
        }, { preserveState: true, replace: true });
    };

    // Danh sách phẳng để render: cha đang mở → chèn các con NGAY DƯỚI (thụt vào).
    const visibleRows: { order: Order; isChild: boolean }[] = orders.flatMap((o) =>
        o.is_parent && openParents[o.id] && o.children?.length
            ? [{ order: o, isChild: false }, ...o.children.map((c) => ({ order: c, isChild: true }))]
            : [{ order: o, isChild: false }],
    );

    return (
        <>
            <Head title="Quản trị · Đơn thuê" />
            <div className="p-6">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-[22px] font-extrabold text-pine">Quản trị đơn thuê</h1>
                        <p className="mt-0.5 text-[13px] text-moss">Theo dõi và cập nhật trạng thái đơn hàng</p>
                    </div>
                </div>

                {/* Stats */}
                <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {[
                        { label: 'Tổng đơn', value: stats.total, color: '#18230F' },
                        { label: 'Chờ xác nhận', value: stats.pending, color: '#9a7a2a' },
                        { label: 'Đã xác nhận', value: stats.confirmed, color: '#2a6ea0' },
                        { label: 'Đang thuê', value: stats.renting, color: '#3a5a1f' },
                        { label: 'Đã trả', value: stats.returned, color: '#5C6E47' },
                        { label: 'Đã huỷ', value: stats.cancelled, color: '#b3493a' },
                    ].map((s) => (
                        <div key={s.label} className="rounded-[14px] border border-cardBorder bg-white p-4">
                            <div className="font-mono text-[24px] font-bold" style={{ color: s.color }}>{s.value}</div>
                            <div className="mt-0.5 text-[12px] text-moss">{s.label}</div>
                        </div>
                    ))}
                </div>

                {/* Tab switcher */}
                <div className="mb-4 flex gap-1 rounded-[12px] border border-cardBorder bg-white p-1 w-fit">
                    <button onClick={() => setTab('orders')}
                        className={`rounded-[9px] px-4 py-2 text-[13px] font-semibold transition ${tab === 'orders' ? 'bg-grass text-white' : 'text-pine hover:bg-[#f1f4ea]'}`}>
                        Đơn thuê
                    </button>
                    <button onClick={() => setTab('inventory')}
                        className={`rounded-[9px] px-4 py-2 text-[13px] font-semibold transition ${tab === 'inventory' ? 'bg-grass text-white' : 'text-pine hover:bg-[#f1f4ea]'}`}>
                        Kho thiết bị
                    </button>
                </div>

                {tab === 'orders' && (
                    <>
                        {/* Status filter + search */}
                        <div className="mb-4 flex flex-wrap items-center gap-2">
                            {TABS.map((t) => (
                                <button key={t.key} onClick={() => filterTab(t.key)}
                                    className={`rounded-pill border px-3.5 py-1.5 text-[12.5px] font-semibold transition ${
                                        filters.status === t.key
                                            ? 'border-grass bg-grass text-white'
                                            : 'border-cardBorder bg-white text-pine hover:border-grass'
                                    }`}>
                                    {t.label}
                                </button>
                            ))}
                            {/* Search mã đơn / tên / SĐT (bopcamping-wtuv T6) */}
                            <form onSubmit={submitSearch} className="ml-auto flex items-center gap-1.5">
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Tìm mã đơn, tên, SĐT…"
                                    className="h-9 w-[220px] rounded-[10px] border border-cardBorder bg-white px-3 text-[12.5px] text-ink outline-none focus:border-grass"
                                />
                                <button type="submit" className="h-9 rounded-[10px] border border-cardBorder bg-white px-3 text-[12.5px] font-semibold text-pine hover:border-grass">Tìm</button>
                                {filters.q && (
                                    <button type="button" onClick={() => { setSearch(''); router.get(route('admin.orders'), filters.status !== 'all' ? { status: filters.status } : {}, { preserveState: true, replace: true }); }}
                                        className="h-9 rounded-[10px] px-2 text-[12.5px] text-[#8a967a] hover:text-[#b3493a]">✕</button>
                                )}
                            </form>
                        </div>

                        {/* Orders table */}
                        {orders.length === 0 ? (
                            <div className="rounded-[16px] border border-cardBorder bg-white py-14 text-center text-moss">
                                Không có đơn nào
                            </div>
                        ) : (
                            <div className="overflow-hidden rounded-[16px] border border-cardBorder bg-white">
                                <table className="w-full text-[13px]">
                                    <thead>
                                        <tr className="border-b border-[#eef2e3]" style={{ background: '#f8faf4' }}>
                                            <th className="px-4 py-3 text-left font-semibold text-moss">Mã đơn</th>
                                            <th className="px-4 py-3 text-left font-semibold text-moss">Khách</th>
                                            <th className="px-4 py-3 text-left font-semibold text-moss">Ngày thuê</th>
                                            <th className="px-4 py-3 text-right font-semibold text-moss">Tổng tiền</th>
                                            <th className="px-4 py-3 text-left font-semibold text-moss">Trạng thái</th>
                                            <th className="px-4 py-3 text-left font-semibold text-moss">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {visibleRows.map(({ order, isChild }) => {
                                            const style = STATUS_STYLE[order.status] ?? STATUS_STYLE.pending;
                                            const nexts = NEXT_STATUSES[order.status] ?? [];
                                            const isExpanded = expandedId === order.id;

                                            // Dòng ĐƠN CHA (bopcamping-wtuv T6): highlight + bấm để show/ẩn các đơn con.
                                            // Cha là "bao" gom đợt — không có nút đổi trạng thái (vòng đời nằm ở con).
                                            if (order.is_parent) {
                                                const open = !!openParents[order.id];
                                                return (
                                                    <tr key={order.id}
                                                        className="cursor-pointer border-b border-[#e3ecd2]"
                                                        style={{ background: '#eef5e0' }}
                                                        onClick={() => setOpenParents((m) => ({ ...m, [order.id]: !open }))}>
                                                        <td className="px-4 py-3">
                                                            <span className="mr-1.5 inline-block w-3 text-center font-bold text-grass">{open ? '▾' : '▸'}</span>
                                                            <span className="font-mono font-bold text-pine">{order.code}</span>
                                                            <span className="ml-1.5 rounded-pill bg-grass px-2 py-0.5 text-[10px] font-bold text-white">GỒM {order.children?.length ?? 0} ĐỢT</span>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div className="font-semibold text-ink">{order.customer_name}</div>
                                                            <div className="font-mono text-[11px] text-moss">{order.customer_phone}</div>
                                                        </td>
                                                        <td className="px-4 py-3 font-mono text-[12px] text-pine">{order.start_date} → {order.end_date}</td>
                                                        <td className="px-4 py-3 text-right">
                                                            <div className="font-mono font-bold text-ink">{money(order.total_price)}</div>
                                                            <div className="font-mono text-[11px] text-campfire">cọc {money(order.deposit_total)}</div>
                                                            {order.discount_total > 0 && (
                                                                <div className="font-mono text-[11px] text-grass">giảm −{money(order.discount_total)} <span className="font-sans">(voucher trên tổng)</span></div>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <span className="rounded-pill px-2.5 py-1 text-[11.5px] font-bold" style={{ color: style.color, background: style.bg }}>
                                                                {STATUS_LABEL[order.status]}
                                                            </span>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div className="flex flex-wrap gap-1.5">
                                                                <button onClick={(e) => { e.stopPropagation(); setOpenParents((m) => ({ ...m, [order.id]: !open })); }}
                                                                    className="rounded-[8px] border border-grass px-2.5 py-1 text-[11px] font-semibold text-grass transition hover:bg-grass hover:text-white">
                                                                    {open ? 'Thu gọn' : `Xem ${order.children?.length ?? 0} đợt`}
                                                                </button>
                                                                {/* Cha chỉ có thao tác HUỶ CẢ CỤM — vòng đời giao/thu nằm ở từng con (T7). */}
                                                                {['pending', 'confirmed'].includes(order.status) && (
                                                                    <button onClick={(e) => {
                                                                        e.stopPropagation();
                                                                        if (window.confirm(`Huỷ CẢ ${order.children?.length ?? 0} đợt của đơn ${order.code}?`)) changeStatus(order, 'cancelled');
                                                                    }}
                                                                        className="rounded-[8px] border border-cardBorder px-2.5 py-1 text-[11px] font-semibold text-[#b3493a] transition hover:border-[#b3493a]">
                                                                        Huỷ cả cụm
                                                                    </button>
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                );
                                            }

                                            return (
                                                <Fragment key={`${order.id}${isChild ? '-child' : ''}`}>
                                                    <tr
                                                        className={`cursor-pointer border-b border-[#f1f4ea] hover:bg-[#fafcf7] ${isChild ? 'bg-[#fbfdf6]' : ''}`}
                                                        onClick={() => setExpandedId(isExpanded ? null : order.id)}>
                                                        <td className={`px-4 py-3 font-mono font-bold text-pine ${isChild ? 'pl-9' : ''}`}>
                                                            {isChild && <span className="mr-1.5 text-[#a9b58f]">└</span>}{order.code}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div className="font-semibold text-ink">{order.customer_name}</div>
                                                            <div className="font-mono text-[11px] text-moss">{order.customer_phone}</div>
                                                        </td>
                                                        <td className="px-4 py-3 font-mono text-[12px] text-pine">
                                                            {order.start_date} → {order.end_date}
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            <div className="font-mono font-bold text-ink">{money(order.total_price)}</div>
                                                            <div className="font-mono text-[11px] text-campfire">cọc {money(order.deposit_total)}</div>
                                                            {order.discount_total > 0 && (
                                                                /* bopcamping-3ag: không ghi cứng "voucher" — giảm có thể từ email bonus/referral */
                                                                <div className="font-mono text-[11px] text-grass">giảm −{money(order.discount_total)}</div>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <span className="rounded-pill px-2.5 py-1 text-[11.5px] font-bold"
                                                                style={{ color: style.color, background: style.bg }}>
                                                                {STATUS_LABEL[order.status]}
                                                            </span>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div className="flex flex-wrap gap-1.5">
                                                                {nexts.map((s) => (
                                                                    <button key={s}
                                                                        onClick={(e) => { e.stopPropagation(); changeStatus(order, s); }}
                                                                        className="rounded-[8px] border border-cardBorder px-2.5 py-1 text-[11px] font-semibold text-pine transition hover:border-grass hover:text-grass">
                                                                        → {STATUS_LABEL[s]}
                                                                    </button>
                                                                ))}
                                                                {nexts.length === 0 && (
                                                                    <span className="text-[11px] text-[#b0ba98]">-</span>
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    {isExpanded && (
                                                        <tr key={`${order.id}-detail`} className="border-b border-[#f1f4ea]">
                                                            <td colSpan={6} className="px-6 pb-5 pt-2" style={{ background: '#fafcf7' }}>
                                                                <div className="grid gap-4 lg:grid-cols-2">
                                                                    {/* Cột trái: khách + thiết bị */}
                                                                    <div>
                                                                        <div className="mb-2 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">Khách hàng</div>
                                                                        <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3 text-[12.5px]">
                                                                            <DetailRow label="Họ tên" value={order.customer_name} />
                                                                            <DetailRow label="SĐT" value={order.customer_phone} mono />
                                                                            <DetailRow label="Email" value={order.customer_email ?? '—'} mono />
                                                                            <DetailRow label="Địa chỉ" value={order.customer_address ?? '—'} />
                                                                            <DetailRow label="Khoảng thuê" value={`${order.start_date} → ${order.end_date} (${order.days} ngày)`} />
                                                                            {(order.requested_pickup_time || order.requested_return_time) && (
                                                                                <DetailRow label="Giờ khách chọn" value={`nhận ${order.requested_pickup_time ?? '—'} · trả ${order.requested_return_time ?? '—'}`} mono />
                                                                            )}
                                                                            <DetailRow label="Đặt lúc" value={order.created_at} />
                                                                        </div>

                                                                        {/* Per-store: cửa hàng thuê + đổi store */}
                                                                        <div className="mb-2 mt-3 flex items-center gap-2 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                                                                            Cơ sở giao
                                                                            {order.location_auto_assigned && (
                                                                                <span className="rounded-pill bg-[#f7e7da] px-2 py-0.5 text-[10px] font-semibold normal-case text-[#8a5a1f]">Hệ thống gán · cần duyệt</span>
                                                                            )}
                                                                            {order.service_location && !order.location_auto_assigned && (
                                                                                <span className="rounded-pill bg-[#eef5e1] px-2 py-0.5 text-[10px] font-semibold normal-case text-grass">Khách chọn</span>
                                                                            )}
                                                                        </div>
                                                                        <StoreChanger order={order} locations={service_locations} />

                                                                        {/* Đổi lịch — chỉ đơn chưa giao (bopcamping-5hjm) */}
                                                                        {['pending', 'confirmed'].includes(order.status) && (
                                                                            <>
                                                                                <div className="mb-2 mt-3 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">Đổi lịch thuê</div>
                                                                                <DatesChanger order={order} maxDiscountPercent={max_discount_percent} />
                                                                            </>
                                                                        )}

                                                                        <div className="mb-2 mt-3 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">Thiết bị</div>
                                                                        <div className="overflow-hidden rounded-[10px] border border-[#eef2e3]">
                                                                            <table className="w-full text-[12px]">
                                                                                <thead>
                                                                                    <tr style={{ background: '#f1f4ea' }}>
                                                                                        <th className="px-3 py-2 text-left font-semibold text-moss">Thiết bị</th>
                                                                                        <th className="px-3 py-2 text-center font-semibold text-moss">SL × ngày</th>
                                                                                        <th className="px-3 py-2 text-right font-semibold text-moss">Thành tiền</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    {groupItems(order.items).map((g) => g.combo === null ? (
                                                                                        <tr key={g.key} className="border-t border-[#eef2e3]">
                                                                                            <td className="px-3 py-2 text-ink">{g.items[0].name}<div className="text-[11px] text-moss">{money(g.items[0].price_per_day)}/ngày</div></td>
                                                                                            <td className="px-3 py-2 text-center text-moss">{g.items[0].quantity} × {g.items[0].days}</td>
                                                                                            <td className="px-3 py-2 text-right">
                                                                                                <div className="font-mono font-bold text-ink">{money(g.items[0].subtotal)}</div>
                                                                                                {g.items[0].duration_discount_percent > 0 && (
                                                                                                    <div className="text-[10.5px]">
                                                                                                        <span className="font-mono text-[#8a967a] line-through">{money(g.items[0].price_per_day * g.items[0].quantity * g.items[0].days)}</span>
                                                                                                        <span className="ml-1 font-bold text-[#3a5a1f]">−{g.items[0].duration_discount_percent}% thuê dài ngày</span>
                                                                                                    </div>
                                                                                                )}
                                                                                            </td>
                                                                                        </tr>
                                                                                    ) : (
                                                                                        /* bopcamping-d7l: khối combo — header + các món con với giá phân bổ */
                                                                                        <Fragment key={g.key}>
                                                                                            <tr className="border-t border-[#eef2e3]" style={{ background: '#f3f7ec' }}>
                                                                                                <td className="px-3 py-2" colSpan={2}>
                                                                                                    <span className="mr-1.5 rounded-pill bg-grass px-1.5 py-0.5 font-mono text-[9.5px] font-bold text-white">COMBO</span>
                                                                                                    <span className="font-bold text-pine">{g.combo}</span>
                                                                                                    <div className="mt-0.5 text-[11px] text-moss">{g.items.length} món · tổng giá phân bổ = giá combo</div>
                                                                                                </td>
                                                                                                <td className="px-3 py-2 text-right">
                                                                                                    <div className="font-mono font-bold text-pine">{money(g.items.reduce((s, it) => s + it.subtotal, 0))}</div>
                                                                                                    {g.items[0].duration_discount_percent > 0 && (
                                                                                                        <div className="text-[10.5px]">
                                                                                                            <span className="font-mono text-[#8a967a] line-through">{money(g.items.reduce((s, it) => s + (it.allocated_price ?? it.price_per_day) * it.days, 0))}</span>
                                                                                                            <span className="ml-1 font-bold text-[#3a5a1f]">−{g.items[0].duration_discount_percent}% thuê dài ngày</span>
                                                                                                        </div>
                                                                                                    )}
                                                                                                </td>
                                                                                            </tr>
                                                                                            {g.items.map((item, i) => (
                                                                                                <tr key={i} className="border-t border-[#f3f7ec]">
                                                                                                    <td className="py-1.5 pl-7 pr-3 text-ink">
                                                                                                        {item.name}
                                                                                                        <div className="text-[11px] text-moss">
                                                                                                            phân bổ {money(item.allocated_price ?? item.price_per_day)}/ngày · giá lẻ {money(item.price_per_day)}/ngày
                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td className="px-3 py-1.5 text-center text-moss">{item.quantity} × {item.days}</td>
                                                                                                    <td className="px-3 py-1.5 text-right font-mono text-ink">{money(item.subtotal)}</td>
                                                                                                </tr>
                                                                                            ))}
                                                                                        </Fragment>
                                                                                    ))}
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    </div>

                                                                    {/* Cột phải: tiền + ưu đãi + ghi chú */}
                                                                    <div>
                                                                        <div className="mb-2 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">Thanh toán</div>
                                                                        <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3 text-[12.5px]">
                                                                            {(() => {
                                                                                // Tiền thuê hiện là NET (đã trừ giảm thuê dài ngày). Nếu có giảm → hiện giá gốc + số đã giảm.
                                                                                const grossRental = order.items.reduce((s, it) => s + (it.combo_group_uuid ? (it.allocated_price ?? it.price_per_day) : it.price_per_day * it.quantity) * it.days, 0);
                                                                                const durSaved = grossRental - order.total_price;
                                                                                return (
                                                                                    <div className="flex items-start justify-between gap-3 py-0.5">
                                                                                        <span className="shrink-0 text-moss">Tiền thuê</span>
                                                                                        <span className="text-right">
                                                                                            {durSaved > 0 && <span className="mr-1.5 font-mono text-[11px] text-[#8a967a] line-through">{money(grossRental)}</span>}
                                                                                            <span className="font-mono text-ink">{money(order.total_price)}</span>
                                                                                            {durSaved > 0 && <div className="text-[10.5px] text-[#3a5a1f]">đã giảm thuê dài ngày −{money(durSaved)}</div>}
                                                                                        </span>
                                                                                    </div>
                                                                                );
                                                                            })()}
                                                                            {order.discount_total > 0 && <DetailRow label="Giảm giá" value={`−${money(order.discount_total)}`} mono accent="#3a5a1f" />}
                                                                            <DetailRow label="Tiền cọc" value={money(order.deposit_total)} mono />
                                                                            <div className="mt-1 flex items-center justify-between border-t border-[#eef2e3] pt-2">
                                                                                <span className="font-bold text-ink">Trả khi nhận</span>
                                                                                <span className="font-mono text-[14px] font-extrabold text-pine">{money(order.amount_due)}</span>
                                                                            </div>
                                                                        </div>

                                                                        <div className="mb-2 mt-3 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">Ưu đãi đã dùng</div>
                                                                        <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3 text-[12.5px]">
                                                                            {/* bopcamping-3ag: nguồn giảm từng dòng với số tiền THỰC áp */}
                                                                            {(order.discount_breakdown ?? []).map((d, i) => (
                                                                                <div key={i} className="flex items-center justify-between gap-2 py-0.5">
                                                                                    <span className="text-ink">
                                                                                        {DISCOUNT_SOURCE_LABEL[d.source] ?? d.source}
                                                                                        {d.code && <span className="ml-1 font-mono font-semibold text-pine">{d.code}</span>}
                                                                                    </span>
                                                                                    <span className="font-mono font-bold" style={{ color: d.amount >= 0 ? '#3a5a1f' : '#b3493a' }}>
                                                                                        {d.amount >= 0 ? `−${money(d.amount)}` : `+${money(-d.amount)}`}
                                                                                    </span>
                                                                                </div>
                                                                            ))}
                                                                            {/* Đơn cũ (trước khi lưu breakdown): chỉ có tổng, không rõ nguồn */}
                                                                            {!order.discount_breakdown?.length && order.discount_total > 0 && (
                                                                                <div className="flex items-center justify-between py-0.5">
                                                                                    <span className="text-moss">Giảm giá (đơn cũ — không có chi tiết nguồn)</span>
                                                                                    <span className="font-mono font-bold text-grass">−{money(order.discount_total)}</span>
                                                                                </div>
                                                                            )}
                                                                            {/* Voucher gắn đơn nhưng chưa có breakdown (đơn cũ) → hiện giá trị danh nghĩa */}
                                                                            {!order.discount_breakdown?.length && order.vouchers.map((v) => (
                                                                                <div key={v.code} className="flex items-center justify-between py-0.5">
                                                                                    <span className="font-mono font-semibold text-ink">{v.code}</span>
                                                                                    <span className="text-moss">{VOUCHER_SOURCE_LABEL[v.source] ?? VOUCHER_SOURCE_FALLBACK} · <strong className="text-grass">{voucherValueText(v.type, v.value)}</strong></span>
                                                                                </div>
                                                                            ))}
                                                                            {order.referral && (
                                                                                <div className="flex items-center justify-between py-0.5">
                                                                                    <span className="text-ink">🎁 Mã giới thiệu</span>
                                                                                    <span className="text-moss">từ <strong>{order.referral.referrer_name ?? '—'}</strong></span>
                                                                                </div>
                                                                            )}
                                                                            {!order.discount_breakdown?.length && order.vouchers.length === 0 && !order.referral && order.discount_total === 0 && (
                                                                                <span className="text-moss">Không có</span>
                                                                            )}
                                                                        </div>

                                                                        {/* Tình trạng chuyển tiền — admin bấm sau khi xác nhận với khách (bopcamping-7be).
                                                                            Đơn đã trả → khoá (chuyển sang theo dõi hoàn cọc bên dưới). */}
                                                                        {(() => {
                                                                            const isReturned = order.status === 'returned';
                                                                            return (
                                                                                <>
                                                                                    <div className="mb-2 mt-3 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">Tình trạng chuyển tiền</div>
                                                                                    <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3">
                                                                                        <div className="grid grid-cols-3 gap-2">
                                                                                            {PAYMENT_OPTIONS.map((opt) => {
                                                                                                const active = (order.payment_status ?? 'unpaid') === opt.key;
                                                                                                return (
                                                                                                    <button
                                                                                                        key={opt.key}
                                                                                                        disabled={isReturned}
                                                                                                        onClick={(e) => { e.stopPropagation(); changePayment(order, opt.key); }}
                                                                                                        aria-pressed={active}
                                                                                                        className={`rounded-[9px] border px-2 py-2 text-[12px] font-bold transition ${isReturned ? 'cursor-not-allowed opacity-70' : ''}`}
                                                                                                        style={active
                                                                                                            ? { background: opt.active.bg, color: opt.active.color, borderColor: opt.active.color }
                                                                                                            : { background: '#fff', color: '#8a967a', borderColor: '#e3e8d6' }}
                                                                                                    >
                                                                                                        {active && '✓ '}{opt.label}
                                                                                                    </button>
                                                                                                );
                                                                                            })}
                                                                                        </div>
                                                                                        <p className="mt-2 text-[11.5px] text-[#a3ad92]">
                                                                                            {isReturned
                                                                                                ? 'Đơn đã trả — tình trạng chuyển tiền đã chốt, xem hoàn cọc bên dưới.'
                                                                                                : <>Bấm để đánh dấu sau khi xác nhận với khách. Cọc {money(order.deposit_total)} · tổng thu {money(order.amount_due)}.</>}
                                                                                        </p>
                                                                                    </div>

                                                                                    {/* Hoàn cọc — chỉ khi đơn đã trả */}
                                                                                    {isReturned && <RefundControl order={order} />}
                                                                                </>
                                                                            );
                                                                        })()}

                                                                        {order.note && (
                                                                            <p className="mt-3 rounded-[10px] border border-[#eef2e3] bg-white p-3 text-[12.5px] text-moss">
                                                                                <span className="font-semibold text-ink">Ghi chú:</span> {order.note}
                                                                            </p>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    )}
                                                </Fragment>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </>
                )}

                {tab === 'inventory' && (
                    <div className="overflow-hidden rounded-[16px] border border-cardBorder bg-white">
                        <table className="w-full text-[13px]">
                            <thead>
                                <tr className="border-b border-[#eef2e3]" style={{ background: '#f8faf4' }}>
                                    <th className="px-4 py-3 text-left font-semibold text-moss">Thiết bị</th>
                                    <th className="px-4 py-3 text-left font-semibold text-moss">Danh mục</th>
                                    <th className="px-4 py-3 text-center font-semibold text-moss">Tổng kho</th>
                                    <th className="px-4 py-3 text-center font-semibold text-moss">Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                {inventory.map((item) => (
                                    <tr key={item.id} className="border-b border-[#f1f4ea] hover:bg-[#fafcf7]">
                                        <td className="px-4 py-3 font-semibold text-ink">{item.name}</td>
                                        <td className="px-4 py-3 text-moss">{item.category}</td>
                                        <td className="px-4 py-3 text-center font-mono font-bold text-pine">{item.quantity}</td>
                                        <td className="px-4 py-3 text-center">
                                            <ProductStatusPill
                                                status={item.status}
                                                label={item.status === 'active' ? 'Đang cho thuê' : 'Đã ẩn'}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

/**
 * Hoàn cọc cho đơn ĐÃ TRẢ (bopcamping-7be): chọn Đã/Chưa hoàn + ghi lý do trừ cọc
 * (rách lều, hư hại…). Lưu cả trạng thái lẫn ghi chú trong 1 lần bấm "Lưu".
 */
function RefundControl({ order }: { order: Order }) {
    const [status, setStatus] = useState(order.deposit_refund_status ?? 'pending');
    const [note, setNote] = useState(order.deposit_refund_note ?? '');
    const [saving, setSaving] = useState(false);

    const dirty = status !== (order.deposit_refund_status ?? 'pending') || note !== (order.deposit_refund_note ?? '');

    const save = () => {
        router.patch(
            route('admin.orders.refund', order.id),
            { deposit_refund_status: status, deposit_refund_note: note },
            { preserveScroll: true, onStart: () => setSaving(true), onFinish: () => setSaving(false) },
        );
    };

    return (
        <>
            <div className="mb-2 mt-3 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">Hoàn cọc</div>
            <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3">
                <div className="grid grid-cols-2 gap-2">
                    {REFUND_OPTIONS.map((opt) => {
                        const active = status === opt.key;
                        return (
                            <button
                                key={opt.key}
                                onClick={(e) => { e.stopPropagation(); setStatus(opt.key); }}
                                aria-pressed={active}
                                className="rounded-[9px] border px-2 py-2 text-[12px] font-bold transition"
                                style={active
                                    ? { background: opt.active.bg, color: opt.active.color, borderColor: opt.active.color }
                                    : { background: '#fff', color: '#8a967a', borderColor: '#e3e8d6' }}
                            >
                                {active && '✓ '}{opt.label}
                            </button>
                        );
                    })}
                </div>
                <textarea
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    onClick={(e) => e.stopPropagation()}
                    rows={2}
                    maxLength={500}
                    placeholder="Lý do trừ cọc nếu có: rách lều, hư hại thiết bị…"
                    className="mt-2 w-full resize-y rounded-[9px] border border-[#e3e8d6] bg-[#fafcf7] px-2.5 py-2 text-[12.5px] text-ink outline-none focus:border-grass"
                />
                <div className="mt-2 flex items-center justify-between gap-2">
                    <span className="text-[11.5px] text-[#a3ad92]">Cọc {money(order.deposit_total)} — trả lại khách sau khi kiểm thiết bị.</span>
                    <button
                        onClick={(e) => { e.stopPropagation(); if (dirty && !saving) save(); }}
                        disabled={!dirty || saving}
                        className="shrink-0 rounded-[9px] px-3.5 py-1.5 text-[12px] font-bold text-white transition disabled:cursor-not-allowed"
                        style={{ background: dirty && !saving ? '#557A2B' : '#c4cfae' }}
                    >
                        {saving ? 'Đang lưu…' : 'Lưu'}
                    </button>
                </div>
            </div>
        </>
    );
}

function DetailRow({ label, value, mono, accent }: { label: string; value: string; mono?: boolean; accent?: string }) {
    return (
        <div className="flex items-start justify-between gap-3 py-0.5">
            <span className="shrink-0 text-moss">{label}</span>
            <span className={`text-right text-ink ${mono ? 'font-mono' : ''}`} style={accent ? { color: accent } : undefined}>{value}</span>
        </div>
    );
}

/** Không có dữ liệu ngày bận cho admin (server kiểm tồn khi lưu) — Set rỗng dùng chung. */
const NO_UNAVAILABLE = new Set<string>();

/**
 * Đổi lịch thuê (bopcamping-5hjm) — chỉ đơn pending/confirmed. Nút mở modal lịch
 * 2 tháng (DateRangeCalendar, pattern modal "Đặt lại" ở trang tài khoản). Preview
 * số ngày + tiền thuê mới tính client-side (scale tuyến tính subtotal); server là
 * source of truth (kiểm tồn kho khoảng mới + tính lại tiền + mail báo khách).
 */
function DatesChanger({ order, maxDiscountPercent }: { order: Order; maxDiscountPercent: number }) {
    const errors = usePage().props.errors as Record<string, string>;
    const [open, setOpen] = useState(false);
    const [start, setStart] = useState<string | null>(order.start_date_iso);
    const [end, setEnd] = useState<string | null>(order.end_date_iso);
    const [saving, setSaving] = useState(false);

    const msPerDay = 86_400_000;
    const newDays = start && end ? Math.round((Date.parse(end) - Date.parse(start)) / msPerDay) + 1 : 0;
    // Preview khớp CHÍNH XÁC server (changeDates + rescaleDiscount): dùng order.days làm mốc cũ
    // cho MỌI dòng, scale tuyến tính, rồi áp lại trần % + kẹp theo tổng (van an toàn).
    const oldDays = Math.max(1, order.days);
    const newTotal = newDays > 0
        ? order.items.reduce((sum, it) => sum + Math.round((it.subtotal * newDays) / oldDays), 0)
        : 0;
    // Giảm giá mới: bỏ dòng cap cũ, scale dòng %, giữ dòng cố định, rồi kẹp min(preCap, trần%, tổng).
    let newDiscount = order.discount_total;
    if (newDays > 0) {
        if (order.discount_breakdown?.length) {
            const preCap = order.discount_breakdown
                .filter((d) => d.source !== 'cap')
                .reduce((sum, d) => sum + (d.percent ? Math.round((d.amount * newDays) / oldDays) : d.amount), 0);
            newDiscount = Math.max(0, Math.min(preCap, Math.floor((newTotal * maxDiscountPercent) / 100), newTotal));
        } else {
            newDiscount = Math.max(0, Math.min(order.discount_total, Math.floor((newTotal * maxDiscountPercent) / 100), newTotal));
        }
    }
    const newAmountDue = newTotal + order.deposit_total - newDiscount;
    const dirty = start !== order.start_date_iso || end !== order.end_date_iso;
    const canSave = !!start && !!end && dirty;
    const fmt = (iso: string) => iso.split('-').reverse().join('/');

    const openModal = () => {
        // Reset về lịch hiện tại của đơn mỗi lần mở (bỏ lựa chọn dở lần trước).
        setStart(order.start_date_iso);
        setEnd(order.end_date_iso);
        setOpen(true);
    };

    const save = () => {
        setSaving(true);
        router.patch(
            route('admin.orders.dates', order.id),
            { start_date: start, end_date: end },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-[12.5px] text-ink">
                    <span className="font-mono font-semibold">{order.start_date} → {order.end_date}</span>
                    <span className="ml-1 text-moss">({order.days} ngày)</span>
                </span>
                <button
                    onClick={openModal}
                    className="flex items-center gap-1.5 rounded-[9px] border border-cardBorder bg-white px-3 py-1.5 text-[12.5px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" />
                        <path d="M16 2v4M8 2v4M3 10h18" />
                    </svg>
                    Đổi lịch
                </button>
            </div>

            {open && (
                <div className="fixed inset-0 z-[200] grid place-items-center overflow-y-auto p-4" style={{ background: 'rgba(24,35,15,.45)' }}>
                    <div className="my-auto w-full max-w-[680px] rounded-[18px] border border-cardBorder bg-white p-5">
                        <div className="mb-1 flex items-start justify-between gap-3">
                            <div className="text-[17px] font-bold text-ink">
                                Đổi lịch đơn <span className="font-mono text-grass">{order.code}</span>
                            </div>
                            <button onClick={() => setOpen(false)} aria-label="Đóng" className="shrink-0 rounded-full p-1 text-[#8a967a] hover:bg-[#f1f4ea]">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="m6 6 12 12M18 6 6 18" /></svg>
                            </button>
                        </div>
                        <p className="mb-3 text-[13px] text-moss">
                            Lịch hiện tại: <strong>{order.start_date} → {order.end_date}</strong> ({order.days} ngày). Tồn kho khoảng mới được kiểm tra khi lưu.
                        </p>

                        <DateRangeCalendar start={start} end={end} unavailable={NO_UNAVAILABLE} onChange={(s, e) => { setStart(s); setEnd(e); }} />

                        <div className="mt-2 text-center text-[13px] text-moss">
                            {start && end ? (
                                <>
                                    Thuê <strong className="text-ink">{fmt(start)} → {fmt(end)}</strong> · {newDays} ngày · tiền thuê mới{' '}
                                    <strong className="font-mono text-ink">{money(newTotal)}</strong>
                                    {newDiscount > 0 && (
                                        <>
                                            {' '}· giảm <strong className="font-mono text-grass">−{money(newDiscount)}</strong>
                                        </>
                                    )}
                                    <span className="ml-1 text-[#8a6d3a]">(cọc giữ nguyên · phải trả <strong className="font-mono text-ink">{money(newAmountDue)}</strong>)</span>
                                </>
                            ) : (
                                'Chạm chọn ngày nhận và ngày trả.'
                            )}
                        </div>

                        {errors.dates && <p className="mt-2 rounded-[8px] px-3 py-2 text-[12.5px]" style={{ background: '#fdf3f1', color: '#b3493a' }}>{errors.dates}</p>}
                        {(errors.start_date || errors.end_date) && (
                            <p className="mt-2 rounded-[8px] px-3 py-2 text-[12.5px]" style={{ background: '#fdf3f1', color: '#b3493a' }}>{errors.start_date ?? errors.end_date}</p>
                        )}

                        <div className="mt-4 flex flex-col gap-2.5 sm:flex-row-reverse">
                            <button
                                onClick={save}
                                disabled={!canSave || saving}
                                className="flex h-[48px] flex-1 items-center justify-center rounded-control text-[15px] font-bold text-white transition disabled:cursor-not-allowed"
                                style={{ background: canSave && !saving ? '#557A2B' : '#c4cfae' }}
                            >
                                {saving ? 'Đang lưu…' : 'Lưu lịch mới'}
                            </button>
                            <button onClick={() => setOpen(false)} className="h-[48px] rounded-control border border-[#cdd6b6] bg-white px-6 text-[14px] font-semibold text-pine sm:flex-none">
                                Huỷ
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

/** Per-store: hiện cửa hàng đơn + cho admin đổi store (kiểm tồn ở backend). */
function StoreChanger({ order, locations }: { order: Order; locations: { id: number; name: string }[] }) {
    const errors = usePage().props.errors as Record<string, string>;
    const change = (id: number) => {
        if (id === order.service_location?.id) return;
        router.patch(route('admin.orders.location', order.id), { service_location_id: id }, { preserveScroll: true });
    };
    return (
        <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3">
            <div className="flex flex-wrap gap-2">
                {locations.map((l) => {
                    const on = order.service_location?.id === l.id;
                    return (
                        <button
                            key={l.id}
                            onClick={() => change(l.id)}
                            className={`rounded-[9px] border px-3 py-1.5 text-[12.5px] font-semibold transition ${
                                on ? 'border-grass bg-grass text-white' : 'border-cardBorder text-pine hover:border-grass'
                            }`}
                        >
                            {l.name}
                        </button>
                    );
                })}
            </div>
            {errors.location && <p className="mt-1.5 text-[12px] text-[#b3493a]">{errors.location}</p>}
        </div>
    );
}

AdminOrders.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
