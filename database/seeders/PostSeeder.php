<?php

namespace Database\Seeders;

use App\Models\Combo;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Bài Cẩm nang soạn sẵn (bopcamping-ennp) — nội dung nằm ở `data/cam_nang_posts.json`
 * (SINGLE SOURCE: sửa bài thì sửa file JSON, không viết HTML vào class này).
 *
 * AN TOÀN KHI CHẠY TRÊN PRODUCTION — khác các seeder dữ liệu mẫu khác (xem DatabaseSeeder):
 *
 *     php artisan db:seed --class=PostSeeder --force
 *
 * - Chỉ TẠO bài chưa có slug. Bài đã có (kể cả bài chủ shop đã sửa, đã đăng) thì bỏ qua
 *   nguyên vẹn — không ghi đè tiêu đề, nội dung, trạng thái hay sản phẩm liên quan.
 * - Bài mới luôn là BẢN NHÁP (status=draft, published_at=null): không tự hiện ra trang
 *   khách hay sitemap. Chủ shop đọc lại, thêm ảnh bìa rồi bấm đăng trong admin.
 * - Sản phẩm/combo liên quan gắn theo slug; slug nào không có trong DB thì lặng lẽ bỏ qua
 *   (dữ liệu dev và production đặt tên khác nhau).
 * - Chạy lại bao nhiêu lần cũng được (idempotent).
 *
 * Nội dung đi qua EditorHtml::clean ở mutator của Post như mọi đường ghi khác; test
 * PostSeederTest kiểm rằng bộ lọc không làm mất chữ, tiêu đề mục hay link nào.
 *
 * DatabaseSeeder vẫn chỉ gọi seeder này ở local/testing — trên production phải gọi tay
 * bằng lệnh ở trên, có chủ đích.
 */
class PostSeeder extends Seeder
{
    public const DATA_FILE = __DIR__.'/data/cam_nang_posts.json';

    public function run(): void
    {
        $created = 0;

        foreach (self::articles() as $article) {
            if (Post::where('slug', $article['slug'])->exists()) {
                continue;
            }

            $post = Post::create([
                'slug' => $article['slug'],
                'title' => $article['title'],
                'excerpt' => $article['excerpt'],
                'content' => $article['content_html'],
                'seo_title' => $article['seo_title'],
                'seo_description' => $article['seo_description'],
                'author_name' => $article['author_name'],
                'status' => Post::STATUS_DRAFT,
                'published_at' => null,
            ]);

            // whereIn theo slug: slug không có trong DB tự rơi ra, không báo lỗi.
            $post->products()->sync(Product::whereIn('slug', $article['related_product_slugs'])->pluck('id'));
            $post->combos()->sync(Combo::whereIn('slug', $article['related_combo_slugs'])->pluck('id'));

            $created++;
        }

        $this->command?->info("Cẩm nang: tạo {$created} bản nháp mới (bài đã có slug được giữ nguyên).");
    }

    /**
     * Danh sách bài đọc từ file JSON.
     *
     * @return list<array{slug: string, title: string, excerpt: string, seo_title: string,
     *     seo_description: string, author_name: string, content_html: string,
     *     related_product_slugs: list<string>, related_combo_slugs: list<string>}>
     */
    public static function articles(): array
    {
        $raw = file_get_contents(self::DATA_FILE);
        if ($raw === false) {
            throw new RuntimeException('Không đọc được '.self::DATA_FILE);
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
