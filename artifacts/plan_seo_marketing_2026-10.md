# Plan — Đẩy mạnh SEO & Marketing (10/10/2026)

**Bối cảnh:** SEO kỹ thuật đã vững sau 2 đợt audit (`seo_audit_2026-08-11.md`,
`seo_audit_2026-08-13.md`): meta/OG/canonical riêng từng trang, sitemap, robots, JSON-LD
(Organization, WebSite, FAQPage, LocalBusiness ×2, Product, Breadcrumb), GA4 bật từ admin.
Đo production 10/10/2026 thấy hổng ở **đo lường chuyển đổi** và **nội dung nhắm truy vấn
thật** của khách.

Mỗi luồng = 1 feature branch tách từ `feat/scaffold-laravel`, có test, **không deploy**
tới khi chủ shop bảo.

| # | Bead | Branch | Việc |
|---|---|---|---|
| 1 | bopcamping-wi6h | `feature/marketing-tracking` | GA4 e-commerce events (view_item, add_to_cart, begin_checkout, purchase) + Meta Pixel + TikTok Pixel, ID nhập từ admin; lưu nguồn đơn (UTM/referrer) vào order, xem trong admin |
| 2 | bopcamping-y05c | `feature/seo-landing-pages` | Trang khu vực `/thue-do-cam-trai/{khu-vuc}` + trang điểm cắm trại `/diem-cam-trai/{slug}`, sitemap, schema, liên kết nội bộ |
| 3 | bopcamping-ennp | `feature/cam-nang` | Blog "Cẩm nang": model + admin CRUD + `/cam-nang` + `Article`/Breadcrumb schema + sitemap + gợi ý sản phẩm |
| 4 | bopcamping-b4c3 | `feature/seo-polish` | alt + width/height ảnh trang chủ (CLS), bộ đếm ký tự tiêu đề SEO trong admin, xác minh & đóng bopcamping-h0hh |

## Nguyên tắc chung

- Theo `.claude/skills/seo/SKILL.md`: SEO đi qua `SeoService`, JSON-LD chỉ khai thứ hiện
  trên trang, trang public mới phải vào `SitemapController`, có test cả chiều "có" lẫn "không".
- Tracking: không gửi PII (SĐT, email, tên) cho nền tảng quảng cáo; script chỉ chèn khi
  admin điền ID; trang admin không bắn pixel.
- Quality gates: `php artisan test` · `npm test` · `npx tsc --noEmit` · `npm run lint` ·
  `./vendor/bin/pint --test` · `npm run build`.

## Xung đột dự kiến khi gộp

Luồng 2 và 3 cùng sửa `routes/web.php`, `SitemapController`, nav admin. Luồng 1 và 4 có
thể cùng sửa `SiteSettings.tsx`. Mỗi luồng thêm code thành khối riêng có comment để lúc
merge dễ giải quyết.
