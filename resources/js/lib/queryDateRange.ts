// Đọc khoảng ngày thuê từ query string URL (?start=YYYY-MM-DD&end=YYYY-MM-DD) — dùng để
// prefill lịch ở trang chi tiết sản phẩm/combo khi khách đến từ trang chủ đã chọn ngày
// (bopcamping-llg6 / PRD FR-3), ƯU TIÊN CAO HƠN cartSuggestedRange().
//
// Sai format, end < start, ngày không có thật (vd 2026-02-30), hoặc start đã ở quá khứ
// → bỏ qua, trả null (KHÔNG throw) — trang không bao giờ vỡ vì URL bẩn (FR-4). Khách vẫn
// sửa lịch tự do sau khi prefill — hàm này chỉ tính giá trị khởi tạo, không lock gì cả.
import { fromISO, toISO, todayISO } from './format';

const ISO_DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

export function queryDateRange(): { start: string; end: string } | null {
    if (typeof window === 'undefined') return null;

    const params = new URLSearchParams(window.location.search);
    const start = params.get('start');
    const end = params.get('end');
    if (!start || !end) return null;
    if (!ISO_DATE_RE.test(start) || !ISO_DATE_RE.test(end)) return null;

    // Loại ngày không tồn tại thật (vd tháng/ngày lố, "2026-02-30" tự lăn qua tháng 3):
    // round-trip qua Date phải khớp lại đúng chuỗi gốc.
    if (toISO(fromISO(start)) !== start || toISO(fromISO(end)) !== end)
        return null;

    if (end < start) return null;
    if (start < todayISO()) return null;

    return { start, end };
}
