# Plan — Epic 4: Trang giới thiệu `/gioi-thieu`

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (inline). Checkbox theo task.

**Goal:** Trang giới thiệu shop chỉnh sửa được trong admin (tiêu đề + ảnh bìa + nội dung TipTap), seed sẵn template, link ở header + footer.

**Architecture:** Bảng `static_pages` (slug unique) — cơ chế dùng lại cho trang tĩnh tương lai. `StaticPage::about()` = firstOrCreate với template mặc định (single source, gọi từ seeder + controller → prod không cần chạy seeder riêng). Public render bằng `MagazineContent` (tái dùng bố cục xen kẽ của Epic 1). Editor + sanitize tái dùng `RichTextEditor` + `EditorHtml::clean`.

**Tech Stack:** Laravel 12 · Inertia · React/TS · TipTap (có sẵn) · mews/purifier (có sẵn).

**Spec gốc:** `artifacts/design_spec_product_page_v2_feedback_seo.md` (Epic 4)

## Global Constraints

- Branch `feature/about-page` từ `feat/scaffold-laravel`; test stg qua `develop`.
- Gates: `php artisan test` · `pint --test` · `tsc --noEmit` · `npm run build`.
- Migration tương thích SQLite + MySQL. Copy UI tiếng Việt, tông be/đất.

---

### Task 1: DB + Model + seed template

**Files:** migration `2026_07_10_000001_create_static_pages_table.php` · `app/Models/StaticPage.php` · `database/seeders/StaticPageSeeder.php` (+ gọi trong `DatabaseSeeder`) · test `tests/Feature/StaticPageTest.php`

- Bảng: `id, slug (unique), title, cover_path (nullable), content (longText nullable), timestamps`.
- `StaticPage::about(): self` — `firstOrCreate(['slug' => 'gioi-thieu'], self::aboutDefaults())`; template: câu chuyện shop, cam kết sạch–đủ–chuẩn, khu vực phục vụ, CTA — ảnh demo từ `/images/album/*` xen kẽ (khớp MagazineContent).
- Test: `about()` tự tạo record với template; gọi 2 lần không nhân đôi.

### Task 2: Admin "Trang nội dung"

**Files:** `app/Http/Controllers/Admin/StaticPageController.php` (`index/edit/update`) · routes group admin (`GET /admin/pages`, `GET/PUT /admin/pages/{staticPage}`) · `resources/js/Pages/Admin/StaticPages.tsx` (list) · `resources/js/Pages/Admin/StaticPageEdit.tsx` (title + cover upload + RichTextEditor full-width) · sidebar `AdminLayout.tsx` thêm mục "Trang nội dung" · test `tests/Feature/AdminStaticPageTest.php`

- `index`: gọi `StaticPage::about()` (đảm bảo có record) rồi list tất cả pages.
- `update`: validate `title required|max:150`, `cover nullable|file|mimes:jpg,jpeg,png,webp|max:4096`, `content nullable|string|max:200000` → `EditorHtml::clean`; upload cover vào `pages/` disk media (xoá cover cũ); POST + `_method=put` (file upload).
- Test: update sanitize script; cover upload lưu file + xoá cũ; guest redirect `/admin/login`.

### Task 3: Public `/gioi-thieu` + link nav

**Files:** `app/Http/Controllers/Shop/StaticPageController.php` (`about`) · route `GET /gioi-thieu` name `about` · `resources/js/Pages/About.tsx` · `Header.tsx` (NAV thêm `{label: 'Giới thiệu', href: '/gioi-thieu'}`) · `Footer.tsx` (link cột "Khám phá") · test trong `StaticPageTest`

- Page: hero ảnh bìa (nếu có) + tiêu đề overlay, thân = `MagazineContent`, cuối trang CTA "Thuê đồ ngay →" `/thiet-bi`.
- Props: `page: {title, cover_url, content}`.
- Test: GET `/gioi-thieu` 200 + component `About` + đủ props (tự seed qua `about()`).

### Task 4: Gates + stg

- Full gates → preview desktop/mobile → merge `develop` push (stg) → user test → merge `feat/scaffold-laravel`.

## Self-review

Spec coverage: URL ✓ · title/ảnh bìa/nội dung template ✓ · admin sửa ✓ · header+footer ✓ · sanitize ✓ · cơ chế static_pages tái dùng ✓. Ngoài phạm vi: CRUD tạo/xoá page (YAGNI, spec đã ghi).
