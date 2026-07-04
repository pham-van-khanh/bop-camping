import { Head, Link, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { ChangeEvent, ReactNode, useEffect, useMemo, useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import { COMBO_GRAD } from '@/Components/site/ComboCard';
import {
    addLine, cartHasLocationConflict, cartTotals, clearCart, getCart, isComboLine, lineDays, lineDeposit,
    lineRent, locationConflict as checkLocationConflict, removeLine, setCart, setQty,
    type CartLine, type CartLocation,
} from '@/lib/cart';
import { money, rangeText } from '@/lib/format';
import { emit, on, EVENTS } from '@/lib/bus';
import { estimateDiscount, voucherValueText, type AvailableVoucher, type PromoInfo } from '@/lib/voucher';
import type { PageProps } from '@/types';

type CheckoutItem = { product_id: number; quantity: number; start: string; end: string };
type CheckoutCombo = { combo_id: number; quantity: number; start: string; end: string };

// Gợi ý từ cart combo detection (PRD 5.4) — server trả tối đa 1 gợi ý.
type Suggestion = {
    type: 'exact' | 'superset' | 'upsell';
    savings: number;
    savings_total: number;
    days: number;
    start: string;
    end: string;
    combo: {
        id: number;
        name: string;
        slug: string;
        combo_price: number;
        deposit: number;
        sum_individual: number;
        items: { product_id: number; name: string; qty: number }[];
        locations: CartLocation[];
    };
    missing: {
        product_id: number;
        name: string;
        qty: number;
        price_per_day: number;
        deposit: number;
        category_slug: string;
        locations: CartLocation[];
    }[];
};

// Dữ liệu mới nhất của sản phẩm/combo trả về từ /gio-thue/lam-tuoi.
type FreshProduct = { name: string; price_per_day: number; deposit: number; locations: CartLocation[]; all_locations: boolean };
type FreshCombo = {
    name: string;
    combo_price: number;
    deposit: number;
    items: { name: string; qty: number }[];
    locations: CartLocation[];
    all_locations: boolean;
};

type Props = PageProps<{
    availableVouchers: AvailableVoucher[];
    referralRef: string;
    firstOrderEligible: boolean;
    promo: PromoInfo;
}>;

export default function Cart() {
    const { auth, flash, availableVouchers, referralRef, firstOrderEligible, promo } = usePage<Props>().props;
    const user = auth.user;
    const promoOn = !!user && promo.enabled;

    const [lines, setLines] = useState<CartLine[]>([]);
    const [selectedCodes, setSelectedCodes] = useState<string[]>([]);
    const [manualCode, setManualCode] = useState('');
    const [suggestion, setSuggestion] = useState<Suggestion | null>(null);
    // Xác nhận điều khoản (checkout-thue-do-camping.md mục 6) — 1 checkbox gộp + popup tóm tắt.
    const [agreed, setAgreed] = useState(false);
    const [policyModal, setPolicyModal] = useState<'terms' | 'deposit' | null>(null);
    // bopcamping-80c (ADR-5): tồn kho theo khoảng ngày từng dòng, key "p|c:id:start:end"
    const [stockMap, setStockMap] = useState<Record<string, number>>({});

    // Email tạm @bopcamping.local (chưa verify) không dùng để prefill.
    const userEmail = user?.email && !user.email.endsWith('@bopcamping.local') ? user.email : '';

    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        phone: string;
        email: string;
        address: string;
        note: string;
        referral_code: string;
        voucher_codes: string[];
        items: CheckoutItem[];
        combos: CheckoutCombo[];
    }>({
        name: user?.name ?? '',
        phone: user?.phone ?? '',
        email: userEmail,
        address: '',
        note: '',
        referral_code: firstOrderEligible ? (referralRef ?? '') : '',
        voucher_codes: [],
        items: [],
        combos: [],
    });

    // Đồng bộ form với giỏ: tách dòng lẻ → items, dòng combo → combos (payload checkout).
    const syncForm = (cartLines: CartLine[]) => {
        setData((prev) => ({
            ...prev,
            items: toCheckoutItems(cartLines),
            combos: toCheckoutCombos(cartLines),
        }));
    };

    useEffect(() => {
        const current = getCart();
        setLines(current);
        syncForm(current);
        const off = on(EVENTS.cartChange, () => {
            const updated = getCart();
            setLines(updated);
            syncForm(updated);
        });

        // Làm tươi giỏ: giá/vị trí lưu ở localStorage có thể đã cũ (admin đổi sau khi thêm).
        if (current.length > 0) {
            const qs = current
                .map((l) => (isComboLine(l) ? `combo_ids[]=${l.id}` : `ids[]=${l.id}`))
                .join('&');
            fetch(`${route('cart.refresh')}?${qs}`, { headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : null))
                .then((j: { products?: Record<string, FreshProduct>; combos?: Record<string, FreshCombo> } | null) => {
                    if (!j?.products) return;
                    const fresh = j.products;
                    const freshCombos = j.combos ?? {};
                    const removed: string[] = [];
                    const next: CartLine[] = [];
                    for (const l of current) {
                        if (isComboLine(l)) {
                            const c = freshCombos[String(l.id)];
                            if (!c) { removed.push(l.name); continue; } // combo đã ẩn/xoá → gỡ
                            next.push({ ...l, name: c.name, price: c.combo_price, deposit: c.deposit, locations: c.locations, comboItems: c.items });
                            continue;
                        }
                        const p = fresh[String(l.id)];
                        if (!p) { removed.push(l.name); continue; } // đã ẩn/xoá → gỡ
                        next.push({ ...l, name: p.name, price: p.price_per_day, deposit: p.deposit, locations: p.locations });
                    }
                    setCart(next); // emit cartChange → listener cập nhật state + items
                    if (removed.length) {
                        emit(EVENTS.toast, `Đã gỡ ${removed.length} thiết bị không còn cho thuê khỏi giỏ.`);
                    }
                })
                .catch(() => {});
        }

        return off;
    }, []);

    useEffect(() => {
        if (flash.order_code) clearCart();
    }, [flash.order_code]);

    // Giữ voucher_codes của form đồng bộ với lựa chọn.
    useEffect(() => setData('voucher_codes', selectedCodes), [selectedCodes]);

    // Re-check tồn kho từng dòng mỗi khi giỏ đổi (bopcamping-80c) — báo hết hàng
    // ngay trong giỏ, không đợi tới checkout.
    useEffect(() => {
        if (lines.length === 0 || flash.order_code) {
            setStockMap({});
            return;
        }
        const t = setTimeout(() => {
            const qs = lines
                .map((l) => `${isComboLine(l) ? 'cr' : 'pr'}[]=${l.id}:${l.start}:${l.end}`)
                .join('&');
            fetch(`${route('cart.refresh')}?${qs}`, { headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : null))
                .then((j: { stock?: Record<string, number> } | null) => {
                    if (j?.stock) setStockMap(j.stock);
                })
                .catch(() => {});
        }, 400);
        return () => clearTimeout(t);
    }, [lines, flash.order_code]);

    /** Tồn kho của dòng trong khoảng ngày của nó — null khi chưa có dữ liệu. */
    const lineStock = (l: CartLine): number | null =>
        stockMap[`${isComboLine(l) ? 'c' : 'p'}:${l.id}:${l.start}:${l.end}`] ?? null;

    // Cart combo detection (PRD 5.4): chạy lại MỖI khi giỏ/voucher đổi (debounce nhẹ).
    // Voucher tham gia vì "convert phải rẻ hơn sau voucher" quyết định có gợi ý hay không.
    useEffect(() => {
        if (lines.length === 0 || flash.order_code) {
            setSuggestion(null);
            return;
        }
        const t = setTimeout(() => {
            axios.post<{ suggestion: Suggestion | null }>(route('cart.suggestion'), {
                items: toCheckoutItems(lines),
                combos: toCheckoutCombos(lines),
                voucher_codes: selectedCodes,
            })
                .then((r) => setSuggestion(r.data.suggestion ?? null))
                .catch(() => setSuggestion(null));
        }, 350);
        return () => clearTimeout(t);
    }, [lines, selectedCodes, flash.order_code]);

    /** Convert 1 click (exact/superset): trừ các món khớp, thêm 1 dòng combo — giữ nguyên khoảng ngày. */
    const convertToCombo = () => {
        if (!suggestion || suggestion.type === 'upsell') return;
        const { combo, start, end } = suggestion;

        const need = new Map(combo.items.map((ci) => [ci.product_id, ci.qty]));
        const next: CartLine[] = [];
        for (const l of lines) {
            if (isComboLine(l) || l.start !== start || l.end !== end) {
                next.push(l);
                continue;
            }
            const n = need.get(l.id) ?? 0;
            const take = Math.min(n, l.qty);
            if (take > 0) need.set(l.id, n - take);
            if (l.qty - take > 0) next.push({ ...l, qty: l.qty - take });
        }
        // Giỏ đã đổi giữa chừng, không còn đủ món → thôi, đợi detection chạy lại
        if ([...need.values()].some((v) => v > 0)) return;

        if (checkLocationConflict(combo.locations, next).conflict) {
            emit(EVENTS.toast, 'Combo không phục vụ tại vị trí của giỏ hiện tại.');
            return;
        }

        setCart([...next, {
            id: combo.id,
            name: combo.name,
            cat: 'combo',
            grad: COMBO_GRAD,
            price: combo.combo_price,
            deposit: combo.deposit,
            qty: 1,
            start,
            end,
            locations: combo.locations,
            kind: 'combo',
            comboItems: combo.items.map((ci) => ({ name: ci.name, qty: ci.qty })),
        }]);
        axios.post(route('cart.suggestion.converted'), { combo_id: combo.id, suggestion_type: suggestion.type }).catch(() => {});
        emit(EVENTS.toast, `Đã chuyển thành ${combo.name} — tiết kiệm ${money(suggestion.savings_total)}`);
    };

    /** Upsell: thêm nhanh món thiếu (giá lẻ) — detection sẽ chạy lại và banner thành "khớp đủ". */
    const quickAddMissing = () => {
        if (!suggestion || suggestion.type !== 'upsell') return;
        for (const m of suggestion.missing) {
            if (checkLocationConflict(m.locations).conflict) {
                emit(EVENTS.toast, `${m.name} không phục vụ tại vị trí của giỏ hiện tại.`);
                return;
            }
        }
        for (const m of suggestion.missing) {
            addLine({
                id: m.product_id,
                name: m.name,
                cat: m.category_slug,
                grad: 'linear-gradient(150deg,#4a6741,#7a9b6b)',
                price: m.price_per_day,
                deposit: m.deposit,
                qty: m.qty,
                start: suggestion.start,
                end: suggestion.end,
                locations: m.locations,
            });
        }
        axios.post(route('cart.suggestion.converted'), { combo_id: suggestion.combo.id, suggestion_type: 'upsell' }).catch(() => {});
        emit(EVENTS.toast, `Đã thêm ${suggestion.missing.map((m) => m.name).join(', ')} vào giỏ`);
    };

    const totals = cartTotals(lines);

    const toggleVoucher = (code: string) => {
        setSelectedCodes((prev) => {
            if (prev.includes(code)) return prev.filter((c) => c !== code);
            if (prev.length >= promo.maxStack) return prev; // chặn vượt trần stack
            return [...prev, code];
        });
    };

    const addManual = () => {
        const code = manualCode.trim().toUpperCase();
        if (code && !selectedCodes.includes(code) && selectedCodes.length < promo.maxStack) {
            setSelectedCodes((prev) => [...prev, code]);
        }
        setManualCode('');
    };

    const refereeValue = useMemo(() => {
        if (!promoOn || !firstOrderEligible || !data.referral_code.trim()) return null;
        return promo.refereeDiscountType === 'percent'
            ? Math.floor((totals.rent * promo.refereeDiscountValue) / 100)
            : Math.round(promo.refereeDiscountValue);
    }, [promoOn, firstOrderEligible, data.referral_code, promo, totals.rent]);

    const selectedVouchers = useMemo(
        () => availableVouchers.filter((v) => selectedCodes.includes(v.code)),
        [availableVouchers, selectedCodes],
    );

    const estimate = useMemo(
        () => estimateDiscount({ rentalTotal: totals.rent, promo, refereeValue, selectedVouchers }),
        [totals.rent, promo, refereeValue, selectedVouchers],
    );

    // "Tổng tiền thuê" = phí thuê sau ưu đãi; "Tổng cần thanh toán" = + cọc (COD khi nhận).
    const rentalAfterDiscount = Math.max(0, totals.rent - estimate.total);
    const payable = rentalAfterDiscount + totals.deposit;

    // Sau khi làm tươi, giỏ có thể không còn cùng 1 vị trí (admin đổi vị trí sản phẩm) → chặn đặt.
    const locationConflict = useMemo(() => cartHasLocationConflict(lines), [lines]);

    const canSubmit = data.name.trim().length >= 2
        && data.phone.trim().length >= 8
        && data.address.trim().length >= 4
        && lines.length > 0
        && !locationConflict
        && agreed
        && !processing;

    /** Gợi ý dưới nút khi chưa đặt được — theo thứ tự ưu tiên. */
    const submitHint = locationConflict
        ? 'Giỏ đang có thiết bị khác vị trí — gỡ bớt để đặt đơn.'
        : (data.name.trim().length < 2 || data.phone.trim().length < 8 || data.address.trim().length < 4)
          ? 'Điền tên, số điện thoại và địa chỉ để đặt đơn.'
          : !agreed
            ? 'Tick xác nhận điều khoản để đặt đơn.'
            : '';

    const set = (k: 'name' | 'phone' | 'email' | 'address' | 'note') =>
        (e: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => setData(k, e.target.value);

    const submit = () => post(route('order.store'));

    // ----- Đặt thành công -----
    if (flash.order_code) {
        return (
            <>
                <Head title="Đã gửi yêu cầu thuê" />
                <main className="mx-auto max-w-[1060px] px-5 pb-12 pt-[30px]">
                    <div className="mx-auto mt-2.5 max-w-[600px] rounded-[20px] border border-cardBorder bg-card text-center" style={{ padding: 'clamp(28px,4vw,44px)' }}>
                        <div className="mx-auto mb-[18px] grid h-[68px] w-[68px] place-items-center rounded-full" style={{ background: '#dcebc4' }}>
                            <svg width="34" height="34" viewBox="0 0 24 24" fill="none"><path d="m5 13 4 4L19 7" stroke="#3a5a1f" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" /></svg>
                        </div>
                        <h1 className="mb-2 text-[26px] font-extrabold text-ink">Đã gửi yêu cầu thuê!</h1>
                        <p className="mb-[18px] text-moss">Tụi mình sẽ gọi xác nhận trong 15–30 phút (giờ hành chính). Mã đơn của bạn:</p>
                        <div className="mb-[22px] inline-block rounded-control border border-dashed px-[26px] py-3 font-mono text-[24px] font-bold tracking-[0.1em] text-grass" style={{ background: '#eef2e3', borderColor: '#b9c79a' }}>
                            {flash.order_code}
                        </div>
                        <div className="mb-[22px] rounded-[13px] px-[18px] py-4 text-left" style={{ background: '#f6f8ef' }}>
                            <Row k="Khách" v={flash.order_name ?? ''} />
                            <Row k="Số loại thiết bị" v={`${flash.order_items} loại`} mono />
                            {(flash.order_discount ?? 0) > 0 && (
                                <Row k="Đã giảm" v={`−${money(flash.order_discount ?? 0)}`} mono accentWarm />
                            )}
                            <Row k="Trả khi nhận (COD)" v={money(flash.order_pay ?? 0)} mono accent />
                        </div>
                        <div className="flex flex-wrap justify-center gap-2.5">
                            <Link
                                href={`/tra-cuu?code=${encodeURIComponent(flash.order_code ?? '')}&phone=${encodeURIComponent(flash.order_phone ?? '')}`}
                                className="grid h-12 place-items-center rounded-control bg-grass px-[22px] font-bold text-white"
                            >
                                Theo dõi đơn này
                            </Link>
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
    const inputCls = 'h-[46px] w-full rounded-[11px] border bg-white px-3.5 text-[15px] text-ink outline-none focus:border-grass';

    return (
        <>
            <Head title="Giỏ thuê" />
            <main className="mx-auto max-w-[1060px] px-5 pb-12 pt-[30px]">
                <h1 className="mb-[22px] font-extrabold tracking-tight text-ink" style={{ fontSize: 'clamp(24px,3vw,32px)' }}>Giỏ thuê</h1>

                {errors.items && (
                    <div className="mb-4 rounded-[12px] border border-red-200 bg-red-50 px-4 py-3 text-[14px] text-red-700">{errors.items}</div>
                )}

                {locationConflict && (
                    <div className="mb-4 flex items-start gap-2.5 rounded-[12px] border border-[#e7c9a3] bg-[#fbf2e6] px-4 py-3 text-[13.5px] text-[#8a5a1f]">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" className="mt-0.5 flex-none">
                            <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z" fill="#C97B36" stroke="#C97B36" strokeWidth="1.5" strokeLinejoin="round" />
                            <circle cx="12" cy="10" r="2.4" fill="#fff" />
                        </svg>
                        <span>Một số thiết bị đã đổi vị trí phục vụ nên giỏ không còn cùng một vị trí. Mỗi đơn chỉ thuê tại một vị trí — vui lòng gỡ bớt thiết bị không phù hợp để đặt đơn.</span>
                    </div>
                )}

                {/* Banner cart combo detection (PRD 5.4): exact/superset → convert 1 click; upsell → thêm nhanh */}
                {suggestion && (
                    <div className="mb-4 flex flex-wrap items-center gap-3 rounded-[14px] px-4 py-3.5 text-white" style={{ background: COMBO_GRAD }}>
                        <span className="rounded-pill bg-white/20 px-2.5 py-1 font-mono text-[11px] font-bold tracking-[0.06em]">COMBO</span>
                        <span className="min-w-[220px] flex-1 text-[13.5px] leading-[1.5]">
                            {suggestion.type === 'upsell' ? (
                                <>
                                    Thêm <b>{suggestion.missing.map((m) => `${m.qty}× ${m.name}`).join(', ')}</b> nữa là thành{' '}
                                    <Link href={`/combos/${suggestion.combo.slug}`} className="font-bold underline decoration-white/50 underline-offset-2">{suggestion.combo.name}</Link>
                                    , rẻ hơn <b className="font-mono">{money(suggestion.savings_total)}</b> cho {suggestion.days} ngày
                                </>
                            ) : (
                                <>
                                    Giỏ của bạn khớp{' '}
                                    <Link href={`/combos/${suggestion.combo.slug}`} className="font-bold underline decoration-white/50 underline-offset-2">{suggestion.combo.name}</Link>
                                    {' '}— tiết kiệm <b className="font-mono">{money(suggestion.savings_total)}</b>
                                    {suggestion.type === 'superset' && <span className="text-white/85"> (các món ngoài combo giữ nguyên thuê lẻ)</span>}
                                </>
                            )}
                        </span>
                        <button
                            onClick={suggestion.type === 'upsell' ? quickAddMissing : convertToCombo}
                            className="h-[38px] whitespace-nowrap rounded-control bg-white px-4 text-[13px] font-bold text-pine transition hover:bg-[#f1f4ea]"
                        >
                            {suggestion.type === 'upsell' ? 'Thêm nhanh' : 'Chuyển thành combo'}
                        </button>
                    </div>
                )}

                <div className="grid items-start gap-6" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))' }}>
                    {/* Danh sách món */}
                    <div>
                        {lines.map((it, i) => (
                            <div key={`${it.kind ?? 'product'}-${it.id}-${it.start}-${it.end}`} className="mb-3 flex gap-3.5 rounded-[14px] border border-cardBorder bg-card p-[13px]">
                                <div className="h-16 w-16 flex-none rounded-[10px]" style={{ background: it.grad }} />
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        {isComboLine(it) && (
                                            <span className="rounded-pill bg-grass px-2 py-0.5 font-mono text-[10px] font-bold text-white">COMBO</span>
                                        )}
                                        <div className="text-[15px] font-bold leading-[1.25] text-ink">{it.name}</div>
                                    </div>
                                    <div className="my-1 font-mono text-[12px] text-[#8a967a]">{rangeText(it.start, it.end)} · {lineDays(it)} ngày</div>
                                    {/* bopcamping-80c: cảnh báo hết hàng theo khoảng ngày ngay trong giỏ */}
                                    {(() => {
                                        const avail = lineStock(it);
                                        if (avail === null || avail >= it.qty) return null;
                                        return (
                                            <div className="mb-1.5 rounded-[8px] px-2.5 py-1.5 text-[12px] font-semibold" style={{ background: '#f6ddd6', color: '#b3493a' }}>
                                                {avail === 0
                                                    ? (isComboLine(it)
                                                        ? 'Combo đã hết trong khoảng này — chọn ngày khác hoặc xoá khỏi giỏ.'
                                                        : 'Món này đã được thuê hết trong khoảng này — chọn ngày khác hoặc xoá khỏi giỏ.')
                                                    : `Khoảng này chỉ còn ${avail} bộ — giảm số lượng giúp mình nhé.`}
                                            </div>
                                        );
                                    })()}
                                    {/* Combo: khối mở rộng xem các món con (PRD combo mục 6) */}
                                    {isComboLine(it) && (it.comboItems?.length ?? 0) > 0 && (
                                        <details className="mb-1.5">
                                            <summary className="cursor-pointer select-none text-[12.5px] font-semibold text-grass">
                                                Gồm {it.comboItems!.length} món — xem chi tiết
                                            </summary>
                                            <ul className="mt-1 rounded-[9px] px-3 py-2 text-[12.5px] text-moss" style={{ background: '#f6f8ef' }}>
                                                {it.comboItems!.map((ci, k) => (
                                                    <li key={k}>· {ci.qty}× {ci.name}</li>
                                                ))}
                                                {/* Đã duyệt với chủ shop: combo là khối nguyên vẹn, không xoá lẻ món con */}
                                                <li className="mt-1 text-[11.5px] italic" style={{ color: '#a3ad92' }}>
                                                    Combo là bộ cố định — muốn đổi món, xoá combo và thêm lẻ từng món.
                                                </li>
                                            </ul>
                                        </details>
                                    )}
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
                        <div className="mt-1 flex flex-wrap gap-4">
                            <Link href="/thiet-bi" className="inline-block text-[14px] font-bold text-grass">+ Thêm thiết bị khác</Link>
                            <Link href="/combos" className="inline-block text-[14px] font-bold text-grass">+ Xem combo tiết kiệm</Link>
                        </div>

                        {/* Sau khi đặt yêu cầu (design doc mục 4) */}
                        <div className="mt-5 rounded-[14px] border border-cardBorder bg-card p-4">
                            <div className="mb-2.5 text-[13px] font-bold text-ink">Sau khi đặt yêu cầu</div>
                            <ol className="flex flex-col gap-2">
                                {[
                                    'Tụi mình gọi điện xác nhận thông tin đơn thuê.',
                                    'Chốt thời gian và địa điểm nhận – trả thiết bị.',
                                    'Báo tổng chi phí (gồm phí giao nhận nếu có).',
                                    'Chuẩn bị và bàn giao thiết bị theo lịch hẹn.',
                                ].map((step, i) => (
                                    <li key={i} className="flex items-start gap-2.5 text-[13px] leading-[1.5] text-[#3f4a32]">
                                        <span className="mt-px grid h-[20px] w-[20px] flex-none place-items-center rounded-full bg-[#eef2e3] font-mono text-[11px] font-bold text-grass">{i + 1}</span>
                                        {step}
                                    </li>
                                ))}
                            </ol>
                        </div>

                        {/* Cam kết (design doc mục 5) */}
                        <div className="mt-3 rounded-[14px] border border-cardBorder bg-card p-4">
                            <div className="mb-2.5 text-[13px] font-bold text-ink">Cam kết của BỐP</div>
                            <div className="grid gap-x-4 gap-y-1.5" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))' }}>
                                {[
                                    'Vệ sinh sạch sẽ trước khi bàn giao',
                                    'Kiểm tra kỹ từng thiết bị trước khi giao',
                                    'Giao đầy đủ phụ kiện theo sản phẩm',
                                    'Hỗ trợ trong suốt thời gian thuê',
                                ].map((c, i) => (
                                    <div key={i} className="flex items-start gap-2 text-[12.5px] leading-[1.5] text-[#3f4a32]">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" className="mt-0.5 flex-none">
                                            <circle cx="12" cy="12" r="10" fill="#dcebc4" />
                                            <path d="m8 12.5 2.6 2.6L16 9.5" stroke="#3a5a1f" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                        </svg>
                                        {c}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Checkout */}
                    <div className="sticky top-[84px] rounded-card border border-cardBorder bg-card p-5">
                        <div className="mb-3.5 text-[16px] font-bold text-ink">Thông tin nhận đồ</div>
                        <div className="mb-4 flex flex-col gap-[11px]">
                            <div>
                                <input value={data.name} onChange={set('name')} placeholder="Họ và tên"
                                    className={`${inputCls} ${errors.name ? 'border-red-400' : 'border-cardBorder'}`} />
                                {errors.name && <p className="mt-1 text-[12px] text-red-500">{errors.name}</p>}
                            </div>
                            <div>
                                <input value={data.phone} onChange={set('phone')} placeholder="Số điện thoại" inputMode="tel"
                                    className={`${inputCls} ${errors.phone ? 'border-red-400' : 'border-cardBorder'}`} />
                                {errors.phone && <p className="mt-1 text-[12px] text-red-500">{errors.phone}</p>}
                            </div>
                            <div>
                                <input value={data.email} onChange={set('email')} placeholder="Email (không bắt buộc)" inputMode="email"
                                    className={`${inputCls} ${errors.email ? 'border-red-400' : 'border-cardBorder'}`} />
                                {errors.email
                                    ? <p className="mt-1 text-[12px] text-red-500">{errors.email}</p>
                                    : <p className="mt-1 text-[11.5px] text-[#a3ad92]">
                                        {userEmail ? 'Bỏ trống sẽ dùng email tài khoản của bạn.' : 'Để nhận email xác nhận đơn thuê.'}
                                    </p>}
                            </div>
                            <div>
                                <input value={data.address} onChange={set('address')} placeholder="Địa chỉ giao nhận"
                                    className={`${inputCls} ${errors.address ? 'border-red-400' : 'border-cardBorder'}`} />
                                {errors.address && <p className="mt-1 text-[12px] text-red-500">{errors.address}</p>}
                            </div>
                            <textarea value={data.note} onChange={set('note')} placeholder="Ghi chú (tuỳ chọn)" rows={2}
                                className="resize-y rounded-[11px] border border-cardBorder bg-white px-3.5 py-[11px] text-[14px] text-ink outline-none focus:border-grass" />
                        </div>

                        {/* Khuyến mãi */}
                        {!user && (
                            <p className="mb-3.5 text-[13px] text-moss">Đăng nhập để dùng mã giới thiệu và voucher của bạn.</p>
                        )}
                        {promoOn && firstOrderEligible && (
                            <div className="mb-3">
                                <label className="mb-1 block text-[11px] font-bold uppercase tracking-[0.05em] text-[#8a967a]">Mã giới thiệu (đơn đầu)</label>
                                <input value={data.referral_code} onChange={(e) => setData('referral_code', e.target.value.toUpperCase())}
                                    placeholder="VD: TOM7K3X"
                                    className={`${inputCls} font-mono uppercase tracking-[0.04em] ${errors.referral_code ? 'border-red-400' : 'border-cardBorder'}`} />
                                {errors.referral_code && <p className="mt-1 text-[12px] text-red-500">{errors.referral_code}</p>}
                            </div>
                        )}
                        {promoOn && availableVouchers.length > 0 && (
                            <div className="mb-3">
                                <div className="mb-1.5 text-[11px] font-bold uppercase tracking-[0.05em] text-[#8a967a]">
                                    Voucher của bạn (tối đa {promo.maxStack})
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    {availableVouchers.map((v) => {
                                        const checked = selectedCodes.includes(v.code);
                                        const disabled = !checked && selectedCodes.length >= promo.maxStack;
                                        return (
                                            <label key={v.code}
                                                className={`flex items-center justify-between gap-2 rounded-[10px] border px-3 py-2 text-[13px] ${checked ? 'border-grass bg-[#eef2e3]' : 'border-cardBorder'} ${disabled ? 'opacity-50' : 'cursor-pointer'}`}>
                                                <span className="flex items-center gap-2">
                                                    <input type="checkbox" checked={checked} disabled={disabled} onChange={() => toggleVoucher(v.code)} className="accent-grass" />
                                                    <span className="font-mono font-bold text-pine">{v.code}</span>
                                                </span>
                                                <span className="font-semibold text-grass">{voucherValueText(v.type, v.value)}</span>
                                            </label>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                        {promoOn && (
                            <div className="mb-4 flex gap-2">
                                <input value={manualCode} onChange={(e) => setManualCode(e.target.value.toUpperCase())}
                                    onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), addManual())}
                                    placeholder="Nhập mã voucher khác"
                                    className={`${inputCls} font-mono uppercase tracking-[0.04em] border-cardBorder`} />
                                <button type="button" onClick={addManual}
                                    className="h-[46px] shrink-0 rounded-[11px] border border-grass px-3 text-[14px] font-bold text-grass">Áp dụng</button>
                            </div>
                        )}

                        {/* Chi tiết thanh toán (checkout-thue-do-camping.md mục 2) */}
                        <div className="border-t border-cardBorder pt-3.5">
                            <div className="mb-1.5 text-[11px] font-bold uppercase tracking-[0.05em] text-[#8a967a]">Chi tiết thanh toán</div>
                            <Row k="Phí thuê" v={money(totals.rent)} mono />
                            {estimate.lines.map((l, i) => (
                                <Row key={i} k={l.label} v={`−${money(l.amount)}`} mono accentWarm />
                            ))}
                            {estimate.capped && (
                                <p className="py-1 text-right text-[11px] text-[#a3ad92]">Đã áp mức giảm tối đa cho đơn này</p>
                            )}
                            <div className="flex justify-between border-t border-dashed py-[7px] text-[14px]" style={{ borderColor: '#e3e8d6' }}>
                                <span className="font-semibold text-ink">Tổng tiền thuê</span>
                                <span className="font-mono font-bold text-ink">{money(rentalAfterDiscount)}</span>
                            </div>
                            <div className="flex justify-between py-[5px] text-[14px]">
                                <span className="inline-flex items-center gap-1.5 text-moss">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" className="flex-none">
                                        <path d="M12 3 5 5.5v5.2c0 4.6 3 8.4 7 9.8 4-1.4 7-5.2 7-9.8V5.5L12 3Z" fill="#C97B36" opacity=".9" />
                                        <path d="m9.2 11.8 2 2 3.6-4" stroke="#fff" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                    Tiền cọc (hoàn lại)
                                </span>
                                <span className="font-mono font-semibold text-campfire">{money(totals.deposit)}</span>
                            </div>
                            <p className="mb-1 pl-[19px] text-[11.5px] leading-[1.45] text-[#a3ad92]">Hoàn lại sau khi trả hàng và kiểm tra thiết bị.</p>
                            <div className="mt-1.5 flex items-baseline justify-between border-t border-dashed pt-2.5" style={{ borderColor: '#d6ddc4' }}>
                                <span className="text-[15px] font-extrabold text-ink">Tổng cần thanh toán</span>
                                <span className="font-mono text-[19px] font-bold text-grass">{money(payable)}</span>
                            </div>
                            <p className="mt-0.5 text-right text-[11.5px] text-[#8a967a]">
                                Tiền mặt (COD) khi nhận đồ{estimate.total > 0 ? ' · giảm là tạm tính, chốt khi đặt đơn' : ''}
                            </p>
                        </div>

                        {/* Lưu ý (rút gọn từ design doc mục 3) */}
                        <ul className="my-3.5 flex flex-col gap-1 rounded-[11px] px-3.5 py-[11px] text-[12.5px] leading-[1.5]" style={{ background: '#eef2e3', color: '#3a5a1f' }}>
                            <li>· Phí giao nhận (nếu có) sẽ được báo khi gọi xác nhận đơn.</li>
                            <li>· Cần gia hạn thời gian thuê, liên hệ trước thời gian trả hàng.</li>
                        </ul>
                        {/* Xác nhận điều khoản (1 checkbox gộp, mở popup tóm tắt) */}
                        <label className="mb-3 flex cursor-pointer items-start gap-2.5 text-[12.5px] leading-[1.55] text-[#3f4a32]">
                            <input type="checkbox" checked={agreed} onChange={(e) => setAgreed(e.target.checked)}
                                className="mt-0.5 h-4 w-4 flex-none accent-grass" />
                            <span>
                                Tôi đồng ý với{' '}
                                <button type="button" onClick={(e) => { e.preventDefault(); setPolicyModal('terms'); }}
                                    className="font-semibold text-grass underline underline-offset-2">Điều khoản thuê</button>
                                {' '}và{' '}
                                <button type="button" onClick={(e) => { e.preventDefault(); setPolicyModal('deposit'); }}
                                    className="font-semibold text-grass underline underline-offset-2">Chính sách hoàn cọc</button>
                                , và hiểu rằng đơn thuê chỉ được xác nhận sau khi nhân viên liên hệ qua điện thoại.
                            </span>
                        </label>
                        <button onClick={submit} disabled={!canSubmit}
                            className="h-[52px] w-full rounded-control text-[16px] font-bold text-white transition disabled:cursor-not-allowed"
                            style={{ background: canSubmit ? '#557A2B' : '#c4cfae' }}>
                            {processing ? 'Đang xử lý…' : 'Đặt yêu cầu thuê'}
                        </button>
                        <div className="mt-2 text-center text-[12px] text-[#8a967a]">
                            {!canSubmit && !processing
                                ? submitHint
                                : 'Tụi mình sẽ gọi xác nhận trong 15–30 phút (giờ hành chính).'}
                        </div>
                    </div>
                </div>
            </main>

            {/* Popup tóm tắt Điều khoản thuê / Chính sách hoàn cọc */}
            {policyModal && (
                <div className="fixed inset-0 z-[200] flex items-center justify-center bg-black/45 px-4" onClick={() => setPolicyModal(null)}>
                    <div className="max-h-[80vh] w-full max-w-[440px] overflow-y-auto rounded-[18px] bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h2 className="mb-3 text-[17px] font-extrabold text-ink">
                            {policyModal === 'terms' ? 'Điều khoản thuê (tóm tắt)' : 'Chính sách hoàn cọc (tóm tắt)'}
                        </h2>
                        <ul className="mb-5 flex flex-col gap-2 text-[13.5px] leading-[1.6] text-[#3f4a32]">
                            {(policyModal === 'terms'
                                ? [
                                    'Giá thuê tính theo ngày, bao gồm cả ngày nhận và ngày trả.',
                                    'Khách kiểm tra thiết bị khi nhận; hỏng hóc, mất mát phát sinh trong thời gian thuê do khách chịu trách nhiệm.',
                                    'Trả thiết bị đúng hẹn. Cần gia hạn, vui lòng liên hệ trước thời gian trả — phí gia hạn tính theo ngày.',
                                    'Thanh toán tiền mặt (COD) khi nhận đồ. Phí giao nhận (nếu có) báo khi gọi xác nhận.',
                                ]
                                : [
                                    'Tiền cọc được hoàn đầy đủ sau khi trả hàng và kiểm tra thiết bị nguyên vẹn.',
                                    'Thiết bị hỏng hoặc thiếu phụ kiện: trừ chi phí sửa chữa / thay thế vào tiền cọc theo thoả thuận khi kiểm tra.',
                                    'Hoàn cọc bằng tiền mặt ngay khi nhận lại đồ, hoặc chuyển khoản trong vòng 24h.',
                                ]
                            ).map((t, i) => (
                                <li key={i} className="flex items-start gap-2">
                                    <span className="mt-[7px] h-1.5 w-1.5 flex-none rounded-full bg-grass" />
                                    {t}
                                </li>
                            ))}
                        </ul>
                        <button onClick={() => setPolicyModal(null)}
                            className="h-[44px] w-full rounded-control bg-grass text-[14px] font-bold text-white transition hover:bg-pine">
                            Đã hiểu
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}

function toCheckoutItems(lines: CartLine[]): CheckoutItem[] {
    return lines
        .filter((l) => !isComboLine(l))
        .map((l) => ({ product_id: l.id, quantity: l.qty, start: l.start, end: l.end }));
}

function toCheckoutCombos(lines: CartLine[]): CheckoutCombo[] {
    return lines
        .filter(isComboLine)
        .map((l) => ({ combo_id: l.id, quantity: l.qty, start: l.start, end: l.end }));
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
