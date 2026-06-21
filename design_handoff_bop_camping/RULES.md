# BỐP CAMPING — Quy tắc thiết kế (cho Claude Code)

> Đọc file này TRƯỚC KHI dựng bất kỳ màn hình nào. Đây là "bản hợp đồng thiết kế": mọi màn hình mới
> phải tuân theo đúng các token và pattern dưới đây để đồng bộ với các màn đã có.
> File tham chiếu giao diện đầy đủ: `reference/BopCamping.dc.html` (markup + logic 6 màn hình).
> Cảnh hero động: `reference/BiomeHero.jsx`. (`support.js` chỉ là runtime của prototype — KHÔNG dùng trong production.)

---

## 0. Cách dùng tài liệu này với Claude Code

1. Copy cả thư mục `design_handoff_bop_camping/` vào repo của bạn (hoặc cạnh repo).
2. Mở Claude Code trong repo và ra lệnh, ví dụ:
   > "Đọc `design_handoff_bop_camping/RULES.md` và `README.md`. Dựng màn hình **[tên màn hình mới]**
   > trong app Laravel + Inertia + React + TS + Tailwind của tôi, tuân theo đúng token màu/chữ/bo góc
   > và các pattern component trong tài liệu. Tham chiếu cách viết ở `reference/BopCamping.dc.html`."
3. Với mỗi màn hình mới, yêu cầu Claude Code: dùng lại Header/Footer chung, đủ 3 trạng thái
   (loading / lỗi / rỗng), responsive mobile, focus bàn phím — xem mục 7 & 8.

Stack đích đã chốt: **Laravel 12 + Inertia + React + TypeScript + Tailwind v3 + Framer Motion + three.js/R3F.**

---

## 1. Màu (dùng đúng hex — KHÔNG tự chế màu mới)

| Token | Hex | Dùng cho |
|---|---|---|
| `grass` (nhấn chính) | `#557A2B` | nút chính, link, active, số liệu mono |
| `grassLight` | `#7FA64B` | viền focus, nhấn phụ |
| `pine` | `#2C3D22` | tiêu đề brand, nền CTA tối, toast |
| `moss` | `#5C6E47` | chữ phụ, label |
| `ink` | `#18230F` | tiêu đề + body chính |
| `campfire` (nhấn ấm — DÙNG ÍT) | `#C97B36` | badge cọc, badge "sắp hết", nhãn DEMO |
| `card` | `#FBFCF7` | nền thẻ, input |
| `cardBorder` | `#E3E8D6` | viền thẻ trung tính |
| ô dữ liệu nhạt | `#eef2e3` / `#f1f4ea` | nền ô spec, stepper |

**Nền toàn trang** = gradient bầu trời → cỏ, cố định theo viewport:
```css
background: linear-gradient(180deg,#a9d8f0 0%,#c6e6f3 14%,#dceff3 34%,#e7f1ec 60%,#eef4e7 100%);
background-attachment: fixed;
```
Chrome xanh trời mát: Header `rgba(221,239,250,.78)` + `backdrop-filter:blur(14px)`, viền `#c2dcec`;
Footer `rgba(214,236,247,.55)`. Selection `#bfe0f0`/chữ `#16324a`; scrollbar thumb `#a9cee2`.

Trạng thái (pill): Chờ xác nhận `#9a7a2a`/`#fbf2d8` · Đã xác nhận `#2a6ea0`/`#dceaf6` ·
Đang thuê `#3a5a1f`/`#dcebc4` · Đã trả `#5C6E47`/`#e7ecdc` · Đã huỷ `#b3493a`/`#f6ddd6`.

## 2. Typography
- **Sans**: `Be Vietnam Pro` (400-800) — tiêu đề + thân.
- **Mono**: `Space Mono` (400/700) — **mọi giá tiền, số lượng, mã đơn, nhãn dữ liệu, ngày tháng**.
- Cỡ: H1 hero `clamp(38px,6vw,68px)/1.02/800`; H1 trang `clamp(26px,3.4vw,36px)/800`;
  H2 `clamp(24px,3vw,32px)/800`; body `15-20px/1.6`; label nhỏ `11-13px`; giá mono `15-22px/700`.
