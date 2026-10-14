<?php

namespace App\Models;

use App\Support\EditorHtml;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Bài viết "Cẩm nang" (bopcamping-ennp).
 *
 * Công khai = status 'published' VÀ published_at <= now (scopePublished) — bài hẹn giờ
 * đăng tương lai và bản nháp đều 404 phía khách. Đây là nơi DUY NHẤT định nghĩa
 * "bài đang hiện": danh sách, trang chi tiết, bài liên quan và sitemap cùng gọi scope này.
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Bản nháp',
        self::STATUS_PUBLISHED => 'Đã đăng',
    ];

    /** Tốc độ đọc tiếng Việt (âm tiết/phút) — dùng ước lượng "x phút đọc". */
    private const WORDS_PER_MINUTE = 200;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'cover_path',
        'status',
        'published_at',
        'seo_title',
        'seo_description',
        'author_name',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    /**
     * Nội dung LUÔN được sanitize khi ghi (chống stored XSS — CWE-79), bất kể đi từ
     * admin, seeder hay tinker. Đặt ở model thay vì controller để không đường ghi nào
     * lọt qua được; trang khách render bằng dangerouslySetInnerHTML nên đây là chốt chặn.
     */
    protected function content(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => EditorHtml::clean($value));
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function combos(): BelongsToMany
    {
        return $this->belongsToMany(Combo::class);
    }

    /** Bài đang hiện công khai: đã đăng và tới giờ đăng. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /** Mới nhất trước — thứ tự hiển thị phía khách. */
    public function scopeLatestPublished(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function isPublic(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    /** Số phút đọc ước lượng (tối thiểu 1). */
    public function readingMinutes(): int
    {
        $text = trim(strip_tags(preg_replace('/<[^>]*>/', ' $0', (string) $this->content)));
        $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text));

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }
}
