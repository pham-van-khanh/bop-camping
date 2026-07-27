# Plan — Nút Zalo nổi phía trên nút "Góp ý"

**Loại:** Small feature (~0.5 ngày) · **Reversibility:** Two-Way Door (thuần UI, không đổi schema/API)
**Ngày:** 2026-07-27

## 1. Yêu cầu

1. Nút Zalo nổi (floating) hiển thị **phía trên** nút "Góp ý" ở góc phải-dưới, trên **mọi trang khách**.
2. Có **2 số Zalo** → bấm icon thì **mở panel** liệt kê cả 2 (nhãn + SĐT), bấm từng dòng để mở Zalo.
3. Chỉ có **1 số Zalo** → bấm icon **mở thẳng** Zalo (không qua panel).
4. Không có số nào → **không render** nút.
5. **Xoá dải Zalo dưới hero** ở trang chủ — Zalo chỉ còn xuất hiện ở nút nổi (và ở footer, giữ nguyên).

## 2. Hiện trạng (đã khảo sát)

| Thành phần | Vị trí | Ghi chú |
|---|---|---|
| Nút "Góp ý" | [FeedbackWidget.tsx:41](resources/js/Components/site/FeedbackWidget.tsx:41) | `fixed bottom-5 right-5 z-[80]`, `h-12`, chữ ẩn dưới `sm` |
| Dải Zalo (trang chủ) | [ZaloContactStrip.tsx](resources/js/Components/site/ZaloContactStrip.tsx) | Chỉ dùng ở [Welcome.tsx:120](resources/js/Pages/Welcome.tsx:120) — **xoá cả component lẫn chỗ dùng** |
| Layout khách | [SiteLayout.tsx:33](resources/js/Layouts/SiteLayout.tsx:33) | Nơi mount `<FeedbackWidget />` → chèn widget mới cạnh đó |
| Nguồn dữ liệu | [HandleInertiaRequests.php:145](app/Http/Middleware/HandleInertiaRequests.php:145) | Shared prop `site.zalo_1` / `site.zalo_2` (`{label, phone, url}`) |
| Resolve URL | [SiteSetting::zaloUrl()](app/Models/SiteSetting.php:42) | Ưu tiên `zaloN_url`, fallback `zalo.me/<phone>`, null nếu trống |
| Type | [index.d.ts:32](resources/js/types/index.d.ts:32) | `SiteZalo` đã có sẵn |

**Không cần đổi backend** — dữ liệu đã có sẵn trong shared prop `site`.

Đã kiểm tra xung đột: không có `fixed bottom-*` nào khác ở `resources/js/Pages/*.tsx`. Toast ở `bottom-[26px] left-1/2` (giữa màn) — không chồng. Các modal dùng z-[90..97] — cao hơn nút nổi, đúng thứ tự.

## 3. Thiết kế

### Component mới: `resources/js/Components/site/ZaloFloatButton.tsx`

```
usable = [site.zalo_1, site.zalo_2].filter(z => z?.url)
usable.length === 0  → return null
usable.length === 1  → <a href={usable[0].url} target="_blank" rel="noreferrer">  (mở thẳng)
usable.length >= 2   → <button> toggle panel liệt kê từng tài khoản
```

**Vị trí:** `fixed bottom-[80px] right-5 z-[80]`
Tính toán: nút Góp ý chiếm `20px → 68px` (bottom-5 + h-12); Zalo ở `80px → 128px` ⇒ khoảng hở 12px.

**Hình dạng:** nút tròn `h-12 w-12`, nền `#0068FF` (xanh Zalo), icon Zalo trắng, `rounded-full shadow-lg`, hover `-translate-y-0.5` — đồng bộ với nút Góp ý. Dùng lại markup `ZaloMark` SVG có sẵn (tách thành component chung để tránh lặp — xem DRY bên dưới).

**Panel (khi có 2 số):**
- Neo trên nút: `absolute bottom-[calc(100%+10px)] right-0`, `w-[248px]`, `rounded-[14px] border border-cardBorder bg-white p-2 shadow-xl`.
- Mỗi dòng: nhãn (`z.label`, đậm) + SĐT (`z.phone`, font-mono, text-moss), bấm mở `z.url` tab mới.
- Đóng khi: bấm ra ngoài (listener trên `document`), phím `Esc`, hoặc sau khi chọn 1 dòng.
- A11y: `aria-expanded`, `aria-haspopup="menu"`, `aria-label="Liên hệ Zalo"`, panel `role="menu"`.
- Animation: `framer-motion` fade + slide-up 6px (khớp EASE `[0.2, 0.7, 0.2, 1]` đang dùng trong `ZaloContactStrip`).

**Mobile:** nút tròn icon-only nên không cần biến thể riêng; panel `w-[248px]` neo phải, không tràn ở màn 360px.

### Gỡ dải Zalo dưới hero

Xoá theo thứ tự (dây chuyền dead code — `site` trong `Welcome.tsx` chỉ phục vụ dải này):

