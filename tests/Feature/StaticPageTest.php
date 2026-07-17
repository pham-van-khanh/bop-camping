<?php

namespace Tests\Feature;

use App\Models\StaticPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Epic 4: trang giới thiệu /gioi-thieu + admin "Trang nội dung". */
class StaticPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** @test */
    public function about_helper_creates_default_once(): void
    {
        $first = StaticPage::about();
        $second = StaticPage::about();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, StaticPage::count());
        $this->assertStringContainsString('Câu chuyện BỐP CAMPING', (string) $first->content);
    }

    /** @test */
    public function public_about_page_renders_with_defaults(): void
    {
        $this->get('/gioi-thieu')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('About')
                ->where('page.title', StaticPage::aboutDefaults()['title'])
                ->has('page.content')
            );
    }

    /** @test */
    public function admin_pages_index_renders(): void
    {
        // provisionAll(): 1 giới thiệu + 5 trang chính sách
        $expected = 1 + count(StaticPage::POLICIES);

        $this->actingAs($this->admin())
            ->get('/admin/pages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/StaticPages')
                ->has('pages', $expected)
            );
    }

    /** @test */
    public function policy_helper_creates_default_once(): void
    {
        $first = StaticPage::policy('chinh-sach-bao-mat');
        $second = StaticPage::policy('chinh-sach-bao-mat');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, StaticPage::count());
        $this->assertStringContainsString('bảo vệ thông tin cá nhân', (string) $first->content);
    }

    /** @test */
    public function all_policy_pages_render_publicly(): void
    {
        foreach (StaticPage::POLICIES as $slug => $title) {
            $this->get('/'.$slug)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Policy')
                    ->where('page.title', StaticPage::policyDefaults($slug)['title'])
                    ->has('page.content')
                );
        }
    }

    /** @test */
    public function unknown_policy_slug_returns_404(): void
    {
        $this->get('/chinh-sach-khong-ton-tai')->assertNotFound();
    }

    /** @test */
    public function admin_update_sanitizes_content_and_saves_title(): void
    {
        $page = StaticPage::about();

        $this->actingAs($this->admin())
            ->put(route('admin.pages.update', $page), [
                'title' => 'Về shop',
                'content' => '<h2>Xin chào</h2><script>alert(1)</script>',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $page->refresh();
        $this->assertSame('Về shop', $page->title);
        $this->assertStringContainsString('<h2>Xin chào</h2>', $page->content);
        $this->assertStringNotContainsString('<script', $page->content);
    }

    /** @test */
    public function admin_update_cover_stores_file_and_drops_old(): void
    {
        Storage::fake('media');
        $page = StaticPage::about();
        $page->update(['cover_path' => 'pages/old.jpg']);
        Storage::disk('media')->put('pages/old.jpg', 'old');

        $this->actingAs($this->admin())
            ->post(route('admin.pages.update', $page), [
                '_method' => 'put',
                'title' => $page->title,
                'content' => $page->content,
                'cover' => UploadedFile::fake()->create('cover.jpg', 300, 'image/jpeg'),
            ])
            ->assertRedirect();

        $page->refresh();
        $this->assertNotSame('pages/old.jpg', $page->cover_path);
        Storage::disk('media')->assertExists($page->cover_path);
        Storage::disk('media')->assertMissing('pages/old.jpg');
    }

    /** @test */
    public function guest_cannot_access_admin_pages(): void
    {
        $page = StaticPage::about();

        $this->get('/admin/pages')->assertRedirect('/admin/login');
        $this->put(route('admin.pages.update', $page), ['title' => 'x'])->assertRedirect('/admin/login');
    }
}
