import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { lazy, Suspense } from 'react';

// Tách Three.js thành chunk riêng, tải sau khi trang đã hiện (trang nhẹ, 3D stream vào sau)
const CampDiorama = lazy(() => import('@/Components/CampDiorama'));

// Ảnh dùng tạm từ picsum (ảnh thật, ngẫu nhiên) — thay bằng ảnh sản phẩm/cảnh thật của shop khi có.
const photo = (seed: string, w = 600, h = 420) =>
    `https://picsum.photos/seed/${seed}/${w}/${h}`;

const gear = [
    { name: 'Lều 2 người', price: '120.000đ', dep: 'cọc 300.000đ', avail: 'còn 3 bộ', low: true, seed: 'bopcamp-tent' },
    { name: 'Bếp gas mini + bình', price: '45.000đ', dep: 'cọc 150.000đ', avail: 'còn 6 bộ', low: false, seed: 'bopcamp-stove' },
    { name: 'Túi ngủ ấm -5°C', price: '60.000đ', dep: 'cọc 200.000đ', avail: 'còn 2 bộ', low: true, seed: 'bopcamp-bag' },
    { name: 'Bàn xếp + 2 ghế', price: '80.000đ', dep: 'cọc 200.000đ', avail: 'còn 5 bộ', low: false, seed: 'bopcamp-table' },
];

const steps = [
    { k: '01', t: 'Chọn ngày', d: 'Pick ngày nhận và ngày trả. Hệ thống báo món nào còn rảnh trong khoảng đó.' },
    { k: '02', t: 'Nhận đồ tận nơi', d: 'Shipper giao trong hai giờ nội thành. Đặt cọc và trả tiền thuê khi nhận.' },
    { k: '03', t: 'Trả khi về', d: 'Mang đồ về trả đúng hẹn, nhận lại đủ tiền cọc. Vậy là xong.' },
];

const EASE: [number, number, number, number] = [0.16, 1, 0.3, 1];

const reveal = {
    initial: { opacity: 0, y: 24 },
    whileInView: { opacity: 1, y: 0 },
    viewport: { once: true, amount: 0.2 },
    transition: { duration: 0.7, ease: EASE },
};

