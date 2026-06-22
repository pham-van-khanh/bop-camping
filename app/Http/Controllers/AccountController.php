<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /** Các trạng thái "đang thuê / chưa hoàn thành". */
    private const ACTIVE_STATUSES = ['pending', 'confirmed', 'renting'];

    public function index(Request $request): Response
    {
        $user = Auth::user();

        // ID các đơn đối soát của khách (qua user_id HOẶC trùng SĐT — bắt cả đơn vãng lai).
        $relatedOrderIds = $user->relatedOrders()->pluck('id');

        // (1) Tổng số sản phẩm đã thuê hoàn thành (đơn returned).
        $completedOrderIds = Order::whereIn('id', $relatedOrderIds)
            ->where('status', 'returned')
            ->pluck('id');

        $completedProductCount = (int) OrderItem::whereIn('order_id', $completedOrderIds)->sum('quantity');

        // (2) Các đơn đang thuê (chưa hoàn thành).
        $activeOrders = Order::whereIn('id', $relatedOrderIds)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->with(['items' => fn ($q) => $q->with('product:id,name')])
            ->latest()
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'code' => $order->code,
                'status' => $order->status,
                'start_date' => $order->start_date->toDateString(),
                'end_date' => $order->end_date->toDateString(),
                'total_price' => (int) $order->total_price,
                'items' => $order->items->map(fn (OrderItem $item) => [
                    'name' => $item->product?->name ?? 'Sản phẩm',
                    'quantity' => $item->quantity,
                ]),
            ]);

        // (3) Voucher của khách — phân nhóm active / used / expired.
        $vouchers = $user->vouchers()->latest()->get()->map(fn (Voucher $v) => [
            'code' => $v->code,
            'type' => $v->type,
            'value' => (float) $v->value,
            'source' => $v->source,
            'bucket' => $this->voucherBucket($v),
            'expires_at' => $v->expires_at?->toDateString(),
        ]);

        return Inertia::render('Account', [
            'stats' => [
                'completedProductCount' => $completedProductCount,
                'activeOrderCount' => $activeOrders->count(),
                'referralCount' => $user->referralsMade()->where('status', 'converted')->count(),
            ],
            'activeOrders' => $activeOrders,
            'referralCode' => $user->getReferralCode(),
            'vouchers' => $vouchers,
        ]);
    }

    /** active = còn dùng được, used = đã dùng, expired = hết hạn/thu hồi. */
    private function voucherBucket(Voucher $voucher): string
    {
        if ($voucher->isUsable()) {
            return 'active';
        }

        if ($voucher->status === 'used') {
            return 'used';
        }

        return 'expired'; // expired | revoked | hết lượt
    }
}
