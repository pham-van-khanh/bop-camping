# ADR — Hợp đồng thuê điện tử + chữ ký tay online

**Ngày:** 2026-08-16 · **Trạng thái:** ✅ Accepted · **Reversibility:** Two-Way Door

## 1. Bối cảnh

Chủ shop muốn bỏ hẳn việc in hợp đồng ra giấy để khách ký tay. Yêu cầu cụ thể:

- Khi shop xác nhận đơn thì **gửi hợp đồng cho khách trước** để khách đọc.
- Khách **ký lúc bàn giao đồ**, nhưng ai muốn ký sớm ở nhà thì cũng được — "ký trước hay sau tuỳ khách".
- Gửi bằng **link**, chủ yếu qua **Zalo**, mở trên **máy nào cũng ký được** (máy khách hoặc máy shipper).
- Hợp đồng có **số CCCD** do khách tự điền lúc ký.
- Mẫu hợp đồng **admin tự sửa được**, không phải gọi dev.
- Chưa ký thì **cảnh báo, không chặn** việc giao đồ.

Mối lo lớn nhất chủ shop nêu ra: *"khi tôi dùng hợp đồng để kiện có thể xảy ra rủi ro"*. Nên ADR
này phải trả lời cả câu hỏi pháp lý, không chỉ câu hỏi kỹ thuật.

## 2. Khảo sát pháp lý (Việt Nam)

| Câu hỏi | Kết luận |
|---|---|
| Hợp đồng điện tử có hiệu lực không? | **Có.** BLDS 2015: giao dịch qua phương tiện điện tử dưới hình thức thông điệp dữ liệu **được coi là giao dịch bằng văn bản**. Thuê động sản thông thường **không** thuộc nhóm bắt buộc công chứng/chứng thực. |
| Chữ ký vẽ tay trên canvas có hợp pháp không? | **Có, ở tầng chứng cứ.** Luật Giao dịch điện tử 2023 (20/2023/QH15, hiệu lực 01/07/2024) Điều 23: chữ ký điện tử **không bị phủ nhận giá trị pháp lý chỉ vì nó ở dạng điện tử**. |
| Có **tương đương chữ ký tay** không? | **Không tự động.** Tầng đó chỉ dành cho chữ ký số do **CA được Bộ TT&TT cấp phép** cấp (VNPT-CA, Viettel-CA, FPT-CA, MISA, FastCA...), hoặc chữ ký điện tử chuyên dùng **đã được chứng nhận bảo đảm an toàn**. |
| Dùng DocuSign thì có lên tầng đó không? | **Không.** DocuSign không phải CA được cấp phép tại VN → cùng tầng pháp lý với phương án tự làm. |
| Thu số CCCD cần gì? | **Luật Bảo vệ dữ liệu cá nhân 2025** (hiệu lực 01/01/2026) + **NĐ 356/2025/NĐ-CP** đã thay `NĐ 13/2023` (hết hiệu lực 01/01/2026). Bắt buộc: đồng ý rõ ràng, nêu đúng mục đích, không dùng sang việc khác, có chính sách xoá. |

> Đây là khảo sát để ra quyết định kỹ thuật, **không phải tư vấn pháp lý**. Nếu về sau shop cho
> thuê thiết bị giá trị lớn, hỏi luật sư trước khi dựa vào mục này.

## 3. Các phương án

| # | Phương án | Ưu | Nhược |
|---|---|---|---|
| A | **Tự host, dựng chuẩn chứng cứ kiểu DocuSign** | 0đ/tháng; chủ động hoàn toàn; ghép thẳng vào dữ liệu đơn/thanh toán đã có | Bằng chứng nằm trên server của shop → phải chủ động bổ sung bản sao bên thứ ba |
| B | **DocuSign eSignature API** | Audit trail chuẩn quốc tế, file niêm phong chống sửa, thương hiệu khách dễ tin | **$600/năm** (gói Starter, 40 envelope/tháng) + $1,25–4,80/envelope vượt hạn mức → ~33.000đ/hợp đồng nếu chạy hết hạn mức, ~131.000đ/hợp đồng nếu shop chỉ ~10 đơn/tháng. Cần go-live certification, thẻ tín dụng quốc tế. **Và không lên được tầng pháp lý cao hơn ở VN.** |
| C | Nhà cung cấp eContract Việt Nam có API (FPT.eContract, VNPT eContract, MISA WeSign) | Được cấp phép ở VN → đạt tầng "tương đương chữ ký tay"; tiếng Việt; thanh toán nội địa | Tài liệu API kém, phải liên hệ sales để lấy giá; chậm một nhịp |
| D | Chỉ HTML, khách tự bấm "In → Lưu PDF" | Không thêm dependency | Shop không có file lưu trữ cố định → yếu nhất về chứng cứ |

