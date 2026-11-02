# ADR: Thống kê truy cập nội bộ + báo Telegram

Ngày: 02/11/2026 · Issue: beads (thống kê truy cập + báo Telegram) · Nhánh: `feature/thong-ke-truy-cap-telegram`

## Quyết định
- Ghi truy cập vào bảng `visit_events` của chính shop (không dùng dịch vụ ngoài): `page_view`,
  `product_view` (server-side, middleware `RecordVisit`) và `contact_click` (client gửi beacon
  `POST /track/lien-he` từ `lib/analytics.ts`, cùng chỗ đã bắt click Zalo cho GA4/Pixel).
- Nhận diện khách ẩn danh bằng cookie `bop_vid` (UUID, 1 năm). Không ghi IP, không ghi user agent.
- Báo Telegram qua `TelegramNotifier` → job `SendTelegramMessage` (queue) cho: bấm Zalo/gọi/Messenger,
  đơn mới, khách mới đăng ký. KHÔNG báo từng lượt xem trang.
- Không đếm: bot (theo user agent), admin, shipper, người đang xem-hộ khách, trang `/admin` `/shipper`.
- Dữ liệu tự xoá sau 180 ngày (`model:prune` hằng ngày lúc 03:00).

## Dữ liệu rời hệ thống (Data Routing)
| Đích | Dữ liệu | Khi nào |
|---|---|---|
| Telegram Bot API (`api.telegram.org`) → chat của chủ shop | Tên + SĐT khách (chỉ khi đã đăng nhập / vừa đặt đơn / vừa đăng ký), trang đang xem, nguồn, mã đơn, tổng tiền | Các sự kiện liệt kê ở trên |

- Token và chat id nằm ở `.env` (`TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`), thiếu thì tắt im lặng.
- Job không log exception kết nối (message chứa URL có token).
- Đã bổ sung Chính sách bảo mật (mục 5): migration `2026_11_02_000002`.

## Giới hạn đã biết
- Zalo không báo về web việc khách có nhắn hay không → chỉ biết "đã bấm nút".
- Một khách bấm cùng kênh nhiều lần trong 10 phút chỉ báo Telegram 1 lần (vẫn ghi đủ vào DB).
- Khách xoá cookie / đổi trình duyệt = khách mới; số "khách truy cập" là ước lượng.
