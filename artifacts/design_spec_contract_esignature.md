# Design Spec — Hệ thống hợp đồng thuê điện tử của BopCamping

**Ngày:** 2026-08-16 · **ADR:** [adr_contract_esignature.md](adr_contract_esignature.md)

## 1. Mục tiêu

Số hoá **Hợp đồng thuê thiết bị camping số 1408/HĐTTB** hiện có của shop — gồm hợp đồng chính
(Điều 1–10), **Phụ lục A (Biên bản bàn giao)** và **Phụ lục B (Biên bản nhận lại thiết bị)** —
để khách ký online, bỏ hẳn việc in giấy.

**Không làm trong phạm vi này:** chữ ký số của CA Việt Nam; webhook đối soát ngân hàng; hợp đồng
cho đơn cha (đơn cha không có hợp đồng riêng — xem 4.1).

## 2. Cấu trúc hợp đồng — ba lần ký, một link

Hợp đồng hiện tại có **ba chỗ ký**, không phải một. Thiết kế phải phản ánh đúng:

| Giai đoạn | Tài liệu | Ký khi nào | Chốt điều gì |
|---|---|---|---|
| 1 | Hợp đồng chính (Điều 1–10) | Sau khi shop xác nhận đơn + đã thu cọc | Điều khoản, giá, cọc, trách nhiệm |
| 2 | **Phụ lục A** — Biên bản bàn giao | Tại chỗ, lúc **giao** đồ | Tình trạng từng món lúc giao (Mới / Tốt / Có vết cũ) + ảnh |
| 3 | **Phụ lục B** — Biên bản nhận lại | Tại chỗ, lúc **trả** đồ | Tình trạng lúc trả + **bảng quyết toán cọc** |

Giai đoạn 3 là chữ ký **quan trọng nhất về tiền**: không có nó thì việc trừ tiền cọc là shop tự
quyết một mình — đúng chỗ dễ tranh cãi nhất. Điều 4a của hợp đồng cũng đã ràng buộc khách phải
ký xác nhận vào Phụ lục A.

**Bất biến:** một đơn = một hợp đồng = **một link duy nhất**. Cả ba lần ký đều mở cùng link đó;
trang tự hiện đúng giai đoạn đang cần ký. Không tồn tại hai phiên bản hợp đồng cho một đơn.

## 3. Luồng nghiệp vụ

1. Khách đặt đơn → **chuyển cọc** (QR SePay đã có).
2. Shop xin ảnh 2 mặt CCCD của khách qua Zalo → **admin upload vào hệ thống và tự nhập** số CCCD, ngày cấp, nơi cấp. Xong thì **xoá ảnh khỏi Zalo** (xem 7.2).
3. Admin bấm **Tạo hợp đồng** → hệ thống dựng hợp đồng chính với đủ dữ liệu đơn + CCCD → nút **Sao chép link**.
4. Gửi link qua Zalo (mail xác nhận đơn cũng đính kèm link).
5. Khách mở link → nhập 4 số cuối SĐT → **đọc và ký** (không phải điền gì, admin đã nhập sẵn).
6. Ký xong → sinh PDF → **gửi mail kèm PDF cho khách và cho shop**.
7. **Lúc giao đồ:** shipper mở lại link, tick tình trạng từng món, chụp ảnh, khách ký **Phụ lục A** tại chỗ → PDF cập nhật, gửi lại mail.
8. **Lúc trả đồ:** mở lại link, tick tình trạng, hệ thống dựng bảng quyết toán từ dữ liệu đơn, khách ký **Phụ lục B** tại chỗ → PDF hoàn chỉnh, gửi lại mail.

Chưa ký ở bất kỳ giai đoạn nào → nhãn đỏ trên đơn và lịch giao. **Cảnh báo, không chặn.**

## 4. Dữ liệu

### 4.1 `contracts` — một đơn một dòng

`order_id` (FK, **unique** — đơn cha `is_parent` không tạo hợp đồng vì không có ngày/đồ riêng) ·
`code` (số hợp đồng, ví dụ `1408/HĐTTB`) · `token` (string 64, unique — `Str::random(64)`) ·
`signer_id_number` (text, cast **`encrypted`**) · `signer_id_issued_on` (date) ·
`signer_id_issued_place` (string) · `id_front_path` / `id_back_path` (string, nullable) ·
`pdf_path` · `first_viewed_at`.

