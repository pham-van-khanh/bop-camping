import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ChangeEvent, ReactNode, useEffect, useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import {
    cartTotals, clearCart, getCart, lineDays, lineDeposit, lineRent, removeLine, setQty, type CartLine,
} from '@/lib/cart';
import { money, rangeText } from '@/lib/format';
import { on, EVENTS } from '@/lib/bus';
import type { PageProps } from '@/types';

type CheckoutItem = { product_id: number; quantity: number; start: string; end: string };

export default function Cart() {
    const { auth, flash } = usePage<PageProps>().props;
    const user = auth.user;

    const [lines, setLines] = useState<CartLine[]>([]);

    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        phone: string;
        address: string;
        note: string;
        voucher_code: string;
        items: CheckoutItem[];
    }>({
        name: user?.name ?? '',
        phone: user?.phone ?? '',
        address: '',
        note: '',
        voucher_code: '',
        items: [],
    });

    // Sync cart từ localStorage
    useEffect(() => {
        const current = getCart();
        setLines(current);
        setData('items', toCheckoutItems(current));
        return on(EVENTS.cartChange, () => {
            const updated = getCart();
            setLines(updated);
            setData('items', toCheckoutItems(updated));
        });
    }, []);

    // Xoá cart sau khi đặt thành công
    useEffect(() => {
        if (flash.order_code) clearCart();
    }, [flash.order_code]);

    const totals = cartTotals(lines);
    const canSubmit = data.name.trim().length >= 2
        && data.phone.trim().length >= 8
        && data.address.trim().length >= 4
        && lines.length > 0
        && !processing;

    const set = (k: 'name' | 'phone' | 'address' | 'note') =>
        (e: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
            setData(k, e.target.value);

    const submit = () => post(route('order.store'));

    // ----- Đặt thành công -----
    if (flash.order_code) {
        return (
            <>
                <Head title="Đã đặt giữ chỗ" />
                <main className="mx-auto max-w-[1060px] px-5 pb-12 pt-[30px]">
                    <div className="mx-auto mt-2.5 max-w-[600px] rounded-[20px] border border-cardBorder bg-card text-center" style={{ padding: 'clamp(28px,4vw,44px)' }}>
                        <div className="mx-auto mb-[18px] grid h-[68px] w-[68px] place-items-center rounded-full" style={{ background: '#dcebc4' }}>
                            <svg width="34" height="34" viewBox="0 0 24 24" fill="none"><path d="m5 13 4 4L19 7" stroke="#3a5a1f" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" /></svg>
                        </div>
                        <h1 className="mb-2 text-[26px] font-extrabold text-ink">Đã đặt giữ chỗ!</h1>
                        <p className="mb-[18px] text-moss">Tụi mình sẽ gọi xác nhận trong ít phút. Mã đơn của bạn:</p>
                        <div className="mb-[22px] inline-block rounded-control border border-dashed px-[26px] py-3 font-mono text-[24px] font-bold tracking-[0.1em] text-grass" style={{ background: '#eef2e3', borderColor: '#b9c79a' }}>
                            {flash.order_code}
                        </div>
                        <div className="mb-[22px] rounded-[13px] px-[18px] py-4 text-left" style={{ background: '#f6f8ef' }}>
                            <Row k="Khách" v={flash.order_name ?? ''} />
                            <Row k="Số loại thiết bị" v={`${flash.order_items} loại`} mono />
                            {(flash.order_discount ?? 0) > 0 && (
                                <Row k="Giảm từ voucher" v={`−${money(flash.order_discount ?? 0)}`} mono accentWarm />
                            )}
                            <Row k="Trả khi nhận (COD)" v={money(flash.order_pay ?? 0)} mono accent />
                        </div>
                        <div className="flex flex-wrap justify-center gap-2.5">
                            <Link href="/tra-cuu" className="grid h-12 place-items-center rounded-control bg-grass px-[22px] font-bold text-white">Tra cứu đơn này</Link>
                            <Link href="/" className="grid h-12 place-items-center rounded-control border border-[#cdd6b6] bg-white px-[22px] font-semibold text-pine">Về trang chủ</Link>
                        </div>
                    </div>
                </main>
            </>
        );
    }

    // ----- Giỏ trống -----
    if (lines.length === 0) {
        return (
            <>
                <Head title="Giỏ thuê" />
                <main className="mx-auto max-w-[1060px] px-5 pb-12 pt-[30px]">
                    <h1 className="mb-[22px] font-extrabold tracking-tight text-ink" style={{ fontSize: 'clamp(24px,3vw,32px)' }}>Giỏ thuê</h1>
                    <div className="rounded-[18px] border border-dashed px-6 py-14 text-center" style={{ borderColor: '#cdd6b6', background: '#FBFCF7' }}>
                        <div className="mb-3 text-[40px]">🎒</div>
                        <div className="mb-1.5 text-[19px] font-bold text-ink">Giỏ thuê đang trống</div>
                        <div className="mb-[22px] text-moss">Thêm vài món thiết bị để bắt đầu chuyến đi của bạn.</div>
                        <Link href="/thiet-bi" className="inline-grid h-12 place-items-center rounded-control bg-grass px-[26px] font-bold text-white">Chọn thiết bị</Link>
                    </div>
                </main>
            </>
        );
    }

    // ----- Giỏ có hàng -----
    return (
        <>
            <Head title="Giỏ thuê" />
            <main className="mx-auto max-w-[1060px] px-5 pb-12 pt-[30px]">
                <h1 className="mb-[22px] font-extrabold tracking-tight text-ink" style={{ fontSize: 'clamp(24px,3vw,32px)' }}>Giỏ thuê</h1>

                {errors.items && (
                    <div className="mb-4 rounded-[12px] border border-red-200 bg-red-50 px-4 py-3 text-[14px] text-red-700">
                        {errors.items}
                    </div>
                )}

                <div className="grid items-start gap-6" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))' }}>
                    {/* Danh sách món */}
                    <div>
                        {lines.map((it, i) => (
                            <div key={`${it.id}-${it.start}-${it.end}`} className="mb-3 flex gap-3.5 rounded-[14px] border border-cardBorder bg-card p-[13px]">
                                <div className="h-16 w-16 flex-none rounded-[10px]" style={{ background: it.grad }} />
                                <div className="min-w-0 flex-1">
                                    <div className="text-[15px] font-bold leading-[1.25] text-ink">{it.name}</div>
                                    <div className="my-1 font-mono text-[12px] text-[#8a967a]">{rangeText(it.start, it.end)} · {lineDays(it)} ngày</div>
                                    <div className="flex flex-wrap items-center justify-between gap-2.5">
                                        <div className="flex items-center overflow-hidden rounded-[9px] border border-cardBorder">
                                            <button onClick={() => setQty(i, it.qty - 1)} className="h-8 w-[30px] bg-[#f1f4ea] text-[16px] text-grass">−</button>
                                            <span className="w-[34px] text-center font-mono text-[13px] font-bold">{it.qty}</span>
                                            <button onClick={() => setQty(i, it.qty + 1)} className="h-8 w-[30px] bg-[#f1f4ea] text-[16px] text-grass">+</button>
                                        </div>
                                        <div className="text-right">
                                            <div className="font-mono text-[15px] font-bold text-ink">{money(lineRent(it))}</div>
                                            <div className="font-mono text-[11px] text-campfire">cọc {money(lineDeposit(it))}</div>
                                        </div>
                                    </div>
                                </div>
                                <button onClick={() => removeLine(i)} title="Xoá" className="self-start px-1 py-0.5 text-[18px]" style={{ color: '#b3493a' }}>×</button>
                            </div>
                        ))}
                        <Link href="/thiet-bi" className="mt-1 inline-block text-[14px] font-bold text-grass">+ Thêm thiết bị khác</Link>
                    </div>

                    {/* Checkout */}
                    <div className="sticky top-[84px] rounded-card border border-cardBorder bg-card p-5">
                        <div className="mb-3.5 text-[16px] font-bold text-ink">Thông tin nhận đồ</div>
                        <div className="mb-4 flex flex-col gap-[11px]">
                            <div>
                                <input value={data.name} onChange={set('name')} placeholder="Họ và tên"
                                    className={`h-[46px] w-full rounded-[11px] border bg-white px-3.5 text-[15px] text-ink outline-none focus:border-grass ${errors.name ? 'border-red-400' : 'border-cardBorder'}`} />
                                {errors.name && <p className="mt-1 text-[12px] text-red-500">{errors.name}</p>}
                            </div>
                            <div>
                                <input value={data.phone} onChange={set('phone')} placeholder="Số điện thoại" inputMode="tel"
                                    className={`h-[46px] w-full rounded-[11px] border bg-white px-3.5 text-[15px] text-ink outline-none focus:border-grass ${errors.phone ? 'border-red-400' : 'border-cardBorder'}`} />
                                {errors.phone && <p className="mt-1 text-[12px] text-red-500">{errors.phone}</p>}
                            </div>
                            <div>
                                <input value={data.address} onChange={set('address')} placeholder="Địa chỉ giao nhận"
                                    className={`h-[46px] w-full rounded-[11px] border bg-white px-3.5 text-[15px] text-ink outline-none focus:border-grass ${errors.address ? 'border-red-400' : 'border-cardBorder'}`} />
                                {errors.address && <p className="mt-1 text-[12px] text-red-500">{errors.address}</p>}
                            </div>
                            <textarea value={data.note} onChange={set('note')} placeholder="Ghi chú (tuỳ chọn)" rows={2}
                                className="resize-y rounded-[11px] border border-cardBorder bg-white px-3.5 py-[11px] text-[14px] text-ink outline-none focus:border-grass" />
                            {user && (
                                <div>
                                    <input
                                        value={data.voucher_code}
                                        onChange={(e) => setData('voucher_code', e.target.value.toUpperCase())}
                                        placeholder="Mã voucher (nếu có)"
                                        className={`h-[46px] w-full rounded-[11px] border bg-white px-3.5 font-mono text-[15px] uppercase tracking-[0.04em] text-ink outline-none focus:border-grass ${errors.voucher_code ? 'border-red-400' : 'border-cardBorder'}`}
                                    />
                                    {errors.voucher_code
                                        ? <p className="mt-1 text-[12px] text-red-500">{errors.voucher_code}</p>
                                        : <p className="mt-1 text-[12px] text-[#8a967a]">Xem mã trong trang Tài khoản. Giảm giá áp dụng khi xác nhận đơn.</p>}
                                </div>
                            )}
                        </div>
                        <div className="border-t border-cardBorder pt-3.5">
                            <Row k="Tổng tiền thuê" v={money(totals.rent)} mono />
                            <Row k="Tổng cọc (hoàn khi trả)" v={money(totals.deposit)} mono accentWarm />
                            <div className="mt-1.5 flex justify-between border-t border-dashed pt-2.5 text-[16px]" style={{ borderColor: '#d6ddc4' }}>
                                <span className="font-bold text-ink">Trả khi nhận</span>
                                <span className="font-mono text-[18px] font-bold text-grass">{money(totals.pay)}</span>
                            </div>
                        </div>
                        <div className="my-3.5 flex items-center gap-2.5 rounded-[11px] px-3.5 py-[11px] text-[13px]" style={{ background: '#eef2e3', color: '#3a5a1f' }}>
                            <span className="grid h-[22px] w-[22px] flex-none place-items-center rounded-[6px] bg-grass text-[11px] font-bold text-white">COD</span>
                            <span>Thanh toán tiền mặt khi nhận đồ. Tiền cọc hoàn lại đầy đủ khi trả đủ và nguyên vẹn.</span>
                        </div>
                        <button onClick={submit} disabled={!canSubmit}
                            className="h-[52px] w-full rounded-control text-[16px] font-bold text-white transition disabled:cursor-not-allowed"
                            style={{ background: canSubmit ? '#557A2B' : '#c4cfae' }}>
                            {processing ? 'Đang xử lý…' : 'Đặt giữ chỗ · COD'}
                        </button>
                        {!canSubmit && !processing && (
                            <div className="mt-2 text-center text-[12px] text-[#8a967a]">Điền tên, số điện thoại và địa chỉ để đặt đơn.</div>
                        )}
                    </div>
                </div>
            </main>
        </>
    );
}

function toCheckoutItems(lines: CartLine[]): CheckoutItem[] {
    return lines.map((l) => ({ product_id: l.id, quantity: l.qty, start: l.start, end: l.end }));
}

function Row({ k, v, mono, accent, accentWarm }: { k: string; v: string; mono?: boolean; accent?: boolean; accentWarm?: boolean }) {
    return (
        <div className="flex justify-between py-[5px] text-[14px]">
            <span className="text-moss">{k}</span>
            <span className={`${mono ? 'font-mono' : ''} ${accent ? 'font-bold' : 'font-semibold'}`}
                style={{ color: accent ? '#557A2B' : accentWarm ? '#C97B36' : '#18230F' }}>{v}</span>
        </div>
    );
}

Cart.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
