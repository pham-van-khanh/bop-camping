import { toISO, todayISO } from '@/lib/format';
import { useState, type CSSProperties } from 'react';

/** Lịch chọn khoảng ngày, 2 tháng, chọn bằng 2 chạm (RULES mục 7). */

const DOWS = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

function weeksOf(year: number, month: number) {
    const lead = (new Date(year, month, 1).getDay() + 6) % 7; // bắt đầu từ Thứ 2
    const days = new Date(year, month + 1, 0).getDate();
    const cells: (number | null)[] = Array(lead).fill(null);
    for (let d = 1; d <= days; d++) cells.push(d);
    while (cells.length % 7) cells.push(null);
    const weeks: (number | null)[][] = [];
    for (let i = 0; i < cells.length; i += 7) weeks.push(cells.slice(i, i + 7));
    return weeks;
}

/**
 * 'lg' = ô ngày to hơn cho popup đặt lịch trên PC (bopcamping-hero). Mặc định 'md' giữ
 * nguyên kích cỡ cũ để 5 chỗ đang dùng (ProductDetail, ComboDetail, Combos, Account,
 * Admin orderShared) không bị đổi layout.
 */
const SIZES = {
    md: {
        cell: 'h-[30px] text-[12.5px]',
        gap: 'gap-[3px]',
        col: 230,
        title: 'text-[15px]',
        month: 'text-[13px]',
        dow: 'text-[10px]',
    },
    lg: {
        cell: 'h-[40px] text-[14.5px]',
        gap: 'gap-[5px]',
        col: 300,
        title: 'text-[17px]',
        month: 'text-[14.5px]',
        dow: 'text-[11px]',
    },
} as const;

type Props = {
    start: string | null;
    end: string | null;
    unavailable: Set<string>;
    onChange: (start: string | null, end: string | null) => void;
    size?: keyof typeof SIZES;
    /**
     * false = ẩn chú giải "Hết hàng" (bopcamping-fpcb). Dùng ở nơi `unavailable` luôn rỗng
     * (chưa có sản phẩm cụ thể để tính) — hiện ô màu cho một trạng thái không bao giờ xuất
     * hiện làm khách tưởng có ngày hết hàng. Mặc định true để 5 chỗ đang dùng không đổi.
     */
    showUnavailableLegend?: boolean;
};