### 4.2 `contract_signatures` — một dòng mỗi giai đoạn ký

`contract_id` · `stage` (`main` | `handover` | `return`) · `content_html` (longText, đóng băng
lúc ký) · `content_hash` (char 64) · `signature_path` (PNG nền trong suốt) · `signed_at` ·
`signed_ip` · `signed_user_agent`. **Unique** `(contract_id, stage)` — mỗi giai đoạn ký một lần.

Tách bảng thay vì nhân ba bộ cột trên `contracts`: ba giai đoạn có cùng cấu trúc dấu vết, gộp
lại thì mọi truy vấn "ai ký gì lúc nào" viết một lần.

### 4.3 `contract_items` — ảnh chụp danh mục đồ + tình trạng hai lượt

`contract_id` · `product_id` (nullable — sản phẩm có thể bị xoá sau này) · `name` ·
`accessories` (text — "1 túi đựng, 8 dây căng lều, 16 cọc ghim đất") · `quantity` ·
`replacement_value` (int — giá trị đền bù, đóng băng tại thời điểm lập hợp đồng) ·
`handover_condition` (`new`|`good`|`used_marks`, nullable) · `handover_note` ·
`return_condition` (`same`|`wear`|`damaged`, nullable) · `return_note`.

Ba giá trị của mỗi cột tình trạng lấy đúng từ checkbox trong Phụ lục A và B của hợp đồng giấy.

### 4.4 `handover_photos`

`contract_item_id` (nullable — ảnh có thể của cả đơn) · `contract_id` · `kind`
(`pickup`|`return`) · `path` · `uploaded_by` · `created_at`. Đi qua `MediaVariantService` như
mọi ảnh khác — **không** resize ở chỗ mới.

### 4.5 Cột mới trên bảng có sẵn

- `products.replacement_value` (int, nullable) — **giá trị đền bù bằng tiền**. Hiện `products` chỉ có `deposit`, không có con số này, nên Điều 6 của hợp đồng đang dựa vào một giá trị không tồn tại (xem 8.2).
- `products.accessories` (text, nullable) — danh sách phụ kiện, in vào Phụ lục A/B.
- `site_settings`: `contract_template_html`, `handover_template_html`, `return_template_html` — ba mẫu, admin sửa được.

## 5. Logic — `ContractService`

**Single source of truth.** Không nơi nào khác được render hợp đồng.

| Hàm | Việc |
|---|---|
| `createFor(Order, IdentityData): Contract` | Admin bấm Tạo hợp đồng. Chụp `contract_items` từ đơn (kèm `replacement_value`, `accessories`). Idempotent. |
| `render(Contract, string $stage): string` | Thay biến vào mẫu tương ứng. |
| `sign(Contract, string $stage, SignData, Request): void` | Đóng băng HTML + hash cho **đúng giai đoạn đó**, lưu chữ ký, ghi dấu vết, sinh lại PDF, gửi mail. |
| `pdf(Contract): string` | Hợp đồng chính + Phụ lục A + Phụ lục B (phần chưa ký để trống) + **biên bản chứng thực**. |

**Biến trong mẫu:** `{{so_hop_dong}}`, `{{ten_khach}}`, `{{cccd_khach}}`, `{{ngay_cap}}`,
`{{noi_cap}}`, `{{sdt_khach}}`, `{{dia_chi_khach}}`, `{{ma_don}}`, `{{ngay_nhan}}`,
`{{ngay_tra}}`, `{{so_ngay_thue}}`, `{{bang_thiet_bi}}`, `{{tong_tien}}`, `{{tien_coc}}`,
`{{bang_ban_giao}}`, `{{bang_nhan_lai}}`, `{{bang_quyet_toan}}`.

Bảng checklist và bảng quyết toán **do hệ thống sinh**, không để admin gõ tay trong editor —
chúng phải khớp với `contract_items` và số tiền của đơn.

### 5.1 Thời điểm đóng băng