## 4. Quyết định

**Chọn A — tự host, nhưng đầu tư vào chuỗi chứng cứ.**

Lý do quyết định: **B tốn ~15,8 triệu đ/năm mà không đổi được tầng pháp lý ở Việt Nam.** Khoản
chênh lệch đó không mua được gì ngoài chất lượng dấu vết — mà dấu vết thì tự dựng được.

### 4.1 Điểm yếu cốt lõi và cách chữa

Toàn bộ bằng chứng do hệ thống của shop tạo ra và shop toàn quyền sửa. Lập luận phản bác hiển
nhiên là *"bên cho thuê tự dựng ra được hết"*. Cách chữa **không phải** làm dấu vết dày hơn trên
server của mình, mà là tạo **bản sao nằm ngoài tầm kiểm soát của shop**:

| Lớp | Bên thứ ba làm chứng | Trạng thái |
|---|---|---|
| Sao kê ngân hàng (nội dung CK = mã đơn) | Ngân hàng | **Nằm ngoài hệ thống** — xem cảnh báo bên dưới |
| PDF đã ký gửi vào hộp thư khách ngay lúc ký (kèm hash, DKIM) | Google/Microsoft | **Làm mới** |
| Ảnh chụp lúc bàn giao đồ | — (bằng chứng thực hiện hợp đồng) | **Làm mới** |
| Xác thực OTP email | — (đã có `EmailOtp`) | **Đã có** — chỉ cần in vào biên bản |

Lớp sao kê ngân hàng là mạnh nhất: khách không thể vừa nói *"tôi không biết đơn này"* vừa giải
thích tại sao chính tài khoản mình chuyển tiền vào đúng mã đơn đó.

> ⚠️ **Nhưng hệ thống KHÔNG nắm dữ liệu này.** `PaymentQrService` chỉ sinh ảnh QR — không
> webhook, không đối soát; admin tự xem sao kê rồi bấm `Order::markPaid()`. Nên số tiền lưu
> trong đơn là **do shop gõ vào**, đúng loại bằng chứng "tự mình tạo ra" mà mục này đang muốn
> tránh. May là bằng chứng thật vẫn tồn tại **độc lập với hệ thống**: nội dung chuyển khoản trên
> QR đã là mã đơn, nên sao kê trong app ngân hàng của shop tự khắc có dòng gắn đúng đơn đó.
> Vì vậy biên bản chứng thực **chỉ đường vào sao kê** (in mã đơn cần tra, số tiền, thời điểm và
> người ghi nhận) chứ không giả vờ chứa sao kê. Muốn hệ thống tự giữ bằng chứng ngân hàng thì
> phải làm webhook SePay — việc riêng, không nằm trong phạm vi này.

### 4.2 Định vị lại vai trò của hợp đồng

Với đơn vài trăm nghìn tới vài triệu, **kiện dân sự không kinh tế** (án phí + thời gian thường
lớn hơn số tiền đòi được). Công cụ thực thi thật sự là **tiền cọc đang giữ**. Vai trò của hợp
đồng là **giữ cọc một cách chính đáng và khiến khách không cãi** — chuỗi bằng chứng ở 4.1 thừa
sức cho việc đó.

