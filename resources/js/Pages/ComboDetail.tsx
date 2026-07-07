import { Head, Link } from '@inertiajs/react';
import { ReactNode, useEffect, useMemo, useRef, useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import DateRangeCalendar from '@/Components/site/DateRangeCalendar';
import { COMBO_GRAD } from '@/Components/site/ComboCard';
import { dayCount, ddmm, money, rangeText } from '@/lib/format';
import { addLine, clearCart, locationConflict, type CartLine, type CartLocation } from '@/lib/cart';
import { emit, EVENTS } from '@/lib/bus';

type ComboItemRow = {
    product_id: number;
    slug: string | null;
    name: string | null;
    quantity: number;
    price_per_day: number;
    thumbnail: string | null;
};

type ComboData = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    combo_price: number;
    deposit: number;
    suitable_for: number | null;
    sum_individual: number;
    savings_amount: number;
    savings_percent: number;
    items: ComboItemRow[];
    images: { url: string; type: 'image' | 'video' }[];
    locations: CartLocation[];
    all_locations: boolean;
};

// Kết quả check tồn kho realtime từ /combos/{slug}/kha-dung (Case 4)
type AvailabilityResult = {
    available: number;
    insufficient: { product_id: number; name: string; available: number; required: number }[];
    next_window: { start: string; end: string } | null;
    substitutes: { id: number; slug: string; name: string; price_per_day: number; thumbnail: string | null }[];
};

interface Props {
    combo: ComboData;
}

