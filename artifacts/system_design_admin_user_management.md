# System Design — Màn hình Quản lý Người dùng (Admin)

- **Ngày:** 2026-06-22
- **Tác giả:** Principal Architect (/architect)
- **Trạng thái:** Draft — chờ duyệt trước khi implement
- **Phạm vi:** Thêm màn hình admin để quản lý 2 nhóm người dùng: **Khách hàng** (passwordless) và **Tài khoản quản trị** (admin/staff).
- **Tech alignment:** Laravel 12 + Inertia + React/TS + Tailwind + shadcn/ui (theo `tech-strategy.md`). Monolith, không REST API riêng.

---

## 1. Bối cảnh & Mục tiêu

BopCamping là shop cho thuê đồ camping (1 shop). Hiện admin quản lý được **đơn / sản phẩm / danh mục** nhưng chưa có chỗ nhìn & quản lý **người dùng**. Hai nhóm khác biệt rõ rệt:

| Nhóm | Đăng nhập | Tạo bởi | Nhu cầu quản lý |
|------|-----------|---------|-----------------|
| **Khách hàng** | SĐT + tên (passwordless, `password = null`) | Tự đăng ký khi đặt đơn | Chủ yếu **xem**: danh sách, tìm kiếm, lịch sử đơn, tổng chi tiêu, liên hệ. Hiếm khi sửa. |
| **Tài khoản quản trị** | SĐT + mật khẩu (`is_admin = true`) | Admin tạo thủ công | **CRUD**: tạo tài khoản nhân viên, đổi quyền, đặt lại mật khẩu, vô hiệu hoá. Nhạy cảm (đặc quyền). |

**Mục tiêu:** một màn hình giúp chủ shop (a) hiểu tệp khách hàng & lịch sử thuê, (b) quản trị tài khoản staff an toàn.

**Non-goals (MVP):** không khoá/ban khách (xem §4 D5), không nhắn tin/marketing, không phân quyền chi tiết theo role (chỉ boolean `is_admin`).

---

## 2. Hiện trạng dữ liệu (đã verify trong code)

```
users:  id, name, phone(unique, nullable, 20), is_admin(bool default false),
        email(nullable), email_verified_at, password(nullable, hashed),
        remember_token, timestamps        # KHÔNG có soft-delete, KHÔNG có cờ block
orders: id, user_id(nullable FK → users, nullOnDelete), code(unique),
        customer_name, customer_phone(20), customer_address(nullable),
        start_date, end_date, total_price, deposit_total,
        status(pending|confirmed|renting|returned|cancelled),
        payment_method(cod), note, timestamps
```

**Sự thật cốt lõi (định hình toàn bộ thiết kế):**
- Checkout gán `user_id = Auth::id()` (`Shop/OrderController:78`). Khách **đang đăng nhập** → đơn có `user_id`. Khách **vãng lai** (không đăng nhập) → `user_id = null`, chỉ còn `customer_phone`/`customer_name`.
- `phone` là **unique trên `users`** → là *natural key* thực tế của khách hàng.
- `Order` đã có `belongsTo(User)`. `User` **chưa** có `orders()` (cần thêm).
- `password` đã `casts → hashed`; `password`/`remember_token` đã ẩn (`$hidden`). Prop Inertia toàn cục chỉ chia sẻ `{id,name,phone,email}` (fix H1 trước đó) — **không** đụng tới khi thêm màn này.

---

## 3. Phạm vi (In / Out)

**In scope (MVP):**
1. Màn `Admin/Users` 1 trang, **2 tab**: `Khách hàng` | `Quản trị viên`.
2. Tab Khách hàng: list + tìm kiếm (tên/SĐT) + phân trang; xem chi tiết (thông tin + lịch sử đơn + thống kê); xoá (có guard).
3. Tab Quản trị viên: list; **tạo** tài khoản admin; **sửa** (tên/SĐT, đặt lại mật khẩu); **đổi quyền** `is_admin`; **xoá/vô hiệu** (có guard).
4. Thêm mục nav "Người dùng" vào `AdminLayout`.

