import { Head, router } from '@inertiajs/react';
import { ReactNode, useMemo, useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import DateRangeCalendar from '@/Components/site/DateRangeCalendar';
import ComboCard, { type ComboCardData } from '@/Components/site/ComboCard';
import { rangeText } from '@/lib/format';

type ComboListItem = ComboCardData & {
    items: { product_id: number; name: string | null; quantity: number; price_per_day: number }[];
    images: { url: string; type: 'image' | 'video' }[];
};

interface Props {
    combos: ComboListItem[];
    filters: { start: string; end: string };
}

export default function Combos({ combos, filters }: Props) {
    // Date-picker chung đầu trang (PRD mục 6): chọn xong cả 2 đầu → reload availability
    const [start, setStart] = useState<string | null>(filters.start || null);
    const [end, setEnd] = useState<string | null>(filters.end || null);
    const [pickerOpen, setPickerOpen] = useState(false);

    const unavailable = useMemo(() => new Set<string>(), []);
    const hasRange = !!(filters.start && filters.end);

    const applyRange = (s: string | null, e: string | null) => {
        setStart(s);
        setEnd(e);
        if (s && e) {
            setPickerOpen(false);
            router.get('/combos', { start: s, end: e }, { preserveState: true, preserveScroll: true });
        }
    };

    const clearRange = () => {
        setStart(null);
        setEnd(null);
        setPickerOpen(false);
        router.get('/combos', {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Combo thuê trọn bộ" />
            <main className="mx-auto max-w-[1400px] px-5 pb-12 pt-[30px]">
                <div className="mb-2 font-mono text-[12px] tracking-[0.1em] text-campfire">THUÊ TRỌN BỘ · RẺ HƠN THUÊ LẺ</div>
                <h1 className="mb-2 font-extrabold tracking-tight text-ink" style={{ fontSize: 'clamp(24px,3vw,32px)' }}>
                    Combo tiết kiệm
                </h1>
                <p className="mb-5 max-w-[560px] text-moss">
                    Trọn bộ đồ camping gói sẵn theo nhu cầu — không phải nghĩ mình thiếu gì, cọc thấp hơn thuê lẻ từng món.
                </p>

                {/* Date-picker chung: trạng thái còn/hết của mọi combo tính theo khoảng này */}
                <div className="mb-7 rounded-[16px] border border-cardBorder bg-card p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div className="text-[12px] text-[#8a967a]">Xem tình trạng còn đồ theo ngày</div>
                            <div className="font-mono text-[15px] font-bold text-ink">{rangeText(start, end)}</div>
                        </div>
                        <div className="flex gap-2">
                            {hasRange && (
                                <button
                                    onClick={clearRange}
                                    className="h-10 rounded-control border border-cardBorder px-4 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]"
                                >
                                    Bỏ chọn ngày
                                </button>
                            )}
                            <button
                                onClick={() => setPickerOpen((o) => !o)}
                                className="h-10 rounded-control bg-grass px-4 text-[13px] font-bold text-white transition hover:bg-pine"
                            >
                                {pickerOpen ? 'Đóng lịch' : start && end ? 'Đổi ngày' : 'Chọn ngày thuê'}
                            </button>
                        </div>
                    </div>
                    {pickerOpen && (
                        <div className="mt-4 max-w-[560px]">
                            <DateRangeCalendar start={start} end={end} unavailable={unavailable} onChange={applyRange} />
                        </div>
                    )}
                </div>

                {combos.length === 0 ? (
                    <div className="rounded-[18px] border border-dashed px-6 py-14 text-center" style={{ borderColor: '#cdd6b6', background: '#FBFCF7' }}>
                        <div className="mb-3 text-[40px]">🎒</div>
                        <div className="mb-1.5 text-[19px] font-bold text-ink">Chưa có combo nào</div>
                        <div className="text-moss">Tụi mình đang gói thêm combo mới — quay lại sau nhé.</div>
                    </div>
                ) : (
                    <div className="grid gap-[18px]" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(258px, 1fr))' }}>
                        {combos.map((c, i) => (
                            <ComboCard key={c.id} c={{ ...c, items_count: c.items.length, image: c.images[0]?.url ?? null }} index={i} />
                        ))}
                    </div>
                )}
            </main>
        </>
    );
}

Combos.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
