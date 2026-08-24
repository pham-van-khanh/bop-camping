import { COMBO_GRAD } from '@/Components/site/ComboCard';
import DateRangeCalendar from '@/Components/site/DateRangeCalendar';
import MagazineContent from '@/Components/site/MagazineContent';
import PickupReturnNote from '@/Components/site/PickupReturnNote';
import ProductCard from '@/Components/site/ProductCard';
import ProductReviews, {
    type ReviewItem,
    type ReviewSummary,
} from '@/Components/site/ProductReviews';
import SiteLayout from '@/Layouts/SiteLayout';
import { emit, EVENTS } from '@/lib/bus';
import {
    addLine,
    cartSuggestedRange,
    clearCart,
    locationConflict,
    type CartLine,
    type CartLocation,
} from '@/lib/cart';
import { dayCount, ddmm, fromISO, money, rangeText, toISO } from '@/lib/format';
import { gradFor } from '@/lib/grad';
import { durationTierPercent, netFromGross } from '@/lib/pricing';
import { queryDateRange } from '@/lib/queryDateRange';
import { isHalfDaySession, type Session } from '@/lib/session';
import type { PageProps } from '@/types';
import type { ProductResource } from '@/types/product';
import { Head, Link, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useMemo, useRef, useState } from 'react';

/**
 * Khung ảnh chính: full width trên mobile, cột ~668px từ breakpoint lg (grid
 * lg:grid-cols-[minmax(0,1fr)_440px] trong max-w-[1400px], trừ padding + dải thumb).
 * Nói đúng cỡ thì browser mới chọn được bậc srcset hợp lý thay vì tải bậc to nhất.
 */
const MAIN_IMAGE_SIZES = '(min-width: 1024px) 668px, 100vw';

/** Phụ kiện "thường thuê cùng" (Case 2, US-03) — admin gán tay ở form sản phẩm. */
type AccessoryItem = {
    id: number;
    slug: string;
    name: string;
    price_per_day: number;
    deposit: number;
    quantity: number;
    thumbnail: string | null;
    category: { name: string; slug: string };
    locations: CartLocation[];
};

/** Combo active tiết kiệm nhất chứa sản phẩm — banner PRD 5.6, ưu tiên hơn gợi ý lẻ. */
type ComboBanner = {
    id: number;
    name: string;
    slug: string;
    combo_price: number;
    sum_individual: number;
    savings_amount: number;
    savings_percent: number;
    items_count: number;
};

interface Props {
    product: ProductResource;
    unavailable_dates: string[];
    unavailable_by_location: Record<number, string[]>;
    accessories: AccessoryItem[];
    combo_banner: ComboBanner | null;
    reviews: ReviewItem[];
    review_summary: ReviewSummary;
    // "You may also like" (1.6) — admin tự chọn, chỉ sản phẩm active
    related_products: ProductResource[];
    // Per-store: tồn theo từng cửa hàng phục vụ
    stock_by_location: {
        id: number;
        name: string;
        slug: string;
        quantity: number;
    }[];
}