**Out of scope (ghi nhận, làm sau):**
- Khoá/ban khách hàng (D5).
- Bảng audit log riêng (MVP dùng Laravel Log).
- Phân quyền nhiều cấp (roles/permissions).
- Gộp/merge tài khoản trùng người.

---

## 4. Quyết định kiến trúc (ADR-style + trade-off)

### D1 — Một màn 2 tab, KHÔNG phải 2 màn riêng ✅
Tái dùng đúng pattern `Orders.tsx` (tab `Đơn thuê | Kho`). Một nav entry, một controller.

| Tiêu chí | 1 màn 2 tab ✅ | 2 màn riêng | Trộn 1 list |
|---|---|---|---|
| Nhất quán UX hiện có | Cao (giống Orders) | TB | Thấp |
| Tách bạch 2 nhóm | Rõ | Rõ nhất | Kém (lẫn quyền) |
| Chi phí code | Thấp | Cao hơn (2 route set) | Thấp nhưng rối |
**Chọn:** 1 màn 2 tab.

### D2 — Liên kết Khách hàng ↔ Đơn: list theo `users`, chi tiết đối soát theo `phone` ✅ (quyết định trọng tâm)
Vì đơn vãng lai không có `user_id`, không thể chỉ dựa `user_id`.

| Phương án | Ưu | Nhược | Kết luận |
|---|---|---|---|
| (a) Chỉ `user_id` | Nhanh, indexed | **Bỏ sót** đơn vãng lai cùng SĐT | Dùng cho **list stats** (đủ cho overview) |
| (b) Gộp theo `phone` toàn bộ | Đủ đơn | Trộn account + guest, query nặng | Không |
| (c) **List = rows `users`; Chi tiết = đơn match `user_id = id` OR `customer_phone = phone`** | List sạch + chi tiết chính xác | 2 nguồn đếm khác nhau (phải nêu rõ) | ✅ **Chọn** |

**Hệ quả:** Số liệu ở **list** (số đơn, tổng chi) tính theo `user_id` (nhanh) và **ghi chú rõ** "đơn đã liên kết tài khoản"; trang **chi tiết** hiển thị đầy đủ đơn đối soát thêm theo SĐT (gồm đơn vãng lai trước khi khách đăng nhập). Đơn vãng lai mà SĐT chưa từng tạo account → chỉ thấy ở màn Đơn (Orders), không thuộc màn này.

### D3 — Thống kê per-khách bằng eager aggregate (chống N+1) ✅
`withCount(['orders'])` + `withSum(orders chưa huỷ, 'total_price')` + `max(created_at)`. KHÔNG lặp query trong vòng map. (Cùng tinh thần với bead phân trang `bopcamping-7y7`.)

### D4 — Bảo vệ thao tác đổi quyền/xoá (SECURITY-CRITICAL) ✅
Toggle `is_admin` = bề mặt leo thang đặc quyền (CWE-269). Bắt buộc các guard:
- **Không tự hạ quyền chính mình** (tránh tự khoá ra ngoài).
- **Không hạ/xoá admin cuối cùng** (`is_admin=true` count > 1) — tránh khoá toàn bộ hệ thống (availability).
- Chỉ admin (middleware `admin`/EnsureAdmin — đã có).
- **Ghi log** mọi lần đổi quyền/xoá: ai, thời điểm, đối tượng (audit — A09).
- Modal xác nhận cho thao tác nhạy cảm; `throttle` cho endpoint mutation.

### D5 — Khoá/ban khách hàng: **DEFER** (YAGNI) ✅
Khách passwordless (SĐT+tên) → khoá yếu (đổi SĐT là vượt). Chưa có vector lạm dụng nêu ra. Nếu cần sau: thêm cột `blocked_at timestamp nullable`, chặn ở `GuestAuthController` + checkout. Ghi nhận, không làm MVP.

