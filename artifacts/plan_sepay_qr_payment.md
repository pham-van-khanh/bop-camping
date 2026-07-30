# Plan — Thanh toán QR qua SePay

**Ngày**: 2026-07-28 · **ADR**: [adr_sepay_qr_payment.md](adr_sepay_qr_payment.md) ·
**PRD**: [prd_sepay_qr_payment.md](prd_sepay_qr_payment.md)

**Ước lượng**: ~12–14 ngày làm việc. **Branch**: `feature/sepay-qr-payment`
(tạo từ `feat/scaffold-laravel` theo workflow trong CLAUDE.md).

## Nguyên tắc xuyên suốt

1. **Một nguồn sự thật cho trạng thái thanh toán.** Webhook, job đối soát, và admin bấm
   tay đều gọi `PaymentMatcher` — không có đường ghi thứ hai. Cùng tinh thần
   `AvailabilityService` là nguồn duy nhất cho tồn kho.
2. **TDD**: test trước, mỗi task tự có test. Không có task "viết test sau".
3. **Idempotency ở tầng DB** (UNIQUE constraint), không chỉ tầng app.
4. Migration chạy được trên **cả sqlite và MySQL** — dùng `string`, không enum DB.
5. Không log secret, không log full payload chứa thông tin TK khách.

---

## T0 — Dựng tài khoản SePay + xác minh giả định (0.5 ngày, **BLOCKING**)

Phần lớn là việc tay trên dashboard, nhưng **phải làm trước** vì kết quả có thể đổi thiết kế.

- [ ] Đăng ký SePay gói **FREE** (0đ, 50 giao dịch/tháng, có webhook + API).
- [ ] Liên kết TK **Vietcombank**. **Dùng TK riêng chỉ để nhận tiền thuê**, không phải TK
      chính của shop (xem rủi ro credentials ở ADR). Kiểm VCB có hỗ trợ **OAuth** không —
      nếu có, chọn OAuth thay vì nhập credentials internet banking.
- [ ] **Xác minh HMAC-SHA256 có khả dụng ở gói FREE hay không.** Docs không nói theo gói,
      changelog không nhắc HMAC → không được giả định. Nếu **không** có: fallback API Key
      + IP whitelist, và cập nhật ADR mục 6.
- [ ] Cấu hình mẫu mã thanh toán: prefix **`BOP`**, suffix **6 ký tự số**, tại
      `my.sepay.vn` → Công ty → Cấu hình chung → Cấu trúc mã thanh toán.
- [ ] Bật **Test mode**, thử form "Mô phỏng giao dịch" → nắm payload thật.
- [ ] Quét thử QR động bằng app VCB → **xác minh số tiền có bị khoá không cho sửa**.
- [ ] Lấy Secret HMAC + API token → ghi vào `.env` (**không commit**), thêm key rỗng vào
      `.env.example`.

**Acceptance**: nhận được 1 webhook mô phỏng về endpoint tạm (`webhook.site` hoặc tunnel),
đọc được payload thật, và biết chắc HMAC có dùng được hay không.

**Đầu ra cần ghi lại**: payload mẫu thật (để viết test đúng), kết luận HMAC, kết luận khoá số tiền.

---

## T1 — Schema + model (2 ngày)

Phụ thuộc: — (làm song song T0 được)

**Migration 1 — lớp thanh toán trên `orders`:**
```
prepay_choice      string(16) nullable   -- none|rental|deposit|both (null = đơn cũ)
deposit_paid_at    timestamp nullable
rental_paid_at     timestamp nullable
payment_expires_at timestamp nullable
```

**Migration 2 — bảng `order_payments`:**
```
id, order_id FK cascade
kind                  string(16)      -- deposit|rental|both
expected_amount       decimal(14,0)
pay_code              string(16) UNIQUE   -- BOP + 6 số
status                string(16)      -- pending|paid|mismatched|expired
sepay_transaction_id  bigint UNIQUE nullable   ← khoá idempotency
transfer_amount       decimal(14,0) nullable
reference_code        string nullable
bank_gateway          string nullable
transaction_date      timestamp nullable
raw_payload           json nullable
paid_at               timestamp nullable
timestamps
index (order_id, status)
```

**Migration 3 — backfill + bỏ cột `payment_status`:**
- `deposit` → `deposit_paid_at = updated_at`
- `full` → `deposit_paid_at = rental_paid_at = updated_at`
- `unpaid` → để null
- Rồi `dropColumn('payment_status')`. Method `down()` phải phục hồi được cột.

