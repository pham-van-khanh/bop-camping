# PRD — Tính năng Thuê theo Combo

> **Artifact:** `prd_combo.md` · **Trạng thái:** Draft v1 · **Ngày:** 2026-07-02
> **Liên quan:** `KE_HOACH.md` (mô hình dữ liệu gốc), `AvailabilityService` (logic tồn kho theo ngày)
> **Bước tiếp theo sau khi duyệt:** `adr_combo_data_model.md` → `plan_combo.md` → Beads issues

---

## 1. Tóm tắt (PR-FAQ rút gọn)

**BopCamping ra mắt Thuê theo Combo** — khách thuê trọn bộ đồ camping (lều, đệm, bàn, ghế, bếp...) trong một lần chọn, giá rẻ hơn thuê lẻ, cọc thấp hơn tổng cọc lẻ. Khách mới không cần biết mình thiếu gì; khách cũ tiết kiệm tiền và thời gian.

**FAQ nhanh:**
- *Combo có kiểm tra trùng lịch không?* Có — combo "còn hàng" khi **tất cả** sản phẩm con còn đủ số lượng trong khoảng ngày thuê, dùng chung logic tồn kho hiện tại.
- *Khách làm hỏng 1 món trong combo thì sao?* Đơn hàng lưu từng sản phẩm con, cọc snapshot theo món → trừ cọc theo món, không ảnh hưởng cả combo.
- *Voucher có áp lên combo không?* Mặc định **không** (combo đã là giá ưu đãi), admin có thể bật per-voucher.

---

## 2. Mục tiêu & Không-mục-tiêu

### Mục tiêu (đo được)
| # | Mục tiêu | Chỉ số |
|---|----------|--------|
| G1 | Tăng giá trị đơn trung bình (AOV) | AOV đơn combo ≥ 1.5× đơn lẻ |
| G2 | Tăng chuyển đổi khách mới | ≥ 30% đơn từ khách lần đầu là đơn combo |
| G3 | Upsell từ giỏ hàng | ≥ 10% giỏ hàng có gợi ý combo được convert |
| G4 | Không phá vỡ logic tồn kho | 0 bug double-booking sau khi ra mắt |

### Không-mục-tiêu (phase sau, KHÔNG làm ở v1)
- **Combo linh hoạt** ("chọn 1 trong 3 loại lều") — phức tạp hóa pricing + availability.
- **Review riêng cho combo** — review vẫn theo sản phẩm.
- **Gợi ý tự động từ lịch sử đơn** (Case 2 phase 2) — cần đủ dữ liệu đơn hàng trước.
- **Combo động theo mùa/giá thay đổi theo ngày trong tuần.**

---

## 3. User Stories

### Khách hàng
- **US-01**: Là khách mới, tôi muốn xem danh sách combo theo nhu cầu ("gia đình 4 người", "cặp đôi") để không phải tự nghĩ mình cần thuê gì.
- **US-02**: Là khách, tôi muốn chọn khoảng ngày và biết ngay combo còn hàng hay không, nếu hết thì món nào hết và ngày nào gần nhất còn.
- **US-03**: Là khách đang xem trang lều, tôi muốn thấy các món thường thuê cùng (bàn, ghế) và thêm tất cả vào giỏ trong 1 click.
- **US-04**: Là khách đã bỏ 3 món lẻ vào giỏ, tôi muốn được báo nếu các món đó (hoặc gần đủ) khớp một combo rẻ hơn, và convert trong 1 click.
- **US-05**: Là khách, tôi muốn thấy rõ tiết kiệm được bao nhiêu (số tiền + %) khi thuê combo so với thuê lẻ.

### Admin
- **US-06**: Là admin, tôi muốn tạo/sửa/ẩn combo: chọn sản phẩm con + số lượng từng món, set giá combo/ngày và tiền cọc combo.
- **US-07**: Là admin, tôi muốn được cảnh báo khi ẩn/xóa một sản phẩm đang nằm trong combo, và combo liên quan tự ẩn để không bán nhầm.
- **US-08**: Là admin, tôi muốn gán tay danh sách "thường thuê cùng" cho từng sản phẩm.
- **US-09**: Là admin, tôi muốn xem combo nào được thuê nhiều và tỷ lệ convert từ banner gợi ý (phục vụ marketing).

