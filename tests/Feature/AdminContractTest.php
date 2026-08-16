<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-4jao — admin nhập danh tính bên thuê, lập hợp đồng và lấy link gửi Zalo.
 *
 * Chủ shop xem CCCD khách gửi qua Zalo rồi NHẬP TAY: hệ thống cố ý không lưu ảnh CCCD, nên
 * không phải hứa xoá và không phát sinh rủi ro dữ liệu cá nhân.
 */
class AdminContractTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function makeOrder(bool $isParent = false, string $code = 'BOP-ADM01'): Order
    {
        $order = Order::create([
            'code' => $code,
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => '0912345678',
            'is_parent' => $isParent,
            'start_date' => '2030-07-01',
            'end_date' => '2030-07-03',
            'total_price' => 361000,
            'deposit_total' => 1500000,
            'status' => 'confirmed',
            'payment_method' => 'cod',
        ]);

        $product = Product::factory()->create(['replacement_value' => 4500000]);
        $order->items()->create([
            'product_id' => $product->id, 'quantity' => 1, 'price_per_day' => 190000,
            'days' => 2, 'start_date' => '2030-07-01', 'end_date' => '2030-07-03', 'subtotal' => 380000,
        ]);

        return $order->fresh();
    }

    /** @test */
    public function admin_lap_duoc_hop_dong_va_luu_danh_tinh(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin())
            ->post(route('admin.contracts.store', $order), [
                'id_number' => '040202015437',
                'id_issued_on' => '2021-03-15',
                'id_issued_place' => 'Cục CSQLHC về TTXH',
            ])->assertRedirect();

        $contract = $order->fresh()->contract;
        $this->assertNotNull($contract);
        $this->assertSame('040202015437', $contract->signer_id_number);
        $this->assertSame('Cục CSQLHC về TTXH', $contract->signer_id_issued_place);
        $this->assertSame(1, $contract->items()->count());
    }

    /** @test */
    public function bam_lap_hai_lan_khong_tao_hop_dong_trung(): void
    {
        $order = $this->makeOrder();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.contracts.store', $order), ['id_number' => '040202015437']);
        $this->actingAs($admin)->post(route('admin.contracts.store', $order), ['id_number' => '040202015437']);

        $this->assertSame(1, Contract::count());
        $this->assertSame(1, $order->fresh()->contract->items()->count());
    }

    /** @test */
    public function don_cha_khong_lap_duoc_hop_dong(): void
    {
        $order = $this->makeOrder(isParent: true);

        $this->actingAs($this->admin())
            ->post(route('admin.contracts.store', $order), ['id_number' => '040202015437'])
            ->assertSessionHasErrors('contract');

        $this->assertNull($order->fresh()->contract);
    }

    /** @test */
    public function khach_khong_lap_duoc_hop_dong(): void
    {
        $order = $this->makeOrder();
        $khach = User::factory()->create(['is_admin' => false]);

        $this->actingAs($khach)
            ->post(route('admin.contracts.store', $order), ['id_number' => '040202015437'])
            ->assertRedirect();

        $this->assertNull($order->fresh()->contract);
    }

    /** @test */
    public function trang_chi_tiet_don_co_link_ky_va_trang_thai_ba_giai_doan(): void
    {
        $order = $this->makeOrder();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.contracts.store', $order), ['id_number' => '040202015437']);
        $contract = $order->fresh()->contract;

        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertInertia(fn ($p) => $p
                ->component('Admin/Orders/Show')
                ->where('order.contract.sign_url', url("/hop-dong/{$contract->token}"))
                ->where('order.contract.signed_stages', [])
                ->where('order.contract.id_number', '040202015437'));
    }

    /** @test */
    public function don_chua_co_hop_dong_thi_prop_la_null(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))
            ->assertInertia(fn ($p) => $p->where('order.contract', null));
    }

    /** @test */
    public function ngay_cap_khong_hop_le_bi_tu_choi(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin())
            ->post(route('admin.contracts.store', $order), [
                'id_number' => '040202015437',
                'id_issued_on' => 'hôm nọ',
            ])->assertSessionHasErrors('id_issued_on');
    }
}
