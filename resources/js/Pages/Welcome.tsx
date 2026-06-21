import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { lazy, Suspense } from 'react';
import Header from '@/Components/site/Header';
import Footer from '@/Components/site/Footer';

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
    { id: 1, name: 'Lều 2 lớp 4 người', desc: 'Chống mưa gió, dựng nhanh 5 phút', price: 180000, deposit: 500000, stock: 3, cat: 'Lều trại', grad: 'linear-gradient(150deg,#3f6322,#6e9440)' },
    { id: 2, name: 'Túi ngủ lông vũ -10°C', desc: 'Ấm cho đêm núi cao, nhẹ 900g', price: 70000, deposit: 250000, stock: 2, cat: 'Túi ngủ', grad: 'linear-gradient(150deg,#2c5a6e,#6ea7bd)' },
    { id: 3, name: 'Bếp gas mini + bình', desc: 'Gọn trong lòng bàn tay, lửa khỏe', price: 45000, deposit: 150000, stock: 6, cat: 'Bếp & nấu', grad: 'linear-gradient(150deg,#7a5a2a,#c79a52)' },
    { id: 4, name: 'Đèn lều tích điện', desc: 'Sáng 12 giờ, 3 mức, treo được', price: 30000, deposit: 100000, stock: 8, cat: 'Đèn & sáng', grad: 'linear-gradient(150deg,#4a4f2a,#9aa05a)' },
];

