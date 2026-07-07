<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\User;
use Database\Seeders\FaqSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-eyo — FAQ trang chủ + CRUD admin (ADR home_faq_contact).
 * Home hiện FAQ active theo sort_order; admin CRUD; non-admin bị chặn.
 */
class FaqTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function faq(string $q, string $a, int $sort = 0, bool $active = true): Faq
    {
        return Faq::create(['question' => $q, 'answer' => $a, 'sort_order' => $sort, 'is_active' => $active]);
    }

    /** @test */
    public function home_lists_active_faqs_ordered_and_excludes_inactive(): void
    {
        $this->faq('Câu B', 'Đáp B', sort: 2);
        $this->faq('Câu A', 'Đáp A', sort: 1);
        $this->faq('Câu ẩn', 'Đáp ẩn', sort: 3, active: false);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->count('faqs', 2)                       // câu ẩn không lọt
                ->where('faqs.0.question', 'Câu A')      // sort_order nhỏ lên trước
                ->where('faqs.1.question', 'Câu B'));
    }

    /** @test */
    public function home_faqs_empty_when_none_active(): void
    {
        $this->faq('Chỉ có ẩn', 'Đáp', active: false);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('faqs', []));
    }

    /** @test */
    public function admin_index_lists_all_faqs_including_inactive(): void
    {
        $this->faq('Hiện', 'x');
        $this->faq('Ẩn', 'y', active: false);

        $this->actingAs($this->admin())->get(route('admin.faqs'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Faqs')
                ->count('faqs', 2));
    }

    /** @test */
    public function admin_can_create_faq(): void
    {
        $this->actingAs($this->admin())->post(route('admin.faqs.store'), [
            'question' => 'Có phải trả tiền trước không?',
            'answer' => 'Không, thanh toán COD khi nhận đồ.',
            'sort_order' => 5,
            'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('faqs', [
            'question' => 'Có phải trả tiền trước không?',
            'sort_order' => 5,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function admin_can_update_and_toggle_active(): void
    {
        $faq = $this->faq('Cũ', 'Đáp cũ');

        $this->actingAs($this->admin())->put(route('admin.faqs.update', $faq), [
            'question' => 'Mới',
            'answer' => 'Đáp mới',
            'sort_order' => 0,
            'is_active' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $faq->refresh();
        $this->assertSame('Mới', $faq->question);
        $this->assertFalse($faq->is_active);
    }

    /** @test */
    public function admin_can_delete_faq(): void
    {
        $faq = $this->faq('Xoá tôi', 'x');

        $this->actingAs($this->admin())->delete(route('admin.faqs.destroy', $faq))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);
    }

    /** @test */
    public function store_requires_question_and_answer(): void
    {
        $this->actingAs($this->admin())->post(route('admin.faqs.store'), [
            'question' => '',
            'answer' => '',
        ])->assertSessionHasErrors(['question', 'answer']);
    }

    /** @test */
    public function non_admin_cannot_access_faq_admin(): void
    {
        // Khách thường (không phải admin)
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get(route('admin.faqs'))->assertRedirect();
        $this->actingAs($user)->post(route('admin.faqs.store'), [
            'question' => 'x', 'answer' => 'y',
        ])->assertRedirect();

        // Khách vãng lai
        $this->get(route('admin.faqs'))->assertRedirect();
    }

    /** @test */
    public function seeder_populates_faqs_from_site_content(): void
    {
        $this->seed(FaqSeeder::class);

        $this->assertGreaterThanOrEqual(8, Faq::count());
        $this->assertTrue(Faq::where('question', 'like', '%COD%')->orWhere('answer', 'like', '%COD%')->exists());
        // Tất cả seed đều active để hiện ngay ở home
        $this->assertSame(Faq::count(), Faq::active()->count());
    }
}
