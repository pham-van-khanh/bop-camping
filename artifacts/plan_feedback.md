# Plan — Epic 2: Chức năng góp ý

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (inline). Checkbox theo task.

**Goal:** Khách gửi góp ý qua widget nổi → mail báo admin; admin đọc + phản hồi qua email (template cố định chào theo tên, nội dung admin soạn) gửi từ **mail cấu hình trong `.env`**.

**Architecture:** Bảng `feedbacks`; `Shop/FeedbackController@store` (throttle); 2 Mailable **ShouldQueue class có tên** (anonymous class không queue được): `FeedbackReceivedMail` → admin, `FeedbackReplyMail` → khách qua **mailer phản hồi cấu hình env** (`MAIL_REPLY_MAILER` + `MAIL_REPLY_FROM_*`, fallback mailer/from mặc định — user đổi mail chỉ sửa env). Admin `/admin/gop-y` + badge sidebar.

**Spec gốc:** `artifacts/design_spec_product_page_v2_feedback_seo.md` (Epic 2). Branch `feature/feedback` từ `feat/scaffold-laravel`.

## Global Constraints

- Gates: `php artisan test` · `pint --test` · `tsc --noEmit` · `npm run build`. Migration SQLite+MySQL. Mail test bằng `Mail::fake()`.

---

### Task 1: DB + gửi góp ý (khách) + mail admin

**Files:** migration `create_feedbacks_table` · `app/Models/Feedback.php` · `app/Http/Controllers/Shop/FeedbackController.php` (`store`) · route `POST /gop-y` (throttle 5,1) · `app/Mail/FeedbackReceivedMail.php` + `resources/views/emails/feedback_received.blade.php` · config/mail.php (`admin_address`, `reply_mailer`, `reply_from`) + `.env.example` · test `tests/Feature/FeedbackTest.php`

- Bảng: `id, name (string), phone (string 20 nullable), email (nullable), content (text), status enum(new,replied) default new, reply_content (text nullable), replied_at (timestamp nullable), timestamps`.
- Validate: `name required|min:2|max:100`, `content required|min:5|max:3000`, `phone nullable|max:20`, `email nullable|email|max:150`, **`phone required_without:email`** (+ message tiếng Việt "Cần ít nhất SĐT hoặc email...").
- Mail admin: tới `config('mail.admin_address')` nếu đặt, fallback `User::adminNotifyEmails()`; nội dung: thông tin khách + góp ý + link `/admin/gop-y`.
- Test: lưu + queue mail; thiếu cả phone+email → 422; throttle.

### Task 2: Widget nổi + modal form (khách)

**Files:** `resources/js/Components/site/FeedbackWidget.tsx` · mount trong `SiteLayout.tsx`

- Nút nổi `fixed bottom-5 right-5` (icon 💬 "Góp ý", tông grass, z-index dưới modal) → mở modal form: Tên*, SĐT, Email, Nội dung* + ghi chú "cần ít nhất SĐT hoặc email". Gửi bằng `router.post('/gop-y')` (Inertia form) → thành công hiện màn cảm ơn trong modal.
- Không chiếm chỗ mobile: nút tròn 48px, modal max-w 480, mobile full-width padding.

### Task 3: Admin /admin/gop-y + mail phản hồi

**Files:** `app/Http/Controllers/Admin/FeedbackController.php` (`index`, `reply`) · routes admin (`GET /admin/gop-y`, `PATCH /admin/gop-y/{feedback}`) · `app/Mail/FeedbackReplyMail.php` + `resources/views/emails/feedback_reply.blade.php` · `resources/js/Pages/Admin/Feedbacks.tsx` · sidebar AdminLayout + badge `pending_feedback` (HandleInertiaRequests) · test bổ sung vào `FeedbackTest`

- Index: list phân trang 30, lọc `?status=new|replied`, panel chi tiết expand.
- Reply: validate `reply_content required|min:5|max:5000`; có email → `Mail::mailer(config('mail.reply_mailer') ?: config('mail.default'))->to($f->email)->send(new FeedbackReplyMail($f))` + set `replied`; chỉ SĐT → nút mail disable ở FE, action `mark` (đánh dấu đã phản hồi, lưu ghi chú, không gửi mail).
- Template mail phản hồi (cố định): "Chào {name}," + đoạn cảm ơn + `{reply_content}` (admin soạn trong form, textarea đổ sẵn template gợi ý) + chữ ký BỐP CAMPING; from = `reply_from` env fallback from mặc định.
- Test: reply gửi mail đúng mailer + status replied; guest chặn; mark không cần email.

### Task 4: Gates + stg

- Full gates → preview widget desktop/mobile + admin → merge `develop` push → user test → merge `feat/scaffold-laravel`.

## Self-review

Spec coverage: widget nổi ✓ · validate ít-nhất-1 ✓ · mail admin env ✓ · admin đọc/phản hồi ✓ · template cố định + nội dung admin sửa ✓ · mail phản hồi từ env (mailer + from riêng, fallback) ✓ · chỉ-SĐT flow ✓ · throttle ✓.
