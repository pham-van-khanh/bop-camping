<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * Số lượng còn có thể thuê của một sản phẩm trong khoảng [start, end] TẠI 1 CỬA HÀNG.
     *
     * Logic (per-store, KHÔNG cộng xuyên cửa hàng):
     *   tồn tại store − tổng qty đã đặt của đơn active chồng lịch GẮN store đó.
     * Đơn chưa gắn store (service_location_id NULL — dữ liệu cũ) tính vào mọi store cho an toàn.
     * Hai khoảng chồng nhau khi: start_A <= end_B AND start_B <= end_A.
     *
     * $location = null → hành vi TOÀN CỤC cũ (products.quantity − mọi đơn chồng lịch) cho
     * caller chưa chuyển per-store. Truyền $location → tính riêng cửa hàng đó (không cộng xuyên store).
     *
     * $excludeOrderId: loại chính đơn này khỏi phần "đã đặt" — dùng khi đổi lịch/đổi cửa hàng
     * để đơn KHÔNG tự chặn mình. Trừ NGAY trong query (trước max(0)), tránh over-credit khi
     * nhiều đơn khác đã đẩy "đã đặt" vượt tồn (cộng bù sau max(0) sẽ cho phép đặt trùng).
     */
    public function availableQuantity(Product $product, Carbon $start, Carbon $end, ?ServiceLocation $location = null, ?int $excludeOrderId = null): int
    {
        $booked = $this->bookedQuantity($product, $start, $end, $location, $excludeOrderId);
        $stock = $location === null ? (int) $product->quantity : (int) $product->stockAt($location->id);

        return max(0, $stock - $booked);
    }

    /**
     * Tổng số lượng đã đặt của sản phẩm chồng khoảng [start,end], tính theo NGÀY TỪNG MÓN
     * (bopcamping-u1nb) — đơn nhiều khoảng ngày không còn khoá tồn dư trên cả envelope.
     * Fallback ngày ĐƠN khi món chưa có ngày (dữ liệu cũ chưa backfill / một số path test).
     * DATE() chuẩn hoá datetime→ngày để so biên cùng ngày chính xác (MySQL + SQLite).
     */
    private function bookedQuantity(Product $product, Carbon $start, Carbon $end, ?ServiceLocation $location, ?int $excludeOrderId): int
    {
        // Đệm quay vòng: nới biên "đã đặt" thêm buffer ngày sau ngày trả (đồ còn giặt/phơi).
        // Theo KHO đang hỏi; nhánh toàn cục lấy max buffer các kho (adr_turnaround_buffer).
        $buffer = $location === null ? $product->maxBufferAcrossLocations() : $product->bufferAt($location->id);

        $q = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', Order::activeStatuses())
            ->where('order_items.product_id', $product->id)
            ->whereRaw('COALESCE(DATE(order_items.start_date), DATE(orders.start_date)) <= ?', [$end->toDateString()])
            ->whereRaw('COALESCE(DATE(order_items.end_date), DATE(orders.end_date)) >= ?', [$start->copy()->subDays($buffer)->toDateString()]);

        // Per-store: chỉ đếm đơn GẮN store đó (đơn NULL store — dữ liệu cũ — tính vào mọi store).
        if ($location !== null) {
            $q->where(fn ($sq) => $sq->where('orders.service_location_id', $location->id)->orWhereNull('orders.service_location_id'));
        }
        // Loại chính đơn này (đổi lịch/đổi cửa hàng — không tự chặn mình).
        if ($excludeOrderId !== null) {
            $q->where('orders.id', '!=', $excludeOrderId);
        }

        return (int) $q->sum('order_items.quantity');
    }

    /**
     * Khả dụng theo TỪNG cửa hàng phục vụ (open) — dùng cho trang sản phẩm hiện "Vinh: N / Hà Nội: M".
     *
     * @return array<int, int> [ service_location_id => available ]
     */
    public function availableByLocations(Product $product, Carbon $start, Carbon $end): array
    {
        $product->loadMissing('serviceLocations');

        return $product->serviceLocations
            ->where('status', 'open')
            ->mapWithKeys(fn (ServiceLocation $loc) => [$loc->id => $this->availableQuantity($product, $start, $end, $loc)])
            ->all();
    }

    /**
     * Kiểm tra có đủ số lượng cần thuê không (tại 1 cửa hàng).
     */
    public function isAvailable(Product $product, Carbon $start, Carbon $end, int $needed = 1, ?ServiceLocation $location = null): bool
    {
        return $this->availableQuantity($product, $start, $end, $location) >= $needed;
    }

    /**
     * Số combo còn có thể thuê trong khoảng [start, end] (PRD combo 5.1).
     *
     * KHÔNG có logic tồn kho mới — mỗi món con đi qua availableQuantity() hiện có:
     * comboAvailable = min( intdiv(available(product_i), quantity_i) ).
     * Combo chưa có món nào → 0 (không bao giờ cho thuê combo rỗng).
     */
    public function comboAvailable(Combo $combo, Carbon $start, Carbon $end, ?ServiceLocation $location = null): int
    {
        $combo->loadMissing('items.product');

        if ($combo->items->isEmpty()) {
            return 0;
        }

        if ($location !== null) {
            return (int) $combo->items->map(function (ComboItem $item) use ($start, $end, $location) {
                if (! $item->product || $item->quantity < 1) {
                    return 0;
                }

                return intdiv($this->availableQuantity($item->product, $start, $end, $location), $item->quantity);
            })->min();
        }

        // Chưa chọn kho → đi qua comboQuantitiesFor để dùng ĐÚNG MỘT công thức
        // "max qua kho của min qua món" (bopcamping-jyxi). Trước đây nhánh này lấy tồn toàn cục
        // của từng món rồi min, nên trang chi tiết combo báo cao hơn số /combos và cao hơn số
        // mà StoreResolver cho đặt.
        return $this->comboQuantitiesFor(new EloquentCollection([$combo]), $start, $end)[$combo->id] ?? 0;
    }

    /**
     * Kiểm tra có đủ số combo cần thuê không (mirror isAvailable cho product).
     */
    public function isComboAvailable(Combo $combo, Carbon $start, Carbon $end, int $needed = 1, ?ServiceLocation $location = null): bool
    {
        return $this->comboAvailable($combo, $start, $end, $location) >= $needed;
    }

    /**
     * Case 4 — các món con làm combo hết trong khoảng [start, end]:
     * món có intdiv(available, qty) < needed, kèm số còn/số cần để hiển thị.
     *
     * @return array<int, array{product: Product, available: int, required: int}>
     */
    public function comboInsufficientItems(Combo $combo, Carbon $start, Carbon $end, int $needed = 1, ?ServiceLocation $location = null): array
    {
        $combo->loadMissing('items.product');

        $insufficient = [];
        foreach ($combo->items as $item) {
            if (! $item->product || $item->quantity < 1) {
                continue;
            }
            $available = $this->availableQuantity($item->product, $start, $end, $location);
            if (intdiv($available, $item->quantity) < $needed) {
                $insufficient[] = [
                    'product' => $item->product,
                    'available' => $available,
                    'required' => $item->quantity * $needed,
                ];
            }
        }

        return $insufficient;
    }

    /**
     * Case 4 — khoảng gần nhất còn đủ combo, giữ nguyên độ dài, dịch tối đa
     * $scanDays ngày kể từ start. Null nếu không có (PRD 5.5: scan tối đa 30 ngày).
     *
     * @return array{start: string, end: string}|null
     */
    public function nextComboWindow(Combo $combo, Carbon $start, Carbon $end, int $scanDays = 30, ?ServiceLocation $location = null): ?array
    {
        for ($offset = 1; $offset <= $scanDays; $offset++) {
            $s = $start->copy()->addDays($offset);
            $e = $end->copy()->addDays($offset);
            if ($this->comboAvailable($combo, $s, $e, $location) >= 1) {
                return ['start' => $s->toDateString(), 'end' => $e->toDateString()];
            }
        }

        return null;
    }

    /**
     * Khoá cho "rổ" tồn TOÀN CỤC (sp chưa gắn kho nào đang mở — dữ liệu cũ).
     * Dùng string để không lẫn với id kho (int) trong cùng một array.
     */
    private const GLOBAL_BUCKET = '*';

    /**
     * BATCH — khả dụng của NHIỀU sản phẩm trong khoảng [start, end], CHỈ 1 QUERY (bopcamping-j91m).
     *
     * Vì sao không gộp thành một SUM: bookedQuantity() dùng đệm quay vòng RIÊNG theo từng
     * sản phẩm/kho, nên biên "đã đặt" của mỗi sp một khác. Cách làm: query một lần với cửa sổ
     * NỚI RỘNG theo đệm lớn nhất của cả tập (superset), rồi cộng trong PHP theo đệm riêng.
     *
     * KHÔNG phải nguồn chân lý thứ hai — chỉ là tối ưu I/O của availableQuantity().
     * Invariant do AvailabilityBatchTest khẳng định:
     *   by_location[locId] === availableQuantity($p, $start, $end, $loc)  cho MỌI kho đang mở
     *   best                === max( by_location )                        khi sp có kho mở
     *   best                === availableQuantity($p, $start, $end, null) khi sp KHÔNG có kho mở
     *
     * ⚠️ best CỐ Ý khác availableQuantity(..., null): nhánh null của per-product là tồn TOÀN CỤC
     * (products.quantity), còn best là "max qua các kho đang mở" — quyết định #2 trong
     * artifacts/prd_date_first_booking.md (khách chưa chọn kho thì món hiện nếu ≥1 kho còn hàng).
     *
     * @param  EloquentCollection<int, Product>  $products
     * @return array<int, array{by_location: array<int, int>, best: int}>
     */
    public function availabilityMatrix(EloquentCollection $products, Carbon $start, Carbon $end): array
    {
        if ($products->isEmpty()) {
            return [];
        }

        $products->loadMissing('serviceLocations');

        // Mỗi sp có các "rổ" cần tính: từng kho đang mở, hoặc rổ toàn cục nếu chưa gắn kho nào.
        // threshold = biên dưới của end đã trừ đệm — so sánh chuỗi 'Y-m-d' (thứ tự từ điển ≡ thứ tự thời gian).
        $buckets = [];
        $maxBuffer = 0;

        foreach ($products as $product) {
            $open = $product->serviceLocations->where('status', 'open');

            if ($open->isEmpty()) {
                $buffer = $product->maxBufferAcrossLocations();
                $buckets[$product->id][self::GLOBAL_BUCKET] = [
                    'stock' => (int) $product->quantity,
                    'threshold' => $start->copy()->subDays($buffer)->toDateString(),
                ];
                $maxBuffer = max($maxBuffer, $buffer);

                continue;
            }

            foreach ($open as $location) {
                $buffer = (int) $location->pivot->buffer_days;
                $buckets[$product->id][(int) $location->id] = [
                    'stock' => (int) $location->pivot->quantity,
                    'threshold' => $start->copy()->subDays($buffer)->toDateString(),
                ];
                $maxBuffer = max($maxBuffer, $buffer);
            }
        }

        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', Order::activeStatuses())
            ->whereIn('order_items.product_id', $products->modelKeys())
            ->whereRaw('COALESCE(DATE(order_items.start_date), DATE(orders.start_date)) <= ?', [$end->toDateString()])
            ->whereRaw('COALESCE(DATE(order_items.end_date), DATE(orders.end_date)) >= ?', [$start->copy()->subDays($maxBuffer)->toDateString()])
            ->selectRaw('order_items.product_id as pid, orders.service_location_id as loc, order_items.quantity as qty, COALESCE(DATE(order_items.end_date), DATE(orders.end_date)) as item_end')
            ->get();

        $booked = [];

        foreach ($rows as $row) {
            $pid = (int) $row->pid;
            if (! isset($buckets[$pid])) {
                continue;
            }
            $loc = $row->loc === null ? null : (int) $row->loc;
            $itemEnd = (string) $row->item_end;
            $qty = (int) $row->qty;

            foreach ($buckets[$pid] as $key => $bucket) {
                // Rổ theo kho: chỉ đếm đơn GẮN kho đó; đơn NULL kho (dữ liệu cũ) tính vào MỌI kho.
                // Rổ toàn cục: đếm mọi đơn. Khớp bookedQuantity() dòng 59-61.
                if ($key !== self::GLOBAL_BUCKET && $loc !== null && $loc !== $key) {
                    continue;
                }
                // Cửa sổ query nới theo đệm LỚN NHẤT cả tập → lọc lại theo đệm riêng của rổ này.
                if ($itemEnd < $bucket['threshold']) {
                    continue;
                }
                $booked[$pid][$key] = ($booked[$pid][$key] ?? 0) + $qty;
            }
        }

        $result = [];

        foreach ($buckets as $pid => $productBuckets) {
            $byLocation = [];
            $globalBest = null;

            foreach ($productBuckets as $key => $bucket) {
                $available = max(0, $bucket['stock'] - ($booked[$pid][$key] ?? 0));
                if ($key === self::GLOBAL_BUCKET) {
                    $globalBest = $available;

                    continue;
                }
                $byLocation[$key] = $available;
            }

            $result[$pid] = [
                'by_location' => $byLocation,
                'best' => $globalBest ?? (empty($byLocation) ? 0 : max($byLocation)),
            ];
        }

        return $result;
    }

    /**
     * BATCH tiện dụng — [product_id => available] cho listing.
     *
     * $location có → khả dụng ĐÚNG kho đó. $location null → "best": max qua các kho đang mở
     * (món hiện ra nếu ít nhất 1 kho còn hàng). Sp chưa gắn kho → nhánh toàn cục cũ.
     *
     * @param  EloquentCollection<int, Product>  $products
     * @return array<int, int>
     */
    public function availableQuantitiesFor(EloquentCollection $products, Carbon $start, Carbon $end, ?ServiceLocation $location = null): array
    {
        $matrix = $this->availabilityMatrix($products, $start, $end);

        $out = [];
        foreach ($matrix as $pid => $row) {
            $out[$pid] = $this->pickFromRow($row, $location?->id);
        }

        return $out;
    }

    /**
     * Chọn con số phù hợp từ một dòng matrix. Một chỗ duy nhất giữ luật "hỏi kho nào thì lấy gì",
     * để wrapper sản phẩm và wrapper combo không lệch nhau.
     *
     * @param  array{by_location: array<int, int>, best: int}  $row
     */
    private function pickFromRow(array $row, ?int $locationId): int
    {
        if ($locationId === null) {
            return $row['best'];
        }

        // Sp không phục vụ ở kho đang hỏi → 0 (khớp stockAt() trả 0).
        // Sp thuộc nhánh toàn cục (by_location rỗng) giữ số toàn cục để không mất hàng dữ liệu cũ.
        return $row['by_location'][$locationId]
            ?? (empty($row['by_location']) ? $row['best'] : 0);
    }

    /**
     * BATCH combo — [combo_id => available] chỉ với 1 query cho TOÀN BỘ món con của mọi combo.
     *
     * Công thức giữ NGUYÊN comboAvailable(): min( intdiv(available_i, quantity_i) ), combo rỗng → 0.
     * Chỉ khác ở chỗ lấy available từ availabilityMatrix() thay vì gọi availableQuantity() từng món
     * (trang /combos trước đây là N combo × M món = N×M query).
     *
     * $location === null → quét kho ĐƯỢC GÁN của combo (Combo::serviceLocations(), đang mở), KHÔNG
     * còn suy ra từ kho của món con (bopcamping-zdeh). Combo chưa gán kho nào → 0, không fallback
     * toàn cục — combo không được gán kho thì không bán được ở đâu.
     *
     * @param  EloquentCollection<int, Combo>  $combos  (nên load 'items.product.serviceLocations', 'serviceLocations')
     * @return array<int, int>
     */
    public function comboQuantitiesFor(EloquentCollection $combos, Carbon $start, Carbon $end, ?ServiceLocation $location = null): array
    {
        if ($combos->isEmpty()) {
            return [];
        }

        $combos->loadMissing(['items.product.serviceLocations', 'serviceLocations']);

        /** @var EloquentCollection<int, Product> $products */
        $products = new EloquentCollection(
            $combos
                ->flatMap(fn (Combo $combo) => $combo->items->map(fn (ComboItem $item) => $item->product))
                ->filter()
                ->unique('id')
                ->values()
                ->all()
        );

        $matrix = $this->availabilityMatrix($products, $start, $end);

        $out = [];
        foreach ($combos as $combo) {
            if ($combo->items->isEmpty()) {
                $out[$combo->id] = 0;

                continue;
            }

            if ($location !== null) {
                $out[$combo->id] = $this->comboAtLocation($combo, $matrix, $location->id);

                continue;
            }

            // ⚠️ Chưa chọn kho: phải là MAX qua từng kho ĐƯỢC GÁN CỦA COMBO của (MIN qua các món),
            // KHÔNG phải MIN qua món của (MAX qua kho) — thứ tự ngược lại là sai.
            // Vd combo gồm A (Vinh 4, HN 2) + B (Vinh 2, HN 4): min-của-max cho 4, nhưng
            // best của A ở Vinh còn best của B ở Hà Nội → KHÔNG kho nào giao nổi cả combo.
            // Đúng là max(min(4,2), min(2,4)) = 2, khớp StoreResolver (đòi một kho đủ cả giỏ).
            //
            // bopcamping-zdeh (T4): tập kho quét KHÔNG còn suy ra từ by_location của món con —
            // combo giờ có kho RIÊNG (pivot combo_service_location, xem Combo::serviceLocations()).
            // Chỉ quét kho đã ĐƯỢC GÁN cho combo và đang mở; combo 0 kho gán → 0, KHÔNG fallback
            // toàn cục (một combo có thể được gán kho mà món con lại thuộc nhánh toàn cục/chưa gắn
            // kho — comboAtLocation() vẫn xử lý đúng qua pickFromRow()).
            $locationIds = $combo->openLocationIds();

            if ($locationIds === []) {
                $out[$combo->id] = 0;

                continue;
            }

            $out[$combo->id] = (int) max(array_map(
                fn (int $locId) => $this->comboAtLocation($combo, $matrix, $locId),
                $locationIds
            ));
        }

        return $out;
    }

    /**
     * Số combo giao được TẠI MỘT kho (null = nhánh toàn cục): min( intdiv(available_i, qty_i) ).
     * Giữ nguyên công thức của comboAvailable(), chỉ lấy available từ matrix.
     *
     * @param  array<int, array{by_location: array<int, int>, best: int}>  $matrix
     */
    private function comboAtLocation(Combo $combo, array $matrix, ?int $locationId): int
    {
        return (int) $combo->items->map(function (ComboItem $item) use ($matrix, $locationId) {
            if (! $item->product || $item->quantity < 1) {
                return 0;
            }
            $row = $matrix[$item->product->id] ?? null;

            return $row === null ? 0 : intdiv($this->pickFromRow($row, $locationId), $item->quantity);
        })->min();
    }

    /**
     * Kiểm tra nhiều sản phẩm cùng lúc (dùng khi validate giỏ hàng).
     *
     * @param  array<int, int>  $items  [ product_id => quantity ]
     * @return array<int, int> [ product_id => available_qty ]  (chỉ trả các sp thiếu hàng)
     */
    public function checkCart(array $items, Carbon $start, Carbon $end, ?ServiceLocation $location = null): array
    {
        $productIds = array_keys($items);

        /** @var Collection<int, Product> $products */
        $products = Product::with('serviceLocations')->whereIn('id', $productIds)->get()->keyBy('id');

        $insufficient = [];

        foreach ($items as $productId => $needed) {
            $product = $products->get($productId);
            if (! $product) {
                $insufficient[$productId] = 0;

                continue;
            }
            $available = $this->availableQuantity($product, $start, $end, $location);
            if ($available < $needed) {
                $insufficient[$productId] = $available;
            }
        }

        return $insufficient;
    }

    /**
     * Trả về tất cả ngày bị "hết hàng" (available = 0) của một sản phẩm
     * trong khoảng tháng — dùng để tô màu calendar phía FE.
     *
     * Cách tiếp cận: lấy các đơn đang hoạt động, gom lịch bận, tính từng ngày.
     * Chỉ dùng cho khoảng ngắn (≤ 90 ngày) để tránh loop lớn.
     */
    public function unavailableDates(Product $product, Carbon $from, Carbon $to, ?ServiceLocation $location = null): array
    {
        // $location = null → toàn cục cũ (products.quantity). Truyền store → tồn store đó.
        $stock = $location === null ? (int) $product->quantity : $product->stockAt($location->id);
        if ($stock === 0) {
            return [];
        }

        // Đệm quay vòng: mỗi đơn chiếm [start, end + buffer] (đồ còn giặt/phơi sau ngày trả).
        // Theo kho đang hỏi; nhánh toàn cục lấy max buffer các kho (adr_turnaround_buffer mục 3.3).
        $buffer = $location === null ? $product->maxBufferAcrossLocations() : $product->bufferAt($location->id);

        // Đơn chồng lịch — nới điều kiện end_date về trước theo buffer để bắt cả đơn mà
        // cửa sổ phơi mới chạm vào [from, to].
        $orders = Order::query()
            ->whereIn('status', Order::activeStatuses())
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from->copy()->subDays($buffer))
            ->when($location !== null, fn ($q) => $q->where(fn ($sq) => $sq->where('service_location_id', $location->id)->orWhereNull('service_location_id')))
            ->with(['items' => fn ($q) => $q->where('product_id', $product->id)])
            ->get()
            ->filter(fn ($o) => $o->items->isNotEmpty());

        $unavailable = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $booked = 0;
            foreach ($orders as $order) {
                if ($cursor->between($order->start_date, $order->end_date->copy()->addDays($buffer))) {
                    $booked += $order->items->sum('quantity');
                }
            }
            if ($booked >= $stock) {
                $unavailable[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        return $unavailable;
    }
}
