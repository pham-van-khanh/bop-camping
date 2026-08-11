<?php

namespace App\Jobs;

use App\Services\MediaVariantService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sinh biến thể ảnh sau khi upload (bopcamping-ix4n).
 *
 * Chạy qua queue vì resize là việc nặng: 12 ảnh 4000px mỗi lần upload sẽ làm
 * admin chờ ~15s và dễ đụng max_execution_time. CẦN queue worker đang chạy —
 * `composer run dev` đã bật sẵn.
 *
 * Không có worker thì biến thể không được sinh, payload tự fallback về file gốc
 * (xem MediaVariantService::payload) — giao diện vẫn chạy đúng, chỉ là chưa nhẹ đi.
 */
class GenerateMediaVariants implements ShouldQueue
{
    use Queueable;

    /** @param  list<string>  $sourcePaths */
    public function __construct(public array $sourcePaths) {}

    public function handle(): void
    {
        $service = MediaVariantService::make();

        foreach ($this->sourcePaths as $path) {
            $service->generate($path);
        }
    }
}