Nội dung đóng băng **lúc ký, theo từng giai đoạn**. Rủi ro: khách đang mở trang đọc bản cũ, admin
đổi mẫu, khách bấm Ký → ký nhầm bản mới. **Chặn bằng:** trang ký gửi kèm `content_hash` của đúng
bản đang đọc; submit mà hash lệch thì **từ chối và bắt tải lại**.

### 5.2 Biên bản chứng thực (trang cuối PDF)

ID hợp đồng · mã đơn · hash của **cả ba** giai đoạn · thời điểm gửi link / mở lần đầu / ký từng
giai đoạn · IP · thiết bị · email đã xác thực OTP · **chỉ dẫn tra sao kê** (mã đơn cần tra, số
tiền, thời điểm và người ghi nhận đã thu).

> Giới hạn phải biết: `PaymentQrService` chỉ sinh ảnh QR — không webhook. Số tiền trong hệ thống
> là **do shop gõ vào**. Bằng chứng ngân hàng thật nằm ở sao kê trong app ngân hàng của shop
> (nội dung CK đã là mã đơn). Biên bản **chỉ đường vào sao kê**, không thay thế nó.

## 6. Route & màn hình

### 6.1 Khách (không cần đăng nhập)

`GET /hop-dong/{token}` (cửa 4 số cuối SĐT → hiện đúng giai đoạn đang cần ký, `noindex`) ·
`POST /hop-dong/{token}/mo` (`throttle:10,1`) · `POST /hop-dong/{token}/ky/{stage}`
(`throttle:10,1`) · `GET /hop-dong/{token}/pdf`.

Màn hình ký: nội dung → canvas `signature_pad` → nút Ký. Giai đoạn `handover`/`return` hiện thêm
checklist tình trạng từng món + nút chụp ảnh (shipper thao tác, khách xem rồi ký).

### 6.2 Admin

Chi tiết đơn: form nhập CCCD + upload 2 ảnh · nút **Tạo hợp đồng** · nút **Sao chép link** ·
trạng thái ba giai đoạn ký · tải PDF. Danh sách đơn: nhãn đỏ giai đoạn còn thiếu.
Trang mới **Mẫu hợp đồng**: 3 mẫu, editor TipTap + `EditorHtml::clean()` (dùng lại của
`StaticPage`), kèm danh sách biến.

### 6.3 Shipper

Lịch giao: nhãn giai đoạn còn thiếu + nút mở link ký · tick tình trạng + chụp ảnh.

## 7. Bảo mật & dữ liệu cá nhân

### 7.1 Link

Token 64 ký tự + cửa **4 số cuối SĐT** (link Zalo bị chuyển tiếp thì người lạ không đọc được
tên/địa chỉ/CCCD). Mỗi giai đoạn ký một lần — ký rồi thì `POST` trả 409.

### 7.2 CCCD — điểm rủi ro cao nhất của tính năng này

- Số CCCD cast **`encrypted`**; **không bao giờ** vào prop Inertia của trang khách; ngoài admin che còn 4 số cuối; không ghi log.
- **Ảnh CCCD** lưu trên disk `media` ở thư mục riêng, **không** sinh biến thể public, chỉ phục vụ qua route có kiểm quyền admin. Không đưa vào PDF hợp đồng.
- Command theo lịch `contracts:purge-identity` — xoá **cả số lẫn ảnh** sau khi đơn hoàn cọc xong **90 ngày** (bắt chước `SendPickupReminders`).
- Trang admin phải có dòng nhắc: **xoá ảnh khỏi Zalo sau khi upload**. Ảnh nằm trong Zalo là chỗ tệ nhất — không kiểm soát quyền xem, không tự xoá, nằm trong thư viện ảnh điện thoại.

## 8. Văn bản hợp đồng cần sửa

Đây là việc của chủ shop, không phải việc code — nhưng phải làm trước khi chạy thật, vì bản hiện
tại vênh với quy trình mới.

