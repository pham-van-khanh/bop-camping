<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OrderDatesChangedMail;
use App\Mail\OrderScheduleConfirmedMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\ServiceLocation;
use App\Services\AvailabilityService;
use App\Services\RentalPricingService;
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

    public function __construct(
        private AvailabilityService $availability,
        private RentalPricingService $pricing,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->query('status', 'all');
        $q = trim((string) $request->query('q', ''));

        // Đơn cha/con (bopcamping-wtuv T6): danh sách chỉ TOP-LEVEL (đơn thường + cha, ẩn con);
        // con nạp kèm trong cha. Search theo mã đơn (cả mã con)/tên/SĐT.
        $relations = ['items.product', 'items.combo', 'vouchers', 'referralUse.referrer', 'serviceLocation', 'rentalPaidBy:id,name', 'depositPaidBy:id,name', 'depositRefundedBy:id,name', 'deliveredBy:id,name', 'collectedBy:id,name'];
        $query = Order::topLevel()->with(array_merge($relations, array_map(fn ($r) => "children.$r", $relations)))->latest();

        if ($q !== '') {
            $query->where(fn ($w) => $w
                ->where('code', 'like', "%{$q}%")
                ->orWhere('customer_name', 'like', "%{$q}%")
                ->orWhere('customer_phone', 'like', "%{$q}%")
                ->orWhereHas('children', fn ($c) => $c->where('code', 'like', "%{$q}%")));
        }

        $mapOrder = fn ($o) => $this->mapOrder($o);

        // Cha: trạng thái hiển thị suy từ con; kèm mảng children (map cùng shape để FE tái dùng UI).
        $orders = $query->get()->map(function ($o) use ($mapOrder) {
            $row = $mapOrder($o);
            if ($o->is_parent) {
                $row['status'] = $o->aggregateStatus();
                $row['children'] = $o->children->map($mapOrder)->values();
            }

            return $row;
        });

        // Thống kê theo TOP-LEVEL (đơn cha đếm 1, trạng thái suy từ con — không đếm trùng con).
        $byStatus = $orders->countBy('status');
        $stats = [
            'total' => $orders->count(),
            'pending' => $byStatus['pending'] ?? 0,
            'confirmed' => $byStatus['confirmed'] ?? 0,
            'renting' => $byStatus['renting'] ?? 0,
            'returned' => $byStatus['returned'] ?? 0,
            'cancelled' => $byStatus['cancelled'] ?? 0,
        ];

        // Lọc trạng thái SAU khi suy trạng thái cha (in-collection — list không phân trang).
        if ($status !== 'all') {
            $orders = $orders->filter(fn ($row) => $row['status'] === $status)->values();
        }

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
            'filters' => ['status' => $status, 'q' => $q],
            // Trần % giảm giá tối đa/đơn — preview đổi lịch dùng để kẹp giống server (bopcamping-lmk6).
            'max_discount_percent' => (float) PromotionSetting::current()->max_discount_percent_per_order,
        ]);
    }

    /**
     * Màn hình riêng cho 1 đơn (spec 2026-07-26): gom toàn bộ chi tiết + action.
     * Đơn cha kèm children; đơn con kèm link cha. Dùng chung mapOrder với danh sách.
     */
    public function show(Order $order): Response
    {
        $relations = ['items.product', 'items.combo', 'vouchers', 'referralUse.referrer', 'serviceLocation', 'rentalPaidBy:id,name', 'depositPaidBy:id,name', 'depositRefundedBy:id,name', 'deliveredBy:id,name', 'collectedBy:id,name'];
        $order->load(array_merge($relations, array_map(fn ($r) => "children.$r", $relations), ['parent:id,code']));

        $row = $this->mapOrder($order);
        if ($order->is_parent) {
            $row['status'] = $order->aggregateStatus();
            $row['children'] = $order->children->map(fn ($c) => $this->mapOrder($c))->values();
        }
        if ($order->parent_id && $order->parent) {
            $row['parent'] = ['id' => $order->parent->id, 'code' => $order->parent->code];
        }

        return Inertia::render('Admin/Orders/Show', [
            'order' => $row,
            'service_locations' => ServiceLocation::open()->ordered()->get()->map(fn ($l) => ['id' => $l->id, 'name' => $l->name]),
            'max_discount_percent' => (float) PromotionSetting::current()->max_discount_percent_per_order,
        ]);
    }

    /**
     * Chuẩn hoá 1 đơn → mảng cho FE (dùng chung index + show). Đơn cha bổ sung status/children ở caller.
     *
     * @return array<string,mixed>
     */
    private function mapOrder(Order $o): array
    {
        return [
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
            // Nửa ngày (adr_pricing_models) — đơn cùng ngày trả sớm; admin thấy đơn trả trưa.
            'is_half_day' => (bool) $o->is_half_day,
            // Buổi khách chọn (spec 2026-07-26): morning|afternoon|full|null.
            'session' => $o->session,
            // Giờ nhận/trả mong muốn + phụ phí ngoài khung giờ (Phase 2 turnaround, bopcamping-h4to).
            'requested_pickup_time' => $o->requested_pickup_time,
            'requested_return_time' => $o->requested_return_time,
            // Giờ giao/thu admin ĐÃ CHỐT + ghi chú nội bộ cho shipper (bopcamping-641t).
            'confirmed_pickup_time' => $o->confirmed_pickup_time,
            'confirmed_return_time' => $o->confirmed_return_time,
            'schedule_note' => $o->schedule_note,
            'schedule_confirmed_at' => $o->schedule_confirmed_at?->format('d/m H:i'),
            'extra_fee' => (int) $o->extra_fee,
            'extra_fee_note' => $o->extra_fee_note,
            'total_price' => $o->total_price,
            'deposit_total' => $o->deposit_total,
            'discount_total' => $o->discount_total,
            // bopcamping-3ag: nguồn giảm từng dòng (voucher/referral/email_bonus/cap); đơn cũ null
            'discount_breakdown' => $o->discount_breakdown,
            'amount_due' => $o->amount_due,
            'status' => $o->status,
            'payment_status' => $o->payment_status,
            // Thu tiền theo 2 khoản độc lập (bopcamping-q7i0)
            'rental_due' => $o->rental_due,
            'rental_paid' => $o->rentalPaid(),
            'rental_paid_at' => $o->rental_paid_at?->format('d/m H:i'),
            'rental_paid_by' => $o->rentalPaidBy?->name,
            'deposit_paid' => $o->depositPaid(),
            'deposit_paid_at' => $o->deposit_paid_at?->format('d/m H:i'),
            'deposit_paid_by' => $o->depositPaidBy?->name,
            // Ai đã làm gì trên đơn: 5 mốc kèm người + giờ (bopcamping-3wfk)
            'actions' => $o->actionLog(),
            'deposit_refund_status' => $o->deposit_refund_status,
            'deposit_refund_note' => $o->deposit_refund_note,
            'note' => $o->note,
            'created_at' => $o->created_at->format('d/m/Y H:i'),
            // Per-store: cửa hàng thuê + đơn hệ thống tự gán (admin review theo địa chỉ)
            'service_location' => $o->serviceLocation ? ['id' => $o->serviceLocation->id, 'name' => $o->serviceLocation->name] : null,
            // Hình thức GIAO khách chọn (bopcamping-z3ug) — 'ship' thì nhớ báo phí và nhập vào Phí phát sinh.
            'delivery_method' => $o->delivery_method,
            'delivery_method_label' => $o->deliveryMethodLabel(),
            'location_auto_assigned' => (bool) $o->location_auto_assigned,
            'items' => $o->items->map(fn ($i) => [
                'name' => $i->product?->name ?? '(đã xoá)',
                'quantity' => $i->quantity,
                'price_per_day' => (int) $i->price_per_day,
                'days' => $i->days,
                'subtotal' => $i->subtotal,
                // % giảm thuê dài ngày đã snapshot (bopcamping-e36e) — admin thấy đã giảm bao nhiêu.
                'duration_discount_percent' => (float) $i->duration_discount_percent,
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
            'is_parent' => (bool) $o->is_parent,
        ];
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

        // Đơn gộp: cả cụm dùng CHUNG 1 cơ sở — kiểm tồn từng CON theo khoảng của nó,
        // đủ hết mới đổi cha + toàn bộ con (bopcamping-wtuv T7).
        $targets = $order->is_parent
            ? $order->children()->where('status', '!=', 'cancelled')->get()
            : collect([$order]);

        foreach ($targets as $target) {
            if ($err = $this->locationShortage($target, $location)) {
                return back()->withErrors(['location' => $err]);
            }
        }

        DB::transaction(function () use ($order, $location) {
            $order->update(['service_location_id' => $location->id, 'location_auto_assigned' => false]);
            if ($order->is_parent) {
                $order->children()->update(['service_location_id' => $location->id, 'location_auto_assigned' => false]);
            }
        });

        return back()->with('success', "Đơn {$order->code} → cơ sở {$location->name}");
    }

    /**
     * Kiểm store đích có đủ MỌI món của đơn trong khoảng ngày của đơn không.
     * Loại chính đơn khỏi "đã đặt" (không tự chặn mình). Trả message lỗi hoặc null nếu đủ.
     */
    private function locationShortage(Order $order, ServiceLocation $location): ?string
    {
        $order->loadMissing('items.product.serviceLocations');

        $needed = [];
        foreach ($order->items as $item) {
            if ($item->product) {
                $needed[$item->product_id] = ($needed[$item->product_id] ?? 0) + $item->quantity;
            }
        }

        foreach ($needed as $productId => $qty) {
            $product = $order->items->firstWhere('product_id', $productId)->product;
            $avail = $this->availability->availableQuantity($product, $order->start_date, $order->end_date, $location, $order->id);
            if ($avail < $qty) {
                return "\"{$product->name}\" tại {$location->name} không đủ ({$avail}/{$qty}) cho khoảng {$order->code}.";
            }
        }

        return null;
    }

    /**
     * Đổi lịch thuê của đơn (bopcamping-5hjm) — chỉ đơn CHƯA giao (pending/confirmed).
     * Kiểm tồn kho khoảng mới tại store của đơn (AvailabilityService — single source),
     * tính lại tiền thuê tuyến tính theo số ngày (cọc + giảm giá GIỮ NGUYÊN),
     * re-arm email nhắc nhận đồ và gửi mail báo khách lịch mới.
     */
    public function changeDates(Request $request, Order $order): RedirectResponse
    {
        // Đơn cha không có món — đổi lịch trên TỪNG CON (mỗi con 1 khoảng ngày riêng).
        if ($order->is_parent) {
            return back()->withErrors(['dates' => 'Đơn gộp: đổi lịch trên từng đợt (đơn con).']);
        }
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

        // Loại chính đơn này khỏi "đã đặt" (không tự chặn mình, không over-credit khi đơn khác chiếm hàng).
        foreach ($needed as $productId => $qty) {
            $product = $order->items->firstWhere('product_id', $productId)->product;
            $avail = $this->availability->availableQuantity($product, $start, $end, $order->serviceLocation, $order->id);
            if ($avail < $qty) {
                return back()->withErrors(['dates' => "\"{$product->name}\" không đủ hàng ({$avail}/{$qty}) cho khoảng mới."]);
            }
        }

        $oldStart = $order->start_date->copy();
        $oldEnd = $order->end_date->copy();
        $oldDays = $order->days;
        $newDays = (int) $start->diffInDays($end) + 1;

        DB::transaction(function () use ($order, $start, $end, $oldStart, $oldDays, $newDays) {
            // Tính lại giá theo số ngày MỚI qua RentalPricingService (nguồn chân lý) — bậc giảm
            // dài ngày được tái xác định theo newDays (KHÔNG scale tuyến tính, vì % bậc đổi theo
            // ngày). Combo dùng allocated_price (đã gồm qty); lẻ dùng price_per_day × qty.
            $newTotal = 0;
            foreach ($order->items as $item) {
                $line = $item->combo_id
                    ? $this->pricing->priceLine((int) $item->allocated_price, 1, $newDays)
                    : $this->pricing->priceLine((int) $item->price_per_day, (int) $item->quantity, $newDays);
                $item->update([
                    'days' => $newDays,
                    // Đổi lịch cấp ĐƠN → mọi món về cùng khoảng mới (bopcamping-u1nb).
                    'start_date' => $start,
                    'end_date' => $end,
                    'subtotal' => $line['net'],
                    'duration_discount_percent' => $line['percent'],
                ]);
                $newTotal += $line['net'];
            }

            // Giảm giá theo tổng thuê mới: dòng % scale theo ngày, dòng tiền cố định giữ nguyên,
            // rồi áp LẠI trần % giá trị đơn (van an toàn) trên tổng mới — không vượt tổng, không âm.
            [$discountTotal, $breakdown] = $this->rescaleDiscount($order, $oldDays, $newDays, $newTotal);

            $order->update([
                'start_date' => $start,
                'end_date' => $end,
                'total_price' => $newTotal,
                'discount_total' => $discountTotal,
                'discount_breakdown' => $breakdown,
                // Ngày nhận đổi → email "Còn 1 ngày nữa!" phải gửi lại theo ngày mới.
                'pickup_reminder_sent_at' => $start->equalTo($oldStart) ? $order->pickup_reminder_sent_at : null,
            ]);
        });

        // Con của đơn gộp đổi lịch → tổng/envelope/voucher của CHA phải tính lại + phân bổ lại
        // (voucher % scale theo tổng mới, cap tái áp) — bopcamping-wtuv T7.
        if ($order->parent_id) {
            $this->recomputeParentAfterChildChange($order->parent()->first());
        }

        if ($email = $order->notifiableEmail()) {
            Mail::to($email)->send(new OrderDatesChangedMail($order->fresh()->loadMissing('items.product'), $oldStart, $oldEnd));
        }

        return back()->with('success', "Đơn {$order->code}: đã đổi lịch thuê");
    }

    /**
     * Tính lại giảm giá khi đổi số ngày thuê (bopcamping-lmk6).
     * Dòng breakdown có cờ `percent = true` (voucher %, referral/email bonus %) scale theo tỉ lệ
     * ngày; dòng tiền cố định giữ nguyên. Dòng `cap` cũ bị bỏ và TÍNH LẠI trên tổng mới. Cuối cùng
     * áp trần % giá trị đơn (van an toàn — giống lúc checkout) → giảm không bao giờ vượt tổng thuê
     * hay vượt trần, không bao giờ âm. Đơn cũ không có breakdown → kẹp discount_total theo trần + tổng.
     *
     * @return array{0:int, 1:array<int,array<string,mixed>>|null}
     */
    private function rescaleDiscount(Order $order, int $oldDays, int $newDays, int $newTotal): array
    {
        $maxPercent = (float) PromotionSetting::current()->max_discount_percent_per_order;
        $cap = (int) floor($newTotal * $maxPercent / 100);

        $breakdown = $order->discount_breakdown;
        if (empty($breakdown)) {
            // Đơn cũ (trước bopcamping-3ag): vẫn kẹp để không vượt tổng mới / trần.
            return [max(0, min((int) $order->discount_total, $cap, $newTotal)), $breakdown];
        }

        // Bỏ dòng cap cũ (tính lại trên tổng mới); scale dòng %, giữ dòng cố định.
        $lines = [];
        foreach ($breakdown as $line) {
            if (($line['source'] ?? null) === 'cap') {
                continue;
            }
            if (! empty($line['percent'])) {
                $line['amount'] = (int) round((int) $line['amount'] * $newDays / max(1, $oldDays));
            }
            $lines[] = $line;
        }

        $preCap = (int) array_sum(array_column($lines, 'amount'));
        $clamped = max(0, min($preCap, $cap, $newTotal));
        if ($clamped !== $preCap) {
            // Dòng điều chỉnh ÂM để sum(breakdown) === discount_total (bopcamping-3ag).
            $lines[] = ['source' => 'cap', 'amount' => $clamped - $preCap, 'percent' => true];
        }

        return [$clamped, array_values($lines)];
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::VALID_STATUSES)],
        ]);

        $was = $order->status;
        $new = $validated['status'];

        // Đơn cha chỉ gom đợt — vòng đời giao/thu nằm ở TỪNG CON (bopcamping-wtuv T7).
        // Trên cha chỉ cho phép HUỶ CẢ CỤM; các trạng thái khác thao tác trên con.
        if ($order->is_parent && $new !== 'cancelled') {
            return back()->withErrors(['status' => 'Đơn gộp chỉ có thể Huỷ cả cụm — đổi trạng thái trên từng đợt (đơn con).']);
        }

        $order->update(['status' => $new]);

        // Ghi dấu ai bấm đã giao / đã thu (bopcamping-3wfk) — giữ dấu ĐẦU TIÊN, đổi trạng
        // thái qua lại không xoá được dấu của người làm thật.
        if ($new === 'renting') {
            $order->stampAction('delivered', $request->user()->id);
        } elseif ($new === 'returned') {
            $order->stampAction('collected', $request->user()->id);
        }

        // Đơn cha/con (bopcamping-wtuv T4): huỷ cha → huỷ hết con; huỷ/khôi phục 1 con →
        // tính lại voucher + phân bổ trên các con CÒN active.
        if ($order->is_parent && $new === 'cancelled') {
            $order->children()->update(['status' => 'cancelled']);
        } elseif ($order->parent_id && ($new === 'cancelled' || $was === 'cancelled')) {
            $this->recomputeParentAfterChildChange($order->parent);
        }

        return back()->with('success', "Đơn {$order->code} → {$new}");
    }

    /**
     * Sau khi 1 con huỷ/khôi phục (bopcamping-wtuv T4): tính lại tổng + voucher của cha trên
     * các con CÒN active rồi phân bổ lại. Voucher % scale theo tổng mới, tiền cố định giữ,
     * áp lại trần % (van an toàn). Bất biến: Σ discount con active === discount cha.
     */
    private function recomputeParentAfterChildChange(?Order $parent): void
    {
        if (! $parent) {
            return;
        }
        $parent->loadMissing('children');
        $active = $parent->children->reject(fn (Order $c) => $c->status === 'cancelled')->values();
        $newTotal = (int) $active->sum('total_price');
        $oldTotal = (int) $parent->total_price;

        $maxPercent = (float) PromotionSetting::current()->max_discount_percent_per_order;
        $cap = (int) floor($newTotal * $maxPercent / 100);

        // Tính lại discount cha: bỏ dòng cap cũ, scale dòng % theo tổng mới, giữ dòng cố định, kẹp.
        $breakdown = $parent->discount_breakdown ?? [];
        $lines = [];
        foreach ($breakdown as $line) {
            if (($line['source'] ?? null) === 'cap') {
                continue;
            }
            if (! empty($line['percent'])) {
                $line['amount'] = $oldTotal > 0 ? (int) round((int) $line['amount'] * $newTotal / $oldTotal) : 0;
            }
            $lines[] = $line;
        }
        $preCap = (int) array_sum(array_column($lines, 'amount'));
        $discountTotal = max(0, min($preCap, $cap, $newTotal));
        if ($lines && $discountTotal !== $preCap) {
            $lines[] = ['source' => 'cap', 'amount' => $discountTotal - $preCap, 'percent' => true];
        }

        $parent->update([
            'total_price' => $newTotal,
            'deposit_total' => (int) $active->sum('deposit_total'),
            'discount_total' => $discountTotal,
            'discount_breakdown' => $lines ?: null,
            // Envelope ngày của cha bám theo các con còn active (đổi lịch con / huỷ con).
            ...($active->isNotEmpty() ? [
                'start_date' => $active->min('start_date'),
                'end_date' => $active->max('end_date'),
            ] : []),
        ]);

        // Phân bổ lại xuống con active ∝ tiền thuê (nguồn chung ở Order model); con huỷ → 0.
        $parent->allocateDiscountToChildren($active);
        foreach ($parent->children->where('status', 'cancelled') as $c) {
            $c->update(['discount_total' => 0, 'discount_breakdown' => null]);
        }
    }

    /**
     * Đánh dấu ĐÃ/CHƯA thu 1 trong 2 khoản: tiền thuê hoặc cọc (bopcamping-q7i0).
     * Hai khoản độc lập — khách có thể chuyển tiền thuê trước, cọc trả khi nhận đồ.
     * Ghi luôn ai đánh dấu để đối soát; payment_status suy ra trong Order::markPaid().
     *
     * KHÁC bản cũ: đơn ĐÃ TRẢ vẫn cho đánh dấu, vì tiền thuê có thể mới thu lúc thu đồ.
     */
    public function updatePayment(Request $request, Order $order): RedirectResponse
    {
        if ($order->is_parent) {
            return back()->withErrors(['payment' => 'Đơn gộp: đánh dấu thu tiền trên từng đợt (đơn con).']);
        }
        if ($order->status === 'cancelled') {
            return back()->withErrors(['payment' => 'Đơn đã huỷ — không đánh dấu thu tiền.']);
        }

        $validated = $request->validate([
            'kind' => ['required', 'in:'.implode(',', Order::PAYMENT_KINDS)],
            'paid' => ['required', 'boolean'],
        ]);

        $order->markPaid($validated['kind'], (bool) $validated['paid'], $request->user()->id);

        $label = $validated['kind'] === 'rental' ? 'tiền thuê' : 'tiền cọc';

        return back()->with('success', "Đơn {$order->code}: đã cập nhật {$label}");
    }

    /**
     * Đánh dấu hoàn cọc khi đơn ĐÃ TRẢ (bopcamping-7be): refunded = đã hoàn ·
     * pending = chưa hoàn; kèm lý do trừ/không hoàn đủ cọc (rách lều, hư hại…).
     */
    public function updateRefund(Request $request, Order $order): RedirectResponse
    {
        if ($order->is_parent) {
            return back()->withErrors(['deposit_refund_status' => 'Đơn gộp: hoàn cọc trên từng đợt (đơn con).']);
        }
        if ($order->status !== 'returned') {
            return back()->withErrors(['deposit_refund_status' => 'Chỉ hoàn cọc cho đơn đã trả.']);
        }

        $validated = $request->validate([
            'deposit_refund_status' => ['required', 'in:'.implode(',', Order::REFUND_STATUSES)],
            'deposit_refund_note' => ['nullable', 'string', 'max:500'],
        ]);

        // Qua markRefunded để LUÔN có dấu ai hoàn cọc, lúc nào (bopcamping-3wfk).
        $order->markRefunded(
            $validated['deposit_refund_status'] === 'refunded',
            $request->user()->id,
            $validated['deposit_refund_note'] ?? null,
        );

        return back()->with('success', "Đơn {$order->code}: đã cập nhật hoàn cọc");
    }

    /**
     * Phụ phí giao/trả NGOÀI KHUNG GIỜ (bopcamping-h4to, Phase 2) — admin nhập tay sau khi
     * liên hệ khách (giao sớm/trả muộn). Cộng vào amount_due; không dùng biểu phí tự động.
     */
    public function updateExtraFee(Request $request, Order $order): RedirectResponse
    {
        if ($order->is_parent) {
            return back()->withErrors(['extra_fee' => 'Đơn gộp: nhập phụ phí trên từng đợt (đơn con).']);
        }

        $validated = $request->validate([
            'extra_fee' => ['required', 'integer', 'min:0', 'max:100000000'],
            'extra_fee_note' => ['nullable', 'string', 'max:255'],
        ], [
            'extra_fee.integer' => 'Phụ phí phải là số.',
        ]);

        $order->update([
            'extra_fee' => $validated['extra_fee'],
            'extra_fee_note' => $validated['extra_fee_note'] ?? null,
        ]);

        return back()->with('success', "Đơn {$order->code}: đã cập nhật phụ phí");
    }

    /**
     * Chốt/sửa giờ giao + giờ thu do SHOP quyết định, kèm ghi chú nội bộ cho shipper
     * (bopcamping-641t, prd_delivery_schedule). KHÔNG ghi đè `requested_*` (giờ khách xin) —
     * hai cột tách biệt để đối chiếu. Chỉ đơn con/đơn thường, chưa trả/chưa huỷ mới chốt được.
     * Xoá trắng cả hai giờ = huỷ chốt (set null). Gửi mail xác nhận CHỈ khi giờ đổi thật.
     */
    public function updateSchedule(Request $request, Order $order): RedirectResponse
    {
        if ($order->is_parent) {
            return back()->withErrors(['confirmed_pickup_time' => 'Đơn gộp: chốt giờ trên từng đợt (đơn con).']);
        }
        if (in_array($order->status, ['returned', 'cancelled'], true)) {
            return back()->withErrors(['confirmed_pickup_time' => 'Đơn đã trả/đã huỷ — không chốt giờ nữa.']);
        }

        $validated = $request->validate([
            'confirmed_pickup_time' => ['nullable', 'date_format:H:i'],
            'confirmed_return_time' => ['nullable', 'date_format:H:i'],
            'schedule_note' => ['nullable', 'string', 'max:255'],
        ]);

        $pickup = $validated['confirmed_pickup_time'] ?? null;
        $return = $validated['confirmed_return_time'] ?? null;

        // Đơn cùng ngày (trả sớm/nửa ngày) → giờ thu phải sau giờ giao (so sánh chuỗi HH:MM là đủ).
        if ($order->start_date->isSameDay($order->end_date) && $pickup && $return && $return <= $pickup) {
            return back()->withErrors(['confirmed_return_time' => 'Giờ thu phải sau giờ giao (đơn trong cùng ngày).']);
        }

        $changed = $pickup !== $order->confirmed_pickup_time || $return !== $order->confirmed_return_time;

        $order->update([
            'confirmed_pickup_time' => $pickup,
            'confirmed_return_time' => $return,
            'schedule_note' => $validated['schedule_note'] ?? null,
            'schedule_confirmed_at' => ($pickup || $return) ? now() : null,
        ]);

        // Xoá trắng cả hai giờ = huỷ chốt → KHÔNG gửi mail "đã chốt giờ" rỗng nghĩa;
        // admin gọi khách như trước khi có giờ. Chỉ báo khi còn ít nhất 1 giờ.
        if ($changed && ($pickup || $return) && ($email = $order->notifiableEmail())) {
            Mail::to($email)->send(new OrderScheduleConfirmedMail($order->fresh()->loadMissing('items.product')));
        }

        return back()->with('success', "Đơn {$order->code}: đã cập nhật giờ giao/thu");
    }
}
