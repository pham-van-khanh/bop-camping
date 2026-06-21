import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { lazy, Suspense } from 'react';
import Header from '@/Components/site/Header';
import Footer from '@/Components/site/Footer';
import HeroSlideshow from '@/Components/site/HeroSlideshow';

const CampDiorama = lazy(() => import('@/Components/CampDiorama'));

const EASE: [number, number, number, number] = [0.2, 0.7, 0.2, 1];
const reveal = {
    initial: { opacity: 0, y: 18 },
    whileInView: { opacity: 1, y: 0 },
    viewport: { once: true, amount: 0.2 },
    transition: { duration: 0.6, ease: EASE },
};

const money = (n: number) => n.toLocaleString('vi-VN') + 'đ';

const featured = [
    { id: 1, name: 'Lều 2 lớp 4 người', price: 180000, deposit: 500000, stock: 3, cat: 'Lều trại', grad: 'linear-gradient(150deg,#3f6322,#6e9440)' },
    { id: 2, name: 'Túi ngủ lông vũ -10°C', price: 70000, deposit: 250000, stock: 2, cat: 'Túi ngủ', grad: 'linear-gradient(150deg,#2c5a6e,#6ea7bd)' },
    { id: 3, name: 'Bếp gas mini + bình', price: 45000, deposit: 150000, stock: 6, cat: 'Bếp & nấu', grad: 'linear-gradient(150deg,#7a5a2a,#c79a52)' },
    { id: 4, name: 'Đèn lều tích điện', price: 30000, deposit: 100000, stock: 8, cat: 'Đèn & sáng', grad: 'linear-gradient(150deg,#4a4f2a,#9aa05a)' },
];

const stats = [
    ['120+', 'Bộ thiết bị'],
    ['4.9★', 'Đánh giá khách'],
    ['2.000+', 'Chuyến đi'],
    ['Nội thành', 'Giao nhận tận nơi'],
];

const biomes = ['Đồng cỏ', 'Rừng thông', 'Núi cao', 'Bờ biển'];

const steps = [
    { n: 1, t: 'Chọn đồ và ngày', d: 'Lọc theo nhu cầu, chọn ngày nhận và ngày trả. Tụi mình hiện luôn món nào còn trống trong khoảng đó.' },
    { n: 2, t: 'Đặt giữ chỗ online', d: 'Để lại tên và số điện thoại. Không cần trả trước, cọc và tiền thuê thu khi nhận đồ (COD).' },
    { n: 3, t: 'Nhận đồ và lên đường', d: 'Tụi mình giao tận nơi nội thành. Trả đồ đúng hẹn khi về, hoàn cọc ngay.' },
];

function ProductCard({ p }: { p: (typeof featured)[number] }) {
    const low = p.stock <= 2;
    return (
        <Link
            href={`/thiet-bi/${p.id}`}
            className="group flex flex-col overflow-hidden rounded-card border border-cardBorder bg-card transition duration-200 hover:-translate-y-1 hover:shadow-cardhover"
        >
            <div className="relative h-40" style={{ background: p.grad }}>
                <div className="absolute inset-0" style={{ background: 'radial-gradient(120px 90px at 78% 22%, rgba(255,255,255,.35), transparent 60%)' }} />
                <div className="absolute inset-x-0 bottom-0 h-16" style={{ background: 'linear-gradient(180deg, rgba(24,35,15,0), rgba(24,35,15,.5))' }} />
                <span
                    className={`absolute left-3 top-3 rounded-pill px-2.5 py-1 font-mono text-[11px] font-bold text-white ${low ? 'bg-campfire' : ''}`}
                    style={low ? undefined : { background: 'rgba(44,61,34,.72)' }}
                >
                    {low ? `Sắp hết · ${p.stock} bộ` : `Còn ${p.stock} bộ`}
                </span>
                <span className="absolute bottom-3 left-3 font-mono text-[11px] tracking-[0.05em] text-white/90">{p.cat}</span>
            </div>
            <div className="flex flex-1 flex-col px-4 pb-4 pt-3.5">
                <div className="min-h-[39px] text-[15.5px] font-bold leading-tight text-ink">{p.name}</div>
                <div className="mt-2.5 flex items-end justify-between">
                    <div className="font-mono text-[17px] font-bold text-ink">
                        {money(p.price)}<span className="font-sans text-[12px] font-normal text-[#8a967a]">/ngày</span>
                    </div>
                    <div className="font-mono text-[11px] text-campfire">cọc {money(p.deposit)}</div>
                </div>
            </div>
        </Link>
    );
}

