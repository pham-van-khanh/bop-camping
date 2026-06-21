<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['phone' => '0976544370'],
            [
                'name'     => 'Admin',
                'phone'    => '0976544370',
                'password' => Hash::make('admin'),
                'is_admin' => true,
            ]
        );
    }
}
