<?php

namespace App\Services;

use App\Models\MediaVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Sinh biến thể ảnh đã resize + dựng srcset (bopcamping-ix4n).
 *
 * SINGLE SOURCE cho mọi thứ liên quan tới kích thước ảnh: bậc thang chiều rộng,
 * việc encode WebP, payload gửi ra Inertia. Đừng resize ảnh ở chỗ khác.
 *
 * Vì sao cần: trước đây upload lưu nguyên file, nên (1) ô thumbnail 76x64 vẫn tải
 * ảnh full, (2) không dám upload ảnh nét vì trang sẽ nặng. Có biến thể rồi thì
 * upload ảnh 3000px là chuyện bình thường — trang chỉ tải đúng cỡ nó cần.
 *
 * File GỐC không bị sửa/xoá: nó là bản dự phòng nếu sau này cần bậc lớn hơn.
 * Nhưng gốc KHÔNG bao giờ được serve — `src` luôn trỏ vào biến thể lớn nhất.
 */
class MediaVariantService
{
    /**
     * Bậc thang chiều rộng, chọn theo khung hiển thị thực tế (đo trên trang):
     *   400  → ô thumbnail 76x64 và ảnh phụ kiện 40x40 (@2x = 152px)
     *   800  → card sản phẩm 293x240 (@2x = 586px)
     *   1600 → ảnh chính chi tiết SP 668x680 (@2x = 1336px)
     * Không sinh bậc lớn hơn ảnh gốc — phóng to chỉ làm file nặng chứ không nét thêm.
     */
    public const WIDTHS = [400, 800, 1600];

    /** Chất lượng WebP — 82 là mức gần như không thấy khác biệt bằng mắt. */
    private const QUALITY = 82;

    public function __construct(private readonly ImageManager $manager) {}

    public static function make(): self
    {
        return new self(new ImageManager(new Driver));
    }

