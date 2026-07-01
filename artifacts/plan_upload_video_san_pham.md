# Plan: Cho phép admin upload video cho sản phẩm

- **Liên quan:** `bopcamping-qwg`, [adr_product_video_upload.md](adr_product_video_upload.md)
- **Kích cỡ:** Small feature (~1 ngày) — Two-Way Door (dễ đảo ngược: thêm cột có default, không đổi schema phá vỡ)
- **Nguồn tham chiếu đã xác nhận qua worker-explorer:**
  - Test pattern mirror: `tests/Feature/AdminCampingSpotTest.php::admin_can_upload_and_delete_media_image_and_video` +
    `cannot_delete_media_of_another_spot`; `tests/Feature/ReviewSubmitTest.php::eligible_customer_can_submit_review_with_image_and_video`
  - Test hiện có cần giữ nguyên/mở rộng: `tests/Feature/AdminProductTest.php::cannot_delete_image_through_wrong_product` (IDOR đã có, chỉ cần thêm case video)
  - FE video-render mirror: `resources/js/Pages/Admin/CampingSpots.tsx:222-227` (thumbnail muted+▶),
    `resources/js/Components/site/ProductReviews.tsx:302-304` (main view controls),
    `resources/js/Components/site/CampingGuideModal.tsx:169-191` (main + thumbnail strip)

## Task 1 — Migration + Model (nền tảng, không phụ thuộc)

**File mới:** `database/migrations/2026_07_02_000001_add_type_to_product_images_table.php`
```php
Schema::table('product_images', function (Blueprint $table) {
    $table->enum('type', ['image', 'video'])->default('image')->after('product_id');
});
```
Clone chính xác cấu trúc `add_type_to_review_images_table` (down() dropColumn tương ứng).

**Sửa:** `app/Models/ProductImage.php` — thêm `'type'` vào `$fillable`.

**Acceptance criteria:**
- `php artisan migrate` chạy sạch, `php artisan migrate:rollback` phục hồi đúng.
- Bản ghi `product_images` cũ tự động có `type = 'image'` (default, không cần backfill thủ công).

## Task 2 — Backend: Admin\ProductController (phụ thuộc Task 1)

**Sửa:** `app/Http/Controllers/Admin/ProductController.php`
1. Thêm hằng (mirror `CampingSpotController`):
   ```php
   private const MEDIA_MIMES = 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime';
   ```
2. `storeImage()`: đổi
   ```php
   'images.*' => 'file|mimes:jpg,jpeg,png,webp|max:4096',
   ```
   → 
   ```php
   'images.*' => ['file', self::MEDIA_MIMES, 'max:51200'],
   ```
   Khi tạo record, gán type theo `str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image'` (mirror `CampingSpotController::storeMedia`).
3. `index()`: map `images` bổ sung `'type' => $img->type`.
4. `destroyImage()`: **không đổi** — IDOR check đã có (`abort_unless($image->product_id === $product->id, 404)`), đã được `AdminProductTest::cannot_delete_image_through_wrong_product` cover.

**Sửa:** `routes/web.php` — route `admin.products.images.store` thêm `->middleware('throttle:60,1')` (khớp `camping-spot.media.store`).

**Sửa:** `app/Http/Controllers/Shop/ProductController.php` — nếu có map riêng `images` cho trang public (index/show), bổ sung `'type'` tương tự (kiểm tra trước khi sửa — có thể chưa cần nếu chỉ dùng `path`).

**Acceptance criteria:**
- Upload 1 ảnh + 1 video cùng lúc → 2 record `product_images` với `type` tương ứng `['image','video']`.
- Upload file mimetype không hợp lệ (vd `.pdf` hoặc `.php` đổi tên đuôi `.mp4`) → 422, không tạo record.
- Xoá qua route với `product_id` sai → 404 (regression test đã có, chỉ cần đảm bảo không phá vỡ).

## Task 3 — Frontend: Admin/Products.tsx (phụ thuộc Task 2, song song với Task 4)