1. [Welcome.tsx:120](resources/js/Pages/Welcome.tsx:120) — bỏ `<ZaloContactStrip …/>` + comment kèm theo.
2. [Welcome.tsx:51](resources/js/Pages/Welcome.tsx:51) — bỏ `const { site } = usePage<PageProps>().props;` (không còn chỗ dùng nào khác).
3. [Welcome.tsx:1,5,6](resources/js/Pages/Welcome.tsx:1) — bỏ import `usePage`, `ZaloContactStrip`, và type `PageProps`.
4. Xoá file `resources/js/Components/site/ZaloContactStrip.tsx` (không còn nơi nào import — đã grep toàn `resources/js` và `tests`).

Icon `ZaloMark` (SVG) đang nằm trong file bị xoá → **chuyển thẳng vào `ZaloFloatButton.tsx`**, không cần component dùng chung nữa (Footer tự vẽ icon riêng của nó).

**Gộp chung 1 commit với việc thêm nút nổi**, để trang chủ không có khoảng thời gian nào mất hẳn lối liên hệ Zalo.

### Mount

Thêm `<ZaloFloatButton />` vào [SiteLayout.tsx](resources/js/Layouts/SiteLayout.tsx) ngay trước `<FeedbackWidget />`. Hai nút độc lập về vị trí (đều `fixed`), không phụ thuộc thứ tự DOM.

## 4. Các bước triển khai

1. Viết `ZaloFloatButton.tsx`: đọc `usePage<PageProps>().props.site`, nhánh 0/1/2 số, panel + click-outside + Esc, SVG Zalo mang từ `ZaloContactStrip` sang.
2. Mount vào `SiteLayout.tsx` (ngay trước `<FeedbackWidget />`).
3. Gỡ dải Zalo dưới hero theo 4 bước ở mục 3, xoá file `ZaloContactStrip.tsx`.
4. Verify bằng preview: trang chủ (dải cũ đã biến mất, khoảng cách hero → section kế tiếp còn hợp lý) + 1 trang khác (vd `/san-pham`); kiểm tra cả 2 trường hợp bằng cách tạm bỏ trống `zalo2_phone`/`zalo2_url` ở admin Cài đặt shop.
5. Quality gates.

## 5. Acceptance criteria

- [ ] Trên mọi trang khách, nút Zalo tròn nằm ngay trên nút "Góp ý", cách 12px, không che nội dung.
- [ ] Cấu hình 2 tài khoản Zalo → bấm icon hiện panel với **đủ 2** nhãn + SĐT; bấm 1 dòng mở `zalo.me` tab mới.
- [ ] Chỉ cấu hình 1 tài khoản → bấm icon mở thẳng Zalo, **không** hiện panel.
- [ ] Xoá cả 2 tài khoản (trống `phone` lẫn `url`) → nút biến mất, nút Góp ý giữ nguyên vị trí.
- [ ] Panel đóng khi bấm ra ngoài và khi nhấn `Esc`.
- [ ] Ở 375px: nút và panel nằm gọn trong màn, không tràn ngang.
- [ ] Modal (đăng nhập, góp ý, camping guide) vẫn nằm **trên** nút nổi.
- [ ] Trang chủ **không còn** dải "LIÊN HỆ NHANH QUA ZALO" dưới hero; khoảng cách hero → section "Một bộ đồ, đi khắp địa hình" nhìn vẫn cân (section đó có sẵn `pt-12`).
- [ ] `grep -rn "ZaloContactStrip" resources/ tests/` không còn kết quả; không còn import thừa trong `Welcome.tsx`.

## 6. Quality gates

```bash
npx tsc --noEmit
npm run build
php artisan test
./vendor/bin/pint --test
```

Không cần test PHP mới (không đổi backend). Prop `site.zalo_*` đã được `tests/Feature/SiteSettingTest.php` phủ.

## 7. Rủi ro

| Rủi ro | Mức | Xử lý |
|---|---|---|
| Chồng với nút Góp ý khi Góp ý đổi chiều cao | Thấp | Cùng hằng số `h-12`; ghi chú trong comment component |
| Panel bị modal che nhưng vẫn bắt click | Thấp | Panel z-[80] < modal z-[90+]; đóng panel khi mất focus |
| Mất dải Zalo ⇒ trang chủ giảm lối liên hệ nổi bật | Trung bình | Nút nổi có mặt trên **mọi** trang (kể cả trang chủ), hiện diện rộng hơn dải cũ; footer vẫn liệt kê đủ 2 số |
| Xoá `site` khỏi `Welcome.tsx` làm vỡ chỗ khác | Thấp | Đã grep: `site` trong file chỉ dùng cho dải này; `tsc --noEmit` bắt được ngay nếu sót |

## 8. Beads

- `bopcamping-sd0b` (P3, task) — Tách ZaloMark thành component dùng chung
- `bopcamping-uen0` (P2, feature) — ZaloFloatButton + mount vào SiteLayout — **blocked by** `bopcamping-sd0b`
