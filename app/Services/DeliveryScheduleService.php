<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Nguồn chân lý cho lịch giao/thu (bopcamping-4gy0). Rút ra khỏi Admin\DeliveryScheduleController
 * để trang admin, trang shipper và tin nhắn giao việc đều đọc CÙNG một dữ liệu —
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
     * Giờ mặc định TOÀN SHOP cho đơn ĐÃ XÁC NHẬN mà không suy được giờ từ buổi khách chọn
     * (tức đơn thuê nhiều ngày) — chủ shop chốt 30/07/2026: giao 08:00, thu 21:00.
     * 21:00 muộn hơn giờ đóng cửa trong Cài đặt shop là CÓ Ý: chừa dư cho lượt thu buổi tối.
     * Đơn còn 'pending' KHÔNG áp mặc định này — chưa xác nhận thì chưa hẹn giờ với khách.
     */
    private const FALLBACK_TIMES = ['pickup' => '08:00', 'return' => '21:00'];

    /**
     * Trạng thái coi là "admin đã xác nhận đơn": mới được áp giờ mặc định toàn shop, và
     * mới được nhờ shipper thu tiền (đơn còn chờ xác nhận thì giá/lịch còn có thể đổi).
     */
    public const CONFIRMED_STATUSES = ['confirmed', 'renting'];

    /**
     * Cấu hình từng lượt: cột ngày, trạng thái hợp lệ, cột giờ đã chốt, cột giờ MẶC ĐỊNH,
     * cột shipper.
     *
     * @return array{date:string,statuses:list<string>,time:string,default_time:string,shipper:string}
     */
    private function leg(string $leg): array
    {
        return $leg === 'pickup'
            ? ['date' => 'start_date', 'statuses' => self::PICKUP_STATUSES, 'time' => 'confirmed_pickup_time', 'default_time' => 'requested_pickup_time', 'shipper' => 'pickup_shipper_id']
            : ['date' => 'end_date', 'statuses' => self::RETURN_STATUSES, 'time' => 'confirmed_return_time', 'default_time' => 'requested_return_time', 'shipper' => 'return_shipper_id'];
    }

    /**
     * Giờ THỰC DỤNG của 1 lượt, theo 3 tầng ưu tiên (feedback 30/07/2026):
     *
     * 1. Giờ admin ĐÃ CHỐT (`confirmed_*`).
     * 2. Giờ suy từ buổi khách chọn với đơn thuê ≤ 1 ngày (sáng 8–12h · chiều 13–20h ·
     *    cả ngày 8–20h). Lấy sẵn từ `requested_*` mà OrderSplitter tính lúc checkout —
     *    KHÔNG suy lại ở đây để giữ một nguồn chân lý.
     * 3. Đơn ĐÃ XÁC NHẬN nhưng không có gì ở trên (đơn nhiều ngày) → mặc định toàn shop
     *    giao 08:00 / thu 21:00.
     *
     * Đơn còn chờ xác nhận mà không suy được giờ → null = thật sự chưa có giờ.
     */
    public function effectiveTime(Order $o, string $leg): ?string
    {
        $cfg = $this->leg($leg);

        return $o->{$cfg['time']}
            ?? $o->{$cfg['default_time']}
            ?? ($o->isConfirmed() ? self::FALLBACK_TIMES[$leg] : null);
    }

    /** True khi giờ đang dùng chỉ là giờ mặc định (admin chưa chốt) — UI nói rõ cho shipper. */
    public function timeIsDefault(Order $o, string $leg): bool
    {
        $cfg = $this->leg($leg);

        return $o->{$cfg['time']} === null && $this->effectiveTime($o, $leg) !== null;
    }

    /**
     * Đơn cần giao/thu trong 1 ngày.
     *
     * Thứ tự: theo GIỜ THỰC DỤNG (xem effectiveTime), đơn không có giờ nào xuống cuối.
     * Sắp ở PHP chứ không ở SQL: luật giờ mặc định phụ thuộc cả buổi khách chọn lẫn trạng
     * thái đơn, viết lại trong SQL là nhân đôi luật — mỗi ngày chỉ vài chục đơn nên rẻ.
     * Không có sắp thứ tự thủ công — chủ shop bỏ kéo-thả (29/07).
     *
     * @param  int|null  $shipperId  Chỉ lấy đơn gán cho shipper này (null = không lọc theo người)
     * @param  bool  $unassignedOnly  Chỉ lấy đơn CHƯA gán shipper (bỏ qua $shipperId)
     * @return Collection<int,Order>
     */
    public function legOrders(string $leg, Carbon $date, ?int $shipperId = null, bool $unassignedOnly = false): Collection
    {
        $cfg = $this->leg($leg);

        return $this->legQuery($leg, $date)
            // phone: để admin mở Zalo đúng shipper đã gán (bopcamping-dolb)
            ->with([
                'items.product', 'serviceLocation',
                'pickupShipper:id,name,phone', 'returnShipper:id,name,phone',
                // Ai đã làm gì (bopcamping-3wfk) — chỉ cần tên người làm.
                'rentalPaidBy:id,name', 'depositPaidBy:id,name',
                'depositRefundedBy:id,name',
                'deliveredBy:id,name', 'collectedBy:id,name',
            ])
            ->when($unassignedOnly, fn ($q) => $q->whereNull($cfg['shipper']))
            ->when(! $unassignedOnly && $shipperId !== null, fn ($q) => $q->where($cfg['shipper'], $shipperId))
            ->orderBy('code')
            ->get()
            ->sortBy(fn (Order $o) => [$this->effectiveTime($o, $leg) === null, $this->effectiveTime($o, $leg) ?? ''])
            ->values();
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
        // Mốc phải là DATETIME, không phải chuỗi ngày. Cột khai báo date() nhưng giá trị lưu
        // kèm giờ ('2026-07-31 00:00:00' — xem bopcamping-ioku), nên so với chuỗi '2026-07-31'
        // thì '2026-07-31 00:00:00' > '2026-07-31' và NGÀY CUỐI THÁNG bị loại khỏi lịch giao.
        // Dùng endOfDay() vẫn giữ được index (khác whereDate() bọc DATE(col)).
        $start = $month->copy()->startOfMonth()->startOfDay();
        $end = $month->copy()->endOfMonth()->endOfDay();

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
     * Tin nhắn giao việc cho shipper, đúng mẫu chủ shop chốt 30/07/2026 (bopcamping-dolb).
     * Sinh ở SERVER để 1 nguồn chân lý — admin chỉ bấm Copy rồi dán vào Zalo (không có
     * Zalo OA/ZNS nên không gửi tự động được).
     *
     * Dòng "nhờ thu" CHỈ xuất hiện khi khoản đó chưa thu — tránh việc shipper thu lần hai.
     */
    public function zaloMessage(Order $o, string $leg): string
    {
        $vnd = fn (int $n) => number_format($n, 0, ',', '.').'đ';
        // Giờ mặc định (chưa chốt) thì nói rõ để shipper biết còn có thể đổi.
        $when = function (string $which) use ($o) {
            $date = ($which === 'pickup' ? $o->start_date : $o->end_date)->format('d/m/Y');
            $time = $this->effectiveTime($o, $which);

            if ($time === null) {
                return $date.' (chưa chốt giờ)';
            }

            return $date.' · '.$time.($this->timeIsDefault($o, $which) ? ' (giờ mặc định)' : '');
        };

        $lines = ["Mã đơn: {$o->code}", $o->customer_name];

        if ($o->customer_phone) {
            $lines[] = $o->customer_phone;
        }
        if ($o->customer_address) {
            $lines[] = $o->customer_address;
        }

        $lines[] = '';
        $lines[] = 'Sản phẩm:';
        foreach ($o->items as $item) {
            $lines[] = $item->quantity.' x '.($item->product?->name ?? '(đã xoá)');
        }

        // Lượt đang giao việc để TRƯỚC, lượt còn lại để sau cho shipper biết cả hai mốc.
        $lines[] = '';
        $lines[] = $leg === 'pickup'
            ? 'Ngày giờ giao: '.$when('pickup')
            : 'Ngày giờ thu: '.$when('return');
        $lines[] = $leg === 'pickup'
            ? 'Ngày giờ thu: '.$when('return')
            : 'Ngày giờ giao: '.$when('pickup');

        // Đơn CHƯA xác nhận thì không nhờ thu tiền: giá/lịch còn có thể đổi, thậm chí huỷ.
        if (! $o->isConfirmed()) {
            $lines[] = 'Đơn chưa được xác nhận — CHƯA thu tiền, chờ shop chốt với khách.';
        } else {
            if (! $o->rentalPaid()) {
                $lines[] = 'Nhờ shipper thu tiền thuê: '.$vnd($o->rental_due);
            }
            if (! $o->depositPaid()) {
                $lines[] = 'Nhờ shipper thu tiền cọc: '.$vnd((int) $o->deposit_total);
            }
            if ($o->rentalPaid() && $o->depositPaid()) {
                $lines[] = 'Khách đã chuyển đủ tiền — không cần thu gì.';
            }
        }

        if ($leg === 'return') {
            $lines[] = '';
            $lines[] = 'Shipper tự kiểm tra đồ và trả cọc cho khách: '.$vnd((int) $o->deposit_total);
        }

        if ($o->schedule_note) {
            $lines[] = '';
            $lines[] = 'Ghi chú: '.$o->schedule_note;
        }

        // Link mở đúng NGÀY của lượt này trong app shipper (feedback 30/07/2026).
        $legDate = ($leg === 'pickup' ? $o->start_date : $o->end_date);
        $lines[] = '';
        $lines[] = 'Xem đơn: '.route('shipper.schedule', [
            'date' => $legDate->toDateString(),
            'month' => $legDate->format('Y-m'),
        ]);

        $lines[] = '';
        $lines[] = 'Nếu có vấn đề gì khác vui lòng liên hệ admin.';

        return implode("\n", $lines);
    }

    /**
     * "Ai phải làm gì" cho lượt này (bopcamping-3wfk): việc còn lại theo đúng thứ tự làm.
     * Rỗng = lượt này xong việc. Tiền tính theo khoản CHƯA thu, không nhắc khoản đã thu.
     *
     * @return list<string>
     */
    public function todo(Order $o, string $leg): array
    {
        $vnd = fn (int $n) => number_format($n, 0, ',', '.').'đ';
        $todo = [];

        // Chưa xác nhận thì việc duy nhất là chờ shop chốt — KHÔNG nhắc thu tiền.
        if (! $o->isConfirmed()) {
            return ['Chờ shop xác nhận đơn'];
        }

        if ($leg === 'pickup') {
            if ($o->status === 'confirmed') {
                $todo[] = 'Giao đồ';
            }
        } elseif ($o->status === 'renting') {
            $todo[] = 'Thu đồ';
        }

        if (! $o->rentalPaid()) {
            $todo[] = 'Thu tiền thuê '.$vnd($o->rental_due);
        }
        if (! $o->depositPaid()) {
            $todo[] = 'Thu cọc '.$vnd((int) $o->deposit_total);
        }
        // Hoàn cọc chỉ là việc của lượt THU, và chỉ khi đã thu cọc của khách trước đó.
        if ($leg === 'return' && $o->depositPaid() && $o->deposit_refund_status !== 'refunded') {
            $todo[] = 'Hoàn cọc '.$vnd((int) $o->deposit_total);
        }

        return $todo;
    }

    /**
     * Chuẩn hoá 1 đơn cho danh sách 1 lượt — dùng chung mọi nơi (web admin/shipper),
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
            // Giờ của lượt này: đã chốt, nếu chưa thì giờ mặc định theo khung giờ shop.
            'time' => $this->effectiveTime($o, $leg),
            'time_is_default' => $this->timeIsDefault($o, $leg),
            // Cả hai mốc để shipper biết luôn đơn này giao lúc nào / thu lúc nào (feedback 30/07).
            'pickup_date' => $o->start_date->format('d/m/Y'),
            'pickup_time' => $this->effectiveTime($o, 'pickup'),
            'pickup_time_is_default' => $this->timeIsDefault($o, 'pickup'),
            'return_date' => $o->end_date->format('d/m/Y'),
            'return_time' => $this->effectiveTime($o, 'return'),
            'return_time_is_default' => $this->timeIsDefault($o, 'return'),
            'leg' => $leg,
            'shipper_id' => $o->{$cfg['shipper']},
            'shipper_name' => $shipper?->name,
            // Lượt CÒN LẠI của đơn: admin thấy ngay còn thiếu người hay chưa (bopcamping-h7w4).
            'other_leg_shipper_name' => ($leg === 'pickup' ? $o->returnShipper : $o->pickupShipper)?->name,
            'customer_name' => $o->customer_name,
            'customer_phone' => $o->customer_phone,
            'customer_address' => $o->customer_address,
            'service_location' => $o->serviceLocation?->name,
            'session' => $o->session,
            'status' => $o->status,
            'amount_due' => $o->amount_due,
            'deposit_total' => $o->deposit_total,
            // Thu tiền 2 khoản độc lập (bopcamping-q7i0) — shipper thu hộ khoản nào chưa thu.
            'rental_due' => $o->rental_due,
            'rental_paid' => $o->rentalPaid(),
            'deposit_paid' => $o->depositPaid(),
            'deposit_refund_status' => $o->deposit_refund_status,
            'deposit_refund_note' => $o->deposit_refund_note,
            'schedule_note' => $o->schedule_note,
            // Ai đã làm gì + việc còn lại của lượt này (bopcamping-3wfk)
            'actions' => $o->actionLog(),
            'todo' => $this->todo($o, $leg),
            'items' => $o->items->map(fn ($i) => [
                'name' => $i->product?->name ?? '(đã xoá)',
                'quantity' => $i->quantity,
            ])->values(),
        ];
    }
}
