---
name: seo
description: SEO cho BopCamping — thẻ meta, JSON-LD, sitemap, canonical. Dùng khi thêm/sửa trang khách, đổi tiêu đề-mô tả, thêm structured data, hoặc khi audit SEO. Bám đúng SeoService + app.blade.php của dự án này.
---

# SEO — BopCamping

Site là **Laravel + Inertia (SPA)**. Nội dung do React render, nên HTML thô gần như
rỗng. Google có render JS nhưng ở *lượt sau*, còn thẻ `<head>` thì đọc ngay lượt đầu.

> **Hệ quả**: mọi thứ SEO quan trọng phải nằm trong `<head>` do server sinh
> (`resources/views/app.blade.php`), KHÔNG được để React tự đặt sau khi mount.

## Kiến trúc — ai chịu trách nhiệm gì

| Tầng | File | Vai trò |
|---|---|---|
| Nguồn dữ liệu | `app/Services/SeoService.php` | **Single source** dựng prop `seo`. Đừng tự ghép mảng seo trong controller. |
| Xuất `<head>` | `resources/views/app.blade.php` | Đọc prop `seo` + `seoSite`, in meta/OG/Twitter/JSON-LD |
| Sitemap | `app/Http/Controllers/Shop/SitemapController.php` | Liệt kê URL, có cache |
| Chặn index | `public/robots.txt` | Chặn trang riêng tư (`/admin`, `/gio-thue`, `/tai-khoan`…) |

### API của `SeoService`

```php
$this->seo->page(
    title: 'Tên trang',           // brand xuất hiện ĐÚNG 1 LẦN — xem Cạm bẫy #2
    description: 'Mô tả 120–160 ký tự',
    image: url('/images/…'),      // tuyệt đối, dùng cho og:image
    url: url()->current(),
    jsonld: $this->seo->breadcrumb($crumbs),   // hoặc mảng schema tự dựng
);
```

## Checklist khi thêm TRANG KHÁCH mới

- [ ] Controller trả prop `seo` qua `SeoService::page()` — **không** để rơi vào mặc định
      site-wide (rơi mặc định = trùng hệt trang chủ với Google)
