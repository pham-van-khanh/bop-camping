# BopCamping — Website cho thuê đồ Camping

Web cho thuê thiết bị cắm trại của **một shop duy nhất**. Khách đặt thuê **theo ngày** (có kiểm tra trùng lịch + tồn kho), **có thu tiền cọc**, thanh toán **COD**. Đăng nhập khách bằng **SĐT + tên + email**, xác thực **OTP 6 số gửi qua email** (chỉ cần OTP lần đầu / khi email chưa verify; verify rồi thì SĐT+tên vào thẳng). Không dùng mật khẩu cho khách. Giao diện tông **be / màu đất kiểu Naturehike**.

> Kế hoạch chi tiết, mô hình dữ liệu và bảng màu nằm trong [KE_HOACH.md](KE_HOACH.md) — đọc file đó trước khi triển khai tính năng.

**Trạng thái hiện tại:** đã qua giai đoạn scaffold — **Laravel 12.62 + Breeze/Inertia/React/TypeScript** cài đầy đủ, build chạy được (`composer run dev`). DB dev = **MySQL 8** qua Docker (container `bopcamping_db`, port 3307; SQLite chỉ còn file rỗng không dùng). Toàn bộ migration đã apply.

Đã triển khai: shop (danh sách/chi tiết sản phẩm, giỏ hàng, checkout/đơn hàng, tra cứu đơn, đăng nhập khách qua OTP email, review + review-invite, voucher/khuyến mãi, referral, địa điểm phục vụ/camping spot) và trang quản trị admin (dashboard, sản phẩm, danh mục, đơn hàng, banner, user, voucher, review, referral, promotion, service location) — xem `app/Http/Controllers/{Shop,Admin}` và `resources/js/Pages/{*,Admin}`. Xem `bd ready` để biết việc tiếp theo.

> **Git/Beads:** repo đã có remote (`origin` → GitHub). Các bước "git push" trong phần Session Completion phía dưới **áp dụng như bình thường**.

## Tech Stack (đã chốt)

Laravel 12 (PHP) · Inertia.js · React + TypeScript · Tailwind CSS · shadcn/ui · Laravel Breeze (auth) · DB = SQLite khi dev (MySQL khi deploy) · Vite.

## Kiến trúc (dự kiến sau khi scaffold)

Monolith Laravel + Inertia — **không có REST API riêng**. Request đi: route → controller → `Inertia::render('Page', $props)` → component React nhận props.

```
app/Http/Controllers/   # Controller cho cả khách (Shop/) và admin (Admin/)
app/Models/             # Eloquent: Category, Product, ProductImage, Order, OrderItem
app/Services/           # Logic nghiệp vụ tách khỏi controller (vd: AvailabilityService)
database/migrations/    # Schema (xem mô hình dữ liệu ở KE_HOACH.md mục 4)
database/seeders/       # Dữ liệu mẫu (danh mục, sản phẩm demo)
resources/js/Pages/     # Trang Inertia (React) — Shop/* và Admin/*
resources/js/Components/# Component dùng lại + shadcn/ui (resources/js/Components/ui)
resources/js/Layouts/   # Layout chung (header/footer tông be Naturehike)
routes/web.php          # Toàn bộ route (web + admin), không dùng routes/api.php
tests/Feature/          # Test luồng (đặt thuê, kiểm tra trùng lịch...)
```

**Logic cốt lõi — tính tồn kho theo ngày** (single source of truth, đặt trong 1 service, KHÔNG lặp lại ở nhiều nơi): với mỗi sản phẩm + khoảng `[start_date, end_date]`, số lượng còn cho thuê = `products.quantity − (tổng quantity đã đặt trong các order chồng lịch)`. Hai khoảng chồng nhau khi `start_A <= end_B AND start_B <= end_A`. Mọi chỗ hiển thị "còn hàng / hết hàng" và validate lúc checkout đều gọi cùng hàm này.

## ⚠️ Lưu ý môi trường (QUAN TRỌNG — máy có nhiều bản PHP/Node)

Máy này có nhiều bản PHP cài qua Homebrew (7.1/7.2/7.4/8.1/8.3) và dùng **nvm** cho Node. Đã cấu hình đúng, nhưng nếu thấy sai phiên bản hãy kiểm tra:

- **PHP**: dùng bản brew mặc định `php` = **8.3.8**, link tại `/opt/homebrew/bin/php`. Các dòng PATH cũ trỏ tới `php@7.x` đã được gỡ khỏi `~/.zshrc`. (Laravel 12 cần PHP ≥ 8.2.)
- **Node**: quản lý bằng **nvm**, mặc định đặt là **Node 20** (`nvm alias default 20`). Bản brew `node` (22) bị nvm "đè" — đây là chủ ý. (React/Vite cần Node ≥ 20.)
- Verify nhanh trong terminal mới: `php -v` → 8.3.x, `node -v` → v20.x.

