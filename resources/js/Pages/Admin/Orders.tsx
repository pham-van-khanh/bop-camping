import { Head, router, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import ProductStatusPill from '@/Components/ProductStatusPill';
import { money } from '@/lib/format';
import { STATUS_LABEL, STATUS_STYLE } from '@/lib/orderStatus';
import { sessionLabel, shopHours } from '@/lib/session';
import { NEXT_STATUSES, type Order } from '@/Pages/Admin/orderShared';

type Stats = { total: number; pending: number; confirmed: number; renting: number; returned: number; cancelled: number };
type InventoryItem = { id: number; name: string; category: string; quantity: number; status: string };

const TABS = [
    { key: 'all', label: 'Tất cả' },
    { key: 'pending', label: 'Chờ xác nhận' },
    { key: 'confirmed', label: 'Đã xác nhận' },
    { key: 'renting', label: 'Đang thuê' },
    { key: 'returned', label: 'Đã trả' },
    { key: 'cancelled', label: 'Đã huỷ' },
];

export default function AdminOrders({
    orders, stats, inventory, filters,
}: {
    orders: Order[]; stats: Stats; inventory: InventoryItem[];
    service_locations: { id: number; name: string }[];
    filters: { status: string; q: string };
    max_discount_percent: number;
}) {
    const [tab, setTab] = useState<'orders' | 'inventory'>('orders');
    const [search, setSearch] = useState(filters.q ?? '');

    // Buổi (spec 2026-07-26) — nhãn ngắn theo setting shop; hiển thị ở cột ngày thuê.
    const hours = shopHours((usePage().props as { site?: Parameters<typeof shopHours>[0] }).site);
    const sessTag = (o: Order) => sessionLabel(o.session, hours);

    const openOrder = (order: Order) => router.visit(route('admin.orders.show', order.id));

    const changeStatus = (order: Order, status: string) => {
        router.patch(route('admin.orders.update', order.id), { status }, { preserveScroll: true });
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

    return (
        <>
            <Head title="Quản trị · Đơn thuê" />
            <div className="p-6">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-[22px] font-extrabold text-pine">Quản trị đơn thuê</h1>
                        <p className="mt-0.5 text-[13px] text-moss">Bấm vào đơn để mở màn hình chi tiết</p>
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
                                        {orders.map((order) => {
                                            const style = STATUS_STYLE[order.status] ?? STATUS_STYLE.pending;
                                            const nexts = NEXT_STATUSES[order.status] ?? [];
                                            const tag = sessTag(order);

                                            return (
                                                <tr key={order.id}
                                                    className={`cursor-pointer border-b border-[#f1f4ea] hover:bg-[#fafcf7] ${order.is_parent ? 'bg-[#eef5e0]' : ''}`}
                                                    onClick={() => openOrder(order)}>
                                                    <td className="px-4 py-3 font-mono font-bold text-pine">
                                                        {order.code}
                                                        {order.is_parent && (
                                                            <span className="ml-1.5 rounded-pill bg-grass px-2 py-0.5 text-[10px] font-bold text-white">GỒM {order.children?.length ?? 0} ĐỢT</span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="font-semibold text-ink">{order.customer_name}</div>
                                                        <div className="font-mono text-[11px] text-moss">{order.customer_phone}</div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="font-mono text-[12px] text-pine">{order.start_date} → {order.end_date}</div>
                                                        {tag && <div className="text-[11px] text-grass">{tag}</div>}
                                                        {order.confirmed_pickup_time || order.confirmed_return_time ? (
                                                            <div className="font-mono text-[11px] text-moss">
                                                                Giao {order.confirmed_pickup_time ?? '—'} · Thu {order.confirmed_return_time ?? '—'}
                                                            </div>
                                                        ) : (
                                                            !order.is_parent && (
                                                                <span className="mt-0.5 inline-block rounded-pill bg-[#f1f4ea] px-1.5 py-0.5 text-[10px] font-semibold text-[#8a967a]">
                                                                    Chưa chốt giờ
                                                                </span>
                                                            )
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <div className="font-mono font-bold text-ink">{money(order.total_price)}</div>
                                                        <div className="font-mono text-[11px] text-campfire">cọc {money(order.deposit_total)}</div>
                                                        {order.discount_total > 0 && (
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
                                                            {/* Đơn cha: chỉ huỷ cả cụm; vòng đời giao/thu nằm ở từng con (T7). */}
                                                            {order.is_parent
                                                                ? ['pending', 'confirmed'].includes(order.status) && (
                                                                    <button onClick={(e) => {
                                                                        e.stopPropagation();
                                                                        if (window.confirm(`Huỷ CẢ ${order.children?.length ?? 0} đợt của đơn ${order.code}?`)) changeStatus(order, 'cancelled');
                                                                    }}
                                                                        className="rounded-[8px] border border-cardBorder px-2.5 py-1 text-[11px] font-semibold text-[#b3493a] transition hover:border-[#b3493a]">
                                                                        Huỷ cả cụm
                                                                    </button>
                                                                )
                                                                : (
                                                                    <>
                                                                        {nexts.map((s) => (
                                                                            <button key={s}
                                                                                onClick={(e) => { e.stopPropagation(); changeStatus(order, s); }}
                                                                                className="rounded-[8px] border border-cardBorder px-2.5 py-1 text-[11px] font-semibold text-pine transition hover:border-grass hover:text-grass">
                                                                                → {STATUS_LABEL[s]}
                                                                            </button>
                                                                        ))}
                                                                        {nexts.length === 0 && <span className="text-[11px] text-[#b0ba98]">-</span>}
                                                                    </>
                                                                )}
                                                        </div>
                                                    </td>
                                                </tr>
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

AdminOrders.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
