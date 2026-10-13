<?php

namespace Tests\Feature;

use App\Models\Combo;
use App\Models\Post;
use App\Models\Product;
use App\Support\EditorHtml;
use Database\Seeders\PostSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * bopcamping-ennp — seeder bài Cẩm nang đọc từ database/seeders/data/cam_nang_posts.json,
 * chạy được trên production: chỉ tạo bản nháp mới, không ghi đè bài đã có.
 */
class PostSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Đường dẫn nội bộ bài viết được phép trỏ tới (đều là route khách có thật). */
    private const LINK_PATTERNS = [
        '#^/thiet-bi$#',
        '#^/thiet-bi/[a-z0-9-]+$#',
        '#^/thiet-bi\?cat=[a-z0-9-]+$#',
        '#^/combos$#',
        '#^/combos/[a-z0-9-]+$#',
        '#^/cam-nang$#',
        '#^/cam-nang/[a-z0-9-]+$#',
        '#^/thue-do-cam-trai/[a-z0-9-]+$#',
        '#^/diem-cam-trai(/[a-z0-9-]+)?$#',
    ];

    /** @return array<int, array<string, mixed>> */
    private function articles(): array
    {
        return PostSeeder::articles();
    }

    /** @test */
    public function data_file_has_eight_complete_articles_with_unique_slugs(): void
    {
        $articles = $this->articles();

        $this->assertCount(8, $articles);
        $this->assertSame(count($articles), count(array_unique(array_column($articles, 'slug'))));

        foreach ($articles as $a) {
            foreach (['slug', 'title', 'excerpt', 'seo_title', 'seo_description', 'author_name', 'content_html'] as $key) {
                $this->assertIsString($a[$key], "{$a['slug']}.{$key}");
                $this->assertNotSame('', trim($a[$key]), "{$a['slug']}.{$key}");
            }
            $this->assertIsArray($a['related_product_slugs']);
            $this->assertIsArray($a['related_combo_slugs']);
            $this->assertLessThanOrEqual(200, mb_strlen($a['title']));
            $this->assertLessThanOrEqual(300, mb_strlen($a['seo_description']));
        }
    }

    /** @test */
    public function seeding_creates_every_article_as_a_draft(): void
    {
        $this->seed(PostSeeder::class);

        $this->assertSame(count($this->articles()), Post::count());
        $this->assertSame(0, Post::published()->count());
        $this->assertSame(0, Post::whereNotNull('published_at')->count());

        $post = Post::where('slug', 'cam-trai-mua-mua')->firstOrFail();
        $this->assertSame(Post::STATUS_DRAFT, $post->status);
        $this->assertSame('Đội ngũ BỐP CAMPING', $post->author_name);
        $this->assertStringContainsString('<h2>', (string) $post->content);

        // Bản nháp không lộ ra trang khách.
        $this->get('/cam-nang/cam-trai-mua-mua')->assertNotFound();
    }

    /** @test */
    public function running_twice_creates_no_duplicates(): void
    {
        $this->seed(PostSeeder::class);
        $this->seed(PostSeeder::class);

        $this->assertSame(count($this->articles()), Post::count());
    }

    /** @test */
    public function an_existing_post_edited_by_the_admin_is_left_untouched(): void
    {
        $edited = Post::factory()->create([
            'slug' => 'chon-leu-2-nguoi-hay-4-nguoi',
            'title' => 'Bài chủ shop đã sửa',
            'content' => '<p>Nội dung riêng của shop.</p>',
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
        $product = Product::factory()->create(['slug' => 'leu-cam-trai-village-60-danh-cho-4-nguoi-dung-nhanh-chong']);

        $this->seed(PostSeeder::class);

        $edited->refresh();
        $this->assertSame('Bài chủ shop đã sửa', $edited->title);
        $this->assertSame('<p>Nội dung riêng của shop.</p>', $edited->content);
        $this->assertSame(Post::STATUS_PUBLISHED, $edited->status);
        $this->assertNotNull($edited->published_at);
        // Không gắn thêm sản phẩm liên quan vào bài đã có.
        $this->assertFalse($edited->products()->whereKey($product->id)->exists());
        $this->assertSame(count($this->articles()), Post::count());
    }

    /** @test */
    public function related_items_attach_by_slug_and_missing_slugs_are_skipped(): void
    {
        $tent = Product::factory()->create(['slug' => 'leu-cam-trai-village-60-danh-cho-4-nguoi-dung-nhanh-chong']);
        $unrelated = Product::factory()->create(['slug' => 'mon-khong-lien-quan']);
        $couples = Combo::factory()->create(['slug' => 'camping-couples']);

        $this->seed(PostSeeder::class);

        $post = Post::where('slug', 'chon-leu-2-nguoi-hay-4-nguoi')->firstOrFail();
        // Bài này liệt kê 4 sản phẩm + 4 combo; DB test chỉ có 1 món mỗi loại → chỉ món đó được gắn.
        $this->assertSame([$tent->id], $post->products()->pluck('products.id')->all());
        $this->assertSame([$couples->id], $post->combos()->pluck('combos.id')->all());
        $this->assertSame(0, DB::table('post_product')->where('product_id', $unrelated->id)->count());

        // Bài không nhắc tới camping-couples thì không bị gắn nhầm.
        $rain = Post::where('slug', 'cam-trai-mua-mua')->firstOrFail();
        $this->assertSame(0, $rain->combos()->count());
    }

    /** @test */
    public function sanitizer_keeps_all_text_headings_and_links(): void
    {
        foreach ($this->articles() as $a) {
            $original = $a['content_html'];
            $clean = (string) EditorHtml::clean($original);

            $this->assertSame($this->text($original), $this->text($clean), "{$a['slug']}: chữ bị đổi khi lọc");
            $this->assertSame($this->headings($original), $this->headings($clean), "{$a['slug']}: mất tiêu đề mục");
            $this->assertSame($this->hrefs($original), $this->hrefs($clean), "{$a['slug']}: mất link");
            $this->assertNotEmpty($this->hrefs($clean), "{$a['slug']}: bài không có link nội bộ nào");
        }
    }

    /** @test */
    public function every_internal_link_points_to_an_existing_customer_route(): void
    {
        $slugs = array_column($this->articles(), 'slug');

        foreach ($this->articles() as $a) {
            foreach ($this->hrefs($a['content_html']) as $href) {
                $this->assertStringStartsWith('/', $href, "{$a['slug']}: link ngoài {$href}");

                $matched = array_filter(self::LINK_PATTERNS, fn (string $re) => preg_match($re, $href) === 1);
                $this->assertNotEmpty($matched, "{$a['slug']}: link {$href} không thuộc route khách nào");

                // Router của app phải khớp được đường dẫn này với một route GET.
                $path = (string) parse_url($href, PHP_URL_PATH);
                try {
                    $route = Route::getRoutes()->match(Request::create($path, 'GET'));
                } catch (HttpException) {
                    $route = null;
                }
                $this->assertNotNull($route, "{$a['slug']}: {$href} không khớp route nào");

                if (preg_match('#^/cam-nang/([a-z0-9-]+)$#', $href, $m) === 1) {
                    $this->assertContains($m[1], $slugs, "{$a['slug']}: {$href} trỏ tới bài không có trong JSON");
                }
            }
        }
    }

    // ── helpers ─────────────────────────────────────────────────────────

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>');
        libxml_clear_errors();

        return new DOMXPath($doc);
    }

    /** Chữ hiển thị, gộp khoảng trắng (Purifier có thể đổi xuống dòng giữa các thẻ). */
    private function text(string $html): string
    {
        $text = $this->xpath($html)->evaluate('string(//body)');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** @return list<string> */
    private function headings(string $html): array
    {
        $out = [];
        foreach ($this->xpath($html)->query('//h2|//h3|//h4') as $node) {
            $out[] = $node->nodeName.':'.trim($node->textContent);
        }

        return $out;
    }

    /** @return list<string> */
    private function hrefs(string $html): array
    {
        $out = [];
        foreach ($this->xpath($html)->query('//a[@href]') as $node) {
            $out[] = $node->getAttribute('href');
        }

        return $out;
    }
}