---

## 4. Mô hình dữ liệu

### Bảng mới

```
combos
  id, name, slug, description, deposit (cọc combo),
  combo_price (giá/ngày), suitable_for (số người, nullable),
  is_active, sort_order, timestamps

combo_items
  id, combo_id (FK), product_id (FK), quantity
  UNIQUE(combo_id, product_id)

combo_images
  id, combo_id (FK), path, sort_order
  (hoặc tái dùng pattern ProductImage nếu muốn polymorphic — quyết ở ADR)

product_accessories            # Case 2 — "thường thuê cùng"
  id, product_id (FK), related_product_id (FK), sort_order
  UNIQUE(product_id, related_product_id)
```

### Sửa bảng hiện có

```
order_items
  + combo_id          (nullable FK — item này thuộc combo nào, null = thuê lẻ)
  + combo_group_uuid  (nullable — nhóm các item cùng 1 combo trong 1 đơn;
                       cần vì 1 đơn có thể chứa 2 combo giống nhau)
  + allocated_price   (giá phân bổ theo tỷ lệ từ combo_price — xem mục 5.3)
  + allocated_deposit (cọc phân bổ — snapshot tại thời điểm đặt)

vouchers
  + applicable_to_combos (boolean, default false) — xem mục 7
```

**Nguyên tắc quan trọng:** đơn hàng KHÔNG lưu combo như 1 dòng gộp. Combo được "bung" thành các `order_items` theo từng sản phẩm con. Lý do:
1. Logic kiểm tra trùng lịch hiện tại chạy trên product — giữ nguyên, không cần sửa.
2. Trả thiếu/hỏng 1 món → trừ cọc theo `allocated_deposit` của món đó.
3. Báo cáo tồn kho, số lượt thuê per-product vẫn đúng.

---

## 5. Logic nghiệp vụ

### 5.1 Tồn kho combo theo ngày — ⚠️ SINGLE SOURCE OF TRUTH

**KHÔNG viết logic tồn kho mới.** Mở rộng `AvailabilityService` với method mới, bên trong gọi lại hàm tính tồn kho per-product hiện có:

```php
// Pseudo-code
function comboAvailable(Combo $combo, $start, $end): int
{
    return $combo->items->map(fn ($item) =>
        intdiv($this->availableQuantity($item->product, $start, $end), $item->quantity)
    )->min();
    // = min( available(product_i) / quantity_i )
}
```

- Combo còn hàng ⇔ `comboAvailable(...) >= số combo khách muốn thuê`.
- Mọi chỗ hiển thị còn/hết combo + validate checkout đều gọi hàm này. Không duplicate.
- Vì order_items lưu theo product con, đơn combo tự động "chiếm" tồn kho product như đơn lẻ — không cần cơ chế giữ chỗ riêng.

### 5.2 Giá & hiển thị tiết kiệm

- `sum_individual = Σ (product.price_per_day × quantity_i)` — tính runtime, không lưu.
- **Tiết kiệm** = `sum_individual − combo_price`, hiển thị cả số tiền và %.
- Admin chỉ nhập `combo_price`; % tiết kiệm tự tính — không cho nhập tay để tránh lệch khi giá lẻ thay đổi.
- Validate khi lưu combo: `combo_price < sum_individual` (warning nếu vi phạm, cho phép override có chủ đích).

### 5.3 Phân bổ giá & cọc vào order_items

Khi checkout combo, phân bổ theo tỷ lệ giá lẻ:

```
allocated_price_i = combo_price × (price_i × qty_i) / sum_individual
```

- Làm tròn về 100₫; món cuối cùng nhận phần dư để tổng khớp đúng `combo_price` (tránh lệch do làm tròn).
- `allocated_deposit_i` phân bổ tương tự từ `combo.deposit`.
- Snapshot tại thời điểm đặt — giá lẻ đổi sau đó không ảnh hưởng đơn cũ.

### 5.4 Cart combo detection (Case 3)