### D6 — Xoá: khách = hard delete (có guard); admin = ưu tiên vô hiệu ✅
`orders.user_id` đã `nullOnDelete` + đơn giữ `customer_name/phone` (denormalized) → xoá khách **không mất lịch sử đơn**.

| | Hard delete | Soft delete (`deleted_at`) |
|---|---|---|
| Đơn giản | ✅ | Phải lọc ở login/list |
| Audit/khôi phục | Không | Có |
| MVP fit | ✅ khách | (cân nhắc cho admin) |
**Chọn:** khách **hard delete** (có confirm + guard); tài khoản admin nên **hạ quyền/vô hiệu** thay vì xoá để giữ dấu vết (xoá vẫn cho phép nhưng chặn self/last-admin).

### D7 — Phân trang + tìm kiếm server-side ✅
`paginate(50)` + search `where name LIKE` / `phone LIKE` (binding param). Không nạp toàn bảng.

### D8 — Lộ PII có chủ đích, map field tường minh ✅
List/chi tiết lộ tên/SĐT/email/địa chỉ **cho admin** (hợp pháp). Controller **map field tường minh** (giống Product/CategoryController), không `->toArray()` cả model, không bao giờ trả `password`/`remember_token`. KHÔNG thêm PII vào shared props toàn cục.

---

## 5. Kiến trúc thông tin & UX

**Nav:** thêm item `Người dùng` (icon 2 người) vào `AdminLayout` NAV → `route('admin.users')`.

**Tab "Khách hàng"** — bảng:
| Cột | Nguồn |
|---|---|
| Tên | `users.name` |
| SĐT (font-mono) | `users.phone` |
| Email | `users.email` (— nếu null) |
| Số đơn | `orders_count` (đã liên kết) |
| Tổng chi (money) | `orders_sum_total_price` (đơn ≠ cancelled) |
| Đơn gần nhất | `max(orders.created_at)` |
| Tham gia | `users.created_at` |
| Thao tác | Xem chi tiết · Xoá |

- Thanh tìm kiếm (debounce) theo tên/SĐT; phân trang dưới bảng.
- **Chi tiết** (row mở rộng hoặc drawer): thông tin liên hệ + **lịch sử đơn đối soát theo D2** (mã đơn, ngày thuê, tổng, trạng thái — tái dùng `STATUS_LABEL/STATUS_STYLE` của Orders, nên **tách ra `lib/orderStatus`** để DRY giống `ProductStatusPill`).

**Tab "Quản trị viên"** — bảng: Tên · SĐT · Quyền (badge) · Tạo lúc · Thao tác (Sửa · Đổi quyền · Xoá).
- Nút **"+ Thêm quản trị viên"** → modal (tên, SĐT, mật khẩu).
- Modal Sửa: tên, SĐT, "đặt lại mật khẩu" (để trống = giữ nguyên).
- Đổi quyền & Xoá: modal confirm; disable nút nếu vi phạm guard (self / last-admin) + hiển thị lý do.

Tái dùng sẵn có: `money()` (`lib/format`), pattern toast + confirm modal + bảng của `Products/Categories.tsx`, badge.

---

## 6. Thiết kế kỹ thuật (contracts — không phải code)

### 6.1 Routes (trong group `middleware('admin')->prefix('admin')->name('admin.')`)
```
GET    /admin/users                 → UserController@index      name=admin.users
POST   /admin/users                 → UserController@store      name=admin.users.store      # tạo admin
PUT    /admin/users/{user}          → UserController@update     name=admin.users.update     # sửa tên/sđt/reset pass
PATCH  /admin/users/{user}/role     → UserController@updateRole name=admin.users.role       # toggle is_admin (guarded)
DELETE /admin/users/{user}          → UserController@destroy    name=admin.users.destroy
```
Mutation routes thêm `throttle` (vd `throttle:30,1`). Upload ảnh khi SỬA: lưu ý dùng POST + `_method` spoofing nếu sau này có avatar (xem fix C1 cũ) — MVP không có avatar nên không áp dụng.