    /**
     * Sinh biến thể cho một file ảnh. Idempotent: gọi lại không làm gì thêm.
     * Trả về số biến thể vừa tạo.
     *
     * KHÔNG ném exception: ảnh lỗi/không đọc được thì chỉ ghi log và bỏ qua —
     * fallback là serve file gốc như trước, không được để việc này làm hỏng
     * luồng upload của admin.
     */
    public function generate(string $sourcePath): int
    {
        if (MediaVariant::where('source_path', $sourcePath)->exists()) {
            return 0;
        }

        $disk = Storage::disk('media');

        try {
            $raw = $disk->get($sourcePath);
            if ($raw === null) {
                return 0;
            }
            $image = $this->manager->decodeBinary($raw);
        } catch (Throwable $e) {
            Log::warning('MediaVariant: không đọc được ảnh', [
                'path' => $sourcePath,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }

        $sourceWidth = $image->width();
        $created = 0;
        // Memo có thể đã ghi "path này không có biến thể" trước đó → phải bỏ đi.
        unset(self::$memo[$sourcePath]);

        foreach ($this->widthsFor($sourceWidth) as $width) {
            try {
                // scaleDown = chỉ thu nhỏ, giữ tỉ lệ, KHÔNG bao giờ phóng to.
                $resized = $this->manager->decodeBinary($raw)->scaleDown(width: $width);
                $variantPath = $this->variantPath($sourcePath, $width);

                $disk->put($variantPath, (string) $resized->encode(new WebpEncoder(quality: self::QUALITY)));

                MediaVariant::create([
                    'source_path' => $sourcePath,
                    'width' => $resized->width(),
                    'height' => $resized->height(),
                    'path' => $variantPath,
                ]);
                $created++;
            } catch (Throwable $e) {
                Log::warning('MediaVariant: resize thất bại', [
                    'path' => $sourcePath,
                    'width' => $width,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $created;
    }

    /**
     * Các bậc cần sinh cho ảnh rộng $sourceWidth: mọi bậc nhỏ hơn gốc, cộng thêm
     * một bậc bằng đúng chiều rộng gốc nếu gốc nhỏ hơn bậc lớn nhất — để ảnh nhỏ
     * (578px như ảnh hiện có) vẫn có ít nhất một biến thể WebP để serve.
     *
     * @return list<int>
     */
    private function widthsFor(int $sourceWidth): array
    {
        $widths = array_values(array_filter(self::WIDTHS, fn (int $w) => $w < $sourceWidth));
        $widths[] = min($sourceWidth, max(self::WIDTHS));

        return array_values(array_unique($widths));
    }

    /** `admin/products/abc.png` + 800 → `admin/products/variants/abc-800.webp` */
    private function variantPath(string $sourcePath, int $width): string
    {
        $dir = trim(dirname($sourcePath), '.'.DIRECTORY_SEPARATOR);
        $name = pathinfo($sourcePath, PATHINFO_FILENAME);

        return ($dir === '' ? '' : $dir.'/').'variants/'.$name.'-'.$width.'.webp';
    }

    /** Xoá mọi biến thể (file + row) của một file gốc. */
    public function forget(string $sourcePath): void
    {
        $variants = MediaVariant::where('source_path', $sourcePath)->get();
        if ($variants->isEmpty()) {
            return;
        }

        Storage::disk('media')->delete(
            $variants->pluck('path')->reject(fn (string $p) => $p === $sourcePath)->all()
        );
        MediaVariant::where('source_path', $sourcePath)->delete();
        unset(self::$memo[$sourcePath]);
    }

    /**
     * Memo trong PHẠM VI MỘT REQUEST: source_path => biến thể của nó.
     * Nạp bằng warm() để tránh N+1 khi render danh sách.
     *
     * @var array<string, Collection<int, MediaVariant>>
     */
    private static array $memo = [];

    /**
     * Nạp sẵn biến thể của nhiều file trong MỘT query. Gọi trước khi shape/render
     * danh sách ảnh. Bỏ qua path đã nạp rồi.
     *
     * @param  iterable<string|null>  $sourcePaths
     */
    public static function warm(iterable $sourcePaths): void
    {
        $paths = collect($sourcePaths)
            ->filter()
            ->unique()
            ->reject(fn (string $p) => isset(self::$memo[$p]))
            ->values();

        if ($paths->isEmpty()) {
            return;
        }

        $grouped = MediaVariant::whereIn('source_path', $paths)->get()->groupBy('source_path');

        // Path không có biến thể cũng phải ghi vào memo (collection rỗng), nếu không
        // payload() sẽ đi query lẻ cho từng cái → đúng cái N+1 đang tránh.
        foreach ($paths as $path) {
            self::$memo[$path] = $grouped->get($path, collect());
        }
    }

    /** Dọn memo — dùng trong test, hoặc job xử lý nhiều ảnh trong một tiến trình. */
    public static function flushMemo(): void
    {
        self::$memo = [];
    }

    /**
     * Payload ảnh cho Inertia: `url` = biến thể LỚN NHẤT (không bao giờ là file
     * gốc, để browser cũ không tải file 5MB), `srcset` + `width` để browser tự
     * chọn cỡ. Chưa có biến thể (ảnh cũ chưa backfill) → fallback về file gốc,
     * srcset = null, giao diện vẫn chạy y như trước.
     *
     * @return array{url: string, srcset: string|null, width: int|null}
     */
    public static function payload(string $sourcePath): array
    {
        $disk = Storage::disk('media');

        if (! isset(self::$memo[$sourcePath])) {
            self::warm([$sourcePath]);
        }
        $variants = self::$memo[$sourcePath]->sortBy('width')->values();

        if ($variants->isEmpty()) {
            return ['url' => $disk->url($sourcePath), 'srcset' => null, 'width' => null];
        }

        $largest = $variants->last();

        return [
            'url' => $disk->url($largest->path),
            'srcset' => $variants
                ->map(fn (MediaVariant $v) => $disk->url($v->path).' '.$v->width.'w')
                ->implode(', '),
            'width' => $largest->width,
        ];
    }
}
