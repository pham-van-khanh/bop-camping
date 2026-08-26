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
2. SĐT **đã có chủ** mà **không hộp thư nào** nhận được mã → **bắt buộc nhập email**, mã gửi
   tới chính email vừa nhập; bên dưới ô email có dòng **liên hệ Zalo** làm lối phụ.
3. Khách điền email lúc **checkout** → gắn luôn email đó vào tài khoản. Trong 60 ngày tiếp
   theo vẫn vào thẳng nhờ cookie, không phải nhập OTP.

### 3a. Luật 2 đã được nới (chủ shop chốt lại 26/08/2026, sau khi bản chặn lên staging)

Bản đầu **chặn hẳn** nhánh này và bắt nhắn Zalo. Chủ shop thấy quá nặng cho khách thật:

> *"nhắn zalo chỉ là option thôi. vẫn giao diện cũ nhưng bắt khách phải nhập email, phía dưới
> là liên hệ zalo để được hỗ trợ."*

**Hệ quả bảo mật — nói thẳng, đây là đánh đổi chứ không phải sơ suất.** Mã gửi tới hộp thư
khách *vừa gõ* chỉ chứng minh "người này mở được hộp thư họ vừa nhập", **không** chứng minh họ
là chủ số. Vậy nên với **tài khoản chưa gắn email**, người lạ biết SĐT vẫn:

- chiếm được tài khoản (gắn email của họ vào),
- và xem được **toàn bộ lịch sử đơn** — tên, địa chỉ giao, số điện thoại — vì
  `User::relatedOrders()` khớp đơn theo `customer_phone`.

Không có cách nào phân biệt chủ số thật với người lạ ở bước này, vì **shop không gửi OTP SMS**
nên số điện thoại chưa bao giờ được xác thực.

Diện bị ảnh hưởng **chỉ là** tài khoản/SĐT chưa có email nào. Khách đã có email vẫn được bảo vệ
đầy đủ bởi chốt `allowedEmails` (xem §4c).

Nếu sau này muốn siết lại mà vẫn giữ trải nghiệm: **hỏi thêm mã một đơn cũ** khi SĐT đó có
lịch sử đơn (số không có đơn thì chẳng có gì để mất, cứ cho vào). Đã ghi bead riêng.

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

### 4c. Chốt chính còn lại — điều kiện là `isNotEmpty()`, KHÔNG phải `$phoneIsClaimed`

Đây là dòng dễ sửa nhầm nhất trong cả thay đổi. Sau khi nới luật 2, hai khái niệm tách đôi:

| Biến | Nghĩa | Dùng ở đâu |
|---|---|---|
| `$phoneIsClaimed` | số đã có tài khoản **hoặc** đã có đơn | quyết định "có được vào thẳng không" |
| `$allowedEmails` | những hộp thư **đã gắn** với số đó | quyết định "mã được gửi đi đâu" |
| `$mailboxUnknown` | đã có chủ **nhưng** chưa hộp thư nào | quyết định "có bắt nhập email không" |

```php
// Số ĐÃ CÓ hộp thư → email lạ bị từ chối. Chốt này giữ nguyên, là chốt chính.
if ($allowedEmails->isNotEmpty() && ! $allowedEmails->contains(Str::lower($email))) { … }
```

- Đổi nhầm sang `$phoneIsClaimed` → chặn luôn ca `$mailboxUnknown`, **trái quyết định §3a**.
- Bỏ hẳn điều kiện → **mở lại chiếm tài khoản của khách đã có email**, tức lỗ hổng `bqsv` gốc.

Test `a_stranger_cannot_claim_a_phone_that_has_guest_orders_using_another_email` khoá dòng này.

### 4c-2. Bỏ trống email khi số chưa gắn hộp thư → bắt nhập

```php
if ($mailboxUnknown) {
    return back()->withErrors(['email' => 'Vui lòng nhập email để nhận mã xác thực.'])
        ->with('login_needs_support', true)->withInput();
}
```

Cờ `login_needs_support` nay có nghĩa hẹp: *"số này chưa gắn hộp thư → ô email thành bắt buộc,
thêm dòng Zalo bên dưới"*. **Không** dùng lại cờ này cho ngõ cụt "email gắn với số đang thuộc
tài khoản khác" — số đó CÓ hộp thư, bật cờ sẽ hiện đúng câu sai rồi đẩy khách vào chốt §4c.

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