### 6.2 UserController — trách nhiệm
- `index(Request)`: đọc `?tab`, `?q`, `?page`. Query khách hàng (`where is_admin=false`) + `withCount/withSum` (D3) + search (D7) + `paginate(50)`; query admin (`where is_admin=true`). `Inertia::render('Admin/Users', {...})` với field map tường minh (D8).
- `store(Request)`: validate (xem 6.4) → tạo user `is_admin=true`, `password=Hash` (cast `hashed` tự lo).
- `update(Request, User)`: validate; chỉ set `password` khi có nhập.
- `updateRole(Request, User)`: **enforce guard D4** (không self, không last-admin) → đổi `is_admin` → **Log::info** audit. Nếu vi phạm → `back()->withErrors`.
- `destroy(User)`: **enforce guard D4**; hard delete (D6); Log audit. `orders.user_id` tự null.

### 6.3 Thay đổi Model
- `User::orders(): HasMany` → `hasMany(Order::class)`.
- Query đối soát đơn theo D2 (chi tiết): scope/method
  `relatedOrders()` ≈ `Order::where('user_id', $u->id)->orWhere('customer_phone', $u->phone)` — đặt ở `User` model hoặc query trong controller (single source, tránh lặp).
- Không thêm cột mới ở MVP (D5 defer, D6 hard delete).

### 6.4 Validation contracts
```
store (admin mới):
  name      required|string|min:2|max:100
  phone     required|string|regex:/^0[0-9]{8,10}$/|unique:users,phone
  password  required|string|min:6           # CHỐT: tối thiểu 6 (quyết định 2026-06-22)
  # is_admin = true (ép cứng, không nhận từ client)

update:
  name      required|string|min:2|max:100
  phone     required|string|regex:/^0[0-9]{8,10}$/|unique:users,phone,{id}
  password  nullable|string|min:6           # có thì reset (min 6), không thì giữ

updateRole:
  is_admin  required|boolean
  # + guard: target ≠ current user; nếu hạ quyền → còn ≥1 admin khác

destroy:
  # guard: target ≠ current user; nếu target is_admin → còn ≥1 admin khác
```

### 6.5 Props gửi sang React (shape)
```ts
// Admin/Users.tsx
{
  tab: 'customers' | 'admins',
  filters: { q: string },
  customers: Paginator<{
    id; name; phone; email|null;
    orders_count: number;
    total_spent: number;       // money đơn ≠ cancelled
    last_order_at: string|null;
    created_at: string;
  }>,
  admins: Array<{ id; name; phone; created_at }>,
  stats?: { total_customers; total_admins },
  // chi tiết khách (khi mở): orders[] đối soát theo D2 — có thể lazy qua partial reload
}
```

---

## 7. Bảo mật (OWASP 2021 / CWE)

| Hạng mục | Rủi ro | Biện pháp |
|---|---|---|
| A01 Broken Access Control | Truy cập màn / sửa quyền trái phép | `EnsureAdmin` (đã có); guard self/last-admin (CWE-269, CWE-285) |
| A04 Insecure Design | Tự khoá / khoá toàn hệ thống | Guard D4 + modal confirm + disable nút có lý do |
| A02 Crypto | Lộ mật khẩu | `password` cast `hashed`, ẩn `$hidden`, không select/trả về |
| A03 Injection | SQLi qua search | Eloquent binding (LIKE param), không nối chuỗi |
| A09 Logging | Không dấu vết đổi quyền | `Log::info` mọi updateRole/destroy (ai/khi/đối tượng) |
| PII (CWE-200) | Lộ SĐT/email/địa chỉ | Map field tường minh; chỉ admin; không vào shared props |
| Rate limit (CWE-307) | Brute mutation | `throttle` trên store/update/role/destroy |

---

