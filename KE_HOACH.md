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
- [ ] **Trang chủ**: banner đẹp, danh mục nổi bật, sản phẩm hot, CTA
- [ ] **Danh sách sản phẩm**: lọc theo danh mục, tìm kiếm, sắp xếp theo giá
- [ ] **Chi tiết sản phẩm**: ảnh, mô tả, giá/ngày, **chọn ngày thuê (calendar)**, kiểm tra còn hàng
- [ ] **Giỏ thuê (cart)**: gom nhiều món cùng khoảng ngày
- [ ] **Đặt đơn (checkout)**: nhập tên/SĐT/địa chỉ, xác nhận COD
- [ ] **Tra cứu đơn** (theo mã đơn + SĐT)
- [ ] Đăng ký / Đăng nhập (tùy chọn — có thể cho đặt không cần tài khoản)

### Phía Admin
- [ ] Dashboard: số đơn, doanh thu, đơn mới
- [ ] Quản lý danh mục (CRUD)
- [ ] Quản lý sản phẩm (CRUD + upload nhiều ảnh)
- [ ] Quản lý đơn thuê: xem, đổi trạng thái, xem lịch bận
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

- [x] **Đăng nhập**: bằng **SĐT + tên** (không dùng email/mật khẩu phức tạp). OTP có thể thêm sau.
- [x] **Tiền cọc**: CÓ thu cọc cho mỗi món (`products.deposit`).
- [x] **Thanh toán**: COD (trả khi nhận).
- [x] **Màu thương hiệu**: tông **be / màu đất** giống **Naturehike** — trung tính, tối giản, ấm.

### Bảng màu dự kiến (giống Naturehike)
- Nền chính: be sáng `#F5F1E8` / `#EDE6D6`
- Màu chữ/đậm: nâu đất `#3A3226` / `#5C4F3A`
- Điểm nhấn (accent): nâu vàng `#A38B5F` / xanh rêu nhạt `#8A9A7B`
- Trắng kem cho card: `#FBF9F4`

### Còn cần chốt sau (không gấp)
- [ ] Giới hạn số ngày thuê tối thiểu/tối đa?
- [ ] Có cần OTP xác thực SĐT không, hay chỉ nhập tên + SĐT là vào?
