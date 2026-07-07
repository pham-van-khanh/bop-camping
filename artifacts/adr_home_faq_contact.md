# ADR — FAQ trang chủ + Thông tin liên hệ/Mạng xã hội (footer + dải Zalo)

> **Artifact:** `adr_home_faq_contact.md` · **Ngày:** 2026-07-07 · **Trạng thái:** ✅ Approved (2026-07-07, chủ shop duyệt)
>
> **Chốt khi duyệt:** (1) QĐ-2 = admin quản lý qua `SiteSetting` (KHÔNG hardcode). (2) Link Zalo = mặc định `zalo.me/<sđt>` (`zalo.me/0976544370`, `zalo.me/0373655008`), admin ghi đè được. (3) Facebook/TikTok = để trống, admin điền sau (icon tự ẩn khi trống). (4) Nội dung 10 FAQ ở mục 6.1 seed nguyên như soạn.
> **Phạm vi:** 3 hạng mục từ yêu cầu — (1) FAQ CRUD hiện ở home, (2.1) Footer liên hệ/social/địa chỉ, (2.2) dải 2 tài khoản Zalo dưới hero.
> **Nguyên tắc:** bám golden path (Laravel 12 + Inertia/React/TS, tông be/đất), theo đúng pattern có sẵn (singleton `PromotionSetting`, CRUD `Category`, shared props Inertia). KHÔNG hardcode dữ liệu nghiệp vụ.

---

## 1. Bối cảnh & hiện trạng

| Thành phần | Hiện trạng | Vấn đề |
|---|---|---|
| `resources/js/Components/site/Footer.tsx` | Hardcode: "0905 123 456", "123 Đường Trại, Đà Nẵng"; "Hướng dẫn thuê / Chính sách cọc / Câu hỏi thường gặp" là `<span>` chết; chưa có social | Số điện thoại giả, không có link hệ thống, không có Zalo/FB/TikTok |
| Home (`Welcome.tsx`) | Có `promo_banners` (ảnh, admin quản lý qua bảng `banners`); hero có nút **Xem thiết bị** + **Tra cứu đơn** | Chưa có FAQ; chưa có dải liên hệ Zalo |
| FAQ | **Chưa tồn tại** | Cần bảng + CRUD admin + section home + seed |
| Địa chỉ phục vụ | `service_locations` (name + area + status): Vinh–Nghệ An, Hà Nội | Đã là single source — footer nên đọc từ đây, không nhập lại |
| Cấu hình singleton | `PromotionSetting::current()` (`firstOrCreate`), sửa ở `Admin/PromotionController` (update-only) | Pattern chuẩn để nhân bản cho "thông tin shop" |
| Shared props | `HandleInertiaRequests@share` (auth, flash, referral, emailBonus, pending_*) | Footer nằm trong `SiteLayout` (mọi trang) → thông tin liên hệ nên là **shared prop**, không truyền lẻ từng controller |
| Admin CRUD mẫu | `Admin/CategoryController` (index/store/update/destroy) + `Pages/Admin/Categories.tsx` | Khuôn cho `Faq` CRUD |

**Quyết định chốt về địa chỉ:** footer đọc `ServiceLocation::open()` (đã có Vinh–Nghệ An, Hà Nội) — **không** lưu địa chỉ lần 2 trong settings (tránh lệch nguồn).

---

## 2. Quyết định kiến trúc

### QĐ-1 — FAQ: bảng riêng `faqs` + CRUD admin (theo pattern `Category`)

Bảng độc lập, model mỏng, controller 4 action, section accordion ở home, seed từ nội dung site thật. Không cần ảnh → đơn giản hơn cả `Category`.

### QĐ-2 — Thông tin liên hệ/social: singleton `SiteSetting` (admin sửa) — KHÔNG hardcode

Đây là fork quan trọng nhất. Trade-off:

