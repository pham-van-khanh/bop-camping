# Checklist test staging — đợt bảo mật đăng nhập (08/2026)

**Phạm vi:** hai bead `bopcamping-bqsv` + `bopcamping-kuhg`, nằm trên **một** nhánh
`feature/phone-only-account-recovery` (nhánh này đã chứa trọn `feature/auth-hardening-impersonation`).
Cả hai **chưa lên production**.

**Staging:** https://staging.bopcamping.cloud

---

## 0. Chạy test tự động TRƯỚC (bắt buộc, chưa ai chạy lần nào)

Máy này thiếu `pdo_sqlite` nên phải trỏ sang MySQL với DB test riêng:

```bash
DB_CONNECTION=mysql DB_DATABASE=bop_camping_test php artisan test --filter=OtpFlowTest
DB_CONNECTION=mysql DB_DATABASE=bop_camping_test php artisan test --filter=AdminImpersonationTest
DB_CONNECTION=mysql DB_DATABASE=bop_camping_test php artisan test --filter=OrderCheckoutTest
DB_CONNECTION=mysql DB_DATABASE=bop_camping_test php artisan test --filter=LoginLookupRenameTest
DB_CONNECTION=mysql DB_DATABASE=bop_camping_test php artisan test --filter=AdminUserTest

# rồi TOÀN BỘ — quan trọng hơn cả 5 lệnh trên, vì đợt này đụng vào đăng nhập,
# thứ mà hầu hết test khác đều dựa vào
DB_CONNECTION=mysql DB_DATABASE=bop_camping_test php artisan test
```

Các gate còn lại:

```bash
npm test && npx tsc --noEmit && npm run lint && ./vendor/bin/pint --test && npm run build
```

> Tạo DB test một lần nếu chưa có:
> `docker exec bopcamping_db mysql -uroot -p… -e "CREATE DATABASE IF NOT EXISTS bop_camping_test"`

### Nếu có test đỏ — đọc trước khi sửa

Đợt này **đổi hành vi có chủ ý**. Test đỏ có thể là test cũ đang khẳng định hành vi đã bỏ, chứ
không phải code sai. Ba hành vi đã đổi:

| Hành vi cũ | Hành vi mới |
|---|---|
| Gõ đúng SĐT của tài khoản đã verify → vào thẳng | Luôn qua OTP |
| Gõ đúng email đã verify → vào thẳng | Luôn qua OTP |
| Cookie nhớ đăng nhập 400 ngày cho mọi người | 60 ngày, riêng tài khoản chỉ-có-SĐT vẫn 400 |

Test đỏ ngoài ba nhóm này thì **là lỗi thật** — báo lại, đừng sửa test cho xanh.

---

## 1. Đăng nhập khách — luồng thường

| # | Việc làm | Mong đợi | ✓ |
|---|---|---|---|
| 1.1 | SĐT hoàn toàn mới, bỏ trống email | Vào thẳng, không cần mã | ☐ |
| 1.2 | SĐT mới + email | Gửi mã tới email đó, nhập mã xong mới vào | ☐ |
| 1.3 | Nhập sai mã 6 số | Báo *"Mã không đúng hoặc đã hết hạn"*, không vào được | ☐ |
| 1.4 | Bấm "Gửi lại mã" | Mã cũ hết hiệu lực, chỉ mã mới nhất dùng được | ☐ |
| 1.5 | Khách quen (có email) gõ SĐT, bỏ trống email | Hiện email **đã che** (`ng***@gmail.com`), gửi mã tới đó | ☐ |
| 1.6 | Đổi tên hiển thị lúc đăng nhập | Tên chỉ đổi **sau khi** nhập đúng mã | ☐ |

## 2. Tài khoản chỉ có SĐT (`bopcamping-kuhg`)

| # | Việc làm | Mong đợi | ✓ |
|---|---|---|---|
| 2.1 | Tạo tài khoản chỉ bằng SĐT, đăng xuất, đăng nhập lại | Ô email đổi thành **"Email (bắt buộc)"**, dưới có dòng *"Chưa có email? Nhắn Zalo…"* | ☐ |
| 2.2 | Ở màn hình 2.1, chưa gõ email | Nút "Tiếp tục" **xám, không bấm được** | ☐ |
| 2.3 | Gõ email hợp lệ | Nút mở khoá, bấm → nhận mã → vào được | ☐ |
| 2.4 | Bấm link Zalo ở 2.1 | Mở đúng Zalo OA của shop | ☐ |
| 2.5 | Sau 2.3, đăng xuất rồi vào lại chỉ bằng SĐT | Gửi mã tới email vừa gắn, hiện bản **che** | ☐ |
| 2.6 | **Trên điện thoại** — lặp lại 2.1 | Chữ không tràn, nút Zalo bấm trúng | ☐ |