**Model:**
- `Order`: accessor `payment_status` (4 giá trị `unpaid|deposit|rental|full`), accessor
  `prepayAmount` / `codAmount` theo `prepay_choice`, relation `payments()`, hằng
  `PREPAY_CHOICES`, scope cho hàng chờ lệch tiền.
- `OrderPayment`: fillable, casts (`raw_payload` → array, tiền → integer), hằng
  `KINDS` + `STATUSES`, relation `order()`.

**Test:**
- [ ] 4 tổ hợp mốc thời gian → đúng 4 giá trị `payment_status`
- [ ] `prepayAmount` / `codAmount` đúng cho cả 4 `prepay_choice`
- [ ] Backfill: seed đơn cũ 3 trạng thái → migrate → nhãn giữ đúng
- [ ] UNIQUE `sepay_transaction_id` thực sự chặn insert trùng ở tầng DB
- [ ] `extra_fee` **không** nằm trong `prepayAmount` (luôn thu khi nhận đồ)

**Rủi ro**: đây là task duy nhất sửa cột đang dùng. Grep hết chỗ đọc `payment_status`
(controller, Inertia props, `resources/js/types/index.d.ts`, admin filter) trước khi drop cột.

---

## T2 — Config + sinh QR (1 ngày)

Phụ thuộc: T0 (cần số TK, tên bank)

- [ ] `config/sepay.php`: `qr_base_url` (mặc định `https://vietqr.app`), `bank.account_number`,
      `bank.name`, `bank.account_holder`, `pay_code_prefix` (`BOP`), `webhook.secret`,
      `api.token`, `api.base_url`, `payment_ttl_minutes` (60), `qr_template` (`compact`).
      Thông tin bank **không phải secret** (khách phải thấy) → có mặc định để dev chạy ngay.
      Secret HMAC + API token **chỉ** từ env, không có mặc định.
- [ ] `App\Services\SePay\QrCodeBuilder`: `url(OrderPayment $payment): string`.
      URL-encode `des`. Không gọi API, không cần key.
- [ ] `App\Services\SePay\PayCodeGenerator`: sinh `BOP` + 6 số, unique (retry khi trùng).

**Test:**
- [ ] URL chứa đúng `acc`, `bank`, `amount`, `des`, `template`
- [ ] `des` được URL-encode đúng
- [ ] `amount` là số nguyên, không thập phân, không phân cách nghìn
- [ ] `pay_code` khớp regex `^BOP\d{6}$` và unique qua 1000 lần sinh
- [ ] Đổi `qr_base_url` sang `https://qr.sepay.vn` → URL đổi theo (không hardcode)

---

## T3 — Xác thực webhook HMAC-SHA256 (1 ngày)

Phụ thuộc: T0 (biết HMAC có dùng được không)

- [ ] `App\Services\SePay\WebhookVerifier::verify(Request $request): bool`
- [ ] Đọc `X-SePay-Signature` (`sha256={hex}`) + `X-SePay-Timestamp`
- [ ] Chuỗi ký `{timestamp}.{raw_body}`, HMAC-SHA256, so bằng **`hash_equals`**
- [ ] **Raw body qua `$request->getContent()`** — tuyệt đối không `json_encode($request->all())`
- [ ] Timestamp lệch > 5 phút → fail

**Test:**
- [ ] Signature đúng → pass
- [ ] Signature sai → fail
- [ ] Thiếu header → fail
- [ ] Timestamp lệch 6 phút → fail (chống replay)
- [ ] **Body có ký tự Unicode tiếng Việt** (tên người CK) → vẫn pass. Test này chính là
      cái bắt lỗi dùng `json_encode` thay vì raw body — `json_encode` mặc định escape
      Unicode thành `\uXXXX` nên signature sẽ lệch.

---

## T4 — PaymentMatcher: nguồn sự thật duy nhất (2 ngày)

Phụ thuộc: T1

`App\Services\SePay\PaymentMatcher` — **mọi** thay đổi trạng thái thanh toán đi qua đây:
webhook, job đối soát, admin bấm tay.

- [ ] `applySepayTransaction(array $payload): MatchResult` —
      bỏ qua `transferType != 'in'` và `code` rỗng; tìm `order_payments` theo `pay_code`;
      `transfer_amount >= expected` → `paid`; `<` → `mismatched`; set mốc thời gian theo
      `kind` (`both` set cả hai); xoá `payment_expires_at`; dispatch mail.
