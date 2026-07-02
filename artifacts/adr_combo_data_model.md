# ADR — Mô hình dữ liệu Combo & các giả định từ câu hỏi mở

> **Artifact:** `adr_combo_data_model.md` · **Trạng thái:** ⚠️ **Proposed — chờ chủ shop duyệt**
> **Ngày:** 2026-07-02 · **Nguồn:** [prd_combo.md](prd_combo.md) mục 10 (câu hỏi mở)
> P1 được triển khai theo các giả định dưới đây; mục nào bị bác thì sửa lại trước khi làm P2.

## Bối cảnh

PRD combo để lại 5 câu hỏi mở cần chốt với chủ shop. Để không chặn P1, mỗi câu được quyết theo giả định ghi sẵn trong PRD (hoặc phương án ít rủi ro nhất), ghi lại tại đây kèm lý do và ảnh hưởng nếu đổi ý.

## Quyết định

### ADR-1. Cọc combo: admin **nhập tay** (không tự tính % tổng cọc lẻ)

- Theo đúng giả định PRD (mục 10.1). Cột `combos.deposit` nullable, admin nhập trực tiếp trong form.
- **Lý do:** chủ shop cần tự do định giá cọc theo giá trị thực tế của bộ đồ (điểm bán của combo là "cọc thấp hơn tổng cọc lẻ" — PRD mục 1); công thức % cứng dễ ra số lẻ khó truyền thông.
- **Nếu đổi ý:** thêm cột `deposit_percent` + auto-fill ở form, schema hiện tại không phải sửa.

### ADR-2. Referral × combo: **giữ nguyên hành vi hiện tại** — tính trên tổng đơn

- Theo PRD mục 7: referral tính trên tổng đơn, không phân biệt phần combo hay phần lẻ.
- **Lý do:** referral thưởng cho việc *giới thiệu khách*, không phải khuyến mãi trên món hàng — không có double-discount cùng bản chất như voucher; sửa hành vi referral là scope creep.
- **Nếu đổi ý:** xử lý ở P2 (chỗ tính thưởng referral), không ảnh hưởng schema P1.

### ADR-3. Ảnh combo: **bảng riêng `combo_images`**, KHÔNG chuyển `ProductImage` sang polymorphic

- **Lý do:**
  1. Repo đã có tiền lệ bảng media riêng per-entity (`product_images`, `camping_spot_media`, `review_images`) — bảng riêng nhất quán với codebase hơn.
  2. Chuyển `product_images` sang polymorphic là migration đụng dữ liệu thật + sửa mọi chỗ đang query, rủi ro cao, không mang lại tính năng gì cho khách.
  3. `combo_images` copy đúng cấu trúc `product_images` (path, sort_order, type image/video) → controller/FE tái dùng pattern sẵn có.
- **Trade-off chấp nhận:** duplicate ~3 cột schema giữa các bảng media (incidental duplication — chấp nhận được theo rules code-quality).

### ADR-4. Số món tối đa trong combo: **không giới hạn nghiệp vụ, UI khuyến nghị ≤ 8, trần kỹ thuật 50**

- Theo đề xuất PRD (mục 10.4): không có giới hạn cứng về nghiệp vụ.
- Server validate `items` array `max:50` — đây là trần **kỹ thuật chống abuse** (CWE-770, cùng lý do trần 12 file/lần upload đã áp cho media), không phải rule nghiệp vụ; UI hiển thị khuyến nghị ≤ 8 món.
- **Nếu đổi ý:** đổi 1 số trong validation rule.

### ADR-5. Đổi khoảng ngày trong giỏ sau khi convert combo: **re-check tồn kho và báo ngay trong giỏ**

- Theo đề xuất PRD (mục 10.5), không đợi đến checkout.
- **Lý do:** nhất quán với ràng buộc "không gợi ý thứ đã hết" (PRD 5.4) — báo sớm nhất có thể; giỏ đã có sẵn cơ chế refresh (`cart.refresh`) để nối vào.
- **Phạm vi:** P4 (cart detection). Checkout vẫn validate lần cuối qua `AvailabilityService` như mọi đơn (defense in depth).

## Quyết định phụ (phát sinh khi thiết kế P1, cùng chờ duyệt)

- **`order_items.combo_id` dùng `nullOnDelete`:** xoá combo không được đụng đơn cũ (đơn đã bung thành items per-product, giá đã snapshot) — item chỉ mất tham chiếu combo, dữ liệu tiền không đổi.
- **`combo_items.product_id` dùng `cascadeOnDelete`:** xoá product thì dòng combo_items biến mất; an toàn vì US-07 đã tự ẩn combo liên quan ngay trước đó (không bao giờ bán combo thiếu món). Không dùng `restrictOnDelete` vì sẽ chặn cứng admin xoá sản phẩm — trái với US-07 (cho xoá, có cảnh báo).
- **`suitable_for` là số người (tinyint, nullable)** đúng chú thích PRD mục 4; nhãn hiển thị ("gia đình 4 người", "cặp đôi") do FE render từ số.

## Hệ quả

- P1 triển khai được ngay, không chờ chốt câu hỏi mở.
- Mọi giả định đều sửa được với chi phí thấp trước P2; riêng ADR-3 (bảng riêng) nếu bác thì phải viết lại migration + controller ảnh combo — cần chốt sớm nhất.
