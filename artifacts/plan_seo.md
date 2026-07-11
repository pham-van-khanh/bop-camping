# Plan — Epic 5: SEO cả gói

**Goal:** Hoàn thiện SEO: SeoService tập trung + phủ meta cho trang chưa set (chủ, danh sách, danh mục, giới thiệu), GA4 + Google verification **nhập từ admin** (không hard-code), thêm LocalBusiness + BreadcrumbList JSON-LD.

**Bối cảnh:** `app.blade.php` ĐÃ có: title/desc/image/canonical động + fallback, OG, Twitter, Organization/WebSite/FAQPage JSON-LD, per-page `seo.jsonld` hook, GTM (env `services.gtm.id`), FB domain verification (env). Phần này BỔ SUNG, không đập đi làm lại.

**Spec gốc:** `artifacts/design_spec_product_page_v2_feedback_seo.md` (Epic 5). Branch `feature/seo`.

---

### Task 1: GA4 + Google Site Verification nhập từ admin

**Files:** migration thêm cột `site_settings` · `SiteSettingController` (validate + share) · `HandleInertiaRequests` (share vào `seo` hoặc prop mới) · `app.blade.php` (render gtag + meta verification khi có giá trị) · `Admin/SiteSettings.tsx` (2 field) · test `SeoTest`

- Migration: `site_settings` thêm `ga_measurement_id` (string 40 nullable), `google_site_verification` (string 120 nullable).
- `SiteSettingController@update` validate 2 field (`nullable|string|max:...`, ga khớp `/^G-[A-Z0-9]+$/i` optional → dùng regex nhẹ hoặc chỉ max). `edit` đã trả `SiteSetting::current()` (guarded=[]) nên FE tự có.
- Share xuống blade: `HandleInertiaRequests` thêm vào mảng `seo` share: `ga_id`, `google_verification` (đọc `SiteSetting::current()` — đã có `sharedSite`, thêm lazy `sharedSeo` gộp url + 2 field). Blade đọc `$seo['ga_id']` / `$seo['google_verification']`.
- Blade: `@if($seo['google_verification'])<meta name="google-site-verification" ...>@endif`; `@if($seo['ga_id'])` chèn gtag.js (`https://www.googletagmanager.com/gtag/js?id={ga}` + init). Đặt cạnh block GTM.
- FE: thêm mục "SEO & Theo dõi" trong SiteSettings.tsx với 2 field (ga_measurement_id, google_site_verification) + hint (lấy ở GA4 Admin / Search Console).
- Test: settings có ga_id → response `/` chứa `gtag/js?id=G-...`; không có → không chứa; verification tương tự.

### Task 2: SeoService + phủ trang thiếu + LocalBusiness/Breadcrumb

**Files:** `app/Services/SeoService.php` · `Shop/ProductController@home,@index` · `Shop/StaticPageController@about` · blade (LocalBusiness JSON-LD site-wide) · test

- `SeoService`:
  - `page(title, description, ?image, ?url, ?jsonld): array` — chuẩn hoá 1 mảng seo (limit desc ~160, strip tags, absolute image url, url mặc định current).
  - `breadcrumb(array $items): array` — BreadcrumbList JSON-LD từ `[[name,url],...]`.
  - `localBusiness(): array` — từ `SiteSetting::current()` (tên, hotline, giờ mở cửa, areaServed Vinh/Hà Nội, url, logo).
- `home()`: set `seo` = page(title chủ đích, desc site) — hiện đang để fallback; đặt tường minh + jsonld có thể để trống (Organization đã ở blade).
- `index()` (`/thiet-bi`): title/desc theo bộ lọc — có `?cat=` thì "Thuê {tên danh mục} tại BỐP CAMPING"; kèm breadcrumb Trang chủ → Thuê đồ (→ danh mục). jsonld = breadcrumb.
- `about()`: seo từ title trang tĩnh + desc strip từ content.
- ProductController@show: bổ sung breadcrumb (Trang chủ → Thuê đồ → {tên SP}) — gộp cùng Product jsonld thành mảng `@graph` hoặc trả `jsonld` là array-of-nodes (blade json_encode nguyên mảng — kiểm tra blade render mảng lồng: hiện `@if(!empty($seo['jsonld']))` encode 1 object; nâng để nếu là list thì bọc `@graph`). → chuẩn hoá: `SeoService::graph([...nodes])`.
- Blade: thêm LocalBusiness JSON-LD site-wide (đọc share `seo.local_business`), chỉ render khi có hotline.
- Test: `/thiet-bi?cat=leu` title chứa tên danh mục + breadcrumb; product page có BreadcrumbList; about có title đúng.

### Task 3: Gates + stg

- `php artisan test` · pint · tsc · build → preview kiểm tra `/` view-source có gtag khi set GA (seed tạm) → merge develop → stg → chính.

## Self-review

Spec coverage: title/desc động mọi trang ✓ (home/listing/about bổ sung, product/combo đã có) · OG/Twitter ✓ (blade sẵn) · canonical ✓ · JSON-LD Product ✓ / Organization ✓ / Breadcrumb ✓ (T2) / LocalBusiness ✓ (T2) / FAQ ✓ · GA4 + GSC từ admin ✓ (T1) · tự sinh desc từ tên+mô tả ✓. GTM env giữ nguyên (không xung đột GA4 admin — độc lập, gate riêng).
