<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * bopcamping-aqkr (T2) — /thiet-bi và /combos nhận ?start=&end= và trả available + in_range.
 *
 * Chốt cả FR-4: ngày bẩn thì BỎ QUA filter, KHÔNG 422 — trang không vỡ vì URL bẩn.
 * Và NFR-1: số query không tăng theo số sản phẩm (listing không phân trang).
 *
 * Test collation-safe: tên sản phẩm không có từ khoá dạng có-dấu trùng nhau nên LIKE của
 * MySQL utf8mb4_unicode_ci và của sqlite cho cùng kết quả.
 */
class ListingDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private ServiceLocation $vinh;

    private ServiceLocation $hanoi;

    private Category $category;

    /** Khoảng hỏi mặc định — luôn ở tương lai để không bị FR-4 loại vì "ngày quá khứ". */
    private string $start;

    private string $end;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        $this->hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'area' => 'Hà Nội', 'status' => 'open', 'sort_order' => 2]);
        $this->category = Category::create(['name' => 'Thiet bi', 'slug' => 'thiet-bi-test']);

        $this->start = Carbon::today()->addDays(10)->toDateString();
        $this->end = Carbon::today()->addDays(12)->toDateString();
    }

    public function test_listing_tra_available_va_in_range_theo_khoang_ngay(): void
    {
        $free = $this->makeProduct('Leu rong', 3);
        $booked = $this->makeProduct('Leu het', 2);
        // Đặt hết 2/2 ở cả hai kho: đơn NULL kho nên tính vào mọi kho.
        $this->makeOrder($booked, 2, $this->start, $this->end, 'confirmed', null);

        $this->get("/thiet-bi?start={$this->start}&end={$this->end}")
            ->assertOk()
            ->assertInertia(function ($page) use ($free, $booked) {
                $byId = collect($page->toArray()['props']['products'])->keyBy('id');

                $this->assertSame(3, $byId[$free->id]['available']);
                $this->assertTrue($byId[$free->id]['in_range']);

                $this->assertSame(0, $byId[$booked->id]['available']);
                $this->assertFalse($byId[$booked->id]['in_range']);
            });
    }

    /** Chưa chọn ngày → available/in_range = null để FE phân biệt "chưa lọc" với "hết hàng". */
    public function test_khong_co_ngay_thi_available_la_null(): void
    {
        $this->makeProduct('Leu thuong', 3);

        $this->get('/thiet-bi')
            ->assertOk()
            ->assertInertia(function ($page) {
                $props = $page->toArray()['props'];
                $this->assertNull($props['products'][0]['available']);
                $this->assertNull($props['products'][0]['in_range']);
                $this->assertNull($props['range_summary']);
                $this->assertSame('', $props['filters']['start']);
            });
    }

    /** Món đặt được xếp TRƯỚC món hết hàng, thứ tự phụ theo ?sort= vẫn giữ (sort stable). */
    public function test_mon_dat_duoc_xep_truoc_mon_het_hang(): void
    {
        // Theo giá tăng dần: het-re (10k) → con-giua (20k) → het-dat (30k).
        $cheapSoldOut = $this->makeProduct('Ghe het re', 1, 10000);
        $midAvailable = $this->makeProduct('Ghe con giua', 5, 20000);
        $pricyAvailable = $this->makeProduct('Ghe con dat', 5, 30000);

        $this->makeOrder($cheapSoldOut, 1, $this->start, $this->end, 'confirmed', null);

        $this->get("/thiet-bi?sort=low&start={$this->start}&end={$this->end}")
            ->assertOk()
            ->assertInertia(function ($page) use ($cheapSoldOut, $midAvailable, $pricyAvailable) {
                $ids = collect($page->toArray()['props']['products'])->pluck('id')->all();

                // Hai món còn hàng lên trước, GIỮ thứ tự giá tăng dần giữa chúng; món hết xuống cuối.
                $this->assertSame([$midAvailable->id, $pricyAvailable->id, $cheapSoldOut->id], $ids);
            });
    }

    public function test_range_summary_dem_so_mon_het_hang(): void
    {
        $this->makeProduct('Bep con', 4);
        $out1 = $this->makeProduct('Bep het mot', 1);
        $out2 = $this->makeProduct('Bep het hai', 1);
        $this->makeOrder($out1, 1, $this->start, $this->end, 'confirmed', null);
        $this->makeOrder($out2, 1, $this->start, $this->end, 'confirmed', null);

        $this->get("/thiet-bi?start={$this->start}&end={$this->end}")
            ->assertOk()
            ->assertInertia(function ($page) {
                $summary = $page->toArray()['props']['range_summary'];
                $this->assertSame(3, $summary['days'], 'ngày thuê tính cả đầu và cuối');
                $this->assertSame(2, $summary['unavailable_count']);
            });
    }

    /** ?vi-tri= + ngày → tính theo ĐÚNG kho đó, không phải max toàn hệ thống. */
    public function test_vi_tri_ket_hop_ngay_tinh_dung_kho(): void
    {
        $product = $this->makeProduct('Tui ngu', 10);
        $product->serviceLocations()->attach($this->vinh->id, ['quantity' => 5, 'buffer_days' => 0]);
        $product->serviceLocations()->attach($this->hanoi->id, ['quantity' => 2, 'buffer_days' => 0]);

        // Đơn gắn Vinh: chỉ trừ tồn Vinh.
        $this->makeOrder($product, 4, $this->start, $this->end, 'confirmed', $this->vinh->id);

        $this->get("/thiet-bi?vi-tri=vinh&start={$this->start}&end={$this->end}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('products.0.available', 1));

        $this->get("/thiet-bi?vi-tri=ha-noi&start={$this->start}&end={$this->end}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('products.0.available', 2));

        // Không chọn kho → max qua các kho đang mở (quyết định #2): max(1, 2) = 2.
        $this->get("/thiet-bi?start={$this->start}&end={$this->end}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('products.0.available', 2));
    }

    /**
     * FR-4 — mọi dạng ngày bẩn đều BỎ QUA filter, trả 200 và props ngày rỗng.
     *
     * @dataProvider ngayBanProvider
     */
    public function test_ngay_ban_bi_bo_qua_khong_loi(string $query, string $lyDo): void
    {
        $this->makeProduct('Den trai', 2);

        $this->get('/thiet-bi?'.$query)
            ->assertOk()
            ->assertInertia(function ($page) use ($lyDo) {
                $props = $page->toArray()['props'];
                $this->assertSame('', $props['filters']['start'], $lyDo);
                $this->assertSame('', $props['filters']['end'], $lyDo);
                $this->assertNull($props['products'][0]['available'], $lyDo);
                $this->assertNull($props['range_summary'], $lyDo);
            });
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function ngayBanProvider(): array
    {
        $future = Carbon::today()->addDays(5)->toDateString();
        $past = Carbon::today()->subDays(5)->toDateString();
        $tooFar = Carbon::today()->addDays(60)->toDateString();

        return [
            'thiếu end' => ["start={$future}", 'chỉ có start thì không đủ khoảng'],
            'thiếu start' => ["end={$future}", 'chỉ có end thì không đủ khoảng'],
            'sai format' => ['start=12/08/2026&end=14/08/2026', 'phải đúng Y-m-d'],
            'end trước start' => ['start=2030-08-14&end=2030-08-12', 'khoảng đảo ngược'],
            'start quá khứ' => ["start={$past}&end={$future}", 'không cho lọc ngày đã qua'],
            'ngày không tồn tại' => ['start=2030-02-30&end=2030-03-05', '30/02 không có thật, Carbon::parse sẽ tràn'],
            'quá 30 ngày' => ["start={$future}&end={$tooFar}", 'vượt MAX_RENTAL_DAYS'],
            'rỗng' => ['start=&end=', 'chuỗi rỗng'],
            'chữ' => ['start=abc&end=xyz', 'không phải ngày'],
        ];
    }

    /** Đúng 30 ngày vẫn hợp lệ — biên trên phải inclusive. */
    public function test_dung_30_ngay_van_hop_le(): void
    {
        $this->makeProduct('Ba lo', 2);
        $start = Carbon::today()->addDays(3);
        $end = $start->copy()->addDays(29); // 3 → 32 = đúng 30 ngày thuê

        $this->get("/thiet-bi?start={$start->toDateString()}&end={$end->toDateString()}")
            ->assertOk()
            ->assertInertia(function ($page) use ($start) {
                $props = $page->toArray()['props'];
                $this->assertSame($start->toDateString(), $props['filters']['start']);
                $this->assertSame(30, $props['range_summary']['days']);
            });
    }

    /** Thuê 1 ngày (start === end) hợp lệ — khớp early_return_discount_pct. */
    public function test_thue_mot_ngay_hop_le(): void
    {
        $this->makeProduct('Ghe xep', 2);
        $day = Carbon::today()->addDays(4)->toDateString();

        $this->get("/thiet-bi?start={$day}&end={$day}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.start', $day)
                ->where('range_summary.days', 1));
    }

    /** NFR-1 — 3 sp và 24 sp phải cùng số query. Nếu ai đưa availableQuantity() vào vòng lặp, đỏ. */
    public function test_so_query_khong_tang_theo_so_san_pham(): void
    {
        $this->assertSame(
            $this->countQueriesOnListing(3),
            $this->countQueriesOnListing(24),
            '/thiet-bi phải là O(1) query theo số sản phẩm (listing không phân trang)'
        );
    }

    private function countQueriesOnListing(int $howMany): int
    {
        // Cô lập bằng danh mục riêng + ?cat= thay vì Product::delete() (vướng khoá ngoại order_items).
        $category = Category::create(['name' => "Nhom {$howMany}", 'slug' => "nhom-{$howMany}"]);

        for ($i = 0; $i < $howMany; $i++) {
            $p = $this->makeProduct("Mon so {$howMany} {$i}", 5, category: $category);
            $p->serviceLocations()->attach($this->vinh->id, ['quantity' => 5, 'buffer_days' => 1]);
            $p->serviceLocations()->attach($this->hanoi->id, ['quantity' => 3, 'buffer_days' => 2]);
            $this->makeOrder($p, 1, $this->start, $this->end, 'confirmed', $this->vinh->id);
        }

        return $this->countQueriesOn("/thiet-bi?cat={$category->slug}&start={$this->start}&end={$this->end}");
    }

    /**
     * Đếm query của 1 URL. Gọi warm-up trước rồi mới đo: request ĐẦU TIÊN của cả test suite
     * còn tạo lazy hàng singleton (site_settings) nên nếu đo luôn sẽ lệch 1 query một cách
     * giả tạo, không liên quan gì tới số sản phẩm.
     */
    private function countQueriesOn(string $url): int
    {
        $this->get($url)->assertOk();

        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $this->get($url)->assertOk();

        // Chốt giá trị ngay: DB::listen không bỏ đăng ký được nên listener cũ sẽ còn sống,
        // nhưng nó chỉ tăng biến của lượt trước — biến đó đã được trả về theo giá trị.
        return $count;
    }

    // ---------- /combos ----------

    public function test_combos_tra_in_range_theo_min_cua_mon_con(): void
    {
        $tent = $this->makeProduct('Leu combo', 5);
        $bottleneck = $this->makeProduct('Dem combo', 2);

        $ok = $this->makeCombo('Combo con hang', 'combo-con-hang', [[$tent, 1]]);
        $out = $this->makeCombo('Combo het hang', 'combo-het-hang', [[$tent, 1], [$bottleneck, 2]]);

        // Chặn đúng món bottleneck → combo chứa nó về 0, combo kia vẫn còn.
        $this->makeOrder($bottleneck, 2, $this->start, $this->end, 'confirmed', null);

        $this->get("/combos?start={$this->start}&end={$this->end}")
            ->assertOk()
            ->assertInertia(function ($page) use ($ok, $out) {
                $byId = collect($page->toArray()['props']['combos'])->keyBy('id');

                $this->assertSame(5, $byId[$ok->id]['available']);
                $this->assertTrue($byId[$ok->id]['in_range']);

                $this->assertSame(0, $byId[$out->id]['available']);
                $this->assertFalse($byId[$out->id]['in_range']);
            });
    }

    /** Combo đặt được xếp trước, và ngày bẩn cũng bị bỏ qua ở /combos (cùng trait). */
    public function test_combos_sap_xep_va_bo_qua_ngay_ban(): void
    {
        $plenty = $this->makeProduct('Bep combo', 9);
        $none = $this->makeProduct('Loi combo', 1);
        // sort_order để combo HẾT hàng đứng trước khi chưa lọc ngày.
        $out = $this->makeCombo('Combo A het', 'combo-a-het', [[$none, 1]], sortOrder: 1);
        $ok = $this->makeCombo('Combo B con', 'combo-b-con', [[$plenty, 1]], sortOrder: 2);
        $this->makeOrder($none, 1, $this->start, $this->end, 'confirmed', null);

        // Có ngày → combo còn hàng lên trước dù sort_order lớn hơn.
        $this->get("/combos?start={$this->start}&end={$this->end}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('combos.0.id', $ok->id)->where('combos.1.id', $out->id));

        // Ngày quá khứ → bỏ filter, trở lại thứ tự sort_order, available null.
        $past = Carbon::today()->subDays(3)->toDateString();
        $this->get("/combos?start={$past}&end={$this->end}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('combos.0.id', $out->id)
                ->where('combos.0.available', null)
                ->where('filters.start', '')
                ->where('range_summary', null));
    }

    /** NFR-1 cho /combos — trước đây là N combo × M món query. */
    public function test_combos_so_query_khong_tang_theo_so_combo(): void
    {
        $this->assertSame(
            $this->countQueriesOnCombos(2),
            $this->countQueriesOnCombos(12),
            '/combos phải là O(1) query theo số combo'
        );
    }

    private function countQueriesOnCombos(int $howMany): int
    {
        DB::table('combo_service_location')->delete();
        ComboItem::query()->delete();
        Combo::query()->delete();
        Product::query()->delete();

        for ($i = 0; $i < $howMany; $i++) {
            $a = $this->makeProduct("Combo mon a {$howMany} {$i}", 6);
            $b = $this->makeProduct("Combo mon b {$howMany} {$i}", 6);
            $combo = $this->makeCombo("Combo so {$howMany} {$i}", "combo-so-{$howMany}-{$i}", [[$a, 1], [$b, 2]]);

            // BẮT BUỘC: combo phải có kho được gán như combo thật do admin tạo. Không có pivot thì
            // shape() short-circuit ở `count(locations) > 0` và giấu mất N+1 của all_locations.
            $combo->serviceLocations()->attach([$this->vinh->id, $this->hanoi->id]);
        }

        return $this->countQueriesOn("/combos?start={$this->start}&end={$this->end}");
    }

    /**
     * all_locations vẫn đúng khi số kho mở được TRUYỀN VÀO shape() thay vì đếm tại chỗ:
     * đủ kho mở → true, thiếu 1 kho → false, không kho nào → false.
     * Kèm luôn chốt cho /combos/{slug} vì show() tự đếm (không có sẵn danh sách như index()).
     */
    public function test_all_locations_dung_theo_so_kho_dang_mo(): void
    {
        $product = $this->makeProduct('Leu all loc', 4);
        $full = $this->makeCombo('Combo du kho', 'combo-du-kho', [[$product, 1]], sortOrder: 1);
        $partial = $this->makeCombo('Combo mot kho', 'combo-mot-kho', [[$product, 1]], sortOrder: 2);
        $none = $this->makeCombo('Combo khong kho', 'combo-khong-kho', [[$product, 1]], sortOrder: 3);

        $full->serviceLocations()->attach([$this->vinh->id, $this->hanoi->id]);
        $partial->serviceLocations()->attach($this->vinh->id);

        $this->get('/combos')
            ->assertOk()
            ->assertInertia(function ($page) use ($full, $partial, $none) {
                $byId = collect($page->toArray()['props']['combos'])->keyBy('id');

                $this->assertTrue($byId[$full->id]['all_locations']);
                $this->assertFalse($byId[$partial->id]['all_locations']);
                $this->assertFalse($byId[$none->id]['all_locations'], 'không kho nào thì không phải "mọi kho"');
            });

        $this->get('/combos/combo-du-kho')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('combo.all_locations', true));

        $this->get('/combos/combo-mot-kho')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('combo.all_locations', false));
    }

    /** Kho chưa mở (coming) KHÔNG tính vào mẫu số: gán đủ kho đang mở là all_locations = true. */
    public function test_all_locations_bo_qua_kho_chua_mo(): void
    {
        ServiceLocation::create(['name' => 'Da Nang', 'area' => 'Da Nang', 'status' => 'coming', 'sort_order' => 3]);

        $product = $this->makeProduct('Bep all loc', 4);
        $combo = $this->makeCombo('Combo du kho mo', 'combo-du-kho-mo', [[$product, 1]]);
        $combo->serviceLocations()->attach([$this->vinh->id, $this->hanoi->id]);

        $this->get('/combos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('combos.0.all_locations', true));
    }

    // ---------- fixtures ----------

    private function makeProduct(string $name, int $quantity, int $price = 100000, ?Category $category = null): Product
    {
        return Product::create([
            'category_id' => ($category ?? $this->category)->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'price_per_day' => $price,
            'quantity' => $quantity,
            'status' => 'active',
        ]);
    }

    /** @param  array<int, array{0: Product, 1: int}>  $items */
    private function makeCombo(string $name, string $slug, array $items, int $sortOrder = 0): Combo
    {
        $combo = Combo::create([
            'name' => $name,
            'slug' => $slug,
            'combo_price' => 500000,
            'status' => 'active',
            'sort_order' => $sortOrder,
        ]);

        foreach ($items as [$product, $qty]) {
            ComboItem::create([
                'combo_id' => $combo->id,
                'product_id' => $product->id,
                'quantity' => $qty,
            ]);
        }

        return $combo;
    }

    private function makeOrder(Product $product, int $quantity, string $start, string $end, string $status, ?int $locationId): Order
    {
        $order = Order::create([
            'code' => 'BOP-'.uniqid(),
            'customer_name' => 'Khach Test',
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
}
