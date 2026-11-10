<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OrderPlacedMail;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\OrderPlacementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Admin tự tạo đơn hộ khách — đơn chốt qua Zalo/điện thoại/tại shop (bopcamping-yv2w).
 *
 * Luật chủ shop chốt 10/11/2026:
 *  - Cho phép ngày QUÁ KHỨ (ghi lại đơn offline đã diễn ra). Phía khách vẫn chỉ từ hôm nay.
 *  - Hết hàng → CHẶN CỨNG, kiểm tồn y như khách đặt (OrderPlacementService → StoreResolver).
 *  - Giá do hệ thống tính (bậc dài ngày, combo, nửa ngày). Không voucher/referral; admin nhập
 *    một khoản "Giảm giá (admin)" kèm lý do, ghi vào discount_breakdown với source = 'admin'.
 *  - Đơn vào thẳng 'confirmed'. Có email → gửi mail xác nhận cho khách. KHÔNG báo
 *    Telegram/mail "đơn mới" cho admin — chính admin vừa tạo.
 *
 * Form chỉ có MỘT khoảng ngày cho cả đơn → luôn ra đơn thường, không bao giờ tách cha/con.
 */
class OrderCreateController extends Controller
{
    public function __construct(
        private OrderPlacementService $placement,
        private AvailabilityService $availability,
    ) {}