## 3. Chống chiếm tài khoản — phần quan trọng nhất

> Làm ở **cửa sổ ẩn danh** để không dính phiên đang đăng nhập.

| # | Việc làm | Mong đợi | ✓ |
|---|---|---|---|
| 3.1 | Gõ SĐT của khách **đã có email** + email **của bạn** | ❌ **Chặn** — *"Email không khớp với số điện thoại này"* | ☐ |
| 3.2 | Gõ SĐT khách đã có email, bỏ trống | Mã về hộp thư **của khách đó**, bạn không vào được | ☐ |
| 3.3 | Sau 3.1 và 3.2, hỏi lại khách | Tên và email của họ **không bị đổi gì** | ☐ |
| 3.4 | Gõ SĐT **hotline shop** (0976544370) ở cửa đăng nhập khách | ❌ Chặn — *"Số điện thoại này không dùng để đăng nhập khách"* | ☐ |
| 3.5 | Gõ hotline vào ô SĐT rồi chờ 1 giây | Không hiện tên/email admin, không gợi ý gì | ☐ |
| 3.6 | Gõ SĐT bất kỳ rồi xem màn nhập mã | Email hiện ra phải **luôn che** — không được lộ nguyên địa chỉ | ☐ |

**3.6 là ca đã bắt được lỗi thật lần trước** — bản vá đầu vô tình trả nguyên
`phamkhanhcntt@gmail.com` chỉ từ một số điện thoại.

## 4. Đăng nhập thay khách từ admin (`bopcamping-bqsv`) — **chưa ai đo được**

Cần tài khoản admin staging. Đây là phần rủi ro nhất còn lại.

| # | Việc làm | Mong đợi | ✓ |
|---|---|---|---|
| 4.1 | `/admin/users` → tab Khách hàng → bấm **"Đăng nhập"** | Vào thẳng tài khoản khách, **không** hỏi mã | ☐ |
| 4.2 | Nhìn đầu trang sau 4.1 | Có thanh vàng *"Bạn đang xem với tư cách …"* + nút **Thoát** | ☐ |
| 4.3 | Bấm **Thoát** | Quay về `/admin/users`, là admin trở lại | ☐ |
| 4.4 | Lặp 4.1 rồi bấm **"Đăng xuất"** ở menu khách (KHÔNG phải Thoát) | ⚠️ Vẫn phải quay về `/admin/users` là admin — **không** bị đá ra ngoài | ☐ |
| 4.5 | Ở tab Quản trị viên, tìm nút "Đăng nhập" cho một admin khác | Không có nút đó | ☐ |
| 4.6 | Đang xem hộ khách, mở `/admin/users` ở tab khác | Bị đẩy về trang đăng nhập admin (đang là khách, đúng) | ☐ |
| 4.7 | Sau 4.1, đóng trình duyệt rồi mở lại | **Không** tự đăng nhập lại vào tài khoản khách | ☐ |
| 4.8 | Xem `storage/logs/laravel.log` sau 4.1 | Có dòng `admin.user.impersonated` với `actor_id`+`target_id`, **không có số điện thoại** | ☐ |

**4.4 là lỗi vừa sửa trong đợt này** — trước đó bấm "Đăng xuất" quen tay là mất luôn phiên admin,
phải đăng nhập lại từ đầu. Header khách không phân biệt được hai nút nên phải xử ở server.

**4.7 kiểm rằng phiên xem hộ không được cấp cookie nhớ đăng nhập** — nếu có, admin sẽ vô tình
để lại một đường vào tài khoản khách sống 60 ngày.

## 5. Gắn email từ checkout

| # | Việc làm | Mong đợi | ✓ |
|---|---|---|---|
| 5.1 | Đăng nhập chỉ bằng SĐT (số mới) → đặt một đơn, **có điền email** | Đơn tạo được, mail xác nhận về đúng email đó | ☐ |
| 5.2 | Sau 5.1, đăng xuất rồi gõ lại SĐT đó | Không còn đòi nhập email; hiện email **đã che** và gửi mã tới đó | ☐ |
| 5.3 | Khách **đã có** email thật, checkout điền email **người thân** | Mail đơn về email người thân, **tài khoản giữ nguyên email cũ** | ☐ |
| 5.4 | Checkout điền email đang dùng cho tài khoản khác | Đơn vẫn tạo được, tài khoản **không** bị đổi email | ☐ |

