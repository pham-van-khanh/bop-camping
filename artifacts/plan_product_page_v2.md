# Plan — Epic 1: Trang chi tiết sản phẩm v2

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (inline) để triển khai từng task. Các bước dùng checkbox (`- [ ]`).

**Goal:** Nâng cấp trang `/thiet-bi/{slug}`: gallery dọc bên trái, thông số key–value, khối setup rich-text (TipTap), lịch thu gọn, đánh giá xuống cuối, "You may also like" admin chọn.

**Architecture:** Monolith Laravel 12 + Inertia. Thêm 2 cột (`specs` JSON, `setup_content` LONGTEXT) vào `products`, pivot mới `product_related`. TipTap phía admin xuất HTML → sanitize server-side (HTMLPurifier qua `mews/purifier`) → render phía khách. Màn admin riêng cho soạn nội dung setup.

**Tech Stack:** Laravel 12 · Inertia · React 18 + TS · TipTap (`@tiptap/react`) · `mews/purifier` · Tailwind.

**Spec gốc:** `artifacts/design_spec_product_page_v2_feedback_seo.md` (Epic 1)

## Global Constraints

- Branch: `feature/product-page-v2` từ `feat/scaffold-laravel`; merge vào `develop` để test stg.
- Quality gates trước merge: `php artisan test` · `./vendor/bin/pint --test` · `npx tsc --noEmit` · `npm run build`.
- Migration tương thích SQLite + MySQL 8. Test collation-safe.
- Không dùng `any` trong TS. Mỗi task 1 commit (hoặc hơn), atomic.
- Tiếng Việt cho copy UI, tông be/đất theo theme hiện có.

---

### Task 1: Nền tảng editor — sanitize + upload ảnh + RichTextEditor

**Files:**
- Modify: `composer.json` (require `mews/purifier`), `package.json` (tiptap)
- Create: `config/purifier.php` (publish rồi sửa), `app/Support/EditorHtml.php`
- Create: `app/Http/Controllers/Admin/EditorImageController.php`
- Modify: `routes/web.php` (trong group admin hiện có)
- Create: `resources/js/Components/admin/RichTextEditor.tsx`
- Test: `tests/Feature/Admin/EditorTest.php`

**Interfaces:**
- Produces: `EditorHtml::clean(?string $html): ?string` — sanitize, trả `null` nếu rỗng.
- Produces: `POST /admin/editor/images` (multipart `image`) → JSON `{url: string}` — auth admin.
- Produces: `<RichTextEditor value={html} onChange={(html) => ...} />` — TipTap, toolbar: H2/H3, bold, italic, bullet/ordered list, link, image (upload qua endpoint trên), undo/redo.

**Steps:**

- [ ] Cài backend: `composer require mews/purifier` → publish config → sửa thêm profile `editor`:

```php
// config/purifier.php — thêm vào 'settings'
'editor' => [
    'HTML.Allowed' => 'h2,h3,h4,p,br,b,strong,i,em,u,s,ul,ol,li,blockquote,hr,'
        .'a[href|title|target|rel],img[src|alt]',
    'URI.AllowedSchemes' => ['http' => true, 'https' => true],
    'Attr.AllowedFrameTargets' => ['_blank'],
    'AutoFormat.RemoveEmpty' => true,
],
```

- [ ] `app/Support/EditorHtml.php`:

```php
<?php

namespace App\Support;

use Mews\Purifier\Facades\Purifier;

class EditorHtml
{
    /** Sanitize HTML từ TipTap (chống XSS, CWE-79). Rỗng/chỉ thẻ trống -> null. */
    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        $clean = trim(Purifier::clean($html, 'editor'));

        return $clean === '' || trim(strip_tags($clean)) === '' ? null : $clean;
    }
}
```

