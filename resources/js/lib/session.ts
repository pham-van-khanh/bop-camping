// Buổi thuê khách chọn khi thuê ĐÚNG 1 NGÀY (spec 2026-07-26). Thuần hiển thị + giá —
// KHÔNG đụng tồn kho (mọi lượt vẫn khoá trọn ngày). Server là nguồn chân lý về giờ/giá;
// client chỉ gửi Session (enum) và mirror giá để hiển thị.
export type Session = 'morning' | 'afternoon' | 'full';

/** Buổi sáng/chiều = nửa ngày (áp ưu đãi trả sớm). Cả ngày = giá đầy đủ. */
export const isHalfDaySession = (s: Session | null | undefined): boolean =>
    s === 'morning' || s === 'afternoon';

// Khung giờ shop (feedback 2026-07-27): sáng = pickup→morningEnd, chiều = afternoonStart→close,
// có khoảng nghỉ giữa morningEnd và afternoonStart để chuẩn bị/ship.
export type ShopHours = { pickup: number; morningEnd: number; afternoonStart: number; close: number };

/** Nhãn hiển thị kèm khung giờ theo setting shop. */
export function sessionLabel(s: Session | null | undefined, h: ShopHours): string | null {
    if (s === 'morning') return `Buổi sáng · ${h.pickup}h–${h.morningEnd}h`;
    if (s === 'afternoon') return `Buổi chiều · ${h.afternoonStart}h–${h.close}h`;
    if (s === 'full') return `Cả ngày · ${h.pickup}h–${h.close}h`;
    return null;
}

/** Đọc khung giờ shop từ shared prop `site` (fallback mặc định 8/12/13/20). */
export function shopHours(site?: { pickup_hour?: number; morning_end_hour?: number; afternoon_start_hour?: number; return_hour?: number } | null): ShopHours {
    return {
        pickup: site?.pickup_hour ?? 8,
        morningEnd: site?.morning_end_hour ?? 12,
        afternoonStart: site?.afternoon_start_hour ?? 13,
        close: site?.return_hour ?? 20,
    };
}
