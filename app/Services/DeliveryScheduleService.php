<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Nguồn chân lý cho lịch giao/thu (bopcamping-4gy0). Rút ra khỏi Admin\DeliveryScheduleController
 * để trang admin, trang shipper và email lịch đều đọc CÙNG một dữ liệu —
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
     * Cấu hình từng lượt: cột ngày, trạng thái hợp lệ, cột giờ đã chốt, cột shipper.
     *
     * @return array{date:string,statuses:list<string>,time:string,shipper:string}
     */
    private function leg(string $leg): array
    {
        return $leg === 'pickup'
            ? ['date' => 'start_date', 'statuses' => self::PICKUP_STATUSES, 'time' => 'confirmed_pickup_time', 'shipper' => 'pickup_shipper_id']
            : ['date' => 'end_date', 'statuses' => self::RETURN_STATUSES, 'time' => 'confirmed_return_time', 'shipper' => 'return_shipper_id'];
    }

    /**
     * Đơn cần giao/thu trong 1 ngày.
     *
     * Thứ tự: theo giờ đã chốt, đơn chưa chốt giờ xuống cuối ('col IS NULL, col' chạy
     * đúng cả sqlite lẫn MySQL). Không có sắp thứ tự thủ công — chủ shop bỏ kéo-thả (29/07).
     *
     * @param  int|null  $shipperId  Chỉ lấy đơn gán cho shipper này (null = không lọc theo người)
     * @param  bool  $unassignedOnly  Chỉ lấy đơn CHƯA gán shipper (bỏ qua $shipperId)
     * @return Collection<int,Order>
     */
    public function legOrders(string $leg, Carbon $date, ?int $shipperId = null, bool $unassignedOnly = false): Collection
    {
        $cfg = $this->leg($leg);

        return $this->legQuery($leg, $date)
            ->with(['items.product', 'serviceLocation', 'pickupShipper:id,name', 'returnShipper:id,name'])
            ->when($unassignedOnly, fn ($q) => $q->whereNull($cfg['shipper']))
            ->when(! $unassignedOnly && $shipperId !== null, fn ($q) => $q->where($cfg['shipper'], $shipperId))
            ->orderByRaw("{$cfg['time']} IS NULL, {$cfg['time']}, code")
            ->get();
    }

    /**
     * Query gốc "đơn của lượt này trong ngày này" — dùng chung cho đọc danh sách và cho
     * hành động ghi (gán shipper cả ngày) để phạm vi luôn khớp nhau.
     *
     * @return Builder<Order>
     */
    public function legQuery(string $leg, Carbon $date)
    {
        $cfg = $this->leg($leg);

        return Order::query()
            ->where('is_parent', false)   // đơn cha chỉ gom đợt, không có món để giao
            ->whereDate($cfg['date'], $date)
            ->whereIn('status', $cfg['statuses']);
    }

    /** Tên cột theo lượt — cho caller cần ghi trực tiếp (gán shipper). */
    public function columns(string $leg): array
    {
        return $this->leg($leg);
    }

    /**
     * Số đơn giao/thu từng ngày trong tháng — FE bôi đỏ ô ngày có đơn.
     * Chỉ trả những ngày CÓ đơn (tháng rỗng = mảng rỗng).
     *
     * @param  int|null  $shipperId  Đếm theo shipper này (null = tất cả)
     * @param  bool  $unassignedOnly  Chỉ đếm đơn chưa gán shipper
     * @return list<array{date:string,pickups:int,returns:int}>
     */
    public function monthDays(Carbon $month, ?int $shipperId = null, bool $unassignedOnly = false): array
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $countBy = function (string $leg) use ($start, $end, $shipperId, $unassignedOnly) {
            $cfg = $this->leg($leg);

            return Order::query()
                ->where('is_parent', false)
                ->whereIn('status', $cfg['statuses'])
                ->whereBetween($cfg['date'], [$start, $end])
                ->when($unassignedOnly, fn ($q) => $q->whereNull($cfg['shipper']))
                ->when(! $unassignedOnly && $shipperId !== null, fn ($q) => $q->where($cfg['shipper'], $shipperId))
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
     * Chuẩn hoá 1 đơn cho danh sách 1 lượt — dùng chung mọi nơi (web admin/shipper/mail),
     * chỉ khác field giờ nào được lấy làm 'time'.
     *
     * @return array<string,mixed>
     */
    public function row(Order $o, string $leg): array
    {
        $cfg = $this->leg($leg);
        $shipper = $leg === 'pickup' ? $o->pickupShipper : $o->returnShipper;

        return [
            'id' => $o->id,
            'code' => $o->code,
            'time' => $o->{$cfg['time']},
            'leg' => $leg,
            'shipper_id' => $o->{$cfg['shipper']},
            'shipper_name' => $shipper?->name,
            'customer_name' => $o->customer_name,
            'customer_phone' => $o->customer_phone,
            'customer_address' => $o->customer_address,
            'service_location' => $o->serviceLocation?->name,
            'session' => $o->session,
            'status' => $o->status,
            'payment_status' => $o->payment_status,
            'amount_due' => $o->amount_due,
            'deposit_total' => $o->deposit_total,
            // Thu tiền 2 khoản độc lập (bopcamping-q7i0) — shipper thu hộ khoản nào chưa thu.
            'rental_due' => $o->rental_due,
            'rental_paid' => $o->rentalPaid(),
            'deposit_paid' => $o->depositPaid(),
            'deposit_refund_status' => $o->deposit_refund_status,
            'deposit_refund_note' => $o->deposit_refund_note,
            'schedule_note' => $o->schedule_note,
            'items' => $o->items->map(fn ($i) => [
                'name' => $i->product?->name ?? '(đã xoá)',
                'quantity' => $i->quantity,
            ])->values(),
        ];
    }
}
