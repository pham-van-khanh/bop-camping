<?php

namespace App\Http\Controllers\Shipper;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DeliveryScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lịch giao/thu CỦA CHÍNH shipper đang đăng nhập (bopcamping-lsch / w2yl).
 *
 * INVARIANT bảo mật (adr_shipper_role_and_access mục 3): mọi truy vấn ở đây phải kẹp
 * shipper id của người đăng nhập. Không nhận order_id tuỳ ý từ request để đọc dữ liệu —
 * shipper chỉ thấy đơn được gán cho mình (OWASP A01, CWE-639).
 */
class ScheduleController extends Controller
{
    /** Xem lịch quá khứ 2 ngày (đối chiếu việc hôm qua) và tối đa 14 ngày tới. */
    private const DAYS_BACK = 2;

    private const DAYS_AHEAD = 14;

    public function __construct(private DeliveryScheduleService $schedule) {}

    public function index(Request $request): Response
    {
        $me = $request->user();
        $date = $this->resolveDate($request->input('date'));

        $pickups = $this->schedule->legOrders('pickup', $date, $me->id)
            ->map(fn (Order $o) => $this->schedule->row($o, 'pickup'))->values();
        $returns = $this->schedule->legOrders('return', $date, $me->id)
            ->map(fn (Order $o) => $this->schedule->row($o, 'return'))->values();

        return Inertia::render('Shipper/Schedule', [
            'date' => $date->toDateString(),
            'date_label' => Str::ucfirst($date->locale('vi')->isoFormat('dddd, DD/MM/YYYY')),
            'today' => Carbon::today()->toDateString(),
            'prev_date' => $this->clamp($date->copy()->subDay())->toDateString(),
            'next_date' => $this->clamp($date->copy()->addDay())->toDateString(),
            'pickups' => $pickups,
            'returns' => $returns,
        ]);
    }

    /** Ngày yêu cầu → ngày hợp lệ trong khoảng cho phép; sai định dạng thì về hôm nay. */
    private function resolveDate(mixed $input): Carbon
    {
        $date = rescue(fn () => Carbon::parse($input)->startOfDay(), Carbon::today(), false);

        return $this->clamp($date);
    }

    private function clamp(Carbon $date): Carbon
    {
        $min = Carbon::today()->subDays(self::DAYS_BACK);
        $max = Carbon::today()->addDays(self::DAYS_AHEAD);

        return $date->lessThan($min) ? $min : ($date->greaterThan($max) ? $max : $date);
    }
}
