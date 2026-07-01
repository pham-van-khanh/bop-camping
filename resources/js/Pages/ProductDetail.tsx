import { Head, Link, usePage } from '@inertiajs/react';
import { ReactNode, useMemo, useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import DateRangeCalendar from '@/Components/site/DateRangeCalendar';
import ProductReviews, { type ReviewItem, type ReviewSummary } from '@/Components/site/ProductReviews';
import { dayCount, fromISO, money, rangeText, toISO } from '@/lib/format';
import { addLine, clearCart, locationConflict, type CartLine, type CartLocation } from '@/lib/cart';
import { emit, EVENTS } from '@/lib/bus';
import type { PageProps } from '@/types';
import type { ProductResource } from '@/types/product';

const GRAD: Record<string, string> = {
    'leu-cam-trai':      'linear-gradient(150deg,#3a5a40,#588157)',
    'tui-ngu':           'linear-gradient(150deg,#4a4e69,#9a8c98)',
    'bep-nau-an':        'linear-gradient(150deg,#7f4f24,#b6873a)',
    'ban-ghe-da-ngoai':  'linear-gradient(150deg,#4a6741,#7a9b6b)',
    'den-chieu-sang':    'linear-gradient(150deg,#3d405b,#6e7db0)',
    'ba-lo-tui':         'linear-gradient(150deg,#5c4033,#8b6355)',
};
const gradFor = (slug: string) => GRAD[slug] ?? 'linear-gradient(150deg,#4a6741,#7a9b6b)';

interface Props {
    product: ProductResource;
    unavailable_dates: string[];
    reviews: ReviewItem[];
    review_summary: ReviewSummary;
    can_review: boolean;
}

export default function ProductDetail({ product, unavailable_dates, reviews, review_summary, can_review }: Props) {
    const { auth } = usePage<PageProps>().props;
    const [activeImg, setActiveImg] = useState(0);
    const [start, setStart] = useState<string | null>(null);
    const [end, setEnd] = useState<string | null>(null);
    const [qty, setQty] = useState(1);
    // Popup khi thêm món khác vị trí với giỏ hiện tại.
    const [conflict, setConflict] = useState<{ pending: CartLine; cartLocations: CartLocation[] } | null>(null);

    const unavailable = useMemo(() => new Set<string>(unavailable_dates ?? []), [unavailable_dates]);

    const baseGrad = gradFor(product.category.slug);
    // Build gallery: real images first, then fallback gradient variants
    const gallery: ({ type: 'img'; src: string } | { type: 'video'; src: string } | { type: 'grad'; bg: string })[] = useMemo(() => {
        if (product.images.length > 0) {
            return product.images.map((img) => (img.type === 'video' ? { type: 'video' as const, src: img.path } : { type: 'img' as const, src: img.path }));
        }
        return [150, 35, 205, 330].map((a) => ({
            type: 'grad' as const,
            bg: baseGrad.replace('150deg', `${a}deg`),
        }));
    }, [product.images, baseGrad]);

    const days = start && end ? dayCount(start, end) : 0;

    let availState: 'none' | 'ok' | 'bad' = 'none';
    if (start && end) {
        availState = 'ok';
        for (let t = fromISO(start).getTime(); t <= fromISO(end).getTime(); t += 86400000) {
            if (unavailable.has(toISO(new Date(t)))) { availState = 'bad'; break; }
        }
    }
    const canAdd    = availState === 'ok';
    const subtotal  = product.price_per_day * qty * days;
    const subDeposit = product.deposit * qty;
    const lowStock  = product.quantity <= 2;
    const locations = product.locations ?? [];
    // Gộp "Toàn hệ thống" chỉ khi có ≥2 vị trí; 1 vị trí thì hiện thẳng tên nơi đó.
    const showAllBadge = !!product.all_locations && locations.length > 1;

    const onChange = (s: string | null, e: string | null) => { setStart(s); setEnd(e); };

    const buildLine = (): CartLine => ({
        id:      product.id,
        name:    product.name,
        cat:     product.category.slug as any,
        grad:    baseGrad,
        price:   product.price_per_day,
        deposit: product.deposit,
        qty,
        start:   start as string,
        end:     end as string,
        locations,
    });

    const commitAdd = (line: CartLine) => {
        addLine(line);
        emit(EVENTS.toast, `Đã thêm ${product.name} vào giỏ`);
    };

    const add = () => {
        if (!canAdd || !start || !end) return;
        const line = buildLine();
        // Giỏ chỉ giữ 1 vị trí: nếu món mới khác vị trí với giỏ → hỏi trước khi thay.
        const { conflict: hasConflict, cartLocations } = locationConflict(locations);
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

    const activeSlide = gallery[activeImg] ?? gallery[0];

    return (
        <>
            <Head title={product.name} />
            <main className="mx-auto max-w-[1120px] px-5 pb-12 pt-6">
                <Link href="/thiet-bi" className="mb-2.5 inline-block py-2 text-[14px] font-semibold text-moss hover:text-grass">← Quay lại danh sách</Link>
                <div className="grid items-start gap-[34px]" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))' }}>
                    {/* gallery */}
                    <div>
                        <div
                            className="relative h-[360px] overflow-hidden rounded-card"
                            style={activeSlide.type === 'grad' ? { background: activeSlide.bg } : { background: baseGrad }}
                        >
                            {activeSlide.type === 'img' && (
                                <img src={activeSlide.src} alt={product.name} className="absolute inset-0 h-full w-full object-cover" />
                            )}
                            {activeSlide.type === 'video' && (
                                <video src={activeSlide.src} controls className="absolute inset-0 h-full w-full object-cover" />
                            )}
                            <div className="absolute inset-0" style={{ background: 'radial-gradient(220px 150px at 76% 20%, rgba(255,255,255,.34), transparent 60%)' }} />
                            <div className="absolute inset-x-0 bottom-0 h-[90px]" style={{ background: 'linear-gradient(180deg,rgba(24,35,15,0),rgba(24,35,15,.4))' }} />
                            <span className="absolute bottom-4 left-[18px] font-mono text-[13px] tracking-[0.06em] text-white">{product.category.name}</span>
                        </div>
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

                        {/* đánh giá sản phẩm (carousel + form + modal) */}
                        <ProductReviews
                            productId={product.id}
                            productName={product.name}
                            reviews={reviews}
                            summary={review_summary}
                            canReview={can_review}
                            isLoggedIn={!!auth.user}
                        />
                    </div>

                    {/* info */}
                    <div>
                        <h1 className="mb-2.5 font-extrabold leading-[1.15] tracking-tight text-ink" style={{ fontSize: 'clamp(24px,3vw,30px)' }}>{product.name}</h1>
                        <div className="mb-[18px] flex flex-wrap items-center gap-2.5">
                            <span className="rounded-pill px-2.5 py-1 text-[12px] font-semibold" style={{ background: lowStock ? '#f7e7da' : '#dceaf6', color: lowStock ? '#C97B36' : '#2a6ea0' }}>
                                {lowStock ? `Sắp hết · còn ${product.quantity} bộ` : `Còn ${product.quantity} bộ trong kho`}
                            </span>
                            {locations.length > 0 && (
                                <span className="inline-flex items-center gap-1.5 rounded-pill border border-[#dbe4cb] bg-[#f3f7ec] px-3 py-1 text-[12px] font-semibold text-pine">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" className="flex-none">
                                        <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z" fill="#C97B36" stroke="#C97B36" strokeWidth="1.5" strokeLinejoin="round" />
                                        <circle cx="12" cy="10" r="2.4" fill="#fff" />
                                    </svg>
                                    {showAllBadge ? 'Cho thuê tại: Toàn hệ thống' : `Cho thuê tại: ${locations.map((l) => l.name).join(' · ')}`}
                                </span>
                            )}
                        </div>
                        <div className="mb-5 flex flex-wrap gap-2.5">
                            <div className="min-w-[140px] flex-1 rounded-[13px] px-4 py-3.5" style={{ background: '#eef2e3' }}>
                                <div className="mb-1 text-[12px] text-moss">Giá thuê</div>
                                <div><span className="font-mono text-[22px] font-bold text-grass">{money(product.price_per_day)}</span><span className="text-[13px] text-[#8a967a]">/ngày</span></div>
                            </div>
                            <div className="min-w-[140px] flex-1 rounded-[13px] px-4 py-3.5" style={{ background: '#f7efe5' }}>
                                <div className="mb-1 text-[12px]" style={{ color: '#9a7a4a' }}>Tiền cọc (hoàn lại)</div>
                                <div className="font-mono text-[22px] font-bold text-campfire">{money(product.deposit)}</div>
                            </div>
                        </div>
                        {product.description && (
                            <p className="mb-[18px] text-[15px] leading-[1.6] text-[#3f4a32]">{product.description}</p>
                        )}

                        <DateRangeCalendar start={start} end={end} unavailable={unavailable} onChange={onChange} />

                        {/* range + qty + availability */}
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
                                        <button onClick={() => setQty((q) => Math.min(product.quantity, q + 1))} className="h-9 w-[34px] bg-[#f1f4ea] text-[18px] text-grass">+</button>
                                    </div>
                                </div>
                            </div>

                            {availState !== 'none' && (
                                <div className="rounded-[10px] px-3 py-2 text-[13px] font-semibold"
                                    style={availState === 'ok' ? { background: '#dcebc4', color: '#3a5a1f' } : { background: '#f6ddd6', color: '#b3493a' }}>
                                    {availState === 'ok' ? `Còn đủ ${product.quantity} bộ cho khoảng này` : 'Khoảng này có ngày đã hết, chọn ngày khác giúp tụi mình nhé'}
                                </div>
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
                                    {start && end ? 'Thêm vào giỏ' : 'Chọn ngày'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

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
                            {' '}“<span className="font-semibold text-pine">{product.name}</span>” chỉ phục vụ tại{' '}
                            <span className="font-semibold text-pine">{locations.map((l) => l.name).join(' · ')}</span>{' '}
                            nên không thể thêm cùng giỏ. Mỗi đơn chỉ thuê tại một vị trí.
                        </p>
                        <div className="flex flex-col gap-2.5">
                            <button
                                onClick={replaceCart}
                                className="h-[46px] rounded-control bg-grass px-5 text-[14px] font-bold text-white transition hover:bg-pine"
                            >
                                Xoá giỏ hiện tại &amp; thêm món này
                            </button>
                            <button
                                onClick={() => setConflict(null)}
                                className="h-[46px] rounded-control border border-cardBorder px-5 text-[14px] font-semibold text-pine transition hover:bg-[#f1f4ea]"
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

ProductDetail.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
