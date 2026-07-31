import RentalDatePicker, {
    type RentalLocationOption,
} from '@/Components/site/RentalDatePicker';
import { rangeText } from '@/lib/format';
import { router } from '@inertiajs/react';
import { useState } from 'react';

/**
 * bopcamping-3kn9 (T4) — thanh đổi ngày trên trang danh sách (/thiet-bi, /combos).
 *
 * Vì sao phải thu gọn: DateRangeCalendar luôn render đủ 2 tháng, để mở sẵn thì lưới sản phẩm
 * bị đẩy xuống rất sâu. Nên mặc định chỉ hiện một dòng tóm tắt, bấm mới mở lịch.
 *
 * Khoảng ngày sống trong URL (?start=&end=), không giữ state riêng — xem quyết định #3 trong
 * artifacts/prd_date_first_booking.md. Bỏ lọc = điều hướng lại với start/end rỗng.
 */

type Props = {
    /** '' khi khách chưa chọn ngày. */
    start: string;
    end: string;
    viTri: string;
    serviceLocations: RentalLocationOption[];
    targetPath: string;
    /** Các filter khác cần giữ khi đổi/bỏ ngày (cat, q, sort...). */
    preserveParams: Record<string, string>;
    /** Số món/combo hết hàng trong khoảng — chỉ để hiện nhãn, không lọc. */
    unavailableCount?: number | null;
    /** Nhãn danh từ: 'thiết bị' hoặc 'combo'. */
    noun?: string;
};

export default function RentalRangeBar({
    start,
    end,
    viTri,
    serviceLocations,
    targetPath,
    preserveParams,
    unavailableCount = null,
    noun = 'thiết bị',
}: Props) {
    const hasRange = !!start && !!end;
    // Chưa có ngày thì mở sẵn lịch cho khách chọn luôn; đã có rồi thì thu gọn.
    const [open, setOpen] = useState(false);

    /**
     * Bỏ lọc ngày = điều hướng lại KHÔNG có start/end nhưng GIỮ mọi filter khác.
     *
     * viTri được gộp ở đây chứ không bắt caller nhét vào preserveParams: component đã nhận
     * viTri làm prop riêng nên để caller tự nhớ thêm lần nữa là cái bẫy — đã sập một lần,
     * bỏ lọc ngày kéo mất luôn địa điểm đang chọn.
     */
    const clearRange = () => {
        const query: Record<string, string> = { ...preserveParams };
        if (viTri) query['vi-tri'] = viTri;

        router.get(targetPath, query, { preserveState: false });
    };

    return (
        <section
            aria-label="Khoảng ngày thuê"
            className="mb-[18px] rounded-card border border-cardBorder bg-card p-4"
        >
            <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                {hasRange ? (
                    <>
                        <span className="inline-flex items-center gap-2 rounded-pill bg-grass px-3.5 py-1.5 text-[13px] font-bold text-white">
                            <svg
                                width="13"
                                height="13"
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                            >
                                <rect
                                    x="3"
                                    y="5"
                                    width="18"
                                    height="16"
                                    rx="3"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                />
                                <path
                                    d="M3 10h18M8 3v4M16 3v4"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    strokeLinecap="round"
                                />
                            </svg>
                            {rangeText(start, end)}
                        </span>
                        {unavailableCount ? (
                            <span className="text-[13px] text-campfire">
                                {unavailableCount} {noun} hết hàng trong khoảng
                                này
                            </span>
                        ) : (
                            <span className="text-[13px] text-moss">
                                Đang xem đồ còn rảnh trong khoảng này
                            </span>
                        )}
                    </>
                ) : (
                    <span className="text-[13.5px] font-semibold text-moss">
                        Chọn ngày đi để biết {noun} nào còn rảnh
                    </span>
                )}

                {/* Mobile: nút xuống dòng và canh trái (ml-auto ở đây để lại khoảng trống lạ
                    bên trái). Từ sm trở lên mới đẩy sang phải cho cân với chip ngày. */}
                <div className="flex w-full items-center gap-2 sm:ml-auto sm:w-auto">
                    <button
                        type="button"
                        onClick={() => setOpen((v) => !v)}
                        aria-expanded={open}
                        className="h-9 rounded-control border border-cardBorder bg-white px-3.5 text-[13px] font-semibold text-pine transition hover:border-grass"
                    >
                        {open ? 'Thu gọn' : hasRange ? 'Đổi ngày' : 'Chọn ngày'}
                    </button>
                    {hasRange && (
                        <button
                            type="button"
                            onClick={clearRange}
                            className="h-9 rounded-control px-2.5 text-[13px] font-semibold text-moss transition hover:text-campfire"
                        >
                            Bỏ lọc ngày
                        </button>
                    )}
                </div>
            </div>

            {open && (
                <div className="mt-4">
                    <RentalDatePicker
                        variant="compact"
                        serviceLocations={serviceLocations}
                        initialStart={start || null}
                        initialEnd={end || null}
                        initialLocation={viTri || null}
                        targetPath={targetPath}
                        preserveParams={preserveParams}
                    />
                </div>
            )}
        </section>
    );
}
