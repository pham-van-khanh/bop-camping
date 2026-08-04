<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateMediaVariants;
use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ServiceLocation;
use App\Services\MediaVariantService;
use App\Support\MediaRef;
use App\Support\MediaType;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        // Tên các combo active chứa từng sản phẩm — FE cảnh báo khi ẩn/xoá (US-07)
        $comboNamesByProduct = Combo::query()
            ->where('is_active', true)
            ->join('combo_items', 'combo_items.combo_id', '=', 'combos.id')
            ->get(['combo_items.product_id', 'combos.name'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->pluck('name')->values());

        // Lọc: tìm theo tên + lọc theo danh mục (giữ query khi phân trang).
        $search = trim((string) $request->query('search', ''));
        $categoryId = (int) $request->query('category', 0);

        $products = Product::with(['category', 'serviceLocations', 'accessories', 'related', 'images' => fn ($q) => $q->orderBy('sort_order')])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->when($categoryId > 0, fn ($q) => $q->where('category_id', $categoryId))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Product $p) => $this->mapProductForForm($p, ($comboNamesByProduct->get($p->id)?->all()) ?? []));

        return Inertia::render('Admin/Products', [
            'products' => $products,
            // Chỉ cần danh mục cho bộ lọc — form thêm/sửa đã tách sang màn riêng.
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'filters' => ['search' => $search, 'category' => $categoryId ?: null],
        ]);
    }

    /** Màn THÊM sản phẩm (trang riêng, thay cho popup cũ). */
    public function create(): Response
    {
        return Inertia::render('Admin/ProductForm', [
            // Kho ảnh cho picker của gallery nháp (bopcamping-7czf) — nạp lazy khi mở picker.
            'mediaLibrary' => Inertia::optional(fn () => MediaRef::library()),
        ] + $this->formSharedProps());
    }

    /** Màn SỬA sản phẩm (trang riêng): form đầy đủ + quản lý ảnh phụ. */
    public function edit(Product $product): Response
    {
        $product->load(['category', 'serviceLocations', 'accessories', 'related', 'images' => fn ($q) => $q->orderBy('sort_order')]);

        // Tên combo active chứa sản phẩm này — FE cảnh báo khi ẩn (US-07).
        $comboNames = Combo::query()
            ->where('is_active', true)
            ->join('combo_items', 'combo_items.combo_id', '=', 'combos.id')
            ->where('combo_items.product_id', $product->id)
            ->pluck('combos.name')
            ->values()
            ->all();

        return Inertia::render('Admin/ProductForm', [
            'product' => $this->mapProductForForm($product, $comboNames),
            // Kho ảnh cho picker "chọn ảnh có sẵn" của gallery — nạp lazy khi mở picker.
            'mediaLibrary' => Inertia::optional(fn () => MediaRef::library()),
        ] + $this->formSharedProps());
    }

    /**
     * Props dùng chung cho form thêm/sửa: danh mục, sản phẩm gợi ý, vị trí phục vụ.
     *
     * @return array<string, mixed>
     */
    private function formSharedProps(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            // Toàn bộ sản phẩm cho picker "Thường thuê cùng" (US-08) — shop nhỏ, không cần search server
            'accessory_options' => Product::orderBy('name')->get(['id', 'name', 'status']),
            // Vị trí phục vụ để chọn khi thêm/sửa sản phẩm (vị trí 'coming' bị khoá ở UI).
            'service_locations' => ServiceLocation::ordered()->get()->map(fn (ServiceLocation $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'area' => $l->area,
                'status' => $l->status,
            ])->values(),
        ];
    }

    /**
     * Map 1 sản phẩm sang shape mà list + form thêm/sửa cùng dùng (single source).
     *
     * @param  array<int, string>  $comboNames
     * @return array<string, mixed>
     */
    private function mapProductForForm(Product $p, array $comboNames): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'description' => $p->description,
            'specs' => $p->specs ?? [],
            'has_setup_content' => $p->setup_content !== null,
            'price_per_day' => $p->price_per_day,
            'quantity' => $p->quantity,
            'deposit' => $p->deposit,
            'early_return_discount_pct' => (int) $p->early_return_discount_pct,
            'thumbnail' => $p->thumbnail ? Storage::disk('media')->url($p->thumbnail) : null,
            'status' => $p->status,
            'category' => $p->category ? ['id' => $p->category->id, 'name' => $p->category->name] : null,
            'service_location_ids' => $p->serviceLocations->pluck('id')->values(),
            // Tồn kho theo store (per-store): {service_location_id: quantity}
            'stocks' => $p->serviceLocations->mapWithKeys(fn ($l) => [$l->id => (int) $l->pivot->quantity]),
            // Đệm giặt/phơi theo kho (adr_turnaround_buffer): {service_location_id: buffer_days}
            'buffers' => $p->serviceLocations->mapWithKeys(fn ($l) => [$l->id => (int) $l->pivot->buffer_days]),
            'accessory_ids' => $p->accessories->pluck('id')->values(),
            'related_ids' => $p->related->pluck('id')->values(),
            'combo_names' => $comboNames,
            'images' => $p->images->map(fn (ProductImage $img) => [
                'id' => $img->id,
                'path' => Storage::disk('media')->url($img->path),
                'sort_order' => $img->sort_order,
                'type' => $img->type,
            ])->values(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:150',
            'category_id' => 'required|integer|exists:categories,id',
            'description' => 'nullable|string',
            'price_per_day' => 'required|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',
            // Ưu đãi trả sớm trong ngày (adr_pricing_models) — % giảm cho đơn cùng ngày.
            'early_return_discount_pct' => 'sometimes|integer|min:0|max:50',
            'status' => 'sometimes|in:active,hidden',
            'thumbnail' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
            'service_location_ids' => 'required|array|min:1',
            'service_location_ids.*' => 'integer|exists:service_locations,id',
            // Tồn kho theo cửa hàng (per-store): map service_location_id => số lượng
            'stocks' => 'sometimes|array',
            'stocks.*' => 'integer|min:0',
            // Đệm giặt/phơi theo kho (adr_turnaround_buffer) — số ngày, trần 30.
            'buffers' => 'sometimes|array',
            'buffers.*' => 'integer|min:0|max:30',
            'accessory_ids' => 'sometimes|nullable|array|max:20',
            'accessory_ids.*' => 'integer|distinct|exists:products,id',
            'specs' => 'sometimes|nullable|array|max:30',
            'specs.*.key' => 'required|string|max:100',
            'specs.*.value' => 'required|string|max:500',
            'related_ids' => 'sometimes|nullable|array|max:12',
            'related_ids.*' => 'integer|distinct|exists:products,id',
            // Ảnh phụ gửi kèm form thêm mới (bopcamping-7czf) — cùng trần với
            // storeImage() ở màn sửa để hai đường không lệch luật nhau.
            'gallery' => 'sometimes|nullable|array|max:12',
            'gallery.*' => ['file', MediaType::MIMES_RULE, 'max:51200'],
            'gallery_sources' => 'sometimes|nullable|array|max:24',
            'gallery_sources.*.type' => 'required|in:product,combo',
            'gallery_sources.*.id' => 'required|integer',
        ], [
            'gallery.max' => 'Tối đa 12 ảnh/video mỗi lần.',
            'gallery.*.mimetypes' => 'Chỉ nhận ảnh (jpg, png, gif, webp) hoặc video (mp4, webm, mov).',
            'gallery.*.max' => 'Mỗi tệp tối đa 50MB.',
            'name.required' => 'Tên sản phẩm không được bỏ trống.',
            'category_id.required' => 'Vui lòng chọn danh mục.',
            'category_id.exists' => 'Danh mục không hợp lệ.',
            'price_per_day.required' => 'Giá thuê không được bỏ trống.',
            'price_per_day.numeric' => 'Giá thuê phải là số.',
            'deposit.numeric' => 'Tiền cọc phải là số.',
            'service_location_ids.required' => 'Vui lòng chọn ít nhất 1 vị trí phục vụ.',
            'service_location_ids.min' => 'Vui lòng chọn ít nhất 1 vị trí phục vụ.',
            'accessory_ids.*.exists' => 'Sản phẩm gợi ý không hợp lệ.',
            'specs.*.key.required' => 'Tên thông số không được bỏ trống.',
            'specs.*.value.required' => 'Giá trị thông số không được bỏ trống.',
            'related_ids.*.exists' => 'Sản phẩm gợi ý không hợp lệ.',
        ]);

        $slug = Slug::unique(Product::class, $data['name']);

        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('admin/products', 'media');
            GenerateMediaVariants::dispatch([$thumbnailPath]);
        }

        $product = Product::create([
            'category_id' => (int) $data['category_id'],
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'specs' => $this->cleanSpecs($data),
            'price_per_day' => (int) $data['price_per_day'],
            'quantity' => 0, // syncStocks cập nhật = tổng tồn theo store
            'deposit' => isset($data['deposit']) ? (int) $data['deposit'] : null,
            'early_return_discount_pct' => (int) ($data['early_return_discount_pct'] ?? 0),
            'status' => $data['status'] ?? 'active',
            'thumbnail' => $thumbnailPath,
        ]);

        $this->syncStocks($product, $data);
        $this->syncSortedRelation($product, $data, 'accessory_ids', 'accessories');
        $this->syncSortedRelation($product, $data, 'related_ids', 'related');

        // Ảnh phụ admin đã chọn ngay trên form thêm mới (bopcamping-7czf).
        if ($request->hasFile('gallery')) {
            $this->saveUploadedImages($product, $request->file('gallery'));
        }
        if (! empty($data['gallery_sources'])) {
            $this->attachSharedImages($product, $data['gallery_sources']);
        }

        // Sang màn sửa để sắp xếp thứ tự ảnh / thêm tiếp (cần ảnh có id thật).
        return to_route('admin.products.edit', $product)
            ->with('success', 'Đã thêm sản phẩm.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:150',
            'category_id' => 'required|integer|exists:categories,id',
            'description' => 'nullable|string',
            'price_per_day' => 'required|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',
            // Ưu đãi trả sớm trong ngày (adr_pricing_models) — % giảm cho đơn cùng ngày.
            'early_return_discount_pct' => 'sometimes|integer|min:0|max:50',
            'status' => 'sometimes|in:active,hidden',
            'thumbnail' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
            'service_location_ids' => 'required|array|min:1',
            'service_location_ids.*' => 'integer|exists:service_locations,id',
            // Tồn kho theo cửa hàng (per-store): map service_location_id => số lượng
            'stocks' => 'sometimes|array',
            'stocks.*' => 'integer|min:0',
            // Đệm giặt/phơi theo kho (adr_turnaround_buffer) — số ngày, trần 30.
            'buffers' => 'sometimes|array',
            'buffers.*' => 'integer|min:0|max:30',
            'accessory_ids' => 'sometimes|nullable|array|max:20',
            'accessory_ids.*' => 'integer|distinct|exists:products,id',
            'specs' => 'sometimes|nullable|array|max:30',
            'specs.*.key' => 'required|string|max:100',
            'specs.*.value' => 'required|string|max:500',
            'related_ids' => 'sometimes|nullable|array|max:12',
            'related_ids.*' => 'integer|distinct|exists:products,id',
        ], [
            'name.required' => 'Tên sản phẩm không được bỏ trống.',
            'category_id.required' => 'Vui lòng chọn danh mục.',
            'category_id.exists' => 'Danh mục không hợp lệ.',
            'price_per_day.required' => 'Giá thuê không được bỏ trống.',
            'price_per_day.numeric' => 'Giá thuê phải là số.',
            'deposit.numeric' => 'Tiền cọc phải là số.',
            'service_location_ids.required' => 'Vui lòng chọn ít nhất 1 vị trí phục vụ.',
            'service_location_ids.min' => 'Vui lòng chọn ít nhất 1 vị trí phục vụ.',
            'accessory_ids.*.exists' => 'Sản phẩm gợi ý không hợp lệ.',
            'specs.*.key.required' => 'Tên thông số không được bỏ trống.',
            'specs.*.value.required' => 'Giá trị thông số không được bỏ trống.',
            'related_ids.*.exists' => 'Sản phẩm gợi ý không hợp lệ.',
        ]);

        $slug = Slug::unique(Product::class, $data['name'], $product->id);

        $thumbnailPath = $product->thumbnail;
        if ($request->hasFile('thumbnail')) {
            if ($thumbnailPath) {
                MediaVariantService::make()->forget($thumbnailPath);
                Storage::disk('media')->delete($thumbnailPath);
            }
            $thumbnailPath = $request->file('thumbnail')->store('admin/products', 'media');
            GenerateMediaVariants::dispatch([$thumbnailPath]);
        }

        $wasActive = $product->status === 'active';

        $product->update([
            'category_id' => (int) $data['category_id'],
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'specs' => array_key_exists('specs', $data) ? $this->cleanSpecs($data) : $product->specs,
            'price_per_day' => (int) $data['price_per_day'],
            'deposit' => isset($data['deposit']) ? (int) $data['deposit'] : null,
            'early_return_discount_pct' => (int) ($data['early_return_discount_pct'] ?? 0),
            'status' => $data['status'] ?? $product->status,
            'thumbnail' => $thumbnailPath,
        ]);

        $this->syncStocks($product, $data);
        $this->syncSortedRelation($product, $data, 'accessory_ids', 'accessories');
        $this->syncSortedRelation($product, $data, 'related_ids', 'related');

        // US-07: sản phẩm vừa bị ẩn → combo chứa nó không được bán tiếp
        if ($wasActive && $product->status === 'hidden') {
            Combo::hideForProduct($product, 'product_hidden');
        }

        return back()->with('success', 'Đã cập nhật sản phẩm.');
    }

    /**
     * Sync quan hệ sản phẩm-sản phẩm có thứ tự ("thường thuê cùng" US-08,
     * "you may also like" Epic 1): sort_order = vị trí trong mảng gửi lên.
     * Không gửi key = giữ nguyên; gửi rỗng ('' từ FormData) = xoá hết.
     * Tự gợi ý chính mình bị loại lặng lẽ.
     */
    private function syncSortedRelation(Product $product, array $data, string $key, string $relation): void
    {
        if (! array_key_exists($key, $data)) {
            return;
        }

        $product->{$relation}()->sync(
            collect($data[$key] ?? [])
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => $id === $product->id)
                ->values()
                ->mapWithKeys(fn (int $id, int $i) => [$id => ['sort_order' => $i]])
                ->all()
        );
    }

    /**
     * Sync tồn kho theo cửa hàng (per-store): pivot quantity cho từng store đã tick,
     * rồi cập nhật products.quantity = tổng (để chỗ hiển thị "tổng còn" không vỡ).
     */
    private function syncStocks(Product $product, array $data): void
    {
        $stocks = $data['stocks'] ?? [];
        $buffers = $data['buffers'] ?? [];
        $pivot = [];
        foreach ($data['service_location_ids'] as $locId) {
            $pivot[(int) $locId] = [
                'quantity' => max(0, (int) ($stocks[$locId] ?? 0)),
                // Đệm giặt/phơi theo kho (adr_turnaround_buffer) — trần 30 khớp validate.
                'buffer_days' => min(30, max(0, (int) ($buffers[$locId] ?? 0))),
            ];
        }
        $product->serviceLocations()->sync($pivot);
        $product->update(['quantity' => array_sum(array_column($pivot, 'quantity'))]);
    }

    /** Chuẩn hoá specs từ form: trim, bỏ dòng key rỗng; không còn dòng nào -> null. */
    private function cleanSpecs(array $data): ?array
    {
        $rows = collect($data['specs'] ?? [])
            ->map(fn (array $row) => [
                'key' => trim((string) $row['key']),
                'value' => trim((string) $row['value']),
            ])
            ->filter(fn (array $row) => $row['key'] !== '')
            ->values()
            ->all();

        return $rows === [] ? null : $rows;
    }

    public function destroy(Product $product): RedirectResponse
    {
        // US-07: ẩn combo TRƯỚC khi xoá — sau đó combo_items cascade theo FK
        Combo::hideForProduct($product, 'product_deleted');

        // Xoá ảnh phụ: xoá row trước, rồi chỉ xoá file khi không còn nơi khác dùng chung.
        $paths = $product->images->pluck('path')->all();
        $product->images()->delete();
        foreach ($paths as $path) {
            MediaRef::deleteFileIfOrphan($path);
        }

        // Xóa thumbnail
        if ($product->thumbnail) {
            MediaVariantService::make()->forget($product->thumbnail);
            Storage::disk('media')->delete($product->thumbnail);
        }

        $product->delete();

        return back()->with('success', 'Đã xoá sản phẩm.');
    }

    /**
     * Lưu file ảnh/video vừa upload thành ảnh phụ, nối vào cuối thứ tự hiện có.
     * Dùng chung bởi store() (thêm mới, ảnh gửi kèm form) và storeImage()
     * (thêm ảnh ở màn sửa) — một chỗ duy nhất quyết định path, type, sort_order.
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function saveUploadedImages(Product $product, array $files): void
    {
        $maxSort = $product->images()->max('sort_order') ?? 0;
        $newImagePaths = [];

        foreach ($files as $file) {
            $path = $file->store('admin/products', 'media');
            $type = MediaType::detect($file);
            $product->images()->create([
                'path' => $path,
                'sort_order' => ++$maxSort,
                'type' => $type,
            ]);
            // Video không resize được bằng GD — chỉ ảnh mới cần biến thể.
            if ($type === 'image') {
                $newImagePaths[] = $path;
            }
        }

        if ($newImagePaths !== []) {
            GenerateMediaVariants::dispatch($newImagePaths);
        }
    }

    /**
     * Gắn ảnh có sẵn (chia sẻ file, không copy) vào sản phẩm. Bỏ qua ảnh mà sản
     * phẩm này đã có để không tạo row trùng path.
     *
     * @param  array<int, array{type: string, id: int|string}>  $sources
     */
    private function attachSharedImages(Product $product, array $sources): void
    {
        $existing = $product->images()->pluck('path')->all();
        $maxSort = $product->images()->max('sort_order') ?? 0;

        MediaRef::resolveSources($sources)
            ->reject(fn (array $src) => in_array($src['path'], $existing, true))
            ->each(function (array $src) use ($product, &$maxSort) {
                $product->images()->create([
                    'path' => $src['path'],
                    'sort_order' => ++$maxSort,
                    'type' => $src['type'],
                ]);
            });
    }

    public function storeImage(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'max:12'],
            'images.*' => ['file', MediaType::MIMES_RULE, 'max:51200'], // ≤50MB
        ], [
            'images.max' => 'Tối đa 12 ảnh/video mỗi lần.',
            'images.*.mimetypes' => 'Chỉ nhận ảnh (jpg, png, gif, webp) hoặc video (mp4, webm, mov).',
            'images.*.max' => 'Mỗi tệp tối đa 50MB.',
        ]);

        $this->saveUploadedImages($product, $request->file('images'));

        return back()->with('success', 'Đã tải lên ảnh.');
    }

    /**
     * Tái sử dụng ảnh đã upload: tạo row mới trỏ cùng file (chia sẻ, không copy).
     * Bỏ qua ảnh mà sản phẩm này đã có (tránh trùng path).
     */
    public function attachImages(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'sources' => ['required', 'array', 'min:1', 'max:24'],
            'sources.*.type' => ['required', 'in:product,combo'],
            'sources.*.id' => ['required', 'integer'],
        ], [
            'sources.required' => 'Chưa chọn ảnh nào.',
        ]);

        $this->attachSharedImages($product, $data['sources']);

        return back()->with('success', 'Đã thêm ảnh.');
    }

    /** Sắp xếp lại thứ tự ảnh (kéo-thả): sort_order = vị trí trong mảng gửi lên. */
    public function reorderImages(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'image_ids' => ['required', 'array'],
            'image_ids.*' => ['integer'],
        ]);

        // Chỉ nhận id thuộc đúng sản phẩm (chống IDOR — CWE-639).
        $owned = $product->images()->pluck('id')->all();
        foreach ($data['image_ids'] as $i => $id) {
            if (in_array((int) $id, $owned, true)) {
                $product->images()->whereKey($id)->update(['sort_order' => $i]);
            }
        }

        return back()->with('success', 'Đã cập nhật thứ tự ảnh.');
    }

    public function destroyImage(Product $product, ProductImage $image): RedirectResponse
    {
        // Chặn IDOR: ảnh phải thuộc đúng sản phẩm trên URL (CWE-639).
        abort_unless($image->product_id === $product->id, 404);

        $path = $image->path;
        $image->delete();
        MediaRef::deleteFileIfOrphan($path);

        return back()->with('success', 'Đã xoá ảnh.');
    }
}
