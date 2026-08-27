# Sổ nhánh — BopCamping

Nơi tra **nhánh nào đang ở đâu**. Mỗi nhánh một dòng: `tên nhánh — chức năng — ngày triển khai`.

> Quy ước nhánh và luồng làm việc nằm ở [CLAUDE.md](CLAUDE.md) mục *Workflow*.
> `feat/scaffold-laravel` = **Production** (bopcamping.com) · `develop` = **Staging** (staging.bopcamping.cloud).
> Push vào nhánh nào là tự deploy môi trường đó.

---

## Production

Đang chạy thật tại **bopcamping.com**.

| Nhánh | Chức năng | Ngày lên production |
|---|---|---|
| `release/auth-hardening-2026-08` | **Đợt siết đăng nhập.** Chặn chiếm tài khoản bằng SĐT; đăng nhập thay khách cho admin; tài khoản chỉ-có-SĐT; email checkout không còn đổi email đăng nhập; menu tài khoản ở header. Gộp từ `feature/auth-hardening-impersonation` + `feature/phone-only-account-recovery`. | 27/08/2026 |

**Điểm revert** của đợt trên: `e988721` rồi `b10eda9` — mới trước, cũ sau. Chi tiết và
lệnh đầy đủ ở [CLAUDE.md](CLAUDE.md) mục *Đợt đang chạy trên production*.

Mọi thứ trước 27/08/2026 chưa lập sổ — tra bằng:

```bash
git log --merges --oneline feat/scaffold-laravel
```

---

## Staging

Đã lên **staging.bopcamping.cloud**, **chưa** lên production.

| Nhánh | Chức năng | Ngày lên staging |
|---|---|---|
| `feature/anh-san-pham-net` | Ảnh sản phẩm nét hơn | 04/08/2026 |
| `feature/dia-chi-cua-hang-map` | Địa chỉ + link Google Maps cho từng cơ sở, hiện khi khách tự đến lấy (bopcamping-n0db) | 06/08/2026 |
| `feature/hinh-thuc-giao-nhan` | Khách chọn hình thức giao/nhận ở checkout (bopcamping-z3ug) | 06/08/2026 |

### ⚠️ Commit làm thẳng trên `develop`, chưa có nhánh riêng

Không thuộc nhánh nào nên rất dễ thất lạc khi `develop` bị reset. **`develop` chỉ dùng
để test — reset là mất.**

| Commit | Nội dung | Ngày |
|---|---|---|
| `4f3a3d9` | **fix(payment)**: QR đòi đúng số CÒN THIẾU + siết bảo mật trang tra cứu (bopcamping-pew1) | 15/08/2026 |
| `938d922` + `97f5fe2` | test(order): khoá mốc `collected_at`/`collected_by` ở cả 2 đường thu đồ (bopcamping-54ie) | 07/08/2026 |
| `85bbf4b` | docs(spec): lượt trả + hoàn cọc có kiểm đồ (bopcamping-c9aw) | 10/08/2026 |
| `d140756` | chore(skills): giữ định dạng `skill-rules.json` khi thêm skill seo | 12/08/2026 |
| `f235343` · `be0dbfe` | docs(qa): checklist đăng nhập — nội dung **đã có** ở nhánh auth, hai commit này chỉ là bản trùng trên develop | 26–27/08/2026 |

`4f3a3d9` là fix thật, nằm một mình trên staging từ 15/08. Nên tách ra một nhánh riêng
rồi đưa lên production, hoặc chốt là bỏ.

---

## Cách ghi thêm

Mỗi lần merge một nhánh lên staging hoặc production thì thêm một dòng vào bảng tương ứng.
Nhánh lên production thì **chuyển dòng** từ bảng Staging sang bảng Production và đổi ngày —
đừng để một nhánh nằm ở cả hai bảng.

Tra lại bất cứ lúc nào nếu sổ lạc hậu:

```bash
# nhánh đã lên staging nhưng chưa lên production
git branch -r --merged origin/develop --no-merged origin/feat/scaffold-laravel

# commit làm thẳng trên develop, chưa có ở production
git log --no-merges --oneline feat/scaffold-laravel..develop
```
