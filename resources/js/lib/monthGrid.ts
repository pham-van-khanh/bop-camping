// Lưới lịch tháng cho trang Lịch giao (feedback 2026-07-28). Tách khỏi component để
// test được thuần logic — jsdom không kiểm được layout, nhưng lưới ngày thì kiểm được.

/** Nhãn thứ theo thói quen VN: tuần bắt đầu Thứ 2. */
export const WEEKDAY_LABELS = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

/**
 * Lưới tháng theo tuần T2→CN. Mỗi hàng đúng 7 ô; ô ngoài tháng = null.
 * `month` dạng 'YYYY-MM'; ô trong tháng là chuỗi 'YYYY-MM-DD'.
 * Dùng UTC để không lệch ngày theo múi giờ máy.
 */
export function buildMonthGrid(month: string): (string | null)[][] {
    const [year, m] = month.split('-').map(Number);
    if (!year || !m) return [];

    const firstWeekday = new Date(Date.UTC(year, m - 1, 1)).getUTCDay(); // 0 = CN
    const lead = (firstWeekday + 6) % 7; // số ô trống trước ngày 1 (T2 = 0)
    const daysInMonth = new Date(Date.UTC(year, m, 0)).getUTCDate();

    const cells: (string | null)[] = Array(lead).fill(null);
    for (let d = 1; d <= daysInMonth; d++) {
        cells.push(`${month}-${String(d).padStart(2, '0')}`);
    }
    while (cells.length % 7 !== 0) cells.push(null);

    const weeks: (string | null)[][] = [];
    for (let i = 0; i < cells.length; i += 7) weeks.push(cells.slice(i, i + 7));

    return weeks;
}
