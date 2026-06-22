# Design Spec — Trang "Tài khoản của tôi" + Chương trình Referral

**Issue:** bopcamping-bgg · **Branch:** `feat/account-referral` · **Ngày:** 2026-06-22

## 1. Mục tiêu

Màn hình `/tai-khoan` cho khách đã đăng nhập (SiteLayout, tông be/Naturehike) hiển thị:
1. Tổng số **sản phẩm đã thuê hoàn thành** (đơn `returned`).
2. Các **đơn đang thuê** (chưa hoàn thành: `pending` / `confirmed` / `renting`).
3. **Mã giới thiệu** của khách + nút copy.

Kèm **chương trình referral đầy đủ** (quyết định đã chốt với chủ shop):
- **Ai nhận:** cả hai — người giới thiệu (referrer) *và* người được giới thiệu (referee).
- **Thưởng:** voucher **giảm tiền thuê** đơn sau, số tiền cố định (`config('referral.reward_amount')`, mặc định **50.000đ**). Hợp COD, không cần cổng thanh toán.
- **Kích hoạt:** khi referee **trả đơn đầu tiên** (order chuyển sang `returned`). Chống gian lận tốt nhất. Idempotent — chỉ cấp một lần.

## 2. Mô hình dữ liệu

### `users` (thêm cột — migration `add_referral_fields_to_users_table`)
| Cột | Kiểu | Ghi chú |
|-----|------|---------|
| `referral_code` | string(12) unique nullable | Tự sinh khi tạo user (alphabet không gây nhầm lẫn). Backfill user cũ. |
| `referred_by` | FK→users.id nullable, nullOnDelete, index | Ai đã giới thiệu user này. Set 1 lần khi đăng ký bằng mã. |

### `vouchers` (mới — `create_vouchers_table`)
| Cột | Kiểu | Ghi chú |
|-----|------|---------|
| `user_id` | FK→users cascadeOnDelete | Chủ sở hữu — người được dùng voucher |
| `code` | string unique | VC-XXXXXX, hiển thị cho khách |
| `amount` | integer (VND) | Số tiền giảm |
| `source` | string | `referral_referrer` / `referral_referee` (mở rộng sau) |
| `referred_user_id` | FK→users nullable | Referee đã kích hoạt thưởng (audit) |
| `trigger_order_id` | FK→orders nullable | Đơn `returned` kích hoạt (audit) |
| `order_id` | FK→orders nullable | Đơn đã dùng voucher (set khi redeem) |
| `redeemed_at` | timestamp nullable | NULL = còn hiệu lực |
| `expires_at` | timestamp nullable | NULL = không hết hạn (MVP) |

**Idempotency:** unique `(referred_user_id, source)` → mỗi referee chỉ sinh tối đa 1 voucher referee + 1 voucher referrer.

### `orders` (thêm cột — `add_discount_total_to_orders_table`)
| Cột | Kiểu | Ghi chú |
|-----|------|---------|
| `discount_total` | integer default 0 | Giảm giá từ voucher. Giữ `total_price` = tổng item; tiền phải trả = `total_price + deposit_total − discount_total`. |

## 3. Luồng

### Sinh mã giới thiệu
`User::booted()` hook `creating` → nếu trống thì `App\Support\ReferralCode::generate()` (vòng while đảm bảo unique, theo pattern `App\Support\Slug`).

### Bắt `referred_by` khi đăng ký
`Shop\GuestAuthController::store` — chỉ khi **tạo user mới**: đọc `ref` (input), tra `users.referral_code`, nếu khớp (và không tự giới thiệu) → set `referred_by`. UI nhồi `ref` vào LoginModal là việc tiếp theo (follow-up).

### Cấp thưởng
`App\Observers\OrderObserver::updated` (gắn qua `#[ObservedBy]` trên Order) → khi `wasChanged('status')` và `status === 'returned'` → `ReferralService::rewardForReturnedOrder($order)`:
- Referee = `$order->user`; bỏ qua nếu null hoặc không có `referred_by`.
- Chỉ cấp khi đây là đơn `returned` **đầu tiên** của referee (count === 1) + guard tồn tại voucher.
- Transaction: tạo voucher cho referee, và cho referrer nếu còn tồn tại.

### Redeem voucher (checkout)
`Shop\OrderController::store` nhận `voucher_code` (optional). Validate thuộc về user + còn hiệu lực → `discount = min(amount, total_price)` → lưu `discount_total`, đánh dấu voucher `redeemed_at` + `order_id`. UI ô nhập voucher ở Cart là follow-up.

## 4. Mặt tiền
`AccountController@index` → `Inertia::render('Account')` với: `completedProductCount`, `activeOrders[]`, `referralCode`, `referralCount`, `vouchers[]`. Dùng `relatedOrders()` (bắt cả đơn vãng lai trùng SĐT). Trang `Account.tsx` dùng SiteLayout + tái dùng `lib/orderStatus.ts`.

## 5. Phạm vi & follow-up
- **Trong phiên này:** schema, model, service, observer, capture, redemption (server-side) + trang + test.
- **Follow-up (bead riêng):** ô nhập voucher ở Cart UI; nhồi `ref` vào LoginModal + lưu `?ref=` vào session từ landing; hiển thị `discount_total` ở admin/receipt.
