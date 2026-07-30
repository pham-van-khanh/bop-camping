<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Đọc khoảng ngày thuê từ query (?start=&end=) — MỘT chỗ duy nhất cho mọi trang listing
 * (bopcamping-aqkr). Trước đây ComboController có bản riêng; trait này thay thế nó để
 * /thiet-bi và /combos không lệch luật.
 *
 * FR-4 (artifacts/prd_date_first_booking.md): ngày không hợp lệ thì BỎ QUA filter ngày,
 * render như khách chưa chọn — KHÔNG 422, KHÔNG exception. Trang không bao giờ vỡ vì URL bẩn
 * (khách share link cũ, bot cào, người ta sửa tay URL...).
 */
trait ParsesRentalRange
{
    /** Số ngày thuê tối đa cho một lần lọc (tính cả ngày đầu và ngày cuối). */
    private const MAX_RENTAL_DAYS = 30;

    /**
     * @return array{0: ?Carbon, 1: ?Carbon} [start, end] — [null, null] nếu không hợp lệ
     */
    protected function parseRange(Request $request): array
    {
        $start = $this->parseDate($request->query('start'));
        $end = $this->parseDate($request->query('end'));

        if (! $start || ! $end) {
            return [null, null];
        }

        // Đảo ngược hoặc đã qua → bỏ. So sánh theo NGÀY (startOfDay) để hôm nay vẫn thuê được.
        if ($end->lt($start) || $start->lt(Carbon::today())) {
            return [null, null];
        }

        // Quá dài → bỏ. +1 vì tính cả ngày đầu (10→12 là 3 ngày thuê).
        if ($start->diffInDays($end) + 1 > self::MAX_RENTAL_DAYS) {
            return [null, null];
        }

        return [$start, $end];
    }

    /**
     * 'Y-m-d' nghiêm ngặt. Chặn cả ngày không tồn tại: Carbon::parse('2026-02-30') tự tràn
     * thành 2026-03-02 nên phải so lại chuỗi sau khi format.
     */
    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value);

        return ($date && $date->format('Y-m-d') === $value) ? $date : null;
    }
}
