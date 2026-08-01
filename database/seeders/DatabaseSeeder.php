<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    /**
     * ⚠️ SEEDER CHỈ DÙNG CHO DEV. Production đã có dữ liệu thật (từ 08/2026) — KHÔNG seed.
     *
     * An toàn hiện tại KHÔNG phải nhờ may:
     * - `scripts/deploy.sh` chỉ chạy `migrate --force`, KHÔNG bao giờ có `--seed`.
     * - Chạy nhầm `db:seed` (hoặc `--class=ProductSeeder`, `--class=ComboSeeder`) trên DB đã
     *   có dữ liệu sẽ CHẾT ở unique constraint (`users.email`, `products.slug`, `combos.slug`)
     *   và KHÔNG đổi một dòng nào. Đã kiểm thật 2026-08-01.
     *
     * VÌ VẬY: KHÔNG biến các seeder này thành idempotent (firstOrCreate / updateOrCreate).
     * Chết ồn ào là TÍNH NĂNG, không phải lỗi — làm nó chạy êm nghĩa là nó cũng chạy êm trên
     * production, và người sau sẽ tưởng seed là thao tác an toàn.
     */
    public function run(): void
    {
        // Test User chỉ dành cho dev/local — KHÔNG tạo ở production.
        if (app()->environment('local', 'testing')) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        $this->call([
            CategorySeeder::class,
            // Cơ sở phải có TRƯỚC sản phẩm: ProductSeeder gắn tồn kho theo kho (bopcamping-ry4u).
            ServiceLocationSeeder::class,
            ProductSeeder::class,
            ComboSeeder::class,
            AdminUserSeeder::class,
            CampingSpotSeeder::class,
            BannerSeeder::class,
            FaqSeeder::class,
            SiteSettingSeeder::class,
            StaticPageSeeder::class,
            DurationDiscountTierSeeder::class,
        ]);
    }
}
