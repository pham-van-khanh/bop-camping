# Design Spec — Vá lỗ hổng đăng nhập + Đăng nhập thay khách từ admin

> Trạng thái: **ĐÃ VIẾT CODE, CHƯA CHẠY ĐƯỢC MỘT BÀI TEST NÀO.**
> Ngày: 2026-08-24 · Liên quan: `bopcamping-bqsv` (P0)
>
> Phiên làm việc bị chặn công cụ (không chạy được test/lint/build/git). Chủ shop đã biết rủi
> ro và quyết định cứ triển khai, nên code đã được viết thẳng vào repo. Tài liệu này giữ lại
> **thiết kế và lý do**; phần code trích trong đây có thể lệch chút ít so với bản đã áp —
> **file thật trong `app/` mới là bản đúng**.
>
> ⚠️ **Việc bắt buộc trước khi merge:** chạy đủ gate (§9) và sửa những gì đỏ. Danh sách test
> cũ đã được viết lại vì chúng khẳng định đúng hành vi lỗ hổng — xem §8.

---

## 1. Vì sao phải sửa

Số điện thoại đang được dùng làm **bằng chứng danh tính**, nhưng nó chưa bao giờ được xác
thực (không có OTP SMS). Hệ quả: gõ đúng SĐT của người khác là vào được tài khoản họ.

**Đã đo bằng test chạy thật** (2 kịch bản, cả hai đều `CHIẾM ĐƯỢC = true`):
- Nạn nhân có email tạm / chưa xác thực → vào thẳng.
- Nạn nhân có email thật **đã xác thực** → server tự điền email đã lưu rồi vẫn vào thẳng.

**Suy từ code, CHƯA chạy được test chứng minh** (cần kiểm ngay khi triển khai):
- `GuestAuthController::store()` dòng 66 **không lọc `is_admin`**, trong khi `lookup()` dòng 27
  thì có. Nên luồng trên nhiều khả năng chiếm được cả **tài khoản admin** — mà `ADMIN_PHONE`
  mặc định trùng số hotline in công khai ở footer.
- Đơn hàng gắn với khách qua `User::relatedOrders()` = `user_id` **HOẶC** `customer_phone`.
  Nên kể cả nạn nhân chưa từng có tài khoản, kẻ lạ gõ số của họ sẽ được tạo tài khoản mới và
  tài khoản đó **thấy luôn đơn cũ** của nạn nhân (tên, địa chỉ nhà, SĐT).

**Phạm vi thiệt hại** (tra trong code): lịch sử đơn kèm địa chỉ nhà · voucher (tiêu được, vì
`VoucherService::apply` lấy voucher theo `$order->user`) · mã giới thiệu · đổi được email tài
khoản. Xoá tài khoản thì không (đòi `current_password`, khách không có mật khẩu).

---

## 2. Quyết định của chủ shop (24/08/2026)

| Điểm | Chốt |
|---|---|
| Cookie nhớ đăng nhập | **60 ngày** (hiện đang là mặc định Laravel 400 ngày) |
| Tài khoản dùng email tạm trên production | Đếm được **0** → bỏ phương án dự phòng cho nhóm này |
| Phiên đang đăng nhập của khách cũ | **Đá ra hết** khi deploy |
| Đăng nhập thay khách từ admin | **Có** — một nút trong `/admin/users`, không cần OTP |

---

## 3. Việc 1 — Hotfix một dòng (làm được ngay, độc lập)

`app/Http/Controllers/Shop/GuestAuthController.php` dòng 66:

```php
// TRƯỚC
$existing = User::where('phone', $data['phone'])->first();

// SAU — thêm đúng điều kiện mà lookup() dòng 27 đã có
$existing = User::where('phone', $data['phone'])->where('is_admin', false)->first();
```

Chặn đường chiếm tài khoản admin. Không ảnh hưởng gì tới khách. Có thể deploy riêng, trước
phần còn lại.

---

## 3b. Ba lỗ hổng phát hiện thêm khi soi lại bản vá (đã sửa)

Bản vá đầu **chưa đủ**. Soi kỹ lần hai tìm ra:

1. **Chiếm tài khoản qua email của chính mình.** Bắt "luôn OTP" nhưng không giới hạn *gửi đi
   đâu* thì vô dụng: kẻ lạ gõ SĐT nạn nhân + email CỦA MÌNH → mã về hộp thư kẻ đó →
   `verifyOtp` tìm tài khoản theo SĐT, gắn email mới vào **tài khoản nạn nhân** rồi đăng nhập.
   Mất trọn tài khoản, y như trước khi vá.
   → Sửa: OTP **chỉ được gửi tới hộp thư đã gắn sẵn với số đó** (email tài khoản + email trên
   các đơn cũ của số đó). Gõ email lạ cho một số đã có chủ → từ chối.

