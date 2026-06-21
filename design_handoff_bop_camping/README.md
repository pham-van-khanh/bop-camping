# Handoff: BỐP CAMPING — Website cho thuê thiết bị cắm trại

## Overview
BỐP CAMPING là website cho thuê thiết bị dã ngoại của **một shop duy nhất** (không phải marketplace).
Khách thuê **theo ngày** (chọn ngày nhận + ngày trả), hệ thống kiểm tra trùng lịch và tồn kho,
có **thu tiền cọc** mỗi món, thanh toán **COD** (trả khi nhận). Đăng nhập bằng **số điện thoại + tên**
(không email/mật khẩu). Toàn bộ nội dung **tiếng Việt**, khách chủ yếu dùng **điện thoại**.

Điểm nhấn: một **khung cảnh động ("hero")** thể hiện *"cùng một khu trại, đổi địa điểm"* — cùng một
chiếc lều + lửa trại trong khi phông nền chuyển dần qua **Đồng cỏ → Rừng thông → Núi cao → Bờ biển**.

## About the Design Files
Các file trong gói này là **bản tham chiếu thiết kế được dựng bằng HTML** (prototype thể hiện diện mạo
và hành vi mong muốn), **không phải code production để copy nguyên**. Nhiệm vụ là **dựng lại các thiết kế
này trong codebase đích bằng các pattern, thư viện sẵn có của dự án**.

Stack mục tiêu đã chốt: **Laravel 12 + Inertia + React + TypeScript + Tailwind CSS v3 + Framer Motion +
three.js / react-three-fiber**. Hướng dẫn bên dưới ánh xạ trực tiếp sang stack này.

- `reference/BopCamping.dc.html` — toàn bộ UI (6 màn hình) dưới dạng một "Design Component". Bỏ qua lớp
  bao `<x-dc>` / `support.js` (đó chỉ là runtime của môi trường prototype). **Phần cần đọc** là markup
  inline-style trong template và class logic ở cuối file — đây là nguồn chân lý cho layout, copy, state.
- `reference/BiomeHero.jsx` — cảnh hero động. Hiện được dựng **thuần bằng DOM/SVG + một vòng rAF**.
  Xem mục "Hero" để biết cách port sang R3F hoặc giữ nguyên DOM.
- `reference/support.js` — runtime của prototype, **không cần** trong codebase thật. Chỉ để mở file HTML.

> Mở nhanh prototype: phục vụ thư mục `reference/` qua một static server rồi mở `BopCamping.dc.html`.

## Fidelity
**High-fidelity (hifi).** Màu, typography, spacing, bo góc, trạng thái hover/focus và các tương tác đều là
giá trị cuối. Hãy dựng lại pixel-perfect bằng Tailwind, dùng đúng các token ở mục **Design Tokens**.

---

## Bảng màu & Design Tokens

### Màu (dùng đúng hex)
| Vai trò | Hex | Ghi chú |
|---|---|---|
| Cỏ — nhấn chính | `#557A2B` | nút chính, link, trạng thái active, số liệu |
| Cỏ nhạt | `#7FA64B` | viền focus, điểm nhấn phụ |
| Rừng thông — chữ đậm/nền tối | `#2C3D22` | tiêu đề brand, nền CTA, toast |
| Rêu — chữ phụ | `#5C6E47` | body phụ, label |
| Chữ chính | `#18230F` | tiêu đề, body chính |
| Lửa trại — nhấn ấm (dùng ít) | `#C97B36` | badge cọc, badge "sắp hết", DEMO |
| Mặt thẻ | `#FBFCF7` | nền card, input |
| (Cũ) Nền trang | `#F1F4EA` | **đã thay** bằng nền bầu trời, xem dưới |

