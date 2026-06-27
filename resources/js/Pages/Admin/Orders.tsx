import { Head, router, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import ProductStatusPill from '@/Components/ProductStatusPill';
import { money } from '@/lib/format';
import { STATUS_LABEL, STATUS_STYLE } from '@/lib/orderStatus';
import { voucherValueText, VOUCHER_SOURCE_LABEL, VOUCHER_SOURCE_FALLBACK, type VoucherType } from '@/lib/voucher';

type OrderItem = { name: string; quantity: number; price_per_day: number; days: number; subtotal: number };
type UsedVoucher = { code: string; type: VoucherType; value: number; source: string };
type Order = {
    id: number; code: string; customer_name: string; customer_phone: string;
    customer_email: string | null; customer_address: string | null;
    start_date: string; end_date: string; days: number;
    total_price: number; deposit_total: number; discount_total: number; amount_due: number;
    status: string; note: string | null; created_at: string; items: OrderItem[];
    vouchers: UsedVoucher[]; referral: { referrer_name: string | null; status: string } | null;
    payment_option: 'full' | 'deposit' | null;
    payment_label: string; deposit_paid: boolean; rental_paid: boolean;
    prepay_amount: number; cod_amount: number;
};
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
    orders, stats, inventory, filters,
}: {
    orders: Order[]; stats: Stats; inventory: InventoryItem[];
    filters: { status: string };
}) {
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [tab, setTab] = useState<'orders' | 'inventory'>('orders');

    const changeStatus = (order: Order, status: string) => {
        router.patch(route('admin.orders.update', order.id), { status }, { preserveScroll: true });
    };

    const markPaid = (order: Order, field: 'deposit' | 'rental', paid: boolean) => {
        router.patch(route('admin.orders.payment', order.id), { field, paid }, { preserveScroll: true });
    };

    const PAYMENT_OPTION_LABEL: Record<string, string> = {
        full: 'Trả toàn bộ trước',
        deposit: 'Cọc trước · thuê COD',
    };

    const filterTab = (status: string) => {
        router.get(route('admin.orders'), status === 'all' ? {} : { status }, { preserveState: true, replace: true });
    };

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
                        {/* Status filter */}
                        <div className="mb-4 flex flex-wrap gap-2">
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
                                            const isExpanded = expandedId === order.id;
                                            return (
                                                <>
                                                    <tr key={order.id}
                                                        className="cursor-pointer border-b border-[#f1f4ea] hover:bg-[#fafcf7]"
                                                        onClick={() => setExpandedId(isExpanded ? null : order.id)}>
                                                        <td className="px-4 py-3 font-mono font-bold text-pine">{order.code}</td>
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
                                                                <div className="font-mono text-[11px] text-grass">voucher −{money(order.discount_total)}</div>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <span className="rounded-pill px-2.5 py-1 text-[11.5px] font-bold"
                                                                style={{ color: style.color, background: style.bg }}>
                                                                {STATUS_LABEL[order.status]}
                                                            </span>
                                                            {order.payment_option && (
                                                                <div className="mt-1 text-[11px] font-semibold text-moss">{order.payment_label}</div>
                                                            )}
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
                                                                            <DetailRow label="Đặt lúc" value={order.created_at} />
                                                                        </div>

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
                                                                                    {order.items.map((item, i) => (
                                                                                        <tr key={i} className="border-t border-[#eef2e3]">
                                                                                            <td className="px-3 py-2 text-ink">{item.name}<div className="text-[11px] text-moss">{money(item.price_per_day)}/ngày</div></td>
                                                                                            <td className="px-3 py-2 text-center text-moss">{item.quantity} × {item.days}</td>
                                                                                            <td className="px-3 py-2 text-right font-mono font-bold text-ink">{money(item.subtotal)}</td>
                                                                                        </tr>
                                                                                    ))}
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    </div>

                                                                    {/* Cột phải: tiền + ưu đãi + ghi chú */}
                                                                    <div>
                                                                        <div className="mb-2 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">Thanh toán</div>
                                                                        <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3 text-[12.5px]">
                                                                            <DetailRow label="Tiền thuê" value={money(order.total_price)} mono />
                                                                            {order.discount_total > 0 && <DetailRow label="Giảm giá" value={`−${money(order.discount_total)}`} mono accent="#3a5a1f" />}
                                                                            <DetailRow label="Tiền cọc" value={money(order.deposit_total)} mono />
                                                                            {order.payment_option ? (
                                                                                <>
                                                                                    <DetailRow label="Hình thức" value={PAYMENT_OPTION_LABEL[order.payment_option]} />
                                                                                    <DetailRow label="Chuyển khoản trước" value={money(order.prepay_amount)} mono />
                                                                                    {order.cod_amount > 0 && <DetailRow label="Thu COD khi nhận" value={money(order.cod_amount)} mono accent="#C97B36" />}
                                                                                    <div className="mt-2 flex items-center justify-between border-t border-[#eef2e3] pt-2">
                                                                                        <span className="text-moss">Tình trạng</span>
                                                                                        <span className="font-semibold text-pine">{order.payment_label}</span>
                                                                                    </div>
                                                                                    <div className="mt-2 flex flex-wrap gap-1.5" onClick={(e) => e.stopPropagation()}>
                                                                                        <PayToggle
                                                                                            label="cọc"
                                                                                            paid={order.deposit_paid}
                                                                                            onToggle={() => markPaid(order, 'deposit', !order.deposit_paid)}
                                                                                        />
                                                                                        <PayToggle
                                                                                            label="phí thuê"
                                                                                            paid={order.rental_paid}
                                                                                            onToggle={() => markPaid(order, 'rental', !order.rental_paid)}
                                                                                        />
                                                                                    </div>
                                                                                </>
                                                                            ) : (
                                                                                <div className="mt-1 flex items-center justify-between border-t border-[#eef2e3] pt-2">
                                                                                    <span className="font-bold text-ink">Trả khi nhận</span>
                                                                                    <span className="font-mono text-[14px] font-extrabold text-pine">{money(order.amount_due)}</span>
                                                                                </div>
                                                                            )}
                                                                        </div>

                                                                        <div className="mb-2 mt-3 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">Ưu đãi đã dùng</div>
                                                                        <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3 text-[12.5px]">
                                                                            {order.vouchers.length === 0 && !order.referral && (
                                                                                <span className="text-moss">Không có</span>
                                                                            )}
                                                                            {order.vouchers.map((v) => (
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
                                                                        </div>

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
                                                </>
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

function PayToggle({ label, paid, onToggle }: { label: string; paid: boolean; onToggle: () => void }) {
    return (
        <button
            onClick={onToggle}
            className={`rounded-[8px] border px-2.5 py-1 text-[11px] font-semibold transition ${
                paid
                    ? 'border-grass bg-[#eef2e3] text-grass hover:bg-[#e2ecd0]'
                    : 'border-cardBorder text-pine hover:border-grass hover:text-grass'
            }`}
        >
            {paid ? `✓ Đã nhận ${label}` : `Đánh dấu nhận ${label}`}
        </button>
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

AdminOrders.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
