<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Mail\NewOrderAdminMail;
use App\Mail\OrderPlacedMail;
use App\Models\Combo;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\OrderSplitter;
use App\Services\Promotion\EmailBonusService;
use App\Services\Promotion\VoucherService;
use App\Services\Referral\ReferralService;
use App\Services\StoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    public function __construct(
        private AvailabilityService $availability,
        private ReferralService $referrals,
        private VoucherService $vouchers,
        private EmailBonusService $emailBonus,
        private StoreResolver $storeResolver,
        private OrderSplitter $splitter,
    ) {}

    /**
     * POST /dat-hang — tạo đơn thuê từ giỏ hàng.
     *
     * Body: { name, phone, address?, note?,
     *         items:  [{ product_id, quantity, start, end }],   // thuê lẻ
     *         combos: [{ combo_id, quantity, start, end }] }    // thuê trọn bộ
     * Combo được bung thành order_items per-product cùng combo_group_uuid (PRD combo mục 4).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^0[0-9]{8,10}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.start' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'items.*.end' => ['required', 'date_format:Y-m-d', 'after_or_equal:items.*.start'],
            'items.*.location_id' => ['nullable', 'integer', 'exists:service_locations,id'],
            'combos' => ['nullable', 'array'],
            'combos.*.combo_id' => ['required', 'integer', 'exists:combos,id'],
            'combos.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'combos.*.start' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'combos.*.end' => ['required', 'date_format:Y-m-d', 'after_or_equal:combos.*.start'],
            'combos.*.location_id' => ['nullable', 'integer', 'exists:service_locations,id'],
            'referral_code' => ['nullable', 'string', 'max:20'],
            'voucher_codes' => ['nullable', 'array', 'max:10'],
            'voucher_codes.*' => ['string', 'max:30'],
        ], [
            'name.required' => 'Vui lòng nhập họ tên.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'email.email' => 'Email không hợp lệ.',
        ]);

        $itemLines = $validated['items'] ?? [];
        $comboLines = $validated['combos'] ?? [];

        if (empty($itemLines) && empty($comboLines)) {
            return back()->withErrors(['items' => 'Giỏ thuê đang trống.'])->withInput();
        }

        $errors = [];
        $products = Product::whereIn('id', array_column($itemLines, 'product_id'))
            ->with('serviceLocations')
            ->get()
            ->keyBy('id');

        // Combo phải đang bán và còn món; nạp sẵn product + vị trí cho check bên dưới.
        $combos = Combo::active()
            ->whereHas('items')
            ->with('items.product.serviceLocations')
            ->whereIn('id', array_column($comboLines, 'combo_id'))
            ->get()
            ->keyBy('id');
        foreach ($comboLines as $line) {
            if (! $combos->has($line['combo_id'])) {
                $errors[] = 'Combo bạn chọn không còn cho thuê.';
            }
        }
        if (! empty($errors)) {
            return back()->withErrors(['items' => implode(' ', $errors)])->withInput();
        }

        // Per-store: GỘP nhu cầu per (sản phẩm + khoảng ngày) từ thuê lẻ + combo bung
        // (chống overbook khi 1 sản phẩm ở cả hai phần).
        $needed = []; // "productId|start|end" => qty
        foreach ($itemLines as $item) {
            $key = "{$item['product_id']}|{$item['start']}|{$item['end']}";
            $needed[$key] = ($needed[$key] ?? 0) + $item['quantity'];
        }
        foreach ($comboLines as $line) {
            $combo = $combos->get($line['combo_id']);
            foreach ($combo->items as $comboItem) {
                $key = "{$comboItem->product_id}|{$line['start']}|{$line['end']}";
                $needed[$key] = ($needed[$key] ?? 0) + $comboItem->quantity * $line['quantity'];
            }
        }

        $productsById = $products->values()
            ->concat($combos->flatMap(fn (Combo $c) => $c->items->map(fn ($i) => $i->product))->filter())
            ->keyBy('id');

        // Cửa hàng khách chọn (per-store): id đầu tiên khác null; 2 store khác nhau → lỗi (giỏ 1 cơ sở).
        $chosenIds = collect($itemLines)->concat($comboLines)
            ->pluck('location_id')->filter()->unique()->values();
        if ($chosenIds->count() > 1) {
            return back()->withErrors(['items' => 'Giỏ đang chọn 2 cơ sở khác nhau. Mỗi đơn chỉ thuê tại một cơ sở.'])->withInput();
        }

        // Resolve store: khách chọn → validate store đó; chưa chọn → tự gán store đủ cả giỏ.
        // StoreResolver đã kiểm tồn kho per-store (single source of truth).
        try {
            $resolved = $this->storeResolver->resolveForCart($needed, $productsById, $chosenIds->first());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        // Email xác nhận: ưu tiên email khách nhập ở checkout; bỏ trống thì lấy email tài khoản (bỏ tạm .local).
        $customerEmail = $validated['email'] ?? null;
        if (! $customerEmail) {
            $user = Auth::user();
            $customerEmail = ($user && ! str_ends_with($user->email, '@bopcamping.local')) ? $user->email : null;
        }

        $base = [
            'user_id' => Auth::id(),
            'service_location_id' => $resolved['location']?->id,
            'location_auto_assigned' => $resolved['auto'],
            'customer_name' => $validated['name'],
            'customer_phone' => $validated['phone'],
            'customer_email' => $customerEmail,
            'customer_address' => $validated['address'] ?? null,
            'note' => $validated['note'] ?? null,
        ];

        // Tách đơn theo khoảng ngày (bopcamping-wtuv): 1 khoảng → đơn thường; ≥2 → cha + con.
        $order = DB::transaction(fn () => $this->splitter->create($base, $itemLines, $comboLines, $productsById, $combos));

        // Khuyến mãi — chỉ cho khách đăng nhập VÀ đơn thường (đơn cha: voucher xử lý ở T3 bopcamping-wtuv).
        if ($order->user_id && ! $order->is_parent) {
            $settings = PromotionSetting::current();

            // (1) Mã giới thiệu cho đơn đầu của referee.
            $this->referrals->applyRefereeFirstOrderDiscount(
                $order,
                $validated['referral_code'] ?? null,
                $settings,
            );
            $order->refresh();

            // (1.5) Ưu đãi thêm email cho đơn đầu (khuyến khích khách bổ sung email — email không bắt buộc).
            $this->emailBonus->applyFirstOrderDiscount($order, $settings);
            $order->refresh();

            // (2) Voucher (stacking + trần) áp lên phần còn lại.
            $this->vouchers->apply($order, $validated['voucher_codes'] ?? [], $settings);
            $order->refresh();

            // (3) VAN AN TOÀN tổng thể: tổng giảm không vượt trần % giá trị đơn.
            $cap = (int) floor((int) $order->total_price * (float) $settings->max_discount_percent_per_order / 100);
            $clamped = min((int) $order->discount_total, $cap, (int) $order->total_price);
            if ($clamped !== (int) $order->discount_total) {
                // Ghi dòng điều chỉnh ÂM để sum(breakdown) luôn khớp discount_total (bopcamping-3ag).
                // percent=true: trần là % giá trị đơn → scale theo ngày khi đổi lịch (bopcamping-lmk6).
                $order->applyDiscountLines([
                    ['source' => 'cap', 'amount' => $clamped - (int) $order->discount_total, 'percent' => true],
                ]);
            }
        }

        // Mail đều là ShouldQueue → gửi nền qua queue. Đơn cha không có món → gửi theo từng CON
        // (mỗi con là 1 đơn hợp lệ có món). Gộp 1 mail cấp cha là bopcamping-wtuv T9.
        $mailables = $order->is_parent ? $order->children()->get()->all() : [$order];
        foreach ($mailables as $mailOrder) {
            if ($email = $mailOrder->notifiableEmail()) {
                Mail::to($email)->send(new OrderPlacedMail($mailOrder));
            }
            if ($admins = User::adminNotifyEmails()) {
                Mail::to($admins)->send(new NewOrderAdminMail($mailOrder));
            }
        }

        return back()->with([
            'order_code' => $order->code,
            'order_name' => $validated['name'],
            'order_phone' => $validated['phone'],
            'order_pay' => $order->total_price + $order->deposit_total - $order->discount_total,
            'order_discount' => $order->discount_total,
            'order_items' => count($itemLines) + count($comboLines),
        ]);
    }
}
