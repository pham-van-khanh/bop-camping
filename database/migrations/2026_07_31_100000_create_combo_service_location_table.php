<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-e5pi — pivot Combo <-> ServiceLocation.
 *
 * Trước đây combo KHÔNG có kho riêng, nó suy ra bằng GIAO các kho đang mở của mọi món con
 * (Combo::commonOpenLocations()). Từ nay admin gán tường minh.
 *
 * Backfill: gán đúng tập mà commonOpenLocations() đang tính ra, để không combo nào đổi hành vi
 * sau khi deploy. Tập rỗng -> gán TẤT CẢ kho đang mở: combo 0 kho sẽ lọt qua cả 2 chốt vị trí
 * của giỏ rồi bị checkout từ chối, tức khách kẹt ở bước cuối (xem PRD mục 6, R1).
 *
 * Cố ý dùng query thô thay vì Model: migration không được phụ thuộc code app có thể đổi/xoá
 * sau (commonOpenLocations() bị xoá ngay trong task này).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combo_service_location', function (Blueprint $table) {
            $table->foreignId('combo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_location_id')->constrained()->cascadeOnDelete();
            $table->primary(['combo_id', 'service_location_id']);
        });

        $openIds = DB::table('service_locations')->where('status', 'open')->pluck('id');
        if ($openIds->isEmpty()) {
            return;
        }

        // Số món PHÂN BIỆT của mỗi combo — kho phải phục vụ đủ số này mới thuộc tập giao.
        $itemCount = DB::table('combo_items')
            ->select('combo_id', DB::raw('COUNT(DISTINCT product_id) as n'))
            ->groupBy('combo_id')
            ->pluck('n', 'combo_id');

        // [combo_id][location_id] = số món phân biệt của combo được phục vụ tại kho đó.
        $served = DB::table('combo_items')
            ->join('product_service_location as psl', 'psl.product_id', '=', 'combo_items.product_id')
            ->whereIn('psl.service_location_id', $openIds)
            ->select('combo_items.combo_id', 'psl.service_location_id', DB::raw('COUNT(DISTINCT combo_items.product_id) as n'))
            ->groupBy('combo_items.combo_id', 'psl.service_location_id')
            ->get();

        $common = [];
        foreach ($served as $row) {
            if ((int) $row->n === (int) ($itemCount[$row->combo_id] ?? -1)) {
                $common[$row->combo_id][] = (int) $row->service_location_id;
            }
        }

        $rows = [];
        foreach (DB::table('combos')->pluck('id') as $comboId) {
            // Combo rỗng món cũng vào nhánh fallback — không để nó ở trạng thái 0 kho.
            $locationIds = $common[$comboId] ?? $openIds->all();

            foreach ($locationIds as $locationId) {
                $rows[] = ['combo_id' => $comboId, 'service_location_id' => $locationId];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('combo_service_location')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_service_location');
    }
};
