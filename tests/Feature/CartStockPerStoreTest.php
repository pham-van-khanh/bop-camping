<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * bopcamping-3o0x — giỏ phải nói tồn của ĐÚNG kho khách đã chọn.
 *
 * Trước đây `/gio-thue/lam-tuoi` tính tồn mà không truyền kho, nên luôn trả "best" = max qua
 * các kho đang mở. Khách chọn Hà Nội (Vinh 3 / HN 1) thì giỏ vẫn hiện 3, tới lúc bấm
 * "Đặt yêu cầu thuê" mới bị StoreResolver chặn — thất bại ở bước cuối, sau khi đã điền hết.
 *
 * Cùng họ với bopcamping-kvcc (trang danh sách) và bopcamping-jyxi (bỏ tồn toàn cục).
 */
class CartStockPerStoreTest extends TestCase
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
        $this->hanoi = ServiceLocation::create(['name' => 'Ha Noi', 'area' => 'Noi thanh', 'status' => 'open', 'sort_order' => 2]);
        $this->category = Category::create(['name' => 'Do camping', 'slug' => 'do-camping-3o0x']);

        $this->start = Carbon::today()->addDays(3)->toDateString();
        $this->end = Carbon::today()->addDays(5)->toDateString();
    }

    /** @param  array<int, int>  $stocks */
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

        foreach ($stocks as $locId => $qty) {
            $p->serviceLocations()->attach($locId, ['quantity' => $qty, 'buffer_days' => 0]);
        }

        return $p;
    }

    /** @return array<string, int> */
    private function stock(string $query): array
    {
        return $this->getJson("/gio-thue/lam-tuoi?{$query}")->json('stock') ?? [];
    }

    private function pr(Product $p): string
    {
        return "pr[]={$p->id}:{$this->start}:{$this->end}";
    }

    /** CA CHÍNH: chọn kho ít hàng -> giỏ phải nói số của kho ĐÓ, không phải max. */
    public function test_chon_kho_thi_gio_noi_ton_cua_kho_do(): void
    {
        $p = $this->product('Leu lech', [$this->vinh->id => 3, $this->hanoi->id => 1]);
        $key = "p:{$p->id}:{$this->start}:{$this->end}";

        $this->assertSame(1, $this->stock($this->pr($p)."&loc={$this->hanoi->id}")[$key]);
        $this->assertSame(3, $this->stock($this->pr($p)."&loc={$this->vinh->id}")[$key]);
    }

    /** Chưa chọn kho -> giữ nguyên "best" (StoreResolver sẽ tự chọn kho rộng nhất). */
    public function test_chua_chon_kho_thi_van_la_max_qua_cac_kho(): void
    {
        $p = $this->product('Leu chua chon', [$this->vinh->id => 3, $this->hanoi->id => 1]);
        $key = "p:{$p->id}:{$this->start}:{$this->end}";

        $this->assertSame(3, $this->stock($this->pr($p))[$key]);
    }

    /** Combo cũng phải theo kho đã chọn, không chỉ sản phẩm lẻ. */
    public function test_combo_cung_theo_kho_da_chon(): void
    {
        $a = $this->product('Leu combo', [$this->vinh->id => 4, $this->hanoi->id => 1]);
        $b = $this->product('Tui ngu combo', [$this->vinh->id => 4, $this->hanoi->id => 4]);

        $combo = Combo::create([
            'name' => 'Combo 3o0x',
            'slug' => 'combo-3o0x',
            'combo_price' => 150000,
            'deposit' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $combo->items()->create(['product_id' => $a->id, 'quantity' => 1]);
        $combo->items()->create(['product_id' => $b->id, 'quantity' => 1]);
        $combo->serviceLocations()->sync([$this->vinh->id, $this->hanoi->id]);

        $q = "cr[]={$combo->id}:{$this->start}:{$this->end}";
        $key = "c:{$combo->id}:{$this->start}:{$this->end}";

        $this->assertSame(1, $this->stock($q."&loc={$this->hanoi->id}")[$key], 'Hà Nội chỉ đủ 1 bộ');
        $this->assertSame(4, $this->stock($q."&loc={$this->vinh->id}")[$key], 'Vinh đủ 4 bộ');
    }

    /**
     * Giỏ TUYỆT ĐỐI không được vỡ vì một query param sai. Thà hiển thị rộng rãi (best) còn
     * hơn trả lỗi hoặc chặn oan khách.
     *
     * @dataProvider locRacProvider
     */
    public function test_tham_so_loc_rac_thi_ve_best_chu_khong_vo(string $loc): void
    {
        $p = $this->product('Leu loc rac', [$this->vinh->id => 3, $this->hanoi->id => 1]);
        $key = "p:{$p->id}:{$this->start}:{$this->end}";

        $res = $this->getJson("/gio-thue/lam-tuoi?{$this->pr($p)}&loc={$loc}");

        $res->assertOk();
        $this->assertSame(3, $res->json('stock')[$key]);
    }

    /** @return array<string, array{0: string}> */
    public static function locRacProvider(): array
    {
        return [
            'chữ' => ['abc'],
            'số âm' => ['-1'],
            'số 0' => ['0'],
            'rỗng' => [''],
            'id không tồn tại' => ['999999'],
            'thử SQL' => ['1 OR 1=1'],
        ];
    }

    /**
     * Tham số rác mà ép kiểu ra ID THẬT thì phải bị từ chối, không được âm thầm chọn kho.
     *
     * Ca này cần thiết vì các giá trị rác ở provider trên đều ép ra 0 hoặc ra Vinh — mà Vinh
     * cũng chính là "best", nên hai đường ra trùng nhau và test không phân biệt được. Ở đây
     * dùng id của HÀ NỘI (kho ít hàng) nên hai đường ra khác hẳn: 3 (từ chối, về best) hay
     * 1 (nhận bừa).
     */
    public function test_loc_rac_ep_kieu_ra_id_that_van_bi_tu_choi(): void
    {
        $p = $this->product('Leu ep kieu', [$this->vinh->id => 3, $this->hanoi->id => 1]);
        $key = "p:{$p->id}:{$this->start}:{$this->end}";

        foreach (["{$this->hanoi->id}abc", "{$this->hanoi->id}.9", "0{$this->hanoi->id}x"] as $rac) {
            $this->assertSame(
                3,
                $this->stock($this->pr($p).'&loc='.urlencode($rac))[$key],
                "loc='{$rac}' phải bị từ chối và về best, không được nhận bừa thành Hà Nội",
            );
        }
    }

    /**
     * Id hợp lệ nhưng có khoảng trắng thừa VẪN được nhận — middleware TrimStrings của Laravel
     * cắt trước khi tới controller. Ghi lại vì lúc viết test tôi tưởng nó bị từ chối; đây là
     * hành vi đúng của framework, không phải lỗ hổng.
     */
    public function test_id_hop_le_kem_khoang_trang_van_duoc_nhan(): void
    {
        $p = $this->product('Leu khoang trang', [$this->vinh->id => 3, $this->hanoi->id => 1]);
        $key = "p:{$p->id}:{$this->start}:{$this->end}";

        $this->assertSame(1, $this->stock($this->pr($p).'&loc='.urlencode(" {$this->hanoi->id} "))[$key]);
    }

    /** Kho chưa mở (coming) không được dùng — coi như chưa chọn. */
    public function test_kho_chua_mo_thi_coi_nhu_chua_chon(): void
    {
        $coming = ServiceLocation::create(['name' => 'Da Nang', 'area' => 'MT', 'status' => 'coming', 'sort_order' => 3]);
        $p = $this->product('Leu kho coming', [$this->vinh->id => 3, $coming->id => 99]);
        $key = "p:{$p->id}:{$this->start}:{$this->end}";

        $this->assertSame(3, $this->stock($this->pr($p)."&loc={$coming->id}")[$key]);
    }

    /**
     * BẤT BIẾN quan trọng nhất: số giỏ hiện phải bằng đúng số checkout cho phép. Đây chính là
     * điều bopcamping-jyxi đã chốt cho trường hợp chưa chọn kho; nay phải đúng cả khi ĐÃ chọn.
     */
    public function test_so_gio_hien_bang_so_checkout_cho_phep(): void
    {
        $p = $this->product('Leu bat bien', [$this->vinh->id => 3, $this->hanoi->id => 1]);
        $key = "p:{$p->id}:{$this->start}:{$this->end}";

        $gio = $this->stock($this->pr($p)."&loc={$this->hanoi->id}")[$key];

        // Đặt đúng số giỏ nói -> phải qua. Đặt hơn 1 -> phải bị chặn.
        $this->post('/dat-hang', $this->payload($p, $gio))->assertSessionHasNoErrors();
        $this->post('/dat-hang', $this->payload($p, $gio + 1))->assertSessionHasErrors();
    }

    /** @return array<string, mixed> */
    private function payload(Product $p, int $qty): array
    {
        return [
            'name' => 'Khach 3o0x',
            'phone' => '0900000000',
            'address' => 'So 1 Test',
            'items' => [[
                'product_id' => $p->id,
                'quantity' => $qty,
                'start' => $this->start,
                'end' => $this->end,
                'location_id' => $this->hanoi->id,
            ]],
        ];
    }
}
