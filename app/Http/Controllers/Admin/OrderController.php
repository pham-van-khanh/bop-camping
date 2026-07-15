<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OrderDatesChangedMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Services\AvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    private const VALID_STATUSES = ['pending', 'confirmed', 'renting', 'returned', 'cancelled'];

    public function __construct(private AvailabilityService $availability) {}

    public function index(Request $request): Response
    {
        $status = $request->query('status', 'all');

        $query = Order::with(['items.product', 'items.combo', 'vouchers', 'referralUse.referrer', 'serviceLocation'])->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->get()->map(fn ($o) => [
            'id' => $o->id,
            'code' => $o->code,
            'customer_name' => $o->customer_name,
            'customer_phone' => $o->customer_phone,
            'customer_email' => $o->customer_email,
            'customer_address' => $o->customer_address,
            'start_date' => $o->start_date->format('d/m/Y'),
            'end_date' => $o->end_date->format('d/m/Y'),
            // ISO cho input[type=date] ở form đổi lịch (bopcamping-5hjm)
            'start_date_iso' => $o->start_date->format('Y-m-d'),
            'end_date_iso' => $o->end_date->format('Y-m-d'),
            'days' => $o->days,
            'total_price' => $o->total_price,
            'deposit_total' => $o->deposit_total,
            'discount_total' => $o->discount_total,
            // bopcamping-3ag: nguồn giảm từng dòng (voucher/referral/email_bonus/cap); đơn cũ null
            'discount_breakdown' => $o->discount_breakdown,
            'amount_due' => $o->amount_due,
            'status' => $o->status,
            'payment_status' => $o->payment_status,
            'deposit_refund_status' => $o->deposit_refund_status,
            'deposit_refund_note' => $o->deposit_refund_note,
            'note' => $o->note,
            'created_at' => $o->created_at->format('d/m/Y H:i'),
            // Per-store: cửa hàng thuê + đơn hệ thống tự gán (admin review theo địa chỉ)
            'service_location' => $o->serviceLocation ? ['id' => $o->serviceLocation->id, 'name' => $o->serviceLocation->name] : null,
            'location_auto_assigned' => (bool) $o->location_auto_assigned,
            'items' => $o->items->map(fn ($i) => [
                'name' => $i->product?->name ?? '(đã xoá)',
                'quantity' => $i->quantity,
                'price_per_day' => (int) $i->price_per_day,
                'days' => $i->days,
                'subtotal' => $i->subtotal,
                // bopcamping-d7l: FE nhóm items combo thành 1 khối theo group uuid (AC-3)
                'combo_group_uuid' => $i->combo_group_uuid,
                'combo_name' => $i->combo_id ? ($i->combo?->name ?? 'Combo (đã xoá)') : null,
                'allocated_price' => $i->allocated_price !== null ? (int) $i->allocated_price : null,
            ]),
            'vouchers' => $o->vouchers->map(fn ($v) => [
                'code' => $v->code,
                'type' => $v->type,
                'value' => (int) $v->value,
                'source' => $v->source,
            ]),
            'referral' => $o->referralUse ? [
                'referrer_name' => $o->referralUse->referrer?->name,
                'status' => $o->referralUse->status,
            ] : null,
        ]);

        // Thống kê
        $all = Order::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status');
        $stats = [
            'total' => Order::count(),
            'pending' => $all['pending'] ?? 0,
            'confirmed' => $all['confirmed'] ?? 0,
            'renting' => $all['renting'] ?? 0,
            'returned' => $all['returned'] ?? 0,
            'cancelled' => $all['cancelled'] ?? 0,
        ];

        // Tồn kho
        $inventory = Product::with('category')->orderBy('name')->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'category' => $p->category->name,
            'quantity' => $p->quantity,
            'status' => $p->status,
        ]);

        return Inertia::render('Admin/Orders', [
            'orders' => $orders,
            'stats' => $stats,
            'inventory' => $inventory,
            // Per-store: cửa hàng đang mở để admin đổi store cho đơn
            'service_locations' => ServiceLocation::open()->ordered()->get()->map(fn ($l) => ['id' => $l->id, 'name' => $l->name]),
            'filters' => ['status' => $status],
        ]);
    }

    /**
     * Per-store: admin đổi cửa hàng của đơn (vd theo địa chỉ khách khi đơn tự gán).
     * Chỉ đổi được nếu store đích còn đủ MỌI món của đơn trong khoảng ngày (trừ chính đơn này).
     */
    public function changeLocation(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'service_location_id' => ['required', 'integer', 'exists:service_locations,id'],
        ]);

        $location = ServiceLocation::findOrFail($data['service_location_id']);
        $order->loadMissing('items.product.serviceLocations');

        // Nhu cầu mỗi món của đơn (gộp theo product) trong khoảng đơn.
        $needed = [];
        foreach ($order->items as $item) {
            if ($item->product) {
                $needed[$item->product_id] = ($needed[$item->product_id] ?? 0) + $item->quantity;
            }
        }

        // Kiểm store đích: khả dụng (đã trừ đơn khác) + phần đơn NÀY đang chiếm ở store đích (nếu trùng) — cộng lại.
        foreach ($needed as $productId => $qty) {
            $product = $order->items->firstWhere('product_id', $productId)->product;
            $avail = $this->availability->availableQuantity($product, $order->start_date, $order->end_date, $location);
            // Nếu đơn đang ở đúng store đích, availableQuantity đã trừ chính nó → cộng lại để so đúng.
            if ($order->service_location_id === $location->id) {
                $avail += $qty;
            }
            if ($avail < $qty) {
                return back()->withErrors(['location' => "\"{$product->name}\" tại {$location->name} không đủ ({$avail}/{$qty}) cho khoảng đơn."]);
            }
        }

        $order->update(['service_location_id' => $location->id, 'location_auto_assigned' => false]);

        return back()->with('success', "Đơn {$order->code} → cơ sở {$location->name}");
    }

    /**
     * Đổi lịch thuê của đơn (bopcamping-5hjm) — chỉ đơn CHƯA giao (pending/confirmed).
     * Kiểm tồn kho khoảng mới tại store của đơn (AvailabilityService — single source),
     * tính lại tiền thuê tuyến tính theo số ngày (cọc + giảm giá GIỮ NGUYÊN),
     * re-arm email nhắc nhận đồ và gửi mail báo khách lịch mới.
     */
    public function changeDates(Request $request, Order $order): RedirectResponse
    {
        if (! in_array($order->status, ['pending', 'confirmed'], true)) {
            return back()->withErrors(['dates' => 'Chỉ đổi lịch đơn chưa giao (chờ xác nhận / đã xác nhận).']);
        }

        $data = $request->validate([
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ], [
            'start_date.after_or_equal' => 'Ngày nhận không được ở quá khứ.',
            'end_date.after_or_equal' => 'Ngày trả phải từ ngày nhận trở đi.',
        ]);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();

        $order->loadMissing('items.product', 'serviceLocation');

        // Nhu cầu mỗi món của đơn (gộp theo product) — như changeLocation.
        $needed = [];
        foreach ($order->items as $item) {
            if ($item->product) {
                $needed[$item->product_id] = ($needed[$item->product_id] ?? 0) + $item->quantity;
            }
        }

        // Khoảng mới chồng khoảng cũ → availableQuantity đã trừ chính đơn này → cộng lại để so đúng.
        $overlapsSelf = $start->lte($order->end_date) && $order->start_date->lte($end);

        foreach ($needed as $productId => $qty) {
            $product = $order->items->firstWhere('product_id', $productId)->product;
            $avail = $this->availability->availableQuantity($product, $start, $end, $order->serviceLocation);
            if ($overlapsSelf) {
                $avail += $qty;
            }
            if ($avail < $qty) {
                return back()->withErrors(['dates' => "\"{$product->name}\" không đủ hàng ({$avail}/{$qty}) cho khoảng mới."]);
            }
        }

        $oldStart = $order->start_date->copy();
        $oldEnd = $order->end_date->copy();
        $oldDays = $order->days;
        $newDays = (int) $start->diffInDays($end) + 1;

        DB::transaction(function () use ($order, $start, $end, $oldStart, $oldDays, $newDays) {
            // Tính lại tiền tuyến tính theo ngày — đúng cho cả dòng lẻ lẫn dòng combo
            // (subtotal nào cũng = đơn giá/ngày × ngày). allocated_* / cọc / giảm giá giữ nguyên.
            foreach ($order->items as $item) {
                $item->update([
                    'days' => $newDays,
                    'subtotal' => (int) round($item->subtotal * $newDays / max(1, $oldDays)),
                ]);
            }

            $order->update([
                'start_date' => $start,
                'end_date' => $end,
                'total_price' => (int) $order->items()->sum('subtotal'),
                // Ngày nhận đổi → email "Còn 1 ngày nữa!" phải gửi lại theo ngày mới.
                'pickup_reminder_sent_at' => $start->equalTo($oldStart) ? $order->pickup_reminder_sent_at : null,
            ]);
        });

        if ($email = $order->notifiableEmail()) {
            Mail::to($email)->send(new OrderDatesChangedMail($order->fresh()->loadMissing('items.product'), $oldStart, $oldEnd));
        }

        return back()->with('success', "Đơn {$order->code}: đã đổi lịch thuê");
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::VALID_STATUSES)],
        ]);

        $order->update(['status' => $validated['status']]);

        return back()->with('success', "Đơn {$order->code} → {$validated['status']}");
    }

    /**
     * Đánh dấu tình trạng chuyển tiền của đơn (bopcamping-7be) — admin bấm sau khi
     * xác nhận với khách. unpaid = chưa chuyển · deposit = đã chuyển cọc · full = chuyển hết.
     * Đơn ĐÃ TRẢ thì khoá (chuyển sang theo dõi hoàn cọc, không đổi tình trạng chuyển tiền nữa).
     */
    public function updatePayment(Request $request, Order $order): RedirectResponse
    {
        if ($order->status === 'returned') {
            return back()->withErrors(['payment_status' => 'Đơn đã trả — không đổi tình trạng chuyển tiền nữa.']);
        }

        $validated = $request->validate([
            'payment_status' => ['required', 'in:'.implode(',', Order::PAYMENT_STATUSES)],
        ]);

        $order->update(['payment_status' => $validated['payment_status']]);

        return back()->with('success', "Đơn {$order->code}: đã cập nhật tình trạng chuyển tiền");
    }

    /**
     * Đánh dấu hoàn cọc khi đơn ĐÃ TRẢ (bopcamping-7be): refunded = đã hoàn ·
     * pending = chưa hoàn; kèm lý do trừ/không hoàn đủ cọc (rách lều, hư hại…).
     */
    public function updateRefund(Request $request, Order $order): RedirectResponse
    {
        if ($order->status !== 'returned') {
            return back()->withErrors(['deposit_refund_status' => 'Chỉ hoàn cọc cho đơn đã trả.']);
        }

        $validated = $request->validate([
            'deposit_refund_status' => ['required', 'in:'.implode(',', Order::REFUND_STATUSES)],
            'deposit_refund_note' => ['nullable', 'string', 'max:500'],
        ]);

        $order->update([
            'deposit_refund_status' => $validated['deposit_refund_status'],
            'deposit_refund_note' => $validated['deposit_refund_note'] ?? null,
        ]);

        return back()->with('success', "Đơn {$order->code}: đã cập nhật hoàn cọc");
    }
}