export default function ProductDetail({
    product,
    unavailable_dates,
    unavailable_by_location,
    accessories,
    combo_banner,
    reviews,
    review_summary,
    related_products,
    stock_by_location,
}: Props) {
    const { auth, durationTiers } = usePage<PageProps>().props;
    // Khung giờ mặc định hệ thống (bopcamping-n6mr) — prefill ô chọn giờ khi thuê 1 ngày.
    const site = (
        usePage().props as {
            site?: {
                pickup_hour?: number;
                return_hour?: number;
                morning_end_hour?: number;
                afternoon_start_hour?: number;
                zalo_1?: { url?: string | null };
            };
        }
    ).site;
    const shopPickup = site?.pickup_hour ?? 8;
    const shopReturn = site?.return_hour ?? 20;
    const shopMorningEnd = site?.morning_end_hour ?? 12;
    const shopAfternoonStart = site?.afternoon_start_hour ?? 13;
    const zaloUrl = site?.zalo_1?.url ?? null;
    const [activeImg, setActiveImg] = useState(0);
    const [lightboxOpen, setLightboxOpen] = useState(false);
    // Prefill ngày từ giỏ (bopcamping-wtuv T5): giỏ đã có khoảng ngày → sản phẩm mở sau tự
    // chọn khoảng đó (khách vẫn đổi được — đổi = cố ý tách đợt, checkout sẽ tách đơn con).
    // Bỏ qua khoảng đã ở quá khứ (giỏ cũ để lâu).
    const suggested = useMemo(() => {
        const r = cartSuggestedRange();
        return r && r.start >= toISO(new Date()) ? r : null;
    }, []);
    // Prefill từ query URL ?start=&end= (bopcamping-llg6 T5, PRD FR-3) — khách đến từ trang
    // chủ đã chọn ngày. ƯU TIÊN CAO HƠN cartSuggestedRange(); khách vẫn sửa lịch tự do sau đó.
    const initialRange = useMemo(
        () => queryDateRange() ?? suggested,
        [suggested],
    );
    const [start, setStart] = useState<string | null>(
        initialRange?.start ?? null,
    );
    const [end, setEnd] = useState<string | null>(initialRange?.end ?? null);
    // 1.7: lịch mặc định thu gọn — bấm ô "Chọn ngày thuê" mới sổ ra.
    const [calOpen, setCalOpen] = useState(false);
    const [qty, setQty] = useState(1);
    // Buổi khách chọn KHI THUÊ 1 NGÀY (spec 2026-07-26) — mặc định "cả ngày".
    const [session, setSession] = useState<Session>('full');
    // Popup khi thêm món khác vị trí với giỏ hiện tại (1 món lẻ hoặc cả loạt phụ kiện).
    const [conflict, setConflict] = useState<{
        pending: CartLine[];
        cartLocations: CartLocation[];
    } | null>(null);
    // Tồn kho THỰC theo khoảng ngày từ server (bopcamping-1z1) — quantity tĩnh
    // không biết combo/đơn khác đã chiếm bao nhiêu trong khoảng khách chọn.
    const [avail, setAvail] = useState<number | null>(null);
    const [checking, setChecking] = useState(false);
    const fetchSeq = useRef(0); // chống race: chỉ nhận kết quả lần fetch mới nhất
    // Per-store: cửa hàng khách chọn (auto nếu chỉ 1 store phục vụ) + tồn khả dụng theo store.
    const stores = stock_by_location ?? [];
    const [storeId, setStoreId] = useState<number | null>(
        stores.length === 1 ? stores[0].id : null,
    );
    const [availByLoc, setAvailByLoc] = useState<Record<number, number> | null>(
        null,
    );
    // Gợi ý P3: tồn kho phụ kiện + combo banner theo khoảng ngày (AC-9).
    const [accAvail, setAccAvail] = useState<Record<number, number> | null>(
        null,
    );
    const [comboAvail, setComboAvail] = useState<number | null>(null);
    const sugSeq = useRef(0);
    const [accChecked, setAccChecked] = useState<Set<number>>(
        () => new Set(accessories.map((a) => a.id)),
    );
    const [accQty, setAccQty] = useState<Record<number, number>>({});

    // Store đang chọn mà hết sạch trong khoảng ngày → tự bỏ chọn để khách chọn cơ sở khác.
    useEffect(() => {
        if (storeId != null && availByLoc && (availByLoc[storeId] ?? 0) <= 0) {
            setStoreId(null);
        }
    }, [storeId, availByLoc]);

    // Lịch chặn ngày hết:
    // - Đã chọn store → chặn ngày hết CỦA STORE ĐÓ (yêu cầu 1).
    // - Chưa chọn store (nhiều cơ sở) → KHÔNG chặn ngày, chỉ hiện số còn (yêu cầu 2).
    // - Sản phẩm chưa cấu hình cơ sở (legacy) → dùng lịch toàn cục.
    const unavailable = useMemo(() => {
        if (storeId != null)
            return new Set<string>(unavailable_by_location?.[storeId] ?? []);
        if (stores.length === 0)
            return new Set<string>(unavailable_dates ?? []);
        return new Set<string>();
    }, [storeId, unavailable_by_location, unavailable_dates, stores.length]);

    useEffect(() => {
        if (!start || !end) {
            setAvail(null);
            setAvailByLoc(null);
            return;
        }
        const seq = ++fetchSeq.current;
        setChecking(true);
        // Chọn store → tồn store đó; chưa chọn (nhiều store) → map by_location, lấy max làm mức cho phép thêm.
        const url =
            storeId != null
                ? `/thiet-bi/${product.slug}/kha-dung?start=${start}&end=${end}&location_id=${storeId}`
                : `/thiet-bi/${product.slug}/kha-dung?start=${start}&end=${end}`;
        fetch(url, { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : null))
            .then(
                (
                    j: {
                        available?: number;
                        by_location?: Record<number, number>;
                    } | null,
                ) => {
                    if (seq !== fetchSeq.current) return;
                    if (!j) {
                        setAvail(null);
                        setAvailByLoc(null);
                        return;
                    }
                    if (j.by_location) {
                        setAvailByLoc(j.by_location);
                        setAvail(Math.max(0, ...Object.values(j.by_location)));
                    } else {
                        setAvailByLoc(null);
                        const a = j.available ?? null;
                        setAvail(a);
                        if (a != null)
                            setQty((q) =>
                                Math.max(1, Math.min(q, Math.max(1, a))),
                            );
                    }
                },
            )
            .catch(() => {
                if (seq === fetchSeq.current) {
                    setAvail(null);
                    setAvailByLoc(null);
                }
            })
            .finally(() => seq === fetchSeq.current && setChecking(false));
    }, [start, end, product.slug, storeId]);

    // Tồn kho gợi ý (phụ kiện + combo banner) theo khoảng ngày — AC-9.
    useEffect(() => {
        if (!start || !end || (accessories.length === 0 && !combo_banner)) {
            setAccAvail(null);
            setComboAvail(null);
            return;
        }
        const seq = ++sugSeq.current;
        const locQ = storeId != null ? `&location_id=${storeId}` : '';
        fetch(
            `/thiet-bi/${product.slug}/goi-y-kha-dung?start=${start}&end=${end}${locQ}`,
            { headers: { Accept: 'application/json' } },
        )
            .then((r) => (r.ok ? r.json() : null))
            .then(
                (
                    j: {
                        accessories: { id: number; available: number }[];
                        combo_available: number | null;
                    } | null,
                ) => {
                    if (seq !== sugSeq.current) return;
                    if (!j) {
                        setAccAvail(null);
                        setComboAvail(null);
                        return;
                    }
                    const map: Record<number, number> = {};
                    j.accessories.forEach((a) => {
                        map[a.id] = a.available;
                    });
                    setAccAvail(map);
                    setComboAvail(j.combo_available);
                },
            )
            .catch(() => {
                if (seq !== sugSeq.current) return;
                setAccAvail(null);
                setComboAvail(null);
            });
    }, [
        start,
        end,
        product.slug,
        accessories.length,
        combo_banner?.id,
        storeId,
    ]);

    const baseGrad = gradFor(product.category.slug);
    // Build gallery: real images first, then fallback gradient variants
    const gallery: (
        | { type: 'img'; src: string; srcset?: string | null }
        | { type: 'video'; src: string }
        | { type: 'grad'; bg: string }
    )[] = useMemo(() => {
        if (product.images.length > 0) {
            return product.images.map((img) =>
                img.type === 'video'
                    ? { type: 'video' as const, src: img.url }
                    : {
                          type: 'img' as const,
                          src: img.url,
                          srcset: img.srcset,
                      },
            );
        }
        return [150, 35, 205, 330].map((a) => ({
            type: 'grad' as const,
            bg: baseGrad.replace('150deg', `${a}deg`),
        }));
    }, [product.images, baseGrad]);

    const days = start && end ? dayCount(start, end) : 0;

    // Fallback client (lịch 90 ngày) khi fetch server lỗi — chỉ phát hiện ngày hết sạch.
    let clientBad = false;
    if (start && end) {
        for (
            let t = fromISO(start).getTime();
            t <= fromISO(end).getTime();
            t += 86400000
        ) {
            if (unavailable.has(toISO(new Date(t)))) {
                clientBad = true;
                break;
            }
        }
    }
    // Số còn thuê được trong khoảng đã chọn: ưu tiên số thực từ server (theo store chọn,
    // hoặc max các store khi chưa chọn). Fallback trước khi fetch = tồn store lớn nhất.
    const maxStoreQty = stores.length
        ? Math.max(...stores.map((s) => s.quantity))
        : product.quantity;
    const remaining = avail ?? (clientBad ? 0 : maxStoreQty);
    // Mốc so "đã được đặt": tồn của CHÍNH cửa hàng đang xét (không phải tổng 2 store),
    // nếu chưa chọn store thì lấy store nhiều hàng nhất (khớp remaining = max). Tránh
    // hiểu nhầm hàng ở store kia thành "đã đặt".
    const storeBaseline =
        storeId != null
            ? (stores.find((s) => s.id === storeId)?.quantity ?? maxStoreQty)
            : maxStoreQty;
    const qtyCap = Math.max(1, remaining);
    const canAdd =
        !!start && !!end && !checking && remaining >= qty && qty >= 1;
    // Giảm giá thuê dài ngày (bopcamping-e36e) — net theo bậc admin cấu hình (mirror server).
    const grossSub = product.price_per_day * qty * days;
    const tierPct = durationTierPercent(days, durationTiers);
    // Buổi sáng/chiều (spec 2026-07-26): thuê 1 ngày + SP có ưu đãi → áp % trả sớm (mirror server priceLine).
    const earlyPct = product.early_return_discount_pct ?? 0;
    const isHalf = days === 1 && isHalfDaySession(session) && earlyPct > 0;
    const effPct = isHalf ? earlyPct : tierPct;
    const subtotal = isHalf
        ? Math.round(grossSub * (1 - earlyPct / 100))
        : netFromGross(grossSub, days, durationTiers);
    const subDeposit = product.deposit * qty;
    const lowStock = product.quantity <= 2;
    const locations = product.locations ?? [];
    // Gộp "Toàn hệ thống" chỉ khi có ≥2 vị trí; 1 vị trí thì hiện thẳng tên nơi đó.
    const showAllBadge = !!product.all_locations && locations.length > 1;

    const onChange = (s: string | null, e: string | null) => {
        setStart(s);
        setEnd(e);
        // Chọn đủ khoảng (có ngày kết thúc) → tự thu lịch lại cho gọn.
        if (s && e) setCalOpen(false);
    };

    const buildLine = (): CartLine => ({
        id: product.id,
        name: product.name,
        cat: product.category.slug,
        grad: baseGrad,
        price: product.price_per_day,
        deposit: product.deposit,
        qty,
        start: start as string,
        end: end as string,
        locations,
        location_id: storeId, // per-store: cửa hàng khách chọn (null = checkout tự gán)
        early_return_pct: product.early_return_discount_pct ?? 0, // ưu đãi trả sớm trong ngày (adr_pricing_models)
        // Buổi khách chọn — chỉ áp khi thuê ĐÚNG 1 NGÀY (spec 2026-07-26); nhiều ngày = null.
        session: start && end && start === end ? session : null,
    });

    const commitAdd = (lines: CartLine[]) => {
        lines.forEach(addLine);
        emit(
            EVENTS.toast,
            lines.length === 1
                ? `Đã thêm ${lines[0].name} vào giỏ`
                : `Đã thêm ${lines.length} món vào giỏ`,
        );
    };

    const add = () => {
        if (!canAdd || !start || !end) return;
        const line = buildLine();
        // Giỏ chỉ giữ 1 vị trí: nếu món mới khác vị trí với giỏ → hỏi trước khi thay.
        const { conflict: hasConflict, cartLocations } =
            locationConflict(locations);
        if (hasConflict) {
            setConflict({ pending: [line], cartLocations });
            return;
        }
        commitAdd([line]);
    };

    const replaceCart = () => {
        if (!conflict) return;
        clearCart();
        commitAdd(conflict.pending);
        setConflict(null);
    };

    /* --- "Thường thuê cùng" (Case 2, US-03) --- */

    // Số còn thuê được của phụ kiện trong khoảng đã chọn (fallback kho tĩnh khi chưa chọn/lỗi fetch).
    const accCap = (a: AccessoryItem) => accAvail?.[a.id] ?? a.quantity;
    // AC-9: đã chọn khoảng ngày → CHỈ hiện món còn hàng; chưa chọn → hiện tất cả.
    const visibleAccessories =
        start && end && accAvail !== null
            ? accessories.filter((a) => (accAvail[a.id] ?? 0) >= 1)
            : accessories;
    const selectedAccessories = visibleAccessories.filter((a) =>
        accChecked.has(a.id),
    );
    const qtyOf = (a: AccessoryItem) =>
        Math.max(1, Math.min(accQty[a.id] ?? 1, Math.max(1, accCap(a))));
    const accPerDay = selectedAccessories.reduce(
        (s, a) => s + a.price_per_day * qtyOf(a),
        0,
    );
    const accDeposit = selectedAccessories.reduce(
        (s, a) => s + a.deposit * qtyOf(a),
        0,
    );

    const toggleAcc = (id: number) =>
        setAccChecked((s) => {
            const n = new Set(s);
            if (n.has(id)) n.delete(id);
            else n.add(id);
            return n;
        });

    const bumpAccQty = (a: AccessoryItem, d: number) =>
        setAccQty((m) => ({
            ...m,
            [a.id]: Math.max(1, Math.min(Math.max(1, accCap(a)), qtyOf(a) + d)),
        }));

    const addAccessories = () => {
        if (!start || !end || selectedAccessories.length === 0) return;
        const lines: CartLine[] = selectedAccessories.map((a) => ({
            id: a.id,
            name: a.name,
            cat: a.category.slug,
            grad: gradFor(a.category.slug),
            price: a.price_per_day,
            deposit: a.deposit,
            qty: qtyOf(a),
            start,
            end,
            locations: a.locations,
        }));
        const conflicted = lines.find(
            (l) => locationConflict(l.locations ?? []).conflict,
        );
        if (conflicted) {
            setConflict({
                pending: lines,
                cartLocations: locationConflict(conflicted.locations ?? [])
                    .cartLocations,
            });
            return;
        }
        commitAdd(lines);
    };

    // Banner combo chỉ hiện khi còn hàng trong khoảng đã chọn (chưa chọn ngày → hiện).
    const showComboBanner =
        !!combo_banner && (comboAvail === null || comboAvail >= 1);

    const activeSlide = gallery[activeImg] ?? gallery[0];

    // 1.8: chuyển ảnh chính bằng nút ‹ › (vòng tròn), sync với thumbnail active.
    const goImg = (dir: number) =>
        setActiveImg((i) => (i + dir + gallery.length) % gallery.length);

    // Feedback #1: dải thumbnails cuộn được (dọc trên desktop, ngang trên mobile);
    // đổi ảnh active (click, nút ‹ ›, phím) thì tự trượt đưa thumbnail đó vào GIỮA
    // — các ảnh kế tiếp tự lộ ra, khách không phải cuộn tay.
    const thumbColRef = useRef<HTMLDivElement>(null);
    const thumbRowRef = useRef<HTMLDivElement>(null);
    useEffect(() => {
        [thumbColRef.current, thumbRowRef.current].forEach((col) => {
            const btn = col?.children[activeImg] as HTMLElement | undefined;
            if (!col || !btn) return;
            col.scrollTo({
                top: btn.offsetTop - (col.clientHeight - btn.offsetHeight) / 2,
                left: btn.offsetLeft - (col.clientWidth - btn.offsetWidth) / 2,
                behavior: 'smooth',
            });
        });
    }, [activeImg]);

    // Phím ← → chuyển ảnh, Esc đóng — khi đang mở lightbox.
    useEffect(() => {
        if (!lightboxOpen) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'ArrowLeft') goImg(-1);
            else if (e.key === 'ArrowRight') goImg(1);
            else if (e.key === 'Escape') setLightboxOpen(false);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [lightboxOpen, gallery.length]);

    // Thumbnail dùng ở cả cột dọc (desktop) lẫn hàng ngang (mobile) — truyền size class.
    const renderThumb = (
        g: (typeof gallery)[number],
        i: number,
        sizeClass: string,
    ) => (
        <button
            key={i}
            onClick={() => setActiveImg(i)}
            // Nhãn phải nói ảnh CỦA CÁI GÌ (bopcamping-1xja). "Ảnh 1" không cho người
            // dùng trình đọc màn hình biết gì, và Google cũng không có chữ nào để hiểu
            // tấm ảnh. Ảnh bên trong để alt="" là đúng — nhãn nằm ở nút bọc, thêm alt
            // nữa sẽ bị đọc lặp.
            aria-label={`Ảnh ${i + 1} của ${product.name}`}
            className={`relative ${sizeClass} overflow-hidden rounded-[11px] transition`}
        >
            {g.type === 'img' ? (
                <img
                    src={g.src}
                    srcSet={g.srcset ?? undefined}
                    /* Ô thumbnail chỉ 76x64 CSS → @2x cần ~152px, browser chọn bậc 400. */
                    sizes="76px"
                    alt=""
                    className="h-full w-full object-cover"
                />
            ) : g.type === 'video' ? (
                <>
                    <video
                        src={g.src}
                        className="h-full w-full object-cover"
                        muted
                    />
                    <span className="absolute inset-0 grid place-items-center bg-black/25 text-[10px] text-white">
                        ▶
                    </span>
                </>
            ) : (
                <div className="h-full w-full" style={{ background: g.bg }} />
            )}
            {/* Viền chọn vẽ PHỦ BÊN TRONG ô (không dùng outline ngoài) để không bị overflow của
                cột dọc / hàng ngang cắt mất 2 cạnh. */}
            <span
                aria-hidden
                className="pointer-events-none absolute inset-0 rounded-[11px]"
                style={{
                    border: i === activeImg ? '2px solid #557A2B' : 'none',
                }}
            />
        </button>
    );

    return (
        <>
            <Head title={product.name} />
            <main className="mx-auto max-w-[1400px] px-5 pb-12 pt-6">
                <Link
                    href="/thiet-bi"
                    className="mb-2.5 inline-block py-2 text-[14px] font-semibold text-moss hover:text-grass"
                >
                    ← Quay lại danh sách
                </Link>
                {/* grid-cols-1 = minmax(0,1fr): chặn hàng thumbs ngang kéo giãn track làm tràn trang mobile */}
                <div className="grid grid-cols-1 items-start gap-[34px] lg:grid-cols-[minmax(0,1fr)_440px]">
                    {/* gallery: thumbnails cột dọc trái (desktop) + ảnh chính (1.1) */}
                    <div>
                        <div className="flex gap-2.5">
                            {/* Thumbnails dọc — chỉ desktop/tablet; mobile dùng hàng ngang phía dưới.
                                Nhiều ảnh quá khổ → cuộn dọc (slide), auto trượt theo ảnh active. */}
                            {gallery.length > 1 && (
                                <div
                                    ref={thumbColRef}
                                    className="relative hidden max-h-[560px] w-[76px] flex-none flex-col gap-2.5 overflow-y-auto md:flex lg:max-h-[680px]"
                                    style={{ scrollbarWidth: 'none' }}
                                >
                                    {gallery.map((g, i) =>
                                        renderThumb(
                                            g,
                                            i,
                                            'h-[64px] w-full flex-none',
                                        ),
                                    )}
                                </div>
                            )}
                            <div
                                className="relative h-[420px] min-w-0 flex-1 overflow-hidden rounded-card md:h-[560px] lg:h-[680px]"
                                style={
                                    activeSlide.type === 'grad'
                                        ? { background: activeSlide.bg }
                                        : { background: '#eef2e3' }
                                }
                            >
                                {activeSlide.type === 'img' && (
                                    <img
                                        src={activeSlide.src}
                                        alt={product.name}
                                        srcSet={activeSlide.srcset ?? undefined}
                                        sizes={MAIN_IMAGE_SIZES}
                                        onClick={() => setLightboxOpen(true)}
                                        className="absolute inset-0 h-full w-full cursor-zoom-in object-cover"
                                    />
                                )}
                                {activeSlide.type === 'video' && (
                                    <video
                                        key={activeSlide.src}
                                        src={activeSlide.src}
                                        controls
                                        autoPlay
                                        muted
                                        loop
                                        playsInline
                                        className="absolute inset-0 h-full w-full object-cover"
                                    />
                                )}
                                {(activeSlide.type === 'img' ||
                                    activeSlide.type === 'video') && (
                                    <button
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setLightboxOpen(true);
                                        }}
                                        aria-label="Xem cỡ lớn"
                                        className="absolute right-3 top-3 z-10 grid h-9 w-9 place-items-center rounded-full bg-black/45 text-white transition hover:bg-black/65"
                                    >
                                        <svg
                                            width="16"
                                            height="16"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                        >
                                            <path
                                                d="M9 3H4a1 1 0 0 0-1 1v5M15 3h5a1 1 0 0 1 1 1v5M9 21H4a1 1 0 0 1-1-1v-5M15 21h5a1 1 0 0 0 1-1v-5"
                                                stroke="white"
                                                strokeWidth="2"
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                            />
                                        </svg>
                                    </button>
                                )}
                                {/* 1.8: nút chuyển ảnh ‹ › */}
                                {gallery.length > 1 && (
                                    <>
                                        <button
                                            onClick={() => goImg(-1)}
                                            aria-label="Ảnh trước"
                                            className="absolute left-3 top-1/2 z-10 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-black/30 text-[20px] text-white transition hover:bg-black/55"
                                        >
                                            ‹
                                        </button>
                                        <button
                                            onClick={() => goImg(1)}
                                            aria-label="Ảnh sau"
                                            className="absolute right-3 top-1/2 z-10 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-black/30 text-[20px] text-white transition hover:bg-black/55"
                                        >
                                            ›
                                        </button>
                                    </>
                                )}
                            </div>
                        </div>
                        {/* Thumbnails hàng ngang — chỉ mobile: 1 hàng trượt ngang,
                            bấm ảnh gần cuối tự kéo sang để lộ các ảnh sau */}
                        {gallery.length > 1 && (
                            <div
                                ref={thumbRowRef}
                                className="relative mt-2.5 flex gap-2.5 overflow-x-auto md:hidden"
                                style={{ scrollbarWidth: 'none' }}
                            >
                                {gallery.map((g, i) =>
                                    renderThumb(
                                        g,
                                        i,
                                        'h-[64px] w-[72px] flex-none',
                                    ),
                                )}
                            </div>
                        )}

                        {/* 1.2: thông số key–value (admin nhập) — card dưới khối ảnh */}
                        {(product.specs?.length ?? 0) > 0 && (
                            <div className="mt-5 rounded-[16px] border border-cardBorder bg-white px-5 py-4">
                                <div className="mb-2 font-mono text-[12px] font-bold tracking-[0.14em] text-campfire">
                                    THÔNG SỐ
                                </div>
                                <dl className="divide-y divide-[#f1f4ea]">
                                    {product.specs!.map((row, i) => (
                                        <div
                                            key={i}
                                            className="flex items-baseline justify-between gap-4 py-2.5"
                                        >
                                            <dt className="text-[13.5px] text-moss">
                                                {row.key}
                                            </dt>
                                            <dd className="text-right text-[14px] font-semibold text-ink">
                                                {row.value}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            </div>
                        )}
                    </div>

                    {/* info */}
                    <div>
                        <h1
                            className="mb-2.5 font-extrabold leading-[1.15] tracking-tight text-ink"
                            style={{ fontSize: 'clamp(24px,3vw,30px)' }}
                        >
                            {product.name}
                        </h1>
                        <div className="mb-[18px] flex flex-wrap items-center gap-2.5">
                            <span
                                className="rounded-pill px-2.5 py-1 text-[12px] font-semibold"
                                style={{
                                    background: lowStock
                                        ? '#f7e7da'
                                        : '#dceaf6',
                                    color: lowStock ? '#C97B36' : '#2a6ea0',
                                }}
                            >
                                {lowStock
                                    ? `Sắp hết · còn ${product.quantity} bộ`
                                    : `Còn ${product.quantity} bộ trong kho`}
                            </span>
                            {locations.length > 0 && (
                                <span className="inline-flex items-center gap-1.5 rounded-pill border border-[#dbe4cb] bg-[#f3f7ec] px-3 py-1 text-[12px] font-semibold text-pine">
                                    <svg
                                        width="12"
                                        height="12"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        className="flex-none"
                                    >
                                        <path
                                            d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"
                                            fill="#C97B36"
                                            stroke="#C97B36"
                                            strokeWidth="1.5"
                                            strokeLinejoin="round"
                                        />
                                        <circle
                                            cx="12"
                                            cy="10"
                                            r="2.4"
                                            fill="#fff"
                                        />
                                    </svg>
                                    {showAllBadge
                                        ? 'Cho thuê tại: Toàn hệ thống'
                                        : `Cho thuê tại: ${locations.map((l) => l.name).join(' · ')}`}
                                </span>
                            )}
                        </div>
                        <div className="mb-5 flex flex-wrap gap-2.5">
                            <div
                                className="min-w-[140px] flex-1 rounded-[13px] px-4 py-3.5"
                                style={{ background: '#eef2e3' }}
                            >
                                <div className="mb-1 text-[12px] text-moss">
                                    Giá thuê
                                </div>
                                <div>
                                    <span className="font-mono text-[22px] font-bold text-grass">
                                        {money(product.price_per_day)}
                                    </span>
                                    <span className="text-[13px] text-[#8a967a]">
                                        /ngày
                                    </span>
                                </div>
                            </div>
                            <div
                                className="min-w-[140px] flex-1 rounded-[13px] px-4 py-3.5"
                                style={{ background: '#f7efe5' }}
                            >
                                <div
                                    className="mb-1 text-[12px]"
                                    style={{ color: '#9a7a4a' }}
                                >
                                    Tiền cọc (hoàn lại)
                                </div>
                                <div className="font-mono text-[22px] font-bold text-campfire">
                                    {money(product.deposit)}
                                </div>
                            </div>
                        </div>

                        {/* PRD 5.6: banner "thuộc combo" — ưu tiên hiển thị hơn gợi ý lẻ */}
                        {combo_banner && showComboBanner && (
                            <Link
                                href={`/combos/${combo_banner.slug}`}
                                className="group mb-[18px] flex items-center gap-3 rounded-[14px] px-4 py-3.5 text-white transition hover:-translate-y-0.5 hover:shadow-cardhover"
                                style={{ background: COMBO_GRAD }}
                            >
                                <span className="rounded-pill bg-white/20 px-2.5 py-1 font-mono text-[11px] font-bold tracking-[0.06em]">
                                    COMBO
                                </span>
                                <span className="flex-1 text-[13.5px] leading-[1.45]">
                                    Sản phẩm này có trong{' '}
                                    <b>{combo_banner.name}</b>
                                    {combo_banner.savings_amount > 0 && (
                                        <>
                                            {' '}
                                            — tiết kiệm{' '}
                                            <b className="font-mono">
                                                {money(
                                                    combo_banner.savings_amount,
                                                )}
                                            </b>
                                            /ngày
                                        </>
                                    )}
                                </span>
                                <span className="whitespace-nowrap text-[13px] font-bold transition group-hover:translate-x-0.5">
                                    Xem combo →
                                </span>
                            </Link>
                        )}

                        {product.description && (
                            <div className="relative mb-3 overflow-hidden rounded-[14px] border border-[#e6ecd8] bg-gradient-to-br from-[#f5f8ef] to-[#eaf1de] py-3.5 pl-[18px] pr-4">
                                <span
                                    aria-hidden
                                    className="absolute inset-y-0 left-0 w-[3px] bg-grass"
                                />
                                <p className="text-[15px] font-medium leading-[1.65] text-[#38492a]">
                                    {product.description}
                                </p>
                            </div>
                        )}
                        {/* 1.3: có nội dung chi tiết → nút cuộn xuống khối #chi-tiet */}
                        {product.setup_content && (
                            <button
                                onClick={() =>
                                    document
                                        .getElementById('chi-tiet')
                                        ?.scrollIntoView({ behavior: 'smooth' })
                                }
                                className="mb-[18px] inline-flex items-center gap-1 text-[13.5px] font-bold text-grass transition hover:text-pine"
                            >
                                Xem thêm
                                <svg
                                    width="13"
                                    height="13"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                >
                                    <path
                                        d="M12 5v14m0 0-6-6m6 6 6-6"
                                        stroke="currentColor"
                                        strokeWidth="2.2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                </svg>
                            </button>
                        )}

                        {/* Per-store: chọn cơ sở khi sản phẩm phục vụ >1 cửa hàng */}
                        {stores.length > 1 && (
                            <div className="mb-[18px]">
                                <div className="mb-2.5 flex items-center gap-1.5">
                                    <svg
                                        width="15"
                                        height="15"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        className="flex-none"
                                    >
                                        <path
                                            d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"
                                            fill="#C97B36"
                                            stroke="#C97B36"
                                            strokeWidth="1.5"
                                            strokeLinejoin="round"
                                        />
                                        <circle
                                            cx="12"
                                            cy="10"
                                            r="2.4"
                                            fill="#fff"
                                        />
                                    </svg>
                                    <span className="text-[14.5px] font-extrabold tracking-tight text-ink">
                                        Chọn cơ sở gần bạn
                                    </span>
                                </div>
                                <div className="flex flex-wrap gap-2.5">
                                    {stores.map((s) => {
                                        const on = storeId === s.id;
                                        const n = availByLoc
                                            ? (availByLoc[s.id] ?? 0)
                                            : s.quantity;
                                        const out = n <= 0; // tạm hết (theo khoảng ngày nếu đã chọn, hoặc tồn tĩnh)
                                        return (
                                            <button
                                                key={s.id}
                                                disabled={out}
                                                onClick={() =>
                                                    setStoreId(on ? null : s.id)
                                                }
                                                className={`flex items-center gap-2 rounded-[12px] border px-3.5 py-2.5 text-left transition ${
                                                    out
                                                        ? 'cursor-not-allowed border-cardBorder bg-[#f6f8f1] opacity-60'
                                                        : on
                                                          ? 'border-grass bg-[#eef5e1]'
                                                          : 'border-cardBorder bg-white hover:border-grass'
                                                }`}
                                            >
                                                <span
                                                    className={`grid h-[18px] w-[18px] place-items-center rounded-full border text-[10px] font-bold ${on ? 'border-grass bg-grass text-white' : 'border-[#c4cca8] text-transparent'}`}
                                                >
                                                    ✓
                                                </span>
                                                <span className="leading-tight">
                                                    <span className="block text-[13.5px] font-bold text-ink">
                                                        {s.name}
                                                    </span>
                                                    <span
                                                        className={`block text-[11.5px] font-semibold ${out ? 'text-campfire' : 'text-moss'}`}
                                                    >
                                                        {out
                                                            ? 'Tạm hết'
                                                            : `còn ${n} bộ`}
                                                    </span>
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {/* 1.7: lịch dạng thu gọn — bấm để sổ, chọn xong tự đóng */}
                        <button
                            onClick={() => setCalOpen((o) => !o)}
                            aria-expanded={calOpen}
                            className="flex w-full items-center justify-between gap-3 rounded-[14px] border border-cardBorder bg-white px-4 py-3.5 text-left transition hover:border-grass"
                        >
                            <span className="flex items-center gap-2.5">
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    className="flex-none text-grass"
                                >
                                    <rect
                                        x="3"
                                        y="5"
                                        width="18"
                                        height="16"
                                        rx="3"
                                        stroke="currentColor"
                                        strokeWidth="1.8"
                                    />
                                    <path
                                        d="M3 9h18M8 3v4M16 3v4"
                                        stroke="currentColor"
                                        strokeWidth="1.8"
                                        strokeLinecap="round"
                                    />
                                </svg>
                                <span>
                                    <span className="block text-[12px] text-[#8a967a]">
                                        Ngày thuê
                                    </span>
                                    <span className="block font-mono text-[14.5px] font-bold text-ink">
                                        {start && end
                                            ? rangeText(start, end)
                                            : start
                                              ? `${ddmm(start)} → chọn ngày trả`
                                              : 'Chọn ngày thuê'}
                                    </span>
                                </span>
                            </span>
                            <svg
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                className={`flex-none text-moss transition-transform ${calOpen ? 'rotate-180' : ''}`}
                            >
                                <path
                                    d="m6 9 6 6 6-6"
                                    stroke="currentColor"
                                    strokeWidth="2.2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                            </svg>
                        </button>
                        {/* Feedback #2: lịch mở dạng popup (680px để 2 tháng nằm ngang, mobile tự xếp dọc) */}
                        {calOpen && (
                            <div
                                className="fixed inset-0 z-[150] flex items-center justify-center bg-black/45 px-4"
                                onClick={() => setCalOpen(false)}
                            >
                                <div
                                    className="max-h-[90vh] w-full max-w-[680px] overflow-y-auto rounded-[18px] bg-white p-5 shadow-xl"
                                    onClick={(e) => e.stopPropagation()}
                                >
                                    <div className="mb-3 flex items-start justify-between gap-3">
                                        <div>
                                            <h2 className="text-[17px] font-extrabold text-ink">
                                                Chọn ngày thuê
                                            </h2>
                                            <p className="mt-0.5 text-[12.5px] text-moss">
                                                Bấm ngày nhận rồi bấm ngày trả —
                                                chọn xong lịch tự đóng.
                                            </p>
                                        </div>
                                        <button
                                            onClick={() => setCalOpen(false)}
                                            aria-label="Đóng"
                                            className="grid h-9 w-9 flex-none place-items-center rounded-full bg-[#f1f4ea] text-[18px] text-pine transition hover:bg-[#e3e8d6]"
                                        >
                                            ×
                                        </button>
                                    </div>
                                    <DateRangeCalendar
                                        start={start}
                                        end={end}
                                        unavailable={unavailable}
                                        onChange={onChange}
                                    />
                                </div>
                            </div>
                        )}

                        {/* Ô riêng khung giờ nhận/trả — luôn hiện ngay dưới ô "Ngày thuê" (yêu cầu chủ shop). */}
                        <div className="mt-2.5 rounded-[12px] border border-cardBorder bg-[#fbfcf8] px-3.5 py-2.5">
                            <PickupReturnNote />
                            <p className="mt-1.5 flex items-start gap-1.5 text-[12px] text-moss">
                                <span aria-hidden>⏰</span>
                                <span>
                                    Muốn giờ nhận/trả khác?{' '}
                                    {zaloUrl ? (
                                        <a
                                            href={zaloUrl}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="font-semibold text-grass underline"
                                        >
                                            Liên hệ Zalo
                                        </a>
                                    ) : (
                                        'Liên hệ shop'
                                    )}{' '}
                                    để sắp xếp thêm.
                                </span>
                            </p>
                        </div>

                        {/* range + qty + availability */}
                        <div className="mt-4 rounded-[14px] border border-cardBorder bg-white p-4">
                            <div className="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <div className="text-[12px] text-[#8a967a]">
                                        Khoảng thuê
                                    </div>
                                    <div className="font-mono text-[15px] font-bold text-ink">
                                        {rangeText(start, end)}
                                    </div>
                                </div>
                                <div className="flex items-center gap-2.5">
                                    <span className="text-[13px] text-moss">
                                        Số bộ
                                    </span>
                                    <div className="flex items-center overflow-hidden rounded-[10px] border border-cardBorder">
                                        <button
                                            onClick={() =>
                                                setQty((q) =>
                                                    Math.max(1, q - 1),
                                                )
                                            }
                                            className="h-9 w-[34px] bg-[#f1f4ea] text-[18px] text-grass"
                                        >
                                            −
                                        </button>
                                        <span className="w-[38px] text-center font-mono font-bold">
                                            {qty}
                                        </span>
                                        <button
                                            onClick={() =>
                                                setQty((q) =>
                                                    Math.min(qtyCap, q + 1),
                                                )
                                            }
                                            className="h-9 w-[34px] bg-[#f1f4ea] text-[18px] text-grass"
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {/* Thuê ĐÚNG 1 NGÀY → khách tự chọn giờ nhận/trả (bopcamping-n6mr) */}
                            {start && end && start === end && (
                                <div className="mb-3 rounded-[12px] border border-cardBorder bg-[#fbfcf8] p-3">
                                    <div className="mb-2 flex items-center gap-2">
                                        <span className="grid h-7 w-7 flex-none place-items-center rounded-full bg-[#eef5e1] text-grass">
                                            <svg
                                                width="15"
                                                height="15"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                            >
                                                <circle
                                                    cx="12"
                                                    cy="12"
                                                    r="9"
                                                    stroke="currentColor"
                                                    strokeWidth="2"
                                                />
                                                <path
                                                    d="M12 7.5v5l3 2"
                                                    stroke="currentColor"
                                                    strokeWidth="2"
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                />
                                            </svg>
                                        </span>
                                        <div>
                                            <div className="text-[13px] font-bold text-ink">
                                                Chọn buổi thuê
                                            </div>
                                            <div className="text-[11.5px] text-moss">
                                                Thuê trong ngày — chọn buổi phù
                                                hợp
                                                {earlyPct > 0
                                                    ? ', buổi sáng/chiều được giảm giá'
                                                    : ''}
                                                .
                                            </div>
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-3 gap-2">
                                        {(
                                            [
                                                {
                                                    key: 'morning',
                                                    label: 'Buổi sáng',
                                                    time: `${shopPickup}h–${shopMorningEnd}h`,
                                                    half: true,
                                                },
                                                {
                                                    key: 'afternoon',
                                                    label: 'Buổi chiều',
                                                    time: `${shopAfternoonStart}h–${shopReturn}h`,
                                                    half: true,
                                                },
                                                {
                                                    key: 'full',
                                                    label: 'Cả ngày',
                                                    time: `${shopPickup}h–${shopReturn}h`,
                                                    half: false,
                                                },
                                            ] as const
                                        ).map((opt) => {
                                            const active = session === opt.key;
                                            return (
                                                <button
                                                    key={opt.key}
                                                    type="button"
                                                    onClick={() =>
                                                        setSession(opt.key)
                                                    }
                                                    aria-pressed={active}
                                                    className={`rounded-[10px] border px-2 py-2 text-center transition ${active ? 'border-grass bg-[#eef5e1] ring-1 ring-grass' : 'border-cardBorder bg-white hover:border-grass'}`}
                                                >
                                                    <span className="block text-[12.5px] font-bold text-ink">
                                                        {opt.label}
                                                    </span>
                                                    <span className="block text-[11px] text-moss">
                                                        {opt.time}
                                                    </span>
                                                    {opt.half &&
                                                        earlyPct > 0 && (
                                                            <span className="mt-0.5 block text-[10.5px] font-semibold text-grass">
                                                                −{earlyPct}%
                                                            </span>
                                                        )}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {start &&
                                end &&
                                (checking ? (
                                    <div
                                        className="rounded-[10px] px-3 py-2 text-[13px] font-semibold"
                                        style={{
                                            background: '#f1f4ea',
                                            color: '#5a6b47',
                                        }}
                                    >
                                        Đang kiểm tra tồn kho…
                                    </div>
                                ) : remaining === 0 ? (
                                    <div
                                        className="rounded-[10px] px-3 py-2 text-[13px] font-semibold"
                                        style={{
                                            background: '#f6ddd6',
                                            color: '#b3493a',
                                        }}
                                    >
                                        Khoảng này đã được thuê hết, chọn ngày
                                        khác giúp tụi mình nhé
                                    </div>
                                ) : (
                                    <div
                                        className="rounded-[10px] px-3 py-2 text-[13px] font-semibold"
                                        style={
                                            remaining < storeBaseline
                                                ? {
                                                      background: '#f7e7da',
                                                      color: '#8a5a1f',
                                                  }
                                                : {
                                                      background: '#dcebc4',
                                                      color: '#3a5a1f',
                                                  }
                                        }
                                    >
                                        {remaining < storeBaseline
                                            ? `Khoảng này chỉ còn ${remaining} bộ trống (${storeBaseline - remaining} bộ đã được đặt)`
                                            : `Còn đủ ${remaining} bộ cho khoảng này`}
                                    </div>
                                ))}

                            {/* Bậc giảm giá thuê dài ngày (bopcamping-e36e) — thuê càng lâu càng rẻ. */}
                            {durationTiers.length > 0 && (
                                <div className="mt-3 rounded-[10px] border border-[#e3e8d6] bg-[#f7faf0] px-3 py-2">
                                    <div className="mb-1 text-[12px] font-semibold text-pine">
                                        🏕️ Thuê dài ngày càng giảm
                                    </div>
                                    <div className="flex flex-wrap gap-x-3 gap-y-1 text-[12px]">
                                        {durationTiers.map((t) => {
                                            const on =
                                                tierPct === t.percent &&
                                                days >= t.minDays;
                                            return (
                                                <span
                                                    key={t.minDays}
                                                    className={
                                                        on
                                                            ? 'font-bold text-grass'
                                                            : 'text-moss'
                                                    }
                                                >
                                                    ≥{t.minDays} ngày −
                                                    {t.percent}%{on ? ' ✓' : ''}
                                                </span>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            <div className="mt-3.5 flex items-center justify-between gap-3">
                                <div>
                                    <div className="text-[12px] text-[#8a967a]">
                                        Tạm tính{' '}
                                        {days > 0 ? `(${days} ngày)` : ''}
                                    </div>
                                    {/* Giá đã giảm nổi bật; giá gốc gạch ngang xuống DÒNG DƯỚI cho gọn (góp ý chủ shop). */}
                                    <div className="flex items-baseline gap-2">
                                        <span className="font-mono text-[20px] font-bold text-grass">
                                            {money(subtotal)}
                                        </span>
                                        {effPct > 0 && days > 0 && (
                                            <span className="rounded-full bg-[#dcebc4] px-2 py-0.5 text-[11px] font-bold text-[#3a5a1f]">
                                                −{effPct}%
                                            </span>
                                        )}
                                    </div>
                                    {effPct > 0 && days > 0 && (
                                        <div className="font-mono text-[12px] text-[#8a967a] line-through">
                                            {money(grossSub)}
                                        </div>
                                    )}
                                    <div className="font-mono text-[11px] text-campfire">
                                        + cọc {money(subDeposit)}
                                    </div>
                                </div>
                                <button
                                    onClick={add}
                                    disabled={!canAdd}
                                    className="h-[50px] rounded-control px-6 font-bold text-white transition disabled:cursor-not-allowed"
                                    style={{
                                        background: canAdd
                                            ? '#557A2B'
                                            : '#c4cfae',
                                    }}
                                >
                                    {start && end
                                        ? 'Thêm vào giỏ'
                                        : 'Chọn ngày'}
                                </button>
                            </div>
                        </div>

                        {/* Case 2 (US-03) + feedback #3: "Thường thuê cùng" nằm ngay dưới nút
                            Thêm vào giỏ — khách khỏi phải kéo xuống tìm. AC-9 giữ nguyên. */}
                        {accessories.length > 0 && (
                            <section className="mt-5">
                                <div className="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                                    <div className="font-mono text-[12px] font-bold tracking-[0.14em] text-campfire">
                                        THƯỜNG THUÊ CÙNG
                                    </div>
                                    {(!start || !end) && (
                                        <span className="text-[11.5px] text-moss">
                                            Chọn ngày để kiểm tra món còn trống
                                        </span>
                                    )}
                                </div>

                                {visibleAccessories.length === 0 ? (
                                    <div className="rounded-[14px] border border-cardBorder bg-white px-4 py-4 text-[13px] text-moss">
                                        Các món gợi ý đều đã kín lịch trong
                                        khoảng {rangeText(start, end)} — đổi
                                        ngày để xem lại nhé.
                                    </div>
                                ) : (
                                    <div className="overflow-hidden rounded-[14px] border border-cardBorder bg-white">
                                        {/* Danh sách món trong thanh cuộn — cột phải không bị kéo dài vô hạn */}
                                        <div className="max-h-[290px] overflow-y-auto">
                                            {visibleAccessories.map((a, i) => {
                                                const on = accChecked.has(a.id);
                                                const cap = Math.max(
                                                    1,
                                                    accCap(a),
                                                );
                                                const q = qtyOf(a);
                                                // Có khoảng ngày + số thực từ server → báo khan hàng nếu bị đơn khác chiếm bớt
                                                const scarce =
                                                    start &&
                                                    end &&
                                                    accAvail !== null &&
                                                    accCap(a) < a.quantity;
                                                return (
                                                    <div
                                                        key={a.id}
                                                        className={`flex flex-wrap items-center gap-2.5 px-3 py-2.5 ${i > 0 ? 'border-t border-[#f1f4ea]' : ''}`}
                                                    >
                                                        <button
                                                            onClick={() =>
                                                                toggleAcc(a.id)
                                                            }
                                                            aria-label={
                                                                on
                                                                    ? `Bỏ chọn ${a.name}`
                                                                    : `Chọn ${a.name}`
                                                            }
                                                            className={`grid h-5 w-5 flex-none place-items-center rounded-[6px] border text-[11px] font-bold transition ${
                                                                on
                                                                    ? 'border-grass bg-grass text-white'
                                                                    : 'border-[#c4cca8] bg-white text-transparent'
                                                            }`}
                                                        >
                                                            ✓
                                                        </button>
                                                        {a.thumbnail ? (
                                                            <img
                                                                src={
                                                                    a.thumbnail
                                                                }
                                                                alt={a.name}
                                                                className="h-10 w-10 flex-none rounded-[9px] object-cover"
                                                            />
                                                        ) : (
                                                            <div
                                                                className="h-10 w-10 flex-none rounded-[9px]"
                                                                style={{
                                                                    background:
                                                                        gradFor(
                                                                            a
                                                                                .category
                                                                                .slug,
                                                                        ),
                                                                }}
                                                            />
                                                        )}
                                                        <div className="min-w-[110px] flex-1">
                                                            <Link
                                                                href={`/thiet-bi/${a.slug}`}
                                                                className="text-[13px] font-bold leading-tight text-ink hover:text-grass"
                                                            >
                                                                {a.name}
                                                            </Link>
                                                            <div className="text-[11px] text-moss">
                                                                <span className="font-mono font-bold text-grass">
                                                                    {money(
                                                                        a.price_per_day,
                                                                    )}
                                                                </span>
                                                                /ngày
                                                                {scarce && (
                                                                    <span className="text-campfire">
                                                                        {' '}
                                                                        · còn{' '}
                                                                        {accCap(
                                                                            a,
                                                                        )}{' '}
                                                                        bộ
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <div
                                                            className={`flex items-center overflow-hidden rounded-[8px] border border-cardBorder ${on ? '' : 'opacity-40'}`}
                                                        >
                                                            <button
                                                                onClick={() =>
                                                                    bumpAccQty(
                                                                        a,
                                                                        -1,
                                                                    )
                                                                }
                                                                disabled={!on}
                                                                className="h-7 w-[26px] bg-[#f1f4ea] text-[14px] text-grass"
                                                            >
                                                                −
                                                            </button>
                                                            <span className="w-[28px] text-center font-mono text-[12px] font-bold">
                                                                {q}
                                                            </span>
                                                            <button
                                                                onClick={() =>
                                                                    bumpAccQty(
                                                                        a,
                                                                        1,
                                                                    )
                                                                }
                                                                disabled={
                                                                    !on ||
                                                                    q >= cap
                                                                }
                                                                className="h-7 w-[26px] bg-[#f1f4ea] text-[14px] text-grass disabled:opacity-50"
                                                            >
                                                                +
                                                            </button>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>

                                        <div
                                            className="flex flex-wrap items-center justify-between gap-2.5 border-t border-[#eef2e3] px-3 py-3"
                                            style={{ background: '#f8faf4' }}
                                        >
                                            <div>
                                                <div className="text-[11.5px] text-[#8a967a]">
                                                    Phần thêm{' '}
                                                    {days > 0
                                                        ? `(${days} ngày)`
                                                        : ''}
                                                </div>
                                                <div className="font-mono text-[15px] font-bold text-grass">
                                                    {days > 0 ? (
                                                        money(accPerDay * days)
                                                    ) : (
                                                        <>
                                                            {money(accPerDay)}
                                                            <span className="font-sans text-[11px] font-normal text-[#8a967a]">
                                                                /ngày
                                                            </span>
                                                        </>
                                                    )}
                                                </div>
                                                {accDeposit > 0 && (
                                                    <div className="font-mono text-[10.5px] text-campfire">
                                                        + cọc{' '}
                                                        {money(accDeposit)}
                                                    </div>
                                                )}
                                            </div>
                                            <button
                                                onClick={addAccessories}
                                                disabled={
                                                    !start ||
                                                    !end ||
                                                    selectedAccessories.length ===
                                                        0
                                                }
                                                className="h-[40px] rounded-control px-4 text-[12.5px] font-bold text-white transition disabled:cursor-not-allowed"
                                                style={{
                                                    background:
                                                        start &&
                                                        end &&
                                                        selectedAccessories.length >
                                                            0
                                                            ? '#557A2B'
                                                            : '#c4cfae',
                                                }}
                                            >
                                                {start && end
                                                    ? `Thêm ${selectedAccessories.length} món vào giỏ`
                                                    : 'Chọn ngày để thêm'}
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </section>
                        )}
                    </div>
                </div>

                {/* 1.4 + feedback #4: nội dung chi tiết full-width, bố cục magazine
                    text/ảnh xen kẽ trái–phải (HTML TipTap đã sanitize server) */}
                {product.setup_content && (
                    <section id="chi-tiet" className="mt-12 scroll-mt-24">
                        <div className="mb-1 font-mono text-[12px] font-bold tracking-[0.14em] text-campfire">
                            CHI TIẾT SẢN PHẨM
                        </div>
                        <h2 className="mb-6 text-[20px] font-extrabold tracking-tight text-ink">
                            Về {product.name}
                        </h2>
                        <MagazineContent html={product.setup_content} />
                    </section>
                )}

                {/* 1.5: đánh giá chuyển xuống cuối trang (carousel + form + modal) */}
                <section className="mt-12">
                    <ProductReviews
                        submitUrl={route('reviews.store', product.slug)}
                        targetName={product.name}
                        reviews={reviews}
                        summary={review_summary}
                        isLoggedIn={!!auth.user}
                    />
                </section>

                {/* 1.6: "You may also like" — admin chọn ở form sản phẩm, dưới cùng trang */}
                {related_products.length > 0 && (
                    <section className="mt-12">
                        <div className="mb-1 font-mono text-[12px] font-bold tracking-[0.14em] text-campfire">
                            GỢI Ý CHO BẠN
                        </div>
                        <h2 className="mb-4 text-[20px] font-extrabold tracking-tight text-ink">
                            Có thể bạn cũng thích
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {related_products.map((p, i) => (
                                <ProductCard key={p.id} p={p} index={i} />
                            ))}
                        </div>
                    </section>
                )}
            </main>

            {/* Lightbox: xem ảnh/video cỡ lớn, không bị cắt */}
            {lightboxOpen &&
                (activeSlide.type === 'img' ||
                    activeSlide.type === 'video') && (
                    <div
                        onClick={() => setLightboxOpen(false)}
                        className="fixed inset-0 z-[95] flex items-center justify-center p-6"
                        style={{ background: 'rgba(12,16,8,.82)' }}
                    >
                        <button
                            onClick={() => setLightboxOpen(false)}
                            aria-label="Đóng"
                            className="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/15 text-[20px] text-white"
                        >
                            ×
                        </button>
                        {gallery.length > 1 && (
                            <>
                                <button
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        goImg(-1);
                                    }}
                                    aria-label="Ảnh trước"
                                    className="absolute left-4 top-1/2 z-10 grid h-12 w-12 -translate-y-1/2 place-items-center rounded-full bg-white/15 text-[28px] text-white transition hover:bg-white/30"
                                >
                                    ‹
                                </button>
                                <button
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        goImg(1);
                                    }}
                                    aria-label="Ảnh sau"
                                    className="absolute right-4 top-1/2 z-10 grid h-12 w-12 -translate-y-1/2 place-items-center rounded-full bg-white/15 text-[28px] text-white transition hover:bg-white/30"
                                >
                                    ›
                                </button>
                                <span className="absolute bottom-5 left-1/2 -translate-x-1/2 rounded-pill bg-white/15 px-3 py-1 font-mono text-[12px] text-white">
                                    {activeImg + 1}/{gallery.length}
                                </span>
                            </>
                        )}
                        {activeSlide.type === 'img' ? (
                            <img
                                srcSet={activeSlide.srcset ?? undefined}
                                /* Lightbox chiếm gần hết màn → cần bậc lớn nhất. */
                                sizes="100vw"
                                src={activeSlide.src}
                                alt={product.name}
                                onClick={(e) => e.stopPropagation()}
                                className="max-h-[90vh] max-w-[92vw] rounded-[12px] object-contain"
                            />
                        ) : (
                            <video
                                key={activeSlide.src}
                                src={activeSlide.src}
                                controls
                                autoPlay
                                muted
                                loop
                                playsInline
                                onClick={(e) => e.stopPropagation()}
                                className="max-h-[90vh] max-w-[92vw] rounded-[12px] object-contain"
                            />
                        )}
                    </div>
                )}

            {/* Popup: giỏ chỉ giữ 1 vị trí */}
            {conflict && (
                <div
                    className="fixed inset-0 z-[200] flex items-center justify-center bg-black/45 px-4"
                    onClick={() => setConflict(null)}
                >
                    <div
                        className="w-full max-w-[420px] rounded-[18px] bg-white p-6 shadow-xl"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div
                            className="mb-4 grid h-12 w-12 place-items-center rounded-full"
                            style={{ background: '#f7e7da' }}
                        >
                            <svg
                                width="24"
                                height="24"
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <path
                                    d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"
                                    fill="#C97B36"
                                    stroke="#C97B36"
                                    strokeWidth="1.5"
                                    strokeLinejoin="round"
                                />
                                <circle cx="12" cy="10" r="2.6" fill="#fff" />
                            </svg>
                        </div>
                        <h2 className="mb-2 text-[18px] font-extrabold text-ink">
                            Giỏ đang thuê ở nơi khác
                        </h2>
                        <p className="mb-5 text-[14px] leading-[1.55] text-moss">
                            Giỏ hiện tại đang thuê tại{' '}
                            <span className="font-semibold text-pine">
                                {conflict.cartLocations
                                    .map((l) => l.name)
                                    .join(' · ')}
                            </span>
                            . “
                            <span className="font-semibold text-pine">
                                {conflict.pending
                                    .map((l) => l.name)
                                    .join('” · “')}
                            </span>
                            ”
                            {conflict.pending.length === 1 ? (
                                <>
                                    {' '}
                                    chỉ phục vụ tại{' '}
                                    <span className="font-semibold text-pine">
                                        {(conflict.pending[0].locations ?? [])
                                            .map((l) => l.name)
                                            .join(' · ')}
                                    </span>{' '}
                                    nên không thể thêm cùng giỏ.
                                </>
                            ) : (
                                <>
                                    {' '}
                                    không phục vụ tại vị trí của giỏ nên không
                                    thể thêm cùng giỏ.
                                </>
                            )}{' '}
                            Mỗi đơn chỉ thuê tại một vị trí.
                        </p>
                        <div className="flex flex-col gap-2.5">
                            <button
                                onClick={replaceCart}
                                className="h-[46px] rounded-control bg-grass px-5 text-[14px] font-bold text-white transition hover:bg-pine"
                            >
                                Xoá giỏ hiện tại &amp; thêm{' '}
                                {conflict.pending.length === 1
                                    ? 'món này'
                                    : `${conflict.pending.length} món mới`}
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
