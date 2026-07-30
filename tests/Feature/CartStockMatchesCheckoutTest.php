<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Services\StoreResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * bopcamping-jyxi — số "còn" mà GIỎ báo phải bằng số CHECKOUT thật sự cho đặt.
 *
 * Lỗi gốc: CartController::refresh() gọi availableQuantity() KHÔNG truyền kho → nhánh tồn
 * TOÀN CỤC products.quantity. Nhưng checkout (StoreResolver::shortfall) đòi MỘT kho đủ cả giỏ,
 * không cộng xuyên kho. Kết quả: giỏ báo "còn 6" (2+4 xuyên kho) trong khi kho lớn nhất chỉ có 4
 * → khách nhét 6 bộ rồi bị chặn ở bước cuối bằng thông báo chung chung.
 *
 * Test này neo giỏ vào ĐÚNG ngưỡng mà StoreResolver chấp nhận, nên nếu ai đổi một bên
 * mà quên bên kia thì đỏ.
 */
class CartStockMatchesCheckoutTest extends TestCase
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
        $this->category = Category::create(['name' => 'Do camping', 'slug' => 'do-camping-cart']);

        $this->start = Carbon::today()->addDays(20)->toDateString();
        $this->end = Carbon::today()->addDays(22)->toDateString();
    }

    /**
     * Ca chính của lỗi: tồn CHIA cho 2 kho (Vinh 4 + Hà Nội 2 = quantity 6).
     * Giỏ phải báo 4 (kho lớn nhất), KHÔNG phải 6.
     */
    public function test_gio_bao_dung_bang_so_kho_lon_nhat_khong_cong_xuyen_kho(): void
    {
        $product = $this->makeProduct('Ghe gap', 6);
        $product->serviceLocations()->attach($this->vinh->id, ['quantity' => 4, 'buffer_days' => 0]);
        $product->serviceLocations()->attach($this->hanoi->id, ['quantity' => 2, 'buffer_days' => 0]);

        $stock = $this->cartStock("pr[]={$product->id}:{$this->start}:{$this->end}");

        $this->assertSame(4, $stock["p:{$product->id}:{$this->start}:{$this->end}"], 'giỏ phải báo 4, không phải 6 (tồn toàn cục)');
    }

    /**
     * INVARIANT — số giỏ báo phải là ngưỡng CHÍNH XÁC mà StoreResolver chấp nhận:
     * đặt đúng số đó → cho; đặt hơn 1 → từ chối.
     */
    public function test_so_gio_bao_la_dung_nguong_checkout_chap_nhan(): void
    {
        $cases = [
            'chia 2 kho' => [[4, 2], 6],
            'lệch nhiều' => [[10, 1], 11],
            'chỉ 1 kho có hàng' => [[3, 0], 3],
            'hai kho bằng nhau' => [[5, 5], 10],
        ];

        foreach ($cases as $label => [[$atVinh, $atHanoi], $globalQty]) {
            $product = $this->makeProduct('SP '.Str::slug($label), $globalQty);
            $product->serviceLocations()->attach($this->vinh->id, ['quantity' => $atVinh, 'buffer_days' => 0]);
            $product->serviceLocations()->attach($this->hanoi->id, ['quantity' => $atHanoi, 'buffer_days' => 0]);

            $stock = $this->cartStock("pr[]={$product->id}:{$this->start}:{$this->end}");
            $shown = $stock["p:{$product->id}:{$this->start}:{$this->end}"];

            $this->assertSame(max($atVinh, $atHanoi), $shown, "[{$label}] giỏ phải báo max qua kho");

            // Đặt đúng số giỏ báo → checkout PHẢI cho.
            $this->assertTrue($this->checkoutAccepts($product, $shown), "[{$label}] checkout phải cho đặt {$shown} bộ");
            // Đặt hơn 1 → checkout PHẢI từ chối. (Giỏ không được báo cao hơn ngưỡng.)
            $this->assertFalse($this->checkoutAccepts($product, $shown + 1), "[{$label}] checkout phải từ chối ".($shown + 1).' bộ');
        }
    }

    /** Sản phẩm chưa gắn kho nào (dữ liệu cũ) → giữ nhánh toàn cục, không bị về 0. */
    public function test_san_pham_khong_gan_kho_van_bao_ton_toan_cuc(): void
    {
        $legacy = $this->makeProduct('Bep cu', 5);

        $stock = $this->cartStock("pr[]={$legacy->id}:{$this->start}:{$this->end}");

        $this->assertSame(5, $stock["p:{$legacy->id}:{$this->start}:{$this->end}"]);
    }

    /** Combo cũng phải theo "best", không cộng xuyên kho. */
    public function test_combo_bao_dung_theo_kho(): void
    {
        $a = $this->makeProduct('Leu combo', 6);
        $a->serviceLocations()->attach($this->vinh->id, ['quantity' => 4, 'buffer_days' => 0]);
        $a->serviceLocations()->attach($this->hanoi->id, ['quantity' => 2, 'buffer_days' => 0]);

        $b = $this->makeProduct('Dem combo', 6);
        $b->serviceLocations()->attach($this->vinh->id, ['quantity' => 2, 'buffer_days' => 0]);
        $b->serviceLocations()->attach($this->hanoi->id, ['quantity' => 4, 'buffer_days' => 0]);

        $combo = Combo::create([
            'name' => 'Combo QA', 'slug' => 'combo-qa-cart',
            'combo_price' => 300000, 'status' => 'active', 'sort_order' => 1,
        ]);
        ComboItem::create(['combo_id' => $combo->id, 'product_id' => $a->id, 'quantity' => 1]);
        ComboItem::create(['combo_id' => $combo->id, 'product_id' => $b->id, 'quantity' => 1]);

        $stock = $this->cartStock("cr[]={$combo->id}:{$this->start}:{$this->end}");

        // Vinh: min(4,2)=2 · Hà Nội: min(2,4)=2 → best 2.
        // Tồn toàn cục sẽ ra min(6,6)=6 → nếu ra 6 là vẫn còn dùng nhánh cũ.
        $this->assertSame(2, $stock["c:{$combo->id}:{$this->start}:{$this->end}"], 'combo phải là 2, không phải 6');
    }

    /** Nhiều dòng cùng khoảng ngày → gom thành 1 batch, số query không tăng theo số món. */
    public function test_so_query_khong_tang_theo_so_dong_gio(): void
    {
        $this->assertSame(
            $this->countQueriesForLines(2),
            $this->countQueriesForLines(12),
            'refresh phải gom theo khoảng ngày, không phải 1 query mỗi dòng'
        );
    }

    private function countQueriesForLines(int $howMany): int
    {
        $ids = [];
        for ($i = 0; $i < $howMany; $i++) {
            $p = $this->makeProduct("Mon {$howMany} {$i}", 5);
            $p->serviceLocations()->attach($this->vinh->id, ['quantity' => 3, 'buffer_days' => 1]);
            $p->serviceLocations()->attach($this->hanoi->id, ['quantity' => 2, 'buffer_days' => 0]);
            $ids[] = $p->id;
        }
        $query = collect($ids)->map(fn ($id) => "pr[]={$id}:{$this->start}:{$this->end}")->implode('&');
        $url = '/gio-thue/lam-tuoi?'.$query;

        // Warm-up: request đầu của suite còn tạo lazy hàng singleton (site_settings).
        $this->get($url)->assertOk();

        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $this->get($url)->assertOk();

        return $count;
    }

    /** Gọi endpoint refresh và trả về map stock. */
    private function cartStock(string $query): array
    {
        $response = $this->get('/gio-thue/lam-tuoi?'.$query)->assertOk();

        return (array) $response->json('stock');
    }

    /** StoreResolver có chấp nhận đặt $qty bộ trong khoảng test không? */
    private function checkoutAccepts(Product $product, int $qty): bool
    {
        $resolver = app(StoreResolver::class);
        $byId = Product::with('serviceLocations')->whereKey($product->id)->get()->keyBy('id');
        $needed = [$product->id.'|'.$this->start.'|'.$this->end => $qty];

        try {
            $resolver->resolveForCart($needed, $byId, null);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function makeProduct(string $name, int $quantity): Product
    {
        return Product::create([
            'category_id' => $this->category->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => $quantity,
            'status' => 'active',
        ]);
    }
}
