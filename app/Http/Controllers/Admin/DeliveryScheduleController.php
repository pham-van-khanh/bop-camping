<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lịch giao/thu theo ngày cho shipper (bopcamping-rtkh, prd_delivery_schedule FR-5).
 * Một trang duy nhất: shipper thấy cần giao đơn nào / thu đơn nào hôm nay, sắp theo giờ
 * đã chốt. Không có role/tài khoản shipper riêng — dùng chung đăng nhập admin (out of scope
 * theo PRD mục 2).
 */
class DeliveryScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        // date param lỗi hoặc thiếu → hôm nay, KHÔNG 500 (AC prd_delivery_schedule mục 6).
        // $report=false: input không hợp lệ là chuyện bình thường (link cũ, gõ tay), không phải lỗi hệ thống.
        $date = rescue(
            fn () => Carbon::parse($request->input('date'))->startOfDay(),
            Carbon::today(),
            false,
        );

        $base = fn () => Order::query()->where('is_parent', false)->with(['items.product', 'serviceLocation']);

        // Cần giao hôm đó: đơn mở khoảng thuê ngày này, chưa/đã xác nhận nhưng chưa qua giai đoạn giao.
        $pickups = $base()->whereDate('start_date', $date)->whereIn('status', ['pending', 'confirmed'])
            // 'col IS NULL, col, code' — chưa chốt giờ (NULL) xuống cuối, chạy đúng cả sqlite + MySQL.
            ->orderByRaw('confirmed_pickup_time IS NULL, confirmed_pickup_time, code')
            ->get();
        // Cần thu hôm đó: đơn kết thúc khoảng thuê ngày này, đang thuê hoặc đã xác nhận (thu sớm/đúng hạn).
        $returns = $base()->whereDate('end_date', $date)->whereIn('status', ['confirmed', 'renting'])
            ->orderByRaw('confirmed_return_time IS NULL, confirmed_return_time, code')
            ->get();

        $pickupRows = $pickups->map(fn (Order $o) => $this->row($o, 'pickup'))->values();
        $returnRows = $returns->map(fn (Order $o) => $this->row($o, 'return'))->values();

        return Inertia::render('Admin/DeliverySchedule', [
            'date' => $date->toDateString(),
            'date_label' => Str::ucfirst($date->locale('vi')->isoFormat('dddd, DD/MM/YYYY')),
            'prev_date' => $date->copy()->subDay()->toDateString(),
            'next_date' => $date->copy()->addDay()->toDateString(),
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
