<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $phone = env('ADMIN_PHONE', '0976544370');
        $email = env('ADMIN_EMAIL', 'admin@bopcamping.local');
        $password = env('ADMIN_PASSWORD');

        // Production BẮT BUỘC đặt ADMIN_PASSWORD trong .env — không cho dùng mật khẩu mặc định.
        if (! $password) {
            if (app()->environment('production')) {
                throw new \RuntimeException(
                    'ADMIN_PASSWORD chưa được đặt trong .env — bắt buộc khi seed admin ở production.'
                );
            }
            $password = 'admin'; // chỉ dùng cho dev/local
        }

        User::updateOrCreate(
            ['phone' => $phone],
            [
                'name' => 'Admin',
                'phone' => $phone,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => Hash::make($password),
                'is_admin' => true,
            ]
        );
    }
}