2. **`verifyOtp` không lọc admin** → vá thêm một lớp.

3. **Có thể tạo tài khoản khách trùng SĐT với admin** → chuyển sang chặn hẳn SĐT admin ngay
   đầu `store()`.

4. **Lỗi 500 tiềm ẩn:** SĐT có đơn cũ mang email đã thuộc tài khoản khác → `verifyOtp` tạo
   user mới trùng email → vỡ ràng buộc `users.email UNIQUE`. → Kiểm trước, trả thông báo đọc
   được thay vì 500.

## 4. Việc 2 — Luôn OTP khi SĐT đã thuộc về ai đó

Sửa `GuestAuthController::store()`. Nguyên tắc: **chỉ cho vào thẳng khi số đó thật sự chưa
gắn với ai** (chưa có tài khoản VÀ chưa có đơn nào). Mọi trường hợp khác đều phải qua OTP.

```php
use App\Models\Order;   // thêm import

$existing = User::where('phone', $data['phone'])->where('is_admin', false)->first();
$phoneHasOrders = Order::where('customer_phone', $data['phone'])->exists();

$email = trim((string) ($data['email'] ?? ''));

if ($email === '') {
    // Số này đã gắn với một người có thật -> KHÔNG bao giờ cho vào chỉ bằng SĐT.
    if ($existing || $phoneHasOrders) {
        if ($existing && $existing->email_verified_at && ! $existing->hasPlaceholderEmail()) {
            // Có hộp thư thật -> gửi OTP tới ĐÓ. Không tự đăng nhập như trước.
            $otp->send($existing->email);
            $request->session()->put('otp_pending', [
                'name' => $this->resolveName($data['name'] ?? null, $existing, $data['phone']),
                'phone' => $data['phone'],
                'email' => $existing->email,
            ]);

            return back()->with('otp_sent', true)->with('otp_email', $existing->email);
        }

        // Không có hộp thư nào để gửi -> buộc nhập email rồi xác thực.
        return back()->withErrors([
            'email' => 'Vui lòng nhập email để nhận mã xác thực.',
        ])->withInput();
    }

    // Số hoàn toàn mới: chưa tài khoản, chưa đơn -> không có gì để chiếm, cho vào thẳng.
    $user = new User(['phone' => $data['phone']]);
    $user->name = $this->resolveName($data['name'] ?? null, null, $data['phone']);
    $user->save();

    Auth::login($user, remember: true);
    $request->session()->regenerate();

    return back();
}
```

**Và XOÁ hẳn nhánh vào-thẳng khi email đã xác thực** (hiện ở dòng ~98–108):

```php
// XOÁ khối này — đây chính là đường khai thác thứ hai
if ($existing && $existing->email_verified_at && $existing->email === $email) {
    ...
    Auth::login($existing, remember: true);
    return back();
}
```

Sau khi xoá, mọi đường đều rơi xuống `$otp->send($email)` như phần cuối hàm đang làm.

> Đánh đổi: khách quen sẽ nhập OTP **một lần trên mỗi thiết bị**. Sau đó cookie 60 ngày lo
> phần còn lại. Đây đúng tinh thần "chỉ OTP lần đầu" của KE_HOACH, chỉ khác là *lần đầu trên
> mỗi thiết bị* thay vì *lần đầu mãi mãi*.

---

## 5. Việc 3 — Cookie nhớ đăng nhập 60 ngày

Laravel mặc định `576000` phút (**400 ngày**, `SessionGuard::$rememberDuration`), app không
chỉnh gì. Đặt lại tường minh trong `app/Providers/AppServiceProvider.php::boot()`:

```php
use Illuminate\Support\Facades\Auth;

// Nhớ đăng nhập 60 ngày (mặc định Laravel là 400 ngày — quá dài cho máy dùng chung).
// Đây là thứ giữ khách quen khỏi phải nhập OTP mỗi lần; SESSION_LIFETIME (120 phút) chỉ
// là tuổi của phiên, không phải tuổi của cookie này.
Auth::viaRequest; // (giữ nguyên các cấu hình sẵn có)
Auth::guard('web')->setRememberDuration(60 * 24 * 60); // 86.400 phút
```

