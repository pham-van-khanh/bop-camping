# Design Spec — Trang sản phẩm v2 · Góp ý · Sitemap/Robots · Trang giới thiệu · SEO

- **Ngày:** 2026-07-09
- **Trạng thái:** Đã được user duyệt thiết kế (qua hội thoại), chờ review spec
- **Phạm vi:** 5 epic độc lập, triển khai theo thứ tự **1 → 4 → 2 → 3 → 5** (Epic 1 và 4 dùng chung TipTap; sitemap + SEO làm cuối để quét đủ trang mới)
- **Quy trình git:** mỗi epic 1 feature branch từ `feat/scaffold-laravel` → merge `develop` (stg test) → test OK → merge feature branch vào `feat/scaffold-laravel`

---

## Nền tảng dùng chung: Rich text editor (TipTap)

- Cài `@tiptap/react`, `@tiptap/starter-kit`, `@tiptap/extension-image`.
- Component dùng lại: `resources/js/Components/admin/RichTextEditor.tsx` — toolbar cơ bản (heading, bold/italic, list, link, image, undo/redo).
- **Xuất HTML.** Chèn ảnh: upload qua endpoint admin `POST /admin/editor/images` → lưu disk `media` (thư mục `editor/`) → trả URL → editor chèn `<img>`.
- **Sanitize server-side** mọi HTML từ editor trước khi lưu (HTMLPurifier — package `mews/purifier`): whitelist thẻ văn bản + `img`, chặn script/iframe/event-handler (chống XSS, CWE-79).
- Hiển thị phía khách: render HTML đã sanitize trong container có class typography riêng (style ảnh xen kẽ text, responsive).

---

## Epic 1 — Trang chi tiết sản phẩm `/thiet-bi/{slug}` v2

### 1.1 Bố cục mới

Desktop:

```
[← Quay lại danh sách]
┌──────┬────────────────────┬──────────────────┐
│thumbs│                    │  Tên, giá, cọc   │
│ dọc  │    Ảnh chính       │  Chọn ngày (thu  │
│ ▢    │  (object-contain,  │  gọn, bấm sổ ra) │
│ ▢    │   không crop,      │  Mô tả ngắn      │
│ ▢    │   nút ‹ › )        │  [Xem thêm ↓]    │
│ ▢    │                    │  Thêm vào giỏ    │
├──────┴────────────────────┤                  │
│  THÔNG SỐ (key–value)     │                  │
└───────────────────────────┴──────────────────┘
│  Khối SETUP / mô tả lớn (rich text)          │ ← "Xem thêm" cuộn tới đây
│  Thường thuê cùng (giữ nguyên)               │
│  Đánh giá (chuyển xuống đây)                 │
│  You may also like                           │
```

- Thumbnails: cột dọc bên trái ngoài cùng, cạnh ảnh chính (tham khảo ảnh mẫu user gửi 09/07). Cột phải (giá, lịch, giỏ) không đổi vị trí.
- **Mobile:** thumbnails chuyển thành hàng ngang dưới ảnh chính; các khối xếp dọc theo thứ tự: ảnh → thông tin/giá → thông số → setup → thường thuê cùng → đánh giá → you may also like.

### 1.2 Thông số kỹ thuật (key–value)

- Cột mới `products.specs` — JSON, mảng `[{key: string, value: string}]`, nullable.
- Admin (form sản phẩm hiện có): bảng nhập động — thêm dòng / xoá dòng / sắp thứ tự; key và value đều text tự do.
- Khách: card "THÔNG SỐ" dưới khối ảnh, mỗi dòng key trái – value phải (như ảnh mẫu). Không có specs → ẩn card.

### 1.3 Mô tả ngắn + nút "Xem thêm"

- `products.description` (plain text, giữ nguyên) = **mô tả ngắn**, hiển thị ở cột phải.
- Nút "Xem thêm" dưới mô tả ngắn → smooth-scroll tới khối setup (1.4).
- Sản phẩm không có `setup_content` → ẩn nút "Xem thêm".

### 1.4 Khối setup / mô tả lớn (rich text)

- Cột mới `products.setup_content` — LONGTEXT nullable, HTML TipTap đã sanitize.
- **Màn admin riêng** `/admin/products/{id}/noi-dung`: editor full-width + nút Lưu, breadcrumb quay về danh sách sản phẩm. Danh sách sản phẩm admin thêm nút "Nội dung" mỗi dòng.
- Khách: section rộng dưới khối ảnh+thông tin, text + ảnh xen kẽ theo nội dung admin soạn. Không có nội dung → ẩn section.