- [ ] Test trước (TDD) — `tests/Feature/Admin/EditorTest.php`: `test_editor_html_strips_script_keeps_img`, `test_admin_can_upload_editor_image` (Storage::fake('media'), assert JSON url), `test_guest_cannot_upload_editor_image` (assert redirect login). Chạy fail → viết `EditorImageController@store` (validate `image: required|file|mimes:jpg,jpeg,png,webp|max:4096`, store `editor/` disk media, trả `{url}`) + route `Route::post('/admin/editor/images', ...)` trong group admin → pass.
- [ ] Cài FE: `npm install @tiptap/react @tiptap/starter-kit @tiptap/extension-image` (+ `@tiptap/extension-link` nếu starter-kit bản resolve chưa gồm Link).
- [ ] `RichTextEditor.tsx`: `useEditor({ extensions: [StarterKit, Image, Link], content: value })`, `onUpdate` → `onChange(editor.getHTML())`; toolbar button ảnh mở file picker → POST `/admin/editor/images` (kèm CSRF header như các fetch admin hiện có) → `editor.chain().focus().setImage({ src: url }).run()`. Style khung theo input admin hiện tại.
- [ ] Thêm CSS `.editor-content` (app.css): style h2/h3/p/ul/ol/img (img `max-width:100%`, bo góc, margin dọc) — dùng chung cho vùng soạn và render phía khách.
- [ ] Gates nhanh (`php artisan test --filter=EditorTest`, `npx tsc --noEmit`) → commit `feat(editor): TipTap + sanitize + upload ảnh editor`.

---

### Task 2: DB + Model — specs, setup_content, product_related

**Files:**
- Create: `database/migrations/2026_07_09_000001_add_specs_and_setup_content_to_products.php`
- Create: `database/migrations/2026_07_09_000002_create_product_related_table.php`
- Modify: `app/Models/Product.php`
- Test: `tests/Feature/Admin/ProductSpecsRelatedTest.php` (tạo ở task này, mở rộng ở Task 3)

**Interfaces:**
- Produces: `products.specs` (json nullable, cast `array`, dạng `[{key, value}]`), `products.setup_content` (longText nullable).
- Produces: `Product::related(): BelongsToMany` — pivot `product_related` (`product_id`, `related_product_id`, `sort_order`), orderBy sort_order — mô hình y hệt `accessories()`.

**Steps:**

- [ ] Migration 1: `$table->json('specs')->nullable(); $table->longText('setup_content')->nullable();` (after `description`).
- [ ] Migration 2:

```php
Schema::create('product_related', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
    $table->unique(['product_id', 'related_product_id']);
});
```

- [ ] `Product.php`: thêm `specs`, `setup_content` vào `$fillable`; `'specs' => 'array'` vào `$casts`; relation `related()` (copy pattern `accessories()`, đổi bảng/cột).
- [ ] Test model: lưu specs array → đọc lại đúng; related trả theo sort_order. `php artisan migrate` + test pass → commit `feat(product): cột specs/setup_content + bảng product_related`.

---

