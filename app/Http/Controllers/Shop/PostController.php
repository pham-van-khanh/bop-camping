<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\Post;
use App\Models\Product;
use App\Services\MediaVariantService;
use App\Services\SeoService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cẩm nang phía khách (bopcamping-ennp): /cam-nang (danh sách) + /cam-nang/{slug}.
 *
 * Chỉ bài Post::published() mới hiện — nháp và bài hẹn giờ tương lai đều 404.
 * Mục tiêu SEO: bắt truy vấn dạng thông tin rồi dẫn người đọc sang trang thuê qua
 * khối "Đồ dùng trong bài" (sản phẩm/combo liên quan, nút "Thuê ngay").
 */
class PostController extends Controller
{
    private const PER_PAGE = 12;

    /** Ảnh mặc định cho og:image/schema khi bài chưa có ảnh nào. */
    private const FALLBACK_IMAGE = '/images/album/forest-camp-aerial.jpg';

    public function __construct(private SeoService $seo) {}

    public function index(Request $request): Response
    {
        $paginator = Post::published()->latestPublished()->paginate(self::PER_PAGE);

        // Trang vượt quá số trang thật = URL rác, đừng trả 200 với danh sách rỗng.
        abort_if($paginator->currentPage() > 1 && $paginator->isEmpty(), 404);

        MediaVariantService::warm($paginator->getCollection()->pluck('cover_path'));
        $paginator->through(fn (Post $p) => $this->card($p));

        $page = $paginator->currentPage();
        $url = url('/cam-nang').($page > 1 ? '?page='.$page : '');
        $title = 'Cẩm nang cắm trại'.($page > 1 ? ' — Trang '.$page : '');

        return Inertia::render('Posts', [
            'posts' => $paginator,
            'seo' => $this->seo->page(
                $this->seo->withBrand($title),
                'Cẩm nang cắm trại cho người mới: checklist đồ cần mang, cách chọn lều, '
                    .'kinh nghiệm cắm trại mùa mưa và mẹo dã ngoại thực tế từ đội BỐP CAMPING.',
                self::FALLBACK_IMAGE,
                $url,
                jsonld: $this->seo->breadcrumb([
                    ['Trang chủ', url('/')],
                    ['Cẩm nang', url('/cam-nang')],
                ]),
            ),
        ]);
    }

    public function show(string $slug): Response
    {
        $post = Post::published()->where('slug', $slug)->firstOrFail();

        $products = $post->products()->active()->orderBy('name')->get();
        $combos = $post->combos()->active()->with('images')->orderBy('name')->get();
        $related = Post::published()->latestPublished()
            ->whereKeyNot($post->id)
            ->limit(3)
            ->get();

        MediaVariantService::warm(collect([$post->cover_path])
            ->concat($products->pluck('thumbnail'))
            ->concat($combos->map(fn (Combo $c) => $c->images->first()?->path))
            ->concat($related->pluck('cover_path')));

        $cover = $post->cover_path ? MediaVariantService::payload($post->cover_path) : null;
        $url = url('/cam-nang/'.$post->slug);
        $image = $cover['url'] ?? $this->firstContentImage($post->content) ?? self::FALLBACK_IMAGE;
        $description = $post->seo_description ?: ($post->excerpt ?: $post->content);

        return Inertia::render('PostDetail', [
            'post' => [
                ...$this->card($post, $cover),
                'content' => $post->content,
                'updated_at' => $post->updated_at?->toIso8601String(),
            ],
            'products' => $this->productCards($products),
            'combos' => $this->comboCards($combos),
            'related' => $related->map(fn (Post $p) => $this->card($p))->values(),
            'seo' => $this->seo->page(
                $this->seo->withBrand($post->seo_title ?: $post->title),
                $description,
                $image,
                $url,
                jsonld: [
                    $this->seo->blogPosting(
                        $post->title,
                        $url,
                        $description,
                        $image,
                        $post->published_at,
                        $post->updated_at,
                        $post->author_name,
                    ),
                    $this->seo->breadcrumb([
                        ['Trang chủ', url('/')],
                        ['Cẩm nang', url('/cam-nang')],
                        [$post->title, $url],
                    ]),
                ],
            ),
        ]);
    }

    /**
     * Dữ liệu thẻ bài (danh sách + bài liên quan). Ảnh qua MediaVariantService (srcset).
     *
     * @param  array{url: string, srcset: string|null, width: int|null}|null  $cover
     */
    private function card(Post $p, ?array $cover = null): array
    {
        $cover ??= $p->cover_path ? MediaVariantService::payload($p->cover_path) : null;

        return [
            'id' => $p->id,
            'title' => $p->title,
            'slug' => $p->slug,
            'excerpt' => $p->excerpt ?: $this->seo->plainText($p->content, 160),
            'cover' => $cover ? ['url' => $cover['url'], 'srcset' => $cover['srcset']] : null,
            'published_at' => $p->published_at?->toIso8601String(),
            'published_label' => $p->published_at?->format('d/m/Y'),
            'reading_minutes' => $p->readingMinutes(),
            'author' => $p->author_name ?: SeoService::BRAND,
        ];
    }

    /** @param Collection<int, Product> $products */
    private function productCards(Collection $products): array
    {
        return $products->map(function (Product $p) {
            $thumb = $p->thumbnail ? MediaVariantService::payload($p->thumbnail) : null;

            return [
                'id' => $p->id,
                'name' => $p->name,
                'url' => '/thiet-bi/'.$p->slug,
                'price' => (int) $p->price_per_day,
                'image' => $thumb['url'] ?? null,
                'srcset' => $thumb['srcset'] ?? null,
            ];
        })->values()->all();
    }

    /** @param Collection<int, Combo> $combos */
    private function comboCards(Collection $combos): array
    {
        return $combos->map(function (Combo $c) {
            $first = $c->images->first();
            $img = $first ? MediaVariantService::payload($first->path) : null;

            return [
                'id' => $c->id,
                'name' => $c->name,
                'url' => '/combos/'.$c->slug,
                'price' => (int) $c->combo_price,
                'image' => $img['url'] ?? null,
                'srcset' => $img['srcset'] ?? null,
            ];
        })->values()->all();
    }

    /** Ảnh đầu tiên trong nội dung — dùng làm og:image khi bài không có ảnh bìa. */
    private function firstContentImage(?string $html): ?string
    {
        return $html && preg_match('/<img[^>]+src="([^"]+)"/i', $html, $m) ? $m[1] : null;
    }
}
