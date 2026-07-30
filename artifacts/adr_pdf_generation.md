# ADR — Sinh PDF trên server (lịch giao)

**Ngày:** 2026-07-29 · **Trạng thái:** ❌ **Rejected (29/07/2026)** · **Reversibility:** Two-Way Door

> **Không áp dụng.** Chủ shop bỏ hẳn tính năng in / PDF / CSV lịch giao ngay trong ngày (29/07),
> trước khi code. Package `barryvdh/laravel-dompdf` đã được cài thử rồi **gỡ lại**; `composer.json`
> và golden path trong `tech-strategy.md` KHÔNG còn dòng nào về PDF. Giữ ADR này để nếu sau
> này cần in lịch thì không phải khảo sát lại — kết luận vẫn là dompdf, lý do ở mục 3.

## 1. Bối cảnh

Chủ shop yêu cầu **in / PDF / CSV** lịch giao, trong đó có **PDF tải trực tiếp từ server** (để gửi file cho shipper qua Zalo/email chứ không bắt họ tự bấm in). `tech-strategy.md` là nguồn chân lý về công nghệ và **chưa có** thư viện PDF nào → phải quyết định và ghi lại.

## 2. Các phương án

| # | Phương án | Ưu | Nhược |
|---|---|---|---|
| A | **`barryvdh/laravel-dompdf`** (wrapper dompdf) | Thuần PHP, không cần binary/Chromium trên VPS; render trực tiếp từ Blade; cài 1 lệnh composer; đủ cho bảng chữ + khung | CSS hạn chế (không flex/grid), phải viết Blade riêng bằng table + inline style; cần nhúng font hỗ trợ tiếng Việt |
| B | `spatie/laravel-pdf` / Browsershot (Puppeteer) | Render đúng như trình duyệt, dùng lại được CSS Tailwind | Cần Node + Chromium **trên server prod** — nặng, dễ vỡ khi deploy VPS thủ công, tốn RAM |
| C | `barryvdh/laravel-snappy` (wkhtmltopdf) | CSS tốt hơn dompdf | wkhtmltopdf đã ngừng phát triển, phải cài binary hệ thống |
| D | Không sinh PDF — chỉ trang in + "Lưu thành PDF" trong hộp thoại in | Không dependency | Không có file để gửi qua Zalo/email — đúng thứ chủ shop cần |

## 3. Quyết định

**Chọn A — `barryvdh/laravel-dompdf`.**

- Lý do quyết định: prod là **VPS Linux + Nginx + PHP-FPM** dựng thủ công ([tech-strategy.md](.claude/rules/tech-strategy.md) mục Deployment). Yêu cầu Chromium (B) làm quy trình deploy phức tạp hẳn lên và dễ chết vì thiếu RAM/thiếu thư viện hệ thống. dompdf chỉ là code PHP → `composer install` là chạy.
- **Tiếng Việt**: dompdf mặc định không có font Việt đầy đủ → nhúng **DejaVu Sans** (đi kèm dompdf, có dấu tiếng Việt) và set `defaultFont`. Phải có test khẳng định PDF chứa chữ có dấu, vì lỗi font là lỗi im lặng (ra ô vuông).
- **Blade PDF viết riêng** (`resources/views/pdf/delivery_schedule.blade.php`), dùng `<table>` + inline style — KHÔNG dùng lại component Tailwind của trang web.
- Trang in HTML (CSS `@media print`) **vẫn làm** vì rẻ và tiện hơn khi in tại chỗ; PDF là để có file gửi đi. Hai đường này dùng chung 1 nguồn dữ liệu (service), không lặp query.

## 4. Hệ quả

- Thêm 1 dependency PHP; cập nhật bảng golden path trong `.claude/rules/tech-strategy.md` (đã làm cùng đợt này).
- File PDF **không lưu xuống disk** — stream trả về ngay (`->download()`), tránh rác trong `storage` và tránh file chứa dữ liệu khách nằm lại trên server.
- PDF/CSV/trang in đều nằm sau middleware `admin` (shipper không cần tự export — họ xem trên app).
- Nếu về sau cần layout phức tạp (logo, watermark, nhiều cột), xem lại phương án B; đổi được vì chỗ gọi đã bọc trong 1 service.

## 5. Liên quan

- [prd_shipper_delivery_ops.md](artifacts/prd_shipper_delivery_ops.md) FR-5 (in/PDF/CSV).
- `.claude/rules/tech-strategy.md` — bảng golden path đã thêm dòng PDF.