- [ ] `markPaidManually(Order $order, string $kind, ?string $note): void` — cho admin.
- [ ] Idempotent: `sepay_transaction_id` đã tồn tại → trả về kết quả cũ, **không** ghi lại.
- [ ] Mốc thời gian đã có sẵn (admin bấm trước) → **không ghi đè**, chỉ lưu `order_payments` audit.
- [ ] Bọc trong DB transaction + `lockForUpdate` trên order.

**Test:**
- [ ] CK đủ → `paid` + đúng mốc thời gian theo từng `kind`
- [ ] CK thừa → `paid` (không phải `mismatched`)
- [ ] CK thiếu 1đ → `mismatched`, **không** set mốc thời gian
- [ ] **Cùng payload gọi 3 lần → chỉ 1 record `paid`**, mốc thời gian không đổi
- [ ] `transferType = 'out'` → bỏ qua hoàn toàn
- [ ] `code` rỗng → bỏ qua + log
- [ ] `code` không khớp đơn nào → báo admin, không throw
- [ ] Admin bấm tay rồi webhook tới → mốc thời gian không bị ghi đè
- [ ] Đơn đã `cancelled` mà webhook tới → không tự mở lại đơn, báo admin
- [ ] Chạy đúng trên **đơn con** (parent/child orders)

---

## T5 — Webhook endpoint (1 ngày)

Phụ thuộc: T3, T4

- [ ] `POST /webhook/sepay` trong `routes/web.php`, **ngoài** middleware group `web`
      (không session, không CSRF).
- [ ] `bootstrap/app.php`: `$middleware->validateCsrfTokens(except: ['webhook/sepay'])`.
      Hiện **chưa có** exception nào → đây là lần đầu thêm.
- [ ] `Http\Controllers\Webhook\SePayController`: verify → **dispatch job** → trả
      `{"success": true}` + 200 ngay. Controller mỏng, không làm việc nặng (giới hạn 30s).
- [ ] `Jobs\ProcessSePayWebhook` (ShouldQueue) → gọi `PaymentMatcher`.
- [ ] Lưu raw payload trước khi xử lý để tra cứu sự cố.
- [ ] Rate limit endpoint (chống spam), IP whitelist làm **lớp phụ** — đọc từ config,
      không hardcode (docs ghi danh sách IP có thể mở rộng).

**Test (Feature):**
- [ ] POST đúng signature → 200 + body chính xác `{"success": true}`
- [ ] Signature sai → 401, **không** tạo record nào
- [ ] Không cần CSRF token vẫn qua được
- [ ] Job được dispatch (`Queue::fake()`), controller không tự xử lý
- [ ] Payload mẫu thật lấy từ T0 → parse đúng

---

## T6 — UI checkout: chọn hình thức trả trước (1.5 ngày)

Phụ thuộc: T1

- [ ] `resources/js/Pages/Cart.tsx`: radio 4 lựa chọn, **mặc định `rental`**.
      Mỗi lựa chọn hiện rõ 2 số tiền: "Chuyển trước: X đ" / "Trả khi nhận đồ: Y đ".
- [ ] Ghi rõ phí ngoài giờ (`extra_fee`) luôn thu khi nhận đồ, không nằm trong QR.
- [ ] `Shop\OrderController::store()`: validate `prepay_choice` ∈ 4 giá trị; sau khi tạo
      order (`OrderSplitter::create()`) → tạo `order_payments` + set `payment_expires_at`
      nếu `!= none`; redirect sang trang thanh toán.
- [ ] `prepay_choice = none` → giữ nguyên luồng hiện tại, không sinh QR.
- [ ] Cập nhật `resources/js/types/index.d.ts`.

**Test:**
- [ ] Feature: mỗi `prepay_choice` → `order_payments` đúng `kind` + `expected_amount`
- [ ] Feature: `none` → **không** tạo `order_payments`, `payment_expires_at` null
- [ ] Feature: `prepay_choice` không hợp lệ → 422
- [ ] Feature: đơn cha/con → QR sinh theo **đơn con**
- [ ] Component (Vitest): radio mặc định `rental`; đổi lựa chọn → 2 số tiền cập nhật đúng

---

## T7 — Trang thanh toán QR + poll (1.5 ngày)

Phụ thuộc: T2, T6

- [ ] `GET /don-hang/{code}/thanh-toan` → Inertia page `Shop/Payment.tsx`
- [ ] `GET /don-hang/{code}/tinh-trang` → JSON `{status, paid_at}` cho FE poll
- [ ] Trang gồm: ảnh QR (`template=compact`), khối CK thủ công dự phòng (số TK, chủ TK,
      ngân hàng, **nội dung CK + nút copy**), đồng hồ đếm ngược tới `payment_expires_at`,
      nút "Tôi sẽ chuyển sau".
