<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-oem — tra cứu đơn theo mã + SĐT (found / not_found).
 */
class OrderLookupTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(): Order
    {
        return Order::create([
            'code' => 'BOP-ABC123',
            'customer_name' => 'Khách',
            'customer_phone' => '0912345678',
            'start_date' => '2030-07-01',
            'end_date' => '2030-07-03',
            'total_price' => 300000,
            'deposit_total' => 200000,
            'status' => 'confirmed',
            'payment_method' => 'cod',
        ]);
    }

    /** @test */
    public function found_when_code_and_phone_match(): void
    {
        $this->makeOrder();

        $this->get(route('lookup', ['code' => 'BOP-ABC123', 'phone' => '0912345678']))
            ->assertInertia(fn (Assert $p) => $p
                ->component('OrderLookup')
                ->where('not_found', false)
                ->where('order.code', 'BOP-ABC123')
                ->where('order.customer_phone', '0912345678'));
    }

    /** @test */
    public function not_found_when_phone_does_not_match(): void
    {
        $this->makeOrder();

        $this->get(route('lookup', ['code' => 'BOP-ABC123', 'phone' => '0999999999']))
            ->assertInertia(fn (Assert $p) => $p
                ->component('OrderLookup')
                ->where('not_found', true)
                ->where('order', null));
    }

    /** @test */
    public function blank_form_when_no_query(): void
    {
        $this->get(route('lookup'))
            ->assertInertia(fn (Assert $p) => $p
                ->component('OrderLookup')
                ->where('not_found', false)
                ->where('order', null));
    }

    /** @test bopcamping-2ded — giờ shop đã chốt hiện cho khách tra cứu vãng lai. */
    public function shows_confirmed_schedule_times_when_set(): void
    {
        $order = $this->makeOrder();
        $order->update(['confirmed_pickup_time' => '14:30', 'confirmed_return_time' => '09:00']);

        $this->get(route('lookup', ['code' => 'BOP-ABC123', 'phone' => '0912345678']))
            ->assertInertia(fn (Assert $p) => $p
                ->where('order.confirmed_pickup_time', '14:30')
                ->where('order.confirmed_return_time', '09:00'));
    }

    /** @test bopcamping-2ded — chưa chốt giờ → null, không lỗi. */
    public function confirmed_schedule_times_are_null_when_not_set(): void
    {
        $this->makeOrder();

        $this->get(route('lookup', ['code' => 'BOP-ABC123', 'phone' => '0912345678']))
            ->assertInertia(fn (Assert $p) => $p
                ->where('order.confirmed_pickup_time', null)
                ->where('order.confirmed_return_time', null));
    }
}
