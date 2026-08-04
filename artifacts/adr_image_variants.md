# ADR — Pipeline biến thể ảnh (resize + WebP + srcset)

- **Ngày:** 2026-08-04
- **Trạng thái:** Accepted
- **Issue:** bopcamping-ix4n (kèm bopcamping-slnb — "ảnh sản phẩm mờ")

## Bối cảnh

Khách báo ảnh sản phẩm trên trang chi tiết bị mờ. Đo trực tiếp trên production
(`/thiet-bi/ban-gap-gon-ngoai-troi-naturehike-mau-kaki`):

| | Kích thước |
|---|---|
| Khung ảnh chính (desktop) | 668 × 680 CSS px |
| Màn Retina (DPR 2) → cần | ~1336 × 1360 px thật |
| Thumbnail sản phẩm đó | 578 × 678 px (65 KB) |
| Ảnh gallery | 790 px chiều rộng (58–125 KB) |

→ Browser phóng ảnh lên 1.7–2.3× so với pixel thật.

**Nguyên nhân gốc là ảnh nguồn quá nhỏ**, không phải lỗi render. Nhưng có hai
vấn đề cấu trúc khiến không thể chỉ "upload ảnh to hơn":

1. **Không có xử lý ảnh nào.** `ProductController::store()` gọi `->store()` lưu
   nguyên file upload. Không có `intervention/image` trong `composer.json`.
2. **Không có `srcset` ở đâu** trong `resources/js`. Ô thumbnail 76 × 64 px vẫn
   tải file 790 px — nhân 10 ảnh mỗi trang chi tiết.

Nghĩa là upload ảnh 3000 px thì trang gánh đủ 3000 px cho mọi ô, kể cả ô 76 px.
Chất lượng ảnh và tốc độ trang đang đối đầu nhau.

## Quyết định

Thêm `intervention/image` (v4, driver GD — PHP 8.3 đã có sẵn GD + WebP, không
cần cài extension mới) và sinh biến thể đã resize khi upload.

**Bậc thang chiều rộng: 400 / 800 / 1600**, chọn theo khung hiển thị thực đo:

| Bậc | Dùng cho | Khung CSS | @2x cần |
|-----|----------|-----------|---------|
| 400 | ô thumbnail gallery, ảnh phụ kiện | 76×64, 40×40 | ~152 px |
| 800 | thẻ sản phẩm ở lưới | 293×240 | ~586 px |
| 1600 | ảnh chính trang chi tiết | 668×680 | ~1336 px |

Quy tắc:

- **Không bao giờ phóng to.** Chỉ sinh bậc nhỏ hơn ảnh gốc, cộng một bậc bằng
  đúng chiều rộng gốc (cap ở 1600) để ảnh nhỏ vẫn có ít nhất một bản WebP.
- **File gốc không bị sửa/xoá** — giữ làm dự phòng nếu sau này cần bậc lớn hơn.
- **File gốc không bao giờ được serve.** `src` luôn trỏ vào biến thể lớn nhất,
  nên browser không hỗ trợ `srcset` cũng không tải file 5 MB.
- **WebP quality 82.**

### Bảng `media_variants` — key theo `source_path`, KHÔNG theo `product_image_id`

Một file ảnh được **chia sẻ** giữa nhiều row `product_images` / `combo_images`
(xem `app/Support/MediaRef.php` — admin có thể tái sử dụng ảnh, nhiều row trỏ
cùng `path`). Nếu key theo id row thì cùng một file sẽ bị resize lặp và các bản
có thể lệch nhau. Key theo file là single source đúng.

### Chạy qua queue

`GenerateMediaVariants` là `ShouldQueue`. Resize 12 ảnh 4000 px đồng bộ sẽ làm
admin chờ ~15 s và dễ đụng `max_execution_time`. **Cần queue worker đang chạy** —
`composer run dev` đã bật sẵn, prod đã có worker cho mail.

### Fallback khi chưa có biến thể

`MediaVariantService::payload()` trả `srcset = null` và `url` = file gốc. Giao
diện chạy y như trước. Nghĩa là:

- ảnh cũ chưa backfill vẫn hiển thị bình thường,
- không có queue worker thì mất tối ưu, **không** vỡ trang,
- ảnh lỗi/không decode được chỉ ghi log, không làm vỡ luồng upload của admin.

### Chống N+1

`payload()` đọc từ memo trong phạm vi một request; controller gọi
`MediaVariantService::warm($paths)` một lần trước khi shape danh sách → **1 query**
cho toàn bộ ảnh của trang. Có test khoá hành vi này (15 ảnh → 1 query).

## Phương án đã cân nhắc và bỏ

| Phương án | Vì sao bỏ |
|-----------|-----------|
| Normalize ảnh gốc về ≤1600 px lúc upload (ghi đè) | Mất bản gốc, không lấy lại được nếu sau này cần bậc lớn hơn |
| Đặt tên biến thể theo quy ước, không cần bảng | Không biết bậc nào tồn tại → `srcset` trỏ vào file 404, hoặc phải probe S3 mỗi lần render (đắt) |
| Thêm cột `variants` JSON vào `product_images` | Ảnh chia sẻ giữa nhiều row → dữ liệu trùng lặp và dễ lệch |
| Resize đồng bộ trong request | 12 ảnh × ~1 s = admin chờ 15 s, dễ timeout |
| Resize on-the-fly qua route ảnh | Thêm điểm chịu tải và cache phức tạp; media đang ở S3 tĩnh, không cần |
| CDN tự resize (CloudFront + Lambda@Edge) | Thêm hạ tầng và chi phí cho một shop; để dành nếu về sau cần |

## Hệ quả

**Tích cực**

- Từ giờ **upload ảnh gốc ≥1600 px là việc nên làm** — trang không nặng thêm vì
  mỗi ô chỉ tải đúng bậc nó cần.
- Ô thumbnail tải bậc 400 thay vì file full → trang chi tiết nhẹ đi rõ rệt.
- WebP nhỏ hơn JPEG/PNG cùng chất lượng.

**Chi phí / rủi ro**

- Mỗi ảnh chiếm thêm 2–3 file trên S3 (gốc vẫn giữ) → dung lượng lưu trữ tăng,
  nhưng băng thông giảm.
- Phụ thuộc queue worker cho phần tối ưu (đã có fallback an toàn).
- Bậc thang gắn với kích thước khung hiện tại. **Đổi layout ảnh thì phải xem lại
  `MediaVariantService::WIDTHS`** và chạy lại backfill.

## Việc phải làm khi deploy

```bash
php artisan migrate
php artisan media:variants          # backfill ảnh cũ (idempotent)
php artisan media:variants --dry-run  # chỉ đếm, không resize
```

Backfill xong thì **upload lại ảnh sản phẩm với bản gốc ≥1600 px** — đây mới là
thứ thật sự hết mờ. Pipeline chỉ làm việc đó trở nên khả thi.

## Liên quan

- `artifacts/adr_s3_media_storage.md` — disk `media` (local dev / S3 prod)
- `resources/js/lib/imageFit.ts` (bopcamping-slnb) — giảm phóng to cho ảnh nhỏ
  hiện có; tự vô hiệu hoá khi ảnh đã đủ lớn
