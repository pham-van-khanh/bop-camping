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
            ProductSeeder::class,
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
