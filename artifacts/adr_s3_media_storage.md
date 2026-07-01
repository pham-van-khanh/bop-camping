# ADR: Lưu trữ ảnh/video media trên S3 (tuỳ chọn, giữ local cho dev)

**Status**: Accepted
**Ngày**: 2026-07-01
**Bead**: bopcamping-u2h

## Context

Toàn bộ ảnh/video của shop (thumbnail + ảnh phụ sản phẩm, media camping-spot,
ảnh category, banner, ảnh review) đang lưu ở disk Laravel tên `public`
(driver `local`, root `storage/app/public`, serve qua symlink
`public/storage`). Đây đúng với golden path hiện tại trong
`tech-strategy.md` ("File/Ảnh sản phẩm: Laravel Storage local disk").

User yêu cầu explicit: chuyển sang lưu trên S3, và cấp key mẫu qua
`.env.example` để tự điền `.env` thật. Đây là thay đổi có chủ đích vào
golden path (`tech-strategy.md` sẽ cập nhật theo ADR này).

## Decision

1. **Không đổi cứng sang S3** — thêm S3 làm *tuỳ chọn* qua biến env mới
   `MEDIA_DISK` (giá trị `local` | `s3`, mặc định `local`). Máy dev không có
   AWS key vẫn chạy được không đổi gì.
2. Đổi tên logic disk cho toàn bộ media nghiệp vụ (khác Laravel disk `public`
   built-in) thành `media` trong `config/filesystems.php`, driver là
   `local` hoặc `s3` tuỳ `MEDIA_DISK`. Sửa lại toàn bộ nơi đang hardcode
   chuỗi `'public'` (5 controller + 1 model) sang `'media'`.
3. Toàn bộ đọc URL public phải luôn qua `Storage::disk('media')->url(...)`
   — không dùng `Storage::url()` không chỉ định disk (facade forward về
   disk **default**, có thể lệch với disk media thật khi 2 disk khác
   driver). Đây là một lỗi ẩn có sẵn (code viết vào disk `public` nhưng đọc
   qua disk default) — vô hại khi cả hai đều local + cùng quy ước URL
   `/storage/...`, nhưng sẽ vỡ ngay khi bật S3. Fix luôn trong ADR này.
4. Dùng driver `s3` chuẩn của Laravel (qua `league/flysystem-aws-s3-v3`),
   tương thích luôn với S3-compatible khác (DigitalOcean Spaces, Cloudflare
   R2, MinIO tự host) qua `AWS_ENDPOINT` + `AWS_USE_PATH_STYLE_ENDPOINT` —
   không lock-in AWS thật.
5. Secrets (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, ...) chỉ khai
   field rỗng trong `.env.example`, không có giá trị thật — đúng
   `security.md` (No hardcoded secrets).

## Alternatives Considered

- **Đổi hẳn `FILESYSTEM_DISK=s3` làm default của cả app** (dùng disk `s3`
  có sẵn của Laravel thay vì tạo disk `media` riêng): bị loại vì
  `FILESYSTEM_DISK` còn ảnh hưởng các phần khác của framework
  (queue failed jobs export, v.v.) — tách riêng disk `media` cho đúng
  Single Responsibility, không đá chéo sang phần không liên quan.
- **Giữ tên disk `public`, chỉ đổi driver theo env**: cân nhắc nhưng tên
  `public` dễ nhầm với disk `public` gốc của Laravel (đang tồn tại sẵn
  trong `filesystems.php`, dùng bởi framework mặc định) — đặt tên `media`
  tách bạch rõ "đây là disk nghiệp vụ của app", giảm nhầm lẫn khi đọc code.

## Cấu trúc folder trên S3

Bucket S3 (do user tự tạo) có 2 folder gốc `admin/` (media do admin quản lý)
và `user/` (media do khách tự tải lên). Path lưu trong DB (`->store($dir,
'media')`) khớp trực tiếp theo prefix này:

| Nguồn | Prefix |
|-------|--------|
| Sản phẩm (thumbnail + ảnh/video phụ) | `admin/products` |
| Category | `admin/categories` |
| Banner | `admin/banners` |
| Camping spot | `admin/camping-spots` |
| Review khách hàng | `user/reviews` |

Chỉ áp dụng cho upload **mới** — file cũ giữ nguyên path đã lưu trong DB
(vd `products/xxx.jpg` không có prefix), vẫn đọc đúng vì `Storage::disk(
'media')->url($path)` dùng path lưu sẵn, không suy diễn lại.

## Sự cố thật gặp khi kết nối bucket thật (đã fix)

Khi user điền `.env` thật và bật `MEDIA_DISK=s3`, ghi file lên bucket thất
bại âm thầm (`throw=>false` nên không có exception) — bật tạm `throw=>true`
để lấy lỗi thật: `AccessControlListNotSupported: The bucket does not allow
ACLs`. Nguyên nhân: disk config có `'visibility' => 'public'`, khiến
Flysystem gắn ACL `public-read` cho mỗi lần `putObject` — nhưng bucket dùng
**Object Ownership = "Bucket owner enforced"** (mặc định của bucket S3 mới
từ 2023), chế độ này cấm hoàn toàn ACL ở object. Đã xoá key `'visibility'`
khỏi nhánh s3 trong `config/filesystems.php` — quyền đọc public giờ hoàn
toàn do **Bucket Policy** phía AWS quyết định (không qua ACL). Verify: PUT
thành công, object đọc public trả 200, đã test upload thật qua Admin UI và
xoá lại sau khi xác nhận.

## Consequences

- Dev machine không cấu hình gì thêm vẫn chạy như cũ (`MEDIA_DISK` không
  set → default `local`).
- Muốn dùng S3: set `MEDIA_DISK=s3` + điền đủ `AWS_*` trong `.env`, không
  cần sửa code.
- `tech-strategy.md` mục "File/Ảnh sản phẩm" cập nhật: local là dev
  default, S3 là lựa chọn prod khi cấu hình qua env.
- Test suite phải đổi `Storage::fake('public')` → `Storage::fake('media')`
  ở 5 file test hiện có, để không viết file thật ra đĩa khi chạy test.
