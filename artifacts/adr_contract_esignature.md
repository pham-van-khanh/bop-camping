# ADR — Hệ thống hợp đồng thuê điện tử của BopCamping

**Ngày:** 2026-08-16 · **Trạng thái:** ✅ Accepted · **Reversibility:** Two-Way Door

## 1. Bối cảnh

Chủ shop muốn bỏ hẳn việc in hợp đồng ra giấy cho khách ký tay. BopCamping tự xây hệ thống hợp
đồng điện tử riêng, gắn thẳng vào dữ liệu đơn hàng sẵn có.

Yêu cầu:

- Shop xác nhận đơn → **gửi hợp đồng cho khách trước** để khách đọc.
- Khách **ký lúc bàn giao đồ**, nhưng ai muốn ký sớm ở nhà thì cũng được — "ký trước hay sau tuỳ khách".
- Gửi bằng **link**, chủ yếu qua **Zalo**, mở trên **máy nào cũng ký được** (máy khách hoặc máy shipper).
- Hợp đồng có **số CCCD** do khách tự điền lúc ký.
- Mẫu hợp đồng **admin tự sửa được**, không phải gọi dev.
- Chưa ký thì **cảnh báo, không chặn** việc giao đồ.

Ràng buộc quan trọng nhất chủ shop nêu ra: hợp đồng phải **đứng vững nếu có chuyện xảy ra**. Nên
ADR này quyết định cả kiến trúc chứng cứ, không chỉ kiến trúc phần mềm.

## 2. Khảo sát pháp lý (Việt Nam)

| Câu hỏi | Kết luận |
|---|---|
| Hợp đồng điện tử có hiệu lực không? | **Có.** BLDS 2015: giao dịch qua phương tiện điện tử dưới hình thức thông điệp dữ liệu **được coi là giao dịch bằng văn bản**. Thuê động sản thông thường **không** thuộc nhóm bắt buộc công chứng/chứng thực. |
| Chữ ký vẽ tay trên canvas có hợp pháp không? | **Có, ở tầng chứng cứ.** Luật Giao dịch điện tử 2023 (20/2023/QH15, hiệu lực 01/07/2024) Điều 23: chữ ký điện tử **không bị phủ nhận giá trị pháp lý chỉ vì nó ở dạng điện tử**. |
| Có **tương đương chữ ký tay** không? | **Không tự động.** Tầng đó chỉ dành cho chữ ký số do **CA được Bộ TT&TT cấp phép** cấp (VNPT-CA, Viettel-CA, FPT-CA, MISA, FastCA...), hoặc chữ ký điện tử chuyên dùng **đã được chứng nhận bảo đảm an toàn**. |
| Thu số CCCD cần gì? | **Luật Bảo vệ dữ liệu cá nhân 2025** (hiệu lực 01/01/2026) + **NĐ 356/2025/NĐ-CP** đã thay `NĐ 13/2023` (hết hiệu lực 01/01/2026). Bắt buộc: đồng ý rõ ràng, nêu đúng mục đích, không dùng sang việc khác, có chính sách xoá. |

> Đây là khảo sát để ra quyết định kỹ thuật, **không phải tư vấn pháp lý**. Nếu về sau shop cho
> thuê thiết bị giá trị lớn, hỏi luật sư trước khi dựa vào mục này.

**Kết luận vận hành:** hợp đồng có hiệu lực; chữ ký nằm ở tầng chứng cứ. Việc cần làm không phải
đi tìm tầng pháp lý cao hơn, mà là **dựng chuỗi chứng cứ đủ chắc** cho tầng này.

## 3. Quyết định kiến trúc chứng cứ

### 3.1 Điểm yếu cốt lõi

Toàn bộ bằng chứng do hệ thống của shop tạo ra và shop toàn quyền sửa. Lập luận phản bác hiển
nhiên là *"bên cho thuê tự dựng ra được hết"*. Làm dấu vết dày thêm trên server của mình **không**
chữa được điều này.

### 3.2 Cách chữa — bản sao ngoài tầm kiểm soát của shop

