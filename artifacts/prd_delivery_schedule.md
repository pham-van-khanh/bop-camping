# PRD — Chốt giờ giao/thu cho đơn + Lịch giao theo ngày (shipper)

**Loại:** Medium feature (~5–6 ngày) · **Reversibility:** Two-Way Door (thêm cột nullable + 1 trang admin mới; rollback = drop cột, xoá route/page)
**Ngày:** 2026-07-28 · **Nguồn:** yêu cầu chủ shop (admin phải sửa được giờ của đơn để shipper biết giao lúc nào)

## 1. Vấn đề

Hôm nay hệ thống **không có giờ nào do shop chốt**. Cụ thể:

- `orders.requested_pickup_time` / `requested_return_time` chỉ là **giờ KHÁCH mong muốn**, server tự suy từ buổi khách chọn khi thuê nửa ngày/1 ngày ([HalfDayCheckoutTest.php:61](tests/Feature/HalfDayCheckoutTest.php:61)); đơn nhiều ngày thì **null**.
- Admin **chỉ xem được**, không sửa được: [orderShared.tsx:425](resources/js/Pages/Admin/orderShared.tsx:425) render `DetailRow` read-only.
- Email nhắc nhận đồ nói thẳng là hệ thống không lưu giờ: *"Tụi mình sẽ liên hệ trước khi giao để hẹn giờ"* ([order_pickup_reminder.blade.php:19](resources/views/emails/order_pickup_reminder.blade.php:19)).
- Không có role shipper, không có trang lịch giao, không có bản in/export.

Hệ quả: giờ giao chỉ tồn tại trong cuộc gọi điện/Zalo giữa admin và khách. Shipper phải hỏi lại admin từng đơn; khách không có bằng chứng giờ hẹn bằng văn bản; admin dễ xếp trùng giờ hai đơn ở hai đầu thành phố.

## 2. Mục tiêu

1. Admin **chốt/sửa được giờ giao và giờ thu** của từng đơn, kèm **ghi chú nội bộ cho shipper**.
2. Shipper mở **một trang duy nhất** thấy: hôm nay (hoặc ngày đã chọn) cần **giao** đơn nào lúc mấy giờ, cần **thu** đơn nào lúc mấy giờ — sắp xếp theo giờ, đủ tên/SĐT/địa chỉ/tiền COD.
3. Khách **nhận email xác nhận giờ** khi admin chốt hoặc đổi giờ; email nhắc nhận đồ in **giờ thật** thay vì câu "sẽ liên hệ để hẹn giờ".

### Không làm (out of scope)

- Không tạo role/tài khoản shipper riêng, không có link công khai không-cần-login (shipper dùng tài khoản admin hoặc màn hình của chủ shop). Nếu sau này cần → phải có ADR riêng vì rò dữ liệu khách.
- Không tối ưu lộ trình, không bản đồ, không gán shipper cho đơn, không SMS/Zalo tự động.
- Không in/PDF/CSV.
- Không tự động đề xuất giờ; không đổi logic tồn kho (giờ **không** ảnh hưởng `AvailabilityService`).
- Không chốt giờ ở cấp **đơn cha** — cha chỉ gom đợt, chốt giờ trên từng đơn con (đúng pattern `updatePayment`/`updateExtraFee`).

## 3. Quyết định thiết kế (đã chốt với chủ shop)

| # | Quyết định | Lý do |
|---|---|---|
| 1 | **Thêm cột riêng** `confirmed_pickup_time` / `confirmed_return_time`, KHÔNG ghi đè `requested_*` | Giữ được "khách xin 6:00 → shop chốt 7:30" để đối chiếu và biện minh `extra_fee` ngoài khung giờ. Ghi đè sẽ xoá dấu yêu cầu gốc → mất căn cứ phụ phí. |
| 2 | Chốt **cả giờ giao và giờ thu** | Shipper đi 2 lượt; giờ thu cũng cần cho đơn `renting`. |
| 3 | **Trang "Lịch giao theo ngày"** riêng (`/admin/lich-giao`), mobile-first | Shipper cần danh sách theo ngày sắp theo giờ, không phải bảng đơn lọc-tìm. Bảng `/admin/orders` sắp theo ngày tạo đơn, không dùng được để đi giao. |
| 4 | **Có gửi email** xác nhận giờ khi giờ thay đổi | Đúng pattern `changeDates` hiện tại (mail `order_dates_changed`); khách có văn bản giờ hẹn. |
| 5 | `schedule_note` là **ghi chú nội bộ**, KHÔNG vào email khách | Ghi chú kiểu "gọi trước 15 phút, nhà cuối hẻm, khách khó tính" chỉ dành cho shipper. |

