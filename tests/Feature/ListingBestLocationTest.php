<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * bopcamping-kvcc — badge "Còn N" khi khách CHƯA chọn kho.
 *
 * Con số đó là max qua các kho đang mở (quyết định #2, artifacts/prd_date_first_booking.md).
 * Khách thấy "Còn 3 bộ", thêm 3 vào giỏ, rồi tới checkout mới biết KHÔNG kho nào có đủ 3 —
 * vì StoreResolver buộc cả giỏ nằm trong MỘT kho. Thất bại muộn và khó hiểu.
 *
 * Nay server gửi thêm `available_at` = tên kho giữ con số đó, và CHỈ gửi khi các kho lệch
 * nhau (bằng nhau thì con số đúng ở mọi kho, thêm tên chỉ làm rối).
 */
class ListingBestLocationTest extends TestCase
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
        $this->category = Category::create(['name' => 'Do camping', 'slug' => 'do-camping-kvcc']);

        $this->start = Carbon::today()->addDays(3)->toDateString();
        $this->end = Carbon::today()->addDays(5)->toDateString();
    }

    /** @param  array<int, int>  $stocks  [location_id => quantity] */
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

    /** @return array<int, array<string, mixed>> */
    private function listing(?string $viTri = null): array
    {
        $url = "/thiet-bi?cat=do-camping-kvcc&start={$this->start}&end={$this->end}";
        if ($viTri !== null) {
            $url .= "&vi-tri={$viTri}";
        }

        return $this->get($url)->original->getData()['page']['props']['products'];
    }

    /** CA CHÍNH: hai kho lệch nhau -> phải nói con số đó ở kho nào. */
    public function test_kho_lech_nhau_thi_noi_ro_ten_kho(): void
    {
        $this->product('Leu lech', [$this->vinh->id => 3, $this->hanoi->id => 1]);

        $row = $this->listing()[0];

        $this->assertSame(3, $row['available'], 'badge vẫn là max qua các kho');
        $this->assertSame('Vinh', $row['available_at'], 'phải chỉ ra kho đang giữ số 3');
    }

    /** Hai kho BẰNG nhau -> con số đúng ở mọi kho, thêm tên chỉ làm rối. */
    public function test_kho_bang_nhau_thi_khong_noi_ten_kho(): void
    {
        $this->product('Leu deu', [$this->vinh->id => 2, $this->hanoi->id => 2]);

        $row = $this->listing()[0];

        $this->assertSame(2, $row['available']);
        $this->assertNull($row['available_at']);
    }

    /** Chỉ phục vụ một kho -> không có gì để so, đừng thêm tên. */
    public function test_chi_mot_kho_thi_khong_noi_ten_kho(): void
    {
        $this->product('Leu mot kho', [$this->vinh->id => 4]);

        $row = $this->listing()[0];

        $this->assertSame(4, $row['available']);
        $this->assertNull($row['available_at']);
    }

    /** Khách ĐÃ chọn kho -> con số đã là của đúng kho đó, không cần chỉ tên. */
    public function test_da_chon_kho_thi_khong_noi_ten_kho(): void
    {
        $this->product('Leu lech 2', [$this->vinh->id => 3, $this->hanoi->id => 1]);

        $row = $this->listing('ha-noi')[0];

        $this->assertSame(1, $row['available'], 'đã chọn Hà Nội thì phải là số của Hà Nội');
        $this->assertNull($row['available_at']);
    }

    /** Chưa chọn NGÀY -> chưa lọc gì, không có con số nào để giải thích. */
    public function test_chua_chon_ngay_thi_khong_co_ten_kho(): void
    {
        $this->product('Leu chua chon ngay', [$this->vinh->id => 3, $this->hanoi->id => 1]);

        $row = $this->get('/thiet-bi?cat=do-camping-kvcc')->original->getData()['page']['props']['products'][0];

        $this->assertNull($row['available']);
        $this->assertNull($row['available_at']);
    }

    /** Món chưa gắn kho nào (dữ liệu cũ, dùng tồn toàn cục) -> không có kho để chỉ. */
    public function test_san_pham_khong_gan_kho_thi_khong_co_ten_kho(): void
    {
        $this->product('Leu khong kho', []);

        $row = $this->listing()[0];

        $this->assertNull($row['available_at']);
    }

    /** Kho đóng (coming) không được tính vào phép so lệch. */
    public function test_kho_chua_mo_khong_tinh(): void
    {
        $coming = ServiceLocation::create(['name' => 'Da Nang', 'area' => 'Mien Trung', 'status' => 'coming', 'sort_order' => 3]);
        $this->product('Leu co kho coming', [$this->vinh->id => 2, $coming->id => 99]);

        $row = $this->listing()[0];

        $this->assertSame(2, $row['available'], 'kho chưa mở không được cộng vào');
        $this->assertNull($row['available_at'], 'chỉ có 1 kho đang mở -> không so được');
    }
}
