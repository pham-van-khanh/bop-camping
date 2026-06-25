# Runbook Deploy BopCamping lên VPS Linux

> Hướng dẫn từng bước dựng server và deploy. Quyết định kiến trúc: [adr_deployment.md](adr_deployment.md).
> Quy ước: thay `bopcamping.vn` bằng domain thật, `<...>` bằng giá trị của bạn.
> Lệnh `$` chạy trên VPS với user thường (có sudo) trừ khi ghi rõ.

---

## Giai đoạn 0 — Mua/đăng ký (làm trước, ngoài server)

- [ ] **Tên miền** — vd `bopcamping.vn` (Mắt Bão/PA/Tenten cho `.vn`, hoặc Cloudflare/Namecheap cho `.com`).
- [ ] **VPS** — Ubuntu 24.04 LTS, ≥1GB RAM (khuyến nghị 2GB). Lấy **IP public** + mật khẩu/SSH key root.
- [ ] **Tài khoản gửi mail SMTP** — Gmail App Password (Google Account → Security → App passwords; cần bật 2FA), hoặc Brevo/Resend.
- [ ] **Trỏ DNS:** tạo bản ghi A `@` và `www` → IP của VPS. Chờ DNS lan (vài phút–vài giờ).

---

## Giai đoạn 1 — Chuẩn bị code (trên máy dev, trước khi deploy)

Chạy quality gates, đảm bảo mọi thứ pass:

```bash
cd /Users/phamkhanh/Documents/khanh/bopcamping
php artisan test
./vendor/bin/pint --test
npm run build          # phải build thành công, tạo public/build
```

> ⚠️ **Test trên MySQL:** dev đang dùng SQLite. Nên chạy thử migrations trên MySQL 8 local
> một lần để chắc tương thích trước khi lên prod (xem mục "Kiểm tra MySQL" cuối file).

Commit toàn bộ thay đổi lên branch và merge về main (theo quy trình dự án).

---

## Giai đoạn 2 — Cấu hình ban đầu trên VPS

### 2.1 Đăng nhập & cập nhật hệ thống

```bash
ssh root@<IP_VPS>
adduser deploy && usermod -aG sudo deploy     # tạo user thường, tránh dùng root
apt update && apt upgrade -y
```

Từ giờ đăng nhập bằng `ssh deploy@<IP_VPS>`.

### 2.2 Cài stack (PHP 8.3, MySQL, Nginx, Node, Composer)

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

# PHP 8.3 + extension cần cho Laravel
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-sqlite3 \
  php8.3-mbstring php8.3-xml php8.3-bcmath php8.3-curl php8.3-zip \
  php8.3-gd php8.3-intl

# MySQL 8, Nginx, Supervisor, certbot, unzip, git
sudo apt install -y mysql-server nginx supervisor certbot python3-certbot-nginx unzip git

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node 20 (qua NodeSource)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 2.3 Tạo database MySQL

```bash
sudo mysql
```
```sql
CREATE DATABASE bopcamping CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'bopcamping'@'localhost' IDENTIFIED BY '<MAT_KHAU_DB_MANH>';
GRANT ALL PRIVILEGES ON bopcamping.* TO 'bopcamping'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## Giai đoạn 3 — Đưa code lên & cấu hình app

### 3.1 Lấy code

```bash
sudo mkdir -p /var/www/bopcamping && sudo chown deploy:deploy /var/www/bopcamping
cd /var/www
git clone <URL_REPO> bopcamping     # hoặc rsync từ máy dev nếu repo chỉ ở local
cd bopcamping
```

> Repo hiện **chỉ ở local** (chưa có remote). Nếu chưa thêm remote, đẩy code bằng:
> `rsync -avz --exclude node_modules --exclude vendor --exclude .git \
>   /Users/phamkhanh/Documents/khanh/bopcamping/ deploy@<IP_VPS>:/var/www/bopcamping/`

### 3.2 Cài dependency + build

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

### 3.3 Tạo file `.env` production

```bash
cp .env.example .env
nano .env
```

Đặt các giá trị sau (xem template đầy đủ ở mục cuối file):

```env
APP_NAME="BopCamping"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://bopcamping.vn

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bopcamping
DB_USERNAME=bopcamping
DB_PASSWORD=<MAT_KHAU_DB_MANH>

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=bopcamping.vn

QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=public

# Tài khoản admin (seed lần đầu) — ADMIN_PASSWORD bắt buộc ở production
ADMIN_PHONE=0976544370
ADMIN_EMAIL=admin@bopcamping.vn
ADMIN_PASSWORD=<MAT_KHAU_ADMIN_MANH>

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=<email_gui@gmail.com>
MAIL_PASSWORD=<app_password_16_ky_tu>
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS="no-reply@bopcamping.vn"
MAIL_FROM_NAME="BopCamping"
```

> Mẹo: copy nhanh template chuẩn bằng `cp .env.production.example .env` rồi điền giá trị.

```bash
php artisan key:generate          # sinh APP_KEY mới
```

### 3.4 Migrate + seed có kiểm soát

```bash
php artisan migrate --force        # --force vì đang ở production
php artisan db:seed --force        # seed danh mục, sản phẩm, admin
```

> ✅ Seeder đã được làm **prod-safe**: ở `APP_ENV=production`, `DatabaseSeeder` **không** tạo
> Test User demo, và `AdminUserSeeder` lấy mật khẩu từ `ADMIN_PASSWORD` trong `.env`
> (nếu để trống sẽ **báo lỗi**, không cho dùng mật khẩu mặc định). Vì vậy chỉ cần đặt
> `ADMIN_PASSWORD`/`ADMIN_EMAIL`/`ADMIN_PHONE` ở bước 3.3 là xong — không cần tinker.

### 3.5 Storage link + phân quyền

```bash
php artisan storage:link
sudo chown -R deploy:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 3.6 Cache cho production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Giai đoạn 4 — Nginx + HTTPS

