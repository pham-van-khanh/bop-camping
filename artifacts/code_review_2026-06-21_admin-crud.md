# Code Review — Admin CRUD (sản phẩm + danh mục)

- **Ngày:** 2026-06-21
- **Phạm vi:** thay đổi chưa commit của coworker (~1.222 dòng): `Admin/ProductController.php`, `Admin/CategoryController.php`, `Admin/AuthController.php`, `HandleInertiaRequests.php`, `routes/web.php`, `Admin/Products.tsx`, `Admin/Categories.tsx`, `ProductDetail.tsx`, `types/index.d.ts`, layouts.
- **Cách review:** 3 `worker-reviewer` song song (bug/logic · bảo mật · chất lượng), đọc code thật.
- **Kết luận:** Needs Work — 1 Critical, 4 High, nhiều Medium/Low.

## ✅ Đã làm tốt (verified)
- Admin routes **được bảo vệ** bởi middleware `EnsureAdmin` (kiểm `auth` + `is_admin`) — không có CRUD admin public.
- Không mass-assignment: controller dùng `validate()` + mảng key tường minh + `$fillable`.
- Không N+1: đã `with(['category','images'])`, `withCount('products')`.
- Không raw SQL; `money()` tái dùng từ `lib/format`; không `any`; xoá danh mục có chặn khi còn sản phẩm.

## 🔴 CRITICAL
**C1 — Sửa sản phẩm/danh mục: ảnh upload bị bỏ âm thầm** (`Admin/Products.tsx:116`, `Admin/Categories.tsx:64`)
`form.put(...)` kèm file → trình duyệt gửi PUT thật, **PHP không nạp `$_FILES` cho PUT**, nên `$request->hasFile('thumbnail'|'image')` luôn `false` khi sửa. Admin đổi ảnh → báo thành công nhưng ảnh không đổi. (Field text vẫn lưu được nên dễ bỏ sót.)
**Fix:** dùng POST + method spoofing:
```ts
form.transform((d) => ({ ...d, _method: 'put' }))
    .post(route('admin.products.update', editing.id), opts);
```

## 🟠 HIGH
**H1 — Lộ toàn bộ User ra props Inertia (CWE-200)** (`HandleInertiaRequests.php:34-36`)
`'user' => $request->user()` nhét cả model (`phone`, `email`, `is_admin`, timestamps) vào mọi trang. `$hidden` chỉ ẩn password/token.
**Fix:** whitelist `{ id, name, is_admin }`.

**H2 — `category_id` thành `0` khi chọn lại placeholder** (`Admin/Products.tsx:414-416`)
`Number('')` = `0` (không phải `''`) → lệch controlled-select + submit `category_id=0`.
**Fix:** `e.target.value === '' ? '' : Number(e.target.value)` (giống price/quantity/deposit).

**H3 — `blank()` chú thích kiểu `FormData` (DOM) thay vì `ProductFormData`** (`Admin/Products.tsx:70`)
Sai kiểu → mất kiểm tra compile-time. **Fix:** đổi annotation thành `ProductFormData`.

**H4 — Dùng em-dash trong copy (vi phạm RULES §6)** (`Admin/Products.tsx:164,418`; `Admin/Categories.tsx:81`)
**Fix:** thay `—` bằng `·` / `-`.

## 🟡 MEDIUM
- **M1 — Cho upload SVG (CWE-434, stored XSS):** rule `image` gồm cả svg, file phục vụ public. **Fix:** `mimes:jpg,jpeg,png,webp`.
- **M2 — Slug uniqueness lặp 4 chỗ (DRY):** tách `SlugService`/trait dùng chung.
- **M3 — Thiếu phân trang admin Products** (`->get()` toàn bảng). **Fix:** `paginate(50)`.
- **M4 — Thiếu trạng thái loading/lỗi cho list; Products `doDelete` không có `onError`** (Categories có). 
- **M5 — Hardcode hex / lệch palette** (`#dde4cc` thay vì token `cardBorder #E3E8D6`); pill trạng thái nên tái dùng `STATUS_STYLE` của `Orders.tsx`.

## 🔵 LOW / INFO
- Login admin **chưa throttle** (CWE-307) → thêm `throttle:5,1`.
- `destroyImage` không kiểm ảnh thuộc đúng product (IDOR, admin-only → thấp).
- `storeImage` thiếu `preserveScroll` (lệch với deleteImage).
- Số đếm header (`{products.length} sản phẩm`) nên dùng `font-mono` (RULES §2).

---
**Ưu tiên sửa ngay:** C1 → H1 → H2/H3/H4 → M1.
