<?php

namespace Database\Seeders;

use App\Models\CampingSpot;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Database\Seeder;

class CampingSpotSeeder extends Seeder
{
    /** Vị trí phục vụ + điểm cắm trại gợi ý (gom theo tỉnh/thành). */
    public function run(): void
    {
        $vinh = ServiceLocation::firstOrCreate(
            ['name' => 'Vinh'],
            ['area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1],
        );
        $hanoi = ServiceLocation::firstOrCreate(
            ['name' => 'Hà Nội'],
            ['area' => 'Nội thành', 'status' => 'open', 'sort_order' => 2],
        );

        $spots = [
            // [name, province, district, terrain_tag, description, season_from, season_to, nearest_id, is_suggested]
            ['Đồng Cao', 'Bắc Giang', null, 'Đồi cỏ', 'Sống lưng đồi cỏ thoáng đãng, săn mây và ngắm hoàng hôn cực đẹp.', 10, 4, null, false],
            ['Hồ Đồng Mô', 'Hà Nội', 'Ba Vì', 'Ven hồ', 'Bãi cỏ ven hồ rộng, gần Hà Nội, hợp cắm trại cuối tuần cùng gia đình.', null, null, $hanoi->id, false],
            ['Núi Hàm Lợn', 'Hà Nội', 'Sóc Sơn', 'Rừng núi', 'Nóc nhà Sóc Sơn, đường mòn dễ đi, hồ nước trong ngay chân núi.', 9, 12, $hanoi->id, true],
            ['Núi Quyết', 'Nghệ An', 'Vinh', 'Đồi view sông', 'Ngay trong thành phố Vinh, nhìn ra sông Lam và cầu Bến Thủy.', 9, 4, $vinh->id, false],
            ['Biển Cửa Lò', 'Nghệ An', 'Cửa Lò', 'Bãi biển', 'Cắm trại sát biển, đêm đốt lửa nghe sóng, sáng đón bình minh.', 4, 8, $vinh->id, true],
            ['Đồi cát Bàu Trắng', 'Bình Thuận', null, 'Đồi cát', 'Sa mạc cát trắng cạnh hồ sen, khung cảnh lạ cho ảnh để đời.', 11, 4, null, false],
            ['Hồ Tà Đùng', 'Đắk Nông', null, 'Hồ trên núi', 'Vịnh Hạ Long của Tây Nguyên với hàng trăm đảo nhỏ giữa hồ xanh.', 11, 4, null, false],
            ['Núi Chứa Chan', 'Đồng Nai', null, 'Đỉnh núi', 'Săn mây trên đỉnh cao thứ nhì Nam Bộ, gần Sài Gòn.', 11, 3, null, false],
            ['Hồ Dầu Tiếng', 'Tây Ninh', null, 'Ven hồ', 'Hồ nước ngọt rộng, bãi cắm trại thoáng, hoàng hôn phản chiếu mặt nước.', 11, 5, null, false],
        ];

        foreach ($spots as $i => [$name, $province, $district, $tag, $desc, $from, $to, $nearId, $suggested]) {
            CampingSpot::firstOrCreate(
                ['name' => $name],
                [
                    'province' => $province,
                    'district' => $district,
                    'terrain_tag' => $tag,
                    'description' => $desc,
                    'map_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($name.' '.$province),
                    'best_season_from' => $from,
                    'best_season_to' => $to,
                    'nearest_service_location_id' => $nearId,
                    'is_suggested' => $suggested,
                    'sort_order' => $i + 1,
                ],
            );
        }

        // Gán tất cả vị trí đang mở cho sản phẩm chưa có vị trí phục vụ nào.
        // (ProductSeeder chạy trước nên lúc đó vị trí chưa tồn tại để link.)
        $openIds = ServiceLocation::open()->pluck('id')->all();
        if ($openIds) {
            Product::doesntHave('serviceLocations')->each(
                fn (Product $p) => $p->serviceLocations()->sync($openIds)
            );
        }
    }
}
