# Design Spec — Tài khoản chỉ có SĐT: cookie 400 ngày, chặn đăng nhập khi không có email

- **Bead:** `bopcamping-kuhg` (nối tiếp `bopcamping-bqsv`)
- **Nhánh:** `feature/phone-only-account-recovery`
- **Ngày:** 26/08/2026
- **Quyết định bởi:** chủ shop

---

## 1. Câu hỏi làm lộ vấn đề

> *"Tôi là khách mới, đăng ký tài khoản chỉ có SĐT. Lần sau khi hết hạn (bị logout) thì đăng
> nhập lại bằng SĐT có vào được không?"*

Đọc lại code sau bản vá `bopcamping-bqsv`: **không vào được**. Tài khoản có email tạm
`…@bopcamping.local`, không đơn nào kèm email → rơi vào nhánh báo *"Vui lòng nhập email để
nhận mã xác thực"*. Cookie nhớ đăng nhập 60 ngày che được phần lớn, nhưng hết hạn / xoá
cookie / đổi máy là kẹt.

## 2. Và một lỗ hổng còn sót, phát hiện khi kiểm chỗ này

Email lúc checkout là **tuỳ chọn** (`OrderController` — `'email' => ['nullable', …]`).
Nên tồn tại một loại SĐT mà bản vá `bqsv` không nhìn thấy:

| Tình huống | `$allowedEmails` (luật cũ) | Hậu quả |
|---|---|---|
| Khách đăng ký bằng SĐT, đặt 5 đơn, không bao giờ điền email | **rỗng** | Số bị coi là "vô chủ" |

Luật cũ đặt `$phoneIsClaimed = $allowedEmails->isNotEmpty()`. Số vô chủ ⇒ người lạ biết SĐT
gõ kèm **email của chính họ** là tạo/chiếm được tài khoản. Rồi `User::relatedOrders()` khớp
đơn **theo `customer_phone`**, nên họ xem được toàn bộ lịch sử: tên, địa chỉ giao, số điện thoại.

Đây đúng là đường tấn công `bqsv` định bịt — chỉ khác ở chỗ nạn nhân không có email.

## 3. Ba luật mới (chủ shop chốt)

1. Tài khoản **chỉ có SĐT** → cookie nhớ đăng nhập **400 ngày** trên máy đó (thay vì 60).
2. SĐT **đã có chủ** mà **không hộp thư nào** nhận được mã → **không cho đăng nhập**; màn hình
   báo *"cần có email"* kèm **nút Zalo**.
3. Khách điền email lúc **checkout** → gắn luôn email đó vào tài khoản. Trong 60 ngày tiếp
   theo vẫn vào thẳng nhờ cookie, không phải nhập OTP.

### 3b. Chốt nghĩa cho luật 3 — chỗ dễ hiểu nhầm nhất

*"Không cần OTP trong 60 ngày"* có hai cách hiểu, khác nhau hoàn toàn:

| Cách hiểu | Hệ quả |
|---|---|
| ✅ **Cookie sống 60 ngày trên máy đó** (đã chốt) | Đúng hành vi hiện tại, không phải làm gì thêm |
| ❌ Trong 60 ngày, gõ SĐT là vào thẳng ở **bất kỳ máy nào** | Mở lại nguyên `bopcamping-bqsv` |

Chủ shop xác nhận cách hiểu thứ nhất.

## 4. Thay đổi cụ thể

### 4a. `allowedEmailsFor()` — một nguồn duy nhất

Trước đây `lookup()` nhìn `users.email`, còn `store()` tự dựng danh sách riêng. Hai chỗ lệch
nhau ở một ca có thật: **tài khoản email tạm nhưng có đơn cũ kèm email thật** — `store()` gửi
được mã, mà `lookup()` lại báo "không có email". Nay cả hai gọi chung
`GuestAuthController::allowedEmailsFor()`.

### 4b. Đổi tiêu chí "số đã có chủ"

