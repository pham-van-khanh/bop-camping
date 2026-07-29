# PRD — Vận hành giao nhận: tài khoản shipper, gán đơn, lộ trình, in/xuất, thông báo

**Loại:** Large feature (~11–13 ngày) · **Ngày:** 2026-07-29 · **Nguồn:** yêu cầu chủ shop 28–29/07/2026
**Nối tiếp:** [prd_delivery_schedule.md](artifacts/prd_delivery_schedule.md) (chốt giờ + lịch giao — đã xong, chờ test stg)
**ADR:** [adr_shipper_role_and_access.md](artifacts/adr_shipper_role_and_access.md) · [adr_pdf_generation.md](artifacts/adr_pdf_generation.md)

## 1. Vấn đề

Đợt trước đã có giờ giao/thu do admin chốt và lịch tháng cho shipper xem. Nhưng:

- Shipper phải **dùng chung tài khoản admin** của chủ shop → thấy toàn bộ doanh thu, khách hàng, sản phẩm. Không giao được cho người ngoài.
- Không biết **đơn nào của ai** khi có 2 shipper trở lên; không có thứ tự đi giao → shipper tự đoán đường.
- Chủ shop phải **gọi/nhắn tay** cho shipper mỗi ngày; không có file lịch để gửi.
- Đánh dấu đã giao/đã thu vẫn phải chủ shop tự bấm sau khi nghe điện.

## 2. Mục tiêu

1. **Tài khoản shipper riêng**, đăng nhập bằng SĐT + mật khẩu, chỉ thấy đơn được gán cho mình.
2. Admin **gán shipper** cho từng lượt (giao / thu); lịch tự sắp theo giờ đã chốt.
3. Shipper mở đơn có **nút chỉ đường** (mở Google Maps trên điện thoại) và **tự đánh dấu đã giao / đã thu**.
4. Admin **gửi lịch cho shipper qua email** bằng 1 nút, và có **nút Chat Zalo** mở sẵn tin nhắn để gửi tay.

### Không làm (đã chốt với chủ shop 29/07)

- **Không** có link xem lịch không cần đăng nhập (rò dữ liệu khách — xem ADR mục 5).
- **Không** gọi API tối ưu lộ trình / vẽ bản đồ trong app: **không gửi địa chỉ, SĐT khách sang nhà cung cấp thứ ba nào**. Chỉ mở link Google Maps từ thiết bị của shipper, thứ tự do admin sắp tay.
- **Không** dùng Zalo OA / ZNS API (cần OA xác minh doanh nghiệp + phí + duyệt template). Chỉ email tự động + nút chat Zalo thủ công. Khi nào có OA thì mở việc riêng.
- **Không** chốt giờ ở cấp **đơn gộp** — cố tình, vì mỗi đợt giao một ngày khác nhau; chỉ chốt ở **đơn con**. (Xác nhận lại 29/07.)
- **Không** tự gợi ý giờ, **không** chặn giờ ngoài khung giờ shop (ô phụ phí ngoài giờ đã có sẵn).
- Không theo dõi GPS shipper, không chấm công, không tính lương/hoa hồng.
- **Không in / PDF / CSV lịch giao** — chủ shop bỏ ngày 29/07/2026 (shipper xem trên điện thoại là đủ). Xem [adr_pdf_generation.md](artifacts/adr_pdf_generation.md) (trạng thái Rejected) nếu sau này cần lại.

## 3. Mô hình dữ liệu

### `users`
| Cột | Kiểu | Nghĩa |
|---|---|---|
| `is_shipper` | boolean, default false, index | Vai shipper (song song `is_admin` — xem ADR mục 3) |

### `orders`
| Cột | Kiểu | Nghĩa |
|---|---|---|
| `pickup_shipper_id` | FK `users.id` nullable, `nullOnDelete` | Ai đi **giao** đơn này |
| `return_shipper_id` | FK `users.id` nullable, `nullOnDelete` | Ai đi **thu** đơn này |

Gán theo **từng lượt** vì lượt giao và lượt thu là 2 ngày khác nhau, có thể 2 người khác nhau. Nullable ⇒ đơn cũ không cần backfill; chưa gán = "chưa có shipper".

**Không** thêm cột "đã giao/đã thu": việc đó là chuyển trạng thái `confirmed → renting → returned` sẵn có, để không có 2 nguồn chân lý (xem ADR mục 3).

## 4. Yêu cầu chức năng

