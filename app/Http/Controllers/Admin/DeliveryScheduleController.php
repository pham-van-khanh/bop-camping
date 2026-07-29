<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lịch giao/thu cho shipper (bopcamping-rtkh + yc7d, prd_delivery_schedule FR-5,
 * prd_shipper_delivery_ops FR-2).
 *
 * Lịch THÁNG: ngày có đơn bôi đỏ + đếm số đơn, ngày đã qua bị khoá; bấm 1 ngày thì liệt kê
 * đơn cần giao / cần thu, sắp theo thứ tự admin kéo-thả rồi tới giờ đã chốt. Admin gán
 * shipper cho từng LƯỢT (giao/thu) và lọc lịch theo người.
 *
 * Dữ liệu lấy qua DeliveryScheduleService để trang in/PDF/CSV/shipper dùng chung 1 nguồn.
 */
class DeliveryScheduleController extends Controller
{
    /** Giá trị lọc đặc biệt: chỉ xem đơn chưa gán shipper. */
    private const FILTER_UNASSIGNED = 'none';

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

        [$shipperId, $unassignedOnly, $filter] = $this->resolveFilter($request->input('shipper'));

        $rows = fn (string $leg) => $this->schedule
            ->legOrders($leg, $date, $shipperId, $unassignedOnly)
            ->map(fn (Order $o) => $this->schedule->row($o, $leg))
            ->values();

        $pickupRows = $rows('pickup');
        $returnRows = $rows('return');

        return Inertia::render('Admin/DeliverySchedule', [
            // Lịch tháng
            'month' => $month->format('Y-m'),
            'month_label' => 'Tháng '.$month->month.' · '.$month->year,
            'prev_month' => $month->copy()->subMonth()->format('Y-m'),
            'next_month' => $month->copy()->addMonth()->format('Y-m'),
            'days' => $this->schedule->monthDays($month, $shipperId, $unassignedOnly),
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
                'unassigned' => $pickupRows->concat($returnRows)->whereNull('shipper_id')->count(),
            ],
            // Gán shipper (bopcamping-yc7d)
            'shippers' => User::shippers()->get(['id', 'name'])->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]),
            'filters' => ['shipper' => $filter],
        ]);
    }

    /**
     * Gán / bỏ gán shipper cho 1 LƯỢT của 1 đơn. Lượt giao và lượt thu độc lập nhau
     * (hai ngày khác nhau, có thể hai người khác nhau).
     */
    public function assign(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'leg' => ['required', 'in:pickup,return'],
            'shipper_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_shipper', true)],
        ], [
            'shipper_id.exists' => 'Tài khoản này không phải shipper.',
        ]);

        if ($order->is_parent) {
            return back()->withErrors(['shipper_id' => 'Đơn gộp: gán shipper trên từng đợt (đơn con).']);
        }
        if (in_array($order->status, ['returned', 'cancelled'], true)) {
            return back()->withErrors(['shipper_id' => 'Đơn đã trả/đã huỷ — không gán shipper nữa.']);
        }

        $column = $this->schedule->columns($data['leg'])['shipper'];
        $order->update([$column => $data['shipper_id'] ?? null]);

        return back()->with('success', "Đơn {$order->code}: đã cập nhật shipper");
    }

    /** Gán 1 shipper cho MỌI đơn CHƯA có shipper của (ngày, lượt) — không ghi đè đơn đã gán. */
    public function assignAll(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'leg' => ['required', 'in:pickup,return'],
            'date' => ['required', 'date'],
            'shipper_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_shipper', true)],
        ], [
            'shipper_id.exists' => 'Tài khoản này không phải shipper.',
        ]);

        $column = $this->schedule->columns($data['leg'])['shipper'];

        $affected = $this->schedule
            ->legQuery($data['leg'], Carbon::parse($data['date']))
            ->whereNull($column)
            ->update([$column => $data['shipper_id']]);

        return back()->with('success', $affected > 0
            ? "Đã gán shipper cho {$affected} đơn chưa có người."
            : 'Không còn đơn nào chưa có shipper.');
    }

    /**
     * Lưu thứ tự đi trong ngày (admin kéo-thả). Chỉ nhận đơn thuộc đúng (ngày, lượt) —
     * id lạ bị bỏ qua để không ai sắp thứ tự cho đơn ngoài phạm vi đang xem.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'leg' => ['required', 'in:pickup,return'],
            'date' => ['required', 'date'],
            'order_ids' => ['required', 'array', 'max:200'],
            'order_ids.*' => ['integer'],
        ]);

        $column = $this->schedule->columns($data['leg'])['sort'];
        $allowed = $this->schedule->legQuery($data['leg'], Carbon::parse($data['date']))->pluck('id')->all();

        DB::transaction(function () use ($data, $column, $allowed) {
            $position = 0;
            foreach ($data['order_ids'] as $id) {
                if (! in_array((int) $id, $allowed, true)) {
                    continue;   // id không thuộc ngày/lượt đang xem → bỏ qua
                }
                Order::whereKey($id)->update([$column => ++$position]);
            }
        });

        return back()->with('success', 'Đã lưu thứ tự đi.');
    }

    /**
     * Đọc param lọc `shipper`: '' hoặc null = tất cả · 'none' = chưa gán · số = 1 shipper.
     *
     * @return array{0:int|null,1:bool,2:string}
     */
    private function resolveFilter(mixed $input): array
    {
        $raw = is_string($input) || is_int($input) ? (string) $input : '';

        if ($raw === self::FILTER_UNASSIGNED) {
            return [null, true, self::FILTER_UNASSIGNED];
        }
        if ($raw !== '' && ctype_digit($raw)) {
            return [(int) $raw, false, $raw];
        }

        return [null, false, ''];
    }
}