const stats = [
    ['120+', 'Bộ thiết bị'],
    ['4.9★', 'Đánh giá khách'],
    ['2.000+', 'Chuyến đi'],
    ['Nội thành', 'Giao nhận tận nơi'],
];

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
                <div className="absolute inset-x-0 bottom-0 h-2/3" style={{ background: 'linear-gradient(180deg, transparent, rgba(20,30,14,.55))' }} />
                <span
                    className={`absolute left-3 top-3 rounded-pill px-2.5 py-1 font-mono text-[11px] font-bold text-white ${low ? 'bg-campfire' : ''}`}
                    style={low ? undefined : { background: 'rgba(44,61,34,.72)' }}
                >
                    {low ? `sắp hết · ${p.stock} bộ` : `còn ${p.stock} bộ`}
                </span>
                <span className="absolute bottom-3 left-3 font-mono text-[11px] uppercase tracking-[0.1em] text-white/90">{p.cat}</span>
            </div>
            <div className="flex flex-1 flex-col p-4">
                <div className="min-h-[39px] text-[15.5px] font-bold leading-tight text-ink">{p.name}</div>
                <p className="mt-1 text-[12.5px] text-[#8a967a]">{p.desc}</p>
                <div className="mt-3 flex items-end justify-between">
                    <div className="font-mono text-[17px] font-bold text-grass">
                        {money(p.price)}<span className="text-[12px] font-normal text-moss"> /ngày</span>
                    </div>
                    <div className="font-mono text-[12px] text-campfire">cọc {money(p.deposit)}</div>
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

            <main className="mx-auto max-w-[1200px] px-5">
                {/* Hero */}
                <section className="grid items-center gap-10 py-12 md:grid-cols-2 md:py-16">
                    <motion.div initial={{ opacity: 0, y: 22 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.7, ease: EASE }}>
                        <span className="font-mono text-[11px] uppercase tracking-[0.1em] text-campfire">
                            Cho thuê theo ngày · Cọc linh hoạt
                        </span>
                        <h1 className="mt-4 text-[clamp(34px,5vw,56px)] font-extrabold leading-[1.04] tracking-tight text-ink">
                            Mang cả khu trại<br />
                            <span className="text-grass">đi bất cứ đâu.</span>
                        </h1>
                        <p className="mt-5 max-w-[46ch] text-[17px] leading-relaxed text-moss">
                            Lều, bếp, túi ngủ, đèn... soạn sẵn theo đúng số ngày bạn đi. Đặt giữ chỗ online, nhận đồ tận nơi, trả khi về.
                        </p>
                        <div className="mt-7 flex flex-wrap gap-3">
                            <Link href="/thiet-bi" className="rounded-control bg-grass px-6 py-3.5 font-bold text-white shadow-btn transition hover:-translate-y-0.5 hover:bg-pine">
                                Xem thiết bị
                            </Link>
                            <Link href="/tra-cuu" className="rounded-control border border-cardBorder bg-card px-6 py-3.5 font-bold text-pine transition hover:-translate-y-0.5 hover:border-grass">
                                Tra cứu đơn của tôi
                            </Link>
                        </div>
                    </motion.div>

                    <motion.div initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} transition={{ duration: 0.9, delay: 0.1, ease: EASE }}>
                        <div className="overflow-hidden rounded-[20px] shadow-hero" style={{ background: 'linear-gradient(180deg,#bfe1f2,#e7f1ec)' }}>
                            <div className="h-[360px] md:h-[460px]">
                                <Suspense fallback={<div className="h-full w-full" />}>
                                    <CampDiorama />
                                </Suspense>
                            </div>
                        </div>
                        <p className="mt-3 text-center font-mono text-[11px] tracking-[0.08em] text-moss">
                            Cùng một khu trại · đổi địa điểm theo mùa
                        </p>
                    </motion.div>
                </section>

                {/* Dải số liệu */}
                <motion.section {...reveal} className="grid grid-cols-2 overflow-hidden rounded-card border border-cardBorder bg-card lg:grid-cols-4">
                    {stats.map(([n, l], i) => (
                        <div key={i} className={`px-5 py-7 text-center ${i % 2 ? 'border-l border-cardBorder' : ''} ${i >= 2 ? 'border-t lg:border-t-0' : ''} ${i === 2 ? 'lg:border-l' : ''} border-cardBorder`}>
                            <div className="font-mono text-[26px] font-bold text-grass">{n}</div>
                            <div className="mt-1 text-sm text-moss">{l}</div>
                        </div>
                    ))}
                </motion.section>

                {/* Thiết bị nổi bật */}
                <section className="py-14">
                    <motion.div {...reveal} className="mb-7 flex items-end justify-between gap-4">
                        <div>
                            <span className="font-mono text-[11px] uppercase tracking-[0.1em] text-campfire">Được thuê nhiều</span>
                            <h2 className="mt-1 text-[clamp(24px,3vw,32px)] font-extrabold tracking-tight text-ink">Thiết bị nổi bật</h2>
                        </div>
                        <Link href="/thiet-bi" className="shrink-0 font-semibold text-grass hover:text-pine">Xem tất cả →</Link>
                    </motion.div>
                    <motion.div {...reveal} className="grid gap-[18px]" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(248px, 1fr))' }}>
                        {featured.map((p) => <ProductCard key={p.id} p={p} />)}
                    </motion.div>
                </section>

                {/* Thuê đồ 3 bước */}
                <section className="pb-14">
                    <motion.h2 {...reveal} className="mb-7 text-[clamp(24px,3vw,32px)] font-extrabold tracking-tight text-ink">Thuê đồ trong 3 bước</motion.h2>
                    <div className="grid gap-[18px] md:grid-cols-3">
                        {steps.map((s, i) => (
                            <motion.div
                                key={s.n}
                                initial={{ opacity: 0, y: 18 }}
                                whileInView={{ opacity: 1, y: 0 }}
                                viewport={{ once: true, amount: 0.3 }}
                                transition={{ duration: 0.6, delay: i * 0.08, ease: EASE }}
                                className="rounded-card border border-cardBorder bg-card p-6"
                            >
                                <span className="grid h-9 w-9 place-items-center rounded-[10px] bg-grass font-mono font-bold text-white">{s.n}</span>
                                <h3 className="mb-1.5 mt-3 text-lg font-bold text-ink">{s.t}</h3>
                                <p className="text-[14.5px] leading-relaxed text-moss">{s.d}</p>
                            </motion.div>
                        ))}
                    </div>
                </section>

                {/* CTA */}
                <motion.section {...reveal} className="mb-16 overflow-hidden rounded-[24px] p-10 text-center md:p-14" style={{ background: 'linear-gradient(120deg,#2C3D22,#3f5a2a 60%,#557A2B)' }}>
                    <h2 className="mx-auto max-w-[22ch] text-[clamp(24px,3.4vw,38px)] font-extrabold tracking-tight text-white">
                        Sẵn sàng cho chuyến đi tiếp theo?
                    </h2>
                    <p className="mx-auto mt-3 max-w-[48ch] text-white/75">
                        Chọn đồ, chốt ngày, để phần soạn sửa nặng nhọc cho tụi mình lo.
                    </p>
                    <Link href="/thiet-bi" className="mt-7 inline-block rounded-control bg-white px-7 py-3.5 font-bold text-pine transition hover:-translate-y-0.5">
                        Bắt đầu chọn đồ
                    </Link>
                </motion.section>
            </main>

            <Footer />
        </>
    );
}
