<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ServiceLocation;
use App\Support\MediaType;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'description' => $p->description,
                'specs' => $p->specs ?? [],
                'has_setup_content' => $p->setup_content !== null,
                'price_per_day' => $p->price_per_day,
                'quantity' => $p->quantity,
                'deposit' => $p->deposit,
                'thumbnail' => $p->thumbnail ? Storage::disk('media')->url($p->thumbnail) : null,
                'status' => $p->status,
                'category' => $p->category ? ['id' => $p->category->id, 'name' => $p->category->name] : null,
                'service_location_ids' => $p->serviceLocations->pluck('id')->values(),
                // Tồn kho theo store (per-store): {service_location_id: quantity}
                'stocks' => $p->serviceLocations->mapWithKeys(fn ($l) => [$l->id => (int) $l->pivot->quantity]),
                'accessory_ids' => $p->accessories->pluck('id')->values(),
                'related_ids' => $p->related->pluck('id')->values(),
                'combo_names' => $comboNamesByProduct->get($p->id) ?? [],
                'images' => $p->images->map(fn (ProductImage $img) => [
                    'id' => $img->id,
                    'path' => Storage::disk('media')->url($img->path),
                    'sort_order' => $img->sort_order,
                    'type' => $img->type,
                ])->values(),
            ]);

        return Inertia::render('Admin/Products', [
            'products' => $products,
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
            'filters' => ['search' => $search, 'category' => $categoryId ?: null],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:150',
            'category_id' => 'required|integer|exists:categories,id',
            'description' => 'nullable|string',
            'price_per_day' => 'required|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:active,hidden',
            'thumbnail' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
            'service_location_ids' => 'required|array|min:1',
            'service_location_ids.*' => 'integer|exists:service_locations,id',
            // Tồn kho theo cửa hàng (per-store): map service_location_id => số lượng
            'stocks' => 'sometimes|array',
            'stocks.*' => 'integer|min:0',
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

        $slug = Slug::unique(Product::class, $data['name']);

        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('admin/products', 'media');
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
            'status' => $data['status'] ?? 'active',
            'thumbnail' => $thumbnailPath,
        ]);

        $this->syncStocks($product, $data);
        $this->syncSortedRelation($product, $data, 'accessory_ids', 'accessories');
        $this->syncSortedRelation($product, $data, 'related_ids', 'related');

        return back()->with('success', 'Đã thêm sản phẩm.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:150',
            'category_id' => 'required|integer|exists:categories,id',
            'description' => 'nullable|string',
            'price_per_day' => 'required|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:active,hidden',
            'thumbnail' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
            'service_location_ids' => 'required|array|min:1',
            'service_location_ids.*' => 'integer|exists:service_locations,id',
            // Tồn kho theo cửa hàng (per-store): map service_location_id => số lượng
            'stocks' => 'sometimes|array',
            'stocks.*' => 'integer|min:0',
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
                Storage::disk('media')->delete($thumbnailPath);
            }
            $thumbnailPath = $request->file('thumbnail')->store('admin/products', 'media');
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
        $pivot = [];
        foreach ($data['service_location_ids'] as $locId) {
            $pivot[(int) $locId] = ['quantity' => max(0, (int) ($stocks[$locId] ?? 0))];
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

        // Xóa tất cả ảnh phụ trên storage
        foreach ($product->images as $image) {
            Storage::disk('media')->delete($image->path);
        }
        $product->images()->delete();

        // Xóa thumbnail
        if ($product->thumbnail) {
            Storage::disk('media')->delete($product->thumbnail);
        }

        $product->delete();

        return back()->with('success', 'Đã xoá sản phẩm.');
    }

    public function storeImage(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'max:12'],
            'images.*' => ['file', MediaType::MIMES_RULE, 'max:51200'], // ≤50MB
        ], [
            'images.max' => 'Tối đa 12 ảnh/video mỗi lần.',
            'images.*.mimetypes' => 'Chỉ nhận ảnh (jpg, png, webp) hoặc video (mp4, webm, mov).',
            'images.*.max' => 'Mỗi tệp tối đa 50MB.',
        ]);

        $maxSort = $product->images()->max('sort_order') ?? 0;

        foreach ($request->file('images') as $file) {
            $product->images()->create([
                'path' => $file->store('admin/products', 'media'),
                'sort_order' => ++$maxSort,
                'type' => MediaType::detect($file),
            ]);
        }

        return back()->with('success', 'Đã tải lên ảnh.');
    }

    public function destroyImage(Product $product, ProductImage $image): RedirectResponse
    {
        // Chặn IDOR: ảnh phải thuộc đúng sản phẩm trên URL (CWE-639).
        abort_unless($image->product_id === $product->id, 404);

        Storage::disk('media')->delete($image->path);
        $image->delete();

        return back()->with('success', 'Đã xoá ảnh.');
    }
}
