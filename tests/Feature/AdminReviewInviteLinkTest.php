<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-tme3 — link mời đánh giá ở chi tiết đơn admin, để dán vào Zalo cho khách
 * không có email (mail mời của OrderObserver không bao giờ tới những đơn đó).
 */
class AdminReviewInviteLinkTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** @test */
    public function returned_order_without_token_gets_a_working_invite_link(): void
    {
        $order = Order::factory()->create(['status' => 'returned', 'review_token' => null]);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertInertia(fn ($p) => $p
                ->where('order.review_invite.submitted', false)
                ->where('order.review_invite.emailed_at', null)
                ->where('order.review_invite.url', fn ($url) => str_contains($url, '/danh-gia/'))
            );

        // Token được tạo và giữ nguyên qua các lần mở lại — link đã gửi khách không chết.
        $token = $order->fresh()->review_token;
        $this->assertNotEmpty($token);
        $this->actingAs($this->admin())->get(route('admin.orders.show', $order));
        $this->assertSame($token, $order->fresh()->review_token);

        // Link mở ra đúng form đánh giá của đơn này.
        $this->get('/danh-gia/'.$token)->assertInertia(fn ($p) => $p
            ->where('found', true)
            ->where('code', $order->code)
        );
    }

    /** @test */
    public function existing_token_and_mail_date_are_reused(): void
    {
        $order = Order::factory()->create([
            'status' => 'returned',
            'review_token' => 'tok-da-gui-mail',
            'review_invited_at' => '2026-11-01 09:00:00',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertInertia(fn ($p) => $p
                ->where('order.review_invite.url', route('review.invite', 'tok-da-gui-mail'))
                ->where('order.review_invite.emailed_at', '01/11/2026')
            );
    }

    /** @test */
    public function submitted_review_is_flagged(): void
    {
        $order = Order::factory()->create([
            'status' => 'returned',
            'review_token' => 'tok',
            'review_submitted_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertInertia(fn ($p) => $p->where('order.review_invite.submitted', true));
    }

    /** @test */
    public function orders_not_yet_returned_have_no_invite_and_get_no_token(): void
    {
        foreach (['pending', 'confirmed', 'renting', 'cancelled'] as $status) {
            $order = Order::factory()->create(['status' => $status, 'review_token' => null]);

            $this->actingAs($this->admin())
                ->get(route('admin.orders.show', $order))
                ->assertInertia(fn ($p) => $p->where('order.review_invite', null));

            $this->assertNull($order->fresh()->review_token, $status);
        }
    }

    /** @test */
    public function order_list_does_not_build_invite_links(): void
    {
        $order = Order::factory()->create(['status' => 'returned', 'review_token' => null]);

        $this->actingAs($this->admin())->get(route('admin.orders'))->assertOk();

        // Danh sách không phân trang: không sinh token hàng loạt chỉ vì mở danh sách.
        $this->assertNull($order->fresh()->review_token);
    }
}
