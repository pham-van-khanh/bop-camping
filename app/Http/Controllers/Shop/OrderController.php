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
            // Mã địa chỉ sau sát nhập (bopcamping-9299) — CHỈ để thống kê, KHÔNG kiểm tồn tại.
            // Dữ liệu tỉnh/xã không có trong DB (FE gọi provinces.open-api.vn); muốn validate
            // thì phải gọi API bên thứ ba ngay lúc tạo đơn = đưa dependency vào đường tiền.
            // customer_address (thứ khách thấy và shipper dùng) mới là nguồn chân lý.
            'province_code' => ['nullable', 'integer', 'min:1'],
            'ward_code' => ['nullable', 'integer', 'min:1'],
            'street' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            // Hình thức GIAO (bopcamping-z3ug). Thiếu → 'self_pickup': phương án rẻ nhất,
            // KHÔNG im lặng rơi vào 'ship' (Nghệ An phải thuê xe ngoài, rơi nhầm là tốn tiền thật).
            'delivery_method' => ['nullable', 'in:'.implode(',', Order::DELIVERY_METHODS)],
            // max:50 — chặn giỏ khổng lồ tạo hàng loạt đơn con/1 request (CWE-770, bopcamping-wtuv).
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.start' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'items.*.end' => ['required', 'date_format:Y-m-d', 'after_or_equal:items.*.start'],
            'items.*.location_id' => ['nullable', 'integer', 'exists:service_locations,id'],
            // Buổi khách chọn khi thuê 1 ngày (spec 2026-07-26) — server tự suy giờ + is_half_day + % giảm.
            // null = thuê nhiều ngày (khung mặc định). KHÔNG nhận giờ/half_day thô từ client.
            'items.*.session' => ['nullable', 'in:morning,afternoon,full'],
            'combos' => ['nullable', 'array', 'max:20'],
            'combos.*.combo_id' => ['required', 'integer', 'exists:combos,id'],
            'combos.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'combos.*.start' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'combos.*.end' => ['required', 'date_format:Y-m-d', 'after_or_equal:combos.*.start'],
            'combos.*.location_id' => ['nullable', 'integer', 'exists:service_locations,id'],
            // Buổi cho combo (bopcamping-w7gi) — cùng luật với items.*.session. Thiếu dòng này
            // thì Laravel loại session khỏi $validated và lựa chọn của khách mất im lặng.
            'combos.*.session' => ['nullable', 'in:morning,afternoon,full'],
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
            ->with('items.product.serviceLocations', 'serviceLocations')
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

        // bopcamping-v5ig — combo CHỈ bán ở kho được gán. StoreResolver chỉ kiểm tồn per-product
        // (combo đã bung thành sản phẩm) nên không biết ràng buộc này; thiếu check ở đây thì khách
        // đặt được combo ở cơ sở mà shop không bán nó.
        //
        // Đơn không gắn kho (chưa có cơ sở nào đang mở / dữ liệu cũ) → BỎ QUA, không chặn.
        if ($resolved['location'] !== null) {
            foreach ($comboLines as $line) {
                $combo = $combos->get($line['combo_id']);
                if (! in_array($resolved['location']->id, $combo->openLocationIds(), true)) {
                    return back()->withErrors([
                        'items' => "Combo \"{$combo->name}\" không cho thuê tại {$resolved['location']->name}. "
                            .'Bạn đổi cơ sở hoặc bỏ combo này khỏi giỏ giúp nhé.',
                    ])->withInput();
                }
            }
        }

        // Email xác nhận: ưu tiên email khách nhập ở checkout; bỏ trống thì lấy email tài khoản (bỏ tạm .local).
        $customerEmail = $validated['email'] ?? null;
        $buyer = Auth::user();
        $attachEmailToBuyer = false;
        if (! $customerEmail) {
            $customerEmail = ($buyer && ! $buyer->hasPlaceholderEmail()) ? $buyer->email : null;
        } elseif ($buyer && $buyer->hasPlaceholderEmail()) {
            // Khách đăng nhập bằng SĐT (email còn là bản tạm .local) mà điền email ở đây → GẮN
            // luôn vào tài khoản (bopcamping-kuhg). Không có bước này thì họ vẫn là tài khoản
            // "không hộp thư": hết cookie là mất quyền vào, phải nhắn Zalo.
            //
            // An toàn vì người đang gõ ĐÃ đăng nhập vào chính tài khoản đó — không phải người lạ.
            // KHÔNG set email_verified_at: email này chưa qua OTP bao giờ, lần đăng nhập sau vẫn
            // phải xác thực. Trùng email của tài khoản khác thì bỏ qua (users.email là UNIQUE,
            // ghi vào sẽ vỡ ràng buộc) — đơn vẫn lưu email để gửi xác nhận.
            //
            // Chỉ ĐÁNH DẤU ở đây, ghi thật nằm trong transaction tạo đơn bên dưới: đây là thứ
            // đổi luôn danh tính đăng nhập của khách, không được phép sống sót khi đơn hỏng.
            $attachEmailToBuyer = ! User::where('email', $customerEmail)->exists();
        }

        $base = [
            'user_id' => Auth::id(),
            'service_location_id' => $resolved['location']?->id,
            'location_auto_assigned' => $resolved['auto'],
            'customer_name' => $validated['name'],
            'customer_phone' => $validated['phone'],
            'customer_email' => $customerEmail,
            'customer_address' => $validated['address'] ?? null,
            'province_code' => $validated['province_code'] ?? null,
            'ward_code' => $validated['ward_code'] ?? null,
            'street' => $validated['street'] ?? null,
            'note' => $validated['note'] ?? null,
            // Nằm trong $base nên đơn CHA và mọi đơn CON đều thừa hưởng (bopcamping-wtuv).
            'delivery_method' => $validated['delivery_method'] ?? 'self_pickup',
        ];

        // Tách đơn theo khoảng ngày (bopcamping-wtuv): 1 khoảng → đơn thường; ≥2 → cha + con.
        //
        // Gắn email vào tài khoản nằm TRONG cùng transaction: nó đổi danh tính đăng nhập của
        // khách, nên đơn hỏng (deadlock, hụt tồn kho, splitter lỗi) thì nó phải cuốn theo. Ghi
        // trước và ngoài transaction như bản cũ thì khách thấy trang lỗi mà email tài khoản đã
        // đổi vĩnh viễn — gõ nhầm một ký tự là lần sau OTP bay vào hộp thư không tồn tại, mà
        // hasPlaceholderEmail() nay false nên cũng không còn nhánh cứu hộ bắt nhập lại email.
        $order = DB::transaction(function () use ($base, $itemLines, $comboLines, $productsById, $combos, $attachEmailToBuyer, $buyer, $customerEmail) {
            if ($attachEmailToBuyer) {
                $buyer->email = $customerEmail;
                $buyer->save();
            }

            return $this->splitter->create($base, $itemLines, $comboLines, $productsById, $combos);
        });

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
                // Phân bổ discount cha xuống con ∝ tiền thuê (nguồn chung ở Order model).
                $parent = $order->fresh();
                $parent->allocateDiscountToChildren($parent->children()->get());
            } else {
                $this->applyPromotions($order, $referralCode, $voucherCodes, $settings);
            }
        }

        // Mail đều là ShouldQueue → gửi nền qua queue. Đơn gộp: 1 email cấp CHA liệt kê
        // từng đợt giao (bopcamping-wtuv T9); đơn thường: mail như cũ.
        if ($email = $order->notifiableEmail()) {
            Mail::to($email)->send(new OrderPlacedMail($order));
        }
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
}
