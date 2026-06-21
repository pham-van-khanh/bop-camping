<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Lều cắm trại',    'description' => 'Lều 2-8 người, chống nước, siêu nhẹ'],
            ['name' => 'Túi ngủ',          'description' => 'Túi ngủ mùa hè và 3 mùa'],
            ['name' => 'Bếp & Nấu ăn',    'description' => 'Bếp gas mini, nồi titanium, bộ nấu ăn'],
            ['name' => 'Bàn ghế dã ngoại', 'description' => 'Ghế gấp, bàn gấp nhẹ tiện lợi'],
            ['name' => 'Đèn & Chiếu sáng', 'description' => 'Đèn pin, đèn lều, đèn sạc'],
            ['name' => 'Ba lô & Túi',      'description' => 'Ba lô trekking 30-65L'],
        ];

        foreach ($categories as $data) {
            Category::create([
                'name'        => $data['name'],
                'slug'        => Str::slug($data['name']),
                'description' => $data['description'],
            ]);
        }
    }
}