- letter-spacing: tiêu đề `-.01em`→`-.025em`; label uppercase `.05em`; mono nhãn `.04-.14em`.
- Nhãn nhỏ kiểu "tag" = mono, UPPERCASE, màu `#C97B36` hoặc `#5C6E47`, letter-spacing `.1em`.

## 3. Bo góc (một hệ duy nhất)
- Card / section: **16px** (hero & slideshow 20px, CTA lớn 24px).
- Control (nút, input, select): **11-13px**.
- Chip / pill / badge: **999px**. Stepper: 9-10px.

## 4. Shadow
- Card hover: `0 20px 40px -22px rgba(44,61,34,.5)` + `translateY(-4px)`.
- Nút chính: `0 12px 24px -10px rgba(85,122,43,.6)` (trên nền ảnh: `0 16px 34px -12px rgba(0,0,0,.6)`).
- Hero/slideshow: `0 30px 60px -24px rgba(44,61,34,.5)`. Toast: `0 16px 40px -12px rgba(44,61,34,.6)`.

## 5. Spacing & layout
- Khung: `max-width:1200px` (chi tiết 1120, lookup 640, checkout 1060), padding ngang 20px.
- Lưới card: `repeat(auto-fill,minmax(248px,1fr))`, gap 18px. Section 2 cột: `minmax(330px,1fr)`, gap 40px.
- Khoảng cách dọc giữa section: 46-54px.
- Hit target ≥ 44px. Mọi lưới dùng `auto-fit/auto-fill + minmax` để tự co về mobile.

## 6. Quy ước bắt buộc
- **KHÔNG dùng em-dash.** Thay bằng `→`, `·`, `-`.
- Mono cho mọi con số có nghĩa dữ liệu; sans cho văn xuôi.
- Một màu nhấn duy nhất (cỏ `#557A2B`); lửa trại `#C97B36` rất tiết chế.
- Tiền: số nguyên VND, format `Number(n).toLocaleString('vi-VN') + 'đ'`.
- Copy tiếng Việt, giọng thân thiện, cụ thể (xưng "tụi mình"). Không emoji trừ trạng thái rỗng.

## 7. Component patterns (tái sử dụng cho màn hình mới)

**Header (chung mọi trang)** — sticky + blur; logo (ô cỏ 38px bo 11px + lều SVG) + "BỐP CAMPING" 800/17px
`#2C3D22` và dòng mono "THUÊ ĐỒ DÃ NGOẠI"; menu pill (active nền cỏ chữ trắng); nút Đăng nhập + Giỏ thuê
(badge mono trên nền `#C97B36`). Mobile: menu cuộn ngang.

**Footer (chung)** — 4 cột (giới thiệu / Thiết bị / Hỗ trợ / Liên hệ), nền `rgba(214,236,247,.55)`,
SĐT mono cỏ, dòng bản quyền mono.

**Nút**: chính = nền cỏ chữ trắng bo 13px; phụ = viền `#cdd6b6` nền card; trên ảnh = viền trắng mờ +
nền `rgba(255,255,255,.12)` + blur. Hover nhấc 2px.

**Card sản phẩm**: ảnh (gradient/ảnh thật) bo trên 14px + lớp gradient tối dưới + nhãn danh mục mono +
badge tồn kho góc trên-trái (≤2 bộ → nền lửa). Thân: tên 700/15.5px, mô tả 12.5px `#8a967a`,
hàng giá mono 17px "/ngày" + "cọc {x}" mono lửa. Hover nhấc 4px + shadow.

**Input / select / textarea**: cao 46-48px, bo 11px, viền `#E3E8D6`, nền `#fff`/`#FBFCF7`.

**Chip danh mục / pill**: bo 999px; active nền cỏ chữ trắng, thường viền `#d6ddc4` nền card.

**Stepper số lượng**: viền `#E3E8D6` bo 9-10px, nút − / + nền `#f1f4ea` chữ cỏ, số ở giữa mono.