## 4. Mô hình dữ liệu

Thêm vào bảng `orders` (nullable ⇒ đơn cũ không cần backfill):

| Cột | Kiểu | Nghĩa |
|---|---|---|
| `confirmed_pickup_time` | `string(5)` nullable | Giờ **giao** đã chốt, `HH:MM`. Null = chưa chốt. |
| `confirmed_return_time` | `string(5)` nullable | Giờ **thu** đã chốt, `HH:MM`. Null = chưa chốt. |
| `schedule_note` | `string(255)` nullable | Ghi chú nội bộ cho shipper. Không gửi khách. |
| `schedule_confirmed_at` | `timestamp` nullable | Lần chốt/đổi giờ gần nhất (audit + hiển thị "chốt lúc …"). |

Định dạng `HH:MM` giống `requested_*` (string, không phải `time`) — nhất quán, so sánh chuỗi vẫn đúng thứ tự, sort được ở cả SQLite và MySQL.

## 5. Yêu cầu chức năng

### FR-1 · Admin chốt giờ trên đơn
- Trong khối chi tiết đơn (dùng chung `/admin/orders` và `/admin/orders/{id}`) có ô **"Giờ giao/thu đã chốt"**: 2 input `type=time` + 1 input ghi chú + nút Lưu.
- Hiển thị song song **giờ khách xin** (nếu có) để admin đối chiếu.
- Xoá trắng ô → set null (huỷ chốt giờ).
- `PATCH /admin/orders/{order}/schedule`, throttle `30,1`, middleware `admin`.

### FR-2 · Ràng buộc nghiệp vụ
- Chặn nếu `is_parent = true` → *"Đơn gộp: chốt giờ trên từng đợt (đơn con)."*
- Chặn nếu `status` ∈ {`returned`, `cancelled`} → *"Đơn đã trả/đã huỷ — không chốt giờ nữa."*
- `date_format:H:i` cho cả hai giờ.
- Đơn **cùng ngày** (`start_date == end_date`) và có cả hai giờ → giờ thu phải **sau** giờ giao.
- Không kiểm tra giờ theo khung giờ shop (`pickup_hour`/`return_hour`): admin được quyền chốt ngoài giờ, phụ phí đã có ô `extra_fee` riêng.

### FR-3 · Email xác nhận giờ
- Gửi `OrderScheduleConfirmedMail` (ShouldQueue) **chỉ khi** `confirmed_pickup_time` hoặc `confirmed_return_time` **thay đổi thật**, đơn có email hợp lệ (`notifiableEmail()`), status ≠ `cancelled`.
- Sửa **chỉ** `schedule_note` → **không** gửi mail.
- Nội dung: mã đơn, ngày + giờ giao, ngày + giờ thu, địa chỉ, số tiền trả khi nhận. Không chứa `schedule_note`.

### FR-4 · Email nhắc nhận đồ dùng giờ thật
- `order_pickup_reminder.blade.php`: nếu có `confirmed_pickup_time` → in **"Giao lúc HH:MM"** và bỏ câu "Tụi mình sẽ liên hệ trước khi giao để hẹn giờ"; chưa chốt → giữ nguyên như hiện tại.

