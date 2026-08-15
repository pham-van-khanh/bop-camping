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
 * Lịch giao/thu CỦA CHÍNH shipper đang đăng nhập (bopcamping-lsch / w2yl / lvw3).
 *
 * Lịch THÁNG: ngày nào có lượt của mình thì bôi đỏ; bấm ngày ra danh sách đơn, mở chi tiết
 * để xem món + tiền và thu tiền tại chỗ.
 *
 * INVARIANT bảo mật (adr_shipper_role_and_access mục 3): mọi truy vấn ở đây phải kẹp
 * shipper id của người đăng nhập. Không nhận order_id tuỳ ý từ request để đọc dữ liệu —
 * shipper chỉ thấy và chỉ đụng được đơn được gán cho mình (OWASP A01, CWE-639).
 */
class ScheduleController extends Controller
{
    /**
     * Xem lịch quá khứ 7 ngày để đối chiếu việc tuần trước với chủ shop (chủ shop 31/07).
     * Phía TƯƠNG LAI không giới hạn: shipper chỉ thấy đơn được gán cho chính mình nên xem
     * xa cũng không rò thêm dữ liệu, mà đơn đặt trước vài tháng thì vẫn phải xem được.
     */
    private const DAYS_BACK = 7;

    public function __construct(private DeliveryScheduleService $schedule) {}