**Badge trạng thái**: pill bo 999px theo bảng màu mục 1.

**Toast**: nền `#2C3D22`, icon ✓ cỏ, tự ẩn ~3.2s. **Modal**: overlay mờ + card 380px bo 20px.

**Lịch chọn ngày** (nếu màn mới cần): 2 tháng, ngày quá khứ mờ, hết hàng nền `#f0d9c4`,
chọn nền cỏ, trong khoảng nền `#dbe6c4`; chọn khoảng bằng 2 chạm.

## 8. Trạng thái & tương tác (mọi màn hình mới phải có)
- **Reveal khi cuộn**: phần tử mờ + trượt lên 18px, 0.6s `cubic-bezier(.2,.7,.2,1)`, chỉ chạy 1 lần.
  React: Framer Motion `initial={{opacity:0,y:18}} whileInView={{opacity:1,y:0}} viewport={{once:true}}`.
- **Danh sách**: luôn có *loading* (skeleton `#e7ebdc` nhấp nháy), *lỗi* (card viền `#ecd3c4`, nút Thử lại),
  *rỗng* (card viền đứt + nút hành động).
- **Form**: validate bắt buộc, nút disabled (nền `#c4cfae`) tới khi đủ, báo lỗi đỏ khi submit thiếu.
- **Hover card**: `translateY(-4px)` + shadow, transition `.25s`.
- **Focus bàn phím**: `:focus-visible { outline: 3px solid #7FA64B; outline-offset: 2px; }` trên mọi control.
- **Responsive**: ưu tiên mobile; bảng cho cuộn ngang; menu cuộn ngang.
- **Đổi route**: cuộn lên đầu trang KHI và CHỈ KHI đổi màn (đừng cuộn khi state khác thay đổi —
  đây là bug đã từng gặp: so sánh `prevState.screen`, không phải mọi update).

## 9. Animation (tinh tế, có chủ đích — không lạm dụng)
- Slideshow hero: đổi ảnh ~4.8s, trượt ngang `.8s cubic-bezier(.22,.7,.2,1)` + Ken Burns zoom nhẹ 6s;
  tạm dừng khi hover / không ở trang chủ / tab ẩn.
- Cảnh 3D `BiomeHero`: loop 30s qua 4 biome, gió chung làm cỏ/hoa/cây đung đưa cùng nhịp.
  Co giãn bằng `container-type:inline-size` + `transform:scale(calc(100cqw / 1600px))` (phải có đơn vị `px`).
  Tôn trọng `prefers-reduced-motion` (cho phép dừng).

## 10. Cấu hình Tailwind gợi ý
```js
// tailwind.config.js — theme.extend
colors: {
  grass:'#557A2B', grassLight:'#7FA64B', pine:'#2C3D22', moss:'#5C6E47',
  ink:'#18230F', campfire:'#C97B36', card:'#FBFCF7', cardBorder:'#E3E8D6',
  skyTop:'#a9d8f0', skyMid:'#dceff3',
},
fontFamily: { sans:['"Be Vietnam Pro"','system-ui','sans-serif'], mono:['"Space Mono"','monospace'] },
borderRadius: { card:'16px', control:'12px', pill:'999px' },
```

## 11. Map màn hình → route (đề xuất cho Inertia)
| Màn | Route | Page |
|---|---|---|
| Trang chủ | `/` | `Home` |
| Danh sách thiết bị | `/thiet-bi` | `Products` |
| Chi tiết | `/thiet-bi/{id}` | `ProductDetail` |
| Giỏ + Checkout | `/gio-thue` | `Cart` |
| Tra cứu đơn | `/tra-cuu` | `OrderLookup` |
| Quản trị | `/admin` | `Admin` |

Khi thêm màn MỚI (vd. "Hồ sơ khách", "Chi tiết đơn admin", "Trang FAQ"): dùng lại Header/Footer,
áp token mục 1-6, dùng component pattern mục 7, đủ trạng thái mục 8. Bám cách viết trong
`reference/BopCamping.dc.html` (tìm theo comment `<!-- ============ TÊN MÀN ============ -->`).
