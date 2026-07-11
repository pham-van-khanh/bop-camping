# Plan — Epic 3: Sitemap động + robots.txt

**Goal:** `GET /sitemap.xml` sinh động từ DB (cache 1h) + `public/robots.txt` chặn khu vực riêng tư, trỏ sitemap.

**Spec gốc:** `artifacts/design_spec_product_page_v2_feedback_seo.md` (Epic 3). Branch `feature/sitemap-robots`.

### Task 1: SitemapController + route + test

- `app/Http/Controllers/Shop/SitemapController.php@index` → XML `urlset`:
  - `/` (daily, 1.0) · `/thiet-bi` (daily, 0.9) · `/combos` (weekly, 0.8) · `/gioi-thieu` (monthly, 0.6)
  - Từng danh mục: `/thiet-bi?cat={slug}` (weekly, 0.7)
  - Từng sản phẩm `active`: `/thiet-bi/{slug}` + `lastmod=updated_at` (weekly, 0.8)
  - Từng combo active: `/combos/{slug}` + lastmod (weekly, 0.7)
- `Cache::remember('sitemap.xml', 3600)`; escape URL bằng `htmlspecialchars`; Content-Type `application/xml`.
- Route `GET /sitemap.xml` name `sitemap` (không throttle — bot Google).
- Test `SitemapTest`: 200 + content-type xml; chứa sản phẩm active + `/gioi-thieu`; KHÔNG chứa sản phẩm hidden; cache flush khi test (Cache::flush trong setUp hoặc key theo thời gian test).

### Task 2: robots.txt

- Ghi đè `public/robots.txt` (hiện đang Allow all mặc định):
  - Disallow: `/admin`, `/tra-cuu`, `/tai-khoan`, `/gio-thue`, `/danh-gia`, `/dang-nhap`, `/profile`, `/dashboard`
  - `Sitemap: https://bopcamping.com/sitemap.xml` (domain production).
- Test: file tồn tại (skip — file tĩnh, review tay).

### Gates

`php artisan test` · pint · build (không đổi FE — tsc/build vẫn chạy cho chắc) → merge develop → stg → merge chính.
