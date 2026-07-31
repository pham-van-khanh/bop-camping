<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Thẻ combo hiện badge cơ sở (bopcamping-daet) nên props phải mang locations +
 * all_locations ở CẢ HAI chỗ dùng thẻ: trang chủ (combo nổi bật) và /combos.
 *
 * Trang chủ trước đây KHÔNG có 2 field này — badge sẽ trống. Test này neo cả hai.
 */
class ComboCardLocationDataTest extends TestCase
{
    use RefreshDatabase;

    private ServiceLocation $vinh;

    private ServiceLocation $hanoi;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghe An', 'status' => 'open', 'sort_order' => 1]);
        $this->hanoi = ServiceLocation::create(['name' => 'Ha Noi', 'area' => 'Ha Noi', 'status' => 'open', 'sort_order' => 2]);
        $this->category = Category::create(['name' => 'Do camping', 'slug' => 'do-camping-ccld']);
    }

    public function test_trang_chu_tra_locations_cho_combo_noi_bat(): void
    {
        $combo = $this->combo('Combo mot kho', [$this->vinh->id]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(function ($page) use ($combo) {
                $c = collect($page->toArray()['props']['featured_combos'])->firstWhere('id', $combo->id);

                $this->assertNotNull($c, 'combo phải có trong featured_combos');
                $this->assertSame([['slug' => 'vinh', 'name' => 'Vinh']], $c['locations']);
                $this->assertFalse($c['all_locations'], 'mới 1/2 cơ sở thì chưa phải toàn hệ thống');
            });
    }

    public function test_trang_chu_all_locations_khi_ban_du_moi_co_so(): void
    {
        $combo = $this->combo('Combo du kho', [$this->vinh->id, $this->hanoi->id]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(function ($page) use ($combo) {
                $c = collect($page->toArray()['props']['featured_combos'])->firstWhere('id', $combo->id);
                $this->assertTrue($c['all_locations']);
            });
    }

    /** Kho chưa mở KHÔNG tính vào mẫu số lẫn danh sách. */
    public function test_trang_chu_bo_qua_co_so_chua_mo(): void
    {
        $coming = ServiceLocation::create(['name' => 'Da Nang', 'area' => 'Da Nang', 'status' => 'coming', 'sort_order' => 3]);
        $combo = $this->combo('Combo co coming', [$this->vinh->id, $this->hanoi->id, $coming->id]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(function ($page) use ($combo, $coming) {
                $c = collect($page->toArray()['props']['featured_combos'])->firstWhere('id', $combo->id);

                $this->assertNotContains('da-nang', array_column($c['locations'], 'slug'));
                $this->assertTrue($c['all_locations'], 'đủ 2 cơ sở ĐANG MỞ là toàn hệ thống');
                $this->assertSame(2, count($c['locations']));
                unset($coming);
            });
    }

    public function test_combo_khong_co_co_so_thi_locations_rong(): void
    {
        $combo = $this->combo('Combo khong kho', []);

        $this->get('/')
            ->assertOk()
            ->assertInertia(function ($page) use ($combo) {
                $c = collect($page->toArray()['props']['featured_combos'])->firstWhere('id', $combo->id);
                $this->assertSame([], $c['locations']);
                $this->assertFalse($c['all_locations']);
            });
    }

    public function test_trang_combos_cung_tra_locations(): void
    {
        $combo = $this->combo('Combo listing', [$this->vinh->id]);

        $this->get('/combos')
            ->assertOk()
            ->assertInertia(function ($page) use ($combo) {
                $c = collect($page->toArray()['props']['combos'])->firstWhere('id', $combo->id);
                $this->assertSame([['slug' => 'vinh', 'name' => 'Vinh']], $c['locations']);
            });
    }

    /** Số query trang chủ KHÔNG tăng theo số combo (eager load + memoize đếm cơ sở). */
    public function test_trang_chu_so_query_khong_tang_theo_so_combo(): void
    {
        $this->assertSame(
            $this->countQueriesOnHome(1),
            $this->countQueriesOnHome(4),
            'trang chủ phải O(1) query theo số combo nổi bật'
        );
    }

    private function countQueriesOnHome(int $howMany): int
    {
        ComboItem::query()->delete();
        Combo::query()->delete();

        for ($i = 0; $i < $howMany; $i++) {
            $this->combo("Combo dem {$howMany} {$i}", [$this->vinh->id, $this->hanoi->id]);
        }

        // Warm-up: request đầu của suite còn tạo lazy hàng singleton (site_settings).
        $this->get('/')->assertOk();

        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $this->get('/')->assertOk();

        return $count;
    }

    /** @param  array<int, int>  $locationIds */
    private function combo(string $name, array $locationIds): Combo
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'SP '.Str::slug($name),
            'slug' => Str::slug($name).'-sp-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => 5,
            'status' => 'active',
        ]);
        $product->serviceLocations()->attach($this->vinh->id, ['quantity' => 5, 'buffer_days' => 0]);
        $product->serviceLocations()->attach($this->hanoi->id, ['quantity' => 5, 'buffer_days' => 0]);

        $combo = Combo::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'combo_price' => 50000,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        ComboItem::create(['combo_id' => $combo->id, 'product_id' => $product->id, 'quantity' => 1]);
        $combo->serviceLocations()->sync($locationIds);

        return $combo->fresh();
    }
}