export default function Welcome({ auth, canLogin }: PageProps<{ canLogin?: boolean }>) {
    return (
        <>
            <Head title="Cho thuê đồ cắm trại" />
            <div className="min-h-screen bg-ground font-sans text-ink">
                {/* Nav */}
                <header className="absolute inset-x-0 top-0 z-20">
                    <nav className="mx-auto flex h-[68px] max-w-7xl items-center justify-between px-6">
                        <span className="text-lg font-extrabold tracking-[0.14em] text-forest">
                            BỐP<span className="text-grass">·</span>CAMPING
                        </span>
                        <div className="hidden gap-6 font-mono text-[0.72rem] uppercase tracking-wider text-moss md:flex">
                            <span>Lều</span><span>Bếp</span><span>Túi ngủ</span><span>Bàn ghế</span><span>Tra cứu đơn</span>
                        </div>
                        <Link
                            href={auth?.user ? route('dashboard') : (canLogin ? route('login') : '#')}
                            className="rounded-full bg-forest px-4 py-2 text-sm font-bold text-ground transition hover:bg-grass"
                        >
                            {auth?.user ? 'Trang quản lý' : 'Đăng nhập'}
                        </Link>
                    </nav>
                </header>

                {/* Hero */}
                <section className="relative overflow-hidden">
                    <div className="absolute inset-0 -z-10 bg-[radial-gradient(125%_90%_at_82%_16%,#FCFBF4_0%,#EFF1E5_48%,#E5EAD8_100%)]" />
                    <div className="mx-auto grid min-h-[100dvh] max-w-7xl items-center gap-10 px-6 pt-24 md:grid-cols-2 md:pt-16">
                        <motion.div
                            initial={{ opacity: 0, y: 26 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.8, ease: EASE }}
                        >
                            <p className="mb-5 font-mono text-[0.74rem] uppercase tracking-[0.2em] text-grass">
                                Thuê theo ngày, có cọc, trả khi về
                            </p>
                            <h1 className="max-w-[15ch] text-4xl font-extrabold leading-[1.03] tracking-tight text-forest sm:text-5xl lg:text-6xl">
                                Mang cả <span className="text-grass">thiên nhiên</span> theo bạn cuối tuần.
                            </h1>
                            <p className="mt-6 max-w-[42ch] text-lg text-moss">
                                Biển, núi, rừng hay đồng cỏ. Lều, bếp, túi ngủ soạn sẵn theo đúng số ngày bạn đi.
                            </p>
                            <div className="mt-8 flex flex-wrap gap-3.5">
                                <a href="#gear" className="rounded-full bg-grass px-6 py-3.5 font-bold text-ground shadow-lg shadow-grass/30 transition hover:-translate-y-0.5 hover:bg-forest">
                                    Chọn ngày đi
                                </a>
                                <a href="#gear" className="rounded-full border border-forest/15 bg-white/60 px-6 py-3.5 font-bold text-forest transition hover:border-grass hover:text-grass">
                                    Xem thiết bị
                                </a>
                            </div>
                        </motion.div>

                        {/* 3D camping diorama (auto-rotate) */}
                        <motion.div
                            className="h-[400px] w-full md:h-[560px]"
                            initial={{ opacity: 0, scale: 0.94 }}
                            animate={{ opacity: 1, scale: 1 }}
                            transition={{ duration: 1, delay: 0.15, ease: EASE }}
                        >
                            <Suspense fallback={<div className="h-full w-full rounded-full bg-grass/5" />}>
                                <CampDiorama />
                            </Suspense>
                        </motion.div>
                    </div>
                </section>

                {/* Stats */}
                <section className="mx-auto max-w-7xl px-6">
                    <motion.div {...reveal} className="grid grid-cols-1 border-y border-forest/10 sm:grid-cols-3">
                        {[
                            ['48 bộ', 'sẵn sàng cho mượn'],
                            ['2 giờ', 'giao tận nơi nội thành'],
                            ['100%', 'hoàn cọc khi trả đủ'],
                        ].map(([n, l], i) => (
                            <div key={i} className={`px-4 py-8 text-center ${i ? 'border-t border-forest/10 sm:border-l sm:border-t-0' : ''}`}>
                                <div className="font-mono text-2xl font-bold text-forest sm:text-3xl">{n}</div>
                                <div className="mt-1 text-sm text-moss">{l}</div>
                            </div>
                        ))}
                    </motion.div>
                </section>

                {/* Gear */}
                <section id="gear" className="mx-auto max-w-7xl px-6 py-20">
                    <motion.div {...reveal}>
                        <p className="mb-2 font-mono text-[0.74rem] uppercase tracking-[0.18em] text-grass">Thiết bị</p>
                        <h2 className="mb-8 text-2xl font-extrabold tracking-tight sm:text-3xl">Mượn gì cho chuyến này?</h2>
                    </motion.div>
                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {gear.map((g, i) => (
                            <motion.article
                                key={g.name}
                                initial={{ opacity: 0, y: 24 }}
                                whileInView={{ opacity: 1, y: 0 }}
                                viewport={{ once: true, amount: 0.2 }}
                                transition={{ duration: 0.6, delay: i * 0.06, ease: EASE }}
                                className="group flex flex-col overflow-hidden rounded-2xl border border-forest/10 bg-surface transition hover:-translate-y-1.5 hover:shadow-xl hover:shadow-forest/10"
                            >
                                <div className="h-40 overflow-hidden">
                                    <img
                                        src={photo(g.seed)}
                                        alt={g.name}
                                        loading="lazy"
                                        className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                    />
                                </div>
                                <div className="flex flex-1 flex-col p-5">
                                    <div className="font-bold">{g.name}</div>
                                    <div className="mt-1.5 font-mono font-bold text-grass">
                                        {g.price} <span className="font-normal text-moss">/ ngày</span>
                                    </div>
                                    <div className="mt-1.5 font-mono text-[0.7rem] text-moss">{g.dep}, hoàn khi trả</div>
                                    <div className="mt-auto flex items-center justify-between gap-2 pt-4">
                                        <span className={`rounded-lg px-2.5 py-1 font-mono text-[0.72rem] ${g.low ? 'bg-ember/15 text-ember' : 'bg-grass/10 text-grass'}`}>
                                            {g.avail}
                                        </span>
                                        <button className="rounded-full bg-forest px-4 py-2 text-sm font-bold text-ground transition hover:bg-grass">
                                            Thuê ngay
                                        </button>
                                    </div>
                                </div>
                            </motion.article>
                        ))}
                    </div>
                </section>

                {/* Steps */}
                <section className="mx-auto max-w-7xl px-6 py-12">
                    <motion.h2 {...reveal} className="mb-8 text-2xl font-extrabold tracking-tight sm:text-3xl">
                        Thuê đồ trong ba bước
                    </motion.h2>
                    <div className="grid grid-cols-1 gap-7 md:grid-cols-3">
                        {steps.map((s, i) => (
                            <motion.div
                                key={s.k}
                                initial={{ opacity: 0, y: 24 }}
                                whileInView={{ opacity: 1, y: 0 }}
                                viewport={{ once: true, amount: 0.3 }}
                                transition={{ duration: 0.6, delay: i * 0.08, ease: EASE }}
                            >
                                <div className="font-mono font-bold text-grass-soft">{s.k}</div>
                                <h3 className="mb-1.5 mt-2 text-xl font-bold tracking-tight">{s.t}</h3>
                                <p className="max-w-[34ch] text-moss">{s.d}</p>
                            </motion.div>
                        ))}
                    </div>
                </section>

                {/* CTA band with nature photo */}
                <section className="mx-auto max-w-7xl px-6 py-12">
                    <motion.div
                        {...reveal}
                        className="relative overflow-hidden rounded-3xl px-8 py-16 text-center"
                    >
                        <img src={photo('bopcamp-landscape', 1400, 600)} alt="" className="absolute inset-0 h-full w-full object-cover" />
                        <div className="absolute inset-0 bg-forest-deep/70" />
                        <div className="relative">
                            <h2 className="mx-auto mb-3 max-w-[20ch] text-2xl font-extrabold tracking-tight text-ground sm:text-4xl">
                                Đi đâu cũng có chỗ ngả lưng.
                            </h2>
                            <p className="mx-auto mb-7 max-w-[44ch] text-ground/80">
                                Chốt ngày, chọn đồ, để phần nặng nhọc cho tụi mình lo.
                            </p>
                            <a href="#gear" className="inline-block rounded-full bg-grass px-7 py-3.5 font-bold text-ground transition hover:-translate-y-0.5 hover:bg-grass-soft">
                                Chọn ngày đi
                            </a>
                        </div>
                    </motion.div>
                </section>

                <footer className="mx-auto max-w-7xl px-6 py-12 text-sm text-moss">
                    BỐP CAMPING — cho thuê thiết bị cắm trại theo ngày.
                </footer>
            </div>
        </>
    );
}
