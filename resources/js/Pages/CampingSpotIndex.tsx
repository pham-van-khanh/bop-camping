import Breadcrumbs from '@/Components/site/Breadcrumbs';
import SpotCard, { type SpotCardData } from '@/Components/site/SpotCard';
import SiteLayout from '@/Layouts/SiteLayout';
import { Head, Link } from '@inertiajs/react';
import { ReactNode } from 'react';

interface Props {
    provinces: { province: string; spots: SpotCardData[] }[];
    locations: { name: string; slug: string }[];
}

/** Danh sách điểm cắm trại /diem-cam-trai, gom theo tỉnh/thành (bopcamping-y05c). */
export default function CampingSpotIndex({ provinces, locations }: Props) {
    return (
        <>
            <Head title="Điểm cắm trại đẹp" />
            <main className="mx-auto max-w-[1400px] px-5 pb-12 pt-6">
                <Breadcrumbs
                    items={[
                        { label: 'Trang chủ', href: '/' },
                        { label: 'Điểm cắm trại' },
                    ]}
                />
                <div className="mb-2 font-mono text-[12px] tracking-[0.1em] text-campfire">
                    CẨM NANG CẮM TRẠI
                </div>
                <h1
                    className="mb-2 font-extrabold tracking-tight text-ink"
                    style={{ fontSize: 'clamp(26px,3.4vw,38px)' }}
                >
                    Điểm cắm trại đẹp theo tỉnh thành
                </h1>
                <p className="mb-2 max-w-[640px] text-[15.5px] leading-[1.65] text-moss">
                    Địa hình, mùa đẹp nhất và đồ cần mang cho từng điểm. Chọn
                    được nơi rồi thì thuê đủ đồ theo ngày, trả tiền khi nhận.
                </p>
                {locations.length > 0 && (
                    <div className="mb-7 flex flex-wrap gap-x-5 gap-y-1.5">
                        {locations.map((l) => (
                            <Link
                                key={l.slug}
                                href={`/thue-do-cam-trai/${l.slug}`}
                                className="text-[14.5px] font-semibold text-grass hover:text-pine"
                            >
                                Thuê đồ cắm trại tại {l.name} →
                            </Link>
                        ))}
                    </div>
                )}

                {provinces.length === 0 ? (
                    <p className="py-12 text-center text-[15px] text-moss">
                        Chưa có điểm cắm trại nào.
                    </p>
                ) : (
                    provinces.map((g) => (
                        <section key={g.province} className="mb-9">
                            <div className="mb-3.5 flex items-center gap-2.5">
                                <h2 className="text-[20px] font-extrabold tracking-tight text-ink">
                                    {g.province}
                                </h2>
                                <span className="rounded-full bg-[#e7eed5] px-2.5 py-1 font-mono text-[11.5px] text-[#3a5a1f]">
                                    {g.spots.length} điểm
                                </span>
                            </div>
                            <div
                                className="grid gap-[18px]"
                                style={{
                                    gridTemplateColumns:
                                        'repeat(auto-fill, minmax(min(100%, 260px), 1fr))',
                                }}
                            >
                                {g.spots.map((s) => (
                                    <SpotCard key={s.id} s={s} />
                                ))}
                            </div>
                        </section>
                    ))
                )}
            </main>
        </>
    );
}

CampingSpotIndex.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
