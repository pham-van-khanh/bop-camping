<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Sinh slug duy nhất cho một model (single source of truth — RULES DRY).
 * Dùng chung cho Product, Category... thay vì lặp vòng while ở mỗi controller.
 */
class Slug
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function unique(string $modelClass, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $i = 1;

        while (
            $modelClass::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