### FR-5 · Trang Lịch giao theo ngày
- `GET /admin/lich-giao?date=YYYY-MM-DD` (mặc định hôm nay), nav mới trong `AdminLayout` ngay dưới "Đơn thuê".
- **Cần giao hôm đó**: `is_parent = false`, `start_date = date`, `status` ∈ {`pending`, `confirmed`}. Đơn `pending` hiện kèm nhãn cảnh báo "chờ xác nhận".
- **Cần thu hôm đó**: `is_parent = false`, `end_date = date`, `status` ∈ {`confirmed`, `renting`}.
- Sắp xếp theo giờ đã chốt tăng dần, **đơn chưa chốt giờ xuống cuối**, rồi theo mã đơn.
- Mỗi dòng: giờ (hoặc "Chưa chốt giờ" nổi bật), mã đơn (link chi tiết), tên khách, SĐT (bấm gọi `tel:`), địa chỉ, cửa hàng, buổi (nếu có), danh sách món + số lượng, tiền trả khi nhận + tình trạng chuyển tiền, `schedule_note`.
- Đầu trang: điều hướng ngày (‹ hôm trước · Hôm nay · hôm sau › + `input[type=date]`) và đếm: *N giao · M thu · K chưa chốt giờ*.
- Mobile-first: card dọc, chữ ≥ 14px, không bảng cuộn ngang (shipper dùng điện thoại).

### FR-6 · Khách thấy giờ đã chốt
- `/tai-khoan` ([AccountController.php:136](app/Http/Controllers/AccountController.php:136) → [Account.tsx:453](resources/js/Pages/Account.tsx:453)) và `/tra-cuu` (`OrderLookupService::shape`) hiển thị **giờ đã chốt** khi có; chưa chốt thì hiện giờ mong muốn như hiện tại.

## 6. Acceptance criteria

- [ ] Admin đặt giờ giao 14:30 + giờ thu 09:00 cho 1 đơn → lưu DB, hiện lại đúng ở cả `/admin/orders` và `/admin/orders/{id}`, `schedule_confirmed_at` được set.
- [ ] Giờ khách xin vẫn còn nguyên trong DB sau khi admin chốt giờ khác.
- [ ] Giờ sai định dạng (`25:00`, `abc`) → lỗi validation, không lưu.
- [ ] Đơn cùng ngày, giờ thu ≤ giờ giao → lỗi validation.
- [ ] Đơn cha → chặn kèm thông báo chỉ về đơn con; đơn `returned`/`cancelled` → chặn.
- [ ] Đổi giờ → `OrderScheduleConfirmedMail` được queue tới email khách; sửa mỗi ghi chú → **không** có mail nào queue.
- [ ] Đơn không email (`@bopcamping.local`) → không mail, không lỗi.
- [ ] Email nhắc nhận đồ của đơn đã chốt giờ chứa `14:30` và **không** chứa "sẽ liên hệ trước khi giao để hẹn giờ".
- [ ] `/admin/lich-giao` mặc định ngày hôm nay; đúng 2 nhóm giao/thu; đơn cha, đơn `cancelled`, đơn `returned` không xuất hiện.
- [ ] Danh sách sắp theo giờ tăng dần, đơn chưa chốt giờ nằm cuối; đếm "chưa chốt giờ" đúng.
- [ ] `?date=` ngày khác → đổi danh sách; ngày không hợp lệ → fallback hôm nay (không 500).
- [ ] Ở 375px: card lịch giao không cuộn ngang; số điện thoại bấm được ra app gọi.
- [ ] Khách xem `/tai-khoan` và `/tra-cuu` thấy giờ đã chốt.
- [ ] Non-admin GET `/admin/lich-giao` → redirect `admin.login`.
- [ ] Quality gates pass: `php artisan test` · `npm test` · `npx tsc --noEmit` · `npm run lint` · `./vendor/bin/pint --test` · `npm run build`.

## 7. Rủi ro

| Rủi ro | Mức | Xử lý |
|---|---|---|
| Admin quên chốt giờ → shipper vẫn mù | Trung bình | Trang lịch giao đếm + tô đậm "Chưa chốt giờ"; xếp cuối danh sách để dễ thấy |
| Đổi giờ liên tục làm khách nhận nhiều mail | Thấp | Chỉ gửi khi giờ **đổi thật**; ghi chú nội bộ không kích hoạt mail |
| Nhầm giờ khách-xin với giờ shop-chốt trên UI | Trung bình | Hai nhãn tách bạch: "Khách xin" vs "Đã chốt"; khách chỉ thấy giờ đã chốt khi có |
| `schedule_note` lộ ra email khách | Thấp | View mail không nhận biến này; có AC kiểm tra |
| Đơn `pending` lọt vào lịch giao gây giao sai | Thấp | Có nhãn "chờ xác nhận" ngay trên card; không ẩn để admin thấy còn đơn cần xác nhận |
| Sort giờ khác nhau giữa SQLite và MySQL | Thấp | `ORDER BY col IS NULL, col, code` — cú pháp chạy đúng cả hai (rule collation-safe trong CLAUDE.md) |

