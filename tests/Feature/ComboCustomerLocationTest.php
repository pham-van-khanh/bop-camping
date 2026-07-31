<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * bopcamping-zdeh (T4) — phía khách: /combos lọc theo kho ĐƯỢC GÁN của combo (không còn suy ra
 * từ giao kho món con) và comboQuantitiesFor() chỉ quét tập kho đó.
 *
 * Xem app/Models/Combo.php (serviceLocations/openLocations/assignableLocationIds sau T1) và
 * artifacts/plan_combo_store_location.md mục T4, artifacts/prd_combo_store_location.md FR-5.
 */
class ComboCustomerLocationTest extends TestCase
{
    use RefreshDatabase;

    private ServiceLocation $vinh;

    private ServiceLocation $hanoi;

    private Category $category;

    private string $start;

    private string $end;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        $this->hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'area' => 'Hà Nội', 'status' => 'open', 'sort_order' => 2]);
        $this->category = Category::create(['name' => 'Do camping', 'slug' => 'do-camping-zdeh']);

        // Luôn ở tương lai để không bị FR-4 (parseRange) loại vì "ngày quá khứ".
        $this->start = Carbon::today()->addDays(10)->toDateString();
        $this->end = Carbon::today()->addDays(12)->toDateString();
    }

    // ---------- /combos?vi-tri= lọc theo kho được gán ----------

    public function test_vi_tri_chi_tra_combo_duoc_gan_kho_do(): void
    {
        $a = $this->product('Leu vinh', [$this->vinh->id => 3]);
        $b = $this->product('Leu hn', [$this->hanoi->id => 3]);

        $comboVinh = $this->combo('Combo vinh', [[$a, 1]], [$this->vinh->id]);
        $comboHanoi = $this->combo('Combo hanoi', [[$b, 1]], [$this->hanoi->id]);

        $this->get('/combos?vi-tri=vinh')
            ->assertOk()
            ->assertInertia(function ($page) use ($comboVinh, $comboHanoi) {
                $ids = collect($page->toArray()['props']['combos'])->pluck('id')->all();

                $this->assertContains($comboVinh->id, $ids);
                $this->assertNotContains($comboHanoi->id, $ids, 'combo gán kho khác không được xuất hiện');
            });
    }

    // ---------- available tính theo đúng kho được gán, không lấy kho khác ----------

    public function test_combo_gan_1_kho_available_tinh_dung_kho_do_khong_lay_kho_khac(): void
    {
        // Món con còn hàng nhiều ở Hà Nội nhưng combo CHỈ gán Vinh, nơi món hết sạch.
        $a = $this->product('Leu het vinh', [$this->vinh->id => 0, $this->hanoi->id => 10]);

        $combo = $this->combo('Combo chi vinh', [[$a, 1]], [$this->vinh->id]);

        $this->get("/combos?start={$this->start}&end={$this->end}")
            ->assertOk()
            ->assertInertia(function ($page) use ($combo) {
                $byId = collect($page->toArray()['props']['combos'])->keyBy('id');

                $this->assertSame(0, $byId[$combo->id]['available'], 'không được lấy tồn ở Hà Nội vì combo không gán kho đó');
                $this->assertFalse($byId[$combo->id]['in_range']);
            });
    }

    public function test_combo_0_kho_gan_available_bang_0(): void
    {
        $a = $this->product('Leu khong gan kho combo', [$this->vinh->id => 10, $this->hanoi->id => 10]);

        // KHÔNG gọi sync() — combo không được gán kho nào dù món con còn hàng khắp nơi.
        $combo = $this->combo('Combo 0 kho', [[$a, 1]], []);

        $this->get("/combos?start={$this->start}&end={$this->end}")
            ->assertOk()
            ->assertInertia(function ($page) use ($combo) {
                $byId = collect($page->toArray()['props']['combos'])->keyBy('id');

                $this->assertSame(0, $byId[$combo->id]['available'], 'combo không gán kho nào -> không bán được ở đâu');
                $this->assertFalse($byId[$combo->id]['in_range']);
            });
    }

    /** Giữ nguyên công thức max-qua-kho-của-min-qua-món (bopcamping-jyxi), chỉ đổi tập kho quét. */
    public function test_combo_gan_2_kho_la_max_qua_kho_cua_min_qua_mon(): void
    {
        // A: Vinh 4, HN 2. B: Vinh 2, HN 4.
        // min-của-max sai (4); đúng là max(min(4,2), min(2,4)) = 2.
        $a = $this->product('Mon a', [$this->vinh->id => 4, $this->hanoi->id => 2]);
        $b = $this->product('Mon b', [$this->vinh->id => 2, $this->hanoi->id => 4]);

        $combo = $this->combo('Combo 2 kho', [[$a, 1], [$b, 1]], [$this->vinh->id, $this->hanoi->id]);

        $this->get("/combos?start={$this->start}&end={$this->end}")
            ->assertOk()
            ->assertInertia(function ($page) use ($combo) {
                $byId = collect($page->toArray()['props']['combos'])->keyBy('id');

                $this->assertSame(2, $byId[$combo->id]['available']);
            });
    }

    // ---------- Query count không tăng theo số combo ----------

    public function test_so_query_khong_tang_theo_so_combo(): void
    {
        $this->assertSame(
            $this->countQueriesOnCombos(2),
            $this->countQueriesOnCombos(12),
            '/combos phải là O(1) query theo số combo'
        );
    }

    private function countQueriesOnCombos(int $howMany): int
    {
        // Cô lập bằng danh mục riêng, không xoá dữ liệu chung (vướng khoá ngoại order_items).
        // Không gán service location cho combo/món ở đây: mục tiêu là đo O(1) của index()/
        // comboQuantitiesFor() theo SỐ COMBO (NFR bopcamping-j91m), khớp fixture của
        // ListingDateFilterTest::countQueriesOnCombos(). Có một N+1 KHÁC, đã có từ trước (không
        // phải do T4), ở ComboController::shape() dòng "all_locations" — gọi
        // ServiceLocation::open()->count() cho MỖI combo có locations không rỗng; đã báo cáo
        // riêng (ngoài phạm vi method index() được giao ở T4), không che bằng test này.
        $category = Category::create(['name' => "Nhom {$howMany}", 'slug' => "nhom-zdeh-{$howMany}"]);

        for ($i = 0; $i < $howMany; $i++) {
            $a = $this->product("Combo mon a {$howMany} {$i}", [], $category);
            $b = $this->product("Combo mon b {$howMany} {$i}", [], $category);
            $this->combo("Combo so {$howMany} {$i}", [[$a, 1], [$b, 2]], []);
        }

        return $this->countQueriesOn("/combos?start={$this->start}&end={$this->end}");
    }

    /**
     * Đếm query của 1 URL. Warm-up trước rồi mới đo: request ĐẦU TIÊN của cả test suite còn
     * tạo lazy hàng singleton (site_settings) nên nếu đo luôn sẽ lệch 1 query một cách giả tạo,
     * không liên quan gì tới số combo (xem ListingDateFilterTest::countQueriesOn()).
     */
    private function countQueriesOn(string $url): int
    {
        $this->get($url)->assertOk();

        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $this->get($url)->assertOk();

        return $count;
    }

    // ---------- helpers ----------

    /** @param  array<int, int>  $stocks  [locationId => quantity] */
    private function product(string $name, array $stocks, ?Category $category = null): Product
    {
        $p = Product::create([
            'category_id' => ($category ?? $this->category)->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => array_sum($stocks),
            'status' => 'active',
        ]);

        foreach ($stocks as $locationId => $qty) {
            $p->serviceLocations()->attach($locationId, ['quantity' => $qty, 'buffer_days' => 0]);
        }

        return $p;
    }

    /**
     * @param  array<int, array{0: Product, 1: int}>  $items
     * @param  array<int, int>  $locationIds  kho ĐƯỢC GÁN cho combo (sync trực tiếp pivot, không qua admin)
     */
    private function combo(string $name, array $items, array $locationIds): Combo
    {
        $combo = Combo::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'combo_price' => 300000,
            'deposit' => 100000,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        foreach ($items as [$product, $qty]) {
            ComboItem::create(['combo_id' => $combo->id, 'product_id' => $product->id, 'quantity' => $qty]);
        }

        $combo->serviceLocations()->sync($locationIds);

        return $combo->fresh();
    }
}
