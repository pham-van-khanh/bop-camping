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
2. Admin **gán shipper** cho từng lượt (giao / thu) và **kéo-thả sắp thứ tự** đi trong ngày.
3. Shipper mở đơn có **nút chỉ đường** (mở Google Maps trên điện thoại) và **tự đánh dấu đã giao / đã thu**.
4. Admin **in / tải PDF / tải CSV** lịch giao của một ngày (lọc theo shipper).
5. Admin **gửi lịch cho shipper qua email** bằng 1 nút, và có **nút Chat Zalo** mở sẵn tin nhắn để gửi tay.

### Không làm (đã chốt với chủ shop 29/07)

- **Không** có link xem lịch không cần đăng nhập (rò dữ liệu khách — xem ADR mục 5).
- **Không** gọi API tối ưu lộ trình / vẽ bản đồ trong app: **không gửi địa chỉ, SĐT khách sang nhà cung cấp thứ ba nào**. Chỉ mở link Google Maps từ thiết bị của shipper, thứ tự do admin sắp tay.
- **Không** dùng Zalo OA / ZNS API (cần OA xác minh doanh nghiệp + phí + duyệt template). Chỉ email tự động + nút chat Zalo thủ công. Khi nào có OA thì mở việc riêng.
- **Không** chốt giờ ở cấp **đơn gộp** — cố tình, vì mỗi đợt giao một ngày khác nhau; chỉ chốt ở **đơn con**. (Xác nhận lại 29/07.)
- **Không** tự gợi ý giờ, **không** chặn giờ ngoài khung giờ shop (ô phụ phí ngoài giờ đã có sẵn).
- Không theo dõi GPS shipper, không chấm công, không tính lương/hoa hồng.

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
| `pickup_sort` | unsigned small int nullable | Thứ tự đi giao trong ngày (kéo-thả) |
| `return_sort` | unsigned small int nullable | Thứ tự đi thu trong ngày |

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
- **Kéo-thả sắp thứ tự** trong từng mục (dùng lại pattern `Reorder` của framer-motion trong [MediaGallery.tsx](resources/js/Components/admin/MediaGallery.tsx:216)), lưu vào `pickup_sort`/`return_sort` qua 1 request.
- Thứ tự hiển thị: đơn đã sắp tay trước (theo `*_sort`), rồi đến đơn chưa sắp theo giờ đã chốt, cuối cùng là đơn chưa chốt giờ.
- Bộ lọc **theo shipper** trên trang lịch (Tất cả / từng shipper / Chưa gán) — áp dụng cho cả in/PDF/CSV.
- Chỉ gán được đơn chưa `returned`/`cancelled`; đơn cha (`is_parent`) không gán (gán ở từng đợt con).

### FR-3 · Trang của shipper
- `/shipper/lich-giao?date=` — mobile-first, **chỉ đơn được gán cho chính mình** trong ngày đó, chia 2 mục Giao / Thu, theo thứ tự admin đã sắp.
- Mỗi đơn: thứ tự (1,2,3…), giờ đã chốt (hoặc "chưa chốt giờ"), tên khách, SĐT bấm gọi, địa chỉ, **nút "Chỉ đường"** (`https://www.google.com/maps/dir/?api=1&destination=<địa chỉ đã encode>`), món + số lượng, **số tiền cần thu (lượt giao) / cần hoàn (lượt thu)**, ghi chú shipper.
- Nút **"Đã giao"** (chỉ khi status `confirmed` và là lượt giao) / **"Đã thu"** (chỉ khi `renting` và là lượt thu) — hỏi xác nhận trước khi đổi, vì việc này gửi mail cho khách.
- Không có nav admin; chỉ có lịch của mình + đăng xuất. Điều hướng ngày: hôm nay ± vài ngày (không cho xem quá khứ xa, không xem đơn không phải của mình).
- Bảo mật: mọi truy vấn kẹp `where(shipper_id = auth id)`; đổi trạng thái phải kiểm lại quyền sở hữu lượt đó (chống IDOR — CWE-639).

### FR-4 · Thông báo
- Nút **"Gửi lịch qua email"** trên trang lịch (theo ngày + shipper đang lọc) → gửi `ShipperScheduleMail` (ShouldQueue) tới email shipper: danh sách theo thứ tự, giờ, địa chỉ, SĐT khách, tiền cần thu, ghi chú.
- **Tự động 06:00 hằng ngày**: gửi lịch hôm đó cho từng shipper có đơn (command `shipper:send-daily-schedule`, idempotent, lịch trong `routes/console.php`). Phụ thuộc cron trên server — bead `bopcamping-ybsm`.
- Shipper **không có email thật** (`@bopcamping.local`) → bỏ qua, hiện cảnh báo trong admin thay vì lỗi.
- Nút **"Chat Zalo"** cạnh mỗi shipper: mở `https://zalo.me/<sđt>` — chủ shop tự dán/gửi. Không API, không tự động.

