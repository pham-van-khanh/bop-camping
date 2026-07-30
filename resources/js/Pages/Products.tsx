import { Head } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { ReactNode, useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import ProductCard from '@/Components/site/ProductCard';
import RentalRangeBar from '@/Components/site/RentalRangeBar';
import type { ProductResource } from '@/types/product';

type Sort = 'pop' | 'low' | 'high';

interface Props {
    products: ProductResource[];
    categories: { id: number; name: string; slug: string }[];
    service_locations: { name: string; slug: string }[];
    filters: { cat: string; q: string; sort: string; vi_tri: string; start: string; end: string };
    // null khi khách chưa chọn khoảng ngày (bopcamping-3kn9).
    range_summary: { days: number; unavailable_count: number } | null;
}

const segBtn = (active: boolean) =>
    `rounded-[9px] px-3 py-1.5 text-[13px] font-semibold transition ${active ? 'bg-grass text-white' : 'text-moss hover:text-pine'}`;

const chipBtn = (active: boolean) =>
    `rounded-pill border px-3.5 py-2 text-[13.5px] font-semibold transition ${
        active ? 'border-grass bg-grass text-white' : 'border-[#d6ddc4] bg-card text-pine hover:border-grass'
    }`;

export default function Products({ products, categories, service_locations, filters, range_summary }: Props) {
    const [search, setSearch] = useState(filters.q ?? '');

    const applyFilters = (patch: Partial<{ cat: string; q: string; sort: string; 'vi-tri': string }>) => {
        router.get(
            '/thiet-bi',
            {
                cat: filters.cat,
                q: filters.q,
                sort: filters.sort,
                'vi-tri': filters.vi_tri,
                // Giữ khoảng ngày khi đổi bộ lọc khác — đổi danh mục không được làm mất ngày đã chọn.
                start: filters.start,
                end: filters.end,
                ...patch,
            },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        applyFilters({ cat: '', q: '', sort: 'pop', 'vi-tri': '' });
    };

    const handleSearch = (value: string) => {
        setSearch(value);
        applyFilters({ q: value });
    };

    const isEmpty = products.length === 0;
    const sort = (filters.sort as Sort) || 'pop';
    const cat  = filters.cat ?? '';
    const viTri = filters.vi_tri ?? '';

    return (
        <>
            <Head title="Thuê đồ — Kho thiết bị" />
            <main className="mx-auto max-w-[1400px] px-5 pb-10 pt-[34px]">
                <div className="mb-6">
                    <div className="mb-[7px] font-mono text-[12px] tracking-[0.1em] text-campfire">KHO THIẾT BỊ</div>
                    <h1 className="font-extrabold tracking-tight text-ink" style={{ fontSize: 'clamp(26px,3.4vw,36px)' }}>Chọn đồ cho chuyến đi</h1>
                </div>

                {/* Thanh khoảng ngày — nguồn chân lý là URL (?start=&end=), không giữ state riêng. */}
                <RentalRangeBar
                    start={filters.start ?? ''}
                    end={filters.end ?? ''}
                    viTri={viTri}
                    serviceLocations={service_locations}
                    targetPath="/thiet-bi"
                    preserveParams={{ cat, q: filters.q ?? '', sort }}
                    unavailableCount={range_summary?.unavailable_count ?? null}
                />

                {/* location filter — chỉ hiện khi có >1 vị trí phục vụ */}
                {service_locations.length > 1 && (
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <span className="mr-0.5 text-[13px] font-semibold text-moss">Thuê tại:</span>
                        <button onClick={() => applyFilters({ 'vi-tri': '' })} className={chipBtn(viTri === '')}>Tất cả</button>
                        {service_locations.map((l) => (
                            <button
                                key={l.slug}
                                onClick={() => applyFilters({ 'vi-tri': l.slug })}
                                className={`inline-flex items-center gap-1 ${chipBtn(viTri === l.slug)}`}
                            >
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" className="flex-none">
                                    <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z" fill="currentColor" opacity=".85" />
                                    <circle cx="12" cy="10" r="2.3" fill={viTri === l.slug ? '#557A2B' : '#FBFCF7'} />
                                </svg>
                                {l.name}
                            </button>
                        ))}
                    </div>
                )}

                {/* toolbar */}
                <div className="mb-[18px] flex flex-wrap items-center gap-3">
                    <div className="flex min-w-[220px] flex-1 items-center gap-2.5 rounded-control border border-cardBorder bg-card px-3.5 py-[11px]">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="#8a967a" strokeWidth="1.8" /><path d="m20 20-3.2-3.2" stroke="#8a967a" strokeWidth="1.8" strokeLinecap="round" /></svg>
                        <input
                            value={search}
                            onChange={(e) => handleSearch(e.target.value)}
                            placeholder="Tìm lều, bếp, túi ngủ..."
                            aria-label="Tìm thiết bị"
                            className="flex-1 border-none bg-transparent text-[15px] text-ink outline-none"
                        />
                    </div>
                    <select
                        value={sort}
                        onChange={(e) => applyFilters({ sort: e.target.value })}
                        aria-label="Sắp xếp"
                        className="h-11 cursor-pointer rounded-control border border-cardBorder bg-card px-3.5 text-[14px] font-semibold text-pine"
                    >
                        <option value="pop">Phổ biến nhất</option>
                        <option value="low">Giá: thấp đến cao</option>
                        <option value="high">Giá: cao đến thấp</option>
                    </select>
                </div>

                {/* category chips */}
                <div className="mb-[22px] flex flex-wrap gap-2">
                    <button onClick={() => applyFilters({ cat: '' })} className={chipBtn(cat === '')}>Tất cả</button>
                    {categories.map((c) => (
                        <button key={c.id} onClick={() => applyFilters({ cat: c.slug })} className={chipBtn(cat === c.slug)}>{c.name}</button>
                    ))}
                </div>

                {/* results */}
                <div className="mb-3.5 flex items-center justify-between">
                    <span className="font-mono text-[13px] text-moss">
                        {products.length} thiết bị
                        {range_summary && (
                            <> · {products.length - range_summary.unavailable_count} còn rảnh</>
                        )}
                    </span>
                </div>
                {isEmpty ? (
                    <div className="rounded-[18px] border border-dashed px-6 py-[50px] text-center" style={{ borderColor: '#cdd6b6', background: '#FBFCF7' }}>
                        <div className="mb-2.5 text-[34px]">⛺</div>
                        <div className="mb-1.5 text-[18px] font-bold text-ink">Chưa tìm thấy thiết bị phù hợp</div>
                        <div className="mb-5 text-moss">Thử từ khoá khác hoặc bỏ bớt bộ lọc.</div>
                        <button onClick={clearFilters} className="h-[46px] rounded-control border border-[#cdd6b6] bg-white px-6 font-semibold text-pine">Xoá bộ lọc</button>
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-[18px] md:grid-cols-3 lg:grid-cols-4">
                        {products.map((p, i) => <ProductCard key={p.id} p={p} index={i} />)}
                    </div>
                )}
            </main>
        </>
    );
}

Products.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
