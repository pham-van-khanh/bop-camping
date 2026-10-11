# Hướng dẫn chủ shop — đợt SEO & Marketing (10/2026)

Nhánh gộp: `release/seo-marketing-2026-10` (chưa deploy). Kế hoạch: `plan_seo_marketing_2026-10.md`.

## Có gì mới

| Mảng | Trang / tính năng |
|---|---|
| Đo chuyển đổi | GA4 + GTM + Meta Pixel + TikTok Pixel nhận sự kiện: xem SP, thêm giỏ, bắt đầu đặt, đặt thành công, bấm liên hệ. Không gửi tên/SĐT/email. Tắt trên /admin, /shipper |
| Nguồn đơn | Mỗi đơn lưu UTM/nguồn (first-touch 30 ngày). Admin: khối "Nguồn" trong chi tiết đơn + bảng "Nguồn đơn — 30 ngày" ở Dashboard |
| Landing khu vực | `/thue-do-cam-trai/vinh`, `/thue-do-cam-trai/ha-noi` |
| Điểm cắm trại | `/diem-cam-trai` + mỗi điểm một trang (điểm mô tả < 200 ký tự & không ảnh → tự noindex) |
| Cẩm nang | Admin → Cẩm nang: viết bài, ảnh bìa, gắn SP/combo. Public `/cam-nang` |
| SEO | Sao đánh giá cho combo, title trình duyệt = title server, ảnh có kích thước (giảm CLS), bộ đếm ký tự title trong admin, vá XSS ở JSON-LD |

## Việc cần làm sau khi deploy

### Admin
1. **Cài đặt shop → SEO & theo dõi**: nhập Meta Pixel ID (Events Manager) và TikTok Pixel ID (TikTok Ads Manager → Assets → Events).
2. **Cơ sở Vinh / Hà Nội**: điền địa chỉ, link Google Maps, và "Giới thiệu khu vực" ≥ 120 ký tự (quận phục vụ, điểm cắm trại gần, cách nhận đồ).
3. **Điểm cắm trại**: viết mô tả ≥ 200 ký tự + ảnh (đường đi, chỗ dựng lều, nước/điện, phí, mùa đẹp); gán "cơ sở gần nhất" cho 5 điểm đang trống (Đồng Cao, Bàu Trắng, Tà Đùng, Chứa Chan, Dầu Tiếng). Sau khi up ảnh: `php artisan media:variants`.
4. **Combo**: gán combo cho từng cơ sở (nuôi trang khu vực + "đồ cần mang").
5. **Cẩm nang**: 3 bài nháp có sẵn ở dev seeder; trên production viết mới. Gợi ý 10 chủ đề ở cuối file.
6. **Tên sản phẩm > 60 ký tự**: rút gọn theo bộ đếm trong form (4 SP đã biết: ghế Nightfall T05, lều Village 6.0, lều cabin Village 13, tăng bạt Naturehike).
7. **Chính sách bảo mật**: thêm đoạn về cookie & công cụ phân tích (GA4, Meta Pixel, TikTok Pixel, cookie nguồn đơn 30 ngày).

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
- `php artisan migrate` (5 migration mới — đã thử rollback/migrate lại trên MySQL 8).
- Queue worker phải chạy (ảnh bìa bài viết / ảnh điểm cắm trại sinh biến thể qua queue).
- Sau deploy: Search Console → gửi lại `sitemap.xml`.

## Giới hạn đã biết
- Chưa có banner đồng ý cookie; chưa có Meta Conversions API (server-side) — adblock sẽ che một phần đơn.
- Giá trị `view_item`/`add_to_cart` là ước tính (giá/ngày × số ngày); `purchase` lấy số tiền thật từ server.

## 10 chủ đề Cẩm nang ưu tiên
1. Checklist đồ cắm trại qua đêm cho người mới
2. Chọn lều 2 người hay 4 người
3. Cắm trại mùa mưa / nồm miền Bắc
4. Top điểm cắm trại gần Vinh
5. Điểm cắm trại gần Hà Nội đi trong ngày / 2N1Đ
6. Thuê hay mua đồ cắm trại? So sánh chi phí
7. Cắm trại biển: dựng lều trên cát, chống gió
8. Cắm trại cùng trẻ nhỏ
9. Nấu ăn khi cắm trại: thực đơn 2N1Đ
10. Chọn túi ngủ theo nhiệt độ đêm miền Bắc
