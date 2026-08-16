{{--
    Mẫu MẶC ĐỊNH của hợp đồng chính — bám nguyên văn hợp đồng giấy 1408/HĐTTB của shop.
    Admin sửa lại ở trang "Mẫu hợp đồng"; bản sửa lưu vào site_settings.contract_template_html
    và ghi đè file này.

    Khối verbatim bao ngoài để Blade KHÔNG nuốt các biến dạng ngoặc nhọn kép — biến ở đây do
    ContractService thay bằng strtr(), không phải do Blade.

    CẢNH BÁO 1: đừng viết tên hai directive verbatim/endverbatim kèm ký tự a-còng ở trong chú
    thích này. Blade gom khối verbatim TRƯỚC khi bỏ chú thích, nên một tên directive lạc
    trong comment sẽ bắt cặp với directive đóng ở cuối file và đẩy NGUYÊN KHỐI CHÚ THÍCH vào
    hợp đồng gửi cho khách. Đã dính đúng lỗi này một lần.

    CẢNH BÁO 2: không đặt chú thích Blade BÊN TRONG khối verbatim bên dưới — verbatim in ra
    nguyên văn, nên chú thích sẽ nằm chình ình trong hợp đồng của khách. Ghi chú gì thì viết
    ở đây. Cũng đã dính một lần.

    Quy ước trình bày: dấu câu đi liền sau chữ in đậm phải nằm BÊN TRONG thẻ (viết
    "<strong>... .</strong>" chứ không phải "<strong>...</strong>."). dompdf coi ranh giới
    thẻ inline là chỗ được ngắt dòng, nên dấu chấm dễ bị đẩy xuống thành một dòng riêng.

    BỐN CHỖ CỐ Ý KHÁC BẢN GIẤY (xem mục 8 design_spec_contract_esignature.md):
      1. Ghi chú CCCD: bản giấy ghi "không giữ bản gốc CCCD", nhưng quy trình mới CÓ lưu ảnh
         2 mặt — phải nói đúng phạm vi lưu và thời hạn xoá (Luật BVDLCN 2025).
      2. Điều 1 dùng {{bang_thiet_bi}} — bảng này CÓ cột giá trị đền bù bằng tiền, thứ bản
         giấy thiếu (Điều 6.3 bắt đền "100% giá trị theo bảng Điều 1" nhưng bảng chỉ ghi
         "15-90%", không có con số gốc nào để nhân vào).
      3. Điều 3.2: cọc thu TRƯỚC khi nhận thiết bị, khớp quy trình thật (bản giấy ghi "khi
         nhận thiết bị").
      4. Điều 10: nói rõ hợp đồng giao kết dạng điện tử, hai Bên cùng giữ bản PDF.
--}}
@verbatim
<h2 style="text-align:center">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</h2>
<p style="text-align:center"><strong>Độc lập – Tự do – Hạnh phúc</strong></p>
<h2 style="text-align:center">HỢP ĐỒNG THUÊ THIẾT BỊ CAMPING</h2>
<p style="text-align:center">Số: {{so_hop_dong}}</p>

<p>Căn cứ Bộ luật Dân sự số 91/2015/QH13 (Điều 472 đến Điều 482 về Hợp đồng thuê tài sản);
Luật Bảo vệ quyền lợi người tiêu dùng số 19/2023/QH15; Luật Giao dịch điện tử số
20/2023/QH15; và sự thỏa thuận giữa hai Bên.</p>

<h3>BÊN CHO THUÊ (BÊN A)</h3>
<p>- Hộ kinh doanh: BỐP CAMPING (đại diện: Ông Phạm Văn Khánh)<br>
- Mã số hộ kinh doanh: 040202015437<br>
- Địa chỉ: Khối Phong Vinh, phường Vinh Lộc, Nghệ An<br>
- Điện thoại: 0976544370 — Email: kpham2874@gmail.com</p>

<h3>BÊN THUÊ (BÊN B)</h3>
<p>- Họ và tên: {{ten_khach}}<br>
- CCCD số: {{cccd_khach}} — Ngày cấp: {{ngay_cap}} — Nơi cấp: {{noi_cap}}<br>
- Địa chỉ liên hệ: {{dia_chi_khach}}<br>
- Điện thoại: {{sdt_khach}}</p>
<p><em>(Bên A chỉ ghi nhận thông tin CCCD của Bên B để đối chiếu khi bàn giao và hoàn cọc;
không giữ bản gốc và không lưu ảnh chụp CCCD.)</em></p>

<p>Hai Bên thống nhất ký kết Hợp đồng thuê thiết bị camping với các nội dung sau:</p>

<h3>ĐIỀU 1. ĐỐI TƯỢNG THUÊ</h3>
<p>Bên A đồng ý cho Bên B thuê các thiết bị camping với danh mục, số lượng và giá trị đền bù
như sau (tình trạng cụ thể của từng thiết bị được ghi nhận tại Biên bản bàn giao – Phụ lục A):</p>
{{bang_thiet_bi}}
<p><em>Giá trị đền bù nêu trên là căn cứ tính bồi thường tại Điều 6.</em></p>

<h3>ĐIỀU 2. THỜI HẠN THUÊ</h3>
<p>2.1. Thời gian nhận thiết bị: {{ngay_nhan}}<br>
2.2. Thời gian trả thiết bị: {{ngay_tra}}<br>
2.3. Tổng số ngày thuê: {{so_ngay_thue}} ngày.</p>

<h3>ĐIỀU 3. GIÁ THUÊ VÀ THANH TOÁN</h3>
<p>3.1. Tổng số tiền Bên B thanh toán cho BỐP CAMPING: <strong>{{tong_tien}}.</strong></p>
<p>3.2. Tiền đặt cọc: <strong>{{tien_coc}},</strong> thanh toán bằng tiền mặt hoặc chuyển khoản
<strong>trước khi nhận thiết bị.</strong> Tiền đặt cọc được hoàn trả cho Bên B ngay sau 24h kể
từ khi Bên A kiểm tra thiết bị trả về đủ số lượng, không hư hỏng bất thường (trừ hao mòn tự
nhiên do sử dụng đúng cách).</p>
<p>3.3. Trường hợp có phát sinh phí phạt trễ hạn, bồi thường hư hỏng/mất mát theo Điều 6, Bên A
được quyền khấu trừ trực tiếp vào tiền đặt cọc; nếu tiền đặt cọc không đủ, Bên B có trách nhiệm
thanh toán bổ sung phần còn thiếu trong vòng 03 (ba) ngày.</p>

<h3>ĐIỀU 4. TRÁCH NHIỆM CỦA BÊN THUÊ (BÊN B)</h3>
<p>a) Kiểm tra tình trạng thiết bị cùng Bên A khi nhận, ký xác nhận vào Biên bản bàn giao
(Phụ lục A); nếu không có ý kiến, coi như thiết bị đã nhận đủ và đúng như mô tả;<br>
b) Sử dụng thiết bị đúng mục đích, đúng công năng, bảo quản cẩn thận trong suốt thời gian thuê;<br>
c) Không cho thuê lại, chuyển nhượng, cầm cố thiết bị cho bất kỳ bên thứ ba nào dưới mọi hình thức;<br>
d) Chịu trách nhiệm bồi thường theo Điều 6 nếu thiết bị bị hư hỏng, mất mát trong thời gian
thuê, trừ trường hợp do lỗi kỹ thuật của thiết bị (lỗi có từ trước, không do Bên B gây ra);<br>
e) Trả thiết bị đúng thời hạn, đúng địa điểm quy định tại Điều 2.</p>