export default function DateRangeCalendar({
    start,
    end,
    unavailable,
    onChange,
    size = 'md',
    showUnavailableLegend = true,
}: Props) {
    const sz = SIZES[size];
    const now = new Date();
    const [view, setView] = useState({
        y: now.getFullYear(),
        m: now.getMonth(),
    });
    const today = todayISO();

    const atCurrent = view.y === now.getFullYear() && view.m === now.getMonth();
    const shift = (delta: number) => {
        const d = new Date(view.y, view.m + delta, 1);
        setView({ y: d.getFullYear(), m: d.getMonth() });
    };

    const click = (iso: string) => {
        if (!start || (start && end)) onChange(iso, null);
        else if (iso < start) onChange(iso, null);
        else onChange(start, iso);
    };

    const months = [
        { y: view.y, m: view.m },
        {
            y: new Date(view.y, view.m + 1, 1).getFullYear(),
            m: new Date(view.y, view.m + 1, 1).getMonth(),
        },
    ];

    return (
        <div className="rounded-card border border-cardBorder bg-card p-[18px]">
            <div className="mb-3.5 flex items-center justify-between">
                <div className={`${sz.title} font-bold text-ink`}>
                    Chọn ngày nhận và trả
                </div>
                <div className="flex gap-1.5">
                    <button
                        onClick={() => !atCurrent && shift(-1)}
                        disabled={atCurrent}
                        aria-label="Tháng trước"
                        className="h-[30px] w-[30px] rounded-[9px] border border-cardBorder bg-white text-moss disabled:opacity-40"
                    >
                        ‹
                    </button>
                    <button
                        onClick={() => shift(1)}
                        aria-label="Tháng sau"
                        className="h-[30px] w-[30px] rounded-[9px] border border-cardBorder bg-white text-moss"
                    >
                        ›
                    </button>
                </div>
            </div>

            <div
                className="grid gap-5"
                style={{
                    // min(col, 100%) — KHÔNG để bề rộng tối thiểu vượt quá container. Thiếu min()
                    // thì trên mobile grid rộng cứng theo col và bị overflow-hidden của popup cắt
                    // mất cột CN (bug phát hiện khi QA popup size 'lg': col 300px > ~255px khả dụng).
                    gridTemplateColumns: `repeat(auto-fit, minmax(min(${sz.col}px, 100%), 1fr))`,
                }}
            >
                {months.map(({ y, m }) => (
                    <div key={`${y}-${m}`}>
                        <div
                            className={`mb-2 text-center ${sz.month} font-bold text-pine`}
                        >
                            Tháng {m + 1}/{y}
                        </div>
                        <div className={`mb-1 grid grid-cols-7 ${sz.gap}`}>
                            {DOWS.map((dw) => (
                                <div
                                    key={dw}
                                    className={`py-[3px] text-center ${sz.dow} font-semibold text-[#a3ad92]`}
                                >
                                    {dw}
                                </div>
                            ))}
                        </div>
                        {weeksOf(y, m).map((wk, wi) => (
                            <div
                                key={wi}
                                className={`mb-[3px] grid grid-cols-7 ${sz.gap}`}
                            >
                                {wk.map((d, di) => {
                                    if (d === null) return <div key={di} />;
                                    const iso = toISO(new Date(y, m, d));
                                    const past = iso < today;
                                    const un = unavailable.has(iso);
                                    const isEdge = iso === start || iso === end;
                                    const inRange =
                                        !!start &&
                                        !!end &&
                                        iso > start &&
                                        iso < end;
                                    const disabled = past || un;
                                    let style: CSSProperties = {
                                        color: '#18230F',
                                    };
                                    if (past) style = { color: '#cfd6c0' };
                                    else if (un)
                                        style = {
                                            background: '#f0d9c4',
                                            color: '#b07a4a',
                                        };
                                    else if (isEdge)
                                        style = {
                                            background: '#557A2B',
                                            color: '#fff',
                                            fontWeight: 700,
                                        };
                                    else if (inRange)
                                        style = {
                                            background: '#dbe6c4',
                                            color: '#3a5a1f',
                                        };
                                    return (
                                        <button
                                            key={di}
                                            onClick={() =>
                                                !disabled && click(iso)
                                            }
                                            disabled={disabled}
                                            className={`${sz.cell} rounded-[7px] transition ${!disabled && !isEdge && !inRange ? 'hover:bg-[#eef2e3]' : ''} ${disabled ? 'cursor-not-allowed' : ''}`}
                                            style={style}
                                        >
                                            {d}
                                        </button>
                                    );
                                })}
                            </div>
                        ))}
                    </div>
                ))}
            </div>

            <div className="mt-3 flex flex-wrap items-center gap-2.5 text-[11px] text-[#8a967a]">
                <span className="inline-flex items-center gap-1.5">
                    <span
                        className="h-[11px] w-[11px] rounded-[4px]"
                        style={{ background: '#557A2B' }}
                    />
                    Đã chọn
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <span
                        className="h-[11px] w-[11px] rounded-[4px]"
                        style={{ background: '#dbe6c4' }}
                    />
                    Trong khoảng
                </span>
                {showUnavailableLegend && (
                    <span className="inline-flex items-center gap-1.5">
                        <span
                            className="h-[11px] w-[11px] rounded-[4px]"
                            style={{ background: '#f0d9c4' }}
                        />
                        Hết hàng
                    </span>
                )}
            </div>
        </div>
    );
}