```php
// Cũ: đếm theo email → hụt ca đơn-không-email
$phoneIsClaimed = $allowedEmails->isNotEmpty();

// Mới: đếm theo TÀI KHOẢN hoặc ĐƠN
$phoneIsClaimed = $existing !== null
    || Order::where('customer_phone', $data['phone'])->exists();
```

### 4c. Chốt chặn Zalo — đặt TRƯỚC khi rẽ nhánh email

Đặt trước là có chủ ý: chặn **cả** nhánh bỏ trống email lẫn nhánh gõ email lạ. Nếu chỉ chặn
nhánh bỏ trống thì gõ đại một email là lách được.

```php
if ($phoneIsClaimed && $allowedEmails->isEmpty()) {
    return back()
        ->withErrors(['phone' => 'Số này cần có email mới đăng nhập được. Nhắn Zalo để shop hỗ trợ.'])
        ->with('login_needs_support', true)
        ->withInput();
}
```

### 4d. Cookie 400 ngày

`setRememberDuration()` chỉ có ở `SessionGuard` (không nằm trong interface `Guard`) → kiểm
kiểu trước khi gọi, để đổi driver auth không làm nổ ứng dụng ngay tại chỗ đăng nhập.

400 ngày là **trần cứng của Chrome** cho tuổi cookie — đặt cao hơn cũng bị trình duyệt cắt về
đây. Cũng đúng bằng mặc định gốc của Laravel (576.000 phút).

### 4e. Checkout gắn email vào tài khoản

Chỉ gắn khi tài khoản đang là email tạm. **Không** set `email_verified_at` — email này chưa
qua OTP bao giờ. **Không** đè lên tài khoản đã có email thật (đặt hộ người thân bằng email của
họ sẽ tự đá mình ra khỏi tài khoản). Bỏ qua nếu email đã thuộc tài khoản khác (`users.email`
là UNIQUE — ghi vào là vỡ ràng buộc, trả 500).

### 4f. Giao diện

`lookup()` trả thêm `needs_support` → LoginModal hiện khối cảnh báo + nút Zalo **ngay khi
khách vừa gõ xong SĐT**, và **ẩn hẳn** nút "Tiếp tục" (để lại nút xám chỉ khiến khách thử đi
thử lại).

Vẫn cần flash `login_needs_support` bên cạnh: với khách **vãng lai** (có đơn cũ, chưa có tài
khoản), `lookup()` cố tình trả `exists: false` để không lộ "số này từng thuê đồ ở shop" — nên
chỉ tới lúc bấm gửi mã mới biết.

## 5. Cái giá phải trả — nói thẳng

| Ai | Ảnh hưởng |
|---|---|
| Khách phone-only mất cookie | Không tự vào lại được, phải nhắn Zalo |
| Khách vãng lai từng đặt đơn không email | Không tạo được tài khoản bằng SĐT đó |
| Chủ tài khoản thật, cookie còn hạn | Không ảnh hưởng; điền email lúc checkout là thoát hẳn diện này |

**Nút Zalo dời rủi ro sang người, không xoá nó.** Kẻ tấn công không phá được OTP thì sẽ nhắn
Zalo: *"em quên email, anh gắn giúp em email này"*. Người trực **phải** hỏi thông tin một đơn
cũ (mã đơn / ngày thuê / địa chỉ giao) trước khi gắn email — không có quy tắc này thì toàn bộ
phần code vừa siết bị vô hiệu ở khâu con người.

## 6. Việc phải làm TRƯỚC khi deploy production

Chủ shop đã chốt **force-logout toàn bộ phiên hiện có** (rotate `remember_token`) ở
`bopcamping-bqsv`. Ghép với luật mới thì **mọi khách phone-only mất quyền vào cùng một lúc** —
cookie 400 ngày không cứu được vì họ chưa hề có cookie mới. Tất cả dồn về Zalo trong vài ngày.

