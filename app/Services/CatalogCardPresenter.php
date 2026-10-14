<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Support\Facades\Storage;

/**
 * Dữ liệu cho thẻ sản phẩm / thẻ combo (ProductCard, ComboCard) — single source.
 *
 * Tách ra từ Shop\ProductController (bopcamping-y05c) vì trang khu vực
 * /thue-do-cam-trai/{slug} và trang điểm cắm trại cũng vẽ đúng hai loại thẻ đó. Chép lại
 * mảng shape ở controller mới là mở đường cho hai bản lệch nhau (vd một bên quên srcset).
 *
 * Container resolve mỗi lần một instance mới (không đăng ký singleton) nên memo số kho
 * đang mở chỉ sống trong controller đang xử lý request.
 */
class CatalogCardPresenter
{
    /** Số vị trí đang mở (memoize) — biết sản phẩm có phục vụ "toàn hệ thống" không. */
    private ?int $openLocationCount = null;

    public function openLocationCount(): int
    {
        return $this->openLocationCount ??= ServiceLocation::open()->count();
    }

    /**
     * Nạp sẵn biến thể ảnh cho cả danh sách TRƯỚC khi product() (bopcamping-ix4n).
     * Không gọi thì mỗi ảnh tự đi một query lẻ — đúng cái N+1 cần tránh.
     *
     * @param  iterable<Product>  $products
     */
    public function warmProducts(iterable $products): void
    {
        $paths = [];
        foreach ($products as $p) {
            $paths[] = $p->thumbnail;
            foreach ($p->images as $img) {
                $paths[] = $img->path;
            }
        }
        MediaVariantService::warm($paths);
    }

    /** Biến đổi Product Eloquent -> array cho ProductCard / trang chi tiết. */
    public function product(Product $p): array
    {
        $thumb = $p->thumbnail ? MediaVariantService::payload($p->thumbnail) : null;

        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'description' => $p->description,
            'price_per_day' => $p->price_per_day,
            'quantity' => $p->quantity,
            'deposit' => $p->deposit ?? 0,
            'early_return_discount_pct' => (int) $p->early_return_discount_pct,
            // `thumbnail` giữ nguyên là string (nhiều component đang dùng trực tiếp);
            // srcset đi kèm ở khoá riêng để browser tự chọn cỡ (bopcamping-ix4n).
            'thumbnail' => $thumb['url'] ?? null,
            'thumbnail_srcset' => $thumb['srcset'] ?? null,
            'status' => $p->status,
            'category' => [
                'id' => $p->category->id,
                'name' => $p->category->name,
                'slug' => $p->category->slug,
            ],
            'images' => $p->images->map(function ($i) {
                // Video không có biến thể — serve nguyên file.
                if ($i->type !== 'image') {
                    return [
                        'url' => Storage::disk('media')->url($i->path),
                        'srcset' => null,
                        'sort_order' => $i->sort_order,
                        'type' => $i->type,
                    ];
                }
                $media = MediaVariantService::payload($i->path);

                return [
                    'url' => $media['url'],
                    'srcset' => $media['srcset'],
                    'sort_order' => $i->sort_order,
                    'type' => $i->type,
                ];
            })->values()->all(),
            'featured' => false,
            // Badge vị trí: chỉ tính vị trí đang mở. all_locations = phục vụ toàn bộ
            // vị trí đang mở -> hiện gộp "Toàn hệ thống"; ngược lại liệt kê từng nơi.
            'locations' => $this->productLocations($p),
            'all_locations' => $this->servesAllOpenLocations($p),
        ];
    }

    /** Danh sách vị trí đang mở mà sản phẩm phục vụ (cho badge thẻ sản phẩm). */
    public function productLocations(Product $p): array
    {
        return $p->serviceLocations
            ->where('status', 'open')
            ->map(fn (ServiceLocation $l) => ['name' => $l->name, 'slug' => $l->slug])
            ->values()
            ->all();
    }

    /** True nếu sản phẩm phủ hết các vị trí đang mở (>=1) -> badge "Toàn hệ thống". */
    private function servesAllOpenLocations(Product $p): bool
    {
        $total = $this->openLocationCount();

        return $total > 0 && $p->serviceLocations->where('status', 'open')->count() === $total;
    }

    /**
     * Nạp sẵn biến thể ảnh bìa combo — ảnh thẻ combo qua MediaVariantService như ProductCard
     * (bopcamping-hjde), không serve thẳng file admin upload nguyên bản (tới 50MB).
     *
     * @param  iterable<Combo>  $combos
     */
    public function warmCombos(iterable $combos): void
    {
        $paths = [];
        foreach ($combos as $c) {
            $paths[] = $c->images->first()?->path;
        }
        MediaVariantService::warm($paths);
    }

    /**
     * Combo -> array cho ComboCard. Cần eager load items.product, images, serviceLocations
     * (thiếu serviceLocations thì badge cơ sở trống + N+1 — bopcamping-daet).
     */
    public function combo(Combo $c): array
    {
        $locations = $c->openLocations();
        $cover = $c->images->first()
            ? MediaVariantService::payload($c->images->first()->path)
            : null;

        return [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'combo_price' => (int) $c->combo_price,
            'sum_individual' => $c->sumIndividualPrice(),
            'savings_amount' => $c->savingsAmount(),
            'savings_percent' => $c->savingsPercent(),
            'suitable_for' => $c->suitable_for,
            'items_count' => $c->items->count(),
            'image' => $cover['url'] ?? null,
            'image_srcset' => $cover['srcset'] ?? null,
            'locations' => $locations,
            // openLocationCount() đã memoize nên không thành N+1 (bopcamping-65k9).
            'all_locations' => $this->openLocationCount() > 0
                && count($locations) === $this->openLocationCount(),
        ];
    }
}