## 8. Cập nhật vòng 2 (28/07/2026 — feedback chủ shop sau khi xem bản đầu)

Ghi đè FR-1 (phần hiển thị) và FR-5. Phần dữ liệu, validate, mail giữ nguyên.

### 8.1 Nhãn thời gian trong admin (thay FR-1 phần hiển thị)

| Trước | Sau | Điều kiện hiện |
|---|---|---|
| "Buổi" (`sessionLabel`, kể cả *Cả ngày*) + "Giờ khách xin" | **"Thời gian"** | Chỉ khi là **nửa ngày** (ca sáng/ca chiều) → hiện nhãn ca kèm khung giờ. Đơn **cả ngày** hoặc **nhiều ngày** → **không hiện dòng nào**. Đơn cũ không có buổi nhưng có giờ khách xin → hiện giờ đó. |
| "Giờ đã chốt" (luôn hiện, null → "chưa chốt") | **"Thời gian thay đổi"** (highlight đỏ đất `#b3493a`, in đậm) | **Chỉ khi admin đã chốt giờ**. Chưa chốt → không có dòng này. |

Danh sách `/admin/orders` theo cùng quy tắc: bỏ pill "Chưa chốt giờ" (nhãn buổi đã đủ), giờ đã chốt hiện thành dòng highlight đỏ.

Logic dùng chung: `defaultTimeLabel()` trong `resources/js/Pages/Admin/orderShared.tsx`.

### 8.2 Lịch giao = lịch THÁNG (thay FR-5)

- Đầu trang là **lịch tháng** (tuần bắt đầu **Thứ 2**), điều hướng ‹ / › theo **tháng** + link "Về hôm nay".
- Ngày **có đơn** → **bôi đỏ** (`#f6ddd6` / chữ `#b3493a`) kèm số đơn `N↓` (giao) · `M↑` (thu).
- Ngày **đã qua** → **khoá** (`disabled`, mờ 45%), vẫn thấy được hôm đó từng có đơn; hôm nay gạch chân.
- Bấm 1 ngày → phần dưới liệt kê **Cần giao** / **Cần thu** của ngày đó (giữ nguyên card như bản đầu).
- Params: `date` (ngày đang chọn) và `month` (tháng đang xem) độc lập — đổi tháng không mất ngày đang chọn. Cả hai sai định dạng đều fallback, không 500.
- Props mới: `month`, `month_label`, `prev_month`, `next_month`, `days[] = {date, pickups, returns}` (chỉ ngày có đơn).
- Lưới ngày tách thành hàm thuần `buildMonthGrid()` (`resources/js/lib/monthGrid.ts`) để test được bằng vitest.

**Lưu ý nghiệp vụ:** ngày *thu* của đơn còn `pending` chưa được tính vào lịch (đơn chưa xác nhận thì chưa chắc có lượt thu) — có test khoá hành vi này.

### 8.3 Chưa áp dụng

Nhãn phía khách (`/tai-khoan`, `/tra-cuu`) vẫn là "Giờ đã chốt" / "Giờ (mong muốn)" — đổi tên "Thời gian / Thời gian thay đổi" chỉ áp dụng cho admin. Cần thì mở việc riêng.

## 9. Liên quan

- [design_spec_admin_order_reschedule.md](artifacts/design_spec_admin_order_reschedule.md) — pattern admin đổi lịch + mail thông báo, tái dùng nguyên cấu trúc.
- [adr_pricing_models.md](artifacts/adr_pricing_models.md) — nửa ngày/buổi, nguồn của `requested_*`.
- [adr_parent_child_orders.md](artifacts/adr_parent_child_orders.md) — vì sao chốt giờ ở đơn con.
- [plan_delivery_schedule.md](artifacts/plan_delivery_schedule.md) — kế hoạch triển khai chi tiết.