**5.3 quan trọng:** đặt hộ người thân mà bị đổi email tài khoản là tự đá mình ra ngoài.

## 6. Cookie nhớ đăng nhập

| # | Việc làm | Mong đợi | ✓ |
|---|---|---|---|
| 6.1 | DevTools → Application → Cookies sau khi tạo tài khoản chỉ-SĐT | `remember_web_*` hết hạn **~400 ngày** | ☐ |
| 6.2 | Tương tự sau khi đăng nhập bằng OTP email | Hết hạn **~60 ngày** | ☐ |
| 6.3 | Đóng hẳn trình duyệt rồi mở lại | Vẫn đang đăng nhập | ☐ |
| 6.4 | Bấm "Đăng xuất" rồi mở lại | Đã đăng xuất thật, không tự vào lại | ☐ |

## 7. Không làm hỏng thứ khác (regression)

Đợt này đụng vào đăng nhập — thứ mọi tính năng khác đều dựa vào.

| # | Việc làm | Mong đợi | ✓ |
|---|---|---|---|
| 7.1 | Đặt đơn khi **chưa** đăng nhập (khách vãng lai) | Vẫn đặt được như cũ | ☐ |
| 7.2 | Trang **Tài khoản** → lịch sử đơn | Hiện đủ đơn, kể cả đơn đặt lúc chưa đăng nhập cùng SĐT | ☐ |
| 7.3 | Áp voucher / mã giới thiệu khi đặt đơn | Vẫn chạy | ☐ |
| 7.4 | Link mời đánh giá trong mail | Mở và gửi được đánh giá | ☐ |
| 7.5 | Tra cứu đơn bằng mã + SĐT | Vẫn chạy | ☐ |
| 7.6 | Đăng nhập admin bằng SĐT + mật khẩu | Vẫn chạy bình thường | ☐ |
| 7.7 | Đăng nhập shipper | Vẫn chạy bình thường | ☐ |

---

## 8. Trước khi merge lên production

☐ **Đếm số khách sẽ gặp bước nhập email lạ** — cũng chính là số tài khoản đang chịu rủi ro ở
design_spec §3a:

```sql
SELECT COUNT(*) FROM users u
WHERE u.is_admin = 0
  AND u.email LIKE '%@bopcamping.local'
  AND NOT EXISTS (
    SELECT 1 FROM orders o
    WHERE o.customer_phone = u.phone AND o.customer_email IS NOT NULL
  );
```

☐ **Dặn người trực Zalo**: khi khách nhắn xin gắn email, **phải hỏi thông tin một đơn cũ**
(mã đơn / ngày thuê / địa chỉ giao) trước khi làm. Không có bước này thì kẻ lạ chỉ việc nhắn
Zalo là qua mặt toàn bộ phần vừa siết.

☐ **Sau khi deploy production**, chạy force-logout mọi phiên cũ (quyết định ở `bopcamping-bqsv`):

```sql
UPDATE users SET remember_token = NULL WHERE is_admin = 0;
```

Kèm `php artisan session:flush` hoặc xoá bảng/thư mục session tuỳ driver đang dùng.

☐ **Dọn dữ liệu test trên staging** (không bắt buộc): `0912000111`, `0912000222`, đơn `BOP-3D2054`.

---

## 9. Rủi ro đã biết và đã chấp nhận

Với **tài khoản chưa gắn email nào**, người lạ biết SĐT vẫn chiếm được tài khoản: mã gửi tới hộp
thư họ *vừa gõ* chỉ chứng minh họ mở được hộp thư đó, không chứng minh họ là chủ số — shop không
có OTP SMS nên số điện thoại chưa bao giờ được xác thực.

Khách **đã có email** không nằm trong diện này (mục 3.1 kiểm đúng chỗ đó).

Đây là đánh đổi chủ shop đã chốt để khách thật không bị kẹt. Hướng siết lại — hỏi thêm mã một
đơn cũ khi SĐT có lịch sử đơn — ghi ở bead `bopcamping-4j3z`.
