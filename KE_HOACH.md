# Kế hoạch dự án: BopCamping — Cho thuê đồ Camping

> Website cho thuê thiết bị cắm trại (1 shop), đặt thuê theo ngày, thanh toán COD.
> Giao diện hiện đại, đẹp mắt, phục vụ người dùng thật.

---

## 1. Công nghệ (Tech Stack)

| Lớp | Công nghệ | Ghi chú |
|-----|-----------|---------|
| Backend | **Laravel 12** | Framework PHP |
| Cầu nối FE-BE | **Inertia.js** | SPA mượt, không cần API riêng |
| Frontend | **React 18 + TypeScript** | Component hiện đại |
| CSS | **Tailwind CSS** | Dựng UI nhanh, đẹp |
| UI Components | **shadcn/ui** | Button, Card, Dialog, Calendar... có sẵn, đẹp |
| Database | **MySQL** (hoặc SQLite khi dev) | |
| Auth | **Laravel Breeze (Inertia + React)** | Đăng ký/đăng nhập sẵn |
| Build tool | **Vite** | Đi kèm Laravel |

---

## 2. Yêu cầu môi trường (LÀM TRƯỚC)

Máy hiện tại đang dùng phần mềm cũ, cần nâng cấp:

```bash
# Trên macOS, dùng Homebrew
brew install php@8.3 node@20 composer mysql

# Kiểm tra lại sau khi cài
php --version      # cần >= 8.2
node --version     # cần >= 20
composer --version
```

- [ ] PHP >= 8.2
- [ ] Node >= 20
- [ ] Composer 2.x ✅ (đã có)
- [ ] MySQL (hoặc dùng SQLite cho đơn giản lúc dev)

---

## 3. Vai trò người dùng

- **Khách (Guest/Customer)**: xem đồ, xem chi tiết, chọn ngày thuê, đặt đơn (COD).
- **Admin (Chủ shop = bạn)**: quản lý sản phẩm, danh mục, đơn thuê, xem lịch bận của từng món.

---

## 4. Mô hình dữ liệu (Database)

### `categories` — Danh mục (Lều, Bếp, Túi ngủ, Bàn ghế...)
- id, name, slug, description, image, timestamps

### `products` — Sản phẩm cho thuê
- id, category_id, name, slug, description
- price_per_day (giá/ngày)
- quantity (số lượng tồn — để biết còn bao nhiêu cái)
- deposit (tiền cọc, có thể null)
- thumbnail, status (active/hidden)
- timestamps

### `product_images` — Nhiều ảnh cho 1 sản phẩm
- id, product_id, path, sort_order

### `orders` — Đơn thuê
- id, user_id (hoặc thông tin khách vãng lai)
- customer_name, customer_phone, customer_address
- start_date, end_date (ngày nhận - ngày trả)
- total_price, deposit_total
- status (pending → confirmed → renting → returned → cancelled)
- payment_method (cod)
- note, timestamps

### `order_items` — Chi tiết đơn (mỗi món trong đơn)
- id, order_id, product_id
- quantity, price_per_day, days, subtotal

> **Kiểm tra trùng lịch**: khi khách chọn ngày, hệ thống tính số lượng đã được đặt
> trong khoảng `[start_date, end_date]` và so với `products.quantity` để biết còn đủ hàng không.

---

## 5. Các trang / Tính năng

### Phía khách (Public)
- [ ] **Trang chủ**: banner đẹp, danh mục nổi bật, sản phẩm hot, CTA, **carousel quote review (system)**
- [ ] **Danh sách sản phẩm**: lọc theo danh mục, tìm kiếm, sắp xếp theo giá
- [ ] **Chi tiết sản phẩm**: ảnh, mô tả, giá/ngày, **chọn ngày thuê (calendar)**, kiểm tra còn hàng, **carousel review sản phẩm (product)**
- [ ] **Giỏ thuê (cart)**: gom nhiều món cùng khoảng ngày
- [ ] **Đặt đơn (checkout)**: nhập tên/SĐT/email/địa chỉ, xác nhận COD → gửi mail xác nhận
- [ ] **Tra cứu đơn** (theo mã đơn + SĐT) — tự động điền nếu đang login hoặc vừa đặt xong
- [ ] **Đăng nhập**: SĐT + tên + email → xác thực OTP qua email
- [ ] **Đánh giá sau chuyến đi**: form điền qua link email (token), upload ảnh, rating 1–5 sao

### Phía Admin
- [ ] Dashboard: số đơn, doanh thu, đơn mới, **badge review pending**
- [ ] Quản lý danh mục (CRUD)
- [ ] Quản lý sản phẩm (CRUD + upload nhiều ảnh)
- [ ] Quản lý đơn thuê: xem, đổi trạng thái, xem lịch bận
- [ ] **Quản lý đánh giá**: duyệt / từ chối review, lọc theo status/category/sản phẩm
- [ ] (Tùy chọn) Lịch tổng quan đồ nào đang được thuê ngày nào

---

## 6. Lộ trình thực hiện (theo giai đoạn)

### Giai đoạn 0 — Chuẩn bị
1. Nâng cấp PHP 8.2+, Node 20+
2. Tạo project Laravel + cấu hình DB

