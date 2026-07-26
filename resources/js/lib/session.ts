// Buổi thuê khách chọn khi thuê ĐÚNG 1 NGÀY (spec 2026-07-26). Thuần hiển thị + giá —
// KHÔNG đụng tồn kho (mọi lượt vẫn khoá trọn ngày). Server là nguồn chân lý về giờ/giá;
// client chỉ gửi Session (enum) và mirror giá để hiển thị.
export type Session = 'morning' | 'afternoon' | 'full';

/** Buổi sáng/chiều = nửa ngày (áp ưu đãi trả sớm). Cả ngày = giá đầy đủ. */
export const isHalfDaySession = (s: Session | null | undefined): boolean =>
    s === 'morning' || s === 'afternoon';

/** Nhãn hiển thị kèm khung giờ theo setting shop (p=giờ giao, s=giờ chia buổi, r=giờ trả). */
export function sessionLabel(
    s: Session | null | undefined,
    p: number,
    split: number,
    r: number,
): string | null {
    if (s === 'morning') return `Buổi sáng · ${p}h–${split}h`;
    if (s === 'afternoon') return `Buổi chiều · ${split}h–${r}h`;
    if (s === 'full') return `Cả ngày · ${p}h–${r}h`;
    return null;
}
