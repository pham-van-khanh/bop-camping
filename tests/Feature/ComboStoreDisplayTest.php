<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * bopcamping-gmup — trang combo hiện ĐỦ các cơ sở, khoá nơi không có combo.
 *
 * Chủ shop chốt: mỗi combo dựng ở một địa điểm cố định. Trước đây trang combo chỉ có một
 * dòng chữ "Cho thuê tại: Vinh" — muốn biết Hà Nội có hay không thì phải tự suy. Nay hiện
 * cả hai, nơi không có thì khoá.
 *
 * CỐ Ý không ép "đúng một cơ sở" trong dữ liệu: hiện đã có combo gắn 2 cơ sở (chủ shop tự
 * chỉnh trong admin). Prop này đúng cho cả hai trường hợp.
 */
class ComboStoreDisplayTest extends TestCase
{
    use RefreshDatabase;

    private ServiceLocation $vinh;

    private ServiceLocation $hanoi;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghe An', 'status' => 'open', 'sort_order' => 1]);
        $this->hanoi = ServiceLocation::create(['name' => 'Ha Noi', 'area' => 'Noi thanh', 'status' => 'open', 'sort_order' => 2]);
        $this->category = Category::create(['name' => 'Do camping', 'slug' => 'do-camping-fu4c']);
    }

    /** @param  array<int, int>  $comboLocationIds */
    private function combo(string $name, array $comboLocationIds): Combo
    {
        $p = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Leu '.$name,
            'slug' => Str::slug('leu-'.$name).'-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => 4,
            'status' => 'active',
        ]);
        $p->serviceLocations()->attach($this->vinh->id, ['quantity' => 2, 'buffer_days' => 0]);
        $p->serviceLocations()->attach($this->hanoi->id, ['quantity' => 2, 'buffer_days' => 0]);

        $combo = Combo::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'combo_price' => 150000,
            'deposit' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $combo->items()->create(['product_id' => $p->id, 'quantity' => 1]);
        $combo->serviceLocations()->sync($comboLocationIds);

        return $combo;
    }

    /** @return array<int, array<string, mixed>> */
    private function stores(Combo $combo): array
    {
        return $this->get("/combos/{$combo->slug}")->original->getData()['page']['props']['stores'];
    }

    /** CA CHÍNH: combo chỉ ở Vinh -> hiện cả hai, Hà Nội bị khoá. */
    public function test_combo_mot_co_so_thi_khoa_co_so_con_lai(): void
    {
        $stores = $this->stores($this->combo('Combo chi o Vinh', [$this->vinh->id]));

        $this->assertCount(2, $stores, 'phải hiện ĐỦ cơ sở đang mở, không chỉ nơi có combo');
        $this->assertSame(['Vinh', 'Ha Noi'], array_column($stores, 'name'));
        $this->assertTrue($stores[0]['served']);
        $this->assertFalse($stores[1]['served'], 'Hà Nội không có combo này -> phải bị khoá');
    }

    /** Combo gắn 2 cơ sở (dữ liệu đang có thật) -> không khoá cái nào. */
    public function test_combo_hai_co_so_thi_khong_khoa_cai_nao(): void
    {
        $stores = $this->stores($this->combo('Combo ca hai noi', [$this->vinh->id, $this->hanoi->id]));

        $this->assertCount(2, $stores);
        $this->assertTrue($stores[0]['served']);
        $this->assertTrue($stores[1]['served']);
    }

    /** Combo chưa gắn cơ sở nào -> khoá hết, khách thấy ngay là chưa bán ở đâu. */
    public function test_combo_khong_gan_co_so_nao_thi_khoa_het(): void
    {
        $stores = $this->stores($this->combo('Combo chua gan', []));

        $this->assertCount(2, $stores);
        $this->assertFalse($stores[0]['served']);
        $this->assertFalse($stores[1]['served']);
    }

    /** Cơ sở chưa mở (coming) KHÔNG được hiện — khách không đặt được ở đó. */
    public function test_co_so_chua_mo_khong_hien(): void
    {
        $coming = ServiceLocation::create(['name' => 'Da Nang', 'area' => 'MT', 'status' => 'coming', 'sort_order' => 3]);
        $combo = $this->combo('Combo co kho coming', [$this->vinh->id, $coming->id]);

        $names = array_column($this->stores($combo), 'name');

        $this->assertNotContains('Da Nang', $names);
        $this->assertCount(2, $names);
    }

    /** Thứ tự phải theo sort_order để hai trang không hiện khác nhau. */
    public function test_giu_dung_thu_tu_sort_order(): void
    {
        $this->hanoi->update(['sort_order' => 0]);

        $names = array_column($this->stores($this->combo('Combo thu tu', [$this->vinh->id])), 'name');

        $this->assertSame(['Ha Noi', 'Vinh'], $names);
    }
}
