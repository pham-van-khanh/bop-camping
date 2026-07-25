import { router } from '@inertiajs/react';
import { useState } from 'react';
import { money } from '@/lib/format';
import { STATUS_STYLE } from '@/lib/orderStatus';

// Panel tra cứu đơn (form + kết quả + timeline) — dùng chung cho trang /tra-cuu
// (khách vãng lai) và section "Tra cứu đơn" trong /tai-khoan (bopcamping-7w8).
// Mỗi trang truyền routeName của chính nó để kết quả render lại đúng trang.

type TimelineStep = { title: string; note: string; state: 'done' | 'current' | 'todo' };
type OrderItem    = { name: string; quantity: number; days: number; subtotal: number };

export type LookupOrder = {
    code: string;
    customer_name: string;
    customer_phone: string;
    start_date: string;
    end_date: string;
    total_price: number;
    deposit_total: number;
    discount_total: number;
    amount_due: number;
    status: string;
    status_label: string;
    note: string | null;
    created_at: string;
    items: OrderItem[];
    timeline: TimelineStep[];
    // Đơn gộp (bopcamping-wtuv T8): tra mã cha → các ĐỢT giao (mỗi đợt là 1 đơn con đầy đủ).
    installments?: LookupOrder[];
};

export type LookupProps = {
    order: LookupOrder | null;
    not_found: boolean;
    query: { code: string; phone: string };
};

const inputCls = 'h-12 w-full rounded-[11px] border border-cardBorder bg-white px-3.5 text-[15px] text-ink outline-none focus:border-grass transition';
const labelCls = 'mb-1.5 block text-[11px] font-bold uppercase tracking-[0.05em] text-[#8a967a]';