**File:** `resources/js/Pages/Admin/Products.tsx`
1. Dòng 8: `type ProductImage = { id: number; path: string; sort_order: number };` → thêm `type: 'image' | 'video';`.
2. Dòng 199-206: input hidden — `accept="image/*"` → `accept="image/*,video/*"`.
3. Dòng ~376-391: JSX render thumbnail — mirror đúng pattern `CampingSpots.tsx:222-227`:
   ```tsx
   {img.type === 'video' ? (
       <video src={img.path} className="h-20 w-20 rounded-[10px] border border-cardBorder object-cover" muted />
   ) : (
       <img src={img.path} className="h-20 w-20 rounded-[10px] border border-cardBorder object-cover" />
   )}
   {img.type === 'video' && <span className="pointer-events-none absolute inset-0 grid place-items-center text-white">▶</span>}
   ```
   Giữ nguyên nút xoá hiện có (vị trí `-right-1.5 -top-1.5`).
4. Đổi label nút "Upload ảnh" → "Upload ảnh/video" (nơi hiển thị nút trigger `uploadRef.current?.click()`).

**Acceptance criteria:** admin thấy video hiện thumbnail có badge ▶, click vẫn xoá đúng item.

## Task 4 — Frontend: ProductDetail.tsx (phụ thuộc Task 2, song song với Task 3)

**File:** `resources/js/Pages/ProductDetail.tsx`
1. Dòng 43-51 (`gallery` useMemo): mở rộng type
   ```ts
   const gallery: ({ type: 'img' | 'video'; src: string } | { type: 'grad'; bg: string })[] = useMemo(() => {
       if (product.images.length > 0) {
           return product.images.map((img) => ({
               type: img.type === 'video' ? 'video' as const : 'img' as const,
               src: img.path,
           }));
       }
       ...
   }, [product.images, baseGrad]);
   ```
2. Dòng ~119-126 (main slide render): thêm nhánh video — mirror `ProductReviews.tsx:302-304`:
   ```tsx
   {activeSlide.type === 'video' && <video src={activeSlide.src} controls className="h-full w-full object-cover" />}
   ```
3. Dòng ~130-146 (thumbnail strip): thêm nhánh video — mirror `CampingGuideModal.tsx:189-191` (muted + badge ▶).
4. Props type sản phẩm phía FE (`product.images` item type) — đảm bảo backend đã trả `type` (Task 2 mục Shop\ProductController).

**Acceptance criteria:** trang chi tiết sản phẩm phát được video, thumbnail dải dưới hiện badge ▶ cho video.

## Task 5 — Test (phụ thuộc Task 1, 2 — không phụ thuộc FE)

**Sửa:** `tests/Feature/AdminProductTest.php` — thêm test mới, mirror
`AdminCampingSpotTest::admin_can_upload_and_delete_media_image_and_video`:
```php
/** @test */
public function admin_can_upload_and_delete_image_and_video(): void
{
    Storage::fake('public');
    $product = $this->makeProduct(null);

    $this->actingAs($this->admin())->post(route('admin.products.images.store', $product), [
        'images' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->create('clip.mp4', 800, 'video/mp4'),
        ],
    ])->assertRedirect();

    $this->assertSame(2, $product->images()->count());
    $this->assertSame(['image', 'video'], $product->images()->orderBy('id')->pluck('type')->all());
}

/** @test */
public function image_upload_rejects_invalid_mimetype(): void
{
    Storage::fake('public');
    $product = $this->makeProduct(null);

    $this->actingAs($this->admin())->post(route('admin.products.images.store', $product), [
        'images' => [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
    ])->assertSessionHasErrors('images.0');

    $this->assertSame(0, $product->images()->count());
}
```
Test IDOR đã có (`cannot_delete_image_through_wrong_product`) — không cần thêm, đã cover cả video vì check chỉ dựa vào `product_id`, không phụ thuộc `type`.

**Acceptance criteria:** `php artisan test --filter=AdminProductTest` pass toàn bộ, bao gồm 2 test mới.

## Dependency graph

```
Task 1 (migration+model)
  ├─→ Task 2 (backend controller+route)
  │     ├─→ Task 3 (FE admin upload UI)      ─┐
  │     ├─→ Task 4 (FE product detail gallery)─┤ song song, không phụ thuộc nhau
  │     └─→ Task 5 (tests)                     ┘
```

## Quality gates trước khi merge

- `php artisan test --filter=AdminProductTest` (+ full suite để tránh regression)
- `npx tsc --noEmit`
- `npm run build`
- `./vendor/bin/pint --test`
- Test trên trình duyệt thật: upload ảnh+video ở Admin/Products, xem gallery ở ProductDetail
