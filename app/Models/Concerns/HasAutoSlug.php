<?php

namespace App\Models\Concerns;

use App\Support\Slug;
use Illuminate\Support\Str;

/**
 * Slug là CỘT, tự sinh từ `name` khi trống (bopcamping-y05c).
 *
 * Chỉ sinh khi slug đang trống — đổi tên về sau KHÔNG đổi slug, vì slug nằm trong URL đã
 * được index. Muốn đổi URL thì admin sửa ô slug một cách có chủ đích.
 * Slug admin gõ tay cũng được chuẩn hoá (bỏ dấu, thường hoá) để không lọt URL lạ.
 */
trait HasAutoSlug
{
    protected static function bootHasAutoSlug(): void
    {
        static::saving(function ($model) {
            $model->slug = filled($model->slug)
                ? Str::slug((string) $model->slug)
                : Slug::unique(static::class, (string) $model->name, $model->getKey());
        });
    }
}