Chạy mỗi khi giỏ hàng thay đổi (thêm/xóa/sửa quantity/đổi ngày). So sánh items lẻ trong giỏ (cùng khoảng ngày) với `combo_items` của các combo active:

| Tình huống | Điều kiện | Hành vi |
|---|---|---|
| **Khớp đủ** | Giỏ ⊇ combo, đủ quantity | Banner "Giỏ của bạn khớp Combo X — tiết kiệm Y₫" + nút convert 1 click |
| **Superset** | Khớp đủ + có món thừa | Convert phần khớp thành combo, món thừa giữ nguyên lẻ |
| **Thiếu 1 món** | Giỏ thiếu đúng 1 loại sản phẩm (hoặc thiếu quantity 1 món) | Upsell: "Thêm [bếp gas] nữa là thành Combo BBQ, rẻ hơn Z₫" + nút thêm nhanh |

**Ràng buộc:**
- Chỉ gợi ý khi `comboAvailable(combo, start, end) ≥ 1` với khoảng ngày của giỏ. Gợi ý xong bấm vào báo hết = trải nghiệm ngược.
- Chỉ gợi ý khi convert thực sự rẻ hơn (sau khi tính voucher nếu có — xem mục 7).
- Nhiều combo cùng khớp → ưu tiên combo tiết kiệm nhiều nhất, tối đa hiển thị 1 gợi ý.
- Convert giữ nguyên khoảng ngày; items lẻ được thay bằng combo (đánh `combo_group_uuid` khi tạo đơn).
- Log sự kiện `combo_suggestion_shown` / `combo_suggestion_converted` → phục vụ US-09.

### 5.5 Combo hết hàng một phần (Case 4)

Khi combo hết vì ≥1 món con hết trong khoảng ngày đã chọn:
- Hiện rõ **món nào hết** ("Đệm hơi đã được thuê hết trong 12–14/07").
- Gợi ý (a) **khoảng ngày gần nhất còn đủ** — scan tối đa 30 ngày tới, và/hoặc (b) **sản phẩm thay thế cùng danh mục** còn hàng (v1 chỉ hiển thị tham khảo, chưa cho swap trong combo — swap = combo linh hoạt, ngoài scope).

### 5.6 Gợi ý trên trang sản phẩm (Case 2)

- Section "Thường thuê cùng" dưới thông tin sản phẩm: lấy từ `product_accessories`, chỉ hiện món **còn hàng trong khoảng ngày khách đang chọn**, kèm checkbox + quantity + nút "Thêm tất cả vào giỏ".
- Nếu sản phẩm đang xem thuộc ≥1 combo active còn hàng: banner "Sản phẩm này có trong **Combo Gia Đình 4 người** — tiết kiệm 120k/ngày" → link trang combo. Ưu tiên banner combo hơn gợi ý lẻ (giá trị marketing cao hơn).

---

## 6. Giao diện

### Public (tông be/màu đất Naturehike, đồng bộ layout hiện có)
| Trang | Nội dung chính |
|---|---|
| `/combos` | Danh sách combo: ảnh, tên, tag nhu cầu (`suitable_for`), giá combo, giá lẻ gạch ngang, badge % tiết kiệm, trạng thái còn/hết theo date-picker chung đầu trang |
| `/combos/{slug}` | Chi tiết: gallery, danh sách món con (link về trang sản phẩm), bảng so sánh giá lẻ vs combo, date-picker + check tồn kho realtime, cọc, nút thêm giỏ. Case 4 hiển thị ở đây |
| Homepage | Section "Combo tiết kiệm" (3–4 combo nổi bật theo `sort_order`) |
| Trang sản phẩm | Section 5.6 |
| Giỏ hàng | Banner detection 5.4; items combo nhóm lại theo `combo_group_uuid`, hiển thị như 1 khối có thể mở rộng xem món con |

### Admin
| Trang | Nội dung |
|---|---|
| Combos index | Bảng: tên, số món, giá, % tiết kiệm (tự tính), trạng thái, lượt thuê |
| Combo form | Chọn sản phẩm (search + quantity từng món), preview tổng giá lẻ & % tiết kiệm live khi nhập giá, upload ảnh, cọc, `suitable_for`, active toggle |
| Product form | Thêm tab "Thường thuê cùng" (quản lý `product_accessories`) + cảnh báo nếu product thuộc combo khi bấm deactivate (US-07: confirm → tự ẩn combo liên quan + ghi log) |
| Dashboard | Widget: top combo theo lượt thuê, tỷ lệ convert banner (từ event log 5.4) |