| Tiêu chí | A. Hardcode trong component | B. Singleton `SiteSetting` (khuyến nghị) |
|---|---|---|
| Khớp quy ước codebase (mọi thứ admin quản lý, no-hardcode) | ✗ | ✓ |
| Đổi SĐT/Zalo/FB **không cần deploy** | ✗ (sửa code + build + push) | ✓ (sửa trong admin) |
| Single source of truth | ✗ (rải rác trong JSX) | ✓ |
| Rủi ro số sai/cũ | Cao | Thấp |
| Công sức | Thấp (~1h) | Trung bình (~3–4h) |

→ **Chọn B.** Chủ shop đã tự xây `PromotionSetting`, `banners`, `service_locations` theo hướng admin-quản-lý; contact info là thứ thực sự hay đổi (thêm số, đổi Zalo). Footer + dải Zalo cùng đọc 1 nguồn qua **shared prop Inertia** (`site`) nên có mặt ở mọi trang mà không phải sửa từng controller.

### QĐ-3 — Dải Zalo (2.2): component riêng đọc `SiteSetting`, đặt ngay dưới hero

KHÔNG tái dùng bảng `banners` (banner là ảnh promo, placement hero/promo) — dải Zalo là CTA liên hệ có cấu trúc (tên + SĐT + nút). Component riêng `ZaloContactStrip` sạch hơn, dữ liệu từ `site.zalo_1/zalo_2`.

### QĐ-4 — Link deep-link: `tel:` cho gọi, `zalo.me/<sđt>` cho Zalo

- SĐT → `tel:0976544370` (mobile mở màn hình gọi).
- Zalo → mặc định `https://zalo.me/<sđt>` (Zalo mở đúng trang cá nhân của số đã đăng ký); admin có thể ghi đè bằng URL vanity. Mở tab mới `target="_blank" rel="noreferrer"`.
- FB/TikTok → URL admin nhập; **ẩn icon nếu URL trống** (không hiện link chết).

---

## 3. Mô hình dữ liệu

### 3.1 Bảng `faqs` (mới)

```
faqs
  id
  question      string(255)         -- câu hỏi
  answer        text                -- trả lời (plain text/xuống dòng; v1 không cần rich text)
  sort_order    unsignedInteger  default 0   -- nhỏ = lên trước
  is_active     boolean          default true -- ẩn/hiện ở home không cần xoá
  timestamps
```
- Model `App\Models\Faq`: `$fillable` [question, answer, sort_order, is_active]; cast `is_active` bool, `sort_order` int; `scopeActive`, `scopeOrdered` (mirror `ServiceLocation`).

### 3.2 Bảng `site_settings` (mới, singleton — mirror `promotion_settings`)

```
site_settings
  id
  hotline_primary     string(20)  null   -- 0976544370
  hotline_secondary   string(20)  null   -- 0373655008
  zalo1_label         string(60)  null   -- vd "Chủ shop / Kinh doanh"
  zalo1_phone         string(20)  null   -- 0976544370
  zalo1_url           string(255) null   -- override; trống => zalo.me/{zalo1_phone}
  zalo2_label         string(60)  null
  zalo2_phone         string(20)  null
  zalo2_url           string(255) null
  facebook_url        string(255) null
  tiktok_url          string(255) null
  working_hours       string(100) null   -- "8:00 – 21:00 hằng ngày"
  timestamps
```
- Model `App\Models\SiteSetting` (`$guarded = []`), `SiteSetting::current(): self` = `firstOrCreate([])` — y hệt `PromotionSetting`.
- **Địa chỉ KHÔNG nằm ở đây** — đọc từ `ServiceLocation::open()->ordered()` (name + area).
- Helper trên model: `zaloUrl(int $n): ?string` = `zalo{n}_url ?: ("https://zalo.me/".$zalo{n}_phone)` khi có phone.

---

## 4. Hợp đồng Backend

### 4.1 Routes (`routes/web.php`)

