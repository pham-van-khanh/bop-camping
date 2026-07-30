import BiomeHero from '@/Components/site/BiomeHero';
import CampingGuideModal, {
    type ProvinceGroup,
} from '@/Components/site/CampingGuideModal';
import ComboCard, { type ComboCardData } from '@/Components/site/ComboCard';
import FaqSection, { type FaqItem } from '@/Components/site/FaqSection';
import HeroSlideshow from '@/Components/site/HeroSlideshow';
import HomeServingPanel, {
    type ServiceLocation,
    type SuggestedSpot,
} from '@/Components/site/HomeServingPanel';
import ProductCard from '@/Components/site/ProductCard';
import RentalDateModal from '@/Components/site/RentalDateModal';
import SystemReviews, {
    type SystemReview,
} from '@/Components/site/SystemReviews';
import SiteLayout from '@/Layouts/SiteLayout';
import type { ProductResource } from '@/types/product';
import { Head, Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { ReactNode, useState } from 'react';

const EASE: [number, number, number, number] = [0.2, 0.7, 0.2, 1];
const reveal = {
    initial: { opacity: 0, y: 18 },
    whileInView: { opacity: 1, y: 0 },
    viewport: { once: true, amount: 0.2 },
    transition: { duration: 0.6, ease: EASE },
};

const biomes = ['Đồng cỏ', 'Rừng thông', 'Núi cao', 'Bờ biển'];

const steps = [
    {
        n: 1,
        t: 'Chọn đồ và ngày',
        d: 'Lọc theo nhu cầu, chọn ngày nhận và ngày trả. Tụi mình hiện luôn món nào còn trống trong khoảng đó.',
    },
    {
        n: 2,
        t: 'Gửi yêu cầu thuê online',
        d: 'Để lại tên và số điện thoại — tụi mình gọi xác nhận trong 15–30 phút. Không cần trả trước, cọc và tiền thuê thu khi nhận đồ (COD).',
    },
    {
        n: 3,
        t: 'Nhận đồ và lên đường',
        d: 'Tụi mình giao tận nơi nội thành. Trả đồ đúng hẹn khi về, hoàn cọc ngay.',
    },
];

type HeroBanner = { src: string; title: string };
type PromoBanner = {
    id: number;
    image: string;
    title: string | null;
    subtitle: string | null;
    href: string | null;
};

interface Props {
    featured: ProductResource[];
    featured_combos: ComboCardData[];
    faqs: FaqItem[];
    system_reviews: SystemReview[];
    review_stat: { avg: number; count: number };
    service_locations: ServiceLocation[];
    suggested_spots: SuggestedSpot[];
    camping_provinces: ProvinceGroup[];
    hero_banners: HeroBanner[];
    promo_banners: PromoBanner[];
}

export default function Home({
    featured,
    featured_combos,
    faqs,
    system_reviews,
    review_stat,
    service_locations,
    suggested_spots,
    camping_provinces,
    hero_banners,
    promo_banners,
}: Props) {
    const [guideOpen, setGuideOpen] = useState(false);
    const [dateOpen, setDateOpen] = useState(false);
    const openCities = service_locations.filter((l) => l.status === 'open');
    const cities =
        openCities.map((l) => l.name).join(' hoặc ') || 'Vinh hoặc Hà Nội';
    // Cam kết + dữ liệu thật (không bịa số): COD · hoàn cọc · đánh giá thật (nếu có) · giao tận nơi tới TP thật.
    const stats: [string, string][] = [
        ['COD', 'Trả tiền khi nhận'],
        ['Hoàn cọc', 'Khi trả đồ đúng hẹn'],
        review_stat.count > 0
            ? [`${review_stat.avg}★`, `${review_stat.count} đánh giá`]
            : ['Theo ngày', 'Nhận – trả linh hoạt'],
        [
            'Tận nơi',
            openCities.length > 0
                ? openCities.map((l) => l.name).join(' · ')
                : 'Giao nội thành',
        ],
    ];
    return (
        <>
            <Head title="Cho thuê thiết bị cắm trại" />

            {/* Hero ảnh full-bleed (slideshow) + panel "Đang phục vụ tại" bên phải */}
            <HeroSlideshow
                slides={hero_banners}
                aside={
                    <HomeServingPanel
                        locations={service_locations}
                        suggested={suggested_spots}
                        onOpenGuide={() => setGuideOpen(true)}
                    />
                }
            >
                <motion.div
                    initial={{ opacity: 0, y: 22 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.7, ease: EASE }}
                >
                    <div
                        className="mb-[22px] inline-flex items-center gap-2 rounded-pill px-3 py-[7px]"
                        style={{
                            background: 'rgba(85,122,43,.34)',
                            border: '1px solid rgba(207,224,168,.4)',
                            backdropFilter: 'blur(6px)',
                        }}
                    >
                        <span
                            className="h-[7px] w-[7px] rounded-full"
                            style={{
                                background: '#9bd34b',
                                boxShadow: '0 0 8px #9bd34b',
                            }}
                        />
                        <span className="font-mono text-[12px] tracking-[0.04em] text-[#eaf3d6]">
                            CHO THUÊ THEO NGÀY · CỌC LINH HOẠT · COD
                        </span>
                    </div>
                    <h1
                        className="mb-5 font-extrabold text-white"
                        style={{
                            fontSize: 'clamp(38px,6vw,68px)',
                            lineHeight: 1.02,
                            letterSpacing: '-0.025em',
                            textShadow: '0 4px 30px rgba(0,0,0,.45)',
                        }}
                    >
                        Mang cả khu trại
                        <br />
                        <span style={{ color: '#bfe06a' }}>đi bất cứ đâu.</span>
                    </h1>
                    <p
                        className="mb-[30px] max-w-[520px] text-white/90"
                        style={{
                            fontSize: 'clamp(16px,1.7vw,20px)',
                            lineHeight: 1.6,
                            textShadow: '0 2px 16px rgba(0,0,0,.4)',
                        }}
                    >
                        Lều, bếp, túi ngủ, đèn trại... thuê đủ bộ cho chuyến đi.
                        Chọn ngày nhận và ngày trả, tụi mình kiểm tra còn hàng
                        và giao tận nơi. Trả tiền khi nhận.
                    </p>
                    <div className="flex flex-wrap gap-3">
                        <Link
                            href="/thiet-bi"
                            className="grid h-[54px] place-items-center rounded-[13px] bg-grass px-7 font-bold text-white transition hover:-translate-y-0.5"
                            style={{
                                boxShadow: '0 16px 34px -12px rgba(0,0,0,.6)',
                            }}
                        >
                            Xem thiết bị
                        </Link>
                        {/* Ô đặt lịch trên banner — bấm mở popup lịch (to hơn dải inline ở PC).
                            Thay cho nút "Tra cứu đơn của tôi" cũ; tra cứu đơn vẫn ở header + footer. */}
                        <button
                            type="button"
                            onClick={() => setDateOpen(true)}
                            aria-haspopup="dialog"
                            className="group flex h-[54px] items-center gap-3 rounded-[13px] pl-4 pr-3 text-left transition hover:bg-white/20"
                            style={{
                                border: '1px solid rgba(255,255,255,.5)',
                                background: 'rgba(255,255,255,.12)',
                                backdropFilter: 'blur(6px)',
                            }}
                        >
                            <svg
                                width="19"
                                height="19"
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                                className="flex-none text-[#bfe06a]"
                            >
                                <rect
                                    x="3"
                                    y="5"
                                    width="18"
                                    height="16"
                                    rx="3"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                />
                                <path
                                    d="M3 10h18M8 3v4M16 3v4"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    strokeLinecap="round"
                                />
                            </svg>
                            <span className="leading-tight">
                                <span className="block font-mono text-[10.5px] tracking-[0.1em] text-white/70">
                                    NGÀY NHẬN – NGÀY TRẢ
                                </span>
                                <span className="block text-[15px] font-bold text-white">
                                    Chọn ngày đi
                                </span>
                            </span>
                            <span
                                className="ml-1 grid h-[34px] w-[34px] flex-none place-items-center rounded-[10px] text-white transition group-hover:bg-grass"
                                style={{ background: 'rgba(85,122,43,.85)' }}
                                aria-hidden="true"
                            >
                                →
                            </span>
                        </button>
                    </div>
                </motion.div>
            </HeroSlideshow>

            {/* Một bộ đồ, đi khắp địa hình (cảnh 3D đổi biôm) */}
            <section
                className="mx-auto grid max-w-[1400px] items-center gap-10 px-5 pb-2.5 pt-12"
                style={{
                    gridTemplateColumns: 'repeat(auto-fit, minmax(330px, 1fr))',
                }}
            >
                <motion.div {...reveal}>
                    <div className="mb-2 font-mono text-[12px] tracking-[0.1em] text-campfire">
                        CÙNG MỘT KHU TRẠI
                    </div>
                    <h2
                        className="mb-3.5 font-extrabold tracking-tight text-ink"
                        style={{ fontSize: 'clamp(24px,3vw,34px)' }}
                    >
                        Một bộ đồ, đi khắp địa hình
                    </h2>
                    <p
                        className="mb-[18px] max-w-[480px] text-[#3f4a32]"
                        style={{
                            fontSize: 'clamp(15px,1.6vw,18px)',
                            lineHeight: 1.65,
                        }}
                    >
                        Từ đồng cỏ, rừng thông, lên núi cao rồi ra bờ biển, vẫn
                        là khu trại đó. Tụi mình gói sẵn từng combo theo địa
                        hình để bạn chỉ việc xách đi và tận hưởng.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {biomes.map((b) => (
                            <span
                                key={b}
                                className="rounded-pill px-3.5 py-[7px] font-mono text-[13px]"
                                style={{
                                    color: '#3a5a1f',
                                    background: '#e7eed5',
                                    border: '1px solid #d3ddb9',
                                }}
                            >
                                {b}
                            </span>
                        ))}
                    </div>
                </motion.div>

                <motion.div {...reveal}>
                    <BiomeHero wind={1.3} />
                    <p className="mt-3.5 text-center font-mono text-[12px] tracking-[0.02em] text-[#8a967a]">
                        Cùng một khu trại · đổi địa điểm theo mùa
                    </p>
                </motion.div>
            </section>

            {/* Dải số liệu */}
            <section className="mx-auto max-w-[1400px] px-5 py-2">
                <motion.div
                    {...reveal}
                    className="grid overflow-hidden rounded-[18px]"
                    style={{
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(150px, 1fr))',
                        gap: 1,
                        background: '#E3E8D6',
                        border: '1px solid #E3E8D6',
                    }}
                >
                    {stats.map(([n, l], i) => (
                        <div
                            key={i}
                            className="bg-card px-[18px] py-[22px] text-center"
                        >
                            <div className="font-mono text-[26px] font-bold text-grass">
                                {n}
                            </div>
                            <div className="mt-[5px] text-[13px] text-moss">
                                {l}
                            </div>
                        </div>
                    ))}
                </motion.div>
            </section>

            {/* Dải banner khuyến mãi (promo) — admin quản lý */}
            {promo_banners.length > 0 && (
                <section className="mx-auto max-w-[1400px] px-5 py-6">
                    <div className="flex flex-col gap-4">
                        {promo_banners.map((b) => (
                            <PromoBannerCard key={b.id} banner={b} />
                        ))}
                    </div>
                </section>
            )}

            {/* Khách nói gì (đánh giá hệ thống) */}
            {system_reviews.length > 0 && (
                <section className="mx-auto max-w-[1400px] px-5 pb-2 pt-12">
                    <motion.div {...reveal} className="mb-6 text-center">
                        <div className="mb-2 font-mono text-[12px] tracking-[0.1em] text-campfire">
                            KHÁCH NÓI GÌ
                        </div>
                        <h2
                            className="font-extrabold tracking-tight text-ink"
                            style={{ fontSize: 'clamp(24px,3vw,32px)' }}
                        >
                            Trải nghiệm thật từ những chuyến đi
                        </h2>
                    </motion.div>
                    <motion.div {...reveal}>
                        <SystemReviews reviews={system_reviews} />
                    </motion.div>
                </section>
            )}

            {/* Thiết bị nổi bật */}
            <section className="mx-auto max-w-[1400px] px-5 pb-2.5 pt-12">
                <div className="mb-[22px] flex items-end justify-between gap-4">
                    <motion.div {...reveal}>
                        <div className="mb-2 font-mono text-[12px] tracking-[0.1em] text-campfire">
                            ĐƯỢC THUÊ NHIỀU
                        </div>
                        <h2
                            className="font-extrabold tracking-tight text-ink"
                            style={{ fontSize: 'clamp(24px,3vw,32px)' }}
                        >
                            Thiết bị nổi bật
                        </h2>
                    </motion.div>
                    <Link
                        href="/thiet-bi"
                        className="shrink-0 whitespace-nowrap font-bold text-grass hover:text-pine"
                    >
                        Xem tất cả →
                    </Link>
                </div>
                <div
                    className="grid gap-[18px]"
                    style={{
                        gridTemplateColumns:
                            'repeat(auto-fill, minmax(290px, 1fr))',
                    }}
                >
                    {featured.map((p, i) => (
                        <ProductCard key={p.id} p={p} compact index={i} />
                    ))}
                </div>
            </section>

            {/* Combo tiết kiệm (PRD combo mục 6 — 3–4 combo nổi bật theo sort_order) */}
            {featured_combos.length > 0 && (
                <section className="mx-auto max-w-[1400px] px-5 pb-2.5 pt-12">
                    <div className="mb-[22px] flex items-end justify-between gap-4">
                        <motion.div {...reveal}>
                            <div className="mb-2 font-mono text-[12px] tracking-[0.1em] text-campfire">
                                THUÊ TRỌN BỘ · RẺ HƠN THUÊ LẺ
                            </div>
                            <h2
                                className="font-extrabold tracking-tight text-ink"
                                style={{ fontSize: 'clamp(24px,3vw,32px)' }}
                            >
                                Combo tiết kiệm
                            </h2>
                        </motion.div>
                        <Link
                            href="/combos"
                            className="shrink-0 whitespace-nowrap font-bold text-grass hover:text-pine"
                        >
                            Xem tất cả →
                        </Link>
                    </div>
                    <div
                        className="grid gap-[18px]"
                        style={{
                            gridTemplateColumns:
                                'repeat(auto-fill, minmax(290px, 1fr))',
                        }}
                    >
                        {featured_combos.map((c, i) => (
                            <ComboCard key={c.id} c={c} index={i} />
                        ))}
                    </div>
                </section>
            )}

            {/* Thuê đồ 3 bước */}
            <section className="mx-auto max-w-[1400px] px-5 pb-5 pt-[54px]">
                <motion.h2
                    {...reveal}
                    className="mb-2 text-center font-extrabold tracking-tight text-ink"
                    style={{ fontSize: 'clamp(24px,3vw,32px)' }}
                >
                    Thuê đồ trong 3 bước
                </motion.h2>
                <motion.p
                    {...reveal}
                    className="mx-auto mb-[34px] max-w-[520px] text-center text-moss"
                >
                    Không cần tài khoản rườm rà. Chỉ cần số điện thoại và tên là
                    xong.
                </motion.p>
                <div
                    className="grid gap-[18px]"
                    style={{
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(250px, 1fr))',
                    }}
                >
                    {steps.map((s, i) => (
                        <motion.div
                            key={s.n}
                            initial={{ opacity: 0, y: 18 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true, amount: 0.3 }}
                            transition={{
                                duration: 0.6,
                                delay: i * 0.08,
                                ease: EASE,
                            }}
                            className="rounded-card border border-cardBorder bg-card px-[22px] py-[26px]"
                        >
                            <span className="mb-4 grid h-[34px] w-[34px] place-items-center rounded-[10px] bg-grass font-mono text-[13px] font-bold text-white">
                                {s.n}
                            </span>
                            <h3 className="mb-[7px] text-[17px] font-bold text-ink">
                                {s.t}
                            </h3>
                            <p className="text-[14px] leading-[1.55] text-moss">
                                {s.d}
                            </p>
                        </motion.div>
                    ))}
                </div>
            </section>

            {/* Câu hỏi thường gặp (admin quản lý) — ẩn khi chưa có FAQ nào */}
            <FaqSection faqs={faqs} />

            {/* CTA */}
            <section className="mx-auto my-[46px] max-w-[1400px] px-5">
                <motion.div
                    {...reveal}
                    className="relative overflow-hidden rounded-[24px] text-center"
                    style={{
                        background:
                            'linear-gradient(120deg,#2C3D22,#3f5a2a 60%,#557A2B)',
                        padding: 'clamp(34px,5vw,56px)',
                    }}
                >
                    <div
                        className="absolute"
                        style={{
                            top: -40,
                            right: -30,
                            width: 180,
                            height: 180,
                            borderRadius: '50%',
                            background: 'rgba(201,123,54,.28)',
                            filter: 'blur(8px)',
                        }}
                    />
                    <h2
                        className="relative mb-3 font-extrabold tracking-tight text-white"
                        style={{ fontSize: 'clamp(26px,3.4vw,38px)' }}
                    >
                        Sẵn sàng cho chuyến đi tiếp theo?
                    </h2>
                    <p
                        className="relative mx-auto mb-[26px] max-w-[480px] text-[17px]"
                        style={{ color: '#d6e4bd' }}
                    >
                        Chọn đồ hôm nay, nhận đồ ngay cuối tuần. Giao nhận nội
                        thành miễn phí cho đơn từ 300.000đ.
                    </p>
                    <Link
                        href="/thiet-bi"
                        className="relative inline-grid h-[54px] place-items-center rounded-[14px] bg-white px-8 text-[17px] font-bold text-pine transition hover:-translate-y-0.5"
                    >
                        Bắt đầu chọn đồ
                    </Link>
                </motion.div>
            </section>

            {guideOpen && (
                <CampingGuideModal
                    provinces={camping_provinces}
                    cities={cities}
                    onClose={() => setGuideOpen(false)}
                />
            )}

            {dateOpen && (
                <RentalDateModal
                    serviceLocations={openCities}
                    onClose={() => setDateOpen(false)}
                />
            )}
        </>
    );
}