### Nền "bầu trời → cỏ" (toàn trang — yêu cầu mới nhất của khách)
Nền trang là gradient cố định theo viewport (xanh trời trên cao, chuyển dần xuống tông cỏ):
```css
background: linear-gradient(180deg,#a9d8f0 0%,#c6e6f3 14%,#dceff3 34%,#e7f1ec 60%,#eef4e7 100%);
background-attachment: fixed;
```
Chrome đi kèm tông xanh trời mát:
- Header (sticky): `background: rgba(221,239,250,.78)` + `backdrop-filter: blur(14px)`, viền dưới `#c2dcec`.
- Footer: `background: rgba(214,236,247,.55)`, viền trên `#c2dcec`.
- Viền card trung tính: `#E3E8D6`. Ô dữ liệu nhạt: `#eef2e3` (xanh cỏ nhạt) / `#f1f4ea`.
- Selection `#bfe0f0` / chữ `#16324a`; scrollbar thumb `#a9cee2`.

### Typography
- **Sans**: `Be Vietnam Pro` (400/500/600/700/800) — tiêu đề + thân.
- **Mono**: `Space Mono` (400/700) — **mọi giá tiền, số lượng, mã đơn, nhãn dữ liệu** (cảm giác tag gắn trên đồ).
- Thang cỡ chữ tiêu biểu: H1 hero `clamp(34px,5vw,56px)/1.04/800`, H1 trang `clamp(26px,3.4vw,36px)/800`,
  H2 `clamp(24px,3vw,32px)/800`, body `15-19px/1.6`, label nhỏ `11-13px`, mono giá `15-22px/700`.
- `letter-spacing`: tiêu đề `-.01em` đến `-.02em`; label uppercase `.05em`; mono nhãn `.04em-.14em`.

### Bo góc (một hệ thống xuyên suốt)
- Card / section lớn: **16px** (CTA lớn 24px, hero 20px).
- Control (nút, input, select): **11-13px**.
- Chip / pill / badge: **999px**.
- Stepper số lượng: 9-10px.

### Shadow
- Card hover: `0 20px 40px -22px rgba(44,61,34,.5)` + `translateY(-4px)`.
- Nút chính: `0 12px 24px -10px rgba(85,122,43,.6)`.
- Hero frame: `0 30px 60px -24px rgba(44,61,34,.5)`.
- Toast: `0 16px 40px -12px rgba(44,61,34,.6)`.

### Spacing
- Khung nội dung: `max-width:1200px` (chi tiết 1120, lookup 640, checkout 1060), padding ngang 20px.
- Gap lưới: 18px (card), 12px (toolbar), gap section dọc ~46-54px.

### Quy ước (bắt buộc)
- **Không dùng em-dash.** Dùng "→", "·", "-" thay thế.
- Mono cho tất cả con số có nghĩa dữ liệu; sans cho văn xuôi.
- Một màu nhấn duy nhất (cỏ `#557A2B`); lửa trại dùng rất tiết chế.

---

## Screens / Views

Có 6 màn hình, điều hướng client-side qua một biến `screen`. Header + footer dùng chung mọi màn.
State đề xuất ở mục **State Management**. Mọi copy bên dưới là **chính xác** từ prototype.

### Header (chung)
- Sticky top, blur. Trái: logo (ô vuông cỏ 38px bo 11px + SVG hình lều) + chữ "BỐP CAMPING" (800/17px,
  màu `#2C3D22`) và dòng mono "THUÊ ĐỒ DÃ NGOẠI" (10px, `#5C6E47`, letter-spacing .14em).
- Giữa: menu pill — Trang chủ / Thuê đồ / Tra cứu đơn / Quản trị. Mục active nền `#557A2B`, chữ trắng,
  bo 10px; mục thường chữ `#3f4a32`. Trên mobile cho cuộn ngang.
- Phải: nút "Đăng nhập" (viền `#E3E8D6`, nền `#FBFCF7`, avatar tròn chữ cái đầu tên) + nút "Giỏ thuê"
  (nền cỏ, badge mono đếm số trên nền `#C97B36`).