| # | Chỗ | Vấn đề | Sửa thành |
|---|---|---|---|
| 1 | Trang 1, ghi chú CCCD | Ghi *"không giữ bản gốc CCCD"* nhưng quy trình mới **có lưu ảnh 2 mặt** | Nêu rõ: có nhận và lưu ảnh chụp CCCD để đối chiếu, không giữ bản gốc, **xoá sau khi hoàn cọc 90 ngày** |
| 2 | **Điều 1, bảng thiết bị** | Cột bồi thường chỉ ghi *"15–90% giá trị thiết bị"* — **không có con số gốc nào để nhân tỷ lệ vào**. Điều 6.3 lại nói mất thì đền *"100% giá trị theo bảng Điều 1"*. Khách mất lều thì 100% của cái gì? | Thêm cột **Giá trị đền bù (VNĐ)** cho từng món; hệ thống tự điền từ `products.replacement_value` |
| 3 | Điều 3.2 | Ghi cọc thanh toán *"khi nhận thiết bị"*, nhưng quy trình mới thu cọc **ngay sau khi đặt đơn** | Sửa thành thu cọc trước khi nhận thiết bị (giữ nguyên quy trình — thu trước tốt hơn cho shop và tạo dấu vết ngân hàng sớm) |
| 4 | Điều 8 | Chưa nhắc việc lưu ảnh CCCD và thời hạn xoá | Bổ sung phạm vi dữ liệu lưu + thời hạn xoá, cho khớp Luật BVDLCN 2025 |
| 5 | Điều 10 | Đã có sẵn mệnh đề *"hoặc xác nhận điện tử qua tin nhắn/ứng dụng"* — tốt, nhưng còn ghi *"lập thành 02 bản... mỗi Bên giữ 01 bản"* | Làm rõ: hợp đồng giao kết dưới dạng điện tử, hai Bên cùng giữ bản PDF có giá trị như nhau |

## 9. Kiểm thử

**Feature** — token sai → 404 · sai 4 số cuối → từ chối + throttle · ký lại cùng giai đoạn → 409 ·
ký `handover` khi chưa ký `main` → từ chối · `content_hash` lệch → từ chối, không ghi gì · đơn cha
không tạo hợp đồng · **số CCCD và đường dẫn ảnh không xuất hiện trong prop Inertia của trang
khách** · `createFor` idempotent · `purge-identity` xoá đúng đơn đủ điều kiện, không đụng đơn khác.

**PDF** — test **bắt buộc** khẳng định PDF chứa chữ tiếng Việt có dấu. Lỗi font dompdf là **lỗi
im lặng** (ra ô vuông); không có test thì phát hiện khi khách đã cầm file. Thêm test: PDF ký đủ
ba giai đoạn có đủ ba chữ ký; ký một giai đoạn thì hai phần kia để trống chứ không vỡ layout.

**Component (Vitest)** — vẽ rồi bấm Ký thì gửi được data URL; chưa vẽ thì nút Ký disabled; nút
Xoá làm sạch canvas; checklist tình trạng bắt buộc chọn đủ món trước khi cho ký.

Test phải **collation-safe** (chạy đúng trên cả sqlite lẫn MySQL `utf8mb4_unicode_ci`).

## 10. Chia việc

| # | Việc |
|---|---|
| 1 | ADR + cập nhật `tech-strategy.md` (thêm lại dòng PDF, thêm `signature_pad`) |
| 2 | Schema: `contracts`, `contract_signatures`, `contract_items`, `handover_photos`, cột mới trên `products` / `site_settings` |
| 3 | Admin: nhập CCCD + upload ảnh, nút Tạo hợp đồng, nút Sao chép link, trạng thái 3 giai đoạn |
| 4 | Trang ký của khách — giai đoạn `main` (cửa 4 số cuối, canvas) |
| 5 | PDF + biên bản chứng thực + mail đính kèm (có test font tiếng Việt) |
| 6 | Giai đoạn `handover`: checklist tình trạng + ảnh + ký (shipper) |
| 7 | Giai đoạn `return`: checklist + bảng quyết toán + ký |
| 8 | Trang Mẫu hợp đồng (3 mẫu) trong admin |
| 9 | Command `contracts:purge-identity` |

Quality gate trước mỗi commit: `php artisan test` · `npm test` · `npx tsc --noEmit` ·
`npm run lint` · `./vendor/bin/pint --test` · `npm run build`.
