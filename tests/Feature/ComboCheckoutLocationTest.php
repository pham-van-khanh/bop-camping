<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * bopcamping-v5ig (T5) — checkout phải TỪ CHỐI đơn có combo mà cơ sở của đơn không nằm trong
 * kho được gán cho combo đó.
 *
 * Vì sao cần check riêng: combo được BUNG thành order_items per-product trước khi vào
 * StoreResolver, nên StoreResolver chỉ thấy tồn từng sản phẩm — nó không biết "combo này chỉ
 * bán ở Vinh". Thiếu check này thì khách đặt được combo ở cơ sở mà shop không bán nó.
 *
 * Đây là ĐƯỜNG TIỀN nên chốt cả 3 nhánh: lệch kho → chặn, khớp kho → đặt được,
 * đơn không gắn kho (legacy) → không chặn.
 */
class ComboCheckoutLocationTest extends TestCase
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

        $this->vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghe An', 'status' => 'open', 'sort_order' => 1]);
        $this->hanoi = ServiceLocation::create(['name' => 'Ha Noi', 'area' => 'Ha Noi', 'status' => 'open', 'sort_order' => 2]);
        $this->category = Category::create(['name' => 'Do camping', 'slug' => 'do-camping-cclt']);

        $this->start = Carbon::today()->addDays(5)->toDateString();
        $this->end = Carbon::today()->addDays(7)->toDateString();
    }

    /** Combo chỉ bán ở Vinh nhưng khách chọn Hà Nội → chặn, message nêu tên combo + tên kho. */
    public function test_tu_choi_khi_combo_khong_ban_o_co_so_cua_don(): void
    {
        // Món con phục vụ CẢ HAI kho và đủ tồn → StoreResolver sẽ cho qua.
        // Chỉ ràng buộc kho của COMBO là lý do phải chặn.
        $combo = $this->comboServedAt([$this->vinh->id]);

        $this->post('/dat-hang', $this->payload($combo, $this->hanoi->id))
            ->assertSessionHasErrors('items');

        $this->assertStringContainsString('Ha Noi', session('errors')->first('items'));
        $this->assertStringContainsString('Combo QA', session('errors')->first('items'));
        $this->assertSame(0, Order::count(), 'không được tạo đơn nào');
    }

    /** Khớp kho → đặt được, đơn gắn đúng cơ sở. */
    public function test_dat_duoc_khi_khop_co_so(): void
    {
        $combo = $this->comboServedAt([$this->vinh->id, $this->hanoi->id]);

        $this->post('/dat-hang', $this->payload($combo, $this->vinh->id))
            ->assertSessionHasNoErrors();

        $order = Order::where('is_parent', false)->firstOrFail();
        $this->assertSame($this->vinh->id, $order->service_location_id);
    }

    /**
     * Khách KHÔNG chọn cơ sở → StoreResolver tự gán. Nếu nó gán cơ sở mà combo không bán thì
     * vẫn phải chặn (không được lọt chỉ vì khách bỏ trống).
     */
    public function test_tu_gan_co_so_ma_combo_khong_ban_thi_van_chan(): void
    {
        // Combo chỉ bán ở Hà Nội; món con chỉ đủ tồn ở Vinh nên StoreResolver sẽ gán Vinh.
        $a = $this->product('Leu', [$this->vinh->id => 5, $this->hanoi->id => 0]);
        $b = $this->product('Dem', [$this->vinh->id => 5, $this->hanoi->id => 0]);
        $combo = $this->makeCombo($a, $b, [$this->hanoi->id]);

        $this->post('/dat-hang', $this->payload($combo, null))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
    }

    /** Không có cơ sở nào đang mở (dữ liệu cũ) → đơn không gắn kho, KHÔNG chặn. */
    public function test_khong_co_co_so_nao_thi_khong_chan(): void
    {
        $a = $this->product('Leu', []);
        $b = $this->product('Dem', []);
        $combo = $this->makeCombo($a, $b, []);

        // Đóng hết cơ sở → resolved['location'] = null.
        ServiceLocation::query()->update(['status' => 'coming']);

        $this->post('/dat-hang', $this->payload($combo, null))
            ->assertSessionHasNoErrors();

        $this->assertNull(Order::where('is_parent', false)->firstOrFail()->service_location_id);
    }

    /** Đơn chỉ có sản phẩm lẻ (không combo) → check này không được làm gì cả. */
    public function test_don_khong_co_combo_thi_khong_bi_anh_huong(): void
    {
        $a = $this->product('Leu le', [$this->vinh->id => 5]);

        $payload = $this->basePayload();
        $payload['items'] = [[
            'product_id' => $a->id, 'quantity' => 1,
            'start' => $this->start, 'end' => $this->end,
            'location_id' => $this->vinh->id, 'session' => null,
        ]];

        $this->post('/dat-hang', $payload)->assertSessionHasNoErrors();

        $this->assertSame($this->vinh->id, Order::where('is_parent', false)->firstOrFail()->service_location_id);
    }

    // ---------- helpers ----------

    /**
     * Combo có món con phục vụ CẢ HAI kho (đủ tồn), nhưng chỉ được GÁN các kho truyền vào.
     *
     * @param  array<int, int>  $assignedLocationIds
     */
    private function comboServedAt(array $assignedLocationIds): Combo
    {
        $a = $this->product('Leu', [$this->vinh->id => 5, $this->hanoi->id => 5]);
        $b = $this->product('Dem', [$this->vinh->id => 5, $this->hanoi->id => 5]);

        return $this->makeCombo($a, $b, $assignedLocationIds);
    }

    /** @param  array<int, int>  $assignedLocationIds */
    private function makeCombo(Product $a, Product $b, array $assignedLocationIds): Combo
    {
        $combo = Combo::create([
            'name' => 'Combo QA',
            'slug' => 'combo-qa-cclt',
            'combo_price' => 200000,
            'deposit' => 100000,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        ComboItem::create(['combo_id' => $combo->id, 'product_id' => $a->id, 'quantity' => 1]);
        ComboItem::create(['combo_id' => $combo->id, 'product_id' => $b->id, 'quantity' => 1]);
        $combo->serviceLocations()->sync($assignedLocationIds);

        return $combo->fresh();
    }

    /** @return array<string, mixed> */
    private function basePayload(): array
    {
        return [
            'name' => 'Khach QA',
            'phone' => '0900000000',
            'address' => 'So 1 duong Test',
            'note' => null,
            'items' => [],
            'combos' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function payload(Combo $combo, ?int $locationId): array
    {
        $payload = $this->basePayload();
        $payload['combos'] = [[
            'combo_id' => $combo->id,
            'quantity' => 1,
            'start' => $this->start,
            'end' => $this->end,
            'location_id' => $locationId,
        ]];

        return $payload;
    }

    /** @param  array<int, int>  $stocks  [locationId => quantity] */
    private function product(string $name, array $stocks): Product
    {
        $p = Product::create([
            'category_id' => $this->category->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => max(5, array_sum($stocks)),
            'status' => 'active',
        ]);

        foreach ($stocks as $locationId => $qty) {
            $p->serviceLocations()->attach($locationId, ['quantity' => $qty, 'buffer_days' => 0]);
        }

        return $p;
    }
}
