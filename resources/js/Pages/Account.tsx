import { Head, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import { money } from '@/lib/format';
import { STATUS_LABEL, STATUS_STYLE } from '@/lib/orderStatus';
import { VOUCHER_SOURCE_FALLBACK, VOUCHER_SOURCE_LABEL, voucherValueText, type VoucherType } from '@/lib/voucher';
import type { PageProps } from '@/types';

type ActiveOrder = {
    id: number;
    code: string;
    status: string;
    start_date: string;
    end_date: string;
    total_price: number;
    items: { name: string; quantity: number }[];
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
    activeOrders: ActiveOrder[];
    referralCode: string;
    vouchers: Voucher[];
}>;

const BUCKET_TABS: { key: Voucher['bucket']; label: string }[] = [
    { key: 'active', label: 'Đang dùng được' },
    { key: 'used', label: 'Đã dùng' },
    { key: 'expired', label: 'Hết hạn' },
];

export default function Account() {
    const { auth, stats, activeOrders, referralCode, vouchers } = usePage<Props>().props;
    const [copied, setCopied] = useState(false);
    const [tab, setTab] = useState<Voucher['bucket']>('active');

    const copyCode = async () => {
        try {
            await navigator.clipboard.writeText(referralCode);
            setCopied(true);
            setTimeout(() => setCopied(false), 1800);
        } catch {
            // Trình duyệt chặn clipboard — khách vẫn đọc/gõ tay được mã.
        }
    };

    const countBy = (bucket: Voucher['bucket']) => vouchers.filter((v) => v.bucket === bucket).length;
    const shown = vouchers.filter((v) => v.bucket === tab);

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
                    <p className="mt-3 text-[13px] text-moss">
                        Chia sẻ mã cho bạn bè. Khi bạn ấy nhập mã ở <strong>đơn thuê đầu tiên</strong> và hoàn tất đơn,
                        bạn nhận voucher thưởng — còn bạn ấy được giảm giá ngay đơn đầu.
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

                {/* Đơn đang thuê */}
                <section>
                    <h2 className="mb-3 text-[18px] font-bold text-ink">Đơn đang thuê</h2>
                    {activeOrders.length === 0 ? (
                        <div className="rounded-card border border-dashed bg-white p-8 text-center" style={{ borderColor: '#d6ddc4' }}>
                            <div className="mb-1 text-[28px]">⛺</div>
                            <div className="font-semibold text-ink">Chưa có đơn nào đang thuê</div>
                            <div className="mt-1 text-[14px] text-moss">Khám phá thiết bị và đặt thuê cho chuyến đi sắp tới nhé.</div>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-3.5">
                            {activeOrders.map((order) => (
                                <article key={order.id} className="rounded-card border border-cardBorder bg-card p-[18px]">
                                    <div className="mb-2.5 flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-mono text-[17px] font-bold tracking-[0.06em] text-grass">{order.code}</span>
                                        <span className="rounded-pill px-3 py-1.5 text-[12px] font-bold" style={STATUS_STYLE[order.status] ?? STATUS_STYLE.pending}>
                                            {STATUS_LABEL[order.status] ?? order.status}
                                        </span>
                                    </div>
                                    <div className="mb-2.5 text-[13px] text-moss">Thuê: {order.start_date} → {order.end_date}</div>
                                    <ul className="mb-2.5 flex flex-col gap-1 text-[14px] text-ink">
                                        {order.items.map((item, i) => (
                                            <li key={i} className="flex justify-between">
                                                <span>{item.name}</span>
                                                <span className="text-moss">×{item.quantity}</span>
                                            </li>
                                        ))}
                                    </ul>
                                    <div className="border-t border-cardBorder pt-2.5 text-right">
                                        <span className="text-[13px] text-moss">Tổng tiền thuê: </span>
                                        <span className="font-mono font-bold text-ink">{money(order.total_price)}</span>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </section>
            </main>
        </>
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
