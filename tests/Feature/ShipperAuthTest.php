<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-lsch — đăng nhập shipper (SĐT + mật khẩu) và ranh giới quyền giữa
 * khách / shipper / admin. Xem adr_shipper_role_and_access mục 3.
 */
class ShipperAuthTest extends TestCase
{
    use RefreshDatabase;

    private function shipper(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'phone' => '0911111111',
            'password' => Hash::make('shipper-pass'),
            'is_shipper' => true,
        ], $attrs));
    }

    /** @test */
    public function login_page_renders(): void
    {
        $this->get(route('shipper.login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Shipper/Login'));
    }

    /** @test */
    public function shipper_logs_in_and_lands_on_own_schedule(): void
    {
        $shipper = $this->shipper();

        $this->post(route('shipper.login.store'), ['phone' => '0911111111', 'password' => 'shipper-pass'])
            ->assertRedirect(route('shipper.schedule'));

        $this->assertAuthenticatedAs($shipper);
    }

    /** @test */
    public function wrong_password_is_rejected(): void
    {
        $this->shipper();

        $this->post(route('shipper.login.store'), ['phone' => '0911111111', 'password' => 'sai-mat-khau'])
            ->assertSessionHasErrors('phone');

        $this->assertGuest();
    }

    /** @test */
    public function non_shipper_with_valid_password_cannot_log_in_and_is_logged_out(): void
    {
        // Tài khoản có mật khẩu đúng nhưng KHÔNG phải shipper (vd admin, hoặc khách có mật khẩu).
        User::factory()->create([
            'phone' => '0922222222',
            'password' => Hash::make('dung-mat-khau'),
            'is_admin' => true,
        ]);

        $this->post(route('shipper.login.store'), ['phone' => '0922222222', 'password' => 'dung-mat-khau'])
            ->assertSessionHasErrors('phone');

        // Quan trọng: không được để phiên đăng nhập treo lại sau khi bị chặn.
        $this->assertGuest();
    }

    /** @test */
    public function error_message_does_not_reveal_whether_the_account_exists(): void
    {
        $this->shipper();

        $existing = $this->post(route('shipper.login.store'), ['phone' => '0911111111', 'password' => 'sai'])
            ->assertSessionHasErrors('phone');
        $missing = $this->post(route('shipper.login.store'), ['phone' => '0999999999', 'password' => 'sai'])
            ->assertSessionHasErrors('phone');

        $this->assertSame(
            session()->get('errors')?->get('phone'),
            $missing->getSession()->get('errors')->get('phone'),
        );
        $this->assertSame(
            $existing->getSession()->get('errors')->get('phone'),
            $missing->getSession()->get('errors')->get('phone'),
        );
    }

    /** @test */
    public function login_returns_to_the_page_the_shipper_was_trying_to_open(): void
    {
        // Link "Xem đơn" trong tin nhắn Zalo kèm ?date=&month= — bấm khi chưa đăng nhập
        // thì sau khi đăng nhập phải về ĐÚNG ngày đó, không rơi về lịch hôm nay.
        $this->shipper();
        $target = route('shipper.schedule', ['date' => '2030-08-01', 'month' => '2030-08']);

        $this->get($target)->assertRedirect(route('shipper.login'));

        $this->post(route('shipper.login.store'), ['phone' => '0911111111', 'password' => 'shipper-pass'])
            ->assertRedirect($target);
    }

    /** @test */
    public function logged_in_shipper_visiting_login_goes_to_schedule(): void
    {
        $this->actingAs($this->shipper())->get(route('shipper.login'))
            ->assertRedirect(route('shipper.schedule'));
    }

    /** @test */
    public function shipper_can_log_out(): void
    {
        $this->actingAs($this->shipper())->post(route('shipper.logout'))
            ->assertRedirect(route('shipper.login'));

        $this->assertGuest();
    }
}
