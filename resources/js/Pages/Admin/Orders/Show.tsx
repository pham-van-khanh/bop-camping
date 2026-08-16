import AdminLayout from '@/Layouts/AdminLayout';
import { money } from '@/lib/format';
import { STATUS_LABEL, STATUS_STYLE } from '@/lib/orderStatus';
import ContractPanel, { type ContractBlock } from '@/Pages/Admin/ContractPanel';
import {
    NEXT_STATUSES,
    OrderDetailPanel,
    useSessionLabel,
    type Order,
    type StoreOption,
} from '@/Pages/Admin/orderShared';
import { Head, Link, router } from '@inertiajs/react';
import { ReactNode } from 'react';

/**
 * Màn hình riêng cho 1 đơn (spec 2026-07-26) — gom toàn bộ thông tin + thao tác.
 * Đơn thường/con: OrderDetailPanel đầy đủ + đổi trạng thái. Đơn cha: tóm tắt + danh sách
 * đợt con (link sang màn riêng từng con) + huỷ cả cụm. Route/hành vi backend giữ nguyên.
 */
export default function AdminOrderShow({
    order,
    service_locations,
    max_discount_percent,
}: {
    order: Order & { contract?: ContractBlock | null };
    service_locations: StoreOption[];
    max_discount_percent: number;
}) {
    const style = STATUS_STYLE[order.status] ?? STATUS_STYLE.pending;
    const nexts = NEXT_STATUSES[order.status] ?? [];
    const sessLabel = useSessionLabel(order.session);

    const changeStatus = (status: string) => {
        router.patch(
            route('admin.orders.update', order.id),
            { status },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`Đơn ${order.code}`} />
            <div className="p-6">
                {/* Breadcrumb / back */}
                <div className="mb-4 flex flex-wrap items-center gap-2 text-[13px]">
                    <Link
                        href={route('admin.orders')}
                        className="font-semibold text-grass hover:underline"
                    >
                        ← Danh sách đơn
                    </Link>
                    {order.parent && (
                        <>
                            <span className="text-[#b0ba98]">/</span>
                            <Link
                                href={route(
                                    'admin.orders.show',
                                    order.parent.id,
                                )}
                                className="font-mono font-semibold text-pine hover:underline"
                            >
                                đơn gộp {order.parent.code}
                            </Link>
                        </>
                    )}
                </div>

                {/* Header */}
                <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <h1 className="font-mono text-[22px] font-extrabold text-pine">
                                {order.code}
                            </h1>
                            <span
                                className="rounded-pill px-2.5 py-1 text-[11.5px] font-bold"
                                style={{
                                    color: style.color,
                                    background: style.bg,
                                }}
                            >
                                {STATUS_LABEL[order.status]}
                            </span>
                            {order.is_parent && (
                                <span className="rounded-pill bg-grass px-2 py-0.5 text-[10px] font-bold text-white">
                                    GỒM {order.children?.length ?? 0} ĐỢT
                                </span>
                            )}
                        </div>
                        <p className="mt-1 text-[13px] text-moss">
                            {order.customer_name} ·{' '}
                            <span className="font-mono">
                                {order.customer_phone}
                            </span>{' '}
                            · đặt {order.created_at}
                            {sessLabel && (
                                <>
                                    {' '}
                                    ·{' '}
                                    <span className="font-semibold text-grass">
                                        {sessLabel}
                                    </span>
                                </>
                            )}
                        </p>
                    </div>

                    {/* Đổi trạng thái nhanh (đơn thường/con) hoặc huỷ cả cụm (đơn cha). */}
                    <div className="flex flex-wrap gap-1.5">
                        {!order.is_parent &&
                            nexts.map((s) => (
                                <button
                                    key={s}
                                    onClick={() => changeStatus(s)}
                                    className="rounded-[9px] border border-cardBorder px-3 py-1.5 text-[12.5px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                                >
                                    → {STATUS_LABEL[s]}
                                </button>
                            ))}
                        {order.is_parent &&
                            ['pending', 'confirmed'].includes(order.status) && (
                                <button
                                    onClick={() => {
                                        if (
                                            window.confirm(
                                                `Huỷ CẢ ${order.children?.length ?? 0} đợt của đơn ${order.code}?`,
                                            )
                                        )
                                            changeStatus('cancelled');
                                    }}
                                    className="rounded-[9px] border border-cardBorder px-3 py-1.5 text-[12.5px] font-semibold text-[#b3493a] transition hover:border-[#b3493a]"
                                >
                                    Huỷ cả cụm
                                </button>
                            )}
                    </div>
                </div>

                {order.is_parent ? (
                    /* Đơn cha (bopcamping-wtuv): envelope + danh sách đợt con, mỗi đợt mở màn riêng. */
                    <div className="rounded-[16px] border border-cardBorder bg-white p-5">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div className="text-[13px] text-moss">
                                Khoảng gộp{' '}
                                <span className="font-mono font-semibold text-pine">
                                    {order.start_date} → {order.end_date}
                                </span>
                            </div>
                            <div className="text-right">
                                <div className="font-mono text-[15px] font-bold text-ink">
                                    {money(order.total_price)}
                                </div>
                                <div className="font-mono text-[11px] text-campfire">
                                    cọc {money(order.deposit_total)}
                                </div>
                                {order.discount_total > 0 && (
                                    <div className="font-mono text-[11px] text-grass">
                                        giảm −{money(order.discount_total)}{' '}
                                        (voucher trên tổng)
                                    </div>
                                )}
                            </div>
                        </div>
                        <div className="mb-2 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                            Các đợt giao ({order.children?.length ?? 0})
                        </div>
                        <div className="grid gap-2">
                            {(order.children ?? []).map((c) => {
                                const cs =
                                    STATUS_STYLE[c.status] ??
                                    STATUS_STYLE.pending;
                                return (
                                    <Link
                                        key={c.id}
                                        href={route('admin.orders.show', c.id)}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-[12px] border border-cardBorder bg-[#fbfdf6] px-4 py-3 transition hover:border-grass"
                                    >
                                        <div>
                                            <span className="font-mono font-bold text-pine">
                                                {c.code}
                                            </span>
                                            <span className="ml-2 font-mono text-[12px] text-moss">
                                                {c.start_date} → {c.end_date} (
                                                {c.days} ngày)
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="font-mono text-[13px] font-bold text-ink">
                                                {money(c.amount_due)}
                                            </span>
                                            <span
                                                className="rounded-pill px-2.5 py-1 text-[11px] font-bold"
                                                style={{
                                                    color: cs.color,
                                                    background: cs.bg,
                                                }}
                                            >
                                                {STATUS_LABEL[c.status]}
                                            </span>
                                            <span className="text-grass">
                                                ›
                                            </span>
                                        </div>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                ) : (
                    <div className="grid gap-4">
                        <div className="rounded-[16px] border border-cardBorder bg-white p-5">
                            <OrderDetailPanel
                                order={order}
                                locations={service_locations}
                                maxDiscountPercent={max_discount_percent}
                            />
                        </div>
                        <ContractPanel
                            orderId={order.id}
                            contract={order.contract ?? null}
                            isParent={false}
                        />
                    </div>
                )}
            </div>
        </>
    );
}

AdminOrderShow.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
