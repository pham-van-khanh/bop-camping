<?php

namespace Database\Seeders;

use App\Models\ServiceLocation;
use Illuminate\Database\Seeder;

/**
 * Cơ sở phục vụ (bopcamping-ry4u).
 *
 * Tách riêng khỏi CampingSpotSeeder để chạy được TRƯỚC ProductSeeder — sản phẩm cần cơ sở
 * tồn tại rồi mới gắn tồn kho theo kho được. CampingSpotSeeder vẫn dùng firstOrCreate nên
 * chạy sau sẽ tìm thấy hai cơ sở này chứ không tạo trùng.
 */
class ServiceLocationSeeder extends Seeder
{
    public function run(): void
    {
        ServiceLocation::firstOrCreate(
            ['name' => 'Vinh'],
            ['area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1],
        );

        ServiceLocation::firstOrCreate(
            ['name' => 'Hà Nội'],
            ['area' => 'Nội thành', 'status' => 'open', 'sort_order' => 2],
        );
    }
}
