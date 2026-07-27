# ADR — Bộ chạy test cho component React (Vitest + Testing Library)

- **Trạng thái:** Accepted
- **Ngày:** 2026-07-27
- **Người quyết:** chủ dự án (chốt trong phiên `/qa-engineer`)
- **Liên quan:** `bopcamping-uen0` (nút Zalo nổi), `.claude/rules/tech-strategy.md`

## Bối cảnh

Golden path trước đây chỉ có 4 cổng chất lượng: Pint (PHP format), ESLint + Prettier,
`tsc --noEmit`, và PHPUnit. **Không có bộ chạy test nào cho JavaScript/React.**

Điều đó ổn khi phần lớn logic nghiệp vụ nằm ở Laravel — availability, giá, voucher,
đơn hàng đều có test PHPUnit. Nhưng khi làm `ZaloFloatButton` (bopcamping-uen0) thì lộ
ra khoảng trống: **toàn bộ logic của tính năng nằm trong component React** —

- 0 tài khoản Zalo → ẩn nút; 1 → link mở thẳng; 2 → panel cho khách chọn;
- đóng panel bằng `Esc`, bấm ra ngoài, hoặc sau khi chọn;
- thuộc tính a11y (`aria-expanded`, `aria-haspopup`, `role="menu"`).

PHPUnit chỉ với tới được hợp đồng dữ liệu (shared prop `site.zalo_*`), không chạm được
nhánh hiển thị. Cách duy nhất để kiểm là mở trình duyệt bấm tay — không lặp lại được,
không chặn được hồi quy.

## Quyết định

Thêm **Vitest + @testing-library/react + jsdom** làm bộ chạy test component, và đưa
`npm test` thành cổng chất lượng thứ năm.

- Cấu hình riêng ở `vitest.config.ts` (không nhét vào `vite.config.js`, vì
  `laravel-vite-plugin` cần manifest/dev-server — vô nghĩa lúc chạy test).
- Test đặt ở `tests/js/`. `phpunit.xml` chỉ quét `tests/Unit` + `tests/Feature` nên
  hai bộ không giẫm chân nhau.
- `tsconfig.json` include thêm `tests/js` để `tsc` bắt lỗi kiểu trong test.
- `npm run lint` mở rộng sang `tests/js` để test chịu chung chuẩn format.

## Phương án đã cân nhắc

| Phương án | Ưu | Nhược | Kết luận |
|---|---|---|---|
| **Vitest + Testing Library** | Dùng lại đúng pipeline Vite/esbuild sẵn có → cấu hình ít, chạy ~1s; test theo hành vi người dùng (role, label) thay vì chi tiết cài đặt | Chạy trên jsdom nên không thấy layout thật, z-index, overlap | **Chọn** |
| Playwright E2E | Trình duyệt thật, bắt được cả layout/overlap/animation | Cần cài browser, chạy chậm, khó đưa vào pre-commit; nặng so với quy mô 1 shop | Chưa làm — cân nhắc lại nếu có nhiều luồng E2E |
| Giữ nguyên, chỉ test PHPUnit | Không thêm dependency | Nhánh hiển thị và tương tác bàn phím/chuột hoàn toàn không có lưới an toàn | Từ chối |

## Hệ quả

**Tích cực**
- Logic component có test hồi quy chạy trong ~1.2s.
- Test viết theo vai trò (`getByRole`) nên đồng thời khoá luôn a11y: đổi `role="menu"`
  hay bỏ `aria-expanded` là test đỏ.
- Mở đường cho các component tương tác khác (modal đăng nhập, lịch chọn ngày, giỏ hàng).

**Tiêu cực / đánh đổi**
- Thêm 5 devDependency (`vitest`, `jsdom`, 3 gói `@testing-library/*`). Không ảnh hưởng
  bundle production — `npm audit --omit=dev` vẫn 0 lỗ hổng.
- jsdom **không** kiểm được layout: lỗi kiểu "nút Góp ý che mũi tên hero"
  (`bopcamping-bdk3`) vẫn phải bắt bằng đo đạc trên trình duyệt thật.
- Test phải mock `usePage` (Inertia) và `framer-motion`. Mock framer-motion là chủ ý:
  giữ animation thật sẽ khiến phần tử nán lại trong DOM lúc exit và làm test chớp tắt.

## Quy ước khi viết test component

1. Truy vấn theo **vai trò và nhãn** (`getByRole('button', { name: … })`), không dùng
   `data-testid` hay class CSS.
2. Mock ranh giới ngoài (Inertia `usePage`, `router`, animation), **không** mock logic
   đang cần kiểm.
3. Mỗi test tự dựng state riêng — không dùng chung biến giữa các test.
4. Sau khi viết xong, **thử phá logic** (mutation thủ công) để chắc test thật sự đỏ.
   Test không bao giờ đỏ là test vô dụng.

## Cổng chất lượng sau ADR này

```bash
php artisan test        # PHPUnit — logic nghiệp vụ Laravel
npm test                # Vitest  — logic component React   ← MỚI
npx tsc --noEmit        # kiểu, gồm cả tests/js
npm run lint            # ESLint + Prettier, gồm cả tests/js
./vendor/bin/pint --test
npm run build
```
