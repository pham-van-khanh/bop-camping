<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lịch giao/thu cho shipper (bopcamping-rtkh, prd_delivery_schedule FR-5).
 * Lịch THÁNG: ngày nào có đơn thì bôi đỏ + đếm số đơn, ngày đã qua bị khoá; bấm 1 ngày
 * thì liệt kê đơn cần giao / cần thu của ngày đó, sắp theo giờ đã chốt (feedback 2026-07-28).
 * Không có role/tài khoản shipper riêng — dùng chung đăng nhập admin (out of scope theo PRD).
 */
class DeliveryScheduleController extends Controller
{
    /** Đơn CẦN GIAO ngày X: mở khoảng thuê hôm đó, chưa giao xong. */
    private const PICKUP_STATUSES = ['pending', 'confirmed'];

    /** Đơn CẦN THU ngày X: kết thúc khoảng thuê hôm đó, đang thuê/đã xác nhận. */
    private const RETURN_STATUSES = ['confirmed', 'renting'];

    public function index(Request $request): Response
    {
        // Param lỗi hoặc thiếu → hôm nay / tháng của ngày đang chọn, KHÔNG 500 (link cũ, gõ tay).
        // $report=false: input không hợp lệ là chuyện bình thường, không phải lỗi hệ thống.
        $date = rescue(
            fn () => Carbon::parse($request->input('date'))->startOfDay(),
            Carbon::today(),
            false,
        );
        $month = rescue(
            fn () => Carbon::createFromFormat('Y-m-d', $request->input('month').'-01')->startOfMonth(),
            $date->copy()->startOfMonth(),
            false,
        );

        $pickups = $this->ordersOf('start_date', self::PICKUP_STATUSES, $date, 'confirmed_pickup_time');
        $returns = $this->ordersOf('end_date', self::RETURN_STATUSES, $date, 'confirmed_return_time');

        $pickupRows = $pickups->map(fn (Order $o) => $this->row($o, 'pickup'))->values();
        $returnRows = $returns->map(fn (Order $o) => $this->row($o, 'return'))->values();

        return Inertia::render('Admin/DeliverySchedule', [
            // Lịch tháng
            'month' => $month->format('Y-m'),
            'month_label' => 'Tháng '.$month->month.' · '.$month->year,
            'prev_month' => $month->copy()->subMonth()->format('Y-m'),
            'next_month' => $month->copy()->addMonth()->format('Y-m'),
            'days' => $this->monthDays($month),
            // Ngày đang chọn
            'date' => $date->toDateString(),
            'date_label' => Str::ucfirst($date->locale('vi')->isoFormat('dddd, DD/MM/YYYY')),
            'today' => Carbon::today()->toDateString(),
            'pickups' => $pickupRows,
            'returns' => $returnRows,
            'stats' => [
                'pickups' => $pickupRows->count(),
                'returns' => $returnRows->count(),
                'unscheduled' => $pickupRows->concat($returnRows)->whereNull('time')->count(),
            ],
        ]);
    }

    /**
     * Đơn cần giao/thu trong 1 ngày, sắp theo giờ đã chốt — chưa chốt giờ xuống cuối.
     * 'col IS NULL, col, code' chạy đúng cả sqlite lẫn MySQL.
     *
     * @param  list<string>  $statuses
     * @return Collection<int,Order>
     */
    private function ordersOf(string $dateColumn, array $statuses, Carbon $date, string $timeColumn)
    {
        return Order::query()
            ->where('is_parent', false)   // đơn cha chỉ gom đợt, không có món để giao
            ->with(['items.product', 'serviceLocation'])
            ->whereDate($dateColumn, $date)
            ->whereIn('status', $statuses)
            ->orderByRaw("$timeColumn IS NULL, $timeColumn, code")
            ->get();
    }

    /**
     * Số đơn giao/thu từng ngày trong tháng — FE bôi đỏ ô ngày có đơn.
     * Chỉ trả những ngày CÓ đơn (tháng rỗng = mảng rỗng).
     *
     * @return list<array{date:string,pickups:int,returns:int}>
     */
    private function monthDays(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $countBy = fn (string $column, array $statuses) => Order::query()
            ->where('is_parent', false)
            ->whereIn('status', $statuses)
            ->whereBetween($column, [$start, $end])
            ->pluck($column)
            ->countBy(fn (Carbon $d) => $d->toDateString());

        $pickups = $countBy('start_date', self::PICKUP_STATUSES);
        $returns = $countBy('end_date', self::RETURN_STATUSES);

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
     * Chuẩn hoá 1 đơn cho danh sách giao ('pickup') hoặc thu ('return') — dùng chung shape,
     * chỉ khác field giờ nào được lấy làm 'time'.
     *
     * @return array<string,mixed>
     */
    private function row(Order $o, string $type): array
    {
        return [
            'id' => $o->id,
            'code' => $o->code,
            'time' => $type === 'pickup' ? $o->confirmed_pickup_time : $o->confirmed_return_time,
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
