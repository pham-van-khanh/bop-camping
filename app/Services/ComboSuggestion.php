<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\ComboItem;
use Illuminate\Support\Collection;

/**
 * Kết quả cart combo detection (PRD combo 5.4) — tối đa 1 gợi ý cho giỏ.
 */
class ComboSuggestion
{
    /**
     * @param  string  $type  exact | superset | upsell
     * @param  int  $savings  tiết kiệm ₫/ngày = tổng giá lẻ − giá combo
     * @param  Collection<int, ComboItem>  $missingItems  món thiếu (chỉ upsell),
     *                                                    mỗi item kèm attribute `missing` = số còn thiếu
     */
    public function __construct(
        public readonly string $type,
        public readonly Combo $combo,
        public readonly int $savings,
        public readonly Collection $missingItems,
    ) {}
}