### 1.5 Đánh giá xuống cuối

- Chuyển `ProductReviews` xuống dưới "Thường thuê cùng", trên "You may also like". Logic/component giữ nguyên.

### 1.6 You may also like (admin chọn)

- Bảng pivot mới `product_related` (`product_id`, `related_product_id`, `sort_order`) — mô hình giống `product_accessories`.
- Admin: picker chọn nhiều sản phẩm trong form sản phẩm (search → click, thứ tự click = thứ tự hiển thị), tối đa 12, không cho chọn chính nó.
- Khách: section "Có thể bạn cũng thích" dưới cùng, card sản phẩm dạng grid (tái dùng card ở trang danh sách), chỉ hiện sản phẩm `active`. Không có → ẩn section.

### 1.7 Lịch thu gọn

- Giữ nguyên `DateRangeCalendar`; bọc trong khối collapse:
  - Mặc định: ô "📅 Chọn ngày thuê" (hiện khoảng ngày đã chọn nếu có) — bấm để sổ lịch.
  - Chọn xong ngày kết thúc → tự thu gọn lại, ô hiển thị khoảng ngày đã chọn.
- Hành vi fetch tồn kho theo khoảng ngày giữ nguyên.

### 1.8 Ảnh chính next/prev + không crop

- Ảnh chính: thêm nút ‹ › chuyển ảnh (sync với thumbnail đang active); dùng `object-contain` trên nền nhạt để hiện trọn sản phẩm, không bị cắt/thu nhỏ.
- Lightbox giữ nguyên (đã hiển thị full).

### Backend

- `Shop/ProductController@show` bổ sung props: `specs`, `setup_content` (HTML), `related_products`.
- `Admin/ProductController` validate: `specs` array các item `{key: required|string|max:100, value: required|string|max:500}`; `related_ids` array max 12, exists, khác chính nó.
- Controller/route mới cho màn soạn nội dung: `Admin/ProductContentController@edit/@update`.
- Endpoint upload ảnh editor: `Admin/EditorImageController@store` (jpg/jpeg/png/webp, max 4MB).

### Test

- Feature test: lưu/validate specs, related không chứa chính nó, sanitize HTML (script bị loại), props trang show đủ trường, ẩn related khi sản phẩm hidden.

---

## Epic 2 — Chức năng góp ý

### Phía khách

- **Widget nổi** góc phải-dưới mọi trang shop (cùng cụm nút to-top): bấm → **modal form góp ý**.
- Form: Tên (bắt buộc) · SĐT · Email · Nội dung (bắt buộc). **Validate: phải có ít nhất 1 trong 2 (SĐT hoặc email).**
- Rate-limit chống spam (throttle theo IP). Gửi xong hiện thông báo cảm ơn.

### Dữ liệu

Bảng `feedbacks`:

| Cột | Kiểu |
|---|---|
| id | PK |
| name | string |
| phone | string(20) nullable |
| email | string nullable |
| content | text |
| status | enum(`new`, `replied`) default `new` |
| reply_content | text nullable |
| replied_at | timestamp nullable |
| created_at / updated_at | timestamps |

### Mail

- `FeedbackReceivedMail` (ShouldQueue) → gửi tới **email admin** (`MAIL_ADMIN_ADDRESS` trong `.env`), nội dung: thông tin khách + góp ý + link màn admin.
- `FeedbackReplyMail` (ShouldQueue) → gửi tới khách, **từ mailer thứ 2** cấu hình `.env` (`MAIL_REPLY_HOST/PORT/USERNAME/PASSWORD/FROM_ADDRESS/FROM_NAME` — fallback dùng mailer mặc định nếu chưa khai). Địa chỉ cụ thể user quyết sau — chỉ đổi env, không đổi code.

### Admin `/admin/gop-y`

- Danh sách góp ý (lọc trạng thái new/replied, phân trang), badge đếm góp ý mới ở sidebar.
- Bấm 1 góp ý → panel chi tiết + form phản hồi:
  - **Template cố định** đổ sẵn: chào theo tên khách + đoạn cảm ơn + `[ô nội dung admin tự soạn]` + chữ ký shop.
  - Có email → nút "Gửi email phản hồi" (lưu `reply_content`, gửi mail, set `replied`).
  - Chỉ có SĐT → nút gửi mail disable kèm ghi chú "liên hệ qua SĐT/Zalo", vẫn có nút "Đánh dấu đã phản hồi" (lưu ghi chú phản hồi).