| Lớp | Ai làm chứng | Trạng thái |
|---|---|---|
| PDF đã ký gửi vào hộp thư khách **ngay lúc ký** (kèm hash, DKIM) | Google/Microsoft | **Làm mới** — mạnh nhất trong hệ thống |
| Ảnh chụp lúc bàn giao đồ | — (bằng chứng thực hiện hợp đồng) | **Làm mới** |
| Xác thực OTP email | — (đã có `EmailOtp`) | **Đã có** — in vào biên bản chứng thực |
| Sao kê ngân hàng (nội dung CK = mã đơn) | Ngân hàng | **Nằm ngoài hệ thống** — xem 3.3 |

Lớp PDF-gửi-vào-hộp-thư-khách là trụ chính: bản đó nằm trên server Google/Microsoft, có header
DKIM chứng minh xuất phát từ tên miền shop vào đúng giờ đó, và shop **không sửa được**. Nếu về
sau hai bên chìa ra hai bản khác nhau thì hash sẽ lệch; hash khớp thì hết đường tranh cãi nội dung.

### 3.3 Sao kê ngân hàng — giới hạn phải biết

`PaymentQrService` chỉ **sinh ảnh QR** — không webhook, không đối soát; admin tự xem sao kê rồi
bấm `Order::markPaid()`. Nên số tiền lưu trong đơn là **do shop gõ vào**, đúng loại bằng chứng
"tự mình tạo ra" mà mục 3.1 đang muốn tránh.

May là bằng chứng thật vẫn tồn tại **độc lập với hệ thống**: nội dung chuyển khoản trên QR đã là
mã đơn, nên sao kê trong app ngân hàng của shop tự khắc có dòng gắn đúng đơn đó — và khách không
thể vừa nói *"tôi không biết đơn này"* vừa giải thích tại sao chính tài khoản mình chuyển tiền vào
đúng mã đơn.

**Quyết định:** biên bản chứng thực **chỉ đường vào sao kê** (in mã đơn cần tra, số tiền, thời
điểm và người ghi nhận), không giả vờ chứa sao kê. Muốn hệ thống tự giữ bằng chứng ngân hàng thì
phải làm webhook SePay — việc riêng, ngoài phạm vi.

### 3.4 Định vị vai trò của hợp đồng

Với đơn vài trăm nghìn tới vài triệu, **kiện dân sự không kinh tế** (án phí + thời gian thường
lớn hơn số tiền đòi được). Công cụ thực thi thật sự là **tiền cọc đang giữ**. Vai trò của hợp
đồng là **giữ cọc một cách chính đáng và khiến khách không cãi** — chuỗi chứng cứ ở 3.2 thừa sức.

Tình huống nặng (khách ôm đồ biến mất) thì đường đi là **tố giác hình sự**, không phải toà dân
sự: Điều 175 BLHS quy định đúng hành vi *thuê tài sản rồi bỏ trốn hoặc cố tình không trả*, áp
dụng từ **giá trị 4 triệu đồng**. Thứ công an cần là hợp đồng chứng minh quan hệ thuê có thời
hạn trả + **số CCCD định danh** + bằng chứng đã giao đồ. Ở đường này tầng pháp lý của chữ ký gần
như không còn là vấn đề — đó là lý do chính khiến việc thu CCCD đáng làm.

### 3.5 Rủi ro chấp nhận

Toà có toàn quyền đánh giá chứng cứ và về lý thuyết có thể cho rằng riêng chữ ký điện tử này chưa
đủ. **Rủi ro này không triệt tiêu được** ở tầng chứng cứ; cách duy nhất xoá hẳn là ký số bằng
chứng thư của một CA Việt Nam.

**Quyết định:** *chưa* làm sẵn lớp trừu tượng để cắm CA (YAGNI — chủ shop đã cân nhắc và bỏ). Nếu
sau này cần, chỗ phải sửa là bước sinh PDF trong `ContractService`, xem mục 5.

## 4. Quyết định công nghệ

### 4.1 Sinh PDF — `barryvdh/laravel-dompdf`

Hồi sinh kết luận của [adr_pdf_generation.md](adr_pdf_generation.md) (ADR đó bị Rejected vì tính
năng in lịch giao bị bỏ, **không phải** vì dompdf sai). Khảo sát ở đó vẫn đúng nguyên: prod là
VPS Linux dựng thủ công nên Browsershot/Chromium quá nặng, wkhtmltopdf đã ngừng phát triển.

Mang sang nguyên ba ràng buộc của ADR cũ:

