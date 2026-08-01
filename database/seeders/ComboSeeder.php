<?php

namespace Database\Seeders;

use App\Models\Combo;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Combo mẫu (bopcamping-ry4u).
 *
 * VÌ SAO CẦN: chưa có seeder nào tạo combo, nên sau migrate:fresh --seed thì trang /combos
 * trống trơn và KHÔNG THỬ ĐƯỢC luồng combo trên máy mình — cả phần gợi ý combo trong giỏ,
 * phần tồn kho combo (min qua các món), lẫn phần combo theo cơ sở.
 *
 * Giá combo đặt THẤP HƠN tổng giá lẻ để đúng ý nghĩa "combo tiết kiệm"; cọc lấy tổng cọc
 * các món cho khớp thực tế.
 */
class ComboSeeder extends Seeder
{
    public function run(): void
    {
        $combos = [
            [
                'name' => 'Combo Cặp Đôi Cuối Tuần',
                'desc' => 'Đủ đồ cho hai người ngủ đêm: lều 2 người, hai túi ngủ và đèn lều.',
                'suitable_for' => 2,
                'sort_order' => 1,
                'items' => [
                    'Lều Naturehike Cloud-Up 2' => 1,
                    'Túi ngủ Naturehike CW400' => 2,
                    'Đèn lều Black Diamond Moji+' => 1,
                ],
            ],
            [
                'name' => 'Combo Gia Đình 4 Người',
                'desc' => 'Lều 3 người, túi ngủ, bếp gas, bộ nồi và ghế gấp — nấu ăn ngủ nghỉ đủ cả.',
                'suitable_for' => 4,
                'sort_order' => 2,
                'items' => [
                    'Lều Naturehike Taga 3 người' => 1,
                    'Túi ngủ Naturehike CW400' => 2,
                    'Bếp gas mini MSR Pocket Rocket 2' => 1,
                    'Bộ nồi Titanium 3 món' => 1,
                    'Ghế gấp Helinox Chair One' => 2,
                ],
            ],
        ];

        $openLocationIds = ServiceLocation::where('status', 'open')->pluck('id')->all();

        foreach ($combos as $data) {
            $products = Product::whereIn('name', array_keys($data['items']))->get()->keyBy('name');

            // Thiếu món thì bỏ qua combo đó — combo hụt món sẽ luôn hiện hết hàng, gây hiểu nhầm.
            if ($products->count() !== count($data['items'])) {
                continue;
            }

            $giaLe = 0;
            $tongCoc = 0;
            foreach ($data['items'] as $name => $qty) {
                $giaLe += (int) $products[$name]->price_per_day * $qty;
                $tongCoc += (int) $products[$name]->deposit * $qty;
            }

            $combo = Combo::create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['desc'],
                // Giảm 15% so với thuê lẻ — combo phải rẻ hơn mới có lý do tồn tại.
                'combo_price' => (int) round($giaLe * 0.85),
                'deposit' => $tongCoc,
                'suitable_for' => $data['suitable_for'],
                'is_active' => true,
                'sort_order' => $data['sort_order'],
            ]);

            foreach ($data['items'] as $name => $qty) {
                $combo->items()->create([
                    'product_id' => $products[$name]->id,
                    'quantity' => $qty,
                ]);
            }

            // Combo bán ở mọi cơ sở đang mở (bopcamping-vj4x: 0 cơ sở = combo không bán được ở đâu).
            $combo->serviceLocations()->sync($openLocationIds);
        }
    }
}