export default function Home({ auth }: PageProps) {
    return (
        <>
            <Head title="Cho thuê thiết bị cắm trại" />
            <Header cartCount={0} userName={auth?.user?.name} />

            {/* Hero ảnh full-bleed (slideshow) */}
            <HeroSlideshow>
                <motion.div initial={{ opacity: 0, y: 22 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.7, ease: EASE }}>
                    <div
                        className="mb-[22px] inline-flex items-center gap-2 rounded-pill px-3 py-[7px]"
                        style={{ background: 'rgba(85,122,43,.34)', border: '1px solid rgba(207,224,168,.4)', backdropFilter: 'blur(6px)' }}
                    >
                        <span className="h-[7px] w-[7px] rounded-full" style={{ background: '#9bd34b', boxShadow: '0 0 8px #9bd34b' }} />
                        <span className="font-mono text-[12px] tracking-[0.04em] text-[#eaf3d6]">CHO THUÊ THEO NGÀY · CỌC LINH HOẠT · COD</span>
                    </div>
                    <h1
                        className="mb-5 font-extrabold text-white"
                        style={{ fontSize: 'clamp(38px,6vw,68px)', lineHeight: 1.02, letterSpacing: '-0.025em', textShadow: '0 4px 30px rgba(0,0,0,.45)' }}
                    >
                        Mang cả khu trại<br />
                        <span style={{ color: '#bfe06a' }}>đi bất cứ đâu.</span>
                    </h1>
                    <p
                        className="mb-[30px] max-w-[520px] text-white/90"
                        style={{ fontSize: 'clamp(16px,1.7vw,20px)', lineHeight: 1.6, textShadow: '0 2px 16px rgba(0,0,0,.4)' }}
                    >
                        Lều, bếp, túi ngủ, đèn trại... thuê đủ bộ cho chuyến đi. Chọn ngày nhận và ngày trả, tụi mình kiểm tra còn hàng và giao tận nơi. Trả tiền khi nhận.
                    </p>
                    <div className="flex flex-wrap gap-3">
                        <Link
                            href="/thiet-bi"
                            className="grid h-[54px] place-items-center rounded-[13px] bg-grass px-7 font-bold text-white transition hover:-translate-y-0.5"
                            style={{ boxShadow: '0 16px 34px -12px rgba(0,0,0,.6)' }}
                        >
                            Xem thiết bị
                        </Link>
                        <Link
                            href="/tra-cuu"
                            className="grid h-[54px] place-items-center rounded-[13px] px-[26px] font-semibold text-white transition hover:bg-white/20"
                            style={{ border: '1px solid rgba(255,255,255,.5)', background: 'rgba(255,255,255,.12)', backdropFilter: 'blur(6px)' }}
                        >
                            Tra cứu đơn của tôi
                        </Link>
                    </div>
                </motion.div>
            </HeroSlideshow>

            {/* Một bộ đồ, đi khắp địa hình (cảnh 3D đổi biôm) */}
            <section className="mx-auto grid max-w-[1200px] items-center gap-10 px-5 pb-2.5 pt-12" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(330px, 1fr))' }}>
                <motion.div {...reveal}>
                    <div className="mb-2 font-mono text-[12px] tracking-[0.1em] text-campfire">CÙNG MỘT KHU TRẠI</div>
                    <h2 className="mb-3.5 font-extrabold tracking-tight text-ink" style={{ fontSize: 'clamp(24px,3vw,34px)' }}>
                        Một bộ đồ, đi khắp địa hình
                    </h2>
                    <p className="mb-[18px] max-w-[480px] text-[#3f4a32]" style={{ fontSize: 'clamp(15px,1.6vw,18px)', lineHeight: 1.65 }}>
                        Từ đồng cỏ, rừng thông, lên núi cao rồi ra bờ biển, vẫn là khu trại đó. Tụi mình gói sẵn từng combo theo địa hình để bạn chỉ việc xách đi và tận hưởng.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {biomes.map((b) => (
                            <span
                                key={b}
                                className="rounded-pill px-3.5 py-[7px] font-mono text-[13px]"
                                style={{ color: '#3a5a1f', background: '#e7eed5', border: '1px solid #d3ddb9' }}
                            >
                                {b}
                            </span>
                        ))}
                    </div>
                </motion.div>

                <motion.div {...reveal}>
                    <div className="overflow-hidden rounded-[20px] shadow-hero" style={{ background: 'linear-gradient(180deg,#bfe1f2,#e7f1ec)' }}>
                        <div className="h-[360px]">
                            <Suspense fallback={<div className="h-full w-full" />}>
                                <CampDiorama />
                            </Suspense>
                        </div>
                    </div>
                    <p className="mt-3.5 text-center font-mono text-[12px] tracking-[0.02em] text-[#8a967a]">
                        Cùng một khu trại · đổi địa điểm theo mùa
                    </p>
                </motion.div>
            </section>

            {/* Dải số liệu */}
            <section className="mx-auto max-w-[1200px] px-5 py-2">
                <motion.div
                    {...reveal}
                    className="grid overflow-hidden rounded-[18px]"
                    style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: 1, background: '#E3E8D6', border: '1px solid #E3E8D6' }}
                >
                    {stats.map(([n, l], i) => (
                        <div key={i} className="bg-card px-[18px] py-[22px] text-center">
                            <div className="font-mono text-[26px] font-bold text-grass">{n}</div>
                            <div className="mt-[5px] text-[13px] text-moss">{l}</div>
                        </div>
                    ))}
                </motion.div>
            </section>

            {/* Thiết bị nổi bật */}
            <section className="mx-auto max-w-[1200px] px-5 pb-2.5 pt-12">
                <div className="mb-[22px] flex items-end justify-between gap-4">
                    <motion.div {...reveal}>
                        <div className="mb-2 font-mono text-[12px] tracking-[0.1em] text-campfire">ĐƯỢC THUÊ NHIỀU</div>
                        <h2 className="font-extrabold tracking-tight text-ink" style={{ fontSize: 'clamp(24px,3vw,32px)' }}>Thiết bị nổi bật</h2>
                    </motion.div>
                    <Link href="/thiet-bi" className="shrink-0 whitespace-nowrap font-bold text-grass hover:text-pine">Xem tất cả →</Link>
                </div>
                <motion.div {...reveal} className="grid gap-[18px]" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(248px, 1fr))' }}>
                    {featured.map((p) => <ProductCard key={p.id} p={p} />)}
                </motion.div>
            </section>

            {/* Thuê đồ 3 bước */}
            <section className="mx-auto max-w-[1200px] px-5 pb-5 pt-[54px]">
                <motion.h2 {...reveal} className="mb-2 text-center font-extrabold tracking-tight text-ink" style={{ fontSize: 'clamp(24px,3vw,32px)' }}>
                    Thuê đồ trong 3 bước
                </motion.h2>
                <motion.p {...reveal} className="mx-auto mb-[34px] max-w-[520px] text-center text-moss">
                    Không cần tài khoản rườm rà. Chỉ cần số điện thoại và tên là xong.
                </motion.p>
                <div className="grid gap-[18px]" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))' }}>
                    {steps.map((s, i) => (
                        <motion.div
                            key={s.n}
                            initial={{ opacity: 0, y: 18 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true, amount: 0.3 }}
                            transition={{ duration: 0.6, delay: i * 0.08, ease: EASE }}
                            className="rounded-card border border-cardBorder bg-card px-[22px] py-[26px]"
                        >
                            <span className="mb-4 grid h-[34px] w-[34px] place-items-center rounded-[10px] bg-grass font-mono text-[13px] font-bold text-white">{s.n}</span>
                            <h3 className="mb-[7px] text-[17px] font-bold text-ink">{s.t}</h3>
                            <p className="text-[14px] leading-[1.55] text-moss">{s.d}</p>
                        </motion.div>
                    ))}
                </div>
            </section>

            {/* CTA */}
            <section className="mx-auto my-[46px] max-w-[1200px] px-5">
                <motion.div
                    {...reveal}
                    className="relative overflow-hidden rounded-[24px] text-center"
                    style={{ background: 'linear-gradient(120deg,#2C3D22,#3f5a2a 60%,#557A2B)', padding: 'clamp(34px,5vw,56px)' }}
                >
                    <div className="absolute" style={{ top: -40, right: -30, width: 180, height: 180, borderRadius: '50%', background: 'rgba(201,123,54,.28)', filter: 'blur(8px)' }} />
                    <h2 className="relative mb-3 font-extrabold tracking-tight text-white" style={{ fontSize: 'clamp(26px,3.4vw,38px)' }}>
                        Sẵn sàng cho chuyến đi tiếp theo?
                    </h2>
                    <p className="relative mx-auto mb-[26px] max-w-[480px] text-[17px]" style={{ color: '#d6e4bd' }}>
                        Chọn đồ hôm nay, nhận đồ ngay cuối tuần. Giao nhận nội thành miễn phí cho đơn từ 300.000đ.
                    </p>
                    <Link
                        href="/thiet-bi"
                        className="relative inline-grid h-[54px] place-items-center rounded-[14px] bg-white px-8 text-[17px] font-bold text-pine transition hover:-translate-y-0.5"
                    >
                        Bắt đầu chọn đồ
                    </Link>
                </motion.div>
            </section>

            <Footer />
        </>
    );
}
