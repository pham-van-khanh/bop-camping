<?php

namespace Tests\Feature;

use App\Mail\FeedbackReceivedMail;
use App\Mail\FeedbackReplyMail;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Epic 2: khách gửi góp ý (widget) + admin phản hồi qua email. */
class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** @test */
    public function guest_can_submit_feedback_and_admin_mail_is_queued(): void
    {
        Mail::fake();
        config(['mail.admin_address' => 'chu-shop@bopcamping.test']);

        $this->post('/gop-y', [
            'name' => 'Khánh',
            'email' => 'khach@example.com',
            'content' => 'Website dễ dùng, nhưng nên thêm phần so sánh lều.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('feedbacks', ['name' => 'Khánh', 'status' => 'new']);
        Mail::assertQueued(FeedbackReceivedMail::class, fn ($m) => $m->hasTo('chu-shop@bopcamping.test'));
    }

    /** Không đặt MAIL_ADMIN_ADDRESS → gửi tới email các tài khoản admin. @test */
    public function admin_mail_falls_back_to_admin_users(): void
    {
        Mail::fake();
        config(['mail.admin_address' => null]);
        $admin = $this->admin();

        $this->post('/gop-y', [
            'name' => 'Khánh',
            'phone' => '0912345678',
            'content' => 'Giao diện đẹp lắm nha!',
        ])->assertRedirect();

        Mail::assertQueued(FeedbackReceivedMail::class, fn ($m) => $m->hasTo($admin->email));
    }

    /** @test */
    public function feedback_requires_phone_or_email(): void
    {
        $this->post('/gop-y', [
            'name' => 'Khánh',
            'content' => 'Góp ý nhưng không để lại liên hệ nào cả nhé.',
        ])->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('feedbacks', 0);
    }

    /** @test */
    public function admin_reply_sends_mail_via_reply_mailer_and_marks_replied(): void
    {
        Mail::fake();
        $f = Feedback::create(['name' => 'Khánh', 'email' => 'khach@example.com', 'content' => 'Nên có thêm combo gia đình.']);

        $this->actingAs($this->admin())
            ->patch(route('admin.feedbacks.reply', $f), [
                'reply_content' => 'Tụi mình sẽ bổ sung combo gia đình trong tháng tới nhé!',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $f->refresh();
        $this->assertSame('replied', $f->status);
        $this->assertNotNull($f->replied_at);
        Mail::assertQueued(FeedbackReplyMail::class, fn ($m) => $m->hasTo('khach@example.com'));
    }

    /** Khách chỉ để SĐT → lưu phản hồi + replied, KHÔNG gửi mail. @test */
    public function admin_reply_without_email_marks_replied_without_mail(): void
    {
        Mail::fake();
        $f = Feedback::create(['name' => 'Khánh', 'phone' => '0912345678', 'content' => 'Gọi lại cho mình nhé.']);

        $this->actingAs($this->admin())
            ->patch(route('admin.feedbacks.reply', $f), ['reply_content' => 'Đã gọi trao đổi trực tiếp với khách.'])
            ->assertRedirect();

        $this->assertSame('replied', $f->refresh()->status);
        Mail::assertNothingQueued();
    }

    /** From của mail phản hồi lấy từ env MAIL_REPLY_FROM_* (fallback from mặc định). @test */
    public function reply_mail_uses_reply_from_address_when_configured(): void
    {
        config(['mail.reply_from.address' => 'hotro@bopcamping.test', 'mail.reply_from.name' => 'BOP Hỗ trợ']);
        $f = Feedback::create(['name' => 'Khánh', 'email' => 'khach@example.com', 'content' => 'Test from.', 'reply_content' => 'OK bạn nhé.']);

        $envelope = (new FeedbackReplyMail($f))->envelope();

        $this->assertSame('hotro@bopcamping.test', $envelope->from->address);
        $this->assertSame('BOP Hỗ trợ', $envelope->from->name);
    }

    /** @test */
    public function admin_index_lists_and_filters_feedback(): void
    {
        Feedback::create(['name' => 'A', 'phone' => '09', 'content' => 'Góp ý 1']);
        Feedback::create(['name' => 'B', 'phone' => '09', 'content' => 'Góp ý 2', 'status' => 'replied', 'reply_content' => 'ok', 'replied_at' => now()]);

        $this->actingAs($this->admin())
            ->get('/admin/gop-y?status=new')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Feedbacks')->has('feedbacks.data', 1));
    }

    /** @test */
    public function guest_cannot_access_admin_feedbacks(): void
    {
        $f = Feedback::create(['name' => 'A', 'phone' => '09', 'content' => 'x']);

        $this->get('/admin/gop-y')->assertRedirect('/admin/login');
        $this->patch(route('admin.feedbacks.reply', $f), ['reply_content' => 'hacked'])->assertRedirect('/admin/login');
    }
}