### Nhiều máy dev, mỗi máy một kiểu (PHP 8.3/8.4/8.5) — quy ước chống lệch

- **composer.json đã pin `config.platform.php = 8.3.8`** (golden path + PHP prod). Máy chạy PHP 8.4/8.5 vẫn dev bình thường, nhưng `composer update` LUÔN resolve theo 8.3 → không bao giờ tạo lock mà máy 8.3 không cài được. KHÔNG gỡ pin này.
- **Test trên máy thiếu `pdo_sqlite`** (vd Linux không sudo): chạy bằng MySQL với DB test riêng:
  `DB_CONNECTION=mysql DB_DATABASE=bop_camping_test php artisan test`.
  Test phải **collation-safe** — chạy đúng trên cả sqlite (LIKE so byte) lẫn MySQL `utf8mb4_unicode_ci` (LIKE không phân biệt dấu: 'leu' khớp 'lều'). Khi viết test search: đừng để dữ liệu "nhiễu" chứa từ khoá dạng có-dấu trong name/description.

## Quick Reference

```bash
# Chạy app (sau khi đã scaffold) — Laravel 12 có sẵn lệnh gộp:
composer run dev          # chạy đồng thời: serve + QUEUE WORKER + vite + logs

# Hoặc chạy riêng:
php artisan serve         # backend tại http://localhost:8000
npm run dev               # Vite dev server (frontend assets)
php artisan queue:work    # BẮT BUỘC để gửi mail (OTP, xác nhận đơn...) — mail là ShouldQueue,
                          # chạy nền qua queue. KHÔNG có worker = mail nằm chờ, KHÔNG gửi.

# Ảnh: sinh biến thể WebP đã resize (400/800/1600px) cho ảnh cũ — idempotent.
# Ảnh MỚI upload tự sinh qua queue, nên cần queue worker chạy.
php artisan media:variants            # backfill
php artisan media:variants --dry-run  # chỉ đếm

# Database (SQLite khi dev)
php artisan migrate           # chạy migration
php artisan migrate:fresh --seed   # reset DB + seed lại dữ liệu mẫu

# Test
php artisan test                       # chạy toàn bộ test
php artisan test --filter=TenTest      # chạy một test cụ thể

# Lint JS/TS
npm run lint       # QUALITY GATE — chỉ kiểm, KHÔNG sửa file. Phải pass trước khi commit.
npm run lint:fix   # tự sửa format (eslint --fix) — chạy khi lint báo lỗi, rồi xem lại diff

# Build production
npm run build
```

> ⚠️ **Không thêm `--fix` vào script `lint`.** Gate phải là read-only; nếu nó tự sửa
> file thì "lint pass" chẳng chứng minh được gì và mọi thay đổi nhỏ đều đẻ ra diff
> khổng lồ. Chi tiết ở [.claude/rules/tech-strategy.md](.claude/rules/tech-strategy.md).

## Core Principles

These seven principles distill every rule, skill, and standard in this framework. Follow them and everything else follows.

### 1. Understand First
Read before writing; grep before creating; verify APIs via docs before assuming training data is current.

### 2. Prove It Works
Write tests first, run quality gates (tests, linter, types, build) before every commit, and add a regression test for every bug fix.

### 3. Keep It Safe
No secrets in code, validate all input, use parameterized queries, apply least privilege, and flag vulnerabilities immediately.

### 4. Keep It Simple
Single responsibility, no premature abstraction, delete dead code, avoid `any` types, fix warnings before committing.

### 5. Don't Repeat Yourself
Check `.claude/skills/` before generating ad-hoc solutions; maintain a single source of truth for business logic.

### 6. Ship It
Work on a branch, commit iteratively, and push to remote — work isn't done until `git push` succeeds.

### 7. Leave a Trail
Artifacts in `./artifacts/`, track work with Beads (`bd` CLI), document decisions in ADRs, name things clearly.

Full details in `.claude/rules/` (auto-loaded).

## Workflow

**Branching (quy ước từ 04/07/2026)** — `feat/scaffold-laravel` là **nhánh chính** (tích hợp); `develop` là **nhánh stg** để test. `main` hiện KHÔNG dùng làm nhánh tích hợp. Luồng chuẩn cho mỗi tính năng:

1. Tạo feature branch **từ `feat/scaffold-laravel`** (vd `feature/<ten-viec>`).
2. Merge feature branch vào `develop` rồi push — user test trên stg.
3. Test OK → merge **feature branch** (KHÔNG merge `develop` — trên đó có thể còn feature khác đang test dở) vào `feat/scaffold-laravel` rồi push.

Không commit trực tiếp vào `main`/`feat/scaffold-laravel`/`develop` (ngoại lệ: chore nhỏ như docs, beads-sync vào nhánh chính). Khi `develop` bẩn/lệch, reset về `feat/scaffold-laravel` (force-push) — mọi thứ trên đó đều đã có ở feature branch gốc.