> Nếu gọi trong `boot()` gây khởi tạo guard sớm ngoài ý muốn, chuyển sang gọi trong một
> middleware nhẹ hoặc ngay trước mỗi `Auth::login(..., remember: true)`. Kiểm bằng test ở §8.

---

## 6. Việc 4 — Đăng nhập thay khách từ admin

### 6.1 Nguyên tắc thiết kế

Chủ shop yêu cầu: bấm một nút trong `/admin/users` là vào tài khoản khách, **không OTP**.

Cách làm **KHÔNG** dùng: thêm ngoại lệ kiểu "nếu là admin thì bỏ qua OTP" vào luồng đăng nhập
khách. Chính dạng ngoại lệ đó đã đẻ ra lỗ hổng đang phải vá. Thay vào đó dựng **một đường
riêng, có kiểm soát**:

| Ràng buộc | Vì sao |
|---|---|
| Route nằm trong nhóm `admin` | Chỉ admin gọi được, dùng lại `EnsureAdmin` sẵn có |
| Không cho mạo danh admin khác | Chặn leo thang quyền giữa các admin |
| `Auth::login($user)` **KHÔNG** `remember` | Không để lại cookie 60 ngày của tài khoản khách trên máy admin |
| Ghi log ai mạo danh ai | Dấu vết đối chiếu khi có khiếu nại |
| Route thoát nằm **NGOÀI** nhóm `admin` | Lúc đang mạo danh, phiên là KHÁCH — `EnsureAdmin` sẽ chặn |

### 6.2 Routes (`routes/web.php`)

```php
// Trong nhóm Route::middleware(['admin'])->prefix('admin')->name('admin.')
Route::post('/users/{user}/dang-nhap-thay', [AdminUserController::class, 'impersonate'])
    ->name('users.impersonate')->middleware('throttle:30,1');

// NGOÀI nhóm admin — khi đang mạo danh thì phiên là khách, không qua nổi EnsureAdmin.
Route::post('/thoat-dang-nhap-thay', [ImpersonationController::class, 'stop'])
    ->name('impersonate.stop')->middleware('auth');
```

### 6.3 `AdminUserController::impersonate`

```php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Đăng nhập thay một khách để hỗ trợ (bopcamping-bqsv).
 *
 * Cố ý KHÔNG dùng `remember`: phiên mạo danh chỉ sống trong phiên hiện tại, không để lại
 * cookie 60 ngày của tài khoản khách trên máy admin.
 */
public function impersonate(Request $request, User $user): RedirectResponse
{
    // Không cho mạo danh admin khác — nếu không, một admin chiếm được quyền của admin kia.
    abort_if($user->is_admin, 403, 'Không thể đăng nhập thay một tài khoản admin.');

    $admin = $request->user();

    Log::info('Admin đăng nhập thay khách', [
        'admin_id' => $admin->id,
        'target_id' => $user->id,
        'target_phone' => $user->phone,
        'ip' => $request->ip(),
    ]);

    Auth::login($user);                 // KHÔNG remember
    $request->session()->regenerate();  // chống session fixation
    $request->session()->put('impersonator_id', $admin->id);

    return redirect()->route('account');
}
```

### 6.4 `app/Http/Controllers/ImpersonationController.php` (mới)

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    /** Thoát mạo danh, quay lại tài khoản admin ban đầu. */
    public function stop(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull('impersonator_id');
        $admin = $adminId ? User::find($adminId) : null;

        // KHÔNG tin session: quyền admin có thể đã bị gỡ trong lúc đang mạo danh.
        if (! $admin || ! $admin->is_admin) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login');
        }

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('admin.users');
    }
}
```

### 6.5 Prop dùng chung cho thanh báo (`HandleInertiaRequests::share`)

```php
// Đang xem hộ khách nào — để layout hiện thanh nhắc + nút Thoát.
'impersonating' => fn () => $request->session()->has('impersonator_id')
    ? ['name' => $request->user()?->name]
    : null,
```

### 6.6 Nút trong `/admin/users`

`resources/js/Pages/Admin/Users.tsx` — hàng khách, cạnh nút "Xem"/"Xoá" (khoảng dòng 486):

```tsx
<button
    onClick={() => router.post(route('admin.users.impersonate', c.id))}
    className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
>
    Đăng nhập