### 1. Trang chủ (`home`)
- **Hero** (2 cột, `minmax(330px,1fr)` auto-fit): cột trái — chip mono "CHO THUÊ THEO NGÀY · CỌC LINH HOẠT",
  H1 "Mang cả khu trại / đi bất cứ đâu." (dòng 2 màu cỏ), đoạn mô tả, 2 nút "Xem thiết bị" (cỏ) +
  "Tra cứu đơn của tôi" (viền). Cột phải — **hero động** (xem mục Hero) + caption mono
  "Cùng một khu trại · đổi địa điểm theo mùa".
- **Dải số liệu**: 4 ô (auto-fit `minmax(150px,1fr)`) nối bằng viền `#E3E8D6`, nền card; số mono cỏ 26px.
  Nội dung: `120+` Bộ thiết bị · `4.9★` Đánh giá khách · `2.000+` Chuyến đi · `Nội thành` Giao nhận tận nơi.
- **Thiết bị nổi bật**: tiêu đề "Thiết bị nổi bật" + nhãn mono "ĐƯỢC THUÊ NHIỀU" + link "Xem tất cả →".
  Lưới card `minmax(248px,1fr)`, lấy 4 sản phẩm đầu (xem cấu trúc card ở màn Thuê đồ).
- **Thuê đồ trong 3 bước**: 3 card, mỗi card có số thứ tự (ô cỏ bo 10px), tiêu đề + mô tả.
  1) "Chọn đồ và ngày" 2) "Đặt giữ chỗ online" 3) "Nhận đồ và lên đường" (copy đầy đủ trong file).
- **CTA**: khối gradient `linear-gradient(120deg,#2C3D22,#3f5a2a 60%,#557A2B)` bo 24px, tiêu đề
  "Sẵn sàng cho chuyến đi tiếp theo?", nút trắng "Bắt đầu chọn đồ".
- **Reveal khi cuộn**: phần tử `[data-reveal]` mờ + trượt lên 18px → hiện khi vào viewport
  (IntersectionObserver, `opacity .6s / transform .6s cubic-bezier(.2,.7,.2,1)`). Trong React dùng
  Framer Motion `whileInView={{opacity:1,y:0}}` `initial={{opacity:0,y:18}}` `viewport={{once:true}}`.

### 2. Thuê đồ — Danh sách (`products`)
- Tiêu đề "Chọn đồ cho chuyến đi" + nhãn mono "KHO THIẾT BỊ".
- **Toolbar**: ô tìm kiếm (icon kính lúp, placeholder "Tìm lều, bếp, túi ngủ..."), select sắp xếp
  (Phổ biến nhất / Giá: thấp đến cao / Giá: cao đến thấp), và **segmented demo** đổi trạng thái
  (Bình thường / Đang tải / Lỗi) — dùng để minh hoạ 3 state, có thể bỏ trong production.
- **Chip danh mục** (pill): Tất cả / Lều trại / Túi ngủ & nệm / Bếp & nấu / Đèn & sáng / Bàn ghế.
  Active nền cỏ chữ trắng; thường viền `#d6ddc4` nền card.
- **Trạng thái**:
  - *Đang tải*: lưới skeleton (6 ô), khối xám `#e7ebdc` nhấp nháy (`@keyframes` opacity .55→1→.55, 1.4s).
  - *Lỗi*: card viền `#ecd3c4`, icon "!" tròn nền `#f7e7da`, tiêu đề "Không tải được danh sách", nút "Thử lại".
  - *Rỗng* (không khớp lọc): card viền đứt, emoji ⛺, "Chưa tìm thấy thiết bị phù hợp", nút "Xoá bộ lọc".
  - *Bình thường*: dòng đếm mono "{n} thiết bị" + lưới card.
- **Card sản phẩm**: ảnh (hiện là gradient theo sản phẩm — xem Assets), badge tồn kho góc trên-trái
  (mono; nếu ≤2 bộ thì nền lửa `#C97B36`, ngược lại nền `rgba(44,61,34,.72)`), nhãn danh mục mono góc
  dưới-trái trên lớp gradient tối. Thân: tên (700/15.5px, min-height 39px), mô tả ngắn (12.5px `#8a967a`),
  hàng cuối: giá mono 17px + "/ngày" và "cọc {x}" mono `#C97B36`. Hover: nhấc 4px + shadow.

