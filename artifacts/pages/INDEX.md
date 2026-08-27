# Artifact pages — BopCamping

Thư mục này giữ **mã nguồn HTML** của các trang đã publish thành Artifact trên claude.ai.

Lý do có nó: link artifact chỉ nằm trong đoạn chat sinh ra nó. Mất chat là mất link, và
các dự án khác cũng publish artifact nên gallery trên claude.ai lẫn hết vào nhau. Bảng dưới
là danh sách của **riêng dự án này**, đi theo repo.

> Gallery claude.ai chưa có tính năng thư mục — nên chỗ phân loại theo dự án là file này,
> không phải trên đó.

## Đang dùng

| File | Là gì | URL |
|---|---|---|
| [so_deploy.html](so_deploy.html) | **Sổ deploy** — nhánh nào đang chạy ở Production / Staging, ngày deploy, commit để revert từng dòng. Phải cập nhật MỖI LẦN deploy. | https://claude.ai/code/artifact/96e44ef5-828e-4d6d-93af-03a6ee5faba4 |
| [qa_run_auth_2026-08.html](qa_run_auth_2026-08.html) | Kết quả chạy quality gate + tự test đợt siết đăng nhập (bopcamping-bqsv, bopcamping-kuhg). Chỉ đọc. | https://claude.ai/code/artifact/a67ab4d6-13a7-40de-b14c-ad15905a004e |
| [qa_checklist_auth_staging_2026-08.html](qa_checklist_auth_staging_2026-08.html) | Checklist 46 ca **tích tay** khi test staging: đạt / không đạt / chưa hài lòng + ghi chú. Trang tự lưu phiên bản mới mỗi lần tích. | https://claude.ai/code/artifact/de5ee57c-35a3-4137-b64a-425c048c6392 |

## Chưa publish

| File | Là gì | URL |
|---|---|---|
| [fix_auth_takeover_2026-08.html](fix_auth_takeover_2026-08.html) | Mô tả 5 chỗ đã vá sau khi soát lại đợt siết đăng nhập: hạng mục, chức năng, hành vi mới, test canh từng chỗ. Kèm output thật của đòn tấn công trước/sau khi vá. | *chưa publish — bộ lọc an toàn chặn lệnh publish ở phiên 27/08* |

## Đã thay thế

| File | Ghi chú |
|---|---|
| [qa_checklist_auth_2026-08_static.html](qa_checklist_auth_2026-08_static.html) | Bản checklist HTML tĩnh đầu tiên (commit `be0dbfe`). Giữ lại làm dấu vết; bản tích tay ở trên thay cho nó. |

## Cách cập nhật một trang

Sửa file HTML trong thư mục này, rồi publish lại **kèm đúng URL cũ** để giữ nguyên link:

```
Artifact(file_path: "artifacts/pages/<tên file>.html",
         url: "<URL trong bảng trên>")
```

Bỏ `url` là đẻ ra artifact MỚI với link khác — người đang giữ link cũ sẽ không thấy cập nhật.

## Lưu ý riêng cho trang tích tay

`qa_checklist_auth_staging_2026-08.html` dùng khả năng `artifact` (trang tự publish phiên bản
mới của chính nó). Cái người dùng tích **nằm trong bản đã publish**, không nằm trong file repo —
file repo luôn có state rỗng.

Nên **trước khi publish lại trang đó, phải đọc bản đang publish và lấy state ra trước**, không thì
ghi đè mất hết cái người ta đã tích:

```
Artifact(action: "read", url: "<URL>")   # lấy nội dung thẻ <script id="state">
```

Rồi chép state đó vào file repo trước khi publish. Nếu state đang rỗng thì publish thẳng cũng được.

## Quy ước đặt tên

Theo bảng artifact ở [CLAUDE.md](../../CLAUDE.md): `<loại>_<chủ đề>_<YYYY-MM>.html`.
Trang chỉ đọc dùng `qa_run_`, `report_`; trang tương tác dùng `qa_checklist_`, `tracker_`.
