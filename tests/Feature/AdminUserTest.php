<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(bool $isAdmin, string $phone, string $name = 'U'): User
    {
        return User::factory()->create([
            'name' => $name,
            'phone' => $phone,
            'is_admin' => $isAdmin,
            'password' => $isAdmin ? Hash::make('secret123') : null,
        ]);
    }

    private function makeOrder(?int $userId, string $phone, string $status = 'confirmed', int $total = 100000): Order
    {
        return Order::create([
            'user_id' => $userId,
            'code' => 'BOP-'.Str::upper(Str::random(6)),
            'customer_name' => 'Khách',
            'customer_phone' => $phone,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
            'total_price' => $total,
            'deposit_total' => 0,
            'status' => $status,
            'payment_method' => 'cod',
        ]);
    }

    /** @test */
    public function guest_and_non_admin_cannot_access_users(): void
    {
        $this->get(route('admin.users'))->assertRedirect(route('admin.login'));

        $customer = $this->makeUser(false, '0911111111');
        $this->actingAs($customer)->get(route('admin.users'))->assertRedirect(route('admin.login'));
    }

    /** @test */
    public function admin_sees_only_customers_in_customer_list(): void
    {
        $admin = $this->makeUser(true, '0900000001', 'Admin');
        $this->makeUser(false, '0911111111', 'Khách A');

        $this->actingAs($admin)
            ->get(route('admin.users', ['tab' => 'customers']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users')
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Khách A'));
    }

    /** @test */
    public function can_create_admin_account_with_hashed_password(): void
    {
        $admin = $this->makeUser(true, '0900000001');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Nhân viên 1',
            'phone' => '0912345678',
            'password' => 'matkhau',
        ])->assertRedirect()->assertSessionHas('success');

        $created = User::where('phone', '0912345678')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->is_admin);
        $this->assertTrue(Hash::check('matkhau', $created->password));
    }

    /** @test */
    public function admin_email_is_saved_and_used_for_notifications(): void
    {
        $admin = $this->makeUser(true, '0900000001');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'QTV Mail',
            'phone' => '0912345678',
            'email' => 'qtv@shop.vn',
            'password' => 'matkhau',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('qtv@shop.vn', User::where('phone', '0912345678')->first()->email);
        $this->assertContains('qtv@shop.vn', User::adminNotifyEmails());
        // Admin tạo lúc setup (makeUser) chỉ có email .local → không nằm trong danh sách nhận.
        $this->assertNotContains('0900000001@bopcamping.local', User::adminNotifyEmails());
    }

    /** @test */
    public function create_admin_validates_unique_phone_and_password(): void
    {
        $admin = $this->makeUser(true, '0900000001');

        // SĐT trùng
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'X', 'phone' => '0900000001', 'password' => 'matkhau',
        ])->assertSessionHasErrors('phone');

        // Mật khẩu quá ngắn
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'X', 'phone' => '0912345678', 'password' => '123',
        ])->assertSessionHasErrors('password');
    }

    /** @test */
    public function admin_creates_customer_with_only_name_phone_email(): void
    {
        $admin = $this->makeUser(true, '0900000001');

        $this->actingAs($admin)->post(route('admin.users.customers.store'), [
            'name' => 'Khách Mới',
            'phone' => '0912345678',
            'email' => 'khach@gmail.com',
        ])->assertRedirect()->assertSessionHas('success');

        $created = User::where('phone', '0912345678')->first();
        $this->assertNotNull($created);
        $this->assertSame('Khách Mới', $created->name);
        $this->assertSame('khach@gmail.com', $created->email);
        $this->assertFalse($created->is_admin);
        $this->assertFalse($created->is_shipper);
        // Khách không dùng mật khẩu (đăng nhập bằng SĐT/OTP).
        $this->assertNull($created->password);
        // Admin tạo hộ = đã xác minh → khách không phải nhập OTP.
        $this->assertNotNull($created->email_verified_at);
    }

    /** @test */
    public function customer_created_by_admin_logs_in_without_otp(): void
    {
        $admin = $this->makeUser(true, '0900000001');

        $this->actingAs($admin)->post(route('admin.users.customers.store'), [
            'name' => 'Khách Mới', 'phone' => '0912345678', 'email' => 'khach@gmail.com',
        ])->assertRedirect();

        $created = User::where('phone', '0912345678')->first();

        // Khách vào bằng SĐT: OTP gửi tới ĐÚNG email admin đã điền hộ, khách không phải gõ
        // lại email của chính mình (bopcamping-bqsv). Chưa xác thực thì chưa vào được.
        $this->post(route('guest.login'), ['phone' => '0912345678'])
            ->assertSessionHas('otp_sent', true)
            ->assertSessionHasNoErrors();
        $this->assertGuest();
        $this->assertSame($created->email, session('otp_pending')['email']);
    }

    /** @test */
    public function admin_can_create_customer_without_email(): void
    {
        $admin = $this->makeUser(true, '0900000001');

        $this->actingAs($admin)->post(route('admin.users.customers.store'), [
            'name' => 'Khách Không Mail', 'phone' => '0912345678',
        ])->assertRedirect()->assertSessionHas('success');

        $created = User::where('phone', '0912345678')->first();
        $this->assertNotNull($created);
        // Email tạm do model tự điền → KHÔNG đánh dấu đã xác minh.
        $this->assertTrue($created->hasPlaceholderEmail());
        $this->assertNull($created->email_verified_at);
    }

    /** @test */
    public function create_customer_validates_unique_phone_and_email(): void
    {
        $admin = $this->makeUser(true, '0900000001');
        $existing = $this->makeUser(false, '0911111111');
        $existing->forceFill(['email' => 'da_dung@gmail.com'])->save();

        $this->actingAs($admin)->post(route('admin.users.customers.store'), [
            'name' => 'X', 'phone' => '0911111111',
        ])->assertSessionHasErrors('phone');

        $this->actingAs($admin)->post(route('admin.users.customers.store'), [
            'name' => 'X', 'phone' => '0912345678', 'email' => 'da_dung@gmail.com',
        ])->assertSessionHasErrors('email');

        $this->actingAs($admin)->post(route('admin.users.customers.store'), [
            'name' => 'X', 'phone' => '091', 'email' => 'sai-email',
        ])->assertSessionHasErrors(['phone', 'email']);
    }

    /** @test */
    public function non_admin_cannot_create_customer(): void
    {
        $customer = $this->makeUser(false, '0911111111');

        $this->actingAs($customer)->post(route('admin.users.customers.store'), [
            'name' => 'X', 'phone' => '0912345678',
        ])->assertRedirect(route('admin.login'));

        $this->assertDatabaseMissing('users', ['phone' => '0912345678']);
    }

    /** @test */
    public function cannot_update_a_customer_through_admin_update(): void
    {
        $admin = $this->makeUser(true, '0900000001');
        $customer = $this->makeUser(false, '0911111111');

        $this->actingAs($admin)->put(route('admin.users.update', $customer), [
            'name' => 'Hacked', 'phone' => '0911111111',
        ])->assertNotFound();
    }

    /** @test */
    public function update_admin_keeps_password_when_blank(): void
    {
        $admin = $this->makeUser(true, '0900000001');
        $target = $this->makeUser(true, '0900000002', 'Cũ');
        $oldHash = $target->password;

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => 'Mới', 'phone' => '0900000002', 'password' => '',
        ])->assertRedirect()->assertSessionHas('success');

        $target->refresh();
        $this->assertSame('Mới', $target->name);
        $this->assertSame($oldHash, $target->password);
    }

    /** @test */
    public function cannot_demote_self(): void
    {
        $admin = $this->makeUser(true, '0900000001');
        $this->makeUser(true, '0900000002'); // có admin khác → không vướng guard last-admin

        $this->actingAs($admin)->patch(route('admin.users.role', $admin), ['is_admin' => false])
            ->assertSessionHasErrors('message');

        $this->assertTrue($admin->fresh()->is_admin);
    }

    /** @test */
    public function can_demote_other_admin_when_multiple_exist(): void
    {
        $admin = $this->makeUser(true, '0900000001');
        $other = $this->makeUser(true, '0900000002');

        $this->actingAs($admin)->patch(route('admin.users.role', $other), ['is_admin' => false])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertFalse($other->fresh()->is_admin);
    }

    /** @test */
    public function cannot_delete_self_or_last_admin(): void
    {
        $admin = $this->makeUser(true, '0900000001');

        // last admin = self → chặn
        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))
            ->assertSessionHasErrors('message');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    /** @test */
    public function deleting_customer_preserves_orders_and_nulls_user_id(): void
    {
        $admin = $this->makeUser(true, '0900000001');
        $customer = $this->makeUser(false, '0911111111');
        $order = $this->makeOrder($customer->id, '0911111111');

        $this->actingAs($admin)->delete(route('admin.users.destroy', $customer))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $order->refresh();
        $this->assertNull($order->user_id);
        $this->assertSame('0911111111', $order->customer_phone); // lịch sử giữ nguyên
    }

    /** @test */
    public function customer_detail_reconciles_orders_by_phone(): void
    {
        $admin = $this->makeUser(true, '0900000001');
        $customer = $this->makeUser(false, '0911111111', 'Khách A');

        $this->makeOrder($customer->id, '0911111111');  // đơn đã liên kết
        $this->makeOrder(null, '0911111111');            // đơn vãng lai cùng SĐT
        $this->makeOrder(null, '0999999999');            // SĐT khác → KHÔNG được tính

        $this->actingAs($admin)
            ->get(route('admin.users', ['tab' => 'customers', 'customer' => $customer->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users')
                ->has('customerDetail.orders', 2));
    }
}