```
// Admin (trong nhóm middleware 'admin' prefix 'admin' name 'admin.')
Route::get   ('/faqs',            [Admin\FaqController::class, 'index'])  ->name('faqs');
Route::post  ('/faqs',            [Admin\FaqController::class, 'store'])  ->name('faqs.store')   ->middleware('throttle:30,1');
Route::put   ('/faqs/{faq}',      [Admin\FaqController::class, 'update']) ->name('faqs.update')  ->middleware('throttle:30,1');
Route::delete('/faqs/{faq}',      [Admin\FaqController::class, 'destroy'])->name('faqs.destroy');

Route::get   ('/settings',        [Admin\SiteSettingController::class, 'edit'])  ->name('settings');
Route::put   ('/settings',        [Admin\SiteSettingController::class, 'update'])->name('settings.update')->middleware('throttle:30,1');
```
Không thêm route shop mới: FAQ đi kèm props trang chủ; contact info là shared prop.

### 4.2 `Admin\FaqController` (theo `CategoryController`)

- `index`: `Faq::ordered()->get()` → `Inertia::render('Admin/Faqs', ['faqs' => ...])`.
- `store`: validate `question` (required, ≤255), `answer` (required string), `sort_order` (nullable int ≥0), `is_active` (boolean); tạo.
- `update`: như store.
- `destroy`: xoá thẳng (FAQ không có quan hệ ràng buộc).

### 4.3 `Admin\SiteSettingController` (theo `PromotionController` — update-only)

- `edit`: `Inertia::render('Admin/SiteSettings', ['settings' => SiteSetting::current(), 'locations' => ServiceLocation::open()->ordered()->get(['name','area'])])` (locations chỉ để hiển thị "địa chỉ lấy từ Điểm cắm trại").
- `update`: validate các field (`hotline_*` nullable string/regex SĐT VN, `*_url` nullable url, labels nullable string); `SiteSetting::current()->update($data)`.

### 4.4 Shared prop `site` (`HandleInertiaRequests@share`)

Thêm khối lazy (đọc DB mỗi request nhưng 1 row, rẻ; có thể cache sau):
```
'site' => fn () => $this->sharedSite(),   // { hotline_primary, hotline_secondary,
                                          //   zalo_1:{label,url}, zalo_2:{label,url},
                                          //   facebook_url, tiktok_url, working_hours,
                                          //   addresses:[{name,area}] }  ← addresses từ ServiceLocation::open()
```
Footer + `ZaloContactStrip` đọc `usePage().props.site`. `zalo_*.url` đã resolve sẵn (áp `zalo.me/<phone>` khi trống). `addresses` = open service locations.

### 4.5 Home props (`Shop\ProductController@home`)

Thêm `'faqs' => Faq::active()->ordered()->get(['id','question','answer'])`. (Contact info đã có qua shared `site` — không cần thêm.)

---

## 5. Hợp đồng Frontend

### 5.1 FAQ — section home (accordion) + link footer

- Component `Components/site/FaqSection.tsx`: nhận `faqs: {id,question,answer}[]`; render accordion (dùng `<details>`/`<summary>` như khối combo trong giỏ, hoặc state mở/đóng). Tiêu đề khối "CÂU HỎI THƯỜNG GẶP" tông `campfire` như các section khác. Ẩn nếu `faqs` rỗng.
- Vị trí trong `Welcome.tsx`: sau "Thuê đồ trong 3 bước", trước CTA cuối (mạch: hiểu quy trình → giải đáp thắc mắc → CTA).
- `id="faq"` để footer "Câu hỏi thường gặp" trỏ `/#faq`.

### 5.2 Footer viết lại (`Footer.tsx`) — đọc `props.site` + `props.site.addresses`

| Cột | Nội dung |
|---|---|
| Brand | logo + tagline (giữ) |
| Thiết bị | `/thiet-bi`, `/combos` (link thật; bỏ 4 span trùng `/thiet-bi` → thay bằng Thiết bị, Combo, và giữ vài danh mục trỏ `/thiet-bi?cat=`) |
| Hỗ trợ | **Tra cứu đơn** `/tra-cuu` (đã có), **Câu hỏi thường gặp** `/#faq` (kích hoạt link chết), Đăng nhập/Tài khoản |
| Liên hệ | 2 SĐT `tel:` (0976544370, 0373655008) + `working_hours`; **địa chỉ**: lặp `site.addresses` → "Vinh – Nghệ An", "Hà Nội – …" |
| Social (mới) | Hàng icon Facebook / TikTok / Zalo — chỉ hiện icon có URL; Zalo dùng `site.zalo_1.url` |

