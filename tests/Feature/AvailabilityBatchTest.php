<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Services\AvailabilityService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * bopcamping-j91m (T1) — availabilityMatrix()/availableQuantitiesFor() là BATCH của
 * availableQuantity(), KHÔNG phải công thức overlap thứ hai.
 *
 * Invariant chính (test_batch_khop_per_product_moi_san_pham_moi_kho): với mọi sp × mọi kho mở,
 * batch phải ra ĐÚNG số mà availableQuantity() per-product ra. Nếu ai sửa buffer/overlap ở một
 * bên mà quên bên kia, test này đỏ.
 *
 * ⚠️ `best` CỐ Ý khác availableQuantity(..., null): xem docblock availabilityMatrix()
 * và quyết định #2 trong artifacts/prd_date_first_booking.md.
 */
class AvailabilityBatchTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityService $service;

    private ServiceLocation $vinh;

    private ServiceLocation $hanoi;

    private ServiceLocation $coming;

    private Carbon $start;

    private Carbon $end;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AvailabilityService;

        $this->vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        $this->hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'area' => 'Hà Nội', 'status' => 'open', 'sort_order' => 2]);
        $this->coming = ServiceLocation::create(['name' => 'Đà Nẵng', 'area' => 'Đà Nẵng', 'status' => 'coming', 'sort_order' => 3]);

        $this->start = Carbon::parse('2026-09-10');
        $this->end = Carbon::parse('2026-09-12');
    }

    /**
     * INVARIANT — batch === per-product cho mọi sp × mọi kho đang mở.
     *
     * Dựng đủ các ca biên trong CÙNG một tập: buffer khác nhau theo sp và theo kho, đơn gắn kho,
     * đơn NULL kho (dữ liệu cũ, phải tính vào mọi kho), đơn cancelled (phải bị bỏ), đơn có ngày
     * riêng từng món (bopcamping-u1nb) và đơn chỉ có ngày cấp đơn, đơn chạm biên qua đệm quay vòng.
     */
    public function test_batch_khop_per_product_moi_san_pham_moi_kho(): void
    {
        $products = $this->seedFixture();

        $matrix = $this->service->availabilityMatrix($products, $this->start, $this->end);

        foreach ($products as $product) {
            $this->assertArrayHasKey($product->id, $matrix, "thiếu sp {$product->name} trong matrix");

            foreach ([$this->vinh, $this->hanoi] as $location) {
                $expected = $this->service->availableQuantity($product, $this->start, $this->end, $location);

                $this->assertSame(
                    $expected,
                    $matrix[$product->id]['by_location'][$location->id] ?? 0,
                    "lệch ở sp={$product->name} kho={$location->name}"
                );

                // Wrapper cho listing phải khớp luôn.
                $batch = $this->service->availableQuantitiesFor($products, $this->start, $this->end, $location);
                $this->assertSame($expected, $batch[$product->id], "wrapper lệch: sp={$product->name} kho={$location->name}");
            }
        }
    }

    /**
     * GIÁ TRỊ TUYỆT ĐỐI — chốt cứng số cho fixture.
     *
     * Vì sao cần cả test này bên cạnh invariant: invariant chỉ bắt batch LỆCH per-product. Nếu ai
     * sửa sai cả hai nhánh giống nhau (vd bỏ lọc Order::activeStatuses() ở cả hai chỗ) thì chúng
     * vẫn khớp nhau và invariant vẫn xanh. Test này neo vào số thật nên bắt được.
     *
     * Tồn/đệm và các đơn xem seedFixture(). Cửa sổ hỏi: 2026-09-10 → 2026-09-12.
     */
    public function test_gia_tri_tuyet_doi_cua_fixture(): void
    {
        $products = $this->seedFixture();
        $matrix = $this->service->availabilityMatrix($products, $this->start, $this->end);

        $expected = [
            // sp            => [Vinh, Hà Nội, best]
            'Lều 2 người' => [3, 3, 3],   // Vinh 6−(2 gắn Vinh + 1 NULL kho)=3; HN 4−1=3. Đơn CANCELLED qty 5 phải bị BỎ.
            'Đệm hơi' => [0, 5, 5],       // Vinh 5−(4+3)→kẹp 0 (đệm 3 kéo đơn trả 09-08 vào tầm); HN đệm 0 nên không đơn nào chạm ⇒ 5.
            'Bếp gas mini' => [1, 2, 2],  // Vinh 3−2=1 (dùng ngày MÓN 09-11, không phải ngày ĐƠN 09-20); HN đơn gắn Vinh nên bỏ ⇒ 2.
            'Đèn lều' => [6, 1, 6],       // Vinh 7−1=6 (ngày món hết 09-08 = đúng biên đệm 2); HN đệm 0 nên ngoài tầm ⇒ 1.
        ];

        foreach ($expected as $name => [$atVinh, $atHanoi, $best]) {
            $product = $products->firstWhere('name', $name);
            $this->assertNotNull($product, "fixture thiếu sp {$name}");

            $this->assertSame($atVinh, $matrix[$product->id]['by_location'][$this->vinh->id], "Vinh lệch ở {$name}");
            $this->assertSame($atHanoi, $matrix[$product->id]['by_location'][$this->hanoi->id], "Hà Nội lệch ở {$name}");
            $this->assertSame($best, $matrix[$product->id]['best'], "best lệch ở {$name}");
        }
    }

    /** Kho status='coming' (chưa mở) KHÔNG được xuất hiện trong by_location (khớp availableByLocations()). */
    public function test_khong_tinh_kho_chua_mo(): void
    {
        $products = $this->seedFixture();

        $matrix = $this->service->availabilityMatrix($products, $this->start, $this->end);

        foreach ($products as $product) {
            $this->assertArrayNotHasKey(
                $this->coming->id,
                $matrix[$product->id]['by_location'],
                "sp {$product->name} không được có kho chưa mở trong by_location"
            );
        }
    }

    /** best = max qua các kho đang mở (quyết định #2: món hiện nếu ≥1 kho còn hàng). */
    public function test_best_la_max_qua_cac_kho_dang_mo(): void
    {
        $products = $this->seedFixture();

        $matrix = $this->service->availabilityMatrix($products, $this->start, $this->end);

        foreach ($products as $product) {
            $perLocation = [
                $this->service->availableQuantity($product, $this->start, $this->end, $this->vinh),
                $this->service->availableQuantity($product, $this->start, $this->end, $this->hanoi),
            ];

            $this->assertSame(max($perLocation), $matrix[$product->id]['best'], "best lệch ở sp={$product->name}");
        }
    }

    /** Sp chưa gắn kho nào (dữ liệu cũ) → fallback nhánh TOÀN CỤC products.quantity. */
    public function test_san_pham_khong_gan_kho_dung_nhanh_toan_cuc(): void
    {
        $legacy = $this->makeProduct('Bếp cũ', 5);
        $this->makeOrder($legacy, 2, '2026-09-11', '2026-09-13', 'confirmed', null);

        $products = Product::with('serviceLocations')->whereKey($legacy->id)->get();
        $matrix = $this->service->availabilityMatrix($products, $this->start, $this->end);

        $expected = $this->service->availableQuantity($legacy, $this->start, $this->end, null);

        $this->assertSame([], $matrix[$legacy->id]['by_location']);
        $this->assertSame($expected, $matrix[$legacy->id]['best']);
        $this->assertSame(3, $expected, 'tồn toàn cục 5 − đã đặt 2');

        // Hỏi theo 1 kho cụ thể: sp toàn cục vẫn giữ số toàn cục, không bị về 0.
        $byLocation = $this->service->availableQuantitiesFor($products, $this->start, $this->end, $this->vinh);
        $this->assertSame($expected, $byLocation[$legacy->id]);
    }

    /** Sp có kho mở nhưng KHÔNG phục vụ ở kho đang hỏi → 0 (khớp stockAt()). */
    public function test_san_pham_khong_phuc_vu_o_kho_dang_hoi_tra_0(): void
    {
        $product = $this->makeProduct('Ghế chỉ ở Vinh', 4);
        $product->serviceLocations()->attach($this->vinh->id, ['quantity' => 4, 'buffer_days' => 0]);

        $products = Product::with('serviceLocations')->whereKey($product->id)->get();

        $atHanoi = $this->service->availableQuantitiesFor($products, $this->start, $this->end, $this->hanoi);

        $this->assertSame(0, $atHanoi[$product->id]);
        $this->assertSame(
            $this->service->availableQuantity($product->fresh(), $this->start, $this->end, $this->hanoi),
            $atHanoi[$product->id]
        );
    }

    /** Tập rỗng → [] và KHÔNG chạy query nào. */
    public function test_tap_rong_khong_query(): void
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $this->assertSame([], $this->service->availabilityMatrix(new EloquentCollection, $this->start, $this->end));
        $this->assertSame(0, $count);
    }

    /**
     * NFR-1 — số query KHÔNG tăng theo số sản phẩm. 2 sp và 20 sp phải cùng số query.
     * Đây là lý do tồn tại của batch: /thiet-bi không phân trang.
     */
    public function test_so_query_khong_tang_theo_so_san_pham(): void
    {
        $this->assertSame(
            $this->countQueriesForMatrix(2),
            $this->countQueriesForMatrix(20),
            'batch phải là O(1) query bất kể số sản phẩm'
        );
    }

    private function countQueriesForMatrix(int $howMany): int
    {
        $category = Category::create(['name' => "C{$howMany}", 'slug' => "c{$howMany}"]);

        for ($i = 0; $i < $howMany; $i++) {
            $p = Product::create([
                'category_id' => $category->id,
                'name' => "SP {$howMany}-{$i}",
                'slug' => "sp-{$howMany}-{$i}",
                'price_per_day' => 50000,
                'quantity' => 5,
            ]);
            $p->serviceLocations()->attach($this->vinh->id, ['quantity' => 5, 'buffer_days' => 1]);
            $p->serviceLocations()->attach($this->hanoi->id, ['quantity' => 3, 'buffer_days' => 2]);
            $this->makeOrder($p, 1, '2026-09-11', '2026-09-12', 'confirmed', $this->vinh->id);
        }

        // Load sẵn quan hệ (như controller sẽ làm) để đếm đúng query của batch, không đếm eager load.
        $products = Product::with('serviceLocations')->where('category_id', $category->id)->get();

        // DB::listen không bỏ đăng ký được — mỗi lần gọi dùng biến đếm RIÊNG và chốt giá trị
        // ngay sau lượt gọi, nên listener cũ còn sống cũng không làm sai số đã trả về.
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $this->service->availabilityMatrix($products, $this->start, $this->end);

        return $count;
    }

    /**
     * Fixture chung: 4 sp phủ các ca biên, mỗi sp ở cả Vinh và Hà Nội với tồn + đệm khác nhau.
     *
     * @return EloquentCollection<int, Product>
     */
    private function seedFixture(): EloquentCollection
    {
        // Đệm 0 ở cả 2 kho — ca đơn giản nhất.
        $tent = $this->makeProduct('Lều 2 người', 10);
        $tent->serviceLocations()->attach($this->vinh->id, ['quantity' => 6, 'buffer_days' => 0]);
        $tent->serviceLocations()->attach($this->hanoi->id, ['quantity' => 4, 'buffer_days' => 0]);

        // Đệm KHÁC NHAU giữa 2 kho — bẫy chính của batch (query nới theo đệm max cả tập).
        $mattress = $this->makeProduct('Đệm hơi', 8);
        $mattress->serviceLocations()->attach($this->vinh->id, ['quantity' => 5, 'buffer_days' => 3]);
        $mattress->serviceLocations()->attach($this->hanoi->id, ['quantity' => 5, 'buffer_days' => 0]);

        // Đệm lớn nhất cả tập.
        $stove = $this->makeProduct('Bếp gas mini', 6);
        $stove->serviceLocations()->attach($this->vinh->id, ['quantity' => 3, 'buffer_days' => 5]);
        $stove->serviceLocations()->attach($this->hanoi->id, ['quantity' => 2, 'buffer_days' => 1]);

        // Không có đơn nào — phải trả nguyên tồn.
        $lamp = $this->makeProduct('Đèn lều', 7);
        $lamp->serviceLocations()->attach($this->vinh->id, ['quantity' => 7, 'buffer_days' => 2]);
        $lamp->serviceLocations()->attach($this->hanoi->id, ['quantity' => 1, 'buffer_days' => 0]);
        // Gắn vào kho CHƯA MỞ với tồn cố tình rất lớn: nếu batch quên lọc status='open' thì
        // by_location sẽ lòi ra kho này và best nhảy lên 99 ⇒ test_khong_tinh_kho_chua_mo +
        // test_gia_tri_tuyet_doi_cua_fixture đỏ ngay. Không có dòng này thì 2 test đó xanh RỖNG.
        $lamp->serviceLocations()->attach($this->coming->id, ['quantity' => 99, 'buffer_days' => 0]);

        // Chồng lịch trực tiếp, gắn kho Vinh.
        $this->makeOrder($tent, 2, '2026-09-11', '2026-09-13', 'confirmed', $this->vinh->id);
        // Đơn NULL kho (dữ liệu cũ) — phải tính vào MỌI kho.
        $this->makeOrder($tent, 1, '2026-09-09', '2026-09-10', 'renting', null);
        // Cancelled — phải bị bỏ hoàn toàn.
        $this->makeOrder($tent, 5, '2026-09-10', '2026-09-12', 'cancelled', $this->hanoi->id);

        // Trả 2026-09-08, đệm Vinh 3 ngày → còn phơi tới 09-11 ⇒ CHẶN ở Vinh, KHÔNG chặn ở Hà Nội (đệm 0).
        $this->makeOrder($mattress, 4, '2026-09-06', '2026-09-08', 'confirmed', $this->vinh->id);

        // ⚠️ CA QUYẾT ĐỊNH của batch: đơn NULL kho → áp vào CẢ HAI rổ, mà 2 rổ có đệm KHÁC NHAU
        // (Vinh 3, Hà Nội 0). Cùng một row DB phải được lọc bằng đệm riêng của từng rổ:
        // trả 09-08 ⇒ tính ở Vinh (biên 09-07), KHÔNG tính ở Hà Nội (biên 09-10).
        // Nếu batch dùng đệm max cả tập cho mọi rổ, Hà Nội sẽ bị trừ oan → invariant đỏ.
        // (Các đơn GẮN kho không phủ được ca này: rổ kho khác đã bị loại bởi check kho.)
        $this->makeOrder($mattress, 3, '2026-09-07', '2026-09-08', 'confirmed', null);

        // Ngày riêng từng món khác ngày cấp đơn (bopcamping-u1nb) — batch phải dùng ngày MÓN.
        $this->makeOrderWithItemDates($stove, 2, '2026-09-20', '2026-09-25', '2026-09-11', '2026-09-11', $this->vinh->id);

        // ⚠️ CA QUYẾT ĐỊNH thứ hai: ngày MÓN kết thúc 09-08 (trước ngưỡng đệm của Hà Nội là 09-10)
        // nhưng ngày ĐƠN kéo tới 09-25 (sau mọi ngưỡng). NULL kho nên áp vào cả 2 rổ.
        //   đúng  → Vinh (đệm 2, biên 09-08) TÍNH; Hà Nội (đệm 0, biên 09-10) KHÔNG tính ⇒ còn 1.
        //   sai   → nếu batch lấy ngày ĐƠN thay ngày MÓN thì Hà Nội bị trừ ⇒ còn 0, invariant đỏ.
        $this->makeOrderWithItemDates($lamp, 1, '2026-09-05', '2026-09-25', '2026-09-05', '2026-09-08', null);

        return Product::with('serviceLocations')
            ->whereIn('id', [$tent->id, $mattress->id, $stove->id, $lamp->id])
            ->get();
    }

    private function makeProduct(string $name, int $quantity): Product
    {
        $category = Category::firstOrCreate(['slug' => 'do-camping'], ['name' => 'Đồ camping']);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => $quantity,
        ]);
    }

    private function makeOrder(Product $product, int $quantity, string $start, string $end, string $status, ?int $locationId): Order
    {
        $order = Order::create([
            'code' => 'BOP-'.uniqid(),
            'customer_name' => 'Khách Test',
            'customer_phone' => '0900000000',
            'start_date' => $start,
            'end_date' => $end,
            'status' => $status,
            'payment_method' => 'cod',
            'service_location_id' => $locationId,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price_per_day' => $product->price_per_day,
            'days' => Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1,
            'subtotal' => $quantity * $product->price_per_day,
        ]);

        return $order;
    }

    /** Đơn có ngày riêng từng món — ngày MÓN phải thắng ngày ĐƠN khi tính chồng lịch. */
    private function makeOrderWithItemDates(
        Product $product,
        int $quantity,
        string $orderStart,
        string $orderEnd,
        string $itemStart,
        string $itemEnd,
        ?int $locationId
    ): Order {
        $order = Order::create([
            'code' => 'BOP-'.uniqid(),
            'customer_name' => 'Khách Test',
            'customer_phone' => '0900000000',
            'start_date' => $orderStart,
            'end_date' => $orderEnd,
            'status' => 'confirmed',
            'payment_method' => 'cod',
            'service_location_id' => $locationId,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price_per_day' => $product->price_per_day,
            'days' => Carbon::parse($itemStart)->diffInDays(Carbon::parse($itemEnd)) + 1,
            'subtotal' => $quantity * $product->price_per_day,
            'start_date' => $itemStart,
            'end_date' => $itemEnd,
        ]);

        return $order;
    }
}