**Đếm trước trên production:**

```sql
SELECT COUNT(*) FROM users u
WHERE u.is_admin = 0
  AND u.email LIKE '%@bopcamping.local'
  AND NOT EXISTS (
    SELECT 1 FROM orders o
    WHERE o.customer_phone = u.phone AND o.customer_email IS NOT NULL
  );
```

- Con số **nhỏ** → deploy thẳng.
- Con số **lớn** → tách hai đợt: deploy luật mới trước cho khách kịp tích cookie 400 ngày,
  force-logout sau.

## 7. Test

### Đã viết lại (vì khẳng định đúng hành vi cũ)

| Test | Đổi gì |
|---|---|
| `remember_cookie_lasts_sixty_days` | Đổi tên + đi qua luồng OTP; 60 ngày giờ chỉ đúng cho tài khoản **có email** |
| `old_phone_account_can_add_email_via_otp` | Thay bằng `a_phone_only_account_can_no_longer_attach_an_email_at_login` — đường này bị bỏ hẳn |
| `returning_guest_without_a_real_email_is_asked_for_one…` | Thay bằng `a_returning_phone_only_account_is_sent_to_zalo…` |

### Mới

| Test | Khẳng định |
|---|---|
| `remember_cookie_lasts_four_hundred_days_for_a_phone_only_account` | Cookie 400 ngày |
| `a_phone_with_guest_orders_that_carry_no_email_cannot_be_claimed` | Lỗ hổng ở §2, cả hai nhánh (gõ email lạ + bỏ trống) |
| `lookup_follows_the_same_rule_as_login_for_a_placeholder_email_account` | `lookup()` và `store()` không lệch nhau |
| `lookup_flags_an_account_with_no_reachable_mailbox` | `needs_support` bật đúng lúc |
| `lookup_does_not_reveal_guest_orders_for_a_phone_without_an_account` | Không lộ khách vãng lai |
| `checkout_email_is_attached_to_a_phone_only_account` | §4e, và không set `email_verified_at` |
| `checkout_email_already_used_by_another_account_is_not_attached` | Không vỡ UNIQUE |
| `checkout_email_does_not_overwrite_an_existing_real_account_email` | Đặt hộ người thân không mất tài khoản |

### ⚠️ Trạng thái chạy test

**Chưa chạy được lần nào trong phiên làm việc này** — bộ lọc an toàn của môi trường chặn mọi
lệnh thực thi (`php artisan test`, `php -l`, `npm test`). CI chỉ chạy `npm ci` + `npm run build`
(`tsc && vite build`), tức **chỉ bắt lỗi TypeScript, không chạy test PHP hay vitest**.

Lệnh phải chạy trước khi merge production:

```bash
DB_CONNECTION=mysql DB_DATABASE=bop_camping_test php artisan test --filter=OtpFlowTest
DB_CONNECTION=mysql DB_DATABASE=bop_camping_test php artisan test --filter=OrderCheckoutTest
DB_CONNECTION=mysql DB_DATABASE=bop_camping_test php artisan test --filter=LoginLookupRenameTest
DB_CONNECTION=mysql DB_DATABASE=bop_camping_test php artisan test --filter=AdminUserTest
DB_CONNECTION=mysql DB_DATABASE=bop_camping_test php artisan test   # toàn bộ
npm test && npx tsc --noEmit && npm run lint && ./vendor/bin/pint --test && npm run build
```

## 8. Còn thiếu (đã ghi bead riêng)

- **Sửa email trong trang tài khoản.** Gõ sai email lúc checkout → tài khoản gắn vĩnh viễn vào
  hộp thư không đọc được → lần sau OTP gửi vào hư không, lại phải qua Zalo. Cần cho khách sửa
  email khi vẫn đang đăng nhập.
- **Test vitest cho LoginModal** ở trạng thái `needsSupport` (hiện chưa có test nào render
  LoginModal, và không chạy thử được trong phiên này).
