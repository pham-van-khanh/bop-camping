# Design Spec — Hợp đồng thuê điện tử + chữ ký tay online

**Ngày:** 2026-08-16 · **ADR:** [adr_contract_esignature.md](adr_contract_esignature.md)

## 1. Mục tiêu

Bỏ hẳn việc in hợp đồng ra giấy. Khách đọc và ký hợp đồng qua **một link duy nhất**, gửi qua
Zalo hoặc email, mở trên máy nào cũng ký được.

**Không làm trong phạm vi này:** chữ ký số của CA Việt Nam; tích hợp DocuSign hay nhà cung cấp
eContract; webhook đối soát ngân hàng; hợp đồng cho đơn cha/con nhiều chặng (đơn cha không có
hợp đồng riêng — xem 3.1).

## 2. Luồng nghiệp vụ

1. Admin xác nhận đơn (`pending → confirmed`) → `OrderObserver` tạo bản hợp đồng nháp với đúng ngày thuê, danh sách đồ, tổng tiền, tiền cọc đã chốt.
2. Trang chi tiết đơn của admin hiện nút **Sao chép link hợp đồng** → dán vào Zalo cho khách. Song song, mail xác nhận đơn (`OrderStatusMail` cho trạng thái `confirmed`) đính kèm link đó.
3. Khách mở link trên máy bất kỳ → **nhập 4 số cuối SĐT** để mở → đọc hợp đồng → điền CCCD + tick đồng ý → vẽ chữ ký → bấm Ký.
4. Ký xong: nội dung đóng băng, sinh PDF lưu vào disk `media`, gửi mail kèm PDF cho khách và cho shop. Link chuyển sang **chỉ đọc** — mở lại xem/tải được, không ký lại được.
5. Chưa ký: admin và shipper thấy nhãn đỏ **"Chưa ký hợp đồng"** trên đơn và trên lịch giao. **Cảnh báo, không chặn.**
6. Lúc bàn giao: shipper mở đúng link đó cho khách ký tại chỗ (nếu chưa ký), và chụp ảnh bàn giao.

**Bất biến quan trọng:** chỉ có **một link, một tài liệu, một chữ ký**. Ký trước ở nhà hay ký lúc
nhận đồ đều là cùng cái link. Không tồn tại hai phiên bản hợp đồng cho một đơn.

## 3. Dữ liệu

### 3.1 Bảng `contracts` — một đơn một dòng

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `order_id` | FK, **unique** | Chỉ đơn con (hoặc đơn không tách) có hợp đồng. Đơn cha `is_parent` **không** tạo hợp đồng — nó không có ngày/đồ riêng. |
| `token` | string(64), unique, index | `Str::random(64)`. Dài hơn `review_token` (40) vì hợp đồng chứa dữ liệu cá nhân. |
| `content_html` | longText, nullable | **Chỉ ghi lúc ký.** Trước khi ký thì null, render động từ mẫu. |
| `content_hash` | char(64), nullable | SHA-256 của `content_html`. |
| `signature_path` | string, nullable | PNG nền trong suốt trên disk `media`. |
| `signed_at` / `signed_ip` / `signed_user_agent` | timestamp / string(45) / string(512) | Dấu vết ký. |
| `signer_id_number` | text, nullable | Số CCCD. Cast **`encrypted`**. |
| `id_consent_at` | timestamp, nullable | Thời điểm tick ô đồng ý xử lý CCCD. |
| `first_viewed_at` | timestamp, nullable | Lần mở link đầu tiên (sau khi qua cửa 4 số cuối). |
| `pdf_path` | string, nullable | File đã ký trên disk `media`. |

### 3.2 Bảng `handover_photos`

`order_id` · `kind` (`pickup`|`return`) · `path` · `uploaded_by` (FK users) · `created_at`.

Ảnh đi qua `MediaVariantService` như mọi ảnh khác — **không** resize ở chỗ mới.

### 3.3 Cột mới trên `site_settings`

`contract_template_html` (longText) — mẫu hợp đồng. Singleton, khớp với cách `site_settings`
đang dùng cho cấu hình shop.

## 4. Logic — `ContractService`

**Single source of truth.** Không nơi nào khác được render hợp đồng.

| Hàm | Việc |
|---|---|
| `draftFor(Order): ?Contract` | Gọi từ `OrderObserver` khi sang `confirmed`. Trả null cho đơn cha. Idempotent. |
| `render(Order): string` | Thay biến vào `contract_template_html`. |
| `sign(Contract, SignData, Request): void` | Đóng băng HTML + hash, lưu chữ ký, ghi dấu vết, sinh PDF, dispatch mail. |
| `pdf(Contract): string` | Blade PDF + trang biên bản chứng thực. |

**Biến trong mẫu:** `{{ten_khach}}`, `{{sdt_khach}}`, `{{dia_chi_khach}}`, `{{cccd_khach}}`,
`{{ma_don}}`, `{{ngay_thue}}`, `{{ngay_tra}}`, `{{bang_thiet_bi}}`, `{{tong_tien}}`,
`{{tien_coc}}`, `{{ten_shop}}`, `{{dia_chi_shop}}`.

### 4.1 Thời điểm đóng băng — chi tiết dễ sai

Nội dung đóng băng **lúc ký**, không phải lúc tạo nháp, vì admin có thể sửa mẫu ở giữa.

Rủi ro kéo theo: khách đang mở trang đọc bản cũ, admin đổi mẫu, khách bấm Ký → ký nhầm bản mới.
**Chặn bằng cách:** trang ký gửi kèm `content_hash` của đúng bản khách đang đọc; lúc submit mà
hash lệch thì **từ chối và bắt tải lại**. Khách không bao giờ ký thứ mình chưa đọc.