    public function index(Request $request): Response
    {
        $me = $request->user();
        $date = $this->resolveDate($request->input('date'));
        $month = $this->resolveMonth($request->input('month'), $date);

        $rows = fn (string $leg) => $this->schedule
            ->legOrders($leg, $date, $me->id)
            ->map(fn (Order $o) => $this->schedule->row($o, $leg))
            ->values();

        return Inertia::render('Shipper/Schedule', [
            // Lịch tháng — chỉ đếm lượt CỦA MÌNH
            'month' => $month->format('Y-m'),
            'month_label' => 'Tháng '.$month->month.' · '.$month->year,
            'prev_month' => $month->copy()->subMonth()->format('Y-m'),
            'next_month' => $month->copy()->addMonth()->format('Y-m'),
            'days' => $this->schedule->monthDays($month, $me->id),
            // Ngày đang chọn
            'date' => $date->toDateString(),
            'date_label' => Str::ucfirst($date->locale('vi')->isoFormat('dddd, DD/MM/YYYY')),
            'today' => Carbon::today()->toDateString(),
            'min_date' => Carbon::today()->subDays(self::DAYS_BACK)->toDateString(),
            'pickups' => $rows('pickup'),
            'returns' => $rows('return'),
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
        $order->stampAction('delivered', $request->user()->id);   // dấu ai giao (bopcamping-3wfk)

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
        $order->stampAction('collected', $request->user()->id);   // dấu ai thu đồ

        return back()->with('success', "Đơn {$order->code}: đã thu đồ");
    }

    /**
     * Shipper thu hộ 1 khoản tiền (tiền thuê hoặc cọc) — bopcamping-lvw3.
     * KHÔNG cần admin uỷ quyền riêng (chốt 30/07): khoản nào chưa thu thì shipper thu được.
     * Chỉ ĐÁNH DẤU ĐÃ THU, không cho bỏ đánh dấu — sửa sai là việc của admin.
     */
    public function collect(Request $request, Order $order, string $kind): RedirectResponse
    {
        abort_unless(in_array($kind, Order::PAYMENT_KINDS, true), 404);
        // Thu tiền được ở CẢ HAI lượt: tiền thuê có thể mới thu đúng lúc đi thu đồ.
        $this->authorizeAnyLeg($request, $order);

        // Đơn chưa xác nhận / đã huỷ: KHÔNG cho thu hộ (giá và lịch còn có thể đổi).
        // Admin vẫn ghi nhận được khách chuyển trước — đó là việc của admin, không phải shipper.
        if (! $order->isConfirmed()) {
            return back()->withErrors(['payment' => 'Đơn chưa được xác nhận — chưa thu tiền. Gọi chủ shop nếu khách muốn trả.']);
        }
        // Ba khoản từ bopcamping-urqo — không hardcode hai khoản nữa, thêm khoản mới mà
        // quên chỗ này thì shipper cầm tiền về không có nút nào để ghi.
        $paid = match ($kind) {
            'rental' => $order->rentalPaid(),
            'fee' => $order->feePaid(),
            default => $order->depositPaid(),
        };

        if ($paid) {
            return back()->withErrors(['payment' => 'Khoản này đã được đánh dấu thu rồi.']);
        }

        $order->markPaid($kind, true, $request->user()->id);

        return back()->with('success', 'Đã ghi nhận thu '.match ($kind) {
            'rental' => 'tiền thuê',
            'fee' => 'phụ phí',
            default => 'cọc',
        });
    }

    /**
     * Shipper trả cọc lại cho khách sau khi kiểm đồ (bopcamping-lvw3) — chỉ lượt THU.
     * Ghi vào đúng trường hoàn cọc sẵn có; ghi chú dùng khi trừ cọc (rách lều, thiếu đồ).
     */
    public function refundDeposit(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeLeg($request, $order, 'return');

        $data = $request->validate([
            'deposit_refund_note' => ['nullable', 'string', 'max:500'],
        ]);

        if (! in_array($order->status, ['renting', 'returned'], true)) {
            return back()->withErrors(['refund' => 'Chỉ hoàn cọc khi đang thu đồ hoặc đơn đã trả.']);
        }
        if ($order->deposit_refund_status === 'refunded') {
            return back()->withErrors(['refund' => 'Cọc đã được đánh dấu hoàn rồi.']);
        }

        // Cùng lối vào với admin để luôn có dấu ai hoàn cọc (bopcamping-3wfk).
        $order->markRefunded(true, $request->user()->id, $data['deposit_refund_note'] ?? null);

        return back()->with('success', "Đơn {$order->code}: đã hoàn cọc cho khách");
    }

    /**
     * Chốt cửa uỷ quyền theo BẢN GHI: đơn phải được gán đúng lượt đó cho chính người đang
     * đăng nhập. 404 (không phải 403) để không tiết lộ đơn đó có tồn tại hay không.
     */
    private function authorizeLeg(Request $request, Order $order, string $leg): void
    {
        $column = $leg === 'pickup' ? 'pickup_shipper_id' : 'return_shipper_id';

        abort_unless($this->isMine($order->{$column}, $request), 404);
    }

    /** Được gán 1 trong 2 lượt của đơn là đủ (dùng cho việc thu tiền). */
    private function authorizeAnyLeg(Request $request, Order $order): void
    {
        abort_unless(
            $this->isMine($order->pickup_shipper_id, $request) || $this->isMine($order->return_shipper_id, $request),
            404,
        );
    }

    /**
     * Ép (int) cả hai bên: driver DB có thể trả id dạng chuỗi, so sánh === kiểu chéo sẽ
     * luôn sai và khoá cả người ĐÚNG. Chưa gán (null) → không khớp ai.
     */
    private function isMine(mixed $assignedId, Request $request): bool
    {
        return $assignedId !== null && (int) $assignedId === (int) $request->user()->id;
    }

    /** Ngày yêu cầu → ngày hợp lệ trong khoảng cho phép; sai định dạng thì về hôm nay. */
    private function resolveDate(mixed $input): Carbon
    {
        $date = rescue(fn () => Carbon::parse($input)->startOfDay(), Carbon::today(), false);

        return $this->clamp($date);
    }

    /** Tháng đang xem — mặc định là tháng của ngày đang chọn; param lỗi thì cũng vậy. */
    private function resolveMonth(mixed $input, Carbon $date): Carbon
    {
        return rescue(
            fn () => Carbon::createFromFormat('Y-m-d', $input.'-01')->startOfMonth(),
            $date->copy()->startOfMonth(),
            false,
        );
    }

    /** Chỉ kẹp phía quá khứ; ngày tương lai để nguyên (không giới hạn). */
    private function clamp(Carbon $date): Carbon
    {
        $min = Carbon::today()->subDays(self::DAYS_BACK);

        return $date->lessThan($min) ? $min : $date;
    }
}