- SĐT: `<a href="tel:0976544370">`. Social: `<a target="_blank" rel="noreferrer">`.
- Bỏ hoàn toàn số/địa chỉ hardcode cũ.

### 5.3 Dải Zalo dưới hero (`Components/site/ZaloContactStrip.tsx`)

- Nhận `zalo1`, `zalo2` từ `props.site`. Render 2 card ngang: icon Zalo + label + SĐT + nút "Liên hệ qua Zalo" → mở `url` tab mới. Ẩn cả dải nếu cả 2 trống.
- Vị trí `Welcome.tsx`: **ngay sau `</HeroSlideshow>`** (đúng "dưới chỗ Xem thiết bị / Tra cứu đơn"), trước section "Một bộ đồ, đi khắp địa hình".
- Style: dải bo góc tông be/đất, nhấn màu Zalo (xanh dương) ở nút để nhận diện, vẫn hoà layout Naturehike.

### 5.4 Admin pages

- `Pages/Admin/Faqs.tsx`: bảng (câu hỏi, trạng thái, thứ tự) + modal create/edit (question, answer textarea, sort_order, toggle active) + xoá — khuôn `Categories.tsx`.
- `Pages/Admin/SiteSettings.tsx`: form 1 trang (2 hotline, 2 khối Zalo label/phone/url, FB, TikTok, working_hours) + ghi chú "Địa chỉ hiển thị lấy từ mục Điểm cắm trại" — khuôn `Promotion.tsx`.
- `AdminLayout.tsx` `NAV`: thêm **"FAQ"** (icon dấu hỏi) và **"Cài đặt shop"** (icon bánh răng), đặt cuối nhóm.

---

## 6. Kế hoạch seeding

### 6.1 `FaqSeeder` — nội dung suy từ site thật (không bịa)

Nguồn: hero/CTA/3-bước/CLAUDE.md. ~10 mục, `sort_order` tăng dần:

1. **Thuê đồ ở BỐP CAMPING như thế nào?** — Chọn thiết bị + ngày nhận/trả, hệ thống hiện món còn trống; gửi tên + SĐT; tụi mình gọi xác nhận trong 15–30 phút; giao tận nơi, trả tiền khi nhận.
2. **Có phải trả tiền trước không?** — Không. Thanh toán COD — trả tiền thuê + cọc khi nhận đồ.
3. **Tiền cọc có được hoàn không?** — Có, hoàn đầy đủ khi trả đồ đúng hẹn và nguyên vẹn.
4. **Thuê theo ngày tính thế nào?** — Theo số ngày giữa ngày nhận và ngày trả; hệ thống kiểm tra trùng lịch + tồn kho theo khoảng ngày.
5. **Giao nhận ở đâu?** — Nội thành Vinh (Nghệ An) và Hà Nội. Miễn phí giao nội thành cho đơn từ 300.000đ.
6. **Đăng nhập/đặt đơn có cần tài khoản không?** — Chỉ cần SĐT + tên (+ email nếu muốn nhận ưu đãi). Lần đầu xác thực bằng mã OTP 6 số gửi qua email; các lần sau vào thẳng.
7. **Combo là gì, có lợi gì?** — Bộ thiết bị gói sẵn (lều + bàn + ghế…), giá rẻ hơn thuê lẻ; xem ở mục Combo.
8. **Có ưu đãi/voucher không?** — Có voucher, mã giới thiệu, và ưu đãi cho đơn đầu khi thêm email.
9. **Tra cứu đơn đã đặt bằng cách nào?** — Vào "Tra cứu đơn", nhập mã đơn + SĐT.
10. **Sau khi trả đồ có đánh giá được không?** — Có, tụi mình gửi link đánh giá sau chuyến đi; đánh giá được duyệt trước khi hiển thị.

