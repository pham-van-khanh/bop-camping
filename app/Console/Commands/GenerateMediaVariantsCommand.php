<?php

namespace App\Console\Commands;

use App\Models\ComboImage;
use App\Models\MediaVariant;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\MediaVariantService;
use Illuminate\Console\Command;

/**
 * Backfill biến thể cho ảnh đã upload trước khi có pipeline (bopcamping-ix4n).
 * Idempotent — chạy lại bao nhiêu lần cũng được, ảnh nào có rồi thì bỏ qua.
 */
class GenerateMediaVariantsCommand extends Command
{
    protected $signature = 'media:variants {--dry-run : Chỉ đếm, không resize}';

    protected $description = 'Sinh biến thể WebP đã resize cho ảnh sản phẩm/combo chưa có';

    public function handle(): int
    {
        $paths = collect()
            ->concat(ProductImage::where('type', 'image')->pluck('path'))
            ->concat(ComboImage::where('type', 'image')->pluck('path'))
            ->concat(Product::whereNotNull('thumbnail')->pluck('thumbnail'))
            ->filter()
            ->unique()
            ->values();

        $this->info("Tìm thấy {$paths->count()} file ảnh.");

        if ($this->option('dry-run')) {
            $done = MediaVariant::whereIn('source_path', $paths)->distinct()->count('source_path');
            $this->info("Đã có biến thể: {$done} — cần xử lý: ".($paths->count() - $done));

            return self::SUCCESS;
        }

        $service = MediaVariantService::make();
        $created = 0;
        $bar = $this->output->createProgressBar($paths->count());
        $bar->start();

        foreach ($paths as $path) {
            $created += $service->generate($path);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Xong. Đã sinh {$created} biến thể.");

        return self::SUCCESS;
    }
}
