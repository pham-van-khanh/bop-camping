<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-2xf6 — admin quản lý tài khoản shipper trong tab Shipper của /admin/users:
 * thêm/sửa/tắt vai/xoá, kèm cảnh báo khi người đó còn lượt giao/thu sắp tới.
 * Xem prd_shipper_delivery_ops FR-1.
 */
class AdminShipperUsersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function shipper(string $name = 'Shipper A'): User
    {
        return User::factory()->create(['name' => $name, 'is_shipper' => true, 'password' => Hash::make('secret123')]);
    }

    /** Đơn có lượt giao trong tương lai gán cho $shipper. */
    private function upcomingOrder(User $shipper): Order
    {
        return Order::create([
            'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => 'Khách', 'customer_phone' => '0900000000',
            'start_date' => now()->addDays(3)->toDateString(), 'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'confirmed', 'payment_method' => 'cod',
            'total_price' => 100000, 'deposit_total' => 50000,
            'pickup_shipper_id' => $shipper->id,
        ]);
    }

    /** @test */
    public function shipper_tab_lists_shippers_with_upcoming_leg_count(): void
    {
        $shipper = $this->shipper('An');
        $this->upcomingOrder($shipper);
        $this->shipper('Bình');

        $this->actingAs($this->admin())->get(route('admin.users', ['tab' => 'shippers']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users')
                ->where('tab', 'shippers')
                ->has('shippers', 2)
                ->where('shippers.0.name', 'An')
                ->where('shippers.0.upcoming_legs', 1)
                ->where('shippers.1.upcoming_legs', 0)
                ->where('stats.shippers', 2));
    }

    /** @test */
    public function shippers_do_not_appear_in_the_customer_list(): void
    {
        $this->shipper('Shipper không phải khách');
        User::factory()->create(['name' => 'Khách thật']);

        $this->actingAs($this->admin())->get(route('admin.users'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Khách thật')
                ->where('stats.customers', 1));
    }

    /** @test */
    public function admin_creates_a_shipper_account(): void
    {
        $this->actingAs($this->admin())->post(route('admin.users.store'), [
            'name' => 'Shipper Mới',
            'phone' => '0912345678',
            'password' => 'matkhau123',
            'role' => 'shipper',
        ])->assertSessionHasNoErrors();

        $created = User::where('phone', '0912345678')->firstOrFail();
        $this->assertTrue($created->isShipper());
        $this->assertFalse((bool) $created->is_admin, 'Tạo shipper KHÔNG được kèm quyền admin');
    }

    /** @test */
    public function creating_without_role_still_makes_an_admin(): void
    {
        // Giữ hành vi cũ của trang: không truyền role → tài khoản quản trị.
        $this->actingAs($this->admin())->post(route('admin.users.store'), [
            'name' => 'Quản trị', 'phone' => '0913333333', 'password' => 'matkhau123',
        ])->assertSessionHasNoErrors();

        $created = User::where('phone', '0913333333')->firstOrFail();
        $this->assertTrue((bool) $created->is_admin);
        $this->assertFalse($created->isShipper());
    }

    /** @test */
    public function admin_edits_shipper_and_resets_password(): void
    {
        $shipper = $this->shipper();
        $oldHash = $shipper->password;

        $this->actingAs($this->admin())->put(route('admin.users.update', $shipper), [
            'name' => 'Tên Mới', 'phone' => '0914444444', 'password' => 'matkhaumoi',
        ])->assertSessionHasNoErrors();

        $shipper->refresh();
        $this->assertSame('Tên Mới', $shipper->name);
        $this->assertSame('0914444444', $shipper->phone);
        $this->assertNotSame($oldHash, $shipper->password);
        $this->assertTrue($shipper->isShipper(), 'Sửa thông tin không được làm mất vai shipper');
    }

    /** @test */
    public function turning_off_the_role_is_blocked_while_upcoming_legs_remain(): void
    {
        $shipper = $this->shipper();
        $order = $this->upcomingOrder($shipper);

        $this->actingAs($this->admin())
            ->patch(route('admin.users.role', $shipper), ['is_shipper' => false])
            ->assertSessionHasErrors('message');

        $this->assertTrue($shipper->fresh()->isShipper());
        $this->assertSame($shipper->id, $order->fresh()->pickup_shipper_id);
    }

    /** @test */
    public function forcing_the_role_off_releases_upcoming_legs(): void
    {
        $shipper = $this->shipper();
        $order = $this->upcomingOrder($shipper);

        $this->actingAs($this->admin())
            ->patch(route('admin.users.role', $shipper), ['is_shipper' => false, 'force' => true])
            ->assertSessionHasNoErrors();

        $this->assertFalse($shipper->fresh()->isShipper());
        $this->assertNull($order->fresh()->pickup_shipper_id, 'Lượt sắp tới phải về "chưa gán"');
    }

    /** @test */
    public function role_change_cannot_target_self(): void
    {
        $me = User::factory()->create(['is_admin' => true, 'is_shipper' => true]);

        $this->actingAs($me)->patch(route('admin.users.role', $me), ['is_shipper' => false])
            ->assertSessionHasErrors('message');

        $this->assertTrue($me->fresh()->isShipper());
    }

    /** @test */
    public function deleting_a_shipper_with_upcoming_legs_needs_confirmation(): void
    {
        $shipper = $this->shipper();
        $order = $this->upcomingOrder($shipper);

        // Lần 1: chưa xác nhận → chặn.
        $this->actingAs($this->admin())->delete(route('admin.users.destroy', $shipper))
            ->assertSessionHasErrors('message');
        $this->assertNotNull($shipper->fresh());

        // Lần 2: xác nhận → xoá, đơn giữ lại và lượt về "chưa gán".
        $this->actingAs($this->admin())->delete(route('admin.users.destroy', $shipper), ['force' => true])
            ->assertSessionHasNoErrors();
        $this->assertNull($shipper->fresh());
        $this->assertNotNull($order->fresh());
        $this->assertNull($order->fresh()->pickup_shipper_id);
    }

    /** @test */
    public function shipper_without_upcoming_legs_is_deleted_without_confirmation(): void
    {
        $shipper = $this->shipper();

        $this->actingAs($this->admin())->delete(route('admin.users.destroy', $shipper))
            ->assertSessionHasNoErrors();

        $this->assertNull($shipper->fresh());
    }

    /** @test */
    public function shipper_cannot_manage_accounts(): void
    {
        $shipper = $this->shipper();
        $other = $this->shipper('Người khác');

        $this->actingAs($shipper)->get(route('admin.users', ['tab' => 'shippers']))
            ->assertRedirect(route('admin.login'));
        $this->actingAs($shipper)->post(route('admin.users.store'), [
            'name' => 'Tự tạo', 'phone' => '0915555555', 'password' => 'matkhau123', 'role' => 'shipper',
        ])->assertRedirect(route('admin.login'));
        $this->actingAs($shipper)->delete(route('admin.users.destroy', $other))
            ->assertRedirect(route('admin.login'));

        $this->assertNotNull($other->fresh());
    }
}