### 4f. Giao diện — vẫn là ô email cũ

`lookup()` trả thêm `needs_support` → LoginModal đổi ô email thành **bắt buộc** ngay khi khách
gõ xong SĐT: placeholder thành *"Email (bắt buộc)"*, nút "Tiếp tục" khoá tới khi có email hợp
lệ, và bên dưới thêm một dòng chữ nhỏ *"Chưa có email? Nhắn Zalo để shop hỗ trợ"*.

Không có khối cảnh báo to, không ẩn nút "Tiếp tục" — Zalo là **lối phụ**, không phải lối duy nhất.

Vẫn cần flash `login_needs_support` bên cạnh: với khách **vãng lai** (có đơn cũ, chưa có tài
khoản), `lookup()` cố tình trả `exists: false` để không lộ "số này từng thuê đồ ở shop" — nên
chỉ tới lúc bấm gửi mã mới biết. Effect đọc cờ này phải phụ thuộc **cả object `flash`**, không
phải riêng giá trị boolean: giá trị giữ nguyên `true` giữa hai lần chặn nên effect sẽ không
chạy lại lần thứ hai.

**Lỗi đua ở debounce tra SĐT** (có sẵn từ trước, nay mới đáng kể): gõ số A → chờ 450ms → bắn
request A → gõ tiếp thành B → bắn request B; A về sau B sẽ ghi đè trạng thái của B. Từ khi
`lookup` điều khiển cả `needsSupport`, ghi đè nhầm nghĩa là bắt nhập email cho số không cần
(hoặc bỏ bắt cho số cần). Đã chặn bằng `if (phone !== lastLookup.current) return;` sau `await`.

## 5. Cái giá phải trả — nói thẳng

| Ai | Ảnh hưởng |
|---|---|
| Khách phone-only mất cookie | Nhập một email là vào lại được (qua OTP) |
| Khách vãng lai từng đặt đơn không email | Nhập email là nhận được số đó |
| **Tài khoản/SĐT chưa có email** | ⚠️ **người lạ biết SĐT chiếm được**, xem trọn lịch sử đơn |
| Khách đã có email | Không ảnh hưởng — chốt `allowedEmails` (§4c) vẫn bảo vệ đầy đủ |

Dòng thứ ba là đánh đổi ở §3a, chủ shop đã biết và chấp nhận. Ghi ở đây để lần sau ai đọc code
cũng thấy — **không phải sơ suất**.

**Người trực Zalo vẫn cần quy tắc.** Zalo giờ là lối phụ, nhưng vẫn là một mặt tấn công: kẻ lạ
sẽ nhắn *"em quên email, anh gắn giúp em email này"*. Người trực **phải** hỏi thông tin một đơn
cũ (mã đơn / ngày thuê / địa chỉ giao) trước khi gắn email.

## 6. Việc phải làm TRƯỚC khi deploy production

Chủ shop đã chốt **force-logout toàn bộ phiên hiện có** (rotate `remember_token`) ở
`bopcamping-bqsv`. Sau khi nới luật 2 (§3a), việc này **nhẹ hơn hẳn**: khách phone-only bị đá
ra chỉ cần nhập một email là vào lại được, không còn dồn hết về Zalo.

Vẫn nên **đếm trước** để biết bao nhiêu khách sẽ gặp bước nhập email lạ lẫm — và vì con số này
chính là **diện chịu rủi ro ở §3a** (số người mà kẻ lạ biết SĐT là chiếm được tài khoản):

```sql
SELECT COUNT(*) FROM users u
WHERE u.is_admin = 0
  AND u.email LIKE '%@bopcamping.local'
  AND NOT EXISTS (
    SELECT 1 FROM orders o
    WHERE o.customer_phone = u.phone AND o.customer_email IS NOT NULL
  );
```

- Con số **nhỏ** → deploy thẳng, rủi ro §3a không đáng kể.
- Con số **lớn** → cân nhắc bổ sung bước hỏi mã đơn cũ (bead riêng) trước khi lên production,
  vì đó chính là số tài khoản đang chiếm được chỉ bằng SĐT.

## 7. Test

### Đã viết lại (vì khẳng định đúng hành vi cũ)

