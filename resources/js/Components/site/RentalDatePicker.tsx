import DateRangeCalendar from '@/Components/site/DateRangeCalendar';
import { router } from '@inertiajs/react';
import { useState } from 'react';

/**
 * bopcamping-oub7 (T3) — Module "Bạn đi ngày nào?": bọc DateRangeCalendar + chọn
 * địa điểm (tuỳ chọn) + nút Xác nhận, điều hướng sang trang danh sách kèm
 * ?start=&end=[&vi-tri=]. Dùng ở trang chủ (variant="hero", PRD FR-1) và
 * (về sau) thanh đổi ngày compact trên /thiet-bi, /combos (variant="compact").
 *
 * `unavailable` luôn truyền `new Set()` ở đây — chưa có tập sản phẩm cụ thể để
 * tính hết hàng nên không tô ngày nào (PRD FR-1: chỉ chặn ngày quá khứ).
 */

export type RentalLocationOption = { name: string; slug?: string };

type Props = {
    variant: 'hero' | 'compact';
    serviceLocations: RentalLocationOption[];
    initialStart?: string | null;
    initialEnd?: string | null;
    initialLocation?: string | null;
    /** Trang đích khi bấm Xác nhận — mặc định /thiet-bi (combo dùng /combos). */
    targetPath?: string;
    /** Query khác cần giữ lại (vd cat/q/sort khi dùng ở thanh compact trên listing). */
    preserveParams?: Record<string, string>;
};

/** Bỏ key có giá trị rỗng/undefined trước khi đưa vào router.get. */
function compact(
    query: Record<string, string | undefined>,
): Record<string, string> {
    const out: Record<string, string> = {};
    for (const [key, value] of Object.entries(query)) {
        if (value) out[key] = value;
    }
    return out;
}

export default function RentalDatePicker({
    variant,
    serviceLocations,
    initialStart = null,
    initialEnd = null,
    initialLocation = null,
    targetPath = '/thiet-bi',
    preserveParams = {},
}: Props) {
    const [start, setStart] = useState<string | null>(initialStart);
    const [end, setEnd] = useState<string | null>(initialEnd);
    const [location, setLocation] = useState<string>(initialLocation ?? '');

    const canConfirm = !!start && !!end;

    const handleConfirm = () => {
        if (!canConfirm) return;
        router.get(
            targetPath,
            compact({
                ...preserveParams,
                start: start ?? undefined,
                end: end ?? undefined,
                'vi-tri': location || undefined,
            }),
            { preserveState: false },
        );
    };

    const isHero = variant === 'hero';

    return (
        <div className={isHero ? 'flex flex-col gap-4' : 'flex flex-col gap-3'}>
            <DateRangeCalendar
                start={start}
                end={end}
                unavailable={new Set()}
                onChange={(s, e) => {
                    setStart(s);
                    setEnd(e);
                }}
            />

            <div
                className={`flex flex-col gap-3 sm:flex-row sm:items-center ${isHero ? 'sm:justify-between' : 'sm:justify-end'}`}
            >
                <div className="flex min-w-[220px] flex-1 flex-col gap-1.5 sm:flex-1 sm:flex-none">
                    <label
                        htmlFor="rental-date-picker-location"
                        className="text-[12.5px] font-semibold text-moss"
                    >
                        Địa điểm nhận đồ
                    </label>
                    <select
                        id="rental-date-picker-location"
                        value={location}
                        onChange={(e) => setLocation(e.target.value)}
                        className="h-11 w-full cursor-pointer rounded-control border border-cardBorder bg-card px-3.5 text-[14px] font-semibold text-pine sm:w-auto"
                    >
                        <option value="">Tất cả địa điểm</option>
                        {serviceLocations.map((l) => (
                            <option
                                key={l.slug ?? l.name}
                                value={l.slug ?? l.name}
                            >
                                {l.name}
                            </option>
                        ))}
                    </select>
                </div>

                <button
                    type="button"
                    onClick={handleConfirm}
                    disabled={!canConfirm}
                    className="h-11 shrink-0 rounded-control bg-grass px-7 font-bold text-white transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:translate-y-0"
                >
                    Xác nhận
                </button>
            </div>
        </div>
    );
}