Tình huống nặng (khách ôm đồ biến mất) thì đường đi là **tố giác hình sự**, không phải toà dân
sự: Điều 175 BLHS quy định đúng hành vi *thuê tài sản rồi bỏ trốn hoặc cố tình không trả*, áp
dụng từ **giá trị 4 triệu đồng**. Thứ công an cần là hợp đồng chứng minh quan hệ thuê có thời
hạn trả + **số CCCD định danh** + bằng chứng đã giao đồ. Ở đường này tầng pháp lý của chữ ký
gần như không còn là vấn đề — đó là lý do chính khiến việc thu CCCD đáng làm.

### 4.3 Rủi ro chấp nhận

Toà có toàn quyền đánh giá chứng cứ và về lý thuyết có thể cho rằng riêng chữ ký điện tử này
chưa đủ. **Rủi ro này không triệt tiêu được** — nhưng nó tồn tại y hệt với DocuSign ở VN. Cách
duy nhất xoá hẳn là mua chữ ký số của CA Việt Nam.

**Quyết định:** *chưa* làm sẵn lớp trừu tượng để cắm CA sau này (YAGNI — chủ shop đã cân nhắc và
bỏ). Nếu sau này cần, chỗ phải sửa là bước sinh PDF trong `ContractService`, xem mục 6.

## 5. Quyết định công nghệ

### 5.1 Sinh PDF — `barryvdh/laravel-dompdf`

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
cập được, và command xoá số CCCD theo lịch (mục 5.4).

### 5.2 Vẽ chữ ký — `signature_pad`

Đã cân nhắc tự viết canvas (~120 dòng). Chọn thư viện vì xử lý cảm ứng đa thiết bị (pointer
events, làm mượt nét, tỷ lệ DPI, ngăn cuộn trang khi vẽ) nhiều lỗi vặt hơn vẻ ngoài của nó.
~4KB, không phụ thuộc gì khác.

### 5.3 Mẫu hợp đồng — dùng lại hạ tầng sẵn có

Không dựng cơ chế soạn thảo mới. Dùng lại **editor TipTap + `EditorHtml::clean()`** đang phục vụ
`StaticPage`, lưu vào cột mới trên `site_settings` (singleton cấu hình shop đã có).

### 5.4 Dữ liệu cá nhân

- `signer_id_number` dùng cast **`encrypted`** của Laravel — lộ DB cũng không đọc được.
- **Không bao giờ** đưa vào prop Inertia của trang khách; nơi hiển thị ngoài admin che còn 4 số cuối.
- Command chạy theo lịch **xoá số CCCD sau khi đơn đã hoàn cọc xong 90 ngày** (bắt chước `SendPickupReminders`).
- Checkbox đồng ý **riêng**, không gộp vào "đồng ý điều khoản", nêu rõ mục đích: *đối chiếu khi bàn giao và hoàn cọc*.

## 6. Hệ quả

- Thêm 2 dependency: `barryvdh/laravel-dompdf` (PHP), `signature_pad` (JS). Phải **cập nhật bảng golden path** trong `.claude/rules/tech-strategy.md` — dòng PDF đã bị gỡ khi ADR cũ bị Rejected.
- Thêm 3 thay đổi schema: bảng `contracts`, bảng `handover_photos`, cột `contract_template_html` trên `site_settings`.
- Toàn bộ việc render/đóng băng/hash/ký/sinh PDF nằm trong **một** `ContractService` — single source of truth, không chỗ nào khác được tự render hợp đồng.
- Nếu sau này muốn lên tầng "tương đương chữ ký tay": chỗ sửa là bước sinh PDF trong `ContractService` — ký số bản PDF bằng chứng thư của CA Việt Nam trước khi lưu. Không đụng tới schema hay luồng khách.

## 7. Liên quan

- [design_spec_contract_esignature.md](design_spec_contract_esignature.md) — thiết kế chi tiết.
- [adr_pdf_generation.md](adr_pdf_generation.md) — khảo sát dompdf gốc (Rejected, hồi sinh kết luận).
- [adr_sepay_qr_payment.md](adr_sepay_qr_payment.md) — nguồn dữ liệu sao kê cho biên bản chứng thực.
- [adr_shipper_role_and_access.md](adr_shipper_role_and_access.md) — trang shipper, nơi gắn nút ký + upload ảnh bàn giao.