### FR-1 · Tài khoản shipper
- Trang `/admin/users` thêm tab **"Shipper"**: danh sách, thêm (tên, SĐT, mật khẩu ≥6, email tuỳ chọn), sửa, đổi mật khẩu, bật/tắt vai, xoá.
- Xoá/tắt vai shipper đang được gán đơn **tương lai** → cảnh báo kèm số đơn, buộc admin gán lại trước (hoặc xác nhận để đơn về "chưa gán").
- Đăng nhập tại `/shipper/dang-nhap` (SĐT + mật khẩu, throttle `10,1`); không phải shipper → chặn + thông báo chung.
- Shared prop `auth.user.is_shipper` để FE điều hướng đúng khu vực.

### FR-2 · Admin gán shipper + sắp thứ tự
- Trong `/admin/lich-giao`, mỗi card đơn có ô chọn shipper (`select`) cho đúng lượt của nó (card ở mục "Cần giao" gán `pickup_shipper_id`, "Cần thu" gán `return_shipper_id`).
- **Gán nhanh cả ngày**: chọn shipper → nút "Gán tất cả đơn chưa có shipper" cho mục đang xem.
- Thứ tự hiển thị: theo **giờ đã chốt**, đơn chưa chốt giờ xuống cuối. **BỎ kéo-thả sắp thứ tự** (chủ shop 29/07/2026: thừa chức năng) → không có cột `*_sort`.
- Bộ lọc **theo shipper** trên trang lịch (Tất cả / từng shipper / Chưa gán) — áp dụng cho cả in/PDF/CSV.
- Chỉ gán được đơn chưa `returned`/`cancelled`; đơn cha (`is_parent`) không gán (gán ở từng đợt con).

### FR-3 · Trang của shipper
- `/shipper/lich-giao?date=` — mobile-first, **chỉ đơn được gán cho chính mình** trong ngày đó, chia 2 mục Giao / Thu, theo thứ tự admin đã sắp.
- Mỗi đơn: giờ đã chốt (hoặc "chưa chốt giờ"), tên khách, SĐT bấm gọi, địa chỉ, **nút "Chỉ đường"** (`https://www.google.com/maps/dir/?api=1&destination=<địa chỉ đã encode>`), món + số lượng, **số tiền cần thu (lượt giao) / cần hoàn (lượt thu)**, ghi chú shipper.
- Nút **"Đã giao"** (chỉ khi status `confirmed` và là lượt giao) / **"Đã thu"** (chỉ khi `renting` và là lượt thu) — hỏi xác nhận trước khi đổi, vì việc này gửi mail cho khách.
- Không có nav admin; chỉ có lịch của mình + đăng xuất. Điều hướng ngày: hôm nay ± vài ngày (không cho xem quá khứ xa, không xem đơn không phải của mình).
- Bảo mật: mọi truy vấn kẹp `where(shipper_id = auth id)`; đổi trạng thái phải kiểm lại quyền sở hữu lượt đó (chống IDOR — CWE-639).

### FR-4 · Thông báo
- Nút **"Gửi lịch qua email"** trên trang lịch (theo ngày + shipper đang lọc) → gửi `ShipperScheduleMail` (ShouldQueue) tới email shipper: danh sách theo thứ tự, giờ, địa chỉ, SĐT khách, tiền cần thu, ghi chú.
- **Tự động 06:00 hằng ngày**: gửi lịch hôm đó cho từng shipper có đơn (command `shipper:send-daily-schedule`, idempotent, lịch trong `routes/console.php`). Phụ thuộc cron trên server — bead `bopcamping-ybsm`.
- Shipper **không có email thật** (`@bopcamping.local`) → bỏ qua, hiện cảnh báo trong admin thay vì lỗi.
- Nút **"Chat Zalo"** cạnh mỗi shipper: mở `https://zalo.me/<sđt>` — chủ shop tự dán/gửi. Không API, không tự động.

## 5. Acceptance criteria

**Tài khoản & quyền**
- [ ] Admin tạo được tài khoản shipper; shipper đăng nhập được ở `/shipper/dang-nhap`.
- [ ] Tài khoản khách (không mật khẩu thật) và user thường **không** vào được `/shipper/*` → redirect `shipper.login`.
- [ ] Shipper **không** vào được `/admin/*` (redirect `admin.login`); admin không bị mất quyền gì.
- [ ] Shipper mở đơn **không phải của mình** (đổi `order` id trong request đổi trạng thái) → 403/404, không đổi được dữ liệu.
- [ ] Shipper không thấy dashboard/thống kê/khách hàng/sản phẩm ở bất kỳ đường nào.

