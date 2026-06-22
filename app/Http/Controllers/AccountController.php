<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
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

        // (3) Mã giới thiệu + voucher còn hiệu lực.
        $vouchers = $user->vouchers()->usable()->latest()->get()->map(fn ($v) => [
            'code' => $v->code,
            'amount' => $v->amount,
            'source' => $v->source,
        ]);

        return Inertia::render('Account', [
            'stats' => [
                'completedProductCount' => $completedProductCount,
                'activeOrderCount' => $activeOrders->count(),
                'referralCount' => $user->referrals()->count(),
            ],
            'activeOrders' => $activeOrders,
            'referralCode' => $user->ensureReferralCode(),
            'vouchers' => $vouchers,
        ]);
    }
}
