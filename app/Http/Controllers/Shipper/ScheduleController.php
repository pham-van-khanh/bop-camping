<?php

namespace App\Http\Controllers\Shipper;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DeliveryScheduleService;
use Illuminate\Http\RedirectResponse;
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

    /**
     * Shipper đánh dấu ĐÃ GIAO: confirmed → renting. Chỉ người được gán lượt GIAO của đúng
     * đơn này mới làm được (chống IDOR — CWE-639). Đi qua status sẵn có nên OrderObserver
     * vẫn gửi mail cho khách như khi admin bấm.
     */
    public function markDelivered(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeLeg($request, $order, 'pickup');

        if ($order->status !== 'confirmed') {
            return back()->withErrors(['status' => 'Đơn chưa được xác nhận hoặc đã giao rồi.']);
        }

        $order->update(['status' => 'renting']);

        return back()->with('success', "Đơn {$order->code}: đã giao");
    }

    /** Shipper đánh dấu ĐÃ THU: renting → returned. Chỉ người được gán lượt THU của đơn này. */
    public function markCollected(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeLeg($request, $order, 'return');

        if ($order->status !== 'renting') {
            return back()->withErrors(['status' => 'Đơn chưa ở trạng thái đang thuê.']);
        }

        $order->update(['status' => 'returned']);

        return back()->with('success', "Đơn {$order->code}: đã thu đồ");
    }

    /**
     * Chốt cửa uỷ quyền theo BẢN GHI: đơn phải được gán đúng lượt đó cho chính người đang
     * đăng nhập. 404 (không phải 403) để không tiết lộ đơn đó có tồn tại hay không.
     */
    private function authorizeLeg(Request $request, Order $order, string $leg): void
    {
        $column = $leg === 'pickup' ? 'pickup_shipper_id' : 'return_shipper_id';

        abort_unless($order->{$column} === $request->user()->id, 404);
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
