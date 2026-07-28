import { describe, expect, it } from 'vitest';
import { buildMonthGrid, WEEKDAY_LABELS } from '@/lib/monthGrid';

/**
 * Lưới lịch tháng của trang Lịch giao (feedback 2026-07-28). Kiểm thuần logic ngày —
 * layout thật (ô đỏ, ô khoá) vẫn phải xem trên trình duyệt (adr_frontend_component_testing).
 */
describe('buildMonthGrid', () => {
    it('trả các hàng đúng 7 ô, tuần bắt đầu Thứ 2', () => {
        const weeks = buildMonthGrid('2026-07');

        expect(WEEKDAY_LABELS[0]).toBe('T2');
        expect(weeks.length).toBeGreaterThanOrEqual(5);
        weeks.forEach((w) => expect(w).toHaveLength(7));
    });

    it('phủ đủ số ngày của tháng, không lặp không thiếu', () => {
        const days = buildMonthGrid('2026-07').flat().filter(Boolean);

        expect(days).toHaveLength(31);
        expect(days[0]).toBe('2026-07-01');
        expect(days[30]).toBe('2026-07-31');
        expect(new Set(days).size).toBe(31);
    });

    it('đặt ngày 1 đúng cột theo thứ trong tuần', () => {
        // 01/07/2026 là Thứ 4 → cột thứ 3 (T2=0, T3=1, T4=2)
        const firstWeek = buildMonthGrid('2026-07')[0];

        expect(firstWeek.slice(0, 2)).toEqual([null, null]);
        expect(firstWeek[2]).toBe('2026-07-01');
    });

    it('xử lý tháng 2 năm nhuận và tháng bắt đầu đúng Thứ 2', () => {
        expect(buildMonthGrid('2028-02').flat().filter(Boolean)).toHaveLength(29);
        expect(buildMonthGrid('2027-02').flat().filter(Boolean)).toHaveLength(28);

        // 01/06/2026 là Thứ 2 → không có ô trống đầu tháng
        expect(buildMonthGrid('2026-06')[0][0]).toBe('2026-06-01');
    });

    it('trả rỗng khi tham số tháng không hợp lệ', () => {
        expect(buildMonthGrid('abc')).toEqual([]);
    });
});
