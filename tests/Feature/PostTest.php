<?php

namespace Tests\Feature;

use App\Jobs\GenerateMediaVariants;
use App\Models\Combo;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PostSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsSeoTitle;
use Tests\TestCase;

/**
 * bopcamping-ennp — Cẩm nang: admin CRUD, trang khách, SEO/JSON-LD, sitemap.
 */
class PostTest extends TestCase
{
    use AssertsSeoTitle;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // sitemap cache 1h
    }

    private function admin(): User
    {
        return User::firstOrCreate(
            ['phone' => '0976544370'],
            ['name' => 'Admin', 'email' => 'admin@bop.test', 'is_admin' => true],
        );
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Checklist đồ cắm trại cho người mới',
            'slug' => '',
            'excerpt' => 'Danh sách đồ cần mang cho chuyến cắm trại đầu tiên.',
            'content' => '<h2>Đồ ngủ</h2><p>Lều, túi ngủ, tấm lót.</p>',
            'status' => Post::STATUS_DRAFT,
        ], $overrides);
    }

    // ── Admin: quyền truy cập ────────────────────────────────────────────

    /** @test */
    public function guest_cannot_access_admin_posts(): void
    {
        $this->get(route('admin.posts'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.posts.create'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.posts.store'), $this->payload())->assertRedirect(route('admin.login'));
        $this->assertSame(0, Post::count());
    }

    /** @test */
    public function non_admin_user_cannot_create_post(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->post(route('admin.posts.store'), $this->payload());

        $this->assertSame(0, Post::count());
    }

    /** @test */
    public function admin_sees_post_list_and_form(): void
    {
        Post::factory()->create(['title' => 'Bài A']);

        $this->actingAs($this->admin())->get(route('admin.posts'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Posts')->has('posts.data', 1));

        $this->actingAs($this->admin())->get(route('admin.posts.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/PostForm')->where('post', null)->has('products')->has('combos'));
    }

    // ── Admin: tạo / sửa / xoá ───────────────────────────────────────────

    /** @test */
    public function admin_creates_post_with_auto_slug_cover_and_relations(): void
    {
        Storage::fake('media');
        Queue::fake();
        $product = Product::factory()->create();
        $combo = Combo::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.posts.store'), $this->payload([
            'cover' => UploadedFile::fake()->image('cover.jpg', 1600, 900),
            'product_ids' => [$product->id],
            'combo_ids' => [$combo->id],
            'author_name' => 'Khánh',
        ]))->assertRedirect()->assertSessionHas('success');

        $post = Post::firstOrFail();
        $this->assertSame('checklist-do-cam-trai-cho-nguoi-moi', $post->slug);
        $this->assertSame(Post::STATUS_DRAFT, $post->status);
        $this->assertNull($post->published_at);
        Storage::disk('media')->assertExists($post->cover_path);
        Queue::assertPushed(GenerateMediaVariants::class, fn ($job) => $job->sourcePaths === [$post->cover_path]);
        $this->assertSame([$product->id], $post->products->pluck('id')->all());
        $this->assertSame([$combo->id], $post->combos->pluck('id')->all());
    }

    /** @test */
    public function publishing_without_date_sets_published_at_now(): void
    {
        $this->actingAs($this->admin())->post(route('admin.posts.store'), $this->payload([
            'status' => Post::STATUS_PUBLISHED,
        ]));

        $post = Post::firstOrFail();
        $this->assertNotNull($post->published_at);
        $this->assertTrue($post->isPublic());
    }

    /** @test */
    public function editing_published_post_keeps_original_publish_date(): void
    {
        $post = Post::factory()->create(['published_at' => now()->subMonth()]);
        $original = $post->published_at->toDateTimeString();

        $this->actingAs($this->admin())->put(route('admin.posts.update', $post), $this->payload([
            'title' => 'Tiêu đề đã sửa',
            'slug' => $post->slug,
            'status' => Post::STATUS_PUBLISHED,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($original, $post->fresh()->published_at->toDateTimeString());
        $this->assertSame('Tiêu đề đã sửa', $post->fresh()->title);
    }

    /** @test */
    public function validation_rejects_missing_title_bad_status_and_bad_slug(): void
    {
        $this->actingAs($this->admin())->post(route('admin.posts.store'), [
            'title' => '',
            'status' => 'archived',
            'slug' => 'Có Dấu Và Khoảng Trắng',
        ])->assertSessionHasErrors(['title', 'status', 'slug']);

        $this->assertSame(0, Post::count());
    }

    /** @test */
    public function published_post_requires_content(): void
    {
        $this->actingAs($this->admin())->post(route('admin.posts.store'), $this->payload([
            'status' => Post::STATUS_PUBLISHED,
            'content' => '',
        ]))->assertSessionHasErrors('content');
    }

    /** @test */
    public function explicit_duplicate_slug_is_rejected(): void
    {
        Post::factory()->create(['slug' => 'chon-leu']);

        $this->actingAs($this->admin())->post(route('admin.posts.store'), $this->payload([
            'slug' => 'chon-leu',
        ]))->assertSessionHasErrors('slug');
    }

    /** @test */
    public function auto_slug_gets_suffix_when_title_repeats(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.posts.store'), $this->payload());
        $this->actingAs($admin)->post(route('admin.posts.store'), $this->payload());

        $this->assertEqualsCanonicalizing(
            ['checklist-do-cam-trai-cho-nguoi-moi', 'checklist-do-cam-trai-cho-nguoi-moi-1'],
            Post::pluck('slug')->all(),
        );
    }

    /** @test */
    public function updating_post_may_keep_its_own_slug(): void
    {
        $post = Post::factory()->create(['slug' => 'giu-nguyen']);

        $this->actingAs($this->admin())->put(route('admin.posts.update', $post), $this->payload([
            'slug' => 'giu-nguyen',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('giu-nguyen', $post->fresh()->slug);
    }

    /** @test */
    public function content_is_sanitized_on_save(): void
    {
        $this->actingAs($this->admin())->post(route('admin.posts.store'), $this->payload([
            'content' => '<p onclick="alert(1)">Xin chào</p><script>alert("xss")</script>'
                .'<img src="https://example.com/a.jpg" onerror="alert(2)" alt="a">'
                .'<a href="javascript:alert(3)">link</a>',
        ]));

        $html = Post::firstOrFail()->content;
        $this->assertStringContainsString('Xin chào', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    /** @test */
    public function sanitization_also_applies_outside_admin_controller(): void
    {
        $post = Post::factory()->create(['content' => '<p>Ok</p><script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script', $post->fresh()->content);
    }

    /** @test */
    public function replacing_cover_deletes_old_file(): void
    {
        Storage::fake('media');
        Queue::fake();
        $old = UploadedFile::fake()->image('old.jpg')->store('posts', 'media');
        $post = Post::factory()->create(['cover_path' => $old]);

        $this->actingAs($this->admin())->put(route('admin.posts.update', $post), $this->payload([
            'slug' => $post->slug,
            'cover' => UploadedFile::fake()->image('new.jpg', 1200, 800),
        ]))->assertSessionHasNoErrors();

        Storage::disk('media')->assertMissing($old);
        Storage::disk('media')->assertExists($post->fresh()->cover_path);
    }

    /** @test */
    public function admin_deletes_post_and_cover(): void
    {
        Storage::fake('media');
        $cover = UploadedFile::fake()->image('c.jpg')->store('posts', 'media');
        $post = Post::factory()->create(['cover_path' => $cover]);
        $post->products()->attach(Product::factory()->create());

        $this->actingAs($this->admin())->delete(route('admin.posts.destroy', $post))
            ->assertRedirect(route('admin.posts'));

        $this->assertSame(0, Post::count());
        $this->assertDatabaseCount('post_product', 0);
        Storage::disk('media')->assertMissing($cover);
    }

    // ── Trang khách ──────────────────────────────────────────────────────

    /** @test */
    public function public_list_shows_only_published_posts_newest_first(): void
    {
        Post::factory()->create(['title' => 'Cũ', 'slug' => 'cu', 'published_at' => now()->subDays(5)]);
        Post::factory()->create(['title' => 'Mới', 'slug' => 'moi', 'published_at' => now()->subDay()]);
        Post::factory()->draft()->create(['title' => 'Nháp', 'slug' => 'nhap']);
        Post::factory()->scheduled()->create(['title' => 'Hẹn giờ', 'slug' => 'hen-gio']);

        $this->get('/cam-nang')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Posts')
                ->has('posts.data', 2)
                ->where('posts.data.0.slug', 'moi')
                ->where('posts.data.1.slug', 'cu'));
    }

    /** @test */
    public function out_of_range_page_is_404(): void
    {
        Post::factory()->create();

        $this->get('/cam-nang?page=5')->assertNotFound();
    }

    /** @test */
    public function draft_and_scheduled_posts_are_404(): void
    {
        Post::factory()->draft()->create(['slug' => 'nhap']);
        Post::factory()->scheduled()->create(['slug' => 'hen-gio']);

        $this->get('/cam-nang/nhap')->assertNotFound();
        $this->get('/cam-nang/hen-gio')->assertNotFound();
        $this->get('/cam-nang/khong-ton-tai')->assertNotFound();
    }

    /** @test */
    public function article_page_renders_content_relations_and_related_posts(): void
    {
        $post = Post::factory()->create(['slug' => 'chon-leu', 'title' => 'Chọn lều 2 người hay 4 người']);
        $active = Product::factory()->create(['name' => 'Lều 4 người']);
        $hidden = Product::factory()->create(['status' => 'hidden']);
        $post->products()->attach([$active->id, $hidden->id]);
        $post->combos()->attach(Combo::factory()->create(['is_active' => true]));
        Post::factory()->create(['slug' => 'bai-khac']);
        Post::factory()->draft()->create(['slug' => 'nhap-khac']);

        $this->get('/cam-nang/chon-leu')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('PostDetail')
                ->where('post.title', 'Chọn lều 2 người hay 4 người')
                ->where('post.reading_minutes', 1)
                ->has('products', 1)
                ->where('products.0.name', 'Lều 4 người')
                ->where('products.0.url', '/thiet-bi/'.$active->slug)
                ->has('combos', 1)
                ->has('related', 1)
                ->where('related.0.slug', 'bai-khac'));
    }

    // ── SEO ─────────────────────────────────────────────────────────────

    /** @test */
    public function index_has_own_seo_and_breadcrumb(): void
    {
        Post::factory()->create();

        $this->get('/cam-nang')
            ->assertInertia(fn (Assert $p) => $p
                ->where('seo.title', 'Cẩm nang cắm trại | BỐP CAMPING')
                ->where('seo.url', url('/cam-nang'))
                ->where('seo.jsonld.@type', 'BreadcrumbList'))
            ->assertSee('"BreadcrumbList"', false)
            ->assertDontSee('"BlogPosting"', false);
    }

    /** @test */
    public function server_title_is_seo_title_with_brand_once_on_list_and_article(): void
    {
        Post::factory()->create(['slug' => 'chon-leu', 'title' => 'Chọn lều 2 người hay 4 người']);
        Post::factory()->create(['slug' => 'co-brand', 'title' => 'Mẹo gấp lều — Bốp Camping']);

        $this->assertSame('Cẩm nang cắm trại | BỐP CAMPING', $this->assertSeoTitleSingleSource($this->get('/cam-nang')));
        $this->assertSame(
            'Chọn lều 2 người hay 4 người | BỐP CAMPING',
            $this->assertSeoTitleSingleSource($this->get('/cam-nang/chon-leu')),
        );
        // Tiêu đề admin đã tự gõ brand (khác hoa/thường) → không nối thêm.
        $this->assertSame('Mẹo gấp lều — Bốp Camping', $this->get('/cam-nang/co-brand')->viewData('page')['props']['seo']['title']);
    }

    /** @test */
    public function article_seo_uses_overrides_and_brand_once(): void
    {
        Post::factory()->create([
            'slug' => 'mua-mua',
            'title' => 'Cắm trại mùa mưa',
            'seo_title' => 'Kinh nghiệm cắm trại mùa mưa',
            'seo_description' => 'Mẹo giữ lều khô ráo, chọn chỗ dựng trại và đồ cần mang khi cắm trại mùa mưa — viết cho người mới bắt đầu đi dã ngoại lần đầu.',
        ]);

        $this->get('/cam-nang/mua-mua')
            ->assertInertia(fn (Assert $p) => $p
                ->where('seo.title', 'Kinh nghiệm cắm trại mùa mưa | BỐP CAMPING')
                ->where('seo.description', 'Mẹo giữ lều khô ráo, chọn chỗ dựng trại và đồ cần mang khi cắm trại mùa mưa — viết cho người mới bắt đầu đi dã ngoại lần đầu.')
                ->where('seo.url', url('/cam-nang/mua-mua')));
    }

    /** @test */
    public function article_seo_falls_back_to_title_and_excerpt(): void
    {
        Post::factory()->create([
            'slug' => 'x',
            'title' => 'Chọn túi ngủ — BỐP CAMPING',
            'excerpt' => 'Tóm tắt bài.',
        ]);

        $this->get('/cam-nang/x')
            ->assertInertia(fn (Assert $p) => $p
                ->where('seo.title', 'Chọn túi ngủ — BỐP CAMPING')
                ->where('seo.description', 'Tóm tắt bài.'));
    }

    /** @test */
    public function article_has_blogposting_and_breadcrumb_schema(): void
    {
        Post::factory()->create(['slug' => 'chon-leu', 'title' => 'Chọn lều', 'author_name' => 'Khánh']);

        $res = $this->get('/cam-nang/chon-leu')->assertOk();
        $res->assertSee('"BlogPosting"', false)->assertSee('"BreadcrumbList"', false);

        $res->assertInertia(fn (Assert $p) => $p
            ->where('seo.jsonld.0.@type', 'BlogPosting')
            ->where('seo.jsonld.0.headline', 'Chọn lều')
            ->where('seo.jsonld.0.author.name', 'Khánh')
            ->where('seo.jsonld.0.publisher.@type', 'Organization')
            ->has('seo.jsonld.0.datePublished')
            ->has('seo.jsonld.0.dateModified')
            ->has('seo.jsonld.0.image')
            ->where('seo.jsonld.1.@type', 'BreadcrumbList')
            ->where('seo.jsonld.1.itemListElement.1.item', url('/cam-nang')));
    }

    /** @test */
    public function product_page_has_no_blogposting_schema(): void
    {
        $product = Product::factory()->create();

        $this->get('/thiet-bi/'.$product->slug)
            ->assertOk()
            ->assertDontSee('"BlogPosting"', false);
    }

    // ── Seeder ──────────────────────────────────────────────────────────

    /** @test */
    public function dev_seeder_only_creates_drafts(): void
    {
        $this->seed(PostSeeder::class);

        $this->assertSame(3, Post::count());
        $this->assertSame(0, Post::published()->count());
        $this->assertStringContainsString('<h2>', Post::first()->content);
        $this->get('/cam-nang/checklist-do-cam-trai-cho-nguoi-moi')->assertNotFound();
    }

    // ── Sitemap ─────────────────────────────────────────────────────────

    /** @test */
    public function sitemap_lists_published_posts_only(): void
    {
        Post::factory()->create(['slug' => 'da-dang']);
        Post::factory()->draft()->create(['slug' => 'ban-nhap']);
        Post::factory()->scheduled()->create(['slug' => 'hen-gio']);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.url('/cam-nang').'</loc>', $xml);
        $this->assertStringContainsString(url('/cam-nang/da-dang'), $xml);
        $this->assertStringNotContainsString('ban-nhap', $xml);
        $this->assertStringNotContainsString('hen-gio', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    /** @test */
    public function sitemap_omits_empty_post_index(): void
    {
        Post::factory()->draft()->create();

        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringNotContainsString('/cam-nang', $xml);
    }

    /**
     * Tiêu đề bài đi thẳng vào JSON-LD (breadcrumb name, BlogPosting headline) và được
     * in RAW bằng {!! json_encode() !!} trong <head>. Không escape '<' thì tiêu đề chứa
     * "</script>" phá khối ld+json và chèn script tuỳ ý cho mọi khách (CWE-79).
     * headline còn đi qua plainText() vốn GIẢI MÃ thực thể, nên "&lt;/script&gt;" cũng thành
     * "</script>" — phải escape ở tầng in ra, không phải ở tầng dữ liệu.
     *
     * @test
     */
    public function jsonld_cannot_break_out_of_script_tag_via_title(): void
    {
        Post::factory()->create([
            'slug' => 'xss-title',
            'title' => '</script><script>alert(1)</script>',
            'excerpt' => '&lt;/script&gt;&lt;script&gt;alert(2)&lt;/script&gt;',
        ]);

        $html = $this->get('/cam-nang/xss-title')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(', $html);
        // JSON-LD vẫn hợp lệ và giữ đúng tiêu đề sau khi giải mã.
        preg_match_all('#application/ld\+json">(.*?)</script>#s', $html, $m);
        $types = [];
        foreach ($m[1] as $block) {
            $data = json_decode($block, true, flags: JSON_THROW_ON_ERROR);
            foreach (array_is_list($data) ? $data : [$data] as $node) {
                $types[] = $node['@type'];
                if ($node['@type'] === 'BreadcrumbList') {
                    $this->assertSame('</script><script>alert(1)</script>', end($node['itemListElement'])['name']);
                }
            }
        }
        $this->assertContains('BlogPosting', $types);
        $this->assertContains('BreadcrumbList', $types);
    }

    /** og:image lấy từ ảnh đầu bài phải là URL thật, không còn &amp; do Purifier escape. @test */
    public function og_image_from_content_is_entity_decoded(): void
    {
        Post::factory()->create([
            'slug' => 'anh-trong-bai',
            'cover_path' => null,
            'content' => '<p>Xem ảnh</p><img src="https://cdn.example.com/a.jpg?w=800&h=600" alt="a">',
        ]);

        $this->get('/cam-nang/anh-trong-bai')->assertInertia(fn (Assert $p) => $p
            ->where('seo.image', 'https://cdn.example.com/a.jpg?w=800&h=600'));
    }
}
