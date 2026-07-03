<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 *
 * Mặc định status 'confirmed' — đơn CHIẾM tồn kho (Order::activeStatuses),
 * đúng nhu cầu phổ biến nhất trong test availability. Mã đơn tự sinh ở model.
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'customer_name' => fake()->name(),
            'customer_phone' => '09'.fake()->numerify('########'),
            'start_date' => '2030-01-01',
            'end_date' => '2030-01-02',
            'status' => 'confirmed',
            'payment_method' => 'cod',
        ];
    }
}
