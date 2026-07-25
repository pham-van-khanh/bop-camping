# Security Audit — Đơn cha/con (parent/child orders)

- **Ngày:** 2026-07-21
- **Phạm vi:** epic `bopcamping-wtuv` (`feature/parent-child-orders` vs `feat/scaffold-laravel`)
- **Kết luận:** ✅ **KHÔNG có lỗ hổng Critical/High.** 1 Low (đã vá), 1 Info.
- **Phương pháp:** STRIDE + trace data-flow (Grep) trên điểm vào thay đổi.

## Bề mặt tấn công

| Điểm vào | Quyền | Input client |
|---|---|---|
| `POST /dat-hang` (checkout, tách cha/con) | Public (throttle 20/phút) | product_id, qty, dates, combos, voucher_codes |
| `PATCH /admin/orders/{o}` + `/location` `/dates` `/payment` `/refund` | **Admin** (middleware `admin`) | status / dates / location / payment / refund |
| `GET /tra-cuu`, `/tai-khoan` (đọc) | Public + phone match / user | code + phone |

## STRIDE

### S — Spoofing / Authentication
- Mọi mutation đơn (huỷ cả cụm, đổi lịch/cơ sở con, payment, refund) nằm trong `Route::middleware(['admin'])`. Khách KHÔNG mutate được. ✅
- Đọc phía khách: `relatedOrders` khớp `user_id` HOẶC `customer_phone` của chính khách; lookup yêu cầu code + phone khớp. ✅

### T — Tampering (TRỌNG TÂM)
- **Client KHÔNG điều khiển cấu trúc cha/con hay giá.** Checkout chỉ nhận product_id/qty/dates/combo/voucher_codes; `is_parent`, `parent_id`, `subtotal`, `total_price`, `discount_total`, mã con — **đều do OrderSplitter/server sinh**. Không field nào cho client set giá/quan hệ. ✅
- Tách đơn + giá net + phân bổ voucher tính server (RentalPricingService + giá sản phẩm DB; voucher trên tổng cha + cap 25%). CWE-602 KHÔNG tồn tại. ✅
- Huỷ/khôi phục con → `recomputeParentAfterChildChange` giữ bất biến `Σ giảm con === giảm cha`, cap tái áp trên tổng mới (test phủ). ✅
- Eloquent parameterized (kể cả search LIKE `%q%` bind tham số) — không SQLi. CSRF web middleware. ✅

### R — Repudiation
- Không audit-log riêng cho huỷ-cả-cụm/đổi lịch admin — **đồng nhất hệ hiện tại**, không phải regression. (Info)

### I — Information Disclosure
- Lookup mã CHA trả `installments` = các con; con mang **cùng customer_phone** (copy từ base lúc tách) → không lộ đơn khách khác. ✅
- Admin list search `?q=` LIKE trên code/tên/SĐT + mã con — chỉ admin, không lộ ra ngoài. ✅

### D — Denial of Service
- **[LOW — ĐÃ VÁ]** `items[]`/`combos[]` ở checkout trước đây KHÔNG giới hạn số phần tử → giỏ độc hại nhiều khoảng ngày ⇒ OrderSplitter tạo hàng loạt đơn CON + order_items trong 1 transaction. Route có `throttle:20,1` giảm tần suất nhưng 1 request vẫn tạo nhiều đơn. Đã thêm `items max:50`, `combos max:20` (CWE-770).
- Split/recompute không N+1 nghiêm trọng; admin list nạp children eager. Chấp nhận (list không phân trang — vốn có từ trước).

### E — Elevation of Privilege
- Toàn bộ mutation sau `admin` middleware; guard cha (chỉ Huỷ cả cụm) là UX/logic, không phải rào quyền. ✅

## Checklist
- [x] Authn/Authz — admin middleware; đọc khách theo phone/user
- [x] Input validation — không field giá/quan hệ từ client; thêm bound mảng
- [x] Secrets — không thêm
- [x] Dependencies — **không thêm dependency mới** → không CVE mới
- [x] Data encryption — không dữ liệu nhạy cảm mới
- [x] Audit logging — snapshot % + breakdown giữ dấu vết tiền

## Findings

| # | Severity | Mô tả | CWE | Trạng thái |
|---|----------|-------|-----|-----------|
| 1 | Low | `items[]`/`combos[]` checkout không giới hạn → tạo hàng loạt đơn con/1 request | CWE-770 | ✅ Đã vá (items max:50, combos max:20) |
| 2 | Info | Không audit-log thao tác đơn của admin (đồng nhất hệ cũ) | CWE-778 | Chấp nhận |

Không cần tạo GitHub issue (không Critical/High).
