<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * bopcamping-e5pi (T1) — combo có kho RIÊNG (pivot combo_service_location) thay vì suy ra
 * bằng giao các kho của món con.
 *
 * Điểm dễ sai nhất và là lý do đổi quyết định #2: assignableLocationIds() phải dựa trên
 * TƯ CÁCH THÀNH VIÊN pivot, KHÔNG dựa trên tồn > 0. Trên prod chỉ 3/11 sản phẩm còn tồn,
 * có combo mọi món tồn 0 — chặn theo tồn thì admin không gán nổi kho nào (PRD mục 6, R2).
 */
class ComboStoreLocationTest extends TestCase
{
    use RefreshDatabase;

    private ServiceLocation $vinh;

    private ServiceLocation $hanoi;

    private ServiceLocation $coming;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        $this->hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'area' => 'Hà Nội', 'status' => 'open', 'sort_order' => 2]);
        $this->coming = ServiceLocation::create(['name' => 'Đà Nẵng', 'area' => 'Đà Nẵng', 'status' => 'coming', 'sort_order' => 3]);
        $this->category = Category::create(['name' => 'Do camping', 'slug' => 'do-camping-cslt']);
    }

    // ---------- assignableLocationIds: theo MEMBERSHIP, không theo tồn ----------

    public function test_assignable_la_giao_kho_cua_moi_mon_con(): void
    {
        $a = $this->product('Leu', [$this->vinh->id => 3, $this->hanoi->id => 2]);
        $b = $this->product('Dem', [$this->vinh->id => 5]);              // chỉ Vinh

        $combo = $this->combo('Combo giao', [[$a, 1], [$b, 1]]);

        // Vinh có ở cả 2 món; Hà Nội thiếu món Dem → chỉ Vinh.
        $this->assertSame([$this->vinh->id], $combo->assignableLocationIds());
    }

    /**
     * CA QUYẾT ĐỊNH — combo mà MỌI món tồn 0 vẫn phải gán được kho.
     * Đây đúng trạng thái combo `relax` và `bbq-party` trên prod.
     */
    public function test_moi_mon_ton_0_van_gan_duoc_kho(): void
    {
        $a = $this->product('Ban gap', [$this->vinh->id => 0, $this->hanoi->id => 0]);
        $b = $this->product('Ghe thu gian', [$this->vinh->id => 0, $this->hanoi->id => 0]);

        $combo = $this->combo('Combo relax', [[$a, 1], [$b, 2]]);

        $ids = $combo->assignableLocationIds();
        sort($ids);

        $this->assertSame([$this->vinh->id, $this->hanoi->id], $ids, 'tồn 0 KHÔNG được chặn việc gán kho');
    }

    public function test_assignable_loai_kho_chua_mo(): void
    {
        $a = $this->product('Bep', [$this->vinh->id => 2, $this->coming->id => 9]);

        $combo = $this->combo('Combo sap mo', [[$a, 1]]);

        $this->assertNotContains($this->coming->id, $combo->assignableLocationIds());
    }

    /** Món chưa gắn kho nào → không kho nào chắc chắn phục vụ được cả combo. */
    public function test_mon_khong_gan_kho_lam_assignable_rong(): void
    {
        $a = $this->product('Leu', [$this->vinh->id => 3]);
        $b = $this->product('Mon la', []);

        $combo = $this->combo('Combo hong', [[$a, 1], [$b, 1]]);

        $this->assertSame([], $combo->assignableLocationIds());
    }

    public function test_combo_rong_mon_thi_assignable_rong(): void
    {
        $combo = Combo::create([
            'name' => 'Combo rong', 'slug' => 'combo-rong-cslt',
            'combo_price' => 100000, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->assertSame([], $combo->fresh()->assignableLocationIds());
    }

    // ---------- openLocations: kho ĐƯỢC GÁN ----------

    public function test_open_locations_la_kho_duoc_gan_khong_phai_giao_mon_con(): void
    {
        // Cả 2 món phục vụ cả 2 kho → giao = cả 2. Nhưng chỉ GÁN Vinh.
        $a = $this->product('Leu', [$this->vinh->id => 3, $this->hanoi->id => 3]);
        $b = $this->product('Dem', [$this->vinh->id => 3, $this->hanoi->id => 3]);

        $combo = $this->combo('Combo chi Vinh', [[$a, 1], [$b, 1]]);
        $combo->serviceLocations()->sync([$this->vinh->id]);

        $slugs = array_column($combo->fresh()->openLocations(), 'slug');

        $this->assertSame(['vinh'], $slugs, 'phải theo kho GÁN, không phải giao món con');
    }

    public function test_open_locations_loai_kho_chua_mo_du_da_gan(): void
    {
        $a = $this->product('Leu', [$this->vinh->id => 3]);
        $combo = $this->combo('Combo co coming', [[$a, 1]]);
        $combo->serviceLocations()->sync([$this->vinh->id, $this->coming->id]);

        $slugs = array_column($combo->fresh()->openLocations(), 'slug');

        $this->assertSame(['vinh'], $slugs);
    }

    public function test_open_locations_tra_dung_dang_slug_name(): void
    {
        $a = $this->product('Leu', [$this->vinh->id => 3]);
        $combo = $this->combo('Combo dang', [[$a, 1]]);
        $combo->serviceLocations()->sync([$this->vinh->id]);

        // Giữ nguyên dạng của commonOpenLocations() cũ để FE không phải đổi type.
        $this->assertSame([['slug' => 'vinh', 'name' => 'Vinh']], $combo->fresh()->openLocations());
    }

    // ---------- stockAtLocation: chỉ là thông tin ----------

    public function test_stock_at_location_tra_ton_tung_mon(): void
    {
        $a = $this->product('Leu', [$this->vinh->id => 3, $this->hanoi->id => 1]);
        $b = $this->product('Dem', [$this->vinh->id => 0]);   // hết ở Vinh, không phục vụ Hà Nội

        $combo = $this->combo('Combo ton', [[$a, 1], [$b, 1]]);

        $this->assertSame([$a->id => 3, $b->id => 0], $combo->stockAtLocation($this->vinh->id));
        // Món không phục vụ ở kho đang hỏi → 0 (khớp Product::stockAt()).
        $this->assertSame([$a->id => 1, $b->id => 0], $combo->stockAtLocation($this->hanoi->id));
    }

    // ---------- Backfill của migration ----------

    /**
     * Backfill phải tái tạo ĐÚNG tập mà commonOpenLocations() cũ tính ra, để không combo nào
     * đổi hành vi sau deploy. Chạy lại migration trên dữ liệu dựng sẵn.
     */
    public function test_backfill_gan_dung_tap_giao_khi_co_kho_chung(): void
    {
        $a = $this->product('Leu', [$this->vinh->id => 3, $this->hanoi->id => 2]);
        $b = $this->product('Dem', [$this->vinh->id => 5]);          // chỉ Vinh
        $combo = $this->combo('Combo giao', [[$a, 1], [$b, 1]]);

        $this->rerunBackfill();

        $this->assertSame(
            [$this->vinh->id],
            DB::table('combo_service_location')->where('combo_id', $combo->id)
                ->pluck('service_location_id')->map(fn ($i) => (int) $i)->all()
        );
    }

    /**
     * CA CHỐNG BẾ TẮC — không có kho chung thì gán TẤT CẢ kho đang mở, KHÔNG để rỗng.
     * Combo 0 kho lọt cả 2 chốt vị trí của giỏ rồi bị checkout từ chối (PRD mục 6, R1).
     */
    public function test_backfill_khong_co_kho_chung_thi_gan_tat_ca_kho_mo(): void
    {
        $a = $this->product('Chi Vinh', [$this->vinh->id => 3]);
        $b = $this->product('Chi HaNoi', [$this->hanoi->id => 3]);
        $combo = $this->combo('Combo lech kho', [[$a, 1], [$b, 1]]);

        $this->rerunBackfill();

        $ids = DB::table('combo_service_location')->where('combo_id', $combo->id)
            ->pluck('service_location_id')->map(fn ($i) => (int) $i)->sort()->values()->all();

        $this->assertSame([$this->vinh->id, $this->hanoi->id], $ids, 'không được để combo 0 kho');
    }

    public function test_backfill_khong_gan_kho_chua_mo(): void
    {
        $a = $this->product('Leu', [$this->vinh->id => 3, $this->coming->id => 9]);
        $combo = $this->combo('Combo coming', [[$a, 1]]);

        $this->rerunBackfill();

        $ids = DB::table('combo_service_location')->where('combo_id', $combo->id)
            ->pluck('service_location_id')->map(fn ($i) => (int) $i)->all();

        $this->assertNotContains($this->coming->id, $ids);
    }

    /** Combo rỗng món cũng phải có kho (nhánh fallback), không để 0 kho. */
    public function test_backfill_combo_rong_mon_van_co_kho(): void
    {
        $combo = Combo::create([
            'name' => 'Combo rong', 'slug' => 'combo-rong-bf',
            'combo_price' => 100000, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->rerunBackfill();

        $this->assertSame(
            2,
            DB::table('combo_service_location')->where('combo_id', $combo->id)->count(),
            'combo rỗng món phải nhận cả 2 kho đang mở'
        );
    }

    // ---------- helpers ----------

    /**
     * Xoá pivot rồi chạy lại đúng đoạn backfill của migration.
     * RefreshDatabase đã chạy migration lúc DB trống nên phải chạy lại trên dữ liệu test.
     */
    private function rerunBackfill(): void
    {
        DB::table('combo_service_location')->delete();

        $migration = require database_path('migrations/2026_07_31_100000_create_combo_service_location_table.php');

        // up() tạo bảng rồi backfill; bảng đã có nên chỉ chạy lại phần backfill bằng cách
        // drop + up lại — an toàn vì pivot chưa có dữ liệu nghiệp vụ nào ngoài backfill.
        Schema::dropIfExists('combo_service_location');
        $migration->up();
    }

    /** @param  array<int, int>  $stocks  [locationId => quantity] */
    private function product(string $name, array $stocks): Product
    {
        $p = Product::create([
            'category_id' => $this->category->id,
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

    /** @param  array<int, array{0: Product, 1: int}>  $items */
    private function combo(string $name, array $items): Combo
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

        return $combo->fresh();
    }
}
