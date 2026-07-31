<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\ComboImage;
use App\Models\ComboItem;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Support\MediaRef;
use App\Support\MediaType;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ComboController extends Controller
{
    public function index(): Response
    {
        $combos = Combo::with(['items.product:id,name,price_per_day,quantity,status', 'images', 'serviceLocations'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Combo $combo) {
                $sumIndividual = $combo->sumIndividualPrice();

                return [
                    'id' => $combo->id,
                    'name' => $combo->name,
                    'slug' => $combo->slug,
                    'description' => $combo->description,
                    'combo_price' => $combo->combo_price,
                    'deposit' => $combo->deposit,
                    'suitable_for' => $combo->suitable_for,
                    'is_active' => $combo->is_active,
                    'sort_order' => $combo->sort_order,
                    // Tổng giá lẻ & tiết kiệm tính runtime từ giá hiện tại (PRD 5.2)
                    'sum_individual' => $sumIndividual,
                    'savings_amount' => $combo->savingsAmount(),
                    'savings_percent' => $combo->savingsPercent(),
                    'items' => $combo->items->map(fn (ComboItem $item) => [
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'product_name' => $item->product?->name,
                        'price_per_day' => $item->product?->price_per_day,
                        'product_status' => $item->product?->status,
                    ])->values(),
                    'service_location_ids' => $combo->serviceLocations->pluck('id')->values(),
                    'images' => $combo->images->map(fn (ComboImage $img) => [
                        'id' => $img->id,
                        'path' => Storage::disk('media')->url($img->path),
                        'sort_order' => $img->sort_order,
                        'type' => $img->type,
                    ])->values(),
                ];
            });

        return Inertia::render('Admin/Combos', [
            'combos' => $combos,
            // Danh sách sản phẩm cho picker (cả hidden — hiển thị kèm nhãn để admin biết)
            'products' => Product::with('serviceLocations:id')->orderBy('name')->get(['id', 'name', 'price_per_day', 'quantity', 'status'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price_per_day' => $p->price_per_day,
                    'quantity' => $p->quantity,
                    'status' => $p->status,
                    // Kho mà sản phẩm phục vụ — FE tính ngay được kho nào gán được cho combo
                    // khi admin thêm/bớt món, không phải gọi lại server.
                    'service_location_ids' => $p->serviceLocations->pluck('id')->values(),
                ])->values(),
            // Cơ sở đang mở để render chip chọn kho (kho 'coming' không cho gán).
            'service_locations' => ServiceLocation::open()->ordered()->get(['id', 'name'])
                ->map(fn (ServiceLocation $l) => ['id' => $l->id, 'name' => $l->name, 'slug' => $l->slug])->values(),
            // Tồn CẤU HÌNH theo kho: { locationId: { productId: qty } } — chỉ để hiện bảng
            // "Món tại kho này", KHÔNG dùng để chặn gán kho (PRD mục 6, R2).
            'location_stock' => $this->locationStockMatrix(),
            // Kho ảnh cho picker "chọn ảnh có sẵn" — nạp lazy khi mở picker (partial reload).
            'mediaLibrary' => Inertia::optional(fn () => MediaRef::library()),
        ]);
    }

    /**
     * Tồn cấu hình theo kho cho MỌI sản phẩm: { locationId: { productId: qty } }.
     * Một query trên pivot — shop chỉ có hơn chục sản phẩm nên không cần endpoint riêng.
     *
     * @return array<int, array<int, int>>
     */
    private function locationStockMatrix(): array
    {
        $out = [];
        foreach (DB::table('product_service_location')->get(['product_id', 'service_location_id', 'quantity']) as $row) {
            $out[(int) $row->service_location_id][(int) $row->product_id] = (int) $row->quantity;
        }

        return $out;
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $combo = Combo::create([
                ...$data['attributes'],
                'slug' => Slug::unique(Combo::class, $data['attributes']['name']),
            ]);
            $combo->items()->createMany($data['items']);
            $combo->serviceLocations()->sync($data['service_location_ids']);
        });

        return back()->with('success', 'Đã tạo combo.');
    }

    public function update(Request $request, Combo $combo): RedirectResponse
    {
        $data = $this->validated($request, $combo);

        DB::transaction(function () use ($combo, $data) {
            $combo->update([
                ...$data['attributes'],
                'slug' => Slug::unique(Combo::class, $data['attributes']['name'], $combo->id),
            ]);
            // Sync trọn danh sách món: xoá hết tạo lại trong transaction (danh sách ngắn, ≤50)
            $combo->items()->delete();
            $combo->items()->createMany($data['items']);
            $combo->serviceLocations()->sync($data['service_location_ids']);
        });

        return back()->with('success', 'Đã cập nhật combo.');
    }

    public function destroy(Combo $combo): RedirectResponse
    {
        // Xoá ảnh: xoá row trước, rồi chỉ xoá file khi không còn nơi khác dùng chung.
        $paths = $combo->images->pluck('path')->all();
        // combo_items + combo_images cascade theo FK; order_items.combo_id → null (giữ đơn cũ)
        $combo->delete();
        foreach ($paths as $path) {
            MediaRef::deleteFileIfOrphan($path);
        }

        return back()->with('success', 'Đã xoá combo.');
    }

    public function storeImage(Request $request, Combo $combo): RedirectResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'max:12'],
            'images.*' => ['file', MediaType::MIMES_RULE, 'max:51200'], // ≤50MB
        ], [
            'images.max' => 'Tối đa 12 ảnh/video mỗi lần.',
            'images.*.mimetypes' => 'Chỉ nhận ảnh (jpg, png, gif, webp) hoặc video (mp4, webm, mov).',
            'images.*.max' => 'Mỗi tệp tối đa 50MB.',
        ]);

        $maxSort = $combo->images()->max('sort_order') ?? 0;

        foreach ($request->file('images') as $file) {
            $combo->images()->create([
                'path' => $file->store('admin/combos', 'media'),
                'sort_order' => ++$maxSort,
                'type' => MediaType::detect($file),
            ]);
        }

        return back()->with('success', 'Đã tải lên ảnh.');
    }

    /**
     * Tái sử dụng ảnh đã upload: tạo row mới trỏ cùng file (chia sẻ, không copy).
     * Bỏ qua ảnh mà combo này đã có (tránh trùng path).
     */
    public function attachImages(Request $request, Combo $combo): RedirectResponse
    {
        $data = $request->validate([
            'sources' => ['required', 'array', 'min:1', 'max:24'],
            'sources.*.type' => ['required', 'in:product,combo'],
            'sources.*.id' => ['required', 'integer'],
        ], [
            'sources.required' => 'Chưa chọn ảnh nào.',
        ]);

        $existing = $combo->images()->pluck('path')->all();
        $maxSort = $combo->images()->max('sort_order') ?? 0;

        MediaRef::resolveSources($data['sources'])
            ->reject(fn (array $src) => in_array($src['path'], $existing, true))
            ->each(function (array $src) use ($combo, &$maxSort) {
                $combo->images()->create([
                    'path' => $src['path'],
                    'sort_order' => ++$maxSort,
                    'type' => $src['type'],
                ]);
            });

        return back()->with('success', 'Đã thêm ảnh.');
    }

    /** Sắp xếp lại thứ tự ảnh (kéo-thả): sort_order = vị trí trong mảng gửi lên. */
    public function reorderImages(Request $request, Combo $combo): RedirectResponse
    {
        $data = $request->validate([
            'image_ids' => ['required', 'array'],
            'image_ids.*' => ['integer'],
        ]);

        // Chỉ nhận id thuộc đúng combo (chống IDOR — CWE-639).
        $owned = $combo->images()->pluck('id')->all();
        foreach ($data['image_ids'] as $i => $id) {
            if (in_array((int) $id, $owned, true)) {
                $combo->images()->whereKey($id)->update(['sort_order' => $i]);
            }
        }

        return back()->with('success', 'Đã cập nhật thứ tự ảnh.');
    }

    public function destroyImage(Combo $combo, ComboImage $image): RedirectResponse
    {
        // Chặn IDOR: ảnh phải thuộc đúng combo trên URL (CWE-639).
        abort_unless($image->combo_id === $combo->id, 404);

        $path = $image->path;
        $image->delete();
        MediaRef::deleteFileIfOrphan($path);

        return back()->with('success', 'Đã xoá ảnh.');
    }

    /**
     * Validate chung cho store/update. Trả về [attributes, items] đã ép kiểu.
     *
     * @return array{attributes: array<string, mixed>, items: array<int, array{product_id: int, quantity: int}>}
     */
    private function validated(Request $request, ?Combo $ignore = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:150',
            'description' => 'nullable|string',
            'combo_price' => 'required|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',
            'suitable_for' => 'nullable|integer|min:1|max:100',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer|min:0',
            // max:50 = trần kỹ thuật chống abuse (ADR-4), UI khuyến nghị ≤ 8 món
            'items' => 'required|array|min:1|max:50',
            'items.*.product_id' => 'required|integer|distinct|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            // Combo phải bán ở ít nhất 1 kho (bopcamping-iylu): combo 0 kho lọt cả 2 chốt
            // vị trí của giỏ rồi bị checkout từ chối -> khách kẹt ở bước cuối.
            'service_location_ids' => 'required|array|min:1',
            'service_location_ids.*' => 'required|integer|distinct|exists:service_locations,id',
            'confirm_over_price' => 'sometimes|boolean',
        ], [
            'name.required' => 'Tên combo không được bỏ trống.',
            'combo_price.required' => 'Giá combo không được bỏ trống.',
            'combo_price.numeric' => 'Giá combo phải là số.',
            'deposit.numeric' => 'Tiền cọc phải là số.',
            'items.required' => 'Combo phải có ít nhất 1 sản phẩm.',
            'items.min' => 'Combo phải có ít nhất 1 sản phẩm.',
            'items.max' => 'Combo tối đa 50 món.',
            'items.*.product_id.distinct' => 'Mỗi sản phẩm chỉ được chọn 1 lần trong combo.',
            'items.*.product_id.exists' => 'Sản phẩm không hợp lệ.',
            'items.*.quantity.min' => 'Số lượng mỗi món tối thiểu là 1.',
            'service_location_ids.required' => 'Combo phải bán ở ít nhất 1 cơ sở.',
            'service_location_ids.min' => 'Combo phải bán ở ít nhất 1 cơ sở.',
        ]);

        $items = collect($data['items'])->map(fn (array $item) => [
            'product_id' => (int) $item['product_id'],
            'quantity' => (int) $item['quantity'],
        ]);

        // PRD 5.2: combo phải rẻ hơn tổng giá lẻ — vi phạm thì bắt xác nhận override
        // có chủ đích (confirm_over_price) thay vì chặn cứng.
        $comboPrice = (int) $data['combo_price'];
        $prices = Product::whereIn('id', $items->pluck('product_id'))->pluck('price_per_day', 'id');
        $sumIndividual = $items->sum(fn (array $item) => (int) $prices[$item['product_id']] * $item['quantity']);

        if ($comboPrice >= $sumIndividual && ! $request->boolean('confirm_over_price')) {
            throw ValidationException::withMessages([
                'combo_price' => "Giá combo ({$comboPrice}₫) không rẻ hơn tổng giá thuê lẻ ({$sumIndividual}₫). Tick \"Vẫn lưu\" nếu đây là chủ đích.",
            ]);
        }

        $locationIds = collect($data['service_location_ids'])
            ->map(fn ($id) => (int) $id)->unique()->values();

        $this->assertLocationsServeAllItems($locationIds, $items->pluck('product_id'));

        return [
            'attributes' => [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'combo_price' => $comboPrice,
                'deposit' => isset($data['deposit']) ? (int) $data['deposit'] : null,
                'suitable_for' => $data['suitable_for'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ],
            'items' => $items->all(),
            'service_location_ids' => $locationIds->all(),
        ];
    }

    /**
     * Mọi kho được gán PHẢI phục vụ TẤT CẢ món của combo — tính từ items ĐANG GỬI LÊN,
     * không tin FE (FE cũng chặn nhưng đó chỉ là tiện lợi).
     *
     * ⚠️ Cơ sở là tư cách thành viên pivot, KHÔNG phải tồn > 0. Prod chỉ 3/11 sản phẩm còn
     * tồn, có combo mọi món tồn 0 — chặn theo tồn thì admin không lưu nổi combo nào
     * (PRD mục 6, R2). Tồn 0 chỉ hiển thị cảnh báo ở UI, không chặn.
     *
     * @param  Collection<int, int>  $locationIds
     * @param  Collection<int, int>  $productIds
     */
    private function assertLocationsServeAllItems($locationIds, $productIds): void
    {
        $productIds = $productIds->unique()->values();

        // [service_location_id => [product_id...]] trong PHẠM VI món của combo này.
        $served = DB::table('product_service_location')
            ->whereIn('service_location_id', $locationIds)
            ->whereIn('product_id', $productIds)
            ->get()
            ->groupBy('service_location_id')
            ->map(fn ($rows) => $rows->pluck('product_id')->map(fn ($id) => (int) $id)->all());

        $problems = [];
        foreach ($locationIds as $locationId) {
            $missing = $productIds->diff($served[$locationId] ?? []);
            if ($missing->isNotEmpty()) {
                $problems[$locationId] = $missing->values()->all();
            }
        }

        if ($problems === []) {
            return;
        }

        $locationNames = ServiceLocation::whereIn('id', array_keys($problems))->pluck('name', 'id');
        $productNames = Product::whereIn('id', $productIds)->pluck('name', 'id');

        $lines = [];
        foreach ($problems as $locationId => $missingIds) {
            $names = collect($missingIds)->map(fn (int $id) => $productNames[$id] ?? "#{$id}")->implode(', ');
            $lines[] = ($locationNames[$locationId] ?? "#{$locationId}").': '.$names;
        }

        throw ValidationException::withMessages([
            'service_location_ids' => 'Có món không phục vụ tại cơ sở đã chọn — '.implode(' · ', $lines).'.',
        ]);
    }
}