### 3. Chi tiết sản phẩm (`detail`)
- Nút "← Quay lại danh sách". Bố cục 2 cột (`minmax(320px,1fr)`).
- **Gallery**: ảnh lớn (330px, bo 16px) + 4 thumbnail (gradient xoay góc khác nhau làm "góc chụp").
  Thumbnail đang chọn có viền cỏ + ring `#cfe0a8`.
- **Thông tin**: tên, "★ {rating}" mono, pill tồn kho; 2 ô — "Giá thuê" (mono cỏ 22px /ngày) và
  "Tiền cọc (hoàn lại)" (nền `#f7efe5`, mono lửa). Đoạn mô tả + bảng thông số (k/v) gạch trên `#E3E8D6`.
- **Lịch chọn ngày** (card): hiện **2 tháng**, nút ‹ › đổi tháng. Lưới 7 cột, hàng đầu T2..CN.
  - Ngày quá khứ: mờ `#cdd2c0`, không bấm được.
  - Ngày hết hàng: nền `#f0d9c4`, chữ `#b88a5a`, `cursor:not-allowed`.
  - Ngày chọn (nhận/trả): nền cỏ chữ trắng; ngày **trong khoảng**: nền `#dbe6c4`.
  - Bấm logic: chưa có start → set start; có start chưa end & ngày sau → set end; ngày trước/bằng → reset start.
  - Chú thích: Đã chọn / Trong khoảng / Hết hàng (3 ô màu).
- **Khoảng thuê + số lượng + tình trạng**: hiển thị "{dd/mm} → {dd/mm}", stepper số bộ (− / mono / +),
  dòng tình trạng:
  - chưa chọn đủ: nền `#f1f4ea`, "Hãy chọn ngày nhận và ngày trả để kiểm tra còn hàng."
  - hết trong khoảng: nền `#f6ddd6` chữ `#b3493a`, "Tiếc quá, khoảng ngày này đã hết hàng..."
  - còn: nền `#dcebc4` chữ `#3a5a1f`, "✓ Còn {n} bộ trống trong khoảng này."
  - Tạm tính = giá × số ngày × số bộ; "+ cọc {deposit × số bộ}". Nút "Thêm vào giỏ" (mờ/disabled nếu chưa hợp lệ).

### 4. Giỏ thuê + Checkout (`cart`)
- *Rỗng*: card viền đứt, emoji 🎒 (animation float), "Giỏ thuê đang trống", nút "Chọn thiết bị".
- *Có món* (2 cột): trái — danh sách dòng (ảnh 64px, tên, "{khoảng} · {n} ngày" mono, stepper số lượng,
  thành tiền mono + "cọc {x}", nút × xoá) + link "+ Thêm thiết bị khác".
  Phải (sticky) — **"Thông tin nhận đồ"**: input Họ và tên / Số điện thoại (`inputmode=tel`) / Địa chỉ +
  textarea Ghi chú. Tổng kết: "Tổng tiền thuê", "Tổng cọc (hoàn khi trả)" (mono lửa), "Trả khi nhận"
  (mono cỏ 18px). Hộp COD nền `#eef2e3`. Nút "Đặt giữ chỗ (COD)" (mờ nếu thiếu tên/SĐT/địa chỉ).
  Báo lỗi đỏ "Vui lòng nhập đủ tên, số điện thoại và địa chỉ." khi bấm mà thiếu.
- *Đặt thành công*: card giữa, icon ✓ tròn nền `#dcebc4`, "Đã đặt giữ chỗ!", mã đơn mono trong khung đứt
  (vd `BOP-2734`), tóm tắt Khách / Số món / Trả khi nhận (COD), 2 nút "Tra cứu đơn này" + "Về trang chủ".

