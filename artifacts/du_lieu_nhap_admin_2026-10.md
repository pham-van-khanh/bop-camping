# Dữ liệu nhập admin — đợt SEO & Marketing 10/2026

Bản đọc của `artifacts/data/du_lieu_seo_marketing_2026-10.json`. Bản có nút sao chép: trang Sổ tay SEO (link trong `artifacts/pages/INDEX.md`).

## Giới thiệu khu vực cho 2 kho (Nghệ An, Hà Nội): đoạn văn, gợi ý đổi tên, link Google Maps

### Kho Nghệ An (slug nghe-an) - trang /thue-do-cam-trai/nghe-an

**Giới thiệu khu vực (intro)** — https://bopcamping.com/admin/camping-spots → menu trái "Điểm cắm trại" → khối "Vị trí phục vụ" ở đầu trang → rê chuột vào ô "Nghệ An" cho hiện 2 nút → bấm biểu tượng bút chì "Sửa" → ô "Giới thiệu khu vực" → "Lưu lại". Trên điện thoại: 2 nút bị ẩn, chạm vào ô tên kho cho hiện rồi mới bấm bút chì. Nút thùng rác đỏ ngay bên cạnh là "Xoá", đừng bấm nhầm; lỡ bấm thì hộp xác nhận hiện ra, chọn "Huỷ".

```
Thuê đồ cắm trại theo ngày ở Vinh, Cửa Lò, Nghi Lộc (Nghệ An): lều, tăng bạt, ghế, bàn gấp, bếp nướng BBQ, đệm hơi. Trả tiền khi nhận đồ (COD).

Có giao tận nơi hoặc tự đến kho: bạn chọn “Tự đến xem đồ” (xem đồ tại kho, miễn phí) hoặc “Nhận tại địa điểm” (tụi mình giao tới chỗ hẹn, báo phí khi gọi xác nhận). Kho ở Đường 72M, Khối Phong Vinh, phường Vinh Lộc, mở 8:00 - 21:00 hàng ngày. Cọc trả cùng tiền thuê, hoàn đủ khi bạn trả đồ đúng hẹn, như lúc nhận.

Combo ở kho này: Relax, BBQ Party, Camping Couples, Camping Family, Camping Standard, Picnic Family 1. Điểm hay đi quanh Vinh: núi Lam Thành, Eo Gió, đồi sim Bãi Lữ (xem gợi ý ở mục Cẩm nang); bạn nên hỏi trước vé và quy định dựng lều.
```

> Dài 695 ký tự (đếm bằng PHP, gồm cả dấu xuống dòng). Dán nguyên văn và giữ 2 dòng trống: trang giữ chỗ xuống dòng, còn Google gộp thành một dòng.  Mô tả Google (meta): vì toàn bài ≥ 120 ký tự, code lấy 160 ký tự đầu rồi thêm "...". Đã chạy thử đúng Str::limit trên đoạn này, kết quả: "Thuê đồ cắm trại theo ngày ở Vinh, Cửa Lò, Nghi Lộc (Nghệ An): lều, tăng bạt, ghế, bàn gấp, bếp nướng BBQ, đệm hơi. Trả tiền khi nhận đồ (COD). Có giao tận nơi..." Mô tả vẫn kết bằng "...". Muốn kết gọn ở "(COD)." thì làm theo gợi ý code ở cuối pack. Khi sửa về sau, bạn chỉ cần giữ đoạn đầu là MỘT câu trọn ý, dài khoảng 120–150 ký tự, có "Vinh" và "thuê đồ cắm trại". Chỗ cắt không quan trọng.  Nguồn từng câu: địa chỉ, giờ, cọc, COD lấy từ dữ liệu shop. Hai cách nhận đồ và phần trong ngoặc lấy theo nhãn + gợi ý ở trang thanh toán (Order::DELIVERY_METHOD_LABELS / DELIVERY_METHOD_HINTS). Câu hoàn cọc chép đúng ý chính sách trên web: "hoàn lại đầy đủ khi bạn trả thiết bị đúng hẹn và trong tình trạng như lúc nhận". 6 combo là đúng 6 combo đang gán cho kho Nghệ An; kho này không có lều cabin Village 13.  Combo: trang khu vực chỉ hiện 4 thẻ combo (chọn theo thứ tự ưu tiên rồi theo tên). Muốn xem đủ thì bấm nút "Combo thuê trọn bộ" hoặc "Tất cả combo" (/combos?vi-tri=nghe-an). Vì vậy đoạn này kể đủ tên cả 6 combo.  Ba điểm cắm trại đều nằm trong bài /cam-nang/diem-cam-trai-gan-vinh-nghe-an và đã kiểm lại trên báo/trang du lịch. Núi Lam Thành (Hưng Nguyên) cách Vinh khoảng 15 km. Khu sinh thái Eo Gió (Nam Đàn) cách Vinh khoảng 10 km. Đồi sim Bãi Lữ thuộc Nghi Yên, Nghi Tiến (Nghi Lộc). Không nêu biển Cửa Lò vì nội quy bãi tắm cấm trải bạt ăn uống. Không có link vì Nghệ An chưa có trang điểm cắm trại nào. Lam Thành và Eo Gió nằm ngoài vùng giao (Vinh, Cửa Lò, Nghi Lộc), nên đoạn này chỉ ghi "Có giao tận nơi" mà không hứa giao tới đó.  Bài Cẩm nang chưa lên production (13/10/2026 còn 404). Nếu deploy mà bài chưa đăng thì chỉ xoá cụm "(xem gợi ý ở mục Cẩm nang)". Tên 3 điểm vẫn giữ, vì đã kiểm độc lập.  Đoạn này không tự cập nhật. Khi đổi giờ mở cửa hoặc gán/bỏ combo cho kho, nhớ sửa lại. Không dán link vào ô này vì ô chỉ hiện chữ thường. KHÔNG đụng ô "Slug" (giữ nghe-an).

### Kho Hà Nội (slug ha-noi) - trang /thue-do-cam-trai/ha-noi

**Giới thiệu khu vực (intro)** — https://bopcamping.com/admin/camping-spots → menu trái "Điểm cắm trại" → khối "Vị trí phục vụ" ở đầu trang → rê chuột vào ô "Hà Nội" cho hiện 2 nút → bấm biểu tượng bút chì "Sửa" → ô "Giới thiệu khu vực" → "Lưu lại". Trên điện thoại: chạm vào ô tên kho cho hiện 2 nút rồi mới bấm bút chì. Nút thùng rác đỏ ngay bên cạnh là "Xoá", đừng bấm nhầm (lỡ bấm thì chọn "Huỷ" ở hộp xác nhận).

```
Thuê đồ cắm trại theo ngày ở nội thành Hà Nội: lều 4 người và lều cabin 6-8 người, tăng bạt, ghế, bàn gấp, bếp BBQ. Trả tiền khi nhận đồ (COD).

Có giao tận nơi hoặc tự đến kho: bạn chọn “Tự đến xem đồ” (xem đồ tại kho, miễn phí) hoặc “Nhận tại địa điểm” (tụi mình giao tới chỗ hẹn, báo phí khi gọi xác nhận). Kho ở tòa nhà Homeland, phường Bồ Đề (quận Long Biên cũ), mở 8:00 - 21:00 hàng ngày. Cọc trả cùng tiền thuê, hoàn đủ khi bạn trả đồ đúng hẹn, như lúc nhận.

Kho Hà Nội có đủ 9 combo, trong đó Camping Family 2, Picnic Family 2 và Chill Couples chỉ có ở đây. Điểm cắm trại nội thành: Công viên Yên Sở, bãi đá sông Hồng (Nhật Tân); bạn nên hỏi trước giờ mở cửa, vé và quy định dựng lều.
```

> Dài 693 ký tự. Dán nguyên văn và giữ 2 dòng trống.  Mô tả Google (meta), đã chạy thử: "Thuê đồ cắm trại theo ngày ở nội thành Hà Nội: lều 4 người và lều cabin 6-8 người, tăng bạt, ghế, bàn gấp, bếp BBQ. Trả tiền khi nhận đồ (COD). Có giao tận nơi..." Mô tả vẫn kết bằng "..." cho tới khi sửa code như gợi ý cuối pack. Khi sửa về sau, chỉ cần giữ đoạn đầu là một câu trọn ý dài 120–150 ký tự.  "Lều cabin 6-8 người" là lều cabin Village 13, chỉ có ở kho Hà Nội. Viết "6-8" không có dấu cách để đoạn đầu vừa 143 ký tự.  Combo: kho Hà Nội được gán cả 9 combo, 3 combo nêu tên là 3 combo chỉ gán cho Hà Nội. Trang khu vực chỉ hiện 4 thẻ combo (chọn theo thứ tự ưu tiên rồi theo tên), nên 3 combo riêng này có thể không lọt vào. Xem đủ ở nút "Combo thuê trọn bộ" hoặc "Tất cả combo" (/combos?vi-tri=ha-noi). Muốn 3 combo riêng của Hà Nội hiện thành thẻ thì đẩy chúng lên đầu thứ tự ưu tiên trong /admin/combos. Lưu ý: thứ tự này dùng chung cho cả trang combo.  Địa chỉ: ghi "quận Long Biên cũ" chứ không ghi "Long Biên cũ", vì từ 1/7/2025 Hà Nội có thêm một "phường Long Biên" mới. Theo Nghị quyết 1656/NQ-UBTVQH15, phường Bồ Đề mới được ghép từ (một phần) các phường cũ thuộc quận Long Biên.  Điểm cắm trại: 2 điểm nêu tên là đúng 2 trang điểm cắm trại đang gán "Gần vị trí phục vụ = Hà Nội", nên cũng hiện thành thẻ trên trang. Đã kiểm: Công viên Yên Sở miễn vé, mở tới 19:00, muốn ở qua đêm phải xin phép ban quản lý (Mia.vn). Bãi đá sông Hồng thì nguồn mâu thuẫn: Đại biểu Nhân dân đầu năm 2024 đưa tin thu 70.000đ/người lớn, 50.000đ/trẻ em ở cổng mà không có vé; một hướng dẫn du lịch năm 2025 lại ghi picnic, cắm trại ở đây miễn phí. Vì thế câu cuối chỉ nhắc khách hỏi trước, không ghi con số. Không nhắc Thung Trâu vì chưa xác minh được điểm này và chưa gán kho nào (xem phần gợi ý).  Đoạn này không tự cập nhật khi đổi giờ hoặc gán/bỏ combo. KHÔNG đụng ô "Slug" (giữ ha-noi).

### Kho Nghệ An (slug nghe-an) - đổi tên (tuỳ chọn, khuyên làm)

**Tên vị trí** — https://bopcamping.com/admin/camping-spots → khối "Vị trí phục vụ" → ô "Nghệ An" (điện thoại: chạm vào ô cho hiện nút) → bút chì "Sửa" → ô "Tên vị trí *" → "Lưu lại". Giữ nguyên ô "Slug (đường dẫn trang khu vực)" = nghe-an.

```
Vinh, Nghệ An
```

> Khuyên đổi.  Tên này đi vào title trang thành "Thuê đồ cắm trại tại Vinh, Nghệ An theo ngày | BỐP CAMPING". Title dài 58 ký tự (đã đếm), dưới 60 nên Google không cắt. Tên cũng vào H1, breadcrumb và schema LocalBusiness "BỐP CAMPING - Vinh, Nghệ An". Nhờ vậy trang bắt được cả "thuê đồ cắm trại Vinh" lẫn "… Nghệ An". Nên dùng dấu phẩy, không dùng "Vinh – Nghệ An": nếu dùng gạch, schema thành "BỐP CAMPING - Vinh – Nghệ An", hai dấu gạch trông như lỗi.  LỢI: (1) Người ở đây gõ "Vinh" khi tìm. Từ 1/12/2024, TP Vinh nhập thị xã Cửa Lò và 4 xã của Nghi Lộc. Từ 1/7/2025, vùng này chia thành 6 phường, 5 phường có chữ "Vinh"; kho nằm ở phường Vinh Lộc. (2) Tên nói đúng vùng shop phục vụ. Tên "Nghệ An" dễ làm khách ở Diễn Châu, Quỳnh Lưu… tưởng shop giao tới chỗ họ.  HẠI: (1) Tên dài hơn trên badge thẻ sản phẩm/combo, chip lọc, ô chọn kho lúc đặt và đơn cũ trong admin. Đơn chỉ nối với kho bằng ID nên không có gì hỏng. Món có ở cả 2 kho vẫn hiện "Toàn hệ thống". (2) Giỏ hàng khách đã lưu từ trước có thể còn hiện tên cũ "Nghệ An" tới khi họ thêm món mới. Đây chỉ là chữ hiển thị. (3) Mất sự đối xứng tỉnh/tỉnh với "Hà Nội". Ảnh hưởng nhỏ, vì Hà Nội là thành phố nên đọc vẫn tự nhiên.  ĐIỀU BẮT BUỘC: ô Slug phải giữ "nghe-an". Form đã điền sẵn nên chỉ cần đừng xoá. Nếu xoá trống, hệ thống tự sinh slug mới "vinh-nghe-an"; khi đó link cũ /thue-do-cam-trai/nghe-an thành 404 và mọi link ?vi-tri=nghe-an mất bộ lọc.  Đoạn giới thiệu ở trên không phụ thuộc tên kho nên giữ nguyên, đổi tên hay không cũng được.

**Gợi ý**