---

## 7. Quy tắc Voucher × Combo

- Mặc định voucher **không áp** lên phần giá trị combo trong đơn (combo đã là giá ưu đãi → tránh double discount).
- Voucher có `applicable_to_combos = true` mới được tính trên cả giá combo.
- Đơn hỗn hợp (combo + items lẻ): voucher thường chỉ tính trên phần items lẻ.
- Referral: giữ nguyên hành vi hiện tại, tính trên tổng đơn (xác nhận lại ở ADR nếu conflict).

---

## 8. Acceptance Criteria (rút gọn — chi tiết ở test plan)

- [ ] **AC-1**: Tạo combo 3 món trong admin → hiện đúng ở `/combos` với % tiết kiệm tự tính.
- [ ] **AC-2**: Combo có món con chỉ còn 1 chiếc trong 12–14/07; đặt combo thành công lần 1 → lần 2 cùng khoảng ngày báo hết, và hiển thị đúng món hết (Case 4).
- [ ] **AC-3**: Đặt combo → `order_items` có N dòng (N = số món), cùng `combo_group_uuid`, tổng `allocated_price` = `combo_price` chính xác đến từng đồng.
- [ ] **AC-4**: Đơn combo chiếm tồn kho product con → khách khác thuê lẻ món đó cùng khoảng ngày thấy giảm số lượng đúng.
- [ ] **AC-5**: Giỏ có đủ món khớp combo → banner hiện, convert 1 click, tổng tiền giảm đúng số tiết kiệm.
- [ ] **AC-6**: Giỏ thiếu 1 món → banner upsell hiện kèm nút thêm nhanh; combo hết hàng trong khoảng ngày đó → KHÔNG hiện banner.
- [ ] **AC-7**: Deactivate product thuộc combo → confirm dialog → combo tự ẩn khỏi public, có log.
- [ ] **AC-8**: Voucher thường không giảm phần combo; voucher có flag mới giảm được.
- [ ] **AC-9**: Section "Thường thuê cùng" chỉ hiện món còn hàng trong khoảng ngày đang chọn.
- [ ] **AC-10**: Toàn bộ check tồn kho combo đi qua `AvailabilityService` — grep codebase không có công thức overlap `start_A <= end_B AND start_B <= end_A` thứ hai.

---

## 9. Phasing & thứ tự triển khai

| Phase | Nội dung | Phụ thuộc |
|---|---|---|
| **P1** | Migrations + models + mở rộng `AvailabilityService` + admin CRUD combo (US-06, US-07) | — |
| **P2** | Trang `/combos`, `/combos/{slug}`, checkout combo end-to-end, Case 4, section homepage | P1 |
| **P3** | `product_accessories` + section trang sản phẩm + banner "thuộc combo" (Case 2) | P1 |
| **P4** | Cart detection + upsell + event log + dashboard widget (Case 3, US-09) | P1–P3 |

Mỗi phase: viết test trước (Feature test cho availability/pricing/detection), chạy quality gates, branch riêng từ `main`, push xong mới coi là done — theo Core Principles của repo.

## 10. Câu hỏi mở (chốt ở ADR / với chủ shop)

1. Cọc combo: admin nhập tay hay tự tính = X% tổng cọc lẻ? (PRD giả định nhập tay.)
2. Combo có tính vào chương trình referral hiện tại như đơn thường không?
3. Ảnh combo: bảng riêng `combo_images` hay chuyển `ProductImage` sang polymorphic?
4. Giới hạn số món tối đa trong 1 combo? (Đề xuất: không giới hạn cứng, UI khuyến nghị ≤ 8.)
5. Khách đổi khoảng ngày trong giỏ sau khi đã convert combo → re-check tồn kho combo và báo ngay, hay chỉ báo ở checkout? (Đề xuất: báo ngay trong giỏ.)