### 5. Tra cứu đơn (`lookup`)
- Tiêu đề "Tra cứu đơn thuê". Card form: input "Mã đơn" (mono, vd "BOP-2048") + "Số điện thoại",
  nút "Tra cứu đơn" (đổi thành "Đang tra cứu..." khi loading ~700ms). Gợi ý mono: "Thử: BOP-2048 · 0905123456".
- *Không tìm thấy*: card viền `#ecd3c4`, "Không tìm thấy đơn".
- *Tìm thấy*: card — mã đơn mono cỏ + tên · SĐT + pill trạng thái; 3 ô Nhận / Trả / Tổng (COD);
  dòng "Thiết bị: ...". **Timeline trạng thái** dọc: Chờ xác nhận → Đã xác nhận → Đang thuê → Đã trả
  (dot cỏ khi đã qua, viền `#d6ddc4` khi chưa; mục hiện tại in đậm 800). Đơn "Đã huỷ" hiển thị 1 mốc đỏ.

### 6. Quản trị (`admin`) — tuỳ chọn
- Tiêu đề "Quản trị" + badge "DEMO". 4 ô thống kê (Đơn thuê / Chờ xác nhận / Đang hoạt động / Bộ trong kho).
- **Bảng "Đơn thuê gần đây"**: cột Mã đơn / Khách / SĐT / Ngày thuê / Trạng thái (select đổi được:
  Chờ xác nhận, Đã xác nhận, Đang thuê, Đã trả, Đã huỷ — màu pill theo trạng thái) / Tổng. Cuộn ngang trên mobile.
- **Bảng "Kho thiết bị"**: Thiết bị / Danh mục / Giá/ngày / Tồn kho (stepper − +) / Trạng thái
  (Sẵn sàng / Hết hàng).

### Phụ trợ chung
- **Toast** (sau khi thêm giỏ): nền `#2C3D22`, icon ✓ cỏ, text + nút "Xem giỏ"; tự ẩn sau ~3.2s.
- **Modal đăng nhập**: overlay mờ, card 380px — "Đăng nhập nhanh", input Tên + Số điện thoại, nút "Tiếp tục".
  Đăng nhập xong tự điền tên/SĐT vào form checkout.

---

## Interactions & Behavior
- **Điều hướng**: client-side qua `screen`; trong Inertia là các route/page riêng
  (`/`, `/thiet-bi`, `/thiet-bi/{id}`, `/gio-thue`, `/tra-cuu`, `/admin`). Cuộn lên đầu khi đổi màn.
- **Reveal khi cuộn**: Framer Motion `whileInView`, `once:true`, dịch 18px, 0.6s.
- **Hover card**: `translateY(-4px)` + shadow, transition `.25s`.
- **Lịch**: kiểm tra tồn kho theo từng ngày trong khoảng; chọn khoảng 2 chạm; min tồn kho trong khoảng
  giới hạn số bộ thuê được.
- **Loading/Lỗi/Rỗng**: mỗi danh sách có đủ 3 trạng thái (xem màn Thuê đồ); tra cứu có loading + not-found.
- **Validation checkout**: bắt buộc Tên + SĐT + Địa chỉ; nút disabled tới khi đủ; báo lỗi khi submit thiếu.
- **Responsive**: mọi lưới dùng auto-fit/`minmax`; menu nav cuộn ngang; bảng admin cuộn ngang;
  thiết kế ưu tiên mobile. Hit target ≥ 44px.
- **Bàn phím / focus**: `:focus-visible` viền `3px solid #7FA64B` offset 2px trên mọi control.