**Gán & thứ tự**
- [ ] Gán shipper cho 1 lượt → lưu đúng cột (`pickup_*` cho lượt giao, `return_*` cho lượt thu).
- [ ] "Gán tất cả đơn chưa có shipper" chỉ chạm đơn chưa gán, không ghi đè đơn đã gán.
- [ ] Đơn cha không gán được; đơn `returned`/`cancelled` không gán được.
- [ ] Lọc theo shipper đổi danh sách trên trang lịch.

**Shipper vận hành**
- [ ] `/shipper/lich-giao` chỉ hiện đơn của mình, sắp theo giờ đã chốt.
- [ ] Bấm "Chỉ đường" mở Google Maps đúng địa chỉ khách (test trên điện thoại thật).
- [ ] "Đã giao" đổi `confirmed → renting`; "Đã thu" đổi `renting → returned`; mail cho khách vẫn gửi như khi admin bấm.
- [ ] Không bấm được "Đã giao" khi đơn đang `pending` (chưa xác nhận).

**Thông báo**
- [ ] Bấm "Gửi lịch qua email" → `ShipperScheduleMail` được queue tới đúng shipper, nội dung đúng ngày + đúng thứ tự.
- [ ] `php artisan shipper:send-daily-schedule` gửi cho từng shipper có đơn hôm nay, chạy 2 lần không gửi trùng.
- [ ] Shipper email `@bopcamping.local` → không gửi, không lỗi, admin thấy cảnh báo.

**Chung**
- [ ] `php artisan test` · `npm test` · `npx tsc --noEmit` · `./vendor/bin/pint --test` · `npm run build` pass.

## 6. Rủi ro

| Rủi ro | Mức | Xử lý |
|---|---|---|
| Rò dữ liệu khách qua khu vực shipper | **Cao** | Middleware riêng + mọi query kẹp `shipper_id`; AC riêng cho IDOR; review bảo mật trước khi merge |
| Mật khẩu shipper yếu / dùng chung | Trung bình | Min 6 ký tự (quy ước hiện có), throttle login, admin đổi được mật khẩu; ghi rõ rủi ro đã chấp nhận trong ADR |
| Shipper bấm nhầm "Đã thu" khi chưa lấy đồ | Trung bình | Hộp xác nhận + chỉ hiện nút đúng trạng thái; admin sửa lại được trạng thái |
| Email lịch không gửi vì thiếu cron/queue | Trung bình | Nút gửi tay luôn có; ghi rõ phụ thuộc `bopcamping-ybsm` |

## 7. Cập nhật vòng 3 (30/07/2026 — feedback chủ shop)

Ghi đè FR-3 và FR-4.

### 7.1 Thu tiền tách 2 khoản (mới)

`orders.payment_status` 3 mức không biểu diễn được "đã thu tiền thuê nhưng chưa thu cọc".
Thêm `rental_paid_at/by` + `deposit_paid_at/by` làm **nguồn chân lý**; `payment_status`
thành **giá trị suy ra** (chưa thu gì = unpaid · thu 1 trong 2 = deposit · đủ = full),
chỉ ghi qua `Order::markPaid()`. Accessor `rental_due` = thuê + phụ phí − giảm giá.

- Admin: 3 nút "tình trạng chuyển tiền" → **2 công tắc độc lập**, hiện ai thu + lúc nào.
- **Đổi hành vi:** đơn đã trả KHÔNG còn bị khoá đánh dấu thu tiền (tiền thuê có thể mới
  thu đúng lúc đi thu đồ); chỉ đơn đã huỷ bị chặn.

### 7.2 Màn shipper (thay FR-3)

- **Lịch tháng lớn** thay điều hướng từng ngày: ngày có lượt của CHÍNH MÌNH thì bôi đỏ kèm
  `N↓` giao · `M↑` thu; ngày ngoài khoảng `[hôm nay−2, hôm nay+14]` bị khoá.
- Card đơn **đóng mặc định**, bấm mở chi tiết: sản phẩm + số lượng, **tiền thuê** và
  **tiền cọc** kèm đã/chưa thu, tổng còn phải thu, ghi chú, Chỉ đường, bấm gọi.
- Khoản nào **chưa thu** thì shipper bấm thu ngay (hỏi lại 1 bước). **Không cần admin uỷ
  quyền riêng** cho từng đơn. Thu được ở cả 2 lượt; chỉ đánh dấu ĐÃ thu, **không** cho bỏ
  đánh dấu (sửa sai là việc admin); ghi ai thu, không ghi đè người thu trước.