### Giai đoạn 1 — Nền tảng
3. Cài Breeze (Inertia + React + Tailwind)
4. Cài shadcn/ui, thiết lập theme/màu sắc
5. Layout chung: header, footer, navbar đẹp

### Giai đoạn 2 — Quản lý dữ liệu (Admin)
6. Migration + Model (category, product, order...)
7. CRUD danh mục
8. CRUD sản phẩm + upload ảnh

### Giai đoạn 3 — Trải nghiệm khách
9. Trang chủ + danh sách + chi tiết sản phẩm
10. Chọn ngày thuê + kiểm tra trùng lịch
11. Giỏ thuê + checkout (COD)

### Giai đoạn 4 — Hoàn thiện
12. Quản lý đơn ở admin
13. Tra cứu đơn cho khách
14. Trau chuốt giao diện, responsive, ảnh đẹp

---

## 7. Quyết định đã chốt

- [x] **Đăng nhập**: bằng **SĐT + tên + email** — xác thực qua **OTP gửi email** (không cần OTP SĐT).
- [x] **Tiền cọc**: CÓ thu cọc cho mỗi món (`products.deposit`).
- [x] **Thanh toán**: COD (trả khi nhận).
- [x] **Màu thương hiệu**: tông **be / màu đất** giống **Naturehike** — trung tính, tối giản, ấm.
- [x] **Email thông báo**: gửi mail xác nhận đơn thuê thành công cho khách.
- [x] **Đánh giá sản phẩm**: sau mỗi chuyến đi, khách nhận lời mời đánh giá; admin duyệt trước khi hiển thị.

### Bảng màu dự kiến (giống Naturehike)
- Nền chính: be sáng `#F5F1E8` / `#EDE6D6`
- Màu chữ/đậm: nâu đất `#3A3226` / `#5C4F3A`
- Điểm nhấn (accent): nâu vàng `#A38B5F` / xanh rêu nhạt `#8A9A7B`
- Trắng kem cho card: `#FBF9F4`

### Còn cần chốt sau (không gấp)
- [ ] Giới hạn số ngày thuê tối thiểu/tối đa?

---

## 8. Tính năng bổ sung (đã chốt)

### 8.1 Email khách hàng & xác thực OTP qua email

**Thay đổi mô hình đăng nhập:**
- Trường đăng nhập: **SĐT + tên + email** (thêm `email` vào bảng `users`)
- Xác thực danh tính: gửi **OTP 6 số qua email** (không xác thực SĐT)
- OTP hết hạn sau 10 phút, dùng 1 lần

**Email thông báo tự động:**
- Đặt đơn thành công → gửi mail kèm mã đơn, tóm tắt sản phẩm, ngày thuê, tổng tiền, tiền cọc
- Đơn được xác nhận (admin confirm) → gửi mail thông báo
- Đơn hoàn trả / huỷ → gửi mail tương ứng
- Sau khi trả đồ (`returned`) → gửi mail mời đánh giá sản phẩm

**Schema bổ sung (`users`):**
```
email (string, unique)
email_verified_at (timestamp, nullable)
```

**Bảng `email_otp` (hoặc dùng cache/session):**
```
id, email, otp (hashed), expires_at, used_at
```

### 8.2 Hệ thống đánh giá sản phẩm (Reviews)

**Luồng hoạt động:**
1. Sau khi đơn chuyển sang `returned`, hệ thống gửi email mời đánh giá (kèm link có token)
2. Khách click link → điền đánh giá (rating 1–5 sao, nội dung, upload ảnh)
3. Admin duyệt review trong panel → `pending` → `approved` / `rejected`
4. Review được duyệt mới hiển thị công khai

**Schema `reviews`:**
```
id, order_item_id (liên kết đến sản phẩm cụ thể trong đơn)
product_id, user_id
rating (1–5)
content (text)
category (enum: 'system' | 'product') — 'system': trải nghiệm tổng thể shop; 'product': nhận xét riêng sản phẩm
status (enum: 'pending' | 'approved' | 'rejected')
admin_note (nullable — lý do từ chối)
review_token (string, unique — dùng trong link email mời đánh giá)
review_token_used_at (timestamp, nullable)
timestamps
```

**Schema `review_images`:**
```
id, review_id, path, sort_order
```

**Hiển thị:**
- **Trang sản phẩm**: hiển thị review loại `product` của sản phẩm đó — dạng carousel auto-next (5 giây/slide) để tránh tràn nội dung
- **Trang chủ (Homepage)**: hiển thị review loại `system` — dạng quote carousel auto-next, kèm tên khách + avatar chữ cái đầu
- Cả hai carousel đều responsive, có thể swipe trên mobile

**Admin panel (bổ sung vào trang Quản lý):**
- Tab "Đánh giá" — lọc theo `status`, `category`, `product`
- Xem nội dung + ảnh, nút Duyệt / Từ chối (kèm ghi chú)
- Hiển thị số review pending trên badge menu

### 8.3 UX thông minh — Tự động điền thông tin

**Trang tra cứu đơn (`/tra-cuu`):**
- Nếu khách **đang đăng nhập** → tự động điền SĐT vào ô tìm kiếm
- Nếu vừa **đặt đơn xong** → redirect sang `/tra-cuu` với query params `?order_code=ORD-xxx&phone=09xxx` → tự động điền cả mã đơn lẫn SĐT, trigger tìm kiếm luôn