</button>
```

### 6.7 Thanh nhắc trong `SiteLayout.tsx`

```tsx
{impersonating && (
    <div className="flex items-center justify-center gap-3 bg-[#fdf6e3] px-4 py-2 text-[13px] text-[#8a6d1f]">
        <span>
            Bạn đang xem với tư cách <b>{impersonating.name}</b>
        </span>
        <button
            onClick={() => router.post(route('impersonate.stop'))}
            className="rounded-[8px] border border-[#e0d0a0] px-3 py-1 font-semibold"
        >
            Thoát
        </button>
    </div>
)}
```

---

## 7. Việc 5 — Đá hết phiên cũ khi deploy

Vì luôn dùng `Auth::login(..., remember: true)` với cookie rất dài, ai đã khai thác lỗ hổng
đang giữ một chiếc chìa còn hạn rất lâu. Vá luồng đăng nhập chỉ khoá cửa trước; phải xoay
token để vô hiệu mọi cookie cũ.

Chạy **SAU khi deploy code**:

```bash
php artisan tinker --execute="DB::table('users')->update(['remember_token' => null]); echo 'da xoay token';"
```

Hệ quả: mỗi khách nhập OTP đúng một lần trên mỗi thiết bị, rồi cookie 60 ngày tiếp quản.

---

## 8b. Test CŨ đã phải viết lại (vì chúng khẳng định đúng hành vi lỗ hổng)

Không phải "sửa test cho xanh" — hành vi mà chúng mô tả chính là đường tấn công:

| File | Test | Trước | Sau |
|---|---|---|---|
| `LoginLookupRenameTest` | `returning_user_logs_in_with_phone_only_using_stored_email` | SĐT không thôi → vào thẳng | đổi tên thành `phone_only_never_logs_into_an_existing_account`, giờ phải gửi OTP + `assertGuest` |
| `LoginLookupRenameTest` | `returning_user_can_change_name_on_direct_login` | gõ email đã verify → vào thẳng + đổi tên | đổi tên qua luồng OTP; tên trong DB **không** đổi trước khi xác thực |
| `LoginLookupRenameTest` | `name_is_optional_and_keeps_existing_when_blank` | vào thẳng | kiểm `otp_pending.name` |
| `AdminAccessTest` | `verified_user_logs_in_without_otp` | vào thẳng | đổi tên thành `verified_user_must_still_pass_otp` |
| `AdminAccessTest` | `guest_login_allows_existing_phone_to_change_name` | vào thẳng + đổi tên | tên nằm chờ ở `otp_pending` |
| `OtpFlowTest` | `returning_guest_without_verified_email_logs_in_directly_without_otp` | vào thẳng | đổi thành "bị yêu cầu nhập email" |
| `AdminUserTest` | khách admin tạo hộ đăng nhập bằng SĐT | vào thẳng | OTP gửi tới email admin đã điền hộ |

## 8. Test phải có

**Luồng đăng nhập:**
1. SĐT của **admin** → không bao giờ đăng nhập được qua luồng khách *(kiểm ngược: bỏ `is_admin` filter thì test này phải đỏ)*.
2. SĐT đã có tài khoản + bỏ trống email → **không** đăng nhập, có `otp_sent`.
3. SĐT chưa có tài khoản nhưng **đã có đơn vãng lai** → không vào thẳng.
4. SĐT hoàn toàn mới → vẫn vào thẳng (không làm hỏng trải nghiệm khách mới).
5. Nhập đúng email đã xác thực → vẫn phải OTP (nhánh vào-thẳng đã bị xoá).
6. `rememberDuration` = 86400 phút.

**Mạo danh:**
7. Admin bấm → đang đăng nhập là khách, session có `impersonator_id`, **không** có cookie remember.
8. Mạo danh một admin khác → 403.
9. Khách thường gọi route mạo danh → bị `EnsureAdmin` chặn.
10. Thoát → quay lại đúng tài khoản admin.
11. Thoát khi quyền admin đã bị gỡ giữa chừng → đăng xuất hẳn, không cho vào admin.

---

## 8c. Lệnh phải chạy — giải thích từng lệnh

Code đã nằm trên nhánh `feature/auth-hardening-impersonation` (đã commit + push). Việc còn
lại là chạy các lệnh dưới đây; **chưa lệnh nào được chạy lần nào.**

```bash
git checkout feature/auth-hardening-impersonation

