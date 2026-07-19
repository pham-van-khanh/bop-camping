<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Mail\NewOrderAdminMail;
use App\Mail\OrderPlacedMail;
use App\Models\Combo;
use App\Models\Order;
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

        // Khuyến mãi (chỉ khách đăng nhập). Đơn cha (bopcamping-wtuv T3): áp trên TỔNG cha
        // rồi PHÂN BỔ xuống con ∝ tiền thuê; đơn thường: áp trực tiếp như cũ.
        if ($order->user_id) {
            $settings = PromotionSetting::current();
            $referralCode = $validated['referral_code'] ?? null;
            $voucherCodes = $validated['voucher_codes'] ?? [];

            if ($order->is_parent) {
                $order->loadMissing('children.items');
                // Voucher không giảm phần combo — gộp combo subtotal của các con (đơn cha không có món).
                $comboPart = (int) $order->children->flatMap(fn ($c) => $c->items)->whereNotNull('combo_id')->sum('subtotal');
                $this->applyPromotions($order, $referralCode, $voucherCodes, $settings, $comboPart);
                $this->allocateDiscountToChildren($order->fresh());
            } else {
                $this->applyPromotions($order, $referralCode, $voucherCodes, $settings);
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

    /**
     * Áp khuyến mãi lên 1 đơn (referee + email bonus + voucher) + van an toàn trần %.
     * $comboPart: phần combo không được voucher giảm (đơn cha truyền gộp từ con).
     */
    private function applyPromotions(Order $order, ?string $referralCode, array $voucherCodes, PromotionSetting $settings, ?int $comboPart = null): void
    {
        $this->referrals->applyRefereeFirstOrderDiscount($order, $referralCode, $settings);
        $order->refresh();

        $this->emailBonus->applyFirstOrderDiscount($order, $settings);
        $order->refresh();

        $this->vouchers->apply($order, $voucherCodes, $settings, $comboPart);
        $order->refresh();

        // Van an toàn: tổng giảm không vượt trần % giá trị đơn / không vượt tổng thuê.
        $cap = (int) floor((int) $order->total_price * (float) $settings->max_discount_percent_per_order / 100);
        $clamped = min((int) $order->discount_total, $cap, (int) $order->total_price);
        if ($clamped !== (int) $order->discount_total) {
            $order->applyDiscountLines([
                ['source' => 'cap', 'amount' => $clamped - (int) $order->discount_total, 'percent' => true],
            ]);
        }
    }

    /**
     * Phân bổ giảm giá của đơn CHA xuống các con ∝ tiền thuê con (bopcamping-wtuv T3).
     * Dồn phần dư vào con cuối để Σ discount con === discount cha. COD thu theo từng con.
     */
    private function allocateDiscountToChildren(Order $parent): void
    {
        $discount = (int) $parent->discount_total;
        $children = $parent->children()->get();
        $totalRental = (int) $children->sum('total_price');

        if ($children->isEmpty()) {
            return;
        }

        $allocated = 0;
        $last = $children->count() - 1;
        foreach ($children->values() as $i => $child) {
            $share = ($discount <= 0 || $totalRental <= 0)
                ? 0
                : ($i === $last ? $discount - $allocated : (int) floor($discount * (int) $child->total_price / $totalRental));
            $allocated += $share;
            $child->update([
                'discount_total' => $share,
                'discount_breakdown' => $share > 0
                    ? [['source' => 'parent_alloc', 'amount' => $share, 'percent' => true]]
                    : null,
            ]);
        }
    }
}
