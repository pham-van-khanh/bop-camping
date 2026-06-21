import { Head } from '@inertiajs/react';
import { ReactNode, useMemo, useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import ProductCard from '@/Components/site/ProductCard';
import { CATEGORIES, PRODUCTS, type CatKey } from '@/lib/catalog';

type DemoState = 'ok' | 'load' | 'err';
type Sort = 'pop' | 'low' | 'high';

const segBtn = (active: boolean) =>
    `rounded-[9px] px-3 py-1.5 text-[13px] font-semibold transition ${active ? 'bg-grass text-white' : 'text-moss hover:text-pine'}`;

const chipBtn = (active: boolean) =>
    `rounded-pill border px-3.5 py-2 text-[13.5px] font-semibold transition ${
        active ? 'border-grass bg-grass text-white' : 'border-[#d6ddc4] bg-card text-pine hover:border-grass'
    }`;

export default function Products() {
    const [search, setSearch] = useState('');
    const [sort, setSort] = useState<Sort>('pop');
    const [cat, setCat] = useState<CatKey | 'all'>('all');
    const [demo, setDemo] = useState<DemoState>('ok');

    const products = useMemo(() => {
        const q = search.trim().toLowerCase();
        let list = PRODUCTS.filter((p) => (cat === 'all' || p.cat === cat) && (!q || p.name.toLowerCase().includes(q) || p.blurb.toLowerCase().includes(q)));
        if (sort === 'low') list = [...list].sort((a, b) => a.price - b.price);
        else if (sort === 'high') list = [...list].sort((a, b) => b.price - a.price);
        return list;
    }, [search, sort, cat]);

    const clearFilters = () => {
        setSearch('');
        setCat('all');
        setSort('pop');
    };

    const isEmpty = products.length === 0;

    return (
        <>
            <Head title="Thuê đồ — Kho thiết bị" />
            <main className="mx-auto max-w-[1200px] px-5 pb-10 pt-[34px]">
                <div className="mb-6">
                    <div className="mb-[7px] font-mono text-[12px] tracking-[0.1em] text-campfire">KHO THIẾT BỊ</div>
                    <h1 className="font-extrabold tracking-tight text-ink" style={{ fontSize: 'clamp(26px,3.4vw,36px)' }}>Chọn đồ cho chuyến đi</h1>
                </div>

                {/* toolbar */}
                <div className="mb-[18px] flex flex-wrap items-center gap-3">
                    <div className="flex min-w-[220px] flex-1 items-center gap-2.5 rounded-control border border-cardBorder bg-card px-3.5 py-[11px]">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="#8a967a" strokeWidth="1.8" /><path d="m20 20-3.2-3.2" stroke="#8a967a" strokeWidth="1.8" strokeLinecap="round" /></svg>
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Tìm lều, bếp, túi ngủ..."
                            aria-label="Tìm thiết bị"
                            className="flex-1 border-none bg-transparent text-[15px] text-ink outline-none"
                        />
                    </div>
                    <select
                        value={sort}
                        onChange={(e) => setSort(e.target.value as Sort)}
                        aria-label="Sắp xếp"
                        className="h-11 cursor-pointer rounded-control border border-cardBorder bg-card px-3.5 text-[14px] font-semibold text-pine"
                    >
                        <option value="pop">Phổ biến nhất</option>
                        <option value="low">Giá: thấp đến cao</option>
                        <option value="high">Giá: cao đến thấp</option>
                    </select>
                    <div className="flex rounded-control border border-cardBorder p-[3px]" style={{ background: '#eef2e3' }}>
                        <button onClick={() => setDemo('ok')} className={segBtn(demo === 'ok')}>Bình thường</button>
                        <button onClick={() => setDemo('load')} className={segBtn(demo === 'load')}>Đang tải</button>
                        <button onClick={() => setDemo('err')} className={segBtn(demo === 'err')}>Lỗi</button>
                    </div>
                </div>

                {/* category chips */}
                <div className="mb-[22px] flex flex-wrap gap-2">
                    <button onClick={() => setCat('all')} className={chipBtn(cat === 'all')}>Tất cả</button>
                    {CATEGORIES.map((c) => (
                        <button key={c.key} onClick={() => setCat(c.key)} className={chipBtn(cat === c.key)}>{c.label}</button>
                    ))}
                </div>

                {/* loading */}
                {demo === 'load' && (
                    <div className="grid gap-[18px]" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(248px, 1fr))' }}>
                        {Array.from({ length: 6 }).map((_, i) => (
                            <div key={i} className="overflow-hidden rounded-card border border-cardBorder bg-card">
                                <div className="h-[170px]" style={{ background: '#e7ebdc', animation: 'skeleton 1.4s ease-in-out infinite' }} />
                                <div className="p-[15px]">
                                    <div className="h-3.5 w-4/5 rounded-md" style={{ background: '#e7ebdc', animation: 'skeleton 1.4s ease-in-out infinite' }} />
                                    <div className="mt-2.5 h-3.5 w-1/2 rounded-md" style={{ background: '#e7ebdc', animation: 'skeleton 1.4s ease-in-out infinite' }} />
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* error */}
                {demo === 'err' && (
                    <div className="rounded-[18px] border bg-card px-6 py-12 text-center" style={{ borderColor: '#ecd3c4' }}>
                        <div className="mx-auto mb-4 grid h-[58px] w-[58px] place-items-center rounded-full text-[28px]" style={{ background: '#f7e7da', color: '#C97B36' }}>!</div>
                        <div className="mb-1.5 text-[18px] font-bold text-ink">Không tải được danh sách</div>
                        <div className="mb-5 text-moss">Kết nối có vẻ trục trặc. Thử lại giúp tụi mình nhé.</div>
                        <button onClick={() => setDemo('ok')} className="h-[46px] rounded-control bg-grass px-6 font-bold text-white">Thử lại</button>
                    </div>
                )}

                {/* results */}
                {demo === 'ok' && (
                    <>
                        <div className="mb-3.5 flex items-center justify-between">
                            <span className="font-mono text-[13px] text-moss">{products.length} thiết bị</span>
                        </div>
                        {isEmpty ? (
                            <div className="rounded-[18px] border border-dashed px-6 py-[50px] text-center" style={{ borderColor: '#cdd6b6', background: '#FBFCF7' }}>
                                <div className="mb-2.5 text-[34px]">⛺</div>
                                <div className="mb-1.5 text-[18px] font-bold text-ink">Chưa tìm thấy thiết bị phù hợp</div>
                                <div className="mb-5 text-moss">Thử từ khoá khác hoặc bỏ bớt bộ lọc.</div>
                                <button onClick={clearFilters} className="h-[46px] rounded-control border border-[#cdd6b6] bg-white px-6 font-semibold text-pine">Xoá bộ lọc</button>
                            </div>
                        ) : (
                            <div className="grid gap-[18px]" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(248px, 1fr))' }}>
                                {products.map((p, i) => <ProductCard key={p.id} p={p} index={i} />)}
                            </div>
                        )}
                    </>
                )}
            </main>
        </>
    );
}

Products.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
