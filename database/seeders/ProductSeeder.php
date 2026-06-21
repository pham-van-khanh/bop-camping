<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // Lều cắm trại
            [
                'category' => 'Lều cắm trại',
                'name'     => 'Lều Naturehike Cloud-Up 2',
                'desc'     => 'Lều 2 người siêu nhẹ 1.5kg, chịu gió cấp 8, chống nước 3000mm. Phù hợp trekking và cắm trại rừng núi.',
                'price'    => 120000,
                'qty'      => 3,
                'deposit'  => 300000,
            ],
            [
                'category' => 'Lều cắm trại',
                'name'     => 'Lều Naturehike Taga 3 người',
                'desc'     => 'Lều gia đình 3 người, dựng nhanh 5 phút, cửa vào rộng, thông gió tốt.',
                'price'    => 160000,
                'qty'      => 2,
                'deposit'  => 400000,
            ],
            // Túi ngủ
            [
                'category' => 'Túi ngủ',
                'name'     => 'Túi ngủ Naturehike CW400',
                'desc'     => 'Phù hợp nhiệt độ 5–15°C, chất liệu polyester, nhẹ 0.9kg. Lý tưởng cho 3 mùa.',
                'price'    => 60000,
                'qty'      => 5,
                'deposit'  => 150000,
            ],
            [
                'category' => 'Túi ngủ',
                'name'     => 'Túi ngủ lông vũ MSR',
                'desc'     => 'Lông vũ 700-fill, chịu đến 0°C, cực nhẹ 0.7kg, dành cho trekking cao núi.',
                'price'    => 90000,
                'qty'      => 2,
                'deposit'  => 250000,
            ],
            // Bếp & Nấu ăn
            [
                'category' => 'Bếp & Nấu ăn',
                'name'     => 'Bếp gas mini MSR Pocket Rocket 2',
                'desc'     => 'Bếp siêu nhẹ 73g, đầu đốt piezo, phù hợp gas van loại nhỏ. Kèm 1 van gas đầy.',
                'price'    => 50000,
                'qty'      => 4,
                'deposit'  => 200000,
            ],
            [
                'category' => 'Bếp & Nấu ăn',
                'name'     => 'Bộ nồi Titanium 3 món',
                'desc'     => 'Bộ nồi 3 chiếc (0.8L + 1.3L + 1.9L) titanium siêu nhẹ. Kèm túi đựng.',
                'price'    => 45000,
                'qty'      => 3,
                'deposit'  => 180000,
            ],
            // Bàn ghế
            [
                'category' => 'Bàn ghế dã ngoại',
                'name'     => 'Ghế gấp Helinox Chair One',
                'desc'     => 'Ghế camp siêu nhẹ 960g, tải trọng 120kg, gấp gọn vào túi nhỏ.',
                'price'    => 40000,
                'qty'      => 6,
                'deposit'  => 150000,
            ],
            // Đèn
            [
                'category' => 'Đèn & Chiếu sáng',
                'name'     => 'Đèn lều Black Diamond Moji+',
                'desc'     => 'Đèn lều 100 lumens, pin AAA x3, chế độ dim, treo hoặc đặt đều được.',
                'price'    => 35000,
                'qty'      => 5,
                'deposit'  => 100000,
            ],
            // Ba lô
            [
                'category' => 'Ba lô & Túi',
                'name'     => 'Ba lô trekking Osprey Talon 44L',
                'desc'     => 'Ba lô 44L khung nhôm, hệ thống lưng thoáng khí AirSpeed, túi hip-belt, phù hợp leo núi 2-3 ngày.',
                'price'    => 100000,
                'qty'      => 2,
                'deposit'  => 300000,
            ],
        ];

        foreach ($products as $data) {
            $category = Category::where('name', $data['category'])->first();
            if (! $category) {
                continue;
            }

            Product::create([
                'category_id'  => $category->id,
                'name'         => $data['name'],
                'slug'         => Str::slug($data['name']),
                'description'  => $data['desc'],
                'price_per_day' => $data['price'],
                'quantity'     => $data['qty'],
                'deposit'      => $data['deposit'],
                'status'       => 'active',
            ]);
        }
    }
}