**Planning flow**: PR-FAQ → PRD → ADR → Design Spec → Plan → Implementation Beads

**Artifacts**: All planning docs stored in `./artifacts/`:

| Type | Pattern | Example |
|------|---------|---------|
| Vision | `pr_faq_[feature].md` | `pr_faq_user_auth.md` |
| Requirements | `prd_[feature].md` | `prd_user_auth.md` |
| Architecture | `adr_[topic].md` | `adr_database_choice.md` |
| System Design | `system_design_[component].md` | `system_design_api.md` |
| Design | `design_spec_[component].md` | `design_spec_login_form.md` |
| Roadmap | `roadmap_[project].md` | `roadmap_mvp.md` |
| Plan | `plan_[task].md` | `plan_api_refactor.md` |
| Security Audit | `security_audit_[date].md` | `security_audit_2025-01.md` |
| Post-Mortem | `postmortem_[incident-id].md` | `postmortem_inc-2025-001.md` |

**Beads** (issue tracking — CLI saves 98% tokens vs MCP):

```bash
bd create "Task"                        # Create
bd ready                                # Find unblocked work
bd show <id>                            # View details
bd update <id> --status in_progress     # Claim
bd close <id>                           # Complete
bd sync                                 # Sync with git
```

See `beads-workflow` skill for complete command reference.

## Working Directories

| Directory | Purpose | Lifecycle |
|-----------|---------|-----------|
| `./artifacts/` | Durable documents (plans, ADRs, PRDs, design specs) | Committed to repo |
| `./scratchpad/` | Ephemeral working notes, exploration output, draft content | Gitignored, disposable |

## Commands

| Command | Role | Use |
|---------|------|-----|
| `/architect` | Principal Architect | System design, ADRs |
| `/builder` | Software Engineer | Implementation, debugging, testing |
| `/qa-engineer` | QA Engineer | Test strategy, E2E, accessibility |
| `/security-auditor` | Security Auditor | Threat modeling, audits |
| `/ui-ux-designer` | UI/UX Designer | Interface design, a11y |
| `/code-check` | Codebase Auditor | SOLID, DRY, consistency audits |
| `/swarm-plan` | Planning Orchestrator | Parallel exploration, decomposition |
| `/swarm-execute` | Execution Orchestrator | Parallel workers, quality gates |
| `/swarm-review` | Adversarial Reviewer | Multi-perspective code review |
| `/swarm-research` | Research Orchestrator | Deep investigation, technology evaluation |

## MCP Tools

| Tool | Use For |
|------|---------|
| Sequential Thinking | Complex analysis, trade-off evaluation |
| Chrome DevTools | Browser testing, performance profiling |
| Context7 | Library documentation lookup |
| Filesystem | File system operations beyond workspace |

## Skills

Check `.claude/skills/` before ad-hoc generation. Skills are auto-suggested based on context via `.claude/skills/skill-rules.json`.


<!-- BEGIN BEADS INTEGRATION v:1 profile:minimal hash:7510c1e2 -->
## Beads Issue Tracker

This project uses **bd (beads)** for issue tracking. Run `bd prime` to see full workflow context and commands.

### Quick Reference

```bash
bd ready              # Find available work
bd show <id>          # View issue details
bd update <id> --claim  # Claim work
bd close <id>         # Complete work
```

### Rules

- Use `bd` for ALL task tracking — do NOT use TodoWrite, TaskCreate, or markdown TODO lists
- Run `bd prime` for detailed command reference and session close protocol
- Use `bd remember` for persistent knowledge — do NOT use MEMORY.md files

**Architecture in one line:** issues live in a local Dolt DB; sync uses `refs/dolt/data` on your git remote; `.beads/issues.jsonl` is a passive export. See https://github.com/gastownhall/beads/blob/main/docs/SYNC_CONCEPTS.md for details and anti-patterns.

## Session Completion

**When ending a work session**, you MUST complete ALL steps below. Work is NOT complete until `git push` succeeds.

**MANDATORY WORKFLOW:**

1. **File issues for remaining work** - Create issues for anything that needs follow-up
2. **Run quality gates** (if code changed) - Tests, linters, builds
3. **Update issue status** - Close finished work, update in-progress items
4. **PUSH TO REMOTE** - This is MANDATORY:
   ```bash
   git pull --rebase
   git push
   git status  # MUST show "up to date with origin"
   ```
5. **Clean up** - Clear stashes, prune remote branches
6. **Verify** - All changes committed AND pushed
7. **Hand off** - Provide context for next session

**CRITICAL RULES:**
- Work is NOT complete until `git push` succeeds
- NEVER stop before pushing - that leaves work stranded locally
- NEVER say "ready to push when you are" - YOU must push
- If push fails, resolve and retry until it succeeds
<!-- END BEADS INTEGRATION -->