### Task 3: Admin — form specs key–value, picker related, màn soạn nội dung

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductController.php` (validate + lưu specs, related; props index)
- Create: `app/Http/Controllers/Admin/ProductContentController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Admin/Products.tsx` (bảng specs động, picker related — tái dùng UI picker accessories, nút "Nội dung")
- Create: `resources/js/Pages/Admin/ProductContent.tsx`
- Test: `tests/Feature/Admin/ProductSpecsRelatedTest.php`, `tests/Feature/Admin/ProductContentTest.php`

**Interfaces:**
- Consumes: `EditorHtml::clean`, `RichTextEditor`, `Product::related()`.
- Produces: validate `specs` (`sometimes|nullable|array|max:30`; `specs.*.key: required|string|max:100`; `specs.*.value: required|string|max:500`), `related_ids` (`sometimes|nullable|array|max:12`; `related_ids.*: integer|distinct|exists:products,id`, tự loại chính nó như accessories).
- Produces: routes `GET/PUT /admin/products/{product}/noi-dung` → page `Admin/ProductContent` props `{product: {id, name, setup_content}}`.

**Steps:**

- [ ] TDD backend: viết test — lưu specs + related qua PUT update; specs thiếu value → 422; related chứa chính nó → bị loại; `setup_content` chứa `<script>` → lưu xong không còn script. Chạy fail.
- [ ] `Admin/ProductController`: thêm rule + message; lưu `'specs' => $this->cleanSpecs($data)` (trim, bỏ dòng key rỗng, `?: null`); tổng quát hoá `syncAccessories` → dùng chung cho `related_ids` (method `syncSortedRelation(Product $product, array $data, string $key, string $relation)`); index() bổ sung `specs`, `related_ids`, `has_setup_content` vào payload từng sản phẩm.
- [ ] `ProductContentController`: `edit()` render `Admin/ProductContent`; `update()` validate `setup_content: nullable|string|max:200000` → `EditorHtml::clean` → save. Routes vào group admin. Test pass.
- [ ] FE `Admin/Products.tsx`: trong form thêm/sửa — khối "Thông số" (bảng động: input key | input value | nút ×, nút "+ Thêm dòng"; gửi lên như `specs[i][key]`); khối "Có thể bạn cũng thích" — copy pattern picker accessories hiện có (search → click chọn, max 12); mỗi dòng bảng thêm nút "Nội dung" → `Link` tới `/admin/products/{id}/noi-dung` (badge chấm xanh khi `has_setup_content`).
- [ ] FE `Admin/ProductContent.tsx`: layout admin hiện có, breadcrumb "Sản phẩm → {name} → Nội dung trang", `RichTextEditor` full-width (min-height ~60vh), nút Lưu (PUT, `router.put`), toast success theo pattern flash hiện có.
- [ ] Gates (`php artisan test`, `tsc`, build) → commit `feat(admin): specs key-value + related picker + màn soạn nội dung sản phẩm`.

---

### Task 4: Shop backend — props mới cho trang chi tiết

**Files:**
- Modify: `app/Http/Controllers/Shop/ProductController.php` (`show()`)
- Modify: `resources/js/types/product.ts`
- Test: `tests/Feature/Shop/ProductDetailV2Test.php`

**Interfaces:**
- Produces (props Inertia `ProductDetail`): `product.specs: {key, value}[]`, `product.setup_content: string|null` (HTML đã sanitize), `related_products: ProductResource[]` (chỉ `active`, shape() như card danh sách, theo sort_order).

**Steps:**

- [ ] TDD: test props show chứa specs/setup_content/related; related bỏ sản phẩm `hidden`. Fail → sửa `shape()` (thêm `specs => $p->specs ?? []`, `setup_content => $p->setup_content`) — **lưu ý:** `shape()` dùng chung cho card danh sách; để payload nhẹ, chỉ nhét `setup_content` ở `show()` (merge thêm sau `$this->shape($p)`), specs để trong shape (nhẹ). `related_products`: `$p->related()->where('status','active')->with('category','images','serviceLocations')->get()->map(fn ($r) => $this->shape($r))`.
- [ ] Cập nhật `types/product.ts`: `specs?: {key: string; value: string}[]`, `setup_content?: string | null`.
- [ ] Test pass → commit `feat(shop): props specs/setup_content/related cho trang chi tiết`.

---

### Task 5: FE — bố cục v2: gallery dọc + object-contain + next/prev (1.1, 1.8)

**Files:**
- Modify: `resources/js/Pages/ProductDetail.tsx`

**Interfaces:**
- Consumes: props hiện có (`product.images`, gallery build sẵn dòng 121–129).

**Steps:**

- [ ] Grid ngoài: 2 cột `lg:grid-cols-[minmax(0,1fr)_400px]` (trái = gallery+thông số+setup, phải = info). Bỏ `auto-fit` cũ.
- [ ] Khối gallery: `flex gap-2.5` — cột thumbnails dọc bên trái (`hidden md:flex md:flex-col w-[76px] gap-2.5`, mỗi thumb `h-[64px]`, giữ style outline active hiện có), ảnh chính `flex-1`. Mobile: thumbnails giữ hàng ngang dưới ảnh (block `md:hidden`, markup 4-cột hiện tại).
- [ ] Ảnh chính: đổi `object-cover` → `object-contain` trên nền `#f4f6ee` (giữ overlay gradient + tên danh mục); thêm 2 nút ‹ › (`absolute left-3/right-3 top-1/2`, `bg-black/35 hover:bg-black/55`, ẩn khi gallery ≤ 1): `setActiveImg((i) => (i + dir + gallery.length) % gallery.length)`.
- [ ] Keyboard: `ArrowLeft/ArrowRight` trong lightbox cũng next/prev (bonus nhỏ, cùng handler).
- [ ] `tsc` + `npm run build` + xem bằng mắt (dev server) → commit `feat(shop): gallery dọc + ảnh contain + nút chuyển ảnh`.

---

