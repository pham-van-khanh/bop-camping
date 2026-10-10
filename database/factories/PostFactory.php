<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = 'Bài viết '.fake()->unique()->numberBetween(1, 999999);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => 'Tóm tắt ngắn của bài viết dùng cho thẻ danh sách.',
            'content' => '<p>Nội dung bài viết mẫu.</p>',
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => Post::STATUS_DRAFT, 'published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(['status' => Post::STATUS_PUBLISHED, 'published_at' => now()->addDays(3)]);
    }
}