## 8. Hiệu năng
- Chống N+1: `withCount` + `withSum` (D3); index `phone` đã unique; `orders(user_id)` nên đảm bảo có index FK (Laravel `constrained()` tạo FK; cân nhắc index `orders.customer_phone` cho đối soát D2 — **đề xuất thêm index** `orders.customer_phone` nếu chưa có).
- Phân trang `paginate(50)` (D7) — không nạp toàn bảng.
- Chi tiết đơn theo SĐT: query khi mở (lazy) thay vì nạp sẵn cho mọi row.

---

## 9. Rủi ro & failure modes
| Rủi ro | Ảnh hưởng | Giảm thiểu |
|---|---|---|
| Khoá admin cuối / tự hạ quyền | Mất quyền quản trị | Guard cứng ở server + disable nút (không chỉ FE) |
| Lệch số liệu list vs chi tiết (D2) | Nhầm lẫn | Ghi nhãn rõ "đơn đã liên kết" ở list |
| Match SĐT sai người | Hiển thị nhầm đơn | `phone` unique trên users → 1 SĐT ↔ 1 account; chấp nhận |
| Xoá khách làm hỏng đơn | Mất lịch sử | `nullOnDelete` + denormalized fields giữ đơn (đã verify) |
| Thêm cột/đổi enum sau | Migration prod (MySQL) | Giữ migration tương thích SQLite↔MySQL |

---

## 10. Acceptance Criteria (cho bead implement)
- [ ] Nav "Người dùng" hiển thị; route admin-only (guest/non-admin → /admin/login). *(liên quan bead `bopcamping-8kk`)*
- [ ] Tab Khách: list có search + phân trang; cột số đơn/tổng chi/đơn gần nhất đúng; không N+1 (kiểm query log).
- [ ] Chi tiết khách: hiện đơn đối soát theo `user_id` OR `customer_phone` (D2), gồm đơn vãng lai cùng SĐT.
- [ ] Tab Admin: tạo/sửa/đổi quyền/xoá hoạt động; mật khẩu được hash; reset pass chỉ khi nhập.
- [ ] **Guard:** không tự hạ quyền; không hạ/xoá admin cuối → trả lỗi rõ; nút bị disable kèm lý do.
- [ ] Mọi updateRole/destroy ghi Log audit.
- [ ] Quality gates: `php artisan test` (có test guard + access), `pint --test`, `tsc`, `npm run build` đều xanh.
- [ ] Test: phân quyền (chặn non-admin), guard last-admin/self, đối soát đơn theo SĐT.

---

## 11. Quyết định đã chốt (2026-06-22, chủ shop)
1. **Khoá khách**: ❌ **DEFER** — không làm MVP (D5). Thêm sau bằng cột `blocked_at` nếu cần.
2. **Audit**: ✅ **Laravel Log** (`Log::info` ai/khi/đối tượng) cho mọi updateRole/destroy. Chưa làm bảng `audit_logs` riêng.
3. **Sửa khách**: ✅ **Chỉ xem** — khách self-service; admin chỉ xem + xoá. Form sửa CHỈ áp dụng tài khoản admin (tab Quản trị viên).
4. **Mật khẩu admin**: ✅ **tối thiểu 6 ký tự** (`min:6` ở store/update).

---

## 12. Kế hoạch triển khai & Handoff
**Thứ tự (1 epic, các task nhỏ):**
1. Migration phụ (chỉ nếu chốt thêm index `orders.customer_phone`).
2. Model: `User::orders()` + `relatedOrders()` scope.
3. `UserController` (index/store/update/updateRole/destroy) + routes + throttle.
4. `Admin/Users.tsx` (2 tab, search, modal, confirm, chi tiết) + nav + tách `lib/orderStatus`.
5. Tests (access control + guards + đối soát SĐT) + chạy quality gates.

**Handoff:**
- → `/swarm-execute` hoặc `/builder` sau khi duyệt design này.
- → `/security-auditor` review phần đổi quyền (D4) trước merge.
- **Artifact này:** `artifacts/system_design_admin_user_management.md` — nguồn chân lý cho việc implement.
- **Beads:** tạo epic `Admin: quản lý người dùng` + tham chiếu artifact này (xem lệnh ở phản hồi).