export default function ComboDetail({ combo }: Props) {
    const [activeImg, setActiveImg] = useState(0);
    const [lightboxOpen, setLightboxOpen] = useState(false);
    const [start, setStart] = useState<string | null>(null);
    const [end, setEnd] = useState<string | null>(null);
    const [qty, setQty] = useState(1);
    const [avail, setAvail] = useState<AvailabilityResult | null>(null);
    const [checking, setChecking] = useState(false);
    const [conflict, setConflict] = useState<{ pending: CartLine; cartLocations: CartLocation[] } | null>(null);
    // Chống race: chỉ nhận kết quả của lần fetch mới nhất
    const fetchSeq = useRef(0);

    const unavailable = useMemo(() => new Set<string>(), []);

    const gallery: ({ type: 'img'; src: string } | { type: 'video'; src: string } | { type: 'grad'; bg: string })[] = useMemo(() => {
        if (combo.images.length > 0) {
            return combo.images.map((img) => (img.type === 'video' ? { type: 'video' as const, src: img.url } : { type: 'img' as const, src: img.url }));
        }
        return [150, 35, 205, 330].map((a) => ({ type: 'grad' as const, bg: COMBO_GRAD.replace('150deg', `${a}deg`) }));
    }, [combo.images]);

    // Check tồn kho realtime khi chọn đủ khoảng ngày (PRD 5.5 / Case 4)
    useEffect(() => {
        if (!start || !end) {
            setAvail(null);
            return;
        }
        const seq = ++fetchSeq.current;
        setChecking(true);
        fetch(`/combos/${combo.slug}/kha-dung?start=${start}&end=${end}`, { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : null))
            .then((j: AvailabilityResult | null) => {
                if (seq !== fetchSeq.current) return;
                setAvail(j);
                if (j) setQty((q) => Math.max(1, Math.min(q, Math.max(1, j.available))));
            })
            .catch(() => seq === fetchSeq.current && setAvail(null))
            .finally(() => seq === fetchSeq.current && setChecking(false));
    }, [start, end, combo.slug]);

    const days = start && end ? dayCount(start, end) : 0;
    const canAdd = !!start && !!end && !checking && (avail?.available ?? 0) >= qty;
    const subtotal = combo.combo_price * qty * days;
    const subDeposit = combo.deposit * qty;
    const showAllBadge = combo.all_locations && combo.locations.length > 1;

    const buildLine = (): CartLine => ({
        id: combo.id,
        kind: 'combo',
        name: combo.name,
        cat: 'combo',
        grad: COMBO_GRAD,
        price: combo.combo_price,
        deposit: combo.deposit,
        qty,
        start: start as string,
        end: end as string,
        locations: combo.locations,
        comboItems: combo.items.map((it) => ({ name: it.name ?? `#${it.product_id}`, qty: it.quantity })),
    });

    const commitAdd = (line: CartLine) => {
        addLine(line);
        emit(EVENTS.toast, `Đã thêm ${combo.name} vào giỏ`);
    };

    const add = () => {
        if (!canAdd) return;
        const line = buildLine();
        const { conflict: hasConflict, cartLocations } = locationConflict(combo.locations);
        if (hasConflict) {
            setConflict({ pending: line, cartLocations });
            return;
        }
        commitAdd(line);
    };

    const replaceCart = () => {
        if (!conflict) return;
        clearCart();
        commitAdd(conflict.pending);
        setConflict(null);
    };

    const applyNextWindow = () => {
        if (!avail?.next_window) return;
        setStart(avail.next_window.start);
        setEnd(avail.next_window.end);
    };

    const activeSlide = gallery[activeImg] ?? gallery[0];
    const soldOut = !!start && !!end && !checking && (avail?.available ?? 0) === 0;

    return (
        <>
            <Head title={combo.name} />
            <main className="mx-auto max-w-[1120px] px-5 pb-12 pt-6">
                <Link href="/combos" className="mb-2.5 inline-block py-2 text-[14px] font-semibold text-moss hover:text-grass">← Tất cả combo</Link>
                <div className="grid items-start gap-[34px]" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))' }}>
                    {/* gallery */}
                    <div>
                        <div
                            className="relative h-[360px] overflow-hidden rounded-card"
                            style={activeSlide.type === 'grad' ? { background: activeSlide.bg } : { background: COMBO_GRAD }}
                        >
                            {activeSlide.type === 'img' && (
                                <img src={activeSlide.src} alt={combo.name} onClick={() => setLightboxOpen(true)} className="absolute inset-0 h-full w-full cursor-zoom-in object-cover" />
                            )}
                            {activeSlide.type === 'video' && (
                                <video key={activeSlide.src} src={activeSlide.src} controls autoPlay muted loop playsInline className="absolute inset-0 h-full w-full object-cover" />
                            )}
                            {(activeSlide.type === 'img' || activeSlide.type === 'video') && (
                                <button
                                    onClick={(e) => { e.stopPropagation(); setLightboxOpen(true); }}
                                    aria-label="Xem cỡ lớn"
                                    className="absolute right-3 top-3 z-10 grid h-9 w-9 place-items-center rounded-full bg-black/45 text-white transition hover:bg-black/65"
                                >
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                        <path d="M9 3H4a1 1 0 0 0-1 1v5M15 3h5a1 1 0 0 1 1 1v5M9 21H4a1 1 0 0 1-1-1v-5M15 21h5a1 1 0 0 0 1-1v-5" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                </button>
                            )}
                            <div className="pointer-events-none absolute inset-0" style={{ background: 'radial-gradient(220px 150px at 76% 20%, rgba(255,255,255,.3), transparent 60%)' }} />
                            <div className="pointer-events-none absolute inset-x-0 bottom-0 h-[90px]" style={{ background: 'linear-gradient(180deg,rgba(24,35,15,0),rgba(24,35,15,.4))' }} />
                            <span className="pointer-events-none absolute bottom-4 left-[18px] font-mono text-[13px] tracking-[0.06em] text-white">COMBO · {combo.items.length} MÓN</span>
                            {combo.savings_amount > 0 && (
                                <span className="absolute left-[18px] top-4 rounded-pill bg-campfire px-3 py-1.5 font-mono text-[12px] font-bold text-white">
                                    Tiết kiệm {combo.savings_percent}% so với thuê lẻ
                                </span>
                            )}
                        </div>
                        {gallery.length > 1 && (
                            <div className="mt-2.5 grid grid-cols-4 gap-2.5">
                                {gallery.map((g, i) => (
                                    <button
                                        key={i}
                                        onClick={() => setActiveImg(i)}
                                        aria-label={`Ảnh ${i + 1}`}
                                        className="relative h-[70px] overflow-hidden rounded-[11px] transition"
                                        style={{ outline: i === activeImg ? '2px solid #557A2B' : '1px solid #E3E8D6', outlineOffset: i === activeImg ? 1 : 0 }}
                                    >
                                        {g.type === 'img' ? (
                                            <img src={g.src} alt="" className="h-full w-full object-cover" />
                                        ) : g.type === 'video' ? (
                                            <>
                                                <video src={g.src} className="h-full w-full object-cover" muted />
                                                <span className="absolute inset-0 grid place-items-center bg-black/25 text-[10px] text-white">▶</span>
                                            </>
                                        ) : (
                                            <div className="h-full w-full" style={{ background: g.bg }} />
                                        )}
                                    </button>
                                ))}
                            </div>
                        )}

                        {/* Bộ này gồm gì — link về trang từng sản phẩm (US-01) */}
                        <div className="mt-5 rounded-[14px] border border-cardBorder bg-card p-4">
                            <div className="mb-3 text-[15px] font-bold text-ink">Bộ này gồm {combo.items.length} món</div>
                            <div className="flex flex-col gap-2">
                                {combo.items.map((it) => (
                                    <Link
                                        key={it.product_id}
                                        href={`/thiet-bi/${it.slug}`}
                                        className="flex items-center gap-3 rounded-[11px] border border-cardBorder bg-white px-3 py-2.5 transition hover:border-grass"
                                    >
                                        {it.thumbnail ? (
                                            <img src={it.thumbnail} alt="" className="h-11 w-11 flex-none rounded-[9px] object-cover" />
                                        ) : (
                                            <div className="grid h-11 w-11 flex-none place-items-center rounded-[9px] bg-[#f1f4ea] text-[17px]">⛺</div>
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate text-[14px] font-semibold text-ink">{it.name}</div>
                                            <div className="font-mono text-[12px] text-moss">{money(it.price_per_day)}/ngày · lẻ</div>
                                        </div>
                                        <span className="rounded-pill bg-[#eef2e3] px-2.5 py-1 font-mono text-[12px] font-bold text-grass">×{it.quantity}</span>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* info */}
                    <div>
                        <h1 className="mb-2.5 font-extrabold leading-[1.15] tracking-tight text-ink" style={{ fontSize: 'clamp(24px,3vw,30px)' }}>{combo.name}</h1>
                        <div className="mb-[18px] flex flex-wrap items-center gap-2.5">
                            {combo.suitable_for && (
                                <span className="rounded-pill px-2.5 py-1 text-[12px] font-semibold" style={{ background: '#e7eed5', color: '#3a5a1f' }}>
                                    Hợp cho {combo.suitable_for} người
                                </span>
                            )}
                            {combo.locations.length > 0 && (
                                <span className="inline-flex items-center gap-1.5 rounded-pill border border-[#dbe4cb] bg-[#f3f7ec] px-3 py-1 text-[12px] font-semibold text-pine">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" className="flex-none">
                                        <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z" fill="#C97B36" stroke="#C97B36" strokeWidth="1.5" strokeLinejoin="round" />
                                        <circle cx="12" cy="10" r="2.4" fill="#fff" />
                                    </svg>
                                    {showAllBadge ? 'Cho thuê tại: Toàn hệ thống' : `Cho thuê tại: ${combo.locations.map((l) => l.name).join(' · ')}`}
                                </span>
                            )}
                        </div>

                        {combo.description && (
                            <p className="mb-[18px] text-[15px] leading-[1.6] text-[#3f4a32]">{combo.description}</p>
                        )}

                        {/* So sánh giá lẻ vs combo (US-05) */}
                        <div className="mb-5 overflow-hidden rounded-[14px] border border-cardBorder bg-white">
                            <div className="border-b border-[#eef2e3] px-4 py-3 text-[13.5px] font-bold text-ink" style={{ background: '#f8faf4' }}>
                                Thuê combo rẻ hơn bao nhiêu?
                            </div>
                            <div className="px-4 py-3">
                                {combo.items.map((it) => (
                                    <div key={it.product_id} className="flex items-center justify-between py-[5px] text-[13.5px]">
                                        <span className="text-moss">{it.name} ×{it.quantity}</span>
                                        <span className="font-mono text-ink">{money(it.price_per_day * it.quantity)}/ngày</span>
                                    </div>
                                ))}
                                <div className="mt-1.5 flex items-center justify-between border-t border-dashed pt-2.5 text-[14px]" style={{ borderColor: '#d6ddc4' }}>
                                    <span className="text-moss">Tổng thuê lẻ</span>
                                    <span className="font-mono font-semibold text-[#8a967a] line-through">{money(combo.sum_individual)}/ngày</span>
                                </div>
                                <div className="flex items-center justify-between py-[5px] text-[15px]">
                                    <span className="font-bold text-ink">Giá combo</span>
                                    <span className="font-mono font-bold text-grass">{money(combo.combo_price)}/ngày</span>
                                </div>
                                {combo.savings_amount > 0 && (
                                    <div className="flex items-center justify-between text-[13.5px]">
                                        <span className="text-moss">Bạn tiết kiệm</span>
                                        <span className="font-mono font-bold text-campfire">
                                            {money(combo.savings_amount)}/ngày (−{combo.savings_percent}%)
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>

                        <DateRangeCalendar start={start} end={end} unavailable={unavailable} onChange={(s, e) => { setStart(s); setEnd(e); }} />

                        {/* range + qty + availability + Case 4 */}
                        <div className="mt-4 rounded-[14px] border border-cardBorder bg-white p-4">
                            <div className="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <div className="text-[12px] text-[#8a967a]">Khoảng thuê</div>
                                    <div className="font-mono text-[15px] font-bold text-ink">{rangeText(start, end)}</div>
                                </div>
                                <div className="flex items-center gap-2.5">
                                    <span className="text-[13px] text-moss">Số bộ</span>
                                    <div className="flex items-center overflow-hidden rounded-[10px] border border-cardBorder">
                                        <button onClick={() => setQty((q) => Math.max(1, q - 1))} className="h-9 w-[34px] bg-[#f1f4ea] text-[18px] text-grass">−</button>
                                        <span className="w-[38px] text-center font-mono font-bold">{qty}</span>
                                        <button onClick={() => setQty((q) => (avail ? Math.min(Math.max(1, avail.available), q + 1) : q + 1))} className="h-9 w-[34px] bg-[#f1f4ea] text-[18px] text-grass">+</button>
                                    </div>
                                </div>
                            </div>

                            {start && end && (
                                checking ? (
                                    <div className="rounded-[10px] px-3 py-2 text-[13px] font-semibold" style={{ background: '#f1f4ea', color: '#5a6b47' }}>
                                        Đang kiểm tra tồn kho…
                                    </div>
                                ) : avail && avail.available > 0 ? (
                                    <div className="rounded-[10px] px-3 py-2 text-[13px] font-semibold" style={{ background: '#dcebc4', color: '#3a5a1f' }}>
                                        Còn đủ {avail.available} bộ combo cho khoảng này
                                    </div>
                                ) : soldOut && avail ? (
                                    /* Case 4 — combo hết một phần: món nào hết + gợi ý (PRD 5.5) */
                                    <div className="rounded-[10px] px-3.5 py-3 text-[13px]" style={{ background: '#f6ddd6' }}>
                                        <div className="mb-1.5 font-bold text-[#b3493a]">Combo tạm hết trong khoảng này</div>
                                        {avail.insufficient.map((it) => (
                                            <div key={it.product_id} className="text-[#8a3328]">
                                                · <span className="font-semibold">{it.name}</span> đã được thuê hết
                                                {start && end ? ` trong ${ddmm(start)}–${ddmm(end)}` : ''} (còn {it.available}, cần {it.required})
                                            </div>
                                        ))}
                                        {avail.next_window && (
                                            <button
                                                onClick={applyNextWindow}
                                                className="mt-2.5 rounded-[9px] bg-white px-3 py-2 text-[12.5px] font-bold text-grass transition hover:bg-[#f1f4ea]"
                                            >
                                                Gần nhất còn đủ: {ddmm(avail.next_window.start)} → {ddmm(avail.next_window.end)} — chọn khoảng này
                                            </button>
                                        )}
                                        {avail.substitutes.length > 0 && (
                                            <div className="mt-3 border-t border-[#eec7bc] pt-2.5">
                                                <div className="mb-1.5 text-[12px] font-semibold text-[#8a3328]">Hoặc thuê lẻ món thay thế còn hàng:</div>
                                                <div className="flex flex-wrap gap-2">
                                                    {avail.substitutes.map((s) => (
                                                        <Link
                                                            key={s.id}
                                                            href={`/thiet-bi/${s.slug}`}
                                                            className="flex items-center gap-2 rounded-[9px] bg-white px-2.5 py-1.5 text-[12.5px] font-semibold text-pine transition hover:text-grass"
                                                        >
                                                            {s.thumbnail
                                                                ? <img src={s.thumbnail} alt="" className="h-6 w-6 rounded-[6px] object-cover" />
                                                                : <span className="grid h-6 w-6 place-items-center rounded-[6px] bg-[#f1f4ea] text-[11px]">⛺</span>}
                                                            {s.name}
                                                            <span className="font-mono text-[11px] text-moss">{money(s.price_per_day)}/ngày</span>
                                                        </Link>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ) : null
                            )}

                            <div className="mt-3.5 flex items-center justify-between gap-3">
                                <div>
                                    <div className="text-[12px] text-[#8a967a]">Tạm tính {days > 0 ? `(${days} ngày)` : ''}</div>
                                    <div className="font-mono text-[20px] font-bold text-grass">{money(subtotal)}</div>
                                    <div className="font-mono text-[11px] text-campfire">+ cọc {money(subDeposit)}</div>
                                </div>
                                <button
                                    onClick={add}
                                    disabled={!canAdd}
                                    className="h-[50px] rounded-control px-6 font-bold text-white transition disabled:cursor-not-allowed"
                                    style={{ background: canAdd ? '#557A2B' : '#c4cfae' }}
                                >
                                    {start && end ? 'Thêm combo vào giỏ' : 'Chọn ngày'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            {/* Lightbox */}
            {lightboxOpen && (activeSlide.type === 'img' || activeSlide.type === 'video') && (
                <div onClick={() => setLightboxOpen(false)} className="fixed inset-0 z-[95] flex items-center justify-center p-6" style={{ background: 'rgba(12,16,8,.82)' }}>
                    <button onClick={() => setLightboxOpen(false)} aria-label="Đóng" className="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/15 text-[20px] text-white">×</button>
                    {activeSlide.type === 'img' ? (
                        <img src={activeSlide.src} alt={combo.name} onClick={(e) => e.stopPropagation()} className="max-h-[90vh] max-w-[92vw] rounded-[12px] object-contain" />
                    ) : (
                        <video key={activeSlide.src} src={activeSlide.src} controls autoPlay muted loop playsInline onClick={(e) => e.stopPropagation()} className="max-h-[90vh] max-w-[92vw] rounded-[12px] object-contain" />
                    )}
                </div>
            )}

            {/* Popup: giỏ chỉ giữ 1 vị trí */}
            {conflict && (
                <div className="fixed inset-0 z-[200] flex items-center justify-center bg-black/45 px-4" onClick={() => setConflict(null)}>
                    <div className="w-full max-w-[420px] rounded-[18px] bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-4 grid h-12 w-12 place-items-center rounded-full" style={{ background: '#f7e7da' }}>
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z" fill="#C97B36" stroke="#C97B36" strokeWidth="1.5" strokeLinejoin="round" />
                                <circle cx="12" cy="10" r="2.6" fill="#fff" />
                            </svg>
                        </div>
                        <h2 className="mb-2 text-[18px] font-extrabold text-ink">Giỏ đang thuê ở nơi khác</h2>
                        <p className="mb-5 text-[14px] leading-[1.55] text-moss">
                            Giỏ hiện tại đang thuê tại{' '}
                            <span className="font-semibold text-pine">{conflict.cartLocations.map((l) => l.name).join(' · ')}</span>.
                            {' '}“<span className="font-semibold text-pine">{combo.name}</span>” chỉ phục vụ tại{' '}
                            <span className="font-semibold text-pine">{combo.locations.map((l) => l.name).join(' · ')}</span>{' '}
                            nên không thể thêm cùng giỏ. Mỗi đơn chỉ thuê tại một vị trí.
                        </p>
                        <div className="flex flex-col gap-2.5">
                            <button onClick={replaceCart} className="h-[46px] rounded-control bg-grass px-5 text-[14px] font-bold text-white transition hover:bg-pine">
                                Xoá giỏ hiện tại &amp; thêm combo này
                            </button>
                            <button onClick={() => setConflict(null)} className="h-[46px] rounded-control border border-cardBorder px-5 text-[14px] font-semibold text-pine transition hover:bg-[#f1f4ea]">
                                Giữ giỏ hiện tại
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

ComboDetail.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