<h3>ĐIỀU 5. TRÁCH NHIỆM CỦA BÊN CHO THUÊ (BÊN A)</h3>
<p>a) Bàn giao thiết bị đúng số lượng, chất lượng, hoạt động bình thường như đã thỏa thuận;<br>
b) Hướng dẫn Bên B cách sử dụng cơ bản, an toàn đối với các thiết bị có yêu cầu kỹ thuật
(bếp gas, đèn, v.v.);<br>
c) Trường hợp thiết bị phát sinh lỗi kỹ thuật không do lỗi của Bên B trong thời gian thuê,
Bên A có trách nhiệm hỗ trợ đổi/sửa thiết bị hoặc hoàn lại phần tiền thuê tương ứng với thời
gian không sử dụng được;<br>
d) Hoàn trả tiền đặt cọc cho Bên B theo đúng quy định tại Khoản 3.2.</p>

<h3>ĐIỀU 6. XỬ LÝ TRẢ TRỄ, HƯ HỎNG, MẤT MÁT THIẾT BỊ</h3>
<p>6.1. Trả trễ hạn: Bên B phải thanh toán phí phạt trễ hạn bằng 10% đơn giá thuê/ngày của
thiết bị tương ứng, tính cho mỗi ngày trễ hạn, kể từ thời điểm quy định tại Khoản 2.2.</p>
<p>6.2. Hư hỏng thiết bị: nếu thiết bị bị hư hỏng do lỗi sử dụng của Bên B, Bên B có trách
nhiệm chi trả chi phí sửa chữa thực tế; nếu không thể sửa chữa, Bên B bồi thường từ 15% đến
90% giá trị đền bù ghi tại Điều 1, tùy mức độ hư hỏng do hai Bên thống nhất tại Biên bản nhận
lại thiết bị (Phụ lục B).</p>
<p>6.3. Mất thiết bị: nếu thiết bị bị mất trong thời gian thuê, Bên B bồi thường 100% giá trị
đền bù ghi tại Điều 1.</p>
<p>6.4. Hao mòn tự nhiên do sử dụng đúng cách, đúng công năng không bị coi là hư hỏng và không
bị tính phí bồi thường.</p>

