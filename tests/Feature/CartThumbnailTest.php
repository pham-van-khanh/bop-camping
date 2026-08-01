<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * bopcamping-2ah2 — giỏ hàng phải có ảnh sản phẩm.
 *
 * Ảnh do SERVER cấp qua /gio-thue/lam-tuoi chứ không phải trang sản phẩm nhét vào lúc thêm
 * giỏ. Nhờ vậy giỏ CŨ của khách (lưu trước khi có tính năng này) cũng có ảnh ngay ở lần mở
 * kế tiếp, và admin đổi ảnh thì giỏ cập nhật theo.
 */
class CartThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private function product(string $name, ?string $thumbnail): Product
    {
        return Product::create([
            'category_id' => $this->category->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => 2,
            'status' => 'active',
            'thumbnail' => $thumbnail,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create(['name' => 'Do camping', 'slug' => 'do-camping-9wq3']);
    }

    public function test_san_pham_co_anh_thi_gio_nhan_duoc_url(): void
    {
        $p = $this->product('Leu co anh', 'products/leu.jpg');

        $image = $this->getJson("/gio-thue/lam-tuoi?ids[]={$p->id}")->json("products.{$p->id}.image");

        $this->assertIsString($image);
        $this->assertStringContainsString('leu.jpg', $image);
    }

    /** Sản phẩm chưa có ảnh -> null, FE vẽ ô gradient như cũ chứ không vỡ. */
    public function test_san_pham_khong_co_anh_thi_tra_null(): void
    {
        $p = $this->product('Leu khong anh', null);

        $this->getJson("/gio-thue/lam-tuoi?ids[]={$p->id}")
            ->assertOk()
            ->assertJsonPath("products.{$p->id}.image", null);
    }

    public function test_combo_lay_anh_dau_tien(): void
    {
        $p = $this->product('Leu trong combo', 'products/leu.jpg');

        $combo = Combo::create([
            'name' => 'Combo co anh',
            'slug' => 'combo-co-anh',
            'combo_price' => 150000,
            'deposit' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $combo->items()->create(['product_id' => $p->id, 'quantity' => 1]);
        $combo->images()->create(['path' => 'combos/combo-1.jpg', 'sort_order' => 1]);
        $combo->images()->create(['path' => 'combos/combo-2.jpg', 'sort_order' => 2]);

        $image = $this->getJson("/gio-thue/lam-tuoi?combo_ids[]={$combo->id}")->json("combos.{$combo->id}.image");

        $this->assertStringContainsString('combo-1.jpg', $image, 'phải lấy ảnh đầu tiên theo sort_order');
    }

    public function test_combo_khong_co_anh_thi_tra_null(): void
    {
        $p = $this->product('Leu combo khong anh', null);

        $combo = Combo::create([
            'name' => 'Combo khong anh',
            'slug' => 'combo-khong-anh',
            'combo_price' => 150000,
            'deposit' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $combo->items()->create(['product_id' => $p->id, 'quantity' => 1]);

        $this->getJson("/gio-thue/lam-tuoi?combo_ids[]={$combo->id}")
            ->assertOk()
            ->assertJsonPath("combos.{$combo->id}.image", null);
    }

    /**
     * Thêm ảnh KHÔNG được làm hỏng phần tồn kho trên cùng endpoint — hai thứ đi chung một
     * response, sửa cái này mà vỡ cái kia thì giỏ nói sai số lượng.
     */
    public function test_them_anh_khong_lam_hong_phan_ton_kho(): void
    {
        $p = $this->product('Leu vua anh vua ton', 'products/leu.jpg');
        $start = Carbon::today()->addDays(3)->toDateString();
        $end = Carbon::today()->addDays(5)->toDateString();

        $res = $this->getJson("/gio-thue/lam-tuoi?ids[]={$p->id}&pr[]={$p->id}:{$start}:{$end}");

        $res->assertOk();
        $this->assertStringContainsString('leu.jpg', $res->json("products.{$p->id}.image"));
        $this->assertSame(2, $res->json("stock.p:{$p->id}:{$start}:{$end}"));
    }
}
