<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\Voucher;
use App\Services\OrderLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /** Các trạng thái "đang thuê / chưa hoàn thành". */
    private const ACTIVE_STATUSES = ['pending', 'confirmed', 'renting'];

    /** Giới hạn lịch sử đơn trả về trang tài khoản (đơn cũ hơn tra bằng mã). */
    private const ORDER_HISTORY_LIMIT = 20;

    public function __construct(private OrderLookupService $lookup) {}

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

        // (2) Đơn đã/đang thuê — mô tả đủ: combo vs thuê lẻ, tiền, ưu đãi đã áp, địa chỉ, đặt lại.
        $orders = Order::whereIn('id', $relatedOrderIds)
            ->with(['items.product.category', 'items.product.serviceLocations', 'items.combo'])
            ->latest()
            ->limit(self::ORDER_HISTORY_LIMIT)
            ->get()
            ->map(fn (Order $order) => $this->shapeOrder($order));

        $activeOrderCount = $orders->whereIn('status', self::ACTIVE_STATUSES)->count();

        // (3) Voucher của khách — phân nhóm active / used / expired.
        $vouchers = $user->vouchers()->latest()->get()->map(fn (Voucher $v) => [
            'code' => $v->code,
            'type' => $v->type,
            'value' => (float) $v->value,
            'source' => $v->source,
            'bucket' => $this->voucherBucket($v),
            'expires_at' => $v->expires_at?->toDateString(),
        ]);

        // (4) Tra cứu đơn ngay trong tài khoản (bopcamping-7w8) — cùng logic /tra-cuu.
        $lookupOrder = null;
        $lookupNotFound = false;
        if ($request->filled('code') && $request->filled('phone')) {
            $lookupOrder = $this->lookup->find((string) $request->input('code'), (string) $request->input('phone'));
            $lookupNotFound = $lookupOrder === null;
        }

        return Inertia::render('Account', [
            'stats' => [
                'completedProductCount' => $completedProductCount,
                'activeOrderCount' => $activeOrderCount,
                'referralCount' => $user->referralsMade()->where('status', 'converted')->count(),
            ],
            'orders' => $orders,
            'referralCode' => $user->getReferralCode(),
            'vouchers' => $vouchers,
            'lookup' => [
                'order' => $lookupOrder,
                'not_found' => $lookupNotFound,
                'query' => [
                    'code' => $request->input('code', ''),
                    'phone' => $request->input('phone', $user->phone ?? ''),
                ],
            ],
        ]);
    }

    /* ---------- helpers ---------- */

    private function shapeOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'code' => $order->code,
            'status' => $order->status,
            'status_label' => $this->lookup->statusLabel($order->status),
            'created_at' => $order->created_at->format('d/m/Y'),
            'start_date' => $order->start_date->format('d/m/Y'),
            'end_date' => $order->end_date->format('d/m/Y'),
            'days' => $order->days,
            'address' => $order->customer_address,
            'phone' => $order->customer_phone,
            'note' => $order->note,
            'total_price' => (int) $order->total_price,
            'deposit_total' => (int) $order->deposit_total,
            'discount_total' => (int) $order->discount_total,
            'amount_due' => (int) $order->amount_due,
            'groups' => $this->itemGroups($order),
            'discounts' => $this->discountLines($order),
            'reorder' => $this->reorderPayload($order),
            // Đánh giá: chỉ đơn ĐÃ TRẢ mới đánh giá được (sinh token on-demand cho đơn vãng lai).
            'review_token' => $order->status === 'returned' ? $order->ensureReviewToken() : null,
            'review_submitted' => $order->review_submitted_at !== null,
        ];
    }

    /**
     * Dòng hiển thị trong đơn: thuê lẻ giữ nguyên, item bung từ combo gộp lại
     * theo combo (mỗi combo_group_uuid = 1 bộ) — khách thấy "Combo X ×2" thay vì
     * từng món con rời rạc lẫn với đồ thuê lẻ.
     */
    private function itemGroups(Order $order): array
    {
        $groups = [];

        foreach ($order->items->whereNull('combo_group_uuid') as $item) {
            $groups[] = [
                'kind' => 'product',
                'name' => $item->product?->name ?? '(sản phẩm đã xoá)',
                'quantity' => (int) $item->quantity,
                'days' => (int) $item->days,
                'subtotal' => (int) $item->subtotal,
            ];
        }

        // Gộp theo combo_id: qty = số bộ (số group uuid), subtotal = tổng các món con.
        $comboItems = $order->items->whereNotNull('combo_group_uuid')->groupBy('combo_id');
        foreach ($comboItems as $items) {
            $instanceCount = $items->pluck('combo_group_uuid')->unique()->count();
            $firstGroup = $items->groupBy('combo_group_uuid')->first();

            $groups[] = [
                'kind' => 'combo',
                'name' => $items->first()->combo?->name ?? 'Combo',
                'quantity' => $instanceCount,
                'days' => (int) $items->first()->days,
                'subtotal' => (int) $items->sum('subtotal'),
                'children' => $firstGroup->map(fn (OrderItem $i) => [
                    'name' => $i->product?->name ?? '(sản phẩm đã xoá)',
                    'quantity' => (int) $i->quantity,
                ])->values()->all(),
            ];
        }

        return $groups;
    }

    /**
     * Ưu đãi đã áp cho đơn — từ discount_breakdown (bopcamping-3ag), nhãn thân thiện.
     * amount dương = được giảm; dòng 'cap' âm = điều chỉnh về mức giảm tối đa.
     */
    private function discountLines(Order $order): array
    {
        return collect($order->discount_breakdown ?? [])->map(fn (array $line) => [
            'label' => match ($line['source'] ?? '') {
                'referral' => 'Mã giới thiệu (đơn đầu)',
                'email_bonus' => 'Ưu đãi thêm email (đơn đầu)',
                'voucher' => 'Voucher'.(isset($line['code']) ? ' '.$line['code'] : ''),
                'cap' => 'Điều chỉnh mức giảm tối đa',
                default => 'Ưu đãi',
            },
            'amount' => (int) $line['amount'],
        ])->values()->all();
    }

    /**
     * Payload "Đặt lại" — dựng lại giỏ từ đơn cũ với GIÁ/VỊ TRÍ HIỆN TẠI (giỏ ở
     * localStorage là snapshot; trang giỏ sẽ tự làm tươi thêm lần nữa). Sản phẩm/combo
     * đã ẩn/xoá bị bỏ qua và đếm vào `skipped` để FE báo cho khách.
     */
    private function reorderPayload(Order $order): ?array
    {
        $productQty = []; // product_id => tổng qty thuê lẻ
        $comboUuids = []; // combo_id => set uuid (số bộ)
        foreach ($order->items as $item) {
            if ($item->combo_group_uuid !== null) {
                $comboUuids[$item->combo_id][$item->combo_group_uuid] = true;
            } else {
                $productQty[$item->product_id] = ($productQty[$item->product_id] ?? 0) + $item->quantity;
            }
        }

        $skipped = 0;

        $products = Product::active()
            ->with(['category', 'serviceLocations'])
            ->whereIn('id', array_keys($productQty))
            ->get()
            ->keyBy('id');
        $productLines = [];
        foreach ($productQty as $id => $qty) {
            $p = $products->get($id);
            if (! $p) {
                $skipped++;

                continue;
            }
            $productLines[] = [
                'id' => $p->id,
                'name' => $p->name,
                'cat' => $p->category?->slug ?? '',
                'price' => (int) $p->price_per_day,
                'deposit' => (int) ($p->deposit ?? 0),
                'qty' => (int) $qty,
                'locations' => $p->serviceLocations->where('status', 'open')
                    ->map(fn (ServiceLocation $l) => ['slug' => $l->slug, 'name' => $l->name])
                    ->values(),
            ];
        }

        $combos = Combo::active()
            ->whereHas('items')
            ->with('items.product.serviceLocations')
            ->whereIn('id', array_keys($comboUuids))
            ->get()
            ->keyBy('id');
        $comboLines = [];
        foreach ($comboUuids as $id => $uuids) {
            $c = $combos->get($id);
            if (! $c) {
                $skipped++;

                continue;
            }
            $comboLines[] = [
                'id' => $c->id,
                'name' => $c->name,
                'price' => (int) $c->combo_price,
                'deposit' => (int) ($c->deposit ?? 0),
                'qty' => count($uuids),
                'comboItems' => $c->items->map(fn ($i) => ['name' => $i->product?->name ?? '', 'qty' => (int) $i->quantity])->values(),
                'locations' => $c->commonOpenLocations(),
            ];
        }

        if (empty($productLines) && empty($comboLines)) {
            return null;
        }

        return ['products' => $productLines, 'combos' => $comboLines, 'skipped' => $skipped];
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