- [ ] Poll **3 giây** → `paid` thì chuyển sang trang thành công (không cần F5).
      Dừng poll khi hết hạn hoặc rời trang (cleanup interval trong `useEffect`).
- [ ] Trang thành công nói rõ đã trả khoản nào, còn phải trả bao nhiêu khi nhận đồ.
- [ ] **Bảo mật**: trang chỉ truy cập được với mã đơn đúng; **không** để lộ thông tin đơn
      khác. Cân nhắc thêm ràng buộc SĐT như `OrderLookupController` đang làm.

**Test:**
- [ ] Feature: đơn `none` → truy cập trang thanh toán bị 404
- [ ] Feature: endpoint poll trả đúng trạng thái, **không** lộ dữ liệu đơn khác
- [ ] Feature: đơn hết hạn → trang hiện trạng thái hết hạn, không hiện QR
- [ ] Component: đếm ngược hiển thị đúng; poll dừng khi unmount (không leak interval)

---

## T8 — Admin: nhãn gộp, hàng chờ lệch tiền, lịch sử (1 ngày)

Phụ thuộc: T4

- [ ] `Admin/Orders.tsx`: cột **Thanh toán** dùng nhãn gộp 4 trạng thái + badge hình thức
      trả trước.
- [ ] Nút "Đã nhận cọc" / "Đã nhận phí thuê" → gọi `PaymentMatcher::markPaidManually()`
      (bỏ đường ghi trực tiếp `payment_status` cũ).
- [ ] Hàng chờ **lệch tiền** (`mismatched`) hiện nổi bật.
- [ ] Chi tiết đơn: bảng lịch sử `order_payments` (số tiền, thời điểm, reference code, bank).
- [ ] `Admin\OrderController::updatePayment()` viết lại theo mốc thời gian thay vì enum cột.
      Giữ 2 ràng buộc hiện có: chặn đơn cha, chặn đơn đã `returned`.

**Test:**
- [ ] Admin bấm tay → cùng kết quả như webhook
- [ ] Đơn cha vẫn bị chặn; đơn `returned` vẫn bị chặn
- [ ] Component: 4 trạng thái render đúng nhãn

---

## T9 — Job tự huỷ đơn chưa CK (1.5 ngày)

Phụ thuộc: T4

- [ ] `Jobs\CancelUnpaidOrders` / command `orders:cancel-unpaid`
- [ ] Quét đơn `prepay_choice != none` **và** `status = pending` **và**
      `payment_expires_at < now()` **và** chưa nhận đồng nào
- [ ] **Trước khi huỷ, gọi API SePay xác minh lần cuối** (theo `pay_code`). Có giao dịch →
      đánh dấu paid qua `PaymentMatcher`, **không** huỷ.
- [ ] Huỷ → `status = cancelled`, `order_payments.status = expired`, nhả tồn kho, mail thông báo.
- [ ] TTL đọc từ config (mặc định **60 phút** — phải > cửa sổ retry webhook ~33 phút).

**Test:**
- [ ] Đơn `rental` quá 60' chưa CK → cancelled + tồn kho trả lại đúng (qua `AvailabilityService`)
- [ ] Đơn `none` quá 60' → **không** bị huỷ
- [ ] Đơn đã `paid` → **không** bị huỷ dù quá hạn
- [ ] API SePay báo đã có giao dịch → không huỷ, chuyển thành `paid`
- [ ] Đơn 59 phút → chưa huỷ (kiểm biên)
- [ ] Chạy đúng trên đơn cha/con

---

## T10 — Job đối soát định kỳ (1 ngày)

Phụ thuộc: T4

- [ ] `Services\SePay\ApiClient`: `GET {api_base}/v2/transactions?webhook_success=0`,
      `Authorization: Bearer`, **tôn trọng rate limit 3 req/s** (throttle giữa các trang).
- [ ] **DTO riêng cho API v2** — snake_case (`amount_in`, `reference_number`), **khác**
      webhook camelCase (`transferAmount`, `referenceCode`). Không dùng chung DTO.
- [ ] `Jobs\ReconcileSePayTransactions` → map sang `PaymentMatcher` (cùng đường ghi).
- [ ] Dùng `since_id` để không quét lại từ đầu mỗi lần.