## State Management
Gợi ý state (prototype dùng một component; trong app nên tách theo trang + store nhẹ cho giỏ/đăng nhập):
- `screen` (route hiện tại) — thay bằng router/Inertia pages.
- Lọc danh sách: `search`, `cat`, `sort`, `demoState` (ok/load/err — chỉ để demo).
- Chi tiết: `pid`, `gIndex` (ảnh đang xem), `start`, `end` (YYYY-MM-DD), `qty`, `calMonth`.
- Giỏ: `cart[]` (mỗi dòng: pid, name, price, deposit, days, qty, start, end).
- Checkout: `form{name,phone,address,note}`, `placed` (kết quả đặt), `formErr`.
- Tra cứu: `look{code,phone}`, `lookState` (idle/loading/found/notfound), `lookResult`.
- Admin: `orders[]`, `products[]` (đổi trạng thái / tồn kho).
- Phiên: `user{name,phone}`, `showLogin`.
- **Data fetching thật** (Laravel/Inertia): danh sách + chi tiết sản phẩm; kiểm tra tồn kho theo khoảng
  ngày (server-side, chống trùng lịch); tạo đơn (sinh mã `BOP-xxxx`, lưu COD + cọc); tra cứu theo mã + SĐT.
  Tiền lưu dạng **đồng (số nguyên VND)**, format `Number(n).toLocaleString('vi-VN') + 'đ'`.

## Hero động ("cùng một khu trại, đổi địa điểm")
File `reference/BiomeHero.jsx`. Hiện dựng **thuần DOM + SVG**, một vòng `requestAnimationFrame` loop 30s,
4 biome (Đồng cỏ/Rừng/Núi/Biển) cross-fade theo `scenePos(t)`; lều + lửa trại + khói luôn hiện;
chi tiết riêng từng cảnh (bướm, đom đóm, đại bàng, thuyền buồm, ghế xếp, dây đèn...).
- **Co giãn**: khung `container-type: inline-size`, nội dung gốc 1600×900 scale bằng
  `transform: scale(calc(100cqw / 1600px))` (lưu ý: phải chia cho `1600px` để ra số không đơn vị).
- **Cách port**:
  - *Giữ DOM/SVG* (đơn giản nhất): copy `BiomeHero.jsx` thành component React thật, thay `React` global
    bằng import, giữ rAF trong `useEffect`. Không cần three.js.
  - *Dùng three.js / R3F* (stack đã nêu): dựng lại 4 biome bằng layer/sprite trong `<Canvas>`,
    cross-fade material theo cùng timeline 30s. Tham chiếu màu trời + thứ tự biome ở file gốc.
  - Tôn trọng `prefers-reduced-motion`: nên cho phép dừng animation.

## Design Tokens (tóm tắt để cấu hình Tailwind)
```js
// tailwind.config — extend.colors
grass: '#557A2B', grassLight: '#7FA64B', pine: '#2C3D22', moss: '#5C6E47',
ink: '#18230F', campfire: '#C97B36', card: '#FBFCF7', cardBorder: '#E3E8D6',
skyTop: '#a9d8f0', skyMid: '#dceff3',
// fontFamily: sans: ['"Be Vietnam Pro"', ...], mono: ['"Space Mono"', ...]
// borderRadius: card 16px, control 12px, pill 999px
```
Spacing/typography/shadow: xem các mục tương ứng ở trên.

## Assets
- **Ảnh sản phẩm**: prototype dùng **gradient placeholder** thay ảnh thật (mỗi sản phẩm một gradient +
  "mặt trời" mờ). Production cần **ảnh thật phong cách cắm trại/thiên nhiên** (lều, túi ngủ, bếp, đèn...),
  tỉ lệ ~3:2, bo 14-16px, có lớp gradient tối phía dưới để chữ nhãn dễ đọc.
- **Icon**: SVG inline (kính lúp, giỏ, lều, ✓...). Có thể thay bằng bộ icon của codebase (lucide...).
- **Font**: Be Vietnam Pro + Space Mono (Google Fonts). Tự host trong production nếu cần.
- **Emoji** dùng ở trạng thái rỗng (⛺ 🎒) — có thể thay bằng minh hoạ/icon của hệ thiết kế.
- Không dùng tài sản thương hiệu bên thứ ba.

## Files
- `reference/BopCamping.dc.html` — toàn bộ 6 màn hình (markup + logic). **Nguồn chân lý.**
- `reference/BiomeHero.jsx` — cảnh hero động.
- `reference/support.js` — runtime prototype (KHÔNG dùng trong production; chỉ để mở file HTML).
