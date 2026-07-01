<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ServiceLocation;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /** Ảnh + video cho phép trong gallery sản phẩm (như review media). */
    private const MEDIA_MIMES = 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime';

    public function index(): Response
    {
        $products = Product::with(['category', 'serviceLocations', 'images' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('name')
            ->paginate(50)
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'description' => $p->description,
                'price_per_day' => $p->price_per_day,
                'quantity' => $p->quantity,
                'deposit' => $p->deposit,
                'thumbnail' => $p->thumbnail ? Storage::url($p->thumbnail) : null,
                'status' => $p->status,
                'category' => $p->category ? ['id' => $p->category->id, 'name' => $p->category->name] : null,
                'service_location_ids' => $p->serviceLocations->pluck('id')->values(),
                'images' => $p->images->map(fn (ProductImage $img) => [
                    'id' => $img->id,
                    'path' => Storage::url($img->path),
                    'sort_order' => $img->sort_order,
                    'type' => $img->type,
                ])->values(),
            ]);

        return Inertia::render('Admin/Products', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            // Vị trí phục vụ để chọn khi thêm/sửa sản phẩm (vị trí 'coming' bị khoá ở UI).
            'service_locations' => ServiceLocation::ordered()->get()->map(fn (ServiceLocation $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'area' => $l->area,
                'status' => $l->status,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:150',
            'category_id' => 'required|integer|exists:categories,id',
            'description' => 'nullable|string',
            'price_per_day' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:active,hidden',
            'thumbnail' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
            'service_location_ids' => 'required|array|min:1',
            'service_location_ids.*' => 'integer|exists:service_locations,id',
        ], [
            'name.required' => 'Tên sản phẩm không được bỏ trống.',
            'category_id.required' => 'Vui lòng chọn danh mục.',
            'category_id.exists' => 'Danh mục không hợp lệ.',
            'price_per_day.required' => 'Giá thuê không được bỏ trống.',
            'price_per_day.numeric' => 'Giá thuê phải là số.',
            'quantity.required' => 'Số lượng không được bỏ trống.',
            'quantity.numeric' => 'Số lượng phải là số.',
            'deposit.numeric' => 'Tiền cọc phải là số.',
            'service_location_ids.required' => 'Vui lòng chọn ít nhất 1 vị trí phục vụ.',
            'service_location_ids.min' => 'Vui lòng chọn ít nhất 1 vị trí phục vụ.',
        ]);

        $slug = Slug::unique(Product::class, $data['name']);

        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('products', 'public');
        }

        $product = Product::create([
            'category_id' => (int) $data['category_id'],
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'price_per_day' => (int) $data['price_per_day'],
            'quantity' => (int) $data['quantity'],
            'deposit' => isset($data['deposit']) ? (int) $data['deposit'] : null,
            'status' => $data['status'] ?? 'active',
            'thumbnail' => $thumbnailPath,
        ]);

        $product->serviceLocations()->sync($data['service_location_ids']);

        return back()->with('success', 'Đã thêm sản phẩm.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:150',
            'category_id' => 'required|integer|exists:categories,id',
            'description' => 'nullable|string',
            'price_per_day' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:active,hidden',
            'thumbnail' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
            'service_location_ids' => 'required|array|min:1',
            'service_location_ids.*' => 'integer|exists:service_locations,id',
        ], [
            'name.required' => 'Tên sản phẩm không được bỏ trống.',
            'category_id.required' => 'Vui lòng chọn danh mục.',
            'category_id.exists' => 'Danh mục không hợp lệ.',
            'price_per_day.required' => 'Giá thuê không được bỏ trống.',
            'price_per_day.numeric' => 'Giá thuê phải là số.',
            'quantity.required' => 'Số lượng không được bỏ trống.',
            'quantity.numeric' => 'Số lượng phải là số.',
            'deposit.numeric' => 'Tiền cọc phải là số.',
            'service_location_ids.required' => 'Vui lòng chọn ít nhất 1 vị trí phục vụ.',
            'service_location_ids.min' => 'Vui lòng chọn ít nhất 1 vị trí phục vụ.',
        ]);

        $slug = Slug::unique(Product::class, $data['name'], $product->id);

        $thumbnailPath = $product->thumbnail;
        if ($request->hasFile('thumbnail')) {
            if ($thumbnailPath) {
                Storage::disk('public')->delete($thumbnailPath);
            }
            $thumbnailPath = $request->file('thumbnail')->store('products', 'public');
        }

        $product->update([
            'category_id' => (int) $data['category_id'],
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'price_per_day' => (int) $data['price_per_day'],
            'quantity' => (int) $data['quantity'],
            'deposit' => isset($data['deposit']) ? (int) $data['deposit'] : null,
            'status' => $data['status'] ?? $product->status,
            'thumbnail' => $thumbnailPath,
        ]);

        $product->serviceLocations()->sync($data['service_location_ids']);

        return back()->with('success', 'Đã cập nhật sản phẩm.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Xóa tất cả ảnh phụ trên storage
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->path);
        }
        $product->images()->delete();

        // Xóa thumbnail
        if ($product->thumbnail) {
            Storage::disk('public')->delete($product->thumbnail);
        }

        $product->delete();

        return back()->with('success', 'Đã xoá sản phẩm.');
    }

    public function storeImage(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'images' => 'required|array',
            'images.*' => ['file', self::MEDIA_MIMES, 'max:51200'], // ≤50MB
        ], [
            'images.*.mimetypes' => 'Chỉ nhận ảnh (jpg, png, webp) hoặc video (mp4, webm, mov).',
            'images.*.max' => 'Mỗi tệp tối đa 50MB.',
        ]);

        $maxSort = $product->images()->max('sort_order') ?? 0;

        foreach ($request->file('images') as $file) {
            $path = $file->store('products', 'public');
            $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');
            $product->images()->create([
                'path' => $path,
                'sort_order' => ++$maxSort,
                'type' => $isVideo ? 'video' : 'image',
            ]);
        }

        return back()->with('success', 'Đã tải lên ảnh.');
    }

    public function destroyImage(Product $product, ProductImage $image): RedirectResponse
    {
        // Chặn IDOR: ảnh phải thuộc đúng sản phẩm trên URL (CWE-639).
        abort_unless($image->product_id === $product->id, 404);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back()->with('success', 'Đã xoá ảnh.');
    }
}