### Test

- Validate ít-nhất-1-trong-2, mail admin được queue khi gửi góp ý, mail phản hồi queue + status chuyển `replied`, throttle.

---

## Epic 3 — Sitemap & robots

- **`GET /sitemap.xml`** — controller sinh động từ DB, cache 1 giờ:
  - Trang chủ, `/thiet-bi` (danh sách), từng danh mục, từng sản phẩm `active` (lastmod = `updated_at`), từng combo active, `/gioi-thieu`.
- **`public/robots.txt`** (file tĩnh):

  ```
  User-agent: *
  Disallow: /admin
  Disallow: /tra-cuu
  Disallow: /tai-khoan
  Disallow: /gio-hang
  Disallow: /thanh-toan
  Sitemap: {APP_URL}/sitemap.xml
  ```

  (Sitemap URL ghi domain production khi deploy; xác nhận file robots.txt hiện tại của Laravel trước khi ghi đè.)
- Test: sitemap trả XML hợp lệ, chứa sản phẩm active, không chứa sản phẩm hidden.

---

## Epic 4 — Trang giới thiệu `/gioi-thieu`

- Bảng mới **`static_pages`**: `id, slug (unique), title, cover_path (nullable), content (LONGTEXT HTML), updated_at/created_at` — cơ chế dùng lại cho các trang tĩnh tương lai (chính sách thuê, hướng dẫn…).
- Seed 1 record `gioi-thieu` với **template nội dung mẫu**: câu chuyện shop, cam kết (đồ sạch – đủ – chuẩn), khu vực phục vụ, CTA thuê đồ — user vào admin sửa lại.
- **Admin** mục "Trang nội dung": danh sách static pages → sửa title, upload ảnh bìa, soạn content bằng TipTap. (Chưa cần nút tạo/xoá page ở GĐ này — YAGNI; thêm khi có nhu cầu trang thứ 2.)
- **Khách** `GET /gioi-thieu`: hero ảnh bìa + tiêu đề, thân trang render HTML, tông be/đất theo theme.
- Link "Giới thiệu" thêm vào **header menu + footer**.
- Test: trang render, admin update được, sanitize HTML.

---

## Epic 5 — SEO cả gói

### Meta tags

- **`app/Services/SeoService.php`** — nguồn chân lý sinh meta cho từng loại trang; controller gọi và truyền prop `seo` (mở rộng prop `seo` đã có ở trang sản phẩm ra toàn site).
- Render bằng `<Head>` của Inertia + biến blade cho SSR-safe phần OG:
  - `<title>` + meta description **tự sinh** từ tên + mô tả (strip tag, cắt ~160 ký tự).
  - Canonical URL.
  - Open Graph + Twitter card (og:title, og:description, og:image = thumbnail/ảnh bìa, og:type) — share Zalo/FB có ảnh + tiêu đề.

### Structured data (JSON-LD)

- `Product` (tên, ảnh, giá thuê/ngày, aggregateRating từ review đã duyệt) — trang sản phẩm.
- `Organization` + `LocalBusiness` (tên shop, hotline, giờ làm việc từ site settings) — toàn site.
- `BreadcrumbList` — trang danh mục/sản phẩm.
- `FAQPage` — FAQ trang chủ.

### Analytics & verification

- Thêm 2 field vào **admin Cài đặt shop** (`site_settings`): `ga_measurement_id`, `google_site_verification`.
- Layout blade: chèn gtag script + meta verification **chỉ khi có giá trị** — không hard-code.

### Skill hỗ trợ khi triển khai/audit

`marketing-skills:` `schema-markup`, `seo-audit` (audit sau triển khai), `site-architecture`, `analytics-tracking`, tuỳ chọn `aeo`.

### Test

- SeoService sinh đúng title/description/canonical cho các loại trang; JSON-LD Product chứa giá + rating; trang không có GA ID thì không render gtag.

---

## Ngoài phạm vi

- Chọn địa chỉ email phản hồi cụ thể (user quyết sau — chỉ sửa `.env`).
- CRUD tạo/xoá static page trong admin (chỉ sửa page có sẵn).
- Tối ưu tốc độ/Core Web Vitals chuyên sâu (có thể audit sau bằng skill `seo-audit`).

## Quality gates (mỗi epic trước khi merge)

`php artisan test` · `./vendor/bin/pint --test` · `npx tsc --noEmit` · `npm run build`