### 4.1 Cấu hình Nginx

```bash
sudo nano /etc/nginx/sites-available/bopcamping
```
```nginx
server {
    listen 80;
    server_name bopcamping.vn www.bopcamping.vn;
    root /var/www/bopcamping/public;

    index index.php;
    charset utf-8;
    client_max_body_size 20M;          # cho upload ảnh review/sản phẩm

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```
```bash
sudo ln -s /etc/nginx/sites-available/bopcamping /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

### 4.2 SSL Let's Encrypt

```bash
sudo certbot --nginx -d bopcamping.vn -d www.bopcamping.vn
# Chọn redirect HTTP → HTTPS khi được hỏi. Certbot tự thêm cron gia hạn.
```

---

## Giai đoạn 5 — Queue worker (BẮT BUỘC cho mail)

```bash
sudo nano /etc/supervisor/conf.d/bopcamping-worker.conf
```
```ini
[program:bopcamping-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/bopcamping/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=deploy
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/bopcamping/storage/logs/worker.log
stopwaitsecs=3600
```
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start bopcamping-worker:*
sudo supervisorctl status         # phải thấy RUNNING
```

> Sau mỗi lần deploy code mới, nhớ `sudo supervisorctl restart bopcamping-worker:*`
> để worker nạp code mới (worker giữ code cũ trong RAM).

---

## Giai đoạn 6 — Kiểm tra (smoke test)

- [ ] Mở `https://bopcamping.vn` → trang chủ hiện, có khoá HTTPS, không có cảnh báo.
- [ ] Vào trang thuê đồ → đặt thử một đơn → **nhận được mail OTP** (kiểm tra queue chạy).
- [ ] Đăng nhập admin (`/admin/...`) bằng mật khẩu mới → vào được dashboard.
- [ ] Ảnh sản phẩm hiển thị (storage link OK).
- [ ] Chuyển một đơn sang `returned` → khách **nhận mail mời đánh giá**.
- [ ] Kiểm tra log không có lỗi: `tail -f storage/logs/laravel.log` và `worker.log`.
- [ ] Thử mở một URL sai → **không** lộ stack trace (xác nhận `APP_DEBUG=false`).

---

## Quy trình deploy lần sau (cập nhật code)

```bash
cd /var/www/bopcamping
php artisan down                      # bật maintenance mode
git pull                              # hoặc rsync code mới
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo supervisorctl restart bopcamping-worker:*
php artisan up                        # tắt maintenance mode
```

---

## TODO sau khi lên sóng (chưa làm ở lần deploy đầu)

- [ ] **Backup tự động:** cron dump MySQL hằng ngày + backup `storage/app/public` (ảnh). Đẩy ra nơi khác (vd object storage).
- [ ] **Theo dõi uptime** (UptimeRobot free) + cảnh báo khi web down.
- [ ] **Firewall:** `sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable`.
- [ ] **Cron scheduler** — chỉ thêm KHI có tác vụ định kỳ (vd dọn OTP hết hạn):
  `* * * * * cd /var/www/bopcamping && php artisan schedule:run >> /dev/null 2>&1`

---

## Phụ lục — Kiểm tra migrations trên MySQL (chạy trên máy dev)

Trước khi tin tưởng deploy, xác nhận 27 migrations chạy sạch trên MySQL 8:

```bash
# Cần MySQL local. Tạo DB tạm rồi trỏ .env.testing hoặc biến môi trường:
mysql -u root -e "CREATE DATABASE bopcamping_test CHARACTER SET utf8mb4;"
DB_CONNECTION=mysql DB_DATABASE=bopcamping_test DB_USERNAME=root DB_PASSWORD= \
  php artisan migrate:fresh --force
# Không lỗi = tương thích. Xoá DB tạm sau khi xong.
```

## Phụ lục — Template `.env` production đầy đủ

> Lưu ý: **không commit** file `.env`. Đây là tham chiếu giá trị.

```env
APP_NAME="BopCamping"
APP_ENV=production
APP_KEY=                      # php artisan key:generate sinh ra
APP_DEBUG=false
APP_URL=https://bopcamping.vn

APP_LOCALE=vi
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stack
LOG_LEVEL=error               # prod chỉ log lỗi, tránh ồn

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bopcamping
DB_USERNAME=bopcamping
DB_PASSWORD=<MAT_KHAU_DB_MANH>

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=bopcamping.vn

QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=public
BROADCAST_CONNECTION=log

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=<email_gui@gmail.com>
MAIL_PASSWORD=<app_password>
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS="no-reply@bopcamping.vn"
MAIL_FROM_NAME="BopCamping"

VITE_APP_NAME="${APP_NAME}"
```
