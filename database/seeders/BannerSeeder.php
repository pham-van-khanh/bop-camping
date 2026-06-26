<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    /** 7 ảnh hero hiện có -> banner quản lý được (giữ slideshow chạy như cũ). */
    public function run(): void
    {
        $hero = [
            ['/images/album/beach-night-tent.jpg', 'Lều sáng đèn bên biển đêm'],
            ['/images/album/forest-camp-aerial.jpg', 'Trại giữa rừng thông'],
            ['/images/album/cliff-turquoise.jpg', 'Vách đá bên biển xanh'],
            ['/images/album/cloud-sea-sunrise.jpg', 'Biển mây đón bình minh'],
            ['/images/album/beach-sunset-relax.jpg', 'Hoàng hôn trên bãi biển'],
            ['/images/album/bay-overview.jpg', 'Toàn cảnh vịnh'],
            ['/images/album/tent-interior-night.jpg', 'Đêm ấm trong lều'],
        ];

        foreach ($hero as $i => [$image, $title]) {
            Banner::firstOrCreate(
                ['placement' => 'hero', 'image' => $image],
                ['title' => $title, 'sort_order' => $i + 1, 'is_active' => true],
            );
        }
    }
}