### Task 6: FE — thông số + setup + nút "Xem thêm" (1.2, 1.3, 1.4)

**Files:**
- Modify: `resources/js/Pages/ProductDetail.tsx`

**Steps:**

- [ ] Card "THÔNG SỐ" dưới khối gallery (cột trái): heading mono tracking như các section (`THÔNG SỐ` màu campfire), list `divide-y`: key trái (`text-moss`) — value phải (`font-semibold text-ink text-right`). Ẩn khi `!product.specs?.length`.
- [ ] Section setup full-width dưới grid chính (trước "Thường thuê cùng"): `<section id="chi-tiet" className="editor-content ...">` render `dangerouslySetInnerHTML={{ __html: product.setup_content }}` (an toàn — đã sanitize server). Heading section "CHI TIẾT SẢN PHẨM". Ẩn khi `!product.setup_content`.
- [ ] Mô tả ngắn (cột phải): thêm nút "Xem thêm ↓" dưới `<p>` mô tả — `onClick={() => document.getElementById('chi-tiet')?.scrollIntoView({ behavior: 'smooth' })}`; chỉ render khi có `setup_content`.
- [ ] `tsc` + build → commit `feat(shop): khối thông số + nội dung chi tiết + nút xem thêm`.

---

### Task 7: FE — lịch thu gọn (1.7)

**Files:**
- Modify: `resources/js/Pages/ProductDetail.tsx` (bọc collapse tại chỗ gọi `DateRangeCalendar`, dòng 363 cũ — KHÔNG sửa component lịch)

**Steps:**

- [ ] State `calOpen` (mặc định `false`). Ô trigger: nút viền card hiện theme — icon 📅 + text: chưa chọn → "Chọn ngày thuê"; đã chọn → `rangeText(start, end)`; mũi tên xoay theo open.
- [ ] Mở: render `DateRangeCalendar` ngay dưới (giữ trong flow, không popover — mobile an toàn). Trong `onChange`: nếu `s && e` (chọn xong end) → `setCalOpen(false)`.
- [ ] `tsc` + build → commit `feat(shop): lịch chọn ngày dạng thu gọn`.

---

### Task 8: FE — đánh giá xuống cuối + You may also like (1.5, 1.6)

**Files:**
- Modify: `resources/js/Pages/ProductDetail.tsx`

**Interfaces:**
- Consumes: `related_products: ProductResource[]` (Task 4); component card — tái dùng card của trang `Products.tsx` nếu đã tách component, nếu chưa thì card gọn inline (thumbnail/grad, tên, giá/ngày, Link).

**Steps:**

- [ ] Chuyển `<ProductReviews …/>` từ trong cột gallery (dòng 302–310 cũ) ra section full-width sau "Thường thuê cùng".
- [ ] Section "CÓ THỂ BẠN CŨNG THÍCH" sau đánh giá: grid `sm:grid-cols-2 lg:grid-cols-4 gap-4`; ẩn khi rỗng.
- [ ] Thứ tự cuối trang: setup → thường thuê cùng → đánh giá → you may also like. Mobile kiểm tra lại flow.
- [ ] `tsc` + build → commit `feat(shop): review xuống cuối + section You may also like`.

---

### Task 9: Quality gates + đưa lên stg

**Steps:**

- [ ] `php artisan test` toàn bộ · `./vendor/bin/pint --test` · `npx tsc --noEmit` · `npm run build` — tất cả pass (fix nếu fail).
- [ ] Tự duyệt UI bằng dev server: desktop + mobile viewport (gallery, specs, setup, collapse lịch, related).
- [ ] Merge `feature/product-page-v2` → `develop`, push (auto-deploy stg) → báo user test.
- [ ] User OK → merge feature branch → `feat/scaffold-laravel`, push, `bd close` các task Epic 1.

## Self-review

- Spec coverage: 1.1→T5 · 1.2→T2/T3/T6 · 1.3→T6 · 1.4→T1/T2/T3/T6 · 1.5→T8 · 1.6→T2/T3/T4/T8 · 1.7→T7 · 1.8→T5 · sanitize/XSS→T1 · test→T1–T4. Đủ.
- Type nhất quán: `specs: {key, value}[]`, `related_ids`, `setup_content` dùng thống nhất T2–T8.