export default function OrderLookupPanel({
    lookup,
    routeName,
    preserveScroll = false,
}: {
    lookup: LookupProps;
    routeName: string;
    preserveScroll?: boolean;
}) {
    const { order, not_found, query } = lookup;

    const [code, setCode]   = useState(query.code ?? '');
    const [phone, setPhone] = useState(query.phone ?? '');
    const [loading, setLoading] = useState(false);

    const valid = code.trim().length >= 3 && phone.trim().length >= 8;

    const doLookup = () => {
        if (!valid) return;
        setLoading(true);
        router.get(
            route(routeName),
            { code: code.trim().toUpperCase(), phone: phone.trim() },
            {
                preserveScroll,
                onFinish: () => setLoading(false),
            },
        );
    };

    return (
        <>
            {/* Search form */}
            <div className="mb-[22px] rounded-card border border-cardBorder bg-card p-5">
                <div className="flex flex-col gap-3">
                    <div>
                        <label className={labelCls}>Mã đơn</label>
                        <input
                            value={code}
                            onChange={(e) => setCode(e.target.value.toUpperCase())}
                            onKeyDown={(e) => e.key === 'Enter' && doLookup()}
                            placeholder="VD: BOP-A1B2C3"
                            className={`${inputCls} font-mono tracking-[0.04em]`}
                        />
                    </div>
                    <div>
                        <label className={labelCls}>Số điện thoại</label>
                        <input
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && doLookup()}
                            placeholder="Số điện thoại đặt đơn"
                            inputMode="tel"
                            className={inputCls}
                        />
                    </div>
                    <button
                        onClick={doLookup}
                        disabled={!valid || loading}
                        className="h-12 rounded-control text-[15px] font-bold text-white transition disabled:cursor-not-allowed disabled:opacity-60"
                        style={{ background: valid && !loading ? '#557A2B' : '#c4cfae' }}
                    >
                        {loading ? 'Đang tra cứu…' : 'Tra cứu đơn'}
                    </button>
                </div>
            </div>

            {/* Not found */}
            {not_found && (
                <div
                    className="rounded-[14px] border bg-white p-[22px] text-center"
                    style={{ borderColor: '#ecd3c4', color: '#9a5a3a' }}
                >
                    <div className="mb-1 text-[28px]">🔍</div>
                    <div className="mb-1 font-bold">Không tìm thấy đơn</div>
                    <div className="text-[14px] text-[#8a967a]">
                        Kiểm tra lại mã đơn (<span className="font-mono">BOP-XXXXXX</span>) và số điện thoại giúp tụi mình nhé.
                    </div>
                </div>
            )}

            {/* Found */}
            {order && (
                <div className="rounded-card border border-cardBorder bg-card p-[22px]">
                    {/* Header */}
                    <div className="mb-[18px] flex flex-wrap items-start justify-between gap-2.5">
                        <div>
                            <div className="font-mono text-[22px] font-bold tracking-[0.06em] text-grass">
                                {order.code}
                            </div>
                            <div className="mt-[3px] text-[14px] text-moss">
                                {order.customer_name} · {order.customer_phone}
                            </div>
                        </div>
                        <span
                            className="rounded-pill px-3 py-1.5 text-[12px] font-bold"
                            style={STATUS_STYLE[order.status] ?? STATUS_STYLE.pending}
                        >
                            {order.status_label}
                        </span>
                    </div>

                    {/* Summary boxes */}
                    <div className="mb-5 flex flex-wrap gap-3.5 text-[14px]">
                        <Box k="Nhận đồ" v={order.start_date} />
                        <Box k="Trả đồ" v={order.end_date} />
                        {order.discount_total > 0 && (
                            <Box k="Đã giảm" v={`−${money(order.discount_total)}`} />
                        )}
                        {order.deposit_total > 0 && (
                            <Box k="Tiền cọc (hoàn lại)" v={money(order.deposit_total)} />
                        )}
                        {/* Khớp nhãn với checkout/màn thành công: thuê sau giảm + cọc, thu COD khi nhận */}
                        <Box k="Trả khi nhận (COD)" v={money(order.amount_due)} accent />
                    </div>

                    {/* Đơn gộp: liệt kê TỪNG ĐỢT giao (mỗi đợt = đơn con) — bopcamping-wtuv T8. */}
                    {order.installments && order.installments.length > 0 ? (
                        <div className="mb-4 flex flex-col gap-3">
                            {order.installments.map((inst, idx) => (
                                <div key={inst.code} className="overflow-hidden rounded-[10px] border border-[#e3ecd2]">
                                    <div className="flex flex-wrap items-center justify-between gap-2 px-3 py-2" style={{ background: '#eef5e0' }}>
                                        <div className="text-[13px] font-bold text-pine">
                                            Đợt {idx + 1} · <span className="font-mono text-grass">{inst.code}</span>
                                            <span className="ml-2 font-mono font-normal text-moss">{inst.start_date} → {inst.end_date}</span>
                                        </div>
                                        <span className="rounded-pill px-2.5 py-1 text-[11px] font-bold" style={STATUS_STYLE[inst.status] ?? STATUS_STYLE.pending}>
                                            {inst.status_label}
                                        </span>
                                    </div>
                                    <table className="w-full text-[13px]">
                                        <tbody>
                                            {inst.items.map((item, i) => (
                                                <tr key={i} className="border-t border-[#eef2e3]">
                                                    <td className="px-3 py-2 text-ink">{item.name}</td>
                                                    <td className="px-3 py-2 text-center text-moss">×{item.quantity}</td>
                                                    <td className="px-3 py-2 text-center text-moss">{item.days} ngày</td>
                                                    <td className="px-3 py-2 text-right font-mono font-bold text-ink">{money(item.subtotal)}</td>
                                                </tr>
                                            ))}
                                            <tr className="border-t border-[#eef2e3]" style={{ background: '#fbfdf6' }}>
                                                <td colSpan={3} className="px-3 py-1.5 text-right text-[12px] text-moss">COD đợt này {inst.discount_total > 0 ? `(đã giảm −${money(inst.discount_total)})` : ''}</td>
                                                <td className="px-3 py-1.5 text-right font-mono font-bold text-pine">{money(inst.amount_due)}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="mb-4 overflow-hidden rounded-[10px] border border-[#eef2e3]">
                            <table className="w-full text-[13px]">
                                <thead>
                                    <tr style={{ background: '#f8faf4' }} className="border-b border-[#eef2e3]">
                                        <th className="px-3 py-2 text-left font-semibold text-moss">Thiết bị</th>
                                        <th className="px-3 py-2 text-center font-semibold text-moss">SL</th>
                                        <th className="px-3 py-2 text-center font-semibold text-moss">Ngày</th>
                                        <th className="px-3 py-2 text-right font-semibold text-moss">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {order.items.map((item, i) => (
                                        <tr key={i} className="border-t border-[#eef2e3]">
                                            <td className="px-3 py-2 text-ink">{item.name}</td>
                                            <td className="px-3 py-2 text-center text-moss">{item.quantity}</td>
                                            <td className="px-3 py-2 text-center text-moss">{item.days}</td>
                                            <td className="px-3 py-2 text-right font-mono font-bold text-ink">
                                                {money(item.subtotal)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Note */}
                    {order.note && (
                        <p className="mb-4 text-[13px] text-moss">
                            <span className="font-semibold">Ghi chú:</span> {order.note}
                        </p>
                    )}

                    {/* Timeline */}
                    <div className="border-t border-cardBorder pt-4">
                        <div className="mb-2.5 text-[11px] font-bold uppercase tracking-[0.05em] text-[#8a967a]">
                            Tiến trình đơn hàng
                        </div>
                        {order.timeline.map((tl, i) => {
                            const last   = i === order.timeline.length - 1;
                            const dotBg  = tl.state === 'todo' ? '#d6ddc4' : '#557A2B';
                            const glow   = tl.state === 'current' ? '0 0 0 4px rgba(85,122,43,.18)' : 'none';
                            return (
                                <div key={i} className="flex items-start gap-3 pb-3.5">
                                    <div className="flex flex-none flex-col items-center">
                                        <span
                                            className="h-3 w-3 rounded-full"
                                            style={{ background: dotBg, boxShadow: glow }}
                                        />
                                        {!last && (
                                            <span
                                                className="mt-1 w-[2px] flex-1"
                                                style={{
                                                    minHeight: 22,
                                                    background: tl.state === 'done' ? '#557A2B' : '#e3e8d6',
                                                }}
                                            />
                                        )}
                                    </div>
                                    <div className="pt-px">
                                        <div
                                            className="text-[14px]"
                                            style={{
                                                fontWeight: tl.state === 'current' ? 700 : 600,
                                                color: tl.state === 'todo' ? '#a3ad92' : '#18230F',
                                            }}
                                        >
                                            {tl.title}
                                        </div>
                                        <div className="text-[12px] text-[#a3ad92]">{tl.note}</div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
        </>
    );
}

function Box({ k, v, accent }: { k: string; v: string; accent?: boolean }) {
    return (
        <div className="rounded-[10px] px-3.5 py-2.5" style={{ background: '#f1f4ea' }}>
            <div className="text-[11px] text-[#8a967a]">{k}</div>
            <div className="font-mono font-bold" style={{ color: accent ? '#557A2B' : '#18230F' }}>
                {v}
            </div>
        </div>
    );
}
