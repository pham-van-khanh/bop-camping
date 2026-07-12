<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Chọn cửa hàng cho 1 đơn (per-store stock): khách chọn thì validate đúng store đó;
 * không chọn thì tự gán store còn đủ CẢ GIỎ trong khoảng ngày. Không cộng xuyên cửa hàng.
 */
class StoreResolver
{
    public function __construct(private AvailabilityService $availability) {}

    /**
     * @param  array<string, int>  $needed  ["productId|start|end" => qty] — nhu cầu gộp lẻ + combo
     * @param  Collection<int, Product>  $productsById  sản phẩm (đã load serviceLocations)
     * @param  int|null  $chosenLocationId  store khách chọn (null = tự gán)
     * @return array{location: ServiceLocation|null, auto: bool} location=null → đơn không gắn store (legacy: sản phẩm chưa cấu hình vị trí)
     *
     * @throws RuntimeException khi store chọn không đủ / không store nào đủ cả giỏ
     */
    public function resolveForCart(array $needed, Collection $productsById, ?int $chosenLocationId): array
    {
        // Chỉ xét sản phẩm CÓ cấu hình vị trí phục vụ (open). Sản phẩm chưa cấu hình → không ràng buộc.
        $locationSets = $productsById
            ->map(fn (Product $p) => $p->serviceLocations->where('status', 'open')->pluck('id')->all())
            ->filter(fn (array $ids) => ! empty($ids))
            ->values()
            ->all();

        // Không sản phẩm nào có vị trí (dữ liệu/test cũ) → đơn không gắn store, kiểm tồn toàn cục.
        if ($locationSets === []) {
            if ($this->shortfall($needed, $productsById, null) !== null) {
                throw new RuntimeException($this->shortfall($needed, $productsById, null));
            }

            return ['location' => null, 'auto' => false];
        }

        $candidateIds = array_values(array_intersect(...$locationSets));

        if (empty($candidateIds)) {
            throw new RuntimeException('Các thiết bị trong giỏ không cùng một cửa hàng phục vụ. Mỗi đơn chỉ thuê tại một cơ sở.');
        }

        $candidates = ServiceLocation::whereIn('id', $candidateIds)->orderBy('sort_order')->orderBy('id')->get();

        // Khách đã chọn store → chỉ xét store đó.
        if ($chosenLocationId !== null) {
            $chosen = $candidates->firstWhere('id', $chosenLocationId);
            if (! $chosen) {
                throw new RuntimeException('Cơ sở bạn chọn không phục vụ đủ các món trong giỏ.');
            }
            if ($this->shortfall($needed, $productsById, $chosen) !== null) {
                throw new RuntimeException($this->shortfall($needed, $productsById, $chosen));
            }

            return ['location' => $chosen, 'auto' => false];
        }

        // Tự gán: store nào đủ CẢ GIỎ; nhiều store đủ → chọn store tổng khả dụng lớn nhất (tie: sort_order).
        $ok = $candidates
            ->filter(fn (ServiceLocation $loc) => $this->shortfall($needed, $productsById, $loc) === null)
            ->sortByDesc(fn (ServiceLocation $loc) => $this->totalHeadroom($needed, $productsById, $loc))
            ->values();

        if ($ok->isEmpty()) {
            throw new RuntimeException('Khoảng ngày này chưa cơ sở nào còn đủ cả giỏ — bạn đổi ngày hoặc liên hệ shop giúp nhé.');
        }

        return ['location' => $ok->first(), 'auto' => true];
    }

    /** Trả message thiếu hàng đầu tiên tại store (null = toàn cục), hoặc null nếu đủ hết. */
    private function shortfall(array $needed, Collection $productsById, ?ServiceLocation $location): ?string
    {
        foreach ($needed as $key => $qty) {
            [$productId, $start, $end] = explode('|', $key);
            $product = $productsById->get((int) $productId);
            if (! $product) {
                return "Sản phẩm #{$productId} không tồn tại.";
            }
            $available = $this->availability->availableQuantity($product, Carbon::parse($start), Carbon::parse($end), $location);
            if ($available < $qty) {
                $at = $location ? " tại {$location->name}" : '';

                return "\"{$product->name}\"{$at} chỉ còn {$available} bộ trong khoảng này.";
            }
        }

        return null;
    }

    /** Tổng khả dụng của các món trong giỏ tại store — để chọn store rộng rãi hơn khi tự gán. */
    private function totalHeadroom(array $needed, Collection $productsById, ServiceLocation $location): int
    {
        $sum = 0;
        foreach ($needed as $key => $qty) {
            [$productId, $start, $end] = explode('|', $key);
            $product = $productsById->get((int) $productId);
            if ($product) {
                $sum += $this->availability->availableQuantity($product, Carbon::parse($start), Carbon::parse($end), $location);
            }
        }

        return $sum;
    }
}