| Test | Đổi gì |
|---|---|
| `remember_cookie_lasts_sixty_days` | Đổi tên + đi qua luồng OTP; 60 ngày giờ chỉ đúng cho tài khoản **có email** |
| `old_phone_account_can_add_email_via_otp` | Đổi tên thành `a_phone_only_account_can_attach_an_email_at_login`, thêm khẳng định "chưa xác thực thì chưa đổi email" |
| `returning_guest_without_a_real_email_is_asked_for_one…` | Thay bằng `a_phone_only_account_must_type_an_email_and_is_offered_zalo` (kiểm cả cờ `login_needs_support`) |
| `a_phone_with_guest_orders_that_carry_no_email_cannot_be_claimed` | Thành `…must_type_an_email` — bỏ trống vẫn chặn, gõ email thì qua |

### Mới

| Test | Khẳng định |
|---|---|
| `remember_cookie_lasts_four_hundred_days_for_a_phone_only_account` | Cookie 400 ngày |
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

## 7b. Đã đo thật trên staging — ĐỢT 1, bản CHẶN (26/08/2026)

> ⚠️ Đợt đo này chạy trên bản **chặn hẳn** (trước khi nới luật 2 ở §3a). Ca 3 và 4 đã đổi hành
> vi; giữ lại làm hồ sơ. Kết quả đo lại của bản hiện tại ở **§7c**.

Vì không chạy được test cục bộ, toàn bộ luật được đo trực tiếp trên
`staging.bopcamping.cloud` bằng trình duyệt, gồm cả đường **bỏ qua giao diện**.

| # | Kịch bản | Cách đo | Kết quả |
|---|---|---|---|
| 1 | Số mới `0912000111` → vào thẳng | Modal đăng nhập | ✅ vào được |
| 2 | Cookie 400 ngày | Header `Set-Cookie` của `POST /dang-nhap` | ✅ `Max-Age=34560000` (= 400 ngày), hết hạn 30/09/2027 |
| 3 | Đăng xuất rồi vào lại bằng đúng số đó | Modal đăng nhập | ✅ hiện cảnh báo + nút Zalo (`zalo.me/791036…`), **ẩn** nút "Tiếp tục" |
| 4 | Kẻ tấn công bỏ qua giao diện: `POST /dang-nhap` với email của họ | `fetch()` trong console | ✅ `auth.user: null`, lỗi ở `phone`, `login_needs_support: true` |
| 5 | Tài khoản nạn nhân sau đòn tấn công | `lookup()` | ✅ tên vẫn "Khách Chỉ SĐT" (không thành "Kẻ Lạ"), email vẫn là bản tạm |
| 6 | Điền email lúc checkout (`0912000222`) | `POST /dat-hang` rồi `lookup()` | ✅ `email_mask: "kh***********@example.com"`, `needs_support: false` |
| 7 | Sau khi gắn email, đăng xuất rồi vào lại | `POST /dang-nhap` chỉ có SĐT | ✅ không vào thẳng, gửi OTP, email trả về **đã che** |

Kịch bản 4 là kịch bản đáng giá nhất: giao diện ẩn nút không phải là bảo mật — chốt chặn
phải nằm ở server, và nó nằm đúng chỗ.

**Lưu ý còn tồn:** tài khoản đã "lên đời" có email (ca 6) vẫn giữ cookie 400 ngày cấp từ lần
đăng nhập đầu, vì cookie phát một lần lúc đăng nhập. Không đáng sửa — tài khoản đó nay đã có
đường OTP để quay lại, chỉ là cookie sống dài hơn 60 ngày cho tới khi họ đăng nhập lại.

**Dữ liệu test còn trên staging:** `0912000111` (tài khoản bị khoá vĩnh viễn — cố ý, để
kiểm chứng), `0912000222` + đơn `BOP-3D2054`.

## 8. Còn thiếu (đã ghi bead riêng)

- **Sửa email trong trang tài khoản.** Gõ sai email lúc checkout → tài khoản gắn vĩnh viễn vào
  hộp thư không đọc được → lần sau OTP gửi vào hư không, lại phải qua Zalo. Cần cho khách sửa
  email khi vẫn đang đăng nhập.
- **Test vitest cho LoginModal** ở trạng thái `needsSupport` (hiện chưa có test nào render
  LoginModal, và không chạy thử được trong phiên này).
