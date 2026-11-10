<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Đặt 1 đơn từ các dòng thuê lẻ + combo — phần CHUNG giữa khách tự đặt (Shop\OrderController)
 * và admin tạo hộ (Admin\OrderCreateController, bopcamping-yv2w).
 *
 * Làm đúng một việc: combo còn bán → gộp nhu cầu → chọn/kiểm cơ sở (StoreResolver, kiểm tồn
 * per-store) → combo có bán tại cơ sở đó → tách đơn + tạo món (OrderSplitter). Khuyến mãi,
 * mail, thông báo là việc của caller vì hai luồng xử lý khác nhau.
 *
 * Mọi lỗi nghiệp vụ ném RuntimeException với câu hiển thị được cho người dùng.
 */
class OrderPlacementService
{
    public function __construct(
        private StoreResolver $storeResolver,
        private OrderSplitter $splitter,
    ) {}

    /**
     * @param  array<string,mixed>  $base  field chung cho đơn (khách, user_id, note…). Key trong
     *                                     $base THẮNG mặc định của OrderSplitter (status, payment_method).
     * @param  array<int,array{product_id:int,quantity:int,start:string,end:string,session?:?string}>  $itemLines
     * @param  array<int,array{combo_id:int,quantity:int,start:string,end:string,session?:?string}>  $comboLines
     * @return Order đơn top-level (thường hoặc CHA)
     *
     * @throws RuntimeException
     */
    public function place(array $base, array $itemLines, array $comboLines, ?int $chosenLocationId): Order
    {
        if (empty($itemLines) && empty($comboLines)) {
            throw new RuntimeException('Giỏ thuê đang trống.');
        }

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
                throw new RuntimeException('Combo bạn chọn không còn cho thuê.');
            }
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

        // Chọn cơ sở: có chọn → validate đúng cơ sở đó; chưa chọn → tự gán cơ sở đủ cả giỏ.
        // StoreResolver đã kiểm tồn kho per-store (single source of truth) và tự ném lỗi.
        $resolved = $this->storeResolver->resolveForCart($needed, $productsById, $chosenLocationId);

        // bopcamping-v5ig — combo CHỈ bán ở kho được gán. StoreResolver chỉ kiểm tồn per-product
        // (combo đã bung thành sản phẩm) nên không biết ràng buộc này; thiếu check ở đây thì đặt
        // được combo ở cơ sở mà shop không bán nó.
        //
        // Đơn không gắn kho (chưa có cơ sở nào đang mở / dữ liệu cũ) → BỎ QUA, không chặn.
        if ($resolved['location'] !== null) {
            foreach ($comboLines as $line) {
                $combo = $combos->get($line['combo_id']);
                if (! in_array($resolved['location']->id, $combo->openLocationIds(), true)) {
                    throw new RuntimeException(
                        "Combo \"{$combo->name}\" không cho thuê tại {$resolved['location']->name}. "
                        .'Bạn đổi cơ sở hoặc bỏ combo này khỏi giỏ giúp nhé.'
                    );
                }
            }
        }

        $base += [
            'service_location_id' => $resolved['location']?->id,
            'location_auto_assigned' => $resolved['auto'],
        ];

        // Tách đơn theo khoảng ngày (bopcamping-wtuv): 1 khoảng → đơn thường; ≥2 → cha + con.
        return DB::transaction(fn () => $this->splitter->create($base, $itemLines, $comboLines, $productsById, $combos));
    }
}
