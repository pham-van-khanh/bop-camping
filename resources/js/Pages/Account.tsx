import { Head, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import { money } from '@/lib/format';
import { STATUS_LABEL, STATUS_STYLE } from '@/lib/orderStatus';
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

type Voucher = { code: string; amount: number; source: string };

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

const VOUCHER_SOURCE_LABEL: Record<string, string> = {
    referral_referrer: 'Thưởng giới thiệu bạn bè',
    referral_referee: 'Ưu đãi khách mới',
};

export default function Account() {
    const { auth, stats, activeOrders, referralCode, vouchers } = usePage<Props>().props;
    const [copied, setCopied] = useState(false);

    const copyCode = async () => {
        try {
            await navigator.clipboard.writeText(referralCode);
            setCopied(true);
            setTimeout(() => setCopied(false), 1800);
        } catch {
            // Trình duyệt chặn clipboard — bỏ qua, khách vẫn đọc/gõ tay được mã.
        }
    };

    return (
        <>
            <Head title="Tài khoản của tôi" />
            <main className="mx-auto max-w-[820px] px-5 pb-12 pt-[38px]">
                <h1
                    className="mb-1 font-extrabold tracking-tight text-ink"
                    style={{ fontSize: 'clamp(24px,3vw,32px)' }}
                >
                    Tài khoản của tôi
                </h1>
                <p className="mb-6 text-moss">
                    Chào {auth.user?.name ?? 'bạn'} 👋 — đây là tổng quan đơn thuê và mã giới thiệu của bạn.
                </p>

                {/* Thống kê */}
                <div className="mb-[22px] grid grid-cols-2 gap-3.5 sm:grid-cols-3">
                    <Stat label="Sản phẩm đã thuê" value={stats.completedProductCount} hint="đã hoàn thành" />
                    <Stat label="Đơn đang thuê" value={stats.activeOrderCount} hint="chưa hoàn thành" />
                    <Stat label="Lượt giới thiệu" value={stats.referralCount} hint="bạn đã mời" />
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
                        Chia sẻ mã cho bạn bè. Khi bạn ấy đăng ký bằng mã này và <strong>trả đơn thuê đầu tiên</strong>,
                        cả hai cùng nhận voucher giảm tiền thuê cho đơn sau.
                    </p>

                    {vouchers.length > 0 && (
                        <div className="mt-4 border-t border-cardBorder pt-4">
                            <div className="mb-2 text-[11px] font-bold uppercase tracking-[0.05em] text-[#8a967a]">
                                Voucher của bạn ({vouchers.length})
                            </div>
                            <ul className="flex flex-col gap-2">
                                {vouchers.map((v) => (
                                    <li
                                        key={v.code}
                                        className="flex flex-wrap items-center justify-between gap-2 rounded-[10px] px-3.5 py-2.5"
                                        style={{ background: '#e7ecdc' }}
                                    >
                                        <span className="text-[13px] text-pine">
                                            <span className="font-mono font-bold">{v.code}</span>
                                            <span className="ml-2 text-moss">
                                                {VOUCHER_SOURCE_LABEL[v.source] ?? 'Ưu đãi'}
                                            </span>
                                        </span>
                                        <span className="font-mono font-bold text-grass">−{money(v.amount)}</span>
                                    </li>
                                ))}
                            </ul>
                            <p className="mt-2 text-[12px] text-[#a3ad92]">
                                Nhập mã voucher khi đặt đơn tiếp theo để được giảm.
                            </p>
                        </div>
                    )}
                </section>

                {/* Đơn đang thuê */}
                <section>
                    <h2 className="mb-3 text-[18px] font-bold text-ink">Đơn đang thuê</h2>
                    {activeOrders.length === 0 ? (
                        <div
                            className="rounded-card border border-dashed bg-white p-8 text-center"
                            style={{ borderColor: '#d6ddc4' }}
                        >
                            <div className="mb-1 text-[28px]">⛺</div>
                            <div className="font-semibold text-ink">Chưa có đơn nào đang thuê</div>
                            <div className="mt-1 text-[14px] text-moss">
                                Khám phá thiết bị và đặt thuê cho chuyến đi sắp tới nhé.
                            </div>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-3.5">
                            {activeOrders.map((order) => (
                                <article key={order.id} className="rounded-card border border-cardBorder bg-card p-[18px]">
                                    <div className="mb-2.5 flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-mono text-[17px] font-bold tracking-[0.06em] text-grass">
                                            {order.code}
                                        </span>
                                        <span
                                            className="rounded-pill px-3 py-1.5 text-[12px] font-bold"
                                            style={STATUS_STYLE[order.status] ?? STATUS_STYLE.pending}
                                        >
                                            {STATUS_LABEL[order.status] ?? order.status}
                                        </span>
                                    </div>
                                    <div className="mb-2.5 text-[13px] text-moss">
                                        Thuê: {order.start_date} → {order.end_date}
                                    </div>
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
