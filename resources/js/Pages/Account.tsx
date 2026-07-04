import { Head, router, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import OrderLookupPanel, { type LookupProps } from '@/Components/site/OrderLookupPanel';
import { COMBO_GRAD } from '@/Components/site/ComboCard';
import { getCart, setCart, type CartLine, type CartLocation } from '@/lib/cart';
import { emit, EVENTS } from '@/lib/bus';
import { money } from '@/lib/format';
import { gradFor } from '@/lib/grad';
import { STATUS_LABEL, STATUS_STYLE } from '@/lib/orderStatus';
import { VOUCHER_SOURCE_FALLBACK, VOUCHER_SOURCE_LABEL, voucherValueText, type VoucherType } from '@/lib/voucher';
import type { PageProps } from '@/types';

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
    products: { id: number; name: string; cat: string; price: number; deposit: number; qty: number; locations: CartLocation[] }[];
    combos: { id: number; name: string; price: number; deposit: number; qty: number; comboItems: { name: string; qty: number }[]; locations: CartLocation[] }[];
    skipped: number;
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
    address: string | null;
    phone: string;
    note: string | null;
    total_price: number;
    deposit_total: number;
    discount_total: number;
    amount_due: number;
    groups: OrderGroup[];
    discounts: OrderDiscount[];
    reorder: ReorderPayload | null;
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

/** yyyy-mm-dd theo giờ máy khách (toISOString là UTC — lệch ngày lúc tối). */
const isoDate = (d: Date) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

export default function Account() {
    const { auth, stats, orders, referralCode, vouchers, lookup } = usePage<Props>().props;
    const [copied, setCopied] = useState(false);
    const [tab, setTab] = useState<Voucher['bucket']>('active');

    const activeOrders = orders.filter((o) => ACTIVE_STATUSES.includes(o.status));
    const doneOrders = orders.filter((o) => !ACTIVE_STATUSES.includes(o.status));
    const [orderTab, setOrderTab] = useState<'active' | 'done'>(activeOrders.length > 0 || doneOrders.length === 0 ? 'active' : 'done');
    const shownOrders = orderTab === 'active' ? activeOrders : doneOrders;

    // "Đặt lại" khi giỏ đang có món — hỏi trước khi thay toàn bộ giỏ.
    const [pendingReorder, setPendingReorder] = useState<AccountOrder | null>(null);

    const [copiedLink, setCopiedLink] = useState(false);
    const shareLink = typeof window !== 'undefined' ? `${window.location.origin}/?ref=${referralCode}` : `/?ref=${referralCode}`;

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

    const countBy = (bucket: Voucher['bucket']) => vouchers.filter((v) => v.bucket === bucket).length;
    const shown = vouchers.filter((v) => v.bucket === tab);

    /**
     * Dựng lại giỏ từ đơn cũ: ngày mặc định = ngày mai + giữ nguyên số ngày thuê
     * (ngày cũ đã qua). Giá/vị trí là bản MỚI NHẤT từ server; trang giỏ còn tự
     * làm tươi + check tồn kho theo ngày nên khách chỉnh tiếp ở đó.
     */
    const buildReorderLines = (o: AccountOrder): CartLine[] => {
        const startD = new Date();
        startD.setDate(startD.getDate() + 1);
        const endD = new Date(startD);
        endD.setDate(endD.getDate() + Math.max(0, o.days - 1));
        const start = isoDate(startD);
        const end = isoDate(endD);
        const r = o.reorder as ReorderPayload;

        return [
            ...r.products.map((p) => ({
                id: p.id, name: p.name, cat: p.cat, grad: gradFor(p.cat),
                price: p.price, deposit: p.deposit, qty: p.qty, start, end, locations: p.locations,
            })),
            ...r.combos.map((c) => ({
                id: c.id, kind: 'combo' as const, name: c.name, cat: 'combo', grad: COMBO_GRAD,
                price: c.price, deposit: c.deposit, qty: c.qty, start, end,
                locations: c.locations, comboItems: c.comboItems,
            })),
        ];
    };

    const commitReorder = (o: AccountOrder) => {
        setCart(buildReorderLines(o));
        const skipped = o.reorder?.skipped ?? 0;
        emit(
            EVENTS.toast,
            skipped > 0
                ? `Đã thêm lại đơn ${o.code} vào giỏ — ${skipped} món không còn cho thuê`
                : `Đã thêm lại đơn ${o.code} vào giỏ`,
        );
        setPendingReorder(null);
        router.visit('/gio-thue');
    };

    const startReorder = (o: AccountOrder) => {
        if (!o.reorder) return;
        if (getCart().length > 0) {
            setPendingReorder(o);
            return;
        }
        commitReorder(o);
    };

    /** Nhảy xuống section tra cứu với mã đơn + SĐT của đơn này (xem timeline). */
    const viewProgress = (o: AccountOrder) => {
        router.get(
            route('account'),
            { code: o.code, phone: o.phone },
            {
                preserveScroll: true,
                onSuccess: () => document.getElementById('tra-cuu')?.scrollIntoView({ behavior: 'smooth' }),
            },
        );
    };

    return (
        <>
            <Head title="Tài khoản của tôi" />
            <main className="mx-auto max-w-[820px] px-5 pb-12 pt-[38px]">
                <h1 className="mb-1 font-extrabold tracking-tight text-ink" style={{ fontSize: 'clamp(24px,3vw,32px)' }}>
                    Tài khoản của tôi
                </h1>
                <p className="mb-6 text-moss">
                    Chào {auth.user?.name ?? 'bạn'} 👋 — tổng quan đơn thuê, voucher và mã giới thiệu của bạn.
                </p>

                {/* Thống kê */}
                <div className="mb-[22px] grid grid-cols-2 gap-3.5 sm:grid-cols-3">
                    <Stat label="Sản phẩm đã thuê" value={stats.completedProductCount} hint="đã hoàn thành" />
                    <Stat label="Đơn đang thuê" value={stats.activeOrderCount} hint="chưa hoàn thành" />
                    <Stat label="Giới thiệu thành công" value={stats.referralCount} hint="bạn đã mời" />
                </div>

                {/* Đơn thuê */}
                <section className="mb-[22px]">
                    <h2 className="mb-3 text-[18px] font-bold text-ink">Đơn thuê của bạn</h2>
                    <div className="mb-3 flex flex-wrap items-center gap-2">
                        <button
                            onClick={() => setOrderTab('active')}
                            className={`rounded-pill px-3 py-1.5 text-[13px] font-semibold transition ${
                                orderTab === 'active' ? 'bg-grass text-white' : 'bg-[#eef2e3] text-pine hover:bg-[#e2e8d2]'
                            }`}
                        >
                            Đang thuê ({activeOrders.length})
                        </button>
                        <button
                            onClick={() => setOrderTab('done')}
                            className={`rounded-pill px-3 py-1.5 text-[13px] font-semibold transition ${
                                orderTab === 'done' ? 'bg-grass text-white' : 'bg-[#eef2e3] text-pine hover:bg-[#e2e8d2]'
                            }`}
                        >
                            Đã kết thúc ({doneOrders.length})
                        </button>
                    </div>

                    {shownOrders.length === 0 ? (
                        <div className="rounded-card border border-dashed bg-white p-8 text-center" style={{ borderColor: '#d6ddc4' }}>
                            <div className="mb-1 text-[28px]">⛺</div>
                            <div className="font-semibold text-ink">
                                {orderTab === 'active' ? 'Chưa có đơn nào đang thuê' : 'Chưa có đơn nào đã kết thúc'}
                            </div>
                            <div className="mt-1 text-[14px] text-moss">Khám phá thiết bị và đặt thuê cho chuyến đi sắp tới nhé.</div>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-3.5">
                            {shownOrders.map((order) => (
                                <OrderCard key={order.id} order={order} onReorder={startReorder} onViewProgress={viewProgress} />
                            ))}
                        </div>
                    )}
                    {orders.length >= 20 && (
                        <p className="mt-2 text-[12px] text-[#a3ad92]">
                            Hiển thị 20 đơn gần nhất — đơn cũ hơn tra bằng mã đơn ở mục Tra cứu bên dưới.
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
                        <div className="mb-1.5 text-[11px] font-bold uppercase tracking-[0.05em] text-[#8a967a]">Link chia sẻ</div>
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
                        Gửi <strong>link</strong> hoặc <strong>mã</strong> cho bạn bè. Bạn ấy mở link sẽ thấy lời mời và được điền sẵn mã; khi
                        nhập mã ở <strong>đơn thuê đầu tiên</strong> và hoàn tất đơn, bạn nhận voucher thưởng — còn bạn ấy được giảm giá ngay đơn đầu.
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
                                    tab === t.key ? 'bg-grass text-white' : 'bg-[#eef2e3] text-pine hover:bg-[#e2e8d2]'
                                }`}
                            >
                                {t.label} ({countBy(t.key)})
                            </button>
                        ))}
                    </div>
                    {shown.length === 0 ? (
                        <p className="py-4 text-center text-[14px] text-[#a3ad92]">Chưa có voucher nào ở mục này.</p>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {shown.map((v) => (
                                <li
                                    key={v.code}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-[10px] px-3.5 py-2.5"
                                    style={{ background: v.bucket === 'active' ? '#e7ecdc' : '#f1f2ed' }}
                                >
                                    <span className="text-[13px] text-pine">
                                        <span className="font-mono font-bold">{v.code}</span>
                                        <span className="ml-2 text-moss">
                                            {VOUCHER_SOURCE_LABEL[v.source] ?? VOUCHER_SOURCE_FALLBACK}
                                        </span>
                                        {v.expires_at && (
                                            <span className="ml-2 text-[11px] text-[#a3ad92]">HSD {v.expires_at}</span>
                                        )}
                                    </span>
                                    <span className={`font-mono font-bold ${v.bucket === 'active' ? 'text-grass' : 'text-[#a3ad92] line-through'}`}>
                                        {voucherValueText(v.type, v.value)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {/* Tra cứu đơn (chuyển từ /tra-cuu vào tài khoản — bopcamping-7w8) */}
                <section id="tra-cuu">
                    <h2 className="mb-1 text-[18px] font-bold text-ink">Tra cứu đơn</h2>
                    <p className="mb-3 text-[14px] text-moss">
                        Xem tiến trình bất kỳ đơn nào bằng mã đơn + số điện thoại đặt đơn (kể cả đơn đặt hộ người khác).
                    </p>
                    <OrderLookupPanel
                        key={`${lookup.query.code}|${lookup.query.phone}`}
                        lookup={lookup}
                        routeName="account"
                        preserveScroll
                    />
                </section>
            </main>

            {/* Popup xác nhận thay giỏ khi Đặt lại */}
            {pendingReorder && (
                <div className="fixed inset-0 z-[200] grid place-items-center p-5" style={{ background: 'rgba(24,35,15,.45)' }}>
                    <div className="w-full max-w-[420px] rounded-[18px] border border-cardBorder bg-white p-6">
                        <div className="mb-1.5 text-[17px] font-bold text-ink">Thay giỏ hiện tại?</div>
                        <p className="mb-4 text-[14px] leading-[1.55] text-moss">
                            Giỏ thuê của bạn đang có món. Đặt lại đơn{' '}
                            <span className="font-mono font-bold text-grass">{pendingReorder.code}</span> sẽ thay toàn bộ giỏ hiện tại.
                        </p>
                        <div className="flex flex-col gap-2">
                            <button
                                onClick={() => commitReorder(pendingReorder)}
                                className="h-11 rounded-control bg-grass text-[14px] font-bold text-white transition hover:bg-pine"
                            >
                                Thay giỏ & thêm đơn này
                            </button>
                            <button
                                onClick={() => setPendingReorder(null)}
                                className="h-11 rounded-control border border-[#cdd6b6] bg-white text-[14px] font-semibold text-pine"
                            >
                                Giữ giỏ hiện tại
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

function OrderCard({
    order,
    onReorder,
    onViewProgress,
}: {
    order: AccountOrder;
    onReorder: (o: AccountOrder) => void;
    onViewProgress: (o: AccountOrder) => void;
}) {
    return (
        <article className="rounded-card border border-cardBorder bg-card p-[18px]">
            <div className="mb-2.5 flex flex-wrap items-center justify-between gap-2">
                <span className="font-mono text-[17px] font-bold tracking-[0.06em] text-grass">{order.code}</span>
                <span className="flex items-center gap-2">
                    <span className="text-[12px] text-[#a3ad92]">Đặt ngày {order.created_at}</span>
                    <span className="rounded-pill px-3 py-1.5 text-[12px] font-bold" style={STATUS_STYLE[order.status] ?? STATUS_STYLE.pending}>
                        {STATUS_LABEL[order.status] ?? order.status}
                    </span>
                </span>
            </div>

            <div className="mb-1 text-[13px] text-moss">
                Thuê: {order.start_date} → {order.end_date} · {order.days} ngày
            </div>
            {order.address && (
                <div className="mb-2.5 text-[13px] text-moss">📍 Giao nhận: {order.address}</div>
            )}

            {/* Món trong đơn — combo gộp thành 1 dòng kèm món con */}
            <ul className="mb-2.5 flex flex-col gap-1.5 text-[14px] text-ink">
                {order.groups.map((g, i) => (
                    <li key={i}>
                        <div className="flex justify-between gap-2">
                            <span>
                                {g.kind === 'combo' && (
                                    <span className="mr-1.5 rounded-pill px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.04em]" style={{ background: '#efe4d3', color: '#7f4f24' }}>
                                        Combo
                                    </span>
                                )}
                                {g.name} <span className="text-moss">×{g.quantity}</span>
                            </span>
                            <span className="font-mono font-semibold text-ink">{money(g.subtotal)}</span>
                        </div>
                        {g.kind === 'combo' && g.children && g.children.length > 0 && (
                            <div className="mt-0.5 pl-[52px] text-[12px] text-[#8a967a]">
                                Gồm: {g.children.map((c) => `${c.name} ×${c.quantity}`).join(' · ')}
                            </div>
                        )}
                    </li>
                ))}
            </ul>

            {/* Tiền — cùng nhãn với checkout & tra cứu */}
            <div className="border-t border-cardBorder pt-2 text-[13.5px]">
                <div className="flex justify-between py-[3px]">
                    <span className="text-moss">Phí thuê</span>
                    <span className="font-mono font-semibold text-ink">{money(order.total_price)}</span>
                </div>
                {order.discounts.map((d, i) => (
                    <div key={i} className="flex justify-between py-[3px]">
                        <span className="text-moss">{d.label}</span>
                        <span className="font-mono font-semibold text-campfire">
                            {d.amount >= 0 ? `−${money(d.amount)}` : `+${money(-d.amount)}`}
                        </span>
                    </div>
                ))}
                {order.deposit_total > 0 && (
                    <div className="flex justify-between py-[3px]">
                        <span className="text-moss">Tiền cọc (hoàn lại)</span>
                        <span className="font-mono font-semibold text-campfire">{money(order.deposit_total)}</span>
                    </div>
                )}
                <div className="mt-1 flex justify-between border-t border-dashed pt-1.5" style={{ borderColor: '#e3e8d6' }}>
                    <span className="font-bold text-ink">Trả khi nhận (COD)</span>
                    <span className="font-mono font-bold text-grass">{money(order.amount_due)}</span>
                </div>
            </div>

            {order.note && (
                <p className="mt-2 text-[12.5px] text-[#8a967a]">
                    <span className="font-semibold">Ghi chú:</span> {order.note}
                </p>
            )}

            <div className="mt-3 flex flex-wrap gap-2 border-t border-cardBorder pt-3">
                <button
                    onClick={() => onViewProgress(order)}
                    className="h-10 rounded-control border border-[#cdd6b6] bg-white px-4 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]"
                >
                    Xem tiến trình
                </button>
                {order.reorder && (
                    <button
                        onClick={() => onReorder(order)}
                        className="h-10 rounded-control bg-grass px-4 text-[13px] font-bold text-white transition hover:bg-pine"
                    >
                        Đặt lại đơn này
                    </button>
                )}
            </div>
        </article>
    );
}

function Stat({ label, value, hint }: { label: string; value: number; hint: string }) {
    return (
        <div className="rounded-card border border-cardBorder bg-card p-4">
            <div className="font-mono text-[28px] font-extrabold leading-none text-grass">{value}</div>
            <div className="mt-1.5 text-[13px] font-semibold text-ink">{label}</div>
            <div className="text-[11px] text-[#a3ad92]">{hint}</div>
        </div>
    );
}

Account.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