# 1. Test PHP — QUAN TRỌNG NHẤT. Phải dùng MySQL, không dùng SQLite mặc định.
DB_CONNECTION=mysql DB_DATABASE=bop_camping_test php artisan test
```
> **Cần gì:** container MySQL `bopcamping_db` phải đang chạy (`docker start bopcamping_db`),
> và DB `bop_camping_test` phải tồn tại. Máy dev này thiếu `pdo_sqlite` nên chạy mặc định sẽ
> lỗi `could not find driver`.
>
> **Kỳ vọng:** ~1030 test. Nhóm dễ đỏ nhất: `OtpFlowTest`, `LoginLookupRenameTest`,
> `AdminAccessTest`, `AdminUserTest`, `AdminImpersonationTest` — đây đúng là những file đã
> sửa. Đỏ ở đây là bình thường và cần đọc kỹ, KHÔNG sửa test cho xanh: 7 test cũ đã được
> viết lại vì chúng khẳng định đúng hành vi lỗ hổng (xem §8b).

```bash
# 2. Test giao diện (jsdom)
npx vitest run
```
> **Kỳ vọng:** 26 file / 212 test. Các test combo đều mock `SiteLayout` nên thanh "đang xem
> với tư cách…" không ảnh hưởng. Nếu đỏ, nhiều khả năng do mock `@inertiajs/react` thiếu
> hàm mới.

```bash
# 3. Kiểu TypeScript
npx tsc --noEmit
```
> **Kỳ vọng:** im lặng. Rủi ro đã biết: prop `impersonating` mới thêm vào `PageProps`, đã để
> optional (`?`) để không bắt mọi nơi phải khai.

```bash
# 4. Lint JS — CHỈ KIỂM, không sửa file
npm run lint
```
> **Kỳ vọng:** `0 errors`, còn ~9 warning có sẵn từ trước. Rủi ro đã biết: `LoginModal.tsx`
> vừa bị gỡ state `useOtherEmail`; nếu còn sót chỗ dùng, lint sẽ báo biến không tồn tại.
> Lỗi định dạng thì chạy `npm run lint:fix` rồi **xem lại diff**.

```bash
# 5. Định dạng PHP
./vendor/bin/pint --test
```
> **Kỳ vọng:** `passed`. Đỏ thì chạy `./vendor/bin/pint` để tự sửa.

```bash
# 6. Build production
npm run build
```
> **Kỳ vọng:** `✓ built`. Đây là chốt chặn cuối bắt lỗi import/cú pháp mà tsc bỏ sót.

**Chỉ khi cả 6 lệnh xanh** mới merge sang `develop` (staging) → kiểm thật trên staging →
rồi mới merge `feat/scaffold-laravel` (production).

**Sau khi production lên xong**, chạy nốt lệnh xoay token ở §7 — thiếu bước này thì kẻ đã
khai thác vẫn giữ cookie còn hạn.

### Kiểm tay trên staging (không lệnh nào thay được)

1. Khách quen, máy lạ → phải hiện ô nhập OTP, mã về đúng hộp thư cũ.
2. Gõ SĐT của khách khác + email của mình → phải bị từ chối.
3. Gõ SĐT hotline admin ở modal khách → phải bị từ chối.
4. `/admin/users` → bấm **"Đăng nhập"** → vào tài khoản khách, thấy thanh vàng, bấm **Thoát**
   → quay lại đúng tài khoản admin.
5. Đăng nhập rồi đóng trình duyệt, mở lại → vẫn đăng nhập (cookie 60 ngày còn hạn).

## 9. Thứ tự triển khai

1. Nhánh mới từ `feat/scaffold-laravel`.
2. Áp §3 (hotfix) trước, có thể deploy riêng nếu muốn chặn sớm.
3. Áp §4, §5, §6 + test §8.
4. Gate đầy đủ: `php artisan test` (MySQL, DB `bop_camping_test`) · `npx vitest run` ·
   `npx tsc --noEmit` · `npm run lint` · `./vendor/bin/pint --test` · `npm run build`.
5. Merge `develop` → test staging → merge `feat/scaffold-laravel` → production.
6. **Sau khi production lên xong**, chạy lệnh xoay token ở §7.
7. Đóng `bopcamping-bqsv`, ghi rõ đã đo lại kịch bản tấn công và nó không còn tái hiện được.

---

## 10. Điều cần nói với khách

Sau khi bật, khách quen sẽ gặp OTP một lần trên mỗi thiết bị. Nên chọn khung giờ vắng và có
sẵn đường Zalo hỗ trợ cho ai kẹt email — dù số tài khoản dùng email tạm đã đếm được là 0,
vẫn sẽ có người gõ nhầm email hoặc không mở được hộp thư.
