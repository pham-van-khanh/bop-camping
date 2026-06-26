<?php

namespace Database\Seeders;

use App\Models\CampingSpot;
use App\Models\ServiceLocation;
use Illuminate\Database\Seeder;

class CampingSpotSeeder extends Seeder
{
    /** Vị trí phục vụ + điểm cắm trại gợi ý (theo thiết kế Cẩm nang cắm trại). */
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
            // Miền Bắc
            ['Đồng Cao', 'mien_bac', 'Bắc Giang', null, 'Đồi cỏ', 'Sống lưng đồi cỏ thoáng đãng, săn mây và ngắm hoàng hôn cực đẹp.', 10, 4, null, null, false],
            ['Hồ Đồng Mô', 'mien_bac', 'Hà Nội', 'Ba Vì', 'Ven hồ', 'Bãi cỏ ven hồ rộng, gần Hà Nội, hợp cắm trại cuối tuần cùng gia đình.', null, null, $hanoi->id, '60 phút', false],
            ['Núi Hàm Lợn', 'mien_bac', 'Hà Nội', 'Sóc Sơn', 'Rừng núi', 'Nóc nhà Sóc Sơn, đường mòn dễ đi, hồ nước trong ngay chân núi.', 9, 12, $hanoi->id, '50 phút', true],

            // Miền Trung
            ['Núi Quyết', 'mien_trung', 'Vinh', 'Nghệ An', 'Đồi view sông', 'Ngay trong thành phố Vinh, nhìn ra sông Lam và cầu Bến Thủy.', 9, 4, $vinh->id, '15 phút', false],
            ['Biển Cửa Lò', 'mien_trung', 'Vinh', 'Nghệ An', 'Bãi biển', 'Cắm trại sát biển, đêm đốt lửa nghe sóng, sáng đón bình minh.', 4, 8, $vinh->id, '40 phút', true],
            ['Đồi cát Bàu Trắng', 'mien_trung', 'Bình Thuận', null, 'Đồi cát', 'Sa mạc cát trắng cạnh hồ sen, khung cảnh lạ cho ảnh để đời.', 11, 4, null, null, false],

            // Miền Nam & Tây Nguyên
            ['Hồ Tà Đùng', 'mien_nam_tay_nguyen', 'Đắk Nông', null, 'Hồ trên núi', 'Vịnh Hạ Long của Tây Nguyên với hàng trăm đảo nhỏ giữa hồ xanh.', 11, 4, null, null, false],
            ['Núi Chứa Chan', 'mien_nam_tay_nguyen', 'Đồng Nai', null, 'Đỉnh núi', 'Săn mây trên đỉnh cao thứ nhì Nam Bộ, gần Sài Gòn.', 11, 3, null, null, false],
            ['Hồ Dầu Tiếng', 'mien_nam_tay_nguyen', 'Tây Ninh', null, 'Ven hồ', 'Hồ nước ngọt rộng, bãi cắm trại thoáng, hoàng hôn phản chiếu mặt nước.', 11, 5, null, null, false],
        ];

        foreach ($spots as $i => [$name, $region, $province, $district, $tag, $desc, $from, $to, $nearId, $time, $suggested]) {
            CampingSpot::firstOrCreate(
                ['name' => $name],
                [
                    'region' => $region,
                    'province' => $province,
                    'district' => $district,
                    'terrain_tag' => $tag,
                    'description' => $desc,
                    'best_season_from' => $from,
                    'best_season_to' => $to,
                    'nearest_service_location_id' => $nearId,
                    'travel_time' => $time,
                    'is_suggested' => $suggested,
                    'sort_order' => $i + 1,
                ],
            );
        }
    }
}