1. Nhúng **DejaVu Sans** + set `defaultFont` — dompdf mặc định không đủ dấu tiếng Việt.
2. **Bắt buộc có test khẳng định PDF chứa chữ có dấu.** Lỗi font là **lỗi im lặng** (ra ô vuông), không có test thì phát hiện khi khách đã cầm file.
3. Blade PDF viết riêng bằng `<table>` + inline style, **không** tái dùng component Tailwind.

**Cố ý làm khác ADR cũ:** ADR cũ chọn *stream, không lưu PDF xuống disk* để tránh dữ liệu khách
nằm lại server. Ở đây **lưu xuống disk `media`** — vì với hợp đồng, file lưu trữ **chính là bằng
chứng**; không lưu thì mất luôn mục đích của tính năng. Đánh đổi này được bù bằng: chỉ admin truy
cập được, và command xoá số CCCD theo lịch (mục 4.4).

### 4.2 Vẽ chữ ký — `signature_pad`

Đã cân nhắc tự viết canvas (~120 dòng). Chọn thư viện vì xử lý cảm ứng đa thiết bị (pointer
events, làm mượt nét, tỷ lệ DPI, ngăn cuộn trang khi vẽ) nhiều lỗi vặt hơn vẻ ngoài của nó.
~4KB, không phụ thuộc gì khác.

### 4.3 Mẫu hợp đồng — dùng lại hạ tầng sẵn có

Không dựng cơ chế soạn thảo mới. Dùng lại **editor TipTap + `EditorHtml::clean()`** đang phục vụ
`StaticPage`, lưu vào cột mới trên `site_settings` (singleton cấu hình shop đã có).

### 4.4 Dữ liệu cá nhân

- `signer_id_number` dùng cast **`encrypted`** của Laravel — lộ DB cũng không đọc được.
- **Không bao giờ** đưa vào prop Inertia của trang khách; nơi hiển thị ngoài admin che còn 4 số cuối.
- Command chạy theo lịch **xoá số CCCD sau khi đơn đã hoàn cọc xong 90 ngày** (bắt chước `SendPickupReminders`).
- Checkbox đồng ý **riêng**, không gộp vào "đồng ý điều khoản", nêu rõ mục đích: *đối chiếu khi bàn giao và hoàn cọc*.

### 4.5 Phương án đã loại

| Phương án | Lý do loại |
|---|---|
| Thuê dịch vụ ký điện tử bên thứ ba | Chi phí thuê bao hàng năm; nhà cung cấp nước ngoài không phải CA được cấp phép ở VN nên **không** nâng được tầng pháp lý so với tự xây; ràng buộc hệ thống vào bên ngoài mà không đổi lại điều gì shop cần. |
| Chỉ HTML, khách tự bấm "In → Lưu PDF" | Không có file lưu trữ cố định → mất trụ chứng cứ chính (3.2). |

## 5. Hệ quả

- Thêm 2 dependency: `barryvdh/laravel-dompdf` (PHP), `signature_pad` (JS). Phải **cập nhật bảng golden path** trong `.claude/rules/tech-strategy.md` — dòng PDF đã bị gỡ khi ADR cũ bị Rejected.
- Thêm 3 thay đổi schema: bảng `contracts`, bảng `handover_photos`, cột `contract_template_html` trên `site_settings`.
- Toàn bộ việc render/đóng băng/hash/ký/sinh PDF nằm trong **một** `ContractService` — single source of truth, không chỗ nào khác được tự render hợp đồng.
- Nếu sau này muốn lên tầng "tương đương chữ ký tay": chỗ sửa là bước sinh PDF trong `ContractService` — ký số bản PDF bằng chứng thư của CA Việt Nam trước khi lưu. Không đụng tới schema hay luồng khách.

## 6. Liên quan

- [design_spec_contract_esignature.md](design_spec_contract_esignature.md) — thiết kế chi tiết.
- [adr_pdf_generation.md](adr_pdf_generation.md) — khảo sát dompdf gốc (Rejected, hồi sinh kết luận).
- [adr_sepay_qr_payment.md](adr_sepay_qr_payment.md) — QR thanh toán, nguồn mã đơn dùng để tra sao kê.
- [adr_shipper_role_and_access.md](adr_shipper_role_and_access.md) — trang shipper, nơi gắn nút ký + upload ảnh bàn giao.
