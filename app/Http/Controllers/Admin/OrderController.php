<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    private const VALID_STATUSES = ['pending', 'confirmed', 'renting', 'returned', 'cancelled'];

    public function index(Request $request): Response
    {
        $status = $request->query('status', 'all');

        $query = Order::with(['items.product', 'items.combo', 'vouchers', 'referralUse.referrer'])->latest();

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
            'days' => $o->days,
            'total_price' => $o->total_price,
            'deposit_total' => $o->deposit_total,
            'discount_total' => $o->discount_total,
            // bopcamping-3ag: nguồn giảm từng dòng (voucher/referral/email_bonus/cap); đơn cũ null
            'discount_breakdown' => $o->discount_breakdown,
            'amount_due' => $o->amount_due,
            'status' => $o->status,
            'payment_status' => $o->payment_status,
            'note' => $o->note,
            'created_at' => $o->created_at->format('d/m/Y H:i'),
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
            'filters' => ['status' => $status],
        ]);
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
     */
    public function updatePayment(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'payment_status' => ['required', 'in:'.implode(',', Order::PAYMENT_STATUSES)],
        ]);

        $order->update(['payment_status' => $validated['payment_status']]);

        return back()->with('success', "Đơn {$order->code}: đã cập nhật tình trạng chuyển tiền");
    }
}