    public function create(): Response
    {
        $products = Product::where('status', 'active')
            ->with('category:id,name', 'serviceLocations')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category?->name,
                'price_per_day' => (int) $p->price_per_day,
                'deposit' => (int) $p->deposit,
                'early_return_pct' => (int) $p->early_return_discount_pct,
                'location_ids' => $p->serviceLocations->where('status', 'open')->pluck('id')->values(),
            ]);

        $combos = Combo::active()
            ->whereHas('items')
            ->with('items.product:id,name', 'serviceLocations')
            ->orderBy('name')
            ->get()
            ->map(fn (Combo $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'price_per_day' => (int) $c->combo_price,
                'deposit' => (int) $c->deposit,
                'early_return_pct' => (int) $c->early_return_discount_pct,
                'location_ids' => $c->openLocationIds(),
                'items' => $c->items->map(fn ($i) => ['name' => $i->product?->name ?? '(đã xoá)', 'qty' => (int) $i->quantity])->values(),
            ]);

        return Inertia::render('Admin/Orders/Create', [
            'products' => $products,
            'combos' => $combos,
            'service_locations' => ServiceLocation::open()->ordered()->get()->map(fn ($l) => ['id' => $l->id, 'name' => $l->name]),
            'delivery_methods' => collect(Order::DELIVERY_METHOD_LABELS)
                ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
        ]);
    }

    /**
     * GET /admin/orders/create/kha-dung — số còn cho thuê của mọi sản phẩm/combo trong khoảng
     * ngày (tại cơ sở đang chọn, hoặc kho tốt nhất nếu chưa chọn). Chỉ để HIỂN THỊ cho admin;
     * lúc lưu vẫn kiểm lại ở server.
     */
    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'location_id' => ['nullable', 'integer', 'exists:service_locations,id'],
        ]);

        $start = Carbon::parse($data['start']);
        $end = Carbon::parse($data['end']);
        $location = isset($data['location_id']) ? ServiceLocation::find($data['location_id']) : null;

        $products = Product::where('status', 'active')->with('serviceLocations')->get();
        $combos = Combo::active()->whereHas('items')->get();

        return response()->json([
            'products' => (object) $this->availability->availableQuantitiesFor($products, $start, $end, $location),
            'combos' => (object) $this->availability->comboQuantitiesFor($combos, $start, $end, $location),
        ]);
    }

    /**
     * GET /admin/orders/create/khach?phone= — khách cũ theo SĐT để điền sẵn tên/email/địa chỉ.
     * Lấy từ tài khoản (nếu có) rồi bổ sung từ đơn gần nhất cùng SĐT.
     */
    public function customer(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'regex:/^0[0-9]{8,10}$/']]);

        $user = $this->customerAccount($data['phone']);
        $last = Order::where('customer_phone', $data['phone'])->where('is_parent', false)->latest()->first();

        if (! $user && ! $last) {
            return response()->json(['found' => false]);
        }

        $email = ($user && ! $user->hasPlaceholderEmail()) ? $user->email : $last?->customer_email;

        return response()->json([
            'found' => true,
            'has_account' => (bool) $user,
            'name' => $user?->name ?: $last?->customer_name,
            'email' => $email,
            'address' => $last?->customer_address,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^0[0-9]{8,10}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'delivery_method' => ['required', Rule::in(Order::DELIVERY_METHODS)],
            'service_location_id' => ['nullable', 'integer', Rule::exists('service_locations', 'id')->where('status', 'open')],
            // KHÔNG có after_or_equal:today — admin được ghi lùi đơn đã diễn ra.
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            // Buổi chỉ có nghĩa khi thuê đúng 1 ngày; OrderSplitter tự bỏ nếu nhiều ngày.
            'session' => ['nullable', 'in:morning,afternoon,full'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('status', 'active')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'combos' => ['nullable', 'array', 'max:20'],
            'combos.*.combo_id' => ['required', 'integer', 'distinct', 'exists:combos,id'],
            'combos.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'discount' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'discount_note' => [Rule::requiredIf(fn () => (int) $request->input('discount') > 0), 'nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Nhập họ tên khách.',
            'phone.required' => 'Nhập số điện thoại khách.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'end.after_or_equal' => 'Ngày trả phải từ ngày nhận trở đi.',
            'discount_note.required' => 'Ghi lý do giảm giá.',
        ]);

        $range = ['start' => $validated['start'], 'end' => $validated['end'], 'session' => $validated['session'] ?? null];
        $itemLines = array_map(fn ($l) => $l + $range, $validated['items'] ?? []);
        $comboLines = array_map(fn ($l) => $l + $range, $validated['combos'] ?? []);
        $discount = (int) ($validated['discount'] ?? 0);

        // Gắn tài khoản khách khi SĐT khớp — đơn hiện trong "Đơn của tôi" của họ.
        $account = $this->customerAccount($validated['phone']);

        $base = [
            'user_id' => $account?->id,
            'created_by' => $request->user()->id,
            'customer_name' => $validated['name'],
            'customer_phone' => $validated['phone'],
            'customer_email' => $validated['email'] ?? null,
            'customer_address' => $validated['address'] ?? null,
            'note' => $validated['note'] ?? null,
            'delivery_method' => $validated['delivery_method'],
            // Key trong $base thắng mặc định 'pending' của OrderSplitter. Tạo thẳng confirmed (sự
            // kiện created) nên OrderObserver KHÔNG bắn OrderStatusMail — mail duy nhất là
            // OrderPlacedMail bên dưới.
            'status' => 'confirmed',
        ];

        try {
            $order = DB::transaction(function () use ($base, $itemLines, $comboLines, $validated, $discount) {
                $order = $this->placement->place($base, $itemLines, $comboLines, $validated['service_location_id'] ?? null);

                if ($discount > 0) {
                    if ($discount > (int) $order->total_price) {
                        throw new RuntimeException('Giảm giá ('.number_format($discount, 0, ',', '.').'đ) lớn hơn tiền thuê ('
                            .number_format((int) $order->total_price, 0, ',', '.').'đ).');
                    }
                    $order->applyDiscountLines([[
                        'source' => 'admin',
                        'amount' => $discount,
                        'percent' => false,
                        'note' => $validated['discount_note'],
                    ]]);
                }

                return $order;
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        if ($email = $order->notifiableEmail()) {
            Mail::to($email)->send(new OrderPlacedMail($order));
        }

        return redirect()->route('admin.orders.show', $order)->with('success', "Đã tạo đơn {$order->code}");
    }

    /** Tài khoản KHÁCH có SĐT này (bỏ qua tài khoản admin). */
    private function customerAccount(string $phone): ?User
    {
        return User::where('phone', $phone)->where('is_admin', false)->first();
    }
}
