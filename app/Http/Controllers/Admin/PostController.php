<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateMediaVariants;
use App\Models\Combo;
use App\Models\Post;
use App\Models\Product;
use App\Services\MediaVariantService;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin "Cẩm nang" (bopcamping-ennp): CRUD bài viết.
 *
 * - Nội dung soạn bằng RichTextEditor (TipTap) như Trang nội dung; sanitize nằm ở
 *   Post model (mutator `content`) nên mọi đường ghi đều qua EditorHtml::clean.
 * - Ảnh bìa lưu disk `media`, biến thể WebP sinh qua GenerateMediaVariants
 *   (MediaVariantService là single source — không resize ở đây).
 */
class PostController extends Controller
{
    public function index(): Response
    {
        $posts = Post::query()
            ->orderByRaw('published_at IS NULL DESC')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (Post $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'status' => $p->status,
                'is_public' => $p->isPublic(),
                'published_at' => $p->published_at?->format('d/m/Y H:i'),
                'updated_at' => $p->updated_at?->format('d/m/Y H:i'),
                'cover_url' => $p->cover_path ? Storage::disk('media')->url($p->cover_path) : null,
            ]);

        return Inertia::render('Admin/Posts', ['posts' => $posts]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/PostForm', [
            'post' => null,
            ...$this->options(),
        ]);
    }

    public function edit(Post $post): Response
    {
        $post->load('products:id', 'combos:id');

        return Inertia::render('Admin/PostForm', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'content' => $post->content,
                'cover_url' => $post->cover_path ? Storage::disk('media')->url($post->cover_path) : null,
                'status' => $post->status,
                // Dạng cho <input type="datetime-local">.
                'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
                'seo_title' => $post->seo_title,
                'seo_description' => $post->seo_description,
                'author_name' => $post->author_name,
                'product_ids' => $post->products->pluck('id')->values(),
                'combo_ids' => $post->combos->pluck('id')->values(),
                'is_public' => $post->isPublic(),
            ],
            ...$this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $post = new Post($this->attributes($data));
        $post->slug = filled($data['slug'] ?? null) ? $data['slug'] : Slug::unique(Post::class, $data['title']);

        if ($request->hasFile('cover')) {
            $post->cover_path = $this->storeCover($request);
        }

        $post->save();
        $this->syncRelations($post, $data);

        return redirect()->route('admin.posts.edit', $post)->with('success', 'Đã tạo bài viết.');
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $data = $this->validated($request, $post);

        $post->fill($this->attributes($data, $post));
        $post->slug = filled($data['slug'] ?? null) ? $data['slug'] : Slug::unique(Post::class, $data['title'], $post->id);

        if ($request->hasFile('cover')) {
            $this->deleteCover($post);
            $post->cover_path = $this->storeCover($request);
        } elseif ($request->boolean('remove_cover')) {
            $this->deleteCover($post);
            $post->cover_path = null;
        }

        $post->save();
        $this->syncRelations($post, $data);

        return back()->with('success', 'Đã lưu bài viết.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->deleteCover($post);
        $post->delete();

        return redirect()->route('admin.posts')->with('success', 'Đã xoá bài viết.');
    }

    /** Danh sách sản phẩm / combo để chọn "liên quan". */
    private function options(): array
    {
        return [
            'products' => Product::orderBy('name')->get(['id', 'name', 'status'])
                ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'active' => $p->status === 'active'])
                ->values(),
            'combos' => Combo::orderBy('name')->get(['id', 'name', 'is_active'])
                ->map(fn (Combo $c) => ['id' => $c->id, 'name' => $c->name, 'active' => (bool) $c->is_active])
                ->values(),
            'statuses' => Post::STATUSES,
        ];
    }

    private function validated(Request $request, ?Post $post = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:200'],
            // Slug do admin sửa: chỉ chữ thường không dấu, số và gạch nối. Bỏ trống = tự sinh.
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('posts', 'slug')->ignore($post?->id)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'required_if:status,'.Post::STATUS_PUBLISHED, 'string', 'max:200000'],
            'cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_cover' => ['boolean'],
            'status' => ['required', Rule::in(array_keys(Post::STATUSES))],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:300'],
            'author_name' => ['nullable', 'string', 'max:100'],
            'product_ids' => ['nullable', 'array', 'max:12'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'combo_ids' => ['nullable', 'array', 'max:12'],
            'combo_ids.*' => ['integer', 'exists:combos,id'],
        ], [
            'title.required' => 'Tiêu đề không được bỏ trống.',
            'slug.regex' => 'Đường dẫn chỉ gồm chữ thường không dấu, số và dấu gạch nối.',
            'slug.unique' => 'Đường dẫn này đã có bài khác dùng.',
            'content.required_if' => 'Bài đăng công khai phải có nội dung.',
            'cover.mimes' => 'Ảnh bìa chỉ nhận jpg, png hoặc webp.',
            'cover.max' => 'Ảnh bìa tối đa 8MB.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'product_ids.*.exists' => 'Sản phẩm liên quan không hợp lệ.',
            'combo_ids.*.exists' => 'Combo liên quan không hợp lệ.',
        ]);
    }

    /** Cột ghi thẳng vào bảng posts (slug/cover xử lý riêng). */
    private function attributes(array $data, ?Post $post = null): array
    {
        $publishedAt = $data['published_at'] ?? null;
        // Đăng mà chưa đặt giờ → đăng ngay. Giữ nguyên giờ cũ nếu bài đã từng đăng,
        // để sửa lỗi chính tả không đẩy bài lên đầu danh sách như bài mới.
        if ($data['status'] === Post::STATUS_PUBLISHED && blank($publishedAt)) {
            $publishedAt = $post?->published_at ?? now();
        }

        return [
            'title' => $data['title'],
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'] ?? null,
            'status' => $data['status'],
            'published_at' => $publishedAt,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'author_name' => $data['author_name'] ?? null,
        ];
    }

    private function syncRelations(Post $post, array $data): void
    {
        $post->products()->sync($data['product_ids'] ?? []);
        $post->combos()->sync($data['combo_ids'] ?? []);
    }

    private function storeCover(Request $request): string
    {
        $path = $request->file('cover')->store('posts', 'media');
        GenerateMediaVariants::dispatch([$path]);

        return $path;
    }

    private function deleteCover(Post $post): void
    {
        if ($post->cover_path) {
            MediaVariantService::make()->forget($post->cover_path);
            Storage::disk('media')->delete($post->cover_path);
        }
    }
}