**Test:**
- [ ] Mock API (`Http::fake()`) → giao dịch webhook trôi được khớp đúng đơn
- [ ] Giao dịch đã xử lý qua webhook → không xử lý lại (idempotent)
- [ ] Rate limit được tôn trọng khi phân trang
- [ ] Field snake_case map đúng (test này chặn lỗi lẫn DTO webhook/API)

---

## T11 — Scheduler + tài liệu (0.5 ngày)

Phụ thuộc: T9, T10

- [ ] `routes/console.php`: schedule `orders:cancel-unpaid` mỗi 5 phút,
      `sepay:reconcile` mỗi 15 phút.
- [ ] Cập nhật `.claude/rules/tech-strategy.md`: bỏ dòng *"Không thanh toán online (chỉ COD)"*,
      thêm SePay vào bảng. ADR này là căn cứ thay đổi golden path.
- [ ] Cập nhật `CLAUDE.md` (trạng thái hiện tại) + `.env.example`.
- [ ] Ghi runbook sự cố: webhook fail thì xem ở đâu, gửi lại thủ công thế nào (tính năng
      **Sự cố** ở dashboard SePay), cách đối soát tay.
- [ ] **Liên kết bopcamping-ybsm** (cron trên server) — blocker cho production.

---

## T12 — Quality gates + kiểm thử tích hợp (1 ngày)

Phụ thuộc: tất cả

- [ ] Test tích hợp end-to-end với **payload mô phỏng thật từ SePay Test mode** (không chỉ
      payload tự bịa) — đây là điểm dễ sai nhất: field thật có thể khác docs.
- [ ] Thử luồng thật trên Test mode: đặt đơn → quét QR → mô phỏng giao dịch → xác nhận tự động.
- [ ] Kiểm test **collation-safe** (chạy đúng trên cả sqlite và MySQL `utf8mb4_unicode_ci`).
- [ ] Chạy đủ: `php artisan test` · `npm test` · `npx tsc --noEmit` · `npm run lint` ·
      `./vendor/bin/pint --test` · `npm run build`
- [ ] Rà bảo mật: không secret trong code, không log secret/PII, webhook có verify,
      trang thanh toán không lộ đơn khác.

---

## Đồ thị phụ thuộc

```
T0 (SePay account, BLOCKING) ─┬─→ T2 (config + QR) ──┐
                              └─→ T3 (HMAC verify) ──┤
                                                      │
T1 (schema + model) ─┬─→ T4 (PaymentMatcher) ─────────┼─→ T5 (webhook endpoint)
                     │         │                      │
                     │         ├─→ T8 (admin UI)      │
                     │         ├─→ T9 (auto-cancel) ───┼─→ T11 (scheduler + docs)
                     │         └─→ T10 (reconciler) ───┘
                     │
                     └─→ T6 (checkout UI) ─→ T7 (payment page + poll)

                                        tất cả ─→ T12 (quality gates)
```

**Làm song song được**: T0 ∥ T1 · T2 ∥ T3 · T6 ∥ (T4→T5) · T8 ∥ T9 ∥ T10

**Đường dài nhất (critical path)**: T1 → T4 → T5 → T12 ≈ 6 ngày, nhưng T0 phải xong trước
T3/T2 nên thực tế ~12–14 ngày nếu làm tuần tự một người.

## Thứ tự khuyến nghị

1. **T0 trước tiên** — kết quả có thể đổi thiết kế (HMAC không có ở gói FREE → phải sửa ADR).
2. T1 song song T0 (không phụ thuộc dashboard).
3. T2 + T3 sau khi T0 xong.
4. T4 — task khó nhất, nhiều test nhất, đừng rút gọn.
5. T5 → chạy được luồng backend đầu-cuối, verify bằng Test mode.
6. T6 → T7 (phần khách thấy).
7. T8, T9, T10 song song.
8. T11, T12 chốt.

## Ghi chú cho người thực hiện

- **Đừng dùng `json_encode($request->all())`** để tính HMAC. Phải là
  `$request->getContent()`. Đây là lỗi số 1 khi tích hợp webhook có signature.
- **Đừng bỏ UNIQUE trên `sepay_transaction_id`** vì "app đã check rồi". SePay retry 8 lần,
  hai job chạy song song sẽ lọt qua check tầng app.
- **Đừng đặt TTL tự huỷ < 34 phút.** Cửa sổ retry webhook là ~33 phút.
- **Đừng dùng chung DTO** cho webhook (camelCase) và API v2 (snake_case).
- Webhook phải trả **đúng** `{"success": true}`; chỉ HTTP 200 là chưa đủ theo docs.
- Test luôn phải phủ **cả đơn cha và đơn con** — dự án có parent/child orders.
