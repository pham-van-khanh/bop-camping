<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Mail\NewOrderAdminMail;
use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\Promotion\VoucherService;
use App\Services\Referral\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    public function __construct(
        private AvailabilityService $availability,
        private ReferralService $referrals,
        private VoucherService $vouchers,
    ) {}

    /**
     * POST /dat-hang — tạo đơn thuê từ giỏ hàng.
     *
     * Body: { name, phone, address?, note?, items: [{ product_id, quantity, start, end }] }
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^0[0-9]{8,10}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.start' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'items.*.end' => ['required', 'date_format:Y-m-d', 'after_or_equal:items.*.start'],
            'referral_code' => ['nullable', 'string', 'max:20'],
            'voucher_codes' => ['nullable', 'array', 'max:10'],
            'voucher_codes.*' => ['string', 'max:30'],
        ], [
            'name.required' => 'Vui lòng nhập họ tên.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'items.required' => 'Giỏ thuê đang trống.',
            'items.min' => 'Giỏ thuê đang trống.',
        ]);

        // Kiểm tra tồn kho từng món
        $errors = [];
        $products = Product::whereIn('id', array_column($validated['items'], 'product_id'))
            ->get()
            ->keyBy('id');

        foreach ($validated['items'] as $item) {
            $product = $products->get($item['product_id']);
            if (! $product) {
                $errors[] = "Sản phẩm #$item[product_id] không tồn tại.";

                continue;
            }

            $start = Carbon::parse($item['start']);
            $end = Carbon::parse($item['end']);
            $available = $this->availability->availableQuantity($product, $start, $end);

            if ($available < $item['quantity']) {
                $errors[] = "\"{$product->name}\" chỉ còn {$available} bộ trong khoảng thời gian này.";
            }
        }

        if (! empty($errors)) {
            return back()->withErrors(['items' => implode(' ', $errors)])->withInput();
        }

        // Tạo đơn trong transaction
        $order = DB::transaction(function () use ($validated, $products) {
            $starts = array_column($validated['items'], 'start');
            $ends = array_column($validated['items'], 'end');
            $startDate = Carbon::parse(min($starts));
            $endDate = Carbon::parse(max($ends));

            // Email gửi xác nhận: lấy từ tài khoản đã đăng nhập (bỏ email tạm .local).
            $user = Auth::user();
            $customerEmail = ($user && ! str_ends_with($user->email, '@bopcamping.local')) ? $user->email : null;

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

            foreach ($validated['items'] as $item) {
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

            $order->update([
                'total_price' => $totalPrice,
                'deposit_total' => $depositTotal,
            ]);

            return $order;
        });

        // Khuyến mãi (chỉ cho khách đã đăng nhập) — referee giảm đơn đầu + voucher.
        if ($order->user_id) {
            $settings = PromotionSetting::current();

            // (1) Mã giới thiệu cho đơn đầu của referee.
            $this->referrals->applyRefereeFirstOrderDiscount(
                $order,
                $validated['referral_code'] ?? null,
                $settings,
            );
            $order->refresh();

            // (2) Voucher (stacking + trần) áp lên phần còn lại.
            $this->vouchers->apply($order, $validated['voucher_codes'] ?? [], $settings);
            $order->refresh();

            // (3) VAN AN TOÀN tổng thể: tổng giảm không vượt trần % giá trị đơn.
            $cap = (int) floor((int) $order->total_price * (float) $settings->max_discount_percent_per_order / 100);
            $clamped = min((int) $order->discount_total, $cap, (int) $order->total_price);
            if ($clamped !== (int) $order->discount_total) {
                $order->update(['discount_total' => $clamped]);
            }
        }

        // Mail đều là ShouldQueue → đẩy vào queue (worker gửi nền), checkout không treo vì SMTP.
        // Mail xác nhận đặt đơn (chỉ khi có email thật — khách đăng nhập đã verify).
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
            'order_items' => count($validated['items']),
        ]);
    }
}
