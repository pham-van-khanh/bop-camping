<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Nguồn chân lý cho lịch giao/thu (bopcamping-4gy0). Rút ra khỏi Admin\DeliveryScheduleController
 * để trang admin, trang shipper, bản in, PDF, CSV và email lịch đều đọc CÙNG một dữ liệu —
 * không lặp query, không lệch số liệu giữa các nơi.
 *
 * Khái niệm "lượt" (leg): 'pickup' = đi GIAO đồ (mở khoảng thuê) · 'return' = đi THU đồ
 * (kết thúc khoảng thuê). Một đơn có tối đa 2 lượt, thường ở 2 ngày khác nhau.
 */
class DeliveryScheduleService
{
    /** Đơn CẦN GIAO ngày X: mở khoảng thuê hôm đó, chưa giao xong. */
    public const PICKUP_STATUSES = ['pending', 'confirmed'];

    /** Đơn CẦN THU ngày X: kết thúc khoảng thuê hôm đó, đang thuê/đã xác nhận. */
    public const RETURN_STATUSES = ['confirmed', 'renting'];

    /**
     * Cấu hình từng lượt: cột ngày, trạng thái hợp lệ, cột giờ đã chốt.
     *
     * @return array{date:string,statuses:list<string>,time:string}
     */
    private function leg(string $leg): array
    {
        return $leg === 'pickup'
            ? ['date' => 'start_date', 'statuses' => self::PICKUP_STATUSES, 'time' => 'confirmed_pickup_time']
            : ['date' => 'end_date', 'statuses' => self::RETURN_STATUSES, 'time' => 'confirmed_return_time'];
    }

    /**
     * Đơn cần giao/thu trong 1 ngày, sắp theo giờ đã chốt — chưa chốt giờ xuống cuối.
     * 'col IS NULL, col, code' chạy đúng cả sqlite lẫn MySQL.
     *
     * @return Collection<int,Order>
     */
    public function legOrders(string $leg, Carbon $date): Collection
    {
        $cfg = $this->leg($leg);

        return Order::query()
            ->where('is_parent', false)   // đơn cha chỉ gom đợt, không có món để giao
            ->with(['items.product', 'serviceLocation'])
            ->whereDate($cfg['date'], $date)
            ->whereIn('status', $cfg['statuses'])
            ->orderByRaw("{$cfg['time']} IS NULL, {$cfg['time']}, code")
            ->get();
    }

    /**
     * Số đơn giao/thu từng ngày trong tháng — FE bôi đỏ ô ngày có đơn.
     * Chỉ trả những ngày CÓ đơn (tháng rỗng = mảng rỗng).
     *
     * @return list<array{date:string,pickups:int,returns:int}>
     */
    public function monthDays(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $countBy = function (string $leg) use ($start, $end) {
            $cfg = $this->leg($leg);

            return Order::query()
                ->where('is_parent', false)
                ->whereIn('status', $cfg['statuses'])
                ->whereBetween($cfg['date'], [$start, $end])
                ->pluck($cfg['date'])
                ->countBy(fn (Carbon $d) => $d->toDateString());
        };

        $pickups = $countBy('pickup');
        $returns = $countBy('return');

        return $pickups->keys()
            ->merge($returns->keys())
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $d) => [
                'date' => $d,
                'pickups' => (int) ($pickups[$d] ?? 0),
                'returns' => (int) ($returns[$d] ?? 0),
            ])
            ->all();
    }

    /**
     * Chuẩn hoá 1 đơn cho danh sách 1 lượt — dùng chung mọi nơi (web/in/PDF/CSV/mail),
     * chỉ khác field giờ nào được lấy làm 'time'.
     *
     * @return array<string,mixed>
     */
    public function row(Order $o, string $leg): array
    {
        return [
            'id' => $o->id,
            'code' => $o->code,
            'time' => $leg === 'pickup' ? $o->confirmed_pickup_time : $o->confirmed_return_time,
            'customer_name' => $o->customer_name,
            'customer_phone' => $o->customer_phone,
            'customer_address' => $o->customer_address,
            'service_location' => $o->serviceLocation?->name,
            'session' => $o->session,
            'status' => $o->status,
            'payment_status' => $o->payment_status,
            'amount_due' => $o->amount_due,
            'deposit_total' => $o->deposit_total,
            'schedule_note' => $o->schedule_note,
            'items' => $o->items->map(fn ($i) => [
                'name' => $i->product?->name ?? '(đã xoá)',
                'quantity' => $i->quantity,
            ])->values(),
        ];
    }
}