- [ ] `title` riêng, brand xuất hiện **đúng 1 lần** (xem Cạm bẫy #2), tổng ~60 ký tự
- [ ] `description` 120–160 ký tự, viết cho người đọc chứ không nhồi từ khoá
- [ ] `image` là URL **tuyệt đối**
- [ ] `BreadcrumbList` nếu trang nằm trong phân cấp
- [ ] Thêm URL vào `SitemapController` nếu là trang public muốn index
- [ ] Nếu trang riêng tư → thêm vào `robots.txt`, **đừng** bỏ vào sitemap
- [ ] Viết test (xem mục Test bên dưới)

## Cạm bẫy đã mắc trong dự án này

### 1. JSON-LD khai không khớp nội dung nhìn thấy — lỗi nặng nhất

Google yêu cầu structured data phải ứng với nội dung **hiện trên chính trang đó**.

Đã mắc thật: `FAQPage` hardcode 4 câu trong `app.blade.php`, trong khi FAQ hiện trên
trang lấy từ DB và là 8 câu khác hẳn → **0/4 câu khớp**. Lại còn nằm ở layout chung nên
xuất trên mọi trang, kể cả trang sản phẩm và trang chính sách vốn không có FAQ nào.

**Quy tắc:**
- Schema mô tả nội dung của trang (`FAQPage`, `Product`, `Review`…) → chỉ xuất ở trang
  THẬT SỰ có nội dung đó, và sinh từ **cùng nguồn dữ liệu** đang render ra màn hình.
- Schema mô tả tổ chức (`Organization`, `WebSite`, `LocalBusiness`) → xuất site-wide được.
- Có 2 nguồn sự thật cho cùng một nội dung = sớm muộn cũng lệch. Admin sửa FAQ trong
  panel thì mảng hardcode không bao giờ đổi theo.

### 2. Brand lặp 2 lần trong title

Lưu ý: `app.blade.php` **không** tự nối brand — nó dùng nguyên `seo.title` (chỉ khi
thiếu hẳn thì mới rơi về mặc định có brand). `SeoService::page()` cũng truyền thẳng.

Brand bị nối ở **controller**, cụ thể `StaticPageController` làm
`$page->title.' | BỐP CAMPING'`. Mà `$page->title` do admin nhập trong panel và người
nhập thường tự gõ luôn brand → thành:

```
Chính sách bảo mật — BỐP CAMPING | BỐP CAMPING
Về BỐP CAMPING — thuê đồ dã ngoại sạch, đủ, chuẩn | BỐP CAMPING
```

Ăn mất chỗ trong ~60 ký tự SERP. Đây là loại lỗi **không nhìn ra khi đọc code** vì một
nửa nguyên nhân nằm trong dữ liệu admin nhập, không nằm trong repo.

→ Quy ước: nơi nào đã nối brand thì tiêu đề nguồn **không được** chứa brand. Đừng nối
brand ở nhiều tầng.

### 3. Trang mới quên khai sitemap

`SitemapController` liệt kê **thủ công**, không tự quét route. Thêm trang public mà quên
thì Google không biết. Đã sót thật: 5 trang chính sách (`/chinh-sach-*`,
`/dieu-khoan-su-dung`) — đều 200 và có canonical, nhưng không có trong sitemap.

Đối chiếu nhanh:

```bash
php artisan route:list --method=GET --json   # route công khai
curl -s https://bopcamping.com/sitemap.xml   # đã khai gì
```

### 4. Đo SEO bằng cách đọc code là sai

Site là SPA nên phải phân biệt hai thứ crawler thấy:

| | Cách đo | Thấy gì |
|---|---|---|
| Lượt đầu (HTML thô) | `curl` | `<head>`: meta, OG, JSON-LD, canonical |
| Lượt render JS | Chrome DevTools, đọc DOM | `h1`, heading, `alt` ảnh, nội dung |

Kiểm `h1` bằng `curl` sẽ luôn ra **0** và tưởng nhầm là lỗi — thực tế React render sau.

## Test — SEO hỏng thì nhìn trang vẫn thấy bình thường

Đây là loại lỗi im lặng, nên **bắt buộc có test**. Sẵn có: `tests/Feature/SeoTest.php`,
`SeoMetaTest.php`, `SitemapTest.php`.

```php
// Trang mới phải có SEO RIÊNG, không rơi vào mặc định site-wide
$this->get('/trang-moi')->assertInertia(fn ($p) => $p
    ->where('seo.title', 'Tên riêng của trang')
    ->has('seo.description'));

// Schema theo nội dung phải khớp thứ đang render
$this->get('/')->assertSee('"FAQPage"', false);
$this->get('/thiet-bi/x')->assertDontSee('"FAQPage"', false);  // trang SP không có FAQ
```

Khi sửa JSON-LD: khẳng định cả **có** ở trang đúng lẫn **không có** ở trang sai. Chỉ
kiểm chiều "có" sẽ không bắt được lỗi nhét schema vào mọi trang.

## Lệnh hay dùng

```bash
# Liệt kê loại JSON-LD của một trang (xử lý được cả mảng schema)
curl -s <url> | python3 -c "
import sys,re,json
for b in re.findall(r'application/ld\+json\">(.*?)</script>', sys.stdin.read(), re.S):
    d=json.loads(b)
    print([x.get('@type') for x in (d if isinstance(d,list) else [d])])"

# Độ dài title/description (title ~60, desc 120–160)
curl -s <url> | grep -oE '<title>[^<]*|name="description" content="[^"]*'
```

## Không thuộc phạm vi

- Không mua backlink, không tạo trang vệ tinh, không nhồi từ khoá.
- Không khai structured data cho thứ không hiện trên trang (xem Cạm bẫy #1) — đây là
  vi phạm chính sách Google, không phải mẹo.

## Tham chiếu

- Audit gần nhất: `artifacts/seo_audit_2026-08-11.md`
- Google Search Central — Structured data guidelines
