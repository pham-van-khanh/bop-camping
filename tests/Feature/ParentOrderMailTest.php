<?php

namespace Tests\Feature;

use App\Mail\NewOrderAdminMail;
use App\Mail\OrderPlacedMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * bopcamping-wtuv (T9) — đơn gộp gửi 1 EMAIL CẤP CHA liệt kê từng đợt (không phải
 * mỗi con 1 mail); đơn thường giữ mail như cũ. View cha render được (không lỗi blade).
 */
class ParentOrderMailTest extends TestCase
{
    use RefreshDatabase;

    private Product $p;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(['is_admin' => true, 'email' => 'admin@bopcamping.test']);
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $this->p = Product::create(['category_id' => $cat->id, 'name' => 'A', 'slug' => 'a', 'price_per_day' => 100000, 'quantity' => 5, 'deposit' => 100000]);
    }

    /** @test */
    public function multi_range_order_sends_one_parent_level_mail(): void
    {
        Mail::fake();

        $this->post(route('order.store'), [
            'name' => 'Khách Gộp', 'phone' => '0911666001', 'email' => 'gop@example.com',
            'items' => [
                ['product_id' => $this->p->id, 'quantity' => 1, 'start' => '2030-09-01', 'end' => '2030-09-02'],
                ['product_id' => $this->p->id, 'quantity' => 1, 'start' => '2030-09-05', 'end' => '2030-09-07'],
            ],
        ])->assertSessionHas('order_code');

        $parent = Order::where('is_parent', true)->firstOrFail();

        // ĐÚNG 1 mail khách (cấp cha, subject ghi số đợt) + 1 mail admin.
        Mail::assertQueued(OrderPlacedMail::class, 1);
        Mail::assertQueued(OrderPlacedMail::class, fn (OrderPlacedMail $m) => $m->order->id === $parent->id
            && str_contains($m->envelope()->subject, '2 đợt giao'));
        Mail::assertQueued(NewOrderAdminMail::class, 1);
    }

    /** @test */
    public function parent_mail_views_render_each_installment(): void
    {
        $this->post(route('order.store'), [
            'name' => 'Khách Gộp', 'phone' => '0911666002', 'email' => 'gop2@example.com',
            'items' => [
                ['product_id' => $this->p->id, 'quantity' => 1, 'start' => '2030-09-01', 'end' => '2030-09-02'],
                ['product_id' => $this->p->id, 'quantity' => 1, 'start' => '2030-09-05', 'end' => '2030-09-07'],
            ],
        ]);
        $parent = Order::where('is_parent', true)->firstOrFail();
        $children = $parent->children()->get();

        // Render thật cả 2 view (bắt lỗi blade/quan hệ thiếu).
        $customerHtml = (new OrderPlacedMail($parent))->render();
        $adminHtml = (new NewOrderAdminMail($parent))->render();

        foreach ($children as $child) {
            $this->assertStringContainsString($child->code, $customerHtml);
            $this->assertStringContainsString($child->code, $adminHtml);
        }
        $this->assertStringContainsString('ĐỢT 1', $customerHtml);
        $this->assertStringContainsString('Tổng thanh toán', $customerHtml);
        $this->assertStringContainsString('đợt giao', $adminHtml);
    }

    /** @test */
    public function single_range_order_keeps_normal_mail(): void
    {
        Mail::fake();

        $this->post(route('order.store'), [
            'name' => 'Khách Thường', 'phone' => '0911666003', 'email' => 'thuong@example.com',
            'items' => [['product_id' => $this->p->id, 'quantity' => 1, 'start' => '2030-09-10', 'end' => '2030-09-11']],
        ])->assertSessionHas('order_code');

        Mail::assertQueued(OrderPlacedMail::class, fn (OrderPlacedMail $m) => ! $m->order->is_parent
            && ! str_contains($m->envelope()->subject, 'đợt'));
    }
}
