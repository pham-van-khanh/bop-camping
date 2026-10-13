# Hướng dẫn chủ shop — đợt SEO & Marketing (10/2026)

Nhánh gộp: `release/seo-marketing-2026-10` (chưa deploy). Kế hoạch: `plan_seo_marketing_2026-10.md`.

## Có gì mới

| Mảng | Trang / tính năng |
|---|---|
| Đo chuyển đổi | GA4 + GTM + Meta Pixel + TikTok Pixel nhận sự kiện: xem SP, thêm giỏ, bắt đầu đặt, đặt thành công, bấm liên hệ. Không gửi tên/SĐT/email. Tắt trên /admin, /shipper |
| Nguồn đơn | Mỗi đơn lưu UTM/nguồn (first-touch 30 ngày). Admin: khối "Nguồn" trong chi tiết đơn + bảng "Nguồn đơn — 30 ngày" ở Dashboard |
| Landing khu vực | `/thue-do-cam-trai/nghe-an`, `/thue-do-cam-trai/ha-noi` (theo slug cơ sở trên production: `nghe-an`, `ha-noi`) |
| Điểm cắm trại | `/diem-cam-trai` + mỗi điểm một trang (điểm mô tả < 200 ký tự & không ảnh → tự noindex) |
| Cẩm nang | Admin → Cẩm nang: viết bài, ảnh bìa, gắn SP/combo. Public `/cam-nang`. Có sẵn 8 bài soạn sẵn (bản nháp) nạp bằng 1 lệnh |
| Chính sách bảo mật | Thêm mục "5. Cookie và công cụ phân tích" — tự thêm khi deploy |
| SEO | Sao đánh giá cho combo, title trình duyệt = title server, ảnh có kích thước (giảm CLS), bộ đếm ký tự title trong admin, vá XSS ở JSON-LD |

## Việc cần làm sau khi deploy

### Admin
1. **Cài đặt shop → SEO & theo dõi**: nhập Meta Pixel ID (Events Manager) và TikTok Pixel ID (TikTok Ads Manager → Assets → Events).
2. **Cơ sở Nghệ An / Hà Nội**: điền địa chỉ, link Google Maps, và "Giới thiệu khu vực" ≥ 120 ký tự (quận phục vụ, điểm cắm trại gần, cách nhận đồ).
3. **Điểm cắm trại**: production có 3 điểm — Công viên Yên Sở, Bãi đá sông Hồng Nhật Tân, Thung Trâu. Viết mô tả ≥ 200 ký tự + ảnh cho cả 3 (đường đi, chỗ dựng lều, nước/điện, phí, mùa đẹp); riêng **Thung Trâu** chưa gán "Gần vị trí phục vụ" → gán cơ sở Hà Nội. Sau khi up ảnh: `php artisan media:variants`.
4. **Combo**: gán combo cho từng cơ sở (nuôi trang khu vực + "đồ cần mang").
5. **Cẩm nang**: nạp 8 bài soạn sẵn (xem mục Deploy), rồi vào Admin → Cẩm nang mở từng bài: đọc lại, thêm ảnh bìa, kiểm tra SP/combo liên quan (đã tự gắn theo slug nếu có), rồi chuyển "Đã đăng". Bài nào chưa ưng thì cứ để nháp — nháp không hiện ra trang khách hay sitemap.
   8 bài: điểm cắm trại gần Vinh, điểm cắm trại gần Hà Nội, thuê hay mua đồ, cắm trại cùng trẻ nhỏ, nướng BBQ, checklist cho người mới, chọn lều 2 hay 4 người, cắm trại mùa mưa. Các bài đã có link tới `/thue-do-cam-trai/nghe-an`, `/thue-do-cam-trai/ha-noi`, 3 trang điểm cắm trại và các combo — đừng đổi slug những trang đó.
6. **Tên sản phẩm > 60 ký tự**: production có **7 sản phẩm** tên dài quá 60 ký tự (bị Google cắt title) — trong đó có ghế Nightfall T05, lều Village 6.0, lều cabin Village 13, tăng bạt Naturehike. Rút gọn theo bộ đếm trong form. Chỉ sửa **tên**, giữ nguyên slug (bài Cẩm nang và combo đang link theo slug).
7. **Chính sách bảo mật**: **không cần tự viết** — lúc deploy, migration tự nối mục "5. Cookie và công cụ phân tích" vào cuối trang `/chinh-sach-bao-mat` (giữ nguyên mục 1–4; đã có mục này thì bỏ qua). Mở trang kiểm tra sau deploy. Sau này tắt/bật thêm công cụ đo lường nào thì sửa lại mục 5 trong Admin → Trang nội dung.

### GA4
- Đánh dấu `purchase` (và `generate_lead` nếu muốn) là **key event**.
- Giữ bật Enhanced measurement → "Page changes based on browser history events".
- Không bật đồng thời GA4 trong admin **và** thẻ GA4 trong GTM (đếm đôi page view).

### Meta / TikTok
- Chọn Purchase / CompletePayment và Contact làm sự kiện chuyển đổi cho chiến dịch.

### Gắn UTM cho mọi link quảng cáo / bài đăng
`?utm_source=facebook&utm_medium=cpc&utm_campaign=he-2026` — viết thống nhất (luôn `facebook`, không lúc `fb` lúc `facebook`).

### Đánh giá
- Duyệt đánh giá khách trong admin → sao vàng hiện trên Google cho SP/combo.

## Deploy
- `php artisan migrate` (6 migration mới: 5 migration tính năng — đã thử rollback/migrate lại trên MySQL 8 — và `2026_10_12_000001_add_cookie_section_to_privacy_policy` nối mục cookie vào chính sách bảo mật).
- Nạp bài Cẩm nang (chạy tay, 1 lần; chạy lại cũng không sao):
  `php artisan db:seed --class=PostSeeder --force`
  Chỉ tạo bài **chưa có slug**, luôn là **bản nháp**, không bao giờ ghi đè bài đã có. Nội dung nằm ở `database/seeders/data/cam_nang_posts.json`.
- Queue worker phải chạy (ảnh bìa bài viết / ảnh điểm cắm trại sinh biến thể qua queue).
- Sau deploy: Search Console → gửi lại `sitemap.xml`.

## Giới hạn đã biết
- Chưa có banner đồng ý cookie; chưa có Meta Conversions API (server-side) — adblock sẽ che một phần đơn.
- Giá trị `view_item`/`add_to_cart` là ước tính (giá/ngày × số ngày); `purchase` lấy số tiền thật từ server.

## 10 chủ đề Cẩm nang ưu tiên
Đã có bản nháp (8 bài trong seeder):
1. Checklist đồ cắm trại qua đêm cho người mới
2. Chọn lều 2 người hay 4 người
3. Cắm trại mùa mưa / nồm miền Bắc
4. Top điểm cắm trại gần Vinh
5. Điểm cắm trại gần Hà Nội đi trong ngày / 2N1Đ
6. Thuê hay mua đồ cắm trại? So sánh chi phí
7. Cắm trại cùng trẻ nhỏ
8. Nướng BBQ khi cắm trại: thực đơn 4 người, an toàn lửa

Còn lại, shop tự viết:
9. Cắm trại biển: dựng lều trên cát, chống gió
10. Chọn túi ngủ theo nhiệt độ đêm miền Bắc