### 4.2 Trang "Biên bản chứng thực" (trang cuối PDF)

Đây là thứ làm tài liệu này có sức nặng chứng cứ. In ra:

- ID hợp đồng, mã đơn, `content_hash`.
- Thời điểm gửi link · thời điểm mở lần đầu · thời điểm ký · IP · thiết bị.
- Email đã xác thực OTP (lấy từ `EmailOtp`) — chứng minh người ký kiểm soát hộp thư đó.
- **Chỉ dẫn tra sao kê:** nội dung chuyển khoản kỳ vọng (= mã đơn), số tiền, thời điểm shop ghi nhận đã thu, và ai ghi nhận.

> **Nói rõ giới hạn:** dự án **không** có webhook SePay (`PaymentQrService` chỉ sinh ảnh QR;
> admin tự đối soát rồi bấm `markPaid()`). Nên con số thu tiền trong hệ thống là **do shop gõ
> vào**, không phải dữ liệu ngân hàng. Bằng chứng bên thứ ba thật sự là **sao kê trong app ngân
> hàng của shop** — nó tồn tại độc lập với hệ thống này vì nội dung chuyển khoản đã là mã đơn.
> Vai trò của biên bản là **chỉ đường vào sao kê đó**, không phải thay thế nó. Muốn hệ thống tự
> giữ bằng chứng ngân hàng thì phải làm webhook SePay — việc riêng, ngoài phạm vi.

## 5. Route & màn hình

### 5.1 Khách (không cần đăng nhập)

| Route | Ghi chú |
|---|---|
| `GET /hop-dong/{token}` | Cửa 4 số cuối SĐT → xem + ký. `noindex`. |
| `POST /hop-dong/{token}/mo` | Kiểm 4 số cuối. `throttle:10,1` — chống dò. |
| `POST /hop-dong/{token}` | Ký. `throttle:10,1`. |
| `GET /hop-dong/{token}/pdf` | Tải PDF (sau khi đã qua cửa). |

Màn hình ký: nội dung hợp đồng → ô CCCD + **checkbox đồng ý riêng** (không gộp vào "đồng ý điều
khoản"), ghi rõ mục đích *đối chiếu khi bàn giao và hoàn cọc* → canvas `signature_pad` → nút Ký.

### 5.2 Admin

- Chi tiết đơn: nút **Sao chép link hợp đồng**, trạng thái ký, link tải PDF, xem CCCD đầy đủ.
- Danh sách đơn: nhãn đỏ **"Chưa ký hợp đồng"**.
- Trang mới **Mẫu hợp đồng**: editor TipTap + `EditorHtml::clean()` (dùng lại của `StaticPage`), có danh sách biến chèn được.

### 5.3 Shipper

- Lịch giao: nhãn "Chưa ký hợp đồng" + nút mở link ký.
- Upload ảnh bàn giao (`pickup` / `return`).

## 6. Bảo mật & dữ liệu cá nhân

- Token 64 ký tự + cửa **4 số cuối SĐT** (link Zalo bị chuyển tiếp thì người lạ vẫn không đọc được tên/địa chỉ/CCCD).
- `signer_id_number` cast `encrypted`; **không bao giờ** vào prop Inertia của trang khách; ngoài admin che còn 4 số cuối; không ghi log.
- Ký một lần — ký rồi thì `POST` trả 409.
- Command theo lịch `contracts:purge-id-numbers` — xoá `signer_id_number` sau khi đơn hoàn cọc xong **90 ngày** (bắt chước `SendPickupReminders`).
- PDF trên disk `media` truy cập qua route có kiểm quyền, không phơi URL công khai.

## 7. Kiểm thử

**Feature (PHPUnit)** — token sai → 404 · sai 4 số cuối → từ chối + đếm throttle · ký lần hai →
409 · `content_hash` lệch → từ chối, không ghi gì · đơn cha không sinh hợp đồng · CCCD **không**
xuất hiện trong prop Inertia của trang khách · nhãn "chưa ký" hiện đúng ở admin và shipper ·
`draftFor` idempotent.

**PDF** — test **bắt buộc** khẳng định PDF chứa chữ tiếng Việt có dấu. Lỗi font dompdf là **lỗi
im lặng** (ra ô vuông); không có test thì phát hiện khi khách đã cầm file. Ràng buộc này lấy từ
[adr_pdf_generation.md](adr_pdf_generation.md).

**Component (Vitest)** — canvas ký: vẽ rồi bấm Ký thì gửi được data URL; chưa vẽ thì nút Ký
disabled; nút Xoá làm sạch canvas.

Test phải **collation-safe** (chạy đúng trên cả sqlite lẫn MySQL `utf8mb4_unicode_ci`).

## 8. Chia việc

| # | Việc | Ghi chú |
|---|---|---|
| 1 | ADR + cập nhật `tech-strategy.md` (thêm lại dòng PDF, thêm `signature_pad`) | Gate cho dependency |
| 2 | Schema + model `Contract` + `ContractService.draftFor/render` + hook `OrderObserver` | Không có UI |
| 3 | Trang ký của khách (cửa 4 số cuối, canvas, CCCD + đồng ý) + `sign()` | |
| 4 | PDF + biên bản chứng thực + mail đính kèm | Có test font tiếng Việt |
| 5 | Admin: trang Mẫu hợp đồng, nút sao chép link, nhãn chưa ký | |
| 6 | Shipper: nhãn + nút mở link + upload ảnh bàn giao | |
| 7 | Command xoá CCCD theo lịch | |

Quality gate trước mỗi commit: `php artisan test` · `npm test` · `npx tsc --noEmit` ·
`npm run lint` · `./vendor/bin/pint --test` · `npm run build`.