### FR-5 · In / PDF / CSV
- `/admin/lich-giao/in?date=&shipper=` — trang HTML khổ A4, CSS `@media print`, không nav, bảng gọn: thứ tự · giờ · mã đơn · khách · SĐT · địa chỉ · món · tiền thu/hoàn · ghi chú. Có cả 2 mục Giao/Thu.
- `/admin/lich-giao/pdf?date=&shipper=` — tải file `lich-giao-YYYY-MM-DD.pdf` (dompdf, font DejaVu Sans, **không lưu xuống disk**).
- `/admin/lich-giao/csv?date=&shipper=` — tải `lich-giao-YYYY-MM-DD.csv`, UTF-8 **có BOM** để Excel tiếng Việt không lỗi font, `streamDownload`.
- Cả 3 dùng chung 1 service lấy dữ liệu (không lặp query, không lệch số liệu giữa các định dạng).
- Cả 3 nằm sau middleware `admin`.

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
- [ ] Kéo-thả đổi thứ tự → lưu, tải lại trang giữ đúng thứ tự.
- [ ] Đơn cha không gán được; đơn `returned`/`cancelled` không gán được.
- [ ] Lọc theo shipper đổi cả danh sách trên web lẫn nội dung in/PDF/CSV.

**Shipper vận hành**
- [ ] `/shipper/lich-giao` chỉ hiện đơn của mình, đúng thứ tự admin sắp.
- [ ] Bấm "Chỉ đường" mở Google Maps đúng địa chỉ khách (test trên điện thoại thật).
- [ ] "Đã giao" đổi `confirmed → renting`; "Đã thu" đổi `renting → returned`; mail cho khách vẫn gửi như khi admin bấm.
- [ ] Không bấm được "Đã giao" khi đơn đang `pending` (chưa xác nhận).

**Thông báo**
- [ ] Bấm "Gửi lịch qua email" → `ShipperScheduleMail` được queue tới đúng shipper, nội dung đúng ngày + đúng thứ tự.
- [ ] `php artisan shipper:send-daily-schedule` gửi cho từng shipper có đơn hôm nay, chạy 2 lần không gửi trùng.
- [ ] Shipper email `@bopcamping.local` → không gửi, không lỗi, admin thấy cảnh báo.

**In / xuất**
- [ ] Trang in vừa 1 khổ A4 với ~10 đơn, không mất cột, không nav.
- [ ] PDF tải về mở được, **chữ tiếng Việt có dấu đúng** (không ô vuông).
- [ ] CSV mở bằng Excel không lỗi font (BOM), số tiền không bị Excel đổi thành ngày.
- [ ] 3 định dạng cho cùng 1 ngày + 1 shipper ra **cùng số đơn**.

**Chung**
- [ ] `php artisan test` · `npm test` · `npx tsc --noEmit` · `./vendor/bin/pint --test` · `npm run build` pass.

## 6. Rủi ro

| Rủi ro | Mức | Xử lý |
|---|---|---|
| Rò dữ liệu khách qua khu vực shipper | **Cao** | Middleware riêng + mọi query kẹp `shipper_id`; AC riêng cho IDOR; review bảo mật trước khi merge |
| Mật khẩu shipper yếu / dùng chung | Trung bình | Min 6 ký tự (quy ước hiện có), throttle login, admin đổi được mật khẩu; ghi rõ rủi ro đã chấp nhận trong ADR |
| Shipper bấm nhầm "Đã thu" khi chưa lấy đồ | Trung bình | Hộp xác nhận + chỉ hiện nút đúng trạng thái; admin sửa lại được trạng thái |
| Font tiếng Việt trong PDF ra ô vuông | Trung bình | Nhúng DejaVu Sans + test khẳng định có chữ có dấu |
| Excel đọc CSV lỗi font/định dạng | Thấp | UTF-8 BOM; số tiền xuất dạng số thuần |
| Email lịch không gửi vì thiếu cron/queue | Trung bình | Nút gửi tay luôn có; ghi rõ phụ thuộc `bopcamping-ybsm` |
| Kéo-thả trên điện thoại khó dùng | Thấp | Kéo-thả là việc của **admin** (màn to); shipper chỉ đọc thứ tự |

## 7. Liên quan

- [plan_shipper_delivery_ops.md](artifacts/plan_shipper_delivery_ops.md) — kế hoạch triển khai + phân rã task.
- [prd_delivery_schedule.md](artifacts/prd_delivery_schedule.md) — nền tảng (giờ đã chốt, lịch tháng).
- [system_design_admin_user_management.md](artifacts/system_design_admin_user_management.md) — trang users hiện tại.
- `bopcamping-vo4` (Basic Auth admin) và `bopcamping-ybsm` (cron) — hai bead đang mở có liên quan.