- Nên đổi tên kho "Nghệ An" thành "Vinh, Nghệ An" (lợi/hại chi tiết ở mục thứ 3). Tóm tắt: title, H1 và schema sẽ có cả "Vinh" lẫn "Nghệ An", title dài 58 ký tự nên không bị cắt, link không đổi. Điều kiện duy nhất: KHÔNG xoá ô Slug (giữ nghe-an). Không đổi cũng ổn: đoạn giới thiệu và mô tả Google đều đã mở đầu bằng "Vinh, Cửa Lò, Nghi Lộc", nên trang vẫn có chữ "Vinh", chỉ thiếu ở title và H1.
- Điền "Link Google Maps" cho CẢ HAI kho. Khi ô này trống, trang khu vực không có nút "Mở bản đồ", schema LocalBusiness thiếu hasMap, và khách chọn "Tự đến xem đồ" ở trang thanh toán không có link chỉ đường. Cách làm: (1) Mở Google Maps (app hoặc maps.google.com). Nếu shop đã có hồ sơ Google Business thì gõ tên shop. Nếu chưa có, gõ địa chỉ ("Đường 72M, Khối Phong Vinh, Vinh Lộc, Nghệ An" hoặc "Homeland, Bồ Đề, Hà Nội") rồi nhấn giữ đúng cổng kho để thả ghim; đừng lấy ghim chung của cả toà nhà hay cả phường. (2) Bấm "Chia sẻ" → "Sao chép đường liên kết" (link dạng https://maps.app.goo.gl/...). (3) Vào https://bopcamping.com/admin/camping-spots → khối "Vị trí phục vụ" → bút chì "Sửa" trên ô kho (điện thoại: chạm vào ô cho hiện nút; đừng bấm nhầm thùng rác "Xoá" bên cạnh) → dán vào ô "Link Google Maps" → "Lưu lại". Ô này chỉ nhận link bắt đầu bằng http:// hoặc https://. (4) Mở /thue-do-cam-trai/nghe-an và /thue-do-cam-trai/ha-noi, bấm "Mở bản đồ" để chắc là mở đúng chỗ.
- Nên làm thêm (việc tay, ngoài admin): tạo và xác minh hồ sơ Google Business Profile cho từng kho tại business.google.com. Dùng đúng tên, địa chỉ, hotline 0373655008 / 0976544370 và giờ 8:00 - 21:00 như trên web. Có hồ sơ thì shop mới hiện trên bản đồ khi khách tìm "thuê đồ cắm trại Vinh" hay "thuê lều Hà Nội". Khi đã có hồ sơ, lấy link "Chia sẻ" của chính hồ sơ đó dán vào ô "Link Google Maps", thay cho ghim thả tay.
- Thung Trâu (trang /diem-cam-trai/thung-trau): kiểm lại TRƯỚC, gán kho SAU. Tra trên mạng không thấy tài liệu nào về một điểm cắm trại tên "Thung Trâu" ở Kim Bôi. Vì vậy bước 1 là xác nhận lại tên thật và vị trí, rồi thêm link Google Maps cho điểm này. Bước 2: chỉ khi đã chắc đây là điểm khách Hà Nội hay đi, mới vào /admin/camping-spots → sửa "Thung Trâu" → ô "Gần vị trí phục vụ" chọn "Hà Nội" → lưu, để điểm hiện thành thẻ trên trang Hà Nội. Tham khảo: một bài báo ghi khoảng 2 tiếng lái xe từ Hà Nội tới Serena Resort Kim Bôi. Đó là quãng tới khu resort, không phải tới điểm này, nên đừng ghi con số lên trang. Từ 1/7/2025 Kim Bôi thuộc tỉnh Phú Thọ (Hòa Bình cũ). Có thể sửa ô "Tỉnh / Thành" thành "Phú Thọ (Hòa Bình cũ)" cho khớp tên mới, vì trang /diem-cam-trai gom điểm theo tỉnh. Đổi lại, title trang điểm sẽ vượt 65 ký tự và tự rút về dạng ngắn "Cắm trại ở Thung Trâu, Phú Thọ (Hòa Bình cũ) | BỐP CAMPING" (mất cụm "mùa đẹp, đồ cần mang").
- Kho Nghệ An chưa có trang điểm cắm trại nào. Ứng viên sẵn có là các điểm đã kiểm trong bài Cẩm nang: núi Lam Thành, Eo Gió, đồi sim Bãi Lữ. Khi thêm điểm trong /admin/camping-spots, chọn "Gần vị trí phục vụ" = Nghệ An. Trang điểm chỉ bị ẩn khỏi Google (noindex) khi vừa không có ảnh nào vừa có mô tả dưới 200 ký tự; nên có cả hai cho chắc. Điểm đã gán kho sẽ tự hiện thành thẻ trên trang kho Nghệ An.
- Sau khi lưu: mở 2 trang kho để kiểm chữ hiển thị. Rồi vào Google Search Console → "Kiểm tra URL" → dán https://bopcamping.com/thue-do-cam-trai/nghe-an và https://bopcamping.com/thue-do-cam-trai/ha-noi → "Yêu cầu lập chỉ mục", để Google lấy mô tả mới sớm hơn. Google có thể tự viết lại mô tả theo từ khoá khách gõ; chuyện này bình thường.
- Gợi ý cho phía code (không phải việc của chủ shop): (a) Hiện mô tả Google của trang khu vực là 160 ký tự đầu của cả đoạn giới thiệu, cắt cứng rồi thêm "...", nên luôn kết bằng một mẩu câu dở. Nên sửa LocalLandingController::areaDescription: nếu đoạn đầu tiên (trước dòng trống) dài ≥ 120 ký tự thì dùng nguyên đoạn đó, không thì giữ cách cắt 160 như hiện tại. Thêm test cho cả hai nhánh. Với 2 đoạn ở trên, meta sẽ kết gọn ở "(COD).". (b) Câu tự sinh của trang điểm (spotDescription) và câu dự phòng của areaDescription khi kho chưa có danh mục đang ghi "túi ngủ" (và "đèn trại"), là những món shop không cho thuê. Nên đổi sang các món thật như lều, tăng bạt, ghế, bếp nướng BBQ.

## Điểm cắm trại: mô tả mới cho 3 điểm đang có và 6 điểm mới (4 ở Nghệ An, 2 ở Hà Nội), bản đã sửa

### Công viên Yên Sở (đang có trên production)

**Mô tả** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → dòng 'Công viên Yên Sở' → nút 'Sửa' → hộp 'Sửa điểm cắm trại' → ô 'Mô tả' → bấm 'Lưu lại'

```
Vào cổng miễn phí, rộng khoảng 323 ha quanh hồ điều hòa ở phía nam Hà Nội; hợp picnic và cắm trại trong ngày cho gia đình có trẻ nhỏ.
- Đường đi: lối vào ở phía Quốc lộ 1A, cạnh khu Gamuda. Có xe buýt tuyến 04 và 06A. Gửi xe máy khoảng 5.000đ, ô tô khoảng 30.000đ/lượt (giá có thể đổi).
- Giờ giấc: mở cửa 5:00 – 19:00. Muốn ngủ lại qua đêm hoặc đốt lửa trại, bạn phải liên hệ và đăng ký với ban quản lý từ trước.
- Chỗ dựng lều: chọn bãi cỏ bằng phẳng, thoáng, xa mép hồ; nhặt sạch vật sắc trên cỏ trước khi trải bạt.
- Tiện ích: có thuê xe đạp, đạp vịt, khu vui chơi trẻ em (tính phí riêng). Bên trong gần như không có quán ăn, nên mang sẵn đồ ăn và nước.
- Thời điểm: mùa xuân, mùa thu dễ chịu nhất; trong ngày nên đến buổi sáng hoặc chiều mát. Cuối tuần đông, đến sớm để chọn chỗ.
- Mang túi đựng rác và dọn sạch trước khi về.
- Thuê lều, tăng bạt, ghế, bàn gấp, bếp nướng theo ngày ở kho Hà Nội của BỐP CAMPING, giao nhận hoặc tự đến lấy, trả tiền khi nhận.
```

> Xoá hết nội dung cũ rồi dán. 962 ký tự. Mô tả Google (meta) do code tự sinh: 'Công viên Yên Sở (Phường Yên Sở, Hoàng Mai cũ, Hà Nội): ven hồ, mùa đẹp cả năm.' rồi khoảng 77 ký tự đầu của Mô tả, cắt ở 160 ký tự. Vì vậy điểm mạnh nhất (miễn phí, 323 ha) được đặt ở đầu Mô tả. Nguồn: VinWonders 12/12/2025 (323 ha, miễn phí, 5:00–19:00, qua đêm/lửa trại phải đăng ký, xe buýt 04 và 06A, gửi xe 5.000/30.000đ, không có quán ăn, mùa xuân/thu); Green SM (xe đạp, đạp vịt, khu vui chơi trẻ em tính phí, cuối tuần đông). Giữ nguyên Tên, Slug, Tỉnh, Loại địa hình, Link bản đồ, Mùa đẹp.

**Khu vực (thành phố/huyện)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → dòng 'Công viên Yên Sở' → nút 'Sửa' → hộp 'Sửa điểm cắm trại' → ô 'Khu vực (thành phố/huyện)' → bấm 'Lưu lại'

```
Phường Yên Sở, Hoàng Mai cũ
```

> Từ 01/7/2025 Hà Nội bỏ cấp quận. VinWonders 12/2025 ghi công viên thuộc phường Yên Sở; theo NQ 1656/NQ-UBTVQH15, phường Yên Sở mới gồm cả phần còn lại của Hoàng Liệt. Không dùng dấu ngoặc vì meta tự đặt khu vực trong ngoặc. Giữ giá trị ngắn để còn chỗ cho Mô tả trong meta.

### Bãi đá sông Hồng (Nhật Tân) (đang có trên production)

**Tên điểm** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → dòng 'Bãi đá sông Hồng (Nhật Tân)' → nút 'Sửa' → hộp 'Sửa điểm cắm trại' → ô 'Tên điểm' → bấm 'Lưu lại'

```
Bãi đá sông Hồng Nhật Tân
```

> KHÔNG BẮT BUỘC. Bỏ dấu ngoặc trong tên để meta Google không thành 'Bãi đá sông Hồng (Nhật Tân) (Phường Hồng Hà, …)', tức hai cặp ngoặc liền nhau. KHÔNG sửa ô Slug: slug giữ nguyên 'bai-da-song-hong-nhat-tan', nên link /diem-cam-trai/bai-da-song-hong-nhat-tan không đổi. Nếu muốn giữ tên cũ thì bỏ qua dòng này, chấp nhận meta có hai cặp ngoặc.

**Mô tả** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → dòng 'Bãi đá sông Hồng (Nhật Tân)' → nút 'Sửa' → hộp 'Sửa điểm cắm trại' → ô 'Mô tả' → bấm 'Lưu lại'

```
Bãi đá ven sông Hồng cuối ngõ 264 Âu Cơ, gần chân cầu Nhật Tân, hợp picnic, cắm trại ban ngày và ngắm hoàng hôn trên sông.
- Đường đi: từ đường Võ Chí Công rẽ vào Âu Cơ, đi đến cuối ngõ 264. Khu này nay thuộc phường Hồng Hà.
- Phí: ngồi picnic trên bãi đá ven sông không cần vé; vào vườn hoa thì mua vé, bảng giá tham khảo 70.000đ/người lớn ban ngày, rẻ hơn sau 18:00. Gửi xe máy khoảng 5.000đ. Trả tiền vé hay gửi xe thì nhớ lấy vé.
- Qua đêm: nên đi về trong ngày; muốn ở lại, hỏi trước người quản lý.
- Mùa đẹp: cuối tháng 11 – đầu tháng 12 là mùa cúc họa mi. Mùa thu có cỏ lau, mùa xuân có đào, cải vàng; tháng 1–2 vườn dâu bên cạnh chín.
- Lưu ý: khu vườn nằm ngoài đê, tháng 10/2025 từng ngập sâu khi nước sông Hồng dâng sau bão. Mùa mưa bão, xem tin mực nước trước khi đi. Tránh khu có biển cấm, trông trẻ nhỏ và không uống rượu bia khi ở gần mép sông.
- Thuê lều, tăng bạt, ghế, bàn gấp, bếp nướng theo ngày ở kho Hà Nội của BỐP CAMPING, giao nhận hoặc tự đến lấy, trả tiền khi nhận.
```

> Xoá hết nội dung cũ rồi dán. 991 ký tự. Mô tả Google (meta) do code tự sinh: 'Bãi đá sông Hồng Nhật Tân (Phường Hồng Hà, Tây Hồ cũ, Hà Nội): ven sông, mùa đẹp cả năm.' rồi khoảng 68 ký tự đầu của Mô tả, cắt ở 160 ký tự. Nguồn: Green SM (ngõ 264 Âu Cơ, gần chân cầu Nhật Tân, Võ Chí Công → Âu Cơ, bảng giá 70.000đ người lớn trước 18h / 30.000đ sau 18h, gửi xe 5.000đ, dâu chín tháng 1–2); MIA.vn 01/2025 (bãi đá không cần vé, cúc họa mi cuối tháng 11 – đầu tháng 12, cỏ lau mùa thu, đào/cải vàng mùa xuân, biển cấm, hạn chế rượu bia ở rìa sông); VTC News 15/10/2025 (ngập sâu sau bão số 10, 11; phường Hồng Hà); Đại biểu Nhân dân 21/01/2026 (đã phục hồi, đón khách Tết); Đại biểu Nhân dân 2024 (có lúc thu tiền cổng mà không đưa vé, nên Mô tả nhắc lấy vé). Giá vé đổi theo mùa, nên xem lại mỗi năm.

**Khu vực (thành phố/huyện)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → dòng 'Bãi đá sông Hồng (Nhật Tân)' → nút 'Sửa' → hộp 'Sửa điểm cắm trại' → ô 'Khu vực (thành phố/huyện)' → bấm 'Lưu lại'

```
Phường Hồng Hà, Tây Hồ cũ
```

> VTC News 15/10/2025 ghi vườn hoa bãi đá thuộc phường Hồng Hà. Địa chỉ ngõ 264 Âu Cơ đã có trong Mô tả.

**Loại địa hình** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → dòng 'Bãi đá sông Hồng (Nhật Tân)' → nút 'Sửa' → hộp 'Sửa điểm cắm trại' → ô 'Loại địa hình' → bấm 'Lưu lại'

```
Ven sông
```

> Sửa cho đúng: đây là bãi ven sông Hồng, không phải hồ. Ô này cho gõ tự do (tối đa 50 ký tự), dù danh sách gợi ý chưa có 'Ven sông'. Thẻ vẫn giữ màu xanh nước vì có chữ 'ven'. Nếu chỉ muốn dùng nhãn có sẵn thì chọn 'Đồi view sông'.

### Thung Trâu (đang có trên production)

**Mô tả** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → dòng 'Thung Trâu' → nút 'Sửa' → hộp 'Sửa điểm cắm trại' → ô 'Mô tả' → bấm 'Lưu lại'

```
Thung lũng cỏ ven sông Bôi giữa núi đá vôi, hợp nhóm đi tự túc. Khu Mỵ Hòa cũ nay thuộc xã Dũng Tiến, tỉnh Phú Thọ.
- Phí: các bài viết gần đây cho biết chưa thu phí vào và không có dịch vụ; phí gửi xe (nếu có) hỏi tại chỗ.
- Đường đi: Đại lộ Thăng Long – Xuân Mai – đường Hồ Chí Minh qua Miếu Môn – quốc lộ 21A về Ba Đồi, Mỵ Hòa; rẽ vào xóm Mỵ, xuống bờ sông Bôi.
- Xe con đỗ ở bãi bờ sông xóm Mỵ. Vào thung phải lội sông: cần xe gầm cao hai cầu, xe một cầu dễ kẹt vì sỏi trơn; nước cao thì đi bộ.
- Vào thung, lệch sang trái là chỗ hay cắm trại; đừng đi sâu vì có mộ cổ.
- Bờ sông gần như không có bóng cây. Tự mang đồ ăn, nước, đèn, thuốc chống muỗi, đồ sơ cứu.
- Nên đi tháng 11 – 3 khi nước cạn, tránh tháng 7–8 mưa bão. Không tắm sông, có chỗ trông nông mà sâu; chèo SUP mặc áo phao. Sóng điện thoại có thể yếu, ngủ qua đêm nên đi đông người. Mang hết rác về.
- Thuê lều, tăng bạt, ghế, bàn gấp, bếp nướng theo ngày ở kho Hà Nội của BỐP CAMPING, giao nhận hoặc tự đến lấy, trả tiền khi nhận.
```

> Xoá hết nội dung cũ rồi dán. 997 ký tự. Mô tả Google (meta) do code tự sinh: 'Thung Trâu (Kim Bôi, Hòa Bình): thung lũng, mùa đẹp t11 - t3.' rồi khoảng 95 ký tự đầu của Mô tả, cắt ở 160 ký tự. Nguồn: Người Quan Sát (miễn phí, không dịch vụ, mùa khô tháng 11–3, không tắm vì có chỗ trông nông mà sâu, qua đêm nên đi đông); Gia Đình Camping (lộ trình Đại lộ Thăng Long – Xuân Mai – Miếu Môn – QL21A – Ba Đồi – Mỵ Hòa – xóm Mỵ, xe 2 cầu, sỏi trơn, lệch trái để cắm trại, mộ cổ phía trong, bờ sông không bóng cây); Vivucamping (sedan đỗ ở bờ sông xóm Mỵ, mặc áo phao khi chèo SUP); Lều Trại Dã Ngoại (sóng điện thoại yếu, tránh tháng 7–8); NQ 1676/NQ-UBTVQH15 (Cuối Hạ, Mỵ Hòa, Nuông Dăm nhập thành xã Dũng Tiến, tỉnh Phú Thọ). Việc chưa thu phí chỉ dựa trên các bài viết, nên Mô tả nhắc hỏi phí gửi xe tại chỗ.

**Khu vực (thành phố/huyện)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → dòng 'Thung Trâu' → nút 'Sửa' → hộp 'Sửa điểm cắm trại' → ô 'Khu vực (thành phố/huyện)' → bấm 'Lưu lại'

```
Kim Bôi
```

> GIỮ NGUYÊN, không sửa. Meta và schema sẽ ghép thành 'Kim Bôi, Hòa Bình', đúng với tên khách vẫn tìm, và không ghép xã mới (Dũng Tiến, thuộc Phú Thọ) với tỉnh cũ. Việc sáp nhập đã ghi ở câu đầu Mô tả. Giữ ô 'Tỉnh / Thành' = 'Hòa Bình'.

**Gần vị trí phục vụ** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → dòng 'Thung Trâu' → nút 'Sửa' → hộp 'Sửa điểm cắm trại' → ô 'Gần vị trí phục vụ' → bấm 'Lưu lại'

```
Hà Nội (Nội Thành)
```

> Chọn dòng 'Hà Nội (Nội Thành)' trong danh sách thả xuống. Sau khi lưu, trang /diem-cam-trai/thung-trau sẽ hiện nút thuê đồ tại kho Hà Nội (dẫn tới /thue-do-cam-trai/ha-noi) và gợi ý các combo có ở kho Hà Nội.

**Mùa đẹp — từ tháng** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → dòng 'Thung Trâu' → nút 'Sửa' → hộp 'Sửa điểm cắm trại' → ô 'Mùa đẹp — từ tháng' → bấm 'Lưu lại'

```
Tháng 11
```

> Mùa nước cạn, lội sông được là tháng 11 đến tháng 3 (Người Quan Sát, Vivucamping). Nhãn trên web sẽ thành 'T11 - T3'.

**Mùa đẹp — đến tháng** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → dòng 'Thung Trâu' → nút 'Sửa' → hộp 'Sửa điểm cắm trại' → ô 'đến tháng' → bấm 'Lưu lại'

```
Tháng 3
```

> Chọn cùng lúc với ô 'từ tháng' ở trên rồi mới bấm 'Lưu lại'.

**Link bản đồ (Google Maps)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → dòng 'Thung Trâu' → nút 'Sửa' → hộp 'Sửa điểm cắm trại' → ô 'Link bản đồ (Google Maps)' → bấm 'Lưu lại'

```
https://www.google.com/maps/search/?api=1&query=Thung+Tr%C3%A2u+Kim+B%C3%B4i+H%C3%B2a+B%C3%ACnh
```

> Chỉ dán nếu ô đang trống. Mở thử link trước. Nếu Google Maps không ra đúng chỗ, dùng link toạ độ bãi bờ sông xóm Mỵ theo Vivucamping: https://www.google.com/maps/search/?api=1&query=20.569135,105.624678 (tự kiểm tra lại trên bản đồ vệ tinh trước khi dán).

**Loại địa hình** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → dòng 'Thung Trâu' → nút 'Sửa' → hộp 'Sửa điểm cắm trại' → ô 'Loại địa hình' → bấm 'Lưu lại'

```
Thung lũng
```

> Không bắt buộc: chỉ đổi chữ 'L' hoa thành thường cho đồng bộ với các nhãn khác. Meta đã tự chuyển sang chữ thường nên Google không bị ảnh hưởng.

### MỚI · Núi Lam Thành (Nghệ An)

**Tên điểm** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Tên điểm'

```
Núi Lam Thành
```

> BẮT BUỘC sau khi bấm 'Lưu lại': click vào dòng điểm vừa tạo → nút 'Upload ảnh/video' → thêm ÍT NHẤT 1 ảnh tự chụp hoặc được phép dùng (không lấy ảnh từ báo, blog). Chưa có ảnh thì thẻ điểm không có ảnh bìa, khi chia sẻ cũng không có ảnh. Nguồn kiểm chứng: Báo Nghệ An 28/03/2019 (khoảng 15 km, đường đê sông Lam, lên núi sau 3 giờ chiều, hoàng hôn); VnBusiness (khoảng 13 km, gửi xe chân núi rồi đi bộ lên bãi cỏ); Toplist (sương sớm, nhìn xuống cánh đồng); VnExpress 09/03/2024 (thành Rum trên núi, tường đá gần 1.000 m, di tích quốc gia từ 1962, đang xuống cấp). Khoảng cách ghi 13–15 km vì hai nguồn ghi 13 km và 15 km. Chưa có nguồn nào cho biết có được ngủ lại hay đốt lửa trong khu di tích không, nên Mô tả dặn khách hỏi ban quản lý di tích hoặc UBND xã trước.

**Slug (đường dẫn trang điểm)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Slug (đường dẫn trang điểm)'

```
nui-lam-thanh
```

> Gõ đúng như vậy. Trang điểm sẽ ở https://bopcamping.com/diem-cam-trai/nui-lam-thanh.

**Tỉnh / Thành** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Tỉnh / Thành'

```
Nghệ An
```

> Gõ đúng dấu (hoặc chọn từ gợi ý) để các điểm cùng tỉnh được gom chung một nhóm trên trang /diem-cam-trai.

**Loại địa hình** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Loại địa hình'

```
Đồi view sông
```

> Chọn từ danh sách gợi ý có sẵn.

**Khu vực (thành phố/huyện)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Khu vực (thành phố/huyện)'

```
Xã Lam Thành, Hưng Nguyên cũ
```

> Núi thuộc xã Hưng Thành cũ (VnExpress 03/2024). Theo NQ 1678/NQ-UBTVQH15 mục 29, Châu Nhân, Hưng Nghĩa, Hưng Thành, Phúc Lợi được nhập thành xã Lam Thành.

**Gần vị trí phục vụ** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Gần vị trí phục vụ'

```
Nghệ An (TP Vinh, Cửa Lò, Nghi Lộc)
```

> Chọn dòng này trong danh sách thả xuống.

**Link bản đồ (Google Maps)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Link bản đồ (Google Maps)'

```
https://www.google.com/maps/search/?api=1&query=N%C3%BAi+Lam+Th%C3%A0nh+H%C6%B0ng+Nguy%C3%AAn+Ngh%E1%BB%87+An
```

> Link tìm kiếm Google Maps. Mở thử trước khi lưu. Muốn ghim đúng chỗ dựng lều thì mở Google Maps → bấm vào điểm → 'Chia sẻ' → 'Sao chép đường liên kết' rồi dán link đó thay vào.

**Mô tả** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Mô tả'

```
Cách trung tâm Vinh khoảng 13–15 km, núi Lam Thành có bãi cỏ thoải trên sườn để dựng lều, nhìn xuống cánh đồng và ngắm hoàng hôn trên sông Lam.
- Đường đi: đi xe máy hoặc ô tô theo đường đê sông Lam về phía Hưng Nguyên. Tới chân núi thì gửi xe rồi đi bộ lên bãi cỏ.
- Thời điểm: lên núi sau khoảng 3 giờ chiều khi nắng đã dịu; hoàng hôn trên sông Lam là lúc đẹp nhất. Sáng sớm có sương.
- Mang theo: giày đi bộ, đủ nước uống, đồ ăn nhẹ, đèn pin.
- Trên núi có thành Rum (Lam Thành), di tích lịch sử – văn hóa cấp quốc gia, tường thành đá dài gần 1.000 m, nhiều đoạn đang xuống cấp. Không trèo lên tường thành, không lấy đá kê bếp; chỉ dựng lều trên bãi cỏ.
- Đây là khu di tích: muốn ngủ lại hay đốt lửa, hỏi trước ban quản lý di tích hoặc UBND xã Lam Thành. Nếu được phép, giữ lửa nhỏ trên nền đất trống, có người trông và dập tắt hẳn. Mang hết rác xuống núi.
- Thuê lều, tăng bạt, ghế, bàn gấp, bếp nướng theo ngày ở kho Nghệ An của BỐP CAMPING, giao nhận hoặc tự đến lấy, trả tiền khi nhận.
```

> 993 ký tự, viết theo dạng gạch đầu dòng như các điểm đang có. Mô tả Google (meta) do code tự sinh: 'Núi Lam Thành (Xã Lam Thành, Hưng Nguyên cũ, Nghệ An): đồi view sông, mùa đẹp cả năm.' rồi khoảng 71 ký tự đầu của Mô tả, cắt ở 160 ký tự. Vì vậy khoảng cách từ Vinh được đặt ở đầu Mô tả.

**Mùa đẹp — từ tháng / đến tháng** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Mùa đẹp — từ tháng' và ô 'đến tháng'

```
— (cả năm)
```

> Để nguyên '— (cả năm)' ở ô 'từ tháng' và '—' ở ô 'đến tháng', rồi bấm 'Lưu lại'. Thời điểm đẹp cụ thể đã ghi trong Mô tả.

### MỚI · Đảo chè Thanh Chương (Nghệ An)

**Tên điểm** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Tên điểm'

```
Đảo chè Thanh Chương
```

> BẮT BUỘC sau khi bấm 'Lưu lại': click vào dòng điểm vừa tạo → nút 'Upload ảnh/video' → thêm ÍT NHẤT 1 ảnh tự chụp hoặc được phép dùng (không lấy ảnh từ báo, blog). Chưa có ảnh thì thẻ điểm không có ảnh bìa, khi chia sẻ cũng không có ảnh. Điểm này THAY cho Đập Ba Khe ở bản nháp trước: nguồn về Ba Khe chỉ có từ 2013–2020, không xác nhận được đường vào và quy định hiện nay. Nguồn kiểm chứng: VinWonders 25/07/2025 (cách Vinh cũ khoảng 50 km; xã Thanh An cũ nay là xã Hoa Quân; vào đồi chè miễn phí; thuyền 50.000đ/người/lượt vào ra, giá có thể đổi; lưu số chủ thuyền để gọi đón; mỗi đảo có chòi với bàn ghế, trà xanh miễn phí; nên đi sáng sớm; mùa đông có sương; xem thời tiết trước); MIA.vn 10/06/2026 (hàng chục ốc đảo trong lòng đập; xuồng 30.000đ khứ hồi, cano dạo đảo 50.000đ, giá cập nhật 7/2023; đi thuyền 10–20 phút tùy lượng khách; mùa hè nắng gắt); Mytour 15/09/2026 (xóm An Ngọc, cạnh đường Hồ Chí Minh, khoảng 50 km từ trung tâm Vinh, đi được mọi mùa); Báo Nghệ An 2016 (đông khách dịp lễ, đi lại bằng thuyền của các hộ dân). Chi tiết 'khoảng 200 m từ đường Hồ Chí Minh tới đập' lấy từ các bài du lịch, chủ shop nên kiểm tra lại trên bản đồ. Chưa có nguồn nào cho biết có được ngủ qua đêm trên đảo không, nên Mô tả chỉ giới thiệu picnic, cắm trại trong ngày và dặn khách hỏi trước nếu muốn ngủ lại.

**Slug (đường dẫn trang điểm)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Slug (đường dẫn trang điểm)'

```
dao-che-thanh-chuong
```

> Gõ đúng như vậy. Trang điểm sẽ ở https://bopcamping.com/diem-cam-trai/dao-che-thanh-chuong.

**Tỉnh / Thành** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Tỉnh / Thành'

```
Nghệ An
```

> Gõ đúng dấu (hoặc chọn từ gợi ý) để các điểm cùng tỉnh được gom chung một nhóm trên trang /diem-cam-trai.

**Loại địa hình** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Loại địa hình'

```
Ven hồ
```

> Chọn từ danh sách gợi ý có sẵn (các đồi chè là những đảo giữa hồ thủy lợi).

**Khu vực (thành phố/huyện)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Khu vực (thành phố/huyện)'

```
Xã Hoa Quân, Thanh Chương cũ
```

> VinWonders 25/07/2025 ghi đảo chè thuộc xã Thanh An cũ, nay là xã Hoa Quân. Theo NQ 1678/NQ-UBTVQH15 mục 90, Thanh An, Thanh Hương, Thanh Quả, Thanh Thịnh được nhập thành xã Hoa Quân.

**Gần vị trí phục vụ** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Gần vị trí phục vụ'

```
Nghệ An (TP Vinh, Cửa Lò, Nghi Lộc)
```

> Chọn dòng này trong danh sách thả xuống.

**Link bản đồ (Google Maps)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Link bản đồ (Google Maps)'

```
https://www.google.com/maps/search/?api=1&query=%C4%90%E1%BA%A3o+ch%C3%A8+Thanh+An+Thanh+Ch%C6%B0%C6%A1ng+Ngh%E1%BB%87+An
```

> Link tìm kiếm Google Maps. Mở thử trước khi lưu. Muốn ghim đúng chỗ dựng lều thì mở Google Maps → bấm vào điểm → 'Chia sẻ' → 'Sao chép đường liên kết' rồi dán link đó thay vào. Link phải ra khu đập Cầu Cau (có bài ghi là Cầu Thau) bên đường Hồ Chí Minh.

**Mô tả** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Mô tả'

```
Hàng chục đồi chè nổi giữa hồ thủy lợi cạnh đường Hồ Chí Minh, cách Vinh khoảng 50 km, vào cửa miễn phí; hợp picnic, cắm trại trong ngày.
- Đường đi: từ Vinh lên Thanh Chương, tới xóm An Ngọc bên đường Hồ Chí Minh (xã Thanh An cũ, nay là xã Hoa Quân), rẽ vào khoảng 200 m là bờ đập có thuyền.
- Ra đảo: đi thuyền máy của người dân, khoảng 10–20 phút tùy lượng khách. Bài viết tháng 7/2025 ghi 50.000đ/người/lượt vào ra; hỏi rõ giá trước khi lên thuyền. Lưu tên, số điện thoại chủ thuyền để gọi đón về.
- Trên đảo có chòi nghỉ, thường được mời trà xanh. Mang theo bạt lót, ghế, đồ ăn, nước và túi rác.
- Thời điểm: chè xanh quanh năm. Nên đi buổi sáng sớm để tránh nắng; mùa đông có sương phủ trên đồi.
- Lưu ý: muốn ngủ lại trên đảo, hỏi trước chủ thuyền và chính quyền xã. Không bơi ở lòng hồ, đi thuyền mặc áo phao. Xem dự báo thời tiết, tránh ngày mưa giông.
- Thuê lều, tăng bạt, ghế, bàn gấp, bếp nướng theo ngày ở kho Nghệ An của BỐP CAMPING, giao nhận hoặc tự đến lấy, trả tiền khi nhận.
```

> 994 ký tự, viết theo dạng gạch đầu dòng như các điểm đang có. Mô tả Google (meta) do code tự sinh: 'Đảo chè Thanh Chương (Xã Hoa Quân, Thanh Chương cũ, Nghệ An): ven hồ, mùa đẹp cả năm.' rồi khoảng 71 ký tự đầu của Mô tả, cắt ở 160 ký tự.

**Mùa đẹp — từ tháng / đến tháng** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Mùa đẹp — từ tháng' và ô 'đến tháng'

```
— (cả năm)
```

> Để nguyên '— (cả năm)' ở ô 'từ tháng' và '—' ở ô 'đến tháng', rồi bấm 'Lưu lại'. Các nguồn ghi chè xanh quanh năm; thời điểm đẹp trong ngày đã ghi trong Mô tả.

### MỚI · Đồi sim Bãi Lữ (Nghệ An)

**Tên điểm** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Tên điểm'

```
Đồi sim Bãi Lữ
```

> BẮT BUỘC sau khi bấm 'Lưu lại': click vào dòng điểm vừa tạo → nút 'Upload ảnh/video' → thêm ÍT NHẤT 1 ảnh tự chụp hoặc được phép dùng (không lấy ảnh từ báo, blog). Chưa có ảnh thì thẻ điểm không có ảnh bìa, khi chia sẻ cũng không có ảnh. Nguồn kiểm chứng: Báo Nghệ An 28/03/2019 (đồi sim trên đường vào Bãi Lữ, lối mòn lên đỉnh, nhìn ra biển và đồng lúa); VinWonders 05/07/2025 (xã Hải Lộc, tháng 4–8 tắm biển và cắm trại, tháng 9–11 dịu hơn, cắm trại qua đêm trên đỉnh núi Lữ, mua hải sản ở chợ bãi biển, ít nhà hàng); MIA.vn (vịnh vòng cung có dòng kéo ra xa bờ, theo biển báo, mang phao; mùa thu miền Trung có bão); Báo Đầu tư 05/2019 (Bãi Lữ Resort có khu biển và khu đồi). Mô tả không ghi hướng hay khoảng cách: các nguồn lệch nhau (16–40 km), còn VinWonders chỉ ghi cách TX Cửa Lò khoảng 25 km mà không ghi hướng. Đã bỏ cụm 'phía bắc Cửa Lò' của bản nháp vì không nguồn nào ghi, dù trên bản đồ thì đúng là phía bắc.

**Slug (đường dẫn trang điểm)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Slug (đường dẫn trang điểm)'

```
doi-sim-bai-lu
```

> Gõ đúng như vậy. Trang điểm sẽ ở https://bopcamping.com/diem-cam-trai/doi-sim-bai-lu.

**Tỉnh / Thành** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Tỉnh / Thành'

```
Nghệ An
```

> Gõ đúng dấu (hoặc chọn từ gợi ý) để các điểm cùng tỉnh được gom chung một nhóm trên trang /diem-cam-trai.

**Loại địa hình** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Loại địa hình'

```
Đồi view biển
```

> Gõ tay (tối đa 50 ký tự), giống kiểu nhãn 'Đồi view sông' có sẵn. Đây là đồi sim và núi Lữ sát biển, không phải bãi tắm, nên không dùng 'Bãi biển'. Thẻ sẽ có màu xanh nước vì có chữ 'biển'. Nếu muốn dùng nhãn có sẵn thì chọn 'Đồi cỏ'.

**Khu vực (thành phố/huyện)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Khu vực (thành phố/huyện)'

```
Xã Hải Lộc, Nghi Lộc cũ
```

> Bãi Lữ trước thuộc Nghi Yên và Nghi Tiến (MIA.vn). Theo NQ 1678/NQ-UBTVQH15 mục 54, Nghi Thiết, Nghi Tiến, Nghi Yên được nhập thành xã Hải Lộc.

**Gần vị trí phục vụ** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Gần vị trí phục vụ'

```
Nghệ An (TP Vinh, Cửa Lò, Nghi Lộc)
```

> Chọn dòng này trong danh sách thả xuống.

**Link bản đồ (Google Maps)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Link bản đồ (Google Maps)'

```
https://www.google.com/maps/search/?api=1&query=B%C3%A3i+L%E1%BB%AF+Ngh%E1%BB%87+An
```

> Link tìm kiếm Google Maps (ra khu Bãi Lữ). Muốn ghim đúng đồi sim thì mở Google Maps → bấm vào đồi → 'Chia sẻ' → 'Sao chép đường liên kết' rồi dán link đó thay vào.

**Mô tả** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Mô tả'

```
Đồi sim và núi Lữ sát biển Bãi Lữ: một bên là biển xanh, bãi đá, một bên là đồng lúa; hợp cắm trại ngắm hoàng hôn và bình minh.
- Đường đi: từ Vinh hoặc Cửa Lò đi xe máy, ô tô hoặc taxi lên Bãi Lữ. Đồi sim nằm ngay trên đường vào biển, lên đỉnh bằng một lối mòn nhỏ. Khu này nay thuộc xã Hải Lộc.
- Thời điểm: tháng 4–8 hợp tắm biển và cắm trại; tháng 9–11 trời dịu hơn nhưng dễ có mưa bão, xem dự báo trước khi đi. Chiều lên đồi ngắm hoàng hôn; ngủ lại trên đỉnh núi Lữ thì đón bình minh trên biển.
- Ăn uống: có thể mua hải sản ở chợ họp trên bãi biển rồi mang lên nướng. Nhà hàng quanh bãi không nhiều.
- Ở Bãi Lữ có khu nghỉ dưỡng; trước khi dựng lều, hỏi người quản lý hoặc người dân xem chỗ đó có được cắm trại không.
- Tắm biển theo biển báo, người không biết bơi mang phao. Trên đồi trống gió, cắm cọc và căng dây lều thật chắc.
- Thuê lều, tăng bạt, ghế, bàn gấp, bếp nướng theo ngày ở kho Nghệ An của BỐP CAMPING, giao nhận hoặc tự đến lấy, trả tiền khi nhận.
```

> 969 ký tự, viết theo dạng gạch đầu dòng như các điểm đang có. Mô tả Google (meta) do code tự sinh: 'Đồi sim Bãi Lữ (Xã Hải Lộc, Nghi Lộc cũ, Nghệ An): đồi view biển, mùa đẹp t4 - t8.' rồi khoảng 74 ký tự đầu của Mô tả, cắt ở 160 ký tự. Khoảng cách từ Vinh được bỏ ra có chủ ý vì các nguồn lệch nhau.

**Mùa đẹp — từ tháng** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Mùa đẹp — từ tháng'

```
Tháng 4
```

> Tháng 4–8 hợp tắm biển, cắm trại (VinWonders 07/2025). Nhãn trên web sẽ thành 'T4 - T8'.

**Mùa đẹp — đến tháng** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'đến tháng' (cạnh 'Mùa đẹp — từ tháng')

```
Tháng 8
```

> Chọn cùng lúc với ô 'từ tháng' rồi bấm 'Lưu lại'.

### MỚI · Đập Vệ Vừng (Nghệ An)

**Tên điểm** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Tên điểm'

```
Đập Vệ Vừng
```

> BẮT BUỘC sau khi bấm 'Lưu lại': click vào dòng điểm vừa tạo → nút 'Upload ảnh/video' → thêm ÍT NHẤT 1 ảnh tự chụp hoặc được phép dùng (không lấy ảnh từ báo, blog). Chưa có ảnh thì thẻ điểm không có ảnh bìa, khi chia sẻ cũng không có ảnh. Nguồn kiểm chứng: Báo Nghệ An 07/04/2025 (đập ở xã Đồng Thành; 720 ha, gần 20 triệu m³; gần 10 đảo, mùa nước rút lộ thêm đảo hợp cắm trại, picnic; đường bê tông dọc hồ; thuyền dạo đảo; cò trắng lúc hoàng hôn; quán đặc sản lâu năm; cá 'trắm Vệ Vừng' nức tiếng vì thịt chắc; theo QL48E khoảng 7 km về phía tây từ trung tâm Yên Thành tới dốc Hủng Trăn; hồ cấp nước tưới và nước sinh hoạt); Tiền Phong (cách thị trấn Yên Thành chừng 10 km, cách TP Vinh khoảng 70 km, vùng tây bắc Yên Thành). Các bài du lịch ghi trung tâm Yên Thành cách Vinh khoảng 50 km, nên Mô tả ghi 'khoảng 60–70 km'; chủ shop nên đo lại trên Google Maps trước khi lưu. Tin 'Yên Thành: 6 hồ chứa đang ở mức báo động khẩn cấp' (Báo Mới) có nhắc hồ Vệ Vừng, nên Mô tả dặn không ngủ trên đảo khi có mưa. Chưa có số liệu về giá thuyền, giá món ăn: hỏi tại chỗ. Bản nháp trước tách thành hai lộ trình và có tuyến 'từ Vinh theo QL7A' không có nguồn; nay đã gộp lại thành một lộ trình.

**Slug (đường dẫn trang điểm)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Slug (đường dẫn trang điểm)'

```
dap-ve-vung
```

> Gõ đúng như vậy. Trang điểm sẽ ở https://bopcamping.com/diem-cam-trai/dap-ve-vung.

**Tỉnh / Thành** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Tỉnh / Thành'

```
Nghệ An
```

> Gõ đúng dấu (hoặc chọn từ gợi ý) để các điểm cùng tỉnh được gom chung một nhóm trên trang /diem-cam-trai.

**Loại địa hình** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Loại địa hình'

```
Ven hồ
```

> Chọn từ danh sách gợi ý có sẵn.

**Khu vực (thành phố/huyện)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Khu vực (thành phố/huyện)'

```
Xã Quang Đồng, Yên Thành cũ
```

> Báo Nghệ An 07/04/2025 ghi đập 'nằm ở xã Đồng Thành' (dấu huyền, không phải Đông Thành). Tiền Phong cũng đặt đập ở Đồng Thành, vùng tây bắc Yên Thành. Theo NQ 1678/NQ-UBTVQH15 mục 107, Đồng Thành, Kim Thành, Quang Thành được nhập thành xã Quang Đồng; Báo Nghệ An đưa tin Đảng bộ xã Quang Đồng họp đại hội lần thứ I ngày 28/8/2025. Bản nháp trước ghi nhầm 'Xã Yên Thành, Đông Thành cũ'; nay đã sửa.

**Gần vị trí phục vụ** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Gần vị trí phục vụ'

```
Nghệ An (TP Vinh, Cửa Lò, Nghi Lộc)
```

> Chọn dòng này trong danh sách thả xuống.

**Link bản đồ (Google Maps)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Link bản đồ (Google Maps)'

```
https://www.google.com/maps/search/?api=1&query=%C4%90%E1%BA%ADp+V%E1%BB%87+V%E1%BB%ABng+Y%C3%AAn+Th%C3%A0nh+Ngh%E1%BB%87+An
```

> Link tìm kiếm Google Maps. Mở thử trước khi lưu. Muốn ghim đúng chỗ dựng lều thì mở Google Maps → bấm vào điểm → 'Chia sẻ' → 'Sao chép đường liên kết' rồi dán link đó thay vào.

**Mô tả** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Mô tả'

```
Hồ rộng khoảng 720 ha, cách Vinh khoảng 60–70 km, giữa hồ có gần 10 đảo; mùa nước rút lộ thêm vài đảo, hợp cắm trại, picnic.
- Đường đi: từ Vinh lên trung tâm Yên Thành (khoảng 50 km), rồi theo quốc lộ 48E thêm khoảng 7 km về phía tây tới dốc Hủng Trăn. Đập thuộc xã Đồng Thành cũ, nay là xã Quang Đồng.
- Dọc hồ có đường bê tông cho xe máy, ô tô nhỏ. Muốn ra đảo, hỏi người dân thuê thuyền.
- Thời điểm: hoàng hôn là lúc hàng nghìn con cò trắng bay về; mùa khô có thêm đảo lộ ra để dựng lều.
- An toàn: đảo nổi chỉ lộ ra mùa khô; không ngủ lại trên đảo khi có mưa lớn hoặc dự báo mưa, luôn hẹn chắc giờ thuyền đón. Không bơi ở lòng hồ; đi thuyền mặc áo phao.
- Ăn uống: có quán đặc sản mở lâu năm, cá trắm Vệ Vừng nổi tiếng chắc thịt.
- Hồ cấp nước tưới và nước sinh hoạt: không xả rác, không đổ dầu mỡ xuống nước, mang hết rác về.
- Thuê lều, tăng bạt, ghế, bàn gấp, bếp nướng theo ngày ở kho Nghệ An của BỐP CAMPING, giao nhận hoặc tự đến lấy, trả tiền khi nhận.
```

> 965 ký tự, viết theo dạng gạch đầu dòng như các điểm đang có. Mô tả Google (meta) do code tự sinh: 'Đập Vệ Vừng (Xã Quang Đồng, Yên Thành cũ, Nghệ An): ven hồ, mùa đẹp cả năm.' rồi khoảng 81 ký tự đầu của Mô tả, cắt ở 160 ký tự. Vì vậy diện tích và khoảng cách từ Vinh được đặt ở đầu Mô tả.

**Mùa đẹp — từ tháng / đến tháng** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Mùa đẹp — từ tháng' và ô 'đến tháng'

```
— (cả năm)
```

> Để nguyên '— (cả năm)' ở ô 'từ tháng' và '—' ở ô 'đến tháng', rồi bấm 'Lưu lại'. Thời điểm đẹp cụ thể đã ghi trong Mô tả.

### MỚI · Hồ Đồng Mô (Hà Nội)

**Tên điểm** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Tên điểm'

```
Hồ Đồng Mô
```

> BẮT BUỘC sau khi bấm 'Lưu lại': click vào dòng điểm vừa tạo → nút 'Upload ảnh/video' → thêm ÍT NHẤT 1 ảnh tự chụp hoặc được phép dùng (không lấy ảnh từ báo, blog). Chưa có ảnh thì thẻ điểm không có ảnh bìa, khi chia sẻ cũng không có ảnh. Nguồn kiểm chứng: VinWonders 17/08/2025 (giáp ranh Ba Vì – Sơn Tây, trong quần thể Làng Văn hóa các dân tộc Việt Nam, hơn 1 giờ lái xe; lộ trình Láng – Hòa Lạc hoặc QL32 – Sơn Lộc – QL21; vé 80.000đ/người/ngày; thuê lều 250.000–600.000đ/24 giờ, nhà sàn, trượt cỏ; SUP, cano, câu cá; ướp đồ ăn ở nhà, thuê bếp nướng tại chỗ; 'có thể đến vào bất kỳ thời gian nào' nhưng nên theo dõi thời tiết, tránh ngày mưa; hoàng hôn đẹp; mang thuốc chống muỗi, áo dài tay). Giá có thể đổi, nên Mô tả đã nhắc khách hỏi trước. Đã bỏ câu 'đi được quanh năm' và câu 'khu hồ nay thuộc xã Đoài Phương' của bản nháp trước.

**Slug (đường dẫn trang điểm)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Slug (đường dẫn trang điểm)'

```
ho-dong-mo
```

> Gõ đúng như vậy. Trang điểm sẽ ở https://bopcamping.com/diem-cam-trai/ho-dong-mo.

**Tỉnh / Thành** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Tỉnh / Thành'

```
Hà Nội
```

> Chọn 'Hà Nội' từ gợi ý có sẵn (đã có 2 điểm Hà Nội) để gom chung một nhóm.

**Loại địa hình** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Loại địa hình'

```
Ven hồ
```

> Chọn từ danh sách gợi ý có sẵn.

**Khu vực (thành phố/huyện)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Khu vực (thành phố/huyện)'

```
Sơn Tây - Ba Vì cũ
```

> Hồ nằm giáp ranh Sơn Tây và Ba Vì (VinWonders 08/2025). Nguồn chỉ cho thấy một phần hồ thuộc xã Đoài Phương: theo Thư viện Pháp luật, xã này lập từ Kim Sơn, Sơn Đông và một phần Cổ Đông; Báo Đại biểu Nhân dân đưa tin vụ san lấp ở hồ Đồng Mô thuộc xã Đoài Phương. Vì vậy ô này không ghi tên một xã duy nhất.

**Gần vị trí phục vụ** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Gần vị trí phục vụ'

```
Hà Nội (Nội Thành)
```

> Chọn dòng này trong danh sách thả xuống.

**Link bản đồ (Google Maps)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Link bản đồ (Google Maps)'

```
https://www.google.com/maps/search/?api=1&query=H%E1%BB%93+%C4%90%E1%BB%93ng+M%C3%B4+S%C6%A1n+T%C3%A2y+H%C3%A0+N%E1%BB%99i
```

> Link tìm kiếm Google Maps. Mở thử trước khi lưu. Muốn ghim đúng khu cắm trại thì mở Google Maps → bấm vào điểm → 'Chia sẻ' → 'Sao chép đường liên kết' rồi dán link đó thay vào.

**Mô tả** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Mô tả'

```
Bãi cỏ ven hồ rộng, nhiều khu cắm trại dịch vụ, cách trung tâm Hà Nội hơn 1 giờ lái xe; hồ nằm trong quần thể Làng Văn hóa – Du lịch các dân tộc Việt Nam, giáp ranh Sơn Tây và Ba Vì.
- Đường đi: cao tốc Láng – Hòa Lạc về hướng Làng Văn hóa; hoặc quốc lộ 32 lên Sơn Tây, tới ngã tư Sơn Lộc rẽ quốc lộ 21 về hồ. Một phần hồ nay thuộc xã Đoài Phương.
- Phí: khu du lịch thu vé vào cửa, bài viết tháng 8/2025 ghi 80.000đ/người/ngày; thuê lều, bếp nướng, nhà sàn tính riêng. Hỏi rõ giá trước khi dùng dịch vụ.
- Hoạt động: chèo SUP, đi cano, trượt cỏ, câu cá (hỏi ban quản lý trước). Có thể ướp sẵn đồ ăn ở nhà mang theo để nướng.
- Thời điểm: xem dự báo trước khi đi, tránh ngày mưa; chiều muộn ngắm hoàng hôn trên hồ.
- Mang thuốc chống muỗi, áo dài tay. Đi cuối tuần thì nên gọi khu cắm trại đặt chỗ trước.
- Nếu khu bạn chọn cho mang lều riêng (nhớ hỏi trước), bạn có thể thuê lều, tăng bạt, ghế, bàn gấp, bếp nướng theo ngày ở kho Hà Nội của BỐP CAMPING, giao nhận hoặc tự đến lấy, trả tiền khi nhận.
```

> 1000 ký tự, viết theo dạng gạch đầu dòng như các điểm đang có. Mô tả Google (meta) do code tự sinh: 'Hồ Đồng Mô (Sơn Tây - Ba Vì cũ, Hà Nội): ven hồ, mùa đẹp cả năm.' rồi khoảng 92 ký tự đầu của Mô tả, cắt ở 160 ký tự.

**Mùa đẹp — từ tháng / đến tháng** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Mùa đẹp — từ tháng' và ô 'đến tháng'

```
— (cả năm)
```

> Để nguyên '— (cả năm)' ở ô 'từ tháng' và '—' ở ô 'đến tháng', rồi bấm 'Lưu lại'. Nguồn ghi có thể đến vào bất kỳ thời gian nào, chỉ cần tránh ngày mưa.

### MỚI · Hồ Suối Hai (Hà Nội)

**Tên điểm** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Tên điểm'

```
Hồ Suối Hai
```

> BẮT BUỘC sau khi bấm 'Lưu lại': click vào dòng điểm vừa tạo → nút 'Upload ảnh/video' → thêm ÍT NHẤT 1 ảnh tự chụp hoặc được phép dùng (không lấy ảnh từ báo, blog). Chưa có ảnh thì thẻ điểm không có ảnh bìa, khi chia sẻ cũng không có ảnh. Nguồn kiểm chứng: MIA.vn 25/05/2026 (khoảng 60 km về phía tây trung tâm Hà Nội; xe buýt 74/20A tới bến xe Sơn Tây rồi taxi khoảng 10 km; 14 đảo phủ cây; bãi cỏ phẳng ven bờ để tự dựng lều; thuyền, câu cá; hoàng hôn ở bờ kè với núi Tản Viên phía sau; quanh hồ hầu như không có nhà hàng; mùa đẹp tháng 5–8 và 10–12; tránh mưa bão); Báo Văn hóa (cắm trại, chèo thuyền, câu cá; hơn 1 giờ đi ô tô).

**Slug (đường dẫn trang điểm)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Slug (đường dẫn trang điểm)'

```
ho-suoi-hai
```

> Gõ đúng như vậy. Trang điểm sẽ ở https://bopcamping.com/diem-cam-trai/ho-suoi-hai.

**Tỉnh / Thành** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Tỉnh / Thành'

```
Hà Nội
```

> Chọn 'Hà Nội' từ gợi ý có sẵn để gom chung một nhóm.

**Loại địa hình** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Loại địa hình'

```
Ven hồ
```

> Chọn từ danh sách gợi ý có sẵn.

**Khu vực (thành phố/huyện)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Khu vực (thành phố/huyện)'

```
Xã Suối Hai, Ba Vì cũ
```

> Sau sắp xếp, xã Suối Hai vẫn là đơn vị cấp xã (QĐ 4909/QĐ-UBND năm 2025 của UBND TP Hà Nội có nêu 'xã Suối Hai').

**Gần vị trí phục vụ** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Gần vị trí phục vụ'

```
Hà Nội (Nội Thành)
```

> Chọn dòng này trong danh sách thả xuống.

**Link bản đồ (Google Maps)** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Link bản đồ (Google Maps)'

```
https://www.google.com/maps/search/?api=1&query=H%E1%BB%93+Su%E1%BB%91i+Hai+Ba+V%C3%AC+H%C3%A0+N%E1%BB%99i
```

> Link tìm kiếm Google Maps. Mở thử trước khi lưu. Muốn ghim đúng bãi cỏ dựng lều thì mở Google Maps → bấm vào điểm → 'Chia sẻ' → 'Sao chép đường liên kết' rồi dán link đó thay vào.

**Mô tả** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Mô tả'

```
Hồ nhân tạo dưới chân núi Ba Vì, cách trung tâm Hà Nội khoảng 60 km, có 14 đảo phủ cây xanh và bãi cỏ ven bờ để tự dựng lều.
- Đường đi: đi xe riêng theo Google Maps; hoặc xe buýt 74, 20A tới bến xe Sơn Tây rồi đi taxi, xe ôm khoảng 10 km.
- Chỗ dựng lều: bãi cỏ bằng phẳng ven bờ hồ. Chiều tối ra bờ kè ngắm hoàng hôn, phía sau là núi Tản Viên. Có thể đi thuyền dạo giữa các đảo, câu cá.
- Quanh hồ hầu như không có nhà hàng, quán xá chủ yếu dọc đường vào: tự mang đồ ăn, nước, đèn.
- Thời điểm: tháng 5–8 lộng gió, mát hơn trong phố; tháng 10–12 trời se lạnh, sáng sớm có sương. Tránh ngày mưa bão.
- An toàn: không bơi ở lòng hồ; trẻ nhỏ luôn có người lớn kèm; đi thuyền mặc áo phao.
- Hỏi người dân hoặc chính quyền xã trước khi dựng lều qua đêm, nhất là trên đảo. Mang hết rác về, không đổ thức ăn thừa xuống hồ.
- Thuê lều, tăng bạt, ghế, bàn gấp, bếp nướng theo ngày ở kho Hà Nội của BỐP CAMPING, giao nhận hoặc tự đến lấy, trả tiền khi nhận.
```

> 949 ký tự, viết theo dạng gạch đầu dòng như các điểm đang có. Mô tả Google (meta) do code tự sinh: 'Hồ Suối Hai (Xã Suối Hai, Ba Vì cũ, Hà Nội): ven hồ, mùa đẹp cả năm.' rồi khoảng 88 ký tự đầu của Mô tả, cắt ở 160 ký tự.

**Mùa đẹp — từ tháng / đến tháng** — https://bopcamping.com/admin/camping-spots → menu trái 'Điểm cắm trại' → nút 'Thêm điểm' → hộp 'Thêm điểm cắm trại' → ô 'Mùa đẹp — từ tháng' và ô 'đến tháng'

```
— (cả năm)
```

> Để nguyên '— (cả năm)' ở ô 'từ tháng' và '—' ở ô 'đến tháng', rồi bấm 'Lưu lại'. Hồ có hai mùa đẹp tách rời (tháng 5–8 và 10–12) mà ô này chỉ nhận một khoảng, nên để cả năm. Hai mùa đã ghi trong Mô tả.

**Gợi ý**

- Mỗi điểm MỚI cần ít nhất 1 ảnh tự chụp hoặc được phép dùng. Sau khi bấm 'Lưu lại', click vào dòng điểm → 'Upload ảnh/video'. Chưa có ảnh thì trang vẫn được Google lập chỉ mục (mô tả đã dài hơn 200 ký tự), nhưng không có ảnh bìa và cũng không có ảnh khi chia sẻ.
- Thứ tự nên làm: (1) sửa 3 điểm cũ: Mô tả, Khu vực, Loại địa hình của bãi đá, gắn Thung Trâu với kho Hà Nội và đặt mùa T11–T3 (đổi tên thành 'Bãi đá sông Hồng Nhật Tân' là tuỳ chọn); (2) thêm Núi Lam Thành, Đập Vệ Vừng, Đảo chè Thanh Chương, Đồi sim Bãi Lữ; (3) thêm Hồ Đồng Mô, Hồ Suối Hai.
- Mô tả Google của mỗi trang điểm do code tự ghép theo mẫu '<Tên> (<Khu vực>, <Tỉnh>): <địa hình>, mùa đẹp <mùa>.', sau đó mới tới Mô tả, và cắt ở 160 ký tự. Phần tự ghép đã chiếm khoảng 60–90 ký tự, nên Google chỉ hiện khoảng 65–95 ký tự đầu của Mô tả. Khi tự sửa Mô tả sau này, giữ ô Khu vực ngắn và đặt điểm mạnh nhất (miễn phí, khoảng cách, cảnh đẹp) ở mấy chữ đầu.
- Mở thử từng link Google Maps trước khi lưu. Nếu link không ra đúng chỗ, mở Google Maps, bấm vào đúng điểm, chọn 'Chia sẻ' → 'Sao chép đường liên kết' rồi dán link đó thay vào. Với Đập Vệ Vừng, nhân tiện đo quãng đường thật từ Vinh; nếu khác nhiều so với 'khoảng 60–70 km' thì sửa trong Mô tả.
- Phí và giờ mở cửa thay đổi theo mùa: vé vườn hoa bãi đá sông Hồng, vé Đồng Mô (80.000đ, theo bài tháng 8/2025), giá thuyền ra đảo chè (50.000đ, theo bài tháng 7/2025), phí gửi xe ở Yên Sở. Nên xem lại mỗi 6 tháng. Mô tả đã ghi 'tham khảo' và nhắc khách hỏi trước.
- Đập Ba Khe (Nam Đàn) của bản nháp trước đã được bỏ khỏi danh sách đăng: nguồn chỉ có từ 2013–2020, không xác nhận được đường vào, phí hay việc còn cắm trại được hiện nay. Nếu chủ shop tự đi xem và thấy vẫn cắm trại được thì có thể thêm sau. Lưu ý: khu Nam Lộc cũ nhiều khả năng nay thuộc xã Vạn An (NQ 1678 nhập thị trấn Nam Đàn, Thượng Tân Lộc, Xuân Hòa thành xã Vạn An), nhưng cần kiểm tra lại. Ở Kỳ Anh (Hà Tĩnh) cũng có một đập Ba Khe, đừng nhầm.
- Thung Trâu: giữ 'Tỉnh / Thành' = 'Hòa Bình' và 'Khu vực' = 'Kim Bôi' vì khách vẫn tìm theo tên cũ. Câu đầu Mô tả đã ghi khu Mỵ Hòa cũ nay thuộc xã Dũng Tiến, tỉnh Phú Thọ. Không đổi slug của 3 điểm cũ để giữ các link đã có trên Google.
- Có thể tích 'Hiện ở panel gợi ý trang chủ' cho 1–2 điểm Nghệ An (Núi Lam Thành, Đảo chè Thanh Chương) để kho Nghệ An cũng có điểm gợi ý, không chỉ Hà Nội. Việc này không bắt buộc.
- Khi các điểm mới đã có ảnh, nên thêm liên kết tới chúng trong bài /cam-nang/diem-cam-trai-gan-vinh-nghe-an và /cam-nang/diem-cam-trai-gan-ha-noi. Trang /thue-do-cam-trai/nghe-an sẽ tự liệt kê các điểm gắn với kho Nghệ An.
- Việc cho lập trình viên: code đang ghi rằng shop cho thuê 'túi ngủ, đèn trại', trong khi shop không có các món này. Các chỗ cần sửa: app/Http/Controllers/Shop/LocalLandingController.php dòng 309 và 333–334 (câu được nối thêm vào meta khi mô tả điểm ngắn); app/Services/SeoService.php dòng 110, 196, 233; resources/js/Pages/CampingSpotDetail.tsx dòng 241 ('Xem lều, bếp, túi ngủ cho thuê →'); resources/js/Pages/Welcome.tsx dòng 168; resources/js/Pages/Products.tsx dòng 205 (placeholder của ô tìm kiếm). Nên đổi theo đúng danh mục đang cho thuê: lều, tăng bạt, bàn, ghế, bếp nướng, nệm hơi, thảm.

## Tên sản phẩm và combo cho tiêu đề Google (title ≤ 60 ký tự)

### Sản phẩm: Bàn gấp Naturehike (kaki)

**Tên sản phẩm** — Mở https://bopcamping.com/admin/products (hoặc menu trái → Sản phẩm) → dòng "Bàn gấp gọn ngoài trời Naturehike - Màu Kaki" → nút "Sửa" (mở /admin/products/{id}/sua) → khối "Thông tin cơ bản" → ô "Tên sản phẩm" → nút "Lưu thay đổi"

```
Bàn gấp gọn Naturehike màu kaki
```

> NÊN SỬA, nhưng CHỜ dev làm xong phần giữ slug / chuyển hướng 301 rồi mới bấm lưu (xem khuyến nghị 1–3). Title hiện tại 67/60 nên bị Google cắt đuôi. Trang vẫn chạy bình thường. Tên từ 44 xuống 31 ký tự, title từ 67 xuống 54/60: "Bàn gấp gọn Naturehike màu kaki — Thuê tại BỐP CAMPING". URL ĐỔI nếu lưu trước khi có bản sửa: /thiet-bi/ban-gap-gon-ngoai-troi-naturehike-mau-kaki thành /thiet-bi/ban-gap-gon-naturehike-mau-kaki. Bài Cẩm nang có gắn sản phẩm này: nuong-bbq-khi-cam-trai (chỉ gắn thẻ, thân bài không có link). Cụm "ngoài trời" bị bỏ thì đưa vào ô Mô tả, cùng khối Thông tin cơ bản, nằm dưới ô Tên sản phẩm và ô Danh mục.

### Sản phẩm: Bạt lót lều Naturehike

**Tên sản phẩm** — Mở https://bopcamping.com/admin/products (hoặc menu trái → Sản phẩm) → dòng "Bạt Lót Lều -  Naturehike" → nút "Sửa" (mở /admin/products/{id}/sua) → khối "Thông tin cơ bản" → ô "Tên sản phẩm" → nút "Lưu thay đổi"

```
Bạt lót lều Naturehike
```

> LÀM NGAY ĐƯỢC, không bắt buộc, chỉ để tên gọn. Bỏ dấu gạch và khoảng trắng kép, viết thường theo kiểu tiếng Việt. Tên 25 xuống 22 ký tự, title 48 xuống 45/60. URL GIỮ NGUYÊN /thiet-bi/bat-lot-leu-naturehike: đã chạy thử, slug sinh từ tên mới giống hệt slug cũ.

### Sản phẩm: Bếp nướng BBQ

**Tên sản phẩm** — Mở https://bopcamping.com/admin/products (hoặc menu trái → Sản phẩm) → dòng "Bếp nướng BBQ cắm trại ngoài trời" → nút "Sửa" → khối "Thông tin cơ bản" → ô "Tên sản phẩm"

```
Bếp nướng BBQ cắm trại ngoài trời
```

> GIỮ NGUYÊN, không cần sửa. Tên 33 ký tự, title 56/60: "Bếp nướng BBQ cắm trại ngoài trời — Thuê tại BỐP CAMPING". URL /thiet-bi/bep-nuong-bbq-cam-trai-ngoai-troi giữ nguyên.

### Sản phẩm: Đệm hơi Horizon

**Tên sản phẩm** — Mở https://bopcamping.com/admin/products (hoặc menu trái → Sản phẩm) → dòng "Đệm hơi bơm hơi Horizon™" → nút "Sửa" (mở /admin/products/{id}/sua) → khối "Thông tin cơ bản" → ô "Tên sản phẩm" → nút "Lưu thay đổi"

```
Đệm hơi bơm hơi Horizon
```

> LÀM NGAY ĐƯỢC, không bắt buộc. Chỉ bỏ ký hiệu ™. Tên 24 xuống 23 ký tự, title 47 xuống 46/60. URL GIỮ NGUYÊN /thiet-bi/dem-hoi-bom-hoi-horizon (đã chạy thử). Tên gọn hơn là "Đệm bơm hơi Horizon" (19 ký tự, title 42/60), nhưng tên này đổi URL thành /thiet-bi/dem-bom-hoi-horizon, nên chỉ dùng sau khi dev đã sửa xong để đổi tên không làm đổi URL.

### Sản phẩm: Ghế SeaStar đệm lưng cao (đen)

**Tên sản phẩm** — Mở https://bopcamping.com/admin/products (hoặc menu trái → Sản phẩm) → dòng "Ghế cắm trại đệm lưng cao SeaStar™ - Màu đen" → nút "Sửa" (mở /admin/products/{id}/sua) → khối "Thông tin cơ bản" → ô "Tên sản phẩm" → nút "Lưu thay đổi"

```
Ghế SeaStar đệm lưng cao màu đen
```

> NÊN SỬA, nhưng CHỜ dev làm xong phần giữ slug / chuyển hướng 301 rồi mới bấm lưu. Title hiện tại 67/60 nên bị Google cắt đuôi. Tên 44 xuống 32 ký tự, title 67 xuống 55/60 (còn dư 5 ký tự). Tên mới giữ đủ tên mẫu SeaStar, chữ "đệm" (điểm chính của mẫu này, hãng gọi là High-Back Padded) và màu. URL ĐỔI nếu lưu trước khi có bản sửa: /thiet-bi/ghe-cam-trai-dem-lung-cao-seastar-mau-den thành /thiet-bi/ghe-seastar-dem-lung-cao-mau-den. Hai cách khác: (a) "Ghế cắm trại SeaStar đệm lưng cao" (33 ký tự, title 56/60, URL /thiet-bi/ghe-cam-trai-seastar-dem-lung-cao), chỉ dùng nếu shop chỉ có màu đen; (b) "Ghế cắm trại lưng cao SeaStar màu đen" (37 ký tự, title 60/60, URL /thiet-bi/ghe-cam-trai-lung-cao-seastar-mau-den), vừa chạm giới hạn và mất chữ "đệm". Bài Cẩm nang có gắn sản phẩm này: diem-cam-trai-gan-vinh-nghe-an, thue-hay-mua-do-cam-trai, nuong-bbq-khi-cam-trai, checklist-do-cam-trai-cho-nguoi-moi (chỉ gắn thẻ, thân bài không có link). Cụm "cắm trại" bị bỏ thì đưa vào ô Mô tả, cùng khối Thông tin cơ bản, dưới ô Tên sản phẩm và ô Danh mục.

### Sản phẩm: Ghế thư giãn Nightfall T05

**Tên sản phẩm** — Mở https://bopcamping.com/admin/products (hoặc menu trái → Sản phẩm) → dòng "Ghế thư giãn gấp gọn Naturehike Nightfall T05 dành cho cắm trại" → nút "Sửa" (mở /admin/products/{id}/sua) → khối "Thông tin cơ bản" → ô "Tên sản phẩm" → nút "Lưu thay đổi"

```
Ghế thư giãn Naturehike Nightfall T05
```

> NÊN SỬA, nhưng CHỜ dev làm xong phần giữ slug / chuyển hướng 301 rồi mới bấm lưu. Đây là title dài nhất trong các sản phẩm: 86/60. Tên 63 xuống 37 ký tự, title 86 xuống 60/60, vừa chạm giới hạn. Tên mới giữ thương hiệu Naturehike và tên mẫu Nightfall T05 (đã kiểm tên mẫu có thật, xem nguồn HomePro). URL ĐỔI nếu lưu trước khi có bản sửa: /thiet-bi/ghe-thu-gian-gap-gon-naturehike-nightfall-t05-danh-cho-cam-trai thành /thiet-bi/ghe-thu-gian-naturehike-nightfall-t05. Tên ngắn hơn để có chỗ dư: (a) "Ghế Naturehike Nightfall T05" (28 ký tự, title 51/60, URL /thiet-bi/ghe-naturehike-nightfall-t05); (b) "Ghế thư giãn Nightfall T05" (26 ký tự, title 49/60, URL /thiet-bi/ghe-thu-gian-nightfall-t05). Cả hai cách cũng đổi URL. Không bài Cẩm nang nào gắn hay link tới sản phẩm này. Hai cụm "gấp gọn" và "dành cho cắm trại" thì đưa vào câu đầu ô Mô tả, cùng khối Thông tin cơ bản, dưới ô Tên sản phẩm và ô Danh mục.

### Sản phẩm: Lều cabin Village 13

**Tên sản phẩm** — Mở https://bopcamping.com/admin/products (hoặc menu trái → Sản phẩm) → dòng "Lều cabin tự động Village™ 13 dành cho 6 - 8 người" → nút "Sửa" (mở /admin/products/{id}/sua) → khối "Thông tin cơ bản" → ô "Tên sản phẩm" → nút "Lưu thay đổi"

```
Lều cabin tự động Village 13
```

> NÊN SỬA, nhưng CHỜ dev làm xong phần giữ slug / chuyển hướng 301 rồi mới bấm lưu. Title hiện tại 73/60. Tên 50 xuống 28 ký tự, title 73 xuống 51/60. URL ĐỔI nếu lưu trước khi có bản sửa: /thiet-bi/leu-cabin-tu-dong-village-13-danh-cho-6-8-nguoi thành /thiet-bi/leu-cabin-tu-dong-village-13. Tên đề xuất không ghi số người, vì phần thông số trên trang Naturehike ghi sức chứa "4 à 6 personnes" (4–6 người). Con số 6–8 chỉ có trong phần quảng cáo của hãng, và chỗ đó ghi "tối đa 8 người". Bạn xác nhận lại sức chứa thực tế trước. Nếu chắc là 6–8 người thì có thể dùng "Lều cabin Village 13 cho 6-8 người" (34 ký tự, title 57/60, URL /thiet-bi/leu-cabin-village-13-cho-6-8-nguoi) hoặc "Lều tự động Village 13 cho 6-8 người" (36 ký tự, title 59/60, URL /thiet-bi/leu-tu-dong-village-13-cho-6-8-nguoi). Bài Cẩm nang liên quan: thân bài chon-leu-2-nguoi-hay-4-nguoi có link viết tay tới URL cũ; bài diem-cam-trai-gan-ha-noi, thue-hay-mua-do-cam-trai và chon-leu-2-nguoi-hay-4-nguoi gắn thẻ sản phẩm này. Sản phẩm chỉ có ở kho Hà Nội.

### Sản phẩm: Lều Village 6.0 (4 người)

**Tên sản phẩm** — Mở https://bopcamping.com/admin/products (hoặc menu trái → Sản phẩm) → dòng "Lều cắm trại Village™ 6.0 dành cho 4 người, dựng nhanh chóng." → nút "Sửa" (mở /admin/products/{id}/sua) → khối "Thông tin cơ bản" → ô "Tên sản phẩm" → nút "Lưu thay đổi"

```
Lều cắm trại Village 6.0 cho 4 người
```

> NÊN SỬA, nhưng CHỜ dev làm xong phần giữ slug / chuyển hướng 301 rồi mới bấm lưu. Title hiện tại 84/60. Tên 61 xuống 36 ký tự, title 84 xuống 59/60. Bỏ dấu chấm cuối và ký hiệu ™. Tên của hãng là "Village 6.0 4-Person Instant Tent", khớp với số 4 người. URL ĐỔI nếu lưu trước khi có bản sửa: /thiet-bi/leu-cam-trai-village-60-danh-cho-4-nguoi-dung-nhanh-chong thành /thiet-bi/leu-cam-trai-village-60-cho-4-nguoi. Đây là sản phẩm được nhắc nhiều nhất trong Cẩm nang: thân bài chon-leu-2-nguoi-hay-4-nguoi có link viết tay tới URL cũ; 6 bài gắn thẻ sản phẩm này (diem-cam-trai-gan-ha-noi, thue-hay-mua-do-cam-trai, cam-trai-cung-tre-nho, checklist-do-cam-trai-cho-nguoi-moi, chon-leu-2-nguoi-hay-4-nguoi, cam-trai-mua-mua). Cụm "dựng nhanh chóng" thì đưa vào câu đầu ô Mô tả, cùng khối Thông tin cơ bản, dưới ô Tên sản phẩm và ô Danh mục.

### Sản phẩm: Tăng bạt bé Naturehike

**Tên sản phẩm** — Mở https://bopcamping.com/admin/products (hoặc menu trái → Sản phẩm) → dòng "Tăng Bạt Bé - Che nắng mưa Dã ngoại Naturehike" → nút "Sửa" (mở /admin/products/{id}/sua) → khối "Thông tin cơ bản" → ô "Tên sản phẩm" → nút "Lưu thay đổi"

```
Tăng bạt bé che nắng mưa Naturehike
```

> NÊN SỬA, nhưng CHỜ dev làm xong phần giữ slug / chuyển hướng 301 rồi mới bấm lưu. Title hiện tại 69/60. Tên 46 xuống 35 ký tự, title 69 xuống 58/60. Giữ chữ "bé" cho khớp cách gọi trong các combo. URL ĐỔI nếu lưu trước khi có bản sửa: /thiet-bi/tang-bat-be-che-nang-mua-da-ngoai-naturehike thành /thiet-bi/tang-bat-be-che-nang-mua-naturehike. Bài Cẩm nang liên quan: thân bài cam-trai-mua-mua có link viết tay tới URL cũ; 4 bài gắn thẻ sản phẩm này (diem-cam-trai-gan-vinh-nghe-an, diem-cam-trai-gan-ha-noi, thue-hay-mua-do-cam-trai, cam-trai-mua-mua). Chữ "dã ngoại" bị bỏ thì đưa vào ô Mô tả, cùng khối Thông tin cơ bản.

### Sản phẩm: Tăng bạt lớn Cloud Skies 960

**Tên sản phẩm** — Mở https://bopcamping.com/admin/products (hoặc menu trái → Sản phẩm) → dòng "Tăng Bạt Lớn - Cloud Skies Tarp 960" → nút "Sửa" (mở /admin/products/{id}/sua) → khối "Thông tin cơ bản" → ô "Tên sản phẩm" → nút "Lưu thay đổi"

```
Tăng bạt lớn Cloud Skies Tarp 960
```

> LÀM NGAY ĐƯỢC, không bắt buộc. Chỉ bỏ dấu gạch và viết thường theo kiểu tiếng Việt. Tên 35 xuống 33 ký tự, title 58 xuống 56/60. URL GIỮ NGUYÊN /thiet-bi/tang-bat-lon-cloud-skies-tarp-960 (đã chạy thử), nên link trong thân bài cam-trai-mua-mua vẫn chạy.

### Sản phẩm: Thảm dã ngoại Happy

**Tên sản phẩm** — Mở https://bopcamping.com/admin/products (hoặc menu trái → Sản phẩm) → dòng "Thảm Dã Ngoại - Happy Ultrasonic Picnic Mat" → nút "Sửa" (mở /admin/products/{id}/sua) → khối "Thông tin cơ bản" → ô "Tên sản phẩm" → nút "Lưu thay đổi"

```
Thảm dã ngoại Happy Ultrasonic
```

> NÊN SỬA, nhưng CHỜ dev làm xong phần giữ slug / chuyển hướng 301 rồi mới bấm lưu. Title hiện tại 66/60. Tên 43 xuống 30 ký tự, title 66 xuống 53/60. Bỏ "Picnic Mat" vì trùng nghĩa với "thảm dã ngoại". URL ĐỔI nếu lưu trước khi có bản sửa: /thiet-bi/tham-da-ngoai-happy-ultrasonic-picnic-mat thành /thiet-bi/tham-da-ngoai-happy-ultrasonic. Bài Cẩm nang có gắn sản phẩm này: diem-cam-trai-gan-vinh-nghe-an (chỉ gắn thẻ, thân bài không có link).

### Combo (tuỳ chọn): BBQ Party

**Tên combo** — Mở https://bopcamping.com/admin/combos (hoặc menu trái → Combo) → dòng "BBQ Party" → nút "Sửa" → cửa sổ sửa combo → ô "Tên combo" → nút "Lưu lại"

```
Combo nướng BBQ 4 người
```

> TUỲ CHỌN. Title hiện tại 40/60, vẫn ổn. Tên mới 23 ký tự, title 54/60: "Combo nướng BBQ 4 người — Thuê trọn bộ tại BỐP CAMPING". Combo có 4 ghế và ô phù hợp ghi 4 người, nên số người trong tên khớp. Được: Google và khách hiểu ngay đây là combo nướng. Mất: cái tên khách đã quen, và URL cũ /combos/bbq-party (link tới URL này đang có trong thân bài diem-cam-trai-gan-vinh-nghe-an và nuong-bbq-khi-cam-trai). URL mới sẽ là /combos/combo-nuong-bbq-4-nguoi. Đánh giá của combo vẫn giữ.

### Combo (tuỳ chọn): Camping Couples

**Tên combo** — Mở https://bopcamping.com/admin/combos (hoặc menu trái → Combo) → dòng "Camping Couples" → nút "Sửa" → cửa sổ sửa combo → ô "Tên combo" → nút "Lưu lại"

```
Combo cắm trại cặp đôi
```

> TUỲ CHỌN. Title hiện tại 46/60. Tên mới 22 ký tự, title 53/60. Được: Google và khách hiểu nghĩa tiếng Việt. Mất: tên khách đã quen, và URL cũ /combos/camping-couples. Đây là combo được link nhiều nhất trong thân bài Cẩm nang: diem-cam-trai-gan-ha-noi, thue-hay-mua-do-cam-trai, checklist-do-cam-trai-cho-nguoi-moi, chon-leu-2-nguoi-hay-4-nguoi. URL mới sẽ là /combos/combo-cam-trai-cap-doi. Cần xem lại số liệu: combo có 2 ghế, 1 đệm hơi, nhưng ô "phù hợp" ghi 4 người, không khớp chữ "cặp đôi". Tôi không tự sửa con số này.

### Combo (tuỳ chọn): Camping Family

**Tên combo** — Mở https://bopcamping.com/admin/combos (hoặc menu trái → Combo) → dòng "Camping Family" → nút "Sửa" → cửa sổ sửa combo → ô "Tên combo" → nút "Lưu lại"

```
Combo cắm trại kèm bếp nướng
```

> TUỲ CHỌN. Title hiện tại 45/60. Tên mới 28 ký tự, title 59/60. Ba combo Camping Family, Camping Standard và Picnic Family 1 có cùng bộ chính (lều Village 6.0, 4 ghế SeaStar, bàn gấp) và chỉ khác một món. Vì vậy tên đặt theo món khác nhau đó: combo này có thêm bếp nướng BBQ. Được: khách phân biệt được ba combo, Google hiểu nghĩa. Mất: tên đã quen, và URL cũ /combos/camping-family (đang có trong thân bài thue-hay-mua-do-cam-trai, cam-trai-cung-tre-nho, checklist-do-cam-trai-cho-nguoi-moi). URL mới sẽ là /combos/combo-cam-trai-kem-bep-nuong. Nên đổi cả ba combo cùng một lúc.

### Combo (tuỳ chọn): Camping Family 2

**Tên combo** — Mở https://bopcamping.com/admin/combos (hoặc menu trái → Combo) → dòng "Camping Family 2" → nút "Sửa" → cửa sổ sửa combo → ô "Tên combo" → nút "Lưu lại"

```
Combo cắm trại 8 người
```

> TUỲ CHỌN. Title hiện tại 47/60. Tên mới 22 ký tự, title 53/60. Có lều nên gọi "cắm trại". Ô phù hợp ghi 8 người, cùng con số với trên tên. Một tên khác không ghi số người: "Combo lều cabin 8 người" (23 ký tự, title 54) cũng ghi số. Nếu bạn còn nghi sức chứa lều Village 13 (thông số hãng ghi 4–6 người, xem mục lều Village 13) thì dùng "Combo cắm trại gia đình lớn" (27 ký tự, title 58/60, URL /combos/combo-cam-trai-gia-dinh-lon). Được: Google và khách hiểu nghĩa. Mất: tên đã quen, và URL cũ /combos/camping-family-2 (đang có trong thân bài diem-cam-trai-gan-ha-noi). URL mới sẽ là /combos/combo-cam-trai-8-nguoi. Combo chỉ có ở kho Hà Nội.

### Combo (tuỳ chọn): Camping Standard

**Tên combo** — Mở https://bopcamping.com/admin/combos (hoặc menu trái → Combo) → dòng "Camping Standard" → nút "Sửa" → cửa sổ sửa combo → ô "Tên combo" → nút "Lưu lại"

```
Combo cắm trại kèm bạt lót
```

> TUỲ CHỌN. Title hiện tại 47/60. Tên mới 26 ký tự, title 57/60. Đặt tên theo món khác nhau là bạt lót lều (xem ghi chú ở Camping Family). Được: phân biệt được với hai combo cùng bộ, Google hiểu nghĩa. Mất: tên đã quen, và URL cũ /combos/camping-standard (đang có trong thân bài diem-cam-trai-gan-vinh-nghe-an, checklist-do-cam-trai-cho-nguoi-moi, cam-trai-mua-mua). URL mới sẽ là /combos/combo-cam-trai-kem-bat-lot. Cần xem lại số liệu: ô "phù hợp" ghi 6 người nhưng combo chỉ có lều 4 người và 4 ghế. Camping Family cùng bộ đó lại ghi 4 người. Tôi không tự sửa.

### Combo (tuỳ chọn): Chill Couples

**Tên combo** — Mở https://bopcamping.com/admin/combos (hoặc menu trái → Combo) → dòng "Chill Couples" → nút "Sửa" → cửa sổ sửa combo → ô "Tên combo" → nút "Lưu lại"

```
Combo dã ngoại cặp đôi
```

> TUỲ CHỌN. Title hiện tại 44/60. Tên mới 22 ký tự, title 53/60. Quy tắc đặt tên trong cả phần combo: có lều thì gọi "cắm trại", không có lều thì gọi "dã ngoại". Combo này có tăng bạt bé, không có lều. Được: không lẫn với "Combo cắm trại cặp đôi", Google hiểu nghĩa. Mất: tên đã quen, và URL cũ /combos/chill-couples (đang có trong thân bài diem-cam-trai-gan-ha-noi và cam-trai-mua-mua). URL mới sẽ là /combos/combo-da-ngoai-cap-doi. Combo chỉ có ở Hà Nội. Cần xem lại số liệu: ô "phù hợp" ghi 4 người nhưng combo có 2 ghế.

### Combo (tuỳ chọn): Picnic Family 1

**Tên combo** — Mở https://bopcamping.com/admin/combos (hoặc menu trái → Combo) → dòng "Picnic Family 1" → nút "Sửa" → cửa sổ sửa combo → ô "Tên combo" → nút "Lưu lại"

```
Combo cắm trại kèm thảm
```

> TUỲ CHỌN. Title hiện tại 46/60. Tên mới 23 ký tự, title 54/60. Combo có lều Village 6.0 nên gọi "cắm trại" theo quy tắc ở Chill Couples. Đặt tên theo món khác nhau là thảm dã ngoại (xem ghi chú ở Camping Family). Được: phân biệt được với hai combo cùng bộ, Google hiểu nghĩa. Mất: tên đã quen, và URL cũ /combos/picnic-family-1 (đang có trong thân bài cam-trai-cung-tre-nho). URL mới sẽ là /combos/combo-cam-trai-kem-tham. Cần xem lại số liệu: ô "phù hợp" ghi 6 người nhưng combo chỉ có lều 4 người và 4 ghế. Combo này cùng giá 614.000đ với Camping Standard.

### Combo (tuỳ chọn): Picnic Family 2

**Tên combo** — Mở https://bopcamping.com/admin/combos (hoặc menu trái → Combo) → dòng "Picnic Family 2" → nút "Sửa" → cửa sổ sửa combo → ô "Tên combo" → nút "Lưu lại"

```
Combo dã ngoại 8 người
```

> TUỲ CHỌN. Title hiện tại 46/60. Tên mới 22 ký tự, title 53/60. Không có lều (chỉ có tăng bạt lớn Cloud Skies 960) nên gọi "dã ngoại". 6 ghế và ô phù hợp ghi 8 người. Tên khác: "Combo tăng bạt lớn 8 người" (26 ký tự, title 57/60, URL /combos/combo-tang-bat-lon-8-nguoi). Được: Google và khách hiểu nghĩa, đi cặp với "Combo cắm trại 8 người". Mất: tên đã quen, và URL cũ /combos/picnic-family-2 (đang có trong thân bài cam-trai-mua-mua). URL mới sẽ là /combos/combo-da-ngoai-8-nguoi. Combo chỉ có ở Hà Nội.

### Combo (tuỳ chọn): Relax

**Tên combo** — Mở https://bopcamping.com/admin/combos (hoặc menu trái → Combo) → dòng "Relax" → nút "Sửa" → cửa sổ sửa combo → ô "Tên combo" → nút "Lưu lại"

```
Combo ghế thư giãn 2 người
```

> TUỲ CHỌN. Title hiện tại 36/60. Tên mới 26 ký tự, title 57/60. Combo gồm 2 ghế Nightfall T05 và 1 bàn gấp, ô phù hợp ghi 2 người. Được: tên hiện tại "Relax" không cho Google biết bên trong có gì, tên mới thì có. Mất: tên đã quen, và URL cũ /combos/relax (đang có trong thân bài diem-cam-trai-gan-vinh-nghe-an). URL mới sẽ là /combos/combo-ghe-thu-gian-2-nguoi.

**Gợi ý**

- ĐÍNH CHÍNH ĐỀ BÀI: đổi tên LÀM ĐỔI URL. Product.php và Combo.php không có code sinh slug, chỉ khai báo cột slug. Slug được tạo lại từ tên mới mỗi lần bấm lưu: app/Http/Controllers/Admin/ProductController.php dòng 255 và app/Http/Controllers/Admin/ComboController.php dòng 131, đều gọi Slug::unique(..., tên mới, id). Form admin không có ô slug. Code không lưu slug cũ và không có chuyển hướng 301. Trang sản phẩm dùng route /thiet-bi/{product:slug}, trang combo dùng /combos/{slug}, nên URL cũ sẽ báo lỗi 404. Tôi đã chạy thử Str::slug trên 11 tên hiện tại: cả 11 ra đúng slug đang có, chứng tỏ slug sinh từ tên.
- THỨ TỰ LÀM ĐÚNG: (1) Nhờ dev sửa để lúc bấm lưu thì giữ slug cũ (chỉ tạo slug khi thêm mới), hoặc lưu slug cũ và chuyển hướng 301. Có thể đưa vào nhánh release/seo-marketing-2026-10 (chưa deploy). Nên tạo một issue bd cho việc này. (2) Deploy đợt SEO và nạp 8 bài Cẩm nang soạn sẵn (PostSeeder, mục 5 của hướng dẫn). (3) Sau đó mới đổi tên 7 sản phẩm và các combo nếu muốn. Ba việc ghi "LÀM NGAY ĐƯỢC" (Bạt lót lều, Đệm hơi Horizon, Tăng bạt lớn) không đổi URL nên làm lúc nào cũng được. Bếp nướng BBQ giữ nguyên tên.
- NẾU ĐỔI TÊN TRƯỚC KHI NẠP 8 BÀI: database/seeders/PostSeeder.php (dòng 58–60) gắn sản phẩm/combo liên quan bằng whereIn theo slug và lặng lẽ bỏ qua slug không tìm thấy. Đổi tên trước thì bài sẽ mất phần SP/combo liên quan mà không báo lỗi. 6 trong 7 sản phẩm cần rút tên (trừ Nightfall T05) và cả 9 combo đều nằm trong danh sách gắn của các bài. Thẻ gắn SP/combo của bài ĐÃ nạp thì gắn theo id, nên đổi tên sau khi nạp không ảnh hưởng. Cách khác: cập nhật slug mới vào database/seeders/data/cam_nang_posts.json trong cùng đợt release. Seeder giữ nguyên bài đã có slug, nên bài đã nạp rồi thì phải sửa tay trong Admin → Cẩm nang.
- LINK SẼ BÁO 404 NẾU ĐỔI TÊN KHI CHƯA CÓ BẢN SỬA: (a) link viết tay trong thân bài Cẩm nang: chon-leu-2-nguoi-hay-4-nguoi (2 link, lều Village 13 và lều Village 6.0), cam-trai-mua-mua (1 link, tăng bạt bé), và toàn bộ 20 link combo trong thân 8 bài (bbq-party 2, camping-couples 5, camping-family 4, camping-standard 3, chill-couples 2, camping-family-2, picnic-family-1, picnic-family-2, relax mỗi cái 1); (b) link trong bài đăng Facebook/Zalo; (c) link trong các gói nội dung khác của đợt này (đang dùng slug cũ); (d) email đơn hàng và email mời đánh giá đã gửi, nếu có link sản phẩm/combo; (e) kết quả Google đang hiện URL cũ. Gửi lại sitemap.xml trong Search Console thì Google cập nhật dần, nhưng (d) và (e) vẫn báo 404 cho đến khi có chuyển hướng 301.
- Bảy sản phẩm có title vượt 60 ký tự: Bàn gấp (67), Ghế SeaStar (67), Ghế Nightfall T05 (86), Lều Village 13 (73), Lều Village 6.0 (84), Tăng bạt bé (69), Thảm Happy (66). Title dài chỉ bị Google cắt đuôi khi hiển thị, trang vẫn chạy bình thường. Vì vậy đây là việc NÊN làm, không gấp đến mức phải đổi ngay và làm hỏng link.
- CẦN SỬA FILE HƯỚNG DẪN: mục 6 của artifacts/huong_dan_seo_marketing_2026-10.md bảo "Chỉ sửa tên, giữ nguyên slug", nhưng admin không có ô slug và lưu là slug đổi theo tên. Cần sửa câu này cho khớp với thứ tự ở khuyến nghị 2. Câu "tên dài quá 60 ký tự" cũng phải sửa thành "tiêu đề Google dài quá 60 ký tự": tên chỉ cần ≤ 37 ký tự, phần còn lại là đuôi " — Thuê tại BỐP CAMPING". Con số 7 sản phẩm trong mục đó thì đã đúng.
- Cách đếm: title sản phẩm = tên + " — Thuê tại BỐP CAMPING" (23 ký tự). Title combo = tên + " — Thuê trọn bộ tại BỐP CAMPING" (31 ký tự). Đếm theo ký tự Unicode. Dòng "Tiêu đề trên Google: n/60 ký tự" dưới ô tên chỉ hiện ra SAU KHI đợt SEO được deploy lên production; production hiện tại chưa có. Trước lúc đó thì dùng số đếm trong gói này. Nên copy-dán nguyên văn tên: nếu gõ tay mà bộ đếm ra số lớn hơn, có thể bộ gõ đã tách dấu thành ký tự riêng. Google cắt title theo bề rộng màn hình, nên tên vừa chạm 60/60 (Ghế Nightfall T05) có thể vẫn bị cắt. Muốn chắc thì dùng tên ngắn hơn ghi trong phần ghi chú.
- Chữ bị bỏ khỏi tên ("gấp gọn", "dành cho cắm trại", "dựng nhanh chóng", "ngoài trời", "dã ngoại") nên đưa vào câu đầu ô Mô tả, cùng khối Thông tin cơ bản, nằm dưới ô Tên sản phẩm và ô Danh mục. Như vậy chữ vẫn có trên trang và trong mô tả cho Google, và ô tìm kiếm trên web shop vẫn khớp nhờ phần mô tả.
- Combo là việc TUỲ CHỌN và tôi khuyên chưa đổi. Cả 9 title combo đều ≤ 47 ký tự, không bị cắt. Mỗi lần đổi đều được và mất như nhau: được là Google và khách hiểu nghĩa tiếng Việt; mất là cái tên khách đã quen, cộng thêm URL /combos/<slug> cũ nếu chưa có bản sửa slug. Cách ít rủi ro hơn: giữ tên, viết nghĩa tiếng Việt vào câu đầu Mô tả combo (ví dụ "Combo cắm trại cho cặp đôi gồm..."). Nếu vẫn muốn đổi thì chờ bản sửa slug và đổi theo nhóm: ba combo cùng bộ lều Village 6.0 + 4 ghế + bàn gấp (Camping Family, Camping Standard, Picnic Family 1) và cặp 8 người (Camping Family 2, Picnic Family 2). Quy tắc dùng: có lều gọi "cắm trại", không có lều gọi "dã ngoại".
- Số liệu "phù hợp" cần bạn xem lại (tôi không tự sửa): Camping Couples và Chill Couples ghi 4 người nhưng chỉ có 2 ghế. Camping Standard và Picnic Family 1 ghi 6 người nhưng chỉ có lều 4 người và 4 ghế, trong khi Camping Family cùng bộ đó ghi 4 người. Sức chứa lều Village 13: thông số trên trang Naturehike ghi 4–6 người, còn tên sản phẩm và combo Camping Family 2 đang dùng 6–8 / 8 người.
- Lều Village 6.0 và Village 13 là dòng lều của Naturehike (đã kiểm trên trang hãng). Tên hiện tại không có chữ Naturehike. Muốn thêm thì shop phải chắc là hàng chính hãng. Lại gần chạm giới hạn: "Lều Naturehike Village 6.0 cho 4 người" dài 38 ký tự, title 61, vượt 1 ký tự, nên tôi không đề xuất.

## Hồ sơ Google Business Profile (Google Maps) cho 2 cơ sở Nghệ An và Hà Nội

### Hồ sơ Google – Cơ sở Nghệ An

**Tên doanh nghiệp** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa trang doanh nghiệp → Giới thiệu (About) → Tên doanh nghiệp

```
Bốp Camping
```

> Google không cho viết hoa toàn bộ tên (ví dụ "SUBWAY" là sai, "Subway" là đúng), cũng không cho thêm địa danh, từ khoá, số điện thoại hay khẩu hiệu. Vì vậy cả hai cơ sở dùng cùng tên "Bốp Camping", không thêm "Nghệ An" hay "Hà Nội". Trang Facebook cũng đang dùng tên này. Đổi tên sau khi đã xác minh có thể phải xác minh lại.

**Danh mục chính** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa trang doanh nghiệp → Giới thiệu (About) → Danh mục kinh doanh → ô "Danh mục chính"

```
Tent rental service
```

> Google không công bố danh sách danh mục chính thức; tên này lấy theo danh sách tổng hợp của bên thứ ba (Dalton Luka, cập nhật 9/5/2026). Không có danh mục "Camping equipment rental". Tên tiếng Việt trong giao diện chỉ là gợi ý, chọn theo nghĩa: gõ "cho thuê lều" rồi chọn mục có nghĩa "dịch vụ cho thuê lều". Nếu không chắc, tạm đổi ngôn ngữ tài khoản Google sang English để chọn đúng tên tiếng Anh rồi đổi lại. Lý do chọn: lều là món chính của shop. Lưu ý: trên Google, "Tent rental service" cũng hay được các bên cho thuê lều sự kiện, nhà bạt đám cưới dùng. Nếu gõ không ra, hoặc thấy hồ sơ hiện cạnh toàn đối thủ cho thuê lều sự kiện, thì dùng "Sports equipment rental service" làm danh mục chính (an toàn hơn) và chuyển "Tent rental service" xuống danh mục phụ. Không chọn "Camping store" (cửa hàng bán đồ cắm trại) vì shop chỉ cho thuê.

**Danh mục khác (danh mục phụ)** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa trang doanh nghiệp → Giới thiệu (About) → Danh mục kinh doanh → "Thêm danh mục khác" (mỗi dòng là một danh mục)

```
Sports equipment rental service
Equipment rental agency
```

> Cả hai có trong danh sách tổng hợp của bên thứ ba (Dalton Luka, cập nhật 9/5/2026); Google không công bố danh sách chính thức. Tên tiếng Việt chỉ là gợi ý, chọn theo nghĩa: gõ "cho thuê thiết bị" rồi chọn mục có nghĩa "dịch vụ cho thuê thiết bị thể thao" và "đại lý cho thuê thiết bị". "Equipment rental agency" thường được bên cho thuê máy móc, dụng cụ dùng; vẫn đúng với shop nhưng là mục rộng, có thể bỏ nếu muốn gọn. Có thể thêm Party equipment rental service (dịch vụ cho thuê đồ dùng tiệc) CHỈ khi khách thật sự hay thuê bàn ghế, bếp nướng cho tiệc nướng ngoài trời (combo BBQ Party). Google cho tối đa 9 danh mục khác nhưng khuyên chọn ít nhất có thể. Tổng cộng nên giữ 3–4 danh mục, không thêm danh mục chỉ để có thêm từ khoá.

**Nội dung mô tả** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa trang doanh nghiệp → Giới thiệu (About) → Nội dung mô tả về doanh nghiệp (hiện ở mục "Từ doanh nghiệp")

```
BỐP CAMPING cho thuê đồ cắm trại theo ngày cho khách ở TP Vinh, Cửa Lò và Nghi Lộc (Nghệ An). Shop có lều cắm trại 4 người, tăng bạt che nắng mưa, bạt lót lều, thảm dã ngoại, ghế gấp, ghế thư giãn, bàn gấp, đệm hơi và bếp nướng BBQ, cùng các combo gói sẵn cho cặp đôi, gia đình và nhóm bạn. Bạn chọn ngày nhận, ngày trả rồi đặt trước trên website hoặc gọi cho tụi mình; giá tính theo số ngày thuê. Đồ được giao nhận tận nơi, hoặc bạn hẹn giờ đến lấy. Bạn thanh toán tiền thuê và đặt cọc khi nhận đồ (COD), không cần trả trước. Túi ngủ, đèn, thực phẩm và dụng cụ nấu nướng bạn tự chuẩn bị. Giờ làm việc 8:00 – 21:00 tất cả các ngày trong tuần.
```

> 642/750 ký tự. Theo quy định của Google: không có đường link, không có mã HTML, không có giá hay khuyến mãi. Đoạn này cố ý ghi rõ túi ngủ, đèn, đồ nấu ăn là đồ khách tự chuẩn bị, vì shop không cho thuê những món đó.

**Số điện thoại chính** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa trang doanh nghiệp → Liên hệ (Contact) → Số điện thoại

```
0373655008
```

> Chọn mã quốc gia Việt Nam (+84). Google yêu cầu số điện thoại do chính doanh nghiệp quản lý và gọi được tới đúng cơ sở đó. Hai cơ sở đang dùng chung hotline, nên người nghe máy cần hỏi khách đang ở Vinh hay Hà Nội.

**Số điện thoại khác** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa trang doanh nghiệp → Liên hệ (Contact) → Số điện thoại → Thêm số điện thoại

```
0976544370
```

> Google cho thêm tối đa 2 số khác (di động hoặc cố định, không được là số fax). Nếu số 0976544370 thực tế do người ở một cơ sở nghe máy, nên đặt nó làm số chính cho cơ sở đó, vì Google khuyên mỗi cơ sở có số gọi thẳng tới cơ sở đó.

**Trang web** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa trang doanh nghiệp → Liên hệ (Contact) → Trang web

```
https://bopcamping.com/?utm_source=google&utm_medium=organic&utm_campaign=gbp_nghe-an
```

> Trang /thue-do-cam-trai/nghe-an chưa lên production (đang trả về 404, kiểm tra 14/10/2026; nằm trên nhánh release/seo-marketing-2026-10), nên tạm dùng trang chủ có gắn UTM. Sau khi chủ shop cho deploy đợt SEO và trang mở được, đổi ô này thành https://bopcamping.com/thue-do-cam-trai/nghe-an?utm_source=google&utm_medium=organic&utm_campaign=gbp_nghe-an. Đuôi UTM không làm link chuyển hướng sang trang khác, nên không vi phạm quy định cấm link chuyển hướng của Google. Lượt truy cập hiện trong GA4 với campaign gbp_nghe-an.

**Facebook** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa trang doanh nghiệp → Liên hệ (Contact) → Đường liên kết đến mạng xã hội → Facebook

```
https://www.facebook.com/people/B%E1%BB%91p-Camping/61592599559898
```

> Google chỉ hỗ trợ Facebook, Instagram, LinkedIn, Pinterest, TikTok, X và YouTube, mỗi nền tảng một link, và tính năng này chỉ có ở một số khu vực. Zalo không có trong danh sách. Link Zalo OA nên để trong tin nhắn trả lời khách.

**Trò chuyện (tuỳ chọn)** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa trang doanh nghiệp → Liên hệ (Contact) → Trò chuyện → Tin nhắn văn bản → Số điện thoại

```
0373655008
```

> Chỉ điền nếu Google có hiện mục "Trò chuyện" (Chat). Tính năng này chỉ có ở một số quốc gia/khu vực và chỉ dành cho hồ sơ đã xác minh. Chọn "Tin nhắn văn bản" (Text message), dán số này, chỉ khi số này nhận được SMS. Google chỉ cho chọn WhatsApp hoặc Tin nhắn văn bản, không có Zalo. Tính năng chat cũ của Google đã ngừng từ 31/7/2024.

**Địa chỉ doanh nghiệp (điền theo từng ô)** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa trang doanh nghiệp → Vị trí (Location) → Địa chỉ doanh nghiệp

```
Địa chỉ đường phố: Đường 72M, Khối Phong Vinh
Phường/Xã: Vinh Lộc
Tỉnh/Thành phố: Nghệ An
```

> Form địa chỉ của Google tách thành nhiều ô; điền mỗi dòng vào đúng ô của nó. Nếu ô Phường/Xã gợi ý "Phường Vinh Lộc" thì chọn gợi ý đó. Vẫn phải nhập địa chỉ để Google xác minh. Có hiện địa chỉ cho khách xem hay không: chỉ hiện khi tại đây có BIỂN HIỆU CỐ ĐỊNH và có người đón khách trong giờ 8:00–21:00. Nếu đây chỉ là kho, không có biển hiệu, thì chọn doanh nghiệp phục vụ tận nơi: ẩn địa chỉ, chỉ giữ Khu vực dịch vụ. Nếu cổng có biển BỐP CAMPING và luôn có người đón khách thì để hiện địa chỉ (hồ sơ kết hợp: vừa có địa chỉ vừa có khu vực dịch vụ).

**Khu vực dịch vụ (mỗi dòng nhập một lần)** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa trang doanh nghiệp → Vị trí (Location) → Khu vực dịch vụ → biểu tượng Chỉnh sửa

```
Vinh
Cửa Lò
Nghi Lộc
```

> Gõ từng dòng rồi chọn gợi ý có đuôi ", Nghệ An". Từ 1/7/2025 TP Vinh và Cửa Lò được sắp xếp lại thành 6 phường: Trường Vinh, Thành Vinh, Vinh Hưng, Vinh Phú, Vinh Lộc, Cửa Lò. Nếu Google không còn gợi ý "Vinh", chọn 6 phường này thay thế. Nếu không thấy "Nghi Lộc" thì chọn những xã mới Google gợi ý mà shop thật sự giao tới (chủ shop chốt). Không chọn cả tỉnh "Nghệ An" làm một mục. Tối đa 20 khu vực, không dùng bán kính, không quá khoảng 2 giờ lái xe.

**Giờ mở cửa** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa trang doanh nghiệp → Giờ (Hours) → Giờ làm việc

```
Thứ Hai – Chủ Nhật: 08:00 – 21:00
```

> Bật cả 7 ngày, mỗi ngày mở 08:00, đóng 21:00. Với hồ sơ phục vụ tận nơi, đây là giờ có người nghe máy và giao nhận. Trước Tết và các ngày lễ, nếu nghỉ hoặc đổi giờ thì đặt Giờ đặc biệt (special hours).

**Thuộc tính: Giao hàng (Delivery)** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa trang doanh nghiệp → Khác (More) → nhóm Tùy chọn dịch vụ (Service options) → Chỉnh sửa

```
Có
```

> Danh sách thuộc tính khác nhau theo danh mục và quốc gia. Không thấy mục này thì bỏ qua. Mục Thanh toán: chỉ chọn những cách khách thật sự trả khi nhận đồ.

**Thuộc tính: Nhận tại cửa hàng (In-store pickup)** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa trang doanh nghiệp → Khác (More) → nhóm Tùy chọn dịch vụ (Service options) → Chỉnh sửa

```
Có – CHỈ khi hồ sơ hiện địa chỉ; nếu đã ẩn địa chỉ thì để trống
```

> Bật khi hồ sơ hiện địa chỉ (có biển hiệu, có người đón khách). Hồ sơ phục vụ tận nơi đã ẩn địa chỉ thì để trống; khách vẫn hẹn giờ đến lấy qua điện thoại như bình thường.

**Thuộc tính: Doanh nghiệp nhỏ** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa trang doanh nghiệp → Khác (More) → nhóm Từ doanh nghiệp (From the business) → Chỉnh sửa

```
Có
```

> Google định nghĩa doanh nghiệp nhỏ là doanh thu dưới 10 triệu USD/năm và không phải nhượng quyền. Chỉ bật nếu mục này có ở Việt Nam. Các thuộc tính kiểu "Do phụ nữ sở hữu" chỉ bật nếu đúng sự thật.

**Dịch vụ: Cho thuê lều cắm trại (nhập tên này, dán mô tả bên dưới)** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa dịch vụ → Thêm dịch vụ khác → Thêm dịch vụ tuỳ chỉnh

```
Lều cắm trại Village 6.0 dành cho 4 người, dựng nhanh. Thuê theo ngày, đặt cọc và thanh toán khi nhận đồ.
```

> Tên dịch vụ: Cho thuê lều cắm trại. Nếu Google hiện phần giá: Loại giá: Cố định · Số tiền: 338000 (giá theo ngày; Google không ghi "/ngày" nên mô tả đã nói rõ thuê theo ngày). Không ghi giá hay số điện thoại vào tên dịch vụ, vì Google tự từ chối dịch vụ tuỳ chỉnh như vậy.

**Dịch vụ: Cho thuê tăng bạt (nhập tên này, dán mô tả bên dưới)** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa dịch vụ → Thêm dịch vụ khác → Thêm dịch vụ tuỳ chỉnh

```
Tăng bạt bé Naturehike và tăng bạt lớn Cloud Skies Tarp 960 để che nắng, che mưa khi picnic, cắm trại. Thuê theo ngày, đặt cọc và thanh toán khi nhận đồ.
```

> Tên dịch vụ: Cho thuê tăng bạt. Nếu Google hiện phần giá: Loại giá: Từ · Số tiền: 115000. Không ghi giá hay số điện thoại vào tên dịch vụ.

**Dịch vụ: Cho thuê bàn ghế dã ngoại (nhập tên này, dán mô tả bên dưới)** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa dịch vụ → Thêm dịch vụ khác → Thêm dịch vụ tuỳ chỉnh

```
Ghế cắm trại SeaStar đệm lưng cao, ghế thư giãn Naturehike Nightfall T05 và bàn gấp Naturehike màu kaki. Thuê lẻ từng món hoặc theo combo, tính theo ngày.
```

> Tên dịch vụ: Cho thuê bàn ghế dã ngoại. Nếu Google hiện phần giá: Loại giá: Từ · Số tiền: 75000. Không ghi giá hay số điện thoại vào tên dịch vụ.

**Dịch vụ: Cho thuê bếp nướng BBQ (nhập tên này, dán mô tả bên dưới)** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa dịch vụ → Thêm dịch vụ khác → Thêm dịch vụ tuỳ chỉnh

```
Bếp nướng BBQ gấp gọn, chỉnh được 3 mức chiều cao, kèm túi đựng và kẹp gắp. Thuê theo ngày. Thực phẩm và nhiên liệu nướng bạn tự chuẩn bị.
```

> Tên dịch vụ: Cho thuê bếp nướng BBQ. Nếu Google hiện phần giá: Loại giá: Cố định · Số tiền: 90000. Không ghi giá hay số điện thoại vào tên dịch vụ. Thông số (3 mức chiều cao, thu gọn 65x34x9 cm, kèm túi đựng và kẹp gắp thịt, nặng khoảng 6,7 kg) lấy từ trang sản phẩm trên production, kiểm tra 14/10/2026; vì nặng khoảng 6,7 kg nên không ghi "gọn nhẹ".

**Dịch vụ: Cho thuê đệm hơi (nhập tên này, dán mô tả bên dưới)** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa dịch vụ → Thêm dịch vụ khác → Thêm dịch vụ tuỳ chỉnh

```
Đệm hơi bơm hơi Horizon để nằm trong lều hoặc nghỉ ngoài trời. Thuê theo ngày, đặt cọc và thanh toán khi nhận đồ.
```

> Tên dịch vụ: Cho thuê đệm hơi. Nếu Google hiện phần giá: Loại giá: Cố định · Số tiền: 60000. Không ghi giá hay số điện thoại vào tên dịch vụ.

**Dịch vụ: Cho thuê thảm và bạt lót (nhập tên này, dán mô tả bên dưới)** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa dịch vụ → Thêm dịch vụ khác → Thêm dịch vụ tuỳ chỉnh

```
Bạt lót lều Naturehike và thảm dã ngoại Happy Ultrasonic Picnic Mat để lót dưới lều hoặc trải chỗ ngồi. Thuê theo ngày.
```

> Tên dịch vụ: Cho thuê thảm và bạt lót. Nếu Google hiện phần giá: Loại giá: Cố định · Số tiền: 50000 (cả hai món cùng giá). Không ghi giá hay số điện thoại vào tên dịch vụ.

**Dịch vụ: Combo đồ cắm trại và picnic (nhập tên này, dán mô tả bên dưới)** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa dịch vụ → Thêm dịch vụ khác → Thêm dịch vụ tuỳ chỉnh

```
Bộ đồ gói sẵn, thuê theo ngày, rẻ hơn thuê lẻ từng món: Relax, BBQ Party, Camping Couples, Camping Standard, Picnic Family 1, Camping Family.
```

> Tên dịch vụ: Combo đồ cắm trại và picnic. Nếu Google hiện phần giá: Loại giá: Từ · Số tiền: 190000. Không ghi giá hay số điện thoại vào tên dịch vụ. Đã đối chiếu: giá cả 6 combo có ở Nghệ An đều thấp hơn tổng giá thuê lẻ các món bên trong. Chill Couples, Picnic Family 2, Camping Family 2 chỉ có ở Hà Nội nên không liệt kê.

**Dịch vụ: Giao nhận đồ thuê (nhập tên này, dán mô tả bên dưới)** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Chỉnh sửa dịch vụ → Thêm dịch vụ khác → Thêm dịch vụ tuỳ chỉnh

```
Giao và nhận lại đồ tận nơi ở TP Vinh, Cửa Lò, Nghi Lộc theo ngày bắt đầu và kết thúc thuê, hoặc bạn hẹn giờ đến lấy.
```

> Tên dịch vụ: Giao nhận đồ thuê. Loại giá: Không có giá. Không ghi giá hay số điện thoại vào tên dịch vụ.

**Bài 1 – Cách thuê đồ theo ngày – nội dung** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Bài đăng → Thêm bài đăng → Cập nhật → ô nội dung (mô tả)

```
Thuê đồ cắm trại theo ngày ở Vinh, Cửa Lò, Nghi Lộc

Đi picnic hay cắm trại vài hôm mà chưa muốn mua cả bộ đồ, bạn có thể thuê theo ngày ở BỐP CAMPING rồi trả lại khi xong chuyến.

Cách thuê:
1. Chọn món đồ, chọn ngày nhận và ngày trả. Giá tính theo số ngày thuê.
2. Đặt trước trên website, hệ thống cho bạn thấy món nào còn trống trong những ngày đó.
3. Nhận đồ tận nơi hoặc hẹn giờ đến lấy. Bạn thanh toán tiền thuê và đặt cọc khi nhận đồ (COD).

Đồ cho thuê ở Nghệ An: lều cắm trại 4 người, tăng bạt, bạt lót lều, thảm dã ngoại, ghế gấp, ghế thư giãn, bàn gấp, đệm hơi và bếp nướng BBQ. Túi ngủ, đèn, thực phẩm và dụng cụ nấu nướng bạn tự chuẩn bị.

Giờ làm việc từ 8:00 đến 21:00 mỗi ngày. Bấm Tìm hiểu thêm để xem đồ và chọn ngày.
```

> 735 ký tự (giới hạn thường được nêu là 1.500 ký tự, theo Metricool; trang trợ giúp của Google không ghi con số). Nút: Tìm hiểu thêm. Dán URL ở mục kế tiếp vào ô link của nút. Kèm 1 ảnh thật của món hoặc combo nhắc trong bài.

**Bài 1 – Cách thuê đồ theo ngày – URL nút Tìm hiểu thêm** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Bài đăng → Thêm bài đăng → Cập nhật → Nút (+) → chọn "Tìm hiểu thêm" → ô Đường liên kết

```
https://bopcamping.com/?utm_source=google&utm_medium=organic&utm_campaign=gbp_nghe-an&utm_content=post-cach-thue
```

> Trang này đang chạy trên production (HTTP 200, kiểm tra 14/10/2026). utm_content cho biết bài nào mang khách về.

**Bài 2 – Combo BBQ Party – nội dung** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Bài đăng → Thêm bài đăng → Cập nhật → ô nội dung (mô tả)

```
Combo BBQ Party: 390.000đ/ngày cho nhóm 4 người

Một buổi nướng ngoài trời cần chỗ ngồi thoải mái, bàn để đồ và một chiếc bếp nướng. Combo BBQ Party gom sẵn cả ba:
- 4 ghế cắm trại SeaStar đệm lưng cao
- 1 bàn gấp Naturehike
- 1 bếp nướng BBQ gấp gọn, chỉnh được 3 mức chiều cao

Thuê theo ngày, rẻ hơn thuê lẻ từng món. Đồ được giao tận nơi ở Vinh, Cửa Lò, Nghi Lộc hoặc bạn hẹn giờ đến lấy; đặt cọc và thanh toán khi nhận đồ.

Thực phẩm, nhiên liệu nướng và bát đũa bạn tự chuẩn bị. Trước khi nướng ở công viên, bãi biển hay khu cắm trại, bạn nên hỏi quy định của nơi đó về lửa và dọn dẹp.

Bấm Tìm hiểu thêm để xem combo và chọn ngày.
```

> 637 ký tự. Nút: Tìm hiểu thêm. Dán URL ở mục kế tiếp vào ô link của nút. Kèm 1 ảnh thật của combo. Thuê lẻ 4 ghế + bàn + bếp là 470.000đ/ngày, nên câu "rẻ hơn thuê lẻ" là đúng.

**Bài 2 – Combo BBQ Party – URL nút Tìm hiểu thêm** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Bài đăng → Thêm bài đăng → Cập nhật → Nút (+) → chọn "Tìm hiểu thêm" → ô Đường liên kết

```
https://bopcamping.com/combos/bbq-party?utm_source=google&utm_medium=organic&utm_campaign=gbp_nghe-an&utm_content=post-bbq-party
```

> Trang này đang chạy trên production (HTTP 200, kiểm tra 14/10/2026). utm_content cho biết bài nào mang khách về.

**Bài 3 – Combo cho gia đình – nội dung** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Bài đăng → Thêm bài đăng → Cập nhật → ô nội dung (mô tả)

```
Cắm trại cùng gia đình: hai combo có sẵn lều

Nếu cả nhà muốn có chỗ nghỉ trong lều và chỗ ngồi quây quần, hai combo dưới đây đã gói sẵn đồ chính.

Camping Family – 640.000đ/ngày, gợi ý cho 4 người
1 lều Village 6.0, 4 ghế SeaStar, 1 bàn gấp, 1 bếp nướng BBQ

Picnic Family 1 – 614.000đ/ngày, gợi ý cho 6 người
1 lều Village 6.0, 1 thảm dã ngoại, 4 ghế SeaStar, 1 bàn gấp

Lều Village 6.0 dành cho 4 người, dựng nhanh. Giá tính theo ngày, đặt cọc và thanh toán khi nhận đồ. Túi ngủ, gối, đèn và đồ ăn bạn tự chuẩn bị.

Bấm Tìm hiểu thêm để xem các combo và chọn ngày nhận đồ.
```

> Khoảng 575 ký tự. Nút: Tìm hiểu thêm. Dán URL ở mục kế tiếp vào ô link của nút. Kèm 1 ảnh thật của combo. Số người gợi ý (4 và 6) lấy đúng từ dữ liệu combo trên website; câu "lều dành cho 4 người" giữ lại để khách nhóm 6 biết sức chứa của lều.

**Bài 3 – Combo cho gia đình – URL nút Tìm hiểu thêm** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Bài đăng → Thêm bài đăng → Cập nhật → Nút (+) → chọn "Tìm hiểu thêm" → ô Đường liên kết

```
https://bopcamping.com/combos?utm_source=google&utm_medium=organic&utm_campaign=gbp_nghe-an&utm_content=post-combo-gia-dinh
```

> Trang này đang chạy trên production (HTTP 200, kiểm tra 14/10/2026). utm_content cho biết bài nào mang khách về.

**Bài 4 – Tăng bạt che nắng mưa – nội dung** — https://business.google.com → chọn hồ sơ Bốp Camping (Nghệ An) → Bài đăng → Thêm bài đăng → Cập nhật → ô nội dung (mô tả)

```
Che nắng, che mưa cho buổi dã ngoại

Ngồi ngoài trời cả ngày thì một tấm tăng bạt giúp cả nhóm có bóng râm và chỗ trú khi trời đổ mưa. Ở Nghệ An, BỐP CAMPING có:
- Tăng bạt bé Naturehike: 115.000đ/ngày
- Tăng bạt lớn Cloud Skies Tarp 960, hợp với nhóm đông: 245.000đ/ngày
- Bạt lót lều Naturehike để lót dưới lều hoặc chỗ ngồi: 50.000đ/ngày

Chưa biết chọn cỡ nào, bạn bấm Gọi ngay, cho tụi mình biết số người và nơi định đi để được tư vấn. Đặt cọc và thanh toán khi nhận đồ. Giờ làm việc 8:00 – 21:00 mỗi ngày.
```

> 511 ký tự. Nút: Gọi ngay. Nút này gọi vào số điện thoại của hồ sơ, không cần link. Kèm 1 ảnh thật của tăng bạt đã căng.

**Danh sách ảnh cần chụp** — Google Tìm kiếm hoặc Maps → tìm "Bốp Camping" → Ảnh → Thêm ảnh / Thêm ảnh bìa / Thêm biểu trưng (giao diện tiếng Anh: Photos → Add photos / Add a cover photo / Add a logo)

```
1. Biểu trưng: logo BỐP CAMPING, ảnh vuông.
2. Ảnh bìa: một bộ đồ dựng hoàn chỉnh ngoài trời (lều, tăng bạt, bàn ghế), ảnh ngang, đủ sáng.
3. Ảnh thật từng món đang có ở Nghệ An, nền gọn: lều Village 6.0 đã dựng; tăng bạt bé và tăng bạt lớn Cloud Skies 960 khi căng; bạt lót lều; thảm dã ngoại; ghế SeaStar; ghế thư giãn Nightfall T05; bàn gấp Naturehike; đệm hơi Horizon đã bơm; bếp nướng BBQ mở ra.
4. Ảnh các combo bày sẵn: Relax, BBQ Party, Camping Couples, Camping Family, Camping Standard, Picnic Family 1.
5. Ảnh lúc giao nhận: nhân viên bàn giao và kiểm tra đồ cùng khách (chỉ để lộ mặt khách khi khách đồng ý, che biển số xe).
6. Ảnh khu chuẩn bị đồ: đồ đã vệ sinh, xếp gọn, sẵn túi đựng.
7. Chỉ khi hồ sơ hiện địa chỉ: ảnh mặt tiền có biển hiệu, chụp từ phía đường để khách dễ nhận ra.
8. Một video ngắn dưới 30 giây: dựng lều Village 6.0 hoặc căng tăng bạt.
Yêu cầu của Google: ảnh JPG hoặc PNG, 10 KB – 5 MB, nên từ 720 × 720 px (tối thiểu 250 × 250 px), rõ nét, đủ sáng, không chỉnh sửa nhiều hay lạm dụng bộ lọc. Video tối đa 30 giây, tối đa 75 MB, từ 720p.
```

> Dùng ảnh tự chụp, không dùng ảnh của hãng hay ảnh trên mạng. Không chèn số điện thoại hay chữ khuyến mãi lên ảnh. Google không đảm bảo ảnh bìa luôn là ảnh đầu tiên khách thấy. Mỗi tháng thêm vài ảnh mới từ các đơn thật.

**Tin nhắn xin đánh giá Google – bản Zalo** — business.google.com trên máy tính → hồ sơ Bốp Camping (Nghệ An) → Đọc bài đánh giá → Tăng số lượng bài đánh giá → biểu tượng Chia sẻ → biểu tượng Sao chép (lấy link); gửi qua Zalo

```
Chào [Tên khách], BỐP CAMPING cảm ơn bạn đã thuê đồ cho chuyến đi vừa rồi. Nếu tiện, bạn dành một phút viết vài dòng đánh giá trên Google giúp tụi mình nhé. Khen hay góp ý đều quý, shop đọc hết để làm tốt hơn: [LINK ĐÁNH GIÁ GOOGLE – NGHỆ AN]
```

> Thay [Tên khách] bằng tên khách và [LINK ĐÁNH GIÁ GOOGLE – NGHỆ AN] bằng link vừa sao chép. Mỗi cơ sở có link riêng. Gửi cho MỌI khách đã trả đồ xong, không chỉ khách vui: Google cấm chỉ xin đánh giá tốt và cấm ngăn khách đánh giá xấu. Không tặng voucher, giảm giá, quà hay mã giới thiệu để đổi lấy đánh giá. Không nhờ nhân viên hay người nhà viết. Mã QR xin đánh giá chỉ tạo được trên trình duyệt máy tính (chuột phải vào mã → "Lưu hình ảnh dưới dạng..."), có thể in dán vào túi đồ. Đánh giá nào cũng nên trả lời ngắn gọn, đúng chuyện, không quảng cáo.

**Tin nhắn xin đánh giá Google – bản SMS ngắn** — Như mục trên (lấy cùng link đánh giá Nghệ An); gửi qua SMS

```
Chao [Ten], BOP CAMPING cam on ban da thue do. Ban danh 1 phut danh gia tren Google giup shop nhe, khen hay gop y deu quy: [LINK]
```

> Khoảng 120 ký tự chưa tính link. Viết không dấu để SMS tính theo bảng ký tự thường (160 ký tự/tin); tin có dấu tiếng Việt chỉ được khoảng 70 ký tự/tin nên dễ bị tính 3–4 tin. Tổng cả link nên dưới 160 ký tự; tên khách dài thì bỏ phần [Ten]. Nếu muốn gửi có dấu: "[Tên], BỐP CAMPING cảm ơn bạn đã thuê đồ. Bạn dành 1 phút đánh giá trên Google giúp tụi mình nhé, khen hay góp ý đều quý: [LINK]" (khoảng 120 ký tự, sẽ tính nhiều tin). Quy định gửi cho mọi khách, không ưu đãi như bản Zalo.

### Hồ sơ Google – Cơ sở Hà Nội

**Tên doanh nghiệp** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa trang doanh nghiệp → Giới thiệu (About) → Tên doanh nghiệp

```
Bốp Camping
```

> Google không cho viết hoa toàn bộ tên (ví dụ "SUBWAY" là sai, "Subway" là đúng), cũng không cho thêm địa danh, từ khoá, số điện thoại hay khẩu hiệu. Vì vậy cả hai cơ sở dùng cùng tên "Bốp Camping", không thêm "Nghệ An" hay "Hà Nội". Đổi tên sau khi đã xác minh có thể phải xác minh lại.

**Danh mục chính** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa trang doanh nghiệp → Giới thiệu (About) → Danh mục kinh doanh → ô "Danh mục chính"

```
Tent rental service
```

> Google không công bố danh sách danh mục chính thức; tên này lấy theo danh sách tổng hợp của bên thứ ba (Dalton Luka, cập nhật 9/5/2026). Không có danh mục "Camping equipment rental". Tên tiếng Việt chỉ là gợi ý, chọn theo nghĩa: gõ "cho thuê lều" rồi chọn mục có nghĩa "dịch vụ cho thuê lều". Nếu không chắc, tạm đổi ngôn ngữ tài khoản Google sang English để chọn rồi đổi lại. Ở Hà Nội có nhiều bên cho thuê lều sự kiện, nhà bạt đám cưới dùng danh mục này; nếu gõ không ra hoặc hồ sơ hiện cạnh toàn đối thủ lều sự kiện, dùng "Sports equipment rental service" làm danh mục chính (an toàn hơn) và chuyển "Tent rental service" xuống danh mục phụ. Không chọn "Camping store" vì shop chỉ cho thuê.

**Danh mục khác (danh mục phụ)** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa trang doanh nghiệp → Giới thiệu (About) → Danh mục kinh doanh → "Thêm danh mục khác" (mỗi dòng là một danh mục)

```
Sports equipment rental service
Equipment rental agency
```

> Cả hai có trong danh sách tổng hợp của bên thứ ba (Dalton Luka, cập nhật 9/5/2026); Google không công bố danh sách chính thức. Tên tiếng Việt chỉ là gợi ý, chọn theo nghĩa: "dịch vụ cho thuê thiết bị thể thao" và "đại lý cho thuê thiết bị". "Equipment rental agency" thường được bên cho thuê máy móc, dụng cụ dùng; vẫn đúng với shop nhưng là mục rộng, có thể bỏ. Có thể thêm Party equipment rental service CHỈ khi khách thật sự hay thuê đồ cho tiệc nướng ngoài trời. Tổng cộng nên giữ 3–4 danh mục, không thêm danh mục chỉ để có thêm từ khoá.

**Nội dung mô tả** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa trang doanh nghiệp → Giới thiệu (About) → Nội dung mô tả về doanh nghiệp (hiện ở mục "Từ doanh nghiệp")

```
BỐP CAMPING cho thuê đồ cắm trại theo ngày cho khách ở nội thành Hà Nội. Shop có lều cắm trại 4 người, lều cabin cho nhóm 6 – 8 người, tăng bạt che nắng mưa, bạt lót lều, thảm dã ngoại, ghế gấp, ghế thư giãn, bàn gấp, đệm hơi và bếp nướng BBQ, cùng các combo gói sẵn cho cặp đôi, gia đình và nhóm đông. Bạn chọn ngày nhận, ngày trả rồi đặt trước trên website hoặc gọi cho tụi mình; giá tính theo số ngày thuê. Đồ được giao nhận tận nơi, hoặc bạn hẹn giờ đến lấy. Bạn thanh toán tiền thuê và đặt cọc khi nhận đồ (COD), không cần trả trước. Túi ngủ, đèn, thực phẩm và dụng cụ nấu nướng bạn tự chuẩn bị. Giờ làm việc 8:00 – 21:00 tất cả các ngày trong tuần.
```

> 654/750 ký tự. Không có đường link, mã HTML, giá hay khuyến mãi. Cố ý ghi rõ túi ngủ, đèn, đồ nấu ăn là đồ khách tự chuẩn bị, vì shop không cho thuê những món đó.

**Số điện thoại chính** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa trang doanh nghiệp → Liên hệ (Contact) → Số điện thoại

```
0373655008
```

> Chọn mã quốc gia Việt Nam (+84). Google yêu cầu số điện thoại do chính doanh nghiệp quản lý và gọi được tới đúng cơ sở đó. Hai cơ sở đang dùng chung hotline, nên người nghe máy cần hỏi khách đang ở Vinh hay Hà Nội.

**Số điện thoại khác** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa trang doanh nghiệp → Liên hệ (Contact) → Số điện thoại → Thêm số điện thoại

```
0976544370
```

> Google cho thêm tối đa 2 số khác (không được là số fax). Nếu số 0976544370 thực tế do người ở một cơ sở nghe máy, nên đặt nó làm số chính cho cơ sở đó.

**Trang web** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa trang doanh nghiệp → Liên hệ (Contact) → Trang web

```
https://bopcamping.com/?utm_source=google&utm_medium=organic&utm_campaign=gbp_ha-noi
```

> Trang /thue-do-cam-trai/ha-noi chưa lên production (đang trả về 404, kiểm tra 14/10/2026; nằm trên nhánh release/seo-marketing-2026-10), nên tạm dùng trang chủ có gắn UTM. Sau khi chủ shop cho deploy đợt SEO và trang mở được, đổi ô này thành https://bopcamping.com/thue-do-cam-trai/ha-noi?utm_source=google&utm_medium=organic&utm_campaign=gbp_ha-noi. Đuôi UTM không làm link chuyển hướng, nên không vi phạm quy định của Google. Lượt truy cập hiện trong GA4 với campaign gbp_ha-noi.

**Facebook** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa trang doanh nghiệp → Liên hệ (Contact) → Đường liên kết đến mạng xã hội → Facebook

```
https://www.facebook.com/people/B%E1%BB%91p-Camping/61592599559898
```

> Google chỉ hỗ trợ Facebook, Instagram, LinkedIn, Pinterest, TikTok, X và YouTube, mỗi nền tảng một link, và chỉ ở một số khu vực. Zalo không có trong danh sách. Link Zalo OA nên để trong tin nhắn trả lời khách.

**Trò chuyện (tuỳ chọn)** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa trang doanh nghiệp → Liên hệ (Contact) → Trò chuyện → Tin nhắn văn bản → Số điện thoại

```
0373655008
```

> Chỉ điền nếu Google có hiện mục "Trò chuyện" (Chat); tính năng này chỉ có ở một số khu vực và chỉ cho hồ sơ đã xác minh. Chọn "Tin nhắn văn bản", dán số này, chỉ khi số này nhận được SMS. Google chỉ cho WhatsApp hoặc Tin nhắn văn bản, không có Zalo. Tính năng chat cũ của Google đã ngừng từ 31/7/2024.

**Địa chỉ doanh nghiệp (điền theo từng ô)** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa trang doanh nghiệp → Vị trí (Location) → Địa chỉ doanh nghiệp

```
Địa chỉ đường phố: Tòa nhà Homeland, [tòa / tầng / số căn]
Phường/Xã: Bồ Đề
Tỉnh/Thành phố: Hà Nội
```

> Thay [tòa / tầng / số căn] bằng chi tiết thật nơi để đồ, để thư hay mã xác minh gửi đúng chỗ; nếu ô Phường/Xã gợi ý "Phường Bồ Đề" thì chọn gợi ý đó. Homeland là chung cư: nếu đồ để trong căn hộ, không có biển hiệu cố định và người đón khách trong giờ làm việc, thì chọn doanh nghiệp phục vụ tận nơi và ẩn địa chỉ (vẫn phải nhập để xác minh). Chỉ để hiện địa chỉ nếu có biển hiệu BỐP CAMPING cố định và luôn có người đón khách trong giờ 8:00–21:00.

**Khu vực dịch vụ (mỗi dòng nhập một lần)** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa trang doanh nghiệp → Vị trí (Location) → Khu vực dịch vụ → biểu tượng Chỉnh sửa

```
Ba Đình
Hoàn Kiếm
Hai Bà Trưng
Đống Đa
Tây Hồ
Cầu Giấy
Thanh Xuân
Hoàng Mai
Long Biên
Bắc Từ Liêm
Nam Từ Liêm
Hà Đông
```

> Đây là 12 quận nội thành cũ của Hà Nội. Gõ từng tên rồi chọn gợi ý có đuôi ", Hà Nội"; bỏ quận nào shop không giao tới. Từ 1/7/2025 Hà Nội không còn cấp quận. Nếu Google không gợi ý tên quận cũ, nhập các phường mới trong nội thành mà shop thật sự giao tới, do chủ shop chốt, tối đa 20 (ví dụ Bồ Đề, Việt Hưng, Long Biên, Hoàn Kiếm, Ba Đình, Cầu Giấy, Đống Đa...). KHÔNG chọn một mục duy nhất "Hà Nội": làm vậy khách sẽ hiểu shop giao toàn thành phố, kể cả Sóc Sơn, Ba Vì, Mỹ Đức, trong khi shop chỉ giao nội thành. Nếu Google chỉ gợi ý được mỗi "Hà Nội" thì dừng lại, hỏi chủ shop trước khi chọn. Không dùng bán kính, không quá khoảng 2 giờ lái xe.

**Giờ mở cửa** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa trang doanh nghiệp → Giờ (Hours) → Giờ làm việc

```
Thứ Hai – Chủ Nhật: 08:00 – 21:00
```

> Bật cả 7 ngày, mỗi ngày mở 08:00, đóng 21:00. Với hồ sơ phục vụ tận nơi, đây là giờ có người nghe máy và giao nhận. Trước Tết và các ngày lễ, nếu nghỉ hoặc đổi giờ thì đặt Giờ đặc biệt.

**Thuộc tính: Giao hàng (Delivery)** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa trang doanh nghiệp → Khác (More) → nhóm Tùy chọn dịch vụ (Service options) → Chỉnh sửa

```
Có
```

> Danh sách thuộc tính khác nhau theo danh mục và quốc gia. Không thấy mục này thì bỏ qua. Mục Thanh toán: chỉ chọn những cách khách thật sự trả khi nhận đồ.

**Thuộc tính: Nhận tại cửa hàng (In-store pickup)** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa trang doanh nghiệp → Khác (More) → nhóm Tùy chọn dịch vụ (Service options) → Chỉnh sửa

```
Để trống (chỉ chọn "Có" nếu hồ sơ Hà Nội hiện địa chỉ)
```

> Mặc định để trống vì kho Hà Nội ở chung cư, nhiều khả năng phải ẩn địa chỉ (xem mục Địa chỉ). Chỉ bật khi hồ sơ hiện địa chỉ, có biển hiệu và người đón khách. Khách vẫn hẹn giờ đến lấy qua điện thoại như bình thường.

**Thuộc tính: Doanh nghiệp nhỏ** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa trang doanh nghiệp → Khác (More) → nhóm Từ doanh nghiệp (From the business) → Chỉnh sửa

```
Có
```

> Google định nghĩa doanh nghiệp nhỏ là doanh thu dưới 10 triệu USD/năm và không phải nhượng quyền. Chỉ bật nếu mục này có ở Việt Nam. Thuộc tính kiểu "Do phụ nữ sở hữu" chỉ bật nếu đúng sự thật.

**Dịch vụ: Cho thuê lều cắm trại (nhập tên này, dán mô tả bên dưới)** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa dịch vụ → Thêm dịch vụ khác → Thêm dịch vụ tuỳ chỉnh

```
Lều Village 6.0 cho 4 người và lều cabin tự động Village 13 cho nhóm 6 – 8 người. Thuê theo ngày, đặt cọc và thanh toán khi nhận đồ.
```

> Tên dịch vụ: Cho thuê lều cắm trại. Nếu Google hiện phần giá: Loại giá: Từ · Số tiền: 338000 (giá theo ngày). Không ghi giá hay số điện thoại vào tên dịch vụ.

**Dịch vụ: Cho thuê tăng bạt (nhập tên này, dán mô tả bên dưới)** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa dịch vụ → Thêm dịch vụ khác → Thêm dịch vụ tuỳ chỉnh

```
Tăng bạt bé Naturehike và tăng bạt lớn Cloud Skies Tarp 960 để che nắng, che mưa khi picnic, cắm trại. Thuê theo ngày, đặt cọc và thanh toán khi nhận đồ.
```

> Tên dịch vụ: Cho thuê tăng bạt. Nếu Google hiện phần giá: Loại giá: Từ · Số tiền: 115000. Không ghi giá hay số điện thoại vào tên dịch vụ.

**Dịch vụ: Cho thuê bàn ghế dã ngoại (nhập tên này, dán mô tả bên dưới)** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa dịch vụ → Thêm dịch vụ khác → Thêm dịch vụ tuỳ chỉnh

```
Ghế cắm trại SeaStar đệm lưng cao, ghế thư giãn Naturehike Nightfall T05 và bàn gấp Naturehike màu kaki. Thuê lẻ từng món hoặc theo combo, tính theo ngày.
```

> Tên dịch vụ: Cho thuê bàn ghế dã ngoại. Nếu Google hiện phần giá: Loại giá: Từ · Số tiền: 75000. Không ghi giá hay số điện thoại vào tên dịch vụ.

**Dịch vụ: Cho thuê bếp nướng BBQ (nhập tên này, dán mô tả bên dưới)** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa dịch vụ → Thêm dịch vụ khác → Thêm dịch vụ tuỳ chỉnh

```
Bếp nướng BBQ gấp gọn, chỉnh được 3 mức chiều cao, kèm túi đựng và kẹp gắp. Thuê theo ngày. Thực phẩm và nhiên liệu nướng bạn tự chuẩn bị.
```

> Tên dịch vụ: Cho thuê bếp nướng BBQ. Nếu Google hiện phần giá: Loại giá: Cố định · Số tiền: 90000. Không ghi giá hay số điện thoại vào tên dịch vụ. Thông số lấy từ trang sản phẩm trên production (kiểm tra 14/10/2026: 3 mức chiều cao, thu gọn 65x34x9 cm, kèm túi đựng và kẹp gắp thịt, nặng khoảng 6,7 kg).

**Dịch vụ: Cho thuê đệm hơi (nhập tên này, dán mô tả bên dưới)** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa dịch vụ → Thêm dịch vụ khác → Thêm dịch vụ tuỳ chỉnh

```
Đệm hơi bơm hơi Horizon để nằm trong lều hoặc nghỉ ngoài trời. Thuê theo ngày, đặt cọc và thanh toán khi nhận đồ.
```

> Tên dịch vụ: Cho thuê đệm hơi. Nếu Google hiện phần giá: Loại giá: Cố định · Số tiền: 60000. Không ghi giá hay số điện thoại vào tên dịch vụ.

**Dịch vụ: Cho thuê thảm và bạt lót (nhập tên này, dán mô tả bên dưới)** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa dịch vụ → Thêm dịch vụ khác → Thêm dịch vụ tuỳ chỉnh

```
Bạt lót lều Naturehike và thảm dã ngoại Happy Ultrasonic Picnic Mat để lót dưới lều hoặc trải chỗ ngồi. Thuê theo ngày.
```

> Tên dịch vụ: Cho thuê thảm và bạt lót. Nếu Google hiện phần giá: Loại giá: Cố định · Số tiền: 50000. Không ghi giá hay số điện thoại vào tên dịch vụ.

**Dịch vụ: Combo đồ cắm trại và picnic (nhập tên này, dán mô tả bên dưới)** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa dịch vụ → Thêm dịch vụ khác → Thêm dịch vụ tuỳ chỉnh

```
Bộ đồ gói sẵn, thuê theo ngày, rẻ hơn thuê lẻ từng món: Relax, Chill Couples, BBQ Party, Camping Couples, Camping Standard, Picnic Family 1, Camping Family, Picnic Family 2, Camping Family 2.
```

> Tên dịch vụ: Combo đồ cắm trại và picnic. Nếu Google hiện phần giá: Loại giá: Từ · Số tiền: 190000. Không ghi giá hay số điện thoại vào tên dịch vụ. Đã đối chiếu: giá cả 9 combo đều thấp hơn tổng giá thuê lẻ các món bên trong.

**Dịch vụ: Giao nhận đồ thuê (nhập tên này, dán mô tả bên dưới)** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Chỉnh sửa dịch vụ → Thêm dịch vụ khác → Thêm dịch vụ tuỳ chỉnh

```
Giao và nhận lại đồ tận nơi trong nội thành Hà Nội theo ngày bắt đầu và kết thúc thuê, hoặc bạn hẹn giờ đến lấy.
```

> Tên dịch vụ: Giao nhận đồ thuê. Loại giá: Không có giá. Không ghi giá hay số điện thoại vào tên dịch vụ.

**Bài 1 – Cách thuê đồ theo ngày – nội dung** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Bài đăng → Thêm bài đăng → Cập nhật → ô nội dung (mô tả)

```
Thuê đồ cắm trại theo ngày ở nội thành Hà Nội

Đi picnic cuối tuần hay cắm trại qua đêm mà chưa muốn mua cả bộ đồ, bạn có thể thuê theo ngày ở BỐP CAMPING rồi trả lại khi xong chuyến.

Cách thuê:
1. Chọn món đồ, chọn ngày nhận và ngày trả. Giá tính theo số ngày thuê.
2. Đặt trước trên website, hệ thống cho bạn thấy món nào còn trống trong những ngày đó.
3. Nhận đồ tận nơi trong nội thành hoặc hẹn giờ đến lấy. Bạn thanh toán tiền thuê và đặt cọc khi nhận đồ (COD).

Ở Hà Nội có thêm lều cabin Village 13 cho nhóm 6 – 8 người, bên cạnh lều 4 người, tăng bạt, thảm, bàn ghế, đệm hơi và bếp nướng BBQ. Túi ngủ, đèn, thực phẩm và dụng cụ nấu nướng bạn tự chuẩn bị.

Giờ làm việc từ 8:00 đến 21:00 mỗi ngày. Bấm Tìm hiểu thêm để xem đồ và chọn ngày.
```

> 747 ký tự (giới hạn thường được nêu là 1.500 ký tự, theo Metricool; trang trợ giúp của Google không ghi con số). Nút: Tìm hiểu thêm. Dán URL ở mục kế tiếp vào ô link của nút. Kèm 1 ảnh thật của món nhắc trong bài.

**Bài 1 – Cách thuê đồ theo ngày – URL nút Tìm hiểu thêm** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Bài đăng → Thêm bài đăng → Cập nhật → Nút (+) → chọn "Tìm hiểu thêm" → ô Đường liên kết

```
https://bopcamping.com/?utm_source=google&utm_medium=organic&utm_campaign=gbp_ha-noi&utm_content=post-cach-thue
```

> Trang này đang chạy trên production (HTTP 200, kiểm tra 14/10/2026). utm_content cho biết bài nào mang khách về.

**Bài 2 – Combo cho nhóm 8 người – nội dung** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Bài đăng → Thêm bài đăng → Cập nhật → ô nội dung (mô tả)

```
Đi nhóm 8 người: combo Camping Family 2

Combo dành cho nhóm đông, hiện chỉ có ở cơ sở Hà Nội:
- 1 lều cabin tự động Village 13 (6 – 8 người)
- 4 ghế SeaStar đệm lưng cao
- 1 bàn gấp
- 1 bếp nướng BBQ
- 1 thảm dã ngoại

Giá 775.000đ/ngày, gợi ý cho 8 người, rẻ hơn thuê lẻ từng món. Nếu chỉ cần lều, lều cabin Village 13 có giá 498.000đ/ngày.

Combo có 4 ghế, nhóm 8 người có thể thuê thêm ghế lẻ. Túi ngủ, đèn, thực phẩm và nhiên liệu nướng bạn tự chuẩn bị. Đặt cọc và thanh toán khi nhận đồ.

Bấm Tìm hiểu thêm để xem combo và chọn ngày.
```

> 539 ký tự. Nút: Tìm hiểu thêm. Dán URL ở mục kế tiếp vào ô link của nút. Kèm 1 ảnh thật của lều cabin Village 13 đã dựng. Thuê lẻ các món trong combo là 1.018.000đ/ngày, nên câu "rẻ hơn thuê lẻ" là đúng.

**Bài 2 – Combo cho nhóm 8 người – URL nút Tìm hiểu thêm** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Bài đăng → Thêm bài đăng → Cập nhật → Nút (+) → chọn "Tìm hiểu thêm" → ô Đường liên kết

```
https://bopcamping.com/combos/camping-family-2?utm_source=google&utm_medium=organic&utm_campaign=gbp_ha-noi&utm_content=post-camping-family-2
```

> Trang này đang chạy trên production (HTTP 200, kiểm tra 14/10/2026). utm_content cho biết bài nào mang khách về.

**Bài 3 – Picnic Yên Sở, bãi đá sông Hồng – nội dung** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Bài đăng → Thêm bài đăng → Cập nhật → ô nội dung (mô tả)

```
Picnic cuối tuần ở Yên Sở hay bãi đá sông Hồng

Hai điểm trải thảm ngồi ngoài trời nửa ngày ngay trong Hà Nội:
- Công viên Yên Sở (Hoàng Mai): bãi cỏ rộng, nhiều cây xanh và hồ lớn. Theo các trang du lịch, vào cổng không mất vé, gửi xe có thu phí và công viên đóng cửa khoảng 19:00. Giờ đóng cửa và phí gửi xe có thể thay đổi, bạn nên hỏi ban quản lý công viên trước khi đi; muốn ở qua đêm hay đốt lửa cũng cần hỏi trước.
- Bãi đá sông Hồng (ngõ 264 Âu Cơ, Tây Hồ): ven sông, có vườn hoa theo mùa. Vào khu vườn hoa thường phải trả phí ở cổng, mức thu thay đổi theo thời điểm, nên bạn hỏi giá trước và xin vé.

Đồ gợi ý: combo Relax 190.000đ/ngày (2 ghế thư giãn Nightfall T05, 1 bàn gấp) hoặc combo Chill Couples 336.000đ/ngày (1 tăng bạt bé, 2 ghế SeaStar, 1 bạt lót lều, 1 bàn gấp). Nhóm đông có Picnic Family 2 giá 680.000đ/ngày, gợi ý cho 8 người. Trước khi mang tăng bạt hay lều vào, bạn hỏi nơi đó có cho dựng đồ mang từ ngoài vào không.

Nhớ mang túi đựng rác và dọn sạch chỗ ngồi trước khi về.
```

> Khoảng 1.000 ký tự. Nút: Tìm hiểu thêm. Dán URL ở mục kế tiếp vào ô link của nút. Kèm 1 ảnh thật của combo Relax hoặc Chill Couples. Thông tin Yên Sở (không thu vé vào cổng, gửi xe có phí, đóng cửa khoảng 19:00, qua đêm phải liên hệ ban quản lý) khớp giữa Mia.vn và Mytour; các trang này là bài du lịch, không phải thông báo chính thức, nên bài có câu nhắc hỏi lại trước khi đi và không ghi số tiền gửi xe. Chuyện thu phí ở cổng bãi đá sông Hồng lấy từ báo Đại biểu Nhân dân (2/2024); bài cố ý không ghi số tiền vì mức thu hay đổi. Không tìm được nguồn chính thức về việc hai nơi này có cho mang lều, tăng bạt từ ngoài vào hay không, nên bài nhắc khách hỏi trước; combo Relax (chỉ ghế và bàn) được đưa lên đầu vì ít rủi ro nhất. Không gắn link /diem-cam-trai/... vì các trang đó chưa lên production (đang 404).

**Bài 3 – Picnic Yên Sở, bãi đá sông Hồng – URL nút Tìm hiểu thêm** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Bài đăng → Thêm bài đăng → Cập nhật → Nút (+) → chọn "Tìm hiểu thêm" → ô Đường liên kết

```
https://bopcamping.com/combos?utm_source=google&utm_medium=organic&utm_campaign=gbp_ha-noi&utm_content=post-picnic-cuoi-tuan
```

> Trang này đang chạy trên production (HTTP 200, kiểm tra 14/10/2026). utm_content cho biết bài nào mang khách về.

**Bài 4 – Đặt sớm cho cuối tuần – nội dung** — https://business.google.com → chọn hồ sơ Bốp Camping (Hà Nội) → Bài đăng → Thêm bài đăng → Cập nhật → ô nội dung (mô tả)

```
Đặt đồ sớm cho cuối tuần và ngày lễ

Mỗi món đồ có số lượng giới hạn theo từng ngày, nên vào những hôm nhiều người đi chơi, lều và tăng bạt có thể hết sớm. Bạn đặt trước để giữ đồ:
- Chọn ngày nhận và ngày trả trên website để xem món nào còn trống.
- Hoặc bấm Gọi ngay, cho tụi mình biết số người, số đêm và nơi định đi để được gợi ý đồ phù hợp.

Bạn thanh toán tiền thuê và đặt cọc khi nhận đồ (COD). Đồ được giao nhận tận nơi trong nội thành Hà Nội hoặc bạn hẹn giờ đến lấy. Giờ làm việc 8:00 – 21:00 mỗi ngày.
```

> 512 ký tự. Nút: Gọi ngay. Nút này gọi vào số điện thoại của hồ sơ, không cần link. Kèm 1 ảnh thật của lều hoặc tăng bạt.

**Danh sách ảnh cần chụp** — Google Tìm kiếm hoặc Maps → tìm "Bốp Camping" → Ảnh → Thêm ảnh / Thêm ảnh bìa / Thêm biểu trưng (giao diện tiếng Anh: Photos → Add photos / Add a cover photo / Add a logo)

```
1. Biểu trưng: logo BỐP CAMPING, ảnh vuông.
2. Ảnh bìa: một bộ đồ dựng hoàn chỉnh ngoài trời (lều, tăng bạt, bàn ghế), ảnh ngang, đủ sáng.
3. Ảnh thật từng món đang có ở Hà Nội, nền gọn: lều Village 6.0 và lều cabin Village 13 đã dựng; tăng bạt bé và tăng bạt lớn Cloud Skies 960 khi căng; bạt lót lều; thảm dã ngoại; ghế SeaStar; ghế thư giãn Nightfall T05; bàn gấp Naturehike; đệm hơi Horizon đã bơm; bếp nướng BBQ mở ra.
4. Ảnh các combo bày sẵn: Relax, Chill Couples, BBQ Party, Camping Couples, Camping Standard, Picnic Family 1, Camping Family, Picnic Family 2, Camping Family 2.
5. Ảnh lúc giao nhận: nhân viên bàn giao và kiểm tra đồ cùng khách (chỉ để lộ mặt khách khi khách đồng ý, che biển số xe).
6. Ảnh khu chuẩn bị đồ: đồ đã vệ sinh, xếp gọn, sẵn túi đựng.
7. Chỉ khi hồ sơ hiện địa chỉ: ảnh mặt tiền có biển hiệu, chụp từ phía đường để khách dễ nhận ra.
8. Một video ngắn dưới 30 giây: dựng lều cabin Village 13 hoặc căng tăng bạt lớn.
Yêu cầu của Google: ảnh JPG hoặc PNG, 10 KB – 5 MB, nên từ 720 × 720 px (tối thiểu 250 × 250 px), rõ nét, đủ sáng, không chỉnh sửa nhiều hay lạm dụng bộ lọc. Video tối đa 30 giây, tối đa 75 MB, từ 720p.
```

> Dùng ảnh tự chụp, không dùng ảnh của hãng hay ảnh trên mạng. Không chèn số điện thoại hay chữ khuyến mãi lên ảnh. Nếu hồ sơ Hà Nội ẩn địa chỉ thì bỏ ảnh số 7. Mỗi tháng thêm vài ảnh mới từ các đơn thật.

**Tin nhắn xin đánh giá Google – bản Zalo** — business.google.com trên máy tính → hồ sơ Bốp Camping (Hà Nội) → Đọc bài đánh giá → Tăng số lượng bài đánh giá → biểu tượng Chia sẻ → biểu tượng Sao chép (lấy link); gửi qua Zalo

```
Chào [Tên khách], BỐP CAMPING cảm ơn bạn đã thuê đồ cho chuyến đi vừa rồi. Nếu tiện, bạn dành một phút viết vài dòng đánh giá trên Google giúp tụi mình nhé. Khen hay góp ý đều quý, shop đọc hết để làm tốt hơn: [LINK ĐÁNH GIÁ GOOGLE – HÀ NỘI]
```

> Thay [Tên khách] và [LINK ĐÁNH GIÁ GOOGLE – HÀ NỘI] bằng tên khách và link vừa sao chép. Mỗi cơ sở có link riêng. Gửi cho MỌI khách đã trả đồ xong, không chỉ khách vui: Google cấm chỉ xin đánh giá tốt và cấm ngăn khách đánh giá xấu. Không tặng voucher, giảm giá, quà hay mã giới thiệu để đổi lấy đánh giá. Không nhờ nhân viên hay người nhà viết. Mã QR xin đánh giá chỉ tạo được trên trình duyệt máy tính, có thể in dán vào túi đồ. Đánh giá nào cũng nên trả lời ngắn gọn, đúng chuyện, không quảng cáo.

**Tin nhắn xin đánh giá Google – bản SMS ngắn** — Như mục trên (lấy cùng link đánh giá Hà Nội); gửi qua SMS

```
Chao [Ten], BOP CAMPING cam on ban da thue do. Ban danh 1 phut danh gia tren Google giup shop nhe, khen hay gop y deu quy: [LINK]
```

> Khoảng 120 ký tự chưa tính link. Viết không dấu để SMS tính theo bảng ký tự thường (160 ký tự/tin); tin có dấu chỉ được khoảng 70 ký tự/tin nên dễ bị tính 3–4 tin. Tổng cả link nên dưới 160 ký tự; tên khách dài thì bỏ phần [Ten]. Bản có dấu nếu muốn: "[Tên], BỐP CAMPING cảm ơn bạn đã thuê đồ. Bạn dành 1 phút đánh giá trên Google giúp tụi mình nhé, khen hay góp ý đều quý: [LINK]". Quy định gửi cho mọi khách, không ưu đãi như bản Zalo.

**Gợi ý**

- Trước khi tạo hồ sơ: mở Google Maps và tìm "Bốp Camping" ở Vinh và Hà Nội. Nếu đã có hồ sơ (do khách hoặc Google tự tạo) thì bấm nhận quyền sở hữu, đừng tạo thêm hồ sơ trùng. Mỗi cơ sở cần một hồ sơ riêng, tạo ở business.google.com. Chốt tên "Bốp Camping" trước khi xác minh, vì đổi tên sau khi đã xác minh có thể phải xác minh lại.
- Quyết định hiện hay ẩn địa chỉ cho TỪNG cơ sở trước khi điền các mục khác. Google chỉ cho hiện địa chỉ khi tại đó có biển hiệu cố định và có người đón khách trong giờ làm việc. Nếu không: ẩn địa chỉ, chỉ giữ Khu vực dịch vụ, để trống thuộc tính "Nhận tại cửa hàng" và bỏ ảnh mặt tiền. Kho Hà Nội ở tòa Homeland (chung cư) nhiều khả năng phải chọn kiểu phục vụ tận nơi, nên gói này để trống "Nhận tại cửa hàng" cho Hà Nội theo mặc định.
- Hỏi & đáp (Q&A) không còn nữa: Google ngừng API Hỏi & đáp từ 3/11/2025, rồi ngày 3/12/2025 bỏ phần Hỏi & đáp khỏi hồ sơ, thay bằng nút hỏi đáp tự động bằng AI trên Google Maps (chưa có ở mọi nước). AI này trả lời dựa trên thông tin hồ sơ, ảnh và đánh giá, nên điền đủ các mục trong gói và xin đánh giá đều đặn. Câu hỏi thường gặp của shop hiện nằm ở khối FAQ trang chủ (sửa ở https://bopcamping.com/admin/faqs); nên bổ sung các câu khách hay hỏi (cọc, giao nhận, giờ làm việc) vào đó. Các bài Cẩm nang (/cam-nang) CHƯA lên production (trả về 404, kiểm tra 14/10/2026); đừng gắn link /cam-nang vào hồ sơ hay bài đăng cho tới khi đợt SEO được deploy và trang mở được.
- Bài đăng: mỗi tuần đăng 1 bài (có thể bật "Lên lịch cho bài đăng này"). Không ghi số điện thoại, email hay tên tài khoản mạng xã hội trong nội dung, vì Google có thể gỡ bài có thông tin liên hệ chưa xác minh; dùng nút "Gọi ngay" thay thế. Bài cũ hơn 6 tháng tự vào lưu trữ. Chỉ dùng loại bài "Ưu đãi" khi có khuyến mãi thật trên website. Khi giá trên website đổi thì sửa hoặc xoá các bài và dịch vụ có ghi giá cũ.
- Đợt SEO (các trang /thue-do-cam-trai/nghe-an, /thue-do-cam-trai/ha-noi, /diem-cam-trai, /cam-nang) đang ở nhánh release/seo-marketing-2026-10 và chưa lên production (hiện trả về 404). Việc deploy do chủ shop quyết định. Chỉ khi đã deploy và các trang đó mở được mới đổi ô Trang web của mỗi hồ sơ sang link trang khu vực có UTM (đã ghi sẵn trong mục Trang web). Hiện để trang chủ có UTM.
- Link Google Maps trên website: ô "Link Google Maps" của mỗi kho hiện dùng cho bước chọn tự đến lấy trong giỏ hàng (khách bấm để chỉ đường tới chỗ lấy đồ); nút "Mở bản đồ" trên trang khu vực chỉ có sau khi deploy đợt SEO. Vì vậy: CHỈ dán link hồ sơ Google vào đây nếu hồ sơ của kho đó HIỆN địa chỉ. Nếu hồ sơ là phục vụ tận nơi (ẩn địa chỉ, không có ghim), giữ hoặc dán link Google Maps thường của đúng điểm lấy đồ (Google Maps → tìm địa chỉ → Chia sẻ → Sao chép đường liên kết) để khách tự đến lấy vẫn chỉ đường được. Cách sửa (làm trên máy tính, vì nút Sửa chỉ hiện khi rê chuột): Admin → menu "Điểm cắm trại" (https://bopcamping.com/admin/camping-spots) → khối "Vị trí phục vụ" → rê chuột vào ô kho (Nghệ An / Hà Nội) để hiện nút bút chì "Sửa" → ô "Link Google Maps" → "Lưu lại".
- Giữ thông tin khớp nhau ở mọi nơi: địa chỉ, số điện thoại và giờ làm việc trên Google, website và trang Facebook phải giống nhau. Website có thể tiếp tục viết BỐP CAMPING theo kiểu thương hiệu; riêng tên trên Google thì viết "Bốp Camping" theo quy định không viết hoa toàn bộ.
- Hai hồ sơ đang dùng chung hotline 0373655008. Google khuyên mỗi cơ sở có số gọi thẳng tới cơ sở đó. Nếu thực tế 0976544370 do người ở một kho nghe máy, nên đặt số đó làm số chính cho kho đó; nếu không thì giữ như gói này và dặn người nghe máy luôn hỏi khách ở Vinh hay Hà Nội.
- Đánh giá: gửi tin nhắn xin đánh giá cho mọi khách sau khi trả đồ, cả khách có góp ý. Không tặng quà, giảm giá hay mã giới thiệu để đổi lấy đánh giá, không nhờ nhân viên hay người nhà viết. Trả lời mọi đánh giá, kể cả đánh giá xấu, ngắn gọn và đúng chuyện. Dùng bản Zalo khi có Zalo của khách; bản SMS không dấu để gói gọn trong 1 tin.
- Google duyệt chỉnh sửa thường mất khoảng 10 phút nhưng có thể đến 30 ngày; trong lúc chờ đừng sửa đi sửa lại cùng một mục. Danh mục, thuộc tính, mục Trò chuyện, phần giá dịch vụ và đường liên kết mạng xã hội thay đổi theo quốc gia: không thấy mục nào trong gói thì bỏ qua, đừng chọn mục khác chỉ cho có.
- Khu vực dịch vụ: từ 1/7/2025 Việt Nam bỏ cấp huyện/quận, nên Google có thể gợi ý tên phường, xã mới thay cho "Vinh", "Nghi Lộc" hay các quận cũ của Hà Nội. Chỉ chọn những nơi shop thật sự giao tới (chủ shop chốt), tối đa 20 khu vực, không quá khoảng 2 giờ lái xe từ kho, và không bao giờ chọn cả tỉnh/thành phố làm một mục.
- Theo dõi hiệu quả: trong GA4 lọc chiến dịch gbp_nghe-an và gbp_ha-noi (utm_content cho biết bài đăng nào mang khách về). Trong business.google.com xem mục Hiệu suất để biết số cuộc gọi, lượt xem đường đi và lượt bấm vào website của từng cơ sở.

## Link gắn UTM cho từng kênh

Luôn viết nguồn giống hệt nhau (facebook, không lúc fb lúc facebook), viết thường, không dấu, không khoảng trắng.

### Facebook

**Link trong bài đăng Fanpage** — Dán vào nền tảng tương ứng (không phải admin BỐP CAMPING)

```
https://bopcamping.com/?utm_source=facebook&utm_medium=social&utm_campaign=fanpage&utm_content=post
```

> Đổi utm_content theo từng bài nếu muốn so sánh, ví dụ post-combo-bbq.

**Link ở phần Giới thiệu của Fanpage** — Dán vào nền tảng tương ứng (không phải admin BỐP CAMPING)

```
https://bopcamping.com/?utm_source=facebook&utm_medium=social&utm_campaign=fanpage&utm_content=bio
```

**Quảng cáo Facebook/Instagram (ô URL trang web)** — Dán vào nền tảng tương ứng (không phải admin BỐP CAMPING)

```
https://bopcamping.com/?utm_source=facebook&utm_medium=cpc&utm_campaign=ten-chien-dich
```

> Thay ten-chien-dich bằng tên chiến dịch không dấu, ví dụ thang11-gia-dinh.

### Instagram

**Link ở bio Instagram** — Dán vào nền tảng tương ứng (không phải admin BỐP CAMPING)

```
https://bopcamping.com/?utm_source=instagram&utm_medium=social&utm_campaign=bio
```

### TikTok

**Link ở bio TikTok** — Dán vào nền tảng tương ứng (không phải admin BỐP CAMPING)

```
https://bopcamping.com/?utm_source=tiktok&utm_medium=social&utm_campaign=bio
```

**Quảng cáo TikTok** — Dán vào nền tảng tương ứng (không phải admin BỐP CAMPING)

```
https://bopcamping.com/?utm_source=tiktok&utm_medium=cpc&utm_campaign=ten-chien-dich
```

> Thay ten-chien-dich như trên.

### Zalo

**Link gửi trong Zalo OA / tin nhắn Zalo** — Dán vào nền tảng tương ứng (không phải admin BỐP CAMPING)

```
https://bopcamping.com/?utm_source=zalo&utm_medium=social&utm_campaign=oa
```

### Google Business Profile

**Ô Trang web — kho Nghệ An** — Dán vào nền tảng tương ứng (không phải admin BỐP CAMPING)

```
https://bopcamping.com/thue-do-cam-trai/nghe-an?utm_source=google&utm_medium=organic&utm_campaign=gbp_nghe-an
```

> Trỏ thẳng về trang khu vực, khách thấy ngay đồ có sẵn ở kho đó.

**Ô Trang web — kho Hà Nội** — Dán vào nền tảng tương ứng (không phải admin BỐP CAMPING)

```
https://bopcamping.com/thue-do-cam-trai/ha-noi?utm_source=google&utm_medium=organic&utm_campaign=gbp_ha-noi
```

**Gợi ý**

- Link rút gọn (bit.ly…) vẫn giữ được UTM nếu bạn rút gọn chính link có UTM.
- Không gắn UTM cho link nội bộ trong website (ví dụ link trong bài Cẩm nang) — làm vậy sẽ ghi đè nguồn thật của khách.

## Bài Cẩm nang

Nội dung HTML đầy đủ: `database/seeders/data/cam_nang_posts.json`.

### 7 điểm cắm trại, dã ngoại gần Vinh, Nghệ An: đồi cỏ, hồ, biển, đảo chè

- Đường dẫn: `diem-cam-trai-gan-vinh-nghe-an`
- Tiêu đề SEO: 7 điểm cắm trại, dã ngoại gần Vinh
- Mô tả SEO: 7 điểm cắm trại, dã ngoại quanh Vinh: núi Lam Thành, đập Ba Khe, Eo Gió, Cửa Lò, Bãi Lữ, đảo chè... kèm mùa đi, đồ nên mang và lưu ý an toàn.
- Tóm tắt: Núi Lam Thành, đập Ba Khe, Eo Gió, Cửa Lò, Bãi Lữ, đảo chè Thanh Chương, Hòn Mát: chỗ nào ngồi tới tối, chỗ nào đi trong ngày, mùa đi, đồ nên mang và lưu ý an toàn.
- 1832 từ

### Điểm cắm trại gần Hà Nội: 7 nơi từ nửa ngày đến 2N1Đ

- Đường dẫn: `diem-cam-trai-gan-ha-noi`
- Tiêu đề SEO: Cắm trại gần Hà Nội: 7 điểm trong ngày, 2N1Đ
- Mô tả SEO: Yên Sở, bãi đá sông Hồng, Đồng Mô, Ba Vì, Thung Trâu: 7 điểm cắm trại gần Hà Nội chia theo nửa ngày, đi trong ngày và 2N1Đ, kèm lưu ý thực tế.
- Tóm tắt: Từ picnic nửa ngày ở Yên Sở, bãi đá sông Hồng đến 2N1Đ ở Ba Vì, Thung Trâu: 7 điểm cắm trại gần Hà Nội kèm lưu ý an toàn, vé và gợi ý đồ cần mang.
- 1886 từ

### Thuê hay mua đồ cắm trại? Cách tính điểm hòa vốn bằng giá thuê thật

- Đường dẫn: `thue-hay-mua-do-cam-trai`
- Tiêu đề SEO: Thuê hay mua đồ cắm trại? Cách tính hòa vốn
- Mô tả SEO: Cắm trại vài lần mỗi năm thì thuê hay mua đồ lợi hơn? Công thức tính điểm hòa vốn với giá thuê thật, kèm các chi phí ẩn khi sở hữu lều, bàn ghế.
- Tóm tắt: Thuê hay mua đồ cắm trại tùy số chuyến bạn đi mỗi năm. Tự tính điểm hòa vốn bằng giá thuê theo ngày thật và xem những chi phí ẩn khi tự sở hữu lều, bàn ghế.
- 1724 từ

### Cắm trại cùng trẻ nhỏ: chọn chỗ, giấc ngủ, an toàn và đồ cần mang

- Đường dẫn: `cam-trai-cung-tre-nho`
- Tiêu đề SEO: Cắm trại cùng trẻ nhỏ: kinh nghiệm cho bố mẹ
- Mô tả SEO: Kinh nghiệm cắm trại cùng trẻ nhỏ: chọn chỗ phẳng có bóng mát, chỗ ngủ cho bé, chống nắng, chống muỗi, an toàn gần nước, bếp than và đồ cần mang.
- Tóm tắt: Chọn chỗ bằng phẳng, gần bãi xe và nhà vệ sinh, giữ nếp ngủ cho bé, chống nắng, chống muỗi đúng tuổi và không rời mắt khi bé ở gần nước, bếp than. Kèm danh sách đồ và lịch trình gợi ý.
- 1501 từ

### Nướng BBQ khi cắm trại: thực đơn 4 người, chuẩn bị và an toàn lửa

- Đường dẫn: `nuong-bbq-khi-cam-trai`
- Tiêu đề SEO: Nướng BBQ khi cắm trại: thực đơn và an toàn
- Mô tả SEO: Thực đơn nướng BBQ cho 4 người đi trong ngày và 2 ngày 1 đêm, cách sơ chế ở nhà, chọn than, an toàn lửa, hỏi nơi cắm trại có cho nướng và dọn tro.
- Tóm tắt: Thực đơn nướng mẫu cho 4 người đi trong ngày và 2 ngày 1 đêm, cách ướp và giữ lạnh đồ sống, mồi than, an toàn lửa và dọn tro sạch sẽ trước khi về.
- 1498 từ

### Checklist đồ cắm trại cho người mới: mang gì, thuê gì

- Đường dẫn: `checklist-do-cam-trai-cho-nguoi-moi`
- Tiêu đề SEO: Checklist đồ cắm trại qua đêm cho người mới
- Mô tả SEO: Đi cắm trại qua đêm lần đầu cần mang gì? Checklist 5 nhóm đồ: ngủ, sinh hoạt, ăn uống, chiếu sáng, cá nhân. Ghi rõ món nào thuê được, món nào tự chuẩn bị.
- Tóm tắt: Chuẩn bị cho đêm đầu tiên ngoài trời: soát đồ theo 5 nhóm, kiểm tra lều và bơm đệm hơi khi nhận, và nhớ quy tắc an toàn không bao giờ đưa bếp vào lều.
- 1438 từ

### Chọn lều 2 người hay 4 người? Cách tính cỡ lều cho đúng

- Đường dẫn: `chon-leu-2-nguoi-hay-4-nguoi`
- Tiêu đề SEO: Chọn lều 2 người hay 4 người cho đúng
- Mô tả SEO: Đi 2 người nên thuê lều 4 người nếu có xe chở đồ. Cách tính cỡ lều theo số người, kiểu chuyến đi, kèm gợi ý lều cho nhóm 2, 4, 6–8 người.
- Tóm tắt: Lều "4 người" là 4 người nằm sát vai, chưa tính đồ đạc. Cách tính cỡ lều theo số người, kiểu chuyến đi, và lều Village nào hợp với cặp đôi, gia đình hay nhóm đông.
- 1498 từ

### Cắm trại mùa mưa: chọn chỗ, căng tăng bạt và giữ lều khô ráo

- Đường dẫn: `cam-trai-mua-mua`
- Tiêu đề SEO: Cắm trại mùa mưa: cách giữ lều khô ráo
- Mô tả SEO: Cắm trại mùa mưa ở Hà Nội, Nghệ An: tháng nào mưa nhiều, cách chọn chỗ dựng lều, căng tăng bạt, lót đáy, giữ đồ khô và khi nào nên hoãn.
- Tóm tắt: Cách chọn chỗ dựng lều, căng tăng bạt, lót đáy và giữ đồ khô khi cắm trại mùa mưa, kèm mốc mùa mưa ở Hà Nội, Nghệ An và lúc nên hoãn vì dông sét, lũ.
- 1422 từ
