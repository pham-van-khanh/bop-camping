<?php

namespace Tests\Feature;

use App\Models\Combo;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Services\AvailabilityService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ComboSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\ServiceLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * bopcamping-ry4u — dữ liệu seed phải DÙNG ĐƯỢC ngay.
 *
 * Trước đây seed xong thì product_service_location trống, mà AvailabilityService chỉ đọc
 * pivot đó (KHÔNG đọc products.quantity). Kết quả: 8/9 món hiện "Hết hàng" ngay sau
 * migrate:fresh --seed, và không ai thử được luồng đặt hàng trên máy mình. Combo thì không
 * có seeder nào nên trang /combos trống trơn.
 *
 * Test này chặn việc đó tái diễn — nếu ai sửa seeder mà quên tồn kho, nó đỏ ngay.
 */
class SeederStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CategorySeeder::class,
            ServiceLocationSeeder::class,
            ProductSeeder::class,
            ComboSeeder::class,
        ]);
    }

    public function test_seed_tao_du_co_so_va_san_pham(): void
    {
        $this->assertGreaterThanOrEqual(2, ServiceLocation::where('status', 'open')->count());
        $this->assertGreaterThan(0, Product::where('status', 'active')->count());
    }

    /** Mỗi sản phẩm phải có tồn ở MỌI cơ sở, không cơ sở nào bị bỏ trống. */
    public function test_moi_san_pham_co_ton_o_moi_co_so(): void
    {
        $soCoSo = ServiceLocation::count();

        foreach (Product::with('serviceLocations')->get() as $product) {
            $this->assertCount(
                $soCoSo,
                $product->serviceLocations,
                "Sản phẩm {$product->name} thiếu dòng tồn kho ở cơ sở nào đó",
            );
        }
    }

    /**
     * BẤT BIẾN của mô hình tồn-theo-kho: products.quantity = SUM(pivot.quantity).
     * Đây đúng là điều syncStocks() phía admin vẫn giữ; seed phải giữ y như vậy, nếu không
     * dữ liệu seed sẽ khác dữ liệu thật và test dựa trên nó sẽ nói dối.
     */
    public function test_tong_ton_khop_voi_products_quantity(): void
    {
        foreach (Product::with('serviceLocations')->get() as $product) {
            $this->assertSame(
                (int) $product->quantity,
                (int) $product->serviceLocations->sum('pivot.quantity'),
                "Sản phẩm {$product->name}: products.quantity lệch với tổng tồn theo kho",
            );
        }
    }

    /** Đồ vải cần giặt/phơi -> phải có đệm quay vòng (adr_turnaround_buffer). */
    public function test_do_vai_co_dem_quay_vong(): void
    {
        $leu = Product::where('name', 'like', 'Lều%')->with('serviceLocations')->firstOrFail();

        foreach ($leu->serviceLocations as $loc) {
            $this->assertSame(1, (int) $loc->pivot->buffer_days);
        }

        $ghe = Product::where('name', 'like', 'Ghế%')->with('serviceLocations')->firstOrFail();
        $this->assertSame(0, (int) $ghe->serviceLocations->first()->pivot->buffer_days);
    }

    /**
     * CA QUAN TRỌNG NHẤT — chính là triệu chứng của bopcamping-ry4u: chọn ngày mà mọi món
     * hiện "Hết hàng". Không có test này thì lỗi cũ quay lại mà không ai biết.
     */
    public function test_chon_ngay_thi_khong_mon_nao_het_hang(): void
    {
        $availability = app(AvailabilityService::class);
        $products = Product::where('status', 'active')->get();

        $quantities = $availability->availableQuantitiesFor(
            $products,
            Carbon::today()->addDays(3),
            Carbon::today()->addDays(5),
            null,
        );

        foreach ($products as $product) {
            $this->assertGreaterThan(
                0,
                $quantities[$product->id] ?? 0,
                "Sản phẩm {$product->name} hiện HẾT HÀNG ngay sau khi seed",
            );
        }
    }

    public function test_seed_tao_combo_dat_duoc(): void
    {
        $combos = Combo::with('items')->where('is_active', true)->get();

        $this->assertGreaterThanOrEqual(2, $combos->count());

        $availability = app(AvailabilityService::class);

        foreach ($combos as $combo) {
            $this->assertNotEmpty($combo->items, "Combo {$combo->name} không có món nào");
            $this->assertNotEmpty(
                $combo->openLocationIds(),
                "Combo {$combo->name} không gắn cơ sở nào -> không bán được ở đâu cả",
            );
            $this->assertGreaterThan(
                0,
                $availability->comboAvailable($combo, Carbon::today()->addDays(3), Carbon::today()->addDays(5)),
                "Combo {$combo->name} hiện hết hàng ngay sau khi seed",
            );
        }
    }

    /** Combo phải RẺ HƠN thuê lẻ, nếu không thì nó không có lý do tồn tại. */
    public function test_gia_combo_re_hon_thue_le(): void
    {
        foreach (Combo::with('items.product')->get() as $combo) {
            $giaLe = $combo->items->sum(fn ($i) => (int) $i->product->price_per_day * $i->quantity);

            $this->assertLessThan(
                $giaLe,
                (int) $combo->combo_price,
                "Combo {$combo->name} đắt hơn hoặc bằng thuê lẻ",
            );
        }
    }
}