<h3>ĐIỀU 7. CHẤM DỨT HỢP ĐỒNG TRƯỚC HẠN</h3>
<p>7.1. Bên B có thể trả thiết bị và chấm dứt Hợp đồng trước thời hạn thuê đã thỏa thuận;
trong trường hợp này, Bên B không được hoàn lại phần tiền thuê tương ứng với thời gian chưa
sử dụng, trừ khi Hai Bên có thỏa thuận khác.</p>
<p>7.2. Bên A có quyền chấm dứt Hợp đồng và yêu cầu Bên B hoàn trả thiết bị ngay nếu phát hiện
Bên B vi phạm nghiêm trọng nghĩa vụ tại Điều 4 (ví dụ: cho thuê lại thiết bị cho bên thứ ba).</p>

<h3>ĐIỀU 8. BẢO MẬT THÔNG TIN KHÁCH HÀNG</h3>
<p>Bên A cam kết chỉ sử dụng thông tin cá nhân của Bên B (họ tên, số CCCD, số điện thoại, địa
chỉ) để phục vụ việc đối chiếu và thực hiện Hợp đồng này, không chia sẻ cho bên thứ ba vì mục
đích khác. Bên A không lưu ảnh chụp CCCD của Bên B. Việc xử lý dữ liệu tuân theo Luật Bảo vệ
quyền lợi người tiêu dùng và pháp luật về bảo vệ dữ liệu cá nhân hiện hành.</p>

<h3>ĐIỀU 9. GIẢI QUYẾT TRANH CHẤP</h3>
<p>Mọi tranh chấp phát sinh trước hết do Hai Bên thương lượng, hòa giải. Trường hợp không giải
quyết được, tranh chấp được đưa ra Tòa án nhân dân có thẩm quyền giải quyết theo pháp luật
Việt Nam.</p>

<h3>ĐIỀU 10. HIỆU LỰC HỢP ĐỒNG</h3>
<p>Hợp đồng này được giao kết dưới <strong>hình thức điện tử</strong> theo Luật Giao dịch điện
tử 2023 và có hiệu lực từ thời điểm Hai Bên ký xác nhận điện tử. Hai Bên cùng giữ bản PDF có
giá trị pháp lý như nhau. Biên bản bàn giao (Phụ lục A) và Biên bản nhận lại thiết bị
(Phụ lục B) là bộ phận không tách rời của Hợp đồng này.</p>
@endverbatim