### 6.2 `SiteSettingSeeder` — giá trị mặc định từ yêu cầu

```
hotline_primary   = '0976544370'
hotline_secondary = '0373655008'
zalo1_label='Tư vấn & đặt đồ'  zalo1_phone='0976544370'  zalo1_url=null (→ zalo.me/0976544370)
zalo2_label='Hỗ trợ thêm'      zalo2_phone='0373655008'  zalo2_url=null (→ zalo.me/0373655008)
facebook_url=null  tiktok_url=null   -- chờ chủ shop điền
working_hours='8:00 – 21:00 hằng ngày'
```
Đăng ký cả 2 seeder trong `DatabaseSeeder`. Dùng `updateOrCreate`/`firstOrCreate` để idempotent (chạy lại `migrate:fresh --seed` không lỗi).

---

## 7. Rollout & tương thích

- 3 migration mới (`2026_07_07_*`): `create_faqs_table`, `create_site_settings_table`. Tương thích SQLite (test) + MySQL 8 (dev/prod), không đụng bảng cũ.
- Shared prop `site` thêm 1 truy vấn nhỏ/req (1 row settings + open locations). Rẻ; nếu cần tối ưu về sau → cache 60s. Không đưa vào phần này để tránh premature optimization.
- Không có breaking change: footer đổi nội dung nhưng vẫn cùng layout; home thêm 2 section (ẩn khi rỗng).

## 8. Test (viết trước khi code)

- `FaqSeeder`/model: `Faq::active()->ordered()` đúng thứ tự, ẩn `is_active=false`.
- `Admin\FaqController`: non-admin bị chặn; store/update/destroy; validate rỗng.
- `Admin\SiteSettingController`: update singleton; validate URL sai; non-admin chặn.
- Shared `site`: `zalo_*.url` fallback `zalo.me/<phone>` khi url trống; `addresses` = open locations.
- Home: có prop `faqs`; render section khi có FAQ, ẩn khi rỗng.
- Feature: footer render 2 `tel:` + social ẩn khi URL trống.

## 9. Beads (đề xuất — tạo khi bắt đầu impl)

| # | Việc | Phụ thuộc |
|---|---|---|
| B1 | `faqs` migration + model + `FaqSeeder` | — |
| B2 | `Admin\FaqController` + routes + `Pages/Admin/Faqs.tsx` + nav | B1 |
| B3 | `FaqSection` + gắn vào Home + prop `faqs` + link footer `/#faq` | B1 |
| B4 | `site_settings` migration + model + `SiteSettingSeeder` | — |
| B5 | `Admin\SiteSettingController` + routes + `Pages/Admin/SiteSettings.tsx` + nav | B4 |
| B6 | Shared prop `site` (+ ServiceLocation addresses) | B4 |
| B7 | Footer viết lại (links thật, 2 SĐT `tel:`, social, địa chỉ từ locations) | B6 |
| B8 | `ZaloContactStrip` dưới hero | B6 |

Gợi ý gộp thành 2 nhánh: `feature/home-faq` (B1–B3) và `feature/site-contact` (B4–B8).

## 10. Cần chủ shop xác nhận / cung cấp

1. **Duyệt QĐ-2** (admin quản lý contact info qua `SiteSetting`) thay vì hardcode — đây là hướng khuyến nghị.
2. **URL Facebook & TikTok** của shop (để seed; nếu chưa có → để trống, admin điền sau, icon tự ẩn).
3. **Zalo**: dùng mặc định `zalo.me/0976544370` và `zalo.me/0373655008` được chứ, hay có link Zalo vanity/OA riêng? Nhãn 2 tài khoản Zalo ("Tư vấn & đặt đồ" / "Hỗ trợ thêm") có ổn không.
4. **Nội dung FAQ seed** (mục 6.1) — đọc lướt xem có mục nào sai thực tế shop (vd phí ship, khung giờ) để sửa trước khi seed.