- Lượt THU: nút **"Đã hoàn cọc"** + ô ghi chú trừ cọc → ghi vào `deposit_refund_status/note`
  sẵn có, không tạo nguồn chân lý thứ hai.

### 7.3 Zalo thay email (thay FR-4)

- Mỗi đơn trong `/admin/lich-giao` có nút **"Nội dung Zalo"**: hiện đoạn text sinh ở server
  (mã đơn, tên, SĐT, địa chỉ, sản phẩm + SL, ngày giờ giao/thu, dòng "nhờ shipper thu…"
  **chỉ khi khoản đó chưa thu**, câu tự kiểm đồ + trả cọc ở lượt thu, ghi chú, câu liên hệ
  admin) + nút **Copy** + nút **mở Zalo** của shipper đã gán.
- **BỎ HẲN email lịch**: xoá `ShipperScheduleMail`, blade, `ShipperScheduleNotifier`,
  command `shipper:send-daily-schedule`, lịch 06:00, route `gui-email`, nút gửi mail và 2
  file test tương ứng. Có test khẳng định các lớp/route đó không còn tồn tại.
- Vẫn **không** dùng Zalo OA/ZNS (không gửi tự động) — đúng quyết định 29/07.

### 7.4 Bổ sung cuối vòng 3 (30/07/2026)

- **Giờ mặc định khi chưa chốt** — 3 tầng ưu tiên:
  1. Giờ admin **đã chốt** (`confirmed_*`).
  2. Đơn thuê **≤ 1 ngày**: khung giờ của buổi khách chọn (sáng 08:00–12:00 · chiều
     13:00–20:00 · cả ngày 08:00–20:00). Dùng lại `requested_*` mà `OrderSplitter` suy sẵn lúc
     checkout — **không suy lại**, giữ một nguồn chân lý.
  3. Đơn **đã xác nhận** (`confirmed`/`renting`) mà không có gì ở trên (đơn nhiều ngày):
     mặc định toàn shop **giao 08:00 · thu 21:00**. 21:00 muộn hơn giờ đóng cửa trong Cài đặt
     shop là **có ý** — chừa dư cho lượt thu buổi tối.

  Đơn còn **chờ xác nhận** mà không suy được giờ → thật sự "chưa chốt giờ" (chưa xác nhận thì
  chưa hẹn giờ với khách). UI ghi rõ **"giờ mặc định"** để phân biệt với giờ admin đã chốt.
  Danh sách sắp theo **giờ thực dụng** (sắp ở PHP, vì luật phụ thuộc cả buổi lẫn trạng thái —
  viết lại trong SQL là nhân đôi luật); chip "chưa chốt giờ" chỉ đếm đơn **không có giờ nào**.
- **Link đơn trong tin nhắn Zalo**: thêm dòng `Xem đơn: …/shipper/lich-giao?date=&month=` mở
  đúng **ngày của lượt đó** (lượt giao → ngày giao, lượt thu → ngày thu).
- **Màn shipper hiện cả hai mốc**: chi tiết đơn có dòng "Giao dd/mm · HH:MM" và "Thu dd/mm ·
  HH:MM"; tin nhắn Zalo cũng in cả hai (lượt đang giao việc để trước).
- **Giờ KHÔNG in ở đầu card** (feedback 31/07): bỏ con giờ cỡ lớn trên card ở cả màn shipper
  và màn admin lịch giao. Giờ chỉ xuất hiện ở 2 nơi: dòng mốc Giao/Thu trong **chi tiết đơn**
  và trong **nội dung Zalo**. Nhãn "mặc định" chuyển xuống dòng mốc. Card admin vẫn giữ badge
  **"Chưa chốt giờ"** khi đơn không có giờ nào — đó là việc admin cần gọi khách, không phải giờ.

## 8. Liên quan

- [plan_shipper_delivery_ops.md](artifacts/plan_shipper_delivery_ops.md) — kế hoạch triển khai + phân rã task.
- [prd_delivery_schedule.md](artifacts/prd_delivery_schedule.md) — nền tảng (giờ đã chốt, lịch tháng).
- [system_design_admin_user_management.md](artifacts/system_design_admin_user_management.md) — trang users hiện tại.
- `bopcamping-vo4` (Basic Auth admin) và `bopcamping-ybsm` (cron) — hai bead đang mở có liên quan.
