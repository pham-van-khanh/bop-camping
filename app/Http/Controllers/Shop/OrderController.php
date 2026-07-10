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
use App\Services\ComboPricingService;
use App\Services\Promotion\EmailBonusService;
use App\Services\Promotion\VoucherService;
use App\Services\Referral\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        private AvailabilityService $availability,
        private ComboPricingService $comboPricing,
        private ReferralService $referrals,
        private VoucherService $vouchers,
        private EmailBonusService $emailBonus,
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
            'combos' => ['nullable', 'array'],
            'combos.*.combo_id' => ['required', 'integer', 'exists:combos,id'],
            'combos.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'combos.*.start' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'combos.*.end' => ['required', 'date_format:Y-m-d', 'after_or_equal:combos.*.start'],
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

        // Giỏ chỉ 1 vị trí: mọi món (kể cả món trong combo) phải thuê được ở cùng ≥1 vị trí đang mở.
        // (FE đã chặn bằng popup; đây là van an toàn vì giỏ nằm ở localStorage.)
        $allProducts = $products->values()
            ->concat($combos->flatMap(fn (Combo $c) => $c->items->map(fn ($i) => $i->product)->filter()));
        $locationSets = $allProducts
            ->map(fn (Product $p) => $p->serviceLocations->where('status', 'open')->pluck('id')->all())
            ->filter(fn (array $ids) => ! empty($ids))
            ->values()
            ->all();
        if (count($locationSets) > 1 && empty(array_intersect(...$locationSets))) {
            return back()->withErrors([
                'items' => 'Các thiết bị trong giỏ không cùng một vị trí phục vụ. Mỗi đơn chỉ thuê tại một vị trí.',
            ])->withInput();
        }

        // Kiểm kho: GỘP tổng nhu cầu per (sản phẩm + khoảng ngày) từ cả thuê lẻ lẫn
        // combo đã bung — chống overbook khi cùng 1 sản phẩm xuất hiện ở cả hai phần.
        // Mọi phép tính đều qua AvailabilityService (single source of truth).
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

        $comboProducts = $combos->flatMap(fn (Combo $c) => $c->items->map(fn ($i) => $i->product))
            ->filter()
            ->keyBy('id');

        // Kiểm kho sơ bộ (chưa khoá) để báo lỗi thân thiện trước khi mở transaction.
        // Recheck authoritative dưới lockForUpdate nằm TRONG transaction bên dưới.
        if ($shortfalls = $this->availabilityErrors($needed, $products, $comboProducts)) {
            return back()->withErrors(['items' => implode(' ', $shortfalls)])->withInput();
        }

        // Tạo đơn trong transaction
        $order = DB::transaction(function () use ($validated, $itemLines, $comboLines, $products, $combos, $needed, $comboProducts) {
            // Van an toàn chống race/oversell: khoá các dòng sản phẩm liên quan rồi
            // kiểm kho LẠI (authoritative) — 2 đơn cùng giành nốt hàng cuối thì đơn
            // thứ hai bị chặn ở đây và rollback. lockForUpdate là no-op trên SQLite.
            $lockIds = collect(array_keys($needed))
                ->map(fn ($k) => (int) explode('|', $k)[0])
                ->unique()->sort()->values()->all();
            Product::whereIn('id', $lockIds)->lockForUpdate()->get();

            if ($shortfalls = $this->availabilityErrors($needed, $products, $comboProducts)) {
                throw ValidationException::withMessages(['items' => implode(' ', $shortfalls)]);
            }

            $starts = array_merge(array_column($itemLines, 'start'), array_column($comboLines, 'start'));
            $ends = array_merge(array_column($itemLines, 'end'), array_column($comboLines, 'end'));
            $startDate = Carbon::parse(min($starts));
            $endDate = Carbon::parse(max($ends));

            // Email gửi xác nhận: ưu tiên email khách nhập ở checkout (khách vãng lai cũng
            // nhận được mail); bỏ trống thì lấy từ tài khoản đăng nhập (bỏ email tạm .local).
            $customerEmail = $validated['email'] ?? null;
            if (! $customerEmail) {
                $user = Auth::user();
                $customerEmail = ($user && ! str_ends_with($user->email, '@bopcamping.local')) ? $user->email : null;
            }

            $order = Order::create([
                'user_id' => Auth::id(),
                'customer_name' => $validated['name'],
                'customer_phone' => $validated['phone'],
                'customer_email' => $customerEmail,
                'customer_address' => $validated['address'] ?? null,
                'note' => $validated['note'] ?? null,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'pending',
                'payment_method' => 'cod',
            ]);

            $totalPrice = 0;
            $depositTotal = 0;

            foreach ($itemLines as $item) {
                $product = $products->get($item['product_id']);
                $days = Carbon::parse($item['start'])->diffInDays(Carbon::parse($item['end'])) + 1;
                $subtotal = $product->price_per_day * $item['quantity'] * $days;

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price_per_day' => $product->price_per_day,
                    'days' => $days,
                    'subtotal' => $subtotal,
                ]);

                $totalPrice += $subtotal;
                $depositTotal += ($product->deposit ?? 0) * $item['quantity'];
            }

            // Bung combo thành order_items per-product (PRD combo 5.3). Mỗi combo-instance
            // = 1 combo_group_uuid riêng (1 đơn có thể chứa 2 combo giống nhau); giá/cọc
            // phân bổ snapshot qua ComboPricingService — tổng khớp từng đồng (AC-3).
            foreach ($comboLines as $line) {
                $combo = $combos->get($line['combo_id']);
                $days = Carbon::parse($line['start'])->diffInDays(Carbon::parse($line['end'])) + 1;
                $allocation = $this->comboPricing->allocate($combo);

                for ($instance = 0; $instance < $line['quantity']; $instance++) {
                    $groupUuid = (string) Str::uuid();

                    foreach ($allocation as $alloc) {
                        $order->items()->create([
                            'product_id' => $alloc['product_id'],
                            'combo_id' => $combo->id,
                            'combo_group_uuid' => $groupUuid,
                            'quantity' => $alloc['quantity'],
                            'price_per_day' => $alloc['price_per_day'], // snapshot giá lẻ để đối chiếu
                            'days' => $days,
                            'subtotal' => $alloc['allocated_price'] * $days,
                            'allocated_price' => $alloc['allocated_price'],
                            'allocated_deposit' => $alloc['allocated_deposit'],
                        ]);
                    }

                    $totalPrice += (int) $combo->combo_price * $days;
                    $depositTotal += (int) ($combo->deposit ?? 0);
                }
            }

            $order->update([
                'total_price' => $totalPrice,
                'deposit_total' => $depositTotal,
            ]);

            return $order;
        });

        // Khuyến mãi (chỉ cho khách đã đăng nhập) — referee giảm đơn đầu + voucher.
        // Bọc trong 1 transaction để 4 bước ghi giảm giá là nguyên tử: lỗi giữa chừng
        // không để lại đơn với discount_breakdown lệch discount_total.
        if ($order->user_id) {
            DB::transaction(function () use ($order, $validated) {
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
                    // Ghi dòng điều chỉnh ÂM để sum(breakdown) luôn khớp discount_total (bopcamping-3ag)
                    $order->applyDiscountLines([
                        ['source' => 'cap', 'amount' => $clamped - (int) $order->discount_total],
                    ]);
                }
            });
        }

        // Mail đều là ShouldQueue → đẩy vào queue (worker gửi nền), checkout không treo vì SMTP.
        // Mail xác nhận đặt đơn — gửi tới email khách nhập ở checkout (chưa verify, khách
        // vãng lai cũng nhận) hoặc email tài khoản; notifiableEmail() chỉ lọc email tạm .local.
        if ($email = $order->notifiableEmail()) {
            Mail::to($email)->send(new OrderPlacedMail($order));
        }

        // Báo QTV có đơn mới (tới email các tài khoản admin đã đặt email thật).
        if ($admins = User::adminNotifyEmails()) {
            Mail::to($admins)->send(new NewOrderAdminMail($order));
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
     * Kiểm kho cho mọi (sản phẩm | khoảng ngày) đã gộp — trả danh sách thông báo
     * thiếu hàng (rỗng nghĩa là đủ). Dùng cả cho pre-check lẫn recheck-dưới-lock,
     * đảm bảo cùng một nguồn công thức (AvailabilityService).
     *
     * @param  array<string, int>  $needed  "productId|start|end" => qty
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Product>  $comboProducts
     * @return array<int, string>
     */
    private function availabilityErrors(array $needed, Collection $products, Collection $comboProducts): array
    {
        $errors = [];
        foreach ($needed as $key => $qty) {
            [$productId, $start, $end] = explode('|', $key);
            $product = $products->get((int) $productId) ?? $comboProducts->get((int) $productId);
            if (! $product) {
                $errors[] = "Sản phẩm #$productId không tồn tại.";

                continue;
            }

            $available = $this->availability->availableQuantity($product, Carbon::parse($start), Carbon::parse($end));
            if ($available < $qty) {
                $errors[] = "\"{$product->name}\" chỉ còn {$available} bộ trong khoảng thời gian này.";
            }
        }

        return $errors;
    }
}
