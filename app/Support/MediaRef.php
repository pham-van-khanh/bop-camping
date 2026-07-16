<?php

namespace App\Support;

use App\Models\Combo;
use App\Models\ComboImage;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Ảnh gallery product/combo được CHIA SẺ: nhiều row product_images/combo_images
 * có thể trỏ tới cùng một file (`path`). Đây là single source cho:
 *  - đếm tham chiếu + xoá file an toàn (chỉ xoá khi không còn nơi nào dùng),
 *  - phân giải "ảnh nguồn" khi tái sử dụng,
 *  - kho ảnh cho picker (nhóm theo product & combo).
 */
final class MediaRef
{
    /** Số row (cả product_images + combo_images) đang trỏ tới path này. */
    public static function refCount(string $path): int
    {
        return ProductImage::where('path', $path)->count()
            + ComboImage::where('path', $path)->count();
    }

    /**
     * Xoá file vật lý CHỈ KHI không còn row nào tham chiếu.
     * Phải gọi SAU khi đã xoá row DB (để refCount phản ánh trạng thái sau xoá).
     */
    public static function deleteFileIfOrphan(?string $path): void
    {
        if ($path && self::refCount($path) === 0) {
            Storage::disk('media')->delete($path);
        }
    }

    /**
     * Phân giải danh sách nguồn [{type,id}] -> collection ['path','type'].
     * id = product_image.id (type=product) hoặc combo_image.id (type=combo).
     * Không tin path từ client — luôn tra lại từ DB. Unique theo path, giữ thứ tự chọn.
     *
     * @param  array<int, array{type: string, id: int|string}>  $sources
     * @return Collection<int, array{path: string, type: string}>
     */
    public static function resolveSources(array $sources): Collection
    {
        $productIds = collect($sources)->where('type', 'product')->pluck('id')->map('intval');
        $comboIds = collect($sources)->where('type', 'combo')->pluck('id')->map('intval');

        $byId = collect();
        if ($productIds->isNotEmpty()) {
            ProductImage::whereIn('id', $productIds)->get(['path', 'type'])
                ->each(fn (ProductImage $r) => $byId->push(['path' => $r->path, 'type' => $r->type]));
        }
        if ($comboIds->isNotEmpty()) {
            ComboImage::whereIn('id', $comboIds)->get(['path', 'type'])
                ->each(fn (ComboImage $r) => $byId->push(['path' => $r->path, 'type' => $r->type]));
        }

        return $byId->unique('path')->values();
    }

    /**
     * Kho ảnh cho picker: nhóm theo product & combo (chỉ item có ảnh).
     * `path` trả về là URL để hiển thị; `id` là id của row ảnh (product_image/combo_image).
     *
     * @return array<int, array{type: string, id: int, name: string, images: array<int, array{id: int, path: string, type: string}>}>
     */
    public static function library(): array
    {
        $mapImages = fn (Collection $images) => $images
            ->map(fn ($img) => [
                'id' => $img->id,
                'path' => Storage::disk('media')->url($img->path),
                'type' => $img->type,
            ])->values()->all();

        $products = Product::has('images')
            ->with('images')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Product $p) => [
                'type' => 'product',
                'id' => $p->id,
                'name' => $p->name,
                'images' => $mapImages($p->images),
            ]);

        $combos = Combo::has('images')
            ->with('images')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Combo $c) => [
                'type' => 'combo',
                'id' => $c->id,
                'name' => $c->name,
                'images' => $mapImages($c->images),
            ]);

        return $products->concat($combos)->values()->all();
    }
}
