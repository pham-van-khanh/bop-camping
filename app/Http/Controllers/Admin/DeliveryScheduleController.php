<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DeliveryScheduleService;
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
 *
 * Dữ liệu lấy qua DeliveryScheduleService để trang in/PDF/CSV/shipper dùng chung 1 nguồn.
 */
class DeliveryScheduleController extends Controller
{
    public function __construct(private DeliveryScheduleService $schedule) {}

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

        $pickupRows = $this->schedule->legOrders('pickup', $date)
            ->map(fn (Order $o) => $this->schedule->row($o, 'pickup'))->values();
        $returnRows = $this->schedule->legOrders('return', $date)
            ->map(fn (Order $o) => $this->schedule->row($o, 'return'))->values();

        return Inertia::render('Admin/DeliverySchedule', [
            // Lịch tháng
            'month' => $month->format('Y-m'),
            'month_label' => 'Tháng '.$month->month.' · '.$month->year,
            'prev_month' => $month->copy()->subMonth()->format('Y-m'),
            'next_month' => $month->copy()->addMonth()->format('Y-m'),
            'days' => $this->schedule->monthDays($month),
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
}
