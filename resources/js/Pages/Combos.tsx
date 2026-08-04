import ComboCard, { type ComboCardData } from '@/Components/site/ComboCard';
import RentalRangeBar from '@/Components/site/RentalRangeBar';
import SiteLayout from '@/Layouts/SiteLayout';
import { Head } from '@inertiajs/react';
import { ReactNode } from 'react';

type ComboListItem = ComboCardData & {
    items: {
        product_id: number;
        name: string | null;
        quantity: number;
        price_per_day: number;
    }[];
    images: { url: string; type: 'image' | 'video' }[];
};

interface Props {
    combos: ComboListItem[];
    service_locations: { name: string; slug: string }[];
    filters: { start: string; end: string; vi_tri: string };
    range_summary: { days: number; unavailable_count: number } | null;
}

export default function Combos({
    combos,
    service_locations,
    filters,
    range_summary,
}: Props) {
    return (
        <>
            <Head title="Combo thuê trọn bộ" />
            <main className="mx-auto max-w-[1400px] px-5 pb-12 pt-[30px]">
                <div className="mb-2 font-mono text-[12px] tracking-[0.1em] text-campfire">
                    THUÊ TRỌN BỘ · RẺ HƠN THUÊ LẺ
                </div>
                <h1
                    className="mb-2 font-extrabold tracking-tight text-ink"
                    style={{ fontSize: 'clamp(24px,3vw,32px)' }}
                >
                    Combo tiết kiệm
                </h1>
                <p className="mb-5 max-w-[560px] text-moss">
                    Trọn bộ đồ camping gói sẵn theo nhu cầu — không phải nghĩ
                    mình thiếu gì, cọc thấp hơn thuê lẻ từng món.
                </p>

                {/* Thanh khoảng ngày dùng CHUNG với /thiet-bi — trạng thái còn/hết của mọi combo
                    tính theo khoảng này. Trước đây trang này có bản tự làm riêng (bopcamping-3kn9). */}
                <RentalRangeBar
                    start={filters.start ?? ''}
                    end={filters.end ?? ''}
                    viTri={filters.vi_tri ?? ''}
                    serviceLocations={service_locations}
                    targetPath="/combos"
                    preserveParams={{}}
                    unavailableCount={range_summary?.unavailable_count ?? null}
                    noun="combo"
                />

                {combos.length === 0 ? (
                    <div
                        className="rounded-[18px] border border-dashed px-6 py-14 text-center"
                        style={{
                            borderColor: '#cdd6b6',
                            background: '#FBFCF7',
                        }}
                    >
                        <div className="mb-3 text-[40px]">🎒</div>
                        <div className="mb-1.5 text-[19px] font-bold text-ink">
                            Chưa có combo nào
                        </div>
                        <div className="text-moss">
                            Tụi mình đang gói thêm combo mới — quay lại sau nhé.
                        </div>
                    </div>
                ) : (
                    <div
                        className="grid gap-[18px]"
                        style={{
                            gridTemplateColumns:
                                'repeat(auto-fill, minmax(258px, 1fr))',
                        }}
                    >
                        {combos.map((c, i) => (
                            <ComboCard
                                key={c.id}
                                c={{
                                    ...c,
                                    items_count: c.items.length,
                                    image: c.images[0]?.url ?? null,
                                }}
                                index={i}
                            />
                        ))}
                    </div>
                )}
            </main>
        </>
    );
}

Combos.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