/** Banner khuyến mãi: ảnh + tiêu đề/mô tả overlay, bấm dẫn tới link (nội bộ hoặc ngoài). */
function PromoBannerCard({ banner }: { banner: PromoBanner }) {
    const content = (
        <div
            className="relative overflow-hidden rounded-[18px] border border-cardBorder"
            style={{ aspectRatio: '1100 / 300' }}
        >
            {/* Dải ngang gọn — ảnh cắt vừa khung (object-cover) */}
            <img
                src={banner.image}
                alt={banner.title ?? ''}
                className="h-full w-full object-cover"
            />
            {(banner.title || banner.subtitle) && (
                <div
                    className="absolute inset-0 flex flex-col justify-center gap-1.5 px-7"
                    style={{
                        background:
                            'linear-gradient(90deg, rgba(24,35,15,.62), rgba(24,35,15,.08) 72%)',
                    }}
                >
                    {banner.title && (
                        <h3
                            className="font-extrabold text-white"
                            style={{
                                fontSize: 'clamp(20px,3vw,30px)',
                                textShadow: '0 2px 12px rgba(0,0,0,.5)',
                            }}
                        >
                            {banner.title}
                        </h3>
                    )}
                    {banner.subtitle && (
                        <p
                            className="max-w-[480px] text-[14px] text-white/90"
                            style={{ textShadow: '0 1px 8px rgba(0,0,0,.5)' }}
                        >
                            {banner.subtitle}
                        </p>
                    )}
                </div>
            )}
        </div>
    );

    if (!banner.href) return content;

    return /^https?:\/\//.test(banner.href) ? (
        <a
            href={banner.href}
            target="_blank"
            rel="noreferrer"
            className="block transition hover:opacity-95"
        >
            {content}
        </a>
    ) : (
        <Link href={banner.href} className="block transition hover:opacity-95">
            {content}
        </Link>
    );
}

Home.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
